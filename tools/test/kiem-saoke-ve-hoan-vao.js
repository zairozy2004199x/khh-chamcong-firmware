/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ: VÀO BẰNG VÉ TỪ GHẾ PHẢI HOÃN ĐẾN KHI TỆP JS ĐỌC XONG (0.47.0)
 *
 * Anh Thắng 25/09/2026: vào bằng vé, bấm "Sao Kê Việt QR" → trắng màn + toast
 * "Cannot read properties of undefined (reading 'vietqr')". Khối tự đăng nhập nằm GIỮA tệp JS và gọi
 * vaoApp('') ngay lúc đọc tới → dùng biến `var` khai ở phía dưới (CONG_TEN, VIEW_CONG, CONG_DUNG…)
 * khi còn undefined → script gãy, các `var X = {}` phía dưới không bao giờ chạy. Đường PIN không dính.
 *
 * Bài này canh: (1) nhánh vé phải bọc setTimeout; (2) các bảng theo cổng thật sự khai SAU khối tự đăng
 * nhập (nếu ai đó dời chúng lên trên thì bài vẫn xanh nhưng lý do đổi — ghi lại cho người sau).
 *
 * Chạy: node tools/test/kiem-saoke-ve-hoan-vao.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-saoke/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; console.log('  ✓ ' + n); } else { TRUOT.push(n); console.log('  ✗ ' + n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }

const iVe = src.indexOf("if (window.SAOKE_VE_OK)");
t('có nhánh vào bằng vé (SAOKE_VE_OK)', iVe > 0);
const dongVe = src.slice(iVe, src.indexOf('\n', iVe));
t("🔴 nhánh vé HOÃN vaoApp('') bằng setTimeout(…, 0), không gọi thẳng", /setTimeout\(\s*function\s*\(\)\s*\{\s*vaoApp\(''\);\s*\}\s*,\s*0\s*\)/.test(dongVe), dongVe);
t("không còn dạng gọi thẳng `{ vaoApp(''); return; }`", !/SAOKE_VE_OK\)\s*\{\s*vaoApp\(''\);\s*return;/.test(src));
['var CONG_TEN', 'var VIEW_CONG', 'var CONG_DUNG', 'var CG_TRANG', 'var CG_BANG'].forEach(function (k) {
  const i = src.indexOf(k);
  t(k + ' khai SAU khối tự đăng nhập (lý do phải hoãn)', i > iVe, i);
});
t('vaoApp vẫn mở tab theo #hash sau khi vào (đường vé giữ được #cvietqr)', /if\(_hv && document\.getElementById\('v-'\+_hv\)\) nav\(_hv, true\);/.test(src));

console.log('');
if (TRUOT.length) { console.log('🔴 TRƯỢT: ' + TRUOT.length); process.exit(1); }
console.log('✓ SẠCH — ' + DAT + ' phép');
