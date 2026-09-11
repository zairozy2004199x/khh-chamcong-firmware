/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN ĐƠN CHI PHÍ CƠ SỞ: MỘT ĐƠN NHIỀU GIAN.
 *
 * Anh Thắng 11/09/2026: *"tiếp tới chi phí kỹ thuật cơ sở, sẽ giống kiểu chi phí bên POSH,
 * 1 đơn nhiều cơ sở chung 1 đơn"*.
 *
 * =============================================================================================
 * 🔴 SỔ CHUNG XUYÊN SUỐT ≠ ĐƠN CƠ SỞ THEO ĐỢT. Cả hai cùng loại "Chi phí cơ sở". Chốt cũ giấu
 *    nút Đóng / Xoá / Đổi tên theo `isCoSo`, nên giữ nguyên là MỌI đơn mới đều bất động: nhân
 *    viên nhập xong không có cách nào chốt, và không có câu nào nói vì sao.
 *
 * 🔴 BẢNG THEO GIAN LẤY SỐ TỪ MÁY CHỦ. Cộng lại ở màn là khai lần thứ hai luật "hạng mục có
 *    con thì tiền nằm ở con" — lần thứ hai bao giờ cũng lệch, rồi tổng các gian không khớp
 *    tổng đơn mà không ai biết bên nào sai.
 *
 * 🔴 CANH CẶP NHÃN–SỐ, KHÔNG CANH RỜI. Ba gian ba con số cùng có mặt mà gán lộn chỗ thì phép
 *    "có đủ ba số" vẫn xanh, và báo cáo gửi đi sai gian.
 *
 * ⚠️ CHẠY THẬT hàm bốc từ app.html, với DOM giả NGHIÊM (ô không có thật thì nổ, không trả bừa).
 *
 * Chạy: node tools/test/kiem-don-coso-man.js
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

/* ── Bệ đỡ: một ô DOM giả duy nhất, và hỏi ô nào KHÔNG có là NỔ ─────────────────────────
   🔴 `el()` trả bừa một vật giả cho mọi tên là đục tên ô trong mã vẫn xanh. */
function chay(r) {
  const box = { style: { display: 'x' }, innerHTML: 'CŨ' };
  const moi = {
    el: id => { if (id !== 'daTheoCoSoBox') throw new Error('hỏi ô lạ: ' + id); return box; },
    money: n => (Number(n) || 0).toLocaleString('vi-VN'),
    esc: s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
  };
  const src = `${boc('renderDaTheoCoSo')}\n return renderDaTheoCoSo(R);`;
  new Function('moi', 'R', `with(moi){ ${src} }`)(moi, r);
  return box;
}
const nutQT = (r, isAdmin) =>
  new Function('R', 'A', `${boc('daNutQuyTrinh')}\n return daNutQuyTrinh(R, A);`)(r, isAdmin);

/* ── 1. 🔴 BỐN NÚT QUY TRÌNH: SỔ CHUNG BẤT ĐỘNG, ĐƠN THEO ĐỢT THÌ KHÔNG ───────────────── */
const chung = nutQT({ cosoChung: true, isCoSo: true, editable: true, closed: false }, true);
t('🔴 sổ chung: KHÔNG hiện nút Đổi tên', chung.doiTen === false, chung);
t('🔴 sổ chung: KHÔNG hiện nút Đóng',    chung.dong === false, chung);
t('🔴 sổ chung: KHÔNG hiện nút Xoá',     chung.xoa === false, chung);
t('   sổ chung: KHÔNG hiện nút Mở lại',  chung.moLai === false, chung);

const don = nutQT({ cosoChung: false, isCoSo: true, editable: true, closed: false }, false);
t('🔴 đơn cơ sở theo đợt: CÓ nút Đổi tên — chốt cũ theo isCoSo giấu mất', don.doiTen === true, don);
t('🔴 đơn cơ sở theo đợt: CÓ nút Đóng — không có thì nhân viên nhập xong không chốt được', don.dong === true, don);
t('🔴 đơn cơ sở theo đợt: CÓ nút Xoá (người nhập còn sửa được đơn)', don.xoa === true, don);
t('   đơn đang mở thì KHÔNG hiện Mở lại', don.moLai === false, don);
/* 🔴 CẢ VỚI ADMIN. Ca trên có isAdmin=false nên nó xanh ngay cả khi mã quên hỏi "đã đóng
   chưa" — phải có một ca Admin + đơn đang mở thì mới bắt được. */
const moAdmin = nutQT({ cosoChung: false, editable: true, closed: false }, true);
t('🔴 Admin xem đơn ĐANG MỞ vẫn KHÔNG thấy Mở lại', moAdmin.moLai === false, moAdmin);

const dong = nutQT({ cosoChung: false, editable: false, closed: true }, true);
t('   đơn đã đóng: Admin thấy Mở lại', dong.moLai === true, dong);
t('   đơn đã đóng: KHÔNG còn Đóng / Xoá / Đổi tên',
  dong.dong === false && dong.xoa === false && dong.doiTen === false, dong);
