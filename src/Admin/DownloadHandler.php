<?php
/**
 * Package download handler.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Admin;

defined( 'ABSPATH' ) || exit;

use Backupino\Backup\InstallerBuilder;
use Backupino\Core\Capabilities;
use Backupino\Plugin;
use Backupino\Support\Fs;

/**
 * Streams a finished package through WordPress, with capability and nonce checks.
 */
final class DownloadHandler {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', array( self::class, 'maybe_download' ) );
	}

	/**
	 * Handle a download request, if any.
	 *
	 * @return void
	 */
	public static function maybe_download(): void {
		if ( ! isset( $_GET['download'] ) || ! isset( $_GET['page'] ) ) {
			return;
		}

		if ( Admin::SLUG !== sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) {
			return;
		}

		check_admin_referer( 'backupino_download' );

		if ( ! Capabilities::user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to download backups.', 'backupino' ), '', array( 'response' => 403 ) );
		}

		$file = sanitize_file_name( wp_unslash( (string) $_GET['download'] ) );

		if ( '' === $file || false !== strpos( $file, '..' ) ) {
			wp_die( esc_html__( 'Invalid file name.', 'backupino' ), '', array( 'response' => 400 ) );
		}

		$packages_dir = Plugin::instance()->paths()->packages();
		$path         = $packages_dir . '/' . $file;

		if ( preg_match( '/(?:^|-)installer\.php$/i', $file ) ) {
			$stem         = preg_replace( '/(?:^|-)installer\.php$/i', '', $file );
			$archive_path = $packages_dir . '/' . $stem . '.zip';
			if ( is_readable( $archive_path ) ) {
				$builder = new InstallerBuilder();
				$code    = $builder->build_for_package( $archive_path, Plugin::instance()->settings() );
				if ( '' !== $code ) {
					Fs::stream_string_download( $code, 'installer.php' );
				}
			}

			if ( is_readable( $path ) ) {
				Fs::stream_download( $path, 'installer.php' );
			}

			wp_die( esc_html__( 'This package no longer exists on the server.', 'backupino' ), '', array( 'response' => 404 ) );
		}

		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'This package no longer exists on the server.', 'backupino' ), '', array( 'response' => 404 ) );
		}

		Fs::stream_download( $path );
	}
}
