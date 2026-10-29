<?php
/**
 * Admin layout: header, navigation, notices and the current screen.
 *
 * @package All_In_One_Minifier
 *
 * @var string $tab Current tab.
 */

defined( 'ABSPATH' ) || exit;

$aiom_nav = array(
	'aiom'          => array( __( 'Dashboard', 'all-in-one-minifier' ), 'dashboard' ),
	'aiom-settings' => array( __( 'Settings', 'all-in-one-minifier' ), 'admin-settings' ),
	'aiom-cache'    => array( __( 'Cache', 'all-in-one-minifier' ), 'database' ),
	'aiom-reports'  => array( __( 'Reports', 'all-in-one-minifier' ), 'chart-bar' ),
	'aiom-tools'    => array( __( 'Tools', 'all-in-one-minifier' ), 'admin-tools' ),
	'aiom-about'    => array( __( 'About', 'all-in-one-minifier' ), 'info-outline' ),
);
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice code from our own redirect.
$aiom_notice_code = isset( $_GET['aiom_notice'] ) ? sanitize_key( wp_unslash( $_GET['aiom_notice'] ) ) : '';
$aiom_notices     = AIOM_Admin::notices();
$aiom_current     = AIOM_Admin::current_page();
?>
<div class="wrap aiom-wrap">
	<header class="aiom-header">
		<div class="aiom-brand">
			<span class="aiom-logo" aria-hidden="true"><span class="dashicons dashicons-performance"></span></span>
			<div>
				<h1><?php esc_html_e( 'All in one Minifier', 'all-in-one-minifier' ); ?></h1>
				<span class="aiom-version"><?php echo esc_html( 'v' . AIOM_VERSION ); ?></span>
			</div>
		</div>
		<div class="aiom-header-side">
			<span class="aiom-pill aiom-pill--<?php echo esc_attr( AIOM_Admin::status_slug() ); ?>"><?php echo esc_html( AIOM_Admin::status_label() ); ?></span>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aiom_clear">
				<input type="hidden" name="scope" value="all">
				<input type="hidden" name="aiom_page" value="<?php echo esc_attr( $aiom_current ); ?>">
				<?php wp_nonce_field( 'aiom_clear' ); ?>
				<button type="submit" class="button"><span class="dashicons dashicons-trash" aria-hidden="true"></span> <?php esc_html_e( 'Clear cache', 'all-in-one-minifier' ); ?></button>
			</form>
		</div>
	</header>

	<nav class="aiom-tabs" aria-label="<?php esc_attr_e( 'Plugin sections', 'all-in-one-minifier' ); ?>">
		<?php foreach ( $aiom_nav as $aiom_slug => $aiom_item ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $aiom_slug ) ); ?>" class="aiom-tab<?php echo $aiom_slug === $aiom_current ? ' is-active' : ''; ?>"<?php echo $aiom_slug === $aiom_current ? ' aria-current="page"' : ''; ?>>
				<span class="dashicons dashicons-<?php echo esc_attr( $aiom_item[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $aiom_item[0] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
	<hr class="wp-header-end">

	<?php if ( isset( $aiom_notices[ $aiom_notice_code ] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $aiom_notices[ $aiom_notice_code ][0] ); ?> is-dismissible"><p><?php echo esc_html( $aiom_notices[ $aiom_notice_code ][1] ); ?></p>
		<?php
		$aiom_cleared = get_transient( 'aiom_last_clear_' . get_current_user_id() );
		if ( 0 === strpos( $aiom_notice_code, 'cleared_' ) && is_array( $aiom_cleared ) && ! empty( $aiom_cleared['third_party'] ) ) :
			?>
			<p>
				<?php
				/* translators: %s: list of cache plugin names. */
				echo esc_html( sprintf( __( 'Also purged: %s.', 'all-in-one-minifier' ), implode( ', ', $aiom_cleared['third_party'] ) ) );
				?>
			</p>
		<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	$aiom_rt = AIOM_Settings::runtime();
	if ( $aiom_rt['safe_mode'] || (int) $aiom_rt['paused_until'] > time() ) :
		?>
		<div class="notice notice-warning aiom-inline-notice">
			<p><strong><?php echo esc_html( AIOM_Admin::status_label() ); ?></strong> — <?php esc_html_e( 'visitors currently get the original, unoptimised site. Saved settings are unchanged.', 'all-in-one-minifier' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo $aiom_rt['safe_mode'] ? 'aiom_safe_mode' : 'aiom_pause'; ?>">
				<input type="hidden" name="enable" value="0">
				<input type="hidden" name="hours" value="0">
				<input type="hidden" name="aiom_page" value="<?php echo esc_attr( $aiom_current ); ?>">
				<?php wp_nonce_field( $aiom_rt['safe_mode'] ? 'aiom_safe_mode' : 'aiom_pause' ); ?>
				<button class="button button-primary"><?php esc_html_e( 'Resume optimisation', 'all-in-one-minifier' ); ?></button>
			</form>
		</div>
	<?php endif; ?>

	<div class="aiom-body">
		<?php require AIOM_DIR . 'admin/views/' . $tab . '.php'; ?>
	</div>
</div>
