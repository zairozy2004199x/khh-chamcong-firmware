/* Màn "Doanh thu vs Chi phí theo cơ sở" + thẻ Cấu hình "Kết nối web Doanh thu" + cột "Tên bên Doanh thu" ở bảng
 * Cơ sở + khối Kỹ thuật Tổng quan gập mặc định. Anh Thắng 25/09/2026: "Em có thể lấy doanh thu cơ sở trên wed
 * doanh-thu-hcm không." · "Để anh đánh giá doanh thu dựa trên chi phí" · "chỗ này ẩn định".
 * Chạy: node tools/test/kiem-doanh-thu-chi-phi.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const nut = () => { const o = {}; return { el: (id) => (o[id] = o[id] || { style: {}, value: '', innerHTML: '', textContent: '' }), o }; };

/* ── 1. Bảng Cơ sở: cột Tên bên Doanh thu ─────────────────────────────────────────────────── */
t('🔴 bảng Cơ sở có tiêu đề cột "Tên bên Doanh thu" đứng SAU Bộ phận, TRƯỚC Đóng cửa', /Bộ phận<\/th><th[^>]*>Tên bên Doanh thu<\/th><th[^>]*>Đóng cửa gian hàng<\/th>/.test(HTML));
{
  const r = new Function(ham('_dongCoso') + '\nreturn _dongCoso;')()(['FZ AN LẠC', 'FZ', 'FZAL', 'KVC MN', 'K&H', 'kvc', ' FUNZONE ADVENTURE GO AN LẠC ']);
  teq('🔴 _dongCoso: ô thứ 7 là tenDoanhThu (cắt khoảng trắng)', 'FUNZONE ADVENTURE GO AN LẠC', r.tenDoanhThu);
  teq('   ô 6 vẫn là boPhan', 'kvc', r.boPhan);
  t('   không gửi `tinh` (máy chủ giữ tỉnh đã khai)', !('tinh' in r), r);
}
{
  const i = HTML.indexOf('  function renderCosoBody(){'); const seg = HTML.slice(i, HTML.indexOf('  function _dongCoso(r){'));
  t('🔴 rowCoso vẽ ô nhập tenDoanhThu (list=dl_dtch) TRƯỚC ô Đóng cửa', /_bpSelCoso\(x\.boPhan, x\.donVi\)\+'<\/td><td><input value="'\+esc\(x\.tenDoanhThu\|\|''\)\+'" list="dl_dtch"/.test(seg));
  teq('   mọi hàng gộp (tiêu đề khối / nhóm / nút thêm) đã lên colspan=9', 0, (seg.match(/colspan="8"/g) || []).length);
  t('   có 5 chỗ colspan=9', (seg.match(/colspan="9"/g) || []).length === 5, (seg.match(/colspan="9"/g) || []).length);
  t('   datalist dl_dtch tồn tại và được đổ từ DT_CUA_HANG', /<datalist id="dl_dtch"><\/datalist>/.test(HTML) && /el\('dl_dtch'\)\.innerHTML=\(\(typeof DT_CUA_HANG/.test(seg));
}

/* ── 2. Thẻ Cấu hình: khoá không bao giờ đổ ra ô ──────────────────────────────────────────── */
t('   CH_NHOM có nhóm Kết nối web Doanh thu → doanhThuCard', /id:\['doanhThuCard'\]/.test(HTML));
t('   thẻ doanhThuCard: ô khoá type=password, autocomplete=new-password', /<input id="cfgDtKhoa" type="password" autocomplete="new-password"/.test(HTML));
{
  const src = ham('renderCfgDoanhThu');
  t('🔴 renderCfgDoanhThu chỉ gán cfgDtKhoa = "" (không bao giờ từ BOOT)', /el\('cfgDtKhoa'\)\.value='';/.test(src) && !/cfgDtKhoa'\)\.value=dt/.test(src) && !/\.khoa\b/.test(src), src);
  const { el, o } = nut();
  new Function('el', 'BOOT', '_laAdmin', 'document', src + '\nrenderCfgDoanhThu();')(el, { doanhThu: { san: true, url: 'https://khmatrix.com', khoaCo: true } }, () => true, { activeElement: null });
  teq('   Admin: bày địa chỉ', 'https://khmatrix.com', o.cfgDtUrl.value);
  t('   nhãn ĐÃ CÓ khoá', /ĐÃ CÓ khoá/.test(o.cfgDtKhoaTt.innerHTML), o.cfgDtKhoaTt.innerHTML);
  teq('   ô khoá trống', '', o.cfgDtKhoa.value);
  const b = nut();
  new Function('el', 'BOOT', '_laAdmin', 'document', src + '\nrenderCfgDoanhThu();')(b.el, { doanhThu: { san: false, url: '', khoaCo: false } }, () => false, { activeElement: null });
  teq('   không phải Admin → thẻ ẩn', 'none', b.o.doanhThuCard.style.display);
}
{
  const src = ham('saveCfgDoanhThu');
  let goi = null; const toasts = [];
  const { el, o } = nut(); o.cfgDtUrl = { value: ' https://khmatrix.com/ ', style: {} }; o.cfgDtKhoa = { value: '', style: {} };
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgDoanhThu();')(el, () => true, (p, m) => { goi = p; }, (k, m) => toasts.push(k + ':' + m));
  teq('🔴 khoá trống vẫn gửi khoa:"" (máy chủ hiểu là GIỮ), url đã cắt khoảng trắng', { doanhThu: { url: 'https://khmatrix.com/', khoa: '' } }, goi);
  goi = null; o.cfgDtUrl.value = 'khmatrix.com';
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgDoanhThu();')(el, () => true, (p) => { goi = p; }, (k, m) => toasts.push(k + ':' + m));
  t('   thiếu http(s) → chối ngay ở màn', goi === null && toasts.some((x) => /http/.test(x)), toasts);
  goi = null; o.cfgDtUrl.value = 'https://khmatrix.com'; o.cfgDtKhoa.value = 'KHOA-MOI-123456789';
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgDoanhThu();')(el, () => false, (p) => { goi = p; }, (k, m) => toasts.push(k + ':' + m));
  t('   không phải Admin → không gửi', goi === null, goi);
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgDoanhThu();')(el, () => true, (p) => { goi = p; }, () => {});
  t('   Admin + khoá mới → gửi khoá, rồi xoá ô ngay', goi && goi.doanhThu.khoa === 'KHOA-MOI-123456789' && o.cfgDtKhoa.value === '', [goi, o.cfgDtKhoa.value]);
}

/* ── 3. Thẻ Tổng quan ─────────────────────────────────────────────────────────────────────── */
t('   thẻ tqDtCard tồn tại, ẩn mặc định, đứng SAU tqKtCard', /id="tqKtCard"[\s\S]*id="tqDtCard" style="display:none"/.test(HTML));
{
  const src = ham('_dtDuocXem') + ham('veTheDoanhThu');
  const chay = (vai, san) => { const { el, o } = nut(); let tai = 0;
    new Function('el', 'BOOT', '_vaiLuat', 'loadDoanhThuChiPhi', src + '\nveTheDoanhThu();')(el, { doanhThu: { san } }, () => vai, () => { tai++; });
    return { d: o.tqDtCard.style.display, tai, thang: (o.tqDtThang || {}).value }; };
  teq('🔴 Nhân viên → không thấy thẻ, không gọi', { d: 'none', tai: 0 }, (({ d, tai }) => ({ d, tai }))(chay('Nhân viên', true)));
  teq('🔴 chưa kết nối (san=false) → Admin cũng không thấy', { d: 'none', tai: 0 }, (({ d, tai }) => ({ d, tai }))(chay('Admin', false)));
  const a = chay('Quản lý', true);
  t('   Quản lý + đã kết nối → hiện, mồi tháng hiện tại YYYY-MM, gọi tải', a.d === '' && a.tai === 1 && /^\d{4}-\d{2}$/.test(a.thang), a);
  t('   Kế toán NCC cũng xem được', chay('Kế toán NCC', true).d === '');
}
{
  const src = ham('_dtTyLe') + ham('renderDoanhThuChiPhi');
  const { el, o } = nut(); const cards = [];
  const R = { success: true, thang: '2026-09', web: 'Doanh thu FABi', nho: true, tongDoanhThu: 201000000, tongChiPhi: 47000000, tyLe: 23.4,
    rows: [
      { coso: 'FUNZONE AN LẠC', cuaHang: 'FUNZONE ADVENTURE GO AN LẠC ( Dịch Vụ và Giải Trí K&H )', doanhThu: 120000000, chiPhi: 40000000, tyLe: 33.3, khop: 'khai' },
      { coso: 'Nhà Ma Phan Văn Trị', cuaHang: 'NHÀ MA PHAN VĂN TRỊ', doanhThu: 50000000, chiPhi: 0, tyLe: 0, khop: 'tu' },
      { coso: '', cuaHang: 'QUÁN LẠ CHƯA KHAI', doanhThu: 1000000, chiPhi: 0, tyLe: 0, khop: 'chua' },
      { coso: 'KHO TỔNG', cuaHang: '', doanhThu: 0, chiPhi: 7000000, tyLe: null, khop: 'chua' },
    ], cuaHangChuaKhop: ['QUÁN LẠ CHƯA KHAI'] };
  new Function('el', '_tqCard', 'money', 'esc', 'R', src + '\nrenderDoanhThuChiPhi(R);')(el, (bg, bd, fg, l, v, s) => { cards.push(l + '|' + v + '|' + s); return ''; }, (n) => String(n), (s) => String(s), R);
  t('   ba thẻ số: doanh thu · chi phí · % (tổng)', cards.length === 3 && /201000000đ/.test(cards[0]) && /47000000đ/.test(cards[1]) && /23\.4%/.test(cards[2]), cards);
  const b = o.tqDtBody.innerHTML;
  t('🔴 4 hàng, hàng khai không có nhãn, hàng "tu" có ≈ tự khớp, "chua" có ? chưa khớp', (b.match(/<tr>/g) || []).length === 4 && /NHÀ MA PHAN VĂN TRỊ <span[^>]*>≈ tự khớp/.test(b) && /QUÁN LẠ CHƯA KHAI <span[^>]*>\? chưa khớp/.test(b) && !/K&amp;H \)<span|K&H \) <span/.test(b), b);
  t('   gian không doanh thu: ghi "(không có doanh thu)", tỷ lệ "—"', /KHO TỔNG<\/td><td><span[^>]*>\(không có doanh thu\)<\/span>/.test(b) && /—<\/span><\/td><\/tr>$/.test(b), b);
  t('   % ≥ 100 đỏ, ≥ 70 cam, còn lại xanh', /#065f46">33\.3%/.test(b), b);
  const n = o.tqDtNote.innerHTML;
  t('   ghi chú: đã nhớ 10 phút + cửa hàng chưa khớp + chỉ đường Cấu hình ▸ Cơ sở', /nhớ 10 phút/.test(n) && /1 cửa hàng chưa khớp[^<]*<b>QUÁN LẠ CHƯA KHAI<\/b>/.test(n) && /Tên bên Doanh thu/.test(n), n);
  const ty = new Function(ham('_dtTyLe') + '\nreturn _dtTyLe;')();
  t('   _dtTyLe: 120 → đỏ, 75 → cam, null → —', /#b91c1c/.test(ty(120)) && /#b45309/.test(ty(75)) && /—/.test(ty(null)));
}
{
  const src = ham('loadDoanhThuChiPhi');
  let goi = null; const { el, o } = nut(); el('tqDtThang').value = '2026-08';
  const run = { withSuccessHandler() { return this; }, withFailureHandler() { return this; }, doanhThuChiPhi(a) { goi = a; } };
  new Function('el', 'google', 'KHOI_DANG', 'esc', src + '\nloadDoanhThuChiPhi(true);')(el, { script: { run } }, 'mn', (s) => s);
  teq('   gọi doanhThuChiPhi với tháng, khối đang đứng, tuoi', { thang: '2026-08', khoi: 'mn', tuoi: true }, goi);
}

/* ── 4. Khối Kỹ thuật Tổng quan gập mặc định ("chỗ này ẩn định") ─────────────────────────── */
t('🔴 thân khối KT nằm trong tqKtThan ẩn mặc định, có nút tqKtNut "▸ Hiện"', /<button class="btn b-x" id="tqKtNut" onclick="tqKtGap\(\)">▸ Hiện<\/button>/.test(HTML) && /<div id="tqKtThan" style="display:none">\s*<div id="tqKtCards"/.test(HTML));
t('   loadTongQuan vẽ lại trạng thái gập (tqKtGap(null)) và gọi veTheDoanhThu', /el\('tqKtCard'\)\.style\.display=showKT\?'':'none';\s*tqKtGap\(null\);\s*veTheDoanhThu\(\);/.test(HTML));
{
  const src = ham('tqKtGap'); let kho = {};
  const LS = { getItem: (k) => (k in kho ? kho[k] : null), setItem: (k, v) => { kho[k] = v; } };
  const mk = () => { const { el, o } = nut(); o.tqKtThan = { style: { display: 'none' } }; o.tqKtNut = { textContent: '' }; return { el, o }; };
  let a = mk(); new Function('el', 'localStorage', src + '\ntqKtGap(null);')(a.el, LS);
  teq('🔴 chưa nhớ gì → GẬP', ['none', '▸ Hiện'], [a.o.tqKtThan.style.display, a.o.tqKtNut.textContent]);
  new Function('el', 'localStorage', src + '\ntqKtGap();')(a.el, LS);
  teq('   bấm nút → xổ, nhớ "1"', ['', '▾ Ẩn', '1'], [a.o.tqKtThan.style.display, a.o.tqKtNut.textContent, kho.vhcp_tq_kt_mo]);
  let b = mk(); new Function('el', 'localStorage', src + '\ntqKtGap(null);')(b.el, LS);
  teq('   vẽ lại sau khi đã nhớ xổ → xổ', '', b.o.tqKtThan.style.display);
  new Function('el', 'localStorage', src + '\ntqKtGap();')(b.el, LS);
  teq('   bấm lần nữa → gập, nhớ "0"', ['none', '0'], [b.o.tqKtThan.style.display, kho.vhcp_tq_kt_mo]);
  let c = mk(); new Function('el', 'localStorage', src + '\ntqKtGap(null);')(c.el, { getItem() { throw new Error('cấm'); }, setItem() { throw new Error('cấm'); } });
  teq('   localStorage bị cấm → vẫn gập, không nổ', 'none', c.o.tqKtThan.style.display);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: màn Doanh thu vs Chi phí, thẻ kết nối, cột Tên bên Doanh thu, khối KT gập mặc định.');
