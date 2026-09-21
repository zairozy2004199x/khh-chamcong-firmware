/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN KẾ TOÁN PHẢI THÊM / SỬA ĐƯỢC ẢNH NGAY TRONG Ô SỬA
 *
 * Anh Thắng 20/09/2026: *"chỗ sửa này đang không thấy sửa, thêm ảnh, sửa ảnh"*.
 *
 * 🔴 GỐC. Chính tab Duyệt báo cáo in "6 ghế thiếu ảnh" mà ô sửa lại chỉ có mấy ô số — báo thiếu
 *    rồi đẩy người ta sang màn Sửa 24h của nhân viên, nơi đã quá hạn từ lâu. Tức là hệ nói ra
 *    một việc phải làm rồi bịt luôn đường làm việc ấy.
 *
 * 🔴 BA LUẬT BÀI NÀY CANH:
 *   1. Một đường lưu ảnh duy nhất — kế toán đi qua VHG_BaoCao::luu_anh(), không tự dựng bản sao
 *      (hai chỗ đặt tên tệp / chọn thư mục là ảnh hai màn nằm hai nơi).
 *   2. Xoá ảnh phải hoàn tác được — ảnh cũ nằm trong ảnh chụp undo. Mất một ô số còn sửa tay
 *      được; mất chứng từ thì không.
 *   3. Một bộ luật nén ảnh duy nhất — khối JS kế toán dùng lại `window.VHG_NEN_ANH` của khối
 *      nhân viên, không chép cạnh dài/chất lượng sang bản thứ hai.
 *
 * Chạy: node tools/test/kiem-kt-sua-anh.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
function doc(p) {
  if (!fs.existsSync(p)) { console.error('✗ Không thấy tệp: ' + p + '\n  Chạy từ gốc kho.'); process.exit(2); }
  return fs.readFileSync(p, 'utf8');
}
const kt    = doc('vhcp-ghe/includes/class-vhg-ketoan.php');
const bc    = doc('vhcp-ghe/includes/class-vhg-baocao.php');
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
const sua = than(kt, 'sua');

console.log('── Máy chủ: kế toán thêm / xoá được ảnh ──────────────────────');
t('nhận ảnh mới qua patch.images (chiso · vesinh · qr)',
  /\$patch\['images'\]/.test(sua) && /'chiso', 'vesinh', 'qr'/.test(sua));
t('đi qua ĐƯỜNG LƯU ẢNH CHUNG của nhân viên, không tự dựng bản sao',
  /VHG_BaoCao::luu_anh\(/.test(sua) && /public static function luu_anh\(/.test(bc));
t('nhận danh sách ảnh bỏ đi qua patch.anhXoa', /\$patch\['anhXoa'\]/.test(sua));
t('chỉ ghi lại cột ảnh KHI có đụng tới (không ghi đè vu vơ)',
  /if \(\s*\$anh_doi\s*\)\s*\{\s*\$data_up\['anh'\]/.test(sua));
t('🔴 ảnh cũ nằm trong ảnh chụp hoàn tác (xoá nhầm lấy lại được)',
  /'anh' => isset\( \$d\['anh'\] \)/.test(sua)
  && sua.indexOf("'anh' => isset( $d['anh'] )") < sua.indexOf('$anh_doi = false'));

console.log('── Màn hình: ô sửa có ảnh ────────────────────────────────────');
t('hiện ảnh đang có của ghế', /\(c\.anh\|\|\[\]\)\.forEach/.test(trang));
t('có nút bỏ ảnh, bấm lại là hoàn tác trước khi Lưu',
  /anhXoa\.indexOf\(u\)/.test(trang) && /anhXoa\.splice/.test(trang));
t('có ba nút thêm ảnh: Chỉ số · Vệ sinh · QR',
  /\['chiso',L\('Chỉ số'/.test(trang) && /\['vesinh',L\('Vệ sinh'/.test(trang) && /\['qr','QR'\]/.test(trang));
t('gửi kèm images / anhXoa khi lưu', /patch\.images\s*=\s*anhMoi/.test(trang) && /patch\.anhXoa\s*=\s*anhXoa/.test(trang));
t('chỉ gửi ảnh khi CÓ đụng tới', /var coAnhMoi\s*=/.test(trang) && /if\(coAnhMoi\)/.test(trang));
t('dùng chung bộ nén ảnh của khối nhân viên (một bộ luật)',
  /window\.VHG_NEN_ANH\s*=\s*nenAnh_/.test(trang) && /var nen\s*=\s*window\.VHG_NEN_ANH/.test(trang));
t('thiếu bộ nén thì vẫn đính được ảnh (đọc thô, không câm lặng)',
  /new FileReader\(\)[\s\S]{0,200}anhMoi\[k\[0\]\]/.test(trang));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
