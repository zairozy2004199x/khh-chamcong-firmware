/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ẢNH BÁO CÁO: CỘT CASH PHẢI LÀ "THỰC THU TIỀN MẶT".
 *
 * Anh Thắng 16/09/2026: *"cột cash là cột thực thu — đang lấy nhầm giá trị"*.
 *
 * ==============================================================================================
 * 🔴 KHÔNG PHẢI LỖI HIỂN THỊ — ẢNH ĐANG NÓI KHÁC CƠ SỞ DỮ LIỆU.
 *
 *    Máy chủ (`VHG_BaoCao::luu()`) lưu:   tien_mat = thực thu · tong = tien_mat + qr
 *    `baoCaoAnh_()` thì lấy thực thu gán vào `actual` rồi TRỪ QR LẦN NỮA.
 *
 *    Ghế VW-GP-6 ngày 16/09: thực thu 150.000đ, QR 80.000đ. Ảnh in ra "Actual 150.000 · Cash
 *    70.000" — mất 80.000đ tiền mặt, và mất luôn 80.000đ khỏi tổng doanh thu của ghế.
 *
 * ⚠️ Và HAI ẢNH CỦA CÙNG MỘT BÁO CÁO VÊNH NHAU: `baoCaoAnhTuRp_()` (khối "Báo cáo trong 24h")
 *    đọc thẳng số máy chủ nên in đúng 150.000. Cùng một ghế, hai con số. Bài này canh cả hai
 *    đường cùng ra một kết quả — vênh nhau lần nữa là đỏ.
 *
 * Chạy: node tools/test/kiem-ghe-anh-bao-cao-tien-mat.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-ghe/includes/class-vhg-trang.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }

/* Bốc nguyên văn đoạn tính tiền trong baoCaoAnh_ ra chạy — không chép lại công thức vào đây, vì
   bài thử chép công thức là bài canh chính nó. */
function doanTinh() {
	const i = src.indexOf('var kx=(KICHXA[r.chairCode]||{tien:0});');
	if (i < 0) { throw new Error('không thấy đoạn tính tiền trong baoCaoAnh_'); }
	const j = src.indexOf('if(b!==\'\') tB+=b;', i);
	if (j < 0) { throw new Error('không thấy chỗ kết đoạn tính tiền'); }
	return src.slice(i, j);
}
/* ⚠️ Đoạn bốc ra dùng b/a được khai TRƯỚC nó trong hàm gốc — dựng lại ở đây cho đúng thứ tự. */
function tinh(r, dv) {
	const mo = 'var b=(r.meterBefore===\'\'||r.meterBefore==null)?\'\':Number(r.meterBefore);'
		+ 'var a=(r.meterAfter===\'\'||r.meterAfter==null)?\'\':Number(r.meterAfter);';
	const f = new Function('r', 'dv', 'KICHXA', mo + doanTinh() + 'return { actual: actual, cash: cash, qr: qr };');
	return f(r, dv, {});
}

/* ── Ca thật: VW-GP-6 ngày 16/09/2026 ────────────────────────────────────────────────────────
   Chỉ số 7.169 -> 7.213 (44 × 10.000 = 440.000 theo máy), nhưng QR đếm sai chỉ số nên nhân viên
   gõ Thực thu tiền mặt = 150.000 và QR = 80.000. */
const GP6 = { meterBefore: 7169, meterAfter: 7213, qr: 80000, actualOverride: 150000, chairCode: 'GP6' };
const r1 = tinh(GP6, 10000);
t('🔴 cột Cash = đúng số Thực thu đã gõ', r1.cash === 150000, r1);
t('🔴 và Actual = Thực thu + QR, đúng công thức máy chủ (tong = tien_mat + qr)', r1.actual === 230000, r1);
t('không trừ QR lần thứ hai (lỗi cũ ra 70.000)', r1.cash !== 70000, r1);

/* ── Không gõ Thực thu thì giữ nguyên nếp cũ ─────────────────────────────────────────────────── */
const BT = { meterBefore: 7417, meterAfter: 7447, qr: 20000, actualOverride: null, chairCode: 'X' };
const r2 = tinh(BT, 10000);
t('không gõ Thực thu: Actual vẫn tính theo chỉ số', r2.actual === 300000, r2);
t('không gõ Thực thu: Cash = Actual − QR như cũ', r2.cash === 280000, r2);

/* Chuỗi rỗng KHÔNG phải là "có gõ" — ô để trống gửi lên '' chứ không phải null. */
const R3 = tinh({ meterBefore: 100, meterAfter: 110, qr: 0, actualOverride: '', chairCode: 'X' }, 10000);
t('ô Thực thu để trống ("") không bị hiểu là đã gõ', R3.actual === 100000, R3);

/* Gõ Thực thu = 0 (thu 0đ tiền mặt, cả ca chỉ có QR) PHẢI được nhận — số 0 là một câu trả lời. */
const R4 = tinh({ meterBefore: 100, meterAfter: 110, qr: 100000, actualOverride: 0, chairCode: 'X' }, 10000);
t('🔴 Thực thu = 0 vẫn được nhận, không rơi về công thức', R4.cash === 0 && R4.actual === 100000, R4);

/* ── Hai đường vẽ ảnh phải cùng một kết quả ──────────────────────────────────────────────────
   `baoCaoAnhTuRp_()` đọc thẳng số máy chủ (c.actual/c.cash/c.qr). Máy chủ cho ca GP-6 là
   tien_mat=150.000, tong=230.000 — tức đúng bằng con số đường kia vừa tính ra. */
t('🔴 đường vẽ từ báo cáo cũ vẫn đọc thẳng số máy chủ, không tính lại',
	src.indexOf('var actual=Number(c.actual||0), qr=Number(c.qr||0), cash=Number(c.cash||0);') > 0);
t('🔴 hai đường ra cùng một con số cho ca GP-6', r1.cash === 150000 && r1.actual === 230000);

console.log(TRUOT.length ? ('✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):\n  · ' + TRUOT.join('\n  · '))
	: ('✓ SẠCH — ' + DAT + ' phép.'));
process.exit(TRUOT.length ? 1 : 0);
