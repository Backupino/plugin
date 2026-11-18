<?php
/**
 * Installer state persistence.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Standalone installer running outside WordPress environment.

/**
 * Stores wizard progress between AJAX requests outside the web document root,
 * wrapped in an executable PHP 403 guard and authenticated encryption.
 */
final class State {

	/**
	 * Executable PHP guard prepended to every state file so direct HTTP access
	 * fails closed even on misconfigured web servers (Apache, Nginx, IIS).
	 *
	 * @var string
	 */
	public const GUARD_PREFIX = "<?php http_response_code( 403 ); exit; ?>\n";

	/**
	 * Absolute path of the state file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Secret key used for authenticated encryption at rest.
	 *
	 * @var string
	 */
	private $secret;

	/**
	 * State data.
	 *
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * Constructor.
	 *
	 * @param string $file   Absolute path.
	 * @param string $secret Optional secret key for authenticated encryption.
	 */
	public function __construct( string $file, string $secret = '' ) {
		$this->file   = $file;
		$this->secret = $secret;
		$this->load();
	}

	/**
	 * Normalize a filesystem path for cross-platform prefix comparisons.
	 *
	 * @param string $path Raw filesystem path.
	 * @return string Normalized path without trailing slash (except root).
	 */
	public static function normalize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}

		$resolved = @realpath( $path );
		if ( is_string( $resolved ) && '' !== $resolved ) {
			$path = $resolved;
		}

		$path   = str_replace( '\\', '/', $path );
		$prefix = '';

		if ( preg_match( '/^([A-Za-z]:)\/(.*)$/', $path, $m ) ) {
			$prefix = $m[1] . '/';
			$rest   = $m[2];
		} elseif ( 0 === strpos( $path, '/' ) ) {
			$prefix = '/';
			$rest   = ltrim( $path, '/' );
		} else {
			$rest = $path;
		}

		$segments = array();
		foreach ( explode( '/', $rest ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $segments );
				continue;
			}
			$segments[] = $part;
		}

		$normalized = $prefix . implode( '/', $segments );

		return '' === $normalized ? $prefix : $normalized;
	}

	/**
	 * Check whether a candidate directory is strictly outside the installer
	 * working directory and the web server document root.
	 *
	 * @param string      $candidate_dir Candidate storage directory.
	 * @param string      $web_dir       Installer working directory.
	 * @param string|null $doc_root      Web server DOCUMENT_ROOT, if known.
	 * @return bool
	 */
	public static function is_outside_webroot( string $candidate_dir, string $web_dir, ?string $doc_root = null ): bool {
		$candidate = self::normalize_path( $candidate_dir );
		if ( '' === $candidate || ! is_dir( $candidate_dir ) ) {
			return false;
		}

		$roots = array();
		$web   = self::normalize_path( $web_dir );
		if ( '' !== $web ) {
			$roots[] = $web;
		}
		if ( null !== $doc_root && '' !== trim( $doc_root ) ) {
			$doc = self::normalize_path( $doc_root );
			if ( '' !== $doc ) {
				$roots[] = $doc;
			}
		}

		$case_insensitive = ( '\\' === DIRECTORY_SEPARATOR ) || ( 1 === preg_match( '/^[A-Za-z]:\//', $candidate ) );
		$cmp_candidate    = $case_insensitive ? strtolower( $candidate ) : $candidate;

		foreach ( $roots as $root ) {
			$cmp_root = $case_insensitive ? strtolower( $root ) : $root;
			$cmp_root = rtrim( $cmp_root, '/' );
			if ( '' === $cmp_root ) {
				continue;
			}

			if ( $cmp_candidate === $cmp_root || 0 === strpos( $cmp_candidate, $cmp_root . '/' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve a writable, non-HTTP-accessible state file path strictly outside
	 * the web document root and installer directory.
	 *
	 * Fails closed with a RuntimeException if no secure external storage directory
	 * is available, ensuring sensitive credentials are never written to webroot.
	 *
	 * @param string             $web_dir      Installer working directory.
	 * @param string             $token        Installer security token.
	 * @param string             $archive_name Archive basename for per-package isolation.
	 * @param string|null        $doc_root     Web server DOCUMENT_ROOT (defaults to $_SERVER['DOCUMENT_ROOT']).
	 * @param array<int, string> $candidates   Optional explicit candidate directories (for testing).
	 * @return string Absolute path to the secure `.php` state file.
	 *
	 * @throws \RuntimeException If no writable directory outside the web root is available.
	 */
	public static function resolve_secure_path(
		string $web_dir,
		string $token,
		string $archive_name = '',
		?string $doc_root = null,
		?array $candidates = null
	): string {
		if ( '' === trim( $token ) ) {
			throw new \RuntimeException( 'Installer security token is missing.' );
		}

		if ( null === $doc_root && isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$doc_root = (string) $_SERVER['DOCUMENT_ROOT'];
		}

		if ( null === $candidates ) {
			$candidates = array();

			$sys_tmp = sys_get_temp_dir();
			if ( is_string( $sys_tmp ) && '' !== trim( $sys_tmp ) ) {
				$candidates[] = $sys_tmp;
			}

			$upload_tmp = (string) ini_get( 'upload_tmp_dir' );
			if ( '' !== trim( $upload_tmp ) ) {
				$candidates[] = $upload_tmp;
			}

			if ( function_exists( 'session_save_path' ) ) {
				$sess_dir = (string) session_save_path();
				if ( '' !== trim( $sess_dir ) ) {
					if ( false !== strpos( $sess_dir, ';' ) ) {
						$parts    = explode( ';', $sess_dir );
						$sess_dir = (string) end( $parts );
					}
					if ( '' !== trim( $sess_dir ) ) {
						$candidates[] = $sess_dir;
					}
				}
			}
		}

		$fingerprint = hash_hmac(
			'sha256',
			self::normalize_path( $web_dir ) . '|' . basename( $archive_name ),
			$token
		);
		$filename = 'backupino-state-' . substr( $fingerprint, 0, 32 ) . '.php';

		$checked = array();
		foreach ( $candidates as $dir ) {
			$dir = trim( (string) $dir );
			if ( '' === $dir || ! is_dir( $dir ) ) {
				continue;
			}

			$normalized = self::normalize_path( $dir );
			if ( '' === $normalized || isset( $checked[ $normalized ] ) ) {
				continue;
			}
			$checked[ $normalized ] = true;

			if ( ! self::is_outside_webroot( $normalized, $web_dir, $doc_root ) ) {
				continue;
			}

			$probe = rtrim( $normalized, '/' ) . '/.backupino-probe-' . bin2hex( random_bytes( 6 ) );
			if ( false === @file_put_contents( $probe, 'ok', LOCK_EX ) ) {
				continue;
			}
			@chmod( $probe, 0600 );
			@unlink( $probe );

			return rtrim( $normalized, '/' ) . '/' . $filename;
		}

		throw new \RuntimeException(
			'Secure temporary storage outside the web document root is unavailable. Installation stopped to protect sensitive credentials.'
		);
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

		$raw = (string) @file_get_contents( $this->file );
		if ( 0 === strpos( $raw, self::GUARD_PREFIX ) ) {
			$raw = substr( $raw, strlen( self::GUARD_PREFIX ) );
		}

		$parsed = json_decode( $raw, true );
		if ( ! is_array( $parsed ) ) {
			$this->data = array();

			return;
		}

		if ( '' !== $this->secret ) {
			$decrypted  = $this->decrypt_envelope( $parsed );
			$this->data = is_array( $decrypted ) ? $decrypted : array();

			return;
		}

		$this->data = $parsed;
	}

	/**
	 * Persist state atomically with PHP guard, encryption (when secret provided),
	 * and restrictive 0600 permissions.
	 *
	 * @return bool
	 */
	public function save(): bool {
		$plaintext = json_encode( $this->data );
		if ( ! is_string( $plaintext ) ) {
			return false;
		}

		if ( '' !== $this->secret ) {
			$envelope = $this->encrypt_envelope( $plaintext );
			$encoded  = json_encode( $envelope );
			if ( ! is_string( $encoded ) ) {
				return false;
			}
		} else {
			$encoded = $plaintext;
		}

		$payload = self::GUARD_PREFIX . $encoded;
		$temp    = $this->file . '.tmp.' . bin2hex( random_bytes( 4 ) );

		if ( false === @file_put_contents( $temp, $payload, LOCK_EX ) ) {
			return false;
		}

		@chmod( $temp, 0600 );

		if ( ! @rename( $temp, $this->file ) ) {
			@unlink( $temp );

			return false;
		}

		@chmod( $this->file, 0600 );

		return true;
	}

	/**
	 * Absolute path of the state file.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Read a value.
	 *
	 * @param string $key      Key.
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
	 * Remove a single key from state.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public function remove( string $key ): void {
		unset( $this->data[ $key ] );
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
	 * Purge sensitive credential keys from state immediately.
	 *
	 * When `$keep_auth_flag` is true and the session was authenticated with an
	 * installer password, only the minimal non-sensitive authentication flag is
	 * retained so the final cleanup step can still be authorized. Otherwise the
	 * entire state file is destroyed immediately.
	 *
	 * @param bool $keep_auth_flag Whether to preserve minimal non-sensitive auth status.
	 * @return void
	 */
	public function purge_sensitive( bool $keep_auth_flag = false ): void {
		unset( $this->data['db'], $this->data['admin_account'] );

		if ( $keep_auth_flag && ! empty( $this->data['authenticated'] ) ) {
			$this->data = array(
				'authenticated' => true,
				'finished'      => ! empty( $this->data['finished'] ),
			);
			$this->save();

			return;
		}

		$this->destroy();
	}

	/**
	 * Delete the state file and any temporary sibling file.
	 *
	 * @return void
	 */
	public function destroy(): void {
		$this->data = array();

		if ( is_file( $this->file ) ) {
			@chmod( $this->file, 0600 );
			@unlink( $this->file );
		}

		if ( is_file( $this->file . '.tmp' ) ) {
			@unlink( $this->file . '.tmp' );
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

	/**
	 * Encrypt a JSON string into an authenticated envelope.
	 *
	 * @param string $plaintext JSON payload.
	 * @return array<string, mixed>
	 */
	private function encrypt_envelope( string $plaintext ): array {
		$enc_key = hash_hmac( 'sha256', 'backupino-state-enc', $this->secret, true );
		$mac_key = hash_hmac( 'sha256', 'backupino-state-mac', $this->secret, true );

		if ( function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_get_cipher_methods' ) ) {
			$methods = openssl_get_cipher_methods();

			if ( in_array( 'aes-256-gcm', $methods, true ) ) {
				$iv  = random_bytes( 12 );
				$tag = '';
				$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $enc_key, OPENSSL_RAW_DATA, $iv, $tag );
				if ( is_string( $ct ) && '' !== $tag ) {
					return array(
						'v'   => 2,
						'alg' => 'aes-256-gcm',
						'iv'  => base64_encode( $iv ),
						'tag' => base64_encode( $tag ),
						'ct'  => base64_encode( $ct ),
					);
				}
			}

			if ( in_array( 'aes-256-cbc', $methods, true ) ) {
				$iv = random_bytes( 16 );
				$ct = openssl_encrypt( $plaintext, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
				if ( is_string( $ct ) ) {
					$mac = hash_hmac( 'sha256', $iv . $ct, $mac_key, true );

					return array(
						'v'   => 2,
						'alg' => 'aes-256-cbc-hmac',
						'iv'  => base64_encode( $iv ),
						'mac' => base64_encode( $mac ),
						'ct'  => base64_encode( $ct ),
					);
				}
			}
		}

		// OpenSSL fallback: authenticated obfuscated payload (still outside webroot + PHP 403 guard + 0600).
		$salt = random_bytes( 16 );
		$ct   = self::xor_stream( $plaintext, $enc_key, $salt );
		$mac  = hash_hmac( 'sha256', $salt . $ct, $mac_key, true );

		return array(
			'v'    => 2,
			'alg'  => 'hmac-stream',
			'salt' => base64_encode( $salt ),
			'mac'  => base64_encode( $mac ),
			'ct'   => base64_encode( $ct ),
		);
	}

	/**
	 * Decrypt and authenticate a persisted state envelope.
	 *
	 * @param array<string, mixed> $envelope Decoded envelope.
	 * @return array<string, mixed>|null
	 */
	private function decrypt_envelope( array $envelope ): ?array {
		if ( 2 !== (int) ( $envelope['v'] ?? 0 ) || empty( $envelope['alg'] ) || empty( $envelope['ct'] ) ) {
			return null;
		}

		$enc_key   = hash_hmac( 'sha256', 'backupino-state-enc', $this->secret, true );
		$mac_key   = hash_hmac( 'sha256', 'backupino-state-mac', $this->secret, true );
		$alg       = (string) $envelope['alg'];
		$ct        = base64_decode( (string) $envelope['ct'], true );
		$plaintext = false;

		if ( ! is_string( $ct ) ) {
			return null;
		}

		if ( 'aes-256-gcm' === $alg && function_exists( 'openssl_decrypt' ) ) {
			$iv  = base64_decode( (string) ( $envelope['iv'] ?? '' ), true );
			$tag = base64_decode( (string) ( $envelope['tag'] ?? '' ), true );
			if ( is_string( $iv ) && is_string( $tag ) ) {
				$plaintext = openssl_decrypt( $ct, 'aes-256-gcm', $enc_key, OPENSSL_RAW_DATA, $iv, $tag );
			}
		} elseif ( 'aes-256-cbc-hmac' === $alg && function_exists( 'openssl_decrypt' ) ) {
			$iv  = base64_decode( (string) ( $envelope['iv'] ?? '' ), true );
			$mac = base64_decode( (string) ( $envelope['mac'] ?? '' ), true );
			if ( is_string( $iv ) && is_string( $mac ) ) {
				$expected = hash_hmac( 'sha256', $iv . $ct, $mac_key, true );
				if ( hash_equals( $expected, $mac ) ) {
					$plaintext = openssl_decrypt( $ct, 'aes-256-cbc', $enc_key, OPENSSL_RAW_DATA, $iv );
				}
			}
		} elseif ( 'hmac-stream' === $alg ) {
			$salt = base64_decode( (string) ( $envelope['salt'] ?? '' ), true );
			$mac  = base64_decode( (string) ( $envelope['mac'] ?? '' ), true );
			if ( is_string( $salt ) && is_string( $mac ) ) {
				$expected = hash_hmac( 'sha256', $salt . $ct, $mac_key, true );
				if ( hash_equals( $expected, $mac ) ) {
					$plaintext = self::xor_stream( $ct, $enc_key, $salt );
				}
			}
		}

		if ( ! is_string( $plaintext ) ) {
			return null;
		}

		$decoded = json_decode( $plaintext, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Deterministic HMAC-SHA256 counter-mode XOR stream for environments without OpenSSL.
	 *
	 * @param string $input Data to encrypt or decrypt.
	 * @param string $key   32-byte key.
	 * @param string $salt  16-byte nonce/salt.
	 * @return string
	 */
	private static function xor_stream( string $input, string $key, string $salt ): string {
		$len     = strlen( $input );
		$out     = '';
		$counter = 0;

		for ( $offset = 0; $offset < $len; $offset += 32 ) {
			$block   = hash_hmac( 'sha256', $salt . pack( 'N', $counter ), $key, true );
			$chunk   = substr( $input, $offset, 32 );
			$out    .= $chunk ^ substr( $block, 0, strlen( $chunk ) );
			++$counter;
		}

		return $out;
	}
}
