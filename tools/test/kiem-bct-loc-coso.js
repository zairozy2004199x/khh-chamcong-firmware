/* ═══════════════════════════════════════════════════════════════════════════════════════════
 * BÁO CÁO TỔNG — LỌC CƠ SỞ NGAY TRÊN BẢNG (Ghế 2.144.0): bỏ dấu, nhiều tên, khớp mã KH; TỔNG tính theo phần đang lọc.
 * Anh Thắng 25/09/2026: "Cho lọc theo cơ sở".
 * 🔴 BẤT BIẾN TIỀN: tổng cột / tổng dòng của bảng lọc = cộng đúng các dòng còn lại — không phải tổng cả chuỗi.
 * Chạy: node tools/test/kiem-bct-loc-coso.js
 * ═══════════════════════════════════════════════════════════════════════════════════════════ */
const fs = require('fs');
const src = fs.readFileSync('vhcp-ghe/includes/class-vhg-trang.php', 'utf8');
let DAT = 0; const TRUOT = [];
function t(n, ok, them) { if (ok) { DAT++; console.log('  ✓ ' + n); } else { TRUOT.push(n); console.log('  ✗ ' + n + (them !== undefined ? (' → ' + JSON.stringify(them)) : '')); } }
function lay(ten) {
  const i = src.indexOf('function ' + ten + '(');
  if (i < 0) throw new Error('không thấy hàm ' + ten);
  let d = 0;
  for (let k = src.indexOf('{', i); k < src.length; k++) { if (src[k] === '{') d++; else if (src[k] === '}' && --d === 0) return src.slice(i, k + 1); }
  throw new Error('hàm ' + ten + ' không đóng ngoặc');
}
/* Mã nằm trong nowdoc PHP (<<<'JS') nên chuỗi giữ nguyên, không phải bỏ escape. */
const M = new Function(lay('bctKd') + '\n' + lay('bctApDungLoc') + '\nreturn { bctKd, bctApDungLoc };')();

