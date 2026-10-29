<?php
/**
 * Spec-aware HTML minifier.
 *
 * This is a tokenizer, not a chain of search/replace expressions. It follows the
 * HTML tokenization rules for tags, quoted/unquoted attribute values, comments,
 * raw-text elements (script, style, textarea, ...) and the script "escaped"
 * states, so element boundaries are found exactly where a browser finds them.
 *
 * Transformations (all optional):
 * - collapse runs of ASCII whitespace in text to a single space or newline
 *   (never removed entirely between inline content);
 * - drop whitespace that is never rendered (inside <head>, around <html>/<body>);
 * - remove comments, except conditional comments and framework/server markers;
 * - normalise whitespace between attributes (attribute values are untouched);
 * - minify inline JSON, and inline CSS/JS through callbacks.
 *
 * Content of pre, code, textarea, listing, SVG text and elements with a
 * preserved class or inline white-space:pre style is passed through verbatim.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTML tokenizer/minifier. Has no WordPress dependencies.
 */
class AIOM_HTML_Minifier {

	const WS = " \t\n\r\f";

	/**
	 * Start tag: name, attributes (quote-aware, per the HTML tokenizer states), self-closing slash.
	 */
	const START_TAG = '/\G<([a-zA-Z][^\t\n\f\r \/>]*+)((?:[\t\n\f\r ]++|\/(?!>)|(?:=|[^\t\n\f\r \/>=])[^\t\n\f\r \/>=]*+(?:[\t\n\f\r ]*+=[\t\n\f\r ]*+(?:"[^"]*+"|\'[^\']*+\'|[^\t\n\f\r >]++))?)*+)(\/?)>/';

	/**
	 * End tag. Attributes on end tags are ignored by browsers, but they still affect where the tag ends.
	 */
	const END_TAG = '/\G<\/([a-zA-Z][^\t\n\f\r \/>]*+)(?:[\t\n\f\r ]++|\/(?!>)|(?:=|[^\t\n\f\r \/>=])[^\t\n\f\r \/>=]*+(?:[\t\n\f\r ]*+=[\t\n\f\r ]*+(?:"[^"]*+"|\'[^\']*+\'|[^\t\n\f\r >]++))?)*+\/?>/';

	/**
	 * Elements whose content is raw text and is never tokenized as markup.
	 *
	 * @var array
	 */
	private static $raw_text = array(
		'script'   => 1,
		'style'    => 1,
		'textarea' => 1,
		'title'    => 1,
		'xmp'      => 1,
		'iframe'   => 1,
		'noembed'  => 1,
		'noframes' => 1,
		'noscript' => 1,
	);

	/**
	 * Elements whose descendant text keeps its exact whitespace.
	 *
	 * @var array
	 */
	private static $preserve_tags = array(
		'pre'     => 1,
		'code'    => 1,
		'listing' => 1,
	);

	/**
	 * Void elements never have content, so they never open a preserved region.
	 *
	 * @var array
	 */
	private static $void = array(
		'area'   => 1,
		'base'   => 1,
		'br'     => 1,
		'col'    => 1,
		'embed'  => 1,
		'hr'     => 1,
		'img'    => 1,
		'input'  => 1,
		'link'   => 1,
		'meta'   => 1,
		'source' => 1,
		'track'  => 1,
		'wbr'    => 1,
	);

	/**
	 * Elements that may appear in <head>. Any other start tag implies <body>.
	 *
	 * @var array
	 */
	private static $head_tags = array(
		'head'     => 1,
		'meta'     => 1,
		'link'     => 1,
		'title'    => 1,
		'style'    => 1,
		'script'   => 1,
		'base'     => 1,
		'noscript' => 1,
		'template' => 1,
	);

	/**
	 * Whitespace directly before these tokens is never rendered.
	 *
	 * @var array
	 */
	private static $drop_before = array(
		'html'  => 1,
		'head'  => 1,
		'/head' => 1,
		'body'  => 1,
		'/body' => 1,
		'/html' => 1,
		'#end'  => 1,
	);

	/**
	 * Whitespace directly after these tokens is never rendered.
	 *
	 * @var array
	 */
	private static $drop_after = array(
		'#start'   => 1,
		'!doctype' => 1,
		'html'     => 1,
		'/head'    => 1,
		'body'     => 1,
		'/body'    => 1,
		'/html'    => 1,
	);

