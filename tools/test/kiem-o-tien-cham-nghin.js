/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô NHẬP TIỀN HIỆN DẤU CHẤM NGHÌN NGAY LÚC GÕ
 *
 * Anh Thắng 11/09/2026: *"khi gõ vào nó hiện ra 500.000 để dễ hiểu"*, kèm ảnh hàng nhập dòng
 * chi phí dự án với ĐƠN GIÁ 500000 và THÀNH TIỀN 500000 viết liền.
 *
 * =============================================================================================
 * 🔴 CHỖ CHẾT NGƯỜI CỦA THAY ĐỔI NÀY: `Number("500.000")` = 500.
 *    JS đọc dấu chấm là dấu THẬP PHÂN. Ô tiền vừa đổi sang hiện "500.000" thì mọi chỗ còn đọc
 *    `Number(el('da_dg').value)` lập tức ra 500 — đơn giá sai GẤP NGHÌN LẦN, mà màn hình vẫn
 *    hiện 500.000 và không có câu lỗi nào. Không bài kiểm nào bắt được kiểu hỏng ấy bằng mắt.
 *
 *    Nên bài kiểm này có hai tầng:
 *      · tầng CHẠY THẬT  — bốc `tienGo`/`_oTien`/`_datTien`/`daCalcTT`... ra chạy;
 *      · tầng QUÉT TĨNH  — soi cả tệp, bắt bất cứ chỗ nào còn đọc ô tiền trần. Tầng này là
 *        thứ bắt được ô thứ mười lăm mà người sửa sau này quên, chứ không chỉ mười ba ô nay.
 *
 * 🔴 CON TRỎ PHẢI ĐỨNG YÊN. Chèn dấu chấm giữa chừng mà con trỏ nhảy về cuối là sửa một chữ
 *    số ở giữa thành cực hình — đúng lý do trước đây chỉ dám định dạng lúc rời ô.
 *
 * Chạy: node tools/test/kiem-o-tien-cham-nghin.js
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
/* Danh sách ô tiền — bốc từ CHÍNH mã, không chép lại: chép là hai danh sách, và hai danh sách
   thì sớm muộn lệch đúng ở ô vừa thêm. */
const mDs = /var O_TIEN=\[([\s\S]*?)\];/.exec(HTML);
t('bốc được danh sách O_TIEN', !!mDs);
const O_TIEN = mDs ? mDs[1].split(',').map(x => x.trim().replace(/^'|'$/g, '')).filter(Boolean) : [];
t('danh sách có đủ mười ba ô', O_TIEN.length === 13, O_TIEN);

const MOI = bocDong('_tienSo') + '\n' + bocDong('_tienDep') + '\n'
  + bocDong('_oTien') + '\n' + bocDong('_datTien') + '\n'
  + bocHam('tienGo') + '\n' + bocDong('daCalcTT') + '\n' + bocDong('blCalcTT') + '\n'
  + bocHam('calcTT') + '\n' + bocDong('calcScTT');

/* Ô giả: đủ để `tienGo` chạy thật (value + selectionStart + setSelectionRange). */
function oGia(v, vt) {
  return { value: v, selectionStart: (vt === undefined ? String(v).length : vt),
    setSelectionRange: function (a) { this.selectionStart = a; } };
}
const KHO = {};
function moiTruong() {
  return new Function('el', 'Math', MOI +
    '\nreturn { go:tienGo, doc:_oTien, dat:_datTien, dep:_tienDep, so:_tienSo,'
    + ' daTT:daCalcTT, blTT:blCalcTT, fTT:calcTT, scTT:calcScTT };')(
    function (id) { return KHO[id]; }, Math);
}
const F = moiTruong();

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. GÕ TỚI ĐÂU CHẤM TỚI ĐÓ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function go(v, vt) { const o = oGia(v, vt); F.go(o); return o; }
teq('gõ 500000 → 500.000', '500.000', go('500000').value);
teq('gõ 5000000 → 5.000.000', '5.000.000', go('5000000').value);
teq('gõ 1 chữ số thì chưa có chấm', '1', go('1').value);
teq('gõ 999 chưa có chấm',          '999', go('999').value);
teq('gõ 1000 → 1.000',              '1.000', go('1000').value);
teq('ô rỗng vẫn rỗng',              '', go('').value);
/* 🔴 Dán từ Excel thường kèm dấu phẩy hoặc chữ "đ" — phải nuốt được, không thì người ta dán
   xong thấy ô trống và gõ tay lại cả ngày. */
