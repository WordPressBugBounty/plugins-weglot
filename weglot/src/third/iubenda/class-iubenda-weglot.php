<?php
namespace WeglotWP\Third\Iubenda;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;


/**
 * Iubenda integration - Exempt Weglot cookies from blocking
 *
 * @since 4.0
 */
class Iubenda_Weglot implements Hooks_Interface_Weglot {

	/**
	 * @var Iubenda_Active
	 */
	private $iubenda_active_services;

	/**
	 * @return void
	 * @throws \Exception
	 * @since 4.0
	 */
	public function __construct() {
		$this->iubenda_active_services = weglot_get_service( Iubenda_Active::class );
	}

	/**
	 * @since 4.0
	 * @see Hooks_Interface_Weglot
	 * @return void
	 */
	public function hooks() {

		if ( ! $this->iubenda_active_services->is_active() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'exempt_weglot_cookies' ), -100 );
		add_action( 'wp_head', array( $this, 'exempt_weglot_cookies' ), 1 );
	}

	/**
	 * Exempt Weglot cookies from Iubenda blocking
	 * This script must load BEFORE Iubenda initializes
	 *
	 * Adds Weglot cookies to Iubenda's whitelist so they are not blocked
	 * even with Consent Mode v2 and URL passthrough enabled
	 *
	 * @since 4.0
	 * @return void
	 */
	public function exempt_weglot_cookies() {

		static $already_output = false;
		if ( $already_output ) {
			return;
		}
		$already_output = true;

		?>
		<script>
			// Configure Iubenda to whitelist Weglot cookies
			// Must be executed before Iubenda CS script loads
			window._iub = window._iub || {};
			window._iub.csConfiguration = window._iub.csConfiguration || {};

			// Initialize cookieWhitelist array if not exists
			if (!Array.isArray(window._iub.csConfiguration.cookieWhitelist)) {
				window._iub.csConfiguration.cookieWhitelist = [];
			}

			// Add Weglot cookies to the whitelist (not subject to consent requirements)
			// These cookies are strictly necessary for language preference functionality
			var weglotCookies = ['WG_CHOOSE_ORIGINAL', 'weglot_language'];
			weglotCookies.forEach(function(cookie) {
				if (window._iub.csConfiguration.cookieWhitelist.indexOf(cookie) === -1) {
					window._iub.csConfiguration.cookieWhitelist.push(cookie);
				}
			});
		</script>
		<?php
	}
}
