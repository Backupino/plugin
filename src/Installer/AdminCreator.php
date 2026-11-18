<?php
/**
 * Safe WordPress Administrator account creator for the standalone installer.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Offline standalone installer exceptions.

/**
 * Inserts a brand-new WordPress Administrator account during restoration without
 * ever modifying, overwriting, or escalating any existing user account.
 */
final class AdminCreator {

	/**
	 * Hash a plaintext password using bcrypt (`$2y$`), which is verified natively
	 * by both WordPress 6.0-6.7 (via PasswordHash::CheckPassword -> crypt()) and
	 * WordPress 6.8+ (via password_verify()).
	 *
	 * @param string $password Plaintext password.
	 * @return string Bcrypt password hash.
	 *
	 * @throws \RuntimeException If password hashing fails.
	 */
	public static function hash_password( string $password ): string {
		if ( '' === $password ) {
			throw new \RuntimeException( 'Administrator password is required.' );
		}

		$hash = password_hash( $password, PASSWORD_BCRYPT );
		if ( ! is_string( $hash ) || '' === $hash || 0 !== strpos( $hash, '$2y$' ) ) {
			throw new \RuntimeException( 'Failed to securely hash administrator password.' );
		}

		return $hash;
	}

	/**
	 * Create a brand-new WordPress Administrator account using INSERT only.
	 *
	 * Checks username and email collisions separately and refuses to modify any
	 * existing user record or usermeta. Returns only non-sensitive account info
	 * after verifying that the user row and required capabilities were written.
	 *
	 * @param Connection $connection    Database connection.
	 * @param string     $table_prefix  Healed WordPress table prefix.
	 * @param string     $username      Desired login name.
	 * @param string     $email         Desired email address.
	 * @param string     $password_hash Pre-computed password hash (never plaintext).
	 * @return array{user_id: int, username: string}
	 *
	 * @throws \RuntimeException On validation error, username/email collision, or DB failure.
	 */
	public static function create(
		Connection $connection,
		string $table_prefix,
		string $username,
		string $email,
		string $password_hash
	): array {
		$username = trim( $username );
		$email    = trim( $email );

		if ( '' === $username || strlen( $username ) > 60 ) {
			throw new \RuntimeException( 'Administrator username is invalid or exceeds 60 characters.' );
		}

		if ( '' === $email || strlen( $email ) > 100 || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \RuntimeException( 'A valid administrator email address is required.' );
		}

		if ( '' === $password_hash ) {
			throw new \RuntimeException( 'Administrator password hash is missing.' );
		}

		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_prefix ) ) {
			throw new \RuntimeException( 'Invalid table prefix for administrator account creation.' );
		}

		$escaped_prefix = str_replace( '`', '``', $table_prefix );
		$users_table    = $escaped_prefix . 'users';
		$meta_table     = $escaped_prefix . 'usermeta';

		// 1. Check username and email collisions SEPARATELY so existing accounts are never touched.
		$login_rows = $connection->fetch_all(
			"SELECT ID FROM `{$users_table}` WHERE LOWER(user_login) = LOWER(?) LIMIT 1",
			array( $username )
		);
		$email_rows = $connection->fetch_all(
			"SELECT ID FROM `{$users_table}` WHERE LOWER(user_email) = LOWER(?) LIMIT 1",
			array( $email )
		);

		$has_login_collision = ! empty( $login_rows );
		$has_email_collision = ! empty( $email_rows );

		if ( $has_login_collision && $has_email_collision ) {
			throw new \RuntimeException(
				'Cannot create new administrator account: both the username and email address are already in use by existing accounts.'
			);
		}

		if ( $has_login_collision ) {
			throw new \RuntimeException(
				'Cannot create new administrator account: the username is already in use by an existing account.'
			);
		}

		if ( $has_email_collision ) {
			throw new \RuntimeException(
				'Cannot create new administrator account: the email address is already in use by an existing account.'
			);
		}

		// 2. Capture current max ID so we only ever match our own newly inserted row.
		$max_rows    = $connection->fetch_all( "SELECT MAX(ID) AS max_id FROM `{$users_table}`" );
		$prev_max_id = isset( $max_rows[0]['max_id'] ) ? (int) $max_rows[0]['max_id'] : 0;

		// 3. Build a unique user_nicename without modifying existing rows.
		$base_nicename = substr( (string) preg_replace( '/[^a-z0-9_\.-]/i', '-', strtolower( $username ) ), 0, 50 );
		$base_nicename = trim( $base_nicename, '-' );
		if ( '' === $base_nicename ) {
			$base_nicename = 'admin';
		}

		$nicename = $base_nicename;
		$suffix   = 2;
		while ( $suffix <= 100 ) {
			$nick_rows = $connection->fetch_all(
				"SELECT ID FROM `{$users_table}` WHERE LOWER(user_nicename) = LOWER(?) LIMIT 1",
				array( $nicename )
			);
			if ( empty( $nick_rows ) ) {
				break;
			}
			$tag      = '-' . $suffix;
			$nicename = substr( $base_nicename, 0, 50 - strlen( $tag ) ) . $tag;
			++$suffix;
		}

		// 4. INSERT new user strictly (never UPDATE existing accounts).
		$now = gmdate( 'Y-m-d H:i:s' );
		try {
			$inserted = $connection->execute(
				"INSERT INTO `{$users_table}` (user_login, user_pass, user_nicename, user_email, user_registered, user_status, display_name) VALUES (?, ?, ?, ?, ?, 0, ?)",
				array( $username, $password_hash, $nicename, $email, $now, $username )
			);
		} catch ( \Throwable $e ) {
			$inserted = false;
		}

		if ( ! $inserted ) {
			throw new \RuntimeException(
				'Failed to create new administrator account: database insert failed or conflicted with an existing unique record.'
			);
		}

		// 5. Locate the exact newly inserted user ID strictly greater than prior MAX(ID).
		$created_rows = $connection->fetch_all(
			"SELECT ID FROM `{$users_table}` WHERE ID > ? AND user_login = ? AND user_email = ? ORDER BY ID DESC LIMIT 1",
			array( $prev_max_id, $username, $email )
		);

		$new_user_id = isset( $created_rows[0]['ID'] ) ? (int) $created_rows[0]['ID'] : 0;
		if ( $new_user_id <= 0 ) {
			throw new \RuntimeException( 'Failed to verify newly created administrator account ID.' );
		}

		// 6. Guard against concurrent race conditions on non-UNIQUE MySQL indexes.
		$login_count_rows = $connection->fetch_all(
			"SELECT COUNT(*) AS cnt FROM `{$users_table}` WHERE LOWER(user_login) = LOWER(?)",
			array( $username )
		);
		$email_count_rows = $connection->fetch_all(
			"SELECT COUNT(*) AS cnt FROM `{$users_table}` WHERE LOWER(user_email) = LOWER(?)",
			array( $email )
		);
		$login_count = isset( $login_count_rows[0]['cnt'] ) ? (int) $login_count_rows[0]['cnt'] : 1;
		$email_count = isset( $email_count_rows[0]['cnt'] ) ? (int) $email_count_rows[0]['cnt'] : 1;

		if ( $login_count > 1 || $email_count > 1 ) {
			self::rollback_new_user( $connection, $users_table, $meta_table, $new_user_id );

			throw new \RuntimeException(
				'Cannot create new administrator account: a concurrent conflict was detected for the username or email address.'
			);
		}

		// 7. Insert usermeta strictly for the new user ID with the healed table prefix.
		$cap_key   = $table_prefix . 'capabilities';
		$level_key = $table_prefix . 'user_level';
		$cap_val   = 'a:1:{s:13:"administrator";b:1;}';
		$level_val = '10';

		$required_meta = array(
			$cap_key   => $cap_val,
			$level_key => $level_val,
		);

		$optional_meta = array(
			'nickname'             => $username,
			'first_name'           => '',
			'last_name'            => '',
			'description'          => '',
			'rich_editing'         => 'true',
			'syntax_highlighting'  => 'true',
			'comment_shortcuts'    => 'false',
			'admin_color'          => 'fresh',
			'use_ssl'              => '0',
			'show_admin_bar_front' => 'true',
		);

		foreach ( array_merge( $required_meta, $optional_meta ) as $m_key => $m_val ) {
			try {
				$ok = $connection->execute(
					"INSERT INTO `{$meta_table}` (user_id, meta_key, meta_value) VALUES (?, ?, ?)",
					array( $new_user_id, $m_key, $m_val )
				);
			} catch ( \Throwable $e ) {
				$ok = false;
			}

			if ( ! $ok && array_key_exists( $m_key, $required_meta ) ) {
				self::rollback_new_user( $connection, $users_table, $meta_table, $new_user_id );

				throw new \RuntimeException( 'Failed to assign administrator capabilities to the new account.' );
			}
		}

		// 8. Verify both required capability rows exist before reporting success.
		$verify_rows = $connection->fetch_all(
			"SELECT meta_key, meta_value FROM `{$meta_table}` WHERE user_id = ? AND meta_key IN (?, ?)",
			array( $new_user_id, $cap_key, $level_key )
		);

		$verified = array();
		foreach ( $verify_rows as $vrow ) {
			if ( isset( $vrow['meta_key'] ) ) {
				$verified[ (string) $vrow['meta_key'] ] = (string) ( $vrow['meta_value'] ?? '' );
			}
		}

		if (
			( $verified[ $cap_key ] ?? '' ) !== $cap_val ||
			( $verified[ $level_key ] ?? '' ) !== $level_val
		) {
			self::rollback_new_user( $connection, $users_table, $meta_table, $new_user_id );

			throw new \RuntimeException( 'Failed to verify administrator capabilities for the new account.' );
		}

		return array(
			'user_id'  => $new_user_id,
			'username' => $username,
		);
	}

	/**
	 * Remove only the newly created user and its usermeta on rollback.
	 *
	 * @param Connection $connection  Database connection.
	 * @param string     $users_table Escaped users table name.
	 * @param string     $meta_table  Escaped usermeta table name.
	 * @param int        $user_id     Newly inserted user ID.
	 * @return void
	 */
	private static function rollback_new_user( Connection $connection, string $users_table, string $meta_table, int $user_id ): void {
		try {
			$connection->execute( "DELETE FROM `{$meta_table}` WHERE user_id = ?", array( $user_id ) );
		} catch ( \Throwable $e ) {
			// Best-effort cleanup.
		}

		try {
			$connection->execute( "DELETE FROM `{$users_table}` WHERE ID = ?", array( $user_id ) );
		} catch ( \Throwable $e ) {
			// Best-effort cleanup.
		}
	}
}
