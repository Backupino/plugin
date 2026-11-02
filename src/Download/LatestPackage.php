<?php
/**
 * Newest package lookup.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Download;

/**
 * Finds the most recent archive in a package directory.
 */
final class LatestPackage {

	/**
	 * Newest `.zip` in the directory, or `null` when there is none.
	 *
	 * @param string $directory Absolute package directory.
	 * @return string|null
	 */
	public function find( string $directory ): ?string {
		if ( ! is_dir( $directory ) ) {
			return null;
		}

		$files = glob( $directory . '/*.zip' );

		if ( ! is_array( $files ) || array() === $files ) {
			return null;
		}

		$files = array_values(
			array_filter(
				$files,
				static function ( $file ) {
					return is_file( $file );
				}
			)
		);

		if ( array() === $files ) {
			return null;
		}

		usort(
			$files,
			static function ( $a, $b ) {
				$time = (int) filemtime( $b ) <=> (int) filemtime( $a );

				return 0 !== $time ? $time : strcmp( basename( $b ), basename( $a ) );
			}
		);

		return (string) $files[0];
	}
}
