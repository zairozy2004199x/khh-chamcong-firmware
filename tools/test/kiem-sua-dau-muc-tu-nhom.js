/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SỬA ĐẦU MỤC TỪ TIÊU ĐỀ NHÓM — ✏️ trên nhóm nhảy sang thẻ 🗂; Lưu gửi `goc` để loại đi theo tên mới.
 * Anh Thắng 24/09/2026 (ảnh ba tiêu đề nhóm Chi phí tiền thuê · Khác · Chưa xếp): *"Muốn sửa phân
 * loại chi phí này"*. Chạy: node tools/test/kiem-sua-dau-muc-tu-nhom.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['renderDauMuc', 'saveCfgDauMuc', 'moSuaDauMuc', '_dmCoSoSel'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));

/* ── 1. renderDauMuc: mỗi dòng mang data-goc = tên lúc vẽ ─────────────────────────────── */
{
  const KHO = {}; const sel = (id) => (KHO[id] = KHO[id] || { innerHTML: '' });
  new Function('CFG', 'BOOT', 'el', 'esc', 'KHOI_DS', 'MIEN_MA', '_delBtn', ham('_dmCoSoSel') + ham('renderDauMuc') + '\nrenderDauMuc();')(
    { dauMucDs: ['Chi phí tiền thuê', 'Khác'], dauMucCoSo: { 'Khác': '' } }, {}, sel, (x) => String(x), [{ ma: 'kvc', ten: 'Khu vui chơi' }], ['mb', 'mn'], () => '<td></td>');
  const h = sel('cfgDauMucBody').innerHTML;
  t('🔴 dòng mang `data-goc` = tên lúc vẽ', /value="Chi phí tiền thuê" data-goc="Chi phí tiền thuê"/.test(h) && /value="Khác" data-goc="Khác"/.test(h), h);
}
/* ── 2. saveCfgDauMuc gửi {ten, coso, goc}; dòng thêm mới goc rỗng ─────────────────────── */
{
  let luu = null; const toasts = [];
  const tr = (ten, goc, coso) => ({ querySelector: (q) => (q === 'input' ? { value: ten, getAttribute: () => goc } : { value: coso }) });
  const body = { getElementsByTagName: () => [tr('Chi phí thuê mall', 'Chi phí tiền thuê', '*'), tr('Chi Phí Cơ Sở KVC', '', 'kvc'), tr('   ', '', '*')] };
  new Function('el', '_saveCfg', 'toast', ham('saveCfgDauMuc') + '\nsaveCfgDauMuc();')(() => body, (p) => { luu = p; }, (k, m) => toasts.push(m));
  teq('🔴 gửi kèm goc để máy chủ đổi tên loại theo; dòng mới goc rỗng; dòng trống bỏ',
    { dauMucDs: [{ ten: 'Chi phí thuê mall', coso: '*', goc: 'Chi phí tiền thuê' }, { ten: 'Chi Phí Cơ Sở KVC', coso: 'kvc', goc: '' }] }, luu);
}
/* ── 3. Tiêu đề nhóm có ✏️ (trừ ô hứng), bấm thì nhảy sang thẻ 🗂 và trỏ đúng dòng ─────── */
{
  const R = ham('renderTkNoMatrix');
  t('🔴 tiêu đề nhóm đầu mục có nút ✏️ sửa gọi moSuaDauMuc(event, tên)', /onclick="moSuaDauMuc\(event,'\+esc\(JSON\.stringify\(dm0\)\)\+'\)"/.test(R) && /\(dm0\?\(' <button/.test(R));
  const KHO = {}; let focused = null; const toasts = [];
  const inp = (goc) => ({ style: {}, getAttribute: () => goc, focus() { focused = goc; }, select() {} });
  const card = { querySelector: () => null, scrollIntoView() { card.cuon = true; }, querySelectorAll: () => [inp('Chi phí tiền thuê'), inp('Khác')] };
  const ev = { preventDefault() { ev.pd = true; }, stopPropagation() { ev.sp = true; } };
  new Function('el', 'toast', 'setTimeout', 'ev', ham('moSuaDauMuc') + "\nmoSuaDauMuc(ev, 'Khác');")((id) => (id === 'dauMucCard' ? card : null), (k, m) => toasts.push(m), () => {}, ev);
  t('🔴 bấm ✏️: không toggle <details>, cuộn tới thẻ, trỏ đúng ô "Khác"', ev.pd && ev.sp && card.cuon && focused === 'Khác', [ev, card.cuon, focused]);
  focused = null; toasts.length = 0;
  new Function('el', 'toast', 'setTimeout', 'ev', ham('moSuaDauMuc') + "\nmoSuaDauMuc(ev, 'Chưa có');")((id) => (id === 'dauMucCard' ? card : null), (k, m) => toasts.push(m), () => {}, ev);
  t('   đầu mục chưa có trong thẻ → nhắc thêm', focused === null && toasts.some((x) => /chưa có trong thẻ/.test(x)), toasts);
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: ✏️ trên nhóm nhảy sang thẻ 🗂 đúng dòng; Lưu gửi tên cũ để loại đi theo tên mới.');
