#!/usr/bin/env bash
# HTTP-level integration tests against the local Docker site: page cache safety rules,
# invalidation, request bypasses and security of every endpoint.
# Usage: bash tests/integration/http-tests.sh   (site at http://localhost:8090, admin/admin)
set -uo pipefail
BASE="${BASE:-http://localhost:8090}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE="docker compose -f $ROOT/tests/docker/docker-compose.yml"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
wpcli() { $COMPOSE run --rm --no-deps -T cli "$@" 2>/dev/null; }
pass=0; fail=0
ok()   { pass=$((pass + 1)); echo "  ok   $1"; }
bad()  { fail=$((fail + 1)); echo "  FAIL $1"; }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected '$3', got '$2')"; fi; }
cache_header() { curl -s -o /dev/null -D - "$@" | tr -d '\r' | awk -F': ' 'tolower($1)=="x-aiom-cache"{print $2}'; }

settings() { wpcli eval "\$s = AIOM_Settings::DEFAULTS; $1 AIOM_Settings::save( \$s ); AIOM_Settings::set_runtime( array( 'safe_mode' => false, 'paused_until' => 0 ) ); AIOM_Page_Cache::purge_all(); echo 'ok';" >/dev/null; }

HOME_STATUS=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")
if [ "$HOME_STATUS" != "200" ]; then echo "Site is not healthy (home page HTTP $HOME_STATUS); aborting."; exit 2; fi

echo "== Page cache: serving rules"
settings '$s["page_cache"] = true;'
P=/sample-post-2/
curl -s -o /dev/null "$BASE$P"
check "anonymous GET is cached and served (second request HIT)" "$(cache_header "$BASE$P")" "HIT"
check "HEAD served from cache" "$(curl -s -I "$BASE$P" | tr -d '\r' | awk -F': ' 'tolower($1)=="x-aiom-cache"{print $2}')" "HIT"
check "tracking parameter (utm_source) uses the cache" "$(cache_header "$BASE$P?utm_source=newsletter&utm_medium=email")" "HIT"
check "any other query string bypasses" "$(cache_header "$BASE$P?replytocom=1")" ""
check "logged-in cookie bypasses" "$(cache_header -H 'Cookie: wordpress_logged_in_abc=x' "$BASE$P")" ""
check "commenter cookie bypasses" "$(cache_header -H 'Cookie: comment_author_abc=Bob' "$BASE$P")" ""
check "WooCommerce cart cookie bypasses" "$(cache_header -H 'Cookie: woocommerce_items_in_cart=1' "$BASE$P")" ""
check "unrelated cookie still uses cache" "$(cache_header -H 'Cookie: _ga=GA1.2.3' "$BASE$P")" "HIT"
check "POST is never served from cache" "$(cache_header -X POST -d 'a=1' "$BASE$P")" ""
check "foreign Host header bypasses" "$(cache_header -H 'Host: evil.example' "$BASE$P")" ""
check "mobile user agent gets its own variant (first request MISS)" "$(cache_header -A 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile' "$BASE$P")" ""
check "mobile variant then cached" "$(cache_header -A 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile' "$BASE$P")" "HIT"

echo "== Page cache: never stores personalised or non-200 pages"
for u in /cart/ /checkout/ /my-account/ "/?s=sample" /does-not-exist/; do
	curl -s -o /dev/null "$BASE$u"
	check "not cached: $u" "$(cache_header "$BASE$u")" ""
done
check "logged-in visits are not stored" "$(curl -s -o /dev/null -H 'Cookie: wordpress_logged_in_x=y' "$BASE/sample-post-3/"; cache_header "$BASE/sample-post-3/")" ""

