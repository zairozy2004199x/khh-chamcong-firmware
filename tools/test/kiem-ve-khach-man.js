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
t('🔴 có vé chưa khai thì nhãn ô ghi "tạm tính" và nói tạm tính 1 khách mỗi vé', /p\.khach_tam \? ' · tạm tính'/.test(nap) && /tạm tính 1 khách mỗi vé/.test(nap));
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
t('mặc định chọn gian Tàu trước', /t\[àa\]u\|train/i.test(boc('cauHinhCS')));
t("GET ve-khach theo cơ sở", /api\('ve-khach\?cua_hang='/.test(tai));
t('ô nhập khách mỗi vé data-vk', /data-vk=/.test(ve));
t('placeholder mang gợi ý', /gợi ý ' \+ x\.goi_y/.test(ve));
t('🔴 nút chính "Lưu cho tất cả cửa hàng" (bảng chung), nút phụ "Lưu riêng cho quán này" (24/09/2026: "để nhỡ vé đó riêng thì cơ sở đó chủ động tự set")', /id="vkLuuChung" data-vk-luu="\*">Lưu cho tất cả cửa hàng</.test(ve) && /id="vkLuu" data-vk-luu="rieng">Lưu riêng cho quán này</.test(ve));
t('🔴 lưu riêng CHỈ gửi ô gõ khác số chung, ô bằng số chung gửi trống', /ra\[i\.dataset\.vk \|\| i\.dataset\.vp\] = \(v !== '' && v !== c\) \? v : '';/.test(ve) && /data-chung="' \+ \(x\.khach_chung != null/.test(ve));
t('vé quán set riêng được ghi rõ kèm số chung; có nút Bỏ set riêng (POST xoa_rieng)', /quán này set riêng/.test(ve) && /id="vkBoRieng"/.test(ve) && /fd\.append\('xoa_rieng', '1'\)/.test(ve));
t("🔴 Lưu gọi POST ve-khach với 'bang' JSON", /api\('ve-khach', \{ method: 'POST'/.test(ve) && /fd\.append\('bang', JSON\.stringify\(b\)\)/.test(ve));
t('ô chọn nói rõ: bảng chung cho mọi quán, quán nào khác thì set riêng', /bảng chung cho mọi quán/.test(boc('oChonCS')));
t('nói rõ 0 khác ô trống', /Ô để trống = không tính/.test(ve));
/* 23/09/2026 anh Thắng: "Mỗi cửa hàng 1 cấu hình đi" — một ô chọn cửa hàng dùng chung cho hai khối. */
t('🔴 vé chưa khai được ĐIỀN SẴN gợi ý (không chỉ placeholder)', /\(thieu && x\.goi_y != null \? x\.goi_y : ''\)/.test(ve));
t("🔴 Lưu gửi cua_hang = '*' khi lưu chung, tên quán khi lưu riêng, kèm xem_cua_hang", /fd\.append\('cua_hang', chungK \? '\*' : \(r\.cua_hang \|\| cauHinhCS\(\)\)\)/.test(ve) && /fd\.append\('xem_cua_hang', r\.cua_hang \|\| cauHinhCS\(\)\)/.test(ve));
t('bảng đổ thành thẻ trên điện thoại: bang-the the-cf + data-nhan', /class="bang-the the-cf bang-cuon"/.test(ve) && /data-nhan="Khách mỗi vé"/.test(ve) && /data-nhan="Sale phụ mỗi vé \(đ\)"/.test(ve));
t('có ô chọn cửa hàng chung (oChonCS) ở khối bóc tách', /oChonCS\('vkCS', r\)/.test(ve));
t('đổi ô chọn là tải lại CẢ HAI khối', /S\.cauHinhCS = sel\.value; taiVeKhach\(o\); taiNhomVe\(o\);/.test(boc('noiChonCS')));
t('nhắc quán khác còn vé chưa khai (con_thieu) và bấm là sang quán ấy', /r\.con_thieu/.test(ve) && /data-sang-cs=/.test(ve));

t('🔴 cột "Sale phụ mỗi vé (đ)" theo tên vé, ô data-vp, placeholder nêu số của nhóm', /<th>Sale phụ mỗi vé \(đ\)<\/th>/.test(ve) && /data-vp=/.test(ve) && /'nhóm: ' \+ nguyen\(x\.phu_nhom\)/.test(ve));
t('Lưu gửi kèm phu (theo tên vé)', /fd\.append\('phu', JSON\.stringify\(bp\)\)/.test(ve));

t('tab Nhập bày "Cách tính khách vào (POS)" từng vé × khách/vé (24/09/2026: "set xong lại sao nó không áp dụng")', /id="bcKhachCach"/.test(boc('napBaoCao')) && /p\.khach_chi_tiet\.map/.test(boc('napBaoCao')));
/* 🔴 26/09/2026: anh Thắng — "Điền sẵn thì phải áp dụng luôn chứ". "k" giờ là GỢI Ý thật (có thể
   2, 3…), không còn luôn luôn là 1 — chữ chú thích phải đọc theo đúng c.k đang dùng, không được
   hardcode "tạm 1" (số ấy đã từng đúng, giờ đã sai vì combo tạm theo gợi ý 2). */
t('🔴 chữ chú thích "tạm X" đọc THEO c.k đang dùng, không hardcode "tạm 1"',
  /\(chưa khai, tạm ' \+ c\.k \+ '\)/.test(boc('napBaoCao')) && !/chưa khai, tạm 1\)/.test(boc('napBaoCao')));

if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép (đạt ' + dat + '):');
  hong.forEach((h) => console.log('   · 🔴 ' + h));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: màn bày khách máy, lệch so khách máy, Quản trị khai được bóc tách.');
