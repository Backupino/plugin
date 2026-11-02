<?php
/**
 * MySQL dumper.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup\Sql;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Db;
use Backupino\Jobs\Deadline;

/**
 * Resumable, dependency free MySQL dumper.
 *
 * Design notes:
 *  - `mysqldump` is rarely available on shared hosting, so everything is done
 *    through SQL that any MySQL 5.7+/MariaDB 10.2+ user can run.
 *  - Rows are read with keyset pagination (`WHERE pk > $last`) which stays fast
 *    on multi-million row tables, unlike `LIMIT offset, n`.
 *  - Every step is time boxed and fully described by the returned state array,
 *    so an interrupted dump continues exactly where it stopped.
 */
final class MySqlDumper {

	/**
	 * Database adapter.
	 *
	 * @var Db
	 */
	private $db;

	/**
	 * Output writer.
	 *
	 * @var SqlWriter
	 */
	private $writer;

	/**
	 * Options.
	 *
	 * @var array<string, mixed>
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param Db                   $db      Database adapter.
	 * @param SqlWriter            $writer  Output writer.
	 * @param array<string, mixed> $options Options: `tables`, `views`, `triggers`, `routines`.
	 */
	public function __construct( Db $db, SqlWriter $writer, array $options = array() ) {
		$this->db      = $db;
		$this->writer  = $writer;
		$this->options = array_merge(
			array(
				'tables'          => true,
				'views'           => true,
				'triggers'        => true,
				'routines'        => true,
				'max_statement'   => 262144,
				'max_rows_insert' => 250,
			),
			$options
		);
	}

	/**
	 * Fresh dump state.
	 *
	 * @return array<string, mixed>
	 */
	public static function initial_state(): array {
		return array(
			'started' => false,
			'tables'  => array(),
			'index'   => 0,
			'phase'   => 'schema',
			'cursor'  => null,
			'offset'  => 0,
			'rows'    => 0,
			'extras'  => false,
			'done'    => false,
			'count'   => 0,
		);
	}

	/**
	 * Run one time boxed slice of the dump.
	 *
	 * @param array<string, mixed> $state          Dump state.
	 * @param Deadline             $deadline       Time budget.
	 * @param int                  $rows_per_chunk Rows read per table query.
	 * @return array<string, mixed> Updated state.
	 */
	public function step( array $state, Deadline $deadline, int $rows_per_chunk = 1000 ): array {
		$state = array_merge( self::initial_state(), $state );

		if ( ! $state['started'] ) {
			$this->write_header();
			$state['tables']  = $this->discover_tables();
			$state['started'] = true;
		}

		$rows_per_chunk = max( 10, $rows_per_chunk );

		while ( ! $deadline->expired() ) {
			if ( $state['done'] ) {
				break;
			}

			if ( $state['index'] >= count( $state['tables'] ) ) {
				if ( ! $state['extras'] ) {
					$this->write_extras();
					$state['extras'] = true;
					continue;
				}

				$this->write_footer();
				$state['done'] = true;
				break;
			}

			$table = $state['tables'][ $state['index'] ];

			if ( 'schema' === $state['phase'] ) {
				$this->write_schema( $table );

				$state['phase']  = 'data';
				$state['cursor'] = null;
				$state['offset'] = 0;

				if ( 'BASE TABLE' !== $table['type'] ) {
					$state['phase'] = 'schema';
					++$state['index'];
				}

				continue;
			}

			$result = $this->write_rows( $table['name'], $state['cursor'], $state['offset'], $rows_per_chunk );

			$state['rows']  += $result['written'];
			$state['count'] += $result['written'];

			if ( $result['finished'] ) {
				$state['phase'] = 'schema';
				++$state['index'];
				$state['cursor'] = null;
				$state['offset'] = 0;
				continue;
			}

			$state['cursor'] = $result['cursor'];
			$state['offset'] = $result['offset'];
		}

		return $state;
	}

