<?php

namespace WeglotWP\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\Version_Service_Weglot;

abstract class Helper_API {

	// V1 Endpoints
	const API_BASE                   = 'https://api.weglot.com';
	const API_BASE_STAGING           = 'https://api.weglot.dev';
	const API_BASE_US                = 'https://api.weglot.us';
	const ROOT_CDN_BASE              = 'https://cdn.weglot.com';
	const ROOT_CDN_BASE_STAGING      = 'https://cdn.weglot.dev';
	const API_CDN_BASE               = 'https://cdn-api-weglot.com';
	const API_CDN_BASE_STAGING       = 'https://cdn-api-weglot.dev';
	const CDN_BASE                   = 'https://cdn.weglot.com/projects-settings/';
	const CDN_BASE_STAGING           = 'https://cdn.weglot.dev/projects-settings/';
	const CDN_BASE_US                = 'https://cdn.weglot.us/projects-settings/';
	const CDN_BASE_SWITCHERS_TPL     = 'https://cdn.weglot.com/switchers/';
	const CDN_BASE_SWITCHERS_TPL_STAGING = 'https://cdn.weglot.dev/switchers/';

	// V2 Endpoints (Placeholders)
	const API_BASE_V2                = 'https://api.weglot.com';
	const API_BASE_V2_STAGING        = 'https://api.weglot.dev';
	const API_BASE_V2_US             = 'https://api.weglot.us';
	const ROOT_CDN_BASE_V2           = 'https://cdn-v2.weglot.com';
	const ROOT_CDN_BASE_V2_STAGING   = 'https://cdn-v2.weglot.dev';
	const CDN_BASE_V2                = 'https://cdn-v2.weglot.com/projects-settings/';
	const CDN_BASE_V2_STAGING        = 'https://cdn-v2.weglot.dev/projects-settings/';
	const CDN_BASE_V2_US             = 'https://cdn-v2.weglot.us/projects-settings/';
	const CDN_BASE_SWITCHERS_TPL_V2  = 'https://cdn.weglot.com/switchers/';
	const CDN_BASE_SWITCHERS_TPL_V2_STAGING = 'https://cdn.weglot.dev/switchers/';
	const DASHBOARD_BASE            = 'https://dashboard.weglot.com';
	const DASHBOARD_BASE_STAGING    = 'https://dashboard.weglot.dev';

	const DASHBOARD_BASE_V2         = 'https://auth.weglot.com';
	const DASHBOARD_BASE_V2_STAGING = 'https://auth.weglot.dev';

