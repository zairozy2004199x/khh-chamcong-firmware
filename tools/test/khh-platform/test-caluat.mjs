/* Luật tính công theo ca (mượn Frappe HR) + bảng công tô màu, lọc bộ phận, tổng kết */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
let fail = 0;
const t = (n, g, w) => { const ok = JSON.stringify(g) === JSON.stringify(w); if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(56)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`); };
const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
const errs = [];
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font/i.test(m.text())) errs.push('console: ' + m.text()); });
await p.goto('file://' + SP + '/build/index.html'); await p.waitForTimeout(1300);

await p.evaluate(() => {
  const A = window.APP;
  ['staff', 'attendance', 'settings'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  const mk = (id, name, code, office, dept) => A.save('staff', { id, name, code, office, dept, status: 'active', role: 'staff' });
  mk('s1', 'An', 'NV01', 'Cửa hàng A', 'Bán hàng');
  mk('s2', 'Bình', 'NV02', 'Cửa hàng A', 'Bán hàng');
  mk('s3', 'Chi', 'NV03', 'Cửa hàng A', 'Kho');
  mk('s4', 'Dũng', 'NV04', 'Văn phòng', 'Kế toán');
  A.save('staff', { id: 'gd', name: 'Giám Đốc', code: 'GD', office: 'Văn phòng', status: 'active', role: 'owner' });
  A.S.me = 'Giám Đốc';
  /* Tháng 9/2026: 1/9 là thứ ba; 6/9 chủ nhật; 5/9 thứ bảy */
  A.save('attendance', { id: 's1_202609', staffId: 's1', cycle: '2026-09', days: {
    '01': { in: '08:10', out: '18:00' },      // đủ công, đúng giờ
    '02': { in: '08:41', out: '17:00' },      // muộn 6' (ân hạn 5'), về sớm 55'
    '03': { in: '08:30', out: '11:00' },      // 2.5h → nửa công
    '04': { in: '08:30', out: '09:30' },      // 1h → vắng (dưới 2h)
    '07': { leave: 'annual' } } });
  A.save('attendance', { id: 's4_202609', staffId: 's4', cycle: '2026-09', days: { '01': { in: '08:00', out: '18:00' } } });
});
await p.waitForTimeout(400);

/* --- bộ xếp loại --- */
const dg = await p.evaluate(() => {
  const A = window.APP;
  A.go('checkin'); return null;
});
await p.waitForTimeout(500);
await p.evaluate(() => document.querySelector('[data-cs="Cửa hàng A"]').click()); await p.waitForTimeout(500);
await p.evaluate(() => { const el = document.querySelector('#bcCyc'); el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(600);

const o = (d) => p.evaluate(dd => {
  const td = document.querySelector(`[data-cong="s1:${dd}"]`).closest('td');
  return { cls: [...td.classList].filter(c => /^tt-|^muon$/.test(c)).sort(), title: td.querySelector('button').title };
}, d);
let c = await o(1);
t('vào 08:10 ra 18:00 → Đủ công, không cờ', c.cls, ['tt-du']);
c = await o(2);
t('vào 08:41 (ân hạn 5′) → cờ muộn', c.cls.includes('muon'), true);
t('  ghi rõ số phút muộn = 41 − 30 − 5 = 6′', /muộn 6′/.test(c.title), true);
t('  và về sớm 60 − 5 = 55′', /về sớm 55′/.test(c.title), true);
t('  8h19m vẫn là Đủ công (cờ không đổi loại)', c.cls.includes('tt-du'), true);
c = await o(3);
t('2.5h → Nửa công', c.cls.includes('tt-nua'), true);
t('  ra 11:00 trong ca tới 12:00 → cờ về sớm 55′', c.cls.includes('muon') && /về sớm 55′/.test(c.title), true);
t('1h → Vắng dù có quẹt', (await o(4)).cls.includes('tt-vang'), true);
t('có đơn nghỉ → Nghỉ phép', (await o(7)).cls, ['tt-phep']);
t('chủ nhật không quẹt → Ngày nghỉ (mặc định nghỉ CN)', (await o(6)).cls, ['tt-nghi']);
t('thứ bảy không quẹt, đã qua → Vắng', (await o(5)).cls.includes('tt-vang') || (await o(5)).cls.includes('tt-trong'), true);

/* --- cột tổng kết + chân bảng --- */
const tk = await p.evaluate(() => {
  const tr = document.querySelector('[data-cong="s1:1"]').closest('tr');
  return [...tr.querySelectorAll('td.tk')].map(x => x.textContent.trim());
});
/* Vắng = ngày 4 (1h) + mọi ngày đã qua trong tháng 9 không quẹt, trừ chủ nhật và các ngày có dữ liệu */
const homNay = new Date(); const vangMong = (() => {
  let n = 1; for (let d = 5; d <= 30; d++) { const dt = new Date(2026, 8, d);
    if (dt < new Date(homNay.getFullYear(), homNay.getMonth(), homNay.getDate()) && dt.getDay() !== 0 && d !== 7) n++; } return n; })();
t('cột Đủ / Nửa / Vắng / Muộn của An', tk, ['2', '1', String(vangMong), '1']);
t('thẻ đầu trang đếm Vắng toàn cơ sở', await p.evaluate(() =>
  [...document.querySelectorAll('.tile')].some(x => /VẮNG/i.test(x.innerText) && /\n\s*\d+/.test(x.innerText))), true);

/* --- lọc bộ phận --- */
t('có ô lọc bộ phận khi cơ sở nhiều bộ phận', await p.evaluate(() => !!document.querySelector('#bcBp')), true);
await p.evaluate(() => { const el = document.querySelector('#bcBp'); el.value = 'Kho'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(500);
t('lọc Kho chỉ còn Chi', await p.evaluate(() => [...document.querySelectorAll('table.bcong tbody td.nv b')].map(x => x.textContent)), ['Chi']);
await p.evaluate(() => { const el = document.querySelector('#bcBp'); el.value = ''; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(400);

/* --- chế độ tổng kết --- */
await p.evaluate(() => { const el = document.querySelector('#bcTom'); el.checked = true; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(500);
t('tổng kết: giấu cột ngày', await p.evaluate(() => document.querySelectorAll('table.bcong thead th.d').length), 0);
t('  vẫn còn cột Đủ/Nửa/Vắng/Muộn', await p.evaluate(() => document.querySelectorAll('table.bcong thead th.tk').length), 4);
await p.evaluate(() => { const el = document.querySelector('#bcTom'); el.checked = false; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(400);

/* --- luật riêng cho cơ sở: cửa hàng làm cả chủ nhật, ngưỡng khác --- */
await p.evaluate(() => document.querySelector('[data-cv="shifts"]').click()); await p.waitForTimeout(500);
t('tab Ca làm việc có bảng luật', await p.evaluate(() => document.body.innerText.includes('Luật tính công')), true);
t('  dòng Mặc định hiện ngưỡng', await p.evaluate(() => /< 2h vắng · < 4h nửa/.test(document.body.innerText)), true);
await p.evaluate(() => document.querySelector('[data-luat="+"]').click()); await p.waitForTimeout(500);
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  const ins = [...d.querySelectorAll('input')];
  ins[0].value = 'Cửa hàng A';                            // cơ sở
  const byLabel = (lab) => [...d.querySelectorAll('.fld')].find(f => f.querySelector('label') && f.querySelector('label').textContent.startsWith(lab)).querySelector('input');
  byLabel('Dưới bao nhiêu giờ là Vắng').value = '3';
  byLabel('Dưới bao nhiêu giờ là Nửa công').value = '6';
  byLabel('Ân hạn đi muộn').value = '15';
  [...d.querySelectorAll('.pick button')].find(b => b.textContent === 'Làm cả tuần').click();
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(700);
t('lưu luật riêng vào settings.caLuat', await p.evaluate(() => Object.keys(window.APP.setting('caLuat', {}).theoCoSo || {})), ['Cửa hàng A']);
t('  bảng luật hiện thêm dòng cơ sở', await p.evaluate(() => document.body.innerText.includes('Làm cả tuần')), true);

await p.evaluate(() => document.querySelector('[data-cs="Cửa hàng A"]').click()); await p.waitForTimeout(500);
await p.evaluate(() => { const el = document.querySelector('#bcCyc'); el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(600);
t('luật riêng: 2.5h nay là Vắng (ngưỡng 3h)', (await o(3)).cls.includes('tt-vang'), true);
t('luật riêng: ân hạn 15′ → 08:41 không còn muộn', /muộn \d/.test((await o(2)).title), false);
t('  nhưng về sớm 17:00 vẫn còn cờ', /về sớm/.test((await o(2)).title), true);
t('luật riêng: làm cả tuần → chủ nhật không quẹt là Vắng', (await o(6)).cls, ['tt-vang']);

/* Văn phòng vẫn theo mặc định */
await p.evaluate(() => document.querySelector('[data-cs="Văn phòng"]').click()); await p.waitForTimeout(500);
await p.evaluate(() => { const el = document.querySelector('#bcCyc'); el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(500);
t('cơ sở khác vẫn theo luật mặc định (CN là ngày nghỉ)', await p.evaluate(() =>
  [...document.querySelector('[data-cong="s4:6"]').closest('td').classList].includes('tt-nghi')), true);

/* --- thêm / sửa ca --- */
await p.evaluate(() => document.querySelector('[data-cv="shifts"]').click()); await p.waitForTimeout(400);
await p.evaluate(() => document.querySelector('[data-cact="themca"]').click()); await p.waitForTimeout(400);
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]'); const ins = [...d.querySelectorAll('input')];
  ins[0].value = 'c3'; ins[1].value = 'Ca tối'; ins[2].value = '18:30'; ins[3].value = '22:00';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(600);
t('thêm ca C3 (tự viết hoa mã)', await p.evaluate(() => window.APP.setting('caLuat', {}).ca.map(c => c.id)), ['C1', 'C2', 'C3']);
await p.evaluate(() => document.querySelector('[data-cact="themca"]').click()); await p.waitForTimeout(300);
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]'); const ins = [...d.querySelectorAll('input')];
  ins[0].value = 'C4'; ins[1].value = 'Sai'; ins[2].value = '20:00'; ins[3].value = '19:00';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(400);
t('ca kết thúc trước bắt đầu bị chặn', await p.evaluate(() => window.APP.setting('caLuat', {}).ca.length), 3);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); if (d) d.querySelector('[data-x="cancel"]').click(); });

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
