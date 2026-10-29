<?php
/**
 * Versioned file cache for optimised CSS and JavaScript.
 *
 * Key = md5( real path | mtime | size | plugin version | output settings | URL base ).
 * The file name embeds the key, so any change to the source, its location or
 * relevant settings produces a new URL: browsers and CDNs never get stale
 * content, and no purge is needed when themes or plugins update.
 *
 * A request never minifies the same file twice: results are written once
 * (under a non-blocking lock, via temp file + rename) and served from disk.
 * Files that cannot be minified get a ".skip" marker holding the reason, so
 * failures are not retried on every request.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset cache.
 */
class AIOM_Asset_Cache {

	/**
	 * Skip reason for files that minification would shrink by less than 2%.
	 */
	const COMPACT = 'already compact';

	/**
	 * URL prefix => directory map, longest prefix first.
	 *
	 * @var array|null
	 */
	private static $map = null;

	/**
	 * Map a stylesheet/script URL to a local, readable file inside WordPress.
	 *
	 * @param string $src  URL as enqueued.
	 * @param string $type 'css' or 'js'.
	 * @return array|null array( file, url, base, name ) or null for external/unknown files.
	 */
	public static function locate( $src, $type ) {
		$src = html_entity_decode( (string) $src, ENT_QUOTES, 'UTF-8' );
		if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
			return null;
		}
		$url = substr( $src, 0, strcspn( $src, '?#' ) );
		if ( '' === $url ) {
			return null;
		}
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			$url = '//' . wp_parse_url( site_url(), PHP_URL_HOST ) . $url;
		}
		$url  = preg_replace( '#^https?:#i', '', $url );
		$file = null;
		foreach ( self::map() as $prefix => $dir ) {
			if ( 0 === stripos( $url, $prefix . '/' ) ) {
				$file = $dir . '/' . rawurldecode( substr( $url, strlen( $prefix ) + 1 ) );
				break;
			}
		}
		if ( null === $file || false !== strpos( $file, '..' ) || false !== strpos( $file, "\0" ) ) {
			return null;
		}
		$real = realpath( $file );
		if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) || strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) !== $type ) {
			return null;
		}
		$allowed = false;
		foreach ( array( ABSPATH, WP_CONTENT_DIR ) as $root ) {
			$root = realpath( $root );
			if ( $root && 0 === strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed || AIOM_Cache_Dir::contains( $real ) ) {
			return null;
		}
		$base = substr( $url, 0, strrpos( $url, '/' ) + 1 );
		return array(
			'file' => $real,
			'url'  => $src,
			'base' => $base,
			'name' => basename( $real ),
		);
	}

	/**
	 * Prefix map built from WordPress' URL/path constants.
	 *
	 * @return array
	 */
	private static function map() {
		if ( null === self::$map ) {
			$strip = static function ( $u ) {
				return untrailingslashit( preg_replace( '#^https?:#i', '', $u ) );
			};
			$map   = array(
				$strip( content_url() )  => untrailingslashit( WP_CONTENT_DIR ),
				$strip( plugins_url() )  => untrailingslashit( WP_PLUGIN_DIR ),
				$strip( includes_url() ) => untrailingslashit( ABSPATH . WPINC ),
				$strip( site_url() )     => untrailingslashit( ABSPATH ),
			);
			if ( defined( 'WPMU_PLUGIN_URL' ) && defined( 'WPMU_PLUGIN_DIR' ) ) {
				$map[ $strip( WPMU_PLUGIN_URL ) ] = untrailingslashit( WPMU_PLUGIN_DIR );
			}
			uksort(
				$map,
				static function ( $a, $b ) {
					return strlen( $b ) - strlen( $a );
				}
			);
			self::$map = $map;
		}
		return self::$map;
	}

	/**
	 * Cache file path (without extension markers) for a located asset.
	 *
	 * @param string $type 'css' or 'js'.
	 * @param array  $job  Result of locate().
	 * @return array( string path, string url )
	 */
	public static function target( $type, array $job ) {
		$key  = md5( $job['file'] . '|' . filemtime( $job['file'] ) . '|' . filesize( $job['file'] ) . '|' . AIOM_Settings::output_hash() . '|' . $job['base'] );
		$slug = substr( preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( pathinfo( $job['name'], PATHINFO_FILENAME ) ) ), 0, 40 );
		$name = trim( $slug, '-' ) . '-' . substr( $key, 0, 12 ) . '.' . $type;
		return array(
			AIOM_Cache_Dir::base() . 'assets/' . $type . '/' . $name,
			AIOM_Cache_Dir::base_url() . 'assets/' . $type . '/' . $name,
		);
	}

	/**
	 * Look up the cache state of an asset.
	 *
	 * @param string $type 'css' or 'js'.
	 * @param array  $job  Result of locate().
	 * @return array( status: hit|skip|miss, url, path, reason )
	 */
	public static function lookup( $type, array $job ) {
		list( $path, $url ) = self::target( $type, $job );
		if ( is_file( $path ) ) {
			// Refresh mtime at most daily; unused files are removed by maintenance.
			if ( filemtime( $path ) < time() - DAY_IN_SECONDS ) {
				@touch( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return array(
				'status' => 'hit',
				'url'    => $url,
				'path'   => $path,
				'reason' => '',
			);
		}
		if ( is_file( $path . '.skip' ) ) {
			$marker = self::read_marker( $path . '.skip' );
			return array(
				'status' => 'skip',
				'url'    => '',
				'path'   => $path,
				'reason' => $marker['reason'],
			);
		}
		return array(
			'status' => 'miss',
			'url'    => $url,
			'path'   => $path,
			'reason' => '',
		);
	}

	/**
	 * Generate the optimised file.
	 *
	 * @param string $type 'css' or 'js'.
	 * @param array  $job  Result of locate().
	 * @return array( status: generated|hit|skip|busy|failed, reason )
	 */
	public static function generate( $type, array $job ) {
		list( $path ) = self::target( $type, $job );
		if ( ! AIOM_Cache_Dir::ensure( 'assets/' . $type ) ) {
			AIOM_Log::throttled( 'error', 'cache_dir', 'The cache directory is not writable: wp-content/cache/aiom/' );
			return array(
				'status' => 'failed',
				'reason' => 'cache directory not writable',
			);
		}
		$lock = AIOM_Cache_Dir::lock( md5( $path ) );
		if ( ! $lock ) {
			return array(
				'status' => 'busy',
				'reason' => 'being generated by another request',
			);
		}
		try {
			if ( is_file( $path ) ) {
				return array(
					'status' => 'hit',
					'reason' => '',
				);
			}
			$result = self::build( $type, $job );
			if ( isset( $result['skip'] ) ) {
				AIOM_Cache_Dir::write(
					$path . '.skip',
					wp_json_encode(
						array(
							'src'    => self::label( $job ),
							'reason' => $result['skip'],
							'time'   => time(),
						)
					)
				);
				return array(
					'status' => 'skip',
					'reason' => $result['skip'],
				);
			}
			if ( ! AIOM_Cache_Dir::write( $path, $result['code'] ) ) {
				AIOM_Log::throttled( 'error', 'write_failed', 'Could not write optimised files to wp-content/cache/aiom/. Check permissions and free disk space.' );
				return array(
					'status' => 'failed',
					'reason' => 'write failed',
				);
			}
			return array(
				'status' => 'generated',
				'reason' => '',
			);
		} finally {
			AIOM_Cache_Dir::unlock( $lock );
		}
	}

	/**
	 * Minify a file.
	 *
	 * @param string $type 'css' or 'js'.
	 * @param array  $job  Result of locate().
	 * @return array array( code ) or array( skip => reason )
	 */
	private static function build( $type, array $job ) {
		$size = (int) filesize( $job['file'] );
		if ( $size > (int) AIOM_Settings::get( 'max_asset_kb' ) * 1024 ) {
			return array( 'skip' => 'larger than the size limit' );
		}
		if ( 'js' === $type ) {
			if ( ! AIOM_JS_Minifier::available() ) {
				return array( 'skip' => 'PHP mbstring extension missing' );
			}
			if ( ! self::has_memory( AIOM_JS_Minifier::memory_needed( $size ) ) ) {
				AIOM_Log::add( 'warning', 'memory', sprintf( 'Not enough PHP memory to minify %s (%s KB); served unminified.', self::label( $job ), number_format( $size / 1024 ) ) );
				return array( 'skip' => 'not enough PHP memory' );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$src = file_get_contents( $job['file'] );
		if ( false === $src || '' === trim( $src ) ) {
			return array( 'skip' => 'empty or unreadable' );
		}
		try {
			if ( 'css' === $type ) {
				$out = AIOM_CSS_Minifier::minify(
					$src,
					array(
						'rewrite_base' => $job['base'],
						'font_display' => (string) AIOM_Settings::get( 'css_font_display' ),
					)
				);
			} else {
				$out = AIOM_JS_Minifier::minify( $src );
			}
		} catch ( AIOM_Minify_Exception $e ) {
			AIOM_Log::add( 'warning', 'minify_failed', sprintf( '%s was served unminified: %s', self::label( $job ), $e->getMessage() ) );
			return array( 'skip' => $e->getMessage() );
		}
		if ( '' === trim( $out ) || strlen( $src ) - strlen( $out ) < 0.02 * strlen( $src ) ) {
			// Already minified (often without a .min suffix): serving the original avoids a needless URL change.
			return array( 'skip' => self::COMPACT );
		}
		return array( 'code' => $out );
	}

	/**
	 * Whether enough memory is available, raising the limit like WordPress does for admin tasks.
	 *
	 * @param int $needed Bytes.
	 * @return bool
	 */
	private static function has_memory( $needed ) {
		$available = static function () {
			$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
			return $limit < 0 ? PHP_INT_MAX : $limit - memory_get_usage( true );
		};
		if ( $available() >= $needed ) {
			return true;
		}
		wp_raise_memory_limit( 'aiom' );
		return $available() >= $needed;
	}

	/**
	 * Read a skip marker.
	 *
	 * @param string $file Marker file.
	 * @return array( src, reason, time )
	 */
	public static function read_marker( $file ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) @file_get_contents( $file ), true );
		return array(
			'src'    => isset( $data['src'] ) ? (string) $data['src'] : '',
			'reason' => isset( $data['reason'] ) ? (string) $data['reason'] : 'skipped',
			'time'   => isset( $data['time'] ) ? (int) $data['time'] : (int) @filemtime( $file ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		);
	}

	/**
	 * Failed/skipped assets, newest first.
	 *
	 * @param int $limit Limit.
	 * @return array[]
	 */
	public static function skipped( $limit = 50 ) {
		$out = array();
		foreach ( array( 'css', 'js' ) as $type ) {
			foreach ( (array) glob( AIOM_Cache_Dir::base() . 'assets/' . $type . '/*.skip' ) as $file ) {
				$marker = self::read_marker( $file );
				if ( self::COMPACT !== $marker['reason'] ) {
					$out[] = array_merge( $marker, array( 'type' => $type ) );
				}
			}
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['time'] - $a['time'];
			}
		);
		return array_slice( $out, 0, $limit );
	}

	/**
	 * Path relative to the WordPress root, for messages.
	 *
	 * @param array $job Job.
	 * @return string
	 */
	public static function label( array $job ) {
		return AIOM_Log::redact( $job['file'] );
	}
}
