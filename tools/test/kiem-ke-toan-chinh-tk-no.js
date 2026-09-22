/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô "TK NỢ" CỦA TỪNG DÒNG CHI — PHẦN MÀN HÌNH.
 *
 * Anh Thắng 22/09/2026: *"Sau khi quyết toán, thì kế toán có quyền điều chỉnh tk nợ theo nhu
 * cầu, vì Cùng tên gọi nhưng nội dung khác, Nên lúc tạo đơn nhân viên không cần quan tâm (đến
 * phần quyết toán thì nó mới hiện qua và kế toán chọn các số lập sẵn và bấm quyết toán là
 * xong)"*.
 *
 * Phần LUẬT (ai đổi được · mã nào nhận · ô trống thì sao) canh ở `kiem-ke-toan-chinh-tk-no.php`,
 * chạy thật hàm máy chủ. Bài này canh đúng phần máy chủ không thấy: Ô CHỌN BÀY RA CÁI GÌ.
 *
 * 🔴 BA CHUYỆN, VÀ CẢ BA ĐỀU IM LẶNG NẾU HỎNG:
 *   1) ô chỉ bày "các số lập sẵn" — không phải ô gõ tự do;
 *   2) mã của CHÍNH LOẠI ẤY lên trước (gần như luôn là mã đúng, kế toán không phải dò);
 *   3) mã đang nằm trên dòng mà nay không còn trong Cấu hình VẪN hiện — không thì mở ô chọn
 *      lên là nó tự nhảy về "tự động", và một lần bấm hụt ra ngoài là mã cũ mất.
 *
 * Chạy: node tools/test/kiem-ke-toan-chinh-tk-no.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
const API = fs.readFileSync('wordpress/vhcp-chi-phi/includes/class-vhcp-api.php', 'utf8');

let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
function bocHam(ten) { const i = HTML.indexOf('  function ' + ten + '('); if (i < 0) return ''; const j = HTML.indexOf('\n  }', i) + 4; return j > i ? HTML.slice(i, j) : ''; }

/* ═══ 0. CỔNG API PHẢI GÁC — nếu không thì cả chốt vai bên lõi cũng chỉ là lớp thứ nhất ═══ */
t('🔴 `setLineTkNo` có trong sổ hàm của cổng API',
  /'setLineTkNo'\s*=>\s*array\(\s*'VHCP_Don',\s*'set_line_tk_no'\s*\)/.test(API));
t('🔴 và nằm trong danh sách CHỈ NGƯỜI DUYỆT gọi được',
  /'setLineNhom',\s*'setLineTkNo'/.test(API));

/* ═══ 1. Ô CHỌN BÀY ĐÚNG "CÁC SỐ LẬP SẴN" ═══════════════════════════════════════════════ */
const HAM = ['_tenTk', '_tkNoCuaLoai', '_tkNoTatCa', '_tkOpt', '_oTkNoDong'];
HAM.forEach(function (h) { t('⚠️ bốc được `' + h + '`', bocHam(h).length > 20, h); });
const NEN = HAM.map(bocHam).join('\n');

