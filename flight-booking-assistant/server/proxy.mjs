// Dò Vé Rẻ — proxy giá thật.
//
// Trình duyệt không gọi thẳng Amadeus được (CORS, và gọi thẳng là lộ khoá cho mọi người xem
// mã nguồn trang). Proxy này đứng giữa: giữ khoá ở máy chủ, xin token, gọi Amadeus, rồi trả về
// đúng hình dạng mà index.html đang dùng.
//
//   AMADEUS_ID=... AMADEUS_SECRET=... node server/proxy.mjs
//   node server/proxy.mjs --mock          (chạy bằng dữ liệu mẫu, không cần khoá)
//
// Biến môi trường:
//   AMADEUS_ID, AMADEUS_SECRET   khoá lấy ở developers.amadeus.com (gói Self-Service có bậc miễn phí)
//   AMADEUS_ENV=test|production  mặc định test
//   PORT                         mặc định 8787
//   ALLOW_ORIGIN                 mặc định * — đặt lại nếu đưa proxy lên máy chủ chung
//   VND_RATE                     tỉ giá quy đổi khi Amadeus trả tiền tệ khác VND (vd 26200)
//   TAX_BASE                     dịch vụ tra mã số thuế, mặc định https://api.vietqr.io/v2/business/

import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const MOCK = process.argv.includes('--mock');
const PORT = +(process.env.PORT || 8787);
const ORIGIN = process.env.ALLOW_ORIGIN || '*';
const HOST = (process.env.AMADEUS_ENV === 'production' ? 'https://api.amadeus.com' : 'https://test.api.amadeus.com');
const ID = process.env.AMADEUS_ID, SECRET = process.env.AMADEUS_SECRET;
const BASE = process.env.AMADEUS_BASE || HOST;          // để test trỏ sang máy chủ giả
const RATE = +(process.env.VND_RATE || 0);
const TAX_BASE = process.env.TAX_BASE || 'https://api.vietqr.io/v2/business/';

if (!MOCK && (!ID || !SECRET)) {
  console.error('Thiếu AMADEUS_ID / AMADEUS_SECRET. Chạy thử không khoá: node server/proxy.mjs --mock');
  process.exit(1);
}

/* ---------- token: xin một lần, dùng tới lúc hết hạn ---------- */
let token = { value: '', exp: 0 };
async function getToken(){
  if (token.value && Date.now() < token.exp - 30000) return token.value;
  const r = await fetch(BASE + '/v1/security/oauth2/token', {
    method: 'POST',
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ grant_type: 'client_credentials', client_id: ID, client_secret: SECRET })
  });
  const j = await r.json().catch(() => ({}));
  if (!r.ok || !j.access_token) {
    throw new Error('Amadeus từ chối khoá (' + r.status + '): ' + (j.error_description || j.error || 'không rõ'));
  }
  token = { value: j.access_token, exp: Date.now() + (j.expires_in || 1799) * 1000 };
  return token.value;
}

/* ---------- cache: giá vé không đổi từng giây, 5 phút là đủ tươi ---------- */
const cache = new Map();
const TTL = 5 * 60 * 1000;
function cached(key){
  const hit = cache.get(key);
  if (hit && Date.now() < hit.exp) return hit.data;
  cache.delete(key);
  return null;
}
function keep(key, data){
  cache.set(key, { data, exp: Date.now() + TTL });
  if (cache.size > 200) cache.delete(cache.keys().next().value);
}

/* ---------- đổi dữ liệu Amadeus sang hình dạng của bảng giá ---------- */
const AIRLINE_SITE = {
  VN: 'https://www.vietnamairlines.com/vn/vi/home',
  VJ: 'https://www.vietjetair.com/vi',
  QH: 'https://www.bambooairways.com/vn/vi/',
  VU: 'https://www.vietravelairlines.com/vi'
};
const titleCase = s => String(s || '').toLowerCase().replace(/\b[a-z]/g, c => c.toUpperCase());
const isoMinutes = d => {
  const m = /P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?/.exec(d || '') || [];
  return (+m[1] || 0) * 1440 + (+m[2] || 0) * 60 + (+m[3] || 0);
};
const hhmm = at => String(at || '').slice(11, 16);
const dayOf = at => String(at || '').slice(0, 10);

function mapOffers(body, q){
  const carriers = (body.dictionaries && body.dictionaries.carriers) || {};
  return (body.data || []).map(o => {
    const it = (o.itineraries || [])[0];
    if (!it || !it.segments || !it.segments.length) return null;
    const segs = it.segments;
    const first = segs[0], last = segs[segs.length - 1];
    const code = first.carrierCode || (o.validatingAirlineCodes || [])[0] || '??';
    const tp = (o.travelerPricings || [])[0] || {};
    const fd = (tp.fareDetailsBySegment || [])[0] || {};
    const bags = (fd.includedCheckedBags || {}).quantity;
    const perPax = +((tp.price || {}).total) || 0;
    const total = +((o.price || {}).grandTotal || (o.price || {}).total) || 0;
    const cur = (o.price || {}).currency || 'VND';
    const f = (cur !== 'VND' && RATE) ? RATE : 1;
    return {
      al: { code, name: titleCase(carriers[code]) || code, site: AIRLINE_SITE[code] || '' },
      code: code + (first.number || ''),
      dep: hhmm(first.departure && first.departure.at),
      arr: hhmm(last.arrival && last.arrival.at),
      overnight: dayOf(last.arrival && last.arrival.at) !== dayOf(first.departure && first.departure.at),
      mins: isoMinutes(it.duration) || segs.reduce((n, s) => n + isoMinutes(s.duration), 0),
      stops: segs.length - 1,
      bag: bags > 0,
      bagText: bags > 0 ? bags + ' kiện ký gửi' : 'chỉ xách tay',
      cabin: fd.cabin || q.cabin || 'ECONOMY',
      seller: { name: 'Amadeus (GDS)', note: 'đặt lại trên trang hãng' },
      price: Math.round(perPax * f),
      total: Math.round(total * f),
      cur: (cur !== 'VND' && RATE) ? 'VND' : cur,
      srcCur: cur,
      depHour: +hhmm(first.departure && first.departure.at).slice(0, 2) || 0
    };
  }).filter(Boolean).sort((a, b) => a.price - b.price);
}

