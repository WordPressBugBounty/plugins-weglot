<?php

namespace Weglot\Parser\Check\Dom;

use Weglot\Parser\Definitions\Enum\WordType;

class IframeSrc extends AbstractDomChecker
{
    public const DOM = 'iframe';
    public const PROPERTY = 'src';
    public const WORD_TYPE = WordType::EXTERNAL_LINK;

    protected function check()
    {
        $currentUrl = $this->node->src;
        $parsedUrl = parse_url($currentUrl);
        $currentHost = $this->getServerHost();

        if (isset($currentHost) && isset($parsedUrl['host']) && str_replace('www.', '', $parsedUrl['host']) !== str_replace('www.', '', $currentHost)) {
            return true;
        }

        return false;
    }
}
