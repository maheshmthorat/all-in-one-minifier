<?php
/**
 * Inline <style>/<script> minification with a content-addressed memo.
 *
 * Inline blocks such as theme.json global styles are identical across
 * requests, so their minified form is stored under md5(content). Blocks that
 * vary per request (nonces) simply miss; at most a few new entries are written
 * per request, and unused entries expire through maintenance.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline content minifier.
 */
class AIOM_Inline {

	const SKIP           = "\0AIOM-SKIP";
	const MAX_WRITES     = 8;
	const MEMO_MIN_BYTES = 2048;

	/**
	 * Memo writes in this request.
	 *
	 * @var int
	 */
	private static $writes = 0;

	/**
	 * Minify inline CSS.
	 *
	 * @param string $css CSS.
	 * @return string
	 */
	public static function css( $css ) {
		$fn = static function () use ( $css ) {
			return AIOM_CSS_Minifier::minify( $css );
		};
		return strlen( $css ) < self::MEMO_MIN_BYTES ? self::safe( $fn, $css ) : self::memo( 'css', $css, $fn );
	}

	/**
	 * Minify inline JavaScript.
	 *
	 * @param string $js     Script.
	 * @param bool   $module Whether it is a module.
	 * @return string
	 */
	public static function js( $js, $module ) {
		if ( strlen( $js ) > 102400 ) {
			return $js;
		}
		$fn = static function () use ( $js, $module ) {
			return AIOM_JS_Minifier::minify( $js, $module );
		};
		return self::memo( $module ? 'mjs' : 'js', $js, $fn );
	}

	/**
	 * Run a minifier, returning the input on failure.
	 *
	 * @param callable $fn       Minifier.
	 * @param string   $original Input.
	 * @return string
	 */
	private static function safe( $fn, $original ) {
		try {
			return $fn();
		} catch ( AIOM_Minify_Exception $e ) {
			return $original;
		}
	}

	/**
	 * Memoised minification.
	 *
	 * @param string   $ext     Memo extension.
	 * @param string   $content Input.
	 * @param callable $fn      Minifier.
	 * @return string
	 */
	private static function memo( $ext, $content, $fn ) {
		$key  = md5( AIOM_VERSION . '|' . $content );
		$dir  = 'inline/' . substr( $key, 0, 2 );
		$file = AIOM_Cache_Dir::base() . $dir . '/' . $key . '.' . $ext;
		if ( is_file( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$cached = @file_get_contents( $file );
			if ( false !== $cached ) {
				return self::SKIP === $cached ? $content : $cached;
			}
		}
		try {
			$out = $fn();
		} catch ( AIOM_Minify_Exception $e ) {
			$out = null;
		}
		if ( self::$writes < self::MAX_WRITES && AIOM_Cache_Dir::ensure( $dir ) ) {
			++self::$writes;
			AIOM_Cache_Dir::write( $file, null === $out ? self::SKIP : $out );
		}
		return null === $out ? $content : $out;
	}
}
