/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÁY ĐỨNG YÊN MÀ CÓ QR — CHỈ CẢNH BÁO, VẪN GỬI ĐƯỢC
 *
 * Anh Thắng 31/08/2026: *"Khi chỉ số đứng yên (nhưng lại có chỉ số QR) dẫn đến chỉ số tiền mặt
 * âm. Lúc này nhân viên sẽ nhập thực thu là 0. Thì vẫn cho phép gửi báo cáo."* và *"Chỉ đưa ra
 * cảnh báo, nhưng vẫn cho phép gửi báo cáo bình thường, và chỉ số tiền mặt lúc này vẫn ghi nhận
 * thực thu, và chỉ số QR vẫn là QR."*
 *
 * 🔴 LUẬT NÀY VIẾT Ở HAI NƠI: một bản JavaScript trong trang (chặn tay người bấm) và một bản PHP
 *    ở máy chủ (`VHG_BaoCao::luu()`). Hai bản lệch nhau thì hoặc trang cho gửi mà máy chủ chối —
 *    nhân viên bấm mãi không hiểu vì sao — hoặc trang chặn oan một lượt máy chủ vốn nhận. Bài
 *    kiểm này BỐC CẢ HAI BIỂU THỨC ra, chạy trên cùng một bộ ca, rồi so từng ca một.
 *
 * ⚠️ KHÔNG DÒ CHUỖI. Dò chuỗi chỉ canh được đúng dòng chữ hôm nay; đổi cách viết là mù.
 *
 * Chạy: node tools/test/kiem-may-dung-co-qr.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const G = 'vhcp-ghe/includes/';
