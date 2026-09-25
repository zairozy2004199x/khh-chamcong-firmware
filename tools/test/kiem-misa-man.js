/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * XUẤT MISA — MÀN PHẢI NỐI ĐÚNG VÀO LÕI.
 *
 * Anh Thắng 25/09/2026: *"Giờ bắt đầu bóc tách và xuất dữ liệu ra misa"*. `kiem-misa.php` chạy thật máy chủ
 * (bóc tách combo, mã hàng, đã xuất). Bài này canh phần màn ở tab Quản trị:
 *   · taiQuanTri gọi taiMisa; GET misa?tu&den&cua_hang&tt; hỏng thì khoiLoi (không nuốt);
 *   · khối #dtMisa: ô kỳ / cửa hàng / trạng thái + nút Xem; bảng chứng từ; cảnh báo; nút Tải Excel / CSV /
 *     Đánh dấu; bảng từng dòng bật tắt; ba bảng khai (tài khoản, cơ sở, mặt hàng) với nút lưu riêng;
 *   · tải Excel: nạp xlsx từ cdnjs LÚC BẤM, hỏng thì lùi CSV có BOM; tải xong hỏi đánh dấu đã xuất;
 *   · POST viec=cf|cs|mh|da_xuat|bo_xuat kèm JSON; lưu xong vẽ lại từ bản máy chủ trả về;
 *   · chạy thật veMisa trên DOM giả: 2 chứng từ -> 2 dòng bảng, dòng có cảnh báo mang lớp qua-han.
 *
 * Chạy: node tools/test/kiem-misa-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.js', 'utf8');
