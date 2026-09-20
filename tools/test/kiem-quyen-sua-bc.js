/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * QUYỀN "SỬA BÁO CÁO ĐÃ NỘP" PHẢI TÁCH KHỎI "CHỐT DOANH SỐ"
 *
 * Anh Thắng 20/09/2026: *"hoặc anh sẽ thiết lập thêm 1 tài khoản để thực hiện quyền đó"*.
 *
 * 🔴 VÌ SAO TÁCH. Nhân viên chỉ sửa được trong 24 giờ (VHG_BaoCao::GIO_SUA). Đường kế toán
 *    (`kt_sua`) KHÔNG có hạn giờ — nó đổi thẳng con số trong sổ, kể cả tháng đã chốt. Nhận tiền
 *    sai thì đếm lại ra ngay; sửa sổ sai thì không còn gì để đối chiếu ngược. Hai việc ấy nên là
 *    hai người, nên phải là hai quyền.
 *
 * 🔴 VÀ KHÔNG ĐƯỢC TỰ THU HẸP QUYỀN NGƯỜI ĐANG DÙNG. Chưa khai bao giờ thì quyền mới phải lấy y
 *    danh sách "chốt doanh số" — mặc định rỗng là sáng hôm sau kế toán không sửa được gì mà không
 *    ai hiểu vì sao.
 *
 * Chạy: node tools/test/kiem-quyen-sua-bc.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
function doc(p) {
  if (!fs.existsSync(p)) { console.error('✗ Không thấy tệp: ' + p + '\n  Chạy từ gốc kho.'); process.exit(2); }
  return fs.readFileSync(p, 'utf8');
}
const auth  = doc('vhcp-ghe/includes/class-vhg-auth.php');
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

console.log('── Quyền mới ─────────────────────────────────────────────────');
const vt = than(auth, 'vai_tro_sua_bc');
t('có vai_tro_sua_bc()', vt !== '');
t('đọc option riêng vhg_vai_tro_sua_bc', /get_option\(\s*'vhg_vai_tro_sua_bc'\s*\)/.test(vt));
t('🔴 CHƯA KHAI thì lấy y danh sách chốt doanh số (không thu hẹp quyền đang dùng)',
  /if \(\s*!\s*is_array\(\s*\$ds\s*\)\s*\)\s*\{\s*return self::vai_tro_chot\(\);/.test(vt));
t('Admin luôn nằm trong danh sách', /\$ra\s*=\s*array\(\s*'Admin'\s*\)/.test(vt));
t('chỉ nhận vai trò có thật (lọc theo VAI_TRO_TAT_CA)', /VAI_TRO_TAT_CA/.test(vt));
t('có duoc_sua_bc()', /function duoc_sua_bc\(/.test(auth));
t('quyen_cua() trả cờ sua_bc — một nguồn cho cả cổng chặn lẫn giao diện',
  /'sua_bc'\s*=>\s*self::duoc_sua_bc\(/.test(than(auth, 'quyen_cua')));

console.log('── Cổng chặn ở máy chủ ───────────────────────────────────────');
t('kt_sua bị chặn khi thiếu quyền sua_bc',
  /'kt_sua'\s*===\s*\$viec\s*&&\s*empty\(\s*\$q\['sua_bc'\]\s*\)/.test(trang));
t('… và chặn TRƯỚC khi gọi VHG_KeToan::sua',
  trang.indexOf("empty( $q['sua_bc'] )") < trang.indexOf('VHG_KeToan::sua('));
t('vẫn giữ cổng chung cho mọi việc kt_ (vào được trang kế toán)',
  /empty\(\s*\$q\['quan_tri'\]\s*\)\s*&&\s*empty\(\s*\$q\['chot_doanh_so'\]\s*\)/.test(trang));

console.log('── Khai được ở tab Cấu hình ──────────────────────────────────');
t('trả danh sách hiện tại ra màn hình', /'suabc'\s*=>\s*VHG_Auth::vai_tro_sua_bc\(\)/.test(trang));
t('lưu vào đúng option', /update_option\(\s*'vhg_vai_tro_sua_bc'\s*,\s*\$suabc\s*\)/.test(trang));
t('Admin được nhét lại khi lưu (ô Admin disabled nên trình duyệt không gửi)',
  /\$suabc\s*=\s*\$them_admin\(\s*\$suabc\s*\)/.test(trang));
t('có ô tích trong bảng phân quyền', /\['suabc',\s*L\(/.test(trang));
t('nút Lưu gom đúng khoá suabc', /var g = \{ vao: \[\], giup: \[\], chot: \[\], suabc: \[\], quantri: \[\] \}/.test(trang));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
