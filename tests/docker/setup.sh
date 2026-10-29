#!/usr/bin/env bash
# Install WordPress with realistic content for testing All in one Minifier.
# Usage: bash tests/docker/setup.sh   (after "docker compose up -d")
set -euo pipefail
cd "$(dirname "$0")"

wp() { docker compose run --rm --no-deps -T cli "$@"; }

echo "Waiting for WordPress files and database..."
for _ in $(seq 1 60); do
	if wp core version >/dev/null 2>&1 && wp db check >/dev/null 2>&1; then break; fi
	sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
	wp core install --url=http://localhost:8090 --title="AIOM Test Site" \
		--admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
fi
wp rewrite structure '/%postname%/' --hard
wp option update blogdescription 'Local test site for All in one Minifier'

# Popular plugins used for compatibility testing.
wp plugin install woocommerce contact-form-7 --activate
wp theme install astra

# WooCommerce: pages and a few products.
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json || true
wp wc tool run install_pages --user=admin || true
for i in 1 2 3 4; do
	wp wc product create --user=admin --name="Sample Product $i" --type=simple \
		--regular_price="$((i * 10)).00" --description="Description for product $i" --status=publish >/dev/null || true
done
wp option update woocommerce_coming_soon no || true
wp option update woocommerce_store_pages_only no || true

# Content exercising whitespace-sensitive and script-heavy markup.
FORM_ID=$(wp post list --post_type=wpcf7_contact_form --field=ID --posts_per_page=1 || true)

CODE_PAGE=$(cat <<'HTML'
<!-- wp:paragraph --><p>Whitespace-sensitive blocks follow. Text   with   several   spaces.</p><!-- /wp:paragraph -->
<!-- wp:code --><pre class="wp-block-code"><code>function hello() {
    if (a &lt; b) {
        return "  spaced  ";
    }
}</code></pre><!-- /wp:code -->
<!-- wp:preformatted --><pre class="wp-block-preformatted">Column A    Column B
--------    --------
   x            y</pre><!-- /wp:preformatted -->
<!-- wp:html -->
<textarea rows="3" cols="30">  Indented
    textarea   content</textarea>
<p>Inline <code>a  =  b</code> and <span>one</span> <span>two</span></p>
<script>
// Inline script: must keep working after optimisation.
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('inline-check');
    if (el) { el.textContent = 'inline-js-ok'; }
});
</script>
<p id="inline-check">inline-js-pending</p>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Code   samples"
}
</script>
<svg width="120" height="30" viewBox="0 0 120 30"><text x="0" y="20">  SVG   text  </text></svg>
<!--[if IE]><p>IE only</p><![endif]-->
<!-- /wp:html -->
HTML
)

BLOCKS_PAGE=$(cat <<'HTML'
<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading --><h2 class="wp-block-heading">Column one</h2><!-- /wp:heading --><!-- wp:paragraph --><p>First column text.</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/">Visit shop</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:column --></div><!-- /wp:columns -->
<!-- wp:details --><details class="wp-block-details"><summary>Toggle details</summary><!-- wp:paragraph --><p>Hidden content.</p><!-- /wp:paragraph --></details><!-- /wp:details -->
<!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Plan</th><th>Price</th></tr></thead><tbody><tr><td>Basic</td><td>$10</td></tr><tr><td>Pro</td><td>$20</td></tr></tbody></table></figure><!-- /wp:table -->
<!-- wp:search {"label":"Search","buttonText":"Search"} /-->
<!-- wp:latest-posts /-->
HTML
)

upsert_page() {
	local title="$1" slug="$2" content="$3"
	local id
	id=$(wp post list --post_type=page --name="$slug" --field=ID --posts_per_page=1 || true)
	if [ -z "$id" ]; then
		# --user=admin: content is saved with unfiltered_html, so test scripts are kept.
		wp post create --user=admin --post_type=page --post_status=publish --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain
	else
		wp post update "$id" --user=admin --post_content="$content" >/dev/null
		echo "$id"
	fi
}

HOME_ID=$(upsert_page "Home" "home" "$BLOCKS_PAGE")
upsert_page "Code samples" "code-samples" "$CODE_PAGE" >/dev/null
upsert_page "Blocks" "blocks" "$BLOCKS_PAGE" >/dev/null
if [ -n "$FORM_ID" ]; then
	upsert_page "Contact" "contact" "<!-- wp:shortcode -->[contact-form-7 id=\"$FORM_ID\" title=\"Contact form 1\"]<!-- /wp:shortcode -->" >/dev/null
fi
BLOG_ID=$(upsert_page "Blog" "blog" "")
wp option update show_on_front page
wp option update page_on_front "$HOME_ID"
wp option update page_for_posts "$BLOG_ID"

if [ "$(wp post list --post_type=post --format=count)" -lt 6 ]; then
	for i in 1 2 3 4 5; do
		wp post create --post_status=publish --post_title="Sample post $i" \
			--post_content="<!-- wp:paragraph --><p>Paragraph $i with <strong>bold</strong> and <a href=\"/blog/\">a link</a>.</p><!-- /wp:paragraph -->" >/dev/null
	done
	wp comment create --comment_post_ID="$(wp post list --post_type=post --field=ID --posts_per_page=1)" --comment_content="A test comment" --comment_author="Tester" >/dev/null
fi

# Plugin activation is done separately (legacy benchmark first).
echo "Ready: http://localhost:8090  (admin / admin)"
