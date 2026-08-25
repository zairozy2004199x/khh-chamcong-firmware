#!/usr/bin/env bash
# Kiểm một file .hex bằng ĐÚNG bộ đọc mà firmware dùng.
# Chạy:  bash esp32_nap_pic/ci/xem-hex.sh <file.hex>
set -euo pipefail
cd "$(dirname "$0")"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
g++ -std=c++17 -O1 -Wall -Wextra test/xem_hex.cpp -o "$TMP/xem"
"$TMP/xem" "$1"
