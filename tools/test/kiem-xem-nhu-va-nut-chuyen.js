/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 👁 XEM NHƯ + NÚT ĐI SANG LOẠI ĐƠN KIA
 *
 * Anh Thắng 12/09/2026, hai việc trong một mạch:
 *   *"Đối với nhân viên cơ sở ẩn nút này đi, tránh nhập nhầm"*  (nút "← Quay lại chi phí Kỹ thuật")
 *   *"Giờ anh cần test, làm sao để chọn chế độ nhân viên giả lập nhiều bộ phận để test, thay vì
 *   cứ đăng xuất ra vào"*
 *
 * =============================================================================================
 * 🔴 CHỖ HỞ THẬT CỦA CÁI NÚT: `vis.duan` chỉ bị hạ xuống 0 khi ô Bộ phận CÓ KHAI gì đó. Tài
 *    khoản để trống ô ấy — đúng như ảnh anh Thắng gửi (Nguyễn Văn Bin · Nhân viên · FARM PHAN
 *    THIẾT, không bộ phận) — giữ nguyên `vis.duan = 1`, và nút sáng lên mời họ sang màn không
 *    phải việc của mình.
 *
 * 🔴 VÀ "XEM NHƯ" CHỈ ĐỔI MÀN HÌNH. Máy chủ vẫn nhận ra Admin. Bài kiểm canh điều đó bằng cách
 *    đòi bản sao tài khoản thật được giữ nguyên vẹn và trả lại đủ khi thoát — sửa thẳng vào
 *    `CURUSER` là sau một lượt thử, tài khoản thật mang vai của người được thử.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-xem-nhu-va-nut-chuyen.js
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
function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = HTML.indexOf('\n', i);
  return (j > i) ? HTML.slice(i, j) : '';
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. NÚT ĐI SANG LOẠI ĐƠN KIA — HAI LỚP GÁC
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fnNut = bocHam('_veNutChuyenDon');
t('bốc được _veNutChuyenDon()', fnNut.length > 100, fnNut.length);

/** Chạy thật với một tài khoản + bảng `vis`, trả trạng thái hiện/ẩn của hai nút. */
function nut(vai, bp, vis) {
  const B = { duan: { style: { display: '' } }, don: { style: { display: '' } } };
  new Function('CURUSER', 'document', 'BP_VAO_DUAN', '_vaiGoc', '_vaoDonCoSo', 'vis',
    fnNut + '\n_veNutChuyenDon(vis);')(
    { role: vai, roleGoc: vai, boPhan: bp },
    { querySelectorAll: () => [
      Object.assign(B.duan, { getAttribute: () => 'duan' }),
      Object.assign(B.don,  { getAttribute: () => 'don' }),
    ] },
    ['Văn phòng', 'Kỹ thuật'],
    function () { return vai; },
    function (x) { return !(x && ['Kỹ thuật'].indexOf(x) >= 0); },
    vis);
  return { duan: B.duan.style.display !== 'none', don: B.don.style.display !== 'none' };
}
const MO = { duan: 1, don: 1 };

/* 🔴 CA CHÍNH — đúng tài khoản trong ảnh anh Thắng gửi. */
teq('🔴 NV KHÔNG khai bộ phận: nút sang Kỹ thuật bị ẩn', false, nut('Nhân viên', '', MO).duan);
teq('   và nút sang đơn tuần cũng ẩn',                   false, nut('Nhân viên', '', MO).don);
teq('🔴 NV bộ phận Cơ sở: nút sang Kỹ thuật ẩn',         false, nut('Nhân viên', 'Cơ sở', MO).duan);
teq('   nhưng nút sang đơn tuần thì HIỆN (việc của họ)', true,  nut('Nhân viên', 'Cơ sở', MO).don);

/* Người thật sự làm kỹ thuật / văn phòng thì vẫn có lối tắt. */
teq('NV Kỹ thuật: nút sang Kỹ thuật hiện',    true,  nut('Nhân viên', 'Kỹ thuật', MO).duan);
teq('🔴 NV Kỹ thuật: nút sang đơn tuần ẩn',   false, nut('Nhân viên', 'Kỹ thuật', MO).don);
teq('NV Văn phòng: thấy cả hai · Kỹ thuật',   true,  nut('Nhân viên', 'Văn phòng', MO).duan);
teq('                          · đơn tuần',   true,  nut('Nhân viên', 'Văn phòng', MO).don);

/* 🔴 LỚP 2 CHỈ SIẾT NHÂN VIÊN. Admin/Quản lý/Kế toán là người soát, đi lại cả hai mảng. */
['Admin', 'Quản lý', 'Kế toán cá nhân'].forEach(function (v) {
  teq('🔴 ' + v + ' không khai bộ phận vẫn thấy nút', true, nut(v, '', MO).duan);
});

