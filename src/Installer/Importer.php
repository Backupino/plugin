<?php
/**
 * SQL importer.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * Executes the dump statement by statement, in time boxed slices.
 */
final class Importer {

	/**
	 * Connection.
	 *
	 * @var Connection
	 */
	private $connection;

	/**
	 * Splitter.
	 *
	 * @var SqlSplitter
	 */
	private $splitter;

	/**
	 * Total file size.
	 *
	 * @var int
	 */
	private $size;

	/**
	 * Constructor.
	 *
	 * @param Connection  $connection Database connection.
	 * @param SqlSplitter $splitter   Statement splitter.
	 */
	public function __construct( Connection $connection, SqlSplitter $splitter ) {
		$this->connection = $connection;
		$this->splitter   = $splitter;
		$this->size       = $splitter->size();
	}

	/**
	 * Total dump size in bytes.
	 *
	 * @return int
	 */
	public function size(): int {
		return $this->size;
	}

	/**
	 * Execute a slice of the dump.
	 *
	 * @param int   $offset     Byte offset (by reference).
	 * @param int   $max_bytes  Maximum bytes to consume.
	 * @param float $seconds    Time budget.
	 * @return array{executed: int, done: bool, error: string}
	 */
	public function step( int &$offset, int $max_bytes = 262144, float $seconds = 8.0 ): array {
		$started  = microtime( true );
		$executed = 0;
		$done     = false;

		while ( ( microtime( true ) - $started ) < $seconds ) {
			$batch = $this->splitter->read( $offset, $max_bytes );

			if ( array() === $batch['statements'] ) {
				$done = $batch['done'];

				if ( $batch['offset'] === $offset ) {
					break;
				}

				$offset = $batch['offset'];
				continue;
			}

			foreach ( $batch['statements'] as $statement ) {
				if ( ! $this->connection->query( $statement ) ) {
					$err = $this->connection->error();
					if ( false !== stripos( $err, 'max_allowed_packet' ) || false !== stripos( $err, 'packet bigger' ) ) {
						$sub_statements = self::split_insert_statement( $statement );
						if ( count( $sub_statements ) > 1 ) {
							$all_sub_ok = true;
							foreach ( $sub_statements as $sub_sql ) {
								if ( ! $this->connection->query( $sub_sql ) ) {
									$all_sub_ok = false;
									return array(
										'executed' => $executed,
										'done'     => false,
										'offset'   => $offset,
										'error'    => self::describe( $sub_sql, $this->connection->error() ),
									);
								}
								++$executed;
							}
							if ( $all_sub_ok ) {
								continue;
							}
						}
					}

					// Handle Windows/NTFS, orphaned tablespaces, or foreign key constraint collisions (errno 121) on CREATE TABLE
					if ( ( false !== stripos( $err, 'already exists' ) || false !== stripos( $err, 'tablespace' ) || false !== stripos( $err, 'errno: 121' ) || false !== stripos( $err, 'duplicate key' ) )
						&& preg_match( '/^\s*CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+[`"]?([^`"\s]+)[`"]?/i', $statement, $tbl_match ) ) {
						$tbl_name  = $tbl_match[1];
						$escaped   = '`' . str_replace( '`', '``', $tbl_name ) . '`';
						$retried   = false;
						$clean_sql = (string) preg_replace( '/CONSTRAINT\s+[`"]?[^`"\s]+[`"]?\s+FOREIGN\s+KEY/i', 'FOREIGN KEY', $statement );

						for ( $attempt = 1; $attempt <= 3; ++$attempt ) {
							usleep( 150000 * $attempt );
							@$this->connection->query( "ALTER TABLE {$escaped} DISCARD TABLESPACE" );
							@$this->connection->query( "DROP TABLE IF EXISTS {$escaped}" );
							usleep( 50000 );
							if ( $this->connection->query( $clean_sql ) ) {
								$retried = true;
								break;
							}
						}
						if ( $retried ) {
							++$executed;
							continue;
						}
					}

					// Handle duplicate key on INSERT (resumed or cancelled import recovery)
					if ( false !== stripos( $err, 'duplicate entry' ) && preg_match( '/^\s*INSERT\s+INTO\s+/i', $statement ) ) {
						$ignore_sql = (string) preg_replace( '/^\s*INSERT\s+INTO\s+/i', 'INSERT IGNORE INTO ', $statement );
						if ( $this->connection->query( $ignore_sql ) ) {
							++$executed;
							continue;
						}
					}

					return array(
						'executed' => $executed,
						'done'     => false,
						'offset'   => $offset,
						'error'    => self::describe( $statement, $this->connection->error() ),
					);
				}

				++$executed;
			}

			$offset = $batch['offset'];

			if ( $batch['done'] ) {
				$done = true;
				break;
			}
		}

		return array(
			'executed' => $executed,
			'done'     => $done,
			'offset'   => $offset,
			'error'    => '',
		);
	}

	/**
	 * Build a helpful error message.
	 *
	 * @param string $statement Failing statement.
	 * @param string $error     Driver error.
	 * @return string
	 */
	private static function describe( string $statement, string $error ): string {
		$preview = (string) preg_replace( '/\s+/', ' ', $statement );

		return $error . ' :: ' . substr( $preview, 0, 200 );
	}

	/**
	 * Split a multi-row INSERT into smaller statements if it exceeds packet size.
	 *
	 * @param string $sql Statement.
	 * @return string[]
	 */
	public static function split_insert_statement( string $sql ): array {
		if ( ! preg_match( '/^(\s*INSERT\s+INTO\s+.*?\s+VALUES\s*)(.*)$/is', $sql, $m ) ) {
			return array( $sql );
		}

		$prefix     = $m[1];
		$values_str = trim( $m[2] );
		if ( ';' === substr( $values_str, -1 ) ) {
			$values_str = substr( $values_str, 0, -1 );
		}

		$tuples      = array();
		$len         = strlen( $values_str );
		$in_string   = false;
		$string_char = '';
		$escaped     = false;
		$depth       = 0;
		$start       = -1;

		for ( $i = 0; $i < $len; $i++ ) {
			$c = $values_str[ $i ];

			if ( $in_string ) {
				if ( $escaped ) {
					$escaped = false;
				} elseif ( '\\' === $c ) {
					$escaped = true;
				} elseif ( $c === $string_char ) {
					$in_string = false;
				}
				continue;
			}

			if ( "'" === $c || '"' === $c ) {
				$in_string   = true;
				$string_char = $c;
				continue;
			}

			if ( '(' === $c ) {
				if ( 0 === $depth ) {
					$start = $i;
				}
				++$depth;
			} elseif ( ')' === $c ) {
				--$depth;
				if ( 0 === $depth && -1 !== $start ) {
					$tuples[] = substr( $values_str, $start, $i - $start + 1 );
					$start    = -1;
				}
			}
		}

		if ( count( $tuples ) <= 1 ) {
			return array( $sql );
		}

		$statements = array();
		$chunk      = array();
		$chunk_size = 0;

		foreach ( $tuples as $t ) {
			$t_len = strlen( $t );
			if ( array() !== $chunk && ( ( $chunk_size + $t_len ) > 65536 || count( $chunk ) >= 10 ) ) {
				$statements[] = $prefix . implode( ',', $chunk ) . ';';
				$chunk        = array();
				$chunk_size   = 0;
			}
			$chunk[]     = $t;
			$chunk_size += $t_len;
		}

		if ( array() !== $chunk ) {
			$statements[] = $prefix . implode( ',', $chunk ) . ';';
		}

		return $statements;
	}
}
