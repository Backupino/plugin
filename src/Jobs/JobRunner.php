<?php
/**
 * Job runner.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Atomic file lock for multi-process safety.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Atomic file lock for multi-process safety.

use Backupino\Core\Db;
use Backupino\Core\Paths;
use Backupino\Core\Settings;
use Backupino\Support\Clock;
use Backupino\Support\Fs;
use Backupino\Support\Logger;

/**
 * Drives a job through the pipeline, one time-boxed slice at a time.
 */
final class JobRunner {

	/**
	 * Jobs repository.
	 *
	 * @var JobRepository
	 */
	private $jobs;

	/**
	 * Pipeline.
	 *
	 * @var Pipeline
	 */
	private $pipeline;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Paths.
	 *
	 * @var Paths
	 */
	private $paths;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Database.
	 *
	 * @var Db
	 */
	private $db;

	/**
	 * Clock.
	 *
	 * @var Clock
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param JobRepository $jobs     Jobs repository.
	 * @param Pipeline      $pipeline Pipeline.
	 * @param Settings      $settings Settings.
	 * @param Paths         $paths    Paths.
	 * @param Logger        $logger   Logger.
	 * @param Db            $db       Database adapter.
	 * @param Clock         $clock    Clock.
	 */
	public function __construct(
		JobRepository $jobs,
		Pipeline $pipeline,
		Settings $settings,
		Paths $paths,
		Logger $logger,
		Db $db,
		Clock $clock
	) {
		$this->jobs     = $jobs;
		$this->pipeline = $pipeline;
		$this->settings = $settings;
		$this->paths    = $paths;
		$this->logger   = $logger;
		$this->db       = $db;
		$this->clock    = $clock;
	}

	/**
	 * Advance a job by one time-boxed slice.
	 *
	 * @param Job $job      Job to advance.
	 * @param int $budget   Seconds allowed for this slice.
	 * @return Job The same instance, mutated and persisted.
	 */
	public function step( Job $job, int $budget = 0 ): Job {
		if ( ! $job->is_runnable() ) {
			return $job;
		}

		$lock_dir = $this->paths->jobs();
		if ( ! is_dir( $lock_dir ) ) {
			Fs::mkdir( $lock_dir );
		}

		$lock_file = $lock_dir . '/' . $job->token . '.lock';
		$lock_fp   = @fopen( $lock_file, 'c+' );
		if ( false === $lock_fp ) {
			return $job;
		}

		if ( ! @flock( $lock_fp, LOCK_EX | LOCK_NB ) ) {
			@fclose( $lock_fp );
			return $job;
		}

		// Re-fetch latest state from DB in case another process updated it.
		$refreshed = $this->jobs->find( $job->id );
		if ( null !== $refreshed ) {
			$job = $refreshed;
		}

		if ( ! $job->is_runnable() ) {
			@flock( $lock_fp, LOCK_UN );
			@fclose( $lock_fp );
			return $job;
		}

		if ( $budget <= 0 ) {
			$budget = (int) $this->settings->get( 'runtime.time_budget', 20 );
		}

		$deadline = new Deadline( $budget );
		$context  = new JobContext(
			$job,
			$this->settings,
			$this->paths,
			$this->logger,
			$this->db,
			$this->clock,
			$deadline
		);

		$job->status = JobStatus::RUNNING;

		if ( '' === $job->stage ) {
			$job->stage = $this->pipeline->first();
		}

		$stage = $this->pipeline->get( $job->stage );

		if ( null === $stage ) {
			$this->fail( $job, __( 'Unknown pipeline stage.', 'backupino' ) );

			return $job;
		}

		try {
			$result = $stage->run( $context );

			$job->progress = max( $job->progress, $this->pipeline->progress( $job->stage, $result->fraction() ) );

			if ( '' !== $result->message() ) {
				$job->message = $result->message();
			}

			if ( $result->is_completed() ) {
				$next = $this->pipeline->next( $job->stage );

				if ( '' === $next ) {
					$job->status   = JobStatus::COMPLETED;
					$job->progress = 100;
					if ( false !== $lock_fp ) {
						@flock( $lock_fp, LOCK_UN );
						@fclose( $lock_fp );
						$lock_fp = false;
					}
					@unlink( $lock_file );
					$this->jobs->save( $job );
					try {
						Fs::delete_dir( $this->paths->job_dir( $job->token ) );
					} catch ( \Throwable $t ) {
						$this->logger->warning( 'Cleanup failed: ' . $t->getMessage() );
					}
					try {
						do_action( 'backupino_job_completed', $job );
					} catch ( \Throwable $t ) {
						$this->logger->warning( 'Notification hook failed: ' . $t->getMessage() );
					}
					$this->logger->info(
						'Job finished.',
						array(
							'job'   => $job->id,
							'token' => $job->token,
						)
					);
					return $job;
				} else {
					$job->stage = $next;
					$this->logger->debug(
						'Stage completed.',
						array(
							'job'   => $job->id,
							'stage' => $job->stage,
						)
					);
				}
			}
		} catch ( JobException $e ) {
			if ( false !== $lock_fp ) {
				@flock( $lock_fp, LOCK_UN );
				@fclose( $lock_fp );
				$lock_fp = false;
			}
			$this->fail( $job, $e->getMessage() );
		} catch ( \Throwable $e ) {
			if ( false !== $lock_fp ) {
				@flock( $lock_fp, LOCK_UN );
				@fclose( $lock_fp );
				$lock_fp = false;
			}
			$this->logger->error(
				'Unexpected failure.',
				array(
					'job'       => $job->id,
					'exception' => get_class( $e ),
					'message'   => $e->getMessage(),
					'file'      => $e->getFile(),
					'line'      => $e->getLine(),
				)
			);

			$this->fail( $job, $e->getMessage() );
		}

		$this->jobs->save( $job );

		if ( false !== $lock_fp ) {
			@flock( $lock_fp, LOCK_UN );
			@fclose( $lock_fp );
		}

		return $job;
	}

	/**
	 * Run until the job finishes or the global budget is exhausted.
	 *
	 * @param Job $job           Job.
	 * @param int $total_seconds Maximum total seconds (0 = until done).
	 * @return Job
	 */
	public function run( Job $job, int $total_seconds = 0 ): Job {
		$started = microtime( true );

		while ( $job->is_runnable() ) {
			$job = $this->step( $job );

			if ( $total_seconds > 0 && ( microtime( true ) - $started ) >= $total_seconds ) {
				break;
			}
		}

		return $job;
	}

	/**
	 * Cancel a job.
	 *
	 * @param Job $job Job.
	 * @return Job
	 */
	public function cancel( Job $job ): Job {
		if ( ! $job->is_runnable() ) {
			return $job;
		}

		$job->status  = JobStatus::CANCELLED;
		$job->message = __( 'Cancelled by the user.', 'backupino' );

		Fs::delete_dir( $this->paths->job_dir( $job->token ) );
		@unlink( $this->paths->jobs() . '/' . $job->token . '.lock' );
		$this->jobs->save( $job );

		return $job;
	}

	/**
	 * Mark a job as failed.
	 *
	 * @param Job    $job     Job.
	 * @param string $message Failure reason.
	 * @return void
	 */
	private function fail( Job $job, string $message ): void {
		$job->status  = JobStatus::FAILED;
		$job->message = $message;

		Fs::delete_dir( $this->paths->job_dir( $job->token ) );

		$this->logger->error(
			'Job failed.',
			array(
				'job'     => $job->id,
				'stage'   => $job->stage,
				'message' => $message,
			)
		);
	}
}
