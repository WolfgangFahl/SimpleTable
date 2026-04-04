# AGENTS.md

This file provides guidance for AI coding agents working on the SimpleTable MediaWiki extension.

## Project Overview

SimpleTable is a minimal MediaWiki parser-hook extension that converts delimiter-separated plain text into rendered wiki tables via a `<tab>...</tab>` custom tag. It has no build system, no package manager, and no external dependencies.

## Repository Structure

```
SimpleTable/
├── extension.json          # MediaWiki extension manifest (registration, metadata)
├── SimpleTable.php         # MediaWiki hook adapter (~60 lines)
├── SimpleTableRenderer.php # Pure wikitext builder, no MediaWiki dependency (~200 lines)
├── SimpleTable.js          # Optional editor toolbar button
├── README.md               # Usage docs and changelog
└── LICENSE                 # GPL-3.0-or-later
```

## Architecture

The code is split into two classes to enable unit testing without a running MediaWiki instance:

| Class | Responsibility |
|---|---|
| `SimpleTable` | MediaWiki hook adapter only. Registers the `<tab>` tag, creates a `SimpleTableRenderer`, and passes the resulting wikitext through `$parser->recursiveTagParseFully()`. |
| `SimpleTableRenderer` | Pure wikitext builder. Accepts `($tableText, $args)` in the constructor and exposes `getWikitext()`. Has **no MediaWiki dependency** — fully testable in isolation. |

## Key Entry Points

- **`SimpleTable::onParserFirstCallInit(Parser $parser)`** — Hook handler that registers the `<tab>` tag with the MediaWiki parser.
- **`SimpleTable::hookTab($tableText, array $args, Parser $parser, PPFrame $frame)`** — Thin adapter: constructs a `SimpleTableRenderer` and converts its wikitext output to HTML.
- **`SimpleTableRenderer::__construct(string $tableText, array $args)`** — Parses all tag attributes into typed properties.
- **`SimpleTableRenderer::getWikitext(): string`** — Returns the complete MediaWiki table wikitext (or an error string for an invalid separator).

## Development Guidelines

### Making Changes

- All PHP logic lives in `SimpleTable.php`. There is no autoloader beyond MediaWiki's own class autoloading declared in `extension.json`.
- `extension.json` controls extension metadata, MediaWiki version requirements (`>= 1.35.0`), and hook registration. Update it if adding new hooks, resources, or changing authorship metadata.
- `SimpleTable.js` is loaded only on edit/submit page actions and uses the legacy `mw.toolbar.addButton()` API.

### Security Constraints

- **Attribute allowlist:** Only the attributes listed in `$allowedAttribs` (`class`, `style`, `border`, `id`, `width`, `align`, `summary`) may be passed through to the rendered `<table>` element. Unknown attributes must be silently dropped — do not relax this constraint.
- **XSS protection:** All cell content must be passed through `htmlspecialchars()` before being embedded in wikitext. Do not bypass or remove this.

### Parser API

- Use `$parser->recursiveTagParseFully()` to convert constructed wikitext to HTML. Do **not** use the deprecated `Parser::parse()`, `$parser->mTitle`, or `$parser->mOptions` APIs (removed/deprecated in MediaWiki 1.35+).

### Testing

There is no automated test suite. Changes must be validated manually against a running MediaWiki instance:

1. Install by cloning into `extensions/SimpleTable/` and adding `wfLoadExtension( 'SimpleTable' );` to `LocalSettings.php`.
2. Test all supported separators (`tab`, `space`, `spaces`, `comma`, `colon`, `semicolon`, `bar`, `barbar`) and header modes (`top`, `left`, `topleft`).
3. Verify `collapse` and `applycssborderstyle` attributes behave correctly.
4. Confirm that unknown attributes are dropped and cell content is properly escaped.

### Changelog

Update `README.md` with a new versioned changelog entry for any functional change, security fix, or API update.
