<?php
/**
 * Class autoloader for plugin classes and the bundled Peast library.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps AIOM_Foo_Bar to includes/class-aiom-foo-bar.php (or admin/), and
 * AIOM\Vendor\Peast\X to includes/vendor/peast/Peast/X.php.
 */
class AIOM_Autoloader {

	/**
	 * Plugin root directory with trailing slash.
	 *
	 * @var string
	 */
	private static $dir;

	/**
	 * Register the autoloader.
	 *
	 * @param string $dir Plugin root directory with trailing slash.
	 */
	public static function register( $dir ) {
		self::$dir = $dir;
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class_name Class name.
	 */
	public static function load( $class_name ) {
		$vendor = 'AIOM\\Vendor\\Peast\\';
		if ( 0 === strpos( $class_name, $vendor ) ) {
			$rel  = str_replace( '\\', '/', substr( $class_name, strlen( $vendor ) ) );
			$file = self::$dir . 'includes/vendor/peast/Peast/' . $rel . '.php';
			if ( preg_match( '#^[A-Za-z0-9_/]+$#', $rel ) && is_file( $file ) ) {
				require $file;
			}
			return;
		}
		if ( 0 !== strpos( $class_name, 'AIOM_' ) ) {
			return;
		}
		$file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		foreach ( array( 'includes/', 'admin/' ) as $sub ) {
			if ( is_file( self::$dir . $sub . $file ) ) {
				require self::$dir . $sub . $file;
				return;
			}
		}
	}
}
