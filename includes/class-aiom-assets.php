<?php
/**
 * Integration with the WordPress script/style APIs.
 *
 * - Enqueued local CSS/JS URLs are swapped for cached, minified versions.
 *   Uncached files keep their original URL for this request and are minified
 *   after the response has been sent (or within a small time budget when the
 *   server cannot finish the request early), so visitors never wait for it.
 * - "Defer" uses the WordPress 6.3+ loading-strategy API, which is
 *   dependency-aware: core keeps a script blocking when a dependent or an
 *   "after" inline script needs it.
 * - Async CSS, critical CSS, Google Fonts display=swap and resource hints.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset hooks.
 */
class AIOM_Assets {

	/**
	 * Request plan.
	 *
	 * @var array
	 */
	private static $plan = array();

	/**
	 * Per-asset records for reports.
	 *
	 * @var array
	 */
	private static $records = array();

	/**
	 * Uncached assets to generate at shutdown: path => array( type, job ).
	 *
	 * @var array
	 */
	private static $pending = array();

	/**
	 * Register hooks for this request.
	 *
	 * @param array $plan Plan from AIOM_Context.
	 */
	public static function hook( array $plan ) {
		self::$plan = $plan;
		if ( $plan['css'] || $plan['font_swap'] ) {
			add_filter( 'style_loader_src', array( __CLASS__, 'style_src' ), 5, 2 );
		}
		if ( $plan['js'] ) {
			add_filter( 'script_loader_src', array( __CLASS__, 'script_src' ), 5, 2 );
		}
		if ( $plan['defer'] ) {
			add_action( 'wp_print_scripts', array( __CLASS__, 'apply_defer' ), 1000 );
			add_action( 'wp_print_footer_scripts', array( __CLASS__, 'apply_defer' ), 1 );
		}
		if ( $plan['async_css'] ) {
			add_filter( 'style_loader_tag', array( __CLASS__, 'async_style' ), 20, 4 );
		}
		if ( '' !== $plan['critical'] ) {
			add_action( 'wp_head', array( __CLASS__, 'print_critical_css' ), 2 );
		}
		if ( $plan['hints'] ) {
			add_filter( 'wp_resource_hints', array( __CLASS__, 'resource_hints' ), 10, 2 );
			add_filter( 'wp_preload_resources', array( __CLASS__, 'preload_resources' ) );
		}
		add_action( 'shutdown', array( __CLASS__, 'process_pending' ), 2 );
	}

