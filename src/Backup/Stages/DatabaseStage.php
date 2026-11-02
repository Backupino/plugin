<?php
/**
 * Database dump stage.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup\Stages;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal pipeline exceptions are caught and sanitized before presentation.

use Backupino\Backup\Sql\MySqlDumper;
use Backupino\Backup\Sql\SqlWriter;
use Backupino\Jobs\JobContext;
use Backupino\Jobs\JobException;
use Backupino\Jobs\Stage;
use Backupino\Jobs\StageResult;
use Backupino\Support\Bytes;

/**
 * Dumps every table into `database.sql`.
 */
final class DatabaseStage implements Stage {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'database';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Copying the database', 'backupino' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): int {
		return 32;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( JobContext $context ): StageResult {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$job = $context->job();

		if ( ! $context->settings()->get( 'archive.database', true ) ) {
			return StageResult::completed( __( 'Database dump disabled.', 'backupino' ) );
		}

		$state = $job->stage_state( $this->id() );
		$file  = $context->work_dir() . '/database.sql';

		if ( ! isset( $state['dumper'] ) || ! is_array( $state['dumper'] ) ) {
			$state = array(
				'bytes'  => 0,
				'dumper' => MySqlDumper::initial_state(),
			);
		}

		$writer = new SqlWriter( $file );

		if ( ! $writer->open( true ) ) {
			throw new JobException( __( 'The SQL dump file could not be opened.', 'backupino' ) );
		}

		// Drop anything a previous, interrupted tick may have left behind.
		$writer->truncate( (int) $state['bytes'] );

		$dumper       = new MySqlDumper( $context->db(), $writer );
		$dumper_state = $dumper->step(
			$state['dumper'],
			$context->deadline(),
			(int) $context->settings()->get( 'runtime.rows_per_tick', 1000 )
		);

		$writer->close();

		$state['dumper'] = $dumper_state;
		$state['bytes']  = $dumper_state['done'] ? \Backupino\Support\Fs::size( $file ) : $writer->bytes();

		$job->set_stage_state( $this->id(), $state );

		$message = sprintf(
			/* translators: 1: number of rows, 2: number of tables, 3: dumped size. */
			__( '%1$s rows from %2$s tables (%3$s).', 'backupino' ),
			number_format_i18n( (int) $dumper_state['count'] ),
			number_format_i18n( (int) $dumper_state['index'] ),
			Bytes::format( (int) $state['bytes'] )
		);

		if ( $dumper_state['done'] ) {
			return StageResult::completed( $message );
		}

		$total = max( 1, count( (array) $dumper_state['tables'] ) );
		$ratio = min( 0.99, (float) $dumper_state['index'] / (float) $total );

		return StageResult::running( $ratio, $message );
	}
}
