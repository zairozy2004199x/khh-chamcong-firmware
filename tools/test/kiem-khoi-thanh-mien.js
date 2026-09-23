/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KHỐI Ở CẤU HÌNH NAY LÀ **MIỀN** (MB / MN) — VÀ KHỐI CŨ KHÔNG BỊ ÉP ĐỔI.
 *
 * Anh Thắng 22/09/2026: *"Khối là dùng chung, vì đã phân theo vai trò rồi"* · *"Khối là liên
 * quan Miền Bắc và Miền Nam ôi"* · *"Chuyển nó sang là MB hay MN tương đương với Miền Bắc,
 * Miền Nam"* · *"Khối là để xác định tài khoản nợ"*.
 *
 * =============================================================================================
 * 🔴 HAI TRỤC TÊN GIỐNG NHAU, VÀ TRỘN CHÚNG LÀ HỎNG NGAY
 * =============================================================================================
 *   · `KHOI_MO` / `_khoiBay()` — trục của ĐƠN. Cột `khoi` của một đơn do mã BẢN đóng dấu
 *     (`VHCP_DB::KHOI`), không phải miền. Thanh khối trên màn Đơn chi phí dựng từ đó.
 *   · `_mienDs()` — trục của CẤU HÌNH. Khối của một CƠ SỞ và của một LOẠI CHI PHÍ, và là thứ
 *     bảng mã TK Nợ cắt theo.
 *
 * Nhét MB/MN vào trục đơn là dựng hai cái tab VĨNH VIỄN RỖNG cạnh tab thật. Để trục cấu hình
 * chạy trên cả từ điển khối là bày ba bảng mã rỗng của mấy khối đã ra web riêng. Đã thử cả hai
 * hướng trong cùng một buổi, nên bài này canh cả hai chiều.
 *
 * =============================================================================================
 * 🔴 VÀ CÁI BẪY CHẾT NGƯỜI: Ô CHỌN KHÔNG CÓ OPTION CHO GIÁ TRỊ CŨ
 * =============================================================================================
 * Danh mục đang có hàng chục dòng mang 'kvc' / 'mtd' / 'vp', và cơ sở khai 'KVC' / 'POSH'. Một
 * `<select>` không có option nào ứng với giá trị ấy thì trình duyệt tự chọn option ĐẦU TIÊN —
 * và lượt Lưu kế tiếp ghi 'mb' đè lên MỌI dòng cũ. Không câu lỗi nào: bảng lưu xong vẽ lại
 * trông vẫn bình thường, chỉ là mọi loại nhảy sang một miền và mã TK Nợ của chúng thành vô chủ.
 *
 * Chạy: node tools/test/kiem-khoi-thanh-mien.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs'), path = require('path');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? ('\n      → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }

const GOC = path.resolve(__dirname, '..', '..');
const BAN = ['vhcp-chi-phi', 'vhcp-chi-phi-hn', 'vhcp-chi-phi-mtd', 'vhcp-chi-phi-vp'];

