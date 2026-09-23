/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TAB QUYẾT TOÁN — BẢNG "ĐÃ QUYẾT TOÁN, CHỜ THANH TOÁN" TÁCH RIÊNG, TRẢ TIỀN CẢ TUẦN.
 *
 * Anh Thắng 23/09/2026: *"tab duyệt tạm ứng và duyệt quyết toán tách 2 bảng riêng để dễ theo
 * dõi đơn … check 1 lần duyệt và chi 1 lần"*, rồi *"làm luôn quyết toán đi em"*.
 *
 * 🔴 CÁI HỎNG NGUY: KÉO NHẦM ĐƠN VÀO BẢNG "CHỜ TIỀN". Đơn qua tạm ứng của Khu vui chơi KHÔNG có
 *    bước "Đã thanh toán" — tiền đã đưa từ lúc cấp tạm ứng. Kéo nó lên bảng chờ thanh toán là
 *    mời kế toán bấm "trả tiền" lần thứ hai cho một khoản đã trả; máy chủ chối, nhưng mỗi tuần
 *    một câu chối là người ta hết tin cái nút. Phép tách phải hỏi LUỒNG CỦA ĐƠN, cùng luật với
 *    `_nutThanhToan()` và `danh_dau_thanh_toan()`.
 *
 * Chạy: node tools/test/kiem-qt-cho-thanh-toan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DON = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');
const API = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-api.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); };
const khoi = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('};', i) + 2); };

/* ═══ 0. BỆ ĐỠ ═════════════════════════════════════════════════════════════════ */
['_qtChoTT', '_qtTtHdr', 'qtThanhToanTuan', '_qtVeBangTT', '_dvGomTuan'].forEach(function (n) {
  t('⚠️ bốc được `' + n + '()`', ham(n).length > 40, ham(n).length);
});
t('🔴 thẻ mới có mặt', /id="qtCardTT"/.test(HTML) && /<tbody id="qtBodyTT">/.test(HTML));
t('   và đứng GIỮA "Chờ quyết toán" và "Đã quyết toán"',
  HTML.indexOf('id="qtCardCho"') < HTML.indexOf('id="qtCardTT"') && HTML.indexOf('id="qtCardTT"') < HTML.indexOf('id="qtCardXong"'));

const NEN = [dong('KHOI_LUONG_CHI'), khoi('LUONG_KVC'), khoi('LUONG_CHI'), khoi('LUONG_TT'),
  ham('_luongKhoi'), ham('_luongDon'), ham('_ttTrongLuong'), ham('_qtChoTT')].join('\n');
const choTT = (d, khoiDang) => new Function('KHOI_DANG', NEN + '\nreturn _qtChoTT(' + JSON.stringify(d) + ');')(khoiDang || 'kvc');

/* ═══ 1. 🔴 PHÉP TÁCH HỎI LUỒNG CỦA ĐƠN ══════════════════════════════════════════ */
t('🔴 đơn TRỰC TIẾP đã quyết toán → chờ thanh toán', choTT({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'tt' }) === true);
t('🔴 đơn DUYỆT CHI đã quyết toán → chờ thanh toán', choTT({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'dc' }) === true);
t('🔴 đơn cũ khối MTĐ đã quyết toán → chờ thanh toán (luồng chi của khối có bước ấy)',
  choTT({ trangThai: 'Đã quyết toán', khoi: 'mtd' }) === true);
/* 🔴 PHÉP CỐT LÕI: đơn qua tạm ứng KVC không có bước này — không được kéo lên. */
t('🔴 đơn QUA TẠM ỨNG của KVC đã quyết toán → KHÔNG chờ thanh toán (đã xong)',
  choTT({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'gt' }) === false);
t('   đơn cũ khối KVC cũng vậy', choTT({ trangThai: 'Đã quyết toán', khoi: 'kvc' }) === false);
/* ⚠️ Khối của ĐƠN, không phải khối đang đứng. */
t('⚠️ đứng ở khối MTĐ cũng không kéo đơn "gt" của KVC lên', choTT({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'gt' }, 'mtd') === false);
/* Trạng thái khác thì không vào — kể cả đơn đã thanh toán rồi. */
t('🔴 đơn ĐÃ THANH TOÁN không còn chờ', choTT({ trangThai: 'Đã thanh toán', khoi: 'kvc', luong: 'tt' }) === false);
t('   đơn chờ quyết toán chưa tới lượt', choTT({ trangThai: 'Chờ quyết toán', khoi: 'kvc', luong: 'tt' }) === false);
t('   `null` không nổ', choTT(null) === false);

