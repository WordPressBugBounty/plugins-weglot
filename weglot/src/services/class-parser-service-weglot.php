<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use WeglotWP\Helpers\Helper_API;
use Weglot\Client\Client;
use Weglot\Parser\Parser;
use Weglot\Parser\ConfigProvider\ServerConfigProvider;
use Weglot\Parser\ConfigProvider\ConfigProviderInterface;


/**
 * Parser abstraction
 *
 * @since 2.0
 */
class Parser_Service_Weglot {
	/**
	 * @var Option_Service_Weglot
	 */
	private $option_services;
	/**
	 * @var Regex_Checkers_Service_Weglot
	 */
	private $regex_checkers_services;
	/**
	 * @var Dom_Checkers_Service_Weglot
	 */
	private $dom_checkers_services;

	/** @var array<string,string>  token → original value */
	private $preserved = [];

	/** @var array<string,string> token → original word */
	private $preserved_words = [];

	/**
	 * @since 2.0
	 */
	public function __construct() {
		$this->option_services         = weglot_get_service( Option_Service_Weglot::class );
		$this->dom_checkers_services   = weglot_get_service( Dom_Checkers_Service_Weglot::class );
		$this->regex_checkers_services = weglot_get_service( Regex_Checkers_Service_Weglot::class );
	}

	/**
	 * @return Client
	 * @throws Exception
	 * @since 3.0.0
	 */
	public function get_client() {
		$api_key            = $this->option_services->get_api_key( true );
		$version            = $this->option_services->get_version();
		$translation_engine = $this->option_services->get_translation_engine();
		if ( empty( $translation_engine ) ) {
			$translation_engine = 3;
		}

		$client = new Client(
			$api_key,
			$translation_engine,
			$version,
			array(
				'host' => Helper_API::get_api_url(),
			)
		);
		$client->getHttpClient()->addHeader( 'weglot-integration: WordPress Plugin' );
		$editor_session = isset( $_SERVER['HTTP_WG_EDITOR_SESSION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_WG_EDITOR_SESSION'] ) ) : null;
		if ( $editor_session ) {
			$editor_session = preg_replace( '/[^\w\-.]/', '', $editor_session );
			if ( ! empty( $editor_session ) ) {
				$client->getHttpClient()->addHeader( 'editor-session: ' . $editor_session );
			}
		}

