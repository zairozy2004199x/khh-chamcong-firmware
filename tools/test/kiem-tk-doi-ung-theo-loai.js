/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TK ĐỐI ỨNG KHAI THEO TỪNG LOẠI CHI PHÍ.
 *
 * Anh Thắng 21/09/2026: *"MTĐ tùy loại sẽ có TK đối ứng khác"*, kèm ảnh bản MISA mẫu ghi
 * Nợ 64136 / Có **331** — không phải 141 như đường tạm ứng bên Khu vui chơi.
 *
 * =============================================================================================
 * 🔴 BA CHỖ HỎNG, KHÔNG PHẢI MỘT
 * =============================================================================================
 * 1. KHÔNG CÓ ĐƯỜNG VÀO. Cột `tkCo` của danh mục loại chi phí có trong kho từ lâu, nhưng bảng
 *    trên màn Cấu hình KHÔNG có ô nào cho nó, và dòng trợ giúp còn ghi thẳng *"TK Có app tự
 *    lấy theo hình thức chi, không phải khai"*. Tức là khai là việc không làm được.
 *
 * 2. LÚC XUẤT, HAI CỘT ĐI HAI LUẬT NGƯỢC NHAU. TK **Nợ** đọc lại từ DANH MỤC và coi mã gắn
 *    trên dòng chỉ là bản sao chụp (`tkno_xuat`), còn TK **Có** thì ngược hẳn: bản sao trên
 *    dòng thắng, danh mục không được hỏi lấy một câu. Nên khai xong, mọi dòng ĐÃ NHẬP TRƯỚC ĐÓ
 *    vẫn xuất ra mã cũ — mà đúng mấy dòng ấy mới là thứ cần sửa (67 cơ sở MTĐ nạp từ sổ cũ).
 *
 * 3. `loai_map()` KHOÁ THEO TÊN, DÒNG SAU ĐÈ DÒNG TRƯỚC. Từ 1.239.0 mỗi khối có bảng riêng nên
 *    hai khối hoàn toàn có thể cùng có "Chi phí khác". Tra không phân biệt khối là TK đối ứng
 *    của Máy tự động đè lên dòng của Khu vui chơi — im lặng, và chỉ lộ ra ở tệp MISA đã nộp.
 *
 * ⚠️ BỎ TRỐNG THÌ KHÔNG ĐỔI GÌ CẢ. Loại chưa khai ô này (gần như toàn bộ bên KVC) rơi xuống
 *    đúng hai bậc cũ. Thêm một bậc mà làm đổi mã của sổ đang chạy là sai hàng loạt bút toán đã
 *    đối chiếu xong — nên phép canh cho điều đó nằm ngay dưới đây và không được gỡ.
 *
 * Chạy: node tools/test/kiem-tk-doi-ung-theo-loai.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const CFG = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-cfg.php', 'utf8');
const MISA = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-misa.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
/* ⚠️ Gỡ chú thích CHỈ trên thân hàm đã bốc — gỡ trên cả tệp thì một dấu mở trong <style> bắt
   cặp với một dấu đóng dưới <script> và nuốt trọn thân trang (đã cắn 21/09/2026). */
function sachHam(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }

t('⚠️ bốc được `_mxRowHtml`', bocHam('_mxRowHtml').length > 300);

/* ═══ 1. CÓ ĐƯỜNG VÀO TRÊN MÀN ══════════════════════════════════════════════════ */
t('🔴 hàng loại chi phí có ô TK đối ứng', /data-o="tkco"/.test(sachHam('_mxRowHtml')), sachHam('_mxRowHtml'));
t('🔴 và bảng có đầu cột cho nó', /<th[^>]*>TK đối ứng/.test(HTML));
/* ⚠️ Ô nhập kèm gợi ý từ sơ đồ tài khoản — gõ tay một mã không có trong sổ cái là bút toán
   treo, mà lỗi ấy chỉ lộ ra lúc nhập vào MISA. */
t('⚠️ ô kèm gợi ý `tkChartList`', /data-o="tkco"[^>]*list="tkChartList"|list="tkChartList"[^>]*data-o="tkco"/.test(HTML));
/* 🔴 Dòng trợ giúp cũ nói SAI ("không phải khai") — để nguyên là người ta đọc rồi không đi tìm ô. */
t('🔴 dòng trợ giúp thôi nói "không phải khai"', !/TK Có<\/b> app tự lấy theo hình thức chi[^<]*<b>141<\/b>[^<]*<b>331<\/b>\), không phải khai/.test(HTML));
t('   và nói rõ trống = tự suy, điền = luôn dùng mã ấy', /để trống thì app tự lấy theo hình thức chi/.test(HTML));

/* ═══ 2. LƯU LẠI ĐƯỢC, VÀ KHÔNG XOÁ NHẦM ═══════════════════════════════════════ */
const LUU = HTML.slice(HTML.indexOf('var cu={}; (CFG.loaiChiPhi'), HTML.indexOf('BẢNG DƯỚI — mã TK Nợ, mỗi hàng một mảng'));
t('⚠️ bốc được đoạn đọc lúc Lưu', LUU.length > 800, LUU.length);
t('🔴 lúc Lưu có đọc ô TK đối ứng', /querySelector\('input\[data-o="tkco"\]'\)/.test(LUU), LUU.slice(0, 200));
/* 🔴 HÀNG KHÔNG CÓ Ô THÌ GIỮ NGUYÊN GIÁ TRỊ CŨ. Đọc ra rỗng rồi ghi đè là một lượt Lưu xoá
   sạch mã của mọi loại. Phép này canh đúng cái ba ngôi ấy. */
t('🔴 hàng KHÔNG có ô thì giữ nguyên mã đã lưu, không ghi rỗng đè',
  /oTkCo\s*\?\s*String\(oTkCo\.value\|\|''\)\.trim\(\)\s*:\s*\(goc\.tkCo\|\|''\)/.test(LUU), LUU.slice(-600));
/* ⚠️ Ô CÓ MẶT mà người ta xoá trắng thì rỗng là ý thật — "thôi, trả về tự suy". */
t('⚠️ nhưng ô CÓ mặt mà để trắng thì ghi rỗng (ý thật của người khai)',
  /tkCo:tkCoR/.test(LUU), LUU.slice(-400));

/* ═══ 3. PHẦN CHỐT ĐÃ CHUYỂN SANG BÀI PHP ══════════════════════════════════════
 * 🔴 BÀI NÀY CHỈ CANH ĐƯỢC "MÃ CÓ MẶT", KHÔNG CANH ĐƯỢC "MÃ CÓ CHẠY".
 * Lượt đầu em canh luật ba bậc ở đây bằng cách so THỨ TỰ CHỮ trong thân hàm PHP. Phá thử bằng
 * cách đổi `if ( '' !== $khai )` thành `if ( false )` — tức GỠ HẲN bậc quan trọng nhất, đúng
 * thứ anh Thắng yêu cầu — mà bài vẫn XANH: chữ vẫn nằm đúng chỗ, chỉ là không chạy nữa.
 * Nên luật ba bậc, phép tra theo khối và bản xuất thật nay nằm ở
 * `kiem-tk-doi-ung-theo-loai.php`, gọi thẳng hàm thật với danh mục thật.
 * Ở đây giữ đúng phần MÀN HÌNH — thứ PHP không với tới. */
t('🔴 luật ba bậc được canh bằng bài PHP chạy thật, không bằng bài này',
  require('fs').existsSync('tools/test/kiem-tk-doi-ung-theo-loai.php'));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép (phần màn hình): ô TK đối ứng có mặt, lưu được, và không xoá nhầm mã cũ.');
