<?php
/**
 * Database abstraction used by the plugin.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Narrow database contract.
 *
 * Everything that touches SQL goes through this interface so that dumpers and
 * repositories can be exercised with an in-memory fake inside the test suite.
 */
interface Db {

	/**
	 * Run a statement that returns no rows.
	 *
	 * @param string $sql SQL statement.
	 * @return bool
	 */
	public function query( string $sql ): bool;

	/**
	 * Fetch all rows of a query as associative arrays.
	 *
	 * @param string $sql SQL statement.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( string $sql ): array;

	/**
	 * Fetch a single row.
	 *
	 * @param string $sql SQL statement.
	 * @return array<string, mixed>|null
	 */
	public function row( string $sql ): ?array;

	/**
	 * Fetch a single scalar value.
	 *
	 * @param string $sql     SQL statement.
	 * @param mixed  $fallback Value returned when the query yields nothing.
	 * @return mixed
	 */
	public function var( string $sql, $fallback = null );

	/**
	 * Fetch the first column of every row.
	 *
	 * @param string $sql SQL statement.
	 * @return array<int, mixed>
	 */
	public function column( string $sql ): array;

	/**
	 * Escape a value for inline use in an SQL statement.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function escape( string $value ): string;

	/**
	 * Table prefix of the current site.
	 *
	 * @return string
	 */
	public function prefix(): string;

	/**
	 * Insert a row and return the insert id.
	 *
	 * @param string               $table Table name (unprefixed).
	 * @param array<string, mixed> $data  Column values.
	 * @return int
	 */
	public function insert( string $table, array $data ): int;

	/**
	 * Update rows.
	 *
	 * @param string               $table Table name (unprefixed).
	 * @param array<string, mixed> $data  Column values.
	 * @param array<string, mixed> $where Equality conditions.
	 * @return int Affected rows.
	 */
	public function update( string $table, array $data, array $where ): int;

	/**
	 * Prepare an SQL query for safe execution.
	 *
	 * @param string $query Query statement with %d, %s, %f placeholders.
	 * @param mixed  ...$args Values to bind.
	 * @return string Prepared SQL statement.
	 */
	public function prepare( string $query, ...$args ): string;

	/**
	 * Last error reported by the driver.
	 *
	 * @return string
	 */
	public function error(): string;
}