/* ═══ 2. renderQTList CHIA ĐÚNG — không rơi, không trùng ═══════════════════════ */
const RQ = ham('renderQTList');
t('⚠️ bốc được `renderQTList`', RQ.length > 800, RQ.length);
t('🔴 tách từ CÙNG một nguồn (`daQtHet`), hai bảng bù nhau bằng chính `_qtChoTT`',
  /var choTT=daQtHet\.filter\(_qtChoTT\);/.test(RQ) && /var xong=daQtHet\.filter\(function\(d\)\{ return !_qtChoTT\(d\); \}\);/.test(RQ));
t('🔴 và vẽ ra bảng riêng', /_qtVeBangTT\(choTT\);/.test(RQ));
t('⚠️ chế độ xem theo tuần giấu thẻ này y như hai thẻ kia', /el\('qtCardTT'\)\.style\.display=tuan\?'none':''/.test(RQ));
{
  /* Chạy thật phép chia: 4 đơn → không đơn nào rơi, không đơn nào nằm hai bảng. */
  const ds = [{ maDon: 'a', trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'tt' },
    { maDon: 'b', trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'gt' },
    { maDon: 'c', trangThai: 'Đã thanh toán', khoi: 'kvc', luong: 'tt' },
    { maDon: 'd', trangThai: 'Đã xuất MISA', khoi: 'kvc' }];
  const r = new Function('KHOI_DANG', 'ds', NEN + '\nvar c=ds.filter(_qtChoTT), x=ds.filter(function(d){return !_qtChoTT(d);});'
    + 'return {c:c.map(function(d){return d.maDon;}), x:x.map(function(d){return d.maDon;})};')('kvc', ds);
  teq('🔴 chờ thanh toán = đúng đơn còn nợ bước', ['a'], r.c);
  teq('🔴 đã xong = phần còn lại, đủ ba đơn', ['b', 'c', 'd'], r.x);
}

/* ═══ 3. TIÊU ĐỀ TUẦN + NÚT TRẢ TIỀN CẢ TUẦN ════════════════════════════════════ */
function hdr(rows, quyen) {
  return new Function('canDo', 'esc', '_qtSumHtml', ham('_qtTtHdr') + "\nreturn _qtTtHdr('T', rows, 'qtk0', false);".replace('rows', JSON.stringify(rows)))(
    function () { return quyen !== false; }, function (x) { return String(x == null ? '' : x); }, function () { return 'SUM'; });
}
const H = hdr([{ maDon: 'A1' }, { maDon: 'A2' }]);
t('🔴 nút mang đủ mã đơn của tuần trong `data-ms`', /data-ms="A1,A2"/.test(H), H);
t('🔴 nút đếm đúng số đơn', /Thanh toán cả tuần \(2\)/.test(H), H);
t('🔴 không có quyền `xacNhanQT` → không bày nút', !/qtThanhToanTuan/.test(hdr([{ maDon: 'A1' }], false)));
t('⚠️ nút chặn sự kiện lan lên hàng tiêu đề', /event\.stopPropagation\(\);qtThanhToanTuan/.test(H), H);
t('⚠️ tiêu đề 9 cột (bảng này không có ô tích)', /colspan="9"/.test(H), H);
t('   và kèm tổng của tuần (`_qtSumHtml`)', /SUM/.test(H));
/* Tiền tố nhóm riêng — không đụng tab Duyệt. */
t('🔴 tiền tố nhóm `qtk`, khác `dvk`/`dck`/`dmk` của tab Duyệt', /tien:'qtk'/.test(ham('_qtVeBangTT')));
t('   và dùng `_qtRowHtml` thật với class nhóm + gập', /_qtRowHtml\(d, false, 9, gid, gap\)/.test(ham('_qtVeBangTT')));

