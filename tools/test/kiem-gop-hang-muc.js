/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐƯA MỘT MỤC CON CŨ LÊN THÀNH HẠNG MỤC LỚN — ĐƯỜNG MỘT CHIỀU.
 *
 * Anh Thắng 17/09/2026: *"Đối với chi phí dự án, hạng mục con bỏ đi, vì các chi phí đều là hạng
 * mục lớn"*, và chốt tiếp: giữ nguyên mục con ĐANG CÓ, chỉ cấm tạo mới.
 *
 * =============================================================================================
 * TỆP NÀY TRƯỚC KIA THỬ ĐIỀU NGƯỢC LẠI — *"cái xe 3 tấn gõ nhầm, nó là con của xe vận chuyển,
 * muốn chuyển nó thành con của xe vận chuyển"* (10/09/2026). `daGopVaoMuc()` vốn làm HAI việc
 * ngược nhau: hạ một hạng mục lớn xuống làm con, VÀ đưa một mục con lên làm hạng mục lớn.
 * Việc thứ nhất là một đường TẠO mục con, nên nó đi. Việc thứ hai ở lại, và ở lại có lý do.
 *
 * 🔴 VÌ SAO KHÔNG GỠ LUÔN CẢ NÚT. Dự án cũ còn mục con, và đây là đường duy nhất dọn chúng lên
 *    mà không phải xoá dòng rồi nhập lại. Xoá rồi nhập lại là mất ảnh bill, mất hồ sơ đính kèm,
 *    mất loại chi phí và mã tài khoản đã gắn. Cấm tạo mới mà không chừa lối ra thì người ta sẽ
 *    đi xoá dòng — và mất chứng từ là thứ đắt hơn hẳn một cái nút thừa.
 *
 * 🔴 BẤM Ở HÀNG HẠNG MỤC LỚN THÌ PHẢI NÓI RA, ĐỪNG IM. Nút cũ nằm ở đó để hạ xuống; nay việc ấy
 *    không còn. Mở một hộp chọn rỗng, hay lặng lẽ không làm gì, đều bắt người dùng tự đoán.
 *
 * ⚠️ CHẠY THẬT `daGopVaoMuc()` và `daGopChot()` bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-gop-hang-muc.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

const LINES = [
  { row: 2, noiDung: 'Xe vận chuyển', capCha: '', duToan: 0, thucTe: 2000000, hinhThuc: '' },
  { row: 3, noiDung: 'Xe 2 tấn 5', capCha: 'Xe vận chuyển', soLuong: 1, donGia: 2000000, thucTe: 2000000 },
  /* Dòng gõ nhầm: có ĐỦ mọi ô, kể cả gian và hình thức chi — để phép "gộp không làm mất gì"
     soi được thật. Ô nào để trống ở đây là phép ấy thành xanh oan. */
  { row: 4, noiDung: 'Xe 3 tấn', capCha: '', duToan: 500000, soLuong: 1, donGia: 3000000,
    thucTe: 3000000, vat: 'Có VAT', hinhThuc: 'Trực tiếp', gian: 'FZ MN', anh: 'a.jpg',
    hoSo: 'hs.pdf', loaiCp: 'Chi phí tháo dỡ', tkNo: '64125', note: 'ghi chú' },
];
/* Bệ đỡ: DOM đủ cho hộp chọn inline. `daGopVaoMuc` vẽ hộp, `daGopChot` đọc lại và gửi đi —
   chạy cả hai để soi đúng đường người dùng đi. */