const css = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.css', 'utf8');
const php = fs.readFileSync('wordpress/khh-doanh-thu/khh-doanh-thu.php', 'utf8');
const js = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/(^|[^:'"])\/\/[^\n]*/g, '$1');
let dat = 0; const hong = [];
function t(ten, dk) { if (dk) dat++; else hong.push(ten); }
function boc(ten) {
  const i = js.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = js.indexOf('\n  }\n', i);
  return js.slice(i, j + 4);
}

t('plugin nạp misa.php', /require_once KHH_DT_DIR \. 'misa\.php';/.test(php));
/* Anh Thắng 25/09/2026: "Tách TAB XUẤT MISA RA 1 TAB RIÊNG NHÉ". */
t('🔴 có tab Xuất MISA (#dtTabMS, khung #dtTabMisa), ẩn tới khi cf.duoc_nap; doiTab gọi taiMisa; Quản trị KHÔNG còn gọi', /data-tab="misa" id="dtTabMS" hidden>Xuất MISA/.test(js) && /id="dtTabMisa"/.test(js) && /q\('#dtTabMS'\)\.hidden = !cf\.duoc_nap;/.test(js) && /if \(t === 'misa'\) taiMisa\(\);/.test(boc('doiTab')) && !/taiMisa/.test(boc('taiQuanTri')));
t('taiMisa không tham số thì vẽ vào #dtTabMisa, có chữ "Đang tính" trong lúc chờ', /o = o \|\| q\('#dtTabMisa'\);/.test(boc('taiMisa')) && /Đang tính chứng từ/.test(boc('taiMisa')));
const tm = boc('taiMisa');
t('taiMisa GET misa với tu/den/cua_hang/tt; mặc định kỳ = đầu tháng → hôm nay', /api\(misaDuong\(\)\)/.test(tm) && /MISA\.tu = h\.slice\(0, 8\) \+ '01'/.test(tm) && /'misa\?tu=' \+ MISA\.tu \+ '&den=' \+ MISA\.den \+ '&cua_hang=' \+ encodeURIComponent\(MISA\.ch\) \+ '&tt=' \+ MISA\.tt/.test(boc('misaDuong')));
t('🔴 hỏng thì khoiLoi (nói vì sao), không catch rỗng', /khoiLoi\(o, 'dtMisa'/.test(tm));

const vm = boc('veMisa');
t('khối #dtMisa có ô #misaTu/#misaDen/#misaCS/#misaTT và nút #misaXem', /id="dtMisa"/.test(vm) && /id="misaTu"/.test(vm) && /id="misaDen"/.test(vm) && /id="misaCS"/.test(vm) && /id="misaTT"/.test(vm) && /id="misaXem"/.test(vm));
t('ba trạng thái chua / da / tatca', /\['chua', 'Chưa xuất'\], \['da', 'Đã xuất'\], \['tatca', 'Tất cả'\]/.test(vm));
t('cảnh báo máy chủ (r.warn) bày trong .canh-ghep', /r\.warn\.map\(esc\)\.join/.test(vm) && /class="canh-ghep"/.test(vm));
t('bảng chứng từ: Ngày / Cơ sở / Số chứng từ / Dòng / Tổng dòng / Doanh thu POS / Chốt / Đã xuất / Lưu ý, thẻ điện thoại', /<th>Số chứng từ<\/th><th>Dòng<\/th>/.test(vm) && /<th>Chốt<\/th><th>Đã xuất<\/th>/.test(vm) && /class="bang-cuon bang-the the-ct"/.test(vm) && /class="o-ghi" data-nhan="Lưu ý"/.test(vm));
t('nút Tải Excel #misaXlsx, Tải CSV #misaCsv, Đánh dấu #misaDau (hay Bỏ dấu #misaBoDau khi xem "đã xuất"), bật tắt từng dòng #misaDongMo', /id="misaXlsx"/.test(vm) && /id="misaCsv"/.test(vm) && /id="misaDau"/.test(vm) && /id="misaBoDau"/.test(vm) && /id="misaDongMo"/.test(vm));
/* Anh Thắng 25/09/2026: "giới hạn 20 dòng cho 1 trang". */
t('🔴 phân trang 20 dòng: misaTrang cắt theo MISA_MOI_TRANG = 20, nút ‹ Trước / Sau › mang data-misa-trang', /var MISA_MOI_TRANG = 20;/.test(js) && /ds\.slice\(dau, dau \+ MISA_MOI_TRANG\)/.test(boc('misaTrang')) && /data-misa-trang="' \+ khoa \+ '\|' \+ \(trang - 1\)/.test(boc('misaTrang')));
t('bảng chứng từ và bảng từng dòng đều qua misaTrang, không còn cắt 400', /misaTrang\(ct, MISA\.trang, 'ct'\)/.test(vm) && /misaTrang\(rows, MISA\.trangDong, 'dong'\)/.test(vm) && !/slice\(0, 400\)/.test(vm));
t('bấm chuyển trang -> đổi MISA.trang / trangDong rồi vẽ lại', /if \(p\[0\] === 'ct'\) MISA\.trang = parseInt\(p\[1\], 10\); else MISA\.trangDong = parseInt/.test(boc('noiMisa')));
t('cảnh báo gọn + <details> "Xem đủ danh sách" từ warn_nhom', /r\.warn_nhom \|\| \[\]/.test(vm) && /<summary>Xem đủ danh sách<\/summary>/.test(vm));
t('🔴 ba bảng khai: tài khoản (data-misa-cf), cơ sở (data-misa-cs + data-cs), mặt hàng (data-misa-mh + data-mh); nút #misaLuuCf/#misaLuuCs/#misaLuuMh', /data-misa-cf="' \+ ten \+ '"/.test(vm) && /data-misa-cs="' \+ k \+ '" data-cs="/.test(vm) && /data-misa-mh="' \+ k \+ '" data-mh="/.test(vm) && /id="misaLuuCf"/.test(vm) && /id="misaLuuCs"/.test(vm) && /id="misaLuuMh"/.test(vm));
t('ô tài khoản đủ: tk_dt, tk_no, tk_gv, tk_kho, chi_nhanh, dvt_ve, dvt_hang, tien_to, ma_kh', ['tk_dt', 'tk_no', 'tk_gv', 'tk_kho', 'chi_nhanh', 'dvt_ve', 'dvt_hang', 'tien_to', 'ma_kh'].every(function (k) { return new RegExp("'" + k + "', cf\\." + k).test(vm); }));
t('mặt hàng: ô Mã hàng MISA có placeholder = mã FABi thấy; vé không có ô đơn giá trong combo; cờ combo và "chưa có mã"', /i\('ma', 110, x\.ma_fabi\)/.test(vm) && /x\.la_ve \? '<span class="chu-them"[^']*'/.test(vm) && /chip">combo/.test(vm) && /chip xau">chưa có mã/.test(vm));
t('khối chiếm cả tab (innerHTML), vẽ lại thay chỗ khi đã có', /else \{ o\.innerHTML = h; \}/.test(vm) && /cu\.outerHTML = h/.test(vm) && !/#dtQuyTrinh/.test(vm));

const nx = boc('napXlsx');
t('🔴 xlsx nạp từ cdnjs LÚC BẤM (không nạp sẵn cho cả trang), có sẵn thì dùng ngay', /cdnjs\.cloudflare\.com\/ajax\/libs\/xlsx\/0\.18\.5\/xlsx\.full\.min\.js/.test(nx) && /if \(typeof window\.XLSX !== 'undefined'\) return Promise\.resolve\(window\.XLSX\)/.test(nx) && !/xlsx\.full\.min\.js/.test(php));
t('CSV có BOM, bọc ô có dấu phẩy/ngoặc kép, tên tệp từ máy chủ', /\\ufeff/.test(boc('misaTaiCsv')) && /replace\(\/"\/g, '""'\)/.test(boc('misaTaiCsv')) && /r\.ten_tep \|\| 'MISA_BanHang'/.test(boc('misaTaiCsv')));
t('Excel: aoa_to_sheet([cols] + rows), sheet BanHang, writeFile .xlsx', /aoa_to_sheet\(\[r\.cols\]\.concat\(r\.rows\)\)/.test(boc('misaTaiXlsx')) && /'BanHang'/.test(boc('misaTaiXlsx')) && /\.xlsx'\)/.test(boc('misaTaiXlsx')));

const nm = boc('noiMisa');
t('nút Xem đọc lại bốn ô lọc rồi taiMisa', /MISA\.tu = k\.querySelector\('#misaTu'\)\.value/.test(nm) && /MISA\.tt = k\.querySelector\('#misaTT'\)\.value/.test(nm) && /docLoc\(\);[^\n]*taiMisa\(o\)/.test(nm));
t('🔴 Tải Excel hỏng (CDN chặn) -> lùi CSV và nói rõ', /misaTaiXlsx\(r\)\.then/.test(nm) && /\.catch\(function \(\) \{ misaTaiCsv\(r\);/.test(nm) && /đã tải CSV thay thế/.test(nm));
t('🔴 tải xong hỏi đánh dấu đã xuất (chỉ khi đang xem "chưa xuất"), đồng ý thì POST viec=da_xuat với khoá các chứng từ', /if \(MISA\.tt !== 'chua' \|\| !khoaDs\.length\) return;/.test(nm) && /window\.confirm\('Đã tải tệp MISA/.test(nm) && /dau\('da_xuat', khoaDs/.test(nm) && /fd\.append\('viec', viec\); fd\.append\('khoa', JSON\.stringify\(khoa\)\)/.test(nm));
t('Bỏ dấu POST viec=bo_xuat', /dau\('bo_xuat', khoaDs/.test(nm));
t('ba nút lưu: POST viec + JSON cùng tên, máy chủ trả bản xem -> veMisa lại, giữ mở phần khai', /fd\.append\('viec', viec\); fd\.append\(viec, JSON\.stringify\(gom\.call\(null\)\)\)/.test(nm) && /MISA\.moCf = true; veMisa\(o, r2\)/.test(nm) && /luu\(k\.querySelector\('#misaLuuCf'\), 'cf'/.test(nm) && /luu\(k\.querySelector\('#misaLuuCs'\), 'cs'/.test(nm) && /luu\(k\.querySelector\('#misaLuuMh'\), 'mh'/.test(nm));
t('gom cơ sở / mặt hàng thành { tên => { ô => giá trị } }', /d\[c\]\[i\.getAttribute\('data-misa-cs'\)\] = i\.value/.test(nm) && /d\[c\]\[i\.getAttribute\('data-misa-mh'\)\] = i\.value/.test(nm));
t('POST đi cùng kỳ đang xem (misaDuong) để máy chủ trả bản xem đúng kỳ', (nm.match(/api\(misaDuong\(\), \{ method: 'POST'/g) || []).length >= 2);

/* ---- chạy thật veMisa trên DOM giả ---- */
const than = boc('veMisa') + boc('misaTrang') + '\n  var MISA_MOI_TRANG = 20;\n  var MISA = { tu: "2026-09-01", den: "2026-09-30", ch: "*", tt: "chua", moDong: false, moCf: false, trang: 1, trangDong: 1 };' +
  '\n  var S = { cf: { cua_hang: ["Tutu Train -  Aeon Tân An", "TuTu Train - Lotte Gò Vấp"] } };' +
  '\n  function noiMisa() {}\n  return veMisa;';
const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const ngayVN = (s) => (s ? s.split('-').reverse().join('/') : '');
const VND = new Intl.NumberFormat('vi-VN');
const tien = (n) => VND.format(Math.round(n || 0)) + ' ₫';
const nguyen = (n) => VND.format(Math.round(n || 0));
let veM = null;
try { veM = new Function('esc', 'ngayVN', 'tien', 'nguyen', than)(esc, ngayVN, tien, nguyen); }
catch (e) { hong.push('không nạp được veMisa: ' + e.message); }
if (veM) {
  const o = { html: '', querySelector: function () { return null; } };
  Object.defineProperty(o, 'innerHTML', { get: function () { return this.html; }, set: function (v) { this.html = v; } });
  const r = { cols: ['Ngày hạch toán', 'Ngày chứng từ', 'Số chứng từ', 'Mã khách hàng', 'Diễn giải', 'Mã hàng', 'Tên hàng', 'TK doanh thu', 'TK công nợ', 'TK giá vốn', 'TK kho', 'ĐVT', 'Số lượng', 'Đơn giá', 'Thành tiền', 'Đơn vị', 'Chi nhánh'],
    rows: [['24/09/2026', '24/09/2026', 'BH20260924-TTAMTA', '', 'Bán hàng', 'MNKVCTT006', 'VÉ + BIM BIM', '5110', '131', '', '', 'Vé', 4, 60000, 240000, 'TTAMTA', 'Khu vui chơi']],
    chung_tu: [
      { ngay: '2026-09-24', cua_hang: 'Tutu Train -  Aeon Tân An', khoa: '2026-09-24|tutu', so_ct: 'BH20260924-TTAMTA', so_dong: 11, tong: 2510000, doanh_thu: 2510000, canh: [], chot: true, da_xuat: null },
      { ngay: '2026-09-23', cua_hang: 'TuTu Train - Lotte Gò Vấp', khoa: '2026-09-23|gv', so_ct: 'BH20260923-01', so_dong: 1, tong: 100000, doanh_thu: 120000, canh: ['Cơ sở "…" chưa khai Mã đơn vị MISA.'], chot: false, da_xuat: { luc: '2026-09-25 09:00:00' } },
    ],
    warn: ['1 ngày cơ sở chưa "Lưu và chốt" báo cáo'], tong: 2610000, ten_tep: 'MISA_BanHang_20260901-20260930',
    cf: { tk_dt: '5110', tk_no: '131', tk_gv: '6320', tk_kho: '1567', chi_nhanh: 'Khu vui chơi', dvt_ve: 'Vé', dvt_hang: 'Cái', tien_to: 'BH', ma_kh: '' },
    cs: [{ cua_hang: 'Tutu Train -  Aeon Tân An', ma_dv: 'TTAMTA', ten: '', ma_kh: '' }, { cua_hang: 'TuTu Train - Lotte Gò Vấp', ma_dv: '', ten: '', ma_kh: '' }],
    mh: [{ ten: 'COMBO … BIM BIM', la_ve: true, combo: true, ma_fabi: 'MNKVCTT006', so_luong: 4, tien: 320000, cf: { ma: '', ten: '', dvt: '', gia: 0 } },
         { ten: 'Bim bim nhỏ', la_ve: false, combo: false, ma_fabi: '', so_luong: 0, tien: 0, cf: { ma: '', ten: '', dvt: '', gia: 0 } }] };
  let loi = null;
  try { veM(o, r); } catch (e) { loi = e; }
  const h = o.html;
  t('veMisa chạy được trên DOM giả', !loi);
  t('🔴 hai chứng từ -> hai dòng; dòng có cảnh báo mang lớp qua-han; tổng lệch tô đỏ', (h.match(/<td class="o-may" data-nhan="Ngày">/g) || []).length === 2 && /<tr class="qua-han">/.test(h) && /style="color:var\(--xau\);font-weight:600">100\.000/.test(h));
  t('đã xuất bày lúc đánh dấu; chưa chốt bày "chưa"', /2026-09-25 09:00/.test(h) && /chưa<\/span>/.test(h));
  t('cảnh báo máy chủ hiện trong .canh-ghep', /canh-ghep/.test(h) && /chưa &quot;Lưu và chốt&quot;/.test(h));
  t('bảng cơ sở: GV chưa có mã -> chip "chưa có mã"; ô mã đơn vị TA sẵn TTAMTA', /Lotte Gò Vấp <span class="chip xau">chưa có mã/.test(h) && /data-misa-cs="ma_dv" data-cs="Tutu Train -  Aeon Tân An" value="TTAMTA"/.test(h));
  t('bảng mặt hàng: combo gắn chip, placeholder mã FABi; Bim bim chưa có mã; vé không có ô đơn giá', /chip">combo<\/span>/.test(h) && /placeholder="MNKVCTT006"/.test(h) && /Bim bim nhỏ <span class="chip xau">chưa có mã/.test(h) && /data-nhan="Đơn giá trong combo"><span class="chu-them"/.test(h));
  t('nút tải và đánh dấu 2 ngày; ô chọn cửa hàng có cả hai quán', /id="misaXlsx"/.test(h) && /Đánh dấu 2 ngày đã xuất/.test(h) && /<option value="TuTu Train - Lotte Gò Vấp">/.test(h));
  t('mọi ô khai autocomplete="off"', (h.match(/data-misa-(cf|cs|mh)=/g) || []).length === (h.match(/autocomplete="off" data-misa-(cf|cs|mh)=/g) || []).length);
  t('2 chứng từ -> không có thanh trang', !/trang-nav/.test(h));
  /* 45 chứng từ -> trang 1 có 20 dòng, thanh "Trang 1/3 · 1–20 của 45", nút Trước tắt */
  const r45 = Object.assign({}, r, { chung_tu: [] });
  for (let i = 0; i < 45; i++) r45.chung_tu.push(Object.assign({}, r.chung_tu[0], { ngay: '2026-09-' + ('0' + (1 + (i % 28))).slice(-2), khoa: 'k' + i }));
  veM(o, r45);
  const h45 = o.html;
  t('🔴 45 chứng từ -> 20 dòng ở trang 1, thanh Trang 1/3 · 1–20 của 45, nút Trước tắt', (h45.match(/<td class="o-may" data-nhan="Ngày">/g) || []).length === 20 && /Trang 1\/3 · 1–20 của 45/.test(h45) && /data-misa-trang="ct\|0" disabled/.test(h45) && /data-misa-trang="ct\|2">Sau ›/.test(h45));
  t('nút Đánh dấu vẫn đếm đủ 45 ngày (đánh dấu cả kỳ, không chỉ trang đang xem)', /Đánh dấu 45 ngày đã xuất/.test(h45));
}

/* ---- CSS ---- */
t('CSS có chip.xau và thẻ chứng từ .the-ct hai cột, Lưu ý xuống dòng', /\.khh-dt \.chip\.xau/.test(css) && /\.khh-dt \.bang-the\.the-ct tr\{grid-template-columns:1fr 1fr\}/.test(css) && /\.khh-dt \.bang-the\.the-ct \.o-ghi\{[^}]*white-space:normal/.test(css));

console.log(hong.length ? '✗ HỎNG ' + hong.length + ' / ' + (dat + hong.length) + ' phép:\n  · ' + hong.join('\n  · ')
  : '✓ SẠCH — ' + dat + ' phép: màn Xuất MISA nối đúng lõi, tải Excel/CSV, ba bảng khai.');
process.exit(hong.length ? 1 : 0);
