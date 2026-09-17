/* Kiểm thử Bảng công theo cơ sở: chỉ giữ giá trị công, tách theo từng cơ sở */
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

/* đổi giờ thành phút và ngược lại */
t('đọc "4.9" ra phút', await p.evaluate(() => window.APP.docGio('4.9')), 294);
t('đọc "4:56" ra phút', await p.evaluate(() => window.APP.docGio('4:56')), 296);
t('đọc "7h30" ra phút', await p.evaluate(() => window.APP.docGio('7h30')), 450);
t('đọc "4,9" (dấu phẩy) ra phút', await p.evaluate(() => window.APP.docGio('4,9')), 294);
t('ô trống ra 0', await p.evaluate(() => window.APP.docGio('')), 0);
t('chữ vớ vẩn ra 0', await p.evaluate(() => window.APP.docGio('abc')), 0);
t('296 phút hiện thành 4h 56m', await p.evaluate(() => window.APP.gioPhut(296)), '4h 56m');
t('480 phút hiện thành 8h', await p.evaluate(() => window.APP.gioPhut(480)), '8h');
t('30 phút hiện thành 30m', await p.evaluate(() => window.APP.gioPhut(30)), '30m');
t('0 phút hiện thành 0h', await p.evaluate(() => window.APP.gioPhut(0)), '0h');

await p.evaluate(() => {
  const A = window.APP;
  ['staff', 'attendance'].forEach(c => A.col(c).slice().forEach(x => A.remove(c, x.id)));
  const mk = (id, name, code, office) => A.save('staff',
    { id, name, code, office, status: 'active', role: 'staff' });
  mk('s1', 'Nguyễn Mai Phương', 'MNNV0001', 'Vinpearl Phú Quốc');
  mk('s2', 'Phạm Hòa Bình', 'MNNV0002', 'Vinpearl Phú Quốc');
  mk('s3', 'Trần Minh Chiến', 'MNNV0003', 'Cơ sở Tân Bình');
  mk('s4', 'Đào Thị Hồng Ngân', 'MNNV0004', '');
  A.S.me = 'Nguyễn Mai Phương';
  A.save('staff', { id: 'sme', name: 'Giám Đốc', code: 'GD', office: 'Vinpearl Phú Quốc',
    status: 'active', role: 'owner' });
  A.S.me = 'Giám Đốc';
});
await p.waitForTimeout(400);

t('gom đủ cơ sở từ hồ sơ nhân sự', await p.evaluate(() => window.APP.officeAll()),
  ['Cơ sở Tân Bình', 'Vinpearl Phú Quốc']);

await p.click('[data-go="checkin"]'); await p.waitForTimeout(600);
t('sidebar có mục Bảng công cơ sở', await p.evaluate(() =>
  !!document.querySelector('[data-cv="bcong"]')), true);
t('sidebar liệt kê từng cơ sở', await p.evaluate(() =>
  [...document.querySelectorAll('[data-cs]')].map(x => x.getAttribute('data-cs'))),
  ['Cơ sở Tân Bình', 'Vinpearl Phú Quốc', 'Chưa gán cơ sở']);

await p.evaluate(() => document.querySelector('[data-cs="Vinpearl Phú Quốc"]').click());
await p.waitForTimeout(600);
await p.evaluate(() => { window.APP.S.__cyc = 1; });
/* chốt tháng để số ngày không đổi theo hôm nay */
await p.evaluate(() => {
  const el = document.querySelector('#bcCyc');
  el.value = '2026-09';
  el.dispatchEvent(new Event('change', { bubbles: true }));
});
await p.waitForTimeout(600);

const bang = () => p.evaluate(() => {
  const tb = document.querySelector('table.bcong');
  if (!tb) return null;
  return {
    ngay: tb.querySelectorAll('thead th.d').length,
    cuoiTuan: [...tb.querySelectorAll('thead th.d')].filter(x => x.className.includes('ct')).length,
    nguoi: [...tb.querySelectorAll('tbody tr td.nv b')].map(x => x.textContent),
    tong: Object.fromEntries([...tb.querySelectorAll('tbody tr')].map(tr =>
      [tr.querySelector('td.nv b').textContent, tr.querySelector('td.tg').textContent])),
    chan: tb.querySelector('tfoot td.nv').textContent,
    chanTong: tb.querySelector('tfoot td.tg').textContent
  };
});
let g = await bang();
t('chỉ hiện người của cơ sở đang chọn', g.nguoi.slice().sort(),
  ['Giám Đốc', 'Nguyễn Mai Phương', 'Phạm Hòa Bình']);
