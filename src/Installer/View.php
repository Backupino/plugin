<?php
/**
 * Installer view helpers.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Installer;

defined( 'BACKUPINO_INSTALLER' ) || defined( 'ABSPATH' ) || exit;

/**
 * Small HTML helpers used by the installer page.
 */
final class View {

	/**
	 * Escape for HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function e( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Render the checklist returned by {@see Env::check()}.
	 *
	 * @param array<int, array{label: string, ok: bool, detail: string}> $checks Checks.
	 * @return string
	 */
	public static function checks( array $checks ): string {
		$out = '<ul class="checks">';

		foreach ( $checks as $check ) {
			$out .= sprintf(
				'<li class="%s"><span class="badge">%s</span><strong class="check-label">%s</strong><em class="check-detail">%s</em></li>',
				$check['ok'] ? 'ok' : 'fail',
				$check['ok'] ? '&#10003;' : '&#10007;',
				self::e( $check['label'] ),
				self::e( $check['detail'] )
			);
		}

		return $out . '</ul>';
	}

	/**
	 * Wrap content in the page shell.
	 *
	 * @param string $payload_json JSON payload for the client script.
	 * @param string $body         Body HTML.
	 * @return string
	 */
	public static function page( string $payload_json, string $body ): string {
		$layout = self::layout();

		$output = str_replace( '__CONFIG_JSON__', htmlspecialchars( $payload_json, ENT_QUOTES, 'UTF-8' ), $layout );
		return str_replace( '<!-- __BODY__ -->', $body, $output );
	}

	/**
	 * Base HTML layout template.
	 *
	 * @return string
	 */
	private static function layout(): string {
		$template_file = __DIR__ . '/installer-layout.html';
		if ( is_readable( $template_file ) ) {
			$raw      = (string) file_get_contents( $template_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$css_file = dirname( __DIR__, 2 ) . '/assets/css/installer.css';
			$js_file  = dirname( __DIR__, 2 ) . '/assets/js/installer.js';
			$css      = is_readable( $css_file ) ? (string) file_get_contents( $css_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$js       = is_readable( $js_file ) ? (string) file_get_contents( $js_file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
			$raw      = str_replace( '/* __INSTALLER_CSS__ */', $css, $raw );
			return str_replace( '/* __INSTALLER_JS__ */', $js, $raw );
		}

		return '/* __BACKUPINO_COMPILED_LAYOUT__ */';
	}
}
