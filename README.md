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
| `sep` | Field separator: `tab` (default: `barbar`), `space`, `spaces`, `comma`, `colon`, `semicolon`, `bar`, `barbar` |
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
- **Refactor:** Extract `SimpleTableRenderer` — all wikitext-building logic moved to a pure PHP class with no MediaWiki dependency, enabling unit testing without a running MediaWiki instance. `SimpleTable` is now a thin hook adapter only.

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
