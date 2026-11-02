<?php
/**
 * Notification orchestrator.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Notify;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Settings;
use Backupino\Download\DownloadLink;
use Backupino\Jobs\Job;
use Backupino\Support\OptionsStore;

/**
 * Sends backup notifications and retries the ones that failed.
 *
 * Notifications are a courtesy: a broken messenger never fails a backup, it
 * only fills the retry queue.
 */
final class Notifier {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Retry queue.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Permanent link.
	 *
	 * @var DownloadLink
	 */
	private $link;

	/**
	 * Optional HTTP transport, used by the tests.
	 *
	 * @var callable|null
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
	 * @param Settings      $settings Settings.
	 * @param OptionsStore  $options  Options store for the queue.
	 * @param DownloadLink  $link     Permanent link service.
	 * @param callable|null $http     Optional HTTP transport.
	 */
	public function __construct( Settings $settings, OptionsStore $options, DownloadLink $link, ?callable $http = null ) {
		$this->settings = $settings;
		$this->queue    = new Queue( $options );
		$this->link     = $link;
		$this->http     = $http;
	}

	/**
	 * Configured service id (for backward compatibility).
	 *
	 * @return string
	 */
	public function service(): string {
		return (string) $this->settings->get( 'notify.service', 'none' );
	}

	/**
	 * Whether at least one messenger is configured well enough to send.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return count( $this->channels() ) > 0;
	}

	/**
	 * All configured and enabled transports.
	 *
	 * @return array<string, BotApi>
	 */
	public function channels(): array {
		$channels = array();

		// Telegram
		$tg_enabled = (bool) $this->settings->get( 'notify.telegram_enabled', false );
		$tg_token   = trim( (string) $this->settings->get( 'notify.telegram_token', '' ) );
		$tg_chat_id = trim( (string) $this->settings->get( 'notify.telegram_chat_id', '' ) );

		if ( ! $tg_enabled && 'telegram' === $this->service() ) {
			$tg_enabled = true;
			if ( '' === $tg_token ) {
				$tg_token = trim( (string) $this->settings->get( 'notify.token', '' ) );
			}
			if ( '' === $tg_chat_id ) {
				$tg_chat_id = trim( (string) $this->settings->get( 'notify.chat_id', '' ) );
			}
		}

		if ( $tg_enabled && '' !== $tg_token && '' !== $tg_chat_id ) {
			$tg = ChannelFactory::make( 'telegram', $tg_token, $tg_chat_id, $this->http );
			if ( null !== $tg && $tg->is_configured() ) {
				$channels['telegram'] = $tg;
			}
		}

		// Bale
		$bale_enabled = (bool) $this->settings->get( 'notify.bale_enabled', false );
		$bale_token   = trim( (string) $this->settings->get( 'notify.bale_token', '' ) );
		$bale_chat_id = trim( (string) $this->settings->get( 'notify.bale_chat_id', '' ) );

		if ( ! $bale_enabled && 'bale' === $this->service() ) {
			$bale_enabled = true;
			if ( '' === $bale_token ) {
				$bale_token = trim( (string) $this->settings->get( 'notify.token', '' ) );
			}
			if ( '' === $bale_chat_id ) {
				$bale_chat_id = trim( (string) $this->settings->get( 'notify.chat_id', '' ) );
			}
		}

		if ( $bale_enabled && '' !== $bale_token && '' !== $bale_chat_id ) {
			$bale = ChannelFactory::make( 'bale', $bale_token, $bale_chat_id, $this->http );
			if ( null !== $bale && $bale->is_configured() ) {
				$channels['bale'] = $bale;
			}
		}

		return $channels;
	}

	/**
	 * Configured transport (single / fallback).
	 *
	 * @return BotApi|null
	 */
	private function channel(): ?BotApi {
		$channels = $this->channels();
		if ( array() !== $channels ) {
			return reset( $channels );
		}

		return ChannelFactory::make(
			$this->service(),
			(string) $this->settings->get( 'notify.token', '' ),
			(string) $this->settings->get( 'notify.chat_id', '' ),
			$this->http
		);
	}

