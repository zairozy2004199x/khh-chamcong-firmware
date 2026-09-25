/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * THỢ NỀN CỦA APP CHI PHÍ — CHẠY THẬT PHÉP PHÂN LOẠI ĐƯỜNG GỌI.
 *
 * Anh Thắng 22/09/2026: *"Xong chuyển vào app điện thoại để chạy giao diện điện thoại nhé"*.
 *
 * =============================================================================================
 * 🔴 VÌ SAO PHẢI CHẠY THẬT, KHÔNG SOI CHỮ
 * =============================================================================================
 * `kiem-app-dien-thoai-chi-phi.php` soi rằng ba cổng dữ liệu CÓ được nhắc trong `sw.js`. Phá thử
 * 22/09/2026 cho thấy phép ấy không đủ: đổi `return u.searchParams.has('vhcp_api')` thành
 * `return false && u.searchParams.has('vhcp_api')` thì chữ vẫn còn nguyên, phép vẫn xanh — mà
 * thợ nền từ đó NHỚ HỘ mọi lượt hỏi dữ liệu.
 *
 * Hậu quả không phải là trang hỏng. Là kế toán mở app ra thấy bảng tiền của lần trước và TIN NÓ:
 * đơn vừa gửi trông như biến mất, tổng chi hiện con số cũ. Một bảng tiền nói sai tệ hơn hẳn một
 * bảng tiền không mở được — cái sau ai cũng thấy, cái trước thì không.
 *
 * ⚠️ BỐC HÀM THẬT TỪ `sw.js` RA CHẠY, không chép lại luật. Chép là hai bản luật, và bản trong bài
 *    kiểm không bao giờ sai nên chẳng canh được gì.
 *
 * Chạy: node tools/test/kiem-tho-nen-chi-phi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path'), vm = require('vm');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }

const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

