<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;
use WeglotWP\Helpers\Helper_Tabs_Admin_Weglot;
use WeglotWP\Services\Button_Service_Weglot;
use WeglotWP\Services\Language_Service_Weglot;
use WeglotWP\Services\Option_Service_Weglot;
use WeglotWP\Services\Version_Service_Weglot;

$option_services    = weglot_get_service( 'Option_Service_Weglot' );
$language_services    = weglot_get_service( 'Language_Service_Weglot' );
$user_services    = weglot_get_service( 'User_Api_Service_Weglot' );

include_once __DIR__ . '/section/context.php';

$languages = $option_services->get_option('languages');
$first_target_language = '';
if (is_array($languages) && !empty($languages) && isset($languages[0]['language_to'])) {
	$first_target_language = $languages[0]['language_to'];
}

$target_languages = is_array($languages) ? count($languages) : 0;

$saved_word_count = $option_services->get_option_by_key_v3( 'workspace_usage_word_count' );
$saved_word_count = is_null( $saved_word_count ) ? 0 : (int) $saved_word_count;
?>

<div class="main-layout">
    <?php include_once 'section/sidebar.php' ?>
    <div class="container">
        <div class="info-panels">
            <div class="panel">
                <div class="panel-title"><?php esc_html_e( 'Original language', 'weglot' ); ?></div>
                <div class="panel-value"><div class="wg-<?php echo esc_attr( $language_services->get_original_language()->getExternalCode() ); ?>"><span class="wglanguage-name"><?php echo esc_html( $language_services->get_original_language()->getEnglishName() ); ?></span></div></div>
            </div>
            <div class="panel">
                <div class="panel-title"><?php esc_html_e( 'Target languages', 'weglot' ); ?></div>
                <div class="panel-value"><?php echo esc_html( $target_languages ); ?></div>
            </div>
			<div class="panel">
				<div class="panel-title"><?php esc_html_e( 'Total words', 'weglot' ); ?></div>
				<div class="panel-value" id="total-words-count"><?php echo esc_html( number_format_i18n( $saved_word_count ) ); ?></div>
			</div>
        </div>
        <h2><?php esc_html_e( 'Quick Links', 'weglot' ); ?></h2>
		<div class="quick-links">
			<div class="card">
				<img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/translation-list.svg' ); ?>" width="32" height="32" alt="" />
				<div class="wrapper-card-text">
					<div class="card-title"><?php esc_html_e( 'Translation list', 'weglot' ); ?></div>
					<div class="card-desc"><?php esc_html_e( 'Edit translations', 'weglot' ); ?></div>
				</div>
				<a target="_blank" href="<?php echo esc_url( Helper_API::build_dashboard_project_url( $workspace_slug, $project_slug, 'languages' ) ); ?>" class="btn-link"><img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/arrow-right.svg' ); ?>"  alt="" /></a>
			</div>
			<div class="card">
				<img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/language-switcher-editor.svg' ); ?>" width="32" height="32" alt="" />
				<div class="wrapper-card-text">
					<div class="card-title"><?php esc_html_e( 'Switcher Editor', 'weglot' ); ?></div>
					<div class="card-desc"><?php esc_html_e( 'Customize your language switcher', 'weglot' ); ?></div>
				</div>
				<a target="_blank" href="<?php echo esc_url( Helper_API::build_dashboard_project_url( $workspace_slug, $project_slug, 'visual-editor', array( 'mode' => 'switchers' ) ) ); ?>" class="btn-link"><img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/arrow-right.svg' ); ?>"  alt="" /></a>
			</div>
			<div class="card">
				<img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/language-model.svg' ); ?>" width="32" height="32" alt="" />
				<div class="wrapper-card-text">
					<div class="card-title"><?php esc_html_e( 'Language model', 'weglot' ); ?></div>
					<div class="card-desc"><?php esc_html_e( 'Create your custom model for translations that match your brand voice.', 'weglot' ); ?></div>
				</div>
				<a target="_blank" href="<?php echo esc_url( Helper_API::build_dashboard_project_url( $workspace_slug, $project_slug, 'language-model' ) ); ?>" class="btn-link"><img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/arrow-right.svg' ); ?>"  alt="" /></a>
			</div>

			<div class="card">
				<img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/exclusion.svg' ); ?>" width="32" height="32" alt="" />
				<div class="wrapper-card-text">
					<div class="card-title"><?php esc_html_e( 'Translation Exclusions', 'weglot' ); ?></div>
					<div class="card-desc"><?php esc_html_e( 'Choose pages or blocks to exclude from translation', 'weglot' ); ?></div>
				</div>
				<a target="_blank" href="<?php echo esc_url( Helper_API::build_dashboard_project_url( $workspace_slug, $project_slug, 'exclusions' ) ); ?>" class="btn-link"><img src="<?php echo esc_url(WEGLOT_DIRURL.'app/images/v2/arrow-right.svg' ); ?>"  alt="" /></a>
			</div>
		</div>
    </div>
</div>
<div id="wrap-weglot">
    <?php
    if ( ! $weglot_options['has_first_settings'] && $weglot_options['show_box_first_settings'] ) :
        $option_services->set_option_by_key( 'show_box_first_settings', false );
        ?>
        <div id="weglot-box-first-settings" class="weglot-box-overlay weglot-v2">

            <div class="weglot-box">

                <img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/rocket.svg' ); ?>" width="64"
                     height="64" alt=""/>
                <a class="weglot-btn-close"><img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/close.svg' ); ?>" width="24"
												 height="24" alt=""/></a>
                <h3 class="weglot-box--title"><?php esc_html_e( 'Congrats! Your website is now multilingual.', 'weglot' ); ?></h3>
                <p class="weglot-box--text"><?php esc_html_e( 'You can customize it and make other improvements like editing your translations in your Weglot Dashboard.', 'weglot' ); ?></p>
				<?php if ($first_target_language !== '') : ?>
					<a class="button button-primary weglot-visit-multilingual" href="<?php echo esc_url( untrailingslashit( home_url() ) . '/' . sanitize_key($first_target_language) . '/' ); ?>" target="_blank">
						<?php esc_html_e( 'Visit my multilingual website', 'weglot' ); ?>
					</a>
				<?php endif; ?>
                <iframe style="display: none" src="<?php echo esc_url( home_url() . '/' . $first_target_language . '/' ); ?>"></iframe>
                </div>
        </div>
    <?php

    endif;

    ?>
</div>
