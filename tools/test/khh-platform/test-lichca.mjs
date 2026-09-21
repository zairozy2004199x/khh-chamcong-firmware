/* Lịch ca, ca trống, xin đổi / nhận / trả ca có duyệt (mượn Kintai) */
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
  ['staff', 'roster', 'swaps'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  A.save('staff', { id: 'gd', name: 'Giám Đốc', code: 'GD', office: 'Cửa hàng A', status: 'active', role: 'owner' });
  A.save('staff', { id: 's1', name: 'An', code: 'NV01', office: 'Cửa hàng A', status: 'active', role: 'staff' });
  A.save('staff', { id: 's2', name: 'Bình', code: 'NV02', office: 'Cửa hàng A', status: 'active', role: 'staff' });
  A.S.me = 'Giám Đốc'; A.S.role = 'owner';
});
await p.waitForTimeout(400);
await p.evaluate(() => window.APP.go('checkin', 'cs:Cửa hàng A')); await p.waitForTimeout(500);
await p.evaluate(() => document.querySelector('[data-cv="lichca"]').click()); await p.waitForTimeout(500);
t('mở tab Lịch ca', await p.evaluate(() => document.querySelector('h1').textContent), 'Lịch ca & đổi ca');
t('lưới 7 ngày', await p.evaluate(() => document.querySelectorAll('table.lichca thead th.d').length), 7);
t('có hàng Ca trống + 3 người', await p.evaluate(() => document.querySelectorAll('table.lichca tbody tr').length), 4);

/* quản lý xếp ca cho An vào ngày đầu tuần */
const k0 = await p.evaluate(() => document.querySelector('[data-them]').getAttribute('data-them').split('|')[0]);
await p.evaluate(k => document.querySelector(`[data-them="${k}|s1"]`).click(), k0); await p.waitForTimeout(400);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); d.querySelector('[data-x="save"]').click(); }); await p.waitForTimeout(500);
t('xếp ca lưu vào roster đúng người/ngày', await p.evaluate(k => window.APP.col('roster').map(r => [r.ngay, r.staffId, r.ca]), k0), [[k0, 's1', 'C1']]);
t('  ô hiện chip ca', await p.evaluate(() => document.querySelectorAll('.cachip').length), 1);
/* xếp trùng bị chặn */
await p.evaluate(k => document.querySelector(`[data-them="${k}|s1"]`).click(), k0); await p.waitForTimeout(300);
await p.evaluate(() => document.querySelector('dialog[open] [data-x="save"]').click()); await p.waitForTimeout(400);
t('xếp cùng ca cùng người cùng ngày bị chặn', await p.evaluate(() => window.APP.col('roster').length), 1);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); if (d) d.querySelector('[data-x="cancel"]').click(); });
/* ca trống */
await p.evaluate(k => document.querySelector(`[data-them="${k}|"]`).click(), k0); await p.waitForTimeout(300);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); d.querySelectorAll('select')[0].value = 'C2'; d.querySelector('[data-x="save"]').click(); }); await p.waitForTimeout(500);
t('ca trống lưu với staffId rỗng', await p.evaluate(() => window.APP.col('roster').filter(r => !r.staffId).map(r => r.ca)), ['C2']);
t('  hiện ở hàng Ca trống, kiểu gạch đứt', await p.evaluate(() => document.querySelectorAll('tr.trong .cachip.trong').length), 1);

