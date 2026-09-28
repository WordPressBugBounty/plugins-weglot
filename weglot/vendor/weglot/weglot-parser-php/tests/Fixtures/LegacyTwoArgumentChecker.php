<?php

namespace Weglot\Parser\Tests\Fixtures;

use Weglot\Parser\Check\Dom\AbstractDomChecker;
use Weglot\Parser\Definitions\Enum\WordType;
use WGSimpleHtmlDom\simple_html_dom_node;

/**
 * Third-party style checker, declaring the two argument constructor signature that
 * existed before the provider was passed down. It must keep working untouched.
 */
class LegacyTwoArgumentChecker extends AbstractDomChecker
{
    public const DOM = 'a';
    public const PROPERTY = 'href';
    public const WORD_TYPE = WordType::TEXT;

    /**
     * @param string $property
     */
    public function __construct(simple_html_dom_node $node, $property)
    {
        $this->setNode($node)->setProperty($property);
    }

    protected function check()
    {
        return true;
    }
}
