<?php

namespace WeglotWP\Third\Formstack;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;


/**
 * Formstack_Weglot
 *
 * Formstack renders its forms client side, after WordPress has served the page, so the
 * server side parser never sees them: only the JS engine can translate them. Their
 * fields also carry accessible names that do not follow the translated labels, which
 * the bridge script realigns.
 *
 * @since 6.3
 */
class Formstack_Weglot implements Hooks_Interface_Weglot {

	/**
	 * Formstack embed wrappers.
	 *
	 * @var string[]
	 */
	const DEFAULT_SELECTORS = array( '.fsBody', '.fsForm' );

	/**
	 * @var Formstack_Active
	 */
	private $formstack_active_services;

	/**
	 * @return void
	 * @throws \Exception
	 * @since 6.3
	 */
	public function __construct() {
		$this->formstack_active_services = weglot_get_service( Formstack_Active::class );
	}

	/**
	 * @since 6.3
	 * @see Hooks_Interface_Weglot
	 * @return void
	 */
	public function hooks() {
		// is_active() inspects the queried post, so it cannot be resolved on plugins_loaded
		// like the other integrations: every callback below runs after the main query and
		// gates itself instead.
		add_filter( 'weglot_translate_dynamics', array( $this, 'enable_dynamics' ) );
		add_filter( 'weglot_load_dynamics_script', array( $this, 'load_dynamics_script' ) );
		add_filter( 'weglot_dynamics_selectors', array( $this, 'add_selectors' ) );
		add_filter( 'weglot_whitelist_selectors', array( $this, 'add_selectors' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_accessibility_bridge' ) );
	}

	/**
	 * @param bool $enabled
	 * @return bool
	 * @since 6.3
	 */
	public function enable_dynamics( $enabled ) {
		return $this->formstack_active_services->is_active() ? true : $enabled;
	}

	/**
	 * @param bool $load
	 * @return bool
	 * @since 6.3
	 */
	public function load_dynamics_script( $load ) {
		return $this->formstack_active_services->is_active() ? true : $load;
	}

	/**
	 * @param mixed $selectors
	 * @return mixed
	 * @since 6.3
	 */
	public function add_selectors( $selectors ) {
		if ( ! is_array( $selectors ) || ! $this->formstack_active_services->is_active() ) {
			return $selectors;
		}

		foreach ( $this->get_selectors() as $selector ) {
			if ( ! $this->has_selector( $selectors, $selector ) ) {
				$selectors[] = array( 'value' => $selector );
			}
		}

		return $selectors;
	}

	/**
	 * @return void
	 * @since 6.3
	 */
	public function enqueue_accessibility_bridge() {
		if ( ! $this->formstack_active_services->is_active() ) {
			return;
		}

		// No dependency on wp-weglot-js on purpose: the bridge only reacts to DOM changes,
		// and site owners who deregister that handle would otherwise silently lose it.
		wp_register_script( 'weglot-formstack-js', WEGLOT_URL_DIST . '/formstack-js.js', array(), WEGLOT_VERSION, true );
		wp_localize_script(
			'weglot-formstack-js',
			'weglotFormstack',
			array(
				'selectors' => $this->get_selectors(),
			)
		);
		wp_enqueue_script( 'weglot-formstack-js' );
	}

	/**
	 * @return string[]
	 * @since 6.3
	 */
	private function get_selectors() {
		$selectors = apply_filters( 'weglot_formstack_selectors', self::DEFAULT_SELECTORS );

		if ( ! is_array( $selectors ) ) {
			return self::DEFAULT_SELECTORS;
		}

		$selectors = array_values( array_filter( $selectors, 'is_string' ) );

		return array() === $selectors ? self::DEFAULT_SELECTORS : $selectors;
	}

	/**
	 * @param array<int, mixed> $selectors
	 * @param string            $needle
	 * @return bool
	 * @since 6.3
	 */
	private function has_selector( $selectors, $needle ) {
		foreach ( $selectors as $selector ) {
			$value = is_array( $selector ) && isset( $selector['value'] ) ? $selector['value'] : $selector;

			if ( $needle === $value ) {
				return true;
			}
		}

		return false;
	}
}
