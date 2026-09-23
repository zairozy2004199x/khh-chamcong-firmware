/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MTĐ RA WEB RIÊNG — RỜI THANH KHỐI, NHƯNG SỔ CŨ KHÔNG ĐƯỢC BIẾN MẤT
 *
 * Anh Thắng 22/09/2026: *"Hiện tại chi phí máy tự động áp dụng web riêng nên không dùng chung
 * nữa, em đưa lại phiên bản cũ giúp"*. Hỏi lại thì anh chọn *"Gỡ thanh KHỐI, giữ tính năng"*,
 * và về khối VP thì *"Chưa chốt, để nguyên VP đã"*.
 *
 * =============================================================================================
 * 🔴 CHỖ DỄ HỎNG NHẤT KHÔNG PHẢI LÀ GỠ NÚT — LÀ GIẤU MẤT SỔ
 * =============================================================================================
 * Mọi màn trong app đều lọc theo `KHOI_DANG`. Bỏ 'mtd' khỏi thanh khối mà kho này VẪN CÒN đơn
 * MTĐ (chứng từ kế toán, có đơn chưa xuất MISA) là chúng rơi khỏi giao diện dù dữ liệu còn
 * nguyên — đúng cảnh anh Thắng gặp hôm 11/09/2026 rồi tưởng mất dữ liệu.
 *
 * Nên luật ở đây có HAI vế, và bài này canh cả hai:
 *   · kho SẠCH đơn MTĐ  → nút MTĐ biến mất;
 *   · kho CÒN đơn MTĐ   → nút MTĐ còn, mang nhãn "🗄 … sổ cũ", mở đọc và xuất nốt được.
 *
 * ⚠️ VÀ `KHOI_DS` KHÔNG ĐƯỢC XOÁ 'mtd'. Nó là TỪ ĐIỂN mã→tên, dùng để ĐỌC dữ liệu cũ. Xoá là
 *    đơn cũ hiện ra với mã trần 'mtd' thay vì "Máy tự động".
 *
 * Chạy: node tools/test/kiem-mtd-ra-web-rieng.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const DON = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-don.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }

const HAM = ['_khoiMo', '_khoiLuuTru', '_khoiBay', '_laLuuTru', '_khoiDuoc'];
HAM.forEach(h => t('⚠️ bốc được `' + h + '`', bocHam(h).length > 20, h));
const NEN = bocDong('KHOI_DS') + '\n' + bocDong('KHOI_MO') + '\n' + HAM.map(bocHam).join('\n');

/** Chạy thật mấy hàm chia khối, với danh sách "khối còn đơn" cho trước. */
function be(khoiCoDon, khoiXem) {
  return new Function('BOOT', 'window', NEN
    + '\nreturn { mo:_khoiMo, luu:_khoiLuuTru, bay:_khoiBay, la:_laLuuTru, duoc:_khoiDuoc };')(
    { khoiCoDon: khoiCoDon, khoiXem: khoiXem || null },
    { BOOT: { khoiCoDon: khoiCoDon, khoiXem: khoiXem || null } });
}
const ma = (ds) => ds.map(x => x.ma);

/* ═══ 1. TỪ ĐIỂN GIỮ NGUYÊN, TẬP "CÒN MỞ" MỚI LÀ THỨ ĐỔI ══════════════════════════ */
t('🔴 `KHOI_DS` VẪN đủ ba khối — nó là từ điển để đọc sổ cũ',
  /kvc/.test(bocDong('KHOI_DS')) && /mtd/.test(bocDong('KHOI_DS')) && /vp/.test(bocDong('KHOI_DS')),
  bocDong('KHOI_DS'));
t('🔴 `KHOI_MO` KHÔNG còn mtd — bản gốc thôi nhận đơn MTĐ mới',
  !/'mtd'/.test(bocDong('KHOI_MO')), bocDong('KHOI_MO'));
