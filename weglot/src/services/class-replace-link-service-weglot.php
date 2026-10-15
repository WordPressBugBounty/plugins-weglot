<?php

namespace WeglotWP\Services;

use Weglot\Client\Api\LanguageEntry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * @since 2.0
 */
class Replace_Link_Service_Weglot {

	/**
	 * @var Multisite_Service_Weglot
	 */
	private $multisite_service;
	/**
	 * @var Request_Url_Service_Weglot
	 */
	private $request_url_services;

	/**
	 * @var array<int|string,mixed>|null
	 */
	private $multisite_other_paths;

	/**
	 * @since 2.0
	 */
	public function __construct() {
		$this->multisite_service     = weglot_get_service( Multisite_Service_Weglot::class );
		$this->request_url_services  = weglot_get_service( Request_Url_Service_Weglot::class );
		$this->multisite_other_paths = null;
		if ( is_multisite() ) {
			$this->multisite_other_paths = array_filter(
				$this->multisite_service->get_list_of_network_path(),
				function ( $elem ) {
					return $elem !== $this->request_url_services->get_home_wordpress_directory() . '/';
				}
			);
		}
	}

	/**
	 * Replace an original URL into the current language
	 *
	 * @param string $url
	 * @param LanguageEntry $language
	 * @param Bool $evenExcluded
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_url($url, $language, $evenExcluded = true) {
		$replaced_url = apply_filters('weglot_replace_url', $this->request_url_services->create_url_object($url)->getForLanguage($language, $evenExcluded), $url, $language);

		// Parse the URL to separate the path, query, and fragment
		$parsed_url = wp_parse_url($replaced_url);

		if (!empty($parsed_url['path'])) {
			// Check if the path ends without a trailing slash

			$should_add_trailing_slash = apply_filters( 'weglot_add_trailing_slash', true );
			if ( $should_add_trailing_slash && substr( $parsed_url['path'], -1 ) !== '/' ) {
				$parsed_url['path'] .= '/';
			}
		}

		// Rebuild the URL with all parts (path, query, fragment, etc.)
		$replaced_url = '';
		if (isset($parsed_url['scheme']) && isset($parsed_url['host'])) {
			$replaced_url .= $parsed_url['scheme'] . '://' . $parsed_url['host'];
		}

		if (!empty($parsed_url['path'])) {
			$replaced_url .= $parsed_url['path'];
		}

		if (!empty($parsed_url['query'])) {
			$replaced_url .= '?' . $parsed_url['query'];
		}

		if (!empty($parsed_url['fragment'])) {
			$replaced_url .= '#' . $parsed_url['fragment'];
		}

		return $replaced_url;
	}

	/**
	 * Escape a rewritten URL before it is injected into an HTML attribute through a preg_replace()
	 * replacement string.
	 *
	 * The parser urldecodes the path of every internal link, so a percent-encoded value stored in a
	 * comment "Website" field (e.g. `/%22%3E%3Cimg ... onerror=...%3E`) comes back decoded. esc_url()
	 * strips the characters that would break out of the attribute (`<`, `>`, `"`). The extra
	 * backslash/dollar escaping is required on top because the value lands in the *replacement* of a
	 * preg_replace() call, where `\1` and `$1` would otherwise expand as backreferences — and esc_url()
	 * keeps both `\` and `$` in its whitelist while the path is attacker-controlled.
	 *
	 * @param string $url
	 * @return string
	 */
	private function esc_replacement( $url ) {
		return $this->esc_preg_replacement( esc_url( $url ) );
	}

