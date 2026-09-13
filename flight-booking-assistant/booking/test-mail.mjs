// Chạy: node booking/test-mail.mjs
// Dựng máy chủ SMTP giả (cả cổng thường lẫn STARTTLS) và một dịch vụ gửi thư giả,
// rồi bắt mailer thật nói chuyện với chúng. Không gửi thư nào ra ngoài.

import net from 'node:net';
import tls from 'node:tls';
import { createServer } from 'node:http';
import { execFileSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { makeMailer, buildMessage, encodeHeader } from './mailer.mjs';
import { soanThu } from './mail-noi-dung.mjs';

let pass = 0, fail = 0;
const ok = (name, cond, extra) => {
  if(cond){ pass++; console.log('  ✓ ' + name); }
  else { fail++; console.log('  ✗ ' + name + (extra !== undefined ? '  → ' + JSON.stringify(extra) : '')); }
};
const giaiMa = s => Buffer.from(s, 'base64').toString('utf8');

const DON = {
  code: 'DVR26090001', expiresAt: new Date(Date.now() + 18e5).toISOString(), pnr: 'ABC123', thieu: 0,
  flight: { route: 'SGN-HAN', date: '2026-10-04', airline: 'VJ', number: 'VJ120', dep: '06:15', arr: '08:25' },
  money: { fare: 1690000, fee: 50700, total: 1740700, paid: 1740700 },
  pax: [{ full: 'NGUYỄN VĂN A' }], contact: { email: 'khach@congty.com' }, log: []
};

/* ---------- máy chủ SMTP giả ---------- */
function smtpGia({ port, starttls = false, pem = null, authOk = true }){
  const thu = [];
  const phucVu = (sock, secure, chao = true) => {
    let buf = '', data = null, user = '', pass = '', cho = null, from = '', to = '';
    // sau STARTTLS máy chủ thật KHÔNG chào lại, khách gửi EHLO ngay
    if(chao) sock.write('220 gia.local ESMTP\r\n');
    sock.on('data', c => {
      buf += c.toString('utf8');
      if(data !== null){
        const het = buf.indexOf('\r\n.\r\n');
        if(het === -1) return;
        data += buf.slice(0, het);
        thu.push({ from, to, user, pass, raw: data, secure });
        buf = buf.slice(het + 5); data = null;
        sock.write('250 OK nhan roi\r\n');
      }
      let i;
      while(data === null && (i = buf.indexOf('\r\n')) !== -1){
        const line = buf.slice(0, i); buf = buf.slice(i + 2);
        if(cho === 'user'){ user = giaiMa(line); cho = 'pass'; sock.write('334 UGFzc3dvcmQ6\r\n'); continue; }
        if(cho === 'pass'){
          pass = giaiMa(line); cho = null;
          sock.write(authOk ? '235 xac thuc ok\r\n' : '535 sai mat khau\r\n'); continue;
        }
        const [lenh, ...rest] = line.split(' ');
        const L = lenh.toUpperCase();
        if(L === 'EHLO' || L === 'HELO'){
          sock.write('250-gia.local\r\n' + (starttls && !secure ? '250-STARTTLS\r\n' : '') + '250 AUTH LOGIN\r\n');
        } else if(L === 'STARTTLS'){
          sock.write('220 san sang\r\n');
          const up = new tls.TLSSocket(sock, { isServer: true, cert: pem.cert, key: pem.key });
          up.on('secure', () => phucVu(up, true, false));
          return;
        } else if(L === 'AUTH'){ cho = 'user'; sock.write('334 VXNlcm5hbWU6\r\n'); }
        else if(L === 'MAIL'){ from = /<(.*)>/.exec(rest.join(' '))?.[1] || ''; sock.write('250 OK\r\n'); }
        else if(L === 'RCPT'){ to = /<(.*)>/.exec(rest.join(' '))?.[1] || ''; sock.write('250 OK\r\n'); }
        else if(L === 'DATA'){ data = ''; sock.write('354 moi gui\r\n'); }
        else if(L === 'QUIT'){ sock.write('221 tam biet\r\n'); sock.end(); }
        else sock.write('250 OK\r\n');
      }
    });
    sock.on('error', () => {});
  };
  const srv = net.createServer(s => phucVu(s, false));
  return new Promise(r => srv.listen(port, () => r({ srv, thu })));
}

const dir = await mkdtemp(join(tmpdir(), 'dvr-mail-'));
execFileSync('openssl', ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-subj', '/CN=localhost',
  '-keyout', join(dir, 'k.pem'), '-out', join(dir, 'c.pem'), '-days', '2'], { stdio: 'ignore' });
const pem = { key: await readFile(join(dir, 'k.pem')), cert: await readFile(join(dir, 'c.pem')) };

const A = await smtpGia({ port: 9931 });
const B = await smtpGia({ port: 9932, starttls: true, pem });
const C = await smtpGia({ port: 9933, authOk: false });

/* ---------- dịch vụ gửi thư giả (kiểu Resend) ---------- */
let httpNhan = null;
const http = createServer((req, res) => {
  let s = '';
  req.on('data', c => s += c);
  req.on('end', () => {
    httpNhan = { auth: req.headers.authorization, body: JSON.parse(s || '{}') };
    res.writeHead(200, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ id: 'msg_1' }));
  });
});
await new Promise(r => http.listen(9934, r));

