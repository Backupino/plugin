<?php
/**
 * WordPress backed options store.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Support;

/**
 * Adapter around the WordPress options API.
 */
final class WpOptions implements OptionsStore {

	/**
	 * {@inheritDoc}
	 */
	public function get( string $key, $fallback = null ) {
		return get_option( $key, $fallback );
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( string $key, $value ): bool {
		return (bool) update_option( $key, $value, false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $key ): bool {
		return (bool) delete_option( $key );
	}
}
