/* Kiểm thử màn hình Bộ phận và phạm vi quyền theo bộ phận — chạy trên trình duyệt thật */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';

let fail = 0;
function t(name, got, want) {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(56)} got=${JSON.stringify(got)}${ok ? '' : ' want=' + JSON.stringify(want)}`);
}

const b = await chromium.launch({ executablePath: EXE });
const ctx = await b.newContext({ viewport: { width: 1500, height: 950 } });
const p = await ctx.newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font/i.test(m.text())) errs.push('console: ' + m.text()); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1300);

/* ---- dựng dữ liệu thử ---- */
await p.evaluate(() => {
  const A = window.APP;
  A.col('staff').slice().forEach(s => A.remove('staff', s.id));
  A.col('depts').slice().forEach(d => A.remove('depts', d.id));
  A.col('settings').slice().forEach(d => A.remove('settings', d.id));
  A.col('timeoffs').slice().forEach(d => A.remove('timeoffs', d.id));
  const mk = (code, name, dept, role) =>
    A.save('staff', { id: 'st_' + code, code, name, dept, role: role || 'staff', status: 'active', start: '2024-01-01' });
  mk('01', 'Quang Thắng', 'Phòng kỹ thuật', 'owner');
  mk('02', 'Mỹ Linh', 'Phòng kỹ thuật', 'manager');
  mk('03', 'Hữu Phúc', 'phòng kỹ thuật ');            // lệch hoa thường + thừa dấu cách
  mk('04', 'Khánh Duy', 'Phòng kỹ thuật');
  mk('05', 'Thu Hà', 'P. Kỹ thuật');                   // tên khác hẳn, phải gộp tay
  mk('06', 'Bảo Ngọc', 'Phòng kinh doanh', 'manager');
  mk('07', 'Minh Tuấn', 'Phòng kinh doanh');
  mk('08', 'Gia Hân', 'Phòng kế toán');
  mk('09', 'Trọng Nghĩa', '');
  mk('10', 'Anh Thư', '');
  window.APP.S.me = 'Quang Thắng';
});
await p.waitForTimeout(300);

const rowsOf = () => p.evaluate(() => Array.from(document.querySelectorAll('#content tbody tr')).map(tr => {
  const td = tr.querySelectorAll('td');
  const b = td[0].querySelector('[data-dept]');
  return { ten: b ? b.innerText.trim() : td[0].innerText.trim(), n: td[1].innerText.trim(), truong: td[2].innerText.trim().split('\n').pop().trim() };
}));

await p.click('[data-go="hrm"]');
await p.waitForTimeout(400);
await p.click('.side [data-v="depts"]');
await p.waitForTimeout(400);

t('gộp tự động tên lệch hoa thường / thừa dấu cách', (await rowsOf()).find(r => r.ten === 'Phòng kỹ thuật').n, '4');
t('bộ phận tên khác vẫn tách riêng', (await rowsOf()).some(r => r.ten === 'P. Kỹ thuật'), true);
t('nhóm chưa khai bộ phận nằm cuối', (await rowsOf()).slice(-1)[0].ten, 'Chưa phân bộ phận');
t('đếm đúng người chưa khai bộ phận', (await rowsOf()).slice(-1)[0].n, '2');

/* ---- đổi tên bộ phận: phải cập nhật mọi hồ sơ, kể cả bản gõ lệch ---- */
await p.click('[data-dedit="Phòng kỹ thuật"]');
await p.waitForTimeout(250);
await p.fill('dialog #f_0', 'Phòng Kỹ thuật & Bảo trì');
await p.fill('dialog #f_1', 'Khánh Duy');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(600);

const afterRename = await p.evaluate(() =>
  window.APP.col('staff').filter(s => s.dept === 'Phòng Kỹ thuật & Bảo trì').map(s => s.name).sort());
t('đổi tên cập nhật hết nhân sự trong bộ phận', afterRename, ['Hữu Phúc', 'Khánh Duy', 'Mỹ Linh', 'Quang Thắng']);
t('bản gõ thừa dấu cách cũng được sửa', await p.evaluate(() =>
  window.APP.find('staff', 'st_03').dept), 'Phòng Kỹ thuật & Bảo trì');
t('trưởng bộ phận được lưu', await p.evaluate(() =>
  (window.APP.col('depts').find(d => d.name === 'Phòng Kỹ thuật & Bảo trì') || {}).head), 'Khánh Duy');
t('bảng hiện trưởng bộ phận', (await rowsOf()).find(r => r.ten === 'Phòng Kỹ thuật & Bảo trì').truong, 'Khánh Duy');

/* ---- gộp hai bộ phận đặt tên khác nhau ---- */
await p.click('[data-dmerge="P. Kỹ thuật"]');
await p.waitForTimeout(250);
await p.selectOption('dialog #f_0', 'Phòng Kỹ thuật & Bảo trì');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(600);
t('gộp xong chuyển người sang bộ phận đích', await p.evaluate(() =>
  window.APP.find('staff', 'st_05').dept), 'Phòng Kỹ thuật & Bảo trì');
t('bộ phận bị gộp biến mất khỏi bảng', (await rowsOf()).some(r => r.ten === 'P. Kỹ thuật'), false);
t('số nhân sự cộng dồn đúng', (await rowsOf()).find(r => r.ten === 'Phòng Kỹ thuật & Bảo trì').n, '5');

/* ---- gán bộ phận cho nhóm chưa khai ---- */
await p.click('[data-dmerge="Chưa phân bộ phận"]');
await p.waitForTimeout(250);
await p.selectOption('dialog #f_0', 'Phòng kinh doanh');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(600);
t('người chưa khai được gán đúng bộ phận', await p.evaluate(() =>
  [window.APP.find('staff', 'st_09').dept, window.APP.find('staff', 'st_10').dept]),
  ['Phòng kinh doanh', 'Phòng kinh doanh']);
t('hết nhóm chưa phân bộ phận', (await rowsOf()).some(r => r.ten === 'Chưa phân bộ phận'), false);

/* ---- xoá bộ phận, chuyển người sang nơi khác ---- */
await p.click('[data-ddel="Phòng kế toán"]');
await p.waitForTimeout(250);
await p.selectOption('dialog #f_0', 'Phòng kinh doanh');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(600);
t('xoá bộ phận thì người được chuyển đi, không mất hồ sơ', await p.evaluate(() =>
  window.APP.find('staff', 'st_08').dept), 'Phòng kinh doanh');
t('bộ phận đã xoá biến mất', (await rowsOf()).some(r => r.ten === 'Phòng kế toán'), false);
t('tổng nhân sự không đổi sau mọi thao tác', await p.evaluate(() => window.APP.col('staff').length), 10);

/* ---- thứ tự hiển thị ---- */
const orderBefore = (await rowsOf()).map(r => r.ten);
await p.click('[data-dmove="down:' + orderBefore[0] + '"]');
await p.waitForTimeout(600);
const orderAfter = (await rowsOf()).map(r => r.ten);
t('bấm xuống thì đổi chỗ hai bộ phận đầu', [orderAfter[0], orderAfter[1]], [orderBefore[1], orderBefore[0]]);
t('sidebar cũng theo thứ tự mới', await p.evaluate(() =>
  Array.from(document.querySelectorAll('.side [data-dept]')).map(b => b.getAttribute('data-dept'))[0]), orderAfter[0]);

/* ---- chống tạo trùng khi thêm mới ---- */
const soBoPhanTruoc = (await rowsOf()).length;
await p.click('[data-dact="new"]');
await p.waitForTimeout(250);
await p.fill('dialog #f_0', 'phòng kinh doanh');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(400);
t('thêm trùng tên thì không sinh bộ phận mới', await p.evaluate(() =>
  window.APP.col('depts').filter(d => d.name.toLowerCase() === 'phòng kinh doanh').length), 1);
t('hộp thoại vẫn mở để sửa lại tên', await p.evaluate(() => !!document.querySelector('dialog[open]')), true);
await p.click('dialog [data-x="cancel"]').catch(() => {});
await p.waitForTimeout(400);
t('số bộ phận không đổi', (await rowsOf()).length, soBoPhanTruoc);

/* ---- ô Bộ phận trong hồ sơ nhân sự gợi ý danh sách sẵn có ---- */
await p.click('.side [data-v="list"]');
await p.waitForTimeout(300);
await p.click('.topbar [data-act="new"]');
await p.waitForTimeout(300);
const dl = await p.evaluate(() => {
  const inp = document.querySelector('dialog #f_3');
  const list = inp && document.getElementById(inp.getAttribute('list'));
  return list ? Array.from(list.options).map(o => o.value) : null;
});
t('ô Bộ phận có danh sách gợi ý', dl && dl.includes('Phòng Kỹ thuật & Bảo trì'), true);
await p.fill('dialog #f_0', 'Người Thử');
await p.fill('dialog #f_3', 'phòng KINH doanh');
await p.click('dialog [data-x="save"]');
await p.waitForTimeout(500);
t('gõ lệch hoa thường vẫn vào đúng bộ phận cũ', await p.evaluate(() =>
  (window.APP.col('staff').find(s => s.name === 'Người Thử') || {}).dept), 'Phòng kinh doanh');

/* ---- phạm vi quyền của Quản lý ---- */
const scope = await p.evaluate(() => {
  const A = window.APP;
  A.S.me = 'Mỹ Linh';                                   // Quản lý, thuộc Phòng Kỹ thuật & Bảo trì
  const r = {
    batMacDinh: A.scopeOn(),
    vaiTro: A.role(),
    phamVi: A.myScope(),
    trongBoPhan: A.inScope('Quang Thắng'),
    ngoaiBoPhan: A.inScope('Bảo Ngọc'),
    duyetTrongBoPhan: A.canFor('timeoff.approve', 'Hữu Phúc'),
    duyetNgoaiBoPhan: A.canFor('timeoff.approve', 'Minh Tuấn'),
    suaChamCongNgoai: A.canFor('checkin.edit', 'Minh Tuấn'),
    luongNgoai: A.canFor('payroll.all', 'Minh Tuấn'),
    tuMinh: A.inScope('Mỹ Linh'),
  };
  A.S.me = 'Bảo Ngọc';                                  // Quản lý Phòng kinh doanh
  r.quanLyKhacThayBoPhanMinh = A.inScope('Minh Tuấn');
  r.quanLyKhacKhongThayKyThuat = A.inScope('Quang Thắng');
  A.S.me = 'Quang Thắng';                               // Chủ sở hữu
  r.chuSoHuuThayTatCa = A.myScope() === null && A.inScope('Minh Tuấn');
  return r;
});
t('mặc định bật giới hạn theo bộ phận', scope.batMacDinh, true);
t('Mỹ Linh đúng là Quản lý', scope.vaiTro, 'manager');
t('phạm vi = bộ phận của chính mình', scope.phamVi, ['phòng kỹ thuật & bảo trì']);
t('người cùng bộ phận nằm trong phạm vi', scope.trongBoPhan, true);
t('người khác bộ phận nằm ngoài phạm vi', scope.ngoaiBoPhan, false);
t('duyệt được đơn nghỉ trong bộ phận', scope.duyetTrongBoPhan, true);
t('KHÔNG duyệt được đơn nghỉ ngoài bộ phận', scope.duyetNgoaiBoPhan, false);
t('KHÔNG sửa được chấm công ngoài bộ phận', scope.suaChamCongNgoai, false);
t('KHÔNG xem được lương ngoài bộ phận', scope.luongNgoai, false);
t('luôn thấy chính mình', scope.tuMinh, true);
t('Quản lý khác thấy bộ phận của họ', scope.quanLyKhacThayBoPhanMinh, true);
t('Quản lý khác không thấy bộ phận kia', scope.quanLyKhacKhongThayKyThuat, false);
t('Chủ sở hữu thấy toàn công ty', scope.chuSoHuuThayTatCa, true);

/* ---- trưởng bộ phận được quản lý bộ phận mình phụ trách ---- */
const head = await p.evaluate(() => {
  const A = window.APP;
  const d = A.col('depts').find(x => x.name === 'Phòng kinh doanh')
    || { id: A.uid(), name: 'Phòng kinh doanh' };
  A.save('depts', Object.assign({}, d, { head: 'Mỹ Linh' }));
  A.S.me = 'Mỹ Linh';
  return { phamVi: A.myScope().sort(), duyetChoNguoiBoPhanKia: A.canFor('timeoff.approve', 'Minh Tuấn') };
});
t('trưởng bộ phận cộng thêm bộ phận phụ trách', head.phamVi, ['phòng kinh doanh', 'phòng kỹ thuật & bảo trì']);
t('duyệt được cho bộ phận mình làm trưởng', head.duyetChoNguoiBoPhanKia, true);

/* ---- tắt giới hạn thì quay về kiểu cũ ---- */
const off = await p.evaluate(() => {
  const A = window.APP;
  A.save('settings', { id: 'scope', dept: 0 });
  A.S.me = 'Mỹ Linh';
  return { batTat: A.scopeOn(), phamVi: A.myScope(), duyetMoiNguoi: A.canFor('timeoff.approve', 'Gia Hân') };
});
t('tắt được giới hạn', off.batTat, false);
t('tắt rồi thì Quản lý không bị giới hạn', off.phamVi, null);
t('tắt rồi thì duyệt được cho mọi người', off.duyetMoiNguoi, true);

await p.evaluate(() => { window.APP.save('settings', { id: 'scope', dept: 1 }); window.APP.S.me = 'Quang Thắng'; });
await p.waitForTimeout(300);
await p.click('.side [data-v="depts"]');
await p.waitForTimeout(500);
await p.screenshot({ path: SP + '/dept-screen.png' });
await p.click('.side [data-v="org"]');
await p.waitForTimeout(500);
await p.screenshot({ path: SP + '/dept-org.png' });

await b.close();
console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 10).join('\n') : '\nKhông có lỗi JS nào trên trang');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
