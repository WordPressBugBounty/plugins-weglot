# CLAUDE.md

This file provides guidance to Claude Code when working with this repository.

---

## Purpose & Architecture

`weglot-parser-php` is a **standalone DOM parsing library**. It extracts translatable content from HTML, JSON, and text sources, and applies translated content back into the original structure.

**This library has no knowledge of the Weglot API.** It does not make HTTP calls, does not hold an API key, and does not depend on `weglot/weglot-php`.

### Dependency graph

```
weglot/weglot-php
  └── depends on → weglot/weglot-parser-php   (parsing + shared types)

weglot/weglot-parser-php
  └── (no Weglot dependencies)
```

The shared domain types (`WordEntry`, `WordCollection`, `TranslateEntry`, `WordType`, etc.) live here, under `src/Definitions/` (`Weglot\Parser\Definitions\` namespace). `weglot-php` consumes them through its dependency on this package — `TranslatingParser extends Parser` inherits them.

### Split rationale

`weglot/weglot-php` historically contained both parsing logic and API communication in a single `Parser` class. The parsing layer has been extracted here so that any project needing DOM parsing can depend on this package without pulling in API clients or credentials.

The API-facing layer lives in `weglot/weglot-php` as `TranslatingParser extends Parser`. It adds `translate()` and `apiTranslate()` on top of the pure parsing base, and is the only place where Weglot API calls happen.

---

## What belongs here

- HTML/JSON/text parsing (`Parser`, `parseHTML`, `parseJSON`, `parseText`)
- DOM checker rules (`src/Check/Dom/`, `src/Check/Regex/`)
- Formatters (`src/Formatter/`)
- Config providers (`src/ConfigProvider/`)

## What does NOT belong here

- Any `use Weglot\Client\...` import
- HTTP calls, API endpoints, API key handling
- `translate()` or `apiTranslate()` — those live in `weglot/weglot-php`

---

## Shared types

The domain types shared with `weglot-php` (`WordEntry`, `WordCollection`, `TranslateEntry`, `WordType`, `BotType`, the `AbstractCollection*` traits/interfaces, and the `Exception/` hierarchy) live here under `src/Definitions/`, in the `Weglot\Parser\Definitions\` namespace. `weglot-php` consumes them through its dependency on this package; never duplicate them downstream.

---

## Tech stack

- PHP 7.4+
- Composer
- `weglot/simplehtmldom` for HTML DOM parsing

---

## Code quality

Run before every commit:

```bash
vendor/bin/php-cs-fixer fix          # coding style (@Symfony ruleset)
vendor/bin/phpstan analyse --memory-limit=-1
```

`php-cs-fixer` is configured via `.php-cs-fixer.dist.php` (`@Symfony` + `@Symfony:risky`).
Use `--dry-run --diff` to preview without writing, as the CI does.

---

## Tooling

When exploring or searching the codebase, prefer **PhpStorm MCP tools** over `grep` or raw Bash:

- File reading: `mcp__phpstorm__get_file_text_by_path`
- File search: `mcp__phpstorm__find_files_by_name_keyword`, `mcp__phpstorm__find_files_by_glob`
- Text/regex search: `mcp__phpstorm__search_in_files_by_text`, `mcp__phpstorm__search_in_files_by_regex`
- Symbol navigation: `mcp__phpstorm__search_symbol`
- Directory listing: `mcp__phpstorm__list_directory_tree`