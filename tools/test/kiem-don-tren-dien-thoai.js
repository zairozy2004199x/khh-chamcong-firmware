/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * TRANG ĐƠN CHI PHÍ TRÊN ĐIỆN THOẠI — GỌN LẠI BỐN CHỖ
 *
 * Anh Thắng 06/09/2026 gửi ảnh màn điện thoại của một đơn đang "Chờ duyệt tạm ứng". Bốn thứ
 * chiếm gần hết màn trước khi thấy dòng chi nào:
 *   1. Sáu nút xếp thành ba hàng.
 *   2. Nút đỏ đậm "Xoá (admin)" — xoá vĩnh viễn kể cả đơn đã xuất MISA — nổi bật nhất màn.
 *   3. Dải trạng thái nói một lần, khối to ngay dưới nói lại y hệt.
 *   4. "Lịch sử chỉnh đơn (22)" chặn trước nội dung — mà 22 dòng ấy chỉ là 11 việc.
 *
 * 🔴 RÀNG BUỘC XUYÊN SUỐT: KHÔNG ĐƯỢC ĐỔI GÌ TRÊN MÁY TÍNH. Màn rộng vốn không có vấn đề nào ở
 *    trên; mọi thay đổi chỉ được sống dưới mốc 820px. Một bản "sửa cho điện thoại" mà làm gãy
 *    màn kế toán đang dùng hằng ngày thì lỗ nặng hơn lãi.
 *
 * ⚠️ BỐC HÀM THẬT TỪ app.html RA CHẠY, không chép lại. Phần CSS thì soi luật dựng — không có
 *    trình duyệt ở đây nên bài này KHÔNG canh được bố cục thật sự trông ra sao; nó canh được
 *    rằng luật CSS nói đúng điều mình định nói, và rằng mã JS xử đúng.
 *
 * Chạy: node tools/test/kiem-don-tren-dien-thoai.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const CSS  = fs.readFileSync('wordpress/vhcp-chi-phi/assets/css/vhcp.css', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them != null ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

/* Bóc chú thích trước khi soi — bài kiểm bên nhánh Ghế đã dính hai lần cả tự-xanh lẫn tự-đỏ vì
   chú thích nhắc lại đúng chuỗi đang canh. Chỉ bóc chú thích KHỐI, và đòi dấu mở phải đứng sau
   khoảng trắng (chuỗi kiểu MIME "image" gạch chéo sao có hai ký tự cuối trùng dấu mở). */
function boCT(x) { return x.replace(/(^|[\s;{}(,:])\/\*[\s\S]*?\*\//g, '$1 '); }
const CSS_MA  = boCT(CSS);
const HTML_MA = HTML.replace(/<!--[\s\S]*?-->/g, ' ');

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. CỤM NÚT PHỤ — RÚT VÀO SAU "⋯", VÀ CHỈ TRÊN MÁY HẸP
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 có nút "⋯ Thêm"', HTML_MA.indexOf('id="btnMoreDon"') > 0);
t('🔴 có khối bọc nút phụ', HTML_MA.indexOf('class="db-phu"') > 0);

/* Năm nút phụ phải NẰM TRONG khối bọc — sót một nút ở ngoài là nó vẫn chiếm một hàng riêng
   trên điện thoại, tức là vá hụt đúng cái đang đi vá. */
/* 🔴 CẮT KHỐI BỌC BẰNG CÁCH ĐẾM THẺ CÂN BẰNG, KHÔNG BẰNG MỘT CHUỖI ĐÓNG.
   Bản đầu của phép này cắt tới chuỗi `</div></div>` gần nhất — phá thử bắt được ngay: đục cho
   thẻ đóng nhảy lên sớm (đẩy hai nút Xoá ra NGOÀI cụm phụ) thì chuỗi ấy vẫn tìm thấy ở chỗ
   khác, nên đoạn cắt ra vẫn bao trùm cả hai nút và bài vẫn xanh. Mà đúng hai nút ấy là thứ cả
   đợt sửa này đi vá. Đếm thẻ thì không lừa được. */
const iMo = HTML_MA.indexOf('<div class="db-phu"');
function catKhoi(src, tu) {
  if (tu < 0) return '';
  let i = tu, sau = 0;
  const re = /<div\b|<\/div>/g;
  re.lastIndex = tu;
  let m;
  while ((m = re.exec(src))) {
    sau += (m[0] === '</div>') ? -1 : 1;
    if (sau === 0) return src.slice(tu, m.index + m[0].length);
    i = m.index;
  }
  return '';
}
const khoiPhu = catKhoi(HTML_MA, iMo);
t('bốc được khối bọc', iMo > 0 && khoiPhu.length > 200, khoiPhu.length);
/* Đối chứng cho chính phép cắt: khối cắt ra phải KẾT THÚC bằng thẻ đóng, không phải cắt giữa
   chừng — cắt hụt thì mọi phép "nút X nằm trong cụm" đỏ oan, cắt thừa thì chúng xanh oan. */
t('đối chứng: khối cắt ra đóng đúng thẻ', /<\/div>$/.test(khoiPhu.trim()), khoiPhu.slice(-60));
[ 'btnThungRac', 'btnChuyenDV', 'btnChuyenKy', 'btnDelDon', 'btnDelDonAdmin' ].forEach(function (id) {
  t('🔴 nút ' + id + ' nằm trong cụm phụ', khoiPhu.indexOf('id="' + id + '"') > 0, khoiPhu.slice(0, 120));
});

/* 🔴 NÚT HÀNH ĐỘNG CHÍNH PHẢI Ở NGOÀI. Đây là thứ người ta vào trang để bấm ("Gửi xin tạm ứng",
   "Gửi quyết toán"); giấu nó sau "⋯" là giấu đúng việc chính. */
t('🔴 nút hành động chính KHÔNG bị nhét vào cụm phụ', khoiPhu.indexOf('id="btnAction"') < 0, null);
t('và vẫn có mặt trên thanh', HTML_MA.indexOf('id="btnAction"') > 0, null);
/* Ô chọn đơn và "Tạo đơn mới" cũng phải ở ngoài — đó là hai thứ dùng nhiều nhất. */
t('ô chọn đơn ở ngoài cụm phụ', khoiPhu.indexOf('id="donSel"') < 0, null);
t('nút Tạo đơn mới ở ngoài cụm phụ', khoiPhu.indexOf('id="btnNewDon"') < 0, null);

/* ---- Luật CSS: máy tính không đổi gì ---- */
/* `display:contents` nghĩa là khối bọc BIẾN MẤT khỏi cây bố cục — mấy nút vẫn là con trực tiếp
   của hàng flex, y hệt trước khi bọc. Đây là cả lý do chọn cách bọc này. */
t('🔴 máy tính: khối bọc trong suốt (display:contents)',
  /\.db-phu\{display:contents\}/.test(CSS_MA.replace(/\s+/g, '')), null);
t('🔴 máy tính: KHÔNG bày nút "⋯"',
  /#btnMoreDon\{display:none\}/.test(CSS_MA.replace(/\s+/g, '')), null);

/* Mọi thứ vừa thêm phải nằm trong đúng một khối @media 820px. Rải ra nhiều mốc là mai sau đổi
   một chỗ, quên chỗ kia, và hai nửa của cùng một cơ chế chạy lệch nhau. */
const iMedia = CSS_MA.indexOf('@media(max-width:820px){\n    #btnMoreDon');
t('🔴 có khối @media 820px riêng cho cụm nút', iMedia > 0, CSS_MA.slice(0, 80));
const khoiMedia = iMedia > 0 ? CSS_MA.slice(iMedia, CSS_MA.indexOf('\n  }', iMedia)) : '';
t('máy hẹp: hiện nút "⋯"', /#btnMoreDon\{display:inline-block\}/.test(khoiMedia.replace(/\s+/g, '')), khoiMedia);
t('🔴 máy hẹp: cụm phụ ẩn khi chưa mở', /\.db-phu\{display:none\}/.test(khoiMedia.replace(/\s+/g, '')), khoiMedia);
t('🔴 và chỉ hiện khi hàng có lớp "mo"', /\.db-hang1\.mo\.db-phu\{display:flex/.test(khoiMedia.replace(/\s+/g, '')), khoiMedia);
/* Vạch ngăn trước hai nút Xoá — chúng hại nhất nên không được nằm lẫn giữa đám nút thường. */
t('🔴 có vạch ngăn trước hai nút Xoá', HTML_MA.indexOf('class="db-vach"') > 0, null);
t('vạch ấy chỉ hiện trên máy hẹp khi mở cụm',
  /\.db-vach\{display:none\}/.test(CSS_MA.replace(/\s+/g, ''))
  && /\.db-hang1\.mo\.db-vach\{display:block/.test(khoiMedia.replace(/\s+/g, '')), null);
/* Vạch phải đứng TRƯỚC hai nút xoá trong DOM, không thì nó ngăn nhầm chỗ. */
t('🔴 vạch đứng trước nút Xoá đơn, không phải sau',
  khoiPhu.indexOf('db-vach') > 0 && khoiPhu.indexOf('db-vach') < khoiPhu.indexOf('btnDelDon'), null);

/* ---- Bấm "⋯": bốc hàm thật ra chạy ---- */
const iH = HTML.indexOf('function moreDon(){');
const jH = HTML.indexOf('\n  }', iH) + 4;
t('bốc được hàm bật/tắt cụm nút', iH > 0 && jH > iH);
const fnMore = (iH > 0 && jH > iH) ? HTML.slice(iH, jH) : '';

function bam(soLan) {
  const lop = { _c: [], contains: function (x) { return this._c.indexOf(x) >= 0; },
    toggle: function (x) { const i = this._c.indexOf(x); if (i < 0) { this._c.push(x); return true; } this._c.splice(i, 1); return false; } };
  const hang = { classList: lop };
  const nut = { textContent: '⋯ Thêm', attrs: {},
    setAttribute: function (k, v) { this.attrs[k] = v; } };
  const f = new Function('HANG', 'NUT',
    'var document={querySelector:function(){ return HANG; }};\n'
    + 'function el(id){ return id==="btnMoreDon" ? NUT : null; }\n'
    + fnMore + '\nreturn moreDon;');
  const g = f(hang, nut);
  for (let i = 0; i < soLan; i++) g();
  return { mo: lop.contains('mo'), nhan: nut.textContent, aria: nut.attrs['aria-expanded'] };
}
const b1 = bam(1);
t('🔴 bấm một lần -> cụm nút mở ra', true === b1.mo, b1);
/* Ba chấm không nói được đang mở hay đóng — người ta cần biết để khỏi bấm hai lần rồi tưởng
   nút hỏng. */
t('🔴 và nhãn nút đổi thành "Đóng"', b1.nhan.indexOf('Đóng') >= 0, b1.nhan);
teq('trình đọc màn hình cũng biết', 'true', b1.aria);
const b2 = bam(2);
t('🔴 bấm lần nữa -> đóng lại', false === b2.mo, b2);
t('và nhãn quay về "Thêm"', b2.nhan.indexOf('Thêm') >= 0, b2.nhan);
teq('aria cũng quay về', 'false', b2.aria);
teq('đối chứng: ba lần bấm thì mở', true, bam(3).mo);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. KHỐI TRẠNG THÁI — MỘT LẦN, KHÔNG PHẢI HAI
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iBadge = HTML.indexOf("el('donBadge').innerHTML=_dai");
t('bốc được chỗ dựng khối trạng thái', iBadge > 0);
const dongBadge = iBadge > 0 ? HTML.slice(iBadge, HTML.indexOf(';', iBadge)) : '';
/* 🔴 Chip nhỏ `<span class="badge">Chờ duyệt tạm ứng</span>` nói đúng cái mà khối to nói ngay
   dưới bằng chữ lớn hơn. Bỏ chip, giữ khối. */
t('🔴 không còn chip trạng thái nhỏ nói lại tên trạng thái',
  dongBadge.indexOf("class=\"badge ") < 0 && dongBadge.indexOf('stCls(st)') < 0, dongBadge);
t('🔴 khối trạng thái to vẫn còn', dongBadge.indexOf('_dai') > 0, dongBadge);
t('và mấy dải cảnh báo vẫn còn (trả lại · khoá · kế toán sửa)',
  dongBadge.indexOf('_traBanner') > 0 && dongBadge.indexOf('_kdBanner') > 0
  && dongBadge.indexOf('_ktBanner') > 0, dongBadge);

/* ⚠️ CÁI BỎ ĐI PHẢI LÀ CÁI LẶP, KHÔNG PHẢI THÔNG TIN. Kỳ · cơ sở · người lập là thứ nhận dạng
   đơn, không có ở đâu khác trên màn — nó phải chuyển VÀO khối to, không được biến mất. */
const iDai = HTML.indexOf("var _dai='<div style=\"padding:13px 16px");
t('bốc được khối to', iDai > 0);
const khoiDai = iDai > 0 ? HTML.slice(iDai, HTML.indexOf("+_thanhBuoc(st)", iDai)) : '';
t('🔴 kỳ · cơ sở · người lập chuyển VÀO khối to', khoiDai.indexOf('_nhanDang') > 0, khoiDai);
const iND = HTML.indexOf('var _nhanDang=');
const dongND = iND > 0 ? HTML.slice(iND, HTML.indexOf(';', iND)) : '';
t('dòng nhận dạng có kỳ', dongND.indexOf('r.don.ky') > 0, dongND);
t('có cơ sở', dongND.indexOf('_csD') > 0, dongND);
t('có người lập', dongND.indexOf('r.don.nguoiLap') > 0, dongND);
t('khối to vẫn nói tên trạng thái', khoiDai.indexOf('Đơn đang') > 0, null);
t('vẫn có ba chốt "làm được gì"', khoiDai.indexOf('_lamDuocGi(st)') > 0, null);
t('vẫn có câu giải thích', khoiDai.indexOf('_ct.chu') > 0, null);

/* 🔴 BẢNG `hint` PHẢI ĐI HẲN, KHÔNG ĐƯỢC NẰM LẠI LÀM MÃ CHẾT. Mọi dòng của nó đã có trong
   `_cauTrangThai()` và nói đủ hơn. Để lại là một bảng chữ không ai đọc mà người sau sẽ sửa
   nhầm vào, rồi không hiểu vì sao màn hình không đổi gì. */
t('🔴 bảng hint cũ đã gỡ hẳn', HTML_MA.indexOf("'⏳ chờ Quản lý/Kế toán duyệt tạm ứng'") < 0, null);
t('và câu ấy vẫn được nói ở khối to (không mất thông tin)',
  HTML.indexOf('Đang chờ <b>quản lý duyệt</b> số tạm ứng') > 0, null);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. LỊCH SỬ — GỘP CẶP TRÙNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const iG = HTML.indexOf('function _suGop(ds){');
const jG = HTML.indexOf('\n  }', iG) + 4;
t('bốc được hàm gộp sử', iG > 0 && jG > iG);
const fnGop = (iG > 0 && jG > iG) ? HTML.slice(iG, jG) : '';
const gop = new Function(fnGop + '\nreturn _suGop;')();

/* Đúng ca trong ảnh anh Thắng gửi: cùng người, cùng giây, cùng nội dung, hai tên việc khác nhau. */
const CA = [
  { nguoi: 'Nguyễn Mai Anh', vaiTro: 'Nhân viên', tg: '31/08/2026 16:09:49',
    hanhDong: 'Thêm dòng', chiTiet: 'MKT - HOẠT NÁO · 1.000.000đ' },
  { nguoi: 'Nguyễn Mai Anh', vaiTro: 'Nhân viên', tg: '31/08/2026 16:09:49',
    hanhDong: 'Thêm hạng mục xin', chiTiet: 'MKT - HOẠT NÁO · 1.000.000đ' },
];
const raCA = gop(CA);
teq('🔴 hai dòng của cùng một thao tác -> gộp còn một', 1, raCA.length);
t('🔴 giữ CẢ HAI tên việc, tra ngược ra sổ gốc được',
  raCA[0].hanhDong.indexOf('Thêm dòng') >= 0 && raCA[0].hanhDong.indexOf('Thêm hạng mục xin') >= 0,
  raCA[0].hanhDong);
teq('giữ nguyên người', 'Nguyễn Mai Anh', raCA[0].nguoi);
teq('giữ nguyên mốc thời gian', '31/08/2026 16:09:49', raCA[0].tg);
teq('giữ nguyên nội dung', 'MKT - HOẠT NÁO · 1.000.000đ', raCA[0].chiTiet);
teq('và vai trò', 'Nhân viên', raCA[0].vaiTro);

/* 🔴 THIẾU MỘT TRONG BA ĐIỀU KIỆN THÌ KHÔNG GỘP. Đúng lúc cần sổ này là lúc đang truy ai đổi
   con số nào — gộp nhầm hai lần sửa thật thành một là xoá mất chính câu trả lời. */
teq('🔴 khác GIÂY -> giữ hai dòng', 2, gop([
  { nguoi: 'A', tg: '16:09:49', hanhDong: 'Thêm dòng', chiTiet: 'X' },
  { nguoi: 'A', tg: '16:09:50', hanhDong: 'Thêm hạng mục xin', chiTiet: 'X' } ]).length);
teq('🔴 khác NGƯỜI -> giữ hai dòng', 2, gop([
  { nguoi: 'A', tg: '16:09:49', hanhDong: 'Thêm dòng', chiTiet: 'X' },
  { nguoi: 'B', tg: '16:09:49', hanhDong: 'Thêm hạng mục xin', chiTiet: 'X' } ]).length);
teq('🔴 khác NỘI DUNG -> giữ hai dòng', 2, gop([
  { nguoi: 'A', tg: '16:09:49', hanhDong: 'Thêm dòng', chiTiet: 'X' },
  { nguoi: 'A', tg: '16:09:49', hanhDong: 'Thêm hạng mục xin', chiTiet: 'Y' } ]).length);
/* Hai dòng giống hệt cả tên việc = hai lần sửa thật trùng nhau, không phải một thao tác ghi hai
   chỗ. Không gộp. */
teq('🔴 CÙNG tên việc -> KHÔNG gộp (là hai lần sửa thật)', 2, gop([
  { nguoi: 'A', tg: '16:09:49', hanhDong: 'Sửa số tiền', chiTiet: 'X' },
  { nguoi: 'A', tg: '16:09:49', hanhDong: 'Sửa số tiền', chiTiet: 'X' } ]).length);

/* Ca trong ảnh: 22 dòng, xen kẽ từng cặp -> phải ra 11 việc. */
const HAI_MUOI_HAI = [];
for (let i = 0; i < 11; i++) {
  HAI_MUOI_HAI.push({ nguoi: 'Nguyễn Mai Anh', tg: 'lúc ' + i, hanhDong: 'Thêm dòng', chiTiet: 'mục ' + i });
  HAI_MUOI_HAI.push({ nguoi: 'Nguyễn Mai Anh', tg: 'lúc ' + i, hanhDong: 'Thêm hạng mục xin', chiTiet: 'mục ' + i });
}
teq('🔴 22 dòng của 11 việc -> đếm ra 11', 11, gop(HAI_MUOI_HAI).length);
/* Không gộp bắc cầu: ba dòng cùng giây không được nuốt thành một nếu dòng thứ ba là việc khác. */
teq('không gộp bắc cầu qua dòng thứ ba', 2, gop([
  { nguoi: 'A', tg: 'T', hanhDong: 'Thêm dòng', chiTiet: 'X' },
  { nguoi: 'A', tg: 'T', hanhDong: 'Thêm hạng mục xin', chiTiet: 'X' },
  { nguoi: 'A', tg: 'T', hanhDong: 'Xoá dòng', chiTiet: 'Y' } ]).length);
teq('danh sách rỗng vẫn chạy', 0, gop([]).length);
teq('không truyền gì cũng không vỡ', 0, gop(null).length);

/* ⚠️ GỘP Ở MÀN HÌNH, KHÔNG ĐỘNG VÀO SỔ. Sổ chỉnh đơn là bằng chứng — bớt một dòng khỏi sổ để
   màn hình gọn là đổi bằng chứng lấy thẩm mỹ. Canh điều đó bằng cách đếm: hàm gộp chỉ được
   nhắc tới ĐÚNG HAI chỗ — nơi khai nó, và nơi vẽ danh sách. Xuất hiện chỗ thứ ba là nó đã lọt
   vào một đường nào khác, và đường ấy nhiều khả năng là đường ghi. */
t('🔴 gộp chạy đúng lúc VẼ danh sách', HTML.indexOf('var it=_suGop((r&&r.items)||[]);') > 0, null);
teq('🔴 và chỉ có ĐÚNG HAI chỗ nhắc tới nó: chỗ khai và chỗ vẽ', 2,
  (HTML_MA.match(/_suGop/g) || []).length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. LỊCH SỬ GẬP SẴN TRÊN MÁY HẸP — VÀ CHỈ TRÊN MÁY HẸP
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('🔴 có đo bề rộng màn', HTML.indexOf("window.matchMedia('(max-width:820px)')") > 0, null);
/* Một hệ mốc, không đẻ thêm mốc mới: JS phải dùng đúng con số mà CSS dùng cho cụm nút. */
teq('🔴 JS và CSS dùng CÙNG mốc 820px', 1,
  (HTML.match(/max-width:820px/g) || []).length);
t('và CSS cũng ở 820px', CSS_MA.indexOf('@media(max-width:820px)') > 0, null);
/* Máy hẹp: cả khối nằm trong <details> gập sẵn (không có thuộc tính `open`). */
const iHep = HTML.indexOf('if(_hep){');
t('bốc được nhánh máy hẹp', iHep > 0);
const nhanhHep = iHep > 0 ? HTML.slice(iHep, HTML.indexOf('return;', iHep)) : '';
t('🔴 máy hẹp: gói cả khối vào <details>', nhanhHep.indexOf('<details>') > 0, nhanhHep.slice(0, 200));
t('🔴 và GẬP SẴN (không có thuộc tính open)', nhanhHep.indexOf('<details open') < 0, null);
/* Không mở ra cũng phải biết trong đó có gì, để quyết định có đáng mở hay không. */
t('🔴 nhãn nói ra SỐ việc', nhanhHep.indexOf("('+it.length+')") > 0, nhanhHep.slice(0, 300));
t('và nói rõ bấm để xem', nhanhHep.indexOf('bấm để xem') > 0, null);
/* Mở ra thì cuộn trong khung riêng — không kéo dài trang, dù 11 hay 300 việc. */
t('🔴 mở ra thì cuộn trong khung riêng, không kéo dài trang',
  nhanhHep.indexOf('max-height:300px;overflow:auto') > 0, null);
/* Máy tính: nhánh cũ giữ nguyên — 5 dòng gần nhất + <details> cho phần còn lại. */
t('🔴 máy tính giữ nguyên cách cũ: 5 dòng gần nhất',
  HTML.indexOf('h+=_suDongHtml(it.slice(0,5));') > 0, null);
t('và vẫn còn khối "xem dòng cũ hơn" với nút Thu gọn',
  HTML.indexOf("id=\"suCu\"") > 0 && HTML.indexOf('suThuGon()') > 0, null);

/* ---------- KẾT ---------- */
if (TRUOT.length) {
  console.log('\n✗ HỎNG ' + TRUOT.length + ' phép:');
  TRUOT.forEach(function (x) { console.log('  ✗ ' + x); });
  process.exit(1);
}
console.log('✓ SẠCH — ' + DAT + ' phép: trang đơn gọn lại trên điện thoại, máy tính không đổi gì.');
