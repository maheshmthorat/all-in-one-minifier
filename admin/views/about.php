<?php
/**
 * About screen: the plugin, its author, other plugins, helpful tools and support.
 *
 * No remote images are loaded here, so viewing the page makes no external requests.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

$aiom_plugins = array(
	array( 'AJAX Loading', 'https://wordpress.org/plugins/ajax-loading/', __( 'Load pages without a full reload.', 'all-in-one-minifier' ) ),
	array( 'All in one WP Content Protector', 'https://wordpress.org/plugins/all-in-one-wp-content-security/', __( 'Protect your content from copying.', 'all-in-one-minifier' ) ),
	array( 'Better Smooth Scroll', 'https://wordpress.org/plugins/better-smooth-scroll/', __( 'Smooth scrolling for your site.', 'all-in-one-minifier' ) ),
);
$aiom_tools   = array(
	array( 'PageSpeed Insights', 'https://pagespeed.web.dev/' ),
	array( 'GTmetrix', 'https://gtmetrix.com/' ),
	array( 'WebPageTest', 'https://www.webpagetest.org/' ),
	array( 'HTTP/3 Check', 'https://http3check.net/' ),
	array( 'Sucuri SiteCheck', 'https://sitecheck.sucuri.net/' ),
);
?>
<div class="aiom-grid aiom-grid--2-1">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'About All in one Minifier', 'all-in-one-minifier' ); ?></h2>
		<p>
			<?php
			/* translators: %s: version number. */
			echo esc_html( sprintf( __( 'Version %s. Safe HTML, CSS and JavaScript minification with versioned file caching, an optional page cache and measured before/after reports.', 'all-in-one-minifier' ), AIOM_VERSION ) );
			?>
		</p>
		<div class="aiom-actions">
			<a class="button" href="https://wordpress.org/plugins/all-in-one-minifier/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Plugin page', 'all-in-one-minifier' ); ?></a>
			<a class="button" href="https://wordpress.org/support/plugin/all-in-one-minifier/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support forum', 'all-in-one-minifier' ); ?></a>
			<a class="button" href="https://wordpress.org/support/plugin/all-in-one-minifier/reviews/#new-post" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Write a review', 'all-in-one-minifier' ); ?></a>
		</div>
	</section>

	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Developer', 'all-in-one-minifier' ); ?></h2>
		<div class="aiom-author">
			<span class="aiom-author-avatar" aria-hidden="true">MT</span>
			<div>
				<strong>Mahesh Mohan Thorat</strong><br>
				<a href="https://maheshthorat.web.app/" target="_blank" rel="noopener noreferrer">maheshthorat.web.app</a>
				<p class="description">
					<?php
					/* translators: %s: year. */
					echo esc_html( sprintf( __( '© %s — created by Mahesh Mohan Thorat', 'all-in-one-minifier' ), gmdate( 'Y' ) ) );
					?>
				</p>
			</div>
		</div>
	</section>
</div>

<div class="aiom-grid aiom-grid--2">
	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Other plugins', 'all-in-one-minifier' ); ?></h2>
		<ul class="aiom-linklist">
			<?php foreach ( $aiom_plugins as $aiom_p ) : ?>
				<li>
					<a href="<?php echo esc_url( $aiom_p[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $aiom_p[0] ); ?></a>
					<span class="description"><?php echo esc_html( $aiom_p[2] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="aiom-card">
		<h2 class="aiom-card-title"><?php esc_html_e( 'Helpful links', 'all-in-one-minifier' ); ?></h2>
		<p class="description"><?php esc_html_e( 'External testing services; they open in a new tab.', 'all-in-one-minifier' ); ?></p>
		<ul class="aiom-linklist">
			<?php foreach ( $aiom_tools as $aiom_t ) : ?>
				<li><a href="<?php echo esc_url( $aiom_t[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $aiom_t[0] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
</div>

<section class="aiom-card aiom-support">
	<div>
		<h2 class="aiom-card-title"><?php esc_html_e( 'Say thanks', 'all-in-one-minifier' ); ?></h2>
		<p class="description"><?php esc_html_e( 'All in one Minifier is free. If it helps your site, you can support its development.', 'all-in-one-minifier' ); ?></p>
		<a class="button button-primary" href="https://buymeacoffee.com/maheshmthorat" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy me a coffee', 'all-in-one-minifier' ); ?></a>
	</div>
	<div class="aiom-upi">
		<img src="<?php echo esc_url( AIOM_URL . 'admin/assets/img/qr.svg' ); ?>" width="180" height="180" alt="<?php esc_attr_e( 'UPI QR code', 'all-in-one-minifier' ); ?>" loading="lazy">
		<p><strong>Mr Mahesh Mohan Thorat</strong><br>UPI: <code>maheshmthorat@oksbi</code></p>
	</div>
</section>
