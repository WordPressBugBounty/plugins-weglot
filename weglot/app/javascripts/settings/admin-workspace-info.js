
const init_workspace_info = function () {
	document.addEventListener('DOMContentLoaded', () => {
		const $ = jQuery;
		const hasSidebar = $('#wrapper-plan').length > 0;
		const hasTotalWords = $('#total-words-count').length > 0;

		// Si aucun des éléments n'existe, on arrête
		if (!hasSidebar && !hasTotalWords) {
			return;
		}


		function syncContainerHeight() {
			const mainLayout = document.querySelector('.main-layout');
			const sidebar = document.querySelector('.sidebar');
			const container = document.querySelector('.container');
			const CONTAINER_HEIGHT_OFFSET = 100;
			// Vérifie que mainLayout existe et qu'il n'a pas la classe 'dashboard'
			if (mainLayout && !mainLayout.classList.contains('dashboard') && sidebar && container) {
				container.style.height = sidebar.offsetHeight -CONTAINER_HEIGHT_OFFSET + 'px';
			}
		}

		syncContainerHeight();
		window.addEventListener('resize', syncContainerHeight);

		const $skeleton = $('#plan-card-skeleton');
		const $planCard = $('.plan-card').not('.plan-card--skeleton');

		const loadWorkspaceInfo = () => {
			// Afficher le skeleton si on est sur la sidebar
			if (hasSidebar) {
				$skeleton.show().attr('aria-busy', 'true');
				$planCard.hide();
			}

			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					action: 'get_workspace_info',
					security: weglotAdmin.nonces.get_workspace_info,
				},
				success: ({ success, data }) => {
					if (success && data) {
						const workspaceData = data.data || {};
						const meta = data.meta || {};

						// Mettre à jour la carte du plan (sidebar)
						if (hasSidebar) {
							updatePlanCard(workspaceData);
						}

						// Mettre à jour le total de mots (home)
						if (hasTotalWords) {
							updateTotalWords(workspaceData, meta);
						}
					} else {
						console.error('Failed to retrieve workspace info');
					}
				},
				error: () => {
					console.error('AJAX error while loading workspace info');
				},
				complete: () => {
					// Masquer le skeleton et afficher la carte
					if (hasSidebar) {
						$skeleton.fadeOut(300, () => {
							$planCard.fadeIn(300);
						});
					}
				}
			});
		};

		const updatePlanCard = (data) => {
			const subscription = data.subscription || {};
			const usage = data.usage || {};
			const plan = subscription.plan || 'free';
			const wordCount = usage.wordCount || 0;
			const wordLimit = subscription.wordLimit || 0;

			// Formater le nom du plan (première lettre en majuscule)
			const planName = 'Plan ' + plan.charAt(0).toUpperCase() + plan.slice(1);

			// Mettre à jour le titre du plan
			$planCard.find('.panel-title').text(planName);

			// Mettre à jour les mots utilisés
			$planCard.find('.info-word-used span').text(
				formatNumber(wordCount) + ' / ' + formatNumber(wordLimit)
			);

			// Calculer le pourcentage
			const percentage = wordLimit > 0 ? (wordCount / wordLimit) * 100 : 0;

			// Ajouter la classe d'avertissement si >= 80%
			if (percentage >= 80) {
				$planCard.addClass('plan-card-warning');
			} else {
				$planCard.removeClass('plan-card-warning');
			}

			// Animer la barre de progression de 0% vers le pourcentage réel
			animateProgressBar($planCard.find('.progress-bar-fill'), percentage, 800);
		};

		const updateTotalWords = (data, meta) => {
			const usage = data.usage || {};
			const apiWordCount = usage.word_count || 0;

			const previous = typeof meta.previous_word_count === 'number' ? meta.previous_word_count : null;
			const current = typeof meta.current_word_count === 'number' ? meta.current_word_count : apiWordCount;
			const hasChanged = !!meta.has_changed;

			const $counter = $('#total-words-count');

			// Si pas de changement => pas d'animation, on affiche juste
			if (!hasChanged && previous !== null) {
				$counter.text(formatNumber(current));
				return;
			}

			// Sinon on anime : première fois => 0, sinon previous => current
			const start = previous === null ? 0 : previous;
			animateCounter($counter, start, current, 1000);
		};

		const animateProgressBar = ($element, targetPercentage, duration) => {
			const startTime = Date.now();

			const updateProgress = () => {
				const elapsed = Date.now() - startTime;
				const progress = Math.min(elapsed / duration, 1);

				// Utiliser une fonction d'easing pour un effet plus fluide
				const easeOutQuad = progress * (2 - progress);
				const currentPercentage = targetPercentage * easeOutQuad;

				$element.css('width', currentPercentage + '%');

				if (progress < 1) {
					requestAnimationFrame(updateProgress);
				} else {
					// S'assurer que la valeur finale est exacte
					$element.css('width', targetPercentage + '%');
				}
			};

			requestAnimationFrame(updateProgress);
		};

		const animateCounter = ($element, start, end, duration) => {
			const startTime = Date.now();
			const range = end - start;

			const updateCounter = () => {
				const elapsed = Date.now() - startTime;
				const progress = Math.min(elapsed / duration, 1);

				// Utiliser une fonction d'easing pour un effet plus fluide
				const easeOutQuad = progress * (2 - progress);
				const current = Math.floor(start + (range * easeOutQuad));

				$element.text(formatNumber(current));

				if (progress < 1) {
					requestAnimationFrame(updateCounter);
				} else {
					// S'assurer que la valeur finale est exacte
					$element.text(formatNumber(end));
				}
			};

			requestAnimationFrame(updateCounter);
		};

		const formatNumber = (num) => {
			return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		};

		// Charger les informations au démarrage
		loadWorkspaceInfo();
	});
};

export default init_workspace_info;
