<?php
/**
 * Job statuses.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

/**
 * Lifecycle states of a backup job.
 */
final class JobStatus {

	public const QUEUED    = 'queued';
	public const RUNNING   = 'running';
	public const COMPLETED = 'completed';
	public const FAILED    = 'failed';
	public const CANCELLED = 'cancelled';

	/**
	 * Whether the job reached a terminal state.
	 *
	 * @param string $status Status name.
	 * @return bool
	 */
	public static function is_final( string $status ): bool {
		return in_array( $status, array( self::COMPLETED, self::FAILED, self::CANCELLED ), true );
	}

	/**
	 * Whether the job can be stepped right now.
	 *
	 * @param string $status Status name.
	 * @return bool
	 */
	public static function is_runnable( string $status ): bool {
		return in_array( $status, array( self::QUEUED, self::RUNNING ), true );
	}

	/**
	 * Human readable label.
	 *
	 * @param string $status Status name.
	 * @return string
	 */
	public static function label( string $status ): string {
		switch ( $status ) {
			case self::QUEUED:
				return __( 'Queued', 'backupino' );
			case self::RUNNING:
				return __( 'Running', 'backupino' );
			case self::COMPLETED:
				return __( 'Completed', 'backupino' );
			case self::FAILED:
				return __( 'Failed', 'backupino' );
			case self::CANCELLED:
				return __( 'Cancelled', 'backupino' );
			default:
				return $status;
		}
	}
}
