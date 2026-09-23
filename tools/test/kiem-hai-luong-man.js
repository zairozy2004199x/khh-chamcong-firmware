/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HAI LUỒNG ĐƠN — PHÍA MÀN HÌNH (lát 2). Bản song sinh của `kiem-hai-luong-don.php`.
 *
 * Anh Thắng 22/09/2026: *"Bên anh đó có 2 luồng. 1 luồng trực tiếp, 1 luồng gián tiếp. Trực
 * tiếp là gửi đơn đầy đủ cho kế toán và quyết toán. Còn gián tiếp là tạm ứng, duyệt tạm ứng…
 * cái đang chạy"*, và chốt: **người lập chọn luồng trên TỪNG ĐƠN**.
 *
 * =============================================================================================
 * 🔴 VÌ SAO BÀI NÀY PHẢI CHẠY THẬT, KHÔNG ĐƯỢC SOI CHỮ
 * =============================================================================================
 * Lỗi của luồng không bao giờ nổ. Hỏi nhầm trục — hỏi KHỐI ĐANG ĐỨNG thay vì LUỒNG CỦA ĐƠN —
 * thì màn vẫn vẽ ra một thanh bước đẹp đẽ, vẫn bày một cái nút bấm được; chỉ là nó thuộc về
 * một luồng khác. Người lập bấm "Gửi xin tạm ứng" cho một đơn đã tiêu tiền xong, hoặc ngồi đợi
 * một nút "💵 Đã thanh toán" không bao giờ hiện. Không một dòng lỗi nào.
 *
 * ⚠️ HAI BÊN PHẢI CÙNG LUẬT. Máy chủ (`VHCP_Don::luong_cua`) và màn (`_luongKhoi`) là hai bản
 *    của MỘT quy tắc. Lệch một vế là màn vẽ một luồng mà máy chủ không công nhận — bấm rồi ăn
 *    câu chối đọc như app hỏng. Mục 6 canh đúng chỗ ấy: so bộ khoá của cả ba bảng luồng.
 *
 * ⚠️ "RỖNG = THEO KHỐI NHƯ CŨ" LÀ MỘT LUẬT, KHÔNG PHẢI MỘT CHỖ TRỐNG. Hàng nghìn đơn lập
 *    trước 22/09/2026 không có ô luồng; chúng PHẢI chạy đúng như hôm qua. Mã lạ cũng vậy —
 *    thà chạy như cũ còn hơn rơi vào một luồng thứ ba do gõ sai mà ra.
 *
 * Chạy: node tools/test/kiem-hai-luong-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DON = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* ⚠️ BỐC THEO NEO CÓ THẬT TRONG TỆP, và đo lại độ dài ngay — bốc hụt thì mọi phép dưới chạy
   trên chuỗi rỗng và xanh rỗng. */
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
const dong = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); };
const khoi = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('};', i) + 2); };
const mang = (n) => { const i = HTML.indexOf('  var ' + n + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); };

/* ═══ 0. BỆ ĐỠ CÓ THẬT CHƯA ═════════════════════════════════════════════════════ */
['_luongKhoi', '_luongDon', '_laTrucTiep', '_tenTT', '_ttTrongLuong', '_thanhBuoc',
  '_luongBuocDs', '_nutThanhToan', 'veNdLuong', 'ndChonLuong'].forEach(function (n) {
  t('⚠️ bốc được `' + n + '()`', ham(n).length > 40, ham(n).length);
});
['LUONG_KVC', 'LUONG_CHI', 'LUONG_TT'].forEach(function (n) {
  t('⚠️ bốc được bảng `' + n + '`', khoi(n).length > 60, khoi(n).length);
});

const NEN = [dong('KHOI_LUONG_CHI'), khoi('LUONG_KVC'), khoi('LUONG_CHI'), khoi('LUONG_TT'),
  ham('_luongKhoi'), ham('_luongDon'), ham('_laTrucTiep'), ham('_tenTT'), ham('_ttTrongLuong')].join('\n');
t('⚠️ nền luồng dựng được', NEN.replace(/\s/g, '').length > 400, NEN.length);

/* Chạy nền ấy với một khối đang đứng cho trước. `esc` mượn bản tối giản — nó chỉ để nối chuỗi. */
function chay(khoiDang, than) {
  return new Function('KHOI_DANG', 'esc', NEN + '\n' + than)(khoiDang,
    function (x) { return String(x == null ? '' : x); });
}

/* ═══ 1. LUỒNG CỦA MỘT ĐƠN — ĐƠN NÓI TRƯỚC, KHỐI NÓI SAU ════════════════════════ */
const maLuong = (d) => chay('kvc', 'return _luongDon(' + JSON.stringify(d) + ');');
teq('🔴 đơn ghi "tt" → mã luồng `tt`', 'tt', maLuong({ luong: 'tt' }));
teq('🔴 đơn ghi "gt" → mã luồng `gt`', 'gt', maLuong({ luong: 'gt' }));
teq('🔴 đơn CŨ (không có ô luồng) → rỗng = "theo khối như cũ"', '', maLuong({}));
teq('   ô luồng rỗng → cũng rỗng', '', maLuong({ luong: '' }));
/* ⚠️ MÃ LẠ VỀ RỖNG, KHÔNG PHẢI VỀ 'tt'. Gõ sai một chữ mà rơi vào luồng trực tiếp là đơn bỏ
   qua sạch ba bước tạm ứng — tiền ra khỏi công ty không qua ai duyệt. */
teq('🔴 mã LẠ → rỗng (chạy như cũ), không rơi vào luồng thứ ba', '', maLuong({ luong: 'xx' }));
teq('   viết hoa vẫn nhận', 'tt', maLuong({ luong: 'TT' }));
teq('   thừa khoảng trắng vẫn nhận', 'gt', maLuong({ luong: '  gt ' }));
teq('   `null` không làm nổ', '', maLuong({ luong: null }));

const buoc = (khoiDon, luong, khoiDang) =>
  chay(khoiDang || 'kvc', 'return Object.keys(_luongKhoi(' + JSON.stringify(khoiDon) + ',' + JSON.stringify(luong) + '));');

