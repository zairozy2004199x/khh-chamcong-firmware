/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * THANH TỔNG LÚC NÀO CŨNG HIỆN — anh Thắng 07/09/2026
 *
 * *"mặc định lúc nào cũng hiện: Tổng tạm ứng (cho đơn đã duyệt tạm ứng) tại vị trí đang hiện
 * (nhớ cả bên quyết toán luôn)"*
 *
 * Trước đây thanh xanh ấy chỉ mọc ra KHI CÓ NGƯỜI TÍCH ĐƠN. Mà con số kế toán cần nhất lại là
 * con số KHI CHƯA TÍCH GÌ: tổng tạm ứng của những đơn đã duyệt — tức số tiền đang phải chi.
 * Muốn thấy nó thì phải tích chọn hết bảng rồi bỏ tích, hoặc tự cộng bằng mắt.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY TRÊN DOM GIẢ. Soi chuỗi thì không phân biệt được "thanh luôn hiện"
 *    với "thanh có mặt trong mã nhưng vẫn bị ẩn" — mà đó đúng là thứ đang sửa.
 *
 * Chạy: node tools/test/kiem-thanh-tong-luon-hien.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const CSS_QT = fs.readFileSync('wordpress/vhcp-chi-phi/assets/css/vhcp.css', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* Hàm khai gọn trên MỘT dòng — `bocHam` tìm dấu đóng `\n  }` nên không bắt được loại này. */
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

/* Bệ đỡ DOM giả: mỗi id một ô nhớ `textContent` / `style.display`. Mồi `display` bằng một giá
   trị KHÔNG phải đáp án nào, để phép nào không được hàm đụng tới thì đỏ chứ không xanh oan. */
function dungDom(ids) {
  const o = {};
  ids.forEach(function (id) { o[id] = { textContent: 'CHUA-DAT', style: { display: 'CHUA-DAT' } }; });
  return o;
}
function oTich(ds) {
  return ds.map(function (x) {
    return { getAttribute: function (k) {
      return k === 'data-tu' ? String(x.tu || 0)
        : k === 'data-tc' ? String(x.tc || 0)
        : k === 'data-cl' ? String(x.cl || 0)
        : k === 'data-st' ? (x.st || '') : (x.m || '');
    } };
  });
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. TAB DUYỆT TẠM ỨNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnDv = bocHam('dvUpdateBar');
t('bốc được dvUpdateBar()', fnDv.length > 300, fnDv.length);
t('đối chứng: hàm bốc ra khép kín', /\}\s*$/.test(fnDv), fnDv.slice(-30));
const fnDaDuyet = bocHam('_daDuyetTU');
t('bốc được chốt "đã duyệt tạm ứng"', fnDaDuyet.length > 20, fnDaDuyet);

const IDS_DV = ['duyetBatchBar', 'duyetSelInfo', 'duyetSelTotal', 'btnDuyetChon', 'btnCapChon', 'btnTraChon'];
function chayDv(list, tich, quyen) {
  const o = dungDom(IDS_DV);
  const f = new Function('el', 'DV_LIST', 'dvSelected', 'canDo', 'money',
    fnDaDuyet + '\n' + fnDv + '\ndvUpdateBar();');
  f(function (id) { return o[id] || null; }, list, function () { return oTich(tich); },
    function (v) { return quyen.indexOf(v) >= 0; },
    function (n) { return String(n); });
  return o;
}

/* Đúng bảng trong ảnh anh gửi: 6 đơn chờ duyệt, 3 đơn chờ cấp. */
const BANG = [
  { trangThai: 'Chờ duyệt tạm ứng', tamUng: 345000 },
  { trangThai: 'Chờ duyệt tạm ứng', tamUng: 45000 },
  { trangThai: 'Chờ duyệt tạm ứng', tamUng: 434000 },
  { trangThai: 'Chờ duyệt tạm ứng', tamUng: 237000 },
  { trangThai: 'Chờ duyệt tạm ứng', tamUng: 1000000 },
  { trangThai: 'Chờ duyệt tạm ứng', tamUng: 1540000 },
  { trangThai: 'Chờ cấp tạm ứng',   tamUng: 3780000 },
  { trangThai: 'Chờ cấp tạm ứng',   tamUng: 4150000 },
  { trangThai: 'Chờ cấp tạm ứng',   tamUng: 8149000 },
];

const a = chayDv(BANG, [], ['duyetTU', 'capTU', 'traDon']);
teq('🔴 chưa tích gì: thanh VẪN HIỆN', 'flex', a.duyetBatchBar.style.display);
/* 16.079.000 = đúng ba đơn "Chờ cấp tạm ứng" — con số trong ảnh anh gửi. */
teq('🔴 và cộng đúng tổng tạm ứng của đơn ĐÃ DUYỆT', '16079000đ', a.duyetSelTotal.textContent);
t('nhãn nói rõ đang cộng đơn đã duyệt', /đã duyệt tạm ứng/.test(a.duyetSelInfo.textContent), a.duyetSelInfo.textContent);
t('   và kèm số đơn', /^3 đơn/.test(a.duyetSelInfo.textContent), a.duyetSelInfo.textContent);
/* ⚠️ KHÔNG cộng nhầm đơn chưa duyệt: 6 đơn kia cộng lại là 3.601.000đ. */
t('⚠️ KHÔNG cộng đơn còn chờ duyệt', a.duyetSelTotal.textContent !== '19680000đ', a.duyetSelTotal.textContent);
/* Nút "Trả các đơn đã chọn" chỉ có nghĩa khi ĐANG chọn — bày lúc chưa tích là mời bấm một
   lệnh không có đối tượng. */
teq('⚠️ chưa tích thì ẩn nút "Trả các đơn đã chọn"', 'none', a.btnTraChon.style.display);
teq('và ẩn cả nút duyệt hàng loạt', 'none', a.btnDuyetChon.style.display);

/* --- CÓ TÍCH: quay về nghĩa cũ --- */
const b = chayDv(BANG, [{ st: 'Chờ cấp tạm ứng', tu: 3780000 }, { st: 'Chờ cấp tạm ứng', tu: 4150000 }],
  ['duyetTU', 'capTU', 'traDon']);
teq('🔴 tích 2 đơn: cộng đúng 2 đơn ấy', '7930000đ', b.duyetSelTotal.textContent);
teq('nhãn đổi sang "Đã chọn"', 'Đã chọn 2 đơn', b.duyetSelInfo.textContent);
teq('hiện nút cấp hàng loạt', '', b.btnCapChon.style.display);
teq('và hiện lại nút Trả', '', b.btnTraChon.style.display);

/* --- BẢNG RỖNG / CHƯA CÓ ĐƠN NÀO ĐÃ DUYỆT --- */
const c = chayDv([{ trangThai: 'Chờ duyệt tạm ứng', tamUng: 500000 }], [], ['duyetTU']);
teq('⚠️ chưa đơn nào đã duyệt: thanh vẫn hiện', 'flex', c.duyetBatchBar.style.display);
teq('và nói thẳng là chưa có', '0đ', c.duyetSelTotal.textContent);
t('   bằng câu người đọc hiểu', /Chưa có đơn nào/.test(c.duyetSelInfo.textContent), c.duyetSelInfo.textContent);

/* --- 🔴 CỘNG TỪ DỮ LIỆU, KHÔNG TỪ Ô TÍCH ---
   Ô tích chỉ mọc ở dòng người xem có quyền thao tác. Kế toán KHÔNG có quyền `duyetTU` nên
   sáu đơn "Chờ duyệt" của họ không có ô tích — nhưng tổng "đã duyệt" thì không liên quan gì
   tới quyền, và phải ra đúng con số ấy. */
const d = chayDv(BANG, [], ['capTU']);
teq('🔴 người không có quyền duyệt vẫn thấy đúng tổng', '16079000đ', d.duyetSelTotal.textContent);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. TAB QUYẾT TOÁN — cùng luật
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnQt = bocHam('qtUpdateBar');
t('bốc được qtUpdateBar()', fnQt.length > 300, fnQt.length);
t('đối chứng: hàm bốc ra khép kín', /\}\s*$/.test(fnQt), fnQt.slice(-30));

