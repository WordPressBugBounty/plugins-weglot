const init_v2_dashboard = function () {
	document.addEventListener('DOMContentLoaded', () => {


		const TOAST_DURATION = 1500; // en ms, doit matcher l'animation

		function showToast(title, description, type = 'success') {
			const container = document.getElementById('toast-container');
			if (!container) {
				return;
			}

			const ALLOWED_TOAST_TYPES = { success: true, warning: true, danger: true, info: true };
			const safeType = (typeof type === 'string' && ALLOWED_TOAST_TYPES[type]) ? type : 'info';

			const MAX_TITLE_LENGTH = 120;
			const MAX_DESCRIPTION_LENGTH = 500;

			const normalizeToastText = (input, maxLen) => {
				let s = String(input == null ? '' : input);

				// Normalisation Unicode (si supportée) pour éviter des variantes “look-alike” et formes composées
				if (typeof s.normalize === 'function') {
					try {
						s = s.normalize('NFKC');
					} catch (e) {
						// ignore si certains moteurs refusent la forme
					}
				}

				// Supprime les caractères de contrôle ASCII + DEL
				s = s.replace(/[\u0000-\u001F\u007F]/g, '');

				// Supprime quelques contrôles Unicode fréquemment problématiques
				// - U+2028/U+2029 (séparateurs de ligne/paragraphes)
				// - U+200E/U+200F (marks directionnels)
				// - U+2066..U+2069 (isolation directionnelle)
				s = s.replace(/[\u2028\u2029\u200E\u200F\u2066-\u2069]/g, '');

				// Normalise les fins de ligne et espaces (optionnel mais utile pour éviter les toasts “géants”)
				s = s.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
				s = s.replace(/[ \t\f\v]+/g, ' ');

				s = s.trim();

				if (maxLen && s.length > maxLen) {
					s = s.slice(0, maxLen) + '…';
				}

				return s;
			};
			const safeTitle = normalizeToastText(title, MAX_TITLE_LENGTH);
			const safeDescription = normalizeToastText(description, MAX_DESCRIPTION_LENGTH);


			const toast = document.createElement('div');
			toast.className = `toast toast--${safeType}`;

			// icône simple (tu peux remplacer par tes <svg> ou fonts)
			const icon = document.createElement('div');
			icon.className = 'toast__icon';
			icon.textContent =
				type === 'success' ? '✓' :
					type === 'warning' ? '!' :
						type === 'danger'  ? '✕' :
							'i';

			const body = document.createElement('div');
			body.className = 'toast__body';

			const titleEl = document.createElement('p');
			titleEl.className = 'toast__title';
			titleEl.textContent = safeTitle;

			body.appendChild(titleEl);

			if (safeDescription) {
				const descEl = document.createElement('p');
				descEl.className = 'toast__description';
				descEl.textContent = safeDescription;
				body.appendChild(descEl);
			}

			const close = document.createElement('button');
			close.className = 'toast__close';
			close.setAttribute('aria-label', 'Fermer');
			close.innerHTML = '&times;';
			close.addEventListener('click', () => {
				container.removeChild(toast);
			});

			const progress = document.createElement('div');
			progress.className = 'toast__progress toast__progress--animate';

			toast.appendChild(icon);
			toast.appendChild(body);
			toast.appendChild(close);
			toast.appendChild(progress);

			container.appendChild(toast);

			// auto-dismiss quand l’animation se termine
			setTimeout(() => {
				if (container.contains(toast)) {
					container.removeChild(toast);
				}
			}, TOAST_DURATION);
		}

		if (!document.getElementById('weglot-form')) {
			return;
		}
		const $ = jQuery;

		function showInlineToast(buttonElement) {
			// Supprime les anciens toasts inline s'il y en a
			$('.wg-inline-toast').remove();

			// Crée le toast
			const toast = $('<div>')
				.addClass('wg-inline-toast')
				.html('<span class="wg-inline-toast__icon">✓</span> Copied!');

			// Positionne le toast à gauche du bouton
			const $button = $(buttonElement);
			const buttonOffset = $button.offset();
			const toastWidth = 90; // largeur approximative du toast

			toast.css({
				position: 'absolute',
				top: buttonOffset.top,
				left: buttonOffset.left - toastWidth - 8
			});

			// Ajoute au body
			$('body').append(toast);

			// Animation d'apparition
			setTimeout(() => toast.addClass('wg-inline-toast--show'), 10);

			// Auto-suppression après 1.5s
			setTimeout(() => {
				toast.removeClass('wg-inline-toast--show');
				setTimeout(() => toast.remove(), 300);
			}, 1500);
		}

		$(document).on('click', 'a.btn-link.wg-copy', async function (e) {
			const textToCopy = String($(this).attr('data-to-copy') || '').trim();

			// Si pas de data-to-copy, on ne fait rien (et on laisse le comportement par défaut si besoin)
			if (!textToCopy) {
				return;
			}

			e.preventDefault();

			try {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					await navigator.clipboard.writeText(textToCopy);
				} else {
					throw new Error('Clipboard API not available');
				}

				showInlineToast(this);
			} catch (err) {
				// Fallback (anciens navigateurs / permissions)
				const $tmp = $('<textarea>')
					.val(textToCopy)
					.css({ position: 'fixed', left: '-9999px', top: '0' });

				$('body').append($tmp);
				$tmp[0].select();
				document.execCommand('copy');
				$tmp.remove();

				showInlineToast(this);
			}
		});

		const generate_destination_language = () => {
			return weglot_languages.available.filter(itm => {
				return itm.internal_code !== weglot_languages.original;
			});
		};

		const targetLangSelect = $("#target-lang");
		const sourceLangSelect = $("#source-lang");

		if (sourceLangSelect.length > 0) {
			const sourceSelectizeInstance = sourceLangSelect.selectize({
				persist: false,
				valueField: "value",
				labelField: "text",
				searchField: ["value", "text"],
				sortField: [{ field: "text", direction: "asc" }],
				maxItems: 1,
				render: {
					item: function(item, escape) {
						const language = weglot_languages.available.find(l => l.internal_code === item.value);
						if (language) {
							return `<div class="wg-${escape(language.external_code)}">
                                        <span class="wglanguage-name"></span>
                                        ${escape(language.english)}
                                    </div>`;
						}
						return `<div>${escape(item.text)}</div>`;
					},
					option: function(item, escape) {
						const language = weglot_languages.available.find(l => l.internal_code === item.value);
						if (language) {
							return `<div class="weglot__choice__language wg-${escape(language.external_code)}">
                                        <span class="weglot__choice__language--english">${escape(language.english)}</span>
                                        <span class="weglot__choice__language--local">${escape(language.local)} [${escape(language.external_code)}]</span>
                                    </div>`;
						}
						return `<div>${escape(item.text)}</div>`;
					}
				}
			});

			// Désactiver le select source
			sourceSelectizeInstance[0].selectize.disable();
		}

		if (targetLangSelect.length > 0) {

			if (targetLangSelect[0].selectize) {
				targetLangSelect[0].selectize.destroy();
			}

			const selectizeInstance = targetLangSelect.selectize({
				delimiter: "|",
				persist: false,
				valueField: "internal_code",
				labelField: "local",
				searchField: ["internal_code", "english", "local"],
				sortField: [{ field: "english", direction: "asc" }],
				maxItems: weglot_languages.limit,
				plugins: ["remove_button"],
				options: generate_destination_language(),
				items: weglot_languages.selected || [],
				hideSelected: true,
				render: {
					item: function(item, escape) {
						var english = escape(item.english);
						var external = escape(item.external_code);
						return `<div class="wg-${external} item">
                                    <span class="wglanguage-name"></span>
                                    ${english}
                                </div>`;
					},
					option: function(item, escape) {
						var english = escape(item.english);
						var local = escape(item.local);
						var external = escape(item.external_code);
						return `<div class="weglot__choice__language wg-${external}">
                                    <span class="weglot__choice__language--english">${english}</span>
                                    <span class="weglot__choice__language--local">${local} [${external}]</span>
                                </div>`;
					}
				}
			});

			selectizeInstance[0].selectize.disable();
			targetLangSelect.on("change", function() {
				const selectedLanguages = selectizeInstance[0].selectize.getValue();
				// Votre logique de gestion du changement ici
			});
		}


		const form = document.getElementById('weglot-form');
		const bar = document.getElementById('save_settings_bar');
		const apiKeyInput = $('#api-key');
		const errorMessage = document.querySelector('#api-key + .error-message');
		const saveMessage = document.getElementById('save_message');

		if (form && bar) {

			console.log(apiKeyInput)
			let initialData = new FormData(form);

			const snapshotForm = (formEl) => {
				const fd = new FormData(formEl);

				return Array.from(fd.entries())
					.map(([k, v]) => [k, String(v)])
					.sort((a, b) => (a[0] + "\u0000" + a[1]).localeCompare(b[0] + "\u0000" + b[1]));
			};

			let initialSnapshot = snapshotForm(form);
			const initialApiKey = apiKeyInput.val().trim();

			const isFormDirty = (formEl) => {
				const current = snapshotForm(formEl);

				if (current.length !== initialSnapshot.length) {
					return true;
				}

				for (let i = 0; i < current.length; i++) {
					if (current[i][0] !== initialSnapshot[i][0] || current[i][1] !== initialSnapshot[i][1]) {
						return true;
					}
				}

				return false;
			};

			const updateSaveBar = () => {
				// Règle demandée : si la clé API est invalide => la bar ne doit pas s'afficher
				if (apiKeyInput.hasClass('is-invalid')) {
					bar.classList.remove('active');
					return;
				}

				if (apiKeyInput.val().trim() !== initialApiKey) {
					bar.classList.remove('active');
					return;
				}

				// Sinon, la bar n'est visible que si on a divergé de l'état initial
				if (isFormDirty(form)) {
					bar.classList.add('active');
				} else {
					bar.classList.remove('active');
				}
			};

			form.addEventListener('input', updateSaveBar);
			form.addEventListener('change', updateSaveBar);


			document.getElementById('cancel-save-settings').onclick = function(e) {
				e.preventDefault();
				form.reset();
				bar.classList.remove('active');
				if (errorMessage) {
					errorMessage.style.display = 'none';
				}

				initialSnapshot = snapshotForm(form);
				updateSaveBar();
			}

			document.getElementById('confirm-save-settings').onclick = function(e) {
				e.preventDefault();
				const apiKeyEl = document.getElementById('api-key');

				const saveNonce = window.weglotAdmin && window.weglotAdmin.nonces
					? window.weglotAdmin.nonces.save_settings_v2
					: '';

				const getCheckboxValue = (selector) => {
					const el = document.querySelector(selector);
					return (el && el.checked) ? 1 : 0;
				};

				const data = {
					action: 'weglot_save_settings_v2',
					security: saveNonce,

					api_key_private: apiKeyEl ? apiKeyEl.value.trim() : '',
					translate_search: getCheckboxValue('input[name="translate_search"]'),
					translate_amp: getCheckboxValue('input[name="translate_amp"]'),
					translate_email: getCheckboxValue('input[name="translate_email"]'),
				};

				$.ajax({
					method: 'POST',
					url: ajaxurl,
					data: data,

					success: (response) => {
						if (response.success) {
							bar.classList.remove('active');

							initialSnapshot = snapshotForm(form);
							showToast('Changes saved!', '', 'success')

						} else {
							const message = response && response.data && response.data.message ? response.data.message : '';
							showToast('Settings saved failed!', '', 'danger')

							if (message === 'Invalid API key.') {
								apiKeyInput.removeClass('is-valid').addClass('is-invalid');
								updateErrorMessage();
								bar.classList.remove('active');

								if (saveMessage) {
									saveMessage.classList.remove('active');
								}

								return;
							}

							// Fallback: autre erreur
							bar.classList.remove('active');
						}
					},
					error: () => {
						// Erreur réseau / serveur
						bar.classList.remove('active');
					}
				});
			}

			const updateErrorMessage = () => {
				if (apiKeyInput.hasClass('is-invalid')) {
					if (errorMessage) {
						errorMessage.style.display = 'block';
						bar.classList.remove('active');
					}
				} else {
					if (errorMessage) {
						errorMessage.style.display = 'none';
						bar.classList.add('active');
					}
				}
				updateSaveBar();
			};
			const handleApiKeyValidation = () => {
				const key = apiKeyInput.val().trim();

				// Condition initiale si la clé est trop courte (invalide)
				if (key.length < 10) {
					apiKeyInput.addClass('is-invalid');
					updateErrorMessage();
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
					success: ({ success, data }) => {
						if (success) {
							// Clé valide
							apiKeyInput.removeClass('is-invalid').addClass('is-valid');
							if (data && data.iframe_url) {
								const existing = document.getElementById('weglot-api-prefetch-iframe');
								if (existing) {
									existing.remove();
								}
								const iframe = document.createElement('iframe');
								iframe.id = 'weglot-api-prefetch-iframe';
								iframe.style.display = 'none';
								iframe.src = data.iframe_url;
								document.body.appendChild(iframe);
							}

							if (key !== initialApiKey) {
								location.reload();
								return;
							}
						} else {
							// Clé invalide (réponse de l'API)
							apiKeyInput.removeClass('is-valid').addClass('is-invalid');
						}
						updateErrorMessage();
					},
					error: () => {
						// Erreur AJAX
						apiKeyInput.removeClass('is-valid').addClass('is-invalid');
						bar.classList.remove('active');
						updateErrorMessage();
					}
				});
			};

			let typingTimer;
			apiKeyInput.on('keyup', () => {
				clearTimeout(typingTimer);
				typingTimer = setTimeout(handleApiKeyValidation, 250); // Délai réduit à 500ms
			});

			apiKeyInput.on('keydown', () => {
				clearTimeout(typingTimer);
			});

			apiKeyInput.on('blur', () => {
				updateSaveBar();
			});

		}

	});
};

export default init_v2_dashboard;
