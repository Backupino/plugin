<?php
/**
 * Channel factory.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Notify;

/**
 * Maps the configured service to a Bot API transport.
 */
final class ChannelFactory {

	/**
	 * Telegram Bot API.
	 */
	const TELEGRAM = 'telegram';

	/**
	 * Bale Bot API.
	 */
	const BALE = 'bale';

	/**
	 * Supported services.
	 *
	 * @return array<string, string>
	 */
	public static function services(): array {
		return array(
			self::TELEGRAM => __( 'Telegram', 'backupino' ),
			self::BALE     => __( 'Bale', 'backupino' ),
		);
	}

	/**
	 * Base URL of a service.
	 *
	 * @param string $service Service id.
	 * @return string Empty when the service is unknown.
	 */
	public static function base_url( string $service ): string {
		$map = array(
			self::TELEGRAM => 'https://api.telegram.org',
			self::BALE     => 'https://tapi.bale.ai',
		);

		return isset( $map[ $service ] ) ? $map[ $service ] : '';
	}

	/**
	 * Build the transport for a service.
	 *
	 * @param string        $service Service id.
	 * @param string        $token   Bot token.
	 * @param string        $chat_id Target chat.
	 * @param callable|null $http    Optional HTTP transport.
	 * @return BotApi|null Null when the service is disabled or unknown.
	 */
	public static function make( string $service, string $token, string $chat_id, ?callable $http = null ): ?BotApi {
		$base = self::base_url( $service );

		if ( '' === $base ) {
			return null;
		}

		return new BotApi( $base, $token, $chat_id, $http );
	}
}
