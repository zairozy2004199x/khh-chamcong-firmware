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

# 🔴 TỰ DÒ, KHÔNG GÕ TAY DANH SÁCH.
# Bản đầu gõ tay bốn plugin, và hai trong bốn khai hằng số là None nên chốt chính bỏ qua luôn.
# Thêm plugin mới (vhcp-trang-chu, vhcp-noi-bo, vhcp-hop-dong) thì chẳng ai nhớ khai vào đây —
# đúng ngày 26/08/2026 em nâng header vhcp-trang-chu lên 1.4.0 mà để VHTC_VERSION ở 1.3.0, và
# bài kiểm này xanh. Nay dò cả thư mục: plugin nào có mặt là plugin ấy bị canh.
#
# Quy ước: tệp chính của plugin `wordpress/<ten>/` là `<ten>.php`. Cả bảy plugin đang theo đúng
# quy ước này, và chốt "có tệp chính" ở dưới sẽ đỏ ngay nếu có cái thứ tám không theo.
def tim_plugin():
    ra = {}
    for ten in sorted(os.listdir(GOC)):
        thu_muc = os.path.join(GOC, ten)
        if not os.path.isdir(thu_muc):
            continue
        chinh = os.path.join(thu_muc, ten + '.php')
        if os.path.exists(chinh):
            ra[ten] = chinh
    return ra

hong = 0
dat  = 0

def la(ten, dieu, chi_tiet=''):
    global hong, dat
    if dieu:
        dat += 1
        return
    print('  HỎNG %-52s %s' % (ten, chi_tiet))
    hong += 1

PLUGIN = tim_plugin()

# Đếm từ chính thư mục, không gõ tay con số — nhưng vẫn phải có gì đó để đếm: bài kiểm chạy đúng
# ngần ấy vòng lặp rồi báo "SẠCH" cũng là cái nó sẽ làm khi đường dẫn sai và không thấy plugin nào.
la('tìm thấy plugin để canh', len(PLUGIN) >= 4, '%d thư mục' % len(PLUGIN))

for thu_muc, duong in PLUGIN.items():
    src = io.open(duong, encoding='utf-8').read()

    m = re.search(r'^\s*\*\s*Version:\s*([0-9][0-9.]*)\s*$', src, re.M)
    la('%s: có dòng Version ở header' % thu_muc, m is not None)
    if not m:
        continue
    header = m.group(1)

    # Tên hằng số cũng dò luôn: mỗi plugin đặt một tiền tố riêng (VHCP_, VHCC_, VCG_, VHNB_…),
    # gõ tay bảng quy đổi là lại thêm một chỗ phải nhớ sửa.
    hs = re.findall(r"define\(\s*'([A-Z0-9_]*(?:VERSION|PHIEN_BAN))'\s*,\s*'([0-9][0-9.]*)'\s*\)", src)
    la('%s: tệp chính có khai đúng MỘT hằng số phiên bản' % thu_muc, len(hs) == 1,
       '%d hằng: %s' % (len(hs), [x[0] for x in hs]))
    if len(hs) != 1:
        continue
    hang, gia_tri = hs[0]

    # 🔴 CHỐT CHÍNH. Lệch là cài đè không chạy nâng cấp và trình duyệt giữ CSS/JS cũ.
    la('%s: header == %s' % (thu_muc, hang), header == gia_tri,
       'header %s · %s %s' % (header, hang, gia_tri))

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép phiên bản' % dat)
