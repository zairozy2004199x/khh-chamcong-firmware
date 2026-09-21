/**
 * Ô NGÀY: ĐỪNG CHẠY LÚC NGƯỜI TA ĐANG GÕ, VÀ ĐỪNG XOÁ TRẮNG CÁI ĐANG CÓ.
 *
 * Anh Thắng 18/09/2026: *"cứ bấm sửa số ngày nó trắng xíu trong nhảy ra, sao không bấm số, rồi
 * chọn mới chạy"*.
 *
 * `<input type="date">` bắn `change` NGAY GIỮA LÚC GÕ, không đợi gõ xong:
 *   · xoá một ô để sửa -> giá trị thành RỖNG -> `change` -> đi hỏi máy chủ một khoảng rỗng;
 *   · gõ tiếp -> vừa đủ một ngày hợp lệ là `change` lần nữa, dù còn đang gõ dở tháng/năm.
 * Mỗi lượt như thế lại xoá trắng cả màn Đối soát rồi vẽ lại -> màn chớp và nhảy về đầu trang.
 *
 * Bài này chạy THẬT hàm `noiONgay` trên một ô giả và một bộ hẹn giờ giả — chứ không chỉ tìm
 * chuỗi trong mã. Tìm chuỗi thì đổi 500 thành 0 vẫn xanh, mà 0 là quay lại y như cũ.
 *
 * 🔴 BỐN CHỖ DỄ HỎNG LẠI:
 *   1. Giá trị rỗng / gõ dở mà vẫn chạy — đây là cái làm màn trắng.
 *   2. Gõ ba nhịp ra ba lượt tải thay vì một.
 *   3. Rời ô rồi mà vẫn bắt đợi hết nhịp chờ — chọn xong trên lịch phải chạy ngay.
 *   4. Rời ô sau khi nhịp chờ đã chạy -> chạy lại lần hai, hai lượt tải cho một lần sửa.
 *
 * Chạy: node tools/test/kiem-o-ngay.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.js');
const src = fs.readFileSync(TEP, 'utf8');

let dat = 0;
const hong = [];
function t(ten, dk) { if (dk) { dat++; } else { hong.push(ten); } }

function boc(ten) {
  const i = src.indexOf('  function ' + ten + '(');
  if (i < 0) { console.error('KHÔNG tìm thấy ' + ten); process.exit(1); }
  const j = src.indexOf('\n  }\n', i);
  return src.slice(i, j + 4);
}
const mRe = src.match(/var NGAY_DU = [^\n]+/);
if (!mRe) { console.error('KHÔNG tìm thấy NGAY_DU'); process.exit(1); }

/* ── bộ hẹn giờ giả: giữ lại hẹn để tự tay cho chạy ───────────────────────────────── */
let hen = [];
let cho = [];   // nhịp chờ đã xin, để kiểm nó KHÁC 0 — xem phép cuối mục 2
const setTimeoutGia = (f, ms) => { hen.push(f); cho.push(ms); return hen.length; };
const clearTimeoutGia = (id) => { if (id) hen[id - 1] = null; };
const chayHen = () => { const x = hen; hen = []; x.forEach((f) => f && f()); };

const noiONgay = new Function(
  'setTimeout', 'clearTimeout',
  mRe[0] + '\n' + boc('noiONgay') + '\nreturn noiONgay;'
)(setTimeoutGia, clearTimeoutGia);

/* ── ô ngày giả ───────────────────────────────────────────────────────────────────── */
function oGia(v) {
  const h = {};
  return {
    value: v,
    addEventListener(k, f) { (h[k] = h[k] || []).push(f); },
    ban(k) { (h[k] || []).slice().forEach((f) => f()); },
  };
}
function dung(batDau) {
  hen = []; cho = [];
  const o = oGia(batDau);
  const goi = [];
  noiONgay(o, (v) => goi.push(v));
  return { o, goi };
}

/* ── 1. giá trị rỗng hoặc gõ dở thì KHÔNG làm gì ──────────────────────────────────── */
let a = dung('2026-09-01');
a.o.value = ''; a.o.ban('change');
t('🔴 ô bị xoá trắng thì KHÔNG đặt hẹn', hen.filter(Boolean).length === 0);
chayHen();
t('🔴 và KHÔNG đi hỏi máy chủ', a.goi.length === 0);

a.o.value = '2026-9'; a.o.ban('change');
chayHen();
t('gõ dở ("2026-9") cũng không chạy', a.goi.length === 0);

