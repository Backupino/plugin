<?php
/**
 * Installer state persistence.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Standalone installer running outside WordPress environment.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Offline standalone installer exceptions.

/**
 * Stores wizard progress between AJAX requests inside a per-run private `0700`
 * directory strictly outside the web document root, wrapped in an executable
 * PHP 403 guard and authenticated encryption using a server-only secret.
 */
final class State {

	/**
	 * Executable PHP guard prepended to every state file so direct HTTP access
	 * fails closed even on misconfigured web servers (Apache, Nginx, IIS).
	 *
	 * @var string
	 */
	public const GUARD_PREFIX = "<?php http_response_code( 403 ); exit; ?>\n";

	/**
	 * Prefix for per-run private state directories.
	 *
	 * @var string
	 */
	public const RUN_DIR_PREFIX = 'backupino-run-';

	/**
	 * Filename of the state file inside a private run directory.
	 *
	 * @var string
	 */
	public const STATE_FILENAME = 'state.php';

	/**
	 * Absolute path of the state file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Absolute path of the private per-run directory (when managed as a private run).
	 *
	 * @var string
	 */
	private $run_dir;

	/**
	 * Opaque signed run handle (nonce.hmac) referencing the private run directory.
	 *
	 * @var string
	 */
	private $run_handle;

	/**
	 * Server-only secret key used for authenticated encryption at rest.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * When true, corrupted, tampered, or undecryptable state fails closed by throwing.
	 *
	 * @var bool
	 */
	private $strict;

	/**
	 * State data.
	 *
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * Constructor.
	 *
	 * @param string $file       Absolute path of the state file.
	 * @param string $secret     Optional server-only secret key for authenticated encryption.
	 * @param string $run_dir    Optional owning private run directory.
	 * @param string $run_handle Optional signed run handle.
	 * @param bool   $strict     Fail closed on missing/tampered state during load.
	 */
	public function __construct(
		string $file,
		string $secret = '',
		string $run_dir = '',
		string $run_handle = '',
		bool $strict = false
	) {
		$this->file       = $file;
		$this->secret     = $secret;
		$this->run_dir    = $run_dir;
		$this->run_handle = $run_handle;
		$this->strict     = $strict;
		$this->load();
	}

