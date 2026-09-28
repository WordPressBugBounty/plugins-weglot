<?php

namespace Weglot\Parser\Tests\Check\Dom;

use PHPUnit\Framework\TestCase;
use Weglot\Parser\Check\DomCheckerProvider;
use Weglot\Parser\ConfigProvider\ManualConfigProvider;
use Weglot\Parser\Definitions\Enum\BotType;
use Weglot\Parser\Parser;
use WGSimpleHtmlDom\simple_html_dom_node;

abstract class DomCheckerTestCase extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    /**
     * @param string $url URL the config provider reports, as the plugin sets it after loadFromServer()
     *
     * @return DomCheckerProvider
     */
    protected function providerForUrl($url, int $translationEngine = 3)
    {
        $parser = new Parser($translationEngine, new ManualConfigProvider($url, BotType::HUMAN, 'title'));

        return $parser->getDomCheckerProvider();
    }

    /**
     * @param string $html
     * @param string $selector
     *
     * @return simple_html_dom_node
     */
    protected function firstNode($html, $selector)
    {
        $dom = \WGSimpleHtmlDom\str_get_html($html, true, true, WG_DEFAULT_TARGET_CHARSET, false);

        static::assertNotFalse($dom, 'The fixture HTML could not be parsed.');

        $nodes = $dom->find($selector);

        static::assertNotEmpty($nodes, \sprintf('No "%s" node found in the fixture HTML.', $selector));

        return $nodes[0];
    }
}
