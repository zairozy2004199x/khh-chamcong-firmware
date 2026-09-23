/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN DUYỆT GOM THEO TUẦN — CHI PHÍ CƠ SỞ DUYỆT CẢ TUẦN MỘT LƯỢT.
 *
 * Anh Thắng 22/09/2026: *"Khu duyệt các chi phí, hiện chi phí cơ sở lên và duyệt theo tuần.
 * Còn các chi phí khác thì kiểm tra và duyệt khi gửi"*, nối tiếp 10/09: *"kế toán phân biệt
 * đơn theo cơ sở (theo tuần), đơn theo dự án (theo thời gian setup linh động)"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CHẠY THẬT
 * =============================================================================================
 * Cái hỏng ở đây không nổ, nó chỉ duyệt nhầm tiền. Nút "Duyệt cả tuần" tích ô rồi đi qua đúng
 * cửa `doDuyetChon()` đang có — nên nếu nó tích lố sang tuần khác, hoặc quên bỏ tích cũ của
 * người dùng, thì hộp xác nhận vẫn hiện ra một con số trông hợp lý và kế toán bấm OK. Tiền của
 * tuần khác đi theo, không một dòng lỗi nào.
 *
 * ⚠️ BẢNG NÀY CHỞ ĐÚNG CHI PHÍ CƠ SỞ (sổ `don`). Đơn dự án · công tác · setup · marketing nằm
 *    ở mấy khối riêng và giữ kiểu "duyệt từng cái khi gửi" — đúng vế sau câu anh Thắng nói.
 *    Mục 5 canh cho mấy khối ấy KHÔNG bị kéo vào lối gom tuần.
 *
 * Chạy: node tools/test/kiem-duyet-theo-tuan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };

/* ═══ 0. BỆ ĐỠ CÓ THẬT CHƯA ═════════════════════════════════════════════════════ */
['_dvGomTuan', '_dvNhomHdr', 'dvNhomToggle', 'dvLamCaTuan', '_tachDonVi', '_kyVal'].forEach(function (n) {
  t('⚠️ bốc được `' + n + '()`', ham(n).length > 40, ham(n).length);
});

const NEN = [ham('_kyVal'), ham('_tachDonVi'), ham('_dvNhomHdr'), ham('_dvGomTuan')].join('\n');

/* Vẽ thật bảng: mỗi đơn một dòng mang mã, để đọc lại thứ tự và nhóm. */
function ve(dons, opts) {
  opts = opts || {};
  return new Function('BOOT', 'esc', 'money', 'canDo', 'list',
    NEN + '\nreturn _dvGomTuan(list, function(d, gid, gap){'
    + ' return "<tr class=\\"dvm " + (gid||"") + "\\" data-ma=\\"" + d.maDon + "\\"" + ((gid&&gap)?" hidden":"") + "></tr>"; });')(
    { nhieuDonVi: !!opts.nhieuDonVi }, function (x) { return String(x == null ? '' : x); },
    function (x) { return String(Number(x) || 0); }, function () { return opts.quyen !== false; }, dons);
}

const D = (ma, ky, st, tu, dv) => ({ maDon: ma, ky: ky, trangThai: st, tamUng: tu || 0, donVi: dv || 'K&H' });
const TUAN_A = 'Tuần (01/09 - 07/09/2026)';
const TUAN_B = 'Tuần (08/09 - 14/09/2026)';

/* ═══ 1. GOM ĐÚNG NHÓM, XẾP ĐÚNG THỨ TỰ ════════════════════════════════════════ */
const H1 = ve([D('A1', TUAN_A, 'Chờ duyệt tạm ứng', 100), D('B1', TUAN_B, 'Chờ duyệt tạm ứng', 200),
  D('A2', TUAN_A, 'Chờ cấp tạm ứng', 300)]);
