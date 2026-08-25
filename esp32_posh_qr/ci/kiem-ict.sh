#!/usr/bin/env bash
# Biên dịch và chạy bài test cho ict_ghe.h TRÊN MÁY TÍNH, dùng cổng UART giả.
# Chốt cứng từng byte của khung lệnh gửi sang bo ghế.
#
# Chạy:  bash esp32_posh_qr/ci/kiem-ict.sh
set -euo pipefail
cd "$(dirname "$0")"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
echo "--- biên dịch ---"
g++ -std=c++17 -O1 -Wall -Wextra -Wno-unused-parameter \
    -I test/shim test/kiem_ict.cpp -o "$TMP/kiem"
echo "--- chạy ---"
"$TMP/kiem"
