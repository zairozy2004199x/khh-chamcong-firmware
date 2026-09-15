/*
 * engine.js — Lõi tính toán phân bổ chi phí, KHÔNG phụ thuộc giao diện.
 *
 * Chạy được cả trong trình duyệt (window.BaoCaoEngine) lẫn Node (module.exports)
 * để kiểm thử tự động đối chiếu với file Excel gốc.
 *
 * Mô hình dữ liệu (state):
 *   period       {month, year}
 *   groups       [{id, name, method}]           method: 'ratio' | 'revenue' | 'equal'
 *   departments  [{id, name, group, ratio, revenue, revenueOverride, unitCode}]
 *   sites        [{dept, code, name, revenue}]   điểm bán / cơ sở, dùng để tính doanh thu BP và phân bổ theo điểm
 *   costItems    [{id, kind, name, misaGeneral, misaDetail, account, total,
 *                  split, shares:{groupId: amount}, objectCode, excludeDepts:[deptId], groupKey, method, note}]
 *                  split: 'equal' (chia đều các nhóm) | '<groupId>' (100% một nhóm) | 'custom' (nhập tay shares)
 *                  groupKey: các khoản cùng groupKey gộp thành 1 cột trên báo cáo (VD: Scholarship HH / PH)
 *                  method: (tuỳ chọn) ghi đè phương pháp phân bổ của nhóm cho riêng khoản này
 *   manualCols   [{id, name, account, values:{deptId: amount}}]   cột nhập tay trên báo cáo (CP 154, CP mua máy 1543…)
 *   salaryDept   [{dept, luongVP, luongVanHanh, luongQLAC, luongAlex, luongKPI, bonus, misaGeneral, misaDetail}]
 *   salarySites  [{id, dept, groupTitle, stt, name, reported, report, dntt, actual, unitCode, misaGeneral, misaDetail}]
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.BaoCaoEngine = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  const SALARY_DEPT_FIELDS = [
    { key: 'luongVP', label: 'Lương văn phòng' },
    { key: 'luongVanHanh', label: 'Lương vận hành' },
    { key: 'luongQLAC', label: 'Lương QL AC' },
    { key: 'luongAlex', label: 'Lương ALEX' },
    { key: 'luongKPI', label: 'Lương KPI BP NV cơ sở' },
    { key: 'bonus', label: 'Bonus VP' },
  ];

  function num(v) {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return isFinite(v) ? v : 0;
    const s = String(v).replace(/\s/g, '').replace(/\./g, '').replace(',', '.');
    const n = parseFloat(s);
    return isFinite(n) ? n : 0;
  }

  function periodLabel(period) {
    const m = String(period && period.month ? period.month : 1).padStart(2, '0');
    const y = period && period.year ? period.year : new Date().getFullYear();
    return `T${m}/${y}`;
  }
  function periodShort(period) {
    const m = period && period.month ? period.month : 1;
    const y = period && period.year ? period.year : new Date().getFullYear();
    return `T${m}.${y}`;
  }

  /** Doanh thu của một bộ phận: tổng doanh thu các điểm, hoặc số nhập tay nếu revenueOverride. */
  function deptRevenue(state, dept) {
    if (dept.revenueOverride) return num(dept.revenue);
    const sites = (state.sites || []).filter((s) => s.dept === dept.id);
    if (sites.length === 0) return num(dept.revenue);
    return sites.reduce((a, s) => a + num(s.revenue), 0);
  }

  /** Số tiền của một khoản chi phí rơi vào từng nhóm (MTĐ / KVC…) theo kiểu chia. */
  function itemShares(state, item) {
    const groups = state.groups || [];
    const total = num(item.total);
    const out = {};
    if (item.split === 'custom') {
      groups.forEach((g) => (out[g.id] = num(item.shares && item.shares[g.id])));
    } else if (item.split === 'equal' || !item.split) {
      groups.forEach((g) => (out[g.id] = groups.length ? total / groups.length : 0));
    } else {
      groups.forEach((g) => (out[g.id] = g.id === item.split ? total : 0));
    }
    return out;
  }

  /** Trọng số phân bổ của từng bộ phận trong nhóm (đã loại các bộ phận bị exclude, đã chuẩn hoá về tổng = 1). */
  function groupWeights(state, groupId, excludeDepts, methodOverride) {
    const group = (state.groups || []).find((g) => g.id === groupId);
    const method = methodOverride || (group && group.method) || 'revenue';
    const depts = (state.departments || []).filter(
      (d) => d.group === groupId && !(excludeDepts || []).includes(d.id)
    );
    const raw = {};
    depts.forEach((d) => {
      if (method === 'ratio') raw[d.id] = num(d.ratio);
      else if (method === 'equal') raw[d.id] = 1;
      else raw[d.id] = deptRevenue(state, d);
    });
    const sum = Object.values(raw).reduce((a, b) => a + b, 0);
    const w = {};
    depts.forEach((d) => (w[d.id] = sum > 0 ? raw[d.id] / sum : 0));
    return { weights: w, sum, method };
  }

  /** Phân bổ một khoản chi phí về từng bộ phận. Trả về {deptId: amount}. */
  function allocateItem(state, item) {
    const shares = itemShares(state, item);
    const out = {};
    (state.departments || []).forEach((d) => (out[d.id] = 0));
    (state.groups || []).forEach((g) => {
      const share = shares[g.id] || 0;
      if (!share) return;
      const { weights } = groupWeights(state, g.id, item.excludeDepts, item.method);
      Object.keys(weights).forEach((deptId) => {
        out[deptId] += share * weights[deptId];
      });
    });
    return out;
  }

  /** Gộp các khoản chi phí thành cột báo cáo (cùng groupKey → 1 cột). */
  function reportColumns(state) {
    const cols = [];
    (state.costItems || []).forEach((item) => {
      const key = item.groupKey ? `g:${item.groupKey}` : `i:${item.id}`;
      let col = cols.find((c) => c.key === key);
      if (!col) {
        col = { key, items: [], title: '', account: '' };
        cols.push(col);
      }
      col.items.push(item);
    });
    cols.forEach((c) => {
      c.title = c.items.map((i) => (i.misaDetail || i.name || '').trim()).filter(Boolean).join(' / ');
      c.account = c.items.map((i) => (i.account || '').trim()).filter(Boolean).join(' / ');
      c.total = c.items.reduce((a, i) => a + num(i.total), 0);
    });
    return cols;
  }

  /** Tính toàn bộ "File tổng báo cáo". */
  function computeReport(state) {
    const depts = state.departments || [];
    const columns = reportColumns(state);

    // ---- Mục I: chi phí phân bổ theo bộ phận
    const matrix = {}; // matrix[deptId][colKey]
    depts.forEach((d) => (matrix[d.id] = {}));
    const itemAlloc = {}; // itemAlloc[itemId][deptId]
    columns.forEach((col) => {
      depts.forEach((d) => (matrix[d.id][col.key] = 0));
      col.items.forEach((item) => {
        const a = allocateItem(state, item);
        itemAlloc[item.id] = a;
        depts.forEach((d) => (matrix[d.id][col.key] += a[d.id] || 0));
      });
    });
    const colTotals = {};
    columns.forEach((col) => {
      colTotals[col.key] = depts.reduce((a, d) => a + matrix[d.id][col.key], 0);
    });
    const manualCols = (state.manualCols || []).map((mc) => {
      const vals = {};
      depts.forEach((d) => (vals[d.id] = num(mc.values && mc.values[d.id])));
      return { ...mc, vals, total: Object.values(vals).reduce((a, b) => a + b, 0) };
    });
    const rowTotals = {};
    depts.forEach((d) => {
      rowTotals[d.id] = columns.reduce((a, c) => a + matrix[d.id][c.key], 0);
    });
    const grandTotal = Object.values(rowTotals).reduce((a, b) => a + b, 0);

    // ---- Mục II: lương theo bộ phận
    const salaryDept = depts.map((d) => {
      const row = (state.salaryDept || []).find((r) => r.dept === d.id) || {};
      const out = { dept: d.id, misaGeneral: row.misaGeneral || '', misaDetail: row.misaDetail || '' };
      let total = 0;
      SALARY_DEPT_FIELDS.forEach((f) => {
        out[f.key] = num(row[f.key]);
        total += out[f.key];
      });
      out.total = total;
      return out;
    });
    const salaryDeptTotals = {};
    SALARY_DEPT_FIELDS.forEach((f) => {
      salaryDeptTotals[f.key] = salaryDept.reduce((a, r) => a + r[f.key], 0);
    });
    salaryDeptTotals.total = salaryDept.reduce((a, r) => a + r.total, 0);

    // ---- Mục III: lương nhân viên cơ sở theo nhóm
    const siteGroups = [];
    (state.salarySites || []).forEach((row) => {
      const title = row.groupTitle || (depts.find((d) => d.id === row.dept) || {}).name || 'Khác';
      let g = siteGroups.find((x) => x.title === title);
      if (!g) {
        g = { title, rows: [], sub: { reported: 0, report: 0, dntt: 0, actual: 0 } };
        siteGroups.push(g);
      }
      const r = {
        ...row,
        reported: num(row.reported),
        report: num(row.report),
        dntt: num(row.dntt),
        actual: num(row.actual),
      };
      g.rows.push(r);
      g.sub.reported += r.reported;
      g.sub.report += r.report;
      g.sub.dntt += r.dntt;
      g.sub.actual += r.actual;
    });
    const salarySitesTotal = { reported: 0, report: 0, dntt: 0, actual: 0 };
    siteGroups.forEach((g) => {
      salarySitesTotal.reported += g.sub.reported;
      salarySitesTotal.report += g.sub.report;
      salarySitesTotal.dntt += g.sub.dntt;
      salarySitesTotal.actual += g.sub.actual;
    });
    /** Lương NV cơ sở theo bộ phận (cột "Theo báo cáo") — dùng để phân bổ xuống điểm. */
    const salarySitesByDept = {};
    depts.forEach((d) => (salarySitesByDept[d.id] = { reported: 0, actual: 0 }));
    (state.salarySites || []).forEach((row) => {
      if (!salarySitesByDept[row.dept]) salarySitesByDept[row.dept] = { reported: 0, actual: 0 };
      salarySitesByDept[row.dept].reported += num(row.reported);
      salarySitesByDept[row.dept].actual += num(row.actual);
    });

    // ---- Doanh thu & tỷ trọng
    const revenue = {};
    depts.forEach((d) => (revenue[d.id] = deptRevenue(state, d)));
    const groupInfo = (state.groups || []).map((g) => {
      const gd = depts.filter((d) => d.group === g.id);
      const rev = gd.reduce((a, d) => a + revenue[d.id], 0);
      const cost = gd.reduce((a, d) => a + rowTotals[d.id], 0);
      const { weights, method } = groupWeights(state, g.id, []);
      return { ...g, depts: gd.map((d) => d.id), revenue: rev, cost, weights, method };
    });

    return {
      period: state.period,
      periodLabel: periodLabel(state.period),
      departments: depts,
      columns,
      matrix,
      itemAlloc,
      colTotals,
      rowTotals,
      grandTotal,
      manualCols,
      salaryDept,
      salaryDeptTotals,
      siteGroups,
      salarySitesTotal,
      salarySitesByDept,
      revenue,
      groupInfo,
    };
  }

  /**
   * Phân bổ chi phí của một bộ phận xuống từng điểm theo tỷ trọng doanh thu
   * (tương đương các sheet "Posh T8.2026", "FZ T8.2026"…).
   */
  function allocateSites(state, report, deptId) {
    const dept = (state.departments || []).find((d) => d.id === deptId);
    if (!dept) return null;
    const sites = (state.sites || []).filter((s) => s.dept === deptId);
    const sumRev = sites.reduce((a, s) => a + num(s.revenue), 0);
    const sal = report.salaryDept.find((r) => r.dept === deptId) || {};
    const cols = [
      { key: 'luongNV', title: `Lương NV ${report.periodLabel}`, total: report.salarySitesByDept[deptId].reported },
      { key: 'luongVanHanh', title: `Lương vận hành ${report.periodLabel}`, total: num(sal.luongVanHanh) },
    ];
    report.columns.forEach((c) => cols.push({ key: c.key, title: c.title, total: report.matrix[deptId][c.key], account: c.account }));
    report.manualCols.forEach((mc) => cols.push({ key: `m:${mc.id}`, title: mc.name, total: mc.vals[deptId], account: mc.account }));
    const rows = sites.map((s, i) => {
      const w = sumRev > 0 ? num(s.revenue) / sumRev : 0;
      const vals = {};
      cols.forEach((c) => (vals[c.key] = c.total * w));
      const total = cols.reduce((a, c) => a + vals[c.key], 0);
      return { stt: i + 1, code: s.code, name: s.name, revenue: num(s.revenue), weight: w, vals, total };
    });
    const totals = {};
    cols.forEach((c) => (totals[c.key] = rows.reduce((a, r) => a + r.vals[c.key], 0)));
    return { dept, cols, rows, totals, sumRevenue: sumRev, grandTotal: rows.reduce((a, r) => a + r.total, 0) };
  }

  /** Kiểm tra dữ liệu, trả về danh sách {level:'error'|'warn'|'info', msg}. */
  function validate(state) {
    const issues = [];
    const depts = state.departments || [];
    const groups = state.groups || [];
    if (!depts.length) issues.push({ level: 'error', msg: 'Chưa có bộ phận nào.' });
    if (!groups.length) issues.push({ level: 'error', msg: 'Chưa có nhóm phân bổ nào (MTĐ / KVC).' });
    const seenDept = new Set();
    depts.forEach((d) => {
      if (!d.id || seenDept.has(d.id)) issues.push({ level: 'error', msg: `Bộ phận "${d.name}" trùng mã hoặc thiếu mã.` });
      seenDept.add(d.id);
      if (!groups.find((g) => g.id === d.group)) issues.push({ level: 'error', msg: `Bộ phận "${d.name}" chưa gán nhóm hợp lệ.` });
    });
    groups.forEach((g) => {
      const gd = depts.filter((d) => d.group === g.id);
      if (!gd.length) {
        issues.push({ level: 'warn', msg: `Nhóm "${g.name}" không có bộ phận nào.` });
        return;
      }
      if (g.method === 'ratio') {
        const s = gd.reduce((a, d) => a + num(d.ratio), 0);
        if (Math.abs(s - 1) > 1e-6)
          issues.push({ level: 'warn', msg: `Tổng tỷ lệ nhóm "${g.name}" = ${(s * 100).toFixed(2)}% (khác 100%). Sẽ tự chuẩn hoá về 100%.` });
      } else if (g.method === 'revenue') {
        const rev = gd.reduce((a, d) => a + deptRevenue(state, d), 0);
        if (rev <= 0) issues.push({ level: 'error', msg: `Nhóm "${g.name}" phân bổ theo doanh thu nhưng tổng doanh thu = 0.` });
        gd.forEach((d) => {
          if (deptRevenue(state, d) <= 0) issues.push({ level: 'info', msg: `Bộ phận "${d.name}" doanh thu = 0 → không nhận chi phí phân bổ theo doanh thu.` });
        });
      }
    });
    (state.costItems || []).forEach((it, idx) => {
      const label = it.name || `Khoản #${idx + 1}`;
      if (num(it.total) === 0) issues.push({ level: 'warn', msg: `Khoản "${label}" có tổng tiền = 0.` });
      if (it.split === 'custom') {
        const s = Object.values(itemShares(state, it)).reduce((a, b) => a + b, 0);
        if (Math.abs(s - num(it.total)) > 0.5)
          issues.push({ level: 'error', msg: `Khoản "${label}": tổng chia nhóm (${fmt(s)}) ≠ tổng tiền (${fmt(num(it.total))}).` });
      }
      if (!it.misaDetail) issues.push({ level: 'info', msg: `Khoản "${label}" chưa có nội dung diễn giải chi tiết MISA (tiêu đề cột báo cáo).` });
      (it.excludeDepts || []).forEach((id) => {
        if (!depts.find((d) => d.id === id)) issues.push({ level: 'warn', msg: `Khoản "${label}" loại trừ bộ phận không tồn tại: ${id}.` });
      });
    });
    const codes = new Map();
    (state.sites || []).forEach((s) => {
      if (!s.code) return;
      if (codes.has(s.code)) issues.push({ level: 'warn', msg: `Mã đơn vị "${s.code}" bị trùng (${codes.get(s.code)} / ${s.name}).` });
      codes.set(s.code, s.name);
      if (!depts.find((d) => d.id === s.dept)) issues.push({ level: 'error', msg: `Điểm "${s.name}" (${s.code}) gán bộ phận không tồn tại.` });
    });
    (state.salarySites || []).forEach((r) => {
      if (!depts.find((d) => d.id === r.dept)) issues.push({ level: 'error', msg: `Dòng lương "${r.name}" gán bộ phận không tồn tại.` });
      if (Math.abs(num(r.reported) - num(r.actual)) > 0.5)
        issues.push({ level: 'info', msg: `Lương "${r.name}": Theo báo cáo ${fmt(num(r.reported))} ≠ Thực lĩnh ${fmt(num(r.actual))} (lệch ${fmt(num(r.actual) - num(r.reported))}).` });
    });
    return issues;
  }

  function fmt(n, digits) {
    if (n === null || n === undefined || n === '' || isNaN(n)) return '';
    const d = digits === undefined ? 0 : digits;
    const r = Math.round(Math.abs(n) * Math.pow(10, d)) / Math.pow(10, d);
    let s = r.toFixed(d);
    let [int, dec] = s.split('.');
    int = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    s = dec ? `${int},${dec}` : int;
    if (n < 0 && r !== 0) s = `(${s})`;
    return s;
  }

  function newId(prefix) {
    return `${prefix}${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
  }

  /** Bộ dữ liệu trống theo đúng khuôn K&H (2 nhóm, 7 bộ phận) để nhập tay từ đầu. */
  function emptyState(month, year) {
    const now = new Date();
    return {
      period: { month: month || now.getMonth() + 1, year: year || now.getFullYear() },
      company: 'K&H CO. LTD',
      region: 'MN',
      groups: [
        { id: 'MTD', name: 'Máy tự động (MTĐ)', method: 'ratio' },
        { id: 'KVC', name: 'Khu vui chơi (KVC)', method: 'revenue' },
      ],
      departments: [
        { id: 'posh', name: 'Posh', group: 'MTD', ratio: 0.6, revenue: 0, revenueOverride: false, unitCode: 'Posh MN' },
        { id: 'jp', name: 'JP', group: 'MTD', ratio: 0.4, revenue: 0, revenueOverride: false, unitCode: 'JP MN' },
        { id: 'funzone', name: 'Funzone', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '' },
        { id: 'event', name: 'Event', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '' },
        { id: 'farm', name: 'Farm', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '' },
        { id: 'tutu', name: 'Tutu', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '' },
        { id: 'pinball', name: 'Pinball', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '' },
      ],
      sites: [],
      costItems: [],
      manualCols: [
        { id: 'mc154', name: 'Phân bổ chi phí 154', account: '', values: {} },
        { id: 'mc1543', name: 'Phân bổ chi phí mua máy 1543', account: '', values: {} },
      ],
      salaryDept: [],
      salarySites: [],
    };
  }

  /** Chuẩn hoá state cũ / thiếu trường để engine không lỗi. */
  function normalizeState(s) {
    const st = Object.assign(emptyState(), s || {});
    st.period = Object.assign({ month: 1, year: 2026 }, st.period || {});
    st.groups = (st.groups || []).map((g) => ({ method: 'revenue', ...g }));
    st.departments = (st.departments || []).map((d) => ({ ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', ...d }));
    st.sites = (st.sites || []).map((x) => ({ code: '', name: '', revenue: 0, ...x }));
    st.costItems = (st.costItems || []).map((it) => {
      const o = { kind: 'company', name: '', misaGeneral: '', misaDetail: '', account: '', total: 0, split: 'equal', shares: {}, objectCode: '', excludeDepts: [], groupKey: '', method: '', note: '', ...it };
      if (!o.id) o.id = newId('ci');
      // tương thích dữ liệu cũ: mtd/kvc → shares + split
      if (it && (it.mtd !== undefined || it.kvc !== undefined) && !it.shares) {
        o.shares = { MTD: num(it.mtd), KVC: num(it.kvc) };
        if (it.split === 'half') o.split = 'equal';
        else if (it.split === 'mtd') o.split = 'MTD';
        else if (it.split === 'kvc') o.split = 'KVC';
        else o.split = 'custom';
      }
      delete o.mtd;
      delete o.kvc;
      return o;
    });
    st.manualCols = (st.manualCols || []).map((m) => ({ name: '', account: '', values: {}, ...m, id: m.id || newId('mc') }));
    st.salaryDept = (st.salaryDept || []).map((r) => ({ misaGeneral: '', misaDetail: '', ...r }));
    st.salarySites = (st.salarySites || []).map((r) => ({ reported: 0, report: 0, dntt: 0, actual: 0, unitCode: '', misaGeneral: '', misaDetail: '', ...r, id: r.id || newId('ss') }));
    return st;
  }

  return {
    SALARY_DEPT_FIELDS,
    num,
    fmt,
    newId,
    periodLabel,
    periodShort,
    deptRevenue,
    itemShares,
    groupWeights,
    allocateItem,
    reportColumns,
    computeReport,
    allocateSites,
    validate,
    emptyState,
    normalizeState,
  };
});
