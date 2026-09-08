/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG "ĐÃ QUYẾT TOÁN" PHẢI HIỆN CẢ ĐƠN ĐÃ XUẤT MISA — anh Thắng 07/09/2026
 *
 * *"hiện đơn đã quyết toán"* — gửi kèm ảnh bảng trắng trơn, badge "0 đơn", ô "Tuần / kỳ riêng"
 * rỗng không một tuần nào.
 *
 * 🔴 VÌ SAO BẢNG RỖNG. Bảng ấy trước chỉ nhận đúng chữ `'Đã quyết toán'`. Nhưng xuất MISA xong
 *    là đơn TỰ CHUYỂN sang `'Đã xuất MISA'` — nên mỗi đơn quyết toán rồi, hễ kế toán xuất file,
 *    LÀ BIẾN MẤT khỏi bảng. Càng làm đúng quy trình thì bảng càng trắng. Và đúng những đơn ấy
 *    mới là thứ anh cần tra: *"lọc theo tuần để TÌM ĐƠN CŨ"* — đơn cũ thì xuất MISA từ lâu rồi.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY, KHÔNG CHÉP LUẬT VÀO ĐÂY. Chép là bản chép xanh mãi kể cả khi app
 *    đã đổi — đúng cái bẫy mà bộ thử này phải tránh.
 *
 * Chạy: node tools/test/kiem-hien-don-da-quyet-toan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DON  = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n', i);
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}

/* Bảy trạng thái của luồng, bốc từ CHÍNH app.html — thêm bớt trạng thái là danh sách này theo. */
const TT = (function () {
  const m = /var TT_LUONG=\[([^\]]+)\]/.exec(HTML);
  if (!m) { console.error('HỎNG: không thấy TT_LUONG trong app.html'); process.exit(1); }
  return m[1].split(',').map(function (s) { return s.trim().replace(/^'|'$/g, ''); });
})();
teq('bốc đủ 7 trạng thái của luồng', 7, TT.length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. CHỐT `_daQT()` — CHẠY THẬT TRÊN CẢ BẢY TRẠNG THÁI
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnDaQT = bocDong('_daQT');
t('bốc được chốt _daQT()', fnDaQT.length > 20, fnDaQT);
const daQT = new Function(fnDaQT + '\nreturn _daQT;')();

teq('"Đã quyết toán" → là đơn đã quyết toán xong', true, daQT({ trangThai: 'Đã quyết toán' }));
teq('🔴 "Đã xuất MISA" → CŨNG là đơn đã quyết toán xong', true, daQT({ trangThai: 'Đã xuất MISA' }));
TT.filter(function (s) { return s !== 'Đã quyết toán' && s !== 'Đã xuất MISA'; })
  .forEach(function (s) { teq('"' + s + '" → CHƯA quyết toán xong', false, daQT({ trangThai: s })); });
teq('không có đơn → false, không nổ', false, !!daQT(null));
teq('đơn thiếu trạng thái → false', false, !!daQT({}));

/* ⚠️ HAI BÊN PHẢI NÓI CÙNG MỘT CÂU. Máy chủ đã chốt ranh giới này ở `VHCP_Don::TT_CHOT` từ
   trước; màn hình đi lệch khỏi nó chính là lỗi đang sửa. Đối chiếu danh sách chứ không tin
   rằng hai chỗ tình cờ giống nhau. */
const ttChot = (function () {
  const m = /const TT_CHOT = array\(([^)]*)\)/.exec(DON);
  return m ? m[1].split(',').map(function (s) { return s.trim().replace(/^'|'$/g, ''); }).filter(Boolean) : [];
})();
teq('máy chủ chốt đúng hai trạng thái', ['Đã quyết toán', 'Đã xuất MISA'], ttChot);
t('🔴 màn hình và máy chủ khớp nhau từng trạng thái',
  TT.every(function (s) { return daQT({ trangThai: s }) === (ttChot.indexOf(s) >= 0); }),
  TT.filter(function (s) { return daQT({ trangThai: s }) !== (ttChot.indexOf(s) >= 0); }));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. BỘ LỌC DỰNG BẢNG — CHẠY ĐÚNG DÒNG LỌC BỐC TỪ renderQTList()
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnRender = bocHam('renderQTList');
t('bốc được renderQTList()', fnRender.length > 1000, fnRender.length);

