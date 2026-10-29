=== All in one Minifier ===
Contributors: maheshmthorat
Donate link: https://buymeacoffee.com/maheshmthorat
Tags: minify, performance, cache, page cache, optimize
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Safe HTML, CSS and JavaScript minification with versioned file caching, an optional page cache and measured before/after reports.

== Description ==

All in one Minifier makes your pages smaller and, with its optional page cache, faster to serve — without breaking them. Every transformation is designed so that browsers see exactly the same page, and every number it shows you is measured on your own server.

= What it does =

* **HTML minification** with a real HTML tokenizer (not search-and-replace). Whitespace runs are collapsed, never removed between inline elements, and nothing inside `pre`, `textarea`, `code`, `script`, `style`, SVG text or attribute values is changed. Conditional comments and framework markers (Vue, React, Knockout, Lit, Livewire, ESI) are kept. If a result fails validation, the original page is served.
* **CSS minification** for stylesheets loaded through WordPress: comments and whitespace only, with relative `url()` references rewritten so fonts and images keep working. Custom properties are left exactly as written.
* **JavaScript minification** using a bundled JavaScript parser (Peast): scripts are parsed into a syntax tree and printed back without whitespace and comments, so the program is unchanged. Scripts that cannot be parsed are served untouched. No Node.js or shell access needed.
* **Versioned file cache**: each file is minified once and served from `wp-content/cache/aiom/`. File names include a hash of the source path, modification time and relevant settings, so edited themes and plugins are picked up automatically and browsers never get stale files.
* **Optional page cache** for anonymous visitors (off by default) — the feature that actually lowers server response time. It never serves logged-in users, carts, checkouts, accounts, POST requests, searches, previews or pages that set cookies, and it is purged when content changes.
* **Optional loading improvements**, each off by default and marked “Test first”: dependency-aware `defer` through the WordPress script API, delaying chosen scripts (analytics, chat) until user interaction, asynchronous stylesheets with your critical CSS, `font-display`, preconnect and preload hints.
* **Measured reports**: request a page as a visitor with and without optimisation and see the exact HTML, CSS and JavaScript sizes, which files were minified or skipped and why, render-blocking resources, and server response time (TTFB). No Lighthouse scores or Core Web Vitals are invented.
* **Per-page controls** in the editor, exclusion rules for files, URLs and post types, and a WooCommerce safe mode for cart, checkout and account pages.
* **Safety tools**: test mode (only administrators see optimised pages), safe mode and pause (switch everything off without losing settings), the `AIOM_DISABLE` constant, settings export/import, diagnostics and a log.

= What it deliberately does not do =

* **Combine files.** With HTTP/2, combining rarely helps and changes cascade order and caching. Each file is minified separately.
* **Generate critical CSS automatically.** That needs a headless browser. Paste critical CSS from a dedicated tool, site-wide or per page.
* **Add `async` to scripts**, which can change execution order. `defer` is applied through WordPress so dependencies stay in order.
* **Claim speed it has not measured.** Smaller files do not translate one-to-one into faster pages. Use the Reports screen to see what changed on your site.

= How to check the results =

1. Turn on **Test mode** (Settings → General) and browse your site while logged in as an administrator: you see the optimised pages, visitors do not.
2. Open **Reports** and measure your key pages. The report shows bytes before/after (with a gzip estimate), each file’s result and server response time.
3. When everything works, turn test mode off.

= Good to know =

* With server compression (gzip/brotli), HTML minification saves only a little on the wire. To minify, the page is held until it is complete, so on servers that stream the start of a page early the first byte arrives later (total response time stays about the same). Reports show both effects. The page cache is what reduces server time.
* Most WordPress core and WooCommerce files are already minified; they are detected and served as they are.
* Works alongside other caching plugins. If WP Rocket, LiteSpeed Cache, W3 Total Cache, Autoptimize and others already minify or defer, the matching feature here stands aside, and their caches are purged when you clear this plugin’s cache. This page cache stays off while another page cache is active.

= Privacy =

The plugin makes no external requests and sends no data anywhere. Reports request your own pages from your own server. The optional “Test with PageSpeed Insights” link opens Google’s site in your browser only when you click it.

= For developers =

* Filters: `aiom_cache_dir`, `aiom_cache_url` (cache location; the page cache reads them before most plugins load, so set them in a must-use plugin), `aiom_loopback_url` (URL used for report requests).
* Action: `aiom_purge_all` — fires after the cache is cleared, for purging CDNs or proxies.
* Constants: `AIOM_DISABLE` turns everything off; `DONOTMINIFY` skips a request; `DONOTCACHEPAGE` prevents page caching.
* Add `data-aiom-nodelay` to a script tag to keep it from ever being delayed.

== Installation ==

1. Install from Plugins → Add New (search for “All in one Minifier”), or upload the `all-in-one-minifier` folder to `/wp-content/plugins/`.
2. Activate the plugin. The recommended settings are active immediately: HTML, CSS and JavaScript minification. Nothing changes when scripts run.
3. Visit **AIO Minifier → Reports** and measure your home page.
4. Optional: enable the page cache (Settings → Page cache) if you do not use another page cache, and try the “Test first” options in test mode.

