/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * Ô VAI TRÒ THEO CHA – CON: CHỌN CHA THÌ CHỈ RA CON CỦA CHA.
 *
 * Anh Thắng 21/09/2026: *"chỗ vai trò và bộ phận theo cha - con"*, rồi nói rõ hơn:
 * *"chọn cha thì chỉ ra con của cha"*. Anh vẽ sẵn cây:
 *     Admin
 *     Nhân Viên  ├ Nhân Viên Cơ Sở Khu Vui Chơi · Nhân Viên Kỹ Thuật Máy Tự Động …
 *     Quản Lý    ├ Quản Lý Khu Vui Chơi · Quản Lý Chung …
 *
 * =============================================================================================
 * 🔴 BA CHỖ HỎNG ĐƯỢC, CẢ BA ĐỀU IM LẶNG
 * =============================================================================================
 * 1. Ô CHA LÀ Ô LÁI, KHÔNG MANG GIÁ TRỊ. `_readRows()` đọc theo THỨ TỰ ô — đếm thêm một ô là
 *    mọi cột sau nó lùi một chỗ: vai trò hoá bộ phận, bộ phận hoá cơ sở. Cả bảng người dùng
 *    ghi lệch, không một câu lỗi nào.
 * 2. VAI LẠ PHẢI GIỮ NGUYÊN. Nếu ai đó vừa xoá một vai mà ô chọn không còn dòng ấy, mở bảng
 *    lên là vai của người ta lặng lẽ nhảy sang dòng đầu — và bấm Lưu một cái là phong nhầm
 *    hàng loạt. Dòng đầu ở đây là **Admin**.
 * 3. ĐỔI CHA PHẢI DỰNG LẠI CON. Giữ nguyên con của cha cũ là gán một người vào vai thuộc
 *    nhánh khác — tức nhánh quyền khác — mà nhìn màn thì thấy đúng.
 *
 * Chạy: node tools/test/kiem-vai-cha-con.js
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

const TEN = ['_vaiGocCua', '_vaiConCua', '_vaiConHtml', '_roleSel', '_vaiChaDoi'];
TEN.forEach(function (x) { t('bốc được `' + x + '`', bocHam(x).length > 30, x); });

/* ═══ 1. 🔴 Ô CHA PHẢI BỊ `_readRows()` BỎ QUA ═══════════════════════════════════ */
t('🔴 `_readRows()` bỏ qua ô lái `.vai-cha`',
  /select:not\(\.vai-cha\)/.test(bocHam('_readRows')), bocHam('_readRows'));
t('   và ô cha thật sự mang lớp ấy', /class="vai-cha"/.test(bocHam('_roleSel')));

/* ── bệ đỡ ────────────────────────────────────────────────────────────────────── */
const VAI_GOC = ['Quản lý', 'Kế toán cá nhân', 'Kế toán NCC', 'Nhân viên'];
const VAITRO = [
  { ten: 'Nhân Viên Cơ Sở Khu Vui Chơi', goc: 'Nhân viên' },
  { ten: 'Nhân Viên Kỹ Thuật Máy Tự Động', goc: 'Nhân viên' },
  { ten: 'Quản Lý Khu Vui Chơi', goc: 'Quản lý' },
  { ten: 'Quản Lý Chung', goc: 'Quản lý' },
];
const vm = require('vm');
function moi() {
  const ctx = { CFG: { vaiTro: VAITRO }, VAI_GOC: VAI_GOC,
    esc: (x) => String(x == null ? '' : x).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;') };
  vm.createContext(ctx);
  vm.runInContext(TEN.map(bocHam).join('\n'), ctx);
  return ctx;
}
const M = moi();
/** Các <option> của ô con trong chuỗi HTML `_roleSel` trả về. */
function conCua(h) {
  const i = h.indexOf('</select>') + 9;            // bỏ ô cha
  return (h.slice(i).match(/<option[^>]*>([^<]*)</g) || []).map(x => x.replace(/<option[^>]*>/, '').replace('<', ''));
}
function chaChon(h) {
  const oCha = h.slice(0, h.indexOf('</select>'));
  const m = oCha.match(/<option value="([^"]*)" selected>/);
  return m ? m[1] : null;
}

/* ═══ 2. MỞ MỘT NGƯỜI ĐANG MANG VAI CON ══════════════════════════════════════════ */
let h = M._roleSel('Nhân Viên Kỹ Thuật Máy Tự Động');
teq('🔴 cha tự suy ra đúng nhánh của vai con', 'Nhân viên', chaChon(h));
teq('   ô con chỉ có con của nhánh ấy + chính vai gốc', 3, conCua(h).length);
t('   và vai đang khai được chọn sẵn',
  /<option value="Nhân Viên Kỹ Thuật Máy Tự Động" selected>/.test(h), h);
t('🔴 con của nhánh KHÁC không lọt vào', h.indexOf('Quản Lý Chung') < 0, conCua(h));

