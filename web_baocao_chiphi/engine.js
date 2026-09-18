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
 *   sites        [{dept, code, name, revenue, khongChiPhi, fabiTen, gheTen}]   điểm bán / cơ sở, dùng để tính doanh thu BP và phân bổ theo điểm
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

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * GHÉP CỬA HÀNG "DOANH THU FABi" VỚI ĐIỂM BÁN — anh Thắng 18/09/2026: *"lấy đẩy doanh thu từ
   * doanh thu hcm sang báo cáo tổng"*.
   *
   * 🔴 MÁY CHỈ ĐỀ NGHỊ, NGƯỜI MỚI QUYẾT. Tên hai bên viết khác hẳn nhau — bên FABi là tên quán
   *    ("TuTu Train - Aeon Tân Phú", "(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ"), bên này là tên điểm
   *    trong sổ kế toán. Không có luật nào ghép đúng 100%, mà ghép sai thì doanh thu chạy vào
   *    nhầm bộ phận và kéo theo TOÀN BỘ chi phí phân bổ sai — sổ vẫn cân, chỉ sai chỗ.
   *    Nên hàm này chỉ trả về ĐỀ NGHỊ kèm lý do; ghi vào báo cáo là một bước riêng, sau khi người
   *    dùng nhìn và xác nhận. Ghép xong nhớ lại ở `site.fabiTen` để tháng sau khỏi làm lại.
   *
   * Cách ghép, theo thứ tự tin cậy giảm dần:
   *    1. `da_luu`  — site.fabiTen trùng đúng tên cửa hàng (lần trước người dùng đã chốt)
   *    2. `ten`     — khoá tên (bỏ dấu, bỏ ký tự lạ) trùng khít
   *    3. `gan`     — trùng nhiều từ đặc trưng nhất, và chỉ khi HƠN HẲN cái thứ nhì
   *    4. null      — không đoán; để người dùng tự chọn
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */

  /** Khoá so tên: bỏ dấu tiếng Việt, hoa hết, bỏ mọi thứ không phải chữ/số. */
  function khoaTen(s) {
    return String(s == null ? '' : s)
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd').replace(/Đ/g, 'D')
      .toUpperCase().replace(/[^A-Z0-9]+/g, '');
  }

  /* ĐUÔI PHÁP NHÂN. Tên bên FABi gần như cái nào cũng kèm "( Dịch Vụ và Giải Trí K&H )" —
     đó là tên công ty, không phải tên quán, và nó có ở MỌI cửa hàng nên chẳng phân biệt được gì.
     Không cắt thì không tên nào trùng khít được, mọi thứ rơi xuống nhánh đoán gần.
     ⚠️ Chỉ cắt khi dấu "(" nằm SAU phần chữ: "(GHOST BRIDE BÀ RỊA)Cô Dâu Âm Phủ" mở ngoặc ngay
        từ ký tự đầu — cắt ở đó là mất sạch tên. */
  function tenGonFabi(s) {
    const t = String(s == null ? '' : s).trim();
    const i = t.lastIndexOf('(');
    return (i > 3 ? t.slice(0, i) : t).trim();
  }

  /** Tách thành từ (>=3 ký tự) để đếm phần chung — "AEON", "TANPHU"… */
  function tuTen(s) {
    return String(s == null ? '' : s)
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd').replace(/Đ/g, 'D')
      .toUpperCase().split(/[^A-Z0-9]+/).filter((t) => t.length >= 3);
  }

  /* Từ quá phổ biến thì KHÔNG tính là bằng chứng: "MN" có ở mọi điểm, "AEON" có ở cả chục nơi.
     Đếm cả chúng thì hai điểm khác hẳn nhau vẫn ra điểm số cao bằng nhau. */
  const TU_CHUNG = ['MN', 'DICH', 'VU', 'VA', 'GIAI', 'TRI', 'CONG', 'TY', 'TNHH', 'CHI', 'NHANH'];

  /* Điểm ghép = SỐ TỪ CHUNG + TỶ LỆ phủ. Chỉ đếm từ chung là chưa đủ:
     "Tutu Train - Bình Dương" và "POSH MN AEON MALL BÌNH DƯƠNG" chung đúng 2 từ (BINH, DUONG) —
     đủ để lọt nếu chỉ xét số lượng, mà ghép vậy là doanh thu Tutu chạy vào Posh rồi kéo theo
     toàn bộ chi phí phân bổ sai. Thêm tỷ lệ thì hai từ ấy chỉ phủ 2/4 tên bên kia → rớt;
     còn "Tutu Train - Estella" ↔ "TUTU MN ESTELLA" phủ 2/2 → đậu, dù cũng chỉ 2 từ. */
  function diemGhep_(a, b) {
    const ta = tuTen(a).filter((t) => TU_CHUNG.indexOf(t) < 0);
    const tb = tuTen(b).filter((t) => TU_CHUNG.indexOf(t) < 0);
    if (!ta.length || !tb.length) return { chung: 0, ty_le: 0 };
    let chung = 0;
    ta.forEach((t) => { if (tb.indexOf(t) >= 0) chung++; });
    return { chung, ty_le: chung / Math.min(ta.length, tb.length) };
  }

  const NGUONG_TU = 2;      // ít nhất 2 từ đặc trưng chung
  const NGUONG_TY_LE = 0.6; // và phủ ít nhất 60% cái tên ngắn hơn

  /* 🔴 CƠ SỞ BỎ HẲN. Anh Thắng 18/09/2026: *"Cái anh đang làm là MN"* — trang Ghế liệt kê cơ sở
     CẢ NƯỚC, còn báo cáo này chỉ của miền Nam. Không có chỗ đánh dấu "cơ sở này không thuộc báo
     cáo" thì hai chuyện xấu xảy ra: hoặc "Lấy hết" kéo luôn cơ sở ngoài vùng vào làm phồng doanh
     thu MN, hoặc chúng đứng mãi ở "chưa ghép" và dòng THIẾU LIÊN KẾT kêu suốt đời — kêu mãi thì
     người ta thôi đọc, rồi hôm có cơ sở MN thật sự rơi ra ngoài cũng chẳng ai để ý.
     Nên: bỏ hẳn được, nhớ theo từng nguồn, và bỏ rồi thì thôi không hỏi lại. */
  function dsBoQua(state, khoa) {
    const m = ((state || {}).options || {}).boQuaNguon || {};
    return (m[khoa || 'fabiTen'] || []).map((x) => String(x).trim()).filter(Boolean);
  }

  /**
   * @param {object} state
   * @param {Array}  ds   [{cua_hang, thanh_tien, so_ngay}] — từ KHBC_FABi
   * @return {Array} [{cua_hang, thanh_tien, so_ngay, siteIndex, cach, diem, deNghi}]
   */
  function ghepFabi(state, ds, khoa, bpCho) {
    khoa = khoa || 'fabiTen';
    const sites = state.sites || [];
    /* 🔴 NGUỒN NÀO CHỈ THUỘC MẤY BỘ PHẬN ẤY. Anh Thắng 18/09/2026: nguồn Ghế có 1.243.443.000,
       ghép đủ 56 cửa hàng, mà Posh chỉ lên 777.443.000 — 466.000.000 chảy sang Event, vì ghép gần
       đúng theo tên nối "AEON MALL …" bên Ghế vào điểm Event cùng địa điểm. Doanh thu ghế nằm ở
       bộ phận Event thì tỷ trọng phân bổ chi phí sai cho CẢ HAI bộ phận, mà tổng vẫn cộng đẹp.
       Ghế là Posh/JP — ngoài hai bộ phận ấy thì không ghép, kể cả liên kết cũ đã lưu. */
    const chan = (i) => (bpCho && bpCho.length ? bpCho.indexOf((sites[i] || {}).dept) < 0 : false);
    const khoaSite = sites.map((s) => khoaTen(s.name));
    const bq = dsBoQua(state, khoa);
    const ra = (ds || []).map((d) => ({
      cua_hang: String(d.cua_hang || ''),
      thanh_tien: num(d.thanh_tien),
      so_ngay: num(d.so_ngay),
      /* Cơ sở đã BỎ HẲN thì không ghép, không đoán, không tính vào phần "đang rơi" — xem dsBoQua. */
      boHan: bq.indexOf(String(d.cua_hang || '').trim()) >= 0,
      siteIndex: null, cach: 'khong', diem: 0,
    }));
    ra.forEach((r) => { if (r.boHan) r.cach = 'bo_han'; });
    const daDung = {};
    const nhan = (k, i, cach, diem) => { ra[k].siteIndex = i; ra[k].cach = cach; ra[k].diem = diem || 0; daDung[i] = true; };

    // 1. đã lưu từ lần trước — chốt trước tiên, người dùng đã quyết rồi
    ra.forEach((r, k) => {
      if (r.boHan || r.siteIndex !== null || !r.cua_hang.trim()) return;
      const i = sites.findIndex((s, ix) => !daDung[ix] && !chan(ix) && (s[khoa] || '').trim() === r.cua_hang.trim());
      if (i >= 0) { nhan(k, i, 'da_luu'); }
    });

    // 2. khoá tên trùng khít (sau khi cắt đuôi pháp nhân)
    ra.forEach((r, k) => {
      if (r.boHan || r.siteIndex !== null) return;
      const kh = khoaTen(tenGonFabi(r.cua_hang));
      if (!kh) return;
      const i = khoaSite.findIndex((x, ix) => !daDung[ix] && !chan(ix) && x && x === kh);
      if (i >= 0) { nhan(k, i, 'ten'); }
    });

    /* 3. Ghép gần đúng — XẾP THEO ĐIỂM, KHÔNG THEO THỨ TỰ DANH SÁCH.
       Chạy tuần tự theo danh sách thì cửa hàng đứng trước "xí" mất điểm bán mà lẽ ra thuộc về
       cửa hàng đứng sau khớp hơn — kết quả đổi theo thứ tự sắp xếp của trang FABi, một thứ
       chẳng liên quan gì tới chuyện ghép. */
    const cap = [];
    ra.forEach((r, k) => {
      if (r.boHan || r.siteIndex !== null) return;
      let top1 = 0, top2 = 0;
      sites.forEach((s, i) => {
        if (daDung[i] || chan(i)) return;
        const d = diemGhep_(tenGonFabi(r.cua_hang), s.name);
        if (d.chung < NGUONG_TU || d.ty_le < NGUONG_TY_LE) return;
        if (d.chung > top1) { top2 = top1; top1 = d.chung; } else if (d.chung > top2) { top2 = d.chung; }
        cap.push({ k, i, chung: d.chung, ty_le: d.ty_le });
      });
      /* Hai điểm cùng giống như nhau thì THÀ ĐỂ TRỐNG: đoán bừa là 50% sai, mà cái sai ấy im
         lặng — số vẫn vào, tổng vẫn đẹp. */
      if (top1 > 0 && top1 === top2) {
        for (let x = cap.length - 1; x >= 0 && cap[x].k === k; x--) { cap.pop(); }
      }
    });
    cap.sort((a, b) => (b.chung - a.chung) || (b.ty_le - a.ty_le));
    cap.forEach((c) => {
      if (ra[c.k].siteIndex !== null || daDung[c.i]) return;
      nhan(c.k, c.i, 'gan', c.chung);
    });
    return ra;
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * LIÊN KẾT SỐNG VỚI DOANH THU FABi — anh Thắng 18/09/2026: *"thay vì đẩy thì nó vậy tự link và
   * lấy dữ liệu realtime qua"*.
   *
   * Khác hẳn `ghepFabi` ở một điểm sống còn: đường TỰ ĐỘNG **chỉ đi theo liên kết người đã chốt**
   * (`site.fabiTen`), TUYỆT ĐỐI không đoán tên. Đoán thì phải có người nhìn; chạy ngầm mà đoán là
   * một ngày nào đó doanh thu tự nhảy vào nhầm điểm, không ai bấm gì, không ai biết gì.
   *
   * Muốn thêm liên kết mới thì vẫn qua nút "Nạp từ Doanh thu FABi" — ở đó có màn xem trước.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  function dongBoFabi(state, ds, khoa, bpCho) {
    khoa = khoa || 'fabiTen';
    const sites = state.sites || [];
    const saiBp = [];
    const tien = {};
    (ds || []).forEach((d) => { tien[String(d.cua_hang || '').trim()] = num(d.thanh_tien); });
    const doi = [];
    let daLinh = 0;
    sites.forEach((s, i) => {
      const k = (s[khoa] || '').trim();
      if (!k) return;
      /* Liên kết cũ trỏ vào điểm sai bộ phận: KHÔNG ghi số mới vào đó nữa, và báo ra để gỡ. */
      if (bpCho && bpCho.length && bpCho.indexOf(s.dept) < 0) {
        saiBp.push({ i, code: s.code, name: s.name, dept: s.dept, nguon: khoa, fabiTen: k });
        return;
      }
      daLinh++;
      if (!Object.prototype.hasOwnProperty.call(tien, k)) {
        /* Cửa hàng biến mất bên FABi (đổi tên, ngừng bán). KHÔNG đưa về 0: số 0 trông y hệt một
           tháng ế, mà thật ra là mất liên kết. Báo ra để người ta đi nối lại. */
        doi.push({ i, code: s.code, name: s.name, nguon: khoa, fabiTen: k, mat: true });
        return;
      }
      const moi = tien[k];
      if (num(s.revenue) !== moi) {
        doi.push({ i, code: s.code, name: s.name, nguon: khoa, fabiTen: k, cu: num(s.revenue), moi });
        s.revenue = moi;
      }
    });
    /* 🔴 TIỀN BÊN NGUỒN KHÔNG NỐI VÀO ĐIỂM NÀO THÌ RƠI RA NGOÀI BÁO CÁO — VÀ IM LẶNG.
       Anh Thắng 18/09/2026: trang Ghế 01→17/09 tổng 1.216.383.000, báo cáo chỉ thấy 546.005.000.
       Chênh 670 triệu vì phần lớn cơ sở bên Ghế chưa ai nối vào điểm nào; không dòng nào nói ra,
       nên nhìn màn Tổng quan thì tưởng doanh thu tháng này thấp. Đếm phần dôi ra và trả về để
       giao diện bày thành số, đừng để nó biến mất. */
    const daNoi = {};
    sites.forEach((x) => {
      /* Điểm nối SAI BỘ PHẬN không phải một liên kết hợp lệ — đừng coi cơ sở ấy là "đã có chỗ",
         không thì tiền của nó lặng lẽ biến khỏi cả phần "đang rơi" lẫn báo cáo. */
      if (bpCho && bpCho.length && bpCho.indexOf(x.dept) < 0) return;
      const k = (x[khoa] || '').trim();
      if (k) daNoi[k] = true;
    });
    const bq = dsBoQua(state, khoa);
    const chuaNoi = (ds || []).filter((d) => {
      const t = String(d.cua_hang || '').trim();
      return !daNoi[t] && bq.indexOf(t) < 0 && num(d.thanh_tien) !== 0;
    });
    return { daLinh, doi, soDoi: doi.filter((x) => !x.mat).length, mat: doi.filter((x) => x.mat), saiBp,
      chuaNoi, tongNguon: (ds || []).reduce((a, d) => a + num(d.thanh_tien), 0),
      tongChuaNoi: chuaNoi.reduce((a, d) => a + num(d.thanh_tien), 0) };
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * CƠ SỞ MỚI BÊN FABi → TỰ TÁCH ĐIỂM MỚI BÊN NÀY
   *
   * Anh Thắng 18/09/2026: *"Sau khi FABi đổi cơ sở mới, thì bên FABi tự tách điểm mới, thì bên
   * doanh thu cũng phải tự tách điểm mới, chứ đừng kẹt nhé"*.
   *
   * Trước đó cửa hàng không khớp điểm nào thì đứng mãi ở "— chưa ghép —" và doanh thu của nó rơi
   * ra ngoài báo cáo — im lặng. Mở một quán mới là tháng ấy báo cáo thiếu nguyên một cơ sở.
   *
   * ⚠️ ĐOÁN BỘ PHẬN, KHÔNG ĐOÁN MÃ ĐƠN VỊ. Bộ phận đoán sai thì thấy ngay trên màn xem trước và
   *    sửa một cái là xong. Còn "Mã đơn vị" là mã trong sổ MISA — bịa ra một mã trông hợp lý thì
   *    nó đi thẳng vào tờ nhập MISA và hạch toán vào một đơn vị KHÔNG TỒN TẠI. Nên để TRỐNG, và
   *    validate() có một dòng cảnh báo riêng cho điểm có doanh thu mà thiếu mã.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */

  /* Chữ hiệu của từng bộ phận, dùng để đoán cửa hàng mới thuộc về đâu. Đọc từ tên quán thật trên
     trang Doanh thu FABi của anh Thắng. Đoán sai cũng không sao — người dùng đổi trên màn xem
     trước trước khi ghi. */
  const DAU_HIEU_BP = {
    tutu: ['TUTU'],
    funzone: ['FUNZONE', 'VRFUN', 'VR'],
    farm: ['FARM', 'ECOFARM'],
    event: ['GHOST', 'SNOW', 'NGOINHAMA', 'MA', 'BRIDE'],
    pinball: ['PINBALL'],
    posh: ['POSH'],
    jp: ['JP'],
  };

  /** Đoán bộ phận cho một cửa hàng FABi mới. '' = không đoán được. */
  function doanBoPhan(tenCuaHang, departments) {
    const t = khoaTen(tenGonFabi(tenCuaHang));
    const co = (departments || []).map((d) => d.id);
    let nhat = '', daiNhat = 0;
    Object.keys(DAU_HIEU_BP).forEach((dept) => {
      if (co.indexOf(dept) < 0) return;
      DAU_HIEU_BP[dept].forEach((h) => {
        /* Lấy dấu hiệu DÀI NHẤT khớp được: "VRFUN" thắng "VR", "ECOFARM" thắng "FARM".
           Không thì một chữ hai ký tự vô tình nằm trong tên quán là đủ kéo nó sang bộ phận khác. */
        if (h.length > daiNhat && t.indexOf(h) >= 0) { daiNhat = h.length; nhat = dept; }
      });
    });
    return nhat;
  }

  /**
   * Tạo điểm mới cho những dòng người dùng đã chọn "tạo mới".
   * @param {Array} ghep [{cua_hang, thanh_tien, siteIndex, taoMoi:'<deptId>'}]
   * @return {{tao:Array, ghep:Array}} ghep đã được cập nhật siteIndex trỏ vào điểm vừa tạo
   */
  function taoDiemTuFabi(state, ghep, khoa) {
    khoa = khoa || 'fabiTen';
    const tao = [];
    (ghep || []).forEach((g) => {
      const dept = (g.taoMoi || '').trim();
      if (!dept || g.siteIndex !== null) return;
      if (!(state.departments || []).some((d) => d.id === dept)) return;
      const ten = tenGonFabi(g.cua_hang) || String(g.cua_hang || '').trim();
      if (!ten) return;
      state.sites = state.sites || [];
      const moi = {
        dept,
        code: '',                 // 🔴 KHÔNG bịa mã đơn vị — xem khối dài ở trên
        name: ten,
        revenue: num(g.thanh_tien),
        khongChiPhi: false,
        fabiTen: '',
        gheTen: '',
      };
      moi[khoa] = String(g.cua_hang || '');
      state.sites.push(moi);
      g.siteIndex = state.sites.length - 1;
      g.cach = 'tao';
      tao.push({ dept, name: ten, fabiTen: g.cua_hang });
    });
    return { tao, ghep };
  }

  /**
   * Ghi doanh thu đã ghép vào state. Trả về {xong, boQua} — KHÔNG tự gọi, giao diện gọi sau khi
   * người dùng bấm xác nhận.
   * @param {Array} ghep [{cua_hang, thanh_tien, siteIndex}]
   */
  const KHOA_NGUON = ['fabiTen', 'gheTen'];
  function napFabi(state, ghep, khoa) {
    khoa = khoa || 'fabiTen';
    let xong = 0, bo = 0;
    (ghep || []).forEach((g) => {
      /* Bỏ HẲN là một quyết định, không phải một dòng "chưa kịp ghép" — đừng đếm vào `boQua` rồi
         báo lại như thể còn việc phải làm. */
      if (g.boHan) return;
      const i = g.siteIndex;
      if (i === null || i === undefined || !state.sites[i]) { bo++; return; }
      state.sites[i].revenue = num(g.thanh_tien);
      /* Nhớ lại lựa chọn để tháng sau tự ghép — đây là thứ biến một việc làm tay hằng tháng
         thành một việc làm tay ĐÚNG MỘT LẦN. */
      state.sites[i][khoa] = String(g.cua_hang || '');
      /* 🔴 GỠ khoá của nguồn KIA. Một điểm nối hai nguồn thì mỗi lần mở kỳ chúng ghi đè nhau, và
         số cuối cùng phụ thuộc cái nào chạy sau — một cái sai đổi theo thứ tự, không tài nào
         dò ra. */
      KHOA_NGUON.forEach((k) => { if (k !== khoa) { state.sites[i][k] = ''; } });
      xong++;
    });
    return { xong, boQua: bo };
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * BẮT ĐẦU MỘT KỲ MỚI — GIỮ DANH MỤC, XOÁ SỐ.
   *
   * 🔴 Anh Thắng 18/09/2026: *"ủa, nếu chọn kỳ tháng 9 nó phải trống chứ"*. Đúng. Trước đây đổi
   *    sang kỳ chưa có thì app hỏi "đẩy dữ liệu đang có lên làm bản gốc?" và chép NGUYÊN CẢ SỐ
   *    LIỆU của kỳ trước — doanh thu từng điểm, lương từng bộ phận, tiền từng khoản chi phí.
   *
   *    Đó là loại sai tệ nhất trong cả cái app này: kỳ mới mở ra đã có sẵn một bộ số trông hoàn
   *    chỉnh, cộng đúng, tỷ trọng đẹp — nhưng là số của THÁNG TRƯỚC. Không có dòng nào báo, không
   *    có ô nào đỏ. Người dùng nhập thêm vài khoản mới rồi xuất báo cáo, và tháng 9 đi ra bằng
   *    doanh thu tháng 8.
   *
   * Nên tách hai thứ: DANH MỤC (nhóm, bộ phận, điểm bán, mã đơn vị, tài khoản, lời diễn giải,
   * cách chia…) thì chép sang — đó là công dựng một lần dùng mãi. SỐ thì về 0 hết.
   *
   * ⚠️ Giữ `fabiTen` và `khongChiPhi` của điểm bán: đó là DANH MỤC, không phải số. Xoá đi thì
   *    tháng nào cũng phải ghép lại tên cửa hàng FABi và tích lại cơ sở nghỉ — đúng cái việc mà
   *    hai tính năng kia sinh ra để khỏi phải làm lại.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  function batDauKyMoi(state) {
    const st = normalizeState(JSON.parse(JSON.stringify(state || {})));
    st.sites = (st.sites || []).map((s) => ({ ...s, revenue: 0 }));
    st.departments = (st.departments || []).map((d) => ({ ...d, revenue: 0, revenueOverride: false }));
    st.manualCols = (st.manualCols || []).map((m) => ({ ...m, values: {} }));
    st.salaryDept = (st.salaryDept || []).map((r) => {
      const o = { ...r };
      SALARY_DEPT_FIELDS.forEach((f) => { o[f.key] = 0; });
      return o;
    });
    st.salarySites = (st.salarySites || []).map((r) => ({ ...r, reported: 0, report: 0, dntt: 0, actual: 0 }));
    /* Khoản chi phí: GIỮ danh mục (tên, tài khoản, lời MISA, cách chia, mã đối tượng) nhưng số
       tiền về 0 và trạng thái về "chờ duyệt" — kỳ mới thì chưa ai duyệt gì cả. Giữ nguyên tiền là
       đúng cái bẫy ở trên, chỉ khác chỗ nó nằm ở cột chi phí thay vì cột doanh thu. */
    st.costItems = (st.costItems || []).map((it) => ({
      ...it, total: 0, shares: {}, status: 'cho_duyet', approvedBy: '', createdAt: '',
    }));
    /* Số đã về 0 hết thì từ đây chúng là số CỦA KỲ NÀY. */
    st.soCuaKy = khoaKy(st.period);
    return st;
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * LƯƠNG TỪ PLUGIN CHẤM CÔNG — MỖI CƠ SỞ MỘT DÒNG
   *
   * Anh Thắng 18/09/2026: *"giờ Lương lấy từ trang nhân sự theo cơ sở"*, và chọn "tổng mỗi cơ sở
   * một dòng" (không kéo từng nhân viên sang).
   *
   * Dùng lại đúng lối đã làm cho doanh thu: ghép có xem trước → nhớ liên kết (`nsTen`) → kỳ sau
   * tự về. Khác một điểm: ghép vào DÒNG LƯƠNG (Mục III) chứ không vào điểm bán.
   *
   * 🔴 CƠ SỞ CHƯA CÓ GIÁ GIỜ THÌ KHÔNG GHI SỐ 0. Bên Chấm công, Khu vui chơi trả `co_luong=false`
   *    vì chưa khai giá giờ. Ghi 0 vào báo cáo thì nhìn y hệt "tháng này không có lương" — và chi
   *    phí lương của cả một nhóm biến mất mà tổng vẫn cộng đẹp. Những dòng ấy bị BỎ QUA, và giao
   *    diện phải bày lý do.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */

  /** Ghép cơ sở bên Chấm công với dòng lương (Mục III). Cùng luật với ghepFabi. */
  function ghepLuong(state, ds) {
    const rows = state.salarySites || [];
    const khoaRow = rows.map((r) => khoaTen(r.name));
    const ra = (ds || []).map((d) => ({
      cua_hang: String(d.cua_hang || ''),
      thanh_tien: num(d.thanh_tien),
      co_luong: d.co_luong !== false,
      ghi_chu: String(d.ghi_chu || ''),
      bo_phan: String(d.bo_phan || ''),
      rowIndex: null, cach: 'khong', diem: 0,
    }));
    const daDung = {};
    const nhan = (k, i, cach, diem) => { ra[k].rowIndex = i; ra[k].cach = cach; ra[k].diem = diem || 0; daDung[i] = true; };

    ra.forEach((r, k) => {
      const i = rows.findIndex((x, ix) => !daDung[ix] && (x.nsTen || '').trim() === r.cua_hang.trim() && r.cua_hang.trim() !== '');
      if (i >= 0) { nhan(k, i, 'da_luu'); }
    });
    ra.forEach((r, k) => {
      if (r.rowIndex !== null) return;
      const kh = khoaTen(tenGonFabi(r.cua_hang));
      if (!kh) return;
      const i = khoaRow.findIndex((x, ix) => !daDung[ix] && x && x === kh);
      if (i >= 0) { nhan(k, i, 'ten'); }
    });
    const cap = [];
    ra.forEach((r, k) => {
      if (r.rowIndex !== null) return;
      let top1 = 0, top2 = 0;
      rows.forEach((x, i) => {
        if (daDung[i]) return;
        const d = diemGhep_(tenGonFabi(r.cua_hang), x.name);
        if (d.chung < NGUONG_TU || d.ty_le < NGUONG_TY_LE) return;
        if (d.chung > top1) { top2 = top1; top1 = d.chung; } else if (d.chung > top2) { top2 = d.chung; }
        cap.push({ k, i, chung: d.chung, ty_le: d.ty_le });
      });
      if (top1 > 0 && top1 === top2) {
        for (let x = cap.length - 1; x >= 0 && cap[x].k === k; x--) { cap.pop(); }
      }
    });
    cap.sort((a, b) => (b.chung - a.chung) || (b.ty_le - a.ty_le));
    cap.forEach((c) => {
      if (ra[c.k].rowIndex !== null || daDung[c.i]) return;
      nhan(c.k, c.i, 'gan', c.chung);
    });
    return ra;
  }

  /**
   * Ghi lương đã ghép vào Mục III. Dòng nào chọn "tạo mới" (`taoMoi` = deptId) thì thêm một dòng
   * lương mới cho cơ sở ấy — cùng ý với việc tự tách điểm bán khi FABi mở quán mới.
   */
  function napLuong(state, ghep) {
    let xong = 0, bo = 0, tao = 0, chuaGia = 0;
    state.salarySites = state.salarySites || [];
    (ghep || []).forEach((g) => {
      /* 🔴 Chưa có giá giờ thì BỎ QUA, không ghi 0 — xem khối dài ở trên. */
      if (g.co_luong === false) { chuaGia++; return; }
      let i = g.rowIndex;
      if ((i === null || i === undefined) && (g.taoMoi || '').trim()) {
        const ten = tenGonFabi(g.cua_hang) || String(g.cua_hang || '').trim();
        if (!ten) { bo++; return; }
        state.salarySites.push({
          id: newId('ss'), dept: g.taoMoi, groupTitle: '', stt: state.salarySites.length + 1,
          name: ten, reported: 0, report: 0, dntt: 0, actual: 0, unitCode: '',
          misaGeneral: '', misaDetail: '', nsTen: String(g.cua_hang || ''),
        });
        i = state.salarySites.length - 1;
        tao++;
      }
      if (i === null || i === undefined || !state.salarySites[i]) { bo++; return; }
      const r = state.salarySites[i];
      /* `reported` là ô đi vào cột "Lương NV" của phân bổ (xem allocateSites); `actual` là thực
         lĩnh. Lương từ chấm công là số ĐÃ TÍNH nên điền cả hai — để trống `actual` thì Mục III
         cộng ra một tổng, phân bổ lại ăn một tổng khác. */
      r.reported = num(g.thanh_tien);
      r.actual = num(g.thanh_tien);
      r.nsTen = String(g.cua_hang || '');
      xong++;
    });
    return { xong, boQua: bo, tao, chuaGia };
  }

  /** Liên kết sống: chỉ đi theo `nsTen` đã chốt, không đoán. Cùng luật với dongBoFabi. */
  function dongBoLuong(state, ds) {
    const rows = state.salarySites || [];
    const tien = {};
    const coGia = {};
    (ds || []).forEach((d) => {
      const k = String(d.cua_hang || '').trim();
      tien[k] = num(d.thanh_tien);
      coGia[k] = d.co_luong !== false;
    });
    const doi = [];
    let daLinh = 0;
    rows.forEach((r, i) => {
      const k = (r.nsTen || '').trim();
      if (!k) return;
      daLinh++;
      if (!Object.prototype.hasOwnProperty.call(tien, k)) {
        doi.push({ i, name: r.name, nsTen: k, mat: true });
        return;
      }
      /* Tháng này bên ấy chưa khai giá thì GIỮ SỐ CŨ, y như khi mất liên kết. */
      if (!coGia[k]) { doi.push({ i, name: r.name, nsTen: k, chuaGia: true }); return; }
      const moi = tien[k];
      if (num(r.reported) !== moi) {
        doi.push({ i, name: r.name, nsTen: k, cu: num(r.reported), moi });
        r.reported = moi;
        r.actual = moi;
      }
    });
    /* Cùng luật với dongBoFabi: lương bên Nhân sự không nối vào dòng nào thì cũng phải đếm ra. */
    const daNoi = {};
    rows.forEach((x) => { const k = (x.nsTen || '').trim(); if (k) daNoi[k] = true; });
    const chuaNoi = (ds || []).filter((d) => !daNoi[String(d.cua_hang || '').trim()]
      && d.co_luong !== false && num(d.thanh_tien) !== 0);
    return { daLinh, doi, soDoi: doi.filter((x) => !x.mat && !x.chuaGia).length,
      mat: doi.filter((x) => x.mat), chuaGia: doi.filter((x) => x.chuaGia),
      chuaNoi, tongChuaNoi: chuaNoi.reduce((a, d) => a + num(d.thanh_tien), 0) };
  }

  /** Khoá kỳ dạng '2026-09' — cùng cách đặt khoá với máy chủ (BaoCaoApi.periodKey). */
  function khoaKy(p) {
    p = p || {};
    return `${p.year}-${String(p.month).padStart(2, '0')}`;
  }

  /** Số trên màn hình có đúng là của kỳ đang chọn không. */
  function lechKy(state) {
    const nay = khoaKy((state || {}).period);
    const cua = String((state || {}).soCuaKy || nay);
    return cua === nay ? '' : cua;
  }

  /** Kiểm tra dữ liệu, trả về danh sách {level:'error'|'warn'|'info', msg}. */
  function validate(state) {
    const issues = [];
    const lech = lechKy(state);
    if (lech) {
      issues.push({ level: 'error', msg: `SỐ ĐANG XEM LÀ CỦA KỲ ${lech}, KHÔNG PHẢI ${khoaKy(state.period)}. `
        + 'Đổi ô chọn tháng chỉ đổi nhãn — doanh thu, lương và tiền từng khoản vẫn là của kỳ cũ. '
        + 'Vào ⋯ → "Xoá số liệu kỳ này" để dựng kỳ mới từ danh mục, rồi nạp lại số.' });
    }
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

    /* 🔴 ĐIỂM CÓ DOANH THU MÀ THIẾU MÃ ĐƠN VỊ. Hay gặp nhất với điểm vừa tự tách từ cửa hàng mới
       bên FABi: mã đơn vị là mã trong sổ MISA, app cố ý KHÔNG bịa. Thiếu thì tờ nhập MISA có cột
       "Mã đơn vị" trống, và bút toán không biết thuộc đơn vị nào. */
    const thieuMa = (state.sites || []).filter((s) => num(s.revenue) > 0 && !String(s.code || '').trim());
    if (thieuMa.length) {
      issues.push({ level: 'warn', msg: `${thieuMa.length} điểm có doanh thu nhưng chưa có Mã đơn vị (${thieuMa.slice(0, 5).map((s) => s.name).join(', ')}${thieuMa.length > 5 ? '…' : ''}) — tờ nhập MISA sẽ trống cột "Mã đơn vị".` });
    }

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
    /* 🔴 `soCuaKy` — SỐ TRÊN MÀN HÌNH LÀ CỦA KỲ NÀO.
       Đổi ô chọn tháng chỉ đổi cái NHÃN; doanh thu, lương, tiền từng khoản vẫn là của kỳ cũ cho
       tới khi có người nạp lại. Không ghi dấu lại thì màn Tổng quan bày một bộ số trông hoàn
       chỉnh mà là số tháng trước, đội tên tháng này — %CP/DT sai, không ô nào đỏ. Dấu này để
       validate() và màn Tổng quan bắt được chuyện đó. */
    st.soCuaKy = String(st.soCuaKy || khoaKy(st.period));
    st.groups = (st.groups || []).map((g) => ({ method: 'revenue', ...g }));
    st.departments = (st.departments || []).map((d) => ({ ratio: 0, revenue: 0, revenueOverride: false, unitCode: '', ...d }));
    /* HAI khoá liên kết, hai nguồn: `fabiTen` ← Doanh thu FABi, `gheTen` ← Ghế Massage (Posh/JP).
       Một điểm chỉ được nối MỘT nguồn — hai nguồn cùng ghi vào một điểm là chúng đè nhau mỗi lần
       mở kỳ, và số cuối cùng phụ thuộc vào cái nào chạy sau. napFabi() lo việc dọn khoá kia. */
    st.sites = (st.sites || []).map((x) => ({ code: '', name: '', revenue: 0, khongChiPhi: false, fabiTen: '', gheTen: '', ...x, khongChiPhi: !!x.khongChiPhi }));
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
    /* `fabiTuDong` — bật thì doanh thu của các điểm ĐÃ LIÊN KẾT tự lấy từ Doanh thu FABi mỗi lần
       mở kỳ, khỏi bấm. Mặc định TẮT: bật sẵn cho mọi site là tự ý đổi cách một cái app đang chạy
       lấy số, mà người dùng không hề yêu cầu. */
    /* `luongTuDong` — cùng ý với `fabiTuDong` nhưng cho Mục III: lương của các dòng ĐÃ LIÊN KẾT
       (`nsTen`) tự lấy từ trang Nhân sự mỗi lần mở kỳ. */
    st.options = Object.assign({ includePending: false, fabiTuDong: false, luongTuDong: false }, st.options || {});
    /* `bpMacDinh` — bộ phận cho những cơ sở KHÔNG đoán được bộ phận từ tên, chọn một lần cho mỗi
       nguồn rồi nhớ. Tên bên Ghế là tên địa điểm ("VINCOM BIÊN HÒA") nên không có chữ hiệu nào để
       đoán; đoán bừa thì doanh thu vào nhầm bộ phận mà tổng vẫn cộng đẹp. */
    st.options.bpMacDinh = Object.assign({}, st.options.bpMacDinh || {});
    /* `boQuaNguon` — danh sách cơ sở BỎ HẲN của từng nguồn, khoá theo tên cột liên kết
       ('fabiTen' | 'gheTen'). Xem khối dài ở dsBoQua(). */
    st.options.boQuaNguon = Object.assign({}, st.options.boQuaNguon || {});
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
    /* `nsTen` = tên cơ sở bên plugin Chấm công mà dòng lương này lấy số về. Cùng lối liên kết
       sống như `fabiTen`/`gheTen` ở điểm bán, nhưng nằm trên DÒNG LƯƠNG vì anh Thắng chọn
       "tổng mỗi cơ sở một dòng" chứ không kéo từng nhân viên. */
    st.salarySites = (st.salarySites || []).map((r) => ({ reported: 0, report: 0, dntt: 0, actual: 0, unitCode: '', misaGeneral: '', misaDetail: '', nsTen: '', ...r, id: r.id || newId('ss') }));
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
    ghepFabi,
    napFabi,
    ghepLuong,
    napLuong,
    dongBoLuong,
    taoDiemTuFabi,
    doanBoPhan,
    dongBoFabi,
    batDauKyMoi,
    khoaKy,
    lechKy,
    dsBoQua,
    khoaTen,
    tenGonFabi,
    parseAccount,
    periodLastDay,
    validate,
    emptyState,
    normalizeState,
  };
});
