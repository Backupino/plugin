<?php
/**
 * Chunked archive extractor.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * Extracts the package archive in slices so that huge sites do not time out.
 */
final class Extractor {

	/**
	 * Archive path.
	 *
	 * @var string
	 */
	private $archive;

	/**
	 * Destination directory.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * Open archive.
	 *
	 * @var \ZipArchive|null
	 */
	private $zip;

	/**
	 * Entry names.
	 *
	 * @var string[]
	 */
	private $names = array();

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Constructor.
	 *
	 * @param string $archive   Absolute archive path.
	 * @param string $directory Destination directory.
	 */
	public function __construct( string $archive, string $directory ) {
		$this->archive   = $archive;
		$this->directory = rtrim( $directory, '/' );
	}

	/**
	 * Open the archive.
	 *
	 * @return bool
	 */
	public function open(): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->error = __( 'The Zip extension is not available on this server.', 'backupino' );

			return false;
		}

		if ( ! is_file( $this->archive ) ) {
			$this->error = __( 'The archive file is missing.', 'backupino' );

			return false;
		}

		$this->zip = new \ZipArchive();

		if ( true !== $this->zip->open( $this->archive ) ) {
			$this->error = __( 'The archive cannot be opened. Is it complete?', 'backupino' );
			$this->zip   = null;

			return false;
		}

		$this->names = array();

		for ( $i = 0; $i < $this->zip->numFiles; $i++ ) {
			$name = $this->zip->getNameIndex( $i );

			if ( false !== $name ) {
				$this->names[] = $name;
			}
		}

		return true;
	}

	/**
	 * Total number of entries.
	 *
	 * @return int
	 */
	public function total(): int {
		return count( $this->names );
	}

	/**
	 * Extract a slice.
	 *
	 * @param int $offset Entry offset (by reference).
	 * @param int $limit  Maximum entries.
	 * @return int Extracted entries.
	 */
	public function step( int &$offset, int $limit ): int {
		if ( null === $this->zip ) {
			return 0;
		}

		$done  = 0;
		$total = count( $this->names );

		while ( $offset < $total && $done < $limit ) {
			$name = $this->names[ $offset ];
			++$offset;

			if ( ! self::is_safe( $name ) ) {
				$this->error = sprintf( /* translators: %s: archive entry name. */ __( 'Skipped unsafe entry: %s', 'backupino' ), $name );
				continue;
			}

			$target = $this->directory . '/' . $name;

			if ( '/' === substr( $name, -1 ) ) {
				if ( ! is_dir( $target ) ) {
					@mkdir( $target, 0755, true );
				}

				continue;
			}

			$parent = dirname( $target );

			if ( ! is_dir( $parent ) ) {
				@mkdir( $parent, 0755, true );
			}

			if ( $this->zip->extractTo( $this->directory, $name ) ) {
				++$done;
			}
		}

		return $done;
	}

	/**
	 * Close the archive.
	 *
	 * @return void
	 */
	public function close(): void {
		if ( null !== $this->zip ) {
			$this->zip->close();
			$this->zip = null;
		}
	}

	/**
	 * Last error.
	 *
	 * @return string
	 */
	public function error(): string {
		return $this->error;
	}

	/**
	 * Reject entries that would escape the destination.
	 *
	 * @param string $name Entry name.
	 * @return bool
	 */
	/**
	 * Initial state.
	 *
	 * @return array<string, mixed>
	 */
	public static function initial_state(): array {
		return array(
			'offset' => 0,
			'total'  => 0,
			'done'   => false,
		);
	}

	public static function is_safe( string $name ): bool {
		$name = str_replace( '\\', '/', $name );

		if ( '' === $name || '/' === $name[0] || false !== strpos( $name, "\0" ) ) {
			return false;
		}

		if ( preg_match( '#(^|/)\.\.(/|$)#', $name ) ) {
			return false;
		}

		return false === strpos( $name, ':' );
	}
}
