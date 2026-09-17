/* Kiểm thử ghép đường dẫn REST — lỗi này từng làm cả nền tảng trắng trơn */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
let fail = 0;
const t = (n, g, w) => {
  const ok = JSON.stringify(g) === JSON.stringify(w);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(56)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`);
};
const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext()).newPage();
await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1200);

const thu = (rest, path, qs) => p.evaluate(a => {
  window.KH_API = Object.assign({}, window.KH_API, { rest: a[0] });
  return window.APP.restUrl(a[1], a[2]);
}, [rest, path, qs]);

/* Đường dẫn tĩnh kiểu đẹp */
t('permalink đẹp: nối tham số bằng ?',
  await thu('https://khmatrix.com/wp-json/khh/v1/', 'state', 'since=0'),
  'https://khmatrix.com/wp-json/khh/v1/state?since=0');
t('  không có tham số thì để nguyên',
  await thu('https://khmatrix.com/wp-json/khh/v1/', 'doc', ''),
  'https://khmatrix.com/wp-json/khh/v1/doc');

/* Đường dẫn tĩnh "Mặc định" — rest_url() đã có sẵn dấu ? */
t('permalink mặc định: nối tham số bằng &',
  await thu('https://khmatrix.com/index.php?rest_route=/khh/v1/', 'state', 'since=0'),
  'https://khmatrix.com/index.php?rest_route=/khh/v1/state&since=0');
t('  KHÔNG sinh ra hai dấu ? làm WordPress trả 404',
  (await thu('https://khmatrix.com/index.php?rest_route=/khh/v1/', 'state', 'since=0'))
    .split('?').length - 1, 1);
t('  ghi không tham số vẫn đúng tuyến',
  await thu('https://khmatrix.com/index.php?rest_route=/khh/v1/', 'bulk', ''),
  'https://khmatrix.com/index.php?rest_route=/khh/v1/bulk');

/* đối chứng: cách ghép cũ sai ở đâu */
t('(đối chứng) nối thẳng chuỗi thì hỏng tuyến',
  'https://khmatrix.com/index.php?rest_route=/khh/v1/' + 'state?since=0',
  'https://khmatrix.com/index.php?rest_route=/khh/v1/state?since=0');

await b.close();
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail ? 1 : 0);
