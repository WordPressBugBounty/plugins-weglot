<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;

/**
 * Manages the lifecycle of the Weglot webhook used to purge the CDN cache
 * when project settings change.
 *
 * @since 3.0.0
 */
class Webhook_Service_Weglot {

	/**
	 * @var Option_Service_Weglot
	 */
	private $option_service;

	/**
	 * @var Version_Service_Weglot
	 */
	private $version_service;

	/**
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->option_service  = weglot_get_service( Option_Service_Weglot::class );
		$this->version_service = weglot_get_service( Version_Service_Weglot::class );
	}

	/**
	 * Creates the webhook if it does not already exist remotely.
	 * Only runs for v2 API keys.
	 *
	 * @param string $api_key
	 * @return void
	 */
	public function register_webhook( string $api_key ): void {
		if ( '' === trim( $api_key ) ) {
			return;
		}

		if ( $this->version_service->get_version_from_api_key_private( $api_key ) !== 2 ) {
			return;
		}

		$stored_id = get_option( sprintf( '%s-%s', WEGLOT_SLUG, 'webhook_id' ), '' );

		if ( is_string( $stored_id ) && '' !== $stored_id ) {
			if ( $this->webhook_exists_remotely( $api_key, $stored_id ) ) {
				return;
			}
		}

		$this->create_webhook( $api_key );
	}

	/**
	 * Deletes the existing webhook and creates a new one for the given API key.
	 * Must be called before the new API key is persisted in the database,
	 * as delete_webhook() reads the currently stored key to authenticate the DELETE request.
	 *
	 * @param string $api_key
	 * @return void
	 */
	public function replace_webhook( string $api_key ): void {
		if ( '' === trim( $api_key ) ) {
			return;
		}

		if ( $this->version_service->get_version_from_api_key_private( $api_key ) !== 2 ) {
			return;
		}

		$this->delete_webhook();
		$this->create_webhook( $api_key );
	}

	/**
	 * Deletes the webhook from the Weglot API and removes the stored ID.
	 *
	 * @return void
	 */
	public function delete_webhook(): void {
		$webhook_id = get_option( sprintf( '%s-%s', WEGLOT_SLUG, 'webhook_id' ), '' );
		if ( ! is_string( $webhook_id ) || '' === $webhook_id ) {
			return;
		}

		$api_key_private = $this->option_service->get_api_key_private();
		if ( ! is_string( $api_key_private ) || '' === $api_key_private ) {
			return;
		}

		$url      = $this->get_webhook_endpoint( $api_key_private ) . '/' . $webhook_id;
		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'DELETE',
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- webhook deletion on deactivation tolerates a short wait
				'headers' => $this->get_auth_headers( $api_key_private ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		if ( 204 === wp_remote_retrieve_response_code( $response ) ) {
			delete_option( sprintf( '%s-%s', WEGLOT_SLUG, 'webhook_id' ) );
		}
	}

	/**
	 * @param string $api_key
	 * @param string $webhook_id
	 * @return bool
	 */
	private function webhook_exists_remotely( string $api_key, string $webhook_id ): bool {
		$url      = $this->get_webhook_endpoint( $api_key ) . '/' . $webhook_id;
		$response = Helper_API::vip_safe_wp_remote_get(
			$url,
			array(
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- checking webhook existence during onboarding
				'headers' => $this->get_auth_headers( $api_key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Returns the stored route slug, generating and persisting it from the API key if absent.
	 *
	 * @param string $api_key
	 * @return string
	 */
	private function get_or_create_route_slug( string $api_key ): string {
		$option_key = sprintf( '%s-%s', WEGLOT_SLUG, 'webhook_route_slug' );
		$slug       = get_option( $option_key, '' );

		if ( is_string( $slug ) && '' !== $slug ) {
			return $slug;
		}

		$slug = md5( $api_key );
		update_option( $option_key, $slug );

		return $slug;
	}

	/**
	 * @param string $api_key
	 * @return void
	 */
	private function create_webhook( string $api_key ): void {
		$target_url = rest_url( 'weglot/v2/cache/purge/' . $this->get_or_create_route_slug( $api_key ) );

		if ( strpos( $target_url, 'http://' ) === 0 ) {
			$target_url = 'https://' . substr( $target_url, 7 );
		}
		$payload = wp_json_encode(
			array(
				'targetUrl'   => $target_url,
				'eventTypes'  => array( 'project.settings.updated' ),
				'description' => sprintf( 'WordPress - %s - webhook purge cache', get_bloginfo( 'name' ) ),
			)
		);

		if ( false === $payload ) {
			return;
		}

		$response = wp_remote_post( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_post_wp_remote_post
			$this->get_webhook_endpoint( $api_key ),
			array(
				'timeout' => 15, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- webhook creation during onboarding
				'headers' => $this->get_auth_headers( $api_key ),
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		if ( 201 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['subscriptionId'] ) || ! is_string( $body['subscriptionId'] ) ) {
			return;
		}

		update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'webhook_id' ), $body['subscriptionId'] );
	}

	/**
	 * Returns the full webhook API endpoint for the given key.
	 *
	 * Uses the project-specific API domain stored during onboarding (e.g. api.eu.weglot.com)
	 * so it respects regional routing, falling back to the default API base when not yet stored.
	 *
	 * @param string $api_key
	 * @return string
	 */
	private function get_webhook_endpoint( string $api_key ): string {
		return Helper_API::get_api_domain( $api_key, 'api_base_url' ) . '/projects/webhooks';
	}

	/**
	 * @param string $api_key
	 * @return array<string,string>
	 */
	private function get_auth_headers( string $api_key ): array {
		return array(
			'Authorization' => 'Key ' . $api_key,
			'Content-Type'  => 'application/json',
		);
	}
}
