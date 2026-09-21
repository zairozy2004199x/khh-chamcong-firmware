/* Báo cáo ngày cơ sở: giờ công cạnh doanh thu (mượn Kintai) */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
let fail = 0;
const t = (n, g, w) => { const ok = JSON.stringify(g) === JSON.stringify(w); if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(56)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`); };
const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
const errs = []; p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
await p.goto('file://' + SP + '/build/index.html'); await p.waitForTimeout(1300);
await p.evaluate(() => {
  const A = window.APP;
  ['staff', 'attendance', 'revenue', 'dailyrev', 'settings'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  A.save('staff', { id: 'gd', name: 'Giám Đốc', code: 'GD', office: 'FUNFEST', status: 'active', role: 'owner' });
  A.save('staff', { id: 's1', name: 'An', code: 'NV01', office: 'FUNFEST', status: 'active' });
  A.save('staff', { id: 's2', name: 'Bình', code: 'NV02', office: 'FZ ADV AL', status: 'active' });
  A.S.me = 'Giám Đốc';
  A.save('attendance', { id: 'gd_202609', staffId: 'gd', cycle: '2026-09', days: { '15': { m: 480 } } });
  A.save('attendance', { id: 's1_202609', staffId: 's1', cycle: '2026-09', days: { '15': { m: 480 }, '16': { m: 240 } } });
  A.save('attendance', { id: 's2_202609', staffId: 's2', cycle: '2026-09', days: { '15': { m: 600 } } });
  /* doanh thu đọc thẳng từ FABi (chỉ cửa hàng FUNFEST, tên khác hoa thường) */
  A.save('revenue', { id: 'dt_20260915_x', ngay: '2026-09-15', coso: 'Funfest', dt: 8000000, tt: 7800000, hd: 40 });
});
await p.waitForTimeout(400);
await p.evaluate(() => window.APP.go('checkin')); await p.waitForTimeout(400);
await p.evaluate(() => document.querySelector('[data-cv="bcngay"]').click()); await p.waitForTimeout(400);
await p.evaluate(() => { const el = document.querySelector('#bnDate'); el.value = '2026-09-15'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(500);

const hang = () => p.evaluate(() => [...document.querySelectorAll('table.bcn tbody tr')].map(tr => [...tr.querySelectorAll('td')].map(td => td.innerText.replace(/\s+/g, ' ').trim())));
let h = await hang();
const ff = h.find(r => r[0] === 'FUNFEST'), fz = h.find(r => r[0] === 'FZ ADV AL');
t('FUNFEST: 2 nhân sự, 2 có mặt, 16h công', [ff[1], ff[2], ff[3]], ['2', '2', '16h']);
t('  doanh thu khớp FABi dù khác hoa thường, gắn nhãn FABi', /8\.000\.000 ₫ FABI/i.test(ff[4]), true);
t('  chi phí NC = 16h × 25.000', ff[5], '400.000 ₫');
t('  NC/DT = 5%', /5%/.test(ff[6]), true);
t('  có số FABi thì không có nút nhập tay', ff[7], '');
t('FZ ADV AL chưa có doanh thu', fz[4], '—');
t('  nhưng có nút Nhập DT', fz[7], 'Nhập DT');

/* nhập tay cho FZ */
await p.evaluate(() => document.querySelector('[data-nhapdt="FZ ADV AL"]').click()); await p.waitForTimeout(400);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); d.querySelector('input[type=number]').value = '5000000'; d.querySelector('[data-x="save"]').click(); });
await p.waitForTimeout(500);
t('lưu doanh thu nhập tay đúng mã ổn định', await p.evaluate(() => window.APP.col('dailyrev').map(x => [x.id, x.dt])), [['dr_20260915_fz-adv-al', 5000000]]);
h = await hang(); const fz2 = h.find(r => r[0] === 'FZ ADV AL');
t('  hiện với nhãn nhập tay', /5\.000\.000 ₫ NHẬP TAY/i.test(fz2[4]), true);
t('  NC/DT của FZ = 250.000/5.000.000 = 5%', /5%/.test(fz2[6]), true);
t('  nút đổi thành Sửa DT', fz2[7], 'Sửa DT');

/* thẻ tổng và đơn giá */
t('thẻ Doanh thu cộng cả hai nguồn', await p.evaluate(() => [...document.querySelectorAll('.tile')].some(x => x.innerText.includes('13.000.000 ₫'))), true);
await p.evaluate(() => document.querySelector('[data-cact="dongia"]').click()); await p.waitForTimeout(300);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); d.querySelector('input[type=number]').value = '50000'; d.querySelector('[data-x="save"]').click(); });
await p.waitForTimeout(500);
t('đổi đơn giá giờ → chi phí NC nhân đôi', (await hang()).find(r => r[0] === 'FUNFEST')[5], '800.000 ₫');
t('  đơn giá lưu vào settings', await p.evaluate(() => window.APP.setting('baoCaoNgay', {}).donGia), 50000);

/* cả tháng */
await p.evaluate(() => { const el = document.querySelector('#bnThang'); el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(500);
t('cả tháng: FUNFEST cộng thêm 4h ngày 16 → 20h', (await hang()).find(r => r[0] === 'FUNFEST')[3], '20h');
t('  cả tháng không có nút nhập tay theo ngày', (await hang()).every(r => r[7] === ''), true);

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
