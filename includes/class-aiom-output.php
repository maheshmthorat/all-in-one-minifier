<?php
/**
 * Output buffer: applies HTML transformations to complete HTML documents.
 *
 * The buffer is only processed when it is a complete text/html document of a
 * successful (2xx/404) response. Streamed/flushed output, redirects, non-HTML
 * content types and truncated documents (e.g. after a fatal error) pass
 * through untouched. Any failure returns the original markup.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Output processing.
 */
class AIOM_Output {

	/**
	 * Request plan.
	 *
	 * @var array
	 */
	private static $plan = array();

	/**
	 * Whether output was flushed in chunks (then nothing is transformed).
	 *
	 * @var bool
	 */
	private static $streaming = false;

	/**
	 * Start buffering.
	 *
	 * @param array $plan Plan.
	 */
	public static function start( array $plan ) {
		self::$plan      = $plan;
		self::$streaming = false;
		ob_start( array( __CLASS__, 'callback' ) );
	}

	/**
	 * Output buffer callback.
	 *
	 * @param string $buffer Buffer.
	 * @param int    $phase  PHP_OUTPUT_HANDLER_* flags.
	 * @return string
	 */
	public static function callback( $buffer, $phase ) {
		if ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) {
			return $buffer;
		}
		$final = (bool) ( $phase & ( PHP_OUTPUT_HANDLER_FINAL | PHP_OUTPUT_HANDLER_END ) );
		if ( ! $final || self::$streaming ) {
			self::$streaming = true;
			return $buffer;
		}
		try {
			return self::process( (string) $buffer );
		} catch ( Throwable $e ) {
			AIOM_Log::throttled( 'error', 'output', 'HTML optimisation failed and the page was served unchanged: ' . $e->getMessage() );
			return $buffer;
		}
	}

	/**
	 * Transform a document.
	 *
	 * @param string $html Document.
	 * @return string
	 */
	public static function process( $html ) {
		$plan = self::$plan;
		if ( strlen( $html ) < 255 || ! self::is_html_response( $html ) ) {
			return $html;
		}
		$start = microtime( true );
		$out   = $html;
		$stats = array();
		$error = '';
		if ( $plan['delay'] && preg_match( '/<html[^>]*\s(?:amp|\x{26A1})(?:[\s=>])/iu', substr( $html, 0, 2048 ) ) ) {
			$plan['delay'] = false; // AMP documents must not have their scripts changed.
		}
		if ( $plan['html'] || $plan['delay'] ) {
			AIOM_Delay::reset();
			$html_on = $plan['html'];
			$m       = new AIOM_HTML_Minifier(
				array(
					'collapse_whitespace'       => $html_on,
					'remove_comments'           => $html_on && AIOM_Settings::get( 'html_remove_comments' ),
					'keep_comments'             => AIOM_Settings::lines( 'html_keep_comments' ),
					'collapse_block_whitespace' => $html_on && AIOM_Settings::get( 'html_block_whitespace' ),
					'preserve_classes'          => AIOM_Settings::lines( 'html_preserve_classes' ),
					'minify_json'               => $html_on && AIOM_Settings::get( 'html_inline_json' ),
					'css_callback'              => ( $html_on && AIOM_Settings::get( 'html_inline_css' ) ) ? array( 'AIOM_Inline', 'css' ) : null,
					'js_callback'               => ( $html_on && AIOM_Settings::get( 'html_inline_js' ) && AIOM_JS_Minifier::available() ) ? array( 'AIOM_Inline', 'js' ) : null,
					'script_callback'           => $plan['delay'] ? array( 'AIOM_Delay', 'filter_script' ) : null,
				)
			);
			try {
				$candidate = $m->process( $html );
				if ( self::valid( $html, $candidate ) ) {
					$out   = $candidate;
					$stats = $m->stats;
				} else {
					$error = 'validation';
				}
			} catch ( AIOM_Minify_Exception $e ) {
				$error = $e->getMessage();
			}
			if ( '' !== $error ) {
				AIOM_Log::throttled( 'warning', 'html_rejected', 'HTML optimisation was skipped for a page because the result failed validation (' . $error . '); the original HTML was served.' );
			} elseif ( $plan['delay'] && AIOM_Delay::count() ) {
				$out = AIOM_Delay::inject_loader( $out );
			}
		}
		$elapsed = microtime( true ) - $start;
		AIOM_Stats::bump( 'html_requests' );
		AIOM_Stats::bump( 'html_us', (int) round( $elapsed * 1000000 ) );

		if ( $plan['page_cache'] ) {
			AIOM_Page_Cache::maybe_store( $out );
		}
		if ( 'on' === $plan['report'] ) {
			AIOM_Reports::record_request(
				array(
					'html_bytes'     => strlen( $html ),
					'html_opt_bytes' => strlen( $out ),
					'html_ms'        => round( $elapsed * 1000, 2 ),
					'html_error'     => $error,
					'html_stats'     => $stats,
					'delayed'        => AIOM_Delay::count(),
					'assets'         => AIOM_Assets::records(),
					'plan'           => $plan,
				)
			);
		}
		return $out;
	}

	/**
	 * Whether the buffer is a complete HTML document of a cacheable response.
	 *
	 * @param string $html Buffer.
	 * @return bool
	 */
	public static function is_html_response( $html ) {
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'text/html' ) ) {
				return false;
			}
			if ( 0 === stripos( $h, 'location:' ) ) {
				return false;
			}
		}
		$code = http_response_code();
		if ( $code && 404 !== $code && ( $code < 200 || $code >= 300 ) ) {
			return false;
		}
		$head = substr( $html, 0, 1024 );
		if ( false === stripos( $head, '<html' ) && false === stripos( $head, '<!doctype html' ) ) {
			return false;
		}
		return false !== stripos( $html, '</html>' ) || false !== stripos( $html, '</body>' );
	}

	/**
	 * Sanity checks on the transformed document.
	 *
	 * @param string $in  Original.
	 * @param string $out Result.
	 * @return bool
	 */
	public static function valid( $in, $out ) {
		if ( ! is_string( $out ) || '' === trim( $out ) || strlen( $out ) < 0.25 * strlen( $in ) ) {
			return false;
		}
		foreach ( array( '<head', '</head>', '<body', '</body>', '</html>' ) as $tag ) {
			if ( false !== stripos( $in, $tag ) && false === stripos( $out, $tag ) ) {
				return false;
			}
		}
		return true;
	}
}
