/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NÚT "🏪➜ ĐỔI CƠ SỞ CỦA ĐƠN" — BỐC HÀM THẬT RA CHẠY.
 *
 * Anh Thắng 19/09/2026: *"cho quyền admin đổi đơn sang cơ sở khác là được"*.
 *
 * =============================================================================================
 * 🔴 HAI LỖI ĐÃ CẮN, BÀI NÀY GIỮ CẢ HAI
 * =============================================================================================
 * 1. ĐỌC HỤT MỘT TẦNG (1.217.0). Bản đầu viết `CUR.cosoTrongDon`. Gói `getDon` có HAI tầng:
 *    `CUR` là cả gói, còn các ô của chính cái đơn nằm trong `CUR.don` — `CUR.don.cosoDon` ngay
 *    cạnh đã nói thế. Đọc hụt thì `undefined || []` ra mảng rỗng: KHÔNG lỗi, KHÔNG cảnh báo,
 *    chỉ là nút báo *"Đơn này chưa có dòng nào mang cơ sở"* đúng trên cái đơn đang hiện rõ cơ
 *    sở FZ_SC_VIVO_T4 và 6.136.000đ tạm ứng.
 *
 * 2. BA HỘP THOẠI LIÊN TIẾP, CHẾT TRÊN ĐIỆN THOẠI (1.217.2). Anh Thắng mở bằng Safari iPhone:
 *    *"Nút đổi đơn chưa chạy"*. iOS gắn vào hộp thoại đầu một ô "không cho trang này tạo thêm
 *    hộp thoại"; dính vào là mọi hộp sau trả `null` trong im lặng, luồng đứt giữa chừng mà
 *    không một câu lỗi nào. Nay hỏi bằng KHỐI TRONG TRANG (`#doiCoSoBox`), đúng khuôn
 *    `#datKyBox` vốn đã ghi sẵn bài học ấy — chỉ còn MỘT `confirm` cuối cùng.
 *
 * 🔴 BÀI PHÍA MÁY CHỦ KHÔNG BẮT ĐƯỢC CẢ HAI. `kiem-doi-ten-coso.php` gọi thẳng hàm PHP nên sợi
 *    dây nối hai bên — tên khoá, đúng tầng, cách hỏi — nằm ngoài tầm nó.
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
function bocDong(ten) {
  const i = HTML.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  return HTML.slice(i, HTML.indexOf('\n', i));
}
const MOI = ['moDoiCoSoDon', '_dcsLocDich', '_dcsCosoCu', 'chotDoiCoSoDon'].map(bocHam).join('\n')
  + '\n' + bocDong('dongDoiCoSoDon');
t('bốc được cả bộ hàm', MOI.length > 900, MOI.length);

/* ═══ 🔴 KHÔNG CÒN CHUỖI HỘP THOẠI ════════════════════════════════════════════════
 * Đếm trên chính mã: mở khối ra KHÔNG được hỏi hộp thoại nào, và cả luồng chỉ còn đúng một
 * `confirm` ở bước chốt. Thêm một `prompt` nào nữa là dựng lại đúng lỗi trên iPhone. */
