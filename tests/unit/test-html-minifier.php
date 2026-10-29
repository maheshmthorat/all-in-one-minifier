<?php
/**
 * HTML minifier regression tests.
 *
 * @package All_In_One_Minifier
 */

/**
 * HTML tests.
 */
class Test_HTML_Minifier extends AIOM_Test_Case {

	private function min( $html, array $opts = array() ) {
		$m = new AIOM_HTML_Minifier( $opts );
		return $m->process( $html );
	}

	public function test_collapses_text_whitespace() {
		$this->assertSame( "<p>a b\nc</p>", $this->min( "<p>a   b\n\n   c</p>" ) );
		$this->assertSame( '<p>a b</p>', $this->min( "<p>a\t\tb</p>" ) );
	}

	public function test_keeps_single_space_between_inline_elements() {
		$this->assertSame( '<span>a</span> <span>b</span>', $this->min( '<span>a</span>    <span>b</span>' ) );
		$this->assertSame( "<b>a</b>\n<i>b</i>", $this->min( "<b>a</b>\n\n   <i>b</i>" ) );
	}

	public function test_non_breaking_space_untouched() {
		$this->assertSame( "<p>a\xC2\xA0\xC2\xA0b &nbsp; c</p>", $this->min( "<p>a\xC2\xA0\xC2\xA0b &nbsp;  c</p>" ) );
	}

	public function test_document_structure_whitespace_removed() {
		$in  = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"utf-8\">\n  <title>  T  </title>\n  <link rel=\"stylesheet\" href=\"a.css\">\n</head>\n<body class=\"home\">\n  <p>x</p>\n</body>\n</html>\n";
		$out = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>  T  </title><link rel="stylesheet" href="a.css"></head><body class="home"><p>x</p></body></html>';
		$this->assertSame( $out, $this->min( $in ) );
	}

	public function test_whitespace_kept_around_ignored_head_and_body_tags() {
		// Text in <head> implies <body>; the later </head> and <body> are ignored, so the
		// whitespace around them is rendered body text and must survive.
		$in = "<html><head><title>t</title>stray\n</head>\n<body>\n<a>x</a></body></html>";
		$this->assertSame( "<html><head><title>t</title>stray\n</head>\n<body>\n<a>x</a></body></html>", $this->min( $in ) );
		$this->assertSame( '<html><head></head><body><p>x</p></body></html>', $this->min( "<html>\n<head>\n</head>\n<body>\n<p>x</p>\n</body>\n</html>" ) );
	}

	public function test_pre_textarea_code_preserved() {
		$pre = "<pre>  a\n    b  </pre>";
		$this->assertSame( $pre, $this->min( $pre ) );
		$ta = "<textarea name=\"x\">  line1\n\n  line2 </textarea>";
		$this->assertSame( $ta, $this->min( $ta ) );
		$code = "<p>x <code>a   =   b</code> y</p>";
		$this->assertSame( $code, $this->min( $code ) );
		$nested = "<pre><code class=\"lang\">if (a) {\n    b();\n}</code>\n</pre>";
		$this->assertSame( $nested, $this->min( $nested ) );
		$upper = "<PRE>  A  </PRE>";
		$this->assertSame( $upper, $this->min( $upper ) );
	}

	public function test_pre_with_nested_same_tag() {
		$in = "<pre>a  <pre>b  </pre>  c  </pre>  d   e";
		$this->assertSame( "<pre>a  <pre>b  </pre>  c  </pre> d e", $this->min( $in ) );
	}

	public function test_attribute_values_untouched_and_whitespace_normalised() {
		$this->assertSame( '<input value="  a   b  " title=\'x  y\'>', $this->min( '<input value="  a   b  " title=\'x  y\'>' ) );
		$this->assertSame( '<a href="x" class="y">z</a>', $this->min( "<a   href=\"x\"\n     class=\"y\"   >z</a>" ) );
		$this->assertSame( '<div data-x="a>b" class="c">', $this->min( '<div data-x="a>b"    class="c">' ) );
		$this->assertSame( '<a b = "1" c= "2">', $this->min( '<a b = "1" c= "2">' ), 'already single-spaced attributes are left as-is' );
		$this->assertSame( '<a b ="1" c="2">', $this->min( "<a b = \"1\"\n c=  \"2\">" ) );
		$this->assertSame( '<div x-on:click.prevent="open = !open" @keyup.escape="a" :class="{ \'x\': y }">', $this->min( "<div  x-on:click.prevent=\"open = !open\"\n @keyup.escape=\"a\" :class=\"{ 'x': y }\">" ) );
	}

