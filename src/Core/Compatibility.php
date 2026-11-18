<?php
/**
 * Environment checks.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

use Backupino\Support\Bytes;
use Backupino\Support\Fs;

/**
 * Collects runtime facts and blocking problems for the current host.
 */
final class Compatibility {

	/**
	 * Minimum required extensions.
	 *
	 * @var string[]
	 */
	private const REQUIRED_EXTENSIONS = array( 'json', 'zlib' );

	/**
	 * Whether the plugin can run here.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		return array() === self::blocking_issues();
	}

	/**
	 * Problems that prevent the plugin from working at all.
	 *
	 * @return string[]
	 */
	public static function blocking_issues(): array {
		$issues = array();

		if ( version_compare( PHP_VERSION, BACKUPINO_MIN_PHP, '<' ) ) {
			$issues[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Backupino requires PHP %1$s or newer. This server runs PHP %2$s.', 'backupino' ),
				BACKUPINO_MIN_PHP,
				PHP_VERSION
			);
		}

		foreach ( self::REQUIRED_EXTENSIONS as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$issues[] = sprintf(
					/* translators: %s: PHP extension name. */
					__( 'The PHP extension "%s" is required but not loaded.', 'backupino' ),
					$extension
				);
			}
		}

		if ( ! self::has_archiver() ) {
			$issues[] = __( 'Neither the Zip extension nor PclZip is available, so archives cannot be created.', 'backupino' );
		}

