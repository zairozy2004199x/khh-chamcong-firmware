/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HAI CÁCH SẮP MẢNG KINH DOANH — theo tên, hoặc theo CÂY TÀI KHOẢN.
 *
 * Anh Thắng 12/09/2026, chỉ vào bảng "🔢 TK Nợ · KVC — 7 mảng": *"Chỗ mảng kinh doanh, tạo cho
 * anh 2 cách sắp xếp: sắp xếp theo số tk 641-6411-6412-6412, 642-6421-6422-6423 · Sắp xếp theo
 * mảng"*.
 *
 * =============================================================================================
 * 🔴 SO CHUỖI, KHÔNG SO SỐ — cả bài này xoay quanh đúng một chỗ đó.
 *    Xếp theo CÂY thì 6411 phải đứng TRƯỚC 642 (nó là con của 641). So bằng giá trị SỐ thì
 *    642 < 6411, nên 642 nhảy lên trước và cắt đôi cây 641 — đúng thứ anh Thắng bảo là không
 *    muốn. So CHUỖI ra đúng ngay, vì chuỗi ngắn mà là tiền tố thì đứng trước.
 *    Phần 1 canh thẳng vào đó, và nó là phép quan trọng nhất của cả tệp.
 *
 * 🔴 MỘT MẢNG CÓ NHIỀU MÃ nên phải chọn ra một mã làm mốc — lấy mã NHỎ NHẤT theo cây.
 *
 * ⚠️ CHẠY THẬT `_mxMaGoc()` / `_mxSapCols()` bốc từ app.html, trên đúng dữ liệu trong ảnh
 *    anh Thắng gửi (7 mảng KVC).
 *
 * Chạy: node tools/test/kiem-sap-mang-theo-tk.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC  = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n  }', i);
  return HTML.slice(i, j + 4);
}
/* `MX_SAP` là biến ngoài hàm và đọc localStorage — bệ thử thay bằng một biến thường để đổi
   được chế độ, chứ không giả localStorage: bài kiểm canh LUẬT SẮP XẾP, không canh chỗ nhớ. */
const F = new Function('MX_SAP_INIT', 'CFG',
  'var MX_SAP=MX_SAP_INIT;' + boc('_mangTong') + boc('_mangTongDoan') + boc('_mxMaGoc') + boc('_mxSapCols')
  + '; return { maGoc:_mxMaGoc, sap:_mxSapCols, tong:_mangTong, doan:_mangTongDoan };');

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 THỨ TỰ CÂY — chỗ so số sẽ sai
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const A = F('tk', { mangTk: [] });
/* Mỗi "mảng" ở đây chỉ khai đúng một mã, để phép so thứ tự không bị mã khác chen vào. */
function xepTheoMa(danh) {
  const cols = Object.keys(danh);
  const rows = [{ ten: 'L' }];
  const mx = {}; cols.forEach(c => { mx['L|' + c] = danh[c]; });
  return A.sap(cols, rows, mx);
}
teq('🔴 641 → 6411 → 6412 → 642 → 6421 (so SỐ sẽ đẩy 642 lên trước 6411)',
  ['a641', 'b6411', 'c6412', 'd642', 'e6421'],
  xepTheoMa({ d642: '642', b6411: '6411', e6421: '6421', a641: '641', c6412: '6412' }));
teq('   cấp 3 nằm trong cây cấp 2',
  ['x641', 'y6411', 'z64111', 'w642'],
  xepTheoMa({ w642: '642', z64111: '64111', x641: '641', y6411: '6411' }));
/* Đối chứng: nếu ai đó đổi sang so SỐ thì thứ tự ra thế này — giữ lại để thấy rõ khác biệt. */
const soSanh = ['642', '6411'].slice().sort((a, b) => Number(a) - Number(b));
teq('   (đối chứng) so bằng SỐ cho ra thứ tự SAI', ['642', '6411'], soSanh);

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 2. DỮ LIỆU THẬT — 7 mảng KVC trong ảnh anh Thắng gửi
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const ROWS = ['Chi phí cơ sở', 'Chi phí tháo dỡ', 'Chi phí NVL đồ ăn - mua lẻ',
  'Chi phí NVL đồ uống - mua lẻ', 'Chi phí marketing', 'Chi phí nuôi thú',
  'Chi phí hoạt náo', 'Chi phí setup', 'Chi phí MKT - hoạt náo'].map(ten => ({ ten }));
