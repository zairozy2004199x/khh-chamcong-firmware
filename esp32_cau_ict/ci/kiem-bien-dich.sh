#!/usr/bin/env bash
# BIÊN DỊCH KIỂM firmware cầu nghe lén ngay trên máy tính, không cần arduino-cli.
# Dùng chung bộ thư viện ESP32 giả với esp32_posh_qr (một bộ giả cho cả repo).
#
# ⚠️ KHÔNG THAY THẾ CI — g++ chỉ soi cú pháp và kiểu, không kiểm cỡ .bin hay
#    thư viện thật. Nó chỉ bắt lỗi SỚM, vì workflow của repo chỉ chạy khi push main.
#
# Chạy:  bash esp32_cau_ict/ci/kiem-bien-dich.sh
set -euo pipefail
cd "$(dirname "$0")/.."
SHIM=../esp32_posh_qr/ci/test/shim

echo "--- biên dịch esp32_cau_ict.ino (thư viện ESP32 giả) ---"
g++ -std=c++17 -fsyntax-only -Wall -Wextra \
    -I "$SHIM" -include "$SHIM/esp_stub.h" -x c++ esp32_cau_ict.ino
echo "✅ sketch biên dịch sạch"

echo "--- đối chiếu bảng lệnh TRO với danh sách lệnh thật sự xử lý ---"
python3 - <<'PY'
import re, sys
src = open('esp32_cau_ict.ino', encoding='utf-8').read()
tro = src[src.index('void inTro()'):src.index('void inTrangThai()')]
in_ra = set()
for dong in re.findall(r'"\s{2}([^"\\]*)', tro):
    tu = dong.strip().split()
    if tu and re.fullmatch(r'[A-Z]{2,9}', tu[0]):
        in_ra.add(tu[0])
xu_ly = set(re.findall(r'lenh == "([A-Z]+)"', src))
thieu, thua = sorted(in_ra - xu_ly), sorted(xu_ly - in_ra)
if thieu: print("🔴 in ra bảng lệnh nhưng KHÔNG có chỗ xử lý:", ", ".join(thieu))
if thua:  print("🔴 có chỗ xử lý nhưng KHÔNG in ra bảng lệnh:", ", ".join(thua))
if thieu or thua: sys.exit(1)
print(f"✅ {len(in_ra)} lệnh khớp nhau: " + " ".join(sorted(in_ra)))
PY
