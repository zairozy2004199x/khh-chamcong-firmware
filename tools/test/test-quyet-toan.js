/**
 * Màn QUYẾT TOÁN sau khi bỏ khâu gom: hai bảng riêng + phân trang 15 đơn,
 * và cây phân cấp chi phí (Loại chi phí → danh sách → chi phí con).
 *
 * Lấy thẳng hàm trong app.html ra chạy — không chép lại luật ở đây, chép là rồi sẽ lệch
 * với app mà phép thử vẫn xanh.
 *
 *   node tools/test/test-quyet-toan.js
 */
const fs = require('fs');
const path = require('path');

const GOC  = path.join(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const DON  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-don.php'), 'utf8');
const API  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-api.php'), 'utf8');
const DB   = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-db.php'), 'utf8');
const APP  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-app.php'), 'utf8');
const CHAN = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-ghe/includes/class-vhg-chan.php'), 'utf8');

let dat = 0; const hong = [];
function t(ten, dieu, nhan) { if (dieu) { dat++; return; } hong.push(ten + (nhan === undefined ? '' : ' → nhận được: ' + JSON.stringify(nhan))); }
function teq(ten, mong, nhan) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(nhan), nhan); }

function layHam(ten) {
  const m = HTML.match(new RegExp('\\n  function ' + ten + '\\([\\s\\S]*?\\n  \\}'));
  if (!m) { console.error('HỎNG: không tìm thấy hàm ' + ten + ' trong app.html'); process.exit(1); }
  return m[0];
}

