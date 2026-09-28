<?php

namespace WeglotWP\Domcheckers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Weglot\Parser\Check\Dom\AbstractDomChecker;
use Weglot\Parser\Definitions\Enum\WordType;


/**
 * @since 2.5.0
 */
class Input_Reset extends AbstractDomChecker {
	/**
	 * {@inheritdoc}
	 */
	const DOM = "input[type='reset']";
	/**
	 * {@inheritdoc}
	 */
	const PROPERTY = 'value';
	/**
	 * @var integer
	 */
	const WORD_TYPE = WordType::TEXT;
}
