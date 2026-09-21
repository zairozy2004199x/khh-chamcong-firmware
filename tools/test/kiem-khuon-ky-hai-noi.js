/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KHUÔN CHUỖI KỲ DỰNG Ở HAI NƠI — PHẢI RA CÙNG MỘT KẾT QUẢ.
 *
 * Chuỗi kỳ `T9/2026 (7/9-13/9/2026)` là hợp đồng của cả app: lọc theo tuần, xuất MISA, báo cáo,
 * `khoang_ky()` — tất cả bám vào nó. Nhưng nó được dựng ở HAI nơi:
 *
 *    · `_kyRange(s,e)`               trong app.html — lúc TẠO đơn kỳ tự do
 *    · `VHCP_Don::ky_tu_khoang()`    trong PHP      — lúc ĐẶT LẠI khoảng ngày của đơn
 *
 * =============================================================================================
 * 🔴 HAI NƠI GÕ CỨNG LÀ HAI NƠI SẼ LỆCH NHAU. Lệch một dấu gạch thôi thì đơn tạo ra và đơn đặt
 *    lại rơi vào HAI kỳ khác nhau cho cùng một khoảng ngày — và lọc theo tuần không bao giờ gom
 *    chúng lại được nữa. Hỏng kiểu ấy không ném lỗi nào: bảng vẫn vẽ, chỉ là thiếu đơn.
 *
 * ⚠️ CHẠY THẬT CẢ HAI: bốc `_kyRange` ra khỏi app.html chạy bằng node, gọi PHP bằng dòng lệnh,
 *    rồi so từng cặp. Dò chuỗi mã của hai bên là không chốt được gì — hai đoạn mã viết khác nhau
 *    vẫn có thể ra cùng kết quả, và viết giống nhau vẫn có thể ra khác.
 *
 * Chạy: node tools/test/kiem-khuon-ky-hai-noi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path'), cp = require('child_process');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

const i = HTML.indexOf('function _kyRange(');
t('bốc được _kyRange() từ app.html', i >= 0);
const kyRange = new Function('return ' + HTML.slice(i, HTML.indexOf('\n', i)).trim())();

/* Ngày chọn để đụng vào đúng những chỗ dễ lệch: trong tháng, vắt tháng, vắt năm, một ngày,
   đợt dài, tháng hai năm nhuận, và ngày một chữ số (chỗ dễ đệm số 0 nhầm). */
const CA = [
  ['2026-09-07', '2026-09-13'],
  ['2026-08-31', '2026-09-06'],
  ['2026-12-28', '2027-01-03'],
  ['2026-09-05', '2026-09-05'],
  ['2026-09-01', '2026-09-25'],
  ['2028-02-28', '2028-03-05'],
  ['2026-01-01', '2026-01-07'],
  ['2026-10-06', '2026-10-12'],
  ['2026-11-30', '2026-12-06'],
];

/* PHP: gọi một lượt cho cả danh sách, khỏi bật php chín lần. */
const php = `
$goc = ${JSON.stringify(GOC)};
require $goc . '/tools/test/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
$ra = array();
foreach ( ${JSON.stringify(CA)
  .replace(/\[/g, 'array(').replace(/\]/g, ')')
  .replace(/"/g, "'")} as $c ) { $ra[] = VHCP_Don::ky_tu_khoang( $c[0], $c[1] ); }
echo json_encode( $ra );
`;
const r = cp.spawnSync('php', ['-r', php], { encoding: 'utf8' });
t('chạy được VHCP_Don::ky_tu_khoang() qua PHP', r.status === 0, (r.stderr || '').slice(0, 400));
let dsPhp = [];
try { dsPhp = JSON.parse((r.stdout || '').trim()); } catch (e) { t('PHP trả về JSON đọc được', false, r.stdout); }

CA.forEach((c, k) => {
  const [a, z] = c;
  const js = kyRange(new Date(a + 'T00:00:00'), new Date(z + 'T00:00:00'));
  t('🔴 ' + a + ' → ' + z + ': app.html và PHP dựng CÙNG một chuỗi kỳ',
    js === dsPhp[k] && !!js, { js, php: dsPhp[k] });
});

/* Và chuỗi ấy phải đọc ngược ra được — nếu không thì `chuyen_ky()` chối chính cái kỳ mình vừa
   dựng, và `khoang_ky()` trả rỗng cho mọi báo cáo. */
const doc = cp.spawnSync('php', ['-r', `
$goc = ${JSON.stringify(GOC)};
require $goc . '/tools/test/wp-stub.php';
vhcp_test_boot( $goc . '/wordpress/vhcp-chi-phi' );
$ra = array();
foreach ( json_decode( ${JSON.stringify(JSON.stringify(CA.map(c => kyRange(new Date(c[0] + 'T00:00:00'), new Date(c[1] + 'T00:00:00')))))}, true ) as $ky ) {
  $ra[] = VHCP_Don::khoang_ky( $ky );
}
echo json_encode( $ra );
`], { encoding: 'utf8' });
let dsDoc = [];
try { dsDoc = JSON.parse((doc.stdout || '').trim()); } catch (e) { t('đọc ngược trả JSON được', false, doc.stdout + doc.stderr); }
CA.forEach((c, k) => {
  t('🔴 chuỗi do app.html dựng, PHP đọc ngược ra đúng ' + c[0] + '→' + c[1],
    dsDoc[k] && dsDoc[k][0] === c[0] && dsDoc[k][1] === c[1], dsDoc[k]);
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: hai nơi dựng chuỗi kỳ vẫn ra cùng một kết quả.');
