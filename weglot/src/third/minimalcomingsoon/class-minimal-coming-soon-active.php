<?php

namespace WeglotWP\Third\MinimalComingSoon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Third_Active_Interface_Weglot;


/**
 * Minimal_Coming_Soon_Active
 *
 * @since 3.1.9
 */
class Minimal_Coming_Soon_Active implements Third_Active_Interface_Weglot {

	/**
	 * @since 3.1.9
	 * @return boolean
	 *
	 * Check if Minimal Coming Soon & Maintenance Mode plugin is active
	 * https://wordpress.org/plugins/minimal-coming-soon-maintenance-mode/
	 */
	public function is_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = true;

		if ( ! is_plugin_active( 'minimal-coming-soon-maintenance-mode/minimal-coming-soon-maintenance-mode.php' ) ) {
			$active = false;
		}

		return apply_filters( 'weglot_minimal_coming_soon_is_active', $active );
	}
}
