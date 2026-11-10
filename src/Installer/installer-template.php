<?php
/**
 * Standalone Offline Migration & Restoration Wizard Template.
 *
 * NOTE FOR WORDPRESS.ORG PLUGIN REVIEWERS:
 * This file is NOT executed directly within the host WordPress environment.
 * It serves exclusively as an offline build template that is compiled into the standalone
 * `installer.php` file included within backup packages.
 * That standalone script is meant to rebuild and migrate the site on an empty destination
 * server where WordPress has not yet been installed.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput -- Standalone offline installer JSON/HTML template.
// phpcs:disable WordPress.Security.NonceVerification -- Standalone offline installer without WordPress nonce system.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Standalone offline installer without WordPress unslash/sanitize functions.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Standalone offline installer executed outside WordPress scope.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Standalone installer running outside WordPress environment.
// phpcs:disable Squiz.PHP.DiscouragedFunctions -- Standalone installer resource/timeout management.

if ( ! defined( 'ABSPATH' ) ) {
	@set_time_limit( 180 );
	ob_start();
}

register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		while ( ob_get_level() > 0 ) {
			@ob_end_clean();
		}
		if ( ! headers_sent() ) {
			http_response_code( 200 );
			header( 'Content-Type: application/json; charset=UTF-8' );
		}
		echo json_encode( array(
			'ok'    => false,
			'error' => 'Fatal Error: ' . $error['message'] . ' in ' . basename( $error['file'] ) . ':' . $error['line'],
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}
} );

$backupino_payload = json_decode( (string) base64_decode( '__BACKUPINO_PAYLOAD__' ), true );
$backupino_payload = is_array( $backupino_payload ) ? $backupino_payload : array();

/* __BACKUPINO_BODY__ */

/**
 * Send a JSON response and stop.
 *
 * @param array<string, mixed> $data   Payload.
 * @param int                  $status HTTP status.
 * @return void
 */
