<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( 'weglot_autoload' );
spl_autoload_register( 'weglot_autoload_legacy_class' );

/**
 * Keeps the class names of weglot-php 1.9.8 and earlier resolvable.
 *
 * Weglot 6.3 moved them to weglot/weglot-parser-php. Customer code written against 6.2, including
 * the examples of the developer docs, still uses the old names, and a missing class there is a
 * fatal error on every translated page. Aliases are only created when an old name is requested.
 * `catch` and `instanceof` do not trigger autoloading, so they only match an old name once it has
 * been aliased.
 *
 * @since 6.3.1
 * @param string $class_name
 * @return void
 */
function weglot_autoload_legacy_class( $class_name ) {
	static $legacy_classes = null;
	if ( null === $legacy_classes ) {
		$legacy_classes = weglot_legacy_class_names();
	}
	// Already declared (aliased earlier, or a plugin bundling weglot-php 1.9.8): class_alias() would fail.
	if ( ! isset( $legacy_classes[ $class_name ] ) || class_exists( $class_name, false ) || interface_exists( $class_name, false ) || trait_exists( $class_name, false ) ) {
		return;
	}

	$current_name = $legacy_classes[ $class_name ];
	if ( class_exists( $current_name ) || interface_exists( $current_name ) || trait_exists( $current_name ) ) {
		class_alias( $current_name, $class_name );
	}
}

/**
 * Class names removed in Weglot 6.3, mapped to their current name.
 *
 * @since 6.3.1
 * @return array<string,string>
 */
function weglot_legacy_class_names() {
	return array(
		'Weglot\Client\Api\Enum\BotType'                              => 'Weglot\Parser\Definitions\Enum\BotType',
		'Weglot\Client\Api\Enum\WordType'                             => 'Weglot\Parser\Definitions\Enum\WordType',
		'Weglot\Client\Api\Exception\AbstractException'               => 'Weglot\Parser\Definitions\Exception\AbstractException',
		'Weglot\Client\Api\Exception\InvalidWordTypeException'        => 'Weglot\Parser\Definitions\Exception\InvalidWordTypeException',
		'Weglot\Client\Api\Exception\MissingRequiredParamException'   => 'Weglot\Parser\Definitions\Exception\MissingRequiredParamException',
		'Weglot\Client\Api\Exception\WeglotCode'                      => 'Weglot\Parser\Definitions\Exception\WeglotCode',
		'Weglot\Client\Api\Shared\AbstractCollection'                 => 'Weglot\Parser\Definitions\Shared\AbstractCollection',
		'Weglot\Client\Api\Shared\AbstractCollectionArrayAccess'      => 'Weglot\Parser\Definitions\Shared\AbstractCollectionArrayAccess',
		'Weglot\Client\Api\Shared\AbstractCollectionCountable'        => 'Weglot\Parser\Definitions\Shared\AbstractCollectionCountable',
		'Weglot\Client\Api\Shared\AbstractCollectionEntry'            => 'Weglot\Parser\Definitions\Shared\AbstractCollectionEntry',
		'Weglot\Client\Api\Shared\AbstractCollectionInterface'        => 'Weglot\Parser\Definitions\Shared\AbstractCollectionInterface',
		'Weglot\Client\Api\Shared\AbstractCollectionIterator'         => 'Weglot\Parser\Definitions\Shared\AbstractCollectionIterator',
		'Weglot\Client\Api\Shared\AbstractCollectionSerializable'     => 'Weglot\Parser\Definitions\Shared\AbstractCollectionSerializable',
		'Weglot\Client\Api\TranslateEntry'                            => 'Weglot\Parser\Definitions\TranslateEntry',
		'Weglot\Client\Api\WordCollection'                            => 'Weglot\Parser\Definitions\WordCollection',
		'Weglot\Client\Api\WordEntry'                                 => 'Weglot\Parser\Definitions\WordEntry',
		'Weglot\Util\JsonUtil'                                        => 'Weglot\Parser\Util\JsonUtil',
		'Weglot\Util\Server'                                          => 'Weglot\Parser\Util\Server',
		'Weglot\Util\SourceType'                                      => 'Weglot\Parser\Util\SourceType',
	);
}

/**
 * Weglot autoload class PHP with class-name.php
 * @since 2.0
 * @param string $class_name
 * @return void
 */
function weglot_autoload( $class_name ) {
	$dir_class  = __DIR__ . '/src/';
	$prefix     = 'class-';
	$file_parts = explode( '\\', $class_name );

	$total_parts = count( $file_parts ) - 1;
	$dir_file    = $dir_class;
	for ( $i = 1; $i <= $total_parts; $i++ ) {
		if ( $total_parts !== $i ) {
			$dir_file .= strtolower( $file_parts[ $i ] ) . '/';
		} else {
			$string    = str_replace( '_', '-', strtolower( $file_parts[ $i ] ) );
			$file_load = $dir_file . $prefix . $string . '.php';

			if ( file_exists( $file_load ) ) {
				include_once $file_load; // nosemgrep: audit.php.lang.security.file.inclusion-arg
			}
		}
	}
}
