<?php
/**
 * Real time source.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

/**
 * System clock backed by PHP's `time()`.
 */
final class SystemClock implements Clock {

	/**
	 * {@inheritDoc}
	 */
	public function now(): int {
		return time();
	}

	/**
	 * {@inheritDoc}
	 */
	public function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s', $this->now() );
	}
}
