<?php

namespace WeglotWP\Third\Doofinder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Services\Language_Service_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\Request_Url_Service_Weglot;
use WeglotWP\Services\Translate_Service_Weglot;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;


/**
 * The Doofinder layer searches over a WebSocket opened from the browser, so the query never
 * reaches PHP and cannot be filtered server side. It is intercepted on the search input
 * instead, and reverse-translated through the REST route registered here — the private API
 * key must never be exposed to the browser.
 *
 * @since 6.5
 */
class Doofinder_Service implements Hooks_Interface_Weglot {

	const REST_NAMESPACE = 'weglot/v1';
	const REST_ROUTE     = '/doofinder/reverse-translate';

	/**
	 * The layer mounts its results under an id carrying a random suffix, hence the prefix match.
	 */
	const LAYER_SELECTOR = '[id^="df-hook-layer-"]';

	/**
	 * @var Doofinder_Active
	 */
	private $doofinder_active_service;

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
	 * @since 6.5
	 */
	public function __construct() {
		$this->option_services          = weglot_get_service( Option_Service_Weglot::class );
		$this->request_url_services     = weglot_get_service( Request_Url_Service_Weglot::class );
		$this->language_services        = weglot_get_service( Language_Service_Weglot::class );
		$this->translate_services       = weglot_get_service( Translate_Service_Weglot::class );
		$this->doofinder_active_service = weglot_get_service( Doofinder_Active::class );
	}

	/**
	 * @since 6.5
	 * @return void
	 */
	public function hooks() {
		// is_active() is gated on a filter, so it cannot be evaluated here: hooks() runs on
		// plugins_loaded, before a theme functions.php had any chance to register that filter.
		// Every callback below checks it instead, at a point where the filter is known.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_interceptor' ) );
		add_action( 'wp_footer', array( $this, 'inject_layer_language' ), 9 );
		add_filter( 'weglot_dynamics_selectors', array( $this, 'add_layer_selector' ) );
		add_filter( 'weglot_whitelist_selectors', array( $this, 'add_layer_selector' ) );
	}

	/**
	 * @since 6.5
	 * @return void
	 */
	public function register_routes() {
		if ( ! $this->doofinder_active_service->is_active() ) {
			return;
		}

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reverse_translate_query' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'q'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'lang' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * @since 6.5
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return true|WP_Error
	 */
	public function check_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'weglot_rest_forbidden', 'Forbidden.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Reverse-translate a search term back into the original language.
	 *
	 * The language comes from the payload and not from the request URL: the REST route is
	 * served from /wp-json/, which carries no language prefix, so Request_Url_Service would
	 * always resolve it to the original language and the translation would be a no-op.
	 *
	 * @since 6.5
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response
	 */
	public function reverse_translate_query( WP_REST_Request $request ) {
		$query = $request->get_param( 'q' );

		if ( ! is_string( $query ) || '' === trim( $query ) ) {
			return new WP_REST_Response( array( 'q' => '' ), 200 );
		}

		$original_language = $this->language_services->get_original_language();

		if ( null === $original_language ) {
			return new WP_REST_Response( array( 'q' => $query ), 200 );
		}

		$current_language = $this->resolve_requested_language( $request->get_param( 'lang' ) );

		if ( null === $current_language || $current_language === $original_language->getInternalCode() ) {
			return new WP_REST_Response( array( 'q' => $query ), 200 );
		}

		$api_key = $this->option_services->get_api_key_private();

		if ( ! is_string( $api_key ) || '' === $api_key ) {
			return new WP_REST_Response( array( 'q' => $query ), 200 );
		}

		$reversed = $this->translate_services->reverse_translate_term( $api_key, $current_language, $original_language->getInternalCode(), home_url(), $query );

		if ( ! is_string( $reversed ) || '' === $reversed ) {
			return new WP_REST_Response( array( 'q' => $query ), 200 );
		}

		return new WP_REST_Response( array( 'q' => $reversed ), 200 );
	}

	/**
	 * Accept a language code only when it is one of the configured destination languages,
	 * so the route cannot be driven into translating towards an arbitrary language.
	 *
	 * @since 6.5
	 *
	 * @param mixed $lang
	 *
	 * @return string|null
	 */
	private function resolve_requested_language( $lang ) {
		if ( ! is_string( $lang ) || '' === $lang ) {
			return null;
		}

		foreach ( $this->language_services->get_destination_languages( true ) as $language ) {
			if ( $language->getInternalCode() === $lang ) {
				return $lang;
			}
		}

		return null;
	}

	/**
	 * @since 6.5
	 * @return void
	 */
	public function enqueue_interceptor() {
		$current_language = $this->get_translated_current_language();

		if ( null === $current_language ) {
			return;
		}

		wp_register_script( 'weglot-doofinder-js', WEGLOT_URL_DIST . '/doofinder-js.js', array(), WEGLOT_VERSION, true );
		wp_localize_script(
			'weglot-doofinder-js',
			'weglotDoofinder',
			array(
				'endpoint' => rest_url( self::REST_NAMESPACE . self::REST_ROUTE ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'language' => $current_language,
				/**
				 * Trace the query interception in the browser console.
				 *
				 * @since 6.5
				 *
				 * @param bool $enabled
				 */
				'debug'    => (bool) apply_filters( 'weglot_doofinder_debug', false ),
			)
		);
		wp_enqueue_script( 'weglot-doofinder-js' );
	}

	/**
	 * Configure the layer in the visitor language.
	 *
	 * The Doofinder plugin already does this in Settings::maybe_prepend_extra_config(), but only
	 * when a language plugin it knows about is installed. With Weglot it falls back to
	 * No_Language_Plugin, whose get_current_language() returns an empty string, so the block is
	 * skipped and the layer renders in the default language.
	 *
	 * Runs before the plugin own wp_footer callback (priority 10) so the command queue exists
	 * by the time the layer loader executes.
	 *
	 * @since 6.5
	 * @see wp_footer
	 * @return void
	 */
	public function inject_layer_language() {
		if ( ! class_exists( '\Doofinder\WP\Settings' ) || ! \Doofinder\WP\Settings::is_js_layer_enabled() ) {
			return;
		}

		$current_language = $this->get_translated_current_language();

		if ( null === $current_language ) {
			return;
		}
		?>
		<script>
			(function (w, k) { w[k] = w[k] || function () { (w[k].q = w[k].q || []).push(arguments); }; })(window, 'doofinderApp');
			doofinderApp('config', 'language', <?php echo wp_json_encode( $current_language ); ?>);
		</script>
		<?php
	}

	/**
	 * @since 6.5
	 *
	 * @param mixed $selectors
	 *
	 * @return mixed
	 */
	public function add_layer_selector( $selectors ) {
		if ( ! is_array( $selectors ) || ! $this->doofinder_active_service->is_active() ) {
			return $selectors;
		}

		$selectors[] = array( 'value' => self::LAYER_SELECTOR );

		return $selectors;
	}

	/**
	 * The current language, or null when the integration has nothing to do: inactive, or the
	 * visitor is already browsing in the original language.
	 *
	 * @since 6.5
	 * @return string|null
	 */
	private function get_translated_current_language() {
		if ( ! $this->doofinder_active_service->is_active() ) {
			return null;
		}

		$original_language = $this->language_services->get_original_language();

		if ( null === $original_language ) {
			return null;
		}

		$current_language = $this->request_url_services->get_current_language()->getInternalCode();

		if ( $current_language === $original_language->getInternalCode() ) {
			return null;
		}

		return $current_language;
	}
}
