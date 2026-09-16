/*
 * Kiểm thử engine đối chiếu với số liệu trong file Excel gốc T8/2026.
 * Chạy:  node web_baocao_chiphi/test/engine.test.js
 */
const assert = require('assert');
const path = require('path');
const fs = require('fs');
const E = require('../engine.js');

// sample-data.js gán vào window.SAMPLE_DATA → giả lập window
global.window = {};
require('../sample-data.js');
const state = E.normalizeState(window.SAMPLE_DATA);
const expected = JSON.parse(fs.readFileSync(path.join(__dirname, 'expected-T8-2026.json'), 'utf8'));

let passed = 0;
function close(actual, exp, label, tol = 0.01) {
  if (exp === null || exp === undefined || typeof exp === 'string') return; // ô trống / #REF! trong Excel: bỏ qua
  assert(Math.abs(actual - exp) <= tol, `${label}: được ${actual}, mong đợi ${exp}`);
  passed++;
}

const report = E.computeReport(state);

// ---- Mục I: 17 cột chi phí, 7 bộ phận
assert.strictEqual(report.columns.length, 17, 'phải có 17 cột chi phí (18 khoản, 2 khoản gộp)');
report.columns.forEach((c, i) => {
  assert.strictEqual(c.title, String(expected.headers[i]).trim(), `tiêu đề cột ${i + 2}`);
  passed++;
});
const order = ['posh', 'jp', 'funzone', 'event', 'farm', 'tutu', 'pinball'];
order.forEach((d) => {
  report.columns.forEach((c, i) => close(report.matrix[d][c.key], expected.section1[d][i], `Mục I ${d} cột ${c.title}`, 1));
});
report.columns.forEach((c, i) => close(report.colTotals[c.key], expected.colTotals[i], `Tổng cột ${c.title}`, 1));

// Tổng phân bổ mỗi khoản phải bằng đúng tổng tiền khoản đó
state.costItems.forEach((it) => {
  const a = E.allocateItem(state, it);
  const s = Object.values(a).reduce((x, y) => x + y, 0);
  close(s, E.num(it.total), `Bảo toàn tổng khoản "${it.name}"`, 0.01);
});

// ---- Cột nhập tay
const mc154 = report.manualCols.find((m) => m.name.includes('154'));
close(mc154.vals.posh, 80503841, 'CP 154 Posh');
close(mc154.total, 80503841 + 16253005 + 8914614 + 6514615 + 1469000, 'Tổng CP 154');

// ---- Mục III: lương NV cơ sở
const g = (t) => report.siteGroups.find((x) => x.title === t);
close(g('POSH - JP').sub.reported, expected.salarySites.poshjp.reported, 'Lương Posh-JP theo báo cáo');
close(g('POSH - JP').sub.actual, expected.salarySites.poshjp.actual, 'Lương Posh-JP thực lĩnh');
close(g('Tàu HCM').sub.reported, expected.salarySites.tutu.reported, 'Lương Tàu HCM');
close(g('Funzone HCM').sub.reported, expected.salarySites.funzone.reported, 'Lương Funzone HCM');
close(g('Event HCM').sub.reported, expected.salarySites.event.reported, 'Lương Event HCM');
close(g('Farm HCM').sub.reported, expected.salarySites.farm.reported, 'Lương Farm HCM');
close(report.salarySitesTotal.actual, expected.grandActual, 'Tổng thực lĩnh G70');

