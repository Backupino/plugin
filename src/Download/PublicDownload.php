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

		if ( function_exists( 'backupino' ) ) {
			backupino()->load_textdomain();
		}

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

		/**
		 * Filter additional inline CSS styles for the download landing page.
		 *
		 * @since 1.0.2
		 * @param string $custom_styles Additional CSS code.
		 */
		$custom_styles = (string) apply_filters( 'backupino_download_page_styles', '' );
		$site_name = get_bloginfo( 'name' );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		?>
		<!DOCTYPE html>
		<html dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" lang="<?php echo esc_attr( get_locale() ); ?>">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Download Backup Package', 'backupino' ); ?> &mdash; <?php echo esc_html( $site_name ); ?></title>
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone public landing page without WordPress theme ?>
			<style>
				:root {
					--bk-primary: <?php echo esc_attr( $tokens['primary_color'] ); ?>;
					--bk-primary-hover: <?php echo esc_attr( $tokens['primary_hover'] ); ?>;
					--bk-secondary: <?php echo esc_attr( $tokens['secondary_color'] ); ?>;
					--bk-secondary-hover: <?php echo esc_attr( $tokens['secondary_hover'] ); ?>;
					--bk-both: <?php echo esc_attr( $tokens['both_color'] ); ?>;
					--bk-both-hover: <?php echo esc_attr( $tokens['both_hover'] ); ?>;
					--bk-bg: <?php echo esc_attr( $tokens['bg_color'] ); ?>;
					--bk-card-bg: <?php echo esc_attr( $tokens['card_bg'] ); ?>;
					--bk-text: <?php echo esc_attr( $tokens['text_color'] ); ?>;
					--bk-font: <?php echo esc_attr( $tokens['font_family'] ); ?>;
				}
				*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
				body {
					font-family: var(--bk-font);
					background: var(--bk-bg);
					color: var(--bk-text);
					display: flex;
					align-items: center;
					justify-content: center;
					min-height: 100vh;
					padding: 24px;
				}
				.card {
					background: var(--bk-card-bg);
					border: 1px solid #e2e8f0;
					border-radius: 12px;
					box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -2px rgba(0,0,0,0.05);
					max-width: 540px;
					width: 100%;
					padding: 32px;
					text-align: center;
				}
				.logo-badge {
					display: inline-flex;
					align-items: center;
					gap: 8px;
					background: #f0fdf4;
					border: 1px solid #bbf7d0;
					color: #166534;
					font-size: 13px;
					font-weight: 600;
					padding: 6px 14px;
					border-radius: 9999px;
					margin-bottom: 20px;
				}
				h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
				p.subtitle { font-size: 14px; color: #64748b; margin-bottom: 24px; }
				.info-box {
					background: #f8fafc;
					border: 1px solid #e2e8f0;
					border-radius: 8px;
					padding: 16px;
					margin-bottom: 24px;
					text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
					font-size: 13.5px;
				}
				.info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e2e8f0; }
				.info-row:last-child { border-bottom: none; }
				.info-label { color: #64748b; }
				.info-value { font-weight: 600; color: #1e293b; }
				.buttons-stack { display: flex; flex-direction: column; gap: 12px; }
				.btn {
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 8px;
					padding: 13px 20px;
					border-radius: 8px;
					font-size: 14px;
					font-weight: 600;
					text-decoration: none;
					cursor: pointer;
					border: none;
					transition: all 0.15s ease;
				}
				.btn-primary { background: var(--bk-primary); color: #ffffff; }
				.btn-primary:hover { background: var(--bk-primary-hover); }
				.btn-secondary { background: var(--bk-secondary); color: #ffffff; }
				.btn-secondary:hover { background: var(--bk-secondary-hover); }
				.btn-both { background: var(--bk-both); color: #ffffff; }
				.btn-both:hover { background: var(--bk-both-hover); }
				.instructions {
					margin-top: 24px;
					padding-top: 20px;
					border-top: 1px solid #f1f5f9;
					font-size: 12.5px;
					color: #64748b;
					line-height: 1.6;
					text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
				}
				<?php if ( '' !== $custom_styles ) : ?>
				<?php echo wp_strip_all_tags( $custom_styles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</style>
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
						<span class="info-value"><?php echo esc_html( $archive_name ); ?></span>
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
					<button type="button" class="btn btn-both" onclick="downloadBoth()">
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
			</div>

			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone public landing page without WordPress theme ?>
			<script>
				function downloadBoth() {
					var f1 = document.createElement('iframe');
					f1.style.display = 'none';
					f1.src = <?php echo wp_json_encode( $installer_url ); ?>;
					document.body.appendChild(f1);

					setTimeout(function() {
						var f2 = document.createElement('iframe');
						f2.style.display = 'none';
						f2.src = <?php echo wp_json_encode( $archive_url ); ?>;
						document.body.appendChild(f2);
					}, 800);
				}
			</script>
		</body>
		</html>
		<?php
	}
}