/* ═══ 3. NGƯỜI MANG VAI GỐC ══════════════════════════════════════════════════════ */
h = M._roleSel('Quản lý');
teq('cha là chính vai gốc ấy', 'Quản lý', chaChon(h));
teq('   ô con có 1 dòng "không chia nhỏ" + 2 con của Quản lý', 3, conCua(h).length);
t('   và dòng "không chia nhỏ" đang được chọn',
  /<option value="Quản lý" selected>— Quản lý \(không chia nhỏ\) —/.test(h), h);

/* Admin không có con nào -> ô con chỉ một dòng. */
h = M._roleSel('Admin');
teq('🔴 Admin không có vai con: ô con đúng 1 dòng', 1, conCua(h).length);
teq('   và cha là Admin', 'Admin', chaChon(h));

/* ═══ 4. 🔴 VAI LẠ KHÔNG ĐƯỢC LẶNG LẼ NHẢY SANG DÒNG ĐẦU ═════════════════════════
 * Dòng đầu của ô cha là **Admin**. Nuốt mất vai lạ là bấm Lưu một cái phong Admin cho cả loạt. */
h = M._roleSel('Vai đã bị xoá');
t('🔴 vai lạ vẫn hiện nguyên trong ô con', h.indexOf('Vai đã bị xoá') > 0, h);
t('   và nói rõ nó đã bị xoá', h.indexOf('(vai đã xoá)') > 0, h);
/* Ô cha chọn dòng rỗng — tức "— không rõ —". Điều phải canh là nó KHÔNG phải 'Admin'
   (dòng đầu của danh sách), vì nhảy sang đó là phong Admin sau một cú bấm Lưu. */
teq('🔴 cha giữ ở "— không rõ —"', '', chaChon(h));
t('🔴 và tuyệt đối KHÔNG nhảy sang Admin', chaChon(h) !== 'Admin', chaChon(h));
t('   cha hiện "— không rõ —"', h.indexOf('— không rõ —') > 0, h);
t('   ô con viền đỏ để người khai thấy ngay', h.indexOf('border-color:#c0392b') > 0, h);

/* ═══ 5. 🔴 ĐỔI CHA THÌ DỰNG LẠI CON — CHẠY THẬT ═════════════════════════════════ */
function oGiaCap(vaiDangKhai) {
  const con = { innerHTML: '', value: '', style: { borderColor: '' } };
  const cha = { value: '', className: 'vai-cha' };
  cha.parentNode = { querySelector: (sel) => (sel === 'select:not(.vai-cha)' ? con : null) };
  con.innerHTML = M._vaiConHtml(M._vaiGocCua(vaiDangKhai) || vaiDangKhai, vaiDangKhai);
  return { cha, con };
}
let o = oGiaCap('Nhân Viên Cơ Sở Khu Vui Chơi');
o.cha.value = 'Quản lý';
M._vaiChaDoi(o.cha);
const ds = (o.con.innerHTML.match(/<option[^>]*>([^<]*)</g) || []).map(x => x.replace(/<option[^>]*>/, '').replace('<', ''));
teq('🔴 đổi cha sang Quản lý: ô con chỉ còn con của Quản lý',
  ['— Quản lý (không chia nhỏ) —', 'Quản Lý Khu Vui Chơi', 'Quản Lý Chung'], ds);
t('🔴 và con của nhánh CŨ biến hẳn', o.con.innerHTML.indexOf('Nhân Viên Cơ Sở') < 0, o.con.innerHTML);
t('   mặc định về chính vai gốc, không giữ vai nhánh cũ',
  /<option value="Quản lý" selected>/.test(o.con.innerHTML), o.con.innerHTML);
t('   và bỏ viền đỏ nếu trước đó là vai lạ', o.con.style.borderColor === '');

/* Cha "— không rõ —" (vai lạ) thì ĐỪNG tự sửa gì — người khai chưa quyết. */
o = oGiaCap('Vai đã bị xoá');
const truoc = o.con.innerHTML;
o.cha.value = '';
M._vaiChaDoi(o.cha);
teq('🔴 cha để "— không rõ —" thì KHÔNG tự đụng vào ô con', truoc, o.con.innerHTML);

/* ═══ 6. GIÁ TRỊ LƯU XUỐNG VẪN LÀ MỘT CHUỖI VAI TRÒ NHƯ CŨ ═══════════════════════
 * Cả thay đổi này chỉ là CÁCH BÀY. Đụng vào sơ đồ hay vào máy chủ là chuyện khác hẳn. */
t('🔴 không thêm cột nào vào gói lưu người dùng',
  /vaiTro:r\[3\]\|\|'Nhân viên'/.test(bocHam('saveCfgUsers')), bocHam('saveCfgUsers'));

/* ═════════════════════════════════════════════════════════════════════════════════════════ */
if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach(function (x) { console.log('  · ' + x); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: chọn cha chỉ ra con của cha, vai lạ không bị nuốt, cột không lệch.');
