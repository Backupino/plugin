<?php
/**
 * Filesystem layout of the plugin.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

defined( 'ABSPATH' ) || exit;

use Backupino\Support\Fs;
use Backupino\Support\Str;

/**
 * Resolves every path the plugin writes to.
 */
final class Paths {

	/**
	 * Storage root (`wp-content/backupino` by default).
	 *
	 * @var string
	 */
	private $base;

	/**
	 * WordPress installation root.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Constructor.
	 *
	 * @param string $base Storage root.
	 * @param string $root WordPress root.
	 */
	public function __construct( string $base, string $root ) {
		$this->base = Str::untrailingslash( Fs::normalize( $base ) );
		$this->root = Str::trailingslash( Fs::normalize( $root ) );
	}

	/**
	 * Build from the current WordPress environment.
	 *
	 * Uses wp-content/backupino outside of public uploads for enhanced security and isolation.
	 *
	 * @return self
	 */
	public static function from_wordpress(): self {
		$content_dir = defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : ( (string) ABSPATH . 'wp-content' );
		$base        = $content_dir . '/backupino';

		return new self( $base, (string) ABSPATH );
	}

	/**
	 * Storage root.
	 *
	 * @return string
	 */
	public function base(): string {
		return $this->base;
	}

	/**
	 * WordPress root.
	 *
	 * @return string
	 */
	public function root(): string {
		return $this->root;
	}

	/**
	 * Log directory.
	 *
	 * @return string
	 */
	public function logs(): string {
		return $this->base . '/logs';
	}

	/**
	 * Directory that holds one working directory per job.
	 *
	 * @return string
	 */
	public function jobs(): string {
		return $this->base . '/jobs';
	}

	/**
	 * Working directory of a single job.
	 *
	 * @param string $token Job token.
	 * @return string
	 */
	public function job_dir( string $token ): string {
		return $this->jobs() . '/' . $token;
	}

	/**
	 * Directory containing finished packages.
	 *
	 * @return string
	 */
	public function packages(): string {
		return $this->base . '/packages';
	}

	/**
	 * Create directories and drop the web guards.
	 *
	 * @return void
	 */
	public function ensure(): void {
		foreach ( array( $this->base, $this->logs(), $this->jobs(), $this->packages() ) as $dir ) {
			Fs::ensure_dir( $dir );
			Fs::protect( $dir );
		}
	}

	/**
	 * Relative (web) path for a file inside the storage root.
	 *
	 * @param string $absolute Absolute path.
	 * @return string
	 */
	public function relative( string $absolute ): string {
		return Str::relative( $this->base, $absolute );
	}

	/**
	 * Whether a path lives inside the storage root (used to avoid backing up ourselves).
	 *
	 * @param string $absolute Absolute path.
	 * @return bool
	 */
	public function contains( string $absolute ): bool {
		$absolute = Fs::normalize( $absolute );

		return Str::starts_with( $absolute, $this->base . '/' ) || $absolute === $this->base;
	}
}
