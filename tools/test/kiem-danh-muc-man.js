/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DANH MỤC HÀNG HOÁ FABi — MÀN PHẢI NỐI ĐÚNG VÀO LÕI.
 *
 * Anh Thắng 25/09/2026: *"Tạo hàng mới trên FABi mà chưa bán… Tạo thêm tải danh sách hàng hoá xuống. Cho phép xoá hàng
 * sai trên kho hàng."* `kiem-danh-muc.php` chạy thật máy chủ. Bài này canh:
 *   · Quản trị: khối #dtDanhMuc — nạp file (POST danh-muc, FormData 'file'), tải CSV, xoá (xoa=1), bảng theo nhóm;
 *   · tab Kho: "Thêm mặt hàng mới" là ô CHỌN trong r.danh_muc (đúng quán), chọn là điền mã, "Khác" mới gõ; combo chưa
 *     bán vào ô chọn combo với nhãn "(FABi chưa bán)"; nút xoá cho người được ghi;
 *   · Bóc tách vé: dòng vé chưa bán mang chip "FABi chưa bán — khai trước".
 *
 * Chạy: node tools/test/kiem-danh-muc-man.js
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

t('taiQuanTri gọi taiDanhMuc(o); hỏng thì khoiLoi', /taiDanhMuc\(o\);/.test(boc('taiQuanTri')) && /api\('danh-muc'\)/.test(boc('taiDanhMuc')) && /khoiLoi\(o, 'dtDanhMuc'/.test(boc('taiDanhMuc')));
const vd = boc('veDanhMuc');
t('🔴 khối #dtDanhMuc: ô file #dmFile (.xlsx/.csv), nút Nạp #dmNap, Tải CSV #dmTai, Xoá #dmXoa (chỉ khi đã có)', /id="dtDanhMuc"/.test(vd) && /id="dmFile" accept="\.xlsx,\.xlsm,\.csv,\.tsv,\.txt"/.test(vd) && /id="dmNap"/.test(vd) && /ds\.length \? '<button class="vien" type="button" id="dmTai">/.test(vd) && /id="dmXoa"/.test(vd));
t('nạp: FormData file -> POST danh-muc, vẽ lại, báo số món mới / không còn', /fd\.append\('file', f, f\.name\)/.test(vd) && /api\('danh-muc', \{ method: 'POST', body: fd \}\)/.test(vd) && /món mới so với bản trước/.test(vd) && /món không còn/.test(vd));
t('tải CSV có BOM, 7 cột Mã món · Tên · Nhóm · Loại · ĐVT · Giá · Cửa hàng', /\\ufeff/.test(vd) && /\['Mã món', 'Tên', 'Nhóm', 'Loại', 'ĐVT', 'Giá', 'Cửa hàng'\]/.test(vd) && /danh-muc-hang-hoa-fabi\.csv/.test(vd));
t('xoá: hỏi xác nhận rồi POST xoa=1', /window\.confirm\('Xoá danh mục hàng hoá FABi/.test(vd) && /fd\.append\('xoa', '1'\)/.test(vd));
t('bảng món theo nhóm trong <details>, thẻ điện thoại .the-ct', /Xem ' \+ ds\.length \+ ' món theo nhóm/.test(vd) && /class="bang-cuon bang-the the-ct"/.test(vd));
t('chú thích nói đúng tên file FABi và cột', /update item in store/.test(vd) && /Tên nhóm · Tên loại/.test(vd));

const mh = boc('veKhoMatHang');
t('🔴 tab Kho: có danh mục -> ô chọn #mhThemChon liệt kê món của quán chưa có ở đây (hàng / vé-combo), "Khác — gõ tên…"; không có -> ô gõ như cũ', /id="mhThemChon"/.test(mh) && /<optgroup label="Hàng hoá">/.test(mh) && /<optgroup label="Vé \/ combo">/.test(mh) && /<option value="__khac__">Khác — gõ tên…<\/option>/.test(mh) && /: '<label class="o">Thêm mặt hàng mới<input type="text" id="mhThemTen"/.test(mh));
t('món đã có trong danh mục kho / đã bán không bày lại (so lỏng)', /ten\.indexOf\(x\.ten\) < 0 && !ten\.some\(function \(t\) \{ return long_\(t\) === long_\(x\.ten\); \}\)/.test(mh));
t('option mang data-ma; chưa nạp danh mục thì nhắc văn phòng nạp ở Quản trị', /data-ma="' \+ esc\(x\.ma \|\| ''\)/.test(mh) && /Chưa nạp <b>Danh sách hàng hoá FABi<\/b>/.test(mh));
const nk = boc('noiKho');
t('🔴 chọn trong danh mục -> điền #mhThemMa từ data-ma; "Khác" -> mở ô tên; Thêm lấy tên từ ô chọn', /mhSel\.addEventListener\('change'/.test(nk) && /opt\.getAttribute\('data-ma'\)/.test(nk) && /o2\.hidden = mhSel\.value !== '__khac__'/.test(nk) && /var tenMoi = \(chonDm && chonDm !== '__khac__'\) \? chonDm :/.test(nk));
const cb = boc('veKhoCombo');
t('🔴 ô chọn combo có combo trong danh mục chưa bán, nhãn "(FABi chưa bán)"; thành phần lấy thêm hàng danh mục khi chưa chọn danh mục kho', /dmCombo = \(r\.danh_muc \|\| \[\]\)\.filter\(function \(x\) \{ return x\.combo; \}\)/.test(cb) && /\(FABi chưa bán\)/.test(cb) && /if \(!x\.ve && !x\.combo && !mon\.some/.test(cb));
t('Bóc tách vé: vé chưa bán mang chip "FABi chưa bán — khai trước"', /x\.chua_ban \? ' · <span class="chip">FABi chưa bán — khai trước<\/span>'/.test(boc('veVeKhach')));

/* chạy thật veKhoMatHang với danh mục: 2 món chưa có ở quán, 1 món đã có */
const than = mh + "\n  var S = { cf: {} };\n  function esc(s) { return String(s == null ? '' : s).replace(/[&<>\"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;' }[c]; }); }\n  function nguyen(n) { return String(Math.round(n || 0)); }\n  return veKhoMatHang;";
let ve = null;
try { ve = new Function(than)(); } catch (e) { hong.push('không nạp được veKhoMatHang: ' + e.message); }
if (ve) {
  const h = ve({ mon_da_thay: { 'BIM BIM NHỎ': 10 }, mat_hang: ['BIM BIM NHỎ'], ma_hang: {}, duoc_ghi: true, danh_muc_luc: '2026-09-25 10:00:00',
    danh_muc: [{ ten: 'Bim bim  nhỏ', ma: 'MNKVCDS007', ve: false, combo: false }, { ten: 'ĐÙI GÀ PHÔ MAI', ma: 'MNKVCDS099', ve: false, combo: false }, { ten: 'VÉ TUTU TRAIN: VÉ MỚI 2026', ma: 'MNKVCTT060', ve: true, combo: false }] });
  t('🔴 chạy thật: "Bim bim  nhỏ" (lỏng trùng món đã có) không bày; ĐÙI GÀ ở nhóm Hàng hoá với data-ma; VÉ MỚI ở nhóm Vé / combo; đếm "2 món chưa có ở đây"', !/value="Bim bim  nhỏ"/.test(h) && /<optgroup label="Hàng hoá"><option value="ĐÙI GÀ PHÔ MAI" data-ma="MNKVCDS099">/.test(h) && /<optgroup label="Vé \/ combo"><option value="VÉ TUTU TRAIN: VÉ MỚI 2026"/.test(h) && /\(2 món chưa có ở đây\)/.test(h));
  t('nhắc "Danh mục FABi nạp 2026-09-25 10:00"', /Danh mục FABi nạp 2026-09-25 10:00/.test(h));
  const h0 = ve({ mon_da_thay: {}, mat_hang: [], ma_hang: {}, duoc_ghi: true, danh_muc: [] });
  t('chưa nạp danh mục -> ô gõ tên + nhắc nạp', /id="mhThemTen"/.test(h0) && !/id="mhThemChon"/.test(h0) && /Chưa nạp <b>Danh sách hàng hoá FABi<\/b>/.test(h0));
}

console.log(hong.length ? '✗ HỎNG ' + hong.length + ' / ' + (dat + hong.length) + ' phép:\n  · ' + hong.join('\n  · ')
  : '✓ SẠCH — ' + dat + ' phép: danh mục FABi nối vào Quản trị, sổ kho, combo và bóc tách vé.');
process.exit(hong.length ? 1 : 0);
