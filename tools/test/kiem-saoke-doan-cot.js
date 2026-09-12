/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ — ĐOÁN CỘT PHẢI ĐÚNG TRÊN **TIÊU ĐỀ THẬT** CỦA CỔNG VIỆT QR
 *
 * Anh Thắng 12/09/2026 gửi thẳng hai file kết xuất thật: `transactions_…xlsx` và
 * `store_export_…xlsx`. Đưa tiêu đề thật vào bộ đoán cột thì lòi ra một lỗi mà ba cái ảnh chụp
 * màn hình trước đó không thể thấy được.
 *
 * 🔴 LỖI: cột Nội dung bị đoán thành CỘT TIỀN.
 *    Tiêu đề tiền là **"Số tiền đến (VND)"**. Từ khoá của cột Nội dung có `'nd'`, mà `'(vnd)'`
 *    CHỨA `'nd'` — bộ đoán cũ dò chuỗi con thô nên nó khớp ngay ở cột 2.
 *
 *    Hậu quả KHÔNG dừng ở chọn nhầm ô. Mọi dòng sẽ mang "nội dung" = `20000`; `may_hop_le()`
 *    thấy chuỗi ấy hợp lệ (một từ, ngắn, toàn chữ số) và trả về nó như TÊN MÁY. Cả kỳ tiền chui
 *    vào một cái máy tên **"20000"** — thứ không tồn tại. Đúng loại lỗi câm mà chú thích của
 *    `may_hop_le()` đã cảnh báo, nhưng vào bằng cửa khác: cửa đoán cột.
 *
 * ⚠️ BÀI NÀY CHẠY CHÍNH HÀM TRONG `app.html`, không chép lại luật. Chép ra đây là bài thử canh
 *    chính nó, và nó sẽ xanh kể cả khi app đã hỏng.
 *
 * Chạy: node tools/test/kiem-saoke-doan-cot.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-saoke/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', mong === thuc, thuc); }

/* Bốc NGUYÊN VĂN một hàm trong app.html ra rồi dựng lại — cân bằng ngoặc để lấy đủ thân hàm. */
function bocHam(ten) {
	const i = src.indexOf('function ' + ten + '(');
	if (i < 0) { throw new Error('không thấy hàm ' + ten + ' trong app.html'); }
	let sau = 0, dem = 0, j = src.indexOf('{', i);
	for (let k = j; k < src.length; k++) {
		if (src[k] === '{') { dem++; }
		else if (src[k] === '}') { dem--; if (dem === 0) { sau = k + 1; break; } }
	}
	return src.slice(i, sau);
}

/* Hai hàm phụ mà `cgDoanCotTx` gọi tới ở bước "dò theo hình dạng". */
const moiTruong = bocHam('cgThoiDiemO') + '\n' + bocHam('cgSoO') + '\n'
	+ bocHam('cgDoanCotTx') + '\n' + bocHam('cgDoanCotBD') + '\n'
	+ 'return { cgDoanCotTx: cgDoanCotTx, cgDoanCotBD: cgDoanCotBD };';
const M = new Function(moiTruong)();

/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * TIÊU ĐỀ THẬT — chép đúng từ hai file anh Thắng gửi 12/09/2026.
 * ⚠️ Đừng "dọn cho gọn": cái sai nằm ở đúng mấy chữ này ("(VND)", "Mã tham chiếu" đứng trước
 *    "Mã đơn hàng", "Tên cửa hàng" đứng trước "Mã cửa hàng").
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
const TX_HEAD = ['STT','Thời gian TT','Số tiền đến (VND)','Số tiền đi (VND)','Loại','Trạng thái',
	'Mã tham chiếu','Mã đơn hàng','Mã điểm bán','Mã cửa hàng','Tài khoản nhận','Thời gian tạo',
	'Nội dung TT','Ghi chú','Loại giao dịch'];
