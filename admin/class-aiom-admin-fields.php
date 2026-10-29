<?php
/**
 * Settings screen definitions: sections, labels, help text and risk notes.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field metadata.
 */
class AIOM_Admin_Fields {

	/**
	 * Sections with their fields.
	 *
	 * Field keys: label, desc, risk (bool, shows a warning badge), warn (text),
	 * rows (textarea height), placeholder, options (select), unit, depends (feature yielding).
	 *
	 * @return array
	 */
	public static function sections() {
		$rules_help = __( 'One rule per line: a handle (e.g. <code>jquery-core</code>) or part of the file URL (e.g. <code>/plugins/my-slider/</code>). Use <code>*</code> as a wildcard.', 'all-in-one-minifier' );
		$url_help   = __( 'One rule per line. A path starting with <code>/</code> is matched against the page path (<code>/checkout/*</code>, <code>/landing/</code>); anything else matches part of the URL.', 'all-in-one-minifier' );
		return array(
			'general'    => array(
				'title'  => __( 'General', 'all-in-one-minifier' ),
				'icon'   => 'admin-generic',
				'desc'   => __( 'The recommended settings are safe for most sites. Turn on test mode before trying advanced options.', 'all-in-one-minifier' ),
				'fields' => array(
					'enabled'            => array(
						'label' => __( 'Enable optimisation', 'all-in-one-minifier' ),
						'desc'  => __( 'Master switch for every feature below. Use Tools → Safe mode or Pause to switch off temporarily without changing settings.', 'all-in-one-minifier' ),
					),
					'test_mode'          => array(
						'label' => __( 'Test mode', 'all-in-one-minifier' ),
						'desc'  => __( 'Only logged-in administrators see optimised pages; visitors get the original site and the page cache is off. Use it to check navigation, forms, search and checkout before going live.', 'all-in-one-minifier' ),
					),
					'optimize_logged_in' => array(
						'label' => __( 'Optimise for logged-in users', 'all-in-one-minifier' ),
						'desc'  => __( 'Off by default: logged-in users (and the admin bar) see unmodified pages. Logged-in users are never served from the page cache.', 'all-in-one-minifier' ),
					),
					'auto_compat'        => array(
						'label' => __( 'Avoid duplicate optimisation', 'all-in-one-minifier' ),
						'desc'  => __( 'When another active plugin (WP Rocket, LiteSpeed Cache, Autoptimize, W3 Total Cache…) already minifies or defers, the matching feature here stands aside instead of processing files twice.', 'all-in-one-minifier' ),
					),
				),
			),
			'html'       => array(
				'title'  => __( 'HTML', 'all-in-one-minifier' ),
				'icon'   => 'editor-code',
				'desc'   => __( 'HTML is processed by a tokenizer that never changes the content of pre, textarea, code, script or style elements, attribute values, or SVG text. If a result fails validation the original page is served.', 'all-in-one-minifier' ),
				'fields' => array(
					'html_minify'           => array(
						'label'   => __( 'Minify HTML', 'all-in-one-minifier' ),
						'desc'    => __( 'Collapses runs of whitespace to a single space or line break and tidies whitespace inside tags. Whitespace between inline elements is kept, so text and layout are unchanged. Trade-off: to minify, the page is held until it is complete, so a server that would otherwise stream the start of the page early sends its first byte later (total response time stays about the same). With server compression the transfer saving is usually small — a report shows both. The page cache removes this cost.', 'all-in-one-minifier' ),
						'depends' => 'html',
					),
					'html_remove_comments'  => array(
						'label' => __( 'Remove HTML comments', 'all-in-one-minifier' ),
						'desc'  => __( 'Conditional comments, server/edge includes (ESI, SSI) and framework markers (Vue, React, Knockout, Lit, Livewire) are always kept.', 'all-in-one-minifier' ),
					),
					'html_inline_css'       => array(
						'label' => __( 'Minify inline &lt;style&gt; blocks', 'all-in-one-minifier' ),
						'desc'  => __( 'Uses the same CSS minifier as for files. Results for large, repeated blocks (such as theme.json styles) are cached.', 'all-in-one-minifier' ),
					),
					'html_inline_json'      => array(
						'label' => __( 'Minify JSON blocks', 'all-in-one-minifier' ),
						'desc'  => __( 'Removes whitespace from JSON-LD structured data, import maps and speculation rules — only when the content is valid JSON.', 'all-in-one-minifier' ),
					),
					'html_inline_js'        => array(
						'label' => __( 'Minify inline &lt;script&gt; blocks', 'all-in-one-minifier' ),
						'desc'  => __( 'Parses each inline script and re-emits it without whitespace and comments. Scripts that cannot be parsed are left unchanged. Off by default: inline scripts are usually small and often change on every request (nonces), so the saving rarely justifies the processing time.', 'all-in-one-minifier' ),
					),
					'html_block_whitespace' => array(
						'label' => __( 'Remove whitespace between block elements', 'all-in-one-minifier' ),
						'desc'  => __( 'Removes the remaining single whitespace character between block-level tags such as &lt;div&gt; and &lt;li&gt;.', 'all-in-one-minifier' ),
						'risk'  => true,
						'warn'  => __( 'Elements styled as inline-block (common in menus and grids) render a small gap for that whitespace; removing it can shift layouts slightly. Check pages in test mode.', 'all-in-one-minifier' ),
					),
					'html_keep_comments'    => array(
						'label'       => __( 'Always keep comments containing', 'all-in-one-minifier' ),
						'desc'        => __( 'One text per line. Comments containing any of these are kept.', 'all-in-one-minifier' ),
						'placeholder' => "google_ad_section\nmy-plugin-marker",
						'rows'        => 3,
					),
					'html_preserve_classes' => array(
						'label'       => __( 'Keep exact whitespace inside elements with class', 'all-in-one-minifier' ),
						'desc'        => __( 'One class per line, for elements styled with white-space: pre through a stylesheet (inline styles are detected automatically).', 'all-in-one-minifier' ),
						'placeholder' => 'poem',
						'rows'        => 3,
					),
				),
			),
			'css'        => array(
				'title'  => __( 'CSS', 'all-in-one-minifier' ),
				'icon'   => 'art',
				'desc'   => __( 'Stylesheets loaded through WordPress are minified once, stored as versioned files and served from wp-content/cache/aiom/. Relative url() references are rewritten so fonts and images keep working. Files are not combined: with HTTP/2 combining rarely helps and it changes the cascade order and caching granularity.', 'all-in-one-minifier' ),
				'fields' => array(
					'css_minify'            => array(
						'label'   => __( 'Minify CSS files', 'all-in-one-minifier' ),
						'desc'    => __( 'Removes comments and whitespace only — no property or selector rewriting. Already-minified (.min.css) and external files are left alone.', 'all-in-one-minifier' ),
						'depends' => 'css',
					),
					'css_exclude'           => array(
						'label' => __( 'Do not minify these stylesheets', 'all-in-one-minifier' ),
						'desc'  => $rules_help,
						'rows'  => 3,
					),
					'css_font_display'      => array(
						'label'   => __( 'Add font-display to @font-face', 'all-in-one-minifier' ),
						'desc'    => __( 'Adds font-display to local @font-face rules that do not set one, so text is visible while web fonts load. “swap” shows fallback text immediately.', 'all-in-one-minifier' ),
						'options' => array(
							''         => __( 'Off', 'all-in-one-minifier' ),
							'swap'     => 'swap',
							'fallback' => 'fallback',
							'optional' => 'optional',
							'block'    => 'block',
						),
					),
					'css_google_fonts_swap' => array(
						'label' => __( 'Google Fonts: add display=swap', 'all-in-one-minifier' ),
						'desc'  => __( 'Adds display=swap to enqueued fonts.googleapis.com stylesheets that do not specify it.', 'all-in-one-minifier' ),
					),
					'css_critical'          => array(
						'label'       => __( 'Critical CSS', 'all-in-one-minifier' ),
						'desc'        => __( 'CSS printed inline at the top of &lt;head&gt; for above-the-fold content. This plugin does not generate critical CSS automatically (it needs a headless browser); paste CSS produced by a critical-CSS tool. Pages can add their own in the editor.', 'all-in-one-minifier' ),
						'rows'        => 6,
						'code'        => true,
						'placeholder' => 'body{margin:0;font-family:system-ui}.site-header{height:64px}',
					),
					'css_async'             => array(
						'label' => __( 'Load stylesheets asynchronously', 'all-in-one-minifier' ),
						'desc'  => __( 'Stylesheets stop blocking rendering (loaded with media="print" and switched on load, with a noscript fallback). Only applied when critical CSS is set, otherwise pages would flash unstyled.', 'all-in-one-minifier' ),
						'risk'  => true,
						'warn'  => __( 'Without complete critical CSS the page renders unstyled for a moment (flash of unstyled content). Requires inline event handlers, which a strict Content Security Policy blocks.', 'all-in-one-minifier' ),
					),
					'css_async_exclude'     => array(
						'label' => __( 'Keep these stylesheets render-blocking', 'all-in-one-minifier' ),
						'desc'  => $rules_help,
						'rows'  => 3,
					),
				),
			),
			'js'         => array(
				'title'  => __( 'JavaScript', 'all-in-one-minifier' ),
				'icon'   => 'media-code',
				'desc'   => __( 'Scripts are parsed into a syntax tree (bundled Peast parser) and printed back without whitespace and comments, so the program is unchanged; scripts that cannot be parsed are served as they are. Nothing needs Node.js.', 'all-in-one-minifier' ),
				'fields' => array(
					'js_minify'         => array(
						'label'   => __( 'Minify JavaScript files', 'all-in-one-minifier' ),
						'desc'    => __( 'Already-minified (.min.js) and external scripts are left alone. Large files are skipped (see “Largest file to minify”). Requires the PHP mbstring extension.', 'all-in-one-minifier' ),
						'depends' => 'js',
					),
					'js_exclude'        => array(
						'label' => __( 'Do not minify these scripts', 'all-in-one-minifier' ),
						'desc'  => $rules_help,
						'rows'  => 3,
					),
					'js_defer'          => array(
						'label'   => __( 'Defer scripts', 'all-in-one-minifier' ),
						'desc'    => __( 'Asks WordPress to load enqueued scripts with “defer”. WordPress keeps a script blocking when something that depends on it cannot be deferred, so dependency order is preserved. async is never added.', 'all-in-one-minifier' ),
						'risk'    => true,
						'warn'    => __( 'Inline scripts printed directly by a theme or plugin (not through WordPress) may run before a deferred library such as jQuery. jQuery is excluded by default for that reason. Test in test mode.', 'all-in-one-minifier' ),
						'depends' => 'defer',
					),
					'js_defer_exclude'  => array(
						'label' => __( 'Never defer', 'all-in-one-minifier' ),
						'desc'  => $rules_help,
						'rows'  => 3,
					),
					'js_delay'          => array(
						'label'   => __( 'Delay selected scripts until user interaction', 'all-in-one-minifier' ),
						'desc'    => __( 'Scripts matching the patterns below run after the first scroll, tap, click, key press or mouse movement (or after the timeout). Useful for chat widgets, analytics and ads.', 'all-in-one-minifier' ),
						'risk'    => true,
						'warn'    => __( 'Delayed scripts miss the page’s DOMContentLoaded and load events, and analytics may not count visitors who leave without interacting (use the timeout). Never delay consent managers, payment or checkout scripts.', 'all-in-one-minifier' ),
						'depends' => 'delay',
					),
					'js_delay_patterns' => array(
						'label'       => __( 'Scripts to delay', 'all-in-one-minifier' ),
						'desc'        => __( 'One per line: part of the script URL, a text that appears in an inline script, or a script id. Nothing is delayed unless listed.', 'all-in-one-minifier' ),
						'placeholder' => "googletagmanager.com\nconnect.facebook.net\nstatic.hotjar.com\nembed.tawk.to",
						'rows'        => 4,
					),
					'js_delay_timeout'  => array(
						'label' => __( 'Run delayed scripts anyway after', 'all-in-one-minifier' ),
						'desc'  => __( '0 waits for interaction only.', 'all-in-one-minifier' ),
						'unit'  => __( 'seconds', 'all-in-one-minifier' ),
					),
				),
			),
			'hints'      => array(
				'title'  => __( 'Resource hints', 'all-in-one-minifier' ),
				'icon'   => 'networking',
				'desc'   => __( 'Nothing is preloaded automatically: preloading the wrong files competes with important content. Run a report to see which origins and files a page uses.', 'all-in-one-minifier' ),
				'fields' => array(
					'hints_preconnect' => array(
						'label'       => __( 'Preconnect to origins', 'all-in-one-minifier' ),
						'desc'        => __( 'One origin per line, for third-party hosts needed early (fonts, CDN). Add “crossorigin” after the origin for font hosts (fonts.gstatic.com gets it automatically). Keep this list short.', 'all-in-one-minifier' ),
						'placeholder' => "https://fonts.gstatic.com\nhttps://cdn.example.com",
						'rows'        => 3,
					),
					'hints_preload'    => array(
						'label'       => __( 'Preload files', 'all-in-one-minifier' ),
						'desc'        => __( 'One URL per line, for files the browser discovers late, such as a web font used above the fold or the hero image. The type is detected from the extension; you can add as=, type=, media= or fetchpriority=high.', 'all-in-one-minifier' ),
						'placeholder' => "/wp-content/themes/mytheme/fonts/inter.woff2\n/wp-content/uploads/hero.webp fetchpriority=high",
						'rows'        => 3,
					),
				),
			),
			'exclusions' => array(
				'title'  => __( 'Exclusions', 'all-in-one-minifier' ),
				'icon'   => 'dismiss',
				'desc'   => __( 'Excluded pages get no optimisation at all and are never page-cached. Individual pages can also be excluded from the editor (“Optimisation” box).', 'all-in-one-minifier' ),
				'fields' => array(
					'exclude_urls'       => array(
						'label'       => __( 'Never optimise these URLs', 'all-in-one-minifier' ),
						'desc'        => $url_help,
						'placeholder' => "/members/*\n/landing-page/",
						'rows'        => 4,
					),
					'exclude_post_types' => array(
						'label' => __( 'Never optimise these post types', 'all-in-one-minifier' ),
						'desc'  => __( 'Applies to single entries of the selected types.', 'all-in-one-minifier' ),
					),
					'wc_bypass'          => array(
						'label' => __( 'Leave WooCommerce cart, checkout and account pages untouched', 'all-in-one-minifier' ),
						'desc'  => __( 'Recommended. When off, these pages are minified, but scripts are never deferred or delayed there and they are never page-cached.', 'all-in-one-minifier' ),
					),
				),
			),
			'page_cache' => array(
				'title'  => __( 'Page cache', 'all-in-one-minifier' ),
				'icon'   => 'database',
				'desc'   => __( 'Stores finished pages and serves them to anonymous visitors before WordPress builds the page. This is the feature that reduces server response time (TTFB); minification alone reduces bytes, not server time.', 'all-in-one-minifier' ),
				'fields' => array(
					'page_cache'               => array(
						'label'   => __( 'Enable page cache for anonymous visitors', 'all-in-one-minifier' ),
						'desc'    => __( 'Never used for logged-in users, commenters, carts or other visitors with session cookies, requests with query strings (other than tracking parameters), POST requests, searches, 404s, previews, password-protected content, or pages that set cookies or define DONOTCACHEPAGE. Pages are purged when content changes.', 'all-in-one-minifier' ),
						'risk'    => true,
						'warn'    => __( 'Pages that show per-visitor content without cookies (for example geolocation or random content) would show the same version to everyone; exclude them below. Disabled automatically while another page cache is active.', 'all-in-one-minifier' ),
						'depends' => 'page_cache',
					),
					'page_cache_ttl'           => array(
						'label' => __( 'Keep cached pages for', 'all-in-one-minifier' ),
						'desc'  => __( '10 hours or less keeps WordPress nonces in cached forms valid.', 'all-in-one-minifier' ),
						'unit'  => __( 'hours', 'all-in-one-minifier' ),
					),
					'page_cache_mobile'        => array(
						'label' => __( 'Separate cache for mobile devices', 'all-in-one-minifier' ),
						'desc'  => __( 'Keep on if your theme or a plugin prints different HTML for phones (wp_is_mobile()).', 'all-in-one-minifier' ),
					),
					'page_cache_exclude'       => array(
						'label' => __( 'Never cache these URLs', 'all-in-one-minifier' ),
						'desc'  => $url_help,
						'rows'  => 3,
					),
					'page_cache_cookies'       => array(
						'label'       => __( 'Bypass when these cookies are present', 'all-in-one-minifier' ),
						'desc'        => __( 'Cookie name prefixes, one per line, in addition to WordPress, WooCommerce and EDD session cookies.', 'all-in-one-minifier' ),
						'placeholder' => 'my_membership_',
						'rows'        => 3,
					),
					'page_cache_ignore_params' => array(
						'label' => __( 'Query parameters that may use the cache', 'all-in-one-minifier' ),
						'desc'  => __( 'Tracking parameters that do not change the page. Any other query string bypasses the cache. A trailing * matches a prefix.', 'all-in-one-minifier' ),
						'rows'  => 4,
					),
				),
			),
			'cache'      => array(
				'title'  => __( 'Cache storage & limits', 'all-in-one-minifier' ),
				'icon'   => 'archive',
				'desc'   => __( 'Optimised files are versioned by source file, so they never need manual purging after theme or plugin updates. Unused files are deleted automatically.', 'all-in-one-minifier' ),
				'fields' => array(
					'cache_max_mb'         => array(
						'label' => __( 'Maximum cache size', 'all-in-one-minifier' ),
						'desc'  => __( 'When exceeded, cached pages are removed first, then the least recently used files.', 'all-in-one-minifier' ),
						'unit'  => 'MB',
					),
					'cache_unused_days'    => array(
						'label' => __( 'Delete optimised files unused for', 'all-in-one-minifier' ),
						'desc'  => __( 'Old versions stay available this long for pages cached by browsers, CDNs or other caches.', 'all-in-one-minifier' ),
						'unit'  => __( 'days', 'all-in-one-minifier' ),
					),
					'max_asset_kb'         => array(
						'label' => __( 'Largest file to minify', 'all-in-one-minifier' ),
						'desc'  => __( 'Bigger files are served unminified. JavaScript parsing needs roughly 70 times the file size in PHP memory.', 'all-in-one-minifier' ),
						'unit'  => 'KB',
					),
					'generation_budget_ms' => array(
						'label' => __( 'Time budget for new files per request', 'all-in-one-minifier' ),
						'desc'  => __( 'New optimised files are created after the page has been sent where the server supports it (PHP-FPM, LiteSpeed). Elsewhere, at most this much time is spent at the end of a request; remaining files are created on later requests.', 'all-in-one-minifier' ),
						'unit'  => 'ms',
					),
					'stats_enabled'        => array(
						'label' => __( 'Record cache statistics', 'all-in-one-minifier' ),
						'desc'  => __( 'Appends a few bytes per optimised request to a log file that is summarised hourly. No database writes on page views.', 'all-in-one-minifier' ),
					),
				),
			),
		);
	}
}
