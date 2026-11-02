<?php
/**
 * Jalali and Gregorian date and time calculations.
 *
 * 100% self-contained pure PHP implementation without external library or ext-intl dependency.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Date and calendar conversions and formatting.
 */
final class Dates {

	/**
	 * Jalali month names in Persian.
	 */
	public const JALALI_MONTHS_FA = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	/**
	 * Jalali month names in English transliteration.
	 */
	public const JALALI_MONTHS_EN = array(
		1  => 'Farvardin',
		2  => 'Ordibehesht',
		3  => 'Khordad',
		4  => 'Tir',
		5  => 'Mordad',
		6  => 'Shahrivar',
		7  => 'Mehr',
		8  => 'Aban',
		9  => 'Azar',
		10 => 'Dey',
		11 => 'Bahman',
		12 => 'Esfand',
	);

	/**
	 * Determine if current site locale is Persian.
	 *
	 * @param string|null $locale Explicit locale to test or null for current.
	 * @return bool
	 */
	public static function is_persian_locale( ?string $locale = null ): bool {
		if ( null === $locale ) {
			if ( function_exists( 'determine_locale' ) ) {
				$locale = (string) determine_locale();
			} elseif ( function_exists( 'get_locale' ) ) {
				$locale = (string) get_locale();
			} else {
				$locale = 'en_US';
			}
		}

		return 0 === strpos( $locale, 'fa' );
	}

	/**
	 * Check if a Jalali year is a leap year (366 days).
	 *
	 * @param int $jy Jalali year.
	 * @return bool
	 */
	public static function is_jalali_leap( int $jy ): bool {
		list( $gy, $gm, $gd )                   = self::jalali_to_gregorian( $jy, 12, 30 );
		list( $check_jy, $check_jm, $check_jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
		return ( 30 === $check_jd && 12 === $check_jm );
	}

	/**
	 * Days in a specific Jalali month.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month (1-12).
	 * @return int
	 */
	public static function jalali_days_in_month( int $jy, int $jm ): int {
		if ( $jm >= 1 && $jm <= 6 ) {
			return 31;
		}

		if ( $jm >= 7 && $jm <= 11 ) {
			return 30;
		}

		if ( 12 === $jm ) {
			return self::is_jalali_leap( $jy ) ? 30 : 29;
		}

		return 30;
	}

	/**
	 * Convert Gregorian year/month/day to Jalali year/month/day.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month (1-12).
	 * @param int $gd Gregorian day (1-31).
	 * @return array{0: int, 1: int, 2: int} Array of [jy, jm, jd].
	 */
	public static function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( (int) $jy, (int) $jm, (int) $jd );
	}