t('   nhưng vẫn còn vp — anh Thắng bảo "chưa chốt, để nguyên VP đã"',
  /'vp'/.test(bocDong('KHOI_MO')), bocDong('KHOI_MO'));
teq('🔴 khối còn mở = KVC + VP', ['kvc', 'vp'], ma(be([]).mo()));

/* ═══ 2. KHO SẠCH ĐƠN MTĐ → NÚT BIẾN MẤT ════════════════════════════════════════ */
{
  const B = be(['kvc', 'vp']);
  teq('🔴 kho không còn đơn MTĐ → thanh khối chỉ bày KVC + VP', ['kvc', 'vp'], ma(B.bay()));
  teq('   và không có khối lưu trữ nào', [], ma(B.luu()));
}

/* ═══ 3. KHO CÒN ĐƠN MTĐ → NÚT CÒN, NHƯNG LÀ "SỔ CŨ" ════════════════════════════
 * 🔴 VẾ NÀY LÀ LÝ DO CẢ BÀI TỒN TẠI. Bỏ nó là quay lại đúng kiểu hỏng "tưởng mất dữ liệu". */
{
  const B = be(['kvc', 'mtd', 'vp']);
  teq('🔴 kho CÒN đơn MTĐ → nút MTĐ vẫn bày ra', ['kvc', 'vp', 'mtd'], ma(B.bay()));
  teq('   và nó nằm ở nhóm LƯU TRỮ, không phải nhóm đang mở', ['mtd'], ma(B.luu()));
  t('   `_laLuuTru` gọi đúng tên: mtd là sổ cũ', B.la('mtd') === true);
  t('   còn kvc/vp thì không', B.la('kvc') === false && B.la('vp') === false);
  t('⚠️ không phân biệt hoa thường', B.la('MTD') === true && B.la('KVC') === false);
}

/* ═══ 4. CHỐT ĐƠN VỊ VẪN CẮT ĐÈ LÊN TRÊN ════════════════════════════════════════
 * ⚠️ `_khoiDuoc()` lọc tập BÀY RA theo `BOOT.khoiXem`. Đổi nó thành lọc `KHOI_DS` là khối đã
 *    ra riêng lại bấm được ngay cả trên kho chẳng còn đơn nào của nó. */
{
  teq('🔴 tài khoản chỉ xem KVC → chỉ còn KVC', ['kvc'], ma(be(['kvc', 'mtd'], ['kvc']).duoc()));
  teq('🔴 tài khoản xem cả hệ → đủ tập bày ra', ['kvc', 'vp', 'mtd'], ma(be(['kvc', 'mtd', 'vp'], null).duoc()));
  teq('🔴 khai xem "mtd" nhưng kho sạch → không có nút nào của mtd',
    [], ma(be(['kvc'], ['mtd']).duoc()));
}

