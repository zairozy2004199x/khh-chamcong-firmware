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

def _hamOf(_s, _ten):
    """Thân một hàm trong app.html. Dò trong THÂN chứ không quét cả tệp: cả trang có hàng
    trăm chỗ khác có `<select>` hay `checkbox`, quét cả tệp là bắt nhầm người khác."""
    _i = _s.find('  function ' + _ten + '(')
    if _i < 0:
        return ''
    _j = _s.find('\n  }', _i)
    return _s[_i:_j + 4] if _j > _i else ''


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

# 🔴 CÙNG HỌ VỚI BẪY TRÊN, và đã cắn lần thứ hai (25/08/2026): HAI `function` cùng tên.
#    JavaScript không báo gì, khai báo SAU thắng, khai báo TRƯỚC chết. Lần này là `_ymd`:
#    bản nhận Date bị bản nhận chuỗi đè, nên hai ô ngày của "khoảng ngày tự chọn" mở ra TRỐNG
#    thay vì điền sẵn — không lỗi, không cảnh báo, chỉ là hai ô rỗng mà không ai biết vì sao.
#    Chỉ soi hàm TOÀN CỤC (thụt đúng 2 dấu cách, đúng lối viết của tệp này). Hàm lồng bên
#    trong hai hàm khác nhau thì trùng tên là bình thường — chúng ở hai phạm vi khác nhau và
#    không đè nhau. Bắt cả chúng là báo đỏ oan, rồi người ta tắt phép thử này đi.
dem = {}
for m in re.finditer(r'^  function ([A-Za-z_$][\w$]*)\s*\(', src, re.M):
    dem.setdefault(m.group(1), []).append(src[:m.start()].count('\n') + 1)
trung_ham = {k: v for k, v in dem.items() if len(v) > 1}
la('không hàm nào bị khai hai lần', not trung_ham,
   '; '.join('%s ở dòng %s' % (k, v) for k, v in list(trung_ham.items())[:5]))

# Chốt riêng đúng chỗ đã hỏng, phòng khi ai đó đổi lại.
m = re.search(r'function renderUsers\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy renderUsers()', m is not None)
if m:
    than = m.group(1)
    la('renderUsers KHÔNG khai var _laAdmin', 'var _laAdmin' not in than,
       'khai lại là bảng người dùng trắng trở lại')
    la('renderUsers vẫn dùng hàm _laAdmin()', '_laAdmin()' in than)
    # Từ 12/09/2026 bảng tách theo vai trò, dựng vào một khối chung rồi mới sinh từng tbody.
    # Cái phải canh vẫn là "renderUsers CÓ VẼ RA gì đó" — vì đúng lỗi 25/08/2026 là hàm chết
    # giữa chừng và bảng không bao giờ được vẽ.
    la('renderUsers có vẽ bảng người dùng', "el('cfgUserNhom').innerHTML" in than)
    la('   và sinh tbody cho từng vai', "cfgUserBody'+i" in than)

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
# 🔴 TỪ 10/09/2026 DANH SÁCH BỘ PHẬN KHÔNG CÒN GÕ CỨNG — nó khai ở Cấu hình → 🗂 Bộ phận và
# máy chủ gửi xuống (`CFG.boPhanDs`). Bảy tên dưới chỉ còn là ĐƯỜNG LUI cho lúc CFG chưa nạp.
# Bài này nay canh hai chuyện: đường lui còn đủ, và ô chọn ĐỌC TỪ MÁY CHỦ chứ không đọc đường lui.
m3 = re.search(r"var BOPHAN_MAC_DINH=\[(.*?)\];", src)
la('đọc được BOPHAN_MAC_DINH (đường lui)', m3 is not None)
if m3:
    bp = [x.strip().strip("'") for x in m3.group(1).split(',')]
    la('có bộ phận Văn phòng', 'Văn phòng' in bp, str(bp))
    la('vẫn giữ đủ 5 bộ phận cũ',
       all(x in bp for x in ['Cơ sở', 'Kỹ thuật', 'Marketing', 'Công tác', 'Setup']), str(bp))
la('ô chọn Bộ phận dựng từ _bpDs(), không từ danh sách gõ cứng',
   '_bpDs().map(function(b){return [b,_bpNhan(b)];})' in src)
la('_bpDs() ưu tiên danh sách máy chủ gửi xuống',
   'CFG.boPhanDs' in src and 'BOOT.boPhanDs' in src)
la('🔴 danh sách máy chủ RỖNG thì ngã về đường lui, không trả mảng rỗng',
   '(ds&&ds.length)?ds:BOPHAN_MAC_DINH' in src)
la('không còn chỗ nào dùng BOPHAN_LIST gõ cứng', 'BOPHAN_LIST' not in src)
# 🔴 Kỹ thuật cũng được chọn kỳ tự do — anh Thắng, về đơn cơ sở của bộ phận Kỹ thuật:
#    "quyết toán theo tuần (nhưng không ép buộc tuần nào, khi nào gửi quyết toán thì mới chốt)".
#    Nhân viên CƠ SỞ thì vẫn không: đơn của họ là đơn xin tạm ứng cho MỘT TUẦN vận hành.
la('Văn phòng và Kỹ thuật được chọn kỳ tự do',
   "BP_KY_TU_DO=['Văn phòng','Kỹ thuật']" in src)
la('🔴 nhân viên CƠ SỞ vẫn KHÔNG được chọn kỳ tự do',
   "'Cơ sở'" not in src.split('BP_KY_TU_DO=[')[1].split(']')[0])
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

# ⚠️ CANH Ý ĐỊNH: hàm nhận thêm tham số ĐƠN VỊ từ 1.94.0 (`_cosoSel(v, dv)`), nên đừng ghim
#    lại đúng một tham số — ghim là đỏ vì bài kiểm chứ không phải vì mã hỏng.
m6 = re.search(r'function _cosoSel\(v[^)]*\)\{(.*?)\n  \}', src, re.S)
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
# Nút chuyển giữa hai loại đơn nằm TRONG trang chứ không trên hàng tab -> phải ẩn riêng,
# không thì người không có quyền bấm vào rồi ăn trang trắng.
# 🔴 CẶP NÚT "LOẠI ĐƠN" CŨ ĐÃ BỎ (anh Thắng 11/09/2026: *"gộp nó lại thành 1, chọn xong tự hỏi
#    ra đơn gì tránh lộn"*) — nó trông như bộ chọn LOẠI ĐƠN nhưng chỉ đổi TRANG. Nay là một nút
#    chuyển một chiều `data-dcsw-di`, vẽ bằng `_veNutChuyenDon(vis)`.
la('không còn cặp nút bật/tắt LOẠI ĐƠN', '[data-dcsw="duan"]' not in src)
# 🔴 TỪ 12/09/2026 GÁC HAI LỚP. Anh Thắng, chỉ vào nút "← Quay lại chi phí Kỹ thuật" trên màn
#    đơn tuần: *"Đối với nhân viên cơ sở ẩn nút này đi, tránh nhập nhầm"*. Lớp `vis` một mình
#    không đủ: nó chỉ hạ xuống 0 khi ô Bộ phận CÓ khai, mà phần lớn tài khoản nhân viên cơ sở
#    để trống ô ấy.
la('ẩn nút chuyển loại đơn khi không có quyền (lớp 1: vis)',
   '[data-dcsw-di]' in src and 'vis[di]' in src)
la('lớp 2: nhân viên chưa khai bộ phận cũng ẩn',
   "bp!==''" in src and 'BP_VAO_DUAN.indexOf(bp)' in src)
la('và lớp 2 chỉ siết nhân viên', "la_nv=(_vaiLuat()==='Nhân viên')" in src)

m9 = re.search(r'function _kyTuDo\(\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy _kyTuDo()', m9 is not None)
if m9:
    t9 = m9.group(1)
    la('vai khác Nhân viên vẫn được kỳ tự do', "!=='Nhân viên') return true" in t9)
    la('Nhân viên phải nằm trong BP_KY_TU_DO', 'BP_KY_TU_DO.indexOf(' in t9)

# `newDon(daChonTuan)` nhận cờ "đã chọn loại ở bên kia rồi" từ 11/09/2026 — anh Thắng:
# *"Bấm Đơn tuần của cơ sở vẫn hiện hỏi lần 2"*. Khuôn dò phải nhận cả bản có tham số.
m10 = re.search(r'function newDon\([^)]*\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy newDon()', m10 is not None)
if m10:
    t10 = m10.group(1)
    # 🔴 Nhân viên cơ sở phải KHÔNG CÓ dòng đó, chứ không phải "có mà không chọn sẵn".
    #    Để đó rồi trông chờ người ta đừng bấm là sớm muộn có người bấm.
    la('dòng kỳ tự do chỉ dựng khi được phép', "(tudo?'<option value=\"__tudo__\"" in t10)
    la('không còn chọn sẵn theo tudo', "((!tudo&&o.cur)" not in t10)

