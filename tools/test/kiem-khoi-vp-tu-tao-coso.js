/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * KHỐI VĂN PHÒNG PHẢI TỰ TẠO ĐƯỢC CƠ SỞ ĐẦU TIÊN CỦA NÓ.
 *
 * Anh Thắng 22/09/2026: *"Tạo Khối VP (cho tự tạo, do VP không có cơ sở)"*.
 *
 * =============================================================================================
 * 🔴 MỘT CÁI VÒNG TỰ KHOÁ CHÍNH NÓ
 * =============================================================================================
 * Bảng Cơ sở gom theo khối, và vòng gom chỉ tạo nhóm cho khối **CÓ** cơ sở. Văn phòng không có
 * gian vật lý nào, nên nó không có nhóm — mà nút "+ Thêm cơ sở" lại nằm trong nhóm. Thành ra
 * Văn phòng KHÔNG CÓ CÁCH NÀO tạo cơ sở đầu tiên của mình.
 *
 * 🔴 VÀ HỘP "+ THÊM CƠ SỞ" KHÔNG CÓ Ô KHỐI. Cột trong bảng đã là "Khối" từ 21/09, còn hộp thêm
 *    vẫn là ô GÕ TAY "Đơn vị" với gợi ý *"để trống = K&H"* — hai chỗ ghi vào CÙNG MỘT ô dữ liệu
 *    (`donVi`) bằng hai kiểu khác nhau. Gõ tay ra một chuỗi không khớp mã khối nào thì cơ sở mới
 *    rơi vào nhóm "(chưa rõ đơn vị)".
 *
 * ⚠️ Cùng lẽ với `_mxNhomDv()` bên bảng mã — ở đó cũng luôn bày đủ ba khối.
 *
 * Chạy: node tools/test/kiem-khoi-vp-tu-tao-coso.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }
function bocMang(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('];', i) + 2); }
function bocDong(ten) { const i = HTML.indexOf('  var ' + ten + '='); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n', i)); }

/* ═══ 1. HỘP THÊM CƠ SỞ CÓ Ô KHỐI ══════════════════════════════════════════════ */
t('🔴 hộp "+ Thêm cơ sở" có ô chọn Khối', /<select id="ncKhoi">/.test(HTML));
t('🔴 và ô gõ tay "Đơn vị" cũ đã gỡ', HTML.indexOf('id="ncDonVi"') < 0);
/* 🔴 TỪ 22/09/2026 Ô NÀY BÀY **MIỀN**, không bày cả từ điển khối — anh Thắng: *"Khối là liên
   quan Miền Bắc và Miền Nam ôi"* · *"Chuyển nó sang là MB hay MN"*. */
t('⚠️ ô khối đổ từ `_mienDs()`, cùng nguồn với cột Khối của bảng',
  /_mienDs\(\)/.test(bocHam('addCfgCoso')) && !/KHOI_DS\.map/.test(bocHam('addCfgCoso')), bocHam('addCfgCoso'));

const NEN = bocMang('KHOI_DS') + '\n' + bocDong('KHOI_DV_DUP') + '\n'
  + "\nvar MIEN_MA=['mb','mn'];\n"
  + ['_khoiDvBang', '_khoiCuaDv', '_tenKhoi', '_mienDs', 'addCfgCoso', 'submitCfgCoso'].map(bocHam).join('\n');
t('⚠️ nền chạy thử dựng được', NEN.replace(/\s/g, '').length > 600, NEN.length);

function themCoSo(khoiSan, khoiDang, ten) {
  const O = {}; const CFG = { coso: [] }; const nhac = [];
  const moi = {
    CFG: CFG, KHOI_DANG: khoiDang, BOOT: { khoiTheoDv: { kvc: ['KVC'], mtd: ['MTĐ', 'POSH'], vp: ['VP'] } },
    esc: (v) => String(v == null ? '' : v),
    el: (id) => (O[id] = O[id] || { value: '', innerHTML: '', style: {}, focus: function () {} }),
    toast: (k, c) => nhac.push(c), closeCfgCoso: () => {}, renderCosoBody: () => {},
    _syncCosoFromDOM: () => {}, setTimeout: () => {},
  };
  const f = new Function('moi', `with(moi){ ${NEN}\n return {mo:addCfgCoso, gui:submitCfgCoso}; }`)(moi);
  f.mo(khoiSan);
  const oKhoi = (O.ncKhoi.innerHTML.match(/value="([^"]*)"/g) || []).map((x) => x.slice(7, -1));
  const chon = (O.ncKhoi.innerHTML.match(/value="([^"]*)" selected/) || [])[1] || '';
  O.ncKhoi.value = chon;
  O.ncTen.value = ten;
  f.gui();
  return { oKhoi: oKhoi, chonSan: chon, coso: CFG.coso, nhac: nhac.join(' ') };
}

const mb = themCoSo('mb', 'kvc', 'VĂN PHÒNG HN');
teq('🔴 ô khối bày hai MIỀN, cộng một ô rỗng "chưa chọn"', ['', 'mb', 'mn'], mb.oKhoi);
/* 🔴 CHỌN SẴN KHỐI CỦA BẢNG VỪA BẤM, không phải khối đang đứng trên thanh KHỐI: trang Cấu hình
   không có thanh ấy, nên `KHOI_DANG` ở đây là giá trị nhớ từ lần trước — chọn theo nó là cơ sở
   mới rơi vào khối người ta không hề nhắm tới. */
teq('🔴 chọn sẵn miền của bảng vừa bấm (mb), KHÔNG phải khối đang đứng (kvc)', 'mb', mb.chonSan);
teq('🔴 cơ sở mới ghi đúng mã miền vào ô `donVi`', 'mb', mb.coso[0].donVi);
teq('   và đúng tên', 'VĂN PHÒNG HN', mb.coso[0].ten);
t('⚠️ câu báo nói rõ vào khối nào', /Miền Bắc/.test(mb.nhac), mb.nhac);

/* ═══ 🔴 CHƯA CHỌN MIỀN THÌ CHỐI, KHÔNG ĐOÁN HỘ ═══════════════════════════════════
 * Bản trước ngã về `KHOI_DANG` — giá trị nhớ từ lần trước, mà trang Cấu hình không có thanh
 * khối để đổi. Gian mới vì thế rơi vào một khối chẳng ai chọn, và chi phí của nó nằm sai bảng
 * mã tài khoản: không câu lỗi nào, chỉ lộ ra lúc đối chiếu với kế toán.
 *
 * ⚠️ Ô RỖNG PHẢI LÀ MỘT OPTION THẬT. Một `<select>` không có option nào `selected` thì trình
 *    duyệt lấy option ĐẦU — "chưa chọn" lặng lẽ hoá thành "Miền Bắc". */
{
  const khong = themCoSo('', 'mtd', 'X');
  teq('🔴 bấm nút chung (không truyền miền) → KHÔNG chọn sẵn gì', '', khong.chonSan);
  teq('🔴 và chưa chọn miền thì KHÔNG thêm cơ sở nào', 0, khong.coso.length);
  t('   kèm câu nhắc, không im lặng', /Chọn miền/.test(khong.nhac), khong.nhac);
  const cu2 = themCoSo('kvc', 'kvc', 'Y');
  teq('🔴 truyền một KHỐI CŨ cũng không được chọn sẵn — phải tự chọn miền', '', cu2.chonSan);
  teq('   và cũng không thêm được cho tới khi chọn', 0, cu2.coso.length);
}

/* ═══ 2. BẢNG BÀY ĐỦ BA KHỐI, KỂ CẢ KHỐI RỖNG ══════════════════════════════════ */
const VE = bocHam('renderCosoBody');
t('⚠️ bốc được `renderCosoBody`', VE.length > 800);
t('🔴 vòng dựng nhóm có bù cho MIỀN chưa có cơ sở nào',
  /_mienDs\(\)\.forEach\(function\(k\)\{[\s\S]{0,400}?dvGr\[k\.ma\]=\[\]; dvOrd\.push\(k\.ma\);/.test(VE), VE.slice(0, 300));
/* ⚠️ Và KHÔNG bù theo cả từ điển khối — ba mã cũ đã ra web riêng, bù chúng là ba nhóm rỗng
   vĩnh viễn nằm trên đầu bảng. */
t('⚠️ không bù theo `KHOI_DS` (từ điển đầy đủ) nữa', !/KHOI_DS\.forEach/.test(VE));
/* ⚠️ Chỉ bù khối người này XEM ĐƯỢC — bày cả ba cho kế toán bị bó một khối là mời họ khai vào
   một bảng mà máy chủ sẽ chối ngay lượt Lưu. */
t('⚠️ chỉ bù khối người này xem được', /if\(!_xemDuocDv\(k\.ma\)\) return;/.test(VE));
/* ⚠️ Đừng bù trùng: khối đã có nhóm mang TÊN ĐƠN VỊ CŨ (VD "POSH") vẫn là khối ấy. */
t('⚠️ không bù trùng khi nhóm đã tồn tại dưới tên đơn vị cũ',
  /dvOrd\.some\(function\(d\)\{ return _khoiCuaDv\(d\)===k\.ma; \}\)/.test(VE));
t('🔴 mỗi nhóm có nút thêm riêng, mang theo mã khối',
  /addCfgCoso\('\+JSON\.stringify\(_maK\)/.test(VE), VE.slice(-600));
t('🔴 nhóm rỗng nói rõ phải làm gì', /Văn phòng thường không có gian vật lý/.test(VE));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: khối chưa có cơ sở vẫn hiện, và tự tạo được cơ sở đầu tiên.');
