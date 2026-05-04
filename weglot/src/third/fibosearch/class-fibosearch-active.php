<?php

namespace WeglotWP\Third\Fibosearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Third_Active_Interface_Weglot;
use WeglotWP\Services\Option_Service_Weglot;


/**
 * @since 3.0
 */
class Fibosearch_Active implements Third_Active_Interface_Weglot {

	/**
	 * @since 3.0.0
	 * @return boolean
	 */
	public function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'ajax-search-for-woocommerce/ajax-search-for-woocommerce.php' ) && ! is_plugin_active( 'ajax-search-for-woocommerce-premium/ajax-search-for-woocommerce.php' ) ) {
			return false;
		}

		/** @var Option_Service_Weglot $option_service */
		$option_service = weglot_get_service( Option_Service_Weglot::class );
		if ( ! $option_service->get_option_custom_settings( 'translate_search' ) ) {
			return false;
		}

		return true;
	}
}