	/**
	 * Write the dump preamble.
	 *
	 * @return void
	 */
	private function write_header(): void {
		$db = $this->db->prefix();

		$this->writer->write(
			"-- Backupino database dump\n"
			. '-- Generated: ' . gmdate( 'c' ) . "\n"
			. "-- Prefix: {$db}\n\n"
			. "SET NAMES utf8mb4;\n"
			. "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
			. "SET time_zone = '+00:00';\n"
			. "SET FOREIGN_KEY_CHECKS = 0;\n"
			. "SET UNIQUE_CHECKS = 0;\n"
			. "SET sql_notes = 0;\n\n"
		);
	}

	/**
	 * Write the dump epilogue.
	 *
	 * @return void
	 */
	private function write_footer(): void {
		$this->writer->write( "\nSET UNIQUE_CHECKS = 1;\nSET FOREIGN_KEY_CHECKS = 1;\nSET sql_notes = 1;\n" );
	}

	/**
	 * List dumpable tables and views.
	 *
	 * @return array<int, array{name: string, type: string}>
	 */
	private function discover_tables(): array {
		$tables = array();

		if ( ! $this->options['tables'] ) {
			return $tables;
		}

		$rows = $this->db->rows( 'SHOW FULL TABLES' );

		foreach ( $rows as $row ) {
			$values = array_values( $row );
			$name   = isset( $values[0] ) ? (string) $values[0] : '';
			$type   = isset( $values[1] ) ? strtoupper( (string) $values[1] ) : 'BASE TABLE';

			if ( '' === $name || false !== stripos( $name, 'backupino_jobs' ) ) {
				continue;
			}

			if ( 'VIEW' === $type && ! $this->options['views'] ) {
				continue;
			}

			$tables[] = array(
				'name' => $name,
				'type' => 'VIEW' === $type ? 'VIEW' : 'BASE TABLE',
			);
		}

		return $tables;
	}

	/**
	 * Write the `DROP` + `CREATE` pair for a table or view.
	 *
	 * @param array{name: string, type: string} $table Table descriptor.
	 * @return void
	 */
	private function write_schema( array $table ): void {
		$name = $this->quote_identifier( $table['name'] );

		if ( 'VIEW' === $table['type'] ) {
			$row = $this->db->row( 'SHOW CREATE VIEW ' . $name );

			if ( null === $row ) {
				return;
			}

			$create = isset( $row['Create View'] ) ? (string) $row['Create View'] : '';

			if ( '' === $create ) {
				return;
			}

			$this->writer->write( "\n-- View structure for {$table['name']}\n" );
			$this->writer->statement( 'DROP VIEW IF EXISTS ' . $name );
			$this->writer->statement( $this->strip_definer( $create ) );

			return;
		}

		$row = $this->db->row( 'SHOW CREATE TABLE ' . $name );

		if ( null === $row ) {
			return;
		}

		$create = isset( $row['Create Table'] ) ? (string) $row['Create Table'] : '';

		if ( '' === $create ) {
			return;
		}

		// Volatile AUTO_INCREMENT counters make dumps impossible to diff.
		$create = (string) preg_replace( '/\s+AUTO_INCREMENT=\d+/', '', $create );

		$this->writer->write( "\n-- Table structure for {$table['name']}\n" );
		$this->writer->statement( 'DROP TABLE IF EXISTS ' . $name );
		$this->writer->statement( $create );
	}

