/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CÂU CẢNH BÁO TRÊN HÀNG GHẾ PHẢI ĐỌC ĐƯỢC
 *
 * Anh Thắng 08/09/2026 gửi màn hình của chị Ngọc Lan (VP-PQ-5: chỉ số trước 312, sau 12, ra
 * −3.000.000): câu *"⚠ Chỉ số sau nhỏ hơn trước — ghi lý do ở cột Ghi chú và nhập số tiền thật
 * ở cột Thực thu tiền mặt"* rơi DỌC, MỖI CHỮ MỘT DÒNG, kéo dài quá nửa màn điện thoại.
 *
 * =============================================================================================
 * 🔴 VÌ SAO. Ô cảnh báo được nhét vào ô CỘT ĐẦU — cột tên ghế. Cột ấy rộng vài chục pixel trên
 *    điện thoại, mà câu thì dài hơn trăm ký tự.
 *
 * 🔴 VÌ SAO NÓ KHÔNG PHẢI CHUYỆN THẨM MỸ. Đúng câu ấy là thứ DUY NHẤT nói cho người thu tiền
 *    biết vì sao bấm "Gửi báo cáo" không được (`guiBaoCao()` chặn hàng bất thường cho tới khi
 *    có lý do + Thực thu). Đọc không nổi thì họ bấm lại, bấm lại, rồi kết luận là trang hỏng —
 *    và đó đúng là chuyện đang xảy ra.
 *
 * ⚠️ DỰNG BẢNG THẬT BẰNG DOM GIẢ RỒI SOI, không dò chuỗi trong mã.
 *
 * Chạy: node tools/test/kiem-canh-bao-doc-duoc.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const DUONG = process.argv[2] || 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(DUONG)) { console.error('✗ Không thấy tệp nguồn: ' + DUONG); process.exit(2); }
