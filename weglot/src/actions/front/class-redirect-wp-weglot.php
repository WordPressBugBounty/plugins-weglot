<?php

namespace WeglotWP\Actions\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;

/**
 *
 * @since 6.0
 */
class Redirect_Wp_Weglot implements Hooks_Interface_Weglot {

	/**
	 * @see Hooks_Interface_Weglot
	 *
	 * @since 6.0
	 * @return void
	 */
	public function hooks() {
		add_filter( 'wp_redirect', array( '\WeglotWP\Helpers\Helper_Filter_Url_Weglot', 'filter_wp_redirect' ), 0 );
	}
}
