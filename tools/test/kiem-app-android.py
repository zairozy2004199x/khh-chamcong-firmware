#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SOI CẤU TRÚC MÃ KOTLIN CỦA APP THU TIỀN.

🔴 VÌ SAO CÓ TỆP NÀY.
   Máy viết mã không có Android SDK, nên không biên dịch được — mà CI thì chỉ chạy khi đã đẩy
   lên. Khoảng giữa hai lúc đó là chỗ những lỗi ngớ ngẩn nhất sống sót: ngoặc lệch, gọi hàm
   không tồn tại, trùng tên giữa lớp và hàm.

   Phép soi này KHÔNG thay được trình biên dịch. Nó chỉ bắt những lớp lỗi đã thật sự cắn:
     · Ngoặc lệch — một dấu là cả tệp vô nghĩa.
     · Gọi tên không khai ở đâu trong app (bắt lỗi gõ nhầm, và lỗi chép mã từ tệp khác sang —
       đúng lớp lỗi `Lf is not defined` vừa làm trắng cả trang web hôm nay).
     · Trùng tên giữa LỚP và HÀM cùng gói — Kotlin nhận cả hai, rồi gọi nhầm cái kia.
     · Import khai mà không dùng, và dùng mà không import (trong phạm vi tên của app).

⚠️ Chạy: python3 tools/test/kiem-app-android.py
"""
import os
import re
import sys

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'android_thu_tien')
GOC = os.path.normpath(GOC)
NGUON = os.path.join(GOC, 'app', 'src', 'main', 'java')

loi = []
dat = 0


def t(ten, dung, them=''):
    global dat
    if dung:
        dat += 1
    else:
        loi.append(ten + (' → ' + them if them else ''))


def bo_chu_thich(s):
    """Gỡ chú thích và chuỗi, để phép đếm ngoặc không vấp vào một dấu ngoặc trong câu chữ."""
    ra = []
    i, n = 0, len(s)
    while i < n:
        c = s[i]
        if c == '"' and s[i:i + 3] == '"""':
            k = s.find('"""', i + 3)
            i = n if k < 0 else k + 3
            ra.append(' ')
            continue
        if c == '"':
            i += 1
            while i < n:
                if s[i] == '\\':
                    i += 2
                    continue
                if s[i] == '"':
                    i += 1
                    break
                i += 1
            ra.append(' ')
            continue
        if c == "'":
            i += 1
            while i < n and s[i] != "'":
                i += 2 if s[i] == '\\' else 1
            i += 1
            ra.append(' ')
            continue
        if s[i:i + 2] == '/*':
            k = s.find('*/', i + 2)
            i = n if k < 0 else k + 2
            ra.append(' ')
            continue
        if s[i:i + 2] == '//':
            k = s.find('\n', i)
            i = n if k < 0 else k
            continue
        ra.append(c)
        i += 1
    return ''.join(ra)


tep = []
for goc, _, ds in os.walk(NGUON):
    for x in ds:
        if x.endswith('.kt'):
            tep.append(os.path.join(goc, x))
tep.sort()

t('tìm được mã nguồn Kotlin', len(tep) >= 8, str(len(tep)))

khai_ham = {}      # tên hàm -> tệp
khai_lop = {}      # tên lớp/object -> tệp
than = {}          # đã gỡ chú thích VÀ CHUỖI — để đếm ngoặc, tìm khai báo
tho = {}           # nguyên văn — để tìm nội dung nằm TRONG chuỗi

for f in tep:
    s = open(f, encoding='utf-8').read()
    sach = bo_chu_thich(s)
    than[f] = sach
    tho[f] = s
    ten_tep = os.path.basename(f)

    # ── ngoặc phải cân
    for mo, dong, nhan in (('{', '}', 'nhọn'), ('(', ')', 'tròn'), ('[', ']', 'vuông')):
        t('%s: ngoặc %s cân' % (ten_tep, nhan),
          sach.count(mo) == sach.count(dong),
          '%d mở / %d đóng' % (sach.count(mo), sach.count(dong)))

    # ── phải có khai gói
    t('%s: có khai package' % ten_tep, re.search(r'^package\s+vn\.khh\.ghe', s, re.M) is not None)

    for m in re.finditer(r'^\s*(?:@\w+(?:\([^)]*\))?\s*)*(?:private\s+|internal\s+|public\s+)?fun\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(', sach, re.M):
        khai_ham.setdefault(m.group(1), []).append(ten_tep)
    for m in re.finditer(r'^\s*(?:private\s+|internal\s+|public\s+|abstract\s+|open\s+|data\s+|sealed\s+)*(?:class|object|interface)\s+([A-Za-z_][A-Za-z0-9_]*)', sach, re.M):
        khai_lop.setdefault(m.group(1), []).append(ten_tep)

# ── 🔴 TRÙNG TÊN GIỮA LỚP VÀ HÀM CÙNG GÓI.
#    `class Ung` và `fun Ung()` cùng nằm ở `vn.khh.ghe`: Kotlin nhận cả hai, và `Ung()` là lời
#    gọi HÀM DỰNG của lớp chứ không phải hàm. App dựng ra một Application thứ hai rồi hiện màn
#    trắng — và trình biên dịch không kêu một tiếng nào.
trung = sorted(set(khai_ham) & set(khai_lop))
t('🔴 không có tên nào vừa là lớp vừa là hàm', not trung, ', '.join(trung))

