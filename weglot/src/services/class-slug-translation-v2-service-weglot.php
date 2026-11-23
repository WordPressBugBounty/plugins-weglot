<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Helpers\Helper_API;

/**
 * Translated slugs of Weglot 2 projects.
 *
 * Weglot 1 slugs come from /translations/slugs and are merged into custom_urls by
 * Option_Service_Weglot; this service gives Weglot 2 the same [language => [translated => original]]
 * shape, so URL rewriting, links, the switcher and hreflang need no change.
 *
 * Slugs are cached per language in an option and served even when outdated: a single request
 * refreshes them under a lock, every other request keeps the previous slugs instead of waiting on
 * the API.
 *
 * @since 6.5
 *
 * @phpstan-type SlugEntry array{version: string|null, fetched_at: int, retry_after: int, slugs: array<string, string>, redirects: array<string, string>}
 * @phpstan-type SlugCache array{key: string, languages: array<string, SlugEntry>}
 */
class Slug_Translation_V2_Service_Weglot {
	const ROUTE             = 'slug-translations/per-slug';
	const CACHE_OPTION      = 'weglot_slugs_v2_cache';
	const LOCK_OPTION       = 'weglot_slugs_v2_lock';
	const LAST_ERROR_OPTION = 'weglot_slugs_v2_last_error';

	/**
	 * Refreshed at least daily: the API may not bump versions.slugTranslations when only a
	 * redirect changes.
	 */
	const MAX_AGE = 86400;

	/**
	 * Delay before a language whose fetch failed is requested again.
	 */
	const RETRY_DELAY = 300;

	/**
	 * A lock older than this is considered abandoned (the request holding it died).
	 */
	const LOCK_TIMEOUT = 30;

	/**
	 * Total time one request may spend fetching languages; the rest waits for the next request.
	 */
	const FETCH_BUDGET = 5;

	/**
	 * Cache entries resolved for the current request.
	 *
	 * @var array<string, SlugEntry>|null
	 */
	private $entries = null;

	/**
	 * @var bool
	 */
	private $is_loading = false;

	/**
	 * Not tied to the auto_slug_translation_enabled project setting: it only controls the
	 * automatic translation of new slugs, existing translations (manual ones, or imported from a
	 * Weglot 1 project, whose migration leaves the setting off) keep being served.
	 *
	 * @since 6.5
	 *
	 * @param mixed $api_key
	 *
	 * @return bool
	 */
	public function is_enabled( $api_key ) {
		$version_service = weglot_get_service( Version_Service_Weglot::class );
		if ( ! is_string( $api_key ) || ! $version_service instanceof Version_Service_Weglot || 2 !== $version_service->get_version_from_api_key_private( $api_key ) ) {
			return false;
		}

		/** This filter is documented in src/services/class-option-service-weglot.php */
		return (bool) apply_filters( 'weglot_active_slugs', true );
	}

	/**
	 * Translated slugs, in the shape custom_urls expects.
	 *
	 * @since 6.5
	 *
	 * @param array<string, mixed> $options Mapped project options.
	 * @param string $api_key Private API key of the project.
	 * @param string[] $languages Internal codes of the destination languages.
	 *
	 * @return array<string, array<string, string>> [language => [translated slug => original slug]]
	 */
	public function get_slugs( array $options, $api_key, array $languages ) {
		$slugs = array();

		foreach ( $this->get_entries( $options, $api_key, $languages ) as $language => $entry ) {
			$map = $this->reverse_slugs( $entry['slugs'] );
			if ( array() !== $map ) {
				$slugs[ $language ] = $map;
			}
		}

		return $slugs;
	}

	/**
	 * Former translated slugs still pointing to a current one, for the 301s of renamed slugs.
	 *
	 * Only read from what get_slugs() already loaded during the request: no API call.
	 *
	 * @since 6.5
	 *
	 * @param string $language Internal code of the language.
	 *
	 * @return array<string, string> [former translated slug => current translated slug]
	 */
	public function get_redirects( $language ) {
		if ( null === $this->entries || ! isset( $this->entries[ $language ] ) ) {
			return array();
		}

		return $this->entries[ $language ]['redirects'];
	}

