<?php
/**
 * Zip backend.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * `ZipArchive` based writer.
 *
 * The archive is opened and closed once per tick; libzip appends to an existing
 * central directory, so this stays safe and keeps the handle count low.
 */
final class ZipArchiveWriter implements ArchiveWriter {

	/**
	 * Underlying archive.
	 *
	 * @var \ZipArchive|null
	 */
	private $zip;

	/**
	 * Whether deflate compression is used.
	 *
	 * @var bool
	 */
	private $compress;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';
	private $path = '';

	/**
	 * Constructor.
	 *
	 * @param bool $compress Whether to deflate entries.
	 */
	public function __construct( bool $compress = true ) {
		$this->compress = $compress;
	}

	/**
	 * {@inheritDoc}
	 */
	public function open( string $path ): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->error = __( 'The Zip extension is not available.', 'backupino' );

			return false;
		}

		$this->path = $path;
		$this->zip  = new \ZipArchive();

		$result = $this->zip->open( $path, \ZipArchive::CREATE );

		if ( true !== $result ) {
			$this->error = sprintf(
				/* translators: %d: ZipArchive error code. */
				__( 'Could not open the archive (error %d).', 'backupino' ),
				(int) $result
			);
			$this->zip = null;

			return false;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function add_file( string $absolute, string $local ): bool {
		if ( null === $this->zip ) {
			$this->error = __( 'The archive is not open.', 'backupino' );

			return false;
		}

		$local = ltrim( str_replace( '\\', '/', $local ), '/' );

		if ( ! is_readable( $absolute ) ) {
			$this->error = sprintf(
				/* translators: %s: file path. */
				__( 'File is not readable: %s', 'backupino' ),
				$absolute
			);

			return false;
		}

		if ( ! $this->zip->addFile( $absolute, $local ) ) {
			$this->error = sprintf(
				/* translators: %s: file path. */
				__( 'Could not add file to archive: %s', 'backupino' ),
				$absolute
			);

			return false;
		}

		if ( $this->compress ) {
			$index = $this->zip->numFiles - 1;

			if ( $index >= 0 ) {
				$this->zip->setCompressionIndex( $index, \ZipArchive::CM_DEFLATE );
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function add_from_string( string $local, string $contents ): bool {
		if ( null === $this->zip ) {
			$this->error = __( 'The archive is not open.', 'backupino' );

			return false;
		}

		$local = ltrim( str_replace( '\\', '/', $local ), '/' );

		return (bool) $this->zip->addFromString( $local, $contents );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count(): int {
		return null === $this->zip ? 0 : (int) $this->zip->numFiles;
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): bool {
		if ( null === $this->zip ) {
			return true;
		}

		$status = '';
		$closed = false;

		try {
			if ( '' !== $this->path ) {
				clearstatcache( true, $this->path );
				@chmod( $this->path, 0666 );
			}
			$closed = (bool) @$this->zip->close();
		} catch ( \Throwable $t ) {
			$closed = false;
			$status = $t->getMessage();
		}

		if ( ! $closed && '' === $status && null !== $this->zip && method_exists( $this->zip, 'getStatusString' ) ) {
			try {
				$status = (string) $this->zip->getStatusString();
			} catch ( \Throwable $t ) {
				// Ignored.
			}
		}
		$this->zip = null;

		if ( ! $closed ) {
			/* translators: %s: status description. */
			$this->error = ( '' !== $status && 'No error' !== $status ) ? sprintf( __( 'The archive could not be finalized (%s).', 'backupino' ), $status ) : __( 'The archive could not be finalized.', 'backupino' );
		}

		return (bool) $closed;
	}

	/**
	 * {@inheritDoc}
	 */
	public function engine(): string {
		return 'zip';
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): string {
		return $this->error;
	}
}