function respond( array $data, int $status = 200 ): void {
	while ( ob_get_level() > 0 ) {
		@ob_end_clean();
	}
	if ( ! headers_sent() ) {
		// Use HTTP 200 so web servers (cPanel, LiteSpeed, Nginx) do not intercept or suppress the JSON error body
		http_response_code( 200 );
		header( 'Content-Type: application/json; charset=UTF-8' );
	}

	echo json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

$backupino_dir          = (string) getcwd();

if ( false !== strpos( str_replace( '\\', '/', $backupino_dir ), '/wp-content/' ) ) {
	if ( ! headers_sent() ) {
		http_response_code( 403 );
	}
	exit( 'Forbidden: The standalone installer must not be executed inside wp-content.' );
}

// Quarantine any rogue server configuration files in destination directory that could crash LiteSpeed/Apache
if ( is_file( $backupino_dir . '/.htaccess' ) && ! file_exists( $backupino_dir . '/.htaccess.backupino-orig' ) ) {
	@rename( $backupino_dir . '/.htaccess', $backupino_dir . '/.htaccess.backupino-orig' );
}
if ( is_file( $backupino_dir . '/.user.ini' ) && ! file_exists( $backupino_dir . '/.user.ini.backupino-orig' ) ) {
	@rename( $backupino_dir . '/.user.ini', $backupino_dir . '/.user.ini.backupino-orig' );
}
if ( is_file( $backupino_dir . '/php.ini' ) && ! file_exists( $backupino_dir . '/php.ini.backupino-orig' ) ) {
	@rename( $backupino_dir . '/php.ini', $backupino_dir . '/php.ini.backupino-orig' );
}

$backupino_state_file   = $backupino_dir . '/backupino-install.json';
$backupino_archive_name = isset( $backupino_payload['archive'] ) ? basename( (string) $backupino_payload['archive'] ) : '';
$backupino_archive      = $backupino_dir . '/' . $backupino_archive_name;
$backupino_token        = isset( $backupino_payload['token'] ) ? (string) $backupino_payload['token'] : '';
$backupino_action       = isset( $_REQUEST['action'] ) ? (string) preg_replace( '/[^a-z_]/', '', (string) $_REQUEST['action'] ) : 'index';

// Auto-detect current host and base URL
$backupino_scheme   = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
$backupino_raw_host = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
$backupino_host     = (string) preg_replace( '/[^a-zA-Z0-9.:\-_]/', '', $backupino_raw_host );
$backupino_raw_scr  = isset( $_SERVER['SCRIPT_NAME'] ) ? (string) $_SERVER['SCRIPT_NAME'] : '';
$backupino_script   = (string) preg_replace( '/[^a-zA-Z0-9.\/_\-]/', '', $backupino_raw_scr );
$backupino_subpath  = rtrim( str_replace( '\\', '/', dirname( $backupino_script ) ), '/' );
$backupino_auto_url = $backupino_scheme . '://' . $backupino_host . ( '/' === $backupino_subpath ? '' : $backupino_subpath );

if ( 'index' === $backupino_action ) {
	$backupino_checks = Env::check( $backupino_payload, $backupino_dir, $backupino_archive );
	$backupino_body   = '<div id="app">
	<section id="step1">
		<h2 style="font-size:16px;margin:0 0 10px" data-i18n="env_check">Environment check</h2>'
		. View::checks( $backupino_checks ) . '
		<p class="err" id="error1"></p>
		<button type="button" id="start" data-i18n="continue" ' . ( Env::passes( $backupino_checks ) ? '' : 'disabled' ) . '>Continue</button>
		<small class="hint"><span data-i18n="package_lbl">Package</span>: ' . View::e( (string) ( $backupino_payload['name'] ?? '' ) ) . ' &middot; <span data-i18n="built_lbl">built</span> ' . View::e( (string) ( $backupino_payload['created'] ?? '' ) ) . '</small>
	</section>

	<section id="step2" class="hidden">
		<h2 style="font-size:16px;margin:0 0 10px" data-i18n="database">Database</h2>
		<form id="dbform" autocomplete="off">
			' . ( '' !== (string) ( $backupino_payload['password'] ?? '' ) ? '<label for="installer_password" data-i18n="installer_password">Installer Password</label><input type="password" id="installer_password" name="installer_password" required placeholder="Enter package password">' : '' ) . '
			<div class="grid">
				<div><label for="db_host" data-i18n="db_host">Host</label><input id="db_host" name="db_host" value="localhost" required></div>
				<div><label for="db_port" data-i18n="db_port">Port</label><input id="db_port" name="db_port" value="" placeholder="3306"></div>
			</div>
			<label for="db_name" data-i18n="db_name">Database name</label><input id="db_name" name="db_name" required>
			<div class="grid">
				<div><label for="db_user" data-i18n="db_user">Username</label><input id="db_user" name="db_user" required></div>
				<div><label for="db_pass" data-i18n="db_pass">Password</label><input id="db_pass" name="db_pass" type="password"></div>
			</div>
			<label for="prefix" data-i18n="prefix">Table prefix</label><input id="prefix" name="prefix" value="' . View::e( (string) ( $backupino_payload['table_prefix'] ?? 'wp_' ) ) . '">
			<label data-i18n="target_path">Target Path</label><input readonly value="' . View::e( $backupino_dir ) . '" style="background:#f1f5f9;color:#64748b;cursor:default;">
			<label for="new_url" data-i18n="new_url">New site URL</label><input id="new_url" name="new_url" value="' . View::e( $backupino_auto_url ) . '" placeholder="https://example.com">
			<small class="hint" data-i18n="new_url_hint">Auto-detected from current domain and path.</small>
			<label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-weight:normal;cursor:pointer;">
				<input type="checkbox" id="empty_db" name="empty_db" value="1" checked style="width:auto;margin:0;">
				<span data-i18n="empty_db_lbl">Empty existing database tables before import (Recommended)</span>
			</label>
			<p class="err" id="error2"></p>
			<div style="display:flex;gap:10px;align-items:center;margin-top:16px;">
				<button type="button" class="ghost" id="back-to-step1" data-i18n="back_btn" style="margin-top:0;">&larr; Back</button>
				<button type="button" id="connect" data-i18n="connect_btn" style="margin-top:0;">Connect &amp; continue</button>
			</div>
		</form>
	</section>

	<section id="step3" class="hidden">
		<h2 style="font-size:16px;margin:0 0 10px" id="progress-title" data-i18n="working">Working…</h2>
		<div class="bar"><i id="bar"></i></div>
		<pre id="log"></pre>
		<div class="backupino-warning-box" style="margin-top:12px;padding:10px 14px;background:#fefce8;border:1px solid #fef08a;border-radius:8px;font-size:12.5px;color:#854d0e;display:flex;align-items:center;gap:8px;">
			<span style="font-size:16px;line-height:1;">⚠️</span>
			<span data-i18n="do_not_close_hint">Please keep this browser window open and do not refresh or navigate away until the restoration is completely finished.</span>
		</div>
		<p class="err" id="error3" style="display:none;margin-top:12px;padding:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#b91c1c;font-size:13px;word-break:break-all;"></p>
	</section>

	<section id="step4" class="hidden">
		<h2 style="font-size:16px;margin:0 0 10px;color:#16a34a;" id="done-title" data-i18n="ready_title">&#10003; Your site is ready</h2>
		<p class="lead" id="done-url" style="font-weight:600;word-break:break-all;"></p>
		<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px;">
			<button type="button" id="cleanup" data-i18n="cleanup_btn" style="background:#16a34a;margin-top:0;">Delete installer files &amp; Login</button>
		</div>
		<p class="err" id="error4"></p>
	</section>
</div>';

	$config_data = array(
		'token'     => $backupino_token,
		'locale'    => (string) ( $backupino_payload['locale'] ?? 'en_US' ),
		'envPasses' => Env::passes( $backupino_checks ),
	);

	echo View::page( (string) json_encode( $config_data ), $backupino_body );
	exit;
}

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store, no-cache, must-revalidate' );
header( 'X-Robots-Tag: noindex, nofollow' );

if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
	respond( array( 'ok' => false, 'error' => 'Method not allowed.' ), 405 );
}

$backupino_sent_token = isset( $_REQUEST['token'] ) ? (string) $_REQUEST['token'] : '';

if ( '' === $backupino_token || ! hash_equals( $backupino_token, $backupino_sent_token ) ) {
	respond( array( 'ok' => false, 'error' => 'Invalid installer token.' ), 403 );
}

@set_time_limit( 180 );

$backupino_state = new State( $backupino_state_file );

// Optional password gate, configured when the package was built.
$backupino_password = isset( $backupino_payload['password'] ) ? (string) $backupino_payload['password'] : '';

if ( '' !== $backupino_password && ! $backupino_state->get( 'authenticated' ) ) {
	$backupino_supplied = isset( $_REQUEST['installer_password'] ) ? (string) $_REQUEST['installer_password'] : '';

	if ( ! hash_equals( $backupino_password, $backupino_supplied ) ) {
		respond( array( 'ok' => false, 'error' => 'Installer password required.' ), 401 );
	}

	$backupino_state->set( 'authenticated', true );
	$backupino_state->save();
}