	/**
	 * Escape a value used as a preg_replace() replacement so `\1` / `$1` are treated literally
	 * instead of expanding as backreferences. Use this directly when esc_url() cannot run on the
	 * value (e.g. the multisite `wg-` marker produces a deliberately invalid scheme that esc_url()
	 * would strip to an empty string); escape the URL part with esc_url() before building the marker.
	 *
	 * @param string $value
	 * @return string
	 */
	private function esc_preg_replacement( $value ) {
		return str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $value );
	}

	/**
	 * Replace href in <a>
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @version 2.0.4
	 * @since 2.0
	 */
	public function replace_a( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language     = $this->request_url_services->get_current_language();
		$no_replace_condition = apply_filters( 'weglot_no_replace_a_href', 'wp-content/uploads' );

		if ( strpos( $current_url, $no_replace_condition ) !== false ) {
			return $translated_page;
		}
		$replace_multisite_link = apply_filters( 'replace_multisite_link', false );
		$replace_url_other_site = '';
		if ( $replace_multisite_link ) {
			$parsed_url         = wp_parse_url( $current_url );
			if ( isset( $parsed_url['path'] ) ) {
				$current_home_path    = wp_parse_url( get_home_url( get_current_blog_id(), '/' ), PHP_URL_PATH );
				$found_dynamic_string = false;
				$index_to_remove      = array_search( "/", $this->multisite_other_paths );
				if ( $index_to_remove !== false ) {
					unset( $this->multisite_other_paths[ $index_to_remove ] );
				}
				foreach ( $this->multisite_other_paths as $dynamic_string ) {
					if ( strpos( $this->replace_url( $current_url, $current_language ), trim( $dynamic_string, '/' ) ) !== false && $dynamic_string != '/' ) {
						$found_dynamic_string   = true;
						$replace_url_other_site = str_replace( trim( $dynamic_string, '/' ), "", $this->replace_url( $current_url, $current_language ) );
						if(is_string($current_home_path)){
							$replace_url_other_site = str_replace( $current_home_path, $dynamic_string, $replace_url_other_site );
						}
						break; // Exit the loop once a match is found
					}
				}

				// If no dynamic string is found, keep the original path
				if ( ! $found_dynamic_string ) {
					$replace_url_other_site = $this->replace_url( $current_url, $current_language );
				}

				$paths      = explode( '/', $parsed_url['path'] );
				$other_site = in_array( '/' . $paths[1] . '/', $this->multisite_other_paths );
				if ( $other_site ) {
					$replace_url_other_site = preg_replace( '#(?<!https:)\/\/+#', '/', 'wg-' . esc_url( $replace_url_other_site ) );
					$translated_page        = preg_replace( '/<a' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . preg_quote( $sometags2, '/' ) . '>/', 'a' . $sometags . 'href=' . $quote1 . $this->esc_preg_replacement( $replace_url_other_site ) . $quote2 . $sometags2 . '>', $translated_page );
				} else {
					$translated_page = preg_replace( '/<a' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . preg_quote( $sometags2, '/' ) . '>/', '<a' . $sometags . 'href=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2 . $sometags2 . '>', $translated_page );
				}

			}

		} else {
			$translated_page = preg_replace( '/<a' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . preg_quote( $sometags2, '/' ) . '>/', '<a' . $sometags . 'href=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2 . $sometags2 . '>', $translated_page );
		}

		return $translated_page;
	}

	/**
	 * Replace data-link attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_datalink( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$regex_limit_default = 200000;

		// Check if the filter exists.
		if ( has_filter( 'weglot_regex_tags_limit' ) ) {
			// Use the filtered limit.
			$regex_limit = apply_filters( 'weglot_regex_tags_limit', $regex_limit_default );

			// Check if $sometags exceeds the limit.
			if ( strlen( $sometags ) > $regex_limit ) {
				// Skip the preg_replace; leave $translated_page as is.
			} else {
				$translated_page = preg_replace(
					'/<' . preg_quote( $sometags, '/' ) . 'data-link=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/',
					'<' . $sometags . 'data-link=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2,
					$translated_page
				);
			}
		} else {
			// No filter exists; use the default behavior.
			$translated_page = preg_replace(
				'/<' . preg_quote( $sometags, '/' ) . 'data-link=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/',
				'<' . $sometags . 'data-link=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2,
				$translated_page
			);
		}
		return $translated_page;
	}

	/**
	 * Replace data-url attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_dataurl( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<' . preg_quote( $sometags, '/' ) . 'data-url=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<' . $sometags . 'data-url=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace data-cart-url attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_datacart( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<' . preg_quote( $sometags, '/' ) . 'data-cart-url=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<' . $sometags . 'data-cart-url=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace form action attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_form( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<form' . preg_quote( $sometags, '/' ) . 'action=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<form ' . $sometags . 'action=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language, false ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace canonical attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_canonical( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<link rel="canonical"' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<link rel="canonical"' . $sometags . 'href=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace link next attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_next( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<link rel="next"' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<link rel="next"' . $sometags . 'href=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace link prev attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_prev( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<link rel="prev"' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<link rel="prev"' . $sometags . 'href=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace amphtml attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_amp( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<link rel="amphtml"' . preg_quote( $sometags, '/' ) . 'href=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<link rel="amphtml"' . $sometags . 'href=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace meta og url attribute
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 * @param string $quote1
	 * @param string $quote2
	 * @param string $sometags
	 * @param string $sometags2
	 *
	 * @return string
	 * @since 2.0
	 */
	public function replace_meta( $translated_page, $current_url, $quote1, $quote2, $sometags = null, $sometags2 = null ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<meta property="og:url"' . preg_quote( $sometags, '/' ) . 'content=' . preg_quote( $quote1 . $current_url . $quote2, '/' ) . '/', '<meta property="og:url"' . $sometags . 'content=' . $quote1 . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . $quote2, $translated_page );

		return $translated_page;
	}

	/**
	 * Replace loc in sitemap
	 *
	 * @param string $translated_page
	 * @param string $current_url
	 *
	 * @return string
	 * @version 2.0.4
	 * @since 2.0
	 */
	public function replace_loc( $translated_page, $current_url ) {
		$current_language = $this->request_url_services->get_current_language();
		$translated_page  = preg_replace( '/<loc>' . preg_quote( $current_url, '/' ) . '<\/loc>/', '<loc>' . $this->esc_replacement( $this->replace_url( $current_url, $current_language ) ) . '</loc>', $translated_page );

		return $translated_page;
	}
}
