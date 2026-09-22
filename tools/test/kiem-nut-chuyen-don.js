/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT ĐI SANG LOẠI ĐƠN KIA — HAI LỚP GÁC
 *
 * Anh Thắng 12/09/2026: *"Đối với nhân viên cơ sở ẩn nút này đi, tránh nhập nhầm"* (nút
 * "← Quay lại chi phí Kỹ thuật").
 *
 * =============================================================================================
 * 🔴 CHỖ HỞ THẬT CỦA CÁI NÚT: `vis.duan` chỉ bị hạ xuống 0 khi ô Bộ phận CÓ KHAI gì đó. Tài
 *    khoản để trống ô ấy — đúng như ảnh anh Thắng gửi (Nguyễn Văn Bin · Nhân viên · FARM PHAN
 *    THIẾT, không bộ phận) — giữ nguyên `vis.duan = 1`, và nút sáng lên mời họ sang màn không
 *    phải việc của mình.
 *
 * =============================================================================================
 * ⚠️ PHẦN "👁 XEM NHƯ" ĐÃ GỠ KHỎI BÀI NÀY — 22/09/2026
 * =============================================================================================
 * Anh Thắng: *"loại bỏ tính năng này"*, kèm ảnh chụp dải Xem như. Bài trước canh khoảng ba chục
 * phép về nó (giữ bản sao tài khoản thật, trả lại nguyên vẹn khi thoát, ô khối đọc `KHOI_DS`…).
 * Gỡ hết, và tệp đổi tên theo — `kiem-xem-nhu-va-nut-chuyen.js` → `kiem-nut-chuyen-don.js`.
 *
 * 🔴 NHƯNG GIỮ LẠI MẤY PHÉP CANH "ĐÃ GỠ THẬT". Một tính năng bị gỡ mà không ai canh là một tính
 *    năng chờ ngày quay lại: một lượt merge lùi, một lần khôi phục nhầm tệp, và dải ấy hiện ra
 *    lại mà không ai hay. Xem khối cuối tệp.
 *
 * Chạy: node tools/test/kiem-nut-chuyen-don.js
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
/* 🔴 BỐC MÃ THẬT: từ 21/09/2026 bộ phận đọc lại từ TÊN VAI CON (cột Bộ phận đã rời bảng
   Người dùng). Bịa một bản ở đây là bài kiểm canh luật của chính nó. */
const BP_THAT = `  var BP_THEO_TEN_VAI=[
    {bp:'Kỹ thuật', tu:['ky thuat']},
    {bp:'Cơ sở',    tu:['co so']},
    {bp:'Marketing', tu:['marketing']},
    {bp:'Văn phòng', tu:['van phong']}
  ];
  function _boDauVai(s){
    return String(s==null?'':s).toLowerCase().replace(/\\u0111/g,'d')
      .normalize('NFD').replace(/[\\u0300-\\u036f]/g,'').replace(/\\s+/g,' ').trim();
  }
  function _bpCuaVai(ten){
    var t=' '+_boDauVai(ten)+' ';
    if(t===' ') return '';
    for(var i=0;i<BP_THEO_TEN_VAI.length;i++){
      var x=BP_THEO_TEN_VAI[i];
      for(var j=0;j<x.tu.length;j++){ if(t.indexOf(' '+x.tu[j]+' ')>=0) return x.bp; }
    }
    return '';
  }
  function _bpCuaToi(){
    var b=String((CURUSER&&CURUSER.boPhan)||'').trim();
    if(b) return b;
    return _bpCuaVai((CURUSER&&CURUSER.role)||'');
  }`;
