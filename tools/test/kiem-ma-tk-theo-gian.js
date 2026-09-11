/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÃ TÀI KHOẢN ĐI THEO GIAN — ĐỪNG BẮT NHÂN VIÊN CHỌN
 *
 * Anh Thắng 11/09/2026, nhìn ô Loại chi phí của đơn cơ sở xổ ra bốn dòng cùng tên "Chi phí cơ
 * sở" khác mã (64196 · 64166 · 64126 · 64106):
 *   *"mã tk nó đi theo cơ sở mà, chọn này nhân viên đâu biết tài khoản nào."*
 *
 * =============================================================================================
 * 🔴 BỐN DÒNG ẤY LÀ BỐN MẢNG, KHÔNG PHẢI BỐN LỰA CHỌN. Cùng một loại chi phí, FARM một mã, FZ
 *    một mã, EVENT một mã — mã là hàm của (loại × mảng của gian), khai sẵn ở Cấu hình. Người
 *    nhập không có cách nào biết, mà cũng không cần biết.
 *
 * 🔴 TRÊN ĐƠN GHÉP NHIỀU GIAN, GIAN CHỌN SAU CÙNG. Thứ tự là loại -> nội dung -> gian, nên lúc
 *    mở ô chọn chưa có gì để tra. Bày sẵn bốn mã lúc ấy là bắt trả lời trước một câu chỉ trả
 *    lời được sau.
 *
 * 🔴 NHƯNG CHỌN GIAN RỒI MÀ CÒN HAI MÃ THÌ PHẢI HỎI — hai mã ấy cùng một mảng, máy không chọn
 *    hộ được. Bài kiểm canh cả hai chiều: gộp khi chưa có gian, TÁCH khi đã có.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-ma-tk-theo-gian.js
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
const HAM = ['_mangCua', '_mangPham', '_tkNoList', '_tapTkCo', '_tkNoCua', '_tenTk', '_loaiCpOpts', '_selLoai', '_tkHint'];
const MOI = HAM.map(function (x) { const c = bocHam(x); t('bốc được ' + x + '()', c.length > 20, c.length); return c; }).join('\n');

/* Danh mục thử theo đúng ảnh anh Thắng gửi: một loại "Chi phí cơ sở" khai bốn mã ở bốn mảng. */
const BOOT = {
  cosoPll: { 'funzone vũng tàu': 'FZ MN', 'farm nha trang': 'FARM MN', 'adv go! an lạc': 'EVENT FZ MN' },
  coso: ['FUNZONE VŨNG TÀU', 'FARM NHA TRANG', 'ADV GO! AN LẠC'],
  tkNoMx: {
    'chi phí cơ sở': { 'fz mn': ['64126'], 'farm mn': ['64166'], 'event fz mn': ['64196'] },
    /* Loại có HAI mã trong CÙNG một mảng — ca thật sự mơ hồ, phải hỏi. */
    'chi phí tháo dỡ': { 'fz mn': ['64125', '64105'] },
  },
  loaiChiPhi: [{ ten: 'Chi phí cơ sở', tkCo: '' }, { ten: 'Chi phí tháo dỡ', tkCo: '' }],
  phanloai: [], tenTk: {},
};
function moiTruong(nhieuGian) {
  return new Function('BOOT', 'TKNAME', 'el', 'esc', '_loaiCpList', '_donNhieuCoSo', '_bpTach',
    MOI + '\nreturn { opts:_loaiCpOpts, hint:_tkHint, list:_tkNoList };')(
    BOOT, {}, function (id) { return KHO[id]; }, function (x) { return String(x == null ? '' : x); },
    function () { return BOOT.loaiChiPhi; },
    function () { return nhieuGian; },
    function () { return []; });
}
const KHO = {};

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. 🔴 CHƯA CHỌN GIAN → MỘT DÒNG MỖI LOẠI, KHÔNG BẮT CHỌN MÃ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const F = moiTruong(true);
t('nền: chưa có gian thì tra ra cả bốn mã của mọi mảng', F.list('Chi phí cơ sở', '').length === 3, F.list('Chi phí cơ sở', ''));

const o1 = F.opts('', '', '', '');
const cs1 = o1.filter(x => x.ten === 'Chi phí cơ sở');
teq('🔴 "Chi phí cơ sở" gộp còn ĐÚNG MỘT dòng', 1, cs1.length);
teq('   và dòng ấy KHÔNG kèm mã',                '', cs1[0].tk);
t('🔴 nhãn nói rõ mã theo gian', cs1[0].nhan.indexOf('theo gian') >= 0, cs1[0].nhan);
t('🔴 nhãn KHÔNG bày mã nào ra',  !/6[45]\d{3}/.test(cs1[0].nhan), cs1[0].nhan);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 CHỌN GIAN RỒI → HẸP LẠI ĐÚNG MÃ CỦA GIAN ẤY
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const oFZ = F.opts('FUNZONE VŨNG TÀU', '', '', '').filter(x => x.ten === 'Chi phí cơ sở');
teq('chọn gian FZ → vẫn một dòng', 1, oFZ.length);
t('🔴 và nay có mã 64126 của mảng FZ', oFZ[0].nhan.indexOf('64126') >= 0, oFZ[0].nhan);
t('🔴 KHÔNG lẫn mã của mảng khác',
  oFZ[0].nhan.indexOf('64166') < 0 && oFZ[0].nhan.indexOf('64196') < 0, oFZ[0].nhan);

