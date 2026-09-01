/**
 * Kiểm CHUỖI KỲ của đơn chi phí — BỐC THẲNG TỪ app.html, KHÔNG CHÉP LẠI.
 *
 * Anh Thắng 25/08/2026: nhân viên Văn phòng lên dự án thì đơn theo lịch linh động, thời gian
 * bất kỳ — gom theo tuần chỉ đúng với nhân viên cơ sở. Nghĩa là kỳ không còn luôn luôn là một
 * tuần, mà có thể là một khoảng ngày người ta tự chọn.
 *
 * Anh Thắng 01/09/2026: *"giờ luật tạo cho đơn mới theo tuần liên tục, không tạo theo tháng
 * nữa"*. Tuần nay là thứ 2 → chủ nhật, đủ 7 ngày, KHÔNG cắt ở cuối tháng.
 *
 * 🔴 VÌ SAO PHẢI KIỂM: chuỗi kỳ chỉ mang MỘT năm, nằm ở cuối. Mọi báo cáo xếp đơn theo _kyVal()
 * đọc ngược từ chuỗi đó ra. Đợt vắt năm mà đọc sai là đơn nhảy sang năm khác trong báo cáo, và
 * không có gì báo. Trước đây đơn tuần không bao giờ chạm ca này vì nó bị cắt ở cuối tháng; bỏ
 * cắt tháng ra thì tuần cũng vắt tháng, và mỗi năm có một tuần vắt NĂM.
 *
 * 🔴 VÌ SAO BỐC TỪ TỆP NGUỒN: bản trước của bài này giữ một BẢN CHÉP của `_kyRange` ngay trong
 * đây. Đổi luật nhãn tháng ở app.html xong chạy lại, bài vẫn xanh — vì nó đang chấm bản chép
 * của chính nó. Bản chép không bao giờ nói cho ta biết bản thật đã đổi.
 *
 * Chạy: node tools/test/kiem-ky-don.js
 */
const fs = require('fs');
const path = require('path');
const APP = fs.readFileSync(
  path.join(__dirname, '..', '..', 'wordpress', 'vhcp-chi-phi', 'templates', 'app.html'), 'utf8');

/** Bốc nguyên văn một hàm JS từ app.html theo tên. */
function boc(ten) {
  const i = APP.indexOf('function ' + ten + '(');
  if (i < 0) { throw new Error('Không thấy hàm ' + ten + ' trong app.html'); }
  let d = 0, j = APP.indexOf('{', i), k = j;
  for (; k < APP.length; k++) {
    if (APP[k] === '{') { d++; }
    else if (APP[k] === '}') { d--; if (d === 0) { k++; break; } }
  }
  return APP.slice(i, k);
}

const src = [ '_kyRange', '_kyVal', '_mondayOf', 'genKyOptions' ].map(boc).join('\n');
const F = {};
new Function('el', 'ho', src + '\nho._kyRange=_kyRange;ho._kyVal=_kyVal;ho._mondayOf=_mondayOf;ho.genKyOptions=genKyOptions;')(function(){return null;}, F);
const { _kyRange, _kyVal, _mondayOf, genKyOptions } = F;

var hong = 0, dat = 0;
function la(ten, mong, duoc) { if (mong === duoc) { dat++; return; } console.log('  HỎNG ' + ten + ' — mong ' + mong + ' được ' + duoc); hong++; }
function D(y, m, d) { return new Date(y, m - 1, d); }

/* ── NHÃN THÁNG LẤY THEO NGÀY CUỐI ────────────────────────────────────────────────────────────
   Không phải sở thích: `VHCP_Don::nhan_ky()` bên máy chủ dựng tên bằng tháng của ngày cuối, và
   `khoang_ky()`/`ky_num()` suy ngược năm bằng `sm > em ? yy-1 : yy` — tức đọc con số trong nhãn
   NHƯ LÀ tháng của ngày cuối. Giao diện lấy theo ngày đầu là hai bên sinh hai chuỗi khác nhau
   cho cùng một tuần bắc tháng. */
