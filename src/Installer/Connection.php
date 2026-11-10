<?php
/**
 * Installer database connection contract.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * Minimal database surface used while rebuilding the site.
 */
interface Connection {

	/**
	 * Run a statement.
	 *
	 * @param string $sql SQL.
	 * @return bool
	 */
	public function query( string $sql ): bool;

	/**
	 * Execute a prepared statement with parameters.
	 *
	 * @param string $sql    SQL with ? placeholders.
	 * @param array  $params Parameter values.
	 * @return bool
	 */
	public function execute( string $sql, array $params = array() ): bool;

	/**
	 * Fetch rows as associative arrays.
	 *
	 * @param string $sql SQL.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( string $sql ): array;

	/**
	 * Fetch rows using a prepared statement with parameters.
	 *
	 * @param string $sql    SQL with ? placeholders.
	 * @param array  $params Parameter values.
	 * @return array<int, array<string, mixed>>
	 */
	public function fetch_all( string $sql, array $params = array() ): array;

	/**
	 * Escape a value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public function escape( string $value ): string;

	/**
	 * Last error.
	 *
	 * @return string
	 */
	public function error(): string;

	/**
	 * Server version string.
	 *
	 * @return string
	 */
	public function version(): string;

	/**
	 * Select a database.
	 *
	 * @param string $name Database name.
	 * @return bool
	 */
	public function select( string $name ): bool;
}