t('  đủ 30 cột ngày của tháng 9', g.ngay, 30);
t('  đánh dấu 8 ngày cuối tuần (T7 và CN của tháng 9/2026)', g.cuoiTuan, 8);
t('  chưa nhập thì tổng là 0h', Object.values(g.tong), ['0h', '0h', '0h']);
t('  chân bảng đếm đúng số người', g.chan, '3 người');

/* nhập công một ô */
await p.evaluate(() => document.querySelector('[data-cong="s2:15"]').click());
await p.waitForTimeout(500);
t('mở được hộp nhập công của đúng ô', await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  return !!d && /15\/09 · Phạm Hòa Bình/.test(d.querySelector('h3').textContent);
}), true);
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  const inp = d.querySelectorAll('input');
  inp[0].value = '4:56'; inp[1].value = 'C1-C2';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(700);
t('lưu công vào đúng ngày, tính bằng phút', await p.evaluate(() =>
  window.APP.find('attendance', 's2_202609').days['15']), { m: 296, ca: 'C1-C2' });
t('ô hiện số giờ rút gọn', await p.evaluate(() =>
  document.querySelector('[data-cong="s2:15"] b').textContent), '4.9');
t('  kèm mã ca', await p.evaluate(() =>
  document.querySelector('[data-cong="s2:15"] span').textContent), 'C1-C2');
g = await bang();
t('tổng dòng đúng định dạng giờ phút', g.tong['Phạm Hòa Bình'], '4h 56m');
t('  người chưa có công vẫn là 0h', g.tong['Giám Đốc'], '0h');
t('tổng cả bảng cộng đúng', g.chanTong, '4h 56m');

/* bảng công KHÔNG đụng tới giờ vào/ra của dữ liệu chấm công */
await p.evaluate(() => {
  const A = window.APP;
  A.save('attendance', { id: 's1_202609', staffId: 's1', cycle: '2026-09',
    days: { '16': { in: '08:10', out: '18:00', late: 0, device: 'Máy Tân Bình', method: 'Khuôn mặt' } } });
});
await p.waitForTimeout(400);
await p.evaluate(() => document.querySelector('[data-cong="s1:16"]').click());
await p.waitForTimeout(500);
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  d.querySelectorAll('input')[0].value = '8';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(700);
t('nhập công xong vẫn giữ nguyên giờ vào/ra cũ', await p.evaluate(() =>
  window.APP.find('attendance', 's1_202609').days['16']),
  { in: '08:10', out: '18:00', late: 0, device: 'Máy Tân Bình', method: 'Khuôn mặt',
    m: 480, ca: 'C1-C2' });
t('  mã ca máy tính ra cũng được chốt theo', await p.evaluate(() =>
  window.APP.find('attendance', 's1_202609').days['16'].ca), 'C1-C2');

/* nhập hàng loạt */
await p.evaluate(() => document.querySelector('[data-cact="fill"]').click());
await p.waitForTimeout(500);
t('mở được hộp nhập hàng loạt', await p.evaluate(() =>
  document.querySelector('dialog[open]').querySelector('h3').textContent), 'Nhập công hàng loạt');
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  d.querySelector('select').value = 's3x';       /* không có → giữ "cả cơ sở" */
  d.querySelector('select').value = '*';
  const inp = [...d.querySelectorAll('input')];
  inp[0].value = '1'; inp[1].value = '7'; inp[2].value = '8'; inp[3].value = 'C1';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(800);
t('điền hàng loạt bỏ qua thứ bảy và chủ nhật', await p.evaluate(() =>
  Object.keys(window.APP.find('attendance', 's1_202609').days).sort()),
  ['01', '02', '03', '04', '07', '16']);
t('  mỗi ngày đúng 8 tiếng', await p.evaluate(() =>
  window.APP.find('attendance', 's1_202609').days['02']), { m: 480, ca: 'C1' });
t('  điền cho cả cơ sở, không lan sang cơ sở khác', await p.evaluate(() =>
  !window.APP.find('attendance', 's3_202609')), true);
t('  không ghi đè mã ca đã có của ngày khác', await p.evaluate(() =>
  window.APP.find('attendance', 's2_202609').days['15'].ca), 'C1-C2');

