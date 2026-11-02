<?php
/**
 * Job context.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Db;
use Backupino\Core\Paths;
use Backupino\Core\Settings;
use Backupino\Support\Clock;
use Backupino\Support\Logger;

/**
 * Immutable service bag handed to stages for a single tick.
 */
final class JobContext {

	/**
	 * Job being executed.
	 *
	 * @var Job
	 */
	private $job;

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
	 * Deadline of the current tick.
	 *
	 * @var Deadline
	 */
	private $deadline;

	/**
	 * Constructor.
	 *
	 * @param Job      $job      Job.
	 * @param Settings $settings Settings.
	 * @param Paths    $paths    Paths.
	 * @param Logger   $logger   Logger.
	 * @param Db       $db       Database adapter.
	 * @param Clock    $clock    Clock.
	 * @param Deadline $deadline Tick deadline.
	 */
	public function __construct(
		Job $job,
		Settings $settings,
		Paths $paths,
		Logger $logger,
		Db $db,
		Clock $clock,
		Deadline $deadline
	) {
		$this->job      = $job;
		$this->settings = $settings;
		$this->paths    = $paths;
		$this->logger   = $logger;
		$this->db       = $db;
		$this->clock    = $clock;
		$this->deadline = $deadline;
	}

	/**
	 * Job.
	 *
	 * @return Job
	 */
	public function job(): Job {
		return $this->job;
	}

	/**
	 * Settings.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Paths.
	 *
	 * @return Paths
	 */
	public function paths(): Paths {
		return $this->paths;
	}

	/**
	 * Logger.
	 *
	 * @return Logger
	 */
	public function logger(): Logger {
		return $this->logger;
	}

	/**
	 * Database.
	 *
	 * @return Db
	 */
	public function db(): Db {
		return $this->db;
	}

	/**
	 * Clock.
	 *
	 * @return Clock
	 */
	public function clock(): Clock {
		return $this->clock;
	}

	/**
	 * Tick deadline.
	 *
	 * @return Deadline
	 */
	public function deadline(): Deadline {
		return $this->deadline;
	}

	/**
	 * Working directory of the job, guaranteed to exist.
	 *
	 * @return string
	 */
	public function work_dir(): string {
		$dir = $this->paths->job_dir( $this->job->token );

		if ( ! is_dir( $dir ) ) {
			\Backupino\Support\Fs::ensure_dir( $dir );
		}

		return $dir;
	}
}
