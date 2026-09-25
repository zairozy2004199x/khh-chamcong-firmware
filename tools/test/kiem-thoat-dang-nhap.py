#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
VÀO ĐƯỢC THÌ PHẢI RA ĐƯỢC — CẢ HAI LỐI VÀO.

Trang doanh thu có HAI lối vào, cố ý:
  · cửa hàng trưởng không có tài khoản WordPress -> vào bằng PIN chấm công (`manPin`);
  · người văn phòng -> vào bằng tài khoản WordPress.

Trước 18/09/2026 nút "Thoát" chỉ hiện khi `cf.bang_pin`, tức CHỈ lối PIN mới ra được. Người vào
bằng tài khoản không có đường nào thoát khỏi trang.

🔴 VÌ SAO ĐÁNG CÓ BÀI NÀY. Đây là trang mở trên MÁY DÙNG CHUNG ở cửa hàng và trên điện thoại
   chuyền tay nhau. Không thoát được nghĩa là người sau ngồi vào vẫn đang là người trước — xem
   được đúng những gì người trước xem, gồm doanh thu mọi cơ sở nếu người trước là quản lý. Nó
   không báo lỗi, không ai thấy gì bất thường, nên sẽ không ai đi tìm.

🔴 23/09/2026 — MỘT NÚT, MÁY CHỦ TỰ THOÁT. Anh Thắng: *"đăng xuất ra nó nhảy ra trang wordpress"*.
   Lối cũ cho tài khoản là <a href=wp_logout_url>: trình duyệt bị đưa sang wp-login.php và (tuỳ
   nonce còn hạn, tuỳ plugin móc `logout_redirect`, tuỳ link đẹp đã flush) không quay về. Nay:
     cả hai lối -> nút #dtThoat -> REST `dang-xuat`; máy chủ đóng phiên PIN VÀ gọi `wp_logout()`
     khi đang là tài khoản WordPress; trả `wp: true` thì màn TẢI LẠI TRANG (nonce REST cũ đã chết
     cùng phiên, gọi tiếp là 403) — tải lại đúng địa chỉ đang đứng, không đi qua wp-login.php.
   `link_ra` (wp_logout_url) chỉ còn là đường LÙI khi REST hỏng, và chỉ cho lối tài khoản.

