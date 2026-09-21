/* Tìm nhanh Ctrl+K */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
let fail = 0;
const t = (n, g, w) => { const ok = JSON.stringify(g) === JSON.stringify(w); if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(56)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`); };
const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1400, height: 900 } })).newPage();
const errs = [];
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));
await p.goto('file://' + SP + '/build/index.html'); await p.waitForTimeout(1300);
await p.evaluate(() => {
  const A = window.APP;
  ['staff', 'projects', 'tasks'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  A.save('staff', { id: 's1', name: 'Nguyễn Thị Minh Thư', code: 'MNNV2KVC0008', office: 'KHU VUI CHƠI FUNFEST', title: 'Thu ngân', status: 'active' });
  A.save('staff', { id: 's2', name: 'Trương Tuấn Hào', code: 'MNNV2KVC0118', office: 'FF SC', status: 'active' });
  A.save('staff', { id: 's3', name: 'Người Đã Nghỉ', code: 'X', status: 'left' });
  A.save('projects', { id: 'p1', name: 'FUNFEST - VINPEARL PQ', group: 'Khu vui chơi', lists: [] });
  A.save('tasks', { id: 't1', pid: 'p1', title: 'Lắp máy chấm công Phú Quốc', status: 'todo', assignee: 'Trương Tuấn Hào' });
  A.save('tasks', { id: 't2', pid: 'p1', title: 'Việc đã xong', status: 'done' });
});
await p.waitForTimeout(400);

const tim = q => p.evaluate(qq => window.APP.timNhanh(qq).map(x => [x.loai, x.ten]), q);
t('gõ không dấu vẫn tìm ra người có dấu', (await tim('minh thu'))[0], ['Nhân sự', 'Nguyễn Thị Minh Thư']);
t('tìm theo mã nhân sự', (await tim('0118'))[0], ['Nhân sự', 'Trương Tuấn Hào']);
t('người đã nghỉ không hiện', (await tim('đã nghỉ')).some(x => x[1] === 'Người Đã Nghỉ'), false);
t('tìm dự án', (await tim('vinpearl'))[0], ['Dự án', 'FUNFEST - VINPEARL PQ']);
t('tìm công việc còn mở', (await tim('lắp máy'))[0], ['Công việc', 'Lắp máy chấm công Phú Quốc']);
t('  việc đã xong không hiện', (await tim('đã xong')).length, 0);
t('tìm cơ sở', (await tim('ff sc'))[0], ['Cơ sở', 'FF SC']);
t('tìm màn hình', (await tim('bảng lương'))[0], ['Màn hình', 'Bảng lương']);
t('khớp đầu tên xếp trước khớp giữa', (await tim('thư'))[0][1], 'Nguyễn Thị Minh Thư');
t('chuỗi rỗng → không có gì', (await tim('')).length, 0);
t('nhiều từ: mọi từ phải khớp', (await tim('minh hào')).length, 0);

/* --- mở bằng phím, đi bằng mũi tên --- */
t('có nút tìm trên thanh biểu tượng', await p.evaluate(() => !!document.querySelector('#railFind')), true);
await p.keyboard.press('Control+KeyK'); await p.waitForTimeout(300);
t('Ctrl+K mở bảng lệnh', await p.evaluate(() => !!document.querySelector('dialog.pal[open]')), true);
t('  ô gõ được lấy tiêu điểm', await p.evaluate(() => document.activeElement && document.activeElement.id), 'palQ');
await p.keyboard.type('funfest'); await p.waitForTimeout(250);
const rows = await p.evaluate(() => [...document.querySelectorAll('.palrow')].map(r => r.querySelector('.loai').textContent));
t('kết quả gồm cả cơ sở và dự án', rows.includes('Cơ sở') && rows.includes('Dự án'), true);
const dau = await p.evaluate(() => document.querySelector('.palrow.on b').textContent);
await p.keyboard.press('ArrowDown'); await p.waitForTimeout(150);
t('mũi tên xuống đổi dòng chọn', await p.evaluate(() => document.querySelector('.palrow.on b').textContent) !== dau, true);
await p.keyboard.press('ArrowUp'); await p.waitForTimeout(150);
/* Enter trên dòng đầu: hoặc dự án hoặc cơ sở tuỳ điểm — kiểm bằng cái đầu tiên */
const loaiDau = await p.evaluate(() => document.querySelector('.palrow.on .loai').textContent);
await p.keyboard.press('Enter'); await p.waitForTimeout(700);
t('Enter đóng bảng và chuyển màn', await p.evaluate(() => !document.querySelector('dialog.pal[open]')), true);
t('  tới đúng ứng dụng', await p.evaluate(() => window.APP.cur.id), loaiDau === 'Dự án' ? 'wework' : 'checkin');

/* chọn thẳng người → mở hồ sơ */
await p.keyboard.press('Control+KeyK'); await p.waitForTimeout(250);
await p.keyboard.type('tuan hao'); await p.waitForTimeout(250);
await p.keyboard.press('Enter'); await p.waitForTimeout(700);
t('chọn người → sang Hồ sơ nhân sự đúng người', await p.evaluate(() => window.APP.cur.id === 'hrm' && document.body.innerText.includes('Trương Tuấn Hào')), true);

/* chọn cơ sở → bảng công của cơ sở đó */
await p.keyboard.press('Control+KeyK'); await p.waitForTimeout(250);
await p.keyboard.type('ff sc'); await p.waitForTimeout(250);
await p.keyboard.press('Enter'); await p.waitForTimeout(800);
t('chọn cơ sở → Bảng công cơ sở của FF SC', await p.evaluate(() =>
  window.APP.cur.id === 'checkin' && document.querySelector('h1').textContent.includes('Bảng công') && document.querySelector('#bcCs').value === 'FF SC'), true);

await p.keyboard.press('Control+KeyK'); await p.waitForTimeout(200);
await p.keyboard.press('Escape'); await p.waitForTimeout(200);
t('Esc đóng', await p.evaluate(() => !document.querySelector('dialog.pal[open]')), true);

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
