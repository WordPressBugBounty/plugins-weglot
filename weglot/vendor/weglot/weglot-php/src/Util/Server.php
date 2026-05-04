<?php

namespace Weglot\Util;

use DeviceDetector\Cache\StaticCache;
use DeviceDetector\DeviceDetector;
use Weglot\Client\Api\Enum\BotType;

class Server
{
    /**
     * @var array In-memory cache for bot detection results by user agent
     */
    private static $botCache = [];

    /**
     * @var StaticCache|null Singleton cache instance for DeviceDetector
     */
    private static $deviceDetectorCache;

    /**
     * @param bool $use_forwarded_host
     *
     * @return string
     */
    public static function fullUrl(array $server, $use_forwarded_host = false)
    {
        return self::urlOrigin($server, $use_forwarded_host).$server['REQUEST_URI'];
    }

    /**
     * @param bool $use_forwarded_host
     *
     * @return string
     */
    public static function urlOrigin(array $server, $use_forwarded_host = false)
    {
        return self::getProtocol($server).'://'.self::getHost($server, $use_forwarded_host);
    }

    /**
     * @return bool
     */
    public static function detectBotVe(array $server)
    {
        $userAgent = self::getUserAgent($server);
        $checkBotVe = Text::contains($userAgent, 'Weglot Visual Editor');
        if (null !== $userAgent && $checkBotVe) {
            return true;
        }

        return false;
    }

    /**
     * @return int
     */
    public static function detectBot(array $server)
    {
        $userAgent = self::getUserAgent($server);
        if (null === $userAgent) {
            return BotType::HUMAN;
        }

        if (!isset(self::$botCache[$userAgent])) {
            self::$botCache[$userAgent] = self::detectBotFromUserAgent($userAgent);
        }

        return self::$botCache[$userAgent];
    }

    /**
     * @param string $userAgent
     *
     * @return int
     */
    private static function detectBotFromUserAgent($userAgent)
    {
        if (str_contains($userAgent, 'wprocketbot')) {
            return BotType::GOOGLE;
        }

        if (null === self::$deviceDetectorCache) {
            self::$deviceDetectorCache = new StaticCache();
        }

        $dd = new DeviceDetector($userAgent);
        $dd->setCache(self::$deviceDetectorCache);
        $dd->parse();

        if (!$dd->isBot()) {
            return BotType::HUMAN;
        }

        $botInfo = $dd->getBot();

        if (isset($botInfo['name'])) {
            $botName = strtolower($botInfo['name']);
            switch (true) {
                case str_contains($botName, 'google'):
                    return BotType::GOOGLE;
                case str_contains($botName, 'bing'):
                    return BotType::BING;
                case str_contains($botName, 'yahoo'):
                    return BotType::YAHOO;
                case str_contains($botName, 'baidu'):
                    return BotType::BAIDU;
                case str_contains($botName, 'yandex'):
                    return BotType::YANDEX;
            }
        }

        return BotType::OTHER;
    }

    /**
     * @return bool
     */
    private static function isSsl(array $server)
    {
        if (isset($server['HTTPS'])) {
            if ('on' == strtolower($server['HTTPS'])) {
                return true;
            }

            if ('1' == $server['HTTPS']) {
                return true;
            } elseif (isset($server['SERVER_PORT']) && ('443' == $server['SERVER_PORT'])) {
                return true;
            }
        }

        if (isset($server['HTTP_X_FORWARDED_PROTO']) && 'https' === $server['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public static function getProtocol(array $server)
    {
        $protocol = isset($server['SERVER_PROTOCOL']) ? strtolower($server['SERVER_PROTOCOL']) : 'http';

        return substr($protocol, 0, strpos($protocol, '/')).(self::isSsl($server) ? 's' : '');
    }

    /**
     * @return string
     */
    public static function getPortForUrl(array $server)
    {
        $ssl = self::isSsl($server);

        if ((!$ssl && '80' === self::getPort($server))
            || ($ssl && '443' === self::getPort($server))) {
            return '';
        }

        return ':'.self::getPort($server);
    }

    /**
     * @return string
     */
    public static function getPort(array $server)
    {
        if (!isset($server['SERVER_PORT'])) {
            return '';
        }

        return $server['SERVER_PORT'];
    }

    /**
     * @param bool $use_forwarded_host
     *
     * @return string
     */
    public static function getHost(array $server, $use_forwarded_host = false)
    {
        $host = null;

        if ($use_forwarded_host && isset($server['HTTP_X_FORWARDED_HOST'])) {
            $host = $server['HTTP_X_FORWARDED_HOST'];
        } elseif (isset($server['HTTP_HOST'])) {
            $host = $server['HTTP_HOST'];
        }

        if (null === $host && isset($server['SERVER_NAME'])) {
            $host = $server['SERVER_NAME'].self::getPort($server);
        }

        return $host;
    }

    /**
     * @return string|null
     */
    public static function getUserAgent(array $server)
    {
        return isset($server['HTTP_USER_AGENT']) ? $server['HTTP_USER_AGENT'] : null;
    }
}