	/**
	 * Mark every language as outdated, so the next request refreshes them while the current
	 * slugs keep being served. Called by the Weglot 2 cache purge webhook.
	 *
	 * Per language, not cache-wide: a language that keeps failing must not make the others be
	 * refetched on every request.
	 *
	 * @since 6.5
	 *
	 * @return void
	 */
	public function mark_stale() {
		$cache = get_option( self::CACHE_OPTION );
		if ( ! is_array( $cache ) || ! isset( $cache['languages'] ) || ! is_array( $cache['languages'] ) ) {
			return;
		}

		foreach ( array_keys( $cache['languages'] ) as $language ) {
			$cache['languages'][ $language ]['fetched_at']  = 0;
			$cache['languages'][ $language ]['retry_after'] = 0;
		}

		update_option( self::CACHE_OPTION, $cache, false );
		$this->entries = null;
	}

	/**
	 * Path to send a former translated slug to.
	 *
	 * Works on the request path as the visitor typed it, before Weglot\Util\Url maps translated
	 * slugs back to original ones: there, a former translated slug equal to another page's
	 * original slug ('contact' for /es/contacto) could not be told apart and the live page would
	 * get a permanent redirect.
	 *
	 * A former slug that is now the current slug of another page is not redirected: that page owns
	 * it. A target that is no longer a current slug is ignored, which also rules out loops.
	 *
	 * Known limitation: a page whose slug is the same in both languages (or untranslated) is not in
	 * custom_urls, so if that slug is also another page's former slug, the request is redirected.
	 *
	 * @since 6.5
	 *
	 * @param string $path Request path, translated slugs, no language segment.
	 * @param string $language Internal code of the language.
	 * @param array<int|string, mixed> $custom_urls [translated slug => original slug] of the language.
	 *
	 * @return string|null Null when nothing has to be redirected.
	 */
	public function find_redirect_path( $path, $language, array $custom_urls ) {
		$redirects = $this->get_redirects( $language );
		if ( array() === $redirects ) {
			return null;
		}

		$segments   = explode( '/', $path );
		$redirected = false;

		foreach ( $segments as $index => $segment ) {
			if ( isset( $redirects[ $segment ] ) && ! isset( $custom_urls[ $segment ] ) && isset( $custom_urls[ $redirects[ $segment ] ] ) ) {
				$segments[ $index ] = $redirects[ $segment ];
				$redirected         = true;
			}
		}

		return $redirected ? implode( '/', $segments ) : null;
	}

	/**
	 * @param array<string, mixed> $options
	 * @param string $api_key
	 * @param string[] $languages
	 *
	 * @return array<string, SlugEntry>
	 */
	private function get_entries( array $options, $api_key, array $languages ) {
		if ( null !== $this->entries ) {
			return $this->entries;
		}

		// A weglot_active_slugs callback, or Helper_API::get_api_domain() on a cold cache, can read
		// the options again: the nested call gets nothing and, unlike the outer one, is not memoised.
		if ( $this->is_loading ) {
			return array();
		}

		$this->is_loading = true;

		try {
			$this->entries = $this->is_enabled( $api_key )
				? $this->load( $this->read_version( $options ), $api_key, $languages )
				: array();
		} finally {
			$this->is_loading = false;
		}

		return $this->entries;
	}

	/**
	 * @param string|null $version
	 * @param string $api_key
	 * @param string[] $languages
	 *
	 * @return array<string, SlugEntry>
	 */
	private function load( $version, $api_key, array $languages ) {
		$cache = $this->read_cache( $api_key );
		$now   = time();

		if ( array() !== $this->outdated_languages( $cache, $languages, $version, $now ) && $this->acquire_lock() ) {
			try {
				// Read again under the lock: the request that held it may have just refreshed.
				$cache    = $this->read_cache( $api_key, true );
				$outdated = $this->outdated_languages( $cache, $languages, $version, $now );
				if ( array() !== $outdated ) {
					$cache = $this->refresh( $cache, $outdated, $version, $api_key, $now );
					update_option( self::CACHE_OPTION, $cache, false );
				}
			} finally {
				delete_option( self::LOCK_OPTION );
			}
		}

		$entries = array();
		foreach ( $languages as $language ) {
			if ( isset( $cache['languages'][ $language ] ) ) {
				$entries[ $language ] = $cache['languages'][ $language ];
			}
		}

		return $entries;
	}

