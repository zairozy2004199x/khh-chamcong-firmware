/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG LOẠI CHI PHÍ — MỖI ĐẦU MỤC MỘT BẢNG — PHÍA MÀN HÌNH, BỐC HÀM THẬT RA CHẠY.
 *
 * Anh Thắng 21/09/2026: *"chỗ loại chi phí, chia ra 3 bảng của 3 khối, để tránh dùng chung"*,
 * rồi 22/09/2026 đổi trục: *"Khối là dùng chung, vì đã phân theo vai trò rồi"* · *"Khối là để
 * xác định tài khoản nợ"* · *"Mỗi đầu mục là 1 bảng riêng,,"*.
 *
 * 🔴 ĐỔI TRỤC CHIA BẢNG, KHÔNG ĐỔI DỮ LIỆU — và đó chính là chỗ bài này canh gắt nhất. Mỗi
 *    dòng VẪN mang đúng một `khoi`, vì bảng mã TK Nợ bên dưới vẫn cắt theo khối. Trước bản
 *    này khối của một dòng đọc từ mốc `data-khoi` của tbody; nay tbody mang ĐẦU MỤC, nên bỏ
 *    sót một chỗ là lượt Lưu đóng TÊN ĐẦU MỤC vào cột `khoi` của mọi dòng — mọi loại rơi khỏi
 *    bảng mã, im lặng.
 *
 * =============================================================================================
 * 🔴 LỖI ĐÃ CẮN NGAY LƯỢT DỰNG ĐẦU, VÀ BÀI NÀY GIỮ NÓ
 * =============================================================================================
 * Vẽ xong ba bảng, mở Chromium ra nhìn thì bảng Máy tự động hiện **1 loại** trong khi dữ liệu
 * gieo có **2**. Nguyên do: vòng gom hàng khử trùng tên theo MỖI TÊN, nên dòng "Chi phí khác"
 * của MTĐ bị nuốt vì KVC đã có một dòng cùng tên.
 *
 * Đúng cái nó phải cho phép. *"Tránh dùng chung"* nghĩa là mỗi khối MỘT BẢN GHI RIÊNG, trùng
 * tên cũng không sao — sửa mã bên này không đụng bên kia. Khử theo tên là giữ nguyên cái dùng
 * chung, chỉ khác là nay nó biến mất hẳn thay vì bày ra ba chỗ. Im lặng, và chỉ lộ ra khi có
 * người đếm số dòng trên màn.
 *
 * ⚠️ Cùng cái bẫy ấy còn ba chỗ nữa trong luồng: khoá trùng tên lúc Lưu, bảng tra mã cũ (`cu`),
 *    và lượt điền hộ Tên MISA. Bài này canh cả bốn.
 *
 * Chạy: node tools/test/kiem-loai-3-bang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocSach(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }
function bocBien3(ten) {
  const i = HTML.indexOf('  var ' + ten + '=');
  const j = HTML.indexOf('];', i) + 2;
  return (i >= 0 && j > i) ? HTML.slice(i, j) : '';
}

['_khoiCuaLoai', '_mxBodies', 'addCfgLoai'].forEach(function (x) {
  t('bốc được `' + x + '`', bocHam(x).length > 40, x);
});

/* ═══ 1. 🔴 KHỬ TRÙNG PHẢI THEO KHỐI|TÊN Ở CẢ BỐN CHỖ ════════════════════════════ */
const VE = bocSach('renderTkNoMatrix');
t('🔴 vòng gom hàng khử trùng theo KHỐI|TÊN', /_khoiCuaLoai\(x\)\s*\+\s*'\|'/.test(VE), 'không thấy');
const LUU = bocSach('saveCfgTkNoMx');
/* Khối lấy từ Ô CHỌN trên hàng (`khoiR`), không phải từ bảng chứa nó (`khoiB`) — anh Thắng
   21/09/2026: *"chỗ đơn vị thay bằng khối, để khối nào thì nó nằm trong khối đó"*. Đổi ô rồi
   bấm Lưu là dòng phải nhảy sang bảng khối mới; khoá trùng tên vì thế cũng theo khối MỚI. */
