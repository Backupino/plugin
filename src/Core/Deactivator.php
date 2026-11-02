<?php
/**
 * Deactivation routine.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs when the plugin is deactivated.
 */
final class Deactivator {

	/**
	 * Deactivate the plugin without destroying data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		foreach ( array( 'backupino_run_jobs', 'backupino_scheduled_backup' ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );

			while ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}
}
