<?php
/**
 * Package metadata.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

/**
 * Describes a finished, downloadable package.
 */
final class Package {

	/**
	 * Package base name (without extension).
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Absolute path of the archive.
	 *
	 * @var string
	 */
	public $archive = '';

	/**
	 * Absolute path of the installer.
	 *
	 * @var string
	 */
	public $installer = '';

	/**
	 * Archive size in bytes.
	 *
	 * @var int
	 */
	public $size = 0;

	/**
	 * SHA-256 of the archive.
	 *
	 * @var string
	 */
	public $hash = '';

	/**
	 * Free-form metadata stored inside the archive.
	 *
	 * @var array<string, mixed>
	 */
	public $meta = array();

	/**
	 * Build a package descriptor.
	 *
	 * @param string               $name      Base name.
	 * @param string               $archive   Archive path.
	 * @param string               $installer Installer path.
	 * @param int                  $size      Archive size.
	 * @param string               $hash      Archive hash.
	 * @param array<string, mixed> $meta      Metadata.
	 * @return self
	 */
	public static function create( string $name, string $archive, string $installer, int $size, string $hash, array $meta = array() ): self {
		$package            = new self();
		$package->name      = $name;
		$package->archive   = $archive;
		$package->installer = $installer;
		$package->size      = $size;
		$package->hash      = $hash;
		$package->meta      = $meta;

		return $package;
	}

	/**
	 * Serialize.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'name'      => $this->name,
			'archive'   => $this->archive,
			'installer' => $this->installer,
			'size'      => $this->size,
			'hash'      => $this->hash,
			'meta'      => $this->meta,
		);
	}

	/**
	 * Hydrate from array data.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return self::create(
			isset( $data['name'] ) ? (string) $data['name'] : '',
			isset( $data['archive'] ) ? (string) $data['archive'] : '',
			isset( $data['installer'] ) ? (string) $data['installer'] : '',
			isset( $data['size'] ) ? (int) $data['size'] : 0,
			isset( $data['hash'] ) ? (string) $data['hash'] : '',
			isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array()
		);
	}

	/**
	 * Default file name of a package.
	 *
	 * @param string $site_host Sanitized site host.
	 * @return string
	 */
	public static function default_name( string $site_host ): string {
		$host = strtolower( preg_replace( '/[^a-z0-9\.\-]+/i', '-', $site_host ) );

		return trim( $host, '-' ) . '_' . gmdate( 'Ymd_His' );
	}
}
