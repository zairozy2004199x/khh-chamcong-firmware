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

# ---------------------------------------------------------------- vai tự tạo dùng được
print('— vai tự tạo phải dùng được —')
# 🔴 LỖI THẬT: bảng tab tra theo TÊN VAI. Vai tự tạo không có trong bảng nên rơi vào nhánh
#    mặc định {don:1} — vai vừa tạo ra chỉ còn đúng MỘT tab, tức tính năng tạo vai vô nghĩa.
la('có hàm lấy vai gốc phía giao diện', 'function _vaiGoc(' in src)
la('bảng tab tra theo VAI GỐC', '}[_vaiGoc()]||{don:1}' in src)
la('  không còn tra theo tên vai', '}[role]||{don:1}' not in src)
la('nhánh bộ phận cũng theo vai gốc', "if(_vaiGoc()==='Nhân viên'){" in src)
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
la('có nhãn LOẠI ĐƠN', '>LOẠI ĐƠN<' in src)
la('nút đổi thành Chi phí · cơ sở', 'Chi phí · cơ sở' in src)
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
la('dải trạng thái in tên trạng thái cỡ lớn', "font-size:17px;font-weight:800" in src)
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

# Cấu hình: hai cột Đơn vị / Xem đơn vị — hai việc khác nhau, không gộp.
la('bảng người dùng có cột Đơn vị', '>Đơn vị</th>' in src)
la('và cột Xem đơn vị', '>Xem đơn vị</th>' in src)
la('lưu người dùng gửi kèm cả hai ô', 'donVi:(r[7]' in src and 'xemDonVi:(r[8]' in src)
# Ô Đơn vị là ô NHẬP kèm gợi ý, không phải ô xổ đóng: chi nhánh mới phải khai được ngay.
la('ô Đơn vị nhập được tự do (có datalist gợi ý)',
   'function _dvInp(' in src and 'list="dl_donvi"' in src)

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
la('hàng 2 gom trạng thái + lịch sử chỉnh đơn', 'class="db-hang2"' in src)
la('có ô đệm đẩy nhóm nút về mép phải', 'class="db-day"' in src)
for _id in ['donSel', 'btnNewDon', 'btnChuyenDV', 'btnDelDon', 'btnDelDonAdmin', 'btnAction']:
    _i = src.index('id="%s"' % _id)
    la('%s nằm ở hàng 1' % _id,
       src.rindex('class="db-hang1"', 0, _i) > src.rindex('class="bar don-bar"', 0, _i)
       and 'class="db-hang2"' not in src[src.rindex('class="db-hang1"', 0, _i):_i])
for _id in ['donBadge', 'donSuBox']:
    _i = src.index('id="%s"' % _id)
    la('%s nằm ở hàng 2' % _id, 'class="db-hang2"' in src[:_i].rsplit('class="db-hang1"', 1)[-1])
la('ba lớp có kiểu chữ thật trong tệp css',
   '.db-hang1{' in css and '.db-hang2{' in css and '.db-day{' in css)
# Hàng 2 rỗng thì không được treo một khoảng trắng trông như lỗi.
la('hàng 2 chỉ chiếm chỗ khi có nội dung', '.db-hang2:not(:empty){margin-top' in css)

print()
if hong:
    print('🔴 HỎNG: %d | ĐẠT: %d' % (hong, dat))
    sys.exit(1)
print('✓ SẠCH — %d phép trên app.html' % dat)