BAN.forEach(function (ban) {
  const fH = path.join(GOC, 'wordpress', ban, 'templates/app.html');
  if (!fs.existsSync(fH)) { t('có ' + ban + '/app.html', false); return; }
  const HTML = fs.readFileSync(fH, 'utf8');
  const pre = ban === 'vhcp-chi-phi' ? 'vhcp-chi-phi' : ban;
  const DONVI = fs.readFileSync(path.join(GOC, 'wordpress', ban, 'includes/class-vhcp-donvi.php'), 'utf8');
  const CFGP = fs.readFileSync(path.join(GOC, 'wordpress', ban, 'includes/class-vhcp-cfg.php'), 'utf8');
  const boc = function (ten) {
    const i = HTML.indexOf('function ' + ten + '(');
    t(ban + ': bốc được ' + ten + '()', i >= 0);
    return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4);
  };
  const bocDong = function (ten) {
    const i = HTML.indexOf('  var ' + ten + '=');
    t(ban + ': bốc được bảng ' + ten, i >= 0);
    return i < 0 ? '[]' : HTML.slice(HTML.indexOf('=', i) + 1, HTML.indexOf('\n', i)).replace(/;\s*$/, '');
  };

  /* ═══ 1. MÁY CHỦ BIẾT HAI MIỀN, VÀ KHÔNG BỎ RƠI BA MÃ CŨ ═════════════════════════ */
  t('🔴 ' + ban + ': `KHOI_THEO_DON_VI` có mb và mn', /'mb' *=> *array\(/.test(DONVI) && /'mn' *=> *array\(/.test(DONVI), '');
  t('🔴 ' + ban + ': và GIỮ ba mã cũ — sổ đang mang chúng',
    /'kvc' *=> *array\(/.test(DONVI) && /'mtd' *=> *array\(/.test(DONVI) && /'vp' *=> *array\(/.test(DONVI), '');
  t('🔴 ' + ban + ': `ten_khoi()` có nhãn cho cả hai miền',
    /'mb' *=> *'Miền Bắc'/.test(CFGP) && /'mn' *=> *'Miền Nam'/.test(CFGP), '');
  /* ⚠️ Miền KHÔNG ánh xạ từ tên đơn vị cũ: một đơn vị có cơ sở ở cả hai miền. Nhận đúng tên
     viết tắt của chính nó, để `khoi_cua()` không gán bừa cơ sở vào một miền. */
  t('⚠️ ' + ban + ': mb/mn chỉ nhận tên viết tắt của chính nó, không cướp tên đơn vị cũ',
    !/'mb' *=> *array\([^)]*(KVC|POSH|MTĐ)/.test(DONVI), (DONVI.match(/'mb' *=> *array\([^)]*\)/) || [''])[0]);

  /* ═══ 2. HAI TRỤC TÁCH RỜI ═══════════════════════════════════════════════════════ */
  const mienMa = JSON.parse(bocDong('MIEN_MA').replace(/'/g, '"'));
  const khoiMo = JSON.parse(bocDong('KHOI_MO').replace(/'/g, '"'));
  teq('🔴 ' + ban + ': trục CẤU HÌNH là hai miền', ['mb', 'mn'], mienMa);
  t('🔴 ' + ban + ': trục ĐƠN (`KHOI_MO`) KHÔNG dính miền — nhét vào là hai tab vĩnh viễn rỗng',
    khoiMo.indexOf('mb') < 0 && khoiMo.indexOf('mn') < 0, khoiMo);

  const ctx = { KHOI_DS: [{ ma: 'mb', ten: 'Miền Bắc' }, { ma: 'mn', ten: 'Miền Nam' },
    { ma: 'kvc', ten: 'Khu vui chơi' }, { ma: 'mtd', ten: 'Máy tự động' }, { ma: 'vp', ten: 'Văn phòng' }],
    MIEN_MA: mienMa,
    esc: function (x) { return String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); },
  };
  const F = new Function('moi', 'with(moi){' + [boc('_mienDs'), boc('_tenKhoi'), boc('_khoiSelLoai')].join('\n')
    + '\nreturn { mien:_mienDs, oLoai:_khoiSelLoai }; }')(ctx);
  teq('🔴 ' + ban + ': `_mienDs()` trả đúng hai miền, không kéo theo khối cũ',
    ['mb', 'mn'], F.mien().map(function (x) { return x.ma; }));

  /* ═══ 3. 🔴 Ô CHỌN KHỐI CỦA LOẠI CHI PHÍ: BÀY MIỀN, GIỮ GIÁ TRỊ CŨ ══════════════ */
  const ops = function (h) {
    return (h.match(/<option value="([^"]*)"( selected)?>/g) || []).map(function (x) {
      return x.slice(15, x.indexOf('"', 15)) + (/ selected/.test(x) ? ' ✓' : '');
    });
  };
  {
    const h = F.oLoai('mb');
    teq('🔴 ' + ban + ': loại đã theo miền → ô chọn bày đúng hai miền', ['mb ✓', 'mn'], ops(h));
    t('   không có dấu nhắc thừa', h.indexOf('khối cũ') < 0, h);
  }
  {
    /* 🔴 PHÉP QUAN TRỌNG NHẤT CỦA BÀI. Không có option 'kvc' thì trình duyệt chọn 'mb', và một
       cú Lưu đổi khối cho MỌI dòng cũ — mã TK Nợ của chúng thành vô chủ, im lặng. */
    const h = F.oLoai('kvc');
    teq('🔴 ' + ban + ': loại còn mang KHỐI CŨ → vẫn có option của nó, và CHỌN SẴN',
      ['mb', 'mn', 'kvc ✓'], ops(h));
    t('   và hiện ra bằng TÊN, không phải mã trần', h.indexOf('>Khu vui chơi<') >= 0, h);
    t('🔴 kèm dấu nhắc để kế toán tự đổi — máy không ép đổi khối của ai',
      h.indexOf('khối cũ') >= 0 && h.indexOf('Miền Bắc / Miền Nam') >= 0, h);
  }
  {
    teq('   loại CHƯA khai khối thì không đẻ option lạ', ['mb', 'mn'], ops(F.oLoai('')));
  }

  /* ═══ 4. BẢNG MÃ TK Nợ CẮT THEO MIỀN, NHƯNG KHÔNG BỎ RƠI KHỐI CŨ CÒN MẢNG ═══════ */
  const VE = boc('_mxNhomDv').replace(/\/\*[\s\S]*?\*\//g, ' ');
  t('🔴 ' + ban + ': `_mxNhomDv()` dựng mục theo `_mienDs()`, không theo cả từ điển khối',
    /var bay=_mienDs\(\);/.test(VE) && !/KHOI_DS\.map\(function\(k\)\{ return \{ dv:k\.ma/.test(VE), VE.slice(0, 400));
  /* 🔴 Khối cũ còn mảng PHẢI giữ mã khối của nó. Trả '' là mục ấy rơi về `KHOI_DANG` ở chỗ lọc
     loại — bảng mã của khối này bày loại của khối kia, và mã gõ vào đó ghi sang khối khác. */
  t('🔴 ' + ban + ': khối cũ còn mảng giữ nguyên mã khối, không trả rỗng',
    /khoi:\(KHOI_DS\.some\(function\(x\)\{ return x\.ma===k; \}\) \? k : ''\)/.test(VE), VE.slice(-400));

  /* ═══ 5. CƠ SỞ: Ô KHỐI CŨNG LÀ MIỀN, VÀ CŨNG GIỮ GIÁ TRỊ CŨ ════════════════════ */
  const CS = boc('_khoiSelCoso');
  t('🔴 ' + ban + ': ô Khối của cơ sở bày `_mienDs()`', /var ds=_mienDs\(\);/.test(CS), CS.slice(0, 300));
  t('🔴 và giữ option cho khối cũ đọc ra được', /var la=\(k && !ds\.some/.test(CS), CS.slice(0, 500));
  const THEM = boc('addCfgCoso');
  t('🔴 ' + ban + ': hộp thêm cơ sở có option rỗng "chưa chọn" — không để trình duyệt chọn hộ',
    /— chọn miền —/.test(THEM), THEM);
  const GUI = boc('submitCfgCoso');
  t('🔴 và lượt gửi CHỐI khi chưa chọn miền, thay vì đoán theo khối đang đứng',
    /if\(!khoiMoi\)\{ toast\('warn'/.test(GUI) && !/\|\|String\(KHOI_DANG\|\|'kvc'\)/.test(GUI), GUI.slice(0, 800));
});

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: khối ở Cấu hình là miền, hai trục tách rời, khối cũ không bị ép đổi.');