echo "== Page cache: cached files are never executed"
PHP_ID=$(wpcli post create --user=admin --post_status=publish --post_title="PHP literal" --post_content='<!-- wp:html --><pre>&lt;?php echo "never"; ?&gt;</pre><code><?php echo "EXECUTED-" . "PHP"; ?></code><!-- /wp:html -->' --porcelain | tail -1)
PURL=$(wpcli post url "$PHP_ID" | tail -1)
curl -s -o /dev/null "$PURL"; BODY=$(curl -s "$PURL")
check "served from cache" "$(cache_header "$PURL")" "HIT"
check "PHP in cached HTML is not executed" "$(echo "$BODY" | grep -c 'EXECUTED-PHP')" "0"
wpcli post delete "$PHP_ID" --force >/dev/null
check "direct web access to cached page files is denied" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-content/cache/aiom/pages/")" "403"

echo "== Page cache: invalidation"
curl -s -o /dev/null "$BASE$P"; curl -s -o /dev/null "$BASE/"
POST_ID=$(wpcli post list --name=sample-post-2 --field=ID --post_type=post | tail -1)
wpcli post update "$POST_ID" --post_content='<!-- wp:paragraph --><p>Updated content marker-7781.</p><!-- /wp:paragraph -->' >/dev/null
check "updating a post purges it" "$(cache_header "$BASE$P")" ""
check "updated content is served" "$(curl -s "$BASE$P" | grep -c 'marker-7781')" "1"
check "updating a post purges the home page" "$(cache_header "$BASE/")" ""
curl -s -o /dev/null "$BASE$P"; curl -s -o /dev/null "$BASE$P"
CID=$(wpcli comment create --comment_post_ID="$POST_ID" --comment_content="New comment 4412" --comment_approved=1 --porcelain | tail -1)
check "a new approved comment purges the post" "$(curl -s "$BASE$P" | grep -c 'New comment 4412')" "1"
wpcli comment delete "$CID" --force >/dev/null
curl -s -o /dev/null "$BASE$P"
MENU=$(wpcli menu create "Test menu $RANDOM" --porcelain | tail -1)
curl -s -o /dev/null "$BASE$P"; curl -s -o /dev/null "$BASE$P"
wpcli menu item add-custom "$MENU" "Docs" https://example.com/docs >/dev/null
check "site-wide change (menu item) purges everything" "$(cache_header "$BASE$P")" ""
curl -s -o /dev/null "$BASE$P"; curl -s -o /dev/null "$BASE$P"
NAV=$(wpcli post create --post_type=wp_navigation --post_status=publish --post_title="Block nav" --post_content='<!-- wp:navigation-link {"label":"X","url":"/x/"} /-->' --porcelain | tail -1)
check "block theme navigation change purges everything" "$(cache_header "$BASE$P")" ""
wpcli post delete "$NAV" --force >/dev/null; wpcli menu delete "$MENU" >/dev/null

echo "== Global switches"
curl -s -o /dev/null "$BASE$P"; curl -s -o /dev/null "$BASE$P"
wpcli eval 'AIOM_Settings::set_runtime( array( "safe_mode" => true ) );' >/dev/null
check "safe mode stops serving cached pages" "$(cache_header "$BASE$P")" ""
O=$(curl -s "$BASE/code-samples/"); R=$(curl -s "$BASE/code-samples/?aiom_report=bogus&aiom_mode=off")
check "safe mode serves original HTML (comments kept)" "$(echo "$O" | grep -c '<!-- /wp:html -->\|wp-block-code')" "$(echo "$R" | grep -c '<!-- /wp:html -->\|wp-block-code')"
wpcli eval 'AIOM_Settings::set_runtime( array( "safe_mode" => false, "paused_until" => time() + 3600 ) );' >/dev/null
check "pause stops serving cached pages" "$(cache_header "$BASE$P")" ""
wpcli eval 'AIOM_Settings::set_runtime( array( "paused_until" => 0 ) );' >/dev/null
settings '$s["page_cache"] = true; $s["test_mode"] = true;'
curl -s -o /dev/null "$BASE$P"
check "test mode disables the page cache" "$(cache_header "$BASE$P")" ""
A=$(curl -s "$BASE/code-samples/" | wc -c); settings ''; Bz=$(curl -s "$BASE/code-samples/" | wc -c)
if [ "$A" -gt "$Bz" ]; then ok "test mode: visitors get unoptimised HTML ($A B vs $Bz B optimised)"; else bad "test mode output size ($A vs $Bz)"; fi

