/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BẢNG CƠ SỞ RỖNG PHẢI NÓI VÌ SAO RỖNG
 *
 * Cắn thật 11/09/2026: anh Thắng mở ⚙️ Cấu hình, bảng "🏢 Mã đơn vị theo Cơ sở" trắng trơn, chỉ
 * còn hàng tiêu đề. Câu đầu tiên: *"mã đơn vị theo cơ sở bị ẩn mất rồi"* — tưởng mất dữ liệu.
 * Không mất gì: tài khoản lúc ấy bị bó theo Đơn vị nên máy chủ lọc sạch danh mục.
 *
 * =============================================================================================
 * 🔴 MỘT BẢNG TRẮNG CÓ HAI NGHĨA HOÀN TOÀN KHÁC NHAU:
 *      "chưa khai cơ sở nào"  ·  "khai rồi nhưng tài khoản này không được xem"
 *    Hai nghĩa ấy dẫn tới hai việc phải làm trái ngược (đi khai · đi sửa phân quyền), mà màn
 *    thì vẽ y hệt nhau. Người dùng không có cách nào phân biệt — nên màn phải nói ra.
 *
 * 🔴 KHÔNG ĐƯỢC NÓI NHẦM CHIỀU. Bày câu "bạn bị giới hạn đơn vị" cho một sổ thật sự chưa khai
 *    gì là đuổi người ta đi sửa phân quyền suốt buổi trong khi việc cần làm là bấm "+ Thêm cơ
 *    sở". Nên bài kiểm dưới canh CẢ HAI chiều, không chỉ chiều vừa cắn.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY, không chép lại.
 *
 * Chạy: node tools/test/kiem-bang-coso-rong-noi-ly-do.js
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
const fnRender = bocHam('renderCosoBody');
t('bốc được renderCosoBody()', fnRender.length > 500, fnRender.length);
/* ⚠️ Từ 22/09/2026 `renderCosoBody()` bù thêm nhóm cho khối CHƯA CÓ CƠ SỞ NÀO (để Văn phòng tạo
   được cơ sở đầu tiên — xem `kiem-khoi-vp-tu-tao-coso.js`), và phép bù ấy hỏi `_xemDuocDv()`.
   🔴 MƯỢN HÀM THẬT, đừng chép thêm một bản vào `KHOI_THAT`: bản chép sẽ trôi lệch, và lệch ở
      đúng chỗ quyết định "kế toán bị bó khối có thấy bảng của khối khác không". */
const fnXemDuoc = bocHam('_xemDuocDv');
t('bốc được _xemDuocDv()', fnXemDuoc.length > 80, fnXemDuoc.length);

/* Bệ đỡ: giữ lại đúng thứ bài kiểm cần đọc — nội dung đổ vào `cfgCosoBody`, và có gọi khoá
   bảng lại hay không. `el()` trả về một ô giả cho MỌI id, kể cả `dl_pll`/`dl_donvi`/`dl_tinh`. */
/* 🔴 BỐC MÃ THẬT, ĐỪNG BỊA LẠI LUẬT Ở ĐÂY. Từ 21/09/2026 cột Bộ phận đã rời bảng Người
   dùng, nên luật nào cần bộ phận thì đọc lại từ TÊN VAI CON. Bịa một bản ở bài kiểm là nó
   canh luật của chính nó, xanh vĩnh viễn dù bản thật đi đường khác. */
