/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HỘP "GIAN / CƠ SỞ" LÚC LẬP ĐƠN CHỈ XỔ GIAN CỦA KHỐI ĐANG ĐỨNG.
 *
 * Anh Thắng 21/09/2026: *"Đã phân quyền nhân viên, nhưng vẫn thấy cơ sở bên KVC"*.
 * Anh Trương Tấn Hiếu — vai "Nhân Viên Kỹ Thuật Máy Tự Động", cột Khối tích đúng Máy tự động,
 * đang đứng ở tab khối "Máy tự động" — mở hộp Gian ra vẫn thấy NHÀ MA PHAN VĂN TRỊ, FUNZONE,
 * TÀU TÂN PHÚ… toàn gian khu vui chơi, lẫn với 67 gian ghế vừa hút.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHÂN QUYỀN ĐÚNG MÀ VẪN THẤY
 * =============================================================================================
 * Cột Khối ở bảng Người dùng gác việc BÀY NÚT KHỐI NÀO. Hộp Gian thì không hỏi khối một câu
 * nào — nó đổ nguyên danh sách cơ sở máy chủ gửi về. Mà màn KHÔNG THỂ lọc, vì nó không hề biết
 * gian nào thuộc khối nào: bảng tra cơ sở → đơn vị nằm ở máy chủ từ lâu
 * (`VHCP_DonVi::cua_coso()`) mà chưa bao giờ được gửi xuống.
 *
 * 🔴 CHỌN NHẦM GIAN LÀ TIỀN RƠI VÀO SỔ CỦA KHỐI KHÁC, và hỏng theo kiểu khó truy nhất: đơn vẫn
 *    lập được, số vẫn vào, chỉ là vào nhầm chỗ — đến lúc đối chiếu cuối kỳ mới lộ.
 *
 * ⚠️ GIAN CHƯA RÕ KHỐI VẪN HIỆN — cùng luật với `_hopKhoi()` bên đơn. Danh mục dựng từ sổ cũ,
 *    có gian chưa khai đơn vị; ẩn chúng là người nhập không chọn được gian có thật, rồi gõ tay
 *    mỗi người một kiểu — đúng cái bệnh hộp này sinh ra để chữa.
 *
 * Chạy: node tools/test/kiem-gian-theo-khoi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const PHP = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }
/* ⚠️ Gỡ chú thích CHỈ trên thân hàm đã bốc — gỡ trên cả tệp thì một dấu mở trong <style> bắt
   cặp với một dấu đóng dưới <script> và nuốt trọn thân trang (đã cắn 21/09/2026). */
function sachHam(ten) { return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/\/\/[^\n]*/g, ' '); }

/* ═══ 1. MÁY CHỦ GỬI BẢNG TRA XUỐNG ═════════════════════════════════════════════ */
t('🔴 gói khởi động mang bảng cơ sở → đơn vị', /'cosoDv'\s*=>/.test(PHP), 'không thấy');
/* ⚠️ GỬI BẢNG TRA, KHÔNG GỬI SẴN DANH SÁCH ĐÃ LỌC: khối đổi theo tab người đang đứng, mà gói
   khởi động chỉ nạp MỘT lần — gửi sẵn là đổi tab xong hộp vẫn đứng ở khối cũ. */
