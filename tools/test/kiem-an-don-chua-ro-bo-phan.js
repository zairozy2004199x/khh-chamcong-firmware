/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN QUYẾT TOÁN PHẢI NÓI RA — VÀ ẨN ĐƯỢC — ĐƠN "CHƯA RÕ BỘ PHẬN"
 *
 * Anh Thắng 08/09/2026: *"lý do sao tk kế toán mtd vẫn hiện đơn kvc"*, kèm ảnh màn Quyết toán
 * của tài khoản mang vai "Kế toán máy tự động" mà bảng vẫn đầy đơn khu vui chơi.
 *
 * Máy chủ trả cờ `bpMo` cho từng đơn (xem `kiem-don-chua-ro-bo-phan.php`). Bài này lo nửa còn
 * lại: màn có THẬT SỰ dùng cờ ấy không.
 *
 * 🔴 BA CHỖ DỄ QUÊN, và quên chỗ nào cũng cho ra một màn "trông như đã chạy":
 *      · ô tích bật mà bảng vẫn đủ đơn — lọc không gọi tới `_anVaoMo`
 *      · bảng rỗng mà câu giải thích vẫn đổ cho ô lọc tuần — người ta đi tìm nhầm chỗ
 *      · ô lọc tuần còn bày những tuần chỉ có đơn đã ẩn — chọn vào là bảng trắng
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY, KHÔNG CHÉP LUẬT VÀO ĐÂY.
 *
 * Chạy: node tools/test/kiem-an-don-chua-ro-bo-phan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n', i);
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}

/* ── Sân giả: mấy ô lọc của màn Quyết toán + vài mảnh vụn app dùng chung ─────────────────── */
const O = { qtThang: '', qtKy: '', qtCoso: '', qtKyXong: '' };
function el(id) { return Object.prototype.hasOwnProperty.call(O, id) ? { value: O[id] } : null; }
function esc(x) { return String(x == null ? '' : x); }
function _thangCuaKy(k) { const m = /(\d{1,2})\/(\d{4})\s*$/.exec(String(k || '')); return m ? (m[1] + '/' + m[2]) : ''; }
function xoaLoc() { O.qtThang = ''; O.qtKy = ''; O.qtCoso = ''; O.qtKyXong = ''; }

/* ── Bốc hàm thật ─────────────────────────────────────────────────────────────────────────── */
const fnAnVaoMo = bocDong('_anVaoMo');
const fnQtLoc = bocHam('_qtLoc');
const fnQtLocXong = bocHam('_qtLocXong');
const fnDaQT = bocDong('_daQT');
const fnEmpty = bocHam('_qtEmptyXongText');
const fnBanner = bocHam('renderBpBanner');
t('bốc được _anVaoMo()', fnAnVaoMo.length > 20, fnAnVaoMo);
t('bốc được _qtLoc()', fnQtLoc.length > 40);
t('bốc được _qtLocXong()', fnQtLocXong.length > 40);
t('bốc được _qtEmptyXongText()', fnEmpty.length > 40);
t('bốc được renderBpBanner()', fnBanner.length > 40);

/** Dựng bộ lọc thật, với `_AN_MO` đặt theo ý mình. */
function locVoi(anMo) {
  return new Function('el', '_thangCuaKy', '_AN_MO',
    fnAnVaoMo + '\n' + fnQtLoc + '\n' + fnQtLocXong +
    '\nreturn { loc:_qtLoc, locXong:_qtLocXong, an:_anVaoMo };')(el, _thangCuaKy, anMo);
}