/* ---------- gọi Amadeus ---------- */
async function search(q){
  if (MOCK) {
    const raw = JSON.parse(await readFile(resolve(HERE, 'fixtures', 'amadeus-offers.json'), 'utf8'));
    return { offers: mapOffers(raw, q), source: 'mock' };
  }
  const p = new URLSearchParams({
    originLocationCode: q.from,
    destinationLocationCode: q.to,
    departureDate: q.dep,
    adults: String(q.adt || 1),
    currencyCode: q.cur || 'VND',
    max: String(q.max || 25)
  });
  if (q.ret) p.set('returnDate', q.ret);
  if (+q.chd) p.set('children', String(q.chd));
  if (+q.inf) p.set('infants', String(q.inf));
  if (q.cabin) p.set('travelClass', q.cabin);
  if (q.direct === '1') p.set('nonStop', 'true');

  const r = await fetch(BASE + '/v2/shopping/flight-offers?' + p, {
    headers: { Authorization: 'Bearer ' + await getToken() }
  });
  const body = await r.json().catch(() => ({}));
  if (!r.ok) {
    const e = (body.errors || [])[0] || {};
    throw new Error('Amadeus ' + r.status + ': ' + (e.detail || e.title || 'không rõ lỗi'));
  }
  return { offers: mapOffers(body, q), source: BASE.includes('test.') ? 'amadeus-test' : 'amadeus' };
}

/* ---------- tra mã số thuế → tên và địa chỉ doanh nghiệp ---------- */
const taxCache = new Map();
async function lookupTax(mst){
  const key = String(mst).replace(/[^\d-]/g,'');
  if (!/^\d{10}(-\d{3})?$/.test(key)) throw new Error('Mã số thuế phải là 10 số (hoặc 10-3 số cho chi nhánh).');
  const hit = taxCache.get(key);
  if (hit && Date.now() < hit.exp) return { ...hit.data, cached: true };

  if (MOCK) {
    const data = { tax: key, company: 'CÔNG TY TNHH K&H (dữ liệu mẫu)', address: 'Số 1, đường ABC, phường XYZ, TP HCM', shortName: 'K&H' };
    taxCache.set(key, { data, exp: Date.now() + 86400000 });
    return { ...data, cached: false };
  }

  const r = await fetch(TAX_BASE + encodeURIComponent(key), { headers: { accept: 'application/json' } });
  const j = await r.json().catch(() => ({}));
  // VietQR v2: { code: "00", data: { name, internationalName, shortName, address } }
  const d = j.data || j;
  const name = d.name || d.tenDoanhNghiep || d.companyName;
  if (!r.ok || (j.code && j.code !== '00') || !name) {
    throw new Error('Không tra được MST ' + key + (j.desc ? ' — ' + j.desc : ''));
  }
  const data = { tax: key, company: name, address: d.address || d.diaChi || '', shortName: d.shortName || '' };
  taxCache.set(key, { data, exp: Date.now() + 86400000 });
  if (taxCache.size > 500) taxCache.delete(taxCache.keys().next().value);
  return { ...data, cached: false };
}

/* ---------- máy chủ ---------- */
const send = (res, code, obj) => {
  res.writeHead(code, {
    'content-type': 'application/json; charset=utf-8',
    'access-control-allow-origin': ORIGIN,
    'access-control-allow-headers': 'content-type',
    'cache-control': 'no-store'
  });
  res.end(JSON.stringify(obj));
};

const server = createServer(async (req, res) => {
  const u = new URL(req.url, 'http://x');
  if (req.method === 'OPTIONS') return send(res, 204, {});

  if (u.pathname === '/api/health') {
    return send(res, 200, {
      ok: true,
      provider: MOCK ? 'mock' : 'amadeus',
      env: MOCK ? 'mock' : (BASE.includes('test.') ? 'test' : 'production'),
      tax: MOCK ? 'mock' : TAX_BASE,
      cached: cache.size
    });
  }

  if (u.pathname === '/api/offers') {
    const q = Object.fromEntries(u.searchParams);
    if (!q.from || !q.to || !q.dep) return send(res, 400, { error: 'Thiếu tham số from / to / dep.' });
    const key = JSON.stringify(q);
    const hit = cached(key);
    if (hit) return send(res, 200, { ...hit, cached: true });
    try {
      const out = await search(q);
      keep(key, out);
      return send(res, 200, { ...out, cached: false });
    } catch (e) {
      return send(res, 502, { error: e.message });
    }
  }

  if (u.pathname === '/api/tax') {
    const mst = u.searchParams.get('mst') || '';
    try { return send(res, 200, await lookupTax(mst)); }
    catch (e) { return send(res, 404, { error: e.message }); }
  }

  send(res, 404, { error: 'Chỉ có /api/offers, /api/tax và /api/health.' });
});

server.listen(PORT, () => {
  console.log('Dò Vé Rẻ proxy · http://localhost:' + PORT
    + (MOCK ? '  [dữ liệu mẫu, không gọi Amadeus]' : '  [' + (BASE.includes('test.') ? 'amadeus test' : 'amadeus production') + ']'));
  console.log('Dán địa chỉ này vào ô "Proxy giá thật" trong Dò Vé Rẻ.');
});
