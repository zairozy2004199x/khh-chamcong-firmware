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
t('⚠️ ô khối đổ từ `KHOI_DS`, cùng nguồn với thanh KHỐI',
  /KHOI_DS\.map/.test(bocHam('addCfgCoso')), bocHam('addCfgCoso'));

const NEN = bocMang('KHOI_DS') + '\n' + bocDong('KHOI_DV_DUP') + '\n'
  + ['_khoiDvBang', '_khoiCuaDv', '_tenKhoi', 'addCfgCoso', 'submitCfgCoso'].map(bocHam).join('\n');
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

const vp = themCoSo('vp', 'kvc', 'VĂN PHÒNG HCM');
teq('🔴 ô khối có đủ ba khối', ['kvc', 'mtd', 'vp'], vp.oKhoi);
/* 🔴 CHỌN SẴN KHỐI CỦA BẢNG VỪA BẤM, không phải khối đang đứng trên thanh KHỐI: trang Cấu hình
   không có thanh ấy, nên `KHOI_DANG` ở đây là giá trị nhớ từ lần trước — chọn theo nó là cơ sở
   mới rơi vào khối người ta không hề nhắm tới. */
teq('🔴 chọn sẵn khối của bảng vừa bấm (vp), KHÔNG phải khối đang đứng (kvc)', 'vp', vp.chonSan);
teq('🔴 cơ sở mới ghi đúng mã khối vào ô `donVi`', 'vp', vp.coso[0].donVi);
teq('   và đúng tên', 'VĂN PHÒNG HCM', vp.coso[0].ten);
t('⚠️ câu báo nói rõ vào khối nào', /Văn phòng/.test(vp.nhac), vp.nhac);
/* Không truyền khối thì ngã về khối đang đứng — người bấm nút chung ở đầu thẻ. */
teq('⚠️ không truyền khối → theo khối đang đứng', 'mtd', themCoSo('', 'mtd', 'X').coso[0].donVi);

/* ═══ 2. BẢNG BÀY ĐỦ BA KHỐI, KỂ CẢ KHỐI RỖNG ══════════════════════════════════ */
const VE = bocHam('renderCosoBody');
t('⚠️ bốc được `renderCosoBody`', VE.length > 800);
t('🔴 vòng dựng nhóm có bù cho khối chưa có cơ sở nào',
  /KHOI_DS\.forEach\(function\(k\)\{[\s\S]{0,400}?dvGr\[k\.ma\]=\[\]; dvOrd\.push\(k\.ma\);/.test(VE), VE.slice(0, 300));
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
