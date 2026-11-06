<?php
/**
 * Cron scheduling.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Cron;

defined( 'ABSPATH' ) || exit;

use Backupino\Jobs\Job;
use Backupino\Jobs\JobStatus;
use Backupino\Plugin;
use Backupino\Support\Dates;

/**
 * Runs queued jobs in the background and creates scheduled backups.
 */
final class Scheduler {

	/**
	 * Plugin container.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin container.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( 'backupino_run_jobs', array( $this, 'run_jobs' ) );
		add_action( 'backupino_scheduled_backup', array( $this, 'create_scheduled_job' ) );
		add_action( 'init', array( $this, 'sync_schedule' ) );
	}

	/**
	 * Add the custom cron intervals.
	 *
	 * @param array<string, array<string, mixed>> $schedules Existing schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_schedules( array $schedules ): array {
		if ( ! isset( $schedules['backupino_minute'] ) ) {
			$schedules['backupino_minute'] = array(
				'interval' => 60,
				'display'  => __( 'Backupino: every minute', 'backupino' ),
			);
		}

		if ( ! isset( $schedules['backupino_monthly'] ) ) {
			$schedules['backupino_monthly'] = array(
				'interval' => 2592000,
				'display'  => __( 'Once monthly (first day of the month)', 'backupino' ),
			);
		}

		return $schedules;
	}

	/**
	 * Keep the scheduled backup event in sync with the settings.
	 *
	 * @return void
	 */
	public function sync_schedule(): void {
		$enabled = (bool) $this->plugin->settings()->get( 'schedule.enabled', false );
		$stored  = get_option( 'backupino_schedule_state', array() );
		$current = is_array( $stored ) ? (string) ( $stored['interval'] ?? '' ) : '';

		if ( ! $enabled ) {
			if ( false !== wp_next_scheduled( 'backupino_scheduled_backup' ) ) {
				wp_clear_scheduled_hook( 'backupino_scheduled_backup' );
				update_option( 'backupino_schedule_state', array( 'interval' => '' ), false );
			}

			return;
		}

		$next = wp_next_scheduled( 'backupino_scheduled_backup' );
		if ( false !== $next && 'monthly' === $current ) {
			return;
		}

		wp_clear_scheduled_hook( 'backupino_scheduled_backup' );
		$target = Dates::next_month_start( time() );
		wp_schedule_single_event( $target, 'backupino_scheduled_backup' );
		update_option( 'backupino_schedule_state', array( 'interval' => 'monthly' ), false );
	}

	/**
	 * Create a job for the scheduled backup and schedule next run if monthly.
	 *
	 * @return void
	 */
	public function create_scheduled_job(): void {
		$job = Job::create(
			array(
				'type'     => 'scheduled',
				'note'     => __( 'Scheduled backup', 'backupino' ),
				'files'    => (bool) $this->plugin->settings()->get( 'archive.files', true ),
				'database' => (bool) $this->plugin->settings()->get( 'archive.database', true ),
			),
			$this->plugin->clock()
		);

		$this->plugin->jobs()->create( $job );

		$this->plugin->logger()->info( 'Scheduled job queued.', array( 'job' => $job->id ) );

		$enabled = (bool) $this->plugin->settings()->get( 'schedule.enabled', false );
		if ( $enabled ) {
			// Schedule next month start (after current day has passed).
			$target = Dates::next_month_start( time() + 86400 );
			wp_schedule_single_event( $target, 'backupino_scheduled_backup' );
		}
	}

	/**
	 * Advance pending jobs in the background.
	 *
	 * @return void
	 */
	public function run_jobs(): void {
		$this->plugin->notifier()->flush();

		$deadline = time() + 25;

		foreach ( $this->plugin->jobs()->pending() as $job ) {
			if ( time() > $deadline || ! $job->is_runnable() ) {
				break;
			}

			// Do not step manual backup jobs that the browser is actively driving.
			if ( 'manual' === (string) $job->option( 'type', 'manual' ) ) {
				$updated = strtotime( $job->updated_at );
				if ( false !== $updated && ( time() - $updated ) < 45 ) {
					continue;
				}
			}

			$job = $this->plugin->runner()->step( $job );

			if ( JobStatus::COMPLETED === $job->status ) {
				Retention::enforce( $this->plugin );
			}
		}
	}
}
