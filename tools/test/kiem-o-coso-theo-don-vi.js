/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HỘP CHỌN CƠ SỞ LỌC THEO ĐƠN VỊ CỦA CHÍNH DÒNG ẤY
 *
 * Anh Thắng 08/09/2026: *"khi chọn KVC hoặc K&H sẽ ra các cơ sở này, còn nếu chọn đơn vị POSH
 * sẽ hiện các cơ sở POSH được lấy từ dữ liệu trang POSH"*.
 *
 * 🔴 Danh mục cơ sở dùng chung hai bên, và bên ghế đẩy sang hàng chục địa điểm nữa. Khai một
 *    tài khoản POSH mà phải lội qua cả danh sách khu vui chơi là mời người ta tích nhầm — mà
 *    tích nhầm ở đây là một nhân viên đọc được gian của bên kia.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-o-coso-theo-don-vi.js
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
function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n', i);
  return (j > i) ? HTML.slice(i, j) : '';
}

const fnDvChuan = bocDong('_dvChuan');
const fnTheoDv = bocHam('_cosoTheoDv');
const fnSel = bocHam('_cosoSel');
t('bốc được _dvChuan()', fnDvChuan.length > 30);
t('bốc được _cosoTheoDv()', fnTheoDv.length > 40);
t('bốc được _cosoSel()', fnSel.length > 300);

/* Danh mục thử: hai gian K&H (một ô Đơn vị BỎ TRỐNG — phần lớn dòng cũ đang như thế), hai gian
   POSH đẩy từ bên ghế sang. */
const CFG = { coso: [
  { ten: 'ADV GO! AN LẠC',       donVi: 'K&H',  tenMisa: 'ADV Go An Lac', maDonVi: 'EVFZADVGAL' },
  { ten: 'NHÀ MA BÌNH DƯƠNG',    donVi: '',     tenMisa: '', maDonVi: 'AMBD' },
  { ten: 'Cali Thảo Điền',       donVi: 'POSH', tenMisa: '', maDonVi: '' },
  { ten: 'BỆNH VIỆN 175',        donVi: 'POSH', tenMisa: '', maDonVi: '' },
] };
const BOOT = { donVi: ['K&H', 'POSH'] };
function esc(x) { return String(x == null ? '' : x); }

function moiTruong(extra) {
  return new Function('CFG', 'BOOT', 'esc', '_csLabel', '_csPhu', '_csNhan', '_bd',
    fnDvChuan + '\n' + fnTheoDv + '\n' + fnSel + '\n' + (extra || '') +
    '\nreturn { chuan:_dvChuan, theo:_cosoTheoDv, sel:_cosoSel };')(
    CFG, BOOT, esc,
    function (s) { return s.length ? s.join(', ') : 'Tất cả cơ sở'; },
    function () { return ''; }, function (x) { return String(x); }, function (x) { return String(x); });
}
const F = moiTruong();

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. Ô ĐƠN VỊ BỎ TRỐNG = NHÀ MẶC ĐỊNH, KHÔNG PHẢI "BÀY HẾT"
 *
 * 🔴 Phần lớn tài khoản và nhiều dòng cơ sở đang để trống ô ấy. Hiểu trống thành "tất cả" là
 *    lọc y như không lọc, mà nhìn thì tưởng đã chạy.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('trống → nhà mặc định', 'K&H', F.chuan(''));