/* Bốc NGUYÊN dòng dựng biến `xong` rồi chạy nó — không viết lại điều kiện ở đây. */
const dongXong = (function () {
  const m = /\n\s*var xong=\(BOOT\.dons\|\|\[\]\)\.filter\([^\n]*\);/.exec(fnRender);
  return m ? m[0].trim() : '';
})();
t('bốc được dòng dựng bảng "Đã quyết toán"', dongXong.length > 40, dongXong);

function locXong(dons, locRieng) {
  return new Function('BOOT', '_daQT', '_qtLocXong', dongXong + '\nreturn xong;')(
    { dons: dons }, daQT, locRieng || function () { return true; });
}
const KHO = [
  { maDon: 'D1', ky: '01/09-07/09', trangThai: 'Đã quyết toán' },
  { maDon: 'D2', ky: '25/08-31/08', trangThai: 'Đã xuất MISA' },
  { maDon: 'D3', ky: '01/09-07/09', trangThai: 'Chờ quyết toán' },
  { maDon: 'D4', ky: '01/09-07/09', trangThai: 'Đã cấp tạm ứng' },
  { maDon: 'D5', ky: '18/08-24/08', trangThai: 'Đã xuất MISA' },
];
teq('🔴 bảng nhận CẢ đơn đã xuất MISA', ['D1', 'D2', 'D5'], locXong(KHO).map(function (d) { return d.maDon; }));
teq('không rước nhầm đơn còn chờ / đang cầm tiền', [],
  locXong(KHO).filter(function (d) { return d.trangThai === 'Chờ quyết toán' || d.trangThai === 'Đã cấp tạm ứng'; }));

/* Ca CHÍNH XÁC của anh Thắng: quyết toán xong rồi xuất MISA hết — bảng phải còn đơn, không
   được trắng. Đây là phép bắt đúng lỗi đã cắn. */
const KHO_XUAT_HET = KHO.map(function (d) {
  return d.trangThai === 'Đã quyết toán' ? { maDon: d.maDon, ky: d.ky, trangThai: 'Đã xuất MISA' } : d;
});
teq('🔴 xuất MISA HẾT thì bảng vẫn còn đơn (đúng ảnh anh gửi)', 3, locXong(KHO_XUAT_HET).length);

/* Chốt trạng thái nới ra rồi thì ô lọc tuần RIÊNG của bảng vẫn phải cắt được — nới một cái mà
   buông cái kia là bảng bày hết mọi tuần, đúng thứ ô lọc sinh ra để tránh. */
const chiTuan = function (d) { return d.ky === '25/08-31/08'; };
teq('🔴 vẫn đi qua ô lọc tuần riêng của bảng', ['D2'],
  locXong(KHO, chiTuan).map(function (d) { return d.maDon; }));
teq('ô lọc riêng chối hết thì bảng rỗng', 0, locXong(KHO, function () { return false; }).length);

/* Bảng "xem theo tuần" cộng quỹ từng kỳ — thiếu đơn đã xuất MISA là số dư mọi tuần cũ sai. */
const dongAll = (function () {
  const m = /\n\s*var all=\(BOOT\.dons\|\|\[\]\)\.filter\([^\n]*\);/.exec(fnRender);
  return m ? m[0].trim() : '';
})();
t('bốc được dòng dựng danh sách chung của màn', dongAll.length > 40, dongAll);
const dsAll = new Function('BOOT', '_daQT', '_qtLoc', dongAll + '\nreturn all;')(
  { dons: KHO }, daQT, function () { return true; }).map(function (d) { return d.maDon; });