teq('🔴 bước MỞ không dùng hộp thoại nào', 0, (bocHam('moDoiCoSoDon').match(/\b(prompt|confirm)\s*\(/g) || []).length);
teq('🔴 cả luồng chỉ còn ĐÚNG MỘT hộp xác nhận', 1, (MOI.match(/\bconfirm\s*\(/g) || []).length);
teq('   và không còn `prompt` nào', 0, (MOI.match(/\bprompt\s*\(/g) || []).length);
t('🔴 khối `#doiCoSoBox` có thật trong trang', /id="doiCoSoBox"/.test(HTML));
['dcsCu', 'dcsCuXem', 'dcsMoi', 'dcsLyDo', 'dcsCuWrap'].forEach(function (id) {
  t('   có ô ' + id, new RegExp('id="' + id + '"').test(HTML));
});

/* ── bệ đỡ DOM tối thiểu ───────────────────────────────────────────────────────── */
/* ⚠️ KHỞI ĐIỂM PHẢI LÀ `none`, ĐÚNG NHƯ MARKUP THẬT (`style="display:none"`). Dựng bằng chuỗi
   rỗng là bài kiểm tự tạo ra một thế giới nơi khối lúc nào cũng đang mở — và mọi phép "không
   mở" bên dưới xanh oan. */
function oGia() {
  const o = { value: '', innerHTML: '', style: { display: 'none' }, onchange: null, textContent: '' };
  o.parentNode = { style: { display: '' } };
  return o;
}
function chay(o) {
  o = o || {};
  const KHO = {}; ['doiCoSoBox', 'dcsCu', 'dcsCuXem', 'dcsMoi', 'dcsLyDo', 'dcsCuWrap'].forEach(function (k) { KHO[k] = oGia(); });
  KHO.doiCoSoBox.scrollIntoView = function () {};
  const goi = []; const keu = [];
  const CUR = { don: { maDon: 'D9',
      cosoTrongDon: ('cosoTrongDon' in o) ? o.cosoTrongDon : ['FZ_SC_VIVO_T4'] }, stChot: false };
  const CFG = { coso: (o.danhMuc || ['FUNZONE ADVENTURE', 'FUNZONE VŨNG TÀU', 'FZ_SC_VIVO_T4']).map(function (x) { return { ten: x }; }) };
  const run = { doiCoSoDon: function () { goi.push(Array.prototype.slice.call(arguments)); } };
  const gsr = { withSuccessHandler: function () { return gsr; }, withFailureHandler: function () { return run; } };
  const api = new Function('CUR', 'CFG', 'el', 'esc', '_laAdmin', 'toast', 'confirm', 'loading', 'google', '_log', 'openDon', 'boot',
    MOI + '\nreturn { mo:moDoiCoSoDon, chot:chotDoiCoSoDon, dong:dongDoiCoSoDon };')(
    CUR, CFG, function (id) { return KHO[id]; }, function (x) { return String(x == null ? '' : x); },
    function () { return o.admin !== false; },
    function (l, m) { keu.push(l + ':' + m); },
    function () { return o.dongY !== false; },
    function () {}, { script: { run: gsr } }, function () {}, function () {}, function () {});
  api.mo();
  if (o.chonDich !== undefined) { KHO.dcsMoi.value = o.chonDich; }
  if (o.chonNguon !== undefined) { KHO.dcsCu.value = o.chonNguon; }
  if (o.lyDo !== undefined) { KHO.dcsLyDo.value = o.lyDo; }
  if (o.chot !== false) { api.chot(); }
  return { goi: goi, keu: keu, o: KHO };
}
function dsTrongO(html) {
  const ra = []; const re = /<option value="([^"]*)"/g; let m;
  while ((m = re.exec(html))) { ra.push(m[1]); }
  return ra;
}

/* ═══ 1. 🔴 ĐỌC ĐÚNG TẦNG ══════════════════════════════════════════════════════════ */
const r1 = chay({ chonDich: 'FUNZONE ADVENTURE', lyDo: 'Lập nhầm' });
t('🔴 KHÔNG báo "chưa có dòng nào mang cơ sở" khi đơn rõ ràng có cơ sở',
  !r1.keu.some(function (x) { return /chưa có dòng nào mang cơ sở/.test(x); }), r1.keu);
teq('🔴 khối được mở ra', '', r1.o.doiCoSoBox.style.display);
teq('🔴 gửi đúng (mã đơn, đích, nguồn, lý do)',
  ['D9', 'FUNZONE ADVENTURE', 'FZ_SC_VIVO_T4', 'Lập nhầm'], r1.goi[0]);
teq('   và khối đóng lại sau khi gửi… (chỉ đóng ở nhánh thành công)', 1, r1.goi.length);
/* ⚠️ Phép đối chứng cho lỗi 1: đặt `cosoTrongDon` SAI TẦNG thì phải chối. */
const r1b = (function () {
  const KHO = {}; ['doiCoSoBox', 'dcsCu', 'dcsCuXem', 'dcsMoi', 'dcsLyDo', 'dcsCuWrap'].forEach(function (k) { KHO[k] = oGia(); });
  KHO.doiCoSoBox.scrollIntoView = function () {};
  const keu = [];
  const api = new Function('CUR', 'CFG', 'el', 'esc', '_laAdmin', 'toast', 'confirm', 'loading', 'google', '_log', 'openDon', 'boot',
    MOI + '\nreturn { mo:moDoiCoSoDon };')(
    { don: { maDon: 'D9' }, cosoTrongDon: ['FZ_SC_VIVO_T4'], stChot: false },
    { coso: [{ ten: 'FUNZONE ADVENTURE' }] }, function (id) { return KHO[id]; },
    function (x) { return String(x); }, function () { return true; },
    function (l, m) { keu.push(l + ':' + m); }, function () { return true; },
    function () {}, { script: { run: {} } }, function () {}, function () {}, function () {});
  api.mo();
  return KHO.doiCoSoBox.style.display;
})();
teq('🔴 đặt `cosoTrongDon` SAI TẦNG thì khối KHÔNG mở — đối chứng cho lỗi 1.217.0', 'none', r1b);

/* ═══ 2. CHỈ ADMIN ═════════════════════════════════════════════════════════════════ */
const r2 = chay({ admin: false, chot: false });
teq('🔴 không phải Admin thì khối không mở', 'none', r2.o.doiCoSoBox.style.display);
t('   và nói rõ vì sao', r2.keu.some(function (x) { return /Admin/.test(x); }), r2.keu);

/* ═══ 3. ĐƠN CHƯA CÓ CƠ SỞ THẬT ════════════════════════════════════════════════════ */
const r3 = chay({ cosoTrongDon: [], chot: false });
teq('đơn thật sự chưa có cơ sở → không mở', 'none', r3.o.doiCoSoBox.style.display);
t('   và đây mới là chỗ câu nhắc ấy đúng',
  r3.keu.some(function (x) { return /chưa có dòng nào mang cơ sở/.test(x); }), r3.keu);

/* ═══ 4. Ô ĐÍCH KHÔNG BÀY CHÍNH GIAN ĐANG ĐỨNG ═════════════════════════════════════
 * 🔴 Chọn nó là một cú bấm vô nghĩa, và máy chủ sẽ chối — bày ra chỉ để dẫn người ta vào lỗi. */
const r4 = chay({ chot: false });
t('🔴 ô đích KHÔNG có gian đang đứng', dsTrongO(r4.o.dcsMoi.innerHTML).indexOf('FZ_SC_VIVO_T4') < 0,
  dsTrongO(r4.o.dcsMoi.innerHTML));
t('   nhưng vẫn có mấy gian kia', dsTrongO(r4.o.dcsMoi.innerHTML).length === 2, dsTrongO(r4.o.dcsMoi.innerHTML));

/* ═══ 5. ĐƠN MỘT GIAN: BÀY Ô ĐỌC, GIẤU Ô CHỌN NGUỒN ════════════════════════════════ */
teq('đơn một gian: giấu ô chọn gian nguồn', 'none', r4.o.dcsCuWrap.style.display);
teq('   và bày gian ấy ở ô chỉ-để-đọc', 'FZ_SC_VIVO_T4', r4.o.dcsCuXem.value);

/* ═══ 6. ĐƠN GHÉP NHIỀU GIAN: BÀY Ô CHỌN, VÀ ĐỔI NGUỒN THÌ LỌC LẠI ĐÍCH ════════════ */
const r6 = chay({ cosoTrongDon: ['FZ_SC_VIVO_T4', 'FUNZONE VŨNG TÀU'],
  chonNguon: 'FUNZONE VŨNG TÀU', chonDich: 'FUNZONE ADVENTURE', chot: false });
teq('🔴 đơn 2 gian: bày ô chọn gian nguồn', '', r6.o.dcsCuWrap.style.display);
teq('   và ô ấy liệt đúng hai gian của đơn',
  ['FZ_SC_VIVO_T4', 'FUNZONE VŨNG TÀU'], dsTrongO(r6.o.dcsCu.innerHTML));
t('🔴 đổi gian nguồn thì ô đích lọc lại', typeof r6.o.dcsCu.onchange === 'function');
/* ⚠️ GỌI CÓ GÁC. Bài kiểm mà VĂNG LỖI thì nó vẫn "đỏ", nhưng đỏ theo kiểu không đọc ra được
   phép nào hỏng — và bệ thử đột biến của chính kho này đếm theo dòng '·' nên lượt văng bị ghi
   nhầm thành XANH OAN. Đã cắn thật khi dựng bài này. Hỏng thì phải hỏng thành một dòng đọc được. */
if (typeof r6.o.dcsCu.onchange === 'function') { r6.o.dcsCu.onchange(); }
t('   và sau khi lọc, gian nguồn mới không còn trong ô đích',
  dsTrongO(r6.o.dcsMoi.innerHTML).indexOf('FUNZONE VŨNG TÀU') < 0, dsTrongO(r6.o.dcsMoi.innerHTML));

/* ═══ 7. BẤM THÔI Ở HỘP XÁC NHẬN ═══════════════════════════════════════════════════ */
const r7 = chay({ chonDich: 'FUNZONE ADVENTURE', dongY: false });
teq('🔴 huỷ ở hộp xác nhận thì không gửi', 0, r7.goi.length);

/* ═══ 8. DANH MỤC RỖNG ═════════════════════════════════════════════════════════════ */
const r8 = chay({ danhMuc: [], chot: false });
teq('danh mục cơ sở rỗng → không mở', 'none', r8.o.doiCoSoBox.style.display);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hỏi bằng khối trong trang, chạy được trên điện thoại.');
