#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SỬA MỘT CON SỐ PHÍ GÕ SAI PHẢI LÀ MỘT CÚ BẤM, VÀ PHÍ CHIA TẠM PHẢI NÓI LÀ TẠM.

18/09/2026, hai câu của anh Thắng trong cùng một buổi:

  · *"thêm nút sửa lại phí đi"* — trước đó muốn chữa "68.866" lỡ vào sổ thành 69đ thì chỉ còn
    cách Xoá dòng rồi gõ lại từ đầu, vì hệ coi chính khoảng ấy là một lượt chồng ngày.
  · *"Đã có phí sao không chia cho cửa hàng luôn đi"* — phí đã nhập mà cột Phí vẫn trống, do
    bảng ghép `mã cửa hàng -> tài khoản` chưa học được gì (sao kê nạp từ trước khi có ô mã).

Bài này canh phần MÀN HÌNH của hai việc ấy — phần máy chủ đã có `kiem-momo-phi.php`. Đều là
chuyện `node --check` xanh mà người dùng vẫn kẹt:

  1. Mỗi dòng phí đã nhập phải có nút Sửa, và nút ấy phải mang đủ bốn thứ để đổ lại vào ô nhập
     (tài khoản, từ, đến, số). Thiếu một là bấm Sửa ra một biểu mẫu điền dở.
  2. Số trên nút Sửa phải là số TRẦN. Đổ "62.447" vào ô rồi lưu lại là thêm một vòng đọc-ghi
     dấu chấm — đúng cái vòng đã làm mất 68.797đ.
  3. Phải có bộ xử lý cho nút ấy, và nó phải CUỘN ô nhập vào tầm mắt: bảng đã nhập nằm DƯỚI ô
     nhập, nên bấm Sửa ở dòng cuối mà không cuộn thì màn hình y như không có gì xảy ra.
  4. Khối "Đang chia TẠM" phải có mặt và phải đọc từ `chia_tam` của máy chủ. Chia tạm mà không
     nói là tạm thì cột Phí trông y hệt cột đã ghép đàng hoàng.

⚠️ QUÉT TRÊN MÃ ĐÃ BÓC CHÚ THÍCH. Đã hai lần bài kiểu này xanh nhầm vì từ khoá cần tìm nằm
   trong chính đoạn chú thích giải thích nó.

Chạy: python3 tools/test/kiem-sua-phi-momo.py
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


def boc(s):
    """Bỏ chú thích /* */ và // — xem ⚠️ trên."""
    s = re.sub(r'/\*.*?\*/', ' ', s, flags=re.S)
    return re.sub(r'(?m)^\s*//.*$', ' ', s)


if not TEP.exists():
    sys.exit('KHÔNG thấy %s' % TEP)
ma = boc(TEP.read_text(encoding='utf-8'))

# ---- 1. nút Sửa trên từng dòng phí, mang đủ bốn thứ ----
t('moi dong phi co nut Sua', 'data-phi-sua' in ma)
t('nut Xoa van con (Sua KHONG thay the Xoa)', 'data-phi-xoa' in ma)
# ⚠️ Chỉ xét ĐOẠN VẼ RA NÚT, không xét cả tệp: `data-phi-den` còn xuất hiện trong bộ xử lý
#    (`getAttribute('data-phi-den')`), nên tìm cả tệp là bỏ mất trường hợp nút thiếu thuộc tính
#    mà bộ xử lý vẫn đòi — đúng trường hợp bấm Sửa ra biểu mẫu điền dở. Đã thử: bỏ
#    `data-phi-den` khỏi nút thì phép quét cả tệp vẫn xanh.
m = re.search(r'data-phi-sua="1"(.*?)>Sửa<', ma, re.S)
t('tim thay doan ve ra nut Sua', m is not None)
nut = m.group(1) if m else ''
for thuoc in ('data-phi-tk', 'data-phi-tu', 'data-phi-den', 'data-phi-so'):
    t('nut Sua mang %s' % thuoc, thuoc in nut)

