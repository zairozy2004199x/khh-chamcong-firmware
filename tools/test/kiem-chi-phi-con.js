/**
 * CHI PHÍ CON — TẦNG THỨ BA CỦA CÂY DANH MỤC
 * =============================================================================================
 *
 * Anh Thắng 22/09/2026: *"Trừ khi nó có thêm chi phí nhỏ nữa, thì sẽ hiện, nên trong cấu hình
 * bấm + nếu có chi phí con"*, sau khi đã chốt *"Loại chi phí là chi phí chi tiết, còn đầu mục
 * là Danh mục chính của chi phí"*.
 *
 *      Đầu mục (danh mục cha)  ->  Loại chi phí  ->  Chi phí con
 *
 * 🔴 CHI PHÍ CON KHÔNG PHẢI MỘT TẦNG DỮ LIỆU MỚI — nó là một DÒNG loại chi phí bình thường,
 *    chỉ thêm ô `cha`. Nhờ vậy sổ ĐƠN không phải đổi lấy một cột: đơn vẫn lưu `loai` = tên
 *    dòng người ta chọn, cha hay con cũng thế. Bài này canh đúng hai điều ấy: con HIỆN RA
 *    được, và nó đứng NGAY DƯỚI cha.
 *
 * Chạy: node tools/test/kiem-chi-phi-con.js
 */
const fs = require('fs'), path = require('path');
const GOC = path.resolve(__dirname, '..', '..');
const APP = fs.readFileSync(path.join(GOC, 'wordpress/vhcp-chi-phi/templates/app.html'), 'utf8');

let dat = 0; const truot = [];
function t(ten, dk, them) {
  if (dk) { dat++; return; }
  truot.push(ten + (them === undefined ? '' : '\n      → ' + JSON.stringify(them)));
}
const teq = (ten, mong, thuc) =>
  t(ten + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc);

function layHam(ten) {
  const i = APP.indexOf('function ' + ten + '(');
  if (i < 0) throw new Error('không thấy ' + ten + '()');
  let j = APP.indexOf('{', i), sau = 0;
  for (let k = j; k < APP.length; k++) {
    if (APP[k] === '{') sau++;
    else if (APP[k] === '}') { sau--; if (!sau) return APP.slice(i, k + 1); }
  }
  throw new Error('ngoặc lệch ở ' + ten + '()');
}
const M = new Function('BOOT', 'esc', layHam('_optsHtml')
  + '\n  return { opts:_optsHtml };')(
  { dauMucDs: ['Chi phí cơ sở', 'Chi phí chung', 'Khác'] },
  v => String(v == null ? '' : v));

/* Dòng danh mục như `_loaiCpOpts()` dựng ra: ten · tk · dm (đầu mục) · cha · nhan. */
const R = (ten, dm, cha) => ({ ten, tk: '', dm, cha: cha || '', nhan: ten });
/* 🔴 THỨ TỰ TRONG SỔ PHẢI SAI SẴN, không thì phép "xếp con xuống dưới cha" xanh vô điều kiện.
   Lượt đục đầu tiên dạy đúng chỗ này: bản nháp của bài để con nằm sẵn ngay sau cha, nên gỡ
   HẲN phép xếp lại mà bài vẫn xanh. Nay một con đứng TRƯỚC cha, một con cách cha hai dòng —
   đúng như sổ thật, nơi người ta thêm dần qua nhiều tháng và không ai sắp lại bao giờ. */
const SO = [
  R('Đồ tươi', 'Chi phí cơ sở', 'Chi phí NVL đồ ăn'),
  R('Chi phí cơ sở', 'Chi phí cơ sở'),
  R('Chi phí NVL đồ ăn', 'Chi phí cơ sở'),
  R('Chi phí văn phòng', 'Chi phí chung'),
  R('Đồ khô', 'Chi phí cơ sở', 'Chi phí NVL đồ ăn'),
];
const h = M.opts(SO, '', '', '—');
/* Tên hiện ra theo đúng thứ tự, kèm dấu thụt nếu có. */
const day = [...h.matchAll(/<option value="\d+"[^>]*>([^<]*)</g)].map(x => x[1]);

// ── 1. 🔴 CON PHẢI HIỆN, VÀ ĐỨNG NGAY DƯỚI CHA ────────────────────────────────────────────
t('🔴 chi phí con HIỆN RA ở ô chọn — không hiện là không ai nhập được vào nó',
  day.some(x => /Đồ khô/.test(x)) && day.some(x => /Đồ tươi/.test(x)), day);