const SRC = fs.readFileSync(DUONG, 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 0. DOM GIẢ — đủ để `veDong()` dựng xong một hàng, không hơn
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function Nut(tag) {
  this.tagName = String(tag || '').toUpperCase();
  this.children = []; this.dataset = {}; this.style = { cssText: '' };
  this._cls = []; this.textContent = ''; this.colSpan = 0; this.value = '';
  this._className = '';
}
/* ⚠️ `className` và `classList` PHẢI ĐI ĐÔI, y như DOM thật. Bản đầu của bệ đỡ này để chúng rời
   nhau, nên gán thẳng `tr.className='bc-warn-row'` không hề vào `classList` — và phép thử đỏ vì
   BỆ ĐỠ sai, không phải vì mã sai. Mất một lượt đúng như thế. */
Object.defineProperty(Nut.prototype, 'className', {
  get: function () { return this._className; },
  set: function (v) {
    this._className = String(v == null ? '' : v);
    this._cls = this._className.split(/\s+/).filter(Boolean);
  }
});
Nut.prototype.appendChild = function (c) { this.children.push(c); c.parentNode = this; return c; };
Nut.prototype.addEventListener = function () {};
Nut.prototype.querySelector = function () { return null; };
Nut.prototype.querySelectorAll = function () { return []; };
Object.defineProperty(Nut.prototype, 'classList', {
  get: function () {
    const n = this;
    return { add: function (c) { if (n._cls.indexOf(c) < 0) n._cls.push(c); },
             remove: function (c) { n._cls = n._cls.filter(function (x) { return x !== c; }); },
             contains: function (c) { return n._cls.indexOf(c) >= 0; } };
  }
});
function el(tag, cls, txt) {
  const n = new Nut(tag);
  if (cls) { n.className = cls; }
  if (txt != null) n.textContent = String(txt);
  return n;
}

function bocHam(ten) {
  const i = SRC.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = SRC.indexOf('\n  }', i) + 4;
  return (j > i) ? SRC.slice(i, j) : '';
}
const fnVeDong = bocHam('veDong');
const fnGan = SRC.slice(SRC.indexOf('  function _gan('), SRC.indexOf('\n', SRC.indexOf('  function _gan(')));
t('bốc được veDong()', fnVeDong.length > 800, fnVeDong.length);
t('bốc được _gan()', fnGan.length > 40, fnGan);

/* Mọi thứ `veDong()` chạm tới mà không phải việc của bài này → cho một cái vỏ rỗng. */
function dungHang(ma, before) {
  /* Danh sách này bốc từ chính `veDong()` (mọi định danh VIẾT HOA nó chạm tới). Thiếu một cái
     là `ReferenceError` giữa chừng — mà lỗi ấy trông y hệt "bài kiểm đỏ". */
  const tdRong = function () { return el('td'); };
  return new Function('el', 'money', 'inp', 'cell', 'cellRo', 'celAnh', 'calc', 'warn',
    'ddmmyy_', 'nhanNgayVn', 'KE', 'LASTD', 'NGAY', 'QR', 'document', 'g', 'before',
    fnVeDong + '\nreturn veDong(g, before);')(
    el, function (x) { return String(x); },
    function () { return el('input'); },
    function (x) { const td = el('td'); td.appendChild(x); return td; },
    tdRong, function (c) { return el('td', c); },
    function () {}, function () {},
    function (x) { return String(x); }, function (x) { return String(x); },
    {}, {}, '2026-09-08', {},
    { createElement: function (tg) { return el(tg); } },
    { ma: ma, ten: ma }, before);
}

let TR = null;
try { TR = dungHang('VP-PQ-5', 312); } catch (e) { t('dựng được một hàng ghế', false, String(e.message)); }
if (!TR) {
  console.error('\n✗ TRƯỢT: không dựng nổi hàng ghế — dừng.');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
t('dựng được một hàng ghế', !!TR);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 Ô CẢNH BÁO KHÔNG CÒN NẰM TRONG Ô CỘT ĐẦU
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function timTrong(nut, cls) {
  if (nut._cls && nut._cls.indexOf(cls) >= 0) return nut;
  for (let i = 0; i < nut.children.length; i++) {
    const r = timTrong(nut.children[i], cls);
    if (r) return r;
  }
  return null;
}
const oDau = TR.children[0];
t('ô cột đầu vẫn là tên ghế', !!oDau);
t('🔴 ô cột đầu KHÔNG còn chứa câu cảnh báo', !timTrong(oDau, 'bc-warn'),
  oDau ? oDau.children.map(function (c) { return c.className; }) : null);
/* Và không ô nào khác của hàng chính chứa nó — nhét vào cột "Ghi chú" cũng hẹp y như thế. */
t('🔴 KHÔNG ô nào của hàng chính chứa nó', !timTrong(TR, 'bc-warn'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. NÓ Ở HÀNG RIÊNG, TRẢI HẾT CHIỀU NGANG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const TR2 = TR._pair;
t('🔴 veDong() trả kèm một hàng phụ', !!TR2);
t('hàng phụ mang đúng lớp', TR2 && TR2._cls.indexOf('bc-warn-row') >= 0, TR2 && TR2.className);
t('🔴 hàng phụ ẩn khi chưa có cảnh báo', TR2 && TR2.style.display === 'none', TR2 && TR2.style.display);
const td2 = TR2 && TR2.children[0];
t('hàng phụ có đúng MỘT ô', TR2 && TR2.children.length === 1, TR2 && TR2.children.length);
t('🔴 ô ấy trải hết chiều ngang (colspan lớn)', td2 && Number(td2.colSpan) >= 20, td2 && td2.colSpan);
t('🔴 và câu cảnh báo nằm trong đó', !!(td2 && timTrong(td2, 'bc-warn')));

/* 🔴 HÀNG PHỤ KHÔNG ĐƯỢC MANG `data-ma`. Mọi vòng lặp của màn đều dò `#bc-rows tr[data-ma]` —
   collect, calc, tính tổng. Hàng phụ lọt vào đó là bảng cộng thừa một ghế rỗng, và số tiền
   của cả cơ sở sai. */
teq('🔴 hàng phụ KHÔNG mang data-ma', undefined, TR2 && TR2.dataset.ma);
teq('   đối chứng · hàng chính CÓ mang', 'VP-PQ-5', TR.dataset.ma);

/* `calc()` phải cầm được ô ấy mà không phải dò ngược DOM. */
t('🔴 hàng chính giữ tham chiếu tới ô cảnh báo', !!TR._warn);
t('   và tới hàng phụ', !!TR._warnRow);
t('hai tham chiếu trỏ đúng chỗ', TR._warn === timTrong(TR2, 'bc-warn') && TR._warnRow === TR2);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. GẮN VÀO BẢNG THÌ HAI HÀNG PHẢI ĐI LIỀN NHAU, ĐÚNG THỨ TỰ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const body = el('tbody');
const gan = new Function('body', 'tr', fnGan + '\n_gan(body, tr);');
const A = dungHang('A', 10), B = dungHang('B', 20);
gan(body, A); gan(body, B);
teq('🔴 hai ghế → bốn hàng', 4, body.children.length);
t('🔴 hàng cảnh báo đi NGAY SAU hàng ghế của nó · ghế A',
  body.children[0] === A && body.children[1] === A._pair,
  body.children.map(function (x) { return x.className || x.dataset.ma; }));
t('   · ghế B', body.children[2] === B && body.children[3] === B._pair);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. KIỂU DÁNG — BA LUẬT GIỮ CHO NÓ KHÔNG VỠ LẠI
 *
 * 🔴 Đây là chỗ duy nhất bài này phải soi chuỗi: kiểu dáng nằm trong CSS, không chạy được bằng
 *    DOM giả. Ghi rõ để người sau biết đây là chỗ mù — nó canh LUẬT có mặt, không canh trình
 *    duyệt vẽ ra sao.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iCss = SRC.indexOf('.bc-warn-row .bc-warn{');
const css = iCss > 0 ? SRC.slice(iCss, iCss + 500) : '';
t('bốc được kiểu dáng hàng cảnh báo', '' !== css);
t('🔴 cho xuống dòng bình thường (thắng mọi nowrap của bảng)', /white-space:normal/.test(css), css);
t('🔴 bề rộng tối thiểu theo MÀN HÌNH, không theo bề rộng cột', /min-width:min\(/.test(css), css);
t('   và có chặn trên để không tràn ngang', /max-width:min\(/.test(css), css);
t('cỡ chữ đủ đọc (từ 12px trở lên)', /font-size:12(\.\d+)?px/.test(css), css);
t('có nền và khung để mắt bắt được giữa bảng số', /background:#fff/.test(css) && /border:1px solid/.test(css), css);
/* Khung vàng "chỉ nhắc" vẫn phải khác khung đỏ "chặn gửi" — hai việc khác nhau. */
t('🔴 khung nhắc (vàng) khác khung chặn (đỏ)', /\.bc-warn-row \.bc-warn\.bc-nhac\{/.test(SRC));

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: câu cảnh báo ở hàng riêng trải hết chiều ngang, không rơi dọc nữa.');
