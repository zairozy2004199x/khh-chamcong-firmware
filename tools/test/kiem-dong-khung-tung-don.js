/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MỖI ĐƠN MỘT KHUNG Ở BẢNG THEO DÕI CHỐT HOÁ ĐƠN
 *
 * Anh Thắng 11/09/2026: *"Của đơn nào thì đóng khung cho rõ ràng lại"*, kèm ảnh bốn đơn nối
 * đuôi nhau — trong đó BA đơn trùng tên y hệt ("Chi phí cơ sở tuần 07.09-13.09.2026").
 *
 * =============================================================================================
 * 🔴 MỘT ĐƠN CHIẾM ÍT NHẤT BA HÀNG: hàng đơn · hàng liệt kê hạng mục · và mỗi hạng mục thêm
 *    một hàng ô nhập (ẩn tới khi bấm "Chốt xong"). Bốn đơn là mười mấy hàng phân cách bằng
 *    đúng một đường kẻ mờ như mọi đường kẻ khác. Nhìn xuống giữa bảng thì không biết hàng "Cáp
 *    màn hình" thuộc đơn nào — mà đây là màn kế toán duyệt tiền.
 *
 * 🔴 HÀNG Ô NHẬP PHẢI NẰM TRONG KHUNG. Nó bung ra đúng lúc người ta thao tác; rơi ra ngoài
 *    khung là mất mạch ngay tại giây phút cần rõ nhất. Nên đáy khung là một hàng RIÊNG đặt sau
 *    tất cả hàng ô nhập, không phải viền dưới của hàng liệt kê.
 *
 * ⚠️ BỐC HÀM THẬT RA CHẠY.
 *
 * Chạy: node tools/test/kiem-dong-khung-tung-don.js
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
const HAM = ['renderDonHM', 'hmDongForm', '_hmNgay'];
const MOI = HAM.map(function (x) { const c = bocHam(x); t('bốc được ' + x + '()', c.length > 40, c.length); return c; }).join('\n');

const KHO = {};
function chay(items) {
  ['hmBody', 'hmEmpty', 'hmNhac', 'hmFilter'].forEach(function (id) { KHO[id] = { innerHTML: '', style: {}, value: 'all' }; });
  new Function('HM_ITEMS', 'el', 'esc', 'money', '_dmy', '_hmKeyDuyet', 'hmNutChung', 'hmNhanChung', '_uncGon',
    MOI + '\nrenderDonHM();')(
    items,
    function (id) { return KHO[id]; },
    function (x) { return String(x == null ? '' : x); },
    function (x) { return String(Number(x) || 0); },
    function (v) { return String(v); },
    function (ma, row) { return 'K' + ma + '-' + row; },
    function () { return '<button>nut</button>'; },
    function () { return '<span>nhan</span>'; },
    function () { return ''; });
  return KHO['hmBody'].innerHTML;
}

/* Đúng cảnh trong ảnh: một dự án Setup và hai đơn cơ sở TRÙNG TÊN nhau. */
const IT = [
  { maDA: 'DA1', tenDA: 'TÀU ESTELLA', loaiDA: 'Setup lắp đặt', isCoSo: false, nguoiTao: 'Nguyễn Hữu Thọ',
    row: 5, noiDung: 'Mua đồ điện', thucTe: 13000000, duToan: 0, hinhThuc: '', kyDA: {}, hm: { tt: 'nhap' } },
  { maDA: 'DA1', tenDA: 'TÀU ESTELLA', loaiDA: 'Setup lắp đặt', isCoSo: false, nguoiTao: 'Nguyễn Hữu Thọ',
    row: 6, noiDung: 'Xe vận chuyển', thucTe: 5000000, duToan: 0, hinhThuc: '', kyDA: {}, hm: { tt: 'ung' } },
  { maDA: 'DA2', tenDA: 'Chi phí cơ sở tuần 07.09-13.09.2026', loaiDA: 'Chi phí cơ sở', isCoSo: true, nguoiTao: 'Nguyễn Hữu Thọ',
    row: 5, noiDung: 'Cáp màn hình', thucTe: 500000, duToan: 0, hinhThuc: '', kyDA: { tu: '2026-09-07', den: '2026-09-13' }, hm: { tt: 'xin' } },
  { maDA: 'DA3', tenDA: 'Chi phí cơ sở tuần 07.09-13.09.2026', loaiDA: 'Chi phí cơ sở', isCoSo: true, nguoiTao: 'Nguyễn Hữu Thọ',
    row: 5, noiDung: 'Cáp màn hình', thucTe: 500000, duToan: 0, hinhThuc: '', kyDA: { tu: '2026-09-07', den: '2026-09-13' }, hm: { tt: 'nhap' } },
];
const H = chay(IT);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 1. KHUNG CÓ ĐỦ BỐN CẠNH, MỖI ĐƠN MỘT KHUNG
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
function dem(re) { return (H.match(re) || []).length; }
/* 🔴 GÁY TRÁI PHẢI LIỀN MẠCH QUA CẢ BA HÀNG của một đơn (hàng đơn · hàng liệt kê · hàng đáy).
   Chỉ vẽ ở hàng đầu là khung hở một bên từ hàng thứ hai trở xuống — đúng chỗ nhiều chữ nhất. */
