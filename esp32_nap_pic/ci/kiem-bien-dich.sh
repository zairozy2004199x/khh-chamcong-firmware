#!/usr/bin/env bash
# BIÊN DỊCH KIỂM thợ nạp dsPIC trên máy tính, dùng chung bộ thư viện ESP32 giả.
# Chạy:  bash esp32_nap_pic/ci/kiem-bien-dich.sh
set -euo pipefail
cd "$(dirname "$0")/.."
SHIM=../esp32_posh_qr/ci/test/shim
echo "--- biên dịch esp32_nap_pic.ino ---"
g++ -std=c++17 -fsyntax-only -Wall -Wextra \
    -I "$SHIM" -include "$SHIM/esp_stub.h" -x c++ esp32_nap_pic.ino
echo "✅ sketch biên dịch sạch"
echo "--- đối chiếu bảng lệnh TRO với danh sách lệnh thật sự xử lý ---"
python3 - <<'PY'
import re, sys
src = open('esp32_nap_pic.ino', encoding='utf-8').read()
tro = src[src.index('void inTro()'):src.index('void inTrangThai()')]
in_ra = set()
for dong in re.findall(r'"\s{2}([^"\\]*)', tro):
    tu = dong.strip().split()
    if tu and re.fullmatch(r'[A-Z]{2,9}', tu[0]): in_ra.add(tu[0])
xu_ly = set(re.findall(r'lenh == "([A-Z]+)"', src))
thieu, thua = sorted(in_ra - xu_ly), sorted(xu_ly - in_ra)
if thieu: print("🔴 in ra bảng lệnh nhưng KHÔNG có chỗ xử lý:", ", ".join(thieu))
if thua:  print("🔴 có chỗ xử lý nhưng KHÔNG in ra bảng lệnh:", ", ".join(thua))
if thieu or thua: sys.exit(1)
print(f"✅ {len(in_ra)} lệnh khớp nhau: " + " ".join(sorted(in_ra)))
PY
