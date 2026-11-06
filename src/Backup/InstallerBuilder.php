<?php
/**
 * Standalone installer builder.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Assembles `installer.php`, a single dependency free PHP file that can rebuild
 * the site on an empty server without WordPress being present.
 *
 * The installer sources live in `src/Installer/` as normal, testable classes;
 * this builder flattens them into one file and injects the package payload.
 */
final class InstallerBuilder {

	/**
	 * Library files, in load order (interfaces before implementations).
	 *
	 * @var string[]
	 */
	private const LIBRARY = array(
		'functions.php',
		'../Replace/SerializedParser.php',
		'../Replace/Replacer.php',
		'SqlSplitter.php',
		'State.php',
		'Env.php',
		'Connection.php',
		'MysqliConnection.php',
		'PdoConnection.php',
		'ConnectionManager.php',
		'Extractor.php',
		'Importer.php',
		'UrlRewrite.php',
		'ConfigWriter.php',
		'View.php',
	);

	/**
	 * Absolute path of the installer source directory.
	 *
	 * @var string
	 */
	private $source_dir;

	/**
	 * Constructor.
	 *
	 * @param string $source_dir Installer sources directory.
	 */
	public function __construct( string $source_dir = '' ) {
		$this->source_dir = '' !== $source_dir
			? rtrim( $source_dir, '/' )
			: dirname( __DIR__ ) . '/Installer';
	}

