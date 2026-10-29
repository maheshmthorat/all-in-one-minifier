<?php
/**
 * Admin controller: menu, screens, form actions and AJAX endpoints.
 *
 * Every state-changing action requires the manage_options capability and a
 * valid nonce. There are no unauthenticated endpoints.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class AIOM_Admin {

	const CAP = 'manage_options';

	/**
	 * Screens: slug => tab id.
	 */
	const PAGES = array(
		'aiom'          => 'dashboard',
		'aiom-settings' => 'settings',
		'aiom-cache'    => 'cache',
		'aiom-reports'  => 'reports',
		'aiom-tools'    => 'tools',
		'aiom-about'    => 'about',
	);

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AIOM_FILE ), array( __CLASS__, 'action_links' ) );
		$actions = array( 'save_settings', 'recommended', 'reset', 'clear', 'purge_url', 'safe_mode', 'pause', 'export', 'import', 'repair', 'clear_log', 'delete_reports', 'reset_stats', 'maintenance', 'loopback' );
		foreach ( $actions as $a ) {
			add_action( 'admin_post_aiom_' . $a, array( __CLASS__, 'action_' . $a ) );
		}
		add_action( 'wp_ajax_aiom_run_report', array( __CLASS__, 'ajax_run_report' ) );
		add_action( 'wp_ajax_aiom_preload', array( __CLASS__, 'ajax_preload' ) );
	}

	/**
	 * Upgrade routines and conflict detection.
	 */
	public static function admin_init() {
		AIOM_Install::maybe_upgrade();
		AIOM_Compat::refresh_runtime();
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		$title = __( 'All in one Minifier', 'all-in-one-minifier' );
		add_menu_page( $title, __( 'AIO Minifier', 'all-in-one-minifier' ), self::CAP, 'aiom', array( __CLASS__, 'render' ), 'dashicons-performance', 81 );
		$labels = array(
			'aiom'          => __( 'Dashboard', 'all-in-one-minifier' ),
			'aiom-settings' => __( 'Settings', 'all-in-one-minifier' ),
			'aiom-cache'    => __( 'Cache', 'all-in-one-minifier' ),
			'aiom-reports'  => __( 'Reports', 'all-in-one-minifier' ),
			'aiom-tools'    => __( 'Tools', 'all-in-one-minifier' ),
			'aiom-about'    => __( 'About', 'all-in-one-minifier' ),
		);
		foreach ( $labels as $slug => $label ) {
			add_submenu_page( 'aiom', $label . ' ‹ ' . $title, $label, self::CAP, $slug, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Assets for the plugin screens only.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function enqueue( $hook ) {
		$page = self::current_page();
		if ( ! $page && ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'aiom-admin', AIOM_URL . 'admin/assets/admin.css', array(), AIOM_VERSION );
		if ( $page ) {
			wp_enqueue_script( 'aiom-admin', AIOM_URL . 'admin/assets/admin.js', array(), AIOM_VERSION, array( 'in_footer' => true ) );
			wp_localize_script(
				'aiom-admin',
				'aiomAdmin',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'aiom_ajax' ),
					'i18n'    => array(
						'running'  => __( 'Measuring… this takes 10–60 seconds.', 'all-in-one-minifier' ),
						'failed'   => __( 'The request failed.', 'all-in-one-minifier' ),
						/* translators: 1: current page number, 2: total pages. */
						'preload'  => __( 'Preloading %1$d of %2$d…', 'all-in-one-minifier' ),
						/* translators: 1: pages preloaded, 2: errors. */
						'preloadD' => __( 'Preloaded %1$d pages (%2$d errors).', 'all-in-one-minifier' ),
					),
				)
			);
		}
	}

	/**
	 * Current plugin page slug, or ''.
	 *
	 * @return string
	 */
	public static function current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return isset( self::PAGES[ $page ] ) ? $page : '';
	}

	/**
	 * Plugins screen links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=aiom-settings' ) ) . '">' . esc_html__( 'Settings', 'all-in-one-minifier' ) . '</a>',
			'<a href="https://buymeacoffee.com/maheshmthorat" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Say thanks', 'all-in-one-minifier' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Render a screen.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'all-in-one-minifier' ) );
		}
		$page = self::current_page();
		$tab  = $page ? self::PAGES[ $page ] : 'dashboard';
		include AIOM_DIR . 'admin/views/layout.php';
	}

	/**
	 * Overall status label.
	 *
	 * @return string
	 */
	public static function status_label() {
		$reason = AIOM_Settings::inactive_reason();
		if ( 'safe_mode' === $reason ) {
			return __( 'Safe mode: all optimisation is off', 'all-in-one-minifier' );
		}
		if ( 'paused' === $reason ) {
			/* translators: %s: time. */
			return sprintf( __( 'Paused until %s', 'all-in-one-minifier' ), wp_date( get_option( 'time_format' ), (int) AIOM_Settings::runtime()['paused_until'] ) );
		}
		if ( 'disabled' === $reason ) {
			return __( 'Optimisation is turned off', 'all-in-one-minifier' );
		}
		if ( 'constant' === $reason ) {
			return __( 'Disabled by the AIOM_DISABLE constant', 'all-in-one-minifier' );
		}
		if ( AIOM_Settings::get( 'test_mode' ) ) {
			return __( 'Test mode: only administrators see optimised pages', 'all-in-one-minifier' );
		}
		return __( 'Active', 'all-in-one-minifier' );
	}

	/**
	 * Status slug for styling: ok | test | off.
	 *
	 * @return string
	 */
	public static function status_slug() {
		if ( '' !== AIOM_Settings::inactive_reason() ) {
			return 'off';
		}
		return AIOM_Settings::get( 'test_mode' ) ? 'test' : 'ok';
	}

	/**
	 * Human label for a bypass reason code.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function reason_label( $code ) {
		$labels = array(
			'report_baseline'    => __( 'report baseline request', 'all-in-one-minifier' ),
			'constant'           => __( 'AIOM_DISABLE constant', 'all-in-one-minifier' ),
			'disabled'           => __( 'optimisation turned off', 'all-in-one-minifier' ),
			'safe_mode'          => __( 'safe mode', 'all-in-one-minifier' ),
			'paused'             => __( 'paused', 'all-in-one-minifier' ),
			'not_frontend'       => __( 'not a front-end page', 'all-in-one-minifier' ),
			'donotminify'        => __( 'DONOTMINIFY constant', 'all-in-one-minifier' ),
			'method'             => __( 'not a GET request', 'all-in-one-minifier' ),
			'not_html'           => __( 'not an HTML page', 'all-in-one-minifier' ),
			'preview'            => __( 'preview or page-builder editor', 'all-in-one-minifier' ),
			'test_mode'          => __( 'test mode', 'all-in-one-minifier' ),
			'logged_in'          => __( 'you are logged in; enable “Optimise for logged-in users” or test mode to see optimised pages', 'all-in-one-minifier' ),
			'excluded_url'       => __( 'URL excluded', 'all-in-one-minifier' ),
			'excluded_post_type' => __( 'post type excluded', 'all-in-one-minifier' ),
			'woocommerce'        => __( 'WooCommerce cart/checkout/account page', 'all-in-one-minifier' ),
			'page_setting'       => __( 'disabled for this page', 'all-in-one-minifier' ),
		);
		return isset( $labels[ $code ] ) ? $labels[ $code ] : $code;
	}

	/**
	 * Verify capability and nonce for an admin-post action.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'all-in-one-minifier' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect back to a screen with a notice code.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice code.
	 * @param array  $args   Extra query args.
	 */
	private static function back( $page, $notice, array $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'aiom_notice' => $notice ), $args ), admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	/**
	 * Page slug posted with a form (validated).
	 *
	 * @param string $default Default.
	 * @return string
	 */
	private static function posted_page( $default ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Verified in guard() by every caller.
		$page = isset( $_REQUEST['aiom_page'] ) ? sanitize_key( wp_unslash( $_REQUEST['aiom_page'] ) ) : $default;
		return isset( self::PAGES[ $page ] ) ? $page : $default;
	}

	/**
	 * After any change that alters generated HTML: drop cached pages here and in other caches.
	 */
	private static function purge_after_change() {
		AIOM_Page_Cache::purge_all();
		AIOM_Compat::purge_all();
	}

	/**
	 * Save settings.
	 */
	public static function action_save_settings() {
		self::guard( 'aiom_save_settings' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Nonce verified in guard(); sanitised field by field in AIOM_Settings::sanitize().
		$input = isset( $_POST['aiom'] ) && is_array( $_POST['aiom'] ) ? wp_unslash( $_POST['aiom'] ) : array();
		AIOM_Settings::save( AIOM_Settings::sanitize( $input, true ) );
		AIOM_Compat::refresh_runtime();
		self::purge_after_change();
		self::back( 'aiom-settings', 'saved' );
	}

	/**
	 * Apply recommended settings: defaults for every switch and number; rules and critical CSS are kept.
	 */
	public static function action_recommended() {
		self::guard( 'aiom_recommended' );
		$values = AIOM_Settings::all();
		foreach ( AIOM_Settings::DEFAULTS as $key => $default ) {
			if ( is_bool( $default ) || is_int( $default ) ) {
				$values[ $key ] = $default;
			}
		}
		AIOM_Settings::save( $values );
		self::purge_after_change();
		self::back( self::posted_page( 'aiom-settings' ), 'recommended' );
	}

	/**
	 * Restore all defaults.
	 */
	public static function action_reset() {
		self::guard( 'aiom_reset' );
		AIOM_Settings::save( AIOM_Settings::DEFAULTS );
		self::purge_after_change();
		self::back( 'aiom-tools', 'reset' );
	}

	/**
	 * Clear caches.
	 */
	public static function action_clear() {
		self::guard( 'aiom_clear' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$scope = isset( $_REQUEST['scope'] ) ? sanitize_key( wp_unslash( $_REQUEST['scope'] ) ) : 'all';
		$scope = in_array( $scope, array( 'all', 'pages', 'assets' ), true ) ? $scope : 'all';
		$r     = AIOM_Maintenance::clear( $scope );
		set_transient( 'aiom_last_clear_' . get_current_user_id(), $r, 60 );
		$ref = wp_get_referer();
		if ( $ref && false === strpos( $ref, '/wp-admin/' ) ) {
			wp_safe_redirect( $ref );
			exit;
		}
		self::back( self::posted_page( 'aiom-cache' ), 'cleared_' . $scope );
	}

	/**
	 * Purge one URL from the page cache (admin bar), then return to it.
	 */
	public static function action_purge_url() {
		self::guard( 'aiom_purge_url' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$url = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
		if ( '' !== $url ) {
			AIOM_Page_Cache::purge_url( $url );
		}
		wp_safe_redirect( '' !== $url ? $url : admin_url( 'admin.php?page=aiom-cache' ) );
		exit;
	}

	/**
	 * Toggle safe mode.
	 */
	public static function action_safe_mode() {
		self::guard( 'aiom_safe_mode' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$on = ! empty( $_POST['enable'] );
		AIOM_Settings::set_runtime(
			array(
				'safe_mode'    => $on,
				'paused_until' => 0,
			)
		);
		AIOM_Page_Cache::purge_all();
		self::back( self::posted_page( 'aiom-tools' ), $on ? 'safe_on' : 'safe_off' );
	}

	/**
	 * Pause optimisation temporarily (or resume).
	 */
	public static function action_pause() {
		self::guard( 'aiom_pause' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$hours = isset( $_POST['hours'] ) ? absint( $_POST['hours'] ) : 0;
		$hours = min( 72, $hours );
		AIOM_Settings::set_runtime( array( 'paused_until' => $hours ? time() + $hours * HOUR_IN_SECONDS : 0 ) );
		AIOM_Page_Cache::purge_all();
		self::back( self::posted_page( 'aiom-tools' ), $hours ? 'paused' : 'resumed' );
	}

	/**
	 * Download settings as JSON.
	 */
	public static function action_export() {
		self::guard( 'aiom_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="all-in-one-minifier-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode(
			array(
				'plugin'   => 'all-in-one-minifier',
				'version'  => AIOM_VERSION,
				'exported' => gmdate( 'c' ),
				'settings' => AIOM_Settings::all(),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		exit;
	}

	/**
	 * Import settings from a JSON file or pasted text.
	 */
	public static function action_import() {
		self::guard( 'aiom_import' );
		$json = '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( ! empty( $_FILES['aiom_file']['tmp_name'] ) && is_uploaded_file( $_FILES['aiom_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( isset( $_FILES['aiom_file']['size'] ) && (int) $_FILES['aiom_file']['size'] <= 262144 ) {
				$json = (string) file_get_contents( $_FILES['aiom_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		} elseif ( isset( $_POST['aiom_json'] ) ) {
			$json = (string) wp_unslash( $_POST['aiom_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded and sanitised below.
		}
		// phpcs:enable
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			self::back( 'aiom-tools', 'import_invalid' );
		}
		AIOM_Settings::save( AIOM_Settings::sanitize( $data['settings'], false ) );
		AIOM_Compat::refresh_runtime();
		self::purge_after_change();
		self::back( 'aiom-tools', 'imported' );
	}

	/**
	 * Repair the cache directory.
	 */
	public static function action_repair() {
		self::guard( 'aiom_repair' );
		$r = AIOM_Maintenance::repair();
		self::back( 'aiom-tools', $r['writable'] ? 'repaired' : 'repair_failed' );
	}

	/**
	 * Clear the diagnostic log.
	 */
	public static function action_clear_log() {
		self::guard( 'aiom_clear_log' );
		AIOM_Log::clear();
		self::back( 'aiom-tools', 'log_cleared' );
	}

	/**
	 * Delete all reports.
	 */
	public static function action_delete_reports() {
		self::guard( 'aiom_delete_reports' );
		AIOM_Reports::delete_all();
		self::back( 'aiom-reports', 'reports_deleted' );
	}

	/**
	 * Reset statistics.
	 */
	public static function action_reset_stats() {
		self::guard( 'aiom_reset_stats' );
		AIOM_Stats::reset();
		self::back( 'aiom-cache', 'stats_reset' );
	}

	/**
	 * Run maintenance now.
	 */
	public static function action_maintenance() {
		self::guard( 'aiom_maintenance' );
		AIOM_Maintenance::run();
		self::back( 'aiom-cache', 'maintenance' );
	}

	/**
	 * Test that the server can request its own pages.
	 */
	public static function action_loopback() {
		self::guard( 'aiom_loopback' );
		$start  = microtime( true );
		$r      = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			)
		);
		$result = is_wp_error( $r ) ? array(
			'ok'      => false,
			'message' => $r->get_error_message(),
		) : array(
			'ok'      => 200 === (int) wp_remote_retrieve_response_code( $r ),
			/* translators: 1: HTTP status, 2: milliseconds. */
			'message' => sprintf( __( 'HTTP %1$d in %2$d ms', 'all-in-one-minifier' ), (int) wp_remote_retrieve_response_code( $r ), (int) ( ( microtime( true ) - $start ) * 1000 ) ),
		);
		set_transient( 'aiom_loopback', $result + array( 'time' => time() ), DAY_IN_SECONDS );
		self::back( 'aiom-tools', $result['ok'] ? 'loopback_ok' : 'loopback_failed' );
	}

	/**
	 * AJAX: run a report.
	 */
	public static function ajax_run_report() {
		check_ajax_referer( 'aiom_ajax', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'all-in-one-minifier' ) ), 403 );
		}
		$url    = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$report = AIOM_Reports::run( $url );
		if ( is_wp_error( $report ) ) {
			wp_send_json_error( array( 'message' => $report->get_error_message() ) );
		}
		ob_start();
		include AIOM_DIR . 'admin/views/report-card.php';
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * AJAX: request one page anonymously so it gets optimised (and page-cached).
	 */
	public static function ajax_preload() {
		check_ajax_referer( 'aiom_ajax', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'all-in-one-minifier' ) ), 403 );
		}
		$url = AIOM_Reports::validate_url( isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '' );
		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ) );
		}
		$r = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			)
		);
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( array( 'code' => (int) wp_remote_retrieve_response_code( $r ) ) );
	}

	/**
	 * URLs worth preloading/reporting: home, posts page, recent content.
	 *
	 * @param int $limit Max URLs.
	 * @return array label => url
	 */
	public static function candidate_urls( $limit = 40 ) {
		$urls = array( home_url( '/' ) => __( 'Home page', 'all-in-one-minifier' ) );
		if ( get_option( 'page_for_posts' ) ) {
			$urls[ get_permalink( (int) get_option( 'page_for_posts' ) ) ] = __( 'Posts page', 'all-in-one-minifier' );
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$urls[ wc_get_page_permalink( 'shop' ) ] = __( 'Shop', 'all-in-one-minifier' );
		}
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );
		$posts = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'orderby'          => 'modified',
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $p ) {
			$link = get_permalink( $p );
			if ( $link && ! isset( $urls[ $link ] ) ) {
				$type          = get_post_type_object( $p->post_type );
				$urls[ $link ] = ( $type ? $type->labels->singular_name . ': ' : '' ) . wp_strip_all_tags( get_the_title( $p ) );
			}
		}
		return array_slice( $urls, 0, $limit, true );
	}

	/**
	 * Format bytes.
	 *
	 * @param int|float $bytes Bytes.
	 * @return string
	 */
	public static function bytes( $bytes ) {
		return size_format( max( 0, (float) $bytes ), $bytes >= 1048576 ? 1 : 0 );
	}

	/**
	 * Percentage change label ("-34%").
	 *
	 * @param int|float $before Before.
	 * @param int|float $after  After.
	 * @return string
	 */
	public static function delta( $before, $after ) {
		if ( ! $before ) {
			return '—';
		}
		$pct = ( $after - $before ) / $before * 100;
		return ( $pct > 0 ? '+' : '' ) . number_format_i18n( $pct, abs( $pct ) < 10 ? 1 : 0 ) . '%';
	}

	/**
	 * Notice messages.
	 *
	 * @return array code => array( type, message )
	 */
	public static function notices() {
		return array(
			'saved'           => array( 'success', __( 'Settings saved. Cached pages were cleared so visitors get the new output.', 'all-in-one-minifier' ) ),
			'recommended'     => array( 'success', __( 'Recommended settings applied. Your exclusion rules and critical CSS were kept.', 'all-in-one-minifier' ) ),
			'reset'           => array( 'success', __( 'All settings were restored to their defaults.', 'all-in-one-minifier' ) ),
			'cleared_all'     => array( 'success', __( 'All cache cleared. Files are regenerated as pages are visited.', 'all-in-one-minifier' ) ),
			'cleared_pages'   => array( 'success', __( 'Page cache purged.', 'all-in-one-minifier' ) ),
			'cleared_assets'  => array( 'success', __( 'Optimised CSS/JS files cleared. They are regenerated as pages are visited.', 'all-in-one-minifier' ) ),
			'safe_on'         => array( 'warning', __( 'Safe mode is on: every optimisation is off for all visitors. Your settings are unchanged.', 'all-in-one-minifier' ) ),
			'safe_off'        => array( 'success', __( 'Safe mode is off. Optimisation resumed.', 'all-in-one-minifier' ) ),
			'paused'          => array( 'warning', __( 'Optimisation is paused. It resumes automatically at the time shown.', 'all-in-one-minifier' ) ),
			'resumed'         => array( 'success', __( 'Optimisation resumed.', 'all-in-one-minifier' ) ),
			'imported'        => array( 'success', __( 'Settings imported.', 'all-in-one-minifier' ) ),
			'import_invalid'  => array( 'error', __( 'That file is not a settings export from this plugin.', 'all-in-one-minifier' ) ),
			'repaired'        => array( 'success', __( 'Cache directories were checked and repaired. Failure markers were removed so failed files are retried.', 'all-in-one-minifier' ) ),
			'repair_failed'   => array( 'error', __( 'The cache directory wp-content/cache/aiom/ is not writable. Ask your host to allow PHP to write to wp-content/cache.', 'all-in-one-minifier' ) ),
			'log_cleared'     => array( 'success', __( 'Log cleared.', 'all-in-one-minifier' ) ),
			'reports_deleted' => array( 'success', __( 'All reports deleted.', 'all-in-one-minifier' ) ),
			'stats_reset'     => array( 'success', __( 'Statistics reset.', 'all-in-one-minifier' ) ),
			'maintenance'     => array( 'success', __( 'Maintenance completed.', 'all-in-one-minifier' ) ),
			'loopback_ok'     => array( 'success', __( 'Loopback request succeeded: reports and preloading will work.', 'all-in-one-minifier' ) ),
			'loopback_failed' => array( 'error', __( 'Loopback request failed: this server cannot request its own pages, so reports and preloading will not work. See the diagnostics below.', 'all-in-one-minifier' ) ),
		);
	}
}
