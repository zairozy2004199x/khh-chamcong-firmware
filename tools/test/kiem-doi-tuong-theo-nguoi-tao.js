/* Ô "Đối tượng — NV thanh toán" điền sẵn NGƯỜI TẠO ĐƠN, không phải người đang gõ.
 * Anh Thắng 25/09/2026: "Lấy tên theo người tạo, 1 cơ sở 2 bạn quản lý, cứ ai tạo thì hiện tên người đó là được".
 * Chạy: node tools/test/kiem-doi-tuong-theo-nguoi-tao.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
t('⚠️ bốc được fillDoiTuongList', ham('fillDoiTuongList').length > 40);
function chay(pltt, cur, user, sanCo, vai) {
  const f = { value: sanCo || '' }, dl = { innerHTML: '' };
  const O = { f_dt: f, dl_dt: dl, f_pltt: { value: pltt } };
  new Function('el', 'CUR', 'CURUSER', 'BOOT', '_vaiLuat', 'esc', ham('fillDoiTuongList') + '\nfillDoiTuongList(' + JSON.stringify(pltt) + ');')(
    (id) => O[id] || null, cur, user, { doiTuong: [{ ten: 'Thu Thảo', loai: 'NV' }, { ten: 'NCC Y', loai: 'NCC' }] }, () => vai || 'Nhân viên', (s) => String(s));
  return f.value;
}
const DON_THAO = { don: { maDon: 'D1', nguoiLap: 'Huỳnh Thị Thu Thảo' } };
const TRI = { name: 'NGUYỄN CÔNG TRÍ', role: 'Nhân viên' };
teq('🔴 Trí thêm dòng vào đơn của Thảo → đối tượng mặc định là Thảo (người tạo đơn)', 'Huỳnh Thị Thu Thảo', chay('Thanh toán cá nhân', DON_THAO, TRI));
teq('   chưa mở đơn nào → lui về người đang gõ như cũ', 'NGUYỄN CÔNG TRÍ', chay('Thanh toán cá nhân', null, TRI));
teq('   đơn không có người lập → lui về người đang gõ', 'NGUYỄN CÔNG TRÍ', chay('Thanh toán cá nhân', { don: { maDon: 'D2', nguoiLap: '' } }, TRI));
teq('   ô đã có chữ → không ghi đè', 'Ai Đó', chay('Thanh toán cá nhân', DON_THAO, TRI, 'Ai Đó'));
teq('   lối Nhà cung cấp → không điền tên người vào ô NCC', '', chay('Nhà cung cấp', DON_THAO, TRI));
teq('   Quản lý mở đơn của Thảo cũng ra Thảo', 'Huỳnh Thị Thu Thảo', chay('Thanh toán cá nhân', DON_THAO, { name: 'Quản Lý B', role: 'Quản lý' }, '', 'Quản lý'));
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: đối tượng mặc định = người tạo đơn; không có đơn thì người đang gõ.');
