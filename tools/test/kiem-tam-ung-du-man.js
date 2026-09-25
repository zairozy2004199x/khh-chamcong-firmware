/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TẠM ỨNG DƯ — PHÍA MÀN: KHÔNG KẸP, NHƯNG PHẢI HỎI TRƯỚC KHI TIỀN ĐI.
 *
 * Anh Thắng 23/09/2026: *"Cho cá nhân tạm ứng dư: tức kế toán sẽ nhập lớn hơn số thực tế"*.
 *
 * 🔴 VÌ SAO CHẠY THẬT: soi chữ `confirm(` trong mã thì một bản `if(false && !confirm(...))` vẫn
 *    xanh — phá thử 23/09/2026 lọt đúng chỗ đó (lượt D9). Nút gửi phải CHẠY với `confirm` giả
 *    trả lời KHÔNG, và cửa máy chủ giả phải KHÔNG bị gọi.
 *
 * Chạy: node tools/test/kiem-tam-ung-du-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const money = (n) => String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

t('⚠️ bốc được `lenhCapKep`', ham('lenhCapKep').length > 60);
t('⚠️ bốc được `lenhGuiCap`', ham('lenhGuiCap').length > 300);

/* ═══ 1. Ô SỐ — KHÔNG KẸP, CÓ NÓI ═══════════════════════════════════════════════ */
function kep(gia, con) {
  const o = { value: gia }; const ts = [];
  new Function('money', 'toast', 'o', 'con', ham('lenhCapKep') + '\nlenhCapKep(o, con);')(money, function (k, m) { ts.push(m); }, o, con);
  return { value: o.value, toast: ts };
}
let r = kep('70.000.000', 50000000);
teq('🔴 gõ 70tr khi còn 50tr → GIỮ 70tr, không kẹp', '70.000.000', r.value);
t('🔴 và nói ra phần dư 20tr', /DƯ 20\.000\.000đ/.test(r.toast[0] || ''), r.toast);
r = kep('30.000.000', 50000000);
teq('   gõ dưới phần còn lại → im, không báo gì', 0, r.toast.length);
teq('   và vẫn định dạng lại số', '30.000.000', r.value);

/* ═══ 2. NÚT GỬI — HỎI, VÀ TRẢ LỜI KHÔNG THÌ KHÔNG GỌI CỬA ═══════════════════════ */
function gui(so, con, dapCoNo) {
  const goi = { cap: null, toast: [], hoi: 0 };
  const run = { withSuccessHandler: function () { return run; }, withFailureHandler: function () { return run; },
    capTienPhanDuAn: function (m, d, x) { goi.cap = x; } };
  const doc = { querySelector: function (sel) {
    if (/hmcap="/.test(sel)) return { value: money(so) };
    if (/hmcapngay/.test(sel)) return { value: '' };
    if (/hmcaplan/.test(sel)) return { value: '0' };
    return null; } };
  new Function('document', 'money', 'toast', 'confirm', 'loading', 'google', 'CURUSER', '_log', '_lenhTim', '_conPhaiCap', '_hmVal', '_lenhSau', 'esc',
    ham('lenhGuiCap') + "\nlenhGuiCap('K','DA',1);")(
    doc, money, function (k, m) { goi.toast.push(m); }, function () { goi.hoi++; return dapCoNo; }, function () {},
    { script: { run: run } }, { name: 'KT' }, function () {}, function () { return { soTien: con }; }, function () { return con; },
    function () { return ''; }, function () { return null; }, String);
  return goi;
}
let g = gui(70000000, 50000000, false);
teq('🔴 đưa dư + trả lời KHÔNG → hỏi đúng một lần', 1, g.hoi);
t('🔴 và KHÔNG gọi cửa máy chủ', g.cap === null, g.cap);
g = gui(70000000, 50000000, true);
t('🔴 đưa dư + trả lời CÓ → gửi đúng 70tr', g.cap && g.cap.soTien === 70000000, g.cap);
g = gui(30000000, 50000000, false);
teq('⚠️ đưa dưới phần còn lại → KHÔNG hỏi (hỏi mỗi lượt là người ta bấm OK theo phản xạ)', 0, g.hoi);
t('   và gửi thẳng', g.cap && g.cap.soTien === 30000000, g.cap);
g = gui(0, 50000000, true);
t('⚠️ số 0 → không gửi, nói một câu', g.cap === null && g.toast.length === 1, g);

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: màn không kẹp số dư, hỏi trước khi gửi, trả lời Không thì tiền không đi.');
