/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ CỔNG — LỌC DOANH THU THEO MỘT CỬA HÀNG.
 *
 * Anh Thắng 16/09/2026: *"thêm lọc doanh thu theo cửa hàng momo"*. Bảng "Theo cửa hàng" có tháng
 * tới vài chục dòng; muốn xem riêng một gian thì phải dò mắt.
 *
 * ==============================================================================================
 * 🔴 CHỖ DUY NHẤT ĐÁNG VIẾT BÀI THỬ Ở ĐÂY LÀ CON SỐ TỔNG.
 *    Ẩn vài hàng thì nhìn là thấy. Nhưng nếu dán `d.tongFile` (tổng CẢ THÁNG) cạnh một bảng đã
 *    lọc thì con số ấy NÓI DỐI — và nói dối về tiền thì không ai kiểm lại bằng mắt được, vì trông
 *    nó vẫn là một con số hợp lý. Nên tổng phải cộng lại từ những dòng ĐANG HIỆN.
 *
 * ⚠️ BÀI NÀY CHẠY CHÍNH HÀM TRONG `app.html` trên một DOM giả, không chép lại luật — chép ra đây
 *    là bài thử canh chính nó, và nó sẽ xanh kể cả khi app đã hỏng.
 *
 * Chạy: node tools/test/kiem-saoke-loc-cua-hang.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-saoke/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }

function bocHam(ten) {
	const i = src.indexOf('function ' + ten + '(');
	if (i < 0) { throw new Error('không thấy hàm ' + ten + ' trong app.html'); }
	let sau = 0, dem = 0;
	for (let k = src.indexOf('{', i); k < src.length; k++) {
		if (src[k] === '{') { dem++; }
		else if (src[k] === '}') { dem--; if (dem === 0) { sau = k + 1; break; } }
	}
	return src.slice(i, sau);
}

/* ── DOM giả: đúng những gì hai hàm ấy đụng tới, không hơn. Dựng thừa là bệ đỡ nói dối về mức
      phụ thuộc thật của mã. ────────────────────────────────────────────────────────────────── */
/* ⚠️ Ô CHỌN PHẢI SỐNG QUA CẢ HAI THÁNG, và phải cư xử như thẻ <select> thật: gán lại `innerHTML`
   thì trình duyệt chọn lại — option nào mang `selected` thì `value` là nó, không option nào mang
   thì `value` về rỗng.
   Bản đầu của bài này dựng một ô chọn MỚI cho mỗi tháng, nên phép "đổi tháng thì nhả lọc cũ"
   xanh kể cả khi đã gỡ hẳn cửa nhả — nó đo cái bệ đỡ chứ không đo mã. Đục thử phát hiện ra.
   Lỗi ở bệ đỡ là loại tốn thời gian nhất: nó đổ tội cho đúng thứ mình đang thử (§8 CLAUDE.md). */
const SEL = {
	_html: '', value: '',
	get innerHTML() { return this._html; },
	set innerHTML(v) {
		this._html = v;
		const m = /<option value="([^"]*)" selected>/.exec(v);
		this.value = m ? m[1] : '';
	},
};
const LBL = { textContent: '' };

function dungDOM(dong) {
	const trs = dong.map(function (d) {
		const cells = [ { textContent: d.ten }, { textContent: '' }, { textContent: '' },
			{ textContent: '' }, { textContent: '' }, { textContent: d.tien } ];
		return { getAttribute: function (k) { return k === 'data-ch' ? d.ten : null; },
			style: { display: '' }, cells: cells };
	});
	global.document = {
		getElementById: function (id) {
			if (id === 'cg_momo_chLoc') { return SEL; }
			if (id === 'cg_momo_chLocTong') { return LBL; }
			return null;
		},
		querySelectorAll: function () { return trs; },
	};
	return { sel: SEL, lbl: LBL, trs: trs };
}

const moiTruong = 'var CG_LOCCH = {};\n'
	+ 'function cgId(nguon, ten){ return "cg_" + nguon + "_" + ten; }\n'
	+ 'function cgEl(nguon, ten){ return document.getElementById(cgId(nguon, ten)); }\n'
	+ 'function esc(s){ return String(s == null ? "" : s); }\n'
	+ 'function fmt(n){ return String(n); }\n'
	+ bocHam('cgDungLocCH') + '\n' + bocHam('cgLocCH') + '\n'
	+ 'return { dung: cgDungLocCH, loc: cgLocCH, state: function(){ return CG_LOCCH; } };';
const M = new Function(moiTruong)();

