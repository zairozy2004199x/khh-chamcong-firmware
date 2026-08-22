#!/usr/bin/env bash
# Kiểm hàm KIỂM DẠNG LINK của firmware bằng g++ — không cần Arduino, không cần máy thật.
#
# Vì sao cần: từ 22/08/2026 máy chỉ còn MỘT đường (website), nên ô link đó là chỗ duy nhất. Dán
# nhầm link Apps Script hay link Firebase cũ vào là máy KHÔNG đẩy được lượt nào — không còn
# đường thứ hai đỡ cho như hồi chạy song song. Chặn ở hàm kiểm dạng, và hàm đó phải có phép thử.
#
# Trích hàm TỪ CHÍNH .ino chứ không chép lại — chép là sớm muộn lệch với firmware đang chạy.
#
# Chạy: bash tools/test/fw/kiem-link.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
INO=esp32_hik_chamcong_full/esp32_hik_chamcong_full.ino
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

python3 - "$INO" "$TMP/trich.inc" <<'PY'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()
out = []
for ten in ['wpUrlHopLe']:
    m = re.search(r'\nbool ' + ten + r'\(const String& u\)\{.*?\n\}\n', s, re.S)
    if not m:
        sys.exit('KHÔNG tìm thấy hàm %s trong .ino — đổi tên hàm thì sửa luôn phép thử này.' % ten)
    out.append(m.group(0))
io.open(sys.argv[2], 'w', encoding='utf-8').write(''.join(out))
PY

cp tools/test/fw/kiem-link.cpp "$TMP/t.cpp"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"
