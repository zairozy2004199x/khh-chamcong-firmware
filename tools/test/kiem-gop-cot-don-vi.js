/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỘT CỘT ĐƠN VỊ, MỘT LUẬT — VÀ MỖI VAI TRÒ MỘT BẢNG.
 *
 * Anh Thắng 12/09/2026, ba câu liền nhau:
 *   · *"Bỏ cột tài khoản có."*
 *   · *"Sắp xếp bảng nhân sự theo vai trò, mỗi vai trò 1 bảng"*
 *   · *"Đơn vị với xem đơn vị là 1, đã thuộc đơn vị đó, thì toàn quyền xem của mình. Đã phân
 *      quyền xem và cấp quyền nên không sợ bị ngoài luồng thông tin"*
 *
 * 🔴 BÀI NÀY THAY `kiem-canh-bao-nha-lech.js`. Bài ấy canh `_dvLech()`/`_dvCanhBao()` — hai hàm
 *    báo khi cột "Đơn vị" và cột "Xem đơn vị" khai lệch nhau (cắn thật 09/09/2026, tài khoản
 *    anh Trần Ngọc Quyền). Nay chỉ còn MỘT cột nên không có gì lệch được nữa, và hai hàm ấy đã
 *    bỏ. Cái cần canh đổi hẳn: rằng hai cột kia thật sự BIẾN MẤT khỏi màn, và cái thay thế
 *    (nhiều bảng theo vai) không đánh rơi ai.
 *
 * 🔴 CHỖ NGUY NHẤT LÀ `saveCfgUsers()` GOM TỪ MÀN. Lượt Lưu ghi đè cả danh sách người dùng, nên
 *    một người không nằm trong gói gửi lên là bị XOÁ khỏi sổ — im lặng, không câu lỗi. Vai lạ
 *    (vai tự tạo, vai gõ sai) mà không có bảng riêng thì rơi đúng vào đó. Phần 3 canh việc ấy.
 *
 * ⚠️ CHẠY THẬT `_uNhom()` bốc từ app.html, không chép lại logic.
 *
 * Chạy: node tools/test/kiem-gop-cot-don-vi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const PHP  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-donvi.php'), 'utf8');
const MISA = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-misa.php'), 'utf8');

/* 🔴 GỠ CHÚ THÍCH TRƯỚC KHI QUÉT. Khối chú thích của `xem_duoc()` GIẢI THÍCH vì sao bỏ ô "Xem
   đơn vị" và hằng `VAI_XEM_CA`, nên nó chứa đúng hai cái tên mà phép dưới đang tìm — quét cả
   chú thích là bài kiểm đỏ vì chính lời giải thích của bản vá. Đã cắn thật ngay lượt viết đầu.
   Phép này canh MÃ CHẠY, nên chỉ được nhìn mã chạy. */
const boChuThich = x => String(x).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' ');

/* ── Bệ đỡ ─────────────────────────────────────────────────────────────────────────────── */
function boc(ten) {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n  }', i);
  return HTML.slice(i, j + 4);
}
/* `U_VAI_XEP` và `U_NHAN` là hằng ngoài hàm — bốc riêng, đừng chép lại giá trị vào đây: chép
   là bài kiểm canh bản sao của chính mình, đổi thứ tự thật mà bài vẫn xanh. */
function bocVar(ten) {
  const i = HTML.indexOf('var ' + ten + '=');
  t('bốc được ' + ten, i >= 0);
  if (i < 0) { console.log('\n✗ Không bốc được — dừng.'); process.exit(1); }
  const j = HTML.indexOf('\n', HTML.indexOf('};', i) >= 0 && HTML.indexOf('};', i) < HTML.indexOf('];', i) + 1 ? HTML.indexOf('};', i) : HTML.indexOf('];', i));
  return HTML.slice(i, j + 1);
}
const src = bocVar('U_VAI_XEP') + bocVar('U_NHAN') + boc('_uNhan') + boc('_uNhom')
  + '; return { _uNhom: _uNhom, _uNhan: _uNhan, U_VAI_XEP: U_VAI_XEP };';
const F = new Function(src)();

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 1. HAI CỘT ĐÃ BỎ KHỎI MÀN
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const H = boChuThich(HTML);
t('🔴 không còn cột "TK Có" trên bảng người dùng', H.indexOf('TK Có (khi là người duyệt)') < 0);
t('🔴 không còn cột "Xem đơn vị" trên bảng người dùng', !/>Xem đơn vị</.test(H));
t('🔴 hàm dựng ô "Xem đơn vị" đã bỏ hẳn', H.indexOf('function _xemDvSel(') < 0);
t('🔴 hàm báo lệch nhà/tầm nhìn đã bỏ hẳn',
  H.indexOf('function _dvLech(') < 0 && H.indexOf('function _dvCanhBao(') < 0);
/* Cột "Đơn vị" thì PHẢI CÒN — nó là cột duy nhất còn lại, bỏ nốt là không ai khai được nhà. */
t('   nhưng cột "Đơn vị" vẫn còn', />Đơn vị</.test(H) && H.indexOf('_dvInp(') >= 0);

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 2. MÁY CHỦ CŨNG CHỈ CÒN MỘT NGUỒN
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const XEM = boChuThich((PHP.match(/public static function xem_duoc\(\)[\s\S]*?\n\t\}/) || [''])[0]);
t('bốc được xem_duoc()', XEM.length > 20);
t('🔴 xem_duoc() KHÔNG còn đọc ô "Xem đơn vị"', XEM.indexOf('xemDonVi') < 0, XEM);
t('🔴 xem_duoc() KHÔNG còn nới theo VAI',      XEM.indexOf('VAI_XEM_CA') < 0, XEM);
t('🔴 và hằng VAI_XEM_CA đã bỏ khỏi lớp',      !/const VAI_XEM_CA\s*=/.test(boChuThich(PHP)));
t('   nhưng vẫn theo NHÀ MẸ',                  /la_don_vi_me/.test(XEM), XEM);

