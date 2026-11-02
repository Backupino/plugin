<?php
/**
 * View renderer.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Admin;

defined( 'ABSPATH' ) || exit;

use Backupino\Plugin;

/**
 * Loads admin templates with a shared set of variables.
 */
final class Views {

	/**
	 * Render a template.
	 *
	 * @param string               $template Template name without extension.
	 * @param array<string, mixed> $vars     Extra variables.
	 * @return void
	 */
	public static function render( string $template, array $vars = array() ): void {
		$file = BACKUPINO_DIR . 'templates/' . $template . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		$plugin = Plugin::instance();

		foreach ( $vars as $backupino_key => $backupino_value ) {
			if ( is_string( $backupino_key ) && preg_match( '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $backupino_key ) ) {
				${$backupino_key} = $backupino_value;
			}
		}

		// Variables consumed by every template.
		$settings = $plugin->settings();
		$paths    = $plugin->paths();
		$jobs     = $plugin->jobs()->recent( 25 );

		include $file;
	}

	/**
	 * URL of a plugin admin screen.
	 *
	 * @param string $page Page slug suffix.
	 * @return string
	 */
	public static function url( string $page = '' ): string {
		$slug = '' === $page ? Admin::SLUG : Admin::SLUG . '-' . $page;

		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Build an admin download URL for a file in the packages folder.
	 *
	 * @param string $filename File name or path.
	 * @return string
	 */
	public static function download_url( string $filename ): string {
		return add_query_arg(
			array(
				'page'     => Admin::SLUG,
				'download' => rawurlencode( basename( $filename ) ),
				'_wpnonce' => wp_create_nonce( 'backupino_download' ),
			),
			admin_url( 'admin.php' )
		);
	}
}