const ANH = {
  'EVENT FZ MN':    ['64196', '64125', '', '', '', '', '', '64125', ''],
  'EVENT GHOST MN': ['64196', '64125', '6329', '6329', '64196', '', '64196', '64125', '64196'],
  'EVENT SNOW MN':  ['64196', '64125', '6329', '6329', '64196', '', '64196', '64125', '64196'],
  'EVENT VR MN':    ['64196', '64125', '6329', '6329', '64196', '', '64196', '64125', '64196'],
  'FARM MN':        ['64166', '64125', '6326', '6326', '64166', '64168', '64166', '64125', '64166'],
  'FZ MN':          ['64126', '64125', '6322', '6322', '64126', '', '64126', '64125', '64126'],
  'TUTU MN':        ['64106', '64105', '6320', '6320', '64106', '', '64106', '64105', '64106'],
};
const COLS = Object.keys(ANH);
const MX = {};
COLS.forEach(c => ROWS.forEach((r, i) => { MX[r.ten + '|' + c] = ANH[c][i]; }));

teq('mã gốc của TUTU MN là 6320', '6320', A.maGoc('TUTU MN', ROWS, MX));
teq('mã gốc của FARM MN là 6326', '6326', A.maGoc('FARM MN', ROWS, MX));
/* EVENT FZ MN không khai ô NVL nào nên mã nhỏ nhất của nó là 64125, không phải 6329. */
teq('🔴 EVENT FZ MN — bỏ trống ô NVL nên mã gốc là 64125', '64125', A.maGoc('EVENT FZ MN', ROWS, MX));

teq('🔴 xếp theo số tài khoản trên dữ liệu thật',
  ['TUTU MN', 'FZ MN', 'FARM MN', 'EVENT GHOST MN', 'EVENT SNOW MN', 'EVENT VR MN', 'EVENT FZ MN'],
  A.sap(COLS, ROWS, MX));
/* Ba mảng EVENT cùng mã gốc 6329 — phải giữ A→Z giữa chúng, không thì mỗi lượt vẽ một thứ tự
   khác và người đang dò dở mất dấu. */
teq('   ba mảng cùng mã gốc thì giữ A→Z',
  ['EVENT GHOST MN', 'EVENT SNOW MN', 'EVENT VR MN'],
  A.sap(COLS, ROWS, MX).filter(x => x.indexOf('EVENT') === 0 && x !== 'EVENT FZ MN'));

const B = F('mang', { mangTk: [] });
teq('🔴 xếp theo mảng vẫn là A→Z như cũ', COLS.slice().sort(), B.sap(COLS, ROWS, MX));

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 3. CÁC CA LỆCH
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ Mảng chưa khai mã nào phải xuống CUỐI. Chuỗi rỗng so ra nhỏ nhất, nên không chặn là mấy
   hàng trống chiếm sạch đầu bảng — đúng chỗ người ta nhìn trước nhất. */
const cols2 = ['Chưa khai', 'Có mã'];
const mx2 = { 'L|Có mã': '6412', 'L|Chưa khai': '' };
teq('🔴 mảng chưa khai mã nào nằm CUỐI', ['Có mã', 'Chưa khai'], A.sap(cols2, [{ ten: 'L' }], mx2));
teq('   cả hai đều chưa khai thì về A→Z', ['A', 'B'],
  A.sap(['B', 'A'], [{ ten: 'L' }], {}));

/* Một ô khai NHIỀU mã, cách nhau bởi "|" — phải tách rồi mới so, không thì cả chuỗi
   "64196 | 64197" đem đi so như một mã và xếp sai chỗ. */
teq('🔴 ô nhiều mã "64196 | 6412" lấy mã nhỏ nhất là 6412',
  '6412', A.maGoc('M', [{ ten: 'L' }], { 'L|M': '64196 | 6412' }));

