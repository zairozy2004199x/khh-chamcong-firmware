#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MÀN CẤU HÌNH HỘP THƯ: PHẢI CHỌN ĐƯỢC "HẰNG NGÀY LÚC HH:MM".

23/09/2026 anh Thắng xem hộp thư: FABi gửi đúng 08:00 mỗi sáng — "vậy tự lấy lúc 8h02 đi, 1 lần
thôi". Máy chủ đã có chế độ `ngay`/`luc`; bài này canh MÀN có bày ra và gửi đúng hai trường ấy,
vì máy chủ đúng mà màn không có ô thì người dùng vẫn kẹt ở "mỗi 2 giờ".
Quét trên mã đã bóc chú thích.

Chạy: python3 tools/test/kiem-hop-thu-man.py
"""
import re, sys
from pathlib import Path

TEP = Path(__file__).resolve().parents[2] / 'wordpress' / 'khh-doanh-thu' / 'assets' / 'doanh-thu.js'
dat, hong = 0, []
def t(ten, dk):
    global dat
    if dk: dat += 1
    else: hong.append(ten)

s = TEP.read_text(encoding='utf-8')
s = re.sub(r'/\*.*?\*/', ' ', s, flags=re.S)
s = re.sub(r'(?m)^\s*//.*$', ' ', s)

m = re.search(r'function veHopThu\(o, r\) \{(.*?)\n  \}', s, re.S)
t('tim thay veHopThu', m is not None)
v = m.group(1) if m else ''
t('🔴 co o chon che do (data-thu="che_do")', 'data-thu="che_do"' in v)
t('che do co hai lua chon ngay/gio', 'value="ngay"' in v and 'value="gio"' in v)
t('🔴 co o gio lay (data-thu="luc") kieu time', re.search(r"'luc', c\.luc \|\| '08:02', 'time'", v) is not None)
t('mac dinh hien 08:02 khi chua dat', "c.luc || '08:02'" in v)
t('tieu de khung noi "hang ngay luc" khi che do ngay', 'hằng ngày lúc' in v)
t('van giu o nhip gio cho che do theo gio', "o1('Nhịp (giờ)', 'gio'" in v)
# bo xu ly Luu phai gui moi [data-thu] -> che_do va luc di theo tu dong
m2 = re.search(r'function noiHopThu\(o\) \{(.*?)\n  \}', s, re.S)
t('noiHopThu gom moi [data-thu] khi luu (nen che_do/luc di theo)', m2 is not None and "querySelectorAll('[data-thu]')" in m2.group(1))

# ---- "nạp được 0" phải nói VÌ SAO, và chỉ đường thêm địa chỉ bị chối ----
m3 = re.search(r"api\('hop-thu-chay'[\s\S]*?window\.alert\(([\s\S]*?)\);", s)
t('tim thay cau bao sau Lay thu ngay', m3 is not None)
if m3:
    a = m3.group(1)
    t('🔴 cau bao gom LY DO bo qua theo nhom (c.bo -> ly[b.vi])', 'c.bo' in a or 'lyDo' in a)
    t('🔴 cau bao neu DIA CHI GUI bi choi', 'nguoi_gui_la' in a)
t('🔴 co nut "Them <dia chi>" (data-thu-them) trong nhat ky', 'data-thu-them=' in s)
m4 = re.search(r"closest\('\[data-thu-them\]'\)([\s\S]*?)\n    \}\);", s)
t('co bo xu ly nut Them', m4 is not None)
if m4:
    b = m4.group(1)
    t('ghep vao o [data-thu="nguoi_gui"]', 'data-thu="nguoi_gui"' in b)
    t('khong them trung', 'indexOf(dc) < 0' in b)
    t('🔴 them xong tu bam Luu', "#dtThuLuu" in b and '.click()' in b)
t('hien canh bao do khi o dia chi co muc khong phai email', 'canh_bao_nguoi_gui' in v)

if hong:
    print('\n✗ HỎNG %d phép (đạt %d):' % (len(hong), dat)); [print('   · 🔴 ' + h) for h in hong]; sys.exit(1)
print('\n✓ SẠCH — %d phép: màn hộp thư chọn được hằng ngày lúc HH:MM.' % dat)
