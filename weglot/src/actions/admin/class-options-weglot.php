<?php

namespace WeglotWP\Actions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use WeglotWP\Helpers\Helper_Tabs_Admin_Weglot;
use WeglotWP\Helpers\Helper_Pages_Weglot;
use WeglotWP\Helpers\Helper_Flag_Type;
use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\User_Api_Service_Weglot;
use WeglotWP\Services\Version_Service_Weglot;
use WeglotWP\Services\Webhook_Service_Weglot;

/**
 * Sanitize options after submit form
 *
 * @since 2.0
 */
class Options_Weglot implements Hooks_Interface_Weglot {
	/**
	 * @var Option_Service_Weglot
	 */
	private $option_services;
	/**
	 * @var User_Api_Service_Weglot
	 */
	private $user_api_services;

	/**
	 * @var Version_Service_Weglot
	 */
	private $version_services;

	/**
	 * @var Webhook_Service_Weglot
	 */
	private $webhook_service;

	/**
	 * @throws Exception
	 * @since 2.0
	 */
	public function __construct() {
		$this->option_services   = weglot_get_service( Option_Service_Weglot::class );
		$this->user_api_services = weglot_get_service( User_Api_Service_Weglot::class );
		$this->version_services  = weglot_get_service( Version_Service_Weglot::class );
		$this->webhook_service   = weglot_get_service( Webhook_Service_Weglot::class );
	}

