<?php

namespace WeglotWP\Actions\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;

/**
 * Handle deactivation feedback
 *
 * @since 5.4
 */
class Deactivation_Feedback_Weglot implements Hooks_Interface_Weglot {

	/**
	 * @return void
	 * @since 5.4
	 * @see Hooks_Interface_Weglot
	 */
	public function hooks() {
		add_action( 'wp_ajax_weglot_send_deactivation_feedback', array( $this, 'send_deactivation_feedback' ) );
	}

	/**
	 * Send deactivation feedback email
	 *
	 * @return void
	 * @since 5.4
	 */
	public function send_deactivation_feedback() {
		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? wp_unslash( $_POST['nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_verify_nonce() handles validation
		if ( ! wp_verify_nonce( $nonce, 'weglot_deactivation_feedback' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		$raw_reasons = wp_unslash( $_POST['reasons'] ?? [] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via array_map( 'sanitize_text_field' ) on next line
		$reasons     = is_array( $raw_reasons ) ? array_map( 'sanitize_text_field', $raw_reasons ) : [];
		$comment     = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email       = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$site_url    = get_site_url();
		$admin_email = get_option( 'admin_email' );

		$reason_labels = array(
			'no_longer_need'      => 'I no longer need website translations',
			'translation_quality' => 'I\'m not satisfied with the translation quality',
			'price_too_high'      => 'The price is too high for me',
			'too_complex'         => 'The setup or usage was too complex',
			'missing_features'    => 'Missing features I need',
			'technical_issues'    => 'Technical issues or bugs',
			'found_alternative'   => 'I found an alternative translation service that better suits my needs',
			'other'               => 'Other',
		);

		$email_subject = 'Weglot Plugin Deactivation Feedback - ' . $site_url;

		$email_body = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$email_body .= '<h2 style="color: #473ae0;">Weglot Plugin Deactivation Feedback</h2>';
		$email_body .= '<p><strong>Site URL:</strong> ' . esc_html( $site_url ) . '</p>';
		$email_body .= '<p><strong>Admin Email:</strong> ' . esc_html( $admin_email ) . '</p>';

		if ( ! empty( $reasons ) ) {
			$email_body .= '<h3 style="color: #1f2937; margin-top: 20px;">Reasons for deactivation:</h3>';
			$email_body .= '<ul style="margin-left: 20px;">';
			foreach ( $reasons as $reason ) {
				$label = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : $reason;
				$email_body .= '<li>' . esc_html( $label ) . '</li>';
			}
			$email_body .= '</ul>';
		}

		if ( ! empty( $comment ) ) {
			$email_body .= '<h3 style="color: #1f2937; margin-top: 20px;">Additional comment:</h3>';
			$email_body .= '<p style="background: #f9fafb; padding: 15px; border-left: 3px solid #473ae0;">' . nl2br( esc_html( $comment ) ) . '</p>';
		}

		if ( ! empty( $email ) ) {
			$email_body .= '<h3 style="color: #1f2937; margin-top: 20px;">Follow-up email:</h3>';
			$email_body .= '<p>' . esc_html( $email ) . '</p>';
		}

		$email_body .= '<hr style="margin-top: 30px; border: none; border-top: 1px solid #e0e0e0;">';
		$email_body .= '<p style="color: #6b7280; font-size: 12px;">This feedback was sent automatically from the Weglot WordPress plugin.</p>';
		$email_body .= '</body></html>';

		// Email headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $admin_email,
		);

		if ( ! empty( $email ) ) {
			$headers[] = 'Reply-To: ' . $email;
		}

		// Send email
		// @phpstan-ignore-next-line -- WordPress stubs not resolved in current PHPStan config
		$sent = wp_mail( 'help@weglot.com', $email_subject, $email_body, $headers ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- single transactional email, not bulk

		if ( $sent ) {
			wp_send_json_success( array( 'message' => 'Feedback sent successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to send feedback' ) );
		}
	}
}
