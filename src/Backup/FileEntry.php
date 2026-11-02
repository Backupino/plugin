<?php
/**
 * File entry value object.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

/**
 * A single file scheduled for archiving.
 */
final class FileEntry {

	/**
	 * Absolute path on disk.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Path inside the archive, relative to the WordPress root.
	 *
	 * @var string
	 */
	private $relative;

	/**
	 * Size in bytes.
	 *
	 * @var int
	 */
	private $size;

	/**
	 * Constructor.
	 *
	 * @param string $path     Absolute path.
	 * @param string $relative Archive path.
	 * @param int    $size     Size in bytes.
	 */
	public function __construct( string $path, string $relative, int $size ) {
		$this->path     = $path;
		$this->relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
		$this->size     = $size;
	}

	/**
	 * Absolute path.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Archive path.
	 *
	 * @return string
	 */
	public function relative(): string {
		return $this->relative;
	}

	/**
	 * Size in bytes.
	 *
	 * @return int
	 */
	public function size(): int {
		return $this->size;
	}

	/**
	 * Serializable representation used in the manifest.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'p' => $this->relative,
			's' => $this->size,
		);
	}

	/**
	 * Build from a manifest line.
	 *
	 * @param array<string, mixed> $data Decoded line.
	 * @param string               $root WordPress root.
	 * @return self
	 */
	public static function from_array( array $data, string $root ): self {
		$relative = isset( $data['p'] ) ? (string) $data['p'] : '';

		return new self(
			rtrim( $root, '/' ) . '/' . $relative,
			$relative,
			isset( $data['s'] ) ? (int) $data['s'] : 0
		);
	}
}
