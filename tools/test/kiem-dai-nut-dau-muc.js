/**
 * DẢI NÚT "CHỌN CHI PHÍ NÀO" PHẢI GOM THEO ĐẦU MỤC
 * =============================================================================================
 *
 * 🔴 Anh Thắng 22/09/2026, nhìn màn nhập đơn: *"ở trong bảng nhập nó chỉ hiện loại chi phí chứ
 *    không phải đầu mục, đang ngược"*.
 *
 * Nguyên nhân đo được: dải nút ấy gom theo cột BỘ PHẬN. Cột Bộ phận GỠ khỏi bảng Cấu hình hôm
 * 21/09, nên dòng mới khai xong `boPhan` rỗng hết → mọi loại dồn về MỘT nhóm → `renderNhomCp()`
 * thấy `ds.length < 2` và TỰ ẨN cả dải. Không ai gỡ dải nút; nó chết theo cột đã gỡ, im lặng.
 *
 * 🔴 BÀI NÀY CANH ĐÚNG CHỖ CHẾT ẤY. Phép đầu tiên dựng lại nguyên trạng anh gặp — danh mục
 *    KHÔNG dòng nào có bộ phận — và đòi dải nút phải có nhiều hơn một nút. Bản trước bài này
 *    ĐỎ ngay ở đấy.
 *
 * Chạy: node tools/test/kiem-dai-nut-dau-muc.js
 */
const fs = require('fs'), path = require('path');
const GOC = path.resolve(__dirname, '..', '..');
const APP = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + JSON.stringify(them)));
}
const teq = (ten, mong, thuc) =>
  t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc);

/** Bốc nguyên văn một hàm ra khỏi app.html — cân ngoặc, không dựa vào thụt lề. */
function layHam(ten) {
  const i = APP.indexOf('function ' + ten + '(');
  if (i < 0) throw new Error('không thấy ' + ten + '()');
  let j = APP.indexOf('{', i), sau = 0;
  for (let k = j; k < APP.length; k++) {
    if (APP[k] === '{') sau++;
    else if (APP[k] === '}') { sau--; if (!sau) return APP.slice(i, k + 1); }
  }
  throw new Error('ngoặc lệch ở ' + ten + '()');
}

const HAM = ['_khoiCuaLoai', '_vaiDungDuocLoai', '_bpTach', '_khoaNhom',
             '_dauMucCua', '_dauMucDangDung', '_cacNhomCp', '_nhanNhomCp', '_optsHtml'];
const NGUON = HAM.map(layHam).join('\n')
  + '\n  return { nhom:_cacNhomCp, nhan:_nhanNhomCp, theoDm:_dauMucDangDung, opts:_optsHtml,'
  + '           dm:_dauMucCua };';

function moi(loaiChiPhi, dauMucDs) {
  const BOOT = { loaiChiPhi: loaiChiPhi, dauMucDs: dauMucDs || [], locLoaiTheoVai: false };
  return new Function('BOOT', 'NHOM_CP_CS', 'CURUSER', 'KHOI_DANG', 'esc', '_bpCuaToi', NGUON)(
    BOOT, 'Chi phí cơ sở', { role: 'Nhân viên' }, 'kvc',
    v => String(v == null ? '' : v), () => '');
}
const L = (ten, dauMuc, boPhan) => ({ ten, dauMuc: dauMuc || '', boPhan: boPhan || '', khoi: 'kvc', vaiTro: '' });

const DS_DM = ['Chung · Văn phòng', 'Cơ sở · Marketing mua', 'Cơ sở · Cơ sở tự mua',
               'Cơ sở · Nguyên vật liệu', 'Tiền thuê · Mall', 'Khác'];

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 NGUYÊN TRẠNG ANH THẮNG GẶP — danh mục không dòng nào có bộ phận
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const nhuAnh = [
  L('Chi phí cơ sở',              'Cơ sở · Cơ sở tự mua'),
  L('Chi phí NVL đồ ăn - Mua lẻ', 'Cơ sở · Cơ sở tự mua|Cơ sở · Nguyên vật liệu'),
  L('Chi phí NVL đồ uống - Mua lẻ', 'Cơ sở · Nguyên vật liệu'),
  L('Chi phí nuôi thú',           ''),
];
let M = moi(nhuAnh, DS_DM);
t('🔴 danh mục đã khai đầu mục -> chuyển sang gom theo đầu mục', M.theoDm() === true);
const n1 = M.nhom();
t('🔴 DẢI NÚT KHÔNG CÒN TRỐNG — nhiều hơn một nút thì `renderNhomCp()` mới chịu bày ra',
  n1.length > 1, n1);
