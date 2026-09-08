/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HÀNG ĐỢI QUYẾT TOÁN XẾP THEO LƯỢT GỬI — anh Thắng 08/09/2026
 *
 * *"Cho anh sắp xếp đơn ai gửi quyết toán lên trước sẽ hiện phía trên."*
 *
 * Đây là HÀNG ĐỢI của kế toán, nên phải theo thứ tự đến: ai nộp sớm được xử sớm. Xếp theo KỲ
 * như trước thì mọi đơn cùng tuần nằm lẫn lộn — người nộp từ thứ hai và người nộp chiều chủ
 * nhật đứng ngang nhau, và cái nào lên trên là do may.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY. Soi chuỗi chỉ nói được "có gọi sort"; nó không nói được thứ tự ra
 *    có đúng không — mà đó là toàn bộ nội dung của yêu cầu này.
 *
 * Chạy: node tools/test/kiem-xep-hang-quyet-toan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DON  = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');
const DB   = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-db.php', 'utf8');

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

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. CÓ CHỖ ĐỂ GHI MỐC, VÀ CÓ GHI THẬT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 bảng đơn có cột ngay_gui_qt', /ngay_gui_qt DATETIME NULL/.test(DB), null);
/* ⚠️ Thêm cột mà quên nâng SCHEMA_VERSION thì `dbDelta()` không chạy, cột không sinh ra, và
   mọi lượt ghi vào nó im lặng trôi đi. Canh NGƯỠNG, không ghim một số. */
(function () {
  const m = /SCHEMA_VERSION = '(\d+)\.(\d+)\.(\d+)'/.exec(DB);
  const du = !!m && (Number(m[1]) * 10000 + Number(m[2]) * 100 + Number(m[3])) >= 11000;
  t('🔴 và SCHEMA_VERSION từ 1.10.0 trở lên để lượt nâng cấp nổ ra', du, m ? m[0] : 'không thấy');
})();

(function () {
  const i = DON.indexOf('function gui_quyet_toan');
  const than = i < 0 ? '' : DON.slice(i, i + 2000);
  t('bốc được gui_quyet_toan', than.length > 200, than.length);
  t('🔴 lượt GỬI có ghi mốc ngay_gui_qt', /'ngay_gui_qt'\s*=>/.test(than), null);
  t('và vẫn đổi trạng thái sang Chờ quyết toán', /'trang_thai'\s*=>\s*'Chờ quyết toán'/.test(than), null);
  /* 🔴 ĐỪNG DÙNG LẪN VỚI `ngay_qt`. Cột đó là mốc KẾ TOÁN xác nhận xong, đứng ở cuối chặng;
     ghi nhầm vào đó là đơn vừa gửi đã trông như đã quyết toán. */
  t('⚠️ KHÔNG đụng nhầm sang ngay_qt', !/'ngay_qt'\s*=>/.test(than), than.slice(0, 300));
})();

/* Mốc phải được trả xuống giao diện, không thì bảng chẳng có gì để xếp. */
t('🔴 list_dons trả mốc guiQTAt xuống giao diện', /'guiQTAt'\s*=>\s*self::xep_gui_qt_/.test(DON), null);
/* Và nguồn lui phải đúng thứ tự — đơn cũ không có mốc gửi thì lấy ngày cấp tiền, rồi ngày tạo. */
(function () {
  const m = /array\(\s*'ngay_gui_qt',\s*'ngay_cap',\s*'ngay_tao'\s*\)/.exec(DON);
  t('🔴 nguồn lui đúng thứ tự: gửi -> cấp tiền -> tạo', !!m, null);
})();

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. CHẠY THẬT PHÉP XẾP — bốc nguyên lời gọi `cho.sort(...)` từ renderQTList()
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnRender = bocHam('renderQTList');
t('bốc được renderQTList()', fnRender.length > 1000, fnRender.length);

const dongSort = (function () {
  const i = fnRender.indexOf('cho.sort(function(a,b){');
  if (i < 0) return '';
  const j = fnRender.indexOf('\n      });', i);
  return (j > i) ? fnRender.slice(i, j + 9) : '';
})();
t('bốc được phép xếp của bảng Chờ quyết toán', dongSort.length > 80, dongSort);

