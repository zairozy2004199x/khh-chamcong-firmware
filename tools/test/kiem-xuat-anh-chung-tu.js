/* 📷 Xuất ảnh chứng từ của đơn: gom ảnh từng dòng + hoá đơn tổng, tải zip hoặc chia sẻ.
 * Anh Thắng 25/09/2026: "nhiều lúc kế toán cần xuất các hình trong đính kèm, bổ sung nút xuất ảnh chỗ gần thêm
 * hạng mục, tất cả các ảnh (share ra zalo hoặc tải về)". Chạy: node tools/test/kiem-xuat-anh-chung-tu.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['_tenTepAnh', '_anhChungTuDon', 'veNutXuatAnh', 'xuatAnhDon', '_zipLib'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));
t('🔴 hai nút đứng cạnh Thêm hạng mục', /id="xuatAnhBox"[\s\S]{0,600}?id="btnTaiAnh"[\s\S]{0,400}?id="btnChiaSeAnh"[\s\S]{0,400}?id="btnMoLineForm"/.test(HTML));
t('   renderLines vẽ lại nút sau khi đếm dòng', /el\('lineCount'\)\.textContent=L\.length;\s*try\{ veNutXuatAnh\(\); \}catch\(e\)\{\}/.test(HTML));
t('   JSZip nạp từ cdnjs (cùng nguồn với pdf.js)', /ZIP_JS_URL='https:\/\/cdnjs\.cloudflare\.com\/ajax\/libs\/jszip\//.test(HTML));

/* 1. Tên tệp */
const ten = new Function(ham('_tenTepAnh') + '\nreturn _tenTepAnh;')();
teq('🔴 tên tệp bỏ dấu, có số thứ tự, giữ đuôi thật', '03_Man_hinh_may_tinh_Xiaomi.png', ten(3, 'Màn hình máy tính Xiaomi', 'https://x/uploads/a b.PNG?x=1'));
teq('   PDF giữ đuôi pdf', 'HoaDon1.pdf', ten(0, 'HoaDon1', '/up/hd.pdf'));
teq('   không đuôi → jpg; nội dung rỗng → anh', '01_anh.jpg', ten(1, '', '/up/abc'));
/* 2. Gom ảnh */
const gom = (CUR) => new Function('CUR', ham('_tenTepAnh') + ham('_anhChungTuDon') + '\nreturn _anhChungTuDon();')(CUR);
const CUR1 = { don: { maDon: 'D1', anhHoaDon: '/u/hd1.jpg', anhHoaDon2: '' }, lines: [{ noiDung: 'Module', anh: '/u/1.jpg' }, { noiDung: 'Không ảnh', anh: '' }, { noiDung: 'PDF', anh: '/u/2.pdf' }, { noiDung: 'Trùng', anh: '/u/1.jpg' }] };
teq('🔴 gom theo thứ tự dòng, bỏ dòng không ảnh, khử trùng, thêm hoá đơn tổng', ['01_Module.jpg', '03_PDF.pdf', 'HoaDon1.jpg'], gom(CUR1).map((x) => x.ten));
teq('   đơn không ảnh → rỗng', [], gom({ don: {}, lines: [{ noiDung: 'x' }] }));
/* 3. Nút hiện/ẩn */
function nut(CUR, nav) {
  const O = { xuatAnhBox: { style: {} }, xuatAnhSo: {}, btnChiaSeAnh: { style: {} } };
  new Function('el', 'CUR', 'navigator', ham('_tenTepAnh') + ham('_anhChungTuDon') + ham('veNutXuatAnh') + '\nveNutXuatAnh();')((id) => O[id], CUR, nav || {});
  return O;
}
const n1 = nut(CUR1, { share: () => {}, canShare: () => true });
t('🔴 có ảnh → hộp hiện, đếm 3, máy chia sẻ được → nút Chia sẻ hiện', n1.xuatAnhBox.style.display === 'inline-flex' && n1.xuatAnhSo.textContent === 3 && n1.btnChiaSeAnh.style.display === '', n1);
const n2 = nut(CUR1, {});
t('   máy tính không có Web Share → giấu nút Chia sẻ, vẫn có Tải', n2.btnChiaSeAnh.style.display === 'none' && n2.xuatAnhBox.style.display === 'inline-flex', n2);
teq('   không ảnh → giấu cả hộp', 'none', nut({ don: {}, lines: [] }).xuatAnhBox.style.display);
/* 4. Chạy thật xuatAnhDon với fetch / JSZip giả */
function chay(cach, opt) {
  opt = opt || {};
  const toasts = [], logs = [], zipFiles = {}; let taiTen = null, clicked = 0, shared = null;
  const fakeZip = function () { return { folder: (n) => ({ file: (t, b) => { zipFiles[n + '/' + t] = b; } }), generateAsync: () => Promise.resolve(new Blob(['zip'])) }; };
  const doc = { createElement: () => ({ set href(v) { this._h = v; }, get href() { return this._h; }, set download(v) { taiTen = v; }, click() { clicked++; }, remove() {} }), body: { appendChild() {} }, head: { appendChild() {} } };
  const nav = opt.share ? { share: (o) => { shared = o.files.map((f) => f.name); return Promise.resolve(); }, canShare: () => true } : {};
  new Function('CUR', 'el', 'toast', 'loading', '_log', 'fetch', 'document', 'navigator', 'URL', 'window', 'setTimeout', 'File', 'Blob', 'ZIP_JS_URL', 'ZIP_LIB',
    ham('_tenTepAnh') + ham('_anhChungTuDon') + ham('_zipLib') + ham('xuatAnhDon') + '\nxuatAnhDon(' + JSON.stringify(cach) + ');')(
    CUR1, () => null, (k, m) => toasts.push(k + ':' + m), () => {}, (a, b, c) => logs.push([a, b, c]),
    (url) => Promise.resolve(url.indexOf('2.pdf') >= 0 && opt.loi ? { ok: false, status: 404 } : { ok: true, blob: () => Promise.resolve(new Blob(['x'], { type: 'image/jpeg' })) }),
    doc, nav, { createObjectURL: () => 'blob:1', revokeObjectURL() {} }, { JSZip: fakeZip }, (fn) => fn(), File, Blob, 'x', null);
  /* `xuatAnhDon` không trả promise (bấm nút rồi thôi) — đợi chuỗi promise bên trong chạy xong. */
  return new Promise((r) => setTimeout(r, 40)).then(() => ({ toasts, logs, zipFiles: Object.keys(zipFiles).sort(), taiTen, clicked, shared }));
}
(async () => {
  const a = await chay('tai');
  teq('🔴 tải: zip có đủ 3 tệp trong thư mục ChungTu_D1', ['ChungTu_D1/01_Module.jpg', 'ChungTu_D1/03_PDF.pdf', 'ChungTu_D1/HoaDon1.jpg'], a.zipFiles);
  t('   tên tệp zip theo mã đơn, có bấm tải, có toast ok + nhật ký', a.taiTen === 'ChungTu_D1.zip' && a.clicked === 1 && /^ok:Đã đóng gói 3 tệp/.test(a.toasts[0]) && a.logs[0][0] === 'Xuất ảnh chứng từ', a);
  const b = await chay('tai', { loi: true });
  t('🔴 một tệp tải hỏng → vẫn ra zip 2 tệp + KHONG_TAI_DUOC.txt, toast cảnh báo nói số hụt', b.zipFiles.indexOf('ChungTu_D1/KHONG_TAI_DUOC.txt') >= 0 && b.zipFiles.length === 3 && /^warn:.*1 tệp không tải được/.test(b.toasts[0]), b);
  const c = await chay('chia', { share: true });
  teq('🔴 chia sẻ: mở khay với đúng 3 tệp, không tải zip', [['01_Module.jpg', '03_PDF.pdf', 'HoaDon1.jpg'], 0], [c.shared, c.clicked]);
  const d = await chay('chia', {});
  t('   máy không chia sẻ được → lui về zip, có báo', d.clicked === 1 && d.toasts.some((x) => /tải zip thay/.test(x)), d);
  if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
  console.log('\n✓ SẠCH — ' + DAT + ' phép: gom đủ ảnh/PDF của đơn, tải zip hoặc chia sẻ, tệp hỏng không làm mất cả lượt.');
})();
