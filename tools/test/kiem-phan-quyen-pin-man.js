/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐẨY NGƯỜI SANG CHỈ LÀ ĐẨY NGƯỜI — VAI CẤP Ở TAB QUẢN TRỊ BÊN BÁO CÁO. SOI MÀN VÀ CỔNG.
 *
 * Anh Thắng 23/09/2026: *"đẩy dữ liệu nhân sự là cửa hàng trưởng từ danh sách nhân sự qua để anh
 * phân quyền nộp báo cáo, vẫn như chi phí, chỉ đẩy nhân sự qua, chứ không phân quyền nhiệm vụ
 * trong đó, mà do trang tự phân quyền"*.
 *
 * `kiem-day-bao-cao.php` chạy thật phần lõi (đẩy sang là chưa cấp, đẩy lại không xoá vai). Bài này
 * canh phần lõi ấy CÓ CỬA để dùng: cổng REST cấp vai có gác quyền quản trị, màn Quản trị có bảng
 * người PIN với ô chọn vai và gọi đúng cổng, và cổng đẩy KHÔNG đọc lại `vai` từ bên nhân sự (một
 * dòng `$hs['vai']` quay lại là cả bài học bay).
 *
 * Chạy: node tools/test/kiem-phan-quyen-pin-man.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const JS  = fs.readFileSync('wordpress/khh-doanh-thu/assets/doanh-thu.js', 'utf8');
const NG  = fs.readFileSync('wordpress/khh-doanh-thu/nguoi.php', 'utf8');
const QT  = fs.readFileSync('wordpress/khh-doanh-thu/quan-tri.php', 'utf8');

/* Bỏ chú thích trước khi soi mã — chú thích kể chuyện cũ (`$hs['vai']`) không phải mã. */
function boChuThich(s) {
  return s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:'"])\/\/[^\n]*/g, '$1').replace(/^\s*#.*$/gm, '');
}
const ng = boChuThich(NG), qt = boChuThich(QT), js = boChuThich(JS);

let dat = 0; const hong = [];
function phep(ten, dung) { if (dung) dat++; else hong.push(ten); }

/* Cắt thân một hàm PHP theo tên (đếm ngoặc). */
function thanHam(src, ten) {
  const i = src.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let j = src.indexOf('{', i), sau = 0;
  for (let k = j; k < src.length; k++) {
    if (src[k] === '{') sau++;
    else if (src[k] === '}' && --sau === 0) return src.slice(j, k + 1);
  }
  return '';
}

/* ---- 1. cổng đẩy KHÔNG đọc vai ---- */
const dayVao = thanHam(ng, 'khh_dt_day_vao');
phep('có khh_dt_day_vao()', dayVao.length > 0);
phep("🔴 khh_dt_day_vao() không đọc $hs['vai']", !/\$hs\s*\[\s*'vai'\s*\]/.test(dayVao));
phep("khh_dt_day_vao() không nhét vai vào lệnh update", !/'vai'\s*=>\s*\$vai[\s\S]*?\$wpdb->update/.test(dayVao));
phep("người mới vào sổ với vai '' (không mặc định 'nhap')", !/\$vai\s*=\s*[^;]*'nhap'/.test(dayVao));

/* ---- 2. hàm cấp vai và cổng REST có gác quản trị ---- */
const datVai = thanHam(ng, 'khh_dt_dat_vai');
phep('có khh_dt_dat_vai()', datVai.length > 0);
phep('khh_dt_dat_vai() soát vai bằng khh_dt_vai_ds()', /in_array\(\s*\$vai\s*,\s*khh_dt_vai_ds\(\)/.test(datVai));
phep("khh_dt_vai_ds() gồm đúng '', 'nhap', 'duyet'", /return\s+array\(\s*''\s*,\s*'nhap'\s*,\s*'duyet'\s*\)/.test(thanHam(ng, 'khh_dt_vai_ds')));
const rest = ng.slice(ng.indexOf("'/nguoi-vai'"));
phep("có route /nguoi-vai", ng.indexOf("'/nguoi-vai'") >= 0);
phep("🔴 /nguoi-vai gác bằng khh_dt_duoc_quan_tri", /'permission_callback'\s*=>\s*'khh_dt_duoc_quan_tri'/.test(rest.slice(0, rest.indexOf(');'))));
phep("/nguoi-vai không phải __return_true", !/__return_true/.test(rest.slice(0, rest.indexOf(');'))));

/* ---- 3. khh_dt_quyen_cua: PIN không vai -> '' ---- */
const quyenCua = thanHam(qt, 'khh_dt_quyen_cua');
phep("🔴 khh_dt_quyen_cua() không lùi về 'nhap' cho PIN", !/\?\s*\(string\)\s*\$n\['vai'\]\s*:\s*'nhap'/.test(quyenCua));
phep("và GET /nhan-su trả sổ PIN", /'pin'\s*=>\s*function_exists\(\s*'khh_dt_ds_nguoi_pin'\s*\)/.test(qt));

/* ---- 4. màn Quản trị ---- */
const veQT = (function () {
  const i = js.indexOf('function veQuanTri('); const j = js.indexOf('window.KHHDoanhThu', i);
  return i >= 0 ? js.slice(i, j) : '';
})();
phep('có veQuanTri()', veQT.length > 0);
phep('màn đọc sổ PIN r.pin', /r\.pin\b/.test(veQT));
phep("ô chọn vai có mục trống là 'chưa cấp'", /chon\(\s*'vai'[^)]*chưa cấp/.test(veQT));
phep("nút Lưu gọi api('nguoi-vai') POST", /api\(\s*'nguoi-vai'\s*,\s*\{\s*method:\s*'POST'/.test(veQT));
phep("gửi ma_nv và vai", /fd\.append\(\s*'ma_nv'/.test(veQT) && /fd\.append\(\s*'vai'/.test(veQT));
phep("nói rõ 'Trang Nhân sự chỉ đẩy người sang; vai cấp ở đây'", /chỉ đẩy người sang; vai cấp ở đây/.test(veQT));
phep("người mất PIN vì trùng được nêu", /mất PIN/.test(veQT));

/* Dòng trạng thái ở tab Nhập: người PIN chưa cấp phải được nói vì sao khoá. */
phep("tab Nhập nói 'chưa được cấp quyền nhập' cho PIN chưa cấp",
  /S\.cf\.bang_pin\s*&&\s*!S\.cf\.vai[\s\S]{0,200}chưa được cấp quyền nhập/.test(js));

/* ---- kết ---- */
if (hong.length) {
  console.log('\n✗ HỎNG ' + hong.length + ' phép:');
  hong.forEach(function (h) { console.log('   · ' + h); });
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + dat + ' phép: đẩy người chỉ là đẩy người, vai cấp ở tab Quản trị');
