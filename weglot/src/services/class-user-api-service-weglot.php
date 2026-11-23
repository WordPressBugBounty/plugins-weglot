<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;


/**
 * @since 2.0
 */
class User_Api_Service_Weglot {
	const WORKSPACE_SLUG_OPTION     = 'weglot-translate-workspace-slug';
	const WORKSPACE_SLUG_KEY_OPTION = 'weglot-translate-workspace-slug-key';
	const WORKSPACE_SLUG_THROTTLE   = 'weglot_workspace_slug_fetch_throttle';

	/**
	 * @var array<string,mixed>|null $user_info Information about the user.
	 */
	protected $user_info = null;

	/**
	 * @var array<string,mixed>|null $workspace_info Information about the workspace user plan.
	 */
	protected $workspace_info = null;

	/**
	 * @var Version_Service_Weglot
	 */
	private $version_service;

	public function __construct() {
		$this->version_service = weglot_get_service( Version_Service_Weglot::class );
	}

	/**
	 * Get the plans and their details.
	 *
	 * @return array<string, array<string, int|array<int>>>
	 */
	public function get_plans() {
		return array(
			'starter_free' => array(
				'ids'            => array( 1, 2, 3 ),
				'limit_language' => 1,
			),
			'business'     => array(
				'ids'            => array( 4, 5 ),
				'limit_language' => 3,
			),
			'pro'     => array(
				'ids'            => array( 6, 7 ),
				'limit_language' => 5,
			),
		);
	}

	/**
	 * @since 2.0
	 * @version 3.0.0
	 * @return array<string,mixed>
	 * @param null|string $api_key
	 */
	public function get_user_info( $api_key = null ) {
		if ( null !== $this->user_info ) {
			return $this->user_info;
		}

		if ( null === $api_key ) {
			$api_key = \weglot_get_api_key();
		}

		if ( $this->version_service->get_version_from_api_key_private( $api_key ) === 2 ) {
			return array();
		}

		try {
			$results = $this->do_request( Helper_API::get_api_url() . '/projects/owner?api_key=' . $api_key );
			$json    = \json_decode( $results, true );

			if ( \json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Unknown error with Weglot Api (0001) : ' . \json_last_error() );
			}

			if ( isset( $json['succeeded'] ) && ( 0 === $json['succeeded'] || 1 === $json['succeeded'] ) ) {
				if ( 1 !== $json['succeeded'] ) {
					$error = isset( $json['error'] ) ? $json['error'] : 'Unknown error with Weglot Api (0003)';
					throw new \Exception( $error );
				}

				if ( ! isset( $json['answer'] ) ) {
					throw new \Exception( 'Unknown error with Weglot Api (0004)' );
				}

				$answer          = $json['answer'];
				$this->user_info = $answer;
				return $this->user_info;
			}

			throw new \Exception( 'Unknown error with Weglot Api (0002) : ' . $json );
		} catch ( \Exception $e ) {
			return array(
				'not_exist' => false,
			);
		}
	}

	/**
	 * Slug of the Weglot 2 workspace the current private key belongs to, '' when unknown.
	 *
	 * The slug is stored with a fingerprint of the key it was fetched with: the key can change
	 * through several settings screens or the WEGLOT_API_KEY_PRIVATE constant, and a slug kept
	 * from another key would link to someone else's workspace. A missing or stale slug is fetched
	 * again, at most once every five minutes.
	 *
	 * @return string
	 */
	public function get_workspace_slug() {
		$slug        = get_option( self::WORKSPACE_SLUG_OPTION );
		$fingerprint = $this->api_key_fingerprint( \weglot_get_api_key() );

		if ( is_string( $slug ) && '' !== trim( $slug ) && get_option( self::WORKSPACE_SLUG_KEY_OPTION ) === $fingerprint ) {
			return $slug;
		}

		if ( false === get_transient( self::WORKSPACE_SLUG_THROTTLE ) ) {
			set_transient( self::WORKSPACE_SLUG_THROTTLE, 1, MINUTE_IN_SECONDS * 5 );
			$this->get_workspace_info();

			$slug = get_option( self::WORKSPACE_SLUG_OPTION );
			if ( is_string( $slug ) && '' !== trim( $slug ) && get_option( self::WORKSPACE_SLUG_KEY_OPTION ) === $fingerprint ) {
				return $slug;
			}
		}

		return '';
	}

