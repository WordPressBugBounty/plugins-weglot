<?php


namespace WeglotWP\Actions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_Is_Admin;
use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\Version_Service_Weglot;
use WeglotWP\Services\Webhook_Service_Weglot;

/**
 * Ajax_Projects_Settings
 *
 * @since 3.0.0
 */
class Ajax_Projects_Settings implements Hooks_Interface_Weglot {

	/**
	 * @var Option_Service_Weglot
	 */
	private $option_services;

	/**
	 * @var Version_Service_Weglot
	 */
	private $version_service_weglot;
	/**
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->option_services = weglot_get_service( Option_Service_Weglot::class );
		$this->version_service_weglot = weglot_get_service( Version_Service_Weglot::class );
	}

	/**
	 * @return void
	 * @since 3.0.0
	 * @see Hooks_Interface_Weglot
	 *
	 */
	public function hooks() {
		if ( ! Helper_Is_Admin::is_wp_admin() ) {
			return;
		}

		add_action( 'wp_ajax_get_project_settings', array( $this, 'get_project_settings' ) );
	}

	/**
	 * Get project settings from CDN using API key
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function get_project_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		check_ajax_referer( 'weglot_get_project_settings', 'security' );

		if ( ! isset( $_POST['api_key'] ) ) { //phpcs:ignore
			wp_send_json_error( array( 'message' => 'API key is required' ) );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) ); //phpcs:ignore

		if ( $this->version_service_weglot->get_version_from_api_key_private( $api_key ) === 2 ) {
			$response = $this->option_services->get_options_from_api_with_api_key( $api_key, true, true );
		}else{
			$response = $this->option_services->get_options_from_api_with_api_key( $api_key );
		}

		$success   = isset( $response['success'] ) && $response['success'] === true;
		$has_error = isset( $response['result'] ) && is_array( $response['result'] ) && isset( $response['result']['error'] ) && null !== $response['result']['error'];

		if ( ! $success || $has_error ) {
			wp_send_json_error( array(
				'message'  => 'Failed to retrieve project settings',
				'response' => $response,
			) );
		}

		if ( $this->version_service_weglot->get_version_from_api_key_private( $api_key ) === 2 ) {
			weglot_get_service( Webhook_Service_Weglot::class )->replace_webhook( $api_key );
		}

		update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key_private' ), $api_key );

		if ( isset( $response['result']['api_domain'] ) && is_string( $response['result']['api_domain'] ) && '' !== trim( $response['result']['api_domain'] ) ) {
			update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_domain' ), $response['result']['api_domain'] );
		}

		if ( isset( $response['result']['api_base_url'] ) && is_string( $response['result']['api_base_url'] ) && '' !== trim( $response['result']['api_base_url'] ) ) {
			update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_base_url' ), $response['result']['api_base_url'] );
		}

		if ( isset( $response['result']['languages'] ) && is_array( $response['result']['languages'] ) && ! empty( $response['result']['languages'] ) ) {
			$first_target_lang = $response['result']['languages'][0];
			if ( isset( $first_target_lang['language_to'] ) ) {
				$iframe_url = home_url( '/' . $first_target_lang['language_to'] . '/' );
				$response['iframe_url'] = $iframe_url;
			}
		}

		wp_send_json_success( $response );
	}
}
