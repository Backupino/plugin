<?php
/**
 * Time budget helper.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Monotonic deadline shared by every stage of a single job tick.
 */
class Deadline {

	/**
	 * Absolute expiry timestamp (microtime).
	 *
	 * @var float
	 */
	private $expires_at;

	/**
	 * Constructor.
	 *
	 * @param int $seconds Budget in seconds.
	 */
	public function __construct( int $seconds ) {
		$this->expires_at = microtime( true ) + max( 1, $seconds );
	}

	/**
	 * Seconds left, never negative.
	 *
	 * @return float
	 */
	public function remaining(): float {
		return max( 0.0, $this->expires_at - microtime( true ) );
	}

	/**
	 * Whether the budget is exhausted.
	 *
	 * @return bool
	 */
	public function expired(): bool {
		return $this->remaining() <= 0.0;
	}

	/**
	 * Add more time to the budget.
	 *
	 * @param int $seconds Extra seconds.
	 * @return void
	 */
	public function extend( int $seconds ): void {
		$this->expires_at += max( 0, $seconds );
	}
}
