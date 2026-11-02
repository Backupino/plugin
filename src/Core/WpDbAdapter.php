<?php
/**
 * `$wpdb` adapter.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Implements the {@see Db} contract on top of WordPress' `$wpdb`.
 */
final class WpDbAdapter implements Db {

	/**
	 * Global database object.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional injected database object.
	 */
	public function __construct( $wpdb = null ) {
		if ( null === $wpdb ) {
			global $wpdb;
		}

		$this->wpdb = null !== $wpdb ? $wpdb : $GLOBALS['wpdb'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function query( string $sql ): bool {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return false !== $this->wpdb->query( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function rows( string $sql ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function row( string $sql ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function var( string $sql, $fallback = null ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$value = $this->wpdb->get_var( $sql );

		return null === $value ? $fallback : $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function column( string $sql ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$values = $this->wpdb->get_col( $sql );

		return is_array( $values ) ? $values : array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape( string $value ): string {
		if ( null !== $this->wpdb && isset( $this->wpdb->dbh ) && $this->wpdb->dbh instanceof \mysqli ) {
			return mysqli_real_escape_string( $this->wpdb->dbh, $value );
		}

		return addcslashes( $value, "\0\n\r\\'\"\x1a" );
	}

	/**
	 * {@inheritDoc}
	 */
	public function prefix(): string {
		return $this->wpdb->prefix;
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert( string $table, array $data ): int {
		$table = $this->wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $this->wpdb->insert( $table, $data );

		return false === $result ? 0 : (int) $this->wpdb->insert_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( string $table, array $data, array $where ): int {
		$table = $this->wpdb->prefix . $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $this->wpdb->update( $table, $data, $where );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): string {
		return (string) $this->wpdb->last_error;
	}
}
