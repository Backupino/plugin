<?php
/**
 * Byte formatting helpers.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

/**
 * Human readable byte sizes.
 */
final class Bytes {

	/**
	 * Format a byte count for display.
	 *
	 * @param int $bytes      Byte count.
	 * @param int $decimals   Decimal places.
	 * @return string
	 */
	public static function format( int $bytes, int $decimals = 2 ): string {
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}

		$units = array( 'KB', 'MB', 'GB', 'TB', 'PB' );
		$value = $bytes / 1024;
		$index = 0;
		$last  = count( $units ) - 1;

		while ( $value >= 1024 && $index < $last ) {
			$value /= 1024;
			++$index;
		}

		return number_format( $value, $decimals ) . ' ' . $units[ $index ];
	}

	/**
	 * Convert a PHP ini shorthand (`64M`, `1G`, `-1`) to bytes.
	 *
	 * @param string $value Ini value.
	 * @return int Bytes, `-1` for unlimited, `0` when unknown.
	 */
	public static function from_ini( string $value ): int {
		$value = trim( $value );

		if ( '' === $value ) {
			return 0;
		}

		if ( '-1' === $value ) {
			return -1;
		}

		$unit  = strtolower( substr( $value, -1 ) );
		$num   = (float) $value;
		$scale = 1;

		switch ( $unit ) {
			case 'g':
				$scale = 1024 * 1024 * 1024;
				break;
			case 'm':
				$scale = 1024 * 1024;
				break;
			case 'k':
				$scale = 1024;
				break;
		}

		return (int) round( $num * $scale );
	}
}
