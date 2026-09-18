/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô GIỜ CỦA MÀN XIN BÙ: GÕ `1000` PHẢI RA `10:00`, VÀ PHẢI NÓI RA ĐANG XIN MẤY GIỜ.
 *
 * Anh Thắng 18/09/2026 gửi ảnh màn Xin bù giờ: ô Giờ vào `1000`, Giờ ra `1700`.
 *
 * =============================================================================================
 * 🔴 ĐÓ LÀ CÁCH GÕ TỰ NHIÊN NHẤT, VÀ MÁY CHỦ CHỐI NÓ
 * =============================================================================================
 * Ô khai `inputmode="numeric"` — chính mình mời người ta gõ toàn số. Mà `VHCC_XinBu::phut()`
 * khớp đúng `/^(\d{2}):(\d{2})$/`, nên `1000` bị chối.
 *
 * Tệ hơn: màn kiểm LÝ DO trước. Người dùng thấy "ghi rõ vì sao, ít nhất 5 chữ", gõ lý do, bấm
 * lại — lúc ấy mới ăn lỗi giờ. Hai lần bị chối cho một lần điền, lần sau nói về một ô họ tưởng
 * đã xong.
 *
 * 🔴 SỬA Ở PHÍA GÕ, KHÔNG NỚI Ở MÁY CHỦ — nên bài này canh CẢ HAI VẾ:
 *    · phía gõ chuẩn hoá được `1000` -> `10:00`;
 *    · và `phut()` bên máy chủ VẪN chặt (nới ra là mở cho `10 0`, `1:0:0`…).
 *
 * ⚠️ BÀI NÀY CHẠY THẬT HAI HÀM BỐC TỪ MÃ NGUỒN, không dò chuỗi — cùng lối với
 *    `kiem-o-man-mo-duoc.js`: một hàm có mặt mà tính sai thì dò chuỗi vẫn xanh.
 *
 * Chạy: node tools/test/kiem-o-gio-xin-bu.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const TPL = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-cham-cong/templates/tram.php'), 'utf8');
const BU  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-cham-cong/includes/class-vhcc-xin-bu.php'), 'utf8');

/* ── Bốc hai hàm ra chạy thật ──────────────────────────────────────────────────────────── */
function boc(ten) {
  const i = TPL.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  if (i < 0) { return ''; }
  const j = TPL.indexOf('\n}', i);
  return TPL.slice(i, j + 2);
}
const ma = boc('xbChuanGio') + '\n' + boc('xbPhut');
const chay = new Function(ma + '\nreturn { xbChuanGio: xbChuanGio, xbPhut: xbPhut };')();

/* ── 1. CHUẨN HOÁ LÚC GÕ ───────────────────────────────────────────────────────────────── */
function go(v, xoa) { const o = { value: v }; chay.xbChuanGio(o, !!xoa); return o.value; }

t('🔴 gõ "1000" ra "10:00" — đúng cảnh anh Thắng chụp', '10:00' === go('1000'), go('1000'));
t('🔴 gõ "1700" ra "17:00"', '17:00' === go('1700'), go('1700'));
t('gõ sẵn "08:30" thì giữ nguyên', '08:30' === go('08:30'), go('08:30'));
/* Chèn khi ĐỦ 3 chữ số, không sớm hơn: chèn ở chữ số thứ 2 thì người gõ thấy `10:` nhảy ra
   giữa chừng và tưởng mình gõ nhầm. */
t('🔴 mới 2 chữ số thì CHƯA chèn dấu', '10' === go('10'), go('10'));
t('   3 chữ số mới chèn', '10:0' === go('100'), go('100'));
t('quá 4 chữ số thì cắt, không để tràn', '12:34' === go('123456'), go('123456'));
t('rác không phải số thì bỏ', '' === go('abc'), go('abc'));
/* 🔴 ĐANG XOÁ THÌ ĐỪNG CHÈN LẠI. Tự chèn lại dấu vừa xoá là ô không xoá nổi — người dùng bấm
   Backspace mà chữ không giảm, và họ không có cách nào hiểu vì sao. */
t('🔴 đang xoá thì KHÔNG chèn lại dấu', '10:0' === go('10:0', true), go('10:0', true));

/* ── 2. ĐỌC GIỜ — CÙNG LUẬT VỚI MÁY CHỦ ────────────────────────────────────────────────── */
t('đọc "10:00" ra 600 phút', 600 === chay.xbPhut('10:00'), chay.xbPhut('10:00'));
t('đọc "17:00" ra 1020 phút', 1020 === chay.xbPhut('17:00'), chay.xbPhut('17:00'));
t('🔴 chưa ra hình HH:mm thì trả null, đừng đoán', null === chay.xbPhut('1000'), chay.xbPhut('1000'));
t('giờ > 23 thì null', null === chay.xbPhut('25:00'), chay.xbPhut('25:00'));
t('phút > 59 thì null', null === chay.xbPhut('10:99'), chay.xbPhut('10:99'));

/* ── 3. MÀN CÓ NÓI RA SỐ GIỜ ───────────────────────────────────────────────────────────── */
t('🔴 có chỗ hiện số giờ đang xin', TPL.indexOf('id="xbTong"') >= 0);
t('   và có hàm tính nó', TPL.indexOf('function xbHienTong(') >= 0);
t('🔴 bắt được ca gõ ngược hai ô', TPL.indexOf('hai ô đang ngược nhau') >= 0);
t('   và ca giờ dài bất thường', TPL.indexOf('dài bất thường') >= 0);

/* ── 4. MÁY CHỦ VẪN CHẶT ───────────────────────────────────────────────────────────────── */
/* 🔴 Đây là vế dễ quên nhất: sửa cho người dùng đỡ khổ rồi tiện tay nới luôn cửa cuối. `phut()`
   là chỗ cuối cùng trước khi một con giờ thành công thành tiền. */
t('🔴 VHCC_XinBu::phut() VẪN đòi đúng HH:mm',
  BU.indexOf("preg_match( '/^(\\d{2}):(\\d{2})$/'") >= 0);

console.log('');
if (TRUOT.length) {
  console.log('🔴 HỎNG ' + TRUOT.length + ' phép thử:');
  TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
  console.log('ĐẠT: ' + DAT);
  process.exit(1);
}
console.log('✓ ĐẠT: ' + DAT + ' phép thử — gõ 1000 là ra 10:00, và màn nói ra đang xin mấy giờ.');