const IDS_QT = ['qtBatchBar', 'qtSelInfo', 'qtSelTU', 'qtSelTC', 'qtSelTotal', 'qtSelChim', 'btnQtChon'];
const fnSums = bocHam('_qtSums');
const fnLaChim = bocDong('_laChim');
t('bốc được _qtSums()', fnSums.length > 100, fnSums.length);
t('bốc được _laChim()', fnLaChim.length > 30, fnLaChim);
/* 🔴 ĐƠN CHÌM NAY NẰM CHUNG DANH SÁCH với đơn chờ (chúng đi chung bảng, dạng chìm), nên bệ đỡ
   phải nối hai mảng lại — đúng như `renderQTList()` làm. Truyền riêng như bản trước là bài
   kiểm dựng một cảnh không còn tồn tại. */
function chayQt(rows, chim, tich) {
  const o = dungDom(IDS_QT);
  const f = new Function('el', 'QT_ROWS_CHO', 'QT_ROWS_CHIM', 'qtSelected', 'money',
    fnLaChim + '\n' + fnSums + '\n' + fnQt + '\nqtUpdateBar();');
  f(function (id) { return o[id] || null; }, (rows || []).concat(chim || []), chim,
    function () { return oTich(tich); }, function (n) { return String(n); });
  return o;
}

/* Đúng bảng trong ảnh anh gửi: 7 đơn chờ quyết toán, tạm ứng 23.983.000, thực chi 21.967.000,
   thừa/thiếu 2.016.000. Rút gọn còn ba dòng nhưng giữ nguyên ba con số ấy. */
