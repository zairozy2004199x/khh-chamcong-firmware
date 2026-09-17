/* Kiểm thử THẬT: WordPress 6.4.3 + MariaDB thật, plugin thật, trình duyệt thật */
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
const SP = '/tmp/claude-0/-home-user-khh-chamcong-firmware/30dd3c43-a516-53dc-bb76-000482c08c1d/scratchpad';
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
const WP  = 'http://127.0.0.1:8899';

let fail = 0;
const t = (n, g, w) => {
  const ok = JSON.stringify(g) === JSON.stringify(w);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(52)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`);
};

/* Về vạch xuất phát: xoá nguồn đã khai và mọi bản ghi chấm công trong nền tảng,
   để lần chạy nào cũng đo đúng cảnh "mới cài xong, màn còn trắng". */
execSync('php /tmp/reset-nguon.php', { stdio: 'pipe' });
/* Bộ này thử đường KHAI BẢNG TAY (site không có plugin Chấm Công K&H). Tạm giấu bảng
   vhcc đi để nền tảng không tự nối; xong thì trả lại nguyên tên. */
const sql = q => execSync(`mysql -u root -e "${q}"`, { stdio: 'pipe' });
sql('RENAME TABLE wp_khmatrix.wp_vhcc_cham_cong TO wp_khmatrix.wp_vhcc_cham_cong__tat');
execSync('php -r \'require "/tmp/wpsite/wp-load.php"; delete_transient("khh_vhcc_co"); delete_transient("khh_cc_doc");\'', { stdio: 'pipe' });
process.on('exit', () => { try { sql('RENAME TABLE wp_khmatrix.wp_vhcc_cham_cong__tat TO wp_khmatrix.wp_vhcc_cham_cong'); } catch (e) {} });
console.log('   đã về vạch xuất phát (giấu bảng vhcc)\n');

const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1500, height: 950 } })).newPage();
/* Bản WordPress đóng gói cho Ubuntu tách underscore/backbone ra gói riêng nên
   trang wp-admin thiếu thư viện — lỗi của bản đóng gói, không phải của plugin.
   Chỉ soi lỗi trên trang nền tảng. */
const loi = [];
const boQua = /underscore|backbone|_ is not defined|g\.template|wp-includes\/js|favicon|ERR_/;
const soi = () => p.url().includes('khh_app=1');
p.on('pageerror', e => { if (soi() && !boQua.test(e.message)) loi.push('PAGEERROR: ' + e.message); });
p.on('console', m => {
  if (m.type() === 'error' && soi() && !boQua.test(m.text())) loi.push('console: ' + m.text());
});

/* --- đăng nhập WordPress thật --- */
await p.goto(WP + '/wp-login.php');
await p.fill('#user_login', 'admin');
await p.fill('#user_pass', 'MatKhau!2026');
await p.click('#wp-submit');
await p.waitForLoadState('networkidle');
t('đăng nhập được WordPress thật', /wp-admin/.test(p.url()), true);

/* --- trước khi khai nguồn: màn chấm công trắng --- */
await p.goto(WP + '/?khh_app=1');
await p.waitForTimeout(2500);
await p.evaluate(() => document.querySelector('[data-go="checkin"]').click());
await p.waitForTimeout(900);
const truoc = await p.evaluate(() => {
  const el = [...document.querySelectorAll('.tile')].find(x => /CÓ MẶT/i.test(x.innerText));
  return el ? el.innerText.replace(/\s+/g, ' ').trim() : null;
});
console.log('   trước khi khai nguồn:', truoc);
t('trước khi khai nguồn thì màn chấm công trắng',
  /0\/10/.test(truoc || ''), true);

/* --- chạy trình khai nguồn, y như anh Thắng sẽ làm --- */
await p.goto(WP + '/wp-admin/admin.php?page=khh-nhap-cham-cong&doi=1');
await p.waitForTimeout(600);
t('mở được trang Nguồn chấm công',
  await p.evaluate(() => !!document.querySelector('h1') &&
    document.querySelector('h1').textContent.includes('Nguồn chấm công')), true);

const chon = await p.evaluate(() => {
  const s = document.querySelector('select[name="nguon"]');
  return s ? { chonSan: s.value, soLuaChon: s.options.length } : null;
});
console.log('   bảng được chọn sẵn:', chon && chon.chonSan);
t('tự chọn sẵn đúng bảng chấm công', chon.chonSan, 'chamcong_cu|attendance');

await p.click('button.button-primary');       // Xem thử bảng này
await p.waitForTimeout(800);
const ghep = await p.evaluate(() =>
  [...document.querySelectorAll('tbody tr')].map(tr => {
    const c = tr.querySelector('code'), s = tr.querySelector('select');
    return c && s ? [c.textContent, s.options[s.selectedIndex].textContent.trim()] : null;
  }).filter(Boolean));
console.log('   ghép cột:', JSON.stringify(ghep));
t('tự ghép đúng cột mã nhân sự',
  (ghep.find(x => x[0] === 'EmployeeCode') || [])[1], 'Mã nhân sự');
t('tự ghép đúng cột ngày', (ghep.find(x => x[0] === 'WorkDate') || [])[1], 'Ngày');
t('tự ghép đúng cột giờ vào', (ghep.find(x => x[0] === 'CheckIn') || [])[1], 'Giờ vào');
t('tự ghép đúng cột giờ ra', (ghep.find(x => x[0] === 'CheckOut') || [])[1], 'Giờ ra');
t('tự ghép đúng cột mã ca', (ghep.find(x => x[0] === 'ShiftCode') || [])[1], 'Mã ca');
t('cột id không ghép bừa', (ghep.find(x => x[0] === 'id') || [])[1], '— bỏ qua —');

await p.click('button.button-primary');       // Đối chiếu thử
await p.waitForTimeout(1200);
const doi = await p.evaluate(() => {
  const o = {};
  document.querySelectorAll('tbody tr').forEach(tr => {
    const td = tr.querySelectorAll('td');
    if (td.length === 2) o[td[0].textContent.trim()] = td[1].textContent.replace(/\s+/g, ' ').trim();
  });
  return o;
});
console.log('   đối chiếu:', JSON.stringify(doi, null, 1));
t('đọc đúng số dòng của bảng cũ', /128 dòng/.test(doi['Đọc được'] || ''), true);
t('dùng được 127 dòng (1 dòng người đã nghỉ)',
  /127 dòng/.test(doi['Dùng được'] || ''), true);
t('  khớp đủ 10 người', /10 người/.test(doi['Dùng được'] || ''), true);
t('  báo đúng 1 dòng không tìm thấy người',
  /^1 dòng/.test(doi['Không tìm thấy người trong hồ sơ'] || ''), true);
t('  không có dòng nào lỗi ngày',
  /^0 dòng/.test(doi['Không đọc được ngày'] || ''), true);

await p.click('button.button-primary');       // Dùng bảng này
await p.waitForTimeout(1200);
t('khai xong thì báo thành công',
  await p.evaluate(() => document.body.innerText.includes('Từ giờ dữ liệu chấm công hiện thẳng')), true);

/* --- KIỂM ĐIỂM MẤU CHỐT: mở nền tảng, dữ liệu phải hiện --- */
await p.goto(WP + '/?khh_app=1');
await p.waitForTimeout(3000);
await p.evaluate(() => document.querySelector('[data-go="checkin"]').click());
await p.waitForTimeout(1200);
await p.evaluate(() => {
  const el = document.querySelector('#ckDate');
  if (el) { el.value = '2026-09-15'; el.dispatchEvent(new Event('change', { bubbles: true })); }
});
await p.waitForTimeout(900);

const sau = await p.evaluate(() => {
  const tiles = {};
  document.querySelectorAll('.tile').forEach(x => {
    const l = x.querySelector('.k, .lab, h4, .t');
    tiles[(l ? l.textContent : x.innerText.split('\n')[0]).trim()] = x.innerText.replace(/\s+/g, ' ').trim();
  });
  const hang = [...document.querySelectorAll('table.t tbody tr')].slice(0, 3).map(tr =>
    [...tr.querySelectorAll('td')].map(td => td.innerText.replace(/\s+/g, ' ').trim()));
  return { tiles: Object.values(tiles), hang };
});
console.log('   thẻ thống kê:', JSON.stringify(sau.tiles));
console.log('   ba dòng đầu:', JSON.stringify(sau.hang, null, 1));

/* 9/10: một người trong dữ liệu thử cố tình nghỉ các ngày chia hết cho 5 */
t('MÀN CHẤM CÔNG CÓ DỮ LIỆU', /9\/10/.test(sau.tiles.join(' ')), true);
t('  và đếm đúng người vắng', /VẮNG 1/.test(sau.tiles.join(' ')), true);
t('  hàng đầu có giờ vào', /^0?8:\d\d$|^08:\d\d$/.test(sau.hang[0][2]), true);
t('  hàng đầu có giờ ra', /^18:\d\d$/.test(sau.hang[0][3]), true);

/* --- bảng công cơ sở --- */
await p.evaluate(() => document.querySelector('[data-cv="bcong"]').click());
await p.waitForTimeout(1200);
await p.evaluate(() => {
  const el = document.querySelector('#bcCyc');
  if (el) { el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); }
});
await p.waitForTimeout(1000);
const bc = await p.evaluate(() => {
  const tb = document.querySelector('table.bcong');
  if (!tb) return null;
  return {
    nguoi: tb.querySelectorAll('tbody tr').length,
    oCoSo: tb.querySelectorAll('tbody td.c b').length,
    tongDau: tb.querySelector('tbody tr td.tg').textContent,
    chan: tb.querySelector('tfoot td.tg').textContent
  };
});
console.log('   bảng công:', JSON.stringify(bc));
t('BẢNG CÔNG CƠ SỞ CÓ SỐ', bc && bc.oCoSo > 0, true);
t('  cột tổng ra giờ phút', /h/.test(bc.tongDau), true);
t('  chân bảng cộng được', /h/.test(bc.chan), true);

/* --- sửa tay một ô công: phải thắng số của phần mềm cũ và giữ nguyên sau khi tải lại --- */
await p.evaluate(() => {
  const b2 = document.querySelector('table.bcong tbody tr td.c button');
  if (b2) b2.click();
});
await p.waitForTimeout(700);
const coHop = await p.evaluate(() => !!document.querySelector('dialog[open]'));
t('mở được hộp sửa ô công', coHop, true);
await p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  d.querySelectorAll('input')[0].value = '3:30';
  d.querySelector('[data-x="save"]').click();
});
await p.waitForTimeout(1500);
await p.reload();
await p.waitForTimeout(3000);
await p.evaluate(() => document.querySelector('[data-go="checkin"]').click());
await p.waitForTimeout(900);
await p.evaluate(() => document.querySelector('[data-cv="bcong"]').click());
await p.waitForTimeout(900);
await p.evaluate(() => {
  const el = document.querySelector('#bcCyc');
  if (el) { el.value = '2026-09'; el.dispatchEvent(new Event('change', { bubbles: true })); }
});
await p.waitForTimeout(1200);
const oDau = await p.evaluate(() => {
  const b2 = document.querySelector('table.bcong tbody tr td.c button b');
  return b2 ? { so: b2.textContent, tuMay: b2.className === 'sr' } : null;
});
console.log('   ô vừa sửa sau khi tải lại:', JSON.stringify(oDau));
t('SỬA TAY GIỮ NGUYÊN SAU KHI TẢI LẠI', oDau && oDau.so, '3.5');
t('  và được đánh dấu là số nhập tay', oDau.tuMay, false);

await p.screenshot({ path: SP + '/that-chamcong.png', fullPage: false });
await p.evaluate(() => document.querySelector('[data-cv="log"]').click());
await p.waitForTimeout(800);
await p.screenshot({ path: SP + '/that-log.png', fullPage: false });

await b.close();
console.log(loi.length ? '\nLỗi trang:\n' + loi.slice(0, 8).join('\n') : '\nKhông có lỗi JS');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || loi.length ? 1 : 0);
