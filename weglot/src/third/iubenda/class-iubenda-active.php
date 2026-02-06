<?php

namespace WeglotWP\Third\Iubenda;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Third_Active_Interface_Weglot;


/**
 * Iubenda_Active
 *
 * @since 4.0
 */
class Iubenda_Active implements Third_Active_Interface_Weglot {

	/**
	 * @since 4.0
	 * @return boolean
	 *
	 * Check if Iubenda plugin is active
	 * https://www.iubenda.com/
	 */
	public function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = false;

		// Check for Iubenda Cookie Law Solution plugin
		if ( is_plugin_active( 'iubenda-cookie-law-solution/iubenda_cookie_solution.php' ) ) {
			$active = true;
		}

		return apply_filters( 'weglot_iubenda_is_active', $active );
	}
}
