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

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * SHEET "<Bộ phận> chi tiết" — TỜ NHẬP MISA
   *
   * Anh Thắng 16/09/2026: *"tab chi tiết phân bổ ra để xuất MISA chưa có"*.
   *
   * 🔴 ĐÂY LÀ MẪU CỦA MISA, KHÔNG PHẢI BẢNG CỦA MÌNH. 50 cột, đúng thứ tự, đúng chữ — chép
   *    nguyên từ file thật T8/2026 của anh Thắng. Ta chỉ điền 10 cột; 40 cột còn lại để TRỐNG
   *    nhưng VẪN PHẢI CÓ ĐỦ TIÊU ĐỀ: MISA đọc tờ nhập theo VỊ TRÍ CỘT, bỏ bớt một cột trống ở
   *    giữa là mọi cột sau nó lệch đi một ô — mà lệch kiểu ấy không báo lỗi, nó nhập vào sai chỗ.
   *    Nên đừng "dọn cho gọn" danh sách này.
   *
   * Dòng 1 là nhãn gộp của MISA ("Chi tiết hạch toán" trên cột 9, "Hóa đơn" trên cột 32); dòng 2
   * mới là tiêu đề thật. Giữ cả hai cho giống hệt tờ mẫu.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  const MISA_COLS = ['Ngày chứng từ (*)', 'Ngày hạch toán (*)', 'Số chứng từ (*)', 'Diễn giải ',
    'Loại nghiệp vụ', 'Hạn thanh toán', 'Loại tiền', 'Tỷ giá', 'Diễn giải (Hạch toán)', 'TK Nợ (*)',
    'TK Có (*)', 'Số tiền', 'Số tiền quy đổi', 'Mã đối tượng Nợ', 'Mã đối tượng Có', 'Nghiệp vụ',
    'Mã nhân viên', 'Số TK ngân hàng', 'Tên ngân hàng', 'Số khế ước đi vay', 'Số khế ước cho vay',
    'Mã khoản mục chi phí', 'Mã đơn vị', 'Mã đối tượng THCP', 'Mã công trình', 'Số đơn đặt hàng',
    'Số đơn mua hàng', 'Số hợp đồng mua', 'Số hợp đồng bán', 'Mã thống kê', 'CP không hợp lý',
    'Hạch toán gộp nhiều hóa đơn', 'Diễn giải thuế', 'Có hóa đơn', 'Loại thuế',
    'Giá trị HHDV chưa thuế', 'Giá trị HHDV chưa thuế quy đổi', '% thuế GTGT', '% thuế suất KHAC',
    'Tiền thuế GTGT', 'Tiền thuế GTGT quy đổi', 'TK thuế GTGT', 'Ngày hóa đơn', 'Số hóa đơn',
    'Mẫu số HĐ', 'Ký hiệu HĐ', 'Nhóm HHDV mua vào', 'Mã đối tượng thuế', 'Tên đối tượng thuế',
    'Mã số thuế đối tượng thuế'];
  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * NHỮNG CỘT TA THẬT SỰ ĐIỀN — MỘT ĐỊNH NGHĨA DUY NHẤT, DÙNG CHUNG CHO CẢ FILE LẪN MÀN HÌNH.
   *
   * 🔴 Anh Thắng 16/09/2026: *"khác nhau và thiếu cột"* — tab "Chi tiết MISA" trên màn hình bày
   *    ra một bộ cột khác hẳn file xuất ra (thiếu Ngày chứng từ, Ngày hạch toán, Số tiền quy đổi,
   *    và xếp sai thứ tự). Gốc: cột được kê ở HAI NƠI — bảng này cho file, một danh sách viết tay
   *    khác trong app.js cho màn hình. Hai danh sách thì sớm muộn cũng lệch, mà lệch kiểu ấy phá
   *    đúng lời hứa của cái tab: "nhìn trước khi xuất".
   *
   * Nay CHỈ CÓ bảng này. app.js đọc `EXP.MISA_COLS` + `EXP.MISA_MAP` để dựng bảng trên màn hình,
   * nên thêm/bớt/đổi thứ tự một cột là cả hai bên đổi theo cùng lúc — không còn chỗ để lệch.
   *
   * `i` = chỉ số cột (0-based) trong mẫu 50 cột · `key` = tên trường trong dòng của misaRows()
   * `num` = ô số (Excel phải để kiểu số cho MISA cộng được; màn hình canh phải + ngăn hàng nghìn)
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  /* ⚠️ Bảng này XẾP THEO `i` TĂNG DẦN, và phải giữ vậy: màn hình vẽ cột theo đúng thứ tự trong
     bảng, nên chèn một dòng sai chỗ là cột trên màn hình lệch khỏi thứ tự của file. */
  const MISA_MAP = [
    { i: 0, key: 'ngay' },        // Ngày chứng từ (*)
    { i: 1, key: 'ngay' },        // Ngày hạch toán (*) — file thật để "=A3", tức luôn bằng cột trước
    { i: 2, key: 'soCT' },        // Số chứng từ (*)
    { i: 3, key: 'dienGiai' },    // Diễn giải
    { i: 8, key: 'dienGiaiHT' },  // Diễn giải (Hạch toán)
    { i: 9, key: 'tkNo' },        // TK Nợ (*)
    { i: 10, key: 'tkCo' },       // TK Có (*)
    { i: 11, key: 'soTien', num: true },   // Số tiền
    { i: 12, key: 'soTien', num: true },   // Số tiền quy đổi — file thật để "=L3"
    { i: 14, key: 'maDoiTuongCo' }, // Mã đối tượng Có — mã nhà cung cấp, 1.497 ô có giá trị ở file thật
    { i: 22, key: 'maDonVi' },    // Mã đơn vị
  ];


  function buildMisaSheet(XLSX, state, report, deptId) {
    const kq = E.misaRows(state, report, deptId);
    if (!kq) return null;
    const nhan = [];
    nhan[8] = 'Chi tiết hạch toán';
    nhan[31] = 'Hóa đơn';
    const aoa = [nhan, MISA_COLS.slice()];
    kq.rows.forEach((r) => {
      const row = new Array(MISA_COLS.length).fill('');
      /* Tài khoản để nguyên CHUỖI (parseAccount trả chuỗi) — tài khoản có thể bắt đầu bằng số 0
         mà Excel nuốt số 0 đầu ngay khi ô thành kiểu số. aoaToSheet chỉ gán định dạng cho ô KIỂU
         SỐ nên chuỗi đi qua nguyên vẹn. Số tiền thì ngược lại: phải là số thật để MISA cộng được,
         nên `num: true` trong MISA_MAP. */
      MISA_MAP.forEach((m) => { row[m.i] = m.num ? E.num(r[m.key]) : r[m.key]; });
      aoa.push(row);
    });
    const cols = new Array(MISA_COLS.length).fill(16);
    cols[3] = 44; cols[8] = 56; cols[11] = 18; cols[12] = 18;
    /* Hai ô gộp của dòng nhãn — đo từ file thật: "Chi tiết hạch toán" trải I1:AE1, "Hóa đơn"
       trải AF1:AX1. Không gộp thì hai chữ ấy nằm lọt thỏm trong một ô, nhìn không ra là nhãn
       của cả một mảng cột. */
    return aoaToSheet(XLSX, aoa, { cols, merges: ['I1:AE1', 'AF1:AX1'] });
  }

  /** Dựng toàn bộ workbook. options.siteSheets=false để bỏ các sheet phân bổ theo điểm. */
  function buildWorkbook(XLSX, state, report, options) {
    const opt = Object.assign({ siteSheets: true, allocationSheet: true, revenueSheet: true, misaSheets: true }, options || {});
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildReportSheet(XLSX, state, report), 'File tổng báo cáo');
    if (opt.allocationSheet) XLSX.utils.book_append_sheet(wb, buildAllocationSheet(XLSX, state, report), 'Phân bổ theo bộ phận');
    if (opt.siteSheets) {
      const short = E.periodShort(state.period);
      report.departments.forEach((d) => {
        if (!(state.sites || []).some((s) => s.dept === d.id)) return;
        const ws = buildSiteSheet(XLSX, state, report, d.id);
        if (ws) XLSX.utils.book_append_sheet(wb, ws, safeSheetName(`${d.name} ${short}`));
        /* Đặt NGAY SAU sheet phân bổ của cùng bộ phận, giống hệt file gốc ("Posh T8.2026" rồi
           "Posh chi tiết"). Dồn hết tờ nhập MISA xuống cuối file thì người đối chiếu phải nhảy
           qua lại giữa hai đầu workbook cho mỗi bộ phận. */
        if (opt.misaSheets) {
          const wm = buildMisaSheet(XLSX, state, report, d.id);
          if (wm) XLSX.utils.book_append_sheet(wb, wm, safeSheetName(`${d.name} chi tiết`));
        }
      });
    }
    if (opt.revenueSheet) XLSX.utils.book_append_sheet(wb, buildRevenueSheet(XLSX, state, report), 'Doanh thu');
    return wb;
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * WORKBOOK CHỈ GỒM TỜ NHẬP MISA.
   *
   * 🔴 File tổng có 17 sheet, và sheet ĐẦU TIÊN là "File tổng báo cáo" — bố cục hoàn toàn khác.
   *    Đưa nguyên file ấy cho MISA thì nó đọc trúng sheet đầu và báo sai cột, dù mấy sheet
   *    "<Bộ phận> chi tiết" bên trong hoàn toàn đúng. Nên cần một file RIÊNG chỉ có tờ nhập.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  function buildMisaWorkbook(XLSX, state, report, deptId) {
    const wb = XLSX.utils.book_new();
    const ds = deptId ? report.departments.filter((d) => d.id === deptId) : report.departments;
    ds.forEach((d) => {
      const ws = buildMisaSheet(XLSX, state, report, d.id);
      if (ws) XLSX.utils.book_append_sheet(wb, ws, safeSheetName(`${d.name} chi tiết`));
    });
    return wb.SheetNames.length ? wb : null;
  }

  function misaFileName(state, dept) {
    const p = state.period || {};
    const ky = `T${String(p.month || 1).padStart(2, '0')}_${p.year || ''}`;
    return `MISA_${dept ? dept.name.replace(/\s+/g, '_') + '_' : ''}${state.region || 'MN'}_${ky}.xlsx`;
  }

  function fileName(state) {
    const p = state.period || {};
    return `File_tong_bao_cao_${state.region || 'MN'}_T${String(p.month || 1).padStart(2, '0')}_${p.year || ''}.xlsx`;
  }

  return { buildWorkbook, buildReportSheet, buildAllocationSheet, buildSiteSheet, buildMisaSheet,
    buildMisaWorkbook, misaFileName, buildRevenueSheet, fileName, MISA_COLS, MISA_MAP, NUM_FMT };
});
