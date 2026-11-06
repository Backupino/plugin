<?php
/**
 * Plugin settings.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

use Backupino\Support\OptionsStore;

/**
 * Typed, dot-notation access to the plugin settings with sane defaults.
 */
final class Settings {

	/**
	 * Option key.
	 */
	public const OPTION = 'backupino_settings';

	/**
	 * Storage adapter.
	 *
	 * @var OptionsStore
	 */
	private $store;

	/**
	 * Cached settings array.
	 *
	 * @var array<string, mixed>|null
	 */
	private $cache = null;

	/**
	 * Constructor.
	 *
	 * @param OptionsStore $store Options adapter.
	 */
	public function __construct( OptionsStore $store ) {
		$this->store = $store;
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'archive'   => array(
				'files'              => true,
				'database'           => true,
				'exclude_rules'      => array(),
				'ignore_larger_than' => 0,
			),
			'runtime'   => array(
				'files_per_tick' => 1000,
				'rows_per_tick'  => 1000,
				'time_budget'    => 20,
				'retry_limit'    => 3,
			),
			'storage'   => array(
				'driver' => 'local',
				'local'  => array(
					'path' => '',
				),
			),
			'schedule'  => array(
				'enabled'  => false,
				'interval' => 'monthly',
				'keep'     => 3,
			),
			'notify'    => array(
				'service'             => 'none',
				'token'               => '',
				'chat_id'             => '',
				'telegram_enabled'    => false,
				'telegram_token'      => '',
				'telegram_chat_id'    => '',
				'bale_enabled'        => false,
				'bale_token'          => '',
				'bale_chat_id'        => '',
				'msg_backup_template' => '',
				'msg_restore_template'=> '',
			),
			'installer' => array(
				'password' => '',
				'new_url'  => '',
			),
			'advanced'  => array(
				'log_level'   => 'info',
				'anonymize'   => true,
				'compression' => true,
				'opt_in_news' => false,
			),
		);
	}

	/**
	 * Whole settings tree.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = $this->store->get( self::OPTION, array() );
			$this->cache = self::merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}

		return $this->cache;
	}

	/**
	 * Read a value using dot notation (`runtime.time_budget`).
	 *
	 * @param string $path    Dot separated path.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public function get( string $path, $fallback = null ) {
		$value = $this->all();

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Write a value using dot notation.
	 *
	 * @param string $path  Dot separated path.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public function set( string $path, $value ): bool {
		$all  = $this->all();
		$ref  = &$all;
		$keys = explode( '.', $path );
		$last = array_pop( $keys );

		foreach ( $keys as $key ) {
			if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
				$ref[ $key ] = array();
			}

			$ref = &$ref[ $key ];
		}

		$ref[ $last ] = $value;

		return $this->update( $all );
	}

	/**
	 * Replace the whole settings tree.
	 *
	 * @param array<string, mixed> $values New values.
	 * @return bool
	 */
	public function update( array $values ): bool {
		if ( isset( $values['notify']['service'] ) ) {
			if ( 'telegram' === $values['notify']['service'] && ! isset( $values['notify']['telegram_enabled'] ) ) {
				$values['notify']['telegram_enabled'] = true;
			} elseif ( 'bale' === $values['notify']['service'] && ! isset( $values['notify']['bale_enabled'] ) ) {
				$values['notify']['bale_enabled'] = true;
			} elseif ( 'none' === $values['notify']['service'] ) {
				if ( ! isset( $values['notify']['telegram_enabled'] ) ) {
					$values['notify']['telegram_enabled'] = false;
				}
				if ( ! isset( $values['notify']['bale_enabled'] ) ) {
					$values['notify']['bale_enabled'] = false;
				}
			}
		}

		$merged      = self::merge( self::defaults(), $values );
		$sanitized   = self::sanitize( $merged );
		$this->cache = $sanitized;

		return $this->store->update( self::OPTION, $sanitized );
	}

	/**
	 * Reset to defaults.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		$this->cache = null;

		return $this->store->delete( self::OPTION );
	}

	/**
	 * Recursive merge that keeps array keys from both sides.
	 *
	 * @param array<string, mixed> $base    Defaults.
	 * @param array<string, mixed> $overlay Stored values.
	 * @return array<string, mixed>
	 */
	public static function merge( array $base, array $overlay ): array {
		foreach ( $overlay as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}

	/**
	 * Cast and clamp every known setting to its expected type.
	 *
	 * @param array<string, mixed> $values Raw values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $values ): array {
		$clean = self::sanitize_tree( self::defaults(), $values );

		$clean['runtime']['time_budget']    = min( 120, max( 5, (int) $clean['runtime']['time_budget'] ) );
		$clean['runtime']['files_per_tick'] = min( 5000, max( 10, (int) $clean['runtime']['files_per_tick'] ) );
		$clean['runtime']['rows_per_tick']  = min( 20000, max( 50, (int) $clean['runtime']['rows_per_tick'] ) );
		$clean['schedule']['keep']          = min( 100, max( 1, (int) $clean['schedule']['keep'] ) );

		$allowed_drivers   = (array) apply_filters( 'backupino_storage_drivers', array( 'local' ) );
		if ( ! in_array( $clean['storage']['driver'], $allowed_drivers, true ) ) {
			$clean['storage']['driver'] = 'local';
		}

		$allowed_intervals = (array) apply_filters( 'backupino_schedule_intervals', array( 'monthly' ) );
		if ( ! in_array( $clean['schedule']['interval'], $allowed_intervals, true ) ) {
			$clean['schedule']['interval'] = 'monthly';
		}

		if ( ! in_array( $clean['notify']['service'], array( 'none', 'telegram', 'bale' ), true ) ) {
			$clean['notify']['service'] = 'none';
		}

		// Keep notify.service in sync with the enabled checkboxes.
		$tg_on   = ! empty( $clean['notify']['telegram_enabled'] );
		$bale_on = ! empty( $clean['notify']['bale_enabled'] );

		if ( ! $tg_on && ! $bale_on ) {
			$clean['notify']['service'] = 'none';
		} elseif ( $bale_on && ! $tg_on ) {
			$clean['notify']['service'] = 'bale';
		} elseif ( $tg_on && ! $bale_on ) {
			$clean['notify']['service'] = 'telegram';
		}

		$clean['notify']['token']   = trim( sanitize_text_field( $clean['notify']['token'] ) );
		$clean['notify']['chat_id'] = trim( sanitize_text_field( $clean['notify']['chat_id'] ) );

		if ( isset( $clean['notify']['msg_backup_template'] ) ) {
			$clean['notify']['msg_backup_template'] = trim( (string) $clean['notify']['msg_backup_template'] );
		}
		if ( isset( $clean['notify']['msg_restore_template'] ) ) {
			$clean['notify']['msg_restore_template'] = trim( (string) $clean['notify']['msg_restore_template'] );
		}

		$clean['advanced']['opt_in_news'] = (bool) ( $clean['advanced']['opt_in_news'] ?? false );

		$clean['archive']['exclude_rules'] = array_values(
			array_filter(
				array_map(
					static function ( $rule ) {
						return is_scalar( $rule ) ? trim( (string) $rule ) : '';
					},
					$clean['archive']['exclude_rules']
				),
				static function ( $rule ) {
					return '' !== $rule;
				}
			)
		);

		return $clean;
	}

	/**
	 * Recursively cast values to the shape of the defaults.
	 *
	 * @param array<string, mixed> $defaults Defaults of the group.
	 * @param array<string, mixed> $values   Raw values.
	 * @return array<string, mixed>
	 */
	private static function sanitize_tree( array $defaults, array $values ): array {
		$clean = array();

		foreach ( $defaults as $key => $fallback ) {
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : $fallback;

			if ( is_array( $fallback ) ) {
				if ( ! is_array( $value ) ) {
					$clean[ $key ] = $fallback;
					continue;
				}

				// A list (e.g. exclusion rules) keeps its loose values, an
				// associative group is sanitized recursively.
				$clean[ $key ] = self::is_list( $fallback )
					? array_values( $value )
					: self::sanitize_tree( $fallback, $value );

				continue;
			}

			if ( is_bool( $fallback ) ) {
				$clean[ $key ] = (bool) $value;
				continue;
			}

			if ( is_int( $fallback ) ) {
				$clean[ $key ] = max( 0, (int) $value );
				continue;
			}

			$clean[ $key ] = is_scalar( $value ) ? (string) $value : (string) $fallback;
		}

		return $clean;
	}

	/**
	 * Whether an array is a plain list.
	 *
	 * @param array<mixed> $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ): bool {
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
