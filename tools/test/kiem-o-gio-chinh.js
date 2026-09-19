/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô "GIỜ TỰ TÍNH" CỦA VIỆC CHÍNH NHẢY NGAY LÚC GÕ GIỜ ĐƠN GIÁ KHÁC
 *
 * Anh Thắng 18/09/2026: *"khi khai giờ đơn khác, nhập số vào, giờ đơn chính nhảy luôn để xem"*,
 * kèm ảnh khối chốt lương: gõ MC = 5 mà ô trên vẫn đứng ở 9,00.
 *
 * =============================================================================================
 * 🔴 VÌ SAO CẦN BÀI KIỂM, CHỨ KHÔNG PHẢI NHÌN LÀ XONG
 * =============================================================================================
 * Đoạn JS này do PHP GHÉP CHUỖI mà ra (`class-vhcc-web.php`), nên nó không nằm trong tệp .js
 * nào để mắt soi và không trình soạn thảo nào báo lỗi cú pháp cho nó. Một dấu nháy thiếu là cả
 * khối script chết, và trang vẫn vẽ ra bình thường — chỉ có ô giờ là đứng im, y như trước khi
 * làm tính năng này. Hỏng kiểu ấy không ai phát hiện ra bằng cách mở trang.
 *
 * Nên bài này BỐC ĐÚNG ĐOẠN ẤY TỪ TỆP PHP, dựng một DOM giả, rồi gõ thử như người thật.
 *
 * ⚠️ CON SỐ Ở Ô ẤY CHỈ ĐỂ NHÌN. Máy chủ tự tính lại phần còn lại lúc lưu (`VHCC_ChotLuong`),
 *    nên bài này canh CÁI HIỂN THỊ, không canh tiền. Tiền có bài riêng ở `kiem-chot-luong.php`.
 *
 * Chạy: node tools/test/kiem-o-gio-chinh.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const PHP = fs.readFileSync('wordpress/vhcp-cham-cong/includes/class-vhcc-web.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', mong === thuc, thuc); }

/* ---- bốc đoạn JS ra khỏi chuỗi PHP ----
   Khối là một chuỗi `echo '…' . '…' . …;` có xen chú thích PHP. Cắt chú thích trước, rồi nhặt
   từng chuỗi nháy đơn và nối lại — đúng thứ PHP sẽ in ra. */
const m = PHP.match(/echo '<script>\/\*vhcc-clchinh\*\/[\s\S]*?<\/script>';/);
t('🔴 tìm thấy khối vhcc-clchinh trong class-vhcc-web.php', !!m);
if (!m) { xong(); }

const khongChuThich = m[0].replace(/\/\*[\s\S]*?\*\//g, (x) => (x.indexOf('vhcc-clchinh') >= 0 ? x : ''));
let JS = '';
for (const lit of khongChuThich.match(/'(?:[^'\\]|\\.)*'/g) || []) {
	JS += lit.slice(1, -1).replace(/\\'/g, "'").replace(/\\\\/g, '\\');
}
JS = JS.replace(/^<script>/, '').replace(/<\/script>$/, '');
t('bốc ra được đoạn JS', JS.length > 200, JS.length);

/* 🔴 CÚ PHÁP TRƯỚC ĐÃ. Đây là thứ mở trang không bao giờ nói cho biết. */
try { new Function(JS); t('🔴 đoạn JS đúng cú pháp', true); }
catch (e) { t('🔴 đoạn JS đúng cú pháp', false, String(e)); }

/* ---- DOM giả: một khối .hs-in, ô tổng, ba ô giờ khác, ô kết quả, dòng thập phân ----
   ⚠️ DOM GIẢ PHẢI CÓ ĐÚNG NHỮNG GÌ ĐOẠN JS ĐỤNG TỚI. Thiếu `getAttribute` là bài đỏ với một
   câu lỗi chẳng liên quan gì tới thứ nó định canh — đã vấp đúng thế khi thêm lối giờ:phút. */
const oRa = {
	value: '', style: {}, title: '',
	thuoc: { 'data-clchinh': '1', 'data-clhm': '0' },
	getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.thuoc, k) ? this.thuoc[k] : null; },
};
const oThap = { textContent: '' };
const oTong = { value: '9' };
const oGio = [0, 1, 2].map((i) => ({ name: 'cl_gio[' + i + ']', value: '' }));
const khoi = {
	querySelector: (s) => {
		if (s.indexOf('clchinh') >= 0) { return oRa; }
		if (s.indexOf('clthap') >= 0) { return oThap; }
		return oTong;
	},
	querySelectorAll: () => oGio,
};
oGio.forEach((o) => { o.closest = () => khoi; });

let nghe = null;
const document = { addEventListener: (loai, f) => { if ('input' === loai) { nghe = f; } } };
new Function('document', JS)(document);
t('🔴 khối có gài người nghe sự kiện input', 'function' === typeof nghe);

function go(i, v) { oGio[i].value = v; nghe({ target: oGio[i] }); return oRa.value; }

/* ---- gõ thử như người thật ---- */
teq('gõ MC = 5 thì giờ chính nhảy còn 4,00', '4,00', go(0, '5'));
teq('thêm một dòng 1,5 nữa thì còn 2,50', '2,50', go(1, '1,5'));
/* ⚠️ Bàn phím điện thoại cho dấu nào thì người ta gõ dấu ấy. Chối một trong hai là ô trên đứng
   im mà không nói vì sao — trông y hệt tính năng hỏng. */
