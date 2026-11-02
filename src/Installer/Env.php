<?php
/**
 * Installer environment checks.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

/**
 * Validates that the destination server can host the restored site.
 */
final class Env {

	/**
	 * Run every check.
	 *
	 * @param array<string, mixed> $payload Package payload.
	 * @param string               $directory Destination directory.
	 * @param string               $archive Absolute archive path.
	 * @return array<int, array{label: string, ok: bool, detail: string}>
	 */
	public static function check( array $payload, string $directory, string $archive ): array {
		$checks = array();

		$checks[] = self::result(
			__( 'PHP version', 'backupino' ),
			version_compare( PHP_VERSION, '7.4', '>=' ),
			PHP_VERSION
		);

		foreach ( array( 'json', 'zlib' ) as $extension ) {
			$checks[] = self::result(
				sprintf( /* translators: %s: extension name. */ __( 'PHP extension: %s', 'backupino' ), $extension ),
				extension_loaded( $extension ),
				extension_loaded( $extension ) ? __( 'loaded', 'backupino' ) : __( 'missing', 'backupino' )
			);
		}

		$has_zip    = class_exists( 'ZipArchive' );
		$has_driver = extension_loaded( 'mysqli' ) || extension_loaded( 'pdo_mysql' );

		$checks[] = self::result(
			__( 'Archive support', 'backupino' ),
			$has_zip,
			$has_zip ? __( 'ZipArchive available', 'backupino' ) : __( 'ZipArchive missing', 'backupino' )
		);

		$checks[] = self::result(
			__( 'MySQL driver', 'backupino' ),
			$has_driver,
			extension_loaded( 'mysqli' ) ? 'mysqli' : ( extension_loaded( 'pdo_mysql' ) ? 'pdo_mysql' : __( 'none', 'backupino' ) )
		);

		$checks[] = self::result(
			__( 'Writable directory', 'backupino' ),
			is_writable( $directory ),
			$directory
		);

		$exists = is_file( $archive );

		$checks[] = self::result(
			__( 'Archive file', 'backupino' ),
			$exists,
			$exists ? self::human_size( (int) filesize( $archive ) ) : sprintf( /* translators: %s: file name. */ __( 'not found: %s', 'backupino' ), basename( $archive ) )
		);

		if ( $exists && ! empty( $payload['archive_hash'] ) ) {
			$checks[] = self::result(
				__( 'Archive checksum', 'backupino' ),
				hash_file( 'sha256', $archive ) === $payload['archive_hash'],
				__( 'verified against the package manifest', 'backupino' )
			);
		}

		if ( $exists ) {
			$has_disk_fn = function_exists( 'disk_free_space' );
			$free        = $has_disk_fn ? @\disk_free_space( $directory ) : false;
			$need        = (int) filesize( $archive ) * 2;
			$ok          = ( false === $free ) ? true : ( (int) $free > $need );

			$checks[] = self::result(
				__( 'Free disk space', 'backupino' ),
				$ok,
				false === $free ? __( 'Not restricted / host managed', 'backupino' ) : self::human_size( (int) $free ) . ' / ' . self::human_size( $need ) . ' ' . __( 'needed', 'backupino' )
			);
		}

		return $checks;
	}

	/**
	 * Whether every check passed.
	 *
	 * @param array<int, array{label: string, ok: bool, detail: string}> $checks Checks.
	 * @return bool
	 */
	public static function passes( array $checks ): bool {
		foreach ( $checks as $check ) {
			if ( ! $check['ok'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build a check row.
	 *
	 * @param string $label  Label.
	 * @param bool   $ok     Result.
	 * @param string $detail Detail line.
	 * @return array{label: string, ok: bool, detail: string}
	 */
	private static function result( string $label, bool $ok, string $detail ): array {
		return array(
			'label'  => $label,
			'ok'     => $ok,
			'detail' => $detail,
		);
	}

	/**
	 * Format a byte count.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	public static function human_size( int $bytes ): string {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$index = 0;
		$value = (float) $bytes;

		while ( $value >= 1024 && $index < 4 ) {
			$value /= 1024;
			++$index;
		}

		return round( $value, 2 ) . ' ' . $units[ $index ];
	}
}
