/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CƠ SỞ THEO KHỐI · BỘ PHẬN ĐỌC TỪ TÊN VAI · HAI LOẠI ĐƠN CỦA KỸ THUẬT.
 *
 * Anh Thắng 21/09/2026, bốn câu trong một buổi:
 *   · *"Đơn vị cơ sở theo khối, các cơ sở hiện tại đang KVC, chuyển cột Đơn Vị sang Khối"*
 *   · *"Giờ tiếp đến Nhân Viên Kỹ Thuật Khu Vui chơi"*
 *   · *"Chia ra 2 loại: Chi Phí Dự Án (1 cơ sở 1 đơn) · Chi Phí Tuần (nhiều cơ sở cho 1 đơn)"*
 *   · *"Chi Phí Cơ Sở chỉ dành cho nhân viên cơ sở"*
 *
 * =============================================================================================
 * 🔴 BA CÂU SAU CÓ CHUNG MỘT GỐC, VÀ ĐÓ MỚI LÀ CHỖ PHẢI CHỮA
 * =============================================================================================
 * Thẻ 🔧 Chi phí Kỹ thuật bày BA nút thay vì hai, trong đó có cả "📅 Đơn tuần của cơ sở" — thứ
 * anh vừa chốt là chỉ dành cho nhân viên cơ sở. Nút ấy hiện vì `_vaoDonCoSo('')` trả true khi
 * bộ phận RỖNG. Mà bộ phận rỗng với mọi người, vì cột Bộ phận đã rời bảng Người dùng 1.232.0:
 * *"dùng hết trên vai trò cha, con rồi"*.
 *
 * Cột đi rồi nhưng NHỮNG LUẬT ĐỌC NÓ THÌ CÒN NGUYÊN, và chúng đọc ra chuỗi rỗng — tức "không
 * bó gì", tức ai cũng vào được mọi lối. Đọc lại bộ phận từ TÊN VAI CON là nút ấy tự tắt, khỏi
 * phải đi ẩn tay từng chỗ.
 *
 * ⚠️ VÀ CÁI NÀY ÁP CHO CẢ BA KHỐI — anh Thắng: *"Máy tự động và Văn phòng cùng dùng chung kiểu
 *    chi phí kỹ thuật KVC"*. Nên đừng ai thêm nhánh rẽ theo khối vào đây.
 *
 * Chạy: node tools/test/kiem-khoi-coso-va-ky-thuat.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const PHP_DV = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-donvi.php', 'utf8');
const PHP_DON = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }
/* ═══ 🔴 ĐỪNG GỠ CHÚ THÍCH TRÊN CẢ TỆP — ĐÃ CẮN NGAY LƯỢT VIẾT NÀY ═════════════
   Bản nháp dùng `HTML.replace(/\\/\\*[\\s\\S]*?\\*\\//g,' ')` rồi dò nhãn nút trong đó — và ba
   phép đỏ oan. Lý do: `app.html` có cả CSS lẫn JS, mà cả hai dùng chung kiểu chú thích
   `/* … *``/`. Một dấu mở trong `<style>` bắt cặp với một dấu đóng mãi dưới `<script>`, nuốt
   trọn thân trang ở giữa — tức là nuốt luôn mọi nút, mọi nhãn. Đếm thử thì rõ: chuỗi
   "daChonNhom('coso')" có 1 lần trong HTML và 0 lần sau khi gỡ.

   🔴 Và đây là kiểu hỏng TỆ NHẤT của một bài kiểm: phép "X không còn nữa" trên một chuỗi
      rỗng thì LUÔN XANH. Nó không đỏ oan — nó không bao giờ đỏ.

   Nên: nhãn và nút dò trên HTML NGUYÊN; chỉ gỡ chú thích trên TỪNG THÂN HÀM đã bốc ra. */
function sachHam(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }
/* Phép đối chứng cho chính cái bẫy trên: nếu ai đó quay lại gỡ chú thích cả tệp thì phép này
   đỏ, kèm đúng lý do. */