		return $client;
	}

	/**
	 * @return Parser
	 * @throws Exception
	 * @since 2.0
	 * @version 2.2.2
	 */
	public function get_parser() {

		$exclude_blocks   = $this->option_services->get_exclude_blocks();
		$whitelist_blocks = apply_filters(
			'weglot_parser_whitelist',
			array()
		);
		$custom_switchers = $this->option_services->get_switchers_editor_button();
		$translate_inside_exclusions_blocks   = $this->option_services->get_translate_inside_exclusions_blocks();
		$config           = apply_filters( 'weglot_parser_config_provider', new ServerConfigProvider() );
		if ( ! ( $config instanceof ConfigProviderInterface ) ) {
			$config = new ServerConfigProvider();
		}

		if ( method_exists( $config, 'loadFromServer' ) ) {
			$config->loadFromServer();
		}

		$client = $this->get_client();
		$safe_custom_switchers = is_array( $custom_switchers ) ? $custom_switchers : [];
		$parser = new Parser( $client, $config, $exclude_blocks, $safe_custom_switchers, $whitelist_blocks, $translate_inside_exclusions_blocks );

		$parser->getDomCheckerProvider()->addCheckers( $this->dom_checkers_services->get_dom_checkers() );
		$parser->getRegexCheckerProvider()->addCheckers( $this->regex_checkers_services->get_regex_checkers() );
		$ignored_nodes = apply_filters( 'weglot_get_parser_ignored_nodes', $parser->getIgnoredNodesFormatter()->getIgnoredNodes() );
		$parser->getIgnoredNodesFormatter()->setIgnoredNodes( $ignored_nodes );

		$media_enabled    = $this->option_services->get_option_button( 'media_enabled' );
		$external_enabled = $this->option_services->get_option_button( 'external_enabled' );

		// remove media and/or externalLink checker if not enable.
		$remove_checker = array();
		if ( ! $external_enabled ) {
			$remove_checker[] = '\Weglot\Parser\Check\Dom\ExternalLinkHref';
		}

		if ( ! $media_enabled ) {
			$remove_checker[] = '\Weglot\Parser\Check\Dom\ImageDataSource';
			$remove_checker[] = '\Weglot\Parser\Check\Dom\ImageSource';
		}

		if ( ! empty( $remove_checker ) ) {
			$parser->getDomCheckerProvider()->removeCheckers( $remove_checker );
		}

		return $parser;
	}

	/**
	 * Escape Vue.js attributes so that simple_html_dom does not break.
	 *
	 * @param string $content The HTML content to be processed.
	 * @return string Processed content with Vue.js attributes replaced.
	 */
	public function escape_vue_attributes( $content ) {
		// Escape attributes that start with "v-" (e.g. v-for, v-bind:src, etc.)
		$content = preg_replace( '/\bv-([\w-]+)=/', 'data-vue-v-$1=', $content );

		// Escape shorthand Vue.js directives starting with ":" by ensuring we match the start of the attribute.
		// This regex looks for either the beginning of the string (^) or any whitespace (\s)
		// followed by ":" and then the attribute name.
		return preg_replace( '/(^|\s):([\w-]+)=/', '$1data-vue-bind-$2=', $content );
	}

	/**
	 * Restore the original Vue.js attributes after translation.
	 *
	 * @param string $content The HTML content with escaped Vue attributes.
	 * @return string Content with the original Vue.js attributes restored.
	 */
	public function restore_vue_attributes( $content ) {
		// Restore attributes replaced for "v-" directives.
		$content = preg_replace( '/\bdata-vue-v-([\w-]+)=/', 'v-$1=', $content );

		// Restore the shorthand directives for attributes starting with a colon.
		return preg_replace( '/(^|\s)data-vue-bind-([\w-]+)=/', '$1:$2=', $content );
	}


	/**
	 * @param string   $content The content to process (HTML, JSON string, etc.).
	 * @param string[] $words   List of words to preserve.
	 *
	 * @return string
	 */
	public function preserve_words( string $content, array $words ): string {
		$words = array_values(
			array_filter(
				$words,
				function( $w ) {
					return is_string( $w ) && '' !== $w;
				}
			)
		);

		if ( empty( $words ) ) {
			return $content;
		}

		usort(
			$words,
			function( $a, $b ) {
				return strlen( $b ) <=> strlen( $a );
			}
		);

		$escaped = array_map(
			function( $w ) {
				return preg_quote( $w, '/' );
			},
			$words
		);

		$pattern = '/(?<![\pL\pN_])(' . implode( '|', $escaped ) . ')(?![\pL\pN_])/u';

		$result = preg_replace_callback(
			$pattern,
			function( $m ) {
				static $i = 0;
				$i++;

				$original = $m[1];
				$token    = "__WG_WORD_{$i}__";

				$this->preserved_words[ $token ] = $original;

				return $token;
			},
			$content
		);
		if ( null === $result ) {
			return $content; // Return original content on error
		}
		return $result;
	}

	/**
	 * Restore previously preserved words by replacing tokens back to their original values.
	 *
	 * @param string   $content The content to restore.
	 * @param string[] $words   (Optional) Kept for API symmetry; restoration uses stored tokens.
	 *
	 * @return string
	 */
	public function restore_words( string $content, array $words = [] ): string {
		unset( $words );

		if ( empty( $this->preserved_words ) ) {
			return $content;
		}

		foreach ( $this->preserved_words as $token => $original ) {
			$content = str_replace( $token, $original, $content );
		}

		$this->preserved_words = [];

		return $content;
	}
	/**
	 * @param string $html The HTML content where attributes should be preserved.
	 *
	 * @return string The HTML content with specified attributes replaced by tokens.
	 */
	public function preserve_attributes( string $html ): string {
		$attrs = apply_filters( 'weglot_escape_attributes', [] );
		if ( empty( $attrs ) ) {
			return $html;
		}

		$list = implode( '|', array_map( 'preg_quote', $attrs ) );

		return preg_replace_callback(
			'/\b(' . $list . ')=(\\\\?)([\'"])(.*?)\2\3/s',
			function( $m ) {
				static $i = 0;
				$i++;
				$attr  = $m[1];
				$token = "__WG_ATTR_{$attr}_{$i}__";

				$this->preserved[ $token ] = $m[4];

				return sprintf(
					'%s=%s%s%s%s%s',
					$attr,
					$m[2],
					$m[3],
					$token,
					$m[2],
					$m[3]
				);
			},
			$html
		);
	}


	/**
	 * Restores preserved attributes in the provided HTML string by replacing tokens with their original values.
	 *
	 * @param string $html The HTML string where preserved attributes need to be restored.
	 *
	 * @return string The HTML string with preserved attributes restored to their original values.
	 */
	public function restore_preserved_attributes( string $html ): string {
		foreach ( $this->preserved as $token => $original ) {
			// match quote+token+same-quote, preserving any leading slash
			$html = preg_replace_callback(
				'/(\\\\?)([\'"])' . preg_quote( $token, '/' ) . '\1\2/',
				function( $m ) use ( $original ) {
					return $m[1] . $m[2] . $original . $m[1] . $m[2];
				},
				$html
			);
		}
		// clear stash if you reuse this instance
		$this->preserved = [];
		return $html;
	}

	/**
	 * Escape script tags with template content to prevent parsing and improve performance.
	 * Targets script tags with type="text/template" or specific id/class patterns.
	 *
	 * @param string $html The HTML content to be processed.
	 * @return string Processed content with script templates replaced by tokens.
	 */
	public function escape_script_templates( string $html ): string {
		// Default script types to escape
		$type_patterns = apply_filters( 'weglot_escape_script_types', [
			'text/template',
			'text/html',
			'text/x-template',
			'text/x-handlebars-template',
		]);

		// Script IDs to escape (supports partial matching with *)
		// Example: 'tmpl-*', 'nf-*', '*-js-extra'
		$id_patterns = apply_filters( 'weglot_escape_script_ids', [
			'tmpl-*',
			'nf-*',
			'*-js-extra',
		]);

		// Script classes to escape (supports partial matching with *)
		$class_patterns = apply_filters( 'weglot_escape_script_classes', [] );

		// NEW: escape scripts whose *content* matches one of these patterns (regex, without delimiters)
		// Example: 'nfForms\s*=\s*nfForms\s*\|\|\s*\[\]'
		$contains_patterns = apply_filters( 'weglot_escape_script_contains', [] );

		// Build regex patterns (attribute-based)
		$conditions = [];

		// Add type conditions
		if ( ! empty( $type_patterns ) ) {
			$type_regex = implode( '|', array_map( function( $type ) {
				return preg_quote( $type, '/' );
			}, $type_patterns ) );
			$conditions[] = 'type=["\'](?:' . $type_regex . ')["\']';
		}

		// Add id conditions
		if ( ! empty( $id_patterns ) ) {
			$id_regex_parts = [];
			foreach ( $id_patterns as $pattern ) {
				$regex = preg_quote( $pattern, '/' );
				$regex = str_replace( '\*', '[^"\']*', $regex );
				$id_regex_parts[] = $regex;
			}
			$conditions[] = 'id=["\'](?:' . implode( '|', $id_regex_parts ) . ')["\']';
		}

		// Add class conditions
		if ( ! empty( $class_patterns ) ) {
			$class_regex_parts = [];
			foreach ( $class_patterns as $pattern ) {
				$regex = preg_quote( $pattern, '/' );
				$regex = str_replace( '\*', '[^"\']*', $regex );
				$class_regex_parts[] = $regex;
			}
			$conditions[] = 'class=["\'](?:[^"\']*\s)?(?:' . implode( '|', $class_regex_parts ) . ')(?:\s[^"\']*)?["\']';
		}

		// If nothing to do, return unchanged
		$has_attr_conditions = ! empty( $conditions );
		$has_contains_conditions = is_array( $contains_patterns ) && ! empty( $contains_patterns );
		if ( ! $has_attr_conditions && ! $has_contains_conditions ) {
			return $html;
		}

		// Build regex: capture attributes and content for post-check
		// 1 = attributes part (without <script and >), 2 = inner content
		$pattern = '/<script\b([^>]*)>(.*?)<\/script>/is';

		$result = preg_replace_callback(
			$pattern,
			function( $m ) use ( $conditions, $contains_patterns, $has_attr_conditions, $has_contains_conditions ) {
				$attrs   = $m[1];
				$content = $m[2];

				$should_preserve = false;

				// Attribute-based match (existing behavior)
				if ( $has_attr_conditions ) {
					$attr_pattern = '/(?:' . implode( '|', $conditions ) . ')/i';
					if ( preg_match( $attr_pattern, $attrs ) ) {
						$should_preserve = true;
					}
				}

				// Content-based match (new behavior)
				if ( ! $should_preserve && $has_contains_conditions ) {
					foreach ( $contains_patterns as $cp ) {
						if ( ! is_string( $cp ) || $cp === '' ) {
							continue;
						}
						// treat each entry as a regex fragment (no delimiters expected)
						$test_result = @preg_match( '/'.$cp.'/is', '' );
						if ( false === $test_result ) {
							// Invalid regex pattern, skip it
							continue;
						}
						if ( preg_match( '/'.$cp.'/is', $content ) ) {
							$should_preserve = true;
							break;
						}
					}
				}

				if ( ! $should_preserve ) {
					return $m[0];
				}

				static $i = 0;
				$i++;
				$token = "__WG_SCRIPT_TEMPLATE_{$i}__";

				// Store the entire script tag
				$this->preserved[ $token ] = $m[0];

				// Replace with a simple placeholder comment
				return "<!-- {$token} -->";
			},
			$html
		);

		// Return original HTML if preg_replace_callback failed
		return $result !== null ? $result : $html;
	}

	/**
	 * Restore escaped script template tags after translation.
	 *
	 * @param string $html The HTML string where script tags need to be restored.
	 * @return string The HTML string with script tags restored.
	 */
	public function restore_script_templates( string $html ): string {
		foreach ( $this->preserved as $token => $original ) {
			// Only restore script template tokens
			if ( strpos( $token, '__WG_SCRIPT_TEMPLATE_' ) === 0 ) {
				$html = str_replace( "<!-- {$token} -->", $original, $html );
				// Remove from preserved array to avoid conflicts
				unset( $this->preserved[ $token ] );
			}
		}
		return $html;
	}

}
