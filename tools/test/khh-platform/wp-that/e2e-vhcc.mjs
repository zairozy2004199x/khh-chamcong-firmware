/* Kiểm thử THẬT: plugin Chấm Công (K&H) đang bật cùng site → nền tảng tự nối, KHÔNG khai gì */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
const SP = '/tmp/claude-0/-home-user-khh-chamcong-firmware/30dd3c43-a516-53dc-bb76-000482c08c1d/scratchpad';
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
const WP  = 'http://127.0.0.1:8899';
let fail = 0;
const t = (n, g, w) => {
  const ok = JSON.stringify(g) === JSON.stringify(w);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(54)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`);
};
execSync('php /tmp/reset-nguon.php', { stdio: 'pipe' });   // không khai nguồn nào cả

const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
const loi = [];
const boQua = /underscore|backbone|_ is not defined|g\.template|wp-includes\/js|favicon|ERR_/;
p.on('pageerror', e => { if (p.url().includes('khh_app=1') && !boQua.test(e.message)) loi.push('PAGEERROR: ' + e.message); });
p.on('console', m => { if (m.type() === 'error' && p.url().includes('khh_app=1') && !boQua.test(m.text())) loi.push('console: ' + m.text()); });

await p.goto(WP + '/wp-login.php');
await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'MatKhau!2026'); await p.click('#wp-submit');
await p.waitForLoadState('networkidle');

/* --- mở nền tảng: KHÔNG khai gì, dữ liệu phải có ngay --- */
await p.goto(WP + '/?khh_app=1'); await p.waitForTimeout(3000);
await p.evaluate(() => document.querySelector('[data-go="checkin"]').click()); await p.waitForTimeout(1000);
await p.evaluate(() => { const el = document.querySelector('#ckDate'); el.value = '2026-09-15'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(900);
const the = await p.evaluate(() => [...document.querySelectorAll('.tile')].map(x => x.innerText.replace(/\s+/g, ' ').trim()));
console.log('   thẻ:', JSON.stringify(the));
t('KHÔNG KHAI GÌ mà màn chấm công đã có dữ liệu', /CÓ MẶT 9\/10/.test(the.join(' ')), true);
const hang1 = await p.evaluate(() => [...document.querySelector('table.t tbody tr').querySelectorAll('td')].map(td => td.innerText.replace(/\s+/g, ' ').trim()));
console.log('   dòng đầu:', JSON.stringify(hang1));
t('  cột Thiết bị ghi cơ sở của máy bên vhcc', hang1[5], 'KHU VUI CHƠI FUNFEST');
t('  cột Phương thức dịch từ nguồn "may"', hang1[6].toLowerCase(), 'máy chấm công');

/* --- cơ sở tự điền từ sổ nhân viên bên vhcc --- */
const coso = await p.evaluate(() => [...document.querySelectorAll('[data-cs]')].map(x => [x.getAttribute('data-cs'), x.querySelector('.n') ? x.querySelector('.n').textContent : '']));
console.log('   cơ sở:', JSON.stringify(coso));
t('sidebar chia cơ sở theo sổ nhân viên bên vhcc', coso.map(x => x[0]).includes('FZ ADV AL') && coso.map(x => x[0]).includes('GHOST BRIDE BD'), true);
t('  người đã gán tay vẫn ở Chi Nhánh HCM', (coso.find(x => x[0] === 'Chi Nhánh HCM') || [])[1], '3');
t('  không còn ai "Chưa gán cơ sở"', (coso.find(x => x[0] === 'Chưa gán cơ sở') || ['', '0'])[1], '0');

/* --- bảng công cơ sở: giá trị công = ra − vào của bên ấy --- */
await p.evaluate(() => document.querySelector('[data-cs="FZ ADV AL"]').click()); await p.waitForTimeout(900);
await p.evaluate(() => { const el = document.querySelector('#bcCyc'); el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(1000);
const bc = await p.evaluate(() => {
  const tb = document.querySelector('table.bcong');
  const rows = [...tb.querySelectorAll('tbody tr')].map(tr => [tr.querySelector('td.nv b').textContent, tr.querySelector('td.tg').textContent]);
  const o1 = tb.querySelector('[data-cong$=":1"] b');
  return { rows, o1: o1 ? { so: o1.textContent, tay: o1.className !== 'sr' } : null, chan: tb.querySelector('tfoot td.tg').textContent };
});
console.log('   bảng công FZ ADV AL:', JSON.stringify(bc));
t('bảng công cơ sở chỉ có 3 người của cơ sở đó', bc.rows.length, 3);
t('  ô công là số của bên vhcc, không phải số tự tính', bc.o1 && bc.o1.tay, true);
t('  tổng tháng ra giờ phút', /\d+h/.test(bc.chan), true);

/* --- trang quản trị nói rõ đã tự nối --- */
await p.goto(WP + '/wp-admin/admin.php?page=khh-nhap-cham-cong'); await p.waitForTimeout(800);
const qt = await p.evaluate(() => document.body.innerText);
t('trang quản trị báo đã tự nối với Chấm Công (K&H)', /Đã tự nối với plugin Chấm Công/.test(qt), true);
t('  không bày trình khai bảng ra nữa', /Bước 1 — Chọn bảng/.test(qt), false);
t('  nói rõ 1 mã chưa có hồ sơ', /MNNV9999/.test(qt), true);
await p.screenshot({ path: SP + '/vhcc-quantri.png' });

await p.goto(WP + '/?khh_app=1'); await p.waitForTimeout(3000);
await p.evaluate(() => document.querySelector('[data-go="checkin"]').click()); await p.waitForTimeout(900);
await p.evaluate(() => document.querySelector('[data-cs="FZ ADV AL"]').click()); await p.waitForTimeout(900);
await p.evaluate(() => { const el = document.querySelector('#bcCyc'); el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(1000);
await p.screenshot({ path: SP + '/vhcc-bcong.png' });

await b.close();
console.log(loi.length ? '\nLỗi trang:\n' + loi.slice(0, 8).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || loi.length ? 1 : 0);
