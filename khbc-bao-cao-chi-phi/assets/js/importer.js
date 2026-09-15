/*
 * importer.js — Đọc file Excel "File chi phí MN Txx/yyyy" (định dạng hiện tại của K&H)
 * và dựng thành state cho engine. Dùng SheetJS (window.XLSX).
 *
 * Nhận diện sheet theo TÊN (không phân biệt hoa/thường, dấu):
 *   - "Doanh thu Posh", "Doanh thu JP", "Doanh Thu KVC"  → điểm bán + doanh thu
 *   - "total general"                                    → danh mục khoản chi phí (cột G..N)
 *   - "Bảng tổng lương NV cơ sở"                          → lương NV cơ sở theo điểm, lương VP theo bộ phận
 *   - "File tổng báo cáo" (nếu có)                        → cột nhập tay, mã đơn vị, nội dung MISA
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory(require('./engine.js'));
  else root.BaoCaoImporter = factory(root.BaoCaoEngine);
})(typeof self !== 'undefined' ? self : this, function (E) {
  'use strict';

  const num = E.num;

  function norm(s) {
    return String(s === null || s === undefined ? '' : s)
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .replace(/Đ/g, 'D')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }
  const str = (v) => (v === null || v === undefined ? '' : String(v).trim());
  const isNum = (v) => typeof v === 'number' && isFinite(v);

  function sheetRows(XLSX, wb, name) {
    const ws = wb.Sheets[name];
    if (!ws) return [];
    return XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: null, blankrows: true });
  }

  function findSheet(wb, test) {
    return wb.SheetNames.find((n) => test(norm(n)));
  }

  /** Từ khoá nhận diện bộ phận trong tên sheet / tiêu đề / tên nhóm. */
  const DEPT_KEYWORDS = [
    ['pinball', 'pinball'],
    ['funzone', 'funzone'],
    ['fz ', 'funzone'],
    ['event', 'event'],
    ['farm', 'farm'],
    ['tutu', 'tutu'],
    ['tau', 'tutu'],
    ['train', 'tutu'],
    ['posh', 'posh'],
    ['jp', 'jp'],
  ];
  function deptFromText(text, depts) {
    const t = ' ' + norm(text) + ' ';
    // ưu tiên tên bộ phận đang có trong state
    for (const d of depts || []) {
      if (d.name && t.includes(' ' + norm(d.name) + ' ')) return d.id;
    }
    for (const [kw, id] of DEPT_KEYWORDS) {
      if (t.includes(' ' + kw.trim() + ' ') || t.includes(kw)) return id;
    }
    return null;
  }

  // ---------------------------------------------------------------- Doanh thu
  /**
   * Đọc sheet DAILY REPORT: nhiều khối, mỗi khối có dòng tiêu đề bộ phận (FARM / TUTU TRAIN / …),
   * dòng header "Unit ID | Unit's name | 01..31 | Other income | Total", rồi các dòng điểm.
   */
  function parseDailyReport(rows, fixedDept, depts, log, sheetName) {
    const sites = [];
    let dept = fixedDept || null;
    let totalCol = -1;
    let inHeader = false;
    for (let r = 0; r < rows.length; r++) {
      const row = rows[r] || [];
      const a = row[0];
      const b = row[1];
      const na = norm(a);
      if (!na && !str(b)) continue;
      if (na === 'unit id' || na === 'unit id ') {
        inHeader = true;
        totalCol = -1;
        for (let c = row.length - 1; c >= 2; c--) {
          if (norm(row[c]) === 'total') {
            totalCol = c;
            break;
          }
        }
        if (totalCol < 0) {
          for (let c = row.length - 1; c >= 2; c--) if (row[c] !== null && row[c] !== '') { totalCol = c; break; }
        }
        continue;
      }
      if (na.startsWith('daily report')) continue;
      // dòng tiêu đề khối (FARM, TUTU TRAIN, FUNZONE, POSH, JP…): cột B trống, cột C = 'VND' hoặc trống
      if (str(a) && !str(b) && !isNum(a)) {
        const d = deptFromText(a, depts);
        if (d && !fixedDept && (norm(row[2]) === 'vnd' || !inHeader || r + 2 < rows.length && norm((rows[r + 1] || [])[0]) === 'unit id')) {
          dept = d;
          inHeader = false;
          continue;
        }
        if (na === 'tong' || na === 'total') continue;
        continue; // dòng vùng (HO CHI MINH, BINH DUONG…)
      }
      if (str(a) && str(b) && !isNum(a) && totalCol >= 0) {
        if (na === 'tong' || na === 'total') continue;
        if (!dept) {
          log.push({ level: 'warn', msg: `${sheetName}: điểm "${b}" không xác định được bộ phận, bỏ qua.` });
          continue;
        }
        let rev = row[totalCol];
        if (!isNum(rev)) {
          // Total không có giá trị cache → tự cộng các cột ngày
          rev = 0;
          for (let c = 2; c < totalCol; c++) if (isNum(row[c])) rev += row[c];
        }
        sites.push({ dept, code: str(a), name: str(b).replace(/\s+/g, ' '), revenue: num(rev) });
      }
    }
    return sites;
  }

  // ---------------------------------------------------------------- Chi phí (total general)
  function parseTotalGeneral(rows, groups, log) {
    const items = [];
    // tìm dòng header có 'Tổng tiền' (cột K) ở vùng G..N
    let hdr = -1, colName = 6, colTotal = 10;
    for (let r = 0; r < Math.min(rows.length, 40); r++) {
      const row = rows[r] || [];
      const c = row.findIndex((v) => norm(v) === 'tong tien');
      if (c >= 0 && norm(row[c - 4]).includes('chi phi')) {
        hdr = r;
        colTotal = c;
        colName = c - 4;
        break;
      }
    }
    if (hdr < 0) {
      log.push({ level: 'warn', msg: 'total general: không tìm thấy header "Tổng tiền" ở vùng G..N, dùng vị trí mặc định G/H/I/J/K/L/M/N.' });
      hdr = 0;
    }
    const header = rows[hdr] || [];
    const colGeneral = colName + 1, colDetail = colName + 2, colAccount = colName + 3;
    // các cột nhóm nằm ngay sau 'Tổng tiền' cho tới 'Mã đối tượng'
    const groupCols = [];
    let colObject = -1;
    for (let c = colTotal + 1; c < Math.max(header.length, colTotal + 4); c++) {
      const h = norm(header[c]);
      if (h.includes('ma doi tuong')) { colObject = c; break; }
      if (!h && c > colTotal + 1) continue;
      groupCols.push({ col: c, header: h });
    }
    if (colObject < 0) colObject = colTotal + groupCols.length + 1;
    // ánh xạ cột nhóm → group id
    const mapped = groupCols.map((gc, i) => {
      let g = (groups || []).find((x) => {
        const n = norm(x.name), id = norm(x.id);
        return gc.header && (gc.header === id || n.includes(gc.header) || gc.header.includes(id) || (gc.header.includes('may tu dong') && n.includes('may tu dong')) || (gc.header.includes('khu vui choi') && n.includes('khu vui choi')));
      });
      if (!g && groups && groups[i]) g = groups[i];
      return { col: gc.col, groupId: g ? g.id : null };
    });

    let kind = 'personal';
    for (let r = hdr; r < rows.length; r++) {
      const row = rows[r] || [];
      const name = str(row[colName]);
      const total = row[colTotal];
      const nn = norm(name);
      if (nn.includes('chi phi phat sinh cong ty') || nn.includes('phat sinh cty')) { kind = 'company'; continue; }
      if (nn.includes('chi phi phat sinh ca nhan')) { kind = 'personal'; continue; }
      if (!name || !isNum(total) || total === 0) continue;
      const shares = {};
      let sumShares = 0;
      mapped.forEach((m) => {
        if (!m.groupId) return;
        shares[m.groupId] = num(row[m.col]);
        sumShares += shares[m.groupId];
      });
      let split = 'custom';
      const gids = Object.keys(shares);
      if (gids.length && Math.abs(sumShares - total) < 1) {
        const nonZero = gids.filter((g) => Math.abs(shares[g]) > 0.5);
        if (nonZero.length === 1 && Math.abs(shares[nonZero[0]] - total) < 1) split = nonZero[0];
        else if (nonZero.length === gids.length && gids.every((g) => Math.abs(shares[g] - total / gids.length) < 1)) split = 'equal';
      } else if (!gids.length || sumShares === 0) {
        split = 'equal';
        log.push({ level: 'info', msg: `total general: khoản "${name}" không có số chia nhóm → tạm chia đều.` });
      } else {
        log.push({ level: 'warn', msg: `total general: khoản "${name}" tổng chia nhóm (${E.fmt(sumShares)}) ≠ tổng tiền (${E.fmt(total)}).` });
      }
      items.push({
        id: E.newId('ci'),
        kind,
        name,
        misaGeneral: str(row[colGeneral]),
        misaDetail: str(row[colDetail]),
        account: str(row[colAccount]),
        total: num(total),
        split,
        shares,
        objectCode: str(row[colObject]),
        excludeDepts: [],
        groupKey: '',
        method: '',
        note: str(row[22]),
      });
    }
    return items;
  }

  /**
   * Đọc các khối phân bổ trong total general (tiêu đề khoản ở cột B/C, các dòng bộ phận ở cột A,
   * kết thúc bằng "Tổng cộng") để suy ra bộ phận bị LOẠI TRỪ khỏi phép chia của một khoản.
   * VD: khối "Chi phí thuê nhà NV" chỉ liệt kê Funzone/Event/Farm/Tutu → Pinball bị loại.
   */
  function applyAllocationBlocks(rows, items, depts, groups, log) {
    const deptByName = (t) => {
      const n = norm(t);
      const d = (depts || []).find((x) => norm(x.name) === n);
      return d ? d.id : null;
    };
    for (let r = 0; r < rows.length; r++) {
      const row = rows[r] || [];
      const title = [row[1], row[2]].map(str).filter(Boolean).join(' ');
      if (!title || isNum(row[1]) || norm(row[0]) === 'bo phan') continue;
      // thu các dòng bộ phận ngay sau tiêu đề (bỏ qua dòng header "Bộ Phận")
      const present = [];
      let k = r + 1;
      if (norm((rows[k] || [])[0]) === 'bo phan') k++;
      for (; k < rows.length && k < r + 12; k++) {
        const a = (rows[k] || [])[0];
        const d = deptByName(a);
        if (d) present.push(d);
        else break;
      }
      if (present.length < 1) continue;
      const nt = norm(title);
      const matched = items.filter((it) => {
        const a = norm(it.misaDetail), b = norm(it.name);
        return (a && nt.includes(a)) || (b && nt.includes(b));
      });
      if (!matched.length) continue;
      // nhóm của khối = nhóm của các bộ phận có mặt
      const gids = [...new Set(present.map((id) => (depts.find((d) => d.id === id) || {}).group))].filter(Boolean);
      gids.forEach((gid) => {
        const groupDepts = depts.filter((d) => d.group === gid).map((d) => d.id);
        const missing = groupDepts.filter((id) => !present.includes(id));
        if (!missing.length || missing.length === groupDepts.length) return;
        matched.forEach((it) => {
          if ((it.shares[gid] || 0) === 0 && it.split !== 'equal' && it.split !== gid) return;
          missing.forEach((id) => { if (!it.excludeDepts.includes(id)) it.excludeDepts.push(id); });
          log.push({ level: 'info', msg: `total general: khoản "${it.name}" — khối phân bổ không có ${missing.map((id) => (depts.find((d) => d.id === id) || {}).name).join(', ')} → loại trừ khi chia.` });
        });
      });
    }
  }

  // ---------------------------------------------------------------- Lương (Bảng tổng lương NV cơ sở)
  function parseSalarySummary(rows, depts, log) {
    const out = { sites: [], luongVP: {} };
    let hdr = -1;
    for (let r = 0; r < Math.min(rows.length, 15); r++) {
      const row = rows[r] || [];
      if (norm(row[1]) === 'ten co so' && norm(row[2]).startsWith('so tien')) { hdr = r; break; }
    }
    if (hdr < 0) {
      log.push({ level: 'warn', msg: 'Bảng tổng lương NV cơ sở: không tìm thấy header "Tên cơ sở / Số tiền".' });
      return out;
    }
    const deptRow = rows[hdr + 1] || [];
    const deptCols = [];
    for (let c = 3; c < 12; c++) {
      const t = str(deptRow[c]);
      if (!t) continue;
      const d = deptFromText(t, depts);
      if (d) deptCols.push({ col: c, dept: d, label: t });
    }
    if (!deptCols.length) log.push({ level: 'warn', msg: 'Bảng tổng lương NV cơ sở: không nhận diện được cột bộ phận (Event/Tàu/Funzone/…).' });
    let blank = 0;
    for (let r = hdr + 2; r < rows.length; r++) {
      const row = rows[r] || [];
      const name = str(row[1]);
      const actual = row[2];
      if (!name) { if (++blank > 3) break; continue; }
      blank = 0;
      const nn = norm(name);
      if (nn.startsWith('luong vp')) {
        deptCols.forEach((dc) => { if (isNum(row[dc.col])) out.luongVP[dc.dept] = row[dc.col]; });
        break; // hết phần I
      }
      if (nn.startsWith('luong co so')) continue;
      const hit = deptCols.find((dc) => isNum(row[dc.col]));
      if (!hit) continue;
      const deptId = hit.dept;
      // nhóm hiển thị trên báo cáo
      const reported = num(row[hit.col]);
      out.sites.push({
        id: E.newId('ss'),
        dept: deptId,
        groupTitle: defaultGroupTitle(deptId, depts),
        stt: out.sites.filter((s) => s.dept === deptId).length + 1,
        name,
        reported,
        report: reported,
        dntt: reported,
        actual: isNum(actual) ? actual : reported,
        unitCode: '',
        misaGeneral: '',
        misaDetail: '',
      });
    }
    return out;
  }

  function defaultGroupTitle(deptId, depts) {
    if (deptId === 'posh' || deptId === 'jp') return 'POSH - JP';
    if (deptId === 'tutu') return 'Tàu HCM';
    const d = (depts || []).find((x) => x.id === deptId);
    return `${d ? d.name : deptId} HCM`;
  }

  // ---------------------------------------------------------------- File tổng báo cáo (sheet cũ)
  function parseReportSheet(rows, depts, log) {
    const out = { manualCols: [], salaryDept: [], salarySites: [], sitesFound: false };
    const deptByName = (t) => {
      const n = norm(t);
      const d = (depts || []).find((x) => norm(x.name) === n);
      return d ? d.id : deptFromText(t, depts);
    };
    let r = 0;
    // ---- Mục I: header 'Bộ Phận' + các cột 'Phân bổ…'
    for (; r < rows.length; r++) {
      const row = rows[r] || [];
      if (norm(row[0]) === 'bo phan' && row.some((v, i) => i > 0 && norm(v).startsWith('phan bo'))) {
        const mcols = [];
        row.forEach((v, i) => { if (i > 0 && norm(v).startsWith('phan bo')) mcols.push({ col: i, name: str(v).replace(/\s+/g, ' ') }); });
        const values = mcols.map(() => ({}));
        for (let k = r + 1; k < rows.length; k++) {
          const rr = rows[k] || [];
          const d = str(rr[0]) ? deptByName(rr[0]) : null;
          if (!d) break;
          mcols.forEach((mc, i) => { if (isNum(rr[mc.col])) values[i][d] = rr[mc.col]; });
        }
        mcols.forEach((mc, i) => out.manualCols.push({ id: E.newId('mc'), name: mc.name, account: '', values: values[i] }));
        r += 1;
        break;
      }
    }
    // ---- Mục II: header có 'Lương văn phòng'
    for (; r < rows.length; r++) {
      const row = rows[r] || [];
      const hit = row.findIndex((v) => norm(v).startsWith('luong van phong'));
      if (norm(row[0]) === 'bo phan' && hit > 0) {
        const map = [];
        row.forEach((v, i) => {
          const n = norm(v);
          if (!n || i === 0) return;
          if (n.startsWith('luong van phong')) map.push({ col: i, key: 'luongVP' });
          else if (n.startsWith('luong van hanh')) map.push({ col: i, key: 'luongVanHanh' });
          else if (n.includes('ql ac')) map.push({ col: i, key: 'luongQLAC' });
          else if (n.includes('alex')) map.push({ col: i, key: 'luongAlex' });
          else if (n.includes('kpi')) map.push({ col: i, key: 'luongKPI' });
          else if (n.includes('bonus')) map.push({ col: i, key: 'bonus' });
          else if (n.includes('dien giai chung')) map.push({ col: i, key: 'misaGeneral' });
          else if (n.includes('dien giai chi tiet')) map.push({ col: i, key: 'misaDetail' });
        });
        for (let k = r + 1; k < rows.length; k++) {
          const rr = rows[k] || [];
          const d = str(rr[0]) ? deptByName(rr[0]) : null;
          if (!d) break;
          const rec = { dept: d, misaGeneral: '', misaDetail: '' };
          map.forEach((m) => {
            if (m.key === 'misaGeneral' || m.key === 'misaDetail') rec[m.key] = str(rr[m.col]);
            else if (isNum(rr[m.col])) rec[m.key] = (rec[m.key] || 0) + rr[m.col];
          });
          out.salaryDept.push(rec);
        }
        r += 1;
        break;
      }
    }
    // ---- Mục III: các khối 'STT | <tên nhóm> | Lương NV… | | Báo cáo | DNTT | Thực lĩnh | Mã đơn vị | …'
    let cur = null;
    for (; r < rows.length; r++) {
      const row = rows[r] || [];
      if (norm(row[0]) === 'stt' && str(row[1])) {
        const title = str(row[1]).replace(/\s+/g, ' ');
        const cols = { reported: 2, report: 4, dntt: 5, actual: 6, unitCode: 7, misaGeneral: 8, misaDetail: 9 };
        row.forEach((v, i) => {
          const n = norm(v);
          if (i < 2 || !n) return;
          if (n.startsWith('luong nv')) cols.reported = i;
          else if (n === 'bao cao') cols.report = i;
          else if (n.startsWith('dntt')) cols.dntt = i;
          else if (n.startsWith('thuc linh')) cols.actual = i;
          else if (n.startsWith('ma don vi')) cols.unitCode = i;
          else if (n.includes('dien giai chung')) cols.misaGeneral = i;
          else if (n.includes('dien giai chi tiet')) cols.misaDetail = i;
        });
        const groupDept = deptFromText(title, depts);
        cur = { title, cols, groupDept };
        out.sitesFound = true;
        continue;
      }
      if (cur && isNum(row[0]) && str(row[1])) {
        const name = str(row[1]).replace(/\s+/g, ' ');
        let dept = deptFromText(name, depts);
        if (!dept || (cur.groupDept && cur.groupDept !== 'posh' && cur.groupDept !== 'jp')) dept = cur.groupDept || dept;
        if (!dept) dept = deptFromText(str(row[cur.cols.unitCode]), depts);
        if (!dept) {
          log.push({ level: 'warn', msg: `File tổng báo cáo: dòng lương "${name}" không xác định được bộ phận.` });
          continue;
        }
        out.salarySites.push({
          id: E.newId('ss'),
          dept,
          groupTitle: cur.title,
          stt: row[0],
          name,
          reported: num(row[cur.cols.reported]),
          report: num(row[cur.cols.report]),
          dntt: num(row[cur.cols.dntt]),
          actual: num(row[cur.cols.actual]),
          unitCode: str(row[cur.cols.unitCode]),
          misaGeneral: str(row[cur.cols.misaGeneral]),
          misaDetail: str(row[cur.cols.misaDetail]),
        });
        continue;
      }
      if (cur && !str(row[0]) && !str(row[1]) && !isNum(row[2])) cur = cur; // dòng trống giữa các khối: giữ khối
    }
    return out;
  }

  // ---------------------------------------------------------------- Kỳ báo cáo
  function detectPeriod(wb, rowsTotalGeneral) {
    for (const n of wb.SheetNames) {
      const m = n.match(/T(\d{1,2})\.(\d{4})/i);
      if (m) return { month: +m[1], year: +m[2] };
    }
    for (const row of rowsTotalGeneral.slice(0, 15)) {
      for (const v of row || []) {
        const m = String(v || '').match(/(\d{1,2})\/(\d{4})\)?$/);
        if (m && norm(v).includes('doanh thu')) return { month: +m[1], year: +m[2] };
      }
    }
    return null;
  }

  // ---------------------------------------------------------------- Tổng hợp
  /**
   * @param XLSX     thư viện SheetJS
   * @param data     ArrayBuffer file .xlsx
   * @param base     state hiện tại (để giữ cấu hình nhóm / bộ phận / cột nhập tay)
   * @returns {state, log}
   */
  function importWorkbook(XLSX, data, base) {
    const wb = XLSX.read(data, { type: 'array', cellDates: false });
    const log = [];
    const st = E.normalizeState(JSON.parse(JSON.stringify(base || E.emptyState())));
    const depts = st.departments;

    const nPosh = findSheet(wb, (n) => n.includes('doanh thu posh'));
    const nJP = findSheet(wb, (n) => n.includes('doanh thu jp'));
    const nKVC = findSheet(wb, (n) => n.includes('doanh thu kvc'));
    const nTG = findSheet(wb, (n) => n.includes('total general'));
    const nSal = findSheet(wb, (n) => n.includes('bang tong luong'));
    const nRep = findSheet(wb, (n) => n.includes('file tong bao cao'));

    const tgRows = nTG ? sheetRows(XLSX, wb, nTG) : [];
    const period = detectPeriod(wb, tgRows);
    if (period) st.period = period;
    else log.push({ level: 'info', msg: 'Không tự nhận diện được kỳ báo cáo, giữ kỳ hiện tại.' });

    // ---- điểm & doanh thu
    let sites = [];
    if (nPosh) sites = sites.concat(parseDailyReport(sheetRows(XLSX, wb, nPosh), 'posh', depts, log, nPosh));
    if (nJP) sites = sites.concat(parseDailyReport(sheetRows(XLSX, wb, nJP), 'jp', depts, log, nJP));
    if (nKVC) sites = sites.concat(parseDailyReport(sheetRows(XLSX, wb, nKVC), null, depts, log, nKVC));
    if (sites.length) {
      st.sites = sites;
      depts.forEach((d) => { d.revenueOverride = false; d.revenue = sites.filter((s) => s.dept === d.id).reduce((a, s) => a + s.revenue, 0); });
      log.push({ level: 'ok', msg: `Đọc ${sites.length} điểm bán từ ${[nPosh, nJP, nKVC].filter(Boolean).join(', ')}.` });
    } else {
      log.push({ level: 'warn', msg: 'Không tìm thấy sheet doanh thu (Doanh thu Posh / JP / KVC). Giữ doanh thu hiện tại.' });
    }
    // bộ phận không có điểm nào nhưng total general có doanh thu → nhập tay
    if (tgRows.length) {
      for (let r = 0; r < Math.min(tgRows.length, 40); r++) {
        const row = tgRows[r] || [];
        const d = str(row[0]) && norm(row[1]) !== 'doanh thu (1-31/08/2026)' ? depts.find((x) => norm(x.name) === norm(row[0])) : null;
        if (d && isNum(row[1]) && !sites.some((s) => s.dept === d.id)) { d.revenue = row[1]; d.revenueOverride = true; }
      }
    }

    // ---- khoản chi phí
    if (nTG) {
      const items = parseTotalGeneral(tgRows, st.groups, log);
      if (items.length) {
        // kế thừa cấu hình (loại trừ / gộp cột / phương pháp) từ khoản cùng tên của kỳ trước
        items.forEach((it) => {
          const old = st.costItems.find((o) => norm(o.name) === norm(it.name) && (norm(o.misaDetail) === norm(it.misaDetail) || !o.misaDetail));
          if (old) {
            it.excludeDepts = [...(old.excludeDepts || [])];
            it.groupKey = old.groupKey || '';
            it.method = old.method || '';
          }
        });
        applyAllocationBlocks(tgRows, items, depts, st.groups, log);
        st.costItems = items;
        log.push({ level: 'ok', msg: `Đọc ${items.length} khoản chi phí từ "${nTG}".` });
      } else log.push({ level: 'warn', msg: `"${nTG}": không đọc được khoản chi phí nào.` });
    } else log.push({ level: 'warn', msg: 'Không tìm thấy sheet "total general" → giữ danh mục chi phí hiện tại.' });

    // ---- báo cáo cũ (cột nhập tay, MISA, mã đơn vị)
    let rep = null;
    if (nRep) {
      rep = parseReportSheet(sheetRows(XLSX, wb, nRep), depts, log);
      if (rep.manualCols.length) {
        // giữ id cột nhập tay cũ nếu trùng tên
        st.manualCols = rep.manualCols.map((mc) => {
          const old = st.manualCols.find((o) => norm(o.name) === norm(mc.name));
          return old ? { ...old, values: mc.values } : mc;
        });
      }
      log.push({ level: 'ok', msg: `Đọc sheet "${nRep}": ${rep.manualCols.length} cột nhập tay, ${rep.salaryDept.length} dòng lương bộ phận, ${rep.salarySites.length} dòng lương cơ sở.` });
    }

    // ---- lương
    let sal = null;
    if (nSal) {
      sal = parseSalarySummary(sheetRows(XLSX, wb, nSal), depts, log);
      log.push({ level: 'ok', msg: `Đọc "${nSal}": ${sal.sites.length} dòng lương cơ sở, lương VP của ${Object.keys(sal.luongVP).length} bộ phận.` });
    }
    if (sal && sal.sites.length) {
      // làm giàu bằng mã đơn vị / MISA từ báo cáo cũ: khớp theo (theo báo cáo, thực lĩnh) rồi theo tên
      if (rep && rep.salarySites.length) {
        const used = new Set();
        sal.sites.forEach((s) => {
          let m = rep.salarySites.find((x, i) => !used.has(i) && x.dept === s.dept && Math.abs(x.reported - s.reported) < 1 && Math.abs(x.actual - s.actual) < 1);
          if (!m) m = rep.salarySites.find((x, i) => !used.has(i) && x.dept === s.dept && Math.abs(x.reported - s.reported) < 1);
          if (!m) m = rep.salarySites.find((x, i) => !used.has(i) && norm(x.name) === norm(s.name));
          if (m) {
            used.add(rep.salarySites.indexOf(m));
            s.name = m.name || s.name;
            s.unitCode = m.unitCode;
            s.misaGeneral = m.misaGeneral;
            s.misaDetail = m.misaDetail;
            s.groupTitle = m.groupTitle;
            s.stt = m.stt;
            s.report = m.report || s.report;
            s.dntt = m.dntt || s.dntt;
          }
        });
        // sắp theo thứ tự xuất hiện trên báo cáo cũ
        const titleOrder = [...new Set(rep.salarySites.map((x) => x.groupTitle))];
        sal.sites.sort((a, b) => {
          const ta = titleOrder.indexOf(a.groupTitle), tb = titleOrder.indexOf(b.groupTitle);
          if (ta !== tb) return (ta < 0 ? 99 : ta) - (tb < 0 ? 99 : tb);
          return 0;
        });
      }
      st.salarySites = sal.sites;
    } else if (rep && rep.salarySites.length) {
      st.salarySites = rep.salarySites;
      log.push({ level: 'info', msg: 'Lương cơ sở lấy từ sheet "File tổng báo cáo" cũ (không có Bảng tổng lương).' });
    }
    // lương theo bộ phận
    const salaryDept = depts.map((d) => {
      const old = st.salaryDept.find((x) => x.dept === d.id) || {};
      const fromRep = rep ? rep.salaryDept.find((x) => x.dept === d.id) || {} : {};
      const rec = { dept: d.id, luongVP: 0, luongVanHanh: 0, luongQLAC: 0, luongAlex: 0, luongKPI: 0, bonus: 0, ...old, ...fromRep };
      if (sal && isNum(sal.luongVP[d.id])) rec.luongVP = sal.luongVP[d.id];
      if (!rec.misaGeneral) rec.misaGeneral = old.misaGeneral || '';
      if (!rec.misaDetail) rec.misaDetail = old.misaDetail || '';
      return rec;
    });
    st.salaryDept = salaryDept;

    return { state: E.normalizeState(st), log, sheetNames: wb.SheetNames };
  }

  return { importWorkbook, parseDailyReport, parseTotalGeneral, parseSalarySummary, parseReportSheet, applyAllocationBlocks, norm, sheetRows };
});
