/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * VAI CON PHẢI ĐƯỢC LÀM ĐÚNG NHỮNG GÌ VAI CHA ĐƯỢC LÀM.
 *
 * Anh Thắng 21/09/2026 gửi ảnh màn của chị Mai Anh — vai *"Nhân Viên Cơ Sở Khu Vui Chơi"* —
 * kèm đúng ba chữ: *"mất chỗ tạo đơn"*.
 *
 * =============================================================================================
 * 🔴 MỘT DÒNG BÁO, NHƯNG LÀ CẢ MỘT HỌT
 * =============================================================================================
 * Nút "＋ Tạo đơn mới" bị ẩn vì dòng gác nó so `role==='Nhân viên'` — mà tên vai của chị là
 * vai CON, không trùng một chữ nào. Bảng tra tab ở `applyPerms()` thì đã quy về vai gốc từ
 * lâu, và ngay trên nó còn ghi hẳn *"tra theo vai gốc, không theo tên vai — đây là lỗi thật,
 * không phải giả định"*. Nhưng mười mấy chỗ khác vẫn so thẳng tên vai.
 *
 * Nên chữa mỗi cái nút là chị Mai Anh bấm được nút rồi đụng ngay cái kế tiếp: ô "Người lập"
 * không khoá (nhân viên lẽ ra không tự đổi tên người lập), danh sách đơn không lọc về riêng
 * mình, trang Tổng quan bung ra cả cơ sở không phải của chị. Vì vậy bài này canh CẢ HỌT.
 *
 * 🔴 VÀ MỘT CHỐT NGƯỢC CHIỀU: CON CỦA ADMIN KHÔNG BAO GIỜ THÀNH ADMIN. Ô cha ở bảng vai trò
 *    có cả mục "Admin", nên ai đó tạo được một vai con của Admin. Quy về gốc máy móc là vai
 *    ấy ăn trọn quyền Admin — nới quyền bằng đúng một lượt gõ tên. Hỏng phải theo hướng
 *    THIẾU quyền, không phải thừa.
 *
 * ⚠️ Chip trên đỉnh trang vẫn phải bày TÊN VAI THẬT. Bày vai gốc ở đó là chị Mai Anh mở màn
 *    ra thấy mình thành "Nhân viên", tưởng bị đổi vai.
 *
 * Chạy: node tools/test/kiem-vai-con-dung-quyen.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n  }', i) + 4;
  return (j > i) ? HTML.slice(i, j) : '';
}
/* ⚠️ GỠ CHÚ THÍCH TRƯỚC KHI DÒ CHỮ — repo này đã bị cắn bốn lượt vì phép tìm bắt trúng chính
   câu giải thích nằm ngay bên cạnh, rồi xanh vĩnh viễn kể cả khi mã bị gỡ sạch. */
