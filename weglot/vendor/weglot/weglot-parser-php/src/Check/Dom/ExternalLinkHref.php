<?php

namespace Weglot\Parser\Check\Dom;

use Weglot\Parser\Definitions\Enum\WordType;

class ExternalLinkHref extends AbstractDomChecker
{
    public const DOM = 'a';
    public const PROPERTY = 'href';
    public const WORD_TYPE = WordType::EXTERNAL_LINK;

    protected function check()
    {
        $currentUrl = $this->node->href;
        $parsedUrl = parse_url($currentUrl);
        $currentHost = $this->getServerHost();

        if (preg_match('/^tel:/', $currentUrl) || preg_match('/^mailto:/', $currentUrl)) {
            return true;
        }

        if (isset($currentHost) && isset($parsedUrl['host']) && str_replace('www.', '', $parsedUrl['host']) !== str_replace('www.', '', $currentHost)) {
            return true;
        }

        return false;
    }
}