const QT = [
  { trangThai: 'Chờ quyết toán', tamUng: 3650000, soThucMua: 3090000, chenhLech: 560000 },
  { trangThai: 'Chờ quyết toán', tamUng: 10000000, soThucMua: 2743000, chenhLech: 7257000 },
  { trangThai: 'Chờ quyết toán', tamUng: 10333000, soThucMua: 16134000, chenhLech: -5801000 },
];
const q = chayQt(QT, [], []);
teq('🔴 bên quyết toán: thanh cũng LUÔN hiện', 'flex', q.qtBatchBar.style.display);
teq('🔴 và cũng có ô Tổng tạm ứng', '23983000đ', q.qtSelTU.textContent);
teq('🔴 ô THỰC CHI ở giữa', '21967000đ', q.qtSelTC.textContent);
teq('thừa/thiếu vẫn cộng như cũ', '2016000đ', q.qtSelTotal.textContent);
t('nhãn nói rõ đang cộng cả bảng chờ', /^3 đơn chờ quyết toán$/.test(q.qtSelInfo.textContent), q.qtSelInfo.textContent);
teq('⚠️ chưa tích thì ẩn nút duyệt hàng loạt', 'none', q.btnQtChon.style.display);
teq('không có đơn chìm thì không nói gì thêm', '', q.qtSelChim.textContent);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 ĐƠN "CHÌM" — đã cấp tiền mà CHƯA gửi quyết toán
 *
 * Anh Thắng 07/09/2026: *"nhớ không cộng vào thực chi, nếu đơn chưa gửi quyết toán thì phần
 * tạm ứng vẫn cộng vào (tức phần thừa chưa chi)"*.
 *
 * Không có phần này thì bảng chờ quyết toán trông rất đẹp — mọi đơn đều khớp — trong khi ngoài
 * đời còn mấy chục triệu treo ở đâu đó không ai nhắc tới.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const CHIM = [
  /* Cố ý để `soThucMua` khác 0: đơn đã cấp tiền thì máy chủ vẫn tính thực chi cho nó. Cộng
     nhầm số ấy vào là con số thực chi phình lên bằng một khoản chưa ai soát. */
  { trangThai: 'Đã cấp tạm ứng', tamUng: 5000000, soThucMua: 4200000, chenhLech: 800000 },
  { trangThai: 'Đã cấp tạm ứng', tamUng: 2000000, soThucMua: 1900000, chenhLech: 100000 },
];
const qc = chayQt(QT, CHIM, []);
teq('🔴 tạm ứng CỘNG cả đơn chìm', '30983000đ', qc.qtSelTU.textContent);
teq('🔴 thực chi KHÔNG cộng đơn chìm', '21967000đ', qc.qtSelTC.textContent);
teq('🔴 thừa/thiếu KHÔNG cộng đơn chìm', '2016000đ', qc.qtSelTotal.textContent);
t('và nói rõ có bao nhiêu đơn chìm', /2 đơn CHƯA gửi quyết toán/.test(qc.qtSelChim.textContent), qc.qtSelChim.textContent);
/* 🔴 ĐẾM ĐÔI LÀ CÁI BẪY CỦA LƯỢT NÀY: đơn chìm nay nằm TRONG `QT_ROWS_CHO`, nên cộng thêm một
   lần nữa từ `QT_ROWS_CHIM` là 7.000.000đ vào tổng hai lượt — sai theo hướng làm mọi thứ trông
   tệ hơn thực tế, kiểu sai khó cãi lại nhất vì không ai muốn tin là mình đang thừa tiền. */
t('🔴 nhãn số đơn chờ KHÔNG kể đơn chìm', /^3 đơn chờ quyết toán$/.test(qc.qtSelInfo.textContent), qc.qtSelInfo.textContent);
teq('🔴 KHÔNG đếm đôi tạm ứng của đơn chìm (không phải 37.983.000đ)', '30983000đ', qc.qtSelTU.textContent);
teq('   thừa/thiếu giữ nguyên con số của phần đã soát', '2016000đ', qc.qtSelTotal.textContent);
t('   kèm số tiền đang treo', /7000000/.test(qc.qtSelChim.textContent), qc.qtSelChim.textContent);
/* ⚠️ Đối chứng: nếu lỡ cộng `soThucMua` của đơn chìm thì thực chi sẽ là 28.067.000đ. */
t('⚠️ KHÔNG phải con số của bản cộng nhầm', qc.qtSelTC.textContent !== '28067000đ', qc.qtSelTC.textContent);

const q2 = chayQt(QT, CHIM, [{ tu: 3650000, tc: 3090000, cl: 560000 }]);
teq('tích 1 đơn: tạm ứng theo đơn ấy', '3650000đ', q2.qtSelTU.textContent);
teq('thực chi theo đơn ấy', '3090000đ', q2.qtSelTC.textContent);
teq('và thừa/thiếu theo đơn ấy', '560000đ', q2.qtSelTotal.textContent);
teq('⚠️ đang tích thì KHÔNG kéo đơn chìm vào', '', q2.qtSelChim.textContent);
teq('nhãn đổi sang "Đã chọn"', 'Đã chọn 1 đơn', q2.qtSelInfo.textContent);
teq('hiện nút duyệt hàng loạt', '', q2.btnQtChon.style.display);

