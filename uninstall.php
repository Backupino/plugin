<?php
/**
 * Uninstall routine.
 *
 * Runs automatically when the user deletes the plugin from the WordPress admin.
 * Completely cleans up custom database tables, options, scheduled cron events,
 * and storage directories (packages, temporary jobs, logs).
 *
 * @package Backupino
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Recursively remove a directory and all its contents.
 *
 * @param string $dir Target directory.
 * @return void
 */
function backupino_remove_directory( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			@chmod( $item->getPathname(), 0777 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@rmdir( $item->getPathname() );       // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} else {
			@chmod( $item->getPathname(), 0777 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $item->getPathname() );      // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	@chmod( $dir, 0777 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@rmdir( $dir );       // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Remove every trace of the plugin for the current site.
 *
 * @return void
 */
function backupino_uninstall_site(): void {
	global $wpdb;

	// Drop custom database tables
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'backupino_jobs`' );

	// Delete all stored options
	delete_option( 'backupino_settings' );
	delete_option( 'backupino_schema_version' );
	delete_option( 'backupino_schedule_state' );
	delete_option( 'backupino_installed_at' );
	delete_option( 'backupino_download_key' );
	delete_option( 'backupino_notify_queue' );
	delete_option( 'backupino_migration_notice' );

	// Clear scheduled cron events
	wp_clear_scheduled_hook( 'backupino_cron_worker' );
	wp_clear_scheduled_hook( 'backupino_cron_scheduler' );
	wp_clear_scheduled_hook( 'backupino_cron_queue' );
	wp_clear_scheduled_hook( 'backupino_cron_retention' );

	// Remove storage directories in wp-content/backupino
	$content_dir = defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : ( (string) ABSPATH . 'wp-content' );
	$primary_dir = rtrim( $content_dir, '/' ) . '/backupino';
	backupino_remove_directory( $primary_dir );

	// Also remove upload-based directory if created in legacy installs
	$uploads = wp_upload_dir( null, false );
	if ( ! empty( $uploads['basedir'] ) ) {
		$legacy_dir = rtrim( (string) $uploads['basedir'], '/' ) . '/backupino';
		if ( $legacy_dir !== $primary_dir ) {
			backupino_remove_directory( $legacy_dir );
		}
	}
}

if ( is_multisite() ) {
	$backupino_sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $backupino_sites as $backupino_site_id ) {
		switch_to_blog( (int) $backupino_site_id );
		backupino_uninstall_site();
		restore_current_blog();
	}

	delete_site_option( 'backupino_settings' );
} else {
	backupino_uninstall_site();
}
