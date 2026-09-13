// Chạy: node booking/test-booking.mjs
// Dựng máy chủ đơn hàng thật trên cổng tạm, chạy hết một vòng đời đơn, rồi xoá file dữ liệu.

import { spawn } from 'node:child_process';
import { rm, mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { matchCode, qrImage } from './vietqr.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
let pass = 0, fail = 0;
const ok = (name, cond, extra) => {
  if(cond){ pass++; console.log('  ✓ ' + name); }
  else { fail++; console.log('  ✗ ' + name + (extra !== undefined ? '  → ' + JSON.stringify(extra) : '')); }
};
const sleep = ms => new Promise(r => setTimeout(r, ms));
const TOKEN = 'token-thu';
const SECRET = 'khoa-webhook';

const dir = await mkdtemp(join(tmpdir(), 'dvr-'));
const spawnSrv = (port, extra = {}) => spawn(process.execPath, [resolve(HERE, 'server.mjs')], {
  env: { ...process.env, PORT: String(port), ADMIN_TOKEN: TOKEN, WEBHOOK_SECRET: SECRET,
         BANK_ID: '970436', BANK_ACCOUNT: '0071000123456', BANK_NAME: 'CONG TY TNHH K&H',
         MAIL_MODE: 'log', SHOP_NAME: 'Vé K&H', PUBLIC_URL: 'https://ve.knh.vn',
         FEE_PCT: '3', FEE_MIN: '50000', DATA: join(dir, 'orders-' + port + '.json'), ...extra },
  stdio: 'ignore'
});
const srv = spawnSrv(9921);
const srvHetHan = spawnSrv(9922, { HOLD_MINUTES: '0' });
await sleep(800);

const call = async (port, path, opt = {}) => {
  const r = await fetch('http://127.0.0.1:' + port + path, {
    method: opt.body ? 'POST' : (opt.method || 'GET'),
    headers: { 'content-type': 'application/json', ...(opt.headers || {}) },
    body: opt.body ? JSON.stringify(opt.body) : undefined
  });
  return { status: r.status, body: await r.json().catch(() => ({})) };
};
const api = (p, o) => call(9921, p, o);
const admin = (p, o) => call(9921, p, { ...o, headers: { 'x-admin-token': TOKEN } });

// thư gửi bất đồng bộ nên chờ tới khi đơn ghi nhận xong
const doiThu = async (code, kind, hanMs = 3000) => {
  const het = Date.now() + hanMs;
  while(Date.now() < het){
    const r = await admin('/api/admin/orders');
    const o = (r.body.orders || []).find(x => x.code === code);
    const m = (o && o.mail || []).filter(x => x.kind === kind);
    if(m.length) return m;
    await sleep(80);
  }
  return [];
};

const DON = {
  flight: { route: 'SGN-HAN', date: '2026-10-04', airline: 'VJ', number: 'VJ120', dep: '06:15', arr: '08:25', stops: 0, bag: false },
  fare: { total: 1690000, cur: 'VND' },
  pax: [{ full: 'NGUYEN VAN A', dob: '12/05/1990', idNo: '079090001234' }],
  contact: { name: 'Nguyễn Văn A', phone: '0912345678', email: 'vana@congty.com' },
  invoice: { company: 'CÔNG TY TNHH K&H', tax: '0312345678' }
};

try {
  console.log('\nGhép mã đơn từ nội dung chuyển khoản');
  ok('nội dung sạch', matchCode('DVR26090001') === 'DVR26090001');
  ok('ngân hàng chèn thêm chữ', matchCode('CT tu 0071 den DVR26090001 chuyen tien ve') === 'DVR26090001');
  ok('có dấu cách chen giữa', matchCode('DVR 2609 0001') === 'DVR26090001');
  ok('không có mã thì trả null', matchCode('chuyen tien an trua') === null);
  ok('mã QR có đủ tiền và nội dung',
     /970436-0071000123456-compact2\.png\?amount=1740000&addInfo=DVR1/.test(qrImage({ bankId:'970436', account:'0071000123456', amount:1740000, code:'DVR1' })),
     qrImage({ bankId:'970436', account:'0071000123456', amount:1740000, code:'DVR1' }));

  console.log('\nKhách tạo đơn');
  const c = await api('/api/orders', { body: DON });
  const code = c.body.code;
  ok('tạo được đơn', c.status === 201 && /^DVR\d{8}$/.test(code || ''), c.body);
  ok('phí 3% nhưng không dưới 50.000', c.body.money.fee === 50700, c.body.money);
  ok('tổng = vé + phí', c.body.money.total === 1690000 + 50700, c.body.money);
  ok('trả kèm mã QR đúng số tiền', (c.body.qr || '').includes('amount=1740700') && c.body.qr.includes('addInfo=' + code), c.body.qr);
  ok('nội dung chuyển khoản là mã đơn', c.body.bank.transferNote === code, c.body.bank);
  ok('trạng thái chờ chuyển khoản', c.body.status === 'cho_thanh_toan', c.body.status);

  console.log('\nĐơn sai thì chặn');
  const e1 = await api('/api/orders', { body: { ...DON, contact: { ...DON.contact, phone: '123' } } });
  ok('số điện thoại sai', e1.status === 400 && /điện thoại/.test(e1.body.error), e1.body);
  const e2 = await api('/api/orders', { body: { ...DON, pax: [{ full: 'A' }] } });
  ok('tên khách thiếu họ', e2.status === 400 && /họ và tên/.test(e2.body.error), e2.body);
  const e3 = await api('/api/orders', { body: { ...DON, fare: { total: 120, cur: 'EUR' } } });
  ok('chặn đơn không phải VND', e3.status === 400 && /VND/.test(e3.body.error), e3.body);

  console.log('\nKhách tra đơn');
  const g = await api('/api/orders/' + code);
  ok('xem được đơn của mình', g.status === 200 && g.body.code === code, g.body);
  ok('không lộ giá mình mua vào', g.body.money.cost === undefined, g.body.money);

  console.log('\nNgân hàng báo tiền về');
  const w0 = await api('/api/webhook/bank', { body: { content: code, transferAmount: 1740700 }, headers: { authorization: 'Apikey sai' } });
  ok('sai khoá thì chặn', w0.status === 401, w0.body);
  const w1 = await api('/api/webhook/bank', {
    headers: { authorization: 'Apikey ' + SECRET },
    body: { gateway: 'VCB', transferType: 'in', transferAmount: 1740700,
            content: 'CT tu NGUYEN VAN A den ' + code + ' thanh toan ve may bay', referenceCode: 'FT123' }
  });
  ok('khớp đúng đơn', w1.body.ok === true && w1.body.code === code, w1.body);
  ok('không thiếu tiền', w1.body.thieu === 0, w1.body);
  const g2 = await api('/api/orders/' + code);
  ok('đơn chuyển sang đã nhận tiền', g2.body.status === 'da_nhan_tien', g2.body.status);
  const w2 = await api('/api/webhook/bank', {
    headers: { authorization: 'Apikey ' + SECRET },
    body: { transferType: 'in', transferAmount: 1740700, content: code }
  });
  ok('tiền về lần hai không ghi đè', /đang ở trạng thái/.test(w2.body.skipped || ''), w2.body);

  console.log('\nChuyển thiếu tiền');
  const c2 = await api('/api/orders', { body: DON });
  const w3 = await api('/api/webhook/bank', {
    headers: { authorization: 'Apikey ' + SECRET },
    body: { transferType: 'in', transferAmount: 1000000, content: 'ck ' + c2.body.code }
  });
  ok('tính ra số còn thiếu', w3.body.thieu === 740700, w3.body);

  console.log('\nQuản trị');
  const noAuth = await call(9921, '/api/admin/orders');
  ok('không token thì chặn', noAuth.status === 401, noAuth.body);
  const bk = await admin('/api/admin/orders/' + code + '/booking', { body: { pnr: 'abc123', cost: 1600000 } });
  ok('ghi mã đặt chỗ, viết hoa', bk.body.order.pnr === 'ABC123', bk.body.order);
  ok('đơn sang đã xuất vé', bk.body.order.status === 'da_xuat_ve', bk.body.order.status);
  const bk2 = await admin('/api/admin/orders/' + code + '/booking', { body: { cost: 100 } });
  ok('thiếu mã đặt chỗ thì báo lỗi', bk2.status === 400, bk2.body);
  const list = await admin('/api/admin/orders');
  ok('bảng tổng tính lãi', list.body.tong.lai === 1740700 - 1600000, list.body.tong);
  ok('nhật ký ghi lại từng bước', (list.body.orders.find(o => o.code === code).log || []).length >= 3,
     list.body.orders.find(o => o.code === code).log);
  const rf = await admin('/api/admin/orders/' + c2.body.code + '/refund', { body: { reason: 'hết chỗ giá đó' } });
  ok('hoàn tiền được', rf.body.order.status === 'hoan_tien', rf.body.order.status);

  console.log('\nThư báo khách');
  const t1 = await doiThu(code, 'moi');
  ok('tạo đơn xong là gửi hướng dẫn chuyển khoản', t1.length === 1 && t1[0].ok && t1[0].to === 'vana@congty.com', t1);
  const t2 = await doiThu(code, 'da_nhan_tien');
  ok('tiền về thì báo đang mua vé', t2.length === 1 && t2[0].ok, t2);
  const t3 = await doiThu(code, 'da_xuat_ve');
  ok('xuất vé xong thì gửi mã đặt chỗ', t3.length === 1 && t3[0].ok, t3);
  const t4 = await doiThu(c2.body.code, 'hoan_tien');
  ok('hoàn tiền cũng có thư', t4.length === 1 && t4[0].ok, t4);
  const gl = await admin('/api/admin/orders/' + code + '/mail', { body: {} });
  ok('gửi lại thư được, tự chọn mẫu theo trạng thái', gl.body.ok === true && gl.body.kind === 'da_xuat_ve', gl.body);
  const t5 = await doiThu(code, 'da_xuat_ve');
  ok('lần gửi lại được ghi vào đơn', t5.length === 2, t5.length);
  const nk = (await admin('/api/admin/orders')).body.orders.find(o => o.code === code);
  ok('nhật ký đơn ghi việc gửi thư', (nk.log || []).some(l => /Đã gửi thư/.test(l.what)));

  console.log('\nHết hạn giữ giá');
  const h = await call(9922, '/api/orders', { body: DON });
  await sleep(60);
  const h2 = await call(9922, '/api/orders/' + h.body.code);
  ok('quá giờ thì đơn hết hạn', h2.body.status === 'het_han', h2.body.status);
  ok('hết hạn thì thôi hiện QR', !h2.body.qr, h2.body.qr);
} finally {
  srv.kill(); srvHetHan.kill();
  await rm(dir, { recursive: true, force: true });
}
console.log('\n' + pass + ' đạt, ' + fail + ' hỏng');
process.exit(fail ? 1 : 0);