/* ---- đồng bộ: giờ vào/ra bên Dữ liệu chấm công tự thành giờ công ---- */
await p.evaluate(() => {
  const A = window.APP;
  const a = Object.assign({}, A.find('attendance', 's1_202609'));
  a.days = Object.assign({}, a.days, {
    '17': { in: '08:10', out: '18:00', late: 0, device: 'Máy Tân Bình', method: 'Khuôn mặt' },
    '18': { in: '08:30', out: '12:00', late: 0, device: 'Máy Tân Bình', method: 'Thẻ từ' },
    '21': { in: '08:10', out: '08:05', late: 0 },
    '22': { leave: 'annual' }
  });
  A.save('attendance', a);
});
await p.waitForTimeout(500);
await p.evaluate(() => { document.querySelector('#bcCyc').dispatchEvent(new Event('change', { bubbles: true })); });
await p.waitForTimeout(500);

const oCell = sel => p.evaluate(s2 => {
  const b = document.querySelector(s2);
  if (!b) return null;
  return { so: b.querySelector('b') ? b.querySelector('b').textContent : '',
    tuMay: b.querySelector('b') ? b.querySelector('b').className === 'sr' : null,
    ca: b.querySelector('span:not(.cham)') ? b.querySelector('span:not(.cham)').textContent : '',
    ghi: b.getAttribute('title') };
}, sel);

let c = await oCell('[data-cong="s1:17"]');
t('vào 08:10 ra 18:00 tự thành 8 giờ công', c.so, '8');
t('  trừ nghỉ trưa, không tính thành 9.8', c.so !== '9.8', true);
t('  ghi đủ hai ca', c.ca, 'C1-C2');
t('  đánh dấu là số tự tính, không phải nhập tay', c.tuMay, true);
t('  di chuột vào nói rõ tính từ đâu', /tự tính từ giờ vào 08:10 – ra 18:00/.test(c.ghi), true);

c = await oCell('[data-cong="s1:18"]');
t('chỉ làm ca sáng thì được 3.5 giờ', c.so, '3.5');
t('  chỉ ghi ca sáng', c.ca, 'C1');

t('giờ ra trước giờ vào thì không tính bừa', (await oCell('[data-cong="s1:21"]')).so, '');
t('ngày nghỉ phép không thành giờ công', (await oCell('[data-cong="s1:22"]')).so, '');

c = await oCell('[data-cong="s1:16"]');
t('số nhập tay vẫn thắng số của máy', c.so, '8');
t('  và vẫn hiện là nhập tay', c.tuMay, false);

/* chốt số của máy thành số nhập tay */
await p.evaluate(() => document.querySelector('[data-cong="s1:17"]').click());
await p.waitForTimeout(500);
t('hộp sửa điền sẵn số máy tính ra', await p.evaluate(() =>
  document.querySelector('dialog[open] input').value), '8');
t('  nói rõ lưu lại là chốt', await p.evaluate(() =>
  /chốt con số này/.test(document.querySelector('dialog[open]').innerText)), true);
await p.evaluate(() => document.querySelector('dialog[open] [data-x="save"]').click());
await p.waitForTimeout(700);
c = await oCell('[data-cong="s1:17"]');
t('lưu xong thì thành số nhập tay', c.tuMay, false);
t('  máy chấm công đổi giờ cũng không lung lay', await p.evaluate(() => {
  const A = window.APP;
  const a = Object.assign({}, A.find('attendance', 's1_202609'));
  a.days = Object.assign({}, a.days);
  a.days['17'] = Object.assign({}, a.days['17'], { out: '15:00' });
  A.save('attendance', a);
  return A.find('attendance', 's1_202609').days['17'].m;
}), 480);

/* đổi cơ sở */
await p.evaluate(() => document.querySelector('[data-cs="Cơ sở Tân Bình"]').click());
await p.waitForTimeout(600);
t('đổi cơ sở thì đổi danh sách người', (await bang()).nguoi, ['Trần Minh Chiến']);
t('  bảng cơ sở khác không dính công vừa nhập', (await bang()).chanTong, '0h');
await p.evaluate(() => document.querySelector('[data-cs="Chưa gán cơ sở"]').click());
await p.waitForTimeout(600);
t('người chưa gán cơ sở vẫn có chỗ đứng', (await bang()).nguoi, ['Đào Thị Hồng Ngân']);

await p.evaluate(() => document.querySelector('[data-cs="Vinpearl Phú Quốc"]').click());
await p.waitForTimeout(600);
await p.screenshot({ path: SP + '/bcong.png' });

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 6).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
