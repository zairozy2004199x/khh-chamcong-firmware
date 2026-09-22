import { chromium } from 'playwright';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const p = await b.newPage();
await p.goto('file://' + process.cwd() + '/huong-dan.html', { waitUntil: 'networkidle' });
await p.emulateMedia({ media: 'print' });
await p.pdf({
  path: 'Tai-Chinh-KH-Huong-dan-su-dung.pdf',
  format: 'A4', printBackground: true,
  margin: { top: '17mm', bottom: '18mm', left: '15mm', right: '15mm' },
  displayHeaderFooter: true,
  headerTemplate: '<div></div>',
  footerTemplate: `<div style="width:100%;font-family:'DejaVu Sans',sans-serif;font-size:7.5pt;color:#64748b;padding:0 15mm;display:flex;justify-content:space-between">
    <span>Tài Chính K&amp;H — Hướng dẫn sử dụng · bản 1.5.0</span>
    <span>Trang <span class="pageNumber"></span>/<span class="totalPages"></span></span></div>`,
});
await b.close();
