<?php
/**
 * SQL statement splitter.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * Splits a SQL dump into statements without loading it into memory.
 *
 * The splitter understands single quotes, double quotes, backticks, escaped
 * quotes, `--`/`#` line comments and `/* *\/` block comments, which is enough
 * for the dumps produced by Backupino (and for most mysqldump output).
 */
final class SqlSplitter {

	/**
	 * Maximum bytes read per call.
	 */
	private const MAX_CHUNK = 8388608;

	/**
	 * Absolute path of the SQL file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Size of the SQL file in bytes.
	 *
	 * @return int
	 */
	public function size(): int {
		return (int) @filesize( $this->file );
	}

	/**
	 * Read complete statements starting at `$offset`.
	 *
	 * @param int $offset           Byte offset.
	 * @param int $max_bytes        Maximum bytes to consume.
	 * @return array{statements: string[], offset: int, done: bool}
	 */
	public function read( int $offset, int $max_bytes = 262144 ): array {
		$size = (int) @filesize( $this->file );

		if ( $offset >= $size ) {
			return array(
				'statements' => array(),
				'offset'     => $offset,
				'done'       => true,
			);
		}

		$chunk_size = max( 65536, $max_bytes );

		while ( true ) {
			$chunk = (string) file_get_contents( $this->file, false, null, $offset, $chunk_size );

			if ( '' === $chunk ) {
				return array(
					'statements' => array(),
					'offset'     => $offset,
					'done'       => true,
				);
			}

			$result = $this->scan( $chunk );

			if ( array() !== $result['statements'] || $offset + strlen( $chunk ) >= $size || $chunk_size >= self::MAX_CHUNK ) {
				$new_offset  = $offset + $result['consumed'];
				$reached_eof = ( $offset + strlen( $chunk ) ) >= $size;
				$trailing    = $reached_eof ? trim( substr( $chunk, $result['consumed'] ) ) : '';
				$all_done    = $reached_eof && ( '' === $trailing || 0 === strpos( $trailing, '--' ) || 0 === strpos( $trailing, '#' ) || 0 === strpos( $trailing, '/*' ) );

				return array(
					'statements' => $result['statements'],
					'offset'     => $all_done ? $size : $new_offset,
					'done'       => $new_offset >= $size || $all_done,
				);
			}

			// A single statement is larger than the chunk: read further.
			$chunk_size *= 2;
		}
	}

	/**
	 * Scan a chunk for statement boundaries.
	 *
	 * @param string $chunk Chunk contents.
	 * @return array{statements: string[], consumed: int}
	 */
	private function scan( string $chunk ): array {
		$length     = strlen( $chunk );
		$statements = array();
		$start      = 0;
		$state      = 'normal';
		$i          = 0;

		while ( $i < $length ) {
			$char = $chunk[ $i ];
			$next = $i + 1 < $length ? $chunk[ $i + 1 ] : '';

			switch ( $state ) {
				case 'normal':
					if ( "'" === $char ) {
						$state = 'single';
						break;
					}

					if ( '"' === $char ) {
						$state = 'double';
						break;
					}

					if ( '`' === $char ) {
						$state = 'backtick';
						break;
					}

					if ( '#' === $char ) {
						$state = 'line';
						break;
					}

					if ( '-' === $char && '-' === $next ) {
						$state = 'line';
						++$i;

						break;
					}

					if ( '/' === $char && '*' === $next ) {
						$state = 'block';
						++$i;

						break;
					}

					if ( ';' === $char ) {
						$statement = trim( substr( $chunk, $start, $i - $start + 1 ) );

						if ( '' !== trim( $statement, ";\n\r\t " ) ) {
							$statements[] = $statement;
						}

						$start = $i + 1;
					}

					break;

				case 'single':
				case 'double':
					if ( '\\' === $char ) {
						++$i;

						break;
					}

					if ( ( 'single' === $state && "'" === $char ) || ( 'double' === $state && '"' === $char ) ) {
						if ( $char === $next ) {
							++$i;

							break;
						}

						$state = 'normal';
					}

					break;

				case 'backtick':
					if ( '`' === $char ) {
						$state = 'normal';
					}

					break;

				case 'line':
					if ( "\n" === $char ) {
						$state = 'normal';
					}

					break;

				case 'block':
					if ( '*' === $char && '/' === $next ) {
						$state = 'normal';
						++$i;
					}

					break;
			}

			++$i;
		}

		return array(
			'statements' => $statements,
			'consumed'   => $start,
		);
	}
}