# ---------------------------------------------------------------- vai tự tạo dùng được
print('— vai tự tạo phải dùng được —')
# 🔴 LỖI THẬT: bảng tab tra theo TÊN VAI. Vai tự tạo không có trong bảng nên rơi vào nhánh
#    mặc định {don:1} — vai vừa tạo ra chỉ còn đúng MỘT tab, tức tính năng tạo vai vô nghĩa.
la('có hàm lấy vai gốc phía giao diện', 'function _vaiGoc(' in src)
la('bảng tab tra theo VAI GỐC', '}[_vaiLuat()]||{don:1}' in src)
la('  không còn tra theo tên vai', '}[role]||{don:1}' not in src)
# ═══ MỘT LUẬT DUY NHẤT CHO CẢ TRANG (21/09/2026) ═════════════════════════════
# Anh Thắng gửi ảnh màn của chị Mai Anh (vai "Nhân Viên Cơ Sở Khu Vui Chơi"): *"mất chỗ
# tạo đơn"*. Bảng tra tab đã quy về vai gốc từ lâu, nhưng mười mấy chỗ khác vẫn so thẳng
# `CURUSER.role`. Ngày khai vai con cho cả công ty là chúng hỏng đồng loạt.
la('🔴 có hàm vai-để-so-luật', 'function _vaiLuat(' in src)
# 🔴 CON CỦA ADMIN KHÔNG ĐƯỢC THÀNH ADMIN. Ô cha ở bảng vai trò có cả mục "Admin", nên quy
#    về gốc máy móc là nới quyền Admin bằng đúng một lượt gõ tên.
_fn_vl = _hamOf(src, '_vaiLuat')
la('🔴 con của Admin KHÔNG quy về Admin',
   "(g==='Admin') ? v :" in _fn_vl, _fn_vl)
la('   và chính vai Admin thì vẫn là Admin', "if(v==='Admin') return 'Admin';" in _fn_vl, _fn_vl)
# ⚠️ NÚT TẠO ĐƠN — đúng chỗ anh Thắng báo.
la('🔴 nút Tạo đơn gác theo vai luật, không theo tên vai',
   "var vl=_vaiLuat();" in src and "bn.style.display=(vl==='Nhân viên'||vl==='Quản lý'||vl==='Admin')" in src)
# ⚠️ KHÔNG CÒN CHỖ NÀO KHAI `var role=` từ tên vai rồi đem so với tên vai gốc.
la('🔴 không còn chỗ nào lấy `role` thẳng từ CURUSER để so luật',
   "var role=(CURUSER&&CURUSER.role)||''" not in src)
# ⚠️ NHƯNG CHIP BÀY TÊN VAI VẪN PHẢI LÀ TÊN THẬT. Bày vai gốc là chị Mai Anh mở màn ra thấy
#    mình thành "Nhân viên", tưởng bị đổi vai.
la('⚠️ chip trên đỉnh trang vẫn bày TÊN VAI THẬT', "var role=CURUSER.role;" in src)
la('nhánh bộ phận cũng theo vai gốc', "if(_vaiLuat()==='Nhân viên'){" in src)
m11 = re.search(r'function canDo\(action\)\{(.*?)\n  \}', src, re.S)
la('tìm thấy canDo()', m11 is not None)
if m11:
    t11 = m11.group(1)
    # Vai tự tạo có CỘT RIÊNG trong ma trận -> tra tên vai trước, không có mới lùi về vai gốc.
    la('canDo tra tên vai trước', 'q.hasOwnProperty(r)' in t11)
    la('canDo lùi về vai gốc', 'q[_vaiGoc()]' in t11)

print('— loại đơn: nhãn và phân quyền —')
# Anh Thắng: "chi phí / dự án, chứ nó không phải là gom theo".
# Kiểm NHÃN HIỆN RA (nằm giữa hai thẻ), không phải chữ trong chú thích — bản đầu bắt cả
# chú thích nên đỏ oan.
la('bỏ nhãn GOM THEO', '>GOM THEO<' not in src)
# 🔴 NHÃN "LOẠI ĐƠN" CŨNG ĐÃ BỎ cùng cặp nút của nó (anh Thắng 11/09/2026: *"gộp nó lại thành 1,
#    chọn xong tự hỏi ra đơn gì tránh lộn"*). Cặp nút ấy trông như bộ CHỌN LOẠI ĐƠN nhưng chỉ
#    đổi TRANG đang xem — bấm xong thấy màn khác hẳn thứ mình định lập. Việc chọn loại nay nằm
#    ở hộp "Đơn này là loại nào?" lúc bấm tạo đơn.
la('bỏ nhãn LOẠI ĐƠN của cặp nút cũ', '>LOẠI ĐƠN<' not in src)
la('hộp chọn loại đơn có đủ ba lối',
   'id="ndLoaiCoSo"' in src and 'id="ndLoaiDaCoSo"' in src and 'id="ndLoaiDuAn"' in src)
# 🔴 CỘNG THÊM chứ không THAY luật bộ phận: thay thẳng là nhân viên Kỹ thuật mất tab Dự án
#    ngay lúc cài đè, trước khi kịp tích lại ở bảng phân quyền.
la('quyền Dự án cộng thêm từ ma trận', "if(canDo('donDuAn')) vis.duan=1;" in src)
la('luật bộ phận vẫn còn', 'BP_VAO_DUAN.indexOf(bp)>=0' in src)
la('kỳ tự do cũng cộng thêm từ ma trận', "return canDo('kyTuDo');" in src)

print('— trạng thái đơn + mở khoá sửa đơn —')
# Anh Thắng 26/08/2026: *"tại đầu mỗi đơn bổ sung giúp anh đơn đang ở trạng thái gì, để cho nhân
# viên đơn mình gửi duyệt hay chưa"* và *"giờ sẽ cho nhân viên được phép sửa đơn. trừ khi ở
# trạng thái đã quyết toán mới không được sửa"*.

# 🔴 THANH BƯỚC PHẢI KHỚP LUỒNG BÊN MÁY CHỦ. Đọc thẳng `VHCP_Don::TT_LUONG` từ mã PHP chứ không
#    gõ tay lại ở đây — gõ tay là ba nơi khai cùng một luồng, và nơi quên sửa thì vẽ ra một tiến
#    độ không có thật. Người ta tin cái hình hơn tin câu chữ.
PHP_DON = os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'includes', 'class-vhcp-don.php')
php = io.open(PHP_DON, encoding='utf-8').read()
m_luong = re.search(r"const TT_LUONG = array\((.*?)\);", php, re.S)
la('đọc được TT_LUONG bên máy chủ', m_luong is not None)
if m_luong:
    tt_php = re.findall(r"'([^']+)'", m_luong.group(1))
    m_js = re.search(r"var TT_LUONG=\[(.*?)\];", src)
    la('màn hình có khai TT_LUONG', m_js is not None)
    if m_js:
        tt_js = re.findall(r"'([^']+)'", m_js.group(1))
        la('🔴 thanh bước KHỚP luồng bên máy chủ', tt_php == tt_js,
           'php=%s js=%s' % (tt_php, tt_js))
    # Mỗi bước phải có nhãn ngắn để thanh không tràn ngang trên điện thoại.
    m_ngan = re.search(r"var TT_NGAN =\{(.*?)\};", src)
    la('có bảng nhãn ngắn cho từng bước', m_ngan is not None)
    if m_ngan:
        for b in tt_php:
            la('bước "%s" có nhãn ngắn' % b, ("'%s'" % b) in m_ngan.group(1))

# Câu tiếng người: hai chỗ người dùng thật sự hỏi.
la('nói thẳng "CHƯA GỬI DUYỆT" khi còn Nháp', 'CHƯA GỬI DUYỆT' in src)
la('và "ĐÃ GỬI DUYỆT" khi đã gửi', 'ĐÃ GỬI DUYỆT' in src)
la('và "ĐÃ CHỐT SỔ" khi hết sửa được', 'ĐÃ CHỐT SỔ' in src)
la('dải trạng thái gắn vào đầu đơn', "el('donBadge').innerHTML=" in src and '_thanhBuoc(st)' in src)

# 🔴 MỘT RANH GIỚI. Khoá theo `stChot` chứ không theo danh sách trạng thái gõ tay.
la('khoá sửa dòng theo ĐÃ CHỐT SỔ', 'CUR.lockChi=CUR.stChot;' in src)
la('và stChot đúng hai trạng thái',
   "CUR.stChot=(st==='Đã quyết toán'||st==='Đã xuất MISA');" in src)
la('form nhập dòng mở ở mọi trạng thái chưa chốt',
   "el('lineFormCard').style.display= CUR.stChot?'none':''" in src)
la('bỏ luật cũ "chỉ Nháp hoặc Đã cấp mới sửa dòng"',
   'CUR.lockChi=!(CUR.stNhap||CUR.stCap)' not in src)
# Nhãn khối nhập phải đổi theo trạng thái — thêm dòng sau khi gửi duyệt là PHÁT SINH.
la('nhãn khối nhập nói rõ đang thêm dòng phát sinh', 'Thêm dòng PHÁT SINH' in src)

# 🔴 Hở do chính thay đổi này tạo ra: sửa hạng mục xin SAU khi quản lý đã duyệt thì tổng xin
#    không còn khớp số đã duyệt, mà không có gì nói ra.
la('kêu lên khi tổng xin đã đổi sau khi duyệt', 'Tổng xin đã đổi sau khi duyệt' in src)

# 🔴 Ba chốt "làm được gì" — anh Thắng 26/08: *"to lên với đặt cảnh báo : Đơn đang chờ quyết
#    toán - Khóa xóa - Sửa được.. ( kiểu như vậy )"*. Tên trạng thái là một sự thật về đơn, không
#    phải câu trả lời cho người đang cầm chuột; không nói trước thì họ học bằng cách bị chối.
la('có hàng chốt "làm được gì"', 'function _lamDuocGi(' in src)
for nhan in ('SỬA ĐƯỢC', 'KHOÁ SỬA', 'THÊM DÒNG ĐƯỢC', 'KHOÁ THÊM DÒNG', 'XOÁ ĐƯỢC', 'KHOÁ XOÁ'):
    la('nhãn "%s"' % nhan, nhan in src)
