<?php
/**
 * Backup job entity.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

use Backupino\Support\Clock;
use Backupino\Support\Str;

/**
 * Value object representing a single backup job.
 */
final class Job {

	/**
	 * Database id, `0` until persisted.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Public random token, also used as the working directory name.
	 *
	 * @var string
	 */
	public $token = '';

	/**
	 * Current status.
	 *
	 * @var string
	 */
	public $status = JobStatus::QUEUED;

	/**
	 * Current stage id.
	 *
	 * @var string
	 */
	public $stage = '';

	/**
	 * Overall progress, 0-100.
	 *
	 * @var int
	 */
	public $progress = 0;

	/**
	 * Arbitrary job data: options and per-stage cursors.
	 *
	 * @var array<string, mixed>
	 */
	public $payload = array();

	/**
	 * Last human readable message.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Creation timestamp (UTC, MySQL format).
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Last update timestamp (UTC, MySQL format).
	 *
	 * @var string
	 */
	public $updated_at = '';

	/**
	 * Create a fresh job.
	 *
	 * @param array<string, mixed> $options Job options (`type`, `note`, ...).
	 * @param Clock                $clock   Time source.
	 * @return self
	 */
	public static function create( array $options, Clock $clock ): self {
		$job             = new self();
		$job->token      = Str::random( 8 );
		$job->created_at = $clock->now_mysql();
		$job->updated_at = $job->created_at;
		$job->payload    = array(
			'options' => $options,
			'stages'  => array(),
		);

		return $job;
	}

	/**
	 * Hydrate from a database row.
	 *
	 * @param array<string, mixed> $row Row data.
	 * @return self
	 */
	public static function from_row( array $row ): self {
		$job             = new self();
		$job->id         = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$job->token      = isset( $row['token'] ) ? (string) $row['token'] : '';
		$job->status     = isset( $row['status'] ) ? (string) $row['status'] : JobStatus::QUEUED;
		$job->stage      = isset( $row['stage'] ) ? (string) $row['stage'] : '';
		$job->progress   = isset( $row['progress'] ) ? (int) $row['progress'] : 0;
		$job->message    = isset( $row['message'] ) ? (string) $row['message'] : '';
		$job->created_at = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
		$job->updated_at = isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '';

		$payload = isset( $row['payload'] ) ? json_decode( (string) $row['payload'], true ) : array();

		$job->payload = is_array( $payload ) ? $payload : array();

		if ( ! isset( $job->payload['options'] ) ) {
			$job->payload['options'] = array();
		}

		if ( ! isset( $job->payload['stages'] ) ) {
			$job->payload['stages'] = array();
		}

		return $job;
	}

	/**
	 * Convert to a database row.
	 *
	 * @param Clock $clock Time source.
	 * @return array<string, mixed>
	 */
	public function to_row( Clock $clock ): array {
		$this->updated_at = $clock->now_mysql();

		$row = array(
			'token'      => $this->token,
			'status'     => $this->status,
			'stage'      => $this->stage,
			'progress'   => $this->progress,
			'payload'    => wp_json_encode( $this->payload ),
			'message'    => $this->message,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		);

		if ( $this->id > 0 ) {
			$row['id'] = $this->id;
		}

		return $row;
	}

	/**
	 * Read a job option.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public function option( string $key, $fallback = null ) {
		if ( ! isset( $this->payload['options'][ $key ] ) ) {
			return $fallback;
		}

		return $this->payload['options'][ $key ];
	}

	/**
	 * Cursor data of a stage.
	 *
	 * @param string $stage Stage id.
	 * @return array<string, mixed>
	 */
	public function stage_state( string $stage ): array {
		if ( ! isset( $this->payload['stages'][ $stage ] ) || ! is_array( $this->payload['stages'][ $stage ] ) ) {
			return array();
		}

		return $this->payload['stages'][ $stage ];
	}

	/**
	 * Store cursor data of a stage.
	 *
	 * @param string               $stage Stage id.
	 * @param array<string, mixed> $state Cursor data.
	 * @return void
	 */
	public function set_stage_state( string $stage, array $state ): void {
		$this->payload['stages'][ $stage ] = $state;
	}

	/**
	 * Whether the job finished successfully.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		return JobStatus::COMPLETED === $this->status;
	}

	/**
	 * Whether the job can still be stepped.
	 *
	 * @return bool
	 */
	public function is_runnable(): bool {
		return JobStatus::is_runnable( $this->status );
	}

	/**
	 * Serialize for the REST API.
	 *
	 * @return array<string, mixed>
	 */
	public function to_api(): array {
		return array(
			'id'         => $this->id,
			'token'      => $this->token,
			'status'     => $this->status,
			'statusText' => JobStatus::label( $this->status ),
			'stage'      => $this->stage,
			'progress'   => $this->progress,
			'message'    => $this->message,
			'createdAt'  => $this->created_at,
			'updatedAt'  => $this->updated_at,
			'options'    => isset( $this->payload['options'] ) ? $this->payload['options'] : array(),
		);
	}
}
