<?php
/**
 * Job persistence.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Db;
use Backupino\Core\Schema;
use Backupino\Support\Clock;

/**
 * Repository for the `backupino_jobs` table.
 */
final class JobRepository {

	/**
	 * Database adapter.
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
	 * @param Db    $db    Database adapter.
	 * @param Clock $clock Clock.
	 */
	public function __construct( Db $db, Clock $clock ) {
		$this->db    = $db;
		$this->clock = $clock;
	}

	/**
	 * Persist a new job.
	 *
	 * @param Job $job Job to create.
	 * @return Job
	 */
	public function create( Job $job ): Job {
		$row = $job->to_row( $this->clock );

		$job->id = $this->db->insert( Schema::JOBS_TABLE, $row );

		return $job;
	}

	/**
	 * Save an existing job.
	 *
	 * @param Job $job Job to update.
	 * @return void
	 */
	public function save( Job $job ): void {
		if ( $job->id <= 0 ) {
			return;
		}

		$row = $job->to_row( $this->clock );

		unset( $row['id'] );

		$this->db->update( Schema::JOBS_TABLE, $row, array( 'id' => $job->id ) );
	}

	/**
	 * Find a job by id.
	 *
	 * @param int $id Job id.
	 * @return Job|null
	 */
	public function find( int $id ): ?Job {
		$table = Schema::jobs_table( $this->db );
		$row   = $this->db->row(
			$this->db->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id )
		);

		return null === $row ? null : Job::from_row( $row );
	}

	/**
	 * Find a job by its public token.
	 *
	 * @param string $token Token.
	 * @return Job|null
	 */
	public function find_by_token( string $token ): ?Job {
		$table = Schema::jobs_table( $this->db );
		$row   = $this->db->row(
			$this->db->prepare( "SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token )
		);

		return null === $row ? null : Job::from_row( $row );
	}

	/**
	 * Most recent jobs.
	 *
	 * @param int $limit Maximum number of rows.
	 * @return Job[]
	 */
	public function recent( int $limit = 20 ): array {
		$limit = max( 1, min( 200, $limit ) );
		$table = Schema::jobs_table( $this->db );
		$rows  = $this->db->rows(
			$this->db->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit )
		);

		return array_map( array( Job::class, 'from_row' ), $rows );
	}

	/**
	 * Next job that still needs work.
	 *
	 * @return Job|null
	 */
	public function next_runnable(): ?Job {
		$row = $this->db->row(
			'SELECT * FROM ' . Schema::jobs_table( $this->db )
			. " WHERE status IN ('" . JobStatus::QUEUED . "','" . JobStatus::RUNNING . "')"
			. ' ORDER BY id ASC LIMIT 1'
		);

		return null === $row ? null : Job::from_row( $row );
	}

	/**
	 * Jobs that are not finished yet.
	 *
	 * @return Job[]
	 */
	public function pending(): array {
		$rows = $this->db->rows(
			'SELECT * FROM ' . Schema::jobs_table( $this->db )
			. " WHERE status IN ('" . JobStatus::QUEUED . "','" . JobStatus::RUNNING . "') ORDER BY id ASC"
		);

		return array_map( array( Job::class, 'from_row' ), $rows );
	}

	/**
	 * Delete a job row.
	 *
	 * @param int $id Job id.
	 * @return void
	 */
	public function delete( int $id ): void {
		$table = Schema::jobs_table( $this->db );
		$this->db->query(
			$this->db->prepare( "DELETE FROM {$table} WHERE id = %d", $id )
		);
	}

	/**
	 * Number of jobs by status.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		$rows   = $this->db->rows(
			'SELECT status, COUNT(*) AS total FROM ' . Schema::jobs_table( $this->db ) . ' GROUP BY status'
		);
		$counts = array();

		foreach ( $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}
}