t('🔴 (đối chứng) gỡ chú thích cả tệp là nuốt mất thân trang — đừng dùng cách ấy',
  HTML.replace(/\/\*[\s\S]*?\*\//g, ' ').indexOf("daChonNhom('coso')") < 0
    && HTML.indexOf("daChonNhom('coso')") >= 0);

/* ═══ 1. BỘ PHẬN ĐỌC RA TỪ TÊN VAI CON ══════════════════════════════════════════ */
const BP = new Function('CURUSER',
  bocMang('BP_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_bpCuaVai') + '\n' + bocHam('_bpCuaToi')
  + '\nreturn { vai:_bpCuaVai, toi:_bpCuaToi };');
const bpVai = (v) => BP(null).vai(v);
teq('🔴 «Nhân Viên Kỹ Thuật Khu Vui Chơi» → bộ phận Kỹ thuật', 'Kỹ thuật', bpVai('Nhân Viên Kỹ Thuật Khu Vui Chơi'));
teq('🔴 «Nhân Viên Cơ Sở Khu Vui Chơi» → bộ phận Cơ sở', 'Cơ sở', bpVai('Nhân Viên Cơ Sở Khu Vui Chơi'));
teq('   và cùng luật ở khối Máy tự động', 'Kỹ thuật', bpVai('Nhân Viên Kỹ Thuật Máy Tự Động'));
/* 🔴 XÉT THEO TỪ, KHÔNG THEO CHUỖI CON. "Remarketing" chứa trọn chữ "marketing" nhưng không
   phải bộ phận Marketing. Đổi `' '+tu+' '` thành một `indexOf` trần là phép này đỏ — và đây
   là chốt phải giữ, vì bảng từ khoá có ngày được thêm một từ NGẮN (VD "KT", "VP") — lúc ấy
   chuỗi con bắt nhầm hàng loạt, mà bắt nhầm bộ phận là mở nhầm lối lên đơn. */
teq('🔴 «Nhân Viên Remarketing» KHÔNG phải bộ phận Marketing', '', bpVai('Nhân Viên Remarketing'));
teq('   còn «Nhân Viên Marketing» thì đúng', 'Marketing', bpVai('Nhân Viên Marketing'));
teq('   «Nhân Viên Marketing» → Marketing', 'Marketing', bpVai('Nhân Viên Marketing'));
/* ⚠️ KHÔNG ĐOÁN ĐƯỢC = ĐỂ TRỐNG, giữ đúng nghĩa cũ "không bó gì, thấy hết". Hiểu ngược là
   ngày bản này lên, ai mang vai không nói rõ mảng mất sạch lối vào — hỏng theo hướng khoá cửa. */
teq('⚠️ vai không nói rõ mảng → để TRỐNG, không đoán bừa', '', bpVai('Quản Lý Chung'));
teq('   vai gốc cũng vậy', '', bpVai('Nhân viên'));
/* 🔴 Ô ĐÃ KHAI TAY VẪN THẮNG — giá trị cũ còn trong sổ và vẫn đúng; đoán đè lên nó là tự ý
   sửa khai báo của người ta. */
teq('🔴 ô bộ phận đã khai tay thắng phép đoán', 'Setup',
  BP({ role: 'Nhân Viên Kỹ Thuật Khu Vui Chơi', boPhan: 'Setup' }).toi());
teq('   chưa khai thì mới đoán từ tên vai', 'Kỹ thuật',
  BP({ role: 'Nhân Viên Kỹ Thuật Khu Vui Chơi', boPhan: '' }).toi());

/* ═══ 2. 🔴 HỆ QUẢ: KỸ THUẬT KHÔNG CÒN THẤY LỐI "ĐƠN TUẦN CỦA CƠ SỞ" ════════════
 * Đây là phép nối hai đầu — chốt anh Thắng nói ("chi phí cơ sở chỉ dành cho nhân viên cơ sở")
 * chỉ đúng khi bộ phận đọc ra được. Thiếu nó, `_vaoDonCoSo('')` trả true và nút vẫn hiện. */
const VD = new Function('CURUSER', 'BP_KHONG_DON_COSO',
  bocMang('BP_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_bpCuaVai') + '\n' + bocHam('_bpCuaToi')
  + '\n' + bocHam('_vaoDonCoSo') + '\nreturn _vaoDonCoSo(_bpCuaToi());');
const KHONG = (/var BP_KHONG_DON_COSO=\[([^\]]*)\]/.exec(HTML) || [, "'Kỹ thuật'"])[1]
  .split(',').map(x => x.trim().replace(/^'|'$/g, '')).filter(Boolean);
