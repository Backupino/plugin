<?php
/**
 * Bot API transport.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Notify;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to a Telegram compatible Bot API.
 *
 * Bale exposes the same endpoints as Telegram, so a single transport serves
 * both services; only the base URL changes.
 */
final class BotApi {

	/**
	 * Base URL without the trailing slash.
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Bot token.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Target chat id.
	 *
	 * @var string
	 */
	private $chat_id;

	/**
	 * HTTP transport: `function ( string $url, array $args ): array`.
	 *
	 * @var callable
	 */
	private $http;

	/**
	 * Last error.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Constructor.
	 *
	 * @param string        $base    Base URL, e.g. `https://api.telegram.org`.
	 * @param string        $token   Bot token.
	 * @param string        $chat_id Target chat.
	 * @param callable|null $http    Optional HTTP transport.
	 */
	public function __construct( string $base, string $token, string $chat_id, ?callable $http = null ) {
		$this->base    = rtrim( $base, '/' );
		$this->token   = trim( $token );
		$this->chat_id = trim( $chat_id );

		$this->http = $http ?? static function ( string $url, array $args ): array {
			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				return array(
					'code'  => 0,
					'body'  => '',
					'error' => $response->get_error_message(),
				);
			}

			return array(
				'code'  => (int) wp_remote_retrieve_response_code( $response ),
				'body'  => (string) wp_remote_retrieve_body( $response ),
				'error' => '',
			);
		};
	}

	/**
	 * Whether the channel has everything it needs.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->token && '' !== $this->chat_id;
	}

	/**
	 * Endpoint of the `sendMessage` method.
	 *
	 * @return string
	 */
	public function endpoint(): string {
		return $this->base . '/bot' . $this->token . '/sendMessage';
	}

	/**
	 * Payload for a message.
	 *
	 * @param Message $message Message.
	 * @return array<string, mixed>
	 */
	/**
	 * Whether the current channel connects to Bale.
	 *
	 * @return bool
	 */
	public function is_bale(): bool {
		return false !== strpos( $this->base, 'bale.ai' );
	}

	/**
	 * Format message text appropriately for Bale (Markdown with [text](url)).
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function format_for_bale( string $text ): string {
		// Convert HTML links to Markdown links for Bale: <a href="URL">TEXT</a> -> [TEXT](URL)
		$formatted = preg_replace( '/<a(?:\s+[^>]*)?\bhref=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text );
		// Convert <b> and <strong> to *bold*
		$formatted = preg_replace( '/<(?:b|strong)>(.*?)<\/(?:b|strong)>/is', '*$1*', (string) $formatted );
		// Strip any remaining HTML tags
		return strip_tags( (string) $formatted );
	}

	/**
	 * Format message text appropriately for Telegram (HTML with escaped attribute entities).
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function format_for_telegram( string $text ): string {
		// Convert Markdown [TEXT](URL) to HTML <a href="URL">TEXT</a>
		$formatted = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/i', '<a href="$2">$1</a>', $text );
		// Ensure any raw & inside <a href="..."> is escaped as &amp;
		return (string) preg_replace_callback(
			'/(<a\s+href=[\'"])([^\'"]+)([\'"])/i',
			static function( array $m ): string {
				$url = htmlspecialchars( htmlspecialchars_decode( $m[2] ), ENT_COMPAT, 'UTF-8' );
				return $m[1] . $url . $m[3];
			},
			(string) $formatted
		);
	}

	/**
	 * Payload for a message.
	 *
	 * @param Message $message Message.
	 * @return array<string, mixed>
	 */
	public function payload( Message $message ): array {
		if ( $this->is_bale() ) {
			return array(
				'chat_id'                  => $this->chat_id,
				'text'                     => self::format_for_bale( $message->text() ),
				'parse_mode'               => 'Markdown',
				'disable_web_page_preview' => true,
			);
		}

		return array(
			'chat_id'                  => $this->chat_id,
			'text'                     => self::format_for_telegram( $message->text() ),
			'parse_mode'               => 'HTML',
			'disable_web_page_preview' => true,
		);
	}

	/**
	 * Deliver a message.
	 *
	 * @param Message $message Message.
	 * @return bool
	 */
	public function send( Message $message ): bool {
		$this->error = '';

		if ( ! $this->is_configured() ) {
			$this->error = __( 'The bot token or chat id is missing.', 'backupino' );

			return false;
		}

		if ( $message->is_empty() ) {
			$this->error = __( 'There is nothing to send.', 'backupino' );

			return false;
		}

		$payload = $this->payload( $message );

		$result = ( $this->http )(
			$this->endpoint(),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => (string) wp_json_encode( $payload ),
			)
		);

		$result = is_array( $result ) ? $result : array();

		if ( ! empty( $result['error'] ) ) {
			$this->error = (string) $result['error'];

			return false;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$body = isset( $result['body'] ) ? (string) $result['body'] : '';
		$json = json_decode( $body, true );

		// Fallback retry with stripped plain text and without parse_mode if entity parsing failed
		if ( 200 !== $code && isset( $payload['parse_mode'] ) ) {
			$fallback_payload = array(
				'chat_id'                  => $this->chat_id,
				'text'                     => strip_tags( $message->text() ),
				'disable_web_page_preview' => true,
			);
			$retry_result = ( $this->http )(
				$this->endpoint(),
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => (string) wp_json_encode( $fallback_payload ),
				)
			);

			if ( is_array( $retry_result ) && 200 === (int) ( $retry_result['code'] ?? 0 ) ) {
				$result = $retry_result;
				$code   = 200;
				$body   = (string) ( $retry_result['body'] ?? '' );
				$json   = json_decode( $body, true );
			}
		}

		if ( 200 !== $code || ! is_array( $json ) ) {
			/* translators: %d: HTTP status code. */
			$this->error = sprintf( __( 'The messenger answered with HTTP %d.', 'backupino' ), $code );

			return false;
		}

		if ( empty( $json['ok'] ) ) {
			$this->error = isset( $json['description'] )
				? (string) $json['description']
				: __( 'The messenger rejected the message.', 'backupino' );

			return false;
		}

		return true;
	}

	/**
	 * Last error.
	 *
	 * @return string
	 */
	public function error(): string {
		return $this->error;
	}
}
