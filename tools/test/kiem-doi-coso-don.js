/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT "🏪➜ ĐỔI CƠ SỞ CỦA ĐƠN" — BỐC HÀM THẬT RA CHẠY.
 *
 * Anh Thắng 19/09/2026: *"cho quyền admin đổi đơn sang cơ sở khác là được"*.
 *
 * =============================================================================================
 * 🔴 BÀI NÀY SINH RA TỪ MỘT LỖI ĐỌC HỤT MỘT TẦNG
 * =============================================================================================
 * Bản đầu viết `CUR.cosoTrongDon`. Gói `getDon` có HAI tầng: `CUR` là cả gói (đơn · dòng chi ·
 * đối chiếu…), còn các ô của chính cái đơn nằm trong `CUR.don` — `CUR.don.cosoDon` ngay cạnh đã
 * nói thế. Đọc hụt một tầng thì `undefined || []` ra mảng rỗng: KHÔNG lỗi, KHÔNG cảnh báo, chỉ
 * là nút báo *"Đơn này chưa có dòng nào mang cơ sở"* đúng trên cái đơn đang hiện rõ cơ sở
 * FZ_SC_VIVO_T4 và 6.136.000đ tạm ứng. Anh Thắng: *"đẩy nguyên đơn mà"*.
 *
 * 🔴 BÀI KIỂM PHÍA MÁY CHỦ KHÔNG BẮT ĐƯỢC — và đó mới là bài học. `kiem-doi-ten-coso.php` canh
 *    `doi_coso_don()` rất kỹ (37 phép, 6 đột biến đều đỏ) nhưng nó gọi thẳng hàm PHP, nên cái
 *    dây nối giữa hai bên — tên khoá, đúng tầng — nằm ngoài tầm nó. Phải có một bài CHẠY chính
 *    hàm JS ấy với đúng hình dạng gói mà máy chủ trả về.
 *
 * ⚠️ HÌNH DẠNG GÓI Ở ĐÂY PHẢI GIỐNG THẬT. Dựng `CUR` phẳng cho tiện là bài kiểm tự bịa ra một
 *    thế giới nơi lỗi kia không tồn tại.
 *
 * Chạy: node tools/test/kiem-doi-coso-don.js
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
const FN = bocHam('moDoiCoSoDon');
t('bốc được moDoiCoSoDon()', FN.length > 400, FN.length);

/* Bệ đỡ: dựng đúng hai tầng `CUR` / `CUR.don` như `getDon` trả về. */
function chay(o) {
  o = o || {};
  const goi = [];          // các lượt gọi máy chủ
  const keu = [];          // các câu toast
  const hoi = [];          // các câu prompt đã hỏi
  const traLoi = (o.traLoi || []).slice();
  const CUR = {
    don: { maDon: 'D9', cosoDon: 'FZ_SC_VIVO_T4',
           cosoTrongDon: ('cosoTrongDon' in o) ? o.cosoTrongDon : ['FZ_SC_VIVO_T4'] },
    stChot: false,
  };
  const CFG = { coso: (o.danhMuc || ['FUNZONE ADVENTURE', 'FUNZONE VŨNG TÀU']).map(function (x) { return { ten: x }; }) };
  const run = { doiCoSoDon: function () { goi.push(Array.prototype.slice.call(arguments)); } };
  const gsr = {
    withSuccessHandler: function () { return gsr; },
    withFailureHandler: function () { return run; },
  };
  new Function('CUR', 'CFG', '_laAdmin', 'toast', 'prompt', 'confirm', 'loading', 'google', '_log', 'openDon', 'boot',
    FN + '\nmoDoiCoSoDon();')(
    CUR, CFG,
    function () { return o.admin !== false; },
    function (loai, msg) { keu.push(loai + ':' + msg); },
    function (msg, mac) { hoi.push(msg); return traLoi.length ? traLoi.shift() : mac; },
    function () { return o.dongY !== false; },
    function () {}, { script: { run: gsr } }, function () {}, function () {}, function () {});
  return { goi: goi, keu: keu, hoi: hoi };
}

/* ═══ 1. 🔴 ĐỌC ĐÚNG TẦNG — chính là lỗi đã cắn ════════════════════════════════════ */
const r1 = chay({ traLoi: ['1', 'Lập nhầm'] });
t('🔴 KHÔNG còn báo "chưa có dòng nào mang cơ sở" khi đơn rõ ràng có cơ sở',
  !r1.keu.some(function (x) { return /chưa có dòng nào mang cơ sở/.test(x); }), r1.keu);
