<?php
/**
 * Per-request decisions: whether to optimise, and which features apply.
 *
 * Evaluated once at template_redirect, when conditional tags work. Every
 * bypass has a short reason code, shown in reports and the admin bar.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request context.
 */
class AIOM_Context {

	const META = '_aiom_flags';

	/**
	 * Per-page flags.
	 */
	const PAGE_FLAGS = array( 'all', 'html', 'css', 'js', 'advanced', 'page_cache' );

	/**
	 * Computed plan, false when bypassed, null before computation.
	 *
	 * @var array|false|null
	 */
	private static $plan = null;

	/**
	 * Reason for bypassing optimisation.
	 *
	 * @var string
	 */
	private static $bypass = '';

	/**
	 * Validated report mode: '', 'off' or 'on'.
	 *
	 * @var string|null
	 */
	private static $report = null;

	/**
	 * Reset (used by tests).
	 */
	public static function reset() {
		self::$plan   = null;
		self::$bypass = '';
		self::$report = null;
	}

	/**
	 * The plan for this request.
	 *
	 * @return array|false
	 */
	public static function plan() {
		if ( null === self::$plan ) {
			self::$plan = self::compute();
		}
		return self::$plan;
	}

	/**
	 * Bypass reason ('' when optimising).
	 *
	 * @return string
	 */
	public static function bypass_reason() {
		self::plan();
		return self::$bypass;
	}

	/**
	 * Report mode for loopback measurement requests carrying a valid, short-lived token.
	 *
	 * @return string '', 'off' (serve unoptimised) or 'on' (optimise, synchronously).
	 */
	public static function report_mode() {
		if ( null !== self::$report ) {
			return self::$report;
		}
		self::$report = '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Authenticated by a random token stored server-side.
		if ( isset( $_GET['aiom_report'], $_GET['aiom_mode'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['aiom_report'] ) );
			$mode  = sanitize_key( wp_unslash( $_GET['aiom_mode'] ) );
			$valid = get_transient( 'aiom_report_token' );
			if ( is_string( $valid ) && strlen( $valid ) >= 32 && hash_equals( $valid, $token ) && in_array( $mode, array( 'off', 'on' ), true ) ) {
				self::$report = $mode;
			}
		}
		// phpcs:enable
		return self::$report;
	}

	/**
	 * Flags set on the queried post.
	 *
	 * @return array flag => true
	 */
	public static function page_flags() {
		$id = (int) get_queried_object_id();
		if ( $id <= 0 || ! ( is_singular() || is_front_page() || is_home() ) ) {
			return array();
		}
		$flags = get_post_meta( $id, self::META, true );
		return is_array( $flags ) ? array_fill_keys( array_intersect( $flags, self::PAGE_FLAGS ), true ) : array();
	}

	/**
	 * Compute the plan.
	 *
	 * @return array|false
	 */
	private static function compute() {
		$reason = self::find_bypass();
		if ( '' !== $reason ) {
			self::$bypass = $reason;
			return false;
		}
		$s     = AIOM_Settings::all();
		$flags = self::page_flags();
		if ( isset( $flags['all'] ) ) {
			self::$bypass = 'page_setting';
			return false;
		}
		$report   = self::report_mode();
		$yield    = $s['auto_compat'] && 'on' !== $report;
		$advanced = ! isset( $flags['advanced'] );
		$critical = trim( $s['css_critical'] . "\n" . self::page_critical_css() );

		$plan = array(
			'html'       => $s['html_minify'] && ! isset( $flags['html'] ) && ! ( $yield && AIOM_Compat::yields_to( 'html' ) ),
			'css'        => $s['css_minify'] && ! isset( $flags['css'] ) && ! ( $yield && AIOM_Compat::yields_to( 'css' ) ),
			'js'         => $s['js_minify'] && ! isset( $flags['js'] ) && ! ( $yield && AIOM_Compat::yields_to( 'js' ) ) && AIOM_JS_Minifier::available(),
			'defer'      => $s['js_defer'] && $advanced && ! isset( $flags['js'] ) && ! ( $yield && AIOM_Compat::yields_to( 'defer' ) ),
			'delay'      => $s['js_delay'] && $advanced && ! isset( $flags['js'] ) && AIOM_Settings::lines( 'js_delay_patterns' ) && ! ( $yield && AIOM_Compat::yields_to( 'delay' ) ),
			'critical'   => $advanced && ! isset( $flags['css'] ) ? $critical : '',
			'async_css'  => $s['css_async'] && $advanced && ! isset( $flags['css'] ) && '' !== $critical,
			'font_swap'  => $s['css_google_fonts_swap'] && ! isset( $flags['css'] ),
			'hints'      => $advanced,
			'page_cache' => $s['page_cache'] && ! isset( $flags['page_cache'] ) && '' === $report && ! $s['test_mode'] && '' === AIOM_Compat::yields_to( 'page_cache' ),
			'report'     => $report,
			'sync'       => 'on' === $report,
			'is_wc_page' => self::is_wc_dynamic_page(),
		);
		if ( $plan['is_wc_page'] ) {
			// Never page-cache carts, checkouts or accounts; keep script timing unchanged there.
			$plan['page_cache'] = false;
			$plan['defer']      = false;
			$plan['delay']      = false;
			$plan['async_css']  = false;
		}
		return $plan;
	}

	/**
	 * Per-page critical CSS.
	 *
	 * @return string
	 */
	private static function page_critical_css() {
		$id = (int) get_queried_object_id();
		if ( $id <= 0 || ! is_singular() ) {
			return '';
		}
		return (string) get_post_meta( $id, '_aiom_critical_css', true );
	}

	/**
	 * WooCommerce cart, checkout and account pages.
	 *
	 * @return bool
	 */
	public static function is_wc_dynamic_page() {
		return ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() );
	}

	/**
	 * First applicable bypass reason, cheapest checks first.
	 *
	 * @return string
	 */
	private static function find_bypass() {
		$report = self::report_mode();
		if ( 'off' === $report ) {
			return 'report_baseline';
		}
		$inactive = AIOM_Settings::inactive_reason();
		if ( '' !== $inactive ) {
			return $inactive;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return 'not_frontend';
		}
		if ( defined( 'DONOTMINIFY' ) && DONOTMINIFY ) {
			return 'donotminify';
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return 'method';
		}
		if ( is_feed() || is_robots() || is_trackback() || is_favicon() || is_embed() ) {
			return 'not_html';
		}
		if ( is_preview() || is_customize_preview() || AIOM_Compat::is_builder_request() ) {
			return 'preview';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the WooCommerce AJAX endpoint.
		if ( isset( $_GET['wc-ajax'] ) ) {
			return 'not_html';
		}
		if ( 'on' !== $report ) {
			if ( AIOM_Settings::get( 'test_mode' ) ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return 'test_mode';
				}
			} elseif ( is_user_logged_in() && ! AIOM_Settings::get( 'optimize_logged_in' ) ) {
				return 'logged_in';
			}
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		if ( AIOM_Rules::match_url( AIOM_Settings::lines( 'exclude_urls' ), $uri ) ) {
			return 'excluded_url';
		}
		$types = (array) AIOM_Settings::get( 'exclude_post_types' );
		if ( $types && is_singular( $types ) ) {
			return 'excluded_post_type';
		}
		if ( AIOM_Settings::get( 'wc_bypass' ) && self::is_wc_dynamic_page() ) {
			return 'woocommerce';
		}
		return '';
	}
}
