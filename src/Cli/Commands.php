<?php
/**
 * WP-CLI commands.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Cli;

use Backupino\Core\Compatibility;
use Backupino\Jobs\Job;
use Backupino\Plugin;
use Backupino\Support\Bytes;

/**
 * Registers the `wp backupino` command tree.
 */
final class Commands {

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
	 * Register the CLI command.
	 *
	 * @param Plugin $plugin Plugin container.
	 * @return void
	 */
	public static function register( Plugin $plugin ): void {
		\WP_CLI::add_command( 'backupino', new self( $plugin ) );
	}

	/**
	 * Create a backup and run it to completion.
	 *
	 * ## OPTIONS
	 *
	 * [--files]
	 * : Include files.
	 *
	 * [--database]
	 * : Include the database.
	 *
	 * [--db-only]
	 * : Shorthand for `--database` without files.
	 *
	 * [--quiet]
	 * : Suppress progress output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp backupino create --db-only
	 *     wp backupino create
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function create( array $args, array $assoc_args ): void {
		$files    = isset( $assoc_args['files'] ) || ! isset( $assoc_args['db-only'] );
		$database = isset( $assoc_args['database'] ) || ! isset( $assoc_args['files'] ) || isset( $assoc_args['db-only'] );

		$job = Job::create(
			array(
				'type'     => 'cli',
				'files'    => $files,
				'database' => $database,
			),
			$this->plugin->clock()
		);

		$this->plugin->jobs()->create( $job );

		\WP_CLI::log( sprintf( 'Starting job #%d', $job->id ) );

		if ( ! isset( $assoc_args['quiet'] ) ) {
			\WP_CLI::log( 'This may take a while on large sites.' );
		}

		$job = $this->plugin->runner()->run( $job );

		if ( 'completed' !== $job->status ) {
			\WP_CLI::error( sprintf( 'Backup failed: %s', $job->message ) );
		}

		$package = isset( $job->payload['package'] ) ? (array) $job->payload['package'] : array();

		\WP_CLI::success(
			sprintf(
				'Package %s (%s)',
				(string) ( $package['name'] ?? '' ),
				Bytes::format( (int) ( $package['size'] ?? 0 ) )
			)
		);
	}

	/**
	 * List recent jobs.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Default: table.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function jobs( array $args, array $assoc_args ): void {
		$rows = array();

		foreach ( $this->plugin->jobs()->recent( 50 ) as $job ) {
			$rows[] = array(
				'id'       => $job->id,
				'status'   => $job->status,
				'stage'    => $job->stage,
				'progress' => $job->progress . '%',
				'created'  => $job->created_at,
				'message'  => $job->message,
			);
		}

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'status', 'stage', 'progress', 'created', 'message' ) );
	}

	/**
	 * Advance a pending job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job id.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		$id  = isset( $args[0] ) ? (int) $args[0] : 0;
		$job = $this->plugin->jobs()->find( $id );

		if ( null === $job ) {
			\WP_CLI::error( 'Job not found.' );
		}

		$job = $this->plugin->runner()->run( $job );

		\WP_CLI::success( sprintf( 'Job #%d is %s (progress %d%%)', $job->id, $job->status, $job->progress ) );
	}

	/**
	 * Show the environment / system status.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		$snapshot = Compatibility::snapshot( $this->plugin->paths() );

		foreach ( $snapshot as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = array() === $value ? '—' : implode( ' | ', $value );
			}

			\WP_CLI::log( sprintf( '%-18s %s', $key, (string) $value ) );
		}
	}

	/**
	 * Inspect or reset the settings.
	 *
	 * ## OPTIONS
	 *
	 * [--reset]
	 * : Restore the default settings.
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function settings( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['reset'] ) ) {
			$this->plugin->settings()->reset();
			\WP_CLI::success( 'Settings restored to defaults.' );

			return;
		}

		\WP_CLI::log( (string) wp_json_encode( $this->plugin->settings()->all(), JSON_PRETTY_PRINT ) );
	}
}
