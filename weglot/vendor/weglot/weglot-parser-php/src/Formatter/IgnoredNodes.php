<?php

namespace Weglot\Parser\Formatter;

class IgnoredNodes
{
    /**
     * @var string
     */
    protected $source;

    /**
     * @var array<int, string>
     */
    protected $ignoredNodes = [
        'strong', 'b',
        'em', 'i',
        'small', 'big',
        'sub', 'sup',
        'abbr',
        'acronym',
        'bdo',
        'cite',
        'kbd',
        'q',
    ];

    /**
     * @var array<int, string>
     */
    protected $preservedFragments = [];

    /**
     * @param string $source
     */
    public function __construct($source = '')
    {
        $this->setSource($source);
    }

    /**
     * @param array<int, string> $ignoredNodes
     *
     * @return $this
     */
    public function setIgnoredNodes($ignoredNodes)
    {
        $this->ignoredNodes = $ignoredNodes;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getIgnoredNodes()
    {
        return $this->ignoredNodes;
    }

    /**
     * Raw markup of the tags that must never be escaped, nor the tags that enclose them.
     *
     * @param array<int, string> $preservedFragments
     *
     * @return $this
     */
    public function setPreservedFragments(array $preservedFragments)
    {
        $this->preservedFragments = $preservedFragments;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getPreservedFragments()
    {
        return $this->preservedFragments;
    }

    /**
     * @param string $source
     *
     * @return $this
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param array<int|string, string> $matches
     */
    protected function replaceContent($matches): void
    {
        $this->setSource(
            str_replace(
                $matches[0],
                $this->escape($matches),
                $this->getSource()
            )
        );
    }

    /**
     * @param array<int|string, string> $matches
     */
    protected function escape($matches): string
    {
        return '&lt;'.$matches['tag'].str_replace('>', '&gt;', str_replace('<', '&lt;', $matches['more'])).'&gt;'.$matches['content'].'&lt;/'.$matches['tag'].'&gt;';
    }

    public function handle(): void
    {
        $pattern = '#<(?<tag>'.implode('|', $this->ignoredNodes).')(?<more>\s.*?)?\>(?<content>[^>]*?)\<\/(?<tagclosed>'.implode('|', $this->ignoredNodes).')>#i';
        $matches = [];

        if (empty($this->preservedFragments)) {
            while (preg_match($pattern, $this->getSource(), $matches)) {
                $this->replaceContent($matches);
            }

            return;
        }

        // Same one-at-a-time escaping as above, so the other tags come out exactly as without
        // selectors. A preserved match is left in place and skipped: stop once only those remain.
        while (preg_match_all($pattern, $this->getSource(), $all, \PREG_SET_ORDER)) {
            $next = null;
            foreach ($all as $match) {
                if (!$this->containsPreservedFragment($match[0])) {
                    $next = $match;
                    break;
                }
            }

            if (null === $next) {
                return;
            }

            $this->replaceContent($next);
        }
    }

    /**
     * @param string $match
     */
    protected function containsPreservedFragment($match): bool
    {
        foreach ($this->preservedFragments as $fragment) {
            if (str_contains($match, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
