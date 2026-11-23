<?php

namespace WeglotWP\Third\Doofinder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Third_Active_Interface_Weglot;


/**
 * @since 6.5
 */
class Doofinder_Active implements Third_Active_Interface_Weglot {

	/**
	 * @since 6.5
	 * @return boolean
	 */
	public function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'doofinder-for-woocommerce/doofinder-for-woocommerce.php' ) ) {
			return false;
		}

		/**
		 * Opt into reverse-translating the query sent to the Doofinder search layer.
		 *
		 * @since 6.5
		 *
		 * @param bool $enabled
		 */
		return (bool) apply_filters( 'weglot_translate_doofinder_search', false );
	}
}
