<?php

namespace WeglotWP\Third\MinimalComingSoon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_Is_Admin;
use WeglotWP\Models\Hooks_Interface_Weglot;


/**
 * Minimal_Coming_Soon_Tracking
 *
 * @since 3.1.9
 */
class Minimal_Coming_Soon_Tracking implements Hooks_Interface_Weglot {
	/**
	 * @var Minimal_Coming_Soon_Active
	 */
	private $minimal_coming_soon_active_services;

	/**
	 * @since 3.1.9
	 * @return void
	 */
	public function __construct() {
		$this->minimal_coming_soon_active_services = weglot_get_service( Minimal_Coming_Soon_Active::class );
	}

	/**
	 * @since 3.1.9
	 * @see Hooks_Interface_Weglot
	 * @return void
	 */
	public function hooks() {
		if ( ! Helper_Is_Admin::is_wp_admin() ) {
			return;
		}

		if ( ! $this->minimal_coming_soon_active_services->is_active() ) {
			return;
		}

		add_filter( 'weglot_tabs_admin_options_available', array( $this, 'weglot_minimal_coming_soon_tracking' ) );
	}


	/**
	 * @param array<string,mixed> $options_available
	 * @return array<string,mixed>
	 * @since 3.1.9
	 */
	public function weglot_minimal_coming_soon_tracking( $options_available ) {

		if ( isset( $options_available['api_key_private']['description'] ) ) {

			$register_link         = 'https://dashboard.weglot.com/register-wordpress';
			$register_link_tracked = 'https://dashboard.weglot.com/register-wordpress?fp_ref=minimal-coming-soon';

			$options_available['api_key_private']['description'] = \str_replace( $register_link, $register_link_tracked, $options_available['api_key_private']['description'] );
		}

		return $options_available;
	}
}
