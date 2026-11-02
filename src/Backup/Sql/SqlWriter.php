<?php
/**
 * SQL file writer.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup\Sql;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only writer for the `database.sql` payload.
 *
 * The writer tracks the exact byte count so that an interrupted job can resume
 * by truncating the file back to the last committed offset.
 */
final class SqlWriter {

	/**
	 * Absolute file path.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Open handle.
	 *
	 * @var resource|null
	 */
	private $handle;

	/**
	 * Bytes written to the file.
	 *
	 * @var int
	 */
	private $bytes = 0;

	/**
	 * Constructor.
	 *
	 * @param string $path Absolute path.
	 */
	public function __construct( string $path ) {
		$this->path = $path;
	}

	/**
	 * File path.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Open the file.
	 *
	 * @param bool $append Whether to append to the existing content.
	 * @return bool
	 */
	public function open( bool $append = true ): bool {
		$mode = $append ? 'ab' : 'wb';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$this->handle = @fopen( $this->path, $mode );

		if ( false === $this->handle ) {
			$this->handle = null;

			return false;
		}

		$this->bytes = $append ? (int) filesize( $this->path ) : 0;

		return true;
	}

	/**
	 * Cut the file back to a known good size.
	 *
	 * @param int $bytes Target size.
	 * @return bool
	 */
	public function truncate( int $bytes ): bool {
		if ( null === $this->handle ) {
			return false;
		}

		if ( $bytes < 0 ) {
			$bytes = 0;
		}

		if ( ! ftruncate( $this->handle, $bytes ) ) {
			return false;
		}

		fseek( $this->handle, $bytes );
		$this->bytes = $bytes;

		return true;
	}

	/**
	 * Append raw text.
	 *
	 * @param string $sql Text.
	 * @return bool
	 */
	public function write( string $sql ): bool {
		if ( null === $this->handle ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_read_fwrite
		$written = fwrite( $this->handle, $sql );

		if ( false === $written ) {
			return false;
		}

		$this->bytes += $written;

		return true;
	}

	/**
	 * Append a terminated statement.
	 *
	 * @param string $sql Statement without the trailing semicolon.
	 * @return bool
	 */
	public function statement( string $sql ): bool {
		return $this->write( rtrim( $sql, ";\n" ) . ";\n" );
	}

	/**
	 * Bytes written so far.
	 *
	 * @return int
	 */
	public function bytes(): int {
		return $this->bytes;
	}

	/**
	 * Close the handle.
	 *
	 * @return void
	 */
	public function close(): void {
		if ( null !== $this->handle ) {
			fclose( $this->handle );
			$this->handle = null;
		}
	}

	/**
	 * Destructor.
	 */
	public function __destruct() {
		$this->close();
	}
}
