<?php
/**
 * Archive writer contract.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Backend that produces the package archive.
 *
 * Implementations must be able to reopen an existing archive and keep adding
 * entries, which is what makes chunked, resumable builds possible.
 */
interface ArchiveWriter {

	/**
	 * Open (or create) an archive for appending.
	 *
	 * @param string $path Absolute archive path.
	 * @return bool
	 */
	public function open( string $path ): bool;

	/**
	 * Add a file from disk.
	 *
	 * @param string $absolute Absolute source path.
	 * @param string $local    Path inside the archive.
	 * @return bool
	 */
	public function add_file( string $absolute, string $local ): bool;

	/**
	 * Add a file from a string.
	 *
	 * @param string $local    Path inside the archive.
	 * @param string $contents Contents.
	 * @return bool
	 */
	public function add_from_string( string $local, string $contents ): bool;

	/**
	 * Number of entries currently in the archive.
	 *
	 * @return int
	 */
	public function count(): int;

	/**
	 * Close the archive and flush it to disk.
	 *
	 * @return bool
	 */
	public function close(): bool;

	/**
	 * Engine name, stored in the package metadata.
	 *
	 * @return string
	 */
	public function engine(): string;

	/**
	 * Last error message.
	 *
	 * @return string
	 */
	public function error(): string;
}
