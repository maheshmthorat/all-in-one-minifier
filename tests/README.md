# Testing All in one Minifier

Nothing in this directory is needed at runtime. Exclude `tests/` when building the
release package (for SVN: `svn propset svn:ignore` or copy without it).

All PHP is run inside Docker containers; nothing needs to be installed on the host
except Docker, Node.js (for the parser-based oracles) and curl.

## 1. Unit tests (engines, no WordPress)

```bash
docker run --rm -v "$PWD":/p -w /p php:7.4-cli php tests/run.php     # any of 7.4 … 8.5
```

`tests/run.php [filter]` runs every `tests/unit/test-*.php` method named `test_*`.
Notices and deprecations are turned into failures.

## 2. Differential tests against independent parsers

The engines are run over large real-world corpora and the output is checked with
independent parsers: acorn (JavaScript AST equality), css-tree (CSS) and parse5 (HTML DOM).

```bash
npm install --prefix /tmp/aiom-dev acorn@8 css-tree@3 parse5@7
export NODE_PATH=/tmp/aiom-dev/node_modules
find /path/to/wordpress /path/to/plugins -name '*.js' ! -name '*.min.js' > /tmp/js.txt
docker run --rm -v "$PWD":/p -v /path:/path -v /tmp:/tmp -w /p php:8.3-cli \
  php -d memory_limit=2G tests/oracle/minify-corpus.php js /tmp/js.txt /tmp/out-js
node tests/oracle/compare-js.js /tmp/out-js          # same for css / compare-css.js
node tests/oracle/compare-html.js <dir>              # N.orig.html / N.min.html pairs
bash tests/oracle/capture-pages.sh /tmp/out-html     # live pages from the Docker site
```

`tests/oracle/fixtures/tricky.html` collects adversarial HTML (script escape states,
stray quotes in unquoted attributes, implied `<body>`, conditional comments, SVG/MathML…).

## 3. Local WordPress site

```bash
docker compose -f tests/docker/docker-compose.yml up -d
bash tests/docker/setup.sh            # WordPress, WooCommerce, Contact Form 7, sample content
# http://localhost:8090  —  admin / admin
WP_PHP=php7.4 docker compose -f tests/docker/docker-compose.yml up -d wp   # other PHP versions
```

`bash tests/docker/multisite-test.sh` builds a throwaway subdirectory network on port 8095,
network-activates the plugin, checks per-site isolation, the page cache and uninstall, then removes itself.

The plugin folder is bind-mounted, so edits apply immediately. `aiom-legacy-3.3` is the
released 3.3 code, mounted read-only for before/after benchmarks (keep it inactive).

## 4. Integration tests

```bash
docker compose -f tests/docker/docker-compose.yml run --rm --no-deps cli \
  eval-file wp-content/plugins/all-in-one-minifier/tests/integration/wp-tests.php   # inside WordPress
bash tests/integration/http-tests.sh   # page cache rules, invalidation, permissions, endpoints
```

## 5. Browser tests (Playwright)

```bash
npm install --prefix /tmp/aiom-dev playwright@1.49.1 pngjs@7 pixelmatch@5
bash tests/browser/run-matrix.sh /tmp/aiom-dev /tmp/shots recommended advanced async-css delay
docker run --rm --network host -e OUT=/o -e NODE_PATH=/d/node_modules -v /tmp/aiom-dev:/d \
  -v "$PWD/tests/browser":/t -v /tmp/shots:/o mcr.microsoft.com/playwright:v1.49.1-noble node /t/admin.js
```

`check.js` renders each page with and without optimisation, compares full-page screenshots
pixel by pixel, collects JavaScript errors and failed requests, then submits the contact
form, adds a product to the cart, searches and toggles a details block.

## 6. Benchmarks

```bash
python3 tests/benchmark/ttfb.py LABEL 25      # median/p90 TTFB and total time, anonymous requests
```

Results are appended to `tests/benchmark/results.jsonl`.
