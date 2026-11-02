<?php
/**
 * Backup pipeline definition.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

/**
 * Ordered list of stages with helpers for progression maths.
 */
final class Pipeline {

	/**
	 * Registered stages, keyed by id.
	 *
	 * @var array<string, Stage>
	 */
	private $stages = array();

	/**
	 * Constructor.
	 *
	 * @param Stage[] $stages Stages in execution order.
	 */
	public function __construct( array $stages ) {
		foreach ( $stages as $stage ) {
			if ( ! $stage instanceof Stage ) {
				continue;
			}

			$this->stages[ $stage->id() ] = $stage;
		}
	}

	/**
	 * All stages in order.
	 *
	 * @return array<string, Stage>
	 */
	public function stages(): array {
		return $this->stages;
	}

	/**
	 * Fetch a stage by id.
	 *
	 * @param string $id Stage id.
	 * @return Stage|null
	 */
	public function get( string $id ): ?Stage {
		return isset( $this->stages[ $id ] ) ? $this->stages[ $id ] : null;
	}

	/**
	 * First stage id.
	 *
	 * @return string
	 */
	public function first(): string {
		$ids = array_keys( $this->stages );

		return isset( $ids[0] ) ? (string) $ids[0] : '';
	}

	/**
	 * Id of the stage that follows the given one.
	 *
	 * @param string $id Stage id.
	 * @return string Empty string when the pipeline is finished.
	 */
	public function next( string $id ): string {
		$ids = array_keys( $this->stages );
		$pos = array_search( $id, $ids, true );

		if ( false === $pos ) {
			return '';
		}

		return isset( $ids[ $pos + 1 ] ) ? (string) $ids[ $pos + 1 ] : '';
	}

	/**
	 * Overall progress (0-100) for a given stage and stage fraction.
	 *
	 * @param string $id       Stage id.
	 * @param float  $fraction Progress inside the stage.
	 * @return int
	 */
	public function progress( string $id, float $fraction ): int {
		$total     = 0;
		$completed = 0.0;
		$found     = false;

		foreach ( $this->stages as $stage ) {
			$total += $stage->weight();

			if ( $stage->id() === $id ) {
				$completed += $stage->weight() * max( 0.0, min( 1.0, $fraction ) );
				$found      = true;
			} elseif ( ! $found ) {
				$completed += $stage->weight();
			}
		}

		if ( $total <= 0 ) {
			return 0;
		}

		return (int) floor( ( $completed / $total ) * 100 );
	}
}
