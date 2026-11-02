<?php
/**
 * MySQLi connection.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * {@see Connection} implemented on top of mysqli.
 */
final class MysqliConnection implements Connection {

	/**
	 * Link.
	 *
	 * @var \mysqli
	 */
	private $link;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $config Connection configuration.
	 * @return void
	 *
	 * @throws \RuntimeException When the connection fails.
	 */
	public function __construct( array $config ) {
		if ( ! extension_loaded( 'mysqli' ) ) {
			throw new \RuntimeException( 'mysqli is not available' );
		}

		// PHP 8.1 enables exception mode by default; keep failures as values.
		mysqli_report( MYSQLI_REPORT_OFF );

		$host   = (string) ( $config['host'] ?? 'localhost' );
		$user   = (string) ( $config['user'] ?? '' );
		$pass   = (string) ( $config['password'] ?? '' );
		$port   = isset( $config['port'] ) ? (int) $config['port'] : 0;
		$socket = (string) ( $config['socket'] ?? '' );
		$db     = (string) ( $config['name'] ?? '' );

		$link = @mysqli_connect( $host, $user, $pass, '' === $db ? null : $db, $port, '' === $socket ? null : $socket );

		if ( ! $link ) {
			$this->error = (string) mysqli_connect_error();

			throw new \RuntimeException( $this->error );
		}

		$this->link = $link;

		@mysqli_set_charset( $this->link, 'utf8mb4' );
		@mysqli_query( $this->link, "SET sql_mode = ''" );
		@mysqli_query( $this->link, 'SET foreign_key_checks = 0' );
		@mysqli_query( $this->link, 'SET unique_checks = 0' );
		@mysqli_query( $this->link, 'SET net_buffer_length = 1048576' );

		// Elevate global packet limit if possible (e.g. localhost/XAMPP) and reconnect to inherit it.
		$packet_res = @mysqli_query( $this->link, 'SELECT @@session.max_allowed_packet' );
		$cur_packet = ( $packet_res instanceof \mysqli_result ) ? (int) ( ( mysqli_fetch_row( $packet_res )[0] ?? 0 ) ) : 0;
		if ( $cur_packet > 0 && $cur_packet < 16777216 ) {
			if ( @mysqli_query( $this->link, 'SET GLOBAL max_allowed_packet = 1073741824' ) ) {
				@mysqli_close( $this->link );
				$reconnected = @mysqli_connect( $host, $user, $pass, '' === $db ? null : $db, $port, '' === $socket ? null : $socket );
				if ( $reconnected ) {
					$this->link = $reconnected;
					@mysqli_set_charset( $this->link, 'utf8mb4' );
					@mysqli_query( $this->link, "SET sql_mode = ''" );
					@mysqli_query( $this->link, 'SET foreign_key_checks = 0' );
					@mysqli_query( $this->link, 'SET unique_checks = 0' );
				}
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function query( string $sql ): bool {
		$result = @mysqli_query( $this->link, $sql );

		if ( false === $result ) {
			$this->error = (string) mysqli_error( $this->link );

			return false;
		}

		if ( $result instanceof \mysqli_result ) {
			mysqli_free_result( $result );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function rows( string $sql ): array {
		$result = @mysqli_query( $this->link, $sql );

		if ( false === $result || ! $result instanceof \mysqli_result ) {
			$this->error = (string) mysqli_error( $this->link );

			return array();
		}

		$rows = array();

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- canonical fetch loop.
		while ( $row = mysqli_fetch_assoc( $result ) ) {
			$rows[] = $row;
		}

		mysqli_free_result( $result );

		return $rows;
	}

	/**
	 * {@inheritDoc}
	 */
	public function escape( string $value ): string {
		return (string) mysqli_real_escape_string( $this->link, $value );
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
		return (string) mysqli_get_server_info( $this->link );
	}

	/**
	 * {@inheritDoc}
	 */
	public function select( string $name ): bool {
		$selected = @mysqli_select_db( $this->link, $name );

		if ( ! $selected ) {
			$this->error = (string) mysqli_error( $this->link );
		}

		return (bool) $selected;
	}

	/**
	 * Close the link.
	 */
	public function __destruct() {
		if ( $this->link instanceof \mysqli ) {
			@mysqli_close( $this->link );
		}
	}
}