# 🔴 CANH Ý ĐỊNH, KHÔNG CANH CON SỐ. Bản đầu của phép này ghim cứng "font-size:17px" — anh
#    Thắng 06/09/2026 bảo *"chỗ trạng thái đơn chỉnh nhỏ hơn tầm 20% đang to quá"*, và phép ấy
#    đỏ ngay, dù mã mới vẫn giữ đúng điều nó sinh ra để giữ. Một con số không phải là luật; luật
#    là TÊN TRẠNG THÁI PHẢI NỔI HƠN HẲN CHỮ QUANH NÓ — đó mới là thứ không được mất.
_m_ten = re.search(r"font-size:(\d+(?:\.\d+)?)px;font-weight:800;letter-spacing:\.2px", src)
_m_giai = re.search(r"font-size:(\d+(?:\.\d+)?)px;line-height:[\d.]+\"\>'\+_ct\.chu", src)
la('dải trạng thái in tên trạng thái cỡ riêng, đậm 800', bool(_m_ten))
la('và câu giải thích có cỡ riêng', bool(_m_giai))
if _m_ten and _m_giai:
    la('🔴 tên trạng thái NỔI HƠN HẲN câu giải thích quanh nó',
       float(_m_ten.group(1)) >= float(_m_giai.group(1)) + 2)
la('và hàng chốt nằm trong dải', '_lamDuocGi(st)' in src)

# Bấm "Chi tiết" ở tab Quyết toán -> mở THẲNG trang đơn.
# Anh Thắng: *"khi bấm ra chi tiết thì nhảy ra trang đơn luôn chứ không hiện phía dưới nữa"*.
la('nút Chi tiết ở Quyết toán gọi viewDon', 'onclick="viewDon(' in src)
la('bỏ hẳn đường xổ chi tiết tại chỗ',
   'qtToggleDetail' not in src.replace('`qtToggleDetail`', '') and
   'qtChuaNopDetail' not in src.replace('`qtChuaNopDetail`', ''))
# Bỏ hàm thì phải bỏ luôn HÀNG ẨN chứa nó — để lại là một <tr> rỗng nằm giữa bảng.
la('bỏ luôn hàng ẩn của khối xổ', "id=\'qtd-" not in src and 'id="qtd-' not in src)

print('— lịch sử chỉnh đơn · ô tiền · tìm đơn · phân trang —')
# Anh Thắng 26/08, bốn việc.

# 1) Lịch sử chỉnh đơn, đặt ngay cạnh dải trạng thái.
la('có khung lịch sử chỉnh đơn', 'id="donSuBox"' in src)
la('gọi riêng theo mã đơn, không kéo cả sổ chung', '.getDonLog(' in src)
la('và nạp lại mỗi lần mở đơn', 'loadDonSu(maDon)' in src)
la('đóng đơn thì dọn khung', "loadDonSu('')" in src)
# 🔴 Máy chủ phải có đường ấy — gọi một hàm không khai là bấm vào ra lỗi câm.
PHP_API = os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'includes', 'class-vhcp-api.php')
api = io.open(PHP_API, encoding='utf-8').read()
for ham in ('getDonLog', 'timDon', 'dsLoaiChiPhi'):
    la('máy chủ có khai "%s"' % ham, ("'%s'" % ham) in api)

# 🔴 XỔ RA THÌ PHẢI GẬP LẠI ĐƯỢC — TỪ CHỖ ĐANG ĐỨNG.
# Anh Thắng 28/08/2026: *"bấm nút xổ lịch sử chỉnh đơn, nó xổ ra và không tắt gọn lại được"*,
# kèm ảnh một đơn có 38 dòng sử. <details> vẫn gập được — chỉ là gập bằng đúng cái <summary>
# vừa bị 33 dòng đẩy trôi lên khỏi màn hình, và cái mũi tên ▸ mặc định bé đến mức không ai đọc
# nó là một cái nút. Đọc xong ở đáy danh sách thì không còn manh mối nào bảo rằng bấm lại là
# đóng, mà trang thì đã dài gấp ba.
_i_su = src.find('id="suCu"')
# ⚠️ CẮT ĐÚNG TỚI `</details>`, ĐỪNG CẮT THEO SỐ KÝ TỰ. Cắt 1400 ký tự thì đoạn ấy trùm luôn
#    sang thân hàm `suThuGon` nằm ngay dưới — và vết phá "bỏ onclick khỏi nút" vẫn xanh, vì
#    tên hàm vẫn còn ở chỗ khai báo. Đã vấp đúng vậy.
_j_su = src.find('</details>', _i_su) if _i_su >= 0 else -1
_khoi_su = src[_i_su:_j_su] if _i_su >= 0 and _j_su > _i_su else ''
la('khối "dòng cũ hơn" có id để gập được từ nơi khác', _i_su >= 0)
la('dựng cảnh: cắt được đúng khối, không trùm sang hàm bên cạnh',
   _khoi_su != '' and 'function suThuGon' not in _khoi_su)
# Phần xổ ra tự cuộn trong khung riêng -> không kéo dài trang nữa, dù 33 hay 300 dòng.
la('phần xổ ra tự cuộn, không đẩy dài cả trang',
   'max-height:260px;overflow:auto' in _khoi_su)
# Nút thu gọn nằm NGAY DƯỚI khung cuộn -> đọc xong là gập tại chỗ, khỏi cuộn ngược lên.
la('có nút Thu gọn ngay dưới khung, gập được tại chỗ',
   'suThuGon()' in _khoi_su and 'Thu gọn' in _khoi_su)
# 🔴 CÓ MẶT TRONG MÃ KHÁC VỚI NHÌN THẤY ĐƯỢC. Giấu nút đi bằng `display:none` thì mọi phép thử
#    canh chuỗi ở trên vẫn xanh, mà người dùng thì vẫn không gập lại được — đúng chuyện đang sửa.
la('và nút ấy thật sự hiện ra, không bị giấu',
   '<div style="text-align:center;padding:6px 0 2px">' in _khoi_su)
la('và hàm ấy có thật, đóng <details> chứ không chỉ cuộn',
   'function suThuGon()' in src and 'd.open=false' in src)
# ⚠️ Gập xong phải kéo màn hình về đúng chỗ cái nút vừa nằm — không thì người ta đang lơ lửng
#    giữa trang, không biết mình vừa ở đâu.
la('gập xong kéo màn hình về đúng chỗ', 'scrollIntoView' in src[src.find('function suThuGon()'):
                                                               src.find('function suThuGon()') + 300])
# Chữ trên <summary> đổi theo trạng thái — nói thẳng "bấm lại để gập".
la('chữ trên nút xổ đổi theo trạng thái mở/đóng',
   'su-mo' in _khoi_su and 'su-dong' in _khoi_su)
la('và nói thẳng bấm lại là gập', 'bấm lại để gập' in _khoi_su)
# 🔴 Đổi chữ bằng CSS thì CSS phải có thật — thiếu luật là hiện CẢ HAI câu cùng lúc.
_CSS = os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'assets', 'css', 'vhcp.css')
_css = io.open(_CSS, encoding='utf-8').read()
la('CSS giấu câu "thu gọn" lúc đang đóng', '.su-tt .su-dong{display:none}' in _css)
la('CSS đổi hẳn câu lúc đang mở',
   'details[open] > .su-tt .su-mo{display:none}' in _css
   and 'details[open] > .su-tt .su-dong{display:inline}' in _css)

# 2) Ô tiền nổi màu.
la('ô Tạm ứng nổi màu', 'id="qtTU"' in src and '#eff6ff' in src)
la('ô Thực mua nổi màu', 'id="qtThucMua"' in src and '#f0fdf4' in src)
la('ô Còn lại đổi cả nền lẫn viền theo thừa/thiếu',
   "elc.style.background='#fef2f2'" in src and "elc.style.borderColor" in src)

# 3) Tìm đơn ở đầu trang — theo loại chi phí và cơ sở.
la('có ô tìm đơn ở đầu trang', 'id="tdQ"' in src)
# 🔴 THANH TÌM ĐƠN PHẢI NẰM NGOÀI MỌI TAB. Anh Thắng 26/08: *"Lọc tìm kiếm chung theo loại chi
#    phí lẻ anh chưa thấy"* — bản trước đặt nó trong thanh của tab "Đơn chi phí", nên đứng ở tab
#    khác cuộn lên đầu trang là không có gì. Thứ gọi là "tìm kiếm chung" mà chỉ có ở một tab thì
#    nó là ô tìm của tab đó.
la('thanh tìm đơn là thanh chung (id timChung)', 'id="timChung"' in src)
la('và nằm NGOÀI trang đơn', src.index('id="timChung"') < src.index('<div id="page-don">'))
# Ô xổ loại/cơ sở phải nạp ở MỌI lượt đổi tab, không riêng tab đơn — bỏ sót là đứng ở Sổ chi phí
# thấy ô "mọi loại chi phí" rỗng trơn.
la('ô xổ nạp ở mọi lượt đổi tab, không riêng tab đơn',
   "if(p==='don'){ try{ _fillTimDonOpts(); }catch(e){} }" not in src
   and 'try{ _fillTimDonOpts(); }catch(e){}' in src)
