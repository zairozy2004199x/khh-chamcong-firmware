/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG TỔNG QUAN CŨNG LỌC THEO KHỐI.
 *
 * Anh Thắng 21/09/2026: *"Bộ Phận MTD đang nhìn thấy dữ liệu cơ sở KVC"* — kèm ảnh trang Tổng
 * quan: thẻ "🔧 Chi phí Kỹ thuật — cơ sở đã Setup / Tháo dỡ" liệt kê COOPMART BÌNH DƯƠNG,
 * NHÀ MA PHAN VĂN TRỊ, FUNZONE ADVENTURE, TÀU ESTELLA — toàn gian khu vui chơi.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CÁC MÀN KHÁC ĐÃ LỌC MÀ TRANG NÀY THÌ KHÔNG
 * =============================================================================================
 * Từ 1.242.0 mọi màn lọc khối bằng `_hopKhoi()`: đơn, quyết toán, dự án, hộp Gian. Trang Tổng
 * quan bị bỏ sót vì nó KHÔNG đọc lại dữ liệu của mấy màn ấy — nó gọi thẳng API một lần nữa:
 *   · `listDuAn()` cho thẻ Kỹ thuật        → trước đây vẽ nguyên si, không lọc;
 *   · `getPendingModules()` cho hộp kế toán → mục không mang cột `khoi` để mà lọc;
 *   · `getFinanceReport()` cho hai thẻ tiền → MÁY CHỦ đã cộng xong, màn không lọc được nữa.
 *
 * 🔴 BA CHỖ, BA CÁCH CHỮA KHÁC NHAU — đừng gộp:
 *   · thẻ Kỹ thuật: lọc ở chỗ VẼ (`renderKyThuatTongQuan`), không ở chỗ gọi. Hai người đang gọi
 *     `listDuAn()`; chữa ở chỗ gọi là người thứ ba sau này lại quên.
 *   · hộp kế toán: máy chủ phải GỬI KÈM `khoi` thì `_hopKhoi()` mới có gì để đọc. Thiếu cột ấy
 *     thì `_hopKhoi()` coi như "chưa đóng dấu" và bày ra ở cả ba khối — im lặng thôi lọc.
 *   · hai thẻ tiền: phải đẩy bộ lọc XUỐNG máy chủ. Xuống tới màn thì chỉ còn một con số tổng,
 *     không còn dòng nào để bỏ ra.
 *
 * ⚠️ BẢN GHI CHƯA ĐÓNG DẤU KHỐI VẪN TÍNH / VẪN HIỆN — cùng luật với `_hopKhoi()` ở mọi màn.
 *    Sổ cũ nạp vào có dòng cột `khoi` rỗng; bỏ chúng ra là tổng chi phí tụt xuống mà không ai
 *    hiểu vì sao, và đó là kiểu sai tệ nhất với một con số tiền.
 *
 * Chạy: node tools/test/kiem-tongquan-theo-khoi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const RPT = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-report.php', 'utf8');

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
   cặp với một dấu đóng dưới <script> và nuốt trọn thân trang (đã cắn 21/09/2026), khiến mọi
   phép "X không còn nữa" xanh một cách rỗng tuếch. */
function sachHam(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }

/* Phép canh cho chính cái bẫy trên: thân hàm bốc ra phải CÒN chữ, nếu không mọi phép dưới đây
   là xanh giả. */
t('⚠️ bốc được thân `renderKyThuatTongQuan`', bocHam('renderKyThuatTongQuan').length > 300);
t('⚠️ bốc được thân `loadTongQuan`', bocHam('loadTongQuan').length > 300);
t('⚠️ bốc được thân `renderAcctInbox`', bocHam('renderAcctInbox').length > 300);

/* ═══ 1. THẺ KỸ THUẬT — LỌC Ở CHỖ VẼ ════════════════════════════════════════════ */
const KT = sachHam('renderKyThuatTongQuan');
t('🔴 thẻ Kỹ thuật lọc `_hopKhoi`', /\.filter\(_hopKhoi\)/.test(KT), KT.slice(0, 200));
/* 🔴 LỌC TRƯỚC KHI CHIA BA RỔ. Lọc sau là ba rổ (Setup · Tháo dỡ · Chi phí cơ sở) mỗi rổ một
   tập, và bốn con số ở đầu thẻ cộng từ một tập khác với hai bảng ở dưới. */
t('🔴 lọc TRƯỚC khi chia Setup / Tháo dỡ / Chi phí cơ sở',
  KT.indexOf('.filter(_hopKhoi)') >= 0 && KT.indexOf('.filter(_hopKhoi)') < KT.indexOf("'Setup lắp đặt'"), KT.slice(0, 300));
/* ⚠️ Lọc ở chỗ VẼ chứ không ở chỗ GỌI — hai người đang gọi `listDuAn()`. Phép này canh đúng
   điều đó: chỗ gọi trong `loadTongQuan` KHÔNG được tự lọc rồi coi như xong. */
t('⚠️ `loadTongQuan` vẫn gọi thẳng `listDuAn()`, việc lọc nằm ở chỗ vẽ',
  /renderKyThuatTongQuan\(kr\.items\|\|\[\]\)/.test(sachHam('loadTongQuan')));

/* Chạy thật: một tập dự án ba khối, đứng ở mtd thì chỉ còn dự án mtd + dự án chưa đóng dấu. */
const loc = (khoi, ds) => new Function('KHOI_DANG', 'ds', `
  function _khoiCua(d){ return String((d&&d.khoi)||'').trim().toLowerCase(); }
  function _hopKhoi(d){ var k=_khoiCua(d); return k==='' || k===String(KHOI_DANG).toLowerCase(); }
  return ds.filter(_hopKhoi).map(function(x){return x.ten;});`)(khoi, ds);