	/**
	 * Flattened library code, without the entry point.
	 *
	 * @return string
	 */
	public function library(): string {
		$code = '';

		foreach ( self::LIBRARY as $file ) {
			$path = $this->source_dir . '/' . $file;

			if ( ! is_readable( $path ) ) {
				continue;
			}

			$code .= self::strip( (string) file_get_contents( $path ) ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $code;
	}

	/**
	 * Compile the layout template with CSS and JS inlined.
	 *
	 * @return string
	 */
	public function compiled_layout(): string {
		$template_file = $this->source_dir . '/installer-layout.html';
		$raw           = is_readable( $template_file ) ? (string) file_get_contents( $template_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$css_file      = dirname( $this->source_dir, 2 ) . '/assets/css/installer.css';
		$js_file       = dirname( $this->source_dir, 2 ) . '/assets/js/installer.js';
		$css           = is_readable( $css_file ) ? (string) file_get_contents( $css_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$js            = is_readable( $js_file ) ? (string) file_get_contents( $js_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions

		$raw = str_replace( '/* __INSTALLER_CSS__ */', $css, $raw );
		return str_replace( '/* __INSTALLER_JS__ */', $js, $raw );
	}

	/**
	 * Build the installer for a given package.
	 *
	 * @param array<string, mixed> $payload Package metadata injected at the top of the file.
	 * @return string
	 */
	public function build( array $payload ): string {
		$template_path = $this->source_dir . '/installer-template.php';

		if ( ! is_readable( $template_path ) ) {
			return '';
		}

		$template = (string) file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );

		$encoded = base64_encode( (string) $json );

		$template = str_replace(
			"defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;",
			"define( 'BACKUPINO_INSTALLER', true );\ndefined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;",
			$template
		);
		$template = str_replace( '__BACKUPINO_PAYLOAD__', $encoded, $template );

		$compiled_layout = $this->compiled_layout();
		$library         = $this->library();
		$escaped_layout  = addcslashes( $compiled_layout, "'\\" );
		$library         = str_replace( '/* __BACKUPINO_COMPILED_LAYOUT__ */', $escaped_layout, $library );

		$template = str_replace(
			array( '/* __BACKUPINO_BODY__ */', '__BACKUPINO_BODY__' ),
			$library,
			$template
		);

		return $template;
	}

	/**
	 * Build an installer on-the-fly for a package archive.
	 *
	 * @param string                   $archive_path Absolute path to the zip archive.
	 * @param \Backupino\Core\Settings $settings     Plugin settings.
	 * @return string
	 */
	public function build_for_package( string $archive_path, \Backupino\Core\Settings $settings ): string {
		$stem      = preg_replace( '/\.zip$/i', '', $archive_path );
		$meta_path = $stem . '.meta.json';
		$payload   = array();

		if ( is_readable( $meta_path ) ) {
			$raw    = (string) file_get_contents( $meta_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$parsed = json_decode( $raw, true );
			if ( is_array( $parsed ) ) {
				$payload = $parsed;
			}
		}

		if ( empty( $payload ) && class_exists( 'ZipArchive' ) && is_readable( $archive_path ) ) {
			$zip = new \ZipArchive();
			if ( true === $zip->open( $archive_path ) ) {
				$pkg_json = $zip->getFromName( 'backupino-package.json' );
				if ( false !== $pkg_json ) {
					$parsed = json_decode( (string) $pkg_json, true );
					if ( is_array( $parsed ) ) {
						$payload = $parsed;
					}
				}
				$zip->close();
			}
		}

		$archive_name    = basename( $archive_path );
		$name            = preg_replace( '/\.zip$/i', '', $archive_name );
		$notify_settings = (array) $settings->get( 'notify', array() );

		$defaults = array(
			'version'      => defined( 'BACKUPINO_VERSION' ) ? BACKUPINO_VERSION : '1.0.3',
			'name'         => $name,
			'token'        => \Backupino\Support\Str::random( 16 ),
			'archive'      => $archive_name,
			'archive_hash' => is_readable( $archive_path ) ? (string) hash_file( 'sha256', $archive_path ) : '',
			'archive_size' => is_readable( $archive_path ) ? (int) \Backupino\Support\Fs::size( $archive_path ) : 0,
			'site_url'     => function_exists( 'home_url' ) ? home_url() : '',
			'home_url'     => function_exists( 'home_url' ) ? home_url() : '',
			'site_name'    => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : ( function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '' ),
			'locale'       => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
			'table_prefix' => $GLOBALS['table_prefix'] ?? 'wp_',
			'created'      => gmdate( 'Y-m-d H:i:s' ),
			'password'     => (string) $settings->get( 'installer.password', '' ),
			'notify'       => array(
				'telegram_enabled'     => ! empty( $notify_settings['telegram_enabled'] ),
				'telegram_token'       => (string) ( $notify_settings['telegram_token'] ?? '' ),
				'telegram_chat_id'     => (string) ( $notify_settings['telegram_chat_id'] ?? '' ),
				'bale_enabled'         => ! empty( $notify_settings['bale_enabled'] ),
				'bale_token'           => (string) ( $notify_settings['bale_token'] ?? '' ),
				'bale_chat_id'         => (string) ( $notify_settings['bale_chat_id'] ?? '' ),
				'msg_restore_template' => (string) ( $notify_settings['msg_restore_template'] ?? '' ),
			),
		);

		$merged = array_merge( $defaults, $payload );
		$merged['archive'] = $archive_name;

		return $this->build( $merged );
	}

	/**
	 * Strip the file header, `declare` and `namespace` statements so that many
	 * files can be concatenated into one.
	 *
	 * @param string $code File contents.
	 * @return string
	 */
	private static function strip( string $code ): string {
		$code = (string) preg_replace( '/^<\?php\s*/', '', $code );
		$code = (string) preg_replace( '/declare\(\s*strict_types\s*=\s*1\s*\)\s*;/', '', $code );
		$code = (string) preg_replace( '/^namespace\s+[^;]+;\s*$/m', '', $code );
		$code = (string) preg_replace( '/^use\s+[^;]+;\s*$/m', '', $code );
		$code = (string) preg_replace( '/defined\(\s*\'BACKUPINO_INSTALLER\'\s*\)\s*\|\|\s*defined\(\s*\'ABSPATH\'\s*\)\s*\|\|\s*exit\s*;\s*/m', '', $code );
		$code = (string) preg_replace( '/defined\(\s*\'ABSPATH\'\s*\)\s*\|\|\s*exit\s*;\s*/m', '', $code );

		return trim( $code );
	}

	/**
	 * Names of the library files, useful for tests and diagnostics.
	 *
	 * @return string[]
	 */
	public static function library_files(): array {
		return self::LIBRARY;
	}
}