	public function test_self_closing_with_unquoted_value_keeps_space() {
		$this->assertSame( '<img src=x />', $this->min( '<img src=x />' ) );
		$this->assertSame( '<img src="x"/>', $this->min( '<img src="x" />' ) );
		$this->assertSame( '<br/>', $this->min( '<br />' ) );
		$this->assertSame( '<input disabled />', $this->min( '<input disabled   />' ) );
	}

	public function test_script_content_untouched_without_callback() {
		$s = "<script>\n// comment with <b>tag</b>\nvar a = 1;   var b = '  x  ';\n</script>";
		$this->assertSame( $s, $this->min( $s ) );
	}

	public function test_script_escaped_state() {
		$s   = '<script><!-- document.write("<script>x()</script>"); --></script>';
		$out = $this->min( $s . "\n\n<p>a   b</p>" );
		$this->assertSame( $s . "\n<p>a b</p>", $out );
	}

	public function test_script_end_tag_case_and_spacing() {
		$s = "<SCRIPT type=\"text/javascript\">var x = '</scrip';  </SCRIPT  >";
		$this->assertSame( "<SCRIPT type=\"text/javascript\">var x = '</scrip';  </SCRIPT>", $this->min( $s ) );
	}

	public function test_stray_quote_in_unquoted_value_does_not_swallow_script() {
		$in  = "<a href=foo\"bar>x</a>\n<script>\n// c\nf()\n</script>";
		$out = $this->min( $in );
		$this->assertContains( "<script>\n// c\nf()\n</script>", $out );
	}

	public function test_comments() {
		$this->assertSame( 'ab', $this->min( 'a<!-- x -->b' ) );
		$this->assertSame( 'a b', $this->min( 'a <!-- x --> b' ) );
		$cond = '<!--[if lt IE 9]><script src="h.js"></script><![endif]-->';
		$this->assertSame( $cond, $this->min( $cond ) );
		$this->assertSame( '<!--[if !IE]><!--><p>x</p><!--<![endif]-->', $this->min( '<!--[if !IE]><!--><p>x</p><!--<![endif]-->' ) );
		$this->assertSame( '<p>a<!-- -->b</p>', $this->min( '<p>a<!-- -->b</p>' ), 'React text separator' );
		$this->assertSame( '<div><!--[--><p>x</p><!--]--><!----></div>', $this->min( '<div><!--[--><p>x</p><!--]--><!----></div>' ), 'Vue hydration markers' );
		$this->assertSame( '<!-- ko if: a --><p></p><!-- /ko -->', $this->min( '<!-- ko if: a --><p></p><!-- /ko -->' ) );
		$this->assertSame( '<!--esi <esi:include src="x"/> -->', $this->min( '<!--esi <esi:include src="x"/> -->' ) );
		$this->assertSame( 'ab', $this->min( 'a<!-- x --!>b' ) );
		$this->assertSame( 'a<!-->b', $this->min( 'a<!-->b' ) );
		$this->assertSame( '<!-- keep-me please -->', $this->min( '<!-- keep-me please -->', array( 'keep_comments' => array( 'keep-me' ) ) ) );
		$this->assertSame( 'a<!-- x -->b', $this->min( 'a<!-- x -->b', array( 'remove_comments' => false ) ) );
	}

	public function test_unterminated_constructs_are_left_alone() {
		// An unterminated comment swallows the rest of the document in browsers too, so dropping it is equivalent.
		$this->assertSame( '<p>a</p>', $this->min( "<p>a</p>  <!-- open   \n  end" ) );
		$this->assertSame( "<p>a</p><!--[if IE]> open\n  end", $this->min( "<p>a</p><!--[if IE]> open\n  end" ) );
		$this->assertSame( "<p>x</p><div class=\"a   ", $this->min( "<p>x</p><div class=\"a   " ) );
		$this->assertSame( "<script>var a  =  1;", $this->min( "<script>var a  =  1;" ) );
		$this->assertSame( "<textarea>  a  ", $this->min( "<textarea>  a  " ) );
	}

