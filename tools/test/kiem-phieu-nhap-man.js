/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * PHIẾU NHẬP HÀNG — MÀN PHẢI NỐI ĐÚNG VÀO LÕI.
 * Anh Thắng 25/09/2026: *"Tạo phiếu nhập hàng, khi có phiếu nhập hàng nhập vào hoặc đẩy lên nó sẽ đẩy vào dữ liệu kho hàng"*.
 * `kiem-phieu-nhap.php` chạy thật máy chủ. Bài này canh tab Kho: khối phiếu, POST phieu-nhap đúng trường, ô Nhập khoá
 * khi ngày có phiếu, danh sách phiếu và nút xoá chỉ văn phòng, lưu xong vẽ lại sổ kho.
 * Chạy: node tools/test/kiem-phieu-nhap-man.js
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
/* 25/09/2026 anh Thắng: "cho phiếu lên đầu, với dạng form bấm hiện ra" */
const vk0 = boc('veKho');
t('🔴 khối phiếu nằm ĐẦU tab: ngay sau hàng chọn ngày/cơ sở, trước bảng kho, không còn ở cuối', /if \(ghi\) h \+= veKhoPhieu\(r\);/.test(vk0) && vk0.indexOf('veKhoPhieu(r)') < vk0.indexOf('<table><thead>') && !/h \+= veKhoPhieu\(r\); h \+= veKhoMatHang/.test(vk0));
t('🔴 dạng nút bấm: #pnMo, khung #pnKhung ẩn mặc định (hidden khi chưa S.pnMo), có #pnLap và #pnDs', /id="pnMo"/.test(boc('veKhoPhieu')) && /id="pnKhung"[^>]*' \+ \(S\.pnMo \? '' : ' hidden'\)/.test(boc('veKhoPhieu')) && /id="pnLap"/.test(boc('veKhoPhieu')) && /id="pnDs"/.test(boc('veKhoPhieu')));
t('bấm nút là mở/đóng khung và nhớ S.pnMo; lưu xong giữ mở', /S\.pnMo = kh\.hidden;/.test(boc('noiKho')) && /kh\.hidden = !S\.pnMo;/.test(boc('noiKho')) && /S\.pnMo = true;/.test(boc('vePhieuLap')));
t('noiKho gọi taiPhieu(o); taiPhieu GET phieu-nhap theo co_so + ngay', /taiPhieu\(o\);/.test(boc('noiKho')) && /api\('phieu-nhap\?co_so=' \+ encodeURIComponent\(S\.kho\.cs\) \+ '&ngay='/.test(boc('taiPhieu')));
const lap = boc('vePhieuLap');
t('form: số phiếu (placeholder = số mới), ngày nhập mặc định ngày đang xem, nhà cung cấp, datalist mặt hàng', /id="pnSo"[^>]*placeholder="' \+ esc\(r\.so_moi/.test(lap) && /id="pnNgay" value="' \+ esc\(S\.kho\.ngay\)/.test(lap) && /id="pnNcc"/.test(lap) && /<datalist id="pnDsMH">/.test(lap));
t('🔴 Lưu POST phieu-nhap với co_so, ngay, so_phieu, ncc, ghi_chu, dong JSON [{mh, sl, gia}]', /fd\.append\('co_so', S\.kho\.cs\)/.test(lap) && /fd\.append\('so_phieu'/.test(lap) && /fd\.append\('ncc'/.test(lap) && /fd\.append\('dong', JSON\.stringify\(dong\)\)/.test(lap) && /dong\.push\(\{ mh: mh, sl: sl, gia: gia \}\)/.test(lap) && /api\('phieu-nhap', \{ method: 'POST', body: fd \}\)/.test(lap));
t('chỉ gửi dòng có tên VÀ số lượng; không dòng nào thì báo, không gọi máy chủ', /if \(mh && sl\) dong\.push/.test(lap) && /if \(!dong\.length\) \{ bao\.textContent = /.test(lap));
t('🔴 lưu xong cùng ngày đang xem -> vẽ lại sổ kho (S.khoR = null; taiKho())', /S\.khoR = null; taiKho\(\);/.test(lap));
t('mặt hàng chọn từ danh mục ∪ món FABi từng bán', /r\.mat_hang \|\| \[\]/.test(boc('pnMatHang')) && /r\.mon_da_thay/.test(boc('pnMatHang')));
const vk = boc('veKho');
t('🔴 ngày có phiếu: ô Nhập readonly, ghi "phiếu" kèm số phiếu', /\(r\.phieu_nhap \|\| \{\}\)\[d\.mat_hang\] && ghi/.test(vk) && /readonly data-kho="nhap"/.test(vk) && /class="chi-phieu"/.test(vk));
const ds = boc('vePhieuDs');
t('danh sách phiếu: số phiếu, ngày, NCC, mặt hàng × SL, tổng SL, người; nút Xoá chỉ khi xoa_duoc', /<th>Tổng SL<\/th>/.test(ds) && /r\.xoa_duoc \? '<button[^']*data-pn-xoa=/.test(ds));
t('xoá hỏi lại rồi POST xoa=id và vẽ lại sổ kho', /window\.confirm\('Xoá phiếu '/.test(ds) && /fd\.append\('xoa', b\.getAttribute\('data-pn-xoa'\)\)/.test(ds) && /S\.khoR = null; taiKho\(\);/.test(ds));
t('CSS có .chi-phieu', /\.khh-dt \.chi-phieu\{/.test(fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.css', 'utf8')));
if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: khối phiếu nhập nối đúng cổng, ô Nhập theo phiếu, lưu xong vẽ lại sổ kho.');
