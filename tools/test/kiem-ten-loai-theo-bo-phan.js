/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TÊN HAI LOẠI ĐƠN ĐỔI THEO BỘ PHẬN — CHỈ ĐỔI CHỮ, KHÔNG ĐỔI DỮ LIỆU
 *
 * Anh Thắng 11/09/2026: *"Chi phí bên bộ phận marketing cũng tương tự như vậy, nhưng từ ngữ tên
 * nó khác thôi"*, rồi chỉ đúng hai nút:
 *   *"Đổi tên đối với marketing tên nó khác Chi Phí Marketing Cơ Sở"*
 *   *"Chi Phí Bán Vé Sớm, Khai Trương, Sự Kiện"*
 *
 * =============================================================================================
 * 🔴 LOẠI LƯU TRONG SỔ KHÔNG ĐƯỢC ĐỔI THEO. Cột `loai` vẫn là 'Chi phí cơ sở' / 'Setup lắp
 *    đặt' / 'Tháo dỡ' — nó quyết định MÃ TÀI KHOẢN, bộ lọc bản xuất MISA, và phép
 *    `la_don_coso()` mà ba màn đang đọc. Đổi chữ lưu xuống là đổi hạch toán của cả sổ, chỉ vì
 *    một cái nhãn. Bài kiểm canh thẳng chỗ này.
 *
 * 🔴 ĐUÔI "· Kỹ thuật" KHÔNG ĐƯỢC GÕ CỨNG. Người Marketing mở ra mà thấy đơn của mình mang tên
 *    bộ phận khác thì họ dừng lại hỏi, hoặc tệ hơn là cứ thế lập.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-ten-loai-theo-bo-phan.js
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
const mBang = /var TEN_LOAI_BP=\{[\s\S]*?\n  \};/.exec(HTML);
t('bốc được bảng TEN_LOAI_BP', !!mBang);
const MOI = (mBang ? mBang[0] : '') + '\n' + bocHam('_tenNhom') + '\n' + bocHam('_tenNhomBp')
  + '\n' + bocHam('_apTenNhom') + '\n' + bocHam('daChonNhom') + '\n' + bocHam('_daLoaiChon');

