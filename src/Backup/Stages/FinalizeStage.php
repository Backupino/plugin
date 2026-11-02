<?php
/**
 * Finalize stage.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Backup\Stages;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal pipeline exceptions are caught and sanitized before presentation.

use Backupino\Backup\InstallerBuilder;
use Backupino\Backup\Package;
use Backupino\Backup\ZipArchiveWriter;
use Backupino\Jobs\JobContext;
use Backupino\Jobs\JobException;
use Backupino\Jobs\Stage;
use Backupino\Jobs\StageResult;
use Backupino\Storage\StorageManager;
use Backupino\Support\Fs;
use Backupino\Support\Str;

/**
 * Seals the archive, builds the installer and uploads the package.
 */
final class FinalizeStage implements Stage {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'finalize';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Finalizing the package', 'backupino' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function weight(): int {
		return 10;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( JobContext $context ): StageResult {
		$job   = $context->job();
		$state = $job->stage_state( $this->id() );

		if ( array() === $state ) {
			$state = array(
				'step' => 'seal',
				'done' => false,
			);
		}

		$work    = $context->work_dir();
		$archive = $work . '/archive.zip';
		$db_file = $work . '/database.sql';

		if ( 'seal' === $state['step'] ) {
			$state = $this->seal( $context, $state, $archive, $db_file );
		}

		if ( 'seal' === $state['step'] ) {
			$job->set_stage_state( $this->id(), $state );

			return StageResult::running( 0.3, __( 'Sealing the archive…', 'backupino' ) );
		}

		if ( 'hash' === $state['step'] ) {
			$hash = (string) hash_file( 'sha256', $archive );

			if ( '' === $hash ) {
				throw new JobException( __( 'The archive could not be hashed.', 'backupino' ) );
			}

			$state['hash'] = $hash;
			$state['size'] = Fs::size( $archive );
			$state['step'] = 'installer';
		}

		$name = isset( $state['name'] ) ? (string) $state['name'] : Package::default_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( 'installer' === $state['step'] ) {
			$installer = $this->build_installer( $context, $state, $name );

			if ( '' === $installer ) {
				throw new JobException( __( 'The installer file could not be generated.', 'backupino' ) );
			}

			$state['installer'] = $installer;
			$state['name']      = $name;
			$state['step']      = 'publish';
		}

		if ( 'publish' === $state['step'] ) {
			$state = $this->publish( $context, $state, $archive, $name );
		}

		$package = Package::create(
			(string) $state['name'],
			(string) $state['archive'],
			(string) $state['installer'],
			(int) $state['size'],
			(string) $state['hash'],
			array(
				'created' => $context->clock()->now_mysql(),
				'type'    => (string) $job->option( 'type', 'full' ),
			)
		);

		$job->payload['package'] = $package->to_array();
		$job->set_stage_state( $this->id(), $state );

		return StageResult::completed(
			sprintf(
				/* translators: 1: package name, 2: package size. */
				__( 'Package %1$s is ready (%2$s).', 'backupino' ),
				$package->name,
				\Backupino\Support\Bytes::format( $package->size )
			)
		);
	}