la('có ô lọc loại chi phí', 'id="tdLoai"' in src)
la('có ô lọc cơ sở', 'id="tdCoso"' in src)
la('tìm ở MÁY CHỦ (quét cả dòng chi), không lọc ô xổ', '.timDon(' in src)
la('kết quả hiện rõ đơn thuộc cơ sở nào', '<th>Cơ sở</th>' in src)
# 🔴 Anh Thắng 26/08: *"chỗ này phải hiện hàng con dưa leo ra chứ, hiện tên đơn thì không biết
#    được"*. Gõ "dưa leo" ra 9 đơn mà cột nào cũng ghi "Chi phí NVL đồ ăn - Mua lẻ" — đúng một
#    cái tên ở cả 9 dòng, nhìn xong vẫn phải mở từng đơn ra xem.
la('cột kết quả là DÒNG CHI khớp, không phải tên loại', '<th>Dòng chi khớp</th>' in src)
la('và hiện nội dung + số tiền của từng dòng',
   'esc(x.noiDung' in src and 'money(x.tien)' in src)
# Cắt còn 5 dòng thì phải NÓI RA còn bao nhiêu — cắt im lặng thì "3 dòng" trông y hệt "chỉ có 3".
la('cắt bớt thì nói ra còn bao nhiêu dòng nữa', 'dòng nữa' in src and 'd.soDongKhop' in src)
# Tìm theo kỳ / người lập thì không có dòng nào khớp — lui về tên loại, thà thô còn hơn cột trống.
la('không có dòng khớp thì lui về tên loại', 'if(!lo5) lo5=(d.loai||[])' in src)
la('và mở được thẳng đơn từ kết quả', "onclick=\\'viewDon" in src or 'onclick="viewDon(' in src)

# 4) Tra theo mã: lọc loại + phân trang 20 dòng.
la('tab Tra theo mã có ô lọc loại chi phí', 'id="tmLoai"' in src)
la('có thanh phân trang', 'id="tmPager"' in src)
la('mỗi trang 20 dòng', 'TM_MOI=20' in src)
# 🔴 Chân bảng phải nói TỔNG CỦA CẢ BỘ LỌC — tổng của một trang thì chẳng đối chiếu với cái gì.
la('chân bảng vẫn cộng cả bộ lọc, không cộng mỗi trang đang hiện', "money(tong)" in src)
# 🔴 Xuất Excel theo bộ lọc nhưng KHÔNG cắt trang: cắt 20 dòng vào tệp là đưa một bản thiếu mà
#    trông như đủ.
la('xuất Excel không cắt theo trang', 'TM_LOC&&TM_LOC.length' in src)

# 5) Khối Quyết toán nổi hẳn lên cho kế toán.
# 🔴 Anh Thắng 26/08: *"Đóng nguyên ô này nổi màu lên cho kế toán thấy"*. Mục 3) là chỗ kế toán
#    phải dừng lại — đối chiếu tạm ứng với thực mua rồi trả tiền — mà nó mặc đúng bộ đồ trắng
#    của mọi khối khác, nên trong một trang dài toàn khối trắng thì đúng cái cần dừng lại lại
#    khó thấy nhất.
print()
print('— khối quyết toán nổi màu —')
la('khối Quyết toán mang lớp nổi', 'class="card qt-noi" id="qtCard"' in src)
la('cụm ba con số đóng chung MỘT khung', 'class="qt-cum"' in src)
la('tiêu đề nói rõ đây là việc của kế toán', 'id="qtNhan"' in src and 'kế toán' in src)
# ⚠️ CẢ TRANG CHỈ ĐƯỢC CÓ MỘT KHỐI MANG MÀU NÀY. Tô thêm khối thứ hai là mất tính "một", và
#    mất luôn tác dụng: khi mọi thứ đều nổi thì không có gì nổi.
la('chỉ ĐÚNG MỘT khối mang lớp qt-noi', src.count('qt-noi') == 1)
css = open(GOC + '/wordpress/vhcp-chi-phi/assets/css/vhcp.css', encoding='utf-8').read()
la('lớp qt-noi có kiểu chữ thật trong tệp css', '.card.qt-noi{' in css)
la('và lớp qt-cum cũng vậy', '.qt-cum{' in css)

# 6) Đơn vị K&H · POSH trên giao diện.
print()
print('— đơn vị K&H · POSH —')
# 🔴 Anh Thắng 26/08: *"trong phần chi phí, tạm ứng, quyết toán thì tách thành 2 phần để dễ
#    nhìn chi phí của bộ phận nào."*
la('có hàm tách khối theo đơn vị', 'function _tachDonVi(' in src)
# Chỉ tách khi người xem nhìn được HƠN MỘT đơn vị — kế toán POSH chỉ có đơn POSH, chèn thêm
# một dải "POSH" lên đầu mọi bảng là thêm một dòng chữ không mang tin gì.
la('chỉ tách khi nhìn được hơn một đơn vị', 'if(!BOOT.nhieuDonVi) return' in src)
for _b, _goi in [('Duyệt tạm ứng', "el('duyetBody').innerHTML=_tachDonVi("),
                ('Quyết toán chờ/xong', "el('qtBody'+hoa).innerHTML=_tachDonVi("),
                ('Đã cấp chưa nộp', "el('qtBodyChuaNop').innerHTML=_tachDonVi(")]:
    la('bảng "%s" dùng _tachDonVi' % _b, _goi in src)
la('dòng ngăn có kiểu chữ thật trong tệp css', 'tr.dv-ngan>td{' in css)

# Cấu hình: MỘT cột Đơn vị. Anh Thắng 12/09/2026: *"Đơn vị với xem đơn vị là 1, đã thuộc đơn
# vị đó, thì toàn quyền xem của mình"*. Cột "Xem đơn vị" và cột "TK Có" đã bỏ; chi tiết và các
# phép canh chỉ số ô nằm ở tools/test/kiem-gop-cot-don-vi.js + test-cauhinh-xo.js.
la('🔴 KHÔNG còn cột Xem đơn vị', '>Xem đơn vị</th>' not in src)
la('🔴 KHÔNG còn cột TK Có', 'TK Có (khi là người duyệt)' not in src)
la('lưu người dùng gom từ MỌI bảng vai trò', '_uMoiHang()' in src and 'data-user-body' in src)
# ═══ CỘT ĐƠN VỊ → CỘT KHỐI (21/09/2026) ════════════════════════════════
# Anh Thắng: *"Chỗ đơn vị thay bằng khối — tích nếu 1 người làm 2 khối thì chọn 2, vì có
# thể nv chung sẽ làm việc với 2 khối"*. Ô xổ một-lựa cũ không nói được "hai khối".
la('bảng người dùng có cột Khối', '>Khối</th>' in src and 'function _khoiTichNguoi(' in src)
la('   và là Ô TÍCH nhiều, không phải ô xổ một-lựa',
   'type="checkbox"' in _hamOf(src, '_khoiTichNguoi') and '<select' not in _hamOf(src, '_khoiTichNguoi'))
# 🔴 HAI CHỐT ĐẮT NHẤT CỦA BẢN ĐỔI NÀY — cả hai hỏng lặng lẽ:
#   1. ô tích KHÔNG được `_readRows()` đọc, nên giá trị phải nằm ở một ô ẩn — thiếu nó là
#      tích xong bấm Lưu không lưu gì cả;
#   2. đơn vị cũ vẫn là CỔNG QUYỀN THẬT, phải đi theo trong ô ẩn thứ hai — gửi rỗng lên là
#      lượt Lưu đầu tiên đẩy CẢ CÔNG TY về nhà mẹ K&H, ai cũng đọc được sổ của mọi nhà.
la('🔴 giá trị khối nằm ở ô ẩn cho `_readRows()` đọc',
   'data-khoi-ng' in _hamOf(src, '_khoiTichNguoi') and 'function _khoiTichDoi(' in src)
la('🔴 đơn vị cũ đi theo trong ô ẩn, không bị ghi rỗng đè',
   'data-dv-cu' in _hamOf(src, '_khoiTichNguoi'))
la('🔴 và lượt Lưu đọc đúng thứ tự khối-rồi-đơn-vị',
   "khoi:(r[7]||'').trim(), donVi:(r[8]||'').trim()" in src)

# Đẩy đơn / dòng chi lẻ sang đơn vị khác.
la('có nút đẩy sang đơn vị khác', 'id="btnChuyenDV"' in src)
la('nút chỉ hiện khi nhìn được hơn một đơn vị', 'BOOT.nhieuDonVi && !CUR.stChot' in src)
la('có hàm đẩy đơn', 'function moChuyenDonVi(' in src)
la('gọi đúng hàm máy chủ', '.chuyenDonVi(CUR.don.maDon' in src)
# 🔴 Hỏi rõ "cả đơn hay vài dòng" — hai việc khác hẳn nhau về hậu quả, tự chọn giúp là chọn
#    sai một nửa số lần, mà sai ở đây là tiền nằm nhầm sổ.
la('hỏi rõ cả đơn hay vài dòng chi lẻ', 'Đẩy CẢ ĐƠN' in src and 'chọn vài dòng chi lẻ' in src)
la('nói trước hậu quả rồi mới hỏi đồng ý', 'trạng thái Nháp' in src and 'RỜI khỏi đơn này' in src)

