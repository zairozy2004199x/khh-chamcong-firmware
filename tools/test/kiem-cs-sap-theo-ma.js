/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐỊA ĐIỂM: SẮP "THEO MÃ GHẾ" PHẢI SO THEO SỐ, KHÔNG SO THEO CHỮ
 *
 * Anh Thắng 23/09/2026: *"cho thêm sắp xếp theo mã ghế"*.
 *
 * 🔴 CHỖ DỄ SAI: mã ghế là CHUỖI ("80013", "9999", "VC-GP-6"). So chuỗi thô thì "80013" < "9999" và
 *    "VC-GP-12" < "VC-GP-6" — thứ tự trông "gần đúng" nên không ai để ý, cho tới lúc dò một mã mà
 *    lướt mãi không thấy. Bài này chạy thật hàm sắp trên DOM giả.
 *
 * Chạy: node tools/test/kiem-cs-sap-theo-ma.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const NGUON = 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(NGUON)) { console.error('✗ Không thấy ' + NGUON); process.exit(2); }
const src = fs.readFileSync(NGUON, 'utf8');
let hong = 0;
function t(ten, ok, them) { console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them))); if (!ok) hong++; }
function than(ten) { const i = src.indexOf('function ' + ten + '('); if (i < 0) return ''; let d = 0; for (let k = src.indexOf('{', i); k < src.length; k++) { if (src[k] === '{') d++; else if (src[k] === '}' && --d === 0) return src.slice(i, k + 1); } return ''; }

/* DOM giả tối giản: hàng có cells / children / querySelector / getAttribute; bảng có tBodies + appendChild. */
function hang(ten, csma, soGhe, none) {
  const a = { getAttribute: (k) => k === 'data-csxem' ? (none ? '__none__' : ten) : null };
  const tr = {
    cells: [{ tagName: 'TD' }], children: [{}, { textContent: String(soGhe || 0) }],
    querySelector: (q) => q === '[data-csxem]' ? a : null,
    getAttribute: (k) => k === 'data-cssapma' ? (csma || null) : null, ten,
  };
  return tr;
}
function bang(rows) { const th = { rows: rows.slice(), appendChild(tr) { this.rows = this.rows.filter(x => x !== tr); this.rows.push(tr); } }; return { tBodies: [th] }; }
const f = new Function('kdJS', than('csSapKhoa_') + '\n' + than('csSapMot_') + '\nreturn { csSapKhoa_, csSapMot_ };')((s) => String(s).toLowerCase());
t('bốc được csSapKhoa_ + csSapMot_', !!f.csSapKhoa_ && !!f.csSapMot_);

console.log('── Ô chọn có kiểu mới ───────────────────────────────────────');
t('có option value="ma" trong #cs-sap', /<option value="ma">/.test(src));
t('hàng cơ sở mang data-cssapma (mã nhỏ nhất)', /data-cssapma="' \+ esc\(_csma\)/.test(src) && /function csMaNhoNhat_/.test(src));
/* 🔴 24/09/2026: 2.133.0 lỡ đặt tên `data-csma` — trùng ô "Đổi cơ sở" của ghế; gõ Mã KH trong hàng
   là bắn lệnh dời ghế. Hai phép dưới giữ cho việc ấy không quay lại. */
t('🔴 hàng cơ sở KHÔNG mang data-csma (tên của ô Đổi cơ sở ghế)', !/<tr data-cstim="[^>]*data-csma="/.test(src) && !/data-csma="' \+ esc\(_csma\)/.test(src));
t('🔴 hai chỗ gán lệnh dời ghế chỉ bắt <select data-csma>, không bắt mọi phần tử', (src.match(/querySelectorAll\('select\[data-csma\]'\)/g) || []).length === 2 && !/querySelectorAll\('\[data-csma\]'\)/.test(src));
t('csMaNhoNhat_ dùng CÙNG bộ so numeric với dsMaHtml_', (src.match(/localeCompare\(String\(b\.ma\), undefined, \{numeric:true\}\)/g) || []).length >= 2);

console.log('── Chạy thật phép sắp ───────────────────────────────────────');
const rows = [hang('VẠN HẠNH', '80200', 5), hang('ZZZ TRỐNG', '', 0), hang('(chưa gán)', '', 3, true), hang('AEON TP', '80013', 2), hang('CŨ', '9999', 1), hang('GP', 'VC-GP-12', 1), hang('GP2', 'VC-GP-6', 1)];
const b = bang(rows); f.csSapMot_(b, 'ma');
const thu = b.tBodies[0].rows.map(r => r.ten);
t('🔴 so theo SỐ: 9999 trước 80013 trước 80200', ['CŨ', 'AEON TP', 'VẠN HẠNH'].every((n, i) => thu.indexOf(n) === i), thu);
t('🔴 mã có chữ: VC-GP-6 trước VC-GP-12 (không so chữ thô)', thu.indexOf('GP2') < thu.indexOf('GP'), thu);
t('cơ sở không có ghế dồn cuối, "(chưa gán)" cuối cùng', thu[thu.length - 2] === 'ZZZ TRỐNG' && thu[thu.length - 1] === '(chưa gán)', thu);
t('khối "chỉ còn ghế ẩn" (2.132.0) cũng được sắp', /csSapMot_\(document\.getElementById\('cs-bang-an'\), kieu\)/.test(src));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
