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

		return $warnings;
	}

	/**
	 * Snapshot of the environment, used by the admin screen and the CLI.
	 *
	 * @param Paths $paths Storage layout.
	 * @return array<string, mixed>
	 */
	public static function snapshot( Paths $paths ): array {
		return array(
			'php_version'      => PHP_VERSION,
			'wp_version'       => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'memory_limit'     => (string) ini_get( 'memory_limit' ),
			'max_execution'    => (int) ini_get( 'max_execution_time' ),
			'post_max_size'    => (string) ini_get( 'post_max_size' ),
			'disk_free'        => Fs::free_space( $paths->base() ),
			'zip'              => class_exists( 'ZipArchive' ),
			'openssl'          => extension_loaded( 'openssl' ),
			'curl'             => extension_loaded( 'curl' ),
			'suhosin'          => extension_loaded( 'suhosin' ),
			'storage_writable' => Fs::is_writable_dir( $paths->base() ),
			'storage_path'     => $paths->base(),
			'blocking'         => self::blocking_issues(),
			'warnings'         => self::warnings(),
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
