<?php
/**
 * CSS minifier regression tests.
 *
 * @package All_In_One_Minifier
 */

/**
 * CSS tests.
 */
class Test_CSS_Minifier extends AIOM_Test_Case {

	private function min( $css, array $opts = array() ) {
		return AIOM_CSS_Minifier::minify( $css, $opts );
	}

	public function test_basic_rules() {
		$this->assertSame( 'a{color:red}', $this->min( "a {\n  color: red;\n}\n" ) );
		$this->assertSame( 'a,b{margin:0 auto}', $this->min( "a ,\n b { margin: 0  auto ; }" ) );
		// Space before ":" is kept (it is significant in selectors such as "div :hover").
		$this->assertSame( 'a,b{margin :0 auto}', $this->min( "a ,\n b { margin : 0  auto ; }" ) );
		$this->assertSame( '@media screen and (max-width:600px){a{b:c}.d{e:f}}', $this->min( "@media screen and (max-width: 600px) {\n a { b: c; }\n .d { e: f }\n}" ) );
	}

	public function test_selectors_and_combinators_keep_required_spaces() {
		$this->assertSame( 'a > b + c ~ d .e{x:y}', $this->min( 'a  >  b  +  c ~ d   .e { x: y }' ) );
		$this->assertSame( 'div :first-child{x:y}', $this->min( 'div :first-child { x: y }' ) );
		$this->assertSame( ':is(a,b) c{x:y}', $this->min( ':is( a , b ) c { x: y }' ) );
		$this->assertSame( '@supports not (display:grid){a{b:c}}', $this->min( '@supports not (display: grid) { a { b: c } }' ) );
		$this->assertSame( '@media (a) and (b){x{y:z}}', $this->min( '@media (a) and (b) { x { y: z } }' ) );
	}

	public function test_calc_spaces_preserved() {
		$this->assertSame( 'a{width:calc(100% - (2 * var(--g)))}', $this->min( 'a { width: calc( 100% - ( 2 * var( --g ) ) ); }' ) );
		$this->assertSame( 'a{margin:calc(1px + -2px)}', $this->min( 'a{margin:calc(1px + -2px)}' ) );
	}

	public function test_important() {
		$this->assertSame( 'a{b:c!important;d:e!important}', $this->min( 'a { b: c !important; d: e ! important; }' ) );
	}

	public function test_comments() {
		$this->assertSame( 'a{b:c}', $this->min( "/* header */\na { /* x */ b: c; /* y */ }" ) );
		$this->assertSame( '/*! License MIT */ a{b:c}', $this->min( "/*! License MIT */\na { b: c }" ) );
		$this->assertSame( 'a{b:c}', $this->min( "/*! License MIT */\na { b: c }", array( 'keep_important_comments' => false ) ) );
		$this->assertSame( 'a/**/b{c:d}', $this->min( 'a/* x */b { c: d }' ), 'comment separating identifiers must not merge them' );
		$this->assertSame( 'a{b:c}', $this->min( "a{b:c}\n/*# sourceMappingURL=a.css.map */" ) );
	}

	public function test_strings_preserved_exactly() {
		$css = 'a::before{content:"  a  /* not a comment */  \\"q\\" "}';
		$this->assertSame( $css, $this->min( $css ) );
		$this->assertSame( "a{content:'x;}  y'}", $this->min( "a { content: 'x;}  y' }" ) );
		$this->assertSame( 'a{grid-template-areas:"a  b" "c  d"}', $this->min( "a { grid-template-areas: \"a  b\"\n  \"c  d\"; }" ) );
	}

	public function test_url_tokens() {
		$this->assertSame( 'a{background:url(a.png)}', $this->min( 'a { background: url(  a.png  ) }' ) );
		$data = 'a{b:url(data:image/svg+xml;charset=utf8,%3Csvg%3E/*x*/%3C/svg%3E)}';
		$this->assertSame( $data, $this->min( $data ) );
		$this->assertSame( 'a{b:url("x y.png")}', $this->min( 'a { b: url( "x y.png" ) }' ) );
		$this->assertSame( 'a{b:myurl(x y)}', $this->min( 'a{b:myurl(x  y)}' ), 'only url( is a URL token' );
	}

	public function test_escapes() {
		$this->assertSame( '.a\\:b{c:d}', $this->min( '.a\\:b { c: d }' ) );
		// "\31 " is a hex escape whose terminating space belongs to the escape; the second space is a combinator.
		$this->assertSame( '.\\31  a{x:y}', $this->min( ".\\31  a { x: y }" ) );
		$this->assertSame( '.\\31 0{x:y}', $this->min( '.\\31 0 { x: y }' ) );
		$this->assertSame( 'a{b:c\\;}', $this->min( 'a { b: c\\; }' ), 'escaped semicolon before } must stay' );
	}

	public function test_custom_properties() {
		$this->assertSame( ':root{--a:1px;--b: ;--c:{x}}', $this->min( ":root { --a: 1px; --b: ; --c: {x} }" ) );
		$this->assertSame( 'a{--tw:var(--tw-empty,/*!*/ /*!*/)}', $this->min( 'a { --tw: var(--tw-empty,/*!*/ /*!*/); }' ) );
		$this->assertSame( 'a{--x: }', $this->min( 'a { --x:  ; }' ) );
	}

