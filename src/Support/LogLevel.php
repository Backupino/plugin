<?php
/**
 * Log severity levels.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical log level names.
 */
final class LogLevel {

	public const DEBUG   = 'debug';
	public const INFO    = 'info';
	public const WARNING = 'warning';
	public const ERROR   = 'error';

	/**
	 * Numeric weight of a level, used for threshold filtering.
	 *
	 * @param string $level Level name.
	 * @return int
	 */
	public static function weight( string $level ): int {
		switch ( $level ) {
			case self::DEBUG:
				return 10;
			case self::INFO:
				return 20;
			case self::WARNING:
				return 30;
			case self::ERROR:
				return 40;
			default:
				return 0;
		}
	}
}
