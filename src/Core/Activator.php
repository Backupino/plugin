<?php
/**
 * Activation routine.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once when the plugin is activated.
 */
final class Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Schema::install();

		$paths = Paths::from_wordpress();
		$paths->ensure();

		if ( ! get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::sanitize( Settings::defaults() ), '', false );
		}

		add_option( 'backupino_installed_at', gmdate( 'Y-m-d H:i:s' ), '', false );

		if ( ! wp_next_scheduled( 'backupino_run_jobs' ) ) {
			// The recurrence is registered by the Scheduler, but activation runs
			// before the plugin has fully booted on some setups.
			add_filter(
				'cron_schedules',
				static function ( $schedules ) {
					if ( ! isset( $schedules['backupino_minute'] ) ) {
						$schedules['backupino_minute'] = array(
							'interval' => 60,
							'display'  => __( 'Backupino: every minute', 'backupino' ),
						);
					}

					return $schedules;
				}
			);

			wp_schedule_event( time() + 60, 'backupino_minute', 'backupino_run_jobs' );
		}
	}
}