# 7) Rời tab đơn là đóng luôn đơn đang mở.
print()
print('— rời tab đơn thì đóng đơn —')
# 🔴 Anh Thắng 26/08: *"Anh chuyển qua trang tổng quan xong quay lại đơn chi phí nó vẫn hiện đơn
#    đó. anh chưa mở đơn mà"*. `CUR` sống suốt phiên nên quay lại tab là bày lại đơn cũ. Nặng
#    hơn chuyện khó chịu: mọi nút Sửa/Xoá/Gửi duyệt trên màn đều nhắm vào đơn ấy.
la('rời tab đơn thì quên đơn đang mở', "CUR=null;" in src and "if(el('donSel')) el('donSel').value='';" in src)
la('và vẽ lại màn cho về danh sách', 'try{ _syncDonView(); }catch(e){}' in src)
# viewDon() vẫn phải mở được đơn từ tab khác: nó gọi showPage('don') trước rồi openDon() sau.
la('viewDon vẫn đặt đơn SAU khi đổi tab',
   "showPage('don'); _viewDonFrom=from; el('donSel').value=m; openDon(m);" in src)

# 8) Thanh đơn chia hai hàng bằng tay.
print()
print('— thanh đơn hai hàng —')
# 🔴 Anh Thắng 26/08: *"Dẫn đến lệch giao diện nè"*. Thanh đơn từng là MỘT hàng flex-wrap chứa
#    lẫn ô chọn đơn, các nút, dải trạng thái và khối lịch sử 460px. Bỏ ô tìm ra khỏi đó là số
#    phần tử đổi và flex ngắt dòng ở chỗ khác — nút Xoá đứng chơ vơ giữa khoảng trắng.
#    Để flex tự quyết chỗ ngắt thì bố cục phụ thuộc số phần tử: thêm một nút là vỡ, không báo.
la('thanh đơn dùng lớp don-bar (không còn một hàng flex)', 'class="bar don-bar" id="donBar"' in src)
la('hàng 1 gom ô chọn đơn + các nút', 'class="db-hang1"' in src)
la('hàng 2 gom trạng thái + lịch sử chỉnh đơn', 'db-hang2' in src)
la('có ô đệm đẩy nhóm nút về mép phải', 'class="db-day"' in src)
for _id in ['donSel', 'btnNewDon', 'btnChuyenDV', 'btnDelDon', 'btnDelDonAdmin', 'btnAction']:
    _i = src.index('id="%s"' % _id)
    la('%s nằm ở hàng 1' % _id,
       src.rindex('class="db-hang1"', 0, _i) > src.rindex('class="bar don-bar"', 0, _i)
       and 'class="db-hang2"' not in src[src.rindex('class="db-hang1"', 0, _i):_i])
# ⚠️ CANH LỚP CÓ MẶT, KHÔNG CANH CHUỖI THUỘC TÍNH NGUYÊN VĂN. Bản đầu đòi đúng
#    `class="db-hang2"` — thêm một lớp thứ hai vào thẻ ấy (việc hoàn toàn bình thường, và
#    06/09/2026 đã phải thêm thật) là ba phép này gãy, dù bố cục không hề sai.
for _id in ['donBadge', 'donSuBox']:
    _i = src.index('id="%s"' % _id)
    la('%s nằm ở hàng 2' % _id, 'db-hang2' in src[:_i].rsplit('class="db-hang1"', 1)[-1])

# 🔴 HÀNG 2 PHẢI NẰM NGOÀI THANH DÍNH — anh Thắng 06/09/2026: *"nó đang cố định 1 chỗ nên chèn
#    hết trang. Cả máy tính cũng bị"*. `#donBar` mang `position:sticky;top:0`; khi hàng 2 (khối
#    trạng thái + sổ chỉnh đơn) còn nằm trong đó thì thanh cao hơn khung nhìn, và sticky với
#    phần tử cao hơn khung nhìn sẽ dính ngay rồi ở nguyên đó — nội dung dưới không cuộn lên tới
#    được. Càng thêm nội dung vào hàng 2 thì càng chắc vượt màn, nên nó hỏng DẦN chứ không hỏng
#    đột ngột: đúng kiểu hỏng chỉ có phép canh mới bắt được.
def _boc_the(_s, _tu):
    # Bốc trọn một thẻ <div> bằng cách ĐẾM THẺ CÂN BẰNG. Cắt bằng chuỗi đóng thì lệch ngay khi
    # bên trong có thẻ lồng — và ở đây bên trong có cả chục thẻ.
    _k, _sau = _s.index('>', _tu) + 1, 1
    for _m in re.finditer(r'<div\b|</div>', _s[_k:]):
        _sau += -1 if _m.group(0) == '</div>' else 1
        if _sau == 0:
            return _s[_tu:_k + _m.end()]
    return ''
_bar = _boc_the(src, src.index('<div class="bar don-bar" id="donBar"'))
la('bốc được thanh đơn', len(_bar) > 300)
la('🔴 thanh dính chỉ chứa hàng NÚT', 'db-hang1' in _bar)
la('🔴 khối trạng thái KHÔNG nằm trong thanh dính', 'id="donBadge"' not in _bar)
la('🔴 sổ chỉnh đơn KHÔNG nằm trong thanh dính', 'id="donSuBox"' not in _bar)
la('và thanh vẫn dính ở đầu màn', '.don-bar{display:block;position:sticky;top:0' in css)
# Mở cụm "⋯" thì hàng nút cao hẳn lên — lúc ấy phải thôi dính, không thì lại đúng cái bẫy vừa
# gỡ, chỉ khác là nấp sau một cú bấm.
la('🔴 mở cụm "⋯" thì thanh thôi dính', '.don-bar.mo{position:static}' in css)
# 🔴 BÓC CHÚ THÍCH TRƯỚC KHI DÒ. Phép này lúc đầu dò thẳng `':has(' not in css` và ĐỎ ngay —
#    vì đúng câu chú thích dặn *đừng dùng* `:has()` có chứa hai chữ ấy. Đây là lần thứ năm ở hai
#    kho này một phép thử vấp vào chính chú thích của thứ nó đang canh; chiều tự XANH thì nguy
#    hơn (một luật đã gỡ khỏi mã nhưng còn nằm trong chú thích vẫn làm phép dò xanh).
#    ⚠️ Chỉ bóc chú thích KHỐI, và đòi dấu mở phải đứng sau khoảng trắng — trong CSS hai dấu ấy
#       còn xuất hiện giữa các giá trị (`calc(100%/*...*/)` là chú thích thật, nhưng `url(a/*b)`
#       thì không), nên bóc trần trụi là ăn nhầm vào mã.
_css_ma = re.sub(r'(^|[\s;{},:])/\*[\s\S]*?\*/', r'\1 ', css)
la('đối chứng: bóc chú thích xong vẫn còn luật CSS để soi', '.don-bar' in _css_ma)
la('và lớp ấy do JS gắn, không dựa vào :has() (máy cũ lặng lẽ không áp)',
   "bar.classList.toggle('mo', mo)" in src and ':has(' not in _css_ma)
la('ba lớp có kiểu chữ thật trong tệp css',
   '.db-hang1{' in css and '.db-hang2{' in css and '.db-day{' in css)
# Hàng 2 rỗng thì không được treo một khoảng trắng trông như lỗi.
la('hàng 2 chỉ chiếm chỗ khi có nội dung', '.db-hang2:not(:empty){margin-top' in css)

# ---------------------------------------------------------------- sổ lệnh tạm ứng
# Anh Thắng 07/09/2026: mỗi lượt duyệt đẻ một tờ lệnh, nằm CUỐI trang Duyệt tạm ứng.
print('— sổ lệnh tạm ứng —')
_i_duyet = src.index('<div id="page-duyet"') if '<div id="page-duyet"' in src else -1
la('tìm được trang Duyệt tạm ứng', _i_duyet > 0)
_trang = _boc_the(src, _i_duyet) if _i_duyet > 0 else ''
la('bốc được trọn trang', len(_trang) > 1000, len(_trang))
la('🔴 khối sổ lệnh nằm TRONG trang Duyệt tạm ứng', 'id="lenhTUCard"' in _trang)
# "phía dưới cuối trang" — phải nằm SAU bảng đơn, không phải chen lên trên nó.
la('🔴 và nằm SAU bảng đơn, không chen lên trên',
   _trang.index('id="duyetBody"') < _trang.index('id="lenhTUBody"'))
la('có chỗ vẽ các tờ lệnh', 'id="lenhTUBody"' in _trang)
la('có câu cho sổ rỗng', 'id="lenhTUEmpty"' in _trang)
# Ba thứ anh hỏi phải là BA CỘT thật, không nhét chung một ô chữ.
for _c in ['Tổng tạm ứng', 'Số cơ sở', 'Cơ sở · số tiền · đơn']:
    la('cột "%s"' % _c, _c in _trang)
# Nạp sổ mỗi lần vào tab — không thì duyệt xong phải tải lại trang mới thấy tờ lệnh.
la('🔴 vào tab Duyệt là nạp sổ lệnh', 'loadLenhTU();' in src and 'function loadDuyet(){ boot(renderDuyet);' in src)
# Bảng "Đơn theo dự án" cũng phải nạp cùng lúc — kế toán mở tab Duyệt là thấy CẢ HAI loại đơn.
la('🔴 và nạp luôn bảng đơn theo dự án', 'loadDonHM();' in src)
la('sổ lệnh gọi đúng cửa máy chủ', '.dsLenhTU(' in src)