teq('dán "1,234,567" → 1.234.567',  '1.234.567', go('1,234,567').value);
teq('dán "500.000đ" → 500.000',     '500.000', go('500.000đ').value);
teq('dán " 250 000 " → 250.000',    '250.000', go(' 250 000 ').value);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CON TRỎ ĐỨNG YÊN THEO SỐ CHỮ SỐ, KHÔNG THEO VỊ TRÍ KÝ TỰ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* "500000" con trỏ sau chữ số thứ 3 → "500.000", con trỏ vẫn đứng sau chữ số thứ 3: vị trí 3,
   tức NGAY TRƯỚC dấu chấm vừa chèn.

   🔴 TRƯỚC dấu chấm chứ không phải sau — khác nhau đúng một bước mà đổi hẳn hành vi phím
      Backspace. Đứng trước: bấm Backspace xoá số 0 cuối của "500", ra "50.000" — đúng thứ
      người ta định xoá. Đứng sau: Backspace nhằm vào chính dấu chấm, xoá xong định dạng lại
      chèn ngay dấu chấm ấy trở lại, và người dùng bấm mãi không thấy gì đổi. */
teq('con trỏ sau chữ số thứ 3 của 500000', 3, go('500000', 3).selectionStart);
/* 🔴 CHUỖI ĐÃ ĐẸP SẴN THÌ ĐỪNG ĐỤNG VÀO CON TRỎ. Sự kiện `input` còn nổ vì những thứ không
   đổi chuỗi (gõ một ký tự chữ rồi bị lọc, dán đúng cái đang có). Lúc ấy định dạng lại ra y
   hệt, nhưng phép đếm chữ số kéo con trỏ đang đứng SAU dấu chấm về TRƯỚC nó — con trỏ tự
   lùi một bước giữa lúc người ta đang gõ. Nên khi không có gì đổi thì thoát luôn. */
const oYen = go('500.000', 4);
teq('chuỗi không đổi thì giữ nguyên', '500.000', oYen.value);
teq('🔴 và con trỏ KHÔNG tự lùi',      4, oYen.selectionStart);
/* Chính ca Backspace ấy, chạy thật: "500.000" xoá số 0 cuối của "500" -> "50.000". */
const oXoa = go('50.000', 2);
teq('🔴 Backspace ngay trước dấu chấm xoá được chữ số', '50.000', oXoa.value);
teq('   và con trỏ nằm lại sau "50"', 2, oXoa.selectionStart);
teq('con trỏ ở đầu thì vẫn ở đầu',         0, go('500000', 0).selectionStart);
teq('con trỏ ở cuối thì vẫn ở cuối',       7, go('500000', 6).selectionStart);
/* Sửa một chữ số ở giữa: "1.234.567" xoá số 4 → gõ lại. Con trỏ không được nhảy về cuối. */
const oSua = go('1.23.567', 4);   // vừa xoá số '4', con trỏ đứng sau "1.23"
teq('sửa giữa chuỗi: định dạng lại đúng',  '123.567', oSua.value);
teq('🔴 và con trỏ KHÔNG nhảy về cuối',    3, oSua.selectionStart);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 ĐỌC RA VẪN LÀ SỐ TRẦN — chỗ sai gấp nghìn lần
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
KHO['da_dg'] = oGia('500.000');
teq('_oTien đọc "500.000" ra 500000', '500000', F.doc('da_dg'));
t('🔴 KHÔNG ra 500 (Number đọc chấm là thập phân)', F.doc('da_dg') !== '500', F.doc('da_dg'));
KHO['da_dg'] = oGia('');
teq('ô trống đọc ra chuỗi rỗng', '', F.doc('da_dg'));
teq('ô không tồn tại cũng rỗng', '', F.doc('khong_co_o_nay'));

