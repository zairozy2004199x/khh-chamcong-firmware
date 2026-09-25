/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * SAO KÊ 0.52.0 — MÀN HÌNH NẠP BÙ THEO ĐỢT: chia đúng đợt, tiến độ đúng, gộp kết quả đúng, đợt lỗi nói rõ tới đâu.
 * Anh Thắng 25/09/2026: "nạp file bù rất lâu và hay lỗi" — 7.816 dòng / 24.261 dòng trong MỘT yêu cầu.
 * Chạy: node tools/test/kiem-saoke-nap-bu-gop.js
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-saoke/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; console.log('  ✓ ' + n); } else { TRUOT.push(n); console.log('  ✗ ' + n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function bocHam(ten) {
	const i = src.indexOf('function ' + ten + '(');
	if (i < 0) { throw new Error('không thấy hàm ' + ten); }
	let sau = 0, dem = 0, j = src.indexOf('{', i);
	for (let k = j; k < src.length; k++) { if (src[k] === '{') { dem++; } else if (src[k] === '}') { dem--; if (dem === 0) { sau = k + 1; break; } } }
	return src.slice(i, sau);
}
const m = /var CG_DOT = (\d+);/.exec(src); t('CG_DOT khai trong app.html = 400', m && m[1] === '400', m && m[1]);

/* google.script.run giả: mỗi lượt gọi trả kết quả theo hàm `traLoi(phan)`; gọi lại bất đồng bộ như thật. */
const GOI = [];
let traLoi = null;
const google = { script: { get run() {
	const st = { s: null, f: null };
	const p = {
		withSuccessHandler(fn) { st.s = fn; return p; },
		withFailureHandler(fn) { st.f = fn; return p; },
		napFileCongTx(pin, nguon, phan, tenFile) {
			GOI.push({ pin, nguon, n: phan.length, tenFile });
			setTimeout(function () { const r = traLoi(phan, GOI.length); if (r instanceof Error) { st.f(r); } else { st.s(r); } }, 0);
		}
	};
	return p;
} } };
const M = new Function('google', 'PIN', 'var CG_DOT = ' + m[1] + ';\n' + bocHam('cgGopKqTx') + '\n' + bocHam('cgNapTxDot') + '\nreturn { cgGopKqTx: cgGopKqTx, cgNapTxDot: cgNapTxDot };')(google, '1234');

function kq(themMoi, extra) { return Object.assign({ ok: true, nguon: 'vietqr', tenFile: 'f.xlsx', soDongFile: 400, themMoi, trungBoQua: 400 - themMoi, tongTienThem: themMoi * 1000, boQuaDong: 0, khongNgay: 0, khongTien: 0, khongMa: 0, chuaRoMay: 1, vaMay: 2, khongThanhCong: 0, cuaHangMoi: [], dsMaCH: [], thieuBanDo: [], chuaGan: [], soChuaGan: 0 }, extra || {}); }

console.log('── 1. Gộp kết quả hai đợt ─────────────────────────────────');
let g = M.cgGopKqTx(null, kq(3, { dsMaCH: ['A', 'B'], thieuBanDo: ['X'], cuaHangMoi: ['CS1'], chuaGan: [{ ten: 'POSH Huế', soGd: 2, tien: 50000, maCH: 'A', tenMay: 'AE HUẾ 04' }, { ten: 'JP PNT', soGd: 1, tien: 1000, maCH: 'B', tenMay: 'JP PNT 4' }], soChuaGan: 2 }));
g = M.cgGopKqTx(g, kq(5, { dsMaCH: ['B', 'C'], thieuBanDo: ['X', 'Y'], cuaHangMoi: ['CS2'], chuaGan: [{ ten: 'POSH Huế', soGd: 4, tien: 70000, maCH: 'A2', tenMay: 'AE HUẾ 05' }], soChuaGan: 1 }));
t('🔴 số đếm CỘNG: themMoi 8, trùng 792, tiền 8.000, vaMay 4, soDongFile 800', g.themMoi === 8 && g.trungBoQua === 792 && g.tongTienThem === 8000 && g.vaMay === 4 && g.soDongFile === 800, g);
t('danh sách HỢP bỏ trùng: dsMaCH A,B,C (soMaCH 3) · thiếu bản đồ X,Y (2) · cuaHangMoi CS1,CS2', JSON.stringify(g.dsMaCH) === '["A","B","C"]' && g.soMaCH === 3 && g.soThieuBanDo === 2 && JSON.stringify(g.cuaHangMoi) === '["CS1","CS2"]', g);
t('🔴 "chưa quy được" gộp theo nhãn: POSH Huế 6 GD / 120.000đ, JP PNT giữ nguyên, xếp tiền giảm dần, soChuaGan 2', g.chuaGan.length === 2 && g.chuaGan[0].ten === 'POSH Huế' && g.chuaGan[0].soGd === 6 && g.chuaGan[0].tien === 120000 && g.chuaGan[1].ten === 'JP PNT' && g.soChuaGan === 2, g.chuaGan);
t('máy chủ cũ (không có dsMaCH/chuaGan) vẫn gộp được, không nổ', (function () { const x = M.cgGopKqTx(null, { ok: true, themMoi: 1, trungBoQua: 0, tongTienThem: 5, soMaCH: 7 }); return x.themMoi === 1 && x.chuaGan.length === 0 && x.soMaCH === 7; })());

(async function () {
	console.log('── 2. Chia đợt 1.000 dòng ────────────────────────────────');
	const goi = []; for (let i = 0; i < 1000; i++) { goi.push(['22-09-2026 10:00:00', '20000', 'VPB' + i, '', 'PaymentForOrder', 'A', '', 'Thành công']); }
	const tienDo = []; GOI.length = 0;
	traLoi = function (phan) { return kq(phan.length === 200 ? 7 : 1, { dsMaCH: ['A'] }); };
	const r = await new Promise(function (res) { M.cgNapTxDot('vietqr', goi, 'f.xlsx', function (a, b, c, d) { tienDo.push([a, b, c, d]); }, res, function (msg) { res(new Error(msg)); }); });
	t('🔴 3 lượt gọi: 400 · 400 · 200 dòng, đúng PIN/nguồn/tên file', GOI.length === 3 && GOI.map(x => x.n).join(',') === '400,400,200' && GOI.every(x => x.pin === '1234' && x.nguon === 'vietqr' && x.tenFile === 'f.xlsx'), GOI);
	t('tiến độ: (400,1000,1,3) (800,1000,2,3) (1000,1000,3,3)', JSON.stringify(tienDo) === '[[400,1000,1,3],[800,1000,2,3],[1000,1000,3,3]]', tienDo);
	t('xong: gộp đủ ba đợt (themMoi 1+1+7 = 9, soDongFile 1200 do kq giả), dsMaCH A', !(r instanceof Error) && r.themMoi === 9 && r.soMaCH === 1, r);

	console.log('── 3. Đợt 2 lỗi → nói rõ đã nạp tới 400, không gọi đợt 3 ──');
	GOI.length = 0; const loi = [];
	traLoi = function (phan, lan) { return lan === 2 ? new Error('Máy chủ trả về trang HTML thay vì dữ liệu (HTTP 504)') : kq(1); };
	await new Promise(function (res) { M.cgNapTxDot('vietqr', goi, 'f.xlsx', function () {}, function () { res(); }, function (msg, daNap, tong) { loi.push([msg, daNap, tong]); res(); }); });
	t('🔴 loi(msg, 400, 1000) và dừng — không gọi tiếp', loi.length === 1 && loi[0][1] === 400 && loi[0][2] === 1000 && /HTTP 504/.test(loi[0][0]) && GOI.length === 2, { loi, goi: GOI.length });
	GOI.length = 0; const loi2 = [];
	traLoi = function () { return { ok: false, error: 'Nguồn không hợp lệ' }; };
	await new Promise(function (res) { M.cgNapTxDot('zalo', goi.slice(0, 10), 'f', function () {}, res, function (msg, daNap, tong) { loi2.push([msg, daNap, tong]); res(); }); });
	t('máy chủ trả ok:false cũng thành loi(…, 0, 10)', loi2.length === 1 && loi2[0][1] === 0 && loi2[0][2] === 10 && loi2[0][0] === 'Nguồn không hợp lệ');

	console.log('── 4. 0 dòng → xong ngay với kết quả rỗng, không gọi máy chủ ──');
	GOI.length = 0;
	const r0 = await new Promise(function (res) { M.cgNapTxDot('vietqr', [], 'f', function () {}, res, function (msg) { res(new Error(msg)); }); });
	t('không gọi máy chủ, gop rỗng hợp lệ', GOI.length === 0 && !(r0 instanceof Error) && r0.themMoi === 0 && Array.isArray(r0.chuaGan), r0);

	console.log(''); if (TRUOT.length) { console.log('🔴 TRƯỢT: ' + TRUOT.length); process.exit(1); } console.log('✓ SẠCH — ' + DAT + ' phép');
})();
