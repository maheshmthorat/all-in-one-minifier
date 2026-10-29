<?php
/**
 * Page cache invalidation on content changes.
 *
 * Asset files never need invalidation: their cache key includes the source
 * file's modification time and size, so edited files get new URLs.
 *
 * Policy: publishing a new post or unpublishing one purges everything (lists,
 * archives and pagination shift); updating an already-published post purges
 * that post, the home page, the posts page and its term/author/type archives.
 * Site-wide changes (menus, widgets, theme, customizer, permalinks, plugins)
 * purge everything.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Invalidation hooks.
 */
class AIOM_Invalidation {

	/**
	 * Post types whose changes affect every page (block theme navigation, templates,
	 * global styles, synced patterns, classic menu items, fonts).
	 */
	const SITE_WIDE_TYPES = array( 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_block', 'nav_menu_item', 'wp_font_family', 'wp_font_face' );

	/**
	 * Whether a full purge was already done in this request.
	 *
	 * @var bool
	 */
	private static $purged_all = false;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'post_status' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'post_id_changed' ) );
		add_action( 'wp_insert_comment', array( __CLASS__, 'comment_changed' ) );
		add_action( 'edit_comment', array( __CLASS__, 'comment_changed' ) );
		add_action( 'delete_comment', array( __CLASS__, 'comment_changed' ) );
		add_action( 'transition_comment_status', array( __CLASS__, 'comment_status' ), 10, 3 );
		foreach ( array( 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'product_changed' ) );
		}
		$site_wide = array(
			'switch_theme',
			'customize_save_after',
			'wp_update_nav_menu',
			'wp_create_nav_menu',
			'wp_delete_nav_menu',
			'wp_update_nav_menu_item',
			'wp_add_nav_menu_item',
			'update_option_sidebars_widgets',
			'activated_plugin',
			'deactivated_plugin',
			'upgrader_process_complete',
			'permalink_structure_changed',
			'edited_term',
			'delete_term',
			'update_option_blogname',
			'update_option_blogdescription',
			'update_option_page_on_front',
			'update_option_page_for_posts',
			'update_option_show_on_front',
			'update_option_posts_per_page',
		);
		foreach ( $site_wide as $hook ) {
			add_action( $hook, array( __CLASS__, 'purge_pages' ) );
		}
	}

	/**
	 * Purge all cached pages (not optimised assets).
	 */
	public static function purge_pages() {
		if ( self::$purged_all ) {
			return;
		}
		self::$purged_all = true;
		AIOM_Page_Cache::purge_all();
	}

	/**
	 * Post status transitions.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function post_status( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( in_array( $post->post_type, self::SITE_WIDE_TYPES, true ) ) {
			self::purge_pages();
			return;
		}
		if ( ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		if ( $new_status !== $old_status ) {
			self::purge_pages();
			return;
		}
		self::purge_post( $post );
	}

	/**
	 * A post was deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function post_id_changed( $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status ) {
			self::purge_pages();
		}
	}

	/**
	 * A comment changed.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public static function comment_changed( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( $comment && $comment->comment_post_ID ) {
			AIOM_Page_Cache::purge_url( (string) get_permalink( (int) $comment->comment_post_ID ) );
		}
	}

	/**
	 * A comment was approved, unapproved, spammed or trashed.
	 *
	 * @param string     $new_status New status.
	 * @param string     $old_status Old status.
	 * @param WP_Comment $comment    Comment.
	 */
	public static function comment_status( $new_status, $old_status, $comment ) {
		if ( $comment instanceof WP_Comment && $new_status !== $old_status ) {
			self::comment_changed( (int) $comment->comment_ID );
		}
	}

	/**
	 * Stock changed.
	 *
	 * @param object|int $product Product.
	 */
	public static function product_changed( $product ) {
		$id   = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : (int) $product;
		$post = get_post( $id );
		if ( $post ) {
			self::purge_post( $post );
		}
	}

	/**
	 * Purge a post and the listings that show it.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function purge_post( WP_Post $post ) {
		if ( self::$purged_all ) {
			return;
		}
		$urls = array( get_permalink( $post ), home_url( '/' ) );
		if ( get_option( 'page_for_posts' ) ) {
			$urls[] = get_permalink( (int) get_option( 'page_for_posts' ) );
		}
		$archive = get_post_type_archive_link( $post->post_type );
		if ( $archive ) {
			$urls[] = $archive;
		}
		$urls[] = get_author_posts_url( (int) $post->post_author );
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$terms = get_the_terms( $post, $tax );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( is_string( $link ) ) {
						$urls[] = $link;
					}
				}
			}
		}
		foreach ( array_unique( array_filter( $urls ) ) as $url ) {
			AIOM_Page_Cache::purge_url( (string) $url );
		}
	}
}
