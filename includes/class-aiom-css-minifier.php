<?php
/**
 * Conservative, tokenizer-based CSS minifier.
 *
 * Only transformations that cannot change meaning are applied:
 * - comments are removed (except "/*!" licence comments, by default);
 * - whitespace runs become one space, and are removed only next to "{ } ; , ( )"
 *   and after ":" — never around combinators or arithmetic operators;
 * - the last ";" in a block is dropped.
 * Strings, escapes (including the whitespace that terminates a hex escape),
 * url() tokens and custom property values are copied exactly. Unterminated
 * strings/comments raise an exception so the caller serves the original file.
 *
 * When a stylesheet is moved to the cache directory, relative url() references,
 * image-set() strings and @import strings are rewritten against the original location.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * CSS minifier. Has no WordPress dependencies.
 */
class AIOM_CSS_Minifier {

	const WS = " \t\n\r\f";

	/**
	 * Characters that need individual handling; everything else is copied in bulk.
	 */
	const SPECIAL = " \t\n\r\f\"'/\\(){};";

	/**
	 * Source.
	 *
	 * @var string
	 */
	private $s;

	/**
	 * Source length.
	 *
	 * @var int
	 */
	private $len;

	/**
	 * Output.
	 *
	 * @var string
	 */
	private $out = '';

	/**
	 * Whether the last output character belongs to an escape sequence.
	 *
	 * @var bool
	 */
	private $last_escaped = false;

	/**
	 * Names of open functions/parentheses.
	 *
	 * @var array
	 */
	private $fn = array();

	/**
	 * Open blocks: array( bool is_font_face, int output offset ).
	 *
	 * @var array
	 */
	private $blocks = array();

	/**
	 * Output offset where the current prelude/declaration started.
	 *
	 * @var int
	 */
	private $prelude = 0;

	/**
	 * While inside a custom property value: array( block depth, paren depth ), else null.
	 * Custom property values are token sequences exposed verbatim to JavaScript,
	 * so whitespace inside them is only collapsed, never removed.
	 *
	 * @var array|null
	 */
	private $custom = null;

	/**
	 * Options.
	 *
	 * @var array
	 */
	private $o;

	/**
	 * Number of URLs rewritten.
	 *
	 * @var int
	 */
	public $rewritten = 0;

	/**
	 * Minify CSS.
	 *
	 * @param string $css  Stylesheet.
	 * @param array  $opts {
	 *     @type bool        $keep_important_comments Keep "/*!" comments. Default true.
	 *     @type string|null $rewrite_base            Directory URL (ending in "/") the stylesheet was loaded from.
	 *     @type string      $font_display            Value to add to @font-face rules lacking font-display ('' = off).
	 * }
	 * @return string
	 * @throws AIOM_Minify_Exception On unterminated strings, comments or url().
	 */
	public static function minify( $css, array $opts = array() ) {
		$m = new self( $css, $opts );
		return $m->run();
	}

	/**
	 * Constructor.
	 *
	 * @param string $css  Stylesheet.
	 * @param array  $opts Options.
	 */
	public function __construct( $css, array $opts = array() ) {
		$this->s   = (string) $css;
		$this->len = strlen( $this->s );
		$this->o   = array_merge(
			array(
				'keep_important_comments' => true,
				'rewrite_base'            => null,
				'font_display'            => '',
			),
			$opts
		);
	}

