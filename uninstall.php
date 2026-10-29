<?php
/**
 * Uninstall: remove every option, transient, table, post meta, scheduled event
 * and cached file created by the plugin. Runs only when the plugin is deleted
 * from the Plugins screen, never on deactivation or upgrade.
 *
 * @package All_In_One_Minifier
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove the plugin's data for the current site.
 */
function aiom_uninstall_site() {
	global $wpdb;
	foreach ( array( 'aiom_settings', 'aiom_runtime', 'aiom_version', 'aiom_log', 'aiom_stats', 'aiom_state' ) as $option ) {
		delete_option( $option );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_aiom\\_%' OR option_name LIKE '\\_transient\\_timeout\\_aiom\\_%'" );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'aiom_reports' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed table name.
	// phpcs:enable
	delete_post_meta_by_key( '_aiom_flags' );
	delete_post_meta_by_key( '_aiom_critical_css' );
	wp_clear_scheduled_hook( 'aiom_maintenance' );
}

/**
 * Delete a directory tree, refusing anything outside wp-content/cache/aiom.
 *
 * @param string $dir Directory.
 */
function aiom_uninstall_delete_dir( $dir ) {
	$root = realpath( WP_CONTENT_DIR . '/cache' );
	$real = realpath( $dir );
	if ( ! $root || ! $real || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR . 'aiom' ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.PHP.NoSilencedErrors.Discouraged
		} else {
			@unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
	@rmdir( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.PHP.NoSilencedErrors.Discouraged
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $aiom_site_id ) {
		switch_to_blog( $aiom_site_id );
		aiom_uninstall_site();
		restore_current_blog();
	}
} else {
	aiom_uninstall_site();
}
aiom_uninstall_delete_dir( WP_CONTENT_DIR . '/cache/aiom' );