echo "== Report token"
N=$(curl -s "$BASE/code-samples/?aiom_report=bogus-token-123&aiom_mode=off" | wc -c)
check "a forged report token cannot switch optimisation off" "$N" "$Bz"

echo "== Endpoints and permissions"
check "removed 3.x endpoint admin/admin-ajax.php is gone" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-content/plugins/all-in-one-minifier/admin/admin-ajax.php?post_id=1")" "404"
check "removed 3.x endpoint admin/build-cache.php is gone" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/wp-content/plugins/all-in-one-minifier/admin/build-cache.php")" "404"
check "plugin class files print nothing when requested directly" "$(curl -s "$BASE/wp-content/plugins/all-in-one-minifier/includes/class-aiom-settings.php" | wc -c)" "0"
curl -s -o /dev/null "$BASE/"
FILES_BEFORE=$($COMPOSE exec -T wp sh -c 'find /var/www/html/wp-content/cache/aiom/assets -name "*.css" | wc -l')
check "anonymous admin-post clear is rejected" "$(curl -s -o /dev/null -w '%{http_code}' -d 'action=aiom_clear&scope=all' "$BASE/wp-admin/admin-post.php")" "400"
check "anonymous AJAX report is rejected" "$(curl -s -o /dev/null -w '%{http_code}' -d 'action=aiom_run_report&url=/' "$BASE/wp-admin/admin-ajax.php")" "400"
wpcli user create subscriber1 sub1@example.com --role=subscriber --user_pass=sub1pass >/dev/null 2>&1 || true
J="$TMP/sub.txt"
curl -s -c "$J" -b "$J" -o /dev/null "$BASE/wp-login.php"
curl -s -c "$J" -b "$J" -o /dev/null -d 'log=subscriber1&pwd=sub1pass&wp-submit=Log+In&testcookie=1' "$BASE/wp-login.php"
check "subscriber cannot clear the cache" "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' -d 'action=aiom_clear&scope=all&_wpnonce=x' "$BASE/wp-admin/admin-post.php")" "403"
check "subscriber cannot open plugin screens" "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$BASE/wp-admin/admin.php?page=aiom-settings")" "403"
check "subscriber cannot run reports" "$(curl -s -b "$J" -w '%{http_code}' -o /dev/null -d 'action=aiom_run_report&url=/&nonce=x' "$BASE/wp-admin/admin-ajax.php")" "403"
J2="$TMP/adm.txt"
curl -s -c "$J2" -b "$J2" -o /dev/null "$BASE/wp-login.php"
curl -s -c "$J2" -b "$J2" -o /dev/null -d 'log=admin&pwd=admin&wp-submit=Log+In&testcookie=1' "$BASE/wp-login.php"
check "admin without a valid nonce is rejected" "$(curl -s -b "$J2" -o /dev/null -w '%{http_code}' -d 'action=aiom_clear&scope=all&_wpnonce=bad' "$BASE/wp-admin/admin-post.php")" "403"
FILES_AFTER=$($COMPOSE exec -T wp sh -c 'find /var/www/html/wp-content/cache/aiom/assets -name "*.css" | wc -l')
check "rejected requests left the cache intact" "$FILES_AFTER" "$FILES_BEFORE"
check "reports refuse other hosts (no SSRF)" "$(wpcli eval '$r = AIOM_Reports::run( "http://example.com/" ); echo is_wp_error( $r ) ? $r->get_error_code() : "ran";' | tail -1)" "aiom_url"
wpcli user delete subscriber1 --yes >/dev/null 2>&1 || true

settings ''
echo
echo "$pass passed, $fail failed"
exit $(( fail > 0 ))