const KHO = {};
function oNut() {
  const o = { className: '', style: {}, _b: { textContent: '' }, _s: { textContent: '' } };
  o.querySelector = function (q) { return q === 'b' ? o._b : o._s; };
  return o;
}
function moi(boPhan) {
  ['daNhomCs', 'daNhomDa', 'daNhomTuan', 'ndLoaiDaCoSo', 'ndLoaiDuAn'].forEach(function (id) { KHO[id] = oNut(); });
  ['daTaoChiTiet', 'daLoaiBox', 'daTenBox', 'daTuanBox', 'daDangLapTen', 'daLoai'].forEach(function (id) {
    KHO[id] = { style: {}, textContent: '', value: 'Setup lắp đặt' };
  });
  return new Function('CURUSER', 'el', 'daNapTuan', 'daOnLoai', 'DA_NHOM',
    MOI + '\nreturn { ap:_apTenNhom, ten:_tenNhom, tenBp:_tenNhomBp, chon:daChonNhom, loai:_daLoaiChon,'
    + ' dangLap:function(){ return el("daDangLapTen").textContent; } };')(
    { boPhan: boPhan, name: 'Ai Đó' }, function (id) { return KHO[id]; }, function () {}, function () {}, '');
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. MARKETING — đúng hai cái tên anh Thắng đưa
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const M = moi('Marketing');
M.ap();
t('🔴 nút cơ sở gọi là "Chi Phí Marketing Cơ Sở"',
  KHO['daNhomCs']._b.textContent.indexOf('Chi Phí Marketing Cơ Sở') >= 0, KHO['daNhomCs']._b.textContent);
t('🔴 nút dự án gọi là "Chi Phí Bán Vé Sớm, Khai Trương, Sự Kiện"',
  KHO['daNhomDa']._b.textContent.indexOf('Chi Phí Bán Vé Sớm, Khai Trương, Sự Kiện') >= 0, KHO['daNhomDa']._b.textContent);
t('🔴 KHÔNG còn chữ "Chi phí dự án" với Marketing',
  KHO['daNhomDa']._b.textContent.indexOf('Chi phí dự án') < 0, KHO['daNhomDa']._b.textContent);
t('   phụ đề nút dự án thôi nói Setup / Tháo dỡ',
  KHO['daNhomDa']._s.textContent.indexOf('Setup') < 0, KHO['daNhomDa']._s.textContent);
t('   phụ đề nút cơ sở vẫn nói gom nhiều gian theo tuần',
  KHO['daNhomCs']._s.textContent.indexOf('TUẦN') >= 0, KHO['daNhomCs']._s.textContent);

/* 🔴 KHÔNG DÁN THỪA TÊN BỘ PHẬN. "Chi Phí Marketing Cơ Sở · Marketing" là thừa một lần, đúng
   chỗ màn hẹp nhất. */
teq('🔴 tên đã có chữ Marketing thì không dán đuôi nữa',
  '🏢 Chi Phí Marketing Cơ Sở', M.tenBp('coso'));
t('   còn tên chưa có thì vẫn dán',
  M.tenBp('duan').indexOf('· Marketing') > 0, M.tenBp('duan'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 BỘ PHẬN KHÁC KHÔNG ĐỔI — Kỹ thuật giữ nguyên chữ đang chạy
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const K = moi('Kỹ thuật');
K.ap();
teq('Kỹ thuật: nút cơ sở giữ tên cũ', '🏢 Chi phí cơ sở', KHO['daNhomCs']._b.textContent);
teq('Kỹ thuật: nút dự án giữ tên cũ', '🏗 Chi phí dự án', KHO['daNhomDa']._b.textContent);
teq('   và phụ đề cũ',                'Setup / Tháo dỡ một GIAN', KHO['daNhomDa']._s.textContent);
teq('🔴 hộp Tạo đơn mới vẫn ghi "· Kỹ thuật"', '🏢 Chi phí cơ sở · Kỹ thuật', KHO['ndLoaiDaCoSo']._b.textContent);

const R = moi('');   // chưa khai bộ phận
R.ap();
teq('chưa khai bộ phận: giữ tên mặc định', '🏢 Chi phí cơ sở', KHO['daNhomCs']._b.textContent);
teq('   và KHÔNG dán đuôi rỗng',           '🏢 Chi phí cơ sở', KHO['ndLoaiDaCoSo']._b.textContent);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 LOẠI LƯU XUỐNG SỔ KHÔNG ĐỔI — chỗ đổi nhầm là đổi hạch toán cả sổ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const M2 = moi('Marketing');
M2.chon('coso');
teq('🔴 Marketing chọn "Chi Phí Marketing Cơ Sở" → sổ vẫn ghi loại "Chi phí cơ sở"',
  'Chi phí cơ sở', M2.loai());
M2.chon('duan');
teq('🔴 chọn nhánh dự án → loại vẫn lấy từ ô Loại dự án (Setup lắp đặt)',
  'Setup lắp đặt', M2.loai());

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. DÒNG "ĐANG LẬP" NÓI ĐÚNG TÊN CỦA BỘ PHẬN ẤY
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const M3 = moi('Marketing');
M3.chon('coso');
t('🔴 Marketing: dòng Đang lập ghi tên Marketing', M3.dangLap().indexOf('Chi Phí Marketing Cơ Sở') >= 0, M3.dangLap());
t('🔴 và KHÔNG ghi "· Kỹ thuật"',                  M3.dangLap().indexOf('Kỹ thuật') < 0, M3.dangLap());
const K3 = moi('Kỹ thuật');
K3.chon('duan');
t('Kỹ thuật: dòng Đang lập vẫn ghi "· Kỹ thuật"', K3.dangLap().indexOf('· Kỹ thuật') > 0, K3.dangLap());
K3.chon('');
teq('chưa chọn loại thì dòng Đang lập rỗng', '', K3.dangLap());

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. 🔴 ÁP TÊN LẠI MỖI LẦN MỞ HỘP — quét tĩnh
 *
 * Chữ nằm cứng trong HTML; không gọi lại lúc mở thì người Marketing thấy chữ của Kỹ thuật cho
 * tới khi tải lại trang.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 daMoTao() áp tên lại',    /function daMoTao\(nhomSan\)\{\s*\n\s*_apTenNhom\(\);/.test(HTML));
t('🔴 hộp ＋Tạo đơn mới áp tên lại', /_apTenNhom\(\);\s*\n\s*el\('ndLoaiBox'\)/.test(HTML));
t('🔴 bảng tên khai ở MỘT chỗ', (HTML.match(/var TEN_LOAI_BP=/g) || []).length === 1);
t('   không còn chữ "Chi phí cơ sở · Kỹ thuật" gõ cứng trong mã JS',
  !/tenLoai=cs\?'🏢 Chi phí cơ sở · Kỹ thuật'/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — tên đổi theo bộ phận, loại lưu xuống sổ không đổi');
