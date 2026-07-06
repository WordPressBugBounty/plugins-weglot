<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Helpers\Helper_API;

/**
 * @since 3.1.0
 */
class Version_Service_Weglot implements Hooks_Interface_Weglot {

	const API_VERSION_OPTION_NAME = 'weglot_api_version';

	/**
	 * @since 3.1.0
	 * @see Hooks_Interface_Weglot
	 * @return void
	 */
	public function hooks() {
		}

	/**
	 * @since 3.1.0
	 * @return int
	 */
	private function get_v2_percentage_split() {
		$percentage = 0;

		$url      = Helper_API::ROOT_CDN_BASE . '/wp-routing.json';
		$response = Helper_API::vip_safe_wp_remote_get( $url, [
			'timeout'   => 3,
			'sslverify' => true,
		] );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( is_array( $data ) && isset( $data['v2percentage'] ) && is_numeric( $data['v2percentage'] ) ) {
				$percentage = (int) $data['v2percentage'];
			}
		}
		return apply_filters( 'weglot_v2_percentage_split', $percentage );
	}

	/**
	 * @since 3.1.0
	 * @return int
	 */
	public function get_random_onboarding_version() {
		$roll          = wp_rand( 1, 100 );

		$percentage_v2 = $this->get_v2_percentage_split();
		if ( $roll <= $percentage_v2 ) {
			return 2; // Version V2
		}
		return 1; // Version V1
	}

	/**
	 * @return int
	 */
	public function get_onboarding_version() {
		$saved_version = get_option( self::API_VERSION_OPTION_NAME );
		if ( $saved_version ) {
			return (int) $saved_version;
		}

		$api_key_private = get_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key_private' ) );
		if ( $api_key_private ) {
			$version_to_set = $this->get_version_from_api_key_private($api_key_private);
		} else {
			$version_to_set = 2;
		}

		update_option( self::API_VERSION_OPTION_NAME, $version_to_set );
		return $version_to_set;
	}

	/**
	 * @since 3.1.0
	 * @return void
	 */
	public function route_settings_page_by_version() {
		$onboarding_version = $this->get_onboarding_version();

		if ( $onboarding_version === 2 ) {
			$v2_template_path = WEGLOT_TEMPLATES . '/admin/v2/settings.php';
			if ( file_exists( $v2_template_path ) ) {
				include_once $v2_template_path;
				exit;
			}
		}
	}

	/**
	 *
	 * @param string $api_key
	 *
	 * @return int
	 */
	public function get_version_from_api_key_private($api_key = ''){
		if ( ! is_string( $api_key ) || '' === $api_key ) {
			return 1;
		}
		if ( substr( $api_key, 0, 3 ) !== 'wg_' ) {
			return 2;
		}
		return 1;
	}
}
