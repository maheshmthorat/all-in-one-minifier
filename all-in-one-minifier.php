<?php
/**
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://maheshthorat.web.app
 * @since             0.1
 * @package           All_in_one_Minifier
 *
* Plugin Name: All in one Minifier
* Plugin URI: https://wordpress.org/plugins/all-in-one-minifier/
* Description: Reduce your page load by minify your HTML source on page with all the CSS and JS code present in your page.
* Version: 2.2
* Author: Mahesh Thorat
* Author URI: https://maheshthorat.web.app
**/

/**
 * Prevent file to be called directly
*/
if((!defined('ABSPATH')) || ('all-in-one-minifier.php' == basename($_SERVER['SCRIPT_FILENAME'])))
{
	die;
}

/**
 * Define Constants
*/
define('AOMIN_PLUGIN_FULLNAME', 'All in one Minifier');
define('AOMIN_PLUGIN_IDENTIFIER', 'all-in-one-minifier');
define('AOMIN_PLUGIN_VERSION', '2.2');
define('AOMIN_PLUGIN_LAST_RELEASE', '2022/04/10');
define('AOMIN_PLUGIN_LANGUAGES', 'English');
define('AOMIN_PLUGIN_ABS_PATH', plugin_dir_path(__FILE__));
define('AOMIN_PLUGIN_ABS_CACHE_PATH', plugin_dir_path(__FILE__)."../../cache/alone_minifier/");

/**
 * The core plugin class that is used to define internationalization
 * admin-specific hooks and public-facing site hooks
*/
require AOMIN_PLUGIN_ABS_PATH.'includes/class-all-in-one-minifier-core.php';


/**
 * Begins execution of the plugin
*/
if(!function_exists('run_all_in_one_minifier'))
{
	function run_all_in_one_minifier()
	{
		$plugin = new All_In_One_Minifier_Core();
		$plugin->run();
	}
	run_all_in_one_minifier();
}
