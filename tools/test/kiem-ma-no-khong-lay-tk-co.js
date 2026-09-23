/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÃ CỦA LOẠI CHI PHÍ PHẢI LÀ TK NỢ, KHÔNG PHẢI TK CÓ.
 *
 * Anh Thắng 10/09/2026: *"loại chi phí lấy lộn vị trí rồi"*, *"loại chi phí nó là tài khoản nợ
 * chứ"*. Ô chọn đang hiện **"Chi phí tháo dỡ · TK 331"** — mà 331 là mã PHẢI TRẢ NHÀ CUNG CẤP,
 * tức TK CÓ. Bảng 🧮 ghi rõ loại ấy là **64125** ở mọi mảng.
 *
 * =============================================================================================
 * 🔴 GỐC: `_tkNoCua()` khi ma trận chưa có mã cho mảng thì NGÃ VỀ cột `tkNo` cũ của danh mục —
 *    tàn dư từ thời chưa có ma trận theo mảng. Trong sổ thật có dòng lỡ điền TK Có vào đó.
 *
 * 🔴 HỎNG LẶNG LẼ: dòng chi ghi Nợ 331 / Có 331. Bản xuất MISA nhận một bút toán vô nghĩa, mà
 *    nhìn màn thì thấy "đã có mã" nên không ai ngờ. Thà nói CHƯA GẮN MÃ còn hơn — người khai
 *    thấy chữ ấy là biết phải vào Cấu hình khai, còn thấy một con số thì tin luôn.
 *
 * ⚠️ TẬP TK CÓ LẤY TỪ DỮ LIỆU, KHÔNG GÕ CỨNG MỘT KHOẢNG SỐ. Đoán "6xxx là Nợ" thì sai ngay khi
 *    doanh nghiệp dùng hệ thống tài khoản khác.
 *
 * ⚠️ CHẠY THẬT `_tkNoCua()` bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-ma-no-khong-lay-tk-co.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }
function teq(ten, mong, thuc) { t(ten + ' (mong ' + JSON.stringify(mong) + ')', mong === thuc, thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

function tra(loai, coso, BOOT, CFG) {
  const moi = {
    BOOT: BOOT, CFG: CFG || {},
    _tkNoList: (l, c) => (((BOOT.tkNoMx || {})[String(l).toLowerCase()] || {})[String(c).toLowerCase()] || []),
    _mangCua: () => '', _donNhieuCoSo: () => false, _mangPham: () => [],
  };
  moi.window = moi;
  return new Function('moi', `with(moi){ ${boc('_tapTkCo')}\n${boc('_tkNoCua')} return _tkNoCua('${loai}','${coso}'); }`)(moi);
}
const BOOT1 = {
  loaiChiPhi: [
    { ten: 'Chi phí tháo dỡ', tkNo: '331' },      // 🔴 lỡ điền TK CÓ vào cột TK Nợ
    { ten: 'Chi phí setup',   tkNo: '64125' },    // TK Nợ thật
    { ten: 'Chi phí lương',   tkNo: '141' },      // 🔴 tạm ứng — cũng là TK Có
    { ten: 'Chi phí lạ',      tkNo: '9999' },     // không nằm trong tập TK Có nào
  ],
  phanloai: [{ ten: 'Chuyển khoản', tkCo: '1121' }],
  tkNoMx: { 'chi phí tháo dỡ': { 'funzone': ['64125'] } },
};

/* ── 1. 🔴 ĐÚNG CA ANH THẮNG GẶP ───────────────────────────────────────────────────────── */
teq('🔴 cột TK Nợ cũ chứa 331 (một TK CÓ) → KHÔNG lấy, coi như chưa gắn mã',
  '', tra('Chi phí tháo dỡ', '', BOOT1));
teq('   141 (tạm ứng NV) cũng là TK Có → không lấy', '', tra('Chi phí lương', '', BOOT1));
teq('🔴 TK Có khai ở bảng 💳 Phân loại thanh toán cũng bị chối',
  '', tra('Chi phí x', '', { loaiChiPhi: [{ ten: 'Chi phí x', tkNo: '1121' }], phanloai: [{ ten: 'CK', tkCo: '1121' }], tkNoMx: {} }));
teq('   TK Có của chính dòng danh mục cũng bị chối',
  '', tra('Chi phí y', '', { loaiChiPhi: [{ ten: 'Chi phí y', tkNo: '3311', tkCo: '3311' }], phanloai: [], tkNoMx: {} }));

/* ── 2. KHÔNG ĐƯỢC CHỐI OAN ────────────────────────────────────────────────────────────── */
teq('mã TK Nợ thật thì vẫn lấy bình thường', '64125', tra('Chi phí setup', '', BOOT1));
teq('   mã lạ không nằm trong tập TK Có nào → vẫn lấy (không tự đoán khoảng số)',
  '9999', tra('Chi phí lạ', '', BOOT1));
teq('🔴 và ma trận theo MẢNG vẫn là nguồn ưu tiên, kể cả khi cột cũ chứa TK Có',
  '64125', tra('Chi phí tháo dỡ', 'Funzone', BOOT1));
teq('loại không có trong danh mục → rỗng, không nổ', '', tra('Không có', '', BOOT1));
teq('cột TK Nợ để trống → rỗng', '',
  tra('Chi phí z', '', { loaiChiPhi: [{ ten: 'Chi phí z', tkNo: '' }], phanloai: [], tkNoMx: {} }));

/* ── 3. TẬP TK CÓ DỰNG TỪ DỮ LIỆU ──────────────────────────────────────────────────────── */
const than = boc('_tapTkCo');
t('🔴 141 và 331 là hai mã app tự điền theo hình thức chi — phải nằm trong tập',
  than.indexOf("'141':1") >= 0 && than.indexOf("'331':1") >= 0, than);
t('   và gom thêm TK Có khai ở Cấu hình, không gõ cứng một khoảng số',
  than.indexOf('phanloai') >= 0 && than.indexOf('x.tkCo') >= 0
  && !/6\d{3}/.test(than), than);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: mã của loại chi phí luôn là TK Nợ, không bao giờ mượn một TK Có.');