# ---- 2. số trên nút Sửa là số trần ----
m = re.search(r"data-phi-so=\"' \+ ([^+]+)", nut)
t('tim thay cho dat data-phi-so', m is not None)
if m:
    t('data-phi-so la so TRAN, khong qua ham tien()/dinh dang',
      'tien(' not in m.group(1) and 'Math.round' in m.group(1))

# ---- 3. bộ xử lý nút Sửa ----
m = re.search(r"closest\('\[data-phi-sua\]'\)(.*?)\n    \}\);", ma, re.S)
t('co bo xu ly cho nut Sua', m is not None)
if m:
    than = m.group(1)
    for n in ('tk', 'tu', 'den', 'so'):
        t('bo xu ly do lai o [data-phi="%s"]' % n, ("'%s'" % n) in than)
    t('bo xu ly CUON o nhap vao tam mat', 'scrollIntoView' in than)
    t('bo xu ly boi san so cu de go de', '.select()' in than)
    t('bo xu ly KHONG tu goi may chu (chi do lai bieu mau)',
      "api(" not in than)

# ---- 4. khối "Đang chia TẠM" ----
t('man hinh doc chia_tam tu may chu', 'chia_tam' in ma)
m = re.search(r'var chiaTam\s*=(.*?);', ma, re.S)
t('co bien chiaTam', m is not None)
if m:
    t('chiaTam lay tu momo_phi.chia_tam', 'chia_tam' in m.group(1))
t('co cau "Dang chia TAM" tren man', 'Đang chia TẠM' in ma)
t('cau chia tam co chi duong nap lai sao ke kem ma tai khoan',
  re.search(r'Đang chia TẠM.*?Sao kê MoMo', ma, re.S) is not None)

# ---- 5. nút Xoá: id phải đi trên ĐƯỜNG DẪN, và kết quả phải được xem ----
# 🔴 *"bấm xóa mà không xóa được"* (18/09/2026), và không câu báo nào. Màn hình gửi `id` trong
#    thân multipart của một yêu cầu DELETE; PHP chỉ tự bóc thân multipart cho POST, còn
#    WP_REST_Request chỉ bóc JSON và form-urlencoded. `id` tới máy chủ là rỗng -> xoá hàng số 0
#    -> `xong:false` kèm mã 200 -> màn hình vẽ lại y như cũ.
m = re.search(r"closest\('\[data-phi-xoa\]'\)(.*?)\n    \}\);", ma, re.S)
t('co bo xu ly cho nut Xoa', m is not None)
if m:
    than = m.group(1)
    mg = re.search(r"api\((.*?)\)\s*\n?", than, re.S)
    t('bo xu ly Xoa co goi api()', mg is not None)
    t('🔴 id di tren DUONG DAN (momo-phi?id=...)', "momo-phi?id=" in than)
    t('🔴 KHONG gui FormData trong than mot yeu cau DELETE',
      'FormData' not in than and 'body:' not in than)
    t('van la phuong thuc DELETE', "'DELETE'" in than)
    t('id duoc ma hoa truoc khi ghep vao duong dan', 'encodeURIComponent' in than)
    t('🔴 co XEM ket qua may chu tra ve (khong bo qua)', re.search(r'\.xong\b', than) is not None)
    t('xoa hut thi KEU len (nem loi), khong im lang', 'throw' in than)
    t('xoa duoc thi nap lai phan Doi soat', 'taiDoiSoat' in than)
    t('van hoi lai mot cau truoc khi xoa', 'confirm' in than)

if hong:
    print('\n✗ HỎNG %d phép (đạt %d):' % (len(hong), dat))
    for h in hong:
        print('   · 🔴 %s' % h)
    sys.exit(1)
print('\n✓ SẠCH — %d phép: nút Sửa đổ lại được biểu mẫu, nút Xoá xoá thật, chia tạm thì nói là tạm.' % dat)
