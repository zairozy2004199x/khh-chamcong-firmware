/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN: LOẠI CHI PHÍ THUỘC NHIỀU BỘ PHẬN (phần chạy trên trình duyệt).
 *
 * Anh Thắng 10/09/2026: *"Cho phép loại chi phí chọn theo bộ phận, nhiều bộ phận sẽ chọn loại
 * chi phí đó cùng tên, chỉ là mỗi cơ sở khác mã thôi"*. Bài kiểm phía máy chủ nằm ở
 * `kiem-loai-nhieu-bo-phan.php`; bài này soi phần màn.
 *
 * =============================================================================================
 * 🔴 ĐÂY LÀ CHỖ ANH THẮNG GẶP HỎNG: ô "Loại chi phí" trong màn dự án TRỐNG TRƠN, chỉ còn dòng
 *    "— chưa gắn mã —". Vì bộ lọc so NGUYÊN CHUỖI ô Bộ phận với bộ phận người dùng, nên loại
 *    khai "Kỹ thuật, Setup" không khớp ai cả. Và từ bản bỏ ô "Nội dung hạng mục", ô này trống
 *    nghĩa là KHÔNG NHẬP ĐƯỢC DÒNG NÀO — nội dung dòng lấy theo chính nó.
 *
 * 🔴 LOẠI ĐÃ KHAI BỘ PHẬN THÌ HIỆN, DÙ CHƯA CÓ MÃ. Anh Thắng 10/09/2026: *"theo kỹ thuật đang
 *    có 2 loại chi phí, nhưng mới hiện 1 loại thôi"*. Tích bộ phận là việc người ta CỐ Ý làm;
 *    còn vài trăm dòng rác nạp từ sổ cũ thì bỏ trống ô ấy nên vẫn phải ẩn như trước.
 *
 * ⚠️ CHẠY THẬT `_bpTach()` và `_loaiCpList()` bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-loai-nhieu-bo-phan.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* ── 1. TÁCH Ô BỘ PHẬN — CHẠY THẬT ─────────────────────────────────────────────────────── */
const T = new Function(`${boc('_bpTach')} return _bpTach;`)();
teq('🔴 nhiều bộ phận → tách ra đủ', ['Kỹ thuật', 'Setup'], T('Kỹ thuật, Setup'));
teq('   một bộ phận (dữ liệu CŨ) → vẫn đúng', ['Kỹ thuật'], T('Kỹ thuật'));
teq('   ô trống → rỗng', [], T(''));
teq('   null / undefined → rỗng, không nổ', [], T(null));
teq('   thừa dấu phẩy và khoảng trắng → dọn sạch', ['Kỹ thuật', 'Setup'], T(' Kỹ thuật ,, Setup , '));

/* ── 2. LỌC DANH MỤC — CHẠY THẬT ───────────────────────────────────────────────────────── */
/* 🔴 BỐC MÃ THẬT, ĐỪNG BỊA LẠI LUẬT. Từ 21/09/2026 cột Bộ phận đã rời bảng Người dùng, nên
   luật nào cần bộ phận thì đọc lại từ TÊN VAI CON — anh Thắng: *"dùng hết trên vai trò cha,
   con rồi"*. Bịa một bản ở bài kiểm là nó canh luật của chính nó, xanh vĩnh viễn dù bản thật
   đi đường khác. */
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
function loc(boPhanNguoiDung, dsLoai, coMa) {
  const moi = {
    CURUSER: { boPhan: boPhanNguoiDung },
    NHOM_CP: '', NHOM_CP_CS: 'Cơ sở',
    BOOT: { loaiChiPhi: dsLoai, tkNoMx: {}, khoiBan: 'kvc' },
    KHOI_DANG: 'kvc',
    CURUSER: { role: 'Admin' },   /* Admin không bị lọc theo vai — xem `_vaiDungDuocLoai()` */
    _mangCua: () => '',
    _tkNoCua: (ten) => (coMa && coMa.indexOf(ten) < 0) ? '' : '6421',
    _tkNoList: () => [],
    _donNhieuCoSo: () => false,
    _mangPham: () => [],
    el: () => null,
  };
/* ⚠️ `_khoiCuaLoai` + `KHOI_DANG` thêm 21/09/2026 — loại chi phí nay thuộc đúng một khối
     (anh Thắng: *"chia ra 3 bảng của 3 khối, để tránh dùng chung"*) và `_loaiCpList()` bỏ
     loại của khối khác. Thiếu trong bệ đỡ là bài kiểm nổ `ReferenceError`. */
  const F = new Function('moi', `with(moi){ ${BP_THAT}\n${boc('_khoiCuaLoai')}\n${boc('_vaiDungDuocLoai')}\n${boc('_bpTach')}\n${boc('_khoaNhom')}\n${boc('_loaiCpList')}
    return _loaiCpList; }`)(moi);
  return F('', '').map(x => x.ten);
}
const DS = [
  { ten: 'Chi phí setup',     boPhan: 'Kỹ thuật, Setup' },   // dùng chung hai bộ phận
  { ten: 'Chi phí tháo dỡ',   boPhan: 'Kỹ thuật' },          // riêng Kỹ thuật (dữ liệu CŨ)
  { ten: 'Chi phí quảng cáo', boPhan: 'Marketing' },
  { ten: 'Chi phí điện nước', boPhan: '' },                  // chưa khai -> dùng chung
];
/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 TỪ 20/09/2026: Ô CHỌN LOẠI KHÔNG CÒN CẮT THEO BỘ PHẬN CỦA NGƯỜI ĐĂNG NHẬP
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Anh Thắng: *"Vừa phân theo bộ phận, vừa phân theo mảng. Dẫn đến xung đột — chọn mảng thì sai,
 * bộ phận cũng không có"*, rồi chốt bỏ trục bộ phận khỏi ô nhập.
 *
 * Mấy phép dưới đây TRƯỚC ĐÂY đòi ngược lại ("người Kỹ thuật chỉ thấy loại Kỹ thuật"). Chúng
 * sinh ra ngày 10/09 khi cột Bộ phận vừa được dùng để LỌC — và đúng ở thời điểm ấy. Nay cột ấy
 * đổi vai: nó chỉ còn để DỰNG mấy nút "Chọn chi phí nào" (Cơ sở · Văn phòng · Setup…), tức
 * người nhập TỰ CHỌN thay vì bị cắt ngầm theo danh tính.
 *
 * ⚠️ Ý 10/09 KHÔNG MẤT — nó chuyển chỗ: một loại vẫn khai được nhiều bộ phận, và `_bpTach()`
 *    vẫn phải tách đúng (mấy phép ngay trên vẫn canh). Chỉ khác: danh sách ấy nay quyết định
 *    loại nằm dưới NÚT nào, không quyết định AI thấy nó.
 * ══════════════════════════════════════════════════════════════════════════════════════════════ */
