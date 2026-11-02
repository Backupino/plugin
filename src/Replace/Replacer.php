<?php
/**
 * Search & replace engine.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Replace;

defined( 'ABSPATH' ) || exit;

/**
 * Serialized-safe, JSON-aware search and replace.
 *
 * Used by the standalone installer to rewrite the old site URL with the new
 * one, and by the CLI to run ad-hoc replacements during a migration.
 */
final class Replacer {

	/**
	 * Search needles.
	 *
	 * @var string[]
	 */
	private $search;

	/**
	 * Replacement values.
	 *
	 * @var string[]
	 */
	private $replace;

	/**
	 * Maximum recursion depth for nested payloads.
	 */
	private const MAX_DEPTH = 12;

	/**
	 * Constructor.
	 *
	 * @param string|string[] $search  Needle(s).
	 * @param string|string[] $replace Replacement(s).
	 */
	public function __construct( $search, $replace ) {
		$this->search  = is_array( $search ) ? array_values( array_map( 'strval', $search ) ) : array( (string) $search );
		$this->replace = is_array( $replace ) ? array_values( array_map( 'strval', $replace ) ) : array( (string) $replace );

		// Drop empty search terms: they would match everywhere.
		foreach ( $this->search as $index => $needle ) {
			if ( '' === $needle ) {
				unset( $this->search[ $index ], $this->replace[ $index ] );
			}
		}

		$this->search  = array_values( $this->search );
		$this->replace = array_values( $this->replace );
	}

	/**
	 * Whether there is anything to replace.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->search;
	}

	/**
	 * Build from the old and new site URLs, covering http/https and www variants.
	 *
	 * @param string $old_url Old site URL.
	 * @param string $new_url New site URL.
	 * @return self
	 */
	public static function from_urls( string $old_url, string $new_url ): self {
		$pairs = array( array( rtrim( $old_url, '/' ), rtrim( $new_url, '/' ) ) );

		foreach ( $pairs as $pair ) {
			$variants = self::url_variants( $pair[0] );

			foreach ( $variants as $variant ) {
				$pairs[] = array( $variant, self::swap_scheme( $variant, $pair[1] ) );
			}
		}

		$extended = array();
		foreach ( $pairs as $pair ) {
			$s = $pair[0];
			$r = $pair[1];
			if ( '' === $s || $s === $r ) {
				continue;
			}
			$extended[] = array( $s, $r );

			// JSON-escaped slashes variant: https:\/\/site -> https:\/\/newsite
			if ( false !== strpos( $s, '/' ) ) {
				$extended[] = array( str_replace( '/', '\/', $s ), str_replace( '/', '\/', $r ) );
			}

			// URL-encoded variants for query parameters and dynamic tag attributes
			$enc_s = rawurlencode( $s );
			$enc_r = rawurlencode( $r );
			if ( $enc_s !== $s ) {
				$extended[] = array( $enc_s, $enc_r );
				$extended[] = array( strtolower( $enc_s ), strtolower( $enc_r ) );
			}
		}

		// Sort pairs by search string length descending to replace longer paths before prefixes
		usort( $extended, static function( array $a, array $b ): int {
			return strlen( $b[0] ) <=> strlen( $a[0] );
		} );

		$search  = array();
		$replace = array();

		foreach ( $extended as $pair ) {
			if ( ! in_array( $pair[0], $search, true ) ) {
				$search[]  = $pair[0];
				$replace[] = $pair[1];
			}
		}

		return new self( $search, $replace );
	}

	/**
	 * Replace inside a database value, handling serialization and JSON.
	 *
	 * @param string $subject Raw value.
	 * @param int    $depth   Recursion depth.
	 * @return string
	 */
	public function text( string $subject, int $depth = 0 ): string {
		if ( '' === $subject || $this->is_empty() ) {
			return $subject;
		}

		if ( $depth > self::MAX_DEPTH ) {
			return $this->plain( $subject );
		}

		// Fast pre-filter: if none of the search terms are present, return unchanged immediately.
		$has_needle = false;
		foreach ( $this->search as $needle ) {
			if ( '' !== $needle && false !== strpos( $subject, $needle ) ) {
				$has_needle = true;
				break;
			}
		}

		if ( ! $has_needle ) {
			return $subject;
		}

		if ( SerializedParser::looks_serialized( $subject ) ) {
			$tree = SerializedParser::parse( $subject );

			if ( null !== $tree ) {
				return SerializedParser::render(
					$tree,
					function ( string $leaf ) use ( $depth ) {
						return $this->text( $leaf, $depth + 1 );
					}
				);
			}
		}

		$json = $this->from_json( $subject );

		if ( null !== $json ) {
			return $json;
		}

		return $this->plain( $subject );
	}

