/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * ĐỊA ĐIỂM: CƠ SỞ CHỈ CÒN GHẾ ẨN PHẢI CÓ KHỐI RIÊNG, KHÔNG LẪN VỚI CƠ SỞ TRỐNG
 *
 * Anh Thắng 23/09/2026: *"anh muốn soi lại cơ sở bị ẩn để xoá. Nó đang nằm ở đâu"*.
 *
 * 🔴 GỐC. Bộ đếm ghế chỉ đếm ghế SỐNG (đúng ý anh 12/09), nên cơ sở còn 3 ghế ẩn hiện ra "0 ghế"
 *    và rơi vào khối "📭 Cơ sở chưa có ghế" — y hệt một cơ sở mới tạo. Người tìm không thấy nó
 *    không phải vì nó mất, mà vì nó GIỐNG một thứ khác. Đây là kiểu hỏng nguy hiểm: không có gì
 *    sai để nhìn thấy.
 *
 * ⚠️ BÀI NÀY BỐC ĐÚNG ĐOẠN CHIA KHỐI RA CHẠY với dữ liệu giả, không chỉ dò chữ: "vào đúng khối" là
 *    một phát biểu về NHÁNH NÀO chạy, dò chữ không nói được.
 *
 * Chạy: node tools/test/kiem-coso-chi-con-ghe-an.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const NGUON = 'vhcp-ghe/includes/class-vhg-trang.php';
if (!fs.existsSync(NGUON)) { console.error('✗ Không thấy ' + NGUON + ' — chạy từ gốc kho.'); process.exit(2); }
const src = fs.readFileSync(NGUON, 'utf8');
let hong = 0;
function t(ten, ok, them) { console.log((ok ? '  ✓ ' : '  ✗ ') + ten + (ok || them === undefined ? '' : ' → ' + JSON.stringify(them))); if (!ok) hong++; }

console.log('── Đếm riêng ghế ẩn ─────────────────────────────────────────');
const iDem = src.indexOf('var demAn = {};');
const iEnd = src.indexOf('});', iDem);
t('có bộ đếm ghế ẩn theo cơ sở (demAn)', iDem > 0 && iEnd > iDem);
const demMa = src.slice(iDem, iEnd + 3);
t('🔴 ghế ẩn (m.an) cộng vào demAn theo cơ sở, KHÔNG cộng vào demGhe', /if \(m\.an\) \{ if \(m\.coso\) demAn\[m\.coso\]/.test(demMa) && /return; \}/.test(demMa));
/* Chạy thật bộ đếm. */
const demF = new Function('may', 'var demGhe = {}, chuaGan = 0, soGheHD = 0; ' + demMa + ' return { demGhe, demAn, soGheHD };');
const may = [
  { ma: '80107', coso: 'CGV PEAR PLAZA', an: 1 }, { ma: '80403', coso: 'CGV PEAR PLAZA', an: 1 },
  { ma: '80013', coso: 'AEON TÂN PHÚ', an: 0 }, { ma: '80014', coso: 'AEON TÂN PHÚ', an: 1 },
  { ma: '80999', coso: '', an: 0 },
];
const d = demF(may);
t('cơ sở toàn ghế ẩn: demGhe = 0, demAn = 2', !d.demGhe['CGV PEAR PLAZA'] && d.demAn['CGV PEAR PLAZA'] === 2, d);
t('cơ sở lẫn: 1 sống + 1 ẩn', d.demGhe['AEON TÂN PHÚ'] === 1 && d.demAn['AEON TÂN PHÚ'] === 1, d);

console.log('── Chia bốn khối ────────────────────────────────────────────');
const iIf = src.indexOf("    if(Number(c.dong_cua)){ hDong+=_rh; nDong++; }");
const iElse = src.indexOf("    else { hRong+=_rh; nRong++; }", iIf);
t('bốc được đoạn chia khối', iIf > 0 && iElse > iIf);
const chia = src.slice(iIf, iElse + "    else { hRong+=_rh; nRong++; }".length);
const chiaF = new Function('c', 'demGhe', 'demAn',
  "var h='', _rh='<tr>'+c.ten+'</tr>', hRong='', nRong=0, hDong='', nDong=0, hAn='', nAn=0;" + chia
  + " return h ? 'h' : hDong ? 'hDong' : hAn ? 'hAn' : hRong ? 'hRong' : '?';");
t('🔴 cơ sở CHỈ CÒN GHẾ ẨN → khối hAn, KHÔNG rơi vào "chưa có ghế"',
  'hAn' === chiaF({ ten: 'CGV PEAR PLAZA', dong_cua: 0 }, d.demGhe, d.demAn));
t('cơ sở trống thật (không ghế nào) → hRong như cũ', 'hRong' === chiaF({ ten: 'MỚI TẠO', dong_cua: 0 }, d.demGhe, d.demAn));
t('đóng cửa vẫn THẮNG mọi suy đoán từ số ghế', 'hDong' === chiaF({ ten: 'CGV PEAR PLAZA', dong_cua: 1 }, d.demGhe, d.demAn));
t('có ghế sống → bảng chính, dù có thêm ghế ẩn', 'h' === chiaF({ ten: 'AEON TÂN PHÚ', dong_cua: 0 }, d.demGhe, d.demAn));

console.log('── Hiện ra để người ta thấy ─────────────────────────────────');
t('🔴 có khối riêng "Cơ sở chỉ còn ghế ẩn" với bảng #cs-bang-an', /Cơ sở chỉ còn ghế ẩn[\s\S]{0,900}id="cs-bang-an"/.test(src));
t('ô Số ghế in "(+N ẩn)" khi có ghế ẩn', /demAn\[c\.ten\] \? ' <span class="mut"[^']*'[\s\S]{0,120}\(\+' \+ demAn\[c\.ten\]/.test(src));
t('khối mới đứng TRƯỚC khối "chưa có ghế" (soi cái đáng ngờ trước cái vô hại)',
  src.indexOf('id="cs-bang-an"') < src.indexOf('id="cs-bang-rong"'));

console.log(hong ? '\n🔴 TRƯỢT: ' + hong : '\n✓ SẠCH');
process.exit(hong ? 1 : 0);
