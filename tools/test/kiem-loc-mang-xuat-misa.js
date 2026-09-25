/* Ô lọc "Mảng kinh doanh" trên màn Xuất MISA — anh Thắng 25/09/2026: "Mỗi chi phí sẽ xuất ra 1
 * bảng misa riêng". Chạy: node tools/test/kiem-loc-mang-xuat-misa.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };

t('🔴 bốc được _veOMang', ham('_veOMang').length > 40);
t('   ô xuatMang có trong markup, ẩn mặc định, ngay sau xuatTkNo', /id="xuatTkNo"[\s\S]{0,900}<select id="xuatMang" onchange="callExport\(\)" style="display:none"/.test(HTML));

function ve(src, XUAT, cu) {
  const o = { style: {}, innerHTML: '', value: cu || '' };
  const O = { xuatMang: o, xuatNguon: { value: src } };
  new Function('el', 'XUAT', 'esc', ham('_veOMang') + '\n_veOMang();')((id) => O[id] || null, XUAT, (s) => String(s));
  return o;
}
const X = { mangDs: ['Chi Phí Cơ Sở KVC', 'Chi Phí Vận Hành', '(chưa khai mảng)'] };
const a = ve('vh', X);
teq('🔴 nguồn "vh": ô HIỆN', '', a.style.display);
t('   option "— mọi mảng —" đứng đầu', a.innerHTML.indexOf('<option value="all">— mọi mảng —</option>') === 0, a.innerHTML);
t('   đủ 3 mảng, đúng thứ tự máy chủ trả về (đã xếp "(chưa khai mảng)" cuối)', /KVC<\/option><option value="Chi Phí Vận Hành">Chi Phí Vận Hành<\/option><option value="\(chưa khai mảng\)">/.test(a.innerHTML), a.innerHTML);
teq('   nguồn khác "vh" (dự án/marketing…) → ẩn', 'none', ve('kt', X).style.display);
const b = ve('vh', X, 'Chi Phí Vận Hành');
teq('   giữ lựa chọn cũ khi vẽ lại', 'Chi Phí Vận Hành', b.value);
const c = ve('vh', { mangDs: [] });
teq('   máy chủ chưa trả mangDs (lời gọi cũ) → chỉ còn "— mọi mảng —", không vỡ', '<option value="all">— mọi mảng —</option>', c.innerHTML);

/* onXuatNguon và renderXuat đều phải gọi _veOMang (cùng chỗ gọi _veOTkNo) */
t('🔴 onXuatNguon() gọi _veOMang() ngay sau _veOTkNo()', /_veOTkNo\(\);\s*_veOMang\(\);\s*callExport\(\);/.test(HTML));
t('   renderXuat() cũng gọi _veOMang() sau _veOTkNo()', /_veOTkNo\(\);\s*_veOMang\(\);\s*\}/.test(HTML));

/* callExport() phải truyền đúng 6 tham số, tham số thứ 6 lấy từ ô xuatMang, mặc định 'all' */
{
  const src = ham('callExport');
  let goi = null;
  const O = { xuatNguon: { value: 'vh' }, xuatKy: { value: 'T9/2026' }, xuatTT: { value: 'chuaxuat' }, xuatMau: { value: 'soct' }, xuatTkNo: { value: '641' }, xuatMang: { value: 'Chi Phí Vận Hành' } };
  const run = { withSuccessHandler() { return this; }, withFailureHandler() { return this; }, exportMisa(...a) { goi = a; } };
  new Function('el', 'google', '_vaiLuat', 'loading', 'renderXuat', 'XUAT', src + '\ncallExport();')(
    (id) => O[id], { script: { run } }, () => 'Kế toán cá nhân', () => {}, () => {}, {});
  teq('🔴 callExport gửi đúng 6 tham số, mảng ở vị trí cuối', ['T9/2026', 'chuaxuat', 'cn', 'soct', '641', 'Chi Phí Vận Hành'], goi);
  O.xuatMang = null;   // trước khi có tính năng: chưa có ô này trên trang
  new Function('el', 'google', '_vaiLuat', 'loading', 'renderXuat', 'XUAT', src + '\ncallExport();')(
    (id) => O[id], { script: { run } }, () => 'Kế toán cá nhân', () => {}, () => {}, {});
  teq('   thiếu ô xuatMang → lùi về "all", không vỡ', 'all', goi[5]);
}

if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô lọc Mảng kinh doanh trên Xuất MISA.');
