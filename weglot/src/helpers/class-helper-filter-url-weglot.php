<?php

namespace WeglotWP\Helpers;

use WeglotWP\Services\Language_Service_Weglot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Function helper for URL replace filter
 *
 * @since 2.0
 */
abstract class Helper_Filter_Url_Weglot {

	/**
	 * @since 2.4.0
	 * @param string $url
	 * @return string|bool
	 */
	public static function filter_url_lambda( $url ) {
		$request_url_service = weglot_get_request_url_service();
		$replaced_url        = $request_url_service->create_url_object( $url )->getForLanguage( $request_url_service->get_current_language() );
		if ( $replaced_url ) {
			return $replaced_url;
		} else {
			return $url;
		}
	}

	/**
	 * Prefix the language on wp_redirect targets, unless disabled via filter.
	 * @since 6.0
	 * @param string|false $url
	 * @return string|bool
	 */
	public static function filter_wp_redirect( $url ) {
		if ( is_admin() ) {
			return $url;
		}
		if ( ! apply_filters( 'weglot_active_wp_redirect', true ) ) {
			return $url;
		}
		// A falsy target means "no redirect" to wp_redirect(); rewriting it would send the visitor home.
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		// A relative reference (?query, #fragment, path) resolves in the browser against the current,
		// already prefixed URL; the URL parser would read it as "/" and redirect to the homepage.
		$target_host      = wp_parse_url( $url, PHP_URL_HOST );
		$is_root_relative = '/' === $url[0] && '//' !== substr( $url, 0, 2 );
		if ( ! $is_root_relative && ! is_string( $target_host ) ) {
			return $url;
		}
		// Only prefix internal redirects; an absolute URL pointing to another host must be left untouched.
		// Host is case-insensitive (RFC 3986 §3.2.2) and wp_parse_url() does not normalise it.
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $target_host ) && 0 !== strcasecmp( $target_host, (string) $home_host ) ) {
			return $url;
		}
		if ( self::is_admin_target( $url ) ) {
			return $url;
		}
		return self::filter_url_lambda( $url );
	}

	/**
	 * A redirect to the admin must never take a language prefix: /fr/wp-admin/ is served as a front
	 * request, so core's wp_redirect_admin_locations() or a forced-login plugin sends the visitor to
	 * admin_url() again, prefixed again, in an infinite loop.
	 *
	 * @param string $url
	 * @return bool
	 */
	private static function is_admin_target( $url ) {
		$target_path = trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		foreach ( array( admin_url(), network_admin_url() ) as $admin_url ) {
			$admin_path = trailingslashit( (string) wp_parse_url( $admin_url, PHP_URL_PATH ) );
			if ( 0 === strpos( $target_path, $admin_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Filter URL log redirection
	 * @since 2.0
	 * @version 2.0.2
	 * @param string $url_filter
	 * @return string|bool
	 */
	public static function filter_url_log_redirect( $url_filter ) {

		$request_url_service = weglot_get_request_url_service();
		/** @var Language_Service_Weglot $language_service */
		$language_service = weglot_get_service( Language_Service_Weglot::class );

		$url = $request_url_service->create_url_object( $url_filter );

		if ( $request_url_service->get_current_language() === $language_service->get_original_language()
			&& isset( $_SERVER['HTTP_REFERER'] ) //phpcs:ignore
		) {
			$url_referer = $request_url_service->create_url_object( $_SERVER['HTTP_REFERER'] ); //phpcs:ignore
			$current_language = $url_referer->getCurrentLanguage();
			return $url->getForLanguage( $current_language );
		}
		return $url->getForLanguage( $request_url_service->get_current_language() );
	}
}
