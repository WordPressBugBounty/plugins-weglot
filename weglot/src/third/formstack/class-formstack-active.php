<?php

namespace WeglotWP\Third\Formstack;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Third_Active_Interface_Weglot;


/**
 * Formstack_Active
 *
 * @since 6.3
 */
class Formstack_Active implements Third_Active_Interface_Weglot {

	/**
	 * Detection is per request, not site wide: enabling the JS engine on pages that hold
	 * no form would load the CDN script on the whole site.
	 *
	 * Any other embedding method (widget, page builder meta, template call) has to opt in
	 * through the filter.
	 *
	 * @since 6.3
	 * @return boolean
	 */
	public function is_active() {
		return apply_filters( 'weglot_formstack_is_active', $this->has_embed() );
	}

	/**
	 * @since 6.3
	 * @return boolean
	 */
	private function has_embed() {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post || '' === $post->post_content ) {
			return false;
		}

		// has_shortcode() only matches registered tags, so it already implies the Formstack
		// plugin being active, while the host marker covers the raw JS embed used without it.
		if ( has_shortcode( $post->post_content, 'formstack' ) ) {
			return true;
		}

		return false !== strpos( $post->post_content, 'formstack.com' );
	}
}