	/**
	 * Write one chunk of rows.
	 *
	 * @param string      $table          Table name.
	 * @param string|null $cursor         Last primary key value.
	 * @param int         $offset         Row offset for tables without a usable key.
	 * @param int         $rows_per_chunk Rows per query.
	 * @return array{written: int, finished: bool, cursor: string|null, offset: int}
	 */
	private function write_rows( string $table, ?string $cursor, int $offset, int $rows_per_chunk ): array {
		$columns = $this->db->rows( 'SHOW COLUMNS FROM ' . $this->quote_identifier( $table ) );

		if ( array() === $columns ) {
			return array(
				'written'  => 0,
				'finished' => true,
				'cursor'   => null,
				'offset'   => 0,
			);
		}

		$fields     = array();
		$binary     = array();
		$is_numeric = array();
		$primary    = array();

		foreach ( $columns as $column ) {
			$field = isset( $column['Field'] ) ? (string) $column['Field'] : '';

			if ( '' === $field ) {
				continue;
			}

			$type                 = isset( $column['Type'] ) ? strtolower( (string) $column['Type'] ) : '';
			$fields[]             = $field;
			$binary[ $field ]     = self::is_binary_type( $type );
			$is_numeric[ $field ] = self::is_numeric_type( $type );

			if ( isset( $column['Key'] ) && 'PRI' === $column['Key'] ) {
				$primary[] = $field;
			}
		}

		if ( array() === $fields ) {
			return array(
				'written'  => 0,
				'finished' => true,
				'cursor'   => null,
				'offset'   => 0,
			);
		}

		$keyset = count( $primary ) === 1 && isset( $is_numeric[ $primary[0] ] ) && $is_numeric[ $primary[0] ];

		$select = 'SELECT ' . implode(
			',',
			array_map( array( $this, 'quote_identifier' ), $fields )
		) . ' FROM ' . $this->quote_identifier( $table );

		if ( $keyset ) {
			$key = $primary[0];

			if ( null !== $cursor && '' !== $cursor ) {
				$select .= ' WHERE ' . $this->quote_identifier( $key ) . ' > ' . $this->quote_literal( $cursor );
			}

			$select .= ' ORDER BY ' . $this->quote_identifier( $key ) . ' ASC';
		}

		$select .= ' LIMIT ' . (int) $rows_per_chunk . ( $keyset ? '' : ' OFFSET ' . (int) $offset );

		$rows = $this->db->rows( $select );

		if ( array() === $rows ) {
			return array(
				'written'  => 0,
				'finished' => true,
				'cursor'   => null,
				'offset'   => 0,
			);
		}

		$written = 0;
		$buffer  = array();
		$size    = 0;
		$is_jobs_table = false !== strpos( $table, 'backupino_jobs' );

		foreach ( $rows as $row ) {
			$values = array();

			foreach ( $fields as $field ) {
				$value = array_key_exists( $field, $row ) ? $row[ $field ] : null;
				if ( $is_jobs_table && 'status' === $field && in_array( $value, array( 'running', 'queued' ), true ) ) {
					$value = 'completed';
				}
				$values[] = $this->format_value( $value, $binary[ $field ], $is_numeric[ $field ] );
			}

			$tuple     = '(' . implode( ',', $values ) . ')';
			$tuple_len = strlen( $tuple );

			// If buffer is non-empty and adding this tuple would exceed max_statement,
			// flush the buffer first to keep every statement strictly below packet limits.
			if ( array() !== $buffer && ( $size + $tuple_len ) >= (int) $this->options['max_statement'] ) {
				$this->flush_insert( $table, $fields, $buffer );
				$written += count( $buffer );
				$buffer   = array();
				$size     = 0;
			}

			$buffer[] = $tuple;
			$size    += $tuple_len;

			if ( count( $buffer ) >= (int) $this->options['max_rows_insert'] || $size >= (int) $this->options['max_statement'] ) {
				$this->flush_insert( $table, $fields, $buffer );
				$written += count( $buffer );
				$buffer   = array();
				$size     = 0;
			}
		}

		if ( array() !== $buffer ) {
			$this->flush_insert( $table, $fields, $buffer );
			$written += count( $buffer );
		}

		$finished = count( $rows ) < $rows_per_chunk;

		$next_cursor = null;

		if ( $keyset ) {
			$last = $rows[ count( $rows ) - 1 ];

			if ( isset( $last[ $primary[0] ] ) ) {
				$next_cursor = (string) $last[ $primary[0] ];
			}
		}

		return array(
			'written'  => $written,
			'finished' => $finished,
			'cursor'   => $next_cursor,
			'offset'   => $finished ? 0 : $offset + count( $rows ),
		);
	}

	/**
	 * Emit a batched `INSERT`.
	 *
	 * @param string             $table   Table name.
	 * @param string[]           $fields  Column names.
	 * @param array<int, string> $tuples  Pre-formatted value tuples.
	 * @return void
	 */
	private function flush_insert( string $table, array $fields, array $tuples ): void {
		if ( array() === $tuples ) {
			return;
		}

		$this->writer->statement(
			'INSERT INTO ' . $this->quote_identifier( $table )
			. ' (' . implode(
				',',
				array_map( array( $this, 'quote_identifier' ), $fields )
			) . ') VALUES ' . implode( ',', $tuples )
		);
	}

