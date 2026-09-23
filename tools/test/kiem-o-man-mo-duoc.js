/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô MỞ MÀN TRONG TRẠM PHẢI ĐƯỢC COI LÀ Ô MỞ.
 *
 * Anh Thắng 18/09/2026, ảnh chụp tab Ứng dụng của nhân viên Nguyễn Thị Lắm: cả nhóm **CỦA TÔI**
 * (Phiếu lương · Xin bù giờ · Khai giờ khác · Xin nghỉ · Gửi đơn đi trễ) xám hết, kèm dòng
 * *"chưa được cấp"*. Anh: *"Chỗ phần của tôi. Nhân viên được cấp sử dụng"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO LỌT QUA CẢ MỘT BÀI KIỂM ĐANG XANH
 * =============================================================================================
 * Máy chủ dựng cả năm ô ấy bằng `VHCC_Ung::o( true, … )` — KHÔNG gác gì. `kiem-luoi-ung.php`
 * kiểm đúng chỗ đó và xanh: `mo_duoc` = true, `man` = 'mPhieu'…
 *
 * Chỗ hỏng nằm ở giao diện: `var mo = !!x.mo_duoc && !!x.url;` — đòi có `url`. Mà năm ô ấy
 * KHÔNG có `url`, chúng mở một màn ngay trong trạm bằng `man`. Nên nhánh `if(mo && x.man)`
 * ngay dưới là **mã chết**, không lượt nào chạy tới, và mọi ô `man` rơi xuống nhánh khoá.
 *
 * ⚠️ BÀI CŨ KIỂM "NHÁNH CÓ TỒN TẠI", KHÔNG KIỂM "NHÁNH CÓ ĐẾN ĐƯỢC". Nó tìm chuỗi
 *    `'<button type="button" class="o-ung o-man"'` trong mã nguồn và thấy — trong khi dòng
 *    quyết định ngay phía trên đã chặn hết. Một nhánh có mặt mà không ai tới được thì với
 *    người dùng là không có.
 *
 * Nên bài này KHÔNG dò chuỗi: nó BỐC ĐÚNG BIỂU THỨC ẤY ra rồi CHẠY THẬT với từng dáng ô.
 *
 * Chạy: node tools/test/kiem-o-man-mo-duoc.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const TPL = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-cham-cong/templates/tram.php'), 'utf8');

/* ── Bốc đúng dòng quyết định ô mở hay khoá ────────────────────────────────────────────── */
const m = TPL.match(/var\s+mo\s*=\s*([^;]+);/);
t('bốc được biểu thức quyết định ô mở/khoá', !!m);
if (!m) { ket(); }
const bieu_thuc = m[1].trim();
const quyet = new Function('x', 'return !!(' + bieu_thuc + ');');

/* ── Ba dáng ô, chạy thật ──────────────────────────────────────────────────────────────── */
/* 1. Ô dẫn sang app khác — lối cũ, phải còn nguyên. */
t('ô có url và được cấp thì MỞ',
  quyet({ mo_duoc: true, url: 'https://vi.du/' }) === true, bieu_thuc);

/* 2. 🔴 Ô MỞ MÀN TRONG TRẠM — đúng cái đã hỏng. Năm ô nhóm "Của tôi" đều dáng này. */
t('🔴 ô mở MÀN trong trạm (có man, không url) cũng phải MỞ',
  quyet({ mo_duoc: true, man: 'mPhieu' }) === true, bieu_thuc);

/* 3. Ô khoá. `VHCC_Ung::o()` đã `unset()` CẢ `url` LẪN `man`, nên ô khoá tới đây trống trơn —
      chốt này không phải là lớp gác cuối, nhưng cũng không được biến ô khoá thành ô mở. */
t('ô khoá (máy chủ đã bỏ cả url lẫn man) vẫn KHOÁ',
  quyet({ mo_duoc: false }) === false, bieu_thuc);
t('🔴 và cờ mo_duoc=false thì dù còn sót man cũng KHOÁ',
  quyet({ mo_duoc: false, man: 'mPhieu' }) === false, bieu_thuc);
t('🔴 cờ mo_duoc=false thì dù còn sót url cũng KHOÁ',
  quyet({ mo_duoc: false, url: 'https://vi.du/' }) === false, bieu_thuc);

/* ── Và nhánh <button> phải THẬT SỰ đến được ────────────────────────────────────────────
   Đây là vế còn thiếu của bài cũ: không hỏi "có nhánh không" mà hỏi "ô `man` có rơi vào
   nhánh ấy không". */
const i_mo = TPL.indexOf('var mo =');
const sau  = TPL.slice(i_mo, i_mo + 1400);
t('🔴 nhánh ô mở màn nằm SAU chốt và nhận đúng ô có man',
  /if\s*\(\s*mo\s*&&\s*x\.man\s*\)/.test(sau), sau.slice(0, 300));
t('   nhánh ấy dựng <button>, không phải <a>',
  sau.indexOf('<button type="button" class="o-ung o-man"') >= 0);

/* ── Máy chủ: năm ô nhóm "Của tôi" không được gác gì ────────────────────────────────────
   Giao diện sửa rồi mà máy chủ lỡ gác lại thì người dùng vẫn thấy xám — hai đầu phải khớp. */
const UNG = fs.readFileSync(
  path.join(GOC, 'wordpress/vhcp-cham-cong/includes/class-vhcc-ung.php'), 'utf8');
['Phiếu lương', 'Xin bù giờ', 'Khai giờ khác', 'Xin nghỉ', 'Gửi đơn đi trễ'].forEach(function (ten) {
  const i = UNG.indexOf("'ten'  => '" + ten + "'");
  t('🔴 máy chủ có dựng ô "' + ten + '"', i >= 0);
  if (i < 0) { return; }
  /* Lùi lại tìm lời gọi `o(` gần nhất: phải là `o( true,` — tức không gác quyền nào. */
  const truoc = UNG.slice(Math.max(0, i - 400), i);
  t('🔴 ô "' + ten + '" không gác quyền (o( true, …)',
    /self::o\(\s*true,/.test(truoc), truoc.slice(-160));
});

function ket() {
  console.log('');
  if (TRUOT.length) {
    console.log('🔴 HỎNG ' + TRUOT.length + ' phép thử:');
    TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
    console.log('ĐẠT: ' + DAT);
    process.exit(1);
  }
  console.log('✓ ĐẠT: ' + DAT + ' phép thử — ô mở màn trong trạm không còn bị coi là ô khoá.');
}
ket();
