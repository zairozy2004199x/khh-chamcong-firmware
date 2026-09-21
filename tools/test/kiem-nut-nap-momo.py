#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
NHẮC NGƯỜI TA NẠP FILE MÀ KHÔNG CHỈ ĐƯỜNG THÌ BẰNG KHÔNG NHẮC.

17/09/2026: anh Thắng đứng ngay trước bảng Đối soát MoMo — bảng ấy đang in rõ "Sổ MoMo chưa có
16 ngày … Tải file MoMo của những ngày này lên rồi xem lại" — và kết luận *"chưa có chỗ nạp
momo đối soát"*. Chỗ nạp CÓ, từ bản 1.28.0: thẻ "Sao kê MoMo" trong hộp Nạp số liệu. Nhưng mọi
câu nhắc trong app đều là chữ trơn dạng "bấm Nạp báo cáo → thẻ Sao kê MoMo", tức bắt người đọc
tự tìm một nút ở góc trên màn rồi đếm sang thẻ thứ tư. Không ai làm thế giữa lúc đang soát sổ.

Bài này canh bốn chuyện, đều là chuyện `node --check` xanh mà màn vẫn hỏng:

  1. Câu nhắc thiếu ngày sổ MoMo PHẢI kèm nút bấm được.
  2. Mọi `data-mo-nap="X"` phải trỏ tới một thẻ `data-loai="X"` có thật. Sai tên là nút mở hộp
     ra rồi KHÔNG chọn thẻ nào, im lặng — người bấm tưởng hệ hỏng.
  3. `moHop` chọn thẻ bằng cách BẤM thẻ ấy, không tự đặt `S.napLoai`. Tự đặt là có bản thứ hai
     của luật đổi thẻ, và bản thứ hai sớm muộn lệch -> hộp mở ra thẻ này mà hướng dẫn thẻ khác.
  4. Nút "Nạp báo cáo" ở góc trên phải gắn qua hàm BỌC. Truyền `moHop` trực tiếp làm bộ xử lý
     thì tham số đầu là đối tượng sự kiện, nên nó đi tìm thẻ `[data-loai="[object PointerEvent]"]`
     — không thấy, không chọn gì. Đây đúng là bẫy đã có sẵn trong mã trước lượt vá.

Chạy: python3 tools/test/kiem-nut-nap-momo.py
"""
import re
import sys
from pathlib import Path

GOC = Path(__file__).resolve().parents[2]
TEP = GOC / 'wordpress' / 'khh-doanh-thu' / 'assets' / 'doanh-thu.js'

dat, hong = 0, []

def t(ten, dk):
    global dat
    if dk:
        dat += 1
    else:
        hong.append(ten)

if not TEP.exists():
    sys.exit('KHÔNG thấy %s' % TEP)
s = TEP.read_text(encoding='utf-8')

# ---- 1. câu nhắc thiếu ngày phải có nút ----
m = re.search(r"Sổ MoMo <b>chưa có.*?</div>'", s, re.S)
t('cau nhac "So MoMo chua co N ngay" con ton tai', m is not None)
if m:
    t('cau nhac thieu ngay CO nut nap (khong phai chu tron)',
      'data-mo-nap' in m.group(0) or 'NUT_NAP_MOMO_SK' in m.group(0))

# ---- 2. mọi data-mo-nap phải trỏ tới một thẻ có thật ----
the = set(re.findall(r'data-loai="([a-z_]+)"', s))
nut = set(re.findall(r'data-mo-nap="([a-z_]+)"', s))
t('co it nhat mot nut mo hop nap', len(nut) > 0)
t('thẻ momo_sk ton tai', 'momo_sk' in the)
for n in sorted(nut):
    t('nut data-mo-nap="%s" tro tới thẻ có thật' % n, n in the)

# ---- 3. moHop chọn thẻ bằng cách BẤM ----
m = re.search(r'\n  function moHop\((\w*)\) \{(.*?)\n  \}', s, re.S)
t('tim thay ham moHop', m is not None)
if m:
    tham, than = m.group(1), m.group(2)
    t('moHop NHAN tham so ten the', tham != '')
    t('moHop chon the bang cach BAM (.click())', '.click()' in than)
    t('moHop KHONG tu dat S.napLoai (tranh ban thu hai cua luat doi the)',
      not re.search(r'S\.napLoai\s*=', than))

# ---- 4. #dtNap phải gắn qua hàm bọc ----
m = re.search(r"q\('#dtNap'\)\.addEventListener\('click',\s*([^)]*)\)", s)
t('tim thay cho gan nut Nap bao cao', m is not None)
if m:
    t('#dtNap gan qua ham BOC, khong truyen moHop tran (doi tuong su kien se thanh ten the)',
      m.group(1).strip() != 'moHop')

# ---- 5. uỷ quyền: nút vẽ lại sau vẫn phải chạy ----
t('co uy quyen su kien cho [data-mo-nap] (nut nam trong khoi ve lai)',
  re.search(r"closest\(\s*'\[data-mo-nap\]'\s*\)", s) is not None)

if hong:
    print('✗ TRƯỢT %d phép (đạt %d):' % (len(hong), dat))
    for h in hong:
        print('  · 🔴 ' + h)
    sys.exit(1)
print('\n✓ SẠCH — %d phép: câu nhắc nạp MoMo có nút, nút trỏ đúng thẻ, moHop bấm thẻ thật.' % dat)