/* ═══ 4. BẤM NÚT — ĐI QUA CỬA "NHIỀU", NÓI RÕ KẾT QUẢ ══════════════════════════ */
function bam(ms, dap, kq) {
  const goi = { ms: null, toast: [], load: 0 };
  const run = { withSuccessHandler: function (ok) { run._ok = ok; return run; },
    withFailureHandler: function () { return run; },
    danhDauThanhToanNhieu: function (m) { goi.ms = m; run._ok(kq); } };
  new Function('toast', 'confirm', 'loading', 'google', 'CURUSER', '_log', 'loadQT', 'btn',
    ham('qtThanhToanTuan') + '\nqtThanhToanTuan(btn);')(
    function (k, m) { goi.toast.push(k + ':' + m); }, function () { return dap !== false; }, function () {},
    { script: { run: run } }, { name: 'KT' }, function () {}, function () { goi.load++; },
    { getAttribute: function () { return ms; } });
  return goi;
}
{
  const g = bam('A1,A2', true, { success: true, approved: 2 });
  teq('🔴 gửi đúng danh sách mã lên cửa `danhDauThanhToanNhieu`', ['A1', 'A2'], g.ms);
  t('   báo OK và tải lại', g.toast[0] === 'ok:Đã đánh dấu thanh toán 2 đơn' && g.load === 1, g);
}
{
  const g = bam('A1,A2', false);
  t('🔴 trả lời KHÔNG ở hộp xác nhận → không gọi gì', g.ms === null && g.toast.length === 0, g);
}
{
  const g = bam('', true);
  t('⚠️ nút rỗng → nói một câu, không gọi cửa', g.ms === null && /không còn đơn/.test(g.toast[0] || ''), g);
}
{
  const g = bam('A1,A2', true, { success: false, approved: 1, errors: ['A2: luồng không có bước'] });
  t('🔴 một đơn bị chối → báo WARN kể tên đơn, không báo xanh', /^warn:.*1 đơn.*A2/.test(g.toast[0] || ''), g.toast);
}

/* ═══ 5. MÁY CHỦ — CỬA "NHIỀU" CHỈ LẶP LẠI CỬA ĐƠN LẺ ═══════════════════════════ */
t('🔴 có `danh_dau_thanh_toan_nhieu()`', /public static function danh_dau_thanh_toan_nhieu\( \$ma_dons, \$nguoi \)/.test(DON));
t('🔴 và nó GỌI LẠI `danh_dau_thanh_toan()` cho từng đơn, không chép luật',
  /danh_dau_thanh_toan_nhieu[\s\S]{0,600}?\$r = self::danh_dau_thanh_toan\( \$m, \$nguoi \);/.test(DON));
t('   trả `approved` + `errors`, `success` chỉ khi không đơn nào chối',
  /danh_dau_thanh_toan_nhieu[\s\S]{0,900}?'success' => count\( \$errs \) === 0, 'approved' => \$ok, 'errors' => \$errs/.test(DON));
t('🔴 đăng ký cửa trong bảng định tuyến', /'danhDauThanhToanNhieu' => array\( 'VHCP_Don', 'danh_dau_thanh_toan_nhieu' \)/.test(API));
/* ⚠️ CẮT ĐÚNG KHỐI MẢNG rồi mới dò. Dò bằng `[\s\S]*?` là nó trượt qua dấu `);` của mảng sang
   tận bảng định tuyến (nơi tên cửa chắc chắn có mặt) — phép xanh kể cả khi cửa đã rơi khỏi danh
   sách người duyệt. Lọt lưới thật lúc phá thử 23/09/2026 (lượt P13). */
const KHOI_ND = (function () { const i = API.indexOf('$nguoi_duyet = array('); return i < 0 ? '' : API.slice(i, API.indexOf(');', i)); })();
t('⚠️ bốc được khối `$nguoi_duyet`', KHOI_ND.length > 100 && /'danhDauThanhToan'/.test(KHOI_ND), KHOI_ND.length);
t('🔴 và nằm trong danh sách CHỈ NGƯỜI DUYỆT được gọi', /'danhDauThanhToanNhieu'/.test(KHOI_ND));

if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: bảng chờ thanh toán tách đúng theo luồng của đơn, trả tiền cả tuần đi qua một cửa.');
