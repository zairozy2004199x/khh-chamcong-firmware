/* Kiểm thử đính kèm ảnh / PDF trong hộp Chi tiết công việc */
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
const ctx = await b.newContext({ viewport: { width: 1400, height: 950 } });
const p = await ctx.newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font/i.test(m.text())) errs.push('console: ' + m.text()); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1300);

/* Kho tệp giả: trả về data: URL để ảnh hiện được thật trong thẻ img */
await p.evaluate(() => {
  window.__up = [];
  window.APP.upload = function (blob, name) {
    window.__up.push(name);
    return new Promise(res => {
      const fr = new FileReader();
      fr.onload = () => res({ url: fr.result, id: 'f' + window.__up.length, name,
        type: blob.type, size: blob.size });
      fr.readAsDataURL(blob);
    });
  };
  const A = window.APP;
  A.col('tasks').slice().forEach(x => A.remove('tasks', x.id));
  A.col('projects').slice().forEach(x => A.remove('projects', x.id));
  A.save('projects', { id: 'p1', name: 'Dự án thử', members: ['Quang Thắng'], lists: [] });
  A.save('tasks', { id: 'tk1', pid: 'p1', title: 'Lên hồ sơ thiết kế', status: 'todo',
    assignee: 'Quang Thắng', list: 'default', order: 1, by: 'Quang Thắng', at: new Date().toISOString() });
  A.S.me = 'Quang Thắng';
});
await p.waitForTimeout(400);

await p.click('[data-go="wework"]'); await p.waitForTimeout(600);
await p.evaluate(() => { const el = document.querySelector('[data-task]'); if (el) el.click(); });
await p.waitForTimeout(600);
t('mở được hộp chi tiết công việc', await p.evaluate(() => !!document.querySelector('dialog[open]')), true);
t('có khối Tệp đính kèm', await p.evaluate(() =>
  document.querySelector('dialog').innerText.includes('Tệp đính kèm')), true);
t('lúc đầu báo chưa có tệp', await p.evaluate(() =>
  document.querySelector('dialog').innerText.includes('Chưa có tệp nào')), true);

/* ---- đính kèm một ảnh và một PDF ---- */
await p.setInputFiles('dialog #tFile', [SP + '/tepthu/thiet-ke.png', SP + '/tepthu/hop-dong.pdf']);
await p.waitForTimeout(1200);

const fs = await p.evaluate(() => (window.APP.find('tasks', 'tk1').files || [])
  .map(f => ({ name: f.name, type: f.type, by: f.by, co: !!f.url })));
t('lưu đúng 2 tệp vào công việc', fs.length, 2);
t('  tên tệp đúng', fs.map(f => f.name), ['thiet-ke.png', 'hop-dong.pdf']);
t('  kiểu tệp đúng', fs.map(f => f.type), ['image/png', 'application/pdf']);
t('  ghi lại người đính kèm', fs[0].by, 'Quang Thắng');
t('  tệp nào cũng có đường dẫn', fs.every(f => f.co), true);

t('ảnh hiện thành hình thu nhỏ', await p.evaluate(() =>
  document.querySelectorAll('dialog .att .th img').length), 1);
t('PDF hiện nhãn PDF', await p.evaluate(() =>
  document.querySelectorAll('dialog .att .th.pdf').length), 1);
t('mở tệp ở tab mới', await p.evaluate(() =>
  [...document.querySelectorAll('dialog .att a')].every(a => a.target === '_blank' && /noopener/.test(a.rel))), true);
t('đếm số tệp trên tiêu đề', await p.evaluate(() =>
  document.querySelector('dialog .sec .ds').innerText.includes('2 tệp') ||
  [...document.querySelectorAll('dialog .ds')].some(x => x.innerText.includes('2 tệp'))), true);