	/**
	 * A cache built for another key (key rotation, WEGLOT_API_KEY_PRIVATE, another project) is
	 * thrown away rather than served: its slugs belong to another project.
	 *
	 * @param string $api_key
	 * @param bool $bypass_cache Read the database: the options cache would not see an update made
	 *                           by the request that held the lock.
	 *
	 * @return SlugCache
	 */
	private function read_cache( $api_key, $bypass_cache = false ) {
		$fingerprint = hash( 'sha256', $api_key );

		if ( $bypass_cache ) {
			wp_cache_delete( self::CACHE_OPTION, 'options' );
			$not_options = wp_cache_get( 'notoptions', 'options' );
			if ( is_array( $not_options ) && isset( $not_options[ self::CACHE_OPTION ] ) ) {
				unset( $not_options[ self::CACHE_OPTION ] );
				wp_cache_set( 'notoptions', $not_options, 'options' );
			}
		}

		$cache = get_option( self::CACHE_OPTION );

		if ( ! is_array( $cache ) || ! isset( $cache['key'], $cache['languages'] ) || $fingerprint !== $cache['key'] || ! is_array( $cache['languages'] ) ) {
			return array(
				'key'       => $fingerprint,
				'languages' => array(),
			);
		}

		return array(
			'key'       => $fingerprint,
			'languages' => $cache['languages'],
		);
	}

	/**
	 * @param SlugCache $cache
	 * @param string[] $languages
	 * @param string|null $version
	 * @param int $now
	 *
	 * @return string[]
	 */
	private function outdated_languages( array $cache, array $languages, $version, $now ) {
		$outdated = array();
		foreach ( $languages as $language ) {
			if ( $this->is_stale( $cache, $language, $version, $now ) ) {
				$outdated[] = $language;
			}
		}

		return $outdated;
	}

	/**
	 * @param SlugCache $cache
	 * @param string $language
	 * @param string|null $version
	 * @param int $now
	 *
	 * @return bool
	 */
	private function is_stale( array $cache, $language, $version, $now ) {
		if ( ! isset( $cache['languages'][ $language ] ) ) {
			return true;
		}

		$entry = $cache['languages'][ $language ];

		if ( $entry['retry_after'] > $now ) {
			return false;
		}

		return $entry['version'] !== $version
			|| $now - $entry['fetched_at'] > self::MAX_AGE;
	}

	/**
	 * @param SlugCache $cache
	 * @param string[] $languages
	 * @param string|null $version
	 * @param string $api_key
	 * @param int $now
	 *
	 * @return SlugCache
	 */
	private function refresh( array $cache, array $languages, $version, $api_key, $now ) {
		$api_domain = Helper_API::get_api_domain( $api_key );

		// get_api_domain() falls back on the dashboard host when the API host is unknown: there is
		// no slug route there. Every language waits before the next attempt, or each page view
		// would ask get_api_domain() again.
		if ( in_array( $api_domain, array( Helper_API::DASHBOARD_BASE_V2, Helper_API::DASHBOARD_BASE_V2_STAGING ), true ) ) {
			$this->record_error( '*', 'API host unknown' );
			foreach ( $languages as $language ) {
				$cache = $this->postpone( $cache, $language, $now );
			}
			return $cache;
		}

		$started = microtime( true );
		$timeout = (float) apply_filters( 'custom_http_request_timeout', 3 );

		foreach ( $languages as $language ) {
			$remaining = self::FETCH_BUDGET - ( microtime( true ) - $started );
			if ( $remaining <= 0 ) {
				break;
			}

			$fetched = $this->fetch( $api_domain, $api_key, $language, $version, min( $timeout, $remaining ) );

			if ( null === $fetched ) {
				$cache = $this->postpone( $cache, $language, $now );
				continue;
			}

			$cache['languages'][ $language ] = array(
				'version'     => $version,
				'fetched_at'  => $now,
				'retry_after' => 0,
				'slugs'       => $fetched['slugs'],
				'redirects'   => $fetched['redirects'],
			);
		}

		return $cache;
	}

	/**
	 * A language that could not be fetched keeps its previous slugs and waits RETRY_DELAY.
	 *
	 * @param SlugCache $cache
	 * @param string $language
	 * @param int $now
	 *
	 * @return SlugCache
	 */
	private function postpone( array $cache, $language, $now ) {
		$entry = isset( $cache['languages'][ $language ] ) ? $cache['languages'][ $language ] : array(
			'version'    => null,
			'fetched_at' => 0,
			'slugs'      => array(),
			'redirects'  => array(),
		);

		$entry['retry_after']            = $now + self::RETRY_DELAY;
		$cache['languages'][ $language ] = $entry;

		return $cache;
	}

