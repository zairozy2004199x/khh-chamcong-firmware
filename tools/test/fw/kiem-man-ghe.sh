#!/usr/bin/env bash
# Kiểm bố cục màn chọn gói: không chữ nào tràn khỏi ô. Xem chú thích trong ve-man-ghe.cpp.
set -euo pipefail
d="$(cd "$(dirname "$0")" && pwd)"
goc="$(cd "$d/../../.." && pwd)"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
python3 - "$goc/esp32_ghe_massage/esp32_ghe_massage.ino" "$tmp/trich.inc" <<'PY'
import sys, io
ino, ra = sys.argv[1], sys.argv[2]
src = io.open(ino, encoding='utf-8').read()
def boc(chuky):
    i = src.index(chuky); d = 0; k = src.index('{', i)
    while k < len(src):
        if src[k] == '{': d += 1
        elif src[k] == '}':
            d -= 1
            if d == 0: return src[i:k+1]
        k += 1
    raise SystemExit('khong boc duoc ' + chuky)
def dinh(ten):
    for dong in src.split('\n'):
        if dong.startswith('#define ' + ten + ' ') or dong.startswith('#define ' + ten + '  '): return dong
    raise SystemExit('khong thay #define ' + ten)
mau = [dinh(t) for t in ('COL_BG','COL_KHUNG','COL_VANG','COL_VANG2','COL_KEM','COL_CHU','COL_MO',
                         'COL_VIP','COL_VIP_MO','COL_MO_KEM','COL_THE','COL_VIEN','COL_VIEN2',
                         'COL_VIEN3','COL_SO','COL_PHU')]
btn = [d for d in src.split('\n') if d.startswith('Btn PKG_BTN[PKG_MAX]')][0]
io.open(ra,'w',encoding='utf-8').write('\n'.join(mau)+'\n'+btn+'\n\n' + '\n\n'.join([
    # 🔴 THỨ TỰ LÀ THỨ TỰ BIÊN DỊCH: hàm nào được gọi phải nằm trước chỗ gọi nó.
    #    `capSoTien()` gọi `tienVN()`, `veChu()` gọi `datFont()` — xếp sai là g++ chối ngay.
    boc('String tienVN(long v){'),
    boc('int phutGoi(int i){'),
    boc('enum CapChu {') + ';',          # boc dừng ở '}', enum còn thiếu dấu chấm phẩy
    boc('static void datFont(int cap){'),
    boc('static int rongChu(const String& s, int cap){'),
    boc('static int caoChu(int cap){'),
    boc('static void veChu(const String& s, int x, int y, int cap, uint16_t mau, uint16_t nen, bool dam){'),
    boc('static int capSoTien(){'),
    boc('void veTheGoi(int i){'),
    boc('void drawIdle(){'),
]) + '\n')
PY
cp "$d/ve-man-ghe.cpp" "$tmp/main.cpp"
g++ -std=c++17 -O1 -I"$tmp" -o "$tmp/ve" "$tmp/main.cpp"
"$tmp/ve" --kiem
