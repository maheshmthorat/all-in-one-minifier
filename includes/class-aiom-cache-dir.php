<?php
/**
 * Cache directory layout and safe filesystem helpers.
 *
 * wp-content/cache/aiom/[blog-id/]
 *   assets/css, assets/js  public, versioned optimised files
 *   pages/                 page cache (direct web access denied where .htaccess is honoured)
 *   inline/                memoised inline CSS/JS results (private)
 *   stats/, locks/         counters and generation locks (private)
 *
 * All writes go to a unique temporary file followed by rename(), so readers
 * never see partial files. Deletion is confined to the cache root.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filesystem helper.
 */
class AIOM_Cache_Dir {

	const PRIVATE_DIRS = array( 'pages', 'inline', 'stats', 'locks' );

	/**
	 * Absolute base path with trailing slash.
	 *
	 * @return string
	 */
	public static function base() {
		$dir = WP_CONTENT_DIR . '/cache/aiom/';
		if ( is_multisite() ) {
			$dir .= (int) get_current_blog_id() . '/';
		}
		/**
		 * Filters the cache directory. Must be writable and web-accessible at aiom_cache_url.
		 *
		 * @param string $dir Absolute path with trailing slash.
		 */
		return trailingslashit( apply_filters( 'aiom_cache_dir', $dir ) );
	}

	/**
	 * Base URL with trailing slash.
	 *
	 * @return string
	 */
	public static function base_url() {
		$url = content_url( '/cache/aiom/' );
		if ( is_multisite() ) {
			$url .= (int) get_current_blog_id() . '/';
		}
		/**
		 * Filters the public URL of the cache directory.
		 *
		 * @param string $url URL with trailing slash.
		 */
		return trailingslashit( apply_filters( 'aiom_cache_url', $url ) );
	}

