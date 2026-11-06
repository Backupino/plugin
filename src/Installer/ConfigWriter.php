<?php
/**
 * Writes the wp-config.php of the restored site.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Standalone installer running outside WordPress environment.

/**
 * Renders or updates `wp-config.php` for the restored site.
 */
final class ConfigWriter {

	/**
	 * Strips passwords and cryptographic salts from wp-config content
	 * so raw database credentials and auth keys are never saved in the backup archive.
	 *
	 * @param string $content Raw wp-config.php content.
	 * @return string Redacted wp-config template.
	 */
	public static function redact_secrets( string $content ): string {
		$sensitive_constants = array(
			'DB_NAME',
			'DB_USER',
			'DB_PASSWORD',
			'DB_HOST',
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		foreach ( $sensitive_constants as $key ) {
			$pattern = '/(define\s*\(\s*[\'"]' . preg_quote( $key, '/' ) . '[\'"]\s*,\s*)[\'"].*?[\'"](\s*\)\s*;)/i';
			$content = (string) preg_replace( $pattern, '${1}\'\'${2}', $content );
		}

		return $content;
	}

	/**
	 * Preserves all original custom settings, comments, constants, and structure,
	 * updating database credentials, salts, and table prefix.
	 *
	 * @param string               $content Original wp-config.php content.
	 * @param array<string, mixed> $config  New database configuration.
	 * @return string
	 */
	public static function update_wp_config( string $content, array $config ): string {
		$name     = (string) ( $config['name'] ?? '' );
		$user     = (string) ( $config['user'] ?? '' );
		$password = (string) ( $config['password'] ?? '' );
		$host     = (string) ( $config['host'] ?? 'localhost' );
		$port     = isset( $config['port'] ) ? (int) $config['port'] : 0;
		$prefix   = (string) ( $config['prefix'] ?? 'wp_' );
		$new_url  = (string) ( $config['new_url'] ?? '' );

		if ( $port > 0 && false === strpos( $host, ':' ) ) {
			$host .= ':' . $port;
		}

		$constants = array(
			'DB_NAME'     => $name,
			'DB_USER'     => $user,
			'DB_PASSWORD' => $password,
			'DB_HOST'     => $host,
		);

		foreach ( $constants as $key => $val ) {
			$pattern = '/(define\s*\(\s*[\'"]' . preg_quote( $key, '/' ) . '[\'"]\s*,\s*)[\'"].*?[\'"](\s*\)\s*;)/i';
			if ( preg_match( $pattern, $content ) ) {
				$content = (string) preg_replace_callback(
					$pattern,
					static function ( array $m ) use ( $val ): string {
						return $m[1] . '\'' . addcslashes( $val, "'\\" ) . '\'' . $m[2];
					},
					$content
				);
			} else {
				$insert       = "\ndefine( '" . $key . "', '" . addcslashes( $val, "'\\" ) . "' );\n";
				$settings_pos = strpos( $content, 'wp-settings.php' );
				if ( false !== $settings_pos ) {
					$line_start = strrpos( substr( $content, 0, $settings_pos ), "\n" );
					if ( false !== $line_start ) {
						$content = substr_replace( $content, $insert, $line_start, 0 );
					} else {
						$content .= $insert;
					}
				} else {
					$content .= $insert;
				}
			}
		}

		// Regenerate all 8 fresh random salts
		$salts = self::salts();
		foreach ( $salts as $key => $val ) {
			$pattern = '/(define\s*\(\s*[\'"]' . preg_quote( $key, '/' ) . '[\'"]\s*,\s*)[\'"].*?[\'"](\s*\)\s*;)/i';
			if ( preg_match( $pattern, $content ) ) {
				$content = (string) preg_replace_callback(
					$pattern,
					static function ( array $m ) use ( $val ): string {
						return $m[1] . '\'' . addcslashes( $val, "'\\" ) . '\'' . $m[2];
					},
					$content
				);
			} else {
				$insert       = "\ndefine( '" . $key . "', '" . addcslashes( $val, "'\\" ) . "' );\n";
				$settings_pos = strpos( $content, 'wp-settings.php' );
				if ( false !== $settings_pos ) {
					$line_start = strrpos( substr( $content, 0, $settings_pos ), "\n" );
					if ( false !== $line_start ) {
						$content = substr_replace( $content, $insert, $line_start, 0 );
					} else {
						$content .= $insert;
					}
				} else {
					$content .= $insert;
				}
			}
		}

		if ( '' !== $prefix ) {
			$pattern = '/(\$table_prefix\s*=\s*)[\'"].*?[\'"](\s*;)/i';
			if ( preg_match( $pattern, $content ) ) {
				$content = (string) preg_replace_callback(
					$pattern,
					static function ( array $m ) use ( $prefix ): string {
						return $m[1] . '\'' . addcslashes( $prefix, "'\\" ) . '\'' . $m[2];
					},
					$content
				);
			}
		}

		if ( '' !== $new_url ) {
			$url_constants = array( 'WP_HOME', 'WP_SITEURL' );
			$trimmed_url   = rtrim( $new_url, '/' );
			foreach ( $url_constants as $url_const ) {
				$pattern = '/(define\s*\(\s*[\'"]' . preg_quote( $url_const, '/' ) . '[\'"]\s*,\s*)[\'"].*?[\'"](\s*\)\s*;)/i';
				if ( preg_match( $pattern, $content ) ) {
					$content = (string) preg_replace_callback(
						$pattern,
						static function ( array $m ) use ( $trimmed_url ): string {
							return $m[1] . '\'' . addcslashes( $trimmed_url, "'\\" ) . '\'' . $m[2];
						},
						$content
					);
				}
			}
		}

		return $content;
	}

	/**
	 * Render the file contents from scratch.
	 *
	 * @param array<string, mixed> $config Configuration:
	 *                                     `name`, `user`, `password`, `host`, `port`, `prefix`, `debug`.
	 * @return string
	 */
	public static function render( array $config ): string {
		$name     = (string) ( $config['name'] ?? '' );
		$user     = (string) ( $config['user'] ?? '' );
		$password = (string) ( $config['password'] ?? '' );
		$host     = (string) ( $config['host'] ?? 'localhost' );
		$port     = isset( $config['port'] ) ? (int) $config['port'] : 0;
		$prefix   = (string) ( $config['prefix'] ?? 'wp_' );
		$debug    = ! empty( $config['debug'] );

		if ( $port > 0 && false === strpos( $host, ':' ) ) {
			$host .= ':' . $port;
		}

		$salts = self::salts();

		$lines = array(
			'<?php',
			'/**',
			' * WordPress configuration generated by Backupino.',
			' *',
			' * @package WordPress',
			' */',
			'',
			"define( 'DB_NAME', '" . self::esc( $name ) . "' );",
			"define( 'DB_USER', '" . self::esc( $user ) . "' );",
			"define( 'DB_PASSWORD', '" . self::esc( $password ) . "' );",
			"define( 'DB_HOST', '" . self::esc( $host ) . "' );",
			"define( 'DB_CHARSET', 'utf8mb4' );",
			"define( 'DB_COLLATE', '' );",
			'',
		);

		foreach ( $salts as $constant => $value ) {
			$lines[] = "define( '" . $constant . "', '" . self::esc( $value ) . "' );";
		}

		$lines[] = '';
		$lines[] = "\$table_prefix = '" . self::esc( $prefix ) . "';";
		$lines[] = '';
		$lines[] = "define( 'WP_DEBUG', " . ( $debug ? 'true' : 'false' ) . ' );';
		$lines[] = '';
		$lines[] = "if ( ! defined( 'ABSPATH' ) ) {";
		$lines[] = "\tdefine( 'ABSPATH', __DIR__ . '/' );";
		$lines[] = '}';
		$lines[] = '';
		$lines[] = "require_once ABSPATH . 'wp-settings.php';";

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Write the file.
	 *
	 * @param string               $path             Destination.
	 * @param array<string, mixed> $config           Configuration.
	 * @param string|null          $original_content Original content to update if available.
	 * @return bool
	 */
	public static function write( string $path, array $config, ?string $original_content = null ): bool {
		if ( null !== $original_content && '' !== trim( $original_content ) ) {
			$contents = self::update_wp_config( $original_content, $config );
		} else {
			$contents = self::render( $config );
		}

		if ( false === @file_put_contents( $path, $contents, LOCK_EX ) ) {
			return false;
		}

		@chmod( $path, 0640 );

		return true;
	}

	/**
	 * Fresh secret keys.
	 *
	 * @return array<string, string>
	 */
	public static function salts(): array {
		$names = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		$salts = array();

		foreach ( $names as $name ) {
			$salts[ $name ] = self::random_string( 64 );
		}

		return $salts;
	}

	/**
	 * Random printable string.
	 *
	 * @param int $length Length.
	 * @return string
	 */
	public static function random_string( int $length ): string {
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_[]{}<>~`+=,.;:/?|';
		$max      = strlen( $alphabet ) - 1;
		$out      = '';

		for ( $i = 0; $i < $length; $i++ ) {
			try {
				$index = random_int( 0, $max );
			} catch ( \Exception $e ) {
				$index = function_exists( 'wp_rand' ) ? (int) wp_rand( 0, $max ) : (int) random_int( 0, $max );
			}

			$out .= $alphabet[ $index ];
		}

		return $out;
	}

	/**
	 * Escape a value for a single quoted PHP string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function esc( string $value ): string {
		return str_replace(
			array( '\\', "'" ),
			array( '\\\\', "\\'" ),
			$value
		);
	}
}
