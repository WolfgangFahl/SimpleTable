<?php

/**
 * SimpleTableRenderer — pure wikitext builder, no MediaWiki parser dependency.
 *
 * Converts delimiter-separated plain text and a set of <tab> tag attributes
 * into MediaWiki table wikitext.  Because this class has no dependency on the
 * MediaWiki Parser or any global state it can be instantiated and exercised
 * directly in unit tests without a running MediaWiki installation.
 *
 * Typical usage (from the MediaWiki hook adapter):
 *
 *   $renderer  = new SimpleTableRenderer( $tableText, $args );
 *   $wikitext  = $renderer->getWikitext();   // pass to $parser->recursiveTagParseFully()
 *
 * Or in a test:
 *
 *   $renderer  = new SimpleTableRenderer( "A\tB\nC\tD", [ 'sep' => 'tab', 'head' => 'top' ] );
 *   $wikitext  = $renderer->getWikitext();
 *   $this->assertStringContainsString( '! A', $wikitext );
 */
class SimpleTableRenderer {

    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    /**
     * Supported field separators: name => preg pattern.
     * Default is 'tab' — the original default from JohanTheGhost's implementation.
     * John Bray changed it to 'barbar' but the barbar regex was broken (/\|\|`/
     * matched ||` not ||), so all existing pages were effectively using tab anyway.
     */
    public const SEPARATORS = [
        'space'     => '/ /',
        'spaces'    => '/\s+/',
        'tab'       => '/\t/',
        'comma'     => '/,/',
        'colon'     => '/:/',
        'semicolon' => '/;/',
        'bar'       => '/\|/',
        'barbar'    => '/\|\|/',
    ];

    /** HTML attributes that may be forwarded to the <table> element. */
    public const ALLOWED_ATTRIBS = [ 'class', 'style', 'border', 'id', 'width', 'align', 'summary' ];

    // -----------------------------------------------------------------------
    // Properties (set once in the constructor, immutable afterwards)
    // -----------------------------------------------------------------------

    /** @var string Raw text between the <tab> tags. */
    private string $tableText;

    /** @var string Separator key (e.g. 'tab', 'comma'). */
    private string $sep;

    /** @var string|null Heading mode: null | 'top' | 'left' | 'topleft'. */
    private ?string $head;

    /** @var bool Whether to apply inline CSS border-collapse styling. */
    private bool $applycssborderstyle;

    /** @var string Extra CSS class token for collapsible tables ('mw-collapsed' or ''). */
    private string $collapse;

    /** @var string Validated, escaped extra HTML attributes (excluding class) for the <table> element. */
    private string $extraParams;

