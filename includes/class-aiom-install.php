<?php
/**
 * Activation, deactivation and upgrades.
 *
 * Upgrading from 3.x removes everything the old version stored (options, the
 * wp_alone_minifier_analysis table and wp-content/cache/alone_minifier): its
 * cached pages could be served to the wrong URL and are not reused.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class AIOM_Install {

	const VERSION_OPTION = 'aiom_version';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::maybe_upgrade();
		if ( ! wp_next_scheduled( AIOM_Maintenance::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', AIOM_Maintenance::HOOK );
		}
		AIOM_Compat::refresh_runtime();
	}

	/**
	 * Deactivation: stop the scheduled job and drop cached pages (they would go stale).
	 * Settings and optimised assets are kept.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( AIOM_Maintenance::HOOK );
		AIOM_Page_Cache::purge_all();
	}

	/**
	 * Run upgrade routines when the stored version differs.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION );
		if ( AIOM_VERSION === $installed ) {
			return;
		}
		self::create_tables();
		if ( ! $installed ) {
			self::remove_legacy();
		}
		if ( false === get_option( AIOM_Settings::OPTION ) ) {
			add_option( AIOM_Settings::OPTION, AIOM_Settings::DEFAULTS, '', true );
		}
		if ( false === get_option( AIOM_Settings::RUNTIME ) ) {
			add_option( AIOM_Settings::RUNTIME, array(), '', true );
		}
		foreach ( array( 'assets/css', 'assets/js', 'pages', 'inline', 'stats', 'locks' ) as $sub ) {
			AIOM_Cache_Dir::ensure( $sub );
		}
		if ( ! wp_next_scheduled( AIOM_Maintenance::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', AIOM_Maintenance::HOOK );
		}
		update_option( self::VERSION_OPTION, AIOM_VERSION, true );
	}

	/**
	 * Create the reports table.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = AIOM_Reports::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created datetime NOT NULL,
				url varchar(2048) NOT NULL,
				url_hash char(32) NOT NULL,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				data longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY url_hash (url_hash),
				KEY created (created)
			) {$charset};"
		);
	}

	/**
	 * Remove data stored by versions 3.x and earlier.
	 */
	public static function remove_legacy() {
		global $wpdb;
		delete_option( '_all-in-one_minifier' );
		delete_option( 'all-in-one-minifier' );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'alone_minifier_analysis' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- Fixed table name; schema change.
		$legacy = WP_CONTENT_DIR . '/cache/alone_minifier';
		if ( is_dir( $legacy ) ) {
			AIOM_Cache_Dir::delete_tree( $legacy );
		}
	}
}