t('🔴 mỗi tuần một hàng tiêu đề', (H1.match(/dvGrpHdr/g) || []).length === 2, H1);
/* 🔴 MỚI NHẤT TRƯỚC — cùng phép xếp với màn Quyết toán. Hai màn bày hai thứ tự khác nhau cho
   cùng một danh sách tuần là người dùng phải học hai lần. */
t('🔴 tuần MỚI NHẤT đứng trước', H1.indexOf('08/09') < H1.indexOf('01/09'), H1.slice(0, 400));
/* Mỗi đơn về đúng nhóm của nó. */
const nhom = {};
(H1.match(/<tr class="dvm ([^"]*)" data-ma="([^"]*)"/g) || []).forEach(function (x) {
  const m = /dvm ([^"]*)" data-ma="([^"]*)"/.exec(x); nhom[m[2]] = m[1];
});
t('🔴 hai đơn cùng tuần về cùng một nhóm', nhom.A1 === nhom.A2, nhom);
t('🔴 đơn tuần khác về nhóm khác', nhom.B1 !== nhom.A1, nhom);

/* 🔴 TUẦN MỚI NHẤT XỔ SẴN, TUẦN CŨ GẬP. Đây là màn LÀM VIỆC: gập hết thì mở tab ra thấy một
   danh sách tiêu đề và không biết có việc hay không. */
t('🔴 tuần mới nhất XỔ sẵn (đơn của nó không bị giấu)', !/data-ma="B1" hidden/.test(H1), H1);
t('🔴 tuần cũ thì GẬP', /data-ma="A1" hidden/.test(H1) && /data-ma="A2" hidden/.test(H1), H1);
t('   và mũi tên của hàng tiêu đề nói đúng trạng thái ấy',
  (H1.match(/▾/g) || []).length === 1 && (H1.match(/▸/g) || []).length === 1, H1);

/* Kỳ không đọc được ra số rơi xuống cuối — đó là đơn chưa chốt kỳ, chỗ đúng của nó. */
const H2 = ve([D('X', '(không kỳ)', 'Chờ duyệt tạm ứng'), D('A1', TUAN_A, 'Chờ duyệt tạm ứng')]);
t('⚠️ đơn chưa chốt kỳ rơi xuống CUỐI, không chen lên đầu',
  H2.indexOf('data-ma="A1"') < H2.indexOf('data-ma="X"'), H2);

/* ═══ 2. HÀNG TIÊU ĐỀ NÓI ĐỦ SỐ ════════════════════════════════════════════════ */
const H3 = ve([D('A1', TUAN_A, 'Chờ duyệt tạm ứng', 100), D('A2', TUAN_A, 'Chờ cấp tạm ứng', 300)]);
t('🔴 tiêu đề nói số đơn của tuần', /· 2 đơn/.test(H3), H3);
t('🔴 và tổng tạm ứng của tuần', /tạm ứng 400đ/.test(H3), H3);
/* 🔴 HAI VIỆC KHÁC NHAU, HAI NÚT KHÁC NHAU. Gộp "duyệt" với "cấp tiền" vào một nút là bấm một
   cái khai luôn đã chuyển tiền cho một khoản chưa chuyển. */
/* ⚠️ BÁM VÀO VIỆC NÚT LÀM + SỐ NÓ ĐẾM, KHÔNG GHIM NGUYÊN VĂN NHÃN. Ghim cả câu chữ là mọi
   lượt sửa chữ cho dễ đọc đều thành một phép đỏ — mà chữ trên nút thì anh Thắng đổi luôn. */
function nutCua(h, viec) {
  const re = new RegExp("<button[^>]*dvLamCaTuan\\([^)]*'" + viec + "'\\)[^>]*>([^<]*)</button>");
  const m = re.exec(h); return m ? m[1] : null;
}
t('🔴 có nút làm cả tuần cho việc DUYỆT, và đếm đúng 1 đơn chờ duyệt',
  /\(1\)/.test(nutCua(H3, 'duyet') || ''), nutCua(H3, 'duyet'));