teq('🔴 ba đơn × ba hàng → chín đoạn gáy trái', 9, dem(/border-left:4px solid (#0f766e|#1d4ed8)/g));
teq('🔴 ba đơn → ba cạnh dưới đóng khung', 3, dem(/border-bottom:2px solid #cbd5e1/g));
t('🔴 có cạnh trên mở khung', dem(/border-top:2px solid #cbd5e1/g) >= 3, dem(/border-top:2px solid #cbd5e1/g));
t('🔴 có cạnh phải',          dem(/border-right:2px solid #cbd5e1/g) >= 3, dem(/border-right:2px solid #cbd5e1/g));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 2. 🔴 MÀU GÁY NÓI LUÔN ĐÂY LÀ ĐƠN HAY DỰ ÁN
 *
 * Cùng bảng màu với ba nút chọn loại lúc tạo: đơn cơ sở xanh ngọc · dự án xanh dương.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
teq('dự án Setup → gáy xanh dương, liền ba hàng', 3, dem(/border-left:4px solid #1d4ed8/g));
teq('hai đơn cơ sở → gáy xanh ngọc, liền ba hàng', 6, dem(/border-left:4px solid #0f766e/g));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 3. 🔴 HÀNG Ô NHẬP NẰM TRONG KHUNG — đáy khung đứng SAU nó
 *
 * Đây là chỗ dễ làm sai nhất: đặt viền dưới lên hàng liệt kê thì trông vẫn có khung, nhưng bấm
 * "Chốt xong" là ô nhập bung ra NGOÀI khung.
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const viTriForm = H.indexOf('data-hmf=');
const viTriDay  = H.indexOf('border-bottom:2px solid #cbd5e1');
t('🔴 hàng ô nhập đứng TRƯỚC đáy khung', viTriForm >= 0 && viTriForm < viTriDay, [viTriForm, viTriDay]);
t('🔴 và chính hàng ô nhập cũng có cạnh phải của khung',
  /data-hmf="[^"]*"[^>]*>\s*<td[^>]*border-right:2px solid #cbd5e1/.test(H), H.slice(viTriForm, viTriForm + 260));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 4. 🔴 CÓ KHOẢNG HỞ GIỮA HAI KHUNG — dính nhau thì khung nọ nối khung kia thành một
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('có hàng đệm giữa các khung', /height:12px;padding:0;border:0/.test(H), H.slice(-400));

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 5. 🔴 NỘI DUNG KHÔNG ĐƯỢC MẤT — khung là thêm viền, không phải vẽ lại bảng
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
t('vẫn có tên dự án',        H.indexOf('TÀU ESTELLA') >= 0);
t('vẫn có từng hạng mục · Mua đồ điện', H.indexOf('Mua đồ điện') >= 0);
t('                     · Xe vận chuyển', H.indexOf('Xe vận chuyển') >= 0);
t('                     · Cáp màn hình',  H.indexOf('Cáp màn hình') >= 0);
teq('vẫn đủ ba hàng đơn',   3, dem(/openDuAn\(/g) / 2);   // mỗi đơn: một liên kết tên + một nút Mở
t('vẫn có nhãn "chưa chốt"', H.indexOf('chưa chốt') >= 0);
/* Hai đơn TRÙNG TÊN vẫn là hai khung riêng — đây chính là ca anh Thắng gặp. */
teq('🔴 hai đơn trùng tên vẫn tách thành hai khung', 2,
  (H.match(/Chi phí cơ sở tuần 07\.09-13\.09\.2026/g) || []).length);

/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * 6. 🔴 BẢNG DỰ ÁN KHÔNG BỊ ĐỔI — `hmDongForm` không truyền viền thì vẽ y như cũ
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const F = new Function(bocHam('hmDongForm') + '\nreturn hmDongForm;')();
t('không truyền viền → không có cạnh phải', F('k', 13).indexOf('border-right') < 0, F('k', 13));
t('   vẫn giữ gáy xanh lá vốn có',          F('k', 13).indexOf('border-left:3px solid #16a34a') >= 0, F('k', 13));
t('truyền viền → có cạnh phải',             F('k', 8, ';border-right:2px solid #cbd5e1').indexOf('border-right') > 0);
t('🔴 bảng dự án gọi hmDongForm KHÔNG kèm viền (giữ nguyên như cũ)',
  /hmDongForm\(_hmKeyDA\(p\.row\), 13\)/.test(HTML));

/* ─────────────────────────────────────────────────────────────────────────────────────────── */
if (TRUOT.length) {
  console.log('\n❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(function (x) { console.log('   • ' + x); });
  process.exit(1);
}
console.log('\n✅ ĐẠT ' + DAT + ' / ' + DAT + ' — mỗi đơn một khung đủ bốn cạnh, ô nhập nằm trong khung');
