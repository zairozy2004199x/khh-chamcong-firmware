/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * BÓC TÁCH VÉ → KHÁCH — MÀN PHẢI DÙNG ĐƯỢC CÁI LÕI ĐÃ CÓ.
 *
 * `kiem-ve-khach.php` chạy thật phần tính. Bài này canh ba màn nối vào nó:
 *   · tab Nhập báo cáo bày "Khách vào (POS)" từ `khach_may`, bày "—" khi null (không bày 0), kể tên
 *     vé chưa bóc tách, và lệch so với KHÁCH máy khi có bóc tách (lùi về số vé khi chưa có);
 *   · tab Đối soát cột "Khách − máy" dùng `khach_may` trước, `so_ve` sau;
 *   · tab Quản trị có khối Bóc tách vé: chọn cơ sở (mặc định gian Tàu), ô khách mỗi vé, gợi ý,
 *     Lưu gọi POST ve-khach.
 *
 * Chạy: node tools/test/kiem-ve-khach-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.js', 'utf8');
const js = src.replace(/\/\*[\s\S]*?\*\//g, ' ').replace(/(^|[^:'"])\/\/[^\n]*/g, '$1');
let dat = 0; const hong = [];
function t(ten, dk) { if (dk) dat++; else hong.push(ten); }
function boc(ten) {
  const i = js.indexOf('  function ' + ten + '(');
  if (i < 0) return '';
  const j = js.indexOf('\n  }\n', i);
  return js.slice(i, j + 4);
}

/* ---- tab Nhập báo cáo ---- */
const nap = boc('napBaoCao');
t('bày ô "Khách vào (POS)"', /o_pos\('Khách vào \(POS\)'/.test(nap));
t("🔴 null thì bày '—', không bày 0", /p\.khach_may != null \? nguyen\(p\.khach_may\) : '—'/.test(nap));
t('kể tên vé chưa bóc tách (ve_chua_tach)', /ve_chua_tach/.test(nap) && /chưa bóc tách/.test(nap));
const lech = boc('tinhLech');
t('🔴 lệch so với khách máy khi có bóc tách', /var may = p\.khach_may != null \? Math\.round\(p\.khach_may\) : ve;/.test(lech));
t('và dùng biến ấy để tính', /khach - may/.test(lech));
t('nhãn nói rõ "đã bóc tách vé"', /đã bóc tách vé/.test(lech));

/* ---- tab Đối soát ---- */
t('cột đối soát đổi tên "Khách − máy"', /<th>Khách − máy<\/th>/.test(js));
t('🔴 ô đối soát ưu tiên khach_may, lùi về so_ve', /x\.khach_may != null \? x\.khach_may : x\.so_ve/.test(js));

/* ---- tab Quản trị ---- */
const tai = boc('taiVeKhach'), ve = boc('veVeKhach');
t('có taiVeKhach / veVeKhach', tai.length > 0 && ve.length > 0);
t('Quản trị gọi taiVeKhach', /taiVeKhach\(o\);/.test(boc('taiQuanTri')));
t('mặc định chọn gian Tàu trước', /t\[àa\]u\|train/i.test(tai));
t("GET ve-khach theo cơ sở", /api\('ve-khach\?cua_hang='/.test(tai));
t('ô nhập khách mỗi vé data-vk', /data-vk=/.test(ve));
t('placeholder mang gợi ý', /gợi ý ' \+ x\.goi_y/.test(ve));
t('nút điền gợi ý vào ô trống', /vkGoiY/.test(ve) && /gợi ý \(\\d\+\)/.test(ve));
t("🔴 Lưu gọi POST ve-khach với 'bang' JSON", /api\('ve-khach', \{ method: 'POST'/.test(ve) && /fd\.append\('bang', JSON\.stringify\(b\)\)/.test(ve));
t('nói rõ khai theo tên món, dùng chung mọi cơ sở', /dùng chung cho mọi cơ sở/.test(ve));
t('nói rõ 0 khác ô trống', /Ô để trống = không tính/.test(ve));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: màn bày khách máy, lệch so khách máy, Quản trị khai được bóc tách.');