	/**
	 * Add the remaining files to the archive and close it.
	 *
	 * @param JobContext           $context Context.
	 * @param array<string, mixed> $state   Stage state.
	 * @param string               $archive Archive path.
	 * @param string               $db_file Database dump path.
	 * @return array<string, mixed>
	 */
	private function seal( JobContext $context, array $state, string $archive, string $db_file ): array {
		if ( ! is_file( $archive ) && ! $context->settings()->get( 'archive.files', true ) ) {
			$state['name'] = Package::default_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		}

		$writer = new ZipArchiveWriter( (bool) $context->settings()->get( 'advanced.compression', true ) );

		if ( ! $writer->open( $archive ) ) {
			throw new JobException( $writer->error() );
		}

		if ( $context->settings()->get( 'archive.database', true ) && is_file( $db_file ) ) {
			if ( ! $writer->add_file( $db_file, 'database.sql' ) ) {
				throw new JobException( $writer->error() );
			}
		}

		$manifest = $context->work_dir() . '/manifest.ndjson';

		if ( is_file( $manifest ) ) {
			$writer->add_file( $manifest, 'backupino-manifest.ndjson' );
		}

		$wp_config_file = Str::untrailingslash( $context->paths()->root() ) . '/wp-config.php';
		if ( is_file( $wp_config_file ) && is_readable( $wp_config_file ) ) {
			$writer->add_file( $wp_config_file, 'wp-config.backupino-orig.php' );
		}

		$meta = array(
			'generator'    => 'Backupino',
			'version'      => defined( 'BACKUPINO_VERSION' ) ? BACKUPINO_VERSION : '1.0.0',
			'created'      => $context->clock()->now_mysql(),
			'site_url'     => home_url(),
			'home_url'     => home_url(),
			'table_prefix' => $context->db()->prefix(),
		);

		$writer->add_from_string( 'backupino-package.json', (string) wp_json_encode( $meta, JSON_PRETTY_PRINT ) );

		if ( ! $writer->close() ) {
			throw new JobException( $writer->error() );
		}

		$state['step'] = 'hash';
		$state['name'] = isset( $state['name'] ) ? $state['name'] : Package::default_name( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		return $state;
	}

	/**
	 * Render the installer file into the job directory.
	 *
	 * @param JobContext           $context Context.
	 * @param array<string, mixed> $state   Stage state.
	 * @param string               $name    Package name.
	 * @return string Absolute installer path.
	 */
	private function build_installer( JobContext $context, array $state, string $name ): string {
		$builder = new InstallerBuilder();

		$notify_settings = (array) $context->settings()->get( 'notify', array() );

		$payload = array(
			'version'      => defined( 'BACKUPINO_VERSION' ) ? BACKUPINO_VERSION : '1.0.0',
			'name'         => $name,
			'token'        => Str::random( 16 ),
			'archive_hash' => (string) $state['hash'],
			'archive_size' => (int) $state['size'],
			'site_url'     => home_url(),
			'home_url'     => home_url(),
			'site_name'    => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : (string) wp_parse_url( home_url(), PHP_URL_HOST ),
			'locale'       => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
			'table_prefix' => $context->db()->prefix(),
			'created'      => $context->clock()->now_mysql(),
			'password'     => (string) $context->settings()->get( 'installer.password', '' ),
			'notify'       => array(
				'telegram_enabled'     => ! empty( $notify_settings['telegram_enabled'] ),
				'telegram_token'       => (string) ( $notify_settings['telegram_token'] ?? '' ),
				'telegram_chat_id'     => (string) ( $notify_settings['telegram_chat_id'] ?? '' ),
				'bale_enabled'         => ! empty( $notify_settings['bale_enabled'] ),
				'bale_token'           => (string) ( $notify_settings['bale_token'] ?? '' ),
				'bale_chat_id'         => (string) ( $notify_settings['bale_chat_id'] ?? '' ),
				'msg_restore_template' => (string) ( $notify_settings['msg_restore_template'] ?? '' ),
			),
		);

		$payload['archive'] = $name . '.zip';

		$code = $builder->build( $payload );

		if ( '' === $code ) {
			return '';
		}

		$path = $context->work_dir() . '/installer.php';

		if ( ! Fs::atomic_put( $path, $code ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Copy the package to the configured storage.
	 *
	 * @param JobContext           $context Context.
	 * @param array<string, mixed> $state   Stage state.
	 * @param string               $archive Archive path.
	 * @param string               $name    Package name.
	 * @return array<string, mixed>
	 */
	private function publish( JobContext $context, array $state, string $archive, string $name ): array {
		$storages = new StorageManager( $context->settings(), $context->paths() );
		$storage  = $storages->driver();

		$archive_key   = $name . '.zip';
		$installer_key = $name . '-installer.php';

		$uploaded = $storage->put( $archive, $archive_key );

		if ( ! $uploaded ) {
			throw new JobException(
				sprintf(
					/* translators: %s: storage error. */
					__( 'Upload failed: %s', 'backupino' ),
					$storage->error()
				)
			);
		}

		$storage->put( (string) $state['installer'], $installer_key );

		$state['archive']   = $archive_key;
		$state['installer'] = $installer_key;
		$state['driver']    = $storage->id();
		$state['step']      = 'done';

		$context->logger()->info(
			'Package published.',
			array(
				'job'     => $context->job()->id,
				'archive' => $archive_key,
				'driver'  => $storage->id(),
				'size'    => (int) $state['size'],
			)
		);

		return $state;
	}
}
