<?php
/**
 * Hook wiring.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
class AIOM_Plugin {

	/**
	 * Register hooks.
	 */
	public static function boot() {
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 0 );
		add_action( AIOM_Maintenance::HOOK, array( 'AIOM_Maintenance', 'run' ) );
		add_action( 'admin_bar_menu', array( 'AIOM_Admin_Bar', 'menu' ), 100 );
		AIOM_Invalidation::init();
		if ( is_admin() ) {
			AIOM_Admin::init();
			AIOM_Meta_Box::init();
		}
	}

	/**
	 * Decide what to do for this front-end request and start optimising.
	 */
	public static function template_redirect() {
		$plan = AIOM_Context::plan();
		if ( ! $plan ) {
			return;
		}
		AIOM_Assets::hook( $plan );
		if ( $plan['html'] || $plan['delay'] || $plan['page_cache'] || 'on' === $plan['report'] ) {
			AIOM_Output::start( $plan );
		}
	}
}
