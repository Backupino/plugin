<?php
/**
 * Archive manifest.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Newline delimited JSON index of every file in an archive.
 *
 * NDJSON is used instead of a single JSON document so that the manifest can be
 * appended during scanning and re-read from a byte offset during archiving,
 * keeping memory usage constant.
 */
final class Manifest {

	/**
	 * Absolute manifest path.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Root directory that manifest paths are relative to.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path of the `.ndjson` file.
	 * @param string $root Directory the stored paths are relative to.
	 */
	public function __construct( string $file, string $root = '' ) {
		$this->file = $file;
		$this->root = rtrim( str_replace( '\\', '/', $root ), '/' );
	}

	/**
	 * Manifest path.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->file;
	}

	/**
	 * Truncate the manifest.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false !== @file_put_contents( $this->file, '' );
	}

	/**
	 * Append one entry.
	 *
	 * @param FileEntry $entry Entry.
	 * @return bool
	 */
	public function append( FileEntry $entry ): bool {
		$line = wp_json_encode( $entry->to_array() );

		if ( ! is_string( $line ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		return false !== @file_put_contents( $this->file, $line . "\n", FILE_APPEND );
	}

	/**
	 * Read up to `$limit` entries starting at a byte offset.
	 *
	 * @param int $offset Byte offset.
	 * @param int $limit  Maximum number of entries.
	 * @return array{entries: FileEntry[], offset: int}
	 */
	public function read_at( int $offset, int $limit ): array {
		$entries = array();

		if ( ! is_readable( $this->file ) || $limit <= 0 ) {
			return array(
				'entries' => $entries,
				'offset'  => $offset,
			);
		}

		clearstatcache( true, $this->file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$handle = @fopen( $this->file, 'rb' );

		if ( false === $handle ) {
			return array(
				'entries' => $entries,
				'offset'  => $offset,
			);
		}

		fseek( $handle, max( 0, $offset ) );

		while ( ! feof( $handle ) ) {
			if ( count( $entries ) >= $limit ) {
				break;
			}

			$line = fgets( $handle );

			if ( false === $line ) {
				break;
			}

			$offset += strlen( $line );
			$data    = json_decode( trim( $line ), true );

			if ( ! is_array( $data ) || ! isset( $data['p'] ) ) {
				continue;
			}

			$entries[] = new FileEntry(
				$this->root . '/' . (string) $data['p'],
				(string) $data['p'],
				isset( $data['s'] ) ? (int) $data['s'] : 0
			);
		}

		$final_offset = ftell( $handle );
		if ( false !== $final_offset && $final_offset > 0 ) {
			$offset = (int) $final_offset;
		}

		fclose( $handle );

		return array(
			'entries' => $entries,
			'offset'  => $offset,
		);
	}

	/**
	 * Number of entries in the manifest.
	 *
	 * @return int
	 */
	public function count(): int {
		if ( ! is_readable( $this->file ) ) {
			return 0;
		}

		clearstatcache( true, $this->file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$handle = @fopen( $this->file, 'rb' );

		if ( false === $handle ) {
			return 0;
		}

		$count  = 0;
		$buffer = '';

		while ( ! feof( $handle ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.WP.AlternativeFunctions.file_system_read_fread
			$chunk = fread( $handle, 1048576 );

			if ( false === $chunk || '' === $chunk ) {
				break;
			}

			$count += substr_count( $chunk, "\n" );
			$buffer = $chunk;
		}

		fclose( $handle );

		if ( '' !== $buffer && "\n" !== substr( $buffer, -1 ) ) {
			++$count;
		}

		return $count;
	}

	/**
	 * SHA-256 hash of the manifest content.
	 *
	 * @return string
	 */
	public function hash(): string {
		return is_readable( $this->file ) ? (string) hash_file( 'sha256', $this->file ) : '';
	}

	/**
	 * Root directory that manifest paths are relative to.
	 *
	 * @return string
	 */
	public function root(): string {
		return $this->root;
	}
}
