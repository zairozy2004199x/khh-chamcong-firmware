/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG CƠ SỞ RỖNG PHẢI NÓI VÌ SAO RỖNG
 *
 * Cắn thật 11/09/2026: anh Thắng mở ⚙️ Cấu hình, bảng "🏢 Mã đơn vị theo Cơ sở" trắng trơn, chỉ
 * còn hàng tiêu đề. Câu đầu tiên: *"mã đơn vị theo cơ sở bị ẩn mất rồi"* — tưởng mất dữ liệu.
 * Không mất gì: tài khoản lúc ấy bị bó theo Đơn vị nên máy chủ lọc sạch danh mục.
 *
 * =============================================================================================
 * 🔴 MỘT BẢNG TRẮNG CÓ HAI NGHĨA HOÀN TOÀN KHÁC NHAU:
 *      "chưa khai cơ sở nào"  ·  "khai rồi nhưng tài khoản này không được xem"
 *    Hai nghĩa ấy dẫn tới hai việc phải làm trái ngược (đi khai · đi sửa phân quyền), mà màn
 *    thì vẽ y hệt nhau. Người dùng không có cách nào phân biệt — nên màn phải nói ra.
 *
 * 🔴 KHÔNG ĐƯỢC NÓI NHẦM CHIỀU. Bày câu "bạn bị giới hạn đơn vị" cho một sổ thật sự chưa khai
 *    gì là đuổi người ta đi sửa phân quyền suốt buổi trong khi việc cần làm là bấm "+ Thêm cơ
 *    sở". Nên bài kiểm dưới canh CẢ HAI chiều, không chỉ chiều vừa cắn.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY, không chép lại.
 *
 * Chạy: node tools/test/kiem-bang-coso-rong-noi-ly-do.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
const fnRender = bocHam('renderCosoBody');
t('bốc được renderCosoBody()', fnRender.length > 500, fnRender.length);

/* Bệ đỡ: giữ lại đúng thứ bài kiểm cần đọc — nội dung đổ vào `cfgCosoBody`, và có gọi khoá
   bảng lại hay không. `el()` trả về một ô giả cho MỌI id, kể cả `dl_pll`/`dl_donvi`/`dl_tinh`. */
function chay(coso, xemDonVi) {
  const O = {};
  const NK = { khoa: 0, doLa: 0 };
  function el(id) { if (!O[id]) { O[id] = { id: id, innerHTML: '' }; } return O[id]; }
  function esc(x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
  const CFG = { coso: coso };
  const BOOT = { donVi: ['K&H', 'POSH', 'KVC'], xemDonVi: xemDonVi };
  new Function('CFG', 'BOOT', 'el', 'esc', '_inp', '_delBtn', '_dvMacDinh', '_csLock', 'doCoSoLa', 'doDongCua',
    fnRender + '\nrenderCosoBody();')(
    CFG, BOOT, el, esc,
    function (v) { return '<input value="' + esc(v) + '">'; },
    function () { return '<td></td>'; },
    function () { return 'K&H'; },
    function () { NK.khoa++; },
    function () { NK.doLa++; },
    function () {});
  return { html: O['cfgCosoBody'] ? O['cfgCosoBody'].innerHTML : null, nk: NK };
}

const CS = [
  { ten: 'ADV GO! AN LẠC', donVi: 'KVC', tenMisa: 'ADV Go An Lac', maDonVi: 'EVFZADVGAL', phanLoaiLon: 'EVENT FZ MN', tinh: 'TP HCM' },
];

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. BÓ THEO ĐƠN VỊ MÀ BẢNG RỖNG → nói rõ là phân quyền, KHÔNG phải mất dữ liệu
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const boPosh = chay([], ['POSH']);
t('🔴 nói thẳng "Không phải mất dữ liệu"', boPosh.html.indexOf('Không phải mất dữ liệu') >= 0, boPosh.html);
t('🔴 gọi đúng tên đơn vị đang bó (POSH)', boPosh.html.indexOf('POSH') >= 0, boPosh.html);
t('   chỉ đường đi sửa (ô "Xem đơn vị")',  boPosh.html.indexOf('Xem đơn vị') >= 0, boPosh.html);
t('🔴 KHÔNG bày nhầm câu "chưa khai cơ sở nào"', boPosh.html.indexOf('Chưa khai cơ sở nào') < 0, boPosh.html);
t('   vẫn khoá bảng lại như thường lệ',  boPosh.nk.khoa > 0, boPosh.nk);
t('   vẫn dò cơ sở lạ như thường lệ',    boPosh.nk.doLa > 0, boPosh.nk);

const boHai = chay([], ['POSH', 'KVC']);
t('bó hai đơn vị thì kể đủ cả hai · POSH', boHai.html.indexOf('POSH') >= 0, boHai.html);
t('                              · KVC',   boHai.html.indexOf('KVC') >= 0, boHai.html);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CHIỀU NGƯỢC LẠI: SỔ THẬT SỰ TRỐNG → chỉ đường đi KHAI, đừng đổ cho phân quyền
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const xemCa = chay([], null);   // 🔴 `xemDonVi` null = XEM CẢ, không phải "không xem gì"
t('🔴 xem cả mà bảng rỗng → "Chưa khai cơ sở nào"', xemCa.html.indexOf('Chưa khai cơ sở nào') >= 0, xemCa.html);
t('   chỉ đúng nút phải bấm (+ Thêm cơ sở)', xemCa.html.indexOf('Thêm cơ sở') >= 0, xemCa.html);
t('🔴 KHÔNG đổ oan cho phân quyền', xemCa.html.indexOf('Không phải mất dữ liệu') < 0, xemCa.html);

const xemCaRong = chay([], []);   // mảng RỖNG cũng phải hiểu là "không bó ai"
t('mảng rỗng cũng là chưa khai, không phải bị bó', xemCaRong.html.indexOf('Chưa khai cơ sở nào') >= 0, xemCaRong.html);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. CÓ CƠ SỞ THÌ VẼ BẢNG NHƯ CŨ — lời nhắc không được chen vào
 *
 * ⚠️ Đây là phép đối chứng: thiếu nó thì một bản vá "luôn hiện lời nhắc" vẫn xanh cả mục 1 lẫn
 *    mục 2, trong khi màn thật đã hỏng với mọi người đang dùng.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const coDu = chay(CS, ['KVC']);
t('có cơ sở → vẫn dựng dải đơn vị',       coDu.html.indexOf('ĐƠN VỊ KVC') >= 0, coDu.html);
t('   vẫn dựng dải phân loại lớn',        coDu.html.indexOf('EVENT FZ MN') >= 0, coDu.html);
t('   vẫn vẽ hàng cơ sở',                 coDu.html.indexOf('EVFZADVGAL') >= 0, coDu.html);
t('   vẫn giữ cột Tỉnh (1.137.0)',        coDu.html.indexOf('TP HCM') >= 0, coDu.html);
t('🔴 KHÔNG chen lời nhắc vào bảng có dữ liệu',
  coDu.html.indexOf('Không phải mất dữ liệu') < 0 && coDu.html.indexOf('Chưa khai cơ sở nào') < 0, coDu.html);

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — bảng cơ sở rỗng nói rõ vì sao rỗng');