la('25/8 -> 30/8/2026 gọn trong tháng', 'T8/2026 (25/8-30/8/2026)', _kyRange(D(2026, 8, 25), D(2026, 8, 30)));
la('  đọc lại ra 20260825', 20260825, _kyVal(_kyRange(D(2026, 8, 25), D(2026, 8, 30))));
la('🔴 31/8 -> 6/9/2026 bắc tháng thì nhãn là T9', 'T9/2026 (31/8-6/9/2026)', _kyRange(D(2026, 8, 31), D(2026, 9, 6)));
la('  nhưng vẫn xếp theo NGÀY ĐẦU 20260831', 20260831, _kyVal(_kyRange(D(2026, 8, 31), D(2026, 9, 6))));
la('25/8 -> 5/9/2026 đợt dự án vắt tháng', 'T9/2026 (25/8-5/9/2026)', _kyRange(D(2026, 8, 25), D(2026, 9, 5)));
la('  đọc lại vẫn ra ngày đầu tháng 8', 20260825, _kyVal(_kyRange(D(2026, 8, 25), D(2026, 9, 5))));
/* Vắt NĂM — chỗ dễ sai nhất: chuỗi chỉ mang MỘT năm, ở cuối. Nay tuần cũng chạm ca này. */
la('🔴 28/12/2026 -> 3/1/2027 tuần vắt năm', 'T1/2027 (28/12-3/1/2027)', _kyRange(D(2026, 12, 28), D(2027, 1, 3)));
la('  đọc lại phải ra NĂM 2026', 20261228, _kyVal(_kyRange(D(2026, 12, 28), D(2027, 1, 3))));
la('đợt dài một ngày', 20260901, _kyVal(_kyRange(D(2026, 9, 1), D(2026, 9, 1))));
la('đợt dài 2 tháng', 20260710, _kyVal(_kyRange(D(2026, 7, 10), D(2026, 9, 20))));

/* ── DANH SÁCH TUẦN PHẢI LIÊN TỤC VÀ ĐỦ 7 NGÀY ───────────────────────────────────────────────
   Luật cũ cắt tuần tại ngày cuối tháng, đẻ ra `T8/2026 (31/8-31/8/2026)`: một "tuần" đúng MỘT
   ngày. Tuần làm việc bị xé làm đôi, mỗi nửa một đơn, quyết toán hai lần. */
function khoang(v) {
  var r = /\((\d{1,2})\/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\)/.exec(v);
  var yy = +r[5], sm = +r[2], em = +r[4], sy = (sm > em) ? yy - 1 : yy;
  return [ new Date(sy, sm - 1, +r[1]), new Date(yy, em - 1, +r[3]) ];
}
/* Chạy trên NHIỀU mốc thời gian, trong đó có đúng những mốc luật cũ đẻ ra kỳ dị dạng. */
[ '2026-08-25', '2026-08-31', '2026-09-01', '2026-12-28', '2027-01-01', '2026-02-26' ].forEach(function (mocIso) {
  /* Giả đồng hồ: `new Date()` trần trả về mốc đang thử, mọi dạng gọi khác chuyển tiếp NGUYÊN
     VẸN. Bản đầu viết `new That(a,b,c)` cho mọi lượt — `new Date(s)` (sao chép một Date) hoá
     thành `new That(s, undefined, undefined)` tức Invalid Date, và cả danh sách ra rỗng. */
  var That = Date;
  var moc = new That(mocIso + 'T09:00:00');
  Date = function () {
    if (!arguments.length) { return new That(moc.getTime()); }
    return new (Function.prototype.bind.apply(That, [ null ].concat([].slice.call(arguments))))();
  };
  Date.prototype = That.prototype;
  Date.now = function () { return moc.getTime(); };
  var ds;
  try { ds = genKyOptions(); } finally { Date = That; }

  la('[' + mocIso + '] sinh đủ 9 tuần', 9, ds.length);
  var lech7 = 0, hoLech = 0, truoc = null;
  ds.forEach(function (p) {
    var k = khoang(p.val), so = Math.round((k[1] - k[0]) / 86400000) + 1;
    if (so !== 7) { lech7++; }
    if (k[0].getDay() !== 1 || k[1].getDay() !== 0) { hoLech++; }
    if (truoc && Math.round((k[0] - truoc) / 86400000) !== 1) { hoLech++; }
    truoc = k[1];
  });
  la('[' + mocIso + '] 🔴 mọi tuần đủ 7 ngày, không cắt ở cuối tháng', 0, lech7);
  la('[' + mocIso + '] 🔴 tuần nào cũng thứ 2 → chủ nhật và nối liền tuần trước', 0, hoLech);
  la('[' + mocIso + '] đúng một kỳ được đánh dấu "tuần này"', 1, ds.filter(function (p) { return p.cur; }).length);
  la('[' + mocIso + '] và mốc hôm nay nằm trong chính kỳ ấy', true,
    (function () { var p = ds.filter(function (x) { return x.cur; })[0], k = khoang(p.val);
      return moc.getTime() >= k[0].getTime() && moc.getTime() <= k[1].getTime() + 86399999; })());
});

console.log(hong ? ('\n🔴 HỎNG: ' + hong + ' | ĐẠT: ' + dat) : ('\n✓ SẠCH — ' + dat + ' phép chuỗi kỳ'));
process.exit(hong ? 1 : 0);