/* ⚠️ LỚP 1 VẪN PHẢI CÒN: `vis` tắt thì ẩn, dù bộ phận có hợp lệ. */
teq('🔴 vis.duan = 0 thì ẩn dù là NV Kỹ thuật', false, nut('Nhân viên', 'Kỹ thuật', { duan: 0, don: 1 }).duan);
teq('   vis.don = 0 thì ẩn dù là NV Cơ sở',     false, nut('Nhân viên', 'Cơ sở', { duan: 1, don: 0 }).don);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 👁 XEM NHƯ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const MOI = bocDong('glDangBat') + '\n' + bocHam('_glDat') + '\n' + bocHam('glDoi') + '\n' + bocHam('glThoat');
function moiTruong(toi) {
  const KHO = { glVai: { value: '' }, glBp: { value: '' }, glDangXem: { textContent: '' } };
  const NK = { apply: 0, page: [] };
  const F = new Function('CURUSER', 'el', 'applyPerms', 'showPage', 'defaultPageFor', 'toast', 'GL_GOC',
    'var _u=CURUSER;\n' + MOI
    + '\nreturn { dat:_glDat, doi:glDoi, thoat:glThoat, bat:glDangBat,'
    + ' u:function(){return CURUSER;}, goc:function(){return GL_GOC;}, kho:function(){return arguments[0];} };')(
    JSON.parse(JSON.stringify(toi)),
    function (id) { return KHO[id]; },
    function () { NK.apply++; },
    function (p) { NK.page.push(p); },
    function (r) { return r === 'Nhân viên' ? 'don' : 'tongquan'; },
    function () {},
    null);
  return { F: F, KHO: KHO, NK: NK };
}
t('bốc được _glDat()',  bocHam('_glDat').length > 200, bocHam('_glDat').length);
t('bốc được glThoat()', bocHam('glThoat').length > 100, bocHam('glThoat').length);

/* 🔴 Hàm bốc rời không giữ được biến `GL_GOC` dùng chung, nên phép chạy thật ở trên chỉ đo
   được phần logic trong một lượt. Những chốt còn lại canh bằng quét mã — chúng là chốt về
   CẤU TRÚC (giữ bản sao, trả lại nguyên vẹn), thứ đọc ra được từ chính mã. */
const thanGl = bocHam('_glDat') + bocHam('glThoat') + bocHam('glDoi') + bocHam('glDung');
t('🔴 giữ BẢN SAO của tài khoản thật, không giữ tham chiếu',
  /GL_GOC\s*=\s*JSON\.parse\(\s*JSON\.stringify\(\s*CURUSER/.test(thanGl), thanGl.slice(0, 300));
t('🔴 dựng người-được-xem từ bản sao, không sửa thẳng bản gốc',
  /var u\s*=\s*JSON\.parse\(\s*JSON\.stringify\(\s*GL_GOC/.test(thanGl));
t('🔴 thoát thì TRẢ LẠI đúng bản gốc', /CURUSER\s*=\s*GL_GOC/.test(thanGl));
t('   và xoá cờ đang-xem-như',         /GL_GOC\s*=\s*null/.test(thanGl));
t('   vẽ lại màn sau mỗi lần đổi',     (thanGl.match(/applyPerms\(\)/g) || []).length >= 2, thanGl.match(/applyPerms\(\)/g));
t('🔴 đổi CẢ `role` LẪN `roleGoc` (bảng quyền tra theo vai gốc)',
  /u\.role\s*=\s*vai/.test(thanGl) && /u\.roleGoc\s*=\s*vai/.test(thanGl));
t('🔴 có lối thử "bộ phận để TRỐNG" — chính ca vừa làm hở cái nút',
  thanGl.indexOf('__trong__') > 0);

/* 🔴 CHỈ ADMIN. Cho Quản lý dùng là mở đường xem màn của vai cao hơn mình. */
/* ⚠️ CANH CHÍNH DÒNG GÁN, không canh "có chuỗi 'Admin' ở đâu đó trong hàm". Canh lỏng thì một
   bản vá đổi `bar.style.display='flex'` cứng vẫn xanh, vì chuỗi kia còn nguyên ở dòng trên. */
t('🔴 dải chỉ hiện với Admin', /bar\.style\.display\s*=\s*laAdmin\s*\?/.test(bocHam('glDung')), bocHam('glDung'));
t('   và `laAdmin` tính từ vai thật', /laAdmin\s*=\s*\(\s*\(GL_GOC\|\|CURUSER\|\|\{\}\)\.role\s*===\s*'Admin'/.test(bocHam('glDung')));
t('   thoát sớm nếu không phải Admin', /if\(!laAdmin\)\s*return;/.test(bocHam('glDung')));

/* 🔴 PHẢI NÓI RÕ ĐÂY CHỈ LÀ MÀN HÌNH. Không nói là người thử tin rằng một chốt đang chạy trong
   khi nó chưa từng được thử. */
t('🔴 màn nói rõ máy chủ vẫn biết là Admin', HTML.indexOf('Máy chủ vẫn biết anh là Admin') > 0);
t('   và chip trên đầu nói đang xem như ai', HTML.indexOf('xem như') > 0);
t('   kèm tên thật, khỏi quên mình là ai',   /thật ra: '\s*\+\s*esc\(\s*GL_GOC\.name/.test(HTML));

/* Danh sách bộ phận lấy từ máy chủ, không gõ cứng — hai nơi là hai nơi lệch. */
t('🔴 ô bộ phận đọc BOOT.boPhanDs', /BOOT\s*&&\s*BOOT\.boPhanDs/.test(bocHam('glDung')));
t('   và dựng LẠI sau khi boot xong (lúc ấy mới có danh sách)',
  /_applyTabPerms\(\);[\s\S]{0,400}?glDung\(\);/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — nút gác hai lớp, và Xem như chỉ đổi màn hình');