const D_RO = { maDon: 'D_MTD', ky: 'Tuần 1 · 09/2026', coso: 'CS1', trangThai: 'Chờ quyết toán', bpMo: false };
const D_MO = { maDon: 'D_KVC', ky: 'Tuần 2 · 09/2026', coso: 'CS2', trangThai: 'Chờ quyết toán', bpMo: true };

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. LỐI ẨN — `_anVaoMo()`
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
let X = locVoi(false);
teq('ô tích TẮT · đơn chưa rõ vẫn hiện', false, X.an(D_MO));
teq('ô tích TẮT · đơn rõ dĩ nhiên hiện', false, X.an(D_RO));
X = locVoi(true);
teq('🔴 ô tích BẬT · đơn chưa rõ bị ẩn', true, X.an(D_MO));
teq('🔴 ô tích BẬT · đơn ĐÃ RÕ KHÔNG bị ẩn lây', false, X.an(D_RO));
teq('không có đơn → không nổ', false, !!X.an(null));
teq('đơn máy chủ cũ chưa có cờ → coi như rõ, vẫn hiện', false, !!X.an({ maDon: 'D9' }));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. HAI BỘ LỌC CỦA MÀN PHẢI ĐI QUA LỐI ẤY
 *
 * 🔴 Đây là chỗ hỏng thật sự nếu quên: cờ có, ô tích có, dải nhắc có — mà bảng vẫn đủ đơn.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
xoaLoc();
X = locVoi(false);
teq('ô tích TẮT · lọc chung cho đơn chưa rõ qua',  true, X.loc(D_MO));
teq('ô tích TẮT · lọc bảng-đã-xong cũng cho qua',  true, X.locXong(D_MO));
X = locVoi(true);
teq('🔴 ô tích BẬT · lọc chung CHẶN đơn chưa rõ',       false, X.loc(D_MO));
teq('🔴 ô tích BẬT · lọc bảng-đã-xong CŨNG CHẶN',       false, X.locXong(D_MO));
teq('🔴 và đơn đã rõ vẫn qua cả hai · chung',           true,  X.loc(D_RO));
teq('🔴 và đơn đã rõ vẫn qua cả hai · đã xong',         true,  X.locXong(D_RO));

/* Ô tích không được nuốt mất mấy ô lọc cũ — chúng vẫn phải cắt như trước. */
O.qtCoso = 'CS9';
teq('đối chứng · ô cơ sở vẫn cắt được đơn đã rõ', false, X.loc(D_RO));
O.qtCoso = 'CS1';
teq('đối chứng · đúng cơ sở thì qua',             true,  X.loc(D_RO));
xoaLoc();

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. BẢNG RỖNG PHẢI ĐỔ ĐÚNG TỘI
 *
 * Kế toán bật ô tích buổi sáng, chiều mở lại thấy bảng trắng. Câu giải thích mà chỉ nhắc ô lọc
 * tuần thì họ đi gỡ lọc tuần — gỡ xong vẫn trắng, và kết luận là mất dữ liệu.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function noiGi(dons, anMo) {
  const O2 = { qtEmptyXong: { textContent: '' } };
  const el2 = function (id) {
    if (id === 'qtEmptyXong') return O2.qtEmptyXong;
    return Object.prototype.hasOwnProperty.call(O, id) ? { value: O[id] } : null;
  };
  new Function('el', '_thangCuaKy', '_AN_MO', 'BOOT',
    fnDaQT + '\n' + fnAnVaoMo + '\n' + fnEmpty + '\n_qtEmptyXongText(0);')(el2, _thangCuaKy, anMo, { dons: dons });
  return O2.qtEmptyXong.textContent;
}
const XONG_MO = { maDon: 'D1', ky: 'Tuần 2 · 09/2026', trangThai: 'Đã quyết toán', bpMo: true };
const XONG_RO = { maDon: 'D2', ky: 'Tuần 1 · 09/2026', trangThai: 'Đã xuất MISA', bpMo: false };

xoaLoc();
teq('chưa có đơn nào đã quyết toán → nói đúng thế', 'Chưa có đơn nào đã quyết toán.', noiGi([], true));
const c1 = noiGi([XONG_MO], true);
t('🔴 rỗng vì ô tích → câu giải thích PHẢI nhắc ô tích', /Ẩn hẳn đơn chưa rõ bộ phận/.test(c1), c1);
t('và nói đúng số đơn đang bị giấu', /\b1\b/.test(c1), c1);
t('KHÔNG đổ oan cho ô lọc tuần', !/tuần riêng/.test(c1), c1);
const c2 = noiGi([XONG_MO], false);
t('đối chứng · ô tích TẮT mà vẫn rỗng thì không nhắc ô tích', !/Ẩn hẳn/.test(c2), c2);

