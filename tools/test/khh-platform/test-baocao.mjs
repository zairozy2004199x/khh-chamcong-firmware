/* Kiểm thử trang tổng "Báo cáo Dự Án": thẻ số, lọc bộ phận, kỳ báo cáo, xếp thứ tự,
   tách bạch "hiện tại" với "trong kỳ", và bảng cần xử lý. */
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
const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font/i.test(m.text())) errs.push('console: ' + m.text()); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1300);

/* Dữ liệu dựng tay, mốc thời gian tính theo lúc chạy để phép thử không hỏng theo ngày.
   Việc "xong" chia hai bên mốc 90 ngày để thấy rõ kỳ báo cáo có ăn hay không. */
await p.evaluate(() => {
  const A = window.APP;
  ['projects', 'tasks'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  const D = n => new Date(Date.now() + n * 864e5).toISOString();

  A.save('projects', { id: 'p1', name: 'Lắp đặt Tân Bình', group: 'Phòng kỹ thuật',
    status: 'ontrack', at: D(-120), start: D(-120), end: D(-2), order: 1 });
  A.save('projects', { id: 'p2', name: 'Tuyển dụng quý 3', group: 'Phòng nhân sự',
    status: 'risk', at: D(-40), start: D(-40), end: D(30), order: 2 });
  A.save('projects', { id: 'p3', name: 'Bảo trì Hà Nội', group: 'Phòng kỹ thuật',
    status: 'ontrack', at: D(-300), start: D(-300), order: 3 });

  const v = (id, pid, o) => A.save('tasks', Object.assign({ id, pid, title: id, status: 'todo' }, o));
  /* p1 — Phòng kỹ thuật */
  v('t1', 'p1', { status: 'done', at: D(-300), due: D(-240), doneAt: D(-250), assignee: 'Quang Thắng' }); // đúng hạn (xong trước hạn), NGOÀI cả kỳ 90
  v('t2', 'p1', { status: 'done', at: D(-60), due: D(-50), doneAt: D(-45), assignee: 'Quang Thắng' });    // MUỘN, trong 90 nhưng ngoài 30
  v('t3', 'p1', { status: 'todo', at: D(-30), due: D(-1), assignee: 'Quang Thắng' });                    // quá hạn
  v('t4', 'p1', { status: 'doing', at: D(-10), due: D(3), assignee: 'Mai Phương' });                     // sắp đến hạn
  /* p2 — Phòng nhân sự */
  v('t5', 'p2', { status: 'done', at: D(-30), due: D(-10), doneAt: D(-12), assignee: 'Mai Phương' });    // xong đúng hạn, trong kỳ
  v('t6', 'p2', { status: 'todo', at: D(-20), due: D(-3) });                                             // quá hạn, CHƯA GIAO AI
  v('t7', 'p2', { status: 'doing', at: D(-5) });                                                         // không đặt hạn
  /* p3 — đứng im: việc cuối cùng động tới đã 200 ngày */
  v('t8', 'p3', { status: 'todo', at: D(-200), assignee: 'Mai Phương' });
  /* việc mồ côi: dự án đã xoá — không được cộng vào bất kỳ con số nào */
  v('t9', 'khong-co', { status: 'todo', at: D(-3), due: D(-3), assignee: 'Quang Thắng' });

  A.S.me = 'Quang Thắng';
});
await p.waitForTimeout(400);

/* Đọc thẻ số theo NHÃN chứ không theo vị trí — thêm bớt thẻ sau này không làm đỏ oan. */
const the = () => p.evaluate(() => {
  const o = {};
  document.querySelectorAll('#content .tile').forEach(x => {
    o[x.querySelector('.lb').textContent.trim()] = x.querySelector('.vl').textContent.trim();
  });
  return o;
});
/* Lấy đúng ô tên: textContent của cả ô còn dính chữ viết tắt trong ảnh đại diện ("TB…"). */
const dongDuAn = () => p.evaluate(() =>
  Array.from(document.querySelectorAll('#content table.t tbody tr td:first-child b'))
    .map(x => x.textContent.trim()));

/* 1. Ứng dụng có mặt và mở được */
t('có trong danh sách ứng dụng',
  await p.evaluate(() => !!window.APP.byId.baocao), true);
t('tên ứng dụng', await p.evaluate(() => window.APP.byId.baocao.name), 'Báo cáo Dự Án');
await p.click('[data-go="baocao"]'); await p.waitForTimeout(500);
/* Kỳ và bộ phận nhớ theo máy (localStorage) nên lần chạy trước có thể còn để lại —
   bấm về mốc đã biết thay vì tải lại trang, vì tải lại là dữ liệu ví dụ mọc lại. */
await p.evaluate(() => {
  document.querySelector('.side [data-ky="90"]').click();
});
await p.waitForTimeout(300);
await p.evaluate(() => { document.querySelector('.side [data-bp=""]').click(); });
await p.waitForTimeout(300);

/* 2. Thẻ "hiện tại" — không phụ thuộc kỳ */
let s = await the();
t('đếm đủ dự án', s['Dự án'], '3');
t('việc chưa xong (không tính việc mồ côi)', s['Việc chưa xong'], '5');
t('quá hạn (không tính việc mồ côi)', s['Quá hạn'], '2');
t('sắp đến hạn trong 7 ngày', s['Sắp đến hạn'], '1');

/* 3. Thẻ "trong kỳ" — kỳ 90 ngày lấy t2 (muộn) và t5 (đúng hạn), bỏ t1 xong từ 250 ngày trước */
t('xong trong kỳ 90 ngày', s['Xong trong kỳ'], '2');
t('đúng hạn trong kỳ 90 ngày', s['Đúng hạn'], '50%');

/* 4. Đổi kỳ thì nhóm "trong kỳ" đổi, nhóm "hiện tại" GIỮ NGUYÊN */
await p.evaluate(() => document.querySelector('[data-ky="30"]').click());
await p.waitForTimeout(400);
s = await the();
t('kỳ 30 ngày: xong trong kỳ', s['Xong trong kỳ'], '1');
t('kỳ 30 ngày: đúng hạn', s['Đúng hạn'], '100%');
t('kỳ 30 ngày: quá hạn vẫn thế', s['Quá hạn'], '2');
t('kỳ 30 ngày: dự án vẫn thế', s['Dự án'], '3');

await p.evaluate(() => document.querySelector('[data-ky="all"]').click());
await p.waitForTimeout(400);
s = await the();
t('kỳ tất cả: xong hết 3 việc', s['Xong trong kỳ'], '3');
t('kỳ tất cả: đúng hạn 2/3', s['Đúng hạn'], '66.7%');

/* 5. Lọc theo bộ phận */
await p.evaluate(() => document.querySelector('.side [data-bp="Phòng kỹ thuật"]').click());
await p.waitForTimeout(400);
s = await the();
t('lọc bộ phận: còn 2 dự án', s['Dự án'], '2');
t('lọc bộ phận: quá hạn còn 1', s['Quá hạn'], '1');
t('bảng chỉ còn dự án của bộ phận',
  await dongDuAn(), ['Lắp đặt Tân Bình', 'Bảo trì Hà Nội']);

await p.evaluate(() => document.querySelector('.side [data-bp=""]').click());
await p.waitForTimeout(400);
t('bỏ lọc thì về đủ 3 dự án', (await the())['Dự án'], '3');

/* 6. Xếp thứ tự bảng dự án */
t('mặc định xếp theo quá hạn nhiều trước', (await dongDuAn())[0], 'Lắp đặt Tân Bình');
await p.evaluate(() => document.querySelector('[data-sap="ten"]').click());
await p.waitForTimeout(300);
t('xếp theo tên', await dongDuAn(),
  ['Bảo trì Hà Nội', 'Lắp đặt Tân Bình', 'Tuyển dụng quý 3']);
await p.evaluate(() => document.querySelector('[data-sap="han"]').click());
await p.waitForTimeout(300);
t('xếp theo hạn: chưa đặt hạn xuống cuối', await dongDuAn(),
  ['Lắp đặt Tân Bình', 'Tuyển dụng quý 3', 'Bảo trì Hà Nội']);

/* 7. Tab Theo người */
await p.evaluate(() => document.querySelector('.tabs [data-tab="nguoi"]').click());
await p.waitForTimeout(400);
const nguoi = await p.evaluate(() =>
  Array.from(document.querySelectorAll('#content table.t tbody tr')).map(tr => {
    const o = Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim());
    return [o[0].replace(/\s+/g, ' '), o[1], o[4]];   // tên, được giao, quá hạn
  }));
