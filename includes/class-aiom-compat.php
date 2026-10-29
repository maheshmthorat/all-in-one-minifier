<?php
/**
 * Third-party compatibility: detection of overlapping optimisation features,
 * page-cache conflicts, page-builder editor requests and cache purging.
 *
 * Nothing here assumes another plugin is installed: every API is guarded by
 * function_exists()/class_exists()/defined() before use.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility layer.
 */
class AIOM_Compat {

	/**
	 * Detection cache for this request.
	 *
	 * @var array|null
	 */
	private static $detected = null;

	/**
	 * Detect active performance plugins and which overlapping features they have enabled.
	 *
	 * @return array[] Each: name, page_cache, html, css, js, defer, delay, note.
	 */
	public static function detect() {
		if ( null !== self::$detected ) {
			return self::$detected;
		}
		$found = array();
		$add   = static function ( $name, array $features, $note = '' ) use ( &$found ) {
			$found[] = array_merge(
				array(
					'name'       => $name,
					'page_cache' => false,
					'html'       => false,
					'css'        => false,
					'js'         => false,
					'defer'      => false,
					'delay'      => false,
					'note'       => $note,
				),
				$features
			);
		};

		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$o = static function ( $k ) {
				return function_exists( 'get_rocket_option' ) ? (bool) get_rocket_option( $k ) : false;
			};
			$add(
				'WP Rocket',
				array(
					'page_cache' => true,
					'css'        => $o( 'minify_css' ),
					'js'         => $o( 'minify_js' ),
					'defer'      => $o( 'defer_all_js' ),
					'delay'      => $o( 'delay_js' ),
				)
			);
		}
		if ( defined( 'LSCWP_V' ) ) {
			$o = static function ( $k ) {
				return (bool) get_option( 'litespeed.conf.' . $k );
			};
			$add(
				'LiteSpeed Cache',
				array(
					'page_cache' => $o( 'cache' ),
					'html'       => $o( 'optm-html_min' ),
					'css'        => $o( 'optm-css_min' ),
					'js'         => $o( 'optm-js_min' ),
					'defer'      => $o( 'optm-js_defer' ),
				)
			);
		}
		if ( defined( 'W3TC' ) || defined( 'W3TC_VERSION' ) ) {
			$cfg = null;
			if ( class_exists( '\W3TC\Dispatcher' ) && method_exists( '\W3TC\Dispatcher', 'config' ) ) {
				$cfg = \W3TC\Dispatcher::config();
			}
			$b      = static function ( $k ) use ( $cfg ) {
				return ( $cfg && method_exists( $cfg, 'get_boolean' ) ) ? (bool) $cfg->get_boolean( $k ) : false;
			};
			$minify = $b( 'minify.enabled' );
			$add(
				'W3 Total Cache',
				array(
					'page_cache' => $b( 'pgcache.enabled' ),
					'html'       => $minify && $b( 'minify.html.enable' ),
					'css'        => $minify && $b( 'minify.css.enable' ),
					'js'         => $minify && $b( 'minify.js.enable' ),
				)
			);
		}
		if ( defined( 'WPCACHEHOME' ) ) {
			$add( 'WP Super Cache', array( 'page_cache' => ! empty( $GLOBALS['cache_enabled'] ) ) );
		}
		if ( class_exists( 'WpFastestCache' ) ) {
			$o = json_decode( (string) get_option( 'WpFastestCache' ), true );
			$o = is_array( $o ) ? $o : array();
			$add(
				'WP Fastest Cache',
				array(
					'page_cache' => ! empty( $o['wpFastestCacheStatus'] ),
					'html'       => ! empty( $o['wpFastestCacheMinifyHtml'] ),
					'css'        => ! empty( $o['wpFastestCacheMinifyCss'] ),
					'js'         => ! empty( $o['wpFastestCacheMinifyJs'] ) || ! empty( $o['wpFastestCacheCombineJs'] ),
				)
			);
		}
		if ( defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
			$on = static function ( $k ) {
				return 'on' === get_option( $k );
			};
			$add(
				'Autoptimize',
				array(
					'html'  => $on( 'autoptimize_html' ),
					'css'   => $on( 'autoptimize_css' ),
					'js'    => $on( 'autoptimize_js' ),
					'defer' => $on( 'autoptimize_js_defer_not_aggregate' ) || $on( 'autoptimize_js_defer_inline' ),
				)
			);
		}
		if ( defined( 'CACHE_ENABLER_VERSION' ) ) {
			$add( 'Cache Enabler', array( 'page_cache' => true ) );
		}
		if ( defined( 'BREEZE_VERSION' ) ) {
			$o = get_option( 'breeze_basic_settings' );
			$o = is_array( $o ) ? $o : array();
			$add(
				'Breeze',
				array(
					'page_cache' => ! empty( $o['breeze-active'] ),
					'html'       => ! empty( $o['breeze-minify-html'] ),
					'css'        => ! empty( $o['breeze-minify-css'] ),
					'js'         => ! empty( $o['breeze-minify-js'] ),
				)
			);
		}
		if ( defined( 'SiteGround_Optimizer\VERSION' ) ) {
			$add(
				'SiteGround Optimizer',
				array(
					'page_cache' => (bool) get_option( 'siteground_optimizer_enable_cache' ),
					'html'       => (bool) get_option( 'siteground_optimizer_optimize_html' ),
					'css'        => (bool) get_option( 'siteground_optimizer_optimize_css' ),
					'js'         => (bool) get_option( 'siteground_optimizer_optimize_javascript' ),
					'defer'      => (bool) get_option( 'siteground_optimizer_optimize_javascript_async' ),
				)
			);
		}
		if ( defined( 'WPHB_VERSION' ) ) {
			$add( 'Hummingbird', array( 'page_cache' => true ), 'Check Hummingbird’s Asset Optimization module for overlapping minification.' );
		}
		if ( defined( 'PERFMATTERS_VERSION' ) ) {
			$o = get_option( 'perfmatters_options' );
			$a = ( is_array( $o ) && isset( $o['assets'] ) && is_array( $o['assets'] ) ) ? $o['assets'] : array();
			$add(
				'Perfmatters',
				array(
					'css'   => ! empty( $a['minify_css'] ),
					'js'    => ! empty( $a['minify_js'] ),
					'defer' => ! empty( $a['defer_js'] ),
					'delay' => ! empty( $a['delay_js'] ),
				)
			);
		}
		if ( defined( 'FLYING_PRESS_VERSION' ) ) {
			$add( 'FlyingPress', array( 'page_cache' => true ), 'FlyingPress also minifies and defers assets; avoid enabling the same feature twice.' );
		}
		if ( defined( 'JETPACK_BOOST_VERSION' ) ) {
			$add( 'Jetpack Boost', array(), 'Jetpack Boost can defer JavaScript and generate critical CSS; avoid enabling the same feature twice.' );
		}
		if ( defined( 'CLOUDFLARE_PLUGIN_DIR' ) ) {
			$add( 'Cloudflare', array(), 'If Automatic Platform Optimization caches HTML, purge Cloudflare after clearing this plugin’s cache.' );
		}