	/**
	 * @param string $api_domain
	 * @param string $api_key
	 * @param string $language Internal code of the language.
	 * @param string|null $version
	 * @param float $timeout
	 *
	 * @return array{slugs: array<string, string>, redirects: array<string, string>}|null Null on failure.
	 */
	private function fetch( $api_domain, $api_key, $language, $version, $timeout ) {
		$query = array( 'language' => rawurlencode( $language ) );
		// Optional for the API: it lets an edge cache key the answer on the slug version. The
		// MAX_AGE and mark_stale() refreshes send the same v, so that cache must be invalidated
		// whenever the API changes a slug or a redirect without bumping the version.
		if ( null !== $version ) {
			$query['v'] = rawurlencode( $version );
		}

		$response = wp_remote_get( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- one call per language and per day at most, the result is cached in an option.
			add_query_arg( $query, $api_domain . '/' . self::ROUTE ),
			array(
				'timeout' => $timeout,
				'headers' => array( 'Authorization' => 'Key ' . $api_key ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->record_error( $language, $response->get_error_message() );
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $body ) ) {
			$this->record_error( $language, sprintf( 'HTTP %s', $status ) );
			return null;
		}

		return array(
			'slugs'     => $this->sanitize_map( isset( $body['slugs'] ) ? $body['slugs'] : array() ),
			'redirects' => $this->sanitize_map( isset( $body['redirects'] ) ? $body['redirects'] : array() ),
		);
	}

	/**
	 * Keep string => string pairs that can stand for one path segment.
	 *
	 * @param mixed $map
	 *
	 * @return array<string, string>
	 */
	private function sanitize_map( $map ) {
		if ( ! is_array( $map ) ) {
			return array();
		}

		$clean = array();
		foreach ( $map as $from => $to ) {
			$from = (string) $from;
			if ( ! is_string( $to ) || '' === $from || '' === $to || $from === $to || false !== strpos( $from . $to, '/' ) ) {
				continue;
			}
			$clean[ $from ] = $to;
		}

		return $clean;
	}

	/**
	 * Original => translated becomes translated => original. A translated slug shared by two pages,
	 * or equal to the original slug of another page, cannot be resolved: it is left out.
	 *
	 * @param array<string, string> $slugs [original slug => translated slug]
	 *
	 * @return array<string, string>
	 */
	private function reverse_slugs( array $slugs ) {
		$counts   = array_count_values( $slugs );
		$reversed = array();

		foreach ( $slugs as $original => $translated ) {
			if ( $counts[ $translated ] > 1 || isset( $slugs[ $translated ] ) ) {
				continue;
			}
			$reversed[ $translated ] = (string) $original;
		}

		return $reversed;
	}

	/**
	 * @param array<string, mixed> $options
	 *
	 * @return string|null
	 */
	private function read_version( array $options ) {
		// Weglot 2 names the field in the plural (versions.slugTranslations); the singular
		// versions.slugTranslation belongs to the Weglot 1 CDN.
		if ( ! isset( $options['versions'] ) || ! is_array( $options['versions'] ) || ! isset( $options['versions']['slugTranslations'] ) ) {
			return null;
		}

		$version = $options['versions']['slugTranslations'];

		return is_int( $version ) || is_string( $version ) ? (string) $version : null;
	}

	/**
	 * Same pattern as WP_Upgrader::create_lock(), not loaded on the front: add_option() would not
	 * do, it runs an INSERT ... ON DUPLICATE KEY UPDATE that two requests can both win.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic lock, the options API has no insert-if-absent.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' )", self::LOCK_OPTION, (string) $now ) );

		if ( $inserted ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read past the options cache, which does not know about the INSERT above.
		$locked_at = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s", self::LOCK_OPTION ) );

		if ( $locked_at > $now - self::LOCK_TIMEOUT ) {
			return false;
		}

		// Abandoned lock: dropped, then taken with the same atomic INSERT, so two requests that both
		// saw it expired cannot both win.
		delete_option( self::LOCK_OPTION );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- see above.
		return (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' )", self::LOCK_OPTION, (string) $now ) );
	}

	/**
	 * @param string $language
	 * @param string $error
	 *
	 * @return void
	 */
	private function record_error( $language, $error ) {
		update_option(
			self::LAST_ERROR_OPTION,
			array(
				'language' => $language,
				'error'    => $error,
				'time'     => time(),
			),
			false
		);
	}
}