	/**
	 * @return void
	 * @throws Exception
	 * @version 3.0.0
	 * @see Hooks_Interface_Weglot
	 *
	 * @since 2.0
	 */
	public function hooks() {
		add_action( 'admin_post_weglot_save_settings', array( $this, 'weglot_save_settings' ) );
		add_action( 'wp_ajax_weglot_save_settings_v2', array( $this, 'weglot_save_settings_v2' ) );
		add_action( 'admin_post_weglot_delete_options', array( $this, 'weglot_delete_option_from_db' ) );

		$api_version = $this->version_services->get_version_from_api_key_private($this->option_services->get_api_key_private());

		if($api_version === 1){
			$api_key = $this->option_services->get_api_key( true );
		}else{
			$api_key = $this->option_services->get_api_key_private();
		}

		if ( empty( $api_key ) && ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'weglot-settings' ) === false && strpos( $_GET['page'], 'weglot-dashboard' ) === false) ) { // phpcs:ignore
			// We don't show the notice if we are on Weglot configuration.
			add_action( 'admin_notices', array( '\WeglotWP\Notices\No_Configuration_Weglot', 'admin_notice' ) );
		}
	}

	/**
	 * Activate plugin
	 *
	 * @return void
	 */
	public function activate() {
		update_option( 'weglot_version', WEGLOT_VERSION );
	}


	/**
	 * @since 3.0.0
	 * @return void
	 */
	public function weglot_save_settings_v2() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		check_ajax_referer( 'weglot_save_settings_v2', 'security' );


		$api_key_private = isset( $_POST['api_key_private'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key_private'] ) ) : '';

		if ( '' === $api_key_private || strlen( $api_key_private ) < 10 ) {
			wp_send_json_error( array( 'message' => 'Invalid API key.' ) );
		}

		$validation = $this->option_services->get_options_from_api_with_api_key( $api_key_private, true );
		if ( ! is_array( $validation ) || empty( $validation['success'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid API key.' ) );
		}

		update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key_private' ), $api_key_private );


		$options = $this->option_services->get_options();

		$translate_search = isset( $_POST['translate_search'] ) && '1' === $_POST['translate_search'];
		$translate_amp    = isset( $_POST['translate_amp'] )    && '1' === $_POST['translate_amp'];
		$translate_email  = isset( $_POST['translate_email'] )  && '1' === $_POST['translate_email'];

		$options['custom_settings']['translate_search'] = $translate_search;
		$options['custom_settings']['translate_amp']    = $translate_amp;
		$options['custom_settings']['translate_email']  = $translate_email;

		$backup = false;
		$has_first_settings = $this->option_services->get_has_first_settings();
		if ( $has_first_settings ) {
			$backup = get_option( 'weglot_settings_backup' );
			if ( $backup && is_array( $backup ) && isset( $backup['custom_settings'] ) && is_array( $backup['custom_settings'] ) ) {
				$options['custom_settings'] = array_merge(
					isset( $options['custom_settings'] ) ? $options['custom_settings'] : array(),
					$backup['custom_settings']
				);
			}
		}

		$api_response = $this->option_services->save_options_to_weglot_v2( $options );

		if ( ! $api_response['success'] ) {
			wp_send_json_error( array( 'message' => 'Error while saving to Weglot API' ) );
		}
		$options_bdd = $this->option_services->get_options_bdd_v3();
		$options_bdd['custom_settings']['translate_search'] = $translate_search;
		$options_bdd['custom_settings']['translate_amp']    = $translate_amp;
		$options_bdd['custom_settings']['translate_email']  = $translate_email;

		if ( $backup && is_array( $backup ) ) {
			foreach ( array( 'custom_urls', 'menu_switcher', 'flag_css', 'active_wc_reload' ) as $key ) {
				if ( isset( $backup[ $key ] ) ) {
					$options_bdd[ $key ] = $backup[ $key ];
				}
			}
			if ( isset( $backup['custom_settings'] ) && is_array( $backup['custom_settings'] ) ) {
				$options_bdd['custom_settings'] = array_merge(
					isset( $options_bdd['custom_settings'] ) ? $options_bdd['custom_settings'] : array(),
					$backup['custom_settings']
				);
			}
			delete_option( 'weglot_settings_backup' );
		}

		$this->option_services->set_options( $options_bdd );

		// Delete the transient so the next request re-fetches raw API data.
		// Storing post-Morphism options here would corrupt the transient and
		// cause double-mapping issues on the next get_options() call.
		delete_transient( 'weglot_cache_cdn' );

		wp_send_json_success( array( 'message' => 'ok' ) );
	}

	/**
	 * @since 3.0.0
	 * @return void
	 */
	public function weglot_save_settings() {


		$redirect_url = admin_url( 'admin.php?page=' . Helper_Pages_Weglot::SETTINGS );
		$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; //phpcs:ignore
		if ( ! isset( $_GET['tab'] ) || ! wp_verify_nonce( $nonce, 'weglot_save_settings' ) ) { //phpcs:ignore
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$tab = $_GET[ 'tab' ]; //phpcs:ignore
		$options = $_POST[ WEGLOT_SLUG ]; //phpcs:ignore
		$keep_api_key_private = "";

		$api_key_private = isset( $options['api_key_private'] ) ? (string) $options['api_key_private'] : '';
		$api_version = $this->version_services->get_version_from_api_key_private($api_key_private);
		update_option( $this->version_services::API_VERSION_OPTION_NAME, $api_version );
		if($api_version === 2){
			$option_from_api = $this->option_services->get_options_from_api_with_api_key($api_key_private, true);
			$keep_api_key_private = $api_key_private;
			$options = array();
			if (
				is_array( $option_from_api )
				&& ! empty( $option_from_api['success'] )
				&& isset( $option_from_api['result'] )
				&& is_array( $option_from_api['result'] )
			) {
				$options = $option_from_api['result'];
			}
			$options['api_key_private'] = $keep_api_key_private;
			// I keep it cause don't have it from api settings
		}
		if($api_version === 1){
			$has_api_key = '' !== trim( $api_key_private );
			$has_language_from = isset( $options['language_from'] ) && '' !== trim( $options['language_from'] );
			$has_languages = isset( $options['languages'] ) && is_array( $options['languages'] ) && count( $options['languages'] ) > 0;

			$has_only_api_key = $has_api_key && ! $has_language_from && ! $has_languages;
			if($has_only_api_key){
				update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key_private' ), $api_key_private );
				wp_redirect( $redirect_url ); //phpcs:ignore
				exit;
			}
		}

		//todo check la clé api, si v1, check si on a que ça (pas languge from et language_to je redirige vers tpl 1)
		// todo si v2 on va skip le save des settings et juste save la clé privée
		// SAVE USER VERSION OF PLUGIN INTO SETTINGS.
		$options['custom_settings']['wp_user_version'] = WEGLOT_VERSION;
		$options_bdd = $this->option_services->get_options_bdd_v3();

		if ( ! is_array( $options_bdd ) ) {
			$options_bdd = array();
		}

		switch ( $tab ) {
			case Helper_Tabs_Admin_Weglot::SETTINGS:
				$has_first_settings = $this->option_services->get_has_first_settings();
				if( $api_version === 1 ){
					$options = $this->sanitize_options_settings( $options, $has_first_settings );
				}else{
					$options = $this->sanitize_options_settings( $options, $has_first_settings, 2 );
				}

				$backup = false;
				if ( $has_first_settings ) {
					$backup = get_option( 'weglot_settings_backup' );
					if ( $backup && is_array( $backup ) && isset( $backup['custom_settings'] ) && is_array( $backup['custom_settings'] ) ) {
						$options['custom_settings'] = array_merge(
							isset( $options['custom_settings'] ) ? $options['custom_settings'] : array(),
							$backup['custom_settings']
						);
					}
				}

				if($api_version === 1){
					$response = $this->option_services->save_options_to_weglot( $options );
				}else{
					$response = $this->option_services->save_options_to_weglot( $options );
					$options['api_key_private'] = $keep_api_key_private;
					$response['result'] = $options;
				}

				if ( $response['success'] && is_array( $response['result'] ) ) {
					delete_transient( 'weglot_cache_cdn' );

					$api_key_private = $this->option_services->get_api_key_private();
					$option_v2 = $this->option_services->get_options_from_v2();

					if ( ! $api_key_private && $option_v2 ) {
						$options_bdd['custom_urls']             = $option_v2['custom_urls'];
						$options_bdd['menu_switcher']           = $option_v2['menu_switcher'];
						$options_bdd['has_first_settings']      = $option_v2['has_first_settings'];
						$options_bdd['show_box_first_settings'] = $option_v2['show_box_first_settings'];
					}

					if ( $has_first_settings ) {
						$options_bdd['has_first_settings']      = false;
						$options_bdd['show_box_first_settings'] = true;

						if ( $backup && is_array( $backup ) ) {
							foreach ( array( 'custom_urls', 'menu_switcher', 'flag_css', 'active_wc_reload' ) as $key ) {
								if ( isset( $backup[ $key ] ) ) {
									$options_bdd[ $key ] = $backup[ $key ];
								}
							}
							if ( isset( $backup['custom_settings'] ) && is_array( $backup['custom_settings'] ) ) {
								$options_bdd['custom_settings'] = array_merge(
									isset( $options_bdd['custom_settings'] ) ? $options_bdd['custom_settings'] : array(),
									$backup['custom_settings']
								);
							}
							delete_option( 'weglot_settings_backup' );
						}
					}

					if ( array_key_exists( 'flag_css', $options ) ) {
						$options_bdd['flag_css'] = $options['flag_css'];
					}

					$this->option_services->set_options( $options_bdd );

					if($api_version === 2){
						update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key_private' ), $keep_api_key_private );
						set_transient( 'weglot_cache_cdn', $options, apply_filters( 'weglot_get_options_from_cdn_cache_duration', 300 ) );
					}else{
						update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key_private' ), $api_key_private );
						update_option( sprintf( '%s-%s', WEGLOT_SLUG, 'api_key' ), $response['result']['api_key'] );
					}

					// get menu options.
					$options_menu = $this->option_services->get_option( 'menu_switcher' );
					if ( is_array( $options_menu ) ) {
						if ( ! empty( $options_menu ) ) {
							foreach ( $options_menu as $key => $menu ) {
								// Ensure $menu is an array before modifying
								if ( is_array( $menu ) ) {
									if ( $options['custom_settings']['button_style']['is_dropdown'] ) {
										$options_menu[ $key ]['dropdown'] = 1;
									} else {
										$options_menu[ $key ]['dropdown'] = 0;
									}
								}
							}
						}
					}

					if($api_version === 1){
						delete_transient( 'weglot_cache_cdn' );
					}
					$this->option_services->set_option_by_key( 'menu_switcher', $options_menu );
				}
				break;
			case Helper_Tabs_Admin_Weglot::SUPPORT:
				if ( array_key_exists( 'active_wc_reload', $options ) && 'on' === $options['active_wc_reload'] ) {
					$options_bdd['active_wc_reload'] = true;
				} else {
					$options_bdd['active_wc_reload'] = false;
				}

				$this->option_services->set_options( $options_bdd );
				break;
		}

		wp_redirect( $redirect_url ); //phpcs:ignore
		exit;
	}

	/**
	 * @since 2.0
	 * @version 2.0.6
	 * @param array<string|int,mixed> $options
	 * @param mixed $has_first_settings
	 * @param int $version_api
	 * @return array<string,mixed>
	 */

	public function sanitize_options_settings( $options, $has_first_settings = false, $version_api = 1 ) {
		if($version_api === 1){
			$user_info = [];
			$switchers = [];
			$definitions = [];
		}else{
			$user_info = $this->user_api_services->get_user_info( $options['api_key_private'] );
			$switchers = $this->option_services->get_switchers_editor_button();
			$definitions = $this->option_services->get_option('definitions');
		}

		// Limit language.
		$limit = 30;
		if ( isset( $user_info['languages_limit'] ) ) {
			$limit = $user_info['languages_limit'];
		}
		$options['languages'] = array_splice( $options['languages'], 0, $limit );

		$default_options = $this->option_services->get_options_default();

		$options['custom_settings']['button_style']['is_dropdown'] = isset( $options['custom_settings']['button_style']['is_dropdown'] );
		$options['custom_settings']['button_style']['with_flags']  = isset( $options['custom_settings']['button_style']['with_flags'] );
		$options['custom_settings']['button_style']['full_name']   = isset( $options['custom_settings']['button_style']['full_name'] );
		$options['custom_settings']['button_style']['with_name']   = isset( $options['custom_settings']['button_style']['with_name'] );

		if ( $has_first_settings ) {
			$options['custom_settings']['button_style']['is_dropdown'] = $default_options['custom_settings']['button_style']['is_dropdown'];
			$options['custom_settings']['button_style']['with_flags']  = $default_options['custom_settings']['button_style']['with_flags'];
			$options['custom_settings']['button_style']['full_name']   = $default_options['custom_settings']['button_style']['full_name'];
			$options['custom_settings']['button_style']['with_name']   = $default_options['custom_settings']['button_style']['with_name'];
		}

		// Prioritize custom_css from options : custom_css, fallback to button_style : custom_css if needed
		// Determine which key holds the custom CSS and remove escape slashes
		if (!empty($options['custom_css'])) {
			$css = stripcslashes($options['custom_css']);
		} elseif (!empty($options['custom_settings']['button_style']['custom_css'])) {
			$css = stripcslashes($options['custom_settings']['button_style']['custom_css']);
		} else {
			$css = '';
		}

		// Ensure both values are set to the same unescaped CSS code
		$options['custom_css'] = $css;
		$options['custom_settings']['button_style']['custom_css'] = $css;

		$options['custom_settings']['button_style']['flag_type'] = isset( $options['custom_settings']['button_style']['flag_type'] ) ? $options['custom_settings']['button_style']['flag_type'] : Helper_Flag_Type::RECTANGLE_MAT;

		$options['custom_settings']['translate_email']  = isset( $options['custom_settings']['translate_email'] );
		$options['custom_settings']['translate_search'] = isset( $options['custom_settings']['translate_search'] );
		$options['custom_settings']['translate_amp']    = isset( $options['custom_settings']['translate_amp'] );
		$options['custom_settings']['wp_user_version']  = $options['custom_settings']['wp_user_version'] ?? '';

		if(WEGLOT_WOOCOMMERCE){
			$options['custom_settings']['woocommerce_integration'] = true;
		}

		$options['auto_switch'] = isset( $options['auto_switch'] );

		// Ensure options:custom_settings:switchers is set correctly
		$options['custom_settings']['switchers'] = !empty($switchers) ? $switchers : [];
		$options['custom_settings']['definitions'] = !empty($definitions) ? $definitions : [];

		// Ensure $options['switchers'] is also updated if it's empty but custom_settings['switchers'] is not
		if (empty($options['switchers']) && !empty($options['custom_settings']['switchers'])) {
			$options['switchers'] = $options['custom_settings']['switchers'];
		}

		return $options;
	}

	/**
	 * Delete all Weglot options from database
	 * This will clean the settings and restart the onboarding process
	 * Note: This only cleans WordPress database settings, it does not delete the project from Weglot dashboard
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function weglot_delete_option_from_db() {
		global $wpdb;

		$redirect_url = admin_url( 'admin.php?page=' . Helper_Pages_Weglot::SETTINGS );
		$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_verify_nonce() handles validation
		if ( ! wp_verify_nonce( $nonce, 'weglot_delete_options' ) ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$options_bdd    = $this->option_services->get_options_bdd_v3();
		$cached_options = get_transient( 'weglot_cache_cdn' );
		$options_source = ( $cached_options && is_array( $cached_options ) ) ? $cached_options : $options_bdd;
		$backup         = array();
		foreach ( array( 'custom_urls', 'menu_switcher', 'flag_css', 'active_wc_reload' ) as $key ) {
			if ( isset( $options_bdd[ $key ] ) ) {
				$backup[ $key ] = $options_bdd[ $key ];
			}
		}
		if ( isset( $options_source['custom_settings'] ) && is_array( $options_source['custom_settings'] ) ) {
			$backup['custom_settings'] = $options_source['custom_settings'];
			unset( $backup['custom_settings']['wp_user_version'] );
		}
		if ( ! empty( $backup ) ) {
			update_option( 'weglot_settings_backup', $backup );
		}

		$this->webhook_service->delete_webhook();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reset operation, caching would return stale data
		$weglot_options = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be a placeholder
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%weglot%'
			),
			ARRAY_A
		);

		if ( ! empty( $weglot_options ) && is_array( $weglot_options ) ) {
			foreach ( $weglot_options as $option ) {
				if ( isset( $option['option_name'] ) && $option['option_name'] !== 'weglot_settings_backup' ) {
					delete_option( $option['option_name'] );
				}
			}
		}

		delete_transient( 'weglot_cache_cdn' );

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
