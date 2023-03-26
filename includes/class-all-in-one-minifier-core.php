<?php
/**
 * Class All in one Minifier
 * The file that defines the core plugin class
 *
 * @author Mahesh Thorat
 * @link https://maheshthorat.web.app
 * @version 2.1
 * @package All_in_one_Minifier
*/
class All_In_One_Minifier_Core
{
   /**
    * The unique identifier of this plugin
   */
   protected $plugin_name;

   /**
    * The current version of the plugin
   */
   protected $version;

   /**
    * Define the core functionality of the plugin
   */
   public function __construct()
   {
      $this->plugin_name = AOMIN_PLUGIN_IDENTIFIER;
      $this->version = AOMIN_PLUGIN_VERSION;
   }
   public function run()
   {
      /**
       * The admin of plugin class 
       * admin related content and options
      */
      require AOMIN_PLUGIN_ABS_PATH.'admin/class-all-in-one-minifier-admin.php';

      $plugin_admin = new All_In_One_Minifier_Admin($this->get_plugin_name(), $this->get_version());
      if(is_admin())
      {
         add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_backend_standalone'));
         add_action('admin_menu', array($plugin_admin, 'return_admin_menu'));
         add_action('init', array($plugin_admin, 'return_update_options'));
         add_filter( 'plugin_action_links_all-in-one-minifier/all-in-one-minifier.php', array($plugin_admin, 'aiomin_settings_link'));
      }

      function sanitize_output($buffer) {
         $search = array(
            '/\n(\s+)?\/\/[^\n]*/',     // remove comments
            '/<!--(.|\s)*?-->/', // Remove HTML comments

            # '/[\n]/',         // shorten multiple whitespace sequences

            # '/ (\t| )+/',
            '/([\n])+/',

            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/\s+/',            // remove multiple whitespaces
         );

         $replace = array(
            '',

            '',
            // '',

            // '',
            '$1',

            '>',
            '<',
            '\\1',
            ' ',
         );
         $buffer = preg_replace($search, $replace, $buffer);
         return $buffer;
      }

      $opts = get_option('_all-in-one_minifier');
      if(@$opts['start_minify'] == 'on')
      {
         if((!is_admin() || @$opts['admin'] == 'on') && !isset($_GET['generatereport']) || @$_GET['is_minify'] == 1)
         {
            // ob_start("sanitize_output");
            add_filter( 'template_include', 'process_post', 99 );
            function process_post($template) {
               global $wp_query;
               $post_obj = $wp_query->get_queried_object();
               $post_id = $post_obj->ID;
               $post_slug = get_post_field( 'post_name', $post_id);
               $permalink = get_permalink($post_id);
               $frontpage_id = get_option( 'page_on_front' );
               if($post_id == $frontpage_id)
               {
                  $post_slug = 'index';
               }
               if(file_exists(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.'.html') && filesize(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.'.html') > 0)
               {
                  include AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.'.html';
                  exit;
               }
               else
               {
                  if(!isset($_GET['isMinify']))
                  {
                     ob_start("sanitize_output");
                  }
                  return $template;
               }
            }
         }
         if (is_admin()) {
            function _postUpdatebuild($post_ID, $post_after, $post_before)
            {
               $url = esc_url(plugins_url(AOMIN_PLUGIN_IDENTIFIER)) . "/admin/admin-ajax.php?post_id=" . $post_ID;
               $ch = curl_init();
               curl_setopt($ch, CURLOPT_URL, $url);
               curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
               $output = curl_exec($ch);
               $info = curl_getinfo($ch);
               $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
               curl_close($ch);
            }
            add_action('save_post', '_postUpdatebuild', 10, 3);
         }

      }
      if(@$opts['loadtime'] == 'on')
      {
         if(is_admin())
         {
            add_action( 'admin_footer', array($plugin_admin, 'call_action_console_loadTime') );
         }
         else
         {
            add_action( 'wp_footer', array($plugin_admin, 'call_action_console_loadTime') );
         }
      }
      if(@$_GET['generatereport'] == 'true' && !is_admin())
      {
         add_action( 'wp_head', array($plugin_admin, 'call_action_generate_report_css') );
         add_action( 'wp_head', array($plugin_admin, 'call_action_generate_loadTime_report') );
      }
      if(is_admin())
      {
         $version = get_option( $this->get_plugin_name() );
         if($version == '' || $version < 2)
         {
            if(!is_dir(AOMIN_PLUGIN_ABS_PATH.'../../cache/'))
            {
               mkdir(AOMIN_PLUGIN_ABS_PATH."../../cache/");
            }
            if(!is_dir(AOMIN_PLUGIN_ABS_PATH.'../../cache/alone_minifier'))
            {
               mkdir(AOMIN_PLUGIN_ABS_PATH."../../cache/alone_minifier");
            }
            $this->my_plugin_create_db();
         }
      }
   }

   public function my_plugin_create_db() {

      global $wpdb;
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'alone_minifier_analysis';

      $sql = "CREATE TABLE $table_name (
         id mediumint(9) NOT NULL AUTO_INCREMENT,
         postID mediumint(9) NOT NULL,
         docBeforeTime varchar(25) NOT NULL,
         docAfterTime varchar(25) NOT NULL,
         minifyStatus smallint(1) NOT NULL,
         datetime datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
         UNIQUE KEY id (id)
      ) $charset_collate;";

      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      $wpdb->query( 'DROP TABLE IF EXISTS '.$table_name );
      dbDelta( $sql );
      update_option( $this->get_plugin_name(), $this->get_version() );
   }

   public function get_plugin_name()
   {
      return $this->plugin_name;
   }
   public function get_version()
   {
      return $this->version;
   }
}
?>