t('🔴 có nút làm cả tuần cho việc CẤP TIỀN, và đếm đúng 1 đơn chờ cấp',
  /\(1\)/.test(nutCua(H3, 'cap') || ''), nutCua(H3, 'cap'));
/* ⚠️ Hai nút phải PHÂN BIỆT ĐƯỢC bằng mắt — cùng chữ là kế toán bấm nhầm việc. */
t('⚠️ hai nút không trùng chữ', nutCua(H3, 'duyet') !== nutCua(H3, 'cap'),
  [nutCua(H3, 'duyet'), nutCua(H3, 'cap')]);
/* ⚠️ Không có đơn ở bước nào thì KHÔNG bày nút của bước ấy — bày ra là bấm vào rồi ăn một câu
   "tuần này không còn đơn nào", tức một nút chỉ để làm người ta mất công. */
const H4 = ve([D('A1', TUAN_A, 'Đã cấp tạm ứng', 100)]);
t('⚠️ tuần chỉ toàn đơn đã cấp → KHÔNG bày nút nào', !/dvLamCaTuan\(/.test(H4), H4);
/* 🔴 NÚT THEO QUYỀN. Bày cho người không có quyền là mời họ bấm vào cửa máy chủ sẽ chối. */
t('🔴 không có quyền → không bày nút làm cả tuần',
  !/dvLamCaTuan\(/.test(ve([D('A1', TUAN_A, 'Chờ duyệt tạm ứng', 100)], { quyen: false })), '');
/* ⚠️ Bấm nút KHÔNG được xổ/gập nhóm — hai việc chồng lên nhau ở cùng một chỗ bấm. */
/* ⚠️ ĐẾM, KHÔNG CHỈ DÒ MỘT LẦN. Hàng tiêu đề có HAI nút; dò `.test()` thì sửa hỏng đúng một
   nút vẫn xanh nhờ nút kia — đã lọt lưới thật lúc phá thử 22/09/2026 (lượt T14). */
{
  const goi = (H3.match(/dvLamCaTuan\(/g) || []).length;
  const chan = (H3.match(/event\.stopPropagation\(\);dvLamCaTuan\(/g) || []).length;
  teq('⚠️ MỌI nút đều chặn sự kiện lan lên hàng tiêu đề (`stopPropagation`)', goi, chan);
  t('   và đúng là có hai nút để đếm', goi === 2, goi);
}

/* ═══ 3. VẠCH NGĂN ĐƠN VỊ VẪN CÒN, VÀ GẬP THEO TUẦN ════════════════════════════ */
/* Anh Thắng 20/09: *"tránh duyệt lộn đơn vị"* — vạch ngăn không được mất khi gom tuần. */
const H5 = ve([D('A1', TUAN_A, 'Chờ duyệt tạm ứng', 100, 'K&H'), D('A2', TUAN_A, 'Chờ duyệt tạm ứng', 200, 'POSH')],
  { nhieuDonVi: true });
t('🔴 hai đơn vị trong một tuần → vẫn có vạch ngăn 🏢', (H5.match(/dv-ngan/g) || []).length === 2, H5);
/* 🔴 VẠCH NGĂN PHẢI MANG CÙNG NHÓM VÀ CÙNG TRẠNG THÁI GẬP. Không thì gập một tuần lại còn trơ
   ra một dòng "🏢 K&H · 3 đơn" chỉ vào ba dòng đã biến mất. */
const H6 = ve([D('A1', TUAN_A, 'Chờ duyệt tạm ứng', 100, 'K&H'), D('A2', TUAN_A, 'Chờ duyệt tạm ứng', 200, 'POSH'),
  D('B1', TUAN_B, 'Chờ duyệt tạm ứng', 300, 'K&H')], { nhieuDonVi: true });
const ngan = H6.match(/<tr class="dv-ngan[^>]*>/g) || [];
t('🔴 vạch ngăn của tuần GẬP cũng bị giấu',
  ngan.filter(function (x) { return /display:none/.test(x); }).length === 2, ngan);
t('   vạch ngăn mang đúng class nhóm của tuần nó thuộc về',
  ngan.every(function (x) { return /dv-ngan dvk\d/.test(x); }), ngan);
/* ⚠️ MỘT ĐƠN VỊ THÌ KHÔNG CHÈN VẠCH — thêm một dòng chữ không mang tin gì. */
t('⚠️ tuần chỉ một đơn vị → không chèn vạch ngăn', !/dv-ngan/.test(H3), H3);

/* ═══ 4. LÀM CẢ TUẦN — TÍCH ĐÚNG NHÓM, BỎ TÍCH CŨ ══════════════════════════════ */
/* 🔴 CHẠY THẬT `dvLamCaTuan()` trên một DOM giả. Đây là chỗ tiền đi, và cái hỏng ở đây không
   nổ — nó chỉ duyệt lố sang tuần khác. */
function oTich(ma, st, gid) {
  const o = { checked: false, _ma: ma, _st: st, _gid: gid,
    getAttribute: function (k) { return k === 'data-st' ? st : (k === 'data-dv' ? this._dv : null); } };
  o.closest = function () { return { getAttribute: function (k) { return k === 'data-dv' ? o._dv : null; } }; };
  return o;
}
function chayLamCaTuan(oS, gid, viec, hoiDap) {
  const goi = { duyet: 0, cap: 0, toast: [] };
  new Function('document', 'toast', 'dvUpdateBar', 'confirm', 'doCapChon', 'doDuyetChon', 'gid', 'viec',
    ham('dvLamCaTuan') + '\ndvLamCaTuan(gid, viec);')(
    { querySelectorAll: function (sel) {
        if (sel === '.dvChk') { return oS; }
        const m = /^tr\.(\S+) \.dvChk$/.exec(sel);
        return m ? oS.filter(function (o) { return o._gid === m[1]; }) : [];
      } },
    function (k, m) { goi.toast.push(m); }, function () {}, function () { return hoiDap !== false; },
    function () { goi.cap++; }, function () { goi.duyet++; }, gid, viec);
  return goi;
}
{
  const A = oTich('A1', 'Chờ duyệt tạm ứng', 'dvk1'), B = oTich('B1', 'Chờ duyệt tạm ứng', 'dvk0');
  const C = oTich('A2', 'Chờ cấp tạm ứng', 'dvk1');
  B.checked = true;                                   // người dùng đang tích dở một đơn tuần khác
  const r = chayLamCaTuan([A, B, C], 'dvk1', 'duyet');
  teq('🔴 gọi đúng cửa `doDuyetChon()`', 1, r.duyet);
  t('🔴 tích đúng đơn CHỜ DUYỆT của tuần ấy', A.checked === true, A.checked);
  /* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA CẢ BÀI. Không bỏ tích cũ thì một cú bấm "Duyệt cả tuần này"
     duyệt luôn đơn tuần kia — và hộp xác nhận chỉ nói tổng số, không nói chúng thuộc tuần nào. */
  t('🔴 BỎ tích cũ của tuần khác trước khi làm', B.checked === false, B.checked);
  /* ⚠️ Và không đụng đơn ở bước khác trong CÙNG tuần: "duyệt" với "cấp tiền" là hai việc. */
  t('⚠️ không tích đơn ở bước khác dù cùng tuần', C.checked === false, C.checked);
}
{
  const A = oTich('A1', 'Chờ cấp tạm ứng', 'dvk0');
  const r = chayLamCaTuan([A], 'dvk0', 'cap');
  teq('🔴 việc "cap" đi qua cửa `doCapChon()`', 1, r.cap);
  teq('   và KHÔNG gọi nhầm sang duyệt', 0, r.duyet);
}
{
  /* Tuần không còn đơn ở bước ấy → nói một câu, không gọi cửa nào. */
  const A = oTich('A1', 'Đã cấp tạm ứng', 'dvk0');
  const r = chayLamCaTuan([A], 'dvk0', 'duyet');
  teq('🔴 tuần không còn đơn ở bước ấy → không gọi cửa nào', 0, r.duyet + r.cap);
  t('   và nói ra một câu', r.toast.length === 1 && /không còn đơn nào/.test(r.toast[0]), r.toast);
}
{
  /* 🔴 TUẦN LẪN NHIỀU ĐƠN VỊ → HỎI THÊM MỘT CÂU, và trả lời KHÔNG thì bỏ sạch tích. */
  const A = oTich('A1', 'Chờ duyệt tạm ứng', 'dvk0'), B = oTich('A2', 'Chờ duyệt tạm ứng', 'dvk0');
  A._dv = 'K&H'; B._dv = 'POSH';
  const r = chayLamCaTuan([A, B], 'dvk0', 'duyet', false);
  teq('🔴 lẫn hai đơn vị + trả lời KHÔNG → không duyệt gì', 0, r.duyet);
  t('🔴 và trả lại bảng sạch tích, không để lại bẫy cho cú bấm sau',
    A.checked === false && B.checked === false, [A.checked, B.checked]);
  const A2 = oTich('A1', 'Chờ duyệt tạm ứng', 'dvk0'), B2 = oTich('A2', 'Chờ duyệt tạm ứng', 'dvk0');
  A2._dv = 'K&H'; B2._dv = 'POSH';
  teq('   trả lời CÓ thì vẫn làm', 1, chayLamCaTuan([A2, B2], 'dvk0', 'duyet', true).duyet);
  /* ⚠️ Một đơn vị thì KHÔNG hỏi — hỏi mỗi lượt là người ta bấm OK theo phản xạ, và tới lúc câu
     hỏi thật sự cần thì nó cũng bị bấm qua. */
  const A3 = oTich('A1', 'Chờ duyệt tạm ứng', 'dvk0'); A3._dv = 'K&H';
  teq('⚠️ một đơn vị → không hỏi, làm thẳng', 1, chayLamCaTuan([A3], 'dvk0', 'duyet', false).duyet);
}

/* ═══ 5. 🔴 CHỈ BẢNG CHI PHÍ CƠ SỞ GOM TUẦN ════════════════════════════════════ */
/* Anh Thắng: *"Còn các chi phí khác thì kiểm tra và duyệt khi gửi"*. Dự án chạy theo khoảng
   ngày setup do nhân viên gõ, không theo tuần — bó chúng vào tuần là bày ra một trục sai. */
/* ⚠️ Bốn lời gọi: định nghĩa + `renderDuyet` (bảng chờ duyệt) + `_dvVeBang` (hai bảng kia)
   + `_qtVeBangTT` (bảng chờ thanh toán ở tab Quyết toán, 23/09/2026 — cũng là sổ `don`, tức
   cũng là chi phí cơ sở). Đếm để một khối lạ nào mượn nó là phép này đỏ ngay. */
t('🔴 chỉ các bảng CHI PHÍ CƠ SỞ (sổ `don`) đi qua `_dvGomTuan`',
  (HTML.match(/_dvGomTuan\(/g) || []).length === 4, (HTML.match(/_dvGomTuan\(/g) || []));
t('   trong đó có bảng chờ thanh toán của tab Quyết toán', /_dvGomTuan\(rows,/.test(ham('_qtVeBangTT')));
t('🔴 khối Lệnh tạm ứng KHÔNG bị gom tuần', !/_dvGomTuan/.test(ham('_veLenhTU')), '');
t('🔴 khối Lệnh dự án KHÔNG bị gom tuần', !/_dvGomTuan/.test(ham('loadLenhDA')), '');
t('🔴 khối Hạng mục chưa chốt KHÔNG bị gom tuần', !/_dvGomTuan/.test(ham('loadDonHM')), '');
/* ⚠️ Và hai bảng của màn Quyết toán vẫn gọi thẳng `_tachDonVi` như cũ — lượt này không đụng. */
t('⚠️ màn Quyết toán giữ nguyên lối cũ',
  /el\('qtBody'\+hoa\)\.innerHTML=_tachDonVi\(/.test(HTML)
  && /el\('qtBodyChuaNop'\)\.innerHTML=_tachDonVi\(/.test(HTML), '');

/* ═══ 6. BA BẢNG THEO BƯỚC (23/09/2026) ═══════════════════════════════════════════ */
/* Anh Thắng: *"tách 2 bảng riêng để dễ theo dõi đơn"*, *"check 1 lần duyệt và chi 1 lần"*.
   Duyệt và chi là hai động tác của hai người, nên là hai bảng đứng cạnh nhau — không phải hai
   lựa chọn trong một ô lọc bắt người ta nhớ mà bấm sang. */
t('🔴 ô lọc trạng thái đã BỎ — ba bảng thay nó', !/id="duyetFilter"/.test(HTML));
['duyetBody', 'duyetBodyChi', 'duyetBodyMua'].forEach(function (id) {
  t('🔴 có bảng `' + id + '`', new RegExp('<tbody id="' + id + '"').test(HTML));
});
const RD = ham('renderDuyet');
t('⚠️ bốc được `renderDuyet`', RD.length > 400, RD.length);
/* 🔴 BA TIỀN TỐ NHÓM KHÁC NHAU. `dvNhomToggle()` tìm theo class trên CẢ trang; ba bảng cùng
   dùng `dvk0` thì bấm xổ tuần ở bảng này là tuần ở bảng kia cũng xổ theo. */
t('🔴 ba bảng dùng ba tiền tố nhóm khác nhau (dvk · dck · dmk)',
  /tien:'dvk'/.test(RD) && /'dck'\)/.test(RD) && /'dmk'\)/.test(RD), RD.slice(-900));
/* 🔴 MỘT THÂN DÒNG DUY NHẤT cho ba bảng — chép ra ba chỗ là ngày thêm một cột, hai bảng lệch
   một ô mà trông vẫn bình thường. */
t('🔴 ba bảng dùng chung `_dvHangHtml`', /return _dvHangHtml\(d, gid, gap\);/.test(RD) && /_dvHangHtml\(d, gid, gap\)/.test(ham('_dvVeBang')));
t('   và không còn bản chép nào của thân dòng ngoài `_dvHangHtml`',
  (HTML.match(/class="dvChk" data-m=/g) || []).length === 1);
/* Chia đúng ba trạng thái — chạy thật phép chia. */
{
  const chia = new Function('list',
    "var lCho=list.filter(function(d){ return d.trangThai==='Chờ duyệt tạm ứng'; });"
    + "var lChi=list.filter(function(d){ return d.trangThai==='Chờ cấp tạm ứng'; });"
    + "var lMua=list.filter(function(d){ return d.trangThai==='Đã cấp tạm ứng'; });"
    + 'return [lCho.length, lChi.length, lMua.length];');
  t('⚠️ `renderDuyet` chia ba danh sách đúng bằng ba câu lọc ấy',
    /var lCho=list\.filter\(function\(d\)\{ return d\.trangThai==='Chờ duyệt tạm ứng'; \}\);/.test(RD)
    && /var lChi=list\.filter\(function\(d\)\{ return d\.trangThai==='Chờ cấp tạm ứng'; \}\);/.test(RD)
    && /var lMua=list\.filter\(function\(d\)\{ return d\.trangThai==='Đã cấp tạm ứng'; \}\);/.test(RD));
  teq('   ba trạng thái → ba bảng, không rơi đơn nào', [1, 1, 1],
    chia([D('a', TUAN_A, 'Chờ duyệt tạm ứng'), D('b', TUAN_A, 'Chờ cấp tạm ứng'), D('c', TUAN_A, 'Đã cấp tạm ứng')]));
}

/* ═══ 7. 🔴 ĐỐI CHIẾU QUỸ TUẦN Ở MÀN DUYỆT — TÍNH TRÊN TOÀN BỘ ĐƠN ═══════════════ */
/* Anh Thắng: *"Sang tuần thừa thiếu 1 lần đó"*. Con số này nguy nếu sai theo hướng "còn dư":
   kế toán duyệt tiếp một tuần trên một cái quỹ không có thật. Nên nó phải tính trên MỌI đơn
   của khối — cả đơn đã quyết toán — chứ không trên danh sách đang hiện của màn Duyệt (chỉ có
   khâu tạm ứng), và không được đổi theo mấy ô lọc. */
const NEN_QUY = [ham('_kyVal'), ham('_laChim'), ham('_qtSums'), ham('_dvQuyTheoKy')].join('\n');
t('⚠️ bốc được nền đối chiếu quỹ', NEN_QUY.replace(/\s/g, '').length > 400, NEN_QUY.length);
function quy(dons, seed) {
  return new Function('BOOT', '_hopKhoi', 'el', NEN_QUY + '\nreturn _dvQuyTheoKy();')(
    { dons: dons, soDuDauKy: seed || 0 }, function () { return true; },
    /* 🔴 `el()` NỔ nếu bị gọi — hàm này KHÔNG được đọc ô lọc nào. */
    function (id) { throw new Error('_dvQuyTheoKy đọc ô lọc ' + id); });
}
const DQ = (ma, ky, st, tu, mua) => ({ maDon: ma, ky: ky, trangThai: st, tamUng: tu, thucChiCN: mua, soThucMua: '' });
{
  /* Tuần A: tạm ứng 1000, tiêu 800 (đã quyết toán) → dư 200 mang sang B.
     Tuần B: tạm ứng 500 đang chờ duyệt → giải ngân thêm = 500 − 200 = 300. */
  const Q = quy([DQ('a', TUAN_A, 'Đã quyết toán', 1000, 800), DQ('b', TUAN_B, 'Chờ duyệt tạm ứng', 500, 0)]);
  teq('🔴 tuần sau nhận đúng số dư tuần trước (1000 − 800 = 200)', 200, Q[TUAN_B].carry);
  teq('🔴 giải ngân thêm = tạm ứng tuần này − dư tuần trước', 300, Q[TUAN_B].giaiNgan);
  t('   tuần sau có "kỳ trước"', Q[TUAN_B].hasPrev === true && Q[TUAN_B].isOpen === false);
  t('   tuần đầu là kỳ mở, không có kỳ trước khi chưa seed', Q[TUAN_A].isOpen === true && Q[TUAN_A].hasPrev === false);
}
{
  /* 🔴 PHÉP CỐT LÕI: đơn ĐÃ QUYẾT TOÁN của tuần trước không nằm trong màn Duyệt, nhưng PHẢI được
     cộng vào quỹ. Tính trên danh sách đang hiện là bỏ sót đúng nó → "dư kỳ trước" = 0 thay vì
     −300 (thiếu), và kế toán duyệt tuần mới tưởng quỹ đang khớp. */
  const Q = quy([DQ('a', TUAN_A, 'Đã quyết toán', 500, 800), DQ('b', TUAN_B, 'Chờ duyệt tạm ứng', 400, 0)]);
  teq('🔴 tuần trước THIẾU 300 (đơn đã quyết toán) vẫn được mang sang', -300, Q[TUAN_B].carry);
}
{
  /* Đơn CHÌM (đã cấp mà chưa gửi quyết toán) — `_qtSums` không cộng thực chi của nó, y như màn
     Quyết toán. Hai màn phải nói cùng một con số. */
  const Q = quy([DQ('a', TUAN_A, 'Đã cấp tạm ứng', 1000, 999), DQ('b', TUAN_B, 'Chờ duyệt tạm ứng', 100, 0)]);
  teq('⚠️ đơn chìm: cộng tạm ứng, KHÔNG cộng thực chi (đúng luật `_qtSums`)', 1000, Q[TUAN_B].carry);
}
{
  const Q = quy([DQ('a', TUAN_A, 'Chờ duyệt tạm ứng', 100, 0)], 250);
  teq('⚠️ tuần đầu seed bằng số dư đầu kỳ (`BOOT.soDuDauKy`)', 250, Q[TUAN_A].carry);
  t('   và lúc ấy tuần đầu CÓ "đầu kỳ"', Q[TUAN_A].hasPrev === true && Q[TUAN_A].isOpen === true);
}
t('🔴 hàm không đọc ô lọc nào (không nổ khi `el()` nổ)', (function () { try { quy([DQ('a', TUAN_A, 'Chờ duyệt tạm ứng', 1, 0)]); return true; } catch (e) { return false; } })());
t('⚠️ kỳ không đọc được ra số bị bỏ khỏi chuỗi quỹ', !quy([DQ('x', '(không kỳ)', 'Chờ duyệt tạm ứng', 1, 0)])['(không kỳ)']);

/* Dòng đối chiếu vẽ vào đúng bảng — và CHỈ bảng "chờ duyệt". */
t('🔴 bảng "chờ duyệt" bật `recon`', /\}, \{recon:true, tien:'dvk'\}\);/.test(RD), RD.slice(-400));
t('🔴 hai bảng kia KHÔNG bật `recon` (một con số, in một lần)', !/recon/.test(ham('_dvVeBang')), ham('_dvVeBang'));
{
  /* Vẽ thật với recon bật: dòng quỹ phải nằm ngay dưới tiêu đề tuần, mang class nhóm, và gập
     theo tuần. */
  const veQuy = new Function('BOOT', '_hopKhoi', 'esc', 'money', 'canDo', 'list',
    NEN + '\n' + NEN_QUY + '\n' + ham('_qtReconHtml')
    + '\nreturn _dvGomTuan(list, function(d,gid,gap){ return "<tr class=\'dvm "+gid+"\' data-ma=\'"+d.maDon+"\'></tr>"; }, {recon:true, tien:"dvk"});')(
    { dons: [DQ('a', TUAN_A, 'Đã quyết toán', 1000, 800), DQ('b', TUAN_B, 'Chờ duyệt tạm ứng', 500, 0)], soDuDauKy: 0 },
    function () { return true; }, function (x) { return String(x == null ? '' : x); },
    function (x) { return String(Number(x) || 0); }, function () { return true; },
    [DQ('b', TUAN_B, 'Chờ duyệt tạm ứng', 500, 0), DQ('a2', TUAN_A, 'Chờ duyệt tạm ứng', 50, 0)]);
  t('🔴 có dòng đối chiếu quỹ trong bảng', /dvFlow/.test(veQuy), veQuy.slice(0, 300));
  t('🔴 dòng quỹ nói đúng "dư kỳ trước 200"', /dư kỳ trước <b[^>]*>200</.test(veQuy), veQuy);
  t('   và "giải ngân thêm 300"', /giải ngân thêm <b[^>]*>300</.test(veQuy), veQuy);
  t('🔴 dòng quỹ đứng NGAY DƯỚI tiêu đề tuần, TRƯỚC các dòng đơn',
    veQuy.indexOf('dvGrpHdr') < veQuy.indexOf('dvFlow') && veQuy.indexOf('dvFlow') < veQuy.indexOf("data-ma='b'"), '');
  const flows = veQuy.match(/<tr class="dvFlow[^>]*>/g) || [];
  t('🔴 dòng quỹ của tuần GẬP cũng bị giấu, mang đúng class nhóm',
    flows.length === 2 && flows.filter(function (x) { return /display:none/.test(x); }).length === 1
    && flows.every(function (x) { return /dvFlow dvk\d/.test(x); }), flows);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: màn Duyệt gom theo tuần, làm cả tuần không lố sang tuần khác.');