/* 🔴 ĐƠN TRỰC TIẾP BỎ SẠCH BA BƯỚC TẠM ỨNG — đó là toàn bộ ý nghĩa của luồng này. */
const B_TT = buoc('kvc', 'tt');
t('🔴 đơn TRỰC TIẾP không có bước tạm ứng nào',
  !B_TT.some(function (b) { return /tạm ứng/i.test(b); }), B_TT);
t('   nhưng CÓ bước `Chờ quyết toán`', B_TT.indexOf('Chờ quyết toán') >= 0, B_TT);
t('   và CÓ bước `Đã thanh toán`', B_TT.indexOf('Đã thanh toán') >= 0, B_TT);
teq('   bước đầu là `Nháp` (khoá lưu trong sổ, dù chữ hiện ra là "Tạo đơn")', 'Nháp', B_TT[0]);
/* ⚠️ `Đã thanh toán` PHẢI ĐỨNG TRƯỚC `Đã xuất MISA` — thứ tự bảng chính là thứ tự thanh bước,
   đảo là màn vẽ ngược tiến độ. */
t('⚠️ `Đã thanh toán` đứng TRƯỚC `Đã xuất MISA`',
  B_TT.indexOf('Đã thanh toán') < B_TT.indexOf('Đã xuất MISA'), B_TT);

/* 🔴 ĐƠN 'gt' GIỮA KHỐI MTĐ VẪN LÀ LUỒNG QUA TẠM ỨNG — đơn nói trước, khối nói sau. */
const B_GT_MTD = buoc('mtd', 'gt');
teq('🔴 đơn "gt" giữa khối MTĐ đi luồng tạm ứng, không phải luồng chi của khối',
  Object.keys(JSON.parse(chay('kvc', 'return JSON.stringify(LUONG_KVC);'))), B_GT_MTD);
/* 🔴 ĐƠN RỖNG THÌ MỚI HỎI KHỐI. */
teq('🔴 đơn cũ (rỗng) giữa khối MTĐ → luồng chi của khối, y như trước 22/09',
  Object.keys(JSON.parse(chay('kvc', 'return JSON.stringify(LUONG_CHI);'))), buoc('mtd', ''));
teq('   đơn cũ (rỗng) giữa khối KVC → luồng KVC',
  Object.keys(JSON.parse(chay('kvc', 'return JSON.stringify(LUONG_KVC);'))), buoc('kvc', ''));
/* ⚠️ KHÔNG TRUYỀN KHỐI = LẤY KHỐI ĐANG ĐỨNG. Đường lui này nuôi dải luồng đầu màn. */
teq('⚠️ không truyền khối → lấy khối đang đứng (dải luồng dùng đường này)',
  Object.keys(JSON.parse(chay('mtd', 'return JSON.stringify(LUONG_CHI);'))),
  chay('mtd', 'return Object.keys(_luongKhoi());'));

const laTT = (d) => chay('kvc', 'return _laTrucTiep(' + JSON.stringify(d) + ');');
t('🔴 `_laTrucTiep` chỉ đúng với đơn "tt"', laTT({ luong: 'tt' }) === true);
t('   đơn "gt" không phải trực tiếp', laTT({ luong: 'gt' }) === false);
t('   đơn cũ không phải trực tiếp — kể cả khi đứng ở khối MTĐ', laTT({}) === false);
t('   `null` không làm nổ', laTT(null) === false);

/* ═══ 2. THANH BƯỚC VẼ LUỒNG CỦA ĐƠN, KHÔNG VẼ LUỒNG CỦA MÀN ════════════════════ */
const thanh = (st, khoiDon, luong, khoiDang) =>
  chay(khoiDang || 'kvc', ham('_thanhBuoc') + '\nreturn _thanhBuoc(' +
    [st, khoiDon, luong].map(function (x) { return JSON.stringify(x); }).join(',') + ');');
