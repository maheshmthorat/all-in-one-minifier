<?php
/**
 * Before/after measurement reports.
 *
 * An administrator triggers a report; the server then requests the page over
 * HTTP as an anonymous visitor, alternating "original" (all optimisation off)
 * and "optimised" requests, three times each after a warm-up. Every number in
 * a report is measured on this server:
 * - HTML/CSS/JS bytes are the actual response and file sizes; gzip sizes are
 *   computed locally (labelled as estimates of transfer size).
 * - TTFB is curl's time-to-first-byte (or wall time when curl is unavailable),
 *   median of the runs, measured from the server to itself — it excludes
 *   network latency and does not represent a visitor's full page load.
 * No Lighthouse scores or Core Web Vitals are produced or estimated.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reports.
 */
class AIOM_Reports {

	const RUNS         = 3;
	const KEEP_PER_URL = 10;
	const KEEP_TOTAL   = 300;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'aiom_reports';
	}

	/**
	 * Called by the optimised loopback request with what it did.
	 *
	 * Data is handed over through a private file rather than a transient: the
	 * runner deletes the previous data at the start of its own request, after
	 * which WordPress' options cache would keep reporting it as missing.
	 *
	 * @param array $data Measurements.
	 */
	public static function record_request( array $data ) {
		if ( AIOM_Cache_Dir::ensure( 'stats' ) ) {
			AIOM_Cache_Dir::write( self::data_file(), wp_json_encode( $data ) );
		}
	}

	/**
	 * Hand-over file for the current token.
	 *
	 * @return string
	 */
	private static function data_file() {
		return AIOM_Cache_Dir::base() . 'stats/report-' . md5( self::token() ) . '.json';
	}

	/**
	 * Read and remove the hand-over data.
	 *
	 * @return array
	 */
	private static function take_data() {
		$file = self::data_file();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) @file_get_contents( $file ), true );
		@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Short-lived token that authorises loopback measurement requests.
	 *
	 * @return string
	 */
	public static function token() {
		$t = get_transient( 'aiom_report_token' );
		if ( ! is_string( $t ) || strlen( $t ) < 32 ) {
			$t = wp_generate_password( 40, false, false );
			set_transient( 'aiom_report_token', $t, 15 * MINUTE_IN_SECONDS );
		}
		return $t;
	}

	/**
	 * Accept only http(s) URLs on this site's host.
	 *
	 * @param string $url URL.
	 * @return string|WP_Error
	 */
	public static function validate_url( $url ) {
		$url  = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		$p    = wp_parse_url( $url );
		$home = wp_parse_url( home_url() );
		if ( empty( $p['host'] ) || strtolower( $p['host'] ) !== strtolower( (string) $home['host'] ) || ( isset( $p['port'] ) ? (int) $p['port'] : null ) !== ( isset( $home['port'] ) ? (int) $home['port'] : null ) ) {
			return new WP_Error( 'aiom_url', __( 'Reports can only be run for pages on this site.', 'all-in-one-minifier' ) );
		}
		return $url;
	}

	/**
	 * Run a report.
	 *
	 * @param string $url URL on this site.
	 * @return array|WP_Error
	 */
	public static function run( $url ) {
		$url = self::validate_url( $url );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		if ( get_transient( 'aiom_report_running' ) ) {
			return new WP_Error( 'aiom_busy', __( 'Another report is running. Please wait a minute and try again.', 'all-in-one-minifier' ) );
		}
		set_transient( 'aiom_report_running', 1, 150 );
		try {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 150 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			$token = self::token();
			self::take_data();
			$warm = self::fetch( $url, 'on', $token );
			if ( is_wp_error( $warm ) ) {
				return $warm;
			}
			if ( 200 !== $warm['code'] ) {
				/* translators: %d: HTTP status code. */
				return new WP_Error( 'aiom_status', sprintf( __( 'The page answered with HTTP status %d. Reports need a page that returns 200.', 'all-in-one-minifier' ), $warm['code'] ) );
			}
			$original  = array();
			$optimised = array();
			for ( $i = 0; $i < self::RUNS; $i++ ) {
				$a = self::fetch( $url, 'off', $token );
				$b = self::fetch( $url, 'on', $token );
				if ( is_wp_error( $a ) ) {
					return $a;
				}
				if ( is_wp_error( $b ) ) {
					return $b;
				}
				$original[]  = $a;
				$optimised[] = $b;
			}
			$data         = self::take_data();
			$report       = self::summarise( $url, $original, $optimised, self::measure_page_cache( $url ), $data );
			$report['id'] = self::save( $report );
			return $report;
		} finally {
			delete_transient( 'aiom_report_running' );
		}
	}

	/**
	 * One loopback request.
	 *
	 * @param string $url   URL.
	 * @param string $mode  'off', 'on' or '' (plain visitor request).
	 * @param string $token Token.
	 * @return array|WP_Error
	 */
	private static function fetch( $url, $mode, $token ) {
		$target = '' !== $mode ? add_query_arg(
			array(
				'aiom_report' => $token,
				'aiom_mode'   => $mode,
			),
			$url
		) : $url;
		/**
		 * Filters the URL used for loopback measurement requests (e.g. to use an internal hostname).
		 *
		 * @param string $target URL.
		 */
		$target = apply_filters( 'aiom_loopback_url', $target );
		$info   = array();
		$grab   = static function ( $headers, $curl_info = null ) use ( &$info ) {
			if ( is_array( $curl_info ) ) {
				$info = $curl_info;
			}
		};
		add_action( 'requests-curl.after_request', $grab, 10, 2 );
		$start = microtime( true );
		$r     = wp_remote_get(
			$target,
			array(
				'timeout'     => 45,
				'redirection' => 0,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter for loopbacks.
				'user-agent'  => 'Mozilla/5.0 (compatible; AIOM-Report/' . AIOM_VERSION . '; +' . home_url( '/' ) . ')',
				'headers'     => array(
					'Accept'        => 'text/html',
					'Cache-Control' => 'no-cache',
				),
			)
		);
		$wall  = ( microtime( true ) - $start ) * 1000;
		remove_action( 'requests-curl.after_request', $grab, 10 );
		if ( is_wp_error( $r ) ) {
			/* translators: %s: error message. */
			return new WP_Error( 'aiom_loopback', sprintf( __( 'The server could not request its own page (loopback request failed): %s', 'all-in-one-minifier' ), $r->get_error_message() ) );
		}
		$ttfb = isset( $info['starttransfer_time'] ) && $info['starttransfer_time'] > 0;
		return array(
			'code'     => (int) wp_remote_retrieve_response_code( $r ),
			'ttfb'     => $ttfb ? $info['starttransfer_time'] * 1000 : $wall,
			'method'   => $ttfb ? 'curl_ttfb' : 'wall_time',
			'total'    => $wall,
			'body'     => (string) wp_remote_retrieve_body( $r ),
			'cache'    => (string) wp_remote_retrieve_header( $r, 'x-aiom-cache' ),
			'encoding' => (string) wp_remote_retrieve_header( $r, 'content-encoding' ),
		);
	}

	/**
	 * Measure a plain visitor request against the page cache, when enabled.
	 *
	 * @param string $url URL.
	 * @return array|null
	 */
	private static function measure_page_cache( $url ) {
		if ( ! AIOM_Settings::get( 'page_cache' ) || '' !== AIOM_Settings::inactive_reason() || AIOM_Settings::get( 'test_mode' ) ) {
			return null;
		}
		$first = self::fetch( $url, '', '' );
		if ( is_wp_error( $first ) ) {
			return null;
		}
		$hits = array();
		for ( $i = 0; $i < self::RUNS; $i++ ) {
			$r = self::fetch( $url, '', '' );
			if ( ! is_wp_error( $r ) && 'HIT' === $r['cache'] ) {
				$hits[] = $r;
			}
		}
		return array(
			'hit'    => (bool) $hits,
			'ttfb'   => $hits ? self::median( wp_list_pluck( $hits, 'ttfb' ) ) : null,
			'method' => $hits ? $hits[0]['method'] : '',
		);
	}

	/**
	 * Build the stored report.
	 *
	 * @param string     $url       URL.
	 * @param array      $original  Original runs.
	 * @param array      $optimised Optimised runs.
	 * @param array|null $cached    Page cache measurement.
	 * @param array      $data      Data recorded by the optimised request.
	 * @return array
	 */
	private static function summarise( $url, array $original, array $optimised, $cached, array $data ) {
		$ob = end( $original )['body'];
		$nb = end( $optimised )['body'];

		$assets = array(
			'css' => array(),
			'js'  => array(),
		);
		$totals = array();
		foreach ( array( 'css', 'js' ) as $t ) {
			$totals[ $t ] = array(
				'files'     => 0,
				'optimised' => 0,
				'skipped'   => 0,
				'external'  => 0,
				'bytes'     => 0,
				'opt_bytes' => 0,
				'gzip'      => 0,
				'opt_gzip'  => 0,
			);
		}
		foreach ( isset( $data['assets'] ) ? (array) $data['assets'] : array() as $row ) {
			$t = $row['type'];
			if ( ! isset( $totals[ $t ] ) ) {
				continue;
			}
			$row            = self::measure_asset( $row );
			$assets[ $t ][] = $row;
			++$totals[ $t ]['files'];
			if ( 'optimised' === $row['status'] ) {
				++$totals[ $t ]['optimised'];
			} elseif ( 'external' === $row['status'] ) {
				++$totals[ $t ]['external'];
			} else {
				++$totals[ $t ]['skipped'];
			}
			foreach ( array( 'bytes', 'opt_bytes', 'gzip', 'opt_gzip' ) as $k ) {
				$totals[ $t ][ $k ] += isset( $row[ $k ] ) ? (int) $row[ $k ] : 0;
			}
		}

		$warnings = array();
		if ( ! empty( $data['html_error'] ) ) {
			$warnings[] = __( 'HTML optimisation failed validation for this page, so the original HTML was served.', 'all-in-one-minifier' );
		}
		if ( empty( $data ) ) {
			$warnings[] = __( 'The optimised request did not report details: optimisation may be disabled, or this page is excluded.', 'all-in-one-minifier' );
		}
		foreach ( array( 'css', 'js' ) as $t ) {
			foreach ( $assets[ $t ] as $row ) {
				if ( in_array( $row['status'], array( 'skipped', 'queued' ), true ) ) {
					/* translators: 1: file, 2: reason. */
					$warnings[] = sprintf( __( '%1$s was not minified: %2$s', 'all-in-one-minifier' ), $row['src'], '' !== $row['note'] ? $row['note'] : $row['status'] );
				}
			}
		}
		if ( '' === end( $original )['encoding'] ) {
			$warnings[] = __( 'The server did not compress the HTML response (no gzip/brotli). Enabling compression on the server usually saves far more bytes than minification.', 'all-in-one-minifier' );
		}
		$html_o = strlen( $ob );
		$html_n = strlen( $nb );
		if ( $html_n < $html_o && empty( $cached['hit'] ) ) {
			$gz_o = strlen( (string) gzencode( $ob, 6 ) );
			$gz_n = strlen( (string) gzencode( $nb, 6 ) );
			if ( end( $original )['encoding'] && $gz_o > 0 && ( $gz_o - $gz_n ) / $gz_o < 0.03 ) {
				$warnings[] = __( 'After server compression the HTML saving is under 3%. HTML minification also holds the page until it is complete, so a server that streams the start of the page early sends its first byte later. Enable the page cache (or your host’s) for a real server-time improvement.', 'all-in-one-minifier' );
			}
		}
		foreach ( array( 'html', 'css', 'js', 'defer', 'delay' ) as $f ) {
			$by = AIOM_Compat::yields_to( $f );
			if ( '' !== $by ) {
				/* translators: 1: feature, 2: plugin name. */
				$warnings[] = sprintf( __( '%1$s optimisation is handled by %2$s on normal requests; this report measured this plugin’s version.', 'all-in-one-minifier' ), strtoupper( $f ), $by );
			}
		}

		$ttfb_o = self::median( wp_list_pluck( $original, 'ttfb' ) );
		$ttfb_n = self::median( wp_list_pluck( $optimised, 'ttfb' ) );
		return array(
			'url'            => $url,
			'post_id'        => url_to_postid( $url ),
			'created'        => time(),
			'runs'           => self::RUNS,
			'html'           => array(
				'bytes'     => strlen( $ob ),
				'opt_bytes' => strlen( $nb ),
				'gzip'      => strlen( (string) gzencode( $ob, 6 ) ),
				'opt_gzip'  => strlen( (string) gzencode( $nb, 6 ) ),
				'ms'        => isset( $data['html_ms'] ) ? (float) $data['html_ms'] : null,
				'stats'     => isset( $data['html_stats'] ) ? $data['html_stats'] : array(),
			),
			'css'            => $totals['css'],
			'js'             => $totals['js'],
			'assets'         => $assets,
			'ttfb'           => array(
				'original'  => $ttfb_o,
				'optimised' => $ttfb_n,
				'cached'    => $cached && $cached['hit'] ? $cached['ttfb'] : null,
				'method'    => end( $optimised )['method'],
				'samples_o' => array_map( 'round', wp_list_pluck( $original, 'ttfb' ) ),
				'samples_n' => array_map( 'round', wp_list_pluck( $optimised, 'ttfb' ) ),
			),
			'page_cache'     => $cached,
			'blocking'       => array(
				'original'  => self::render_blocking( $ob ),
				'optimised' => self::render_blocking( $nb ),
			),
			'delayed'        => isset( $data['delayed'] ) ? (int) $data['delayed'] : 0,
			'compressed'     => '' !== end( $original )['encoding'],
			'warnings'       => array_values( array_unique( $warnings ) ),
			'plugin_version' => AIOM_VERSION,
		);
	}

	/**
	 * Add exact and gzip sizes to an asset record (measured here, after the timed requests),
	 * then drop the server paths so they are never stored.
	 *
	 * @param array $row Asset record.
	 * @return array
	 */
	private static function measure_asset( array $row ) {
		$file   = isset( $row['file'] ) ? (string) $row['file'] : '';
		$cached = isset( $row['cached'] ) ? (string) $row['cached'] : '';
		unset( $row['file'], $row['cached'] );
		if ( '' === $file || ! is_readable( $file ) ) {
			return $row;
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$src = (string) file_get_contents( $file );
		$opt = ( '' !== $cached && is_readable( $cached ) ) ? (string) file_get_contents( $cached ) : $src;
		// phpcs:enable
		$row['bytes']     = strlen( $src );
		$row['opt_bytes'] = strlen( $opt );
		$row['gzip']      = strlen( (string) gzencode( $src, 6 ) );
		$row['opt_gzip']  = $opt === $src ? $row['gzip'] : strlen( (string) gzencode( $opt, 6 ) );
		return $row;
	}

	/**
	 * Count render-blocking stylesheets and scripts in <head>.
	 *
	 * @param string $html Document.
	 * @return array( css, js )
	 */
	public static function render_blocking( $html ) {
		$end  = stripos( $html, '</head>' );
		$head = false === $end ? $html : substr( $html, 0, $end );
		$out  = array(
			'css' => 0,
			'js'  => 0,
		);
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $out;
		}
		$p = new WP_HTML_Tag_Processor( $head );
		while ( $p->next_tag() ) {
			$tag = $p->get_tag();
			if ( 'LINK' === $tag && 'stylesheet' === strtolower( (string) $p->get_attribute( 'rel' ) ) && 'print' !== strtolower( (string) $p->get_attribute( 'media' ) ) ) {
				++$out['css'];
			} elseif ( 'SCRIPT' === $tag && null !== $p->get_attribute( 'src' ) ) {
				$type = strtolower( (string) $p->get_attribute( 'type' ) );
				if ( null === $p->get_attribute( 'async' ) && null === $p->get_attribute( 'defer' ) && 'module' !== $type && 'aiom/delayed' !== $type ) {
					++$out['js'];
				}
			}
		}
		return $out;
	}

	/**
	 * Median of numbers.
	 *
	 * @param array $values Values.
	 * @return float|null
	 */
	private static function median( array $values ) {
		$values = array_values( array_filter( array_map( 'floatval', $values ), 'is_finite' ) );
		$n      = count( $values );
		if ( ! $n ) {
			return null;
		}
		sort( $values );
		$mid = intdiv( $n, 2 );
		return round( $n % 2 ? $values[ $mid ] : ( $values[ $mid - 1 ] + $values[ $mid ] ) / 2, 1 );
	}

	/**
	 * Store a report.
	 *
	 * @param array $report Report.
	 * @return int ID.
	 */
	private static function save( array $report ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'created'  => gmdate( 'Y-m-d H:i:s', $report['created'] ),
				'url'      => $report['url'],
				'url_hash' => md5( $report['url'] ),
				'post_id'  => (int) $report['post_id'],
				'data'     => wp_json_encode( $report ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);
		self::prune( $report['url'] );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Keep the newest reports per URL and overall.
	 *
	 * @param string|null $url Only prune this URL's history.
	 */
	public static function prune( $url = null ) {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table name is fixed; IDs are cast with intval().
		if ( $url ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE url_hash = %s ORDER BY id DESC LIMIT %d, 1000", md5( $url ), self::KEEP_PER_URL ) );
		} else {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} ORDER BY id DESC LIMIT %d, 100000", self::KEEP_TOTAL ) );
		}
		if ( $ids ) {
			$wpdb->query( "DELETE FROM {$table} WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' );
		}
		// phpcs:enable
	}

	/**
	 * Recent reports.
	 *
	 * @param int $limit Limit.
	 * @return array[]
	 */
	public static function recent( $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, data FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row->data, true );
			if ( is_array( $data ) ) {
				$data['id'] = (int) $row->id;
				$out[]      = $data;
			}
		}
		return $out;
	}

	/**
	 * One report.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$json = $wpdb->get_var( $wpdb->prepare( "SELECT data FROM {$table} WHERE id = %d", $id ) );
		$data = $json ? json_decode( $json, true ) : null;
		if ( is_array( $data ) ) {
			$data['id'] = (int) $id;
		}
		return is_array( $data ) ? $data : null;
	}

	/**
	 * History for a URL, newest first.
	 *
	 * @param string $url URL.
	 * @return array[]
	 */
	public static function history( $url ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, data FROM {$table} WHERE url_hash = %s ORDER BY id DESC LIMIT %d", md5( $url ), self::KEEP_PER_URL ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$data = json_decode( $row->data, true );
			if ( is_array( $data ) ) {
				$data['id'] = (int) $row->id;
				$out[]      = $data;
			}
		}
		return $out;
	}

	/**
	 * Delete all reports.
	 */
	public static function delete_all() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	}
}