t('🔴 khoá trùng tên lúc Lưu theo khối ĐÃ CHỌN', /khoiR\s*\+\s*'\|'\s*\+\s*ten\.toLowerCase\(\)/.test(LUU), 'không thấy');
t('🔴 và khối ghi xuống lấy từ ô chọn, không từ bảng chứa', /khoi:khoiR/.test(LUU), 'không thấy');
t('   ô chọn khối có mặt trên từng hàng', /_khoiSelLoai\(_khoiCuaLoai\(x\)\)/.test(bocSach('_mxRowHtml')), 'không thấy');
t('🔴 và là Ô CHỌN, không phải ô tích (một loại thuộc ĐÚNG MỘT khối)',
  /<select data-khoi-o/.test(bocSach('_khoiSelLoai')) && !/checkbox/.test(bocSach('_khoiSelLoai')), 'không thấy');
t('   cột Đơn vị cũ đã rời khỏi hàng', !/_dvSelNhieu\(x\.donVi/.test(bocSach('_mxRowHtml')));
t('🔴 lượt Lưu GIỮ giá trị đơn vị cũ, không ghi rỗng đè',
  /var dvL=\(goc\.donVi/.test(LUU) && /donVi:dvL/.test(LUU), 'không thấy');
t('🔴 bảng tra mã cũ (`cu`) khoá theo khối', /cu\[_khoiCuaLoai\(x\)\s*\+\s*'\|'/.test(LUU), 'không thấy');
/* ⚠️ BỐC ĐÚNG HÀM, ĐỪNG ĐỂ FALLBACK `|| HTML`. Lượt viết đầu em dò trong `bocSach('…') || HTML`
   với một tên hàm ĐOÁN SAI — hàm rỗng, fallback nhảy vào cả trang, và phép xanh vĩnh viễn.
   Một phép không bao giờ đỏ được thì không phải phép thử. */
t('bốc được `onPickTk` (nơi điền hộ Tên MISA)', bocHam('onPickTk').length > 200, bocHam('onPickTk').length);
t('🔴 lượt điền hộ Tên MISA dò khắp BA bảng', /_mxBodies\(\)\.some/.test(bocSach('onPickTk')), 'không thấy');
/* Gỡ chú thích trước khi dò — chính chú thích giải thích lượt sửa này có nhắc lại `el('cfgMxBody')`
   cũ, và phép dưới bắt đúng mấy chữ ấy. Lần thứ ba trong tuần dẫm cái bẫy "xanh/đỏ nhờ lời văn". */
const HTML_SACH = HTML.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/<!--[\s\S]*?-->/g, ' ');
t("   và KHÔNG còn bám vào một id `cfgMxBody` duy nhất", !/el\('cfgMxBody'\)/.test(HTML_SACH), 'vẫn còn');

/* ═══ 2. Ô CHỌN LÚC NHẬP ĐƠN LỌC THEO KHỐI ═══════════════════════════════════════
 * Chia ở Cấu hình mà ô chọn vẫn xổ đủ ba khối thì chia chẳng để làm gì. */
t('🔴 `_loaiCpList()` bỏ loại của khối khác', /_khoiCuaLoai\(x\)!==String\(KHOI_DANG\)/.test(bocSach('_loaiCpList')), 'không thấy');
t('   `_cacNhomCp()` cũng vậy', /_khoiCuaLoai\(x\)!==String\(KHOI_DANG\)/.test(bocSach('_cacNhomCp')), 'không thấy');
/* 🔴 ĐỔI 21/09/2026 — anh Thắng: *"Chưa có TK nợ theo máy tự động"*, rồi *"mỗi khối 1 bảng
   mã tk riêng"*. Trước đây bảng mã lọc bằng `KHOI_DANG` — biến của THANH KHỐI bên màn Đơn
   chi phí. Trang Cấu hình không có thanh ấy, nên nó đứng im ở khối nhớ từ lần trước và
   mã của hai khối kia KHÔNG AI KHAI ĐƯỢC. Nay mỗi mục tự mang khối của nó (`g.khoi`).
   ⚠️ VẪN còn vế ngã về `KHOI_DANG`, cho nhóm không đoán được khối ("(chưa rõ khối)") — trả
      rỗng là mã của mấy mảng đó không còn ai sửa được. */
t('🔴 bảng mã TK Nợ lấy khối của CHÍNH mục đó, không theo khối đang chọn ở màn khác',
  /var khoiB=g\.khoi\|\|String\(KHOI_DANG\)\.toLowerCase\(\);/.test(VE)
    && /rows\.filter\(function\(x\)\{ return _khoiCuaLoai\(x\)===khoiB; \}\)/.test(VE), 'không thấy');
/* 🔴 TỪ 22/09/2026 DỰNG THEO `_mienDs()`, không theo cả từ điển khối — anh Thắng: *"Khối là
   để xác định tài khoản nợ"*, và khối nay là MIỀN. Lấy `KHOI_DS` (từ điển đầy đủ) là dựng ra
   ba mục rỗng vĩnh viễn của khối đã ra web riêng. */
t('   và mỗi MIỀN có một mục, kể cả miền chưa có cơ sở nào',
  /var bay=_mienDs\(\);/.test(bocSach('_mxNhomDv'))
  && /bay\.map\(function\(k\)\{ return \{ dv:k\.ma, khoi:k\.ma/.test(bocSach('_mxNhomDv')), 'không thấy');
/* ⚠️ Khối CŨ còn mảng vẫn ra một mục riêng, và phải GIỮ MÃ KHỐI của nó — trả `''` là mục ấy
   rơi về `KHOI_DANG` ở chỗ lọc loại, tức bảng mã của khối này bày loại của khối kia. */
t('   khối cũ còn mảng vẫn có mục riêng, mang đúng mã của nó',
  /khoi:\(KHOI_DS\.some\(function\(x\)\{ return x\.ma===k; \}\) \? k : ''\)/.test(bocSach('_mxNhomDv')), 'không thấy');
t('   và thôi lọc bằng ô Đơn vị của loại (ô ấy đã gỡ)', !/_loaiChoDv\(x, g\.dv\)/.test(VE));
t('   đổi khối thì vẽ lại bảng Cấu hình', /renderTkNoMatrix\(\)/.test(bocSach('doiKhoi')), 'không thấy');

/* ═══ 3. MỖI ĐẦU MỤC MỘT BẢNG, VÀ MỖI BẢNG MỘT NÚT "＋ THÊM LOẠI" ════════════════ */
t('🔴 không còn nút thêm loại chung ở đầu thẻ', !/onclick="addCfgLoai\(\)"/.test(HTML));
/* 🔴 CẢ HAI NÚT (đầu bảng VÀ cuối bảng), không phải "có chỗ nào đó đúng". Cắn ngay lượt phá
   thử 22/09/2026: đục một trong hai nút sang mã khối thì phép này vẫn xanh nhờ nút còn lại. */
teq('   CẢ HAI nút thêm (đầu bảng + cuối bảng) đều mang ĐẦU MỤC của nó', 2,
  (VE.match(/onclick="addCfgLoai\('\+esc\(JSON\.stringify\(dm0\)\)\+'\)"/g) || []).length);
t('🔴 `addCfgLoai()` chối người chưa thuộc khối nào', /_khoiDuoc\(\)/.test(bocSach('addCfgLoai')), 'không thấy');
t('   và mở cái <details> đang gập ra', /\.open\s*=\s*true/.test(bocSach('addCfgLoai')), 'không thấy');
/* 🔴 MỐC CỦA TBODY ĐỔI TỪ KHỐI SANG ĐẦU MỤC — và CẢ BA chỗ đọc nó phải đổi theo, không thì
   một nửa đường dây nói khối còn nửa kia nói đầu mục. */
t('🔴 thân bảng mang `data-dau-muc`', /data-dau-muc="'\+esc\(dm0\)\+'"/.test(VE), 'không thấy');
t('🔴 và KHÔNG còn đóng dấu `data-khoi` lên tbody (nay nó là đầu mục)',
  !/data-khoi="'\+esc\(k\.ma\)\+'"/.test(VE), 'vẫn còn');
t('   `addCfgLoai()` tìm bảng theo `data-dau-muc`', /getAttribute\('data-dau-muc'\)/.test(bocSach('addCfgLoai')), 'không thấy');
t('🔴 lượt Lưu KHÔNG còn lấy khối từ mốc của tbody', !/getAttribute\('data-khoi'\)/.test(LUU), 'vẫn còn');
t('   mà lấy đầu mục ở đó', /getAttribute\('data-dau-muc'\)/.test(LUU), 'không thấy');
/* 🔴 KHỐI LÚC VẼ PHẢI NẰM TRÊN CHÍNH HÀNG. Mất mốc này là lượt Lưu không dò được bản ghi cũ,
   và mấy cột không có ô trên màn (TK Nợ, mã đối tượng, bộ phận) bay sạch — im lặng. */
t('🔴 hàng mang `data-khoi-goc` (khối lúc vẽ)', /data-khoi-goc="'\+esc\(_khoiCuaLoai\(x\)\)\+'"/.test(bocSach('_mxRowHtml')), 'không thấy');
t('   và lượt Lưu dò bản cũ bằng nó', /getAttribute\('data-khoi-goc'\)/.test(LUU), 'không thấy');
/* ⚠️ Bảng nay gom theo đầu mục nên một bảng chứa lẫn dòng của cả ba khối — khoá cả bảng như
   bản trước là hoặc khoá oan dòng của chính họ, hoặc mở toang dòng của khối khác. */
t('🔴 khoá theo TỪNG DÒNG, không theo cả bảng', /_khoaDongKhoiLa\(\)/.test(VE)
  && /data-khoi-la/.test(bocSach('_khoaDongKhoiLa')), 'không thấy');
t('   và cú bấm 🔓 KHÔNG mở được mấy dòng ấy',
  /:not\(\[data-khong-mo\]\)/.test(bocSach('toggleMxLock')), 'không thấy');
t('🔴 lượt Lưu chép nguyên bản cũ cho dòng khối lạ, không tin mỗi cái khoá trên màn',
  /data-khoi-la/.test(LUU), 'không thấy');
t('   đầu mục lạ (đã rời danh sách) vẫn có bảng, không nuốt dòng', /dmDs\.indexOf\(d\)<0/.test(VE), 'không thấy');
t('   và ô hứng "Chưa xếp đầu mục" đứng CUỐI', /dmDs\.push\(''\)/.test(VE), 'không thấy');

/* ── chạy thật: `_khoiCuaLoai` ─────────────────────────────────────────────────── */
const vm = require('vm');
function chay(khoiBan) {
  const ctx = { window: {}, BOOT: { khoiBan: khoiBan || 'kvc' } };
  ctx.window.BOOT = ctx.BOOT;
  vm.createContext(ctx);
  vm.runInContext(bocHam('_khoiCuaLoai'), ctx);
  return ctx;
}
let c = chay('kvc');
teq('`_khoiCuaLoai` đọc đúng khối đã khai', 'mtd', c._khoiCuaLoai({ khoi: 'MTD'.toLowerCase() }));
teq('   không phân biệt hoa thường / khoảng trắng', 'vp', c._khoiCuaLoai({ khoi: '  VP ' }));
/* 🔴 Ô RỖNG KHÔNG ĐƯỢC TRẢ RỖNG. Trả '' là loại ấy không khớp bảng nào — nó rơi khỏi cả ba,
   khỏi ô chọn lúc nhập đơn, khỏi mọi cột mã, trong khi tiền mang tên nó vẫn nằm trong sổ.
   Máy chủ lấp mỗi lượt nạp, nhưng màn phải chịu được cái khoảnh khắc chưa lấp. */
teq('🔴 ô khối RỖNG rơi về khối của bản đang chạy, KHÔNG rỗng', 'kvc', c._khoiCuaLoai({ khoi: '' }));
teq('   và loại không có ô khối cũng vậy', 'kvc', c._khoiCuaLoai({}));
teq('   bản vùng thì rơi về khối của bản ấy', 'vp', chay('vp')._khoiCuaLoai({}));

/* ═══ 4. 🔴 ĐỐI CHỨNG SỐNG: HAI KHỐI CÙNG TÊN, CẢ HAI PHẢI CÒN ═══════════════════
 * Phép soi chữ ở mục 1 nói "có viết đúng biểu thức"; phép này đếm kết quả thật. Bản đầu trượt
 * đúng ở đây: bảng MTĐ ra 1 dòng thay vì 2. */
const DS = [
  { ten: 'Chi phí khác', khoi: 'kvc' },
  { ten: 'Chi phí khác', khoi: 'mtd' },
  { ten: 'Thuê mặt bằng', khoi: 'mtd' },
  { ten: 'VPP', khoi: 'vp' },
];
/* Chạy lại đúng vòng gom hàng của `renderTkNoMatrix()`, không chép tay luật. */
const ctx2 = { CFG: { loaiChiPhi: DS }, BOOT: { khoiBan: 'kvc' }, window: {}, rows: [], seen: {}, coTen: {} };
ctx2.window.BOOT = ctx2.BOOT;
vm.createContext(ctx2);
vm.runInContext(bocHam('_khoiCuaLoai'), ctx2);
/* ⚠️ Mốc bốc bám ĐẦU dòng khai, không bám nguyên văn `var rows=[], seen={};`: từ 21/09/2026
   dòng ấy khai thêm `coTen` (chữa lỗi "xoá xong nó vẫn còn" — xem
   `kiem-xoa-loai-khong-moc-lai.js`). Bám nguyên văn là mỗi lần thêm một biến lại đỏ oan. */
const VONG = VE.slice(VE.indexOf('var rows=[], seen={}'), VE.indexOf('var mx={};'));
t('bốc được vòng gom hàng để chạy', VONG.length > 60, VONG.length);
vm.runInContext(VONG, ctx2);
teq('🔴 gom đủ 4 dòng — "Chi phí khác" của MTĐ KHÔNG bị nuốt', 4, ctx2.rows.length);
const theo = {};
ctx2.rows.forEach(function (x) { (theo[x.khoi] = theo[x.khoi] || []).push(x.ten); });
teq('   MTĐ có đủ 2 loại của nó', ['Chi phí khác', 'Thuê mặt bằng'], theo.mtd);
teq('   KVC giữ dòng của mình', ['Chi phí khác'], theo.kvc);

/* ═══ 5. 🔴 TÍCH THEO VAI TRÒ, KHÔNG CÒN THEO BỘ PHẬN ════════════════════════════
 * Anh Thắng 21/09/2026: *"bỏ tích bộ phận đi, mà tích theo vai trò"*, *"cho full danh sách vai
 * trò, để ai làm anh tích vào"*. */
t('🔴 hàng bảng loại chi phí tích theo VAI TRÒ', /_vaiSelNhieu\(x\.vaiTro/.test(bocSach('_mxRowHtml')), bocSach('_mxRowHtml'));
t('   không còn ô tích bộ phận ở đó', !/_bpSelNhieu/.test(bocSach('_mxRowHtml')));
t('🔴 đầu bảng đổi nhãn theo', /Vai trò được dùng/.test(VE) && !/>Bộ phận <span/.test(VE), 'không thấy');
const LUU2 = bocSach('saveCfgTkNoMx');
t('🔴 lượt Lưu đọc ô tích vai', /\[data-vai\] input:checked/.test(LUU2), 'không thấy');
t('   và KHÔNG đọc ô tích bộ phận nữa', !/\[data-bp\] input:checked/.test(LUU2));
/* 🔴 Bộ phận cũ phải được CHÉP LẠI từ bản cũ, không đọc màn: cột ấy không còn ô nào, đọc ra
   rỗng rồi ghi xuống là xoá sạch dữ liệu anh Thắng đã khai. */
t('🔴 lượt Lưu chép lại bộ phận cũ thay vì ghi rỗng', /boPhan:\(goc\.boPhan\|\|''\)/.test(LUU2), LUU2.slice(0, 200));

/* ── chạy thật `_vaiSelNhieu` và `_vaiDungDuocLoai` ────────────────────────────── */
{
  const VG = ['Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'];
  const ctxv = { VAI_GOC: VG, CURUSER: { role: 'Kế Toán Khu Vui Chơi' },
    CFG: { vaiTro: [
      { ten: 'Quản Lý Khu Vui Chơi', goc: 'Quản lý' },
      { ten: 'Kế Toán Khu Vui Chơi', goc: 'Kế toán cá nhân' },
      { ten: 'Kế Toán Máy Tự Động', goc: 'Kế toán cá nhân' } ] },
    esc: (x) => String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;') };
  vm.createContext(ctxv);
  ctxv.KHOI_THEO_TEN_VAI = JSON.parse(JSON.stringify(
    new Function(bocBien3('KHOI_THEO_TEN_VAI') + '\nreturn KHOI_THEO_TEN_VAI;')()));
  vm.runInContext('var KHOI_THEO_TEN_VAI=' + JSON.stringify(ctxv.KHOI_THEO_TEN_VAI) + ';\n'
    + ['_boDauVai', '_khoiCuaVai', '_vaiOKhoi', '_vaiBay',
       '_vaiConCua', '_vaiSelNhieu', '_vaiDungDuocLoai'].map(bocHam).join('\n'), ctxv);

  /* ⚠️ TẠI SAO PHẢI TRUYỀN KHỐI VÀO — anh Thắng 21/09/2026: *"Loại chi phí theo Khối, Ai có ở
     khối nào mới hiện ra"*. Từ bản 1.235.0 hộp ô tích chỉ bày vai CỦA KHỐI ẤY cộng vai
     chạy ngang; gọi không kèm khối là không khối nào khớp và hộp trụi xuống còn bốn vai gốc. */
  const hv = ctxv._vaiSelNhieu('Kế Toán Máy Tự Động', 'kvc');
  /* 🔴 VAI GỐC THÔI LÀ Ô TÍCH (22/09/2026) — anh Thắng: *"Vai trò đang tích lẻ, nên vai trò
     chung không dùng nữa"*. Trước bản này ở đây đếm 7 (4 vai gốc + 2 vai con KVC + 1 vai MTĐ
     đang tích); nay còn 3 ô tích, bốn vai gốc thành NHÃN NHÓM.
     Vì sao bỏ: `_vaiDungDuocLoai()` so TÊN VAI người ta mang với danh sách, và vai gốc KHÔNG
     tự suy cho vai con (có phép canh ngay dưới). Nên tích "Quản lý" mà không tích vai con nào
     là mọi quản lý thật đều KHÔNG thấy loại ấy — ô tích trông như mở cả nhóm mà thật ra đóng
     sạch. */
  teq('🔴 bảng KVC bày 2 vai con KVC + 1 vai lạc đang tích — vai GỐC thôi là ô tích', 3,
    (hv.match(/type="checkbox"/g) || []).length);
  t('🔴 vai gốc nay là NHÃN NHÓM, không phải ô tích',
    /<b style="font-size:11.5px[^"]*"[^>]*>Quản lý<\/b>/.test(hv) && hv.indexOf('value="Quản lý"') < 0, hv.slice(0, 500));
  t('   và mỗi nhóm có nút ✓ hết / ✕ bỏ để tích lẻ đỡ mệt',
    /onclick="vaiNhomHet\(this,1\)"/.test(hv) && /onclick="vaiNhomHet\(this,0\)"/.test(hv), '');
  /* 🔴 PHÉP ĐỐI CHỨNG — VÀ NÓ ĐÃ ĐỔI NGHĨA NGÀY 21/09/2026.
     Bản 1.235.0 giấu hẳn vai khối khác khi chưa tích, nên ở đây đếm ra ít hơn. Nhưng giấu hẳn
     nghĩa là KHÔNG CÓ CÁCH NÀO tích mới một vai khối khác — mà anh Thắng nói rõ: *"1 người
     có thể nhận 2 vai trò của 2 khối khác nhau"*. Nay chúng nằm trong nếp gấp "vai khối khác":
     VẪN ĐỦ Ô, chỉ là một ô nằm trong `<details>`. */
  const hTrong = ctxv._vaiSelNhieu('', 'kvc');
  teq('🔴 không tích gì thì vẫn đủ 3 ô — vai khối khác chỉ gấp lại, không biến', 3,
    (hTrong.match(/type="checkbox"/g) || []).length);
  /* ═══ 🔴 VAI CHUNG CÒN SÓT TRONG SỔ: GIẤU ĐI, KHÔNG XOÁ ═══════════════════════
     Danh mục thật đang có dòng tích vai gốc. Bỏ ô tích ra khỏi DOM là lượt Lưu kế tiếp xoá
     đúng mấy vai ấy — người bấm Lưu chỉ định sửa một ô khác hẳn. */
  const hSot = ctxv._vaiSelNhieu('Quản lý', 'kvc');
  t('🔴 vai chung còn sót VẪN nằm trong DOM và vẫn `checked` (lượt Lưu không được xoá lặng lẽ)',
    /<input type="checkbox" data-vai-chung value="Quản lý" checked style="display:none">/.test(hSot), hSot.slice(0, 900));
  t('   và có dòng nhắc để kế toán tự thấy',
    /data-vai-sot/.test(hSot) && hSot.indexOf('Còn tích vai chung') >= 0, '');
  t('🔴 kèm HAI đường gỡ, do người quyết — nở ra vai con, hoặc bỏ hẳn',
    /onclick="vaiNoChung\(this\)"/.test(hSot) && /onclick="vaiBoChung\(this\)"/.test(hSot), '');
  t('   loại KHÔNG tích vai chung thì không có dòng nhắc nào', !/data-vai-sot/.test(hTrong));
  /* 🔴 KHÔNG TỰ NỞ LÚC VẼ. Nở là ĐỔI QUYỀN; máy làm thay là đổi quyền trong im lặng. */
  t('🔴 lúc VẼ không tự nở vai chung ra vai con — đó là cú bấm của người',
    !/value="Quản Lý Khu Vui Chơi" checked/.test(hSot), hSot.slice(0, 900));
  t('🔴 và nếp ấy ĐÓNG khi không có ô nào đang tích',
    /<details class="vaiNgoai"(?! open)/.test(hTrong), hTrong);
  t('🔴 nhưng MỞ SẴN khi bên trong có ô đang tích — không để quyền đã khai nằm khuất',
    /<details class="vaiNgoai" open/.test(hv), hv);
  t('   và ô trong nếp vẫn giữ dấu tích', /value="Kế Toán Máy Tự Động" checked/.test(hv), hv);
  t('   vai đã tích được đánh dấu', /value="Kế Toán Máy Tự Động" checked/.test(hv), hv);
  t('   vai chưa tích thì không', !/value="Kế Toán Khu Vui Chơi" checked/.test(hv));
  /* 🔴 Admin không bao giờ bị lọc — bày ô tích cho Admin là ô bấm vào không đổi gì. */
  t('🔴 KHÔNG bày ô tích cho Admin', hv.indexOf('value="Admin"') < 0, hv);

  const L = (v) => ({ ten: 'X', vaiTro: v });
  t('🔴 chưa tích ai = mọi vai dùng được', ctxv._vaiDungDuocLoai(L('')));
  t('vai có trong danh sách thì dùng được', ctxv._vaiDungDuocLoai(L('Kế Toán Khu Vui Chơi, Quản Lý Khu Vui Chơi')));
  t('🔴 vai KHÔNG có trong danh sách thì không', !ctxv._vaiDungDuocLoai(L('Kế Toán Máy Tự Động')));
  t('🔴 VAI GỐC của vai đang mang cũng không tự động qua', !ctxv._vaiDungDuocLoai(L('Kế toán cá nhân')));
  ctxv.CURUSER = { role: 'Admin' };
  t('🔴 Admin qua hết', ctxv._vaiDungDuocLoai(L('Kế Toán Máy Tự Động')));
}
t('🔴 ô chọn lúc nhập đơn có lọc theo vai', /_vaiDungDuocLoai\(x\)/.test(bocSach('_loaiCpList')), 'không thấy');
t('   và dãy nút "Chọn chi phí nào" cũng vậy', /_vaiDungDuocLoai\(x\)/.test(bocSach('_cacNhomCp')), 'không thấy');

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: mỗi đầu mục một bảng, khối vẫn đi theo từng dòng (cho TK Nợ), trùng tên khác khối không nuốt nhau.');