const DS = [
  { ten: 'NHÀ MA PHAN VĂN TRỊ', khoi: 'kvc' },
  { ten: 'FUNZONE ADVENTURE', khoi: 'KVC' },      // viết hoa — vẫn phải khớp
  { ten: 'AEON MALL BÌNH DƯƠNG', khoi: 'mtd' },
  { ten: 'DỰ ÁN SỔ CŨ', khoi: '' }                // chưa đóng dấu
];
teq('🔴 đứng ở mtd: chỉ còn dự án mtd + dự án chưa đóng dấu',
  ['AEON MALL BÌNH DƯƠNG', 'DỰ ÁN SỔ CŨ'], loc('mtd', DS));
teq('   đứng ở kvc thì ngược lại (và so khối KHÔNG phân biệt hoa thường)',
  ['NHÀ MA PHAN VĂN TRỊ', 'FUNZONE ADVENTURE', 'DỰ ÁN SỔ CŨ'], loc('kvc', DS));

/* ═══ 2. HỘP "ĐƠN CHỜ KẾ TOÁN" ══════════════════════════════════════════════════ */
const IB = sachHam('renderAcctInbox');
t('🔴 đơn vận hành trong hộp kế toán lọc `_hopKhoi`',
  /\(BOOT\.dons\|\|\[\]\)\.filter\(_hopKhoi\)/.test(IB), IB.slice(0, 400));
t('🔴 mục của Kỹ thuật/Marketing/Công tác/Setup cũng lọc `_hopKhoi`',
  /\(\(r&&r\.items\)\|\|\[\]\)\.filter\(_hopKhoi\)/.test(IB), IB.slice(0, 900));
/* 🔴 LỌC KIA VÔ NGHĨA NẾU MÁY CHỦ KHÔNG GỬI CỘT `khoi`: `_hopKhoi` sẽ đọc ra chuỗi rỗng, coi
   như "chưa đóng dấu", và bày lại đủ ba khối — im lặng thôi lọc, không ai thấy. */
t('🔴 `pending_modules()` gửi kèm cột `khoi` cho MỖI mảng',
  (RPT.match(/'khoi'\s*=>\s*\$kh\(\s*\$r\s*\)/g) || []).length === 3,
  (RPT.match(/'khoi'\s*=>\s*\$kh\(\s*\$r\s*\)/g) || []).length);
t('   và đọc từ chính bản ghi gốc, không gõ cứng một khối',
  /\$kh\s*=\s*function\s*\(\s*\$r\s*\)[^;]*\$r\['khoi'\]/.test(RPT));

/* ═══ 3. HAI THẺ TIỀN — BỘ LỌC PHẢI XUỐNG MÁY CHỦ ═══════════════════════════════ */
t('🔴 màn gửi khối đang đứng xuống `getFinanceReport`',
  /getFinanceReport\(\{[^}]*khoi:\s*KHOI_DANG/.test(sachHam('loadTongQuan')), sachHam('loadTongQuan').slice(-400));
t('🔴 `finance()` đọc tham số `khoi`', /\$f_khoi\s*=\s*isset\(\s*\$opts\['khoi'\]\s*\)/.test(RPT));
t('🔴 và cắt ngay ở vòng đọc đơn, trước khi cộng',
  /foreach\s*\(\s*VHCP_Don::don_rows\(\)\s*as\s*\$r\s*\)\s*\{[\s\S]{0,300}?\$f_khoi[\s\S]{0,160}?continue;/.test(RPT));
/* 🔴 RỖNG = MỌI KHỐI. Người gọi cũ không gửi `khoi` thì báo cáo giữ nguyên nghĩa cũ là gộp cả
   hệ — thêm tham số mà đổi câu trả lời của người gọi cũ là hỏng ngầm. */
t('🔴 không gửi `khoi` → gộp cả hệ như cũ', /if\s*\(\s*''\s*!==\s*\$f_khoi\s*\)/.test(RPT));
/* ⚠️ ĐƠN CHƯA ĐÓNG DẤU KHỐI VẪN TÍNH — cùng luật với màn. Bỏ chúng ra là tổng tiền tụt xuống
   mà không ai hiểu vì sao. */
t('⚠️ đơn chưa đóng dấu khối VẪN được cộng', /''\s*!==\s*\$kd\s*&&\s*\$kd\s*!==\s*\$f_khoi/.test(RPT));
/* ⚠️ So khối hạ chữ thường cả hai bên: cột `khoi` trong sổ có dòng ghi 'KVC', màn gửi xuống
   'kvc' — so nguyên văn là trượt sạch và bộ lọc lại im lặng thôi lọc. */
t('⚠️ so khối hạ chữ thường cả hai bên',
  /\$f_khoi\s*=\s*isset[^;]*mb_strtolower/.test(RPT) && /\$kd\s*=\s*mb_strtolower/.test(RPT));

/* ═══ 4. ĐỔI KHỐI LÀ TẢI LẠI NGAY ═══════════════════════════════════════════════ */
/* Hai thẻ tiền do MÁY CHỦ cộng, nên vẽ lại bằng dữ liệu cũ là vô ích — phải hỏi lại. */
t('🔴 đổi khối lúc đang đứng ở Tổng quan thì hỏi lại máy chủ',
  /loadTongQuan\(\)/.test(sachHam('doiKhoi')) && /CUR_PAGE===['"]tongquan['"]/.test(sachHam('doiKhoi')),
  sachHam('doiKhoi').slice(-500));
t('   và đúng tên trang mà `showPage` đặt', /<div id="page-tongquan"/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: Tổng quan (thẻ Kỹ thuật · hộp kế toán · hai thẻ tiền) đều lọc theo khối.');
