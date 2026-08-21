#!/usr/bin/env bash
# Kiểm ba hàm KIỂM DẠNG LINK của firmware bằng g++ — không cần Arduino, không cần máy thật.
#
# Vì sao cần: từ khi máy đẩy CẢ HAI đường (Apps Script + WordPress) thì có hai ô link, và cái
# lỗi tốn kém nhất là DÁN LẪN Ô. Dán link /exec vào ô WordPress thì máy đẩy hai lần vào cùng
# Apps Script và KHÔNG lượt nào tới MySQL — mà log trông như thành công. Dán ngược lại thì mất
# chấm công thật. Hai chuyện đó phải chặn ở hàm kiểm dạng, và hàm đó phải có phép thử.
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
for ten in ['execUrlHopLe', 'wpUrlHopLe', 'fbHostHopLe']:
    m = re.search(r'\nbool ' + ten + r'\(const String& u\)\{.*?\n\}\n', s, re.S)
    if not m:
        sys.exit('KHÔNG tìm thấy hàm %s trong .ino — đổi tên hàm thì sửa luôn phép thử này.' % ten)
    out.append(m.group(0))
io.open(sys.argv[2], 'w', encoding='utf-8').write(''.join(out))
PY

cp tools/test/fw/kiem-link.cpp "$TMP/t.cpp"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"