teq('null cũng thế',        'K&H', F.chuan(null));
teq('thừa khoảng trắng',    'K&H', F.chuan('   '));
teq('có tên thì giữ nguyên','POSH', F.chuan('POSH'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. LỌC THEO ĐƠN VỊ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('🔴 chọn K&H → chỉ gian K&H, kể cả dòng bỏ trống ô Đơn vị',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG'], F.theo('K&H'));
teq('🔴 để trống cũng ra đúng bộ ấy',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG'], F.theo(''));
teq('🔴 chọn POSH → chỉ gian POSH',
  ['Cali Thảo Điền', 'BỆNH VIỆN 175'], F.theo('POSH'));
teq('khác hoa thường vẫn khớp', ['Cali Thảo Điền', 'BỆNH VIỆN 175'], F.theo('posh'));
teq('đơn vị chưa có gian nào → rỗng, không rơi về bày hết', [], F.theo('ĐƠN VỊ LẠ'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. HỘP CHỌN DỰNG RA ĐÚNG BẤY NHIÊU DÒNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function dsTrongHop(html) {
  const ra = []; const re = /<option value="([^"]*)"/g; let m;
  while ((m = re.exec(html))) { ra.push(m[1]); }
  return ra;
}
teq('🔴 dòng POSH: hộp chỉ bày gian POSH',
  ['Cali Thảo Điền', 'BỆNH VIỆN 175'], dsTrongHop(F.sel('', 'POSH')));
teq('🔴 dòng K&H: hộp chỉ bày gian K&H',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG'], dsTrongHop(F.sel('', 'K&H')));
teq('dòng chưa khai đơn vị: theo nhà mặc định',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG'], dsTrongHop(F.sel('', '')));

/* 🔴 GỌI KHÔNG KÈM ĐƠN VỊ THÌ KHÔNG LỌC GÌ. Bảng đăng nhập Google chưa có cột Đơn vị; lọc bừa
   theo nhà mặc định là nuốt mất mọi cơ sở POSH của nó. */
teq('🔴 gọi một tham số (bảng chưa có cột Đơn vị) → bày hết như trước',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG', 'Cali Thảo Điền', 'BỆNH VIỆN 175'], dsTrongHop(F.sel('')));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 CƠ SỞ ĐANG CHỌN KHÔNG BAO GIỜ BỊ LỌC MẤT
 *
 * Lọc mất là lần lưu tiếp theo âm thầm xoá lựa chọn người ta đã đặt — và không ai thấy nó biến
 * đi. Chuyện này xảy ra thật: một tài khoản phụ trách gian của cả hai bên (anh Bin, 08/09/2026:
 * "FARM PHAN THIẾT, POSH_HCM").
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const h = F.sel('Cali Thảo Điền, ADV GO! AN LẠC', 'POSH');
const ds = dsTrongHop(h);
t('🔴 gian POSH đang chọn vẫn có', ds.indexOf('Cali Thảo Điền') >= 0, ds);
t('🔴 gian K&H đang chọn — lệch đơn vị — VẪN CÒN, không bị vứt', ds.indexOf('ADV GO! AN LẠC') >= 0, ds);
t('gian K&H KHÔNG chọn thì không bày', ds.indexOf('NHÀ MA BÌNH DƯƠNG') < 0, ds);
teq('và cả hai đều đang được tích', 2, (h.match(/ selected/g) || []).length);
teq('số ô tích trong danh sách khớp số dòng', ds.length, (h.match(/type="checkbox"/g) || []).length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. ĐỔI Ô ĐƠN VỊ -> HỘP CƠ SỞ CÙNG HÀNG ĐỔI THEO NGAY
 *
 * 🔴 Đợi tới lượt vẽ lại cả bảng thì người ta gõ "POSH" xong mở hộp ra vẫn thấy nguyên danh
 *    sách khu vui chơi, và kết luận là lọc không chạy.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnDvInp = bocHam('_dvInp');
const fnDvDoi = bocHam('_dvDoi');
t('bốc được _dvInp()', fnDvInp.length > 100);
t('bốc được _dvDoi()', fnDvDoi.length > 100);
t('🔴 ô Đơn vị có gắn tay nghe đổi', /oninput="_dvDoi\(this\)"/.test(fnDvInp), fnDvInp);
t('bảng người dùng truyền đơn vị vào hộp cơ sở',
  /_cosoSel\(u\.coso,\s*u\.donVi\)/.test(HTML));

/* Chạy thật `_dvDoi` trên một hàng giả: gõ POSH -> hộp phải đổi, và giữ nguyên gian đang tích. */
function hangGia(selText, dvMoi) {
  const td = { innerHTML: '' };
  const opts = ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG', 'Cali Thảo Điền', 'BỆNH VIỆN 175']
    .map(function (c) { return { value: c, selected: selText.split(',').map(function (s) { return s.trim(); }).indexOf(c) >= 0 }; });
  const w = { parentNode: td, querySelectorAll: function (q) { return q === 'select option' ? opts : []; } };
  const tr = { tagName: 'TR', querySelector: function (q) { return q === '.csw' ? w : null; } };
  const o = { value: dvMoi, parentNode: tr, tagName: 'INPUT' };
  new Function('CFG', 'BOOT', 'esc', '_csLabel', '_csPhu', '_csNhan', '_bd', 'o',
    fnDvChuan + '\n' + fnTheoDv + '\n' + fnSel + '\n' + fnDvDoi + '\n_dvDoi(o);')(
    CFG, BOOT, esc,
    function (s) { return s.length ? s.join(', ') : 'Tất cả cơ sở'; },
    function () { return ''; }, function (x) { return String(x); }, function (x) { return String(x); }, o);
  return td.innerHTML;
}
const sau = hangGia('ADV GO! AN LẠC', 'POSH');
const dsSau = dsTrongHop(sau);
t('🔴 gõ POSH → hộp bày gian POSH', dsSau.indexOf('Cali Thảo Điền') >= 0, dsSau);
t('🔴 và GIỮ NGUYÊN gian đang tích dù nó là của K&H', dsSau.indexOf('ADV GO! AN LẠC') >= 0, dsSau);
t('gian K&H không tích thì bỏ khỏi hộp', dsSau.indexOf('NHÀ MA BÌNH DƯƠNG') < 0, dsSau);
teq('vẫn đúng một ô đang tích', 1, (sau.match(/ selected/g) || []).length);

const sau2 = hangGia('', 'K&H');
teq('gõ ngược lại K&H → về đúng bộ K&H',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG'], dsTrongHop(sau2));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. NÚT HÚT CƠ SỞ TỪ GHẾ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnHut = bocHam('hutCoSoGhe');
t('bốc được hutCoSoGhe()', fnHut.length > 200);
t('🔴 có nút trên màn Cấu hình', /onclick="hutCoSoGhe\(\)"/.test(HTML));
t('gọi đúng cổng máy chủ', /\.hutCoSoGhe\(\)/.test(fnHut), fnHut);
t('hỏi lại trước khi chạy', /confirm\(/.test(fnHut));
t('nói rõ là CHỈ THÊM, không xoá', /CHỈ THÊM/.test(fnHut), fnHut);
t('🔴 hút xong nạp lại cấu hình (không thì bấm Lưu là ghi đè mất dòng vừa hút)',
  /loadCfg\(\)/.test(fnHut), fnHut);
t('báo lỗi bằng toast đúng thứ tự tham số', /toast\('err'/.test(fnHut), fnHut);
t('báo xong cũng thế', /toast\('ok'/.test(fnHut), fnHut);

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hộp cơ sở lọc theo đơn vị, đổi đơn vị là đổi ngay, gian đang chọn không mất.');
