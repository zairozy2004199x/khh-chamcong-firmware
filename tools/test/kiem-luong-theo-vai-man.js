/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG LUỒNG DUYỆT: MỖI VAI TỰ TẠO MỘT DÒNG — vai trò chính là bộ phận.
 *
 * Anh Thắng 23/09/2026: *"tên vai trò tức là bộ phận, nó chưa đang tạo theo"*, rồi *"Đổi tên bộ
 * phận sang tên vai trò cho cùng tên"*. Ảnh: bảng 🎭 Vai trò tự tạo có 12 vai, bảng Luồng vẫn bày
 * bảy bộ phận cố định (Cơ sở · Văn phòng · Kỹ thuật…) — hai bảng không nói cùng một tên.
 *
 * 🔴 CHẠY THẬT `renderLuongBp` với CFG giả — soi chữ thì `_bpDs()` hay `CFG.vaiTro` đều "có mặt".
 *
 * Chạy: node tools/test/kiem-luong-theo-vai-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const mang = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); };
t('⚠️ bốc được `renderLuongBp`', ham('renderLuongBp').length > 200);
t('⚠️ bốc được `saveCfgLuongBp`', ham('saveCfgLuongBp').length > 100);

function ve(cfg) {
  const KHO = {};
  new Function('CFG', 'BOOT', 'el', 'esc', '_laAdmin', '_bpDs', mang('LUONG_BP_DS') + '\n' + ham('renderLuongBp') + '\nrenderLuongBp();')(
    cfg, {}, (id) => (KHO[id] = KHO[id] || { innerHTML: '', style: {} }), (x) => String(x == null ? '' : x), () => true,
    () => ['Cơ sở', 'Văn phòng', 'Kỹ thuật']);
  return KHO.cfgLuongBpBody.innerHTML;
}
const ten = (h) => (h.match(/<input value="([^"]*)" readonly/g) || []).map((x) => x.replace(/.*value="([^"]*)".*/, '$1'));

/* ── 1. 🔴 Dòng = vai tự tạo, đúng thứ tự bảng Vai ──────────────────────────────────────── */
{
  const cfg = { vaiTro: [{ ten: 'Quản Lý Khu Vui Chơi', goc: 'Quản lý' }, { ten: 'Kế Toán VP Chung', goc: 'Kế toán cá nhân' }, { ten: 'Nhân Viên Cơ Sở Khu Vui Chơi', goc: 'Nhân viên' }],
    boPhanLuong: { 'Nhân Viên Cơ Sở Khu Vui Chơi': 'gt', 'Kế Toán VP Chung': 'tt' } };
  const h = ve(cfg);
  teq('🔴 ba dòng = ba vai tự tạo, đúng thứ tự', ['Quản Lý Khu Vui Chơi', 'Kế Toán VP Chung', 'Nhân Viên Cơ Sở Khu Vui Chơi'], ten(h));
  t('🔴 KHÔNG còn bày bảy bộ phận cũ khi đã có vai', !/value="Cơ sở"/.test(h) && !/Văn phòng/.test(h));
  const hang = h.split('<tr>').slice(1);
  t('🔴 luồng đã khai nhớ đúng mã trên đúng dòng', /value="gt" selected/.test(hang[2]) && /value="tt" selected/.test(hang[1]) && !/ selected/.test(hang[0].replace(/value="" selected/, '')), hang.map((x) => (x.match(/value="([^"]*)" selected/) || [])[1]));
  t('   dòng chưa khai chọn "— theo khối như cũ —"', /value="" selected/.test(hang[0]));
  t('   tên vai là ô readonly (không gõ đè tên ở đây)', (h.match(/readonly/g) || []).length === 3);
  t('   nhắc vai kế thừa quyền của vai gốc nào', /title="Kế thừa quyền của Quản lý"/.test(h));
}
/* ── 2. Chưa có vai tự tạo → lui về danh sách bộ phận, không để bảng trắng ───────────────── */
{
  const h = ve({ vaiTro: [], boPhanLuong: {} });
  teq('🔴 không vai → bày danh sách bộ phận hiện có', ['Cơ sở', 'Văn phòng', 'Kỹ thuật'], ten(h));
  const h2 = ve({ boPhanLuong: {} });
  teq('   thiếu hẳn khoá vaiTro cũng lui về', 3, ten(h2).length);
  const h3 = ve({ vaiTro: [{ ten: '  ', goc: 'Nhân viên' }, null], boPhanLuong: {} });
  teq('   vai tên trống / dòng rỗng bị bỏ → lui về', 3, ten(h3).length);
}
/* ── 3. Lưu gửi đúng {ten, luong} theo hàng ─────────────────────────────────────────────── */
{
  let gui = null; const toasts = [];
  new Function('_readRows', '_saveCfg', 'toast', ham('saveCfgLuongBp') + '\nsaveCfgLuongBp();')(
    () => [['Nhân Viên Cơ Sở Khu Vui Chơi', 'gt'], ['Kế Toán VP Chung', '']], (p) => { gui = p; }, (k, m) => toasts.push(k + ':' + m));
  teq('🔴 gửi boPhanDs = tên vai + luồng', [{ ten: 'Nhân Viên Cơ Sở Khu Vui Chơi', luong: 'gt' }, { ten: 'Kế Toán VP Chung', luong: '' }], gui && gui.boPhanDs);
  gui = null;
  new Function('_readRows', '_saveCfg', 'toast', ham('saveCfgLuongBp') + '\nsaveCfgLuongBp();')(() => [], (p) => { gui = p; }, (k, m) => toasts.push(k + ':' + m));
  t('   bảng rỗng → cảnh báo chỉ đường sang bảng Vai, không lưu', gui === null && toasts.some((x) => /warn:.*Vai trò tự tạo/.test(x)), toasts);
}
/* ── 4. Câu chữ: nói "vai trò", không còn nói bộ phận cố định ─────────────────────────── */
t('🔴 tiêu đề card: Luồng duyệt theo vai trò', /<h2>🧭 Luồng duyệt theo vai trò<\/h2>/.test(HTML));
t('   cột đầu ghi "Vai trò (= bộ phận)"', /<th style="width:300px">Vai trò <span[^>]*>\(= bộ phận\)<\/span><\/th>/.test(HTML));
t('   nhóm Cấu hình đổi tên theo', /ten:'🧭 Luồng duyệt theo vai trò'/.test(HTML));
t('   dòng khoá trong hộp Tạo đơn chỉ đường đúng tên bảng mới', /Cấu hình › Luồng duyệt theo vai trò/.test(HTML));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: bảng Luồng mỗi vai tự tạo một dòng; không vai thì lui về bộ phận; lưu đúng {tên vai, luồng}.');
