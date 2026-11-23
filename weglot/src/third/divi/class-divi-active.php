<?php

namespace WeglotWP\Third\Divi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Third_Active_Interface_Weglot;


/**
 * Divi_Active
 *
 * Divi is a theme, so it is detected through the active template rather than through
 * is_plugin_active(). A child theme reports its own name in get_stylesheet(), which is why the
 * parent template is what gets checked.
 *
 * @since 6.5
 */
class Divi_Active implements Third_Active_Interface_Weglot {

	/**
	 * @since 6.5
	 * @return boolean
	 */
	public function is_active() {
		$active = 'divi' === strtolower( (string) get_template() );

		return (bool) apply_filters( 'weglot_divi_is_active', $active );
	}

}