const BOOT = {
  tkNoMx: {
    'chi phí khác': { 'farm': ['64166'], 'tutu': ['64106'] },
    'chi phí điện nước': { 'farm': ['64127'] },
  },
  tenTk: { 64166: 'CP khác - Farm', 64106: 'CP khác - TuTu', 64127: 'Điện nước' },
};
function ve(dong) {
  return new Function('BOOT', 'TKNAME', 'esc', NEN + '\nreturn _oTkNoDong(' + JSON.stringify(dong) + ');')(
    BOOT, null, (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'));
}
const giaTri = (h) => (h.match(/value="[^"]*"/g) || []).map((x) => x.slice(7, -1));

const h1 = ve({ id: 'L1', nhom: 'Chi phí khác', tkNo: '64166' });
/* ⚠️ XẾP THEO SỐ, không theo thứ tự khai. Bảng Cấu hình khai theo mảng, nên thứ tự khai là
   thứ tự người ta gõ — kế toán dò mã thì dò theo con số. */
teq('🔴 bày ĐÚNG những mã đã khai, cộng ô trống "tự động", xếp theo số',
  ['', '64106', '64166', '64127'], giaTri(h1));
t('🔴 mã của CHÍNH loại ấy nhóm riêng và đứng trước',
  h1.indexOf('Mã của loại này') >= 0
  && h1.indexOf('Mã của loại này') < h1.indexOf('Mã khác đã khai'), h1);
t('   và mã của loại khác nằm ở nhóm sau',
  h1.indexOf('>64127') > h1.indexOf('Mã khác đã khai'), h1);
t('🔴 "— tự động theo loại —" là một lựa chọn THẬT, không chỉ là nhãn',
  /<option value="">— tự động theo loại —<\/option>/.test(h1), h1);
t('   mã đang dùng được chọn sẵn', /value="64166"\s+selected/.test(h1), h1);
t('   kèm TÊN tài khoản cho đỡ phải dò', /64166\s+CP khác - Farm/.test(h1), h1);
t('🔴 bấm là gọi thẳng `saveLineTkNo` với đúng id dòng',
  /saveLineTkNo\('L1',this\.value\)/.test(h1), h1);

/* ⚠️ Ô GÕ TỰ DO LÀ ĐƯỜNG CHO MÃ MA. "Các số lập sẵn" là chữ của anh Thắng, và cũng là chốt. */
t('🔴 là ô CHỌN, không phải ô gõ', /^<select/.test(h1) && !/<input/.test(h1), h1.slice(0, 60));

/* ═══ 2. MÃ CŨ KHÔNG CÒN TRONG CẤU HÌNH VẪN PHẢI HIỆN ═══════════════════════════════════
 * 🔴 Không thì mở ô chọn lên là nó tự nhảy về "tự động", và một cú bấm hụt ra ngoài là mã cũ
 *    mất — mất im lặng, trên một dòng đã vào sổ. */
const h2 = ve({ id: 'L2', nhom: 'Chi phí khác', tkNo: '64199' });
t('🔴 mã lạ vẫn có mặt trong ô chọn', giaTri(h2).indexOf('64199') >= 0, giaTri(h2));
t('   và được chọn sẵn', /value="64199" selected/.test(h2), h2);
t('   và nói rõ nó không còn trong Cấu hình', /không còn trong Cấu hình/.test(h2), h2);

/* ═══ 3. LOẠI CHƯA KHAI MÃ NÀO — vẫn phải chọn được, đó là cả điểm của lượt sửa này ═══════ */
const h3 = ve({ id: 'L3', nhom: 'Loại mới tinh', tkNo: '' });
t('🔴 loại chưa khai mã vẫn có ô chọn đầy đủ mã của hệ',
  giaTri(h3).indexOf('64166') >= 0 && giaTri(h3).indexOf('64106') >= 0, giaTri(h3));
t('   không bày nhóm "Mã của loại này" rỗng', h3.indexOf('Mã của loại này') < 0, h3);
t('   và mặc định là "tự động"', /<option value="">— tự động theo loại —<\/option>/.test(h3)
  && !/selected/.test(h3), h3);

/* ═══ 4. Ô NÀY CHỈ KẾ TOÁN THẤY ═════════════════════════════════════════════════════════
 * 🔴 `_oLoaiCp()` thoát sớm cho người không phải kế toán — ô TK Nợ phải nằm SAU cái thoát ấy.
 *    Vẽ nó cho mọi vai là người nhập đổi được mã hạch toán, đúng thứ `set_line_nhom()` đã
 *    cấm từ 18/09. Máy chủ vẫn chối, nhưng bày ra một nút bấm xong báo lỗi là tệ theo kiểu
 *    khác: người ta tưởng app hỏng. */
const oLoai = bocHam('_oLoaiCp');
t('⚠️ bốc được `_oLoaiCp`', oLoai.length > 100);
t('🔴 ô TK Nợ vẽ SAU cửa thoát "không phải kế toán"',
  oLoai.indexOf('if(!_laKeToan()){') >= 0
  && oLoai.indexOf('_oTkNoDong(l)') > oLoai.indexOf('if(!_laKeToan()){'), oLoai.indexOf('_oTkNoDong(l)'));
t('   và cửa thoát ấy KHÔNG hề gọi `_oTkNoDong`',
  oLoai.slice(oLoai.indexOf('if(!_laKeToan()){'), oLoai.indexOf('var ds=_loaiCpList')).indexOf('_oTkNoDong') < 0);

/* ═══ 5. LƯU XONG PHẢI NÓI RA ĐÃ THÀNH MÃ NÀO ═══════════════════════════════════════════ */
const luu = bocHam('saveLineTkNo');
t('⚠️ bốc được `saveLineTkNo`', luu.length > 100);
t('🔴 gọi đúng hàm cổng `setLineTkNo`', /\.setLineTkNo\(id, val\)/.test(luu), luu);
t('🔴 ghi nhật ký (đây là mã hạch toán, phải có vết)', /_log\(/.test(luu), luu);
t('🔴 báo lại mã mới, và báo riêng khi trả về tự động',
  /r\.tkNo \? \(/.test(luu) && /tự động/.test(luu), luu);
/* ⚠️ PHÉP NÀY TỪNG VÔ DỤNG: nó tìm `openDon(CUR.don.maDon)`, mà chuỗi ấy nằm ngay trong định
   nghĩa `var lai=…` ở đầu hàm — nên xoá lời GỌI `lai()` ở nhánh thành công vẫn xanh. Đột biến
   đã sống sót đúng một lượt vì thế. Nay soi chính LỜI GỌI, ở cả ba nhánh. */
t('🔴 nhánh THÀNH CÔNG nạp lại đơn (không thì màn nói một đằng, sổ một nẻo)',
  /toast\('ok', r\.tkNo[^;]*\);\s*lai\(\);/.test(luu), luu);
t('🔴 nhánh máy chủ CHỐI cũng nạp lại — ô chọn không được đứng ở giá trị chưa lưu được',
  /if\(!r\|\|!r\.success\)\{[^}]*lai\(\); return; \}/.test(luu), luu);
t('   và `lai` thật sự mở lại đơn đang xem', /var lai=function\(\)\{[^}]*openDon\(CUR\.don\.maDon\)/.test(luu), luu);
t('⚠️ nhánh MẤT MẠNG cũng nạp lại',
  /withFailureHandler\(function\(e\)\{loading\(false\);toast\('err',e\.message\);lai\(\);\}\)/.test(luu), luu);

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô TK Nợ của dòng chi bày đúng các số lập sẵn, chỉ kế toán thấy.');
