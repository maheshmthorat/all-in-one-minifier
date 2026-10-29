#!/usr/bin/env bash
# Throwaway subdirectory multisite on http://localhost:8095: network-activates the plugin
# and checks per-site settings/caches, the early page-cache path (network plugins load
# before vars.php and pluggable functions), the mobile variant and the uninstaller.
# Usage: bash tests/docker/multisite-test.sh     (removes its containers afterwards)
set -uo pipefail
P="$(cd "$(dirname "$0")/../.." && pwd)"
NET=aiom-ms; DB=aiom-ms-db; WEB=aiom-ms-wp; VOL=aiom-ms-wp
cleanup() { docker rm -f $WEB $DB >/dev/null 2>&1; docker volume rm $VOL >/dev/null 2>&1; docker network rm $NET >/dev/null 2>&1; }
cleanup; trap cleanup EXIT
docker network create $NET >/dev/null
docker run -d --name $DB --network $NET -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp -e MARIADB_ROOT_PASSWORD=root mariadb:11.4 >/dev/null
sleep 12
docker run -d --name $WEB --network $NET -p 127.0.0.1:8095:80 -e WORDPRESS_DB_HOST=$DB -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp -e WORDPRESS_DEBUG=1 \
	-v $VOL:/var/www/html -v "$P":/var/www/html/wp-content/plugins/all-in-one-minifier:ro wordpress:php8.3-apache \
	bash -c "echo 'Listen 8095' >> /etc/apache2/ports.conf; exec docker-entrypoint.sh apache2-foreground" >/dev/null
sleep 8; curl -s -o /dev/null http://localhost:8095/
wp() { docker run --rm --network $NET -u 33:33 --entrypoint wp -e WORDPRESS_DB_HOST=$DB -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp \
	-v $VOL:/var/www/html -v "$P":/var/www/html/wp-content/plugins/all-in-one-minifier:ro wordpress:cli-php8.3 "$@" 2>/dev/null; }
wp core multisite-install --url=http://localhost:8095 --title=MS --admin_user=admin --admin_password=admin --admin_email=a@example.com --skip-email --skip-config >/dev/null
docker exec $WEB sh -c "sed -i \"s#/\\* That's all, stop editing#define('MULTISITE', true);\ndefine('SUBDOMAIN_INSTALL', false);\ndefine('DOMAIN_CURRENT_SITE', 'localhost:8095');\ndefine('PATH_CURRENT_SITE', '/');\ndefine('SITE_ID_CURRENT_SITE', 1);\ndefine('BLOG_ID_CURRENT_SITE', 1);\ndefine('WP_DEBUG_LOG', true);\ndefine('WP_DEBUG_DISPLAY', false);\n/* That's all, stop editing#\" wp-config.php
printf '%s\n' 'RewriteEngine On' 'RewriteBase /' 'RewriteRule ^index\.php\$ - [L]' 'RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin\$ \$1wp-admin/ [R=301,L]' 'RewriteCond %{REQUEST_FILENAME} -f [OR]' 'RewriteCond %{REQUEST_FILENAME} -d' 'RewriteRule ^ - [L]' 'RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) \$2 [L]' 'RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)\$ \$2 [L]' 'RewriteRule . index.php [L]' > .htaccess"
wp site create --slug=second >/dev/null
wp rewrite structure '/%postname%/' >/dev/null; wp rewrite structure '/%postname%/' --url=http://localhost:8095/second/ >/dev/null
wp plugin activate all-in-one-minifier --network >/dev/null
wp eval '$s = AIOM_Settings::all(); $s["page_cache"] = true; AIOM_Settings::save( $s );' --url=http://localhost:8095/second/

pass=0; fail=0
check() { if [ "$2" = "$3" ]; then pass=$((pass+1)); echo "  ok   $1"; else fail=$((fail+1)); echo "  FAIL $1 (expected '$3', got '$2')"; fi; }
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
hdr() { curl -s -o /dev/null -D - "$@" | tr -d '\r' | awk -F': ' 'tolower($1)=="x-aiom-cache"{print $2}'; }
for u in /second/ /second/hello-world/ /; do for _ in 1 2; do curl -s -o /dev/null "http://localhost:8095$u"; done; done
check "site 2 home: HTTP 200" "$(status http://localhost:8095/second/)" "200"
check "site 2 page cache serves" "$(hdr http://localhost:8095/second/)" "HIT"
check "site 1 keeps its own settings (no page cache)" "$(hdr http://localhost:8095/)" ""
check "site 1 home: HTTP 200" "$(status http://localhost:8095/)" "200"
curl -s -o /dev/null -A 'Mozilla/5.0 (iPhone) Mobile' http://localhost:8095/second/
check "mobile variant served" "$(hdr -A 'Mozilla/5.0 (iPhone) Mobile' http://localhost:8095/second/)" "HIT"
check "per-site cache directories" "$(docker exec $WEB sh -c 'ls /var/www/html/wp-content/cache/aiom | tr "\n" " "')" "1 2 "
wp plugin deactivate all-in-one-minifier --network >/dev/null
wp eval 'define( "WP_UNINSTALL_PLUGIN", "all-in-one-minifier/all-in-one-minifier.php" ); include WP_PLUGIN_DIR . "/all-in-one-minifier/uninstall.php";'
check "uninstall: site 2 options removed" "$(wp option list --search='aiom_*' --format=count --url=http://localhost:8095/second/)" "0"
check "uninstall: report tables removed" "$(wp db query "SHOW TABLES LIKE '%aiom_reports'" --skip-column-names | wc -l)" "0"
check "no PHP errors, warnings or notices" "$(docker exec $WEB sh -c 'grep -c . /var/www/html/wp-content/debug.log 2>/dev/null || echo 0')" "0"
echo; echo "$pass passed, $fail failed"; exit $(( fail > 0 ))
