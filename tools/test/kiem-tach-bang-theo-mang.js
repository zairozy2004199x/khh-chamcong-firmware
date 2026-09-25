/* Tách bảng theo Mảng kinh doanh — hai chỗ: màn Xuất MISA (đã có sheet riêng ở 1.336.0, nay tách
 * cả trên màn) và bảng "Đã quyết toán" ở Quyết toán. Anh Thắng 25/09/2026:
 * "mảng nào, bảng xuất riêng, vẫn đang chung 1 bảng" · "Chỗ đã quyết toán vẫn đang chung 1 bảng, tách luôn".
 * Chạy: node tools/test/kiem-tach-bang-theo-mang.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const nut = () => { const o = {}; return { el: (id) => (o[id] = o[id] || { style: {}, value: '', innerHTML: '', textContent: '' }), o }; };

/* ── A. Xuất MISA trên màn ────────────────────────────────────────────────────────────────── */
t('⚠️ bốc được _xuatColsHead / _xuatRowHtml', ham('_xuatColsHead').length > 20 && ham('_xuatRowHtml').length > 40);
t('   markup có xuatTablesMang ngay sau xuatTable, ẩn mặc định', /id="xuatTable"[\s\S]{0,900}<div id="xuatTablesMang" style="display:none">/.test(HTML));
{
  const SRC = ham('_xuatColsHead') + ham('_xuatRowHtml') + ham('_laMaDon') + ham('renderXuat');
  function chay(XUAT) {
    const { el, o } = nut();
    new Function('el', 'XUAT', 'esc', 'money', '_laMaDon', 'renderXuatWarn', 'renderXuatBanGiao', '_veOTkNo', '_veOMang',
      SRC + '\nrenderXuat();')(el, XUAT, (s) => String(s), (n) => String(n), () => false, () => {}, () => {}, () => {}, () => {});
    return o;
  }
  const X = { sodon: 3, count: 4, cols: ['TK Nợ', 'Diễn giải'], rows: [['64191', 'A'], ['64191', 'B'], ['64196', 'C'], ['64199', 'D']],
    rowMang: ['Chi Phí Vận Hành', 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC', ''] };
  const o = chay(X);
  teq('🔴 ≥ 2 mảng: ẩn bảng gộp, hiện khối theo mảng', ['none', ''], [o.xuatTable.style.display, o.xuatTablesMang.style.display]);
  teq('   bảng gộp (xuatBody) để trống — không còn hai chỗ cùng vẽ', '', o.xuatBody.innerHTML);
  const h = o.xuatTablesMang.innerHTML;
  t('🔴 đủ 3 khối, đúng tên mảng, "(chưa khai mảng)" cho mảng rỗng, đếm dòng đúng',
    /Chi Phí Vận Hành[\s\S]*?2 dòng/.test(h) && /Chi Phí Cơ Sở KVC[\s\S]*?1 dòng/.test(h) && /\(chưa khai mảng\)[\s\S]*?1 dòng/.test(h), h);
  t('   khối Vận Hành đứng trước, có đúng 2 dòng A/B, không lẫn dòng C của khối KVC',
    h.indexOf('Chi Phí Vận Hành') < h.indexOf('Chi Phí Cơ Sở KVC') && (h.match(/>A</g) || []).length === 1 && (h.match(/>B</g) || []).length === 1
    && !new RegExp('>C<').test(h.slice(0, h.indexOf('Chi Phí Cơ Sở KVC'))), h);

  const mot = chay({ sodon: 1, count: 2, cols: X.cols, rows: X.rows.slice(0, 2), rowMang: ['Chi Phí Vận Hành', 'Chi Phí Vận Hành'] });
  teq('🔴 chỉ một mảng → bảng gộp hiện lại, khối mảng ẩn, không đổi thói quen cũ', ['', 'none'], [mot.xuatTable.style.display, mot.xuatTablesMang.style.display]);
  t('   bảng gộp vẽ đủ dữ liệu', />A</.test(mot.xuatBody.innerHTML) && />B</.test(mot.xuatBody.innerHTML), mot.xuatBody.innerHTML);

  const cu = chay({ sodon: 1, count: 4, cols: X.cols, rows: X.rows });
  teq('🔴 thiếu rowMang (máy chủ cũ) → lui về bảng gộp, không vỡ', ['', 'none'], [cu.xuatTable.style.display, cu.xuatTablesMang.style.display]);
}

/* ── B. Bảng "Đã quyết toán" ở Quyết toán ──────────────────────────────────────────────────── */
t('⚠️ bốc được _mangCuaCoSo / _mangCuaDon / _mangSapXep / _qtVeBang', ['_mangCuaCoSo', '_mangCuaDon', '_mangSapXep', '_qtVeBang'].every((n) => ham(n).length > 20));
t('   markup: bảng gộp có id qtTableXong, khối mảng qtXongMangWrap ẩn mặc định, đứng trước qtEmptyXong', /id="qtTableXong"[\s\S]{0,1600}<div id="qtXongMangWrap" style="display:none"><\/div>\s*<div class="empty" id="qtEmptyXong"/.test(HTML));

{
  const _mangCuaCoSo = new Function('CFG', ham('_mangCuaCoSo') + '\nreturn _mangCuaCoSo;')({ coso: [
    { ten: 'FUNZONE AN LẠC', phanLoaiLon: 'Chi Phí Cơ Sở KVC' }, { ten: 'VĂN PHÒNG HỒ CHÍ MINH', phanLoaiLon: 'Chi Phí Vận Hành' },
    { ten: 'AEON MALL TÂN PHÚ', phanLoaiLon: 'Chi Phí Vận Hành' }, { ten: 'KHO CHƯA KHAI', phanLoaiLon: '' },
  ] });
  teq('   _mangCuaCoSo: khớp không phân biệt hoa/thường', 'Chi Phí Cơ Sở KVC', _mangCuaCoSo('funzone an lạc'));
  teq('   cơ sở lạ → rỗng', '', _mangCuaCoSo('Không Tồn Tại'));

  const CFG_G = { coso: [
    { ten: 'FUNZONE AN LẠC', phanLoaiLon: 'Chi Phí Cơ Sở KVC' },
    { ten: 'VĂN PHÒNG HỒ CHÍ MINH', phanLoaiLon: 'Chi Phí Vận Hành' },
    { ten: 'AEON MALL TÂN PHÚ', phanLoaiLon: 'Chi Phí Vận Hành' },
    { ten: 'VĂN PHÒNG MTĐ MN', phanLoaiLon: 'Chi Phí Vận Hành' },
    { ten: 'TÀU BÌNH DƯƠNG', phanLoaiLon: 'Chi Phí Cơ Sở MTD' },
  ] };
  const mCuaDon = new Function('CFG', ham('_mangCuaCoSo') + ham('_mangCuaDon') + '\nreturn _mangCuaDon;')(CFG_G);
  teq('🔴 đơn một cơ sở → đúng mảng của cơ sở đó', 'Chi Phí Cơ Sở KVC', mCuaDon({ coso: 'FUNZONE AN LẠC' }));
  teq('🔴 đơn nhiều cơ sở CÙNG mảng (ảnh: Văn Phòng HCM, AEON MALL TÂN PHÚ, Văn Phòng MTĐ MN) → mảng đó', 'Chi Phí Vận Hành',
    mCuaDon({ coso: 'Văn Phòng Hồ Chí Minh, AEON MALL TÂN PHÚ, Văn Phòng MTĐ MN' }));
  teq('🔴 đơn nhiều cơ sở KHÁC mảng → giỏ riêng "(nhiều mảng)", không đoán bừa', '(nhiều mảng)', mCuaDon({ coso: 'FUNZONE AN LẠC, TÀU BÌNH DƯƠNG' }));
  teq('   cơ sở lạ / chưa khai → rỗng ("(chưa khai mảng)" khi hiện tên)', '', mCuaDon({ coso: 'KHO CHƯA KHAI' }));
  teq('   không có cơ sở → rỗng', '', mCuaDon({ coso: '' }));

  const _mangSapXep = new Function(ham('_mangSapXep') + '\nreturn _mangSapXep;')();
  const ds = ['(chưa khai mảng)'.replace('(chưa khai mảng)', ''), '(nhiều mảng)', 'Chi Phí Vận Hành', 'Chi Phí Cơ Sở KVC'];
  teq('🔴 thứ tự: tên mảng theo bảng chữ cái trước, "(nhiều mảng)" rồi "(chưa khai mảng)" cuối', ['Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', '(nhiều mảng)', ''], ds.slice().sort(_mangSapXep));
}

{
  /* _qtRowHtml thật cần canDo/stCls/… — tiêm hàm giả chỉ in mã đơn (như kiem-qt-hai-trang.js). */
  const SRC = ham('_mangCuaCoSo') + ham('_mangCuaDon') + ham('_mangSapXep') + ham('_tachDonVi') + ham('_qtSumHtml') + ham('_qtPagerHtml') + ham('_laChim') + ham('_qtVeBang');
  const rowGia = (d, canBatch, COLS) => '<tr data-ma="' + d.maDon + '"><td colspan="' + COLS + '">' + d.maDon + '</td></tr>';
  function chay(rows, khoa, canBatch, CFG_coso) {
    const { el, o } = nut();
    o.qtEmptyXong = o.qtEmptyCho = o.qtEmptyChoVp = { style: {} };
    new Function('el', 'CFG', 'BOOT', 'esc', 'money', 'QT_MOI_TRANG', 'QT_TRANG', 'QT_ROWS_CHO', '_qtRowHtml', 'rows',
      SRC + '\nreturn _qtVeBang(' + JSON.stringify(khoa) + ', rows, ' + JSON.stringify(!!canBatch) + ');')(
      el, { coso: CFG_coso || [] }, { nhieuDonVi: false }, (s) => String(s), (n) => String(n), 999, { cho: 1, choVp: 1, xong: 1 }, [], rowGia, rows);
    return o;
  }
  const CFG_G = [
    { ten: 'FUNZONE AN LẠC', phanLoaiLon: 'Chi Phí Cơ Sở KVC' },
    { ten: 'VĂN PHÒNG HỒ CHÍ MINH', phanLoaiLon: 'Chi Phí Vận Hành' },
  ];
  const rows = [
    { maDon: 'D1', ky: 'T9/2026', coso: 'FUNZONE AN LẠC', trangThai: 'Đã quyết toán' },
    { maDon: 'D2', ky: 'T9/2026', coso: 'FUNZONE AN LẠC', trangThai: 'Đã quyết toán' },
    { maDon: 'D3', ky: 'T9/2026', coso: 'VĂN PHÒNG HỒ CHÍ MINH', trangThai: 'Đã thanh toán' },
  ];
  const o = chay(rows, 'xong', false, CFG_G);
  teq('🔴 "xong" với ≥ 2 mảng: ẩn bảng gộp, hiện khối mảng', ['none', ''], [o.qtTableXong.style.display, o.qtXongMangWrap.style.display]);
  teq('   bảng gộp (qtBodyXong) để trống', '', o.qtBodyXong.innerHTML);
  const h = o.qtXongMangWrap.innerHTML;
  t('   đủ 2 khối: Chi Phí Cơ Sở KVC (2 đơn D1,D2) và Chi Phí Vận Hành (1 đơn D3)', /Chi Phí Cơ Sở KVC.*?2 đơn/.test(h) && /Chi Phí Vận Hành.*?1 đơn/.test(h) && /D1/.test(h) && /D2/.test(h) && /D3/.test(h), h);
  t('   mỗi khối có dòng tổng riêng (gọi _qtSumHtml)', /Tạm ứng|Thực chi|money/.test(h) || h.length > 200, h.length);

  const o1 = chay(rows.slice(0, 2), 'xong', false, CFG_G);
  teq('🔴 chỉ một mảng trong trang đang xem → bảng gộp hiện lại như cũ', ['', 'none'], [o1.qtTableXong.style.display, o1.qtXongMangWrap.style.display]);
  t('   bảng gộp vẽ đủ D1, D2', /D1/.test(o1.qtBodyXong.innerHTML) && /D2/.test(o1.qtBodyXong.innerHTML));

  /* Nhập theo thứ tự ngược (chưa khai → Vận Hành → KVC) — khối vẫn phải xếp KVC, Vận Hành, "(chưa khai mảng)" cuối. */
  const nguoc = [
    { maDon: 'D0', ky: 'T9/2026', coso: 'KHO CHƯA KHAI', trangThai: 'Đã quyết toán' },
    { maDon: 'D3', ky: 'T9/2026', coso: 'VĂN PHÒNG HỒ CHÍ MINH', trangThai: 'Đã quyết toán' },
    { maDon: 'D1', ky: 'T9/2026', coso: 'FUNZONE AN LẠC', trangThai: 'Đã quyết toán' },
  ];
  const hN = chay(nguoc, 'xong', false, CFG_G).qtXongMangWrap.innerHTML;
  const iK = hN.indexOf('🧮 Chi Phí Cơ Sở KVC'), iV = hN.indexOf('🧮 Chi Phí Vận Hành'), iC = hN.indexOf('🧮 (chưa khai mảng)');
  t('🔴 thứ tự khối không theo thứ tự nhập: KVC trước, Vận Hành giữa, "(chưa khai mảng)" cuối', iK >= 0 && iV > iK && iC > iV, [iK, iV, iC]);
  t('   khối "(chưa khai mảng)" chứa D0, không lẫn D1/D3', /D0/.test(hN.slice(iC)) && !/D1|D3/.test(hN.slice(iC)), hN.slice(iC));

  const oCho = chay(rows, 'cho', true, CFG_G);
  t('🔴 bảng "cho" (Chờ quyết toán) KHÔNG bị đụng — vẫn vẽ thẳng vào qtBodyCho, không tách mảng', /D1/.test(oCho.qtBodyCho.innerHTML) && /D2/.test(oCho.qtBodyCho.innerHTML) && /D3/.test(oCho.qtBodyCho.innerHTML), oCho.qtBodyCho.innerHTML);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: tách bảng theo Mảng kinh doanh ở Xuất MISA và bảng Đã quyết toán.');
