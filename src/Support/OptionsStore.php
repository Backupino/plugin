<?php
/**
 * Options storage contract.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal key/value store abstraction over `wp_options`.
 *
 * Keeping this behind an interface lets the settings layer be unit tested
 * without booting WordPress.
 */
interface OptionsStore {

	/**
	 * Read a value.
	 *
	 * @param string $key     Option name.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null );

	/**
	 * Persist a value.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public function update( string $key, $value ): bool;

	/**
	 * Delete a value.
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	public function delete( string $key ): bool;
}
