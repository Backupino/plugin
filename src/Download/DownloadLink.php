<?php
/**
 * Permanent download link.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Download;

defined( 'ABSPATH' ) || exit;

use Backupino\Support\OptionsStore;

/**
 * Builds and validates the permanent "latest backup" URL.
 *
 * The URL never expires: the secret key inside it is a bearer token that is
 * only useful together with an authenticated admin session, so the link can be
 * pasted into a messenger and kept forever.
 */
final class DownloadLink {

	/**
	 * Query flag that routes a request to the download handler.
	 */
	const QUERY = 'backupino_download';

	/**
	 * Query argument that carries the secret key.
	 */
	const KEY_QUERY = 'backupino_key';

	/**
	 * Option that stores the secret key.
	 */
	const OPTION = 'backupino_download_key';

	/**
	 * Value that always resolves to the newest package.
	 */
	const VALUE = 'latest';

	/**
	 * Options store.
	 *
	 * @var OptionsStore
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param OptionsStore $options Options store.
	 */
	public function __construct( OptionsStore $options ) {
		$this->options = $options;
	}

	/**
	 * Secret key, created on first use.
	 *
	 * @return string
	 */
	public function key(): string {
		$key = (string) $this->options->get( self::OPTION, '' );

		if ( '' === $key ) {
			$key = bin2hex( random_bytes( 16 ) );
			$this->options->update( self::OPTION, $key );
		}

		return $key;
	}

	/**
	 * Whether a candidate key matches the stored secret.
	 *
	 * @param string $candidate Key from the request.
	 * @return bool
	 */
	public function matches( string $candidate ): bool {
		$key = $this->key();

		return '' !== $candidate && '' !== $key && hash_equals( $key, $candidate );
	}

	/**
	 * Permanent URL of a package or the newest package.
	 *
	 * @param string $target Target ('latest' or specific package name).
	 * @param string $file   Optional file type ('archive' or 'installer').
	 * @return string
	 */
	public function url( string $target = self::VALUE, string $file = '' ): string {
		$base = home_url( '/' );
		$glue = false === strpos( $base, '?' ) ? '?' : '&';

		$url = $base . $glue . self::QUERY . '=' . rawurlencode( $target ) . '&' . self::KEY_QUERY . '=' . rawurlencode( $this->key() );

		if ( '' !== $file ) {
			$url .= '&file=' . rawurlencode( $file );
		}

		return $url;
	}

	/**
	 * Route to the login screen, coming back here afterwards.
	 *
	 * @param string $target Target ('latest' or specific package name).
	 * @param string $file   Optional file type ('archive' or 'installer').
	 * @return string
	 */
	public function login_url( string $target = self::VALUE, string $file = '' ): string {
		return wp_login_url( $this->url( $target, $file ) );
	}
}
