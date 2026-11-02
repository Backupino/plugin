<?php
/**
 * Settings template.
 *
 * @package Backupino
 *
 * @var \Backupino\Plugin        $plugin   Plugin container.
 * @var \Backupino\Core\Settings $settings Settings.
 * @var \Backupino\Core\Paths    $paths    Paths.
 */

defined( 'ABSPATH' ) || exit;

$backupino_values = $settings->all();
$backupino_tab    = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'archive';

$backupino_valid_tabs = array(
	'archive'   => array(
		'label' => __( 'Archive & Exclusions', 'backupino' ),
		'icon'  => 'dashicons-media-archive',
	),
	'storage'   => array(
		'label' => __( 'Storage Destination', 'backupino' ),
		'icon'  => 'dashicons-category',
	),
	'notify'    => array(
		'label' => __( 'Bot Notifications', 'backupino' ),
		'icon'  => 'dashicons-bell',
	),
	'schedule'  => array(
		'label' => __( 'Recurring Schedule', 'backupino' ),
		'icon'  => 'dashicons-clock',
	),
	'installer' => array(
		'label' => __( 'Standalone Installer', 'backupino' ),
		'icon'  => 'dashicons-admin-tools',
	),
	'system'    => array(
		'label' => __( 'System Status', 'backupino' ),
		'icon'  => 'dashicons-dashboard',
	),
	'updates'   => array(
		'label' => __( 'Updates & Privacy', 'backupino' ),
		'icon'  => 'dashicons-shield',
	),
);

if ( ! array_key_exists( $backupino_tab, $backupino_valid_tabs ) ) {
	$backupino_tab = 'archive';
}

