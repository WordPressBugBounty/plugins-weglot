<?php

namespace WeglotWP\Actions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\Webhook_Service_Weglot;

/**
 * Handles the webhook lifecycle on plugin activation and deactivation.
 *
 * @since 3.0.0
 */
class Webhook_Action_Weglot implements Hooks_Interface_Weglot {

	/**
	 * @return void
	 * @since 3.0.0
	 */
	public function hooks(): void {
		add_action( 'admin_init', array( $this, 'maybe_register_webhook_after_update' ) );
	}

	/**
	 * Registers the webhook once after each plugin version change, covering updates
	 * that bypass the activation hook (e.g. automatic or manual file replacement).
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function maybe_register_webhook_after_update(): void {
		$option_key     = sprintf( '%s-%s', WEGLOT_SLUG, 'db_version' );
		$stored_version = get_option( $option_key, '' );

		if ( $stored_version === WEGLOT_VERSION ) {
			return;
		}

		update_option( $option_key, WEGLOT_VERSION );

		$api_key = weglot_get_service( Option_Service_Weglot::class )->get_api_key_private();
		if ( ! is_string( $api_key ) || '' === $api_key ) {
			return;
		}

		weglot_get_service( Webhook_Service_Weglot::class )->register_webhook( $api_key );
	}

	/**
	 * @return void
	 * @since 3.0.0
	 */
	public function activate(): void {
		$api_key = weglot_get_service( Option_Service_Weglot::class )->get_api_key_private();
		if ( ! is_string( $api_key ) || '' === $api_key ) {
			return;
		}
		weglot_get_service( Webhook_Service_Weglot::class )->register_webhook( $api_key );
	}

	/**
	 * @return void
	 * @since 3.0.0
	 */
	public function deactivate(): void {
		weglot_get_service( Webhook_Service_Weglot::class )->delete_webhook();
	}
}