	/**
	 * Block-level elements for the optional aggressive whitespace removal.
	 * <li> is deliberately absent: menus and tabs commonly render list items as
	 * inline-block, where the whitespace between them is a visible gap.
	 *
	 * @var array
	 */
	private static $block = array(
		'address'    => 1,
		'article'    => 1,
		'aside'      => 1,
		'base'       => 1,
		'blockquote' => 1,
		'body'       => 1,
		'caption'    => 1,
		'col'        => 1,
		'colgroup'   => 1,
		'dd'         => 1,
		'details'    => 1,
		'dialog'     => 1,
		'div'        => 1,
		'dl'         => 1,
		'dt'         => 1,
		'fieldset'   => 1,
		'figcaption' => 1,
		'figure'     => 1,
		'footer'     => 1,
		'form'       => 1,
		'h1'         => 1,
		'h2'         => 1,
		'h3'         => 1,
		'h4'         => 1,
		'h5'         => 1,
		'h6'         => 1,
		'head'       => 1,
		'header'     => 1,
		'hgroup'     => 1,
		'hr'         => 1,
		'html'       => 1,
		'legend'     => 1,
		'link'       => 1,
		'main'       => 1,
		'meta'       => 1,
		'nav'        => 1,
		'noscript'   => 1,
		'ol'         => 1,
		'optgroup'   => 1,
		'option'     => 1,
		'p'          => 1,
		'script'     => 1,
		'section'    => 1,
		'style'      => 1,
		'summary'    => 1,
		'table'      => 1,
		'tbody'      => 1,
		'td'         => 1,
		'template'   => 1,
		'tfoot'      => 1,
		'th'         => 1,
		'thead'      => 1,
		'title'      => 1,
		'tr'         => 1,
		'ul'         => 1,
	);

	/**
	 * Comments starting with these prefixes carry meaning (IE conditionals, ESI,
	 * Knockout, Vue/Svelte hydration markers, Lit, SSI, cache-plugin markers...).
	 *
	 * @var array
	 */
	private static $keep_comment_prefixes = array(
		'[if',
		'<![endif',
		'[endif',
		'[',
		']',
		'#',
		'esi',
		'/esi',
		'ko ',
		'/ko',
		'?lit$',
		'noindex',
		'/noindex',
		'googleoff',
		'googleon',
		'aiom-keep',
		'mfunc',
		'/mfunc',
		'mclude',
		'/mclude',
		'dynamic-cached-content',
		'/dynamic-cached-content',
		'wp:',
		'/wp:',
	);

	/**
	 * Script types treated as executable JavaScript.
	 *
	 * @var array
	 */
	private static $js_types = array(
		''                         => 1,
		'text/javascript'          => 1,
		'application/javascript'   => 1,
		'module'                   => 1,
		'text/ecmascript'          => 1,
		'application/ecmascript'   => 1,
		'application/x-javascript' => 1,
		'text/x-javascript'        => 1,
		'text/jscript'             => 1,
		'text/livescript'          => 1,
	);

	/**
	 * Script types that contain JSON data.
	 *
	 * @var array
	 */
	private static $json_types = array(
		'application/ld+json' => 1,
		'application/json'    => 1,
		'importmap'           => 1,
		'speculationrules'    => 1,
	);

	/**
	 * Options.
	 *
	 * @var array
	 */
	private $o;

	/**
	 * Output chunks.
	 *
	 * @var array
	 */
	private $out = array();

	/**
	 * Whitespace-only text waiting for the next token to decide its fate.
	 *
	 * @var string
	 */
	private $pending_ws = '';

	/**
	 * Key of the last emitted token ('div', '/div', '#text', '!--', ...).
	 *
	 * @var string
	 */
	private $prev = '#start';

	/**
	 * Whether the tokenizer is inside <head>.
	 *
	 * @var bool
	 */
	private $in_head = false;

	/**
	 * Whether <body> (explicit or implied) has started.
	 *
	 * @var bool
	 */
	private $in_body = false;

	/**
	 * Depth of SVG/MathML foreign content.
	 *
	 * @var int
	 */
	private $foreign = 0;

	/**
	 * Stack of open whitespace-preserving elements: array( name, nesting depth ).
	 *
	 * @var array
	 */
	private $preserve = array();

	/**
	 * Counters describing what was done.
	 *
	 * @var array
	 */
	public $stats = array();

