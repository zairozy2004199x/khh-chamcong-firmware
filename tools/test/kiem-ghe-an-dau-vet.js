/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GHẾ BỊ GIẤU PHẢI ĐỂ LẠI DẤU VẾT, VÀ CHUYỂN CƠ SỞ PHẢI GỠ CỜ ẨN
 *
 * Gốc (anh Thắng 18/09/2026): ghế 80111 mang cờ `an` từ 13/09. Cơ sở CGV Vincom Xuân Khánh có 2
 * ghế nhưng báo cáo 17/09 chỉ có 1 — màn nhập không vẽ dòng nào cho ghế ẩn và KHÔNG NÓI GÌ, nên
 * người nộp thấy một bảng "đủ" và ký. Mất 5 ngày mới lần ra, vì không chỗ nào ghi ai bật cờ ẩn.
 *
 * 🔴 BA LUẬT BÀI NÀY CANH, mỗi luật là một lần đã mất tiền hoặc mất mấy ngày đi dò:
 *   1. Mọi lệnh ghi cột `an` phải ghi kèm `an_luc` + `an_ai` — dấu vết nằm trên chính dòng ghế,
 *      không nằm ở bảng `nhat_ky` (bảng ấy chỉ giữ 500 dòng, log tiền vào đẩy trôi trong ngày).
 *   2. `dat_coso` / `dat_coso_lo` phải đặt `an=0`: chuyển ghế về một cơ sở là để DÙNG nó ở đó.
 *      Trước đây đổi Địa điểm không gỡ cờ ẩn, ghế sang cơ sở mới vẫn vô hình với nhân viên.
 *   3. Màn nhập phải NÓI RA số ghế đang bị giấu (`gheAn` → dải cảnh báo), vì thứ nguy hiểm không
 *      phải cái bảng thiếu dòng, mà là cái bảng thiếu dòng trông y như bảng đủ.
 *
 * Chạy: node tools/test/kiem-ghe-an-dau-vet.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
function doc(p) {
  if (!fs.existsSync(p)) { console.error('✗ Không thấy tệp: ' + p + '\n  Chạy từ gốc kho.'); process.exit(2); }
  return fs.readFileSync(p, 'utf8');
}
const may    = doc('vhcp-ghe/includes/class-vhg-may.php');
const db     = doc('vhcp-ghe/includes/class-vhg-db.php');
const baocao = doc('vhcp-ghe/includes/class-vhg-baocao.php');
const trang  = doc('vhcp-ghe/includes/class-vhg-trang.php');

let hong = 0;
function t(ten, ok, them) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them)));
  if (!ok) hong++;
}
/* Thân một hàm PHP (đếm ngoặc từ dấu { đầu tiên). */
function than(src, ten) {
  const i = src.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let d = 0;
  for (let k = src.indexOf('{', i); k < src.length; k++) {
    if (src[k] === '{') d++;
    else if (src[k] === '}' && --d === 0) return src.slice(i, k + 1);
  }
  return '';
}

console.log('── 1. Dấu vết nằm trên dòng ghế ───────────────────────────────');
t('bảng `may` có cột an_luc', /an_luc\s+DATETIME/.test(db));
t('bảng `may` có cột an_ai', /an_ai\s+VARCHAR/.test(db));
['dat_an', 'dat_an_lo', 'xoa_may'].forEach(function (fn) {
  const b = than(may, fn);
  t(fn + '() ghi kèm an_luc + an_ai', b !== '' && /an_luc/.test(b) && /an_ai/.test(b));
});
['dat_an', 'dat_an_lo', 'xoa_may', 'dat_coso', 'dat_coso_lo'].forEach(function (fn) {
  t(fn + '() nhận tham số "ai bấm"', new RegExp('function\\s+' + fn + '\\([^)]*\\$ai').test(may));
});
t('router truyền tên người bấm vào các lệnh ẩn/xoá/đổi cơ sở',
  (trang.match(/VHG_May::(dat_an|dat_an_lo|xoa_may|dat_coso|dat_coso_lo)\([^;]*\$ai\['name'\]/g) || []).length >= 5);

console.log('── 2. Chuyển cơ sở thì gỡ cờ ẩn ───────────────────────────────');
['dat_coso', 'dat_coso_lo'].forEach(function (fn) {
  const b = than(may, fn);
  t(fn + "() đặt an=0 khi chuyển ghế", b !== '' && /'an'\s*=>\s*0|SET[^"]*an=0/.test(b));
});

console.log('── 3. Màn nhập phải nói ra ghế đang bị giấu ───────────────────');
t('ds_ghe() gom ghế bị giấu ra tham chiếu $an_bo', /function ds_ghe\([^)]*&\$an_bo/.test(baocao));
t('ghế bị bỏ được ghi vào $an_bo trước khi continue', /\$an_bo\[\]\s*=\s*array\(/.test(baocao));
t('boot() gửi gheAn cho màn nhập', /'gheAn'\s*=>/.test(baocao));
t('màn nhập dựng dải cảnh báo từ BC.gheAn', /BC\.gheAn/.test(trang) && /bc-nhac-ghean/.test(trang));
t('dải cảnh báo được gọi mỗi lần chọn cơ sở', /veNhacGheAn\(\s*neo\s*,\s*loc\s*\)/.test(trang));
t('màn "Tìm ghế" hiện dấu vết ai ẩn / lúc nào', /m\.anLuc/.test(trang) && /m\.anAi/.test(trang));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