		self::$detected = $found;
		return $found;
	}

	/**
	 * Name of a plugin providing the given feature, if overlap-avoidance is enabled.
	 *
	 * @param string $feature html|css|js|defer|delay|page_cache.
	 * @return string Plugin name or ''.
	 */
	public static function yields_to( $feature ) {
		if ( 'page_cache' !== $feature && ! AIOM_Settings::get( 'auto_compat' ) ) {
			return '';
		}
		foreach ( self::detect() as $p ) {
			if ( ! empty( $p[ $feature ] ) ) {
				return $p['name'];
			}
		}
		return '';
	}

	/**
	 * Store the name of any active page cache so the early cache server (which runs
	 * before other plugins load) can stand aside without loading them.
	 */
	public static function refresh_runtime() {
		self::$detected = null;
		$name           = self::yields_to( 'page_cache' );
		if ( '' === $name && defined( 'WP_CACHE' ) && WP_CACHE && file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			$name = __( 'a page cache drop-in (advanced-cache.php)', 'all-in-one-minifier' );
		}
		if ( AIOM_Settings::runtime()['page_cache_conflict'] !== $name ) {
			AIOM_Settings::set_runtime( array( 'page_cache_conflict' => $name ) );
		}
	}

	/**
	 * Whether the request is a page-builder editor/preview frame or another
	 * request where markup must be left exactly as generated.
	 *
	 * @return bool
	 */
	public static function is_builder_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only detection of editor frames.
		foreach ( array( 'elementor-preview', 'fl_builder', 'et_fb', 'ct_builder', 'vc_editable', 'vcv-editable', 'brizy-edit', 'brizy-edit-iframe', 'tve', 'op3editor', 'uxb_iframe', 'bricks', 'breakdance', 'oxygen_iframe', 'zionbuilder-preview', 'tb-preview', 'customize_changeset_uuid' ) as $param ) {
			if ( isset( $_GET[ $param ] ) ) {
				return true;
			}
		}
		// phpcs:enable
		return false;
	}

	/**
	 * Purge third-party page caches (after this plugin's asset URLs changed).
	 *
	 * @return string[] Names of caches that were purged.
	 */
	public static function purge_all() {
		$done = array();
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$done[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' );
			$done[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$done[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$done[] = 'WP Super Cache';
		}
		if ( class_exists( 'WpFastestCache' ) ) {
			do_action( 'wpfc_clear_all_cache' );
			$done[] = 'WP Fastest Cache';
		}
		if ( defined( 'CACHE_ENABLER_VERSION' ) ) {
			do_action( 'cache_enabler_clear_complete_cache' );
			$done[] = 'Cache Enabler';
		}
		if ( defined( 'BREEZE_VERSION' ) ) {
			do_action( 'breeze_clear_all_cache' );
			$done[] = 'Breeze';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$done[] = 'SiteGround Optimizer';
		}
		if ( defined( 'WPHB_VERSION' ) ) {
			do_action( 'wphb_clear_page_cache' );
			$done[] = 'Hummingbird';
		}
		if ( class_exists( 'Nginx_Helper' ) ) {
			do_action( 'rt_nginx_helper_purge_all' );
			$done[] = 'Nginx Helper';
		}
		/**
		 * Fires after this plugin purged its caches, so other caches (CDN, proxy) can be purged too.
		 */
		do_action( 'aiom_purge_all' );
		return $done;
	}
}
