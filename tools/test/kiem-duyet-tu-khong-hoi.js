/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DUYỆT TẠM ỨNG TỪ BẢNG — BẤM LÀ XONG, KHÔNG HỎI SỐ TIỀN.
 *
 * Anh Thắng 13/09/2026: *"bấm duyệt không cần hỏi cái này nhé"* — kèm ảnh hộp `prompt` "DUYỆT
 * TẠM ỨNG — số tiền tạm ứng cấp cho NV (sửa lại nếu chưa hợp lý)" với sẵn con số 4240000, đúng
 * bằng số tạm ứng của chính đơn ấy.
 *
 * 🔴 CON SỐ ẤY VỐN ĐÃ LÀ SỐ CỦA ĐƠN. Hộp hỏi chỉ điền sẵn `d.tamUng` rồi chờ người ta bấm OK —
 *    một nhịp thừa đứng giữa cái nút và việc nó làm, mà bảng này duyệt cả chục đơn một lượt.
 *
 * ⚠️ NHƯNG SỐ VẪN PHẢI ĐƯỢC GỬI LÊN, VÀ PHẢI ĐÚNG SỐ CỦA ĐƠN. Bỏ hộp hỏi mà gửi rỗng là máy chủ
 *    nhận `amt=''` — tạm ứng về 0, đơn duyệt xong mà không ai được cấp đồng nào. Phần 2 canh
 *    đúng chỗ đó: đây là chỗ một bản "đơn giản hoá" dễ làm hỏng nhất.
 *
 * ⚠️ VÀ ĐƯỜNG SỬA SỐ PHẢI CÒN. Ca cần sửa là ca hiếm, nhưng có thật; nó nằm ở màn đơn (ô
 *    `tuDuyetInp` cạnh nút Duyệt). Bỏ luôn cả đường ấy là lấy mất một khả năng, không phải
 *    bỏ một nhịp thừa.
 *
 * Chạy: node tools/test/kiem-duyet-tu-khong-hoi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
/* Gỡ chú thích KIỂU JS — chỉ dùng cho THÂN HÀM đã bốc ra, không bao giờ cho cả tệp (trong phần
   thân trang có thuộc tính `accept` mang giá trị "image/" kèm dấu sao, gỡ kiểu JS trên cả tệp
   là nó nuốt trắng hàng trăm dòng HTML ở giữa). */
const boChuThich = x => String(x).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');

function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được ' + ten + ' — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n  }', i);
  return HTML.slice(i, j + 4);
}

/* ═══ 1. KHÔNG CÒN HỎI ═══════════════════════════════════════════════════════════ */
const DTU = boChuThich(boc('doDuyetTU'));
t('🔴 doDuyetTU KHÔNG còn hộp prompt hỏi số tiền', !/prompt\s*\(/.test(DTU), DTU.slice(0, 400));
/* ⚠️ Và cũng KHÔNG đổi sang confirm(): anh Thắng bảo bấm duyệt là xong, đổi một hộp hỏi lấy
   một hộp hỏi khác thì chẳng khác gì. */
t('🔴 và cũng không thay bằng confirm()', !/confirm\s*\(/.test(DTU), DTU.slice(0, 400));

/* ═══ 2. 🔴 NHƯNG SỐ VẪN PHẢI GỬI LÊN, VÀ ĐÚNG SỐ CỦA ĐƠN ═══════════════════════
 * Đây là phép quan trọng nhất của bài. Gửi rỗng thì máy chủ ghi tạm ứng = 0: đơn duyệt xong
 * mà không ai được cấp đồng nào, và màn hình vẫn báo "Đã duyệt tạm ứng".
 * ═════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ `[^)]*` KHÔNG DÙNG ĐƯỢC Ở ĐÂY: tham số giữa là `(CURUSER&&CURUSER.name)||''`, có dấu
   đóng ngoặc bên trong, nên lớp phủ định ấy dừng sớm và phép đỏ oan trong khi mã vẫn đúng. */
t('🔴 vẫn gọi duyetTamUng với tham số số tiền',
  /\.duyetTamUng\(\s*m\s*,[\s\S]*?,\s*amt\s*\)/.test(DTU), DTU.slice(-260));
t('🔴 và số ấy LẤY TỪ ĐƠN (d.tamUng), không phải rỗng hay 0 cứng',
  /amt\s*=\s*d\s*\?\s*d\.tamUng/.test(DTU), DTU.slice(0, 400));
t('   đơn tra đúng theo mã đang bấm', /x\.maDon\s*===\s*m/.test(DTU), null);

/* ═══ 3. ĐƯỜNG SỬA SỐ VẪN CÒN Ở MÀN ĐƠN ═════════════════════════════════════════ */
t('🔴 màn đơn vẫn có ô nhập số tạm ứng khi duyệt', /id="tuDuyetInp"/.test(HTML), null);
const DCUR = boChuThich(boc('doDuyetCur'));
t('   và nút Duyệt ở màn đơn ĐỌC ô ấy', /tuDuyetInp/.test(DCUR), null);
/* Ở màn đơn thì HỎI LẠI là đúng: người ta vừa gõ tay một con số, hỏi lại là cơ hội cuối để
   thấy mình gõ nhầm. Khác hẳn bảng, nơi con số không do ai gõ cả. */
t('   ở màn đơn thì VẪN hỏi lại (vì số vừa gõ tay)', /confirm\s*\(/.test(DCUR), null);

/* ═══ 4. DUYỆT HÀNG LOẠT VỐN ĐÃ KHÔNG HỎI — GIỮ NGUYÊN ══════════════════════════ */
t('⚠️ duyệt nhiều đơn một lượt không truyền số tiền nào (máy chủ tự lấy của từng đơn)',
  /\.duyetTamUngNhieu\(\s*ms\s*,[^)]*\)/.test(boChuThich(HTML.slice(HTML.indexOf('duyetTamUngNhieu') - 600, HTML.indexOf('duyetTamUngNhieu') + 200))), null);

/* ═══ KẾT ═══════════════════════════════════════════════════════════════════════ */
console.log('');
if (TRUOT.length) {
  console.log('TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  ✗ ' + x));
  process.exit(1);
}
console.log('ĐẠT: ' + DAT + ' phép thử');
console.log('Tất cả phép thử đều đạt.');
