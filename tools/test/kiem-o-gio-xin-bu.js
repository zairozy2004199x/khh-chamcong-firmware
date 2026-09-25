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

/* ── 4. THIẾU MẤY GIỜ SO VỚI CA ────────────────────────────────────────────────────────── */
/* Anh Thắng 18/09/2026: *"Hiện giờ thiếu so với ca làm"*. Chạy thật `xbGioChu` + soi `xbHienTong`. */
const ma2 = boc('xbGioChu');
const chay2 = new Function(ma2 + '\nreturn xbGioChu;')();
t('7h tròn ghi là "7h"', '7h' === chay2(420), chay2(420));
t('7h30 ghi là "7h30"', '7h30' === chay2(450), chay2(450));
t('có phút lẻ một chữ số thì vẫn hai chữ ("8h05")', '8h05' === chay2(485), chay2(485));

t('🔴 có chỗ bày ca của hôm ấy', TPL.indexOf('id="xbCa"') >= 0);
t('   và hỏi máy chủ bằng cửa riêng', TPL.indexOf("goi('xinbuca'") >= 0);
t('🔴 nói ra THIẾU bao nhiêu so với ca', TPL.indexOf('THIẾU ') >= 0);
t('   và cả DƯ, không chỉ thiếu', TPL.indexOf('DƯ ') >= 0);
t('   khớp đúng ca thì cũng nói', TPL.indexOf('vừa đúng ca') >= 0);
/* 🔴 KHÔNG CÓ LỊCH THÌ ĐỪNG SO. Không có ca mà vẫn so thì mọi đơn đều "dư", và một lời cảnh
   báo sai thì lần sau người ta không đọc nữa. */
t('🔴 chỉ so khi máy chủ THẬT SỰ có ca (tongPhut > 0)',
  TPL.indexOf('XB_CA && XB_CA.tongPhut > 0') >= 0);
t('   và không có lịch thì NÓI LÀ không có, không im',
  TPL.indexOf('không thấy ca nào xếp cho anh/chị') >= 0);
/* Đổi ngày giữa chừng: lượt trả về của ngày cũ không được đè lên ngày mới. */
t('🔴 lượt trả về trễ của ngày cũ bị bỏ',
  TPL.indexOf("el('xbNgay').value !== ng") >= 0);

/* Máy chủ: ca qua nửa đêm (Ca 3 22:00→06:00) phải ra 8h, không ra số âm. */
const TRAM = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-cham-cong/includes/class-vhcc-tram.php'), 'utf8');
t('🔴 máy chủ cộng thêm một ngày cho ca qua nửa đêm',
  TRAM.indexOf('if ( $p2 <= $p1 ) { $p2 += 24 * 60; }') >= 0);
t('   và mã NV lấy từ thẻ phiên, không nhận từ thân',
  TRAM.indexOf("VHCC_Lich::lich_cua_nguoi( $u['ma_nv'], $ng, $ng )") >= 0);

/* ── 5. KHUNG XEM CAMERA KHÔNG ĐƯỢC ĐẨY NÚT CHỤP KHỎI MÀN ──────────────────────────────── */
/* Anh Thắng 18/09/2026, ảnh chụp iPhone: *"Đẩy màn chụp nhỏ lại 1/2 để cho nút chụp lên cao"*.
   Camera trước cho khung DỌC; `width:100%` thì chiều cao kéo theo tỷ lệ và nút "Chụp ngay" rơi
   xuống mép dưới — người đang một tay cầm máy tự chụp mặt mình phải cuộn mới bấm được.

   🔴 20/09/2026 — BA PHÉP THỬ Ở ĐÂY TỪNG CANH GIỮ CHÍNH CÁI LỖI.
   Bản cũ đòi `max-height:…vh` VÀ `object-fit:cover` đặt thẳng lên `.khung video`. Đúng hai thứ
   ấy sinh ra lỗi anh Thắng chụp lại: *"bấm chụp nó gom ảnh là sao vậy"*. Thẻ có kích thước gốc
   mà chạm `max-height` thì trình duyệt co luôn CHIỀU NGANG để giữ tỷ lệ — `<video>` teo thành
   một dải hẹp giữa hai lề trắng, còn `<canvas>` (đã khai width/height bằng thuộc tính HTML) đi
   nhánh khác của cùng luật ấy nên giữ nguyên chiều ngang và bị `cover` cắt trên dưới. Cùng một
   dòng CSS, hai khung khác hẳn nhau: người ta canh mặt vào một khung rồi nhận về khung khác.

   Nên mấy phép thử này phải ĐỔI LUẬT, không phải nới ra. Ai đi sửa lỗi ấy mà gặp một bài thử
   đòi giữ nguyên `max-height`/`cover` thì sẽ tưởng mình vừa làm hỏng thứ gì. Luật mới: `.khung`
   tự giữ chiều cao, hai thẻ con phủ kín nó — chi tiết và phép thử đầy đủ ở `kiem-tram-js.py`. */
