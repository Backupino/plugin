<?php
/**
 * Archive reader contract.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Reads an existing package archive.
 *
 * Both the plugin (for verification) and the standalone installer use this
 * contract, which keeps the extraction logic identical in both places.
 */
interface ArchiveReader {

	/**
	 * Open an archive.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public function open( string $path ): bool;

	/**
	 * Every entry name in the archive.
	 *
	 * @return string[]
	 */
	public function entries(): array;

	/**
	 * Read a single entry.
	 *
	 * @param string $name Entry name.
	 * @return string|null
	 */
	public function read( string $name ): ?string;

	/**
	 * Extract a slice of entries into a directory.
	 *
	 * @param string $directory Destination directory.
	 * @param int    $offset    Index of the first entry of this slice.
	 * @param int    $limit     Maximum entries to extract.
	 * @param int    $total     Set to the total number of entries.
	 * @return int Number of extracted entries.
	 */
	public function extract( string $directory, int $offset, int $limit, int &$total = 0 ): int;

	/**
	 * Close the archive.
	 *
	 * @return void
	 */
	public function close(): void;

	/**
	 * Last error.
	 *
	 * @return string
	 */
	public function error(): string;
}
