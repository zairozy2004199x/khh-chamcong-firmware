/*
 * engine.js — Lõi tính toán phân bổ chi phí, KHÔNG phụ thuộc giao diện.
 *
 * Chạy được cả trong trình duyệt (window.BaoCaoEngine) lẫn Node (module.exports)
 * để kiểm thử tự động đối chiếu với file Excel gốc.
 *
 * Mô hình dữ liệu (state):
 *   period       {month, year}
 *   groups       [{id, name, method}]           method: 'ratio' | 'revenue' | 'equal'
 *   departments  [{id, name, group, ratio, revenue, revenueOverride, unitCode, misaPrefix}]
 *   sites        [{dept, code, name, revenue, khongChiPhi}]   điểm bán / cơ sở, dùng để tính doanh thu BP và phân bổ theo điểm
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

  /** Trạng thái khoản chi phí khi làm việc nhiều người (không có status = coi như đã duyệt). */
  const COST_STATUSES = [
    { value: 'cho_duyet', label: 'Chờ duyệt' },
    { value: 'da_duyet', label: 'Đã duyệt' },
    { value: 'tu_choi', label: 'Từ chối' },
  ];
  /** Khoản chi phí được đưa vào tính toán: bỏ khoản từ chối; khoản chờ duyệt chỉ tính khi options.includePending. */
  function activeCostItems(state) {
    const inc = !!(state.options && state.options.includePending);
    return (state.costItems || []).filter((it) => {
      const st = it.status || 'da_duyet';
      if (st === 'tu_choi') return false;
      if (st === 'cho_duyet') return inc;
      return true;
    });
  }

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
    activeCostItems(state).forEach((item) => {
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
      /* Hai LỜI khác nhau, đừng gộp: `general` là "Diễn giải" của cả chứng từ, `detail` là
         "Diễn giải (Hạch toán)" của từng dòng — trên tờ nhập MISA chúng nằm ở hai cột riêng và
         nói hai chuyện riêng. Xem misaRows(). */
      c.general = c.items.map((i) => (i.misaGeneral || '').trim()).filter(Boolean).join(' / ');
      c.detail = c.items.map((i) => (i.misaDetail || i.name || '').trim()).filter(Boolean).join(' / ');
      /* MÃ ĐỐI TƯỢNG CÓ — mã nhà cung cấp, MỘT mã cho cả chứng từ. Lấy cái đầu tiên có giá trị
         chứ KHÔNG nối bằng " / " như `account`: đây là một ô mã của MISA, nối hai mã vào là MISA
         không tra ra đối tượng nào và từ chối chứng từ. (Cột gộp nhiều khoản khác nhà cung cấp là
         chuyện hiếm — file thật T8/2026 có đúng 1 trong 132 chứng từ.) */
      c.objectCode = (c.items.map((i) => (i.objectCode || '').trim()).filter(Boolean)[0]) || '';
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
      /* HAI cặp lời, không phải một: cột "Lương NV cơ sở" và cột "Lương vận hành BP" lên MISA
         thành HAI chứng từ riêng với hai câu diễn giải riêng. Dùng chung một cặp là mọi dòng
         lương vận hành mang nhãn của lương nhân viên — sai mà vẫn cộng đúng tổng, nên soát sổ
         không bắt được. */
      const out = { dept: d.id,
        misaGeneral: row.misaGeneral || '', misaDetail: row.misaDetail || '',
        misaGeneral2: row.misaGeneral2 || '', misaDetail2: row.misaDetail2 || '',
        misaAccount: row.misaAccount || '', misaAccount2: row.misaAccount2 || '',
        misaObjectCode: row.misaObjectCode || '', misaObjectCode2: row.misaObjectCode2 || '' };
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
    /* ═════════════════════════════════════════════════════════════════════════════════════════
     * HAI TỔNG DOANH THU KHÁC NHAU, ĐỪNG DÙNG LẪN.
     *
     * Anh Thắng 16/09/2026: *"Có 1 số cơ sở nghỉ hay đóng cửa, vẫn ghi nhận doanh thu chứ không
     * muốn thêm chi phí vào nó"* (ví dụ PBSCVV — PINBALL MN SC VIVO).
     *
     *   · `sumRev`   — CẢ cơ sở nghỉ. Đây là doanh thu thật của bộ phận, hiện ở cột Doanh thu và
     *                  dòng Tổng cộng. Bỏ nó ra là báo cáo doanh thu sai.
     *   · `sumRevCP` — BỎ cơ sở nghỉ. Đây là MẪU SỐ chia chi phí. Bỏ nó ra khỏi mẫu số thì phần
     *                  chi phí đáng lẽ về nó được các cơ sở còn lại GÁNH LẠI, nên TỔNG CHI PHÍ
     *                  của bộ phận KHÔNG ĐỔI — chỉ đổi chỗ ngồi.
     *
     * Đo trên file thật T8/2026 của anh Thắng: Pinball có 3 điểm, SC VIVO nghỉ (doanh thu 120.000
     * vẫn ghi). AMTP nhận 5.783.649 / 10.061.700 = 57,48%, đúng bằng 47.400.000 / (47.400.000 +
     * 35.060.838) — tức mẫu số KHÔNG có 120.000. Và tổng hai điểm còn lại = 10.061.700, đúng
     * bằng tổng chi phí của bộ phận: không đồng nào rơi mất.
     * ═════════════════════════════════════════════════════════════════════════════════════════ */
    const sumRev = sites.reduce((a, s) => a + num(s.revenue), 0);
    const sumRevCP = sites.reduce((a, s) => a + (s.khongChiPhi ? 0 : num(s.revenue)), 0);
    const sal = report.salaryDept.find((r) => r.dept === deptId) || {};
    const cols = [
      /* Hai cột lương KHÔNG có Mã đối tượng Có, và đó là đúng: TK Có 3341 là "phải trả người lao
         động" — không có nhà cung cấp nào để trỏ tới. File thật T8/2026 cũng để trống đúng hai
         chứng từ này (và cả chứng từ 1543). Vẫn cho khai đè, phòng khi kế toán cần. */
      { key: 'luongNV', title: `Lương NV ${report.periodLabel}`, total: report.salarySitesByDept[deptId].reported,
        general: sal.misaGeneral || '', detail: sal.misaDetail || '', account: sal.misaAccount || '',
        objectCode: sal.misaObjectCode || '' },
      { key: 'luongVanHanh', title: `Lương vận hành ${report.periodLabel}`, total: num(sal.luongVanHanh),
        general: sal.misaGeneral2 || '', detail: sal.misaDetail2 || '', account: sal.misaAccount2 || '',
        objectCode: sal.misaObjectCode2 || '' },
    ];
    report.columns.forEach((c) => cols.push({ key: c.key, title: c.title, total: report.matrix[deptId][c.key],
      account: c.account, general: c.general, detail: c.detail, objectCode: c.objectCode }));
    report.manualCols.forEach((mc) => cols.push({ key: `m:${mc.id}`, title: mc.name, total: mc.vals[deptId],
      account: mc.account, general: mc.misaGeneral || '', detail: mc.misaDetail || '', objectCode: mc.objectCode || '' }));
    const rows = sites.map((s, i) => {
      /* Cơ sở nghỉ: tỷ trọng 0 nên mọi cột chi phí ra 0 — giống hệt dòng SC VIVO trong file thật.
         Vẫn GIỮ DÒNG (không lọc bỏ) vì doanh thu của nó là thật và phải đọc được ở sheet này. */
      const w = s.khongChiPhi ? 0 : (sumRevCP > 0 ? num(s.revenue) / sumRevCP : 0);
      const vals = {};
      cols.forEach((c) => (vals[c.key] = c.total * w));
      const total = cols.reduce((a, c) => a + vals[c.key], 0);
      return { stt: i + 1, code: s.code, name: s.name, revenue: num(s.revenue), weight: w,
        khongChiPhi: !!s.khongChiPhi, vals, total };
    });
    const totals = {};
    cols.forEach((c) => (totals[c.key] = rows.reduce((a, r) => a + r.vals[c.key], 0)));
    return { dept, cols, rows, totals, sumRevenue: sumRev, sumRevenueCP: sumRevCP,
      grandTotal: rows.reduce((a, r) => a + r.total, 0) };
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * TỜ NHẬP MISA — "<Bộ phận> chi tiết"
   *
   * Anh Thắng 16/09/2026: *"tab chi tiết phân bổ ra để xuất MISA chưa có"*. Sheet "<Bộ phận>
   * T8.2026" đã phân bổ xong tiền về từng điểm, nhưng MISA không đọc được bảng hai chiều ấy — nó
   * cần MỘT DÒNG cho mỗi (khoản chi phí × điểm). Đây là chỗ bẻ bảng ra thành dòng.
   *
   * Không tính lại đồng nào: cùng `allocateSites()` đã dựng sheet kia. Tính lại ở đây là một ngày
   * nào đó hai sheet trong CÙNG một file lệch nhau, và người đọc không có cách nào biết bên nào
   * đúng.
   *
   * ⚠️ GIỮ NGUYÊN DÒNG TIỀN = 0. File thật của anh Thắng có (BZONE THẢO ĐIỀN, CENTRAL PREMIUM
   *    QUẬN 8 đều 0đ) và MISA nhận. Lọc bỏ thì số dòng mỗi chứng từ đổi theo từng tháng, người
   *    đối chiếu mất mốc "66 điểm = 66 dòng" để đếm.
   *
   * SỐ CHỨNG TỪ `NVK` + prefix + ngày + tháng + số thứ tự, ví dụ `NVKPOSH310801`:
   *   · `31` = NGÀY CUỐI THÁNG của kỳ, không phải hôm nay. Chứng từ thuộc về kỳ, không thuộc về
   *     lúc bấm nút — xuất lại tháng sau mà số chứng từ đổi là MISA coi như chứng từ khác.
   *   · số thứ tự đánh theo THỨ TỰ CỘT, mỗi cột một chứng từ.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */

  /**
   * Tách cặp tài khoản. Nguồn viết KHÔNG nhất quán — file thật có cả `N64131/C3341` lẫn
   * `N64213/331` (thiếu chữ C) — nên đừng đòi đúng một dạng: người gõ sẽ gõ như họ vẫn gõ, và
   * một ô lệch dạng mà bị bỏ qua thì dòng đó lên MISA thiếu tài khoản.
   * @return {{no:string, co:string}}
   */
  function parseAccount(txt) {
    const t = String(txt == null ? '' : txt).trim();
    if (!t) return { no: '', co: '' };
    /* Lấy ĐÚNG hai cụm số đầu tiên theo thứ tự Nợ rồi Có. Cột gộp nhiều khoản nối nhau bằng
       " / " (xem reportColumns) nên có thể có nhiều hơn hai — cặp đầu là cặp của cột. */
    const so = t.match(/\d+/g) || [];
    return { no: so[0] || '', co: so[1] || '' };
  }

  /** Ngày cuối tháng của kỳ, dạng dd/mm/yyyy — xem khối trên về việc vì sao không dùng hôm nay. */
  function periodLastDay(period) {
    const p = period || {};
    const y = Number(p.year) || new Date().getFullYear();
    const m = Number(p.month) || 1;
    const d = new Date(Date.UTC(y, m, 0)).getUTCDate();   // ngày 0 của tháng sau = ngày cuối tháng này
    return { d, m, y, text: `${pad2(d)}/${pad2(m)}/${y}` };
  }

  function pad2(n) { return String(n).padStart(2, '0'); }

  /**
   * Dựng toàn bộ dòng nhập MISA của MỘT bộ phận.
   * @return {null|{dept, period, rows:[{ngay, soCT, dienGiai, dienGiaiHT, tkNo, tkCo, soTien, maDonVi}]}}
   */
  function misaRows(state, report, deptId) {
    const alloc = allocateSites(state, report, deptId);
    if (!alloc) return null;
    const ky = periodLastDay(state.period);
    const prefix = (alloc.dept.misaPrefix || '').trim() || String(deptId).toUpperCase();
    const rows = [];
    alloc.cols.forEach((c, i) => {
      const tk = parseAccount(c.account);
      const soCT = `NVK${prefix}${pad2(ky.d)}${pad2(ky.m)}${pad2(i + 1)}`;
      /* Thiếu lời thì lùi về tiêu đề cột + kỳ, KHÔNG để trống: MISA bắt buộc "Diễn giải", một ô
         trống là cả chứng từ bị từ chối lúc nhập — mà lúc ấy người ta đã ở trong MISA rồi. */
      const chung = (c.general || '').trim() || `${c.title} ${alloc.dept.unitCode || alloc.dept.name} ${report.periodLabel}`.trim();
      const rieng = (c.detail || '').trim() || c.title;
      const dtCo = (c.objectCode || '').trim();
      alloc.rows.forEach((r) => {
        /* 🔴 BỎ HẲN DÒNG, không đẩy một dòng 0đ. Khác hẳn chỗ dòng 0đ vẫn giữ ở trên: dòng 0đ kia
           là điểm ĐANG hoạt động mà tháng này không phát sinh, còn đây là điểm CỐ Ý không nhận
           chi phí — đẩy lên MISA một bút toán 0đ cho nó là ghi nhận chi phí cho một cơ sở đã nghỉ,
           đúng thứ anh Thắng muốn tránh. File thật cũng bỏ hẳn (Pinball chi tiết không có PBSCVV). */
        if (r.khongChiPhi) return;
        rows.push({
          ngay: ky.text,
          soCT,
          dienGiai: chung,
          /* Tên điểm ĐÃ mang sẵn "POSH MN …" nên nối thẳng, không chèn thêm tên bộ phận lần nữa. */
          dienGiaiHT: `${rieng} - ${r.name}`,
          tkNo: tk.no,
          tkCo: tk.co,
          maDoiTuongNo: '',        // file thật T8/2026 để trống toàn bộ cột này
          maDoiTuongCo: dtCo,
          soTien: r.vals[c.key] || 0,
          maDonVi: r.code || '',
        });
      });
    });
    return { dept: alloc.dept, period: ky, rows };
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
      /* Nói ra chỗ này, đừng lặng lẽ lùi về mã bộ phận: số chứng từ sai vẫn nhập được vào MISA,
         và chỉ lộ ra khi kế toán đi tìm chứng từ theo số mà không thấy. */
      if (!(d.misaPrefix || '').trim()) {
        issues.push({ level: 'warn', msg: `Bộ phận "${d.name}" chưa có mã chứng từ MISA — số chứng từ sẽ lấy tạm "${String(d.id).toUpperCase()}".` });
      }
    });
    /* ═════════════════════════════════════════════════════════════════════════════════════════
     * CƠ SỞ KHÔNG NHẬN CHI PHÍ — nói ra, đừng để im.
     *
     * 🔴 Nếu MỌI cơ sở của một bộ phận đều tích "không nhận chi phí" thì mẫu số bằng 0, mọi tỷ
     *    trọng ra 0, và TOÀN BỘ chi phí của bộ phận ấy BỐC HƠI khỏi sheet phân bổ lẫn tờ nhập
     *    MISA — trong khi File tổng báo cáo vẫn cộng đủ. Hai sheet trong cùng một file lệch nhau
     *    mà không có dòng nào báo, chỉ lộ ra khi kế toán đối chiếu tổng.
     * ═════════════════════════════════════════════════════════════════════════════════════════ */
    depts.forEach((d) => {
      const ds = (state.sites || []).filter((x) => x.dept === d.id);
      if (!ds.length) return;
      const nghi = ds.filter((x) => x.khongChiPhi);
      if (!nghi.length) return;
      const conNhan = ds.reduce((a, x) => a + (x.khongChiPhi ? 0 : num(x.revenue)), 0);
      if (conNhan <= 0) {
        issues.push({ level: 'error', msg: `Bộ phận "${d.name}": mọi cơ sở đều không nhận chi phí (hoặc doanh thu 0) — toàn bộ chi phí của bộ phận sẽ KHÔNG được phân bổ về điểm nào. Bỏ tích ít nhất một cơ sở.` });
      } else {
        issues.push({ level: 'info', msg: `Bộ phận "${d.name}": ${nghi.length} cơ sở không nhận chi phí (${nghi.map((x) => x.code || x.name).join(', ')}) — vẫn ghi doanh thu, phần chi phí chia lại cho các cơ sở còn lại.` });
      }
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
    const pending = (state.costItems || []).filter((it) => it.status === 'cho_duyet').length;
    if (pending) issues.push({ level: state.options && state.options.includePending ? 'warn' : 'info', msg: `${pending} khoản đang chờ duyệt${state.options && state.options.includePending ? ' — ĐANG được tính vào báo cáo (tuỳ chọn "tính cả khoản chờ duyệt").' : ' — chưa tính vào báo cáo.'}` });
    activeCostItems(state).forEach((it, idx) => {
      const label = it.name || `Khoản #${idx + 1}`;
      if (num(it.total) === 0) issues.push({ level: 'warn', msg: `Khoản "${label}" có tổng tiền = 0.` });
      if (it.split === 'custom') {
        const s = Object.values(itemShares(state, it)).reduce((a, b) => a + b, 0);
        if (Math.abs(s - num(it.total)) > 0.5)
          issues.push({ level: 'error', msg: `Khoản "${label}": tổng chia nhóm (${fmt(s)}) ≠ tổng tiền (${fmt(num(it.total))}).` });
      }
      if (!it.misaDetail) issues.push({ level: 'info', msg: `Khoản "${label}" chưa có nội dung diễn giải chi tiết MISA (tiêu đề cột báo cáo).` });
      /* 🔴 CẢNH BÁO, KHÔNG PHẢI "info": thiếu tài khoản thì dòng lên tờ nhập MISA có TK Nợ/TK Có
         TRỐNG, và MISA từ chối CẢ chứng từ chứ không riêng dòng ấy. Lúc đó người ta đã ngồi trong
         MISA rồi, phải quay ngược về đây sửa — nên nói sớm, ngay trên màn kiểm tra. */
      const tk = parseAccount(it.account);
      if (!tk.no || !tk.co) {
        issues.push({ level: 'warn', msg: `Khoản "${label}" chưa đủ cặp tài khoản Nợ/Có — tờ nhập MISA sẽ trống hai cột này và MISA từ chối cả chứng từ.` });
      }
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
        { id: 'posh', name: 'Posh', group: 'MTD', ratio: 0.6, revenue: 0, revenueOverride: false, unitCode: 'Posh MN', misaPrefix: 'POSH' },
        { id: 'jp', name: 'JP', group: 'MTD', ratio: 0.4, revenue: 0, revenueOverride: false, unitCode: 'JP MN', misaPrefix: 'JP' },
        { id: 'funzone', name: 'Funzone', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', misaPrefix: 'FZ' },
        { id: 'event', name: 'Event', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', misaPrefix: 'EV' },
        { id: 'farm', name: 'Farm', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', misaPrefix: 'FA' },
        { id: 'tutu', name: 'Tutu', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', misaPrefix: 'TU' },
        { id: 'pinball', name: 'Pinball', group: 'KVC', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', misaPrefix: 'PBMN' },
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
    st.sites = (st.sites || []).map((x) => ({ code: '', name: '', revenue: 0, khongChiPhi: false, ...x, khongChiPhi: !!x.khongChiPhi }));
    st.costItems = (st.costItems || []).map((it) => {
      const o = { kind: 'company', name: '', misaGeneral: '', misaDetail: '', account: '', total: 0, split: 'equal', shares: {}, objectCode: '', excludeDepts: [], groupKey: '', method: '', note: '', status: '', createdBy: '', ...it };
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
    st.options = Object.assign({ includePending: false }, st.options || {});
    /* ═════════════════════════════════════════════════════════════════════════════════════════
     * `misaPrefix` = phần giữa của SỐ CHỨNG TỪ (`NVK<prefix><ngày><tháng><stt>`).
     *
     * 🔴 PHẢI GIEO THEO MÃ BỘ PHẬN, KHÔNG ĐƯỢC ĐỂ TRỐNG RỒI SUY RA TỪ TÊN. Bảy mã dưới đây đọc
     *    thẳng từ file thật T8/2026 của anh Thắng, và chúng KHÔNG theo quy luật nào: Funzone → FZ,
     *    Event → EV, Pinball → PBMN (có đuôi miền). Suy từ tên thì Pinball ra "PINBA" — một số
     *    chứng từ trông rất hợp lý, nhập vào MISA trót lọt, và sai.
     *
     * ⚠️ Gieo ở normalizeState chứ không chỉ ở state mặc định: dữ liệu ANH THẮNG ĐÃ LƯU từ trước
     *    không có khoá này. Chỉ đặt ở state mặc định thì người đang dùng dở sẽ không bao giờ nhận
     *    được mã đúng — mà họ lại chính là người duy nhất có dữ liệu thật.
     * ═════════════════════════════════════════════════════════════════════════════════════════ */
    const MISA_PREFIX = { posh: 'POSH', jp: 'JP', funzone: 'FZ', event: 'EV', farm: 'FA',
      tutu: 'TU', pinball: 'PBMN' };
    st.departments = (st.departments || []).map((d) => ({
      ...d,
      /* Người dùng đã tự đặt thì GIỮ — bảng trên chỉ để mồi, không để đè. */
      misaPrefix: (d.misaPrefix || '').trim() || MISA_PREFIX[d.id] || '',
    }));
    st.manualCols = (st.manualCols || []).map((m) => ({ name: '', account: '', misaGeneral: '', misaDetail: '', objectCode: '', values: {}, ...m, id: m.id || newId('mc') }));
    /* ═════════════════════════════════════════════════════════════════════════════════════════
     * TÀI KHOẢN LƯƠNG — MỖI BỘ PHẬN MỘT SỐ KHÁC NHAU, KHÔNG PHẢI MỘT SỐ DÙNG CHUNG.
     *
     * 🔴 Anh Thắng 16/09/2026: *"sai nữa rồi"* — màn hình hiện 64131 cho Event, trong khi file
     *    thật ghi 64191. Gốc: bản trước đọc MỖI tab "Posh chi tiết" rồi gieo 64131 cho cả bảy bộ
     *    phận. Đúng cái sai đã mắc ở vụ "Mã đối tượng Có": đọc một tab rồi suy ra cho tất cả.
     *
     * Đếm lại trên CẢ 7 tab "… chi tiết" của file thật T8/2026 — TK Nợ của hai chứng từ lương:
     *     Tutu 64101 · JP 64111 · Funzone 64121 · Posh 64131 · Farm 64161 · Pinball 64171 ·
     *     Event 64191                                   (TK Có = 3341 cho tất cả)
     *
     * ⚠️ Số này KHÔNG suy ra được từ bất cứ đâu — nó là số hiệu tài khoản kế toán đặt cho từng bộ
     *    phận. Sai một số thì chứng từ vẫn nhập được vào MISA, chỉ là chi phí lương của bộ phận
     *    này chạy vào tài khoản của bộ phận khác — sổ vẫn cân, chỉ sai chỗ.
     * ═════════════════════════════════════════════════════════════════════════════════════════ */
    const TK_LUONG = { tutu: '64101', jp: '64111', funzone: '64121', posh: '64131',
      farm: '64161', pinball: '64171', event: '64191' };
    st.salaryDept = (st.salaryDept || []).map((r) => {
      /* Bộ phận lạ (do người dùng tự thêm) thì KHÔNG bịa số: để trống, và validate() đã có sẵn
         cảnh báo "chưa đủ cặp tài khoản" lo phần nhắc. Bịa một số trông hợp lý là tệ nhất. */
      const mac = TK_LUONG[r.dept] ? `N${TK_LUONG[r.dept]}/C3341` : '';
      return {
        misaGeneral: '', misaDetail: '', misaGeneral2: '', misaDetail2: '', ...r,
        misaAccount: (r.misaAccount || '').trim() || mac,
        misaAccount2: (r.misaAccount2 || '').trim() || mac,
        misaObjectCode: r.misaObjectCode || '',
        misaObjectCode2: r.misaObjectCode2 || '',
      };
    });
    st.salarySites = (st.salarySites || []).map((r) => ({ reported: 0, report: 0, dntt: 0, actual: 0, unitCode: '', misaGeneral: '', misaDetail: '', ...r, id: r.id || newId('ss') }));
    return st;
  }

  return {
    SALARY_DEPT_FIELDS,
    COST_STATUSES,
    activeCostItems,
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
    misaRows,
    parseAccount,
    periodLastDay,
    validate,
    emptyState,
    normalizeState,
  };
});
