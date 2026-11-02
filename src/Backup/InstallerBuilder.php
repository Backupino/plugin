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

			$code .= self::strip( (string) file_get_contents( $path ) ) . "\n";
		}

		return $code;
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

		$template = (string) file_get_contents( $template_path );

		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload );

		$encoded = base64_encode( (string) $json );

		$template = str_replace(
			"defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;",
			"define( 'BACKUPINO_INSTALLER', true );\ndefined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;",
			$template
		);
		$template = str_replace( '__BACKUPINO_PAYLOAD__', $encoded, $template );
		$template = str_replace( '__BACKUPINO_BODY__', $this->library(), $template );

		return $template;
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