t('ghi vào lịch sử hoạt động', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').log || []).slice(-1)[0].tx.includes('đính kèm')), true);

/* ---- tệp không phải ảnh/PDF thì từ chối ---- */
await p.setInputFiles('dialog #tFile', [SP + '/tepthu/khong-nhan.txt']);
await p.waitForTimeout(800);
t('tệp .txt bị từ chối', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').files || []).length), 2);
t('  có báo cho người dùng biết', await p.evaluate(() => {
  const el = document.querySelector('#toast');
  return !!el && !el.hidden && el.textContent.includes('Chỉ nhận ảnh hoặc PDF');
}), true);
t('  không gọi tải lên cho tệp sai kiểu', await p.evaluate(() => window.__up.length), 2);

/* ---- gỡ tệp ---- */
p.once('dialog', d => d.dismiss());
await p.evaluate(() => document.querySelector('dialog [data-fdel="0"]').click());
await p.waitForTimeout(500);
t('huỷ xác nhận thì không gỡ', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').files || []).length), 2);

p.once('dialog', d => d.accept());
await p.evaluate(() => document.querySelector('dialog [data-fdel="0"]').click());
await p.waitForTimeout(700);
const con = await p.evaluate(() => (window.APP.find('tasks', 'tk1').files || []).map(f => f.name));
t('đồng ý thì gỡ đúng tệp đó', con, ['hop-dong.pdf']);
t('ghi lại việc gỡ tệp', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').log || []).slice(-1)[0].tx.includes('gỡ tệp')), true);

/* ---- tệp còn nguyên sau khi lưu và mở lại ---- */
await p.evaluate(() => document.querySelector('dialog [data-x="save"]').click());
await p.waitForTimeout(600);
await p.evaluate(() => { const el = document.querySelector('[data-task]'); if (el) el.click(); });
await p.waitForTimeout(600);
t('lưu công việc xong tệp vẫn còn', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').files || []).length), 1);
t('mở lại vẫn thấy tệp trên giao diện', await p.evaluate(() =>
  document.querySelectorAll('dialog .att').length), 1);

/* ---- máy chủ từ chối: câu báo phải nói LÝ DO THẬT, không nói chung chung ---- */
await p.evaluate(() => {
  window.APP.upload = function () {
    window.APP.uploadErr = 'máy chủ trả mã 413: Tệp lớn hơn mức máy chủ cho phép.';
    return Promise.resolve(null);
  };
});
await p.setInputFiles('dialog #tFile', [SP + '/tepthu/thiet-ke.png']);
await p.waitForTimeout(900);
const bao = await p.evaluate(() => {
  const el = document.querySelector('#toast');
  return el && !el.hidden ? el.textContent : '';
});
t('tải hỏng thì nhắc lại đúng lý do máy chủ nói', /413/.test(bao) && /lớn hơn mức máy chủ/.test(bao), true);
t('  không nuốt lý do thành câu chung chung', /kho tệp|chưa bật/.test(bao), false);
t('  không ghi tệp hỏng vào công việc', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').files || []).length), 1);
t('  chữ nhắc dưới nút trở lại bình thường', await p.evaluate(() =>
  document.querySelector('#tFileHint').textContent.includes('Ảnh hoặc PDF')), true);

/* ---- giới hạn dung lượng lấy theo máy chủ, không tự đặt 20MB ---- */
await p.evaluate(() => { window.KH_API = { maxUpload: 100, canUpload: 1 }; }); /* tệp thử nặng 153 B */
await p.evaluate(() => { const el = document.querySelector('[data-task]'); if (el) el.click(); });
await p.waitForTimeout(500);
t('chữ nhắc ghi đúng mức của máy chủ', await p.evaluate(() =>
  document.querySelector('#tFileHint').textContent.includes('100 B')), true);
await p.setInputFiles('dialog #tFile', [SP + '/tepthu/thiet-ke.png']);
await p.waitForTimeout(600);
t('tệp quá mức máy chủ bị chặn ngay', await p.evaluate(() =>
  (window.APP.find('tasks', 'tk1').files || []).length), 1);
t('  báo rõ mức cho phép', await p.evaluate(() => {
  const el = document.querySelector('#toast');
  return !!el && el.textContent.includes('100 B') && el.textContent.includes('quá mức máy chủ');
}), true);

/* ---- tài khoản không có quyền tải tệp thì chặn ngay ở nút ---- */
await p.evaluate(() => { window.KH_API = { maxUpload: 2097152, canUpload: 0 }; });
await p.evaluate(() => { const el = document.querySelector('[data-task]'); if (el) el.click(); });
await p.waitForTimeout(500);
t('không có quyền thì nút Thêm tệp bị khoá', await p.evaluate(() =>
  document.querySelector('dialog [data-x="addfile"]').disabled), true);
t('  nói rõ vì sao khoá', await p.evaluate(() =>
  document.querySelector('#tFileHint').textContent.includes('chưa có quyền')), true);

await p.evaluate(() => { delete window.KH_API; });
await p.evaluate(() => { const el = document.querySelector('[data-task]'); if (el) el.click(); });
await p.waitForTimeout(500);
t('bản không có máy chủ thì vẫn mở nút bình thường', await p.evaluate(() =>
  document.querySelector('dialog [data-x="addfile"]').disabled), false);

await p.screenshot({ path: SP + '/dinhkem.png' });
const dlg = await p.$('dialog[open]');
if (dlg) await dlg.screenshot({ path: SP + '/dinhkem.png' });

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
