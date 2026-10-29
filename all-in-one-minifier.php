<?php
/**
 * Plugin Name:       All in one Minifier
 * Plugin URI:        https://wordpress.org/plugins/all-in-one-minifier/
 * Description:       Safe HTML, CSS and JavaScript minification with versioned file caching, an optional page cache, dependency-aware script deferral and measured before/after reports.
 * Version:           4.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Mahesh Thorat
 * Author URI:        https://maheshthorat.web.app
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       all-in-one-minifier
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'AIOM_VERSION' ) ) {
	return;
}

define( 'AIOM_VERSION', '4.0.0' );
define( 'AIOM_FILE', __FILE__ );
define( 'AIOM_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIOM_URL', plugin_dir_url( __FILE__ ) );

require_once AIOM_DIR . 'includes/class-aiom-autoloader.php';
AIOM_Autoloader::register( AIOM_DIR );

register_activation_hook( __FILE__, array( 'AIOM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AIOM_Install', 'deactivate' ) );

// The optional page cache answers before the theme and remaining plugins run.
AIOM_Page_Cache::serve();

AIOM_Plugin::boot();
