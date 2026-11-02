<?php
/**
 * Notification retry queue.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Notify;

use Backupino\Support\OptionsStore;

/**
 * Keeps messages that could not be delivered yet.
 *
 * Messengers go down and sites lose connectivity; a backup is never failed
 * because of a notification, so undelivered texts wait here and are retried on
 * the next cron tick.
 */
final class Queue {

	/**
	 * Option that holds the queue.
	 */
	const OPTION = 'backupino_notify_queue';

	/**
	 * Maximum number of pending messages.
	 */
	const LIMIT = 5;

	/**
	 * Attempts before a message is dropped.
	 */
	const MAX_ATTEMPTS = 3;

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
	 * Pending messages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$items = $this->options->get( self::OPTION, array() );

		return is_array( $items ) ? array_values( $items ) : array();
	}

	/**
	 * Whether anything is waiting.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->all();
	}

	/**
	 * Add a message to the queue, dropping the oldest when full.
	 *
	 * @param string $text Body.
	 * @return void
	 */
	public function push( string $text ): void {
		if ( '' === trim( $text ) ) {
			return;
		}

		$items = $this->all();

		$items[] = array(
			'text'     => $text,
			'attempts' => 0,
		);

		if ( count( $items ) > self::LIMIT ) {
			$items = array_slice( $items, -self::LIMIT );
		}

		$this->options->update( self::OPTION, $items );
	}

	/**
	 * Retry every pending message.
	 *
	 * @param callable $sender `function ( string $text ): bool`.
	 * @return int Number of delivered messages.
	 */
	public function flush( callable $sender ): int {
		$items     = $this->all();
		$remaining = array();
		$delivered = 0;

		foreach ( $items as $item ) {
			$text     = isset( $item['text'] ) ? (string) $item['text'] : '';
			$attempts = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;

			if ( $sender( $text ) ) {
				++$delivered;
				continue;
			}

			++$attempts;

			if ( $attempts >= self::MAX_ATTEMPTS ) {
				continue;
			}

			$remaining[] = array(
				'text'     => $text,
				'attempts' => $attempts,
			);
		}

		if ( array() === $remaining ) {
			$this->options->delete( self::OPTION );
		} else {
			$this->options->update( self::OPTION, $remaining );
		}

		return $delivered;
	}

	/**
	 * Drop everything.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->options->delete( self::OPTION );
	}
}
