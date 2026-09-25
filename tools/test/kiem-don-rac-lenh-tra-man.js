/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT 🗑 DỌN LỆNH BỊ TRẢ — PHẢI HỎI, TRẢ LỜI KHÔNG THÌ KHÔNG GỌI CỬA.
 * Soi chữ `confirm(` thì `if(false && !confirm(...))` vẫn xanh — chạy thật với `confirm` giả.
 * Chạy: node tools/test/kiem-don-rac-lenh-tra-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
t('⚠️ bốc được `lenhXoaRac`', ham('lenhXoaRac').length > 100);
function bam(dap, kq) {
  const g = { goi: null, hoi: 0, toast: [], sau: 0 };
  const run = { withSuccessHandler(f) { this._ok = f; return this; }, withFailureHandler() { return this; },
    xoaLenhTraDuAn(ma, dot) { g.goi = { ma, dot }; this._ok(kq || { success: true }); } };
  new Function('confirm', 'loading', 'google', 'toast', '_log', '_lenhSau', ham('lenhXoaRac') + "\nlenhXoaRac('DA1', 4, 'K');")(
    () => { g.hoi++; return dap; }, () => {}, { script: { run } }, (k, m) => g.toast.push(k + ':' + m), () => {}, () => () => { g.sau++; });
  return g;
}
let g = bam(false);
teq('🔴 trả lời KHÔNG → hỏi một lần, không gọi cửa', { hoi: 1, goi: null }, { hoi: g.hoi, goi: g.goi });
g = bam(true);
teq('🔴 trả lời CÓ → gọi đúng cửa với mã dự án + số lệnh', { ma: 'DA1', dot: 4 }, g.goi);
t('   báo xong và vẽ lại', g.toast[0] === 'ok:Đã dọn lệnh 4' && g.sau === 1, g);
g = bam(true, { success: false, error: 'Chỉ Admin' });
t('🔴 máy chủ chối → báo lỗi, không vẽ lại như đã xoá', g.toast[0] === 'err:Chỉ Admin' && g.sau === 0, g);
/* Nút chỉ mọc cho Admin + lệnh 'tra' — chạy thật `lenhNutChung`. */
const NUT = new Function('esc', '_hmLaKT', '_hmLaDuyet', 'CURUSER', ham('lenhNutChung') + '\nreturn lenhNutChung;');
const nut = (tt, role) => NUT(String, () => true, () => true, { role })('K', 'DA1', 4, tt);
t('🔴 Admin + tra → có nút 🗑', /lenhXoaRac/.test(nut('tra', 'Admin')));
t('🔴 Kế toán + tra → KHÔNG', !/lenhXoaRac/.test(nut('tra', 'Kế toán cá nhân')));
t('🔴 Admin + xin → KHÔNG (lệnh đang chạy)', !/lenhXoaRac/.test(nut('xin', 'Admin')));
t('   Admin + ung → không có 🗑, chỉ có Thu hồi', !/lenhXoaRac/.test(nut('ung', 'Admin')) && /Thu hồi/.test(nut('ung', 'Admin')));
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: nút 🗑 chỉ Admin + lệnh bị trả, hỏi trước, chối thì không vẽ lại.');
