#!/usr/bin/env bash
# Chạy bộ thử plugin Dò vé rẻ: dựng máy chủ giả (PHP thật của plugin) rồi mở trình duyệt thật.
set -euo pipefail
cd "$(dirname "$0")"
[ -d node_modules ] || npm i --silent playwright
php -S 127.0.0.1:8098 mockdvr.php >/dev/null 2>&1 & echo $! > dvr.pid
trap 'kill $(cat dvr.pid) 2>/dev/null || true; rm -f dvr.pid dvr-orders.json' EXIT
sleep 1
node test-dvr.mjs