# --- 🔴 BẢN THỨ HAI CHO KẾ TOÁN (anh Thắng 07/09/2026) ---
# Kế toán là người cầm tiền đi phát, nên tờ lệnh phải nằm trong tầm mắt họ, không bắt sang
# tab của quản lý.
_i_qt = src.index('<div id="page-qt"') if '<div id="page-qt"' in src else -1
la('tìm được trang Quyết toán', _i_qt > 0)
_trang_qt = _boc_the(src, _i_qt) if _i_qt > 0 else ''
la('bốc được trọn trang Quyết toán', len(_trang_qt) > 1000, len(_trang_qt))
la('🔴 màn kế toán CŨNG có khối lệnh tạm ứng', 'id="lenhTUCardKT"' in _trang_qt)
la('và nó nằm SAU bảng đơn của màn ấy',
   _trang_qt.index('id="qtBody"') < _trang_qt.index('id="lenhTUBodyKT"'))
la('🔴 vào tab Quyết toán cũng nạp sổ lệnh tạm ứng',
   'loadLenhTU();' in src.split('function loadQT()')[1].split('\n')[0])
# 🔴 Anh Thắng: "Khi nv gửi chốt quyết toán, bên tab quyết toán của kế toán cũng sẽ hiện lên
#    đơn đó giống tạm ứng để kế toán theo dõi". Không nạp thì bảng trắng trơn, mà lệnh thì có
#    thật — kế toán tưởng chưa ai gửi.
la('🔴 và nạp cả lệnh QUYẾT TOÁN của dự án',
   'loadQtLenh();' in src.split('function loadQT()')[1].split('\n')[0])
# 🔴 MỘT PHÉP VẼ CHO CẢ HAI CHỖ. Hai bản vẽ riêng là hai chỗ để lệch, rồi hai màn nói hai con
#    số cho cùng một tờ lệnh — đúng cảnh ảnh 31/08/2026 "2 có số tổng tạm ứng khác nhau".
la('🔴 hai chỗ bày dùng CHUNG một phép vẽ', 'function _veLenhTU(idBody, idSo, idEmpty){' in src)
for _b in ['lenhTUBody', 'lenhTUBodyKT']:
    la('   %s vẽ qua hàm chung' % _b, ("_veLenhTU('%s'" % _b) in src)
# Mã đơn phải bấm được: một cơ sở có thể có mấy đơn trong cùng lượt duyệt, kế toán cần mở
# thẳng đơn để soát chứ không dò lại theo tên cơ sở.
# 🔴 DÒ TRONG ĐÚNG HÀM VẼ, không dò cả tệp: `onclick="viewDon(` có mặt ở dăm chỗ khác trong
#    app.html, nên dò trần trụi là tự XANH kể cả khi mã đơn trên tờ lệnh đã thành chữ chết.
_i_ve = src.index('function _veLenhTU(idBody, idSo, idEmpty){')
_j_ve = src.index('\n  }', _i_ve) + 4
_fn_ve = src[_i_ve:_j_ve]
la('bốc được hàm vẽ tờ lệnh', len(_fn_ve) > 400, len(_fn_ve))
la('đối chứng: hàm bốc ra khép kín', _fn_ve.rstrip().endswith('}'), _fn_ve[-40:])
la('🔴 tờ lệnh chỉ ra VÀO ĐƠN NÀO', 'c.maDons' in _fn_ve)
la('và mã đơn bấm được, mở thẳng đơn', 'viewDon(' in _fn_ve)
la('   dùng chính mã đơn ấy làm nhãn', "esc(m)" in _fn_ve)

# ---------------------------------------------------------------- rê chuột vào bill -> phóng to
# Anh Thắng 07/09/2026: ảnh chứng từ trong bảng chỉ cao 28-34px, muốn đọc số tiền trên bill
# phải bấm mở thẻ mới rồi đóng lại — kế toán soát chục dòng là chục lần mở-đóng.
print('— rê chuột vào bill —')
la('🔴 có hàm dựng lớp phủ phóng to', 'function _billZoomInit(){' in src)
la('và nó chạy ngay khi mở trang', '_billZoomInit();' in src)
_i_bz = src.index('function _billZoomInit(){')
_j_bz = src.index('\n  }', _i_bz) + 4
_fn_bz = src[_i_bz:_j_bz]
la('bốc được hàm', len(_fn_bz) > 600, len(_fn_bz))
la('đối chứng: hàm bốc ra khép kín', _fn_bz.rstrip().endswith('}'), _fn_bz[-40:])
# 🔴 ỦY QUYỀN SỰ KIỆN Ở document — mọi bảng ở đây đều được VẼ LẠI (đổi trang, lọc, tải lại).
#    Gắn tay vào từng thẻ ảnh thì sau lần vẽ lại đầu tiên là rê chuột không ra gì, không báo
#    lỗi gì cả — đúng cái bẫy đã cắn nút xoá ghế bên nhánh Ghế.
la('🔴 nghe ở document, không gắn vào từng thẻ ảnh',
   "document.addEventListener('mouseover'" in _fn_bz)
la('   và lọc bằng closest([data-bill])', "closest('[data-bill]')" in _fn_bz)
la('   không gắn tay vào từng img', '.querySelectorAll' not in _fn_bz)
# Lớp phủ phải ở body: bảng có overflow-x nên ảnh phóng to bên trong ô sẽ bị khung cuộn cắt.
la('🔴 lớp phủ gắn vào body, thoát khỏi khung cuộn của bảng',
   'document.body.appendChild(ov)' in _fn_bz)
la('   và dựng MỘT lần rồi dùng lại (không rác chồng rác)', 'if(ov) return ov;' in _fn_bz)
_css_bz = re.sub(r'(^|[\s;{},:])/\*[\s\S]*?\*/', r'\1 ', css)
la('   lớp phủ định vị fixed trong css', '#billZoom{' in _css_bz and 'position:fixed' in _css_bz)
la('   và không ăn chuột (khỏi chặn chính thẻ đang rê)', 'pointer-events:none' in _css_bz)
# Ảnh bill ở cột cuối bảng, tức sát mép phải — thả bừa bên phải con trỏ là nửa ảnh ra ngoài màn.
la('🔴 giữ ảnh trong khung nhìn, không tràn ra ngoài màn',
   'window.innerWidth' in _fn_bz and 'window.innerHeight' in _fn_bz)
la('cuộn trang thì đóng ảnh lại', "addEventListener('scroll'" in _fn_bz)
# Ảnh nào rê được: hai bảng có ảnh chứng từ + liên kết ảnh chuyển khoản.
la('🔴 ảnh chứng từ trong bảng dòng chi rê được', src.count('data-bill="') >= 3, src.count('data-bill="'))
la('⚠️ thẻ <a> giữ nguyên: bấm vẫn mở ảnh gốc (điện thoại không rê được)',
   src.count('target="_blank" title="Rê chuột để phóng to') >= 2)

# ---------------------------------------------------- điền sẵn tên NV thanh toán = người nhập
# Anh Thắng 14/09/2026: *"Lấy tên nhân viên nhập làm tên mặc định ban đầu nếu không sửa"*.
#
# 🔴 GỢI Ý VÀ GIÁ TRỊ MẶC ĐỊNH LÀ HAI VIỆC KHÁC NHAU. Bản trước trộn làm một: chỉ vai "Nhân
#    viên" mới được điền sẵn, vì họ cũng là vai duy nhất bị thu hẹp danh sách gợi ý. Nên Admin,
#    Quản lý, Kế toán mở đơn ra là ô trống — mà phần lớn đơn họ nhập vẫn là tiền của chính họ.
print('— tên NV thanh toán điền sẵn —')
_i_dt = src.index('function fillDoiTuongList(')
_j_dt = src.index('\n  }', _i_dt) + 4
_fn_dt = src[_i_dt:_j_dt]
la('bốc được hàm fillDoiTuongList', len(_fn_dt) > 300, len(_fn_dt))
la('đối chứng: hàm bốc ra khép kín', _fn_dt.rstrip().endswith('}'), _fn_dt[-40:])
la('🔴 mọi vai đều được điền sẵn tên mình, không riêng Nhân viên',
   "if(want==='NV' && f && CURUSER && CURUSER.name && !String(f.value||'').trim()) f.value=CURUSER.name;" in _fn_dt)
# 🔴 CHỈ Ở LỐI "THANH TOÁN CÁ NHÂN". Ô này lúc chọn NCC là tên NHÀ CUNG CẤP — điền tên người
#    nhập vào đó là dựng ra một nhà cung cấp mang tên nhân viên, và bút toán ấy đi thẳng sang MISA.
la('🔴 không điền khi đang chọn Nhà cung cấp', "want==='NV' && f" in _fn_dt)
# ⚠️ CHỈ ĐIỀN KHI Ô ĐANG TRỐNG: người ta gõ tên người khác rồi đổi qua đổi lại ô Phân loại là
#    mất chữ vừa gõ, mất im lặng, ngay trước lúc bấm Thêm hạng mục.
la('⚠️ không đè lên tên đã gõ', "!String(f.value||'').trim()" in _fn_dt)
# ⚠️ Danh sách GỢI Ý vẫn thu hẹp cho Nhân viên — chọn nhầm người là tiền vào tay người khác.
la('⚠️ gợi ý vẫn chỉ mình họ với vai Nhân viên', "_vaiLuat()==='Nhân viên'" in _fn_dt)
# ⚠️ Lượt gọi lúc nạp trang không truyền tham số; không đọc ô Phân loại đang có thì nó luôn
#    chạy như "chưa chọn phân loại", và tên mặc định không bao giờ hiện ở lần mở trang đầu.
la('🔴 gọi không tham số thì đọc ô Phân loại đang có',
   "if(pltt===undefined||pltt===null) pltt=(el('f_pltt')&&el('f_pltt').value)||'';" in _fn_dt)

