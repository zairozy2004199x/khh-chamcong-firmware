/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * LOẠI ĐÃ TÍCH VAI TRÒ THÌ HIỆN Ở Ô CHỌN, DÙ CHƯA KHAI MÃ.
 *
 * Anh Thắng 21/09/2026: *"Đã set là nhân viên máy tự động, nhưng vẫn chưa hiện loại chi phí.
 * do chưa set mã hay sao"* — anh đoán ĐÚNG, và đúng cả chỗ nó vô lý: hai loại "Chi Phí Khác
 * MTĐ" / "Chi Phí Chung MTĐ" tích rõ vai "Nhân Viên Kỹ Thuật Máy Tự Động" mà ô chọn vẫn trắng.
 *
 * =============================================================================================
 * 🔴 LỖI DO CHÍNH BẢN 1.233.0 GÂY RA
 * =============================================================================================
 * `_loaiCpList()` chỉ hiện loại ĐÃ KHAI MÃ — có lý do thật: nạp dữ liệu cũ đẻ ra vài trăm dòng
 * rác ("Nguyễn Hữu Thọ", "Cấp Mạng VNPT"…), để nguyên thì ô chọn thành vài trăm dòng.
 *
 * Cửa thoát cho loại KHAI CÓ CHỦ là ô Bộ phận. Nhưng 1.233.0 gỡ cột Bộ phận khỏi bảng loại chi
 * phí (anh Thắng: *"bỏ tích bộ phận đi, mà tích theo vai trò"*) — mà cửa thoát vẫn đo bằng
 * `boPhan`. Mọi loại tạo TỪ ĐÓ ĐẾN NAY đều có `boPhan` rỗng, nên loại nào chưa kịp khai mã là
 * TÀNG HÌNH.
 *
 * Đúng cái bẫy 10/09/2026 (*"kỹ thuật đang có 2 loại chi phí, nhưng mới hiện 1 loại thôi"*)
 * quay lại bằng một cửa khác — cửa do chính lượt sửa ấy mở ra.
 *
 * ⚠️ VÀ KHÔNG ĐƯỢC CHỮA BẰNG CÁCH TẮT BỘ LỌC. Ô Khối thì mọi dòng đều có (seed() lấp mỗi lượt
 *    nạp), nên lấy khối làm cửa thoát là vài trăm dòng rác đổ hết ra ô chọn — chữa một lỗi
 *    bằng cách dựng lại đúng cái lỗi mà bộ lọc sinh ra để chặn.
 *
 * Chạy: node tools/test/kiem-loai-chua-co-ma-van-hien.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const boc = (ten) => {
  const i = HTML.indexOf('  function ' + ten + '(');
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* Bốc đúng chùm hàm `_loaiCpList()` cần, và chạy THẬT — dò chữ thì một bộ lọc sai luật vẫn xanh. */
/* Bảng từ khoá bộ phận đi kèm `_bpCuaVai()` — thiếu là nổ `ReferenceError`, trông như mã hỏng
   chứ không phải bệ đỡ thiếu. */
const bocMang = (ten) => {
  const i = HTML.indexOf('  var ' + ten + '=');
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2);
};
/* `MIEN_MA` + `_loaiHopKhoi` (23/09/2026): cửa khối của `_loaiCpList` chỉ so khi cùng trục miền/khối cũ. */
const NGUON = bocMang('BP_THEO_TEN_VAI') + '\n' + bocMang('MIEN_MA') + '\n' + ['_khoiCuaLoai', '_vaiTachLoai', '_locLoaiTheoKhoi', '_loaiHopKhoi', '_vaiDungDuocLoai', '_mangCua', '_donNhieuCoSo',
  '_mangPham', '_tkNoList', '_tapTkCo', '_tkNoCua', '_khoaNhom', '_bpTach', '_boDauVai',
  '_bpCuaVai', '_bpCuaToi', '_loaiCpList'].map(boc).join('\n')
  + '\n return _loaiCpList;';
['_vaiTachLoai', '_loaiCpList'].forEach((x) => t('bốc được `' + x + '`', boc(x).length > 40, boc(x).length));

function ds(loai, user) {
  const F = new Function('BOOT', 'NHOM_CP', 'CURUSER', 'CUR', 'CUR_PAGE', 'DA_CUR', 'KHOI_DANG', 'esc', NGUON)(
    { loaiChiPhi: loai, tkNoMx: {}, cosoPll: {} }, '', user,
    { don: { nhieuCoSo: false } }, 'don', null, 'mtd', (v) => String(v == null ? '' : v));
  return F('', '').map((x) => x.ten);
}
const HIEU = { name: 'Trương Tấn Hiếu', role: 'Nhân Viên Kỹ Thuật Máy Tự Động', roleGoc: 'Nhân viên', boPhan: '' };