try {
switch ( $backupino_action ) {
	case 'check':
		$checks = Env::check( $backupino_payload, $backupino_dir, $backupino_archive );

		respond(
			array(
				'ok'     => true,
				'checks' => $checks,
				'passed' => Env::passes( $checks ),
			)
		);
		break;

	case 'connect':
		$prefix = isset( $_POST['prefix'] ) && '' !== trim( (string) $_POST['prefix'] ) ? trim( (string) $_POST['prefix'] ) : 'wp_';
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $prefix ) ) {
			respond( array( 'ok' => false, 'error' => 'Invalid table prefix.' ), 400 );
		}

		$config = array(
			'host'     => isset( $_POST['db_host'] ) ? sanitize_env( (string) $_POST['db_host'] ) : 'localhost',
			'port'     => isset( $_POST['db_port'] ) ? (int) $_POST['db_port'] : 0,
			'name'     => isset( $_POST['db_name'] ) ? trim( (string) $_POST['db_name'] ) : '',
			'user'     => isset( $_POST['db_user'] ) ? trim( (string) $_POST['db_user'] ) : '',
			'password' => isset( $_POST['db_pass'] ) ? (string) $_POST['db_pass'] : '',
			'prefix'   => $prefix,
		);

		$new_url = isset( $_POST['new_url'] ) ? trim( (string) $_POST['new_url'] ) : '';
		if ( '' !== $new_url ) {
			if ( false === filter_var( $new_url, FILTER_VALIDATE_URL ) || ! preg_match( '#^https?://#i', $new_url ) ) {
				respond( array( 'ok' => false, 'error' => 'Invalid new site URL.' ), 400 );
			}
		}

		if ( '' === $config['name'] || '' === $config['user'] ) {
			respond( array( 'ok' => false, 'error' => 'Database name and user are required.' ), 400 );
		}

		try {
			$connection = ConnectionManager::ensure( $config );
		} catch ( \RuntimeException $e ) {
			respond( array( 'ok' => false, 'error' => $e->getMessage() ), 400 );
		}

		$tables = array();

		foreach ( $connection->rows( 'SHOW TABLES' ) as $row ) {
			$values = array_values( $row );

			if ( isset( $values[0] ) ) {
				$tables[] = (string) $values[0];
			}
		}

		$backupino_state->set( 'db', $config );
		$backupino_state->set( 'tables_found', count( $tables ) );
		$backupino_state->set( 'new_url', $new_url );
		$backupino_state->set( 'empty_db', ! empty( $_POST['empty_db'] ) );
		$backupino_state->save();

		respond(
			array(
				'ok'           => true,
				'db'           => $config['name'],
				'message'      => sprintf( 'Database connected (%d tables existing).', count( $tables ) ),
				'tables_found' => count( $tables ),
			)
		);
		break;

	case 'extract':
		if ( ! is_readable( $backupino_archive ) ) {
			respond( array( 'ok' => false, 'error' => 'Archive file not found: ' . basename( $backupino_archive ) ), 400 );
		}

		$extractor = new Extractor( $backupino_archive, $backupino_dir );
		if ( ! $extractor->open() ) {
			respond( array( 'ok' => false, 'error' => $extractor->error() ), 400 );
		}

		$offset = (int) $backupino_state->get( 'extract_offset', 0 );
		$total  = $extractor->total();
		$done   = $extractor->step( $offset, 300 );

		$backupino_state->set( 'extract_offset', $offset );
		$backupino_state->save();

		$extractor->close();

		$is_done = ( $offset >= $total );

		respond(
			array(
				'ok'      => true,
				'done'    => $is_done,
				'percent' => $total > 0 ? min( 100, (int) floor( ( $offset / $total ) * 100 ) ) : 100,
				'message' => sprintf( '%d of %d files extracted', min( $offset, $total ), $total ),
			)
		);
		break;

	case 'import':
		// Ensure any rogue server configuration extracted during files extraction is quarantined safely without overwriting destination backups
		foreach ( array( '.htaccess', '.user.ini', 'php.ini' ) as $cfg_file ) {
			$file_path = $backupino_dir . '/' . $cfg_file;
			$orig_path = $backupino_dir . '/' . $cfg_file . '.backupino-orig';
			if ( is_file( $file_path ) ) {
				if ( ! file_exists( $orig_path ) ) {
					@rename( $file_path, $orig_path );
				} else {
					@unlink( $file_path );
				}
			}
		}

		$sql_file = $backupino_dir . '/database.sql';

		if ( ! is_readable( $sql_file ) ) {
			respond(
				array(
					'ok'      => true,
					'done'    => true,
					'percent' => 100,
					'message' => 'No database dump found in the archive.',
				)
			);
		}

		$db = $backupino_state->get( 'db' );

		if ( ! is_array( $db ) ) {
			respond( array( 'ok' => false, 'error' => 'Connect to the database first.' ), 400 );
		}

		try {
			$connection = ConnectionManager::connect( $db );
			$connection->query( 'SET FOREIGN_KEY_CHECKS = 0' );
			$connection->query( 'SET UNIQUE_CHECKS = 0' );
		} catch ( \RuntimeException $e ) {
			respond( array( 'ok' => false, 'error' => $e->getMessage() ), 400 );
		}

		$offset   = (int) $backupino_state->get( 'sql_offset', 0 );

		if ( 0 === $offset && ! empty( $backupino_state->get( 'empty_db' ) ) ) {
			try {
				$connection->query( 'SET FOREIGN_KEY_CHECKS = 0' );
				$connection->query( 'SET UNIQUE_CHECKS = 0' );
				foreach ( $connection->rows( 'SHOW FULL TABLES' ) as $row ) {
					$vals     = array_values( $row );
					$tbl_name = (string) ( $vals[0] ?? '' );
					$tbl_type = strtoupper( (string) ( $vals[1] ?? 'BASE TABLE' ) );
					if ( '' !== $tbl_name ) {
						$escaped_tbl = '`' . str_replace( '`', '``', $tbl_name ) . '`';
						$drop_sql    = 'VIEW' === $tbl_type
							? "DROP VIEW IF EXISTS {$escaped_tbl}"
							: "DROP TABLE IF EXISTS {$escaped_tbl}";
						$connection->query( $drop_sql );
					}
				}

				$connection->query( 'SET FOREIGN_KEY_CHECKS = 1' );
				$connection->query( 'SET UNIQUE_CHECKS = 1' );
			} catch ( \Throwable $e ) {
				// Non-fatal, continue with dump execution.
			}
		}

		$source_prefix = (string) ( $backupino_payload['table_prefix'] ?? 'wp_' );
		$target_prefix = (string) ( $db['prefix'] ?? 'wp_' );

		$importer = new Importer( $connection, new SqlSplitter( $sql_file ), $source_prefix, $target_prefix );
		$result   = $importer->step( $offset, 262144, 8.0 );

		if ( ! empty( $result['error'] ) ) {
			respond( array( 'ok' => false, 'error' => $result['error'] ), 500 );
		}

		$backupino_state->set( 'sql_offset', $offset );
		$backupino_state->save();

		$size = max( 1, $importer->size() );

		respond(
			array(
				'ok'      => true,
				'done'    => (bool) $result['done'],
				'percent' => ! empty( $result['done'] ) ? 100 : min( 99, (int) floor( ( $offset / $size ) * 100 ) ),
				'message' => sprintf( '%d statements executed', $result['executed'] ),
			)
		);
		break;

	case 'rewrite':
		@set_time_limit( 180 );

		foreach ( array( '.htaccess', '.user.ini', 'php.ini' ) as $cfg_file ) {
			$file_path = $backupino_dir . '/' . $cfg_file;
			$orig_path = $backupino_dir . '/' . $cfg_file . '.backupino-orig';
			if ( is_file( $file_path ) ) {
				if ( ! file_exists( $orig_path ) ) {
					@rename( $file_path, $orig_path );
				} else {
					@unlink( $file_path );
				}
			}
		}

		$db = $backupino_state->get( 'db' );

		if ( ! is_array( $db ) ) {
			respond( array( 'ok' => false, 'error' => 'Connect to the database first.' ), 400 );
		}

		$old_url = (string) ( $backupino_payload['site_url'] ?? '' );
		$new_url = (string) $backupino_state->get( 'new_url', '' );

		if ( '' === $new_url ) {
			$new_url = $backupino_auto_url;
			$backupino_state->set( 'new_url', $new_url );
		}

		if ( '' === $old_url || $old_url === $new_url ) {
			respond(
				array(
					'ok'      => true,
					'done'    => true,
					'percent' => 100,
					'message' => 'URLs already match.',
				)
			);
		}

		try {
			$connection = ConnectionManager::connect( $db );
			$connection->query( 'SET FOREIGN_KEY_CHECKS = 0' );
			$connection->query( 'SET UNIQUE_CHECKS = 0' );
		} catch ( \RuntimeException $e ) {
			respond( array( 'ok' => false, 'error' => $e->getMessage() ), 400 );
		}

		$rewriter = new UrlRewrite( $connection, Replacer::from_urls( $old_url, $new_url ) );
		$state    = $backupino_state->get( 'rewrite', UrlRewrite::initial_state() );
		$state    = $rewriter->step( is_array( $state ) ? $state : UrlRewrite::initial_state(), 200, 6.0 );

		$backupino_state->set( 'rewrite', $state );
		$backupino_state->save();

		$total = max( 1, count( (array) $state['tables'] ) );

		respond(
			array(
				'ok'      => true,
				'done'    => (bool) $state['done'],
				'percent' => min( 100, (int) floor( ( (int) $state['index'] / $total ) * 100 ) ),
				'message' => sprintf( '%d rows scanned, %d values updated', (int) $state['rows'], (int) $state['changes'] ),
			)
		);
		break;

	case 'config':
		$db = $backupino_state->get( 'db' );

		if ( ! is_array( $db ) ) {
			respond( array( 'ok' => false, 'error' => 'Connect to the database first.' ), 400 );
		}

		$new_url = (string) $backupino_state->get( 'new_url', '' );

		if ( '' === $new_url ) {
			$new_url = $backupino_auto_url;
			$backupino_state->set( 'new_url', $new_url );
		}

		$source_prefix = (string) ( $backupino_payload['table_prefix'] ?? 'wp_' );
		$target_prefix = (string) ( $db['prefix'] ?? 'wp_' );

		try {
			$connection = ConnectionManager::connect( $db );

			// 1. Rename any legacy tables when the user explicitly changed prefix across different names
			if ( '' !== $source_prefix && '' !== $target_prefix && 0 !== strcasecmp( $source_prefix, $target_prefix ) ) {
				$raw_tables = $connection->rows( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" );
				if ( empty( $raw_tables ) ) {
					$raw_tables = $connection->rows( 'SHOW TABLES' );
				}
				$tables_to_rename = array();
				foreach ( $raw_tables as $r ) {
					$vals = array_values( $r );
					if ( isset( $vals[0] ) ) {
						$old_name = (string) $vals[0];
						if ( 0 === stripos( $old_name, $source_prefix ) ) {
							$new_name = $target_prefix . substr( $old_name, strlen( $source_prefix ) );
							if ( 0 !== strcasecmp( $old_name, $new_name ) ) {
								$tables_to_rename[] = array( 'old' => $old_name, 'new' => $new_name );
							}
						}
					}
				}
				if ( array() !== $tables_to_rename ) {
					foreach ( $tables_to_rename as $pair ) {
						try {
							$connection->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '``', $pair['new'] ) . '`' );
							$connection->query( 'RENAME TABLE `' . str_replace( '`', '``', $pair['old'] ) . '` TO `' . str_replace( '`', '``', $pair['new'] ) . '`' );
						} catch ( \Throwable $rename_err ) {
							try {
								$connection->query( 'CREATE TABLE IF NOT EXISTS `' . str_replace( '`', '``', $pair['new'] ) . '` LIKE `' . str_replace( '`', '``', $pair['old'] ) . '`' );
								$connection->query( 'INSERT IGNORE INTO `' . str_replace( '`', '``', $pair['new'] ) . '` SELECT * FROM `' . str_replace( '`', '``', $pair['old'] ) . '`' );
								$connection->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '``', $pair['old'] ) . '`' );
							} catch ( \Throwable $fallback_err ) {
								// Non-fatal.
							}
						}
					}
				}
			}

			// 2. Physical Table Prefix Healing (Windows lower_case_table_names <-> Linux case-sensitive tables)
			$all_raw_tables = $connection->rows( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" );
			if ( empty( $all_raw_tables ) ) {
				$all_raw_tables = $connection->rows( 'SHOW TABLES' );
			}
			$physical_tables = array();
			foreach ( $all_raw_tables as $r ) {
				$vals = array_values( $r );
				if ( isset( $vals[0] ) && '' !== $vals[0] ) {
					$physical_tables[] = (string) $vals[0];
				}
			}

			if ( ! in_array( $target_prefix . 'options', $physical_tables, true ) ) {
				$matched_table = null;
				// First check case-insensitive match for target prefix options
				foreach ( $physical_tables as $ptbl ) {
					if ( 0 === strcasecmp( $ptbl, $target_prefix . 'options' ) ) {
						$matched_table = $ptbl;
						break;
					}
				}
				// Fallback: match case-insensitive source prefix options
				if ( null === $matched_table && '' !== $source_prefix ) {
					foreach ( $physical_tables as $ptbl ) {
						if ( 0 === strcasecmp( $ptbl, $source_prefix . 'options' ) ) {
							$matched_table = $ptbl;
							break;
						}
					}
				}
				// Fallback: match any table ending in 'options' whose prefix also has posts or users
				if ( null === $matched_table ) {
					foreach ( $physical_tables as $ptbl ) {
						if ( strlen( $ptbl ) > 7 && 0 === strcasecmp( substr( $ptbl, -7 ), 'options' ) ) {
							$candidate_prefix = substr( $ptbl, 0, strlen( $ptbl ) - 7 );
							$has_companion    = false;
							foreach ( $physical_tables as $check_tbl ) {
								if ( 0 === strcasecmp( $check_tbl, $candidate_prefix . 'posts' ) || 0 === strcasecmp( $check_tbl, $candidate_prefix . 'users' ) ) {
									$has_companion = true;
									break;
								}
							}
							if ( $has_companion ) {
								$matched_table = $ptbl;
								break;
							}
						}
					}
				}

				if ( null !== $matched_table ) {
					$healed_prefix = substr( $matched_table, 0, strlen( $matched_table ) - 7 );
					if ( preg_match( '/^[a-zA-Z0-9_]+$/', $healed_prefix ) ) {
						$target_prefix = $healed_prefix;
						$db['prefix']  = $healed_prefix;
						$backupino_state->set( 'db', $db );
						$backupino_state->save();
					}
				}
			}

			// 3. User roles & usermeta key normalization with healed prefix (prepared statements)
			$target_opt_table  = str_replace( '`', '``', $target_prefix ) . 'options';
			$target_meta_table = str_replace( '`', '``', $target_prefix ) . 'usermeta';

			$target_roles_key = $target_prefix . 'user_roles';
			try {
				$opt_rows = $connection->fetch_all( "SELECT option_name FROM `{$target_opt_table}` WHERE option_name LIKE '%user_roles'" );
				foreach ( $opt_rows as $orow ) {
					$oname = (string) ( $orow['option_name'] ?? '' );
					if ( 0 === strcasecmp( $oname, $target_roles_key ) && $oname !== $target_roles_key ) {
						$connection->execute(
							"UPDATE `{$target_opt_table}` SET option_name = ? WHERE option_name = ?",
							array( $target_roles_key, $oname )
						);
					}
				}
			} catch ( \Throwable $e ) {
				// Ignore if option not present.
			}

			try {
				$meta_rows = $connection->fetch_all( "SELECT DISTINCT meta_key FROM `{$target_meta_table}` WHERE meta_key LIKE '%capabilities' OR meta_key LIKE '%user_level'" );
				foreach ( $meta_rows as $mrow ) {
					$mkey   = (string) ( $mrow['meta_key'] ?? '' );
					$suffix = '';
					if ( substr( $mkey, -12 ) === 'capabilities' ) {
						$suffix = 'capabilities';
					} elseif ( substr( $mkey, -10 ) === 'user_level' ) {
						$suffix = 'user_level';
					}
					if ( '' !== $suffix && 0 === strcasecmp( $mkey, $target_prefix . $suffix ) && $mkey !== ( $target_prefix . $suffix ) ) {
						$connection->execute(
							"UPDATE `{$target_meta_table}` SET meta_key = ? WHERE meta_key = ?",
							array( $target_prefix . $suffix, $mkey )
						);
					}
				}
			} catch ( \Throwable $e ) {
				// Ignore if usermeta missing.
			}

			if ( $source_prefix !== $target_prefix && '' !== $source_prefix && '' !== $target_prefix ) {
				$source_len = strlen( $source_prefix );
				$like_param = str_replace( array( '\\', '_', '%' ), array( '\\\\', '\\_', '\\%' ), $source_prefix ) . '%';
				try {
					$connection->execute(
						"UPDATE `{$target_meta_table}` SET meta_key = CONCAT(?, SUBSTRING(meta_key, ?)) WHERE meta_key LIKE ?",
						array( $target_prefix, $source_len + 1, $like_param )
					);
				} catch ( \Throwable $e ) {
					// Ignore if table missing.
				}
			}

			// 4. Update core options & locales using prepared statements
			try {
				$connection->execute(
					"UPDATE `{$target_opt_table}` SET option_value = ? WHERE option_name IN ('siteurl','home')",
					array( $new_url )
				);

				$target_locale = ! empty( $backupino_payload['locale'] ) ? (string) $backupino_payload['locale'] : 'en_US';
				$connection->execute(
					"REPLACE INTO `{$target_opt_table}` (`option_name`, `option_value`, `autoload`) VALUES ('WPLANG', ?, 'yes')",
					array( $target_locale )
				);

				$connection->execute(
					"UPDATE `{$target_meta_table}` SET meta_value = ? WHERE meta_key = 'locale'",
					array( $target_locale )
				);

				$connection->execute(
					"REPLACE INTO `{$target_opt_table}` (`option_name`, `option_value`, `autoload`) VALUES ('backupino_migration_notice', '1', 'yes')"
				);

				$connection->execute(
					"DELETE FROM `{$target_opt_table}` WHERE option_name = 'rewrite_rules'"
				);
			} catch ( \Throwable $e ) {
				// Non-fatal if options table is customized or missing.
			}

			// Repair unconfigured Elementor dynamic tags with settings=""
			$target_postmeta_table = str_replace( '`', '``', $target_prefix ) . 'postmeta';
			$target_posts_table    = str_replace( '`', '``', $target_prefix ) . 'posts';
			try {
				$connection->query( "UPDATE `{$target_postmeta_table}` SET meta_value = REPLACE(meta_value, 'settings=\"\"', 'settings=\"%7B%7D\"') WHERE meta_key = '_elementor_data' AND meta_value LIKE '%settings=\"\"%'" );
				$connection->query( "UPDATE `{$target_postmeta_table}` SET meta_value = REPLACE(meta_value, 'settings=\\\"\\\"', 'settings=\\\"%7B%7D\\\"') WHERE meta_key = '_elementor_data' AND meta_value LIKE '%settings=\\\"\\\"%'" );
				$connection->query( "UPDATE `{$target_posts_table}` SET post_content = REPLACE(post_content, 'settings=\"\"', 'settings=\"%7B%7D\"') WHERE post_content LIKE '%settings=\"\"%'" );
				$connection->query( "UPDATE `{$target_posts_table}` SET post_content = REPLACE(post_content, 'settings=\\\"\\\"', 'settings=\\\"%7B%7D\\\"') WHERE post_content LIKE '%settings=\\\"\\\"%'" );
			} catch ( \Throwable $e ) {
				// Non-fatal if tables missing.
			}

			// Auto-heal any orphaned WordPress placeholder hashes ({sha256}) in post_name with prepared statements
			try {
				$rows_with_hash = $connection->fetch_all( "SELECT `ID`, `post_name` FROM `{$target_posts_table}` WHERE `post_name` LIKE '%{%}%'" );
				foreach ( $rows_with_hash as $r_hash ) {
					$post_slug = (string) ( $r_hash['post_name'] ?? '' );
					if ( preg_match( '/\{[a-f0-9]{64}\}/i', $post_slug ) ) {
						$fixed_slug = (string) preg_replace( '/\{[a-f0-9]{64}\}/i', '%', $post_slug );
						$connection->execute(
							"UPDATE `{$target_posts_table}` SET `post_name` = ? WHERE `ID` = ?",
							array( $fixed_slug, (int) $r_hash['ID'] )
						);
					}
				}
			} catch ( \Throwable $e ) {
				// Non-fatal.
			}

			// Auto-heal any orphaned WordPress placeholder hashes ({sha256}) in _elementor_data with prepared statements
			try {
				$meta_with_hash = $connection->fetch_all( "SELECT `meta_id`, `meta_value` FROM `{$target_postmeta_table}` WHERE `meta_key` = '_elementor_data' AND `meta_value` LIKE '%{%}%'" );
				foreach ( $meta_with_hash as $m_hash ) {
					$meta_val = (string) ( $m_hash['meta_value'] ?? '' );
					if ( preg_match( '/\{[a-f0-9]{64}\}/i', $meta_val ) ) {
						$fixed_meta = (string) preg_replace( '/\{[a-f0-9]{64}\}/i', '%', $meta_val );
						$connection->execute(
							"UPDATE `{$target_postmeta_table}` SET `meta_value` = ? WHERE `meta_id` = ?",
							array( $fixed_meta, (int) $m_hash['meta_id'] )
						);
					}
				}
			} catch ( \Throwable $e ) {
				// Non-fatal.
			}

			// Clean up backupino_jobs on the restored site so no ghost or in-progress job from source site exists
			$target_jobs_table = str_replace( '`', '``', $target_prefix ) . 'backupino_jobs';
			try {
				$connection->query( "TRUNCATE TABLE `{$target_jobs_table}`" );
			} catch ( \Throwable $e ) {
				// Table might not exist yet, ignore.
			}
		} catch ( \RuntimeException $e ) {
			respond( array( 'ok' => false, 'error' => $e->getMessage() ), 400 );
		}

		// Preserve pre-compiled Elementor CSS files from the backup package so frontend loads immediately without dynamic tag recompilation errors.

		$orig_config  = $backupino_dir . '/wp-config.backupino-orig.php';
		$orig_content = null;
		if ( is_file( $orig_config ) && is_readable( $orig_config ) ) {
			$orig_content = (string) file_get_contents( $orig_config );
			@unlink( $orig_config );
		} elseif ( is_file( $backupino_archive ) && class_exists( 'ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( true === $zip->open( $backupino_archive ) ) {
				$tpl = $zip->getFromName( 'backupino-wp-config.tpl' );
				if ( false !== $tpl && '' !== $tpl ) {
					$orig_content = (string) $tpl;
				}
				$zip->close();
			}
		}

		$db['new_url'] = $new_url;
		if ( ! ConfigWriter::write( $backupino_dir . '/wp-config.php', $db, $orig_content ) ) {
			respond( array( 'ok' => false, 'error' => 'wp-config.php could not be written.' ), 500 );
		}

		// Reconfigure .htaccess for target environment with clean WordPress rewrite rules
		$url_path  = (string) parse_url( $new_url, PHP_URL_PATH );
		$base_path = ( '' === $url_path || '/' === $url_path ) ? '/' : ( '/' . trim( $url_path, '/' ) . '/' );
		$htaccess  = $backupino_dir . '/.htaccess';
		$standard_htaccess = "# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase {$base_path}\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . {$base_path}index.php [L]\n</IfModule>\n# END WordPress\n";

		if ( is_file( $htaccess ) && ! file_exists( $backupino_dir . '/.htaccess.backupino-orig' ) ) {
			@copy( $htaccess, $backupino_dir . '/.htaccess.backupino-orig' );
		}
		@file_put_contents( $htaccess, $standard_htaccess );

		// Detect custom login URL configured by security plugins (WPS Hide Login, AIOWPS, iThemes, Perfmatters, Cerber)
		$detected_login_slug = '';
		if ( isset( $connection ) && isset( $target_opt_table ) ) {
			try {
				// 1. WPS Hide Login
				$whl_row = $connection->fetch_all( "SELECT option_value FROM `{$target_opt_table}` WHERE option_name = ? LIMIT 1", array( 'whl_page' ) );
				if ( ! empty( $whl_row[0]['option_value'] ) ) {
					$val = trim( (string) $whl_row[0]['option_value'] );
					if ( '' !== $val ) {
						$detected_login_slug = $val;
					}
				}

				// 2. All In One Security (AIOWPS)
				if ( '' === $detected_login_slug ) {
					$aiowps_row = $connection->fetch_all( "SELECT option_value FROM `{$target_opt_table}` WHERE option_name = ? LIMIT 1", array( 'aio_wp_security_configs' ) );
					if ( ! empty( $aiowps_row[0]['option_value'] ) ) {
						$aiowps_data = @unserialize( (string) $aiowps_row[0]['option_value'], array( 'allowed_classes' => false ) );
						if ( is_array( $aiowps_data ) && ! empty( $aiowps_data['aiowps_enable_rename_login_page'] ) && ! empty( $aiowps_data['aiowps_login_page_slug'] ) ) {
							$detected_login_slug = trim( (string) $aiowps_data['aiowps_login_page_slug'] );
						}
					}
				}

				// 3. iThemes / Solid Security
				if ( '' === $detected_login_slug ) {
					$itsec_row = $connection->fetch_all( "SELECT option_value FROM `{$target_opt_table}` WHERE option_name = ? LIMIT 1", array( 'itsec-storage' ) );
					if ( ! empty( $itsec_row[0]['option_value'] ) ) {
						$itsec_data = @unserialize( (string) $itsec_row[0]['option_value'], array( 'allowed_classes' => false ) );
						if ( is_array( $itsec_data ) && isset( $itsec_data['hide-backend'] ) && is_array( $itsec_data['hide-backend'] ) ) {
							if ( ! empty( $itsec_data['hide-backend']['enabled'] ) && ! empty( $itsec_data['hide-backend']['slug'] ) ) {
								$detected_login_slug = trim( (string) $itsec_data['hide-backend']['slug'] );
							}
						}
					}
				}

				// 4. Perfmatters
				if ( '' === $detected_login_slug ) {
					$perf_row = $connection->fetch_all( "SELECT option_value FROM `{$target_opt_table}` WHERE option_name = ? LIMIT 1", array( 'perfmatters_options' ) );
					if ( ! empty( $perf_row[0]['option_value'] ) ) {
						$perf_data = @unserialize( (string) $perf_row[0]['option_value'], array( 'allowed_classes' => false ) );
						if ( is_array( $perf_data ) && ! empty( $perf_data['login_url'] ) ) {
							$detected_login_slug = trim( (string) $perf_data['login_url'] );
						}
					}
				}

				// 5. WP Cerber
				if ( '' === $detected_login_slug ) {
					$cerber_row = $connection->fetch_all( "SELECT option_value FROM `{$target_opt_table}` WHERE option_name = ? LIMIT 1", array( 'cerber_login_path' ) );
					if ( ! empty( $cerber_row[0]['option_value'] ) ) {
						$val = trim( (string) $cerber_row[0]['option_value'] );
						if ( '' !== $val ) {
							$detected_login_slug = $val;
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Non-fatal, fallback to standard wp-login.php
			}
		}

		$admin_login_url = ( '' !== $detected_login_slug )
			? rtrim( $new_url, '/' ) . '/' . trim( $detected_login_slug, '/' ) . '/'
			: rtrim( $new_url, '/' ) . '/wp-login.php';

		$backupino_state->set( 'finished', true );
		$backupino_state->save();

		// Enqueue restore notification in WordPress options so it is dispatched cleanly via WordPress HTTP API on first boot
		if ( ! empty( $backupino_payload['notify']['enabled'] ) && isset( $connection ) && isset( $target_opt_table ) ) {
			$notif        = (array) $backupino_payload['notify'];
			$site_name    = ! empty( $backupino_payload['site_name'] ) ? (string) $backupino_payload['site_name'] : $new_url;
			$admin_url    = $admin_login_url;
			$restore_date = gmdate( 'Y-m-d H:i' );
			$tpl          = ! empty( $notif['msg_restore_template'] ) ? (string) $notif['msg_restore_template'] : '';
			if ( '' === trim( $tpl ) ) {
				$site_locale = ! empty( $backupino_payload['locale'] ) ? (string) $backupino_payload['locale'] : 'en_US';
				if ( 0 === strpos( $site_locale, 'fa' ) ) {
					$tpl = "🎉 بازگردانی وب‌سایت با موفقیت انجام شد:\n🔹 {site}\n\n   🌐 آدرس جدید سایت: {url}\n   📅 تاریخ بازگردانی: {date}\n\n🔑 ورود به مدیریت وردپرس:\n<a href=\"{admin_url}\">ورود به پیشخوان وردپرس</a>";
				} else {
					$tpl = "🎉 Site restored successfully:\n🔹 {site}\n\n   🌐 New site URL: {url}\n   📅 Restoration date: {date}\n\n🔑 WordPress Admin login:\n<a href=\"{admin_url}\">Log in to WordPress Admin</a>";
				}
			}
			$restore_msg = str_replace(
				array( '{site}', '{url}', '{admin_url}', '{date}' ),
				array( $site_name, $new_url, $admin_url, $restore_date ),
				$tpl
			);

			$queue_data = serialize(
				array(
					array(
						'text'     => $restore_msg,
						'attempts' => 0,
					),
				)
			);
			try {
				$connection->execute(
					"REPLACE INTO `{$target_opt_table}` (`option_name`, `option_value`, `autoload`) VALUES (?, ?, ?)",
					array( 'backupino_notify_queue', $queue_data, 'no' )
				);
			} catch ( \Throwable $e ) {
				// Non-fatal if table unavailable.
			}
		}

		respond(
			array(
				'ok'      => true,
				'done'    => true,
				'percent' => 100,
				'url'     => $new_url,
				'admin'   => $admin_login_url,
				'message' => 'wp-config.php written.',
			)
		);
		break;

	case 'cleanup':
		$backupino_state->destroy();

		// Restore preserved destination host php configuration (.user.ini / php.ini) if valid
		foreach ( array( '.user.ini', 'php.ini' ) as $ini_file ) {
			$orig_path = $backupino_dir . '/' . $ini_file . '.backupino-orig';
			$dest_path = $backupino_dir . '/' . $ini_file;
			if ( is_file( $orig_path ) && ! file_exists( $dest_path ) ) {
				$content = (string) @file_get_contents( $orig_path );
				$has_broken_prepend = false;
				if ( preg_match( '/auto_prepend_file\s*=\s*[\'"]?([^\r\n\'"]+)[\'"]?/i', $content, $m ) ) {
					$prepended = trim( $m[1] );
					if ( '' !== $prepended && ! file_exists( $prepended ) ) {
						$has_broken_prepend = true;
					}
				}
				if ( ! $has_broken_prepend ) {
					@rename( $orig_path, $dest_path );
				}
			}
		}

		$targets = array(
			$backupino_dir . '/database.sql',
			$backupino_dir . '/backupino-manifest.ndjson',
			$backupino_dir . '/backupino-package.json',
			$backupino_dir . '/backupino-wp-config.tpl',
			$backupino_state_file,
			$backupino_archive,
			$backupino_dir . '/wp-config.backupino-orig.php',
			$backupino_dir . '/.htaccess.backupino-orig',
			$backupino_dir . '/.user.ini.backupino-orig',
			$backupino_dir . '/php.ini.backupino-orig',
		);

		$dir_files = @scandir( $backupino_dir );
		if ( is_array( $dir_files ) ) {
			foreach ( $dir_files as $f ) {
				if ( '.' === $f || '..' === $f ) {
					continue;
				}
				$fpath = $backupino_dir . '/' . $f;
				if ( ! is_file( $fpath ) ) {
					continue;
				}
				if ( preg_match( '/\.(zip|sql|ndjson)$/i', $f ) ) {
					if (
						0 === strpos( $f, 'backupino' ) ||
						( '' !== $backupino_archive_name && $f === $backupino_archive_name ) ||
						preg_match( '/^[a-zA-Z0-9_\.\-]+_\d{8}_\d{6}\.zip$/', $f )
					) {
						$targets[] = $fpath;
					}
				}
			}
		}

		$targets = array_unique( $targets );
		foreach ( $targets as $target ) {
			if ( is_file( $target ) ) {
				@chmod( $target, 0777 );
				@unlink( $target );
			}
		}

		// Delete installer script itself last
		$self = __FILE__;
		@chmod( $self, 0777 );
		@unlink( $self );
		register_shutdown_function( static function () use ( $self ) {
			if ( is_file( $self ) ) {
				@chmod( $self, 0777 );
				@unlink( $self );
			}
		} );

		respond( array( 'ok' => true, 'done' => true ) );
		break;

	default:
		respond( array( 'ok' => false, 'error' => 'Unknown action.' ), 400 );
}
} catch ( \Throwable $backupino_err ) {
	respond( array( 'ok' => false, 'error' => $backupino_err->getMessage() ), 500 );
}

/**
 * Normalize a host value coming from a form field.
 *
 * @param string $value Raw value.
 * @return string
 */
function sanitize_env( string $value ): string {
	$value = trim( $value );

	return '' === $value ? 'localhost' : preg_replace( '/[^A-Za-z0-9\.\-_:]/', '', $value );
}