const q3 = chayQt([], [], []);
teq('bảng rỗng: thanh vẫn hiện', 'flex', q3.qtBatchBar.style.display);
t('và nói thẳng là không có đơn nào', /Không có đơn nào/.test(q3.qtSelInfo.textContent), q3.qtSelInfo.textContent);
/* Không còn đơn chờ nào mà vẫn có đơn chìm -> con số treo vẫn phải hiện ra. */
const q4 = chayQt([], CHIM, []);
t('   và nói rõ không có đơn chờ nào', /Không có đơn nào chờ quyết toán/.test(q4.qtSelInfo.textContent), q4.qtSelInfo.textContent);
teq('🔴 hết đơn chờ nhưng còn đơn chìm: tạm ứng vẫn hiện', '7000000đ', q4.qtSelTU.textContent);
teq('   thực chi bằng 0', '0đ', q4.qtSelTC.textContent);
teq('   và thừa/thiếu bằng 0 (chưa soát đơn nào)', '0đ', q4.qtSelTotal.textContent);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. HAI THANH KHÔNG CÒN ẨN SẴN TRONG MÃ TRANG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const HTML_MA = HTML.replace(/<!--[\s\S]*?-->/g, ' ');
[['duyetBatchBar', 'Duyệt tạm ứng'], ['qtBatchBar', 'Quyết toán']].forEach(function (x) {
  const m = HTML_MA.match(new RegExp('<div id="' + x[0] + '" style="([^"]*)"'));
  t('tìm được thanh ' + x[1], !!m, x[0]);
  if (m) {
    t('🔴 thanh ' + x[1] + ' KHÔNG còn display:none lúc dựng trang',
      m[1].indexOf('display:none') < 0, m[1]);
  }
});
/* 🔴 CỘNG CẢ NHÓM, KHÔNG CHỈ TRANG ĐANG XEM. Bảng quyết toán phân trang 15 đơn — cộng theo ô
   tích trên màn thì con số tụt mỗi khi sang trang, mà người đọc không có cách nào biết. */
