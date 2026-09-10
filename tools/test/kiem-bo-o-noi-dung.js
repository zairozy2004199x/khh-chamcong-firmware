/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỎ Ô "NỘI DUNG HẠNG MỤC" — CỘT NỘI DUNG LẤY THEO LOẠI CHI PHÍ.
 *
 * Anh Thắng 10/09/2026 chỉ vào ô ấy: *"bỏ cái này"*, và chốt cột Nội dung lấy Loại chi phí.
 *
 * =============================================================================================
 * 🔴 CỘT NỘI DUNG VẪN PHẢI CÓ CHỮ. Nó là mô tả duy nhất của dòng chi: đi vào PDF gửi bộ phận,
 *    vào bản xuất MISA, và là thứ màn "Tìm đơn" dò để chỉ ra DÒNG CHI khớp. Bỏ ô nhập rồi ghi
 *    chuỗi rỗng xuống là cả ba chỗ ấy cùng ra dòng trắng — mà lúc đọc lại sổ thì không còn gì
 *    để đoán ra dòng ấy là khoản gì.
 *
 * 🔴 BỎ Ô THÌ PHẢI BỎ SẠCH MỌI CHỖ TRỎ VÀO NÓ. `el('da_nd')` còn sót ở đâu là chỗ ấy nhận
 *    null rồi nổ ReferenceError giữa chừng — sửa dòng, xoá form, đặt con trỏ đều đi qua đó.
 *
 * ⚠️ CHẠY THẬT `saveDuAnLineUI()` bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-bo-o-noi-dung.js
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

/* ── 1. Ô ĐÃ BỎ, VÀ BỎ SẠCH ────────────────────────────────────────────────────────────── */
t('🔴 ô nhập "Nội dung hạng mục" đã bỏ khỏi form dự án', HTML.indexOf('id="da_nd"') < 0);
t('   gợi ý lịch sử của nó cũng bỏ theo', HTML.indexOf('dl_da_nd') < 0);
t('🔴 KHÔNG còn chỗ nào trỏ vào el(\'da_nd\') (sót một chỗ là nổ giữa chừng)',
  HTML.indexOf("'da_nd'") < 0 && HTML.indexOf('"da_nd"') < 0);
t('   cột Nội dung của bảng dòng chi vẫn còn (mô tả đi vào PDF · MISA · tìm đơn)',
  HTML.indexOf('<thead><tr><th>Nội dung</th><th>Gian</th>') >= 0);

/* ── 2. CHẠY THẬT: NỘI DUNG LẤY THEO LOẠI CHI PHÍ ──────────────────────────────────────── */
function chay(loaiDaChon) {
  const NK = { gui: null, toast: [] };
  const O = () => ({ value: '', textContent: '', style: {}, focus() {}, scrollIntoView() {} });
  const KHO = {};
  const moi = {
    DA_CUR: { maDA: 'DA1', ten: 'Aeon' }, DA_EDIT_ROW: null,
    el: id => (KHO[id] = KHO[id] || O()),
    loading: () => {}, toast: (k, m) => NK.toast.push([k, m]),
    money: x => String(x), _log: () => {},
    _selLoai: () => ({ loai: loaiDaChon, tkNo: '6421' }),
    daCancelEditLine: () => {}, openDuAn: () => {}, loadDuAn: () => {},
    google: { script: { run: {
      withSuccessHandler() { return this; }, withFailureHandler() { return this; },
      addDuAnLine(ma, rec) { NK.gui = rec; },
      updateDuAnLine(ma, row, rec) { NK.gui = rec; },
    } } },
  };
  new Function('moi', `with(moi){ ${boc('saveDuAnLineUI')} saveDuAnLineUI(); }`)(moi);
  return NK;
}
{
  const NK = chay('Chi phí vận chuyển');
  t('🔴 nội dung dòng LẤY THEO loại chi phí đã chọn',
    NK.gui && NK.gui.noiDung === 'Chi phí vận chuyển', NK.gui);
  t('   và vẫn ghi kèm loại + mã tài khoản như cũ',
    NK.gui && NK.gui.loaiCp === 'Chi phí vận chuyển' && NK.gui.tkNo === '6421', NK.gui);
}
{
  const NK = chay('  Chi phí setup  ');
  t('   thừa khoảng trắng được cắt', NK.gui && NK.gui.noiDung === 'Chi phí setup', NK.gui);
}
/* 🔴 Chưa chọn loại thì KHÔNG được ghi dòng trắng xuống sổ. */
{
  const NK = chay('');
  t('🔴 chưa chọn loại chi phí → CHỐI, không ghi dòng nào', NK.gui === null, NK.gui);
  t('   và nói rõ phải làm gì', NK.toast.some(x => /Chọn loại chi phí/.test(x[1])), NK.toast);
}
{
  const NK = chay('   ');
  t('   loại toàn khoảng trắng cũng chối', NK.gui === null, NK.gui);
}

/* ── 3. CON TRỎ CHUYỂN SANG Ô LOẠI CHI PHÍ ─────────────────────────────────────────────── */
const keo = boc('_daKeoToi');
t('🔴 tạo dự án xong đặt con trỏ ở ô Loại chi phí (ô đầu tiên phải điền)',
  keo.indexOf("el('da_loaicp')") >= 0, keo);
t('   và vẫn chặn trình duyệt tự kéo theo ô', keo.indexOf('preventScroll:true') >= 0, keo);
const sua = boc('daEditLine');
t('   sửa dòng cũng nhảy về ô Loại chi phí', sua.indexOf("el('da_loaicp').focus()") >= 0, sua);
t('🔴 sửa dòng KHÔNG còn điền vào ô đã bỏ', sua.indexOf('da_nd') < 0, sua);
const xoa = boc('daClearLineForm');
t('   dọn form không đụng ô đã bỏ', xoa.indexOf('da_nd') < 0, xoa);
t('   nhưng vẫn dọn các ô còn lại', xoa.indexOf("'da_gian','da_dt'") >= 0, xoa);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: bỏ ô nhập nhưng cột Nội dung không bao giờ trắng.');
