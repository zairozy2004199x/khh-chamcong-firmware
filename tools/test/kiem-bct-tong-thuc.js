/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO TỔNG — CỘT TỔNG PHẢI LÀ TIỀN THẬT
 *
 * Anh Thắng 18/09/2026: *"Tổng phải là thực thu để xác định tiền, còn chỉ số trên máy nó đâu phải
 * doanh thu, vì chỉ số trên máy nó còn sai số"* + *"QR là QR thực về ngân hàng, còn con số QR nhân
 * viên nhập chỉ là đối chiếu thôi"*.
 *
 * 🔴 BỐN LUẬT, mỗi cái hỏng một kiểu:
 *   1. Vế tiền mặt lấy `tien_mat` (thực thu), KHÔNG lấy `tong` — `tong` đã gồm QR nhân viên khai,
 *      cộng thêm QR bank nữa là ĐẾM TIỀN HAI LẦN.
 *   2. Tiền bank cộng vào `$o` TRƯỚC khi dựng danh sách cơ sở, để cơ sở có tiền về mà chưa ai nộp
 *      báo cáo vẫn ra một dòng (không thì tiền thật biến mất khỏi bảng).
 *   3. CHỈ mức cơ sở. Sao kê không quy được tiền về từng ghế; chia bừa rồi gọi là tiền thật thì
 *      tệ hơn là nói thẳng "mức này là số nhân viên khai".
 *   4. Màn hình phải NÓI cột TỔNG đang là tiền gì, và nói phần tiền bank chưa quy được cơ sở —
 *      đổi cách tính một cột tiền mà im lặng thì người đọc so với bản in hôm qua và không biết
 *      bên nào sai.
 *
 * Chạy: node tools/test/kiem-bct-tong-thuc.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
function doc(p) {
  if (!fs.existsSync(p)) { console.error('✗ Không thấy tệp: ' + p + '\n  Chạy từ gốc kho.'); process.exit(2); }
  return fs.readFileSync(p, 'utf8');
}
const kt    = doc('vhcp-ghe/includes/class-vhg-ketoan.php');
const trang = doc('vhcp-ghe/includes/class-vhg-trang.php');
let hong = 0;
function t(ten, ok, them) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them)));
  if (!ok) hong++;
}
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
const b = than(kt, 'bao_cao_tong');
t('tìm thấy bao_cao_tong()', b !== '');

console.log('── Công thức ──────────────────────────────────────────────────');
t('cờ $tong_thuc chỉ bật khi cot=tong VÀ muc=coso VÀ có sao kê',
  /\$tong_thuc\s*=\s*\(\s*'tong'\s*===\s*\$cot\s*&&\s*'coso'\s*===\s*\$muc\s*&&[^;]*\$vqd\['co'\]/.test(b));
t('vế tiền mặt lấy tien_mat khi tổng thực (không lấy `tong`)',
  /\$r\[\s*\$tong_thuc\s*\?\s*'tien_mat'\s*:\s*\$cot\s*\]/.test(b));
t('cộng VietQR thực vào ô theo cơ sở + ngày', /if\s*\(\s*\$tong_thuc\s*\)\s*\{[\s\S]{0,400}\$vqd\['vq'\]/.test(b));
t('chỉ cộng ngày nằm trong khoảng đang xem', /in_array\(\s*\$ng_vq\s*,\s*\$ds_ngay\s*,\s*true\s*\)/.test(b));
t('cộng TRƯỚC khi dựng danh sách cơ sở (cơ sở chỉ có tiền bank vẫn ra dòng)',
  b.indexOf("$vqd['vq']") > 0 && b.indexOf("$vqd['vq']") < b.indexOf('$ds_cs = array_keys'));
t('vietqr_thuc_() gọi ĐÚNG MỘT lần (không tính hai lượt)',
  (b.match(/self::vietqr_thuc_\(/g) || []).length === 1, (b.match(/self::vietqr_thuc_\(/g) || []).length);
t('trả cờ tongThuc cho màn hình', /'tongThuc'\s*=>/.test(b));

console.log('── Màn hình phải nói ra ───────────────────────────────────────');
t('in công thức khi TỔNG là tiền thật', /r\.tongThuc/.test(trang) && /thực thu tiền mặt \+ VietQR thực/.test(trang));
/* 🔴 ĐẢO LẠI TỪ 2.114.0 — anh Thắng 19/09/2026: *"Dữ liệu QR từ nhiều nguồn mà, nếu không biết
   thì bỏ qua"*. Tài khoản nhận VietQR không chỉ có ghế; phần không khớp đo trên host ra 725 TỶ
   trong khi cả bảng ghế kỳ ấy là 1,24 tỷ. Báo nó lên là báo tiền của mảng khác. Bài kiểm nay canh
   chiều NGƯỢC: không được có dải cảnh báo ấy nữa. */
t('KHÔNG báo "tiền chưa quy được cơ sở" (tiền nhiều nguồn, không phải của ghế)',
  !/CHƯA quy được về cơ sở/.test(trang) && !/Chưa quy được cơ sở/.test(trang));
t('nói rõ mức Từng ghế là số nhân viên khai', /QR NHÂN VIÊN KHAI/.test(trang));
t('nói rõ khi chưa đọc được sao kê', /Chưa đọc được sao kê/.test(trang));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
