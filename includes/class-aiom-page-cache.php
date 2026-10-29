<?php
/**
 * Optional page cache for anonymous visitors (off by default).
 *
 * Served while plugins load, before the theme runs. A page is only cached and
 * only served when the request carries no session/cart/login cookies, has no
 * query string other than ignorable tracking parameters, is a GET/HEAD for this
 * site's own host, and the generated response was a plain 200 HTML page that
 * set no cookies and was not marked DONOTCACHEPAGE. Personalised pages (carts,
 * checkouts, accounts, password-protected posts, previews, searches, 404s) are
 * never stored. Files are sent with readfile(), never executed.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page cache.
 */
class AIOM_Page_Cache {

	/**
	 * Cookies (prefixes) that indicate a personalised session.
	 */
	const BYPASS_COOKIES = array(
		'wordpress_logged_in_',
		'wordpress_sec_',
		'wp-postpass_',
		'comment_author_',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session_',
		'edd_items_in_cart',
		'wordpress_no_cache',
	);

	/**
	 * Response headers worth replaying from cache.
	 */
	const REPLAY_HEADERS = array( 'link', 'x-pingback', 'content-language', 'x-robots-tag' );

	/**
	 * Request key: false = not computed, null = not cacheable.
	 *
	 * @var string|null|false
	 */
	private static $key = false;

	/**
	 * Reset (tests).
	 */
	public static function reset() {
		self::$key = false;
	}

	/**
	 * Serve a cached page and exit, if one is valid for this request.
	 *
	 * Runs while plugins load — on multisite, network-activated plugins load before
	 * parts of WordPress (vars.php, pluggable functions). Anything unexpected here
	 * falls back to normal page generation instead of breaking the request.
	 */
	public static function serve() {
		try {
			self::try_serve();
		} catch ( Throwable $e ) {
			self::$key = false;
		}
	}