const jsSrc  = fs.readFileSync(G + 'class-vhg-trang.php', 'utf8');
const phpSrc = fs.readFileSync(G + 'class-vhg-baocao.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }

/* ---------- bốc biểu thức của TRANG ---------- */
const iJs = jsSrc.indexOf('var mayDungCoQR=(');
const jJs = jsSrc.indexOf(';', iJs);
t('bốc được luật trong trang', iJs > 0 && jJs > iJs);
const bieuThucJs = jsSrc.slice(iJs + 'var mayDungCoQR='.length, jJs);

const luatTrang = new Function('before', 'after', 'qr', 'rawCash', 'return ' + bieuThucJs + ';');

/* ---------- bốc biểu thức của MÁY CHỦ, đổi sang JS ---------- */
const iP = phpSrc.indexOf('$dung_yen = (');
const jP = phpSrc.indexOf('$may_dung_co_qr = (');
const kP = phpSrc.indexOf(';', jP);
t('bốc được luật ở máy chủ', iP > 0 && jP > iP && kP > jP);
let php = phpSrc.slice(iP, kP + 1);
/* Đổi cú pháp PHP sang JS: chỉ những phép thật sự có mặt trong hai dòng ấy. Cố ý KHÔNG viết một
   bộ dịch PHP→JS tổng quát — nó sẽ nuốt trôi mọi thứ, kể cả cú pháp mà bản dịch hiểu sai. */
php = php
  .replace(/\$r\['chi_so_truoc'\]/g, 'before')
  .replace(/\$r\['chi_so_sau'\]/g, 'after')
  .replace(/\$r\['qr'\]/g, 'qr')
  .replace(/\$r\['tien_mat'\]/g, 'rawCash')
  .replace(/\$dung_yen/g, 'dung_yen')
  .replace(/\$may_dung_co_qr/g, 'may_dung_co_qr')
  .replace(/\(int\) (\w+)/g, 'Number($1)')
  .replace(/null !== before/g, "(before!==null&&before!=='')")
  .replace(/===/g, '===')
  .replace(/ && /g, ' && ');
/* Sót một ký hiệu PHP là biểu thức dưới đây ném lỗi hoặc — tệ hơn — chạy ra một kết quả khác
   hẳn bản thật mà vẫn xanh. Soi `$` (biến PHP), `::` (gọi lớp) và lối viết `null !==` của PHP. */
t('bản dịch không còn ký hiệu PHP nào sót lại', !/\$|::|null !==/.test(php), php);
const luatMayChu = new Function('before', 'after', 'qr', 'rawCash',
  'var dung_yen, may_dung_co_qr;' + php + 'return may_dung_co_qr;');

/* ---------- bảng ca ---------- */
/* [nhãn, trước, sau, qr, mong đợi] — rawCash tính đúng như hai đầu tính: (sau-trước)*đv - qr. */
const DV = 10000;
const CA = [
  ['🔴 ca của anh Thắng: 449 → 449, QR 10.000 → chỉ cảnh báo', 449, 449, 10000, true],
  ['máy đứng yên mà KHÔNG có QR (nghỉ hẳn) → không phải ca này', 449, 449, 0, false],
  ['🔴 chỉ số TĂNG mà QR lớn hơn (ca AM-BD-1) → VẪN phải chặn', 597, 610, 240000, false],
  ['chỉ số tăng, QR nhỏ hơn → bình thường, không cảnh báo', 597, 610, 50000, false],
  ['🔴 chỉ số ĐI NGƯỢC → vẫn là bất thường phải ghi lý do', 610, 597, 10000, false],
  ['đứng yên + QR lớn → vẫn là ca này', 100, 100, 999000, true],
  ['chưa gõ chỉ số sau → chưa xét', 449, '', 10000, false],
  ['chưa có chỉ số trước → chưa xét', '', 449, 10000, false],
];

/* 🔴 CA RIÊNG: MÁY ĐỨNG YÊN, KHÔNG QR, MÀ TIỀN VẪN ÂM — do trừ lượt KÍCH GHẾ TỪ XA.
   `rawCash` ở đây là `actual − qr` với actual ĐÃ TRỪ tiền kích xa, nên nó âm được ngay cả khi
   QR bằng 0. Ca ấy KHÔNG phải "khách trả QR mà máy không đếm" — không có lượt QR nào cả — nên
   phải rơi vào nhánh bất thường cũ, đòi lý do như thường. Đây chính là ca làm cho chốt `qr>0`
   có tác dụng thật; thiếu nó thì bỏ `qr>0` đi mà bài kiểm vẫn xanh. */
const CA_KICH = ['🔴 đứng yên, KHÔNG QR, âm vì trừ kích xa → KHÔNG phải ca này', 449, 449, 0, -30000, false];

CA.forEach(function (c) {
  const [nhan, before, after, qr, mong] = c;
  const raw = (before === '' || after === '') ? 0 : (Number(after) - Number(before)) * DV - qr;
  const rTrang  = !!luatTrang(before, after, qr, raw);
  const rMayChu = !!luatMayChu(before === '' ? null : Number(before), after === '' ? null : Number(after), qr, raw);
  t('TRANG: ' + nhan, rTrang === mong, { thay: rTrang, mong: mong });
  /* Máy chủ nhận `chi_so_truoc` là null khi chưa có, còn trang nhận chuỗi rỗng — khác cách biểu
     diễn "chưa biết", nhưng KẾT LUẬN phải giống hệt. */
  t('MÁY CHỦ: ' + nhan, rMayChu === mong, { thay: rMayChu, mong: mong });
  t('🔴 hai đầu cùng kết luận: ' + nhan, rTrang === rMayChu, { trang: rTrang, mayChu: rMayChu });
});

(function () {
  const [nhan, before, after, qr, raw, mong] = CA_KICH;
  const rTrang  = !!luatTrang(before, after, qr, raw);
  const rMayChu = !!luatMayChu(Number(before), Number(after), qr, raw);
  t('TRANG: ' + nhan, rTrang === mong, { thay: rTrang, mong: mong });
  t('MÁY CHỦ: ' + nhan, rMayChu === mong, { thay: rMayChu, mong: mong });
  t('🔴 hai đầu cùng kết luận: ' + nhan, rTrang === rMayChu, { trang: rTrang, mayChu: rMayChu });
})();

/* ---------- chốt: máy chủ vẫn CHẶN khi thiếu Thực thu ---------- */
/* "Nhập thực thu là 0" — số 0 ấy là lời khai rằng ca này không thu được đồng tiền mặt nào. Bỏ
   trống thì tiền mặt rơi về công thức và ghi số ÂM vào sổ. */
t('🔴 máy chủ chỉ tha khi ĐÃ có Thực thu',
  /\$bat_thuong && ! \( \$may_dung_co_qr && null !== \$thuc_thu \)/.test(phpSrc));
t('🔴 và trang cũng đòi Thực thu trước khi cho gửi',
  /if\(mayDungR\)\{[\s\S]{0,120}if\(!coTTR\)\{ canhBao\.push/.test(jsSrc));
/* Tiền mặt ghi nhận = Thực thu, QR giữ nguyên là QR — đúng lời anh Thắng. */
t('🔴 tiền mặt lấy đúng Thực thu, tổng = tiền mặt + QR',
  /\$r\['tien_mat'\] = \$thuc_thu;\s*\n\s*\$r\['tong'\]\s*= \$r\['tien_mat'\] \+ \$r\['qr'\];/.test(phpSrc));
/* Cảnh báo phải để lại vết cho kế toán — "chỉ cảnh báo" là không chặn tay nhân viên, không phải
   im lặng với người soát sổ. */
/* ⚠️ SOI CẢ ĐIỀU KIỆN, không chỉ soi chuỗi. Chuỗi vẫn nằm nguyên trong tệp kể cả khi cái `if`
   bọc nó bị đổi thành `if ( false )` — soi trần thì phép thử xanh trong khi kế toán không còn
   nhận được cảnh báo nào. */
t('🔴 ghi chú mang cảnh báo để kế toán soi được',
  /if \( \$may_dung_co_qr && null !== \$thuc_thu \) \{\s*\n\s*\$r\['ghi_chu'\][\s\S]{0,200}MÁY ĐỨNG YÊN/.test(phpSrc));
/* Khung nhắc KHÔNG được có ô lý do: có ô là người ta tưởng phải điền mới gửi được. */
const iN = jsSrc.indexOf('if(mayDungCoQR){');
const iE = jsSrc.indexOf('} else if(batThuong){', iN);
t('bốc được nhánh vẽ khung nhắc', iN > 0 && iE > iN);
t('🔴 khung nhắc KHÔNG dựng ô lý do', iN > 0 && !/el\('input','ly-do-bt'\)/.test(jsSrc.slice(iN, iE)));
t('và nó mặc áo NHẮC, không phải áo chặn màu đỏ', iN > 0 && /bc-nhac/.test(jsSrc.slice(iN, iE)));

/* ---------- CẢNH BÁO PHẢI ĐI TỚI KẾ TOÁN ---------- */
/* Anh Thắng 31/08/2026: *"cảnh báo đó sẽ đi kèm khi gửi về kế toán, để kế toán biết nhé"*.
   Ba chặng, thiếu chặng nào thì cảnh báo chết dọc đường:
     1. lúc GỬI  — ghép vào `ghi_chu` của hàng (đã canh ở khối trên)
     2. lúc ĐỌC  — `kt_ct` trả `ghi_chu` về màn kế toán, và hàng ghế in nó ra
     3. lúc LƯỚT — đếm ở dòng tóm tắt để kế toán biết thẻ nào cần mở
   Chặng 3 mới là chặng dễ quên nhất: hai chặng đầu vẫn chạy, chỉ là không ai đọc — mà cảnh báo
   không ai đọc thì bằng không có. */
const ktSrc = fs.readFileSync(G + 'class-vhg-ketoan.php', 'utf8');
t('🔴 (2) kế toán nhận được ghi chú của từng ghế',
  /'note'\s*=>\s*\(string\) \$r\['ghi_chu'\]/.test(ktSrc));
t('🔴 (2) và hàng ghế IN ghi chú ấy ra màn', /if\(c\.note\)\{/.test(jsSrc));
t('và tô đỏ khi ghi chú mở đầu bằng ⚠', /\/\^⚠\/\.test\(c\.note\)/.test(jsSrc));

t('🔴 (3) máy chủ ĐẾM số ghế có cảnh báo trong mỗi báo cáo',
  /\$o\['chairsWarn'\]\+\+/.test(ktSrc));
/* Đếm theo DẤU `⚠` ở đầu ghi chú — quy ước sẵn có của màn này. Dò từng câu chữ thì thêm một
   loại cảnh báo mới là phải nhớ sửa thêm chỗ đếm, mà không ai nhớ. */
t('và đếm theo DẤU ⚠, không dò từng câu chữ',
  /mb_strpos\( trim\( \(string\) \$r\['ghi_chu'\] \), '⚠' \)/.test(ktSrc));
t('🔴 (3) và dòng tóm tắt của báo cáo bày con số ấy ra',
  /if\(o\.chairsWarn\)\{[\s\S]{0,200}ghế cần soi/.test(jsSrc));
/* Bộ đếm phải nằm trong khuôn khởi tạo, nếu không lượt cộng đầu tiên là cộng vào khoá chưa có. */
t('bộ đếm được khai giá trị đầu', /'chairsWarn' => 0,/.test(ktSrc));

if (TRUOT.length) {
  console.log('HỎNG: ' + TRUOT.length);
  TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
  process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: máy đứng yên mà có QR thì chỉ cảnh báo, hai đầu cùng một luật.');