const oFarm = F.opts('FARM NHA TRANG', '', '', '').filter(x => x.ten === 'Chi phí cơ sở');
t('gian FARM → mã 64166', oFarm[0].nhan.indexOf('64166') >= 0, oFarm[0].nhan);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 CHIỀU NGƯỢC LẠI: ĐÃ CÓ GIAN MÀ VẪN HAI MÃ THÌ PHẢI HỎI
 *
 * ⚠️ Thiếu phép này thì một bản vá "gộp tất" vẫn xanh cả mục 1 lẫn mục 2, trong khi nó vừa
 *    lấy mất đường chỉ rõ mã cho ca thật sự mơ hồ — và máy chủ sẽ để trống mã.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const oHai = F.opts('FUNZONE VŨNG TÀU', '', '', '').filter(x => x.ten === 'Chi phí tháo dỡ');
teq('🔴 hai mã trong cùng một mảng → TÁCH thành hai dòng', 2, oHai.length);
teq('   dòng 1 mang mã riêng', '64125', oHai[0].tk);
teq('   dòng 2 mang mã riêng', '64105', oHai[1].tk);

/* Và khi chưa có gian thì loại ấy cũng gộp — người nhập chưa tới lượt trả lời. */
const oHaiTrong = F.opts('', '', '', '').filter(x => x.ten === 'Chi phí tháo dỡ');
teq('chưa có gian thì loại hai-mã cũng gộp lại', 1, oHaiTrong.length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 ĐƠN MỘT-GIAN (KVC) KHÔNG ĐƯỢC ĐỔI HÀNH VI
 *
 * Nó vốn phải chốt gian trước. Chưa có gian thì `_tkNoList` trả rỗng, nên chỗ này không có gì
 * để gộp — nhưng canh lại cho chắc: gộp nhầm cho nó là ô chọn bày loại của mảng khác.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const G = moiTruong(false);
teq('đơn một gian: chưa chọn gian thì không tra ra mã nào', [], G.list('Chi phí cơ sở', ''));
const gFZ = G.opts('FUNZONE VŨNG TÀU', '', '', '').filter(x => x.ten === 'Chi phí cơ sở');
t('   chọn gian rồi thì vẫn ra đúng mã của gian', gFZ[0].nhan.indexOf('64126') >= 0, gFZ[0].nhan);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. 🔴 DÒNG NHẮC MÃ: CHƯA CHỌN GIAN KHÔNG PHẢI LÀ LỖI
 *
 * "Nợ ?" màu đỏ lúc chưa tới lượt chọn gian là dọa người nhập, và đẩy họ đi tìm mã để chọn tay
 * — đúng việc vừa bỏ đi.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function hint(coso, tkDaChon) {
  KHO['sp'] = { textContent: '', style: {} };
  KHO['sel'] = { options: [{ getAttribute: function (k) {
    if (k === 'data-ten') return 'Chi phí cơ sở';
    if (k === 'data-tk') return tkDaChon || '';
    return '';
  } }], selectedIndex: 0, value: 'Chi phí cơ sở' };
  KHO['htt'] = { value: '' };
  F.hint('sp', 'sel', 'htt', coso);
  return KHO['sp'];
}
const h0 = hint('');
t('🔴 chưa chọn gian: nói mã theo gian', h0.textContent.indexOf('(theo gian)') >= 0, h0.textContent);
t('🔴 và KHÔNG tô đỏ',                   h0.style.color !== '#dc2626', h0.style.color);
t('   cũng không bày dấu hỏi',           h0.textContent.indexOf('Nợ ?') < 0, h0.textContent);
/* 🔴 CHƯA CÓ GIAN NHƯNG DÒNG ĐANG SỬA ĐÃ MANG MÃ — phải hiện chính mã ấy, đừng nuốt.
   Ca này có thật: bấm ✏️ sửa một dòng cũ ở đơn ghép gian thì ô chọn nạp lại mã đã lưu, mà ô
   Gian có thể chưa kịp đổ. Ghi "(theo gian)" đè lên là giấu mất mã thật của dòng, và người
   nhập tưởng dòng mình chưa có mã. */
const hCu = hint('', '64196');
t('🔴 chưa có gian nhưng dòng đã mang mã: hiện đúng mã ấy', hCu.textContent.indexOf('64196') >= 0, hCu.textContent);
t('   và KHÔNG ghi đè bằng "(theo gian)"', hCu.textContent.indexOf('(theo gian)') < 0, hCu.textContent);

const h1 = hint('FUNZONE VŨNG TÀU');
t('chọn gian rồi: hiện mã thật 64126', h1.textContent.indexOf('64126') >= 0, h1.textContent);
t('   và tô xanh (đã có mã)',          h1.style.color === '#0f766e', h1.style.color);

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — mã tài khoản tự đi theo gian, nhân viên khỏi đoán');
