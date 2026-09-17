/* Kiểm thử mảng kinh doanh: tách nhân sự theo mảng, bộ phận dùng chung */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';

let fail = 0;
function t(name, got, want) {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(58)} got=${JSON.stringify(got)}${ok ? '' : ' want=' + JSON.stringify(want)}`);
}

const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font/i.test(m.text())) errs.push('console: ' + m.text()); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1300);

/* Posh và HVC là hai mảng; Phòng kế toán dùng chung cho cả hai. */
await p.evaluate(() => {
  const A = window.APP;
  ['staff', 'depts', 'settings', 'payrolls'].forEach(c => A.col(c).slice().forEach(d => A.remove(c, d.id)));
  const mk = (id, name, dept, unit, role, salary) =>
    A.save('staff', { id, code: id, name, dept, unit, role: role || 'staff', status: 'active', start: '2024-01-01', salary: salary || 10000000 });
  mk('p1', 'Posh Một', 'Phòng kinh doanh', 'Posh', 'manager', 20000000);
  mk('p2', 'Posh Hai', 'Phòng kinh doanh', 'Posh');
  mk('p3', 'Posh Ba', 'Phòng kỹ thuật', 'Posh');
  mk('h1', 'HVC Một', 'Phòng kinh doanh', 'HVC', 'manager', 20000000);
  mk('h2', 'HVC Hai', 'Phòng kỹ thuật', 'HVC');
  mk('c1', 'Kế toán Chung', 'Phòng kế toán', '');          // dùng chung
  mk('c2', 'Hành chính Chung', 'Phòng kinh doanh', '');    // dùng chung, cùng bộ phận với Posh Một
  mk('boss', 'Quang Thắng', 'Phòng kỹ thuật', '', 'owner', 30000000);
  A.save('settings', { id: 'units', list: ['Posh', 'HVC'], scope: 1 });
  A.S.me = 'Quang Thắng';
});
await p.waitForTimeout(400);

await p.click('[data-go="hrm"]'); await p.waitForTimeout(400);

/* ---- thanh bên có nhóm mảng ---- */
const sideUnits = () => p.evaluate(() => Array.from(document.querySelectorAll('.side [data-unit]'))
  .map(b => ({ ten: b.querySelector('.t').innerText.trim(), n: b.querySelector('.n').innerText.trim() })));
t('thanh bên liệt kê đủ mảng + dùng chung', (await sideUnits()).map(u => u.ten),
  ['Tất cả các mảng', 'HVC', 'Posh', 'Dùng chung']);
t('đếm đúng người từng mảng', (await sideUnits()).map(u => u.n), ['8', '2', '3', '3']);

/* ---- lọc theo mảng: thấy người của mảng + người dùng chung ---- */
const names = () => p.evaluate(() => Array.from(document.querySelectorAll('#content tbody tr td:first-child b')).map(b => b.innerText.trim()));
t('chưa lọc thì thấy hết', (await names()).length, 8);

await p.click('.side [data-unit="Posh"]'); await p.waitForTimeout(500);
t('lọc Posh: người Posh + người dùng chung', (await names()).sort(),
  ['Hành chính Chung', 'Kế toán Chung', 'Posh Ba', 'Posh Hai', 'Posh Một', 'Quang Thắng']);
t('không lẫn người HVC', (await names()).some(n => n.startsWith('HVC')), false);
t('có nhắc số người dùng chung', await p.evaluate(() =>
  (document.querySelector('#content .by') || {}).innerText || ''), 'Đang xem mảng Posh — gồm cả 3 người Dùng chung (làm cho mọi mảng).');

await p.click('.side [data-unit="Dùng chung"]'); await p.waitForTimeout(500);
t('lọc Dùng chung: chỉ người chưa khai mảng', (await names()).sort(),
  ['Hành chính Chung', 'Kế toán Chung', 'Quang Thắng']);

/* ---- bộ phận vẫn dùng chung, không bị nhân đôi ---- */
await p.click('.side [data-unit=""]'); await p.waitForTimeout(400);
await p.click('.side [data-v="depts"]'); await p.waitForTimeout(400);
const deptRows = () => p.evaluate(() => Array.from(document.querySelectorAll('#content tbody tr')).map(tr => ({
  ten: tr.querySelector('[data-dept]').innerText.trim(), n: tr.querySelectorAll('td')[1].innerText.trim() })));
t('bộ phận không bị tách theo mảng', (await deptRows()).map(r => r.ten).sort(),
  ['Phòng kinh doanh', 'Phòng kế toán', 'Phòng kỹ thuật']);
t('Phòng kinh doanh gộp cả Posh lẫn HVC', (await deptRows()).find(r => r.ten === 'Phòng kinh doanh').n, '4');

/* ---- gán mảng hàng loạt theo bộ phận ---- */
await p.click('.side [data-v="units"]'); await p.waitForTimeout(400);
await p.click('[data-uact="bulk"]'); await p.waitForTimeout(300);
await p.selectOption('dialog #f_0', 'Phòng kế toán');
await p.selectOption('dialog #f_1', 'HVC');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(700);
t('gán cả bộ phận vào một mảng', await p.evaluate(() =>
  window.APP.find('staff', 'c1').unit), 'HVC');
t('bộ phận khác không bị đụng', await p.evaluate(() =>
  [window.APP.find('staff', 'c2').unit, window.APP.find('staff', 'p1').unit]), ['', 'Posh']);

/* trả lại dùng chung để thử tiếp */
await p.evaluate(() => {
  const A = window.APP;
  ['c1'].forEach(id => A.save('staff', Object.assign({}, A.find('staff', id), { unit: '' })));
});
await p.waitForTimeout(400);

/* ---- đổi tên mảng: cập nhật mọi hồ sơ ---- */
await p.click('[data-uedit="Posh"]'); await p.waitForTimeout(300);
await p.fill('dialog #f_0', 'Posh Retail');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(700);
t('đổi tên mảng cập nhật hết hồ sơ', await p.evaluate(() =>
  window.APP.col('staff').filter(s => s.unit === 'Posh Retail').length), 3);
t('danh mục mảng cũng đổi theo', await p.evaluate(() => window.APP.unitNames()), ['HVC', 'Posh Retail']);
t('không còn hồ sơ nào ở tên cũ', await p.evaluate(() =>
  window.APP.col('staff').filter(s => s.unit === 'Posh').length), 0);

/* ---- không cho thêm mảng trùng tên ---- */
await p.click('[data-uact="new"]'); await p.waitForTimeout(300);
await p.fill('dialog #f_0', 'hvc');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(400);
t('chặn thêm mảng trùng tên', await p.evaluate(() => window.APP.unitNames().length), 2);
t('hộp thoại vẫn mở', await p.evaluate(() => !!document.querySelector('dialog[open]')), true);
await p.click('dialog [data-x="cancel"]').catch(() => {});
await p.waitForTimeout(300);

/* ---- ô Mảng trong hồ sơ nhân sự ---- */
await p.click('.side [data-v="list"]'); await p.waitForTimeout(300);
await p.click('.topbar [data-act="new"]'); await p.waitForTimeout(300);
t('hồ sơ có ô chọn mảng, mặc định Dùng chung', await p.evaluate(() => {
  const sel = document.querySelector('dialog #f_4');
  return sel ? { opts: Array.from(sel.options).map(o => o.text), chon: sel.value } : null;
}), { opts: ['Dùng chung', 'HVC', 'Posh Retail'], chon: '' });
await p.click('dialog [data-x="cancel"]'); await p.waitForTimeout(300);

/* ---- phạm vi quyền theo mảng ---- */
const sc = await p.evaluate(() => {
  const A = window.APP;
  A.S.me = 'Posh Một';                       // Quản lý, mảng Posh Retail, Phòng kinh doanh
  const r = {
    batMacDinh: A.unitScopeOn(),
    vaiTro: A.role(),
    cungMangCungBoPhan: A.canFor('timeoff.approve', 'Posh Hai'),
    khacMangCungBoPhan: A.canFor('timeoff.approve', 'HVC Một'),
    nguoiDungChungCungBoPhan: A.canFor('timeoff.approve', 'Hành chính Chung'),
    nguoiDungChungKhacBoPhan: A.canFor('timeoff.approve', 'Kế toán Chung'),
    cungMangKhacBoPhan: A.canFor('timeoff.approve', 'Posh Ba'),
  };
  A.S.me = 'Quang Thắng';                    // Chủ sở hữu, để Dùng chung
  r.chuSoHuuThayHet = A.inScope('HVC Một') && A.inScope('Posh Hai');
  return r;
});
t('mặc định bật tách quyền theo mảng', sc.batMacDinh, true);
t('Posh Một là Quản lý', sc.vaiTro, 'manager');
t('cùng mảng + cùng bộ phận: được', sc.cungMangCungBoPhan, true);
t('KHÁC mảng dù cùng bộ phận: bị chặn', sc.khacMangCungBoPhan, false);
t('người dùng chung cùng bộ phận: quản được', sc.nguoiDungChungCungBoPhan, true);
t('người dùng chung khác bộ phận: vẫn bị giới hạn bộ phận chặn', sc.nguoiDungChungKhacBoPhan, false);
t('cùng mảng nhưng khác bộ phận: bị chặn (giới hạn bộ phận)', sc.cungMangKhacBoPhan, false);
t('Chủ sở hữu thấy mọi mảng', sc.chuSoHuuThayHet, true);

/* ---- tắt giới hạn bộ phận, chỉ giữ tách mảng ---- */
const sc2 = await p.evaluate(() => {
  const A = window.APP;
  A.save('settings', { id: 'scope', dept: 0 });
  A.S.me = 'Posh Một';
  return {
    cungMangKhacBoPhan: A.canFor('timeoff.approve', 'Posh Ba'),
    khacMang: A.canFor('timeoff.approve', 'HVC Hai'),
    ghiChu: A.scopeNote(),
  };
});
t('tắt giới hạn bộ phận: cùng mảng khác bộ phận được', sc2.cungMangKhacBoPhan, true);
t('vẫn chặn khác mảng', sc2.khacMang, false);
t('câu giải thích phạm vi đúng', sc2.ghiChu, 'mảng Posh Retail (cộng người dùng chung)');

/* ---- tắt luôn tách mảng thì về như cũ ---- */
const sc3 = await p.evaluate(() => {
  const A = window.APP;
  A.save('settings', { id: 'units', list: A.unitNames(), scope: 0 });
  A.S.me = 'Posh Một';
  return { batTat: A.unitScopeOn(), khacMang: A.canFor('timeoff.approve', 'HVC Hai'), ghiChu: A.scopeNote() };
});
t('tắt được tách mảng', sc3.batTat, false);
t('tắt rồi thì quản được mọi mảng', sc3.khacMang, true);
t('không còn câu giới hạn nào', sc3.ghiChu, '');

/* ---- bảng lương tách theo mảng, cộng lại đúng tổng ---- */
await p.evaluate(() => {
  const A = window.APP;
  A.save('settings', { id: 'scope', dept: 1 });
  A.save('settings', { id: 'units', list: A.unitNames(), scope: 1 });
  A.S.me = 'Quang Thắng';
});
await p.waitForTimeout(300);
await p.click('[data-go="payroll"]'); await p.waitForTimeout(500);
await p.click('.side [data-act="calc"]').catch(() => {});
await p.waitForTimeout(1200);

const tong = async () => p.evaluate(() => {
  const f = document.querySelector('#content tfoot td.n');
  const rows = document.querySelectorAll('#content tbody tr').length;
  return { tong: f ? f.innerText.replace(/[^\d]/g, '') : '', rows };
});
const all = await tong();
t('bảng lương có đủ 8 người', all.rows, 8);

await p.click('[data-punit="Posh Retail"]'); await p.waitForTimeout(500);
const posh = await tong();
await p.click('[data-punit="HVC"]'); await p.waitForTimeout(500);
const hvc = await tong();
await p.click('[data-punit="Dùng chung"]'); await p.waitForTimeout(500);
const chung = await tong();
t('ba mảng cộng lại đủ số người', posh.rows + hvc.rows + chung.rows, all.rows);
t('ba mảng cộng lại đúng tổng thực nhận',
  Number(posh.tong) + Number(hvc.tong) + Number(chung.tong), Number(all.tong));
t('mảng Dùng chung không lẫn vào mảng khác', chung.rows, 3);

await p.click('[data-punit=""]'); await p.waitForTimeout(400);
await p.click('[data-go="hrm"]'); await p.waitForTimeout(400);
await p.click('.side [data-v="units"]'); await p.waitForTimeout(600);
await p.screenshot({ path: SP + '/unit-screen.png' });
await p.click('.side [data-v="report"]'); await p.waitForTimeout(700);
await p.screenshot({ path: SP + '/unit-report.png' });

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 8).join('\n') : '\nKhông có lỗi JS nào trên trang');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
