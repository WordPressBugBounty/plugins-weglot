/**
 * A string translated server side (weglotAdmin.i18n, see Admin_Enqueue_Weglot), or the English
 * fallback when the page did not localize it.
 *
 * The result is plain text: write it with .text() / textContent, never as HTML.
 */
const t = (key, fallback) => {
	const i18n = window.weglotAdmin && window.weglotAdmin.i18n;

	return i18n && typeof i18n[key] === 'string' && i18n[key] !== '' ? i18n[key] : fallback;
};

export default t;
