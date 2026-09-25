/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * THANH KHỐI PHẢI BÀY MIỀN NGƯỜI ẤY TÍCH — KỂ CẢ KHI CHƯA CÓ ĐƠN NÀO.
 * Anh Thắng 23/09/2026: Bắc–Nam dùng chung một bản, *"Nhân viên miền bắc thì thấy miền bắc"*.
 * 🔴 Đơn đóng dấu theo người lập (`khoi_cho_don`), nên tab của người ấy PHẢI có nút khối đó —
 *    không thì đơn đầu tiên họ lập biến mất khỏi chính màn của họ.
 * Chạy: node tools/test/kiem-khoi-theo-nguoi-lap-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); };
const mang = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); };

const NEN = [mang('KHOI_DS'), dong('KHOI_MO'), dong('MIEN_MA'), ham('_khoiMo'), ham('_khoiLuuTru'), ham('_khoiBay'), ham('_khoiDuoc')].join('\n');
t('⚠️ bốc được nền thanh khối', NEN.replace(/\s/g, '').length > 300, NEN.length);
function duoc(khoiXem, khoiCoDon) {
  /* `BOOT` là biến toàn cục trong trình duyệt; `_khoiLuuTru` đọc `window.BOOT&&BOOT.khoiCoDon`
     nên bệ đỡ phải khai CẢ HAI cùng một vật. */
  const B = { khoiXem: khoiXem, khoiCoDon: khoiCoDon || [] };
  return new Function('window', 'BOOT', NEN + '\nreturn _khoiDuoc().map(function(x){ return x.ma; });')({ BOOT: B }, B);
}
/* Kho mới, chưa đơn nào. */
teq('🔴 NV tích mb, kho CHƯA có đơn mb → vẫn có nút mb', ['mb'], duoc(['mb'], []));
teq('🔴 NV tích mn → nút mn', ['mn'], duoc(['mn'], []));
teq('🔴 KT tích cả hai miền → hai nút', ['mb', 'mn'], duoc(['mb', 'mn'], []));
/* Kho đã có đơn: không nhân đôi. */
teq('   kho đã có đơn mb → vẫn đúng một nút mb, không nhân đôi', ['mb'], duoc(['mb'], ['mb']));
/* Chốt cũ giữ nguyên: khối đã ra riêng không mọc nút trên kho trống. */
teq('⚠️ tích mtd trên kho không còn đơn mtd → KHÔNG mọc nút (chốt cũ)', [], duoc(['mtd'], []));
teq('   tích mtd mà kho còn đơn mtd → có nút như trước', ['mtd'], duoc(['mtd'], ['mtd']));
/* Người thấy khối mở (kvc/vp) vẫn như cũ. */
teq('   tích kvc → nút kvc như cũ', ['kvc'], duoc(['kvc'], []));
teq('   không khai gì (null) → bày tập mặc định', ['kvc', 'vp'], duoc(null, []));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: miền người tích luôn có nút, kể cả kho chưa có đơn.');
