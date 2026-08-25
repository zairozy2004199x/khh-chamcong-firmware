#!/usr/bin/env python3
"""
Chốt SỐ PHIÊN BẢN của các plugin — hai chỗ khai phải bằng nhau.

VÌ SAO CÓ TỆP NÀY
Ngày 25/08/2026 anh Thắng cài đè plugin chi phí và WordPress hiện bảng so sánh
"Hiện tại 1.35.0 · Đã tải lên 1.35.0" — không biết bản nào mới. Em sửa bốn lượt
mà quên nâng số.

Tệ hơn: mở ra thì thấy header ghi `Version: 1.35.0` còn `VHCP_VERSION` trong mã
vẫn đứng ở `1.31.0`. Số thứ hai KHÔNG phải ghi chú — `vhcp_ver` so với nó để biết
có phải chạy bước nâng cấp không, và nó đi vào `?ver=` của CSS/JS để trình duyệt
bỏ bộ nhớ đệm. Lệch nhau nghĩa là suốt từ 1.31 tới giờ, cài đè không chạy bước
nâng cấp nào và trình duyệt vẫn dùng CSS/JS cũ. Không có gì báo.

Chạy: python3 tools/test/kiem-phien-ban.py
"""
import io, os, re, sys

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'wordpress')

# thư mục plugin -> (tệp chính, tên hằng số phiên bản)
PLUGIN = {
    'vhcp-chi-phi':   ('vhcp-chi-phi.php',   'VHCP_VERSION'),
    'vhcp-cong':      ('vhcp-cong.php',      'VCG_PHIEN_BAN'),
    'vhcp-cham-cong': ('vhcp-cham-cong.php', None),
    'vhcp-ghe':       ('vhcp-ghe.php',       None),
}

hong = 0
dat  = 0

def la(ten, dieu, chi_tiet=''):
    global hong, dat
    if dieu:
        dat += 1
        return
    print('  HỎNG %-52s %s' % (ten, chi_tiet))
    hong += 1

for thu_muc, (ten_tep, hang) in PLUGIN.items():
    duong = os.path.join(GOC, thu_muc, ten_tep)
    if not os.path.exists(duong):
        continue
    src = io.open(duong, encoding='utf-8').read()

    m = re.search(r'^\s*\*\s*Version:\s*([0-9][0-9.]*)\s*$', src, re.M)
    la('%s: có dòng Version ở header' % thu_muc, m is not None)
    if not m:
        continue
    header = m.group(1)

    if not hang:
        dat += 1
        continue

    m2 = re.search(r"define\(\s*'" + hang + r"'\s*,\s*'([0-9][0-9.]*)'\s*\)", src)
    la('%s: có define %s' % (thu_muc, hang), m2 is not None)
    if not m2:
        continue

    # 🔴 CHỐT CHÍNH. Lệch là cài đè không chạy nâng cấp và trình duyệt giữ CSS/JS cũ.
    la('%s: header == %s' % (thu_muc, hang), header == m2.group(1),
       'header %s · %s %s' % (header, hang, m2.group(1)))

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép phiên bản' % dat)