		return $issues;
	}

	/**
	 * Non blocking warnings worth surfacing in the admin UI.
	 *
	 * @return string[]
	 */
	public static function warnings(): array {
		$warnings = array();
		$memory   = Bytes::from_ini( (string) ini_get( 'memory_limit' ) );

		if ( $memory > 0 && $memory < 128 * 1024 * 1024 ) {
			$warnings[] = sprintf(
				/* translators: %s: current memory limit. */
				__( 'PHP memory limit is %s. Backups will run in small chunks, but 128M or more is recommended.', 'backupino' ),
				ini_get( 'memory_limit' )
			);
		}

		$runtime = (int) ini_get( 'max_execution_time' );

		if ( 0 !== $runtime && $runtime < 30 ) {
			$warnings[] = sprintf(
				/* translators: %d: seconds. */
				__( 'max_execution_time is %d seconds. Long steps will be split automatically.', 'backupino' ),
				$runtime
			);
		}

		if ( ! extension_loaded( 'openssl' ) ) {
			$warnings[] = __( 'OpenSSL extension is missing: secure HTTPS communications will be unavailable.', 'backupino' );
		}

		if ( ! extension_loaded( 'mbstring' ) ) {
			$warnings[] = __( 'mbstring is missing: multibyte site titles may be truncated on URL replacement.', 'backupino' );
		}

		if ( self::is_nginx() ) {
			$warnings[] = __( 'Nginx web server detected: Nginx ignores .htaccess and web.config files, and index.html only hides directory listing. Direct HTTP access to backup files is NOT guaranteed to be blocked until your server administrator adds the server-level Nginx deny rule in Backupino -> Settings -> Storage Destination and reloads Nginx. Backupino cannot automatically alter or reload Nginx configuration.', 'backupino' );
		}

		return $warnings;
	}

	/**
	 * Detect whether the current request is served behind Nginx (best-effort).
	 *
	 * Note: Detection is purely informational and best-effort. Reverse proxies
	 * or caching servers sitting in front of Apache or PHP-FPM might not advertise
	 * Nginx in SERVER_SOFTWARE. User-controlled request headers (such as HTTP_X_*)
	 * are intentionally NOT trusted for security reasons.
	 *
	 * @param string|null $server_software Optional SERVER_SOFTWARE override for testing.
	 * @return bool
	 */
	public static function is_nginx( ?string $server_software = null ): bool {
		if ( null === $server_software ) {
			if ( isset( $GLOBALS['is_nginx'] ) && true === $GLOBALS['is_nginx'] ) {
				return true;
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only server banner check.
			$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) : '';
		}

		return false !== stripos( $server_software, 'nginx' );
	}

	/**
	 * Extract canonical origin components (scheme, host, effective port) from an absolute URL.
	 *
	 * Returns null if the URL is relative, protocol-relative, invalid, or has an unsupported scheme.
	 *
	 * @param string $url URL to parse.
	 * @return array{scheme: string, host: string, port: int}|null
	 */
	public static function extract_origin( string $url ): ?array {
		$trimmed = trim( $url );
		if ( '' === $trimmed || 0 === strpos( $trimmed, '//' ) ) {
			return null;
		}

		$parts = wp_parse_url( $trimmed );
		if ( false === $parts || ! is_array( $parts ) ) {
			return null;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';

		if ( '' === $scheme || '' === $host || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}

		if ( isset( $parts['port'] ) ) {
			if ( ! is_int( $parts['port'] ) && ! ctype_digit( (string) $parts['port'] ) ) {
				return null;
			}
			$port = (int) $parts['port'];
			if ( $port < 1 || $port > 65535 ) {
				return null;
			}
		} else {
			$port = 'https' === $scheme ? 443 : 80;
		}

		return array(
			'scheme' => $scheme,
			'host'   => $host,
			'port'   => $port,
		);
	}

	/**
	 * Detailed inspection of Nginx storage protection and location block generation.
	 *
	 * Only generates a concrete `location ^~` directive when the storage web path can be
	 * reliably and safely determined relative to ABSPATH or the site/home URL (same-origin).
	 * If uploads point to an external CDN, a different host, or an indeterminate path,
	 * status is set to 'manual' to prevent displaying inaccurate or misleading Nginx rules.
	 *
	 * Output paths are strictly sanitized to prevent directive injection, newlines, and traversal.
	 *
	 * @param Paths $paths Storage layout.
	 * @return array{status: string, rule: string, web_path: string, fs_path: string, reason: string}
	 */
	public static function nginx_storage_info( Paths $paths ): array {
		$base    = rtrim( str_replace( '\\', '/', $paths->base() ), '/' );
		$root    = rtrim( str_replace( '\\', '/', $paths->root() ), '/' );
		$fs_path = $paths->packages();

		$uploads_baseurl = '';
		$uploads_basedir = '';
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads         = wp_upload_dir( null, false );
			$uploads_baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
			$uploads_basedir = isset( $uploads['basedir'] ) ? rtrim( str_replace( '\\', '/', (string) $uploads['basedir'] ), '/' ) : '';
		}

		$candidate_path = '';

		// 1. Primary resolution: If base is inside wp_upload_dir basedir, map through baseurl.
		$is_inside_uploads = '' !== $uploads_basedir && ( $base === $uploads_basedir || 0 === strpos( $base, $uploads_basedir . '/' ) );
		if ( $is_inside_uploads ) {
			$trimmed_baseurl = trim( $uploads_baseurl );
			if ( '' === $trimmed_baseurl ) {
				return array(
					'status'   => 'manual',
					'rule'     => '',
					'web_path' => '',
					'fs_path'  => $fs_path,
					'reason'   => 'invalid_baseurl',
				);
			}

			// Reject protocol-relative URLs (e.g. //cdn.example.org/uploads).
			if ( 0 === strpos( $trimmed_baseurl, '//' ) ) {
				return array(
					'status'   => 'manual',
					'rule'     => '',
					'web_path' => '',
					'fs_path'  => $fs_path,
					'reason'   => 'protocol_relative',
				);
			}

			$parsed_baseurl = wp_parse_url( $trimmed_baseurl );
			if ( false === $parsed_baseurl || ! is_array( $parsed_baseurl ) ) {
				return array(
					'status'   => 'manual',
					'rule'     => '',
					'web_path' => '',
					'fs_path'  => $fs_path,
					'reason'   => 'malformed_baseurl',
				);
			}

			// Reject unexpected query, fragment, or user credentials in baseurl.
			if ( isset( $parsed_baseurl['query'] ) || isset( $parsed_baseurl['fragment'] ) || isset( $parsed_baseurl['user'] ) || isset( $parsed_baseurl['pass'] ) ) {
				return array(
					'status'   => 'manual',
					'rule'     => '',
					'web_path' => '',
					'fs_path'  => $fs_path,
					'reason'   => 'unexpected_url_components',
				);
			}

			$upload_path = isset( $parsed_baseurl['path'] ) ? (string) $parsed_baseurl['path'] : '';
			$is_absolute = isset( $parsed_baseurl['scheme'] ) || isset( $parsed_baseurl['host'] );

			if ( $is_absolute ) {
				$upload_origin = self::extract_origin( $trimmed_baseurl );
				if ( null === $upload_origin ) {
					return array(
						'status'   => 'manual',
						'rule'     => '',
						'web_path' => '',
						'fs_path'  => $fs_path,
						'reason'   => 'invalid_origin',
					);
				}

				// Resolve expected site origins from home_url() and site_url().
				$home_url    = function_exists( 'home_url' ) ? (string) home_url() : '';
				$site_url    = function_exists( 'site_url' ) ? (string) site_url() : '';
				$home_origin = '' !== $home_url ? self::extract_origin( $home_url ) : null;
				$site_origin = '' !== $site_url ? self::extract_origin( $site_url ) : null;

				// Fail closed if neither home_url nor site_url provides a valid origin to verify against.
				if ( null === $home_origin && null === $site_origin ) {
					return array(
						'status'   => 'manual',
						'rule'     => '',
						'web_path' => '',
						'fs_path'  => $fs_path,
						'reason'   => 'missing_expected_origin',
					);
				}

				// Check exact same-origin match (scheme, hostname, effective port).
				$matches_home = null !== $home_origin &&
					$upload_origin['scheme'] === $home_origin['scheme'] &&
					$upload_origin['host'] === $home_origin['host'] &&
					$upload_origin['port'] === $home_origin['port'];

				$matches_site = null !== $site_origin &&
					$upload_origin['scheme'] === $site_origin['scheme'] &&
					$upload_origin['host'] === $site_origin['host'] &&
					$upload_origin['port'] === $site_origin['port'];

				if ( ! $matches_home && ! $matches_site ) {
					return array(
						'status'   => 'manual',
						'rule'     => '',
						'web_path' => '',
						'fs_path'  => $fs_path,
						'reason'   => 'origin_mismatch',
					);
				}
			} else {
				// Relative URL: must start strictly with '/' and not contain scheme markers or traversal.
				if ( '' === $upload_path || 0 !== strpos( $upload_path, '/' ) ) {
					return array(
						'status'   => 'manual',
						'rule'     => '',
						'web_path' => '',
						'fs_path'  => $fs_path,
						'reason'   => 'invalid_relative_path',
					);
				}
			}

			if ( '' === trim( $upload_path ) ) {
				return array(
					'status'   => 'manual',
					'rule'     => '',
					'web_path' => '',
					'fs_path'  => $fs_path,
					'reason'   => 'invalid_path',
				);
			}

			$rel_to_upload  = substr( $base, strlen( $uploads_basedir ) );
			$candidate_path = rtrim( $upload_path, '/' ) . '/' . ltrim( $rel_to_upload, '/' );
		} elseif ( '' !== $root && ( $base === $root || 0 === strpos( $base, $root . '/' ) ) ) {
			// 2. Fallback resolution: If base is directly inside ABSPATH / root.
			$rel_to_root = substr( $base, strlen( $root ) );
			$site_ref    = function_exists( 'site_url' ) && '' !== (string) site_url()
				? (string) site_url()
				: ( function_exists( 'home_url' ) ? (string) home_url() : '' );
			$site_path   = (string) wp_parse_url( $site_ref, PHP_URL_PATH );
			$site_path   = '/' . trim( $site_path, '/' );

			if ( '/' !== $site_path && '' !== $site_path && 0 !== strpos( $rel_to_root, $site_path . '/' ) ) {
				$candidate_path = rtrim( $site_path, '/' ) . '/' . ltrim( $rel_to_root, '/' );
			} else {
				$candidate_path = '/' . ltrim( $rel_to_root, '/' );
			}
		}

		// If candidate path cannot be determined or resolved relative to webroot, require manual configuration.
		if ( '' === $candidate_path ) {
			return array(
				'status'   => 'manual',
				'rule'     => '',
				'web_path' => '',
				'fs_path'  => $fs_path,
				'reason'   => 'outside_root',
			);
		}

		// 3. Strict security validation: reject control characters, newlines, NUL bytes, directive injection, and traversal.
		if ( false !== strpos( $candidate_path, "\n" ) ||
		     false !== strpos( $candidate_path, "\r" ) ||
		     false !== strpos( $candidate_path, "\0" ) ||
		     false !== strpos( $candidate_path, ';' ) ||
		     false !== strpos( $candidate_path, '{' ) ||
		     false !== strpos( $candidate_path, '}' ) ) {
			return array(
				'status'   => 'manual',
				'rule'     => '',
				'web_path' => '',
				'fs_path'  => $fs_path,
				'reason'   => 'unsafe_chars',
			);
		}

		// Reject any path segment containing '.' or '..' traversal, or empty segments.
		$segments = explode( '/', trim( $candidate_path, '/' ) );
		foreach ( $segments as $seg ) {
			if ( '' === $seg || '.' === $seg || '..' === $seg ) {
				return array(
					'status'   => 'manual',
					'rule'     => '',
					'web_path' => '',
					'fs_path'  => $fs_path,
					'reason'   => 'traversal',
				);
			}
		}

		// Strictly validate characters: Never mutate or strip characters silently.
		// If the candidate path contains any characters outside [A-Za-z0-9/_.-] (such as %20, spaces, etc.),
		// refuse to generate a misleading rule and fail closed to manual status.
		if ( 1 !== preg_match( '#^[A-Za-z0-9/_.\-]+$#', $candidate_path ) ) {
			return array(
				'status'   => 'manual',
				'rule'     => '',
				'web_path' => '',
				'fs_path'  => $fs_path,
				'reason'   => 'disallowed_chars',
			);
		}

		$trimmed = trim( $candidate_path, '/' );

		// Prevent matching the entire document root or invalid short paths.
		if ( '' === $trimmed || strlen( $trimmed ) < 2 ) {
			return array(
				'status'   => 'manual',
				'rule'     => '',
				'web_path' => '',
				'fs_path'  => $fs_path,
				'reason'   => 'root_path',
			);
		}

		$web_path = '/' . $trimmed . '/';
		$rule     = "location ^~ {$web_path} {\n    deny all;\n    return 403;\n}";

		return array(
			'status'   => 'exact',
			'rule'     => $rule,
			'web_path' => $web_path,
			'fs_path'  => $fs_path,
			'reason'   => 'ok',
		);
	}

	/**
	 * Build an Nginx server-block location rule denying direct HTTP access to the
	 * Backupino storage directory. Uses `^~` so static-file regex rules cannot bypass it.
	 *
	 * Returns empty string if the web path cannot be reliably determined (e.g. external CDN or custom mapping).
	 *
	 * @param Paths $paths Storage layout.
	 * @return string
	 */
	public static function nginx_storage_rule( Paths $paths ): string {
		$info = self::nginx_storage_info( $paths );
		return $info['rule'];
	}

	/**
	 * Snapshot of the environment, used by the admin screen and the CLI.
	 *
	 * @param Paths $paths Storage layout.
	 * @return array<string, mixed>
	 */
	public static function snapshot( Paths $paths ): array {
		$nginx_info = self::nginx_storage_info( $paths );

		return array(
			'php_version'        => PHP_VERSION,
			'wp_version'         => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'memory_limit'       => (string) ini_get( 'memory_limit' ),
			'max_execution'      => (int) ini_get( 'max_execution_time' ),
			'post_max_size'      => (string) ini_get( 'post_max_size' ),
			'disk_free'          => Fs::free_space( $paths->base() ),
			'zip'                => class_exists( 'ZipArchive' ),
			'openssl'            => extension_loaded( 'openssl' ),
			'curl'               => extension_loaded( 'curl' ),
			'suhosin'            => extension_loaded( 'suhosin' ),
			'is_nginx'           => self::is_nginx(),
			'nginx_rule'         => $nginx_info['rule'],
			'nginx_storage_info' => $nginx_info,
			'storage_writable'   => Fs::is_writable_dir( $paths->base() ),
			'storage_path'       => $paths->base(),
			'blocking'           => self::blocking_issues(),
			'warnings'           => self::warnings(),
		);
	}

	/**
	 * Whether an archiver backend exists.
	 *
	 * @return bool
	 */
	private static function has_archiver(): bool {
		return class_exists( 'ZipArchive' ) || class_exists( 'PclZip' );
	}
}