/* `ai_khai_lac()` phải soi cột "Đơn vị", và KHÔNG được lấy `ds()` làm thước đo — `ds()` gom
   chính cột ấy vào, nên mọi tên gõ lạc tự hợp thức hoá mình. Đã cắn thật lượt viết đầu. */
const LAC = boChuThich((PHP.match(/public static function ai_khai_lac\(\)[\s\S]*?\n\t\}/) || [''])[0]);
t('bốc được ai_khai_lac()', LAC.length > 20);
t('🔴 ai_khai_lac() soi cột "Đơn vị"', /\['donVi'\]/.test(LAC), LAC);
t('🔴 và KHÔNG lấy ds() làm thước đo (ds() gom chính cột ấy vào → không gì lạc được)',
  !/self::ds\(\)/.test(LAC), LAC);
t('   mà đo bằng nơi khai thật: danh mục cơ sở', /cosoDonVi/.test(LAC), LAC);

/* TK Có: không còn tra theo người duyệt nữa. */
t('🔴 MISA không còn bảng tra "TK Có theo người duyệt"', boChuThich(MISA).indexOf('m_co_user') < 0);
t('   và câu báo thiếu TK Có chỉ sang đúng chỗ còn khai được',
  /Thiếu TK Có cho hình thức chi/.test(MISA));

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * 3. MỖI VAI MỘT BẢNG — VÀ KHÔNG ĐÁNH RƠI AI
 * ══════════════════════════════════════════════════════════════════════════════════════════ */
const DS = [
  { ten: 'Admin',  vaiTro: 'Admin' },
  { ten: 'Bin',    vaiTro: 'Nhân viên' },
  { ten: 'Hòa',    vaiTro: 'Quản lý' },
  { ten: 'Nhân',   vaiTro: 'Kế toán cá nhân' },
  { ten: 'Phượng', vaiTro: 'Kế toán NCC' },
  { ten: 'Thịnh',  vaiTro: 'Nhân viên' },
];
const nh = F._uNhom(DS);
teq('🔴 xếp theo thứ bậc, không theo thứ tự gặp',
  ['Admin', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'], nh.map(g => g.vai));
teq('   gom đúng người vào bảng Nhân viên', ['Bin', 'Thịnh'],
  (nh.find(g => g.vai === 'Nhân viên') || { ds: [] }).ds.map(u => u.ten));
teq('🔴 KHÔNG mất ai — tổng người của mọi bảng bằng đúng danh sách vào',
  DS.length, nh.reduce((a, g) => a + g.ds.length, 0));

/* Vai gốc không có ai thì bỏ hẳn bảng, khỏi bày khung rỗng. */
const nh2 = F._uNhom([{ ten: 'A', vaiTro: 'Nhân viên' }]);
teq('vai gốc không có ai thì không dựng bảng rỗng', ['Nhân viên'], nh2.map(g => g.vai));

/* 🔴 VAI LẠ PHẢI CÓ BẢNG RIÊNG. Đây là chỗ nguy nhất: bảng người dùng lưu bằng cách GOM TỪ
   MÀN, nên ai không được vẽ ra là lượt Lưu kế tiếp xoá luôn khỏi sổ. */
const nh3 = F._uNhom([
  { ten: 'A', vaiTro: 'Nhân viên' },
  { ten: 'B', vaiTro: 'Nhân viên cơ sở' },     // vai tự tạo
  { ten: 'C', vaiTro: 'Kế toán máy tự động' }, // vai tự tạo
  { ten: 'D', vaiTro: 'Quản lí' },             // gõ sai dấu — vẫn phải hiện
]);
teq('🔴 vai tự tạo và vai gõ sai đều có bảng riêng, xếp sau vai gốc',
  ['Nhân viên', 'Nhân viên cơ sở', 'Kế toán máy tự động', 'Quản lí'], nh3.map(g => g.vai));
teq('   và không ai bị đánh rơi', 4, nh3.reduce((a, g) => a + g.ds.length, 0));

/* Ô vai trò để TRỐNG = Nhân viên, đúng như máy chủ hiểu (`VHCP_Cfg` gieo 'Nhân viên' khi rỗng).
   Hiểu khác đi là hàng ấy sinh ra một bảng tên "" — trông như bảng hỏng. */
const nh4 = F._uNhom([{ ten: 'A', vaiTro: '' }, { ten: 'B' }]);
teq('🔴 ô Vai trò để trống rơi về Nhân viên, không đẻ ra bảng tên rỗng', ['Nhân viên'], nh4.map(g => g.vai));
teq('   cả hai người vào đúng bảng ấy', 2, nh4[0].ds.length);

teq('danh sách rỗng thì không bảng nào', [], F._uNhom([]).map(g => g.vai));
teq('gọi với null cũng không nổ', [], F._uNhom(null).map(g => g.vai));

/* Nhãn: vai gốc có tên đẹp, vai lạ vẫn phải đọc được chứ không thành "undefined". */
teq('nhãn vai gốc', '👤 Nhân viên', F._uNhan('Nhân viên'));
t('🔴 nhãn vai lạ vẫn hiện đúng tên, không thành undefined',
  F._uNhan('Nhân viên cơ sở').indexOf('Nhân viên cơ sở') >= 0, F._uNhan('Nhân viên cơ sở'));

/* ─────────────────────────────────────────────────────────────────────────────────────── */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — một cột đơn vị, mỗi vai một bảng, không đánh rơi ai');
