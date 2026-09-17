/* Kiểm thử ô Bộ phận trong hộp Sửa dự án: nhớ lại giá trị cũ để chọn lại */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';

let fail = 0;
const t = (n, g, w) => {
  const ok = JSON.stringify(g) === JSON.stringify(w);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(54)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`);
};

const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1400, height: 950 } })).newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font/i.test(m.text())) errs.push('console: ' + m.text()); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1300);

await p.evaluate(() => {
  const A = window.APP;
  ['tasks', 'projects', 'depts', 'staff'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  /* sổ bộ phận của Nhân sự */
  A.save('depts', { id: 'd1', name: 'KHU VUI CHƠI' });
  A.save('depts', { id: 'd2', name: 'Kế toán' });
  /* bộ phận chỉ ghi trên hồ sơ nhân viên, chưa có trong sổ */
  A.save('staff', { id: 's1', name: 'Quang Thắng', dept: 'Bảo trì', role: 'owner', status: 'active' });
  /* bộ phận chỉ từng gõ ở một dự án khác */
  A.save('projects', { id: 'p9', name: 'Dự án cũ', group: 'Sự kiện', members: [], lists: [] });
  A.save('projects', { id: 'p1', name: 'FUNFEST - VINPEARL PQ', group: 'KHU VUI CHƠI',
    members: ['admin'], lists: [{ id: 'default', name: 'Chưa phân loại' }] });
  A.S.me = 'Quang Thắng';
});
await p.waitForTimeout(400);

/* danh sách gộp đủ ba nguồn, sắp xếp tiếng Việt, không trùng */
t('gộp đủ bộ phận từ sổ, hồ sơ và dự án cũ', await p.evaluate(() =>
  window.APP.deptAll(window.APP.col('projects').map(x => x.group))),
  ['Bảo trì', 'Kế toán', 'KHU VUI CHƠI', 'Sự kiện']);
t('trùng tên (khác hoa thường) chỉ còn một', await p.evaluate(() =>
  window.APP.deptAll(['khu vui chơi', 'Khu Vui Chơi']).length), 3);
t('bỏ qua ô trống', await p.evaluate(() =>
  window.APP.deptAll(['', '  ', null, undefined]).length), 3);

await p.click('[data-go="wework"]'); await p.waitForTimeout(600);
const moSua = async () => {
  await p.evaluate(() => { document.querySelector('[data-proj="p1"]').click(); });
  await p.waitForTimeout(500);
  await p.evaluate(() => { document.querySelector('[data-act="editproj"]').click(); });
  await p.waitForTimeout(600);
};
const oBoPhan = () => p.evaluate(() => {
  const inp = document.querySelector('dialog[open] input[list]');
  if (!inp) return null;
  const list = document.getElementById(inp.getAttribute('list'));
  return { value: inp.value, nhan: inp.closest('.fld').querySelector('label').textContent,
    goiY: list ? [...list.options].map(o => o.value) : null };
});
await moSua();

t('mở đúng hộp Sửa dự án của FUNFEST', await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  return d.querySelector('h3').textContent === 'Sửa dự án' &&
    d.querySelector('input').value === 'FUNFEST - VINPEARL PQ';
}), true);

const o = await oBoPhan();
t('ô Bộ phận có sổ gợi ý kèm theo', !!o && Array.isArray(o.goiY), true);
t('  nhãn là tiếng Việt, không còn "Department"', o.nhan, 'Bộ phận');
t('  gợi ý đúng những bộ phận đã dùng', o.goiY, ['Bảo trì', 'Kế toán', 'KHU VUI CHƠI', 'Sự kiện']);
t('  giữ nguyên giá trị đang có của dự án', o.value, 'KHU VUI CHƠI');
const dl = o.goiY;

/* gõ lại một bộ phận cũ nhưng sai hoa thường và thừa khoảng trắng */
const luu = async val => {
  await p.evaluate(v => { document.querySelector('dialog[open] input[list]').value = v; }, val);
  await p.evaluate(() => document.querySelector('dialog[open] [data-x="save"]').click());
  await p.waitForTimeout(700);
  return p.evaluate(() => window.APP.find('projects', 'p1').group);
};
t('gõ sai hoa thường thì lấy lại đúng cách viết cũ', await luu(' kế toán '), 'Kế toán');

/* bộ phận mới gõ lần đầu thì giữ nguyên, lần sau đã có trong sổ gợi ý */
await moSua();
t('bộ phận mới được giữ nguyên', await luu('Hậu cần'), 'Hậu cần');

await moSua();
const dl2 = (await oBoPhan()).goiY;
t('lần sau bộ phận mới đã nằm trong sổ gợi ý', dl2.indexOf('Hậu cần') >= 0, true);
t('  bộ phận cũ không mất đi', dl2.indexOf('KHU VUI CHƠI') >= 0, true);
t('  không sinh thêm bản trùng vì gõ khác hoa thường',
  dl2.filter(x => x.toLowerCase() === 'kế toán').length, 1);

await p.screenshot({ path: SP + '/bophan.png' });
const dlg = await p.$('dialog[open]');
if (dlg) await dlg.screenshot({ path: SP + '/bophan.png' });

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