	/**
	 * Serve implementation.
	 */
	private static function try_serve() {
		if ( ! AIOM_Settings::get( 'page_cache' ) || AIOM_Settings::get( 'test_mode' ) || '' !== AIOM_Settings::inactive_reason() ) {
			return;
		}
		if ( '' !== AIOM_Settings::runtime()['page_cache_conflict'] ) {
			return;
		}
		$key = self::request_key();
		if ( ! $key ) {
			return;
		}
		$file  = self::file( $key );
		$mtime = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $mtime || $mtime + (int) AIOM_Settings::get( 'page_cache_ttl' ) * HOUR_IN_SECONDS < time() ) {
			return;
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mtime ) . ' GMT' );
			header( 'X-AIOM-Cache: HIT' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$meta = json_decode( (string) @file_get_contents( $file . '.meta' ), true );
			if ( is_array( $meta ) && ! empty( $meta['headers'] ) && is_array( $meta['headers'] ) ) {
				foreach ( $meta['headers'] as $h ) {
					header( (string) $h, false );
				}
			}
		}
		AIOM_Stats::bump( 'page_hits' );
		if ( 'HEAD' !== self::method() ) {
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		}
		exit;
	}

	/**
	 * Request method.
	 *
	 * @return string
	 */
	private static function method() {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	}

	/**
	 * Cache key for the current request, or null if the request must not use the cache.
	 *
	 * @return string|null
	 */
	public static function request_key() {
		if ( false !== self::$key ) {
			return self::$key;
		}
		self::$key = null;
		$method    = self::method();
		if ( ( 'GET' !== $method && 'HEAD' !== $method ) || is_admin() || wp_doing_ajax() || wp_doing_cron()
			|| ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return null;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below; used only for hashing and matching.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path || '/' !== $path[0] || false !== stripos( $path, '.php' ) || preg_match( '#/(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)|\.\.|[\x00-\x1f]#i', $path ) ) {
			return null;
		}
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only parameter names are inspected.
		if ( '' !== $query ) {
			parse_str( $query, $params );
			foreach ( array_keys( $params ) as $name ) {
				if ( ! self::ignored_param( (string) $name ) ) {
					return null;
				}
			}
		}
		$prefixes = array_merge( self::BYPASS_COOKIES, AIOM_Settings::lines( 'page_cache_cookies' ) );
		foreach ( array_keys( $_COOKIE ) as $name ) {
			foreach ( $prefixes as $p ) {
				if ( 0 === strpos( (string) $name, $p ) ) {
					return null;
				}
			}
		}
		$host = self::host();
		if ( '' === $host ) {
			return null;
		}
		if ( AIOM_Rules::match_url( AIOM_Settings::lines( 'page_cache_exclude' ), $uri ) || AIOM_Rules::match_url( AIOM_Settings::lines( 'exclude_urls' ), $uri ) ) {
			return null;
		}
		$mobile    = AIOM_Settings::get( 'page_cache_mobile' ) && self::is_mobile();
		self::$key = self::key( is_ssl(), $host, $path, $mobile );
		return self::$key;
	}

	/**
	 * Mobile detection with the same rules as wp_is_mobile(), usable before WordPress
	 * has loaded vars.php (network-activated plugins on multisite load earlier).
	 * Serving and storing both use this, so cache variants always match.
	 *
	 * @return bool
	 */
	public static function is_mobile() {
		if ( isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ) {
			return '?1' === $_SERVER['HTTP_SEC_CH_UA_MOBILE'];
		}
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$ua = (string) $_SERVER['HTTP_USER_AGENT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only searched for fixed substrings.
		foreach ( array( 'Mobile', 'Android', 'Silk/', 'Kindle', 'BlackBerry', 'Opera Mini', 'Opera Mobi' ) as $needle ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The request host, only if it is this site's host.
	 *
	 * @return string
	 */
	private static function host() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return self::site_host() === $host ? $host : '';
	}

	/**
	 * This site's host[:port] from the Home URL.
	 *
	 * @return string
	 */
	private static function site_host() {
		$home = wp_parse_url( home_url() );
		return strtolower( (string) ( isset( $home['host'] ) ? $home['host'] : '' ) ) . ( isset( $home['port'] ) ? ':' . (int) $home['port'] : '' );
	}

	/**
	 * Cache key.
	 *
	 * @param bool   $https  HTTPS.
	 * @param string $host   Host.
	 * @param string $path   Path.
	 * @param bool   $mobile Mobile variant.
	 * @return string
	 */
	private static function key( $https, $host, $path, $mobile ) {
		return md5( ( $https ? 'https' : 'http' ) . '://' . $host . $path . '|' . ( $mobile ? 'mobile' : 'desktop' ) );
	}

	/**
	 * Whether a query parameter may be ignored (tracking parameters).
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	private static function ignored_param( $name ) {
		foreach ( AIOM_Settings::lines( 'page_cache_ignore_params' ) as $p ) {
			if ( $p === $name || ( '*' === substr( $p, -1 ) && 0 === strpos( $name, substr( $p, 0, -1 ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * File for a key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function file( $key ) {
		return AIOM_Cache_Dir::base() . 'pages/' . substr( $key, 0, 2 ) . '/' . $key . '.html';
	}

	/**
	 * Store the generated page if this response is safe to share between visitors.
	 *
	 * @param string $html Final HTML.
	 * @return bool
	 */
	public static function maybe_store( $html ) {
		$key = self::request_key();
		if ( ! $key || is_user_logged_in() || 200 !== http_response_code() || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
			return false;
		}
		if ( is_404() || is_search() || is_preview() || is_feed() || is_trackback() || is_robots() || is_customize_preview() || ( is_singular() && post_password_required() ) ) {
			return false;
		}
		if ( AIOM_Context::is_wc_dynamic_page() || AIOM_Assets::has_pending() ) {
			return false;
		}
		$replay = array();
		foreach ( headers_list() as $h ) {
			$name = strtolower( trim( strtok( $h, ':' ) ) );
			if ( 'set-cookie' === $name || ( 'content-security-policy' === $name && false !== stripos( $h, 'nonce-' ) ) ) {
				return false;
			}
			if ( in_array( $name, self::REPLAY_HEADERS, true ) ) {
				$replay[] = $h;
			}
		}
		if ( ! AIOM_Cache_Dir::ensure( 'pages/' . substr( $key, 0, 2 ) ) ) {
			return false;
		}
		$file = self::file( $key );
		$meta = array(
			'url'     => ( is_ssl() ? 'https://' : 'http://' ) . self::site_host() . (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH ),
			'time'    => time(),
			'mobile'  => AIOM_Settings::get( 'page_cache_mobile' ) && self::is_mobile(),
			'headers' => $replay,
		);
		return AIOM_Cache_Dir::write( $file . '.meta', wp_json_encode( $meta ) ) && AIOM_Cache_Dir::write( $file, $html );
	}

	/**
	 * Purge every variant of a URL.
	 *
	 * @param string $url URL.
	 * @return int Files deleted.
	 */
	public static function purge_url( $url ) {
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) ) {
			return 0;
		}
		$host  = strtolower( $p['host'] ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
		$path  = isset( $p['path'] ) && '' !== $p['path'] ? $p['path'] : '/';
		$count = 0;
		foreach ( array( true, false ) as $https ) {
			foreach ( array( true, false ) as $mobile ) {
				$file = self::file( self::key( $https, $host, $path, $mobile ) );
				if ( is_file( $file ) && @unlink( $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					++$count;
				}
				if ( is_file( $file . '.meta' ) ) {
					@unlink( $file . '.meta' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}
		return $count;
	}

	/**
	 * Purge all cached pages.
	 *
	 * @return int Files deleted.
	 */
	public static function purge_all() {
		$dir = AIOM_Cache_Dir::base() . 'pages';
		return is_dir( $dir ) ? AIOM_Cache_Dir::delete_tree( $dir, true ) : 0;
	}

	/**
	 * Cached state of a URL (desktop variant).
	 *
	 * @param string $url URL.
	 * @return int|false Cache time, or false.
	 */
	public static function cached_at( $url ) {
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) ) {
			return false;
		}
		$host = strtolower( $p['host'] ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
		$file = self::file( self::key( 'https' === ( isset( $p['scheme'] ) ? $p['scheme'] : '' ), $host, isset( $p['path'] ) && '' !== $p['path'] ? $p['path'] : '/', false ) );
		$time = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return ( $time && $time + (int) AIOM_Settings::get( 'page_cache_ttl' ) * HOUR_IN_SECONDS >= time() ) ? $time : false;
	}

	/**
	 * Delete expired pages.
	 *
	 * @return int Files deleted.
	 */
	public static function expire() {
		$dir = AIOM_Cache_Dir::base() . 'pages';
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$limit = time() - (int) AIOM_Settings::get( 'page_cache_ttl' ) * HOUR_IN_SECONDS;
		$count = 0;
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( $f->isFile() && '.html' === substr( $f->getFilename(), -5 ) && $f->getMTime() < $limit ) {
				@unlink( $f->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink( $f->getPathname() . '.meta' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$count;
			}
		}
		return $count;
	}
}