const TH_TT = thanh('Chờ quyết toán', 'kvc', 'tt');
t('🔴 thanh bước của đơn trực tiếp KHÔNG có ô tạm ứng nào', !/tạm ứng/i.test(TH_TT), TH_TT);
t('   và có ô `Đã thanh toán`', /Đã thanh toán/.test(TH_TT), TH_TT);
t('   ô đang đứng được tô đậm (nền xanh)', /#0369a1/.test(TH_TT), TH_TT);
const TH_GT = thanh('Chờ quyết toán', 'kvc', 'gt');
t('🔴 cùng một khối, đơn "gt" thì thanh bước CÓ ô tạm ứng', /tạm ứng/i.test(TH_GT), TH_GT);
/* 🔴 ĐÂY LÀ PHÉP CỐT LÕI CỦA CẢ LÁT NÀY: hai đơn cùng khối, cùng trạng thái, khác luồng thì
   phải ra hai thanh KHÁC NHAU. Hỏi nhầm trục là hai cái này bằng nhau. */
t('🔴 hai đơn cùng khối + cùng trạng thái, khác luồng → hai thanh KHÁC nhau', TH_TT !== TH_GT);
/* ⚠️ KHỐI ĐANG ĐỨNG KHÔNG ĐƯỢC ĐỔI THANH của một đơn đã có luồng. Kế toán KVC mở đơn MTĐ bàn
   giao sang mà thanh đổi theo chỗ mình đứng là họ đọc sai tiến độ của đơn người khác. */
teq('⚠️ đứng ở khối khác cũng không đổi thanh của đơn đã có luồng',
  TH_TT, thanh('Chờ quyết toán', 'kvc', 'tt', 'mtd'));
/* ⚠️ TRẠNG THÁI LẠ: không tô ô nào là "đã qua" — thà trông trống còn hơn vẽ bừa tiến độ. */
t('⚠️ trạng thái lạ (đơn cũ đứng ở bước không còn trong luồng) không tô ô "đã qua" nào',
  !/✓ /.test(thanh('Chờ cấp tạm ứng', 'kvc', 'tt')), thanh('Chờ cấp tạm ứng', 'kvc', 'tt'));

/* ═══ 2b. VÀ MÀN ĐƠN PHẢI GỌI THANH BƯỚC ẤY ĐÚNG CÁCH ═══════════════════════════ */
/* 🔴 `_thanhBuoc()` chạy đúng chưa đủ — chỗ GỌI nó mới là nơi chọn trục. Hỏi `CUR.khoi` (một ô
   không hề tồn tại trong thứ `get_don` trả về) là lặng lẽ rơi về khối ĐANG ĐỨNG, và Admin xem
   "tất cả" thì thanh bước vẽ luồng của màn chứ không của đơn. Bỏ luôn tham số luồng thì mọi
   đơn trực tiếp hiện ra với ba bước tạm ứng mà chúng không bao giờ đi qua.
   ⚠️ CẮT THEO NEO CÓ THẬT, không theo số ký tự — thêm một dòng chú thích là cửa sổ trượt. */
const DAI = (function () {
  const i = HTML.indexOf("      var _dai='<div style=\"padding:10px 13px;background:'");
  const neo = "+'</div>';";
  const j = HTML.indexOf(neo, i);
  return (i < 0 || j < 0) ? '' : HTML.slice(i, j + neo.length);
})();
t('⚠️ bốc được đoạn dựng dải trạng thái trong `openDon()`', /_thanhBuoc\(/.test(DAI), DAI.length);
function daiVoi(st, don, khoiDang) {
  return new Function('st', 'CUR', 'esc', 'KHOI_DANG', '_ct', '_nhanDang', '_lamDuocGi',
    NEN + '\n' + ham('_thanhBuoc') + '\n' + DAI + '\nreturn _dai;')(
    st, { don: don, khoi: khoiDang }, function (x) { return String(x == null ? '' : x); }, khoiDang,
    { nen: '#fff', vien: '#eee', mau: '#000', chu: '' }, '', function () { return ''; });
}
/* 🔴 ĐỨNG Ở KHỐI MTĐ, MỞ ĐƠN TRỰC TIẾP CỦA KHỐI KVC — thanh phải là thanh của ĐƠN. */
const D_TT = daiVoi('Chờ quyết toán', { khoi: 'kvc', luong: 'tt' }, 'mtd');
t('🔴 dải trên màn đơn vẽ luồng của ĐƠN, không có bước tạm ứng nào', !/tạm ứng/i.test(D_TT), D_TT);
t('   và có bước `Đã thanh toán`', /Đã thanh toán/.test(D_TT), D_TT);
/* 🔴 ĐƠN CŨ CỦA KHỐI KVC, XEM TỪ KHỐI MTĐ: phải vẽ luồng KVC (của đơn), không phải luồng chi
   của MTĐ (của màn). Đây chính là phép mà `CUR.khoi` — một ô không tồn tại — làm hỏng. */
const D_CU = daiVoi('Chờ quyết toán', { khoi: 'kvc' }, 'mtd');
t('🔴 đơn cũ của khối KVC xem từ khối MTĐ vẫn vẽ luồng KVC',
  !/Đã thanh toán/.test(D_CU) && /tạm ứng/i.test(D_CU), D_CU);
t('   đối chứng · đơn cũ của khối MTĐ thì có bước `Đã thanh toán`',
  /Đã thanh toán/.test(daiVoi('Chờ quyết toán', { khoi: 'mtd' }, 'mtd')));

/* ═══ 3. NÚT GỬI Ở "NHÁP" — ĐƠN TRỰC TIẾP GỬI THẲNG QUYẾT TOÁN ══════════════════ */
/* 🔴 CHẠY CHÍNH ĐOẠN MÃ CỦA `openDon()`, không chép lại. Chép lại là canh bản của bài kiểm.
   ⚠️ Cắt tới hết nhánh `else` — cắt theo SỐ KÝ TỰ là thêm một dòng chú thích thì phần cần soi
      rơi ra ngoài cửa sổ (đã cắn 21/09/2026 ở hai bài khác). */
const NHANH = (function () {
  const i = HTML.indexOf('      var _tt=_laTrucTiep(CUR&&CUR.don);');
  const neo = "      else { btn.style.display='none'; btn.onclick=null; }";
  const j = HTML.indexOf(neo, i);
  return (i < 0 || j < 0) ? '' : HTML.slice(i, j + neo.length);
})();
t('⚠️ bốc được nhánh nút gửi trong `openDon()`', NHANH.length > 300, NHANH.length);
function nut(st, don, quyen) {
  const B = { style: {}, textContent: '', onclick: null };
  const goi = {};
  new Function('st', 'CUR', 'canDo', 'el', 'guiQT', 'guiDuyetTU', 'B', 'esc', 'KHOI_DANG',
    NEN + '\nvar btn=B, nhan="", tay=null, goi="";\n' + NHANH + '\nreturn {nhan:nhan, tay:tay, goi:goi, hien:B.style.display};')(
    st, { don: don }, function (q) { return quyen.indexOf(q) >= 0; },
    function () { return B; }, function () { goi.qt = 1; }, function () { goi.tu = 1; }, B,
    function (x) { return String(x == null ? '' : x); }, 'kvc');
  return { nhan: B.textContent, tay: B.onclick, hien: B.style.display, goi: goi };
}
const N_TT = nut('Nháp', { luong: 'tt' }, ['guiQT', 'xinTU']);
t('🔴 đơn TRỰC TIẾP ở Nháp → nút "Gửi quyết toán cho kế toán"', /Gửi quyết toán/.test(N_TT.nhan), N_TT.nhan);
t('   và KHÔNG mời xin tạm ứng', !/tạm ứng/i.test(N_TT.nhan), N_TT.nhan);
N_TT.tay(); teq('🔴 bấm vào nó gọi đúng `guiQT`, không gọi `guiDuyetTU`', { qt: 1 }, N_TT.goi);
const N_GT = nut('Nháp', { luong: 'gt' }, ['guiQT', 'xinTU']);
t('🔴 đơn QUA TẠM ỨNG ở Nháp → vẫn là nút "Gửi xin tạm ứng"', /Gửi xin tạm ứng/.test(N_GT.nhan), N_GT.nhan);
N_GT.tay(); teq('   và nó gọi `guiDuyetTU`', { tu: 1 }, N_GT.goi);
const N_CU = nut('Nháp', {}, ['guiQT', 'xinTU']);
t('🔴 đơn CŨ (chưa có ô luồng) → y như hôm qua: xin tạm ứng', /Gửi xin tạm ứng/.test(N_CU.nhan), N_CU.nhan);
/* 🔴 QUYỀN LÀ `guiQT` CHO LƯỢT GỬI QUYẾT TOÁN, dù đơn đang ở Nháp. Gác bằng `xinTU` là vai chỉ
   được xin tạm ứng lại gửi được quyết toán — nới quyền trong im lặng. */
teq('🔴 vai chỉ có `xinTU` KHÔNG gửi được quyết toán cho đơn trực tiếp', 'none',
  nut('Nháp', { luong: 'tt' }, ['xinTU']).hien);
teq('   vai chỉ có `guiQT` thì gửi được', 'inline-block', nut('Nháp', { luong: 'tt' }, ['guiQT']).hien);
/* ⚠️ Và vai chỉ có `guiQT` KHÔNG được bày nút xin tạm ứng cho đơn "gt" — cửa quyền hai bên
   không được lẫn sang nhau. */
teq('⚠️ vai chỉ có `guiQT` không xin tạm ứng hộ đơn "gt"', 'none', nut('Nháp', { luong: 'gt' }, ['guiQT']).hien);
/* Bước "Đã cấp tạm ứng" là của luồng gián tiếp — không đụng gì tới lát này. */
t('⚠️ đối chứng · "Đã cấp tạm ứng" vẫn gửi quyết toán như cũ',
  /Gửi quyết toán/.test(nut('Đã cấp tạm ứng', { luong: 'gt' }, ['guiQT']).nhan));

/* ═══ 4. NÚT "💵 ĐÃ THANH TOÁN" — HỎI LUỒNG CỦA CHÍNH ĐƠN ═══════════════════════ */
const nutTT = (d, khoiDang) => chay(khoiDang || 'kvc',
  ham('_nutThanhToan') + '\nreturn _nutThanhToan(' + JSON.stringify(d) + ');');
/* Bệ đỡ cần `canDo` + `esc`; `esc` đã có trong `chay`, `canDo` nhét thêm ở đây. */
function nutTTQuyen(d, khoiDang, co) {
  return new Function('KHOI_DANG', 'esc', 'canDo',
    NEN + '\n' + ham('_nutThanhToan') + '\nreturn _nutThanhToan(' + JSON.stringify(d) + ');')(
    khoiDang || 'kvc', function (x) { return String(x == null ? '' : x); }, function () { return co; });
}
t('🔴 đơn TRỰC TIẾP đã quyết toán, giữa khối KVC → CÓ nút 💵 Đã thanh toán',
  /Đã thanh toán/.test(nutTTQuyen({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'tt' }, 'kvc', true)));
/* 🔴 ĐÂY LÀ CHỖ "HỎI MỖI KHỐI" SẼ SAI. Trước 22/09 khối kvc không có bước này; hỏi khối thì
   đúng cái nút vừa kiểm ở trên biến mất, và đơn trực tiếp đứng im mãi ở "Đã quyết toán". */
teq('🔴 đơn "gt" đã quyết toán giữa khối KVC → KHÔNG có nút (luồng ấy không có bước này)', '',
  nutTTQuyen({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'gt' }, 'kvc', true));
t('🔴 đơn cũ giữa khối MTĐ vẫn CÓ nút — luồng chi của khối có bước ấy',
  /Đã thanh toán/.test(nutTTQuyen({ trangThai: 'Đã quyết toán', khoi: 'mtd' }, 'kvc', true)));
/* ⚠️ KHỐI CỦA ĐƠN, KHÔNG PHẢI KHỐI ĐANG ĐỨNG — kế toán KVC mở danh sách có lẫn đơn MTĐ. */
teq('⚠️ đứng ở khối MTĐ cũng không mọc nút cho đơn "gt" của khối KVC', '',
  nutTTQuyen({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'gt' }, 'mtd', true));
teq('⚠️ chưa quyết toán thì không có nút, dù luồng có bước ấy', '',
  nutTTQuyen({ trangThai: 'Chờ quyết toán', khoi: 'kvc', luong: 'tt' }, 'kvc', true));
teq('🔴 không có quyền `xacNhanQT` → không nút', '',
  nutTTQuyen({ trangThai: 'Đã quyết toán', khoi: 'kvc', luong: 'tt' }, 'kvc', false));

/* ═══ 5. DẢI LUỒNG ĐẦU MÀN — KHÔNG ĐƯỢC NUỐT BƯỚC CÓ ĐƠN ĐANG ĐỨNG ══════════════ */
/* 🔴 `_luongBuocDs()` vẽ HỢP của các luồng đang có mặt. Lấy riêng luồng của khối thì một đơn
   trực tiếp dừng ở `Đã thanh toán` giữa khối KVC biến mất khỏi dải — `_luongDem()` vẫn đếm
   nó, nhưng không thẻ nào bày con số ấy ra, và dải sinh ra chính là để chỉ chỗ tắc. */
function buocDs(khoiDang, dons) {
  return new Function('KHOI_DANG', 'esc', 'BOOT', '_hopKhoi',
    NEN + '\n' + khoi('LUONG_DIEN') + '\n' + ham('_luongBuocDs') + '\nreturn _luongBuocDs();')(
    khoiDang, function (x) { return String(x == null ? '' : x); }, { dons: dons || [] },
    function (d) { return String(d.khoi || '') === khoiDang; });
}
teq('⚠️ đối chứng · không đơn nào → dải đúng bằng luồng của khối', buoc('kvc', ''), buocDs('kvc', []));
const DS_LAN = buocDs('kvc', [{ khoi: 'kvc', luong: 'tt', trangThai: 'Đã thanh toán' },
  { khoi: 'kvc', luong: '', trangThai: 'Chờ cấp tạm ứng' }]);
t('🔴 có đơn trực tiếp giữa khối KVC → dải mọc thêm bước `Đã thanh toán`',
  DS_LAN.indexOf('Đã thanh toán') >= 0, DS_LAN);
t('   mà KHÔNG mất bước tạm ứng của khối', DS_LAN.indexOf('Chờ cấp tạm ứng') >= 0, DS_LAN);
/* ⚠️ THỨ TỰ LẤY TỪ `LUONG_DIEN`. Nối đuôi hai bảng là `Đã thanh toán` rơi xuống sau
   `Đã xuất MISA` — dải vẽ ngược tiến độ mà không ai thấy sai ngay. */
t('⚠️ `Đã thanh toán` vẫn nằm trước `Đã xuất MISA`',
  DS_LAN.indexOf('Đã thanh toán') < DS_LAN.indexOf('Đã xuất MISA'), DS_LAN);
t('⚠️ `Nháp` vẫn đứng đầu', DS_LAN[0] === 'Nháp', DS_LAN);
/* ⚠️ ĐƠN KHỐI KHÁC KHÔNG ĐƯỢC KÉO BƯỚC VÀO DẢI — dải là của khối đang đứng. */
teq('⚠️ đơn trực tiếp của khối KHÁC không mọc bước vào dải này',
  buoc('kvc', ''), buocDs('kvc', [{ khoi: 'mtd', luong: 'tt', trangThai: 'Đã thanh toán' }]));
/* 🔴 VÀ DẢI PHẢI THẬT SỰ DÙNG HÀM ẤY. Để `veThanhLuong()` quay lại `Object.keys(_luongKhoi())`
   là mọi phép trên vẫn xanh (chúng gọi thẳng `_luongBuocDs`), mà màn thì nuốt bước như cũ. */
t('🔴 `veThanhLuong()` lấy danh sách bước từ `_luongBuocDs()`',
  /var DS=_luongBuocDs\(\)/.test(ham('veThanhLuong')), ham('veThanhLuong').slice(0, 200));

/* ═══ 6. HAI BÊN CÙNG LUẬT — MÀN vs MÁY CHỦ ═════════════════════════════════════ */
/* 🔴 Lệch một vế là màn vẽ ra một luồng máy chủ không công nhận. Bốc bộ khoá của cả ba bảng ở
   hai bên rồi so thẳng — không so chữ hiện ra (chữ được phép khác), chỉ so KHOÁ LƯU TRONG SỔ. */
function khoaJs(ten) { return (khoi(ten).match(/'([^']+)'\s*:/g) || []).map(function (x) { return x.replace(/'|\s|:/g, ''); }); }
function khoaPhp(ten) {
  const i = DON.indexOf('const ' + ten + ' = array(');
  if (i < 0) return [];
  const s = DON.slice(i, DON.indexOf(');', i));
  return (s.match(/'([^']+)'\s*=>/g) || []).map(function (x) { return x.replace(/'|\s|=>/g, ''); });
}
['LUONG_KVC', 'LUONG_CHI', 'LUONG_TT'].forEach(function (n) {
  const a = khoaJs(n), b = khoaPhp(n);
  t('⚠️ `' + n + '` bốc được ở cả hai bên', a.length > 2 && b.length > 2, { js: a.length, php: b.length });
  teq('🔴 `' + n + '` — màn và máy chủ CÙNG bộ bước', b, a);
});
/* 🔴 BỘ MÃ LUỒNG PHẢI KHỚP. Màn bày một mã máy chủ không biết là đơn lập ra rơi thẳng vào
   nhánh "rỗng = theo khối", tức người lập chọn một đằng máy chủ hiểu một nẻo. */
