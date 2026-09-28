<?php

namespace Weglot\Parser\Check\Dom;

use Weglot\Parser\Definitions\Enum\WordType;

class SpanTitle extends AbstractDomChecker
{
    public const DOM = 'span[title]';
    public const PROPERTY = 'title';
    public const WORD_TYPE = WordType::TEXT;
}
