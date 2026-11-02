<?php
/**
 * Serialized data parser.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Replace;

defined( 'ABSPATH' ) || exit;

/**
 * Tokenizer for PHP's `serialize()` output.
 *
 * URL replacement has to rewrite serialized payloads without breaking their
 * length prefixes. Instead of the fragile "regex + fix the length" approach,
 * the payload is parsed into a tree, the string leaves are rewritten, and the
 * tree is rendered back with freshly computed lengths.
 */
final class SerializedParser {

	/**
	 * Input being parsed.
	 *
	 * @var string
	 */
	private $input = '';

	/**
	 * Cursor position.
	 *
	 * @var int
	 */
	private $position = 0;

	/**
	 * Parse a serialized string.
	 *
	 * @param string $input Serialized payload.
	 * @return array<string, mixed>|null Token tree, `null` when invalid.
	 */
	public static function parse( string $input ): ?array {
		$parser = new self();
		$token  = $parser->read_token( $input, 0 );

		if ( null === $token ) {
			return null;
		}

		if ( strlen( $input ) !== $token['end'] ) {
			return null;
		}

		return $token['node'];
	}

	/**
	 * Render a token tree.
	 *
	 * @param array<string, mixed> $node   Token tree.
	 * @param callable             $mapper Applied to every string leaf.
	 * @return string
	 */
	public static function render( array $node, callable $mapper ): string {
		switch ( $node['type'] ) {
			case 'string':
				$value = (string) call_user_func( $mapper, (string) $node['value'] );

				return 's:' . strlen( $value ) . ':"' . $value . '";';

			case 'int':
				return 'i:' . $node['value'] . ';';

			case 'float':
				return 'd:' . $node['value'] . ';';

			case 'bool':
				return 'b:' . ( (int) $node['value'] ? '1' : '0' ) . ';';

			case 'null':
				return 'N;';

			case 'array':
				$out = 'a:' . count( $node['items'] ) . ':{';

				foreach ( $node['items'] as $item ) {
					$out .= self::render( $item[0], $mapper ) . self::render( $item[1], $mapper );
				}

				return $out . '}';

			case 'object':
				$class_name = (string) $node['class'];
				$out        = 'O:' . strlen( $class_name ) . ':"' . $class_name . '":' . count( $node['items'] ) . ':{';

				foreach ( $node['items'] as $item ) {
					$out .= self::render( $item[0], $mapper ) . self::render( $item[1], $mapper );
				}

				return $out . '}';

			case 'custom':
				$class_name = (string) $node['class'];
				$data       = (string) call_user_func( $mapper, (string) $node['data'] );

				return 'C:' . strlen( $class_name ) . ':"' . $class_name . '":' . strlen( $data ) . ':{' . $data . '}';
		}

		return '';
	}

	/**
	 * Whether the payload "looks like" serialized data.
	 *
	 * @param string $value Subject.
	 * @return bool
	 */
	public static function looks_serialized( string $value ): bool {
		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			return false;
		}

		if ( ! preg_match( '/^(?:[aOsidbC]:|N;)/', $trimmed ) ) {
			return false;
		}