const DL = { theoCuaHang: [
	{ cuaHangFile: 'Tutu Train - Aeon Tân An',  cuaHangChuan: 'TUTU TÂN AN', soTien: 11340000 },
	{ cuaHangFile: 'TU TU TRAIN - AEON TÂN PHÚ', cuaHangChuan: 'TÀU TÂN PHÚ', soTien: 580000 },
	{ cuaHangFile: 'Tutu Train - Estella',       cuaHangChuan: 'TÀU ESTELLA', soTien: 48300000 },
] };
const DONG = [
	{ ten: 'Tutu Train - Aeon Tân An',   tien: '11.340.000' },
	{ ten: 'TU TU TRAIN - AEON TÂN PHÚ', tien: '580.000' },
	{ ten: 'Tutu Train - Estella',       tien: '48.300.000' },
];

/* ── 1. Dựng ô chọn ─────────────────────────────────────────────────────────────────────────── */
let D = dungDOM(DONG);
M.dung('momo', DL);
t('ô chọn có dòng "Tất cả" kèm số lượng', D.sel.innerHTML.indexOf('— Tất cả cửa hàng (3) —') >= 0);
t('có đủ ba cửa hàng trong ô chọn',
	DONG.every(function (d) { return D.sel.innerHTML.indexOf('>' + d.ten + '<') >= 0; }));
/* 🔴 Khoá lọc là TÊN Ở FILE CỔNG, không phải tên chuẩn: nhiều tên file cùng ánh xạ về một tên
   chuẩn, lọc theo tên chuẩn là chọn một gian nhưng ẩn mất chính vài dòng của nó. */
t('🔴 ô chọn dùng tên ở FILE cổng, không dùng tên chuẩn',
	D.sel.innerHTML.indexOf('TUTU TÂN AN') < 0 && D.sel.innerHTML.indexOf('Tutu Train - Aeon Tân An') >= 0);
t('chưa lọc thì không khoe con số nào', D.lbl.textContent === '');
t('chưa lọc thì mọi dòng đều hiện', D.trs.every(function (r) { return r.style.display === ''; }));

/* ── 2. Lọc một cửa hàng ────────────────────────────────────────────────────────────────────── */
D.sel.value = 'Tutu Train - Estella';
M.loc('momo');
t('chỉ còn dòng của cửa hàng đã chọn',
	D.trs[2].style.display === '' && D.trs[0].style.display === 'none' && D.trs[1].style.display === 'none');
/* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI. */
t('🔴 tổng cộng lại từ dòng ĐANG HIỆN, không lấy tổng cả tháng',
	D.lbl.textContent.indexOf('48300000') >= 0, D.lbl.textContent);
t('không lẫn tiền của cửa hàng khác vào tổng',
	D.lbl.textContent.indexOf('11340000') < 0 && D.lbl.textContent.indexOf('60220000') < 0, D.lbl.textContent);
t('nói rõ đang lọc cửa hàng nào', D.lbl.textContent.indexOf('Tutu Train - Estella') >= 0);

/* ── 3. Bỏ lọc ──────────────────────────────────────────────────────────────────────────────── */
D.sel.value = '';
M.loc('momo');
t('bỏ lọc thì mọi dòng hiện lại', D.trs.every(function (r) { return r.style.display === ''; }));
t('bỏ lọc thì không còn khoe con số lọc', D.lbl.textContent === '');

/* ── 4. Đổi tháng: cửa hàng cũ không còn ────────────────────────────────────────────────────── */
/* ⚠️ Giữ lựa chọn cũ mà nó không có trong tháng mới là BẢNG TRỐNG TRƠN không rõ lý do — người
   dùng tưởng tháng ấy không có dữ liệu. Phải tự nhả về "tất cả". */
D.sel.value = 'Tutu Train - Estella';
M.loc('momo');
const THANG_KHAC = { theoCuaHang: [ { cuaHangFile: 'VR FUN', cuaHangChuan: 'VR FUN', soTien: 115000 } ] };
D = dungDOM([ { ten: 'VR FUN', tien: '115.000' } ]);
M.dung('momo', THANG_KHAC);
t('🔴 cửa hàng đang lọc không có trong tháng mới -> tự nhả về tất cả',
	M.state().momo === '' && D.sel.value === '', { state: M.state().momo, o: D.sel.value });
t('và bảng không bị ẩn sạch', D.trs.every(function (r) { return r.style.display === ''; }));

