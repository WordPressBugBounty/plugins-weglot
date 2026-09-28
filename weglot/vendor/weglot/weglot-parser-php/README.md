# weglot-parser-php

A standalone PHP library for parsing and extracting translatable content from HTML, JSON, and text sources.

This library handles DOM parsing only — it has no knowledge of translation APIs or HTTP calls. It is designed to be used as a dependency by libraries that need to extract content for translation and apply translated content back to the source.

## Requirements

- PHP 7.4+
- Composer

## Installation

```bash
composer require weglot/weglot-parser-php
```

## Usage

```php
use Weglot\Parser\Definitions\Enum\BotType;
use Weglot\Parser\Parser;
use Weglot\Parser\ConfigProvider\ManualConfigProvider;

// ManualConfigProvider takes the current page URL and a bot type (BotType::HUMAN for regular requests)
$config = new ManualConfigProvider('https://example.com/page', BotType::HUMAN);
$parser = new Parser(3 /* translationEngine */, $config);

// Parse HTML — extracts translatable words and returns a tree + word collection
$result = $parser->parse($htmlString);
$words  = $result['words'];  // WordCollection — the list of words to translate
$tree   = $result['tree'];   // Internal tree — pass this to formatters() after translation

// Apply translations back to the source (once you have a TranslateEntry with output words)
$translatedHtml = $parser->formatters($htmlString, $translateEntry, $tree);
```

## What this library does

- Parses HTML via `simplehtmldom` and extracts translatable text nodes and attributes
- Parses JSON and plain text sources
- Applies DOM checker rules to determine which attributes and nodes are translatable
- Applies translated content back into the original DOM structure

## What this library does NOT do

- No HTTP calls
- No Weglot API interaction
- No language detection or redirection

For a full translation pipeline (parsing + API calls + response handling), see [weglot/weglot-php](https://github.com/weglot/weglot-php).

## Relationship with weglot-php

This library is extracted from `weglot/weglot-php`, where parsing and API calls were historically coupled in a single `Parser` class. The split is:

| Package | Responsibility |
|---|---|
| `weglot/weglot-parser-php` | DOM parsing, word extraction, translation application, and the shared domain types (`WordEntry`, `WordCollection`, `TranslateEntry`, `WordType`, …) under `Weglot\Parser\Definitions\` |
| `weglot/weglot-php` | API client, `TranslatingParser extends Parser` (adds `translate()` + `apiTranslate()`) |

`weglot-php` depends on `weglot-parser-php` and inherits the shared types through it. This package has no Weglot dependencies. There are no circular dependencies.

## Architecture

```
src/
├── Parser.php                  # Main entry point
├── ConfigProvider/             # Config interfaces and implementations
├── Check/                      # DOM and regex checker rules
│   ├── Dom/                    # Per-attribute/tag checkers
│   └── Regex/                  # JSON/form field checkers
└── Formatter/                  # DOM formatters (exclude blocks, switchers, etc.)
```

## License

Proprietary — © Weglot