	/**
	 * Normalize a filesystem path for cross-platform prefix comparisons.
	 *
	 * @param string $path Raw filesystem path.
	 * @return string Normalized path without trailing slash (except root).
	 */
	public static function normalize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}

		$resolved = @realpath( $path );
		if ( is_string( $resolved ) && '' !== $resolved ) {
			$path = $resolved;
		}

		$path   = str_replace( '\\', '/', $path );
		$prefix = '';

		if ( preg_match( '/^([A-Za-z]:)\/(.*)$/', $path, $m ) ) {
			$prefix = $m[1] . '/';
			$rest   = $m[2];
		} elseif ( 0 === strpos( $path, '/' ) ) {
			$prefix = '/';
			$rest   = ltrim( $path, '/' );
		} else {
			$rest = $path;
		}

		$segments = array();
		foreach ( explode( '/', $rest ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $segments );
				continue;
			}
			$segments[] = $part;
		}

		$normalized = $prefix . implode( '/', $segments );

		return '' === $normalized ? $prefix : $normalized;
	}

	/**
	 * Check whether a candidate directory is strictly outside the installer
	 * working directory and the web server document root.
	 *
	 * @param string      $candidate_dir Candidate storage directory.
	 * @param string      $web_dir       Installer working directory.
	 * @param string|null $doc_root      Web server DOCUMENT_ROOT, if known.
	 * @return bool
	 */
	public static function is_outside_webroot( string $candidate_dir, string $web_dir, ?string $doc_root = null ): bool {
		$candidate = self::normalize_path( $candidate_dir );
		if ( '' === $candidate || ! is_dir( $candidate_dir ) ) {
			return false;
		}

		$roots = array();
		$web   = self::normalize_path( $web_dir );
		if ( '' !== $web ) {
			$roots[] = $web;
		}
		if ( null !== $doc_root && '' !== trim( $doc_root ) ) {
			$doc = self::normalize_path( $doc_root );
			if ( '' !== $doc ) {
				$roots[] = $doc;
			}
		}

		$case_insensitive = ( '\\' === DIRECTORY_SEPARATOR ) || ( 1 === preg_match( '/^[A-Za-z]:\//', $candidate ) );
		$cmp_candidate    = $case_insensitive ? strtolower( $candidate ) : $candidate;

		foreach ( $roots as $root ) {
			$cmp_root = $case_insensitive ? strtolower( $root ) : $root;
			$cmp_root = rtrim( $cmp_root, '/' );
			if ( '' === $cmp_root ) {
				continue;
			}

			if ( $cmp_candidate === $cmp_root || 0 === strpos( $cmp_candidate, $cmp_root . '/' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find a writable base directory strictly outside the web document root and
	 * installer working directory where a private `0700` directory can be created.
	 *
	 * @param string                  $web_dir    Installer working directory.
	 * @param string|null             $doc_root   Web server DOCUMENT_ROOT.
	 * @param array<int, string>|null $candidates Optional explicit candidate list (for testing).
	 * @return string Normalized absolute path of the external base directory.
	 *
	 * @throws \RuntimeException If no secure external storage directory is available.
	 */
	public static function find_secure_base_dir(
		string $web_dir,
		?string $doc_root = null,
		?array $candidates = null
	): string {
		if ( null === $doc_root && isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Server document root fallback during standalone restore.
			$doc_root = (string) $_SERVER['DOCUMENT_ROOT'];
		}

		if ( null === $candidates ) {
			$candidates = self::default_candidate_dirs();
		}

		$checked = array();
		foreach ( $candidates as $dir ) {
			$dir = trim( (string) $dir );
			if ( '' === $dir || is_link( $dir ) || ! is_dir( $dir ) ) {
				continue;
			}

			$normalized = self::normalize_path( $dir );
			if ( '' === $normalized || isset( $checked[ $normalized ] ) ) {
				continue;
			}
			$checked[ $normalized ] = true;

			if ( ! self::is_outside_webroot( $normalized, $web_dir, $doc_root ) ) {
				continue;
			}

			$probe_dir = rtrim( $normalized, '/' ) . '/.backupino-probe-' . bin2hex( random_bytes( 8 ) );
			if ( ! @mkdir( $probe_dir, 0700, false ) ) {
				continue;
			}
			@chmod( $probe_dir, 0700 );
			$ok = ! is_link( $probe_dir ) && is_dir( $probe_dir ) && self::is_outside_webroot( $probe_dir, $web_dir, $doc_root );
			@rmdir( $probe_dir );

			if ( $ok ) {
				return $normalized;
			}
		}

		throw new \RuntimeException(
			'Secure temporary storage outside the web document root is unavailable. Installation stopped to protect sensitive credentials.'
		);
	}

	/**
	 * Atomically create a brand-new, private `0700` run directory outside the web root
	 * with a cryptographically random name independent of any browser-visible token.
	 *
	 * Rejects pre-existing directories, symlinks, invalid ownership, or unsafe permissions.
	 *
	 * @param string                  $web_dir      Installer working directory.
	 * @param string                  $state_secret Independent server-only secret (>= 32 chars).
	 * @param string                  $archive_name Archive basename for binding.
	 * @param string|null             $doc_root     Web server DOCUMENT_ROOT.
	 * @param array<int, string>|null $candidates   Optional explicit candidate list (for testing).
	 * @return self
	 *
	 * @throws \RuntimeException If secure private storage cannot be created or verified.
	 */
	public static function create_private_run(
		string $web_dir,
		string $state_secret,
		string $archive_name = '',
		?string $doc_root = null,
		?array $candidates = null
	): self {
		if ( strlen( trim( $state_secret ) ) < 32 ) {
			throw new \RuntimeException( 'Installer server-side security secret is missing or invalid.' );
		}

		$base_dir = self::find_secure_base_dir( $web_dir, $doc_root, $candidates );

		for ( $attempt = 0; $attempt < 8; ++$attempt ) {
			$nonce   = bin2hex( random_bytes( 16 ) );
			$run_dir = rtrim( $base_dir, '/' ) . '/' . self::RUN_DIR_PREFIX . $nonce;

			// Atomic creation (non-recursive) fails if directory or symlink already exists.
			if ( ! @mkdir( $run_dir, 0700, false ) ) {
				continue;
			}

			@chmod( $run_dir, 0700 );

			try {
				self::assert_private_dir_safe( $run_dir, $web_dir, $doc_root );
			} catch ( \RuntimeException $e ) {
				@rmdir( $run_dir );
				throw $e;
			}

			$handle     = $nonce . '.' . self::sign_run( $nonce, $web_dir, $archive_name, $state_secret );
			$run_secret = self::derive_run_secret( $nonce, $web_dir, $state_secret );
			$state_file = $run_dir . '/' . self::STATE_FILENAME;

			$state = new self( $state_file, $run_secret, $run_dir, $handle, false );
			if ( ! $state->save() ) {
				$state->destroy();
				throw new \RuntimeException( 'Failed to initialize secure installer state.' );
			}

			return $state;
		}

		throw new \RuntimeException(
			'Failed to allocate an exclusive private state directory. Installation stopped.'
		);
	}

	/**
	 * Open and verify an existing private run directory using its server-signed handle.
	 *
	 * Fails closed if the handle signature is invalid, the directory or state file is a
	 * symlink, owned by another user, inside webroot, pre-created, missing, or tampered.
	 *
	 * @param string                  $run_handle   Opaque signed handle (`<32-hex>.<64-hex>`).
	 * @param string                  $web_dir      Installer working directory.
	 * @param string                  $state_secret Independent server-only secret.
	 * @param string                  $archive_name Archive basename for binding.
	 * @param string|null             $doc_root     Web server DOCUMENT_ROOT.
	 * @param array<int, string>|null $candidates   Optional explicit candidate list (for testing).
	 * @return self
	 *
	 * @throws \RuntimeException On invalid handle, unsafe path/ownership, or tampered state.
	 */
	public static function open_private_run(
		string $run_handle,
		string $web_dir,
		string $state_secret,
		string $archive_name = '',
		?string $doc_root = null,
		?array $candidates = null
	): self {
		if ( strlen( trim( $state_secret ) ) < 32 ) {
			throw new \RuntimeException( 'Installer server-side security secret is missing or invalid.' );
		}

		if ( 1 !== preg_match( '/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $run_handle, $m ) ) {
			throw new \RuntimeException( 'Invalid or missing installer session handle.' );
		}

		$nonce        = $m[1];
		$provided_sig = $m[2];
		$expected_sig = self::sign_run( $nonce, $web_dir, $archive_name, $state_secret );

		if ( ! hash_equals( $expected_sig, $provided_sig ) ) {
			throw new \RuntimeException( 'Installer session verification failed.' );
		}

		if ( null === $doc_root && isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Server document root fallback during standalone restore.
			$doc_root = (string) $_SERVER['DOCUMENT_ROOT'];
		}

		if ( null === $candidates ) {
			$candidates = self::default_candidate_dirs();
		}

		$run_dir = '';
		$checked = array();
		foreach ( $candidates as $dir ) {
			$dir = trim( (string) $dir );
			if ( '' === $dir || is_link( $dir ) || ! is_dir( $dir ) ) {
				continue;
			}
			$normalized = self::normalize_path( $dir );
			if ( '' === $normalized || isset( $checked[ $normalized ] ) ) {
				continue;
			}
			$checked[ $normalized ] = true;

			$candidate_run = rtrim( $normalized, '/' ) . '/' . self::RUN_DIR_PREFIX . $nonce;
			if ( file_exists( $candidate_run ) || is_link( $candidate_run ) ) {
				$run_dir = $candidate_run;
				break;
			}
		}

		if ( '' === $run_dir ) {
			throw new \RuntimeException( 'Installer session state was not found or has expired.' );
		}

		self::assert_private_dir_safe( $run_dir, $web_dir, $doc_root );

		$state_file = $run_dir . '/' . self::STATE_FILENAME;
		self::assert_private_file_safe( $state_file, $run_dir );

		$run_secret = self::derive_run_secret( $nonce, $web_dir, $state_secret );

		return new self( $state_file, $run_secret, $run_dir, $run_handle, true );
	}

	/**
	 * Load state from disk. In strict mode, any missing, malformed, unguarded, or
	 * tampered state fails closed by throwing RuntimeException.
	 *
	 * @return void
	 *
	 * @throws \RuntimeException In strict mode when state cannot be verified or decrypted.
	 */
	public function load(): void {
		if ( is_link( $this->file ) ) {
			$this->data = array();
			if ( $this->strict ) {
				throw new \RuntimeException( 'Installer state file is unsafe (symlink detected).' );
			}

			return;
		}

		if ( ! is_readable( $this->file ) ) {
			$this->data = array();
			if ( $this->strict ) {
				throw new \RuntimeException( 'Installer state file is missing or unreadable.' );
			}

			return;
		}

		$raw = @file_get_contents( $this->file );
		if ( ! is_string( $raw ) ) {
			$this->data = array();
			if ( $this->strict ) {
				throw new \RuntimeException( 'Installer state file could not be read.' );
			}

			return;
		}

		if ( 0 !== strpos( $raw, self::GUARD_PREFIX ) ) {
			$this->data = array();
			if ( $this->strict ) {
				throw new \RuntimeException( 'Installer state integrity check failed.' );
			}
		} else {
			$raw = substr( $raw, strlen( self::GUARD_PREFIX ) );
		}

		$parsed = json_decode( $raw, true );
		if ( ! is_array( $parsed ) ) {
			$this->data = array();
			if ( $this->strict ) {
				throw new \RuntimeException( 'Installer state is corrupted or tampered.' );
			}

			return;
		}

		if ( '' !== $this->secret ) {
			$decrypted = $this->decrypt_envelope( $parsed );
			if ( ! is_array( $decrypted ) ) {
				$this->data = array();
				if ( $this->strict ) {
					throw new \RuntimeException( 'Installer state decryption or authentication failed.' );
				}

				return;
			}
			$this->data = $decrypted;

			return;
		}

		$this->data = $parsed;
	}

	/**
	 * Persist state atomically with PHP guard, server-secret authenticated encryption,
	 * and restrictive 0600 permissions inside the private run directory.
	 *
	 * @return bool
	 */
	public function save(): bool {
		if ( is_link( $this->file ) ) {
			return false;
		}

		if ( '' !== $this->run_dir && ( is_link( $this->run_dir ) || ! is_dir( $this->run_dir ) ) ) {
			return false;
		}

		$plaintext = json_encode( $this->data );
		if ( ! is_string( $plaintext ) ) {
			return false;
		}

		if ( '' !== $this->secret ) {
			$envelope = $this->encrypt_envelope( $plaintext );
			$encoded  = json_encode( $envelope );
			if ( ! is_string( $encoded ) ) {
				return false;
			}
		} else {
			$encoded = $plaintext;
		}

		$payload = self::GUARD_PREFIX . $encoded;
		$temp    = $this->file . '.tmp.' . bin2hex( random_bytes( 6 ) );

		$fp = @fopen( $temp, 'xb' );
		if ( false === $fp ) {
			return false;
		}

		@chmod( $temp, 0600 );
		$written = @fwrite( $fp, $payload );
		@fflush( $fp );
		@fclose( $fp );

		if ( false === $written || $written !== strlen( $payload ) ) {
			@unlink( $temp );

			return false;
		}

		if ( ! @rename( $temp, $this->file ) ) {
			@unlink( $temp );

			return false;
		}

		@chmod( $this->file, 0600 );

		return true;
	}

	/**
	 * Absolute path of the state file.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Absolute path of the private per-run directory.
	 *
	 * @return string
	 */
	public function run_dir(): string {
		return $this->run_dir;
	}

	/**
	 * Opaque signed handle referencing this private run.
	 *
	 * @return string
	 */
	public function handle(): string {
		return $this->run_handle;
	}

	/**
	 * Read a value.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $fallback;
	}

	/**
	 * Write a value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Remove a single key from state.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public function remove( string $key ): void {
		unset( $this->data[ $key ] );
	}

	/**
	 * Whether a key exists.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * Purge sensitive credential keys from state immediately.
	 *
	 * When `$keep_auth_flag` is true and the session was authenticated with an
	 * installer password, only the minimal non-sensitive authentication flag is
	 * retained so the final cleanup step can still be authorized. Otherwise the
	 * entire private run state and directory are destroyed immediately.
	 *
	 * @param bool $keep_auth_flag Whether to preserve minimal non-sensitive auth status.
	 * @return void
	 */
	public function purge_sensitive( bool $keep_auth_flag = false ): void {
		unset( $this->data['db'], $this->data['admin_account'] );

		if ( $keep_auth_flag && ! empty( $this->data['authenticated'] ) ) {
			$this->data = array(
				'authenticated' => true,
				'finished'      => ! empty( $this->data['finished'] ),
			);
			$this->cleanup_crash_temps();
			$this->save();

			return;
		}

		$this->destroy();
	}

	/**
	 * Delete the state file, any crash-leftover `.tmp.*` files strictly inside this
	 * run's private directory, and remove the private directory itself.
	 *
	 * Never touches files belonging to other runs, shared temp files, or user files.
	 *
	 * @return void
	 */
	public function destroy(): void {
		$this->data = array();

		if ( '' !== $this->run_dir && is_dir( $this->run_dir ) && ! is_link( $this->run_dir ) ) {
			$this->cleanup_crash_temps();

			if ( is_file( $this->file ) || is_link( $this->file ) ) {
				@chmod( $this->file, 0600 );
				@unlink( $this->file );
			}

			@rmdir( $this->run_dir );

			return;
		}

		if ( is_file( $this->file ) ) {
			@chmod( $this->file, 0600 );
			@unlink( $this->file );
		}

		if ( is_file( $this->file . '.tmp' ) ) {
			@unlink( $this->file . '.tmp' );
		}
	}

	/**
	 * Remove any crash-leftover temporary files (`state.php.tmp*`) strictly scoped
	 * to this run's private directory.
	 *
	 * @return void
	 */
	public function cleanup_crash_temps(): void {
		if ( '' === $this->run_dir || ! is_dir( $this->run_dir ) || is_link( $this->run_dir ) ) {
			return;
		}

		$entries = @scandir( $this->run_dir );
		if ( ! is_array( $entries ) ) {
			return;
		}

		$prefix = self::STATE_FILENAME . '.tmp';
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			if ( $entry === $prefix || 1 === preg_match( '/^' . preg_quote( $prefix, '/' ) . '\.[a-f0-9]+$/i', $entry ) ) {
				$path = rtrim( $this->run_dir, '/' ) . '/' . $entry;
				if ( is_file( $path ) || is_link( $path ) ) {
					@chmod( $path, 0600 );
					@unlink( $path );
				}
			}
		}
	}

	/**
	 * All data.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->data;
	}

	/**
	 * Default external candidate directories outside webroot.
	 *
	 * @return array<int, string>
	 */
	private static function default_candidate_dirs(): array {
		$candidates = array();

		$sys_tmp = sys_get_temp_dir();
		if ( is_string( $sys_tmp ) && '' !== trim( $sys_tmp ) ) {
			$candidates[] = $sys_tmp;
		}

		$upload_tmp = (string) ini_get( 'upload_tmp_dir' );
		if ( '' !== trim( $upload_tmp ) ) {
			$candidates[] = $upload_tmp;
		}

		if ( function_exists( 'session_save_path' ) ) {
			$sess_dir = (string) session_save_path();
			if ( '' !== trim( $sess_dir ) ) {
				if ( false !== strpos( $sess_dir, ';' ) ) {
					$parts    = explode( ';', $sess_dir );
					$sess_dir = (string) end( $parts );
				}
				if ( '' !== trim( $sess_dir ) ) {
					$candidates[] = $sess_dir;
				}
			}
		}

		return $candidates;
	}

	/**
	 * Verify that a private run directory is real, not a symlink, outside webroot,
	 * owned by the current OS user, and has no group/world access on POSIX systems.
	 *
	 * @param string      $dir      Candidate private run directory.
	 * @param string      $web_dir  Installer working directory.
	 * @param string|null $doc_root Web server DOCUMENT_ROOT.
	 * @return void
	 *
	 * @throws \RuntimeException If any safety invariant fails.
	 */
	private static function assert_private_dir_safe( string $dir, string $web_dir, ?string $doc_root ): void {
		if ( is_link( $dir ) || ! is_dir( $dir ) ) {
			throw new \RuntimeException( 'Secure private state directory is missing or unsafe.' );
		}

		if ( ! self::is_outside_webroot( $dir, $web_dir, $doc_root ) ) {
			throw new \RuntimeException( 'State directory resolved inside web document root. Installation stopped.' );
		}

		if ( '\\' !== DIRECTORY_SEPARATOR ) {
			clearstatcache( true, $dir );
			if ( function_exists( 'posix_geteuid' ) && function_exists( 'fileowner' ) ) {
				$owner = @fileowner( $dir );
				if ( false === $owner || $owner !== posix_geteuid() ) {
					throw new \RuntimeException( 'Private state directory ownership verification failed.' );
				}
			}

			$perms = @fileperms( $dir );
			if ( false !== $perms && 0 !== ( $perms & 0077 ) ) {
				throw new \RuntimeException( 'Private state directory permissions are too permissive.' );
			}
		}
	}

	/**
	 * Verify that the state file inside a private run directory is a regular file
	 * (not a symlink), lives inside the private directory, and has safe ownership/perms.
	 *
	 * @param string $file    Absolute state file path.
	 * @param string $run_dir Expected parent private run directory.
	 * @return void
	 *
	 * @throws \RuntimeException If any safety invariant fails.
	 */
	private static function assert_private_file_safe( string $file, string $run_dir ): void {
		if ( is_link( $file ) || ! is_file( $file ) ) {
			throw new \RuntimeException( 'Installer state file is missing or unsafe.' );
		}

		$norm_dir  = rtrim( self::normalize_path( $run_dir ), '/' );
		$norm_file = self::normalize_path( $file );
		if ( 0 !== strpos( $norm_file, $norm_dir . '/' ) ) {
			throw new \RuntimeException( 'Installer state file path escaped its private directory.' );
		}

		if ( '\\' !== DIRECTORY_SEPARATOR ) {
			clearstatcache( true, $file );
			if ( function_exists( 'posix_geteuid' ) && function_exists( 'fileowner' ) ) {
				$owner = @fileowner( $file );
				if ( false === $owner || $owner !== posix_geteuid() ) {
					throw new \RuntimeException( 'Installer state file ownership verification failed.' );
				}
			}

			$perms = @fileperms( $file );
			if ( false !== $perms && 0 !== ( $perms & 0077 ) ) {
				throw new \RuntimeException( 'Installer state file permissions are too permissive.' );
			}
		}
	}

	/**
	 * Compute the server-signed HMAC binding a run nonce to this installer instance.
	 *
	 * @param string $nonce        32-char hex nonce.
	 * @param string $web_dir      Installer working directory.
	 * @param string $archive_name Archive basename.
	 * @param string $state_secret Server-only secret.
	 * @return string 64-char hex HMAC-SHA256.
	 */
	private static function sign_run( string $nonce, string $web_dir, string $archive_name, string $state_secret ): string {
		return hash_hmac(
			'sha256',
			'backupino-run-handle|' . $nonce . '|' . self::normalize_path( $web_dir ) . '|' . basename( $archive_name ),
			$state_secret
		);
	}

	/**
	 * Derive the per-run encryption secret from the server-only secret and run nonce.
	 *
	 * @param string $nonce        32-char hex nonce.
	 * @param string $web_dir      Installer working directory.
	 * @param string $state_secret Server-only secret.
	 * @return string
	 */
	private static function derive_run_secret( string $nonce, string $web_dir, string $state_secret ): string {
		return hash_hmac(
			'sha256',
			'backupino-run-secret|' . $nonce . '|' . self::normalize_path( $web_dir ),
			$state_secret
		);
	}

	/**
	 * Encrypt a JSON string into an authenticated envelope.
	 *
	 * @param string $plaintext JSON payload.
	 * @return array<string, mixed>
	 */
	private function encrypt_envelope( string $plaintext ): array {
		$enc_key = hash_hmac( 'sha256', 'backupino-state-enc', $this->secret, true );
		$mac_key = hash_hmac( 'sha256', 'backupino-state-mac', $this->secret, true );

		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_get_cipher_methods' ) ) {
			$methods = openssl_get_cipher_methods();

			if ( in_array( 'aes-256-gcm', $methods, true ) ) {
				$iv  = random_bytes( 12 );
				$tag = '';
				$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $enc_key, OPENSSL_RAW_DATA, $iv, $tag );
				if ( is_string( $ct ) && '' !== $tag ) {
					return array(
						'v'   => 2,
						'alg' => 'aes-256-gcm',
						'iv'  => base64_encode( $iv ),
						'tag' => base64_encode( $tag ),
						'ct'  => base64_encode( $ct ),
					);
				}
			}

			if ( in_array( 'aes-256-cbc', $methods, true ) ) {
				$iv = random_bytes( 16 );
				$ct = openssl_encrypt( $plaintext, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
				if ( is_string( $ct ) ) {
					$mac = hash_hmac( 'sha256', $iv . $ct, $mac_key, true );

					return array(
						'v'   => 2,
						'alg' => 'aes-256-cbc-hmac',
						'iv'  => base64_encode( $iv ),
						'mac' => base64_encode( $mac ),
						'ct'  => base64_encode( $ct ),
					);
				}
			}
		}

		// OpenSSL fallback: authenticated stream cipher (still inside 0700 private dir outside webroot + PHP 403 guard).
		$salt = random_bytes( 16 );
		$ct   = self::xor_stream( $plaintext, $enc_key, $salt );
		$mac  = hash_hmac( 'sha256', $salt . $ct, $mac_key, true );

		return array(
			'v'    => 2,
			'alg'  => 'hmac-stream',
			'salt' => base64_encode( $salt ),
			'mac'  => base64_encode( $mac ),
			'ct'   => base64_encode( $ct ),
		);
	}

	/**
	 * Decrypt and authenticate a persisted state envelope.
	 *
	 * @param array<string, mixed> $envelope Decoded envelope.
	 * @return array<string, mixed>|null
	 */
	private function decrypt_envelope( array $envelope ): ?array {
		if ( 2 !== (int) ( $envelope['v'] ?? 0 ) || empty( $envelope['alg'] ) || empty( $envelope['ct'] ) ) {
			return null;
		}

		$enc_key   = hash_hmac( 'sha256', 'backupino-state-enc', $this->secret, true );
		$mac_key   = hash_hmac( 'sha256', 'backupino-state-mac', $this->secret, true );
		$alg       = (string) $envelope['alg'];
		$ct        = base64_decode( (string) $envelope['ct'], true );
		$plaintext = false;

		if ( ! is_string( $ct ) ) {
			return null;
		}

		if ( 'aes-256-gcm' === $alg && function_exists( 'openssl_decrypt' ) ) {
			$iv  = base64_decode( (string) ( $envelope['iv'] ?? '' ), true );
			$tag = base64_decode( (string) ( $envelope['tag'] ?? '' ), true );
			if ( is_string( $iv ) && is_string( $tag ) ) {
				$plaintext = openssl_decrypt( $ct, 'aes-256-gcm', $enc_key, OPENSSL_RAW_DATA, $iv, $tag );
			}
		} elseif ( 'aes-256-cbc-hmac' === $alg && function_exists( 'openssl_decrypt' ) ) {
			$iv  = base64_decode( (string) ( $envelope['iv'] ?? '' ), true );
			$mac = base64_decode( (string) ( $envelope['mac'] ?? '' ), true );
			if ( is_string( $iv ) && is_string( $mac ) ) {
				$expected = hash_hmac( 'sha256', $iv . $ct, $mac_key, true );
				if ( hash_equals( $expected, $mac ) ) {
					$plaintext = openssl_decrypt( $ct, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
				}
			}
		} elseif ( 'hmac-stream' === $alg ) {
			$salt = base64_decode( (string) ( $envelope['salt'] ?? '' ), true );
			$mac  = base64_decode( (string) ( $envelope['mac'] ?? '' ), true );
			if ( is_string( $salt ) && is_string( $mac ) ) {
				$expected = hash_hmac( 'sha256', $salt . $ct, $mac_key, true );
				if ( hash_equals( $expected, $mac ) ) {
					$plaintext = self::xor_stream( $ct, $enc_key, $salt );
				}
			}
		}

		if ( ! is_string( $plaintext ) ) {
			return null;
		}

		$decoded = json_decode( $plaintext, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Deterministic HMAC-SHA256 counter-mode XOR stream for environments without OpenSSL.
	 *
	 * @param string $input Data to encrypt or decrypt.
	 * @param string $key   32-byte key.
	 * @param string $salt  16-byte nonce/salt.
	 * @return string
	 */
	private static function xor_stream( string $input, string $key, string $salt ): string {
		$len     = strlen( $input );
		$out     = '';
		$counter = 0;

		for ( $offset = 0; $offset < $len; $offset += 32 ) {
			$block   = hash_hmac( 'sha256', $salt . pack( 'N', $counter ), $key, true );
			$chunk   = substr( $input, $offset, 32 );
			$out    .= $chunk ^ substr( $block, 0, strlen( $chunk ) );
			++$counter;
		}

		return $out;
	}
}