	/**
	 * Convert Jalali year/month/day to Gregorian year/month/day.
	 *
	 * @param int $jy Jalali year.
	 * @param int $jm Jalali month (1-12).
	 * @param int $jd Jalali day (1-31).
	 * @return array{0: int, 1: int, 2: int} Array of [gy, gm, gd].
	 */
	public static function jalali_to_gregorian( int $jy, int $jm, int $jd ): array {
		$jy   += 1595;
		$days  = -355668 + ( 365 * $jy ) + intdiv( $jy, 33 ) * 8 + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd;

		if ( $jm < 7 ) {
			$days += ( $jm - 1 ) * 31;
		} else {
			$days += ( ( $jm - 7 ) * 30 ) + 186;
		}

		$gy    = 400 * intdiv( $days, 146097 );
		$days %= 146097;

		if ( $days > 36524 ) {
			$days--;
			$gy += 100 * intdiv( $days, 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}

		$gy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$gy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
		}

		$gd    = $days + 1;
		$sal_a = array( 0, 31, ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || ( $gy % 400 === 0 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

		for ( $gm = 0; $gm < 13 && $gd > $sal_a[ $gm ]; $gm++ ) {
			$gd -= $sal_a[ $gm ];
		}

		return array( (int) $gy, (int) $gm, (int) $gd );
	}

	/**
	 * Get site DateTimeZone object.
	 *
	 * @return DateTimeZone
	 */
	public static function site_timezone(): DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		if ( function_exists( 'get_option' ) ) {
			$timezone_string = (string) get_option( 'timezone_string', '' );
			if ( '' !== $timezone_string ) {
				try {
					return new DateTimeZone( $timezone_string );
				} catch ( \Exception $e ) {
					// Fall through to offset.
				}
			}

			$gmt_offset = (float) get_option( 'gmt_offset', 0 );
			$hours      = (int) $gmt_offset;
			$minutes    = (int) abs( ( $gmt_offset - $hours ) * 60 );
			$offset_str = sprintf( '%+03d:%02d', $hours, $minutes );

			try {
				return new DateTimeZone( $offset_str );
			} catch ( \Exception $e ) {
				// Fall through to UTC.
			}
		}

		return new DateTimeZone( 'UTC' );
	}

	/**
	 * Format timestamp based on locale.
	 *
	 * Persian: 1404/08/09 14:30 (Jalali, Latin digits)
	 * English/other: 2026-10-31 14:30 (Gregorian, Latin digits)
	 *
	 * @param int               $timestamp Unix timestamp (UTC).
	 * @param string|null       $locale    Locale string or null for current.
	 * @param DateTimeZone|null $timezone  Timezone or null for site timezone.
	 * @return string
	 */
	public static function format( int $timestamp, ?string $locale = null, ?DateTimeZone $timezone = null ): string {
		$tz = $timezone ?: self::site_timezone();
		$dt = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz );

		$gy = (int) $dt->format( 'Y' );
		$gm = (int) $dt->format( 'm' );
		$gd = (int) $dt->format( 'd' );
		$hi = $dt->format( 'H:i' );

		if ( self::is_persian_locale( $locale ) ) {
			list( $jy, $jm, $jd ) = self::gregorian_to_jalali( $gy, $gm, $gd );
			return sprintf( '%04d/%02d/%02d %s', $jy, $jm, $jd, $hi );
		}

		return sprintf( '%04d-%02d-%02d %s', $gy, $gm, $gd, $hi );
	}

	/**
	 * Compute timestamp for the start (00:00:00 local time) of the next month.
	 *
	 * If locale is Persian, computes the 1st of next Jalali month.
	 * If locale is English/other, computes the 1st of next Gregorian month.
	 *
	 * @param int               $from_timestamp Starting Unix timestamp (UTC).
	 * @param string|null       $locale         Locale string or null for current.
	 * @param DateTimeZone|null $timezone       Timezone or null for site timezone.
	 * @return int Unix timestamp (UTC) of 00:00:00 on the 1st of next month.
	 */
	public static function next_month_start( int $from_timestamp, ?string $locale = null, ?DateTimeZone $timezone = null ): int {
		$tz = $timezone ?: self::site_timezone();
		$dt = ( new DateTimeImmutable( '@' . $from_timestamp ) )->setTimezone( $tz );

		$gy = (int) $dt->format( 'Y' );
		$gm = (int) $dt->format( 'm' );
		$gd = (int) $dt->format( 'd' );

		if ( self::is_persian_locale( $locale ) ) {
			list( $jy, $jm ) = self::gregorian_to_jalali( $gy, $gm, $gd );

			// Next Jalali month.
			$next_jm = $jm + 1;
			$next_jy = $jy;

			if ( $next_jm > 12 ) {
				$next_jm = 1;
				$next_jy++;
			}

			// 1st of next Jalali month in Gregorian.
			list( $target_gy, $target_gm, $target_gd ) = self::jalali_to_gregorian( $next_jy, $next_jm, 1 );

			$target_dt = new DateTimeImmutable(
				sprintf( '%04d-%02d-%02d 00:00:00', $target_gy, $target_gm, $target_gd ),
				$tz
			);

			return $target_dt->getTimestamp();
		}

		// Next Gregorian month.
		$next_gm = $gm + 1;
		$next_gy = $gy;

		if ( $next_gm > 12 ) {
			$next_gm = 1;
			$next_gy++;
		}

		$target_dt = new DateTimeImmutable(
			sprintf( '%04d-%02d-01 00:00:00', $next_gy, $next_gm ),
			$tz
		);

		return $target_dt->getTimestamp();
	}
}
