<?php
/**
 * Capability helpers.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

/**
 * Central place for the capability that guards every plugin feature.
 */
final class Capabilities {

	/**
	 * Capability required to manage backups.
	 *
	 * Defaults to `manage_options` so that the plugin behaves like WordPress
	 * expects out of the box. Site owners can narrow this with a filter.
	 *
	 * @return string
	 */
	public static function manage(): string {
		return (string) apply_filters( 'backupino_manage_capability', 'manage_options' );
	}

	/**
	 * Whether the current user may manage backups.
	 *
	 * @return bool
	 */
	public static function user_can_manage(): bool {
		return current_user_can( self::manage() );
	}
}
