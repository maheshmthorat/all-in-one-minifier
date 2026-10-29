<?php
/**
 * One report.
 *
 * @package All_In_One_Minifier
 *
 * @var array $report       Report data.
 * @var bool  $aiom_compact Optional compact rendering.
 */

defined( 'ABSPATH' ) || exit;

$aiom_compact = ! empty( $aiom_compact );
$aiom_r       = $report;
$aiom_path    = (string) wp_parse_url( $aiom_r['url'], PHP_URL_PATH );
$aiom_metric  = static function ( $label, $before, $after, $unit, $sub = '', $note = '' ) {
	$fmt   = static function ( $v ) use ( $unit ) {
		if ( null === $v ) {
			return '—';
		}
		return 'ms' === $unit ? number_format_i18n( $v, $v < 10 ? 1 : 0 ) . ' ms' : AIOM_Admin::bytes( $v );
	};
	$class = '';
	if ( null !== $before && null !== $after && $before > 0 ) {
		$change = ( $after - $before ) / $before;
		$class  = $change < -0.01 ? 'is-better' : ( $change > 0.05 ? 'is-worse' : 'is-same' );
	}
	?>
	<div class="aiom-metric <?php echo esc_attr( $class ); ?>">
		<span class="aiom-metric-label"><?php echo esc_html( $label ); ?></span>
		<span class="aiom-metric-values">
			<span class="aiom-metric-before"><?php echo esc_html( $fmt( $before ) ); ?></span>
			<span class="aiom-metric-arrow" aria-hidden="true">→</span>
			<span class="aiom-metric-after"><?php echo esc_html( $fmt( $after ) ); ?></span>
			<?php if ( null !== $before && null !== $after ) : ?>
				<span class="aiom-metric-delta"><?php echo esc_html( AIOM_Admin::delta( $before, $after ) ); ?></span>
			<?php endif; ?>
		</span>
		<?php if ( '' !== $sub ) : ?>
			<span class="aiom-metric-sub"><?php echo esc_html( $sub ); ?></span>
		<?php endif; ?>
		<?php if ( '' !== $note ) : ?>
			<span class="aiom-metric-note"><?php echo esc_html( $note ); ?></span>
		<?php endif; ?>
	</div>
	<?php
};
$aiom_files = static function ( $t ) {
	/* translators: 1: minified count, 2: total count, 3: external count. */
	return sprintf( __( '%1$d of %2$d files minified · %3$d external', 'all-in-one-minifier' ), $t['optimised'], $t['files'], $t['external'] );
};
?>
<div class="aiom-report">
	<div class="aiom-report-head">
		<div>
			<a href="<?php echo esc_url( $aiom_r['url'] ); ?>" target="_blank" rel="noopener" class="aiom-report-url"><?php echo esc_html( '' !== $aiom_path ? $aiom_path : '/' ); ?></a>
			<time datetime="<?php echo esc_attr( gmdate( 'c', $aiom_r['created'] ) ); ?>"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aiom_r['created'] ) ); ?></time>
		</div>
		<?php if ( $aiom_compact && ! empty( $aiom_r['id'] ) ) : ?>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aiom-reports&report=' . (int) $aiom_r['id'] ) ); ?>"><?php esc_html_e( 'Details', 'all-in-one-minifier' ); ?></a>
		<?php endif; ?>
	</div>

	<div class="aiom-metrics">
		<?php
		$aiom_metric( __( 'HTML size', 'all-in-one-minifier' ), $aiom_r['html']['bytes'], $aiom_r['html']['opt_bytes'], 'bytes', sprintf( /* translators: 1: before, 2: after. */ __( 'gzip estimate: %1$s → %2$s', 'all-in-one-minifier' ), AIOM_Admin::bytes( $aiom_r['html']['gzip'] ), AIOM_Admin::bytes( $aiom_r['html']['opt_gzip'] ) ) );
		$aiom_metric( __( 'CSS (local files)', 'all-in-one-minifier' ), $aiom_r['css']['bytes'] ? $aiom_r['css']['bytes'] : null, $aiom_r['css']['bytes'] ? $aiom_r['css']['opt_bytes'] : null, 'bytes', $aiom_files( $aiom_r['css'] ) );
		$aiom_metric( __( 'JavaScript (local files)', 'all-in-one-minifier' ), $aiom_r['js']['bytes'] ? $aiom_r['js']['bytes'] : null, $aiom_r['js']['bytes'] ? $aiom_r['js']['opt_bytes'] : null, 'bytes', $aiom_files( $aiom_r['js'] ) );
		$aiom_metric(
			__( 'Server response (TTFB)', 'all-in-one-minifier' ),
			$aiom_r['ttfb']['original'],
			$aiom_r['ttfb']['optimised'],
			'ms',
			null !== $aiom_r['ttfb']['cached'] ? sprintf( /* translators: %s: milliseconds. */ __( 'from page cache: %s ms', 'all-in-one-minifier' ), number_format_i18n( $aiom_r['ttfb']['cached'], 1 ) ) : ( AIOM_Settings::get( 'page_cache' ) ? __( 'page cache: not served from cache for this page', 'all-in-one-minifier' ) : __( 'page cache off', 'all-in-one-minifier' ) ),
			__( 'median of 3, measured on this server', 'all-in-one-minifier' )
		);
		?>
	</div>

	<?php if ( ! $aiom_compact ) : ?>
		<div class="aiom-report-facts">
			<div><span><?php esc_html_e( 'Files already minified', 'all-in-one-minifier' ); ?></span><strong>
				<?php
				$aiom_already = 0;
				foreach ( array( 'css', 'js' ) as $aiom_t ) {
					foreach ( isset( $aiom_r['assets'][ $aiom_t ] ) ? $aiom_r['assets'][ $aiom_t ] : array() as $aiom_a ) {
						$aiom_already += 'already_minified' === $aiom_a['status'] ? 1 : 0;
					}
				}
				echo esc_html( number_format_i18n( $aiom_already ) );
				?>
			</strong></div>
			<div><span><?php esc_html_e( 'HTML processing time', 'all-in-one-minifier' ); ?></span><strong><?php echo null !== $aiom_r['html']['ms'] ? esc_html( number_format_i18n( $aiom_r['html']['ms'], 2 ) . ' ms' ) : '—'; ?></strong></div>
			<div><span><?php esc_html_e( 'Blocking stylesheets in head', 'all-in-one-minifier' ); ?></span><strong><?php echo esc_html( $aiom_r['blocking']['original']['css'] . ' → ' . $aiom_r['blocking']['optimised']['css'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'Blocking scripts in head', 'all-in-one-minifier' ); ?></span><strong><?php echo esc_html( $aiom_r['blocking']['original']['js'] . ' → ' . $aiom_r['blocking']['optimised']['js'] ); ?></strong></div>
			<div><span><?php esc_html_e( 'Delayed scripts', 'all-in-one-minifier' ); ?></span><strong><?php echo esc_html( number_format_i18n( $aiom_r['delayed'] ) ); ?></strong></div>
			<div><span><?php esc_html_e( 'Server compression', 'all-in-one-minifier' ); ?></span><strong><?php echo $aiom_r['compressed'] ? esc_html__( 'Yes', 'all-in-one-minifier' ) : esc_html__( 'No', 'all-in-one-minifier' ); ?></strong></div>
			<div><span><?php esc_html_e( 'TTFB samples (ms)', 'all-in-one-minifier' ); ?></span><strong><?php echo esc_html( implode( ', ', $aiom_r['ttfb']['samples_o'] ) . ' / ' . implode( ', ', $aiom_r['ttfb']['samples_n'] ) ); ?></strong></div>
		</div>

		<?php if ( ! empty( $aiom_r['warnings'] ) ) : ?>
			<div class="aiom-callout aiom-callout--warn">
				<strong><?php esc_html_e( 'Notes', 'all-in-one-minifier' ); ?></strong>
				<ul>
					<?php foreach ( $aiom_r['warnings'] as $aiom_w ) : ?>
						<li><?php echo esc_html( $aiom_w ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php
		foreach ( array(
			'css' => __( 'Stylesheets', 'all-in-one-minifier' ),
			'js'  => __( 'Scripts', 'all-in-one-minifier' ),
		) as $aiom_type => $aiom_title ) :
			if ( empty( $aiom_r['assets'][ $aiom_type ] ) ) {
				continue;
			}
			?>
			<h3 class="aiom-subtitle"><?php echo esc_html( $aiom_title ); ?></h3>
			<table class="widefat striped aiom-table aiom-assets">
				<thead><tr><th><?php esc_html_e( 'File', 'all-in-one-minifier' ); ?></th><th><?php esc_html_e( 'Result', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'Before', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'After', 'all-in-one-minifier' ); ?></th><th class="num"><?php esc_html_e( 'gzip est.', 'all-in-one-minifier' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $aiom_r['assets'][ $aiom_type ] as $aiom_a ) : ?>
						<tr>
							<td><code title="<?php echo esc_attr( $aiom_a['handle'] ); ?>"><?php echo esc_html( $aiom_a['src'] ); ?></code></td>
							<td><span class="aiom-chip aiom-chip--<?php echo esc_attr( 'optimised' === $aiom_a['status'] ? 'on' : ( 'skipped' === $aiom_a['status'] ? 'warn' : 'off' ) ); ?>"><?php echo esc_html( str_replace( '_', ' ', $aiom_a['status'] ) ); ?></span> <?php echo esc_html( $aiom_a['note'] ); ?></td>
							<td class="num"><?php echo isset( $aiom_a['bytes'] ) ? esc_html( AIOM_Admin::bytes( $aiom_a['bytes'] ) ) : '—'; ?></td>
							<td class="num"><?php echo isset( $aiom_a['opt_bytes'] ) ? esc_html( AIOM_Admin::bytes( $aiom_a['opt_bytes'] ) . ' (' . AIOM_Admin::delta( $aiom_a['bytes'], $aiom_a['opt_bytes'] ) . ')' ) : '—'; ?></td>
							<td class="num"><?php echo isset( $aiom_a['gzip'] ) ? esc_html( AIOM_Admin::bytes( $aiom_a['gzip'] ) . ' → ' . AIOM_Admin::bytes( $aiom_a['opt_gzip'] ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<details class="aiom-howto">
			<summary><?php esc_html_e( 'How these numbers were measured', 'all-in-one-minifier' ); ?></summary>
			<ul>
				<li><?php esc_html_e( 'The server requested this page as an anonymous visitor: once to warm up, then three times without optimisation and three times with it, alternating.', 'all-in-one-minifier' ); ?></li>
				<li><?php esc_html_e( 'Sizes are the exact bytes of the HTML response and of each local file. Gzip figures are computed here as an estimate of transfer size; your server or CDN may use different compression.', 'all-in-one-minifier' ); ?></li>
				<li>
					<?php
					echo esc_html(
						'curl_ttfb' === $aiom_r['ttfb']['method']
							? __( 'TTFB is time to first byte as reported by curl, measured from this server to itself, so it excludes internet latency. Minification adds a little processing time; the page cache is what reduces server time.', 'all-in-one-minifier' )
							: __( 'TTFB here is the full request time measured in PHP (curl was not available), measured from this server to itself.', 'all-in-one-minifier' )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Smaller files do not translate one-to-one into faster pages: compression, caching, network and rendering matter too. No Lighthouse score or Core Web Vitals are estimated here — use the external test below for lab metrics.', 'all-in-one-minifier' ); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $aiom_r['url'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Test with PageSpeed Insights', 'all-in-one-minifier' ); ?></a>
				<span class="description"><?php esc_html_e( '(external Google service; opens in a new tab and sends this URL to Google. Only works for publicly reachable sites.)', 'all-in-one-minifier' ); ?></span>
			</p>
		</details>
	<?php endif; ?>
</div>
