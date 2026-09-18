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

⚠️ HAI LỐI THOÁT KHÁC NHAU, KHÔNG DÙNG CHUNG MỘT ĐƯỜNG:
     vào bằng PIN      -> REST `dang-xuat` (đóng phiên PIN, KHÔNG đụng đăng nhập WordPress)
     vào bằng tài khoản -> `wp_logout_url()` (thoát hẳn khỏi WordPress)
   Và lối thứ hai phải là <a> cho người BẤM: `wp_logout_url()` mang nonce, gọi bằng fetch là
   WordPress chối — chối im lặng, nên người dùng chỉ thấy một nút bấm không phản hồi.

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
    t('lối PIN có nút Thoát', 'dtThoat' in than)
    t('lối PIN gọi thoatPin', 'thoatPin' in than)
    # 🔴 Chính chỗ đã hỏng: nhánh KHÔNG phải bang_pin cũng phải có đường ra.
    t('🔴 lối TÀI KHOẢN cũng có đường Thoát (dùng link_ra)', 'link_ra' in than)
    t('🔴 đường ấy là <a> cho người bấm, không phải fetch (wp_logout_url mang nonce)',
      re.search(r"<a[^>]*href=[^>]*link_ra", than) is not None)
    t('link_ra đi qua esc() trước khi ghép vào HTML', re.search(r'esc\(\s*cf\.link_ra\s*\)', than) is not None)
    # Chốt ngược: đừng biến nút PIN thành link WordPress — hai lối khác nhau.
    t('lối PIN KHÔNG bị đổi thành link_ra',
      re.search(r'bang_pin\s*\?[^:]*dtThoat', than) is not None)

# ---- 3. thoatPin vẫn phải đóng phiên PIN ở máy chủ, không chỉ xoá thẻ ở máy ----
m2 = re.search(r'\n  function thoatPin\(\) \{(.*?)\n  \}', js, re.S)
t('tìm thấy thoatPin', m2 is not None)
if m2:
    t('thoatPin gọi REST dang-xuat (đóng phiên ở máy chủ)', "'dang-xuat'" in m2.group(1))
    t('và vẫn xoá thẻ ở máy dù máy chủ có lỗi', 'datThe' in m2.group(1))

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