$backupino_snapshot = \Backupino\Core\Compatibility::snapshot( $paths );
?>
<div class="wrap backupino-wrap" id="backupino-settings-app">
	<!-- Brand Header & Top Navigation Tabs -->
	<header class="backupino-header">
		<div class="backupino-brand">
			<div class="backupino-logo-icon" style="padding: 0; overflow: hidden; background: none; box-shadow: none;">
				<img src="<?php echo esc_url( BACKUPINO_URL . 'assets/images/icon.svg' ); ?>" alt="Backupino" width="40" height="40" style="display: block; width: 40px; height: 40px; border-radius: 8px;">
			</div>
			<div>
				<h1 class="backupino-brand-title">
					<?php esc_html_e( 'Backupino', 'backupino' ); ?>
					<span class="backupino-version-badge"><?php echo esc_html( BACKUPINO_VERSION ); ?> <?php esc_html_e( 'Free', 'backupino' ); ?></span>
				</h1>
			</div>
		</div>

		<nav class="backupino-nav-tabs" aria-label="<?php esc_attr_e( 'Backupino Navigation', 'backupino' ); ?>">
			<a class="backupino-tab-link" href="<?php echo esc_url( \Backupino\Admin\Views::url() ); ?>">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'Backups', 'backupino' ); ?>
			</a>
			<a class="backupino-tab-link is-active" href="<?php echo esc_url( \Backupino\Admin\Views::url( 'settings' ) ); ?>">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'backupino' ); ?>
			</a>
		</nav>
	</header>

	<!-- Two-column Layout: Sidebar first (Right in RTL, Left in LTR), Main second -->
	<div class="backupino-layout">
		<!-- Sidebar Column: Settings Navigation Links -->
		<aside class="backupino-sidebar">
			<section class="backupino-card backupino-nav-card">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-menu-alt3"></span>
						<span><?php esc_html_e( 'Settings Navigation', 'backupino' ); ?></span>
					</h2>
				</div>
				<ul class="backupino-quick-nav">
					<?php foreach ( $backupino_valid_tabs as $backupino_key => $backupino_meta ) : ?>
						<li>
							<a href="<?php echo esc_url( \Backupino\Admin\Views::url( 'settings' ) . '&tab=' . $backupino_key ); ?>"
								class="backupino-nav-item <?php echo $backupino_tab === $backupino_key ? 'is-active' : ''; ?>">
								<span class="dashicons <?php echo esc_attr( $backupino_meta['icon'] ); ?> backupino-nav-icon"></span>
								<span class="backupino-nav-label"><?php echo esc_html( $backupino_meta['label'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<!-- Quick Environment Mini-Widget -->
			<section class="backupino-card">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-admin-site-alt3"></span>
						<span><?php esc_html_e( 'Environment', 'backupino' ); ?></span>
					</h2>
				</div>
				<ul class="backupino-info-list">
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'PHP Version', 'backupino' ); ?></span>
						<span class="backupino-info-value"><?php echo esc_html( PHP_VERSION ); ?></span>
					</li>
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'Memory Limit', 'backupino' ); ?></span>
						<span class="backupino-info-value"><?php echo esc_html( (string) ini_get( 'memory_limit' ) ); ?></span>
					</li>
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'Storage Status', 'backupino' ); ?></span>
						<span class="backupino-info-value" style="color: #16a34a; font-weight: 700;">
							<?php echo $backupino_snapshot['storage_writable'] ? esc_html__( 'Writable', 'backupino' ) : esc_html__( 'Protected', 'backupino' ); ?>
						</span>
					</li>
				</ul>
			</section>
		</aside>

		<!-- Main Column: Dedicated View for Current Tab -->
		<main class="backupino-main">
			<?php if ( 'system' === $backupino_tab ) : ?>
				<!-- DEDICATED SYSTEM STATUS & DIAGNOSTICS VIEW -->
				<section class="backupino-card">
					<div class="backupino-card-header">
						<h2 class="backupino-card-title">
							<span class="dashicons dashicons-dashboard"></span>
							<span><?php esc_html_e( 'System Status & Diagnostics', 'backupino' ); ?></span>
						</h2>
					</div>
					<p class="description">
						<?php esc_html_e( 'Live diagnostic health report of active server extensions, filesystem permissions, and system stability.', 'backupino' ); ?>
					</p>

					<!-- Health Metric Grid Cards -->
					<div class="backupino-system-grid">
						<div class="backupino-system-metric">
							<div class="metric-icon"><span class="dashicons dashicons-admin-generic"></span></div>
							<div class="metric-info">
								<span class="metric-label"><?php esc_html_e( 'PHP Version', 'backupino' ); ?></span>
								<span class="metric-val"><?php echo esc_html( PHP_VERSION ); ?></span>
							</div>
							<span class="metric-badge is-ok">✓ <?php esc_html_e( 'Compatible', 'backupino' ); ?></span>
						</div>

						<div class="backupino-system-metric">
							<div class="metric-icon"><span class="dashicons dashicons-performance"></span></div>
							<div class="metric-info">
								<span class="metric-label"><?php esc_html_e( 'Memory Limit', 'backupino' ); ?></span>
								<span class="metric-val"><?php echo esc_html( (string) ini_get( 'memory_limit' ) ); ?></span>
							</div>
							<span class="metric-badge is-ok">✓ <?php esc_html_e( 'OK', 'backupino' ); ?></span>
						</div>

						<div class="backupino-system-metric">
							<div class="metric-icon"><span class="dashicons dashicons-clock"></span></div>
							<div class="metric-info">
								<span class="metric-label"><?php esc_html_e( 'Execution Timeout', 'backupino' ); ?></span>
								<span class="metric-val"><?php echo esc_html( (string) ini_get( 'max_execution_time' ) ); ?>s</span>
							</div>
							<span class="metric-badge is-ok">✓ <?php esc_html_e( 'OK', 'backupino' ); ?></span>
						</div>

						<div class="backupino-system-metric">
							<div class="metric-icon"><span class="dashicons dashicons-media-archive"></span></div>
							<div class="metric-info">
								<span class="metric-label"><?php esc_html_e( 'ZipArchive', 'backupino' ); ?></span>
								<span class="metric-val"><?php echo class_exists( 'ZipArchive' ) ? esc_html__( 'Enabled', 'backupino' ) : esc_html__( 'PclZip fallback', 'backupino' ); ?></span>
							</div>
							<span class="metric-badge is-ok">✓ <?php esc_html_e( 'Ready', 'backupino' ); ?></span>
						</div>

						<div class="backupino-system-metric">
							<div class="metric-icon"><span class="dashicons dashicons-database"></span></div>
							<div class="metric-info">
								<span class="metric-label"><?php esc_html_e( 'Database Driver', 'backupino' ); ?></span>
								<span class="metric-val"><?php echo esc_html( extension_loaded( 'mysqli' ) ? 'MySQLi' : 'PDO' ); ?></span>
							</div>
							<span class="metric-badge is-ok">✓ <?php esc_html_e( 'Streaming', 'backupino' ); ?></span>
						</div>

						<div class="backupino-system-metric">
							<div class="metric-icon"><span class="dashicons dashicons-lock"></span></div>
							<div class="metric-info">
								<span class="metric-label"><?php esc_html_e( 'OpenSSL', 'backupino' ); ?></span>
								<span class="metric-val"><?php echo extension_loaded( 'openssl' ) ? esc_html__( 'Active', 'backupino' ) : esc_html__( 'Inactive', 'backupino' ); ?></span>
							</div>
							<span class="metric-badge is-ok">✓ <?php esc_html_e( 'Encrypted', 'backupino' ); ?></span>
						</div>
					</div>

					<!-- Storage Path Diagnostics -->
					<div class="backupino-system-storage-card" style="margin-top: 20px;">
						<h3 class="backupino-sub-title">
							<span class="dashicons dashicons-portfolio"></span>
							<span><?php esc_html_e( 'Backup Storage Destination', 'backupino' ); ?></span>
						</h3>
						<p class="description">
							<?php esc_html_e( 'Isolated, protected path used to safely store archive files away from public access:', 'backupino' ); ?>
						</p>
						<div class="backupino-path-box">
							<code><?php echo esc_html( $paths->packages() ); ?></code>
							<button type="button" class="button backupino-btn" id="backupino-test-storage">
								<span class="dashicons dashicons-yes-alt"></span>
								<span><?php esc_html_e( 'Run Storage Write Test', 'backupino' ); ?></span>
							</button>
						</div>
						<p class="backupino-result" id="backupino-storage-result" style="margin-top: 8px;"></p>
					</div>

					<!-- Real-time Activity & Diagnostics Log Viewer -->
					<div class="backupino-system-log-card" style="margin-top: 24px;">
						<div class="backupino-log-header">
							<h3 class="backupino-sub-title" style="margin: 0;">
								<span class="dashicons dashicons-format-aside"></span>
								<span><?php esc_html_e( 'System & Activity Log Stream', 'backupino' ); ?></span>
							</h3>
							<button type="button" class="button button-small backupino-btn" id="backupino-refresh-log">
								<span class="dashicons dashicons-update"></span>
								<span><?php esc_html_e( 'Refresh', 'backupino' ); ?></span>
							</button>
						</div>
						<div class="backupino-terminal-wrap" style="margin-top: 10px;">
							<div class="backupino-terminal-header">
								<div class="backupino-terminal-dots">
									<span class="dot red"></span>
									<span class="dot yellow"></span>
									<span class="dot green"></span>
								</div>
								<span class="backupino-terminal-title"><?php esc_html_e( 'System Activity Log (Last 100 entries)', 'backupino' ); ?></span>
							</div>
							<div class="backupino-terminal-body" id="backupino-system-log" style="max-height: 340px; overflow-y: auto;">
								<?php
								$backupino_log_raw   = $plugin->logger()->tail( 100 );
								$backupino_log_lines = '' !== trim( $backupino_log_raw ) ? explode( "\n", trim( $backupino_log_raw ) ) : array();
								if ( array() === $backupino_log_lines ) :
									?>
									<div class="backupino-log-line init"><?php esc_html_e( 'No recent log entries found.', 'backupino' ); ?></div>
								<?php else : ?>
									<?php foreach ( $backupino_log_lines as $backupino_line ) : ?>
										<div class="backupino-log-line">
											<span class="log-msg"><?php echo esc_html( $backupino_line ); ?></span>
										</div>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</section>

			<?php else : ?>

				<!-- FORM VIEW FOR CONFIGURATION TABS -->
				<form id="backupino-settings" class="backupino-card">

					<?php if ( 'archive' === $backupino_tab ) : ?>
						<!-- Tab 1: Archive & Exclusions -->
						<div class="backupino-section" id="section-archive">
							<h2 class="backupino-section-title">
								<span class="dashicons dashicons-media-archive"></span>
								<span><?php esc_html_e( 'Archive & Exclusions', 'backupino' ); ?></span>
							</h2>
							<p class="description">
								<?php esc_html_e( 'Configure which files, folders, or sizes should be skipped during backup creation to save disk space and maximize speed.', 'backupino' ); ?>
							</p>

							<div class="backupino-field-group">
								<label class="backupino-label">
									<span class="backupino-label-text"><?php esc_html_e( 'Excluded paths and patterns (one per line)', 'backupino' ); ?></span>
									<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Folders or file patterns that will be excluded from the backup archive (e.g. wp-content/cache/, *.mp4).', 'backupino' ); ?>" tabindex="0">?</span>
								</label>
								<textarea name="archive.exclude_rules" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $backupino_values['archive']['exclude_rules'] ) ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Examples: wp-content/uploads/2022/, *.mp4, node_modules/. Cache and backup folders are always skipped automatically.', 'backupino' ); ?>
								</p>
							</div>

							<div class="backupino-grid">
								<div class="backupino-field-group">
									<label class="backupino-label">
										<span class="backupino-label-text"><?php esc_html_e( 'Skip files larger than (MB, 0 = no limit)', 'backupino' ); ?></span>
										<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Files exceeding this size threshold will be skipped to keep backups fast and reliable. Enter 0 for no limit.', 'backupino' ); ?>" tabindex="0">?</span>
									</label>
									<input type="number" min="0" step="1" name="archive.ignore_larger_than_mb"
										value="<?php echo esc_attr( (string) ( (int) $backupino_values['archive']['ignore_larger_than'] / 1048576 ) ); ?>">
								</div>

								<div class="backupino-field-group">
									<label class="backupino-label">
										<span class="backupino-label-text"><?php esc_html_e( 'Files packed per request', 'backupino' ); ?></span>
										<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Batch size for slicing file packing per tick. Default 1000 files ensures fast backup on modern systems.', 'backupino' ); ?>" tabindex="0">?</span>
									</label>
									<input type="number" min="10" max="5000" name="runtime.files_per_tick"
										value="<?php echo esc_attr( (string) $backupino_values['runtime']['files_per_tick'] ); ?>">
								</div>
							</div>
						</div>

					<?php elseif ( 'storage' === $backupino_tab ) : ?>
						<!-- Tab 2: Storage Destination -->
						<div class="backupino-section" id="section-storage">
							<h2 class="backupino-section-title">
								<span class="dashicons dashicons-category"></span>
								<span><?php esc_html_e( 'Storage Destination', 'backupino' ); ?></span>
							</h2>
							<p class="description">
								<?php esc_html_e( 'Backup archives and standalone installers are stored locally in a secure, protected directory on your server.', 'backupino' ); ?>
							</p>

							<div class="backupino-field-group">
								<label class="backupino-label">
									<span class="backupino-label-text"><?php esc_html_e( 'Primary Destination Driver', 'backupino' ); ?></span>
									<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Primary storage destination for completed packages. In the Free edition, local protected storage is used.', 'backupino' ); ?>" tabindex="0">?</span>
								</label>
								<select name="storage.driver" id="backupino-driver">
									<?php foreach ( $plugin->storages()->available() as $backupino_driver => $backupino_label ) : ?>
										<option value="<?php echo esc_attr( (string) $backupino_driver ); ?>"
											<?php selected( (string) $backupino_driver, (string) $backupino_values['storage']['driver'] ); ?>>
											<?php echo esc_html( (string) $backupino_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="backupino-field-group" style="margin-top: 16px;">
								<label class="backupino-label">
									<span class="backupino-label-text"><?php esc_html_e( 'Protected Directory Path', 'backupino' ); ?></span>
								</label>
								<input type="text" readonly class="large-text code" value="<?php echo esc_attr( $paths->packages() ); ?>">
								<p class="description">
									<?php esc_html_e( 'This location contains an .htaccess file blocking direct HTTP requests and indexing for maximum security.', 'backupino' ); ?>
								</p>
							</div>
						</div>

					<?php elseif ( 'notify' === $backupino_tab ) : ?>
						<!-- Tab 3: Bot Notifications -->
						<div class="backupino-section" id="section-notify">
							<h2 class="backupino-section-title">
								<span class="dashicons dashicons-bell"></span>
								<span><?php esc_html_e( 'Bot Notifications', 'backupino' ); ?></span>
							</h2>
							<p class="description">
								<?php esc_html_e( 'Backupino can announce every finished backup, with the permanent download link, in Telegram or Bale. You can enable either or both messengers simultaneously.', 'backupino' ); ?>
							</p>

							<!-- Telegram Settings Card -->
							<div class="backupino-card" style="margin-top: 16px; padding: 18px; border: 1px solid var(--backupino-border); border-radius: 10px; background: #fff;">
								<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
									<h3 style="margin: 0; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
										<span class="dashicons dashicons-format-chat" style="color: #0284c7;"></span>
										<span><?php esc_html_e( 'Telegram Notification', 'backupino' ); ?></span>
									</h3>
									<label class="backupino-check" style="margin: 0;">
										<input type="checkbox" name="notify.telegram_enabled" value="1" <?php checked( ! empty( $backupino_values['notify']['telegram_enabled'] ) ); ?>>
										<span class="backupino-check-label"><?php esc_html_e( 'Enable Telegram', 'backupino' ); ?></span>
									</label>
								</div>
								<div class="backupino-grid">
									<div class="backupino-field-group">
										<label class="backupino-label">
											<span class="backupino-label-text"><?php esc_html_e( 'Telegram Bot Token', 'backupino' ); ?></span>
											<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Bot token obtained from Telegram @BotFather.', 'backupino' ); ?>" tabindex="0">?</span>
										</label>
										<input type="password" name="notify.telegram_token" autocomplete="off"
											placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
											value="<?php echo esc_attr( (string) ( $backupino_values['notify']['telegram_token'] ?? '' ) ); ?>">
									</div>
									<div class="backupino-field-group">
										<label class="backupino-label">
											<span class="backupino-label-text"><?php esc_html_e( 'Telegram Chat ID', 'backupino' ); ?></span>
											<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Telegram numeric chat or channel ID.', 'backupino' ); ?>" tabindex="0">?</span>
										</label>
										<input type="text" name="notify.telegram_chat_id"
											placeholder="-1001234567890"
											value="<?php echo esc_attr( (string) ( $backupino_values['notify']['telegram_chat_id'] ?? '' ) ); ?>">
									</div>
								</div>
								<div style="margin-top: 12px; display: flex; align-items: center; gap: 10px;">
									<button type="button" class="button backupino-btn backupino-test-single-notify" data-service="telegram" id="backupino-notify-test-telegram">
										<span class="dashicons dashicons-email-alt" style="vertical-align: middle;"></span>
										<span><?php esc_html_e( 'Test Telegram', 'backupino' ); ?></span>
									</button>
									<span class="backupino-result" id="backupino-notify-telegram-result"></span>
								</div>
							</div>

							<!-- Bale Settings Card -->
							<div class="backupino-card" style="margin-top: 20px; padding: 18px; border: 1px solid var(--backupino-border); border-radius: 10px; background: #fff;">
								<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
									<h3 style="margin: 0; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
										<span class="dashicons dashicons-format-chat" style="color: #059669;"></span>
										<span><?php esc_html_e( 'Bale Notification', 'backupino' ); ?></span>
									</h3>
									<label class="backupino-check" style="margin: 0;">
										<input type="checkbox" name="notify.bale_enabled" value="1" <?php checked( ! empty( $backupino_values['notify']['bale_enabled'] ) ); ?>>
										<span class="backupino-check-label"><?php esc_html_e( 'Enable Bale', 'backupino' ); ?></span>
									</label>
								</div>
								<div class="backupino-grid">
									<div class="backupino-field-group">
										<label class="backupino-label">
											<span class="backupino-label-text"><?php esc_html_e( 'Bale Bot Token', 'backupino' ); ?></span>
											<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Bot token obtained from Bale @BotFather.', 'backupino' ); ?>" tabindex="0">?</span>
										</label>
										<input type="password" name="notify.bale_token" autocomplete="off"
											placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
											value="<?php echo esc_attr( (string) ( $backupino_values['notify']['bale_token'] ?? '' ) ); ?>">
									</div>
									<div class="backupino-field-group">
										<label class="backupino-label">
											<span class="backupino-label-text"><?php esc_html_e( 'Bale Chat ID', 'backupino' ); ?></span>
											<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Bale numeric chat or channel ID.', 'backupino' ); ?>" tabindex="0">?</span>
										</label>
										<input type="text" name="notify.bale_chat_id"
											placeholder="123456789"
											value="<?php echo esc_attr( (string) ( $backupino_values['notify']['bale_chat_id'] ?? '' ) ); ?>">
									</div>
								</div>
								<div style="margin-top: 12px; display: flex; align-items: center; gap: 10px;">
									<button type="button" class="button backupino-btn backupino-test-single-notify" data-service="bale" id="backupino-notify-test-bale">
										<span class="dashicons dashicons-email-alt" style="vertical-align: middle;"></span>
										<span><?php esc_html_e( 'Test Bale', 'backupino' ); ?></span>
									</button>
									<span class="backupino-result" id="backupino-notify-bale-result"></span>
								</div>
							</div>

							<!-- Backup Message Template Card -->
							<div class="backupino-card" style="margin-top: 20px; padding: 18px; border: 1px solid var(--backupino-border); border-radius: 10px; background: #fff;">
								<h3 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-format-aside" style="color: #6366f1;"></span>
									<span><?php esc_html_e( 'Backup Message Template', 'backupino' ); ?></span>
								</h3>
								<p class="description" style="margin-bottom: 12px;">
									<?php esc_html_e( 'Message template sent to messengers when a backup finishes.', 'backupino' ); ?>
								</p>
								<div class="backupino-field-group">
									<?php
									$backup_tpl_val = ! empty( $backupino_values['notify']['msg_backup_template'] )
										? (string) $backupino_values['notify']['msg_backup_template']
										: \Backupino\Notify\Message::default_backup_template();
									?>
									<textarea name="notify.msg_backup_template" rows="5" class="large-text" style="font-family: inherit; font-size: 13px; line-height: 1.6;" placeholder="<?php echo esc_attr( \Backupino\Notify\Message::default_backup_template() ); ?>"><?php echo esc_textarea( $backup_tpl_val ); ?></textarea>
								</div>
								<div style="margin-top: 10px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12.5px;">
									<strong style="color: #334155; display: block; margin-bottom: 6px;"><?php esc_html_e( 'Available tags:', 'backupino' ); ?></strong>
									<div style="display: flex; flex-wrap: wrap; gap: 8px 16px;">
										<span><code>{site}</code> : <?php esc_html_e( 'Site Name', 'backupino' ); ?></span>
										<span><code>{kind}</code> : <?php esc_html_e( 'Backup Contents', 'backupino' ); ?></span>
										<span><code>{size}</code> : <?php esc_html_e( 'Backup Size', 'backupino' ); ?></span>
										<span><code>{date}</code> : <?php esc_html_e( 'Backup Date', 'backupino' ); ?></span>
										<span><code>{link}</code> : <?php esc_html_e( 'Download Link', 'backupino' ); ?></span>
									</div>
									<div style="margin-top: 6px; color: #64748b; font-size: 11.5px;">
										<?php esc_html_e( 'HTML formatting tags such as <b>bold</b> and <a href="...">links</a> are supported.', 'backupino' ); ?>
									</div>
								</div>
							</div>

							<!-- Restoration Message Template Card -->
							<div class="backupino-card" style="margin-top: 20px; padding: 18px; border: 1px solid var(--backupino-border); border-radius: 10px; background: #fff;">
								<h3 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
									<span class="dashicons dashicons-update" style="color: #10b981;"></span>
									<span><?php esc_html_e( 'Restoration Message Template', 'backupino' ); ?></span>
								</h3>
								<p class="description" style="margin-bottom: 12px;">
									<?php esc_html_e( 'Message template sent to messengers when the site is restored using the installer.', 'backupino' ); ?>
								</p>
								<div class="backupino-field-group">
									<?php
									$restore_tpl_val = ! empty( $backupino_values['notify']['msg_restore_template'] )
										? (string) $backupino_values['notify']['msg_restore_template']
										: \Backupino\Notify\Message::default_restore_template();
									?>
									<textarea name="notify.msg_restore_template" rows="5" class="large-text" style="font-family: inherit; font-size: 13px; line-height: 1.6;" placeholder="<?php echo esc_attr( \Backupino\Notify\Message::default_restore_template() ); ?>"><?php echo esc_textarea( $restore_tpl_val ); ?></textarea>
								</div>
								<div style="margin-top: 10px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12.5px;">
									<strong style="color: #334155; display: block; margin-bottom: 6px;"><?php esc_html_e( 'Available tags:', 'backupino' ); ?></strong>
									<div style="display: flex; flex-wrap: wrap; gap: 8px 16px;">
										<span><code>{site}</code> : <?php esc_html_e( 'Site Name', 'backupino' ); ?></span>
										<span><code>{url}</code> : <?php esc_html_e( 'New Site URL', 'backupino' ); ?></span>
										<span><code>{admin_url}</code> : <?php esc_html_e( 'Admin Login URL', 'backupino' ); ?></span>
										<span><code>{date}</code> : <?php esc_html_e( 'Restoration Date', 'backupino' ); ?></span>
									</div>
									<div style="margin-top: 6px; color: #64748b; font-size: 11.5px;">
										<?php esc_html_e( 'HTML formatting tags such as <b>bold</b> and <a href="...">links</a> are supported.', 'backupino' ); ?>
									</div>
								</div>
							</div>

							<?php $backupino_queued = $plugin->notifier()->pending(); ?>
							<?php if ( $backupino_queued > 0 ) : ?>
								<p class="description" style="margin-top: 12px; color: #b45309;">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: number of queued messages. */
											_n( '%d message is waiting to be resent on the next scheduled run.', '%d messages are waiting to be resent on the next scheduled run.', $backupino_queued, 'backupino' ),
											$backupino_queued
										)
									);
									?>
								</p>
							<?php endif; ?>
						</div>

					<?php elseif ( 'schedule' === $backupino_tab ) : ?>
						<!-- Tab 4: Recurring Schedule -->
						<div class="backupino-section" id="section-schedule">
							<h2 class="backupino-section-title">
								<span class="dashicons dashicons-clock"></span>
								<span><?php esc_html_e( 'Recurring Schedule', 'backupino' ); ?></span>
							</h2>
							<p class="description">
								<?php esc_html_e( 'Automatic recurring backups trigger via WordPress cron. In Persian locale, monthly schedules automatically align with the start of the Jalali month.', 'backupino' ); ?>
							</p>

							<label class="backupino-check" style="margin-bottom: 14px;">
								<input type="checkbox" name="schedule.enabled" <?php checked( (bool) $backupino_values['schedule']['enabled'] ); ?>>
								<span class="backupino-check-label"><?php esc_html_e( 'Run automatic backups', 'backupino' ); ?></span>
								<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Enables recurring background backup jobs using WordPress built-in WP-Cron scheduler.', 'backupino' ); ?>" tabindex="0">?</span>
							</label>

							<div class="backupino-grid">
								<div class="backupino-field-group">
									<label class="backupino-label">
										<span class="backupino-label-text"><?php esc_html_e( 'Frequency', 'backupino' ); ?></span>
										<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'In Free edition, monthly execution triggers on the 1st of each Persian/Gregorian month based on site language.', 'backupino' ); ?>" tabindex="0">?</span>
									</label>
									<select name="schedule.interval">
										<?php foreach ( (array) apply_filters( 'backupino_schedule_intervals_labels', array( 'monthly' => __( 'Monthly (first day of the month)', 'backupino' ) ) ) as $backupino_interval => $backupino_interval_label ) : ?>
											<option value="<?php echo esc_attr( $backupino_interval ); ?>" <?php selected( $backupino_interval, $backupino_values['schedule']['interval'] ); ?>>
												<?php echo esc_html( is_string( $backupino_interval_label ) ? $backupino_interval_label : $backupino_interval ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="backupino-field-group">
									<label class="backupino-label">
										<span class="backupino-label-text"><?php esc_html_e( 'Keep at most (backups)', 'backupino' ); ?></span>
										<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Maximum number of recent backups to keep on disk. Older packages are automatically pruned to save space.', 'backupino' ); ?>" tabindex="0">?</span>
									</label>
									<input type="number" min="1" max="100" name="schedule.keep" value="<?php echo esc_attr( (string) $backupino_values['schedule']['keep'] ); ?>">
								</div>
							</div>
						</div>

					<?php elseif ( 'installer' === $backupino_tab ) : ?>
						<!-- Tab 5: Standalone Installer Configuration -->
						<div class="backupino-section" id="section-installer">
							<h2 class="backupino-section-title">
								<span class="dashicons dashicons-admin-tools"></span>
								<span><?php esc_html_e( 'Standalone Installer', 'backupino' ); ?></span>
							</h2>
							<p class="description">
								<?php esc_html_e( 'Configure preset credentials for the standalone migration installer generated with each backup.', 'backupino' ); ?>
							</p>

							<div class="backupino-grid">
								<div class="backupino-field-group">
									<label class="backupino-label">
										<span class="backupino-label-text"><?php esc_html_e( 'Installer password (optional)', 'backupino' ); ?></span>
										<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Password protecting installer.php when accessed via browser during migration or restoration.', 'backupino' ); ?>" tabindex="0">?</span>
									</label>
									<input type="text" name="installer.password" value="<?php echo esc_attr( (string) $backupino_values['installer']['password'] ); ?>">
								</div>

								<div class="backupino-field-group">
									<label class="backupino-label">
										<span class="backupino-label-text"><?php esc_html_e( 'Default new URL (optional)', 'backupino' ); ?></span>
										<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Preset target URL for search & replace when migrating to a known new domain.', 'backupino' ); ?>" tabindex="0">?</span>
									</label>
									<input type="url" name="installer.new_url" value="<?php echo esc_attr( (string) $backupino_values['installer']['new_url'] ); ?>">
								</div>
							</div>
						</div>

					<?php elseif ( 'updates' === $backupino_tab ) : ?>
						<!-- Tab 6: Updates & Community -->
						<div class="backupino-section" id="section-updates">
							<h2 class="backupino-section-title">
								<span class="dashicons dashicons-shield"></span>
								<span><?php esc_html_e( 'Updates & Privacy', 'backupino' ); ?></span>
							</h2>
							<label class="backupino-check">
								<input type="checkbox" name="advanced.opt_in_news" <?php checked( (bool) ( $backupino_values['advanced']['opt_in_news'] ?? false ) ); ?>>
								<span class="backupino-check-label"><?php esc_html_e( 'Receive critical security notices, product updates, and roadmap announcements (opt-in).', 'backupino' ); ?></span>
								<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( '100% privacy-compliant: zero tracking, zero telemetry, strictly optional notifications.', 'backupino' ); ?>" tabindex="0">?</span>
							</label>
							<p class="description">
								<?php esc_html_e( 'We respect your privacy. No telemetry or personal data is collected or sent without your explicit consent.', 'backupino' ); ?>
							</p>

							<div class="backupino-field-group" style="margin-top: 24px; padding: 18px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
								<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
									<div style="display: flex; align-items: center; gap: 10px;">
										<span class="dashicons dashicons-update" style="font-size: 22px; color: #0284c7;"></span>
										<div>
											<strong style="display: block; font-size: 14px; margin-bottom: 2px;"><?php esc_html_e( 'Current Version:', 'backupino' ); ?> <?php echo esc_html( BACKUPINO_VERSION ); ?></strong>
											<span style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Check GitHub releases for the latest version and updates.', 'backupino' ); ?></span>
										</div>
									</div>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=backupino-settings&tab=updates&backupino_check_update=1' ), 'backupino_check_update' ) ); ?>" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 500;">
										<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span>
										<span><?php esc_html_e( 'Check for updates', 'backupino' ); ?></span>
									</a>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<!-- Universal Save Bar for Configuration Tabs -->
					<div class="backupino-save-bar">
						<button type="submit" class="button button-primary button-hero backupino-btn-primary">
							<span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
							<span><?php esc_html_e( 'Save settings', 'backupino' ); ?></span>
						</button>
						<span class="backupino-result" id="backupino-settings-result"></span>
					</div>
				</form>
			<?php endif; ?>
		</main>
	</div>
</div>