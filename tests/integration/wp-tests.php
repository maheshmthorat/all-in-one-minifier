<?php
/**
 * In-WordPress integration tests. Run: wp eval-file tests/integration/wp-tests.php
 * (the docker "cli" service: docker compose run --rm cli eval-file wp-content/plugins/all-in-one-minifier/tests/integration/wp-tests.php)
 *
 * @package All_In_One_Minifier
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'AIOM_VERSION' ) ) {
	exit( 1 );
}

$aiom_pass = 0;
$aiom_fail = 0;
$check     = static function ( $label, $cond ) use ( &$aiom_pass, &$aiom_fail ) {
	if ( $cond ) {
		++$aiom_pass;
		echo "  ok   $label\n";
	} else {
		++$aiom_fail;
		echo "  FAIL $label\n";
	}
};
$saved = get_option( AIOM_Settings::OPTION );

echo "== Settings validation\n";
$s = AIOM_Settings::sanitize(
	array(
		'html_minify'        => '1',
		'page_cache_ttl'     => '9999',
		'max_asset_kb'       => '-5',
		'css_font_display'   => 'evil',
		'css_critical'       => "body{color:red}</style><script>alert(1)</script>\\201C",
		'css_exclude'        => "  a  \n\n<b>x</b>\nа" . str_repeat( 'x', 600 ) . "\na",
		'exclude_post_types' => array( 'post', 'nonexistent', '../etc' ),
		'unknown_key'        => 'x',
	),
	true
);
$check( 'checkbox present => true', true === $s['html_minify'] );
$check( 'missing checkbox => false (form semantics)', false === $s['css_minify'] );
$check( 'integers are clamped to range', 168 === $s['page_cache_ttl'] && 50 === $s['max_asset_kb'] );
$check( 'invalid choice falls back to default', '' === $s['css_font_display'] );
$check( 'critical CSS cannot contain markup', false === strpos( $s['css_critical'], '<' ) );
$check( 'critical CSS keeps backslash escapes', false !== strpos( $s['css_critical'], '\\201C' ) );
$check( 'rule lists: trimmed, deduplicated, tags stripped, overlong lines dropped', "a\nx" === $s['css_exclude'] );
$check( 'post types limited to registered public types', array( 'post' ) === $s['exclude_post_types'] );
$check( 'unknown keys are dropped', ! isset( $s['unknown_key'] ) );
$partial = AIOM_Settings::sanitize( array( 'js_defer' => true ), false );
$check( 'import semantics keep unspecified keys', $partial['html_minify'] === AIOM_Settings::get( 'html_minify' ) && true === $partial['js_defer'] );

echo "== Rule matching\n";
$check( 'handle match', false !== AIOM_Rules::match_asset( array( 'jquery-core' ), 'jquery-core', '/x.js' ) );
$check( 'URL fragment match', false !== AIOM_Rules::match_asset( array( '/plugins/slider/' ), 'h', 'https://s/wp-content/plugins/slider/a.js' ) );
$check( 'wildcard match', false !== AIOM_Rules::match_asset( array( 'woo*cart' ), 'wc-cart', 'https://s/woocommerce/cart.js' ) );
$check( 'no accidental short match', false === AIOM_Rules::match_asset( array( 'js' ), 'x', 'https://s/a.js' ) );
$check( 'URL path rule', false !== AIOM_Rules::match_url( array( '/checkout/*' ), '/checkout/order-received/5/?key=x' ) );
$check( 'exact path rule ignores trailing slash', false !== AIOM_Rules::match_url( array( '/landing' ), '/landing/' ) );
$check( 'path rule does not match other paths', false === AIOM_Rules::match_url( array( '/landing/' ), '/landing-two/' ) );

echo "== Asset location and cache keys\n";
$job = AIOM_Asset_Cache::locate( includes_url( 'js/wp-embed.js' ) . '?ver=1', 'js' );
$check( 'locates a core file from its URL', $job && is_file( $job['file'] ) );
$check( 'rejects path traversal', null === AIOM_Asset_Cache::locate( content_url( '/../wp-config.php' ), 'js' ) );
$check( 'rejects wrong extension', null === AIOM_Asset_Cache::locate( includes_url( 'version.php' ), 'js' ) );
$check( 'rejects external hosts', null === AIOM_Asset_Cache::locate( 'https://cdn.example.com/a.js', 'js' ) );
$check( 'rejects files inside the cache directory', null === AIOM_Asset_Cache::locate( AIOM_Cache_Dir::base_url() . 'assets/js/x.js', 'js' ) );
list( $p1 ) = AIOM_Asset_Cache::target( 'js', $job );
$tmp        = $job;
$tmp['base'] = '//other-host/wp-includes/js/';
list( $p2 ) = AIOM_Asset_Cache::target( 'js', $tmp );
$check( 'cache key changes with the URL base', $p1 !== $p2 );
$s2                     = AIOM_Settings::all();
$s2['css_font_display'] = 'swap';
AIOM_Settings::save( $s2 );
$cssjob = AIOM_Asset_Cache::locate( content_url( '/plugins/contact-form-7/includes/css/styles.css' ), 'css' );
list( $c1 ) = AIOM_Asset_Cache::target( 'css', $cssjob );
$s2['css_font_display'] = '';
AIOM_Settings::save( $s2 );
list( $c2 ) = AIOM_Asset_Cache::target( 'css', $cssjob );
$check( 'cache key changes with output-affecting settings', $c1 !== $c2 );

echo "== Generation: atomic, locked, failure-safe\n";
AIOM_Maintenance::clear( 'assets' );
$r = AIOM_Asset_Cache::generate( 'js', $job );
list( $path ) = AIOM_Asset_Cache::target( 'js', $job );
$check( 'generates a minified file', 'generated' === $r['status'] && filesize( $path ) < filesize( $job['file'] ) );
$check( 'second call is a hit, no regeneration', 'hit' === AIOM_Asset_Cache::generate( 'js', $job )['status'] );
$lock = AIOM_Cache_Dir::lock( md5( $path . 'x' ) );
$check( 'a held lock blocks a second holder', false === AIOM_Cache_Dir::lock( md5( $path . 'x' ) ) );
AIOM_Cache_Dir::unlock( $lock );
$check( 'released lock can be taken again', false !== ( $l2 = AIOM_Cache_Dir::lock( md5( $path . 'x' ) ) ) );
AIOM_Cache_Dir::unlock( $l2 );
$check( 'no temporary files left behind', ! glob( AIOM_Cache_Dir::base() . 'assets/js/*.tmp' ) );

$bad = WP_CONTENT_DIR . '/uploads/aiom-test-broken.js';
file_put_contents( $bad, "var a = ;\n" );
$badjob = AIOM_Asset_Cache::locate( content_url( '/uploads/aiom-test-broken.js' ), 'js' );
$r      = AIOM_Asset_Cache::generate( 'js', $badjob );
$check( 'invalid JavaScript is skipped, not broken', 'skip' === $r['status'] && false !== strpos( $r['reason'], 'parse' ) );
$check( 'the failure is remembered (no retry each request)', 'skip' === AIOM_Asset_Cache::lookup( 'js', $badjob )['status'] );
$check( 'the failure is logged without server paths', false === strpos( wp_json_encode( AIOM_Log::entries() ), ABSPATH ) );
unlink( $bad );

$s3                 = AIOM_Settings::all();
$s3['max_asset_kb'] = 50;
AIOM_Settings::save( $s3 );
$big = AIOM_Asset_Cache::locate( includes_url( 'js/jquery/jquery.js' ), 'js' );
$check( 'files above the size limit are skipped', 'skip' === AIOM_Asset_Cache::generate( 'js', $big )['status'] );
AIOM_Settings::save( $saved );

echo "== Unwritable cache directory\n";
AIOM_Maintenance::clear( 'assets' );
AIOM_Cache_Dir::ensure( 'assets/js' );
$dir = AIOM_Cache_Dir::base() . 'assets/js';
chmod( $dir, 0555 );
clearstatcache();
$r = AIOM_Asset_Cache::generate( 'js', $job );
$check( 'generation fails cleanly when the directory is read-only', in_array( $r['status'], array( 'failed', 'busy' ), true ) || 0 === posix_geteuid() );
$check( 'lookup still answers (serves the original)', 'miss' === AIOM_Asset_Cache::lookup( 'js', $job )['status'] || 0 === posix_geteuid() );
chmod( $dir, 0755 );

echo "== Upgrade from 3.x removes legacy data\n";
global $wpdb;
update_option( '_all-in-one_minifier', array( 'start_minify' => 'on' ) );
update_option( 'all-in-one-minifier', '3.3' );
$wpdb->query( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}alone_minifier_analysis (id int)" ); // phpcs:ignore
wp_mkdir_p( WP_CONTENT_DIR . '/cache/alone_minifier' );
file_put_contents( WP_CONTENT_DIR . '/cache/alone_minifier/blog.html', '<html>stale</html>' );
delete_option( AIOM_Install::VERSION_OPTION );
AIOM_Install::maybe_upgrade();
$check( 'old options removed', false === get_option( '_all-in-one_minifier' ) && false === get_option( 'all-in-one-minifier' ) );
$check( 'old table dropped', null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}alone_minifier_analysis'" ) ); // phpcs:ignore
$check( 'old cache directory deleted', ! is_dir( WP_CONTENT_DIR . '/cache/alone_minifier' ) );
$check( 'version recorded', AIOM_VERSION === get_option( AIOM_Install::VERSION_OPTION ) );
$check( 'existing settings untouched by upgrade', get_option( AIOM_Settings::OPTION ) === $saved );
$check( 'reports table exists', null !== $wpdb->get_var( "SHOW TABLES LIKE '" . AIOM_Reports::table() . "'" ) ); // phpcs:ignore

echo "== Cache directory safety\n";
$check( 'delete_tree refuses paths outside the cache', 0 === AIOM_Cache_Dir::delete_tree( WP_CONTENT_DIR . '/uploads' ) && is_dir( WP_CONTENT_DIR . '/uploads' ) );
$check( 'private directories are protected by .htaccess', is_file( AIOM_Cache_Dir::base() . 'pages/.htaccess' ) && is_file( AIOM_Cache_Dir::base() . 'stats/.htaccess' ) );
$check( 'maintenance runs', is_array( AIOM_Maintenance::run() ) );

echo "== Preload parsing and hints\n";
$pl = AIOM_Assets::parse_preload( '/wp-content/themes/t/fonts/a.woff2' );
$check( 'font preload gets as=font, type and crossorigin', 'font' === $pl['as'] && 'font/woff2' === $pl['type'] && 'anonymous' === $pl['crossorigin'] );
$check( 'unknown extensions are ignored', null === AIOM_Assets::parse_preload( '/a.unknown' ) );
$check( 'image preload keeps fetchpriority', 'high' === AIOM_Assets::parse_preload( '/hero.webp fetchpriority=high' )['fetchpriority'] );
$check( 'origins are normalised', 'https://fonts.gstatic.com' === AIOM_Assets::origin( 'https://Fonts.GStatic.com/s/x' ) );

update_option( AIOM_Settings::OPTION, $saved );
echo "\n$aiom_pass passed, $aiom_fail failed\n";
if ( $aiom_fail ) {
	exit( 1 );
}