The plugin needs PHP 7.4 or newer, the `mbstring` extension for JavaScript minification, and a writable `wp-content/cache` directory.

= Upgrading from 3.x =

Version 4 is a complete rewrite. On upgrade it removes the 3.x data (its options, the `wp_alone_minifier_analysis` table and `wp-content/cache/alone_minifier`), because pages cached by 3.x could be served under the wrong address. The 3.x page cache was always on; in 4.0 the page cache is a separate setting and **off by default** — enable it under Settings → Page cache. Run a report afterwards to compare.

= If something looks wrong =

* Tools → **Safe mode** switches every optimisation off immediately; settings are kept.
* Or add `define( 'AIOM_DISABLE', true );` to `wp-config.php`.
* Exclude a page in its editor (“Optimisation” box), or a file under Settings → CSS/JavaScript.
* Tools → Diagnostics and the log explain files that were left unminified.

== Frequently Asked Questions ==

= Will this break my site? =

The default settings only remove characters that browsers ignore, and they were verified against independent HTML, CSS and JavaScript parsers on thousands of real files from WordPress core, WooCommerce, Elementor, Yoast SEO, Contact Form 7 and popular themes. If a page or file cannot be processed safely it is served unchanged. Options that change how scripts load are off by default and marked “Test first”.

= Why are some files “already compact”? =

Many plugins ship minified files without a `.min` suffix. If minifying would save less than 2%, the original file is served so its URL does not change for nothing.

= Why did server response time go up a little? =

Processing a page took about 2–3 ms on the test server (it depends on page size and server speed), and holding the page to minify it means the first byte is sent when the page is complete. Enable the page cache (or your host’s) to remove page generation time entirely.

= Does it work with WooCommerce? =

Yes. Cart, checkout and account pages are left untouched by default, never page-cached, and scripts there are never deferred or delayed. Visitors with items in their cart bypass the page cache.

= Does it work with page builders? =

Editor and preview requests are detected by the URL parameters of Elementor, Beaver Builder, Divi, Bricks, Oxygen, Breakdance, WPBakery, Brizy, Thrive and the Customizer, and left unoptimised. Front-end pages built with a builder are minified like any other page; Elementor’s own scripts were included in the JavaScript verification, but each builder has not been tested in a live site.

= Does it support multisite? =

Yes. Each site has its own settings, reports and cache directory (`wp-content/cache/aiom/<site-id>/`), and the plugin can be network-activated. This was tested on a subdirectory network, including the page cache and uninstalling.

= Where are files stored? =

In `wp-content/cache/aiom/`. Unused files are deleted automatically after 30 days (configurable), and the cache is kept under a size limit.

== Screenshots ==

1. Dashboard: status of each optimisation, cache statistics and the latest measured report.
2. Settings with plain-language explanations; options that can affect behaviour are marked “Test first”.
3. A report: sizes before and after, what happened to each file, and server response time.
4. Cache management: storage, statistics, preloading and files left unminified.

== Changelog ==

= 4.0.0 =
Complete rewrite focused on correctness, security and measurable results.

Security
* Removed the unauthenticated `admin/admin-ajax.php` and `admin/build-cache.php` endpoints.
* Removed the `?is_minify=1` behaviour that logged the current user out and back in on any visitor’s request.
* Cached pages are sent with `readfile()` instead of being executed with `include`.
* Every admin action checks the `manage_options` capability and a nonce; reports only request pages on the site’s own host.

Correctness
* The 3.x page cache could serve one URL’s page for another (for example the shop page at the blog address), answered POST requests and query-string URLs from cache, and could store per-visitor content. The new page cache is opt-in and follows strict rules.
* The 3.x minifier deleted lines starting with `//` (breaking inline scripts and code samples) and collapsed whitespace inside `pre` and `textarea`. It is replaced by a spec-based HTML tokenizer.

New
* CSS and JavaScript file minification with a versioned file cache.
* Optional page cache, defer, delay, async CSS with critical CSS, font-display, preconnect/preload.
* Measured reports with history, a new admin interface, per-page controls, exclusions, test mode, safe mode, pause, import/export, diagnostics and log.
* Compatibility detection and cache purging for WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, Autoptimize, Cache Enabler, Breeze, SiteGround Optimizer, Hummingbird, Perfmatters, FlyingPress, Jetpack Boost and Nginx Helper.

= 3.3 =
* FIX: Vulnerability issue fixed in API

= 3.2 =
* FIX: Issue while generating cache when user loggedin.

= 3.1 =
* Security Update

= 3.0 =
* Tested up to: 6.4.3
* Improved performance, optimised page delivery.

= 2.2 =
* New: No need to create cache manually. Auto create cache when updating post / pages.

= 2.1 =
* Upgrade: More accurate cache support.

= 2.0 =
* New: Cache support

= 1.0 =
* New: Live Page Speed view, Page Speed Report and post type filters.

= 0.1 =
* Initial Public Release

== Upgrade Notice ==

= 4.0.0 =
Security and correctness rewrite. Old cached pages are deleted on upgrade, and the page cache is now a separate setting that is off by default: enable it under Settings → Page cache if you relied on it. Run a report after upgrading to compare.