Chạy: python3 tools/test/kiem-thoat-dang-nhap.py
"""
import re
import sys
from pathlib import Path

GOC = Path(__file__).resolve().parents[2] / 'wordpress' / 'khh-doanh-thu'
JS  = GOC / 'assets' / 'doanh-thu.js'
CSS = GOC / 'assets' / 'doanh-thu.css'
PHP = GOC / 'khh-doanh-thu.php'

dat, hong = 0, []

def t(ten, dk):
    global dat
    if dk:
        dat += 1
    else:
        hong.append(ten)

for f in (JS, CSS, PHP):
    if not f.exists():
        sys.exit('KHÔNG thấy %s' % f)
js, css, php = JS.read_text(encoding='utf-8'), CSS.read_text(encoding='utf-8'), PHP.read_text(encoding='utf-8')

# ---- 1. Máy chủ phải gửi ra đường thoát WordPress ----
t('cấu hình gửi ra link_ra', "'link_ra'" in php)
t('link_ra dựng bằng wp_logout_url (không tự ghép URL)',
  re.search(r"'link_ra'\s*=>\s*esc_url_raw\(\s*wp_logout_url\(", php) is not None)
t('vẫn còn link_wp cho lối đăng nhập', "'link_wp'" in php)

# ---- 2. Màn hình phải phơi nút Thoát cho CẢ HAI lối ----
m = re.search(r'\n  function veNguoiXem\(cf\) \{(.*?)\n  \}', js, re.S)
t('tìm thấy veNguoiXem', m is not None)
if m:
    # ⚠️ BỎ CHÚ THÍCH TRƯỚC KHI SOI. Chính bài này đã để lọt một lượt đục thử vì chữ `link_ra`
    #    nằm trong câu chú thích ngay trên đoạn mã — gỡ hẳn nhánh <a> đi mà phép vẫn xanh. Đúng
    #    lớp lỗi vừa phải vá ở `kiem-bo-ao-tron.php` sáng nay: chữ trong chú thích không phải mã
    #    chạy, và một phép thử soi cả chú thích là phép thử tự lừa mình.
    than = re.sub(r'/\*[\s\S]*?\*/', ' ', m.group(1))
    than = re.sub(r'(?<!:)//[^\n]*', ' ', than)
    t('có nút Thoát #dtThoat', 'dtThoat' in than)
    t('nút gọi thoatPin', 'thoatPin' in than)
    # 🔴 Nút Thoát KHÔNG tuỳ lối vào: người tài khoản cũng phải có nút (18/09 từng thiếu).
    t('🔴 nút Thoát không bị gác sau bang_pin', re.search(r'bang_pin\s*\?', than) is None)
    # 🔴 Không còn <a href=wp_logout_url> — chính cái đưa người ta sang trang WordPress (23/09).
    t('🔴 không còn <a> trỏ link_ra trong veNguoiXem', re.search(r"<a[^>]*link_ra", than) is None)

# ---- 2b. Máy chủ tự thoát WordPress trong REST dang-xuat ----
NG = GOC / 'nguoi.php'
ng = re.sub(r'/\*[\s\S]*?\*/', ' ', NG.read_text(encoding='utf-8'))
m4 = re.search(r'\nfunction khh_dt_rest_dang_xuat\(\) \{(.*?)\n\}', ng, re.S)
t('tìm thấy khh_dt_rest_dang_xuat', m4 is not None)
if m4:
    t('🔴 dang-xuat gọi wp_logout() khi đang là tài khoản WordPress',
      re.search(r'is_user_logged_in\(\)[\s\S]{0,80}wp_logout\(\)', m4.group(1)) is not None)
    t('và vẫn đóng phiên PIN (khh_dt_phien_dong)', 'khh_dt_phien_dong' in m4.group(1))
    t("trả cờ 'wp' cho màn biết phải tải lại", "'wp'" in m4.group(1))

# ---- 3. thoatPin vẫn phải đóng phiên PIN ở máy chủ, không chỉ xoá thẻ ở máy ----
m2 = re.search(r'\n  function thoatPin\(\) \{(.*?)\n  \}', js, re.S)
t('tìm thấy thoatPin', m2 is not None)
if m2:
    than2 = re.sub(r'/\*[\s\S]*?\*/', ' ', m2.group(1))
    t('thoatPin gọi REST dang-xuat (đóng phiên ở máy chủ)', "'dang-xuat'" in than2)
    t('và vẫn xoá thẻ ở máy dù máy chủ có lỗi', 'datThe' in than2)
    # 🔴 Huỷ phiên WordPress rồi thì nonce REST đang cầm đã chết -> phải tải lại trang, không khoiDong().
    t('🔴 r.wp -> tải lại trang (location.reload)', re.search(r'r\.wp\)[\s\S]{0,40}location\.reload\(\)', than2) is not None)
    # Đường lùi: REST hỏng mà là tài khoản WordPress -> mới dùng link_ra.
    t('link_ra chỉ dùng ở nhánh catch (đường lùi)', re.search(r'catch\([\s\S]*link_ra', than2) is not None)
    t('và chỉ cho lối tài khoản (!bang_pin)', re.search(r'!cf\.bang_pin\s*&&\s*cf\.link_ra', than2) is not None)

# ---- 4. Lớp .vien dùng cho cả <button> lẫn <a> ----
m3 = re.search(r'\.khh-dt \.vien\{(.*?)\}', css, re.S)
t('tìm thấy luật .vien', m3 is not None)
if m3:
    t('.vien bỏ gạch chân (kẻo <a> Thoát bị gạch chân)', 'text-decoration:none' in m3.group(1))
    t('.vien là inline-block (kẻo <a> lệch khỏi hàng)', 'inline-block' in m3.group(1))

if hong:
    print('✗ TRƯỢT %d phép (đạt %d):' % (len(hong), dat))
    for h in hong:
        print('  · 🔴 ' + h)
    sys.exit(1)
print('\n✓ SẠCH — %d phép: vào bằng PIN hay bằng tài khoản thì đều thoát được.' % dat)
