<?php
/**
 * Installer state persistence.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * Stores wizard progress between AJAX requests.
 */
final class State {

	/**
	 * Absolute path of the state file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * State data.
	 *
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
		$this->load();
	}

	/**
	 * Load state from disk.
	 *
	 * @return void
	 */
	public function load(): void {
		if ( ! is_readable( $this->file ) ) {
			$this->data = array();

			return;
		}

		$raw    = (string) file_get_contents( $this->file );
		$parsed = json_decode( $raw, true );

		$this->data = is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * Persist state atomically with restrictive permissions.
	 *
	 * @return bool
	 */
	public function save(): bool {
		$encoded = json_encode( $this->data );

		if ( ! is_string( $encoded ) ) {
			return false;
		}

		$temp = $this->file . '.tmp';

		if ( false === @file_put_contents( $temp, $encoded, LOCK_EX ) ) {
			return false;
		}

		@chmod( $temp, 0600 );

		return @rename( $temp, $this->file );
	}

	/**
	 * Read a value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $fallback;
	}

	/**
	 * Write a value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}

	/**
	 * Whether a key exists.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * Delete the state file.
	 *
	 * @return void
	 */
	public function destroy(): void {
		$this->data = array();

		if ( is_file( $this->file ) ) {
			@unlink( $this->file );
		}
	}

	/**
	 * All data.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		return $this->data;
	}
}