t('🔴 khung xem camera có trần chiều cao', /\.khung\{[^}]*height:\s*\d+vh/.test(TPL),
  (TPL.match(/\.khung\{[^}]*}/) || [''])[0]);
t('   trần tính theo vh, không theo pixel (màn nào cũng chừa đúng nửa)',
  /\.khung\{[^}]*height:\s*\d+vh/.test(TPL));
/* 🔴 KHÔNG BÓP MÉO, VÀ CŨNG KHÔNG CẮT. `contain` giữ đúng tỷ lệ y như `cover`, nhưng thêm một
   điều `cover` không cho: cái hiện trên màn ĐÚNG BẰNG cái lưu xuống. Khung xem là thứ người ta
   dùng để canh mặt vào giữa — cắt phần nhìn trong khi ảnh lưu giữ nguyên cả khung là bắt họ
   canh theo một tấm ảnh không tồn tại. */
t('🔴 và giữ đúng tỷ lệ bằng object-fit:contain, không cắt phần nhìn',
  /\.khung video[^}]*object-fit:\s*contain/.test(TPL),
  (TPL.match(/\.khung video[^}]*}/) || [''])[0]);
/* Ảnh LƯU vẫn vẽ từ kích thước thật của video — nay khớp luôn với cái đang bày trên màn. */
t('🔴 ảnh lưu vẫn lấy từ videoWidth/videoHeight, không lấy từ khung đã bày',
  TPL.indexOf('v.videoWidth / v.videoHeight') >= 0);

/* ── 6. Ô NGÀY KHÔNG ĐƯỢC TRÀN RA NGOÀI THẺ (iOS) ──────────────────────────────────────── */
/* Anh Thắng 18/09/2026, ảnh iPhone màn Xin phép đi trễ và Xin nghỉ: *"Lệch ô"* — ô NGÀY thò
   hẳn ra khỏi mép phải thẻ trắng. Safari đặt cho `input[type=date]` một `min-width` nội tại đủ
   chứa "ngày 18 thg 9, 2026", và nó THẮNG `width:100%`. */
const RULE = (TPL.match(/input\[type=date\][^}]*}/) || [''])[0];
t('🔴 có luật riêng cho ô ngày/giờ', RULE.length > 0);
t('🔴 gỡ sàn chiều rộng nội tại (min-width:0)', /min-width:\s*0/.test(RULE), RULE);
t('🔴 bỏ vỏ native, không thì Safari tự đặt lại kích thước',
  /-webkit-appearance:\s*none/.test(RULE), RULE);
t('   và có chốt chặn cuối max-width:100%', /max-width:\s*100%/.test(RULE), RULE);
/* Luật phải phủ cả `month`/`time` — màn Bảng công và Phiếu lương dùng ô tháng. */
t('luật phủ cả ô tháng và ô giờ',
  /input\[type=month\]/.test(TPL) && /input\[type=time\]/.test(TPL));
/* 🔴 KHÔNG ĐƯỢC GỠ `appearance` CỦA MỌI Ô. Gỡ cả `select` là mất mũi tên xổ — người dùng
   không còn biết ô ấy bấm được. */
t('🔴 KHÔNG gỡ appearance của select',
  !/^\s*select\s*{[^}]*appearance:\s*none/m.test(TPL));

/* ── 7. MÁY CHỦ VẪN CHẶT ───────────────────────────────────────────────────────────────── */
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
