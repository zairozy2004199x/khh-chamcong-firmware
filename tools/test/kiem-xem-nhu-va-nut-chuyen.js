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
/* 🔴 LỐI "bộ phận để TRỐNG" ĐÃ GỠ CÙNG Ô BỘ PHẬN (21/09/2026) — nó thử một trục không còn ai
   khai. Thay vào đó là lối "khối: như tôi", và cái phải canh nay là RA THÌ TRẢ NGUYÊN VẸN:
   `BOOT.khoiXem` là dữ liệu máy chủ, sửa mà không giữ bản gốc thì Admin kẹt ở một khối cho tới
   lúc tải lại trang. Phép chạy thật nằm ở khối dưới. */
t('🔴 thoát thì trả lại cả `BOOT.khoiXem`', /BOOT\.khoiXem\s*=\s*GL_KHOIXEM/.test(thanGl), thanGl);
t('   và cả khối đang đứng',                /KHOI_DANG\s*=\s*GL_KHOIDANG/.test(thanGl));

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

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 Ô THỨ HAI NAY LÀ KHỐI, KHÔNG PHẢI BỘ PHẬN
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng 21/09/2026: *"Chỗ bộ phận anh không dùng, để khối MTĐ · KVC · VP để anh test hệ
 * thống"*.
 *
 * Ô bộ phận là TÀN DƯ: cột Bộ phận đã rời khỏi bảng Người dùng từ 21/09 (*"bỏ tích bộ phận đi,
 * mà tích theo vai trò"*), nên nó thử một trục KHÔNG CÒN AI KHAI — xem như một bộ phận rồi kết
 * luận "chạy đúng" là kết luận về một thứ không tồn tại nữa. Khối mới là trục đang sống: nó
 * quyết định luồng, bảng mã, hộp Gian, dải luồng, và cả việc ai được xuất MISA.
 *
 * ⚠️ Mấy phép cũ về `BOOT.boPhanDs` đã GỠ theo, không phải vì chúng sai mà vì thứ chúng canh
 *    không còn trên màn. Đầu bên kia (`kiem-goi-khoi-dong-bo-phan.php`) vẫn giữ — gói khởi động
 *    còn gửi `boPhanDs` cho những chỗ khác đọc. */
{
  const KHO2 = {
    giaLapBar: { style: { display: '' } },
    glVai:  { value: '', innerHTML: '', options: [] },
    glKhoi: { value: '', innerHTML: '', options: [] },
  };
  const KHOI_DS = [{ ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }];
  KHO2.glKhoi.innerHTML = '';
  new Function('BOOT', 'CURUSER', 'GL_GOC', 'VAI_GOC', 'KHOI_DS', 'el', 'esc',
    bocHam('glDung') + '\nglDung();')(
    {}, { role: 'Admin' }, null, ['Quản lý', 'Nhân viên'], KHOI_DS,
    (id) => KHO2[id], (x) => String(x == null ? '' : x));
  const h = KHO2.glKhoi.innerHTML;
  teq('🔴 ô khối có "như tôi" + đủ ba khối', 4, (h.match(/<option/g) || []).length);
  t('🔴 đủ ba mã khối', /value="kvc"/.test(h) && /value="mtd"/.test(h) && /value="vp"/.test(h), h);
  t('   và bày TÊN người đọc được, không bày mã', h.indexOf('Máy tự động') > 0 && h.indexOf('Khu vui chơi') > 0, h);
  t('   vẫn giữ lối "như tôi"', h.indexOf('— khối: như tôi —') > 0);
  /* ⚠️ DANH SÁCH LẤY TỪ `KHOI_DS`, cùng nguồn với thanh KHỐI — gõ cứng ba mã trong `glDung()` là
     hai nơi lệch nhau, mà lệch nghĩa là thử một khối không tồn tại rồi kết luận nhầm. */
  t('⚠️ đọc `KHOI_DS`, không gõ cứng ba mã',
    /KHOI_DS\.map/.test(bocHam('glDung')) && !/'mtd'/.test(bocHam('glDung')), bocHam('glDung'));
  t('🔴 ô bộ phận đã gỡ hẳn khỏi màn', HTML.indexOf("id=\"glBp\"") < 0);
}

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 XEM NHƯ MỘT KHỐI = MÔ PHỎNG NGƯỜI CHỈ THUỘC KHỐI ẤY, VÀ RA THÌ TRẢ NGUYÊN VẸN
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Chỉ đổi tab đang chọn thì chẳng thử được gì — Admin vốn bấm sang khối nào cũng được. Thứ đáng
 * thử là "người CHỈ thuộc MTĐ thấy gì": thanh KHỐI còn đúng một nút, và mọi màn lọc theo nó.
 *
 * 🔴 VÀ PHẢI TRẢ LẠI ĐƯỢC. `BOOT.khoiXem` là dữ liệu MÁY CHỦ gửi xuống, không phải của người
 *    dùng — sửa mà không giữ bản gốc thì "Thôi, về tài khoản của tôi" trả được vai nhưng KHÔNG
 *    trả được khối, và Admin kẹt ở một khối cho tới lúc tải lại trang. */
{
  const KHO3 = { glVai: { value: '' }, glKhoi: { value: '' }, glDangXem: { textContent: '' } };
  const BOOT3 = { khoiXem: null };
  const moi = {
    BOOT: BOOT3, CURUSER: { name: 'Thắng', role: 'Admin' }, GL_GOC: null,
    GL_KHOIXEM: null, GL_KHOIDANG: null, KHOI_DANG: 'kvc',
    el: (id) => KHO3[id], esc: (x) => String(x == null ? '' : x),
    _tenKhoi: (m) => ({ kvc: 'Khu vui chơi', mtd: 'Máy tự động', vp: 'Văn phòng' }[m] || m),
    /* `_glDat` rào `window.BOOT` (trang thật chạy trong trình duyệt). Trong node không có
       `window`, nên bệ đỡ phải dựng một cái trỏ về đúng BOOT giả — không thì hàm nổ ở dòng
       rào, và bài kiểm đỏ vì bệ đỡ chứ không vì mã. */
    window: { get BOOT() { return BOOT3; }, set BOOT(v) { } },
    applyPerms: () => {}, showPage: () => {}, defaultPageFor: () => 'don',
    _vaiLuat: () => 'Admin', veThanhKhoi: () => {}, toast: () => {},
  };
  const f = new Function('moi', `with(moi){
    ${bocHam('_glDat')}
    ${bocHam('glThoat')}
    return { vao:_glDat, ra:glThoat, xem:function(){ return {khoiXem:BOOT.khoiXem, dang:KHOI_DANG, goc:!!GL_GOC}; } };
  }`)(moi);
  f.vao('', 'mtd');
  let x = f.xem();
  teq('🔴 xem như MTĐ → thanh khối chỉ còn khối ấy', ['mtd'], x.khoiXem);
  teq('🔴 và khối đang đứng nhảy sang MTĐ', 'mtd', x.dang);
  t('   nhãn nói rõ đang xem như khối nào', /Máy tự động/.test(KHO3.glDangXem.textContent), KHO3.glDangXem.textContent);
  f.ra();
  x = f.xem();
  teq('🔴 thoát ra → trả NGUYÊN VẸN `BOOT.khoiXem`', null, x.khoiXem);
  teq('🔴 và trả nguyên vẹn khối đang đứng', 'kvc', x.dang);
  t('   và thôi đánh dấu đang xem như người khác', x.goc === false);
  t('   xoá luôn nhãn', KHO3.glDangXem.textContent === '');
}
t('   và dựng LẠI sau khi boot xong (lúc ấy mới có `BOOT`)',
  /_applyTabPerms\(\);[\s\S]{0,400}?glDung\(\);/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — nút gác hai lớp, và Xem như chỉ đổi màn hình');
