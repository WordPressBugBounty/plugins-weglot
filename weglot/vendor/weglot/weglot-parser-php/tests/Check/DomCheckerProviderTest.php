<?php

namespace Weglot\Parser\Tests\Check;

use PHPUnit\Framework\TestCase;
use Weglot\Parser\Check\Dom\ExternalLinkHref;
use Weglot\Parser\ConfigProvider\ManualConfigProvider;
use Weglot\Parser\Definitions\Enum\BotType;
use Weglot\Parser\Definitions\Enum\WordType;
use Weglot\Parser\Parser;
use Weglot\Parser\Tests\Fixtures\LegacyTwoArgumentChecker;

class DomCheckerProviderTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'internal-host.kinsta.cloud';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    /**
     * @param string $url
     *
     * @return Parser
     */
    private function parser($url, int $translationEngine = 3)
    {
        return new Parser($translationEngine, new ManualConfigProvider($url, BotType::HUMAN, 'title'));
    }

    public function testCheckersReceiveTheProvider(): void
    {
        $parser = $this->parser('https://example.com/page');
        $provider = $parser->getDomCheckerProvider();

        $node = \WGSimpleHtmlDom\str_get_html('<html><body><a href="/x">Link</a></body></html>', true, true, WG_DEFAULT_TARGET_CHARSET, false)->find('a')[0];
        $checker = new ExternalLinkHref($node, 'href', $provider);

        static::assertSame($provider, $checker->getProvider());
    }

    /**
     * The parser must not report a link pointing at the public host as external when the
     * origin only sees its internal hostname in $_SERVER['HTTP_HOST'].
     */
    public function testInternalLinkBehindAProxyIsNotSentAsExternalLink(): void
    {
        $parser = $this->parser('https://example.com/page');

        $parser->parseHTML('<html><body><a href="https://example.com/contact">Contact</a></body></html>');

        foreach ($parser->getWords() as $word) {
            static::assertNotSame(WordType::EXTERNAL_LINK, $word->getType(), \sprintf('"%s" was collected as an external link.', $word->getWord()));
        }
    }

    public function testExternalLinkIsStillSentAsExternalLink(): void
    {
        $parser = $this->parser('https://example.com/page');

        $parser->parseHTML('<html><body><a href="https://somewhere-else.com/contact">Contact</a></body></html>');

        $types = [];
        foreach ($parser->getWords() as $word) {
            $types[] = $word->getType();
        }

        static::assertContains(WordType::EXTERNAL_LINK, $types);
    }

    public function testThirdPartyCheckerWithTwoArgumentConstructorStillWorks(): void
    {
        $node = \WGSimpleHtmlDom\str_get_html('<html><body><a href="/x">Link</a></body></html>', true, true, WG_DEFAULT_TARGET_CHARSET, false)->find('a')[0];
        $provider = $this->parser('https://example.com/page')->getDomCheckerProvider();

        // PHP allows extra arguments on userland methods: no ArgumentCountError here.
        $class = LegacyTwoArgumentChecker::class;
        $checker = new $class($node, 'href', $provider);

        static::assertInstanceOf(LegacyTwoArgumentChecker::class, $checker);
        static::assertTrue($checker->handle());
        static::assertNull($checker->getProvider());
    }

    public function testThirdPartyCheckerWithTwoArgumentConstructorRunsThroughTheProvider(): void
    {
        foreach ([1, 2, 3] as $translationEngine) {
            $parser = $this->parser('https://example.com/page', $translationEngine);
            $provider = $parser->getDomCheckerProvider();
            $provider->removeCheckers($provider->getCheckers());
            $provider->addChecker(LegacyTwoArgumentChecker::class);

            $dom = \WGSimpleHtmlDom\str_get_html('<html><body><a href="/x">Link</a></body></html>', true, true, WG_DEFAULT_TARGET_CHARSET, false);
            $nodes = $provider->handle($dom);

            static::assertCount(1, $nodes, \sprintf('Translation engine %d did not collect the legacy checker node.', $translationEngine));
            static::assertSame(LegacyTwoArgumentChecker::class, $nodes[0]['class']);
        }
    }
}
