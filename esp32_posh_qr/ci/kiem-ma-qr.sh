#!/usr/bin/env bash
# Biên dịch và chạy bài test cho ma_qr.h TRÊN MÁY TÍNH (không cần chip, không cần
# arduino-cli). Kèm luôn bước đối chiếu chéo với công cụ sinh mã tao-ma-qr.py:
# máy chủ ký một kiểu mà chip kiểm một kiểu là hỏng cả hệ thống, mà lỗi đó chỉ lộ
# ra lúc khách đứng trước ghế.
#
# Chạy:  bash esp32_posh_qr/ci/kiem-ma-qr.sh
set -euo pipefail
cd "$(dirname "$0")"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "--- biên dịch bài test ---"
g++ -std=c++17 -O1 -Wall -Wextra -Wno-unused-parameter \
    -I test/shim test/kiem_ma_qr.cpp -o "$TMP/kiem" -lcrypto

echo "--- chạy bài test ---"
"$TMP/kiem"

echo
echo "--- đối chiếu chéo: tao-ma-qr.py ký  ->  ma_qr.h kiểm ---"
KHOA="khoa-doi-chieu-cheo-$(date +%s)"
MA="$(python3 tao-ma-qr.py --khoa "$KHOA" --may GHE-07 --phut 20 --han 3600 --ma doichieu1 --im)"
echo "    mã sinh ra: $MA"
KQ="$("$TMP/kiem" "$KHOA" "$MA")"
echo "    chip đọc  : $KQ"
case "$KQ" in
  "MO_GHE may=GHE-07 phut=20 "*"ma=doichieu1") echo "    ✅ khớp" ;;
  *) echo "    ❌ KHÔNG khớp — máy chủ và chip đang hiểu mã khác nhau"; exit 1 ;;
esac

GIO="$(python3 tao-ma-qr.py --khoa "$KHOA" --dat-gio 1786000000 --im)"
KQ="$("$TMP/kiem" "$KHOA" "$GIO")"
[ "$KQ" = "DAT_GIO gio=1786000000" ] || { echo "    ❌ mã đặt giờ không khớp: $KQ"; exit 1; }
echo "    ✅ mã đặt giờ khớp"

echo
echo "✅ TẤT CẢ ĐỀU ĐẠT"
