/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * MÀN NHẬP DỰNG THEO LỊCH SỬ THU TIỀN — "HÔM TRƯỚC CÓ THÌ NAY PHẢI CÓ"
 *
 * Anh Thắng 19/09/2026: *"nguyên tắc đi từ đầu đến cuối dữ liệu"*, *"hôm trước có, nay phải có
 * chứ"*, *"không có dữ liệu xoá nào, khoá lại ẩn ghế đi"*.
 *
 * 🔴 VÌ SAO ĐẢO NGUỒN SỰ THẬT. Ba lần "tự nhiên mất ghế" đều khác nguyên nhân: 80111 do cờ `an`;
 *    GO-TDM-1/2/3 thì KHÔNG dính cờ ẩn (màn nhập 2.114 không hiện dải cảnh báo nào) mà vẫn mất —
 *    rơi khỏi cơ sở vì gán sai. Vá từng nguyên nhân thì nguyên nhân thứ tư lại tới, và mỗi lần
 *    tới là một cơ sở nộp báo cáo thiếu ghế trong nhiều ngày mà không ai thấy.
 *    Danh mục có thể sai; lịch sử thu tiền thì không. Ghế đã từng ra tiền ở cơ sở này thì nó có
 *    thật ở đây.
 *
 * Chạy: node tools/test/kiem-ghe-theo-lich-su.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
function doc(p) {
  if (!fs.existsSync(p)) { console.error('✗ Không thấy tệp: ' + p + '\n  Chạy từ gốc kho.'); process.exit(2); }
  return fs.readFileSync(p, 'utf8');
}
const bc    = doc('vhcp-ghe/includes/class-vhg-baocao.php');
const may   = doc('vhcp-ghe/includes/class-vhg-may.php');
const trang = doc('vhcp-ghe/includes/class-vhg-trang.php');
let hong = 0;
function t(ten, ok, them) {
  console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them)));
  if (!ok) hong++;
}
function than(src, ten) {
  const i = src.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let d = 0;
  for (let k = src.indexOf('{', i); k < src.length; k++) {
    if (src[k] === '{') d++;
    else if (src[k] === '}' && --d === 0) return src.slice(i, k + 1);
  }
  return '';
}
const dsg = than(bc, 'ds_ghe');

console.log('── Lấy ghế theo lịch sử ───────────────────────────────────────');
t('ds_ghe() đọc bc_dong + bc để lấy ghế đã từng thu', /bc_dong[\s\S]{0,200}report_id/.test(dsg));
/* Soi RIÊNG câu truy vấn, không soi cả thân hàm: chú thích trong thân có nhắc `coso_id` để giải
   thích vì sao KHÔNG dùng nó — soi cả thân là bài kiểm đỏ vì chính lời giải thích của nó. */
const sqlLs = (dsg.match(/'SELECT h\.coso[\s\S]*?ARRAY_A \)/) || [''])[0];
t('khớp theo bc.coso (tên đóng băng), KHÔNG theo may.coso_id — chính coso_id là thứ đang sai',
  /h\.coso/.test(sqlLs) && !/coso_id/.test(sqlLs) && !/'may'/.test(sqlLs), sqlLs.slice(0, 80));
t('chỉ lấy hàng CÓ dữ liệu (đừng kéo về hàng trắng)',
  /chi_so_sau IS NOT NULL OR d\.tong <> 0 OR d\.actual <> 0/.test(dsg));
t('có cửa sổ ngày để ghế tháo thật còn đường rụng', /GHE_LS_NGAY/.test(dsg) && /const GHE_LS_NGAY/.test(bc));
t('vẫn chốt theo phạm vi PIN (không rò ghế cơ sở khác)', /trong_pham_vi\(\s*\$q\s*,\s*\$cs_ls/.test(dsg));
t('không thêm trùng ghế đã có trong danh mục', /\$da_co\[/.test(dsg));
t('ghế kéo về từ lịch sử thì bỏ khỏi dải cảnh báo "đang bị giấu"', /\$an_bo\s*=\s*\$loc/.test(dsg));

console.log('── Khoá tính năng ẩn ghế ──────────────────────────────────────');
t('có cửa chặn chung chan_an_()', /function chan_an_\(\)/.test(may));
['dat_an', 'dat_an_lo', 'xoa_may'].forEach(function (fn) {
  t(fn + '() chặn chiều BẬT cờ ẩn', /return self::chan_an_\(\);/.test(than(may, fn)));
});
t('vẫn CHO GỠ cờ ẩn (đưa về) — chỉ chặn chiều bật',
  /if \(\s*\$an\s*\)\s*\{\s*return self::chan_an_\(\); \}/.test(than(may, 'dat_an')));
t('nút bấm nói lý do thay vì gọi máy chủ rồi nhận lỗi', /function khoaAnGhe\(\)/.test(trang));
t('không còn chỗ nào bấm để BẬT cờ ẩn',
  !/lam\('may_an',\s*\{\s*ma:[^}]*an:\s*1/.test(trang)
  && !/lam\('may_an_lo',\s*\{\s*ma:[^}]*an:\s*1/.test(trang)
  && !/lam\('may_xoa',/.test(trang));

console.log('── Sửa 24h phải bày cả ghế chưa nhập ─────────────────────────');
/* Anh Thắng 19/09: *"trả lại để nhập lại, hoặc bấm sửa thì nó phải có cả ghế chưa nhập chứ"*.
   ds_24h() lọc hàng CÓ dữ liệu, nên báo cáo nộp thiếu ghế thì mở Sửa ra vẫn thiếu đúng chỗ ấy —
   đúng lúc người ta mở để bổ sung thì màn hình giấu mất phần cần bổ sung. */
const ds24 = than(bc, 'ds_24h');
t('ds_24h() ghép thêm ghế của cơ sở chưa có dòng', /chuaNhap/.test(ds24) && /ds_ghe\(/.test(ds24));
t('ghế ghép thêm có sẵn chỉ số trước (khỏi gõ mò)', /chi_so_truoc\(/.test(ds24));
t('chỉ ghép ghế ĐÚNG cơ sở của báo cáo', /squash\(\s*\$g0\['coso'\]\s*\)\s*!==\s*self::squash/.test(ds24));
const sd = than(bc, 'sua_dong');
t('sua_dong() TẠO dòng cho ghế chưa nhập thay vì chối', /\$wpdb->insert\(\s*VHG_DB::t\( 'bc_dong' \)/.test(sd));
t('… nhưng vẫn chốt phạm vi PIN + đúng cơ sở của báo cáo',
  /trong_pham_vi\(\s*\$q\s*,\s*\(string\) \$h0\['coso'\]\s*,\s*\$ma\s*\)/.test(sd));
t('màn Sửa 24h đánh dấu hàng CHƯA NHẬP', /c\.chuaNhap/.test(trang) && /CHƯA NHẬP/.test(trang));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
