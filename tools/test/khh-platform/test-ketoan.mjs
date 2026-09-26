/* Kiểm khối "Kế toán" trên bệ phóng: chỉ Quản trị / Chủ sở hữu thấy, nhóm rỗng thì tự ẩn,
   và hạ vai rồi thì không mở lại được bằng id ứng dụng còn nhớ trong máy.

   VÌ SAO CÓ TỆP NÀY
   Trước đây nền tảng không có khái niệm "ứng dụng chỉ dành cho một vai": mọi app đã khai
   là ai cũng thấy. Khối Kế toán là chỗ đầu tiên cần giấu, mà giấu hụt thì hỏng kín đáo —
   nút biến khỏi bệ phóng nhưng vẫn còn ở thanh biểu tượng, hoặc id cũ nằm trong
   localStorage vẫn mở thẳng vào được. Nên phải kiểm cả ba đường: bệ phóng, thanh biểu
   tượng, và lối vào bằng id. */
import { chromium } from 'playwright-core';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';

let fail = 0;
const t = (n, g, w) => {
  const ok = JSON.stringify(g) === JSON.stringify(w);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(58)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`);
};

const KETOAN = [
  { id: 'unc', ten: 'Ủy nhiệm chi & Công nợ', mo: 'Khoản nào đã đi tiền', url: 'https://vi-du.invalid/uy-nhiem-chi/', mau: '#1D5B8F', icon: 'unc' },
  { id: 'chiphi', ten: 'Báo cáo chi phí', mo: 'Phân bổ chi phí', url: 'https://vi-du.invalid/bao-cao-chi-phi/', mau: '#1F6F5F', icon: 'report' },
];

const b = await chromium.launch({ executablePath: EXE });
const errs = [];

async function mo(api) {
  const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
  p.on('console', m => { if (m.type() === 'error' && !/ERR_|font|favicon/i.test(m.text())) errs.push('console: ' + m.text()); });
  p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
  // Chặn mọi lượt tải khung nhúng: phép thử chỉ quan tâm src, không cần trang thật.
  await p.route('**vi-du.invalid**', r => r.fulfill({ status: 200, contentType: 'text/html', body: 'trang nhúng' }));
  await p.addInitScript(a => { window.KH_API = a; }, api);
  await p.goto('file://' + SP + '/build/index.html');
  await p.waitForTimeout(900);
  return p;
}

const ten = p => p.$$eval('.apps .app-t .nm', ns => ns.map(n => n.textContent));
const nhom = p => p.$$eval('.lcats .lcat', ns => ns.map(n => n.textContent));
const rail = p => p.$$eval('.iconrail .ir[data-go]', ns => ns.map(n => n.getAttribute('data-go')));

/* ---------- Quản trị: thấy đủ ---------- */
let p = await mo({ role: 'admin', me: 'Thắng', ketoan: KETOAN });
t('admin — có nhóm Kế toán+', (await nhom(p)).includes('Kế toán+'), true);
await p.click('.lcat[data-cat="ketoan"]');
await p.waitForTimeout(250);
t('admin — nhóm Kế toán gồm 2 app', await ten(p), ['Ủy nhiệm chi & Công nợ', 'Báo cáo chi phí']);
t('admin — thanh biểu tượng có app kế toán', (await rail(p)).filter(x => x.startsWith('kt_')), ['kt_unc', 'kt_chiphi']);

await p.click('.app-t[data-go="kt_unc"]');
await p.waitForTimeout(400);
t('admin — mở ra khung nhúng đúng địa chỉ',
  await p.getAttribute('.kt-khung', 'src'), 'https://vi-du.invalid/uy-nhiem-chi/');
t('admin — có nút mở tab mới', await p.getAttribute('.kt-ngoai', 'href'), 'https://vi-du.invalid/uy-nhiem-chi/');
await p.close();

/* ---------- Nhân viên: không thấy gì ---------- */
p = await mo({ role: 'staff', me: 'Lan', ketoan: KETOAN });
t('nhân viên — KHÔNG có nhóm Kế toán+', (await nhom(p)).includes('Kế toán+'), false);
t('nhân viên — bệ phóng không có app kế toán',
  (await ten(p)).filter(x => /Ủy nhiệm|Báo cáo chi phí/.test(x)), []);
t('nhân viên — thanh biểu tượng không có app kế toán',
  (await rail(p)).filter(x => x.startsWith('kt_')), []);
t('nhân viên — app khác vẫn thấy bình thường', (await ten(p)).includes('Chấm công'), true);

/* Hạ vai rồi mà id cũ còn trong máy: phải bị chặn, không mở vào được. */
const sau = await p.evaluate(() => {
  window.APP.go('kt_unc');
  return { app: window.APP.S.app, coKhung: !!document.querySelector('.kt-khung') };
});
t('nhân viên — gọi thẳng APP.go bị chặn', sau, { app: 'home', coKhung: false });
await p.close();

/* ---------- Chưa cài plugin kế toán nào ---------- */
p = await mo({ role: 'admin', me: 'Thắng', ketoan: [] });
t('chưa cài plugin — nhóm Kế toán+ tự ẩn', (await nhom(p)).includes('Kế toán+'), false);
t('chưa cài plugin — bệ phóng vẫn có app cũ', (await ten(p)).length > 5, true);
await p.close();

/* ---------- Không có KH_API (khung chạy thử trần) ---------- */
p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(900);
t('không có KH_API — không vỡ, không có nhóm Kế toán',
  await p.$$eval('.lcats .lcat', ns => ns.map(n => n.textContent).includes('Kế toán+')), false);
await p.close();

/* ---------- Điện thoại ---------- */
p = await (await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true })).newPage();
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
await p.route('**vi-du.invalid**', r => r.fulfill({ status: 200, contentType: 'text/html', body: 'trang nhúng' }));
await p.addInitScript(a => { window.KH_API = a; }, { role: 'admin', me: 'Thắng', ketoan: KETOAN });
await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(900);
await p.click('.lcat[data-cat="ketoan"]');
await p.waitForTimeout(250);
await p.click('.app-t[data-go="kt_unc"]');
await p.waitForTimeout(500);
const dt = await p.evaluate(() => {
  const f = document.querySelector('.kt-khung');
  const r = f.getBoundingClientRect();
  return {
    caoHon400: r.height > 400,
    vuaBeNgang: r.width <= window.innerWidth + 1,
    khongCuonNgang: document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1,
  };
});
t('điện thoại — khung nhúng cao và vừa bề ngang', dt, { caoHon400: true, vuaBeNgang: true, khongCuonNgang: true });
await p.close();

await b.close();
if (errs.length) { fail++; console.log('LỖI JS:\n  ' + errs.slice(0, 6).join('\n  ')); }
console.log(fail ? `\n${fail} phép thử KHÔNG đạt.` : '\nTất cả đạt.');
process.exit(fail ? 1 : 0);
