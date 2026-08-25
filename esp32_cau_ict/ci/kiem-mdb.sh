#!/usr/bin/env bash
# Bài test giải mã khung MDB 9 bit — chạy trên máy, g++ thuần, không cần chip.
# Chạy:  bash esp32_cau_ict/ci/kiem-mdb.sh
set -euo pipefail
cd "$(dirname "$0")"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
g++ -std=c++17 -O1 -Wall -Wextra test/kiem_mdb.cpp -o "$TMP/kiem"
"$TMP/kiem"
