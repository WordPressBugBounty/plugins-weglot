/**
 * Reverse-translate the query typed into the Doofinder search layer.
 *
 * The layer searches over a WebSocket, and exposes no hook running before a request is sent,
 * so the term is intercepted on the input itself: we write the original-language term, let the
 * layer read it, and restore what the visitor typed within the same tick. The browser repaints
 * only once the current task is done, so the substitution stays invisible.
 */

// The layer mounts its searchbox under an id carrying a random suffix, hence the prefix/suffix
// match. dfd-hook is a hook point Doofinder declares itself, so it is matched too: it does not
// depend on the id scheme. bind() is idempotent, so an input matched by both is bound once.
const INPUT_SELECTOR =
	'[id^="dfd-searchbox-id-"][id$="-input"], [dfd-hook="Input"]';
const DEBOUNCE_MS = 300;
const CACHE_PREFIX = "weglot_df_";
const LOG_PREFIX = "[weglot-doofinder]";

const nativeValueSetter = Object.getOwnPropertyDescriptor(
	HTMLInputElement.prototype,
	"value"
).set;

let selfDispatch = false;
let lastReversed = null;
let debounceId = null;
// Two reverse translations can be in flight when the visitor pauses, then types again: only the
// latest one may reach the layer, or a slower stale answer would replace the newer search.
let searchGeneration = 0;

function config() {
	return window.weglotDoofinder || null;
}

function log(...args) {
	const settings = config();

	if (settings === null || !settings.debug) {
		return;
	}

	console.log(LOG_PREFIX, ...args);
}

function cacheKey(term) {
	return `${CACHE_PREFIX}${config().language}_${term}`;
}

function readCache(term) {
	try {
		return window.sessionStorage.getItem(cacheKey(term));
	} catch (e) {
		return null;
	}
}

function writeCache(term, reversed) {
	try {
		window.sessionStorage.setItem(cacheKey(term), reversed);
	} catch (e) {
		// Private browsing or an exhausted quota must not break the search.
	}
}

function reverseTranslate(term) {
	const cached = readCache(term);

	if (cached !== null) {
		log("cache hit", { term, reversed: cached });

		return Promise.resolve(cached);
	}

	log("requesting reverse translation", {
		term,
		lang: config().language,
		endpoint: config().endpoint,
	});

	return fetch(config().endpoint, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-WP-Nonce": config().nonce,
		},
		body: JSON.stringify({ q: term, lang: config().language }),
	})
		.then((response) => {
			log("endpoint responded", response.status);

			return response.ok ? response.json() : null;
		})
		.then((data) => {
			const reversed =
				data && typeof data.q === "string" && data.q !== "" ? data.q : term;

			if (reversed === term) {
				log("term unchanged by the endpoint", { term, data });
			}

			writeCache(term, reversed);

			return reversed;
		})
		.catch((error) => {
			log("endpoint call failed", error);

			return term;
		});
}

function search(input, term) {
	const generation = ++searchGeneration;

	reverseTranslate(term).then((reversed) => {
		if (generation !== searchGeneration) {
			log("discarding a stale reverse translation", { term, reversed });

			return;
		}

		if (reversed === term) {
			log("nothing to substitute, leaving the layer alone", term);

			return;
		}

		// Restore what the input holds now, not the trimmed term captured before the round trip.
		const typed = input.value;

		lastReversed = reversed;
		selfDispatch = true;
		nativeValueSetter.call(input, reversed);
		input.dispatchEvent(new Event("input", { bubbles: true }));
		log("dispatched to the layer", { typed, sent: reversed });
		nativeValueSetter.call(input, typed);
		selfDispatch = false;
		log("restored the typed term, input now reads", input.value);
	});
}

function onInput(input) {
	if (selfDispatch) {
		log("ignoring our own dispatch");

		return;
	}

	// Any keystroke makes an in-flight reverse translation stale, even before the debounce fires.
	searchGeneration++;

	const term = input.value.trim();

	window.clearTimeout(debounceId);

	// The layer may echo our own substitution back into the input; translating it again would
	// be a pointless round trip.
	if (term === "" || term === lastReversed) {
		log("skipping term", { term, lastReversed });

		return;
	}

	log("input event, debouncing", term);
	debounceId = window.setTimeout(() => search(input, term), DEBOUNCE_MS);
}

function bind(input) {
	if (input.dataset.weglotDoofinderBound === "1") {
		return;
	}

	input.dataset.weglotDoofinderBound = "1";
	input.addEventListener("input", () => onInput(input));
	log("bound input", input.id);
}

function bindAll(event) {
	if (config() === null) {
		return;
	}

	const inputs = document.querySelectorAll(INPUT_SELECTOR);

	log("bindAll", {
		trigger: event && event.type ? event.type : "manual",
		selector: INPUT_SELECTOR,
		found: inputs.length,
	});

	inputs.forEach(bind);
}

// The searchbox may be rendered by the layer itself, hence both entry points; bind() is
// idempotent so binding twice is harmless.
document.addEventListener("doofinder.layer.load", bindAll);
document.addEventListener("DOMContentLoaded", bindAll);

log("script loaded", config());

if (config() !== null && config().debug) {
	window.weglotDoofinderDebug = { bindAll, reverseTranslate, INPUT_SELECTOR };
}
