<?php
/**
 * Stage contract.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

/**
 * A single resumable step of the backup pipeline.
 *
 * Stages must be idempotent and resumable: the runner may call `run()` many
 * times, possibly on different PHP requests, until it reports completion.
 */
interface Stage {

	/**
	 * Stable identifier, also used as the job `stage` column value.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Human readable label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Relative weight of the stage within the whole job (used for progress).
	 *
	 * @return int
	 */
	public function weight(): int;

	/**
	 * Run one slice of work.
	 *
	 * @param JobContext $context Job services and state.
	 * @return StageResult
	 */
	public function run( JobContext $context ): StageResult;
}