// ---------------------------------------------------------------- 1. bỏ khâu gom
t('bỏ tab "Gom hóa đơn"', !/id="tab-gom"/.test(HTML));
t('bỏ trang page-gom', !/id="page-gom"/.test(HTML));
t('bỏ hàm loadGom/renderGom', !/function (loadGom|renderGom)\s*\(/.test(HTML));
t('bỏ endpoint dayChoKeToan ở máy chủ', !/dayChoKeToan/.test(API));
t('bỏ hàm day_cho_ke_toan', !/function day_cho_ke_toan/.test(DON));
t('không còn chỗ nào dựng trạng thái "Chờ quản lý gom"',
  !/'Chờ quản lý gom'/.test(HTML), (HTML.match(/Chờ quản lý gom/g) || []).length);
t('gửi quyết toán đi THẲNG sang "Chờ quyết toán"',
  /function gui_quyet_toan[\s\S]{0,900}?'trang_thai' => 'Chờ quyết toán'/.test(DON));
// Bỏ màn Gom mà không dời đơn đang mắc kẹt = đơn biến mất khỏi mọi tab, tiền treo luôn.
t('CÓ phép dời đơn còn kẹt ở "Chờ quản lý gom" sang "Chờ quyết toán"',
  /function bo_khau_gom[\s\S]*?UPDATE \$t SET trang_thai=%s WHERE trang_thai=%s[\s\S]*?'Chờ quyết toán', 'Chờ quản lý gom'/.test(DB));
t('phép dời đó có chạy khi nâng cấp', /self::bo_khau_gom\(\);/.test(DB));
t('và SCHEMA_VERSION đã tăng để nâng cấp nổ ra', /SCHEMA_VERSION = '1\.6\.0'/.test(DB));
t('danh sách nhắc "đã cấp tiền — chưa nộp hóa đơn" được giữ lại', /id="qtBodyChuaNop"/.test(HTML));

// ---------------------------------------------------------------- 2. hai bảng
t('có bảng Chờ quyết toán', /id="qtBodyCho"/.test(HTML));
t('có bảng Đã quyết toán', /id="qtBodyXong"/.test(HTML));
t('bỏ ô chọn lọc cũ qtFilter', !/id="qtFilter"/.test(HTML));
t('vẫn giữ được xem theo tuần/kỳ (đối chiếu quỹ)', /id="qtCardTuan"/.test(HTML) && /_qtReconHtml/.test(HTML));

// ---------------------------------------------------------------- 3. phân trang
const PG = new Function('QT_MOI_TRANG', layHam('_qtPagerHtml') + '; return _qtPagerHtml;')(15);
teq('1 trang thì không vẽ thanh chuyển trang', '', PG('cho', 12, 1, 1));
const p1 = PG('cho', 40, 1, 3);
t('nhiều trang: có thanh chuyển trang', p1.length > 0);
t('trang 1: nói rõ đang xem đơn 1–15 trên 40', p1.indexOf('<b>1–15</b> trên <b>40</b>') >= 0, p1);
t('trang 1: nút "Trước" bị khoá', /‹ Trước<\/button>/.test(p1) && /disabled[^>]*>‹ Trước/.test(p1), p1);
const p2 = PG('cho', 40, 2, 3);
t('trang 2: hiện đơn 16–30', p2.indexOf('<b>16–30</b> trên <b>40</b>') >= 0, p2);
t('trang 2: cả Trước lẫn Sau đều bấm được', !/disabled[^>]*>‹ Trước/.test(p2) && !/disabled[^>]*>Sau ›/.test(p2));
const p3 = PG('cho', 40, 3, 3);
t('trang cuối: hiện đơn 31–40 (không phải 31–45)', p3.indexOf('<b>31–40</b> trên <b>40</b>') >= 0, p3);
t('trang cuối: nút "Sau" bị khoá', /disabled[^>]*>Sau ›/.test(p3), p3);
t('trang đang xem được tô đậm', /class="btn b-p" onclick="qtTrang\('cho',3\)">3</.test(p3), p3);
const pn = PG('xong', 500, 20, 34);
t('rất nhiều trang: rút gọn bằng dấu …', pn.indexOf('…') >= 0, pn);
t('rất nhiều trang: vẫn có nút về trang 1 và trang cuối',
  /qtTrang\('xong',1\)">1</.test(pn) && /qtTrang\('xong',34\)">34</.test(pn), pn);
t('15 đơn mỗi trang', /var QT_MOI_TRANG=15\b/.test(HTML));

// ---------------------------------------------------------------- 4. cây phân cấp chi phí
global.esc = v => String(v == null ? '' : v);
global.money = v => String(Number(v) || 0);
const LBC = new Function('esc', 'money', layHam('_linesByCosoHtml') + '; return _linesByCosoHtml;')(global.esc, global.money);

const mot = LBC({ lines: [
  { coso: 'ADV GO AN LẠC', nhom: 'Chi phí cơ sở', noiDung: 'khăn lau', thanhTien: 246000 },
  { coso: 'ADV GO AN LẠC', nhom: 'Chi phí cơ sở', noiDung: 'nước rửa chén', thanhTien: 211000 },
  { coso: 'ADV GO AN LẠC', nhom: 'Chi phí cơ sở', noiDung: 'ổ cắm', thanhTien: 259000, phatSinh: true },
] });
t('loại chi phí là tiêu đề cấp trên', mot.indexOf('▣ Loại chi phí: Chi phí cơ sở') >= 0, mot.slice(0, 300));
t('MỘT cơ sở thì KHÔNG dựng thêm dải tên cơ sở (chỗ thừa)',
  mot.indexOf('ADV GO AN LẠC') < 0, mot.slice(0, 400));
t('một cơ sở: tiêu đề nói "theo loại chi phí"', mot.indexOf('theo loại chi phí') >= 0);
t('chi phí con nằm TRONG loại đó, không tách thành mục riêng',
  mot.indexOf('vẫn thuộc "Chi phí cơ sở"') >= 0, mot);
t('chi phí con được đánh dấu + và thụt vào', /padding-left:26px/.test(mot) && />\+<\/span>/.test(mot));
teq('loại chi phí chỉ xuất hiện ĐÚNG MỘT LẦN (trước đây hiện 2 lần: đã xin + phát sinh)',
  1, (mot.match(/▣ Loại chi phí: Chi phí cơ sở/g) || []).length);
t('tổng của loại tính CẢ chi phí con', mot.indexOf('716000') >= 0, mot);
t('đếm đủ 3 dòng trong loại', mot.indexOf('· 3 dòng') >= 0);
t('nói rõ có bao nhiêu chi phí con', mot.indexOf('trong đó 1 chi phí con') >= 0);

const hai = LBC({ lines: [
  { coso: 'CƠ SỞ A', nhom: 'Chi phí cơ sở', noiDung: 'a', thanhTien: 100 },
  { coso: 'CƠ SỞ B', nhom: 'Chi phí cơ sở', noiDung: 'b', thanhTien: 200 },
] });
t('HAI cơ sở thì mới dựng dải tên cơ sở', hai.indexOf('🏢 CƠ SỞ A') >= 0 && hai.indexOf('🏢 CƠ SỞ B') >= 0, hai.slice(0, 400));
t('hai cơ sở: tiêu đề nói "theo cơ sở, rồi theo loại chi phí"', hai.indexOf('theo cơ sở, rồi theo loại chi phí') >= 0);
teq('mỗi cơ sở một khối loại chi phí riêng', 2, (hai.match(/▣ Loại chi phí/g) || []).length);
t('đơn rỗng thì báo tử tế', LBC({ lines: [] }).indexOf('Chưa có hạng mục nào') >= 0);

// ---------------------------------------------------------------- 5. Admin lên đơn được
const mNut = HTML.match(/var bn=el\('btnNewDon'\); if\(bn\) bn\.style\.display=\(([^)]*)\)/);
t('tìm được chỗ gác nút Tạo đơn mới', !!mNut);
const dk = mNut ? mNut[1] : '';
t('Admin lên đơn được (để chạy thử luồng)', dk.indexOf("role==='Admin'") >= 0, dk);
t('Nhân viên và Quản lý vẫn lên đơn được', dk.indexOf("role==='Nhân viên'") >= 0 && dk.indexOf("role==='Quản lý'") >= 0, dk);
t('Kế toán vẫn KHÔNG lên đơn', dk.indexOf('Kế toán') < 0, dk);

