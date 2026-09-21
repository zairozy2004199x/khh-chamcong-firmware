/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BỎ MÀN TRÙNG, VÀ BỎ CƠ SỞ TRÙNG TÊN.
 *
 * Anh Thắng 10/09/2026, chỉ vào từng khối: *"bỏ bị trùng"* (danh sách cơ sở ở thẻ 🆕 Khai mã) ·
 * *"loại bỏ không cần thiết, bị trùng"* (khối 🏷 Loại chi phí theo cơ sở) · *"Đã xong, loại bỏ"*
 * (khối 🧹 Dọn bù trừ cũ) · *"LOẠI BỎ, THỪA"* (khối 🔗 Đăng nhập từ trang tổng).
 *
 * =============================================================================================
 * 🔴 BA MÀN CÙNG MỘT VIỆC LÀ BA NƠI KHAI LỆCH NHAU. Khối 🏷 làm đúng việc mà thẻ 🆕 đã làm —
 *    khai mã riêng cho từng gian. Người dùng phải đoán nên vào màn nào, và hai màn ghi cùng
 *    một chỗ thì cái sau đè cái trước mà chẳng ai biết.
 *
 * 🔴 CHỈ BỎ MÀN, KHÔNG BỎ DỮ LIỆU. Mã đã khai riêng cho từng gian vẫn phải còn, và vẫn phải
 *    sửa được ở thẻ 🆕. Bỏ luôn đường máy chủ là mã nằm trong sổ mà không ai với tới.
 *
 * 🔴 CƠ SỞ TRÙNG TÊN HIỆN HAI Ô TÍCH. Bảng Cơ sở gom từ hai nguồn (khai tay · hút từ trang
 *    Ghế) nên một cơ sở rất dễ có hai dòng. Người khai tích một cái rồi tưởng xong, mà mã chỉ
 *    áp cho nửa số dòng.
 *
 * ⚠️ CHẠY THẬT `kcFill()` bốc từ mã nguồn.
 *
 * Chạy: node tools/test/kiem-bo-man-trung.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(ten, ok, them) { if (ok) { DAT++; return; }
  TRUOT.push(ten + (them !== undefined ? '\n      → ' + JSON.stringify(them) : '')); }

const GOC = path.resolve(__dirname, '..', '..');
const HTML = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');
const API  = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/includes/class-vhcp-api.php'), 'utf8');
const boc = ten => {
  const i = HTML.indexOf('function ' + ten + '(');
  t('bốc được ' + ten + '()', i >= 0);
  return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
};

/* ── 1. KHỐI 🏷 ĐÃ BỎ, BỎ SẠCH ─────────────────────────────────────────────────────────── */
t('🔴 khối "Loại chi phí theo cơ sở" đã bỏ khỏi màn', HTML.indexOf('id="lccsCard"') < 0);
t('   và không còn mảnh nào của nó sót lại (sót là nổ khi mở Cấu hình)',
  HTML.indexOf('lccs') < 0, (HTML.match(/lccs\w*/g) || []).slice(0, 8));
/* Đếm CẢ HAI nơi: danh sách hàm được phép gọi, và bảng ánh xạ hàm. Rơi khỏi danh sách cho
   phép là API chối thẳng, mà bảng ánh xạ vẫn còn nên đọc mã nguồn qua loa thì tưởng vẫn chạy. */
const dem = (h, x) => (h.match(new RegExp("'" + x + "'", 'g')) || []).length;
t('🔴 nhưng ĐƯỜNG MÁY CHỦ vẫn còn — mã đã khai riêng cho từng gian không mất',
  dem(API, 'datLoaiChoCoSo') >= 2 && dem(API, 'loaiCuaCoSo') >= 2,
  { dat: dem(API, 'datLoaiChoCoSo'), doc: dem(API, 'loaiCuaCoSo') });
/* Việc ấy nay làm ở thẻ 🆕: tích lẻ từng cơ sở rồi gửi trong `cosos`. */
const kc = boc('khaiChiPhi');
t('🔴 thẻ 🆕 vẫn khai được mã RIÊNG cho từng cơ sở (nếu không thì bỏ khối kia là mất việc)',
  kc.indexOf('cosos:ch.cosos') >= 0 && kc.indexOf('khaiChiPhiChoCoSo(rec)') >= 0, kc);
t('   và vẫn chối khi chưa tích cơ sở nào',
  kc.indexOf('if(!ch.cosos.length && !ch.mangs.length)') >= 0, kc);


/* ── 1b. HAI KHỐI DÙNG XONG / THỪA ─────────────────────────────────────────────────────── */
/* 🧹 Dọn bù trừ cũ: việc dọn MỘT LẦN cho sổ trước bản 1.53.0. Xong rồi mà để lại thì chỉ tổ
   mời người ta bấm vào một việc không còn gì để làm. */
t('🔴 khối "Dọn bù trừ cũ" đã bỏ khỏi màn', HTML.indexOf('id="btCuCard"') < 0);
t('   bỏ sạch, không sót mảnh nào', HTML.indexOf('btCuKq') < 0 && HTML.indexOf('doDoBtCu') < 0
  && HTML.indexOf('btcu-ck') < 0, (HTML.match(/btCu\w*|btcu-\w+/g) || []).slice(0, 8));
t('   nhưng đường máy chủ còn, phòng sổ cũ nào lòi ra thêm',
  (API.match(/'donBuTruCu'/g) || []).length >= 2, (API.match(/'donBuTruCu'/g) || []).length);