	/**
	 * Announce a finished backup to all enabled channels.
	 *
	 * @param Job    $job  Finished job.
	 * @param string $site Site name.
	 * @return bool
	 */
	public function announce( Job $job, string $site ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$package = isset( $job->payload['package'] ) && is_array( $job->payload['package'] )
			? $job->payload['package']
			: array();

		$files    = (bool) $job->option( 'files', true );
		$database = (bool) $job->option( 'database', true );

		if ( $files && $database ) {
			$kind = __( 'files and database', 'backupino' );
		} elseif ( $database ) {
			$kind = __( 'database only', 'backupino' );
		} else {
			$kind = __( 'files only', 'backupino' );
		}

		$template = (string) $this->settings->get( 'notify.msg_backup_template', '' );

		$package_name = isset( $package['name'] ) && '' !== trim( (string) $package['name'] )
			? (string) $package['name']
			: 'latest';

		$archive_link   = $this->link->url( $package_name, 'archive' );
		$installer_link = $this->link->url( $package_name, 'installer' );
		$landing_link   = $this->link->url( $package_name );

		$message = Message::backup_completed(
			$site,
			$kind,
			isset( $package['size'] ) ? (int) $package['size'] : 0,
			'' !== $job->created_at ? $job->created_at : gmdate( 'Y-m-d H:i' ),
			$landing_link,
			$template,
			$installer_link,
			$archive_link
		);

		return $this->send( $message );
	}

	/**
	 * Send the message behind the "test" button.
	 *
	 * @param string      $site             Site name.
	 * @param string|null $specific_service Specific service to test ('telegram'|'bale'|null).
	 * @return bool
	 */
	public function send_test( string $site, ?string $specific_service = null ): bool {
		return $this->send( Message::test( $site, $this->link->url() ), $specific_service );
	}

	/**
	 * Deliver a message, queueing it when messengers are unreachable.
	 *
	 * @param Message     $message          Message.
	 * @param string|null $specific_service Specific service or null for all.
	 * @return bool
	 */
	public function send( Message $message, ?string $specific_service = null ): bool {
		$this->error = '';

		$channels = $this->channels();

		if ( null !== $specific_service && '' !== $specific_service ) {
			if ( ! isset( $channels[ $specific_service ] ) ) {
				$this->error = sprintf(
					/* translators: %s: messenger service name. */
					__( '%s messenger is not enabled or not fully configured.', 'backupino' ),
					ucfirst( $specific_service )
				);

				return false;
			}
			$channels = array( $specific_service => $channels[ $specific_service ] );
		}

		if ( array() === $channels ) {
			$this->error = __( 'No messenger is configured.', 'backupino' );

			return false;
		}

		$success_count = 0;
		$errors        = array();

		foreach ( $channels as $service => $channel ) {
			if ( $channel->send( $message ) ) {
				++$success_count;
			} else {
				$errors[] = ucfirst( $service ) . ': ' . $channel->error();
			}
		}

		if ( $success_count > 0 ) {
			if ( array() !== $errors ) {
				$this->error = implode( ' | ', $errors );
			}

			return true;
		}

		$this->error = implode( ' | ', $errors );
		$this->queue->push( $message->text() );

		return false;
	}

	/**
	 * Retry queued messages across enabled channels.
	 *
	 * @return int Delivered messages.
	 */
	public function flush(): int {
		if ( $this->queue->is_empty() ) {
			return 0;
		}

		$channels = $this->channels();

		if ( array() === $channels ) {
			return 0;
		}

		return $this->queue->flush(
			static function ( string $text ) use ( $channels ) {
				$msg    = new Message( $text );
				$any_ok = false;
				foreach ( $channels as $channel ) {
					if ( $channel->send( $msg ) ) {
						$any_ok = true;
					}
				}

				return $any_ok;
			}
		);
	}

	/**
	 * Pending messages.
	 *
	 * @return int
	 */
	public function pending(): int {
		return count( $this->queue->all() );
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
