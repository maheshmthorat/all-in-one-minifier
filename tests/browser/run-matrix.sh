#!/usr/bin/env bash
# Run the browser regression test under several plugin configurations.
# Usage: bash tests/browser/run-matrix.sh <devtools-dir-with-node_modules> <screenshot-dir> [config...]
# Configs: recommended advanced async-css delay page-cache
set -uo pipefail
DEV="$1"; OUT="$2"; shift 2
CONFIGS=("$@"); [ ${#CONFIGS[@]} -eq 0 ] && CONFIGS=(recommended advanced async-css delay)
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
COMPOSE="docker compose -f $ROOT/tests/docker/docker-compose.yml"
wpcli() { $COMPOSE run --rm --no-deps -T cli "$@" 2>/dev/null; }

set_config() {
	local php
	case "$1" in
		recommended) php='$s = AIOM_Settings::DEFAULTS;' ;;
		advanced)    php='$s = AIOM_Settings::DEFAULTS; $s["js_defer"] = true; $s["html_inline_js"] = true; $s["html_block_whitespace"] = true; $s["css_font_display"] = "swap"; $s["css_google_fonts_swap"] = true;' ;;
		async-css)   php='$s = AIOM_Settings::DEFAULTS; $s["css_async"] = true; $s["css_critical"] = "body{margin:0}.wp-site-blocks{padding-top:0}";' ;;
		delay)       php='$s = AIOM_Settings::DEFAULTS; $s["js_delay"] = true; $s["js_delay_patterns"] = "wp-emoji\nsourcebuster\norder-attribution"; $s["js_delay_timeout"] = 5;' ;;
		page-cache)  php='$s = AIOM_Settings::DEFAULTS; $s["page_cache"] = true;' ;;
	esac
	wpcli eval "$php AIOM_Settings::save( \$s ); AIOM_Maintenance::clear( 'all' ); echo 'configured';" >/dev/null
}

TOKEN=$(wpcli eval 'echo AIOM_Reports::token();' | tail -1)
status=0
for cfg in "${CONFIGS[@]}"; do
	set_config "$cfg"
	# Warm the asset cache so optimised files exist before measuring.
	for p in / /code-samples/ /blocks/ /blog/ /sample-post-1/ /shop/ /product/sample-product-1/ /contact/ /sample-page/; do
		curl -s -o /dev/null "http://localhost:8090$p?aiom_report=$TOKEN&aiom_mode=on"
	done
	docker run --rm --network host -u "$(id -u):$(id -g)" -e HOME=/tmp -e TOKEN="$TOKEN" -e LABEL="$cfg" -e OUT=/o \
		-e NODE_PATH=/d/node_modules -v "$DEV":/d:ro -v "$ROOT/tests/browser":/t:ro -v "$OUT":/o \
		mcr.microsoft.com/playwright:v1.49.1-noble node /t/check.js || status=1
done
set_config recommended
exit $status
