/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ — LỌC THEO TUẦN / THÁNG (kỳ lịch)
 *
 * Anh Thắng 12/09/2026: "Cho thêm tính năng lọc theo tuần hoặc tháng."
 *
 * Phép tính ngày là thứ TRÔNG thì đúng mà sai âm thầm: lệch một ngày ở biên tuần, hụt ngày
 * 29/02, tháng 1 lùi về tháng 0 thay vì tháng 12 năm trước. Sai kiểu ấy không nổ, không đỏ,
 * chỉ ra thiếu tiền của một ngày — và đúng cái ngày biên ấy mới có người thắc mắc.
 *
 * ⚠️ BÀI NÀY CHẠY CHÍNH HÀM `khoangLich` TRONG `app.html` — bốc nguyên văn ra, không chép luật.
 *    Chép luật ra đây thì bài thử xanh cả khi app đã hỏng, đúng vết xe của bản 0.18.0 (luật suy
 *    ra máy có BA bản sao, bài thử chỉ canh một bản, màn hình vẫn trắng).
 *
 * Chạy: node tools/test/kiem-saoke-ky-lich.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-saoke/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

function bocHam(ten) {
	const i = src.indexOf('function ' + ten + '(');
	if (i < 0) { throw new Error('không thấy hàm ' + ten + ' trong app.html'); }
	let sau = 0, dem = 0;
	const j = src.indexOf('{', i);
	for (let k = j; k < src.length; k++) {
		if (src[k] === '{') { dem++; }
		else if (src[k] === '}') { dem--; if (dem === 0) { sau = k + 1; break; } }
	}
	return src.slice(i, sau);
}

const khoangLich = new Function(bocHam('dcYmd') + '\n' + bocHam('khoangLich') + '\nreturn khoangLich;')();

/* Ngày mốc viết dạng (năm, thángJS, ngày) — tháng của JS đếm từ 0, viết nhầm là cả bài vô nghĩa. */
function moc(y, m, d, gio) { return new Date(y, m - 1, d, gio === undefined ? 12 : gio, 0, 0); }

console.log('── Kỳ lịch: tuần ───────────────────────────────────────');

/* 09/09/2026 là THỨ TƯ. Tuần này = Thứ Hai 07/09 tới nay; phần còn lại của tuần chưa xảy ra
   nên bị cắt ở hôm nay — ô "Đến ngày" mà hiện 13/09 thì trông như app lấy sai dữ liệu. */
teq('Thứ Tư · tuanNay cắt ở hôm nay', { tu: '2026-09-07', den: '2026-09-09' }, khoangLich('tuanNay', moc(2026, 9, 9)));
teq('Thứ Hai · tuanNay = đúng một ngày', { tu: '2026-09-07', den: '2026-09-07' }, khoangLich('tuanNay', moc(2026, 9, 7)));

/* 🔴 BẪY SỐ 1 — CHỦ NHẬT. getDay() của JS trả 0 cho Chủ nhật, lấy thẳng làm số ngày lùi thì
   Chủ nhật thành đầu tuần: tu = 13/09, mất trọn sáu ngày trước đó. Phải (getDay()+6)%7. */
teq('Chủ nhật · vẫn thuộc tuần bắt đầu Thứ Hai', { tu: '2026-09-07', den: '2026-09-13' }, khoangLich('tuanNay', moc(2026, 9, 13)));
teq('Chủ nhật · tuanTruoc', { tu: '2026-08-31', den: '2026-09-06' }, khoangLich('tuanTruoc', moc(2026, 9, 13)));
teq('Thứ Tư · tuanTruoc (trọn 7 ngày, không cắt)', { tu: '2026-08-31', den: '2026-09-06' }, khoangLich('tuanTruoc', moc(2026, 9, 9)));