t('   và lấy từ bảng đã dựng sẵn ở cấu hình, không dựng lại',
  /'cosoDv'\s*=>\s*\(\s*isset\(\s*\$s_all\['cosoDonVi'\]\s*\)/.test(PHP), 'không thấy');

/* ═══ 2. CHẠY THẬT BỘ LỌC ═══════════════════════════════════════════════════════ */
const NEN = bocMang('KHOI_DS') + '\n' + bocDong('KHOI_DV_DUP') + '\n' + bocHam('_khoiDvBang') + '\n'
  + bocHam('_khoiCuaDv') + '\n' + bocHam('_khoiCuaGian') + '\n' + bocHam('_gianHopKhoi');
const BOOT = {
  khoiTheoDv: { kvc: ['KVC'], mtd: ['MTĐ', 'MTD', 'POSH'], vp: ['VP'] },
  cosoDv: { 'nhà ma phan văn trị': 'KVC', 'aeon mall bình dương': 'POSH', 'gian chưa khai': '' }
};
const loc = (ten, khoi) => new Function('BOOT', 'KHOI_DANG', 'ten', NEN + '\nreturn _gianHopKhoi(ten);')(BOOT, khoi, ten);
const khoiCua = (ten) => new Function('BOOT', 'KHOI_DANG', 'ten', NEN + '\nreturn _khoiCuaGian(ten);')(BOOT, 'kvc', ten);

teq('🔴 gian khu vui chơi đọc ra khối kvc', 'kvc', khoiCua('NHÀ MA PHAN VĂN TRỊ'));
teq('🔴 gian ghế (đơn vị POSH) đọc ra khối mtd', 'mtd', khoiCua('AEON MALL BÌNH DƯƠNG'));
/* ⚠️ Bảng tra dựng với khoá ĐÃ HẠ CHỮ THƯỜNG. Tra nguyên văn là trượt hết, và mọi gian trông
   như "chưa rõ khối" — tức bộ lọc im lặng thôi lọc, không ai thấy. */
teq('🔴 tra bằng khoá hạ chữ thường, không tra nguyên văn', 'mtd', khoiCua('aeon mall bình dương'));

t('🔴 đứng ở mtd: KHÔNG bày gian khu vui chơi', loc('NHÀ MA PHAN VĂN TRỊ', 'mtd') === false);
t('🔴 đứng ở mtd: CÓ bày gian ghế', loc('AEON MALL BÌNH DƯƠNG', 'mtd') === true);
t('   đứng ở kvc thì ngược lại', loc('NHÀ MA PHAN VĂN TRỊ', 'kvc') === true
  && loc('AEON MALL BÌNH DƯƠNG', 'kvc') === false);
/* 🔴 Chốt hỏng-an-toàn: gian chưa khai đơn vị vẫn hiện ở MỌI khối. Ẩn nó là người nhập không
   chọn được một gian có thật, rồi gõ tay mỗi người một kiểu. */
t('🔴 gian chưa rõ khối vẫn hiện ở MỌI khối',
  loc('GIAN CHƯA KHAI', 'kvc') && loc('GIAN CHƯA KHAI', 'mtd') && loc('GIAN CHƯA KHAI', 'vp'));
/* Phép đối chứng cho chính chốt trên: thiếu `BOOT.cosoDv` (gói cũ) thì KHÔNG được lọc gì cả,
   chứ không phải lọc sạch. Lọc sạch là hộp Gian trắng trơn với mọi người. */
const locKhongBang = (ten, khoi) => new Function('BOOT', 'KHOI_DANG', 'ten', NEN + '\nreturn _gianHopKhoi(ten);')({}, khoi, ten);
t('🔴 gói khởi động bản CŨ (chưa có bảng tra) → bày ĐỦ, không bày rỗng',
  locKhongBang('NHÀ MA PHAN VĂN TRỊ', 'mtd') && locKhongBang('AEON MALL BÌNH DƯƠNG', 'kvc'));

/* ═══ 3. HỘP GIỮ DANH SÁCH GỐC ĐỂ ĐỔI KHỐI CÒN LỌC LẠI ═════════════════════════
 * Đợi tải lại trang thì người ta đổi khối xong mở hộp ra vẫn thấy gian của khối cũ, và kết
 * luận là lọc không chạy. */
const NAP = sachHam('_daNapCoSoDs') + '\n' + sachHam('_daLocLaiGian');
t('🔴 hộp lọc danh sách qua `_gianHopKhoi`', /\.filter\(_gianHopKhoi\)/.test(NAP), NAP);
t('🔴 và GIỮ danh sách gốc để lọc lại khi đổi khối', /DA_COSO_DS\s*=\s*\(coso\|\|\[\]\)\.slice\(\)/.test(NAP), NAP);
/* 🔴 HAI VIỆC, HAI HÀM. Lượt đầu em cho `_daNapCoSoDs(null)` nghĩa "lọc lại" — nhưng `null` ĐÃ
   CÓ nghĩa "dọn sạch hộp", và `kiem-tab-kythuat-va-gian.js` canh đúng điều ấy nên nó đỏ. */
t('🔴 "dọn sạch" và "lọc lại" là HAI hàm rời nhau',
  bocHam('_daLocLaiGian').length > 40 && /DA_COSO_DS\s*=\s*\(coso\|\|\[\]\)\.slice\(\)/.test(sachHam('_daNapCoSoDs')),
  bocHam('_daLocLaiGian'));
t('🔴 đổi khối là nạp lại hộp ngay, không đợi tải lại trang',
  /_daLocLaiGian\(\)/.test(sachHam('doiKhoi')), sachHam('doiKhoi'));
/* ⚠️ VẪN LÀ Ô NHẬP, không phải ô xổ đóng: gian mới chưa có trong danh mục phải gõ được, không
   thì đơn Setup gian mới không nhập nổi dòng nào. */
t('⚠️ ô Gian vẫn là ô nhập kèm gợi ý', /<input id="da_gian" list="dl_gian_da"/.test(HTML));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hộp Gian chỉ xổ gian của khối đang đứng, gian chưa rõ khối vẫn hiện.');