/* --- nhân viên An: xin đổi ca của mình cho Bình --- */
await p.evaluate(() => { window.APP.S.me = 'An'; window.APP.S.role = 'staff'; window.APP.render(); }); await p.waitForTimeout(500);
t('nhân viên không thấy nút + xếp ca', await p.evaluate(() => document.querySelectorAll('[data-them]').length), 0);
const rid = await p.evaluate(() => window.APP.col('roster').find(r => r.staffId === 's1').id);
t('ca của mình có nút xin đổi', await p.evaluate(id => !!document.querySelector(`[data-xin="${id}"]`), rid), true);
await p.evaluate(id => document.querySelector(`[data-xin="${id}"]`).click(), rid); await p.waitForTimeout(400);
await p.evaluate(() => { const d = document.querySelector('dialog[open]'); d.querySelector('select').value = 's2'; d.querySelector('input').value = 'việc nhà'; d.querySelector('[data-x="save"]').click(); });
await p.waitForTimeout(500);
t('tạo yêu cầu đổi ca chờ duyệt', await p.evaluate(() => window.APP.col('swaps').map(x => [x.loai, x.tu, x.den, x.tt])), [['doi', 's1', 's2', 'cho']]);
t('  chip ca đánh dấu đang chờ', await p.evaluate(() => document.querySelectorAll('.cachip.xin').length), 1);
await p.evaluate(id => document.querySelector(`[data-xin="${id}"]`).click(), rid); await p.waitForTimeout(300);
t('  xin lần hai cùng ca bị chặn', await p.evaluate(() => window.APP.col('swaps').length), 1);
/* An xin nhận ca trống */
const ridTrong = await p.evaluate(() => window.APP.col('roster').find(r => !r.staffId).id);
p.once('dialog', d => d.accept());
await p.evaluate(id => document.querySelector(`[data-nhan="${id}"]`).click(), ridTrong); await p.waitForTimeout(500);
t('xin nhận ca trống tạo yêu cầu "nhan"', await p.evaluate(() => window.APP.col('swaps').filter(x => x.loai === 'nhan').map(x => x.den)), ['s1']);
t('nhân viên không có nút duyệt', await p.evaluate(() => document.querySelectorAll('[data-duyet]').length), 0);

/* --- quản lý duyệt --- */
await p.evaluate(() => { window.APP.S.me = 'Giám Đốc'; window.APP.S.role = 'owner'; window.APP.render(); }); await p.waitForTimeout(500);
t('quản lý thấy 2 yêu cầu chờ', await p.evaluate(() => document.querySelectorAll('[data-duyet$=":1"]').length), 2);
const idDoi = await p.evaluate(() => window.APP.col('swaps').find(x => x.loai === 'doi').id);
await p.evaluate(id => document.querySelector(`[data-duyet="${id}:1"]`).click(), idDoi); await p.waitForTimeout(500);
t('duyệt đổi: ca chuyển sang Bình', await p.evaluate(id => window.APP.find('roster', id).staffId, rid), 's2');
t('  yêu cầu ghi duyệt, có người và lúc', await p.evaluate(id => { const x = window.APP.find('swaps', id); return x.tt === 'duyet' && x.quyetBoi === 'Giám Đốc' && !!x.quyetLuc; }, idDoi), true);
const idNhan = await p.evaluate(() => window.APP.col('swaps').find(x => x.loai === 'nhan').id);
await p.evaluate(id => document.querySelector(`[data-duyet="${id}:0"]`).click(), idNhan); await p.waitForTimeout(500);
t('từ chối nhận: ca vẫn trống', await p.evaluate(id => window.APP.find('roster', id).staffId, ridTrong), '');
t('  yêu cầu ghi từ chối', await p.evaluate(id => window.APP.find('swaps', id).tt, idNhan), 'tuchoi');
t('hết yêu cầu chờ', await p.evaluate(() => document.querySelectorAll('[data-duyet]').length), 0);

/* --- tuần trước / sau --- */
const th0 = await p.evaluate(() => document.querySelector('table.lichca thead th.d b').textContent);
await p.evaluate(() => document.querySelector('[data-cact="tuansau"]').click()); await p.waitForTimeout(400);
t('sang tuần sau đổi cột ngày', await p.evaluate(() => document.querySelector('table.lichca thead th.d b').textContent) !== th0, true);
await p.evaluate(() => document.querySelector('[data-cact="tuannay"]').click()); await p.waitForTimeout(400);
t('về tuần này', await p.evaluate(() => document.querySelector('table.lichca thead th.d b').textContent), th0);

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