t('🔴 Kỹ thuật KHÔNG vào đơn tuần của cơ sở nữa',
  VD({ role: 'Nhân Viên Kỹ Thuật Khu Vui Chơi', boPhan: '' }, KHONG) === false);
t('🔴 nhưng nhân viên CƠ SỞ thì vẫn vào được',
  VD({ role: 'Nhân Viên Cơ Sở Khu Vui Chơi', boPhan: '' }, KHONG) === true);
t('   và vai không rõ mảng vẫn vào được (hỏng theo hướng bày thừa)',
  VD({ role: 'Nhân viên', boPhan: '' }, KHONG) === true);

/* ═══ 3. HAI LOẠI ĐƠN CỦA KỸ THUẬT, ĐÚNG CHỮ ANH ĐẶT ════════════════════════════ */
t('🔴 lối "Chi phí tuần" nói rõ NHIỀU cơ sở cho 1 đơn', HTML.indexOf('Nhiều cơ sở cho 1 đơn') >= 0);
t('🔴 lối "Chi phí dự án" nói rõ 1 cơ sở 1 đơn', HTML.indexOf('1 cơ sở 1 đơn') >= 0);
/* ⚠️ SOI ĐÚNG HAI CÁI NÚT, ĐỪNG QUÉT CẢ TỆP. Bản nháp dò chuỗi "🏢 Chi phí cơ sở<" trên
   cả trang và đỏ oan — vì nó bắt trúng một `<option>` hoàn toàn khác: loại chi phí tên "Cơ
   sở" ở hộp chọn nhóm. Cái ấy đúng và phải ở lại; anh Thắng nói về TÊN MỘT LỐI LÊN ĐƠN. */
function nhanNut(id) {
  const i = HTML.indexOf('id="' + id + '"');
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('</button>', i));
}
t('🔴 nút lên đơn của Kỹ thuật không còn tên "Chi phí cơ sở"',
  nhanNut('daNhomCs').indexOf('Chi phí cơ sở') < 0
    && nhanNut('ndLoaiDaCoSo').indexOf('Chi phí cơ sở') < 0,
  [nhanNut('daNhomCs'), nhanNut('ndLoaiDaCoSo')]);
t('   mà tên "Chi phí tuần"', nhanNut('daNhomCs').indexOf('Chi phí tuần') >= 0
    && nhanNut('ndLoaiDaCoSo').indexOf('Chi phí tuần') >= 0);
/* ⚠️ LỐI CỦA NHÂN VIÊN CƠ SỞ thì GIỮ nguyên tên — anh chỉ bảo chữ ấy thuộc về họ, không
   bảo bỏ nó. Bỏ luôn là nhân viên cơ sở mất chỗ lên đơn. */
t('⚠️ lối "Đơn tuần của cơ sở" vẫn còn cho nhân viên cơ sở',
  nhanNut('ndLoaiCoSo').indexOf('Đơn tuần của cơ sở') >= 0);
/* ⚠️ MÃ `coso` / `dacoso` GIỮ NGUYÊN — anh bảo đổi CHỮ, không bảo đổi mã. Đổi mã là đụng máy
   chủ và mọi đơn cũ. */
t('⚠️ mã lối vẫn là `coso` / `dacoso` / `duan`',
  HTML.indexOf("daChonNhom('coso')") >= 0 && HTML.indexOf("ndChonLoai('dacoso')") >= 0
    && HTML.indexOf("daChonNhom('duan')") >= 0);
/* ⚠️ MỘT KIỂU CHO CẢ BA KHỐI — anh Thắng: *"Máy tự động và Văn phòng cùng dùng chung kiểu chi
   phí kỹ thuật KVC"*. Khối không được len vào chỗ quyết định bày lối nào. */
t('⚠️ khối KHÔNG chen vào khối tạo đơn của Kỹ thuật',
  !/KHOI_DANG|khoiBan/.test(sachHam('daMoTao') + sachHam('daChonNhom')), 'có');

