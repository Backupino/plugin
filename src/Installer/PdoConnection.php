<?php
/**
 * PDO connection fallback.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.RestrictedClasses -- Offline standalone installer without WordPress core.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Offline standalone installer exceptions.

/**
 * {@see Connection} implemented on top of PDO, used when mysqli is disabled.
 */
final class PdoConnection implements Connection {

	/**
	 * Handle.
	 *
	 * @var \PDO
	 */
	private $pdo;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Build PDO MySQL DSN string from configuration array.
	 *
	 * Handles WordPress DB_HOST variations including host:port, host:/path/to/socket,
	 * IPv6 [::1]:port, and explicit socket/port options.
	 *
	 * @param array<string, mixed> $config Database configuration.
	 * @return string DSN string.
	 */
	public static function build_dsn( array $config ): string {
		$raw_host = (string) ( $config['host'] ?? 'localhost' );
		$port     = isset( $config['port'] ) ? (int) $config['port'] : 0;
		$db       = (string) ( $config['name'] ?? '' );
		$socket   = (string) ( $config['socket'] ?? '' );

		$host = $raw_host;
		if ( '' === $socket && false !== strpos( $host, ':/' ) ) {
			$parts  = explode( ':/', $host, 2 );
			$host   = $parts[0];
			$socket = '/' . $parts[1];
		} elseif ( preg_match( '/^\[([a-fA-F0-9:]+)\]:(\d+)$/', $host, $m ) ) {
			$host = $m[1];
			if ( 0 === $port ) {
				$port = (int) $m[2];
			}
		} elseif ( false !== strpos( $host, ':' ) ) {
			$parts = explode( ':', $host, 2 );
			$host  = $parts[0];
			if ( 0 === $port && is_numeric( $parts[1] ) ) {
				$port = (int) $parts[1];
			}
		}

		if ( '' === $host ) {
			$host = 'localhost';
		}

		$dsn = 'mysql:charset=utf8mb4';

		if ( '' !== $socket ) {
			$dsn .= ';unix_socket=' . $socket;
		} else {
			$dsn .= ';host=' . $host . ( $port > 0 ? ';port=' . $port : '' );
		}

		if ( '' !== $db ) {
			$dsn .= ';dbname=' . $db;
		}

		return $dsn;
	}

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $config Connection configuration.
	 * @return void
	 *
	 * @throws \RuntimeException When the connection fails.
	 */
	public function __construct( array $config ) {
		if ( ! extension_loaded( 'pdo_mysql' ) ) {
			throw new \RuntimeException( 'pdo_mysql is not available' );
		}

		$dsn = self::build_dsn( $config );

		try {
			$this->pdo = new \PDO(
				$dsn,
				(string) ( $config['user'] ?? '' ),
				(string) ( $config['password'] ?? '' ),
				array(
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
				)
			);

			@$this->pdo->exec( "SET sql_mode = ''" );
			@$this->pdo->exec( 'SET foreign_key_checks = 0' );
			@$this->pdo->exec( 'SET unique_checks = 0' );
			@$this->pdo->exec( 'SET net_buffer_length = 1048576' );

			$stmt = @$this->pdo->query( 'SELECT @@session.max_allowed_packet' );
			$cur  = $stmt ? (int) $stmt->fetchColumn() : 0;
			if ( $cur > 0 && $cur < 16777216 ) {
				if ( false !== @$this->pdo->exec( 'SET GLOBAL max_allowed_packet = 1073741824' ) ) {
					$this->pdo = new \PDO(
						$dsn,
						(string) ( $config['user'] ?? '' ),
						(string) ( $config['password'] ?? '' ),
						array(
							\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
						)
					);
					@$this->pdo->exec( "SET sql_mode = ''" );
					@$this->pdo->exec( 'SET foreign_key_checks = 0' );
					@$this->pdo->exec( 'SET unique_checks = 0' );
				}
			}
		} catch ( \PDOException $e ) {
			$this->error = $e->getMessage();

			throw new \RuntimeException( $this->error );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function query( string $sql ): bool {
		$result = $this->pdo->query( $sql );

		if ( false === $result ) {
			$this->error = (string) $this->pdo->errorInfo()[2];

			return false;
		}

		$result->closeCursor();

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( string $sql, array $params = array() ): bool {
		$stmt = $this->pdo->prepare( $sql );
		if ( false === $stmt ) {
			$this->error = (string) ( $this->pdo->errorInfo()[2] ?? '' );
			return false;
		}

		$success = $stmt->execute( array_values( $params ) );
		if ( ! $success ) {
			$this->error = (string) ( $stmt->errorInfo()[2] ?? $this->pdo->errorInfo()[2] ?? '' );
		}

		$stmt->closeCursor();

		return $success;
	}

	/**
	 * {@inheritDoc}
	 */
	public function rows( string $sql ): array {
		$result = $this->pdo->query( $sql );

		if ( false === $result ) {
			$this->error = (string) $this->pdo->errorInfo()[2];

			return array();
		}

		$rows = $result->fetchAll( \PDO::FETCH_ASSOC );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch_all( string $sql, array $params = array() ): array {
		$stmt = $this->pdo->prepare( $sql );
		if ( false === $stmt ) {
			$this->error = (string) ( $this->pdo->errorInfo()[2] ?? '' );
			return array();
		}

		$success = $stmt->execute( array_values( $params ) );
		if ( ! $success ) {
			$this->error = (string) ( $stmt->errorInfo()[2] ?? $this->pdo->errorInfo()[2] ?? '' );
			$stmt->closeCursor();
			return array();
		}

		$rows = $stmt->fetchAll( \PDO::FETCH_ASSOC );
		$stmt->closeCursor();

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape( string $value ): string {
		return trim( (string) $this->pdo->quote( $value ), "'" );
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): string {
		return $this->error;
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		$version = $this->pdo->getAttribute( \PDO::ATTR_SERVER_VERSION );

		return is_string( $version ) ? $version : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function select( string $name ): bool {
		return $this->query( 'USE `' . str_replace( '`', '``', $name ) . '`' );
	}
}
