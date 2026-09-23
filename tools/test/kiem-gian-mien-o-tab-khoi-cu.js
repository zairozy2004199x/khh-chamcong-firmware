/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô CƠ SỞ / HỘP GIAN: GIAN ĐÃ KHAI MIỀN VẪN HIỆN KHI ĐANG ĐỨNG Ở TAB KHỐI CŨ.
 *
 * Anh Thắng 23/09/2026 (ảnh): NV cơ sở Khu vui chơi mở "Tạm ứng xin", ô Cơ sở chỉ còn dấu "—",
 * kèm ảnh bảng Cơ sở với cột Khối toàn "Miền Nam".
 *
 * 🔴 GỐC: `_gianHopKhoi()` so khối của GIAN (nay là miền: mb · mn) với khối ĐANG ĐỨNG trên thanh
 *    đơn (bản gốc: 'kvc'). Hai trục khác nhau, không gian nào khớp → hộp trắng với cả công ty.
 *    Cùng họ với lỗi "loại chi phí miền bị khoá mờ" (kiem-cau-hinh-mien-khong-khoa.js).
 *
 * Luật mới: cùng trục thì so (kvc≠mtd → ẩn; mb≠mn → ẩn); khác trục thì KHÔNG so (gian 'mn' hiện
 * ở tab 'kvc'); chưa khai khối thì hiện ở mọi tab (hỏng-an-toàn, như cũ).
 *
 * Chạy: node tools/test/kiem-gian-mien-o-tab-khoi-cu.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); };
const mang = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); };
t('⚠️ bốc được `_gianHopKhoi`', ham('_gianHopKhoi').length > 60);
t('⚠️ bốc được `MIEN_MA`', /mb/.test(dong('MIEN_MA')) && /mn/.test(dong('MIEN_MA')));

const NEN = mang('KHOI_DS') + '\n' + dong('MIEN_MA') + '\n' + dong('KHOI_DV_DUP') + '\n'
  + ['_khoiDvBang', '_khoiCuaDv', '_khoiCuaGian', '_gianHopKhoi'].map(ham).join('\n');
/* Bảng tra đúng cảnh thật: cơ sở KVC đã đổi sang MN, một gian còn tiếng cũ, một gian chưa khai. */
const BOOT = {
  khoiTheoDv: { mb: ['MB', 'MIỀN BẮC'], mn: ['MN', 'MIỀN NAM'], kvc: ['KVC'], mtd: ['MTĐ', 'POSH'], vp: ['VP'] },
  cosoDv: { 'farm phan thiết': 'MN', 'funzone vũng tàu': 'MN', 'yokid buôn mê thuột': 'MB', 'aeon mall bình dương': 'POSH', 'gian chưa khai': '' }
};
const loc = (ten, khoi) => new Function('BOOT', 'KHOI_DANG', 'ten', NEN + '\nreturn _gianHopKhoi(ten);')(BOOT, khoi, ten);

/* ── 1. 🔴 CẢNH THẬT: đứng ở tab Khu vui chơi, gian đã khai Miền Nam ─────────────────────── */
t('🔴 đứng ở kvc: gian MIỀN NAM vẫn hiện (khác trục, không so)', loc('FARM PHAN THIẾT', 'kvc') === true);
t('🔴 đứng ở kvc: gian MIỀN BẮC cũng hiện', loc('YOKID BUÔN MÊ THUỘT', 'kvc') === true);
t('   đứng ở vp: gian miền vẫn hiện', loc('FUNZONE VŨNG TÀU', 'vp') === true);
/* ── 2. Cùng trục thì vẫn LỌC như trước ─────────────────────────────────────────────────── */
t('🔴 đứng ở kvc: gian POSH (mtd, cùng trục cũ) vẫn BỊ ẨN', loc('AEON MALL BÌNH DƯƠNG', 'kvc') === false);
t('🔴 đứng ở mn (người tích Miền Nam): gian Miền Bắc BỊ ẨN', loc('YOKID BUÔN MÊ THUỘT', 'mn') === false);
t('🔴 đứng ở mn: gian Miền Nam hiện', loc('FARM PHAN THIẾT', 'mn') === true);
t('   đứng ở mb: gian Miền Bắc hiện, Miền Nam ẩn', loc('YOKID BUÔN MÊ THUỘT', 'mb') === true && loc('FARM PHAN THIẾT', 'mb') === false);
t('   đứng ở mb: gian tiếng cũ (POSH) hiện — khác trục', loc('AEON MALL BÌNH DƯƠNG', 'mb') === true);
/* ── 3. Hỏng-an-toàn giữ nguyên ─────────────────────────────────────────────────────────── */
t('   gian chưa khai khối hiện ở MỌI tab', loc('GIAN CHƯA KHAI', 'kvc') && loc('GIAN CHƯA KHAI', 'mn') && loc('GIAN CHƯA KHAI', 'vp'));
t('   không phân biệt hoa thường ở KHOI_DANG', loc('FARM PHAN THIẾT', 'MN') === true && loc('YOKID BUÔN MÊ THUỘT', 'MN') === false);
/* ── 4. Cả hai chỗ bày cơ sở đều đi qua đúng một phép ───────────────────────────────────── */
const sach = (n) => ham(n).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');
t('🔴 ô Cơ sở của đơn tuần lọc qua `_gianHopKhoi`', /\.filter\(_gianHopKhoi\)/.test(sach('_veLaiOCoSo')));
t('🔴 hộp Gian của dự án lọc qua `_gianHopKhoi`', /\.filter\(_gianHopKhoi\)/.test(sach('_daLocLaiGian')));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: gian miền hiện ở tab khối cũ; cùng trục mới lọc; chưa khai vẫn hiện.');
