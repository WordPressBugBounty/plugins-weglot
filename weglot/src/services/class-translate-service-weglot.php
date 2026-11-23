<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Weglot\Client\Api\Exception\ApiError;
use Weglot\Client\Api\LanguageEntry;
use WeglotWP\Helpers\Helper_Json_Inline_Weglot;
use WeglotWP\Helpers\Helper_API;


/**
 * @since 2.3.0
 */
class Translate_Service_Weglot {

	/**
	 * Route of the Weglot 2 reverse translate endpoint, which is not covered by the public
	 * API reference.
	 */
	const REVERSE_TRANSLATE_V2_ROUTE = 'reverse-translate';

	/**
	 * @var Parser_Service_Weglot
	 */
	private $parser_services;
	/**
	 * @var string
	 */
	private $current_language;
	/**
	 * @var string
	 */
	private $original_language;
	/**
	 * @var Request_Url_Service_Weglot
	 */
	private $request_url_services;
	/**
	 * @var Language_Service_Weglot
	 */
	private $language_services;
	/**
	 * @var Replace_Url_Service_Weglot
	 */
	private $replace_url_services;
	/**
	 * @var Option_Service_Weglot
	 */
	private $option_services;
	/**
	 * @var Generate_Switcher_Service_Weglot
	 */
	private $generate_switcher_service;
	/**
	 * @var Version_Service_Weglot
	 */
	private $version_services;


	/**
	 * @since 2.3.0
	 */
	public function __construct() {
		$this->option_services           = weglot_get_service( Option_Service_Weglot::class );
		$this->request_url_services      = weglot_get_service( Request_Url_Service_Weglot::class );
		$this->replace_url_services      = weglot_get_service( Replace_Url_Service_Weglot::class );
		$this->parser_services           = weglot_get_service( Parser_Service_Weglot::class );
		$this->generate_switcher_service = weglot_get_service( Generate_Switcher_Service_Weglot::class );
		$this->language_services         = weglot_get_service( Language_Service_Weglot::class );
		$this->version_services          = weglot_get_service( Version_Service_Weglot::class );
	}


	/**
	 * @return void
	 * @since 2.3.0
	 */
	public function weglot_translate() {
		$is_wp_engine = apply_filters( 'weglot_is_wp_engine_hosting', false );

		if ( $is_wp_engine ) {
			add_filter( 'final_output', array( $this, 'weglot_treat_page' ), 999 );
		} else {
			ob_start( array( $this, 'weglot_treat_page' ) );
		}
	}

	/**
	 * @param LanguageEntry $current_language
	 *
	 * @return Translate_Service_Weglot
	 * @since 2.3.0
	 */
	public function set_current_language( $current_language ) {
		$this->current_language = $current_language->getInternalCode();

		return $this;
	}

	/**
	 * @param LanguageEntry $original_language
	 *
	 * @return Translate_Service_Weglot
	 * @since 2.3.0
	 */
	public function set_original_language( $original_language ) {
		$this->original_language = $original_language->getInternalCode();

		return $this;
	}

	/**
	 * @param string $content
	 *
	 * @return string
	 */
	public function get_canonical_url_from_content( $content ) {
		$check_canonical = preg_match( '/<link rel="canonical"(.*?)?href=(\"|\')([^\s\>]+?)(\"|\')/', $content, $matches );

		if ( 1 === $check_canonical ) {
			if ( isset( $matches[3] ) && ! empty( $matches[3] ) ) {
				return $matches[3];
			} else {
				return '';
			}
		} else {
			return '';
		}
	}

