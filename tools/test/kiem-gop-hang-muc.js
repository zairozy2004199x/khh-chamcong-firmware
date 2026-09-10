/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * GỘP MỘT HẠNG MỤC LỚN THÀNH MỤC CON CỦA HẠNG MỤC KHÁC.
 *
 * Anh Thắng 10/09/2026: *"cái xe 3 tấn gõ nhầm, nó là con của xe vận chuyển, muốn chuyển nó
 * thành con của xe vận chuyển"*.
 *
 * =============================================================================================
 * Vốn vẫn làm được — bấm ✏️ rồi đổi ô "Thuộc" — nhưng phải NHỚ là ô ấy có, mà nhìn hàng thì
 * không có gì gợi ra. Nay có nút ngay cạnh "＋ con".
 *
 * 🔴 CHỌN TỪ DANH SÁCH, KHÔNG GÕ TAY. Anh Thắng: *"hiện ô tích cho dễ hơn không"* — bản trước
 *    hỏi bằng `prompt()`, người ta phải gõ lại đúng tên cha giữa một danh sách dài. Gõ sai một
 *    chữ là bị chối (may), gõ trúng tên hạng mục khác là dòng chui vào nhầm chỗ (tệ).
 *
 * 🔴 CHỈ GỘP ĐƯỢC HẠNG MỤC CHƯA CÓ CON. Gộp một hạng mục đang có con vào hạng mục khác là lồng
 *    ba cấp, mà bảng chỉ vẽ hai — mấy dòng con sẽ BIẾN KHỎI MÀN tuy vẫn nằm trong sổ và vẫn
 *    cộng vào tổng. Tiền có thật mà không ai nhìn thấy là kiểu hỏng tệ nhất ở đây.
 *
 * 🔴 GÕ SAI TÊN CHA CŨNG THẾ. Dòng thành mục con của một hạng mục KHÔNG TỒN TẠI; bảng chỉ vẽ
 *    mục con nằm dưới cha của nó, nên dòng ấy biến khỏi màn mà tiền vẫn trong sổ.
 *
 * ⚠️ CHẠY THẬT `daGopVaoMuc()` bốc từ mã nguồn.
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
  if (chon !== undefined && chon !== null) { NK.sel = { value: chon }; F.chot(row); }
  return NK;
}

