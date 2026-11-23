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
		<h1><?php esc_html_e( 'General', 'weglot' ); ?></h1>
		<form class="weglot-form" id="weglot-form">
			<?php $api_key_is_read_only = $option_services->has_api_key_private_constant(); ?>
			<label for="api-key"><?php esc_html_e( 'API Key', 'weglot' ); ?></label>
			<input
				type="text"
				id="api-key"
				name="api-key"
				value="<?php echo esc_attr( $option_services->get_displayed_api_key_private() ); ?>"
				<?php if ( $api_key_is_read_only ) : ?>readonly<?php endif; ?>
			>
			<span class="error-message" aria-live="polite" style="display: none;"><?php esc_html_e( 'The API key is invalid. Please double check and try again.', 'weglot' ); ?></span>
			<?php if ( $api_key_is_read_only ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: name of the PHP constant holding the API key. */
						esc_html__( 'Defined by the %s constant in your server configuration. It is never stored in the database and cannot be edited here. Only the last 4 characters are shown.', 'weglot' ),
						'<code>WEGLOT_API_KEY_PRIVATE</code>'
					);
					?>
				</p>
			<?php endif; ?>
			<label for="source-lang"><?php esc_html_e( 'Source language', 'weglot' ); ?></label>
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
			<label for="target-lang"><?php esc_html_e( 'Target language', 'weglot' ); ?></label>
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

				<?php foreach ( $languages as $language ) : ?>
					<option
						value="<?php echo esc_attr( $language->getInternalCode() ); ?>"
						<?php selected( true, in_array( $language, $destination_languages, true ) ); ?>>
						<?php echo esc_html( $language->getLocalName() ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<p class="weglot-dashboard">
				<?php esc_html_e( 'Want to make a change?', 'weglot' ); ?>
				<a href="<?php echo esc_url( Helper_API::build_dashboard_project_url( $workspace_slug, $project_slug, 'languages' ) ); ?>" target="_blank"><?php esc_html_e( 'Go to Weglot dashboard', 'weglot' ); ?></a>
			</p>

			<h1><?php esc_html_e( 'Language switcher', 'weglot' ); ?></h1>
			<div class="caption"><?php esc_html_e( 'The switcher is usually at the bottom right. Feel free to change it up whenever you want.', 'weglot' ); ?></div>
			<div class="quick-links fullsize">
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/menu.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title"><?php esc_html_e( 'In menu', 'weglot' ); ?></div>
							<div class="card-desc"><?php esc_html_e( 'Place the button in a menu area.', 'weglot' ); ?></div>
						</div>
					</div>
					<div class="wrapper-link-tab">
						<a target="_blank" href="<?php echo esc_url( $menu_services->weglot_get_navigation_admin_url() ); ?>" class=""><?php esc_html_e( 'How to use', 'weglot' ); ?></a> -
						<a href="<?php echo esc_url( $menu_services->weglot_get_navigation_admin_url() ); ?>" class="grey-btn-action"><?php esc_html_e( 'Go to Menus', 'weglot' ); ?></a>
					</div>
				</div>
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/widget.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title"><?php esc_html_e( 'In widget', 'weglot' ); ?></div>
							<div class="card-desc"><?php esc_html_e( 'Add the button to a widget area.', 'weglot' ); ?></div>
						</div>
					</div>

					<div class="wrapper-link-tab">
						<a target="_blank" href="<?php echo esc_url( $menu_services->weglot_get_navigation_admin_url() ); ?>" class=""><?php esc_html_e( 'How to use', 'weglot' ); ?></a> -
						<a href="<?php echo esc_url( $menu_services->weglot_get_widgets_admin_url() ); ?>" class="grey-btn-action"><?php esc_html_e( 'Go to Widget', 'weglot' ); ?></a>
					</div>
				</div>
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/code.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title"><?php esc_html_e( 'With a shortcode', 'weglot' ); ?></div>
							<div class="card-desc">
								<?php
								/* translators: %s: the language switcher shortcode. */
								printf( esc_html__( 'Use %s anywhere in your content.', 'weglot' ), '[weglot_switcher]' );
								?>
							</div>
						</div>
					</div>
					<a href="#" data-to-copy="[weglot_switcher]" class="btn-link wg-copy grey-btn-action"><?php esc_html_e( 'Copy shortcode', 'weglot' ); ?></a>
				</div>
				<div class="card no-hover">
					<div class="card-top">
						<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/div-code.svg' ); ?>" width="32"
							 height="32" alt=""/>
						<div class="wrapper-card-text">
							<div class="card-title"><?php esc_html_e( 'In the source code', 'weglot' ); ?></div>
							<div class="card-desc"><?php esc_html_e( 'Add a code whenever in the source code of your HTML page.', 'weglot' ); ?></div>
						</div>
					</div>
					<a href="#" data-to-copy="<?php echo esc_attr( '<div id="weglot_here"></div>' ); ?>" class="btn-link wg-copy grey-btn-action"><?php esc_html_e( 'Copy snippet', 'weglot' ); ?></a>
				</div>
			</div>
			<h1><?php esc_html_e( 'Other options', 'weglot' ); ?></h1>
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
					<span class="option-label"><?php esc_html_e( 'Translate WordPress emails', 'weglot' ); ?></span>
					<span class="option-desc"><?php esc_html_e( 'Automatically translate all emails sent from your website.', 'weglot' ); ?></span>
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
					<span class="option-label"><?php esc_html_e( 'Translate mobile-optimized pages (AMP)', 'weglot' ); ?></span>
					<span class="option-desc"><?php esc_html_e( 'Make sure your translated pages load fast and display correctly on mobile devices.', 'weglot' ); ?></span>
				</label>
				<label class="option-row">
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
					<span class="option-label"><?php esc_html_e( 'Enable multilingual search', 'weglot' ); ?></span>
					<span class="option-desc"><?php esc_html_e( 'Let visitors search in any language and get corresponding results', 'weglot' ); ?></span>
				</label>
			</div>
			<div id="save_settings_bar">
				<a href="#" id="cancel-save-settings"><?php esc_html_e( 'Cancel', 'weglot' ); ?></a>
				<a href="#" id="confirm-save-settings"><?php esc_html_e( 'Save', 'weglot' ); ?></a>
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
