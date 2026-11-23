<?php

namespace WeglotWP\Third\Divi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WeglotWP\Models\Hooks_Interface_Weglot;
use Weglot\Parser\Check\Regex\RegexChecker;


/**
 * Translate the responsive content Divi stores in its data-et-multi-view attribute.
 *
 * Divi renders per-breakpoint content as HTML-escaped JSON in that attribute
 * (Divi/includes/builder/module/helpers/MultiViewOptions.php), so the parser never sees it as text:
 *
 *     data-et-multi-view="{&quot;schema&quot;:{&quot;content&quot;:{&quot;desktop&quot;:&quot;…&quot;,
 *                          &quot;tablet&quot;:&quot;…&quot;}},&quot;slug&quot;:&quot;et_pb_text&quot;}"
 *
 * The schema holds five contexts — content, attrs, styles, classes, visibility — plus the optional
 * target and hover_selector selectors. Only content carries text meant for a reader.
 *
 * JsonChecker matches leaf key names, not paths, and skips non-string values. The breakpoint names
 * therefore select content and nothing else: content is the only context whose breakpoint key holds
 * a string directly, styles and attrs nest one level deeper under a CSS property or an attribute
 * name, and classes and visibility hold integers.
 *
 * @since 6.5
 */
class Divi_Multi_View_Weglot implements Hooks_Interface_Weglot {

	/**
	 * Divi drops a context that only has a desktop value, so the attribute is only present when a
	 * breakpoint differs. All four are listed because any of them can be the one that varies.
	 */
	const BREAKPOINTS = array( 'desktop', 'tablet', 'phone', 'hover' );

	/**
	 * @var Divi_Active
	 */
	private $divi_active_services;

	/**
	 * @since 6.5
	 * @return void
	 */
	public function __construct() {
		$this->divi_active_services = weglot_get_service( Divi_Active::class );
	}

	/**
	 * @since 6.5
	 * @see Hooks_Interface_Weglot
	 * @return void
	 */
	public function hooks() {
		if ( ! $this->divi_active_services->is_active() ) {
			return;
		}

		add_filter( 'weglot_get_regex_checkers', array( $this, 'add_multi_view_checker' ) );
	}

	/**
	 * @since 6.5
	 *
	 * @param array<int,mixed> $regex_checkers
	 *
	 * @return array<int,mixed>
	 */
	public function add_multi_view_checker( $regex_checkers ) {
		if ( ! is_array( $regex_checkers ) ) {
			return $regex_checkers;
		}

		/**
		 * Which parts of the multi view schema to translate.
		 *
		 * Defaults to the breakpoint names, which select schema.content only. Adding an attribute
		 * name here also reaches schema.attrs, at the risk of sending CSS classes or selectors to
		 * the API.
		 *
		 * @since 6.5
		 *
		 * @param array<int,string> $keys
		 */
		$keys = apply_filters( 'weglot_divi_multi_view_keys', self::BREAKPOINTS );

		if ( ! is_array( $keys ) || array() === $keys ) {
			return $regex_checkers;
		}

		// The attribute value is esc_attr()'d, so its quotes are entities and the capture cannot
		// swallow the closing one. It is decoded before parsing and re-encoded afterwards.
		$regex_checkers[] = new RegexChecker(
			'#data-et-multi-view="((.|\s)+?)"#',
			'JSON',
			1,
			array_values( $keys ),
			'html_entity_decode',
			'htmlentities'
		);

		return $regex_checkers;
	}

}
