<?php
/**
 * String helpers.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Small string utilities shared by the plugin and the installer.
 */
final class Str {

	/**
	 * Random lowercase hexadecimal string.
	 *
	 * @param int $bytes Number of random bytes (output is twice as long).
	 * @return string
	 */
	public static function random( int $bytes = 8 ): string {
		try {
			return bin2hex( random_bytes( $bytes ) );
		} catch ( \Exception $e ) {
			$seed = function_exists( 'wp_rand' ) ? wp_rand() : random_int( 1, 999999 );
			return substr( md5( uniqid( (string) $seed, true ) ), 0, $bytes * 2 );
		}
	}

	/**
	 * Convert Persian and Arabic digits to Latin digits (0-9).
	 *
	 * @param mixed $value Input string or number.
	 * @return string
	 */
	public static function latin_digits( $value ): string {
		$value = (string) $value;

		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $arabic, $latin, str_replace( $persian, $latin, $value ) );
	}

	/**
	 * Format a number with Latin digits and optional decimal places.
	 *
	 * @param int|float|string $number   Number to format.
	 * @param int              $decimals Decimals (default 0).
	 * @return string
	 */
	public static function number( $number, int $decimals = 0 ): string {
		if ( ! is_numeric( $number ) ) {
			return self::latin_digits( (string) $number );
		}

		$formatted = number_format( (float) $number, $decimals, '.', ',' );

		return self::latin_digits( $formatted );
	}

	/**
	 * Ensure a trailing slash.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function trailingslash( string $path ): string {
		return rtrim( $path, '/\\' ) . '/';
	}

	/**
	 * Ensure no trailing slash.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function untrailingslash( string $path ): string {
		return rtrim( $path, '/\\' );
	}

	/**
	 * Relative path of `$path` inside `$root`, using forward slashes.
	 *
	 * @param string $root Absolute base path.
	 * @param string $path Absolute path.
	 * @return string
	 */
	public static function relative( string $root, string $path ): string {
		$root = self::trailingslash( Fs::normalize( $root ) );
		$path = Fs::normalize( $path );

		if ( 0 === strpos( $path, $root ) ) {
			return substr( $path, strlen( $root ) );
		}

		return ltrim( $path, '/' );
	}

	/**
	 * Whether a string starts with a needle (PHP 7.4 compatible).
	 *
	 * @param string $haystack Subject.
	 * @param string $needle   Prefix.
	 * @return bool
	 */
	public static function starts_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
	}

	/**
	 * Whether a string ends with a needle (PHP 7.4 compatible).
	 *
	 * @param string $haystack Subject.
	 * @param string $needle   Suffix.
	 * @return bool
	 */
	public static function ends_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		$length = strlen( $needle );

		return $length <= strlen( $haystack ) && substr( $haystack, -$length ) === $needle;
	}

	/**
	 * Truncate a string to a maximum byte length.
	 *
	 * @param string $value Subject.
	 * @param int    $max   Maximum length.
	 * @return string
	 */
	public static function limit( string $value, int $max ): string {
		if ( strlen( $value ) <= $max ) {
			return $value;
		}

		return substr( $value, 0, $max );
	}
}
