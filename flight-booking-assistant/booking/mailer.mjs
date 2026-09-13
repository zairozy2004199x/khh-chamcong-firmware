// Gửi email cho khách — không dùng thư viện ngoài.
//
// Ba chế độ, khai bằng MAIL_MODE trong .env:
//   log   (mặc định) chỉ in ra màn hình, không gửi đi đâu — dùng khi chạy thử
//   smtp  nói chuyện thẳng với máy chủ SMTP (Gmail, hộp thư của công ty…)
//   http  gọi API của dịch vụ gửi thư (Resend, Brevo…) — một lời gọi fetch
//
// SMTP hỗ trợ cả cổng 465 (TLS ngay từ đầu) lẫn 587 (STARTTLS), xác thực AUTH LOGIN.

import net from 'node:net';
import tls from 'node:tls';

/* ---------- mã hoá tiếng Việt cho đúng chuẩn thư ---------- */
const b64 = s => Buffer.from(s, 'utf8').toString('base64');
// Tiêu đề có dấu phải bọc theo RFC 2047, không thì client hiện ra chữ rác
export const encodeHeader = s =>
  /^[\x20-\x7E]*$/.test(s) ? s : '=?UTF-8?B?' + b64(s) + '?=';
// Thân thư base64 phải bẻ dòng 76 ký tự
const wrap = s => (s.match(/.{1,76}/g) || []).join('\r\n');

export function buildMessage({ from, fromName, to, subject, text, html, replyTo }){
  const bound = 'dvr' + Math.random().toString(36).slice(2, 12);
  const head = [
    'From: ' + (fromName ? encodeHeader(fromName) + ' <' + from + '>' : from),
    'To: ' + to,
    replyTo ? 'Reply-To: ' + replyTo : '',
    'Subject: ' + encodeHeader(subject),
    'Date: ' + new Date().toUTCString(),
    'MIME-Version: 1.0',
    'Content-Type: multipart/alternative; boundary="' + bound + '"'
  ].filter(Boolean).join('\r\n');

  const body = [
    '',
    '--' + bound,
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    '',
    wrap(b64(text || '')),
    '--' + bound,
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    '',
    wrap(b64(html || '')),
    '--' + bound + '--',
    ''
  ].join('\r\n');

  return head + '\r\n' + body;
}

/* ---------- nói chuyện với máy chủ SMTP ---------- */
function doiTra(sock){
  // đọc tới khi gặp dòng kết (mã 3 số + dấu cách), trả về { code, text }
  return new Promise((ok, no) => {
    let buf = '';
    const onData = d => {
      buf += d.toString('utf8');
      const dong = buf.split('\r\n').filter(Boolean);
      const cuoi = dong[dong.length - 1] || '';
      if(/^\d{3} /.test(cuoi)){
        cleanup();
        ok({ code: +cuoi.slice(0, 3), text: buf.trim() });
      }
    };
    const onErr = e => { cleanup(); no(e); };
    const onEnd = () => { cleanup(); no(new Error('máy chủ SMTP đóng kết nối giữa chừng')); };
    const cleanup = () => { sock.off('data', onData); sock.off('error', onErr); sock.off('end', onEnd); };
    sock.on('data', onData); sock.on('error', onErr); sock.on('end', onEnd);
  });
}
async function noi(sock, lenh, chờ){
  if(lenh !== null) sock.write(lenh + '\r\n');
  const r = await doiTra(sock);
  if(chờ && !chờ.includes(r.code)){
    throw new Error('SMTP trả ' + r.code + ' cho "' + String(lenh).split(' ')[0] + '": ' + r.text.split('\r\n').pop());
  }
  return r;
}

