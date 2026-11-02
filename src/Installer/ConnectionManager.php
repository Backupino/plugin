<?php
/**
 * Connection factory.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Standalone offline installer exceptions.

/**
 * Opens a database connection, creating the database when the user may.
 */
final class ConnectionManager {

	/**
	 * Connect and select the database.
	 *
	 * @param array<string, mixed> $config  Connection configuration.
	 * @param bool                 $select  Whether the database must be selected.
	 * @return Connection
	 *
	 * @throws \RuntimeException On failure.
	 */
	public static function connect( array $config, bool $select = true ): Connection {
		$errors = array();

		foreach ( array( 'mysqli', 'pdo' ) as $driver ) {
			try {
				$conn_config = $config;
				if ( ! $select ) {
					unset( $conn_config['name'] );
				}

				/** @var Connection $connection */
				$connection = 'mysqli' === $driver
					? new MysqliConnection( $conn_config )
					: new PdoConnection( $conn_config );

				if ( ! $select ) {
					return $connection;
				}

				if ( $connection->select( (string) ( $config['name'] ?? '' ) ) ) {
					return $connection;
				}

				$errors[] = $connection->error();
			} catch ( \RuntimeException $e ) {
				$errors[] = $e->getMessage();
			}
		}

		throw new \RuntimeException( implode( ' / ', array_filter( $errors ) ) );
	}

	/**
	 * Ensure a database exists, creating it when permissions allow.
	 *
	 * @param array<string, mixed> $config Configuration.
	 * @return Connection
	 *
	 * @throws \RuntimeException On failure.
	 */
	public static function ensure( array $config ): Connection {
		$name = (string) ( $config['name'] ?? '' );

		try {
			return self::connect( $config, true );
		} catch ( \RuntimeException $e ) {
			$server = self::connect( $config, false );

			$created = $server->query(
				'CREATE DATABASE IF NOT EXISTS `' . str_replace( '`', '``', $name ) . '`'
				. ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
			);

			if ( ! $created ) {
				throw new \RuntimeException(
					sprintf(
						/* translators: %s: driver error. */
						__( 'The database could not be created: %s', 'backupino' ),
						$server->error()
					)
				);
			}

			return self::connect( $config, true );
		}
	}
}
