<?php
/**
 * Reports screen.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen parameters.
$aiom_report_id = isset( $_GET['report'] ) ? absint( $_GET['report'] ) : 0;
$aiom_prefill   = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
// phpcs:enable

if ( $aiom_report_id ) :
	$report = AIOM_Reports::get( $aiom_report_id );
	?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-reports' ) ); ?>">← <?php esc_html_e( 'All reports', 'all-in-one-minifier' ); ?></a></p>
	<?php if ( ! $report ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'Report not found.', 'all-in-one-minifier' ); ?></p></div>
	<?php else : ?>
		<section class="aiom-card">
			<?php include AIOM_DIR . 'admin/views/report-card.php'; ?>
		</section>
		<?php $aiom_history = AIOM_Reports::history( $report['url'] ); ?>
		<?php if ( count( $aiom_history ) > 1 ) : ?>
			<section class="aiom-card">
				<h2 class="aiom-card-title"><?php esc_html_e( 'History for this page', 'all-in-one-minifier' ); ?></h2>
				<table class="widefat striped aiom-table">
					<thead><tr><th><?php esc_html_e( 'Date', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'HTML', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'CSS', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'JS', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'TTFB original → optimised', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'Page cache', 'all-in-one-minifier' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $aiom_history as $aiom_h ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-reports&report=' . (int) $aiom_h['id'] ) ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aiom_h['created'] ) ); ?></a></td>
								<td class="num"><?php echo esc_html( AIOM_Admin::bytes( $aiom_h['html']['opt_bytes'] ) . ' (' . AIOM_Admin::delta( $aiom_h['html']['bytes'], $aiom_h['html']['opt_bytes'] ) . ')' ); ?></td>
								<td class="num"><?php echo esc_html( AIOM_Admin::bytes( $aiom_h['css']['opt_bytes'] ) ); ?></td>
								<td class="num"><?php echo esc_html( AIOM_Admin::bytes( $aiom_h['js']['opt_bytes'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( (float) $aiom_h['ttfb']['original'], 1 ) . ' → ' . number_format_i18n( (float) $aiom_h['ttfb']['optimised'], 1 ) . ' ms' ); ?></td>
								<td class="num"><?php echo null !== $aiom_h['ttfb']['cached'] ? esc_html( number_format_i18n( $aiom_h['ttfb']['cached'], 1 ) . ' ms' ) : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>
	<?php endif; ?>
	<?php
	return;
endif;

$aiom_candidates = AIOM_Admin::candidate_urls( 25 );
$aiom_recent     = AIOM_Reports::recent( 30 );
?>
<div class="aiom-grid aiom-grid--2-1">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Measure a page', 'all-in-one-minifier' ); ?></h2>
		<form class="aiom-report-form">
			<label for="aiom-report-select" class="screen-reader-text"><?php esc_html_e( 'Page', 'all-in-one-minifier' ); ?></label>
			<select id="aiom-report-select">
				<?php foreach ( $aiom_candidates as $aiom_u => $aiom_l ) : ?>
					<option value="<?php echo esc_attr( $aiom_u ); ?>" <?php selected( $aiom_prefill, $aiom_u ); ?>><?php echo esc_html( $aiom_l ); ?></option>
				<?php endforeach; ?>
				<option value="" <?php selected( '' !== $aiom_prefill && ! isset( $aiom_candidates[ $aiom_prefill ] ) ); ?>><?php esc_html_e( 'Another URL…', 'all-in-one-minifier' ); ?></option>
			</select>
			<label for="aiom-report-url" class="screen-reader-text"><?php esc_html_e( 'URL', 'all-in-one-minifier' ); ?></label>
			<input type="url" id="aiom-report-url" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/example-page/' ) ); ?>" value="<?php echo esc_attr( isset( $aiom_candidates[ $aiom_prefill ] ) ? '' : $aiom_prefill ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Run report', 'all-in-one-minifier' ); ?></button>
		</form>
		<p class="aiom-report-status" aria-live="polite"></p>
		<div class="aiom-report-output"></div>
	</section>
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'What a report shows', 'all-in-one-minifier' ); ?></h2>
		<ul class="aiom-bullets">
			<li><?php esc_html_e( 'Exact HTML, CSS and JavaScript sizes before and after optimisation, plus a gzip estimate.', 'all-in-one-minifier' ); ?></li>
			<li><?php esc_html_e( 'Which files were minified, skipped or external, and why.', 'all-in-one-minifier' ); ?></li>
			<li><?php esc_html_e( 'Server response time (TTFB) with and without optimisation, and from the page cache when it is enabled.', 'all-in-one-minifier' ); ?></li>
			<li><?php esc_html_e( 'Render-blocking stylesheets and scripts in the page head.', 'all-in-one-minifier' ); ?></li>
		</ul>
		<p class="description"><?php esc_html_e( 'Reports run only when you start them, from this server to itself; visitors are never slowed down. They do not invent Lighthouse scores or Core Web Vitals.', 'all-in-one-minifier' ); ?></p>
	</section>
</div>

<section class="aiom-card">
	<h2 class="aiom-card-title"><?php esc_html_e( 'Recent reports', 'all-in-one-minifier' ); ?></h2>
	<?php if ( ! $aiom_recent ) : ?>
		<p class="description"><?php esc_html_e( 'No reports yet.', 'all-in-one-minifier' ); ?></p>
	<?php else : ?>
		<table class="widefat striped aiom-table">
			<thead><tr>
				<th><?php esc_html_e( 'Page', 'all-in-one-minifier' ); ?></th>
				<th><?php esc_html_e( 'Date', 'all-in-one-minifier' ); ?></th>
				<th class="num"><?php esc_html_e( 'HTML', 'all-in-one-minifier' ); ?></th>
				<th class="num"><?php esc_html_e( 'CSS', 'all-in-one-minifier' ); ?></th>
				<th class="num"><?php esc_html_e( 'JS', 'all-in-one-minifier' ); ?></th>
				<th class="num"><?php esc_html_e( 'TTFB', 'all-in-one-minifier' ); ?></th>
			</tr></thead>
			<tbody>
				<?php foreach ( $aiom_recent as $aiom_rr ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-reports&report=' . (int) $aiom_rr['id'] ) ); ?>"><?php echo esc_html( (string) wp_parse_url( $aiom_rr['url'], PHP_URL_PATH ) ); ?></a></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aiom_rr['created'] ) ); ?></td>
						<td class="num"><?php echo esc_html( AIOM_Admin::delta( $aiom_rr['html']['bytes'], $aiom_rr['html']['opt_bytes'] ) ); ?></td>
						<td class="num"><?php echo esc_html( $aiom_rr['css']['bytes'] ? AIOM_Admin::delta( $aiom_rr['css']['bytes'], $aiom_rr['css']['opt_bytes'] ) : '—' ); ?></td>
						<td class="num"><?php echo esc_html( $aiom_rr['js']['bytes'] ? AIOM_Admin::delta( $aiom_rr['js']['bytes'], $aiom_rr['js']['opt_bytes'] ) : '—' ); ?></td>
						<td class="num">
							<?php
							echo esc_html( number_format_i18n( (float) $aiom_rr['ttfb']['original'], 0 ) . ' → ' . number_format_i18n( (float) $aiom_rr['ttfb']['optimised'], 0 ) . ' ms' );
							if ( null !== $aiom_rr['ttfb']['cached'] ) {
								echo esc_html( ' · ' . sprintf( /* translators: %s: ms. */ __( 'cached %s ms', 'all-in-one-minifier' ), number_format_i18n( $aiom_rr['ttfb']['cached'], 1 ) ) );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiom-inline-form aiom-right">
			<input type="hidden" name="action" value="aiom_delete_reports">
			<?php wp_nonce_field( 'aiom_delete_reports' ); ?>
			<button class="button-link button-link-delete" data-aiom-confirm="<?php esc_attr_e( 'Delete every report?', 'all-in-one-minifier' ); ?>"><?php esc_html_e( 'Delete all reports', 'all-in-one-minifier' ); ?></button>
		</form>
	<?php endif; ?>
</section>
