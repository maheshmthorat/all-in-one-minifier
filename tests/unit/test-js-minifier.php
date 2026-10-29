<?php
/**
 * JavaScript minifier regression tests.
 *
 * @package All_In_One_Minifier
 */

/**
 * JS tests.
 */
class Test_JS_Minifier extends AIOM_Test_Case {

	private function min( $js, $module = false ) {
		return AIOM_JS_Minifier::minify( $js, $module );
	}

	public function test_basic() {
		$this->assertSame( 'var a=1;function f(x){return x+1;}', $this->min( "var a = 1;\n// comment\nfunction f ( x ) {\n  /* block */\n  return x + 1 ;\n}\n" ) );
	}

	public function test_asi_hazards_are_resolved_by_the_parser() {
		$this->assertSame( 'a;++b;', $this->min( "a\n++b" ) );
		$this->assertSame( 'function f(){return;x;}', $this->min( "function f() {\n return\n x\n}" ) );
		$this->assertSame( 'var a=b;(c||d).e();', $this->min( "var a = b;\n(c || d).e()" ) );
		$this->assertSame( 'a=b+ +c;d=e- -f;g=h+ ++i;', $this->min( 'a = b + +c; d = e - -f; g = h + ++i;' ) );
	}

	public function test_strings_templates_regex_kept_raw() {
		$this->assertSame( "var s='a  b\\n',t=\"\x04\",u=`x\t\${a+`y \${b}`}  z`;", $this->min( "var s = 'a  b\\n', t = \"\x04\", u = `x\t\${ a + `y \${b}` }  z`;" ) );
		$this->assertSame( 'var r=/[/]\/ *x/gi,d=a/b/c;', $this->min( 'var r = /[/]\/ *x/gi, d = a / b / c;' ) );
		$this->assertSame( 'var x="<\/script>";', $this->min( 'var x = "<\/script>";' ) );
		$this->assertSame( 'var n=0x1F+1e3+.5+1_000n;', $this->min( 'var n = 0x1F + 1e3 + .5 + 1_000n;' ) );
	}

	public function test_structure_preserved() {
		$this->assertSame( 'if(a){b();}else if(c)d();else{e();}', $this->min( "if (a) { b(); } else if (c) d(); else { e(); }" ) );
		$this->assertSame( 'switch(x){case 1:{let a=1;f(a);}case 2:g();h();}', $this->min( "switch (x) { case 1: { let a = 1; f(a); } case 2: g(); h(); }" ) );
		$this->assertSame( 'switch(x){case 1:let a=1;case 2:f(a);}', $this->min( "switch (x) { case 1: let a = 1; case 2: f(a); }" ), 'let in a case must not be wrapped in a new block' );
		$this->assertSame( 'var o={a:a,b,c:1,[d]:d,e(){}};', $this->min( 'var o = { a: a, b, c: 1, [d]: d, e() {} };' ), 'no new shorthand properties; computed keys keep their value' );
		$this->assertSame( 'var {[a]:a=1,b:c=2,d=3}=o;', $this->min( 'var { [a]: a = 1, b: c = 2, d = 3 } = o;' ), 'destructuring defaults keep computed keys' );
		$this->assertSame( 'l:for(;;){break l;}', $this->min( "l: for (;;) { break l; }" ) );
	}

	public function test_peast_rendering_bugs_fixed() {
		$this->assertSame( 'a=(10).toString()+(1_0).x+1..y+1.5.z+0x1F.w;', $this->min( 'a = (10).toString() + 1_0 .x + 1..y + 1.5.z + 0x1F.w;' ), 'integer member access' );
		$this->assertSame( 'a=[,];b=[x,,];c=[,y];d=[];', $this->min( 'a = [,]; b = [x,,]; c = [,y]; d = [];' ), 'array holes keep length' );
		$this->assertSame( 'o={formats:[,],n:{m:[[,],10..x]}};', $this->min( 'o = { formats: [,], n: { m: [[,], 10..x] } };' ), 'fixes apply inside object properties' );
		$this->assertSame( 'var [,,c]=z;', $this->min( 'var [, , c, ] = z;' ), 'a trailing comma after an element is not a hole' );
		$this->assertSame( '{{let a=1;}}', $this->min( '{ { let a = 1; } }' ), 'nested blocks' );
		$this->assertSame( '{const x=1;f(x);}', $this->min( "{\n  const x = 1;\n  f(x);\n}" ), 'a program that is one block keeps its scope' );
		$this->assertSame( '{const x=1;}', $this->min( '{ const x = 1; }' ) );
		$this->assertSame( 'function f(){{g();}}', $this->min( 'function f() { { g(); } }' ) );
		$this->assertSame( 'a();b();', $this->min( "a()\n;\n;b();;" ), 'empty statements in lists are dropped' );
		$this->assertSame( 'if(a);else b();', $this->min( 'if (a) ; else b();' ), 'an empty statement as a body is kept' );
		$this->assertSame( 'for(;;);', $this->min( 'for (;;) ;' ) );
	}

	public function test_modern_syntax() {
		$this->assertSame( 'class A extends B{#p=1;static{x();}async *g(){yield await 1;}}', $this->min( "class A extends B { #p = 1; static { x(); } async *g() { yield await 1; } }" ) );
		$this->assertSame( 'const f=async(a,...b)=>a?.c??b;', $this->min( 'const f = async (a, ...b) => a?.c ?? b;' ) );
		$this->assertSame( 'import x from "y";export default x;', $this->min( 'import x from "y"; export default x;', true ) );
	}

	public function test_directives_kept() {
		$this->assertSame( '(function(){"use strict";a();})();', $this->min( "(function () {\n  \"use strict\";\n  a();\n})();" ) );
	}

	public function test_syntax_errors_throw() {
		$this->assertThrows(
			'AIOM_Minify_Exception',
			function () {
				$this->min( 'var a = ;' );
			}
		);
		$this->assertThrows(
			'AIOM_Minify_Exception',
			function () {
				$this->min( 'const a = <div>JSX</div>;' );
			}
		);
		$this->assertThrows(
			'AIOM_Minify_Exception',
			function () {
				$this->min( "var s = '\xE9t\xE9';" );
			}
		);
	}

	public function test_empty_and_comment_only() {
		$this->assertSame( '', $this->min( "// only a comment\n/* x */" ) );
		$this->assertSame( "  \n", $this->min( "  \n" ) );
	}
}