/* ═══ 1. 🔴 ĐÚNG CẢNH ANH THẮNG CHỤP ═══════════════════════════════════════════ */
const LOAI_MTD = [
  { ten: 'Chi Phí Khác MTĐ',  khoi: 'mtd', boPhan: '', vaiTro: 'Quản Lý Máy Tự Động, Kế Toán Máy Tự Động, Nhân Viên Kỹ Thuật Máy Tự Động' },
  { ten: 'Chi Phí Chung MTĐ', khoi: 'mtd', boPhan: '', vaiTro: 'Quản Lý Máy Tự Động, Kế Toán Máy Tự Động, Nhân Viên Kỹ Thuật Máy Tự Động' },
];
teq('🔴 loại tích vai, CHƯA khai mã → vẫn hiện ở ô chọn',
  ['Chi Phí Khác MTĐ', 'Chi Phí Chung MTĐ'], ds(LOAI_MTD, HIEU));

/* ═══ 2. 🔴 NHƯNG DÒNG RÁC NẠP TỪ SỔ CŨ VẪN BỊ ẨN ══════════════════════════════
 * Phép đối chứng cho chính chốt trên. Thiếu nó thì "chữa" bằng cách tắt hẳn bộ lọc cũng xanh
 * — và ô chọn của anh Thắng thành vài trăm dòng tên người, tên vật tư. */
const RAC = [
  { ten: 'Nguyễn Hữu Thọ, Nguyễn Bá Tuấn', khoi: 'mtd', boPhan: '', vaiTro: '' },
  { ten: 'Cấp Mạng VNPT',                  khoi: 'mtd', boPhan: '', vaiTro: '' },
];
teq('🔴 dòng rác (không tích vai, không mã) VẪN bị ẩn', [], ds(RAC, HIEU));
teq('   trộn chung thì chỉ lọt loại đã tích vai',
  ['Chi Phí Khác MTĐ'], ds([LOAI_MTD[0]].concat(RAC), HIEU));

/* ═══ 3. CỬA THOÁT CŨ (BỘ PHẬN) VẪN CÒN, cho dòng khai trước 1.233.0 ═══════════ */
teq('⚠️ loại khai BỘ PHẬN từ thời trước vẫn hiện như cũ', ['Chi phí setup'],
  ds([{ ten: 'Chi phí setup', khoi: 'mtd', boPhan: 'Kỹ thuật', vaiTro: '' }], HIEU));

/* ═══ 4. 🔴 LỌC THEO VAI VẪN CHẠY — cửa thoát không được nới quyền ═════════════
 * Loại tích vai KHÁC thì người này không thấy: cửa thoát chỉ trả lời "loại này có khai có chủ
 * không", không trả lời "ai được dùng". Lẫn hai câu ấy là mở sổ cho nhầm người. */
teq('🔴 loại tích vai NGƯỜI KHÁC thì vẫn không hiện', [],
  ds([{ ten: 'Chi Phí VP', khoi: 'mtd', boPhan: '', vaiTro: 'Kế Toán VP Chung' }], HIEU));
/* ⚠️ Không tích vai nào = MỌI vai — nhưng vẫn phải có mã hoặc bộ phận mới hiện (nó là dòng rác
   cho tới khi có người khai gì đó cho nó). */
teq('⚠️ không tích vai + không mã = vẫn ẩn', [],
  ds([{ ten: 'Chi phí khác', khoi: 'mtd', boPhan: '', vaiTro: '' }], HIEU));

/* ═══ 5. ⚠️ KHÔNG ĐƯỢC LẤY Ô KHỐI LÀM CỬA THOÁT ═══════════════════════════════
 * Từ 1.234.0 MỌI dòng đều có khối (seed() lấp mỗi lượt nạp), nên đo bằng khối là tắt hẳn bộ
 * lọc. Phép trên (dòng rác vẫn ẩn) đã canh điều này — đây là phép nói thẳng ra lý do. */
const LOC = boc('_loaiCpList');
t('⚠️ cửa thoát KHÔNG đo bằng ô khối',
  !/&&\s*!_khoiCuaLoai\(x\)/.test(LOC) && !/\|\|\s*_khoiCuaLoai\(x\)/.test(LOC), 'có');
t('🔴 và cửa thoát mới đo bằng vai đã tích',
  /!_vaiTachLoai\(x\)\.length/.test(LOC), LOC.slice(LOC.indexOf('_tkNoCua'), LOC.indexOf('_tkNoCua') + 200));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: loại tích vai hiện dù chưa có mã, dòng rác vẫn ẩn.');