	/**
	 * Plain `str_replace`.
	 *
	 * @param string $subject Subject.
	 * @return string
	 */
	public function plain( string $subject ): string {
		return str_replace( $this->search, $this->replace, $subject );
	}

	/**
	 * Replace recursively inside arrays and objects.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function deep( $value ) {
		if ( is_string( $value ) ) {
			return $this->text( $value );
		}

		if ( is_array( $value ) ) {
			$out = array();

			foreach ( $value as $key => $item ) {
				$new_key = is_string( $key ) ? $this->text( $key ) : $key;

				$out[ $new_key ] = $this->deep( $item );
			}

			return $out;
		}

		if ( $value instanceof \stdClass ) {
			$out = new \stdClass();

			foreach ( get_object_vars( $value ) as $key => $item ) {
				$out->{$key} = $this->deep( $item );
			}

			return $out;
		}

		return $value;
	}

	/**
	 * Replace inside a JSON document, preserving its structure.
	 *
	 * @param string $subject Subject.
	 * @return string|null `null` when the subject is not JSON.
	 */
	private function from_json( string $subject ): ?string {
		$trimmed = trim( $subject );

		if ( '' === $trimmed ) {
			return null;
		}

		$first = $trimmed[0];

		if ( '{' !== $first && '[' !== $first ) {
			return null;
		}

		$decoded = json_decode( $trimmed, true );

		if ( null === $decoded && 'null' !== $trimmed ) {
			return null;
		}

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$replaced = $this->deep( $decoded );
		$encoded  = json_encode( $replaced, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $encoded ) ) {
			return null;
		}

		// Keep the surrounding whitespace of the original document.
		$leading  = substr( $subject, 0, strpos( $subject, $trimmed ) );
		$trailing = substr( $subject, strlen( $leading ) + strlen( $trimmed ) );

		return $leading . $encoded . $trailing;
	}

	/**
	 * Generate the common variants of a URL.
	 *
	 * @param string $url URL.
	 * @return string[]
	 */
	private static function url_variants( string $url ): array {
		$variants = array();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parsed = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );

		if ( ! is_array( $parsed ) || ! isset( $parsed['host'] ) ) {
			return $variants;
		}

		$scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : 'https';
		$host   = (string) $parsed['host'];
		$path   = isset( $parsed['path'] ) ? rtrim( (string) $parsed['path'], '/' ) : '';
		$port   = isset( $parsed['port'] ) ? ':' . (int) $parsed['port'] : '';

		$hosts = array( $host );

		if ( 0 === strpos( $host, 'www.' ) ) {
			$hosts[] = substr( $host, 4 );
		} else {
			$hosts[] = 'www.' . $host;
		}

		foreach ( array( 'http', 'https' ) as $variant_scheme ) {
			foreach ( $hosts as $variant_host ) {
				$variants[] = $variant_scheme . '://' . $variant_host . $port . $path;
			}
		}

		return array_values( array_unique( $variants ) );
	}

	/**
	 * Keep the scheme of the matched variant when swapping to the new URL.
	 *
	 * @param string $variant Old URL variant.
	 * @param string $target  New site URL.
	 * @return string
	 */
	private static function swap_scheme( string $variant, string $target ): string {
		$variant_scheme = 0 === strpos( $variant, 'http://' ) ? 'http://' : 'https://';
		$target_scheme  = 0 === strpos( $target, 'http://' ) ? 'http://' : 'https://';
		$stripped       = preg_replace( '#^https?://#', '', $target );

		return $variant_scheme . str_replace( $target_scheme, '', $target_scheme . $stripped );
	}
}
