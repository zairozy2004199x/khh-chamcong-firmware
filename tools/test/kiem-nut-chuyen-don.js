/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT ĐI SANG LOẠI ĐƠN KIA — và canh dải "👁 Xem như" đã gỡ hẳn
 *
 * Anh Thắng 12/09/2026, hai việc trong một mạch:
 *   *"Đối với nhân viên cơ sở ẩn nút này đi, tránh nhập nhầm"*  (nút "← Quay lại chi phí Kỹ thuật")
 *
 * =============================================================================================
 * 🔴 CHỖ HỞ THẬT CỦA CÁI NÚT: `vis.duan` chỉ bị hạ xuống 0 khi ô Bộ phận CÓ KHAI gì đó. Tài
 *    khoản để trống ô ấy — đúng như ảnh anh Thắng gửi (Nguyễn Văn Bin · Nhân viên · FARM PHAN
 *    THIẾT, không bộ phận) — giữ nguyên `vis.duan = 1`, và nút sáng lên mời họ sang màn không
 *    phải việc của mình.
 *
 * 🔴 DẢI "👁 XEM NHƯ" ĐÃ GỠ ngày 22/09/2026 (*"bỏ này đi"*). Mục 2 của bài này nay canh chiều
 *    NGƯỢC LẠI: không một mẩu nào của nó còn sót ở bất cứ bản nào trong bốn bản.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
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
 * 2. 🔴 DẢI "👁 XEM NHƯ" ĐÃ GỠ — CANH CHO NÓ ĐỪNG MỌC LẠI
 *
 * Anh Thắng 22/09/2026: *"bỏ này đi"*. Dải ấy là công cụ thử của Admin: đổi vai + khối để xem
 * màn của người khác mà không phải đăng xuất.
 *
 * 🔴 GỠ CẢ KHỐI, KHÔNG CHỈ ẨN ĐI. Ẩn thì mã mô phỏng vẫn nằm đó và vẫn sửa được `CURUSER` cùng
 *    `BOOT.khoiXem` từ bảng điều khiển trình duyệt — tức vẫn còn đúng cái đường mà việc gỡ này
 *    muốn đóng, chỉ là không ai nhìn thấy cửa nữa.
 *
 * ⚠️ VÌ SAO CANH BẰNG BÀI KIỂM chứ không chỉ xoá rồi thôi: bản vùng (HN · MTD · VP) được SINH
 *    LẠI từ bản gốc mỗi lần bản gốc lên bản mới. Sót lại một mẩu ở bản gốc là bốn bản cùng có
 *    lại, và lần ấy không ai đi soi `app.html` nữa.
 *
 * ⚠️ Phép canh soi CẢ BỐN BẢN, không chỉ bản gốc — đúng chỗ mà lượt sinh lại có thể bỏ quên.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];
const DAU_VET = ['giaLapBar', 'glDung', 'glDoi', 'glThoat', 'glDangBat', '_glDat',
                 'GL_GOC', 'GL_KHOIXEM', 'GL_KHOIDANG', 'glVai', 'glKhoi', 'glDangXem',
                 'Xem như'];
/**
 * ⚠️ TƯỚC CHÚ THÍCH TRƯỚC KHI SOI.
 *
 * Bia mộ để lại trong mã ("dải Xem như đã gỡ, đừng dựng lại") có NHẮC TÊN thứ vừa gỡ — nên soi
 * chuỗi trên nguyên tệp là bài kiểm tự bắt chính lời ghi chú của mình, rồi đỏ mãi. Chữa bằng
 * cách xoá bia thì mất luôn lời dặn; chữa đúng là chỉ soi phần MÃ CHẠY.
 * (Giữ `https://` — `//` trong địa chỉ không phải chú thích.)
 */
function chiMaChay(h) {
  return h
    .replace(/<!--[\s\S]*?-->/g, ' ')      // chú thích HTML
    .replace(/\/\*[\s\S]*?\*\//g, ' ')    // chú thích JS nhiều dòng
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1');  // chú thích JS một dòng
}

BAN.forEach(function (b) {
  const f = 'wordpress/' + b + '/templates/app.html';
  if (!fs.existsSync(f)) { t('có ' + f, false); return; }
  const h = chiMaChay(fs.readFileSync(f, 'utf8'));
  DAU_VET.forEach(function (d) {
    t('🔴 ' + b + ': không còn dấu vết `' + d + '`', h.indexOf(d) < 0,
      h.indexOf(d) < 0 ? undefined : h.slice(Math.max(0, h.indexOf(d) - 60), h.indexOf(d) + 60));
  });
});

/* ⚠️ Và chip tên người dùng phải thôi hỏi "đang xem như ai" — để lại nhánh ấy là nó đọc một
   biến không còn tồn tại, và CẢ HÀM `applyPerms()` chết giữa chừng. Màn hình trắng, không một
   câu báo nào: đúng kiểu hỏng mà việc gỡ dở dang hay để lại. */
BAN.forEach(function (b) {
  const f = 'wordpress/' + b + '/templates/app.html';
  if (!fs.existsSync(f)) { return; }
  const h = fs.readFileSync(f, 'utf8');
  const i = h.indexOf("el('userChip').innerHTML");
  t('🔴 ' + b + ': chip tên người dùng không còn nhánh "xem như"',
    i >= 0 && !/glDangBat|GL_GOC/.test(h.slice(i, i + 400)), h.slice(i, i + 220));
});

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. GỠ ĐỦ, NHƯNG ĐỪNG GỠ LẠM
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Mục 2 canh chiều "còn sót gì không". Mục này canh chiều ngược lại — CÓ CẮT NHẦM GÌ KHÔNG.
 * Hai chỗ nằm sát ngay cạnh mã vừa gỡ, và mất chúng thì hỏng nặng mà không một câu báo nào:
 *
 * 🔴 `boot()` gọi `_applyTabPerms()` rồi mới gọi `glDung()` — hai lời gọi dính nhau một dòng.
 *    Quét sạch cả cụm là tab khoá cứng sau khi đăng nhập, không ai vào được đâu cả.
 * 🔴 Chip tên người dùng có HAI nhánh, gỡ nhánh "xem như" mà lỡ tay gỡ cả nhánh còn lại là góc
 *    trên màn trống trơn — trông y như chưa đăng nhập.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
BAN.forEach(function (b) {
  const f = 'wordpress/' + b + '/templates/app.html';
  if (!fs.existsSync(f)) { return; }
  const h = fs.readFileSync(f, 'utf8');
  t('🔴 ' + b + ': `boot()` VẪN mở tab theo phân quyền',
    /BOOT=b\|\|BOOT; loading\(false\); _applyTabPerms\(\);/.test(h));
  const i = h.indexOf("el('userChip').innerHTML");
  t('🔴 ' + b + ': chip VẪN nói đủ tên · vai',
    i >= 0 && /esc\(CURUSER\.name\)/.test(h.slice(i, i + 200)) && /esc\(role\)/.test(h.slice(i, i + 200)),
    i >= 0 ? h.slice(i, i + 160) : '(không thấy chip)');
});

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — nút gác hai lớp, và dải Xem như đã gỡ sạch ở cả bốn bản');