/* ═══ 3b. 🔴 CHỮ TRÊN NÚT ĐẾN TỪ `_tenNhom()`, KHÔNG TỪ HTML ════════════════
 * ĐÂY LÀ CHỖ BẢN 1.238.0 ĐÃ HỎNG, nên phải có phép giữ lại. Bản ấy đổi chữ ở thẻ `<button>`
 * trong HTML, bài kiểm tĩnh xanh, gói cài có đúng chữ mới — nhưng `_apTenNhom()` GHI ĐÈ nhãn mỗi
 * lượt mở hộp, nên anh Thắng mở màn ra vẫn thấy nguyên ba nút chữ cũ: *"Sao vẫn hiện 3"*.
 *
 * ⚠️ LƯỢT SOI TRÌNH DUYỆT CŨNG KHÔNG BẮT ĐƯỢC, vì nó đọc `textContent` của nút mà KHÔNG gọi
 *    `_apTenNhom()` trước — tức là vẫn chỉ đọc chữ trong HTML, chỉ khác đường đi. Mở trình duyệt
 *    mà bỏ bước dựng thật thì cũng chỉ là một phép dò chữ khác.
 * ════════════════════════════════════════════════════════════════════════════════════════ */
const BANG_TEN = new Function((/var TEN_LOAI_BP=\{[\s\S]*?\n  \};/.exec(HTML) || ['var TEN_LOAI_BP={};'])[0]
  + '\nreturn TEN_LOAI_BP;')();
const TN = new Function('CURUSER', 'TEN_LOAI_BP', 'nhom',
  bocMang('BP_THEO_TEN_VAI') + '\n' + bocHam('_boDauVai') + '\n' + bocHam('_bpCuaVai') + '\n'
  + bocHam('_bpCuaToi') + '\n' + bocHam('_tenNhom') + '\nreturn _tenNhom(nhom);');
const tn = (nhom) => TN({ role: 'Nhân Viên Kỹ Thuật Khu Vui Chơi', boPhan: '' }, BANG_TEN, nhom);
teq('🔴 tên lối tuần lấy từ `_tenNhom()` là chữ MỚI', '🗓 Chi phí tuần', tn('coso').ten);
teq('🔴 và phụ đề nói NHIỀU cơ sở cho 1 đơn', 'Nhiều cơ sở cho 1 đơn', tn('coso').phu);
teq('🔴 lối dự án nói 1 cơ sở 1 đơn', '1 cơ sở 1 đơn · Setup / Tháo dỡ', tn('duan').phu);
/* Phép đối chứng: chữ ở HTML và chữ ở `_tenNhom()` phải KHỚP nhau. Lệch là người đọc mã thấy
   một đằng, người dùng thấy một nẻo — đúng cái bẫy vừa mắc. */
t('🔴 chữ ở HTML và chữ ở `_tenNhom()` khớp nhau',
  nhanNut('daNhomCs').indexOf(tn('coso').ten) >= 0 && nhanNut('daNhomCs').indexOf(tn('coso').phu) >= 0,
  nhanNut('daNhomCs'));
/* 🔴 NÚT THỨ BA GÁC BẰNG LUẬT BỘ PHẬN, không bằng `_tabDuoc('don')`: bảng Phân quyền có thể MỞ
   THÊM tab Đơn cho một vai, và lúc ấy nút hiện lại dù người ấy là Kỹ thuật — đúng ảnh anh
   Thắng chụp. Vế này thiếu ở 1.238.0. */
t('🔴 nút "Đơn tuần của cơ sở" gác bằng luật bộ phận, không chỉ bằng tab',
  /_tabDuoc\('don'\) && _vaoDonCoSo\(_bpCuaToi\(\)\)/.test(sachHam('daMoTao')), sachHam('daMoTao'));


/* ═══ 4. CƠ SỞ: CỘT ĐƠN VỊ → CỘT KHỐI ══════════════════════════════════════════ */
t('đầu bảng cơ sở đã là cột Khối', HTML.indexOf('>Khối</th>') >= 0);
const CS = sachHam('_khoiSelCoso');
t('bốc được `_khoiSelCoso`', CS.length > 100, CS.length);
t('🔴 là Ô CHỌN — một gian nằm ở ĐÚNG MỘT khối', /<select/.test(CS) && !/checkbox/.test(CS));
const K = new Function('BOOT', 'esc', 'dv',
  bocMang('KHOI_DS') + '\n' + bocDong('KHOI_DV_DUP') + '\n' + bocHam('_khoiDvBang') + '\n'
  + bocHam('_khoiCuaDv') + '\n' + bocHam('_dvChuanCuaKhoi') + '\n' + bocHam('_tenKhoi') + '\n'
  + bocHam('_dvMacDinh') + '\n' + bocHam('_khoiSelCoso') + '\nreturn _khoiSelCoso(dv);');
