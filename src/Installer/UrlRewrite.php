<?php
/**
 * Chunked URL rewrite.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

use Backupino\Replace\Replacer;

/**
 * Rewrites the old site URL to the new one across every table.
 *
 * Rows are read with keyset pagination and written back one by one, so the
 * process is resumable and never loads an entire table into memory.
 */
final class UrlRewrite {

	/**
	 * Connection.
	 *
	 * @var Connection
	 */
	private $connection;

	/**
	 * Replacer.
	 *
	 * @var Replacer
	 */
	private $replacer;

	/**
	 * Text column type pattern.
	 */
	private const TEXT_TYPES = '/char|text|json|enum|set|tinytext|mediumtext|longtext/i';

	/**
	 * Constructor.
	 *
	 * @param Connection $connection Database connection.
	 * @param Replacer   $replacer   Search and replace engine.
	 */
	public function __construct( Connection $connection, Replacer $replacer ) {
		$this->connection = $connection;
		$this->replacer   = $replacer;
	}

	/**
	 * Fresh state.
	 *
	 * @return array<string, mixed>
	 */
	public static function initial_state(): array {
		return array(
			'tables'  => array(),
			'index'   => 0,
			'columns' => array(),
			'primary' => '',
			'cursor'  => null,
			'offset'  => 0,
			'rows'    => 0,
			'changes' => 0,
			'done'    => false,
		);
	}

	/**
	 * Run a slice of the rewrite.
	 *
	 * @param array<string, mixed> $state   State.
	 * @param int                  $rows    Rows per query.
	 * @param float                $seconds Time budget.
	 * @return array<string, mixed>
	 */
	public function step( array $state, int $rows = 200, float $seconds = 6.0 ): array {
		$state = array_merge( self::initial_state(), $state );
		$start = microtime( true );

		if ( array() === $state['tables'] ) {
			$state['tables'] = $this->tables();
		}

		while ( ! $state['done'] && ( microtime( true ) - $start ) < $seconds ) {
			if ( $state['index'] >= count( $state['tables'] ) ) {
				$state['done'] = true;
				break;
			}

			$table = (string) $state['tables'][ $state['index'] ];

			if ( array() === $state['columns'] ) {
				$columns = $this->text_columns( $table );

				if ( array() === $columns || array() === $columns['columns'] ) {
					++$state['index'];
					$state['columns'] = array();
					$state['primary'] = '';
					$state['cursor']  = null;
					$state['offset']  = 0;
					continue;
				}

				$state['columns'] = $columns['columns'];
				$state['primary'] = $columns['primary'];
				$state['cursor']  = null;
				$state['offset']  = 0;
			}

			$remaining = max( 0.5, $seconds - ( microtime( true ) - $start ) );

			try {
				$result = $this->rewrite_table(
					$table,
					(array) $state['columns'],
					(string) $state['primary'],
					$state['cursor'],
					(int) $state['offset'],
					$rows,
					$remaining
				);
			} catch ( \Throwable $e ) {
				// Skip table on error and proceed with remaining tables
				++$state['index'];
				$state['columns'] = array();
				$state['primary'] = '';
				$state['cursor']  = null;
				$state['offset']  = 0;
				continue;
			}

			$state['rows']    += $result['rows'];
			$state['changes'] += $result['changes'];

			if ( $result['finished'] ) {
				++$state['index'];
				$state['columns'] = array();
				$state['primary'] = '';
				$state['cursor']  = null;
				$state['offset']  = 0;
				continue;
			}

			$state['cursor'] = $result['cursor'];
			$state['offset'] = $result['offset'];
		}

		return $state;
	}

