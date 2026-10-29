<?php
/**
 * Hourly maintenance and cache management actions.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maintenance.
 */
class AIOM_Maintenance {

	const HOOK = 'aiom_maintenance';

	/**
	 * Run housekeeping: fold stats, expire pages, delete unused files, enforce the size limit.
	 *
	 * @return array Summary.
	 */
	public static function run() {
		AIOM_Stats::fold();
		$summary = array(
			'expired_pages' => AIOM_Page_Cache::expire(),
			'unused'        => self::delete_older_than( array( 'assets', 'inline' ), time() - (int) AIOM_Settings::get( 'cache_unused_days' ) * DAY_IN_SECONDS ),
			'stale_temp'    => self::delete_temp(),
			'size_trimmed'  => self::enforce_size(),
		);
		AIOM_Reports::prune();
		return $summary;
	}

	/**
	 * Delete files whose mtime (refreshed daily on use) is older than a timestamp.
	 *
	 * @param string[] $subs  Sub-directories.
	 * @param int      $limit Timestamp.
	 * @return int
	 */
	private static function delete_older_than( array $subs, $limit ) {
		$count = 0;
		foreach ( $subs as $sub ) {
			foreach ( self::files( $sub ) as $f ) {
				if ( $f['mtime'] < $limit && @unlink( $f['path'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					++$count;
				}
			}
		}
		return $count;
	}

	/**
	 * Remove leftover temporary files and old locks (from crashed requests).
	 *
	 * @return int
	 */
	private static function delete_temp() {
		$count = 0;
		$limit = time() - HOUR_IN_SECONDS;
		foreach ( array( 'assets', 'pages', 'inline', 'locks' ) as $sub ) {
			foreach ( self::files( $sub ) as $f ) {
				if ( $f['mtime'] < $limit && ( '.tmp' === substr( $f['path'], -4 ) || '.lock' === substr( $f['path'], -5 ) ) && @unlink( $f['path'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					++$count;
				}
			}
		}
		return $count;
	}

	/**
	 * Keep the cache under the configured size: drop cached pages and inline memos
	 * first, then the least recently used assets (never ones used in the last day).
	 *
	 * @return int Files deleted.
	 */
	public static function enforce_size() {
		$max   = (int) AIOM_Settings::get( 'cache_max_mb' ) * 1048576;
		$usage = AIOM_Cache_Dir::usage();
		if ( $usage[1] <= $max ) {
			return 0;
		}
		$excess = $usage[1] - $max;
		$count  = 0;
		foreach ( array( 'pages', 'inline', 'assets' ) as $sub ) {
			$files = self::files( $sub );
			usort(
				$files,
				static function ( $a, $b ) {
					return $a['mtime'] - $b['mtime'];
				}
			);
			foreach ( $files as $f ) {
				if ( $excess <= 0 ) {
					break 2;
				}
				if ( 'assets' === $sub && $f['mtime'] > time() - DAY_IN_SECONDS ) {
					break;
				}
				if ( @unlink( $f['path'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					$excess -= $f['size'];
					++$count;
				}
			}
		}
		if ( $excess > 0 ) {
			AIOM_Log::throttled( 'warning', 'cache_size', 'The cache is larger than the configured limit even after cleanup. Consider raising the limit.' );
		}
		return $count;
	}

	/**
	 * Files in a sub-directory (excluding index.php/.htaccess).
	 *
	 * @param string $sub Sub-directory.
	 * @return array[] Each: path, mtime, size.
	 */
	private static function files( $sub ) {
		$dir = AIOM_Cache_Dir::base() . $sub;
		$out = array();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( $f->isFile() && ! in_array( $f->getFilename(), array( 'index.php', '.htaccess' ), true ) ) {
				$out[] = array(
					'path'  => $f->getPathname(),
					'mtime' => $f->getMTime(),
					'size'  => $f->getSize(),
				);
			}
		}
		return $out;
	}

	/**
	 * Clear caches.
	 *
	 * @param string $scope all|pages|assets.
	 * @return array Summary: files deleted and third-party caches purged.
	 */
	public static function clear( $scope ) {
		$deleted = 0;
		$base    = AIOM_Cache_Dir::base();
		if ( 'all' === $scope || 'pages' === $scope ) {
			$deleted += AIOM_Page_Cache::purge_all();
		}
		if ( 'all' === $scope || 'assets' === $scope ) {
			foreach ( array( 'assets', 'inline' ) as $sub ) {
				if ( is_dir( $base . $sub ) ) {
					$deleted += AIOM_Cache_Dir::delete_tree( $base . $sub, true );
				}
			}
		}
		$third_party = array();
		if ( 'pages' !== $scope ) {
			// Pages cached elsewhere may reference the deleted asset files.
			$third_party = AIOM_Compat::purge_all();
		}
		$state               = get_option( 'aiom_state', array() );
		$state               = is_array( $state ) ? $state : array();
		$state['last_clear'] = time();
		update_option( 'aiom_state', $state, false );
		return array(
			'deleted'     => $deleted,
			'third_party' => $third_party,
		);
	}

	/**
	 * Repair: recreate directories and protection files, remove temp files, locks and failure markers.
	 *
	 * @return array Summary.
	 */
	public static function repair() {
		$ok = true;
		foreach ( array( 'assets/css', 'assets/js', 'pages', 'inline', 'stats', 'locks' ) as $sub ) {
			$ok = AIOM_Cache_Dir::ensure( $sub ) && $ok;
		}
		$removed = self::delete_temp();
		foreach ( self::files( 'locks' ) as $f ) {
			if ( @unlink( $f['path'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$removed;
			}
		}
		$markers = 0;
		foreach ( self::files( 'assets' ) as $f ) {
			if ( '.skip' === substr( $f['path'], -5 ) && @unlink( $f['path'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$markers;
			}
		}
		return array(
			'writable' => $ok,
			'removed'  => $removed,
			'markers'  => $markers,
		);
	}
}
