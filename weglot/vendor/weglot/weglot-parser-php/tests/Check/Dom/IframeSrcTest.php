<?php

namespace Weglot\Parser\Tests\Check\Dom;

use Weglot\Parser\Check\Dom\IframeSrc;

class IframeSrcTest extends DomCheckerTestCase
{
    /**
     * @param string $src
     *
     * @return \WGSimpleHtmlDom\simple_html_dom_node
     */
    private function iframeNode($src)
    {
        return $this->firstNode(\sprintf('<html><body><iframe src="%s"></iframe></body></html>', $src), 'iframe');
    }

    public function testIframeOnTheConfigHostIsNotCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $node = $this->iframeNode('https://example.com/embed');

        $checker = new IframeSrc($node, 'src', $this->providerForUrl('https://example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testIframeOnAnotherHostIsCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $node = $this->iframeNode('https://www.youtube.com/embed/xyz');

        $checker = new IframeSrc($node, 'src', $this->providerForUrl('https://example.com/page'));

        static::assertTrue($checker->handle());
    }

    public function testInternalIframeBehindAProxyIsNotCollected(): void
    {
        $_SERVER['HTTP_HOST'] = 'internal-host.kinsta.cloud';
        $node = $this->iframeNode('https://example.com/embed');

        $checker = new IframeSrc($node, 'src', $this->providerForUrl('https://example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testWwwIsStillIgnoredInTheComparison(): void
    {
        $_SERVER['HTTP_HOST'] = 'internal-host.kinsta.cloud';
        $node = $this->iframeNode('https://www.example.com/embed');

        $checker = new IframeSrc($node, 'src', $this->providerForUrl('https://example.com/page'));

        static::assertFalse($checker->handle());
    }

    public function testFallsBackOnServerHostWhenNoProviderIsGiven(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';

        $internal = new IframeSrc($this->iframeNode('https://example.com/embed'), 'src');
        $external = new IframeSrc($this->iframeNode('https://www.youtube.com/embed/xyz'), 'src');

        static::assertFalse($internal->handle());
        static::assertTrue($external->handle());
    }

    public function testFallsBackOnServerHostWhenConfigUrlIsEmpty(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $provider = $this->providerForUrl('');

        $internal = new IframeSrc($this->iframeNode('https://example.com/embed'), 'src', $provider);
        $external = new IframeSrc($this->iframeNode('https://www.youtube.com/embed/xyz'), 'src', $provider);

        static::assertFalse($internal->handle());
        static::assertTrue($external->handle());
    }

    public function testFallsBackOnServerNameWhenHttpHostIsMissing(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '8443';
        $provider = $this->providerForUrl('');

        $internal = new IframeSrc($this->iframeNode('https://example.com/embed'), 'src', $provider);
        $external = new IframeSrc($this->iframeNode('https://www.youtube.com/embed/xyz'), 'src', $provider);

        static::assertFalse($internal->handle(), 'The port must not make the host comparison fail.');
        static::assertTrue($external->handle());
    }

    public function testPortIsIgnoredInTheFallbackHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.com:8443';
        $node = $this->iframeNode('https://example.com/embed');

        $checker = new IframeSrc($node, 'src', $this->providerForUrl(''));

        static::assertFalse($checker->handle());
    }
}
