<?php
/**
 * Admin integration.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Admin;

use Backupino\Core\Capabilities;

/**
 * Registers the admin menu, assets and REST routes.
 */
final class Admin {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'backupino';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'migration_notice' ) );
		add_action( 'rest_api_init', array( new RestController(), 'register_routes' ) );
		DownloadHandler::register();
		add_filter( 'plugin_action_links_' . plugin_basename( BACKUPINO_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Backupino - Free WordPress Backup & Migration', 'backupino' ),
			__( 'Backupino', 'backupino' ),
			Capabilities::manage(),
			self::SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-backup',
			74
		);

		add_submenu_page(
			self::SLUG,
			__( 'Backups', 'backupino' ),
			__( 'Backups', 'backupino' ),
			Capabilities::manage(),
			self::SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'backupino' ),
			__( 'Settings', 'backupino' ),
			Capabilities::manage(),
			self::SLUG . '-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue assets on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'backupino-admin',
			BACKUPINO_URL . 'assets/css/admin.css',
			array(),
			BACKUPINO_VERSION
		);

		wp_enqueue_script(
			'backupino-admin',
			BACKUPINO_URL . 'assets/js/admin.js',
			array(),
			BACKUPINO_VERSION,
			true
		);

		wp_localize_script(
			'backupino-admin',
			'BackupinoData',
			array(
				'restUrl' => esc_url_raw( rest_url( 'backupino/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'isRtl'   => is_rtl(),
				'strings' => array(
					'creating' => __( 'Starting…', 'backupino' ),
					'running'  => __( 'Working…', 'backupino' ),
					'failed'   => __( 'The job failed.', 'backupino' ),
					'confirm'  => __( 'Delete this package permanently?', 'backupino' ),
					'saved'    => __( 'Settings saved.', 'backupino' ),
					'copied'   => __( 'Link copied to the clipboard.', 'backupino' ),
					'notified' => __( 'Test message sent.', 'backupino' ),
					'jobDetails'    => __( 'Backup Job', 'backupino' ),
					'cancelConfirm' => __( 'Cancel the current backup job?', 'backupino' ),
					'cancelled'     => __( 'Backup cancelled.', 'backupino' ),
				),
			)
		);
	}

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! Capabilities::user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage backups.', 'backupino' ) );
		}

		Views::render( 'dashboard' );
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! Capabilities::user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to manage backups.', 'backupino' ) );
		}

		Views::render( 'settings' );
	}

	/**
	 * Add a settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$settings_url     = admin_url( 'admin.php?page=' . self::SLUG . '-settings' );
		$check_update_url = wp_nonce_url(
			admin_url( 'plugins.php?backupino_check_update=1' ),
			'backupino_check_update'
		);

		$new_links = array(
			sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'backupino' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( $check_update_url ), esc_html__( 'Check for updates', 'backupino' ) ),
		);

		return array_merge( $new_links, $links );
	}

	/**
	 * Display migration/restoration success notice if flag is set.
	 *
	 * @return void
	 */
	public function migration_notice(): void {
		if ( '1' !== (string) get_option( 'backupino_migration_notice', '0' ) ) {
			return;
		}

		delete_option( 'backupino_migration_notice' );

		?>
		<div class="notice notice-success is-dismissible" style="padding: 12px 16px; border-left-color: #10b981; border-right-color: #10b981;">
			<p style="font-size: 14px; margin: 0; font-weight: 500;">
				<strong><?php esc_html_e( 'Backupino — Site Migration Completed!', 'backupino' ); ?></strong>
				<br>
				<?php esc_html_e( 'Your website has been successfully restored and migrated to this location. Installer files and temporary data have been cleaned up.', 'backupino' ); ?>
			</p>
		</div>
		<?php
	}
}