	/**
	 * Ensure a sub-directory exists and is protected.
	 *
	 * @param string $sub Sub-directory such as 'assets/css' or 'pages'.
	 * @return bool
	 */
	public static function ensure( $sub ) {
		$base = self::base();
		$dir  = $base . trim( $sub, '/' ) . '/';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( ! file_exists( $base . 'index.php' ) ) {
			self::write( $base . 'index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			self::write( $dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}
		$top = strtok( trim( $sub, '/' ), '/' );
		if ( in_array( $top, self::PRIVATE_DIRS, true ) && ! file_exists( $base . $top . '/.htaccess' ) ) {
			self::write( $base . $top . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		return is_writable( $dir );
	}

	/**
	 * Atomically write a file.
	 *
	 * @param string $file Absolute path.
	 * @param string $data Content.
	 * @return bool
	 */
	public static function write( $file, $data ) {
		$tmp = $file . '.' . str_replace( '.', '', uniqid( '', true ) ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct, atomic writes are required on the request path; WP_Filesystem may prompt for credentials.
		if ( false === @file_put_contents( $tmp, $data ) ) {
			return false;
		}
		if ( defined( 'FS_CHMOD_FILE' ) ) {
			@chmod( $tmp, FS_CHMOD_FILE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! @rename( $tmp, $file ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			return false;
		}
		return true;
	}

	/**
	 * Whether a path is inside the cache root.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public static function contains( $path ) {
		$base = realpath( self::base() );
		$real = realpath( $path );
		return $base && $real && ( $real === $base || 0 === strpos( $real, $base . DIRECTORY_SEPARATOR ) );
	}

	/**
	 * Recursively delete a directory inside the cache root (or the legacy 3.x cache directory).
	 *
	 * @param string $dir Directory.
	 * @param bool   $keep_root Keep the directory itself (and its index.php/.htaccess).
	 * @return int Number of files deleted.
	 */
	public static function delete_tree( $dir, $keep_root = false ) {
		$legacy = wp_normalize_path( WP_CONTENT_DIR . '/cache/alone_minifier' );
		if ( ! is_dir( $dir ) || ( ! self::contains( $dir ) && wp_normalize_path( untrailingslashit( $dir ) ) !== $legacy ) ) {
			return 0;
		}
		$count = 0;
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			$path = $item->getPathname();
			if ( $keep_root && dirname( $path ) === untrailingslashit( $dir ) && in_array( $item->getFilename(), array( 'index.php', '.htaccess' ), true ) ) {
				continue;
			}
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} elseif ( @unlink( $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				++$count;
			}
		}
		if ( ! $keep_root ) {
			@rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		return $count;
	}

	/**
	 * File count and bytes of a sub-directory (index.php/.htaccess excluded).
	 *
	 * @param string $sub Sub-directory, or '' for everything.
	 * @param int    $max Stop counting after this many files.
	 * @return array( int files, int bytes, bool truncated )
	 */
	public static function usage( $sub = '', $max = 50000 ) {
		$dir = self::base() . trim( $sub, '/' );
		if ( ! is_dir( $dir ) ) {
			return array( 0, 0, false );
		}
		$files = 0;
		$bytes = 0;
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $item ) {
			if ( ! $item->isFile() || in_array( $item->getFilename(), array( 'index.php', '.htaccess' ), true ) ) {
				continue;
			}
			++$files;
			$bytes += $item->getSize();
			if ( $files >= $max ) {
				return array( $files, $bytes, true );
			}
		}
		return array( $files, $bytes, false );
	}

	/**
	 * Count cache contents by kind in one pass.
	 *
	 * @return array kind => array( files, bytes ) for css, js, skipped, pages, inline, other, total.
	 */
	public static function inventory() {
		$out = array_fill_keys( array( 'css', 'js', 'skipped', 'pages', 'inline', 'other', 'total' ), array( 0, 0 ) );
		$dir = self::base();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $item ) {
			$name = $item->getFilename();
			if ( ! $item->isFile() || 'index.php' === $name || '.htaccess' === $name ) {
				continue;
			}
			$rel  = substr( wp_normalize_path( $item->getPathname() ), strlen( wp_normalize_path( $dir ) ) );
			$top  = strtok( $rel, '/' );
			$size = $item->getSize();
			if ( 'assets' === $top ) {
				$kind = '.skip' === substr( $name, -5 ) ? 'skipped' : ( '.css' === substr( $name, -4 ) ? 'css' : ( '.js' === substr( $name, -3 ) ? 'js' : 'other' ) );
			} elseif ( 'pages' === $top ) {
				$kind = '.html' === substr( $name, -5 ) ? 'pages' : 'other';
			} elseif ( 'inline' === $top ) {
				$kind = 'inline';
			} else {
				$kind = 'other';
			}
			++$out[ $kind ][0];
			$out[ $kind ][1] += $size;
			++$out['total'][0];
			$out['total'][1] += $size;
		}
		return $out;
	}

	/**
	 * Acquire a non-blocking exclusive lock.
	 *
	 * @param string $key Lock key.
	 * @return array|false Lock to pass to unlock(), or false if someone else holds it.
	 */
	public static function lock( $key ) {
		if ( ! self::ensure( 'locks' ) ) {
			return false;
		}
		$path = self::base() . 'locks/' . preg_replace( '/[^a-z0-9_-]/i', '', $key ) . '.lock';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$fh = @fopen( $path, 'c' );
		if ( ! $fh ) {
			return false;
		}
		if ( ! flock( $fh, LOCK_EX | LOCK_NB ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return false;
		}
		return array(
			'fh'   => $fh,
			'path' => $path,
		);
	}

	/**
	 * Release a lock and remove its file.
	 *
	 * @param array|false $lock Lock from lock().
	 */
	public static function unlock( $lock ) {
		if ( is_array( $lock ) ) {
			@unlink( $lock['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			flock( $lock['fh'], LOCK_UN );
			fclose( $lock['fh'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
	}
}
