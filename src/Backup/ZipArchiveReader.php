<?php
/**
 * Zip reader.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

/**
 * `ZipArchive` based reader with path traversal protection.
 */
final class ZipArchiveReader implements ArchiveReader {

	/**
	 * Underlying archive.
	 *
	 * @var \ZipArchive|null
	 */
	private $zip;

	/**
	 * Cached entry list.
	 *
	 * @var string[]|null
	 */
	private $names = null;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * {@inheritDoc}
	 */
	public function open( string $path ): bool {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->error = 'Zip extension unavailable';

			return false;
		}

		$this->zip = new \ZipArchive();

		if ( true !== $this->zip->open( $path ) ) {
			$this->error = 'Cannot open archive';
			$this->zip   = null;

			return false;
		}

		$this->names = null;

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function entries(): array {
		if ( null === $this->zip ) {
			return array();
		}

		if ( null === $this->names ) {
			$this->names = array();

			for ( $i = 0; $i < $this->zip->numFiles; $i++ ) {
				$name = $this->zip->getNameIndex( $i );

				if ( false !== $name ) {
					$this->names[] = $name;
				}
			}
		}

		return $this->names;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read( string $name ): ?string {
		if ( null === $this->zip ) {
			return null;
		}

		$content = $this->zip->getFromName( $name );

		return false === $content ? null : (string) $content;
	}

	/**
	 * {@inheritDoc}
	 */
	public function extract( string $directory, int $offset, int $limit, int &$total = 0 ): int {
		if ( null === $this->zip ) {
			return 0;
		}

		$names = $this->entries();
		$total = count( $names );
		$done  = 0;

		for ( $i = max( 0, $offset ); $i < $total && $done < $limit; $i++ ) {
			$name = $names[ $i ];

			if ( ! self::is_safe_entry( $name ) ) {
				$this->error = 'Unsafe archive entry: ' . $name;
				continue;
			}

			$target = rtrim( $directory, '/' ) . '/' . $name;

			if ( '/' === substr( $name, -1 ) ) {
				if ( ! is_dir( $target ) ) {
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					@mkdir( $target, 0755, true );
				}

				continue;
			}

			$parent = dirname( $target );

			if ( ! is_dir( $parent ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@mkdir( $parent, 0755, true );
			}

			if ( $this->zip->extractTo( $directory, $name ) ) {
				++$done;
			}
		}

		return $done;
	}

	/**
	 * {@inheritDoc}
	 */
	public function close(): void {
		if ( null !== $this->zip ) {
			$this->zip->close();
			$this->zip = null;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): string {
		return $this->error;
	}

	/**
	 * Reject entries that would escape the destination directory.
	 *
	 * @param string $name Entry name.
	 * @return bool
	 */
	public static function is_safe_entry( string $name ): bool {
		$name = str_replace( '\\', '/', $name );

		if ( '' === $name || '/' === $name[0] ) {
			return false;
		}

		if ( false !== strpos( $name, "\0" ) ) {
			return false;
		}

		if ( preg_match( '#(^|/)\.\.(/|$)#', $name ) ) {
			return false;
		}

		return false === strpos( $name, ':' );
	}
}
