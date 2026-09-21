/* Công việc: chế độ Bảng tính (sửa thẳng ô) và Gantt — mượn Plane */
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
await p.goto('file://' + SP + '/build/index.html'); await p.waitForTimeout(1300);
await p.evaluate(() => {
  const A = window.APP;
  ['projects', 'tasks'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  A.save('projects', { id: 'p1', name: 'Dự án G', members: ['Giám Đốc'], lists: [{ id: 'default', name: 'Chưa phân loại' }, { id: 'l2', name: 'Thi công' }] });
  const d = (n) => { const x = new Date(); x.setDate(x.getDate() + n); x.setHours(9, 0, 0, 0); return x.toISOString(); };
  A.save('tasks', { id: 't1', pid: 'p1', title: 'Khảo sát', status: 'todo', assignee: 'Giám Đốc', start: d(-3), due: d(2), list: 'default', order: 1 });
  A.save('tasks', { id: 't2', pid: 'p1', title: 'Quá hạn rồi', status: 'doing', due: d(-1), list: 'l2', order: 2 });
  A.save('tasks', { id: 't3', pid: 'p1', title: 'Không hạn', status: 'todo', list: 'default', order: 3 });
  A.S.me = 'Giám Đốc';
});
await p.waitForTimeout(400);
await p.click('[data-go="wework"]'); await p.waitForTimeout(600);
await p.evaluate(() => document.querySelector('[data-proj="p1"]').click()); await p.waitForTimeout(500);

/* --- bảng tính --- */
t('có bốn chế độ xem', await p.evaluate(() => [...document.querySelectorAll('.seg [data-mode]')].map(x => x.getAttribute('data-mode'))), ['list', 'board', 'sheet', 'gantt']);
await p.evaluate(() => document.querySelector('[data-mode="sheet"]').click()); await p.waitForTimeout(500);
t('bảng tính có 3 dòng việc', await p.evaluate(() => document.querySelectorAll('table.sheet tbody tr[data-srow]').length), 3);
const doiO = async (id, k, v) => {
  await p.evaluate(a => { const el = document.querySelector(`[data-sheet="${a[0]}:${a[1]}"]`); el.value = a[2]; el.dispatchEvent(new Event('change', { bubbles: true })); }, [id, k, v]);
  await p.waitForTimeout(500);
};
await doiO('t1', 'title', 'Khảo sát mặt bằng');
t('sửa tên ngay trên ô', await p.evaluate(() => window.APP.find('tasks', 't1').title), 'Khảo sát mặt bằng');
t('  có ghi lịch sử', await p.evaluate(() => (window.APP.find('tasks', 't1').log || []).slice(-1)[0].tx), 'đổi tên → “Khảo sát mặt bằng” trên bảng tính');
await doiO('t1', 'status', 'done');
t('đổi trạng thái → done, có doneAt', await p.evaluate(() => { const x = window.APP.find('tasks', 't1'); return x.status === 'done' && !!x.doneAt; }), true);
await doiO('t3', 'list', 'l2');
t('đổi nhóm → cập nhật list và order', await p.evaluate(() => { const x = window.APP.find('tasks', 't3'); return x.list === 'l2' && typeof x.order === 'number'; }), true);
await doiO('t3', 'due', '2026-12-25');
t('đặt hạn chót từ ô ngày', await p.evaluate(() => (window.APP.find('tasks', 't3').due || '').slice(0, 10)), '2026-12-25');
await doiO('t3', 'assignee', 'Giám Đốc');
t('giao người từ ô', await p.evaluate(() => window.APP.find('tasks', 't3').assignee), 'Giám Đốc');
await doiO('t2', 'title', '   ');
t('tên trống bị bỏ qua, giữ tên cũ', await p.evaluate(() => window.APP.find('tasks', 't2').title), 'Quá hạn rồi');
t('ô hạn quá khứ tô đỏ', await p.evaluate(() => document.querySelector('[data-sheet="t2:due"]').classList.contains('bad')), true);

/* --- gantt --- */
await p.evaluate(() => document.querySelector('[data-mode="gantt"]').click()); await p.waitForTimeout(500);
const g = await p.evaluate(() => ({
  thanh: [...document.querySelectorAll('.g-bar')].map(x => [x.textContent, [...x.classList].filter(c => /^st-|late/.test(c)).sort()]),
  ngay: document.querySelectorAll('.g-d').length,
  hom: document.querySelectorAll('.g-d.hom').length,
  ten: [...document.querySelectorAll('.g-name span')].map(x => x.textContent)
}));
t('Gantt vẽ 3 thanh (t3 nay có hạn)', g.thanh.length, 3);
t('  việc xong tô xanh', g.thanh.find(x => x[0] === 'Khảo sát mặt bằng')[1], ['st-done']);
t('  việc quá hạn tô đỏ', g.thanh.find(x => x[0] === 'Quá hạn rồi')[1].includes('late'), true);
t('  có đúng một cột "hôm nay"', g.hom, 1);
t('  trục ngày phủ tới hạn xa nhất (25/12)', g.ngay >= 90, true);
const rong = await p.evaluate(() => {
  const w = parseFloat(getComputedStyle(document.querySelector('.gantt')).getPropertyValue('--gw'));
  const b = [...document.querySelectorAll('.g-bar')].find(x => x.textContent === 'Khảo sát mặt bằng');
  return Math.round((parseFloat(b.style.width) + 4) / w);
});
t('  thanh dài đúng số ngày (−3 → +2 = 6 ngày)', rong, 6);
await p.evaluate(() => [...document.querySelectorAll('.g-bar')].find(x => x.textContent === 'Quá hạn rồi').click()); await p.waitForTimeout(500);
/* tên việc nằm trong ô nhập của hộp chi tiết, innerText không thấy — đọc value */
t('bấm thanh mở chi tiết việc', await p.evaluate(() => { const d = document.querySelector('dialog[open]');
  return !!d && [...d.querySelectorAll('input')].some(i => i.value === 'Quá hạn rồi'); }), true);

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
