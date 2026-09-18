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

  /* 🔴 MỖI BỘ PHẬN MỘT TÀI KHOẢN LƯƠNG RIÊNG — anh Thắng 16/09/2026: *"sai nữa rồi"*, màn hình
     hiện 64131 cho Event trong khi file thật ghi 64191. Bản trước đọc mỗi tab Posh rồi gieo
     64131 cho cả bảy. Bảng dưới đây ĐẾM TỪ CẢ 7 TAB "… chi tiết" của file thật T8/2026.
     Sai một số thì chứng từ vẫn nhập được vào MISA, chỉ là chi phí lương của bộ phận này chạy
     vào tài khoản của bộ phận khác — sổ vẫn cân, chỉ sai chỗ, nên soát sổ không bắt được. */
  const TK_THAT = { tutu: '64101', jp: '64111', funzone: '64121', posh: '64131',
    farm: '64161', pinball: '64171', event: '64191' };
  Object.keys(TK_THAT).forEach((d) => {
    const r = stL.salaryDept.find((x) => x.dept === d);
    assert.strictEqual(r.misaAccount, `N${TK_THAT[d]}/C3341`, `TK lương của "${d}" phải là ${TK_THAT[d]}`);
    assert.strictEqual(r.misaAccount2, `N${TK_THAT[d]}/C3341`, `cột lương vận hành của "${d}" cũng vậy`);
    const m = E.misaRows(stL, E.computeReport(stL), d);
    if (m && m.rows.length) {
      assert.strictEqual(m.rows[0].tkNo, TK_THAT[d], `dòng MISA của "${d}" phải mang TK ${TK_THAT[d]}`);
      assert.strictEqual(m.rows[0].tkCo, '3341');
    }
  });
  // Bảy bộ phận phải ra BẢY số khác nhau — gieo trùng là lỗi đã mắc
  const daCo = stL.salaryDept.map((r) => r.misaAccount);
  assert.strictEqual(new Set(daCo).size, 7, 'bảy bộ phận phải có bảy tài khoản lương khác nhau');

  // Bộ phận lạ (người dùng tự thêm): KHÔNG bịa số, để trống cho validate() nhắc
  const stZ = E.normalizeState({ ...window.SAMPLE_DATA,
    departments: [...window.SAMPLE_DATA.departments, { id: 'moi', name: 'Bộ phận mới', group: 'KVC', ratio: 0 }],
    salaryDept: [...(window.SAMPLE_DATA.salaryDept || []), { dept: 'moi' }] });
  assert.strictEqual((stZ.salaryDept.find((x) => x.dept === 'moi') || {}).misaAccount, '',
    'bộ phận lạ: để trống, không bịa một số trông hợp lý');
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

  /* ═════════════════════════════════════════════════════════════════════════════════════════
   * FILE RIÊNG CHO MISA — anh Thắng 16/09/2026: *"xuất file MISA KHÔNG ĐÚNG CỘT"*.
   *
   * Cột trong sheet "<Bộ phận> chi tiết" vốn đã đúng (đã đối chiếu từng ô với file gốc). Vấn đề
   * là FILE: nút cũ xuất workbook 17 sheet, mà sheet ĐẦU TIÊN là "File tổng báo cáo" — bố cục
   * hoàn toàn khác. Đưa nguyên file ấy cho MISA thì nó đọc trúng sheet đầu rồi báo sai cột.
   *
   * Nên có `buildMisaWorkbook()`: file CHỈ gồm tờ nhập, mở lên là đúng thứ cần nhập.
   * ═════════════════════════════════════════════════════════════════════════════════════════ */
  const XL = { utils: {
    book_new: () => ({ SheetNames: [], Sheets: {} }),
    book_append_sheet: (wb, ws, n) => { wb.SheetNames.push(n); wb.Sheets[n] = ws; },
    aoa_to_sheet: (aoa) => ({ '!ref': `A1:BX${aoa.length}`, __aoa: aoa }),
    /* aoaToSheet quét ô bằng decode_range().s/.e — stub phải trả ĐÚNG hình dạng ấy, không thì
       nổ ngay ở dòng đầu. Giữ kèm `__src` để còn kiểm được chuỗi gộp đã truyền vào. */
    decode_range: (r) => ({ s: { r: 0, c: 0 }, e: { r: 0, c: 0 }, __src: r }),
    decode_cell: () => ({ r: 0, c: 0 }),
    encode_cell: () => 'A1',
    encode_col: (c) => String(c),
  } };
  const wbM = EXP.buildMisaWorkbook(XL, st, rp, 'posh');
  assert.strictEqual(wbM.SheetNames.length, 1, 'xuất một bộ phận thì file chỉ có MỘT sheet');
  assert(/chi tiết$/.test(wbM.SheetNames[0]), 'và sheet ĐẦU TIÊN phải là tờ nhập, không phải báo cáo');
  const wbA = EXP.buildMisaWorkbook(XL, st, rp, '');
  assert(wbA.SheetNames.length > 1 && wbA.SheetNames.every((n) => /chi tiết$/.test(n)),
    'xuất cả loạt thì MỌI sheet đều là tờ nhập — không lẫn sheet báo cáo nào');
  // dòng nhãn phải gộp đúng như mẫu (đo từ file gốc)
  const wsM = wbM.Sheets[wbM.SheetNames[0]];
  assert.deepStrictEqual((wsM['!merges'] || []).map((x) => x.__src), ['I1:AE1', 'AF1:AX1'],
    'hai ô gộp của dòng nhãn — đo từ file gốc T8/2026');
  // tên file nói rõ là tờ MISA của kỳ nào
  assert(/^MISA_.*T08_2026\.xlsx$/.test(EXP.misaFileName(st, st.departments[0])),
    EXP.misaFileName(st, st.departments[0]));

  console.log('OK — Mã đối tượng Có (cột 15), mẫu 50 cột / 11 cột điền, và file riêng cho MISA.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// GHÉP DOANH THU FABi  (anh Thắng 18/09/2026: "lấy đẩy doanh thu từ doanh thu hcm sang báo cáo tổng")
//
// 13 tên cửa hàng dưới đây LẤY ĐÚNG từ trang khmatrix.com/doanh-thu-hcm anh Thắng gửi — kể cả
// cái ngoặc dính liền "(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ" và mấy tên bị cắt cụt giữa chừng.
// Tên thật mới bày ra được chỗ khó: bên FABi là tên quán, bên này là tên điểm trong sổ kế toán.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const FABI = [
    { cua_hang: '(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ', thanh_tien: 18225000, so_ngay: 7 },
    { cua_hang: 'COFFE GO AN LẠC ( Dịch Vụ và Giải', thanh_tien: 620000, so_ngay: 5 },
    { cua_hang: 'ECO FARM LOTTE PHAN THIẾT ( Dịch V', thanh_tien: 6935000, so_ngay: 7 },
    { cua_hang: 'FUNZONE ADVENTURE GO AN LẠC ( Dịch', thanh_tien: 2265000, so_ngay: 4 },
    { cua_hang: 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và', thanh_tien: 27667000, so_ngay: 7 },
    { cua_hang: 'TuTu Train - Aeon Tân Phú ( Dịch V', thanh_tien: 29905000, so_ngay: 7 },
    { cua_hang: 'TuTu Train - Lotte Gò Vấp ( Dịch v', thanh_tien: 10440000, so_ngay: 7 },
    { cua_hang: 'Tutu Train - Aeon Tân An ( Dịch v', thanh_tien: 3580000, so_ngay: 7 },
    { cua_hang: 'Tutu Train - Aeon Bình Tân ( Dịch', thanh_tien: 0, so_ngay: 7 },
    { cua_hang: 'Tutu Train - Bình Dương ( Dịch Vụ', thanh_tien: 12045000, so_ngay: 7 },
    { cua_hang: 'Tutu Train - Estella ( Dịch vụ K&', thanh_tien: 20770000, so_ngay: 6 },
    { cua_hang: 'VR FUN - SC Vivo Q7 ( Dịch Vụ và G', thanh_tien: 3680000, so_ngay: 7 },
    { cua_hang: 'VR Fun Aeon Tân An ( Dịch Vụ K&H )', thanh_tien: 3120000, so_ngay: 7 },
  ];

  // -- khoá tên: bỏ dấu, bỏ ký tự lạ --
  assert.strictEqual(E.khoaTen('TuTu Train - Aeon Tân Phú'), 'TUTUTRAINAEONTANPHU');
  assert.strictEqual(E.khoaTen('(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ'), 'GHOSTBRIDEBARIACODAUAMPHU');
  assert.strictEqual(E.khoaTen('Đầm Sen'), 'DAMSEN', 'chữ đ phải thành d');
  assert.strictEqual(E.khoaTen(null), '');

  // -- cắt đuôi pháp nhân: nó có ở MỌI cửa hàng nên không phân biệt được gì --
  assert.strictEqual(E.tenGonFabi('FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )'), 'FUNZONE CITY VŨNG TÀU');
  assert.strictEqual(E.tenGonFabi('VR Fun Aeon Tân An ( Dịch Vụ K&H )'), 'VR Fun Aeon Tân An');
  assert.strictEqual(E.tenGonFabi('(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ'), '(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ',
    'mở ngoặc ngay từ đầu thì KHÔNG cắt — cắt là mất sạch tên');

  const st = E.normalizeState(window.SAMPLE_DATA);
  st.sites = [
    { dept: 'tutu', code: 'TTAMTP', name: 'TUTU MN AEON TÂN PHÚ', revenue: 0 },
    { dept: 'tutu', code: 'TTLGV', name: 'TUTU MN LOTTE GÒ VẤP', revenue: 0 },
    { dept: 'tutu', code: 'TTEST', name: 'TUTU MN ESTELLA', revenue: 0 },
    { dept: 'funzone', code: 'FZVT', name: 'FUNZONE CITY VŨNG TÀU', revenue: 0 },
    { dept: 'event', code: 'EVGBBR', name: 'EVENT MN GHOST BRIDE BÀ RỊA', revenue: 0 },
    { dept: 'farm', code: 'FALPT', name: 'FARM MN LOTTE PHAN THIẾT', revenue: 0 },
    { dept: 'posh', code: 'KHONGLIENQUAN', name: 'POSH MN AEON MALL BÌNH DƯƠNG', revenue: 0 },
  ];

  const g = E.ghepFabi(st, FABI);
  assert.strictEqual(g.length, 13, 'mọi cửa hàng đều phải có một dòng, kể cả khi không ghép được');
  const tim = (t) => g.find((x) => x.cua_hang.indexOf(t) === 0);
  const ten = (x) => (x.siteIndex === null ? null : st.sites[x.siteIndex].code);

  // -- tên trùng khít --
  assert.strictEqual(ten(tim('FUNZONE CITY')), 'FZVT');
  assert.strictEqual(tim('FUNZONE CITY').cach, 'ten', 'trùng khít thì phải là "ten", không phải đoán gần');

  // -- gần đúng: đủ từ đặc trưng --
  assert.strictEqual(ten(tim('TuTu Train - Aeon Tân Phú')), 'TTAMTP');
  assert.strictEqual(ten(tim('TuTu Train - Lotte Gò Vấp')), 'TTLGV');
  assert.strictEqual(ten(tim('Tutu Train - Estella')), 'TTEST');
  assert.strictEqual(ten(tim('(GHOST BRIDE')), 'EVGBBR');
  assert.strictEqual(ten(tim('ECO FARM LOTTE PHAN THIẾT')), 'FALPT');

  // 🔴 KHÔNG ĐƯỢC ĐOÁN BỪA. Mấy quán này không có điểm tương ứng trong danh sách.
  assert.strictEqual(ten(tim('COFFE GO AN LẠC')), null, 'không có điểm nào khớp thì để trống');
  assert.strictEqual(tim('COFFE GO AN LẠC').cach, 'khong');
  assert.strictEqual(ten(tim('Tutu Train - Aeon Tân An')), null,
    '"Aeon Tân An" và "Aeon Tân Phú" chỉ hơn nhau một từ — bằng điểm thì thà để trống');

  // 🔴 MỘT ĐIỂM CHỈ NHẬN MỘT CỬA HÀNG. Hai quán cùng ghép vào một điểm là doanh thu đè lên nhau,
  //    mà tổng vẫn ra một con số trông hợp lý.
  const daDung = g.map(ten).filter(Boolean);
  assert.strictEqual(new Set(daDung).size, daDung.length, 'không điểm nào bị hai cửa hàng nhận');

  // -- POSH không được dính vào: nó không có mặt ở FABi --
  assert(!daDung.includes('KHONGLIENQUAN'), 'điểm không liên quan phải đứng ngoài');

  // -- NHỚ LỰA CHỌN: lần sau ghép bằng `da_luu`, không phải đoán lại --
  const kq = E.napFabi(st, g);
  assert.strictEqual(kq.xong, daDung.length);
  assert.strictEqual(kq.boQua, 13 - daDung.length, 'cửa hàng không ghép được thì KHÔNG ghi bừa');
  assert.strictEqual(st.sites.find((s) => s.code === 'TTAMTP').revenue, 29905000);
  assert.strictEqual(st.sites.find((s) => s.code === 'KHONGLIENQUAN').revenue, 0, 'điểm ngoài cuộc giữ nguyên');
  const g2 = E.ghepFabi(st, FABI);
  assert.strictEqual(g2.find((x) => x.cua_hang.indexOf('TuTu Train - Aeon Tân Phú') === 0).cach, 'da_luu',
    'tháng sau phải ghép bằng lựa chọn đã lưu, không đoán lại');

  // -- người dùng sửa tay thì phải theo, kể cả khi máy đoán khác --
  const g3 = E.ghepFabi(st, FABI);
  const iCoffe = g3.findIndex((x) => x.cua_hang.indexOf('COFFE') === 0);
  g3[iCoffe].siteIndex = st.sites.findIndex((s) => s.code === 'FZVT');
  // ...nhưng FZVT đang là của FUNZONE CITY, nên bỏ nó ra trước cho khỏi đè
  g3.find((x) => x.cua_hang.indexOf('FUNZONE CITY') === 0).siteIndex = null;
  E.napFabi(st, g3);
  assert.strictEqual(st.sites.find((s) => s.code === 'FZVT').revenue, 620000, 'sửa tay phải thắng');
  assert.strictEqual(st.sites.find((s) => s.code === 'FZVT').fabiTen.indexOf('COFFE'), 0);

  console.log('OK — ghép doanh thu FABi: khớp tên, không đoán bừa, nhớ lựa chọn.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// BẮT ĐẦU KỲ MỚI  (anh Thắng 18/09/2026: "ủa, nếu chọn kỳ tháng 9 nó phải trống chứ")
//
// Kỳ mới mở ra mà đã có sẵn một bộ số trông hoàn chỉnh — cộng đúng, tỷ trọng đẹp — nhưng là số
// của THÁNG TRƯỚC, là loại sai tệ nhất: không dòng nào báo, không ô nào đỏ.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const cu = E.normalizeState(window.SAMPLE_DATA);
  cu.sites[0].khongChiPhi = true;
  cu.sites[0].fabiTen = 'TuTu Train - Estella ( Dịch vụ K&H )';
  const bcCu = E.computeReport(cu);
  assert(bcCu.grandTotal > 0 && Object.values(bcCu.revenue).some((v) => v > 0), 'kỳ cũ phải có số thật');

  const moi = E.batDauKyMoi(cu);
  const bc = E.computeReport(moi);

  // -- SỐ về 0 hết --
  assert(moi.sites.every((s) => s.revenue === 0), 'doanh thu từng điểm về 0');
  assert(Object.values(bc.revenue).every((v) => v === 0), 'doanh thu từng bộ phận về 0');
  assert.strictEqual(bc.grandTotal, 0, 'tổng chi phí phân bổ về 0');
  assert(moi.costItems.every((it) => E.num(it.total) === 0), 'tiền từng khoản về 0');
  assert(moi.salaryDept.every((r) => E.SALARY_DEPT_FIELDS.every((f) => E.num(r[f.key]) === 0)), 'lương bộ phận về 0');
  assert(moi.salarySites.every((r) => E.num(r.reported) === 0 && E.num(r.actual) === 0), 'lương NV cơ sở về 0');
  assert(moi.manualCols.every((m) => !Object.keys(m.values || {}).length), 'cột nhập tay về rỗng');

  // -- DANH MỤC giữ nguyên: đó là công dựng một lần dùng mãi --
  assert.strictEqual(moi.sites.length, cu.sites.length, 'giữ đủ điểm bán');
  assert.strictEqual(moi.sites[0].code, cu.sites[0].code);
  assert.strictEqual(moi.sites[0].name, cu.sites[0].name);
  assert.strictEqual(moi.sites[0].dept, cu.sites[0].dept);
  assert.strictEqual(moi.departments.length, cu.departments.length);
  assert.strictEqual(moi.costItems.length, cu.costItems.length, 'giữ danh mục khoản chi phí');
  const it0 = moi.costItems[0], c0 = cu.costItems[0];
  assert.strictEqual(it0.name, c0.name);
  assert.strictEqual(it0.account, c0.account, 'giữ tài khoản — khai lại hằng tháng là chỗ sinh lỗi');
  assert.strictEqual(it0.misaDetail, c0.misaDetail);
  assert.strictEqual(it0.split, c0.split, 'giữ cách chia');

  // 🔴 GIỮ `fabiTen` và `khongChiPhi` — đó là DANH MỤC, không phải số. Xoá đi thì tháng nào cũng
  //    phải ghép lại tên cửa hàng FABi và tích lại cơ sở nghỉ.
  assert.strictEqual(moi.sites[0].khongChiPhi, true, 'giữ tích "không nhận chi phí"');
  assert.strictEqual(moi.sites[0].fabiTen, cu.sites[0].fabiTen, 'giữ liên kết với cửa hàng FABi');
  // và tài khoản lương theo bộ phận cũng phải còn
  assert.strictEqual(moi.salaryDept.find((r) => r.dept === 'event').misaAccount, 'N64191/C3341');

  // -- kỳ mới thì chưa ai duyệt gì --
  assert(moi.costItems.every((it) => it.status === 'cho_duyet'), 'khoản chi phí về "chờ duyệt"');

  // -- KHÔNG đụng vào state gốc --
  assert(cu.sites.some((s) => E.num(s.revenue) > 0), 'state cũ phải còn nguyên số của nó');

  console.log('OK — bắt đầu kỳ mới: giữ danh mục, xoá sạch số.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// LIÊN KẾT SỐNG VỚI FABi  (anh Thắng 18/09/2026: "tự link và lấy dữ liệu realtime qua")
//
// Đường TỰ ĐỘNG khác đường bấm-nút ở một điểm sống còn: nó CHỈ đi theo liên kết người đã chốt,
// tuyệt đối không đoán tên. Đoán thì phải có người nhìn; chạy ngầm mà đoán là doanh thu tự nhảy
// vào nhầm điểm, không ai bấm gì, không ai biết gì.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const st = E.normalizeState(window.SAMPLE_DATA);
  st.sites = [
    { dept: 'tutu', code: 'TTAMTP', name: 'TUTU MN AEON TÂN PHÚ', revenue: 0, fabiTen: 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )' },
    { dept: 'tutu', code: 'TTEST', name: 'TUTU MN ESTELLA', revenue: 5, fabiTen: 'Tutu Train - Estella ( Dịch vụ K&H )' },
    { dept: 'funzone', code: 'FZVT', name: 'FUNZONE CITY VŨNG TÀU', revenue: 777, fabiTen: '' },
    { dept: 'event', code: 'EVCU', name: 'EVENT MN QUÁN CŨ', revenue: 123456, fabiTen: 'QUÁN ĐÃ ĐÓNG ( Dịch vụ K&H )' },
  ];
  const DS = [
    { cua_hang: 'TuTu Train - Aeon Tân Phú ( Dịch Vụ và Giải Trí K&H )', thanh_tien: 29905000 },
    { cua_hang: 'Tutu Train - Estella ( Dịch vụ K&H )', thanh_tien: 20770000 },
    { cua_hang: 'FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )', thanh_tien: 27667000 },
  ];

  const r = E.dongBoFabi(st, DS);
  assert.strictEqual(st.sites[0].revenue, 29905000, 'điểm đã liên kết phải tự nhận số mới');
  assert.strictEqual(st.sites[1].revenue, 20770000);

  // 🔴 KHÔNG ĐOÁN: FZVT không có fabiTen nên dù tên trùng khít vẫn phải đứng ngoài.
  assert.strictEqual(st.sites[2].revenue, 777,
    'chưa liên kết thì đường tự động KHÔNG được đụng vào, dù tên khớp');

  // 🔴 CỬA HÀNG BIẾN MẤT BÊN FABi: KHÔNG đưa về 0. Số 0 trông y hệt một tháng ế, mà thật ra là
  //    mất liên kết — người ta sẽ chốt sổ với một điểm doanh thu 0 mà không biết vì sao.
  assert.strictEqual(st.sites[3].revenue, 123456, 'mất liên kết thì GIỮ số cũ, không về 0');
  assert.strictEqual(r.mat.length, 1, 'và phải báo ra');
  assert.strictEqual(r.mat[0].code, 'EVCU');

  assert.strictEqual(r.daLinh, 3, 'đếm đúng số điểm đang liên kết');
  assert.strictEqual(r.soDoi, 2, 'đếm đúng số điểm vừa đổi số');

  // -- chạy lại mà số không đổi thì KHÔNG báo đổi (khỏi làm bẩn nhật ký/đồng bộ) --
  const r2 = E.dongBoFabi(st, DS);
  assert.strictEqual(r2.soDoi, 0, 'chạy lại không đổi gì thì không báo đổi');
  assert.strictEqual(r2.mat.length, 1, 'nhưng vẫn báo cái mất liên kết');

  // -- công tắc mặc định TẮT: không tự ý đổi cách app đang lấy số --
  assert.strictEqual(E.normalizeState({}).options.fabiTuDong, false);

  console.log('OK — liên kết sống FABi: chỉ theo liên kết đã chốt, mất liên kết thì giữ số cũ.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// CƠ SỞ MỚI BÊN FABi → TỰ TÁCH ĐIỂM MỚI  (anh Thắng 18/09/2026: "chứ đừng kẹt nhé")
//
// Tên quán lấy đúng trang khmatrix.com/doanh-thu-hcm, gồm mấy quán mới chưa có điểm bên này:
// "Ngôi Nhà Ma - Aeon Bình Dương", "SNOW FUN AEON BÌNH DƯƠNG", "VR FUN - SC Vivo Q7".
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const st = E.normalizeState(window.SAMPLE_DATA);
  st.sites = [{ dept: 'tutu', code: 'TTAMTP', name: 'TUTU MN AEON MALL TÂN PHÚ', revenue: 0 }];

  // -- đoán bộ phận theo chữ hiệu --
  const dp = st.departments;
  assert.strictEqual(E.doanBoPhan('Tutu Train - Aeon Tân An ( Dịch vụ K&H )', dp), 'tutu');
  assert.strictEqual(E.doanBoPhan('VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )', dp), 'funzone');
  assert.strictEqual(E.doanBoPhan('FUNZONE CITY VŨNG TÀU ( Dịch Vụ và Giải Trí K&H )', dp), 'funzone');
  assert.strictEqual(E.doanBoPhan('ECO FARM LOTTE PHAN THIẾT ( Dịch Vụ K&H )', dp), 'farm',
    '"ECOFARM" phải thắng "FARM" — lấy dấu hiệu DÀI NHẤT');
  assert.strictEqual(E.doanBoPhan('SNOW FUN AEON BÌNH DƯƠNG ( Dịch Vụ K&H )', dp), 'event');
  assert.strictEqual(E.doanBoPhan('Ngôi Nhà Ma - Aeon Bình Dương ( Dịch Vụ K&H )', dp), 'event');
  assert.strictEqual(E.doanBoPhan('COFFE GO AN LẠC ( Dịch Vụ K&H )', dp), '',
    'không có chữ hiệu nào thì ĐỪNG đoán — để người chọn');

  // -- tạo điểm mới --
  const ghep = [
    { cua_hang: 'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )', thanh_tien: 32460000, siteIndex: null, taoMoi: 'funzone' },
    { cua_hang: 'Ngôi Nhà Ma - Aeon Bình Dương ( Dịch Vụ K&H )', thanh_tien: 216545000, siteIndex: null, taoMoi: 'event' },
    { cua_hang: 'COFFE GO AN LẠC ( Dịch Vụ K&H )', thanh_tien: 19705000, siteIndex: null, taoMoi: '' },
  ];
  const r = E.taoDiemTuFabi(st, ghep);
  assert.strictEqual(r.tao.length, 2, 'chỉ tạo những dòng người dùng đã chọn bộ phận');
  assert.strictEqual(st.sites.length, 3, 'thêm 2 điểm vào danh sách');

  const vr = st.sites.find((s) => s.name.indexOf('VR FUN') === 0);
  assert.strictEqual(vr.dept, 'funzone');
  assert.strictEqual(vr.name, 'VR FUN - SC Vivo Q7', 'tên điểm CẮT đuôi pháp nhân');
  assert.strictEqual(vr.revenue, 32460000, 'và nhận luôn doanh thu của kỳ này');
  assert.strictEqual(vr.fabiTen, 'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )',
    'giữ NGUYÊN tên gốc làm khoá liên kết — cắt đuôi ở đây là tháng sau không ghép lại được');

  // 🔴 KHÔNG BỊA MÃ ĐƠN VỊ. Mã đơn vị là mã trong sổ MISA; bịa ra thì bút toán hạch toán vào một
  //    đơn vị KHÔNG TỒN TẠI, mà nhìn tờ nhập thì thấy có mã nên chẳng ai nghi.
  assert.strictEqual(vr.code, '', 'để trống mã đơn vị');
  const canhBao = E.validate(st).filter((i) => i.msg.indexOf('chưa có Mã đơn vị') >= 0);
  assert(canhBao.length && canhBao[0].level === 'warn', 'nhưng phải CẢNH BÁO ra, không im lặng');

  // -- dòng chưa chọn bộ phận thì để nguyên, không tạo bừa --
  assert.strictEqual(ghep[2].siteIndex, null);

  // -- tạo xong thì liên kết sống hoạt động luôn --
  const kq = E.dongBoFabi(st, [
    { cua_hang: 'VR FUN - SC Vivo Q7 ( Dịch Vụ và Giải Trí K&H )', thanh_tien: 40000000 },
    { cua_hang: 'Ngôi Nhà Ma - Aeon Bình Dương ( Dịch Vụ K&H )', thanh_tien: 216545000 },
  ]);
  assert.strictEqual(st.sites.find((s) => s.name.indexOf('VR FUN') === 0).revenue, 40000000,
    'điểm vừa tạo phải tự cập nhật ở các kỳ sau');
  assert.strictEqual(kq.daLinh, 2);

  console.log('OK — cơ sở mới bên FABi: tự tách điểm, đoán bộ phận, không bịa mã đơn vị.');
}

// ═══════════════════════════════════════════════════════════════════════════════════════════════
// LƯƠNG TỪ TRANG NHÂN SỰ  (anh Thắng 18/09/2026: "giờ Lương lấy từ trang nhân sự theo cơ sở",
// chọn "Tổng mỗi cơ sở một dòng")
//
// Luật quan trọng nhất ở đây là luật KHÔNG ghi:
//   Bên Chấm công, Khu vui chơi trả co_luong=false vì chưa khai giá giờ. Ghi 0 vào báo cáo thì
//   nhìn y hệt "tháng này không có lương" — chi phí lương của cả một nhóm biến mất mà tổng vẫn
//   cộng đẹp, không ai nghi. Những dòng ấy phải bị BỎ QUA và đếm riêng để giao diện bày lý do.
// ═══════════════════════════════════════════════════════════════════════════════════════════════
{
  const st = E.normalizeState(window.SAMPLE_DATA);
  st.salarySites = [
    { id: 'ss1', dept: 'posh', stt: 1, name: 'POSH MN AEON MALL BÌNH DƯƠNG', reported: 10, actual: 10 },
    { id: 'ss2', dept: 'jp',   stt: 2, name: 'JP MN AEON MALL BÌNH TÂN',     reported: 20, actual: 20 },
    { id: 'ss3', dept: 'funzone', stt: 3, name: 'FUNZONE CITY VŨNG TÀU',     reported: 30, actual: 30 },
  ];

  const DS = [
    { cua_hang: 'POSH MN AEON MALL BÌNH DƯƠNG', thanh_tien: 111649262, co_luong: true,  bo_phan: 'posh' },
    { cua_hang: 'JP MN AEON MALL BÌNH TÂN',     thanh_tien: 41635755,  co_luong: true,  bo_phan: 'jp' },
    { cua_hang: 'FUNZONE CITY VŨNG TÀU',        thanh_tien: 0, co_luong: false, ghi_chu: 'Chưa khai giá giờ' },
    { cua_hang: 'TUTU MN AEON MALL TÂN PHÚ',    thanh_tien: 8000000,   co_luong: true },
  ];

  // -- ghép: 3 dòng khớp tên, dòng thứ 4 chưa có chỗ --
  const ghep = E.ghepLuong(st, DS);
  assert.strictEqual(ghep.length, 4);
  assert.strictEqual(ghep[0].rowIndex, 0);
  assert.strictEqual(ghep[1].rowIndex, 1);
  assert.strictEqual(ghep[2].rowIndex, 2);
  assert.strictEqual(ghep[3].rowIndex, null, 'cơ sở chưa có dòng lương thì để người chọn, không ghép bừa');
  assert.strictEqual(ghep[2].co_luong, false, 'phải mang cờ chưa có giá sang cho giao diện bày ra');
  assert.strictEqual(ghep[2].ghi_chu, 'Chưa khai giá giờ');

  // -- nạp --
  ghep[3].taoMoi = 'tutu';
  const r = E.napLuong(st, ghep);
  assert.strictEqual(r.xong, 3, '2 dòng cũ + 1 dòng vừa tạo');
  assert.strictEqual(r.tao, 1);
  assert.strictEqual(r.chuaGia, 1, 'dòng chưa khai giá giờ phải được ĐẾM RIÊNG, không lẫn vào bỏ qua');
  assert.strictEqual(st.salarySites[0].reported, 111649262);
  assert.strictEqual(st.salarySites[0].actual, 111649262,
    'phải điền cả actual — bỏ trống thì Mục III cộng một tổng, phân bổ ăn một tổng khác');
  assert.strictEqual(st.salarySites[0].nsTen, 'POSH MN AEON MALL BÌNH DƯƠNG', 'nhớ liên kết cho kỳ sau');

  // 🔴 Dòng chưa khai giá giờ KHÔNG được ghi 0 — giữ nguyên số cũ.
  assert.strictEqual(st.salarySites[2].reported, 30, 'chưa có giá giờ thì GIỮ SỐ CŨ, không ghi 0');
  assert.strictEqual(st.salarySites[2].actual, 30);
  assert.strictEqual(st.salarySites[2].nsTen || '', '', 'và cũng không chốt liên kết cho một số chưa có');

  // -- dòng mới --
  assert.strictEqual(st.salarySites.length, 4);
  const tutu = st.salarySites[3];
  assert.strictEqual(tutu.dept, 'tutu');
  assert.strictEqual(tutu.name, 'TUTU MN AEON MALL TÂN PHÚ');
  assert.strictEqual(tutu.reported, 8000000);
  assert.strictEqual(tutu.unitCode, '', 'KHÔNG bịa mã đơn vị — mã đơn vị là mã trong sổ MISA');

  // -- liên kết sống: kỳ sau số tự về, không phải ghép lại --
  const kq = E.dongBoLuong(st, [
    { cua_hang: 'POSH MN AEON MALL BÌNH DƯƠNG', thanh_tien: 120000000, co_luong: true },
    { cua_hang: 'TUTU MN AEON MALL TÂN PHÚ',    thanh_tien: 0, co_luong: false, ghi_chu: 'Chưa khai giá giờ' },
  ]);
  assert.strictEqual(kq.daLinh, 3, '3 dòng đã chốt liên kết (ss1, ss2, dòng tutu mới)');
  assert.strictEqual(st.salarySites[0].reported, 120000000);
  assert.strictEqual(st.salarySites[0].actual, 120000000);
  assert.strictEqual(kq.soDoi, 1);
  assert.strictEqual(kq.mat.length, 1, 'JP không còn trong danh sách kỳ này → báo MẤT LIÊN KẾT');
  assert.strictEqual(st.salarySites[1].reported, 41635755, 'mất liên kết thì GIỮ SỐ CŨ, không ghi 0');
  assert.strictEqual(kq.chuaGia.length, 1);
  assert.strictEqual(st.salarySites[3].reported, 8000000,
    'tháng này bên ấy chưa khai giá → giữ số cũ, y như khi mất liên kết');

  // -- một cơ sở chỉ nối vào đúng một dòng lương --
  const st2 = E.normalizeState(window.SAMPLE_DATA);
  st2.salarySites = [
    { id: 'a', dept: 'posh', name: 'POSH MN AEON MALL BÌNH DƯƠNG', reported: 0, actual: 0 },
    { id: 'b', dept: 'posh', name: 'POSH MN AEON MALL BÌNH DƯƠNG', reported: 0, actual: 0 },
  ];
  const g2 = E.ghepLuong(st2, [{ cua_hang: 'POSH MN AEON MALL BÌNH DƯƠNG', thanh_tien: 5, co_luong: true }]);
  assert.strictEqual(g2[0].rowIndex, 0, 'chỉ một dòng nhận, không nhân đôi lương');

  console.log('OK — lương từ Nhân sự: mỗi cơ sở một dòng, chưa khai giá giờ thì KHÔNG ghi 0.');
}
