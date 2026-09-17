/* Kiểm thử hộp "Tài khoản của bạn": xem hồ sơ nhân sự của chính mình và nút đổi mật khẩu.

   Khung build/ vốn chạy ngoài WordPress nên không có window.KH_API — mà đúng hai việc này
   chỉ có khi chạy trong WordPress. Nên ở đây khai KH_API giả TRƯỚC khi trang nạp, đúng hình
   dạng mà khh_api_config() trả về. */
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url'; import { dirname } from 'node:path';
const SP = dirname(fileURLToPath(import.meta.url));
const EXE = '/opt/pw-browsers/chromium_headless_shell-1194/chrome-linux/headless_shell';

let fail = 0;
const t = (n, g, w) => {
  const ok = JSON.stringify(g) === JSON.stringify(w);
  if (!ok) fail++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${n.padEnd(54)} got=${JSON.stringify(g)}${ok ? '' : ' want=' + JSON.stringify(w)}`);
};

const b = await chromium.launch({ executablePath: EXE });
const ctx = await b.newContext({ viewport: { width: 1500, height: 950 } });
await ctx.addInitScript(() => {
  window.KH_API = {
    rest: 'https://khong-co-that.test/wp-json/khh/v1/', media: '', nonce: 'nonce-gia',
    me: 'Quang Thắng', title: 'Quản lý dự án', role: 'admin', login: 'quangthang',
    staffId: 's1', tuXem: 1, xemLuong: 0, tuDoiMk: 1, mkMin: 8,
    out: 'https://khong-co-that.test/?khh_thoat=1',
  };
});
const p = await ctx.newPage();
/* Mọi lệnh gọi máy chủ đều hỏng (không có WordPress thật) — chặn sẵn cho nhanh và gọn. */
await p.route('**/khong-co-that.test/**', r => r.abort());

await p.goto('file://' + SP + '/build/index.html');
await p.waitForTimeout(1300);

await p.evaluate(() => {
  const A = window.APP;
  A.col('staff').slice().forEach(x => A.remove('staff', x.id));
  A.save('staff', {
    id: 's1', name: 'Quang Thắng', code: 'MNV-01', title: 'Quản lý dự án',
    dept: 'Phòng kỹ thuật', unit: 'Posh', office: 'Văn phòng HCM', manager: 'Giám Đốc',
    start: '2021-03-01', official: '2021-06-01', contract: 'HĐ lao động 12 tháng',
    worktime: 'Full-time', leaveLeft: 9, email: 'thang@khh.vn', phone: '0909000111',
    salary: 18000000, allowance: 2000000, status: 'active', role: 'admin',
    dob: '1994-07-02', bhxh: '0123456789', bank: 'Vietcombank',
  });
});
await p.waitForTimeout(300);

const moHop = async () => {
  await p.evaluate(() => { document.querySelector('#meBtn').click(); });
  await p.waitForTimeout(300);
};
const dongHop = async () => {
  await p.evaluate(() => {
    const d = document.querySelector('dialog[open]');
    if (d) d.querySelector('[data-x="cancel"]').click();
  });
  await p.waitForTimeout(250);
};
const chu = () => p.evaluate(() => {
  const d = document.querySelector('dialog[open]');
  return d ? d.textContent.replace(/\s+/g, ' ') : '';
});
const nut = () => p.evaluate(() => Array.from(
  document.querySelectorAll('dialog[open] .dlg-f button')).map(x => x.textContent.trim()));

/* 1. Bật xem hồ sơ: hiện đúng các dòng của chính mình */
await moHop();
let c = await chu();
t('có khối hồ sơ nhân sự', c.includes('Hồ sơ nhân sự của bạn'), true);
t('hiện mã nhân sự', c.includes('MNV-01'), true);
t('hiện bộ phận', c.includes('Phòng kỹ thuật'), true);
t('hiện mảng kinh doanh', c.includes('Posh'), true);
t('hiện quản lý trực tiếp', c.includes('Giám Đốc'), true);
t('ngày vào làm hiện dạng Việt', c.includes('01/03/2021'), true);
t('phép còn lại kèm đơn vị', c.includes('9 ngày'), true);
t('hiện tình trạng', c.includes('Đang làm việc'), true);
t('hiện tên đăng nhập', c.includes('quangthang'), true);

/* 2. Mặc định KHÔNG bày lương, và không bày mấy ô riêng tư không cần thiết */
t('không hiện lương khi cấu hình tắt', /18\.000\.000/.test(c), false);
t('không bày ngày sinh', c.includes('02/07/1994'), false);
t('không bày số sổ BHXH', c.includes('0123456789'), false);

/* 3. Nút trong hộp */
t('có nút đổi mật khẩu', (await nut()).includes('Đổi mật khẩu'), true);
t('có nút thoát', (await nut()).includes('Thoát'), true);
await dongHop();

/* 4. Bật hiện lương */
await p.evaluate(() => { window.KH_API.xemLuong = 1; });
await moHop();
c = await chu();
t('bật cấu hình thì hiện lương', /18\.000\.000/.test(c), true);
t('bật cấu hình thì hiện phụ cấp', /2\.000\.000/.test(c), true);
await dongHop();

/* 5. Tắt xem hồ sơ */
await p.evaluate(() => { window.KH_API.tuXem = 0; });
await moHop();
c = await chu();
t('tắt cấu hình thì không còn khối hồ sơ', c.includes('Hồ sơ nhân sự của bạn'), false);
t('tắt cấu hình thì không lộ mã nhân sự', c.includes('MNV-01'), false);
t('vẫn còn tên và vai trò', c.includes('Quang Thắng'), true);
await dongHop();

/* 6. Tắt tự đổi mật khẩu thì giấu nút — và vào bằng mã PIN (không có tên đăng nhập) cũng vậy */
await p.evaluate(() => { window.KH_API.tuXem = 1; window.KH_API.tuDoiMk = 0; });
await moHop();
t('tắt cấu hình thì giấu nút đổi mật khẩu', (await nut()).includes('Đổi mật khẩu'), false);
await dongHop();
await p.evaluate(() => { window.KH_API.tuDoiMk = 1; window.KH_API.login = ''; });
await moHop();
t('vào bằng mã PIN thì giấu nút đổi mật khẩu', (await nut()).includes('Đổi mật khẩu'), false);
await dongHop();
await p.evaluate(() => { window.KH_API.login = 'quangthang'; });

/* 7. Hộp đổi mật khẩu */
await moHop();
await p.evaluate(() => {
  Array.from(document.querySelectorAll('dialog[open] .dlg-f button'))
    .find(x => x.textContent.trim() === 'Đổi mật khẩu').click();
});
await p.waitForTimeout(400);
t('mở được hộp đổi mật khẩu',
  await p.evaluate(() => document.querySelector('dialog[open] h3').textContent.trim()), 'Đổi mật khẩu');
t('đủ ba ô mật khẩu',
  await p.evaluate(() => document.querySelectorAll('dialog[open] input[type="password"]').length), 3);
t('nhắc độ dài tối thiểu lấy từ cấu hình',
  (await chu()).includes('Tối thiểu 8 ký tự'), true);

/* 🔴 HAI Ô CẠNH NHAU PHẢI THẲNG HÀNG. Ô "Mật khẩu mới" có dòng nhắc, ô "Nhập lại" thì không —
   `.two` kéo hai ô cao bằng nhau, và nếu `.fld` không ghim `align-content:start` thì ô không có
   dòng nhắc tự giãn hai hàng còn lại: nhãn đứng yên, ô nhập tụt xuống 10px và cao thêm 10px.
   Đo thẳng toạ độ chứ không nhìn bằng mắt — lệch mười pixel là thứ chỉ thấy khi có người chụp
   màn hình gửi lại, đúng như lần 17/09/2026. */
const doO = await p.evaluate(() =>
  Array.from(document.querySelectorAll('dialog[open] .two .fld')).map(f => {
    const i = f.querySelector('input').getBoundingClientRect();
    return { tren: Math.round(i.top), cao: Math.round(i.height) };
  }));
t('hai ô mật khẩu mới thẳng hàng', doO.length === 2 && doO[0].tren === doO[1].tren, true);
t('và cao bằng nhau', doO.length === 2 && doO[0].cao === doO[1].cao, true);

/* Hai ô mật khẩu mới khác nhau → báo lỗi, và hộp KHÔNG được đóng mất công gõ lại */
await p.evaluate(() => {
  const o = document.querySelectorAll('dialog[open] input[type="password"]');
  o[0].value = 'matkhaucu1'; o[1].value = 'matkhaumoi1'; o[2].value = 'matkhaumoi2';
  document.querySelector('dialog[open] [data-x="save"]').click();
});
await p.waitForTimeout(300);
t('báo hai ô mới chưa giống nhau',
  await p.evaluate(() => (document.querySelector('.toast') || {}).textContent || ''),
  'Hai ô mật khẩu mới chưa giống nhau.');
t('hộp vẫn mở', await p.evaluate(() => !!document.querySelector('dialog[open]')), true);

/* Mật khẩu mới quá ngắn */
await p.evaluate(() => {
  const o = document.querySelectorAll('dialog[open] input[type="password"]');
  o[0].value = 'matkhaucu1'; o[1].value = 'ngan'; o[2].value = 'ngan';
  document.querySelector('dialog[open] [data-x="save"]').click();
});
await p.waitForTimeout(300);
t('báo mật khẩu mới quá ngắn',
  await p.evaluate(() => (document.querySelector('.toast') || {}).textContent || ''),
  'Mật khẩu mới phải từ 8 ký tự trở lên.');

/* Máy chủ không gọi được → báo mất kết nối, không im lặng coi như xong */
await p.evaluate(() => {
  const o = document.querySelectorAll('dialog[open] input[type="password"]');
  o[0].value = 'matkhaucu1'; o[1].value = 'matkhaumoi1'; o[2].value = 'matkhaumoi1';
  document.querySelector('dialog[open] [data-x="save"]').click();
});
await p.waitForTimeout(800);
t('gọi máy chủ hỏng thì báo rõ',
  await p.evaluate(() => (document.querySelector('.toast') || {}).textContent || ''),
  'Mất kết nối máy chủ — chưa đổi được mật khẩu.');
t('hỏng thì vẫn giữ hộp cho gõ lại',
  await p.evaluate(() => !!document.querySelector('dialog[open]')), true);

await b.close();
console.log(fail ? `\n${fail} phép thử KHÔNG đạt` : '\nTất cả phép thử đều đạt');
process.exit(fail ? 1 : 0);
