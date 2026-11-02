<?php
/**
 * Plugin Name:       Backupino
 * Plugin URI:        https://backupino.com
 * Description:       Fast, resumable WordPress backups, cloning and migration with a self-contained installer.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Backupino
 * Author URI:        https://profiles.wordpress.org/backupino/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       backupino
 * Domain Path:       /languages
 *
 * @package Backupino
 */

defined( 'ABSPATH' ) || exit;

define( 'BACKUPINO_VERSION', '1.0.0' );
define( 'BACKUPINO_FILE', __FILE__ );
define( 'BACKUPINO_DIR', plugin_dir_path( __FILE__ ) );
define( 'BACKUPINO_URL', plugin_dir_url( __FILE__ ) );
define( 'BACKUPINO_MIN_PHP', '7.4' );
define( 'BACKUPINO_MIN_WP', '6.0' );

require_once BACKUPINO_DIR . 'src/autoload.php';

/**
 * Register activation and deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'Backupino\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Backupino\\Core\\Deactivator', 'deactivate' ) );

/**
 * Boot the plugin once WordPress is ready.
 *
 * @return \Backupino\Plugin
 */
function backupino() {
	return \Backupino\Plugin::instance();
}

add_action( 'plugins_loaded', 'backupino', 5 );