try {
  console.log('\nĐóng gói thư');
  ok('tiêu đề tiếng Việt bọc RFC 2047', encodeHeader('Vé đã xuất').startsWith('=?UTF-8?B?'));
  ok('giải ngược ra đúng chữ', giaiMa(encodeHeader('Vé đã xuất').slice(10, -2)) === 'Vé đã xuất');
  ok('tiêu đề thuần ASCII thì để nguyên', encodeHeader('Order DVR1') === 'Order DVR1');
  const raw = buildMessage({ from: 'a@b.c', fromName: 'Vé K&H', to: 'x@y.z', subject: 'Thử', text: 'a', html: '<b>a</b>' });
  ok('có hai phần text và html', /multipart\/alternative/.test(raw) && /text\/plain/.test(raw) && /text\/html/.test(raw));
  ok('tên người gửi có dấu cũng được bọc', /From: =\?UTF-8\?B\?.*<a@b\.c>/.test(raw), raw.split('\r\n')[0]);

  console.log('\nGửi qua SMTP thường + AUTH LOGIN');
  const m1 = makeMailer({ MAIL_MODE: 'smtp', SMTP_HOST: '127.0.0.1', SMTP_PORT: '9931', SMTP_SECURE: '0',
                          SMTP_USER: 've@knh.vn', SMTP_PASS: 'matkhau', MAIL_FROM: 've@knh.vn', SHOP_NAME: 'Vé K&H' });
  const t = soanThu('da_xuat_ve', DON, { shopName: 'Vé K&H', publicUrl: 'https://ve.knh.vn' });
  const r1 = await m1.send({ to: DON.contact.email, ...t });
  ok('gửi trót lọt', r1.ok === true, r1);
  const g = A.thu[0] || {};
  ok('đúng người gửi và người nhận', g.from === 've@knh.vn' && g.to === 'khach@congty.com', { from: g.from, to: g.to });
  ok('xác thực đúng tài khoản', g.user === 've@knh.vn' && g.pass === 'matkhau', { user: g.user });
  const phanText = giaiMa((/text\/plain[\s\S]*?\r\n\r\n([\s\S]*?)\r\n--/.exec(g.raw) || [])[1].replace(/\r\n/g, ''));
  ok('thân thư có mã đặt chỗ', phanText.includes('ABC123'), phanText.slice(0, 60));
  ok('thân thư có tên khách có dấu', phanText.includes('NGUYỄN VĂN A'));
  ok('tiêu đề đi qua nguyên vẹn', giaiMa((/Subject: =\?UTF-8\?B\?(.*)\?=/.exec(g.raw) || [])[1]).includes('ABC123'));

  console.log('\nGửi qua STARTTLS (đường của Gmail cổng 587)');
  const m2 = makeMailer({ MAIL_MODE: 'smtp', SMTP_HOST: 'localhost', SMTP_PORT: '9932', SMTP_SECURE: '0',
                          SMTP_USER: 'u', SMTP_PASS: 'p', SMTP_INSECURE: '1', MAIL_FROM: 've@knh.vn' });
  const r2 = await m2.send({ to: 'khach@congty.com', ...t });
  ok('nâng cấp lên TLS rồi gửi được', r2.ok === true, r2);
  ok('thư đi trên kênh đã mã hoá', (B.thu[0] || {}).secure === true, B.thu[0] && B.thu[0].secure);

  console.log('\nSai mật khẩu');
  const m3 = makeMailer({ MAIL_MODE: 'smtp', SMTP_HOST: '127.0.0.1', SMTP_PORT: '9933', SMTP_SECURE: '0',
                          SMTP_USER: 'u', SMTP_PASS: 'sai', MAIL_FROM: 've@knh.vn' });
  const e3 = await m3.send({ to: 'khach@congty.com', ...t }).catch(e => e);
  ok('báo lỗi rõ ràng, không nuốt', e3 instanceof Error && /535/.test(e3.message), String(e3));

  console.log('\nGửi qua API dịch vụ thư');
  const m4 = makeMailer({ MAIL_MODE: 'http', MAIL_API_URL: 'http://127.0.0.1:9934/emails', MAIL_API_KEY: 'key_1',
                          MAIL_FROM: 've@knh.vn', MAIL_FROM_NAME: 'Vé K&H' });
  const r4 = await m4.send({ to: 'khach@congty.com', ...t });
  ok('gọi API thành công', r4.ok === true && r4.id === 'msg_1', r4);
  ok('gửi kèm khoá', httpNhan.auth === 'Bearer key_1', httpNhan.auth);
  ok('gửi đủ text lẫn html', !!httpNhan.body.html && !!httpNhan.body.text && httpNhan.body.to[0] === 'khach@congty.com');

  console.log('\nChế độ log');
  const m5 = makeMailer({ MAIL_MODE: 'log' });
  const r5 = await m5.send({ to: 'ai@do.vn', subject: 'x', text: 'y', html: '<i>y</i>' });
  ok('không chạm mạng, vẫn báo ok', r5.logged === true, r5);
  ok('mô tả chế độ cho người dùng biết', /log/.test(m5.mota()), m5.mota());

  console.log('\nThiếu cấu hình');
  const e6 = await makeMailer({ MAIL_MODE: 'smtp' }).send({ to: 'a@b.c', subject:'x', text:'y', html:'z' }).catch(e => e);
  ok('nhắc đúng biến còn thiếu', /SMTP_HOST/.test(String(e6.message)), String(e6));
  const e7 = await makeMailer({ MAIL_MODE: 'log' }).send({ subject:'x', text:'y', html:'z' }).catch(e => e);
  ok('thiếu người nhận thì chặn', /người nhận/.test(String(e7.message)), String(e7));
} finally {
  A.srv.close(); B.srv.close(); C.srv.close(); http.close();
  await rm(dir, { recursive: true, force: true });
}
console.log('\n' + pass + ' đạt, ' + fail + ' hỏng');
process.exit(fail ? 1 : 0);
