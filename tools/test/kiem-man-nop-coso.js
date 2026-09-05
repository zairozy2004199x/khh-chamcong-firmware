/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN "TÔI ĐANG CẦM": BẢNG CƠ SỞ CHƯA NỘP · TÍCH · TỔNG ĐỔI THEO Ô TÍCH
 *
 * Anh Thắng 05/09/2026: *"chỗ phần tôi đang cầm tiền: hiện cơ sở nào chưa nộp · khi nhân viên
 * nộp hoặc gửi bill thì tích vào sẽ nộp cơ sở nào · nếu tích ít hơn thì sẽ hiện lại tổng số tiền
 * cơ sở tích"*.
 *
 * 🔴 CON SỐ PHẢI ĐỔI THEO Ô TÍCH, NGAY LÚC TÍCH. Một dòng chữ "nộp toàn bộ 12.610.000đ" đứng yên
 *    trong khi người ta vừa bỏ tích hai cơ sở là con số nói dối — và nó nói dối về TIỀN, ngay
 *    trên cái nút sắp bấm.
 *
 * ⚠️ BỐC HÀM THẬT TỪ MÃ NGUỒN RA CHẠY với một DOM giả — không chép lại, không dò chuỗi. Cái khoá
 *    thật (lọc theo cơ sở lúc gắn dòng) nằm ở máy chủ và có bài riêng: `kiem-nop-theo-coso.php`.
 *
 * Chạy: node tools/test/kiem-man-nop-coso.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const tr = fs.readFileSync('vhcp-ghe/includes/class-vhg-trang.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ---------- Bốc mã thật ---------- */
function boc(dau, het) {
	const i = tr.indexOf(dau); if (i < 0) return '';
	const j = tr.indexOf(het, i + dau.length); return j < 0 ? '' : tr.slice(i, j);
}
const fTay = boc("  function nopO_(){", "\n  /* ---- QUỸ: quản lý xác nhận đã nhận");
t('bốc được cả cụm buộc tay nút Nộp', '' !== fTay, fTay.slice(0, 80));
if (!fTay) { console.log('\n✗ không bốc được — dừng.'); process.exit(1); }

/* ---------- DOM giả ---------- */
function lamO(cs, tien) {
	return { checked: true, _cs: cs, _tien: tien, onchange: null, disabled: false,
		getAttribute: function (k) { return 'data-cs' === k ? this._cs : ('data-tien' === k ? String(this._tien) : null); } };
}
function chay(dsCoso, kich) {
	const os = dsCoso.map(function (c) { return lamO(c.coso, c.tong); });
	const canh = { innerHTML: '' };
	const nut  = { disabled: false, onclick: null };
	const all  = { checked: true, onchange: null };
	const gc   = { value: 'ghi chú thử' };
	const daGui = [], daHoi = [], daAlert = [];
	let batHoi = true;
	const f = new Function('OS', 'CANH', 'NUT', 'ALL', 'GC', 'DAGUI', 'DAHOI', 'DAALERT', 'HOI', 'DQUY',
		'function L(vi,en){ return vi; }\n'
		+ 'function tien(n){ return (Number(n)||0).toLocaleString("vi-VN")+"đ"; }\n'
		+ 'var ban=false, D={quy:{toi:DQUY}};\n'
		+ 'function lam(v,d){ DAGUI.push([v,d]); }\n'
		+ 'function confirm(m){ DAHOI.push(m); return HOI(); }\n'
		+ 'function alert(m){ DAALERT.push(m); }\n'
		+ 'var document={ querySelectorAll:function(s){ return ".nop-cs"===s ? OS : []; },'
		+ ' getElementById:function(id){ return {"nop-canh":CANH,"nop-ok":NUT,"nop-cs-all":ALL,"nop-gc":GC}[id]||null; } };\n'
		+ fTay + '\nreturn { capNhat: nopCapNhat_, chon: nopChon_ };');
	const api = f(os, canh, nut, all, gc, daGui, daHoi, daAlert, function () { return batHoi; },
		{ tong: dsCoso.reduce(function (a, c) { return a + c.tong; }, 0) });
	const ra = { os: os, canh: canh, nut: nut, all: all, daGui: daGui, daHoi: daHoi, daAlert: daAlert,
		api: api, dapUng: function (v) { batHoi = v; } };
	if (kich) kich(ra);
	return ra;
}

const CS3 = [
	{ coso: 'AM-TP', tong: 2000000 },
	{ coso: 'GO TRƯỜNG CHINH', tong: 4610000 },
	{ coso: 'VC-TĐỨC', tong: 6000000 },
];

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. MẶC ĐỊNH TÍCH HẾT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Nộp cả vòng vẫn là việc thường ngày nhất, nên nó phải là MỘT cú bấm; bỏ tích là việc của ca
   lẻ. Mặc định bỏ trống thì mỗi lần nộp là một lần đi tích từng dòng, và sớm muộn có người tích
   sót một cơ sở mà không nhận ra. */
const r0 = chay(CS3);
teq('🔴 mở màn là tích sẵn cả ba cơ sở', 3, r0.api.chon().length);
t('đối chứng: dòng chú thích có chữ', r0.canh.innerHTML.length > 20, r0.canh.innerHTML);
t('🔴 tích hết -> nói "toàn bộ" và đúng tổng', r0.canh.innerHTML.indexOf('toàn bộ') >= 0
	&& r0.canh.innerHTML.indexOf('12.610.000') >= 0, r0.canh.innerHTML);
t('nút Nộp mở', false === r0.nut.disabled, r0.nut.disabled);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. BỎ TÍCH -> TỔNG ĐỔI THEO
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const r1 = chay(CS3, function (r) { r.os[2].checked = false; r.api.capNhat(); });
t('🔴 bỏ tích VC-TĐỨC -> tổng còn 6.610.000', r1.canh.innerHTML.indexOf('6.610.000') >= 0, r1.canh.innerHTML);
t('🔴 và KHÔNG còn nói "toàn bộ"', r1.canh.innerHTML.indexOf('toàn bộ') < 0, r1.canh.innerHTML);
t('nói rõ đang nộp 2/3 cơ sở', r1.canh.innerHTML.indexOf('2/3') >= 0, r1.canh.innerHTML);
/* 🔴 NÓI RA CẢ HAI CON SỐ. Chỉ nói con số nộp thì người ta không biết mình vừa để lại gì trên
   tay — mà đúng cái "còn lại" ấy là thứ họ phải nhớ để đi nộp nốt. */
t('🔴 và nói ra phần CÒN CẦM LẠI: 6.000.000',
	r1.canh.innerHTML.indexOf('6.000.000') >= 0 && r1.canh.innerHTML.indexOf('đang cầm') >= 0, r1.canh.innerHTML);
t('ô "tích hết" tự bỏ tích theo', false === r1.all.checked, r1.all.checked);

const r2 = chay(CS3, function (r) { r.os[0].checked = false; r.os[1].checked = false; r.api.capNhat(); });
t('🔴 chỉ còn một cơ sở -> tổng đúng 6.000.000', r2.canh.innerHTML.indexOf('6.000.000') >= 0, r2.canh.innerHTML);
t('và phần còn cầm là 6.610.000', r2.canh.innerHTML.indexOf('6.610.000') >= 0, r2.canh.innerHTML);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. KHÔNG TÍCH GÌ -> KHOÁ NÚT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* 🔴 CHO BẤM RỒI ĐỂ MÁY CHỦ TRẢ VỀ "không có đồng nào" LÀ BẮT NGƯỜI TA ĐI MỘT VÒNG MẠNG để biết
   một chuyện màn hình đã biết sẵn. Và tệ hơn: gửi mảng rỗng lên thì máy chủ hiểu là NỘP TẤT —
   đúng thứ người ta vừa cố tránh. */
const r3 = chay(CS3, function (r) { r.os.forEach(function (o) { o.checked = false; }); r.api.capNhat(); });
t('🔴 không tích gì -> KHOÁ nút Nộp', true === r3.nut.disabled, r3.nut.disabled);
t('và nói rõ phải tích ít nhất một cơ sở', r3.canh.innerHTML.indexOf('ít nhất một') >= 0, r3.canh.innerHTML);
/* Bấm được (bằng cách nào đó) thì cũng không gửi gì. */
r3.nut.onclick();
teq('🔴 bấm khi chưa tích -> KHÔNG gửi gì đi', 0, r3.daGui.length);
t('và có nhắc', r3.daAlert.length === 1, r3.daAlert);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. BẤM NỘP — GỬI ĐÚNG CƠ SỞ ĐÃ TÍCH
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const r4 = chay(CS3, function (r) { r.os[2].checked = false; r.api.capNhat(); r.nut.onclick(); });
teq('🔴 gửi đúng một việc', 1, r4.daGui.length);
teq('và là việc nộp', 'nop_tao', r4.daGui[0] && r4.daGui[0][0]);
teq('🔴 kèm ĐÚNG hai cơ sở đã tích, không kèm cơ sở bỏ tích',
	['AM-TP', 'GO TRƯỜNG CHINH'], r4.daGui[0] && r4.daGui[0][1].coso);
teq('và mang theo ghi chú đã gõ', 'ghi chú thử', r4.daGui[0] && r4.daGui[0][1].ghi_chu);
/* Câu hỏi phải nói ra SỐ TIỀN và TÊN từng cơ sở — bấm nộp là một quyết định về tiền. */
t('🔴 câu hỏi nói ra số tiền sắp nộp', (r4.daHoi[0] || '').indexOf('6.610.000') >= 0, r4.daHoi);
t('🔴 và liệt kê tên từng cơ sở', (r4.daHoi[0] || '').indexOf('AM-TP') >= 0
	&& (r4.daHoi[0] || '').indexOf('GO TRƯỜNG CHINH') >= 0, r4.daHoi);
t('🔴 không nhắc cơ sở đã bỏ tích', (r4.daHoi[0] || '').indexOf('VC-TĐỨC') < 0, r4.daHoi);

/* Bấm rồi TỪ CHỐI ở câu hỏi: không gửi gì. */
const r5 = chay(CS3, function (r) { r.dapUng(false); r.nut.onclick(); });
teq('🔴 bấm rồi từ chối ở câu hỏi -> không gửi gì đi', 0, r5.daGui.length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. Ô "TÍCH HẾT"
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const r6 = chay(CS3, function (r) {
	r.os[0].checked = false; r.api.capNhat();
	r.all.checked = true; r.all.onchange.call(r.all);
});
teq('🔴 bấm "tích hết" -> tích lại đủ ba', 3, r6.api.chon().length);
t('và dòng chú thích quay về "toàn bộ"', r6.canh.innerHTML.indexOf('toàn bộ') >= 0, r6.canh.innerHTML);
const r7 = chay(CS3, function (r) { r.all.checked = false; r.all.onchange.call(r.all); });
teq('bỏ "tích hết" -> bỏ tích tất', 0, r7.api.chon().length);
t('và nút bị khoá', true === r7.nut.disabled, null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. KHÔNG CÓ BẢNG TÍCH -> NỘP TẤT NHƯ CŨ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Chỉ một cơ sở (bảng tích không được bày), hoặc máy chủ chưa trả `theo_coso` — đường cũ phải
   chạy y nguyên, và KHÔNG được gửi khoá `coso`: gửi mảng rỗng thì máy chủ hiểu là nộp tất, nhưng
   để nó tự rơi vào nhánh ấy thì một hôm bảng tích hỏng sẽ lặng lẽ thành nộp tất. */
const r8 = chay([], function (r) { r.nut.onclick(); });
teq('🔴 không có bảng tích -> vẫn gửi được', 1, r8.daGui.length);
teq('và KHÔNG kèm khoá coso (máy chủ hiểu là nộp tất)', undefined, r8.daGui[0] && r8.daGui[0][1].coso);
t('câu hỏi nói "toàn bộ"', (r8.daHoi[0] || '').indexOf('toàn bộ') >= 0, r8.daHoi);
t('🔴 và không đụng gì tới nút / dòng chú thích khi không có ô tích nào',
	false === r8.nut.disabled, r8.nut.disabled);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 7. KHỐI DỰNG: BẢNG CHỈ HIỆN KHI CÓ HƠN MỘT CƠ SỞ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* Một cơ sở duy nhất thì bảng tích chỉ là một dòng luôn tích sẵn — thêm một thứ để nhìn mà không
   thêm một lựa chọn nào. */
const iDung = tr.indexOf("( toi.theo_coso || [] ).length > 1");
t('🔴 bảng tích chỉ bày khi có HƠN MỘT cơ sở', iDung > 0);
const khoiDung = iDung > 0 ? tr.slice(iDung, tr.indexOf("+ '<div class=\"act\" style=\"margin-top:12px\">'", iDung)) : '';
t('mỗi dòng mang tên cơ sở và số tiền để tay xử lý đọc lại',
	/data-cs="' \+ esc\(c\.coso\)/.test(khoiDung) && /data-tien="' \+ c\.tong/.test(khoiDung), khoiDung.slice(0, 300));
/* 🔴 Tên cơ sở do người gõ — thả thẳng vào thuộc tính là mở cửa cho một cái tên phá vỡ cả bảng. */
t('🔴 tên cơ sở được thoát trước khi vào thuộc tính', /esc\(c\.coso\)/.test(khoiDung), null);
t('mặc định tích sẵn', /data-tien="' \+ c\.tong \+ '" checked/.test(khoiDung), khoiDung.slice(0, 400));
/* Nói ra tiền đến từ đâu (ngăn ghế / tại quầy / báo cáo) — lệch quỹ thì câu hỏi đầu tiên luôn là
   "tiền này ở đâu ra". */
t('mỗi dòng nói rõ tiền đến từ nguồn nào',
	/c\.tu_ghe > 0/.test(khoiDung) && /c\.tu_quay > 0/.test(khoiDung) && /c\.tu_bao_cao > 0/.test(khoiDung), null);

/* ---------- KẾT ---------- */
if (TRUOT.length) {
	console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
	TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
	process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: tích cơ sở nào thì tổng và cú bấm theo đúng cơ sở ấy.');
