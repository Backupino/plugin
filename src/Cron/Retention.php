<?php
/**
 * Package retention.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Cron;

use Backupino\Plugin;
use Backupino\Support\Fs;

/**
 * Deletes the oldest local packages so that a site never fills its quota.
 */
final class Retention {

	/**
	 * Remove old packages, keeping the newest `$keep`.
	 *
	 * @param Plugin $plugin Plugin container.
	 * @return int Number of removed files.
	 */
	public static function enforce( Plugin $plugin ): int {
		$keep = (int) $plugin->settings()->get( 'schedule.keep', 3 );

		if ( $keep <= 0 ) {
			return 0;
		}

		return self::prune( $plugin->paths()->packages(), $keep );
	}

	/**
	 * Prune a directory of packages.
	 *
	 * @param string $directory Directory that holds packages.
	 * @param int    $keep      Number of packages to keep.
	 * @return int Removed files.
	 */
	public static function prune( string $directory, int $keep ): int {
		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$files = glob( $directory . '/*.zip' );

		if ( ! is_array( $files ) || count( $files ) <= $keep ) {
			return 0;
		}

		usort(
			$files,
			static function ( $a, $b ) {
				return filemtime( $b ) <=> filemtime( $a );
			}
		);

		$removed = 0;

		foreach ( array_slice( $files, $keep ) as $file ) {
			$stem = preg_replace( '/\.zip$/', '', $file );

			if ( Fs::delete( $file ) ) {
				++$removed;
			}

			Fs::delete( $stem . '-installer.php' );
		}

		return $removed;
	}
}