const MA_JS = (mang('ND_LUONG_DS').match(/ma\s*:\s*'([^']+)'/g) || []).map(function (x) { return x.replace(/.*'([^']+)'.*/, '$1'); });
const MA_PHP = khoaPhp('LUONG_MA');
teq('🔴 mã luồng bày trên màn khớp `VHCP_Don::LUONG_MA`', MA_PHP.slice().sort(), MA_JS.slice().sort());
teq('   và đúng BA mã, không hơn', 3, MA_JS.length);
/* 🔴 'dc' (DUYỆT CHI) THÊM 1.288.0 — anh Thắng: *"Hoặc bộ phận sẽ chọn phương án duyệt chi"*.
   Nó phải TRỎ VÀO CHÍNH `LUONG_CHI`, không phải một bảng thứ tư chép lại: chép ra là hai nơi
   phải nhớ sửa mỗi lượt đổi chữ, và chỗ quên thì đơn cùng luồng hiện hai tên ở hai màn. */
teq('🔴 mã "dc" đi đúng bộ bước của luồng DUYỆT CHI',
  Object.keys(JSON.parse(chay('kvc', 'return JSON.stringify(LUONG_CHI);'))), buoc('kvc', 'dc'));
t('🔴 luồng duyệt chi CÓ khâu duyệt trước khi tiêu (khác hẳn trực tiếp)',
  buoc('kvc', 'dc').indexOf('Chờ duyệt tạm ứng') >= 0, buoc('kvc', 'dc'));
