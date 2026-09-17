#!/usr/bin/env bash
# Kiểm THÂN JSON CỦA LƯỢT CHẤM CÔNG + luật tên cơ sở, bằng g++ — không cần Arduino, không cần
# máy thật.
#
# Vì sao cần: thân JSON này là chuỗi mà TIỀN LƯƠNG đi qua. Nếu nó hỏng, cổng nhận trả 400,
# `wpGoi` trả "" và lượt chấm công rơi vào sổ đầu đọc — trong khi NHỊP SỐNG VẪN XANH và màn hình
# vẫn báo bình thường. Không có tín hiệu nào để ai đó biết mà đi sửa. Tệ hơn: hỏng chỉ xảy ra với
# đúng những nhân viên có dấu " hoặc \ trong tên, nên nhìn bảng chỉ thấy "vài người hay thiếu
# công" — rất dễ bị quy oan thành lỗi người chứ không phải lỗi máy.
#
# Trích hàm TỪ CHÍNH .ino chứ không chép lại — chép là sớm muộn lệch với firmware đang chạy, và
# lúc đó phép thử vẫn xanh trong khi máy thật đã hỏng.
#
# Chạy: bash tools/test/fw/kiem-than-cham-cong.sh
set -euo pipefail
cd "$(dirname "$0")/../../.."
INO=esp32_hik_chamcong_full/esp32_hik_chamcong_full.ino
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

python3 - "$INO" "$TMP/trich.inc" <<'PY'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()

# Thứ tự trong tệp sinh ra phải là thứ tự phụ thuộc: thanChamCong gọi jsonEscMin_.
mau = [
    ('fwMaNgay',     r'\nString fwMaNgay\(const String& v\)\{.*?\n\}\n'),
    ('tenCoSoHopLe', r'\nbool tenCoSoHopLe\(const String& t\)\{.*?\n\}\n'),
    ('jsonEscMin_',  r'\nString jsonEscMin_\(const String& s\)\{[^\n]*\n'),
    ('thanChamCong', r'\nString thanChamCong\(const String& mac,.*?\n\}\n'),
]
out = []
for ten, rx in mau:
    m = re.search(rx, s, re.S)
    if not m:
        sys.exit('KHÔNG tìm thấy hàm %s trong .ino — đổi tên/đổi chữ ký thì sửa luôn phép thử này.' % ten)
    out.append(m.group(0))
io.open(sys.argv[2], 'w', encoding='utf-8').write(''.join(out))
PY

# ---- Soát CHỖ GỌI, không chỉ soát hàm ----
# Phép thử C++ ở dưới gọi thẳng vào `tenCoSoHopLe`/`thanChamCong`, nên nó chỉ chứng minh HÀM
# đúng. Nếu có ai gỡ lời gọi ở một cửa thì hàm vẫn đúng, phép thử vẫn xanh, mà cửa đó lại nhận
# bừa như trước — đúng kiểu hỏng của bản trước 17/09/2026. Nên phải soát ngay trong .ino.
#
# Soát THEO TỪNG HÀM chứ không đếm tổng số lời gọi: đếm tổng thì một chú thích có nhắc tên hàm
# cũng làm sai số, và thêm cửa thứ tư hợp lệ lại làm đỏ oan.
python3 - "$INO" <<'PY2'
import io, re, sys
s = io.open(sys.argv[1], encoding='utf-8').read()

def than_ham(ten, mau):
    m = re.search(mau, s, re.S)
    if not m:
        sys.exit('HONG: khong tim thay %s trong .ino — doi ten ham thi sua luon phep thu nay.' % ten)
    # Bỏ chú thích trước khi soi, kẻo chính câu "xem `tenCoSoHopLe()`" lại được tính là lời gọi.
    t = re.sub(r'/\*.*?\*/', ' ', m.group(0), flags=re.S)
    t = re.sub(r'(?<!:)//[^\n]*', ' ', t)
    return t

CUA = [
    # Mẫu BẮT BUỘC có dấu `{`: `hbSend` có DÒNG KHAI TRƯỚC (`void hbSend();` phía trên
    # `backfillRange`). Mẫu không đòi `{` thì nó khớp vào dòng khai đó rồi quét tiếp sang thân
    # của hàm KHÁC, và phép thử đỏ oan. Đỏ oan cũng tai như xanh oan: lần sau không ai tin nó.
    ('hoiCuaHang',        r'\nbool hoiCuaHang\([^)]*\)\s*\{.*?\n\}',        'may chu tra loi rieng'),
    ('hbSend',            r'\nvoid hbSend\([^)]*\)\s*\{.*?\n\}',            'nhip song'),
    ('handleSaveStation', r'\nvoid handleSaveStation\([^)]*\)\s*\{.*?\n\}', 'o go tay o portal'),
]
for ten, mau, mo_ta in CUA:
    if 'tenCoSoHopLe(' not in than_ham(ten, mau):
        sys.exit('HONG: `%s()` (%s) khong goi `tenCoSoHopLe`. Cua nay nhan bua ten co so co ky '
                 'tu la -> hong JSON MOI luot cham cong cua may do, ma nhip song van xanh nen '
                 'khong ai biet de sua.' % (ten, mo_ta))

# Thân chấm công phải đi qua builder, không được dựng chuỗi tay lại trong pushEvent.
if 'thanChamCong(' not in than_ham('pushEvent', r'\nbool pushEvent\([^)]*\)\s*\{.*?\n\}'):
    sys.exit('HONG: `pushEvent` khong goi `thanChamCong()`. Dung chuoi JSON tay ngay trong '
             'pushEvent la vong qua het phep thu escape o duoi.')

print('  chỗ gọi: cả 3 cửa tên cơ sở đều có gác, pushEvent dùng builder.')
PY2

cp tools/test/fw/kiem-than-cham-cong.cpp "$TMP/t.cpp"
g++ -std=c++17 -I"$TMP" -o "$TMP/t" "$TMP/t.cpp"
"$TMP/t"
