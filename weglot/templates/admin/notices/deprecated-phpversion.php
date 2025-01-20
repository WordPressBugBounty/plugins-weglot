<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="error settings-error notice is-dismissible">
	<p>
		<?php
			echo esc_html__( 'Weglot also works with PHP 7.2.24+ . However, these versions have reached their official End Of Life and may expose your site to security vulnerabilities. Please consider upgrading to PHP 8.1 or higher, which is the minimum version that still receives security updates.', 'weglot' );
		?>
	</p>
</div>