/* 🔗 SSO: vai trò đã có đường ánh xạ tự động từ trang tổng. */
t('🔴 khối "Đăng nhập từ trang tổng (SSO)" đã bỏ khỏi màn', HTML.indexOf('id="ssoCard"') < 0);
t('   bỏ sạch cả hàm lẫn lời gọi', HTML.indexOf('ssoBody') < 0 && HTML.indexOf('renderSso') < 0
  && HTML.indexOf('saveSso') < 0, (HTML.match(/sso\w*/gi) || []).slice(0, 8));
/* 🔴 CHỈ BỎ MÀN. Dòng ngoại lệ đã khai vẫn có hiệu lực — bỏ dữ liệu là đổi quyền của người
   đang dùng mà không ai bấm gì cả. */
t('🔴 các dòng SSO đã khai VẪN CÒN hiệu lực ở máy chủ (bỏ là đổi quyền người khác trong im lặng)',
  API.indexOf("'saveConfig'") >= 0);

/* ── 2. DANH SÁCH CƠ SỞ: BỎ TRÙNG — CHẠY THẬT ──────────────────────────────────────────── */
function chay(coso) {
  const KHO = {};
  const moi = {
    CFG: { coso: coso, loaiChiPhi: [] },
    el: id => (KHO[id] = KHO[id] || { innerHTML: '', value: '', style: {} }),
    esc: x => String(x == null ? '' : x),
    _bpSel: () => '<select></select>',
    kcInfo: () => {},
  };
  new Function('moi', `with(moi){ ${boc('kcFill')} kcFill(); }`)(moi);
  return KHO['kcCosoBox'].innerHTML;
}
{
  const h = chay([
    { ten: 'AEON MALL BÌNH TÂN', phanLoaiLon: '' },
    { ten: 'AEON MALL BÌNH TÂN', phanLoaiLon: '' },        // trùng y hệt
    { ten: '  aeon mall bình tân ', phanLoaiLon: '' },     // trùng, khác hoa/thường + khoảng trắng
    { ten: 'FZ MN', phanLoaiLon: 'Funzone' },
    { ten: 'FZ MN', phanLoaiLon: 'Funzone' },              // trùng trong cùng mảng
    { ten: 'BÌNH DƯƠNG', phanLoaiLon: 'Funzone' },
  ]);
  const dem = s => (h.match(new RegExp('data-coso="' + s + '"', 'g')) || []).length;
  t('🔴 cơ sở lẻ trùng y hệt → chỉ MỘT ô tích', dem('AEON MALL BÌNH TÂN') === 1, h.match(/data-coso="[^"]*"/g));
  t('🔴 trùng chỉ vì hoa/thường hay khoảng trắng thừa → cũng chỉ MỘT',
    (h.match(/data-coso="[^"]*aeon[^"]*"/gi) || []).length === 1, h.match(/data-coso="[^"]*"/gi));
  t('   và GIỮ cách viết của dòng ĐẦU (dòng người ta khai tay)',
    h.indexOf('data-coso="AEON MALL BÌNH TÂN"') >= 0, h.match(/data-coso="[^"]*"/g));
  t('🔴 trùng trong cùng một mảng cũng bỏ', dem('FZ MN') === 1, h.match(/data-coso="[^"]*"/g));
  t('   mảng vẫn đếm đúng số cơ sở sau khi bỏ trùng (2, không phải 3)',
    /Funzone<\/span> <span[^>]*>2</.test(h) || h.indexOf('>2</span></label>') >= 0, h.match(/>\d+<\/span>/g));
  t('   cơ sở KHÁC tên thì vẫn giữ đủ', h.indexOf('data-coso="BÌNH DƯƠNG"') >= 0);
  /* Lối tích lẻ phải được VẼ RA THẬT, không chỉ có chữ ấy đâu đó trong tệp. Bỏ khối 🏷 rồi thì
     đây là chỗ duy nhất còn khai mã riêng cho một gian. */
  t('🔴 màn vẫn vẽ ra lối tích lẻ từng cơ sở (chỗ duy nhất còn khai riêng cho một gian)',
    h.indexOf('id="kcLeBox"') >= 0 && h.indexOf('onclick="kcToggleLe()"') >= 0, h.slice(-400));
  /* Cơ sở TRONG mảng cũng phải xếp — mở "ngoại lệ: tích lẻ" ra là một danh sách dài y như thế. */
  const trongMang = (h.slice(h.indexOf('kcLeBox')).match(/data-coso="([^"]*)"/g) || []).map(x => x.slice(11, -1));
  t('🔴 cơ sở trong từng mảng cũng được sắp xếp',
    JSON.stringify(trongMang) === JSON.stringify(['BÌNH DƯƠNG', 'FZ MN']), trongMang);
}
/* Cơ sở tên rỗng thì bỏ, không đẻ ô tích vô danh. */
{
  const h = chay([{ ten: '   ', phanLoaiLon: '' }, { ten: 'A', phanLoaiLon: '' }]);
  t('tên cơ sở để trống → không đẻ ô tích vô danh',
    (h.match(/data-coso="[^"]*"/g) || []).length === 1, h.match(/data-coso="[^"]*"/g));
}
/* Sắp xếp cho dễ tìm — bảy chục cơ sở mà để lộn xộn thì dò bằng mắt rất lâu. */
{
  const h = chay([
    { ten: 'ZONE C', phanLoaiLon: '' }, { ten: 'AEON', phanLoaiLon: '' }, { ten: 'MEGA', phanLoaiLon: '' },
  ]);
  const ds = (h.match(/data-coso="([^"]*)"/g) || []).map(x => x.slice(11, -1));
  t('🔴 danh sách cơ sở lẻ được sắp xếp (bảy chục cái mà lộn xộn thì dò rất lâu)',
    JSON.stringify(ds) === JSON.stringify(['AEON', 'MEGA', 'ZONE C']), ds);
}

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: bỏ màn trùng mà không mất việc, bỏ cơ sở trùng mà không mất cơ sở.');
