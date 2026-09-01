#!/usr/bin/env bash
# ==============================================================================================
# DỰNG MÀN CHỌN GÓI CỦA GHẾ RA ẢNH — TRÍCH HÀM VẼ TỪ CHÍNH .ino, KHÔNG CHÉP LẠI.
#
# 🔴 VÌ SAO PHẢI CÓ. Màn ghế chỉ nhìn được sau khi nạp firmware lên con ESP32 đang chạy tiền của
#    khách — mà đó đúng là thứ không được làm để thử giao diện. Trước bản này, mọi lượt sửa bố
#    cục đều là đoán: đọc mã, tưởng tượng ra màn, rồi anh Thắng chụp ảnh ghế thật báo về. Ba lỗi
#    đã sinh ra đúng theo đường ấy — hai chữ đè lên nhau ở dải tiêu đề, chữ tràn ra ngoài ô mà
#    lượt vẽ sau không xoá được, và thẻ VVIP chữ vàng trên nền đồng không đọc nổi.
#
# 🔴 TRÍCH TỪ .ino, KHÔNG VIẾT LẠI. Cùng lối với kiem-link.sh. Một bản chép của hàm vẽ sẽ xanh
#    mãi mãi kể cả khi bản thật đã đổi — đúng cái bẫy vừa dính ở kiem-ky-don.js.
#
# ⚠️ NÉT CHỮ CHỈ GẦN ĐÚNG. Font dựng sẵn của TFT_eSPI không có ở đây, nên bộ giả vẽ chữ bằng
#    font 5×7 phóng to theo đúng CHIỀU CAO và CHIỀU RỘNG mà `textWidth()` của bản giả trả về.
#    Vậy nên ảnh này dùng để đọc BỐ CỤC (ô nằm đâu, chữ có tràn, có chồng nhau không), không
#    dùng để chấm nét chữ.
#
# Chạy: bash tools/test/fw/ve-man-ghe.sh [tệp-ra.png]
# ==============================================================================================
set -euo pipefail
goc="$(cd "$(dirname "$0")/../../.." && pwd)"
ino="$goc/esp32_ghe_massage/esp32_ghe_massage.ino"
ra="${1:-$goc/dist/man-ghe.png}"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

# --- trích các hàm cần thiết, nguyên văn, theo dấu ngoặc nhọn ---
python3 - "$ino" "$tmp/trich.inc" <<'PY'
import sys, io
ino, ra = sys.argv[1], sys.argv[2]
src = io.open(ino, encoding='utf-8').read()

def boc(chuky):
    i = src.index(chuky)
    d = 0; k = src.index('{', i)
    while k < len(src):
        if src[k] == '{': d += 1
        elif src[k] == '}':
            d -= 1
            if d == 0: return src[i:k+1]
        k += 1
    raise SystemExit('khong boc duoc ' + chuky)

# hằng số màu + bảng nút, lấy nguyên khối khai báo
def dinh(ten):
    for dong in src.split('\n'):
        if dong.startswith('#define ' + ten + ' ') or dong.startswith('#define ' + ten + '  '):
            return dong
    raise SystemExit('khong thay #define ' + ten)

mau = [dinh(t) for t in ('COL_BG','COL_KHUNG','COL_VANG','COL_VANG2','COL_KEM','COL_CHU',
                         'COL_MO','COL_VIP','COL_VIP_MO','COL_MO_KEM','COL_THE','COL_VIEN',
                         'COL_VIEN2','COL_VIEN3','COL_SO','COL_PHU')]
btn = [d for d in src.split('\n') if d.startswith('Btn PKG_BTN[PKG_MAX]')][0]

out = '\n'.join(mau) + '\n' + btn + '\n\n' \
    + boc('String tienVN(long v){') + '\n\n' \
    + boc('int phutGoi(int i){') + '\n\n' \
    + boc('void veTheGoi(int i){') + '\n\n' \
    + boc('void drawIdle(){') + '\n'
io.open(ra, 'w', encoding='utf-8').write(out)
print('  trich: %d dong tu .ino' % out.count('\n'))
PY

cp "$(dirname "$0")/ve-man-ghe.cpp" "$tmp/main.cpp"
g++ -std=c++17 -O1 -I"$tmp" -o "$tmp/ve" "$tmp/main.cpp"
"$tmp/ve" "$tmp/man.ppm"
python3 -c "
from PIL import Image
import sys
im = Image.open('$tmp/man.ppm')
im.resize((im.width*3, im.height*3), Image.NEAREST).save('$ra')
print('  ảnh:', '$ra', im.width, 'x', im.height, '(phóng 3x)')
"
