<?php
/**
 * Plugin container.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino;

defined( 'ABSPATH' ) || exit;

use Backupino\Admin\Admin;
use Backupino\Backup\Stages\DatabaseStage;
use Backupino\Backup\Stages\FilesStage;
use Backupino\Backup\Stages\FinalizeStage;
use Backupino\Backup\Stages\ScanStage;
use Backupino\Cli\Commands;
use Backupino\Core\Capabilities;
use Backupino\Core\Compatibility;
use Backupino\Core\Db;
use Backupino\Core\Paths;
use Backupino\Core\Settings;
use Backupino\Core\WpDbAdapter;
use Backupino\Cron\Scheduler;
use Backupino\Download\DownloadLink;
use Backupino\Download\PublicDownload;
use Backupino\Jobs\Job;
use Backupino\Notify\Notifier;
use Backupino\Jobs\JobRepository;
use Backupino\Jobs\JobRunner;
use Backupino\Jobs\Pipeline;
use Backupino\Storage\StorageManager;
use Backupino\Support\Clock;
use Backupino\Support\Logger;
use Backupino\Support\SystemClock;
use Backupino\Support\WpOptions;

/**
 * Wires the plugin together and exposes the shared services.
 *
 * The container is deliberately tiny: every service is constructed lazily and
 * only when it is actually needed, so that a request that never touches
 * backups pays no cost.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Clock.
	 *
	 * @var Clock|null
	 */
	private $clock = null;

	/**
	 * Settings.
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Paths.
	 *
	 * @var Paths|null
	 */
	private $paths = null;

	/**
	 * Database adapter.
	 *
	 * @var Db|null
	 */
	private $db = null;

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	private $logger = null;

	/**
	 * Jobs repository.
	 *
	 * @var JobRepository|null
	 */
	private $jobs = null;

	/**
	 * Pipeline.
	 *
	 * @var Pipeline|null
	 */
	private $pipeline = null;

	/**
	 * Runner.
	 *
	 * @var JobRunner|null
	 */
	private $runner = null;

	/**
	 * Permanent download link.
	 *
	 * @var DownloadLink|null
	 */
	private $download_link = null;

	/**
	 * Notification sender.
	 *
	 * @var Notifier|null
	 */
	private $notifier = null;

	/**
	 * Storage manager.
	 *
	 * @var StorageManager|null
	 */
	private $storages = null;

	/**
	 * Private constructor: use {@see instance()}.
	 */
	private function __construct() {
	}

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot(): void {

		if ( Compatibility::is_supported() ) {
			( new Admin() )->register();
			( new Scheduler( $this ) )->register();
			PublicDownload::register();
			add_action( 'backupino_job_completed', array( $this, 'notify_completed_job' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'render_compatibility_notice' ) );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register( $this );
		}
	}


	/**
	 * Show blocking requirements as an admin notice.
	 *
	 * @return void
	 */
	public function render_compatibility_notice(): void {
		if ( ! Capabilities::user_can_manage() ) {
			return;
		}

		$issues = Compatibility::blocking_issues();

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><ul>',
			esc_html__( 'Backupino cannot run on this server:', 'backupino' )
		);

		foreach ( $issues as $issue ) {
			printf( '<li>%s</li>', esc_html( $issue ) );
		}

		echo '</ul></div>';
	}

	/**
	 * Clock.
	 *
	 * @return Clock
	 */
	public function clock(): Clock {
		if ( null === $this->clock ) {
			$this->clock = new SystemClock();
		}

		return $this->clock;
	}

	/**
	 * Settings.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings( new WpOptions() );
		}

		return $this->settings;
	}

	/**
	 * Paths.
	 *
	 * @return Paths
	 */
	public function paths(): Paths {
		if ( null === $this->paths ) {
			$this->paths = Paths::from_wordpress();
		}

		return $this->paths;
	}

	/**
	 * Database adapter.
	 *
	 * @return Db
	 */
	public function db(): Db {
		if ( null === $this->db ) {
			$this->db = new WpDbAdapter();
		}

		return $this->db;
	}

	/**
	 * Permanent download link service.
	 *
	 * @return DownloadLink
	 */
	public function download_link(): DownloadLink {
		if ( null === $this->download_link ) {
			$this->download_link = new DownloadLink( new WpOptions() );
		}

		return $this->download_link;
	}

	/**
	 * Notification sender.
	 *
	 * @return Notifier
	 */
	public function notifier(): Notifier {
		if ( null === $this->notifier ) {
			$this->notifier = new Notifier( $this->settings(), new WpOptions(), $this->download_link() );
		}

		return $this->notifier;
	}

	/**
	 * Human readable site name for messages.
	 *
	 * @return string
	 */
	public function site_name(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( '' !== $host ) {
			return $host;
		}

		return (string) home_url();
	}

	/**
	 * Announce a finished backup.
	 *
	 * @param mixed $job Finished job.
	 * @return void
	 */
	public function notify_completed_job( $job ): void {
		if ( ! $job instanceof Job ) {
			return;
		}

		$this->notifier()->announce( $job, $this->site_name() );
	}

	/**
	 * Logger.
	 *
	 * @return Logger
	 */
	public function logger(): Logger {
		if ( null === $this->logger ) {
			$this->logger = new Logger(
				$this->paths()->logs() . '/backupino.log',
				(string) $this->settings()->get( 'advanced.log_level', 'info' )
			);
		}

		return $this->logger;
	}

	/**
	 * Jobs repository.
	 *
	 * @return JobRepository
	 */
	public function jobs(): JobRepository {
		if ( null === $this->jobs ) {
			$this->jobs = new JobRepository( $this->db(), $this->clock() );
		}

		return $this->jobs;
	}

	/**
	 * Pipeline.
	 *
	 * @return Pipeline
	 */
	public function pipeline(): Pipeline {
		if ( null === $this->pipeline ) {
			$this->pipeline = new Pipeline(
				array(
					new ScanStage(),
					new FilesStage(),
					new DatabaseStage(),
					new FinalizeStage(),
				)
			);
		}

		return $this->pipeline;
	}

	/**
	 * Job runner.
	 *
	 * @return JobRunner
	 */
	public function runner(): JobRunner {
		if ( null === $this->runner ) {
			$this->runner = new JobRunner(
				$this->jobs(),
				$this->pipeline(),
				$this->settings(),
				$this->paths(),
				$this->logger(),
				$this->db(),
				$this->clock()
			);
		}

		return $this->runner;
	}

	/**
	 * Storage manager.
	 *
	 * @return StorageManager
	 */
	public function storages(): StorageManager {
		if ( null === $this->storages ) {
			$this->storages = new StorageManager( $this->settings(), $this->paths() );
		}

		return $this->storages;
	}
}
