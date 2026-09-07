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

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

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

const IDS_QT = ['qtBatchBar', 'qtSelInfo', 'qtSelTU', 'qtSelTotal', 'btnQtChon'];
function chayQt(rows, tich) {
  const o = dungDom(IDS_QT);
  const f = new Function('el', 'QT_ROWS_CHO', 'qtSelected', 'money', fnQt + '\nqtUpdateBar();');
  f(function (id) { return o[id] || null; }, rows, function () { return oTich(tich); },
    function (n) { return String(n); });
  return o;
}

const QT = [
  { tamUng: 3780000, chenhLech: 120000 },
  { tamUng: 4150000, chenhLech: -80000 },
  { tamUng: 8149000, chenhLech: 0 },
];
const q = chayQt(QT, []);
teq('🔴 bên quyết toán: thanh cũng LUÔN hiện', 'flex', q.qtBatchBar.style.display);
teq('🔴 và cũng có ô Tổng tạm ứng', '16079000đ', q.qtSelTU.textContent);
teq('thừa/thiếu vẫn cộng như cũ', '40000đ', q.qtSelTotal.textContent);
t('nhãn nói rõ đang cộng cả bảng chờ', /3 đơn chờ quyết toán/.test(q.qtSelInfo.textContent), q.qtSelInfo.textContent);
teq('⚠️ chưa tích thì ẩn nút duyệt hàng loạt', 'none', q.btnQtChon.style.display);

const q2 = chayQt(QT, [{ tu: 4150000, cl: -80000 }]);
teq('tích 1 đơn: tạm ứng theo đơn ấy', '4150000đ', q2.qtSelTU.textContent);
teq('và thừa/thiếu theo đơn ấy', '-80000đ', q2.qtSelTotal.textContent);
teq('nhãn đổi sang "Đã chọn"', 'Đã chọn 1 đơn', q2.qtSelInfo.textContent);
teq('hiện nút duyệt hàng loạt', '', q2.btnQtChon.style.display);

const q3 = chayQt([], []);
teq('bảng rỗng: thanh vẫn hiện', 'flex', q3.qtBatchBar.style.display);
t('và nói thẳng là không có đơn nào', /Không có đơn nào/.test(q3.qtSelInfo.textContent), q3.qtSelInfo.textContent);

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
  t('   và mang cả thừa/thiếu', mQtChk[0].indexOf('data-cl=') > 0, mQtChk[0]);
  t('   lấy từ chính dòng ấy, không phải số cứng',
    /data-tu="'\+\(Number\(d\.tamUng\)/.test(mQtChk[0]), mQtChk[0]);
}

if (TRUOT.length) {
  console.log('\n=== THANH TỔNG LUÔN HIỆN ===');
  TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
  console.log('ĐẠT: ' + DAT + '   TRƯỢT: ' + TRUOT.length);
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hai thanh tổng luôn hiện, chưa tích thì cộng cả bảng.');
