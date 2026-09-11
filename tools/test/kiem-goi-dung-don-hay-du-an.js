/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GỌI ĐÚNG TÊN: ĐƠN CHI PHÍ CƠ SỞ KHÔNG PHẢI DỰ ÁN, VÀ NÓ KHÔNG CÓ "THỜI GIAN SETUP"
 *
 * Anh Thắng 11/09/2026, nhìn khối duyệt lệnh của một đơn chi phí cơ sở theo tuần:
 *   *"Lệnh tạm ứng theo chi phí kỹ thuật chứ"*
 *   *"Dự án tuần thì nó đâu có thười gian setup"*
 *
 * =============================================================================================
 * 🔴 CÙNG MỘT CẶP NGÀY, HAI NGHĨA KHÁC HẲN. Với dự án Setup/Tháo dỡ, cặp ngày ấy là thời gian
 *    thi công. Với đơn chi phí cơ sở, nó là TUẦN của đơn. Một tiêu đề cột không nói được hai
 *    nghĩa — nên nghĩa phải đi theo từng hàng.
 *
 * 🔴 CỜ ĐI TỪ MÁY CHỦ, MÀN KHÔNG TỰ SO CHUỖI. `VHCP_DuAn::la_don_coso()` là nơi duy nhất biết
 *    loại nào là đơn cơ sở; màn chỉ đọc `isCoSo`. So chuỗi ở màn là nơi thứ hai phải sửa mỗi
 *    lần đổi tên loại, và nơi thứ hai thì sớm muộn lệch.
 *
 * Chạy: node tools/test/kiem-goi-dung-don-hay-du-an.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const PHP = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-duan.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. KHOẢNG NGÀY TỰ NÓI NÓ LÀ KHOẢNG GÌ — bốc hàm thật ra chạy
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnNgay = bocHam('_hmNgay');
t('bốc được _hmNgay()', fnNgay.length > 80, fnNgay.length);
const F = new Function('esc', '_dmy', fnNgay + '\nreturn _hmNgay;')(
  function (x) { return String(x == null ? '' : x); },
  function (v) { const p = String(v).split('-'); return p.length === 3 ? (p[2] + '/' + p[1] + '/' + p[0]) : v; });

const KY = { tu: '2026-09-07', den: '2026-09-13' };
const raDuAn = F(KY, false);
const raDon  = F(KY, true);
t('cả hai đều hiện đủ khoảng ngày · dự án', raDuAn.indexOf('07/09/2026') >= 0 && raDuAn.indexOf('13/09/2026') >= 0, raDuAn);
t('                              · đơn',    raDon.indexOf('07/09/2026') >= 0 && raDon.indexOf('13/09/2026') >= 0, raDon);
t('🔴 dự án Setup/Tháo dỡ: gọi là thời gian setup', raDuAn.indexOf('thời gian setup') >= 0, raDuAn);
t('🔴 dự án KHÔNG bị gọi nhầm là tuần',             raDuAn.indexOf('tuần') < 0, raDuAn);
t('🔴 đơn chi phí cơ sở: gọi là tuần của đơn',      raDon.indexOf('tuần của đơn') >= 0, raDon);
t('🔴 đơn KHÔNG bị gọi nhầm là setup',              raDon.indexOf('setup') < 0, raDon);
/* Chưa gõ ngày thì phải kêu đỏ như cũ — đừng để lời nhãn mới nuốt mất cảnh báo cũ. */
t('chưa gõ ngày thì vẫn kêu đỏ', F({}, true).indexOf('chưa gõ') >= 0, F({}, true));
t('   và không kèm nhãn loại',   F({}, true).indexOf('tuần của đơn') < 0, F({}, true));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 BA BẢNG ĐỀU TRUYỀN CỜ — quên một chỗ là bảng ấy gọi sai tên trở lại
 *
 * Quét tĩnh: không được còn lời gọi `_hmNgay(...)` nào thiếu tham số thứ hai.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Bỏ chính dòng KHAI HÀM ra trước khi đếm — nó cũng khớp mẫu, mà nó không phải lời gọi. */
const goi = HTML.replace(/function _hmNgay\([^)]*\)/g, '').match(/_hmNgay\([^)]*\)/g) || [];
t('có đúng ba chỗ gọi _hmNgay (ba bảng)', goi.length === 3, goi);
goi.forEach(function (g) {
  t('🔴 lời gọi có truyền cờ isCoSo: ' + g, g.indexOf('isCoSo') > 0, g);
});

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. TIÊU ĐỀ KHỐI VÀ CỘT GỌI ĐƯỢC CẢ HAI LOẠI
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 không còn cột "Thời gian setup" gõ cứng ở khối lệnh',
  (HTML.match(/<th>Thời gian setup<\/th>/g) || []).length === 0,
  HTML.match(/<th>Thời gian setup<\/th>/g));
t('🔴 không còn tiêu đề "Lệnh tạm ứng theo dự án" trống trơn',
  HTML.indexOf('Lệnh tạm ứng theo dự án <') < 0);
t('   tiêu đề mới kể cả đơn',  HTML.indexOf('Lệnh tạm ứng theo dự án / đơn') >= 0);
t('   cột đầu kể cả đơn',      HTML.indexOf('<th>Dự án / Đơn</th>') >= 0);
t('🔴 câu "chưa có" không đổ riêng cho dự án',
  HTML.indexOf('Chưa có lệnh tạm ứng nào của dự án') < 0 && HTML.indexOf('Chưa có lệnh quyết toán nào của dự án') < 0);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 CỜ DỰNG Ở MỘT CHỖ DUY NHẤT BÊN MÁY CHỦ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('máy chủ có hàm la_don_coso()', /public static function la_don_coso\(/.test(PHP));
t('   và có hằng tên loại',       /const LOAI_COSO = 'Chi phí cơ sở';/.test(PHP));
t('🔴 cả ba nơi dựng cờ đều gọi hàm ấy, không so chuỗi tay',
  (PHP.match(/'isCoSo'\s*=>\s*self::la_don_coso\(/g) || []).length === 3,
  PHP.match(/'isCoSo'[^,]*/g));
t("🔴 không còn chỗ nào so chuỗi tay để ra isCoSo",
  !/'isCoSo'\s*=>\s*\(\s*\$\w+\['loai'\]\s*===/.test(PHP));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — đơn cơ sở được gọi là đơn, và khoảng ngày nói rõ nó là tuần');