	/**
	 * Dump triggers and routines.
	 *
	 * @return void
	 */
	private function write_extras(): void {
		if ( $this->options['triggers'] ) {
			$triggers = $this->db->rows( 'SHOW TRIGGERS' );

			foreach ( $triggers as $trigger ) {
				$name = isset( $trigger['Trigger'] ) ? (string) $trigger['Trigger'] : '';

				if ( '' === $name ) {
					continue;
				}

				$row = $this->db->row( 'SHOW CREATE TRIGGER ' . $this->quote_identifier( $name ) );

				if ( null === $row ) {
					continue;
				}

				$create = isset( $row['SQL Original Statement'] ) ? (string) $row['SQL Original Statement'] : '';

				if ( '' === $create ) {
					continue;
				}

				$this->writer->write( "\n-- Trigger {$name}\n" );
				$this->writer->statement( 'DROP TRIGGER IF EXISTS ' . $this->quote_identifier( $name ) );
				$this->writer->statement( $this->strip_definer( $create ) );
			}
		}

		if ( ! $this->options['routines'] ) {
			return;
		}

		$routines = $this->db->rows(
			'SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()'
		);

		foreach ( $routines as $routine ) {
			$name = isset( $routine['ROUTINE_NAME'] ) ? (string) $routine['ROUTINE_NAME'] : '';
			$type = isset( $routine['ROUTINE_TYPE'] ) ? strtoupper( (string) $routine['ROUTINE_TYPE'] ) : '';

			if ( '' === $name || ! in_array( $type, array( 'PROCEDURE', 'FUNCTION' ), true ) ) {
				continue;
			}

			$row = $this->db->row( 'SHOW CREATE ' . $type . ' ' . $this->quote_identifier( $name ) );

			if ( null === $row ) {
				continue;
			}

			$create = isset( $row[ 'Create ' . ucfirst( strtolower( $type ) ) ] )
				? (string) $row[ 'Create ' . ucfirst( strtolower( $type ) ) ]
				: '';

			if ( '' === $create ) {
				continue;
			}

			$this->writer->write( "\n-- {$type} {$name}\n" );
			$this->writer->statement( 'DROP ' . $type . ' IF EXISTS ' . $this->quote_identifier( $name ) );
			$this->writer->statement( $this->strip_definer( $create ) );
		}
	}

	/**
	 * Format a single SQL literal.
	 *
	 * @param mixed $value   Raw value.
	 * @param bool  $binary  Whether the column is binary.
	 * @param bool  $is_numeric Whether the column is numeric.
	 * @return string
	 */
	private function format_value( $value, bool $binary, bool $is_numeric ): string {
		if ( null === $value ) {
			return 'NULL';
		}

		$value = (string) $value;

		if ( $binary ) {
			return '0x' . bin2hex( $value );
		}

		if ( $is_numeric && is_numeric( $value ) ) {
			return $value;
		}

		return "'" . $this->db->escape( $value ) . "'";
	}

	/**
	 * Quote a string literal for a WHERE clause.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function quote_literal( string $value ): string {
		if ( is_numeric( $value ) ) {
			return $value;
		}

		return "'" . $this->db->escape( $value ) . "'";
	}

	/**
	 * Quote an identifier with backticks.
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 */
	private function quote_identifier( string $identifier ): string {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Remove `DEFINER` clauses that break imports on other servers.
	 *
	 * @param string $sql Statement.
	 * @return string
	 */
	private function strip_definer( string $sql ): string {
		$sql = (string) preg_replace( '/DEFINER=`[^`]*`@`[^`]*`\s*/i', '', $sql );

		return (string) preg_replace( '/DEFINER=[^\s]+\s+/i', '', $sql );
	}

	/**
	 * Whether a column type stores binary data.
	 *
	 * @param string $type Column type.
	 * @return bool
	 */
	public static function is_binary_type( string $type ): bool {
		return 1 === preg_match( '/(binary|blob|bit|geometry)/i', $type );
	}

	/**
	 * Whether a column type is numeric.
	 *
	 * @param string $type Column type.
	 * @return bool
	 */
	public static function is_numeric_type( string $type ): bool {
		return 1 === preg_match( '/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|year)/i', $type );
	}
}
