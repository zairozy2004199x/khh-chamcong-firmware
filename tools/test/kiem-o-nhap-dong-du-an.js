/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * FORM DÒNG CHI CỦA DỰ ÁN: Ô NỘI DUNG · Ô GIAN.
 *
 * Anh Thắng 10/09/2026: *"bộ phận gắn với nhân viên mà, nên ẩn đi"* rồi *"Thay vào chỗ là Nội
 * dung đơn"* — ô nhãn "Bộ phận / Gian" nhường chỗ cho ô Nội dung.
 *
 * =============================================================================================
 * 🔴 Ô ẤY KHÔNG PHẢI "BỘ PHẬN" MÀ LÀ "GIAN", và nó có việc thật: mã tài khoản khai theo MẢNG
 *    của gian, nên bỏ hẳn là mọi dòng chi mất mã. Nhãn cũ gọi nó là bộ phận nên đọc vào tưởng
 *    thừa — đổi nhãn, và chỉ hỏi khi thật sự cần.
 *
 * 🔴 DỰ ÁN SETUP / THÁO DỠ ĐÃ MANG SẴN TÊN GIAN — chính là tên dự án. Bắt gõ lại từng dòng là
 *    mời gõ lệch: "Aeon Bình Tân" ở dòng này, "AEON BÌNH TÂN" ở dòng kia, hai dòng rơi vào hai
 *    mảng khác nhau nên ra hai mã tài khoản khác nhau trong cùng một dự án.
 *    TRỪ "Chi phí cơ sở (chung)": dự án ấy gom chi phí của NHIỀU cơ sở nên vẫn phải hỏi.
 *
 * 🔴 CỘT NỘI DUNG VẪN PHẢI CÓ CHỮ. Nó là mô tả duy nhất của dòng chi: đi vào PDF gửi bộ phận,
 *    vào bản xuất MISA, và là thứ màn "Tìm đơn" dò để chỉ ra dòng khớp.
 *
 * ⚠️ CHẠY THẬT `saveDuAnLineUI()` và `_daCoso()` bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-o-nhap-dong-du-an.js
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

/* ── 1. BỐ CỤC ─────────────────────────────────────────────────────────────────────────── */
t('🔴 có ô Nội dung ở form dòng dự án', HTML.indexOf('id="da_nd"') >= 0);
t('   kèm gợi ý theo lịch sử đã nhập', HTML.indexOf('id="dl_da_nd"') >= 0);
t('🔴 ô Gian KHÔNG còn mang nhãn "Bộ phận" (bộ phận đã gắn với nhân viên)',
  HTML.indexOf('Bộ phận / Gian') < 0);
t('   và nó nằm trong hộp ẩn/hiện được', HTML.indexOf('id="daGianBox"') >= 0);
t('🔴 mặc định hộp Gian ĐÓNG — chỉ mở ở dự án gom nhiều cơ sở',
  HTML.indexOf('id="daGianBox" style="display:none"') >= 0);
t('   và mở đúng theo dự án "Chi phí cơ sở (chung)"',
  HTML.indexOf("el('daGianBox').style.display=isCoSo?'':'none'") >= 0);
t('   cột Nội dung của bảng dòng chi vẫn còn',
  HTML.indexOf('<thead><tr><th>Nội dung</th><th>Gian</th>') >= 0);

/* ── 2. GIAN LẤY TỪ ĐÂU — CHẠY THẬT ────────────────────────────────────────────────────── */
function gian(hienBox, oGian, duAn) {
  const KHO = { da_gian: { value: oGian }, daGianBox: { style: { display: hienBox ? '' : 'none' } } };
  const moi = { DA_CUR: duAn, el: id => KHO[id] || null };
  return new Function('moi', `with(moi){ ${boc('_daCoso')} return _daCoso(); }`)(moi);
}
const DA_SETUP = { ten: 'Aeon Bình Tân', loai: 'Setup lắp đặt' };
t('🔴 dự án Setup: gian lấy từ TÊN DỰ ÁN, không bắt gõ lại',
  gian(false, '', DA_SETUP) === 'Aeon Bình Tân', gian(false, '', DA_SETUP));
