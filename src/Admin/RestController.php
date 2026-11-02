<?php
/**
 * REST API.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Admin;

defined( 'ABSPATH' ) || exit;

use Backupino\Core\Capabilities;
use Backupino\Core\Compatibility;
use Backupino\Jobs\Job;
use Backupino\Jobs\JobStatus;
use Backupino\Plugin;
use Backupino\Support\Bytes;
use Backupino\Support\Fs;

/**
 * Exposes jobs, settings and system status to the admin UI.
 */
final class RestController {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE_V1 = 'backupino/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_jobs' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_job' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'type'     => array(
							'type'    => 'string',
							'default' => 'manual',
						),
						'files'    => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'database' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_job' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_job' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs/(?P<id>\d+)/step',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'step_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/jobs/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_job' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/system',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'system' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/notify/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_notification' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/system/storage-test',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'test_storage' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * Checks user capabilities and verifies the REST nonce on state-changing requests.
	 *
	 * @param \WP_REST_Request|null $request Request instance if dispatched by WP REST server.
	 * @return bool
	 */
	public function can_manage( $request = null ): bool {
		if ( ! Capabilities::user_can_manage() ) {
			return false;
		}

		if ( $request instanceof \WP_REST_Request ) {
			$method = strtoupper( (string) $request->get_method() );
			if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
				$nonce = $request->get_header( 'x_wp_nonce' );
				if ( empty( $nonce ) || ( function_exists( 'wp_verify_nonce' ) && ! wp_verify_nonce( (string) $nonce, 'wp_rest' ) ) ) {
					if ( ! defined( 'BACKUPINO_TESTS' ) ) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * List jobs.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_jobs(): \WP_REST_Response {
		$plugin = Plugin::instance();
		$jobs   = array();

		foreach ( $plugin->jobs()->recent( 25 ) as $job ) {
			$jobs[] = $this->present( $job );
		}

		return new \WP_REST_Response( array( 'jobs' => $jobs ) );
	}

	/**
	 * Create a job.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_job( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin = Plugin::instance();

		$job = Job::create(
			array(
				'type'     => sanitize_key( (string) $request->get_param( 'type' ) ),
				'files'    => (bool) $request->get_param( 'files' ),
				'database' => (bool) $request->get_param( 'database' ),
				'note'     => sanitize_text_field( (string) $request->get_param( 'note' ) ),
			),
			$plugin->clock()
		);

		// Per-job overrides of the global archive settings.
		$job->payload['options']['files']    = (bool) $request->get_param( 'files' );
		$job->payload['options']['database'] = (bool) $request->get_param( 'database' );

		$plugin->jobs()->create( $job );
		$plugin->logger()->info( 'Job created.', array( 'job' => $job->id ) );

		return new \WP_REST_Response( $this->present( $job ), 201 );
	}

	/**
	 * Get a single job.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_job( \WP_REST_Request $request ): \WP_REST_Response {
		$job = Plugin::instance()->jobs()->find( (int) $request['id'] );

		if ( null === $job ) {
			return new \WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		return new \WP_REST_Response( $this->present( $job ) );
	}

	/**
	 * Advance a job by one slice.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function step_job( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin = Plugin::instance();
		$job    = $plugin->jobs()->find( (int) $request['id'] );

		if ( null === $job ) {
			return new \WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		if ( ! $job->is_runnable() ) {
			return new \WP_REST_Response( $this->present( $job ) );
		}

		// Give the browser enough time to finish one slice without hitting the
		// gateway timeout, while keeping the loop responsive.
		if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Extension of time limit for incremental backup slice.
			@set_time_limit( 60 );
		}

		$job = $plugin->runner()->step( $job );

		if ( JobStatus::COMPLETED === $job->status ) {
			\Backupino\Cron\Retention::enforce( $plugin );
		}

		return new \WP_REST_Response( $this->present( $job ) );
	}

	/**
	 * Cancel a job.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cancel_job( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin = Plugin::instance();
		$job    = $plugin->jobs()->find( (int) $request['id'] );

		if ( null === $job ) {
			return new \WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		return new \WP_REST_Response( $this->present( $plugin->runner()->cancel( $job ) ) );
	}

	/**
	 * Delete a job and its package files.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_job( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin = Plugin::instance();
		$job    = $plugin->jobs()->find( (int) $request['id'] );

		if ( null === $job ) {
			return new \WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}

		$package = isset( $job->payload['package'] ) && is_array( $job->payload['package'] )
			? $job->payload['package']
			: array();

		foreach ( array( 'archive', 'installer' ) as $key ) {
			if ( ! empty( $package[ $key ] ) ) {
				Fs::delete( $plugin->paths()->packages() . '/' . basename( (string) $package[ $key ] ) );
			}
		}

		Fs::delete_dir( $plugin->paths()->job_dir( $job->token ) );
		$plugin->jobs()->delete( $job->id );

		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Read the settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		return new \WP_REST_Response( Plugin::instance()->settings()->all() );
	}

	/**
	 * Persist the settings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin  = Plugin::instance();
		$current = $plugin->settings()->all();
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$merged = \Backupino\Core\Settings::merge( $current, $payload );

		if ( isset( $payload['notify'] ) && is_array( $payload['notify'] ) ) {
			$tg_on   = ! empty( $payload['notify']['telegram_enabled'] );
			$bale_on = ! empty( $payload['notify']['bale_enabled'] );
			if ( ! $tg_on && ! $bale_on ) {
				$merged['notify']['service'] = 'none';
			}
		}

		$plugin->settings()->update( $merged );

		return new \WP_REST_Response( $plugin->settings()->all() );
	}

	/**
	 * System status.
	 *
	 * @return \WP_REST_Response
	 */
	public function system(): \WP_REST_Response {
		$plugin = Plugin::instance();
		$units  = Compatibility::snapshot( $plugin->paths() );

		$units['log']      = $plugin->logger()->tail( 100 );
		$units['packages'] = $this->scan_packages();
		$units['driver']   = $plugin->storages()->driver()->id();

		return new \WP_REST_Response( $units );
	}

	/**
	 * Send the test message to the configured messenger.
	 *
	 * @return \WP_REST_Response
	 */
	public function test_notification( \WP_REST_Request $request ): \WP_REST_Response {
		$plugin   = Plugin::instance();
		$notifier = $plugin->notifier();
		$service  = sanitize_key( (string) $request->get_param( 'service' ) );
		$sent     = $notifier->send_test( $plugin->site_name(), '' !== $service ? $service : null );

		return new \WP_REST_Response(
			array(
				'sent'  => $sent,
				'error' => $sent ? '' : $notifier->error(),
			),
			$sent ? 200 : 500
		);
	}

	/**
	 * Test the configured storage driver with a tiny object.
	 *
	 * @return \WP_REST_Response
	 */
	public function test_storage(): \WP_REST_Response {
		$plugin  = Plugin::instance();
		$paths   = $plugin->paths();
		$storage = $plugin->storages()->driver();

		$probe = $paths->base() . '/backupino-probe.txt';

		Fs::atomic_put( $probe, 'backupino probe ' . gmdate( 'c' ) );

		$key = 'probe-' . gmdate( 'YmdHis' ) . '.txt';
		$ok  = $storage->put( $probe, $key );

		Fs::delete( $probe );

		if ( ! $ok ) {
			return new \WP_REST_Response(
				array(
					'ok'    => false,
					'error' => $storage->error(),
				),
				400
			);
		}

		$storage->delete( $key );

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'driver'  => $storage->id(),
				'message' => __( 'Storage is writable.', 'backupino' ),
			)
		);
	}

	/**
	 * Serialize a job for the UI.
	 *
	 * @param Job $job Job.
	 * @return array<string, mixed>
	 */
	private function present( Job $job ): array {
		$data = $job->to_api();

		$data['stageLabel'] = '';
		$data['package']    = null;
		$data['progress']   = (int) $job->progress;

		$stage = Plugin::instance()->pipeline()->get( $job->stage );

		if ( null !== $stage ) {
			$data['stageLabel'] = $stage->label();
		}

		if ( isset( $job->payload['package'] ) && is_array( $job->payload['package'] ) ) {
			$package = $job->payload['package'];

			$data['package'] = array(
				'name'         => (string) ( $package['name'] ?? '' ),
				'size'         => Bytes::format( (int) ( $package['size'] ?? 0 ) ),
				'hash'         => (string) ( $package['hash'] ?? '' ),
				'archiveUrl'   => isset( $package['archive'] )
					? add_query_arg(
						array(
							'page'     => 'backupino',
							'download' => rawurlencode( basename( (string) $package['archive'] ) ),
							'_wpnonce' => wp_create_nonce( 'backupino_download' ),
						),
						admin_url( 'admin.php' )
					)
					: '',
				'installerUrl' => isset( $package['installer'] )
					? add_query_arg(
						array(
							'page'     => 'backupino',
							'download' => rawurlencode( basename( (string) $package['installer'] ) ),
							'_wpnonce' => wp_create_nonce( 'backupino_download' ),
						),
						admin_url( 'admin.php' )
					)
					: '',
			);
		}

		$all_logs = Plugin::instance()->logger()->tail( 250 );
		$job_logs = array();
		if ( is_string( $all_logs ) && '' !== trim( $all_logs ) ) {
			$lines       = explode( "\n", trim( $all_logs ) );
			$pattern     = '"job":' . $job->id;
			$alt_pattern = 'job #' . $job->id;
			foreach ( $lines as $line ) {
				if ( false !== strpos( $line, $pattern ) || false !== stripos( $line, $alt_pattern ) ) {
					$job_logs[] = $line;
				}
			}
			if ( empty( $job_logs ) ) {
				$job_logs = array_values( array_slice( array_filter( $lines ), -40 ) );
			}
		}
		$data['logs'] = $job_logs;

		return $data;
	}

	/**
	 * List finished packages on disk.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function scan_packages(): array {
		$paths = Plugin::instance()->paths()->packages();
		$files = glob( $paths . '/*' );

		if ( ! is_array( $files ) ) {
			return array();
		}

		$packages = array();

		foreach ( $files as $file ) {
			$packages[] = array(
				'name' => basename( $file ),
				'size' => Bytes::format( Fs::size( $file ) ),
				'time' => gmdate( 'Y-m-d H:i', (int) filemtime( $file ) ),
			);
		}

		return $packages;
	}
}
