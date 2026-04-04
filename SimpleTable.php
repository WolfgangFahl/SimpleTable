<?php

/*
 * SimpleTable — MediaWiki hook adapter.
 *
 * This file contains only the MediaWiki integration layer:
 *   - registers the <tab> custom tag with the parser
 *   - delegates all wikitext construction to SimpleTableRenderer
 *   - passes the resulting wikitext back through the parser
 *
 * All testable logic lives in SimpleTableRenderer (no MediaWiki dependency).
 *
 * Version history:
 *   1.2a  Last version by JohanTheGhost.
 *   1.3   Add barbar separator and collapsible support. John Bray.
 *   2.0   Rewrite extension registration to use extension.json. John Bray.
 *   2.1   Security: attribute allowlist, htmlspecialchars on field content,
 *         fix $wikiitab typo in applycssborderstyle branch. Wolfgang Fahl.
 *   2.2   Replace deprecated Parser::parse() with recursiveTagParseFully().
 *         Wolfgang Fahl.
 *   2.3   Refactor for testability: extract SimpleTableRenderer. Wolfgang Fahl.
 *
 * Thanks for contributions to:
 *   Smcnaught
 *   Frederik Dohr
 */

class SimpleTable {

    /**
     * Hook handler: registers the <tab> tag with the MediaWiki parser.
     */
    public static function onParserFirstCallInit( Parser $parser ): void {
        $parser->setHook( 'tab', [ self::class, 'hookTab' ] );
    }

    /**
     * Tag hook for <tab></tab>.
     *
     * Delegates wikitext construction to SimpleTableRenderer, then converts
     * the wikitext to HTML via the MediaWiki parser.
     *
     * @param string  $tableText Raw text between the <tab> tags.
     * @param array   $args      Associative array of tag attributes.
     * @param Parser  $parser    The active MediaWiki parser instance.
     * @param PPFrame $frame     The current parser frame.
     * @return string            Rendered HTML, or an error string.
     */
    public static function hookTab( $tableText, array $args, Parser $parser, PPFrame $frame ): string {
        $renderer  = new SimpleTableRenderer( $tableText, $args );
        $wikiTable = $renderer->getWikitext();

        // On invalid separator the renderer returns a plain error string —
        // return it directly without further parsing.
        if ( !str_starts_with( $wikiTable, '{|' ) ) {
            return $wikiTable;
        }

        // Convert the constructed wikitext to HTML.
        // recursiveTagParseFully() is the correct approach for tag hook output
        // in MediaWiki 1.35+ (Parser::parse() is deprecated).
        $html = trim( str_replace( "</table>\n\n", "</table>",
            $parser->recursiveTagParseFully( $wikiTable, $frame )
        ) );

        return $html;
    }
}
