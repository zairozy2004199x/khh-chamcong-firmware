/**
 * Trang /hop-dong/ là HỆ THỐNG RIÊNG: template của nó phải đứng một mình được.
 *
 * Kiểm ba thứ dễ vỡ khi tách trang:
 *   1. Template hợp đồng không còn dựa vào biến/hàm của app chi phí (BOOT, showPage, CUR…),
 *      vì những thứ đó chỉ tồn tại trong app.html.
 *   2. Mọi lệnh gọi máy chủ trong trang hợp đồng đều nằm trong danh sách FNS đã khai ở
 *      class-vhcp-hdapp.php — gọi hàm ngoài danh sách là trang sẽ chết lặng.
 *   3. App chi phí không còn dấu vết của tab hợp đồng.
 *
 * Chạy: node tools/test/test-trang-hopdong.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const goc = path.join(__dirname, '..', '..', 'wordpress', 'vhcp-chi-phi');
const hd  = fs.readFileSync(path.join(goc, 'templates', 'hopdong.html'), 'utf8');
const app = fs.readFileSync(path.join(goc, 'templates', 'app.html'), 'utf8');
const php = fs.readFileSync(path.join(goc, 'includes', 'class-vhcp-hdapp.php'), 'utf8');

let dat = 0, truot = 0;
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot++;
  console.error('TRƯỢT: ' + ten + (them === undefined ? '' : '\n  → ' + them));
}
function teq(ten, mong, thuc) { t(ten + ' (mong ' + mong + ', thực ' + thuc + ')', mong === thuc); }

function js(html) {
  const p = html.split('<script>');
  return p[p.length - 1].split('</scr' + 'ipt>')[0];
}
function demDiv(html) {
  return [(html.match(/<div\b/g) || []).length, (html.match(/<\/div>/g) || []).length];
}

// ---------- 1. Trang hợp đồng đứng một mình ----------
const [mo, dong] = demDiv(hd);
teq('div của trang hợp đồng cân', mo, dong);
t('có chỗ chèn <head> của WordPress', hd.indexOf('<!--VHCP_HEAD-->') >= 0);
t('nạp CSS dùng chung qua VHCP_HEAD, không tự chép style', hd.indexOf('<style>') < 0);

const jsHd = js(hd);
try { new Function(jsHd); dat++; } catch (e) { truot++; console.error('TRƯỢT: JS trang hợp đồng lỗi cú pháp\n  → ' + e.message); }

[['BOOT', /\bBOOT\b/], ['showPage', /\bshowPage\s*\(/], ['CUR (đơn đang mở)', /\bCUR\b(?!USER)/],
 ['applyPerms', /\bapplyPerms\s*\(/], ['_log', /\b_log\s*\(/]].forEach(function (x) {
  t('không dùng ' + x[0] + ' của app chi phí', !x[1].test(jsHd));
});

// Hàm phụ mà trang tự dùng thì phải tự khai
['el', 'esc', 'money', '_v', '_dmy', 'toast', 'loading', '_tqCard'].forEach(function (f) {
  t('tự khai hàm ' + f, jsHd.indexOf('function ' + f + '(') >= 0);
});

// ---------- 2. Chỉ gọi những hàm đã khai trong FNS ----------
const khoiFns = php.slice(php.indexOf('const FNS'), php.indexOf(');', php.indexOf('const FNS')));
const fns = (khoiFns.match(/'([A-Za-z]+)'/g) || []).map(function (s) { return s.replace(/'/g, ''); });
t('đọc được danh sách FNS từ class-vhcp-hdapp.php', fns.length >= 5, fns.join(','));

// Lấy tên hàm nằm ngay sau chuỗi handler: ").tenHam(" hoặc "google.script.run.tenHam("
const goiRa = {};
let m;
const re = /(?:google\.script\.run|withSuccessHandler\([\s\S]{0,4000}?\)|withFailureHandler\([\s\S]{0,4000}?\))\s*\.\s*([a-zA-Z][a-zA-Z0-9]*)\s*\(/g;
while ((m = re.exec(jsHd)) !== null) {
  if (m[1] === 'withSuccessHandler' || m[1] === 'withFailureHandler') { continue; }
  goiRa[m[1]] = 1;
}
const teGoi = Object.keys(goiRa);
t('tìm thấy lệnh gọi máy chủ trong trang', teGoi.length >= 4, teGoi.join(','));
teGoi.forEach(function (f) {
  t('hàm ' + f + ' nằm trong FNS đã khai', fns.indexOf(f) >= 0, 'FNS: ' + fns.join(', '));
});

// ---------- 3. App chi phí sạch dấu vết tab hợp đồng ----------
t('app.html không còn chữ hopdong', !/hopdong/i.test(app));
t('app.html không còn hàm HopDong', app.indexOf('HopDong') < 0);
const [mo2, dong2] = demDiv(app);
teq('div của app chi phí cân', mo2, dong2);
try { new Function(js(app)); dat++; } catch (e) { truot++; console.error('TRƯỢT: JS app chi phí lỗi cú pháp\n  → ' + e.message); }

if (truot) { console.error('\nTRƯỢT ' + truot + ' / ĐẠT ' + dat); process.exit(1); }
console.log('ĐẠT: ' + dat + ' phép thử — trang hợp đồng chạy riêng được.');
