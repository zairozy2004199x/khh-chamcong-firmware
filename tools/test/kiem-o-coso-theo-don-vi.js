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

const fnDvMe = bocDong('_dvMe');
const fnLaDvMe = bocDong('_laDvMe');
const fnDvChuan = bocDong('_dvChuan');
const fnTheoDv = bocHam('_cosoTheoDv');
const fnSel = bocHam('_cosoSel');
t('bốc được _dvMe()', fnDvMe.length > 20);
t('bốc được _laDvMe()', fnLaDvMe.length > 20);
t('bốc được _dvChuan()', fnDvChuan.length > 30);
t('bốc được _cosoTheoDv()', fnTheoDv.length > 40);
t('bốc được _cosoSel()', fnSel.length > 300);

/* Danh mục thử: hai gian K&H (một ô Đơn vị BỎ TRỐNG — phần lớn dòng cũ đang như thế), hai gian
   POSH đẩy từ bên ghế sang. */
const CFG = { coso: [
  { ten: 'ADV GO! AN LẠC',       donVi: 'K&H',  tenMisa: 'ADV Go An Lac', maDonVi: 'EVFZADVGAL' },
  { ten: 'NHÀ MA BÌNH DƯƠNG',    donVi: '',     tenMisa: '', maDonVi: 'AMBD' },
  { ten: 'VR SC VIVO Q7',        donVi: 'KVC',  tenMisa: 'VR SC Vivo', maDonVi: 'VRSCVV' },
  { ten: 'Cali Thảo Điền',       donVi: 'POSH', tenMisa: '', maDonVi: '' },
  { ten: 'BỆNH VIỆN 175',        donVi: 'POSH', tenMisa: '', maDonVi: '' },
] };
/* ⚠️ `donVi` ĐÃ SẮP a→z y như `VHCP_DonVi::ds()` trả về, và 'K&H' KHÔNG đứng đầu ở đây. Cố ý:
   bản cũ quy ô trống về `donVi[0]`, nên bộ dữ liệu nào cũng phải để nhà mẹ đứng đầu thì phép
   mới xanh — tức phép ấy đang canh một sự trùng hợp của phép sắp xếp. Xếp 'Cali' lên trước là
   lỗi ấy hiện ra ngay. */
const BOOT = { donVi: ['Cali', 'K&H', 'KVC', 'POSH'], donViMe: 'K&H' };
const HET = ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG', 'VR SC VIVO Q7', 'Cali Thảo Điền', 'BỆNH VIỆN 175'];   // cả hệ, đúng thứ tự khai trong danh mục
function esc(x) { return String(x == null ? '' : x); }