const iCha = day.findIndex(x => /Chi phí NVL đồ ăn/.test(x));
teq('🔴 hai con đứng NGAY DƯỚI cha, liền nhau, dù trong sổ chúng nằm rải rác',
  true, iCha >= 0 && /Đồ tươi/.test(day[iCha + 1] || '') && /Đồ khô/.test(day[iCha + 2] || ''));
t('   và cha KHÔNG bị con chen lên trước', iCha > 0 || !/↳/.test(day[0] || ''), day);
t('🔴 con có dấu ↳ để nhìn ra tầng — không có dấu thì hai tầng đọc như một dãy phẳng',
  /↳/.test(day[iCha + 1] || '') && /↳/.test(day[iCha + 2] || ''), day);
t('   và cha thì KHÔNG có dấu ấy', !/↳/.test(day[iCha] || ''), day[iCha]);
/* Dấu thụt phải là khoảng trắng CỨNG: trình duyệt nuốt dấu cách thường ở đầu <option>. */
t('🔴 thụt bằng khoảng trắng cứng, không phải dấu cách thường (trình duyệt nuốt mất)',
  / /.test(day[iCha + 1] || ''), JSON.stringify(day[iCha + 1]));

// ── 2. ĐƠN VẪN LƯU TÊN CON, KHÔNG PHẢI TÊN CHA ────────────────────────────────────────────
/* `_selLoai()` đọc `data-ten`; đó là thứ rơi vào sổ đơn. Con mà mang `data-ten` của cha thì
   mọi đơn nhập vào con đều ghi thành cha — sai số liệu, im lặng. */
const tenGui = [...h.matchAll(/data-ten="([^"]*)"/g)].map(x => x[1]);
t('🔴 con gửi đi TÊN CỦA CHÍNH NÓ, không phải tên cha',
  tenGui.indexOf('Đồ khô') >= 0 && tenGui.indexOf('Đồ tươi') >= 0, tenGui);
t('   và dấu ↳ chỉ để nhìn, không dính vào tên gửi đi',
  !tenGui.some(x => /↳| /.test(x)), tenGui);

// ── 3. 🔴 CON MỒ CÔI VẪN PHẢI HIỆN ────────────────────────────────────────────────────────
/* Cha bị xoá khỏi danh mục, hoặc khai lệch đầu mục nên nằm nhóm khác. Mất hẳn con là mất luôn
   lối nhập cho những khoản ấy — đúng kiểu hỏng im lặng mà cả bộ thử này sinh ra để chặn. */
const MO_COI = [R('Chi phí cơ sở', 'Chi phí cơ sở'), R('Đồ khô', 'Chi phí cơ sở', 'Cha đã bị xoá')];
const d2 = [...M.opts(MO_COI, '', '', '—').matchAll(/<option value="\d+"[^>]*>([^<]*)</g)].map(x => x[1]);
t('🔴 con mồ côi VẪN hiện — mất nó là mất luôn lối nhập', d2.some(x => /Đồ khô/.test(x)), d2);
t('   và vẫn giữ dấu ↳ để người ta biết mà đi sửa', /↳/.test(d2.find(x => /Đồ khô/.test(x)) || ''), d2);
teq('   nhưng bị dồn xuống CUỐI nhóm, không chen vào giữa hàng cha', true, /Đồ khô/.test(d2[d2.length - 1]));

// ── 4. KHÔNG CÓ CON THÌ MỌI THỨ Y NHƯ TRƯỚC ───────────────────────────────────────────────
const PHANG = [R('B', 'Chi phí cơ sở'), R('A', 'Chi phí cơ sở')];
const d3 = [...M.opts(PHANG, '', '', '—').matchAll(/<option value="\d+"[^>]*>([^<]*)</g)].map(x => x[1]);
teq('🔴 danh mục không dòng nào có cha -> giữ NGUYÊN thứ tự sổ, không tự sắp lại',
  ['B', 'A'], d3);
t('   và không dòng nào bị gắn dấu thụt', !d3.some(x => /↳/.test(x)), d3);

/* ── kết ────────────────────────────────────────────────────────────────────────────────── */
if (truot.length) {
  console.log('HỎNG: ' + truot.length);
  truot.forEach(x => console.log('  ✗ ' + x));
  console.log('ĐẠT: ' + dat);
  process.exit(1);
}
console.log('ĐẠT: ' + dat + ' phép — chi phí con hiện ra, đứng dưới cha, và đơn vẫn lưu tên con.');
