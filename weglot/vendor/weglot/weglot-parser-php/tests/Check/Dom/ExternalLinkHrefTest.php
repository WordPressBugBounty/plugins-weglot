<?php

namespace Weglot\Parser\Tests\Check\Dom;

use Weglot\Parser\Check\Dom\ExternalLinkHref;

class ExternalLinkHrefTest extends DomCheckerTestCase
{
    /**
     * @param string $href
     *
     * @return \WGSimpleHtmlDom\simple_html_dom_node
     */
    private function linkNode($href)
    {
        return $this->firstNode(\sprintf('<html><body><a href="%s">Link</a></body></html>', $href), 'a');
    }

    public function testLinkOnTheConfigHostIsNotCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $node = $this->linkNode('https://example.com/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl('https://example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testLinkOnAnotherHostIsCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $node = $this->linkNode('https://somewhere-else.com/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl('https://example.com/page'));

        static::assertTrue($checker->handle());
    }

    public function testInternalLinkBehindAProxyIsNotCollected(): void
    {
        // The origin only knows its internal hostname; the public host comes from the config URL.
        $_SERVER['HTTP_HOST'] = 'internal-host.kinsta.cloud';
        $node = $this->linkNode('https://example.com/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl('https://example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testWwwIsStillIgnoredInTheComparison(): void
    {
        $_SERVER['HTTP_HOST'] = 'internal-host.kinsta.cloud';
        $node = $this->linkNode('https://example.com/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl('https://www.example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testRelativeLinkIsNeverCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'internal-host.kinsta.cloud';
        $node = $this->linkNode('/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl('https://example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testTelAndMailtoAreAlwaysCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $provider = $this->providerForUrl('https://example.com/page');

        $tel = new ExternalLinkHref($this->linkNode('tel:+33123456789'), 'href', $provider);
        $mailto = new ExternalLinkHref($this->linkNode('mailto:hello@example.com'), 'href', $provider);

        static::assertTrue($tel->handle());
        static::assertTrue($mailto->handle());
    }

    public function testFallsBackOnServerHostWhenNoProviderIsGiven(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';

        $internal = new ExternalLinkHref($this->linkNode('https://example.com/contact'), 'href');
        $external = new ExternalLinkHref($this->linkNode('https://somewhere-else.com/contact'), 'href');

        static::assertFalse($internal->handle());
        static::assertTrue($external->handle());
    }

    public function testFallsBackOnServerHostWhenConfigUrlIsEmpty(): void
    {
        // ServerConfigProvider starts with an empty URL, before loadFromServer() runs.
        $_SERVER['HTTP_HOST'] = 'example.com';
        $provider = $this->providerForUrl('');

        $internal = new ExternalLinkHref($this->linkNode('https://example.com/contact'), 'href', $provider);
        $external = new ExternalLinkHref($this->linkNode('https://somewhere-else.com/contact'), 'href', $provider);

        static::assertFalse($internal->handle());
        static::assertTrue($external->handle());
    }

    public function testFallsBackOnServerNameWhenHttpHostIsMissing(): void
    {
        // Server::getHost() rebuilds the host from SERVER_NAME, port included.
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '8443';
        $provider = $this->providerForUrl('');

        $internal = new ExternalLinkHref($this->linkNode('https://example.com/contact'), 'href', $provider);
        $external = new ExternalLinkHref($this->linkNode('https://somewhere-else.com/contact'), 'href', $provider);

        static::assertFalse($internal->handle(), 'The port must not make the host comparison fail.');
        static::assertTrue($external->handle());
    }

    public function testPortIsIgnoredInTheFallbackHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com:8443';
        $node = $this->linkNode('https://example.com/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl(''));

        static::assertFalse($checker->handle());
    }

    public function testNothingIsCollectedWhenNoHostCanBeResolved(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
        $node = $this->linkNode('https://somewhere-else.com/contact');

        $checker = new ExternalLinkHref($node, 'href', $this->providerForUrl(''));

        static::assertFalse($checker->handle(), 'Without any known host, no comparison can be made.');
    }
}
