<?php
/**
 * Environment and configuration checks.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Diagnostics.
 */
class AIOM_Diagnostics {

	/**
	 * Run checks.
	 *
	 * @return array[] Each: label, status (ok|warn|error|info), detail.
	 */
	public static function checks() {
		$c   = array();
		$add = static function ( $label, $status, $detail ) use ( &$c ) {
			$c[] = compact( 'label', 'status', 'detail' );
		};

		$add(
			__( 'PHP version', 'all-in-one-minifier' ),
			version_compare( PHP_VERSION, '8.1', '>=' ) ? 'ok' : 'warn',
			version_compare( PHP_VERSION, '8.1', '>=' ) ? PHP_VERSION : sprintf( /* translators: %s: version. */ __( '%s works, but PHP 8.1 or newer is faster and still receives security fixes.', 'all-in-one-minifier' ), PHP_VERSION )
		);
		$add( __( 'WordPress version', 'all-in-one-minifier' ), 'ok', get_bloginfo( 'version' ) );
		$add(
			__( 'JavaScript minification', 'all-in-one-minifier' ),
			AIOM_JS_Minifier::available() ? 'ok' : 'error',
			AIOM_JS_Minifier::available() ? __( 'Available (mbstring extension present).', 'all-in-one-minifier' ) : __( 'Unavailable: the PHP mbstring extension is missing. Scripts are served unminified.', 'all-in-one-minifier' )
		);

		$writable = AIOM_Cache_Dir::ensure( 'assets/css' ) && AIOM_Cache_Dir::ensure( 'assets/js' ) && AIOM_Cache_Dir::ensure( 'pages' );
		$add(
			__( 'Cache directory', 'all-in-one-minifier' ),
			$writable ? 'ok' : 'error',
			$writable ? __( 'wp-content/cache/aiom/ is writable.', 'all-in-one-minifier' ) : __( 'wp-content/cache/aiom/ is not writable. Optimised files and the page cache cannot be stored.', 'all-in-one-minifier' )
		);

		if ( function_exists( 'disk_free_space' ) ) {
			$free = @disk_free_space( WP_CONTENT_DIR ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $free ) {
				$add( __( 'Free disk space', 'all-in-one-minifier' ), $free < 104857600 ? 'warn' : 'ok', size_format( $free, 1 ) );
			}
		}

		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$max   = wp_convert_hr_to_bytes( defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '256M' );
		$best  = $limit < 0 ? -1 : max( $limit, $max );
		$add(
			__( 'PHP memory for minifying', 'all-in-one-minifier' ),
			( $best < 0 || $best >= 134217728 ) ? 'ok' : 'warn',
			$best < 0 ? __( 'Unlimited', 'all-in-one-minifier' ) : sprintf(
				/* translators: 1: memory, 2: file size. */
				__( '%1$s — scripts up to about %2$s can be minified.', 'all-in-one-minifier' ),
				size_format( $best ),
				size_format( max( 0, ( $best - 33554432 ) / AIOM_JS_Minifier::MEMORY_PER_BYTE ) )
			)
		);

		$bg = function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' );
		$add(
			__( 'Background file generation', 'all-in-one-minifier' ),
			$bg ? 'ok' : 'info',
			$bg ? __( 'New optimised files are created after the page has been sent to the visitor.', 'all-in-one-minifier' ) : __( 'This server cannot finish a response early (mod_php). New files are created at the end of a request within the configured time budget.', 'all-in-one-minifier' )
		);

		$next = wp_next_scheduled( AIOM_Maintenance::HOOK );
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$add( __( 'Scheduled maintenance', 'all-in-one-minifier' ), 'info', __( 'WP-Cron is disabled; make sure a server cron job calls wp-cron.php so expired and unused cache files are cleaned up.', 'all-in-one-minifier' ) );
		} else {
			$add(
				__( 'Scheduled maintenance', 'all-in-one-minifier' ),
				$next ? 'ok' : 'warn',
				$next ? sprintf( /* translators: %s: time. */ __( 'Next run %s.', 'all-in-one-minifier' ), human_time_diff( $next ) . ( $next > time() ? '' : ' ' . __( 'overdue', 'all-in-one-minifier' ) ) ) : __( 'Not scheduled. Deactivate and reactivate the plugin.', 'all-in-one-minifier' )
			);
		}

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			$add( __( 'Permalinks', 'all-in-one-minifier' ), 'warn', __( 'Plain permalinks use query strings (?p=123), which the page cache does not cache. Choose a pretty permalink structure.', 'all-in-one-minifier' ) );
		}

		$conflict = AIOM_Settings::runtime()['page_cache_conflict'];
		$add(
			__( 'Page cache', 'all-in-one-minifier' ),
			'' !== $conflict ? 'info' : ( AIOM_Settings::get( 'page_cache' ) ? 'ok' : 'info' ),
			'' !== $conflict ? sprintf( /* translators: %s: plugin name. */ __( '%s handles page caching; this plugin’s page cache stays off.', 'all-in-one-minifier' ), $conflict )
				: ( AIOM_Settings::get( 'page_cache' ) ? __( 'Enabled.', 'all-in-one-minifier' ) : __( 'Off. Enable it, or use your host’s page cache, to reduce server response time.', 'all-in-one-minifier' ) )
		);

		$add(
			__( 'Persistent object cache', 'all-in-one-minifier' ),
			'info',
			wp_using_ext_object_cache() ? __( 'In use.', 'all-in-one-minifier' ) : __( 'Not in use (optional; helps database-heavy sites).', 'all-in-one-minifier' )
		);

		$opcache = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$add(
			__( 'PHP OPcache', 'all-in-one-minifier' ),
			! empty( $opcache['opcache_enabled'] ) ? 'ok' : 'warn',
			! empty( $opcache['opcache_enabled'] ) ? __( 'Enabled.', 'all-in-one-minifier' ) : __( 'Not enabled or not detectable. OPcache usually cuts PHP execution time substantially; ask your host.', 'all-in-one-minifier' )
		);

		$loop = get_transient( 'aiom_loopback' );
		$add(
			__( 'Loopback requests', 'all-in-one-minifier' ),
			is_array( $loop ) ? ( $loop['ok'] ? 'ok' : 'error' ) : 'info',
			is_array( $loop ) ? $loop['message'] : __( 'Not tested yet. Reports and preloading need the server to request its own pages.', 'all-in-one-minifier' )
		);

		$latest = AIOM_Reports::recent( 1 );
		if ( $latest ) {
			$add(
				__( 'HTML compression (gzip/brotli)', 'all-in-one-minifier' ),
				$latest[0]['compressed'] ? 'ok' : 'warn',
				$latest[0]['compressed'] ? __( 'The server compresses HTML responses.', 'all-in-one-minifier' ) : __( 'The server did not compress HTML in the latest report. Server compression saves far more than minification; ask your host to enable it.', 'all-in-one-minifier' )
			);
		}
		return $c;
	}

	/**
	 * Only checks that need attention.
	 *
	 * @return array[]
	 */
	public static function problems() {
		return array_values(
			array_filter(
				self::checks(),
				static function ( $c ) {
					return in_array( $c['status'], array( 'warn', 'error' ), true );
				}
			)
		);
	}
}
