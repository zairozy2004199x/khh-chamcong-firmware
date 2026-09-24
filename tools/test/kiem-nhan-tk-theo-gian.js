/* ══════════════════════════════════════════════════════════════════════════════════════════════
 * NHÃN "TK Nợ …" CẠNH Ô LOẠI CHI PHÍ TRA MA TRẬN THEO GIAN, KHÔNG CHỈ Ô MÃ CỐ ĐỊNH.
 * Anh Thắng 24/09/2026 (ảnh): chọn "Chi phí cơ sở" mà nhãn đỏ "CHƯA GẮN MÃ TK" dù ma trận có
 * 64196/64166/64126 — *"báo như này là đúng hay sai"*: sai. Chạy: node tools/test/kiem-nhan-tk-theo-gian.js
 * ═════════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const HTML = fs.readFileSync('wordpress/vhcp-chi-phi/templates/app.html', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; } else { TRUOT.push(n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
const ham = (n) => { const i = HTML.indexOf('  function ' + n + '('); return i < 0 ? '' : HTML.slice(i, HTML.indexOf('\n  }', i) + 4); };
['showTkNhom', '_tkNoCua', '_tkNoList', '_tkNoCuaLoai', '_mangCua'].forEach((n) => t('⚠️ bốc được `' + n + '`', ham(n).length > 40, n));
function ve(loai, coso) {
  const KHO = {}; const sel = (id) => (KHO[id] = KHO[id] || { value: '', textContent: '', style: {} });
  sel('f_nhom').value = loai; sel('f_coso').value = coso;
  const BOOT = {
    loaiChiPhi: [{ ten: 'Chi phí cơ sở', tkNo: '' }, { ten: 'Chi phí lương', tkNo: '6421' }, { ten: 'Chi phí lạ', tkNo: '' }, { ten: 'Chi phí rác', tkNo: '331' }],
    tkNoMx: { 'chi phí cơ sở': { 'event fz mn': ['64196'], 'farm mn': ['64166'], 'fz mn': ['64126'] }, 'chi phí khác': { 'event fz mn': ['64125', '64197'] } },
    cosoPll: { 'adv go an lạc': 'EVENT FZ MN', 'farm nha trang': 'FARM MN', 'gian lạ': '' },
  };
  new Function('BOOT', 'el', '_tapTkCo', '_donNhieuCoSo', '_mangPham', ['_mangCua', '_tkNoList', '_tkNoCuaLoai', '_tkNoCua', 'showTkNhom'].map(ham).join('\n') + '\nshowTkNhom();')(BOOT, sel, () => ({ '331': 1, '141': 1 }), () => false, () => []);
  return { chu: sel('lblNhomTk').textContent, mau: sel('lblNhomTk').style.color };
}
let r = ve('Chi phí cơ sở', 'ADV Go An Lạc');
t('🔴 loại theo mảng + có gian → mã của mảng ấy, kèm tên mảng', r.chu === 'TK Nợ 64196 · EVENT FZ MN' && r.mau === '#2545ff', r);
r = ve('Chi phí cơ sở', 'FARM NHA TRANG');
t('   gian khác → mã khác', r.chu === 'TK Nợ 64166 · FARM MN', r);
r = ve('Chi phí cơ sở', '');
t('🔴 chưa chọn gian, loại CÓ mã theo mảng → nói "Nợ theo gian — chọn cơ sở", màu trung tính, KHÔNG đỏ "chưa gắn mã"', r.chu === 'Nợ theo gian — chọn cơ sở' && r.mau === '#8c8781', r);
r = ve('Chi phí cơ sở', 'GIAN LẠ');
t('   gian chưa khai mảng → nói đúng chỗ thiếu (mảng của gian), màu cam, không đỏ "chưa gắn mã"', /Gian "GIAN LẠ" chưa khai mảng kinh doanh/.test(r.chu) && r.mau === '#b45309', r);
r = ve('Chi phí lương', '');
t('   loại mã cố định → hiện mã, không cần gian', r.chu === 'TK Nợ 6421' && r.mau === '#2545ff', r);
r = ve('Chi phí khác', 'ADV Go An Lạc');
t('   mảng khai 2 mã → liệt kê, nói kế toán chọn lúc quyết toán', /Nợ 64125 \| 64197 — kế toán chọn/.test(r.chu), r);
r = ve('Chi phí lạ', 'ADV Go An Lạc');
t('🔴 loại không mã đâu cả → mới đỏ "chưa gắn mã TK"', r.chu === 'chưa gắn mã TK' && r.mau === '#dc2626', r);
r = ve('Chi phí rác', '');
t('   mã cố định là TK Có (331) → coi như chưa gắn (không bày 331 làm TK Nợ)', r.chu === 'chưa gắn mã TK', r);
r = ve('', 'ADV Go An Lạc');
t('   chưa chọn loại → nhãn trống', r.chu === '', r);
if (TRUOT.length) { console.log('\n✗ TRƯỢT ' + TRUOT.length + ' phép (đạt ' + DAT + '):'); TRUOT.forEach(function (x) { console.log('  · ' + x); }); process.exit(1); }
console.log('\n✓ SẠCH — ' + DAT + ' phép: nhãn TK Nợ tra ma trận theo gian; chưa chọn gian thì nói theo gian, không đỏ oan.');
