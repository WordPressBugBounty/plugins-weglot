<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;
use WeglotWP\Services\User_Api_Service_Weglot;

include_once __DIR__ . '/context.php';
$current_tab = isset( $_GET['tabs'] ) ? sanitize_key( $_GET['tabs'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing
?>
<nav class="sidebar">
	<div class="sidebar-logo"><img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/logo-wg.svg' ); ?>"></div>
	<div class="menu">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=weglot-settings&tabs=home' ) ); ?>" class="menu-item <?php if ( '' === $current_tab || 'home' === $current_tab || 'settings' === $current_tab ) : ?>selected<?php endif; ?>"><img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/home.svg' ); ?>">Home</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=weglot-settings&tabs=dashboard' ) ); ?>" class="menu-item <?php if ( 'dashboard' === $current_tab ) : ?>selected<?php endif; ?>"><img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/settings.svg' ); ?>">Settings</a>
	</div>

	<div id="wrapper-plan">
		<div class="plan-card plan-card--skeleton" id="plan-card-skeleton" aria-busy="true">
			<div class="sk sk-title"></div>

			<div class="sk-progress-bg">
				<div class="sk sk-progress-fill"></div>
			</div>

			<div class="sk-lines-row">
				<div class="sk sk-line"></div>
				<div class="sk sk-line sk-line-2"></div>
			</div>

			<div class="sk sk-btn"></div>
		</div>

		<div class="plan-card plan-card-warning" style="display: none;">
			<div class="panel-title">Plan Starter</div>
			<div class="progress-bar-bg">
				<div class="progress-bar-fill" style="width: 0%;"></div>
			</div>
			<div class="info-word-used">Words used <span>0 / 0</span></div>
			<a href="<?php echo esc_url(Helper_API::get_dashboard_url(). '/' . $workspace_slug . '/modal?tab=billing'); ?>" class="upgrade-btn" target="_blank"><img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/bolt.svg' ); ?>" alt="">Upgrade</a>
		</div>
		<div class="faq-link">Looking for assistance?<br>Ask our team at<br><b>help@weglot.com</b><br>or check out the <a href="https://help.weglot.com/" target="_blank" title="Weglot FAQ">FAQ</a></div>
	</div>

</nav>
