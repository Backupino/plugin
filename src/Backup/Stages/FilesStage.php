<?php
/**
 * Files archiving stage.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup\Stages;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal pipeline exceptions are caught and sanitized before presentation.

use Backupino\Backup\Manifest;
use Backupino\Backup\ZipArchiveWriter;
use Backupino\Jobs\JobContext;
use Backupino\Jobs\JobException;
use Backupino\Jobs\Stage;
use Backupino\Jobs\StageResult;
use Backupino\Support\Bytes;

/**
 * Streams manifest entries into the archive, a slice at a time.
 */
final class FilesStage implements Stage {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'files';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Archiving files', 'backupino' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): int {
		return 50;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( JobContext $context ): StageResult {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$job      = $context->job();
		$manifest = new Manifest( $context->work_dir() . '/manifest.ndjson', $context->paths()->root() );

		$state = array_merge(
			array(
				'offset' => 0,
				'added'  => 0,
				'failed' => 0,
				'total'  => 0,
				'done'   => false,
			),
			$job->stage_state( $this->id() )
		);

		if ( (int) $state['total'] <= 0 ) {
			$state['total'] = (int) $manifest->count();
		}

		if ( ! $context->settings()->get( 'archive.files', true ) ) {
			return StageResult::completed( __( 'File archiving disabled.', 'backupino' ) );
		}

		$per_tick = (int) $context->settings()->get( 'runtime.files_per_tick', 1000 );
		$archive  = $context->work_dir() . '/archive.zip';
		$writer   = new ZipArchiveWriter( (bool) $context->settings()->get( 'advanced.compression', true ) );

		if ( ! $writer->open( $archive ) ) {
			throw new JobException( $writer->error() );
		}

		$processed = 0;

		while ( ! $state['done'] && $processed < $per_tick && ! $context->deadline()->expired() ) {
			$chunk = $manifest->read_at( (int) $state['offset'], min( 250, $per_tick - $processed ) );

			if ( array() === $chunk['entries'] ) {
				$file_size = (int) @filesize( $manifest->path() );
				$is_eof    = ( $file_size > 0 && (int) $state['offset'] >= $file_size )
					|| ( (int) $state['total'] > 0 && (int) $state['added'] >= (int) $state['total'] );

				if ( $is_eof ) {
					$state['done'] = true;
				} else {
					$context->logger()->warning(
						'Empty manifest chunk before EOF reached.',
						array(
							'job'      => $job->id,
							'offset'   => $state['offset'],
							'filesize' => $file_size,
							'added'    => $state['added'],
							'total'    => $state['total'],
						)
					);
				}
				break;
			}

			foreach ( $chunk['entries'] as $entry ) {
				if ( $writer->add_file( $entry->path(), $entry->relative() ) ) {
					++$state['added'];
				} else {
					++$state['failed'];
					$context->logger()->warning(
						'File skipped.',
						array(
							'job'   => $job->id,
							'file'  => $entry->relative(),
							'error' => $writer->error(),
						)
					);
				}

				++$processed;
			}

			$state['offset'] = $chunk['offset'];
		}

		if ( ! $writer->close() ) {
			throw new JobException( $writer->error() );
		}

		$job->set_stage_state( $this->id(), $state );

		$total = max( 1, (int) $state['total'] );
		$ratio = min( 1.0, (float) $state['added'] / (float) $total );

		if ( $state['done'] && (int) $state['added'] < $total ) {
			$file_size = (int) @filesize( $manifest->path() );
			if ( $file_size > 0 && (int) $state['offset'] < $file_size ) {
				$state['done'] = false;
			}
		}

		$message = sprintf(
			/* translators: 1: packed files, 2: total files, 3: skipped files, 4: archive size. */
			__( 'Packed %1$s of %2$s files (%3$s skipped, %4$s).', 'backupino' ),
			number_format_i18n( (int) $state['added'] ),
			number_format_i18n( $total ),
			number_format_i18n( (int) $state['failed'] ),
			Bytes::format( \Backupino\Support\Fs::size( $archive ) )
		);

		return $state['done'] ? StageResult::completed( $message ) : StageResult::running( $ratio, $message );
	}
}
