<?php
/**
 * Translation shims for the standalone installer.
 *
 * The installer runs without WordPress, so the i18n helpers are provided here
 * inside the installer namespace. The plugin never loads this file: it is only
 * concatenated into the generated `installer.php`.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'Backupino\Installer\__' ) ) {
	/**
	 * Return the (untranslated) text.
	 *
	 * @param string $text   Text.
	 * @param string $domain Unused, kept for signature compatibility.
	 * @return string
	 */
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'Backupino\Installer\esc_html' ) ) {
	/**
	 * Escape text for HTML output.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
