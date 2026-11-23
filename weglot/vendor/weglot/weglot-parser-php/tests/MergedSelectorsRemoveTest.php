<?php

namespace Weglot\Parser\Tests;

use PHPUnit\Framework\TestCase;
use Weglot\Parser\ConfigProvider\ManualConfigProvider;
use Weglot\Parser\Definitions\Enum\BotType;
use Weglot\Parser\Formatter\IgnoredNodes;
use Weglot\Parser\Parser;

class MergedSelectorsRemoveTest extends TestCase
{
    private const PARAGRAPH = '<html><body><p>Hello <a href="/pricing" class="cta">see our plans</a> to continue. <b class="price">bold</b> end</p></body></html>';

    /**
     * @var array<string, mixed>
     */
    private $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'example.com';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    /**
     * @param array<string> $mergedSelectorsRemove
     *
     * @return array<int, string>
     */
    private function words(int $translationEngine, string $html, array $mergedSelectorsRemove = []): array
    {
        $parser = new Parser($translationEngine, new ManualConfigProvider('https://example.com/', BotType::HUMAN, 'title'));
        $parser->setMergedSelectorsRemove($mergedSelectorsRemove);
        $parser->parseHTML($html);

        $words = [];
        foreach ($parser->getWords() as $word) {
            $words[] = $word->getWord();
        }

        return $words;
    }

    public function testEngine3MergesTheParagraphWithoutSelectors(): void
    {
        static::assertSame(
            ['Hello <a wg-1="">see our plans</a> to continue. <b wg-2="">bold</b> end'],
            $this->words(3, self::PARAGRAPH)
        );
    }

    /**
     * As in the JS engine, a parent merges only when every child can be merged: a matched element
     * splits the whole paragraph, on both sides of it.
     */
    public function testEngine3StopsMergingTheWholeParentOfAMatchedElement(): void
    {
        static::assertSame(
            ['Hello ', 'see our plans', ' to continue. ', 'bold', ' end'],
            $this->words(3, self::PARAGRAPH, ['a.cta'])
        );
    }

    public function testEngine3DoesNotMergeInsideAMatchedElement(): void
    {
        static::assertSame(
            ['Buy ', 'now'],
            $this->words(3, '<html><body><p><a class="cta">Buy <b>now</b></a></p></body></html>', ['a.cta'])
        );
    }

    public function testEngine3KeepsMergingParentsWithoutAMatchedElement(): void
    {
        static::assertSame(
            ['see our plans', 'Other <b wg-1="">merged</b> sentence'],
            $this->words(3, '<html><body><p><a class="cta">see our plans</a></p><p>Other <b>merged</b> sentence</p></body></html>', ['a.cta'])
        );
    }

    public function testEngine2EscapesInlineTagsWithoutSelectors(): void
    {
        static::assertSame(
            ['Hello ', 'see our plans', ' to continue. &lt;b class="price"&gt;bold&lt;/b&gt; end'],
            $this->words(2, self::PARAGRAPH)
        );
    }

    public function testEngine2KeepsAMatchedInlineTagApart(): void
    {
        static::assertSame(
            ['Hello ', 'see our plans', ' to continue. ', 'bold', ' end'],
            $this->words(2, self::PARAGRAPH, ['b.price'])
        );
    }

    /**
     * Engine 2 never merges links, so a selector on one changes nothing.
     */
    public function testEngine2IgnoresASelectorOnATagItDoesNotMerge(): void
    {
        static::assertSame(
            $this->words(2, self::PARAGRAPH),
            $this->words(2, self::PARAGRAPH, ['a.cta'])
        );
    }

    public function testEngine2KeepsTheInlineTagsInsideAMatchedElementApart(): void
    {
        static::assertSame(
            ['Buy ', 'now'],
            $this->words(2, '<html><body><p><a class="cta">Buy <b>now</b></a></p></body></html>', ['a.cta'])
        );
    }

    public function testEngine2DoesNotMergeTheInlineParentOfAMatchedTag(): void
    {
        static::assertSame(
            ['ok ', 'a ', 'y', ' end'],
            $this->words(2, '<html><body><p>ok <i>a <b class="price">y</b></i> end</p></body></html>', ['b.price'])
        );
    }

    /**
     * The matched markup is compared as written in the source, so quoting and spacing must survive
     * the throwaway parse.
     */
    public function testEngine2MatchesTheMarkupAsWrittenInTheSource(): void
    {
        static::assertSame(
            ['ok ', 'y', ' end'],
            $this->words(2, "<html><body><p>ok <b class='price'  data-a=\"1\">y</b> end</p></body></html>", ['b.price'])
        );
    }

