/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * XUẤT MỘT TRANG HTML RA PDF A4 — dùng cho tài liệu gửi cơ sở.
 *
 * Anh Thắng 14/09/2026: *"xuất anh PDF"*.
 *
 * 🔴 ÉP GIAO DIỆN SÁNG (`colorScheme:'light'`). Trang có cả bảng màu tối; máy nào đang để nền
 *    tối mà xuất thẳng là ra một tệp đen kịt — tốn mực và đọc không nổi. Ép ở ĐÂY chứ không chỉ
 *    trông vào `@media print` của trang: người ta còn xuất từ trang khác nữa.
 * 🔴 `printBackground:true`. Không có nó thì Chromium bỏ hết nền màu, mà MÀU chính là thứ phân
 *    biệt ô cảnh báo với ô thường — bỏ màu là bỏ mất một tầng thông tin.
 * ⚠️ `waitUntil:'networkidle'` + chờ thêm: phông chữ tải từ Google Fonts, xuất trước khi phông
 *    về là cả tệp PDF dùng phông dự phòng, và tiếng Việt có dấu trông lệch hẳn.
 *
 * Chạy:  node tools/xuat-pdf.js <tệp .html> <tệp .pdf>
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
const path = require('path');
let chromium;
try { chromium = require('/opt/node22/lib/node_modules/playwright').chromium; }
catch (e) { chromium = require('playwright').chromium; }

const vao = process.argv[2], ra = process.argv[3];
if (!vao || !ra) { console.log('Dùng: node tools/xuat-pdf.js <tệp .html> <tệp .pdf>'); process.exit(2); }

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ colorScheme: 'light' });
  await p.goto('file://' + path.resolve(vao), { waitUntil: 'networkidle' });
  await p.emulateMedia({ media: 'print', colorScheme: 'light' });
  await p.waitForTimeout(900);
  await p.pdf({
    path: ra, format: 'A4', printBackground: true,
    margin: { top: '14mm', bottom: '14mm', left: '14mm', right: '14mm' },
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: '<div style="width:100%;font:9px Helvetica,sans-serif;color:#6b7f87;'
      + 'padding:0 14mm;display:flex;justify-content:space-between">'
      + '<span>K&amp;H · Quy trình chấm công online</span>'
      + '<span><span class="pageNumber"></span>/<span class="totalPages"></span></span></div>',
  });
  await b.close();
  console.log('✓ ' + ra);
})();
