// Dò Vé Rẻ — mở trang đặt vé, điền sẵn hồ sơ, dừng lại trước bước thanh toán.
//
//   npm i playwright && npx playwright install chromium
//   node automation/dat-ve.mjs "<đường dẫn tìm chuyến>"
//
// Hồ sơ đọc từ profile.json (bấm "Sao chép hồ sơ JSON" trên trang rồi dán vào file đó).
// Script cố ý KHÔNG chạm tới số thẻ, CVV và OTP.

import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const profilePath = process.env.DVR_PROFILE || resolve(here, '..', 'profile.json');

let DATA;
try {
  DATA = JSON.parse(readFileSync(profilePath, 'utf8'));
} catch {
  console.error('Không đọc được ' + profilePath + '.\n' +
    'Mở index.html → mục "Hồ sơ điền sẵn" → bấm "Sao chép hồ sơ JSON" → dán vào file đó.');
  process.exit(1);
}

const URL_TIM_CHUYEN = process.argv[2] || DATA.searchUrl;
if (!URL_TIM_CHUYEN) {
  console.error('Thiếu đường dẫn tìm chuyến. Chép từ thẻ trang bán vé trên index.html rồi truyền vào:\n' +
    '  node automation/dat-ve.mjs "https://..."');
  process.exit(1);
}

const { default: FILL } = await import(resolve(here, '..', 'autofill-bookmarklet.js'))
  .then(m => ({ default: m.default || m }))
  .catch(async () => {
    const { createRequire } = await import('node:module');
    return { default: createRequire(import.meta.url)('../autofill-bookmarklet.js') };
  });

const browser = await chromium.launch({
  headless: false,
  slowMo: 120,
  executablePath: process.env.CHROME_PATH || undefined
});
const page = await browser.newPage({ locale: 'vi-VN', viewport: { width: 1360, height: 900 } });

console.log('→ Mở ' + URL_TIM_CHUYEN);
await page.goto(URL_TIM_CHUYEN, { waitUntil: 'domcontentloaded' });

console.log('\nChọn chuyến bằng tay — giá đổi từng phút, người chọn vẫn chuẩn hơn máy.');
console.log('Tới khi hiện form thông tin hành khách thì bấm ▶ (Resume) trong cửa sổ Playwright.\n');
await page.pause();

const n = await page.evaluate(
  ([src, data]) => new Function('return ' + src)()(data),
  [FILL.toString(), DATA]
);
console.log('→ Đã điền ' + n + ' ô. Soát lại một lượt trước khi đi tiếp.');

console.log('\nDừng ở đây: số thẻ, CVV và OTP do người thật nhập.');
console.log('Thanh toán xong thì đóng cửa sổ hoặc bấm ▶ để script kết thúc.\n');
await page.pause();

await browser.close();
