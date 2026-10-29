<?php
/**
 * Admin bar menu for administrators: status, purge this page, clear cache, report.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin bar.
 */
class AIOM_Admin_Bar {

	/**
	 * Add nodes.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public static function menu( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = AIOM_Admin::status_label();
		$bar->add_node(
			array(
				'id'    => 'aiom',
				'title' => '<span class="ab-icon dashicons dashicons-performance" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Minifier', 'all-in-one-minifier' ) . '</span>',
				'href'  => admin_url( 'admin.php?page=aiom' ),
				'meta'  => array( 'title' => $status ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'aiom',
				'id'     => 'aiom-status',
				'title'  => esc_html( $status ),
			)
		);
		if ( ! is_admin() ) {
			$reason = AIOM_Context::bypass_reason();
			if ( '' !== $reason ) {
				$bar->add_node(
					array(
						'parent' => 'aiom',
						'id'     => 'aiom-bypass',
						/* translators: %s: reason. */
						'title'  => esc_html( sprintf( __( 'This page view: not optimised (%s)', 'all-in-one-minifier' ), AIOM_Admin::reason_label( $reason ) ) ),
					)
				);
			}
			$current = home_url( add_query_arg( array() ) );
			$bar->add_node(
				array(
					'parent' => 'aiom',
					'id'     => 'aiom-purge-page',
					'title'  => esc_html__( 'Purge this page from the page cache', 'all-in-one-minifier' ),
					'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=aiom_purge_url&url=' . rawurlencode( $current ) ), 'aiom_purge_url' ),
				)
			);
			$bar->add_node(
				array(
					'parent' => 'aiom',
					'id'     => 'aiom-report',
					'title'  => esc_html__( 'Run a report for this page', 'all-in-one-minifier' ),
					'href'   => admin_url( 'admin.php?page=aiom-reports&url=' . rawurlencode( $current ) ),
				)
			);
		}
		$bar->add_node(
			array(
				'parent' => 'aiom',
				'id'     => 'aiom-clear',
				'title'  => esc_html__( 'Clear all cache', 'all-in-one-minifier' ),
				'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=aiom_clear&scope=all' ), 'aiom_clear' ),
			)
		);
	}
}
