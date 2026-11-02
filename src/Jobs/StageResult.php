<?php
/**
 * Stage execution result.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of a single stage slice.
 */
final class StageResult {

	/**
	 * Whether the stage finished for good.
	 *
	 * @var bool
	 */
	private $completed;

	/**
	 * Progress inside the stage, 0.0 - 1.0.
	 *
	 * @var float
	 */
	private $fraction;

	/**
	 * Optional status message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Constructor.
	 *
	 * @param bool   $completed Whether the stage is done.
	 * @param float  $fraction  Progress inside the stage.
	 * @param string $message   Status message.
	 */
	private function __construct( bool $completed, float $fraction, string $message ) {
		$this->completed = $completed;
		$this->fraction  = max( 0.0, min( 1.0, $fraction ) );
		$this->message   = $message;
	}

	/**
	 * The stage still has work left.
	 *
	 * @param float  $fraction Progress inside the stage.
	 * @param string $message  Status message.
	 * @return self
	 */
	public static function running( float $fraction, string $message = '' ): self {
		return new self( false, $fraction, $message );
	}

	/**
	 * The stage is finished.
	 *
	 * @param string $message Final message.
	 * @return self
	 */
	public static function completed( string $message = '' ): self {
		return new self( true, 1.0, $message );
	}

	/**
	 * Whether the stage finished.
	 *
	 * @return bool
	 */
	public function is_completed(): bool {
		return $this->completed;
	}

	/**
	 * Progress inside the stage.
	 *
	 * @return float
	 */
	public function fraction(): float {
		return $this->fraction;
	}

	/**
	 * Status message.
	 *
	 * @return string
	 */
	public function message(): string {
		return $this->message;
	}
}
