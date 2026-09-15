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
