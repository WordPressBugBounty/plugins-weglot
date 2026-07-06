/**
 * Weglot Deactivate Popup
 * Shows a popup when user tries to deactivate the plugin (V2 only)
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Get the deactivate link for Weglot plugin
        const deactivateLink = $('#the-list tr[data-slug="weglot"] .deactivate a');

        if (!deactivateLink.length) {
            return;
        }

        // Store the original deactivate URL
        const originalDeactivateUrl = deactivateLink.attr('href');

        // Prevent default deactivation and show popup instead
        deactivateLink.on('click', function(e) {
            e.preventDefault();
            showDeactivatePopup(originalDeactivateUrl);
        });
    });

    /**
     * Redirect to a URL only if it is same-origin (guards against javascript: / data: injection).
     * @param {string} url
     */
    function safeRedirect(url) {
        try {
            // Resolve against the current page URL so WordPress relative links
            // (e.g. "plugins.php?action=deactivate") keep the /wp-admin/ path.
            const parsed = new URL(url, window.location.href);
            if (parsed.origin === window.location.origin) {
                window.location.href = parsed.href;
            }
        } catch (e) {
            // malformed URL — do nothing
        }
    }

    /**
     * Show the deactivate popup
     * @param {string} deactivateUrl - The original deactivate URL
     */
    function showDeactivatePopup(deactivateUrl) {
        // Create popup HTML
        const popupHtml = `
            <div id="weglot-deactivate-popup" class="weglot-box-overlay weglot-v2">
                <div class="weglot-box">
                    <a class="weglot-btn-close">
                        <img src="${weglotDeactivateData.closeIconUrl}" width="24" height="24" alt="Close"/>
                    </a>
                    <h3 class="weglot-box--title">Sorry to see you go 👋</h3>
                    <p class="weglot-box--text">Before you deactivate Weglot, could you tell us why?<br>Your feedback helps us improve the product and your experience.</p>

                    <form id="weglot-deactivate-form" class="weglot-deactivate-form">
                        <div class="weglot-deactivate-reasons">
                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="no_longer_need">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">I no longer need website translations</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="translation_quality">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">I'm not satisfied with the translation quality</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="price_too_high">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">The price is too high for me</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="too_complex">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">The setup or usage was too complex</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="missing_features">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">Missing features I need</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="technical_issues">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">Technical issues or bugs</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="found_alternative">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">I found an alternative translation service that better suits my needs</span>
                            </label>

                            <label class="weglot-reason-option">
                                <input type="checkbox" name="reason" value="other">
                                <svg class="check-icon" viewBox="0 0 16 16">
                                    <path d="M4 8.25L6.5 10.75L12 5.25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="reason-label">Other</span>
                            </label>
                        </div>

                        <div class="weglot-form-field">
                            <label for="weglot-comment">Add a comment (optional)</label>
                            <textarea id="weglot-comment" name="comment" rows="4" placeholder="Tell us more about your experience..."></textarea>
                        </div>

                        <div class="weglot-form-field">
                            <label for="weglot-email">Can we follow up? We'd love to better understand your feedback</label>
                            <input type="email" id="weglot-email" name="email" placeholder="Email address">
                        </div>

                        <div class="weglot-deactivate-buttons">
                            <button type="button" class="weglot-btn-skip">Skip and deactivate</button>
                            <button type="submit" class="weglot-btn-deactivate">Send feedback & deactivate</button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        // Add popup to body
        $('body').append(popupHtml);

        const $popup = $('#weglot-deactivate-popup');

        // Close popup when clicking the close button
        $popup.find('.weglot-btn-close').on('click', function(e) {
            e.preventDefault();
            $popup.fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Skip and deactivate directly (no feedback)
        $popup.find('.weglot-btn-skip').on('click', function(e) {
            e.preventDefault();
            safeRedirect(deactivateUrl);
        });

        // Close popup when clicking outside the box
        $popup.on('click', function(e) {
            if ($(e.target).is('.weglot-box-overlay')) {
                $popup.fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });

        // Handle form submission - Send feedback via AJAX
        $popup.find('#weglot-deactivate-form').on('submit', function(e) {
            e.preventDefault();

            // Collect form data
            const reasons = [];
            $popup.find('input[name="reason"]:checked').each(function() {
                reasons.push($(this).val());
            });

            const formData = {
                action: 'weglot_send_deactivation_feedback',
                nonce: weglotDeactivateData.nonce,
                reasons: reasons,
                comment: $popup.find('#weglot-comment').val(),
                email: $popup.find('#weglot-email').val()
            };

            // Disable submit button during request
            const $submitBtn = $popup.find('.weglot-btn-deactivate');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Sending...');

            // Send feedback via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    // Redirect to deactivate regardless of email success
                    safeRedirect(deactivateUrl);
                },
                error: function() {
                    // Even if email fails, allow deactivation
                    safeRedirect(deactivateUrl);
                }
            });
        });
    }

})(jQuery);