// ---- Phân bổ theo điểm (sheet "Posh T8.2026", dòng 51AMBD)
const posh = E.allocateSites(state, report, 'posh');
close(posh.sumRevenue, expected.poshSite51AMBD.sumRevenue, 'Tổng DT Posh');
const ambd = posh.rows.find((r) => r.code === '51AMBD');
close(ambd.revenue, expected.poshSite51AMBD.revenue, 'DT 51AMBD');
close(ambd.vals.luongNV, expected.poshSite51AMBD.luongNV, 'Lương NV 51AMBD');
const colByTitle = (t) => posh.cols.find((c) => c.title === t).key;
close(ambd.vals[colByTitle('Chi phí thuê kho VP')], expected.poshSite51AMBD.thueKho, 'Thuê kho 51AMBD');
close(ambd.vals[colByTitle('Chi phí chung MN')], expected.poshSite51AMBD.chungVP, 'CP chung 51AMBD');
close(ambd.vals[colByTitle('Chi phí vé máy bay MN T8/2026')], expected.poshSite51AMBD.veMayBay, 'Vé máy bay 51AMBD');
close(ambd.vals[colByTitle('Chi phí chung MN T9/2026 _ Tuyển dụng Vieclam 24h')], expected.poshSite51AMBD.vieclam, 'Vieclam24h 51AMBD');
close(ambd.vals[colByTitle('Chi phí chung MN Tháng 8/2026 _ Gift HH')], expected.poshSite51AMBD.gift, 'Gift HH 51AMBD');
close(ambd.vals[`m:${mc154.id}`], expected.poshSite51AMBD.cp154, 'CP 154 51AMBD');

const fz = E.allocateSites(state, report, 'funzone');
close(fz.sumRevenue, expected.fzSiteVRAMBD.sumRevenue, 'Tổng DT Funzone');
const vr = fz.rows.find((r) => r.code === 'VRAMBD');
close(vr.vals.luongNV, expected.fzSiteVRAMBD.luongNV, 'Lương NV VRAMBD');
close(vr.vals[fz.cols.find((c) => c.title === 'Chi phí thuê nhà NV').key], expected.fzSiteVRAMBD.thueNha, 'Thuê nhà VRAMBD');
close(vr.vals[`m:${mc154.id}`], expected.fzSiteVRAMBD.cp154, 'CP 154 VRAMBD');

// ---- Kiểm tra dữ liệu: bộ mẫu không được có lỗi mức error
const issues = E.validate(state);
assert.strictEqual(issues.filter((i) => i.level === 'error').length, 0, 'validate: không có lỗi ' + JSON.stringify(issues.filter((i) => i.level === 'error')));

// ---- Trường hợp biên
const empty = E.normalizeState(E.emptyState(9, 2026));
const r0 = E.computeReport(empty);
assert.strictEqual(r0.columns.length, 0);
assert.strictEqual(r0.grandTotal, 0);
assert(E.validate(empty).some((i) => i.level === 'error'), 'state trống phải báo lỗi doanh thu = 0');
// chia custom sai tổng
const bad = E.normalizeState(window.SAMPLE_DATA);
bad.costItems[0].split = 'custom';
bad.costItems[0].shares = { MTD: 1, KVC: 2 };
assert(E.validate(bad).some((i) => i.msg.includes('≠ tổng tiền')), 'phải phát hiện chia custom sai tổng');
// định dạng số
assert.strictEqual(E.fmt(1234567.89), '1.234.568');
assert.strictEqual(E.fmt(-1500), '(1.500)');
assert.strictEqual(E.fmt(0.6 * 100, 2), '60,00');
assert.strictEqual(E.num('1.234.567,5'), 1234567.5);
assert.strictEqual(E.num('  2 000 000 '), 2000000);

console.log(`OK — ${passed} phép so khớp với Excel đều đạt, các trường hợp biên đạt.`);

