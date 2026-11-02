<?php
/**
 * Scan stage.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup\Stages;

defined( 'ABSPATH' ) || exit;

use Backupino\Backup\FileEntry;
use Backupino\Backup\FileFilter;
use Backupino\Backup\Manifest;
use Backupino\Backup\Scanner;
use Backupino\Jobs\JobContext;
use Backupino\Jobs\Stage;
use Backupino\Jobs\StageResult;
use Backupino\Support\Bytes;

/**
 * Builds the manifest of files that will be archived.
 */
final class ScanStage implements Stage {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'scan';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Scanning files', 'backupino' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): int {
		return 8;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( JobContext $context ): StageResult {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$job      = $context->job();
		$state    = $job->stage_state( $this->id() );
		$manifest = new Manifest( $context->work_dir() . '/manifest.ndjson', $context->paths()->root() );

		if ( array() === $state ) {
			$manifest->reset();
			$state = Scanner::initial_state();
		}

		$settings = $context->settings();
		$filter   = new FileFilter(
			(array) $settings->get( 'archive.exclude_rules', array() ),
			(int) $settings->get( 'archive.ignore_larger_than', 0 )
		);

		$scanner = new Scanner( $context->paths(), $filter );

		$state = $scanner->scan(
			$state,
			$context->deadline(),
			static function ( FileEntry $entry ) use ( $manifest ) {
				$manifest->append( $entry );
			}
		);

		$job->set_stage_state( $this->id(), $state );

		$message = sprintf(
			/* translators: 1: number of files, 2: total size. */
			__( '%1$s files found (%2$s).', 'backupino' ),
			number_format_i18n( (int) $state['files'] ),
			Bytes::format( (int) $state['bytes'] )
		);

		if ( ! $state['done'] ) {
			return StageResult::running( 0.5, $message );
		}

		$state['total'] = $manifest->count();
		$job->set_stage_state( $this->id(), $state );

		// Prime the archiving stage with the discovered totals.
		$files_state          = $job->stage_state( 'files' );
		$files_state['total'] = $state['total'];
		$job->set_stage_state( 'files', $files_state );

		return StageResult::completed( $message );
	}
}
