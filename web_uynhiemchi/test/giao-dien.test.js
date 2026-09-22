/*
 * Kiểm thử BỘ ÁO: chạy `node test/giao-dien.test.js`, không cần trình duyệt.
 *
 * Không có bài nào ở đây nói "trang đẹp hay xấu" — cái đó chỉ người nhìn mới trả lời được.
 * Chúng chỉ giữ lấy mấy lời hứa mà CSS tự viết ra trong chú thích của nó, vì đó đúng là loại
 * lời hứa sớm muộn cũng có người vô tình bẻ:
 *
 *   1. Bảng màu tối phải khai ĐÚNG HAI LẦN, giống hệt nhau. CSS thuần không chia sẻ được một
 *      khối qua ranh giới `@media`, nên chỗ ấy buộc phải chép đôi. Ai sửa một bên rồi quên bên
 *      kia thì nút "chuyển sang tối" và "hệ điều hành đang tối" cho ra hai bảng màu khác nhau —
 *      một lỗi mà người sửa gần như chắc chắn không gặp trên máy mình.
 *   2. Mọi tên màu có ở mặt sáng thì phải có ở mặt tối. Thiếu một tên là chỗ ấy rơi về giá trị
 *      mặt sáng: một mảng trắng loé giữa nền tối.
 *   3. Cỡ chữ lấy từ thang, không gõ tay. Bản cũ có 11 cỡ chữ rải từ 11 tới 20px, kể cả mấy cỡ
 *      lệch nhau nửa điểm — mắt không đọc ra thứ bậc từ một thang như thế.
 */
const assert = require('assert');
const fs = require('fs');
const path = require('path');