O.qtKyXong = 'Tuần 9 · 09/2026';
const c3 = noiGi([XONG_MO, XONG_RO], true);
t('🔴 vừa bị ô tích vừa bị lọc tuần → kể RA CẢ HAI · ô tích', /Ẩn hẳn đơn chưa rõ bộ phận/.test(c3), c3);
t('🔴 vừa bị ô tích vừa bị lọc tuần → kể RA CẢ HAI · lọc tuần', /tuần riêng/.test(c3), c3);
xoaLoc();

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. DẢI NHẮC — nói số đơn chưa rõ, và bày ô tích ĐÚNG trạng thái đang có
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function veDai(boot, anMo) {
  const hop = { style: {}, innerHTML: '' };
  new Function('el', 'esc', 'BOOT', '_AN_MO', 'toggleAnMo',
    fnBanner + '\nrenderBpBanner();')(
    function (id) { return id === 'bpBanner' ? hop : null; }, esc, boot, anMo, function () {});
  return hop;
}
let b = veDai({ boPhanBo: '', loaiChuaBP: 0, dons: [] }, false);
teq('🔴 người KHÔNG bó bộ phận → dải nhắc TẮT HẲN', 'none', b.style.display);
teq('và không vẽ chữ gì', '', b.innerHTML);

b = veDai({ boPhanBo: 'Máy tự động', loaiChuaBP: 0, dons: [D_RO] }, false);
t('người bị bó → dải nhắc bật', b.style.display !== 'none');
t('nói rõ đang xem bộ phận nào', /Máy tự động/.test(b.innerHTML), b.innerHTML);
t('không còn loại nào chưa khai → khen một câu', /đều đã khai bộ phận/.test(b.innerHTML), b.innerHTML);
t('🔴 không đơn nào chưa rõ → KHÔNG bày ô tích (bày ra là mời tích một thứ rỗng)',
  !/type="checkbox"/.test(b.innerHTML), b.innerHTML);

b = veDai({ boPhanBo: 'Máy tự động', loaiChuaBP: 2, dons: [D_RO, D_MO, { maDon: 'D3', bpMo: true }] }, false);
t('🔴 đếm đúng số ĐƠN chưa rõ bộ phận', /2 đơn/.test(b.innerHTML), b.innerHTML);
t('nói đúng số LOẠI còn phải khai', /<b>2<\/b> loại/.test(b.innerHTML), b.innerHTML);
t('chỉ thẳng chỗ đi khai', /Loại chi phí/.test(b.innerHTML), b.innerHTML);
t('🔴 có đơn chưa rõ → bày ô tích', /type="checkbox"/.test(b.innerHTML), b.innerHTML);
t('ô tích đang TẮT thì không tự đánh dấu sẵn', !/checked/.test(b.innerHTML), b.innerHTML);

b = veDai({ boPhanBo: 'Máy tự động', loaiChuaBP: 2, dons: [D_RO, D_MO] }, true);
t('🔴 ô tích đang BẬT → vẽ lại vẫn thấy nó bật (không thì tích xong nó tự nhả)',
  /checked/.test(b.innerHTML), b.innerHTML);