KHO['da_tt'] = oGia('');
F.dat('da_tt', 1234567);
teq('_datTien ghi vào hiện dạng đẹp', '1.234.567', KHO['da_tt'].value);
F.dat('da_tt', '');
teq('ghi rỗng thì ô rỗng', '', KHO['da_tt'].value);
F.dat('da_tt', null);
teq('ghi null cũng rỗng',  '', KHO['da_tt'].value);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 THÀNH TIỀN = SL × ĐƠN GIÁ, TÍNH TRÊN SỐ TRẦN
 *
 * Đây đúng là chỗ sẽ hỏng im lặng: đơn giá 500.000 × 1 phải ra 500.000, không phải 500.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
KHO['da_sl'] = oGia('1'); KHO['da_dg'] = oGia('500.000'); KHO['da_tt'] = oGia('');
F.daTT();
teq('🔴 dự án: 1 × 500.000 = 500.000', '500.000', KHO['da_tt'].value);
KHO['da_sl'] = oGia('3'); F.daTT();
teq('   3 × 500.000 = 1.500.000', '1.500.000', KHO['da_tt'].value);
KHO['da_sl'] = oGia(''); F.daTT();
teq('   thiếu số lượng → thành tiền rỗng', '', KHO['da_tt'].value);

KHO['bl_sl'] = oGia('2'); KHO['bl_dg'] = oGia('1.250.000'); KHO['bl_tt'] = oGia('');
F.blTT();
teq('🔴 công tác/Setup: 2 × 1.250.000 = 2.500.000', '2.500.000', KHO['bl_tt'].value);

KHO['f_sl'] = oGia('4'); KHO['f_dg'] = oGia('81.000'); KHO['f_tt'] = oGia('');
F.fTT();
teq('🔴 sổ chi phí: 4 × 81.000 = 324.000', '324.000', KHO['f_tt'].value);

KHO['sc_sl'] = oGia('2'); KHO['sc_dg'] = oGia('99.500'); KHO['sc_tt'] = oGia('');
F.scTT();
teq('🔴 sửa dòng chi: 2 × 99.500 = 199.000', '199.000', KHO['sc_tt'].value);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. 🔴 QUÉT TĨNH — KHÔNG CÒN CHỖ NÀO ĐỌC Ô TIỀN TRẦN
 *
 * Tầng này mới là lưới an toàn thật: nó bắt cả ô thêm sau này, và bắt cả chỗ ai đó vô tình
 * viết lại `el('da_dg').value` trong một hàm mới.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
O_TIEN.forEach(function (id) {
  /* Cho phép `el(id).value=''` (ghi rỗng lúc dọn form) — chỉ cấm ĐỌC và cấm ghi số vào trần. */
  const doc = new RegExp("el\\('" + id + "'\\)\\.value(?!\\s*=\\s*'')", 'g');
  const hit = HTML.match(doc) || [];
  t("🔴 '" + id + "' không còn chỗ nào đọc/ghi trần", hit.length === 0, hit);
  /* Và ô phải được đánh dấu để `_tienGanHet()` gắn được định dạng. */
  const th = new RegExp('id="' + id + '"[^>]*data-tien="1"');
  t("   '" + id + "' có data-tien=\"1\" trong HTML", th.test(HTML));
  /* type=number KHÔNG nhận nổi "500.000" — gán vào là ô rỗng luôn, hỏng lặng lẽ. */
  const num = new RegExp('id="' + id + '"[^>]*type="number"');
  t("🔴 '" + id + "' KHÔNG còn type=\"number\"", !num.test(HTML));
});
t('🔴 `_tienGanHet()` được gọi lúc trang dựng', HTML.indexOf('_tienGanHet();') > 0);
t('   và nó bắt sự kiện "input" (bắt cả dán chuột)', /addEventListener\('input'/.test(bocHam('_tienGanHet')));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — ô tiền hiện dấu chấm lúc gõ, đọc ra vẫn số trần');
