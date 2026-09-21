/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * DẤU ĐỊA CHỈ TRÊN ẢNH CHẤM CÔNG.
 *
 * Anh Thắng 21/09/2026: *"Chèn địa chỉ vào ảnh"*.
 *
 * =============================================================================================
 * 🔴 GÓC DƯỚI TẤM ẢNH ĐÃ CHẬT SẴN: dấu giờ (trái), toạ độ (phải), ô bản đồ (trên toạ độ). Dấu
 *    địa chỉ là thứ THỨ TƯ chen vào cùng một vùng — và nó là thứ DÀI NHẤT. Chồng lên nhau thì
 *    không phải "hơi xấu": hai vệt đen dính thành một mảng, và tấm ảnh mất luôn giá trị làm
 *    bằng chứng đối chiếu, tức mất đúng lý do nó được chụp.
 *
 * 🔴 HỘP ĐEN VẼ THEO BỀ NGANG ĐO ĐƯỢC CỦA CHỮ. Nên chữ tràn không phải là chữ thò ra ngoài hộp
 *    — nó là cái HỘP phình to đè lên nửa tấm ảnh. Vì vậy phép cắt dòng phải chặt.
 *
 * ⚠️ CHẠY THẬT hàm `catDong()` bốc thẳng từ `tram.php`, không chép lại sang đây. Chép là bài
 *    này canh bản sao, và bản sao thì không bao giờ hỏng.
 *
 * Chạy: node tools/test/kiem-dau-dia-chi.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const path = require('path');

const GOC = path.resolve(__dirname, '..', '..');
const SRC = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-cham-cong/templates/tram.php'), 'utf8');

let DAT = 0; const TRUOT = [];
function t(ten, dk, them) {
  if (dk) { DAT++; return; }
  TRUOT.push(ten + (them === undefined ? '' : ' — ' + JSON.stringify(them)));
}

/** Bốc nguyên thân một hàm từ mã nguồn, cắt theo cặp ngoặc nhọn. */
function bocHam(ten) {
  const i = SRC.indexOf('function ' + ten + '(');
  if (i < 0) return '';
  let j = SRC.indexOf('{', i), sau = 0, k = j;
  for (; k < SRC.length; k++) {
    if (SRC[k] === '{') sau++;
    else if (SRC[k] === '}') { sau--; if (sau === 0) break; }
  }
  return SRC.slice(i, k + 1);
}

const nguon = bocHam('catDong');
t('bốc được hàm catDong từ tram.php', nguon.length > 100, nguon.length);
const catDong = new Function(nguon + '; return catDong;')();

/* Máy đo bề ngang giả: mỗi ký tự 10 đơn vị. Đủ để canh LUẬT cắt dòng — bề ngang thật thì trình
   duyệt lo, và thử bằng số tròn thì đọc phép thử ra ngay mình đang canh cái gì. */
const g = { measureText: (s) => ({ width: String(s).length * 10 }) };

/* ═══════════════════════════════════════════════════════ cắt theo TỪ, không cắt giữa từ */

let d = catDong(g, 'Le Duc Anh, Phuong Binh Tan', 120, 2);
t('cắt thành hai dòng', 2 === d.length, d);
t('🔴 không cắt giữa một từ', d.every((x) => !/\S$/.test(x) || 'Le Duc Anh, Phuong Binh Tan'.includes(x.replace('…', ''))), d);
t('   mỗi dòng vừa bề ngang', d.every((x) => x.length * 10 <= 120), d);

d = catDong(g, 'Quoc lo 1A', 500, 2);
t('chuỗi ngắn -> một dòng, giữ nguyên', 1 === d.length && 'Quoc lo 1A' === d[0], d);

/* ═══════════════════════════════════════════════════════ quá dài -> cắt và thêm dấu … */

const dai = 'So 1234 Duong Nguyen Van Qua Noi Dai, To dan pho 45, Khu pho 12, '
  + 'Phuong Dong Hung Thuan, Quan 12, Thanh pho Ho Chi Minh, Viet Nam, 71913';
d = catDong(g, dai, 200, 2);
t('🔴 không bao giờ vượt quá số dòng cho phép', 2 === d.length, d);
/* Đây là chốt giữ cho cái HỘP không phình ra đè nửa tấm ảnh. */
t('🔴 mọi dòng đều vừa bề ngang', d.every((x) => x.length * 10 <= 200), d.map((x) => x.length * 10));
t('   dòng cuối có dấu … báo là còn nữa', /…$/.test(d[d.length - 1]), d);

d = catDong(g, dai, 200, 1);
t('một dòng thôi cũng cắt đúng', 1 === d.length && d[0].length * 10 <= 200, d);

/* ⚠️ MỘT TỪ DUY NHẤT DÀI HƠN CẢ DÒNG — không được treo, và vẫn phải ra chữ. */
d = catDong(g, 'Duongquocloxuyenvietkeodaimaimai', 100, 2);
t('🔴 một từ dài hơn cả dòng -> vẫn cắt được, không treo', d.length >= 1 && d[0].length * 10 <= 100, d);

t('chuỗi rỗng -> không ra dòng nào', 0 === catDong(g, '', 200, 2).length);

/* ═══════════════════════════════════════════════ chừa chỗ cho ô bản đồ — soi trên mã nguồn */

/* ⚠️ BỎ CHÚ THÍCH TRƯỚC KHI DÒ. Khối chú thích ngay trên đoạn mã này nhắc lại gần hết mấy chữ
   cần tìm; dò trên nguyên văn là mấy đoạn văn ấy làm bài xanh, kể cả khi mã thật đã hỏng. */
const ma = SRC.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^[ \t]*\/\/.*$/gm, '');

t('🔴 bề ngang dấu địa chỉ cắt tại mép trái ô bản đồ',
  /rongTD\s*=\s*\(veMap\s*\?\s*oX\s*-\s*\d+\s*:/.test(ma), (ma.match(/rongTD[^;]*/) || [''])[0]);
t('🔴 hình ô bản đồ tính TRƯỚC khi vẽ địa chỉ',
  ma.indexOf('var veMap') > 0 && ma.indexOf('var veMap') < ma.indexOf('rongTD'));
t('   và khối vẽ bản đồ dùng lại đúng biến ấy, không tính lại',
  /if\(veMap\)\{/.test(ma.replace(/\s/g, '')) && 1 === (ma.match(/var oB\s*=/g) || []).length,
  (ma.match(/var oB\s*=/g) || []).length);
/* Không có địa chỉ thì KHÔNG vẽ hộp đen rỗng. */
t('🔴 chỉ vẽ khi thật sự có địa chỉ', /if\(dc\)\{/.test(ma.replace(/\s/g, '')));
/* Mất dòng địa chỉ còn hơn mất tấm ảnh — cùng lý do với ô bản đồ. */
t('🔴 bọc try để không làm hỏng cả tấm ảnh',
  /try\s*\{[\s\S]{0,1200}catDong\(/.test(ma));

if (TRUOT.length) {
  console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):');
  TRUOT.forEach((x) => console.log('  · ' + x));
  process.exit(1);
}
console.log('\n✓ SẠCH — ' + DAT + ' phép: địa chỉ vào ảnh mà không đè dấu giờ hay ô bản đồ.');
