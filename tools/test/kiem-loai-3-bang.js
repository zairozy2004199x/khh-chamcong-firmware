/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BA BẢNG LOẠI CHI PHÍ THEO KHỐI — PHÍA MÀN HÌNH, BỐC HÀM THẬT RA CHẠY.
 *
 * Anh Thắng 21/09/2026: *"chỗ loại chi phí, chia ra 3 bảng của 3 khối, để tránh dùng chung"*.
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
t('🔴 bảng mã TK Nợ chỉ bày loại của khối đang chọn',
  /rows\.filter\(function \(x\) \{ return _khoiCuaLoai\(x\)===String\(KHOI_DANG\)/.test(VE.replace(/\s+/g, ' ')) ||
  /return _khoiCuaLoai\(x\)===String\(KHOI_DANG\)\.toLowerCase\(\);/.test(VE), 'không thấy');
t('   và thôi lọc bằng ô Đơn vị của loại (ô ấy đã gỡ)', !/_loaiChoDv\(x, g\.dv\)/.test(VE));
t('   đổi khối thì vẽ lại bảng Cấu hình', /renderTkNoMatrix\(\)/.test(bocSach('doiKhoi')), 'không thấy');

/* ═══ 3. NÚT "＋ THÊM LOẠI" CHUNG ĐÃ BỎ, MỖI KHỐI MỘT NÚT ════════════════════════ */
t('🔴 không còn nút thêm loại chung ở đầu thẻ', !/onclick="addCfgLoai\(\)"/.test(HTML));
t('   mỗi bảng có nút thêm mang mã khối của nó', /onclick="addCfgLoai\(\\'/.test(VE), 'không thấy');
t('🔴 `addCfgLoai()` chối khối không thuộc', /_khoiDuoc\(\)/.test(bocSach('addCfgLoai')), 'không thấy');
t('   và mở cái <details> đang gập ra', /\.open\s*=\s*true/.test(bocSach('addCfgLoai')), 'không thấy');
t('🔴 thân bảng mang `data-khoi`', /data-khoi="'\+esc\(k\.ma\)\+'"/.test(VE), 'không thấy');
t('   khối không thuộc vẫn HIỆN (gập + ổ khoá), không bị bỏ', /🔒 /.test(VE) && /mxKhoi/.test(VE));

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
const ctx2 = { CFG: { loaiChiPhi: DS }, BOOT: { khoiBan: 'kvc' }, window: {}, rows: [], seen: {} };
ctx2.window.BOOT = ctx2.BOOT;
vm.createContext(ctx2);
vm.runInContext(bocHam('_khoiCuaLoai'), ctx2);
const VONG = VE.slice(VE.indexOf('var rows=[], seen={};'), VE.indexOf('var mx={};'));
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
  vm.runInContext(['_vaiConCua', '_vaiSelNhieu', '_vaiDungDuocLoai'].map(bocHam).join('\n'), ctxv);

  const hv = ctxv._vaiSelNhieu('Kế Toán Máy Tự Động');
  teq('🔴 bày ĐỦ danh sách: 4 vai gốc + 3 vai con', 7, (hv.match(/type="checkbox"/g) || []).length);
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
console.log('\n✓ SẠCH — ' + DAT + ' phép: ba bảng tách thật, trùng tên khác khối không nuốt nhau, ô chọn lọc theo khối.');