	public function test_less_than_in_text() {
		$this->assertSame( '<p>a < b and 3<4</p>', $this->min( '<p>a  <  b and 3<4</p>' ) );
	}

	public function test_json_ld_minified_and_invalid_json_untouched() {
		$in = "<script type=\"application/ld+json\">\n{\n  \"@context\": \"https://schema.org\",\n  \"name\": \"A  B\"\n}\n</script>";
		$this->assertSame( '<script type="application/ld+json">{"@context":"https://schema.org","name":"A  B"}</script>', $this->min( $in ) );
		$bad = "<script type=\"application/json\">{ a: 1 }</script>";
		$this->assertSame( $bad, $this->min( $bad ) );
		$esc = "<script type=\"application/json\">{ \"a\": \"x\\\" \\\\  y\" }</script>";
		$this->assertSame( '<script type="application/json">{"a":"x\\" \\\\  y"}</script>', $this->min( $esc ) );
	}

	public function test_template_scripts_untouched() {
		$t = "<script type=\"text/template\" id=\"t\">\n  <div>  {{ a }}  </div>\n</script>";
		$this->assertSame( $t, $this->min( $t ) );
	}

	public function test_inline_css_and_js_callbacks() {
		$opts = array(
			'css_callback' => static function ( $css ) {
				return AIOM_CSS_Minifier::minify( $css );
			},
			'js_callback'  => static function ( $js ) {
				return 'MIN';
			},
		);
		$this->assertSame( '<style>a{color:red}</style>', $this->min( "<style>\n a { color: red; }\n</style>", $opts ) );
		$this->assertSame( '<style media="print" type="text/css">a{b:c}</style>', $this->min( "<style media=\"print\" type=\"text/css\">a { b: c }</style>", $opts ) );
		$this->assertSame( '<script>MIN</script>', $this->min( '<script>var a = 1;</script>', $opts ) );
		$this->assertSame( '<script src="a.js"></script>', $this->min( '<script src="a.js"></script>', $opts ) );
		$this->assertSame( '<script type="text/x-template">  x  </script>', $this->min( '<script type="text/x-template">  x  </script>', $opts ) );
	}

	public function test_callback_cannot_inject_script_end() {
		$opts = array(
			'js_callback' => static function () {
				return 'a="</script><b>"';
			},
		);
		$this->assertSame( '<script>var a = 1;</script>', $this->min( '<script>var a = 1;</script>', $opts ) );
		$opts = array(
			'js_callback' => static function () {
				throw new RuntimeException( 'boom' );
			},
		);
		$this->assertSame( '<script>var a = 1;</script>', $this->min( '<script>var a = 1;</script>', $opts ), 'Exceptions in callbacks keep the original' );
	}

	public function test_svg_text_preserved_and_cdata() {
		$svg = "<svg viewBox=\"0 0 10 10\"><text x=\"0\">  a   b  </text><style><![CDATA[ .a  { } ]]></style></svg>";
		$out = $this->min( $svg );
		$this->assertContains( '<text x="0">  a   b  </text>', $out );
		$this->assertContains( '<style><![CDATA[ .a  { } ]]></style>', $out );
	}

	public function test_preserve_by_class_and_inline_style() {
		$in = "<div class=\"a keep-ws\">  x   <span> y  </span>  </div> <div>  z  </div>";
		$this->assertSame( "<div class=\"a keep-ws\">  x   <span> y  </span>  </div> <div> z </div>", $this->min( $in, array( 'preserve_classes' => array( 'keep-ws' ) ) ) );
		$in2 = "<div style=\"white-space: pre-wrap\">  a  <div>  b  </div>  </div>";
		$this->assertSame( $in2, $this->min( $in2 ) );
	}

	public function test_noscript_and_iframe_raw() {
		$n = "<noscript>  <img   src=\"x\">  </noscript>";
		$this->assertSame( $n, $this->min( $n ) );
	}