	/**
	 * Run the minifier.
	 *
	 * @return string
	 * @throws AIOM_Minify_Exception On malformed input.
	 */
	public function run() {
		$s   = $this->s;
		$len = $this->len;
		$i   = 0;
		while ( $i < $len ) {
			$n = strcspn( $s, self::SPECIAL, $i );
			if ( $n > 0 ) {
				$chunk = substr( $s, $i, $n );
				if ( null === $this->custom && $this->blocks && false !== ( $colon = strpos( $chunk, ':' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
					$decl = trim( substr( $this->out, $this->prelude ) . substr( $chunk, 0, $colon ) );
					if ( 0 === strncmp( $decl, '--', 2 ) && false === strpbrk( $decl, " \t\n\r\f(" ) ) {
						$this->custom = array( count( $this->blocks ), count( $this->fn ) );
					}
				}
				$this->out         .= $chunk;
				$this->last_escaped = false;
				$i                 += $n;
				if ( $i >= $len ) {
					break;
				}
			}
			$c = $s[ $i ];
			switch ( $c ) {
				case ' ':
				case "\t":
				case "\n":
				case "\r":
				case "\f":
					$j = $i + strspn( $s, self::WS, $i );
					$this->whitespace( $j );
					$i = $j;
					break;

				case '"':
				case "'":
					$j   = $this->string_end( $i );
					$str = substr( $s, $i, $j - $i );
					if ( null !== $this->o['rewrite_base'] && $this->string_is_url() ) {
						$str = $this->rewrite_quoted( $str );
					}
					$this->out         .= $str;
					$this->last_escaped = false;
					$i                  = $j;
					break;

				case '/':
					if ( isset( $s[ $i + 1 ] ) && '*' === $s[ $i + 1 ] ) {
						$i = $this->comment( $i );
					} else {
						$this->out         .= '/';
						$this->last_escaped = false;
						++$i;
					}
					break;

				case '\\':
					$i = $this->escape( $i );
					break;

				case '(':
					$name = '';
					if ( ! $this->last_escaped && preg_match( '/([-A-Za-z0-9_]+)$/', substr( $this->out, -40 ), $fm ) ) {
						$name = strtolower( $fm[1] );
					}
					if ( 'url' === $name ) {
						$i = $this->url_token( $i );
					} else {
						$this->fn[]         = $name;
						$this->out         .= '(';
						$this->last_escaped = false;
						++$i;
					}
					break;

				case ')':
					array_pop( $this->fn );
					$this->out         .= ')';
					$this->last_escaped = false;
					++$i;
					break;

				case '{':
					$prelude            = strtolower( trim( substr( $this->out, $this->prelude ) ) );
					$this->out         .= '{';
					$this->blocks[]     = array( '@font-face' === substr( $prelude, -10 ), strlen( $this->out ) );
					$this->prelude      = strlen( $this->out );
					$this->last_escaped = false;
					++$i;
					break;

				case '}':
					$this->close_block();
					++$i;
					break;

				case ';':
					if ( null !== $this->custom && count( $this->blocks ) === $this->custom[0] && count( $this->fn ) <= $this->custom[1] ) {
						$this->custom = null;
					}
					$this->out         .= ';';
					$this->prelude      = strlen( $this->out );
					$this->last_escaped = false;
					++$i;
					break;
			}
		}
		return $this->out;
	}

	/**
	 * Handle "}": drop a trailing ";", optionally add font-display.
	 */
	private function close_block() {
		$inner_custom = null !== $this->custom && count( $this->blocks ) > $this->custom[0];
		if ( null !== $this->custom && ! $inner_custom ) {
			$this->custom = null;
		}
		$l = strlen( $this->out );
		if ( $l > 0 && ';' === $this->out[ $l - 1 ] && ! $this->last_escaped && ! $inner_custom ) {
			$this->out = substr( $this->out, 0, -1 );
		}
		$block = array_pop( $this->blocks );
		if ( $block && $block[0] && '' !== $this->o['font_display'] && false === stripos( substr( $this->out, $block[1] ), 'font-display' ) ) {
			$l    = strlen( $this->out );
			$last = $l > 0 ? $this->out[ $l - 1 ] : '';
			if ( '{' !== $last && ';' !== $last ) {
				$this->out .= ';';
			}
			$this->out .= 'font-display:' . $this->o['font_display'];
		}
		$this->out         .= '}';
		$this->prelude      = strlen( $this->out );
		$this->last_escaped = false;
	}

	/**
	 * Decide whether a whitespace run is needed.
	 *
	 * @param int $j Position after the whitespace.
	 */
	private function whitespace( $j ) {
		$l = strlen( $this->out );
		if ( 0 === $l || $j >= $this->len ) {
			return;
		}
		$prev = $this->out[ $l - 1 ];
		$next = $this->s[ $j ];
		if ( null !== $this->custom && ! ( ':' === $prev && ! $this->last_escaped ) ) {
			// Inside a custom property value: collapse only; trim before the value ends.
			$ends = ( ';' === $next || '}' === $next ) && count( $this->blocks ) === $this->custom[0];
			if ( ! $ends && ( ' ' !== $prev || $this->last_escaped ) && ! ( '!' === $next && preg_match( '/\G![ \t\n\r\f]*+important/i', $this->s, $mm, 0, $j ) ) ) {
				$this->out .= ' ';
			}
			return;
		}
		if ( ! $this->last_escaped ) {
			if ( ' ' === $prev || false !== strpos( '{};,(', $prev ) ) {
				return;
			}
			if ( '!' === $prev && 0 === substr_compare( $this->s, 'important', $j, 9, true ) ) {
				return;
			}
			// After ":" in a declaration or a media/supports feature, whitespace is insignificant.
			// In a top-level selector it is not ("a: b" is invalid; "a:b" would suddenly match).
			if ( ':' === $prev && ( $this->blocks || $this->fn ) ) {
				// "--empty: ;" keeps its whitespace value.
				if ( ';' === $next || '}' === $next ) {
					$this->out .= ' ';
				}
				return;
			}
		}
		if ( false !== strpos( '{};,)', $next ) ) {
			return;
		}
		if ( '!' === $next && preg_match( '/\G![ \t\n\r\f]*+important/i', $this->s, $mm, 0, $j ) ) {
			return;
		}
		$this->out .= ' ';
	}

	/**
	 * Find the end of a quoted string.
	 *
	 * @param int $i Position of the opening quote.
	 * @return int Position after the closing quote.
	 * @throws AIOM_Minify_Exception On unterminated strings.
	 */
	private function string_end( $i ) {
		$q = $this->s[ $i ];
		$j = $i + 1;
		while ( true ) {
			$j += strcspn( $this->s, $q . "\\\n\r\f", $j );
			if ( $j >= $this->len ) {
				throw new AIOM_Minify_Exception( 'Unterminated CSS string' );
			}
			$ch = $this->s[ $j ];
			if ( $ch === $q ) {
				return $j + 1;
			}
			if ( '\\' === $ch ) {
				$j += 2;
				continue;
			}
			throw new AIOM_Minify_Exception( 'Unterminated CSS string (newline)' );
		}
	}

	/**
	 * Remove (or keep) a comment.
	 *
	 * @param int $i Position of "/*".
	 * @return int Position after the comment.
	 * @throws AIOM_Minify_Exception On unterminated comments.
	 */
	private function comment( $i ) {
		$end = strpos( $this->s, '*/', $i + 2 );
		if ( false === $end ) {
			throw new AIOM_Minify_Exception( 'Unterminated CSS comment' );
		}
		$after = $end + 2;
		if ( $this->o['keep_important_comments'] && isset( $this->s[ $i + 2 ] ) && '!' === $this->s[ $i + 2 ] ) {
			$this->out         .= substr( $this->s, $i, $after - $i );
			$this->last_escaped = false;
			return $after;
		}
		// A comment separates tokens: "a/**/b" must not become "ab".
		$l = strlen( $this->out );
		if ( $l > 0 && $after < $this->len ) {
			$prev = $this->out[ $l - 1 ];
			$next = $this->s[ $after ];
			if ( ( $this->last_escaped || self::is_word( $prev ) ) && ( self::is_word( $next ) || '\\' === $next ) ) {
				$this->out         .= '/**/';
				$this->last_escaped = false;
			}
		}
		return $after;
	}

	/**
	 * Copy an escape sequence exactly, including a hex escape's terminating whitespace.
	 *
	 * @param int $i Position of the backslash.
	 * @return int Position after the escape.
	 */
	private function escape( $i ) {
		$j = $i + 1;
		if ( $j < $this->len ) {
			$hex = strspn( $this->s, '0123456789abcdefABCDEF', $j, 6 );
			if ( $hex > 0 ) {
				$j += $hex;
				if ( $j < $this->len && false !== strpos( self::WS, $this->s[ $j ] ) ) {
					$j += ( "\r" === $this->s[ $j ] && isset( $this->s[ $j + 1 ] ) && "\n" === $this->s[ $j + 1 ] ) ? 2 : 1;
				}
			} else {
				++$j;
			}
		}
		$this->out         .= substr( $this->s, $i, $j - $i );
		$this->last_escaped = true;
		return $j;
	}

	/**
	 * Copy a url( ... ) token, rewriting a relative URL when requested.
	 *
	 * @param int $i Position of "(".
	 * @return int Position after ")".
	 * @throws AIOM_Minify_Exception On unterminated url().
	 */
	private function url_token( $i ) {
		$j  = $i + 1;
		$j += strspn( $this->s, self::WS, $j );
		if ( $j < $this->len && ( '"' === $this->s[ $j ] || "'" === $this->s[ $j ] ) ) {
			$end = $this->string_end( $j );
			$str = substr( $this->s, $j, $end - $j );
			$k   = $end + strspn( $this->s, self::WS, $end );
			if ( $k < $this->len && ')' === $this->s[ $k ] ) {
				if ( null !== $this->o['rewrite_base'] ) {
					$str = $this->rewrite_quoted( $str );
				}
				$this->out         .= '(' . $str . ')';
				$this->last_escaped = false;
				return $k + 1;
			}
			// url( "x" junk ): not a plain URL. Treat as an ordinary function.
			$this->fn[]         = 'url';
			$this->out         .= '(';
			$this->last_escaped = false;
			return $i + 1;
		}
		$k = $j;
		while ( $k < $this->len && ')' !== $this->s[ $k ] ) {
			$k += ( '\\' === $this->s[ $k ] ) ? 2 : 1;
		}
		if ( $k >= $this->len ) {
			throw new AIOM_Minify_Exception( 'Unterminated CSS url()' );
		}
		$val = rtrim( substr( $this->s, $j, $k - $j ), self::WS );
		if ( null !== $this->o['rewrite_base'] && false === strpos( $val, '\\' ) ) {
			$new = $this->resolve( $val );
			if ( $new !== $val ) {
				$val = preg_match( '/[\s()\'"]/', $new ) ? '"' . addcslashes( $new, '"\\' ) . '"' : $new;
			}
		}
		$this->out         .= '(' . $val . ')';
		$this->last_escaped = false;
		return $k + 1;
	}

	/**
	 * Whether a string at the current position is a URL (image-set() argument or @import target).
	 *
	 * @return bool
	 */
	private function string_is_url() {
		$fn = end( $this->fn );
		if ( 'image-set' === $fn || '-webkit-image-set' === $fn ) {
			return true;
		}
		return (bool) preg_match( '/@import\s*$/i', substr( $this->out, -12 ) );
	}

	/**
	 * Rewrite a quoted URL string.
	 *
	 * @param string $str Quoted string including quotes.
	 * @return string
	 */
	private function rewrite_quoted( $str ) {
		$q   = $str[0];
		$val = substr( $str, 1, -1 );
		if ( false !== strpos( $val, '\\' ) ) {
			return $str;
		}
		$new = $this->resolve( $val );
		if ( $new === $val ) {
			return $str;
		}
		return $q . str_replace( $q, '\\' . $q, $new ) . $q;
	}

	/**
	 * Resolve a URL relative to the stylesheet's original directory.
	 *
	 * @param string $u URL.
	 * @return string
	 */
	public function resolve( $u ) {
		$u = trim( $u );
		if ( '' === $u || '#' === $u[0] || '/' === $u[0] || 0 === strpos( $u, '%23' ) || preg_match( '~^[a-z][a-z0-9+.\-]*:~i', $u ) ) {
			return $u;
		}
		$base   = (string) $this->o['rewrite_base'];
		$suffix = '';
		$cut    = strcspn( $u, '?#' );
		if ( $cut < strlen( $u ) ) {
			$suffix = substr( $u, $cut );
			$u      = substr( $u, 0, $cut );
		}
		$prefix = '';
		$path   = $base . $u;
		if ( preg_match( '~^((?:[a-z][a-z0-9+.\-]*:)?//[^/]*)(/.*)$~i', $path, $m ) ) {
			$prefix = $m[1];
			$path   = $m[2];
		}
		$segments = explode( '/', $path );
		$result   = array();
		$last     = count( $segments ) - 1;
		foreach ( $segments as $idx => $seg ) {
			if ( '.' === $seg || '..' === $seg ) {
				if ( '..' === $seg && count( $result ) > 1 ) {
					array_pop( $result );
				}
				if ( $idx === $last ) {
					$result[] = '';
				}
				continue;
			}
			$result[] = $seg;
		}
		++$this->rewritten;
		return $prefix . implode( '/', $result ) . $suffix;
	}

	/**
	 * Identifier-like character.
	 *
	 * @param string $c Character.
	 * @return bool
	 */
	private static function is_word( $c ) {
		return ( $c >= 'a' && $c <= 'z' ) || ( $c >= 'A' && $c <= 'Z' ) || ( $c >= '0' && $c <= '9' ) || '-' === $c || '_' === $c || '%' === $c || ord( $c ) >= 0x80;
	}
}
