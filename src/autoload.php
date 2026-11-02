<?php
/**
 * PSR-4 style autoloader for the Backupino namespace.
 *
 * The plugin keeps a hand written autoloader on purpose: the runtime must stay
 * dependency free so that it works on any shared host, including hosts where
 * Composer binaries or a `vendor/` directory are not shipped.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BACKUPINO_NS' ) ) {
	define( 'BACKUPINO_NS', 'Backupino\\' );
}

if ( ! defined( 'BACKUPINO_SRC' ) ) {
	define( 'BACKUPINO_SRC', __DIR__ );
}

spl_autoload_register(
	/**
	 * Map `Backupino\Foo\Bar` to `src/Foo/Bar.php`.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	function ( $class_name ) {
		$prefix = BACKUPINO_NS;
		$length = strlen( $prefix );

		if ( 0 !== strncmp( $class_name, $prefix, $length ) ) {
			return;
		}

		$relative = substr( $class_name, $length );
		$file     = BACKUPINO_SRC . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
