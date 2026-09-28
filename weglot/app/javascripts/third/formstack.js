/**
 * Keeps the accessible name of Formstack fields aligned with their visible label.
 *
 * Formstack renders its forms client side, so the server side parser never sees them and
 * their labels are only translated by the JS engine. Two things then go out of sync:
 *
 *  - a field carries an `aria-label` that stays in the original language. `aria-label`
 *    outranks the native `<label>` in the accessible name computation, so screen readers
 *    announce the untranslated string while the visible label is translated (WCAG 2.5.3).
 *    Dropping the attribute lets the already translated `<label>` be used instead.
 *  - a field has no programmatic label at all, only a visual `.fsLabel`. It has no
 *    accessible name in any language, so the visible label is mirrored into `aria-label`.
 *
 * Both cases are handled in every language, including the original one: the second is a
 * gap that exists before any translation happens.
 */
(function () {
	"use strict";

	var DEFAULT_SELECTORS = [".fsBody", ".fsForm"];
	var CONTROL_SELECTOR =
		'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]), select, textarea';
	var MARKER = "data-wg-formstack-label";
	var DEBOUNCE = 75;
	var MAX_WRAPPER_DEPTH = 5;

	var config = window.weglotFormstack || {};
	var selectors =
		Array.isArray(config.selectors) && config.selectors.length ? config.selectors : DEFAULT_SELECTORS;
	var containerSelector = selectors.join(",");

	var observed = [];
	var syncTimer = null;
	var attachTimer = null;

	function normalize(value) {
		return value ? String(value).replace(/\s+/g, " ").trim() : "";
	}

	function firstText(nodes) {
		for (var i = 0; i < nodes.length; i++) {
			var text = normalize(nodes[i].textContent);

			if (text) {
				return text;
			}
		}

		return "";
	}

	function referencedText(control) {
		var ids = normalize(control.getAttribute("aria-labelledby")).split(" ");
		var nodes = [];

		for (var i = 0; i < ids.length; i++) {
			var node = ids[i] ? document.getElementById(ids[i]) : null;

			if (node) {
				nodes.push(node);
			}
		}

		return firstText(nodes);
	}

	function nativeLabel(control) {
		return control.labels ? firstText(Array.prototype.slice.call(control.labels)) : "";
	}

	/**
	 * Visible Formstack label of a field that has no programmatic label. The wrapper is
	 * only trusted when it holds a single control, otherwise a two column row would give
	 * both of its fields the same name.
	 */
	function visualLabel(control) {
		var node = control.parentElement;
		var depth = 0;

		while (node && depth < MAX_WRAPPER_DEPTH) {
			var label = node.querySelector(".fsLabel, legend");

			if (label) {
				return node.querySelectorAll(CONTROL_SELECTOR).length === 1 ? normalize(label.textContent) : "";
			}

			node = node.parentElement;
			depth++;
		}

		return "";
	}

	function syncControl(control) {
		// aria-labelledby wins over aria-label, so the accessible name already follows
		// whatever that element says and there is nothing to realign.
		if (referencedText(control)) {
			return;
		}

		var ariaLabel = control.getAttribute("aria-label");
		var label = nativeLabel(control);

		if (label) {
			if (null !== ariaLabel && normalize(ariaLabel) !== label) {
				control.removeAttribute("aria-label");
				control.setAttribute(MARKER, "removed");
			}

			return;
		}

		// Radio and checkbox names come from their own option label plus the surrounding
		// legend: naming them after the question would hide which option they are.
		if ("radio" === control.type || "checkbox" === control.type) {
			return;
		}

		var visible = visualLabel(control);

		if (!visible || normalize(ariaLabel) === visible) {
			return;
		}

		control.setAttribute("aria-label", visible);
		control.setAttribute(MARKER, "set");
	}

	function sync() {
		var containers = document.querySelectorAll(containerSelector);

		for (var i = 0; i < containers.length; i++) {
			var controls = containers[i].querySelectorAll(CONTROL_SELECTOR);

			for (var j = 0; j < controls.length; j++) {
				syncControl(controls[j]);
			}
		}
	}

	function scheduleSync() {
		window.clearTimeout(syncTimer);
		syncTimer = window.setTimeout(sync, DEBOUNCE);
	}

	function observe(container) {
		if (-1 !== observed.indexOf(container)) {
			return;
		}

		observed.push(container);

		new MutationObserver(scheduleSync).observe(container, {
			childList: true,
			subtree: true,
			characterData: true
		});

		scheduleSync();
	}

	function attach() {
		var containers = document.querySelectorAll(containerSelector);

		for (var i = 0; i < containers.length; i++) {
			observe(containers[i]);
		}
	}

	function scheduleAttach() {
		window.clearTimeout(attachTimer);
		attachTimer = window.setTimeout(attach, DEBOUNCE);
	}

	attach();

	// Formstack injects its container after page load, and conditional logic can add more
	// forms later on. Watching the body for new containers stays cheap: the per container
	// observers are the ones that also listen to text changes.
	new MutationObserver(scheduleAttach).observe(document.body, {
		childList: true,
		subtree: true
	});
})();
