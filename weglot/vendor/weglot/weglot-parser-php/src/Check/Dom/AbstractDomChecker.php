<?php

namespace Weglot\Parser\Check\Dom;

use Weglot\Parser\Check\DomCheckerProvider;
use Weglot\Parser\Definitions\Enum\WordType;
use Weglot\Parser\Parser;
use Weglot\Parser\Util\Server;
use Weglot\Parser\Util\Text;
use WGSimpleHtmlDom\simple_html_dom_node;

abstract class AbstractDomChecker
{
    public const DOM = '';
    public const PROPERTY = '';
    public const WORD_TYPE = WordType::TEXT;
    public const ESCAPE_SPECIAL_CHAR = false;

    /**
     * @var simple_html_dom_node
     */
    protected $node;

    /**
     * @var string
     */
    protected $property;

    /**
     * @var DomCheckerProvider|null
     */
    protected $provider;

    /**
     * @param string $property
     */
    public function __construct(simple_html_dom_node $node, $property, ?DomCheckerProvider $provider = null)
    {
        $this->setNode($node)->setProperty($property)->setProvider($provider);
    }

    /**
     * @return $this
     */
    public function setProvider(?DomCheckerProvider $provider = null)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @return DomCheckerProvider|null
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @return $this
     */
    public function setNode(simple_html_dom_node $node)
    {
        $this->node = $node;

        return $this;
    }

    /**
     * @return simple_html_dom_node
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param string $property
     *
     * @return $this
     */
    public function setProperty($property)
    {
        $this->property = $property;

        return $this;
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return bool
     */
    public function handle()
    {
        return $this->defaultCheck() && $this->check();
    }

    /**
     * @return bool
     */
    protected function defaultCheck()
    {
        $property = $this->property;

        if ($this->node->hasAncestorAttribute('wg-mode-whitelist')) {
            return
                '' != Text::fullTrim($this->node->$property)
                && $this->node->hasAncestorAttribute(Parser::ATTRIBUTE_TRANSLATE);
        }

        return
            '' != Text::fullTrim($this->node->$property)
            && (
                !$this->node->hasAncestorAttribute(Parser::ATTRIBUTE_NO_TRANSLATE)
                || $this->node->hasAncestorAttribute(Parser::ATTRIBUTE_TRANSLATE_INSIDE_BLOCKS)
            );
    }

    /**
     * @return bool
     */
    protected function check()
    {
        return true;
    }

    /**
     * Host of the page currently being parsed, without its port.
     *
     * It is derived from the config provider URL, so that a site served behind a
     * reverse proxy — where $_SERVER['HTTP_HOST'] holds the internal origin
     * hostname instead of the public one — resolves its own host correctly.
     * Falls back on the server data when the config carries no usable URL.
     *
     * @return string|null
     */
    protected function getServerHost()
    {
        if (null !== $this->provider) {
            $host = parse_url($this->provider->getParser()->getConfigProvider()->getUrl(), \PHP_URL_HOST);

            if (\is_string($host) && '' !== $host) {
                return $host;
            }
        }

        return self::hostWithoutPort(Server::getHost($_SERVER));
    }

    /**
     * Hosts are compared against parse_url() output, which never carries a port,
     * so the port has to go — Server::getHost() appends it to SERVER_NAME, and
     * HTTP_HOST holds it too whenever the request is not served on 80 or 443.
     *
     * @param mixed $host
     *
     * @return string|null
     */
    private static function hostWithoutPort($host)
    {
        if (!\is_string($host) || '' === $host) {
            return null;
        }

        // Prefixing with // makes parse_url() read the value as an authority,
        // which also keeps bracketed IPv6 hosts intact.
        $parsed = parse_url('//'.$host, \PHP_URL_HOST);

        return \is_string($parsed) && '' !== $parsed ? $parsed : null;
    }

    /**
     * @return list<int|string>
     */
    public static function toArray()
    {
        $class = static::class;

        return [
            $class::DOM,
            $class::PROPERTY,
            $class::WORD_TYPE,
        ];
    }
}