function moiTruong(extra) {
  return new Function('CFG', 'BOOT', 'esc', '_csLabel', '_csPhu', '_csNhan', '_bd',
    fnDvMe + '\n' + fnLaDvMe + '\n' + fnDvChuan + '\n' + fnTheoDv + '\n' + fnSel + '\n' + (extra || '') +
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
/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 NHÀ MẸ ĐỌC CẢ HỆ — PHÉP NÀY TRƯỚC ĐÂY ĐÒI NGƯỢC LẠI
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Bản cũ: `teq('chọn K&H → CHỈ gian K&H', ...)`. Nó xanh suốt vì lúc dựng bài, danh mục thử chỉ
 * có K&H và POSH — chưa dòng nào mang 'KVC'. Đến 19/09/2026 anh Thắng khai xong khối
 * "🏢 ĐƠN VỊ KVC · 21 cơ sở" thì phép so-bằng-nhau ấy GIẤU sạch 21 gian khỏi mọi tài khoản K&H,
 * và anh báo: *"rồi này thì không thấy đâu"*.
 *
 * Máy chủ (`VHCP_DonVi`) vốn đã chốt K&H là NHÀ MẸ đọc cả hệ; giao diện thì không biết, vì tên
 * nhà mẹ chưa từng được gửi xuống. Một luật mà hai nơi giữ hai bản thì sớm muộn lệch — và ở đây
 * nó lệch theo hướng tệ nhất: hộp chọn thiếu → người khai gõ tay một mã → người được phân mở
 * app ra trắng.
 *
 * ⚠️ CHIỀU NGƯỢC LẠI KHÔNG ĐỔI, và mấy phép POSH ngay dưới canh đúng chỗ đó: nhà con vẫn chỉ
 *    thấy nhà mình. Nới cả hai chiều mới là hỏng.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
teq('🔴 chọn K&H (nhà mẹ) → thấy CẢ HỆ, kể cả gian KVC và POSH',
  HET, F.theo('K&H'));
teq('🔴 để trống cũng là nhà mẹ → cũng cả hệ',
  HET, F.theo(''));
/* ⚠️ KHÔNG CÓ NHÀ MẸ thì không ai được nhìn xuyên. Khoá `vhcp_dv_me` để trống là tắt hẳn luật
   này, và lúc ấy K&H trở lại ngang hàng mọi đơn vị khác. */
const F_KHONG_ME = (function () {
  const luu = BOOT.donViMe; BOOT.donViMe = '';
  const r = moiTruong().theo('K&H'); BOOT.donViMe = luu; return r;
})();
teq('🔴 tắt nhà mẹ (vhcp_dv_me rỗng) → K&H lại chỉ thấy gian K&H',
  ['ADV GO! AN LẠC', 'NHÀ MA BÌNH DƯƠNG'], F_KHONG_ME);
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
teq('🔴 dòng K&H (nhà mẹ): hộp bày cả hệ — đây là chỗ 21 gian KVC từng biến mất',
  HET, dsTrongHop(F.sel('', 'K&H')));
teq('dòng chưa khai đơn vị: cũng là nhà mẹ',
  HET, dsTrongHop(F.sel('', '')));

/* 🔴 GỌI KHÔNG KÈM ĐƠN VỊ THÌ KHÔNG LỌC GÌ. Bảng đăng nhập Google chưa có cột Đơn vị; lọc bừa
   theo nhà mặc định là nuốt mất mọi cơ sở POSH của nó. */
teq('🔴 gọi một tham số (bảng chưa có cột Đơn vị) → bày hết như trước',
  HET, dsTrongHop(F.sel('')));

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

/* ═══════════════════════════════════════════════════════════════════════════════════════
 * 5. Ô ĐƠN VỊ ĐÃ THÀNH Ô ẨN — KHÔNG CÒN LƯỢT "ĐỔI RỒI VẼ LẠI HỘP CƠ SỞ"
 *
 * 🔴 MỤC NÀY TỪ 21/09/2026 CANH ĐIỀU NGƯỢC BẢN CŨ. Trước đây nó chạy thật `_dvDoi()` để
 *    chứng minh gõ "POSH" là hộp cơ sở cùng hàng đổi theo ngay. Nay cột Đơn vị đã nhường chỗ
 *    cho cột Khối (anh Thắng 21/09/2026: *"chỗ đơn vị thay bằng khối"*), giá trị đơn vị nằm
 *    trong một ô ẩn và không ai gõ vào nó nữa — nên `_dvDoi()` bỏ hẳn. Giữ lại phép cũ là
 *    canh một hàm không còn lý do tồn tại, và ép người sau dựng lại nó chỉ để bài kiểm xanh.
 *
 * ⚠️ HỘP CƠ SỞ VẪN PHẢI ĐƯỢC LỌC THEO ĐƠN VỊ — đó mới là chốt thật, và nó không đổi:
 *    `_uHang()` vẫn truyền `u.donVi` vào `_cosoSel()`. Bỏ cái đó là mọi hàng bày cả gian của
 *    nhà khác, mà phân cơ sở nhầm là phân quyền nhầm.
 * ═══════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 `_dvDoi()` đã bỏ hẳn — không còn ô Đơn vị để gõ', bocHam('_dvDoi').length === 0);
t('   và hàm dựng ô Đơn vị cũ cũng vậy', bocHam('_dvInp').length === 0);
t('🔴 nhưng bảng người dùng VẪN truyền đơn vị vào hộp cơ sở',
  /_cosoSel\(u\.coso,\s*u\.donVi\)/.test(HTML));
t('🔴 và giá trị đơn vị vẫn đi theo hàng trong một ô ẩn', HTML.indexOf('data-dv-cu') >= 0);

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
