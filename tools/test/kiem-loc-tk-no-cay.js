/* Ô lọc TK Nợ trên màn Xuất MISA: bày ở cả mẫu 10 cột, đổ theo cây cha/con. Anh Thắng 24/09/2026:
 * "bổ sung thêm bộ lọc tk nợ" · "lọc 641 nó sẽ ra cả con và cháu". Chạy: node tools/test/kiem-loc-tk-no-cay.js */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function teq(n, mong, thuc) { t(n + ' (mong ' + JSON.stringify(mong) + ')', JSON.stringify(mong) === JSON.stringify(thuc), thuc); }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
t('⚠️ bốc được _veOTkNo', ham('_veOTkNo').length > 40);
function ve(mau, src, XUAT, cu) {
  const o = { style: {}, innerHTML: '', value: cu || '' };
  const O = { xuatTkNo: o, xuatNguon: { value: src }, xuatMau: { value: mau } };
  new Function('el', 'XUAT', 'esc', ham('_veOTkNo') + '\n_veOTkNo();')((id) => O[id] || null, XUAT, (s) => String(s));
  return o;
}
const X = { tkDs: ['6412', '64166'], tkCay: [{ ma: '641', thuc: false }, { ma: '6412', thuc: true }, { ma: '6416', thuc: false }, { ma: '64166', thuc: true }] };
const a = ve('chuan', 'vh', X);
teq('🔴 mẫu 10 cột: ô lọc HIỆN', '', a.style.display);
t('   nhãn "mọi TK Nợ" không nhắc thêm cột', /<option value="all">— mọi TK Nợ —<\/option>/.test(a.innerHTML), a.innerHTML);
t('🔴 mã cha không có dòng riêng ghi "cả con/cháu", mã lá thì không', /<option value="641">641 — cả con\/cháu<\/option>/.test(a.innerHTML) && /<option value="6412">6412<\/option>/.test(a.innerHTML), a.innerHTML);
const b = ve('soct', 'vh', X);
t('   sổ chi tiết: nhãn nhắc thêm cột TK Nợ', /— mọi TK Nợ \(thêm cột TK Nợ\) —/.test(b.innerHTML), b.innerHTML);
teq('   nguồn khác đơn (dự án) → ẩn', 'none', ve('chuan', 'kt', X).style.display);
const c = ve('chuan', 'vh', { tkDs: ['6421'] });
t('   máy chủ cũ không trả tkCay → lui về tkDs', /<option value="6421">6421<\/option>/.test(c.innerHTML), c.innerHTML);
const d = ve('chuan', 'vh', X, '641');
teq('   giữ lựa chọn cũ khi vẽ lại', '641', d.value);
/* Số chứng từ = mã đơn → đường dẫn mở đơn (anh Thắng: "muốn sửa ở chỗ nào") */
{
  const O = { xuatSoDon: {}, xuatSoDong: {}, xuatEmpty: { style: {} }, xuatHead: {}, xuatBody: {}, xuatTable: { style: {} }, xuatTablesMang: { style: {} } };
  const X2 = { sodon: 1, count: 2, cols: ['TK Nợ', 'Ngày hạch toán', 'Ngày chứng từ', 'Số chứng từ', 'Diễn giải chung', 'Phát sinh Nợ'],
    rows: [['64196', '07/09/2026', '07/09/2026', 'D_abc', 'x', 277000], ['64196', '07/09/2026', '07/09/2026', 'NVK-la', 'y', 5000]] };
  /* 25/09/2026: renderXuat gọi thêm _veOMang (ô lọc Mảng kinh doanh) — tiêm stub cùng chỗ với _veOTkNo;
     cùng ngày tách bảng theo mảng → cần _xuatColsHead/_xuatRowHtml và hai thẻ xuatTable/xuatTablesMang. */
  new Function('el', 'XUAT', 'BOOT', 'esc', 'money', 'renderXuatWarn', 'renderXuatBanGiao', '_veOTkNo', '_veOMang', ham('_laMaDon') + ham('_xuatColsHead') + ham('_xuatRowHtml') + ham('renderXuat') + '\nrenderXuat();')(
    (id) => O[id], X2, { dons: [{ maDon: 'D_abc' }] }, (s) => String(s), (n) => String(n), () => {}, () => {}, () => {}, () => {});
  t('🔴 Số chứng từ là mã đơn trong kho → thành liên kết mở đơn', /onclick="viewDon\('D_abc'\);return false"/.test(O.xuatBody.innerHTML), O.xuatBody.innerHTML);
  t('   chuỗi không phải mã đơn → chữ thường, không liên kết', !/viewDon\('NVK-la'/.test(O.xuatBody.innerHTML) && /NVK-la/.test(O.xuatBody.innerHTML), O.xuatBody.innerHTML);
  t('   cột tiền vẫn canh phải', /class="money"[^>]*>277000</.test(O.xuatBody.innerHTML), O.xuatBody.innerHTML);
}
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach((x) => console.log('  · ' + x)); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: ô lọc TK Nợ bày ở cả hai mẫu, đổ theo cây cha/con.');