/* Đuôi ".0" của bảng tính: "6412.0" vẫn là mã 6412. */
teq('🔴 đuôi ".0" được dọn trước khi so', '6412',
  A.maGoc('M', [{ ten: 'L' }], { 'L|M': '6412.0' }));
teq('   và không lẫn với mã 64120', '6412',
  A.maGoc('M', [{ ten: 'L' }], { 'L|M': '64120 | 6412.0' }));

teq('mảng chưa khai gì trả mã gốc rỗng', '', A.maGoc('M', [{ ten: 'L' }], {}));
teq('danh sách rỗng thì không nổ', [], A.sap([], ROWS, MX));

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 3b. MÃ TỔNG CỦA MẢNG — "TUTU MN (6410)"
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 12/09/2026: *"Chỗ mảng mình sẽ mở ngoặc ra. Tức kiểu nó là số tổng. VD TUTU MN
 * (6410) Cơ sở 64106…"*, *"Còn FZ MN (6412)"*.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const C = F('tk', { mangTk: [
  { pll: 'TUTU MN', nhomTk: '6410' },
  { pll: 'FZ MN',   nhomTk: '6412' },
] });
teq('🔴 đọc mã tổng đã khai', '6410', C.tong('TUTU MN'));
teq('   không phân biệt hoa thường', '6412', C.tong('fz mn'));
teq('   mảng chưa khai thì rỗng', '', C.tong('FARM MN'));

/* 🔴 ĐOÁN THEO CỘT TRÁI NHẤT CÓ MÃ 5 CHỮ SỐ. Anh Thắng: *"đối với loại chi phí setup và tháo
   dỡ thì nó là chi phí CHUNG nên không theo quy luật"* — 64125 nằm ở cả FZ, FARM lẫn EVENT.
   Đếm số lần thì mã chung ấy thắng ở mảng khai ít ô, và EVENT FZ MN (chỉ 3 ô: 64196 · 64125 ·
   64125) đoán ra 6412 thay vì 6419. Phép dưới canh đúng ca ấy — nó là lý do luật này tồn tại. */
teq('   đoán cho TUTU MN',  '6410', C.doan('TUTU MN', ROWS, MX));
teq('   đoán cho FARM MN',  '6416', C.doan('FARM MN', ROWS, MX));
teq('🔴 đoán ĐÚNG cho EVENT FZ MN dù nó khai ít ô', '6419', C.doan('EVENT FZ MN', ROWS, MX));
/* Mã 4 chữ số (6320 · 6322) là hệ khác — cắt ra "632" là một cấp chẳng nói lên mảng nào. */
teq('🔴 bỏ qua mã 4 chữ số, không cắt bừa', '',
  C.doan('M', [{ ten: 'L' }], { 'L|M': '6320' }));
teq('   mảng trống thì không đoán nổi', '', C.doan('M', [{ ten: 'L' }], {}));

/* 🔴 XẾP THEO SỐ TK: MÃ TỔNG THẮNG mã nhỏ nhất. Đây chính là chỗ kéo EVENT FZ MN về đúng chỗ
   với ba mảng EVENT kia — mã nhỏ nhất của nó là 64125 (chi phí chung) nên nó rơi xuống cuối. */
const D = F('tk', { mangTk: COLS.map(c => ({ pll: c, nhomTk: c.indexOf('EVENT') === 0 ? '6419'
  : (c === 'FARM MN' ? '6416' : (c === 'FZ MN' ? '6412' : '6410')) })) });
teq('🔴 khai mã tổng xong, EVENT FZ MN về đúng chỗ với anh em nó',
  ['TUTU MN', 'FZ MN', 'FARM MN', 'EVENT FZ MN', 'EVENT GHOST MN', 'EVENT SNOW MN', 'EVENT VR MN'],
  D.sap(COLS, ROWS, MX));
