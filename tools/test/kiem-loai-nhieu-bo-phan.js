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
function loc(boPhanNguoiDung, dsLoai) {
  const moi = {
    CURUSER: { boPhan: boPhanNguoiDung },
    NHOM_CP: '', NHOM_CP_CS: 'Cơ sở',
    BOOT: { loaiChiPhi: dsLoai, tkNoMx: {} },
    _mangCua: () => '',
    _tkNoCua: () => '6421',          // coi như mọi loại đã khai mã
    _tkNoList: () => [],
    _donNhieuCoSo: () => false,
    _mangPham: () => [],
    el: () => null,
  };
  const F = new Function('moi', `with(moi){ ${boc('_bpTach')}\n${boc('_khoaNhom')}\n${boc('_loaiCpList')}
    return _loaiCpList; }`)(moi);
  return F('', '').map(x => x.ten);
}
const DS = [
  { ten: 'Chi phí setup',     boPhan: 'Kỹ thuật, Setup' },   // dùng chung hai bộ phận
  { ten: 'Chi phí tháo dỡ',   boPhan: 'Kỹ thuật' },          // riêng Kỹ thuật (dữ liệu CŨ)
  { ten: 'Chi phí quảng cáo', boPhan: 'Marketing' },
  { ten: 'Chi phí điện nước', boPhan: '' },                  // chưa khai -> dùng chung
];
teq('🔴 người Kỹ thuật thấy loại khai "Kỹ thuật, Setup"',
  ['Chi phí setup', 'Chi phí tháo dỡ', 'Chi phí điện nước'], loc('Kỹ thuật', DS));
teq('🔴 người Setup CŨNG thấy loại ấy — đây là chỗ bản cũ làm ô trống trơn',
  ['Chi phí setup', 'Chi phí điện nước'], loc('Setup', DS));
teq('   người Marketing thấy phần của mình', ['Chi phí quảng cáo', 'Chi phí điện nước'], loc('Marketing', DS));
teq('🔴 loại CHƯA khai bộ phận hiện cho mọi người (sổ cũ còn nhiều dòng như thế)',
  ['Chi phí điện nước'], loc('Công tác', DS));
teq('🔴 người không bị bó bộ phận → thấy hết',
  ['Chi phí setup', 'Chi phí tháo dỡ', 'Chi phí quảng cáo', 'Chi phí điện nước'], loc('', DS));

/* 🔴 Ca đúng như ảnh anh Thắng gửi: mọi loại đều khai bộ phận khác -> ô trống trơn, và từ bản
   bỏ ô "Nội dung hạng mục" thì trống nghĩa là không nhập được dòng nào. */
teq('người Kỹ thuật mà danh mục toàn loại của bộ phận khác → ô trống (đúng, nhưng là dấu hiệu khai thiếu)',
  [], loc('Kỹ thuật', [{ ten: 'Chi phí quảng cáo', boPhan: 'Marketing' }]));
teq('🔴 khai thêm Kỹ thuật vào chính loại ấy là ô có ngay, không phải đẻ loại trùng tên',
  ['Chi phí quảng cáo'], loc('Kỹ thuật', [{ ten: 'Chi phí quảng cáo', boPhan: 'Marketing, Kỹ thuật' }]));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: một loại chi phí dùng chung được nhiều bộ phận.');
