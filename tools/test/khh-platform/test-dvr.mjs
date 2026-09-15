/* Kiểm thử plugin Dò Vé Rẻ: bố cục co giãn, đặt đơn thật qua REST, phân quyền khách / quản trị */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';
const BASE = 'http://127.0.0.1:8098';

let fail = 0;
function t(name, got, want) {
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name.padEnd(56)} got=${JSON.stringify(got)}${ok ? '' : ' want=' + JSON.stringify(want)}`);
}
const api = (path, opts) => fetch(BASE + '/wp-json/dvr/v1' + path, opts);

const b = await chromium.launch({ executablePath: EXE });
const p = await (await b.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
const errs = [];
p.on('console', m => { if (m.type() === 'error' && !/ERR_|font|favicon/i.test(m.text())) errs.push('console: ' + m.text()); });
p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

await p.goto(BASE + '/');
await p.waitForTimeout(900);

/* ---- trang chạy được ---- */
t('dải nhận diện có trên trang', await p.evaluate(() => !!document.querySelector('.brand')), true);
t('tên thương hiệu đúng', await p.evaluate(() =>
  document.querySelector('.brand .bname').innerText.trim()), 'K&H COM.,LTD');
t('màu thương hiệu là vàng K&H', await p.evaluate(() =>
  getComputedStyle(document.documentElement).getPropertyValue('--accent').trim()), '#B8860B');
t('bảng giá hiện chuyến', await p.evaluate(() => document.querySelectorAll('.res .card').length > 0), true);
/* CSS của nhãn có text-transform:uppercase nên innerText trả về chữ hoa */
t('có nhãn báo giá mô phỏng', await p.evaluate(() =>
  document.querySelector('#srcTag').innerText.normalize('NFC').toLowerCase())
  .then(x => x.includes('mô phỏng'.normalize('NFC'))), true);

/* ---- bố cục: quét mọi bề ngang ---- */
const xau = [];
for (let w = 1280; w >= 360; w -= 20) {
  await p.setViewportSize({ width: w, height: 900 });
  await p.waitForTimeout(90);
  const r = await p.evaluate(() => {
    let tran = 0, de = 0;
    document.querySelectorAll('.res .card').forEach(card => {
      const leg = card.querySelector('.c-leg'), gia = card.querySelector('.c-price');
      if (leg) tran = Math.max(tran, leg.scrollWidth - leg.clientWidth);
      tran = Math.max(tran, card.scrollWidth - card.clientWidth);
      if (leg && gia) {
        const L = leg.getBoundingClientRect(), G = gia.getBoundingClientRect();
        if (L.top < G.bottom && G.top < L.bottom) de = Math.max(de, L.right - G.left);
      }
    });
    return { tran: Math.round(tran), de: Math.round(de),
             trang: document.documentElement.scrollWidth - document.documentElement.clientWidth };
  });
  if (r.tran > 0 || r.de > 0 || r.trang > 0) xau.push(w + ':' + JSON.stringify(r));
}
t('không đè, không tràn ở mọi bề ngang 1280→360', xau, []);
await p.setViewportSize({ width: 1280, height: 900 });
await p.waitForTimeout(200);

/* ---- đặt một đơn thật qua giao diện ---- */
await p.click('.res .card [data-chon]');
await p.waitForTimeout(500);
t('sang màn đặt vé', await p.evaluate(() => !document.querySelector('#tab-dat').hidden), true);
t('có sẵn chuyến đã chọn', await p.evaluate(() =>
  document.querySelector('#datHang').innerText.length > 5), true);

await p.fill('#k0n', 'NGUYEN VAN A');
await p.fill('#k0d', '1990-05-20');
await p.fill('#k0i', '012345678901');
await p.fill('#dName', 'Nguyễn Văn A');
await p.fill('#dPhone', '0912345678');
await p.fill('#dEmail', 'a@khh.vn');
await p.fill('#dTax', '0312345678');
await p.click('#dSubmit');
await p.waitForTimeout(900);

const maDon = await p.evaluate(() => document.querySelector('#dCode').innerText.trim());
t('tạo được đơn, có mã đơn', /^DVR\d{8}$/.test(maDon), true);
t('nội dung chuyển khoản là mã đơn', await p.evaluate(() =>
  document.querySelector('#dNote').innerText.trim()), maDon);

const tren = await (await api('/orders/' + maDon)).json();
t('đơn lưu thật trên máy chủ', tren.order.code, maDon);
t('phí 3% tính đúng', tren.order.money.fee, Math.max(Math.round(tren.order.money.fare * 0.03), 50000));
t('tổng = giá vé + phí', tren.order.money.total, tren.order.money.fare + tren.order.money.fee);
t('số điện thoại lưu đúng', tren.order.contact.phone, '0912345678');

/* ---- máy chủ tự kiểm dữ liệu, không tin trình duyệt ---- */
const xauDon = { pax: [{ full: 'A B' }], contact: { name: 'A', phone: '123', email: 'a@khh.vn' }, money: { fare: 1000 } };
const r1 = await api('/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(xauDon) });
t('điện thoại sai bị máy chủ từ chối', r1.status, 400);
const r2 = await api('/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ ...xauDon, contact: { ...xauDon.contact, phone: '0912345678', email: 'khong-phai-email' } }) });
t('email sai bị máy chủ từ chối', r2.status, 400);
const r3 = await api('/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ pax: [], contact: { phone: '0912345678', email: 'a@khh.vn' } }) });
t('đơn không có khách bị từ chối', r3.status, 400);

/* phí do máy chủ tính lại, không lấy theo trình duyệt gửi lên */
const r4 = await (await api('/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ pax: [{ full: 'C D' }], contact: { name: 'C', phone: '0900000000', email: 'c@khh.vn' },
    money: { fare: 2000000, fee: 1, total: 1 } }) })).json();
t('máy chủ tự tính lại phí, bỏ số trình duyệt gửi', r4.order.money.fee, 60000);
t('máy chủ tự tính lại tổng', r4.order.money.total, 2060000);

/* ---- quản trị đổi trạng thái ---- */
const r5 = await (await api('/orders/' + maDon, { method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ patch: { status: 'da_nhan_tien', money: { paid: tren.order.money.total } }, note: 'Đã nhận tiền' } ) })).json();
t('đánh dấu đã nhận tiền', r5.order.status, 'da_nhan_tien');
t('ghi vào nhật ký đơn', r5.order.log.slice(-1)[0].what, 'Đã nhận tiền');
const r6 = await (await api('/orders/' + maDon, { method: 'POST', headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ patch: { status: 'da_xuat_ve', pnr: 'ab12cd', money: { cost: 900000 } }, note: 'Đã mua vé' } ) })).json();
t('mã đặt chỗ viết hoa', r6.order.pnr, 'AB12CD');
t('trạng thái lạ bị bỏ qua', (await (await api('/orders/' + maDon, { method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ patch: { status: 'xoa_het' }, note: 'thử bậy' }) })).json()).order.status, 'da_xuat_ve');

/* chỉ 2 đơn hợp lệ được tạo: 1 qua giao diện + 1 qua REST; 3 đơn hỏng đã bị từ chối */
t('danh sách đơn: quản trị xem đủ đơn hợp lệ', (await (await api('/orders')).json()).orders.length, 2);

await b.close();

/* ---- đổi sang tư cách khách: kiểm phân quyền ---- */
console.log('\n— chạy lại máy chủ với tư cách khách —');
const { execSync, spawn } = await import('node:child_process');
execSync('kill $(cat ' + SP + '/dvr.pid) 2>/dev/null || true', { shell: '/bin/bash' });
await new Promise(r => setTimeout(r, 600));
const srv = spawn('php', ['-S', '127.0.0.1:8098', 'mockdvr.php'], { cwd: SP, env: { ...process.env, DVR_ADMIN: '0' }, detached: true, stdio: 'ignore' });
srv.unref();
await new Promise(r => setTimeout(r, 1600));

t('khách KHÔNG xem được danh sách đơn', (await api('/orders')).status, 403);
const khach = await (await api('/orders/' + maDon)).json();
t('khách tra được đơn của mình bằng mã', khach.order.code, maDon);
t('khách không thấy giá mua vào', khach.order.money.cost, undefined);
t('khách không thấy nhật ký nội bộ', khach.order.log, undefined);
t('khách vẫn thấy số tiền phải trả', khach.order.money.total > 0, true);
t('khách KHÔNG đổi được trạng thái', (await api('/orders/' + maDon, { method: 'POST',
  headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ patch: { status: 'da_xuat_ve' } }) })).status, 403);

const b2 = await chromium.launch({ executablePath: EXE });
const p2 = await (await b2.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
p2.on('pageerror', e => errs.push('PAGEERROR(khách): ' + e.message));
await p2.goto(BASE + '/');
await p2.waitForTimeout(900);
t('khách không thấy tab Đơn hàng', await p2.evaluate(() => !!document.querySelector('[data-tab="don"]')), false);
t('khách vẫn dò giá được', await p2.evaluate(() => document.querySelectorAll('.res .card').length > 0), true);
await p2.screenshot({ path: SP + '/dvr-khach.png', fullPage: false });
await b2.close();
execSync('kill ' + srv.pid + ' 2>/dev/null || true', { shell: '/bin/bash' });

console.log(errs.length ? '\nLỗi trang:\n' + errs.slice(0, 8).join('\n') : '\nKhông có lỗi JS nào trên trang');
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail || errs.length ? 1 : 0);
