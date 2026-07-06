<?php

namespace WeglotWP\Actions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\User_Api_Service_Weglot;

class Ajax_User_Info implements Hooks_Interface_Weglot {
	/**
	 * @var User_Api_Service_Weglot
	 */
	private $user_services;

	/**
	 * @var Option_Service_Weglot
	 */
	private $option_services;

	public function __construct() {
		$this->user_services = weglot_get_service( User_Api_Service_Weglot::class );
		$this->option_services = weglot_get_service( Option_Service_Weglot::class );
	}

	/**
	 * @see Hooks_Interface_Weglot
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'wp_ajax_get_user_info', array( $this, 'get_user_info' ) );
		add_action( 'wp_ajax_get_workspace_info', array( $this, 'get_workspace_info' ) );

	}

	/**
	 * @since 3.0.0
	 * @return void
	 */
	public function get_user_info() {
		if ( ! isset( $_POST['api_key'] ) ) { //phpcs:ignore
			wp_send_json_error();
		}

		$api_key = sanitize_title( $_POST['api_key'] ); //phpcs:ignore

		$response = $this->user_services->get_user_info( $api_key );

		if ( array_key_exists( 'not_exist', $response ) && ! $response['not_exist'] ) {
			wp_send_json_error();
		}

		wp_send_json_success( $response );
	}

	/**
	 * @since 3.0.0
	 * @return void
	 */
	public function get_workspace_info() {
		check_ajax_referer( 'weglot_get_workspace_info', 'security' );

		$api_key = \weglot_get_api_key();

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'API key not found' ) );
		}

		$previous_word_count = $this->option_services->get_option_by_key_v3( 'workspace_usage_word_count' );
		$previous_word_count = is_null( $previous_word_count ) ? null : (int) $previous_word_count;

		$response = $this->user_services->get_workspace_info( $api_key );

		$current_word_count = 0;
		if ( is_array( $response ) && isset( $response['usage']['wordCount'] ) ) {
			$current_word_count = (int) $response['usage']['wordCount'];
		}

		if ( is_array( $response ) ) {
			$this->option_services->update_workspace_info_from_response( $response );
		}

		$has_changed = ( null === $previous_word_count ) ? true : ( $previous_word_count !== $current_word_count );

		wp_send_json_success(
			array(
				'data' => $response,
				'meta' => array(
					'previous_word_count' => $previous_word_count,
					'current_word_count'  => $current_word_count,
					'has_changed'         => $has_changed,
				),
			)
		);
	}
}