/* ── 5. Thứ tự khối trên màn — anh Thắng 16/09/2026: *"chưa thấy lọc doanh thu theo cửa hàng"*.
   Ô lọc CÓ mà khối chứa nó nằm gần cuối trang, dưới cả phần nạp file và hai khối sửa ánh xạ:
   phải cuộn qua bốn khối mới thấy thì coi như chưa làm. Ghim thứ tự lại — số liệu trước,
   việc-phải-làm sau. ──────────────────────────────────────────────────────────────────────── */
/* ⚠️ DÒ TRONG THÂN `dungViewFile()` THÔI. app.html còn một BẢNG DỊCH ở đầu tệp liệt kê đúng mấy
   tiêu đề này ('Theo cửa hàng':'By store', …); dò trên cả tệp là trúng bảng dịch và mọi phép thứ
   tự đều vô nghĩa — bản đầu của bài này đỏ đúng vì thế. */
const THAN = (function () {
	const i = src.indexOf('function dungViewFile(');
	if (i < 0) { throw new Error('không thấy dungViewFile trong app.html'); }
	return src.slice(i, src.indexOf('\nfunction ', i + 10));
})();
const viTri = function (s) { return THAN.indexOf(s); };
const P_TAI  = viTri('📤 Tải file kết xuất');
const P_NGAY = viTri('📅 Đối soát theo ngày');
const P_CH   = viTri('Theo cửa hàng <span');
const P_AX   = viTri('🏪 Cửa hàng CHƯA ra được mã');
const P_BANK = viTri('Cục về ngân hàng trong tháng');
t('tìm thấy đủ năm khối', [P_TAI, P_NGAY, P_CH, P_AX, P_BANK].every(function (x) { return x > 0; }));
t('🔴 khối "Theo cửa hàng" (chứa ô lọc) đứng TRƯỚC hai khối sửa ánh xạ', P_CH < P_AX);
t('đối soát theo ngày nằm ngay dưới khối tải file', P_TAI < P_NGAY && P_NGAY < P_CH);
t('cục ngân hàng vẫn ở cuối', P_BANK > P_AX);

/* ── 6. Khối "Cần biết" đã bỏ, nhưng ô báo lỗi PHẢI còn ─────────────────────────────────────── */
t('🔴 màn đối soát cổng không còn dựng khối "Cần biết"',
	(function () {
		const i = src.indexOf('function veDoiSoatFile(');
		return src.slice(i, src.indexOf('\nfunction ', i + 10)).indexOf('Cần biết') < 0;
	})());
/* 🔴 Xoá luôn ô `canhBao` là lượt tải hỏng không còn chỗ nào nói ra — màn chỉ đứng im. */
t('🔴 vẫn giữ ô canhBao cho đường báo lỗi', src.indexOf("id('canhBao')") > 0 && src.indexOf('function cgBaoLoiFile(') > 0);

/* ── 7. Dải giới thiệu đầu màn đã bỏ, nhưng câu KHÔNG-NHÂN-ĐÔI-TIỀN phải còn ──────────────────
   Anh Thắng 16/09/2026: *"ẩn này đi"*. Dải ấy giải thích vì sao màn tồn tại — đọc một lần là
   biết. Nhưng nó chứa một câu không có ở đâu khác: "tải lại file cũ bao nhiêu lần cũng không
   nhân đôi tiền". Bỏ luôn câu ấy là người dùng ngần ngại nạp lại, mà ngần ngại nạp lại chính
   là cách để tháng bị thiếu ngày — bỏ một dải chữ mà đổi lấy tiền thiếu thì không đáng. */
t('🔴 không còn dải "không cho nhận webhook" ở đầu màn', THAN.indexOf('không cho nhận webhook') < 0);
/* ⚠️ Dò ĐÚNG ĐOẠN MARKUP, không dò câu chữ suông: chú thích ngay trên chỗ sửa cũng nhắc lại câu
   ấy trong ngoặc kép, nên `indexOf('không nhân đôi tiền')` trúng comment (vị trí 567) chứ không
   trúng mã — hai phép này xanh giả và phép thứ tự thì đỏ oan. Đã dính đúng một lần lúc viết. */
const CAU = THAN.indexOf('<b>tải lại file cũ bao nhiêu lần cũng không nhân đôi tiền</b>');
t('🔴 câu "không nhân đôi tiền" vẫn còn trong markup', CAU > 0);
t('và nó nằm TRONG khối tải file, chỗ sắp bấm nạp', CAU > P_TAI && CAU < P_NGAY, CAU);

console.log(TRUOT.length ? ('✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):\n  · ' + TRUOT.join('\n  · '))
	: ('✓ SẠCH — ' + DAT + ' phép.'));
process.exit(TRUOT.length ? 1 : 0);
