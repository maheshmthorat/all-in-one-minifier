<?php
/**
 * Exact cache statistics with negligible request overhead.
 *
 * Each optimised request appends one short line to an hourly file (an
 * O_APPEND write of a few bytes; atomic on local filesystems). The hourly
 * maintenance job folds finished hours into a stored total. No database writes
 * happen on the request path.
 *
 * Line format: "<asset hits> <asset misses> <generated> <failed> <html µs> <page cache hits> <html requests>"
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Counters.
 */
class AIOM_Stats {

	const OPTION = 'aiom_stats';
	const FIELDS = array( 'asset_hits', 'asset_misses', 'generated', 'failed', 'html_us', 'page_hits', 'html_requests' );

	/**
	 * Counters for the current request.
	 *
	 * @var array
	 */
	private static $current = array();

	/**
	 * Whether the shutdown writer is registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Increment a counter for this request.
	 *
	 * @param string $field Field.
	 * @param int    $by    Amount.
	 */
	public static function bump( $field, $by = 1 ) {
		if ( ! self::$registered ) {
			self::$registered = true;
			register_shutdown_function( array( __CLASS__, 'flush' ) );
		}
		self::$current[ $field ] = ( isset( self::$current[ $field ] ) ? self::$current[ $field ] : 0 ) + $by;
	}

	/**
	 * Append this request's counters.
	 */
	public static function flush() {
		if ( ! self::$current || ! AIOM_Settings::get( 'stats_enabled' ) || ! AIOM_Cache_Dir::ensure( 'stats' ) ) {
			self::$current = array();
			return;
		}
		$values = array();
		foreach ( self::FIELDS as $f ) {
			$values[] = isset( self::$current[ $f ] ) ? (int) self::$current[ $f ] : 0;
		}
		self::$current = array();
		$file          = AIOM_Cache_Dir::base() . 'stats/' . gmdate( 'YmdH' ) . '.log';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $file, implode( ' ', $values ) . "\n", FILE_APPEND );
	}

	/**
	 * Fold finished hourly files into the stored totals.
	 */
	public static function fold() {
		$dir = AIOM_Cache_Dir::base() . 'stats/';
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$totals  = self::stored();
		$current = gmdate( 'YmdH' ) . '.log';
		foreach ( (array) glob( $dir . '*.log' ) as $file ) {
			if ( basename( $file ) === $current ) {
				continue;
			}
			$totals = self::add_file( $totals, $file );
			@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		update_option( self::OPTION, $totals, false );
	}

	/**
	 * Totals including the current, unfolded hour.
	 *
	 * @return array
	 */
	public static function totals() {
		$totals = self::stored();
		foreach ( (array) glob( AIOM_Cache_Dir::base() . 'stats/*.log' ) as $file ) {
			$totals = self::add_file( $totals, $file );
		}
		return $totals;
	}

	/**
	 * Reset counters.
	 */
	public static function reset() {
		foreach ( (array) glob( AIOM_Cache_Dir::base() . 'stats/*.log' ) as $file ) {
			@unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		delete_option( self::OPTION );
	}

	/**
	 * Stored totals.
	 *
	 * @return array
	 */
	private static function stored() {
		$t = get_option( self::OPTION, array() );
		$t = is_array( $t ) ? $t : array();
		foreach ( self::FIELDS as $f ) {
			$t[ $f ] = isset( $t[ $f ] ) ? (int) $t[ $f ] : 0;
		}
		if ( empty( $t['since'] ) ) {
			$t['since'] = time();
		}
		return $t;
	}

	/**
	 * Add one hourly file to totals.
	 *
	 * @param array  $totals Totals.
	 * @param string $file   File.
	 * @return array
	 */
	private static function add_file( array $totals, $file ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$fh = @fopen( $file, 'r' );
		if ( ! $fh ) {
			return $totals;
		}
		while ( false !== ( $line = fgets( $fh ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$parts = explode( ' ', trim( $line ) );
			foreach ( self::FIELDS as $i => $f ) {
				if ( isset( $parts[ $i ] ) ) {
					$totals[ $f ] += (int) $parts[ $i ];
				}
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $totals;
	}
}
