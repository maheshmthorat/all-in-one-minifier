<?php
/**
 * Cache management screen.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

$aiom_inv     = AIOM_Cache_Dir::inventory();
$aiom_s       = AIOM_Settings::all();
$aiom_stats   = AIOM_Stats::totals();
$aiom_lookups = $aiom_stats['asset_hits'] + $aiom_stats['asset_misses'];
$aiom_max     = (int) $aiom_s['cache_max_mb'] * 1048576;
$aiom_pct     = $aiom_max ? min( 100, 100 * $aiom_inv['total'][1] / $aiom_max ) : 0;
$aiom_skipped = AIOM_Asset_Cache::skipped( 30 );
$aiom_urls    = AIOM_Admin::candidate_urls( 60 );
$aiom_post    = esc_url( admin_url( 'admin-post.php' ) );
$aiom_rows    = array(
	'css'     => __( 'Optimised CSS files', 'all-in-one-minifier' ),
	'js'      => __( 'Optimised JavaScript files', 'all-in-one-minifier' ),
	'pages'   => __( 'Cached pages', 'all-in-one-minifier' ),
	'inline'  => __( 'Inline CSS/JS results', 'all-in-one-minifier' ),
	'skipped' => __( 'Files served unchanged (already minified or not minifiable)', 'all-in-one-minifier' ),
	'other'   => __( 'Metadata, locks and statistics', 'all-in-one-minifier' ),
);
?>
<div class="aiom-grid aiom-grid--2-1">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Storage', 'all-in-one-minifier' ); ?></h2>
		<div class="aiom-meter" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: percent. */ __( '%s%% of the cache size limit used', 'all-in-one-minifier' ), number_format_i18n( $aiom_pct, 1 ) ) ); ?>">
			<span style="width:<?php echo esc_attr( max( 1, round( $aiom_pct, 1 ) ) ); ?>%"></span>
		</div>
		<p class="description">
			<?php
			/* translators: 1: used, 2: limit. */
			echo esc_html( sprintf( __( '%1$s of %2$s used', 'all-in-one-minifier' ), AIOM_Admin::bytes( $aiom_inv['total'][1] ), AIOM_Admin::bytes( $aiom_max ) ) );
			?>
			· <a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-settings#aiom-sec-cache' ) ); ?>"><?php esc_html_e( 'Limits and expiry', 'all-in-one-minifier' ); ?></a>
		</p>
		<table class="widefat striped aiom-table">
			<thead><tr><th><?php esc_html_e( 'Contents', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'Files', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'Size', 'all-in-one-minifier' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $aiom_rows as $aiom_k => $aiom_label ) : ?>
					<tr><td><?php echo esc_html( $aiom_label ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( $aiom_inv[ $aiom_k ][0] ) ); ?></td><td class="num"><?php echo esc_html( AIOM_Admin::bytes( $aiom_inv[ $aiom_k ][1] ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>

	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Actions', 'all-in-one-minifier' ); ?></h2>
		<div class="aiom-stack">
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
				<input type="hidden" name="action" value="aiom_clear"><input type="hidden" name="scope" value="all"><input type="hidden" name="aiom_page" value="aiom-cache">
				<?php wp_nonce_field( 'aiom_clear' ); ?>
				<button class="button button-primary"><?php esc_html_e( 'Clear all cache', 'all-in-one-minifier' ); ?></button>
				<p class="description"><?php esc_html_e( 'Deletes cached pages and optimised files, and purges detected cache plugins (their pages may reference the deleted files).', 'all-in-one-minifier' ); ?></p>
			</form>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_clear"><input type="hidden" name="scope" value="pages"><input type="hidden" name="aiom_page" value="aiom-cache">
				<?php wp_nonce_field( 'aiom_clear' ); ?>
				<button class="button"><?php esc_html_e( 'Purge page cache only', 'all-in-one-minifier' ); ?></button>
			</form>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_clear"><input type="hidden" name="scope" value="assets"><input type="hidden" name="aiom_page" value="aiom-cache">
				<?php wp_nonce_field( 'aiom_clear' ); ?>
				<button class="button"><?php esc_html_e( 'Clear optimised CSS/JS only', 'all-in-one-minifier' ); ?></button>
			</form>
			<div class="aiom-preload" data-urls="<?php echo esc_attr( wp_json_encode( array_keys( $aiom_urls ) ) ); ?>">
				<button type="button" class="button aiom-preload-start"><?php esc_html_e( 'Rebuild cache (preload pages)', 'all-in-one-minifier' ); ?></button>
				<p class="description">
					<?php
					/* translators: %d: number of pages. */
					echo esc_html( sprintf( __( 'Requests the home page and the %d most recently updated pages as an anonymous visitor, so optimised files (and cached pages) are ready before visitors arrive.', 'all-in-one-minifier' ), max( 0, count( $aiom_urls ) - 1 ) ) );
					?>
				</p>
				<progress class="aiom-preload-progress" max="<?php echo esc_attr( count( $aiom_urls ) ); ?>" value="0" hidden></progress>
				<p class="aiom-preload-status" aria-live="polite"></p>
			</div>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_maintenance">
				<?php wp_nonce_field( 'aiom_maintenance' ); ?>
				<button class="button"><?php esc_html_e( 'Run cleanup now', 'all-in-one-minifier' ); ?></button>
				<p class="description"><?php esc_html_e( 'Removes expired pages, files unused for the configured number of days and leftovers from interrupted requests. Runs automatically every hour.', 'all-in-one-minifier' ); ?></p>
			</form>
		</div>
	</section>
</div>

<section class="aiom-card">
	<h2 class="aiom-card-title"><?php esc_html_e( 'Statistics', 'all-in-one-minifier' ); ?></h2>
	<div class="aiom-grid aiom-grid--4 aiom-grid--flat">
		<div class="aiom-tile"><span class="aiom-tile-label"><?php esc_html_e( 'Asset cache hits', 'all-in-one-minifier' ); ?></span><span class="aiom-tile-value"><?php echo esc_html( number_format_i18n( $aiom_stats['asset_hits'] ) ); ?></span><span class="aiom-tile-meta"><?php echo $aiom_lookups ? esc_html( sprintf( /* translators: %s: percent. */ __( '%s hit rate', 'all-in-one-minifier' ), number_format_i18n( 100 * $aiom_stats['asset_hits'] / $aiom_lookups, 1 ) . '%' ) ) : '&nbsp;'; ?></span></div>
		<div class="aiom-tile"><span class="aiom-tile-label"><?php esc_html_e( 'Asset cache misses', 'all-in-one-minifier' ); ?></span><span class="aiom-tile-value"><?php echo esc_html( number_format_i18n( $aiom_stats['asset_misses'] ) ); ?></span><span class="aiom-tile-meta"><?php echo esc_html( sprintf( /* translators: %s: count. */ __( '%s files generated', 'all-in-one-minifier' ), number_format_i18n( $aiom_stats['generated'] ) ) ); ?></span></div>
		<div class="aiom-tile"><span class="aiom-tile-label"><?php esc_html_e( 'Pages optimised', 'all-in-one-minifier' ); ?></span><span class="aiom-tile-value"><?php echo esc_html( number_format_i18n( $aiom_stats['html_requests'] ) ); ?></span><span class="aiom-tile-meta"><?php echo $aiom_stats['html_requests'] ? esc_html( sprintf( /* translators: %s: ms. */ __( '%s ms average processing', 'all-in-one-minifier' ), number_format_i18n( $aiom_stats['html_us'] / $aiom_stats['html_requests'] / 1000, 2 ) ) ) : '&nbsp;'; ?></span></div>
		<div class="aiom-tile"><span class="aiom-tile-label"><?php esc_html_e( 'Page cache hits', 'all-in-one-minifier' ); ?></span><span class="aiom-tile-value"><?php echo esc_html( number_format_i18n( $aiom_stats['page_hits'] ) ); ?></span><span class="aiom-tile-meta"><?php echo esc_html( $aiom_s['page_cache'] ? __( 'served without running WordPress themes', 'all-in-one-minifier' ) : __( 'page cache is off', 'all-in-one-minifier' ) ); ?></span></div>
	</div>
	<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="aiom-inline-form">
		<input type="hidden" name="action" value="aiom_reset_stats">
		<?php wp_nonce_field( 'aiom_reset_stats' ); ?>
		<span class="description">
			<?php
			/* translators: %s: date. */
			echo esc_html( sprintf( __( 'Counted since %s. Exact counts, not samples.', 'all-in-one-minifier' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aiom_stats['since'] ) ) );
			?>
		</span>
		<button class="button-link"><?php esc_html_e( 'Reset statistics', 'all-in-one-minifier' ); ?></button>
	</form>
</section>

<section class="aiom-card">
	<h2 class="aiom-card-title"><?php esc_html_e( 'Files that could not be minified', 'all-in-one-minifier' ); ?></h2>
	<?php if ( ! $aiom_skipped ) : ?>
		<p class="description"><?php esc_html_e( 'None. Files that were already minified are served as they are and are not listed here.', 'all-in-one-minifier' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'These files are served exactly as they are. That is the safe outcome; it is not an error on your site. Tools → Repair retries them.', 'all-in-one-minifier' ); ?></p>
		<table class="widefat striped aiom-table">
			<thead><tr><th><?php esc_html_e( 'File', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'Reason', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'When', 'all-in-one-minifier' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $aiom_skipped as $aiom_row ) : ?>
					<tr><td><code><?php echo esc_html( $aiom_row['src'] ); ?></code></td><td><?php echo esc_html( $aiom_row['reason'] ); ?></td><td><?php echo esc_html( human_time_diff( $aiom_row['time'] ) ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