function nut(vai, bp, vis) {
  const B = { duan: { style: { display: '' } }, don: { style: { display: '' } } };
  /* Từ 21/09/2026 `_veNutChuyenDon()` so luật qua `_vaiLuat()` — vai con làm được việc của
     vai cha. Bệ đỡ phải có cả hai, không thì hàm thật nổ ReferenceError. */
  new Function('CURUSER', 'document', 'BP_VAO_DUAN', '_vaiGoc', '_vaiLuat', '_vaoDonCoSo', 'vis',
    BP_THAT + '\n' + fnNut + '\n_veNutChuyenDon(vis);')(
    { role: vai, roleGoc: vai, boPhan: bp },
    { querySelectorAll: () => [
      Object.assign(B.duan, { getAttribute: () => 'duan' }),
      Object.assign(B.don,  { getAttribute: () => 'don' }),
    ] },
    ['Văn phòng', 'Kỹ thuật'],
    function () { return vai; },
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
 * 2. DẢI "👁 XEM NHƯ" PHẢI BIẾN MẤT HẲN — 22/09/2026
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng: *"loại bỏ tính năng này"*.
 *
 * 🔴 GỠ NỬA VỜI LÀ TỆ NHẤT. Để lại mấy hàm mồ côi thì lượt sửa sau có người gọi nhầm; để lại
 *    thẻ HTML ẩn thì một dòng CSS lạc là nó hiện ra. Canh CẢ HAI ĐẦU: không còn thẻ trên màn,
 *    không còn hàm trong mã, và không còn chỗ nào gọi tới.
 * ⚠️ Canh bằng TÊN RIÊNG (`glDung`, `GL_GOC`, `giaLapBar`) chứ không bằng chữ "xem như" chung
 *    chung — chữ ấy còn nằm trong chú thích kể lại chuyện đã gỡ, và canh theo nó là bài đỏ
 *    mỗi lần ai đó viết một câu giải thích.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
['giaLapBar', 'glVai', 'glKhoi', 'glDangXem'].forEach(function (id) {
  t('🔴 không còn thẻ `' + id + '` trên màn', HTML.indexOf('id="' + id + '"') < 0);
});
['glDung', 'glDoi', 'glThoat', '_glDat', 'glDangBat'].forEach(function (fn) {
  t('🔴 không còn hàm `' + fn + '()`', HTML.indexOf('function ' + fn + '(') < 0);
  t('   và không còn chỗ nào gọi `' + fn + '()`', HTML.indexOf(fn + '()') < 0);
});
['GL_GOC', 'GL_KHOIXEM', 'GL_KHOIDANG'].forEach(function (v) {
  t('🔴 không còn biến `' + v + '`', HTML.indexOf(v) < 0);
});
t('🔴 và câu trấn an "Máy chủ vẫn biết anh là Admin" cũng đi theo',
  HTML.indexOf('Máy chủ vẫn biết anh là Admin') < 0);

/* 🔴 CHIP NGƯỜI DÙNG PHẢI VỀ MỘT CÂU DUY NHẤT. Nhánh "👁 xem như …" đọc `GL_GOC.name`; bỏ biến
   mà quên nhánh là chip nổ `ReferenceError` ngay lượt vẽ đầu — trắng luôn góc trên màn. */
{
  const ap = bocHam('applyPerms');
  t('⚠️ bốc được `applyPerms`', ap.length > 200, ap.length);
  t('🔴 chip thôi rẽ nhánh theo trạng thái xem-như', !/glDangBat\(\)/.test(ap), ap);
  t('   và vẫn nói đủ tên · vai · cơ sở',
    /userChip'\)\.innerHTML='👤 '\+esc\(CURUSER\.name\)/.test(ap) && /esc\(role\)/.test(ap), ap);
}

/* ⚠️ `boot()` từng gọi lại `glDung()` sau khi nạp gói khởi động. Lời gọi ấy phải đi, nhưng
   `_applyTabPerms()` ngay trước nó thì PHẢI Ở LẠI — nó mở tab theo phân quyền, không liên quan
   gì tới dải vừa gỡ. Gỡ nhầm cả cụm là mọi tab khoá cứng sau khi đăng nhập. */
t('🔴 `boot()` vẫn mở tab theo phân quyền', /BOOT=b\|\|BOOT; loading\(false\); _applyTabPerms\(\);/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — nút gác hai lớp, và dải Xem như đã gỡ sạch');