	public function test_url_rewriting() {
		$o = array( 'rewrite_base' => '/wp-content/themes/t/css/' );
		$this->assertSame( 'a{b:url(/wp-content/themes/t/img/a.png)}', $this->min( 'a { b: url(../img/a.png) }', $o ) );
		$this->assertSame( 'a{b:url("/wp-content/themes/t/css/f/a.woff2?v=1#x")}', $this->min( 'a { b: url("f/a.woff2?v=1#x") }', $o ) );
		$this->assertSame( "a{b:url('/wp-content/themes/t/css/a.png')}", $this->min( "a{b:url('./a.png')}", $o ) );
		foreach ( array( 'data:image/png;base64,AAA=', '/abs.png', 'https://cdn.example.com/a.png', '//cdn/x.png', '#frag', '%23a' ) as $u ) {
			$this->assertSame( "a{b:url($u)}", $this->min( "a{b:url($u)}", $o ), $u );
		}
		$this->assertSame( '@import "/wp-content/themes/t/css/b.css";a{b:c}', $this->min( "@import \"b.css\";\na { b: c }", $o ) );
		$this->assertSame( '@import url(/wp-content/themes/t/b.css) screen;', $this->min( '@import url(../b.css) screen;', $o ) );
		$this->assertSame( 'a{b:image-set("/wp-content/themes/t/css/a.png" 1x,"/wp-content/themes/t/css/b.png" 2x)}', $this->min( 'a { b: image-set("a.png" 1x, "b.png" 2x) }', $o ) );
		$this->assertSame( 'a{content:"a.png"}', $this->min( 'a { content: "a.png" }', $o ), 'plain strings are not URLs' );
		$this->assertSame( 'a{b:url(/a.png)}', $this->min( 'a{b:url(../../../../a.png)}', $o ), 'resolves above the base like a browser' );
		$this->assertSame( 'a{b:url(/a.png)}', $this->min( 'a{b:url(../../../../../../a.png)}', $o ), 'never climbs above the root' );
		$this->assertSame( 'a{b:url(a%20b\\(1\\).png)}', $this->min( 'a{b:url(a%20b\\(1\\).png)}', $o ), 'escaped URLs are left untouched' );
		$abs = array( 'rewrite_base' => 'https://example.com/sub/css/' );
		$this->assertSame( 'a{b:url(https://example.com/sub/img/x.png)}', $this->min( 'a{b:url(../img/x.png)}', $abs ) );
	}

	public function test_font_display() {
		$o = array( 'font_display' => 'swap' );
		$this->assertSame( '@font-face{font-family:x;src:url(a.woff2);font-display:swap}a{b:c}', $this->min( "@font-face { font-family: x; src: url(a.woff2); }\na { b: c }", $o ) );
		$this->assertSame( '@font-face{font-family:x;font-display:block}', $this->min( '@font-face { font-family: x; font-display: block }', $o ) );
		$this->assertSame( '@font-face{font-family:x}', $this->min( '@font-face { font-family: x }' ) );
	}

	public function test_malformed_input_throws() {
		$this->assertThrows(
			'AIOM_Minify_Exception',
			function () {
				$this->min( 'a { content: "abc }' );
			}
		);
		$this->assertThrows(
			'AIOM_Minify_Exception',
			function () {
				$this->min( 'a { b: c } /* never closed' );
			}
		);
		$this->assertThrows(
			'AIOM_Minify_Exception',
			function () {
				$this->min( 'a { b: url(x.png }' );
			}
		);
	}

	public function test_multibyte_and_unicode_range() {
		$css = '@font-face{unicode-range:U+0000-00FF,U+0131}a::after{content:"→ ü"}';
		$this->assertSame( $css, $this->min( $css ) );
	}

	public function test_idempotent() {
		$in   = "@charset \"utf-8\";\n/*! k */\n:root{--a: ;}\n@media (min-width:1px){ a > b { c : calc(1px + 2px) !important ; } }\n.a\\31  b{x:url( 'a b' )}";
		$once = $this->min( $in );
		$this->assertSame( $once, $this->min( $once ) );
	}

	public function test_performance() {
		$chunk = ".wp-block-button__link { color: #fff; background-color: #32373c; border-radius: 9999px; box-shadow: none; text-decoration: none; padding: calc(.667em + 2px) calc(1.333em + 2px); font-size: 1.125em; }\n/* comment */\n@media (max-width: 600px) { .a .b > .c { margin: 0 auto !important; } }\n";
		$css   = str_repeat( $chunk, 600 );
		$t     = microtime( true );
		$out   = $this->min( $css );
		$ms    = ( microtime( true ) - $t ) * 1000;
		$this->assertTrue( strlen( $out ) < strlen( $css ) * 0.85 );
		$this->assertTrue( $ms < 1500, sprintf( '%.0f KB took %.1f ms', strlen( $css ) / 1024, $ms ) );
	}
}
