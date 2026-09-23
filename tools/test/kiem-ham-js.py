#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
GỌI MỘT HÀM KHÔNG TỒN TẠI — `node --check` KHÔNG BẮT, MÀ MÀN THÌ TRẮNG.

16/09/2026: dọn một hàm chết trong `doanh-thu.js`, lát cắt ăn lẹm sang hàm `theTreo()` nằm kẹp
giữa chú thích và thân của hàm bị xoá. `node --check` vẫn xanh (cú pháp có sai đâu), bộ thử PHP
vẫn xanh (nó không chạm tới JS), và bản ấy đi thẳng lên site: anh Thắng mở tab Đối soát ra chỉ
thấy một dòng chữ "theTreo is not defined".

Lỗi ấy có một dấu vết tĩnh rất rõ: một lời gọi `tên(` mà trong tệp không có `function tên(`.
Bài này soi đúng chuyện đó cho mọi tệp .js của plugin — rẻ, và bắt được nguyên một lớp lỗi mà
cả `node --check` lẫn bộ thử PHP đều mù.

⚠️ CHỈ SOI HÀM TỰ ĐỊNH NGHĨA TRONG TỆP. Hàm sẵn của trình duyệt và phương thức của đối tượng
   (`a.b()`) đều bỏ qua — soi luôn cả chúng thì danh sách trắng dài vô tận và không ai duy trì nổi.