teq('🔴 gửi được lệnh lên máy chủ', 1, r1.goi.length);
teq('   và gửi đúng (mã đơn, cơ sở đích, cơ sở cũ)',
  ['D9', 'FUNZONE ADVENTURE', 'FZ_SC_VIVO_T4', 'Lập nhầm'], r1.goi[0]);
/* ⚠️ Phép đối chứng: dựng `CUR` PHẲNG (cosoTrongDon đặt sai tầng) thì nút phải chối — nếu nó
   vẫn chạy nghĩa là hàm đang đọc cả hai chỗ, và lỗi kia có thể quay lại mà không ai biết. */
const r1b = (function () {
  const FN2 = FN;
  const goi = []; const keu = [];
  const CUR = { don: { maDon: 'D9' }, cosoTrongDon: ['FZ_SC_VIVO_T4'], stChot: false };
  const run = { doiCoSoDon: function () { goi.push(1); } };
  const gsr = { withSuccessHandler: function () { return gsr; }, withFailureHandler: function () { return run; } };
  new Function('CUR', 'CFG', '_laAdmin', 'toast', 'prompt', 'confirm', 'loading', 'google', '_log', 'openDon', 'boot',
    FN2 + '\nmoDoiCoSoDon();')(
    CUR, { coso: [{ ten: 'FUNZONE ADVENTURE' }] }, function () { return true; },
    function (l, m) { keu.push(l + ':' + m); }, function () { return '1'; }, function () { return true; },
    function () {}, { script: { run: gsr } }, function () {}, function () {}, function () {});
  return { goi: goi, keu: keu };
})();
teq('🔴 đặt `cosoTrongDon` SAI TẦNG thì nút chối — phép đối chứng cho lỗi đã cắn', 0, r1b.goi.length);

/* ═══ 2. CHỈ ADMIN ═════════════════════════════════════════════════════════════════ */
const r2 = chay({ admin: false });
teq('🔴 không phải Admin thì không gửi gì', 0, r2.goi.length);
t('   và nói rõ vì sao', r2.keu.some(function (x) { return /Admin/.test(x); }), r2.keu);

/* ═══ 3. ĐƠN CHƯA CÓ CƠ SỞ THẬT thì mới được chối ══════════════════════════════════ */
const r3 = chay({ cosoTrongDon: [] });
teq('đơn thật sự chưa có cơ sở → không gửi', 0, r3.goi.length);
t('   và đây mới là chỗ câu nhắc ấy đúng',
  r3.keu.some(function (x) { return /chưa có dòng nào mang cơ sở/.test(x); }), r3.keu);

/* ═══ 4. ĐƠN GHÉP NHIỀU GIAN — HỎI THÊM MỘT BƯỚC, KHÔNG ĐOÁN ═══════════════════════
 * 🔴 Đoán bừa gian đầu là gom nhầm tiền của một gian không liên quan sang chỗ khác. */
const r4 = chay({ cosoTrongDon: ['FZ_SC_VIVO_T4', 'FUNZONE VŨNG TÀU'], traLoi: ['2', '1', 'Dọn'] });
t('🔴 đơn 2 gian thì HỎI đổi gian nào', r4.hoi.some(function (x) { return /Đổi cơ sở NÀO/.test(x); }), r4.hoi);
teq('   và gửi đúng gian đã chọn (số 2)',
  'FUNZONE VŨNG TÀU', r4.goi.length ? r4.goi[0][2] : null);

/* ═══ 5. CƠ SỞ ĐÍCH LẤY TỪ DANH MỤC, KHÔNG GÕ TAY ══════════════════════════════════
 * 🔴 Gõ tay chính là cách đẻ ra con cơ sở ảo đang phải dọn. */
t('🔴 danh sách đích dựng từ CFG.coso', /CFG\.coso/.test(FN), null);
t('   và loại chính gian đang đứng ra khỏi danh sách đích', /!==\s*cu/.test(FN), null);
const r5 = chay({ danhMuc: [] });
teq('danh mục rỗng → không gửi gì', 0, r5.goi.length);

/* ═══ 6. BẤM HUỶ Ở HỘP XÁC NHẬN THÌ THÔI ═══════════════════════════════════════════ */
const r6 = chay({ traLoi: ['1', 'Lý do'], dongY: false });
teq('🔴 huỷ ở bước xác nhận thì không gửi', 0, r6.goi.length);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: nút đổi cơ sở đọc đúng tầng và hỏi đủ trước khi dời tiền.');
