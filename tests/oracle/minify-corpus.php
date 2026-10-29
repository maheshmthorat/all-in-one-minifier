<?php
/**
 * Differential-testing driver: minify every file in a list with the plugin's engines.
 *
 * Usage: php tests/oracle/minify-corpus.php <js|css|html> <file-list> <out-dir> [max-bytes]
 *
 * Writes N.orig.<ext>, N.min.<ext> and N.name for each processed file; the
 * Node.js comparers in this directory then check semantic equivalence with
 * independent parsers (acorn, css-tree, parse5).
 *
 * @package All_In_One_Minifier
 */

require dirname( __DIR__ ) . '/bootstrap.php';
restore_error_handler();

list( , $type, $list, $out ) = $argv;
$max = isset( $argv[4] ) ? (int) $argv[4] : 1500000;
if ( ! is_dir( $out ) ) {
	mkdir( $out, 0777, true );
}
$files = array_filter( array_map( 'trim', file( $list ) ) );
$stats = array(
	'files'   => 0,
	'skipped' => 0,
	'in'      => 0,
	'out'     => 0,
	'time'    => 0.0,
);
$skips = array();
$i     = 0;
foreach ( $files as $f ) {
	$src = file_get_contents( $f );
	if ( false === $src || strlen( $src ) > $max ) {
		continue;
	}
	$t = microtime( true );
	try {
		if ( 'js' === $type ) {
			$min = AIOM_JS_Minifier::minify( $src );
		} elseif ( 'css' === $type ) {
			$min = AIOM_CSS_Minifier::minify( $src );
		} else {
			$m   = new AIOM_HTML_Minifier( array( 'minify_json' => true ) );
			$min = $m->process( $src );
		}
	} catch ( AIOM_Minify_Exception $e ) {
		++$stats['skipped'];
		$skips[] = basename( dirname( $f ) ) . '/' . basename( $f ) . ': ' . substr( $e->getMessage(), 0, 120 );
		continue;
	}
	$stats['time'] += microtime( true ) - $t;
	++$stats['files'];
	$stats['in']  += strlen( $src );
	$stats['out'] += strlen( $min );
	file_put_contents( "$out/$i.orig.$type", $src );
	file_put_contents( "$out/$i.min.$type", $min );
	file_put_contents( "$out/$i.name", $f );
	++$i;
}
printf(
	"%s: %d minified, %d skipped (left unchanged), %.1f MB -> %.1f MB (-%.1f%%), %.1fs, %.2f MB/s\n",
	strtoupper( $type ),
	$stats['files'],
	$stats['skipped'],
	$stats['in'] / 1048576,
	$stats['out'] / 1048576,
	100 * ( 1 - $stats['out'] / max( 1, $stats['in'] ) ),
	$stats['time'],
	$stats['in'] / 1048576 / max( 0.001, $stats['time'] )
);
foreach ( array_slice( $skips, 0, 40 ) as $s ) {
	echo "  skip: $s\n";
}
