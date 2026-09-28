<?php

namespace WeglotWP\Domcheckers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Weglot\Parser\Check\Dom\AbstractDomChecker;
use Weglot\Parser\Definitions\Enum\WordType;


/**
 * @since 2.0
 */
class Meta_Twitter extends AbstractDomChecker {
	/**
	 * {@inheritdoc}
	 */
	const DOM = "meta[name='twitter:card'],meta[name='twitter:site'],meta[name='twitter:creator']";
	/**
	 * {@inheritdoc}
	 */
	const PROPERTY = 'content';
	/**
	 * @var integer
	 */
	const WORD_TYPE = WordType::META_CONTENT;
}