/* Giao năm: Thứ Hai 05/01/2026, tuần trước nằm hẳn trong năm 2025. */
teq('tuanTruoc vắt qua giao thừa', { tu: '2025-12-29', den: '2026-01-04' }, khoangLich('tuanTruoc', moc(2026, 1, 5)));

console.log('── Kỳ lịch: tháng ──────────────────────────────────────');

teq('thangNay cắt ở hôm nay', { tu: '2026-09-01', den: '2026-09-12' }, khoangLich('thangNay', moc(2026, 9, 12)));
teq('thangNay ngày cuối tháng', { tu: '2026-09-01', den: '2026-09-30' }, khoangLich('thangNay', moc(2026, 9, 30)));
teq('thangNay ngày 1', { tu: '2026-09-01', den: '2026-09-01' }, khoangLich('thangNay', moc(2026, 9, 1)));
teq('thangTruoc (31 ngày)', { tu: '2026-08-01', den: '2026-08-31' }, khoangLich('thangTruoc', moc(2026, 9, 12)));

/* 🔴 BẪY SỐ 2 — CUỐI THÁNG. Cộng 30 ngày hay ghim 31 thì tháng 2 sai ngay. new Date(y, m, 0)
   trả ngày CUỐI của tháng trước và tự biết 28/29/30/31. */
teq('thangTruoc · tháng 2 thường', { tu: '2026-02-01', den: '2026-02-28' }, khoangLich('thangTruoc', moc(2026, 3, 15)));
teq('thangTruoc · tháng 2 nhuận', { tu: '2024-02-01', den: '2024-02-29' }, khoangLich('thangTruoc', moc(2024, 3, 15)));
teq('thangNay · tháng 2 nhuận, ngày cuối', { tu: '2024-02-01', den: '2024-02-29' }, khoangLich('thangNay', moc(2024, 2, 29)));

/* Giao năm: tháng 1 lùi một tháng phải ra tháng 12 NĂM TRƯỚC, không phải "tháng 0". */
teq('thangTruoc vắt qua giao thừa', { tu: '2025-12-01', den: '2025-12-31' }, khoangLich('thangTruoc', moc(2026, 1, 20)));

console.log('── Cửa sổ trượt 7/30 ngày đi chung một cửa ─────────────');

/* Hai nút cũ giờ cũng đi qua khoangLich — một đường tính ngày, không phải hai. */
teq('7 ngày = hôm nay lùi 6', { tu: '2026-09-06', den: '2026-09-12' }, khoangLich('7', moc(2026, 9, 12)));
teq('30 ngày = hôm nay lùi 29', { tu: '2026-08-14', den: '2026-09-12' }, khoangLich('30', moc(2026, 9, 12)));
teq('7 ngày vắt qua đầu tháng', { tu: '2026-08-27', den: '2026-09-02' }, khoangLich('7', moc(2026, 9, 2)));
t('"" (Tự chọn ngày) không đụng vào ô ngày', khoangLich('', moc(2026, 9, 12)) === null);
t('giá trị lạ trả null chứ không trả NaN', khoangLich('linh tinh', moc(2026, 9, 12)) === null);

console.log('── Bất biến quét 400 ngày liên tiếp ────────────────────');

/* 🔴 BẪY SỐ 3 — GIỜ TRONG NGÀY. Trừ bằng mili-giây (t - n*86400000) thì đêm chuyển giờ hoặc
   mốc lúc 23:30 sẽ trượt sang ngày khác. Quét cả năm, mọi kỳ, hai mốc giờ. */
let loi = 0, loiVd = null;
for (let i = 0; i < 400; i++) {
	const d = new Date(2025, 10, 1 + i);
	for (const gio of [0, 23]) {
		const m = new Date(d.getFullYear(), d.getMonth(), d.getDate(), gio, 30);
		const homNay = m.getFullYear() + '-' + ('0' + (m.getMonth() + 1)).slice(-2) + '-' + ('0' + m.getDate()).slice(-2);
		for (const ky of ['tuanNay', 'tuanTruoc', 'thangNay', 'thangTruoc', '7', '30']) {
			const k = khoangLich(ky, m);
			if (!k || k.tu > k.den || k.den > homNay) { loi++; if (!loiVd) { loiVd = { ky, homNay, gio, k }; } }
		}
	}
}
t('mọi kỳ đều có tu ≤ den và den ≤ hôm nay (800 lượt)', loi === 0, loiVd);

