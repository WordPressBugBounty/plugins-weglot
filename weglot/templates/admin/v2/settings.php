<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;
use WeglotWP\Services\Version_Service_Weglot;


$version_service = weglot_get_service( Version_Service_Weglot::class );

$dashboard_url   = Helper_API::get_register_url( true );
$weglot_options  = weglot_get_options();
$option_services = weglot_get_service( 'Option_Service_Weglot' );
$url_form        = wp_nonce_url(
	add_query_arg(
		[
			'action' => 'weglot_save_settings',
			'tab'    => 'settings',
		],
		admin_url( 'admin-post.php' )
	),
	'weglot_save_settings'
);
?>

<?php if ( ! $this->options['has_first_settings'] ) :

	if ( isset( $_GET['tabs'] ) && $_GET['tabs'] === 'dashboard' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing, no data processing
		include_once 'dashboard.php';
	} else {
		include_once 'home.php';
	}
	?>

<?php else: ?>
	<form
		id="weglot-settings-v2-form"
		method="POST"
		class="is-step-1"
		action="<?php echo esc_url( $url_form ); ?>"
	>

		<div class="wrapper_v2 step-1">
			<div>
				<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/beyond-icon.svg' ); ?>" width="250"
					 height="158" alt=""/>
			</div>
			<?php if ( $weglot_options['has_first_settings'] ) : ?>
				<h1>Translate your website</h1>
				<p>Connect Weglot and make your website accessible to audiences around the world.</p>

			<?php endif; ?>

			<a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" class="btn-get-key display-step-2">

                <span class="btn-icon-left">
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" fill="none">
<path d="M0 8C0 3.58172 3.58172 0 8 0H40C44.4183 0 48 3.58172 48 8V40C48 44.4183 44.4183 48 40 48H8C3.58172 48 0 44.4183 0 40V8Z" fill="white" fill-opacity="0.2"/>
<mask id="mask0_8010_11553" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="12" y="12" width="24" height="24">
<rect x="12" y="12" width="24" height="24" fill="#D9D9D9"/>
</mask>
<g mask="url(#mask0_8010_11553)">
<path d="M22.7443 32.2443L16.6656 26.2595C16.3309 25.925 16.1501 25.525 16.1231 25.0595C16.0962 24.5942 16.2335 24.1807 16.5348 23.8193L19.6501 20.0423C19.8142 19.8282 20.0194 19.6652 20.2656 19.553C20.5117 19.4408 20.772 19.3848 21.0463 19.3848H26.9636C27.2379 19.3848 27.4981 19.4408 27.7443 19.553C27.9905 19.6652 28.1956 19.8282 28.3596 20.0423L31.4751 23.8193C31.7764 24.1807 31.9136 24.5942 31.8866 25.0595C31.8596 25.525 31.6788 25.925 31.3443 26.2595L25.2751 32.2443C24.9302 32.5891 24.5084 32.7615 24.0096 32.7615C23.5109 32.7615 23.0891 32.5891 22.7443 32.2443ZM15.5116 16.5598C15.6604 16.4109 15.8345 16.3365 16.0338 16.3365C16.2331 16.3365 16.4104 16.4109 16.5656 16.5598L17.5598 17.529C17.7148 17.6777 17.7923 17.8542 17.7923 18.0587C17.7923 18.2632 17.7148 18.443 17.5598 18.598C17.411 18.7468 17.2343 18.8212 17.0298 18.8212C16.8253 18.8212 16.6456 18.7468 16.4906 18.598L15.5116 17.6135C15.3566 17.4648 15.2791 17.2908 15.2791 17.0913C15.2791 16.8919 15.3566 16.7148 15.5116 16.5598ZM24.0096 14.5C24.2224 14.5 24.4006 14.5718 24.5443 14.7155C24.6878 14.859 24.7596 15.0372 24.7596 15.25V16.6348C24.7596 16.8476 24.6878 17.0258 24.5443 17.1693C24.4006 17.3129 24.2224 17.3848 24.0096 17.3848C23.7969 17.3848 23.6187 17.3129 23.4751 17.1693C23.3316 17.0258 23.2598 16.8476 23.2598 16.6348V15.25C23.2598 15.0372 23.3316 14.859 23.4751 14.7155C23.6187 14.5718 23.7969 14.5 24.0096 14.5ZM32.4731 16.5598C32.6282 16.7148 32.7032 16.8903 32.6981 17.0865C32.6929 17.2827 32.6128 17.4583 32.4578 17.6135L31.4788 18.598C31.3301 18.7468 31.1546 18.8228 30.9521 18.826C30.7494 18.8292 30.5737 18.7532 30.4251 18.598C30.2764 18.4493 30.2036 18.2712 30.2068 18.0635C30.21 17.8558 30.2859 17.6777 30.4346 17.529L31.4038 16.5598C31.5526 16.4109 31.7309 16.3365 31.9386 16.3365C32.1462 16.3365 32.3244 16.4109 32.4731 16.5598ZM18.2886 25.7692L23.7981 31.1905C23.8557 31.2482 23.9262 31.277 24.0096 31.277C24.0931 31.277 24.1636 31.2482 24.2213 31.1905L29.7213 25.7692H18.2886ZM18.1001 24.2692H29.9098L27.2038 21C27.1718 20.968 27.135 20.9407 27.0933 20.9182C27.0516 20.8957 27.0084 20.8845 26.9636 20.8845H21.0463C21.0013 20.8845 20.958 20.8957 20.9163 20.9182C20.8746 20.9407 20.8378 20.968 20.8058 21L18.1001 24.2692Z" fill="white"/>
</g>
</svg>
  </span>
				<p class="btn-text">
					New to Weglot?<br />
					<span class="btn-subtext">Get your free key in 2 minutes</span>
				</p>

				<span class="btn-icon-right">
    <img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/arrow-right-white.svg' ); ?>" width="20" height="20"
		 alt=""/>
  </span>
			</a>

			<p id="have-api-key">Already have an API key? <a href="#" class="display-step-2">Activate Weglot immediately</a></p>

		</div>
		<div class="wrapper_v2 step-2">
			<div>
				<img src="<?php echo esc_url( WEGLOT_DIRURL . 'app/images/v2/icon-key.svg' ); ?>" width="48" height="48"
					 alt=""/>
			</div>
			<?php if ( $weglot_options['has_first_settings'] ) : ?>
				<h1>Activate Weglot</h1>
				<p>Enter your API key to get started</p>

			<?php endif; ?>
			<div class="input-wrapper">
				<label for="api-key">Weglot API key</label>
				<input
					type="text"
					id="api-key"
					name="<?php echo esc_attr( sprintf( '%s[api_key_private]', WEGLOT_SLUG ) ); ?>"
					placeholder="Please enter your API Key"
					value="<?php echo esc_attr( $option_services->get_api_key_private() ); ?>"
				>
				<div id="next-step-info">
					<p>Next step - Generate your first translations</p>
					<div class="steps">
						<div class="step">
							<div class="badge">1</div>
							<p>Browse your website</p>
						</div>

						<div class="step">
							<div class="badge">2</div>
							<p>Visit a few pages (home, products, blog...)</p>
						</div>

						<div class="step">
							<div class="badge">3</div>
							<p>See your site translated instantly ✨.</p>
						</div>
					</div>

				</div>
				<button type="submit" id="btn-connect" class="btn-connect" disabled>Activate Weglot</button>
				<a href="#" id="go-back">Back</a>
			</div>
		</div>
	</form>

<?php endif; ?>