const BOOT = { donVi: ['K&H'], khoiTheoDv: { kvc: ['KVC'], mtd: ['MTĐ', 'MTD', 'POSH'], vp: ['VP'] } };
const esc = (x) => String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
const ops = (h) => [...h.matchAll(/<option value="([^"]*)"( selected)?>([^<]*)</g)].map(m => m[1] + (m[2] ? ' ✓' : '') + ' = ' + m[3]);
teq('cơ sở đang KVC → chọn sẵn Khu vui chơi', true,
  ops(K(BOOT, esc, 'KVC')).indexOf('KVC ✓ = Khu vui chơi') >= 0);
/* 🔴 CHỐT ĐẮT NHẤT CỦA BẢN NÀY. Một khối có NHIỀU tên đơn vị ("MTĐ", "MTD", "POSH" cùng là
   khối máy tự động). Nếu ô chọn luôn ghi tên CHUẨN thì gian đang khai "POSH" âm thầm thành
   "MTĐ" ở lượt Lưu đầu tiên. `khoi_cua()` vẫn ra đúng khối nên nhìn bên ngoài không thấy gì,
   NHƯNG `VHCP_DonVi::xem_duoc()` so ĐÚNG NGUYÊN VĂN chuỗi đơn vị — nên mọi tài khoản khai
   "POSH" lập tức thiếu gian ấy khỏi sổ của họ. Không một câu lỗi. */
const hPosh = ops(K(BOOT, esc, 'POSH'));
t('🔴 gian đang khai POSH giữ NGUYÊN VĂN "POSH", không bị đổi thành MTĐ',
  hPosh.indexOf('POSH ✓ = Máy tự động') >= 0, hPosh);
t('   nhưng ô của khối KHÁC thì mang tên chuẩn của khối ấy',
  hPosh.indexOf('KVC = Khu vui chơi') >= 0, hPosh);
/* 🔴 GIÁ TRỊ LẠ KHÔNG ÁNH XẠ ĐƯỢC PHẢI GIỮ MỘT DÒNG RIÊNG — danh mục dựng từ sổ cũ, có gian
   còn mang tên đơn vị ngoài bảng; bỏ nó là một cú Lưu đổi nhà cho gian đó mà không ai biết. */
const hLa = ops(K(BOOT, esc, 'K&H'));
t('🔴 đơn vị lạ được giữ lại một dòng riêng, chọn sẵn',
  hLa.some(x => x.indexOf('K&amp;H ✓') === 0), hLa);
teq('   ô trống thì nói rõ là chưa khai', true,
  ops(K(BOOT, esc, '')).some(x => x.indexOf('chưa khai') >= 0));

/* ═══ 5. ⚠️ BẢNG ÁNH XẠ GỬI TỪ MÁY CHỦ, KHÔNG CHÉP TAY ═════════════════════════ */
t('🔴 gói khởi động mang bảng đơn vị → khối', /'khoiTheoDv' => VHCP_DonVi::KHOI_THEO_DON_VI/.test(PHP_DON));
t('🔴 và màn đọc từ gói ấy trước, bản dự phòng chỉ để chạy với gói cũ',
  /BOOT\.khoiTheoDv/.test(bocHam('_khoiDvBang')), 'không thấy');
/* Phép đối chứng: bản dự phòng phải KHỚP bảng thật của máy chủ — lệch là màn xếp cơ sở vào
   khối này trong khi máy chủ đọc ra khối khác. */
const mPhp = /const KHOI_THEO_DON_VI = array\(([\s\S]*?)\);/.exec(PHP_DV);
t('đọc được bảng ánh xạ ở máy chủ', !!mPhp);
if (mPhp) {
  const php = {};
  for (const m of mPhp[1].matchAll(/'(\w+)'\s*=>\s*array\(([^)]*)\)/g)) {
    php[m[1]] = m[2].split(',').map(x => x.trim().replace(/^'|'$/g, '')).filter(Boolean);
  }
  const js = new Function(bocDong('KHOI_DV_DUP') + '\nreturn KHOI_DV_DUP;')();
  teq('🔴 bản dự phòng ở màn KHỚP từng chữ với bảng của máy chủ', php, js);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: cơ sở theo khối, bộ phận đọc từ tên vai, Kỹ thuật còn đúng hai loại đơn.');
