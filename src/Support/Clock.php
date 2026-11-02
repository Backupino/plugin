<?php
/**
 * Time source abstraction.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the current time. Abstracted so that job scheduling and retention
 * policies can be tested deterministically.
 */
interface Clock {

	/**
	 * Current UNIX timestamp.
	 *
	 * @return int
	 */
	public function now(): int;

	/**
	 * Current UTC time formatted for MySQL (`Y-m-d H:i:s`).
	 *
	 * @return string
	 */
	public function now_mysql(): string;
}