// ---------------------------------------------------------------- 6. khai nhanh loại chi phí
t('ô chọn loại có dòng "＋ Thêm loại chi phí mới…"', /＋ Thêm loại chi phí mới/.test(HTML));
t('có popup khai nhanh', /id="loaiNhanhModal"/.test(HTML));
t('gọi đúng cổng khai mã của máy chủ', /\.khaiChiPhiChoCoSo\(rec\)/.test(HTML));
t('cổng đó máy chủ có mở', /'khaiChiPhiChoCoSo'/.test(API));
// Nhân viên gõ thêm mã tài khoản vào sổ kế toán là chuyện khác hẳn việc nhập đơn.
t('chỉ vai trò được sửa cấu hình mới thấy dòng thêm mới',
  /function _duocKhaiLoai\(\)\{ return coCauHinh\(\); \}/.test(HTML)
  && /_duocKhaiLoai\(\) \? '<option value="'\+LN_THEM/.test(HTML));
t('ô chọn của ĐƠN cũng gắn dòng thêm mới', /names\.map\([\s\S]{0,120}?\)\.join\(''\)\+_lnDongThem\(\)/.test(HTML));
t('bắt được lựa chọn "thêm mới" rồi trả ô chọn về giá trị cũ', /function lnBatChon\(/.test(HTML) && /sel\.value=\(cuOld===undefined/.test(HTML));
t('nạp lại danh mục sau khi khai (không thì ô chọn vẫn thiếu cái vừa thêm)', /boot\(function\(\)\{[\s\S]{0,400}?fillNhom\(ten\)/.test(HTML));
// 141/331 là tài khoản BÊN TRẢ TIỀN — chặn ngay lúc khai, đừng để nó chui vào danh mục
// rồi mới lòi ra ở bảng xuất MISA thành "Nợ 141 · Có 141".
t('chặn khai TK Nợ bằng 141/331 ngay tại popup',
  /tk\.indexOf\('141'\)===0 \|\| tk\.indexOf\('331'\)===0/.test(HTML));

// Ô "Áp cho": phải liệt kê MỌI mảng, không chỉ mảng của cơ sở đang chọn.
// "Chi phí cơ sở" thì mảng nào cũng dùng — bắt khai lại 7 lần, mỗi lần phải mở một đơn ở
// cơ sở thuộc mảng đó, là không làm nổi.
global.BOOT = { cosoPll: {
  'farm phan thiết':'FARM MN', 'farm nha trang':'FARM MN', 'tàu estella':'TUTU MN',
  'vr sora':'EVENT VR MN', 'fz vũng tàu':'FZ MN', 'ghost hn':'EVENT GHOST MN',
  'snow hn':'EVENT SNOW MN', 'fz event':'EVENT FZ MN', 'chưa khai':'' } };
const dsMang = new Function('BOOT', layHam('_dsMang') + '; return _dsMang;')(global.BOOT);
const MANG = dsMang();
teq('liệt kê đủ 7 mảng, mỗi mảng một lần', 7, MANG.length);
t('có đủ tên các mảng thật',
  ['EVENT FZ MN','EVENT GHOST MN','EVENT SNOW MN','EVENT VR MN','FARM MN','FZ MN','TUTU MN']
    .every(m => MANG.indexOf(m) >= 0), MANG);
t('cơ sở chưa khai mảng thì không sinh ra mảng rỗng', MANG.indexOf('') < 0, MANG);
t('sắp theo bảng chữ cái', JSON.stringify(MANG) === JSON.stringify(MANG.slice().sort((a,b)=>a.localeCompare(b,'vi'))), MANG);
t('ô Áp cho dựng danh sách TÍCH NHIỀU MẢNG, không phải ô chọn một',
  /class="lnMang"/.test(HTML) && /id="lnMangDs"/.test(HTML));
// Soi mỗi _dsMang() là chưa đủ: popup có thể vẫn dựng từ một mảng duy nhất mà phép thử
// vẫn xanh (đã thử làm hỏng đúng kiểu đó và nó lọt). Phải soi CHỖ DÙNG.
t('popup DỰNG danh sách bằng _dsMang() (không phải chỉ mảng của cơ sở đang chọn)',
  /function moLoaiNhanh\([\s\S]*?var ds=_dsMang\(\);[\s\S]*?el\('lnMangDs'\)\.innerHTML=ds\.map/.test(HTML));
t('có nút Chọn tất cả / Bỏ chọn', /lnTickMang\(1\)/.test(HTML) && /lnTickMang\(0\)/.test(HTML));
t('gửi lên máy chủ đúng danh sách đã tích', /rec\.mangs=ms;/.test(HTML));
t('không tích mảng nào thì báo, không lặng lẽ khai hụt', /Tích ít nhất một mảng kinh doanh/.test(HTML));

// MỘT LOẠI CHI PHÍ · NHIỀU TK NỢ — popup phải nói được cả hai kiểu:
//   1) khác mảng thì khác mã  -> khai từng lần, mỗi lần tích mảng tương ứng
//   2) cùng một ô mà nhiều mã -> ô đánh dấu "Thêm mã nữa"
t('popup có ô đánh dấu "Thêm mã nữa" (1 loại · nhiều TK Nợ)', /id="lnThem"/.test(HTML));
t('và gửi cờ đó lên máy chủ', /them:\(el\('lnThem'\)&&el\('lnThem'\)\.checked\)\?1:0/.test(HTML));
t('mở popup thì bỏ tích sẵn (khai lần sau không vô tình cộng dồn mã)',
  /if\(el\('lnThem'\)\) el\('lnThem'\)\.checked=false;/.test(HTML));
t('nói rõ bỏ trống thì mã mới THAY mã cũ', /mã mới THAY mã cũ/.test(HTML));

// ---------------------------------------------------------------- 7. bỏ tab Sổ chi phí
t('tab Sổ chi phí đã nghỉ', /var BO_TAB=\{[^}]*sochi:1/.test(HTML));
// Ẩn tab mà bỏ luôn đường xuất là chôn sống chứng từ chưa xuất MISA — đúng cái bẫy đã
// gặp ở khâu gom.
t('vẫn xuất MISA được các dòng sổ chi phí cũ', /<option value="chi">💵 Sổ chi phí/.test(HTML));
t('không xóa dữ liệu sổ chi phí', /id="page-sochi"/.test(HTML));

// ---------------------------------------------------------------- 8. số lượng bắt buộc
t('ô Số lượng đánh dấu bắt buộc', /<label>Số lượng \*<\/label>/.test(HTML));
t('giao diện chặn khi thiếu số lượng', /Nhập SỐ LƯỢNG \(lớn hơn 0\)/.test(HTML));
t('máy chủ chặn lại lần nữa (app trên máy nào cũng gọi được cổng)',
  /function loi_thieu_so_luong/.test(DON) && (DON.match(/loi_thieu_so_luong\( \$rec \)/g) || []).length >= 2);

// ---------------------------------------------------------------- 9. chân trang pháp lý
t('trang app có chỗ dựng chân trang', /<!--VHCP_CHAN-->/.test(HTML));
t('render() có điền vào chỗ đó', /str_replace\( '<!--VHCP_CHAN-->', self::chan_block\(\), \$html \)/.test(APP));
// Tên công ty / MST / địa chỉ là MỘT sự thật. Chép sang plugin thứ hai là hôm đổi địa chỉ
// phải nhớ sửa hai nơi, và nơi quên thì im lặng nói sai.
t('đọc từ VHG_Chan chứ không chép lại thông tin công ty', /VHG_Chan::html\(\)/.test(APP));
t('KHÔNG có bản sao mã số thuế trong plugin chi phí', !/0106924989/.test(APP + HTML));
t('chưa cài plugin Ghế thì để trống, không bịa', /\) \) \{ return ''; \}/.test(APP) && /class_exists\( 'VHG_Chan' \)/.test(APP));
// 23/08/2026: bản trước gọi thẳng một hàm VỪA THÊM bên plugin Ghế, chỉ gác class_exists.
// Máy anh Thắng chạy Ghế bản cũ -> lớp CÓ, hàm KHÔNG -> trắng cả trang WordPress.
t('gác TỪNG HÀM chứ không chỉ tên lớp (2 plugin cài độc lập, bản lệch nhau được)',
  /! class_exists\( 'VHG_Chan' \) \|\| ! method_exists\( 'VHG_Chan', 'html' \)/.test(APP));
t('lấy bố cục qua hàm css() cũng phải gác', /method_exists\( 'VHG_Chan', 'css' \) \? VHG_Chan::css\(\)/.test(APP));
t('màu nền sáng thuộc về app chi phí, không đòi bản Ghế mới',
  /function chan_css_sang/.test(APP) && !/css_sang/.test(CHAN));
// Chân trang là thẻ cuối của body: trang ít nội dung thì nó dính ngay dưới bảng, treo lơ
// lửng giữa màn hình với khoảng trắng to bên dưới — trông như trang bị đứt.
t('chân trang luôn nằm dưới đáy trang',
  /body\{min-height:100vh;display:flex;flex-direction:column\}/.test(APP)
  && /\.vhg-chan\{margin-top:auto;width:100%\}/.test(APP));

// ---------------------------------------------------------------- 10. mỗi khâu một tab
// "Chờ quyết toán" / "Đã quyết toán" thuộc tab 🧾 Quyết toán. Để chúng ở tab Duyệt tạm ứng
// thì cùng một đơn nằm hai chỗ, mà chỗ đó lại không làm được gì với nó.
const mLoc = HTML.match(/<select id="duyetFilter"[\s\S]*?<\/select>/);
t('tìm được ô lọc của tab Duyệt tạm ứng', !!mLoc);
const LOC = mLoc ? mLoc[0] : '';
t('bỏ "Chờ quyết toán" khỏi tab Duyệt tạm ứng', LOC.indexOf('Chờ quyết toán') < 0, LOC);
t('bỏ "Đã quyết toán" khỏi tab Duyệt tạm ứng', LOC.indexOf('Đã quyết toán') < 0, LOC);
t('vẫn còn đủ 3 khâu tạm ứng',
  LOC.indexOf('Chờ duyệt tạm ứng') >= 0 && LOC.indexOf('Chờ cấp tạm ứng') >= 0 && LOC.indexOf('Đã cấp tạm ứng') >= 0, LOC);
t('nhãn "Cần xử lý" thôi nhắc quyết toán', /Cần xử lý \(chờ duyệt \/ chờ gửi tiền\)/.test(LOC), LOC);
// Bỏ khỏi ô chọn mà "Tất cả" vẫn kéo về là bỏ hụt.
t('"Tất cả" cũng chỉ trong khâu tạm ứng',
  /var KHAU_TU=\['Chờ duyệt tạm ứng','Chờ cấp tạm ứng','Đã cấp tạm ứng'\];/.test(HTML)
  && /if\(KHAU_TU\.indexOf\(d\.trangThai\)<0\) return false;/.test(HTML));
t('"Cần xử lý" = chờ duyệt + chờ gửi tiền, không còn quyết toán',
  /if\(f==='cho'\) return \['Chờ duyệt tạm ứng','Chờ cấp tạm ứng'\]\.indexOf/.test(HTML));
// Cắt tab mà quên đường dẫn tới là tạo liên kết chết — đúng bẫy đã gặp ở khâu gom.
t('Tổng quan đưa đơn "Chờ quyết toán" sang tab Quyết toán, không phải Duyệt',
  /tab:\(d\.trangThai==='Chờ quyết toán'\?'qt':'duyet'\)/.test(HTML));
t('gỡ dòng chết "→ xử lý ở tab Quyết toán" trong bảng Duyệt',
  !/→ xử lý ở tab 🧾 Quyết toán/.test(HTML));

// ---------------------------------------------------------------- 11. lọc tháng/tuần/cơ sở
// Kế toán đối chiếu theo TUẦN với từng cơ sở. Tab Duyệt tạm ứng có bộ lọc này từ lâu,
// màn Quyết toán thì không — phải lật từng trang bảng để nhặt đơn của một tuần.
t('màn Quyết toán có đủ 3 ô lọc',
  /id="qtThang"/.test(HTML) && /id="qtKy"/.test(HTML) && /id="qtCoso"/.test(HTML));
t('có nút Bỏ lọc', /onclick="qtXoaLoc\(\)"/.test(HTML) && /function qtXoaLoc\(\)/.test(HTML));
t('dùng CHUNG bộ dựng ô lọc với tab Duyệt tạm ứng (không chép luật lọc ra bản thứ hai)',
  /_napLocDon\(moiDon, 'qtThang', 'qtKy', 'qtCoso'\)/.test(HTML));
// Dựng ô lọc từ danh sách ĐÃ LỌC thì chọn một tuần xong là mất luôn các tuần khác khỏi ô.
t('ô lọc dựng từ TOÀN BỘ đơn của màn, không phải từ danh sách đã lọc',
  /var moiDon=\(BOOT\.dons\|\|\[\]\)\.filter[\s\S]{0,220}?_napLocDon\(moiDon/.test(HTML));
t('lọc áp cho bảng Chờ và Đã quyết toán', /_qtLoc\(d\) && \(d\.trangThai==='Chờ quyết toán'/.test(HTML));
t('và áp cho cả bảng "chưa nộp hóa đơn"', /d\.trangThai==='Đã cấp tạm ứng' && _qtLoc\(d\)/.test(HTML));
t('lọc theo đúng 3 tiêu chí', /_thangCuaKy\(d\.ky\)!==fT/.test(HTML) && /String\(d\.ky\|\|''\)!==fK/.test(HTML) && /String\(d\.coso\|\|''\)!==fC/.test(HTML));

// ---------------------------------------------------------------- kết
if (hong.length) {
  console.error('\nĐẠT: ' + dat + ' phép thử');
  console.error('HỎNG: ' + hong.length);
  hong.forEach(h => console.error('  ✗ ' + h));
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép thử');
console.log('Tất cả phép thử đều đạt.');
