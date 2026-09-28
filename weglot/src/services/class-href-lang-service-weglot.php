<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * @since 2.3.0
 */
class Href_Lang_Service_Weglot {

	/**
	 * @var Request_Url_Service_Weglot
	 */
	private $request_url_services;

	/**
	 * @var Noindex_Service_Weglot
	 */
	private $noindex_services;


	/**
	 * @since 2.3.0
	 */
	public function __construct() {
		$this->request_url_services = weglot_get_service( Request_Url_Service_Weglot::class );
		$this->noindex_services     = weglot_get_service( Noindex_Service_Weglot::class );
	}

	/**
	 * @since 2.3.0
	 * @return string
	 */
	public function generate_href_lang_tags() {
		$render = "\n";
		if ( ! $this->request_url_services->is_eligible_url() ) {
			return apply_filters( 'weglot_href_lang', $render );
		}

		$urls = $this->request_url_services->get_weglot_url()->getAllUrls();

		// A hreflang pointing at a noindexed page is a contradictory signal: search engines
		// drop it and it can invalidate the whole cluster.
		foreach ( $urls as $url ) {
			if ( ! $url['excluded'] && ! $this->noindex_services->is_noindex( $url['language'] ) ) {
				$render .= '<link rel="alternate" href="' . strtok( esc_url( $url['url'] ), '?' ) . '" hreflang="' . $url['language']->getExternalCode() . '"/>' . "\n";
			}
		}

		return apply_filters( 'weglot_href_lang', $render );
	}
}