	public function test_aggressive_block_whitespace() {
		$opts = array( 'collapse_block_whitespace' => true );
		$this->assertSame( '<div><div>x</div><p>y</p></div>', $this->min( "<div>\n  <div>x</div>\n  <p>y</p>\n</div>", $opts ) );
		$this->assertSame( "<span>a</span>\n<div>b</div>", $this->min( "<span>a</span>\n  <div>b</div>", $opts ) );
		$this->assertSame( "<ul>\n<li>a</li>\n<li>b</li>\n</ul>", $this->min( "<ul>\n  <li>a</li>\n  <li>b</li>\n</ul>", $opts ), 'list items may be inline-block (tabs, menus)' );
		$this->assertSame( "<div>\n<div>x</div>\n</div>", $this->min( "<div>\n  <div>x</div>\n</div>" ), 'Default mode keeps one whitespace character' );
	}

	public function test_no_options_is_identity() {
		$in   = "<!DOCTYPE html>\n<html>\n <head><title>x</title></head>\n<body>\n<!-- c -->\n<p   class=\"a\" >  t  </p>\n</body>\n</html>\n";
		$opts = array(
			'collapse_whitespace' => false,
			'remove_comments'     => false,
			'minify_json'         => false,
		);
		$this->assertSame( $in, $this->min( $in, $opts ) );
	}

	public function test_script_callback_rewrites_open_tag() {
		$opts = array(
			'script_callback' => static function ( $s ) {
				if ( isset( $s['attrs']['src'] ) && false !== strpos( $s['attrs']['src'], 'analytics' ) ) {
					return '<script type="text/plain" data-aiom-delay src="' . $s['attrs']['src'] . '">';
				}
				return null;
			},
		);
		$out = $this->min( '<script src="https://x/analytics.js?a=1&amp;b=2"></script><script src="app.js"></script>', $opts );
		$this->assertSame( '<script type="text/plain" data-aiom-delay src="https://x/analytics.js?a=1&b=2"></script><script src="app.js"></script>', $out );
	}

	public function test_plaintext_and_bogus() {
		$this->assertSame( "<p>a</p> <plaintext>  x  <b>  ", $this->min( "<p>a</p>   <plaintext>  x  <b>  " ) );
		$this->assertSame( 'a<?php echo 1; ?>b', $this->min( 'a<?php echo 1; ?>b' ) );
		$this->assertSame( 'a</ x >b', $this->min( 'a</ x >b' ) );
		$this->assertSame( 'a</>b', $this->min( 'a</>b' ) );
	}

	public function test_idempotent() {
		$in    = "<!DOCTYPE html>\n<html><head>\n<style> a { b : c } </style></head>\n<body>\n<p>a   <b> b </b>  c</p>\n<pre> x </pre>\n<script> var a = 1; </script>\n</body></html>";
		$once  = $this->min( $in );
		$twice = $this->min( $once );
		$this->assertSame( $once, $twice );
	}

	public function test_parse_attrs() {
		$a = AIOM_HTML_Minifier::parse_attrs( ' src="a.js?x=1&amp;y=2" async  data-x=\'q\' TYPE=module id=a id=b' );
		$this->assertSame( 'a.js?x=1&y=2', $a['src'] );
		$this->assertSame( '', $a['async'] );
		$this->assertSame( 'q', $a['data-x'] );
		$this->assertSame( 'module', $a['type'] );
		$this->assertSame( 'a', $a['id'] );
	}

	public function test_large_document_performance() {
		$chunk = "<div class=\"card\">\n    <h2 class=\"title\">  Title   here </h2>\n    <p>Lorem   ipsum <a href=\"/x?a=1&amp;b=2\" data-foo=\"bar\">dolor</a> sit amet.</p>\n    <!-- card end -->\n</div>\n";
		$html  = '<!DOCTYPE html><html><head><title>x</title></head><body>' . str_repeat( $chunk, 1500 ) . '</body></html>';
		$t     = microtime( true );
		$out   = $this->min( $html );
		$ms    = ( microtime( true ) - $t ) * 1000;
		$this->assertTrue( strlen( $out ) < strlen( $html ), 'output should be smaller' );
		$this->assertTrue( $ms < 400, sprintf( '%.1f KB took %.1f ms', strlen( $html ) / 1024, $ms ) );
	}
}