t('   và KHÔNG có bước cấp tạm ứng (khác hẳn qua tạm ứng)',
  buoc('kvc', 'dc').indexOf('Chờ cấp tạm ứng') < 0, buoc('kvc', 'dc'));
teq('🔴 đơn "dc" giữa khối KVC vẫn đi luồng duyệt chi — đơn nói trước, khối nói sau',
  buoc('mtd', ''), buoc('kvc', 'dc'));
teq('   `_luongDon` nhận mã "dc"', 'dc', maLuong({ luong: 'dc' }));
t('   nhưng "dc" KHÔNG phải trực tiếp', laTT({ luong: 'dc' }) === false);
/* ⚠️ `KHOI_LUONG_CHI` (đường lui theo khối) cũng phải cùng hai bên. */
const KLC_JS = (dong('KHOI_LUONG_CHI').match(/'([^']+)'/g) || []).map(function (x) { return x.replace(/'/g, ''); });
const KLC_PHP = ((DON.match(/const KHOI_LUONG_CHI\s*=\s*array\(([^)]*)\)/) || ['', ''])[1].match(/'([^']+)'/g) || [])
  .map(function (x) { return x.replace(/'/g, ''); });
teq('🔴 `KHOI_LUONG_CHI` cùng danh sách ở hai bên', KLC_PHP, KLC_JS);

/* ═══ 7. Ô CHỌN LUỒNG LÚC LẬP ĐƠN ═══════════════════════════════════════════════ */
t('🔴 hộp tạo đơn có ô chọn luồng', /id="ndLuongBox"/.test(HTML) && /id="ndLuongVi"/.test(HTML));
t('   và có câu hỏi bằng tiếng người, không phải nhãn kỹ thuật', /Đơn này đi luồng nào\?/.test(HTML));
/* 🔴 MẶC ĐỊNH 'gt' — *"cái đang chạy"*. Để rỗng là người lập không biết mình vừa chọn gì; để
   'tt' là đổi thói quen của cả trăm người trong một lượt cài bản. */
t('🔴 mặc định là "gt" (qua tạm ứng)', /var ND_LUONG='gt';/.test(HTML), dong('ND_LUONG'));
/* ⚠️ KHÔNG NHỚ VÀO MÁY KHÁCH. Nhớ lựa chọn lần trước là hôm sau lập một đơn khác hẳn mà nó
   lặng lẽ mang luồng cũ. */
t('⚠️ không nhớ luồng vào localStorage/sessionStorage',
  !/(local|session)Storage[^\n]*[Ll]uong/.test(HTML) && !/ND_LUONG[^\n]*Storage/.test(HTML));
function veLuong(chon) {
  const NK = {};
  new Function('moi', 'return function(){ with(moi){ ' + mang('ND_LUONG_DS') + '\n' + ham('_luongKhoa') + '\n' + ham('veNdLuong') + '\n' +
    ham('ndChonLuong') + '\n var ND_LUONG=' + JSON.stringify(chon) + '; veNdLuong(); } }')(
    { esc: function (x) { return String(x == null ? '' : x); },
      el: function (id) { return (NK[id] = NK[id] || { innerHTML: '', textContent: '' }); } })();
  return NK;
}
const V_GT = veLuong('gt');
t('🔴 vẽ ra ĐỦ HAI lựa chọn', /Qua tạm ứng/.test(V_GT.ndLuongBox.innerHTML) && /Trực tiếp/.test(V_GT.ndLuongBox.innerHTML),
  V_GT.ndLuongBox.innerHTML);
/* ⚠️ MỖI LUỒNG MỘT CÂU GIẢI THÍCH — đây là câu hỏi người ta chỉ trả lời đúng khi hiểu. */
t('⚠️ mỗi lựa chọn kèm một câu giải thích, không chỉ mỗi cái tên',
  /Xin tiền trước/.test(V_GT.ndLuongBox.innerHTML) && /Tiền đã chi rồi/.test(V_GT.ndLuongBox.innerHTML),
  V_GT.ndLuongBox.innerHTML);
t('🔴 lựa chọn đang chọn được tô đậm (b-p), cái kia không',
  (V_GT.ndLuongBox.innerHTML.match(/btn b-p/g) || []).length === 1, V_GT.ndLuongBox.innerHTML);
t('🔴 và nói trước đơn sẽ đi qua những bước nào', /Chờ cấp tạm ứng/.test(V_GT.ndLuongVi.textContent), V_GT.ndLuongVi.textContent);
const V_TT = veLuong('tt');
t('🔴 chọn "tt" thì câu "đơn sẽ đi" ĐỔI hẳn',
  V_TT.ndLuongVi.textContent !== V_GT.ndLuongVi.textContent, V_TT.ndLuongVi.textContent);
t('   và KHÔNG còn kể bước `Chờ cấp tạm ứng`',
  !/Chờ cấp tạm ứng/.test(V_TT.ndLuongVi.textContent), V_TT.ndLuongVi.textContent);
/* ⚠️ SOI ĐÚNG CÁI NÚT ĐANG ĐẬM, không soi cả hộp — cả hộp thì nút nào cũng có chữ "Trực tiếp"
   và phép này xanh kể cả khi dấu chọn dính chết ở nút đầu. */
const damTT = (V_TT.ndLuongBox.innerHTML.match(/<button[^>]*btn b-p[\s\S]*?<\/button>/g) || []);
teq('🔴 đúng MỘT nút được tô đậm', 1, damTT.length);
t('🔴 và nút đậm ấy là "Trực tiếp", không phải "Qua tạm ứng"',
  /Trực tiếp/.test(damTT[0] || '') && !/Qua tạm ứng/.test(damTT[0] || ''), damTT[0]);
const damGT = (V_GT.ndLuongBox.innerHTML.match(/<button[^>]*btn b-p[\s\S]*?<\/button>/g) || []);
t('   đối chứng · chọn "gt" thì nút đậm là "Qua tạm ứng"',
  /Qua tạm ứng/.test(damGT[0] || '') && !/Trực tiếp/.test(damGT[0] || ''), damGT[0]);
t('⚠️ mã lạ thì bỏ qua, không đổi lựa chọn đang có', /ND_LUONG_DS\.some/.test(ham('ndChonLuong')), ham('ndChonLuong'));
/* 🔴 VÀ PHẢI THẬT SỰ GỬI ĐI. Chọn xong mà không truyền xuống là ô này chỉ để trang trí. */
/* 23/09/2026: gửi thêm KHỐI ĐANG ĐỨNG (tham số 4) — Bắc–Nam dùng chung một bản, người lập tích
   nhiều khối thì đơn về tab đang mở. Xem `kiem-khoi-theo-nguoi-lap.php`. */
t('🔴 `createDon` gửi kèm luồng đã chọn và khối đang đứng', /\.createDon\(ky,\s*nv,\s*ND_LUONG,\s*KHOI_DANG\)/.test(HTML));
t('🔴 và máy chủ nhận đủ bốn tham số', /function create_don\(\s*\$ky,\s*\$nguoi_lap,\s*\$luong\s*=\s*'',\s*\$khoi_man\s*=\s*''\s*\)/.test(DON));

/* ═══ 8. CÂU CHỮ QUANH NÚT PHẢI GỌI ĐÚNG TÊN NÚT ════════════════════════════════ */
/* 🔴 Bảo người ta bấm một nút không tồn tại là họ ngồi tìm, rồi tưởng đơn của mình lỗi. */
function cau(d) {
  return new Function('CUR', 'esc', NEN + '\n' + ham('_cauTrangThai') + "\nreturn _cauTrangThai('Nháp').chu;")(
    { don: d }, function (x) { return String(x == null ? '' : x); });
}
t('🔴 đơn trực tiếp ở Nháp: câu nhắc gọi tên "Gửi quyết toán cho kế toán"',
  /Gửi quyết toán cho kế toán/.test(cau({ luong: 'tt' })), cau({ luong: 'tt' }));
t('   và KHÔNG bảo bấm "Gửi xin tạm ứng"', !/Gửi xin tạm ứng/.test(cau({ luong: 'tt' })), cau({ luong: 'tt' }));
t('🔴 đơn qua tạm ứng vẫn là câu cũ', /Gửi xin tạm ứng/.test(cau({ luong: 'gt' })), cau({ luong: 'gt' }));
t('   đơn cũ cũng vậy', /Gửi xin tạm ứng/.test(cau({})), cau({}));
function viSao(d) {
  return new Function('CUR', 'CURUSER', 'esc', NEN + '\n' + ham('_viSaoKhongGui') + "\nreturn _viSaoKhongGui('Nháp').html;")(
    { don: d }, { role: 'Nhân viên' }, function (x) { return String(x == null ? '' : x); });
}
/* 🔴 CHỈ ĐÚNG CÁI QUYỀN ĐANG THIẾU. Chỉ nhầm ô là người quản trị bật một quyền không liên
   quan, thử lại vẫn không có nút, rồi kết luận app hỏng. */
t('🔴 không gửi được đơn trực tiếp → chỉ vào quyền "Gửi quyết toán"',
  /Gửi quyết toán/.test(viSao({ luong: 'tt' })), viSao({ luong: 'tt' }));
t('   không gửi được đơn qua tạm ứng → chỉ vào quyền "Xin tạm ứng"',
  /Xin tạm ứng/.test(viSao({ luong: 'gt' })), viSao({ luong: 'gt' }));

/* ═══ 8b. MỌI BẢNG ĐƠN GỌI TÊN TRẠNG THÁI THEO LUỒNG CỦA ĐƠN ════════════════════ */
/* 🔴 Chữ hiện trên màn ĐỔI THEO LUỒNG: đơn trực tiếp ở "Nháp" đọc là *Tạo đơn*, đơn qua tạm
   ứng đọc là *Nháp*. Một bảng quên truyền luồng thì đúng bảng đó gọi sai tên — và nó sai im
   lặng, vì chuỗi trả về vẫn là một cái tên trạng thái hợp lệ của luồng khác. */
function veDanhSach(dons) {
  const KHO = {};
  const moi = {
    CUR: null, CURUSER: { role: 'Nhân viên', name: 'NV' },
    esc: function (x) { return String(x == null ? '' : x); },
    el: function (id) { return (KHO[id] = KHO[id] || { style: {}, innerHTML: '', textContent: '', value: '' }); },
    _myDons: function () { return dons; },
    _fillDonFilters: function () {}, _thangCuaKy: function () { return ''; },
    stCls: function () { return 'st-x'; }, money: function (x) { return String(Number(x) || 0); },
    _canDelDon: function () { return false; }, canDo: function () { return false; },
    viSaoTrong: function () { return ''; }
  };
  new Function('moi', 'with(moi){ ' + NEN + '\n' + ham('renderDonList') + '\n renderDonList(); }')(moi);
  return KHO.donListBody.innerHTML;
}
const DL_TT = veDanhSach([{ maDon: 'D1', ky: 'K', trangThai: 'Nháp', khoi: 'kvc', luong: 'tt' }]);
t('🔴 bảng đơn gọi đơn TRỰC TIẾP ở Nháp là "Tạo đơn"', /Tạo đơn/.test(DL_TT), DL_TT);
const DL_GT = veDanhSach([{ maDon: 'D2', ky: 'K', trangThai: 'Nháp', khoi: 'kvc', luong: 'gt' }]);
t('🔴 và đơn QUA TẠM ỨNG ở cùng bước vẫn là "Nháp"', />Nháp</.test(DL_GT) && !/Tạo đơn/.test(DL_GT), DL_GT);
t('   đơn cũ (chưa có ô luồng) cũng là "Nháp" — y như hôm qua',
  !/Tạo đơn/.test(veDanhSach([{ maDon: 'D3', ky: 'K', trangThai: 'Nháp', khoi: 'kvc' }])));
/* ⚠️ CENSUS: bỏ sót MỘT bảng là đúng bảng đó nói sai, mà mấy bảng kia vẫn xanh. Đếm thẳng trên
   nguồn: mọi lượt gọi `_tenTT` với trạng thái CỦA MỘT ĐƠN phải truyền cả luồng của đơn ấy.
   Ngoại lệ DUY NHẤT là dải luồng đầu màn — ở đó `st` là một BƯỚC của dải, không phải của đơn
   nào, nên nó gọi `_tenTT(st)` một tham số. */
{
  /* ⚠️ Bỏ chính DÒNG KHAI HÀM ra khỏi census (`function _tenTT(st, khoi, luong)`) — nó không
     phải một lượt gọi, và đếm nhầm nó là phép này đỏ mãi mãi. */
  const goi = (HTML.match(/(function\s+)?_tenTT\([^)]*\)/g) || []).filter(function (g) { return !/^function/.test(g); });
  const cuaDon = goi.filter(function (g) { return /\.trangThai|\bst\s*,/.test(g); });
  t('⚠️ tìm được đủ chỗ gọi `_tenTT` với trạng thái của một đơn', cuaDon.length >= 6, cuaDon);
  const thieu = cuaDon.filter(function (g) { return !/_luongDon\(/.test(g); });
  teq('🔴 KHÔNG bảng nào gọi tên trạng thái mà quên luồng của đơn', [], thieu);
}

/* ═══ 9. DẢI BÀN GIAO KHÔNG ĐƯỢC NÓI CHẮC MỘT LUỒNG ═════════════════════════════ */
/* 🔴 Màn quyết toán nay lẫn cả hai luồng, nên hỏi khối rồi nói chắc MỘT lối là nói dối đúng
   một nửa bảng: hoặc giấu mất một nút người ta phải bấm, hoặc bảo người ta đợi một nút không
   bao giờ hiện. Câu phải kể cả hai lối; ai có bước ấy thì `_nutThanhToan(d)` tự bày. */
const BG = ham('renderQtBanGiao').replace(/\/\*[\s\S]*?\*\//g, ' ');
t('⚠️ bốc được `renderQtBanGiao`', BG.length > 150, BG.length);
t('🔴 dải bàn giao KHÔNG hỏi `_ttTrongLuong` theo khối nữa', !/_ttTrongLuong/.test(BG), BG);
t('   nhưng câu chữ VẪN kể bước `Đã thanh toán`', /Đã thanh toán/.test(BG), BG);
t('   và vẫn nói đơn tự chuyển sang kế toán Khu vui chơi', /kế toán Khu vui chơi/.test(BG), BG);

/* ═══ 10. LUỒNG MỒI SẴN + BẢNG KHAI THEO BỘ PHẬN (1.288.0) ══════════════════════════ */
/* 🔴 CHẠY THẬT `_luongMoi()`, KHÔNG SOI CHỮ. Soi chữ "có nhắc tới BOOT.luongBo không" là một
   phép xanh cả khi nhánh ấy bị vô hiệu (`if(false) return bo;`) — đã thử, nó lọt lưới. */
function luongMoi(luongBo, khoiDang) {
  return new Function('BOOT', 'KHOI_DANG', 'KHOI_LUONG_CHI', ham('_luongMoi') + '\nreturn _luongMoi();')(
    { luongBo: luongBo }, khoiDang, ['mtd', 'vp']);
}
teq('🔴 bộ phận khai "dc" → mồi sẵn "dc", dù đang đứng ở khối Khu vui chơi', 'dc', luongMoi('dc', 'kvc'));
teq('   bộ phận khai "tt" → mồi "tt"', 'tt', luongMoi('tt', 'kvc'));
teq('   bộ phận khai "gt" → mồi "gt" kể cả giữa khối Máy tự động', 'gt', luongMoi('gt', 'mtd'));
/* 🔴 BỘ PHẬN CHƯA KHAI → NGÃ VỀ ĐÚNG ĐƯỜNG LUI CỦA MÁY CHỦ. Đây chính là lỗi bản 1.287.0: nó
   gõ cứng 'gt', nên đơn mới của Máy tự động / Văn phòng mất hẳn khâu duyệt chi. */
teq('🔴 chưa khai + khối Máy tự động → "dc" (giữ khâu duyệt chi như trước 1.287.0)', 'dc', luongMoi('', 'mtd'));
teq('   chưa khai + khối Văn phòng → "dc"', 'dc', luongMoi('', 'vp'));
teq('   chưa khai + khối Khu vui chơi → "gt"', 'gt', luongMoi('', 'kvc'));
teq('   chưa khai + khối vùng (hn) → "gt"', 'gt', luongMoi('', 'hn'));
teq('⚠️ mã lạ từ máy chủ → cũng ngã về đường lui, không mồi mã bậy', 'gt', luongMoi('xyz', 'kvc'));

/* Bảng khai ở Cấu hình — vẽ thật rồi đọc thật. */
function veBangBp(mp, admin) {
  const KHO = {};
  const moi = {
    CFG: { boPhanLuong: mp }, BOOT: {},
    _laAdmin: function () { return admin !== false; },
    _bpDs: function () { return ['Văn phòng', 'Marketing', 'Máy tự động']; },
    esc: function (x) { return String(x == null ? '' : x); },
    el: function (id) { return (KHO[id] = KHO[id] || { style: {}, innerHTML: '' }); }
  };
  new Function('moi', 'with(moi){ ' + mang('LUONG_BP_DS') + '\n' + ham('renderLuongBp') + '\n renderLuongBp(); }')(moi);
  return KHO;
}
const BP1 = veBangBp({ 'Văn phòng': 'dc' }, true);
t('🔴 bảng khai vẽ đủ mọi bộ phận đang có',
  /Văn phòng/.test(BP1.cfgLuongBpBody.innerHTML) && /Marketing/.test(BP1.cfgLuongBpBody.innerHTML)
  && /Máy tự động/.test(BP1.cfgLuongBpBody.innerHTML), BP1.cfgLuongBpBody.innerHTML);
/* ⚠️ CỘT TÊN PHẢI KHOÁ. Gõ được vào đó là đẻ ra một bộ phận thứ tám không có trong danh mục,
   và mọi phép `bo_phan_chuan()` sẽ chối nó trong im lặng. */
teq('🔴 cột Bộ phận là ô KHOÁ (readonly), không gõ vào được', 3,
  (BP1.cfgLuongBpBody.innerHTML.match(/readonly/g) || []).length);
t('🔴 bộ phận đã khai thì ô chọn nhớ đúng mã', /value="dc" selected/.test(BP1.cfgLuongBpBody.innerHTML),
  BP1.cfgLuongBpBody.innerHTML);
t('   bộ phận chưa khai thì về dòng rỗng "theo khối như cũ"',
  /value="" selected/.test(BP1.cfgLuongBpBody.innerHTML), BP1.cfgLuongBpBody.innerHTML);
teq('🔴 không phải Admin → giấu hẳn thẻ (máy chủ cũng chối)', 'none', veBangBp({}, false).luongBpCard.style.display);

/* 🔴 LƯU PHẢI GỬI ĐỦ MỌI DÒNG, KỂ CẢ DÒNG ĐỂ TRỐNG. Lọc bỏ dòng trống là một lượt "chọn lại
   về theo khối rồi bấm Lưu" không bao giờ tới được máy chủ: màn báo xong, mà mã cũ vẫn nằm
   nguyên trong sổ và đơn mới vẫn đi luồng cũ. */
{
  const HANG = [['Văn phòng', ''], ['Marketing', 'tt']];
  let gui = null;
  new Function('_readRows', '_saveCfg', 'toast', ham('saveCfgLuongBp') + '\nsaveCfgLuongBp();')(
    function () { return HANG; }, function (p) { gui = p; }, function () {});
  t('⚠️ `saveCfgLuongBp()` có gọi lưu', !!gui, gui);
  teq('🔴 gửi ĐỦ hai dòng, kể cả dòng để trống',
    [{ ten: 'Văn phòng', luong: '' }, { ten: 'Marketing', luong: 'tt' }], gui && gui.boPhanDs);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hai luồng đơn chạy đúng trên màn, và cùng luật với máy chủ.');
