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
const CSS_TEP = path.join(__dirname, '..', '..', 'wordpress', 'khh-doanh-thu', 'assets', 'doanh-thu.css');
const cssGoc = fs.readFileSync(CSS_TEP, 'utf8').replace(/\/\*[\s\S]*?\*\//g, ' ');

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
/* 24/09/2026 anh Thắng: "cứ bấm nào nó lại mất bảng" — tab có nút Lọc thì hai ô ngày KHÔNG tự chạy nữa
   (tự chạy = vẽ lại cả thanh lọc = bảng lịch đang mở biến mất). Chỉ Lọc / Enter mới chạy. */
t('🔴 tab Doanh thu KHÔNG nối noiONgay vào #dtTu/#dtDen (chỉ chạy khi Lọc)', !/noiONgay\(q\('#dtTu'\)/.test(boCC) && !/noiONgay\(q\('#dtDen'\)/.test(boCC));
t('🔴 tab Đối soát KHÔNG nối noiONgay vào hai ô ngày', !/noiONgay\(t,/.test(boCC) && !/noiONgay\(d,/.test(boCC));
t('ô ngày lẻ (sổ kho, thẻ kho) vẫn dùng noiONgay', /noiONgay\(k\.querySelector\('#khoNgay'\)/.test(boCC) && /noiONgay\(noi\.querySelector\('#theTu'\)/.test(boCC));
t('Đối soát: nút Lọc gọi locTay và Enter cũng chạy Lọc', /locTay\(t, d, function \(tu, den\)/.test(boCC) && /locKhiEnter\(t, nl\); locKhiEnter\(d, nl\);/.test(boCC));

/* ── 10. taiDoiSoat: giữ số cũ trên màn, và bỏ lượt trả về trễ ────────────────────── */
const td = boc('taiDoiSoat').replace(/\/\*[\s\S]*?\*\//g, ' ');
t('🔴 chi xoa trang khi CHUA co gi de giu', /if \(!S\.dsR\)/.test(td));
t('con so cu thi chi danh dau dang ban', /aria-busy/.test(td));
t('🔴 danh so luot goi', /\+\+dsLuot/.test(td));
t('luot tra ve tre thi BO, khong de len luot moi',
  (td.match(/luot !== dsLuot/g) || []).length >= 2);

/* ── 11. nút Lọc và tab Doanh thu không bỏ rơi lượt gọi (anh Thắng 23/09/2026: "chọn ngày nó ko
      tự ra, thêm nút tìm kiếm để nó chạy ngày lọc") ───────────────────────────────────────── */
t('tab Doanh thu có nút #dtLoc', /id="dtLoc"/.test(boCC));
t('tab Doi soat có nút #dsLoc', /id="dsLoc"/.test(boCC));
t('nút dtLoc gọi locTay với hai ô ngày', /#dtLoc'\)\.addEventListener\('click'[\s\S]{0,120}locTay\(q\('#dtTu'\), q\('#dtDen'\)/.test(boCC));
t('nút dsLoc gọi locTay', /#dsLoc'\)[\s\S]{0,160}locTay\(t, d,/.test(boCC));
t('Enter trong ô ngày cũng chạy Lọc', /locKhiEnter\(q\('#dtTu'\), q\('#dtLoc'\)\)/.test(boCC));
const taiSrc = boc('tai').replace(/\/\*[\s\S]*?\*\//g, ' ');
t('🔴 tai() KHÔNG còn bỏ rơi lượt gọi khi đang tải', !/if \(S\.dangTai\) return;/.test(taiSrc));
t('🔴 tai() đánh số lượt (++dtLuot)', /\+\+dtLuot/.test(taiSrc));
t('lượt trả về trễ thì bỏ (cả then và catch)', (taiSrc.match(/luot !== dtLuot/g) || []).length >= 2);
/* locTay chạy thật: thiếu một ngày -> không chạy; đủ hai ngày -> chạy đúng thứ tự. */
{
  const m = src.match(/  function locTay\([\s\S]*?\n  \}\n/);
  t('tìm thấy locTay', !!m);
  if (m) {
    let baoGoi = 0;
    const f = new Function('NGAY_DU', 'bao', m[0] + '\nreturn locTay;')(new RegExp(mRe[0].match(/\/(.*)\//)[1]), () => { baoGoi++; });
    const goi = [];
    t('thiếu ngày đến -> không chạy, có báo', f({ value: '2026-09-16' }, { value: '' }, (a, b) => goi.push([a, b])) === false && goi.length === 0 && baoGoi === 1);
    t('đủ hai ngày -> chạy một lượt', f({ value: '2026-09-16' }, { value: '2026-09-23' }, (a, b) => goi.push([a, b])) === true && goi.length === 1 && goi[0][0] === '2026-09-16' && goi[0][1] === '2026-09-23');
    f({ value: '2026-09-23' }, { value: '2026-09-16' }, (a, b) => goi.push([a, b]));
    t('Từ sau đến thì đảo lại, không hỏi khoảng ngược', goi[1][0] === '2026-09-16' && goi[1][1] === '2026-09-23');
  }
}

/* ── 12. đang tải KHÔNG được khoá luôn thanh lọc ──────────────────────────────────
 * Anh Thắng 26/09/2026, ảnh tab Đối soát: *"Không chỉnh được ngày"*. Trong lúc `taiDoiSoat()`
 * đang chờ máy chủ (`aria-busy="true"`), CSS khoá `pointer-events:none` cho CẢ TAB để không ai
 * bấm vào bảng số sắp bị thay — đúng ý, nhưng khoá luôn CẢ ô Từ/đến, ô Cơ sở và nút Lọc theo,
 * nên mạng chậm hay một lượt tải bị treo là không còn cách nào tự sửa ngày để thử lại. `opacity`
 * mờ cả cây thì không gỡ được cho riêng con (CSS không có đường thoát), nhưng `pointer-events`
 * gỡ được — nên phải có luật MỞ LẠI đường bấm/gõ cho riêng `.loc` (thanh lọc), còn phần còn lại
 * (bảng) vẫn khoá như cũ. */
t('🔴 CSS: aria-busy khoá pointer-events cho cả tab Đối soát', /#dtTabDoiSoat\[aria-busy="true"\]\s*\{[^}]*pointer-events:\s*none/.test(cssGoc));
t('🔴 … NHƯNG mở lại pointer-events:auto cho .loc và mọi con của nó (ô ngày, ô cơ sở, nút Lọc vẫn bấm/gõ được khi đang tải)',
  /#dtTabDoiSoat\[aria-busy="true"\]\s*\.loc\s*,\s*#dtTabDoiSoat\[aria-busy="true"\]\s*\.loc\s*\*\s*\{[^}]*pointer-events:\s*auto/.test(cssGoc));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: ô ngày đợi gõ xong mới chạy, và màn không chớp trắng.');