    /** @var string User-supplied class value (merged into the base class string). */
    private string $userClass;

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    /**
     * @param string $tableText Raw content between the <tab> tags.
     * @param array  $args      Associative array of tag attributes.
     */
    public function __construct( string $tableText, array $args ) {
        $this->tableText = $tableText;
        $this->parseArgs( $args );
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Returns the MediaWiki wikitext for the table, or an error string if the
     * separator is invalid.
     *
     * @return string Wikitext (or an error message string).
     */
    public function getWikitext(): string {
        if ( !array_key_exists( $this->sep, self::SEPARATORS ) ) {
            return "Invalid separator: {$this->sep}";
        }

        $params    = $this->buildTableParams();
        $wikitab   = $this->buildRows();
        $wikiTable = $this->wrapTable( $params, $wikitab );

        return $wikiTable;
    }

    /**
     * Exposes the resolved separator key (useful for tests).
     */
    public function getSep(): string {
        return $this->sep;
    }

    /**
     * Exposes the resolved heading mode (useful for tests).
     */
    public function getHead(): ?string {
        return $this->head;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Parses the raw tag-attribute array into typed properties.
     */
    private function parseArgs( array $args ): void {
        $this->sep                 = 'tab';
        $this->head                = null;
        $this->applycssborderstyle = false;
        $this->collapse            = '';
        $this->extraParams         = '';
        $this->userClass           = '';

        foreach ( $args as $key => $val ) {
            if ( $key === 'sep' ) {
                $this->sep = $val;
            } elseif ( $key === 'head' ) {
                $this->head = $val;
            } elseif ( $key === 'applycssborderstyle' ) {
                $this->applycssborderstyle = true;
            } elseif ( $key === 'collapse' ) {
                $this->collapse = 'mw-collapsed';
            } elseif ( $key === 'class' ) {
                // Collected separately so it can be merged into the single
                // class= attribute rather than emitting a duplicate.
                $this->userClass = htmlspecialchars( $val, ENT_QUOTES, 'UTF-8' );
            } elseif ( in_array( $key, self::ALLOWED_ATTRIBS, true ) ) {
                $this->extraParams .= ' '
                    . htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' )
                    . '="'
                    . htmlspecialchars( $val, ENT_QUOTES, 'UTF-8' )
                    . '"';
            }
            // Unknown / disallowed attributes are silently dropped.
        }
    }

    /**
     * Builds the full attribute string for the opening {| line.
     */
    private function buildTableParams(): string {
        $params  = 'data-expandtext="+" data-collapsetext="-"';
        $params .= $this->extraParams;

        // Merge user-supplied class tokens with the always-present base classes
        // into a single class= attribute, deduplicating tokens.
        $baseTokens = [ 'wikitable', 'mw-collapsible' ];
        if ( $this->collapse !== '' ) {
            $baseTokens[] = $this->collapse;
        }
        $userTokens = $this->userClass !== ''
            ? preg_split( '/\s+/', trim( $this->userClass ) )
            : [];
        $allTokens  = array_unique( array_merge( $baseTokens, $userTokens ) );
        $params    .= ' class="' . implode( ' ', $allTokens ) . '"';

        return $params;
    }

    /**
     * Converts the table body text into wikitext row markup.
     */
    private function buildRows(): string {
        $pattern   = self::SEPARATORS[$this->sep];
        $wikitab   = '';
        $lines     = preg_split( '/\n/', trim( $this->tableText ) );
        $row       = 0;

        foreach ( $lines as $line ) {
            $wikitab .= "|-\n";
            $wikitab .= $this->buildRow( $line, $pattern, $row );
            $row++;
        }

        return $wikitab;
    }

    /**
     * Converts a single line of text into wikitext cell markup.
     *
     * @param string $line    One line of raw input.
     * @param string $pattern Preg pattern for the separator.
     * @param int    $row     Zero-based row index (used to detect the header row).
     */
    private function buildRow( string $line, string $pattern, int $row ): string {
        $isTopHeader = ( $this->head !== null && strpos( $this->head, 'top' ) !== false && $row === 0 );
        $bar         = $isTopHeader ? '!' : '|';

        if ( $this->applycssborderstyle ) {
            $bar .= 'style="border-style: solid; border-width: 1px" |';
        }

        $fields = preg_split( $pattern, $line );
        $output = '';
        $col    = 0;

        foreach ( $fields as $field ) {
            $isLeftHeader = ( $this->head !== null && strpos( $this->head, 'left' ) !== false && $col === 0 );
            $cbar         = $isLeftHeader ? '!' : $bar;

            // Do NOT escape cell content: it is wikitext that must reach the
            // parser intact (e.g. '''bold''', [[links]]).  The MediaWiki parser
            // (recursiveTagParseFully) handles sanitisation when rendering HTML.
            // See: https://github.com/WolfgangFahl/SimpleTable/issues/7
            $output .= $cbar . ' ' . $field . "\n";

            $col++;
        }

        return $output;
    }

    /**
     * Wraps the row markup in opening/closing MediaWiki table syntax.
     *
     * @param string $params  Attribute string for the opening {| line.
     * @param string $wikitab Row markup produced by buildRows().
     */
    private function wrapTable( string $params, string $wikitab ): string {
        if ( !$this->applycssborderstyle ) {
            return "{|" . $params . "\n" . $wikitab . "|}";
        }

        $tableStyle = 'style="border-collapse: collapse; border-width: 1px;'
                    . ' border-style: solid; border-color: #000"';
        return "{|" . $tableStyle . " " . $params . "\n" . $wikitab . "|}";
    }
}