const KHOI_THAT = `  var KHOI_DS=[{ma:'mb',ten:'Miền Bắc'},{ma:'mn',ten:'Miền Nam'},{ma:'kvc',ten:'Khu vui chơi'},{ma:'mtd',ten:'Máy tự động'},{ma:'vp',ten:'Văn phòng'}];
  var KHOI_DV_DUP={mb:['MB'],mn:['MN'],kvc:['KVC'],mtd:['MT\\u0110','MTD','POSH'],vp:['VP','V\\u0102N PH\\u00d2NG','VAN PHONG']};
  /* 🔴 MIỀN (22/09/2026) — \`renderCosoBody()\` bù nhóm theo \`_mienDs()\`, không theo cả từ điển
     khối: ba mã cũ đã ra web riêng, bù chúng là ba nhóm rỗng vĩnh viễn trên đầu bảng. */
  var MIEN_MA=['mb','mn'];
  function _mienDs(){
    var ra=KHOI_DS.filter(function(x){ return MIEN_MA.indexOf(x.ma)>=0; });
    return ra.length ? ra : [{ma:'mb',ten:'Miền Bắc'},{ma:'mn',ten:'Miền Nam'}];
  }
  function _khoiDvBang(){
    var b=(typeof BOOT!=='undefined' && BOOT) ? BOOT.khoiTheoDv : null;
    return (b && b.kvc) ? b : KHOI_DV_DUP;
  }
  function _khoiCuaDv(dv){
    var k=String(dv==null?'':dv).trim().toUpperCase();
    if(!k) return '';
    var b=_khoiDvBang();
    for(var i=0;i<KHOI_DS.length;i++){
      var ma=KHOI_DS[i].ma, ds=b[ma]||[];
      for(var j=0;j<ds.length;j++){ if(String(ds[j]).toUpperCase()===k) return ma; }
    }
    return '';
  }
  function _dvChuanCuaKhoi(ma){
    var ds=_khoiDvBang()[String(ma||'').toLowerCase()]||[];
    return ds[0]||'';
  }
  function _tenKhoi(ma){
    for(var i=0;i<KHOI_DS.length;i++){ if(KHOI_DS[i].ma===ma) return KHOI_DS[i].ten; }
    return '';
  }
  function _khoiSelCoso(dv){
    var cu=String(dv==null?'':dv).trim();
    var k=_khoiCuaDv(cu);
    var h='<select style="width:100%" title="Khối của gian này — quyết định chi phí của nó rơi vào sổ nào.">';
    if(!k){
      h+='<option value="'+esc(cu)+'" selected>'
        +(cu ? ('— giữ nguyên: '+esc(cu)+' —') : '— chưa khai ('+esc(_dvMacDinh())+') —')+'</option>';
    }
    h+=KHOI_DS.map(function(x){
      /* Ô đang chọn giữ CHUỖI CŨ; các ô kia mang tên chuẩn — xem chốt 🔴 ở trên. */
      var v=(x.ma===k) ? cu : _dvChuanCuaKhoi(x.ma);
      return '<option value="'+esc(v)+'"'+(x.ma===k?' selected':'')+'>'+esc(x.ten)+'</option>';
    }).join('');
    return h+'</select>';
  }`;