t('và vẫn đếm được đơn đang bị giấu', /1 đơn/.test(b.innerHTML), b.innerHTML);
t('nói rõ đây chỉ là cách xem của riêng mình', /không đổi dữ liệu/.test(b.innerHTML), b.innerHTML);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. DẤU ❓BP TRÊN TỪNG HÀNG — để còn biết đơn nào mà đi khai
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnRow = bocHam('_qtRowHtml');
t('bốc được _qtRowHtml()', fnRow.length > 200);
function veHang(d) {
  return new Function('el', 'esc', 'money', 'canDo', '_laChim', 'stCls', 'd',
    fnRow + '\nreturn _qtRowHtml(d, false, 9);')(
    function () { return null; }, esc,
    function (x) { return String(Number(x) || 0); },
    function () { return true; },
    function (x) { return x && x.trangThai === 'Đã cấp tạm ứng'; },
    function () { return 'st-duyet'; }, d);
}
const H_MO = veHang({ maDon: 'D_KVC', ky: 'K', trangThai: 'Chờ quyết toán', bpMo: true });
const H_RO = veHang({ maDon: 'D_MTD', ky: 'K', trangThai: 'Chờ quyết toán', bpMo: false });
t('🔴 đơn chưa rõ bộ phận mang dấu ❓BP', /❓BP/.test(H_MO), H_MO);
t('🔴 đơn đã rõ KHÔNG mang dấu ấy', !/❓BP/.test(H_RO), H_RO);
t('dấu có lời giải thích khi rê chuột', /title="Chưa rõ đơn này thuộc bộ phận nào/.test(H_MO), H_MO);
t('đơn cũ chưa có cờ thì cũng không mang dấu', !/❓BP/.test(veHang({ maDon: 'D9', ky: 'K', trangThai: 'Nháp' })));
t('đối chứng · vẫn mở được Chi tiết như thường', /viewDon\('D_KVC'\)/.test(H_MO), H_MO);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. Ô TÍCH NHỚ Ở MÁY NGƯỜI DÙNG, VÀ VẼ LẠI CẢ BA CHỖ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnToggle = bocHam('toggleAnMo');
t('bốc được toggleAnMo()', fnToggle.length > 40);
const KHO = {};
const goi = [];
function chayToggle(check) {
  const f = new Function('localStorage', 'renderBpBanner', 'renderQTList', 'renderDuyet', '_AN_MO',
    fnToggle + '\nreturn function(o){ toggleAnMo(o); return _AN_MO; };')(
    { getItem: function (k) { return Object.prototype.hasOwnProperty.call(KHO, k) ? KHO[k] : null; },
      setItem: function (k, v) { KHO[k] = String(v); } },
    function () { goi.push('banner'); }, function () { goi.push('qt'); }, function () { goi.push('duyet'); }, false);
  return f({ checked: check });
}
teq('tích vào → cờ bật', true, chayToggle(true));
teq('🔴 và nhớ lại ở máy người dùng', '1', KHO.vhcp_an_bp_mo);
teq('bỏ tích → nhớ luôn trạng thái tắt (không phải xoá khoá)', '0', (chayToggle(false), KHO.vhcp_an_bp_mo));
t('🔴 tích một cái là vẽ lại dải nhắc', goi.indexOf('banner') >= 0, goi);
t('🔴 và vẽ lại bảng Quyết toán (không thì tích xong bảng đứng im)', goi.indexOf('qt') >= 0, goi);
t('và vẽ lại cả màn Duyệt tạm ứng', goi.indexOf('duyet') >= 0, goi);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 7. Ô LỌC TUẦN KHÔNG ĐƯỢC BÀY TUẦN CHỈ CÒN ĐƠN ĐÃ ẨN
 *
 * 🔴 Ẩn đơn xong mà ô xổ vẫn còn tuần của nó thì kế toán chọn vào và nhận bảng trắng — rồi đi
 *    tìm một lỗi không có thật. Ô lọc phải dựng từ đúng những đơn còn hiện.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnTrongMan = bocHam('_qtTrongMan');
t('bốc được _qtTrongMan()', fnTrongMan.length > 40);
function trongManVoi(anMo) {
  return new Function('_AN_MO',
    fnDaQT + '\n' + fnAnVaoMo + '\n' + fnTrongMan + '\nreturn _qtTrongMan;')(anMo);
}
const D_CHIM = { maDon: 'D_TU', ky: 'Tuần 3 · 09/2026', trangThai: 'Đã cấp tạm ứng', bpMo: true };
let tm = trongManVoi(false);
teq('ô tích TẮT · đơn chưa rõ vẫn góp tuần vào ô lọc', true, tm(D_MO));
teq('đối chứng · đơn của khâu trước KHÔNG thuộc màn này', false, tm({ trangThai: 'Chờ duyệt tạm ứng' }));
teq('đối chứng · đơn đã xuất MISA vẫn thuộc màn này',      true,  tm({ trangThai: 'Đã xuất MISA' }));
teq('đối chứng · đơn đang cầm tiền cũng thuộc màn này',    true,  tm({ trangThai: 'Đã cấp tạm ứng' }));
tm = trongManVoi(true);
teq('🔴 ô tích BẬT · tuần của đơn chưa rõ bị bỏ khỏi ô lọc', false, tm(D_MO));
teq('🔴 kể cả đơn chìm (đang cầm tiền) mà chưa rõ',          false, tm(D_CHIM));
teq('🔴 và đơn đã rõ vẫn góp tuần như thường',               true,  tm(D_RO));

/* Và màn Quyết toán phải THẬT SỰ dựng ô lọc qua chốt ấy — chạy cả `renderQTList()` để xem nó
   đưa cái gì vào ô xổ và vào từng bảng. Bốc chốt ra kiểm riêng thì chốt đúng mà chỗ gọi quên
   là bộ thử vẫn xanh. */
const fnQTList = bocHam('renderQTList');
t('bốc được renderQTList()', fnQTList.length > 800);
function chayQTList(dons, anMo) {
  const thu = { loc: null, kyRieng: null, cho: null, xong: null };
  const el4 = function (id) { return Object.prototype.hasOwnProperty.call(O, id) ? { value: O[id] } : null; };
  new Function('el', 'BOOT', '_AN_MO', 'QT_XEM', '_thangCuaKy', 'canDo', 'renderBpBanner',
    '_napLocDon', '_napKyRieng', '_qtVeBang', '_qtVeChuaNop', '_qtEmptyXongText', 'qtUpdateBar',
    '_laChim', '_kyVal', 'thu',
    fnDaQT + '\n' + fnAnVaoMo + '\n' + fnTrongMan + '\n' + fnQtLoc + '\n' + fnQtLocXong + '\n' +
    fnQTList + '\nrenderQTList();')(
    el4, { dons: dons }, anMo, 'bang', _thangCuaKy, function () { return false; }, function () {},
    function (l) { thu.loc = l.map(function (x) { return x.maDon; }); },
    function (_i, l) { thu.kyRieng = l.map(function (x) { return x.maDon; }); },
    function (ten, l) { thu[ten] = l.map(function (x) { return x.maDon; }); },
    function () {}, function () {}, function () {},
    function (x) { return x && x.trangThai === 'Đã cấp tạm ứng'; },
    function () { return 0; }, thu);
  return thu;
}
const Q_RO = { maDon: 'Q_MTD', ky: 'Tuần 1 · 09/2026', coso: 'CS1', trangThai: 'Chờ quyết toán', bpMo: false };
const Q_MO = { maDon: 'Q_KVC', ky: 'Tuần 2 · 09/2026', coso: 'CS2', trangThai: 'Chờ quyết toán', bpMo: true };
const Q_XONG_MO = { maDon: 'Q_XONG', ky: 'Tuần 2 · 09/2026', coso: 'CS2', trangThai: 'Đã xuất MISA', bpMo: true };

xoaLoc();
let R = chayQTList([Q_RO, Q_MO, Q_XONG_MO], false);
teq('ô tích TẮT · ô lọc dựng từ cả ba đơn', ['Q_MTD', 'Q_KVC', 'Q_XONG'], R.loc);
teq('ô tích TẮT · bảng chờ có cả hai đơn chờ',  ['Q_MTD', 'Q_KVC'], R.cho);
teq('ô tích TẮT · bảng đã xong có đơn đã xuất', ['Q_XONG'], R.xong);

R = chayQTList([Q_RO, Q_MO, Q_XONG_MO], true);
teq('🔴 ô tích BẬT · ô lọc CHỈ dựng từ đơn còn hiện', ['Q_MTD'], R.loc);
teq('🔴 ô tích BẬT · ô tuần riêng cũng thế',          [], R.kyRieng);
teq('🔴 ô tích BẬT · bảng chờ bỏ đơn chưa rõ',        ['Q_MTD'], R.cho);
teq('🔴 ô tích BẬT · bảng đã xong cũng bỏ',           [], R.xong);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 8. MÀN DUYỆT TẠM ỨNG CŨNG PHẢI THEO
 *
 * Ẩn ở màn Quyết toán mà màn Duyệt tạm ứng vẫn bày đủ thì ô tích chỉ dọn được nửa nhà — và
 * đúng nửa còn lại là chỗ đang giữ tiền.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnDuyet = bocHam('renderDuyet');
t('bốc được renderDuyet()', fnDuyet.length > 400);
function duyetVoi(dons, anMo) {
  let thay = null;
  const O3 = {
    duyetFilter: { value: 'all' }, dvThang: { value: '' }, dvKy: { value: '' }, dvCoso: { value: '' },
    duyetEmpty: { style: {} }, duyetBody: { innerHTML: '' },
  };
  new Function('el', 'esc', 'money', 'canDo', 'stCls', 'BOOT', 'CURUSER', '_AN_MO',
    '_thangCuaKy', '_napLocDon', '_renderTongLH', '_tachDonVi', 'dvUpdateBar', 'ghiLai',
    fnAnVaoMo + '\n' + fnDuyet + '\nrenderDuyet();')(
    function (id) { return Object.prototype.hasOwnProperty.call(O3, id) ? O3[id] : null; },
    esc, function (x) { return String(Number(x) || 0); }, function () { return true; },
    function () { return 'st-duyet'; }, { dons: dons }, { role: 'Kế toán máy tự động' }, anMo,
    _thangCuaKy, function () {}, function () {},
    function (l) { thay = l.map(function (x) { return x.maDon; }); return ''; },
    function () {}, function () {});
  return thay;
}
const TU_RO = { maDon: 'T_MTD', ky: 'K', trangThai: 'Chờ duyệt tạm ứng', bpMo: false };
const TU_MO = { maDon: 'T_KVC', ky: 'K', trangThai: 'Chờ cấp tạm ứng', bpMo: true };
teq('ô tích TẮT · màn Duyệt bày cả hai đơn', ['T_MTD', 'T_KVC'], duyetVoi([TU_RO, TU_MO], false));
teq('🔴 ô tích BẬT · màn Duyệt cũng bỏ đơn chưa rõ', ['T_MTD'], duyetVoi([TU_RO, TU_MO], true));
teq('🔴 và không bỏ nhầm đơn đã rõ', ['T_MTD'], duyetVoi([TU_RO], true));

/* 🔴 DẢI NHẮC PHẢI NẰM NGOÀI MỌI TRANG CON. Trước đây nó ở trong tab Tổng quan, mà anh Thắng
   đứng ở tab Quyết toán — nên câu giải thích duy nhất về chuyện đang bị bó thì anh không thấy,
   và kết luận là tính năng không chạy.

   ⚠️ `#main` KHÔNG PHẢI KHUNG CHUNG. Trông thì giống, nhưng nó là vùng CHI TIẾT MỘT ĐƠN:
      `openDon()` bật lên, `logout()` tắt đi. Đặt dải nhắc vào đó là nó chỉ hiện khi người ta
      đã mở sẵn một đơn ra — bản nháp đầu của chính bản này đã sai đúng như thế. */
const iBanner = HTML.indexOf('id="bpBanner"');
const iTabBar = HTML.indexOf('id="tabbar"');
const iPage1 = HTML.indexOf('id="page-tongquan"');
const iMain = HTML.indexOf('id="main"');
t('bốc được vị trí dải nhắc trong trang', iBanner > 0 && iTabBar > 0 && iPage1 > 0 && iMain > 0);
t('🔴 dải nhắc nằm dưới THANH TAB — chỗ tab nào cũng thấy', iBanner > iTabBar,
  { banner: iBanner, tabbar: iTabBar });
t('🔴 và nằm NGOÀI mọi trang con', iBanner < iPage1, { banner: iBanner, page1: iPage1 });
t('🔴 KHÔNG nằm trong `#main` (vùng chi tiết một đơn, chỉ hiện khi mở đơn)', iBanner < iMain,
  { banner: iBanner, main: iMain });
/* Và `#main` đúng là vùng chi tiết đơn thật — chốt trên chỉ có nghĩa nếu điều đó còn đúng. */
t('đối chứng · `#main` do openDon bật / logout tắt', /el\('main'\)\.style\.display='none'/.test(HTML));
t('🔴 và dải nhắc được vẽ lại ở MỌI lượt đổi tab', /showPage[\s\S]{0,4000}?renderBpBanner\(\)/.test(HTML));

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: đơn chưa rõ bộ phận được đánh dấu, đếm đúng, ẩn được, và bảng rỗng đổ đúng tội.');
