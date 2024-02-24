<?php

/**
 * Class All in one Minifier
 * The file that defines the core plugin class
 *
 * @author Mahesh Thorat
 * @link https://maheshthorat.web.app
 * @version 3.2
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

   public static function parseURL($parsed_url)
   {
      $parsed_url = wp_parse_url($parsed_url);
      $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
      if (empty($path)) {
         $post_slug = 'index';
      } else {
         $post_slug = trim($path, '/');
      }
      if ($post_slug == '') {
         $post_slug = 'index';
      }
      return $post_slug;
   }
   public function run()
   {
      /**
       * The admin of plugin class 
       * admin related content and options
       */
      require AOMIN_PLUGIN_ABS_PATH . 'admin/class-all-in-one-minifier-admin.php';

      $plugin_admin = new All_In_One_Minifier_Admin($this->get_plugin_name(), $this->get_version());
      if (is_admin()) {
         add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_backend_standalone'));
         add_action('admin_menu', array($plugin_admin, 'return_admin_menu'));
         add_action('init', array($plugin_admin, 'return_update_options'));
         add_filter('plugin_action_links_all-in-one-minifier/all-in-one-minifier.php', array($plugin_admin, 'aiomin_settings_link'));
      }
      add_action('admin_bar_menu', array($plugin_admin, 'my_custom_admin_bar_link'), 999);

      function sanitize_output($buffer)
      {
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

      if (!is_admin()) {
         $opts = get_option('_all-in-one_minifier');
         if (isset($opts['start_minify']) && $opts['start_minify'] == 'on' && (!(defined('WP_CLI') && WP_CLI))) {
            if (((!is_admin()) && !isset($_GET['generatereport']) || (isset($_GET['is_minify']) && $_GET['is_minify'] == 1)) && (strpos($_SERVER['REQUEST_URI'], 'wp-json/wp') === false)) {
               $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
               $host = $_SERVER['HTTP_HOST'];
               $request_uri = $_SERVER['REQUEST_URI'];
               $parsed_url = ($is_https ? 'https://' : 'http://') . $host . $request_uri;
               $post_slug = self::parseURL($parsed_url);

               if ((file_exists(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . '.html') && filesize(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . '.html') > 0) && @$_GET['is_minify'] == false && !isset($_COOKIE['wordpress_logged_in_' . COOKIEHASH])) {
                  include AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . '.html';
                  exit;
               } else {
                  $currentUser = 0;
                  if (isset($_GET['is_minify']) || !isset($_COOKIE['wordpress_logged_in_' . COOKIEHASH])) {
                     add_action('init', 'logout_user_at_start_of_hooks');
                     function logout_user_at_start_of_hooks()
                     {
                        global $currentUser;
                        if (is_user_logged_in()) {
                           $currentUser = get_current_user_id();
                           wp_logout();
                        }
                     }
                     ob_start();
                     add_action('shutdown', function () {
                        global $currentUser;
                        if (!empty($currentUser)) {
                           $user = get_user_by('id', $currentUser);
                           wp_set_current_user($currentUser, $user->user_login);
                           wp_set_auth_cookie($currentUser);
                           do_action('wp_login', $user->user_login);
                        }

                        $post_id = get_the_ID();
                        if (empty($post_id)) {
                           return;
                        }
                        if (!is_singular()) {
                           $postType = get_post_type($post_id);
                           if ($postType != 'page') {
                              $post_id = get_option('page_for_posts');
                           }
                        }
                        if ($post_id > 0) {
                           $parsed_url = get_permalink($post_id);
                           $post_slug = self::parseURL($parsed_url);

                           $final = '';
                           $levels = ob_get_level();
                           for ($i = 0; $i < $levels; $i++) {
                              $final .= ob_get_clean();
                           }
                           echo $content = apply_filters('final_output', $final);
                           if (!empty($content)) {
                              $directory_path = pathinfo(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug, PATHINFO_DIRNAME);
                              if (!file_exists($directory_path)) {
                                 wp_mkdir_p($directory_path);
                              }

                              $withoutSanitize = file_put_contents(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . "-without.html", $content);

                              wp_delete_file(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . "-without.html");
                              $withSanitize = file_put_contents(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . ".html", sanitize_output($content));

                              global $wpdb;
                              $datetime = gmdate('Y-m-d H:i:s');
                              $minifyStatus = 1;
                              $table_name = $wpdb->prefix . 'alone_minifier_analysis';
                              $querystr = "SELECT postID FROM $table_name WHERE postID = $post_id LIMIT 1 ";
                              $pageposts = $wpdb->get_results($querystr);
                              if (count($pageposts) > 0) {
                                 $wpdb->query($wpdb->prepare("UPDATE $table_name SET docBeforeTime = '$withoutSanitize', docAfterTime = '$withSanitize', datetime = '$datetime' WHERE postID = $post_id AND minifyStatus = $minifyStatus "));
                              } else {
                                 $wpdb->insert($table_name, array('postID' => $post_id, 'minifyStatus' => $minifyStatus, 'docBeforeTime' => $withoutSanitize, 'docAfterTime' => $withSanitize, 'datetime' => $datetime));
                              }
                           }
                        }
                     }, 0);
                  }
               }
            }
         }
         add_action('wp_footer', array($plugin_admin, 'call_action_frontend_js'));
      }

      if (is_admin()) {
         $version = get_option($this->get_plugin_name());
         if ($version == '' || $version < 2) {
            if (!is_dir(AOMIN_PLUGIN_ABS_PATH . '../../cache/')) {
               wp_mkdir_p(AOMIN_PLUGIN_ABS_PATH . "../../cache/");
            }
            if (!is_dir(AOMIN_PLUGIN_ABS_PATH . '../../cache/alone_minifier')) {
               wp_mkdir_p(AOMIN_PLUGIN_ABS_PATH . "../../cache/alone_minifier");
            }
            $this->my_plugin_create_db();
         }
      }
   }

   public function my_plugin_create_db()
   {
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

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      $wpdb->query('DROP TABLE IF EXISTS ' . $table_name);
      dbDelta($sql);
      update_option($this->get_plugin_name(), $this->get_version());
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