function chay(coso, xemDonVi) {
  const O = {};
  const NK = { khoa: 0, doLa: 0 };
  function el(id) { if (!O[id]) { O[id] = { id: id, innerHTML: '' }; } return O[id]; }
  function esc(x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
  const CFG = { coso: coso };
  const BOOT = { donVi: ['K&H', 'POSH', 'KVC'], xemDonVi: xemDonVi };
  new Function('CFG', 'BOOT', 'el', 'esc', '_inp', '_delBtn', '_dvMacDinh', '_csLock', 'doCoSoLa', 'doDongCua',
    KHOI_THAT + '\n' + fnXemDuoc + '\n' + fnRender + '\nrenderCosoBody();')(
    CFG, BOOT, el, esc,
    function (v) { return '<input value="' + esc(v) + '">'; },
    function () { return '<td></td>'; },
    function () { return 'K&H'; },
    function () { NK.khoa++; },
    function () { NK.doLa++; },
    function () {});
  return { html: O['cfgCosoBody'] ? O['cfgCosoBody'].innerHTML : null, nk: NK };
}

const CS = [
  { ten: 'ADV GO! AN LẠC', donVi: 'KVC', tenMisa: 'ADV Go An Lac', maDonVi: 'EVFZADVGAL', phanLoaiLon: 'EVENT FZ MN', tinh: 'TP HCM' },
];

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. BÓ THEO ĐƠN VỊ MÀ BẢNG RỖNG → nói rõ là phân quyền, KHÔNG phải mất dữ liệu
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const boPosh = chay([], ['POSH']);
t('🔴 nói thẳng "Không phải mất dữ liệu"', boPosh.html.indexOf('Không phải mất dữ liệu') >= 0, boPosh.html);
t('🔴 gọi đúng tên đơn vị đang bó (POSH)', boPosh.html.indexOf('POSH') >= 0, boPosh.html);
t('   chỉ đường đi sửa (ô "Đơn vị")',  boPosh.html.indexOf('<b>Đơn vị</b>') >= 0, boPosh.html);
t('🔴 KHÔNG bày nhầm câu "chưa khai cơ sở nào"', boPosh.html.indexOf('Chưa khai cơ sở nào') < 0, boPosh.html);
t('   vẫn khoá bảng lại như thường lệ',  boPosh.nk.khoa > 0, boPosh.nk);
t('   vẫn dò cơ sở lạ như thường lệ',    boPosh.nk.doLa > 0, boPosh.nk);

const boHai = chay([], ['POSH', 'KVC']);
t('bó hai đơn vị thì kể đủ cả hai · POSH', boHai.html.indexOf('POSH') >= 0, boHai.html);
t('                              · KVC',   boHai.html.indexOf('KVC') >= 0, boHai.html);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CHIỀU NGƯỢC LẠI: SỔ THẬT SỰ TRỐNG → chỉ đường đi KHAI, đừng đổ cho phân quyền
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
/* ⚠️ TỪ 22/09/2026 CẢNH "XEM CẢ MÀ RỖNG" ĐỔI CÁCH BÀY, không đổi ý. Trước đây là MỘT câu
   "📭 Chưa khai cơ sở nào. Bấm + Thêm cơ sở"; nay là BA nhóm khối, mỗi nhóm một câu "chưa có cơ
   sở nào" và một nút "+ Thêm cơ sở cho <khối>".
   🔴 VÌ SAO ĐỔI: nút chung không trả lời được câu "thêm vào khối nào?", và Văn phòng — vốn
      không có gian vật lý nào — thì không có nhóm nên KHÔNG CÓ CÁCH NÀO tạo cơ sở đầu tiên
      (xem `kiem-khoi-vp-tu-tao-coso.js`). Ý cần canh vẫn nguyên: bảng rỗng phải nói vì sao
      rỗng, và KHÔNG được đổ oan cho phân quyền. */
const xemCa = chay([], null);   // 🔴 `xemDonVi` null = XEM CẢ, không phải "không xem gì"
t('🔴 xem cả mà bảng rỗng → nói rõ từng khối chưa có cơ sở',
  xemCa.html.indexOf('Khối này chưa có cơ sở nào') >= 0, xemCa.html);
/* 🔴 TỪ 22/09/2026 NHÓM BÙ LÀ **MIỀN** — anh Thắng: *"Khối là liên quan Miền Bắc và Miền Nam
   ôi"*. Ba mã cũ đã ra web riêng; bù chúng là ba nhóm rỗng vĩnh viễn nằm trên đầu bảng. Nhóm
   của khối cũ CÒN cơ sở vẫn hiện — vòng gom lo việc ấy, chỗ bù chỉ thêm nhóm còn thiếu. */
t('   và bày đủ hai MIỀN để chọn chỗ thêm',
  ['Miền Bắc', 'Miền Nam'].every(function (k) { return xemCa.html.indexOf('Thêm cơ sở cho ' + k) >= 0; }), xemCa.html);
t('   và KHÔNG bù nhóm rỗng cho khối đã ra web riêng',
  ['Khu vui chơi', 'Máy tự động', 'Văn phòng'].every(function (k) { return xemCa.html.indexOf('Thêm cơ sở cho ' + k) < 0; }), xemCa.html);
t('🔴 KHÔNG đổ oan cho phân quyền', xemCa.html.indexOf('Không phải mất dữ liệu') < 0, xemCa.html);
/* Chỉ Văn phòng mới được nhắc "tạo cơ sở đại diện" — nơi khác có gian thật, nhắc thế là mời
   khai một gian không tồn tại.
   🔴 VÀ TỪ 22/09/2026 NHÓM BÙ LÀ MIỀN, mà miền nào cũng có gian thật — nên câu ấy KHÔNG được
      hiện ở nhóm miền. Nó vẫn còn trong mã, gác bằng mã khối 'vp', cho nhóm Văn phòng nào còn
      sống trong dữ liệu cũ. */
t('⚠️ câu gợi ý "cơ sở đại diện" KHÔNG hiện ở nhóm miền',
  (xemCa.html.match(/không có gian vật lý/g) || []).length === 0, xemCa.html);
/* ⚠️ VẾ "HOẶC" LÀ MỘT PHÉP KHÔNG BAO GIỜ ĐỎ — bỏ. Soi đúng cái cổng: câu ấy nằm sau một
   điều kiện gác bằng MÃ KHỐI 'vp', không phải bày cho mọi nhóm rỗng. */
t('   nhưng vẫn còn trong mã, gác bằng mã khối vp',
  /_khoiCuaDv\(d\)==='vp'[\s\S]{0,240}không có gian vật lý/.test(HTML), 'không thấy');

const xemCaRong = chay([], []);   // mảng RỖNG cũng phải hiểu là "không bó ai"
t('mảng rỗng cũng là chưa khai, không phải bị bó',
  xemCaRong.html.indexOf('Khối này chưa có cơ sở nào') >= 0 && xemCaRong.html.indexOf('Không phải mất dữ liệu') < 0, xemCaRong.html);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. CÓ CƠ SỞ THÌ VẼ BẢNG NHƯ CŨ — lời nhắc không được chen vào
 *
 * ⚠️ Đây là phép đối chứng: thiếu nó thì một bản vá "luôn hiện lời nhắc" vẫn xanh cả mục 1 lẫn
 *    mục 2, trong khi màn thật đã hỏng với mọi người đang dùng.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const coDu = chay(CS, ['KVC']);
/* Dải nhóm đổi từ "🏢 ĐƠN VỊ KVC" sang "🧩 KHỐI Khu vui chơi" 21/09/2026 — anh Thắng:
   *"đơn vị cơ sở theo khối — chuyển cột Đơn Vị sang Khối"*. */
t('có cơ sở → vẫn dựng dải nhóm, nay đặt tên theo KHỐI',
  coDu.html.indexOf('KHỐI Khu vui chơi') >= 0, coDu.html);
t('🔴 và cột đơn vị thành Ô CHỌN khối, không còn ô gõ tay',
  coDu.html.indexOf('list="dl_donvi"') < 0 && /<select[^>]*>[\s\S]*Khu vui chơi/.test(coDu.html), coDu.html);
t('   vẫn dựng dải phân loại lớn',        coDu.html.indexOf('EVENT FZ MN') >= 0, coDu.html);
t('   vẫn vẽ hàng cơ sở',                 coDu.html.indexOf('EVFZADVGAL') >= 0, coDu.html);
t('   vẫn giữ cột Tỉnh (1.137.0)',        coDu.html.indexOf('TP HCM') >= 0, coDu.html);
t('🔴 KHÔNG chen lời nhắc vào bảng có dữ liệu',
  coDu.html.indexOf('Không phải mất dữ liệu') < 0 && coDu.html.indexOf('Chưa khai cơ sở nào') < 0, coDu.html);

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — bảng cơ sở rỗng nói rõ vì sao rỗng');
