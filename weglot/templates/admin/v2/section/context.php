<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;
use WeglotWP\Services\User_Api_Service_Weglot;

if ( ! isset( $option_services ) ) {
	$option_services = weglot_get_service( 'Option_Service_Weglot' );
}

$user_api       = weglot_get_service( User_Api_Service_Weglot::class );
$workspace_slug = $user_api ? $user_api->get_workspace_slug() : '';

$project_slug = $option_services->get_option( 'project_slug' );
$project_slug = is_string( $project_slug ) ? $project_slug : '';

$dashboard_url = Helper_API::get_dashboard_url();
