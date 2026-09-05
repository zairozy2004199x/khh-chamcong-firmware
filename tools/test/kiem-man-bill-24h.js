/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN 24H: NHÓM THEO CƠ SỞ · KHỐI BILL · MẤT NÚT SỬA KHI ĐÃ KHOÁ
 *
 * Anh Thắng 05/09/2026: *"chỗ phần báo cáo trong 24h sửa được sẽ hiện ra báo cáo từng cơ sở mà
 * nhân viên đã nộp, đồng thời chỗ đó sẽ có thêm (bổ sung bill chuyển khoản) · khi nhân viên add
 * bill và xác nhận đã nộp thì báo cáo đó sẽ không sửa được nữa"*.
 *
 * ⚠️ ĐÂY LÀ BÀI CANH GIAO DIỆN, KHÔNG PHẢI CANH KHOÁ. Cái khoá thật nằm ở máy chủ và có bài
 *    riêng (`kiem-bill-khoa-baocao.php`) — cổng `bc_edit`/`bc_supplement` nhận lệnh từ bất cứ ai
 *    có PIN, nên giấu nút chỉ là dọn mắt. Bài này canh phần dọn mắt ấy có đúng không: bày một
 *    cái nút rồi để máy chủ chối là bắt người ta gõ lại cả báo cáo mới biết mình không được sửa.
 *
 * ⚠️ BỐC KHỐI DỰNG THẬT RA CHẠY rồi soi HTML/DOM nó sinh ra — không chép lại, không dò chuỗi.
 *
 * Chạy: node tools/test/kiem-man-bill-24h.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const P  = 'vhcp-ghe/includes/class-vhg-trang.php';
