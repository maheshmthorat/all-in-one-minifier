#!/usr/bin/env python3
"""Measure anonymous TTFB, total response time and size for a list of URLs.

Usage: python3 tests/benchmark/ttfb.py LABEL [runs] [base-url]

Each URL is requested twice to warm caches, then `runs` times; median and p90
time-to-first-byte (curl's time_starttransfer) and the HTML size are printed.
Results are appended as JSON lines to tests/benchmark/results.jsonl.
"""
import json
import os
import statistics
import subprocess
import sys
import time

LABEL = sys.argv[1] if len(sys.argv) > 1 else 'run'
RUNS = int(sys.argv[2]) if len(sys.argv) > 2 else 15
BASE = sys.argv[3] if len(sys.argv) > 3 else 'http://localhost:8090'
PATHS = ['/', '/code-samples/', '/blog/', '/sample-post-1/', '/shop/', '/contact/']


def fetch(url):
    out = subprocess.run(
        ['curl', '-s', '-o', '/dev/null', '-H', 'Accept-Encoding: identity',
         '-w', '%{http_code} %{time_starttransfer} %{size_download} %{time_total}', url],
        capture_output=True, text=True, check=True).stdout.split()
    return int(out[0]), float(out[1]) * 1000, int(out[2]), float(out[3]) * 1000


rows = []
for path in PATHS:
    url = BASE + path
    for _ in range(2):
        fetch(url)
    samples, totals, size, code = [], [], 0, 0
    for _ in range(RUNS):
        code, ms, size, total = fetch(url)
        samples.append(ms)
        totals.append(total)
        time.sleep(0.05)
    samples.sort()
    totals.sort()
    row = {
        'label': LABEL, 'path': path, 'status': code, 'bytes': size,
        'median_ms': round(statistics.median(samples), 1),
        'p90_ms': round(samples[int(len(samples) * 0.9) - 1], 1),
        'total_median_ms': round(statistics.median(totals), 1),
        'runs': RUNS, 'time': int(time.time()),
    }
    rows.append(row)
    print(f"{LABEL:<22} {path:<18} {code} {size:>8} B  TTFB median {row['median_ms']:>6.1f} ms  p90 {row['p90_ms']:>6.1f} ms  total median {row['total_median_ms']:>6.1f} ms")

with open(os.path.join(os.path.dirname(__file__), 'results.jsonl'), 'a') as fh:
    for row in rows:
        fh.write(json.dumps(row) + '\n')