/* ── 1. GỘP ĐÚNG ───────────────────────────────────────────────────────────────────────── */
{
  const NK = chay(4, 'Xe vận chuyển');
  t('🔴 gộp "Xe 3 tấn" vào "Xe vận chuyển" → gửi đúng dòng, đúng cha',
    NK.gui && NK.gui.row === 4 && NK.gui.rec.capCha === 'Xe vận chuyển', NK.gui);
  t('   giữ nguyên nội dung · số lượng · đơn giá · thực tế',
    NK.gui && NK.gui.rec.noiDung === 'Xe 3 tấn' && NK.gui.rec.soLuong === 1
    && NK.gui.rec.donGia === 3000000 && NK.gui.rec.thucTe === 3000000, NK.gui && NK.gui.rec);
  t('   và giữ gian · ảnh · hồ sơ',
    NK.gui && NK.gui.rec.gian === 'FZ MN' && NK.gui.rec.anh === 'a.jpg'
    && NK.gui.rec.hoSo === 'hs.pdf', NK.gui && NK.gui.rec);
  t('   và giữ cả loại chi phí · mã tài khoản · VAT · ghi chú',
    NK.gui && NK.gui.rec.loaiCp === 'Chi phí tháo dỡ' && NK.gui.rec.tkNo === '64125'
    && NK.gui.rec.vat === 'Có VAT' && NK.gui.rec.note === 'ghi chú', NK.gui && NK.gui.rec);
  /* Mục con không giữ dự toán và hình thức chi riêng — hai thứ ấy theo hạng mục lớn. Để lại là
     bảng cộng dự toán hai lần: một ở cha, một ở dòng con vừa gộp. */
  t('🔴 bỏ dự toán và hình thức chi (mục con theo hạng mục lớn, để lại là cộng hai lần)',
    NK.gui && NK.gui.rec.duToan === '' && NK.gui.rec.hinhThuc === '', NK.gui && NK.gui.rec);
  t('🔴 hộp CHỌN liệt kê hạng mục lớn (không bắt gõ tay)',
    /<select/.test(NK.hoi || '') && /Xe vận chuyển/.test(NK.hoi || ''), NK.hoi);
  t('   có nút chốt và nút thôi', /daGopChot\(/.test(NK.hoi || '') && /✕/.test(NK.hoi || ''), NK.hoi);
  t('   báo đã gộp xong', NK.toast.some(x => x[0] === 'ok' && /Đã gộp/.test(x[1])), NK.toast);
}
{
  /* 🔴 Giữa lúc chọn và lúc bấm ✓, hạng mục kia có thể vừa bị người khác xoá. */
  const NK = chay(4, 'Hạng mục vừa bị xoá');
  t('🔴 hạng mục đã biến mất giữa chừng → CHỐI (không thì dòng chui vào chỗ không tồn tại)',
    NK.gui === null, NK.gui);
  t('   và nói rõ phải tải lại', NK.toast.some(x => /không còn/.test(x[1])), NK.toast);
}

/* ── 2. 🔴 CHỐI NHỮNG CA LÀM MẤT DÒNG KHỎI MÀN ─────────────────────────────────────────── */
{
  const NK = chay(4, 'Xe bồn');
  t('🔴 gõ tên cha không có → CHỐI (không thì dòng biến khỏi màn mà tiền vẫn trong sổ)',
    NK.gui === null, NK.gui);
  t('   và nói rõ hạng mục nào không còn', NK.toast.some(x => /không còn/.test(x[1])), NK.toast);
}
/* Bỏ trống hay bấm Cancel là "thôi, không gộp nữa" — phải IM LẶNG. Rơi xuống chốt tên cha thì
   người ta nhận một câu lỗi đỏ cho việc mình vừa cố ý huỷ. */
{
  const NK = chay(4, '');
  t('bỏ trống → không làm gì', NK.gui === null);
  t('   và im lặng, không ném câu lỗi cho việc vừa cố ý huỷ',
    !NK.toast.some(x => x[0] === 'err'), NK.toast);
}
{
  const NK = chay(4, null);
  t('bấm Cancel → không làm gì', NK.gui === null);
  t('   và cũng im lặng', !NK.toast.some(x => x[0] === 'err'), NK.toast);
}
/* 🔴 Chỉ gộp vào HẠNG MỤC LỚN. Gộp vào một mục con là lồng ba cấp — bảng chỉ vẽ hai. */
{
  const NK = chay(4, 'Xe 2 tấn 5');
  t('🔴 KHÔNG cho gộp vào một MỤC CON (lồng ba cấp thì bảng không vẽ nổi)',
    NK.gui === null, NK.gui);
  t('   danh sách chọn cũng không liệt kê mục con', !/Xe 2 tấn 5/.test(NK.hoi || ''), NK.hoi);
}
{
  const NK = chay(9, 'Xe vận chuyển');
  t('dòng không có thật → chối, không nổ', NK.gui === null, NK.toast);
}
{
  const NK = chay(2, 'Xe 3 tấn', [LINES[0], LINES[1]]);
  t('🔴 KHÔNG cho gộp vào CHÍNH NÓ (danh sách cha đã loại dòng đang gộp)',
    NK.gui === null, NK.gui);
}
{
  const NK = chay(4, 'x', [LINES[2]]);
  t('chỉ có một hạng mục lớn → báo chưa có chỗ để gộp vào',
    NK.gui === null && NK.toast.some(x => /Chưa có hạng mục lớn nào khác/.test(x[1])), NK.toast);
}

/* ── 3. NÚT TRÊN HÀNG ──────────────────────────────────────────────────────────────────── */
t('🔴 có nút gộp ngay trên hàng hạng mục lớn', HTML.indexOf('daGopVaoMuc(') >= 0);
t('🔴 nút CHỈ hiện khi hạng mục ấy CHƯA CÓ CON (gộp cái đang có con là lồng ba cấp)',
  HTML.indexOf("!(childrenBy[p.noiDung]||[]).length") >= 0);
t('   và chỉ hiện khi có hạng mục lớn khác để gộp vào',
  HTML.indexOf('parents.length>1') >= 0);
t('   chỉ người sửa được đơn mới thấy nút', HTML.indexOf('var goNut=(canEdit &&') >= 0);
t('   rê chuột vào nói rõ nút làm gì', HTML.indexOf('dùng khi lỡ gõ nó thành mục lớn') >= 0);
t('🔴 hàng có chỗ để đặt hộp chọn (data-gop)', HTML.indexOf("' <span data-gop=\"'+p.row+'\">") >= 0);
t('🔴 KHÔNG còn hỏi bằng prompt (gõ tay giữa danh sách dài là mời gõ nhầm)',
  HTML.indexOf("prompt('Gộp \"'") < 0);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: gộp được hạng mục gõ nhầm, và không dòng nào biến khỏi màn.');
