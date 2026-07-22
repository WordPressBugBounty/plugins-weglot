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

$option_services   = weglot_get_service( 'Option_Service_Weglot' );
$language_services = weglot_get_service( 'Language_Service_Weglot' );
$menu_services = weglot_get_service( 'Menu_Options_Service_Weglot' );
$destination_language = $option_services->get_option( 'destination_language' );
$target_languages     = is_array( $destination_language ) ? count( $destination_language ) : 0;
include_once __DIR__ . '/section/context.php';
?>

<div class="main-layout dashboard">
	<?php include_once 'section/sidebar.php' ?>
	<div class="container">
		<h1>General</h1>
		<form class="weglot-form" id="weglot-form">
			<label for="api-key">API Key</label>
			<input type="text" id="api-key" name="api-key" value="<?php echo esc_attr( $option_services->get_option( 'api_key_private' ) ); ?>">
			<span class="error-message" aria-live="polite" style="display: none;">The API key is invalid. Please double check and try again.</span>
			<label for="source-lang">Source language</label>
			<div class="source-lang-label wg-<?php echo esc_attr( $language_services->get_original_language()->getExternalCode() ); ?>">
			</div>
			<select id="source-lang" name="source-lang" class="weglot-select-source" style="display:none;">
				<?php
				$original_language = $language_services->get_original_language();
				$all_languages = $language_services->get_languages_available( array( 'sort' => true ) );

				foreach ( $all_languages as $language ) :
					?>
					<option
						value="<?php echo esc_attr( $language->getInternalCode() ); ?>"
						<?php selected( $language->getInternalCode(), $original_language->getInternalCode() ); ?>>
						<?php echo esc_html( $language->getLocalName() ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<label for="target-lang">Target language</label>
			<select id="target-lang" name="target-lang[]" class="weglot-select-destination" multiple style="display:none;">
				<?php
				$languages             = $language_services->get_all_languages();
				$destination_languages = $language_services->get_destination_languages( true );

				foreach ( $destination_languages as $language ) :
					?>
					<option
						value="<?php echo esc_attr( $language->getInternalCode() ); ?>"
						selected="selected">
						<?php echo esc_html( $language->getEnglishName() ); ?>
					</option>
				<?php endforeach; ?>

				<!-- Ensuite, toutes les autres langues -->
				<?php foreach ( $languages as $language ) : ?>
					<option
						value="<?php echo esc_attr( $language->getInternalCode() ); ?>"
						<?php selected( true, in_array( $language, $destination_languages, true ) ); ?>>
						<?php echo esc_html( $language->getLocalName() ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<p class="weglot-dashboard">
				Want to make a change?
				<a href="<?php echo esc_url(Helper_API::get_dashboard_url(). '/' . $workspace_slug . '/' . $project_slug . '/languages'); ?>" target="_blank">Go to Weglot dashboard</a>
			</p>

			<h1>Language switcher</h1>
			<div class="caption">The switcher is usually at the bottom right. Feel free to change it up whenever you want.
			</div>
			<div class="quick-links fullsize">
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/menu.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title">In menu</div>
							<div class="card-desc">Place the button in a menu area.</div>
						</div>
					</div>
					<div class="wrapper-link-tab">
						<a target="_blank" href="<?php echo esc_url( $menu_services->weglot_get_navigation_admin_url() ); ?>" class="">How to use</a> -
						<a href="<?php echo esc_url( $menu_services->weglot_get_navigation_admin_url() ); ?>" class="grey-btn-action">Go to Menus</a>
					</div>
				</div>
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/widget.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title">In widget</div>
							<div class="card-desc">Add the button to a widget area.</div>
						</div>
					</div>

					<div class="wrapper-link-tab">
						<a target="_blank" href="<?php echo esc_url( $menu_services->weglot_get_navigation_admin_url() ); ?>" class="">How to use</a> -
						<a href="<?php echo esc_url( $menu_services->weglot_get_widgets_admin_url() ); ?>" class="grey-btn-action">Go to Widget</a>
					</div>
				</div>
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/code.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title">With a shortcode</div>
							<div class="card-desc">Use [weglot_switcher] anywhere in your content.</div>
						</div>
					</div>
					<a href="#" data-to-copy="[weglot_switcher]" class="btn-link wg-copy grey-btn-action">Copy shortcode</a>
				</div>
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/div-code.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title">In the source code</div>
							<div class="card-desc">Add a code whenever in the source code of your HTML page.</div>
						</div>
					</div>
					<a href="#" data-to-copy="<?php echo esc_attr( '<div id="weglot_here"></div>' ); ?>" class="btn-link wg-copy grey-btn-action">Copy snippet</a>
				</div>
			</div>
			<h1>Other options</h1>
			<div class="options-section">
				<label class="option-row">
					<input type="checkbox" name="translate_email" <?php checked( $option_services->get_option_custom_settings( 'translate_email' ), true ); ?>/>
					<svg class="check-icon" viewBox="0 0 16 16">
						<path
							d="M4 8.25L6.5 10.75L12 5.25"
							stroke="currentColor"
							stroke-width="1.5"
							stroke-linecap="round"
							stroke-linejoin="round"
						/>
					</svg>
					<span class="option-label">Translate WordPress emails</span>
					<span class="option-desc">Automatically translate all emails sent from your website.</span>
				</label>
				<label class="option-row">
					<input type="checkbox" name="translate_amp" <?php checked( $option_services->get_option_custom_settings( 'translate_amp' ), true ); ?>/>
					<svg class="check-icon" viewBox="0 0 16 16">
						<path
							d="M4 8.25L6.5 10.75L12 5.25"
							stroke="currentColor"
							stroke-width="1.5"
							stroke-linecap="round"
							stroke-linejoin="round"
						/>
					</svg>
					<span class="option-label">Translate mobile-optimized pages (AMP)</span>
					<span class="option-desc">Make sure your translated pages load fast and display correctly on mobile devices.</span>
				</label>
				<label class="option-row" style="display: none;">
					<input type="checkbox" name="translate_search" <?php checked( $option_services->get_option_custom_settings( 'translate_search' ), true ); ?>/>
					<svg class="check-icon" viewBox="0 0 16 16">
						<path
							d="M4 8.25L6.5 10.75L12 5.25"
							stroke="currentColor"
							stroke-width="1.5"
							stroke-linecap="round"
							stroke-linejoin="round"
						/>
					</svg>
					<span class="option-label">Enable multilingual search</span>
					<span class="option-desc">Let visitors search in any language and get corresponding results</span>
				</label>
			</div>
			<div id="save_settings_bar">
				<a href="#" id="cancel-save-settings">Cancel</a>
				<a href="#" id="confirm-save-settings">Save</a>
			</div>
			<div class="toast-container" id="toast-container"></div>
		</form>

		<h1 id="reset_options"><?php esc_html_e( 'Reset Configuration', 'weglot' ); ?></h1>
		<p><?php esc_html_e( 'If you want to restart the onboarding process, you can reset all Weglot plugin settings stored in your WordPress database. This will not affect your Weglot project or translations.', 'weglot' ); ?></p>
		<div class="options-section">
			<div class="option-row" style="display: flex; align-items: flex-start; gap: 16px;">

				<?php
				$delete_url = wp_nonce_url(
					add_query_arg(
						[ 'action' => 'weglot_delete_options' ],
						admin_url( 'admin-post.php' )
					),
					'weglot_delete_options'
				);
				?>
				<a href="<?php echo esc_url( $delete_url ); ?>"
				   class="reset-btn-action"
				   onclick="return confirm('<?php echo esc_js( __( 'Do you want to reset all Weglot settings and restart the onboarding? Your Weglot project will not be affected.', 'weglot' ) ); ?>');">
					<?php esc_html_e( 'Reset Settings', 'weglot' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