Chạy: python3 tools/test/kiem-ham-js.py
"""
import re
import sys
from pathlib import Path

GOC = Path(__file__).resolve().parents[2]
DS_TEP = sorted((GOC / 'wordpress' / 'khh-doanh-thu' / 'assets').glob('*.js'))

# Hàm sẵn có / từ khoá — gọi tới thì không cần định nghĩa trong tệp.
SAN = {
    'if', 'for', 'while', 'switch', 'catch', 'return', 'typeof', 'function', 'new', 'delete',
    'void', 'in', 'of', 'do', 'else', 'try', 'throw', 'case', 'break', 'continue',
    'Array', 'Object', 'String', 'Number', 'Boolean', 'Date', 'Math', 'JSON', 'RegExp', 'Error',
    'Promise', 'Set', 'Map', 'Intl', 'FormData', 'fetch', 'parseInt', 'parseFloat', 'isNaN',
    'setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'encodeURIComponent',
    'decodeURIComponent', 'alert', 'confirm', 'require', 'document', 'window', 'console',
}

def soi(duong):
    """
    Tìm những lời gọi `tên(` mà trong tệp không có `function tên(`.

    ⚠️ KHÔNG BÓC CHUỖI VÀ CHÚ THÍCH NỮA. Đã thử hai lối và cả hai đều sai theo kiểu tệ nhất — báo
       hỏng những hàm đang chạy tốt:
         · `re.sub` nối tiếp: `//` trong `'https://…'` bị coi là mở chú thích, nuốt luôn dấu nháy
           đóng, và mọi chuỗi phía sau lệch pha;
         · máy quét từng ký tự: dấu `"` nằm trong một biểu thức chính quy (`/[&<>"]/g`) bị coi là
           mở chuỗi, nuốt cả vùng mã phía sau.
       Bóc cho đúng thì phải hiểu cả biểu thức chính quy, tức là viết gần xong một bộ phân tích
       JavaScript — quá đắt cho một phép soi.

       Nên đọc thẳng mã thô. Tên hàm chỉ nhận ký tự ASCII, nên chữ tiếng Việt trong chuỗi không
       bao giờ trông giống lời gọi; còn chữ tiếng Anh trong chuỗi thì hầu như không có dạng
       `tên(` dính liền. Đổi lại, phép soi này CHỈ ĐƯỢC PHÉP BÁO KHI CHẮC, nên chỗ nào ngờ vực
       thì bỏ qua — thà sót còn hơn báo oan, vì một bài kiểm hay báo oan là bài kiểm không ai chạy.
    """
    ma = duong.read_text(encoding='utf-8')
    ten = r'[A-Za-z_$][A-Za-z0-9_$]*'

    dinh_nghia = set(re.findall(r'\bfunction\s+(%s)\s*\(' % ten, ma))
    dinh_nghia |= set(re.findall(r'\b(?:var|let|const)\s+(%s)\s*=\s*function\b' % ten, ma))
    dinh_nghia |= set(re.findall(r'\b(?:var|let|const)\s+(%s)\s*=\s*\([^)]*\)\s*=>' % ten, ma))
    dinh_nghia |= set(re.findall(r'\b(?:var|let|const)\s+(%s)\s*=\s*%s\s*=>' % (ten, ten), ma))

    # Biến thường (có thể đang giữ một hàm) và tham số — không đủ chắc để coi là lời gọi sai.
    bo_qua = set(re.findall(r'\b(?:var|let|const)\s+(%s)' % ten, ma))
    bo_qua |= set(re.findall(r'\bfunction\s*%s?\s*\(([^)]*)\)' % ten, ma) and
                  [t.strip() for nhom in re.findall(r'\bfunction\s*%s?\s*\(([^)]*)\)' % ten, ma)
                   for t in nhom.split(',') if t.strip()])

    vung_chu_thich = do_chu_thich(ma)
    thieu = {}
    for khop in re.finditer(r'(?<![.\w$])(%s)\s*\(' % ten, ma):
        t = khop.group(1)
        if t in SAN or t in dinh_nghia or t in bo_qua:
            continue
        if trong_chuoi(ma, khop.start()) or trong_chu_thich(ma, vung_chu_thich, khop.start()):
            continue
        thieu.setdefault(t, ma[:khop.start()].count('\n') + 1)
    return thieu


def do_chu_thich(ma):
    """
    Các vùng `/* … */` trong tệp, tìm thô — không nhìn xem nó có nằm trong chuỗi hay không.

    ⚠️ CỐ Ý THÔ, VÀ CỐ Ý SAI VỀ MỘT PHÍA. Nếu một chuỗi có chứa `/*` thì vùng "chú thích" bị đo
       rộng quá, và phép soi BỎ SÓT vài lời gọi. Còn nếu đo chặt hơn thì nó lại BÁO OAN những hàm
       đang chạy tốt — mà một bài kiểm hay báo oan thì lần sau không ai chạy nữa. Sót thì còn
       những phép khác và mắt người; báo oan thì mất luôn cả bài kiểm.
    """
    ra = []
    i = 0
    while True:
        a = ma.find('/*', i)
        if a < 0:
            break
        b = ma.find('*/', a + 2)
        if b < 0:
            ra.append((a, len(ma)))
            break
        ra.append((a, b + 2))
        i = b + 2
    return ra


def trong_chu_thich(ma, vung, vi_tri):
    for a, b in vung:
        if a <= vi_tri < b:
            return True
        if a > vi_tri:
            break
    # Chú thích một dòng: có `//` đứng trước trên cùng dòng.
    dau_dong = ma.rfind('\n', 0, vi_tri) + 1
    return '//' in ma[dau_dong:vi_tri]


def trong_chuoi(ma, vi_tri):
    """
    Chỗ này có đang nằm trong một chuỗi không — đếm dấu nháy từ đầu dòng.

    Mã trong tệp này dựng HTML bằng chuỗi, nên chữ tiếng Anh trong đó hay có dạng `MoMo (` hay
    `var(--xau)` — trông y hệt một lời gọi. Đếm nháy lẻ thì đang ở trong chuỗi: thô, nhưng đúng
    cho lối viết một chuỗi không vắt qua nhiều dòng, và đó chính là lối viết của tệp này.
    """
    dau_dong = ma.rfind('\n', 0, vi_tri) + 1
    truoc = ma[dau_dong:vi_tri]
    truoc = truoc.replace("\\'", '').replace('\\"', '')
    return (truoc.count("'") % 2 == 1) or (truoc.count('"') % 2 == 1)


def main():
    if not DS_TEP:
        print('Không thấy tệp .js nào để soi.')
        return 1
    hong = 0
    for tep in DS_TEP:
        thieu = soi(tep)
        if thieu:
            hong += len(thieu)
            print('✗ %s' % tep.relative_to(GOC))
            for ten, dong in sorted(thieu.items(), key=lambda x: x[1]):
                print('   dòng %-5d gọi %s() — không thấy định nghĩa trong tệp' % (dong, ten))
    if hong:
        print('\n✗ HỎNG — %d lời gọi tới hàm không tồn tại.' % hong)
        return 1
    print('\n✓ SẠCH — %d tệp JS, mọi lời gọi đều có hàm thật.' % len(DS_TEP))
    return 0

sys.exit(main())