teq('   và bày đúng đầu mục, xếp theo thứ tự máy chủ, ô hứng chót bảng',
  ['Cơ sở · Cơ sở tự mua', 'Cơ sở · Nguyên vật liệu', 'Chưa xếp đầu mục'], n1);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. MỘT LOẠI THUỘC NHIỀU ĐẦU MỤC -> DỰNG NHIỀU NÚT
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
M = moi([L('Chi phí NVL đồ ăn', 'Cơ sở · Cơ sở tự mua|Cơ sở · Nguyên vật liệu')], DS_DM);
teq('🔴 một loại hai đầu mục -> hiện dưới CẢ HAI nút',
  ['Cơ sở · Cơ sở tự mua', 'Cơ sở · Nguyên vật liệu'], M.nhom());

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. CHƯA KHAI ĐẦU MỤC -> GIỮ NGUYÊN LỐI CŨ, KHÔNG AI MẤT GÌ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
M = moi([L('Chi phí tháo dỡ', '', 'Kỹ thuật'), L('Chi phí cơ sở', '', 'Cơ sở')], DS_DM);
t('🔴 chưa dòng nào khai đầu mục -> KHÔNG chuyển, vẫn gom theo bộ phận', M.theoDm() === false);
teq('   và dải nút vẫn y như trước bản này', ['Chi phí cơ sở', 'Kỹ thuật'], M.nhom());
teq('   nhãn cũ cũng giữ nguyên', '🏢 Chi phí cơ sở', M.nhan('Chi phí cơ sở'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. NHÃN NÚT LÀ TÊN ĐẦU MỤC, KHÔNG NẮN
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
M = moi(nhuAnh, DS_DM);
teq('🔴 nhãn nút chính là tên đầu mục — máy không tự gắn biểu tượng vào tên người ta khai',
  'Cơ sở · Nguyên vật liệu', M.nhan('Cơ sở · Nguyên vật liệu'));
/* ⚠️ ĐÒI ĐÚNG DẤU RIÊNG, không chỉ đòi "có chứa chữ ấy". Gỡ hẳn nhánh đầu mục khỏi
   `_nhanNhomCp()` thì nó trả về nguyên `k`, tức vẫn chứa chữ "Chưa xếp đầu mục" — phép cũ
   xanh trơn trong khi nhãn đã rơi về bảng cũ. Đúng lỗi "xanh vì lý do sai" lần thứ tư. */
teq('   ô hứng có dấu riêng để nhìn ra ngay giữa dải nút', '• Chưa xếp đầu mục',
  M.nhan('Chưa xếp đầu mục'));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. ĐẦU MỤC LẠ (kế toán đổi tên) VẪN CÓ NÚT, CHỈ XUỐNG SAU
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
M = moi([L('A', 'Cơ sở · Nguyên vật liệu'), L('B', 'Một tên cũ đã đổi')], DS_DM);
teq('🔴 đầu mục không còn trong danh sách VẪN có nút — mất nút là mất luôn lối vào những loại ấy',
  ['Cơ sở · Nguyên vật liệu', 'Một tên cũ đã đổi'], M.nhom());

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. 🔴 Ô CHỌN LOẠI CHI PHÍ — MỘT LOẠI HAI ĐẦU MỤC PHẢI HIỆN Ở CẢ HAI NHÓM
 * ══════════════════════════════════════════════════════════════════════════════════════════════
 * Mấy khối trên mới canh DẢI NÚT. Ô chọn bên dưới nó là chuyện khác, và là chỗ ba lượt đục
 * từng sống sót: gom loại vào đầu mục ĐẦU TIÊN thôi, và đánh dấu `selected` ở cả hai lần hiện.
 * Grep mã nguồn không bắt được hai ca ấy — phải dựng HTML ra mà đếm.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
M = moi(nhuAnh, DS_DM);
const DS2 = [
  { ten: 'Chi phí NVL đồ ăn', tk: '', nhan: 'Chi phí NVL đồ ăn',
    dm: M.dm({ dauMuc: 'Cơ sở · Cơ sở tự mua|Cơ sở · Nguyên vật liệu' }) },
  { ten: 'Chi phí nuôi thú', tk: '', nhan: 'Chi phí nuôi thú', dm: M.dm({ dauMuc: '' }) },
];
const h2 = M.opts(DS2, '', '', '— Chọn loại chi phí —');
const nhomCua = (h) => (h.match(/<optgroup label="([^"]*)"/g) || []).map(x => x.slice(17, -1));

teq('🔴 loại hai đầu mục hiện ở CẢ HAI nhóm, cộng nhóm của loại chưa xếp',
  ['Cơ sở · Cơ sở tự mua', 'Cơ sở · Nguyên vật liệu', 'Chưa xếp đầu mục'], nhomCua(h2));
teq('🔴 và nó ra ĐÚNG HAI dòng — một cho mỗi nhóm, không phải một dòng rồi thôi',
  2, (h2.match(/data-ten="Chi phí NVL đồ ăn"/g) || []).length);
/* Hai dòng ấy phải mang CÙNG `data-ten`/`data-tk` thì chọn dòng nào cũng ra một kết quả —
   `_selLoai()` đọc bằng đúng hai thuộc tính này. */
teq('   hai dòng cùng một mã tài khoản, nên chọn dòng nào cũng như nhau',
  2, (h2.match(/data-ten="Chi phí NVL đồ ăn" data-tk=""/g) || []).length);

/* 🔴 `selected` CHỈ Ở LẦN HIỆN ĐẦU. Đánh dấu cả hai thì trình duyệt nhảy xuống cái CUỐI — ô
   chọn mở ra ở nhóm dưới trong khi người ta vừa chọn ở nhóm trên, nhìn như máy tự đổi ý. */
const h3 = M.opts(DS2, 'Chi phí NVL đồ ăn', '', '—');
teq('🔴 loại đang chọn chỉ được đánh dấu MỘT lần, dù hiện hai lần',
  1, (h3.match(/selected/g) || []).length);
t('   và dấu ấy nằm ở lần hiện ĐẦU (nhóm trên), không phải nhóm dưới',
  h3.indexOf('selected') < h3.indexOf('Cơ sở · Nguyên vật liệu'), h3.slice(0, 300));

/* Một đầu mục thì mọi thứ y như trước bản này — không ai mất gì. */
const h4 = M.opts([{ ten: 'A', tk: '', nhan: 'A', dm: M.dm({ dauMuc: 'Khác' }) }], '', '', '—');
teq('🔴 một nhóm duy nhất thì KHÔNG bọc optgroup, y như trước', [], nhomCua(h4));

/* ── kết ────────────────────────────────────────────────────────────────────────────────────── */
if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — dải nút nhập đơn gom theo đầu mục, và chưa khai thì không đổi gì.');