	/**
	 * Filter: style URL.
	 *
	 * @param string $src    URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function style_src( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}
		if ( self::$plan['font_swap'] && false !== stripos( $src, 'fonts.googleapis.com/css' ) && false === stripos( $src, 'display=' ) ) {
			$src = add_query_arg( 'display', 'swap', $src );
			self::record( 'css', $handle, $src, 'external', 'display=swap added' );
			return $src;
		}
		return self::$plan['css'] ? self::process( 'css', $handle, $src ) : $src;
	}

	/**
	 * Filter: script URL.
	 *
	 * @param string $src    URL.
	 * @param string $handle Handle.
	 * @return string
	 */
	public static function script_src( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}
		return self::process( 'js', $handle, $src );
	}

	/**
	 * Swap an asset URL for its optimised version when available.
	 *
	 * @param string $type   'css' or 'js'.
	 * @param string $handle Handle.
	 * @param string $src    Original URL.
	 * @return string
	 */
	private static function process( $type, $handle, $src ) {
		$rule = AIOM_Rules::match_asset( AIOM_Settings::lines( 'css' === $type ? 'css_exclude' : 'js_exclude' ), $handle, $src );
		if ( false !== $rule ) {
			self::record( $type, $handle, $src, 'excluded', $rule );
			return $src;
		}
		$job = AIOM_Asset_Cache::locate( $src, $type );
		if ( ! $job ) {
			self::record( $type, $handle, $src, 'external', '' );
			return $src;
		}
		if ( preg_match( '/[.-]min\.(?:css|js)$/i', $job['name'] ) ) {
			self::record( $type, $handle, $src, 'already_minified', '', $job );
			return $src;
		}
		$r = AIOM_Asset_Cache::lookup( $type, $job );
		if ( 'miss' === $r['status'] && self::$plan['sync'] ) {
			$g = AIOM_Asset_Cache::generate( $type, $job );
			$r = AIOM_Asset_Cache::lookup( $type, $job );
			if ( 'generated' === $g['status'] ) {
				AIOM_Stats::bump( 'generated' );
			}
		}
		if ( 'hit' === $r['status'] ) {
			AIOM_Stats::bump( 'asset_hits' );
			self::record( $type, $handle, $src, 'optimised', '', $job, $r['path'] );
			return $r['url'];
		}
		if ( 'skip' === $r['status'] ) {
			if ( AIOM_Asset_Cache::COMPACT === $r['reason'] ) {
				self::record( $type, $handle, $src, 'already_minified', '', $job );
			} else {
				self::record( $type, $handle, $src, 'skipped', $r['reason'], $job );
			}
			return $src;
		}
		AIOM_Stats::bump( 'asset_misses' );
		self::$pending[ $r['path'] ] = array( $type, $job );
		self::record( $type, $handle, $src, 'queued', '', $job );
		return $src;
	}

	/**
	 * Record what happened to an asset. In report mode the file paths are kept so the
	 * report runner can measure sizes afterwards, outside the timed request.
	 *
	 * @param string      $type   Type.
	 * @param string      $handle Handle.
	 * @param string      $src    URL.
	 * @param string      $status Status.
	 * @param string      $note   Note.
	 * @param array|null  $job    Located file.
	 * @param string|null $cached Cached file.
	 */
	private static function record( $type, $handle, $src, $status, $note, $job = null, $cached = null ) {
		$row = array(
			'type'   => $type,
			'handle' => (string) $handle,
			'src'    => (string) wp_parse_url( (string) $src, PHP_URL_PATH ),
			'status' => $status,
			'note'   => AIOM_Log::redact( (string) $note ),
		);
		if ( ! empty( self::$plan['report'] ) && $job ) {
			$row['file']   = $job['file'];
			$row['cached'] = $cached;
		}
		self::$records[] = $row;
	}

	/**
	 * Asset records for this request.
	 *
	 * @return array
	 */
	public static function records() {
		return self::$records;
	}

	/**
	 * Whether some assets are still waiting to be optimised.
	 *
	 * @return bool
	 */
	public static function has_pending() {
		return ! empty( self::$pending );
	}

	/**
	 * Generate queued assets after the response has been sent where possible.
	 */
	public static function process_pending() {
		if ( ! self::$pending ) {
			return;
		}
		$budget = (int) AIOM_Settings::get( 'generation_budget_ms' ) / 1000;
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			$budget = 20;
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
			$budget = 20;
		} else {
			flush();
		}
		ignore_user_abort( true );
		$start     = microtime( true );
		$generated = 0;
		foreach ( self::$pending as $item ) {
			if ( microtime( true ) - $start > $budget ) {
				break;
			}
			$r = AIOM_Asset_Cache::generate( $item[0], $item[1] );
			if ( 'generated' === $r['status'] ) {
				++$generated;
			} elseif ( 'failed' === $r['status'] || ( 'skip' === $r['status'] && AIOM_Asset_Cache::COMPACT !== $r['reason'] ) ) {
				AIOM_Stats::bump( 'failed' );
			}
		}
		self::$pending = array();
		if ( $generated ) {
			AIOM_Stats::bump( 'generated', $generated );
			$state                   = get_option( 'aiom_state', array() );
			$state                   = is_array( $state ) ? $state : array();
			$state['last_generated'] = time();
			update_option( 'aiom_state', $state, false );
		}
	}

	/**
	 * Mark scripts as "defer" through the WordPress loading-strategy API.
	 */
	public static function apply_defer() {
		$scripts = wp_scripts();
		$rules   = AIOM_Settings::lines( 'js_defer_exclude' );
		foreach ( $scripts->registered as $handle => $dep ) {
			if ( empty( $dep->src ) || '' !== (string) $scripts->get_data( $handle, 'strategy' ) ) {
				continue;
			}
			if ( false !== AIOM_Rules::match_asset( $rules, $handle, (string) $dep->src ) ) {
				continue;
			}
			$scripts->add_data( $handle, 'strategy', 'defer' );
		}
	}

	/**
	 * Load a stylesheet without blocking rendering (critical CSS covers first paint).
	 *
	 * @param string $tag    Tag HTML.
	 * @param string $handle Handle.
	 * @param string $href   URL.
	 * @param string $media  Media.
	 * @return string
	 */
	public static function async_style( $tag, $handle, $href, $media ) {
		if ( 'print' === $media || false !== AIOM_Rules::match_asset( AIOM_Settings::lines( 'css_async_exclude' ), $handle, (string) $href ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $tag;
		}
		$p = new WP_HTML_Tag_Processor( $tag );
		if ( ! $p->next_tag( 'link' ) || 'stylesheet' !== strtolower( (string) $p->get_attribute( 'rel' ) ) ) {
			return $tag;
		}
		$original = (string) $p->get_attribute( 'media' );
		$p->set_attribute( 'media', 'print' );
		$p->set_attribute( 'data-aiom-media', '' !== $original ? $original : 'all' );
		$p->set_attribute( 'onload', 'this.media=this.dataset.aiomMedia;this.onload=null' );
		return $p->get_updated_html() . '<noscript>' . trim( $tag ) . "</noscript>\n";
	}

	/**
	 * Print critical CSS early in <head>.
	 */
	public static function print_critical_css() {
		$css = self::$plan['critical'];
		try {
			$css = AIOM_CSS_Minifier::minify( $css );
		} catch ( AIOM_Minify_Exception $e ) {
			unset( $e ); // Print as entered.
		}
		// The value is stripped of "<" on save, so it cannot close the style element.
		echo '<style id="aiom-critical-css">' . str_replace( '<', '', $css ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Filter: add configured preconnect origins.
	 *
	 * @param array  $urls     Hints.
	 * @param string $relation Relation type.
	 * @return array
	 */
	public static function resource_hints( $urls, $relation ) {
		if ( 'preconnect' !== $relation ) {
			return $urls;
		}
		foreach ( AIOM_Settings::lines( 'hints_preconnect' ) as $line ) {
			$parts  = preg_split( '/\s+/', $line );
			$origin = self::origin( $parts[0] );
			if ( '' === $origin ) {
				continue;
			}
			$cross  = in_array( 'crossorigin', $parts, true ) || false !== strpos( $origin, 'fonts.gstatic.com' );
			$urls[] = $cross ? array(
				'href'        => $origin,
				'crossorigin' => 'anonymous',
			) : $origin;
		}
		return $urls;
	}

	/**
	 * Filter: add configured preload resources.
	 *
	 * @param array $resources Resources.
	 * @return array
	 */
	public static function preload_resources( $resources ) {
		foreach ( AIOM_Settings::lines( 'hints_preload' ) as $line ) {
			$item = self::parse_preload( $line );
			if ( $item ) {
				$resources[] = $item;
			}
		}
		return $resources;
	}

	/**
	 * Parse "URL [as=font] [type=font/woff2] [crossorigin] [media=...]" into a preload entry.
	 *
	 * @param string $line Line.
	 * @return array|null
	 */
	public static function parse_preload( $line ) {
		$parts = preg_split( '/\s+/', trim( $line ) );
		$href  = esc_url_raw( array_shift( $parts ) );
		if ( '' === $href ) {
			return null;
		}
		$item = array( 'href' => $href );
		foreach ( $parts as $p ) {
			if ( 'crossorigin' === $p ) {
				$item['crossorigin'] = 'anonymous';
			} elseif ( preg_match( '/^(as|type|media|fetchpriority)=(.+)$/', $p, $m ) ) {
				$item[ $m[1] ] = sanitize_text_field( $m[2] );
			}
		}
		if ( empty( $item['as'] ) ) {
			$ext   = strtolower( pathinfo( (string) wp_parse_url( $href, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$types = array(
				'woff2' => array( 'font', 'font/woff2' ),
				'woff'  => array( 'font', 'font/woff' ),
				'ttf'   => array( 'font', 'font/ttf' ),
				'otf'   => array( 'font', 'font/otf' ),
				'css'   => array( 'style', '' ),
				'js'    => array( 'script', '' ),
				'jpg'   => array( 'image', '' ),
				'jpeg'  => array( 'image', '' ),
				'png'   => array( 'image', '' ),
				'gif'   => array( 'image', '' ),
				'webp'  => array( 'image', '' ),
				'avif'  => array( 'image', '' ),
				'svg'   => array( 'image', '' ),
			);
			if ( ! isset( $types[ $ext ] ) ) {
				return null;
			}
			$item['as'] = $types[ $ext ][0];
			if ( '' !== $types[ $ext ][1] && empty( $item['type'] ) ) {
				$item['type'] = $types[ $ext ][1];
			}
		}
		if ( 'font' === $item['as'] ) {
			$item['crossorigin'] = 'anonymous';
		}
		return $item;
	}

	/**
	 * Normalise a URL to its origin.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function origin( $url ) {
		$p = wp_parse_url( trim( $url ) );
		if ( empty( $p['host'] ) ) {
			return '';
		}
		$scheme = ( isset( $p['scheme'] ) && in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) ) ? strtolower( $p['scheme'] ) : 'https';
		return $scheme . '://' . strtolower( $p['host'] ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
	}
}
