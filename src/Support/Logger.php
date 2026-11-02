<?php
/**
 * File based logger used by jobs and the installer.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Robust logging directory creation.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Atomic append logging.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rename -- Log file rotation.

/**
 * Append-only line logger with size based rotation.
 *
 * The logger is intentionally defensive: a logging failure must never abort a
 * backup, so every filesystem error is swallowed.
 */
final class Logger {

	/**
	 * Absolute path of the log file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Minimum level that is written.
	 *
	 * @var string
	 */
	private $threshold;

	/**
	 * Maximum log file size before rotation, in bytes.
	 *
	 * @var int
	 */
	private $max_bytes;

	/**
	 * Constructor.
	 *
	 * @param string $file      Absolute log file path.
	 * @param string $threshold Minimum level to write.
	 * @param int    $max_bytes Rotation threshold in bytes.
	 */
	public function __construct( string $file, string $threshold = LogLevel::INFO, int $max_bytes = 1048576 ) {
		$this->file      = $file;
		$this->threshold = $threshold;
		$this->max_bytes = $max_bytes;
	}

	/**
	 * Write a log record.
	 *
	 * @param string               $level   Level name.
	 * @param string               $message Human readable message.
	 * @param array<string, mixed> $context Structured context.
	 * @return void
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( LogLevel::weight( $level ) < LogLevel::weight( $this->threshold ) ) {
			return;
		}

		$line = sprintf(
			'[%s] [%s] %s',
			gmdate( 'Y-m-d H:i:s' ),
			$level,
			$message
		);

		if ( array() !== $context ) {
			$encoded = wp_json_encode( $context );

			if ( is_string( $encoded ) ) {
				$line .= ' ' . $encoded;
			}
		}

		$this->write( $line . "\n" );
	}

	/**
	 * Debug level shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( LogLevel::DEBUG, $message, $context );
	}

	/**
	 * Info level shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( LogLevel::INFO, $message, $context );
	}

	/**
	 * Warning level shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( LogLevel::WARNING, $message, $context );
	}

	/**
	 * Error level shortcut.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( LogLevel::ERROR, $message, $context );
	}

	/**
	 * Absolute path of the log file.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Read the last N lines of the log.
	 *
	 * @param int $lines Line count.
	 * @return string
	 */
	public function tail( int $lines = 200 ): string {
		if ( ! is_readable( $this->file ) ) {
			return '';
		}

		$content = (string) file_get_contents( $this->file );
		$rows    = explode( "\n", rtrim( $content, "\n" ) );

		if ( count( $rows ) > $lines ) {
			$rows = array_slice( $rows, -1 * $lines );
		}

		return implode( "\n", $rows );
	}

	/**
	 * Append a line to the log file, rotating it when it grows too large.
	 *
	 * @param string $line Fully formatted line including the newline.
	 * @return void
	 */
	private function write( string $line ): void {
		$dir = dirname( $this->file );

		if ( ! is_dir( $dir ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@mkdir( $dir, 0755, true );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$size = is_file( $this->file ) ? (int) @filesize( $this->file ) : 0;

		if ( $size > $this->max_bytes ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@rename( $this->file, $this->file . '.1' );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@file_put_contents( $this->file, $line, FILE_APPEND | LOCK_EX );
	}
}
