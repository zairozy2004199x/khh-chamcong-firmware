/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô LOẠI CHI PHÍ: LOẠI ĐÃ KHAI MIỀN VẪN HIỆN KHI ĐANG ĐỨNG Ở TAB KHỐI CŨ.
 *
 * Anh Thắng 23/09/2026 (ảnh): NV cơ sở mở "Nhập hạng mục xin tạm ứng", ô Loại chi phí chỉ còn
 * "—", trong khi Cấu hình có "Chi phí cơ sở" tích đúng vai "Nhân Viên Cơ Sở Khu Vui Chơi",
 * khối Miền Nam, TK 141.
 *
 * 🔴 GỐC: cửa đầu của `_loaiCpList()` so khối của LOẠI (nay là miền mb · mn) với khối ĐANG ĐỨNG
 *    (bản gốc 'kvc') — khác trục, không loại nào khớp → ô trống với cả công ty. Cùng họ với ô Cơ
 *    sở (kiem-gian-mien-o-tab-khoi-cu.js) và Cấu hình (kiem-cau-hinh-mien-khong-khoa.js).
 *
 * Chạy: node tools/test/kiem-loai-mien-o-tab-khoi-cu.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const sach = (n) => ham(n).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');
t('⚠️ bốc được `_loaiHopKhoi`', ham('_loaiHopKhoi').length > 80);

const hop = (loai, dang, boot) => new Function('BOOT', 'KHOI_DANG', 'MIEN_MA', 'x',
  ham('_locLoaiTheoKhoi') + ham('_khoiCuaLoai') + ham('_loaiHopKhoi') + '\nreturn _loaiHopKhoi(x);')(
  boot || { khoiBan: 'kvc' }, dang, ['mb', 'mn'], loai);

/* ── 1. 🔴 Cảnh thật: đứng tab Khu vui chơi, loại khai Miền Nam ──────────────────────────── */
t('🔴 loại Miền Nam HIỆN ở tab kvc (khác trục, không so)', hop({ ten: 'Chi phí cơ sở', khoi: 'mn' }, 'kvc') === true);
t('🔴 loại Miền Bắc cũng hiện ở tab kvc', hop({ khoi: 'mb' }, 'kvc') === true);
t('   loại miền hiện ở tab vp', hop({ khoi: 'mn' }, 'vp') === true);
/* ── 2. Cùng trục thì vẫn lọc như trước ────────────────────────────────────────────────── */
t('🔴 loại khối cũ khác (mtd) vẫn BỊ ẨN ở tab kvc', hop({ khoi: 'mtd' }, 'kvc') === false);
t('🔴 đứng tab mn (người tích Miền Nam): loại Miền Bắc BỊ ẨN', hop({ khoi: 'mb' }, 'mn') === false);
t('🔴 đứng tab mn: loại Miền Nam hiện', hop({ khoi: 'mn' }, 'mn') === true);
t('   đứng tab mb: loại khối cũ (kvc) hiện — khác trục', hop({ khoi: 'kvc' }, 'mb') === true);
t('   loại chưa khai khối → lấy khối bản cài (kvc): hiện ở kvc, ẩn ở mtd, hiện ở mn (khác trục)',
  hop({}, 'kvc') === true && hop({}, 'mtd') === false && hop({}, 'mn') === true);
t('   không phân biệt hoa thường ở khối LOẠI', hop({ khoi: 'MN' }, 'mn') === true && hop({ khoi: 'MB' }, 'mn') === false);
/* 🔴 Và ở KHOI_DANG — phá thử 23/09/2026: bỏ hạ chữ thường của `KHOI_DANG` mà ca trên vẫn xanh, vì
   'MN' viết hoa không nằm trong MIEN_MA → bị coi là "khối cũ" → khác trục → hợp hết. */
t('🔴 không phân biệt hoa thường ở KHOI_DANG', hop({ khoi: 'mn' }, 'MN') === true && hop({ khoi: 'mb' }, 'MN') === false);
/* ── 3. Cờ tắt lọc theo khối → hợp hết ────────────────────────────────────────────────── */
t('   `locLoaiTheoKhoi=false` → mọi loại hợp, kể cả khối cũ khác', hop({ khoi: 'mtd' }, 'kvc', { khoiBan: 'kvc', locLoaiTheoKhoi: false }) === true);
/* ── 4. Ba cửa dùng chung một phép ─────────────────────────────────────────────────────── */
['_loaiCpList', '_loaiCpVi', '_cacNhomCp'].forEach((n) => {
  t('🔴 `' + n + '` đi qua `_loaiHopKhoi(x)`', /!_loaiHopKhoi\(x\)/.test(sach(n)), n);
  t('   và không còn so thẳng `_khoiCuaLoai(x)!==…KHOI_DANG`', !/_khoiCuaLoai\(x\)!==/.test(sach(n)), n);
});
t('🔴 không còn chỗ nào so thẳng khối loại với KHOI_DANG', !/_khoiCuaLoai\(x\)!==String\(KHOI_DANG\)/.test(HTML));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: loại miền hiện ở tab khối cũ; cùng trục mới lọc; ba cửa dùng chung một phép.');