	/**
	 * Constructor.
	 *
	 * @param array $options {
	 *     @type bool     $collapse_whitespace       Collapse whitespace in text and tags. Default true.
	 *     @type bool     $remove_comments           Remove non-functional comments. Default true.
	 *     @type string[] $keep_comments             Extra comment prefixes to keep.
	 *     @type bool     $collapse_block_whitespace Remove whitespace between two block-level tags. Default false.
	 *     @type string[] $preserve_classes          Class names whose elements keep exact whitespace.
	 *     @type bool     $minify_json               Remove whitespace from JSON script blocks. Default true.
	 *     @type callable $css_callback              function( string $css ): string for inline <style>.
	 *     @type callable $js_callback               function( string $js, bool $is_module ): string for inline <script>.
	 *     @type callable $script_callback           function( array $script ): ?string, may return a replacement opening tag.
	 * }
	 */
	public function __construct( array $options = array() ) {
		$this->o = array_merge(
			array(
				'collapse_whitespace'       => true,
				'remove_comments'           => true,
				'keep_comments'             => array(),
				'collapse_block_whitespace' => false,
				'preserve_classes'          => array(),
				'minify_json'               => true,
				'css_callback'              => null,
				'js_callback'               => null,
				'script_callback'           => null,
			),
			$options
		);
	}

	/**
	 * Process a full HTML document or fragment.
	 *
	 * @param string $html Input markup.
	 * @return string
	 * @throws AIOM_Minify_Exception When the regular expression engine fails.
	 */
	public function process( $html ) {
		$this->out        = array();
		$this->pending_ws = '';
		$this->prev       = '#start';
		$this->in_head    = false;
		$this->in_body    = false;
		$this->foreign    = 0;
		$this->preserve   = array();
		$this->stats      = array(
			'comments_removed' => 0,
			'inline_css'       => 0,
			'inline_js'        => 0,
			'json'             => 0,
			'scripts'          => 0,
		);

		$len = strlen( $html );
		$pos = 0;
		while ( $pos < $len ) {
			$lt = strpos( $html, '<', $pos );
			if ( false === $lt ) {
				$this->text( substr( $html, $pos ) );
				break;
			}
			if ( $lt > $pos ) {
				$this->text( substr( $html, $pos, $lt - $pos ) );
			}
			$next = isset( $html[ $lt + 1 ] ) ? $html[ $lt + 1 ] : '';
			if ( '!' === $next ) {
				$pos = $this->markup_declaration( $html, $lt );
			} elseif ( '/' === $next ) {
				$pos = $this->end_tag( $html, $lt );
			} elseif ( '?' === $next ) {
				$pos = $this->verbatim_until( $html, $lt, '>', '!' );
			} elseif ( ( $next >= 'a' && $next <= 'z' ) || ( $next >= 'A' && $next <= 'Z' ) ) {
				$pos = $this->start_tag( $html, $lt );
			} else {
				$this->text( '<' );
				$pos = $lt + 1;
			}
		}
		$this->flush_ws( '#end' );

		return implode( '', $this->out );
	}