# ------------------------------------------------- mọi khối nội dung nằm trong khung căn giữa
# Anh Thắng 14/09/2026, ảnh /chi-phi: *"Chỉnh lệch trang"*. `#datKyBox` và `#donListCard` là
# `.card` trần đứng ngoài mọi `.wrap`, nên trải hết bề ngang và dính sát mép trái, trong khi cả
# trang (thanh xanh · thanh tab · thanh tìm đơn · mọi thẻ khác) căn theo trục 1600px.
#
# 🔴 SOI CẤU TRÚC, KHÔNG SOI MỘT CHUỖI. Dò "có chữ wrap ở gần donListCard" thì bọc sai chỗ hay
#    quên thẻ đóng vẫn xanh. Đây đếm độ sâu thật: `.card` nào không có `.wrap` nào bao ngoài.
print('— không khối nào tràn ra ngoài khung căn giữa —')
import re as _re
_body = src[src.index('<body>'):src.index("<script>")]
_depth = 0
_inwrap = []
_lac = []
for _m in _re.finditer(r'<div\b[^>]*>|</div>', _body):
    _t = _m.group(0)
    if _t == '</div>':
        _depth -= 1
        _inwrap = [d for d in _inwrap if d < _depth]
    else:
        if 'class="wrap"' in _t:
            _inwrap.append(_depth)
        if 'class="card"' in _t and not _inwrap:
            _lac.append(_t[:70])
        _depth += 1
la('🔴 không còn .card nào nằm ngoài .wrap', not _lac, _lac)
# ⚠️ Thẻ đóng thiếu thì cả phần dưới trang tụt vào trong khối — trình duyệt không báo gì.
_mo = len(_re.findall(r'<div\b', _body)); _dong = len(_re.findall(r'</div>', _body))
la('⚠️ số thẻ div mở bằng số thẻ đóng', _mo == _dong, '%d mở / %d đóng' % (_mo, _dong))
# ⚠️ Lớp bọc phải bỏ đệm dọc: .card đã có margin-bottom riêng, cộng thêm 16px trên dưới là hai
#    khối ấy tự dãn xa hẳn phần còn lại — sửa lệch ngang mà đẻ lệch dọc.
la('⚠️ lớp bọc bỏ đệm dọc', 'class="wrap" style="padding-top:0;padding-bottom:0"' in _body)

# ------------------------------------------------- bàn giao: người mới cùng cơ sở mở được đơn cũ
# Anh Thắng 14/09/2026: *"bạn cũ nghỉ, bạn mới nhận việc thì đơn chi phí phải nhìn lại hết được
# đơn của bạn để có thể tiếp tục chỉnh sửa đơn đó, cùng cơ sở"*.
#
# 🔴 DANH SÁCH VÀ CỬA MỞ ĐƠN PHẢI NỚI BẰNG NHAU. Danh sách đã lấp cơ sở từ hàng TẠM ỨNG từ
#    07/09/2026 (`$cs_tu` trong `list_dons()`), nhưng cửa mở đơn thì chỉ hỏi bảng dòng chi. Hai
#    bên lệch đúng ở ca hay gặp nhất: ĐƠN XIN ỨNG TRƯỚC — có số tạm ứng mà chưa liệt kê hạng mục
#    nào. Người mới THẤY đơn ấy trong danh sách, bấm vào thì bị chối. Và đó đúng là những đơn
#    đang treo tiền, cần bàn giao nhất.
print('— bàn giao đơn cho người cùng cơ sở —')
_don_php = io.open(os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'includes', 'class-vhcp-don.php'),
                   encoding='utf-8').read()
_don_ma = _re.sub(r'/\*[\s\S]*?\*/', ' ', _don_php)
_i_cs = _don_ma.index('function cac_coso_cua_don(')
_fn_cs = _don_ma[_i_cs:_don_ma.index('\n\t}', _i_cs)]
la('bốc được hàm cac_coso_cua_don', len(_fn_cs) > 150, len(_fn_cs))
la('🔴 cửa mở đơn hỏi CẢ bảng tạm ứng, không chỉ dòng chi',
   'UNION' in _fn_cs and '$tu' in _fn_cs)
la('   và hỏi một câu, không hai lượt', _fn_cs.count('get_col') == 1, _fn_cs.count('get_col'))
# ⚠️ Cửa này chạy trước MỌI lượt mở, sửa, xoá dòng — nên nó phải là chỗ DUY NHẤT quyết định,
#    và vẫn hỏi đúng `trong_tam()` mà danh sách đang hỏi, để hai bên không thể lệch lần nữa.
la('⚠️ vẫn hỏi đúng trong_tam() như danh sách',
   'VHCP_Auth::trong_tam' in _don_ma[_don_ma.index('function loi_khong_phai_don_minh('):][:2000])
la('⚠️ và dựng chuỗi cơ sở từ chính cac_coso_cua_don()',
   'cac_coso_cua_don( $ma_don )' in _don_ma[_don_ma.index('function loi_khong_phai_don_minh('):][:2000])

# ------------------------------------------------ phân cơ sở thì toàn quyền, không cảnh báo
# Anh Thắng 14/09/2026, cả một chuỗi: *"2 nhân viên cùng cơ sở thì làm việc như nhau, nhìn thấy
# nội dung như nhau, chức năng quyền hạn như nhau"* · *"có quyền làm tiếp đơn cũ của người cũ"*
# · *"làm gì có danh mục cơ sở"* · *"cấu hình nội bộ chi phí mà, không liên quan bên ngoài"* ·
# *"loại bỏ cảnh báo, phân cơ sở thì toàn quyền"*.
#
# 🔴 KHÔNG SOI Ô CƠ SỞ VỚI DANH MỤC NÀO. Bản 1.174.0 gắn cờ mọi giá trị không có trong CFG.coso,
#    và cờ vàng bắn vào gần như mọi dòng — toàn cơ sở thật đang chạy trên đơn. Kết tội hàng loạt
#    là cách chắc chắn nhất để người dùng thôi tin mọi cảnh báo khác, kể cả cảnh báo đúng.
print('— phân cơ sở thì toàn quyền —')
# ⚠️ SOI MÃ ĐÃ BỎ CHÚ THÍCH. Bản nháp dò cả tệp và đỏ vì đọc trúng chính câu giải thích *vì sao*
#    bỏ cảnh báo — phép kiểm chửi đúng cái chú thích nói rằng nó đã được sửa. Cùng cái bẫy đã
#    gặp với kiem-tach-ban-vung.php và kiem-trang-tong.php.
_src_ma = _re.sub(r'/\*[\s\S]*?\*/', ' ', src)
la('🔴 bảng Người dùng không còn gắn cờ ô Cơ sở',
   'chưa có trong danh mục cơ sở' not in _src_ma and 'không có trong danh mục' not in _src_ma)
la('   và không còn dò CFG.coso để kết tội', 'var lac=' not in src, [l for l in src.split('\n') if 'var lac=' in l][:2])
# 🔴 MÁY CHỦ ĐÃ LỌC RỒI — MÀN KHÔNG LỌC LẠI. Hai bản luật phân quyền thì chỉ cần lệch một vế là
#    màn GIẤU mất đơn mà máy chủ vẫn cho xem: im lặng, không câu lỗi nào. Đúng chuyện đã xảy ra.
la('🔴 màn không lọc lại phạm vi, để máy chủ giữ một bản luật duy nhất',
   'function _trongPhamVi(d){ return true; }' in src)
la('   và không còn bản chép so ô Cơ sở với chuỗi trên đơn',
   'd.nguoiLap===CURUSER.name || _trongCoSoToi(d)' not in src)
# ── THANH KHỐI (20/09/2026) KHÔNG ĐƯỢC NÚP SAU CHỐT PHẠM VI ─────────────────────────────────
# 🔴 Lọc theo KHỐI là chuyện CHỌN TAB: người dùng tự bấm, tự đổi lại, và thanh khối hiện SỐ ĐƠN
#    của từng khối nên không gì bị giấu lặng lẽ. Phạm vi QUYỀN thì khác hẳn — máy chủ giữ một
#    bản luật duy nhất. Nhét khối vào `_trongPhamVi()` là phá luôn cái guard ngay trên, và
#    khiến người sau tưởng khối là chuyện quyền hạn rồi đi thêm luật vào đó.
la('🔴 khối lọc ở hàm RIÊNG, không nhét vào chốt phạm vi',
   'function _hopKhoi(d)' in src and 'function _donHienDuoc(d){ return _trongPhamVi(d) && _hopKhoi(d); }' in src)
la('   và hai chỗ bày đơn (ô Chọn đơn · Danh sách đơn) dùng CHUNG chốt ấy',
   src.count('.filter(_donHienDuoc)') >= 2, [l.strip() for l in src.split('\n') if '_donHienDuoc)' in l][:3])
# ⚠️ Đơn CHƯA có dấu khối vẫn phải hiện: ẩn một bản ghi lọt lưới là tiền có thật mà không tab
#    nào thấy — hỏng theo hướng MẤT DỮ LIỆU, nặng hơn hẳn hướng bày thừa.
la('🔴 đơn chưa có dấu khối vẫn hiện ở mọi khối', "return k==='' || k===String(KHOI_DANG).toLowerCase();" in src)
# Dải phạm vi ĐÃ GỠ — anh Thắng 14/09/2026: *"loại bỏ này cho anh"*. Nó dựng để CHẨN ĐOÁN và
# đã làm xong việc ấy (chỉ ra "108 đơn của cơ sở khác", tìm đúng chốt sau bốn lượt vá mò). Xong
# việc thì gỡ: một dòng nhắc mỗi ngày rằng "bạn chỉ thấy cơ sở của mình" là thứ người dùng đọc
# đúng một lần rồi thôi, còn nó thì chiếm chỗ mãi mãi.
la('🔴 dải phạm vi đã gỡ khỏi màn', 'function vePhamVi(' not in src and 'phamViBox' not in src)
la('   và không còn dòng "Đã ẩn …" trên trang', 'function veDaChan(' not in src)
# ⚠️ PHẦN ĐẾM Ở MÁY CHỦ THÌ GIỮ: rẻ (ba phép cộng) và là công cụ đã chứng minh giá trị — lần sau
#    có ai "không thấy đơn" thì bật lại một dòng là biết chốt nào cắt, khỏi mò lại từ đầu.
_don_php2 = io.open(os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'includes', 'class-vhcp-don.php'),
                    encoding='utf-8').read()