/* Kỳ đã qua thì KHÔNG được cắt — cắt cả tuanTruoc là mất số liệu mà chẳng ai thấy. */
let dayDu = 0;
for (let i = 0; i < 60; i++) {
	const k = khoangLich('tuanTruoc', new Date(2026, 0, 1 + i, 9));
	const so = Math.round((new Date(k.den) - new Date(k.tu)) / 86400000) + 1;
	if (so === 7) { dayDu++; }
}
teq('tuanTruoc luôn trọn 7 ngày (60 mốc)', 60, dayDu);

console.log('── Đấu dây trên màn hình ───────────────────────────────');

/* Bản 0.18.0 mất một lượt vì luật đúng mà CHỖ GỌI thiếu. Đếm chỗ gọi, đừng chỉ tin hàm đúng. */
teq('thanh lọc cổng có ô Kỳ', 1, (src.match(/oKy\(id\('ky'\)/g) || []).length);
teq('màn Sao Kê Ngân Hàng có ô Kỳ', 1, (src.match(/id="fKy"/g) || []).length);
t('ô Kỳ tĩnh được đổ option từ KY_LICH', /dungOKyTinh\(\)/.test(src) && /\['fKy'\]\.forEach/.test(src));
t('dungOKyTinh được gọi lúc vào app', /dungOKyTinh\(\);/.test(src.slice(src.indexOf('function vaoApp('), src.indexOf('function doLogin('))));

/* Gõ tay ngày mà nhãn kỳ vẫn "Tuần này" là app nói dối — và lần Làm mới sau đó nhảy về tuần,
   mất khoảng anh vừa gõ. Cả 4 ô ngày (2 màn × Từ/Đến) đều phải nhả nhãn. */
teq('2 ô ngày màn cổng nhả nhãn kỳ', 2, (src.match(/onchange="cgBoKy\(/g) || []).length);
teq('2 ô ngày màn sao kê nhả nhãn kỳ', 2, (src.match(/onchange="skBoKy\(\)"/g) || []).length);

/* "Làm mới" xoá mọi ô lọc — bỏ sót fKy thì ngày trống mà nhãn còn tên kỳ. */
t('Làm mới xoá luôn ô Kỳ', /'fKw','fKy'\]/.test(src));

/* Hai nút cũ phải đi qua đúng một đường tính ngày, không được mọc lại bản sao thứ hai. */
teq('chỉ một nơi tính ngày cho màn cổng', 1, (src.match(/khoangLich\(ky\)/g) || []).length);
t('cgDatNgay chỉ còn là vỏ gọi cgDoiKhoang', /function cgDatNgay\(nguon, luiNgay\)\{\s*\n\s*cgDoiKhoang\(/.test(src));

/* Phiên bản: header và hằng VER phải bằng nhau — lệch là màn hình khoe số bản không có thật. */
const php = fs.readFileSync('vhcp-saoke/vhcp-saoke.php', 'utf8');
const vHeader = (php.match(/^\s*\*\s*Version:\s*([0-9.]+)/m) || [])[1];
const vConst = (php.match(/const VER\s*=\s*'([0-9.]+)'/) || [])[1];
teq('Version: header == const VER', vHeader, vConst);

console.log('────────────────────────────────────────────────────────');
if (TRUOT.length) {
	console.log('✗ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length) + ':');
	TRUOT.forEach(function (x) { console.log('    · ' + x); });
	process.exit(1);
}
console.log('✓ ĐẠT — ' + DAT + ' mục.');
