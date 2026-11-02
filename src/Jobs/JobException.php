<?php
/**
 * Job execution exception.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when a stage cannot continue; the runner marks the job as failed.
 */
final class JobException extends \RuntimeException {
}
