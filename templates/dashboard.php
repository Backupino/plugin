<?php
/**
 * Dashboard template.
 *
 * @package Backupino
 *
 * @var \Backupino\Plugin               $plugin   Plugin container.
 * @var \Backupino\Core\Settings        $settings Settings.
 * @var \Backupino\Core\Paths           $paths    Paths.
 * @var array<int, \Backupino\Jobs\Job> $jobs     Jobs.
 */

defined( 'ABSPATH' ) || exit;

$backupino_issues = \Backupino\Core\Compatibility::warnings();

$backupino_running_job = null;
foreach ( $jobs as $backupino_j ) {
	if ( $backupino_j->is_runnable() ) {
		$backupino_running_job = $backupino_j;
		break;
	}
}
?>
<div class="wrap backupino-wrap" id="backupino-app">
	<!-- Brand Header & Navigation Tabs -->
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
			<a class="backupino-tab-link is-active" href="<?php echo esc_url( \Backupino\Admin\Views::url() ); ?>">
				<span class="dashicons dashicons-database"></span>
				<?php esc_html_e( 'Backups', 'backupino' ); ?>
			</a>
			<a class="backupino-tab-link" href="<?php echo esc_url( \Backupino\Admin\Views::url( 'settings' ) ); ?>">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Settings', 'backupino' ); ?>
			</a>
		</nav>
	</header>

	<?php if ( array() !== $backupino_issues ) : ?>
		<div class="notice notice-warning inline">
			<ul>
				<?php foreach ( $backupino_issues as $backupino_issue ) : ?>
					<li><?php echo esc_html( $backupino_issue ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<!-- Two-column Layout: Sidebar first (Right in RTL, Left in LTR), Main second -->
	<div class="backupino-layout">
		<!-- Sidebar Column: Narrower navigation / quick cards -->
		<aside class="backupino-sidebar">
			<!-- Permanent Download Link Card -->
			<section class="backupino-card">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-admin-links"></span>
						<span><?php esc_html_e( 'Permanent download link', 'backupino' ); ?></span>
						<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Direct link that always serves the newest completed backup. Requires administrator login before download.', 'backupino' ); ?>" tabindex="0">?</span>
					</h2>
				</div>
				<p class="description">
					<?php esc_html_e( 'This link always points at the newest backup. It never expires, but the visitor must be signed in as an administrator before the download starts.', 'backupino' ); ?>
				</p>
				<div class="backupino-copy">
					<input type="text" readonly class="large-text code" id="backupino-permalink"
						value="<?php echo esc_attr( $plugin->download_link()->url() ); ?>">
					<button type="button" class="button backupino-btn" id="backupino-copy-link">
						<?php esc_html_e( 'Copy link', 'backupino' ); ?>
					</button>
				</div>
				<p class="backupino-result" id="backupino-copy-result"></p>
			</section>

			<!-- Storage & Environment Summary Card -->
			<section class="backupino-card">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-admin-generic"></span>
						<span><?php esc_html_e( 'Storage & Environment', 'backupino' ); ?></span>
					</h2>
				</div>
				<ul class="backupino-info-list">
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'Destination', 'backupino' ); ?></span>
						<span class="backupino-info-value"><?php echo esc_html( $plugin->storages()->driver()->label() ); ?></span>
					</li>
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'PHP Version', 'backupino' ); ?></span>
						<span class="backupino-info-value"><?php echo esc_html( PHP_VERSION ); ?></span>
					</li>
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'Memory Limit', 'backupino' ); ?></span>
						<span class="backupino-info-value"><?php echo esc_html( (string) ini_get( 'memory_limit' ) ); ?></span>
					</li>
					<li>
						<span class="backupino-info-label"><?php esc_html_e( 'Time Limit', 'backupino' ); ?></span>
						<span class="backupino-info-value"><?php echo esc_html( (string) ini_get( 'max_execution_time' ) ); ?>s</span>
					</li>
				</ul>

				<p class="description" style="margin-top: 12px; margin-bottom: 8px;">
					<?php esc_html_e( 'Storage folder:', 'backupino' ); ?><br>
					<code style="word-break: break-all; font-size: 11.5px;"><?php echo esc_html( $paths->packages() ); ?></code>
				</p>

				<button type="button" class="button backupino-btn" id="backupino-test-storage" style="width: 100%; justify-content: center; margin-top: 6px;">
					<span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
					<?php esc_html_e( 'Test storage', 'backupino' ); ?>
				</button>
				<p class="backupino-result" id="backupino-storage-result" style="display: block; text-align: center; margin-top: 6px;"></p>
			</section>

			<!-- Migration Tips Card -->
			<section class="backupino-card">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-info"></span>
						<span><?php esc_html_e( 'Standalone Restore', 'backupino' ); ?></span>
					</h2>
				</div>
				<p class="description" style="margin-bottom: 0;">
					<?php esc_html_e( 'Each backup includes installer.php alongside the archive zip. You can copy both files to an empty domain or localhost and run installer.php in your browser to restore the entire website in 3 steps without pre-installing WordPress.', 'backupino' ); ?>
				</p>
			</section>
		</aside>

		<!-- Main Column: Wider content (Left in RTL, Right in LTR) -->
		<main class="backupino-main">
			<!-- 1. Create Backup Card -->
			<section class="backupino-card" id="backupino-card-create">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-plus-alt2"></span>
						<span><?php esc_html_e( 'Create a backup', 'backupino' ); ?></span>
					</h2>
				</div>
				<p class="description">
					<?php esc_html_e( 'Everything is packed into one archive plus a standalone installer that rebuilds the site on any empty server.', 'backupino' ); ?>
				</p>

				<noscript>
					<p><strong><?php esc_html_e( 'JavaScript is required to build backups.', 'backupino' ); ?></strong></p>
				</noscript>

				<form id="backupino-create" class="backupino-form">
					<div class="backupino-grid">
						<label class="backupino-check">
							<input type="checkbox" name="files" checked>
							<span class="backupino-check-label"><?php esc_html_e( 'Include files', 'backupino' ); ?></span>
							<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Includes WordPress core files, active themes, plugins, and uploads media library.', 'backupino' ); ?>" tabindex="0">?</span>
						</label>
						<label class="backupino-check">
							<input type="checkbox" name="database" checked>
							<span class="backupino-check-label"><?php esc_html_e( 'Include database', 'backupino' ); ?></span>
							<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Dumps all WordPress database tables into pure standard SQL without relying on mysqldump.', 'backupino' ); ?>" tabindex="0">?</span>
						</label>
					</div>

					<div class="backupino-field-group" style="margin-top: 14px;">
						<label class="backupino-label" for="backupino-note-field">
							<span class="backupino-label-text"><?php esc_html_e( 'Label (optional)', 'backupino' ); ?></span>
							<span class="backupino-tooltip" data-tooltip="<?php esc_attr_e( 'Optional note to identify why this backup was created (e.g. before updating WooCommerce).', 'backupino' ); ?>" tabindex="0">?</span>
						</label>
						<input type="text" id="backupino-note-field" name="note" maxlength="120" placeholder="<?php esc_attr_e( 'Before updating plugins', 'backupino' ); ?>">
					</div>

					<div style="margin-top: 18px;">
						<button type="submit" class="button button-primary button-hero backupino-btn-primary" id="backupino-start">
							<span class="dashicons dashicons-cloud-upload"></span>
							<span><?php esc_html_e( 'Create backup', 'backupino' ); ?></span>
						</button>
					</div>
				</form>
			</section>

			<!-- 2. Dedicated Backup Progress & Live Activity Feed Card -->
			<section class="backupino-card backupino-progress-card <?php echo null !== $backupino_running_job ? '' : 'hidden'; ?>"
				id="backupino-progress"
				<?php if ( null !== $backupino_running_job ) : ?>
					data-active-job="<?php echo esc_attr( (string) $backupino_running_job->id ); ?>"
					data-stage="<?php echo esc_attr( $backupino_running_job->stage ); ?>"
					data-progress="<?php echo esc_attr( (string) $backupino_running_job->progress ); ?>"
				<?php endif; ?>>
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-update spin"></span>
						<span><?php esc_html_e( 'Backup in Progress', 'backupino' ); ?></span>
					</h2>
					<div style="display: flex; align-items: center; gap: 8px;">
						<span class="backupino-progress-badge" id="backupino-progress-badge"><?php echo null !== $backupino_running_job ? esc_html( (string) $backupino_running_job->progress ) . '%' : '0%'; ?></span>
						<button type="button" class="button backupino-btn backupino-btn-danger" id="backupino-cancel-job" style="color: #b91c1c; border-color: #fecaca; background: #fff5f5; font-size: 11.5px; height: 26px; padding: 2px 8px; display: inline-flex; align-items: center; gap: 3px;" title="<?php esc_attr_e( 'Cancel backup job', 'backupino' ); ?>">
							<span class="dashicons dashicons-no-alt" style="font-size: 13px; width: 13px; height: 13px; vertical-align: middle;"></span>
							<span><?php esc_html_e( 'Cancel', 'backupino' ); ?></span>
						</button>
					</div>
				</div>

				<!-- Visual Step Flow -->
				<div class="backupino-stages-flow" id="backupino-stages-flow">
					<div class="backupino-stage-pill" data-stage="manifest">
						<span class="dashicons dashicons-search"></span>
						<span class="stage-text"><?php esc_html_e( 'Cataloging', 'backupino' ); ?></span>
					</div>
					<div class="backupino-stage-pill" data-stage="database">
						<span class="dashicons dashicons-database"></span>
						<span class="stage-text"><?php esc_html_e( 'Database', 'backupino' ); ?></span>
					</div>
					<div class="backupino-stage-pill" data-stage="files">
						<span class="dashicons dashicons-media-archive"></span>
						<span class="stage-text"><?php esc_html_e( 'Files', 'backupino' ); ?></span>
					</div>
					<div class="backupino-stage-pill" data-stage="installer">
						<span class="dashicons dashicons-admin-tools"></span>
						<span class="stage-text"><?php esc_html_e( 'Installer', 'backupino' ); ?></span>
					</div>
					<div class="backupino-stage-pill" data-stage="package">
						<span class="dashicons dashicons-archive"></span>
						<span class="stage-text"><?php esc_html_e( 'Packaging', 'backupino' ); ?></span>
					</div>
				</div>

				<!-- Main Gradient Progress Bar -->
				<div class="backupino-progress-bar-wrap">
					<div class="backupino-bar">
						<span class="backupino-bar-fill" style="<?php echo null !== $backupino_running_job ? 'width: ' . esc_attr( (string) $backupino_running_job->progress ) . '%;' : ''; ?>"></span>
					</div>
				</div>

				<div class="backupino-progress-meta">
					<p class="backupino-progress-text" id="backupino-progress-text">
						<?php echo null !== $backupino_running_job && '' !== $backupino_running_job->message ? esc_html( $backupino_running_job->message ) : esc_html__( 'Initializing backup job…', 'backupino' ); ?>
					</p>
				</div>

				<!-- Live Terminal / Activity Feed -->
				<div class="backupino-terminal-wrap">
					<div class="backupino-terminal-header">
						<div class="backupino-terminal-dots">
							<span class="dot red"></span>
							<span class="dot yellow"></span>
							<span class="dot green"></span>
						</div>
						<span class="backupino-terminal-title"><?php esc_html_e( 'Live Activity Feed', 'backupino' ); ?></span>
						<span class="backupino-terminal-indicator">● <?php esc_html_e( 'RUNNING', 'backupino' ); ?></span>
					</div>
					<div class="backupino-terminal-body" id="backupino-activity-log">
						<div class="backupino-log-line init">[00:00:00] <?php esc_html_e( 'Ready to start backup...', 'backupino' ); ?></div>
					</div>
				</div>

				<!-- Warning Box for Active Backup -->
				<div class="backupino-notice-box" style="margin-top: 14px; padding: 12px 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 13px; color: #92400e; display: flex; align-items: center; gap: 10px; line-height: 1.5;">
					<span class="dashicons dashicons-warning" style="font-size: 20px; width: 20px; height: 20px; color: #d97706; flex-shrink: 0;"></span>
					<span><?php esc_html_e( 'The backup process is running in the background. Please keep this browser window open until the operation finishes completely.', 'backupino' ); ?></span>
				</div>
			</section>

			<!-- 3. Recent Backups Table Card -->
			<section class="backupino-card" id="backupino-card-recent">
				<div class="backupino-card-header">
					<h2 class="backupino-card-title">
						<span class="dashicons dashicons-list-view"></span>
						<span><?php esc_html_e( 'Recent backups', 'backupino' ); ?></span>
					</h2>
				</div>
				<table class="widefat striped backupino-table" id="backupino-jobs">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Created', 'backupino' ); ?></th>
							<th><?php esc_html_e( 'Status', 'backupino' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'backupino' ); ?></th>
							<th><?php esc_html_e( 'Package name', 'backupino' ); ?></th>
							<th><?php esc_html_e( 'Size', 'backupino' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'backupino' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( array() === $jobs ) : ?>
						<tr>
							<td colspan="6" style="text-align: center; padding: 24px; color: #64748b;">
								<?php esc_html_e( 'No backups yet.', 'backupino' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $jobs as $backupino_job ) : ?>
							<?php
							$has_archive    = isset( $backupino_job->payload['package']['archive'] );
							$archive_file   = $has_archive ? (string) $backupino_job->payload['package']['archive'] : '';
							$installer_file = isset( $backupino_job->payload['package']['installer'] ) ? (string) $backupino_job->payload['package']['installer'] : '';
							$archive_url    = $has_archive ? \Backupino\Admin\Views::download_url( $archive_file ) : '';
							$installer_url  = '' !== $installer_file ? \Backupino\Admin\Views::download_url( $installer_file ) : '';
							$package_size   = isset( $backupino_job->payload['package']['size'] ) ? (int) $backupino_job->payload['package']['size'] : 0;
							?>
							<tr data-job="<?php echo esc_attr( (string) $backupino_job->id ); ?>">
								<td>
									<?php
									$backupino_created_ts = strtotime( $backupino_job->created_at );
									echo esc_html( false !== $backupino_created_ts ? \Backupino\Support\Dates::format( $backupino_created_ts ) : $backupino_job->created_at );
									?>
								</td>
								<td>
									<button type="button" class="backupino-status backupino-status--<?php echo esc_attr( $backupino_job->status ); ?> backupino-job-log-trigger"
										data-job-id="<?php echo esc_attr( (string) $backupino_job->id ); ?>"
										title="<?php esc_attr_e( 'Click to view log and details', 'backupino' ); ?>"
										style="cursor: pointer; border: none; font-family: inherit;">
										<span><?php echo esc_html( \Backupino\Jobs\JobStatus::label( $backupino_job->status ) ); ?></span>
										<span class="dashicons dashicons-search" style="font-size: 13px; width: 13px; height: 13px; margin-inline-start: 4px; vertical-align: middle;"></span>
									</button>
								</td>
								<td><strong><?php echo esc_html( (string) $backupino_job->progress ); ?>%</strong></td>
								<td>
									<?php
									if ( isset( $backupino_job->payload['package']['name'] ) ) {
										$backupino_pkg_full = (string) $backupino_job->payload['package']['name'];
										$backupino_pkg_pos  = strpos( $backupino_pkg_full, '_' );
										$backupino_pkg_sub  = false !== $backupino_pkg_pos ? substr( $backupino_pkg_full, $backupino_pkg_pos + 1 ) : $backupino_pkg_full;
										echo '<code class="backupino-pkg-name" title="' . esc_attr( $backupino_pkg_full ) . '">' . esc_html( $backupino_pkg_sub ) . '</code>';
									} else {
										echo '&mdash;';
									}
									?>
								</td>
								<td>
									<?php
									if ( $package_size > 0 ) {
										echo esc_html( \Backupino\Support\Bytes::format( $package_size ) );
									} else {
										echo '&mdash;';
									}
									?>
								</td>
								<td class="backupino-actions-cell" style="white-space: nowrap;">
									<?php if ( $has_archive && '' !== $archive_url ) : ?>
										<a href="<?php echo esc_url( $archive_url ); ?>" class="backupino-btn-icon"
											title="<?php esc_attr_e( 'Download Archive (.zip)', 'backupino' ); ?>"
											aria-label="<?php esc_attr_e( 'Download Archive', 'backupino' ); ?>">
											<span class="dashicons dashicons-archive"></span>
										</a>
									<?php endif; ?>
									<?php if ( '' !== $installer_url ) : ?>
										<a href="<?php echo esc_url( $installer_url ); ?>" class="backupino-btn-icon"
											title="<?php esc_attr_e( 'Download Installer (installer.php)', 'backupino' ); ?>"
											aria-label="<?php esc_attr_e( 'Download Installer', 'backupino' ); ?>">
											<span class="dashicons dashicons-admin-tools"></span>
										</a>
									<?php endif; ?>
									<?php if ( $has_archive && '' !== $archive_url && '' !== $installer_url ) : ?>
										<button type="button" class="backupino-btn-icon backupino-download-pair"
											data-archive="<?php echo esc_url( $archive_url ); ?>"
											data-installer="<?php echo esc_url( $installer_url ); ?>"
											title="<?php esc_attr_e( 'Download Archive and Installer simultaneously', 'backupino' ); ?>"
											aria-label="<?php esc_attr_e( 'Download Both', 'backupino' ); ?>">
											<span class="dashicons dashicons-download"></span>
										</button>
									<?php endif; ?>
									<button type="button" class="backupino-btn-icon backupino-btn-icon--danger backupino-delete"
										data-job="<?php echo esc_attr( (string) $backupino_job->id ); ?>"
										title="<?php esc_attr_e( 'Delete backup package', 'backupino' ); ?>"
										aria-label="<?php esc_attr_e( 'Delete', 'backupino' ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</section>
		</main>
	</div>

	<!-- Job Details & Log Modal Overlay -->
	<div id="backupino-job-modal" class="backupino-modal-overlay" style="display: none;">
		<div class="backupino-modal-card">
			<div class="backupino-modal-header">
				<h3 class="backupino-modal-title">
					<span class="dashicons dashicons-media-text" style="color: #0284c7;"></span>
					<span id="backupino-modal-job-title"><?php esc_html_e( 'Backup Details & Log', 'backupino' ); ?></span>
				</h3>
				<button type="button" class="backupino-modal-close" id="backupino-modal-close" aria-label="<?php esc_attr_e( 'Close', 'backupino' ); ?>">&times;</button>
			</div>
			<div class="backupino-modal-body">
				<div class="backupino-modal-meta-grid">
					<div>
						<span class="backupino-modal-label"><?php esc_html_e( 'Status', 'backupino' ); ?>:</span>
						<span id="backupino-modal-status" class="backupino-status"></span>
					</div>
					<div>
						<span class="backupino-modal-label"><?php esc_html_e( 'Progress', 'backupino' ); ?>:</span>
						<strong id="backupino-modal-progress">0%</strong>
					</div>
					<div>
						<span class="backupino-modal-label"><?php esc_html_e( 'Date', 'backupino' ); ?>:</span>
						<span id="backupino-modal-date">&mdash;</span>
					</div>
					<div>
						<span class="backupino-modal-label"><?php esc_html_e( 'Message', 'backupino' ); ?>:</span>
						<span id="backupino-modal-message">&mdash;</span>
					</div>
				</div>

				<div id="backupino-modal-package-wrap" style="display: none; margin-top: 12px; padding: 10px 14px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
					<div style="font-size: 12px; color: #475569; display: flex; flex-wrap: wrap; gap: 14px;">
						<div><strong><?php esc_html_e( 'Package', 'backupino' ); ?>:</strong> <span id="backupino-modal-package-name"></span></div>
						<div><strong><?php esc_html_e( 'Size', 'backupino' ); ?>:</strong> <span id="backupino-modal-package-size"></span></div>
					</div>
					<div style="margin-top: 8px; display: flex; flex-wrap: wrap; gap: 8px;">
						<a href="#" id="backupino-modal-download-zip" class="button backupino-btn" style="text-decoration: none;">
							<span class="dashicons dashicons-archive"></span> <?php esc_html_e( 'Download Archive (.zip)', 'backupino' ); ?>
						</a>
						<a href="#" id="backupino-modal-download-installer" class="button backupino-btn" style="text-decoration: none;">
							<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Download Installer (.php)', 'backupino' ); ?>
						</a>
					</div>
				</div>

				<div style="margin-top: 14px;">
					<label style="font-weight: 600; font-size: 12.5px; color: #334155; margin-bottom: 6px; display: block;">
						<?php esc_html_e( 'Execution Log', 'backupino' ); ?>:
					</label>
					<div class="backupino-modal-log-terminal">
						<pre id="backupino-modal-log-content"><?php esc_html_e( 'Loading logs…', 'backupino' ); ?></pre>
					</div>
				</div>
			</div>
			<div class="backupino-modal-footer">
				<button type="button" class="button backupino-btn backupino-btn-secondary" id="backupino-modal-close-btn">
					<?php esc_html_e( 'Close', 'backupino' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>