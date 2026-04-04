# SimpleTable

A MediaWiki extension that converts tab-separated or similar data into a Wiki table using `<tab>...</tab>` tags.

This is the **actively maintained fork** of the original unmaintained extension by JohanTheGhost and John Bray.
Canonical URL: https://github.com/WolfgangFahl/SimpleTable

MediaWiki extension page: https://www.mediawiki.org/wiki/Extension:SimpleTable

## Usage

```
<tab class="wikitable" sep="tab" head="top">
Header1	Header2	Header3
A	B	C
</tab>
```

### Tag parameters

| Parameter | Description |
|---|---|
| `sep` | Field separator: `tab` (default), `space`, `spaces`, `comma`, `colon`, `semicolon`, `bar`, `barbar` |
| `head` | `top` — first row as header; `left` — first column as header; `topleft` — both |
| `collapse` | Add `mw-collapsed` to make the table collapsible |
| `applycssborderstyle` | Apply inline CSS `border-collapse` style |
| `class`, `style`, `border`, `id`, `width`, `align`, `summary` | Passed through to the `<table>` element |

## Installation

1. Clone or download into `extensions/SimpleTable/`
2. Add to `LocalSettings.php`:
   ```php
   wfLoadExtension( 'SimpleTable' );
   ```

## Changelog

### 2.3 (2026)
- **Bugfix:** Restore `tab` as the default separator, matching JohanTheGhost's original v1.2a intent. John Bray's v1.3 changed the default to `barbar` but introduced a broken regex (`/\|\|`/` matched `` ||` `` not `||`), so `barbar` never split anything and all pages without an explicit `sep=` were relying on tab behaviour. Fixes [#8](https://github.com/WolfgangFahl/SimpleTable/issues/8).
- **Bugfix:** Wiki markup inside cells (`'''bold'''`, `[[links]]`, etc.) was being destroyed by `htmlspecialchars()` before reaching the parser. Cell content is now passed through as raw wikitext; the MediaWiki parser sanitises it during HTML rendering. Fixes [#7](https://github.com/WolfgangFahl/SimpleTable/issues/7).
- **Bugfix:** Non-last columns were wrapped in a raw `<span>` inside wikitext, which blocked markup parsing in those cells. The span has been removed.
- **Bugfix:** `class="wikitable"` passed by the caller produced a duplicate `class=` attribute. User-supplied class tokens are now merged and deduplicated into the single base `class=` attribute.
- **Bugfix:** `strpos()` called with `null` `$head` produced PHP 8.1+ deprecation notices; guarded with null checks.
- **Refactor:** Extract `SimpleTableRenderer` — all wikitext-building logic moved to a pure PHP class with no MediaWiki dependency, enabling unit testing without a running MediaWiki instance. `SimpleTable` is now a thin hook adapter only.
- **Tests:** Add `tests/phpunit/unit/SimpleTableRendererTest.php`; runnable with plain PHPUnit (no MediaWiki install required).
- **Version:** Add `version` field to `extension.json` so the version appears on Special:Version.

### 2.2 (2026)
- **Fix:** Replace deprecated `Parser::parse()` / `$parser->mTitle` / `$parser->mOptions` with `$parser->recursiveTagParseFully()`, eliminating deprecation notices on MediaWiki 1.35+
- Add mediawiki.org extension page link to README

### 2.1 (2026)
- **Security:** Added `$allowedAttribs` allowlist — unknown/disallowed `<tab>` attributes are now silently dropped instead of being passed verbatim to the table element
- **Security:** Applied `htmlspecialchars()` to all field content to prevent XSS
- **Bugfix:** Fixed undefined variable `$wikiitab` (typo) in the `applycssborderstyle` branch
- Updated `extension.json` URL and author to reflect active maintainer

### 2.0 (2020) — John Bray
- Rewrote extension registration to use `wfLoadExtension()` / `extension.json`
- Added `barbar` separator and `mw-collapsible` support

### 1.2a — JohanTheGhost
- Original implementation

## License

GPL-3.0-or-later