teq('🔴 xem theo tuần cũng đếm đơn đã xuất MISA (không thì quỹ tuần cũ sai)',
  ['D1', 'D2', 'D3', 'D5'], dsAll);
t('nhưng KHÔNG rước đơn đang cầm tiền vào danh sách chung', dsAll.indexOf('D4') < 0, dsAll);

/* Ô "Tuần / kỳ riêng" cũng phải dựng từ cùng một chốt — không thì bảng có đơn mà ô lọc rỗng. */
/* ⚠️ CANH Ý ĐỊNH, ĐỪNG GHIM NGUYÊN VĂN. Từ 1.92.0 ô này còn loại thêm đơn đang bị ẩn vì chưa
   rõ bộ phận, nên câu lệnh dài ra — mà điều phải đúng vẫn chỉ là: nó dựng từ `_daQT`. */
t('ô tuần riêng dựng từ cùng chốt _daQT', /_napKyRieng\('qtKyXong',[^;]{0,160}_daQT/.test(fnRender));
/* Ô lọc chung (tháng / tuần / cơ sở) cũng phải thấy đơn đã xuất MISA, không thì tháng cũ
   biến mất khỏi ô và không ai chọn tới được. */
/* Ô lọc chung nay dựng qua `_qtTrongMan()` (1.92.0 tách ra để chốt "ẩn đơn chưa rõ bộ phận"
   chạy được cả ở đây) — nên soi vào chính hàm ấy, đừng ghim vào cách viết cũ. */
const fnTrongMan = bocHam('_qtTrongMan');
t('bốc được _qtTrongMan()', fnTrongMan.length > 40);
t('ô lọc chung cũng đếm đơn đã xuất MISA', /_daQT\(d\)/.test(fnTrongMan), fnTrongMan);
t('và màn Quyết toán dựng ô lọc qua đúng hàm ấy', /_napLocDon\([^;]{0,80}_qtTrongMan/.test(fnRender));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. BẢNG RỖNG PHẢI NÓI VÌ SAO RỖNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnEmpty = bocHam('_qtEmptyXongText');
t('bốc được _qtEmptyXongText()', fnEmpty.length > 200, fnEmpty.length);

function chayEmpty(soHien, dons, oLoc) {
  const o = { qtEmptyXong: { textContent: 'CHUA-DAT' } };
  ['qtKyXong', 'qtThang', 'qtCoso'].forEach(function (id) { o[id] = { value: (oLoc && oLoc[id]) || '' }; });
  /* Từ 1.92.0 câu này còn kể cả ô tích "Ẩn hẳn đơn chưa rõ bộ phận" (xem
     `kiem-an-don-chua-ro-bo-phan.js`). Ở đây ô tích luôn TẮT, nên phần ấy đứng yên và mấy phép
     dưới vẫn soi đúng thứ chúng sinh ra để soi. */
  new Function('el', 'BOOT', '_daQT', '_anVaoMo', 'soHien', fnEmpty + '\n_qtEmptyXongText(soHien);')(
    function (id) { return o[id] || null; }, { dons: dons }, daQT, function () { return false; }, soHien);
  return o.qtEmptyXong.textContent;
}
teq('có đơn hiện ra → không đụng tới dòng chữ', 'CHUA-DAT', chayEmpty(3, KHO));
teq('kho SẠCH thật → nói đúng là chưa có đơn nào',
  'Chưa có đơn nào đã quyết toán.', chayEmpty(0, [{ maDon: 'X', trangThai: 'Chờ quyết toán' }]));
/* ⚠️ ĐỘT BIẾN TƯƠNG ĐƯƠNG, ghi lại để lần sau khỏi đuổi theo: dời câu gán ra trước lớp gác
   (`e.textContent=...; if(!co) return;`) KHÔNG đổi kết quả, vì mọi nhánh phía sau đều gán đè
   `e.textContent` vô điều kiện. Không có phép thử nào bắt được, và ép cho đỏ thì chỉ ghim vào
   cách viết chứ không ghim vào hành vi. */

const loiLoc = chayEmpty(0, KHO, { qtKyXong: '08/09-14/09' });
t('🔴 bị ô lọc cắt hết → nói RÕ có bao nhiêu đơn', /Có 3 đơn đã quyết toán/.test(loiLoc), loiLoc);
t('và nói RÕ ô nào đang cắt', /tuần riêng "08\/09-14\/09"/.test(loiLoc), loiLoc);
t('và chỉ đường ra', /Bỏ lọc/.test(loiLoc), loiLoc);
const loiBa = chayEmpty(0, KHO, { qtKyXong: 'K1', qtThang: 'T9/2026', qtCoso: 'TÀU ESTELLA' });
t('ba ô cùng bật thì kể đủ ba', /tuần riêng "K1"[\s\S]*tháng "T9\/2026"[\s\S]*cơ sở "TÀU ESTELLA"/.test(loiBa), loiBa);
t('không ô nào bật mà vẫn rỗng → không đổ lỗi cho ô lọc',
  !/ô lọc/.test(chayEmpty(0, KHO, {})), chayEmpty(0, KHO, {}));
t('có gọi _qtEmptyXongText sau khi vẽ bảng', /_qtEmptyXongText\(xong\.length\)/.test(fnRender));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. ĐƠN ĐÃ XUẤT MISA HIỆN RA THÌ KHÔNG ĐƯỢC BÀY NÚT DUYỆT
 *    Máy chủ vốn chối (`xac_nhan_quyet_toan_ncc`: 'Đơn chưa gửi hoặc đã xuất'), nên nút ấy chỉ
 *    dẫn tới câu báo lỗi — mà từ bản này nó nằm ngay trước mắt kế toán.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('máy chủ vẫn chối duyệt NCC trên đơn đã xuất MISA',
  /function xac_nhan_quyet_toan_ncc[\s\S]{0,400}?\$st === 'Đã xuất MISA'[\s\S]{0,80}?err\(/.test(DON));

const fnRow = bocHam('_qtRowHtml');
t('bốc được _qtRowHtml()', fnRow.length > 500, fnRow.length);
function veHang(d) {
  return new Function('el', 'esc', 'money', 'canDo', '_laChim', 'stCls', 'd',
    fnRow + '\nreturn _qtRowHtml(d, false, 9);')(
    function () { return null; },
    function (x) { return String(x == null ? '' : x); },
    function (x) { return String(Number(x) || 0); },
    function () { return true; },                      // mọi quyền đều CÓ — để nút nào bày được là bày
    function (x) { return x && x.trangThai === 'Đã cấp tạm ứng'; },
    function () { return 'st-duyet'; }, d);
}
const CO_NCC = { maDon: 'D9', ky: 'K', thucChiNCC: 500000, qtNCC: 0, chenhLech: 0, tamUng: 0, soThucMua: 0 };
function hangVoi(st) { const x = JSON.parse(JSON.stringify(CO_NCC)); x.trangThai = st; return veHang(x); }
t('đối chứng: đơn "Đã quyết toán" VẪN bày được nút Duyệt NCC', /Duyệt NCC/.test(hangVoi('Đã quyết toán')));
t('🔴 đơn "Đã xuất MISA" KHÔNG bày nút Duyệt NCC', !/Duyệt NCC/.test(hangVoi('Đã xuất MISA')), hangVoi('Đã xuất MISA'));
t('đơn "Nháp" cũng không bày', !/Duyệt NCC/.test(hangVoi('Nháp')));
t('đơn đã xuất MISA vẫn mở được Chi tiết để tra', /viewDon\('D9'\)/.test(hangVoi('Đã xuất MISA')));
t('và vẫn ghi rõ trạng thái thật lên hàng', /Đã xuất MISA/.test(hangVoi('Đã xuất MISA')));

/* ══════════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.error('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.error('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: bảng "Đã quyết toán" hiện cả đơn đã xuất MISA, rỗng thì nói vì sao rỗng.');