	/**
	 * Returns the Version_Service_Weglot instance, or null if not yet registered (e.g. during a plugin update
	 * when an older Weglot context was initialised first and the service list is incomplete).
	 *
	 * @return Version_Service_Weglot|null
	 */
	private static function try_get_version_service() {
		try {
			return weglot_get_service( Version_Service_Weglot::class );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get the current environment.
	 *
	 * @return string
	 */
	public static function get_environment() {
		if ( defined( 'WEGLOT_ENV' ) ) {
			return apply_filters( 'weglot_environment', WEGLOT_ENV );
		}

		if ( defined( 'WEGLOT_DEV' ) && WEGLOT_DEV ) {
			return apply_filters( 'weglot_environment', 'staging' );
		}

		return apply_filters( 'weglot_environment', 'production' );
	}


	/**
	 * Get the CDN URL based on the current version and environment.
	 *
	 * @return string
	 */
	public static function get_cdn_url() {
		$env             = self::get_environment();
		$version_service = self::try_get_version_service();
		$version         = $version_service ? $version_service->get_onboarding_version() : 1;

		if ( $version === 2 ) {
			if ( 'staging' === $env ) {
				return self::API_BASE_STAGING;
			}
			if ( 'env_us' === $env ) {
				return self::API_BASE_US;
			}
			return self::API_BASE;
		}

		// V1 fallback
		if ( 'env_us' === $env ) {
			return self::CDN_BASE_US;
		}
		if ( 'staging' === $env ) {
			return self::CDN_BASE_STAGING;
		}
		return self::CDN_BASE;
	}


	/**
	 * Get the API URL based on the current version and environment.
	 *
	 * @return string
	 */
	public static function get_api_url() {
		$env             = self::get_environment();
		$version_service = self::try_get_version_service();
		$version         = $version_service ? $version_service->get_onboarding_version() : 1;

		if ( $version === 2 ) {
			if ( 'staging' === $env ) {
				return self::API_BASE_V2_STAGING;
			}
			if ( 'env_us' === $env ) {
				return self::API_BASE_V2_US;
			}
			return self::API_BASE_V2;
		}

		// V1 fallback
		if ( 'env_us' === $env ) {
			return self::API_BASE_US;
		}
		if ( 'staging' === $env ) {
			return self::API_BASE_STAGING;
		}
		return self::API_BASE;
	}

	/**
	 * Get the root CDN base URL based on the current environment.
	 *
	 * @return string
	 */
	public static function get_root_cdn_base() {
		$env = self::get_environment();

		return 'staging' === $env ? self::ROOT_CDN_BASE_STAGING : self::ROOT_CDN_BASE;
	}

	/**
	 * Get the switchers template URL based on the current version and environment.
	 *
	 * @return string
	 */
	public static function get_tpl_switchers_url() {
		$env             = self::get_environment();
		$version_service = self::try_get_version_service();
		$version         = $version_service ? $version_service->get_onboarding_version() : 1;

		if ( $version === 2 ) {
			if ( 'staging' === $env ) {
				return self::CDN_BASE_SWITCHERS_TPL_V2_STAGING;
			}
			return self::CDN_BASE_SWITCHERS_TPL_V2;
		}

		// V1 fallback
		if ( 'staging' === $env ) {
			return self::CDN_BASE_SWITCHERS_TPL_STAGING;
		}
		return self::CDN_BASE_SWITCHERS_TPL;
	}

	/**
	 * Retrieves the URL for the dashboard, depending on the environment and version.
	 *
	 * @param bool $random Optional. Whether to retrieve a random onboarding version. Default false.
	 *
	 * @return string The URL for the dashboard.
	 */
	public static function get_dashboard_url($random = false) {
		$env = self::get_environment();
		$version_service = self::try_get_version_service();
		if($random === false){
			$version = $version_service ? $version_service->get_onboarding_version() : 2;
		}else{
			$version = $version_service ? $version_service->get_random_onboarding_version() : 2;
		}


		if ( $version === 2 ) {
			if ( 'staging' === $env ) {
				return self::DASHBOARD_BASE_V2_STAGING;
			}
			return self::DASHBOARD_BASE_V2;
		}

		// V1 fallback
		if ( 'staging' === $env ) {
			return self::DASHBOARD_BASE_STAGING;
		}
		return self::DASHBOARD_BASE;
	}

	/**
	 * Generates the registration URL based on the environment, onboarding version, and randomization settings.
	 *
	 * @param bool $random Whether to use a random onboarding version. Defaults to false.
	 *
	 * @return string The generated registration URL.
	 */
	public static function get_register_url($random = false) {
		$env = self::get_environment();
		$version_service = self::try_get_version_service();
		if($random === false){
			$version = $version_service ? $version_service->get_onboarding_version() : 2;
		}else{
			$version = $version_service ? $version_service->get_random_onboarding_version() : 2;
		}

		if ( $version === 2 ) {
			$register_path = '/register/wordpress';
			$site_url = get_site_url();
			if ( 'staging' === $env ) {
				return add_query_arg('url', urlencode($site_url), self::DASHBOARD_BASE_V2_STAGING . $register_path);
			}

			return add_query_arg('url', urlencode($site_url), self::DASHBOARD_BASE_V2 . $register_path);
		}

		$register_path = '/register-wordpress';
		// V1 fallback
		if ( 'staging' === $env ) {
			return self::DASHBOARD_BASE_STAGING.$register_path;
		}
		return self::DASHBOARD_BASE.$register_path;
	}

	/**
	 * Wrapper around wp_remote_get() which can be moved into VIP-safe context.
	 *
	 * @param string             $url  The URL to retrieve.
	 * @param array<string,mixed> $args Optional WP HTTP args.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function vip_safe_wp_remote_get( string $url, array $args = [] ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		return wp_remote_get( $url, $args );
	}

	/**
	 * @param string $api_key
	 * @param string $field Field to retrieve: 'api_domain' or 'api_base_url'
	 * @return string
	 */
	public static function get_api_domain( $api_key, $field = 'api_base_url' ) {
		$option_key   = sprintf( '%s-%s', WEGLOT_SLUG, $field );
		$cached_value = get_option( $option_key, '' );

		if ( is_string( $cached_value ) && '' !== trim( $cached_value ) ) {
			if ( ! defined( 'WEGLOT_DEV' ) ) {
				return $cached_value;
			}
			$expected_suffix = WEGLOT_DEV ? '.dev' : '.com';
			if ( substr( $cached_value, -strlen( $expected_suffix ) ) === $expected_suffix ) {
				return $cached_value;
			}
			// env mismatch: discard cached value and re-fetch from API
		}

		if ( ! is_string( $api_key ) || '' === trim( $api_key ) ) {
			$env = self::get_environment();
			if ( 'staging' === $env ) {
				return self::DASHBOARD_BASE_V2_STAGING;
			}
			return self::DASHBOARD_BASE_V2;
		}

		/** @var Option_Service_Weglot $option_services */
		$option_services = weglot_get_service( Option_Service_Weglot::class );
		if ( ! $option_services ) {
			$env = self::get_environment();
			if ( 'staging' === $env ) {
				return self::DASHBOARD_BASE_V2_STAGING;
			}
			return self::DASHBOARD_BASE_V2;
		}

		$response = $option_services->get_options_from_api_with_api_key( $api_key, true );

		if ( ! is_array( $response ) || empty( $response['success'] ) ) {
			$env = self::get_environment();
			if ( 'staging' === $env ) {
				return self::DASHBOARD_BASE_V2_STAGING;
			}
			return self::DASHBOARD_BASE_V2;
		}

		$value = isset( $response['result'][ $field ] ) && is_string( $response['result'][ $field ] )
			? trim( $response['result'][ $field ] )
			: '';

		if ( '' === $value ) {
			$env = self::get_environment();
			if ( 'staging' === $env ) {
				return self::DASHBOARD_BASE_V2_STAGING;
			}
			return self::DASHBOARD_BASE_V2;
		}

		update_option( $option_key, $value );

		return $value;
	}
}