/* Mảng chưa khai tổng vẫn lui về mã nhỏ nhất, không bị đẩy xuống cuối như mảng trắng trơn.
   ⚠️ Dữ liệu chọn sao cho HAI CÁCH RA HAI KẾT QUẢ KHÁC NHAU, không thì phép xanh cả khi mã
      tổng bị bỏ qua hoàn toàn: FZ MN khai tổng 6300 (nhỏ hơn mã nhỏ nhất 6322 của chính nó),
      còn TUTU MN không khai nên lui về 6320. Có dùng mã tổng → FZ trước; không dùng → TUTU
      trước, vì 6320 < 6322. */
const E = F('tk', { mangTk: [{ pll: 'FZ MN', nhomTk: '6300' }] });
teq('🔴 mã tổng của FZ thắng, còn TUTU lui về mã nhỏ nhất 6320',
  ['FZ MN', 'TUTU MN'], E.sap(['TUTU MN', 'FZ MN'], ROWS, MX));
teq('   (đối chứng) không ai khai tổng thì TUTU lên trước',
  ['TUTU MN', 'FZ MN'], A.sap(['TUTU MN', 'FZ MN'], ROWS, MX));

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 HAI NÚT PHẢI BẤM ĐƯỢC — CÁI KHÓA CỦA BẢNG MÃ KHÔNG ĐƯỢC NUỐT CHÚNG
 * ══════════════════════════════════════════════════════════════════════════════════════════
 * Cắn thật 12/09/2026, ngay bản đầu: anh Thắng bấm "Theo số tài khoản" mà bảng không nhúc
 * nhích. Luật sắp xếp đúng cả — 18 phép ở trên đều xanh — nhưng `toggleMxLock()` quét
 * `button` trong cả khối `cfgTkNoMx` và disable sạch, kể cả hai nút mới. Mà
 * `renderTkNoMatrix()` gọi khóa NGAY SAU khi vẽ, nên chúng chết từ lượt dựng đầu tiên: nút
 * trông vẫn bình thường, bấm thì không có gì xảy ra, không một câu lỗi.
 *
 * 🔴 BÀI HỌC CHO CẢ TỆP NÀY: canh cái hàm chạy đúng là CHƯA ĐỦ khi người dùng không với tới
 *    được nó. Hai phép dưới soi đường đi từ ngón tay tới hàm, không soi hàm.
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const iKhoa = HTML.indexOf('function toggleMxLock(');
t('bốc được toggleMxLock()', iKhoa >= 0);
const KHOA = HTML.slice(iKhoa, HTML.indexOf('\n  }', iKhoa));
t('🔴 cái khóa CHỪA những nút chỉ đổi cách bày', /:not\(\[data-khong-khoa\]\)/.test(KHOA), KHOA);
/* Và hai nút ấy phải thật sự mang dấu — chừa trong hàm khóa mà quên gắn dấu thì vẫn chết.
   ⚠️ BẮT ĐÚNG THẺ `<button …>`, ĐỪNG QUÉT MỘT CỬA SỔ QUANH NÓ. Ngay trên chỗ dựng nút có một
      chú thích giải thích vì sao cần `data-khong-khoa` — quét rộng là trúng chú thích ấy, và
      phép xanh kể cả khi cái dấu đã bị gỡ khỏi nút. Đã mắc đúng lượt phá thử 12/09/2026. */
const NUT = (HTML.match(/<button[^>]*onclick="mxDatSap\(/) || [''])[0];
t('bốc được chỗ dựng hai nút sắp xếp', NUT.length > 10, NUT);
t('🔴 hai nút sắp xếp có mang dấu data-khong-khoa', /data-khong-khoa/.test(NUT), NUT);
/* Đối chứng: ô NHẬP MÃ thì vẫn phải bị khóa — chừa nhầm là mở toang bảng mã cho lỡ tay sửa. */
const iVe = HTML.indexOf('data-loai="');
const O_MA = HTML.slice(Math.max(0, iVe - 200), iVe + 200);
t('🔴 ô nhập mã KHÔNG được mang dấu ấy (vẫn phải khóa)', !/data-khong-khoa/.test(O_MA), O_MA.slice(0, 200));

/* ─────────────────────────────────────────────────────────────────────────────────────── */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — hai cách sắp mảng, thứ tự cây đúng 641 → 6411 → 642');