// ---- Trạng thái duyệt: khoản chờ duyệt không tính, trừ khi bật includePending; khoản từ chối không bao giờ tính
{
  const st = E.normalizeState(window.SAMPLE_DATA);
  const base = E.computeReport(st).grandTotal;
  st.costItems[0].status = 'cho_duyet';
  st.costItems[1].status = 'tu_choi';
  let r = E.computeReport(st);
  assert(Math.abs(r.grandTotal - (base - 55000000 - 10000000)) < 0.01, 'khoản chờ duyệt / từ chối phải bị loại');
  assert(E.validate(st).some((i) => i.msg.includes('chờ duyệt')), 'phải báo có khoản chờ duyệt');
  st.options.includePending = true;
  r = E.computeReport(st);
  assert(Math.abs(r.grandTotal - (base - 10000000)) < 0.01, 'includePending phải tính khoản chờ duyệt');
  console.log('OK — trạng thái duyệt.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// TỜ NHẬP MISA — "<Bộ phận> chi tiết"  (anh Thắng 16/09/2026)
//
// Luật ở đây KHÔNG do ai nghĩ ra: đọc thẳng từ file thật "File chi phí MN T8/2026" của anh Thắng,
// tab "Posh chi tiết" (20 chứng từ × 66 điểm = 1.320 dòng). Mỗi phép dưới đây trói vào một nét đã
// quan sát được ở file ấy, nên sửa mã mà lệch khỏi file thật là đỏ ngay.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const st = E.normalizeState(window.SAMPLE_DATA);
  st.period = { month: 8, year: 2026 };
  const rp = E.computeReport(st);

  // -- tách cặp tài khoản: nguồn viết KHÔNG nhất quán, cả hai dạng đều có thật trong file --
  assert.deepStrictEqual(E.parseAccount('N64131/C3341'), { no: '64131', co: '3341' });
  assert.deepStrictEqual(E.parseAccount('N64213/331'), { no: '64213', co: '331' },
    'thiếu chữ C vẫn phải đọc được — file thật có dạng này');
  assert.deepStrictEqual(E.parseAccount('  '), { no: '', co: '' });
  assert.deepStrictEqual(E.parseAccount('N64136/C1543 / N64214/C331'), { no: '64136', co: '1543' },
    'cột gộp nhiều khoản: lấy CẶP ĐẦU, không trộn số của khoản sau');

  // -- ngày: NGÀY CUỐI THÁNG CỦA KỲ, không phải hôm nay --
  assert.strictEqual(E.periodLastDay({ month: 8, year: 2026 }).text, '31/08/2026');
  assert.strictEqual(E.periodLastDay({ month: 2, year: 2024 }).text, '29/02/2024', 'năm nhuận');
  assert.strictEqual(E.periodLastDay({ month: 2, year: 2026 }).text, '28/02/2026');
  assert.strictEqual(E.periodLastDay({ month: 4, year: 2026 }).text, '30/04/2026');

  const posh = E.misaRows(st, rp, 'posh');
  assert(posh && posh.rows.length, 'phải dựng được dòng MISA cho Posh');
  const soDiem = (st.sites || []).filter((s) => s.dept === 'posh').length;
  const soCot = E.allocateSites(st, rp, 'posh').cols.length;

  // -- mỗi (cột × điểm) đúng MỘT dòng, kể cả dòng 0đ --
  assert.strictEqual(posh.rows.length, soCot * soDiem,
    `phải là ${soCot} chứng từ × ${soDiem} điểm = ${soCot * soDiem} dòng`);

  // 🔴 GIỮ DÒNG 0đ. File thật có (BZONE THẢO ĐIỀN, CENTRAL PREMIUM QUẬN 8 đều 0đ) và MISA nhận.
  // Lọc bỏ thì số dòng mỗi chứng từ đổi theo từng tháng, người đối chiếu mất mốc để đếm.
  const theoCT = {};
  posh.rows.forEach((r) => { theoCT[r.soCT] = (theoCT[r.soCT] || 0) + 1; });
  assert.strictEqual(Object.keys(theoCT).length, soCot, 'mỗi cột một số chứng từ riêng');
  Object.keys(theoCT).forEach((k) => assert.strictEqual(theoCT[k], soDiem,
    `chứng từ ${k} phải đủ ${soDiem} dòng — dòng 0đ cũng giữ`));

  // -- số chứng từ: NVK + prefix + ngày cuối tháng + tháng + số thứ tự --
  assert.strictEqual(posh.rows[0].soCT, 'NVKPOSH310801', 'đúng dạng của file thật');
  assert.strictEqual(posh.rows[soDiem].soCT, 'NVKPOSH310802', 'cột kế tiếp tăng số thứ tự');
  // So CẢ chuỗi, không cắt đầu: cắt đầu thì một mã dài/ngắn hơn vẫn lọt.
  assert.strictEqual(E.misaRows(st, rp, 'pinball').rows[0].soCT, 'NVKPBMN310801',
    'Pinball mang đuôi miền — suy từ tên sẽ ra "PINBA", một số chứng từ trông hợp lý mà sai');
  // 🔴 Dữ liệu đã lưu từ trước KHÔNG có misaPrefix — phải được gieo lúc chuẩn hoá, không thì
  // người duy nhất có dữ liệu thật lại là người duy nhất nhận số chứng từ sai.
  assert(!(window.SAMPLE_DATA.departments || []).some((d) => d.misaPrefix),
    'dữ liệu mẫu cố ý KHÔNG có misaPrefix — đó là điều kiện của phép kiểm này');
  assert.strictEqual((st.departments.find((d) => d.id === 'funzone') || {}).misaPrefix, 'FZ');

  // -- đổi kỳ thì ngày VÀ số chứng từ cùng đổi theo kỳ, không theo hôm nay --
  const st2 = E.normalizeState(window.SAMPLE_DATA);
  st2.period = { month: 2, year: 2026 };
  const p2 = E.misaRows(st2, E.computeReport(st2), 'posh');
  assert.strictEqual(p2.rows[0].ngay, '28/02/2026');
  assert.strictEqual(p2.rows[0].soCT, 'NVKPOSH280201');

  // -- TIỀN KHÔNG ĐƯỢC TÍNH LẠI: phải khớp từng đồng với sheet phân bổ của cùng bộ phận --
  const alloc = E.allocateSites(st, rp, 'posh');
  alloc.cols.forEach((c, i) => {
    const cua = posh.rows.slice(i * soDiem, (i + 1) * soDiem);
    cua.forEach((r, j) => assert(Math.abs(r.soTien - (alloc.rows[j].vals[c.key] || 0)) < 1e-9,
      `tiền dòng MISA phải bằng đúng ô trên sheet phân bổ (cột ${c.key})`));
    const tong = cua.reduce((a, r) => a + r.soTien, 0);
    assert(Math.abs(tong - (alloc.totals[c.key] || 0)) < 0.01,
      `cộng một chứng từ phải bằng tổng cột ${c.key} trên sheet phân bổ`);
  });

  // -- lời: hai cột nói hai chuyện, và KHÔNG được để trống (MISA bắt buộc "Diễn giải") --
  posh.rows.forEach((r) => {
    assert(String(r.dienGiai).trim() !== '', 'Diễn giải không được trống');
    assert(String(r.dienGiaiHT).trim() !== '', 'Diễn giải (Hạch toán) không được trống');
  });
  assert(posh.rows[0].dienGiaiHT.endsWith(' - ' + alloc.rows[0].name),
    'Diễn giải (Hạch toán) = lời của khoản + " - " + tên điểm');
  // Tên điểm đã mang sẵn "POSH MN …" nên không được chèn tên bộ phận lần nữa
  assert.strictEqual((posh.rows[0].dienGiaiHT.match(/ - /g) || []).length, 1,
    'chỉ một dấu " - " ngăn khoản với điểm');

  // -- mã đơn vị đi theo ĐÚNG điểm của dòng đó --
  posh.rows.forEach((r, i) => assert.strictEqual(r.maDonVi, alloc.rows[i % soDiem].code));

  // -- lương NV và lương vận hành là HAI chứng từ, HAI câu diễn giải khác nhau --
  const sal = st.salaryDept.find((x) => x.dept === 'posh') || {};
  sal.misaGeneral = 'Chi phí Lương 1 Posh MN Tháng 8/2026';
  sal.misaDetail = 'Chi Phí Lương nhân viên cơ sở';
  sal.misaGeneral2 = 'Chi phí Lương vận hành BP Posh MN Tháng 8/2026';
  sal.misaDetail2 = 'Chi phí Lương vận hành BP Posh';
  const p3 = E.misaRows(st, E.computeReport(st), 'posh');
  assert.strictEqual(p3.rows[0].dienGiai, 'Chi phí Lương 1 Posh MN Tháng 8/2026');
  assert.strictEqual(p3.rows[soDiem].dienGiai, 'Chi phí Lương vận hành BP Posh MN Tháng 8/2026',
    'lương vận hành phải mang lời CỦA NÓ, không dùng lại lời của lương nhân viên');
  assert.notStrictEqual(p3.rows[0].dienGiaiHT, p3.rows[soDiem].dienGiaiHT);

  // -- bộ phận không có điểm nào thì không dựng tờ nhập, và không nổ --
  const st4 = E.normalizeState(window.SAMPLE_DATA);
  st4.sites = st4.sites.filter((s) => s.dept !== 'farm');
  const r4 = E.misaRows(st4, E.computeReport(st4), 'farm');
  assert(r4 && r4.rows.length === 0, 'bộ phận rỗng: trả về danh sách rỗng, không nổ');
  assert(E.misaRows(st, rp, 'khong-co-that') === null, 'bộ phận không tồn tại: trả null');

  // -- HAI CỘT LƯƠNG PHẢI CÓ TÀI KHOẢN. Mô hình cũ không có chỗ nào khai, nên mọi dòng lương lên
  //    tờ nhập với TK Nợ/TK Có TRỐNG — và MISA từ chối CẢ chứng từ, không riêng dòng ấy.
  const stL = E.normalizeState(window.SAMPLE_DATA);
  const salL = stL.salaryDept.find((x) => x.dept === 'posh');
  assert.strictEqual(salL.misaAccount, 'N64131/C3341', 'gieo từ file thật T8/2026');
  assert.strictEqual(salL.misaAccount2, 'N64131/C3341');
  const pL = E.misaRows(stL, E.computeReport(stL), 'posh');
  assert.strictEqual(pL.rows[0].tkNo, '64131');
  assert.strictEqual(pL.rows[0].tkCo, '3341');
  assert.strictEqual(pL.rows[soDiem].tkNo, '64131', 'cột lương vận hành cũng phải có');
  // người dùng tự đặt thì GIỮ, mặc định chỉ để mồi
  const stL2 = E.normalizeState({ ...window.SAMPLE_DATA,
    salaryDept: (window.SAMPLE_DATA.salaryDept || []).map((r) =>
      (r.dept === 'posh' ? { ...r, misaAccount: 'N6421/C331' } : r)) });
  assert.strictEqual(stL2.salaryDept.find((x) => x.dept === 'posh').misaAccount, 'N6421/C331');

  // -- thiếu tài khoản phải CẢNH BÁO, không im lặng --
  const canhBao = E.validate(st).filter((i) => i.msg.includes('cặp tài khoản'));
  assert(canhBao.length && canhBao.every((i) => i.level === 'warn'),
    'khoản thiếu tài khoản phải ra cảnh báo mức warn');

  console.log('OK — tờ nhập MISA "<Bộ phận> chi tiết".');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// CƠ SỞ NGHỈ / ĐÓNG CỬA — vẫn ghi doanh thu, KHÔNG nhận chi phí  (anh Thắng 16/09/2026)
//
// Bộ số dưới đây LẤY THẲNG từ tab "Pinball T8.2026" trong file thật T8/2026: 3 điểm, SC VIVO nghỉ.
// Nhờ có số thật nên bài kiểm trả lời được câu quan trọng nhất — tiền của cơ sở nghỉ ĐI ĐÂU:
// nó được hai điểm còn lại GÁNH LẠI, tổng bộ phận không đổi một đồng.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const TONG_CP = 10061700;            // tổng "Lương NV" của Pinball trên file thật
  const DT = { AMTP: 47400000, SCVV: 120000, LMPT: 35060838 };
  const st = E.normalizeState(window.SAMPLE_DATA);
  st.period = { month: 8, year: 2026 };
  st.sites = [
    { dept: 'pinball', code: 'PBAMTP', name: 'PINBALL MN AMTP', revenue: DT.AMTP },
    { dept: 'pinball', code: 'PBSCVV', name: 'PINBALL MN SC VIVO', revenue: DT.SCVV },
    { dept: 'pinball', code: 'PBLMPT', name: 'PINBALL MN LMPT', revenue: DT.LMPT },
  ];
  // ép đúng một cột chi phí bằng tổng thật, để so được với con số trong file
  st.salarySites = [];
  st.salaryDept = st.salaryDept.map((r) => (r.dept === 'pinball'
    ? { ...r, luongVP: 0, luongVanHanh: TONG_CP, luongQLAC: 0, luongAlex: 0, luongKPI: 0, bonus: 0 } : r));

  // -- CHƯA tích: chia theo cả 3 điểm --
  let rp = E.computeReport(st);
  let al = E.allocateSites(st, rp, 'pinball');
  const truoc = al.rows.find((r) => r.code === 'PBSCVV').vals.luongVanHanh;
  assert(truoc > 0, 'chưa tích thì cơ sở vẫn nhận chi phí');

  // -- TÍCH "không nhận chi phí" cho SC VIVO --
  st.sites[1].khongChiPhi = true;
  rp = E.computeReport(st);
  al = E.allocateSites(st, rp, 'pinball');
  const g = (c) => al.rows.find((r) => r.code === c);

  // 1. Doanh thu VẪN ghi nhận — cả ở dòng của nó lẫn ở tổng bộ phận
  assert.strictEqual(g('PBSCVV').revenue, DT.SCVV, 'vẫn ghi doanh thu của cơ sở nghỉ');
  assert.strictEqual(al.sumRevenue, DT.AMTP + DT.SCVV + DT.LMPT, 'tổng doanh thu gồm CẢ cơ sở nghỉ');
  assert.strictEqual(al.sumRevenueCP, DT.AMTP + DT.LMPT, 'mẫu số chia chi phí BỎ cơ sở nghỉ');
  assert.strictEqual(rp.revenue.pinball, DT.AMTP + DT.SCVV + DT.LMPT,
    'doanh thu bộ phận trên File tổng báo cáo KHÔNG được đổi — tích ô này chỉ động tới chi phí');

  // 2. Cơ sở nghỉ: MỌI cột chi phí = 0, nhưng VẪN CÒN DÒNG ở sheet phân bổ (như file thật)
  assert.strictEqual(g('PBSCVV').weight, 0);
  assert.strictEqual(g('PBSCVV').total, 0);
  al.cols.forEach((c) => assert.strictEqual(g('PBSCVV').vals[c.key], 0, `cột ${c.key} phải 0`));
  assert.strictEqual(al.rows.length, 3, 'vẫn đủ 3 dòng — doanh thu của nó phải đọc được');

  // 3. 🔴 TIỀN CHIA LẠI, KHÔNG MẤT — đối chiếu TỪNG ĐỒNG với file thật
  assert(Math.abs(g('PBAMTP').vals.luongVanHanh - 5783649.445813296) < 0.01,
    'AMTP phải khớp đúng con số trong file thật T8/2026');
  assert(Math.abs(g('PBLMPT').vals.luongVanHanh - 4278050.554186704) < 0.01,
    'LMPT phải khớp đúng con số trong file thật T8/2026');
  assert(Math.abs(al.totals.luongVanHanh - TONG_CP) < 0.01,
    'tổng cột KHÔNG đổi — tiền của cơ sở nghỉ được hai điểm kia gánh lại, không rơi mất');

  // 4. Tờ nhập MISA BỎ HẲN dòng của cơ sở nghỉ (không phải đẩy một dòng 0đ)
  const mi = E.misaRows(st, rp, 'pinball');
  const maDV = {};
  mi.rows.forEach((r) => { maDV[r.maDonVi] = (maDV[r.maDonVi] || 0) + 1; });
  assert.deepStrictEqual(Object.keys(maDV).sort(), ['PBAMTP', 'PBLMPT'],
    'chỉ hai mã đơn vị — y như tab "Pinball chi tiết" của file thật');
  assert(!mi.rows.some((r) => r.maDonVi === 'PBSCVV'),
    'không được đẩy bút toán 0đ cho cơ sở đã nghỉ — đó vẫn là ghi nhận chi phí cho nó');
  const ct1 = mi.rows.filter((r) => r.soCT === mi.rows[0].soCT);
  assert.strictEqual(ct1.length, 2, 'mỗi chứng từ còn 2 dòng, không phải 3');

  // 5. Bỏ tích thì mọi thứ quay lại y như cũ — không để lại dấu vết
  st.sites[1].khongChiPhi = false;
  const al2 = E.allocateSites(st, E.computeReport(st), 'pinball');
  assert(Math.abs(al2.rows.find((r) => r.code === 'PBSCVV').vals.luongVanHanh - truoc) < 1e-9);

  // 6. Cả bộ phận đều nghỉ = chi phí bốc hơi -> phải báo LỖI, không im lặng
  const stX = E.normalizeState({ ...window.SAMPLE_DATA,
    sites: st.sites.map((x) => ({ ...x, khongChiPhi: true })) });
  const loi = E.validate(stX).filter((i) => i.msg.includes('KHÔNG được phân bổ'));
  assert(loi.length && loi[0].level === 'error',
    'mọi cơ sở đều nghỉ: phải là LỖI — không thì chi phí biến mất mà File tổng báo cáo vẫn cộng đủ');

  // 7. Có cơ sở nghỉ thì nói ra cho người dùng biết
  const tin = E.validate(st).filter((i) => i.msg.includes('không nhận chi phí'));
  st.sites[1].khongChiPhi = true;
  assert(E.validate(st).some((i) => i.msg.includes('PBSCVV')), 'phải nêu đích danh cơ sở đang nghỉ');

  console.log('OK — cơ sở nghỉ: giữ doanh thu, không nhận chi phí, tiền chia lại đủ.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// MÃ ĐỐI TƯỢNG CÓ — cột 15 của mẫu MISA  (anh Thắng 16/09/2026: "Đọc file để lấy đủ cột chứ em")
//
// Đếm trên file thật T8/2026: trong 50 cột của mẫu, CHỈ 11 cột có dữ liệu. Bản trước điền 10 —
// bỏ sót đúng "Mã đối tượng Có" (1.497 ô, mã nhà cung cấp dạng CC00004 / CC00458…).
//
// Luật đọc được từ file: MỘT mã cho cả chứng từ, và chỉ có khi TK Có = 331 (phải trả người bán);
// chứng từ lương (TK Có 3341) và phân bổ 1543 thì để TRỐNG — không có nhà cung cấp nào để trỏ tới.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const st = E.normalizeState(window.SAMPLE_DATA);
  st.period = { month: 8, year: 2026 };
  // đặt mã nhà cung cấp cho một khoản, y như file thật
  const it = st.costItems.find((x) => (x.account || '').trim());
  it.objectCode = 'CC00004';
  const rp = E.computeReport(st);
  const mi = E.misaRows(st, rp, 'posh');

  const soDiem = (st.sites || []).filter((x) => x.dept === 'posh').length;
  const cua = mi.rows.filter((r) => r.maDoiTuongCo === 'CC00004');
  assert.strictEqual(cua.length, soDiem, 'mã nhà cung cấp phải phủ ĐỦ mọi dòng của chứng từ đó');
  assert.strictEqual(new Set(cua.map((r) => r.soCT)).size, 1, 'và chỉ nằm trong ĐÚNG một chứng từ');

  // Hai cột lương để TRỐNG — TK Có 3341 không có nhà cung cấp
  assert.strictEqual(mi.rows[0].maDoiTuongCo, '', 'chứng từ lương phải để trống Mã đối tượng Có');
  assert.strictEqual(mi.rows[soDiem].maDoiTuongCo, '', 'lương vận hành cũng vậy');

  // Mã đối tượng Nợ: file thật để trống TOÀN BỘ cột
  assert(mi.rows.every((r) => r.maDoiTuongNo === ''), 'Mã đối tượng Nợ để trống, đúng như file thật');

  // 🔴 Cột GỘP nhiều khoản khác nhà cung cấp: lấy MÃ ĐẦU TIÊN, không nối " / ".
  //    Đây là một ô MÃ của MISA — nối hai mã vào là MISA không tra ra đối tượng nào và từ chối
  //    cả chứng từ. (`account` thì nối được vì parseAccount chỉ bốc cặp số đầu.)
  const st2 = E.normalizeState(window.SAMPLE_DATA);
  const hai = st2.costItems.filter((x) => (x.account || '').trim()).slice(0, 2);
  hai[0].groupKey = 'gop'; hai[1].groupKey = 'gop';
  hai[0].objectCode = 'CC00111'; hai[1].objectCode = 'CC00222';
  const cot = E.computeReport(st2).columns.find((c) => c.key === 'g:gop');
  assert.strictEqual(cot.objectCode, 'CC00111', 'cột gộp: lấy mã đầu tiên, KHÔNG nối hai mã');
  assert(!/\//.test(cot.objectCode), 'không được có dấu "/" trong ô mã');

  // Khoản không khai mã thì để trống, không bịa
  const st3 = E.normalizeState(window.SAMPLE_DATA);
  st3.costItems.forEach((x) => { x.objectCode = ''; });
  const mi3 = E.misaRows(st3, E.computeReport(st3), 'posh');
  assert(mi3.rows.every((r) => r.maDoiTuongCo === ''), 'chưa khai mã thì để trống');

  /* ═════════════════════════════════════════════════════════════════════════════════════════
   * 🔴 NGƯỜI GÁC: MẪU 50 CỘT VÀ NHỮNG CỘT PHẢI ĐIỀN.
   *
   * Anh Thắng 16/09/2026: *"Đọc file để lấy đủ cột chứ em"* — bản trước bỏ sót "Mã đối tượng Có"
   * vì chỉ nhìn mấy dòng đầu của file rồi suy ra, thay vì ĐẾM xem cột nào thật sự có dữ liệu.
   *
   * Hai con số dưới đây ĐẾM TRÊN CẢ 7 TAB "… chi tiết" của file thật T8/2026 (1.849 dòng):
   *   · mẫu có đúng 50 cột, giống hệt nhau ở cả 7 tab
   *   · trong đó 11 cột có dữ liệu — theo chỉ số 1-based:
   *       1 Ngày chứng từ · 2 Ngày hạch toán · 3 Số chứng từ · 4 Diễn giải ·
   *       9 Diễn giải (Hạch toán) · 10 TK Nợ · 11 TK Có · 12 Số tiền · 13 Số tiền quy đổi ·
   *       15 Mã đối tượng Có · 23 Mã đơn vị
   *   · 39 cột còn lại TRỐNG SẠCH ở cả 1.849 dòng
   *
   * Bài này chốt lại con số ấy. Sau này ai thêm cột vào mẫu mà quên nối dữ liệu — hoặc ngược lại,
   * nối một cột mà file gốc không hề dùng — là đỏ ngay tại đây, không phải đợi kế toán phát hiện
   * lúc nhập vào MISA.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  const EXP = require('../exporter.js');
  const CO_DU_LIEU = [1, 2, 3, 4, 9, 10, 11, 12, 13, 15, 23];   // 1-based, đếm từ file thật
  assert.strictEqual(EXP.MISA_COLS.length, 50, 'mẫu MISA phải đúng 50 cột');
  const dienVao = [...new Set(EXP.MISA_MAP.map((m) => m.i + 1))].sort((a, b) => a - b);
  assert.deepStrictEqual(dienVao, CO_DU_LIEU,
    'những cột app điền phải khớp ĐÚNG những cột có dữ liệu trong file gốc T8/2026');
  // và bảng phải xếp theo thứ tự cột — màn hình vẽ theo đúng thứ tự này
  const thuTu = EXP.MISA_MAP.map((m) => m.i);
  assert.deepStrictEqual(thuTu, [...thuTu].sort((a, b) => a - b),
    'MISA_MAP phải xếp theo chỉ số cột tăng dần, không thì cột trên màn hình lệch khỏi file');
  // mọi key trong bảng phải là trường có thật của một dòng misaRows()
  const mau = mi.rows[0];
  EXP.MISA_MAP.forEach((m) => assert(m.key in mau, `MISA_MAP trỏ tới trường không có: ${m.key}`));

  console.log('OK — Mã đối tượng Có (cột 15) đi đúng đường; mẫu 50 cột / 11 cột điền khớp file gốc.');
}