t('🔴 bảng quyết toán giữ CẢ NHÓM để cộng', HTML.indexOf("if(khoa==='cho') QT_ROWS_CHO=rows;") > 0);
t('🔴 và bảng đơn chìm cũng được giữ lại', HTML.indexOf('QT_ROWS_CHIM=rows;') > 0);
t('   lấy từ bảng "Đã cấp tạm ứng — chưa nộp hóa đơn"',
  /rows=\(BOOT\.dons\|\|\[\]\)\.filter\(function\(d\)\{ return d\.trangThai==='Đã cấp tạm ứng'/.test(HTML));
t('   và `rows` là cả nhóm, không phải trang đang xem (`lat`)',
  HTML.indexOf('QT_ROWS_CHO=lat') < 0);

/* ⚠️ HAI CHỖ BỆ ĐỠ KHÔNG VỚI TỚI, PHẢI SOI MÃ.
   Bài này gọi thẳng `dvUpdateBar()` / `qtUpdateBar()` với danh sách và ô tích do chính nó
   dựng, nên nó KHÔNG chạy qua `renderDuyet()` (nơi nạp danh sách) lẫn `_qtRowHtml()` (nơi
   dựng ô tích). Đục thủng hai chỗ ấy thì mọi phép trên vẫn xanh — phá thử 07/09/2026 xác
   nhận. Canh bằng mã, và canh ĐIỀU KIỆN DỰNG chứ không phải chuỗi có mặt. */
const mDvList = HTML_MA.match(/DV_LIST=([A-Za-z_$][\w$]*)\s*;/);
t('🔴 renderDuyet có nạp danh sách đang hiện vào DV_LIST', !!mDvList, 'DV_LIST=');
if (mDvList) {
  teq('   và nạp đúng danh sách sau lọc (`list`), không phải mảng rỗng', 'list', mDvList[1]);
}
const mQtChk = HTML_MA.match(/<input type="checkbox" class="qtChk"[^>]*/);
t('bốc được ô tích của bảng quyết toán', !!mQtChk, 'qtChk');
if (mQtChk) {
  t('🔴 ô tích mang cả số tạm ứng của dòng', mQtChk[0].indexOf('data-tu=') > 0, mQtChk[0]);
  t('   và mang cả thực chi', mQtChk[0].indexOf('data-tc=') > 0, mQtChk[0]);
  t('   và mang cả thừa/thiếu', mQtChk[0].indexOf('data-cl=') > 0, mQtChk[0]);
  t('   lấy từ chính dòng ấy, không phải số cứng',
    /data-tu="'\+\(Number\(d\.tamUng\)/.test(mQtChk[0]), mQtChk[0]);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3b. DÒNG "CHÌM" NẰM NGAY TRONG BẢNG CHỜ QUYẾT TOÁN
 *
 * Anh Thắng 07/09/2026: *"hiện thêm các đơn chưa quyết toán chung 1 tuần, nhưng dạng ẩn chìm
 * và chỉ lấy số tạm ứng nếu đơn đó chưa quyết toán"*.
 *
 * Trước đây chúng chỉ có ở một bảng riêng tận cuối trang, nên soát một tuần là phải nhớ cuộn
 * xuống đối chiếu — mà thứ dễ quên nhất lại đúng là mấy đơn đang treo tiền.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnRow = bocHam('_qtRowHtml');
t('bốc được _qtRowHtml()', fnRow.length > 500, fnRow.length);
function veHang(d) {
  return new Function('d', 'canBatch', 'COLS', 'gcls', 'collapsed', 'esc', 'money', 'canDo', 'stCls',
    fnLaChim + '\n' + fnRow + '\nreturn _qtRowHtml(d, canBatch, COLS, gcls, collapsed);')(
    d, true, 10, '', false,
    function (v) { return String(v == null ? '' : v); },
    function (n) { return String(n); },
    function () { return true; },
    function () { return 'st-cho'; });
}
const hChim = veHang({ trangThai: 'Đã cấp tạm ứng', maDon: 'D_CHIM', ky: 'T9/2026', coso: 'A',
  tamUng: 5000000, soThucMua: 4200000, chenhLech: 800000 });
const hCho = veHang({ trangThai: 'Chờ quyết toán', maDon: 'D_CHO', ky: 'T9/2026', coso: 'A',
  tamUng: 3650000, soThucMua: 3090000, chenhLech: 560000 });

t('🔴 dòng chìm mang lớp riêng để nhạt đi', /class="qm[^"]*qt-chim/.test(hChim), hChim.slice(0, 80));
t('   dòng thường thì không', !/qt-chim/.test(hCho), hCho.slice(0, 80));
t('🔴 dòng chìm VẪN hiện số tạm ứng', hChim.indexOf('5000000') > 0, hChim);
/* 🔴 "—" CHỨ KHÔNG PHẢI SỐ 0: số 0 đọc như "đã soát, không chi đồng nào", còn "—" nói đúng
   chuyện đang xảy ra — chưa có gì để nói. */
t('🔴 nhưng thực chi để dấu "—", không phải số', hChim.indexOf('4200000') < 0, hChim);
t('   và thừa/thiếu cũng "—"', hChim.indexOf('800000') < 0, hChim);
teq('   đúng hai dấu "—"', 2, (hChim.match(/—/g) || []).length);
t('⚠️ dòng thường vẫn hiện đủ ba số', hCho.indexOf('3650000') > 0 && hCho.indexOf('3090000') > 0 && hCho.indexOf('560000') > 0, hCho);
/* Chìm thì không tích được (chưa gửi quyết toán thì chẳng có gì để duyệt), và không có nút. */
t('🔴 dòng chìm KHÔNG có ô tích', hChim.indexOf('class="qtChk"') < 0, hChim);
t('   và KHÔNG có nút duyệt', hChim.indexOf('qtInlineXacNhan') < 0 && hChim.indexOf('qtInlineNCC') < 0, hChim);
/* 🔴 CA DUY NHẤT LÀM NÚT MỌC RA: đơn chìm CÓ phần nhà cung cấp chưa duyệt. Không có ca này thì
   bỏ hẳn chốt "chìm thì không nút" đi bài vẫn xanh — nhánh kia tự false vì thiếu dữ liệu. */
const hChimNCC = veHang({ trangThai: 'Đã cấp tạm ứng', maDon: 'D_CHIM2', ky: 'T9/2026', coso: 'A',
  tamUng: 5000000, soThucMua: 4200000, chenhLech: 800000, thucChiNCC: 900000, qtNCC: false });
t('🔴 đơn chìm CÓ phần NCC chưa duyệt: vẫn KHÔNG mọc nút duyệt',
  hChimNCC.indexOf('qtInlineNCC') < 0, hChimNCC);
/* Đối chứng: đơn ĐÃ gửi quyết toán mà còn phần NCC thì nút ấy PHẢI có — nếu không thì phép
   trên xanh vì nút chẳng bao giờ mọc cho ai cả. */
const hChoNCC = veHang({ trangThai: 'Chờ quyết toán', maDon: 'D_CHO2', ky: 'T9/2026', coso: 'A',
  tamUng: 3650000, soThucMua: 3090000, chenhLech: 560000, thucChiNCC: 900000, qtNCC: false, qtCN: true });
t('⚠️ đối chứng: đơn đã gửi QT còn phần NCC thì CÓ nút duyệt NCC',
  hChoNCC.indexOf('qtInlineNCC') > 0, hChoNCC);
t('⚠️ nhưng vẫn mở được chi tiết', hChim.indexOf('viewDon(') > 0, hChim);
t('dòng thường thì tích được', hCho.indexOf('class="qtChk"') > 0, hCho);

/* --- `_qtSums` là nơi DUY NHẤT biết luật đơn chìm --- */
function sums(rows) {
  return new Function('rows', fnLaChim + '\n' + fnSums + '\nreturn _qtSums(rows);')(rows);
}
const sChim = sums([{ trangThai: 'Đã cấp tạm ứng', tamUng: 5000000, soThucMua: 4200000, chenhLech: 800000 }]);
teq('🔴 _qtSums: đơn chìm cộng tạm ứng', 5000000, sChim.tu);
teq('🔴 _qtSums: KHÔNG cộng thực chi', 0, sChim.mua);
teq('🔴 _qtSums: thừa/thiếu KHÔNG cộng (chưa soát thì chưa có thừa thiếu)', 0, sChim.cl);
const sCho = sums([{ trangThai: 'Chờ quyết toán', tamUng: 3650000, soThucMua: 3090000, chenhLech: 560000 }]);
teq('⚠️ đơn thường vẫn cộng như cũ (tạm ứng)', 3650000, sCho.tu);
teq('   (thực chi)', 3090000, sCho.mua);
teq('   (thừa/thiếu)', 560000, sCho.cl);

/* --- Bảng chờ thật sự có kéo đơn chìm vào --- */
const HTML_MA3 = HTML.replace(/<!--[\s\S]*?-->/g, ' ');
t('🔴 bảng chờ nối thêm đơn "Đã cấp tạm ứng"',
  /\.concat\(\(BOOT\.dons\|\|\[\]\)\.filter\(function\(d\)\{ return d\.trangThai==='Đã cấp tạm ứng' && _qtLoc\(d\); \}\)\)/.test(HTML_MA3),
  'concat');
t('lớp .qt-chim có kiểu chữ thật trong css', /tr\.qt-chim > td\{/.test(CSS_QT), 'css');

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. BẢNG "ĐÃ QUYẾT TOÁN" CÓ Ô LỌC TUẦN RIÊNG — anh Thắng 07/09/2026
 *
 * *"Bổ sung phần đã quyết toán lọc theo tuần (tránh lọc trùng với chờ quyết toán)"*.
 *
 * Ô lọc chung ở đầu trang áp cho CẢ BA bảng — mà hai bảng cần hai khoảng thời gian khác nhau:
 * "Chờ quyết toán" là việc của tuần này, "Đã quyết toán" là tra lại tuần trước. Chọn một tuần
 * ở ô chung là bảng kia rỗng theo.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnLoc = bocHam('_qtLoc');
const fnLocXong = bocHam('_qtLocXong');
t('bốc được _qtLoc()', fnLoc.length > 100, fnLoc.length);
t('bốc được _qtLocXong()', fnLocXong.length > 100, fnLocXong.length);

/* Bệ đỡ: bốn ô lọc, mỗi ô một giá trị. */
function locXong(d, oChung, oRieng) {
  const o = {
    qtThang: { value: oChung.thang || '' },
    qtKy: { value: oChung.ky || '' },
    qtCoso: { value: oChung.coso || '' },
    qtKyXong: { value: oRieng || '' },
  };
  const f = new Function('el', '_thangCuaKy',
    fnLoc + '\n' + fnLocXong + '\nreturn _qtLocXong;')(
    function (id) { return o[id] || null; },
    function (k) { const m = /^T(\d+)\/(\d+)/.exec(String(k || '')); return m ? (m[2] + '-' + m[1]) : ''; });
  return f(d);
}

const D_T8_17 = { ky: 'T8/2026 (17/8-23/8/2026)', coso: 'FARM PHAN THIẾT' };
const D_T8_24 = { ky: 'T8/2026 (24/8-30/8/2026)', coso: 'FUNZONE VŨNG TÀU' };

/* --- Ô riêng BỎ TRỐNG: y hệt lọc chung, không đổi gì cho ai không dùng tới --- */
teq('ô riêng trống: theo lọc chung (khớp)', true, locXong(D_T8_17, { ky: 'T8/2026 (17/8-23/8/2026)' }, ''));
teq('ô riêng trống: theo lọc chung (không khớp)', false, locXong(D_T8_24, { ky: 'T8/2026 (17/8-23/8/2026)' }, ''));
teq('ô riêng trống, lọc chung rỗng: nhận hết', true, locXong(D_T8_24, {}, ''));

/* --- 🔴 Ô RIÊNG ĐÈ Ô CHUNG: đây là cả điểm của tính năng --- */
teq('🔴 ô riêng ĐÈ ô chung: chọn tuần 24/8 dù ô chung đang là 17/8',
  true, locXong(D_T8_24, { ky: 'T8/2026 (17/8-23/8/2026)' }, 'T8/2026 (24/8-30/8/2026)'));
teq('   và đơn tuần khác bị loại',
  false, locXong(D_T8_17, { ky: 'T8/2026 (17/8-23/8/2026)' }, 'T8/2026 (24/8-30/8/2026)'));
/* ⚠️ Ô THÁNG chung KHÔNG được đè ngược lại tuần riêng — chọn tuần T8 mà ô tháng chung đang để
   T9 thì bảng rỗng, và người dùng không hiểu vì sao ô tuần mình vừa chọn lại không ra gì. */
teq('⚠️ ô THÁNG chung không cắt mất tuần riêng',
  true, locXong(D_T8_24, { thang: '2026-9' }, 'T8/2026 (24/8-30/8/2026)'));
/* Nhưng ô CƠ SỞ chung thì vẫn áp — lọc cơ sở là việc chung của cả màn, không phải chuyện tuần. */
teq('⚠️ nhưng ô CƠ SỞ chung vẫn áp',
  false, locXong(D_T8_24, { coso: 'FARM PHAN THIẾT' }, 'T8/2026 (24/8-30/8/2026)'));
teq('   và khớp cơ sở thì nhận',
  true, locXong(D_T8_24, { coso: 'FUNZONE VŨNG TÀU' }, 'T8/2026 (24/8-30/8/2026)'));

/* --- Ô CHỌN TUẦN RIÊNG dựng từ đâu --- */
const fnNapKy = bocHam('_napKyRieng');
t('bốc được _napKyRieng()', fnNapKy.length > 200, fnNapKy.length);
function napKy(ds, cu) {
  const o = { qtKyXong: { value: cu || '', innerHTML: '' } };
  new Function('el', 'esc', '_kyVal', fnNapKy + '\n_napKyRieng("qtKyXong", ' + JSON.stringify(ds) + ');')(
    function (id) { return o[id] || null; },
    function (v) { return String(v == null ? '' : v); },
    function (k) { const m = /\((\d+)\/(\d+)-/.exec(String(k || '')); return m ? (+m[2] * 100 + +m[1]) : -1; });
  return o.qtKyXong;
}
const nk = napKy([D_T8_17, D_T8_24, { ky: 'T8/2026 (24/8-30/8/2026)' }], '');
t('ô có lựa chọn "theo lọc chung"', /theo lọc chung/.test(nk.innerHTML), nk.innerHTML);
teq('⚠️ tuần trùng chỉ hiện MỘT lần', 2, (nk.innerHTML.match(/<option value="T8/g) || []).length);
t('tuần mới nhất lên trước', nk.innerHTML.indexOf('24/8-30/8') < nk.innerHTML.indexOf('17/8-23/8'), nk.innerHTML);
/* 🔴 GIỮ LỰA CHỌN CŨ khi vẽ lại. Bảng này vẽ lại mỗi lần tải; ô lọc tự nhảy về "mọi tuần" thì
   người đang đối chiếu mất chỗ đứng sau mỗi thao tác. */
const nk2 = napKy([D_T8_17, D_T8_24], 'T8/2026 (17/8-23/8/2026)');
teq('🔴 vẽ lại vẫn giữ tuần đang chọn', 'T8/2026 (17/8-23/8/2026)', nk2.value);
/* Nhưng tuần không còn trong danh sách thì phải nhả ra, không giữ một lựa chọn chết. */
const nk3 = napKy([D_T8_24], 'T8/2026 (17/8-23/8/2026)');
teq('⚠️ tuần không còn đơn nào thì nhả lựa chọn', '', nk3.value);

/* --- Ô ấy dựng từ CHÍNH đơn đã quyết toán, không phải mọi đơn của màn --- */
const HTML_MA2 = HTML.replace(/<!--[\s\S]*?-->/g, ' ');
t('🔴 ô tuần riêng nạp từ đơn ĐÃ quyết toán',
  /_napKyRieng\('qtKyXong',[^;]*trangThai==='Đã quyết toán'/.test(HTML_MA2), 'napKyRieng');
/* 🔴 VÀ BẢNG "ĐÃ QUYẾT TOÁN" PHẢI DỰNG LẠI TỪ ĐẦU, không lọc tiếp từ danh sách đã bị ô chung
   cắt — lọc tiếp thì tuần riêng không bao giờ với tới được mấy tuần ô chung đã loại. */
t('🔴 bảng đã quyết toán dựng lại từ BOOT.dons, không lọc tiếp từ `all`',
  /var xong=\(BOOT\.dons\|\|\[\]\)\.filter\(function\(d\)\{ return d\.trangThai==='Đã quyết toán' && _qtLocXong\(d\); \}\)/.test(HTML_MA2),
  'xong=');
t('nút bỏ lọc riêng có thật', HTML_MA2.indexOf('qtXoaLocXong()') > 0);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. Ô LỌC TUẦN CHUNG — TUẦN NÀY + 4 TUẦN TRƯỚC (anh Thắng 07/09/2026)
 *
 * *"lọc theo tuần hiện tại và 4 tuần phía sau, kèm lọc theo tháng để tra hết các đơn"*.
 *
 * Trước đây ô này chỉ liệt kê tuần CÓ ĐƠN, nên đầu tuần — lúc chưa ai lập đơn — nó rỗng hoặc
 * chỉ còn mỗi tuần cũ (đúng ảnh anh gửi: cả ô chỉ có một tuần). Kế toán thì cần chọn đúng tuần
 * đang chạy để soát.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnNapLoc = bocHam('_napLocDon');
const fnTuanGan = bocHam('_tuanGanDay');
t('bốc được _napLocDon()', fnNapLoc.length > 500, fnNapLoc.length);
t('bốc được _tuanGanDay()', fnTuanGan.length > 100, fnTuanGan.length);
t('bốc được _mondayOf() và _kyRange() (hàm một dòng)',
  bocDong('_mondayOf').length > 60 && bocDong('_kyRange').length > 60,
  [bocDong('_mondayOf').length, bocDong('_kyRange').length]);

/* Bệ đỡ: ba ô chọn, mỗi ô nhớ innerHTML + value. Ngày "hôm nay" do bài kiểm ghim, nên kết quả
   không trôi theo lịch thật — bài kiểm phụ thuộc ngày chạy là bài kiểm tự đỏ sau vài tháng. */
function napLoc(dons, thangDangChon, kyDangChon) {
  const o = {
    fThang: { value: thangDangChon || '', innerHTML: '' },
    fKy: { value: kyDangChon || '', innerHTML: '' },
    fCoso: { value: '', innerHTML: '' },
  };
  /* Bốc luôn `_mondayOf` và `_kyRange` từ mã thật — chép tay hai hàm ấy sang đây là dựng bản
     sao, rồi bản sao xanh mãi kể cả khi khuôn nhãn kỳ bên kia đã đổi. */
  const src = 'var _HOM_NAY=new Date(2026,8,10);\n'          // Thứ Năm 10/09/2026
    + bocDong('_mondayOf') + '\n' + bocDong('_kyRange') + '\n'
    + fnTuanGan.replace('new Date()', '_HOM_NAY') + '\n' + fnNapLoc
    + '\n_napLocDon(DONS, "fThang", "fKy", "fCoso");';
  new Function('el', 'esc', '_kyVal', '_thangCuaKy', 'DONS', src)(
    function (id) { return o[id] || null; },
    function (v) { return String(v == null ? '' : v); },
    function (k) {
      const m = /\((\d+)\/(\d+)-(\d+)\/(\d+)\/(\d+)\)/.exec(String(k || ''));
      if (m) return (+m[5]) * 10000 + (+m[2]) * 100 + (+m[1]);
      const mt = /^(\d+)-(\d+)$/.exec(String(k || ''));
      return mt ? (+mt[1]) * 10000 + (+mt[2]) * 100 : -1;
    },
    function (k) {
      const m = /^T(\d+)\/(\d+)/.exec(String(k || ''));
      return m ? (m[2] + '-' + m[1]) : '';
    },
    dons);
  return o;
}
function cacTuan(html) {
  return (html.match(/<option value="(T[^"]*)"/g) || []).map(function (x) { return x.slice(15, -1); });
}

/* Tuần chứa 10/09/2026 là 7/9-13/9; bốn tuần trước là 31/8, 24/8, 17/8, 10/8. */
const D_T9 = { ky: 'T9/2026 (7/9-13/9/2026)', coso: 'A' };
const D_T7_CU = { ky: 'T7/2026 (6/7-12/7/2026)', coso: 'B' };

const n1 = napLoc([D_T9, D_T7_CU], '', '');
const t1 = cacTuan(n1.fKy.innerHTML);
teq('🔴 chưa chọn tháng: đúng 5 tuần', 5, t1.length);
teq('   tuần này lên đầu', 'T9/2026 (7/9-13/9/2026)', t1[0]);
teq('   và bốn tuần trước nó', 'T8/2026 (10/8-16/8/2026)', t1[4]);
/* ⚠️ Tuần bắc hai tháng mang nhãn của tháng NGÀY CUỐI — 31/8-6/9 là "T9/2026", không phải
   "T8/2026". Đó là luật của `_kyRange`, và bài kiểm phải theo nó chứ không theo trực giác. */
t('🔴 tuần chưa có đơn nào VẪN được bày (đầu tuần vẫn chọn được tuần đang chạy)',
  t1.indexOf('T9/2026 (31/8-6/9/2026)') >= 0, t1);
t('🔴 tuần cũ hơn khoảng thì KHÔNG bày (để dành cho ô tháng)',
  t1.indexOf('T7/2026 (6/7-12/7/2026)') < 0, t1);

/* --- CHỌN THÁNG -> bày hết tuần CÓ ĐƠN của tháng ấy: đường "tra hết các đơn" --- */
const n2 = napLoc([D_T9, D_T7_CU], '2026-7', '');
const t2 = cacTuan(n2.fKy.innerHTML);
teq('🔴 chọn tháng T7: bày tuần của tháng ấy', 1, t2.length);
teq('   đúng tuần có đơn', 'T7/2026 (6/7-12/7/2026)', t2[0]);
t('⚠️ và KHÔNG kéo 5 tuần gần đây vào', t2.indexOf('T9/2026 (7/9-13/9/2026)') < 0, t2);

/* --- ĐƠN Ở TUẦN XA HƠN VỀ PHÍA TRƯỚC vẫn giữ (lập trước cho tuần sau) --- */
const n3 = napLoc([{ ky: 'T9/2026 (21/9-27/9/2026)', coso: 'A' }], '', '');
t('⚠️ đơn của tuần chưa tới vẫn có mặt', cacTuan(n3.fKy.innerHTML).indexOf('T9/2026 (21/9-27/9/2026)') >= 0,
  cacTuan(n3.fKy.innerHTML));

/* --- 🔴 TUẦN ĐANG CHỌN KHÔNG BỊ RƠI khi vẽ lại --- */
const n4 = napLoc([D_T7_CU], '', 'T7/2026 (6/7-12/7/2026)');
teq('🔴 tuần đang chọn nằm ngoài khoảng vẫn giữ được', 'T7/2026 (6/7-12/7/2026)', n4.fKy.value);
t('   và có trong danh sách', cacTuan(n4.fKy.innerHTML).indexOf('T7/2026 (6/7-12/7/2026)') >= 0,
  cacTuan(n4.fKy.innerHTML));

/* --- Ô THÁNG vẫn bày mọi tháng có đơn, không bị cắt theo 5 tuần --- */
const thangs = (n1.fThang.innerHTML.match(/<option value="(\d{4}-\d+)"/g) || []);
teq('⚠️ ô tháng vẫn bày đủ tháng có đơn (T9 và T7)', 2, thangs.length);

if (TRUOT.length) {
  console.log('\n=== THANH TỔNG LUÔN HIỆN ===');
  TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
  console.log('ĐẠT: ' + DAT + '   TRƯỢT: ' + TRUOT.length);
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hai thanh tổng luôn hiện, chưa tích thì cộng cả bảng.');
