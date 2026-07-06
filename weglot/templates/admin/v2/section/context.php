<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;
use WeglotWP\Services\User_Api_Service_Weglot;

if ( ! isset( $option_services ) ) {
	$option_services = weglot_get_service( 'Option_Service_Weglot' );
}

$workspace_slug = get_option( 'weglot-translate-workspace-slug' );
if ( ! is_string( $workspace_slug ) || trim( $workspace_slug ) === '' ) {
	$throttle_key = 'weglot_workspace_slug_fetch_throttle';
	if ( false === get_transient( $throttle_key ) ) {
		set_transient( $throttle_key, 1, MINUTE_IN_SECONDS * 5 );

		$user_api = weglot_get_service( User_Api_Service_Weglot::class );
		if ( $user_api ) {
			$user_api->get_workspace_info(); // met à jour l'option si possible
			$workspace_slug = get_option( 'weglot-translate-workspace-slug' );
		}
	}
}

$workspace_slug = is_string( $workspace_slug ) ? sanitize_title( $workspace_slug ) : '';

$project_slug = $option_services->get_option( 'project_slug' );
$project_slug = is_string( $project_slug ) ? sanitize_title( $project_slug ) : '';

$dashboard_url = Helper_API::get_dashboard_url();
