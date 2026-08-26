#!/usr/bin/env python3
"""
Kiểm phần JavaScript của TRẠM CHẤM CÔNG (wordpress/vhcp-cham-cong/templates/tram.php).

VÌ SAO CÓ TỆP NÀY RIÊNG, không gộp vào kiem-tram.php
Bài kiểm bằng PHP soi được logic máy chủ, nhưng KHÔNG dịch nổi JavaScript. Mà trạm là trang
duy nhất trong cả hệ mà nhân viên mở HÀNG NGÀY và mở bằng điện thoại: một lỗi cú pháp ở đây
không hiện thông báo nào cả — trang tải xong, nút bấm không lên, và người ta đứng ở cửa hàng
bấm mãi rồi bỏ, không ai báo về vì "trang vẫn mở được".

Cũng quét lại đúng cái bẫy đã cắn mình ở app.html ngày 25/08/2026: một `var` bên trong hàm
trùng tên với một hàm toàn cục thì từ DÒNG ĐẦU của hàm đó, cái tên kia đã chết.

Chạy: python3 tools/test/kiem-tram-js.py
"""
import io, os, re, subprocess, sys, tempfile

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..')
TPL = os.path.join(GOC, 'wordpress', 'vhcp-cham-cong', 'templates', 'tram.php')

hong = 0
dat  = 0

def la(ten, dieu, chi_tiet=''):
    global hong, dat
    if dieu:
        dat += 1
        return
    print('  HỎNG %-56s %s' % (ten, chi_tiet))
    hong += 1

src = io.open(TPL, encoding='utf-8').read()
khoi = re.findall(r'<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>', src, re.S)
la('có khối <script> nhúng', len(khoi) == 1, 'tìm thấy %d' % len(khoi))

# Thay đoạn PHP nhúng bằng một giá trị thật, không xoá trắng: xoá trắng thì `var CFG = ;`
# và node báo lỗi cú pháp của BÀI KIỂM chứ không phải của trang.
js = khoi[0].replace('<?php echo wp_json_encode( $cfg ); ?>',
                     '{"cong":"http://x/cham-cong-online/","ver":"test"}')
la('không còn thẻ PHP nào sót trong JS', '<?php' not in js, js[js.find('<?php'):][:80])

print('— cú pháp —')
with tempfile.TemporaryDirectory() as d:
    f = os.path.join(d, 'tram.js')
    io.open(f, 'w', encoding='utf-8').write(js)
    r = subprocess.run(['node', '--check', f], capture_output=True, text=True)
    la('node dịch được', r.returncode == 0, r.stderr.strip()[:300])

print('— gọi tên chưa khai —')
# ============================================================================================
# 🔴 CHỐT NÀY SINH RA TỪ MỘT LỖI THẬT, VÀ LÀ LỖI NẶNG NHẤT TRONG CẢ ĐỢT.
#
# 25/08/2026: trang trạm đứng im ba lần liền. Nguyên nhân cuối cùng lộ ra là
#     Uncaught ReferenceError: thoiTheoGps is not defined
# Em sửa khối bản đồ bằng cách cắt từ mốc này tới mốc kia rồi thay nội dung mới — mà
# `thoiTheoGps` cùng bốn biến GPS_* nằm LỌT GIỮA hai mốc ấy. Chúng biến mất, còn năm chỗ gọi
# thì vẫn nguyên.
#
# `node --check` KHÔNG bắt được: gọi một tên chưa khai là cú pháp hợp lệ, lỗi chỉ nổ lúc chạy.
# Nên bài kiểm vẫn xanh, bản .zip vẫn dựng, và người dùng nhận một trang chết lặng.
#
# Đây là lần thứ HAI trong ngày cùng một kiểu cắt nhầm (lần trước mất bốn hàm của VHCC_Mat).
# Cắt theo mốc là thao tác nguy hiểm; chốt này là cái lưới hứng bên dưới.
# ============================================================================================