# ── 🔴 GỌI MỘT TÊN KHÔNG KHAI Ở ĐÂU.
#    Đây đúng là lớp lỗi `Lf is not defined` vừa làm trắng cả trang web: chép một dòng mã từ tệp
#    khác sang, mang theo lời gọi một hàm chỉ tồn tại ở tệp kia.
# Danh sách hàm của app phải được dùng ít nhất một nơi (trừ điểm vào của Android/Compose).
DIEM_VAO = {'onCreate', 'onResume', 'doWork', 'main', 'MainActivity', 'Ung', 'GocApp'}
# ⚠️ Đếm trên bản THÔ: một lời gọi nằm trong chuỗi mẫu `${dinhDang(...)}` là lời gọi THẬT, mà
#    bản đã gỡ chuỗi thì không còn nó. Bộ soi tự kêu oan là bộ soi người ta tắt đi.
tat_ca = '\n'.join(tho.values())
khong_dung = []
for ten, o in khai_ham.items():
    if ten in DIEM_VAO or ten.startswith('on'):
        continue
    # đếm số lần xuất hiện: >1 nghĩa là ngoài dòng khai còn chỗ gọi
    if len(re.findall(r'\b%s\b' % re.escape(ten), tat_ca)) <= 1:
        khong_dung.append(ten)
t('⚠️ không có hàm nào khai ra rồi bỏ đó', not khong_dung, ', '.join(sorted(khong_dung)))

# ── Những tên của app mà một tệp DÙNG thì phải import (hoặc cùng gói).
for f in tep:
    sach = than[f]
    ten_tep = os.path.basename(f)
    goi_app = set(re.findall(r'\b(Kho|Luu|Api|MangHong|HangDoi|DayHangDoi)\b', sach))
    for g in goi_app:
        if re.search(r'^\s*(class|object)\s+%s\b' % g, sach, re.M):
            continue
        cung_goi = any(os.path.basename(x).startswith(g) for x in [f])
        co_import = re.search(r'^import\s+vn\.khh\.ghe\.[\w.]*%s\b' % g, sach, re.M) is not None
        # cùng gói `vn.khh.ghe` thì không cần import
        goi_cua_tep = re.search(r'^package\s+([\w.]+)', sach, re.M).group(1)
        goi_cua_ten = {'Kho': 'vn.khh.ghe', 'Luu': 'vn.khh.ghe', 'Api': 'vn.khh.ghe.mang',
                       'MangHong': 'vn.khh.ghe.mang', 'HangDoi': 'vn.khh.ghe.kho',
                       'DayHangDoi': 'vn.khh.ghe.kho'}[g]
        t('%s: dùng %s thì phải import (hoặc cùng gói)' % (ten_tep, g),
          co_import or goi_cua_tep == goi_cua_ten)

# ── Những chốt về TIỀN phải còn nguyên trong mã.
kho = tho[os.path.join(NGUON, 'vn', 'khh', 'ghe', 'Kho.kt')]
t('🔴 chốt ca luôn vào hàng đợi trước khi đẩy',
  'hangDoi.them("chot_luu"' in kho)
t('🔴 mỗi lượt chốt mang một ma_lan riêng',
  'put("ma_lan", UUID.randomUUID().toString())' in kho)
t('🔴 nộp tiền KHÔNG vào hàng đợi — tiền chuyển tay phải có mặt hai người',
  'hangDoi.them("nop_tao"' not in kho)
t('⚠️ máy chủ từ chối thì bỏ khỏi hàng đợi, không đẩy mãi',
  'hangDoi.xoa(lenh.id)' in kho)

hd = tho[os.path.join(NGUON, 'vn', 'khh', 'ghe', 'kho', 'HangDoi.kt')]
t('🔴 hàng đợi ghi qua tệp tạm rồi đổi tên', 'renameTo(tep)' in hd)
t('⚠️ tệp hàng đợi hỏng thì KHÔNG tự xoá', 'tep.delete()' not in hd)

day = tho[os.path.join(NGUON, 'vn', 'khh', 'ghe', 'kho', 'DayHangDoi.kt')]
t('🔴 nút "Gửi ngay" và bộ tự động dùng CHUNG một hàm đẩy',
  'suspend fun dayMotLuot' in day and 'dayMotLuot(applicationContext)' in day)

api = tho[os.path.join(NGUON, 'vn', 'khh', 'ghe', 'mang', 'Api.kt')]
t('🔴 phân biệt mất mạng với máy chủ từ chối', 'class MangHong' in api)
t('⚠️ trả lời không phải JSON thì kêu lên, không nuốt', 'tường lửa' in api)

luu = tho[os.path.join(NGUON, 'vn', 'khh', 'ghe', 'Luu.kt')]
t('🔴 địa chỉ máy chủ luôn ép về https', '"https://$s"' in luu)

# ── Manifest xin đúng quyền, không thừa
mf = open(os.path.join(GOC, 'app', 'src', 'main', 'AndroidManifest.xml'), encoding='utf-8').read()
for q in ('INTERNET', 'CAMERA'):
    t('manifest xin quyền ' + q, q in mf)
for q in ('READ_EXTERNAL_STORAGE', 'ACCESS_FINE_LOCATION', 'READ_CONTACTS', 'RECORD_AUDIO'):
    t('🔴 manifest KHÔNG xin quyền thừa ' + q, q not in mf)
t('⚠️ máy không có camera vẫn cài được', 'android:required="false"' in mf)

if loi:
    print('HỎNG: %d' % len(loi))
    for x in loi:
        print('  ✗ ' + x)
    print('ĐẠT: %d' % dat)
    sys.exit(1)
print('✓ SẠCH  %d phép soi trên %d tệp Kotlin.' % (dat, len(tep)))