const SACH = HTML.replace(/\/\*[\s\S]*?\*\//g, ' ');

/* ═══ 1. CHẠY THẬT `_vaiLuat()` ═════════════════════════════════════════════════ */
t('bốc được `_vaiLuat`', bocHam('_vaiLuat').length > 40, bocHam('_vaiLuat').length);
function vaiLuat(role, roleGoc) {
  return new Function('CURUSER', bocHam('_vaiGoc') + '\n' + bocHam('_vaiLuat') + '\nreturn _vaiLuat();')(
    { role: role, roleGoc: roleGoc });
}
teq('🔴 vai con của Nhân viên → so luật như Nhân viên',
  'Nhân viên', vaiLuat('Nhân Viên Cơ Sở Khu Vui Chơi', 'Nhân viên'));
teq('   vai con của Quản lý → như Quản lý', 'Quản lý', vaiLuat('Quản Lý Máy Tự Động', 'Quản lý'));
teq('   vai con của Kế toán → như Kế toán cá nhân',
  'Kế toán cá nhân', vaiLuat('Kế Toán VP Chung', 'Kế toán cá nhân'));
teq('   vai gốc thì vẫn là chính nó', 'Nhân viên', vaiLuat('Nhân viên', 'Nhân viên'));
teq('   Admin vẫn là Admin', 'Admin', vaiLuat('Admin', 'Admin'));
/* 🔴 CHỐT NGƯỢC CHIỀU — bỏ nhánh này là nới quyền Admin bằng một lượt gõ tên. */
teq('🔴 CON của Admin KHÔNG thành Admin', 'Trợ lý Sếp', vaiLuat('Trợ lý Sếp', 'Admin'));
/* Gói cũ chưa có `roleGoc` (phiên đăng nhập còn trong sessionStorage lúc nâng bản) thì phải
   ngã về chính tên vai, không được ném ra chuỗi rỗng — rỗng là không khớp rọ nào và người ta
   mất sạch tab ngay giữa ca làm. */
teq('⚠️ thiếu `roleGoc` (phiên cũ) → ngã về tên vai', 'Nhân viên', vaiLuat('Nhân viên', ''));
teq('   và không bao giờ trả chuỗi rỗng khi có tên vai', 'Vai Lạ', vaiLuat('Vai Lạ', ''));

/* ═══ 2. 🔴 KHÔNG CÒN CHỖ NÀO SO LUẬT BẰNG TÊN VAI ══════════════════════════════
 * Đây là phép quét cả họt. Trả `role` về `CURUSER.role` ở BẤT KỲ chỗ nào là nó đỏ. */
t('🔴 không còn chỗ nào khai `role` thẳng từ CURUSER để so luật',
  SACH.indexOf("var role=(CURUSER&&CURUSER.role)||''") < 0, 'còn');
['Nhân viên', 'Quản lý', 'Kế toán cá nhân', 'Kế toán NCC'].forEach(function (v) {
  t('🔴 không còn so `CURUSER.role===«' + v + '»»'.slice(0, 1),
    SACH.indexOf("CURUSER.role==='" + v + "'") < 0, 'còn');
});
/* ⚠️ `role==='Admin'` thì ĐƯỢC PHÉP so thẳng — và phải thế: Admin là vai duy nhất không quy
   từ gốc lên, nên mấy chỗ ấy để nguyên là đúng, không phải sót. */
t('⚠️ nhưng so thẳng `role===Admin` vẫn còn (cố ý, không phải sót)',
  SACH.indexOf("CURUSER.role==='Admin'") >= 0);

/* ═══ 3. NÚT "＋ TẠO ĐƠN MỚI" — ĐÚNG CHỖ ANH THẮNG BÁO ══════════════════════════ */
t('nút có mặt trên màn', /id="btnNewDon"/.test(HTML));
t('🔴 và gác theo vai LUẬT',
  /var vl=_vaiLuat\(\);/.test(SACH)
    && /bn\.style\.display=\(vl==='Nhân viên'\|\|vl==='Quản lý'\|\|vl==='Admin'\)/.test(SACH), 'không thấy');

/* ═══ 4. NHỮNG CHỐT ĐI KÈM — chị Mai Anh sẽ đụng ngay sau khi bấm được nút ══════ */
[['ô Người lập khoá với nhân viên', "el('ndNguoiLap').readOnly=(_vaiLuat()==='Nhân viên')"],
 ['danh sách đơn lọc về riêng mình', "_vaiLuat()==='Nhân viên' && CURUSER.name"],
 ['bảng tra tab', "}[_vaiLuat()]||{don:1}"],
 ['nhánh bộ phận của nhân viên', "if(_vaiLuat()==='Nhân viên'){"],
 ['trang mặc định lúc đăng nhập', 'defaultPageFor(_vaiLuat())']
].forEach(function (x) { t('🔴 ' + x[0] + ' theo vai luật', SACH.indexOf(x[1]) >= 0, x[1]); });

/* ═══ 5. ⚠️ CHIP VẪN BÀY TÊN VAI THẬT ═══════════════════════════════════════════
 * Phép đối chứng cho mục 2: nếu ai đó "dọn" nốt chỗ này thì chị Mai Anh mở màn ra thấy mình
 * là "Nhân viên" và tưởng bị đổi vai. Quy về gốc là chuyện của LUẬT, không phải của CHỮ BÀY RA. */
t('⚠️ chip đỉnh trang vẫn lấy tên vai thật', SACH.indexOf('var role=CURUSER.role;') >= 0, 'không thấy');
t('   và chip in đúng biến ấy', /esc\(CURUSER\.name\)\+' · '\+esc\(role\)/.test(SACH), 'không thấy');

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: vai con làm được việc của vai cha, con của Admin thì không.');