t('   ô Gian có giá trị cũ cũng không lấn át (hộp đang đóng)',
  gian(false, 'GÕ LỆCH', DA_SETUP) === 'Aeon Bình Tân', gian(false, 'GÕ LỆCH', DA_SETUP));
t('🔴 dự án "Chi phí cơ sở (chung)": hộp mở → lấy đúng ô Gian của DÒNG',
  gian(true, 'FARM NHA TRANG', { ten: 'Chi phí Kỹ thuật cơ sở (chung)', loai: 'Chi phí cơ sở' }) === 'FARM NHA TRANG');
t('   ô Gian bỏ trống thì trả rỗng, không mượn tên dự án chung',
  gian(true, '', { ten: 'Chi phí Kỹ thuật cơ sở (chung)', loai: 'Chi phí cơ sở' }) === '');
t('   trường coso của dự án được ưu tiên nếu có',
  gian(false, '', { coso: 'FARM MN', ten: 'Tên khác' }) === 'FARM MN');
t('chưa mở dự án nào → rỗng, không nổ', gian(false, '', null) === '');

/* ── 3. NỘI DUNG DÒNG — CHẠY THẬT ──────────────────────────────────────────────────────── */
function luu(oNd, loaiDaChon) {
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
  moi.el('da_nd').value = oNd;
  new Function('moi', `with(moi){ ${boc('saveDuAnLineUI')} saveDuAnLineUI(); }`)(moi);
  return NK;
}
{
  const NK = luu('Thuê xe cẩu', 'Chi phí tháo dỡ');
  t('🔴 gõ nội dung → dòng mang đúng nội dung ấy', NK.gui && NK.gui.noiDung === 'Thuê xe cẩu', NK.gui);
  t('   và vẫn ghi kèm loại chi phí + mã tài khoản',
    NK.gui && NK.gui.loaiCp === 'Chi phí tháo dỡ' && NK.gui.tkNo === '6421', NK.gui);
}
{
  const NK = luu('   ', 'Chi phí tháo dỡ');
  t('🔴 bỏ trống nội dung → ngã về TÊN LOẠI CHI PHÍ, không để dòng trắng',
    NK.gui && NK.gui.noiDung === 'Chi phí tháo dỡ', NK.gui);
}
{
  const NK = luu('  Thuê xe cẩu  ', 'Chi phí tháo dỡ');
  t('   thừa khoảng trắng được cắt', NK.gui && NK.gui.noiDung === 'Thuê xe cẩu', NK.gui);
}
{
  const NK = luu('', '');
  t('🔴 không nội dung mà cũng chưa chọn loại → CHỐI, không ghi dòng trắng', NK.gui === null, NK.gui);
  t('   và nói rõ phải làm gì', NK.toast.some(x => /Nhập nội dung hoặc chọn loại/.test(x[1])), NK.toast);
}
{
  const NK = luu('Thuê xe cẩu', '');
  t('   chưa chọn loại nhưng có nội dung → vẫn ghi được',
    NK.gui && NK.gui.noiDung === 'Thuê xe cẩu', NK.gui);
}

/* ── 4. CON TRỎ VÀ SỬA DÒNG ────────────────────────────────────────────────────────────── */
const keo = boc('_daKeoToi');
t('🔴 tạo dự án xong đặt con trỏ ở ô Nội dung (ô đầu tiên phải điền)',
  keo.indexOf("el('da_nd')") >= 0, keo);
t('   và vẫn chặn trình duyệt tự kéo theo ô', keo.indexOf('preventScroll:true') >= 0, keo);
const sua = boc('daEditLine');
t('🔴 sửa dòng điền lại ĐÚNG nội dung cũ (không thì lưu một cái là mất mô tả)',
  sua.indexOf("el('da_nd').value=l.noiDung||''") >= 0, sua);
t('   và điền lại cả gian cũ', sua.indexOf("el('da_gian').value=l.gian||''") >= 0, sua);
const xoa = boc('daClearLineForm');
t('   dọn form dọn cả ô Nội dung', xoa.indexOf("'da_nd'") >= 0, xoa);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô Nội dung trở lại, gian tự lấy theo dự án, không dòng nào trắng.');
