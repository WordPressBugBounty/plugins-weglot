<?php

namespace Weglot\Parser\Tests\Util;

use PHPUnit\Framework\TestCase;
use Weglot\Parser\Util\Server;

class ServerTest extends TestCase
{
    public function testGetHostReadsHttpHost(): void
    {
        static::assertSame('example.com', Server::getHost(['HTTP_HOST' => 'example.com']));
    }

    public function testGetHostKeepsThePortCarriedByHttpHost(): void
    {
        static::assertSame('example.com:8443', Server::getHost(['HTTP_HOST' => 'example.com:8443']));
    }

    public function testGetHostIgnoresTheForwardedHostUnlessAskedTo(): void
    {
        $server = [
            'HTTP_HOST' => 'internal-host.kinsta.cloud',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
        ];

        static::assertSame('internal-host.kinsta.cloud', Server::getHost($server));
        static::assertSame('example.com', Server::getHost($server, true));
    }

    public function testGetHostFallsBackOnServerNameWithAPortSeparator(): void
    {
        $server = [
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '8443',
        ];

        static::assertSame('example.com:8443', Server::getHost($server));
    }

    public function testGetHostDropsTheDefaultPortFromServerName(): void
    {
        static::assertSame('example.com', Server::getHost(['SERVER_NAME' => 'example.com', 'SERVER_PORT' => '80']));
        static::assertSame('example.com', Server::getHost(['SERVER_NAME' => 'example.com', 'SERVER_PORT' => '443', 'HTTPS' => 'on']));
    }

    public function testGetHostReturnsNullWhenNothingIsAvailable(): void
    {
        static::assertNull(Server::getHost([]));
    }
}