const CSS = fs.readFileSync(path.join(__dirname, '..', 'style.css'), 'utf8');
/* Bỏ chú thích trước khi soi, để chữ trong chú thích không bị tính là khai báo. */
const THAN = CSS.replace(/\/\*[\s\S]*?\*\//g, '');

let soTest = 0;
function test(ten, fn) {
  soTest++;
  try {
    fn();
    console.log('  ✓ ' + ten);
  } catch (e) {
    console.error('  ✗ ' + ten + '\n    ' + e.message);
    process.exitCode = 1;
  }
}
function nhom(ten) { console.log('\n' + ten); }

/* Cắt lấy phần trong dấu { } bắt đầu từ vị trí `tu`, đếm ngoặc để lồng nhau vẫn đúng. */
function ruot(chuoi, tu) {
  const mo = chuoi.indexOf('{', tu);
  assert.ok(mo >= 0, 'không thấy dấu { sau vị trí ' + tu);
  let sau = 1;
  for (let i = mo + 1; i < chuoi.length; i++) {
    if (chuoi[i] === '{') sau++;
    else if (chuoi[i] === '}' && --sau === 0) return chuoi.slice(mo + 1, i);
  }
  throw new Error('thiếu dấu } đóng lại');
}

/* Đọc các dòng `--ten: giá trị;` thành một bảng tra. */
function docBien(khoi) {
  const ra = {};
  const re = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m;
  while ((m = re.exec(khoi))) ra[m[1]] = m[2].trim().replace(/\s+/g, ' ');
  return ra;
}

const viTriMedia = THAN.indexOf('@media (prefers-color-scheme: dark)');
const viTriDau = THAN.indexOf('[data-theme="dark"]');

nhom('Hai khối màu tối phải khớp nhau');

test('CSS khai bảng màu tối ở cả hai chỗ', () => {
  assert.ok(viTriMedia >= 0, 'thiếu @media (prefers-color-scheme: dark)');
  assert.ok(viTriDau >= 0, 'thiếu [data-theme="dark"]');
});

/* Khối @media bọc ngoài một khối :root nữa, nên phải bóc hai lần. */
const toiHeDieuHanh = docBien(ruot(ruot(THAN, viTriMedia), 0));
const toiBamNut = docBien(ruot(THAN, viTriDau));

test('khối "theo hệ điều hành" chặn được lựa chọn sáng tường minh', () => {
  const dau = ruot(THAN, viTriMedia);
  assert.ok(
    /:root:not\(\[data-theme="light"\]\)/.test(dau),
    'thiếu :not([data-theme="light"]) — người chọn "sáng" trên máy đang để tối sẽ vẫn ra tối'
  );
});

test('hai khối khai đúng cùng một bộ tên', () => {
  const a = Object.keys(toiHeDieuHanh).sort();
  const b = Object.keys(toiBamNut).sort();
  const thieuBenNut = a.filter((t) => b.indexOf(t) < 0);
  const thieuBenHe = b.filter((t) => a.indexOf(t) < 0);
  assert.deepStrictEqual(
    { thieuBenNut: thieuBenNut, thieuBenHe: thieuBenHe },
    { thieuBenNut: [], thieuBenHe: [] },
    'một bên có tên mà bên kia không'
  );
});

test('và khai đúng cùng một giá trị cho từng tên', () => {
  const lech = Object.keys(toiHeDieuHanh)
    .filter((t) => toiBamNut[t] !== toiHeDieuHanh[t])
    .map((t) => t + ': ' + toiHeDieuHanh[t] + ' ≠ ' + toiBamNut[t]);
  assert.deepStrictEqual(lech, [], 'giá trị lệch giữa hai khối');
});

test('🔴 và khối tối có đủ mọi tên màu của mặt sáng', () => {
  const sang = docBien(ruot(THAN, THAN.indexOf(':root')));
  /* Thang giãn cách, bo góc, cỡ chữ, phông và bóng đổ không đổi theo mặt sáng/tối —
     chỉ MÀU mới phải khai lại. */
  const khongPhaiMau = /^--(d\d|bo-|c\d|radius|font|mono|bong-|shadow)/;
  const thieu = Object.keys(sang).filter(
    (t) => !khongPhaiMau.test(t) && toiBamNut[t] === undefined
  );
  assert.deepStrictEqual(thieu, [], 'tên màu không có bản tối — chỗ ấy sẽ loé sáng trên nền tối');
});

nhom('Thang chữ và thang giãn cách');

test('mọi cỡ chữ trên màn hình đều lấy từ thang --c1..--c7', () => {
  /* `pt` là đơn vị của tờ giấy A4 trong khối in, không phải của màn hình — thang --cN tính
     bằng px nên không áp được vào đó. */
  const tho = (THAN.match(/font-size:\s*[^;]+;/g) || []).filter(
    (d) => !/var\(--c\d\)/.test(d) && !/\d+(\.\d+)?pt/.test(d)
  );
  assert.deepStrictEqual(tho, [], 'cỡ chữ gõ thẳng — hãy thêm vào thang rồi dùng var(--cN)');
});

test('thang chữ đúng 7 cỡ, không có cỡ nửa điểm', () => {
  const sang = docBien(ruot(THAN, THAN.indexOf(':root')));
  const co = Object.keys(sang).filter((t) => /^--c\d+$/.test(t));
  assert.strictEqual(co.length, 7, 'thang chữ phải là 7 cỡ, đang là ' + co.length);
  co.forEach((t) => {
    assert.ok(/^\d+px$/.test(sang[t]), t + ' = "' + sang[t] + '" — cỡ chữ phải là số nguyên px');
  });
});

test('thang giãn cách đi đúng bước 4px', () => {
  const sang = docBien(ruot(THAN, THAN.indexOf(':root')));
  ['--d1', '--d2', '--d3', '--d4', '--d5', '--d6'].forEach((t) => {
    assert.ok(sang[t] !== undefined, 'thiếu ' + t);
    const px = parseInt(sang[t], 10);
    assert.strictEqual(px % 4, 0, t + ' = ' + sang[t] + ' — không nằm trên bước 4px');
  });
});

nhom('Mấy chỗ đã từng hỏng');

test('🔴 ô tích trong bộ lọc không ăn khuôn của ô nhập', () => {
  /* Thiếu :not([type="checkbox"]) thì ô tích "Chỉ quá hạn" mọc thành một khung rỗng rộng
     120px nằm cạnh chữ — trông như trang vỡ. */
  const luat = THAN.match(/\.filters input[^{]*,\s*\.filters select\s*\{/);
  assert.ok(luat, 'không thấy luật khuôn ô nhập của bộ lọc');
  assert.ok(
    /:not\(\[type="checkbox"\]\)/.test(luat[0]),
    'luật khuôn ô nhập đang quét cả ô tích: ' + luat[0]
  );
});

test('🔴 màu không bao giờ là thứ duy nhất nói ra trạng thái', () => {
  /* Nhãn trạng thái luôn có CHỮ bên trong (xem app.js), nên ở đây chỉ cần giữ cho mỗi biến
     thể vừa đổi nền vừa đổi màu chữ — nền đơn thuần thì người mù màu không phân biệt nổi
     "đã đi" với "quá hạn". */
  ['ok', 'wait', 'info', 'muted', 'late'].forEach((bt) => {
    const luat = THAN.match(new RegExp('\\.tag\\.' + bt + '\\s*\\{([^}]*)\\}'));
    assert.ok(luat, 'thiếu .tag.' + bt);
    assert.ok(/background:/.test(luat[1]), '.tag.' + bt + ' thiếu nền');
    assert.ok(/color:/.test(luat[1]), '.tag.' + bt + ' thiếu màu chữ');
  });
});

test('🔴 ô nhập trên điện thoại đủ 16px để iOS không tự phóng to trang', () => {
  const dt = THAN.slice(THAN.indexOf('@media (max-width: 720px)'));
  assert.ok(dt.length > 0, 'thiếu khối màn hình hẹp');
  assert.ok(
    /font-size:\s*var\(--c5\)/.test(dt),
    'khối điện thoại không ghim cỡ chữ ô nhập về --c5 (16px)'
  );
});

console.log('\n' + soTest + ' bài kiểm thử.' + (process.exitCode ? ' CÓ LỖI.' : ' Tất cả đạt.'));
