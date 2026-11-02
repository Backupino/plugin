<?php
/**
 * Filesystem helpers.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Robust defensive directory management.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Atomic file writing and protection.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rename -- Atomic file rename.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_unlink -- Robust file removal.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Robust directory removal.

/**
 * Thin, defensive filesystem utility layer.
 *
 * All helpers return booleans or values instead of throwing so that a single
 * unreadable file can never abort a whole backup.
 */
final class Fs {

	/**
	 * Recursively create a directory and protect it from web listing.
	 *
	 * @param string $dir Absolute directory path.
	 * @return bool
	 */
	public static function ensure_dir( string $dir ): bool {
		if ( is_dir( $dir ) ) {
			return true;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$created = @mkdir( $dir, 0755, true );

		if ( is_dir( $dir ) ) {
			self::protect( $dir );
		}

		return (bool) $created || is_dir( $dir );
	}

	/**
	 * Drop `.htaccess`, `web.config`, and `index.php` guards into a directory.
	 *
	 * @param string $dir Absolute directory path.
	 * @return void
	 */
	public static function protect( string $dir ): void {
		$htaccess   = $dir . '/.htaccess';
		$web_config = $dir . '/web.config';
		$index      = $dir . '/index.php';

		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents(
				$htaccess,
				"# Backupino: never list or download raw backup files.\n"
				. "Options -Indexes\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n"
				. "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phps|pl|py|cgi)$\">\n"
				. "\t<IfModule mod_authz_core.c>\n\t\tRequire all denied\n\t</IfModule>\n"
				. "\t<IfModule !mod_authz_core.c>\n\t\tOrder allow,deny\n\t\tDeny from all\n\t</IfModule>\n"
				. "</FilesMatch>\n",
				LOCK_EX
			);
		}

		if ( ! file_exists( $web_config ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents(
				$web_config,
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n"
				. "  <system.webServer>\n"
				. "    <authorization>\n"
				. "      <deny users=\"*\" />\n"
				. "    </authorization>\n"
				. "  </system.webServer>\n"
				. "</configuration>\n",
				LOCK_EX
			);
		}

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
		}
	}

	/**
	 * Atomically replace the contents of a file.
	 *
	 * @param string $path    Target path.
	 * @param string $content Payload.
	 * @return bool
	 */
	public static function atomic_put( string $path, string $content ): bool {
		$temp = $path . '.' . Str::random( 6 ) . '.tmp';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === @file_put_contents( $temp, $content, LOCK_EX ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! @rename( $temp, $path ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $temp );
			return false;
		}

		return true;
	}

	/**
	 * Delete a file if it exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function delete( string $path ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return (bool) @unlink( $path );
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @param string $dir Absolute path.
	 * @return bool
	 */
	public static function delete_dir( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return self::delete( $dir );
		}

		$items = scandir( $dir );

		if ( false === $items ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				self::delete_dir( $path );
			} else {
				self::delete( $path );
			}
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return (bool) @rmdir( $dir );
	}

	/**
	 * Size of a file in bytes, `0` when it cannot be read.
	 *
	 * @param string $path Absolute path.
	 * @return int
	 */
	public static function size( string $path ): int {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$size = is_file( $path ) ? @filesize( $path ) : 0;

		return false === $size ? 0 : (int) $size;
	}

	/**
	 * Free disk space for the volume that contains a path.
	 *
	 * @param string $path Absolute path.
	 * @return int Bytes, `-1` when unknown.
	 */
	public static function free_space( string $path ): int {
		$probe = $path;

		while ( $probe && ! file_exists( $probe ) ) {
			$parent = dirname( $probe );

			if ( $parent === $probe ) {
				break;
			}

			$probe = $parent;
		}

		if ( ! function_exists( 'disk_free_space' ) ) {
			return -1;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$free = @\disk_free_space( $probe );

		return false === $free ? -1 : (int) $free;
	}

	/**
	 * Whether a directory is writable.
	 *
	 * @param string $dir Absolute path.
	 * @return bool
	 */
	public static function is_writable_dir( string $dir ): bool {
		return is_dir( $dir ) && is_writable( $dir );
	}

	/**
	 * Normalize a path to forward slashes without duplicate separators.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize( string $path ): string {
		$path = str_replace( '\\', '/', $path );

		return (string) preg_replace( '#/+#', '/', $path );
	}

	/**
	 * Stream a file to the browser and stop execution.
	 *
	 * Uses a plain readfile loop so that large archives never hit memory limits.
	 *
	 * @param string $path          Absolute path.
	 * @param string $download_name Optional custom filename for the Content-Disposition header.
	 * @return void
	 */
	public static function stream_download( string $path, string $download_name = '' ): void {
		if ( ! is_readable( $path ) ) {
			wp_die(
				esc_html__( 'The requested file does not exist.', 'backupino' ),
				'',
				array( 'response' => 404 )
			);
		}

		$filename = '' !== $download_name ? $download_name : basename( $path );
		$filename = str_replace( array( '"', "\r", "\n" ), '', $filename );

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) self::size( $path ) );
		header( 'X-Accel-Buffering: no' );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile, WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $path );
		exit;
	}
}
