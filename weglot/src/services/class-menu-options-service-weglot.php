<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_Menu_Options_Weglot;


/**
 * @since 2.4.0
 */
class Menu_Options_Service_Weglot {
	/**
	 * @since 2.4.0
	 */
	public function __construct() {
	}

	/**
	 * @since 2.4.0
	 * @return array<int|string,mixed>
	 */
	public function get_options_default() {
		$keys = Helper_Menu_Options_Weglot::get_keys();

		return apply_filters(
			'weglot_menu_switcher_options_default',
			array_map(
				function() {
					return false;
				},
				array_flip( $keys )
			)
		);
	}

	/**
	 * @since 2.4.0
	 * @return array<int|string,mixed>
	 */
	public function get_list_options_menu_switcher() {
		return Helper_Menu_Options_Weglot::get_menu_switcher_list_options();
	}

	/**
	 * @return string
	 */
	public function weglot_get_navigation_admin_url() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return admin_url();
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return admin_url( 'site-editor.php?p=%2Fnavigation' );
		}

		if ( current_theme_supports( 'menus' ) ) {
			return admin_url( 'nav-menus.php' );
		}

		return admin_url( 'customize.php' );

	}

	/**
	 * @return string|null.
	 */
	function weglot_get_widgets_admin_url() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return null;
		}

		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

		if ( $is_block_theme ) {
			return admin_url( 'site-editor.php' );
		}

		global $wp_registered_sidebars;
		$has_sidebars = is_array( $wp_registered_sidebars ) && ! empty( $wp_registered_sidebars );

		if ( $has_sidebars ) {
			return admin_url( 'widgets.php' );
		}

		return admin_url( 'customize.php' );
	}
}