		return null !== self::parse( $trimmed );
	}

	/**
	 * Read one token starting at `$offset`.
	 *
	 * @param string $input  Payload.
	 * @param int    $offset Start offset.
	 * @return array{node: array<string, mixed>, end: int}|null
	 */
	private function read_token( string $input, int $offset ): ?array {
		$length = strlen( $input );

		if ( $offset >= $length ) {
			return null;
		}

		$type = $input[ $offset ];

		switch ( $type ) {
			case 'N':
				if ( $offset + 1 < $length && ';' === $input[ $offset + 1 ] ) {
					return array(
						'node' => array( 'type' => 'null' ),
						'end'  => $offset + 2,
					);
				}

				return null;

			case 'b':
				$end   = $this->find( $input, $offset, ';' );
				$value = substr( $input, $offset + 2, $end - ( $offset + 2 ) );

				return array(
					'node' => array(
						'type'  => 'bool',
						'value' => '1' === $value,
					),
					'end'  => $end + 1,
				);

			case 'i':
				$end = $this->find( $input, $offset, ';' );

				return array(
					'node' => array(
						'type'  => 'int',
						'value' => substr( $input, $offset + 2, $end - ( $offset + 2 ) ),
					),
					'end'  => $end + 1,
				);

			case 'd':
				$end = $this->find( $input, $offset, ';' );

				return array(
					'node' => array(
						'type'  => 'float',
						'value' => substr( $input, $offset + 2, $end - ( $offset + 2 ) ),
					),
					'end'  => $end + 1,
				);

			case 's':
			case 'C':
				return $this->read_string( $input, $offset, $type );

			case 'a':
				return $this->read_container( $input, $offset, 'array' );

			case 'O':
				return $this->read_container( $input, $offset, 'object' );
		}

		return null;
	}

	/**
	 * Read a string or custom-serializable token.
	 *
	 * @param string $input  Payload.
	 * @param int    $offset Start offset.
	 * @param string $type   Token type (`s` or `C`).
	 * @return array{node: array<string, mixed>, end: int}|null
	 */
	private function read_string( string $input, int $offset, string $type ): ?array {
		if ( ! preg_match( '/^[sC]:(\d+):"/', substr( $input, $offset ), $match ) ) {
			return null;
		}

		$declared = (int) $match[1];
		$start    = $offset + strlen( $match[0] );
		$value    = substr( $input, $start, $declared );

		if ( strlen( $value ) !== $declared ) {
			return null;
		}

		$after = $start + $declared;

		if ( ! isset( $input[ $after ] ) ) {
			return null;
		}

		if ( 's' === $type ) {
			if ( '"' !== $input[ $after ] || ! isset( $input[ $after + 1 ] ) || ';' !== $input[ $after + 1 ] ) {
				return null;
			}

			return array(
				'node' => array(
					'type'  => 'string',
					'value' => $value,
				),
				'end'  => $after + 2,
			);
		}

		// C:<class-length>:"<class>":<data-length>:{<data>}.
		if ( '"' !== $input[ $after ] || ! preg_match( '/^":(\d+):{/', substr( $input, $after + 1 ), $data_match ) ) {
			return null;
		}

		$data_start = $after + 1 + strlen( $data_match[0] );
		$data       = substr( $input, $data_start, (int) $data_match[1] );

		if ( strlen( $data ) !== (int) $data_match[1] ) {
			return null;
		}

		$end = $data_start + strlen( $data );

		if ( ! isset( $input[ $end ] ) || '}' !== $input[ $end ] ) {
			return null;
		}

		return array(
			'node' => array(
				'type'  => 'custom',
				'class' => $value,
				'data'  => $data,
			),
			'end'  => $end + 1,
		);
	}

	/**
	 * Read an array or object token.
	 *
	 * @param string $input  Payload.
	 * @param int    $offset Start offset.
	 * @param string $kind   `array` or `object`.
	 * @return array{node: array<string, mixed>, end: int}|null
	 */
	private function read_container( string $input, int $offset, string $kind ): ?array {
		if ( ! preg_match( '/^[aO]:(\d+):/', substr( $input, $offset ), $match ) ) {
			return null;
		}

		$declared = (int) $match[1];
		$position = $offset + strlen( $match[0] );
		$name     = null;
		$count    = $declared;

		if ( 'object' === $kind ) {
			if ( ! isset( $input[ $position ] ) || '"' !== $input[ $position ] ) {
				return null;
			}

			$name     = substr( $input, $position + 1, $declared );
			$position = $position + 1 + $declared;

			if ( strlen( $name ) !== $declared || ! isset( $input[ $position ] ) || '"' !== $input[ $position ] ) {
				return null;
			}

			++$position;

			if ( ! isset( $input[ $position ] ) || ':' !== $input[ $position ] ) {
				return null;
			}

			++$position;

			if ( ! preg_match( '/^(\d+):/', substr( $input, $position ), $count_match ) ) {
				return null;
			}

			$count     = (int) $count_match[1];
			$position += strlen( $count_match[0] );
		}

		if ( ! isset( $input[ $position ] ) || '{' !== $input[ $position ] ) {
			return null;
		}

		++$position;
		$items = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$key = $this->read_token( $input, $position );

			if ( null === $key ) {
				return null;
			}

			$value = $this->read_token( $input, $key['end'] );

			if ( null === $value ) {
				return null;
			}

			$items[]  = array( $key['node'], $value['node'] );
			$position = $value['end'];
		}

		if ( ! isset( $input[ $position ] ) || '}' !== $input[ $position ] ) {
			return null;
		}

		return array(
			'node' => array(
				'type'  => $kind,
				'class' => $name,
				'items' => $items,
			),
			'end'  => $position + 1,
		);
	}

	/**
	 * Find the next occurrence of a character.
	 *
	 * @param string $input  Payload.
	 * @param int    $offset Start offset.
	 * @param string $char   Character to find.
	 * @return int
	 */
	private function find( string $input, int $offset, string $char ): int {
		$position = strpos( $input, $char, $offset + 2 );

		return false === $position ? strlen( $input ) : $position;
	}
}
