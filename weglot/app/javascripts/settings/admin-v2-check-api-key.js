const init_v2_api_key_check = function () {
	document.addEventListener('DOMContentLoaded', () => {
		if (!document.querySelector('.wrapper_v2')) {
			return;
		}

		const root =
			document.querySelector('#weglot-settings-v2-form') ||
			document.querySelector('form.is-step-1, form.is-step-2') ||
			document.body;

		const setStep = (step) => {
			if (!root || !root.classList) {
				return;
			}

			if (step === 1) {
				root.classList.add('is-step-1');
				root.classList.remove('is-step-2');
			} else if (step === 2) {
				root.classList.add('is-step-2');
				root.classList.remove('is-step-1');
			}
		};

		document.addEventListener('click', (e) => {
			const target = e.target;

			const toStep1 = target && target.closest ? target.closest('.display-step-2') : null;
			if (toStep1) {
				setStep(2);
				return;
			}

			const goBack = target && target.closest ? target.closest('.go-back, #go-back') : null;
			if (goBack) {
				setStep(1);
			}
		});
		const $ = jQuery;
		const apiKeyInput = $('#api-key');
		const connectButton = $('#btn-connect');

		// Private API keys are "sk_" + 32 hex chars = 35 characters.
		const API_KEY_MIN_LENGTH = 35;

		const handleApiKeyValidation = () => {
			const key = apiKeyInput.val().trim();

			connectButton.prop('disabled', true).text('Checking...');
			apiKeyInput.removeClass('is-valid is-invalid'); // Réinitialise l'état visuel

			// Champ vide : texte par défaut, rien à vérifier.
			if (key.length === 0) {
				connectButton.text('Connect Weglot');
				return;
			}

			// Clé trop courte : on sait qu'elle est invalide, pas besoin d'appeler l'API.
			if (key.length < API_KEY_MIN_LENGTH) {
				connectButton.text('Invalid API Key');
				apiKeyInput.addClass('is-invalid');
				return;
			}

			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					action: 'get_project_settings',
					security: (window.weglotAdmin && window.weglotAdmin.nonces) ? window.weglotAdmin.nonces.get_project_settings : '',
					api_key: key,
				},
				success: ({ success }) => {
					if (success) {
						// Clé valide
						connectButton.prop('disabled', false).text('Connect Weglot');
						apiKeyInput.removeClass('is-invalid').addClass('is-valid');
					} else {
						// Clé invalide (réponse de l'API)
						connectButton.prop('disabled', true).text('Invalid API Key');
						apiKeyInput.removeClass('is-valid').addClass('is-invalid');
					}
				},
				error: () => {
					// Erreur AJAX
					connectButton.prop('disabled', true).text('Error - Please try again');
					apiKeyInput.removeClass('is-valid').addClass('is-invalid');
				}
			});
		};

		let typingTimer;
		apiKeyInput.on('keyup input paste', () => {
			clearTimeout(typingTimer);
			typingTimer = setTimeout(handleApiKeyValidation, 500); // Délai réduit à 500ms
		});

		apiKeyInput.on('keydown', () => {
			clearTimeout(typingTimer);
		});

		if (apiKeyInput.val().trim().length > 0) {
			handleApiKeyValidation();
		}

		$('#weglot-settings-v2-form').on('submit', () => {
			connectButton.prop('disabled', true).text('Activating...');
		});
	});
};

export default init_v2_api_key_check;