	/**
	 * @return boolean
	 */
	public function check_ajax_exclusion_before_treat() { //phpcs:ignore
		if ( wp_doing_ajax() && is_string( wp_get_referer() ) ) {
			if ( ! $this->request_url_services->create_url_object( wp_get_referer() )->getForLanguage( $this->request_url_services->get_current_language(), false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return boolean
	 */
	public function check_404_exclusion_before_treat() { //phpcs:ignore
		if ( http_response_code() == 404 ) {
			$excluded_urls = $this->option_services->get_exclude_urls();
			foreach ( $excluded_urls as $item ) {
				if ( '^/404$' === $item[0] ) {
					return true;
				}
			}

			return false;
		} else {
			return false;
		}
	}

	/**
	 * @param string $content
	 *
	 * @return string
	 * @throws \Exception
	 * @since 2.3.0
	 * @see weglot_init / ob_start
	 */
	public function weglot_treat_page( $content ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}

		$active_translation = apply_filters( 'weglot_active_translation', true );
		$original_language = $this->language_services->get_original_language();
		if ( empty( $content ) || ! $active_translation || null === $original_language ) {
			return $content;
		}
		$this->set_original_language( $original_language );
		$this->set_current_language( $this->request_url_services->get_current_language() ); // Need to reset.

		$content = apply_filters(
			'weglot_modify_original_content',
			$content,
			$this->current_language,
			$this->original_language
		);

		// Choose type translate.
		$type = ( Helper_Json_Inline_Weglot::is_json( $content ) ) ? 'json' : 'html';
		if ( 'json' !== $type ) {
			$type = ( Helper_Json_Inline_Weglot::is_xml( $content ) ) ? 'xml' : 'html';
		}

		$type              = apply_filters( 'weglot_type_treat_page', $type );
		$canonical         = $this->get_canonical_url_from_content( $content );
		$force_request_url = apply_filters( 'weglot_get_current_canonical_url', false );

		$weglot_force_translate_cart = apply_filters( 'weglot_force_translate_cart', false );
		if ( $weglot_force_translate_cart ) {
			$content = $this->force_translate_cart( $content );
		}
		// No need to translate but prepare new dom with button.
		if (
			$this->current_language === $this->original_language
			|| $this->check_404_exclusion_before_treat()
			|| ! $this->request_url_services->get_weglot_url()->getForLanguage( $this->request_url_services->get_current_language(), false )
		) {
			if ( wp_doing_ajax() && is_string( wp_get_referer() ) ) {
				if ( ! $this->request_url_services->create_url_object( wp_get_referer() )->getForLanguage( $this->request_url_services->get_current_language(), false ) ) { //phpcs:ignore
					// do nothing because the ajax referer are not exclude!
				} else {
					return $content;
				}
			} else {
				// if type is xml we render the content without treatment.
				if ( 'xml' === $type || 'json' === $type ) {
					return $content;
				} else {
					//we check if we need to translate some forced child
					$translate_inside_exclusions_blocks = $this->option_services->get_translate_inside_exclusions_blocks();
					if ( 0 < count( $translate_inside_exclusions_blocks ) ) {
						add_filter( 'weglot_parser_whitelist', function ( $whitelist ) {
							$translate_inside_exclusions_blocks = $this->option_services->get_translate_inside_exclusions_blocks();

							return array_merge( $whitelist, $translate_inside_exclusions_blocks );
						} );
						$parser = $this->parser_services->get_parser();
						if ( $force_request_url ) {
							$request_url        = $this->request_url_services->get_current_canonical_url();
							$translated_content = $parser->translate( $content, $this->original_language, $this->current_language, array(), $canonical, $request_url );
						} else {
							$translated_content = $parser->translate( $content, $this->original_language, $this->current_language, array(), $canonical );
						}

						return $this->weglot_render_dom( $translated_content, $canonical );
					} else {
						return $this->weglot_render_dom( $content, $canonical );
					}
				}
			}
		}
		do_action( 'weglot_treat_page_hook', $this->current_language );

		$parser = $this->parser_services->get_parser();

		try {
			switch ( $type ) {
				case 'json':
					$extra_keys = apply_filters( 'weglot_add_json_keys', array() );
					if ( apply_filters( 'weglot_escape_attribute_in_json', false ) ) {
						$content = $this->parser_services->preserve_attributes( $content ); // List attributes you want to escape
					}
					$translated_content = $parser->translate( $content, $this->original_language, $this->current_language, $extra_keys );
					$translated_content = wp_json_encode( $this->replace_url_services->replace_link_in_json( json_decode( $translated_content, true ) ) );
					if ( apply_filters( 'weglot_escape_attribute_in_json', false ) && is_string( $translated_content ) ) {
						$translated_content = $this->parser_services->restore_preserved_attributes( $translated_content );
					}

					return apply_filters( 'weglot_json_treat_page', $translated_content );
				case 'xml':
					$translated_content = $parser->translate( $content, $this->original_language, $this->current_language, array(), $canonical );
					if ( $this->current_language !== $this->original_language ) {
						$translated_content = $this->replace_url_services->replace_link_in_xml( $translated_content );
					}
					$translated_content = apply_filters( 'weglot_html_treat_page', $translated_content );

					return apply_filters( 'weglot_xml_treat_page', $translated_content );
				case 'html':
					$content = $this->add_ai_disclaimer( $content );

					if ( apply_filters( 'weglot_escape_attribute_in_html', false ) ) {
						$content = $this->parser_services->preserve_attributes( $content );
					}

					if ( apply_filters( 'weglot_escape_vue_js', false ) ) {
						// Escape the Vue.js attributes before processing.
						$content = $this->parser_services->escape_vue_attributes( $content );
					}


					$preserve_words_enabled = apply_filters( 'weglot_preserve_words_enabled', false );
					$preserve_words_list    = apply_filters( 'weglot_preserve_words_list', array(), $content );
					if ( $preserve_words_enabled && is_array( $preserve_words_list ) && ! empty( $preserve_words_list ) ) {
						$content = $this->parser_services->preserve_words( $content, $preserve_words_list );
					}

					if ( apply_filters( 'weglot_escape_script_templates', false ) ) {
						$content = $this->parser_services->escape_script_templates( $content );
					}


					if ( $force_request_url ) {
						$request_url        = $this->request_url_services->get_current_canonical_url();
						$translated_content = $parser->translate( $content, $this->original_language, $this->current_language, array(), $canonical, $request_url );
					} else {
						$translated_content = $parser->translate( $content, $this->original_language, $this->current_language, array(), $canonical );
					}


					// Restore script templates
					if ( apply_filters( 'weglot_escape_script_templates', false ) ) {
						$translated_content = $this->parser_services->restore_script_templates( $translated_content );
					}
					if ( $preserve_words_enabled && is_array( $preserve_words_list ) && ! empty( $preserve_words_list ) ) {
						$translated_content = $this->parser_services->restore_words( $translated_content, $preserve_words_list );
					}

					if ( apply_filters( 'weglot_escape_vue_js', false ) ) {
						$translated_content = $this->parser_services->restore_vue_attributes( $translated_content );
					}

					if ( apply_filters( 'weglot_escape_attribute_in_html', false ) ) {
						$translated_content = $this->parser_services->restore_preserved_attributes( $translated_content );
					}

					$translated_content = apply_filters( 'weglot_html_treat_page', $translated_content );
					$translated_content = $this->replace_url_services->proxify_url( $translated_content );
					$translated_content = $this->disable_automated_translation_services( $translated_content );

					return $this->weglot_render_dom( $translated_content, $canonical );
				default:
					$name_filter = sprintf( 'weglot_%s_treat_page', $type );

					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- hook name is always prefixed with "weglot_"
					return apply_filters( $name_filter, $content, $parser, $this->original_language, $this->current_language );
			}
		} catch ( ApiError $e ) {
			if ( 'json' !== $type ) {
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', 1 );
				}
				nocache_headers();
				$content .= '<!--Weglot error API : ' . $this->remove_comments( $e->getMessage() ) . '-->';
			}

			return $content;
		} catch ( \Exception $e ) {
			if ( 'json' !== $type ) {
				if ( ! defined( 'DONOTCACHEPAGE' ) ) {
					define( 'DONOTCACHEPAGE', 1 );
				}
				nocache_headers();
				$content .= '<!--Weglot error : ' . $this->remove_comments( $e->getMessage() ) . '-->';
			}

			return $content;
		}
	}


	/**
	 * Remove comments from HTML.
	 *
	 * @param string $html the HTML string.
	 *
	 * @return string
	 * @since 2.3.0
	 */
	private function remove_comments( $html ) {
		$without_comments = preg_replace( '/<!--(.*)-->/Uis', '', $html );

		// The result is printed inside an HTML comment: on a PCRE failure, the raw message could
		// carry a "-->" and leak the rest of it into the page.
		return null === $without_comments ? '' : $without_comments;
	}

	/**
	 * Force translate woocommerce cart.
	 *
	 * @param string $content the HTML string.
	 *
	 * @return string
	 * @since 2.3.0
	 */
	private function force_translate_cart( $content ) {
		if ( ! is_string( wp_get_referer() ) ) {
			return $content;
		}
		if ( false !== strpos( wp_get_referer(), '/cart/' ) ) {
			// This is the cart page
			$parser           = $this->parser_services->get_parser();
			$current_language = $this->request_url_services->create_url_object( wp_get_referer() )->getCurrentLanguage();
			if ( $current_language->getInternalCode() != $this->original_language ) {
				$translated_content = $parser->translate( $content, $this->original_language, $current_language->getInternalCode() );
				$translated_content = apply_filters( 'weglot_html_treat_page', $translated_content );

				return $this->weglot_render_dom( $translated_content );
			}
		}

		return $content;
	}

	/**
	 * @param string $dom the HTML string.
	 *
	 * @return string
	 * @since 2.3.0
	 */
	private function add_ai_disclaimer( $dom ) {
		$ai_disclaimer_selector = $this->option_services->get_option_custom_settings( 'ai_disclaimer_selector' );

		if ( ! isset( $ai_disclaimer_selector ) || '' === $ai_disclaimer_selector || ! is_string( $ai_disclaimer_selector ) ) {
			return $dom;
		}

		$disclaimer_text = 'Translated content on this website may be generated using artificial intelligence. Learn more about AI-generated translations';
		$disclaimer_text = apply_filters( 'weglot_ai_disclaimer_text', $disclaimer_text );

		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );

		// Use proper encoding and flags for complete HTML documents
		$success = $doc->loadHTML(
			'<?xml version="1.0" encoding="UTF-8"?>' . $dom,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		if ( ! $success ) {
			libxml_clear_errors();

			return $dom; // Return original on error
		}

		libxml_clear_errors();

		$xpath       = new \DOMXPath( $doc );
		$xpath_query = $this->css_to_xpath( $ai_disclaimer_selector );

		if ( false === $xpath_query ) {
			return $dom; // Invalid selector
		}

		$elements = $xpath->query( $xpath_query );

		if ( false === $elements || $elements->length === 0 ) {
			return $dom; // Query failed or no elements found
		}

		$target_element = $elements->item( 0 );
		if ( $target_element instanceof \DOMNode ) {
			$text_node = $doc->createTextNode( $disclaimer_text );
			$target_element->appendChild( $text_node );
		}

		// Remove XML processing instruction node to prevent it from appearing in output
		for ( $i = $doc->childNodes->length - 1; $i >= 0; $i -- ) {
			$node = $doc->childNodes->item( $i );
			if ( $node && $node->nodeType === XML_PI_NODE ) {
				$doc->removeChild( $node );
			}
		}

		$html = $doc->saveHTML();

		return $html !== false ? $html : $dom;

	}

	/**
	 *
	 * @param string $selector CSS selector.
	 *
	 * @return string|false XPath query or false on invalid selector.
	 */
	private function css_to_xpath( $selector ) {
		$selector = trim( $selector );

		// Validate selector format
		if ( ! preg_match( '/^[#.]?[a-zA-Z0-9_-]+$/', $selector ) ) {
			return false; // Invalid selector
		}

		if ( strpos( $selector, '#' ) === 0 ) {
			$id = substr( $selector, 1 );

			// Regex validation ensures $id contains only [a-zA-Z0-9_-], so no XPath escaping needed
			return "//*[@id='" . $id . "']";
		}

		if ( strpos( $selector, '.' ) === 0 ) {
			$class = substr( $selector, 1 );
			$class = str_replace( "'", "", $class );

			return "//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')]";
		}

		$selector = str_replace( "'", "", $selector );

		return "//" . $selector;
	}

	/**
	 * Replace links and add switcher on the final HTML.
	 *
	 * @param string $dom the final translated HTML.
	 * @param string $canonical the canonical link.
	 *
	 * @return string
	 * @since 2.3.0
	 */
	public function weglot_render_dom( $dom, $canonical = '' ) {
		$dom = $this->generate_switcher_service->generate_switcher_from_dom( $dom );

		// We only need this on translated page.
		if ( $this->current_language !== $this->original_language ) {
			$dom = $this->replace_url_services->replace_link_in_dom( $dom );
		}

		// Remove hreflangs if non-canonical page.
		if ( '' !== $canonical ) {
			$canonical   = urldecode( $canonical );
			$current_url = $this->request_url_services->get_weglot_url();
			if ( $current_url->getPath() !== $this->request_url_services->create_url_object( $canonical )->getPath() ) {
				$without_hreflang = preg_replace( '/<link rel="alternate" href=(\"|\')([^\s\>]+?)(\"|\') hreflang=(\"|\')([^\s\>]+?)(\"|\')\/>/', '', $dom );
				if ( null !== $without_hreflang ) {
					$dom = $without_hreflang;
				}
			}

			// update canonical if page excluded page.
			$original_language = $this->language_services->get_original_language();
			if ( null !== $original_language && ! $current_url->getForLanguage( $this->request_url_services->get_current_language(), false ) ) {
				$original_language_url = $current_url->getForLanguage( $original_language );
				if ( is_string( $original_language_url ) ) {
					$with_canonical = preg_replace( '/<link rel="canonical"(.*?)?href=(\"|\')([^\s\>]+?)(\"|\')/', '<link rel="canonical" href="' . esc_url( $original_language_url ) . '"', $dom );
					if ( null !== $with_canonical ) {
						$dom = $with_canonical;
					}
				}
			}
		}

		return apply_filters( 'weglot_render_dom', $dom );
	}

	/**
	 * Disable automated translation services adding translate="no" attributes.
	 *
	 * @param string $html the HTML string.
	 *
	 * @return string
	 * @since 2.3.0
	 */
	private function disable_automated_translation_services( $html ) {
		$remove_auto_service_translate = apply_filters( 'weglot_remove_google_translate', true );
		if ( $remove_auto_service_translate ) {
			$pattern     = '/<html(\s*>|\s+)/i';
			$replacement = '<html translate="no"$1';

			$result = preg_replace( $pattern, $replacement, $html );

			return null === $result ? $html : $result;
		}

		return $html;
	}

	/**
	 * Kept for code calling it through weglot_get_service(): it still returns a string, with an
	 * error message on failure, which callers used to mistake for the translated term.
	 * On Weglot 2, $l_to and $t are ignored: the target is the project original language.
	 *
	 * @deprecated 6.5 Use reverse_translate_term(), which returns null on failure.
	 *
	 * @param string $api_key Weglot API key
	 * @param string $l_from Source language
	 * @param string $l_to Target language
	 * @param string $request_url Request URL
	 * @param string $word Word to translate
	 * @param int $t Text type
	 *
	 * @return string
	 * @since 2.4.0
	 */
	public function reverseTranslate( $api_key, $l_from, $l_to, $request_url, $word, $t ) {
		return $this->reverse_translate_term( $api_key, $l_from, $l_to, $request_url, $word, $t ) ?? 'Error: Invalid response from API';
	}

	/**
	 * Translate a search term back into the original language, on Weglot 1 or Weglot 2.
	 *
	 * @param string $api_key Weglot private API key
	 * @param string $l_from Language the term is written in
	 * @param string $l_to Original language of the project
	 * @param string $request_url Request URL, sent to Weglot 1 only
	 * @param string $term Term to reverse translate
	 * @param int $t Text type, sent to Weglot 1 only
	 *
	 * @return string|null The reversed term, or null when the API gave none.
	 * @since 6.5
	 */
	public function reverse_translate_term( $api_key, $l_from, $l_to, $request_url, $term, $t = 1 ) {
		if ( 2 === $this->version_services->get_version_from_api_key_private( $api_key ) ) {
			// Weglot 2 infers the target language from the project.
			return $this->reverse_translate_weglot_2( $api_key, $l_from, $term );
		}

		return $this->reverse_translate_weglot_1( $api_key, $l_from, $l_to, $request_url, $term, $t );
	}

	/**
	 * @param string $api_key Weglot private API key
	 * @param string $l_from Source language
	 * @param string $l_to Target language
	 * @param string $request_url Request URL
	 * @param string $word Word to translate
	 * @param int $t Text type
	 *
	 * @return string|null
	 * @since 6.5
	 */
	private function reverse_translate_weglot_1( $api_key, $l_from, $l_to, $request_url, $word, $t ) {
		$request_body = wp_json_encode( [
			"l_from"      => $l_from,
			"l_to"        => $l_to,
			"request_url" => $request_url,
			"words"       => [
				[ "w" => $word, "t" => $t ]
			]
		] );

		if ( ! is_string( $request_body ) ) {
			return null;
		}

		$url      = sprintf( '%s/translate', Helper_API::get_api_url() );
		$response = wp_remote_post(
			add_query_arg( 'api_key', $api_key, $url ),
			[
				'body'        => $request_body,
				'headers'     => [ 'Content-Type' => 'application/json' ],
				'method'      => 'POST',
				'data_format' => 'body',
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log_reverse_translate( 'v1 transport error', $url, $request_body, $response->get_error_message() );

			return null;
		}

		$status        = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $body, true );
		$reversed      = is_array( $response_data ) && isset( $response_data['to_words'][0] ) ? $response_data['to_words'][0] : null;

		if ( 200 !== $status || ! is_string( $reversed ) || '' === $reversed ) {
			$this->log_reverse_translate( sprintf( 'v1 unexpected response (HTTP %s)', $status ), $url, $request_body, $body );

			return null;
		}

		$this->log_reverse_translate( sprintf( 'v1 translated (HTTP %s)', $status ), $url, $request_body, $body );

		return $reversed;
	}

	/**
	 * Reverse translate a content with the Weglot 2 API.
	 *
	 * The v2 route takes the source language and a raw string, and answers with a bare JSON
	 * string holding the content translated back into the project original language — unlike
	 * /translate there is no l_to, no words array, and no object wrapping the result.
	 *
	 * Returns null rather than an error string on failure, so a caller guarding with
	 * is_string() falls back to the untranslated term instead of searching for the error.
	 *
	 * @param string $api_key Weglot private API key
	 * @param string $l_from Language the content is written in
	 * @param string $content Content to reverse translate
	 *
	 * @return string|null
	 * @since 6.5
	 */
	public function reverse_translate_weglot_2( $api_key, $l_from, $content ) {
		$request_body = wp_json_encode(
			array(
				'l_from'  => $l_from,
				'content' => $content,
			)
		);

		if ( ! is_string( $request_body ) ) {
			return null;
		}

		$url = sprintf( '%s/%s', Helper_API::get_api_domain( $api_key ), self::REVERSE_TRANSLATE_V2_ROUTE );

		$response = wp_remote_post(
			$url,
			array(
				'body'        => $request_body,
				'headers'     => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Key ' . $api_key,
				),
				'method'      => 'POST',
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_reverse_translate( 'v2 transport error', $url, $request_body, $response->get_error_message() );

			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( ! is_string( $decoded ) || '' === $decoded ) {
			$this->log_reverse_translate( sprintf( 'v2 unexpected response (HTTP %s)', $status ), $url, $request_body, $body );

			return null;
		}

		$this->log_reverse_translate( sprintf( 'v2 translated (HTTP %s)', $status ), $url, $request_body, $body );

		return $decoded;
	}

	/**
	 * Trace a reverse translate call when the weglot_reverse_translate_debug filter is on.
	 *
	 * Both routes return null on failure, so a wrong host, payload shape or quota shows up as a
	 * silent "term left untranslated" on the front. This is the only place that says why.
	 *
	 * @param string $outcome
	 * @param string $url
	 * @param string $request_body
	 * @param string $response_body
	 *
	 * @return void
	 * @since 6.5
	 */
	private function log_reverse_translate( $outcome, $url, $request_body, $response_body ) {
		/**
		 * Log every reverse translate call (Weglot 1 and Weglot 2) to the PHP error log.
		 *
		 * @since 6.5
		 *
		 * @param bool $enabled
		 */
		if ( true !== apply_filters( 'weglot_reverse_translate_debug', false ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- opt-in diagnostic for an undocumented endpoint; off unless the filter is enabled.
		error_log(
			sprintf(
				'[weglot reverse-translate] %s | url=%s | request=%s | response=%s',
				$outcome,
				$url,
				$request_body,
				$response_body
			)
		);
	}
}