const r = {
  ok: true, muc: 'coso', cot: 'qr', ngay: ['2026-09-01', '2026-09-02', '2026-09-03'], vqCo: 1,
  hang: [
    { coso: 'CGV VINCOM PHAN VĂN TRỊ', maKH: 'KH00248', soGhe: 2, so: [1340000, 0, 0], tong: 1340000, vq: [120000, 80000, 70000], vqTong: 270000 },
    { coso: 'Cali Thảo Điền', maKH: 'KH00270', soGhe: 4, so: [0, 0, 50000], tong: 50000, vq: [560000, 550000, 50000], vqTong: 1160000 },
    { coso: 'ESTELLA', maKH: 'KH00117', soGhe: 6, so: [190000, 0, 0], tong: 190000, vq: [340000, 1140000, 620000], vqTong: 2100000 },
    { coso: 'CM VP', maKH: '', soGhe: 0, so: [0, 0, 0], tong: 0, vq: [720000, 950000, 150000], vqTong: 1820000 }
  ],
  tongCot: [1530000, 0, 50000], tong: 1580000, soGhe: 12, vqTongCot: [1740000, 2720000, 890000], vqTong: 5350000
};
console.log('── 1. Khớp không dấu, không hoa thường, theo mã KH ─────────');
t('bctKd bỏ dấu: "Cali Thảo Điền" → "cali thao dien", "ĐÀ LẠT" → "da lat"', M.bctKd('Cali Thảo Điền') === 'cali thao dien' && M.bctKd('ĐÀ LẠT') === 'da lat', [M.bctKd('Cali Thảo Điền'), M.bctKd('ĐÀ LẠT')]);
let f = M.bctApDungLoc(r, 'cali');
t('🔴 "cali" → đúng 1 dòng Cali Thảo Điền', f.hang.length === 1 && f.hang[0].coso === 'Cali Thảo Điền', f.hang.map(g => g.coso));
f = M.bctApDungLoc(r, 'thao dien');
t('gõ không dấu "thao dien" vẫn ra', f.hang.length === 1 && f.hang[0].coso === 'Cali Thảo Điền');
f = M.bctApDungLoc(r, 'kh00117');
t('gõ mã KH "kh00117" → ESTELLA', f.hang.length === 1 && f.hang[0].coso === 'ESTELLA');
f = M.bctApDungLoc(r, 'estella, cali');
t('nhiều tên cách dấu phẩy → 2 dòng, giữ thứ tự bảng', f.hang.length === 2 && f.hang[0].coso === 'Cali Thảo Điền' && f.hang[1].coso === 'ESTELLA');
t('nhãn lọc: locSo 2 / locTong 4, locCoSo giữ nguyên chữ người gõ', f.locSo === 2 && f.locTong === 4 && f.locCoSo === 'estella, cali');
console.log('── 2. TỔNG tính theo phần đang lọc ───────────────────────');
t('🔴 tổng dòng = 50.000 + 190.000; tổng cột [190000, 0, 50000]; số ghế 4 + 6 = 10', f.tong === 240000 && JSON.stringify(f.tongCot) === '[190000,0,50000]' && f.soGhe === 10, [f.tong, f.tongCot, f.soGhe]);
t('🔴 VietQR thực cũng theo phần lọc: vqTong 3.260.000, vqTongCot [900000,1690000,670000]', f.vqTong === 3260000 && JSON.stringify(f.vqTongCot) === '[900000,1690000,670000]', [f.vqTong, f.vqTongCot]);
t('không đụng dữ liệu gốc (BCT_DATA còn nguyên 4 dòng, tổng cũ)', r.hang.length === 4 && r.tong === 1580000 && r.tongCot[0] === 1530000);
console.log('── 3. Biên ─────────────────────────────────────────────────');
t('ô lọc rỗng / toàn dấu phẩy → trả nguyên bảng (cùng đối tượng, không nhãn lọc)', M.bctApDungLoc(r, '') === r && M.bctApDungLoc(r, ' , ') === r && M.bctApDungLoc(r, null) === r);
f = M.bctApDungLoc(r, 'khong co');
t('không khớp gì → 0 dòng, tổng 0, cột toàn 0 (không lấy tổng cả chuỗi)', f.hang.length === 0 && f.tong === 0 && f.vqTong === 0 && JSON.stringify(f.tongCot) === '[0,0,0]' && f.soGhe === 0);
const rg = { muc: 'ghe', ngay: ['2026-09-01'], hang: [
  { coso: 'ESTELLA', maKH: 'KH00117', tenGhe: 'EST-1', maGhe: '80001', soGhe: 6, so: [100], tong: 100, vq: [10], vqTong: 10 },
  { coso: 'ESTELLA', maKH: 'KH00117', tenGhe: 'EST-2', maGhe: '80002', soGhe: 6, so: [200], tong: 200, vq: [20], vqTong: 20 },
  { coso: 'Cali Thảo Điền', maKH: 'KH00270', tenGhe: 'CALI-1', maGhe: '80003', soGhe: 4, so: [300], tong: 300, vq: [30], vqTong: 30 } ] };
f = M.bctApDungLoc(rg, 'estella');
t('🔴 theo TỪNG GHẾ: 2 dòng, số ghế của cơ sở đếm MỘT lần (6, không phải 12)', f.hang.length === 2 && f.soGhe === 6 && f.tong === 300, [f.hang.length, f.soGhe, f.tong]);
f = M.bctApDungLoc(rg, '80003');
t('theo từng ghế lọc được theo MÃ GHẾ / tên ghế', f.hang.length === 1 && f.hang[0].tenGhe === 'CALI-1' && M.bctApDungLoc(rg, 'est-2').hang.length === 1);
console.log('── 4. Dây nối màn hình ────────────────────────────────────');
t('ô nhập id=bct-loc trên thanh điều khiển, gõ là vẽ lại (oninput → bctVeLai), giữ giá trị khi vẽ lại thanh', src.indexOf('id="bct-loc" value="\' + esc(BCT_LOC)') > 0 && src.indexOf("lo.oninput = function(){ BCT_LOC = lo.value; bctVeLai(); }") > 0);
t('bctLoad và bctXuat đều đi qua bctApDungLoc(…, BCT_LOC)', src.indexOf('box.appendChild(bctBang(bctApDungLoc(r, BCT_LOC)));') > 0 && src.indexOf('var r = bctApDungLoc(BCT_DATA, BCT_LOC);') > 0);
t('bảng nói rõ đang lọc N/M và TỔNG theo phần lọc', src.indexOf("L('Đang lọc','Filtering')") > 0);
console.log(''); if (TRUOT.length) { console.log('🔴 TRƯỢT: ' + TRUOT.length); process.exit(1); } console.log('✓ SẠCH — ' + DAT + ' phép');
