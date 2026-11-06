<?php
/**
 * Storage factory.
 *
 * The plugin manages storage destinations. The default destination is local
 * server storage. Additional drivers can be registered through
 * the `backupino_storage_drivers` filter.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Storage;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Paths;
use Backupino\Core\Settings;

/**
 * Builds the storage driver configured in the settings.
 */
final class StorageManager {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Paths.
	 *
	 * @var Paths
	 */
	private $paths;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 * @param Paths    $paths    Paths.
	 */
	public function __construct( Settings $settings, Paths $paths ) {
		$this->settings = $settings;
		$this->paths    = $paths;
	}

	/**
	 * Settings accessor, handed to driver factories.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Paths accessor, handed to driver factories.
	 *
	 * @return Paths
	 */
	public function paths(): Paths {
		return $this->paths;
	}

	/**
	 * Registered driver factories.
	 *
	 * A factory receives this manager and must return a {@see Storage}.
	 *
	 * @return array<string, callable>
	 */
	public function drivers(): array {
		$drivers = apply_filters( 'backupino_storage_drivers', array(), $this );

		if ( ! is_array( $drivers ) ) {
			$drivers = array();
		}

		if ( ! isset( $drivers['local'] ) ) {
			$drivers['local'] = array( $this, 'local_driver' );
		}

		return $drivers;
	}

	/**
	 * The built in local driver.
	 *
	 * @return Storage
	 */
	public function local_driver(): Storage {
		return new LocalStorage( $this->paths->packages() );
	}

	/**
	 * Driver ids mapped to their human readable labels.
	 *
	 * @return array<string, string>
	 */
	public function available(): array {
		$labels = array();

		foreach ( $this->drivers() as $id => $factory ) {
			if ( ! is_callable( $factory ) ) {
				continue;
			}

			$storage = $factory( $this );

			$labels[ (string) $id ] = $storage instanceof Storage ? $storage->label() : (string) $id;
		}

		return $labels;
	}

	/**
	 * Configured driver.
	 *
	 * @return Storage
	 */
	public function driver(): Storage {
		return $this->driver_for( (string) $this->settings->get( 'storage.driver', 'local' ) );
	}

	/**
	 * Driver by id, falling back to local storage when the driver
	 * is not available.
	 *
	 * @param string $id Driver id.
	 * @return Storage
	 */
	public function driver_for( string $id ): Storage {
		$drivers = $this->drivers();

		if ( isset( $drivers[ $id ] ) && is_callable( $drivers[ $id ] ) ) {
			return ( $drivers[ $id ] )( $this );
		}

		return $this->local_driver();
	}
}