/* ═══ 5. CÒN MỘT KHỐI THÌ ẨN CẢ THANH ═══════════════════════════════════════════
 * Ngày VP cũng ra riêng thì thanh còn một nút, luôn bật, bấm không đi đâu. */
{
  const v = bocHam('veThanhKhoi');
  t('🔴 `veThanhKhoi` ẩn thanh khi tập bày ra còn dưới 2 khối',
    /if\(bay\.length<2\)\{[^}]*display='none'/.test(v), v.slice(v.indexOf('var bay='), v.indexOf('var bay=') + 260));
  t('   và dựng nút từ `bay`, không từ `KHOI_DS`',
    /o\.innerHTML=bay\.map\(/.test(v) && !/o\.innerHTML=KHOI_DS\.map\(/.test(v), v);
  t('   vẫn vẽ lại menu ▾ trước khi thoát sớm (hai chỗ không được lệch)',
    /if\(bay\.length<2\)[\s\S]{0,200}?veMenuKhoi\('duyet'\); veMenuKhoi\('qt'\)/.test(v), v);
  t('🔴 menu ▾ trên tab cũng dựng từ `_khoiBay()`',
    /o\.innerHTML=_khoiBay\(\)\.map\(/.test(bocHam('veMenuKhoi')), bocHam('veMenuKhoi'));
}

/* ═══ 6. NÚT SỔ CŨ PHẢI NÓI RA NÓ LÀ SỔ CŨ ══════════════════════════════════════
 * 🔴 Bày một nút y hệt hai nút kia là mời người ta lập đơn mới vào một khối không còn ai vận
 *    hành, rồi đơn ấy nằm lại một mình ở kho sai. */
{
  const v = bocHam('veThanhKhoi');
  t('🔴 nút của khối lưu trữ có nhãn riêng', /_laLuuTru\(x\.ma\)/.test(v), v);
  t('   mang dấu 🗄 và chữ "sổ cũ"', /🗄/.test(v) && /sổ cũ/.test(v), v);
  t('   và title dặn đừng lập đơn mới', /đừng lập đơn mới/.test(v), v);
  t('   nhưng VẪN bấm được để đọc/xuất nốt',
    /_laLuuTru\(x\.ma\)\)\{[\s\S]{0,400}?onclick="doiKhoi/.test(v), v);
}

/* ═══ 7. MÁY CHỦ PHẢI GỬI "KHỐI NÀO CÒN ĐƠN" ════════════════════════════════════
 * ⚠️ Không có khoá này thì `_khoiLuuTru()` luôn rỗng, và vế "kho còn sổ" ở mục 3 thành vô
 *    nghĩa — nút MTĐ biến mất kể cả khi còn đơn. Hỏng đúng theo hướng nguy hiểm nhất. */
t('🔴 gói khởi động gửi `khoiCoDon`', /'khoiCoDon'\s*=>\s*self::khoi_con_don\(\)/.test(DON), 'không thấy');
t('🔴 có hàm `khoi_con_don()`', /public static function khoi_con_don\(\)/.test(DON));
/* ⚠️ "TOÀN KHO, KHÔNG THEO NGƯỜI ĐANG XEM" KHÔNG CANH ĐƯỢC TỪ ĐÂY. Phép cũ dò chuỗi
   `SELECT DISTINCT khoi FROM $t`; thêm ` WHERE nguoi_lap = %s` vào chính câu ấy là biến nó
   thành câu hỏi theo người, mà chuỗi kia vẫn nằm nguyên — đột biến SỐNG SÓT. Chốt ấy chuyển
   sang `kiem-khoi-con-don.php`, chạy thật với sổ thật và ba vai khác nhau.
   Ở đây chỉ giữ một phép nhẹ: câu truy vấn KHÔNG có mệnh đề WHERE. Nó bắt được đúng cái đột
   biến kia, nhưng đừng nhầm nó với phép chốt — phép chốt nằm bên PHP. */
{
  const than = (DON.match(/public static function khoi_con_don\(\)[\s\S]{0,700}?\n\t\}/) || [''])[0];
  t('⚠️ bốc được thân `khoi_con_don()`', than.length > 100, than.length);
  t('🔴 câu truy vấn không có WHERE (chốt thật ở kiem-khoi-con-don.php)',
    /SELECT DISTINCT khoi FROM \$t/.test(than) && !/WHERE/i.test(than), than);
}
/* 🔴 Ô `khoi` RỖNG PHẢI TÍNH LÀ 'kvc' — cùng luật với mặc định của cột và với `lap_khoi()`.
   Bỏ qua chúng là một nhúm đơn cũ không khối nào nhận. */
t('🔴 đơn chưa có dấu khối tính là kvc',
  /khoi_con_don[\s\S]{0,400}?if \( '' === \$k \) \{ \$k = 'kvc'; \}/.test(DON));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(x => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: MTĐ rời thanh khối, nhưng kho còn sổ thì nút còn, gắn nhãn sổ cũ.');
