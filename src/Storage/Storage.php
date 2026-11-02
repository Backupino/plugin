<?php
/**
 * Storage contract.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Storage;

/**
 * Destination for finished packages.
 */
interface Storage {

	/**
	 * Machine readable id, used in settings.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Human readable label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Upload a local file.
	 *
	 * @param string $local_path Absolute local path.
	 * @param string $remote_key Destination key.
	 * @return bool
	 */
	public function put( string $local_path, string $remote_key ): bool;

	/**
	 * Download a file.
	 *
	 * @param string $remote_key Source key.
	 * @param string $local_path Destination path.
	 * @return bool
	 */
	public function get( string $remote_key, string $local_path ): bool;

	/**
	 * Delete a file.
	 *
	 * @param string $remote_key Key.
	 * @return bool
	 */
	public function delete( string $remote_key ): bool;

	/**
	 * Whether a key exists.
	 *
	 * @param string $remote_key Key.
	 * @return bool
	 */
	public function exists( string $remote_key ): bool;

	/**
	 * Last error message.
	 *
	 * @return string
	 */
	public function error(): string;
}
