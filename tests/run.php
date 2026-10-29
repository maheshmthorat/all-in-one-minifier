<?php
/**
 * Test runner: php tests/run.php [name-filter]
 *
 * Runs every tests/unit/test-*.php class method starting with "test_".
 *
 * @package All_In_One_Minifier
 */

require __DIR__ . '/bootstrap.php';

$filter = isset( $argv[1] ) ? $argv[1] : '';
$files  = glob( __DIR__ . '/unit/test-*.php' );
sort( $files );

$passed     = 0;
$failed     = 0;
$assertions = 0;
$failures   = array();
$start      = microtime( true );

foreach ( $files as $file ) {
	$before = get_declared_classes();
	require_once $file;
	foreach ( array_diff( get_declared_classes(), $before ) as $class ) {
		if ( ! is_subclass_of( $class, 'AIOM_Test_Case' ) ) {
			continue;
		}
		foreach ( get_class_methods( $class ) as $method ) {
			if ( 0 !== strpos( $method, 'test_' ) ) {
				continue;
			}
			$name = $class . '::' . $method;
			if ( '' !== $filter && false === stripos( $name, $filter ) ) {
				continue;
			}
			$t = new $class();
			try {
				$t->$method();
				++$passed;
				echo '.';
			} catch ( Throwable $e ) {
				++$failed;
				$failures[] = $name . "\n" . get_class( $e ) . ': ' . $e->getMessage() . "\n  at " . $e->getFile() . ':' . $e->getLine();
				echo 'F';
			}
			$assertions += $t->assertions;
		}
	}
}

printf( "\n\nPHP %s — %d passed, %d failed, %d assertions (%.2fs)\n", PHP_VERSION, $passed, $failed, $assertions, microtime( true ) - $start );
foreach ( $failures as $i => $f ) {
	echo "\n" . ( $i + 1 ) . ') ' . $f . "\n";
}
exit( $failed ? 1 : 0 );
