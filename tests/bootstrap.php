<?php
/**
 * Minimal, dependency-free test harness for the pure (WordPress-independent) engines.
 *
 * Usage: php tests/run.php [filter]
 *
 * @package All_In_One_Minifier
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-aiom-autoloader.php';
AIOM_Autoloader::register( dirname( __DIR__ ) . '/' );

// Convert notices/deprecations into failures: the plugin must run cleanly.
set_error_handler(
	static function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

/**
 * Base test case with a few assertions.
 */
abstract class AIOM_Test_Case {

	/**
	 * Assertion count.
	 *
	 * @var int
	 */
	public $assertions = 0;

	/**
	 * Assert strict equality.
	 *
	 * @param mixed  $expected Expected.
	 * @param mixed  $actual   Actual.
	 * @param string $msg      Message.
	 * @throws RuntimeException On failure.
	 */
	protected function assertSame( $expected, $actual, $msg = '' ) {
		++$this->assertions;
		if ( $expected !== $actual ) {
			throw new RuntimeException( ( $msg ? $msg . "\n" : '' ) . "Expected: " . var_export( $expected, true ) . "\nActual:   " . var_export( $actual, true ) );
		}
	}

	/**
	 * Assert truthiness.
	 *
	 * @param mixed  $value Value.
	 * @param string $msg   Message.
	 * @throws RuntimeException On failure.
	 */
	protected function assertTrue( $value, $msg = '' ) {
		++$this->assertions;
		if ( true !== $value ) {
			throw new RuntimeException( $msg ? $msg : 'Expected true, got ' . var_export( $value, true ) );
		}
	}

	/**
	 * Assert a substring is present.
	 *
	 * @param string $needle   Needle.
	 * @param string $haystack Haystack.
	 * @param string $msg      Message.
	 */
	protected function assertContains( $needle, $haystack, $msg = '' ) {
		$this->assertTrue( false !== strpos( $haystack, $needle ), ( $msg ? $msg . ': ' : '' ) . "'$needle' not found in: $haystack" );
	}

	/**
	 * Assert a substring is absent.
	 *
	 * @param string $needle   Needle.
	 * @param string $haystack Haystack.
	 * @param string $msg      Message.
	 */
	protected function assertNotContains( $needle, $haystack, $msg = '' ) {
		$this->assertTrue( false === strpos( $haystack, $needle ), ( $msg ? $msg . ': ' : '' ) . "'$needle' unexpectedly found in: $haystack" );
	}

	/**
	 * Assert that a callable throws a given exception class.
	 *
	 * @param string   $class_name Exception class.
	 * @param callable $fn         Callable.
	 * @throws RuntimeException On failure.
	 */
	protected function assertThrows( $class_name, $fn ) {
		++$this->assertions;
		try {
			$fn();
		} catch ( Exception $e ) {
			if ( $e instanceof $class_name ) {
				return;
			}
			throw new RuntimeException( "Expected $class_name, got " . get_class( $e ) . ': ' . $e->getMessage() );
		}
		throw new RuntimeException( "Expected $class_name to be thrown" );
	}
}