const dongNV = nutQT({ cosoChung: false, editable: false, closed: true }, false);
t('   đơn đã đóng: nhân viên KHÔNG thấy Mở lại', dongNV.moLai === false, dongNV);
const moKhongSua = nutQT({ cosoChung: false, editable: false, closed: false }, false);
t('   đơn đang mở mà người này không sửa được: không thấy nút Xoá', moKhongSua.xoa === false, moKhongSua);

/* ── 2. BẢNG THEO GIAN CHỈ HIỆN Ở ĐƠN CƠ SỞ ───────────────────────────────────────────── */
const setup = chay({ isCoSo: false, theoCoSo: [{ coso: 'Gian A', duToan: 1, thucTe: 2, soDong: 1 }] });
t('dự án Setup/Tháo dỡ: bảng theo gian ẨN (gian chính là tên dự án)', setup.style.display === 'none', setup.style.display);
t('   và dọn sạch nội dung cũ, không để lại bảng của đơn vừa xem', setup.innerHTML === '', setup.innerHTML);
const rong = chay({ isCoSo: true, theoCoSo: [] });
t('đơn cơ sở chưa có dòng nào: bảng ẩn', rong.style.display === 'none', rong.style.display);

/* ── 3. 🔴 CANH CẶP NHÃN–SỐ ───────────────────────────────────────────────────────────── */
const H = chay({
  isCoSo: true, cosoChung: false,
  theoCoSo: [
    { coso: 'Gian A', duToan: 5000000, thucTe: 5000000, soDong: 3 },
    { coso: 'Gian B', duToan: 1000000, thucTe: 1200000, soDong: 1 },
    { coso: 'Gian C', duToan: 800000,  thucTe: 800000,  soDong: 1 },
    { coso: '',       duToan: 0,       thucTe: 300000,  soDong: 1 },
  ],
}).innerHTML;
t('bảng hiện ra', H.length > 0);
/* Đọc các ô của HÀNG mang tên gian ấy — không phải "có con số này đâu đó trong bảng". */
function hang(ten) {
  const i = H.indexOf('🏢 ' + ten);
  if (i < 0) return null;
  const het = H.indexOf('</tr>', i);
  return H.slice(i, het < 0 ? H.length : het).match(/>([^<>]*?)<\/td>/g) || null;
}
const A = hang('Gian A'), B = hang('Gian B'), C = hang('Gian C');
t('🔴 HÀNG Gian A mang đúng số của Gian A (3 dòng · 5.000.000 · 5.000.000 · khớp)',
  !!A && A.join('|').includes('3') && A.join('|').includes('5.000.000') && A.join('|').includes('✓ khớp'), A);
t('🔴 HÀNG Gian B mang 1.200.000 và báo VƯỢT 200.000 — không phải số của gian khác',
  !!B && B.join('|').includes('1.200.000') && B.join('|').includes('⚠️ vượt 200.000'), B);
t('   HÀNG Gian C khớp', !!C && C.join('|').includes('800.000') && C.join('|').includes('✓ khớp'), C);
t('🔴 dòng chưa ghi gian KHÔNG biến mất — nó vẫn là tiền thật của đơn',
  /chưa ghi gian/.test(H) && H.includes('300.000'), H.slice(0, 200));
t('   dòng tổng nói rõ đơn rải qua mấy gian', /TỔNG 4 GIAN/.test(H), H.slice(-400));
/* Tổng phải là tổng CỦA CÁC HÀNG, canh bằng con số chứ không bằng chữ "TỔNG". */
t('🔴 dòng tổng = 7.300.000 (5.000.000+1.200.000+800.000+300.000)',
  H.slice(H.indexOf('TỔNG 4 GIAN')).includes('7.300.000'), H.slice(H.indexOf('TỔNG 4 GIAN')));
t('   tổng dự toán = 6.800.000',
  H.slice(H.indexOf('TỔNG 4 GIAN')).includes('6.800.000'), H.slice(H.indexOf('TỔNG 4 GIAN')));

/* ── 4. TÊN GIAN LÀ CHỮ NGƯỜI GÕ -> PHẢI RÀO ────────────────────────────────────────── */
const X = chay({ isCoSo: true, theoCoSo: [{ coso: '<script>x</script>', duToan: 0, thucTe: 1, soDong: 1 }] }).innerHTML;
t('🔴 tên gian có thẻ HTML bị rào, không chạy được', !/<script>/.test(X), X.slice(0, 200));

/* ═══════════════════════════════════════════════════════════════════════════════════════ */
console.log('');
if (TRUOT.length) {
  console.log('❌ TRƯỢT ' + TRUOT.length + ' / ' + (DAT + TRUOT.length));
  TRUOT.forEach(x => console.log('   • ' + x));
  process.exit(1);
}
console.log('✅ ĐẠT ' + DAT + ' / ' + DAT + ' — màn đơn chi phí cơ sở: một đơn nhiều gian');
process.exit(0);
