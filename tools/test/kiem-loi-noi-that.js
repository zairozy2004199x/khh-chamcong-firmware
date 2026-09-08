/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * CÂU LỖI TRÊN MÀN PHẢI NÓI RA MÁY CHỦ TRẢ VỀ CÁI GÌ
 *
 * Anh Thắng 08/09/2026: *"Trang ghế không gửi được báo cáo. Đây là lỗi gì"* — màn chỉ nói
 * *"Không đọc được trả lời của máy chủ (mạng hoặc tường lửa)"*. Cùng câu ấy anh đã báo
 * 29/08/2026, và bản vá lần đó (nén ảnh nhỏ lại) không trúng: lỗi quay lại cả khi không đính
 * ảnh nào.
 *
 * 🔴 CÂU ĐOÁN LÀ CÂU VÔ DỤNG. "Mạng hoặc tường lửa" đúng với đủ mọi nguyên nhân — gói tin nặng,
 *    tường lửa chặn, PHP chết, hay chỉ một dòng `Warning:` in trước JSON — nên nó không chỉ ra
 *    nguyên nhân nào. Người ở cơ sở đi đổi wifi; người sửa thì vá mò. Mã HTTP tách bạch ngay.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY, KHÔNG CHÉP LUẬT VÀO ĐÂY.
 *
 * Chạy: node tools/test/kiem-loi-noi-that.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const SRC = fs.readFileSync('vhcp-ghe/includes/class-vhg-trang.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

function bocHam(ten, thut) {
  const mo = thut + 'function ' + ten + '(';
  const i = SRC.indexOf(mo);
  if (i < 0) return '';
  const j = SRC.indexOf('\n' + thut + '}', i) + thut.length + 2;
  return (j > i) ? SRC.slice(i, j) : '';
}
const fn1 = bocHam('loiTho_', '  ');
const fn2 = bocHam('loiTho2_', '');
t('bốc được loiTho_() của màn báo cáo', fn1.length > 300, fn1.length);
t('bốc được loiTho2_() của màn /ghe', fn2.length > 300, fn2.length);

const F1 = new Function(fn1 + '\nreturn loiTho_;')();
/* Màn /ghe hai ngôn ngữ: `L(vi,en)` chọn theo NN. Chạy cả hai để chắc không nhánh nào rơi ra
   `undefined` — một câu lỗi hiện chữ "undefined" còn tệ hơn câu đoán cũ. */
function F2(x, nn) {
  return new Function('L', 'x', fn2 + '\nreturn loiTho2_(x);')(
    function (vi, en) { return nn === 'en' ? en : vi; }, x);
}
function x(status, body) { return { status: status, responseText: body === undefined ? '' : body }; }

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. MỖI MÃ HTTP MỘT CÂU KHÁC NHAU — ĐÓ LÀ TOÀN BỘ MỤC ĐÍCH
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 mất kết nối (mã 0) → nói mất kết nối', /Không nối được/.test(F1(x(0))), F1(x(0)));
t('🔴 413 → nói GÓI TIN QUÁ NẶNG, và bảo bớt ảnh',
  /nặng/.test(F1(x(413))) && /ảnh/.test(F1(x(413))), F1(x(413)));
t('🔴 403 → nói TƯỜNG LỬA chặn', /Tường lửa/.test(F1(x(403, '<html>Blocked</html>'))), F1(x(403, '<html>Blocked</html>')));
t('406 cũng là tường lửa (ModSecurity hay trả mã này)', /Tường lửa/.test(F1(x(406))), F1(x(406)));
t('🔴 500 → nói MÁY CHỦ LỖI, và nói rõ KHÔNG PHẢI MẠNG',
  /Máy chủ gặp lỗi/.test(F1(x(500))) && /không phải mạng/.test(F1(x(500))), F1(x(500)));
t('404 → nói máy chủ từ chối', /từ chối \(404\)/.test(F1(x(404))), F1(x(404)));
t('🔴 200 mà RỖNG → nói rỗng + đoán đúng hướng (PHP chết / gói tin bị cắt)',
  /RỖNG/.test(F1(x(200, ''))) && /cắt|chết/.test(F1(x(200, ''))), F1(x(200, '')));

/* 🔴 PHÉP QUAN TRỌNG NHẤT: 200 kèm rác. Đây là tình huống một `Warning:` của plugin khác in
   trước JSON — nguyên nhân số một, và là thứ câu lỗi cũ không bao giờ nói ra. */
const RAC = '<br /><b>Warning</b>: Undefined array key "x" in <b>/home/u1/wp-content/plugins/abc.php</b> on line <b>44</b><br />{"ok":true}';
const c = F1(x(200, RAC));
t('🔴 200 + rác → nói "không đọc được" kèm nguyên văn', /không đọc được/.test(c), c);
t('🔴 và ĐƯA RA đúng câu cảnh báo để dò', /Undefined array key/.test(c), c);
t('bỏ thẻ HTML cho đọc được trên điện thoại', !/<b>|<br/.test(c), c);
t('gộp khoảng trắng, không xuống dòng loạn', !/\s{2,}/.test(c), c);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. KHÔNG ĐƯỢC DỘI CẢ TRANG CHẶN LÊN MÀN ĐIỆN THOẠI
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const DAI = '<html>' + 'A'.repeat(50000) + '</html>';
const cd = F1(x(200, DAI));
t('🔴 trang chặn dài 50 nghìn ký tự → câu lỗi vẫn ngắn', cd.length < 260, cd.length);
t('và vẫn giữ được một mẩu để nhận dạng', /A/.test(cd), cd.slice(0, 80));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. KHÔNG CÂU NÀO ĐƯỢC RƠI RA "undefined" — cả hai ngôn ngữ
 *
 * 🔴 Ghép chuỗi bằng `L(a,b)+st+L(c,d)` rất dễ sót một vế. Một câu lỗi hiện chữ "undefined"
 *    còn tệ hơn hẳn câu đoán cũ: nó làm người đọc tin là chính trang đang hỏng.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const MA = [0, 200, 400, 403, 404, 406, 413, 500, 502, 503];
['vi', 'en'].forEach(function (nn) {
  MA.forEach(function (st) {
    [ '', 'rác gì đó', RAC ].forEach(function (body) {
      const s1 = F2(x(st, body), nn);
      t('màn /ghe · ' + nn + ' · mã ' + st + ' · thân "' + body.slice(0, 8) + '" → có câu tử tế',
        typeof s1 === 'string' && s1.length > 10 && !/undefined/.test(s1), s1);
      const s2 = F1(x(st, body));
      t('màn báo cáo · mã ' + st + ' · thân "' + body.slice(0, 8) + '" → có câu tử tế',
        typeof s2 === 'string' && s2.length > 10 && !/undefined/.test(s2), s2);
    });
  });
});
t('🔴 bản tiếng Anh thật sự khác bản tiếng Việt', F2(x(413), 'en') !== F2(x(413), 'vi'),
  [F2(x(413), 'en'), F2(x(413), 'vi')]);
t('và bản tiếng Anh không lẫn chữ Việt', !/[àáâãèéêìíòóôõùúýăđĩũơưạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/i.test(F2(x(500), 'en')), F2(x(500), 'en'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. HAI MÀN ĐỀU PHẢI DÙNG NÓ — bốc hàm ra kiểm mà chỗ gọi quên thì bộ thử vẫn xanh
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 màn báo cáo gọi loiTho_()', /error:\s*loiTho_\(x\)/.test(SRC));
t('🔴 màn /ghe gọi loiTho2_()', /error:\s*loiTho2_\(x\)/.test(SRC));
/* Và hai câu chờ/mất mạng cũ vẫn phải còn — chúng đúng, không phải thứ đang sửa. */
t('đối chứng · câu "hết giờ chờ" giữ nguyên', /ontimeout[\s\S]{0,160}?quá tải/.test(SRC));
t('đối chứng · câu "mất kết nối khi gửi" giữ nguyên', /onerror[\s\S]{0,160}?Mất kết nối/.test(SRC));

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (v) { console.error('  · ' + v); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: câu lỗi nói đúng mã HTTP và đưa ra thứ máy chủ thật sự trả về.');
