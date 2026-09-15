/*
 * exporter.js — Dựng workbook Excel từ state + report (SheetJS community: có định dạng số, độ rộng cột, gộp ô).
 *
 * Sheets:
 *   1. "File tổng báo cáo"        — đúng bố cục 3 mục như file gốc
 *   2. "Phân bổ theo bộ phận"     — bảng kiểm: từng khoản → chia nhóm → chia bộ phận
 *   3. "<Bộ phận> T8.2026"        — phân bổ xuống điểm theo tỷ trọng doanh thu (mỗi bộ phận 1 sheet)
 *   4. "Doanh thu"                — doanh thu & tỷ trọng từng bộ phận / điểm
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(require('./engine.js'));
  else root.BaoCaoExporter = factory(root.BaoCaoEngine);
})(typeof self !== 'undefined' ? self : this, function (E) {
  'use strict';

  const NUM_FMT = '#,##0;(#,##0);-';
  const PCT_FMT = '0.00%';

  /** Chuyển mảng 2 chiều thành sheet, tự gán định dạng số cho ô số. */
  function aoaToSheet(XLSX, aoa, opts) {
    const ws = XLSX.utils.aoa_to_sheet(aoa);
    const range = XLSX.utils.decode_range(ws['!ref'] || 'A1');
    for (let R = range.s.r; R <= range.e.r; R++) {
      for (let C = range.s.c; C <= range.e.c; C++) {
        const addr = XLSX.utils.encode_cell({ r: R, c: C });
        const cell = ws[addr];
        if (!cell || cell.t !== 'n') continue;
        cell.z = (opts && opts.pctCols && opts.pctCols.includes(C)) ? PCT_FMT : NUM_FMT;
      }
    }
    if (opts && opts.cols) ws['!cols'] = opts.cols.map((w) => ({ wch: w }));
    if (opts && opts.merges) ws['!merges'] = opts.merges.map((m) => XLSX.utils.decode_range(m));
    return ws;
  }

  function safeSheetName(name) {
    return String(name).replace(/[\\/?*[\]:]/g, ' ').trim().slice(0, 31);
  }

  // ---------------------------------------------------------------- Sheet 1: File tổng báo cáo
  function buildReportSheet(XLSX, state, report) {
    const P = report.periodLabel;
    const depts = report.departments;
    const aoa = [];
    const merges = [];

    aoa.push([`BÁO CÁO CHI PHÍ ${P}`]);
    merges.push(`A1:${XLSX.utils.encode_col(1 + report.columns.length + report.manualCols.length)}1`);

    // ---- Mục I
    const hdr = ['Bộ Phận'];
    report.columns.forEach((c) => hdr.push(c.title));
    report.manualCols.forEach((m) => hdr.push(m.name));
    hdr.push('Tổng chi phí phân bổ');
    aoa.push(hdr);
    depts.forEach((d) => {
      const row = [d.name];
      report.columns.forEach((c) => row.push(report.matrix[d.id][c.key] || 0));
      report.manualCols.forEach((m) => row.push(m.vals[d.id] || 0));
      row.push((report.rowTotals[d.id] || 0) + report.manualCols.reduce((a, m) => a + (m.vals[d.id] || 0), 0));
      aoa.push(row);
    });
    const tot = ['Tổng cộng'];
    report.columns.forEach((c) => tot.push(report.colTotals[c.key] || 0));
    report.manualCols.forEach((m) => tot.push(m.total || 0));
    tot.push(report.grandTotal + report.manualCols.reduce((a, m) => a + m.total, 0));
    aoa.push(tot);
    // dòng tài khoản / mã đối tượng tham chiếu
    const acc = ['Tài khoản'];
    report.columns.forEach((c) => acc.push(c.account || ''));
    report.manualCols.forEach((m) => acc.push(m.account || ''));
    aoa.push(acc);
    aoa.push([]);

    // ---- Mục II
    const h2 = ['Bộ Phận'];
    E.SALARY_DEPT_FIELDS.forEach((f) => h2.push(`${f.label}\n${P}`));
    h2.push('Tổng lương', 'Nội dung cột diễn giải chung trên misa', 'Nội dung cột diễn giải chi tiết trên misa');
    aoa.push(h2);
    report.salaryDept.forEach((r) => {
      const d = depts.find((x) => x.id === r.dept) || { name: r.dept };
      const row = [d.name];
      E.SALARY_DEPT_FIELDS.forEach((f) => row.push(r[f.key] || 0));
      row.push(r.total || 0, r.misaGeneral || '', r.misaDetail || '');
      aoa.push(row);
    });
    const t2 = ['Tổng cộng'];
    E.SALARY_DEPT_FIELDS.forEach((f) => t2.push(report.salaryDeptTotals[f.key] || 0));
    t2.push(report.salaryDeptTotals.total || 0);
    aoa.push(t2);
    aoa.push([]);

    // ---- Mục III
    aoa.push(['Lương cơ sở HCM', 'Tên cơ sở', 'Theo báo cáo TK', '', '', '', 'Số tiền']);
    report.siteGroups.forEach((g) => {
      aoa.push(['STT', g.title, `Lương NV ${P}`, '', 'Báo cáo', 'DNTT/ Báo cáo', 'Thực lĩnh', 'Mã đơn vị', 'Nội dung cột diễn giải chung trên misa', 'Nội dung cột diễn giải chi tiết trên misa']);
      g.rows.forEach((r, i) => {
        aoa.push([r.stt || i + 1, r.name, r.reported, 0, r.report, r.dntt, r.actual, r.unitCode || '', r.misaGeneral || '', r.misaDetail || '']);
      });
      aoa.push(['', `Cộng ${g.title}`, g.sub.reported, 0, g.sub.report, g.sub.dntt, g.sub.actual]);
    });
    aoa.push(['', 'TỔNG CỘNG LƯƠNG CƠ SỞ', report.salarySitesTotal.reported, 0, report.salarySitesTotal.report, report.salarySitesTotal.dntt, report.salarySitesTotal.actual]);

    const cols = [16, 34];
    for (let i = 0; i < report.columns.length + report.manualCols.length + 8; i++) cols.push(18);
    return aoaToSheet(XLSX, aoa, { cols, merges });
  }

  // ---------------------------------------------------------------- Sheet 2: Phân bổ theo bộ phận
  function buildAllocationSheet(XLSX, state, report) {
    const depts = report.departments;
    const groups = state.groups || [];
    const aoa = [];
    aoa.push([`PHÂN BỔ CHI PHÍ THEO BỘ PHẬN ${report.periodLabel}`]);
    aoa.push([]);
    aoa.push(['Doanh thu & tỷ trọng']);
    aoa.push(['Bộ phận', 'Nhóm', 'Phương pháp', 'Doanh thu', 'Tỷ lệ cố định', 'Tỷ trọng áp dụng', 'Tổng chi phí phân bổ']);
    depts.forEach((d) => {
      const g = report.groupInfo.find((x) => x.id === d.group) || {};
      aoa.push([d.name, g.name || d.group, methodLabel(g.method), report.revenue[d.id] || 0, d.ratio || 0, (g.weights || {})[d.id] || 0, report.rowTotals[d.id] || 0]);
    });
    aoa.push([]);
    const hdr = ['STT', 'Loại', 'Khoản chi phí', 'Nội dung chung MISA', 'Nội dung chi tiết MISA', 'Tài khoản', 'Mã đối tượng', 'Tổng tiền', 'Kiểu chia'];
    groups.forEach((g) => hdr.push(g.name));
    depts.forEach((d) => hdr.push(d.name));
    hdr.push('Kiểm tra (tổng phân bổ)', 'Loại trừ', 'Gộp cột', 'Ghi chú');
    aoa.push(hdr);
    const activeItems = E.activeCostItems(state);
    activeItems.forEach((it, i) => {
      const shares = E.itemShares(state, it);
      const alloc = report.itemAlloc[it.id] || {};
      const row = [i + 1, it.kind === 'personal' ? 'Cá nhân' : 'Công ty', it.name, it.misaGeneral || '', it.misaDetail || '', it.account || '', it.objectCode || '', E.num(it.total), splitLabel(state, it)];
      groups.forEach((g) => row.push(shares[g.id] || 0));
      let sum = 0;
      depts.forEach((d) => { row.push(alloc[d.id] || 0); sum += alloc[d.id] || 0; });
      row.push(sum, (it.excludeDepts || []).map((id) => (depts.find((d) => d.id === id) || { name: id }).name).join(', '), it.groupKey || '', it.note || '');
      aoa.push(row);
    });
    const tot = ['', '', 'Tổng cộng', '', '', '', '', activeItems.reduce((a, it) => a + E.num(it.total), 0), ''];
    groups.forEach((g) => tot.push(activeItems.reduce((a, it) => a + (E.itemShares(state, it)[g.id] || 0), 0)));
    depts.forEach((d) => tot.push(report.rowTotals[d.id] || 0));
    tot.push(report.grandTotal);
    aoa.push(tot);
    const pctCols = [5]; // tỷ trọng áp dụng
    const cols = [6, 10, 40, 34, 44, 14, 12, 16, 16];
    groups.forEach(() => cols.push(16));
    depts.forEach(() => cols.push(16));
    cols.push(18, 16, 14, 30);
    return aoaToSheet(XLSX, aoa, { cols, pctCols });
  }

  function methodLabel(m) {
    return m === 'ratio' ? 'Tỷ lệ cố định' : m === 'equal' ? 'Chia đều' : 'Theo doanh thu';
  }
  function splitLabel(state, it) {
    if (it.split === 'custom') return 'Tuỳ chỉnh';
    if (!it.split || it.split === 'equal') return 'Chia đều các nhóm';
    const g = (state.groups || []).find((x) => x.id === it.split);
    return `100% ${g ? g.name : it.split}`;
  }

  // ---------------------------------------------------------------- Sheet 3: phân bổ theo điểm
  function buildSiteSheet(XLSX, state, report, deptId) {
    const alloc = E.allocateSites(state, report, deptId);
    if (!alloc) return null;
    const aoa = [];
    aoa.push([`HẠCH TOÁN MISA ${alloc.dept.name.toUpperCase()} — ${report.periodLabel}`]);
    aoa.push(['Phân bổ theo tỷ trọng doanh thu từng điểm. Dòng "Số tiền phân bổ" = số của bộ phận trên File tổng báo cáo.']);
    const hdr = ['STT', 'Tên điểm', 'Mã đơn vị', `Doanh thu ${report.periodLabel}`, 'Tỷ trọng'];
    alloc.cols.forEach((c) => hdr.push(c.title));
    hdr.push('Tổng');
    aoa.push(hdr);
    const accRow = ['', 'Tài khoản', '', '', ''];
    alloc.cols.forEach((c) => accRow.push(c.account || ''));
    aoa.push(accRow);
    const totRow = ['', 'Số tiền phân bổ', '', alloc.sumRevenue, 1];
    alloc.cols.forEach((c) => totRow.push(c.total || 0));
    totRow.push(alloc.cols.reduce((a, c) => a + (c.total || 0), 0));
    aoa.push(totRow);
    alloc.rows.forEach((r) => {
      const row = [r.stt, r.name, r.code, r.revenue, r.weight];
      alloc.cols.forEach((c) => row.push(r.vals[c.key] || 0));
      row.push(r.total);
      aoa.push(row);
    });
    const sum = ['', 'Tổng cộng', '', alloc.sumRevenue, 1];
    alloc.cols.forEach((c) => sum.push(alloc.totals[c.key] || 0));
    sum.push(alloc.grandTotal);
    aoa.push(sum);
    const cols = [6, 40, 14, 18, 10];
    alloc.cols.forEach(() => cols.push(18));
    cols.push(18);
    return aoaToSheet(XLSX, aoa, { cols, pctCols: [4] });
  }

  // ---------------------------------------------------------------- Sheet 4: doanh thu
  function buildRevenueSheet(XLSX, state, report) {
    const aoa = [[`DOANH THU ${report.periodLabel}`], []];
    aoa.push(['Bộ phận', 'Mã đơn vị', 'Tên điểm', 'Doanh thu', 'Tỷ trọng trong bộ phận']);
    report.departments.forEach((d) => {
      const sites = (state.sites || []).filter((s) => s.dept === d.id);
      const rev = report.revenue[d.id] || 0;
      if (!sites.length) {
        aoa.push([d.name, '', '(nhập tay)', rev, 1]);
        return;
      }
      sites.forEach((s) => aoa.push([d.name, s.code, s.name, E.num(s.revenue), rev > 0 ? E.num(s.revenue) / rev : 0]));
      aoa.push(['', '', `Cộng ${d.name}`, rev, 1]);
    });
    aoa.push(['', '', 'TỔNG CỘNG', Object.values(report.revenue).reduce((a, b) => a + b, 0), '']);
    return aoaToSheet(XLSX, aoa, { cols: [14, 14, 44, 18, 14], pctCols: [4] });
  }

  /** Dựng toàn bộ workbook. options.siteSheets=false để bỏ các sheet phân bổ theo điểm. */
  function buildWorkbook(XLSX, state, report, options) {
    const opt = Object.assign({ siteSheets: true, allocationSheet: true, revenueSheet: true }, options || {});
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildReportSheet(XLSX, state, report), 'File tổng báo cáo');
    if (opt.allocationSheet) XLSX.utils.book_append_sheet(wb, buildAllocationSheet(XLSX, state, report), 'Phân bổ theo bộ phận');
    if (opt.siteSheets) {
      const short = E.periodShort(state.period);
      report.departments.forEach((d) => {
        if (!(state.sites || []).some((s) => s.dept === d.id)) return;
        const ws = buildSiteSheet(XLSX, state, report, d.id);
        if (ws) XLSX.utils.book_append_sheet(wb, ws, safeSheetName(`${d.name} ${short}`));
      });
    }
    if (opt.revenueSheet) XLSX.utils.book_append_sheet(wb, buildRevenueSheet(XLSX, state, report), 'Doanh thu');
    return wb;
  }

  function fileName(state) {
    const p = state.period || {};
    return `File_tong_bao_cao_${state.region || 'MN'}_T${String(p.month || 1).padStart(2, '0')}_${p.year || ''}.xlsx`;
  }

  return { buildWorkbook, buildReportSheet, buildAllocationSheet, buildSiteSheet, buildRevenueSheet, fileName, NUM_FMT };
});
