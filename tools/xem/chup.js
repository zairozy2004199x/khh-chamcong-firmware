/* Mở tệp HTML đã dựng bằng Chromium, chụp ảnh, và IN RA những gì màn hình đang nói.
 *
 * ⚠️ Phần in ra chữ quan trọng ngang phần chụp ảnh: ảnh cho thấy bố cục, còn mấy dòng chữ này
 *    cho thấy màn hình đang NÓI GÌ VỀ AI — đúng loại lỗi mà bản 3.69.0 dính (câu "đó là trạng
 *    thái ĐÚNG" nói trùm cả người cần đụng tay). Đọc chữ nhanh hơn soi ảnh. */
const path = require('path');
const fs = require('fs');
let chromium;
try { chromium = require('/opt/node22/lib/node_modules/playwright').chromium; }
catch (e) { try { chromium = require('playwright').chromium; }
  catch (e2) { console.log('✗ chưa có playwright — cài rồi chạy lại, hoặc mở thẳng tệp HTML bằng trình duyệt'); process.exit(2); } }

const tep = process.argv[2];
const thuMuc = process.argv[3] || path.dirname(tep);
if (!tep || !fs.existsSync(tep)) { console.log('✗ chưa dựng được tệp HTML'); process.exit(2); }

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1500, height: 1200 }, deviceScaleFactor: 2 });
  await p.goto('file://' + path.resolve(tep));
  await p.waitForTimeout(300);

  await p.screenshot({ path: path.join(thuMuc, 'man-day-du.png'), fullPage: true });
  const dai = await p.$('.dai-mb');
  if (dai) await dai.screenshot({ path: path.join(thuMuc, 'dai-dem.png') });

  console.log('\n── Dải đếm nói gì ─────────────────────────────────────────');
  for (const x of await p.$$eval('.dai-hang', e => e.map(x => x.textContent.replace(/\s+/g, ' ').trim()))) {
    console.log('  · ' + x);
  }
  for (const x of await p.$$eval('.dai-mb p', e => e.map(x => x.textContent.replace(/\s+/g, ' ').trim()))) {
    console.log('  · ' + x);
  }
  console.log('\n── Từng người, hệ đang hiểu mảng nào ──────────────────────');
  const rows = await p.$$eval('tbody tr', trs => trs.map(tr => {
    const td = tr.querySelectorAll('td');
    if (td.length < 4) return null;
    const ten = (td[1]?.textContent || '').trim().split('\n')[0].slice(0, 24);
    const ghim = [...tr.querySelectorAll('input[name^="mbp_mang"]:checked')].map(i => i.value);
    const suy = (tr.querySelector('.mb-suy')?.textContent || '').trim();
    return ten ? { ten, ghim, suy } : null;
  }).filter(Boolean));
  rows.forEach(r => console.log('  ' + r.ten.padEnd(26)
    + (r.ghim.length ? 'ghim:[' + r.ghim.join(', ') + '] ' : '') + r.suy));

  /* 🔴 CHỮ BỊ CẮT là lỗi 3.69.0 đã dính — dò máy, đừng bắt người soi ảnh. */
  console.log('\n── Ô nào đang bị cắt mất chữ ──────────────────────────────');
  const cat = await p.$$eval('select, .mb-suy, .nut', els => els
    .filter(e => e.scrollWidth > e.clientWidth + 2)
    .map(e => (e.tagName.toLowerCase()) + ' «' + e.textContent.replace(/\s+/g, ' ').trim().slice(0, 46) + '»'));
  if (cat.length) { cat.slice(0, 10).forEach(x => console.log('  ⚠️ ' + x)); }
  else { console.log('  ✓ không ô nào bị cắt'); }

  console.log('\n📷 ảnh: ' + path.join(thuMuc, 'man-day-du.png') + ' · ' + path.join(thuMuc, 'dai-dem.png'));
  await b.close();
})();