t('ba nhóm người phụ trách', nguoi.length, 3);
t('người có việc quá hạn lên đầu', nguoi[0][2], '1');
t('gộp việc chưa giao thành một dòng',
  nguoi.filter(r => r[0].includes('Chưa giao')).length, 1);
t('đánh dấu "bạn" cho người đang đăng nhập',
  nguoi.some(r => r[0].includes('Quang Thắng') && r[0].includes('bạn')), true);

/* 8. Tab Cần xử lý */
await p.evaluate(() => document.querySelector('.tabs [data-tab="canhbao"]').click());
await p.waitForTimeout(400);
s = await the();
t('dự án trễ hạn', s['Dự án trễ hạn'], '1');
t('dự án đứng im', s['Dự án đứng im'], '1');
t('việc quá hạn', s['Việc quá hạn'], '2');
t('việc chưa giao ai', s['Việc chưa giao ai'], '2');

/* 9. Bấm vào dự án thì sang đúng ứng dụng Công việc & Dự án */
await p.evaluate(() => document.querySelector('#content [data-proj]').click());
await p.waitForTimeout(500);
t('mở được dự án từ báo cáo', await p.evaluate(() => window.APP.S.app), 'wework');

/* 10. Dự án đã xong thì không bị kêu trễ hạn dù quá ngày kết thúc */
await p.evaluate(() => {
  const A = window.APP;
  A.col('tasks').filter(x => x.pid === 'p1').forEach(x => {
    A.save('tasks', Object.assign({}, x, { status: 'done', doneAt: new Date().toISOString() }));
  });
});
await p.waitForTimeout(300);
await p.click('[data-go="baocao"]'); await p.waitForTimeout(500);
await p.evaluate(() => document.querySelector('.tabs [data-tab="canhbao"]').click());
await p.waitForTimeout(400);
t('dự án xong hết việc không còn bị kêu trễ', (await the())['Dự án trễ hạn'], '0');

t('không có lỗi JavaScript nào', errs, []);
await b.close();
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail ? 1 : 0);
