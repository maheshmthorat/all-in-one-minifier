<?php
/**
 * Dashboard screen.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

$aiom_s        = AIOM_Settings::all();
$aiom_inactive = AIOM_Settings::inactive_reason();
$aiom_features = array(
	array( __( 'HTML minification', 'all-in-one-minifier' ), $aiom_s['html_minify'], 'html' ),
	array( __( 'CSS minification', 'all-in-one-minifier' ), $aiom_s['css_minify'], 'css' ),
	array( __( 'JavaScript minification', 'all-in-one-minifier' ), $aiom_s['js_minify'] && AIOM_JS_Minifier::available(), 'js' ),
	array( __( 'Defer scripts', 'all-in-one-minifier' ), $aiom_s['js_defer'], 'defer' ),
	array( __( 'Delay selected scripts', 'all-in-one-minifier' ), $aiom_s['js_delay'] && '' !== trim( $aiom_s['js_delay_patterns'] ), 'delay' ),
	array( __( 'Async CSS with critical CSS', 'all-in-one-minifier' ), $aiom_s['css_async'] && '' !== trim( $aiom_s['css_critical'] ), '' ),
	array( __( 'Page cache', 'all-in-one-minifier' ), $aiom_s['page_cache'], 'page_cache' ),
);
$aiom_inv      = AIOM_Cache_Dir::inventory();
$aiom_stats    = AIOM_Stats::totals();
$aiom_lookups  = $aiom_stats['asset_hits'] + $aiom_stats['asset_misses'];
$aiom_state    = get_option( 'aiom_state', array() );
$aiom_latest   = AIOM_Reports::recent( 1 );
$aiom_log      = array_slice( AIOM_Log::entries(), 0, 5 );
$aiom_problems = AIOM_Diagnostics::problems();
?>
<div class="aiom-grid aiom-grid--2-1">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Optimisation status', 'all-in-one-minifier' ); ?></h2>
		<ul class="aiom-features">
			<?php
			foreach ( $aiom_features as $aiom_f ) :
				$aiom_by = '' !== $aiom_f[2] && $aiom_f[1] ? AIOM_Compat::yields_to( $aiom_f[2] ) : '';
				if ( 'page_cache' === $aiom_f[2] && $aiom_f[1] ) {
					$aiom_by = AIOM_Settings::runtime()['page_cache_conflict'];
				}
				if ( '' !== $aiom_inactive ) {
					$aiom_chip = array( 'off', __( 'Off (globally)', 'all-in-one-minifier' ) );
				} elseif ( ! $aiom_f[1] ) {
					$aiom_chip = array( 'off', __( 'Off', 'all-in-one-minifier' ) );
				} elseif ( '' !== $aiom_by ) {
					/* translators: %s: plugin name. */
					$aiom_chip = array( 'warn', sprintf( __( 'Handled by %s', 'all-in-one-minifier' ), $aiom_by ) );
				} else {
					$aiom_chip = array( 'on', $aiom_s['test_mode'] ? __( 'On (admins only)', 'all-in-one-minifier' ) : __( 'On', 'all-in-one-minifier' ) );
				}
				?>
				<li><span><?php echo esc_html( $aiom_f[0] ); ?></span><span class="aiom-chip aiom-chip--<?php echo esc_attr( $aiom_chip[0] ); ?>"><?php echo esc_html( $aiom_chip[1] ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<div class="aiom-actions">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-reports' ) ); ?>"><?php esc_html_e( 'Measure a page', 'all-in-one-minifier' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-settings' ) ); ?>"><?php esc_html_e( 'Change settings', 'all-in-one-minifier' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-tools' ) ); ?>"><?php esc_html_e( 'Diagnostics', 'all-in-one-minifier' ); ?></a>
			<?php if ( '' === $aiom_inactive ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiom-inline-form">
					<input type="hidden" name="action" value="aiom_safe_mode">
					<input type="hidden" name="enable" value="1">
					<input type="hidden" name="aiom_page" value="aiom">
					<?php wp_nonce_field( 'aiom_safe_mode' ); ?>
					<button class="button" data-aiom-confirm="<?php esc_attr_e( 'Turn off all optimisation for every visitor until you resume? Settings are kept.', 'all-in-one-minifier' ); ?>"><?php esc_html_e( 'Safe mode', 'all-in-one-minifier' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</section>

	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Health', 'all-in-one-minifier' ); ?></h2>
		<?php if ( ! $aiom_problems ) : ?>
			<p class="aiom-good"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'No problems found.', 'all-in-one-minifier' ); ?></p>
		<?php else : ?>
			<ul class="aiom-checks">
				<?php foreach ( $aiom_problems as $aiom_c ) : ?>
					<li class="is-<?php echo esc_attr( $aiom_c['status'] ); ?>"><strong><?php echo esc_html( $aiom_c['label'] ); ?></strong><span><?php echo esc_html( $aiom_c['detail'] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<h3 class="aiom-subtitle"><?php esc_html_e( 'Recent warnings', 'all-in-one-minifier' ); ?></h3>
		<?php if ( ! $aiom_log ) : ?>
			<p class="description"><?php esc_html_e( 'None.', 'all-in-one-minifier' ); ?></p>
		<?php else : ?>
			<ul class="aiom-log-mini">
				<?php foreach ( $aiom_log as $aiom_e ) : ?>
					<li class="is-<?php echo esc_attr( $aiom_e['level'] ); ?>"><span><?php echo esc_html( $aiom_e['message'] ); ?></span><time><?php echo esc_html( human_time_diff( $aiom_e['time'] ) ); ?></time></li>
				<?php endforeach; ?>
			</ul>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-tools#aiom-log' ) ); ?>"><?php esc_html_e( 'View log', 'all-in-one-minifier' ); ?></a>
		<?php endif; ?>
	</section>
</div>

<div class="aiom-grid aiom-grid--4">
	<div class="aiom-tile">
		<span class="aiom-tile-label"><?php esc_html_e( 'Optimised files', 'all-in-one-minifier' ); ?></span>
		<span class="aiom-tile-value"><?php echo esc_html( number_format_i18n( $aiom_inv['css'][0] + $aiom_inv['js'][0] ) ); ?></span>
		<span class="aiom-tile-meta">
			<?php
			/* translators: 1: CSS file count, 2: JS file count, 3: size. */
			echo esc_html( sprintf( __( '%1$s CSS · %2$s JS · %3$s', 'all-in-one-minifier' ), number_format_i18n( $aiom_inv['css'][0] ), number_format_i18n( $aiom_inv['js'][0] ), AIOM_Admin::bytes( $aiom_inv['css'][1] + $aiom_inv['js'][1] ) ) );
			?>
		</span>
	</div>
	<div class="aiom-tile">
		<span class="aiom-tile-label"><?php esc_html_e( 'Cached pages', 'all-in-one-minifier' ); ?></span>
		<span class="aiom-tile-value"><?php echo esc_html( number_format_i18n( $aiom_inv['pages'][0] ) ); ?></span>
		<span class="aiom-tile-meta"><?php echo esc_html( $aiom_s['page_cache'] ? AIOM_Admin::bytes( $aiom_inv['pages'][1] ) : __( 'Page cache off', 'all-in-one-minifier' ) ); ?></span>
	</div>
	<div class="aiom-tile">
		<span class="aiom-tile-label"><?php esc_html_e( 'Asset cache hit rate', 'all-in-one-minifier' ); ?></span>
		<span class="aiom-tile-value"><?php echo $aiom_lookups ? esc_html( number_format_i18n( 100 * $aiom_stats['asset_hits'] / $aiom_lookups, 1 ) . '%' ) : '—'; ?></span>
		<span class="aiom-tile-meta">
			<?php
			/* translators: 1: hits, 2: misses. */
			echo esc_html( sprintf( __( '%1$s hits · %2$s misses', 'all-in-one-minifier' ), number_format_i18n( $aiom_stats['asset_hits'] ), number_format_i18n( $aiom_stats['asset_misses'] ) ) );
			?>
		</span>
	</div>
	<div class="aiom-tile">
		<span class="aiom-tile-label"><?php esc_html_e( 'HTML processing time', 'all-in-one-minifier' ); ?></span>
		<span class="aiom-tile-value"><?php echo $aiom_stats['html_requests'] ? esc_html( number_format_i18n( $aiom_stats['html_us'] / $aiom_stats['html_requests'] / 1000, 1 ) . ' ms' ) : '—'; ?></span>
		<span class="aiom-tile-meta">
			<?php
			/* translators: %s: number of requests. */
			echo esc_html( sprintf( __( 'average over %s pages', 'all-in-one-minifier' ), number_format_i18n( $aiom_stats['html_requests'] ) ) );
			?>
		</span>
	</div>
</div>

<div class="aiom-grid aiom-grid--2-1">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Latest report', 'all-in-one-minifier' ); ?></h2>
		<?php if ( $aiom_latest ) : ?>
			<?php
			$report       = $aiom_latest[0];
			$aiom_compact = true;
			include AIOM_DIR . 'admin/views/report-card.php';
			?>
		<?php else : ?>
			<div class="aiom-empty">
				<p><?php esc_html_e( 'No measurements yet. A report requests a page as a visitor would, with and without optimisation, and shows the measured difference in bytes and server response time.', 'all-in-one-minifier' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-reports' ) ); ?>"><?php esc_html_e( 'Run your first report', 'all-in-one-minifier' ); ?></a>
			</div>
		<?php endif; ?>
	</section>
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Cache activity', 'all-in-one-minifier' ); ?></h2>
		<dl class="aiom-dl">
			<dt><?php esc_html_e( 'Last file generated', 'all-in-one-minifier' ); ?></dt>
			<dd><?php echo ! empty( $aiom_state['last_generated'] ) ? esc_html( sprintf( /* translators: %s: time span. */ __( '%s ago', 'all-in-one-minifier' ), human_time_diff( $aiom_state['last_generated'] ) ) ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Last cleared', 'all-in-one-minifier' ); ?></dt>
			<dd><?php echo ! empty( $aiom_state['last_clear'] ) ? esc_html( sprintf( /* translators: %s: time span. */ __( '%s ago', 'all-in-one-minifier' ), human_time_diff( $aiom_state['last_clear'] ) ) ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Files generated', 'all-in-one-minifier' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( $aiom_stats['generated'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Files left unminified', 'all-in-one-minifier' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( $aiom_stats['failed'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Page cache hits', 'all-in-one-minifier' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( $aiom_stats['page_hits'] ) ); ?></dd>
			<dt><?php esc_html_e( 'Counting since', 'all-in-one-minifier' ); ?></dt>
			<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), $aiom_stats['since'] ) ); ?></dd>
		</dl>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-cache' ) ); ?>"><?php esc_html_e( 'Manage cache', 'all-in-one-minifier' ); ?></a>
	</section>
</div>