	/**
	 * hash(), not wp_hash(): this can run before pluggable.php is loaded (Yoast SEO Premium early include).
	 *
	 * @param mixed $api_key
	 * @return string
	 */
	private function api_key_fingerprint( $api_key ) {
		return hash( 'sha256', is_string( $api_key ) ? $api_key : '' );
	}

	/**
	 * Get workspace information from Weglot API.
	 *
	 * @since 4.0.0
	 * @return array<string,mixed>
	 * @param null|string $api_key
	 */
	public function get_workspace_info( $api_key = null ) {
		if ( null !== $this->workspace_info ) {
			return $this->workspace_info;
		}

		if ( null === $api_key ) {
			$api_key = \weglot_get_api_key();
		}

		try {
			$results = $this->do_request(  Helper_API::get_api_domain($api_key) . '/workspaces/current', [], $api_key );
			$json    = \json_decode( $results, true );
			if ( \json_last_error() !== JSON_ERROR_NONE ) {
				throw new \Exception( 'Unknown error with Weglot Api (workspace-0001) : ' . \json_last_error() );
			}

			$slug = isset($json['slug']) && is_string($json['slug']) ? sanitize_key(trim($json['slug'])) : '';
			if ($slug !== '') {
				update_option( self::WORKSPACE_SLUG_OPTION, $slug );
				update_option( self::WORKSPACE_SLUG_KEY_OPTION, $this->api_key_fingerprint( $api_key ) );
			}

			if ( isset( $json['uuid'] ) ) {
				if ( '' == $json['uuid'] ) {
					$error = isset( $json['detail'] ) ? $json['detail'] : 'Unknown error with Weglot Api (workspace-0003)';
					throw new \Exception( $error );
				}

				if ( ! isset( $json['subscription'] )||  ! isset( $json['usage']) ) {
					throw new \Exception( 'Unknown error with Weglot Api (workspace-0004)' );
				}

				$answer                = $json;
				$this->workspace_info = $answer;
				return $this->workspace_info;
			}

			throw new \Exception( 'Unknown error with Weglot Api (workspace-0002) : ' . $json );
		} catch ( \Exception $e ) {
			return array(
				'not_exist' => false,
			);
		}
	}

	/**
	 *
	 * @param string $url
	 * @param array<string,mixed> $parameters
	 * @param string|null $api_key API key for Authorization header
	 * @return string
	 * @throws \Exception
	 */
	public function do_request( $url, $parameters = [], $api_key = null ) {
		$active_sslverify = apply_filters( 'weglot_active_sslverify', true );

		$headers = array(
			'Content-type' => 'application/json',
		);

		// Add Authorization header if api_key is provided
		if ( null !== $api_key ) {
			$headers['Authorization'] = 'Key ' . $api_key;
		}

		if ( $parameters ) {
			$payload = json_encode( $parameters ); //phpcs:ignore
			if ( \json_last_error() === JSON_ERROR_NONE && is_string( $payload )) {
				$response = wp_remote_post(
					$url,
					array(
						'method'      => 'POST',
						'timeout'     => 3,
						'redirection' => 5,
						'blocking'    => true,
						'headers'     => $headers,
						'body'        => $payload,
						'cookies'     => array(),
						'sslverify'   => $active_sslverify,
					)
				);
			} else {
				throw new \Exception( 'Cannot json encode parameters: ' . esc_html( (string) \json_last_error() ) );
			}
		} else {
			$response = wp_remote_get( //phpcs:ignore
				$url,
				array(
					'method'      => 'GET',
					'timeout'     => 3,
					'redirection' => 5,
					'blocking'    => true,
					'headers'     => $headers,
					'body'        => '',
					'cookies'     => array(),
					'sslverify'   => $active_sslverify,
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			throw new \Exception( 'Error doing the external request to ' . esc_html( $url ) . ': ' . esc_html( $error_message ) );
		} else {
			return $response['body'];
		}
	}
}