const HET = ['Chi phí setup', 'Chi phí tháo dỡ', 'Chi phí quảng cáo', 'Chi phí điện nước'];
teq('🔴 người Kỹ thuật thấy ĐỦ danh mục — bộ phận thôi cắt ngầm', HET, loc('Kỹ thuật', DS));
teq('🔴 người Setup cũng thấy đủ', HET, loc('Setup', DS));
teq('   người Marketing cũng thế', HET, loc('Marketing', DS));
teq('🔴 người Công tác cũng thế — trước đây chỉ thấy đúng một loại', HET, loc('Công tác', DS));
teq('🔴 người không bị bó bộ phận → vẫn thấy hết (không đổi)', HET, loc('', DS));

/* 🔴 CA TỪNG LÀM Ô TRỐNG TRƠN — đúng ảnh anh Thắng gửi 10/09: danh mục toàn loại của bộ phận
   khác. Trước đây ra rỗng, và từ bản bỏ ô "Nội dung hạng mục" thì rỗng nghĩa là KHÔNG NHẬP
   ĐƯỢC DÒNG NÀO. Nay không còn cửa nào dẫn tới cảnh ấy. */
teq('🔴 danh mục toàn loại bộ phận khác → VẪN chọn được, không còn ô trống trơn',
  ['Chi phí quảng cáo'], loc('Kỹ thuật', [{ ten: 'Chi phí quảng cáo', boPhan: 'Marketing' }]));


/* ── 3. 🔴 CHƯA KHAI MÃ VẪN HIỆN, NẾU ĐÃ KHAI BỘ PHẬN ──────────────────────────────────── */
const DS2 = [
  { ten: 'Chi phí tháo dỡ', boPhan: 'Kỹ thuật' },   // có mã
  { ten: 'Chi phí setup',   boPhan: 'Kỹ thuật' },   // CHƯA có mã, nhưng đã tích bộ phận
  { ten: 'Vật tư và tiếp khách từ 18-26/7', boPhan: '' },   // rác nạp từ sổ cũ, chưa có mã
];
teq('🔴 loại đã tích bộ phận thì HIỆN dù chưa khai mã (đúng ca anh Thắng gặp)',
  ['Chi phí tháo dỡ', 'Chi phí setup'], loc('Kỹ thuật', DS2, ['Chi phí tháo dỡ']));
teq('🔴 nhưng dòng RÁC từ sổ cũ (không bộ phận, không mã) vẫn bị ẩn — ô chọn không thành vài trăm dòng',
  ['Chi phí tháo dỡ', 'Chi phí setup'], loc('', DS2, ['Chi phí tháo dỡ']));
teq('   khai mã cho nó thì hiện, như trước',
  ['Chi phí tháo dỡ', 'Chi phí setup', 'Vật tư và tiếp khách từ 18-26/7'],
  loc('', DS2, ['Chi phí tháo dỡ', 'Vật tư và tiếp khách từ 18-26/7']));
/* 🔴 VÀ NGƯỜI BỘ PHẬN KHÁC NAY CŨNG THẤY — phép này trước đòi `[]`. Cắt ngầm theo danh tính đã
   bỏ (20/09/2026); thứ còn cắt là MÃ theo mảng và nút "Chọn chi phí nào", cả hai đều nhìn thấy
   được và đều có dòng nhắc kể lý do. */
teq('🔴 người bộ phận khác NAY CŨNG thấy — bộ phận thôi cắt ngầm',
  ['Chi phí tháo dỡ', 'Chi phí setup'], loc('Marketing', DS2, ['Chi phí tháo dỡ']));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: một loại chi phí dùng chung được nhiều bộ phận.');