teq('dấu CHẤM cũng ăn, không riêng dấu phẩy', '2,50', go(1, '1.5'));
teq('xoá trắng một dòng thì cộng lại', '4,00', go(1, ''));
teq('xoá hết thì về đủ giờ chấm công', '9,00', go(0, ''));

/* 🔴 GÕ QUÁ TAY PHẢI THẤY NGAY. Máy chủ chối lượt lưu ấy, nhưng nói ra tại ô thì người ta sửa
   TRƯỚC khi bấm, không phải sau. */
teq('gõ nhiều hơn cả giờ chấm công thì ra số âm', '-3,00', go(0, '12'));
teq('và ô đổi sang màu đỏ', 'var(--do)', oRa.style.color);
t('kèm câu nói rõ vì sao', oRa.title.indexOf('nhiều hơn') >= 0, oRa.title);
go(0, '1');
teq('hết âm thì bỏ màu đi', '', oRa.style.color);
t('và câu nhắc trở lại bình thường', oRa.title.indexOf('không gõ tay được') >= 0, oRa.title);

/* Ngăn nghìn theo lối Việt: dấu chấm cho nghìn, dấu phẩy cho thập phân. */
oTong.value = '1234.5';
teq('số lớn ngăn nghìn đúng lối Việt', '1.234,50', go(0, '0'));

/* Chữ rác trong ô không được làm hỏng cả phép tính — coi như 0. */
oTong.value = '9';
teq('gõ chữ rác thì tính như 0', '9,00', go(0, 'abc'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LỐI GIỜ:PHÚT — VÀ DÒNG THẬP PHÂN BÊN DƯỚI VẪN PHẢI ĐÚNG
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 19/09/2026, ảnh ô tổng lưới `203:30` cạnh ô này `203,50`: *"khác hệ, chỉnh lại về
 * 203.3"*. Ô lớn nay viết theo lưới.
 *
 * ⚠️ NHƯNG SỐ NHÂN VỚI ĐƠN GIÁ VẪN LÀ THẬP PHÂN, và nó nằm ở dòng nhỏ ngay dưới. Hai chỗ phải
 *    nhảy CÙNG LÚC: ô lớn nói `203:30` mà dòng dưới còn đứng ở số cũ thì tệ hơn hẳn chỉ có một
 *    con số, vì người ta sẽ nhân cái đứng im.
 * ---------------------------------------------------------------------------------------------- */
oRa.thuoc['data-clhm'] = '1';
oTong.value = '9';
teq('🔴 bật giờ:phút: gõ MC = 5 thì ô lớn ra 4:00', '4:00', go(0, '5'));
teq('   và dòng thập phân dưới nó ra 4,00', '4,00', oThap.textContent);
teq('🔴 nửa giờ ra :30, KHÔNG phải :50', '3:30', go(0, '5,5'));
teq('   dòng thập phân cùng lúc là 3,50', '3,50', oThap.textContent);
/* 🔴 CHÍNH CON SỐ TRONG ẢNH. 203,5 giờ là 203:30; cắt phần thập phân làm phút thì ra 203:05 —
   và nó sẽ nằm ngay cạnh ô tổng `203:30` của chính lưới ấy. */
oTong.value = '203.5';
teq('🔴 203,5 giờ = 203:30 (không phải 203:05)', '203:30', go(0, ''));
teq('   dòng thập phân là 203,50', '203,50', oThap.textContent);
/* 5 giờ 20 phút — ca 16:40 → 22:00 trong ảnh. 1/3 giờ không chia chẵn ra phút. */
oTong.value = '5.3333333';
teq('🔴 5,3333 giờ = 5:20', '5:20', go(0, ''));
teq('   số âm vẫn đọc được', '-1:30', (function () { oTong.value = '0'; return go(0, '1,5'); })());
oTong.value = '9';
go(0, '');

/* Tắt lại lối giờ:phút thì mọi thứ về y như cũ — người bấm nút không mất gì. */
oRa.thuoc['data-clhm'] = '0';
teq('tắt đi thì ô lớn về thập phân', '4,00', go(0, '5'));
go(0, '');

/* ---- ô kết quả phải là ô CHỈ ĐỌC, và phải có dấu để JS tìm ra ---- */
t('🔴 ô giờ chính có readonly — nó là kết quả, không phải chỗ gõ',
	/data-clchinh="1"[\s\S]{0,200}readonly/.test(PHP) || /readonly[\s\S]{0,200}data-clchinh="1"/.test(PHP));
t('ô tổng giờ chấm công có mặt để trừ', PHP.indexOf('name="cl_gio_cham"') >= 0);

xong();

function xong() {
	if (TRUOT.length) {
		console.log('🔴 HỎNG ' + TRUOT.length + ' phép thử:');
		for (const x of TRUOT) { console.log('  ✗ ' + x); }
		console.log('ĐẠT: ' + DAT);
		process.exit(1);
	}
	console.log('✓ ĐẠT — ' + DAT + ' phép: ô giờ chính nhảy đúng theo mọi lượt gõ.');
	process.exit(0);
}