const TX_ROWS = [TX_HEAD,
	['1','12-09-2026 15:26:09','20000','0','Giao dịch đến','Thành công','0552rdiA-8C3tbvkQN',
	 'VPBfnWDdWQUB4','VVB701528','M4QMOQLG7Y','8640107702 - BIDV','12-09-2026 15:25:52',
	 'VQR263777080WFB3 AMBT 03','-','QR giao dịch'],
	['2','12-09-2026 15:26:05','20000','0','Giao dịch đến','Thành công','0832SLQ6-8C3tbkIvu',
	 'VPB8lH3IqKWi1','VVB279793','EIJQ4R7BW4','8640107702 - BIDV','12-09-2026 15:25:49',
	 'VQR26377707DKJWE VHM 01','-','QR giao dịch']];
const BD_HEAD = ['STT','Tên cửa hàng','Mã cửa hàng','Mã điểm bán','Tên điểm bán',
	'Doanh thu ngày','Số lượng GD ngày','Ngày tạo'];

const dTx = M.cgDoanCotTx(TX_HEAD, TX_HEAD.length, TX_ROWS);
const dBd = M.cgDoanCotBD(BD_HEAD, BD_HEAD.length);

/* ---- bảng Giao dịch thanh toán ---- */
teq('Thời điểm  -> "Thời gian TT"',      1,  dTx.txThoiDiem);
teq('Số tiền    -> "Số tiền đến (VND)"', 2,  dTx.txTien);
/* 🔴 Mã giao dịch phải là MÃ ĐƠN HÀNG (VPB…), không phải Mã tham chiếu: khoá chống trùng bên
   mình là mã đơn hàng. Chọn nhầm là mọi dòng thành "mới" -> TIỀN ĐẾM HAI LẦN. */
teq('🔴 Mã giao dịch -> "Mã đơn hàng", KHÔNG phải "Mã tham chiếu"', 7, dTx.txMa);
teq('Mã tham chiếu -> đúng cột của nó', 6, dTx.txRef);
/* 🔴 ĐÂY LÀ PHÉP QUAN TRỌNG NHẤT CỦA BÀI: bản cũ trả về 2 (cột tiền) vì '(vnd)' chứa 'nd'. */
teq('🔴 Nội dung  -> "Nội dung TT", KHÔNG dính vào "(VND)"', 12, dTx.txND);
t('🔴 và tuyệt đối không trỏ vào cột tiền', dTx.txND !== dTx.txTien, dTx);
teq('Mã cửa hàng -> đúng cột', 9,  dTx.txMaCH);
teq('Mã điểm bán -> đúng cột', 8,  dTx.txMaDiem);
teq('Trạng thái  -> đúng cột', 5,  dTx.txTT);

/* ---- bảng Danh sách cửa hàng ---- */
/* ⚠️ "Tên cửa hàng" đứng TRƯỚC "Mã cửa hàng" trong file thật — thứ tự ngược với trực giác. */
teq('Mã cửa hàng -> cột 2 (dù "Tên cửa hàng" đứng trước)', 2, dBd.bdMa);
teq('Tên cửa hàng (= tên máy) -> cột 1', 1, dBd.bdTen);
teq('Mã điểm bán -> cột 3',  3, dBd.bdMaD);
teq('Tên điểm bán -> cột 4', 4, dBd.bdTenD);
t('🔴 hai ô Mã/Tên cửa hàng KHÔNG cùng trỏ một cột', dBd.bdMa !== dBd.bdTen, dBd);

/* ---- và bộ đoán không được vỡ với file lạ ---- */
const dLa = M.cgDoanCotTx(['a','b','c'], 3, [['a','b','c']]);
t('tiêu đề lạ thì trả -1, không nổ', dLa.txND === -1 && dLa.txMaCH === -1, dLa);

console.log('');
if (TRUOT.length) {
	console.log('TRƯỢT ' + TRUOT.length + ':');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	console.log('ĐẠT: ' + DAT);
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép trên TIÊU ĐỀ THẬT của hai file kết xuất Việt QR.');
