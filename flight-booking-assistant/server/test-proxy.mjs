// Chạy: node server/test-proxy.mjs
// Dựng một "Amadeus giả" và một dịch vụ tra MST giả đúng schema thật, rồi bắt proxy gọi vào đó.
// Không cần khoá, không chạm mạng ngoài.

import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { readFile } from 'node:fs/promises';

const HERE = dirname(fileURLToPath(import.meta.url));
let pass = 0, fail = 0;
const ok = (name, cond, extra) => {
  if (cond) { pass++; console.log('  ✓ ' + name); }
  else { fail++; console.log('  ✗ ' + name + (extra !== undefined ? '  → ' + JSON.stringify(extra) : '')); }
};
const sleep = ms => new Promise(r => setTimeout(r, ms));

/* --- Amadeus giả --- */
let tokenCalls = 0, searchCalls = 0, lastQuery = null;
const fake = createServer(async (req, res) => {
  const u = new URL(req.url, 'http://x');
  if (u.pathname === '/v1/security/oauth2/token') {
    tokenCalls++;
    res.writeHead(200, { 'content-type': 'application/json' });
    return res.end(JSON.stringify({ access_token: 'tok_' + tokenCalls, expires_in: 1799, token_type: 'Bearer' }));
  }
  if (u.pathname === '/v2/shopping/flight-offers') {
    searchCalls++;
    lastQuery = Object.fromEntries(u.searchParams);
    if (req.headers.authorization !== 'Bearer tok_1') {
      res.writeHead(401, { 'content-type': 'application/json' });
      return res.end(JSON.stringify({ errors: [{ title: 'Invalid access token' }] }));
    }
    res.writeHead(200, { 'content-type': 'application/json' });
    return res.end(await readFile(resolve(HERE, 'fixtures', 'amadeus-offers.json')));
  }
  if (u.pathname.startsWith('/tax/')) {
    const mst = u.pathname.split('/').pop();
    res.writeHead(200, { 'content-type': 'application/json' });
    if (mst === '0312345678') {
      return res.end(JSON.stringify({ code: '00', desc: 'Thành công', data: {
        id: '0312345678', name: 'CÔNG TY TNHH THƯƠNG MẠI K&H', internationalName: 'K&H TRADING CO., LTD',
        shortName: 'K&H', address: '12 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP Hồ Chí Minh' } }));
    }
    return res.end(JSON.stringify({ code: '51', desc: 'Không tìm thấy doanh nghiệp' }));
  }
  res.writeHead(404); res.end('{}');
});
await new Promise(r => fake.listen(9911, r));

/* --- proxy thật, trỏ vào Amadeus giả --- */
const proc = spawn(process.execPath, [resolve(HERE, 'proxy.mjs')], {
  env: { ...process.env, PORT: '9912', AMADEUS_ID: 'id', AMADEUS_SECRET: 'secret',
         AMADEUS_BASE: 'http://127.0.0.1:9911', TAX_BASE: 'http://127.0.0.1:9911/tax/' },
  stdio: 'ignore'
});
await sleep(700);
const get = async path => { const r = await fetch('http://127.0.0.1:9912' + path); return { status: r.status, body: await r.json() }; };

try {
  console.log('\nSức khoẻ');
  const h = await get('/api/health');
  ok('trả ok', h.body.ok === true, h.body);
  ok('báo đúng nhà cung cấp', h.body.provider === 'amadeus', h.body);

  console.log('\nDò giá');
  const r1 = await get('/api/offers?from=SGN&to=HAN&dep=2026-10-04&adt=1&cabin=ECONOMY&direct=1');
  const o = r1.body.offers || [];
  ok('gọi được, có kết quả', r1.status === 200 && o.length === 3, r1.body.error);
  ok('xin token đúng 1 lần', tokenCalls === 1, tokenCalls);
  ok('chuyển tham số sang Amadeus', lastQuery.originLocationCode === 'SGN' && lastQuery.departureDate === '2026-10-04'
     && lastQuery.nonStop === 'true' && lastQuery.travelClass === 'ECONOMY', lastQuery);
  ok('xếp từ rẻ tới đắt', o[0].price <= o[1].price && o[1].price <= o[2].price, o.map(x => x.price));
  ok('rẻ nhất là chuyến 1.450.000 của QH', o[0].price === 1450000 && o[0].al.code === 'QH', o[0]);
  ok('tên hãng lấy từ dictionaries', o[0].al.name === 'Bamboo Airways', o[0].al);
  ok('đếm đúng điểm dừng', o[0].stops === 1 && o[1].stops === 0, o.map(x => x.stops));
  ok('giờ bay lấy từ segment', o[1].dep === '06:15' && o[1].arr === '08:25', [o[1].dep, o[1].arr]);
  ok('đổi ISO8601 sang phút', o[1].mins === 130, o[1].mins);
  ok('nhận ra chuyến qua đêm', o[0].overnight === true, o[0]);
  ok('đọc hành lý ký gửi', o[0].bag === true && o[1].bag === false, o.map(x => x.bag));
  ok('giữ nguyên tiền tệ VND', o[0].cur === 'VND', o[0].cur);

  console.log('\nCache');
  const r2 = await get('/api/offers?from=SGN&to=HAN&dep=2026-10-04&adt=1&cabin=ECONOMY&direct=1');
  ok('lần hai lấy từ cache', r2.body.cached === true && searchCalls === 1, { cached: r2.body.cached, searchCalls });
  await get('/api/offers?from=SGN&to=DAD&dep=2026-10-04&adt=1');
  ok('chặng khác thì gọi lại', searchCalls === 2, searchCalls);

  console.log('\nTra mã số thuế');
  const t1 = await get('/api/tax?mst=0312345678');
  ok('trả tên công ty', t1.body.company === 'CÔNG TY TNHH THƯƠNG MẠI K&H', t1.body);
  ok('trả địa chỉ', /Nguyễn Huệ/.test(t1.body.address || ''), t1.body);
  const t2 = await get('/api/tax?mst=0312345678');
  ok('nhớ kết quả đã tra', t2.body.cached === true, t2.body);
  const t3 = await get('/api/tax?mst=9999999999');
  ok('MST không có thì báo rõ', t3.status === 404 && /Không tra được/.test(t3.body.error), t3.body);
  const t4 = await get('/api/tax?mst=123');
  ok('MST sai định dạng thì chặn sớm', t4.status === 404 && /10 số/.test(t4.body.error), t4.body);

  console.log('\nLỗi');
  const e1 = await get('/api/offers?from=SGN');
  ok('thiếu tham số thì báo 400', e1.status === 400, e1.body);
} finally {
  proc.kill(); fake.close();
}
console.log('\n' + pass + ' đạt, ' + fail + ' hỏng');
process.exit(fail ? 1 : 0);
