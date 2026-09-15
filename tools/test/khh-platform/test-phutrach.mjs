/* Kiểm thử: chọn nhóm công việc thì người phụ trách hiện sẵn */
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
  ['tasks', 'projects'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  A.save('projects', { id: 'p1', name: 'FUNFEST', members: ['Giám Đốc', 'Anh Tuấn'], lists: [
    { id: 'default', name: 'Chưa phân loại' },
    { id: 'ks', name: 'Khảo Sát Mặt Bằng' },
    { id: 'tc', name: 'Thi Công' },
    { id: 'moi', name: 'Nhóm chưa có việc' }] });
  const mk = (id, list, who) => A.save('tasks', { id, pid: 'p1', list, title: 'Việc ' + id,
    assignee: who, status: 'todo', order: 1, subs: [], cmts: [], log: [] });
  /* nhóm Khảo sát: chỉ một người */
  mk('k1', 'ks', 'Giám Đốc'); mk('k2', 'ks', 'giám đốc');
  /* nhóm Thi công: hai người, Anh Tuấn nhiều việc hơn */
  mk('t1', 'tc', 'Anh Tuấn'); mk('t2', 'tc', 'Anh Tuấn'); mk('t3', 'tc', 'Chị Hoa');
  mk('t4', 'tc', '');
  A.S.me = 'Quang Thắng';
});
await p.waitForTimeout(400);

await p.click('[data-go="wework"]'); await p.waitForTimeout(700);
await p.evaluate(() => document.querySelector('[data-proj="p1"]').click());
await p.waitForTimeout(500);

/* mở hộp Công việc mới */
const moMoi = async () => {
  await p.evaluate(() => {
    const el = document.querySelector('[data-act="newtask"],[data-newtask],[data-act="addtask"]');
    if (el) el.click();
  });
  await p.waitForTimeout(500);
};
await moMoi();
t('mở được hộp Công việc mới', await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  return !!d && d.querySelector('h3').textContent === 'Công việc mới';
}), true);

const doc = () => p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  const sel = d.querySelector('select');
  const flds = [...d.querySelectorAll('.fld')];
  const f = flds.find(x => /phụ trách/i.test(x.querySelector('label') ? x.querySelector('label').textContent : ''));
  const h = f.querySelector('.hint');
  return { nhom: sel.value, ai: f.querySelector('input').value,
    nhac: h.hidden ? '' : h.textContent, chon: [...h.querySelectorAll('[data-goiy]')].map(x => x.textContent) };
});
const chonNhom = async v => {
  await p.evaluate(val => {
    const sel = document.querySelector('dialog[open] select');
    sel.value = val;
    sel.dispatchEvent(new Event('change', { bubbles: true }));
  }, v);
  await p.waitForTimeout(250);
};

/* nhóm chưa có việc nào: không đoán bừa, giữ nguyên người đang đăng nhập */
await chonNhom('moi');
let d = await doc();
t('nhóm chưa có việc thì không gợi ý gì', d.nhac, '');
t('  giữ nguyên người đang đăng nhập', d.ai, 'Quang Thắng');

/* nhóm một người: điền luôn */
await chonNhom('ks');
d = await doc();
t('nhóm một người thì điền sẵn luôn', d.ai, 'Giám Đốc');
t('  nói rõ vì sao điền', d.nhac, 'Nhóm này vẫn do Giám Đốc phụ trách.');
t('  không đếm thành hai người vì viết hoa khác nhau', d.chon, []);

/* nhóm hai người: không tự chọn, bày ra cho bấm */
await chonNhom('tc');
d = await doc();
t('nhóm nhiều người thì KHÔNG tự chọn thay', d.ai, 'Giám Đốc');
t('  bày ra đủ người để bấm chọn', d.chon, ['Anh Tuấn', 'Chị Hoa']);
t('  người làm nhiều việc hơn đứng trước', d.chon[0], 'Anh Tuấn');
t('  bỏ qua việc chưa giao cho ai', d.chon.indexOf('') < 0, true);

await p.evaluate(() => [...document.querySelectorAll('dialog[open] [data-goiy]')]
  .find(x => x.getAttribute('data-goiy') === 'Chị Hoa').click());
await p.waitForTimeout(250);
t('bấm vào tên thì điền vào ô phụ trách', (await doc()).ai, 'Chị Hoa');

/* đổi lại nhóm một người thì ghi đè lựa chọn cũ */
await chonNhom('ks');
t('đổi sang nhóm một người thì điền lại', (await doc()).ai, 'Giám Đốc');

/* lưu xong việc mới thì đúng người, đúng nhóm */
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  d.querySelector('input').value = 'Đo đạc mặt bằng';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(700);
const moi = await p.evaluate(() => window.APP.col('tasks')
  .filter(x => x.title === 'Đo đạc mặt bằng').map(x => ({ list: x.list, ai: x.assignee })));
t('lưu ra đúng nhóm và đúng người', moi, [{ list: 'ks', ai: 'Giám Đốc' }]);

/* mở lại lần nữa: gợi ý chạy ngay lúc mở, không phải bấm đổi mới thấy */
await moMoi();
await chonNhom('tc');
await p.evaluate(() => document.querySelector('dialog[open] [data-x="cancel"]').click());
await p.waitForTimeout(300);
await moMoi();
d = await doc();
t('mở hộp mới là đã gợi ý sẵn theo nhóm đang chọn',
  d.nhom === 'default' ? d.nhac === '' : d.nhac.length > 0, true);

const dlg = await p.$('dialog[open]');
if (dlg) { await chonNhom('tc'); await dlg.screenshot({ path: SP + '/phutrach.png' }); }

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
