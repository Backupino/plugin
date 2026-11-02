<?php
/**
 * Archive inclusion rules.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup;

/**
 * Decides which files enter the archive.
 *
 * Rule syntax:
 *  - `wp-content/cache/`  directory (trailing slash) and everything below it
 *  - `*.log`              any file whose basename matches
 *  - `node_modules/**`    `**` crosses directory boundaries
 *  - `wp-config.php`      exact relative path (at the archive root)
 */
final class FileFilter {

	/**
	 * Patterns that are always skipped.
	 *
	 * @var string[]
	 */
	private const DEFAULT_RULES = array(
		'wp-content/cache/',
		'wp-content/upgrade/',
		'wp-content/updraft/',
		'wp-content/ai1wm-backups/',
		'wp-content/backups/',
		'wp-content/backup-*/',
		'wp-content/backupino/',
		'wp-content/Backupino/',
		'wp-content/uploads/backupino/',
		'wp-content/uploads/backup-*/',
		'node_modules/',
		'.git/',
		'**/.git/',
		'.svn/',
		'.hg/',
		'.idea/',
		'.vscode/',
		'.DS_Store',
		'*.log',
		'*.tmp',
		'wp-config.php',
		'wp-config-local.php',
		'error_log',
		'.user.ini',
		'.htpasswd',
	);

	/**
	 * Active rules.
	 *
	 * @var string[]
	 */
	private $rules;

	/**
	 * Maximum file size in bytes, `0` for unlimited.
	 *
	 * @var int
	 */
	private $max_size;

	/**
	 * Constructor.
	 *
	 * @param string[] $rules     Extra rules supplied by the user.
	 * @param int      $max_size  Maximum file size, `0` to disable.
	 * @param bool     $defaults  Whether the built-in rules apply as well.
	 */
	public function __construct( array $rules = array(), int $max_size = 0, bool $defaults = true ) {
		$all = array();

		if ( $defaults ) {
			$all = self::DEFAULT_RULES;
		}

		foreach ( $rules as $rule ) {
			$rule = trim( (string) $rule );

			if ( '' !== $rule ) {
				$all[] = $rule;
			}
		}

		$this->rules    = array_values( array_unique( $all ) );
		$this->max_size = max( 0, $max_size );
	}

	/**
	 * Default exclusion patterns.
	 *
	 * @return string[]
	 */
	public static function defaults(): array {
		return self::DEFAULT_RULES;
	}

	/**
	 * Active rules.
	 *
	 * @return string[]
	 */
	public function rules(): array {
		return $this->rules;
	}

	/**
	 * Whether a directory should be traversed.
	 *
	 * @param string $relative Directory path relative to the root.
	 * @return bool
	 */
	public function allows_dir( string $relative ): bool {
		$relative = trim( $relative, '/' ) . '/';

		if ( '' === trim( $relative, '/' ) ) {
			return true;
		}

		foreach ( $this->rules as $rule ) {
			if ( $this->matches_rule( $rule, rtrim( $relative, '/' ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a file should be archived.
	 *
	 * @param string $relative File path relative to the root.
	 * @param int    $size     Size in bytes.
	 * @return bool
	 */
	public function allows_file( string $relative, int $size ): bool {
		$relative = ltrim( $relative, '/' );

		if ( '' === $relative ) {
			return false;
		}

		if ( $this->max_size > 0 && $size > $this->max_size ) {
			return false;
		}

		foreach ( $this->rules as $rule ) {
			if ( $this->matches_rule( $rule, $relative ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Test one rule against a path.
	 *
	 * @param string $rule Rule pattern.
	 * @param string $path Relative path.
	 * @return bool
	 */
	private function matches_rule( string $rule, string $path ): bool {
		$rule     = ltrim( str_replace( '\\', '/', $rule ), '/' );
		$dir_only = '/' === substr( $rule, -1 );

		if ( $dir_only ) {
			$rule = rtrim( $rule, '/' );
		}

		if ( '' === $rule ) {
			return false;
		}

		$path = trim( $path, '/' );
		$body = self::glob_to_regex( $rule );

		if ( $dir_only ) {
			// A directory rule such as `node_modules/` matches at any depth.
			if ( false === strpos( $rule, '/' ) ) {
				foreach ( explode( '/', $path ) as $segment ) {
					if ( preg_match( '#^' . $body . '$#', $segment ) ) {
						return true;
					}
				}

				return false;
			}

			// A path rule such as `wp-content/cache/` matches the directory and its content.
			return 1 === preg_match( '#^' . $body . '(?:/.*)?$#', $path );
		}

		if ( false === strpos( $rule, '/' ) ) {
			return 1 === preg_match( '#^' . $body . '$#', basename( $path ) );
		}

		return 1 === preg_match( '#^' . $body . '$#', $path );
	}

	/**
	 * Convert a glob to a regular expression body.
	 *
	 * @param string $glob Glob pattern.
	 * @return string
	 */
	public static function glob_to_regex( string $glob ): string {
		$out    = '';
		$length = strlen( $glob );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $glob[ $i ];

			if ( '*' === $char ) {
				if ( $i + 1 < $length && '*' === $glob[ $i + 1 ] ) {
					$out .= '.*';
					++$i;
				} else {
					$out .= '[^/]*';
				}

				continue;
			}

			if ( '?' === $char ) {
				$out .= '[^/]';
				continue;
			}

			$out .= preg_quote( $char, '#' );
		}

		return $out;
	}
}