function chay(row, chon, lines) {
  const NK = { gui: null, toast: [], oGop: null, sel: null };
  const KHO = {};
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon', lines: lines || LINES },
    loading: () => {}, toast: (k, m) => NK.toast.push([k, m]), _log: () => {},
    esc: x => String(x == null ? '' : x),
    openDuAn: () => {}, loadDuAn: () => {},
    document: { querySelector: sel => {
      if (sel.indexOf('data-gop=') >= 0) return (NK.oGop = NK.oGop || { innerHTML: '' });
      if (sel.indexOf('data-gopsel=') >= 0) return NK.sel;
      return null;
    } },
    google: { script: { run: {
      withSuccessHandler(f) { this._ok = f; return this; },
      withFailureHandler() { return this; },
      updateDuAnLine(ma, r, rec) { NK.gui = { ma, row: r, rec }; this._ok({ success: true }); },
    } } },
  };
  const F = new Function('moi', `with(moi){ ${boc('daGopVaoMuc')}\n${boc('daGopChot')}
    return { mo: daGopVaoMuc, chot: daGopChot }; }`)(moi);
  F.mo(row);
  NK.hoi = NK.oGop ? NK.oGop.innerHTML : '';
  /* 🔴 CHỈ BẤM ✓ KHI HỘP CHỌN ĐÃ THẬT SỰ HIỆN RA. Nút ✓ nằm TRONG cái hộp mà `daGopVaoMuc`
     vẽ; `daGopVaoMuc` chối thì trên màn chẳng có nút nào để bấm. Gọi thẳng `daGopChot` bất kể
     là bệ đỡ đi một đường người dùng không đi được — và mọi chốt trong `daGopVaoMuc` sẽ xanh
     kể cả khi đã bị đục thủng. */
  if (chon !== undefined && chon !== null && /<select/.test(NK.hoi)) {
    NK.sel = { value: chon }; F.chot(row);
  }
  return NK;
}

/* ── 1. 🔴 BẤM Ở HÀNG HẠNG MỤC LỚN → CHỐI, VÀ NÓI RA VÌ SAO ────────────────────────────── */
{
  const NK = chay(4, 'Xe vận chuyển');
  t('🔴 không còn hạ một hạng mục lớn xuống làm mục con', NK.gui === null, NK.gui);
  t('   và nói thẳng vì sao, không im lặng',
    NK.toast.some(x => /chỉ có hạng mục lớn/.test(x[1])), NK.toast);
  t('   câu chối nói luôn nút này còn dùng để làm gì',
    NK.toast.some(x => /ĐƯA mục con cũ lên/.test(x[1])), NK.toast);
  t('   và KHÔNG mở hộp chọn (mở hộp rỗng là mời bấm rồi hỏng)',
    !/<select/.test(NK.hoi || ''), NK.hoi);
}
{
  /* Hạng mục lớn đang CÓ con cũng vậy — cùng một câu, không rẽ nhánh riêng. */
  const NK = chay(2, undefined, [LINES[0], LINES[1], LINES[2]]);
  t('hạng mục lớn đang có con: cùng một câu chối', NK.gui === null
    && NK.toast.some(x => /chỉ có hạng mục lớn/.test(x[1])), NK.toast);
}