BAN.forEach(function (ban) {
  const f = path.join(GOC, 'wordpress', ban, 'assets/js/sw.js');
  if (!fs.existsSync(f)) { t('có ' + ban + '/assets/js/sw.js', false); return; }
  const JS = fs.readFileSync(f, 'utf8');
  /* Tiền tố chữ thường của bản vùng — `tools/tach-ban-vung.sh` đổi vhcp -> vhcp<mã>. */
  const low = (ban === 'vhcp-chi-phi') ? 'vhcp' : 'vhcp' + ban.slice('vhcp-chi-phi-'.length);

  const boc = function (ten) {
    const i = JS.indexOf('function ' + ten + '(');
    t(ban + ': bốc được ' + ten + '()', i >= 0);
    return i < 0 ? '' : JS.slice(i, JS.indexOf('\n}', i) + 2);
  };
  /* 🔴 THAY `__GOC_TINH__` ĐÚNG NHƯ PHP LÀM. Thợ nền so địa chỉ tệp với thư mục plugin do máy
     chủ nhét vào; để nguyên chỗ trống là `laTinh()` trả `false` cho tất cả, và mọi phép mục 3
     đỏ oan. Thay ở đây cũng chính là phép canh: nếu ai gỡ lượt `str_replace` bên PHP thì thợ
     nền thật chạy với chuỗi `__GOC_TINH__` — và hàm tự bảo vệ bằng cách thôi nhớ (xem `laTinh`). */
  const GOC_TINH = '/wp-content/plugins/' + ban + '/';
  const ctx = { URL: URL, self: { location: { href: 'https://khmatrix.com/chi-phi-kvc/sw.js' } } };
  vm.createContext(ctx);
  vm.runInContext("var GOC_TINH=" + JSON.stringify(GOC_TINH) + ";\n"
    + boc('laDuLieu') + '\n' + boc('laTinh'), ctx);
  const goc = 'https://khmatrix.com';
  const du = function (u) { return ctx.laDuLieu(new URL(u, goc)); };
  const tinh = function (u) { return ctx.laTinh(new URL(u, goc)); };

  /* ═══ 1. 🔴 BA CỔNG DỮ LIỆU ĐỀU PHẢI BỊ CHỪA RA ═══════════════════════════════════
   * App có ba đường gọi máy chủ (xem `VHCP_App::head_block`): /wp-json/, admin-ajax.php, và
   * chính URL trang kèm `<tiền tố>_api` — cổng cuối sinh ra vì Cloudflare hay chặn hai cổng
   * trước. Bỏ sót cổng nào là bảng tiền hiện số của lần mở trước. */
  t('🔴 ' + ban + ': cổng REST bị chừa ra', du('/wp-json/' + low + '/v1/call'), '');
  t('🔴 ' + ban + ': cổng admin-ajax bị chừa ra', du('/wp-admin/admin-ajax.php?action=' + low + '_call'), '');
  t('🔴 ' + ban + ': cổng trên chính URL trang bị chừa ra',
    du('/chi-phi-kvc/?' + low + '_api=1'), '');
  t('   kể cả khi nó đi kèm tham số khác', du('/chi-phi-kvc/?ve=tram&' + low + '_api=1'), '');

  /* ═══ 2. PHÉP ĐỐI CHỨNG — KHÔNG ĐƯỢC CHỪA NHẦM MỌI THỨ ═══════════════════════════
   * Một hàm trả `true` cho tất cả thì bốn phép trên xanh hết mà thợ nền chẳng nhớ gì, tức lớp
   * vỏ app vô dụng. Phải có đường KHÔNG phải dữ liệu. */
  t('🔴 ' + ban + ': trang app KHÔNG bị coi là đường dữ liệu', !du('/chi-phi-kvc/'), '');
  t('   tệp tĩnh cũng không', !du('/wp-content/plugins/' + ban + '/assets/css/vhcp.css?ver=1'), '');

  /* ═══ 3. TỆP TĨNH CỦA CHÍNH BỘ NÀY THÌ NHỚ HẲN ═══════════════════════════════════
   * Chúng mang `?ver=` đổi theo số bản: địa chỉ đổi là lượt nhớ cũ thành vô dụng, không bao giờ
   * đưa nhầm bản cũ. */
  t('🔴 ' + ban + ': css/js/ảnh của bộ này được nhớ hẳn',
    tinh('/wp-content/plugins/' + ban + '/assets/css/vhcp.css?ver=1.2')
    && tinh('/wp-content/plugins/' + ban + '/assets/js/gas-shim.js')
    && tinh('/wp-content/plugins/' + ban + '/assets/img/app/bieu-tuong-192.png'), '');
  /* 🔴 ĐỐI CHỨNG: đừng nhớ hẳn tệp của plugin KHÁC — bản của họ đi đường riêng, và nhớ hẳn một
     tệp không có `?ver=` là giữ mãi bản cũ của người ta. */
  t('🔴 ' + ban + ': KHÔNG nhớ hẳn tệp của plugin khác',
    !tinh('/wp-content/plugins/mot-plugin-khac/assets/app.js'), '');
  t('   và không nhớ hẳn trang HTML', !tinh('/chi-phi-kvc/'), '');
  t('   cũng không nhớ hẳn tệp lạ trong chính bộ này',
    !tinh('/wp-content/plugins/' + ban + '/includes/class-vhcp-app.php'), '');
  /* 🔴 PHP CHƯA THAY CHỖ TRỐNG → THÀ KHÔNG NHỚ GÌ. Nhớ hẳn theo một chuỗi vô nghĩa là hoặc
     không khớp gì (vô hại), hoặc khớp bậy (giữ mãi tệp của người khác). Thà chắc chắn. */
  {
    const c2 = { URL: URL, self: { location: { href: 'https://khmatrix.com/chi-phi-kvc/sw.js' } } };
    vm.createContext(c2);
    vm.runInContext("var GOC_TINH='__GOC_TINH__';\n" + boc('laTinh'), c2);
    t('🔴 ' + ban + ': PHP chưa thay chỗ trống thì thợ nền thôi nhớ, không đoán bừa',
      !c2.laTinh(new URL('/wp-content/plugins/' + ban + '/assets/css/vhcp.css', goc)), '');
  }

  /* ═══ 4. HAI RỔ KHÔNG ĐƯỢC CHỒNG NHAU ════════════════════════════════════════════
   * Một đường vừa là "dữ liệu" vừa là "tĩnh" thì nhánh nào chạy trước thắng — và luật im lặng
   * đổi nghĩa mỗi lần ai đó sắp lại `fetch`. */
  ['/wp-json/' + low + '/v1/call', '/wp-admin/admin-ajax.php', '/chi-phi-kvc/?' + low + '_api=1']
    .forEach(function (u) {
      t('🔴 ' + ban + ': `' + u + '` chỉ thuộc rổ dữ liệu, không thuộc rổ tĩnh', !tinh(u), '');
    });
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: thợ nền không bao giờ nhớ hộ một lượt hỏi dữ liệu.');
