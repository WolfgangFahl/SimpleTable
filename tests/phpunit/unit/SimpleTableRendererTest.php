<?php

/**
 * Unit tests for SimpleTableRenderer.
 *
 * These tests have no MediaWiki dependency and can be run with plain PHPUnit:
 *
 *   php vendor/bin/phpunit extensions/SimpleTable/tests/phpunit/unit/
 *
 * Or from MediaWiki core:
 *
 *   composer phpunit:entrypoint -- extensions/SimpleTable/tests/phpunit/unit/
 *
 * @group SimpleTable
 */

// Allow running outside of a full MediaWiki installation (standalone PHPUnit).
if ( !class_exists( 'MediaWikiUnitTestCase' ) ) {
    require_once __DIR__ . '/../../../SimpleTableRenderer.php';

    /**
     * Minimal shim so the file is also runnable via MediaWiki's test runner
     * without duplicating the class definition.
     */
    abstract class MediaWikiUnitTestCase extends \PHPUnit\Framework\TestCase {}
}

#[\PHPUnit\Framework\Attributes\CoversClass(SimpleTableRenderer::class)]
#[\PHPUnit\Framework\Attributes\Group('SimpleTable')]
class SimpleTableRendererTest extends MediaWikiUnitTestCase {

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Builds a renderer and returns its wikitext output.
     */
    private function render( string $text, array $args = [] ): string {
        return ( new SimpleTableRenderer( $text, $args ) )->getWikitext();
    }

    // ------------------------------------------------------------------
    // Issue #7 — wiki markup inside cells must be preserved as-is
    // ------------------------------------------------------------------

    /**
     * Bold markup ('''…''') must not be HTML-escaped.
     *
     * Before the fix htmlspecialchars() turned single-quotes into &#039;,
     * so the parser never saw the triple-apostrophe bold syntax.
     */
    public function testBoldMarkupIsPreserved(): void {
        $wikitext = $this->render( "'''Titel'''\t'''Glatzer Zeitung.'''", [ 'sep' => 'tab' ] );

        $this->assertStringContainsString(
            "'''Titel'''",
            $wikitext,
            "Triple-apostrophe bold markup must not be HTML-escaped in cell content"
        );
        $this->assertStringContainsString(
            "'''Glatzer Zeitung.'''",
            $wikitext,
            "Triple-apostrophe bold markup must not be HTML-escaped in second cell"
        );
        $this->assertStringNotContainsString(
            '&#039;',
            $wikitext,
            "Single-quotes must not be HTML-entity-encoded in wikitext output"
        );
    }

    /**
     * Wiki-link markup ([[…]]) must not be HTML-escaped.
     *
     * Before the fix square brackets were escaped to &lsqb; / &#91; etc.,
     * preventing the parser from resolving internal links.
     */
    public function testWikiLinkMarkupIsPreserved(): void {
        $wikitext = $this->render( "Ort\t[[GOV:GLAADTJO80HK|Glatz]]", [ 'sep' => 'tab' ] );

        $this->assertStringContainsString(
            '[[GOV:GLAADTJO80HK|Glatz]]',
            $wikitext,
            "Wiki-link markup must reach the parser unescaped"
        );
        $this->assertStringNotContainsString(
            '&lsqb;',
            $wikitext
        );
        $this->assertStringNotContainsString(
            '&#91;',
            $wikitext
        );
    }

    /**
     * Full reproduction of the input from issue #7.
     * Verifies that none of the cells have their markup destroyed.
     */
    public function testIssue7FullInputPreservesMarkup(): void {
        $input = implode( "\n", [
            "'''Titel'''\t'''Glatzer Zeitung.'''",
            "'''Untertitel'''\tAllgemeiner Anzeiger für Stadt und Land.",
            "'''Erscheinungsort'''\t[[GOV:GLAADTJO80HK|Glatz]]",
            "'''Herausgeber'''\tL. Schirmer",
            "'''Periodizität'''\t2x wöchentlich",
        ] );

        $wikitext = $this->render( $input, [ 'sep' => 'tab', 'border' => '0' ] );

        // Bold markup intact
        $this->assertStringContainsString( "'''Titel'''",          $wikitext );
        $this->assertStringContainsString( "'''Glatzer Zeitung.'''", $wikitext );
        $this->assertStringContainsString( "'''Untertitel'''",     $wikitext );
        $this->assertStringContainsString( "'''Erscheinungsort'''", $wikitext );

        // Wiki-link intact
        $this->assertStringContainsString( '[[GOV:GLAADTJO80HK|Glatz]]', $wikitext );

        // No HTML entity escaping of markup characters
        $this->assertStringNotContainsString( '&#039;', $wikitext );
        $this->assertStringNotContainsString( '&amp;',  $wikitext );
    }

    // ------------------------------------------------------------------
    // Baseline sanity tests
    // ------------------------------------------------------------------

    public function testInvalidSeparatorReturnsErrorString(): void {
        $result = $this->render( 'A,B', [ 'sep' => 'invalid' ] );
        $this->assertStringStartsWith( 'Invalid separator:', $result );
    }

    public function testDefaultSeparatorIsBarbar(): void {
        $renderer = new SimpleTableRenderer( 'A||B', [] );
        $this->assertSame( 'barbar', $renderer->getSep() );
    }

    public function testTopHeaderRowUsesExclamationMark(): void {
        $wikitext = $this->render( "H1\tH2\nA\tB", [ 'sep' => 'tab', 'head' => 'top' ] );
        // First row should use ! (header cell marker)
        $this->assertMatchesRegularExpression( '/!\s+H1/', $wikitext );
        // Second row should use | (data cell marker)
        $this->assertMatchesRegularExpression( '/\|\s+A/', $wikitext );
    }

    public function testLeftHeaderColumnUsesExclamationMark(): void {
        $wikitext = $this->render( "H\tA\nH2\tB", [ 'sep' => 'tab', 'head' => 'left' ] );
        $this->assertMatchesRegularExpression( '/!\s+H\b/', $wikitext );
    }

    public function testCollapseAddsClass(): void {
        $wikitext = $this->render( 'A||B', [ 'collapse' => '1' ] );
        $this->assertStringContainsString( 'mw-collapsed', $wikitext );
    }

    public function testAllowedAttribsArePassedThrough(): void {
        $wikitext = $this->render( 'A||B', [ 'class' => 'wikitable sortable' ] );
        $this->assertStringContainsString( 'class="wikitable sortable"', $wikitext );
    }

    public function testUnknownAttribsAreSilentlyDropped(): void {
        $wikitext = $this->render( 'A||B', [ 'onclick' => 'alert(1)' ] );
        $this->assertStringNotContainsString( 'onclick', $wikitext );
        $this->assertStringNotContainsString( 'alert',   $wikitext );
    }

    public function testOutputStartsWithTableSyntax(): void {
        $wikitext = $this->render( 'A||B' );
        $this->assertStringStartsWith( '{|', $wikitext );
        $this->assertStringEndsWith( '|}', $wikitext );
    }
}
