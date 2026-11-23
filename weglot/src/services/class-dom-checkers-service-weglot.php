<?php

namespace WeglotWP\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Weglot\Util\Text;


/**
 * Dom Checkers
 *
 * @since 2.0
 * @version 2.0.6
 */
class Dom_Checkers_Service_Weglot {

	/**
	 * @since 2.0
	 * @return array<int, string>
	 */
	public function get_dom_checkers() {
		$files    = self::get_checker_filenames( __DIR__ . '/../domcheckers' );
		$checkers = array_map(
			function ( $filename ) {
				// Thanks WPCS :)
				$filename = Text::removeFileExtension( $filename );
				$filename = str_replace( 'class-', '', $filename );
				$filename = implode( '_', array_map( 'ucfirst', explode( '-', $filename ) ) );
				return '\\WeglotWP\\Domcheckers\\' . $filename;
			},
			$files
		);

		return apply_filters( 'weglot_get_dom_checkers', $checkers );
	}

	/**
	 * Class files of a checker directory. A stray file there (.DS_Store, a macOS "._" copy,
	 * an editor backup) would be mapped to a class that does not exist and fatal every
	 * translated page. Keys are preserved, as callers and filters have always received them.
	 *
	 * @since 6.5
	 * @param string $directory Absolute path of the directory to scan.
	 * @return array<int, string>
	 */
	public static function get_checker_filenames( string $directory ): array {
		$filenames = is_dir( $directory ) ? scandir( $directory ) : false;
		if ( ! is_array( $filenames ) ) {
			return array();
		}

		$class_files = preg_grep( '/^class-[a-z0-9-]+\.php$/', $filenames );

		return is_array( $class_files ) ? $class_files : array();
	}
}