    /**
     * Engine 2 compares markup, not nodes: identical markup outside the selector context is kept
     * apart as well.
     */
    public function testEngine2KeepsIdenticalMarkupApartOutsideTheSelectorContext(): void
    {
        static::assertSame(
            ['in ', 'y', 'out ', 'y'],
            $this->words(2, '<html><body><div class="cart"><p>in <b class="price">y</b></p></div><p>out <b class="price">y</b></p></body></html>', ['.cart b.price'])
        );
    }

    public function testEngine1IgnoresTheSelectors(): void
    {
        static::assertSame(
            $this->words(1, self::PARAGRAPH),
            $this->words(1, self::PARAGRAPH, ['a.cta', 'b.price'])
        );
    }

    /**
     * The formatter and the checker provider live as long as the parser: selectors cleared between
     * two pages must not keep applying. getWords() accumulates across calls, hence the tree.
     */
    public function testEngine2DoesNotKeepThePreviousSelectorsOnTheSameParser(): void
    {
        $parser = new Parser(2, new ManualConfigProvider('https://example.com/', BotType::HUMAN, 'title'));
        $parser->setMergedSelectorsRemove(['b.price']);
        $parser->parseHTML(self::PARAGRAPH);
        $parser->setMergedSelectorsRemove([]);
        $tree = $parser->parseHTML(self::PARAGRAPH);

        static::assertStringContainsString('&lt;b class="price"&gt;bold&lt;/b&gt;', $tree['source']);
    }

    public function testEngine3DoesNotKeepThePreviousSelectorsOnTheSameParser(): void
    {
        $parser = new Parser(3, new ManualConfigProvider('https://example.com/', BotType::HUMAN, 'title'));
        $parser->setMergedSelectorsRemove(['a.cta']);
        $parser->parseHTML(self::PARAGRAPH);
        $parser->setMergedSelectorsRemove([]);
        $tree = $parser->parseHTML(self::PARAGRAPH);

        static::assertCount(1, $tree['nodes']);
    }

    public function testEngine2SourceIsUnchangedWhenNoSelectorMatches(): void
    {
        $withoutSelectors = (new Parser(2, new ManualConfigProvider('https://example.com/', BotType::HUMAN, 'title')))->parseHTML(self::PARAGRAPH);
        $withSelectors = (new Parser(2, new ManualConfigProvider('https://example.com/', BotType::HUMAN, 'title')))
            ->setMergedSelectorsRemove(['.nothing-here'])
            ->parseHTML(self::PARAGRAPH);

        static::assertSame($withoutSelectors['source'], $withSelectors['source']);
    }

    /**
     * A selector matching elsewhere on the page must leave the other paragraphs exactly as without
     * selectors, including tags only escaped correctly thanks to an earlier identical copy.
     */
    public function testEngine2LeavesTheParagraphsOutsideTheSelectorUnchanged(): void
    {
        $html = '<html><body><p><b>Note</b> text <em class="x">see <b>Note</b> here</em></p><p class="z">Other <i class="price">9</i> sentence</p></body></html>';

        static::assertSame(
            $this->words(2, $html)[0],
            $this->words(2, $html, ['.price'])[0]
        );
    }

    public function testEngine2KeepsTheNestedInlineTagsOfAMatchedContainerApart(): void
    {
        static::assertSame(
            ['Some ', 'bold', ' end.'],
            $this->words(2, '<html><body><div class="item"><p>Some <b>bold</b> end.</p></div></body></html>', ['.item'])
        );
    }

    public function testPreservedFragmentAsTheOnlyMatchTerminates(): void
    {
        $formatter = (new IgnoredNodes('<p><b class="price">y</b></p>'))->setPreservedFragments(['<b class="price">y</b>']);
        $formatter->handle();

        static::assertSame('<p><b class="price">y</b></p>', $formatter->getSource());
    }

    public function testPreservedFragmentAsTheFirstMatchTerminatesAndEscapesTheOthers(): void
    {
        $formatter = (new IgnoredNodes('<p><b class="price">y</b> and <i>it</i> <em><b>z</b></em></p>'))->setPreservedFragments(['<b class="price">y</b>']);
        $formatter->handle();

        static::assertSame('<p><b class="price">y</b> and &lt;i&gt;it&lt;/i&gt; &lt;em&gt;&lt;b&gt;z&lt;/b&gt;&lt;/em&gt;</p>', $formatter->getSource());
    }

    public function testWithoutPreservedFragmentsTheOutputIsUnchanged(): void
    {
        $source = '<p>a <b class="x">b</b> <em><i>c</i></em> <b>b</b></p>';

        $formatter = new IgnoredNodes($source);
        $formatter->handle();

        static::assertSame('<p>a &lt;b class="x"&gt;b&lt;/b&gt; &lt;em&gt;&lt;i&gt;c&lt;/i&gt;&lt;/em&gt; &lt;b&gt;b&lt;/b&gt;</p>', $formatter->getSource());
    }
}
