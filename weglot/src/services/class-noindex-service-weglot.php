<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Weglot\Client\Api\LanguageEntry;
use Weglot\Util\Regex;
use Weglot\Util\Regex\RegexEnum;


/**
 * Keep some translated URLs out of the search engines index.
 *
 * @since 6.3
 */
class Noindex_Service_Weglot {

	/**
	 * @var Request_Url_Service_Weglot
	 */
	private $request_url_services;

	/**
	 * @var Language_Service_Weglot
	 */
	private $language_services;


	/**
	 * @since 6.3
	 */
	public function __construct() {
		$this->request_url_services = weglot_get_service( Request_Url_Service_Weglot::class );
		$this->language_services    = weglot_get_service( Language_Service_Weglot::class );
	}

	/**
	 * Rules describing which URLs must not be indexed, in which languages.
	 *
	 * Each rule is matched against the original path, never the translated one,
	 * so a rule stays valid whatever the translated slugs are.
	 *
	 * @return array<int,array{0:Regex,1:LanguageEntry[]|null}> Second item is null when every destination language is targeted.
	 * @since 6.3
	 */
	public function get_noindex_urls() {
		/**
		 * Allow developers to keep some translated URLs out of the index.
		 *
		 * Each rule is an array where `value` is an original path or URL, `type` a RegexEnum
		 * constant (default CONTAIN) and `languages` a list of internal codes (default: every
		 * destination language). Malformed rules are skipped.
		 *
		 * @param mixed $rules
		 */
		$raw_rules = apply_filters( 'weglot_noindex_urls', array() );

		if ( ! is_array( $raw_rules ) ) {
			return array();
		}

		$rules = array();

		foreach ( $raw_rules as $raw_rule ) {
			if ( ! is_array( $raw_rule ) || ! isset( $raw_rule['value'] ) || ! is_string( $raw_rule['value'] ) || '' === $raw_rule['value'] ) {
				continue;
			}

			$type = isset( $raw_rule['type'] ) && is_string( $raw_rule['type'] ) ? $raw_rule['type'] : RegexEnum::CONTAIN;

			$rules[] = array(
				new Regex( $type, $this->request_url_services->url_to_relative( $raw_rule['value'] ) ),
				$this->resolve_languages( $raw_rule ),
			);
		}

		return $rules;
	}

	/**
	 * @param array<string,mixed> $raw_rule
	 *
	 * @return LanguageEntry[]|null Null when the rule targets every destination language.
	 * @since 6.3
	 */
	private function resolve_languages( $raw_rule ) {
		if ( ! isset( $raw_rule['languages'] ) || ! is_array( $raw_rule['languages'] ) || array() === $raw_rule['languages'] ) {
			return null;
		}

		$languages = array();

		foreach ( $raw_rule['languages'] as $internal_code ) {
			if ( ! is_string( $internal_code ) ) {
				continue;
			}

			$language = $this->language_services->get_language_from_internal( $internal_code );
			if ( $language instanceof LanguageEntry ) {
				$languages[] = $language;
			}
		}

		return $languages;
	}

	/**
	 * The original language is never noindexed: its SEO is the site owner's responsibility.
	 *
	 * @param LanguageEntry|null $language
	 *
	 * @return boolean
	 * @since 6.3
	 */
	public function is_noindex( $language ) {
		if ( ! $language instanceof LanguageEntry ) {
			return false;
		}

		$original_language = $this->language_services->get_original_language();
		if ( $original_language instanceof LanguageEntry && $original_language->getInternalCode() === $language->getInternalCode() ) {
			return false;
		}

		$path = $this->request_url_services->get_weglot_url()->getPath();

		foreach ( $this->get_noindex_urls() as $rule ) {
			if ( null !== $rule[1] && ! $this->has_language( $rule[1], $language ) ) {
				continue;
			}

			if ( $rule[0]->match( $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param LanguageEntry[] $languages
	 * @param LanguageEntry   $language
	 *
	 * @return boolean
	 * @since 6.3
	 */
	private function has_language( $languages, $language ) {
		foreach ( $languages as $item ) {
			if ( $item->getInternalCode() === $language->getInternalCode() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 * @since 6.3
	 */
	public function generate_noindex_tag() {
		if ( ! $this->is_noindex( $this->request_url_services->get_current_language() ) ) {
			return '';
		}

		return "\n" . '<meta name="robots" content="noindex, follow"/>';
	}
}
