/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô LOẠI CHI PHÍ TRỐNG THÌ PHẢI NÓI ĐÚNG VÌ SAO TRỐNG.
 *
 * Anh Thắng 22/09/2026: *"Do dùng tk test hay sao không có loại chi phí"* — ảnh đơn ở cơ sở Cali
 * Thảo Điền (Máy tự động), ô LOẠI CHI PHÍ trống trơn, kèm câu gợi ý bảo đi khai mã cho mảng
 * POSH MN.
 *
 * =============================================================================================
 * 🔴 EM ĐÃ SUÝT CHỮA NHẦM CHỖ — GHI LẠI ĐỂ KHỎI LẶP
 * =============================================================================================
 * Lượt đầu em đoán là cửa thoát "tích vai trò thì hiện" bị cửa mảng ở cuối hàm lật ngược, rồi
 * sửa `_loaiCpList()` cho cửa thoát sống tới cuối. Sửa xong thì `test-o-loai-chi-phi.js` đỏ đúng
 * chỗ: *"cơ sở TUTU KHÔNG thấy loại chỉ khai cho FARM"*. Bài ấy ĐÚNG — nó ghim một tính năng anh
 * Thắng đã chốt: *ô mã để trống = mảng đó không dùng loại này*. Em đã hoàn tác.
 *
 * Nguyên nhân THẬT, dựng lại được: `_loaiCpList()` cắt loại của khối khác ngay ở cửa đầu (đúng —
 * chọn nhầm là tiền rơi vào sổ khối khác). Cơ sở Cali Thảo Điền thuộc Máy tự động, mà lúc ấy
 * người dùng đang đứng ở khối khác → không loại nào qua cửa. Gốc rễ là ô Cơ sở CHƯA lọc theo
 * khối, đã vá ở 1.252.0.
 *
 * 🔴 CÒN LẠI MỘT LỖ: CÂU GIẢI THÍCH CHỈ SAI CHỖ. `_loaiCpVi()` chỉ biết đếm "thiếu mã" và "lệch
 *    nhóm", nên nó bảo người ta đi khai mã — một việc khai xong cũng KHÔNG làm loại hiện ra.
 *    Người khai đi khai, không thấy gì đổi, rồi báo là app hỏng. Bài này canh đúng lỗ ấy.
 *
 * 🔴 HẬU TRUYỆN — 22/09/2026, CHÍNH CÁI CỬA ẤY ĐÃ ĐƯỢC GỠ, nhưng do ANH THẮNG quyết, không
 *    phải do em đoán: *"Sau khi quyết toán, thì kế toán có quyền điều chỉnh tk nợ theo nhu cầu,
 *    vì Cùng tên gọi nhưng nội dung khác, Nên lúc tạo đơn nhân viên không cần quan tâm"*. Đoạn
 *    ghi lại ở trên vẫn để nguyên: nó không sai, và nó là bằng chứng rằng lằn ranh nằm ở "ai
 *    quyết", chứ không ở "cửa ấy hay hay dở". Mục 3 bên dưới nay canh chiều ngược lại.
 *
 * Chạy: node tools/test/kiem-loai-trong-noi-ly-do.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }
/* THÂN HÀM, ĐÃ BỎ CHÚ THÍCH — dùng khi phép thử hỏi "mã CÓ LÀM việc này không", chứ không phải
   "trong hàm có nhắc tới nó không". Chú thích ở đây kể cả lịch sử những cửa ĐÃ GỠ, nên tìm chuỗi
   trên bản còn chú thích là phép thử đọc trúng cái xác của tính năng cũ rồi báo là nó còn sống.
   ⚠️ CHỈ BÓC TỪNG HÀM MỘT, đừng đem phép này quét cả `app.html`: trong tệp có CSS, và `/* */`
      của CSS bắt cặp với `*/` của JS nuốt mất nguyên phần thân trang — đã dính một lần. */
function bocThan(ten) {
  return bocHam(ten).replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|\n)\s*\/\/[^\n]*/g, '$1');
}
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }

const HAM = ['_bpTach', '_vaiTachLoai', '_tkNoCua', '_tkNoList', '_tapTkCo', '_mangCua', '_mangPham',
  '_donNhieuCoSo', '_khoaNhom', '_khoiCuaLoai', '_locLoaiTheoKhoi', '_vaiDungDuocLoai', '_boDauVai', '_bpCuaVai', '_bpCuaToi',
  '_khoiDvBang', '_khoiCuaDv', '_khoiCuaGian', '_gianHopKhoi', '_tenKhoi', '_loaiCpList', '_loaiCpVi'];
const NEN = bocMang('KHOI_DS') + '\n' + bocDong('KHOI_DV_DUP') + '\n'
  + bocMang('BP_THEO_TEN_VAI') + '\n' + HAM.map(bocHam).join('\n');
t('⚠️ nền chạy thử dựng được', NEN.replace(/\s/g, '').length > 1500, NEN.length);
HAM.forEach(function (h) { t('⚠️ bốc được `' + h + '`', bocHam(h).length > 20); });

const VAI = 'Kế Toán Máy Tự Động';
const BOOT = {
  loaiChiPhi: [{ ten: 'Chi Phí Khác MTĐ', tkNo: '', tkCo: '331', boPhan: '', loaiTt: '', khoi: 'mtd', vaiTro: VAI }],
  tkNoMx: {}, cosoPll: { 'cali thảo điền': 'POSH MN' },
  cosoDv: { 'cali thảo điền': 'POSH', 'tàu estella': 'KVC' },
  khoiTheoDv: { kvc: ['KVC'], mtd: ['MTĐ', 'MTD', 'POSH'], vp: ['VP'] },
};
function vi(coso, khoi) {
  return new Function('BOOT', 'CURUSER', 'KHOI_DANG', 'NHOM_CP', 'NHOM_CP_CS', 'CUR', 'esc',
    NEN + "\nreturn _loaiCpVi(coso,'','canhan');".replace('coso', JSON.stringify(coso)))(
    BOOT, { role: 'Admin' }, khoi, '', '(cơ sở)', null, (v) => String(v == null ? '' : v));
}

/* ═══ 1. ĐÚNG CẢNH TRONG ẢNH ═══════════════════════════════════════════════════ */
const lech = vi('Cali Thảo Điền', 'kvc');
t('🔴 đứng lệch khối → câu giải thích nói ĐÚNG lý do', /thuộc Máy tự động/.test(lech.chu), lech.chu);
t('🔴 và chỉ đúng việc phải làm: bấm sang khối ấy', /thanh KHỐI/.test(lech.chu), lech.chu);
/* 🔴 ĐÂY LÀ LỖ ĐANG VÁ: câu cũ bảo đi khai mã, mà khai xong cũng không làm loại hiện ra. */
t('🔴 KHÔNG còn bảo đi khai mã (khai xong cũng vô ích)',
  !/khai mã|Cấu hình/.test(lech.chu), lech.chu);
t('   và tô đỏ, không phải màu nhắc nhẹ', lech.mau === '#dc2626', lech.mau);

/* ═══ 2. ĐỨNG ĐÚNG KHỐI THÌ ĐỪNG DỌA ═══════════════════════════════════════════ */
const dung = vi('Cali Thảo Điền', 'mtd');
t('🔴 đứng đúng khối → KHÔNG hiện câu lệch khối', !/thuộc Máy tự động/.test(dung.chu), dung.chu);

/* ⚠️ CƠ SỞ CHƯA KHAI KHỐI thì đừng dọa lệch khối — cùng luật hỏng-an-toàn với `_gianHopKhoi()`:
   danh mục dựng từ sổ cũ, và một câu đỏ sai là người ta đi đổi khối vô ích. */
const laDat = vi('GIAN CHƯA KHAI', 'kvc');
t('⚠️ cơ sở chưa khai khối → KHÔNG dọa lệch khối', !/thuộc /.test(laDat.chu), laDat.chu);

/* ═══ 3. CỬA NÀO CÒN, CỬA NÀO ĐÃ GỠ ═══════════════════════════════════════════
 * Phép ở đây ghim rằng lượt sửa chỉ đụng đúng những cửa nó được phép đụng.
 *
 * 🔴 CỬA MẢNG ĐÃ GỠ — 22/09/2026, theo lệnh của anh Thắng: *"lúc tạo đơn nhân viên không cần
 *    quan tâm"* (tới TK Nợ). Chỗ này trước ghim NGƯỢC LẠI: hôm 1.253.0 em suýt tự ý nới cửa ấy
 *    nên viết một phép để chặn chính mình, và nó đã làm đúng việc — giữ tính năng lại cho tới
 *    khi NGƯỜI DÙNG quyết định bỏ. Nay anh quyết rồi thì phép này đảo chiều, không xoá đi:
 *    cửa đã tháo cũng cần người canh, kẻo một lượt merge lùi nào đó lắp lại mà không ai hay. */
/* ⚠️ TỪ 1.277.0 CỬA KHỐI ĐI QUA MỘT CỜ. Anh Thắng 22/09/2026: *"Khối là dùng chung, vì đã
   phân theo vai trò rồi, Khối là liên quan Miền Bắc và Miền Nam thôi"* — bản Hà Nội tắt cờ ấy,
   bản gốc giữ nguyên. Nên phép này canh CÁI CỬA CÒN ĐÓ và còn HỎI CỜ, chứ không ghim nguyên
   văn một dòng `if` — ghim nguyên văn là đỏ vì lối viết đổi, không phải vì cửa mất. */
t('🔴 `_loaiCpList` vẫn có cửa khối, và cửa ấy HỎI CỜ trước',
  /_locLoaiTheoKhoi\(\) && _khoiCuaLoai\(x\)!==String\(KHOI_DANG\)\.toLowerCase\(\)/.test(bocHam('_loaiCpList')),
  bocHam('_loaiCpList').slice(0, 200));
t('🔴 cửa mảng ở cuối ĐÃ GỠ — ô mã trống thôi ẩn loại',
  !/return !!row\[mang\];/.test(bocHam('_loaiCpList')), 'cửa mảng còn nguyên');
t('   …và không còn biến `mx` / `mang` nào đứng lại không ai đọc',
  !/var mx=BOOT\.tkNoMx/.test(bocHam('_loaiCpList')), 'còn var mx');
/* ⚠️ CHỐT CHỐNG RÁC LÀ CỬA DUY NHẤT CÒN CHẶN mấy trăm dòng nạp từ sổ cũ. Gỡ cửa mảng xong mà
   lỡ tay gỡ nốt cửa này là ô chọn đổ ra vài trăm dòng tên người và tên hoá đơn. */
t('🔴 chốt chống rác (không mã · không bộ phận · không vai) VẪN nguyên',
  /!_tkNoCua\(x\.ten, coso\) && !_tkNoList\(x\.ten, coso\)\.length/.test(bocHam('_loaiCpList'))
  && /!_bpTach\(x\.boPhan\)\.length && !_vaiTachLoai\(x\)\.length\) return false;/.test(bocHam('_loaiCpList')),
  'chốt chống rác đã mất');
/* ⚠️ VÀ CÂU GIẢI THÍCH PHẢI THÔI NÓI VỀ CỬA ĐÃ GỠ — một lý do không có thật còn tệ hơn im lặng:
   nó gửi người ta đi khai mã, khai xong vẫn không đổi gì. */
t('🔴 `_loaiCpVi` thôi đổ cho "chưa khai mã cho mảng"',
  bocThan('_loaiCpVi').indexOf('chưa khai mã cho mảng') < 0, 'câu cũ còn đó');
t('   và gọi đúng tên cửa còn lại: "chưa khai gì"',
  bocThan('_loaiCpVi').indexOf('chưa khai gì') >= 0, 'không nêu chốt chống rác');
t('⚠️ `bocThan` thật sự có bóc chú thích (không thì hai phép trên vô dụng)',
  bocHam('_loaiCpVi').length - bocThan('_loaiCpVi').length > 500,
  bocHam('_loaiCpVi').length - bocThan('_loaiCpVi').length);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô loại trống thì nói đúng lý do, cửa mảng đã gỡ, chốt chống rác còn nguyên.');