# Tên có sẵn trong trình duyệt hoặc trong JavaScript, không cần khai.
CO_SAN = set("""
    fetch setTimeout clearTimeout setInterval clearInterval requestAnimationFrame
    parseInt parseFloat isNaN isFinite encodeURIComponent decodeURIComponent
    Number String Boolean Array Object Math JSON Date Promise Error RegExp
    Image FileReader Blob File FormData URL AbortController Event CustomEvent
    alert confirm prompt console document window navigator performance location
    localStorage sessionStorage history screen getComputedStyle btoa atob
    Map Set WeakMap WeakSet Symbol Proxy Reflect BigInt
    if for while switch catch return typeof function
""".split())

def ten_khai(ma):
    """Mọi tên được khai trong tệp: hàm, var, tham số hàm, biến của catch."""
    ra = set()
    ra |= set(re.findall(r'\bfunction\s+([A-Za-z_$][\w$]*)', ma))
    # var a = 1, b = 2  -> bắt cả tên sau dấu phẩy
    for m in re.finditer(r'\bvar\s+([^;\n]+)', ma):
        for phan in m.group(1).split(','):
            g = re.match(r'\s*([A-Za-z_$][\w$]*)', phan)
            if g:
                ra.add(g.group(1))
    for m in re.finditer(r'\bfunction\s*[A-Za-z_$\w$]*\s*\(([^)]*)\)', ma):
        for phan in m.group(1).split(','):
            g = re.match(r'\s*([A-Za-z_$][\w$]*)', phan)
            if g:
                ra.add(g.group(1))
    ra |= set(re.findall(r'\bcatch\s*\(\s*([A-Za-z_$][\w$]*)', ma))
    return ra

def ten_goi(ma):
    """Mọi lời gọi `ten(` — bỏ qua `.ten(` (phương thức) và `function ten(`."""
    ra = {}
    for m in re.finditer(r'(^|[^\w$.])([A-Za-z_$][\w$]*)\s*\(', ma, re.M):
        ten = m.group(2)
        truoc = ma[max(0, m.start() - 12):m.start() + 1]
        if re.search(r'\bfunction\s*$', truoc):
            continue
        ra.setdefault(ten, ma[:m.start()].count('\n') + 1)
    return ra

# Bỏ chú thích và chuỗi trước khi soi — một cái tên nhắc trong lời giải thích không phải lời gọi.
sach = re.sub(r'/\*.*?\*/', '', js, flags=re.S)
sach = re.sub(r'^\s*//.*$', '', sach, flags=re.M)
sach = re.sub(r"'(?:[^'\\\n]|\\.)*'", "''", sach)
sach = re.sub(r'"(?:[^"\\\n]|\\.)*"', '""', sach)

da_khai = ten_khai(sach) | CO_SAN
thieu = [(t, d) for t, d in ten_goi(sach).items() if t not in da_khai]
la('mọi hàm được gọi đều có khai', not thieu,
   '; '.join('dòng %d: %s()' % (d, t) for t, d in sorted(thieu, key=lambda x: x[1])[:6]))

# Và phép thử NGƯỢC: bộ dò trên phải bắt được khi một hàm biến mất — không thì nó chỉ là một
# chốt luôn xanh, y như `node --check` đã luôn xanh suốt lúc thiếu `thoiTheoGps`.
gia = sach.replace('function veViTri(', 'function veViTri_DA_XOA(', 1)
la('bộ dò bắt được khi một hàm biến mất',
   'veViTri' in [t for t in ten_goi(gia) if t not in (ten_khai(gia) | CO_SAN)])

print('— biến che mất hàm cùng tên —')
ham = set(re.findall(r'^function ([A-Za-z_$][\w$]*)\s*\(', js, re.M))
la('đọc được danh sách hàm toàn cục', len(ham) >= 10, 'đếm được %d' % len(ham))
trung = []
for m in re.finditer(r'^(\s+)var\s+([A-Za-z_$][\w$]*)\s*=', js, re.M):
    thut, ten = m.group(1), m.group(2)
    if len(thut) > 0 and ten in ham:
        trung.append((js[:m.start()].count('\n') + 1, ten))
la('không biến nội bộ nào che mất hàm toàn cục', not trung,
   '; '.join('dòng %d: var %s' % t for t in trung[:5]))

