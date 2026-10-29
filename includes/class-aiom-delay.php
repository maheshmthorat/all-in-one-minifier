<?php
/**
 * Opt-in delayed JavaScript: matching scripts run after the first user
 * interaction (or an optional timeout) instead of during page load.
 *
 * Only scripts that match a configured pattern are delayed. Matching scripts
 * get type="aiom/delayed" (browsers do not execute unknown types); a small
 * loader re-creates them in document order, waiting for each external
 * non-async script to load before running the next, so relative order is kept.
 * Scripts delayed this way do not see DOMContentLoaded/load events — that is the
 * main compatibility risk, and why the feature is opt-in and pattern-based.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Delay JS.
 */
class AIOM_Delay {

	/**
	 * Loader, ~0.9 KB. __T__ is replaced with the timeout in seconds (0 = interaction only).
	 */
	const LOADER = '(function(w,d){var done=0,T=__T__,ev=["keydown","mousedown","mousemove","touchstart","scroll","wheel"],o={passive:true};function run(){if(done)return;done=1;ev.forEach(function(e){w.removeEventListener(e,run,o)});var l=[].slice.call(d.querySelectorAll("script[data-aiom-delay]"));(function next(){var s=l.shift();if(!s)return;var n=d.createElement("script"),t=s.getAttribute("data-aiom-type"),a,i;for(i=0;i<s.attributes.length;i++){a=s.attributes[i];if(a.name!=="type"&&a.name!=="data-aiom-delay"&&a.name!=="data-aiom-type")n.setAttribute(a.name,a.value)}if(t)n.type=t;if(s.nonce)n.nonce=s.nonce;if(s.src&&!s.async&&t!=="module"){n.async=false;n.onload=n.onerror=next;s.parentNode.replaceChild(n,s)}else{if(!s.src)n.text=s.text;s.parentNode.replaceChild(n,s);next()}})()}ev.forEach(function(e){w.addEventListener(e,run,o)});if(T>0)setTimeout(run,T*1000)})(window,document);';

	/**
	 * Number of scripts delayed in this response.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Reset (per response).
	 */
	public static function reset() {
		self::$count = 0;
	}

	/**
	 * Delayed scripts in this response.
	 *
	 * @return int
	 */
	public static function count() {
		return self::$count;
	}

	/**
	 * Script callback for AIOM_HTML_Minifier: returns a replacement opening tag for matching scripts.
	 *
	 * @param array $s Script info.
	 * @return string|null
	 */
	public static function filter_script( array $s ) {
		if ( 'js' !== $s['kind'] ) {
			return null;
		}
		$a = $s['attrs'];
		if ( isset( $a['data-aiom-nodelay'] ) || isset( $a['data-no-optimize'] ) || isset( $a['data-aiom-delay'] ) || ( isset( $a['id'] ) && 'aiom-delay-loader' === $a['id'] ) ) {
			return null;
		}
		$subject = isset( $a['src'] ) ? $a['src'] : $s['content'];
		if ( ! self::matches( AIOM_Settings::lines( 'js_delay_patterns' ), $subject, isset( $a['id'] ) ? $a['id'] : '' ) ) {
			return null;
		}
		$out = '<script';
		foreach ( $a as $name => $value ) {
			if ( ! preg_match( '/^[a-z_:][a-z0-9_:.@-]*$/', $name ) ) {
				return null; // Unusual attribute names: leave the script alone.
			}
			if ( 'type' === $name ) {
				continue;
			}
			$out .= ' ' . $name . ( '' === $value ? '' : '="' . esc_attr( $value ) . '"' );
		}
		$out .= ' type="aiom/delayed" data-aiom-delay';
		if ( '' !== $s['type'] ) {
			$out .= ' data-aiom-type="' . esc_attr( $s['type'] ) . '"';
		}
		++self::$count;
		return $out . '>';
	}

	/**
	 * Whether a script matches a delay pattern (substring of URL/content, or id).
	 *
	 * @param string[] $patterns Patterns.
	 * @param string   $subject  URL or inline content.
	 * @param string   $id       Element id.
	 * @return bool
	 */
	public static function matches( array $patterns, $subject, $id ) {
		foreach ( $patterns as $p ) {
			if ( strlen( $p ) >= 3 && ( false !== stripos( $subject, $p ) || ( '' !== $id && 0 === strcasecmp( $id, $p ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Insert the loader before </body>.
	 *
	 * @param string $html Document.
	 * @return string
	 */
	public static function inject_loader( $html ) {
		$script = '<script id="aiom-delay-loader">' . str_replace( '__T__', (string) (int) AIOM_Settings::get( 'js_delay_timeout' ), self::LOADER ) . '</script>';
		$pos    = strripos( $html, '</body>' );
		if ( false === $pos ) {
			return $html . $script;
		}
		return substr_replace( $html, $script, $pos, 0 );
	}
}
