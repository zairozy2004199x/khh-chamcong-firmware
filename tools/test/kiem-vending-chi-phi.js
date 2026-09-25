/* Thẻ Cấu hình "🤖 Kết nối web Vending HCMC" — anh Thắng 25/09/2026: "nối chi phí từ web khác qua chi phí của web anh".
 * Chạy: node tools/test/kiem-vending-chi-phi.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const nut = () => { const o = {}; return { el: (id) => (o[id] = o[id] || { style: {}, value: '', innerHTML: '', textContent: '' }), o }; };

t('   CH_NHOM có nhóm Kết nối web Vending → vendingCard', /id:\['vendingCard'\]/.test(HTML));
t('   thẻ: ô khoá type=password autocomplete=new-password; có ô tháng + nút kéo', /<input id="cfgVdKhoa" type="password" autocomplete="new-password"/.test(HTML) && /<input type="month" id="cfgVdThang"/.test(HTML) && /onclick="dongBoVending\(\)"/.test(HTML));
t('   renderCfgVending() gọi ngay sau renderCfgDoanhThu()', /renderCfgDoanhThu\(\);[^\n]*\n\s*renderCfgVending\(\);/.test(HTML));
{
  const src = ham('renderCfgVending');
  t('🔴 renderCfgVending chỉ gán cfgVdKhoa = "" — không bao giờ từ BOOT', /el\('cfgVdKhoa'\)\.value='';/.test(src) && !/cfgVdKhoa'\)\.value=vd/.test(src) && !/\.khoa\b/.test(src), src);
  const { el, o } = nut();
  new Function('el', 'BOOT', '_laAdmin', 'document', src + '\nrenderCfgVending();')(el, { vending: { url: 'https://vending.kh.vn', khoaCo: true, soDaNhap: 12, lanCuoi: '25/09 16:00 · 2026-09 · mới 2 · cập nhật 1' } }, () => true, { activeElement: null });
  teq('   Admin: bày địa chỉ, ô khoá trống, nhãn ĐÃ CÓ, tháng mồi YYYY-MM', ['https://vending.kh.vn', '', true, true], [o.cfgVdUrl.value, o.cfgVdKhoa.value, /ĐÃ CÓ khoá/.test(o.cfgVdKhoaTt.innerHTML), /^\d{4}-\d{2}$/.test(o.cfgVdThang.value)]);
  t('   ghi chú: số đã nhập + lần cuối', /Đã nhập 12 khoản/.test(o.cfgVdNote.textContent) && /Lần cuối/.test(o.cfgVdNote.textContent), o.cfgVdNote.textContent);
  const b = nut();
  new Function('el', 'BOOT', '_laAdmin', 'document', src + '\nrenderCfgVending();')(b.el, { vending: {} }, () => false, { activeElement: null });
  teq('   không phải Admin → ẩn', 'none', b.o.vendingCard.style.display);
}
{
  const src = ham('saveCfgVending'); let goi = null; const toasts = [];
  const { el, o } = nut(); o.cfgVdUrl = { value: ' https://vending.kh.vn/ ' }; o.cfgVdKhoa = { value: '' };
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgVending();')(el, () => true, (p) => { goi = p; }, (k, m) => toasts.push(k + ':' + m));
  teq('🔴 khoá trống vẫn gửi khoa:"" (máy chủ hiểu GIỮ), url cắt khoảng trắng', { vending: { url: 'https://vending.kh.vn/', khoa: '' } }, goi);
  goi = null; o.cfgVdUrl.value = 'vending.kh.vn';
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgVending();')(el, () => true, (p) => { goi = p; }, (k, m) => toasts.push(k + ':' + m));
  t('   thiếu http(s) → chối ở màn', goi === null && toasts.some((x) => /http/.test(x)), toasts);
  goi = null; o.cfgVdUrl.value = 'https://vending.kh.vn'; o.cfgVdKhoa.value = 'KHOA-MOI-123456789';
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgVending();')(el, () => false, (p) => { goi = p; }, () => {});
  t('   không phải Admin → không gửi', goi === null);
  new Function('el', '_laAdmin', '_saveCfg', 'toast', src + '\nsaveCfgVending();')(el, () => true, (p) => { goi = p; }, () => {});
  t('   Admin + khoá mới → gửi khoá rồi xoá ô ngay', goi && goi.vending.khoa === 'KHOA-MOI-123456789' && o.cfgVdKhoa.value === '', [goi, o.cfgVdKhoa.value]);
}
{
  const src = ham('dongBoVending'); let goi = null, booted = 0; const toasts = [], logs = [];
  const { el, o } = nut(); o.cfgVdThang = { value: '2026-09' }; o.cfgVdKq = { style: { display: 'none' }, innerHTML: '' };
  const R = { success: true, thang: '2026-09', tu: '2026-09-01', den: '2026-09-30', web: 'VENDING HCMC', tong: 6, moi: 2, capNhat: 1, boQua: 3, boQuaBoPhan: 1, daXuat: 1, gieo: { coso: 5, loai: 10 }, loi: ['CP-2026-0005: bộ phận "Kho lạ" không có'] };
  const run = { withSuccessHandler(f) { this._ok = f; return this; }, withFailureHandler() { return this; }, dongBoVending(a) { goi = a; this._ok(R); } };
  new Function('el', 'confirm', 'loading', 'google', 'toast', '_log', 'boot', 'esc', src + '\ndongBoVending();')(el, () => false, () => {}, { script: { run } }, () => {}, () => {}, () => {}, (s) => s);
  t('   bấm Huỷ ở confirm → không gọi', goi === null);
  new Function('el', 'confirm', 'loading', 'google', 'toast', '_log', 'boot', 'esc', src + '\ndongBoVending();')(el, () => true, () => {}, { script: { run } }, (k, m) => toasts.push(k + ':' + m), (a) => logs.push(a), () => { booted++; }, (s) => String(s));
  teq('🔴 gọi dongBoVending({thang})', { thang: '2026-09' }, goi);
  t('   toast ok tóm số, ghi _log, nạp lại BOOT', /ok:Tháng 2026-09: mới 2 đơn · cập nhật 1/.test(toasts[0]) && logs[0] === 'Kéo chi phí Vending' && booted === 1, [toasts, logs, booted]);
  const h = o.cfgVdKq.innerHTML;
  t('   bảng kết quả: mới/cập nhật/bỏ qua/đã xuất/bộ phận lạ/gieo/lỗi đều hiện', o.cfgVdKq.style.display === '' && /Mới: <b>2<\/b>/.test(h) && /Cập nhật: <b>1<\/b>/.test(h) && /bỏ qua\): 3/.test(h) && /giữ\): 1/.test(h) && /Bộ phận lạ: 1/.test(h) && /5 cơ sở, 10 loại/.test(h) && /1 khoản lỗi/.test(h) && /Kho lạ/.test(h), h);
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: thẻ Kết nối web Vending, khoá không lộ, kéo về theo tháng và bày kết quả.');
