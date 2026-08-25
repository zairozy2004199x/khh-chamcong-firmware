#!/usr/bin/env bash
# BIÊN DỊCH KIỂM sketch POSH QR ngay trên máy tính, không cần arduino-cli, không cần chip.
#
# VÌ SAO CÓ FILE NÀY: workflow của repo chỉ biên dịch khi push vào nhánh main, nên sửa
# code trên nhánh nháp là KHÔNG có ai kiểm hộ. Sai một dấu chấm phẩy mà tới lúc gộp vào
# main mới biết thì đã muộn. Bộ thư viện ESP32 giả trong ci/test/shim/ đủ để g++ soi hết
# cú pháp và kiểu dữ liệu của cả 4 file.
#
# ⚠️ ĐÂY KHÔNG THAY THẾ CI. g++ không kiểm được cỡ file .bin, không kiểm được thư viện
#    thật, và bộ giả có thể dễ tính hơn thư viện thật. Nó chỉ bắt lỗi SỚM.
#
# Chạy:  bash esp32_posh_qr/ci/kiem-bien-dich.sh
set -euo pipefail
cd "$(dirname "$0")/.."
TMP="$(mktemp -d)"
DON_SECRETS=0
trap 'rm -rf "$TMP"; [ "$DON_SECRETS" = 1 ] && rm -f secrets.h; true' EXIT

if [ ! -f secrets.h ]; then
  cp ci/secrets.ci.h secrets.h            # y như CI làm: toàn placeholder
  DON_SECRETS=1
fi

echo "--- biên dịch esp32_posh_qr.ino (thư viện ESP32 giả) ---"
g++ -std=c++17 -fsyntax-only -Wall -Wextra -Wno-unused-parameter -Wno-unused-variable \
    -I ci/test/shim -include ci/test/shim/esp_stub.h -x c++ esp32_posh_qr.ino
echo "✅ sketch biên dịch sạch"

# ⚠️ Bước này sinh ra từ một lỗi thật: bảng lệnh TRO đã liệt kê DAY/TUKIEM/OE/CHIEU
#    nhưng phần xử lý lệnh thì KHÔNG được ghi vào file (một bước sửa file khớp hụt).
#    Biên dịch vẫn xanh, nên lỗi chỉ lộ ra khi có người ngồi trước ghế gõ lệnh và
#    nhận về "khong hieu". Từ nay: in ra lệnh nào thì phải xử lệnh đó.
echo "--- đối chiếu bảng lệnh TRO với danh sách lệnh thật sự xử lý ---"
python3 - <<'PY'
import re, sys
src = open('esp32_posh_qr.ino', encoding='utf-8').read()

tro = src[src.index('void inTro()'):src.index('void inTrangThai()')]
in_ra = set()
for dong in re.findall(r'"\s{2}([^"\\]*)', tro):
    tu = dong.strip().split()
    if tu and re.fullmatch(r'[A-Z]{2,8}', tu[0]):
        in_ra.add(tu[0])

xu_ly = set(re.findall(r'lenh == "([A-Z]+)"', src))

thieu = sorted(in_ra - xu_ly)
thua  = sorted(xu_ly - in_ra)
if thieu:
    print("🔴 in ra bảng lệnh nhưng KHÔNG có chỗ xử lý:", ", ".join(thieu))
if thua:
    print("🔴 có chỗ xử lý nhưng KHÔNG in ra bảng lệnh:", ", ".join(thua))
if thieu or thua:
    sys.exit(1)
print(f"✅ {len(in_ra)} lệnh khớp nhau: " + " ".join(sorted(in_ra)))
PY
