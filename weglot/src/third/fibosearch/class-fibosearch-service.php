<?php

namespace WeglotWP\Third\Fibosearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\Request_Url_Service_Weglot;
use WeglotWP\Services\Language_Service_Weglot;
use WeglotWP\Services\Translate_Service_Weglot;


/**
 * @since 3.0
 */
class Fibosearch_Service implements Hooks_Interface_Weglot{

	/**
	 * @var Fibosearch_Active
	 */
	private $fibosearch_active_service;
	/**
	 * @var Option_Service_Weglot
	 */
	private $option_services;

	/**
	 * @var Request_Url_Service_Weglot
	 */
	private $request_url_services;

	/**
	 * @var Language_Service_Weglot
	 */
	private $language_services;

	/**
	 * @var Translate_Service_Weglot
	 */
	private $translate_services;

	/**
	 * @since 3.0.0
	 */
	public function __construct() {
		$this->option_services      = weglot_get_service( Option_Service_Weglot::class );
		$this->request_url_services = weglot_get_service( Request_Url_Service_Weglot::class );
		$this->language_services    = weglot_get_service( Language_Service_Weglot::class );
		$this->fibosearch_active_service   = weglot_get_service( Fibosearch_Active::class );
		$this->translate_services   = weglot_get_service( Translate_Service_Weglot::class );
	}

	/**
	 * @since 3.0.0
	 * @return void
	 */
	public function hooks() {
		if ( ! $this->fibosearch_active_service->is_active() ) {
			return;
		}

		// Force FiboSearch to use the WordPress AJAX endpoint (wc_ajax) instead of
		// the direct search.php with SHORTINIT, so Weglot is fully loaded during searches.
		if ( ! defined( 'DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT' ) ) {
			define( 'DGWT_WCAS_ALTERNATIVE_SEARCH_ENDPOINT', true );
		}

		add_filter( 'dgwt/wcas/phrase', array( $this, 'reverse_translate_phrase' ), 10, 1 );
		add_filter( 'dgwt/wcas/phrase/initial', array( $this, 'reverse_translate_phrase' ), 10, 1 );
		add_filter( 'weglot_add_json_keys', array( $this, 'fibosearch_weglot_add_json_keys' ), 10, 1 );
	}


	/**
	 * Filters and adds a custom key to the Weglot JSON keys array.
	 *
	 * @param array<string> $keys An array of existing JSON keys.
	 *
	 * @return array<string> The modified array of JSON keys with an additional entry.
	 */
	public function fibosearch_weglot_add_json_keys( $keys ) {
		$keys[] = 'value';
		return $keys;
	}

	/**
	 * Reverse translate FiboSearch phrase using the dgwt/wcas/phrase filter
	 *
	 * @param string $keyword
	 * @return string
	 * @since 3.0.0
	 */
	public function reverse_translate_phrase( $keyword ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- FiboSearch plugin does not provide nonce for this AJAX action.
		$wc_ajax = isset( $_REQUEST['wc-ajax'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ) : null;
		if ( ! $wc_ajax || ('dgwt_wcas_ajax_search' !== $wc_ajax && 'dgwt_wcas_ajax_search_pro' !== $wc_ajax) ){
			return $keyword;
		}

		return $this->do_reverse_translate( $keyword );
	}

	/**
	 * Core reverse-translation logic, shared by both hooks.
	 *
	 * @param string $keyword
	 * @return string
	 */
	private function do_reverse_translate( $keyword ) {
		$current_language  = $this->request_url_services->get_current_language();
		$original_language = $this->language_services->get_original_language();

		if ( $current_language->getInternalCode() === $original_language->getInternalCode() ) {
			return $keyword;
		}

		$api_key = $this->option_services->get_api_key_private();
		if ( ! $api_key ) {
			return $keyword;
		}

		$request_url   = home_url();
		$reversed_term = $this->translate_services->reverseTranslate(
			$api_key,
			$current_language->getInternalCode(),
			$original_language->getInternalCode(),
			$request_url,
			$keyword,
			1
		);

		if ( null !== $reversed_term && '' !== $reversed_term && is_string( $reversed_term ) ) {
			return $reversed_term;
		}

		return $keyword;
	}

}
