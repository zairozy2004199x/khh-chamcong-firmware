// Dò Vé Rẻ — máy chủ đơn hàng.
//
// Luồng: khách chọn chuyến → nhập thông tin → tạo đơn → chuyển khoản theo mã QR
// → tiền về (webhook ngân hàng hoặc mình bấm tay) → mình đi mua vé → nhập mã đặt
// chỗ → đơn xong. Mua hụt thì hoàn tiền, có nút riêng.
//
//   node booking/server.mjs
//
// Biến môi trường (bắt buộc mấy cái đầu nếu muốn thu tiền thật):
//   BANK_ID=970436  BANK_ACCOUNT=0071000123456  BANK_NAME="CONG TY TNHH K&H"  BANK_LABEL=Vietcombank
//   ADMIN_TOKEN=...            mật khẩu vào trang quản trị (bắt buộc, không có thì tự sinh)
//   WEBHOOK_SECRET=...         khoá dịch vụ báo biến động số dư gọi vào (SePay, Casso…)
//   FEE_PCT=3  FEE_FLAT=0  FEE_MIN=0     phí dịch vụ cộng vào giá vé
//   HOLD_MINUTES=30            giữ giá bao lâu trước khi đơn hết hạn
//   PORT=8788  ALLOW_ORIGIN=*  DATA=booking/data/orders.json

