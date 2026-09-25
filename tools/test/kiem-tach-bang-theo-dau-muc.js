/* Tách bảng theo ĐẦU MỤC của loại chi phí — hai chỗ: màn Xuất MISA và bảng "Đã quyết toán".
 * Anh Thắng 25/09/2026: "mảng nào, bảng xuất riêng, vẫn đang chung 1 bảng" · "Chỗ đã quyết toán vẫn
 * đang chung 1 bảng, tách luôn" — rồi khi thấy bảng tách theo MẢNG cơ sở ("EVENT FZ MN · 43 dòng"):
 * "đã bảo tách 2 bảng riêng biệt, còn bảng POSH MN thuộc loại chi phí. 4 chi phí, 4 bảng riêng biệt
 * cho anh" → trục tách là đầu mục (Chi Phí Vận Hành · Cơ Sở KVC · Cơ Sở MTD · Khác), máy chủ tính.
 * Chạy: node tools/test/kiem-tach-bang-theo-dau-muc.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
/* Hàm một dòng (`function x(){ return …; }`) — bốc đúng dòng ấy, không nuốt sang hàm kế. */
const dong = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i) + 1); };
const nut = () => { const o = {}; return { el: (id) => (o[id] = o[id] || { style: {}, value: '', innerHTML: '', textContent: '' }), o }; };
const DM_DS = ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', 'Chi Phí Cơ Sở MTD', 'Chi Phí Khác'];