async function guiSMTP(cfg, msg){
  const port = +cfg.port || 587;
  const secure = cfg.secure !== undefined ? cfg.secure : port === 465;
  let sock = secure
    ? tls.connect({ host: cfg.host, port, servername: cfg.host, rejectUnauthorized: cfg.rejectUnauthorized !== false })
    : net.connect({ host: cfg.host, port });

  await new Promise((ok, no) => {
    sock.once(secure ? 'secureConnect' : 'connect', ok);
    sock.once('error', no);
  });
  sock.setTimeout(+cfg.timeout || 20000, () => sock.destroy(new Error('SMTP quá hạn chờ')));

  try {
    await noi(sock, null, [220]);
    let chao = await noi(sock, 'EHLO dovere.local', [250]);

    if(!secure && /STARTTLS/i.test(chao.text)){
      await noi(sock, 'STARTTLS', [220]);
      sock = tls.connect({ socket: sock, servername: cfg.host, rejectUnauthorized: cfg.rejectUnauthorized !== false });
      await new Promise((ok, no) => { sock.once('secureConnect', ok); sock.once('error', no); });
      chao = await noi(sock, 'EHLO dovere.local', [250]);
    }

    if(cfg.user){
      await noi(sock, 'AUTH LOGIN', [334]);
      await noi(sock, b64(cfg.user), [334]);
      await noi(sock, b64(cfg.pass || ''), [235]);
    }

    await noi(sock, 'MAIL FROM:<' + msg.from + '>', [250]);
    await noi(sock, 'RCPT TO:<' + msg.to + '>', [250, 251]);
    await noi(sock, 'DATA', [354]);
    // dòng chỉ có dấu chấm là dấu kết thúc thư, nên phải nhân đôi chấm đầu dòng
    sock.write(msg.raw.replace(/\r\n\./g, '\r\n..') + '\r\n.\r\n');
    await noi(sock, null, [250]);
    await noi(sock, 'QUIT', [221]).catch(() => {});
    return { ok: true };
  } finally {
    sock.destroy();
  }
}

/* ---------- gọi API dịch vụ gửi thư ---------- */
async function guiHTTP(cfg, msg){
  const r = await fetch(cfg.apiUrl, {
    method: 'POST',
    headers: { 'content-type': 'application/json', authorization: 'Bearer ' + cfg.apiKey },
    body: JSON.stringify({
      from: cfg.fromName ? cfg.fromName + ' <' + msg.from + '>' : msg.from,
      to: [msg.to], subject: msg.subject, text: msg.text, html: msg.html
    })
  });
  const j = await r.json().catch(() => ({}));
  if(!r.ok) throw new Error('dịch vụ gửi thư trả ' + r.status + ': ' + (j.message || j.error || ''));
  return { ok: true, id: j.id };
}

/* ---------- cửa chính ---------- */
export function makeMailer(env = process.env){
  const cfg = {
    mode: (env.MAIL_MODE || 'log').toLowerCase(),
    from: env.MAIL_FROM || 've@dovere.local',
    fromName: env.MAIL_FROM_NAME || env.SHOP_NAME || 'Dò Vé Rẻ',
    replyTo: env.MAIL_REPLY_TO || '',
    host: env.SMTP_HOST, port: env.SMTP_PORT, user: env.SMTP_USER, pass: env.SMTP_PASS,
    secure: env.SMTP_SECURE === '1' ? true : env.SMTP_SECURE === '0' ? false : undefined,
    rejectUnauthorized: env.SMTP_INSECURE === '1' ? false : true,
    apiUrl: env.MAIL_API_URL, apiKey: env.MAIL_API_KEY
  };

  return {
    cfg,
    mota(){
      if(cfg.mode === 'smtp') return 'smtp ' + cfg.host + ':' + (cfg.port || 587) + (cfg.user ? ' (' + cfg.user + ')' : '');
      if(cfg.mode === 'http') return 'http ' + cfg.apiUrl;
      return 'log (chỉ in ra màn hình, chưa gửi thật)';
    },
    async send({ to, subject, text, html }){
      if(!to) throw new Error('thiếu địa chỉ người nhận');
      const msg = { from: cfg.from, to, subject, text, html,
                    raw: buildMessage({ ...cfg, to, subject, text, html }) };
      if(cfg.mode === 'smtp'){
        if(!cfg.host) throw new Error('MAIL_MODE=smtp nhưng chưa khai SMTP_HOST');
        return guiSMTP(cfg, msg);
      }
      if(cfg.mode === 'http'){
        if(!cfg.apiUrl) throw new Error('MAIL_MODE=http nhưng chưa khai MAIL_API_URL');
        return guiHTTP(cfg, msg);
      }
      console.log('\n─── thư (chế độ log, không gửi đi) ───');
      console.log('Tới    : ' + to);
      console.log('Tiêu đề: ' + subject);
      console.log(text.trim().split('\n').map(l => '  ' + l).join('\n'));
      console.log('───────────────────────────────────────\n');
      return { ok: true, logged: true };
    }
  };
}