# 🔴 Cùng họ: HAI `function` toàn cục cùng tên. JavaScript không báo gì, cái SAU thắng, cái
#    TRƯỚC chết. Đã cắn ở app.html ngày 25/08/2026 (`_ymd` khai hai lần) và chết im lặng.
dem = {}
for m in re.finditer(r'^function ([A-Za-z_$][\w$]*)\s*\(', js, re.M):
    dem.setdefault(m.group(1), []).append(js[:m.start()].count('\n') + 1)
trung_ham = {k: v for k, v in dem.items() if len(v) > 1}
la('không hàm toàn cục nào bị khai hai lần', not trung_ham,
   '; '.join('%s ở dòng %s' % (k, v) for k, v in list(trung_ham.items())[:5]))

print('— mọi nút đều có người nghe —')
# Nút vẽ ra mà quên gài sự kiện là nút bấm không xảy ra gì, và trang không báo lỗi.
nut = set(re.findall(r'<button id="([A-Za-z0-9_]+)"', src))
o_nhap = set(re.findall(r'<input id="([A-Za-z0-9_]+)"', src))
nghe = set(re.findall(r"el\('([A-Za-z0-9_]+)'\)\.addEventListener", js))
la('không nút nào bị bỏ quên', nut <= nghe, 'thiếu: %s' % sorted(nut - nghe))
# Ô nhập cũng được gài (Enter để gửi) nên tính cả vào; còn gài cho một id KHÔNG tồn tại thì
# addEventListener ném lỗi và cả khối script chết từ dòng đó — không nút nào chạy nữa.
la('không gài sự kiện cho phần tử không tồn tại', nghe <= (nut | o_nhap),
   'không có: %s' % sorted(nghe - nut - o_nhap))

print('— mọi id được gọi đều có thật —')
# el('x') trỏ vào một id không có trong HTML là `null.classList` -> hàm chết giữa chừng.
co_id = set(re.findall(r'\sid="([A-Za-z0-9_]+)"', src))
goi_id = set(re.findall(r"el\('([A-Za-z0-9_]+)'\)", js)) | set(re.findall(r"hien\('([A-Za-z0-9_]+)'", js))
# Vài id do chính JS dựng ra lúc chạy (ô chọn cơ sở / nhiệm vụ), nên khai ở đây.
dung_luc_chay = {'oCS', 'oNV'}
thieu = goi_id - co_id - dung_luc_chay
la('không gọi id nào không có trong HTML', not thieu, 'thiếu: %s' % sorted(thieu))
for x in sorted(dung_luc_chay):
    la('id dựng lúc chạy "%s" có chỗ dựng thật' % x, ('id="%s"' % x) in js)
# ...và phải được đọc bằng lối chịu được null, vì lúc chưa dựng thì nó KHÔNG có.
la('id dựng lúc chạy được đọc chịu null', 'var oCS = el(' in js and 'oCS ? oCS.value' in js)

print('— bốn ràng buộc đỏ —')
la('RB1 · lấy mốc giờ từ máy chủ', "goi('gio'" in js)
la('RB1 · đọc mốc bằng getUTC* (mốc đã cộng lệch múi giờ ở máy chủ)',
   'getUTCHours' in js and 'getUTCDate' in js)
la('RB2 · thu nhỏ về 720px', re.search(r'RONG_ANH\s*=\s*720', js) is not None
   and re.search(r'Math\.min\(\s*RONG_ANH\s*,', js) is not None)
la('RB3 · dựng màn chọn cơ sở sau khi chụp', 'veManChon()' in js)
la('RB4 · có cờ chặn bấm lại', 'DANG_LUU' in js)

print('— không rò HTML —')
# Tên cơ sở / họ tên đi thẳng vào innerHTML là một dấu nháy trong tên cũng vỡ bảng.
la('có hàm thoát HTML', 'function esc(' in js)
tho = re.findall(r"innerHTML\s*=\s*'[^']*'\s*\+\s*(?!esc\()([A-Za-z_$][\w$.]*)", js)
la('không ghép thẳng biến vào innerHTML', not tho, str(tho[:5]))

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép trên tram.php' % dat)