/* ── 2. 🔴 MỤC CON CŨ VẪN ĐƯA LÊN ĐƯỢC ────────────────────────────────────────────────── */
const L3 = [
  { row: 2, noiDung: 'Xe vận chuyển', capCha: '', duToan: 9000000, thucTe: 0, hinhThuc: 'Trực tiếp' },
  { row: 3, noiDung: 'Xe 2 tấn 5', capCha: 'Xe vận chuyển', soLuong: 1, donGia: 2000000,
    thucTe: 2000000, hinhThuc: 'Trực tiếp', gian: 'FZ MN', loaiCp: 'Chi phí tháo dỡ', tkNo: '64125' },
  { row: 5, noiDung: 'Nhân công', capCha: '', duToan: 4000000, thucTe: 0, hinhThuc: '' },
];
{
  const NK = chay(3, '__LEN__', L3);
  t('🔴 ĐƯA MỤC CON LÊN thành hạng mục lớn → capCha rỗng',
    NK.gui && NK.gui.row === 3 && NK.gui.rec.capCha === '', NK.gui && NK.gui.rec);
  t('🔴 và GIỮ hình thức chi đang kế thừa (bỏ trống là khoản NCC nhảy sang bảng tạm ứng)',
    NK.gui && NK.gui.rec.hinhThuc === 'Trực tiếp', NK.gui && NK.gui.rec);
  t('   giữ nguyên tiền · loại chi phí · mã tài khoản · gian',
    NK.gui && NK.gui.rec.thucTe === 2000000 && NK.gui.rec.loaiCp === 'Chi phí tháo dỡ'
    && NK.gui.rec.tkNo === '64125' && NK.gui.rec.gian === 'FZ MN', NK.gui && NK.gui.rec);
  t('   báo đã đưa lên hạng mục lớn',
    NK.toast.some(x => x[0] === 'ok' && /hạng mục lớn/.test(x[1])), NK.toast);
}
{
  const NK = chay(3, undefined, L3);
  t('🔴 hộp chọn CHỈ có một đích: đưa lên hạng mục lớn', /__LEN__/.test(NK.hoi || ''), NK.hoi);
  t('🔴 và KHÔNG liệt kê hạng mục lớn nào để làm cha mới (hết đường tạo mục con)',
    !/Nhân công/.test(NK.hoi || '') && !/Xe vận chuyển/.test(NK.hoi || ''), NK.hoi);
  t('   vẫn có nút chốt và nút thôi',
    /daGopChot\(/.test(NK.hoi || '') && /✕/.test(NK.hoi || ''), NK.hoi);
}
{
  /* Đưa lên thì KHÔNG kiểm "cha còn tồn tại" — chẳng có cha nào để kiểm. Chốt nhầm ở đây là
     mục con mồ côi không bao giờ lên được, mà mồ côi thì nó đang không hiện trên bảng. */
  const L5 = [{ row: 3, noiDung: 'Xe 2 tấn 5', capCha: 'Xe vận chuyển', thucTe: 2000000 }];
  const NK = chay(3, '__LEN__', L5);
  t('🔴 đưa lên được cả khi hạng mục lớn cũ đã bị xoá',
    NK.gui && NK.gui.rec.capCha === '', NK.toast);
}
{
  const NK = chay(9, '__LEN__', L3);
  t('dòng không có thật → chối, không nổ', NK.gui === null, NK.toast);
}

/* ── 3. 🔴 MÃ NGUỒN: HAI ĐƯỜNG TẠO MỤC CON ĐÃ ĐI HẲN ──────────────────────────────────── */
t('🔴 nút "＋ con" đã gỡ khỏi bảng', HTML.indexOf('＋ con</button>') < 0);
t('🔴 hàm daAddChild() đã gỡ', HTML.indexOf('function daAddChild(') < 0);
t('🔴 nút "↳ vào mục" (hạ hạng mục lớn xuống) đã gỡ', HTML.indexOf('↳ vào mục</button>') < 0);
t('   và chỗ dựng nút phụ của hạng mục lớn nay rỗng', /var addChild='';/.test(HTML));

/* Ô "Thuộc" thôi bày mọi hạng mục lớn làm cha — nhưng PHẢI chừa đúng cha của dòng đang sửa,
   không thì mở một mục con cũ ra sửa là nó rơi lên cấp trên, im lặng, và đổi tổng tiền. */
t('🔴 ô "Thuộc" không còn liệt kê mọi hạng mục lớn làm cha',
  HTML.indexOf("parents.map(function(p){return '<option value=\"'+esc(p.noiDung)+'\">↳ mục con của:") < 0);
t('🔴 nhưng GIỮ option cha của dòng đang sửa (chống rơi cấp im lặng)',
  /_chaGiu/.test(HTML) && /\(dòng cũ\)/.test(HTML));
t('🔴 và daEditLine chêm lại option ấy TRƯỚC khi gán giá trị',
  HTML.indexOf('CHÊM LẠI OPTION CHA TRƯỚC KHI GÁN') >= 0);

/* ── 4. NÚT TRÊN HÀNG ──────────────────────────────────────────────────────────────────── */
t('🔴 nút dời vẫn nằm ở HÀNG MỤC CON', HTML.indexOf("var doiNut=canEdit?(' <span data-gop=\"'+k.row+'\">") >= 0);
t('   và gọi đúng dòng mục con', HTML.indexOf('daGopVaoMuc('+"'+k.row+'"+')') >= 0);
t('   hàng mục con nhận được nút phụ', HTML.indexOf('daLineCells(k,true, doiNut)') >= 0);
t('🔴 KHÔNG còn hỏi bằng prompt (gõ tay giữa danh sách dài là mời gõ nhầm)',
  HTML.indexOf("prompt('Gộp \"'") < 0);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hết đường tạo mục con, mục con cũ vẫn dọn lên được.');
