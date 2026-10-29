<?php
/**
 * Tools & diagnostics screen.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

$aiom_post   = esc_url( admin_url( 'admin-post.php' ) );
$aiom_checks = AIOM_Diagnostics::checks();
$aiom_compat = AIOM_Compat::detect();
$aiom_log    = AIOM_Log::entries();
$aiom_rt     = AIOM_Settings::runtime();
?>
<div class="aiom-grid aiom-grid--2">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Safe mode & pause', 'all-in-one-minifier' ); ?></h2>
		<p class="description"><?php esc_html_e( 'If something looks wrong, switch optimisation off for everyone without touching your settings. Cached pages are purged so visitors immediately get the original site. You can also add define( \'AIOM_DISABLE\', true ); to wp-config.php.', 'all-in-one-minifier' ); ?></p>
		<div class="aiom-actions">
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>">
				<input type="hidden" name="action" value="aiom_safe_mode"><input type="hidden" name="enable" value="<?php echo $aiom_rt['safe_mode'] ? '0' : '1'; ?>">
				<?php wp_nonce_field( 'aiom_safe_mode' ); ?>
				<button class="button <?php echo $aiom_rt['safe_mode'] ? 'button-primary' : ''; ?>"><?php echo $aiom_rt['safe_mode'] ? esc_html__( 'Turn safe mode off', 'all-in-one-minifier' ) : esc_html__( 'Turn safe mode on', 'all-in-one-minifier' ); ?></button>
			</form>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_pause"><input type="hidden" name="hours" value="1">
				<?php wp_nonce_field( 'aiom_pause' ); ?>
				<button class="button"><?php esc_html_e( 'Pause for 1 hour', 'all-in-one-minifier' ); ?></button>
			</form>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_pause"><input type="hidden" name="hours" value="24">
				<?php wp_nonce_field( 'aiom_pause' ); ?>
				<button class="button"><?php esc_html_e( 'Pause for 24 hours', 'all-in-one-minifier' ); ?></button>
			</form>
		</div>
	</section>

	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Settings backup', 'all-in-one-minifier' ); ?></h2>
		<div class="aiom-stack">
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_export">
				<?php wp_nonce_field( 'aiom_export' ); ?>
				<button class="button"><?php esc_html_e( 'Export settings (JSON)', 'all-in-one-minifier' ); ?></button>
			</form>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="aiom_import">
				<?php wp_nonce_field( 'aiom_import' ); ?>
				<label for="aiom-import-file"><?php esc_html_e( 'Import from a file', 'all-in-one-minifier' ); ?></label>
				<input type="file" id="aiom-import-file" name="aiom_file" accept="application/json,.json" required>
				<button class="button" data-aiom-confirm="<?php esc_attr_e( 'Replace the current settings with the imported ones?', 'all-in-one-minifier' ); ?>"><?php esc_html_e( 'Import', 'all-in-one-minifier' ); ?></button>
			</form>
			<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
				<input type="hidden" name="action" value="aiom_reset">
				<?php wp_nonce_field( 'aiom_reset' ); ?>
				<button class="button button-link-delete" data-aiom-confirm="<?php esc_attr_e( 'Restore every setting, including exclusion rules and critical CSS, to the defaults? Consider exporting first.', 'all-in-one-minifier' ); ?>"><?php esc_html_e( 'Restore default settings', 'all-in-one-minifier' ); ?></button>
			</form>
		</div>
	</section>
</div>

<section class="aiom-card" id="aiom-diagnostics">
	<h2 class="aiom-card-title"><?php esc_html_e( 'Diagnostics', 'all-in-one-minifier' ); ?></h2>
	<table class="widefat striped aiom-table aiom-checks-table">
		<tbody>
			<?php foreach ( $aiom_checks as $aiom_c ) : ?>
				<tr class="is-<?php echo esc_attr( $aiom_c['status'] ); ?>">
					<td class="aiom-check-status"><span class="aiom-dot" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html( $aiom_c['status'] ); ?></span></td>
					<th scope="row"><?php echo esc_html( $aiom_c['label'] ); ?></th>
					<td><?php echo esc_html( $aiom_c['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="aiom-actions">
		<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<input type="hidden" name="action" value="aiom_loopback">
			<?php wp_nonce_field( 'aiom_loopback' ); ?>
			<button class="button"><?php esc_html_e( 'Test loopback request', 'all-in-one-minifier' ); ?></button>
		</form>
		<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<input type="hidden" name="action" value="aiom_repair">
			<?php wp_nonce_field( 'aiom_repair' ); ?>
			<button class="button"><?php esc_html_e( 'Repair cache directory', 'all-in-one-minifier' ); ?></button>
		</form>
	</div>
</section>

<section class="aiom-card">
	<h2 class="aiom-card-title"><?php esc_html_e( 'Compatibility', 'all-in-one-minifier' ); ?></h2>
	<?php if ( ! $aiom_compat ) : ?>
		<p class="description"><?php esc_html_e( 'No other caching or optimisation plugin detected.', 'all-in-one-minifier' ); ?></p>
	<?php else : ?>
		<table class="widefat striped aiom-table">
			<thead><tr><th><?php esc_html_e( 'Plugin', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'Overlapping features it has enabled', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'What this plugin does', 'all-in-one-minifier' ); ?></th></tr></thead>
			<tbody>
				<?php
				$aiom_names = array(
					'page_cache' => __( 'page cache', 'all-in-one-minifier' ),
					'html'       => __( 'HTML minify', 'all-in-one-minifier' ),
					'css'        => __( 'CSS minify', 'all-in-one-minifier' ),
					'js'         => __( 'JS minify', 'all-in-one-minifier' ),
					'defer'      => __( 'defer JS', 'all-in-one-minifier' ),
					'delay'      => __( 'delay JS', 'all-in-one-minifier' ),
				);
				foreach ( $aiom_compat as $aiom_p ) :
					$aiom_on = array();
					foreach ( $aiom_names as $aiom_k => $aiom_n ) {
						if ( ! empty( $aiom_p[ $aiom_k ] ) ) {
							$aiom_on[] = $aiom_n;
						}
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $aiom_p['name'] ); ?></strong></td>
						<td><?php echo esc_html( $aiom_on ? implode( ', ', $aiom_on ) : __( 'none detected', 'all-in-one-minifier' ) ); ?><?php echo '' !== $aiom_p['note'] ? '<br><span class="description">' . esc_html( $aiom_p['note'] ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( $aiom_on ? ( AIOM_Settings::get( 'auto_compat' ) ? __( 'Stands aside for those features; purges its cache when you clear this plugin’s cache.', 'all-in-one-minifier' ) : __( '“Avoid duplicate optimisation” is off: both plugins process the same features.', 'all-in-one-minifier' ) ) : __( 'Works alongside it; purges its cache when you clear this plugin’s cache.', 'all-in-one-minifier' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'CDNs and proxies (Cloudflare, Varnish) are not purged automatically unless their plugin listens to the aiom_purge_all action; purge them after clearing this cache.', 'all-in-one-minifier' ); ?></p>
</section>

<section class="aiom-card" id="aiom-log">
	<h2 class="aiom-card-title"><?php esc_html_e( 'Diagnostic log', 'all-in-one-minifier' ); ?></h2>
	<?php if ( ! $aiom_log ) : ?>
		<p class="description"><?php esc_html_e( 'Nothing logged. Warnings appear here when a file cannot be minified, a page fails validation or the cache cannot be written.', 'all-in-one-minifier' ); ?></p>
	<?php else : ?>
		<table class="widefat striped aiom-table">
			<thead><tr><th><?php esc_html_e( 'When', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'Level', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'Message', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'Times', 'all-in-one-minifier' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $aiom_log as $aiom_e ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aiom_e['time'] ) ); ?></td>
						<td><span class="aiom-chip aiom-chip--<?php echo esc_attr( 'error' === $aiom_e['level'] ? 'bad' : 'warn' ); ?>"><?php echo esc_html( $aiom_e['level'] ); ?></span></td>
						<td><?php echo esc_html( $aiom_e['message'] ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( isset( $aiom_e['count'] ) ? $aiom_e['count'] : 1 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo $aiom_post; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="aiom-inline-form aiom-right">
			<input type="hidden" name="action" value="aiom_clear_log">
			<?php wp_nonce_field( 'aiom_clear_log' ); ?>
			<button class="button-link"><?php esc_html_e( 'Clear log', 'all-in-one-minifier' ); ?></button>
		</form>
	<?php endif; ?>
</section>

<p class="description"><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-about' ) ); ?>"><?php esc_html_e( 'About the plugin and its developer', 'all-in-one-minifier' ); ?></a></p>
