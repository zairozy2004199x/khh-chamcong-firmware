#!/usr/bin/env python3
"""
Kiểm phần JavaScript nhúng trong templates/app.html của plugin chi phí.

VÌ SAO CÓ TỆP NÀY
Ngày 25/08/2026 bảng "Người dùng & Phân quyền" hiện ra TRỐNG TRƠN, anh Thắng báo
"hệ thống phân quyền cho từng nhân viên bị mất". Dữ liệu còn nguyên trên máy chủ.
Thủ phạm là một dòng:

    var _laAdmin = (CURUSER && CURUSER.role === 'Admin');

đặt bên trong renderUsers(), TRÙNG TÊN với hàm toàn cục _laAdmin(). JavaScript kéo
mọi khai báo `var` lên đầu hàm, nên ngay từ dòng đầu tiên cái tên đó đã là một biến
rỗng chứ không còn là hàm. Lời gọi _laAdmin() nằm phía trên ném TypeError, hàm chết
tại chỗ, và lệnh vẽ bảng bên dưới không bao giờ chạy.

Không có phép thử nào bắt được vì app.html là một tệp HTML 5000 dòng, không ai dịch
nó cả. Tệp này làm đúng việc đó: tách khối <script> ra, bắt node kiểm cú pháp, và
quét đúng cái bẫy đã cắn mình.

Chạy: python3 tools/test/kiem-app-js.py
"""
import io, os, re, subprocess, sys, tempfile

GOC = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..')
HTML = os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'templates', 'app.html')

hong = 0
dat  = 0

def la(ten, dieu, chi_tiet=''):
    global hong, dat
    if dieu:
        dat += 1
        return
    print('  HỎNG %-56s %s' % (ten, chi_tiet))
    hong += 1

src = io.open(HTML, encoding='utf-8').read()
khoi = re.findall(r'<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>', src, re.S)

print('— cú pháp —')
la('có ít nhất một khối <script> nhúng', len(khoi) >= 1, 'tìm thấy %d' % len(khoi))
with tempfile.TemporaryDirectory() as d:
    for i, b in enumerate(khoi):
        f = os.path.join(d, 'k%d.js' % i)
        io.open(f, 'w', encoding='utf-8').write(b)
        r = subprocess.run(['node', '--check', f], capture_output=True, text=True)
        la('khối %d dịch được' % i, r.returncode == 0, r.stderr.strip()[:200])

# ---------------------------------------------------------------- bẫy đã cắn mình
print('— biến che mất hàm cùng tên —')
ham = set(re.findall(r'^  function ([A-Za-z_$][\w$]*)\s*\(', src, re.M))
la('đọc được danh sách hàm toàn cục', len(ham) > 100, 'đếm được %d' % len(ham))

trung = []
for m in re.finditer(r'^(\s+)var\s+([A-Za-z_$][\w$]*)\s*=', src, re.M):
    thut, ten = m.group(1), m.group(2)
    if len(thut) > 2 and ten in ham:
        trung.append((src[:m.start()].count('\n') + 1, ten))

# 🔴 KHÔNG ĐƯỢC PHÉP CÓ CÁI NÀO. `var` bên trong hàm mà trùng tên một hàm toàn cục là
#    từ dòng đầu tiên của hàm đó, cái tên kia đã chết. Lỗi chỉ lộ ra lúc chạy, và lộ ra
#    dưới dạng "một bảng trắng" chứ không phải một dòng báo lỗi.
la('không biến nội bộ nào che mất hàm toàn cục', not trung,
   '; '.join('dòng %d: var %s' % t for t in trung[:5]))

# Chốt riêng đúng chỗ đã hỏng, phòng khi ai đó đổi lại.
m = re.search(r'function renderUsers\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy renderUsers()', m is not None)
if m:
    than = m.group(1)
    la('renderUsers KHÔNG khai var _laAdmin', 'var _laAdmin' not in than,
       'khai lại là bảng người dùng trắng trở lại')
    la('renderUsers vẫn dùng hàm _laAdmin()', '_laAdmin()' in than)
    la('renderUsers có vẽ cfgUserBody', "el('cfgUserBody').innerHTML" in than)

# ---------------------------------------------------------------- chốt chặn xóa trắng
print('— chốt chặn lưu danh sách rỗng —')
la('có cờ phân biệt tải hỏng với rỗng', 'CFGUSERS_LOI' in src)
la('lưu 0 người phải hỏi xác nhận', 'usersXoaHet' in src)
m2 = re.search(r'function saveCfgUsers\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy saveCfgUsers()', m2 is not None)
if m2:
    la('saveCfgUsers chặn khi danh sách rỗng', 'if(!data.length)' in m2.group(1))

# ---------------------------------------------------------------- cơ sở lạ
print('— cơ sở lạ —')
la('có hộp cảnh báo cơ sở lạ', 'cosoLaBox' in src)
la('renderCosoBody có gọi doCoSoLa()', re.search(r'_csLock\(true\);\s*\n\s*doCoSoLa\(\);', src) is not None)
la('có đường gộp / đổi tên cơ sở', 'doiTenCoSo(' in src)

# ---------------------------------------------------------------- bộ phận Văn phòng
print('— bộ phận Văn phòng & kỳ tự do —')
m3 = re.search(r"var BOPHAN_LIST=\[(.*?)\];", src)
la('đọc được BOPHAN_LIST', m3 is not None)
if m3:
    bp = [x.strip().strip("'") for x in m3.group(1).split(',')]
    la('có bộ phận Văn phòng', 'Văn phòng' in bp, str(bp))
    la('vẫn giữ đủ 5 bộ phận cũ',
       all(x in bp for x in ['Cơ sở', 'Kỹ thuật', 'Marketing', 'Công tác', 'Setup']), str(bp))
la('Văn phòng được chọn kỳ tự do', "BP_KY_TU_DO=['Văn phòng']" in src)
la('modal có ô khoảng ngày', 'ndTuDoBox' in src and 'ndTuNgay' in src and 'ndDenNgay' in src)

m4 = re.search(r'function submitNewDon\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy submitNewDon()', m4 is not None)
if m4:
    t = m4.group(1)
    la('nhận nhánh kỳ tự do', "__tudo__" in t)
    # 🔴 Ngày kết thúc trước ngày bắt đầu thì chuỗi kỳ sinh ra ngược và _kyVal() đọc sai mốc
    #    -> đơn xếp nhầm chỗ trong mọi báo cáo. Phải chặn.
    la('chặn ngày kết thúc trước ngày bắt đầu', 'dz.getTime()<da.getTime()' in t)
    la('dùng lại đúng khuôn chuỗi kỳ cũ', '_kyRange(da,dz)' in t)

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép trên app.html' % dat)
