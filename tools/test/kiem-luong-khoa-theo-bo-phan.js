/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỘ PHẬN ĐÃ CHỐT LUỒNG THÌ HỘP "TẠO ĐƠN MỚI" KHÔNG BÀY BA NÚT NỮA.
 *
 * Anh Thắng 23/09/2026: *"Đã chọn luồng duyệt thì ẩn đi, để tránh nhân viên nhầm"* — ảnh: bảng
 * Luồng duyệt theo bộ phận có Cơ sở → Qua tạm ứng, nhưng NV cơ sở mở hộp Tạo đơn vẫn thấy đủ ba
 * nút Qua tạm ứng · Trực tiếp · Duyệt chi. Lật quyết định 22/09 *"bộ phận khai, đơn vẫn sửa được"*.
 *
 * 🔴 CHẠY THẬT `veNdLuong` / `ndChonLuong` với DOM giả — soi chữ thì `if(false&&…)` vẫn xanh.
 *
 * Chạy: node tools/test/kiem-luong-khoa-theo-bo-phan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const mang = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  ];', i) + 5); };
['_luongKhoa', 'veNdLuong', 'ndChonLuong', '_luongMoi'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));
t('⚠️ bốc được `ND_LUONG_DS`', /ma:'dc'/.test(mang('ND_LUONG_DS')));
t('   nhãn câu hỏi có id để đổi chữ khi khoá', /<label id="ndLuongLbl">Đơn này đi luồng nào\?<\/label>/.test(HTML));

function be(luongBo, chon) {
  const KHO = {};
  const moi = {
    BOOT: { luongBo: luongBo }, KHOI_DANG: 'kvc', KHOI_LUONG_CHI: ['mtd', 'vp'],
    esc: (x) => String(x == null ? '' : x),
    el: (id) => (KHO[id] = KHO[id] || { innerHTML: '', textContent: '' }),
  };
  const F = new Function('moi', 'with(moi){ ' + mang('ND_LUONG_DS') + '\nvar ND_LUONG=' + JSON.stringify(chon) + ';\n'
    + ['_luongKhoa', '_luongMoi', 'veNdLuong', 'ndChonLuong'].map(ham).join('\n')
    + '\nreturn { ve: veNdLuong, chon: ndChonLuong, luong: function(){ return ND_LUONG; }, moi: _luongMoi, khoa: _luongKhoa }; }')(moi);
  return { F, KHO };
}
const soNut = (b) => (b.KHO.ndLuongBox.innerHTML.match(/<button/g) || []).length;

/* ── 1. 🔴 Bộ phận Cơ sở đã chốt "Qua tạm ứng" ──────────────────────────────────────────── */
{
  const b = be('gt', 'gt');
  b.F.ve();
  teq('🔴 bộ phận đã chốt → KHÔNG có nút nào để bấm', 0, soNut(b));
  t('🔴 nhưng NÓI rõ đơn đi luồng nào', /Qua tạm ứng/.test(b.KHO.ndLuongBox.innerHTML) && /🔒/.test(b.KHO.ndLuongBox.innerHTML), b.KHO.ndLuongBox.innerHTML);
  t('   không nhắc tới hai luồng kia (tránh gợi nhầm)', !/Trực tiếp/.test(b.KHO.ndLuongBox.innerHTML) && !/Duyệt chi/.test(b.KHO.ndLuongBox.innerHTML));
  t('   chỉ đường sửa: nhờ kế toán ở Cấu hình', /Cấu hình/.test(b.KHO.ndLuongBox.innerHTML));
  teq('   nhãn đổi từ câu hỏi sang lời khẳng định', 'Luồng của đơn', b.KHO.ndLuongLbl.textContent);
  t('   vẫn kể các bước đơn sẽ đi', /Chờ cấp tạm ứng/.test(b.KHO.ndLuongVi.textContent), b.KHO.ndLuongVi.textContent);
  b.F.chon('tt');
  teq('🔴 gọi thẳng `ndChonLuong("tt")` (thanh địa chỉ) → KHÔNG đổi', 'gt', b.F.luong());
  t('   và hộp vẫn nói Qua tạm ứng', /Qua tạm ứng/.test(b.KHO.ndLuongBox.innerHTML) && !/Trực tiếp/.test(b.KHO.ndLuongBox.innerHTML));
}
/* ── 2. 🔴 Biến bị đổi lệch trước lượt vẽ → vẽ lại là kéo về đúng luồng bộ phận ─────────── */
{
  const b = be('dc', 'tt');       // ND_LUONG đang 'tt' (lệch) nhưng bộ phận chốt 'dc'
  b.F.ve();
  teq('🔴 vẽ xong, ND_LUONG bị kéo về luồng bộ phận', 'dc', b.F.luong());
  t('   hộp nói Duyệt chi', /Duyệt chi/.test(b.KHO.ndLuongBox.innerHTML) && soNut(b) === 0);
  t('   câu "đơn sẽ đi" là của Duyệt chi', /Chờ duyệt chi/.test(b.KHO.ndLuongVi.textContent), b.KHO.ndLuongVi.textContent);
}
/* ── 3. Bộ phận ĐỂ TRỐNG → vẫn ba nút và đổi được như cũ ────────────────────────────────── */
{
  const b = be('', 'gt');
  b.F.ve();
  teq('🔴 bộ phận để trống → vẫn đủ BA nút', 3, soNut(b));
  teq('   nhãn vẫn là câu hỏi', 'Đơn này đi luồng nào?', b.KHO.ndLuongLbl.textContent);
  t('   không có ổ khoá', !/🔒/.test(b.KHO.ndLuongBox.innerHTML));
  b.F.chon('tt');
  teq('🔴 bấm nút khác thì đổi được', 'tt', b.F.luong());
  t('   nút đậm là Trực tiếp', /btn b-p[^>]*>[^<]*<b>🧾 Trực tiếp/.test(b.KHO.ndLuongBox.innerHTML) || /<button[^>]*btn b-p[\s\S]*?Trực tiếp/.test(b.KHO.ndLuongBox.innerHTML));
  teq('   `_luongKhoa()` trả rỗng', '', b.F.khoa());
}
/* ── 4. Mã lạ trong bộ phận không khoá ──────────────────────────────────────────────────── */
{
  const b = be('xyz', 'gt');
  teq('   bộ phận ghi mã lạ → coi như trống, không khoá', '', b.F.khoa());
  b.F.ve();
  teq('   và vẫn ba nút', 3, soNut(b));
}
/* ── 5. Câu chữ ở Cấu hình không còn hứa "người lập vẫn đổi được" ──────────────────────── */
t('🔴 card Luồng duyệt theo bộ phận không còn câu "người lập vẫn đổi được"', !/người lập vẫn đổi được/.test(HTML));
t('   và nói rõ hộp Tạo đơn không hỏi nữa', /không hỏi nữa/.test(HTML));

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: bộ phận đã chốt luồng thì hộp Tạo đơn không hỏi, chỉ nói; để trống mới bày ba nút.');
