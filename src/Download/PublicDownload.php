<?php
/**
 * Public download endpoint.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Download;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Capabilities;
use Backupino\Plugin;
use Backupino\Support\Bytes;
use Backupino\Support\Dates;
use Backupino\Support\Fs;

/**
 * Serves package downloads through authenticated, permanent URLs.
 */
final class PublicDownload {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_serve' ), 5 );
	}

	/**
	 * Serve the request when the query flag is present.
	 *
	 * @return void
	 */
	public static function maybe_serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing flag, not a state change.
		if ( ! isset( $_GET[ DownloadLink::QUERY ] ) ) {
			return;
		}

		$plugin = Plugin::instance();
		$link   = $plugin->download_link();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key       = isset( $_GET[ DownloadLink::KEY_QUERY ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ DownloadLink::KEY_QUERY ] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$target    = sanitize_file_name( wp_unslash( (string) $_GET[ DownloadLink::QUERY ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$file_type = isset( $_GET['file'] ) ? sanitize_key( wp_unslash( (string) $_GET['file'] ) ) : '';

		$packages_dir = $plugin->paths()->packages();
		$archive_path = null;

		$ip_raw     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
		$rate_key   = 'backupino_dl_fails_' . md5( $ip_raw );
		$fail_count = function_exists( 'get_transient' ) ? (int) get_transient( $rate_key ) : 0;

		if ( $fail_count >= 5 ) {
			wp_die(
				esc_html__( 'Too many failed download attempts. Please try again in 10 minutes.', 'backupino' ),
				esc_html__( 'Too Many Requests', 'backupino' ),
				array( 'response' => 429 )
			);
		}

		if ( 'latest' === $target || '' === $target ) {
			$archive_path = ( new LatestPackage() )->find( $packages_dir );
		} else {
			$clean     = preg_replace( '/\.zip$/i', '', $target );
			$candidate = $packages_dir . '/' . $clean . '.zip';
			if ( is_readable( $candidate ) ) {
				$archive_path = $candidate;
			}
		}

		$policy = new AccessPolicy( $link );

		switch ( $policy->evaluate( $key, is_user_logged_in(), Capabilities::user_can_manage(), null !== $archive_path ) ) {
			case AccessPolicy::LOGIN:
				wp_safe_redirect( $link->login_url( $target, $file_type ) );
				exit;

			case AccessPolicy::FORBIDDEN:
				if ( function_exists( 'set_transient' ) ) {
					$window = defined( 'MINUTE_IN_SECONDS' ) ? 10 * MINUTE_IN_SECONDS : 600;
					set_transient( $rate_key, $fail_count + 1, $window );
				}
				wp_die(
					esc_html__( 'This download link is not valid.', 'backupino' ),
					'',
					array( 'response' => 403 )
				);
				break;

			case AccessPolicy::MISSING:
				wp_die(
					esc_html__( 'There is no backup ready to download yet.', 'backupino' ),
					'',
					array( 'response' => 404 )
				);
				break;
		}

		if ( function_exists( 'delete_transient' ) && $fail_count > 0 ) {
			delete_transient( $rate_key );
		}

		$archive_path = (string) $archive_path;
		$installer_path = preg_replace( '/\.zip$/i', '-installer.php', $archive_path );

		// Direct streaming requests
		if ( 'installer' === $file_type ) {
			$builder = new \Backupino\Backup\InstallerBuilder();
			$code    = $builder->build_for_package( $archive_path, $plugin->settings() );
			if ( '' !== $code ) {
				$plugin->logger()->info(
					'Installer dynamically streamed through download link.',
					array(
						'user' => get_current_user_id(),
						'file' => 'installer.php',
					)
				);
				Fs::stream_string_download( $code, 'installer.php' );
			}

			if ( is_readable( $installer_path ) ) {
				$plugin->logger()->info(
					'Installer downloaded through download link.',
					array(
						'user' => get_current_user_id(),
						'file' => basename( $installer_path ),
					)
				);
				Fs::stream_download( $installer_path, 'installer.php' );
			}
			wp_die( esc_html__( 'Installer file not found.', 'backupino' ), '', array( 'response' => 404 ) );
		}

		if ( 'archive' === $file_type ) {
			$plugin->logger()->info(
				'Package archive downloaded through download link.',
				array(
					'user' => get_current_user_id(),
					'file' => basename( $archive_path ),
				)
			);
			Fs::stream_download( $archive_path );
		}

		// CLI agents like curl or wget get the archive directly
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( false !== stripos( $user_agent, 'curl' ) || false !== stripos( $user_agent, 'wget' ) ) {
			Fs::stream_download( $archive_path );
		}

		// Browser visit without specific file type: render clean download landing page offering BOTH files
		self::render_download_page( $link, $archive_path, $installer_path, $target, $key );
		exit;
	}

	/**
	 * Render the authenticated download landing page with options for both Archive and Installer.
	 *
	 * @param DownloadLink $link           Download link generator.
	 * @param string       $archive_path   Absolute path to the archive zip.
	 * @param string       $installer_path Absolute path to the installer php.
	 * @param string       $target         Target package name or 'latest'.
	 * @param string       $key            Secret key.
	 * @return void
	 */
	private static function render_download_page( DownloadLink $link, string $archive_path, string $installer_path, string $target, string $key ): void {
		$archive_name   = basename( $archive_path );
		$archive_size   = Bytes::format( Fs::size( $archive_path ) );
		$archive_time   = (int) @filemtime( $archive_path );
		$date_formatted = $archive_time > 0 ? Dates::format( $archive_time ) : '&mdash;';

		$archive_url   = $link->url( $target, 'archive' );
		$installer_url = $link->url( $target, 'installer' );

		$is_rtl    = is_rtl();
		$default_tokens = array(
			'primary_color'   => '#0284c7',
			'primary_hover'   => '#0369a1',
			'secondary_color' => '#0f172a',
			'secondary_hover' => '#1e293b',
			'both_color'      => '#16a34a',
			'both_hover'      => '#15803d',
			'bg_color'        => '#f1f5f9',
			'card_bg'         => '#ffffff',
			'text_color'      => '#1e293b',
			'font_family'     => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
		);

		/**
		 * Filter the design tokens for the download landing page.
		 *
		 * @since 1.0.2
		 * @param array<string,string> $tokens Design tokens map.
		 */
		$filtered_tokens = apply_filters( 'backupino_design_tokens', $default_tokens );
		if ( ! is_array( $filtered_tokens ) ) {
			$filtered_tokens = $default_tokens;
		}

		$tokens = array();
		foreach ( $default_tokens as $t_key => $default_val ) {
			$raw_val = isset( $filtered_tokens[ $t_key ] ) ? (string) $filtered_tokens[ $t_key ] : $default_val;
			if ( 'font_family' === $t_key ) {
				$clean = preg_replace( '/[^a-zA-Z0-9\s,\-\'"]/', '', $raw_val );
				$tokens[ $t_key ] = ! empty( $clean ) ? $clean : $default_val;
			} else {
				$hex = sanitize_hex_color( $raw_val );
				$tokens[ $t_key ] = ! empty( $hex ) ? $hex : $default_val;
			}
		}

		$css_vars = sprintf(
			'--bk-primary:%1$s;--bk-primary-hover:%2$s;--bk-secondary:%3$s;--bk-secondary-hover:%4$s;--bk-both:%5$s;--bk-both-hover:%6$s;--bk-bg:%7$s;--bk-card-bg:%8$s;--bk-text:%9$s;--bk-font:%10$s;--primary:%1$s;--primary-hover:%2$s;--secondary:%3$s;--secondary-hover:%4$s;--both:%5$s;--both-hover:%6$s;--bg:%7$s;--card-bg:%8$s;--text:%9$s;--font:%10$s;',
			$tokens['primary_color'],
			$tokens['primary_hover'],
			$tokens['secondary_color'],
			$tokens['secondary_hover'],
			$tokens['both_color'],
			$tokens['both_hover'],
			$tokens['bg_color'],
			$tokens['card_bg'],
			$tokens['text_color'],
			$tokens['font_family']
		);

		/**
		 * Filter additional inline CSS styles for the download landing page.
		 *
		 * @since 1.0.2
		 * @param string $custom_styles Additional CSS code.
		 */
		$custom_styles = (string) apply_filters( 'backupino_download_page_styles', '' );
		$site_name     = get_bloginfo( 'name' );

		$css_url = defined( 'BACKUPINO_URL' )
			? BACKUPINO_URL . 'assets/css/public-download.css'
			: plugins_url( 'assets/css/public-download.css', dirname( dirname( __DIR__ ) ) . '/backupino.php' );
		$js_url  = defined( 'BACKUPINO_URL' )
			? BACKUPINO_URL . 'assets/js/public-download.js'
			: plugins_url( 'assets/js/public-download.js', dirname( dirname( __DIR__ ) ) . '/backupino.php' );

		wp_register_style( 'backupino-public-download', $css_url, array(), defined( 'BACKUPINO_VERSION' ) ? BACKUPINO_VERSION : '1.0.3' );
		$inline_css = ':root { ' . $css_vars . ' } ' . $custom_styles;
		wp_add_inline_style( 'backupino-public-download', $inline_css );
		wp_enqueue_style( 'backupino-public-download' );

		wp_register_script( 'backupino-public-download', $js_url, array(), defined( 'BACKUPINO_VERSION' ) ? BACKUPINO_VERSION : '1.0.3', true );
		wp_enqueue_script( 'backupino-public-download' );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		?>
		<!DOCTYPE html>
		<html dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" lang="<?php echo esc_attr( get_locale() ); ?>">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Download Backup Package', 'backupino' ); ?> &mdash; <?php echo esc_html( $site_name ); ?></title>
			<?php wp_print_styles( 'backupino-public-download' ); ?>
		</head>
		<body>
			<div class="card">
				<div class="logo-badge">
					<span>✓</span>
					<span><?php esc_html_e( 'Backupino Verified Package', 'backupino' ); ?></span>
				</div>
				<h1><?php esc_html_e( 'Download Backup Package', 'backupino' ); ?></h1>
				<p class="subtitle"><?php echo esc_html( $site_name ); ?></p>

				<div class="info-box">
					<div class="info-row">
						<span class="info-label"><?php esc_html_e( 'Package Name', 'backupino' ); ?>:</span>
						<span class="info-value info-value-name" title="<?php echo esc_attr( $archive_name ); ?>"><?php echo esc_html( $archive_name ); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label"><?php esc_html_e( 'File Size', 'backupino' ); ?>:</span>
						<span class="info-value"><?php echo esc_html( $archive_size ); ?></span>
					</div>
					<div class="info-row">
						<span class="info-label"><?php esc_html_e( 'Created Date', 'backupino' ); ?>:</span>
						<span class="info-value"><?php echo esc_html( $date_formatted ); ?></span>
					</div>
				</div>

				<div class="buttons-stack">
					<button type="button" class="btn btn-both" id="backupino-download-both" data-archive-url="<?php echo esc_url( $archive_url ); ?>" data-installer-url="<?php echo esc_url( $installer_url ); ?>">
						<span>📥</span>
						<span><?php esc_html_e( 'Download Archive + Installer (Both)', 'backupino' ); ?></span>
					</button>
					<a href="<?php echo esc_url( $archive_url ); ?>" class="btn btn-primary">
						<span>📦</span>
						<span><?php esc_html_e( 'Download Backup Archive (.zip)', 'backupino' ); ?></span>
					</a>
					<a href="<?php echo esc_url( $installer_url ); ?>" class="btn btn-secondary">
						<span>🛠️</span>
						<span><?php esc_html_e( 'Download Installer (installer.php)', 'backupino' ); ?></span>
					</a>
				</div>

				<div class="instructions">
					<strong>💡 <?php esc_html_e( 'How to restore or migrate:', 'backupino' ); ?></strong>
					<ol style="margin-inline-start: 18px; margin-top: 6px;">
						<li><?php esc_html_e( 'Upload both the .zip archive and installer.php into an empty directory on your destination server.', 'backupino' ); ?></li>
						<li><?php esc_html_e( 'Open installer.php in your web browser and follow the on-screen steps.', 'backupino' ); ?></li>
					</ol>
				</div>

				<div class="card-footer">
					<span><?php esc_html_e( 'Powered by Backupino', 'backupino' ); ?></span>
				</div>
			</div>

			<?php wp_print_scripts( 'backupino-public-download' ); ?>
		</body>
		</html>
		<?php
	}
}