const tr = fs.readFileSync(P, 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ---------- DOM giả tí hon: đủ cho mấy hàm này dựng cây thẻ ---------- */
function lamEl(tag) {
	const e = {
		tagName: String(tag).toUpperCase(), children: [], attrs: {}, dataset: {},
		style: { cssText: '', _set: {} }, className: '', _text: '', href: '', src: '', type: '',
		disabled: false, multiple: false, files: null, value: '', placeholder: '', accept: '', id: '',
		onclick: null, onchange: null,
		appendChild: function (c) { this.children.push(c); return c; },
		setAttribute: function (k, v) { this.attrs[k] = String(v); },
		getAttribute: function (k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
		addEventListener: function () {},
		get textContent() { return this._text; },
		set textContent(v) { this._text = v == null ? '' : String(v); this.children = []; },
	};
	e.style.setProperty = function () {};
	return e;
}
/* Gom toàn bộ chữ + tên lớp + thuộc tính của một cây thẻ thành một chuỗi để soi. */
function doc(e) {
	if (!e) return '';
	let s = ' <' + e.tagName + ' class="' + (e.className || '') + '" id="' + (e.id || '') + '"';
	for (const k in e.attrs) { s += ' ' + k + '="' + e.attrs[k] + '"'; }
	if (e.src) s += ' src="' + e.src + '"';
	if (e.href) s += ' href="' + e.href + '"';
	if (e.placeholder) s += ' ph="' + e.placeholder + '"';
	s += '>' + (e._text || '');
	e.children.forEach(function (c) { s += doc(c); });
	return s + '</' + e.tagName + '>';
}
/* Tìm mọi thẻ trong cây thoả một điều kiện. */
function tim(e, f) {
	let ra = [];
	if (!e) return ra;
	if (f(e)) ra.push(e);
	e.children.forEach(function (c) { ra = ra.concat(tim(c, f)); });
	return ra;
}
function nut(e, chu) { return tim(e, function (x) { return 'BUTTON' === x.tagName && (x._text || '').indexOf(chu) >= 0; }); }

/* ---------- Bốc mã thật ---------- */
function boc(dau, het) {
	const i = tr.indexOf(dau);
	if (i < 0) return '';
	const j = tr.indexOf(het, i + dau.length);
	return j < 0 ? '' : tr.slice(i, j);
}
const fHuyHieu = boc('function huyHieuNop_(rp){', '\n  function recentItem(rp){');
const fItem    = boc('function recentItem(rp){', '\n  /* ══');
const fBill    = boc('function khoiBill_(rp){', '\n  /* Ô chọn 1 ảnh cho màn Sửa 24h');
const fPicker  = boc('function anhPicker_(cls,nhan){', '\n  function theGheSua(');
const fLoad    = boc('function loadRecent(){', '\n  function recentItem(');

t('bốc được huy hiệu trạng thái', '' !== fHuyHieu, fHuyHieu.slice(0, 60));
t('bốc được khối một báo cáo',    '' !== fItem,    fItem.slice(0, 60));
t('bốc được khối bill',           '' !== fBill,    fBill.slice(0, 60));
t('bốc được ô chọn ảnh',          '' !== fPicker,  fPicker.slice(0, 60));
t('bốc được khối tải danh sách',  '' !== fLoad,    fLoad.slice(0, 60));
if (!fItem || !fBill || !fHuyHieu || !fPicker) {
	console.log('\n✗ không bốc đủ khối — dừng.'); process.exit(1);
}

/* ---------- Chạy recentItem() + khoiBill_() thật ---------- */
function veThe(rp, batBam) {
	const daGui = [];
	const f = new Function('rp', 'DOCU', 'GOI', 'XACNHAN', 'DAGUI', 'LOADLAI',
		'function el(tg,c,tx){ var e=DOCU.createElement(tg); if(c)e.className=c; if(tx!=null)e.textContent=tx; return e; }\n'
		+ 'function money(n){ return (Number(n)||0).toLocaleString("vi-VN"); }\n'
		+ 'function inp(cls,ph,isText){ var e=el("input",cls); e.type="text"; e.placeholder=ph||""; return e; }\n'
		+ 'function confirm(){ return XACNHAN; }\n'
		+ 'function docAnhTu_(f,cb){ cb([{name:"b.jpg",dataUrl:"x"}]); }\n'
		+ 'function goi(v,d,cb){ DAGUI.push([v,d]); cb({ok:true,message:"xong"}); }\n'
		+ 'function loadRecent(){ LOADLAI.n++; }\n'
		+ 'var CEL_ANH_DEM=0;\n'
		+ fPicker + '\n' + fHuyHieu + '\n' + fItem + '\n' + fBill + '\n'
		+ 'return recentItem(rp);');
	const lai = { n: 0 };
	const the = f(rp, { createElement: lamEl }, null, batBam !== false, daGui, lai);
	return { the: the, daGui: daGui, veLai: lai };
}

const RP_CAM = { reportId: 'RPT-1', date: '05/09', locName: 'GO TRƯỜNG CHINH', rows: 3,
	total: 1200000, cash: 900000, nopTt: 'dang_cam', khoa: 0, billAnh: [], chairs: [] };
const RP_KHOA = { reportId: 'RPT-2', date: '05/09', locName: 'GO TRƯỜNG CHINH', rows: 3,
	total: 1200000, cash: 900000, nopTt: 'cho_xac_nhan', khoa: 1,
	billAnh: ['/a/1.jpg', '/a/2.jpg'], billLuc: '2026-09-05 10:00:00', billGhiChu: 'VCB 999', chairs: [] };

const raCam = veThe(RP_CAM), raKhoa = veThe(RP_KHOA);
t('đối chứng: dựng được thẻ báo cáo', doc(raCam.the).length > 200, doc(raCam.the).slice(0, 150));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. CHƯA KHOÁ: CÓ NÚT SỬA, CÓ KHỐI BILL
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('🔴 chưa khoá thì CÓ nút Sửa', 1, nut(raCam.the, 'Sửa').length);
teq('🔴 và có nút Xác nhận đã nộp', 1, nut(raCam.the, 'Xác nhận đã nộp').length);
t('có ô chọn ảnh bill', tim(raCam.the, function (x) { return 'INPUT' === x.tagName && 'file' === x.type; }).length >= 1, null);
/* Ô ghi chú cho mã giao dịch — bàn phím CHỮ, vì người ta gõ tên ngân hàng vào đó. */
t('có ô ghi chú / mã giao dịch',
	tim(raCam.the, function (x) { return (x.placeholder || '').indexOf('Mã giao dịch') >= 0; }).length === 1, null);
/* 🔴 Số tiền mặt PHẢI NỘP đứng ngay trên ô chọn ảnh — cái bill phải khớp con số này. QR đã về
   tài khoản công ty rồi, không nằm trong đây. */
t('🔴 nói ra SỐ TIỀN MẶT phải nộp, không phải tổng doanh thu',
	doc(raCam.the).indexOf('900.000') >= 0 && doc(raCam.the).indexOf('Tiền mặt phải nộp') >= 0, doc(raCam.the));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. ĐÃ KHOÁ: MẤT NÚT SỬA, MẤT CẢ ĐƯỜNG BẤM NỘP LẦN NỮA
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('🔴 đã khoá thì KHÔNG còn nút Sửa', 0, nut(raKhoa.the, 'Sửa').length);
teq('🔴 và không còn nút Xác nhận đã nộp', 0, nut(raKhoa.the, 'Xác nhận đã nộp').length);
teq('🔴 và không còn ô chọn ảnh nào', 0,
	tim(raKhoa.the, function (x) { return 'INPUT' === x.tagName && 'file' === x.type; }).length);
/* Bằng chứng phải bày lại được — đó là thứ người ta cần khi đi hỏi kế toán. */
teq('🔴 bày lại đủ ảnh bill đã đính', 2,
	tim(raKhoa.the, function (x) { return 'IMG' === x.tagName; }).length);
t('ảnh bấm mở được ra tab mới để soi rõ',
	tim(raKhoa.the, function (x) { return 'A' === x.tagName && '/a/1.jpg' === x.href; }).length === 1, null);
t('🔴 nói rõ đã khoá và khoá lúc nào',
	doc(raKhoa.the).indexOf('2026-09-05 10:00:00') >= 0 && doc(raKhoa.the).indexOf('không sửa được') >= 0, doc(raKhoa.the));
t('🔴 và chỉ đường: nhờ kế toán mở lại', doc(raKhoa.the).indexOf('kế toán') >= 0, null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. BÁO CÁO TOÀN QR — KHÔNG CÓ GÌ ĐỂ NỘP
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Bày một cái nút "Xác nhận đã nộp" cho báo cáo không có đồng tiền mặt nào là mời người ta bấm
   một cú chắc chắn bị máy chủ chối. */
const raQr = veThe(Object.assign({}, RP_CAM, { cash: 0 }));
teq('🔴 báo cáo toàn QR: KHÔNG bày nút Xác nhận đã nộp', 0, nut(raQr.the, 'Xác nhận đã nộp').length);
t('và nói rõ vì sao', doc(raQr.the).indexOf('không cần nộp') >= 0, doc(raQr.the));
t('nhưng nút Sửa vẫn còn (vẫn sửa được số liệu)', nut(raQr.the, 'Sửa').length === 1, null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. BẤM NÚT XÁC NHẬN — GỬI ĐÚNG VIỆC, VÀ HỎI LẠI TRƯỚC
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 MỘT CÚ BẤM LÀM BA VIỆC (lưu bill · mở lượt nộp · KHOÁ báo cáo) nên câu hỏi phải NÓI RA cả
   ba. Người ta đồng ý với thứ mình đọc được, không phải thứ mã nguồn làm. */
const iHoi = fBill.indexOf('if(!confirm(');
t('🔴 có hỏi lại trước khi bấm', iHoi > 0);
const cauHoi = iHoi > 0 ? fBill.slice(iHoi, fBill.indexOf('return;', iHoi)) : '';
t('🔴 câu hỏi nói ra: báo cáo sẽ KHOÁ', cauHoi.indexOf('KHOÁ') >= 0, cauHoi);
t('🔴 nói ra: kế toán sẽ thấy một lượt nộp', cauHoi.indexOf('Đã nhận') >= 0, cauHoi);
t('nói ra: ảnh bill được đính vào', cauHoi.indexOf('bill') >= 0, cauHoi);

/* Bấm rồi TỪ CHỐI ở câu hỏi: không được gửi gì đi. */
const raHuy = veThe(RP_CAM, false);
nut(raHuy.the, 'Xác nhận đã nộp')[0].onclick();
teq('🔴 bấm rồi từ chối ở câu hỏi -> không gửi gì đi', 0, raHuy.daGui.length);

/* Có ảnh, đồng ý: gửi đúng việc, đúng mã báo cáo. */
const raOk = veThe(RP_CAM, true);
const nOk = nut(raOk.the, 'Xác nhận đã nộp')[0];
tim(raOk.the, function (x) { return 'INPUT' === x.tagName && 'file' === x.type; })
	.forEach(function (i) { i.files = [{ name: 'bill.jpg' }]; });
nOk.onclick();
teq('🔴 đồng ý -> gửi đúng một việc', 1, raOk.daGui.length);
teq('và là việc đính bill + nộp', 'bc_nop_bill', raOk.daGui[0] && raOk.daGui[0][0]);
teq('🔴 mang đúng mã báo cáo của thẻ ấy', 'RPT-1', raOk.daGui[0] && raOk.daGui[0][1].report_id);
t('và có kèm ảnh', !!(raOk.daGui[0] && raOk.daGui[0][1].anh && raOk.daGui[0][1].anh.qr), raOk.daGui[0]);
teq('🔴 gửi xong thì vẽ lại cả khối (mất nút Sửa ngay, không đợi tải trang sau)', 1, raOk.veLai.n);

/* 🔴 KHÔNG ẢNH THÌ KHÔNG BẤM ĐƯỢC — chốt ở nút, để khỏi đi một vòng mạng mới biết. Máy chủ
   chốt lại lần nữa (nop_bill), nhưng cái khoá này đổi lấy một tờ bằng chứng, nên nút cũng phải
   biết luật. */
const raThieu = veThe(RP_CAM, true);
nut(raThieu.the, 'Xác nhận đã nộp')[0].onclick();
teq('🔴 chưa chọn ảnh -> KHÔNG gửi gì đi', 0, raThieu.daGui.length);
t('và nói rõ thiếu ảnh', doc(raThieu.the).indexOf('ít nhất 1 ảnh') >= 0, doc(raThieu.the));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. HUY HIỆU TRẠNG THÁI
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function nhan(rp) {
	const f = new Function('rp', 'DOCU',
		'function el(tg,c,tx){ var e=DOCU.createElement(tg); if(c)e.className=c; if(tx!=null)e.textContent=tx; return e; }\n'
		+ fHuyHieu + '\nreturn huyHieuNop_(rp)._text;');
	return f(rp, { createElement: lamEl });
}
t('🔴 đang cầm tiền -> nói "Đang cầm"', nhan({ nopTt: 'dang_cam' }).indexOf('Đang cầm') >= 0, nhan({ nopTt: 'dang_cam' }));
t('🔴 đã nộp -> nói đang chờ kế toán', nhan({ nopTt: 'cho_xac_nhan' }).indexOf('chờ kế toán') >= 0, nhan({ nopTt: 'cho_xac_nhan' }));
t('🔴 kế toán đã nhận -> nói đã nhận', nhan({ nopTt: 'da_nhan' }).indexOf('đã nhận') >= 0, nhan({ nopTt: 'da_nhan' }));
/* Trạng thái lạ (dữ liệu cũ, cột rỗng) không được im lặng thành "đã nhận" — đó là nói rằng tiền
   đã về quầy trong khi không ai biết nó ở đâu. */
t('🔴 trạng thái lạ thì rơi về "Đang cầm", không rơi về "đã nhận"',
	nhan({ nopTt: 'gi-do-la' }).indexOf('Đang cầm') >= 0, nhan({ nopTt: 'gi-do-la' }));
t('đã khoá thì huy hiệu mang ổ khoá', nhan({ nopTt: 'cho_xac_nhan', khoa: 1 }).indexOf('🔒') >= 0, null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. NHÓM THEO CƠ SỞ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Anh Thắng: *"sẽ hiện ra báo cáo từng cơ sở mà nhân viên đã nộp"*. Một người trực hai ba cơ sở
   mà danh sách phẳng thì phải đọc từng dòng mới biết cơ sở nào đã gửi. */
const iNhom = fLoad.indexOf('var thuTu=[], theoCs={};');
t('🔴 có gom báo cáo theo cơ sở', iNhom > 0, fLoad.slice(0, 200));
const khoiNhom = iNhom > 0 ? fLoad.slice(iNhom, fLoad.indexOf('pager.textContent', iNhom)) : '';
/* Bốc đúng đoạn gom ra CHẠY — canh thứ tự và cách gom, không dò chữ. */
function gom(ds) {
	const wrapl = lamEl('div');
	const f = new Function('trangDs', 'wrapl', 'DOCU', 'RECENT',
		'function el(tg,c,tx){ var e=DOCU.createElement(tg); if(c)e.className=c; if(tx!=null)e.textContent=tx; return e; }\n'
		+ 'function recentItem(rp){ return RECENT(rp); }\n'
		+ khoiNhom + '\nreturn wrapl;');
	return f(ds, wrapl, { createElement: lamEl }, function (rp) { const e = lamEl('div'); e.textContent = rp.reportId; return e; });
}
const raGom = gom([
	{ reportId: 'A1', locName: 'GO TRƯỜNG CHINH' },
	{ reportId: 'B1', locName: 'AM-TP' },
	{ reportId: 'A2', locName: 'GO TRƯỜNG CHINH' },
]);
const chu = doc(raGom);
t('đối chứng: gom chạy được', chu.indexOf('A1') >= 0 && chu.indexOf('B1') >= 0, chu.slice(0, 200));
teq('🔴 ba báo cáo hai cơ sở -> ĐÚNG HAI nhóm', 2, raGom.children.length);
t('🔴 mỗi nhóm có tiêu đề mang tên cơ sở',
	chu.indexOf('GO TRƯỜNG CHINH') >= 0 && chu.indexOf('AM-TP') >= 0, chu);
t('🔴 tiêu đề đếm số báo cáo của cơ sở đó', chu.indexOf('2 báo cáo') >= 0 && chu.indexOf('1 báo cáo') >= 0, chu);
/* 🔴 HAI BÁO CÁO CÙNG CƠ SỞ PHẢI VÀO CÙNG MỘT NHÓM, kể cả khi bị một cơ sở khác chen giữa —
   đây chính là ca gom sai hay xảy ra nhất (chỉ so với hàng liền trước). */
const nhomA = raGom.children[0];
teq('🔴 A1 và A2 nằm chung một nhóm dù B1 chen giữa', 3, nhomA.children.length);  // 1 tiêu đề + 2 thẻ
t('và đúng hai thẻ ấy', doc(nhomA).indexOf('A1') >= 0 && doc(nhomA).indexOf('A2') >= 0
	&& doc(nhomA).indexOf('B1') < 0, doc(nhomA));
/* Thứ tự nhóm theo lần xuất hiện đầu tiên — giữ nguyên trật tự thời gian mà máy chủ đã xếp. */
t('🔴 nhóm giữ thứ tự xuất hiện, không tự xếp lại theo bảng chữ cái',
	doc(raGom.children[0]).indexOf('GO TRƯỜNG CHINH') >= 0, doc(raGom.children[0]).slice(0, 120));
/* Báo cáo thiếu tên cơ sở không được làm gãy nhóm hay đẻ ra nhóm tên rỗng. */
const raRong = gom([{ reportId: 'X1', locName: '' }]);
t('🔴 báo cáo thiếu tên cơ sở vẫn có nhóm, và nhóm có tên đọc được',
	1 === raRong.children.length && doc(raRong).indexOf('chưa rõ cơ sở') >= 0, doc(raRong));

/* ---------- KẾT ---------- */
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: 24h nhóm theo cơ sở, có khối bill, khoá rồi thì mất nút Sửa.');
