<?php
/**
 * Download access policy.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Download;

/**
 * Decides what a permanent-link request is allowed to do.
 *
 * Keeping the rules in one pure method makes the security behaviour testable
 * without booting WordPress.
 */
final class AccessPolicy {

	/**
	 * Stream the package.
	 */
	const ALLOW = 'allow';

	/**
	 * Send the visitor to the login screen.
	 */
	const LOGIN = 'login';

	/**
	 * Refuse: the key is wrong or the visitor may not manage backups.
	 */
	const FORBIDDEN = 'forbidden';

	/**
	 * The key is valid but no package exists yet.
	 */
	const MISSING = 'missing';

	/**
	 * Link service.
	 *
	 * @var DownloadLink
	 */
	private $link;

	/**
	 * Constructor.
	 *
	 * @param DownloadLink $link Link service.
	 */
	public function __construct( DownloadLink $link ) {
		$this->link = $link;
	}

	/**
	 * Evaluate a request.
	 *
	 * @param string $key         Key supplied by the visitor.
	 * @param bool   $logged_in   Whether the visitor has a session.
	 * @param bool   $authorized  Whether the visitor may manage backups.
	 * @param bool   $has_package Whether a package is available.
	 * @return string One of the class constants.
	 */
	public function evaluate( string $key, bool $logged_in, bool $authorized, bool $has_package ): string {
		if ( ! $this->link->matches( $key ) ) {
			return self::FORBIDDEN;
		}

		if ( ! $logged_in ) {
			return self::LOGIN;
		}

		if ( ! $authorized ) {
			return self::FORBIDDEN;
		}

		if ( ! $has_package ) {
			return self::MISSING;
		}

		return self::ALLOW;
	}
}
