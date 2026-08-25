#!/usr/bin/env bash
# Bài test cho bộ đọc file .hex của dsPIC — chạy trên máy, g++ thuần, không cần chip.
# Chạy:  bash esp32_nap_pic/ci/kiem-hex.sh
set -euo pipefail
cd "$(dirname "$0")"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
echo "--- biên dịch ---"
g++ -std=c++17 -O1 -Wall -Wextra test/kiem_hex.cpp -o "$TMP/kiem"
echo "--- chạy ---"
"$TMP/kiem"