/* ── 2. đủ một ngày thì chờ một nhịp rồi chạy MỘT lượt ────────────────────────────── */
a = dung('2026-09-01');
a.o.value = '2026-09-16'; a.o.ban('change');
t('đủ ngày rồi vẫn CHƯA chạy ngay', a.goi.length === 0);
/* 🔴 Nhịp chờ phải THẬT SỰ là một nhịp. Đặt 0 thì `setTimeout` chạy ngay ở vòng sau, tức mỗi
   nhịp gõ vẫn ra một lượt tải — y như cũ, mà mọi phép khác vẫn xanh vì hẹn giờ giả không xét
   con số. Đã thử: đổi 500 thành 0 mà bài vẫn sạch, nên mới có phép này. */
t('🔴 nhịp chờ là một nhịp THẬT (>= 250ms), không phải 0',
  cho.filter((x) => typeof x === 'number').every((x) => x >= 250) && cho.some((x) => x >= 250));
chayHen();
t('hết nhịp chờ thì chạy', a.goi.length === 1);
t('và chạy với đúng ngày vừa gõ', a.goi[0] === '2026-09-16');

/* ── 3. gõ ba nhịp liên tiếp ra ĐÚNG MỘT lượt, lấy giá trị cuối ───────────────────── */
a = dung('2026-09-01');
a.o.value = '2026-09-02'; a.o.ban('change');
a.o.value = '2026-09-12'; a.o.ban('change');
a.o.value = '2026-09-16'; a.o.ban('change');
chayHen();
t('🔴 gõ ba nhịp chỉ ra MỘT lượt tải', a.goi.length === 1);
t('và là giá trị cuối cùng', a.goi[0] === '2026-09-16');

/* ── 4. rời ô thì chạy NGAY, không bắt đợi ────────────────────────────────────────── */
a = dung('2026-09-01');
a.o.value = '2026-09-16'; a.o.ban('change');
a.o.ban('blur');
t('🔴 rời ô là chạy ngay, không đợi hết nhịp', a.goi.length === 1 && a.goi[0] === '2026-09-16');
chayHen();
t('🔴 và nhịp chờ cũ KHÔNG chạy thêm lượt nữa', a.goi.length === 1);

/* ── 5. rời ô SAU khi nhịp chờ đã chạy thì thôi ───────────────────────────────────── */
a = dung('2026-09-01');
a.o.value = '2026-09-16'; a.o.ban('change');
chayHen();
a.o.ban('blur');
t('🔴 rời ô sau khi đã chạy thì KHÔNG chạy lại', a.goi.length === 1);

/* ── 6. rời ô lúc đang trống thì thôi ─────────────────────────────────────────────── */
a = dung('2026-09-01');
a.o.value = ''; a.o.ban('blur');
t('rời ô lúc đang trống thì không chạy', a.goi.length === 0);

/* ── 7. gõ trở lại đúng giá trị cũ thì không phải tải lại ─────────────────────────── */
a = dung('2026-09-01');
a.o.value = '2026-09-16'; a.o.ban('change'); chayHen();
a.o.value = '2026-09-01'; a.o.ban('change'); chayHen();
a.o.value = '2026-09-01'; a.o.ban('change'); chayHen();
t('cùng một giá trị gõ lại KHÔNG tải thêm lượt', a.goi.length === 2);

/* ── 8. ô không có thì đừng nổ ────────────────────────────────────────────────────── */
let no = false;
try { noiONgay(null, () => {}); } catch (e) { no = true; }
t('ô không có thì im lặng bỏ qua, không nổ', !no);

/* ── 9. mọi ô ngày đều phải đi qua hàm này ────────────────────────────────────────── */
const boCC = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/^\s*\/\/.*$/gm, ' ');
t('🔴 khong con o ngay nao noi thang vao change',
  !/#(dtTu|dtDen|dsTu|dsDen)'\)\.addEventListener\('change'/.test(boCC));
t('tab Doanh thu dung noiONgay', /noiONgay\(q\('#dtTu'\)/.test(boCC));
t('tab Doi soat dung noiONgay', /noiONgay\(t,/.test(boCC) && /noiONgay\(d,/.test(boCC));

/* ── 10. taiDoiSoat: giữ số cũ trên màn, và bỏ lượt trả về trễ ────────────────────── */
const td = boc('taiDoiSoat').replace(/\/\*[\s\S]*?\*\//g, ' ');
t('🔴 chi xoa trang khi CHUA co gi de giu', /if \(!S\.dsR\)/.test(td));
t('con so cu thi chi danh dau dang ban', /aria-busy/.test(td));
t('🔴 danh so luot goi', /\+\+dsLuot/.test(td));
t('luot tra ve tre thi BO, khong de len luot moi',
  (td.match(/luot !== dsLuot/g) || []).length >= 2);

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: ô ngày đợi gõ xong mới chạy, và màn không chớp trắng.');