	/**
	 * Remove whitespace outside strings from a JSON document, if it is valid JSON.
	 *
	 * @param string $json JSON text.
	 * @return string
	 */
	public static function minify_json( $json ) {
		if ( false === strpbrk( $json, " \t\n\r" ) || strlen( $json ) > 1048576 ) {
			return $json;
		}
		$trimmed = trim( $json );
		if ( '' === $trimmed ) {
			return $json;
		}
		json_decode( $trimmed );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return $json;
		}
		$out = preg_replace_callback(
			'/"(?:[^"\\\\]++|\\\\.)*+"|[ \t\n\r]++/s',
			static function ( $m ) {
				return '"' === $m[0][0] ? $m[0] : '';
			},
			$trimmed
		);
		return null === $out ? $json : $out;
	}

	/**
	 * Collapse ASCII whitespace runs: to "\n" when the run contains a line break, otherwise to " ".
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function collapse( $s ) {
		if ( false === strpbrk( $s, "\t\n\r\f" ) && false === strpos( $s, '  ' ) ) {
			return $s;
		}
		$s = preg_replace( '/[ \t\f]*+[\r\n][ \t\n\r\f]*+/', "\n", $s );
		return preg_replace( '/[ \t\f]{2,}|[\t\f]/', ' ', $s );
	}

	/**
	 * Handle text between tags.
	 *
	 * @param string $t Text.
	 */
	private function text( $t ) {
		if ( $this->preserve || ! $this->o['collapse_whitespace'] ) {
			if ( '' !== $this->pending_ws ) {
				$this->out[]      = $this->pending_ws;
				$this->pending_ws = '';
			}
			$this->out[] = $t;
			$this->prev  = '#text';
			return;
		}
		if ( strspn( $t, self::WS ) === strlen( $t ) ) {
			$this->pending_ws .= $t;
			return;
		}
		if ( $this->in_head || ! $this->in_body ) {
			// Character data implicitly closes <head> and opens <body>.
			$this->flush_ws( '#text' );
			$this->in_head = false;
			$this->in_body = true;
		}
		if ( '' !== $this->pending_ws ) {
			$t                = $this->pending_ws . $t;
			$this->pending_ws = '';
		}
		$t = self::collapse( $t );
		if ( $this->after_ws_text() ) {
			// Text on both sides of a removed comment: keep a single whitespace character.
			$t = ltrim( $t, self::WS );
		}
		$this->out[] = $t;
		$this->prev  = '#text';
	}

	/**
	 * Whether the previous output is text ending in whitespace.
	 *
	 * @return bool
	 */
	private function after_ws_text() {
		if ( '#text' !== $this->prev || ! $this->out ) {
			return false;
		}
		$last = end( $this->out );
		return '' !== $last && false !== strpos( self::WS, substr( $last, -1 ) );
	}

	/**
	 * Emit or drop pending whitespace, now that the next token is known.
	 *
	 * @param string $next Key of the next token.
	 */
	private function flush_ws( $next ) {
		if ( '' === $this->pending_ws ) {
			return;
		}
		$ws               = $this->pending_ws;
		$this->pending_ws = '';
		// Once <body> is open (possibly implied by earlier content), a later <head>, </head>
		// or <body> tag is ignored by browsers and whitespace around it is rendered body text.
		$ignored = $this->in_body && ( 'head' === $next || '/head' === $next || 'body' === $next );
		if ( $this->in_head || ( isset( self::$drop_before[ $next ] ) && ! $ignored ) || isset( self::$drop_after[ $this->prev ] ) ) {
			return;
		}
		if ( $this->o['collapse_block_whitespace'] && isset( self::$block[ ltrim( $this->prev, '/' ) ], self::$block[ ltrim( $next, '/' ) ] ) ) {
			return;
		}
		if ( $this->after_ws_text() ) {
			return;
		}
		$this->out[] = ( false !== strpbrk( $ws, "\n\r" ) ) ? "\n" : ' ';
	}

	/**
	 * Handle "<!": comments, CDATA, doctype and bogus comments.
	 *
	 * @param string $html Document.
	 * @param int    $lt   Position of "<".
	 * @return int New position.
	 */
	private function markup_declaration( $html, $lt ) {
		if ( 0 === substr_compare( $html, '<!--', $lt, 4 ) ) {
			$s = $lt + 4;
			if ( isset( $html[ $s ] ) && '>' === $html[ $s ] ) {
				$end = $s + 1;
			} elseif ( 0 === substr_compare( $html, '->', $s, 2 ) ) {
				$end = $s + 2;
			} else {
				$e1 = strpos( $html, '-->', $s );
				$e2 = strpos( $html, '--!>', $s );
				if ( false !== $e2 && ( false === $e1 || $e2 < $e1 ) ) {
					$end = $e2 + 4;
				} elseif ( false !== $e1 ) {
					$end = $e1 + 3;
				} else {
					$end = strlen( $html );
				}
			}
			$this->comment( substr( $html, $lt, $end - $lt ) );
			return $end;
		}
		if ( $this->foreign && 0 === substr_compare( $html, '<![CDATA[', $lt, 9 ) ) {
			return $this->verbatim_until( $html, $lt, ']]>', '!' );
		}
		$key = ( 0 === substr_compare( $html, '<!doctype', $lt, 9, true ) ) ? '!doctype' : '!';
		return $this->verbatim_until( $html, $lt, '>', $key );
	}

	/**
	 * Emit everything from $lt to the end of $needle verbatim.
	 *
	 * @param string $html   Document.
	 * @param int    $lt     Start position.
	 * @param string $needle Terminator.
	 * @param string $key    Token key.
	 * @return int New position.
	 */
	private function verbatim_until( $html, $lt, $needle, $key ) {
		$e   = strpos( $html, $needle, $lt + 1 );
		$end = ( false === $e ) ? strlen( $html ) : $e + strlen( $needle );
		$this->flush_ws( $key );
		$this->out[] = substr( $html, $lt, $end - $lt );
		$this->prev  = $key;
		return $end;
	}

	/**
	 * Keep or drop a comment.
	 *
	 * @param string $c Full comment including delimiters.
	 */
	private function comment( $c ) {
		if ( ! $this->o['remove_comments'] || $this->keep_comment( $c ) ) {
			$this->flush_ws( '!--' );
			$this->out[] = $c;
			$this->prev  = '!--';
			return;
		}
		++$this->stats['comments_removed'];
	}

	/**
	 * Whether a comment carries meaning and must be preserved.
	 *
	 * @param string $c Full comment.
	 * @return bool
	 */
	private function keep_comment( $c ) {
		$inner = (string) substr( $c, 4 );
		if ( '-->' === substr( $inner, -3 ) ) {
			$inner = substr( $inner, 0, -3 );
		} elseif ( '--!>' === substr( $inner, -4 ) ) {
			$inner = substr( $inner, 0, -4 );
		}
		$t = ltrim( $inner, self::WS );
		if ( '' === $t || '>' === $t || '->' === $t ) {
			return true;
		}
		foreach ( self::$keep_comment_prefixes as $p ) {
			if ( 0 === strncasecmp( $t, $p, strlen( $p ) ) ) {
				return true;
			}
		}
		foreach ( $this->o['keep_comments'] as $p ) {
			if ( '' !== $p && false !== stripos( $t, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle an end tag.
	 *
	 * @param string $html Document.
	 * @param int    $lt   Position of "<".
	 * @return int New position.
	 * @throws AIOM_Minify_Exception On regex engine failure.
	 */
	private function end_tag( $html, $lt ) {
		$r = preg_match( self::END_TAG, $html, $m, 0, $lt );
		if ( false === $r ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'PCRE error in end tag: ' . preg_last_error() );
		}
		if ( ! $r ) {
			if ( isset( $html[ $lt + 2 ] ) && '>' === $html[ $lt + 2 ] ) {
				return $this->verbatim_until( $html, $lt, '>', '!' );
			}
			// "</" followed by a non-letter is a bogus comment; at EOF it is dropped by browsers.
			return $this->verbatim_until( $html, $lt, '>', '!' );
		}
		$lname = strtolower( $m[1] );
		$this->flush_ws( '/' . $lname );
		$this->out[] = $this->o['collapse_whitespace'] ? '</' . $m[1] . '>' : $m[0];
		$this->prev  = ( 'head' === $lname && $this->in_body ) ? '/head!' : '/' . $lname;

		if ( 'head' === $lname ) {
			$this->in_head = false;
		} elseif ( ( 'svg' === $lname || 'math' === $lname ) && $this->foreign > 0 ) {
			--$this->foreign;
		}
		if ( $this->preserve ) {
			$top = count( $this->preserve ) - 1;
			if ( $this->preserve[ $top ][0] === $lname ) {
				if ( --$this->preserve[ $top ][1] <= 0 ) {
					array_pop( $this->preserve );
				}
			}
		}
		return $lt + strlen( $m[0] );
	}

	/**
	 * Handle a start tag, including raw-text elements.
	 *
	 * @param string $html Document.
	 * @param int    $lt   Position of "<".
	 * @return int New position.
	 * @throws AIOM_Minify_Exception On regex engine failure.
	 */
	private function start_tag( $html, $lt ) {
		$r = preg_match( self::START_TAG, $html, $m, 0, $lt );
		if ( false === $r ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'PCRE error in start tag: ' . preg_last_error() );
		}
		if ( ! $r ) {
			// EOF inside a tag: browsers drop it. Keep the remainder untouched.
			$this->flush_ws( '!' );
			$this->out[] = substr( $html, $lt );
			return strlen( $html );
		}
		$name    = $m[1];
		$lname   = strtolower( $name );
		$attrs   = $m[2];
		$slash   = $m[3];
		$tag_end = $lt + strlen( $m[0] );

		// Whitespace before this tag belongs to the state before it.
		$this->flush_ws( $lname );
		$was_body = $this->in_body;
		if ( 'head' === $lname ) {
			$this->in_head = ! $this->in_body;
		} elseif ( 'body' === $lname || ( ! $this->in_body && 'html' !== $lname && ! isset( self::$head_tags[ $lname ] ) ) ) {
			$this->in_head = false;
			$this->in_body = true;
		}

		$open = $this->o['collapse_whitespace'] ? $this->normalize_tag( $name, $attrs, $slash ) : $m[0];

		if ( isset( self::$raw_text[ $lname ] ) ) {
			return $this->raw_text_element( $html, $lname, $open, $attrs, $tag_end );
		}
		if ( 'plaintext' === $lname ) {
			$this->out[] = $open . substr( $html, $tag_end );
			return strlen( $html );
		}

		$this->out[] = $open;
		// A second <body> (after an implied one) is ignored by browsers: whitespace after it is content.
		$this->prev = ( 'body' === $lname && $was_body ) ? 'body!' : $lname;

		if ( ( 'svg' === $lname || 'math' === $lname ) && '/' !== $slash ) {
			++$this->foreign;
		}
		if ( ! isset( self::$void[ $lname ] ) && ! ( $this->foreign && '/' === $slash ) ) {
			if ( $this->preserve && $this->preserve[ count( $this->preserve ) - 1 ][0] === $lname ) {
				++$this->preserve[ count( $this->preserve ) - 1 ][1];
			} elseif ( isset( self::$preserve_tags[ $lname ] ) || ( $this->foreign && 'text' === $lname ) || $this->preserves_whitespace( $attrs ) ) {
				$this->preserve[] = array( $lname, 1 );
			}
		}
		return $tag_end;
	}

	/**
	 * Whether attributes mark an element as whitespace-sensitive.
	 *
	 * @param string $attrs Raw attribute string.
	 * @return bool
	 */
	private function preserves_whitespace( $attrs ) {
		if ( '' === $attrs ) {
			return false;
		}
		if ( false !== stripos( $attrs, 'white-space' ) && preg_match( '/white-space\s*:\s*(?:pre|break-spaces)/i', $attrs ) ) {
			return true;
		}
		if ( $this->o['preserve_classes'] && false !== stripos( $attrs, 'class' ) ) {
			$info = self::parse_attrs( $attrs );
			if ( isset( $info['class'] ) ) {
				$classes = preg_split( '/[\t\n\f\r ]+/', $info['class'] );
				foreach ( $this->o['preserve_classes'] as $c ) {
					if ( in_array( $c, $classes, true ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Emit a raw-text element (script, style, textarea...) with its content.
	 *
	 * @param string $html    Document.
	 * @param string $lname   Lowercase tag name.
	 * @param string $open    Opening tag to emit.
	 * @param string $attrs   Raw attributes.
	 * @param int    $tag_end Position after the opening tag.
	 * @return int New position.
	 * @throws AIOM_Minify_Exception On regex engine failure.
	 */
	private function raw_text_element( $html, $lname, $open, $attrs, $tag_end ) {
		$close = ( 'script' === $lname ) ? self::find_script_end( $html, $tag_end ) : self::find_close( $html, $lname, $tag_end );
		if ( false === $close ) {
			// Unterminated: the browser treats the rest of the document as this element's text.
			$this->out[] = $open . substr( $html, $tag_end );
			return strlen( $html );
		}
		$r = preg_match( self::END_TAG, $html, $cm, 0, $close );
		if ( false === $r ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'PCRE error in raw text end tag: ' . preg_last_error() );
		}
		if ( ! $r ) {
			$this->out[] = $open . substr( $html, $tag_end );
			return strlen( $html );
		}
		$content   = substr( $html, $tag_end, $close - $tag_end );
		$close_tag = $this->o['collapse_whitespace'] ? '</' . $cm[1] . '>' : $cm[0];

		if ( 'script' === $lname ) {
			list( $open, $content ) = $this->script( $open, $attrs, $content );
		} elseif ( 'style' === $lname && ! $this->foreign && is_callable( $this->o['css_callback'] ) && '' !== trim( $content ) ) {
			$info = self::parse_attrs( $attrs );
			$type = isset( $info['type'] ) ? strtolower( trim( $info['type'] ) ) : '';
			if ( '' === $type || 'text/css' === $type ) {
				$min = $this->call( $this->o['css_callback'], array( $content ) );
				if ( is_string( $min ) && false === stripos( $min, '</style' ) ) {
					$content = $min;
					++$this->stats['inline_css'];
				}
			}
		}

		$this->out[] = $open . $content . $close_tag;
		$this->prev  = '/' . $lname;
		return $close + strlen( $cm[0] );
	}

	/**
	 * Process an inline or external script element.
	 *
	 * @param string $open    Opening tag.
	 * @param string $attrs   Raw attributes.
	 * @param string $content Script text.
	 * @return array( string $open, string $content )
	 */
	private function script( $open, $attrs, $content ) {
		++$this->stats['scripts'];
		$info = self::parse_attrs( $attrs );
		$type = isset( $info['type'] ) ? strtolower( trim( $info['type'] ) ) : '';
		$semi = strpos( $type, ';' );
		if ( false !== $semi ) {
			$type = trim( substr( $type, 0, $semi ) );
		}
		if ( isset( self::$js_types[ $type ] ) ) {
			$kind = 'js';
		} elseif ( isset( self::$json_types[ $type ] ) ) {
			$kind = 'json';
		} else {
			$kind = 'other';
		}

		if ( is_callable( $this->o['script_callback'] ) ) {
			$replacement = $this->call(
				$this->o['script_callback'],
				array(
					array(
						'open'    => $open,
						'attrs'   => $info,
						'type'    => $type,
						'kind'    => $kind,
						'content' => $content,
					),
				)
			);
			if ( is_string( $replacement ) && '' !== $replacement ) {
				$open = $replacement;
			}
		}

		if ( ! isset( $info['src'] ) && '' !== trim( $content ) ) {
			if ( 'js' === $kind && is_callable( $this->o['js_callback'] ) ) {
				$min = $this->call( $this->o['js_callback'], array( $content, 'module' === $type ) );
				if ( is_string( $min ) && $min !== $content && self::safe_in_script( $min, $content ) ) {
					$content = $min;
					++$this->stats['inline_js'];
				}
			} elseif ( 'json' === $kind && $this->o['minify_json'] ) {
				$min = self::minify_json( $content );
				if ( $min !== $content && self::safe_in_script( $min, $content ) ) {
					$content = $min;
					++$this->stats['json'];
				}
			}
		}
		return array( $open, $content );
	}

	/**
	 * A transformed script must not introduce sequences that change where the element ends.
	 *
	 * @param string $new New content.
	 * @param string $old Original content.
	 * @return bool
	 */
	private static function safe_in_script( $new, $old ) {
		foreach ( array( '</script', '<!--', '<script' ) as $seq ) {
			if ( false !== stripos( $new, $seq ) && false === stripos( $old, $seq ) ) {
				return false;
			}
		}
		return substr_count( strtolower( $new ), '</script' ) === substr_count( strtolower( $old ), '</script' );
	}

	/**
	 * Invoke a user callback, never letting it break the page.
	 *
	 * @param callable $cb   Callback.
	 * @param array    $args Arguments.
	 * @return mixed|null
	 */
	private function call( $cb, array $args ) {
		try {
			return call_user_func_array( $cb, $args );
		} catch ( Exception $e ) {
			return null;
		} catch ( Error $e ) {
			return null;
		}
	}

	/**
	 * Normalise whitespace between attributes. Attribute values are never changed.
	 *
	 * @param string $name  Tag name.
	 * @param string $attrs Raw attributes (with leading whitespace).
	 * @param string $slash Self-closing slash or ''.
	 * @return string
	 */
	private function normalize_tag( $name, $attrs, $slash ) {
		if ( '' === $attrs ) {
			return '<' . $name . $slash . '>';
		}
		$trailing = false !== strpos( self::WS, substr( $attrs, -1 ) );
		if ( false === strpbrk( $attrs, "\t\n\r\f" ) && false === strpos( $attrs, '  ' ) && ! $trailing ) {
			return '<' . $name . $attrs . $slash . '>';
		}
		$attrs = preg_replace_callback(
			'/=[\t\n\f\r ]*+("[^"]*+"|\'[^\']*+\')|[\t\n\f\r ]++/',
			static function ( $m ) {
				return isset( $m[1] ) ? '=' . $m[1] : ' ';
			},
			$attrs
		);
		$attrs = rtrim( $attrs, ' ' );
		// "<img src=x />" must not become "<img src=x/>" (the slash would join the unquoted value).
		if ( '/' === $slash && $trailing && '' !== $attrs ) {
			$last = substr( $attrs, -1 );
			if ( '"' !== $last && "'" !== $last ) {
				$attrs .= ' ';
			}
		}
		return '<' . $name . $attrs . $slash . '>';
	}

	/**
	 * Parse attributes into a lowercase-name => decoded-value map (first occurrence wins).
	 *
	 * @param string $attrs Raw attribute string.
	 * @return array
	 */
	public static function parse_attrs( $attrs ) {
		$out = array();
		if ( '' === trim( $attrs ) ) {
			return $out;
		}
		preg_match_all( '/((?:=|[^\t\n\f\r \/>=])[^\t\n\f\r \/>=]*+)(?:[\t\n\f\r ]*+=[\t\n\f\r ]*+("[^"]*+"|\'[^\']*+\'|[^\t\n\f\r >]++))?/', $attrs, $mm, PREG_SET_ORDER );
		foreach ( $mm as $m ) {
			$k = strtolower( $m[1] );
			if ( isset( $out[ $k ] ) ) {
				continue;
			}
			$v = isset( $m[2] ) ? $m[2] : '';
			if ( '' !== $v && ( '"' === $v[0] || "'" === $v[0] ) ) {
				$v = substr( $v, 1, -1 );
			}
			$out[ $k ] = ( false === strpos( $v, '&' ) ) ? $v : html_entity_decode( $v, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return $out;
	}

	/**
	 * Find "</name" followed by a tag-name terminator, case-insensitively.
	 *
	 * @param string $html Document.
	 * @param string $name Lowercase tag name.
	 * @param int    $from Search start.
	 * @return int|false
	 */
	private static function find_close( $html, $name, $from ) {
		$needle = '</' . $name;
		$nl     = strlen( $needle );
		$p      = $from;
		while ( false !== ( $p = stripos( $html, $needle, $p ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$c = isset( $html[ $p + $nl ] ) ? $html[ $p + $nl ] : '';
			if ( '' === $c || false !== strpos( " \t\n\r\f/>", $c ) ) {
				return $p;
			}
			$p += $nl;
		}
		return false;
	}

	/**
	 * Whether "<name" or "</name" plus terminator starts at $p.
	 *
	 * @param string $html  Document.
	 * @param int    $p     Position.
	 * @param string $token "<script" or "</script".
	 * @return bool
	 */
	private static function tag_at( $html, $p, $token ) {
		$n = strlen( $token );
		if ( 0 !== substr_compare( $html, $token, $p, $n, true ) ) {
			return false;
		}
		$c = isset( $html[ $p + $n ] ) ? $html[ $p + $n ] : '';
		return '' !== $c && false !== strpos( " \t\n\r\f/>", $c );
	}

	/**
	 * Find the end of a script element, honouring the "script data escaped" states
	 * (a "</script>" inside "<!-- <script> ... -->" does not end the element).
	 *
	 * @param string $html Document.
	 * @param int    $from Position after the opening tag.
	 * @return int|false Position of "</script".
	 */
	public static function find_script_end( $html, $from ) {
		$end = self::find_close( $html, 'script', $from );
		if ( false === $end ) {
			return false;
		}
		$open = strpos( $html, '<!--', $from );
		if ( false === $open || $open > $end ) {
			return $end;
		}
		$len   = strlen( $html );
		$i     = $from;
		$state = 0; // 0 = data, 1 = escaped, 2 = double escaped.
		while ( $i < $len ) {
			if ( 0 === $state ) {
				$lt = strpos( $html, '<', $i );
				if ( false === $lt ) {
					return false;
				}
				if ( 0 === substr_compare( $html, '<!--', $lt, 4 ) ) {
					$i = $lt + 4;
					if ( isset( $html[ $i ] ) && '>' === $html[ $i ] ) {
						++$i;
					} else {
						$state = 1;
					}
					continue;
				}
				if ( self::tag_at( $html, $lt, '</script' ) ) {
					return $lt;
				}
				$i = $lt + 1;
				continue;
			}
			$dash = strpos( $html, '-->', $i );
			$lt   = strpos( $html, '<', $i );
			if ( false !== $dash && ( false === $lt || $dash < $lt ) ) {
				$state = 0;
				$i     = $dash + 3;
				continue;
			}
			if ( false === $lt ) {
				return false;
			}
			if ( 1 === $state ) {
				if ( self::tag_at( $html, $lt, '</script' ) ) {
					return $lt;
				}
				if ( self::tag_at( $html, $lt, '<script' ) ) {
					$state = 2;
					$i     = $lt + 7;
					continue;
				}
			} elseif ( self::tag_at( $html, $lt, '</script' ) ) {
				$state = 1;
				$i     = $lt + 8;
				continue;
			}
			$i = $lt + 1;
		}
		return false;
	}
}
