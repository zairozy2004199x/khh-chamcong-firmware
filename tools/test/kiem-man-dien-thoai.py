#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MÀN NHẬP TRÊN ĐIỆN THOẠI: 16px LÀ BẮT BUỘC, KHÔNG PHẢI GU THẨM MỸ.

Anh Thắng 20/09/2026: *"Hiện trang nhập báo cáo có tích hợp dùng gọn chuẩn trên đt ko"*. Soát
ra một lỗi thật: ô nhập để `font-size:14px`.

🔴 Safari trên iPhone TỰ PHÓNG TO cả trang mỗi khi chạm vào ô nhập có cỡ chữ DƯỚI 16px. Không
   tắt được bằng CSS; `user-scalable=no` thì iOS bỏ qua từ lâu (mà chặn phóng to cũng là chặn
   luôn người mắt kém). Nhân viên nhập báo cáo ngoài cửa hàng gõ bằng điện thoại, nên cứ mỗi ô
   là màn nhảy một cái rồi phải vuốt về. Đây là loại lỗi không ai mở báo lỗi, người ta chỉ thôi
   dùng màn ấy.

Bốn thứ bài này canh, đều là chuyện mở trên máy tính thì không thấy gì:

  1. Ở bề ngang điện thoại, MỌI ô nhập phải >= 16px.
  2. Ô chạm phải cao >= 44px — ngón tay không bấm trúng ô cao 34px.
  3. Thẻ `viewport` phải có, và KHÔNG được chặn phóng to.
  4. Lưới ô nhập phải tự xuống một cột (`auto-fit` + `minmax`), chứ không ghim số cột cứng.

Chạy: python3 tools/test/kiem-man-dien-thoai.py
"""
import re
import sys
from pathlib import Path

GOC = Path(__file__).resolve().parents[2]
CSS = GOC / 'wordpress' / 'khh-doanh-thu' / 'assets' / 'doanh-thu.css'
PHP = GOC / 'wordpress' / 'khh-doanh-thu' / 'khh-doanh-thu.php'
JS = GOC / 'wordpress' / 'khh-doanh-thu' / 'assets' / 'doanh-thu.js'

dat, hong = 0, []


def t(ten, dk):
    global dat
    if dk:
        dat += 1
    else:
        hong.append(ten)


# ⚠️ BÓC CHÚ THÍCH TRƯỚC KHI QUÉT. Bài kiểu này đã hai lần xanh nhầm vì từ khoá cần tìm nằm
#    trong chính đoạn chú thích giải thích nó — và ở đây còn tệ hơn: chú thích lọt vào phần
#    "bộ chọn" của phép dò, làm câu báo lỗi đọc không ra gì.
css = re.sub(r'/\*.*?\*/', ' ', CSS.read_text(encoding='utf-8'), flags=re.S)
php = PHP.read_text(encoding='utf-8')
js = JS.read_text(encoding='utf-8')


def khoi_dien_thoai(s):
    """Thân của MỌI khối @media bề ngang điện thoại, nối lại.

    🔴 PHẢI GOM HẾT, KHÔNG LẤY MỖI KHỐI ĐẦU. Bản đầu của bài này chỉ lấy khối @media đầu tiên,
       trong khi luật bày thẻ dọc cho sổ kho nằm ở một khối @media THỨ HAI cuối tệp. Đã thử:
       hạ ô nhập của sổ kho xuống 14px thì bài vẫn xanh — đúng cái lỗi Safari phóng to mà bài
       này sinh ra để canh, lọt ngay ở màn nhân viên dùng nhiều nhất.
    """
    ra = []
    for m in re.finditer(r'@media\s*\(max-width:\s*(?:5\d\d|6\d\d)px\)\s*\{', s):
        i = m.end()
        sau, j = 1, i
        while j < len(s) and sau:
            if s[j] == '{':
                sau += 1
            elif s[j] == '}':
                sau -= 1
            j += 1
        ra.append(s[i:j - 1])
    return '\n'.join(ra)


dt = khoi_dien_thoai(css)
t('có khối @media cho bề ngang điện thoại', dt != '')

# ---- 1. 16px ----
m = re.search(r'font-size:\s*16px', dt)
t('🔴 ô nhập đặt font-size 16px ở bề ngang điện thoại', m is not None)
if m:
    # phải phủ input, select và textarea — thiếu cái nào là cái ấy vẫn làm Safari phóng to
    truoc = dt[:m.start()]
    cho = truoc[truoc.rfind('}') + 1:] if '}' in truoc else truoc
    for the in ('input', 'select', 'textarea'):
        t('luật 16px phủ cả <%s>' % the, the in cho)

# 🔴 Và KHÔNG được còn ô nhập nào dưới 16px trong khối điện thoại.
for x in re.finditer(r'([^{}]*)\{([^{}]*font-size:\s*(\d+(?:\.\d+)?)px[^{}]*)\}', dt):
    chon, than, co = x.group(1), x.group(2), float(x.group(3))
    if re.search(r'\b(input|select|textarea)\b', chon) and co < 16:
        t('🔴 còn ô nhập để %gpx ở bề ngang điện thoại (%s)' % (co, chon.strip()[:40]), False)

# ---- 2. ô chạm 44px ----
t('🔴 ô nhập cao ít nhất 44px trên điện thoại', re.search(r'min-height:\s*4[4-9]px|min-height:\s*[5-9]\dpx', dt) is not None)

# ---- 3. viewport ----
m = re.search(r'<meta name="viewport" content="([^"]+)"', php)
t('có thẻ viewport', m is not None)
if m:
    n = m.group(1)
    t('viewport đặt width=device-width', 'width=device-width' in n)
    t('🔴 KHÔNG chặn phóng to (chặn là chặn luôn người mắt kém)',
      'user-scalable=no' not in n and 'maximum-scale=1' not in n)

# ---- 4. lưới tự xuống cột ----
t('🔴 lưới ô nhập dùng auto-fit + minmax, không ghim số cột cứng',
  re.search(r'\.bc-luoi\{[^}]*auto-fit[^}]*minmax', css) is not None)
t('dải số máy POS cũng vậy',
  re.search(r'\.bc-pos-hang\{[^}]*auto-fit[^}]*minmax', css) is not None)
t('bảng dài thì cuộn ngang, không vỡ layout',
  re.search(r'\.bang-cuon\{[^}]*overflow-x:\s*auto', css) is not None)
t('hàng lọc và hàng nút biết xuống dòng',
  re.search(r'\.loc\{[^}]*flex-wrap:\s*wrap', css) is not None
  and re.search(r'\.bc-nut\{[^}]*flex-wrap:\s*wrap', css) is not None)

# ---- 5. ô số phải gợi bàn phím số ----
boCC = re.sub(r'/\*.*?\*/', ' ', js, flags=re.S)
m = re.search(r'function o_nhap\(.*?\n  \}', boCC, re.S)
t('tìm thấy hàm dựng ô nhập', m is not None)
if m:
    t('🔴 ô nhập số gợi BÀN PHÍM SỐ (inputmode numeric)', 'inputmode="numeric"' in m.group(0))
    t('và KHÔNG dùng type="number" — dấu chấm kiểu Việt bị hiểu là thập phân',
      'type="number"' not in m.group(0))

if hong:
    print('\n✗ HỎNG %d phép (đạt %d):' % (len(hong), dat))
    for h in hong:
        print('   · 🔴 %s' % h)
    sys.exit(1)
print('\n✓ SẠCH — %d phép: màn nhập gõ được bằng điện thoại, không bị Safari phóng to.' % dat)
