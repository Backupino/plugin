<?php
/**
 * Local storage.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Storage;

use Backupino\Support\Fs;

/**
 * Stores packages on the local disk, inside the plugin's storage directory.
 */
final class LocalStorage implements Storage {

	/**
	 * Absolute destination directory.
	 *
	 * @var string
	 */
	private $directory;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Constructor.
	 *
	 * @param string $directory Absolute directory.
	 */
	public function __construct( string $directory ) {
		$this->directory = rtrim( str_replace( '\\', '/', $directory ), '/' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'local';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'This server', 'backupino' );
	}

	/**
	 * Absolute path of a key.
	 *
	 * @param string $remote_key Key.
	 * @return string
	 */
	public function path( string $remote_key ): string {
		return $this->directory . '/' . ltrim( $remote_key, '/' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put( string $local_path, string $remote_key ): bool {
		Fs::ensure_dir( $this->directory );

		$target = $this->path( $remote_key );
		$parent = dirname( $target );

		Fs::ensure_dir( $parent );

		if ( $target === $local_path ) {
			return true;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( @copy( $local_path, $target ) ) {
			return true;
		}

		// Fall back to a manual copy for hosts that disable `copy()` on stream wrappers.
		$contents = @file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( false === $contents ) {
			$this->error = __( 'Could not read the package file.', 'backupino' );

			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === @file_put_contents( $target, $contents ) ) {
			$this->error = __( 'Could not write the package file.', 'backupino' );

			return false;
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( string $remote_key, string $local_path ): bool {
		$source = $this->path( $remote_key );

		if ( ! is_readable( $source ) ) {
			$this->error = __( 'Package not found.', 'backupino' );

			return false;
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return (bool) @copy( $source, $local_path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $remote_key ): bool {
		return Fs::delete( $this->path( $remote_key ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists( string $remote_key ): bool {
		return is_file( $this->path( $remote_key ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function error(): string {
		return $this->error;
	}
}
