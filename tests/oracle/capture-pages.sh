#!/usr/bin/env bash
# Capture original and optimised HTML of live pages for compare-html.js.
# Uses the plugin's report token, so captures bypass the page cache and run the
# same code path as an anonymous visitor.
# Usage: bash tests/oracle/capture-pages.sh <out-dir> [base-url] [paths...]
set -euo pipefail
OUT="$1"; BASE="${2:-http://localhost:8090}"; shift $(( $# > 1 ? 2 : 1 ))
PATHS=("$@")
if [ ${#PATHS[@]} -eq 0 ]; then
	PATHS=(/ /code-samples/ /blocks/ /blog/ /sample-post-1/ /shop/ /contact/ /cart/ /my-account/ /sample-page/ /?s=sample /product/sample-product-1/ /category/uncategorized/ /does-not-exist/)
fi
cd "$(dirname "$0")/../docker"
TOKEN=$(docker compose run --rm --no-deps -T cli eval 'echo AIOM_Reports::token();' 2>/dev/null | tail -1)
mkdir -p "$OUT"
i=0
for p in "${PATHS[@]}"; do
	sep='?'; [[ "$p" == *\?* ]] && sep='&'
	curl -s "$BASE$p${sep}aiom_report=$TOKEN&aiom_mode=off" -o "$OUT/$i.orig.html"
	curl -s "$BASE$p${sep}aiom_report=$TOKEN&aiom_mode=on" -o "$OUT/$i.min.html"
	echo "$p" > "$OUT/$i.name"
	printf '%-28s original %7d B  optimised %7d B\n' "$p" "$(wc -c < "$OUT/$i.orig.html")" "$(wc -c < "$OUT/$i.min.html")"
	i=$((i + 1))
done
