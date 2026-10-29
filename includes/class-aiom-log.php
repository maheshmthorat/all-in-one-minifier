<?php
/**
 * Diagnostic log: a small ring buffer of warnings and errors.
 *
 * Written only when something goes wrong (each failing asset version is logged
 * once because a skip marker prevents retries), never on normal requests.
 * Server paths are stripped from messages.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log storage.
 */
class AIOM_Log {

	const OPTION = 'aiom_log';
	const MAX    = 100;

	/**
	 * Add an entry; identical entries within a day are merged with a counter.
	 *
	 * @param string $level   'error' | 'warning' | 'info'.
	 * @param string $code    Machine code.
	 * @param string $message Human-readable message (English; codes are stable).
	 */
	public static function add( $level, $code, $message ) {
		$message = self::redact( (string) $message );
		$log     = get_option( self::OPTION, array() );
		$log     = is_array( $log ) ? $log : array();
		$now     = time();
		foreach ( $log as $i => $entry ) {
			if ( $entry['code'] === $code && $entry['message'] === $message && $now - $entry['time'] < DAY_IN_SECONDS ) {
				$entry['count'] = isset( $entry['count'] ) ? $entry['count'] + 1 : 2;
				$entry['time']  = $now;
				unset( $log[ $i ] );
				array_unshift( $log, $entry );
				update_option( self::OPTION, array_values( $log ), false );
				return;
			}
		}
		array_unshift(
			$log,
			array(
				'time'    => $now,
				'level'   => $level,
				'code'    => $code,
				'message' => $message,
				'count'   => 1,
			)
		);
		update_option( self::OPTION, array_slice( $log, 0, self::MAX ), false );
	}

	/**
	 * Log at most once per hour for a given code (for conditions that could repeat on every request).
	 *
	 * @param string $level   Level.
	 * @param string $code    Code.
	 * @param string $message Message.
	 */
	public static function throttled( $level, $code, $message ) {
		$key = 'aiom_log_' . md5( $code . $message );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, HOUR_IN_SECONDS );
		self::add( $level, $code, $message );
	}

	/**
	 * Remove server paths.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function redact( $s ) {
		$s = str_replace( '\\', '/', $s );
		return str_replace(
			array( wp_normalize_path( WP_CONTENT_DIR ), wp_normalize_path( untrailingslashit( ABSPATH ) ) ),
			array( 'wp-content', '' ),
			$s
		);
	}

	/**
	 * Entries, newest first.
	 *
	 * @return array
	 */
	public static function entries() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the log.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
