/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * HÀNG BÁN THEO MÁY + SALE VÉ / BÁN LẺ / SALE PHỤ — MÀN PHẢI NỐI ĐÚNG VÀO LÕI.
 *
 * Anh Thắng 23/09/2026: *"hiện số lượng hàng bán và thành tiền để nhân viên kiểm… nếu lệch nhân viên
 * mới nhập, đúng rồi thì để nguyên… để kế toán xác nhận"*; *"tách giúp anh 2 ô là tiền sale vé và
 * tiền sale bán lẻ"*; *"Tiền Sale Phụ"*; *"thêm cấu hình tích trong cấu hình"*.
 *
 * `kiem-hang-ban-chot.php` chạy thật phần tính. Bài này canh:
 *   · tab Nhập báo cáo: bảng hàng bán (SL máy, thành tiền, ô SL thực, lệch), ba ô Sale vé / bán lẻ /
 *     phụ; chỉ gửi dòng có gõ số VÀ khác máy; ô trống = đúng máy;
 *   · tab Đối soát: ba cột tiền + cột "Hàng bán" (khớp máy / N món lệch / chưa soát);
 *   · tab Quản trị: khối nhóm món với cột tích Sale vé? và ô số "Sale phụ mỗi vé (đ)", POST nhom-ve
 *     kèm nhom_phu dạng { nhóm: đ/vé }.
 *
 * Chạy: node tools/test/kiem-hang-ban-chot-man.js
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
t('ba ô Sale vé / Sale bán lẻ / Sale phụ ở hàng số máy',
  /o_pos\('Sale vé \(POS\)'/.test(nap) && /o_pos\('Sale bán lẻ \(POS\)'/.test(nap) && /o_pos\('Sale phụ \(POS\)'/.test(nap));
t('napBaoCao gọi veHangBan(p, b)', /veHangBan\(p, b\)/.test(nap));
const hb = boc('veHangBan');
t('có veHangBan', hb.length > 0);
t('bảng có SL máy, Thành tiền, SL thực, Lệch', /<th>SL máy<\/th><th>Thành tiền<\/th><th>SL thực \(nếu lệch\)<\/th><th>Lệch<\/th>/.test(hb));
t('ô SL thực data-thuc, placeholder = số máy', /data-thuc="' \+ esc\(m\.n\) \+ '" value="' \+ v \+ '" placeholder="' \+ nguyen\(m\.q\)/.test(hb));
t('thẻ điện thoại: lớp bang-the ở khung bọc và data-nhan từng ô', /class="bang-the bang-cuon"/.test(hb) && /data-nhan="SL máy"/.test(hb));
t('nói rõ "Đúng máy thì để trống"', /Đúng máy thì để trống/.test(hb));
const dmt = boc('docMonThuc');
t('có docMonThuc', dmt.length > 0);
t('🔴 chỉ gửi dòng có gõ số VÀ khác máy', /if \(w === ''\) return;/.test(dmt) && /v === may\) return;/.test(dmt));
t('bỏ số âm / không phải số', /isNaN\(v\) \|\| v < 0/.test(dmt));
t('luuBaoCao gửi mon_thuc', /fd\.append\('mon_thuc', JSON\.stringify\(docMonThuc\(\)\)\)/.test(boc('luuBaoCao')));

/* ---- tab Đối soát ---- */
t('đối soát có ba cột tiền', /<th>Sale vé<\/th><th>Bán lẻ<\/th><th>Sale phụ<\/th>/.test(js));
t('và cột Hàng bán', /<th>Hàng bán<\/th>/.test(js));
t('🔴 ô Hàng bán phân biệt lệch / khớp máy / chưa soát', /món lệch<\/b>/.test(js) && /'khớp máy'/.test(js) && /chưa soát/.test(js));
t('dòng chưa nhập báo cáo trải đủ 6 cột', /colspan="6" class="chua"/.test(js) && !/colspan="5" class="chua"/.test(js));

/* ---- tab Quản trị ---- */
const nv = boc('veNhomVe');
t('có veNhomVe và Quản trị gọi taiNhomVe', nv.length > 0 && /taiNhomVe\(o\);/.test(boc('taiQuanTri')));
t('cột tích Sale vé? và cột số "Sale phụ mỗi vé (đ)"', /<th>Sale vé\?<\/th>/.test(nv) && /<th>Sale phụ mỗi vé \(đ\)<\/th>/.test(nv));
t('🔴 ô phụ là input NUMBER (đ/vé), không còn checkbox', /type="number"[^>]*data-nhom-phu=/.test(nv) && !/type="checkbox"[^>]*data-nhom-phu=/.test(nv));
t('gửi nhom_phu dạng { nhóm: đ/vé }, chỉ số > 0', /var ds = \[\], dsPhu = \{\};/.test(nv) && /if \(v > 0\) dsPhu\[c\.dataset\.nhomPhu\] = v;/.test(nv));
t("🔴 Lưu POST nhom-ve gửi cả nhom_ve và nhom_phu", /fd\.append\('nhom_ve', JSON\.stringify\(ds\)\)/.test(nv) && /fd\.append\('nhom_phu', JSON\.stringify\(dsPhu\)\)/.test(nv));
t("🔴 và kèm cua_hang — cấu hình khai riêng từng quán", /fd\.append\('cua_hang', r\.cua_hang \|\| cauHinhCS\(\)\)/.test(nv));
t('khối nhóm có ô chọn cửa hàng chung và GET theo cửa hàng', /oChonCS\('nvCS', r\)/.test(nv) && /api\('nhom-ve\?cua_hang='/.test(boc('taiNhomVe')));
/* 24/09/2026 anh Thắng: "chỗ set Sale Phụ anh không thấy" — lỗi tải khối phải hiện ra, và có link dẫn từ tab Nhập. */
t('🔴 lỗi tải hai khối cấu hình hiện ra (khoiLoi), không nuốt bằng catch rỗng', /catch\(function \(e\) \{ khoiLoi\(o, 'dtNhomVe'/.test(js) && /catch\(function \(e\) \{ khoiLoi\(o, 'dtVeKhach'/.test(js));
t('tab Nhập báo cáo có link "Quản trị → Sale vé / Bán lẻ / Sale phụ" cho quản trị', /id="bcSangCauHinh"/.test(nap) && /Quản trị → Sale vé \/ Bán lẻ \/ Sale phụ/.test(nap));
t('bấm link là mở Quản trị đúng cửa hàng đang nhập', /S\.cauHinhCS = ch;\s*doiTab\('quantri'\)/.test(nap));
t('nói rõ đang thừa bảng chung khi quán chưa khai riêng', /đang thừa bảng chung/.test(nv));
t('nói rõ bán lẻ = phần còn lại, phụ = số vé × tiền phụ mỗi vé (ví dụ combo 80k có 20k phụ)', /phần còn lại/.test(nv) && /số vé × tiền phụ mỗi vé/.test(nv) && /20000/.test(nv));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: hàng bán soát tại chỗ, ba ô tiền tách theo nhóm đã tích.');