/* ── A. Xuất MISA trên màn ────────────────────────────────────────────────────────────────── */
t('⚠️ bốc được _xuatKhoiDauMuc / _xuatColsHead / _xuatRowHtml', ham('_xuatKhoiDauMuc').length > 40 && ham('_xuatColsHead').length > 20 && ham('_xuatRowHtml').length > 40);
t('   markup có xuatTablesDauMuc ngay sau xuatTable, ẩn mặc định', /id="xuatTable"[\s\S]{0,900}<div id="xuatTablesDauMuc" style="display:none">/.test(HTML));
t('   không còn thẻ/hàm theo mảng cũ (xuatTablesMang, _mangCuaDon, rowMang)', !/xuatTablesMang|_mangCuaDon|_mangSapXep|_mangCuaCoSo|rowMang\[/.test(HTML));
{
  const SRC = ham('_xuatKhoiDauMuc') + ham('_xuatColsHead') + ham('_xuatRowHtml') + ham('_laMaDon') + ham('renderXuat');
  function chay(XUAT) {
    const { el, o } = nut();
    new Function('el', 'XUAT', 'esc', 'money', '_laMaDon', 'renderXuatWarn', 'renderXuatBanGiao', '_veOTkNo', '_veOMang',
      SRC + '\nrenderXuat();')(el, XUAT, (s) => String(s), (n) => String(n), () => false, () => {}, () => {}, () => {}, () => {});
    return o;
  }
  /* Dòng xếp theo MẢNG cơ sở như tệp thật (EVENT FZ MN trước, POSH MN sau) — nhưng bảng phải tách theo ĐẦU MỤC. */
  const X = { sodon: 3, count: 4, cols: ['TK Nợ', 'Diễn giải'], rows: [['64196', 'A'], ['64191', 'B'], ['64196', 'C'], ['64199', 'D']],
    rowDauMuc: ['Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''], dauMucThu: ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''] };
  const o = chay(X);
  teq('🔴 ≥ 2 đầu mục: ẩn bảng gộp, hiện khối theo đầu mục', ['none', ''], [o.xuatTable.style.display, o.xuatTablesDauMuc.style.display]);
  teq('   bảng gộp (xuatBody) để trống — không còn hai chỗ cùng vẽ', '', o.xuatBody.innerHTML);
  const h = o.xuatTablesDauMuc.innerHTML;
  t('🔴 đủ 3 khối, đúng tên đầu mục, "Chưa xếp đầu mục" cho rỗng, đếm dòng đúng',
    /Chi Phí Vận Hành[\s\S]*?1 dòng/.test(h) && /Chi Phí Cơ Sở KVC[\s\S]*?2 dòng/.test(h) && /Chưa xếp đầu mục[\s\S]*?1 dòng/.test(h), h);
  const iV = h.indexOf('🧩 Chi Phí Vận Hành'), iK = h.indexOf('🧩 Chi Phí Cơ Sở KVC'), iC = h.indexOf('🧩 Chưa xếp đầu mục');
  t('🔴 thứ tự khối theo dauMucThu (Vận Hành trước KVC), KHÔNG theo thứ tự dòng xuất hiện; "Chưa xếp" cuối', iV >= 0 && iK > iV && iC > iK, [iV, iK, iC]);
  t('   khối KVC có đúng A và C, không lẫn B của Vận Hành', /(>A<)/.test(h.slice(iK, iC)) && />C</.test(h.slice(iK, iC)) && !/>B</.test(h.slice(iK, iC)), h.slice(iK, iC));

  const mot = chay({ sodon: 1, count: 2, cols: X.cols, rows: X.rows.slice(0, 2), rowDauMuc: ['Chi Phí Vận Hành', 'Chi Phí Vận Hành'], dauMucThu: ['Chi Phí Vận Hành'] });
  teq('🔴 chỉ một đầu mục → bảng gộp hiện lại, khối ẩn, không đổi thói quen cũ', ['', 'none'], [mot.xuatTable.style.display, mot.xuatTablesDauMuc.style.display]);
  t('   bảng gộp vẽ đủ dữ liệu', />A</.test(mot.xuatBody.innerHTML) && />B</.test(mot.xuatBody.innerHTML), mot.xuatBody.innerHTML);

  const cu = chay({ sodon: 1, count: 4, cols: X.cols, rows: X.rows });
  teq('🔴 thiếu rowDauMuc (máy chủ cũ) → lui về bảng gộp, không vỡ', ['', 'none'], [cu.xuatTable.style.display, cu.xuatTablesDauMuc.style.display]);
  /* Lệch độ dài mà vẫn tách thì hai dòng cuối (không có đầu mục) rơi khỏi mọi khối — mất dữ liệu lặng lẽ. */
  const lech = chay({ sodon: 1, count: 4, cols: X.cols, rows: X.rows, rowDauMuc: ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC'] });
  teq('🔴 rowDauMuc lệch độ dài (2 giá trị cho 4 dòng) → bảng gộp đủ 4 dòng, không tách, không rơi dòng', ['', 'none', 4],
    [lech.xuatTable.style.display, lech.xuatTablesDauMuc.style.display, (lech.xuatBody.innerHTML.match(/<tr>/g) || []).length]);
  /* Thiếu dauMucThu (không bắt buộc) → vẫn tách, theo thứ tự xuất hiện. */
  const khongThu = chay({ sodon: 3, count: 4, cols: X.cols, rows: X.rows, rowDauMuc: X.rowDauMuc });
  teq('   thiếu dauMucThu → vẫn tách (thứ tự xuất hiện)', ['none', ''], [khongThu.xuatTable.style.display, khongThu.xuatTablesDauMuc.style.display]);
  t('   … KVC (xuất hiện trước) đứng trước Vận Hành', khongThu.xuatTablesDauMuc.innerHTML.indexOf('🧩 Chi Phí Cơ Sở KVC') < khongThu.xuatTablesDauMuc.innerHTML.indexOf('🧩 Chi Phí Vận Hành'));
}

/* ── B. Bảng "Đã quyết toán" ở Quyết toán ──────────────────────────────────────────────────── */
t('⚠️ bốc được _dauMucCuaDon / _dauMucSapXep / _qtVeBang', dong('_dauMucCuaDon').length > 20 && ham('_dauMucSapXep').length > 40 && ham('_qtVeBang').length > 40);
t('   markup: bảng gộp có id qtTableXong, khối qtXongDauMucWrap ẩn mặc định, đứng trước qtEmptyXong', /id="qtTableXong"[\s\S]{0,1800}<div id="qtXongDauMucWrap" style="display:none"><\/div>\s*<div class="empty" id="qtEmptyXong"/.test(HTML));
{
  const cuaDon = new Function(dong('_dauMucCuaDon') + '\nreturn _dauMucCuaDon;')();
  teq('   _dauMucCuaDon đọc d.dauMuc máy chủ tính', 'Chi Phí Cơ Sở KVC', cuaDon({ dauMuc: 'Chi Phí Cơ Sở KVC' }));
  teq('   máy chủ cũ không trả → rỗng', '', cuaDon({ coso: 'FUNZONE AN LẠC' }));

  const sapXep = new Function('BOOT', ham('_dauMucSapXep') + '\nreturn _dauMucSapXep;')({ dauMucDs: DM_DS });
  const ds = ['', '(nhiều đầu mục)', 'Zzz lạ', 'Chi Phí Cơ Sở KVC', 'Abc lạ', 'Chi Phí Vận Hành'];
  teq('🔴 thứ tự: theo bảng Đầu mục đã khai, tên lạ sau (chữ cái), "(nhiều đầu mục)", rỗng cuối',
    ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', 'Abc lạ', 'Zzz lạ', '(nhiều đầu mục)', ''], ds.slice().sort(sapXep));
  const sapXepKhongBoot = new Function('BOOT', ham('_dauMucSapXep') + '\nreturn _dauMucSapXep;')(null);
  teq('   không có BOOT.dauMucDs → mọi tên coi là lạ, xếp chữ cái, rỗng vẫn cuối', ['Abc', 'Zzz', ''], ['Zzz', '', 'Abc'].sort(sapXepKhongBoot));
}

{
  /* _qtRowHtml thật cần canDo/stCls/… — tiêm hàm giả chỉ in mã đơn (như kiem-qt-hai-trang.js). */
  const SRC = dong('_dauMucCuaDon') + ham('_dauMucSapXep') + ham('_tachDonVi') + ham('_qtSumHtml') + ham('_qtPagerHtml') + ham('_laChim') + ham('_qtVeBang');
  const rowGia = (d, canBatch, COLS) => '<tr data-ma="' + d.maDon + '"><td colspan="' + COLS + '">' + d.maDon + '</td></tr>';
  function chay(rows, khoa, canBatch) {
    const { el, o } = nut();
    o.qtEmptyXong = o.qtEmptyCho = o.qtEmptyChoVp = { style: {} };
    new Function('el', 'CFG', 'BOOT', 'esc', 'money', 'QT_MOI_TRANG', 'QT_TRANG', 'QT_ROWS_CHO', '_qtRowHtml', 'rows',
      SRC + '\nreturn _qtVeBang(' + JSON.stringify(khoa) + ', rows, ' + JSON.stringify(!!canBatch) + ');')(
      el, { coso: [] }, { nhieuDonVi: false, dauMucDs: DM_DS }, (s) => String(s), (n) => String(n), 999, { cho: 1, choVp: 1, xong: 1 }, [], rowGia, rows);
    return o;
  }
  const rows = [
    { maDon: 'D1', ky: 'T9/2026', coso: 'FUNZONE AN LẠC', trangThai: 'Đã quyết toán', dauMuc: 'Chi Phí Cơ Sở KVC' },
    { maDon: 'D2', ky: 'T9/2026', coso: 'FUNZONE AN LẠC', trangThai: 'Đã quyết toán', dauMuc: 'Chi Phí Cơ Sở KVC' },
    { maDon: 'D3', ky: 'T9/2026', coso: 'VĂN PHÒNG HỒ CHÍ MINH', trangThai: 'Đã thanh toán', dauMuc: 'Chi Phí Vận Hành' },
  ];
  const o = chay(rows, 'xong', false);
  teq('🔴 "xong" với ≥ 2 đầu mục: ẩn bảng gộp, hiện khối', ['none', ''], [o.qtTableXong.style.display, o.qtXongDauMucWrap.style.display]);
  teq('   bảng gộp (qtBodyXong) để trống', '', o.qtBodyXong.innerHTML);
  const h = o.qtXongDauMucWrap.innerHTML;
  t('   đủ 2 khối: Chi Phí Cơ Sở KVC (2 đơn D1,D2) và Chi Phí Vận Hành (1 đơn D3)', /Chi Phí Cơ Sở KVC.*?2 đơn/.test(h) && /Chi Phí Vận Hành.*?1 đơn/.test(h) && /D1/.test(h) && /D2/.test(h) && /D3/.test(h), h);
  t('   mỗi khối có dòng tổng riêng (gọi _qtSumHtml)', /Tạm ứng|Thực chi|money/.test(h) || h.length > 200, h.length);

  const o1 = chay(rows.slice(0, 2), 'xong', false);
  teq('🔴 chỉ một đầu mục trong trang đang xem → bảng gộp hiện lại như cũ', ['', 'none'], [o1.qtTableXong.style.display, o1.qtXongDauMucWrap.style.display]);
  t('   bảng gộp vẽ đủ D1, D2', /D1/.test(o1.qtBodyXong.innerHTML) && /D2/.test(o1.qtBodyXong.innerHTML));

  const cu = chay(rows.map((d) => ({ maDon: d.maDon, ky: d.ky, coso: d.coso, trangThai: d.trangThai })), 'xong', false);
  teq('🔴 máy chủ cũ không trả dauMuc → mọi đơn một giỏ → bảng gộp như cũ, không vỡ', ['', 'none'], [cu.qtTableXong.style.display, cu.qtXongDauMucWrap.style.display]);

  /* Nhập theo thứ tự ngược (chưa xếp → nhiều → KVC → Vận Hành) — khối vẫn xếp theo bảng Đầu mục, "(nhiều)" rồi "Chưa xếp" cuối. */
  const nguoc = [
    { maDon: 'D0', ky: 'T9/2026', coso: 'KHO', trangThai: 'Đã quyết toán', dauMuc: '' },
    { maDon: 'D9', ky: 'T9/2026', coso: 'KHO', trangThai: 'Đã quyết toán', dauMuc: '(nhiều đầu mục)' },
    { maDon: 'D1', ky: 'T9/2026', coso: 'FUNZONE AN LẠC', trangThai: 'Đã quyết toán', dauMuc: 'Chi Phí Cơ Sở KVC' },
    { maDon: 'D3', ky: 'T9/2026', coso: 'VĂN PHÒNG HỒ CHÍ MINH', trangThai: 'Đã quyết toán', dauMuc: 'Chi Phí Vận Hành' },
  ];
  const hN = chay(nguoc, 'xong', false).qtXongDauMucWrap.innerHTML;
  const iV = hN.indexOf('🧩 Chi Phí Vận Hành'), iK = hN.indexOf('🧩 Chi Phí Cơ Sở KVC'), iN = hN.indexOf('🧩 (nhiều đầu mục)'), iC = hN.indexOf('🧩 Chưa xếp đầu mục');
  t('🔴 thứ tự khối theo bảng Đầu mục: Vận Hành, KVC, "(nhiều đầu mục)", "Chưa xếp đầu mục" cuối — không theo thứ tự nhập', iV >= 0 && iK > iV && iN > iK && iC > iN, [iV, iK, iN, iC]);
  t('   khối "Chưa xếp đầu mục" chứa D0, không lẫn D1/D3/D9', /D0/.test(hN.slice(iC)) && !/D1|D3|D9/.test(hN.slice(iC)), hN.slice(iC));

  const oCho = chay(rows, 'cho', true);
  t('🔴 bảng "cho" (Chờ quyết toán) KHÔNG bị đụng — vẫn vẽ thẳng vào qtBodyCho, không tách', /D1/.test(oCho.qtBodyCho.innerHTML) && /D2/.test(oCho.qtBodyCho.innerHTML) && /D3/.test(oCho.qtBodyCho.innerHTML), oCho.qtBodyCho.innerHTML);
}

/* ── C. Tải riêng một khối — anh Thắng 25/09/2026 (ảnh hai khối): "muốn xuất cái phía dưới thì như nào" ── */
{
  const SRC = ham('_xuatKhoiDauMuc') + ham('_xuatColsHead') + ham('_xuatRowHtml') + ham('_laMaDon') + ham('renderXuat');
  const { el, o } = nut();
  const X = { sodon: 3, count: 4, cols: ['TK Nợ', 'Diễn giải'], rows: [['64196', 'A'], ['64191', 'B'], ['64196', 'C'], ['64199', 'D']],
    rowDauMuc: ['Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''], dauMucThu: ['Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''] };
  new Function('el', 'XUAT', 'esc', 'money', '_laMaDon', 'renderXuatWarn', 'renderXuatBanGiao', '_veOTkNo', '_veOMang', SRC + '\nrenderXuat();')(
    el, X, (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;'), (n) => String(n), () => false, () => {}, () => {}, () => {}, () => {});
  const h = o.xuatTablesDauMuc.innerHTML;
  teq('🔴 mỗi khối có nút "Tải Excel khối này" mang đúng tên đầu mục của khối', 3, (h.match(/onclick="taiExcelDauMuc\(/g) || []).length);
  t('   nút khối KVC gọi đúng đầu mục KVC; khối rỗng gọi với chuỗi rỗng', /taiExcelDauMuc\(&quot;Chi Phí Cơ Sở KVC&quot;\)/.test(h) && /taiExcelDauMuc\(&quot;&quot;\)/.test(h), h);

  t('⚠️ bốc được taiExcelDauMuc', ham('taiExcelDauMuc').length > 100);
  t('🔴 tải riêng khối KHÔNG chốt đã xuất (không gọi markExported / loadXuat)', !/markExported|loadXuat|confirm\(/.test(ham('taiExcelDauMuc')), ham('taiExcelDauMuc'));
  function taiKhoi(m, coXlsx) {
    const sheets = []; let tenTep = ''; const toasts = [];
    const XLSX = coXlsx ? { utils: { book_new: () => ({}), aoa_to_sheet: (aoa) => ({ __aoa: aoa }), book_append_sheet: (wb, ws, ten) => sheets.push({ ten, dong: ws.__aoa.slice(1) }) }, writeFile: (wb, ten) => { tenTep = ten; } } : undefined;
    const docGia = { body: { appendChild() {} }, createElement: () => ({ click() { tenTep = this.download; }, remove() {} }) };
    new Function('XUAT', 'XLSX', 'toast', 'Blob', 'URL', 'document', 'setTimeout',
      ham('_xuatKhoiDauMuc') + ham('_xlsxTenSheet') + ham('taiExcelDauMuc') + '\ntaiExcelDauMuc(' + JSON.stringify(m) + ');')(
      X, XLSX, (k, s) => toasts.push(k + ':' + s), function () {}, { createObjectURL: () => 'blob:', revokeObjectURL() {} }, docGia, () => {});
    return { sheets, tenTep, toasts };
  }
  const kvc = taiKhoi('Chi Phí Cơ Sở KVC', true);
  teq('🔴 tải khối KVC → MỘT sheet, đúng 2 dòng A và C (không lẫn B, D)', [['Chi Phí Cơ Sở KVC'], ['A', 'C']], [kvc.sheets.map((s) => s.ten), kvc.sheets[0].dong.map((r) => r[1])]);
  t('   tên tệp mang tên đầu mục, không dấu', /^MISA_VanHanhChiPhi_ChiPhiCoSoKVC\.xlsx$/.test(kvc.tenTep), kvc.tenTep);
  t('   báo rõ là CHƯA chốt', kvc.toasts.some((s) => /chưa chốt/.test(s)), kvc.toasts);
  const rong = taiKhoi('', true);
  teq('   khối "Chưa xếp đầu mục" tải được, sheet tên ấy, đúng dòng D', [['Chưa xếp đầu mục'], ['D']], [rong.sheets.map((s) => s.ten), rong.sheets[0].dong.map((r) => r[1])]);
  const csv = taiKhoi('Chi Phí Vận Hành', false);
  t('🔴 máy chặn thư viện → CSV, tên tệp mang tên đầu mục', /^MISA_VanHanhChiPhi_ChiPhiVanHanh\.csv$/.test(csv.tenTep) && csv.sheets.length === 0, csv.tenTep);
  const la = taiKhoi('Không Có', true);
  t('   đầu mục không có dòng → chỉ báo, không tải', la.sheets.length === 0 && la.tenTep === '' && la.toasts.some((s) => /^warn:/.test(s)), la);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: tách bảng theo đầu mục loại chi phí ở Xuất MISA và bảng Đã quyết toán.');
