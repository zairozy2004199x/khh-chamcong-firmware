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
 * Chạy: node tools/test/kiem-loai-trong-noi-ly-do.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }

const HAM = ['_bpTach', '_vaiTachLoai', '_tkNoCua', '_tkNoList', '_tapTkCo', '_mangCua', '_mangPham',
  '_donNhieuCoSo', '_khoaNhom', '_khoiCuaLoai', '_vaiDungDuocLoai', '_boDauVai', '_bpCuaVai', '_bpCuaToi',
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

/* ═══ 3. CỬA LỌC KHÔNG ĐỔI — chỉ câu chữ đổi ═══════════════════════════════════
 * 🔴 Em đã suýt nới cửa lọc và làm hỏng tính năng "ô mã trống = mảng không dùng loại này".
 *    Phép này ghim rằng lượt sửa CHỈ đụng vào câu giải thích. */
t('🔴 `_loaiCpList` vẫn cắt theo khối ở cửa đầu',
  /if\(_khoiCuaLoai\(x\)!==String\(KHOI_DANG\)\.toLowerCase\(\)\) return false;/.test(bocHam('_loaiCpList')));
t('🔴 và cửa mảng ở cuối vẫn nguyên (ô mã trống = mảng không dùng loại này)',
  /return !!row\[mang\];/.test(bocHam('_loaiCpList')));
t('⚠️ KHÔNG nới cửa thoát lên cửa mảng — đã thử và hỏng `test-o-loai-chi-phi.js`',
  bocHam('_loaiCpList').indexOf('coCuaThoat') < 0, 'còn dấu vết coCuaThoat');

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô loại trống vì lệch khối thì nói đúng lý do, và cửa lọc không đổi.');