const fnChim = bocDong('_laChim');
const fnKyVal = bocHam('_kyVal') || bocDong('_kyVal');
t('bốc được _laChim()', fnChim.length > 20, fnChim);
t('bốc được _kyVal()', fnKyVal.length > 20, fnKyVal.length);

function xep(ds) {
  const f = new Function('cho', '_laChim', '_kyVal', fnKyVal + '\n' + fnChim + '\n' + dongSort + '\nreturn cho;');
  return f(ds.slice(), null, null).map(function (d) { return d.maDon; });
}
const KY = 'T9/2026 (7/9-13/9/2026)';
function don(ma, gui, ky, tt) {
  return { maDon: ma, guiQTAt: gui, ky: ky || KY, trangThai: tt || 'Chờ quyết toán' };
}

/* 🔴 PHÉP CHÍNH: cùng một tuần, ai gửi trước lên trên. */
teq('🔴 cùng tuần thì ai gửi TRƯỚC lên trên',
  ['SOM', 'GIUA', 'MUON'],
  xep([ don('MUON', 3000), don('SOM', 1000), don('GIUA', 2000) ]));

/* Khác tuần cũng vẫn theo lượt gửi — hàng đợi là hàng đợi, không phải lịch. */
teq('🔴 khác tuần vẫn theo lượt gửi, không theo kỳ',
  ['SOM', 'MUON'],
  xep([ don('MUON', 5000, 'T8/2026 (24/8-30/8/2026)'), don('SOM', 1000, 'T9/2026 (7/9-13/9/2026)') ]));

/* Đơn CHƯA gửi (chìm) xuống dưới — chưa gửi thì chưa vào hàng. */
teq('🔴 đơn CHƯA gửi quyết toán xuống dưới cùng',
  ['A', 'B', 'CHIM'],
  xep([ don('CHIM', 0, KY, 'Đã cấp tạm ứng'), don('B', 2000), don('A', 1000) ]));
/* Kể cả khi đơn chìm có mốc lớn hơn — trạng thái thắng con số. */
teq('đơn chìm xuống dưới kể cả khi mốc của nó sớm hơn',
  ['A', 'CHIM'],
  xep([ don('CHIM', 1, KY, 'Đã cấp tạm ứng'), don('A', 9999) ]));

/* Đơn CŨ không có mốc nào -> xuống sau đơn có mốc, và trong nhóm ấy xếp theo kỳ cũ trước. */
teq('🔴 đơn không có mốc thì đứng sau đơn có mốc',
  ['CO', 'KHONG'],
  xep([ don('KHONG', 0), don('CO', 5000) ]));
teq('và trong nhóm không mốc thì kỳ CŨ nhất trước',
  ['CU', 'MOI'],
  xep([ don('MOI', 0, 'T9/2026 (7/9-13/9/2026)'), don('CU', 0, 'T8/2026 (24/8-30/8/2026)') ]));

/* Trộn đủ bốn nhóm một lượt — đây là bảng thật của anh Thắng trông thế nào. */
teq('🔴 trộn đủ: có mốc (sớm→muộn) · không mốc (kỳ cũ→mới) · chìm',
  ['G1', 'G2', 'K_CU', 'K_MOI', 'CHIM'],
  xep([
    don('CHIM', 0, KY, 'Đã cấp tạm ứng'),
    don('K_MOI', 0, 'T9/2026 (7/9-13/9/2026)'),
    don('G2', 2000),
    don('K_CU', 0, 'T8/2026 (24/8-30/8/2026)'),
    don('G1', 1000),
  ]));

/* ⚠️ Hai đơn gửi CÙNG một giây thì không được đảo lung tung giữa hai lượt vẽ — người ta đang
   đọc bảng mà dòng nhảy chỗ là mất tin. Lui về kỳ, và kỳ bằng nhau thì giữ thứ tự vào. */
teq('⚠️ cùng mốc gửi thì thứ tự ổn định, không nhảy',
  ['X', 'Y'],
  xep([ don('X', 1000), don('Y', 1000) ]));

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hàng đợi quyết toán xếp theo lượt gửi, ai nộp trước lên trên.');