import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { existsSync, readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { dirname, resolve, extname } from 'node:path';
import { Store } from './store.mjs';
import { qrImage, matchCode } from './vietqr.mjs';
import { makeMailer } from './mailer.mjs';
import { soanThu } from './mail-noi-dung.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));

/* ---------- nạp cấu hình từ file .env (không cần thư viện, không cần gõ biến môi trường) ---------- */
function napEnv(file){
  if(!existsSync(file)) return false;
  try { if(process.loadEnvFile){ process.loadEnvFile(file); return true; } } catch(e){}
  for(const line of readFileSync(file, 'utf8').split('\n')){
    const m = /^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/.exec(line.replace(/^\s*#.*$/, ''));
    if(!m) continue;
    const v = m[2].replace(/^["']|["']$/g, '');
    if(!(m[1] in process.env)) process.env[m[1]] = v;
  }
  return true;
}
for(const f of [resolve(HERE, '.env'), resolve(HERE, '..', '.env')]){ if(napEnv(f)){ console.log('Đọc cấu hình từ ' + f); break; } }

const PORT = +(process.env.PORT || 8788);
const ORIGIN = process.env.ALLOW_ORIGIN || '*';
const BANK = {
  bankId: process.env.BANK_ID || '',
  account: process.env.BANK_ACCOUNT || '',
  accountName: process.env.BANK_NAME || '',
  bankLabel: process.env.BANK_LABEL || ''      // tên ngân hàng hiện cho khách, vd "Vietcombank"
};
const FEE = {
  pct: +(process.env.FEE_PCT || 0),
  flat: +(process.env.FEE_FLAT || 0),
  min: +(process.env.FEE_MIN || 0)
};
const HOLD = +(process.env.HOLD_MINUTES || 30);
const ADMIN_TOKEN = process.env.ADMIN_TOKEN || randomBytes(9).toString('base64url');
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET || '';
const DATA = process.env.DATA || resolve(HERE, 'data', 'orders.json');

const store = await new Store(DATA).init();

/* ---------- thư báo cho khách ---------- */
const mailer = makeMailer();
const MAILCFG = {
  shopName: process.env.SHOP_NAME || 'Dò Vé Rẻ',
  publicUrl: process.env.PUBLIC_URL || ('http://localhost:' + PORT),
  bank: BANK
};
// gửi xong mới ghi kết quả vào đơn; không chặn lời đáp HTTP, hỏng thì ghi lại để gửi lại được
async function guiThu(code, kind){
  const o = store.get(code);
  if(!o) return { ok: false, error: 'không có đơn ' + code };
  const to = (o.contact || {}).email;
  if(!to) return { ok: false, error: 'đơn không có email' };
  let kq;
  try {
    await mailer.send({ to, ...soanThu(kind, o, MAILCFG) });
    kq = { ok: true };
  } catch(e){
    kq = { ok: false, error: e.message };
  }
  await store.write(d => {
    const x = d.orders[code]; if(!x) return;
    const at = new Date().toISOString();
    (x.mail = x.mail || []).push({ at, kind, to, ok: kq.ok, error: kq.error || '' });
    x.log.push({ at, what: kq.ok ? 'Đã gửi thư "' + kind + '" tới ' + to
                                 : 'Gửi thư "' + kind + '" HỎNG: ' + kq.error });
  });
  return kq;
}

/* ---------- trạng thái đơn ---------- */
export const TRANG_THAI = {
  cho_thanh_toan: 'Chờ khách chuyển khoản',
  het_han:        'Quá hạn giữ giá',
  da_nhan_tien:   'Đã nhận tiền, chờ mua vé',
  dang_dat_ve:    'Đang mua vé',
  da_xuat_ve:     'Đã xuất vé',
  hoan_tien:      'Đã hoàn tiền',
  huy:            'Đã huỷ'
};

export function tinhPhi(fare){
  const raw = Math.round(fare * FEE.pct / 100) + FEE.flat;
  return Math.max(raw, FEE.min);
}

// trạng thái thấy được: đơn chưa trả mà quá giờ giữ giá thì coi như hết hạn
function view(o){
  const het = o.status === 'cho_thanh_toan' && Date.now() > Date.parse(o.expiresAt);
  return { ...o, status: het ? 'het_han' : o.status, statusText: TRANG_THAI[het ? 'het_han' : o.status] };
}

/* ---------- kiểm tra dữ liệu khách gửi lên ---------- */
function validate(b){
  const loi = [];
  const f = b.flight || {}, fare = b.fare || {}, ct = b.contact || {};
  const pax = Array.isArray(b.pax) ? b.pax.filter(p => p && String(p.full || '').trim()) : [];
  if(!f.route || !f.date) loi.push('thiếu chặng bay hoặc ngày bay');
  if(!(+fare.total > 0)) loi.push('thiếu số tiền vé');
  if(!pax.length) loi.push('chưa có hành khách nào');
  if(pax.some(p => String(p.full).trim().split(/\s+/).length < 2)) loi.push('họ tên hành khách phải đủ họ và tên');
  if(!String(ct.phone || '').replace(/\D/g,'').match(/^0\d{9}$/)) loi.push('số điện thoại phải là 10 số bắt đầu bằng 0');
  if(!String(ct.email || '').match(/^[^@\s]+@[^@\s]+\.[^@\s]+$/)) loi.push('email chưa đúng');
  if(fare.cur && fare.cur !== 'VND') loi.push('đơn chỉ nhận tiền VND, chặng này đang báo giá ' + fare.cur);
  return { loi, pax };
}

/* ---------- đối đáp HTTP ---------- */
const send = (res, code, obj) => {
  res.writeHead(code, {
    'content-type': 'application/json; charset=utf-8',
    'access-control-allow-origin': ORIGIN,
    'access-control-allow-headers': 'content-type, x-admin-token, authorization',
    'cache-control': 'no-store'
  });
  res.end(JSON.stringify(obj));
};
const body = req => new Promise((ok, no) => {
  let s = '', n = 0;
  req.on('data', c => { n += c.length; if(n > 1e6){ no(new Error('gói tin quá lớn')); req.destroy(); } s += c; });
  req.on('end', () => { try { ok(s ? JSON.parse(s) : {}); } catch(e){ no(new Error('JSON gửi lên không đọc được')); } });
  req.on('error', no);
});
const isAdmin = req => req.headers['x-admin-token'] === ADMIN_TOKEN;
const MIME = { '.html':'text/html; charset=utf-8', '.css':'text/css', '.js':'text/javascript', '.png':'image/png', '.svg':'image/svg+xml' };

/* ---------- máy chủ ---------- */
const server = createServer(async (req, res) => {
  const u = new URL(req.url, 'http://x');
  const path = u.pathname;
  if(req.method === 'OPTIONS') return send(res, 204, {});

  try {
    /* --- khách tạo đơn --- */
    if(path === '/api/orders' && req.method === 'POST'){
      const b = await body(req);
      const { loi, pax } = validate(b);
      if(loi.length) return send(res, 400, { error: loi.join('; ') });

      const fare = Math.round(+b.fare.total);
      const fee = tinhPhi(fare);
      const order = await store.write(d => {
        const code = store.nextCode();
        const now = new Date();
        const o = {
          code,
          createdAt: now.toISOString(),
          expiresAt: new Date(now.getTime() + HOLD*60000).toISOString(),
          status: 'cho_thanh_toan',
          flight: {
            route: b.flight.route, date: b.flight.date, airline: b.flight.airline || '',
            number: b.flight.number || '', dep: b.flight.dep || '', arr: b.flight.arr || '',
            stops: +b.flight.stops || 0, bag: !!b.flight.bag, cabin: b.flight.cabin || 'ECONOMY'
          },
          money: { fare, fee, total: fare + fee, paid: 0, cost: 0 },
          pax, contact: b.contact, invoice: b.invoice || {},
          pnr: '', note: '', log: [{ at: now.toISOString(), what: 'Khách tạo đơn' }]
        };
        d.orders[code] = o;
        return o;
      });

      guiThu(order.code, 'moi').catch(() => {});

      return send(res, 201, {
        ...view(order),
        bank: { ...BANK, transferNote: order.code },
        qr: qrImage({ ...BANK, amount: order.money.total, code: order.code })
      });
    }

    /* --- tạm tính để trang khách hiện phí trước khi tạo đơn --- */
    if(path === '/api/quote' && req.method === 'GET'){
      const fare = Math.round(+u.searchParams.get('fare') || 0);
      if(!(fare > 0)) return send(res, 400, { error: 'Thiếu giá vé.' });
      const fee = tinhPhi(fare);
      return send(res, 200, { fare, fee, total: fare + fee, hold: HOLD, feeRule: FEE });
    }

    /* --- khách xem đơn của mình --- */
    if(path.startsWith('/api/orders/') && req.method === 'GET'){
      const o = store.get(path.split('/')[3]);
      if(!o) return send(res, 404, { error: 'Không có đơn này.' });
      const v = view(o);
      return send(res, 200, {
        code: v.code, status: v.status, statusText: v.statusText, createdAt: v.createdAt,
        expiresAt: v.expiresAt, flight: v.flight, money: { ...v.money, cost: undefined },
        pnr: v.pnr, paxCount: v.pax.length,
        bank: v.status === 'cho_thanh_toan' ? { ...BANK, transferNote: v.code } : null,
        qr: v.status === 'cho_thanh_toan' ? qrImage({ ...BANK, amount: v.money.total, code: v.code }) : ''
      });
    }

    /* --- ngân hàng báo tiền về --- */
    if(path === '/api/webhook/bank' && req.method === 'POST'){
      const auth = req.headers.authorization || req.headers['x-webhook-secret'] || '';
      if(!WEBHOOK_SECRET || auth.replace(/^Apikey\s+/i, '') !== WEBHOOK_SECRET){
        return send(res, 401, { error: 'Sai khoá webhook.' });
      }
      const b = await body(req);
      if(b.transferType && b.transferType !== 'in') return send(res, 200, { skipped: 'không phải tiền vào' });
      const code = matchCode(b.content || b.description || '');
      const amount = Math.round(+(b.transferAmount || b.amount || 0));
      if(!code) return send(res, 200, { skipped: 'nội dung chuyển khoản không có mã đơn' });

      const out = await store.write(d => {
        const o = d.orders[code];
        if(!o) return { error: 'không thấy đơn ' + code };
        if(o.status !== 'cho_thanh_toan') return { skipped: 'đơn ' + code + ' đang ở trạng thái ' + o.status };
        o.money.paid = amount;
        o.status = 'da_nhan_tien';
        o.quaHan = Date.now() > Date.parse(o.expiresAt);
        o.thieu = o.money.total - amount;
        o.log.push({ at: new Date().toISOString(),
          what: 'Nhận ' + amount.toLocaleString('vi-VN') + 'đ'
            + (o.thieu > 0 ? ' — THIẾU ' + o.thieu.toLocaleString('vi-VN') + 'đ'
            : o.thieu < 0 ? ' — THỪA ' + (-o.thieu).toLocaleString('vi-VN') + 'đ' : '')
            + (o.quaHan ? ' — đã quá hạn giữ giá' : ''),
          ref: b.referenceCode || '' });
        return { ok: true, code, paid: amount, thieu: o.thieu, quaHan: o.quaHan };
      });
      if(out.ok) guiThu(out.code, 'da_nhan_tien').catch(() => {});
      return send(res, out.error ? 404 : 200, out);
    }

    /* --- quản trị --- */
    if(path.startsWith('/api/admin/')){
      if(!isAdmin(req)) return send(res, 401, { error: 'Thiếu hoặc sai x-admin-token.' });

      if(path === '/api/admin/orders' && req.method === 'GET'){
        const st = u.searchParams.get('status');
        let list = store.all().map(view);
        if(st) list = list.filter(o => o.status === st);
        const doanhThu = list.filter(o => o.status === 'da_xuat_ve');
        return send(res, 200, {
          orders: list,
          tong: {
            don: list.length,
            daThu: list.filter(o => ['da_nhan_tien','dang_dat_ve','da_xuat_ve'].includes(o.status))
                      .reduce((n,o) => n + o.money.paid, 0),
            daMua: doanhThu.reduce((n,o) => n + (o.money.cost||0), 0),
            lai: doanhThu.reduce((n,o) => n + (o.money.paid - (o.money.cost||0)), 0)
          },
          fee: FEE, hold: HOLD, bank: BANK
        });
      }

      const m = path.match(/^\/api\/admin\/orders\/([A-Za-z0-9]+)\/(paid|booking|refund|cancel|note|mail)$/);
      if(m && req.method === 'POST'){
        const [, code, act] = m;
        const b = await body(req);
        if(act === 'mail'){
          const o = store.get(code);
          if(!o) return send(res, 400, { error: 'Không có đơn ' + code });
          const kind = b.kind || ({ cho_thanh_toan:'moi', het_han:'moi', da_nhan_tien:'da_nhan_tien',
                                    dang_dat_ve:'da_nhan_tien', da_xuat_ve:'da_xuat_ve', hoan_tien:'hoan_tien' })[o.status];
          if(!kind) return send(res, 400, { error: 'Đơn ở trạng thái ' + o.status + ' không có mẫu thư nào.' });
          const kq = await guiThu(code, kind);
          if(!kq.ok) return send(res, 502, { error: 'Không gửi được: ' + kq.error });
          return send(res, 200, { ok: true, kind, order: view(store.get(code)) });
        }
        const out = await store.write(d => {
          const o = d.orders[code.toUpperCase()];
          if(!o) return { error: 'Không có đơn ' + code };
          const at = new Date().toISOString();
          if(act === 'paid'){
            o.money.paid = Math.round(+b.amount || o.money.total);
            o.thieu = o.money.total - o.money.paid;
            o.status = 'da_nhan_tien';
            o.log.push({ at, what: 'Đánh dấu đã nhận tiền (tay)' + (b.ref ? ' · ' + b.ref : '') });
          } else if(act === 'booking'){
            if(!b.pnr) return { error: 'Nhập mã đặt chỗ.' };
            o.pnr = String(b.pnr).toUpperCase();
            o.money.cost = Math.round(+b.cost || 0);
            o.status = 'da_xuat_ve';
            o.log.push({ at, what: 'Đã mua vé · ' + o.pnr
              + (o.money.cost ? ' · giá mua ' + o.money.cost.toLocaleString('vi-VN') + 'đ'
                 + ' · chênh ' + (o.money.paid - o.money.cost).toLocaleString('vi-VN') + 'đ' : '') });
          } else if(act === 'refund'){
            o.status = 'hoan_tien';
            o.log.push({ at, what: 'Hoàn tiền' + (b.reason ? ' — ' + b.reason : '') });
          } else if(act === 'cancel'){
            o.status = 'huy';
            o.log.push({ at, what: 'Huỷ đơn' + (b.reason ? ' — ' + b.reason : '') });
          } else if(act === 'note'){
            o.note = String(b.note || '');
            o.log.push({ at, what: 'Ghi chú: ' + o.note });
          }
          return { ok: true, order: o };
        });
        if(out.error) return send(res, 400, out);
        const mauThu = { paid: 'da_nhan_tien', booking: 'da_xuat_ve', refund: 'hoan_tien' }[act];
        if(mauThu) guiThu(code.toUpperCase(), mauThu).catch(() => {});
        return send(res, 200, { ...out, order: view(out.order) });
      }

      return send(res, 404, { error: 'Không có đường dẫn quản trị này.' });
    }

    /* --- trang tĩnh --- */
    if(req.method === 'GET'){
      if(path === '/autofill.js'){                       // dùng lại đúng hàm điền hộ của trang chính
        const buf = await readFile(resolve(HERE, '..', 'autofill-bookmarklet.js'));
        res.writeHead(200, { 'content-type': 'text/javascript; charset=utf-8' });
        return res.end(buf);
      }
      const file = path === '/' ? '/dat-ve.html' : path;
      if(/^\/[\w.-]+\.(html|css|js|svg|png)$/.test(file)){
        try {
          const buf = await readFile(resolve(HERE, 'public', file.slice(1)));
          res.writeHead(200, { 'content-type': MIME[extname(file)] || 'application/octet-stream' });
          return res.end(buf);
        } catch { /* rơi xuống 404 */ }
      }
    }
    send(res, 404, { error: 'Không có đường dẫn này.' });
  } catch(e){
    send(res, 400, { error: e.message });
  }
});

if(process.env.NODE_ENV !== 'test'){
  server.listen(PORT, () => {
    console.log('Đơn hàng Dò Vé Rẻ · http://localhost:' + PORT);
    console.log('  khách đặt   : http://localhost:' + PORT + '/dat-ve.html');
    console.log('  mình xử lý  : http://localhost:' + PORT + '/quan-tri.html   (token: ' + ADMIN_TOKEN + ')');
    if(!BANK.account) console.log('  ⚠ chưa khai BANK_ID/BANK_ACCOUNT — chưa sinh được mã QR chuyển khoản');
    if(!WEBHOOK_SECRET) console.log('  ⚠ chưa khai WEBHOOK_SECRET — tiền về phải tự bấm "Đã nhận tiền"');
    console.log('  thư báo khách: ' + mailer.mota());
    console.log('  phí dịch vụ : ' + FEE.pct + '% + ' + FEE.flat + 'đ, tối thiểu ' + FEE.min + 'đ · giữ giá ' + HOLD + ' phút');
  });
}
export { server, store, ADMIN_TOKEN, BANK, FEE, HOLD };