la('⚠️ nhưng phần đếm ở máy chủ vẫn còn, để bật lại khi cần',
   'function so_da_chan()' in _don_php2 and "'daChan'" in _don_php2)

# ------------------------------------------------------------------ dọn mấy khối thừa
# Anh Thắng 14/09/2026: *"sẵn sửa trang chi phí loại bỏ mấy cái thừa"*.
print('— dọn khối thừa trên trang chi phí —')
# 🔴 Tab "Vận hành tuần" bỏ bằng đúng cơ chế đã có (`BO_TAB`), không xoá mã: bật lại chỉ là gỡ
#    một chữ, rẻ hơn hẳn dựng lại cả màn.
la('🔴 tab Vận hành tuần đã tắt', 'vhtuan:1' in src)
la('   và tắt bằng BO_TAB, không xoá mã màn', 'function loadVHTuan' in src)
# 🔴 Bảng khai tay "Mảng kinh doanh → nhóm tài khoản" gỡ khỏi màn, nhưng DỮ LIỆU giữ nguyên:
#    "Mã tổng của mảng" vẫn ghi vào cột Nhóm TK của chính bảng ấy.
la('🔴 bảng Mảng → nhóm tài khoản đã gỡ khỏi màn', 'id="mangTkCard"' not in src)
la('⚠️ nhưng sổ mangTk giữ nguyên, không xoá dữ liệu', 'mangTk:data' in src)
# 🔴 Gỡ một bảng mà quên gác `el(...)` là `renderCfg()` ném lỗi ngay và CẢ tab Cấu hình trắng.
la('🔴 chỗ vẽ bảng đã gỡ có gác null', "if(el('cfgMangBody'))" in src)
la('   và hàm đọc hàng cũng gác', 'var _tb=el(tbodyId); if(!_tb) return [];' in src)
# 🔴 Nút "Dọn loại chưa khai mã" — một nút XOÁ HÀNG LOẠT đứng cạnh nút Lưu, trong bảng người ta
#    mở ra mỗi ngày. Dựng cho một lần dọn dữ liệu cũ; giữ lại là để một cú bấm nhầm xoá cả danh mục.
la('🔴 nút "Dọn loại chưa khai mã" đã gỡ', 'Dọn loại chưa khai mã' not in _re.sub(r'<!--[\s\S]*?-->', ' ', src))

# ------------------------------------------------------ dọn tiếp: gộp thẻ, gỡ thẻ, gập khối
print('— dọn đợt hai —')
# 🔴 Hai thẻ "tài khoản" gộp làm một: cùng biểu tượng 🏦, cùng chữ "tài khoản", lại nằm hai nhóm
#    cách xa nhau — người khai phải nhớ "tài khoản" nào ở đâu. Gộp CHỖ ĐỨNG, không trộn hai sổ.
la('🔴 thẻ "Tài khoản nhận tiền" đã gộp vào thẻ hệ thống tài khoản',
   'id="qrCard"' not in src and 'id="cfgQrStk"' in src)
# ⚠️ Dò cả CỤM NÚT, không dò mỗi tên hàm: `saveCfgQR()` còn xuất hiện ở chỗ định nghĩa hàm, nên
#    bản nháp chỉ dò tên vẫn xanh kể cả khi nút đã bị gỡ khỏi thẻ.
la('   và giữ nguyên nút Lưu riêng của nó',
   'onclick="saveCfgQR()">💾 Lưu tài khoản nhận tiền</button>' in src)
la('   tiêu đề nói rõ CẢ HAI nghĩa', 'hệ thống kế toán &amp; tài khoản nhận tiền' in src)
# 🔴 Thẻ "Mã gọi tắt" gỡ khỏi màn, nhưng LÕI TRA SỔ giữ nguyên: mã đã khai vẫn hiệu lực.
la('🔴 thẻ Mã gọi tắt đã gỡ khỏi màn', 'id="maTatCard"' not in src)
_auth = io.open(os.path.join(GOC, 'wordpress', 'vhcp-chi-phi', 'includes', 'class-vhcp-auth.php'),
                encoding='utf-8').read()
la('⚠️ nhưng lõi tra sổ mã gọi tắt giữ nguyên (gỡ màn ≠ xoá sổ)',
   'function so_ma_tat()' in _auth and '$tat = self::so_ma_tat();' in _auth)
# 🔴 Khối "chưa khai phân loại lớn" GẬP lại chứ không xoá: khối "ngoại lệ" bên dưới chỉ duyệt
#    theo MẢNG, nên không bày những cơ sở chưa khai phân loại — xoá là sáu chục gian ấy hết
#    đường khai mã chi phí, và không có câu lỗi nào báo cho ai biết.
la('🔴 khối "chưa khai phân loại lớn" vẫn còn, chỉ gập lại',
   'Chưa khai phân loại lớn' in src and '<details' in src)
la('   và nói ra còn bao nhiêu cơ sở đang chờ khai', "+le.length+' cơ sở)</summary>'" in src)
la('⚠️ ô tích của chúng vẫn dựng đủ, không mất cửa khai',
   'data-coso="\'+esc(c)+\'" onchange="kcInfo()"' in src)

# ------------------------------------------------ bảng TK Nợ của nhà thứ hai: GẬP, không bỏ
print('— bảng TK Nợ theo đơn vị —')
# 🔴 Anh Thắng 14/09/2026 chỉ vào "🔢 TK Nợ · POSH": *"bỏ cái này vì chi phí này là chi phí
#    kvc"*, rồi chốt *"ẩn thôi đừng bỏ"*. Bỏ hẳn thì mã tài khoản của nhà ấy còn nằm trong sổ
#    mà không còn cửa nào sửa — và nhìn màn thì tưởng đã sạch.
# ⚠️ CẮT THÂN HÀM rồi mới dò. Cả tệp có <details> ở dăm chỗ khác (khối "chưa khai phân loại
#    lớn", khối ngoại lệ…), nên dò trên cả tệp là xoá sạch đoạn này bài vẫn xanh.
_m_mx = _re.search(r'function renderTkNoMatrix\(\)\{(.*?)\n  \}', src, _re.S)
la('tìm thấy renderTkNoMatrix()', _m_mx is not None)
if _m_mx:
    _t_mx = _m_mx.group(1)
    la('🔴 khối đơn vị thứ hai trở đi gập bằng <details>',
       'if(nhom.length>1 && i>0){' in _t_mx and '<details' in _t_mx,
       'gập ở đây mới ẩn được bảng TK Nợ của nhà kia')
    la('   vòng lặp có đếm thứ tự để biết khối nào đứng đầu',
       'nhom.forEach(function(g, i){' in _t_mx)
    la('   khối đứng đầu vẫn mở sẵn, không bọc <details>',
       '} else {\n        h+=\'<div style="font-weight:800;color:#171417;margin:16px 0 6px;font-size:13px">\'+nhan+phu' in _t_mx)
    # ⚠️ GẬP CHỨ KHÔNG BỎ KHỎI DOM. saveCfgTkNoMx() gom mã bằng querySelectorAll('.mxNoBody
    #    input'); ô trong <details> đang đóng vẫn đếm, còn display:none / thôi không vẽ thì mã
    #    của cả nhà ấy bay sạch ngay lượt Lưu kế tiếp — im lặng, không một câu lỗi.
    la('⚠️ bảng của nhà bị gập VẪN ĐƯỢC DỰNG (ô còn trong DOM để lượt Lưu đọc được)',
       'than=\'<div class="tw"><table' in _t_mx and 'class="mxNoBody"' in _t_mx)
    # 🔴 Không gõ cứng tên nhà: kế toán POSH chỉ xem được mỗi nhà của họ, chốt cứng "POSH" là
    #    bảng duy nhất của họ cũng gập. Thứ tự quyết định, nên thêm nhà thứ ba tự chạy đúng.
    # ⚠️ SOI MÃ ĐÃ BỎ CHÚ THÍCH. Chú thích ngay trên có nhắc cả "POSH" lẫn "display:none" để
    #    giải thích vì sao KHÔNG dùng chúng — dò trên mã còn chú thích là hai phép dưới đây
    #    đỏ oan, mà đục mất mã thật thì lại xanh.
    _mx_ma = _re.sub(r'/\*[\s\S]*?\*/', ' ', _t_mx)
    _mx_ma = _re.sub(r'//[^\n]*', ' ', _mx_ma)
    la('⚠️ và KHÔNG giấu bằng display:none', 'display:none' not in _mx_ma)
    la('🔴 KHÔNG gõ cứng tên nhà nào trong điều kiện gập', 'POSH' not in _mx_ma,
       'chốt cứng là kế toán POSH mất luôn bảng của chính họ')

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép trên app.html' % dat)
