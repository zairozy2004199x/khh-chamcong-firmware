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

# ---------------------------------------------------------------- hộp chọn cơ sở
print('— hộp chọn cơ sở —')
# 🔴 Tên thường gọi nhiều khi là tên MẢNG chứ không phải tên gian ("EVENT FZ MN" không gợi ra
#    "ADV Go An Lạc"). Nhãn phải kèm tên MISA + mã đơn vị, và phải lọc được.
la('có hàm dựng nhãn cơ sở', 'function _csNhan(' in src)
la('có hàm tách phần phụ', 'function _csPhu(' in src)
m5 = re.search(r'function _csPhu\(ten\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy _csPhu()', m5 is not None)
if m5:
    t5 = m5.group(1)
    # Kiểm ĐÚNG chỗ ghép vào nhãn, không phải chỗ đọc biến ra. Chỉ kiểm 'tenMisa' in t5 là
    # xoá hẳn p.push(tm) vẫn xanh — phép thử đó không bắt được gì.
    la('lấy tên theo MISA từ cấu hình', 'x.tenMisa' in t5)
    la('GHÉP tên MISA vào nhãn', 'p.push(tm)' in t5)
    la('lấy mã đơn vị từ cấu hình', 'x.maDonVi' in t5)
    la('GHÉP mã đơn vị vào nhãn', 'p.push(md)' in t5)
    la('nối bằng dấu chấm giữa', "p.join(' · ')" in t5)
    # Không có dòng cấu hình cho tên đó (cơ sở lạ) thì trả nguyên tên, không được rơi ra rỗng.
    # Không có dòng cấu hình cho tên đó (cơ sở lạ) -> phần phụ rỗng, và _csNhan trả nguyên tên.
    la('cơ sở lạ: phần phụ rỗng', "if(!x) return '';" in t5)
    la('cơ sở lạ: nhãn vẫn là chính tên đó', "return ph ? (ten+' · '+ph) : String(ten);" in src)
la('có ô lọc trong hộp chọn', 'cs-tim' in src and 'function _csTim(' in src)
la('lọc bỏ dấu được', 'function _bd(' in src)

m6 = re.search(r'function _cosoSel\(v\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy _cosoSel()', m6 is not None)
if m6:
    t6 = m6.group(1)
    # ⚠️ Giá trị lưu PHẢI vẫn là tên thường gọi. Đổi sang nhãn là mọi dòng phân quyền đang có
    #    trỏ vào một cái tên không còn tồn tại.
    la('giá trị <option> vẫn là tên thường gọi', "'<option value=\"'+esc(c)+'\"'" in t6)
    la('chuỗi lọc dùng _csNhan (đủ cả ba phần)', '_csNhan(c)' in t6)
    # 🔴 TÊN THƯỜNG GỌI LÀ TÊN CHÍNH — anh Thắng chốt 25/08/2026. Phần MISA/mã đứng sau, chữ mờ.
    la('có tách phần phụ ra khỏi tên chính', 'function _csPhu(' in src)
    la('dòng vẽ tên thường gọi trước', "'><span>'+esc(c)+" in t6)
    # 🔴🔴 CHỖ SUÝT CHẾT NGƯỜI: viết lại dòng <input> mà rơi mất ' checked' thì mọi cơ sở ĐÃ GÁN
    #     hiện ra KHÔNG tích. Người ta bấm Xong là _csDone ghi lại "không chọn cơ sở nào" —
    #     phân quyền cơ sở của cả bảng bay sạch, không lỗi, không hỏi. Em làm rơi đúng lần này.
    la('CHECKBOX GIỮ TRẠNG THÁI ĐÃ TÍCH', "sel.indexOf(c)>=0?' checked':''" in t6)
    la('  chỉ đúng MỘT chỗ dựng checked', t6.count("sel.indexOf(c)>=0?' checked':''") == 1)

m7 = re.search(r'function _csAll\(btn, on\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy _csAll()', m7 is not None)
if m7:
    # Đang lọc mà "Chọn hết" áp cả danh sách là tích/bỏ một đống cơ sở không hề nhìn thấy.
    la('Chọn hết/Bỏ hết chỉ áp dòng đang thấy', "l.style.display==='none'" in m7.group(1))

# ---------------------------------------------------------------- ai vào tab Dự án
print('— luật bộ phận: tuần vs dự án —')
# 🔴 Anh Thắng chốt 25/08/2026:
#      Nhân viên Cơ sở     -> CHỈ "Tuần · cơ sở"
#      Nhân viên Văn phòng -> CẢ HAI
#    Cách lên đơn ở tab Dự án y hệt bên cơ sở; khác duy nhất ở chỗ bộ phận nào được vào.
m8 = re.search(r"var BP_VAO_DUAN=\[(.*?)\];", src)
la('có khai BP_VAO_DUAN', m8 is not None)
if m8:
    vd = [x.strip().strip("'") for x in m8.group(1).split(',')]
    la('Văn phòng vào được tab Dự án', 'Văn phòng' in vd, str(vd))
    la('Kỹ thuật vẫn vào được tab Dự án', 'Kỹ thuật' in vd, str(vd))
    la('Cơ sở KHÔNG vào tab Dự án', 'Cơ sở' not in vd, str(vd))
la('quyền tab Dự án tra theo BP_VAO_DUAN', 'BP_VAO_DUAN.indexOf(bp)>=0' in src)
# Nút "Dự án · gian thi công" nằm TRONG trang chứ không trên hàng tab -> phải ẩn riêng,
# không thì người không có quyền bấm vào rồi ăn trang trắng.
la('ẩn nút GOM THEO khi không có quyền',
   '[data-dcsw="duan"]' in src and 'b.style.display=vis.duan' in src)

m9 = re.search(r'function _kyTuDo\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy _kyTuDo()', m9 is not None)
if m9:
    t9 = m9.group(1)
    la('vai khác Nhân viên vẫn được kỳ tự do', "!=='Nhân viên') return true" in t9)
    la('Nhân viên phải nằm trong BP_KY_TU_DO', 'BP_KY_TU_DO.indexOf(' in t9)

m10 = re.search(r'function newDon\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy newDon()', m10 is not None)
if m10:
    t10 = m10.group(1)
    # 🔴 Nhân viên cơ sở phải KHÔNG CÓ dòng đó, chứ không phải "có mà không chọn sẵn".
    #    Để đó rồi trông chờ người ta đừng bấm là sớm muộn có người bấm.
    la('dòng kỳ tự do chỉ dựng khi được phép', "(tudo?'<option value=\"__tudo__\"" in t10)
    la('không còn chọn sẵn theo tudo', "((!tudo&&o.cur)" not in t10)

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép trên app.html' % dat)