	/**
	 * Tables of the current database.
	 *
	 * @return string[]
	 */
	public function tables(): array {
		$tables = array();

		foreach ( $this->connection->rows( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" ) as $row ) {
			$values = array_values( $row );

			if ( isset( $values[0] ) ) {
				$tables[] = (string) $values[0];
			}
		}

		if ( array() === $tables ) {
			foreach ( $this->connection->rows( 'SHOW TABLES' ) as $row ) {
				$values = array_values( $row );

				if ( isset( $values[0] ) ) {
					$tables[] = (string) $values[0];
				}
			}
		}

		return $tables;
	}

	/**
	 * Text columns and the primary key of a table.
	 *
	 * @param string $table Table name.
	 * @return array{columns: string[], primary: string}
	 */
	private function text_columns( string $table ): array {
		$columns = array();
		$primary = array();

		foreach ( $this->connection->rows( 'SHOW COLUMNS FROM ' . self::quote( $table ) ) as $column ) {
			$field = isset( $column['Field'] ) ? (string) $column['Field'] : '';
			$type  = isset( $column['Type'] ) ? (string) $column['Type'] : '';

			if ( '' === $field ) {
				continue;
			}

			if ( isset( $column['Key'] ) && 'PRI' === $column['Key'] ) {
				$primary[] = $field;
			}

			if ( preg_match( self::TEXT_TYPES, $type ) && false === stripos( $type, 'blob' ) ) {
				$columns[] = $field;
			}
		}

		if ( array() === $columns ) {
			return array(
				'columns' => array(),
				'primary' => '',
			);
		}

		return array(
			'columns' => $columns,
			'primary' => count( $primary ) === 1 ? $primary[0] : '',
		);
	}

	/**
	 * Rewrite a batch of rows.
	 *
	 * @param string      $table   Table name.
	 * @param string[]    $columns Text columns.
	 * @param string      $primary Primary key column, empty when unusable.
	 * @param string|null $cursor  Last primary key value.
	 * @param int         $offset  Row offset when there is no primary key.
	 * @param int         $rows    Rows per query.
	 * @param float       $timeout Max seconds allowed for this batch.
	 * @return array{rows: int, changes: int, finished: bool, cursor: string|null, offset: int}
	 */
	private function rewrite_table( string $table, array $columns, string $primary, ?string $cursor, int $offset, int $rows, float $timeout = 6.0 ): array {
		if ( array() === $columns ) {
			return array(
				'rows'     => 0,
				'changes'  => 0,
				'finished' => true,
				'cursor'   => null,
				'offset'   => 0,
			);
		}

		$select = 'SELECT ' . ( '' !== $primary ? self::quote( $primary ) . ', ' : '' )
			. implode( ', ', array_map( array( self::class, 'quote' ), $columns ) )
			. ' FROM ' . self::quote( $table );

		if ( '' !== $primary ) {
			if ( null !== $cursor && '' !== $cursor ) {
				$select .= ' WHERE ' . self::quote( $primary ) . ' > ' . $this->quoted( $cursor );
			}

			$select .= ' ORDER BY ' . self::quote( $primary ) . ' ASC';
		}

		$select .= ' LIMIT ' . (int) $rows . ( '' !== $primary ? '' : ' OFFSET ' . (int) $offset );

		$result = $this->connection->rows( $select );

		if ( array() === $result ) {
			return array(
				'rows'     => 0,
				'changes'  => 0,
				'finished' => true,
				'cursor'   => null,
				'offset'   => 0,
			);
		}

		$changes         = 0;
		$processed_count = 0;
		$start_batch     = microtime( true );
		$last_key        = $cursor;

		foreach ( $result as $row ) {
			if ( ( microtime( true ) - $start_batch ) >= $timeout ) {
				break;
			}

			$updates = array();

			foreach ( $columns as $column ) {
				$value = isset( $row[ $column ] ) ? (string) $row[ $column ] : '';
				$new   = $this->replacer->text( $value );

				if ( $new !== $value ) {
					$updates[] = self::quote( $column ) . " = '" . $this->connection->escape( $new ) . "'";
					++$changes;
				}
			}

			if ( array() !== $updates && '' !== $primary && isset( $row[ $primary ] ) ) {
				$this->connection->query(
					'UPDATE ' . self::quote( $table ) . ' SET ' . implode( ', ', $updates )
					. ' WHERE ' . self::quote( $primary ) . ' = ' . $this->quoted( (string) $row[ $primary ] )
				);
			}

			++$processed_count;
			if ( '' !== $primary && isset( $row[ $primary ] ) ) {
				$last_key = (string) $row[ $primary ];
			}
		}

		$finished = ( $processed_count >= count( $result ) ) && ( count( $result ) < $rows );
		$next     = ( '' !== $primary ) ? $last_key : null;

		return array(
			'rows'     => $processed_count,
			'changes'  => $changes,
			'finished' => $finished,
			'cursor'   => $next,
			'offset'   => $finished ? 0 : $offset + $processed_count,
		);
	}

	/**
	 * Quote a value for a WHERE clause.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function quoted( string $value ): string {
		if ( is_numeric( $value ) ) {
			return $value;
		}

		return "'" . $this->connection->escape( $value ) . "'";
	}

	/**
	 * Quote an identifier.
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 */
	public static function quote( string $identifier ): string {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}
}
