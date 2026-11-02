<?php
/**
 * Resumable directory scanner.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Paths;
use Backupino\Jobs\Deadline;
use Backupino\Support\Fs;
use Backupino\Support\Str;

/**
 * Walks the WordPress root with an explicit stack so that scanning can be
 * interrupted and resumed in the middle of very large trees.
 *
 * The stack is stored in the job payload, which keeps memory usage flat
 * regardless of the number of directories.
 */
final class Scanner {

	/**
	 * Paths.
	 *
	 * @var Paths
	 */
	private $paths;

	/**
	 * Filter.
	 *
	 * @var FileFilter
	 */
	private $filter;

	/**
	 * Root directory that is scanned and used as the archive prefix.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Constructor.
	 *
	 * @param Paths      $paths  Paths (used to skip our own storage directory).
	 * @param FileFilter $filter Inclusion rules.
	 * @param string     $root   Directory to scan, defaults to the WordPress root.
	 */
	public function __construct( Paths $paths, FileFilter $filter, string $root = '' ) {
		$this->paths  = $paths;
		$this->filter = $filter;
		$this->root   = '' !== $root ? Str::untrailingslash( Fs::normalize( $root ) ) : Str::untrailingslash( $paths->root() );
	}

	/**
	 * Fresh scanning state.
	 *
	 * @return array<string, mixed>
	 */
	public static function initial_state(): array {
		return array(
			'stack'       => array( '' ),
			'files'       => 0,
			'bytes'       => 0,
			'directories' => 0,
			'skipped'     => 0,
			'done'        => false,
		);
	}

	/**
	 * Scan for at most `$deadline`.
	 *
	 * @param array<string, mixed> $state    Mutable scanning state.
	 * @param Deadline             $deadline Time budget.
	 * @param callable             $on_file  Callback receiving a {@see FileEntry}.
	 * @return array<string, mixed> Updated state.
	 */
	public function scan( array $state, Deadline $deadline, callable $on_file ): array {
		$state = array_merge( self::initial_state(), $state );

		while ( true ) {
			if ( array() === $state['stack'] ) {
				$state['done'] = true;
				break;
			}

			if ( $deadline->expired() ) {
				break;
			}

			$relative_dir = (string) array_pop( $state['stack'] );
			$absolute_dir = '' === $relative_dir ? $this->root : $this->root . '/' . $relative_dir;

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$items = @scandir( $absolute_dir );

			if ( false === $items ) {
				++$state['skipped'];
				continue;
			}

			++$state['directories'];

			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}

				$item_relative = '' === $relative_dir ? $item : $relative_dir . '/' . $item;
				$item_absolute = $absolute_dir . '/' . $item;

				if ( is_link( $item_absolute ) ) {
					++$state['skipped'];
					continue;
				}

				if ( is_dir( $item_absolute ) ) {
					if ( $this->paths->contains( $item_absolute ) ) {
						++$state['skipped'];
						continue;
					}

					if ( $this->filter->allows_dir( $item_relative ) ) {
						$state['stack'][] = $item_relative;
					} else {
						++$state['skipped'];
					}

					continue;
				}

				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$size = (int) @filesize( $item_absolute );

				if ( ! $this->filter->allows_file( $item_relative, $size ) ) {
					++$state['skipped'];
					continue;
				}

				call_user_func( $on_file, new FileEntry( $item_absolute, $item_relative, $size ) );

				++$state['files'];
				$state['bytes'] += $size;
			}
		}

		return $state;
	}
}
