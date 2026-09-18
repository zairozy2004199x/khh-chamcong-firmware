/* app.js — Giao diện web "Báo cáo chi phí K&H". Toàn bộ logic tính nằm ở engine.js. */
(function () {
  'use strict';

  const APP_VERSION = '1.1.0';
  const STORAGE_KEY = (window.KHBC_CFG && window.KHBC_CFG.mode === 'wp') ? 'khbc_state_v1' : 'khh_baocao_chiphi_v1';
  const UI_KEY = (window.KHBC_CFG && window.KHBC_CFG.mode === 'wp') ? 'khbc_ui' : 'khh_baocao_chiphi_ui';
  const E = window.BaoCaoEngine;
  const IMP = window.BaoCaoImporter;
  const EXP = window.BaoCaoExporter;
  const API = window.BaoCaoApi;
  const WP = API.MODE === 'wp';
  const UI = window.KHBC_UI;
  const CFGWP = API.CFG || {};

  const MONTHS = Array.from({ length: 12 }, (_, i) => i + 1);
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
  const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = (n, d) => E.fmt(n, d);
  const pct = (x, d) => (x === null || x === undefined || isNaN(x) ? '' : fmt(x * 100, d === undefined ? 2 : d) + '%');

  // ------------------------------------------------------------------ State
  let state = null;
  let report = null;
  let issues = [];
  let prevState = null; // để hoàn tác sau khi nhập Excel / nạp mẫu
  let ui = Object.assign({ tab: 'dashboard', sitesDept: '', siteFilter: 'all', costFilter: 'all', misaMo: [], misaThieu: false, misa50: false }, loadJSON(UI_KEY) || {});
  /* Kết quả ghép FABi đang chờ người dùng duyệt. CỐ Ý KHÔNG lưu vào localStorage: đây là bản
     nháp của một lượt thao tác, để nó sống qua lần tải trang sau là người ta quay lại thấy một
     bảng số cũ của kỳ nào đó rồi bấm Ghi. */
  let fabi = null;
  /* Lần lấy tự động gần nhất — chỉ để hiện trạng thái, KHÔNG lưu vào state. */
  let fabiLan = null;
  /* Bản nháp ghép LƯƠNG từ plugin Chấm công, và lần đồng bộ gần nhất. Cùng lý do như `fabi`:
     không lưu localStorage. */
  let luong = null;
  let luongLan = null;
  let saveTimer = null;

  function loadJSON(key) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }
  function saveState() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        localStorage.setItem(UI_KEY, JSON.stringify(ui));
        $('#saveState').textContent = `Đã tự lưu lúc ${new Date().toLocaleTimeString('vi-VN')} (trên trình duyệt này).`;
      } catch (e) {
        $('#saveState').textContent = 'Không lưu được vào trình duyệt (bộ nhớ đầy hoặc bị chặn) — hãy Lưu file cấu hình .json.';
      }
    }, 250);
  }
  function recompute() {
    state = E.normalizeState(state);
    report = E.computeReport(state);
    issues = E.validate(state);
    const errs = issues.filter((i) => i.level === 'error').length;
    const warns = issues.filter((i) => i.level === 'warn').length;
    const badge = $('#issueBadge');
    if (errs || warns) {
      badge.hidden = false;
      badge.textContent = errs ? errs : warns;
      badge.className = 'pill' + (errs ? '' : ' warn');
    } else badge.hidden = true;
    const pending = state.costItems.filter((it) => it.status === 'cho_duyet').length;
    const ctab = $('#tabs button[data-tab="costs"]');
    let pb = $('#pendingBadge');
    if (!pb) { pb = document.createElement('span'); pb.id = 'pendingBadge'; pb.className = 'pill warn'; ctab.appendChild(pb); }
    pb.hidden = !pending;
    pb.textContent = pending;
    pb.title = `${pending} khoản chờ duyệt`;
    $('#periodBadge').textContent = report.periodLabel;
    $('#selMonth').value = state.period.month;
    $('#inpYear').value = state.period.year;
    document.title = `Báo cáo chi phí K&H ${report.periodLabel}`;
  }
  function commit(opts) {
    recompute();
    saveState();
    if (!opts || !opts.silent) renderTab(opts && opts.tab);
    if (!opts || !opts.noPush) sync.schedulePush();
  }

  // đường dẫn dữ liệu "costItems.3.total"
  function getPath(obj, path) {
    return path.split('.').reduce((o, k) => (o === undefined || o === null ? undefined : o[k]), obj);
  }
  function setPath(obj, path, val) {
    const keys = path.split('.');
    let o = obj;
    for (let i = 0; i < keys.length - 1; i++) {
      if (o[keys[i]] === undefined || o[keys[i]] === null) o[keys[i]] = /^\d+$/.test(keys[i + 1]) ? [] : {};
      o = o[keys[i]];
    }
    o[keys[keys.length - 1]] = val;
  }

  // ------------------------------------------------------------------ Tiện ích HTML
  function inp(path, value, type, cls, extra) {
    const t = type || 'text';
    let v = value;
    if (t === 'num') v = value === 0 || value ? fmt(value, Number.isInteger(value) ? 0 : 2) : '';
    if (t === 'pct') v = value === 0 || value ? fmt(value * 100, 2) : '';
    return `<input data-path="${path}" data-type="${t}" class="${cls || ''}${t === 'num' || t === 'pct' ? ' num' : ''}" value="${esc(v)}" ${extra || ''}>`;
  }
  function sel(path, value, options, extra) {
    return `<select data-path="${path}" data-type="select" ${extra || ''}>${options
      .map((o) => `<option value="${esc(o.value)}" ${String(o.value) === String(value) ? 'selected' : ''}>${esc(o.label)}</option>`)
      .join('')}</select>`;
  }
  function chk(path, value, extra) {
    return `<input type="checkbox" data-path="${path}" data-type="bool" ${value ? 'checked' : ''} ${extra || ''}>`;
  }
  function td(v, cls) {
    return `<td class="${cls || ''}">${v}</td>`;
  }
  function tdn(n, cls) {
    const neg = n < 0 ? ' neg' : '';
    return `<td class="num${neg} ${cls || ''}">${fmt(n)}</td>`;
  }
  function actBtns(list) {
    return `<td class="act">${list
      .map((b) => `<button class="icon-btn ${b.cls || ''}" data-act="${b.act}" data-arg="${esc(b.arg)}" title="${esc(b.title)}">${b.icon}</button>`)
      .join('')}</td>`;
  }
  const deptName = (id) => ((state.departments || []).find((d) => d.id === id) || { name: id }).name;
  const groupName = (id) => ((state.groups || []).find((g) => g.id === id) || { name: id }).name;
  const deptOptions = () => state.departments.map((d) => ({ value: d.id, label: d.name }));
  const METHOD_OPTIONS = [
    { value: 'revenue', label: 'Theo doanh thu' },
    { value: 'ratio', label: 'Tỷ lệ cố định' },
    { value: 'equal', label: 'Chia đều' },
  ];

  // ------------------------------------------------------------------ Tab: Tổng quan
  /* 🔴 SỐ CỦA KỲ NÀO. Đổi ô chọn tháng chỉ đổi cái NHÃN — doanh thu, lương và tiền từng khoản vẫn
     là số của kỳ cũ cho tới khi có người nạp lại. Không nói ra thì màn Tổng quan bày một bộ số
     trông hoàn chỉnh mà là số tháng trước đội tên tháng này: %CP/DT ra 254%, tổng vẫn cộng đẹp,
     không ô nào đỏ. Đây là dòng bắt buộc phải đập vào mắt trước mọi con số khác. */
  function lechKyHtml() {
    const cu = E.lechKy(state);
    if (!cu) return '';
    return `<div class="issue error" style="margin:0 0 12px">
      <span class="lv">SAI KỲ</span>
      <span><strong>Số đang xem là của kỳ ${esc(cu)}, không phải ${esc(E.khoaKy(state.period))}.</strong>
      Đổi ô chọn tháng chỉ đổi nhãn — doanh thu, lương, cột nhập tay và tiền từng khoản vẫn nguyên
      của kỳ cũ, nên mọi tỉ lệ %CP/DT trên màn này đang <strong>sai</strong>.<br>
      <button class="btn small primary" data-act="dungKyMoi">Dựng kỳ ${esc(E.khoaKy(state.period))} từ danh mục (đưa mọi số về 0)</button>
      <button class="btn small" data-act="veKyCu">Quay về kỳ ${esc(cu)}</button>
      </span></div>`;
  }

  /* 🔴 TIỀN BÊN NGUỒN CHƯA NỐI VÀO ĐIỂM NÀO. Anh Thắng 18/09/2026: trang Ghế 01→17/09 tổng
     1.216.383.000 mà báo cáo chỉ thấy 546.005.000 — chênh 670 triệu nằm ở những cơ sở chưa ai
     ghép. Bản trước im lặng bỏ qua, nên nhìn Tổng quan thì tưởng tháng này bán kém chứ không ai
     nghĩ là thiếu liên kết. Số dôi ra phải hiện ngay cạnh doanh thu. */
  function soDuNguonHtml() {
    const soDu = (fabiLan && fabiLan.soDu) || [];
    if (!soDu.length) return '';
    const tong = soDu.reduce((a, x) => a + x.tien, 0);
    return `<div class="issue warn" style="margin:0 0 12px">
      <span class="lv">THIẾU LIÊN KẾT</span>
      <span><strong>${fmt(tong)} bên nguồn chưa nối vào điểm bán nào</strong> nên KHÔNG vào báo cáo này.
      ${soDu.map((x) => `${esc(NGUON[x.nguon].ten)}: ${x.ds.length} cơ sở = ${fmt(x.tien)} / tổng ${fmt(x.tongNguon)}`).join(' · ')}.<br>
      <span class="muted">${esc(soDu.map((x) => x.ds.slice(0, 6).map((d) => d.cua_hang).join(', ')
        + (x.ds.length > 6 ? ` … và ${x.ds.length - 6} cơ sở nữa` : '')).join(' · '))}</span><br>
      ${soDu.map((x) => `<button class="btn small primary" data-act="layHetNguon" data-arg="${esc(x.nguon)}">Lấy hết ${x.ds.length} cơ sở của ${esc(NGUON[x.nguon].nhan)}</button>`).join(' ')}
      <button class="btn small" data-act="moDoanhThu">Sang tab Doanh thu để ghép tay</button>
      </span></div>`;
  }

  function renderDashboard(root) {
    const R = report;
    const totalRev = Object.values(R.revenue).reduce((a, b) => a + b, 0);
    const manualTotal = R.manualCols.reduce((a, m) => a + m.total, 0);
    const totalCost = R.grandTotal + manualTotal + R.salaryDeptTotals.total + R.salarySitesTotal.actual;
    root.innerHTML = `
      ${lechKyHtml()}
      ${soDuNguonHtml()}
      <div class="kpis">
        <div class="kpi"><div class="k">Tổng doanh thu ${R.periodLabel}</div><div class="v">${fmt(totalRev)}</div><div class="s">${state.sites.length} điểm · ${state.departments.length} bộ phận</div></div>
        <div class="kpi"><div class="k">Chi phí phân bổ (Mục I)</div><div class="v">${fmt(R.grandTotal)}</div><div class="s">${state.costItems.length} khoản → ${R.columns.length} cột báo cáo</div></div>
        <div class="kpi"><div class="k">Cột nhập tay (CP 154, 1543…)</div><div class="v">${fmt(manualTotal)}</div><div class="s">${R.manualCols.length} cột</div></div>
        <div class="kpi"><div class="k">Lương bộ phận (Mục II)</div><div class="v">${fmt(R.salaryDeptTotals.total)}</div><div class="s">Lương VP ${fmt(R.salaryDeptTotals.luongVP)}</div></div>
        <div class="kpi"><div class="k">Lương NV cơ sở thực lĩnh (Mục III)</div><div class="v">${fmt(R.salarySitesTotal.actual)}</div><div class="s">${state.salarySites.length} dòng · theo báo cáo ${fmt(R.salarySitesTotal.reported)}</div></div>
        <div class="kpi"><div class="k">Tổng chi phí / Doanh thu</div><div class="v">${totalRev > 0 ? pct(totalCost / totalRev) : '—'}</div><div class="s">Tổng chi phí ${fmt(totalCost)}</div></div>
      </div>

      <div class="card">
        <div class="card-head"><h2>Tổng hợp theo bộ phận</h2><span class="hint">Chi phí phân bổ = Mục I; %CP/DT tính trên toàn bộ chi phí (I + cột nhập tay + lương).</span></div>
        <div class="table-wrap">
          <table class="grid-table">
            <thead><tr>
              <th class="sticky-col">Bộ phận</th><th>Nhóm</th><th class="num">Doanh thu</th><th>Tỷ trọng trong nhóm</th>
              <th class="num">Chi phí phân bổ (I)</th><th class="num">Cột nhập tay</th><th class="num">Lương BP (II)</th><th class="num">Lương cơ sở (III)</th>
              <th class="num">Tổng chi phí</th><th class="num">% CP/DT</th>
            </tr></thead>
            <tbody>
              ${state.departments
                .map((d) => {
                  const g = R.groupInfo.find((x) => x.id === d.group) || { weights: {} };
                  const man = R.manualCols.reduce((a, m) => a + (m.vals[d.id] || 0), 0);
                  const s2 = (R.salaryDept.find((r) => r.dept === d.id) || { total: 0 }).total;
                  const s3 = (R.salarySitesByDept[d.id] || { actual: 0 }).actual;
                  const tot = R.rowTotals[d.id] + man + s2 + s3;
                  const w = g.weights[d.id] || 0;
                  return `<tr>
                    <td class="sticky-col"><strong>${esc(d.name)}</strong></td><td class="muted">${esc(groupName(d.group))}</td>
                    ${tdn(R.revenue[d.id])}
                    <td><div style="display:flex;gap:8px;align-items:center"><span class="bar"><i style="width:${Math.min(100, w * 100)}%"></i></span><span class="muted">${pct(w)}</span></div></td>
                    ${tdn(R.rowTotals[d.id])}${tdn(man)}${tdn(s2)}${tdn(s3)}${tdn(tot)}
                    <td class="num">${R.revenue[d.id] > 0 ? pct(tot / R.revenue[d.id]) : '—'}</td>
                  </tr>`;
                })
                .join('')}
              <tr class="total"><td class="sticky-col">Tổng cộng</td><td></td>${tdn(totalRev)}<td></td>${tdn(R.grandTotal)}${tdn(manualTotal)}${tdn(R.salaryDeptTotals.total)}${tdn(R.salarySitesTotal.actual)}${tdn(totalCost)}<td class="num">${totalRev > 0 ? pct(totalCost / totalRev) : '—'}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid">
        <div class="card">
          <div class="card-head"><h2>Nhóm phân bổ</h2></div>
          <div class="table-wrap"><table class="grid-table">
            <thead><tr><th>Nhóm</th><th>Phương pháp</th><th>Bộ phận</th><th class="num">Doanh thu</th><th class="num">Chi phí (I)</th></tr></thead>
            <tbody>${R.groupInfo
              .map((g) => `<tr><td><strong>${esc(g.name)}</strong></td><td class="muted">${esc(METHOD_OPTIONS.find((m) => m.value === g.method).label)}</td><td class="wrap">${g.depts.map((id) => `<span class="chip">${esc(deptName(id))} ${pct(g.weights[id], 1)}</span>`).join(' ')}</td>${tdn(g.revenue)}${tdn(g.cost)}</tr>`)
              .join('')}</tbody>
          </table></div>
        </div>
        <div class="card">
          <div class="card-head"><h2>Quy trình hàng tháng</h2></div>
          <ol style="margin:0;padding-left:18px;line-height:1.7">
            <li><strong>Nhập Excel</strong> file "File chi phí MN Txx" (hoặc chọn <em>Kỳ mới</em> rồi nhập tay).</li>
            <li>Kiểm tra tab <strong>Doanh thu</strong> (điểm, tỷ lệ Posh/JP) và <strong>Chi phí đầu vào</strong> (kiểu chia, loại trừ, cột nhập tay).</li>
            <li>Bổ sung <strong>Lương</strong> theo bộ phận / theo cơ sở nếu thiếu.</li>
            <li>Xem tab <strong>Kiểm tra</strong> — không còn lỗi đỏ.</li>
            <li><strong>Xuất Excel</strong>: sheet "File tổng báo cáo" + bảng kiểm + phân bổ theo điểm để hạch toán MISA.</li>
          </ol>
          <p class="hint" style="margin-top:8px">Dữ liệu tự lưu trên trình duyệt. Muốn chuyển máy: menu ⋯ → <em>Lưu file cấu hình (.json)</em>.</p>
        </div>
      </div>`;
  }

  // ------------------------------------------------------------------ Tab: Doanh thu
  function renderRevenue(root) {
    const R = report;
    const filter = ui.siteFilter || 'all';
    const sites = state.sites.map((s, i) => ({ s, i })).filter((x) => filter === 'all' || x.s.dept === filter);
    root.innerHTML = `
      <div class="grid">
        <div class="card">
          <div class="card-head"><h2>Nhóm phân bổ</h2><span class="hint">MTĐ chia theo tỷ lệ cố định (Posh 60 / JP 40); KVC chia theo doanh thu.</span></div>
          <div class="table-wrap"><table class="grid-table">
            <thead><tr><th>Mã</th><th>Tên nhóm</th><th>Phương pháp phân bổ</th><th></th></tr></thead>
            <tbody>${state.groups
              .map((g, i) => `<tr><td class="muted"><code>${esc(g.id)}</code></td><td>${inp(`groups.${i}.name`, g.name, 'text', 'wide')}</td><td>${sel(`groups.${i}.method`, g.method, METHOD_OPTIONS)}</td>${actBtns([{ act: 'delGroup', arg: i, icon: '🗑', title: 'Xoá nhóm', cls: 'danger' }])}</tr>`)
              .join('')}</tbody>
          </table></div>
          <div class="toolbar" style="margin-top:8px"><button class="btn small" data-act="addGroup">+ Thêm nhóm</button></div>
        </div>

        <div class="card">
          <div class="card-head"><h2>Bộ phận</h2><span class="hint">Doanh thu tự cộng từ các điểm; tick "Nhập tay" để gõ số trực tiếp.</span></div>
          <div class="table-wrap"><table class="grid-table">
            <thead><tr><th class="sticky-col">Bộ phận</th><th>Mã đơn vị MISA</th><th>Nhóm</th><th class="num">Tỷ lệ cố định %</th><th class="num">Doanh thu</th><th>Nhập tay</th><th class="num">Số điểm</th><th class="num">Tỷ trọng áp dụng</th><th></th></tr></thead>
            <tbody>${state.departments
              .map((d, i) => {
                const g = R.groupInfo.find((x) => x.id === d.group) || { weights: {} };
                const n = state.sites.filter((s) => s.dept === d.id).length;
                return `<tr>
                  <td class="sticky-col">${inp(`departments.${i}.name`, d.name, 'text')}</td>
                  <td>${inp(`departments.${i}.unitCode`, d.unitCode, 'text', 'code')}</td>
                  <td>${sel(`departments.${i}.group`, d.group, state.groups.map((x) => ({ value: x.id, label: x.name })))}</td>
                  <td>${inp(`departments.${i}.ratio`, d.ratio, 'pct')}</td>
                  <td>${d.revenueOverride || n === 0 ? inp(`departments.${i}.revenue`, d.revenue, 'num') : `<input class="num ro" readonly value="${fmt(R.revenue[d.id])}" title="Tổng doanh thu ${n} điểm">`}</td>
                  <td class="center">${chk(`departments.${i}.revenueOverride`, d.revenueOverride)}</td>
                  <td class="num muted">${n}</td>
                  <td class="num">${pct(g.weights[d.id] || 0)}</td>
                  ${actBtns([
                    { act: 'moveDept', arg: `${i}:-1`, icon: '↑', title: 'Lên' },
                    { act: 'moveDept', arg: `${i}:1`, icon: '↓', title: 'Xuống' },
                    { act: 'delDept', arg: i, icon: '🗑', title: 'Xoá bộ phận', cls: 'danger' },
                  ])}
                </tr>`;
              })
              .join('')}
              <tr class="total"><td class="sticky-col">Tổng</td><td></td><td></td><td></td>${tdn(Object.values(R.revenue).reduce((a, b) => a + b, 0))}<td></td><td class="num">${state.sites.length}</td><td></td><td></td></tr>
            </tbody>
          </table></div>
          <div class="toolbar" style="margin-top:8px"><button class="btn small" data-act="addDept">+ Thêm bộ phận</button></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Điểm bán / cơ sở & doanh thu ${R.periodLabel}</h2>
          <div class="spacer"></div>
          <label class="hint">Lọc bộ phận
            <select id="siteFilter">${[{ value: 'all', label: 'Tất cả' }, ...deptOptions()].map((o) => `<option value="${o.value}" ${o.value === filter ? 'selected' : ''}>${esc(o.label)}</option>`).join('')}</select>
          </label>
          <button class="btn small" data-act="addSite">+ Thêm điểm</button>
          <button class="btn small" data-act="togglePaste">Dán nhanh từ Excel</button>
          <button class="btn small ${state.options.fabiTuDong ? 'primary' : ''}" data-act="fabiTuDong" title="Bật thì doanh thu của các điểm ĐÃ LIÊN KẾT tự lấy từ Doanh thu FABi mỗi lần mở kỳ — khỏi bấm. Kỳ đã chốt thì dừng lấy, số đứng yên.">${state.options.fabiTuDong ? '🔗 Tự lấy: BẬT' : '🔗 Tự lấy: tắt'}</button>
          <button class="btn small primary" data-act="napFabi" data-arg="fabi" title="${esc(NGUON.fabi.mo)}">⬇ Nạp từ ${esc(NGUON.fabi.nhan)}</button>
          <button class="btn small primary" data-act="napFabi" data-arg="ghe" title="${esc(NGUON.ghe.mo)}">⬇ Nạp từ ${esc(NGUON.ghe.nhan)}</button>
          <button class="btn small danger" data-act="zeroSites" title="Đưa doanh thu tất cả điểm đang lọc về 0">Xoá số doanh thu</button>
        </div>
        ${fabiTrangThaiHtml()}
        ${fabiBoxHtml()}
        <div id="pasteBox" class="card" style="margin:0 0 10px;background:var(--panel-2)" hidden>
          <p class="hint">Mỗi dòng: <kbd>Mã đơn vị</kbd> <kbd>Tab</kbd> <kbd>Tên điểm</kbd> <kbd>Tab</kbd> <kbd>Doanh thu</kbd> (tuỳ chọn thêm <kbd>Tab</kbd> <kbd>Bộ phận</kbd>). Điểm trùng mã sẽ được cập nhật doanh thu.</p>
          <textarea id="pasteArea" placeholder="50AMBT	POSH MN AEON MALL BÌNH TÂN	197260000	Posh"></textarea>
          <div class="toolbar" style="margin-top:6px">
            <label class="hint">Bộ phận mặc định <select id="pasteDept">${deptOptions().map((o) => `<option value="${o.value}">${esc(o.label)}</option>`).join('')}</select></label>
            <button class="btn small primary" data-act="applyPaste">Thêm vào danh sách</button>
          </div>
        </div>
        <div class="table-wrap tall"><table class="grid-table dense">
          <thead><tr><th>#</th><th>Bộ phận</th><th>Mã đơn vị</th><th>Tên điểm</th><th class="num">Doanh thu</th><th title="Số này lấy từ đâu: liên kết sống với Doanh thu FABi, hay gõ tay.">Nguồn</th><th class="num">Tỷ trọng trong BP</th><th title="Cơ sở nghỉ / đóng cửa: VẪN ghi doanh thu, nhưng không nhận chi phí phân bổ. Phần chi phí đó chia lại cho các cơ sở còn lại — tổng chi phí bộ phận không đổi.">Không nhận<br>chi phí</th><th></th></tr></thead>
          <tbody>${
            sites.length
              ? sites
                  .map(({ s, i }, k) => {
                    const rev = R.revenue[s.dept] || 0;
                    return `<tr>
                      <td class="muted">${k + 1}</td>
                      <td>${sel(`sites.${i}.dept`, s.dept, deptOptions())}</td>
                      <td>${inp(`sites.${i}.code`, s.code, 'text', 'code')}</td>
                      <td>${inp(`sites.${i}.name`, s.name, 'text', 'xwide')}</td>
                      <td>${inp(`sites.${i}.revenue`, s.revenue, 'num', '', (state.options.fabiTuDong && nguonCua(s) && !sync.locked) ? 'readonly title="Số này tự lấy từ nguồn đã nối. Muốn gõ tay thì tắt Tự lấy, hoặc gỡ liên kết ở cột Nguồn."' : '')}</td>
                      <td class="muted" style="font-size:11px">${(() => { const n = nguonCua(s); return n
                        ? `<span title="Đang lấy từ ${esc(NGUON[n].ten)}: ${esc(s[NGUON[n].khoa])}">🔗 ${esc(NGUON[n].nhan)}</span> <button class="btn small ghost" data-act="goLienKet" data-arg="${i}" title="Gỡ liên kết — điểm này quay lại gõ tay">✕</button>`
                        : '<span title="Chưa nối nguồn nào — gõ tay">tay</span>'; })()}</td>
                      <td class="num muted">${rev > 0 ? pct(E.num(s.revenue) / rev) : '—'}</td>
                      <td class="num" title="Cơ sở nghỉ / đóng cửa: vẫn ghi doanh thu, không nhận chi phí.">${chk(`sites.${i}.khongChiPhi`, s.khongChiPhi)}</td>
                      ${actBtns([{ act: 'delSite', arg: i, icon: '🗑', title: 'Xoá điểm', cls: 'danger' }])}
                    </tr>`;
                  })
                  .join('')
              : `<tr><td colspan="9" class="empty">Chưa có điểm nào. Nhập Excel, dán nhanh, hoặc thêm từng điểm.</td></tr>`
          }
          ${sites.length ? `<tr class="total"><td colspan="4">Cộng (${sites.length} điểm)${(() => { const n = sites.filter((x) => x.s.khongChiPhi).length; return n ? ` · <span class="muted">${n} điểm không nhận chi phí</span>` : ''; })()}</td>${tdn(sites.reduce((a, x) => a + E.num(x.s.revenue), 0))}<td></td><td></td><td></td><td></td></tr>` : ''}
          </tbody>
        </table></div>
      </div>`;
    $('#siteFilter', root).addEventListener('change', (e) => {
      ui.siteFilter = e.target.value;
      saveState();
      renderTab();
    });
  }

  // ------------------------------------------------------------------ Tab: Chi phí đầu vào
  function renderCosts(root) {
    const R = report;
    const groups = state.groups;
    const splitOptions = [{ value: 'equal', label: 'Chia đều các nhóm' }, ...groups.map((g) => ({ value: g.id, label: `100% ${g.name}` })), { value: 'custom', label: 'Tuỳ chỉnh' }];
    const kindOptions = [{ value: 'personal', label: 'Cá nhân' }, { value: 'company', label: 'Công ty' }];
    const methodOptions = [{ value: '', label: 'Theo nhóm' }, ...METHOD_OPTIONS];
    const filter = ui.costFilter || 'all';
    const statusOf = (it) => it.status || 'da_duyet';
    const items = state.costItems.map((it, i) => ({ it, i })).filter((x) => filter === 'all' || x.it.kind === filter || statusOf(x.it) === filter);
    const totalAll = state.costItems.reduce((a, it) => a + E.num(it.total), 0);
    const activeTotal = E.activeCostItems(state).reduce((a, it) => a + E.num(it.total), 0);
    const pendingCount = state.costItems.filter((it) => statusOf(it) === 'cho_duyet').length;
    const statusOptions = E.COST_STATUSES;
    const statusLabel = (v) => (statusOptions.find((o) => o.value === v) || { label: v }).label;

    root.innerHTML = `
      <div class="card">
        <div class="card-head">
          <h2>Khoản chi phí ${R.periodLabel}</h2>
          <span class="hint">Tương ứng vùng G..N sheet "total general". <em>Nội dung chi tiết MISA</em> là tiêu đề cột trên báo cáo; các khoản cùng <em>Gộp cột</em> hiển thị chung một cột.</span>
          <div class="spacer"></div>
          <div class="seg">
            ${[{ v: 'all', l: 'Tất cả' }, { v: 'personal', l: 'Cá nhân' }, { v: 'company', l: 'Công ty' }, { v: 'cho_duyet', l: `Chờ duyệt${pendingCount ? ` (${pendingCount})` : ''}` }, { v: 'tu_choi', l: 'Từ chối' }].map((o) => `<button data-act="costFilter" data-arg="${o.v}" class="${filter === o.v ? 'active' : ''}">${o.l}</button>`).join('')}
          </div>
          <button class="btn small primary" data-act="addItem">+ Thêm khoản</button>
        </div>
        <div class="toolbar">
          <label class="hint"><input type="checkbox" data-path="options.includePending" data-type="bool" ${state.options.includePending ? 'checked' : ''}> Tính cả khoản <b>chờ duyệt</b> vào báo cáo (xem trước)</label>
          <span class="hint">Đang tính: <b>${fmt(activeTotal)}</b> / tổng ${fmt(totalAll)}</span>
          <div class="spacer"></div>
          ${pendingCount ? `<button class="btn small" data-act="approveAll" title="Chuyển tất cả khoản chờ duyệt sang Đã duyệt">✓ Duyệt tất cả (${pendingCount})</button>` : ''}
          ${WP && sync.on ? (sync.locked ? `<span class="lock-badge">🔒 Kỳ đã chốt</span>` : '') + (API.getConfig().vai === 'Admin' ? `<button class="btn small" data-act="lockPeriod" title="${sync.locked ? 'Mở lại để sửa' : 'Chốt kỳ: nhân viên và kế toán không sửa được nữa'}">${sync.locked ? '🔓 Mở kỳ' : '🔒 Chốt kỳ'}</button>` : '') : ''}
          ${sync.on ? `<span class="hint">Khoản nhân viên nhập ở <a href="nhap.html" target="_blank" rel="noopener">trang nhập chi phí</a> tự hiện ở đây sau tối đa 1 phút.</span>` : `<span class="hint">Kết nối máy chủ (⚙) để nhân viên nhập chi phí từ máy khác.</span>`}
        </div>
        <div class="table-wrap tall"><table class="grid-table dense">
          <thead><tr>
            <th class="sticky-col">#</th><th>Trạng thái</th><th>Người nhập</th><th>Loại</th><th>Tên khoản (nội bộ)</th><th>Nội dung chung MISA</th><th>Nội dung chi tiết MISA (tiêu đề cột báo cáo)</th>
            <th>Tài khoản</th><th>Mã đối tượng</th><th class="num">Tổng tiền</th><th>Kiểu chia nhóm</th>
            ${groups.map((g) => `<th class="num">${esc(g.name)}</th>`).join('')}
            <th>Phương pháp</th><th>Loại trừ bộ phận</th><th>Gộp cột</th><th>Ghi chú</th><th>Chứng từ</th><th></th>
          </tr></thead>
          <tbody>${
            items.length
              ? items
                  .map(({ it, i }) => {
                    const shares = E.itemShares(state, it);
                    const custom = it.split === 'custom';
                    const stv = statusOf(it);
                    return `<tr class="${stv === 'cho_duyet' ? 'pending' : stv === 'tu_choi' ? 'rejected' : ''}">
                      <td class="sticky-col muted">${i + 1}</td>
                      <td>${sel(`costItems.${i}.status`, stv, statusOptions, `class="${stv}" title="${esc(statusLabel(stv))}${it.approvedBy ? ' bởi ' + esc(it.approvedBy) : ''}"`)}</td>
                      <td class="muted" title="${esc(it.createdAt ? new Date(it.createdAt).toLocaleString('vi-VN') : '')}">${esc(it.createdBy || '')}</td>
                      <td>${sel(`costItems.${i}.kind`, it.kind, kindOptions)}</td>
                      <td>${inp(`costItems.${i}.name`, it.name, 'text', 'wide')}</td>
                      <td>${inp(`costItems.${i}.misaGeneral`, it.misaGeneral, 'text', 'wide')}</td>
                      <td>${inp(`costItems.${i}.misaDetail`, it.misaDetail, 'text', 'xwide')}</td>
                      <td>${inp(`costItems.${i}.account`, it.account, 'text', 'code')}</td>
                      <td>${inp(`costItems.${i}.objectCode`, it.objectCode, 'text', 'code')}</td>
                      <td>${inp(`costItems.${i}.total`, E.num(it.total), 'num')}</td>
                      <td>${sel(`costItems.${i}.split`, it.split, splitOptions)}</td>
                      ${groups.map((g) => `<td>${custom ? inp(`costItems.${i}.shares.${g.id}`, E.num(shares[g.id]), 'num') : `<input class="num ro" readonly value="${fmt(shares[g.id])}">`}</td>`).join('')}
                      <td>${sel(`costItems.${i}.method`, it.method || '', methodOptions)}</td>
                      <td><div class="chips">${state.departments
                        .map((d) => `<label class="chip ${(it.excludeDepts || []).includes(d.id) ? 'on' : ''}" title="Bỏ ${esc(d.name)} khỏi phép chia khoản này"><input type="checkbox" data-path="costItems.${i}.excludeDepts" data-type="toggle" data-value="${d.id}" ${(it.excludeDepts || []).includes(d.id) ? 'checked' : ''}>${esc(d.name)}</label>`)
                        .join('')}</div></td>
                      <td>${inp(`costItems.${i}.groupKey`, it.groupKey, 'text', 'code short')}</td>
                      <td>${inp(`costItems.${i}.note`, it.note, 'text', 'wide')}</td>
                      <td>${(it.attachments || []).length ? `<div class="chips">${UI ? UI.attChips(it.attachments, false) : (it.attachments || []).map((u) => `<a href="${esc(u)}" target="_blank" rel="noopener">📎</a>`).join(' ')}</div>` : '<span class="muted">—</span>'}</td>
                      ${actBtns([
                        { act: 'moveItem', arg: `${i}:-1`, icon: '↑', title: 'Lên' },
                        { act: 'moveItem', arg: `${i}:1`, icon: '↓', title: 'Xuống' },
                        { act: 'dupItem', arg: i, icon: '⧉', title: 'Nhân đôi' },
                        { act: 'delItem', arg: i, icon: '🗑', title: 'Xoá khoản', cls: 'danger' },
                      ])}
                    </tr>`;
                  })
                  .join('')
              : `<tr><td colspan="${18 + groups.length}" class="empty">Chưa có khoản chi phí. Nhập Excel, nhân viên nhập ở trang nhập chi phí, hoặc bấm "+ Thêm khoản".</td></tr>`
          }
          <tr class="total"><td class="sticky-col"></td><td colspan="8">Tổng cộng (${state.costItems.length} khoản, đang tính ${fmt(activeTotal)})</td>${tdn(totalAll)}<td></td>
            ${groups.map((g) => tdn(state.costItems.reduce((a, it) => a + (E.itemShares(state, it)[g.id] || 0), 0))).join('')}<td colspan="6"></td></tr>
          </tbody>
        </table></div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Cột nhập tay trên báo cáo</h2>
          <span class="hint">Số đã có sẵn theo bộ phận, không qua phân bổ (VD: Phân bổ chi phí 154, chi phí mua máy 1543). Khi phân bổ theo điểm sẽ chia theo doanh thu.</span>
          <div class="spacer"></div>
          <button class="btn small" data-act="addManual">+ Thêm cột</button>
        </div>
        <div class="table-wrap"><table class="grid-table dense">
          <thead><tr><th class="sticky-col">Tên cột</th><th>Tài khoản</th>${state.departments.map((d) => `<th class="num">${esc(d.name)}</th>`).join('')}<th class="num">Tổng</th><th></th></tr></thead>
          <tbody>${
            state.manualCols.length
              ? state.manualCols
                  .map((mc, i) => {
                    const rc = R.manualCols.find((x) => x.id === mc.id) || { total: 0 };
                    return `<tr><td class="sticky-col">${inp(`manualCols.${i}.name`, mc.name, 'text', 'wide')}</td><td>${inp(`manualCols.${i}.account`, mc.account, 'text', 'code')}</td>
                      ${state.departments.map((d) => `<td>${inp(`manualCols.${i}.values.${d.id}`, E.num(mc.values && mc.values[d.id]), 'num')}</td>`).join('')}
                      ${tdn(rc.total)}${actBtns([{ act: 'delManual', arg: i, icon: '🗑', title: 'Xoá cột', cls: 'danger' }])}</tr>`;
                  })
                  .join('')
              : `<tr><td colspan="${4 + state.departments.length}" class="empty">Chưa có cột nhập tay.</td></tr>`
          }</tbody>
        </table></div>
      </div>`;
  }

  // ------------------------------------------------------------------ Tab: Lương
  function renderSalary(root) {
    const R = report;
    const F = E.SALARY_DEPT_FIELDS;
    // đảm bảo mỗi bộ phận có 1 dòng lương
    state.departments.forEach((d) => {
      if (!state.salaryDept.find((r) => r.dept === d.id)) state.salaryDept.push({ dept: d.id, misaGeneral: '', misaDetail: '' });
    });
    const groupsTitles = [...new Set(state.salarySites.map((r) => r.groupTitle || ''))];
    root.innerHTML = `
      <div class="card">
        <div class="card-head"><h2>Mục II — Lương theo bộ phận ${R.periodLabel}</h2><span class="hint">Lương văn phòng lấy từ dòng "Lương VP HCM" của Bảng tổng lương; các cột khác nhập tay nếu có.</span></div>
        <div class="table-wrap"><table class="grid-table dense">
          <thead><tr><th class="sticky-col">Bộ phận</th>${F.map((f) => `<th class="num">${esc(f.label)}</th>`).join('')}<th class="num">Tổng</th><th>Nội dung diễn giải chung MISA</th><th>Nội dung diễn giải chi tiết MISA</th></tr></thead>
          <tbody>${state.departments
            .map((d) => {
              const i = state.salaryDept.findIndex((r) => r.dept === d.id);
              const r = state.salaryDept[i];
              const rr = R.salaryDept.find((x) => x.dept === d.id) || { total: 0 };
              return `<tr><td class="sticky-col"><strong>${esc(d.name)}</strong></td>${F.map((f) => `<td>${inp(`salaryDept.${i}.${f.key}`, E.num(r[f.key]), 'num')}</td>`).join('')}${tdn(rr.total)}
                <td>${inp(`salaryDept.${i}.misaGeneral`, r.misaGeneral, 'text', 'xwide')}</td><td>${inp(`salaryDept.${i}.misaDetail`, r.misaDetail, 'text', 'xwide')}</td></tr>`;
            })
            .join('')}
            <tr class="total"><td class="sticky-col">Tổng cộng</td>${F.map((f) => tdn(R.salaryDeptTotals[f.key])).join('')}${tdn(R.salaryDeptTotals.total)}<td></td><td></td></tr>
          </tbody>
        </table></div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Mục III — Lương nhân viên cơ sở ${R.periodLabel}</h2>
          <span class="hint">"Theo báo cáo" = cột bộ phận trong Bảng tổng lương; "Thực lĩnh" = cột Số tiền. Nhóm hiển thị = tiêu đề khối trên báo cáo (POSH - JP, Tàu HCM…).</span>
          <div class="spacer"></div>
          <button class="btn small ${state.options.luongTuDong ? 'primary' : ''}" data-act="luongTuDong" title="Bật thì lương của các dòng ĐÃ LIÊN KẾT tự lấy từ trang Nhân sự mỗi lần mở kỳ. Kỳ đã chốt thì dừng lấy, số đứng yên.">${state.options.luongTuDong ? '🔗 Tự lấy: BẬT' : '🔗 Tự lấy: tắt'}</button>
          <button class="btn small primary" data-act="napLuong" title="quan-tri-cham-cong — tổng lương mỗi cơ sở của kỳ này">⬇ Nạp lương từ Nhân sự</button>
          <button class="btn small ghost" data-act="nsKham" title="Liệt kê lớp / hàm / bảng của plugin Chấm công, để tìm đúng hàm tính lương. Chỉ in TÊN, không đọc nội dung bảng nào.">🔍 Khám plugin Nhân sự</button>
          <button class="btn small" data-act="syncSalary" title="Đặt Báo cáo và DNTT = Theo báo cáo cho tất cả dòng">Báo cáo = DNTT = Theo báo cáo</button>
          <button class="btn small primary" data-act="addSalarySite">+ Thêm dòng</button>
        </div>
        ${luongTrangThaiHtml()}
        ${luongBoxHtml()}
        <div class="table-wrap tall"><table class="grid-table dense">
          <thead><tr><th class="sticky-col">Nhóm hiển thị</th><th>STT</th><th>Tên cơ sở</th><th>Bộ phận</th><th class="num">Theo báo cáo (Lương NV)</th><th class="num">Báo cáo</th><th class="num">DNTT</th><th class="num">Thực lĩnh</th><th class="num">Lệch</th><th>Mã đơn vị</th><th>Nội dung chung MISA</th><th>Nội dung chi tiết MISA</th><th></th></tr></thead>
          <tbody>${
            state.salarySites.length
              ? groupsTitles
                  .map((title) => {
                    const rows = state.salarySites.map((r, i) => ({ r, i })).filter((x) => (x.r.groupTitle || '') === title);
                    const g = R.siteGroups.find((x) => x.title === (title || 'Khác')) || R.siteGroups.find((x) => x.title === title) || { sub: { reported: 0, report: 0, dntt: 0, actual: 0 } };
                    return (
                      rows
                        .map(({ r, i }) => `<tr>
                          <td class="sticky-col">${inp(`salarySites.${i}.groupTitle`, r.groupTitle, 'text', 'code')}</td>
                          <td>${inp(`salarySites.${i}.stt`, r.stt, 'text', 'short')}</td>
                          <td>${inp(`salarySites.${i}.name`, r.name, 'text', 'wide')}${(r.nsTen || '').trim()
                            ? ` <span class="muted" style="font-size:11px" title="Lương đang lấy từ Nhân sự: ${esc(r.nsTen)}">🔗 NS</span> <button class="btn small ghost" data-act="goLienKetLuong" data-arg="${i}" title="Gỡ liên kết — dòng này quay lại gõ tay">✕</button>` : ''}</td>
                          <td>${sel(`salarySites.${i}.dept`, r.dept, deptOptions())}</td>
                          <td>${inp(`salarySites.${i}.reported`, E.num(r.reported), 'num', '', (state.options.luongTuDong && (r.nsTen || '').trim() && !sync.locked) ? 'readonly title="Số này tự lấy từ Nhân sự. Muốn gõ tay thì tắt Tự lấy, hoặc gỡ liên kết cạnh tên cơ sở."' : '')}</td>
                          <td>${inp(`salarySites.${i}.report`, E.num(r.report), 'num')}</td>
                          <td>${inp(`salarySites.${i}.dntt`, E.num(r.dntt), 'num')}</td>
                          <td>${inp(`salarySites.${i}.actual`, E.num(r.actual), 'num')}</td>
                          ${tdn(E.num(r.actual) - E.num(r.reported), 'muted')}
                          <td>${inp(`salarySites.${i}.unitCode`, r.unitCode, 'text', 'code')}</td>
                          <td>${inp(`salarySites.${i}.misaGeneral`, r.misaGeneral, 'text', 'xwide')}</td>
                          <td>${inp(`salarySites.${i}.misaDetail`, r.misaDetail, 'text', 'xwide')}</td>
                          ${actBtns([
                            { act: 'moveSalary', arg: `${i}:-1`, icon: '↑', title: 'Lên' },
                            { act: 'moveSalary', arg: `${i}:1`, icon: '↓', title: 'Xuống' },
                            { act: 'delSalarySite', arg: i, icon: '🗑', title: 'Xoá dòng', cls: 'danger' },
                          ])}
                        </tr>`)
                        .join('') +
                      `<tr class="subtotal"><td class="sticky-col">Cộng ${esc(title || 'Khác')}</td><td colspan="3"></td>${tdn(g.sub.reported)}${tdn(g.sub.report)}${tdn(g.sub.dntt)}${tdn(g.sub.actual)}${tdn(g.sub.actual - g.sub.reported)}<td colspan="4"></td></tr>`
                    );
                  })
                  .join('')
              : `<tr><td colspan="13" class="empty">Chưa có dòng lương cơ sở.</td></tr>`
          }
          <tr class="total"><td class="sticky-col">TỔNG CỘNG</td><td colspan="3"></td>${tdn(R.salarySitesTotal.reported)}${tdn(R.salarySitesTotal.report)}${tdn(R.salarySitesTotal.dntt)}${tdn(R.salarySitesTotal.actual)}${tdn(R.salarySitesTotal.actual - R.salarySitesTotal.reported)}<td colspan="4"></td></tr>
          </tbody>
        </table></div>
      </div>`;
  }

  // ------------------------------------------------------------------ Tab: File tổng báo cáo
  function renderReport(root) {
    const R = report;
    const P = R.periodLabel;
    const F = E.SALARY_DEPT_FIELDS;
    const manualTotal = R.manualCols.reduce((a, m) => a + m.total, 0);
    root.innerHTML = `
      <div class="toolbar no-print">
        <button class="btn primary" data-act="export">📤 Xuất Excel</button>
        <button class="btn" data-act="print">🖨 In / PDF</button>
        <button class="btn" data-act="copyReport">📋 Sao chép Mục I</button>
        <span class="hint">Bảng dưới là nội dung sheet "File tổng báo cáo" sẽ xuất ra. Sửa số ở các tab đầu vào.</span>
      </div>
      <div class="card report">
        <div class="report-title">BÁO CÁO CHI PHÍ ${esc(P)}</div>

        <div class="section">
          <h3>I. Chi phí phân bổ theo bộ phận</h3>
          <div class="table-wrap"><table class="grid-table dense">
            <thead>
              <tr><th class="sticky-col">Bộ Phận</th>${R.columns.map((c) => `<th class="num" title="${esc(c.items.map((i) => i.name).join(' / '))}">${esc(c.title)}</th>`).join('')}${R.manualCols.map((m) => `<th class="num">${esc(m.name)}</th>`).join('')}<th class="num">Tổng</th></tr>
              <tr><th class="sticky-col muted" style="font-weight:500">Tài khoản</th>${R.columns.map((c) => `<th class="num muted" style="font-weight:500">${esc(c.account)}</th>`).join('')}${R.manualCols.map((m) => `<th class="num muted" style="font-weight:500">${esc(m.account || '')}</th>`).join('')}<th></th></tr>
            </thead>
            <tbody>
              ${R.departments
                .map((d) => `<tr><td class="sticky-col"><strong>${esc(d.name)}</strong></td>${R.columns.map((c) => tdn(R.matrix[d.id][c.key])).join('')}${R.manualCols.map((m) => tdn(m.vals[d.id])).join('')}${tdn(R.rowTotals[d.id] + R.manualCols.reduce((a, m) => a + (m.vals[d.id] || 0), 0))}</tr>`)
                .join('')}
              <tr class="total"><td class="sticky-col">Tổng cộng</td>${R.columns.map((c) => tdn(R.colTotals[c.key])).join('')}${R.manualCols.map((m) => tdn(m.total)).join('')}${tdn(R.grandTotal + manualTotal)}</tr>
            </tbody>
          </table></div>
        </div>

        <div class="section">
          <h3>II. Lương theo bộ phận</h3>
          <div class="table-wrap"><table class="grid-table dense">
            <thead><tr><th class="sticky-col">Bộ Phận</th>${F.map((f) => `<th class="num">${esc(f.label)}<br>${esc(P)}</th>`).join('')}<th class="num">Tổng</th><th>Nội dung cột diễn giải chung trên misa</th><th>Nội dung cột diễn giải chi tiết trên misa</th></tr></thead>
            <tbody>
              ${R.salaryDept.map((r) => `<tr><td class="sticky-col"><strong>${esc(deptName(r.dept))}</strong></td>${F.map((f) => tdn(r[f.key])).join('')}${tdn(r.total)}<td class="text">${esc(r.misaGeneral)}</td><td class="text">${esc(r.misaDetail)}</td></tr>`).join('')}
              <tr class="total"><td class="sticky-col">Tổng cộng</td>${F.map((f) => tdn(R.salaryDeptTotals[f.key])).join('')}${tdn(R.salaryDeptTotals.total)}<td></td><td></td></tr>
            </tbody>
          </table></div>
        </div>

        <div class="section">
          <h3>III. Lương nhân viên cơ sở HCM</h3>
          <div class="table-wrap"><table class="grid-table dense">
            <thead><tr><th class="sticky-col">STT</th><th>Tên cơ sở</th><th class="num">Lương NV ${esc(P)}<br><span class="muted">(Theo báo cáo)</span></th><th class="num">Báo cáo</th><th class="num">DNTT / Báo cáo</th><th class="num">Thực lĩnh</th><th>Mã đơn vị</th><th>Nội dung cột diễn giải chung trên misa</th><th>Nội dung cột diễn giải chi tiết trên misa</th></tr></thead>
            <tbody>
              ${R.siteGroups
                .map(
                  (g) => `<tr class="group-head"><th class="sticky-col">STT</th><th>${esc(g.title)}</th><th class="num">Lương NV ${esc(P)}</th><th class="num">Báo cáo</th><th class="num">DNTT</th><th class="num">Thực lĩnh</th><th>Mã đơn vị</th><th></th><th></th></tr>` +
                    g.rows.map((r, i) => `<tr><td class="sticky-col muted">${esc(r.stt || i + 1)}</td><td>${esc(r.name)}</td>${tdn(r.reported)}${tdn(r.report)}${tdn(r.dntt)}${tdn(r.actual)}<td><code>${esc(r.unitCode)}</code></td><td class="text">${esc(r.misaGeneral)}</td><td class="text">${esc(r.misaDetail)}</td></tr>`).join('') +
                    `<tr class="subtotal"><td class="sticky-col"></td><td>Cộng ${esc(g.title)}</td>${tdn(g.sub.reported)}${tdn(g.sub.report)}${tdn(g.sub.dntt)}${tdn(g.sub.actual)}<td></td><td></td><td></td></tr>`
                )
                .join('')}
              <tr class="total"><td class="sticky-col"></td><td>TỔNG CỘNG LƯƠNG CƠ SỞ</td>${tdn(R.salarySitesTotal.reported)}${tdn(R.salarySitesTotal.report)}${tdn(R.salarySitesTotal.dntt)}${tdn(R.salarySitesTotal.actual)}<td></td><td></td><td></td></tr>
            </tbody>
          </table></div>
        </div>
      </div>`;
  }

  // ------------------------------------------------------------------ Tab: Phân bổ theo điểm
  function renderSites(root) {
    const R = report;
    if (!ui.sitesDept || !state.departments.find((d) => d.id === ui.sitesDept)) ui.sitesDept = (state.departments[0] || {}).id || '';
    const alloc = ui.sitesDept ? E.allocateSites(state, R, ui.sitesDept) : null;
    root.innerHTML = `
      <div class="card">
        <div class="card-head">
          <h2>Phân bổ xuống điểm theo tỷ trọng doanh thu</h2>
          <span class="hint">Tương ứng các sheet "Posh T8.2026", "FZ T8.2026"… Dòng "Số tiền phân bổ" là số của bộ phận trên File tổng báo cáo.</span>
          <div class="spacer"></div>
          <div class="seg">${state.departments.map((d) => `<button data-act="sitesDept" data-arg="${d.id}" class="${ui.sitesDept === d.id ? 'active' : ''}">${esc(d.name)}</button>`).join('')}</div>
        </div>
        ${
          alloc && alloc.rows.length
            ? `<div class="table-wrap tall"><table class="grid-table dense">
            <thead>
              <tr><th class="sticky-col">Tên điểm</th><th>Mã đơn vị</th><th class="num">Doanh thu</th><th class="num">Tỷ trọng</th>${alloc.cols.map((c) => `<th class="num">${esc(c.title)}</th>`).join('')}<th class="num">Tổng</th></tr>
              <tr class="subtotal"><td class="sticky-col"><strong>Số tiền phân bổ</strong></td><td class="muted">${alloc.cols.length} cột</td>${tdn(alloc.sumRevenue)}<td class="num" title="${alloc.sumRevenueCP < alloc.sumRevenue ? 'Chi phí chia trên ' + fmt(alloc.sumRevenueCP) + ' — đã trừ doanh thu của cơ sở không nhận chi phí.' : 'Chia trên toàn bộ doanh thu.'}">100%</td>${alloc.cols.map((c) => tdn(c.total)).join('')}${tdn(alloc.cols.reduce((a, c) => a + c.total, 0))}</tr>
            </thead>
            <tbody>
              ${alloc.rows.map((r) => `<tr class="${r.khongChiPhi ? 'muted' : ''}"><td class="sticky-col">${esc(r.name)}${r.khongChiPhi ? ' <span class="hint" title="Cơ sở nghỉ: vẫn ghi doanh thu, không nhận chi phí. Phần của nó đã chia lại cho các cơ sở còn lại.">· không nhận chi phí</span>' : ''}</td><td><code>${esc(r.code)}</code></td>${tdn(r.revenue)}<td class="num muted">${pct(r.weight)}</td>${alloc.cols.map((c) => tdn(r.vals[c.key])).join('')}${tdn(r.total)}</tr>`).join('')}
              <tr class="total"><td class="sticky-col">Tổng cộng</td><td></td>${tdn(alloc.sumRevenue)}<td class="num">100%</td>${alloc.cols.map((c) => tdn(alloc.totals[c.key])).join('')}${tdn(alloc.grandTotal)}</tr>
            </tbody>
          </table></div>`
            : `<div class="empty">Bộ phận này chưa có điểm bán nào (tab Doanh thu).</div>`
        }
      </div>`;
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * TAB "CHI TIẾT MISA" — anh Thắng 16/09/2026: *"Muốn nhìn chi tiết trực quan trước khi xuất"*.
   *
   * Cùng một `E.misaRows()` đã dựng sheet "<Bộ phận> chi tiết" trong file Excel — KHÔNG dựng lại
   * bằng đường khác. Nhìn ở đây thấy sao thì xuất ra đúng vậy.
   *
   * 🔴 VÀ CỘT CŨNG PHẢI ĐỌC TỪ ĐÚNG MỘT CHỖ VỚI FILE. Anh Thắng 16/09/2026: *"khác nhau và thiếu
   *    cột"* — bản đầu tự kê một bộ cột riêng cho màn hình, thiếu Ngày chứng từ / Ngày hạch toán /
   *    Số tiền quy đổi và xếp sai thứ tự. Hai danh sách thì sớm muộn cũng lệch, mà lệch ở đây phá
   *    đúng lời hứa của cái tab. Nay dựng từ `EXP.MISA_COLS` + `EXP.MISA_MAP` — chính hai bảng
   *    exporter dùng để ghi file.
   *
   * GẬP SẴN THEO CHỨNG TỪ. Posh có 21 chứng từ × 66 điểm = 1.386 dòng; đổ hết ra là một bức tường
   * số không ai soát nổi. Gập lại thì mỗi chứng từ một dòng tóm tắt — đúng mức để liếc qua thấy
   * sai ngay, muốn xem kỹ thì bấm mở.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  function renderMisa(root) {
    if (!ui.sitesDept || !state.departments.find((d) => d.id === ui.sitesDept)) ui.sitesDept = (state.departments[0] || {}).id || '';
    const kq = ui.sitesDept ? E.misaRows(state, report, ui.sitesDept) : null;
    const segs = state.departments.map((d) => `<button data-act="sitesDept" data-arg="${d.id}" class="${ui.sitesDept === d.id ? 'active' : ''}">${esc(d.name)}</button>`).join('');

    if (!kq || !kq.rows.length) {
      root.innerHTML = `
        <div class="card">
          <div class="card-head"><h2>Chi tiết MISA</h2><div class="spacer"></div><div class="seg">${segs}</div></div>
          <div class="empty">Bộ phận này chưa có điểm bán nào nhận chi phí (tab Doanh thu).</div>
        </div>`;
      return;
    }

    /* Cột hiện ra: mặc định CHỈ những cột có dữ liệu, nhưng vẫn đúng thứ tự và đúng TÊN như trong
       file. Bật "đủ 50 cột" thì bày trọn mẫu MISA, gồm cả 40 cột để trống — để soi cho chắc mẫu
       khớp, chứ ngày thường nhìn 40 cột rỗng thì chẳng soát được gì. */
    const dayDu = !!ui.misa50;
    const cot = dayDu
      ? EXP.MISA_COLS.map((ten, i) => ({ i, ten, m: EXP.MISA_MAP.find((x) => x.i === i) || null }))
      : EXP.MISA_MAP.map((m) => ({ i: m.i, ten: EXP.MISA_COLS[m.i], m }));

    // gom theo số chứng từ, giữ nguyên thứ tự misaRows đã xếp
    const ct = [];
    const map = {};
    kq.rows.forEach((r) => {
      if (!map[r.soCT]) { map[r.soCT] = { soCT: r.soCT, rows: [], tong: 0 }; ct.push(map[r.soCT]); }
      map[r.soCT].rows.push(r);
      map[r.soCT].tong += r.soTien;
    });
    const thieu = ct.filter((c) => !c.rows[0].tkNo || !c.rows[0].tkCo);
    const hien = ui.misaThieu ? thieu : ct;
    const mo = new Set(ui.misaMo || []);
    const tongTien = ct.reduce((a, c) => a + c.tong, 0);
    const nghi = (state.sites || []).filter((x) => x.dept === ui.sitesDept && x.khongChiPhi);

    /* Ô của một dòng chi tiết — mỗi cột lấy ĐÚNG trường mà exporter sẽ ghi vào ô ấy. */
    function oChiTiet(r, c) {
      if (!c.m) return '<td class="muted"></td>';
      const v = r[c.m.key];
      if (c.m.num) return tdn(E.num(v));
      return `<td>${c.m.key === 'maDonVi' || c.m.key === 'soCT' ? `<code>${esc(v)}</code>` : esc(v)}</td>`;
    }
    /* Ô của dòng TÓM TẮT chứng từ: cột nào giống nhau ở mọi dòng thì in ra, cột nào khác nhau
       (diễn giải hạch toán, mã đơn vị) thì nói rõ "N dòng" thay vì bịa một giá trị đại diện. */
    function oTomTat(c, cum) {
      if (!c.m) return '<td></td>';
      const dau = cum.rows[0];
      if (c.m.num) return tdn(cum.tong);
      if (c.m.key === 'dienGiaiHT') return `<td class="muted">${cum.rows.length} dòng</td>`;
      if (c.m.key === 'maDonVi') return '<td class="muted">—</td>';
      const v = dau[c.m.key];
      const xau = (c.m.key === 'tkNo' || c.m.key === 'tkCo') && !v;
      if (xau) return '<td class="num neg">⚠ thiếu</td>';
      return `<td${c.m.key === 'dienGiai' ? '' : ' class="num"'}>${c.m.key === 'soCT' ? `<code>${esc(v)}</code>` : esc(v)}</td>`;
    }

    root.innerHTML = `
      <div class="card">
        <div class="card-head">
          <h2>Chi tiết MISA</h2>
          <span class="hint">Đúng những dòng — và đúng những cột — sẽ nằm trong sheet "<strong>${esc(kq.dept.name)} chi tiết</strong>" khi bấm Xuất Excel.</span>
          <div class="spacer"></div>
          <div class="seg">${segs}</div>
        </div>
        <div class="toolbar" style="margin-bottom:10px">
          <span class="pill">${ct.length} chứng từ</span>
          <span class="pill">${kq.rows.length} dòng</span>
          <span class="pill">Tổng ${fmt(tongTien)}</span>
          ${thieu.length ? `<button class="btn small ${ui.misaThieu ? 'primary' : 'danger'}" data-act="misaThieu">${ui.misaThieu ? '← Xem tất cả' : `⚠ ${thieu.length} chứng từ thiếu tài khoản`}</button>` : '<span class="pill ok">Đủ tài khoản</span>'}
          <div class="spacer"></div>
          <button class="btn small ${dayDu ? 'primary' : ''}" data-act="misa50" title="Bày trọn mẫu 50 cột của MISA, gồm cả các cột để trống — để đối chiếu mẫu cho chắc.">${dayDu ? 'Chỉ cột có dữ liệu' : 'Đủ 50 cột như file'}</button>
          <button class="btn small" data-act="misaMoHet">${mo.size >= ct.length ? 'Gập tất cả' : 'Mở tất cả'}</button>
          <button class="btn small primary" data-act="xuatMisa" title="Chỉ tờ nhập MISA của bộ phận này — file riêng, mở ra là đúng sheet cần nhập.">⬇ Xuất tờ MISA (${esc(kq.dept.name)})</button>
          <button class="btn small" data-act="xuatMisaHet" title="Một file, mỗi bộ phận một sheet — vẫn chỉ gồm tờ nhập MISA, không kèm báo cáo.">⬇ Cả 7 bộ phận</button>
          <button class="btn small" data-act="export" title="File tổng 17 sheet: báo cáo + phân bổ + tờ nhập MISA. KHÔNG dùng để nhập thẳng vào MISA.">Xuất file tổng</button>
        </div>
        ${nghi.length ? `<p class="hint">${nghi.length} cơ sở không nhận chi phí (${esc(nghi.map((x) => x.code || x.name).join(', '))}) — đã bỏ khỏi danh sách dưới đây, đúng như khi xuất ra file.</p>` : ''}
        ${thieu.length ? `<div class="issue warn"><span class="lv">CẢNH BÁO</span><span>${thieu.length} chứng từ chưa có đủ TK Nợ/TK Có. MISA từ chối <strong>cả chứng từ</strong> chứ không riêng dòng thiếu — khai tài khoản ở tab "Chi phí đầu vào" (hoặc tab Lương) trước khi xuất.</span></div>` : ''}
        <div class="table-wrap tall"><table class="grid-table dense">
          <thead><tr><th style="width:26px"></th>${cot.map((c) => `<th class="${c.m && c.m.num ? 'num' : ''}${c.m ? '' : ' muted'}">${esc(c.ten)}</th>`).join('')}</tr></thead>
          <tbody>
            ${hien.map((c) => {
              const dang = mo.has(c.soCT);
              return `<tr class="subtotal" data-act="misaMo" data-arg="${esc(c.soCT)}" style="cursor:pointer" title="Bấm để ${dang ? 'gập' : 'mở'} ${c.rows.length} dòng">
                  <td>${dang ? '▾' : '▸'}</td>${cot.map((x) => oTomTat(x, c)).join('')}
                </tr>
                ${dang ? c.rows.map((r) => `<tr><td></td>${cot.map((x) => oChiTiet(r, x)).join('')}</tr>`).join('') : ''}`;
            }).join('')}
            <tr class="total"><td></td>${cot.map((c) => (c.m && c.m.num ? tdn(hien.reduce((a, x) => a + x.tong, 0)) : `<td>${c.i === 2 ? `${hien.length}/${ct.length} chứng từ` : c.i === 8 ? `${hien.reduce((a, x) => a + x.rows.length, 0)} dòng` : ''}</td>`)).join('')}</tr>
          </tbody>
        </table></div>
      </div>`;
  }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * NẠP DOANH THU TỪ "DOANH THU FABi" — anh Thắng 18/09/2026.
   *
   * 🔴 XEM TRƯỚC RỒI MỚI GHI, KHÔNG BAO GIỜ GHI THẲNG. Ghép tên quán bên FABi với điểm bán bên
   *    này là việc máy chỉ ĐOÁN được, mà đoán sai thì doanh thu chạy vào nhầm bộ phận và kéo theo
   *    TOÀN BỘ chi phí phân bổ sai — tổng vẫn đẹp, không ai thấy gì. Nên bảng dưới bày rõ từng
   *    dòng ghép vào đâu, vì sao, và cho đổi trước khi bấm Ghi.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  /* HAI NGUỒN doanh thu, cùng một lối liên kết sống. Gom vào một bảng để mọi chỗ trong giao diện
     đọc từ đây — thêm nguồn thứ ba sau này chỉ là thêm một dòng, không phải đi sửa chín chỗ. */
  const NGUON = {
    fabi: { khoa: 'fabiTen', ten: 'Doanh thu FABi', api: 'fabiDoanhThu', nhan: 'FABi',
            mo: 'khmatrix.com/doanh-thu-hcm — Funzone, Tutu, Event, Farm…' },
    /* `bp` — nguồn này CHỈ thuộc mấy bộ phận ấy. Xem khối dài trong engine.ghepFabi: không chặn
       thì ghép gần đúng theo tên nối cơ sở Ghế vào điểm Event cùng địa điểm, và 466 triệu tiền ghế
       chạy sang Event. FABi trải khắp các bộ phận nên để trống = không chặn. */
    ghe: { khoa: 'gheTen', ten: 'Ghế Massage (Posh / JP)', api: 'gheDoanhThu', nhan: 'Ghế',
           bp: ['posh', 'jp'],
           mo: 'khmatrix.com/ghe → Báo cáo tổng — doanh thu ghế theo cơ sở' },
  };

  const FABI_CACH = {
    da_luu: { chu: 'đã lưu', mo: 'Lần trước chính anh đã chọn điểm này cho cửa hàng ấy.' },
    ten: { chu: 'trùng tên', mo: 'Tên cửa hàng và tên điểm khớp nhau sau khi bỏ dấu và đuôi pháp nhân.' },
    gan: { chu: 'gần đúng', mo: 'Đoán theo số từ chung — nên nhìn lại trước khi ghi.' },
    khong: { chu: 'chưa ghép', mo: 'Không đoán được. Tự chọn điểm, hoặc để nguyên thì bỏ qua cửa hàng này.' },
    tay: { chu: 'anh chọn', mo: 'Anh vừa chọn tay — sẽ được nhớ cho kỳ sau.' },
    tao: { chu: '➕ tạo điểm mới', mo: 'Cửa hàng mới bên FABi — sẽ tạo một điểm mới bên này. Mã đơn vị để trống, nhớ điền sau.' },
    bo_han: { chu: '🚫 đã bỏ hẳn', mo: 'Cơ sở không thuộc báo cáo này (VD: ngoài miền Nam). Không ghép, không tính vào phần thiếu liên kết, và không hỏi lại. Chọn lại dòng khác là lấy lại.' },
  };

  function fabiBoxHtml() {
    if (!fabi) return '';
    if (fabi.dangTai) return `<div class="card" style="margin:0 0 10px;background:var(--panel-2)"><p class="hint">Đang đọc doanh thu từ Doanh thu FABi…</p></div>`;
    if (fabi.loi) {
      return `<div class="card" style="margin:0 0 10px;background:var(--panel-2)">
        <div class="issue error"><span class="lv">LỖI</span><span>${esc(fabi.loi)}</span></div>
        <div class="toolbar" style="margin-top:6px"><button class="btn small" data-act="fabiDong">Đóng</button></div></div>`;
    }
    const ds = fabi.ghep || [];
    const chon = ds.filter((x) => x.siteIndex !== null || x.taoMoi);
    const soTao = ds.filter((x) => x.siteIndex === null && x.taoMoi).length;
    const chuaGhep = ds.filter((x) => x.siteIndex === null && !x.taoMoi && !x.boHan);
    const soBoHan = ds.filter((x) => x.boHan).length;
    const tongChon = chon.reduce((a, x) => a + x.thanh_tien, 0);
    /* Tổng của CHÍNH NGUỒN, để đối chiếu thẳng với con số trên trang nguồn. Không bày ra thì
       không ai thấy phần bị bỏ lại — đúng chỗ 670 triệu của anh Thắng đã rơi mất. */
    const tongNguon = ds.filter((x) => !x.boHan).reduce((a, x) => a + x.thanh_tien, 0);
    /* Ô chọn có BA nhóm: bỏ qua · ghép vào điểm sẵn có · TẠO ĐIỂM MỚI trong một bộ phận.
       Nhóm thứ ba là chỗ gỡ cái kẹt anh Thắng nói: cửa hàng mới bên FABi không còn phải đứng mãi
       ở "— chưa ghép —" rồi rơi ra ngoài báo cáo. */
    const opt = (x) => `<option value="" ${x.siteIndex === null && !x.taoMoi && !x.boHan ? 'selected' : ''}>— chưa ghép (bỏ qua kỳ này) —</option>`
      + `<option value="bo_han" ${x.boHan ? 'selected' : ''}>🚫 Bỏ hẳn — không thuộc báo cáo này, đừng hỏi lại</option>`
      + `<optgroup label="Ghép vào điểm sẵn có">` + state.sites
        .map((s, i) => `<option value="${i}" ${i === x.siteIndex ? 'selected' : ''}>${esc(deptName(s.dept))} · ${esc(s.name || s.code || '(chưa đặt tên)')}</option>`).join('')
      + `</optgroup><optgroup label="➕ Tạo điểm mới trong bộ phận">` + bpCuaNguon()
        .map((d) => `<option value="new:${esc(d.id)}" ${x.taoMoi === d.id ? 'selected' : ''}>➕ ${esc(d.name)} — "${esc(E.tenGonFabi(x.cua_hang))}"</option>`).join('')
      + `</optgroup>`;
    return `<div class="card" style="margin:0 0 10px;background:var(--panel-2)">
      <div class="card-head">
        <h2>${esc((NGUON[fabi.nguon] || NGUON.fabi).ten)} ${esc(fabi.tu)} → ${esc(fabi.den)}</h2>
        <span class="hint">${ds.length} cửa hàng${soBoHan ? ` (bỏ hẳn ${soBoHan})` : ''} · <strong>nguồn có ${fmt(tongNguon)}</strong> · đã ghép ${chon.length - soTao}${soTao ? ` · tạo mới ${soTao}` : ''} · tổng sẽ ghi ${fmt(tongChon)}${tongNguon - tongChon > 0 ? ` · <strong class="bad-text">bỏ lại ${fmt(tongNguon - tongChon)}</strong>` : ''}</span>
        <div class="spacer"></div>
        ${(chuaGhep.length || soTao) ? `<label class="hint" style="display:flex;align-items:center;gap:4px">Bộ phận mặc định
          <select data-bpmd title="Cơ sở nào đoán được bộ phận từ tên thì theo tên; còn lại vào bộ phận này. Nhớ cho những lần sau.">
            <option value="">${bpTuNguon(fabi.nguon) ? `— tự theo các điểm đang nối (${esc(deptName(bpTuNguon(fabi.nguon)))}) —` : '— đoán theo tên —'}</option>
            ${bpCuaNguon().map((d) => `<option value="${esc(d.id)}" ${((state.options.bpMacDinh || {})[fabi.nguon] || '') === d.id ? 'selected' : ''}>${esc(d.name)}</option>`).join('')}
          </select></label>
          ${chuaGhep.length ? `<button class="btn small primary" data-act="fabiTaoHet" title="Lấy hết: cửa hàng nào đoán được bộ phận từ tên thì theo tên, còn lại vào bộ phận mặc định. Vẫn sửa được từng dòng trước khi Ghi.">➕ Lấy hết ${chuaGhep.length} cửa hàng còn lại</button>` : ''}` : ''}
        <button class="btn small primary" data-act="fabiGhi" ${chon.length ? '' : 'disabled'}>Ghi ${chon.length} điểm vào báo cáo</button>
        <button class="btn small" data-act="fabiDong">Huỷ</button>
      </div>
      <div class="table-wrap"><table class="grid-table dense">
        <thead><tr><th>Cơ sở (${esc((NGUON[fabi.nguon] || NGUON.fabi).nhan)})</th><th class="num">Doanh thu</th><th class="num">Ngày</th><th>Ghép vào điểm</th><th>Vì sao</th></tr></thead>
        <tbody>
          ${ds.map((x, k) => `<tr class="${x.siteIndex === null || x.boHan ? 'muted' : ''}">
            <td>${esc(x.cua_hang)}</td>
            ${tdn(x.thanh_tien)}
            <td class="num muted">${x.so_ngay}</td>
            <td><select data-fabi="${k}" style="min-width:260px">${opt(x)}</select></td>
            <td class="muted" title="${esc((FABI_CACH[x.cach] || {}).mo || '')}">${esc((FABI_CACH[x.cach] || {}).chu || x.cach)}${x.diem ? ` (${x.diem} từ chung)` : ''}</td>
          </tr>`).join('')}
        </tbody>
      </table></div>
      <p class="hint">Cửa hàng để "— chưa ghép —" sẽ <strong>bỏ qua</strong>, không ghi gì. Điểm nào được ghi sẽ <strong>nhớ lựa chọn</strong> cho kỳ sau.</p>
    </div>`;
  }

  /* Dòng trạng thái liên kết: đang nối bao nhiêu điểm, lấy lúc nào, có điểm nào đứt liên kết
     không. Không có dòng này thì "tự lấy" là một cái hộp đen — số tự đổi mà không ai biết vì sao. */
  /* Bộ phận mặc định cho những cơ sở KHÔNG đoán được bộ phận từ tên — anh Thắng 18/09/2026:
     *"Cứ lấy hết cơ sở đó là được"*. Tên bên Ghế là tên địa điểm ("VINCOM BIÊN HÒA", "SÂN BAY PHÚ
     QUỐC") nên chẳng có chữ hiệu nào để đoán, mà đoán bừa thì doanh thu vào nhầm bộ phận. Nên:
     anh chọn MỘT LẦN cho mỗi nguồn, em nhớ, từ đó bấm một nút là lấy hết. */
  function bpMacDinh(nguon) {
    const m = (state.options && state.options.bpMacDinh) || {};
    return m[nguon || 'fabi'] || bpTuNguon(nguon);
  }

  /* Chưa chọn tay thì SUY RA từ chính dữ liệu của anh: các điểm đang nối nguồn này phần lớn thuộc
     bộ phận nào thì lấy bộ phận ấy. Đây không phải em đoán — nó là lựa chọn anh đã làm cho 40 điểm
     trước đó. Bắt anh chọn thêm một lần nữa mới "lấy hết" được thì vẫn là còn bỏ lại. */
  /* Điểm đang nối một nguồn nhưng nằm ngoài các bộ phận của nguồn ấy. Tính THẲNG TỪ STATE chứ
     không đợi lần lấy sống gần nhất — một cái sai về tiền thì phải hiện ngay khi mở trang, không
     phụ thuộc vào việc có vừa gọi máy chủ hay chưa. */
  function diemSaiBp() {
    const ra = [];
    Object.keys(NGUON).forEach((n) => {
      const N = NGUON[n];
      if (!N.bp || !N.bp.length) return;
      (state.sites || []).forEach((s, i) => {
        if ((s[N.khoa] || '').trim() && N.bp.indexOf(s.dept) < 0) {
          ra.push({ i, code: s.code, name: s.name, dept: s.dept, nguon: N.khoa, fabiTen: s[N.khoa] });
        }
      });
    });
    return ra;
  }

  /** Các bộ phận mà nguồn đang mở được phép dùng (Ghế → Posh/JP; FABi → tất cả). */
  function bpCuaNguon() {
    const N = NGUON[(fabi && fabi.nguon) || 'fabi'] || NGUON.fabi;
    if (!N.bp || !N.bp.length) return state.departments;
    return state.departments.filter((d) => N.bp.indexOf(d.id) >= 0);
  }

  function bpTuNguon(nguon) {
    const N = NGUON[nguon] || NGUON.fabi;
    const khoa = N.khoa;
    const dem = {};
    (state.sites || []).forEach((s) => {
      if (N.bp && N.bp.length && N.bp.indexOf(s.dept) < 0) return;
      if ((s[khoa] || '').trim() && s.dept) { dem[s.dept] = (dem[s.dept] || 0) + 1; }
    });
    let bp = '', n = 0;
    Object.keys(dem).forEach((d) => { if (dem[d] > n) { n = dem[d]; bp = d; } });
    /* Chưa có điểm nào để suy ra thì lấy bộ phận ĐẦU của nguồn (Ghế → Posh). Vẫn là một lựa chọn
       hiện rõ trong ô "Bộ phận mặc định" và đổi được — nhưng không để "Lấy hết" đứng im vì thiếu
       một cú bấm mà anh đã bảo bốn lần là không muốn phải bấm. */
    if (!bp && N.bp && N.bp.length) { bp = N.bp[0]; }
    return bp;
  }

  /** Điểm này đang nối nguồn nào ('fabi' | 'ghe'), hay chưa nối ('' ). */
  function nguonCua(s) {
    const k = Object.keys(NGUON).find((n) => (s[NGUON[n].khoa] || '').trim());
    return k || '';
  }

  function fabiTrangThaiHtml() {
    const dem = {};
    Object.keys(NGUON).forEach((n) => { dem[n] = (state.sites || []).filter((s) => nguonCua(s) === n).length; });
    const linh = Object.values(dem).reduce((a, b) => a + b, 0);
    if (!state.options.fabiTuDong && !linh && !diemSaiBp().length) return '';
    const mat = (fabiLan && fabiLan.mat) || [];
    const sai = diemSaiBp();
    const ve0 = (fabiLan && fabiLan.ve0) || [];
    const chot = sync.locked;
    return `<div class="issue ${sai.length ? 'error' : mat.length ? 'warn' : 'info'}" style="margin:0 0 10px">
      <span class="lv">${state.options.fabiTuDong ? '🔗 TỰ LẤY' : 'LIÊN KẾT'}</span>
      <span>${Object.keys(NGUON).filter((n) => dem[n]).map((n) => `${dem[n]} điểm ← ${esc(NGUON[n].ten)}`).join(' · ') || '0 điểm'}.
      ${!state.options.fabiTuDong ? 'Đang <strong>tắt</strong> tự lấy — số giữ nguyên như đã ghi.'
        : chot ? `Kỳ <strong>đã chốt</strong> nên dừng lấy, số đứng yên.`
        : (fabiLan && fabiLan.luc) ? `Lấy lần cuối lúc ${esc(fabiLan.luc)}${fabiLan.soDoi ? ` · đổi ${fabiLan.soDoi} điểm` : ' · không có gì đổi'} — tự lấy lại mỗi khi mở trang, mỗi khi đổi kỳ và <strong>10 phút một lần</strong>, khỏi bấm.` : 'Sẽ tự lấy khi mở trang.'}
      ${sai.length ? `<br><strong style="color:var(--bad,#c00)">🔴 ${sai.length} điểm nối SAI BỘ PHẬN</strong>:
        ${esc(sai.map((x) => `${x.name || x.code} (${deptName(x.dept)})`).join(', '))} — đây là cơ sở bên
        ${esc(NGUON.ghe.nhan)} nhưng điểm lại nằm ngoài ${esc((NGUON.ghe.bp || []).map(deptName).join(' / '))},
        nên tiền ghế đang cộng vào bộ phận khác. Em đã <strong>ngừng ghi</strong> vào mấy điểm ấy.
        <button class="btn small" data-act="goSaiBp">Gỡ ${sai.length} liên kết sai & xoá số đã ghi nhầm</button>` : ''}
      ${ve0.length ? `<br><strong>${ve0.length} điểm đưa về 0</strong> vì kỳ này nguồn chưa có số liệu (số cũ là của kỳ trước):
        ${esc(ve0.map((x) => x.code || x.name).join(', '))}.` : ''}
      ${mat.length ? `<br><strong>⚠ ${mat.length} điểm mất liên kết</strong> (cửa hàng không còn bên FABi): ${esc(mat.map((x) => x.code || x.name).join(', '))} — số cũ được giữ nguyên, vào cột Nguồn gỡ liên kết rồi nối lại.` : ''}
      </span></div>`;
  }

  async function fabiLayNgay(im) {
    if (!state.options.fabiTuDong || !API.isEnabled() || sync.locked) return;
    /* Chạy CẢ HAI nguồn. Nguồn nào chưa có điểm nào nối thì bỏ qua, khỏi gọi máy chủ thừa. */
    let doi = 0; const mat = []; const soDu = []; const sai = []; const ve0 = [];
    for (const n of Object.keys(NGUON)) {
      const N = NGUON[n];
      if (!(state.sites || []).some((s) => (s[N.khoa] || '').trim())) continue;
      try {
        const r = await API.call(N.api, { thang: state.period.month, nam: state.period.year });
        const d = r && r.data ? r.data : r;
        if (!d || d.ok === false || !d.ds) continue;
        const kq = E.dongBoFabi(state, d.ds, N.khoa, N.bp);
        doi += kq.soDoi;
        kq.mat.forEach((x) => mat.push(x));
        kq.saiBp.forEach((x) => sai.push(x));
        kq.veKhong.forEach((x) => ve0.push(x));
        /* Phần tiền bên nguồn chưa nối vào điểm nào — xem khối dài trong engine.dongBoFabi. */
        if (kq.tongChuaNoi > 0) soDu.push({ nguon: n, ds: kq.chuaNoi, tien: kq.tongChuaNoi, tongNguon: kq.tongNguon });
      } catch (e) { /* mạng hỏng thì thôi, số cũ vẫn còn — lần mở sau lấy lại */ }
    }
    fabiLan = { luc: new Date().toLocaleTimeString('vi-VN'), soDoi: doi, mat, soDu, sai, ve0 };
    if (doi || ve0.length) { commit(); if (!im) toast(`🔗 Cập nhật ${doi} điểm từ nguồn đã nối.` + (ve0.length ? ` ${ve0.length} điểm về 0 vì kỳ này chưa có số.` : '')); }
    else { recompute(); renderTab(); }
  }

  /* Chọn sẵn "tạo điểm mới" cho MỌI cửa hàng chưa ghép: đoán bộ phận theo tên trước, không ra thì
     lấy bộ phận mặc định (tự suy ra từ các điểm đang nối nguồn này). Dùng chung cho lúc nạp và cho
     nút "Lấy hết". Trả về đếm được để còn nói cho người dùng biết vừa làm gì. */
  function layHetVaoNhap() {
    const md = fabi ? bpMacDinh(fabi.nguon) : '';
    let n = 0, khong = 0, theoMd = 0;
    if (!fabi || !fabi.ghep) return { n, khong, theoMd, md };
    fabi.ghep.forEach((g) => {
      if (g.boHan || g.siteIndex !== null || g.taoMoi) return;
      /* Đoán theo tên, nhưng chỉ trong các bộ phận của nguồn — không thì "SNOW FUN AEON…" bên
         Ghế lại chui sang Event. */
      let d = E.doanBoPhan(g.cua_hang, bpCuaNguon());
      if (!d && md) { d = md; theoMd++; }
      if (d) { g.taoMoi = d; g.cach = 'tao'; n++; } else { khong++; }
    });
    return { n, khong, theoMd, md };
  }

  async function napTuFabi(nguon) {
    const N = NGUON[nguon] || NGUON.fabi;
    const kieu = NGUON[nguon] ? nguon : 'fabi';
    if (!API.isEnabled()) return toast('Chức năng này cần đăng nhập vào máy chủ (bản chạy trên WordPress).');
    fabi = { dangTai: true, nguon: kieu };
    renderTab();
    try {
      const r = await API.call(N.api, { thang: state.period.month, nam: state.period.year });
      const d = r && r.data ? r.data : r;
      if (!d || d.ok === false) { fabi = { loi: (d && d.error) || `Không đọc được ${N.ten}.` }; renderTab(); return; }
      if (!d.ds || !d.ds.length) { fabi = { loi: `Kỳ ${R_periodLabel()} chưa có dữ liệu nào bên ${N.ten}.` }; renderTab(); return; }
      fabi = { tu: d.tu, den: d.den, nguon: kieu, ghep: E.ghepFabi(state, d.ds, N.khoa, N.bp) };
      /* 🔴 LẤY HẾT NGAY. Anh Thắng 18/09/2026, ba lần: *"Cứ lấy hết cơ sở đó là được"*, *"Sao
         không lấy hết cơ sở đang có"*, *"nó đang bỏ lại, cần là lấy hết"*. Nên bản xem trước mở ra
         là đã chọn sẵn "tạo điểm mới" cho MỌI cửa hàng chưa ghép — bỏ lại 0đ. Vẫn là bản xem
         trước: còn phải bấm Ghi, và cửa hàng nào không muốn thì đổi sang "bỏ qua" hoặc "bỏ hẳn". */
      layHetVaoNhap();
      renderTab();
    } catch (e) {
      fabi = { loi: 'Lỗi khi gọi máy chủ: ' + e.message };
      renderTab();
    }
  }
  function R_periodLabel() { return E.periodLabel(state.period); }

  /* ═══════════════════════════════════════════════════════════════════════════════════════════
   * LƯƠNG TỪ TRANG NHÂN SỰ (Mục III) — cùng lối "xem trước → ghi → liên kết sống" của doanh thu.
   *
   * 🔴 CƠ SỞ CHƯA KHAI GIÁ GIỜ THÌ BÀY LÝ DO, KHÔNG BÀY SỐ 0. Khu vui chơi bên Chấm công chỉ có
   *    giờ công, chưa ra tiền. Ghi 0 vào đây thì nhìn y hệt "tháng này không có lương" — lương của
   *    cả một nhóm biến mất mà tổng vẫn cộng đẹp, không dòng nào báo.
   * ═══════════════════════════════════════════════════════════════════════════════════════════ */
  function luongBoxHtml() {
    if (!luong) return '';
    if (luong.dangTai) return `<div class="card" style="margin:0 0 10px;background:var(--panel-2)"><p class="hint">Đang đọc lương từ trang Nhân sự…</p></div>`;
    if (luong.loi) {
      return `<div class="card" style="margin:0 0 10px;background:var(--panel-2)">
        <div class="issue error"><span class="lv">LỖI</span><span>${esc(luong.loi)}</span></div>
        <div class="toolbar" style="margin-top:6px"><button class="btn small" data-act="luongDong">Đóng</button></div></div>`;
    }
    const ds = luong.ghep || [];
    const coGia = ds.filter((x) => x.co_luong !== false);
    const chuaGia = ds.filter((x) => x.co_luong === false);
    const chon = coGia.filter((x) => x.rowIndex !== null || x.taoMoi);
    const soTao = coGia.filter((x) => x.rowIndex === null && x.taoMoi).length;
    const chuaGhep = coGia.filter((x) => x.rowIndex === null && !x.taoMoi);
    const tongChon = chon.reduce((a, x) => a + x.thanh_tien, 0);
    const opt = (x) => `<option value="" ${x.rowIndex === null && !x.taoMoi ? 'selected' : ''}>— chưa ghép (bỏ qua) —</option>`
      + `<optgroup label="Ghép vào dòng lương sẵn có">` + state.salarySites
        .map((r, i) => `<option value="${i}" ${i === x.rowIndex ? 'selected' : ''}>${esc(deptName(r.dept))} · ${esc(r.name || '(chưa đặt tên)')}</option>`).join('')
      + `</optgroup><optgroup label="➕ Tạo dòng lương mới trong bộ phận">` + state.departments
        .map((d) => `<option value="new:${esc(d.id)}" ${x.taoMoi === d.id ? 'selected' : ''}>➕ ${esc(d.name)} — "${esc(E.tenGonFabi(x.cua_hang))}"</option>`).join('')
      + `</optgroup>`;
    return `<div class="card" style="margin:0 0 10px;background:var(--panel-2)">
      <div class="card-head">
        <h2>Lương theo cơ sở — Nhân sự ${esc(luong.thang || '')}</h2>
        <span class="hint">${ds.length} cơ sở · đã ghép ${chon.length - soTao}${soTao ? ` · tạo mới ${soTao}` : ''} · tổng sẽ ghi ${fmt(tongChon)}</span>
        <div class="spacer"></div>
        ${chuaGhep.length ? `<button class="btn small" data-act="luongTaoHet" title="Với mỗi cơ sở chưa ghép, tạo một dòng lương mới trong bộ phận đoán được từ tên cơ sở. Vẫn sửa được từng dòng trước khi Ghi.">➕ Tạo dòng cho ${chuaGhep.length} cơ sở còn lại</button>` : ''}
        <button class="btn small primary" data-act="luongGhi" ${chon.length ? '' : 'disabled'}>Ghi ${chon.length} dòng lương</button>
        <button class="btn small" data-act="luongDong">Huỷ</button>
      </div>
      ${chuaGia.length ? `<div class="issue warn"><span class="lv">CHƯA CÓ LƯƠNG</span><span>
        <strong>${chuaGia.length} cơ sở bên Nhân sự chưa tính ra tiền</strong> (Khu vui chơi chưa khai giá giờ):
        ${esc(chuaGia.map((x) => x.cua_hang).join(', '))}.<br>
        Những cơ sở này <strong>bị bỏ qua</strong> — em cố ý <strong>không ghi số 0</strong>, vì số 0 nhìn y hệt
        "tháng này không có lương" mà thật ra là <em>chưa khai giá</em>. Khai giá giờ bên Chấm công rồi nạp lại.
      </span></div>` : ''}
      <div class="table-wrap"><table class="grid-table dense">
        <thead><tr><th>Cơ sở (Nhân sự)</th><th class="num">Lương tháng</th><th>Ghép vào dòng lương</th><th>Vì sao</th></tr></thead>
        <tbody>
          ${ds.map((x, k) => x.co_luong === false
            ? `<tr class="muted"><td>${esc(x.cua_hang)} <button class="btn small ghost" data-act="nsChuanDoan" data-arg="${esc(x.cua_hang)}" title="Xem cấu trúc dữ liệu thật bên Chấm công cho cơ sở này (chỉ in SỐ, giấu hết họ tên / CCCD)">🔧</button></td><td class="num">—</td>
                <td colspan="2" class="muted">chưa khai giá giờ${x.ghi_chu ? ` · ${esc(x.ghi_chu)}` : ''} — bỏ qua, không ghi 0</td></tr>`
            : `<tr class="${x.rowIndex === null && !x.taoMoi ? 'muted' : ''}">
              <td>${esc(x.cua_hang)} <button class="btn small ghost" data-act="nsChuanDoan" data-arg="${esc(x.cua_hang)}" title="Xem cấu trúc dữ liệu thật bên Chấm công cho cơ sở này (chỉ in SỐ, giấu hết họ tên / CCCD)">🔧</button></td>
              ${tdn(x.thanh_tien)}
              <td><select data-luong="${k}" style="min-width:260px">${opt(x)}</select></td>
              <td class="muted" title="${esc((FABI_CACH[x.cach] || {}).mo || '')}">${esc((FABI_CACH[x.cach] || {}).chu || x.cach)}${x.diem ? ` (${x.diem} từ chung)` : ''}${x.ghi_chu ? ` · ⚠ ${esc(x.ghi_chu)}` : ''}</td>
            </tr>`).join('')}
        </tbody>
      </table></div>
      <p class="hint">Cơ sở để "— chưa ghép —" sẽ <strong>bỏ qua</strong>. Dòng nào được ghi sẽ <strong>nhớ liên kết</strong>, kỳ sau số tự về.</p>
    </div>`;
  }

  function luongTrangThaiHtml() {
    const linh = (state.salarySites || []).filter((r) => (r.nsTen || '').trim()).length;
    if (!state.options.luongTuDong && !linh) return '';
    const mat = (luongLan && luongLan.mat) || [];
    const chuaGia = (luongLan && luongLan.chuaGia) || [];
    const chuaNoi = (luongLan && luongLan.chuaNoi) || [];
    const lve0 = (luongLan && luongLan.ve0) || [];
    return `<div class="issue ${mat.length || chuaGia.length || chuaNoi.length || lve0.length ? 'warn' : 'info'}" style="margin:0 0 10px">
      <span class="lv">${state.options.luongTuDong ? '🔗 TỰ LẤY' : 'LIÊN KẾT'}</span>
      <span>${linh} dòng lương ← trang Nhân sự.
      ${!state.options.luongTuDong ? 'Đang <strong>tắt</strong> tự lấy — số giữ nguyên như đã ghi.'
        : sync.locked ? 'Kỳ <strong>đã chốt</strong> nên dừng lấy, số đứng yên.'
        : (luongLan && luongLan.luc) ? `Lấy lần cuối lúc ${esc(luongLan.luc)}${luongLan.soDoi ? ` · đổi ${luongLan.soDoi} dòng` : ' · không có gì đổi'}.` : 'Sẽ tự lấy khi mở kỳ.'}
      ${mat.length ? `<br><strong>⚠ ${mat.length} dòng mất liên kết</strong> (cơ sở không còn bên Nhân sự): ${esc(mat.map((x) => x.name).join(', '))} — số cũ được giữ nguyên.` : ''}
      ${lve0.length ? `<br><strong>${lve0.length} dòng đưa về 0</strong> vì kỳ này Nhân sự chưa có số liệu (số cũ là của kỳ trước):
        ${esc(lve0.map((x) => x.name).join(', '))}.` : ''}
      ${chuaGia.length ? `<br><strong>⚠ ${chuaGia.length} dòng kỳ này chưa khai giá giờ</strong>: ${esc(chuaGia.map((x) => x.name).join(', '))} — <strong>giữ số cũ</strong>, em không ghi 0 đè lên.` : ''}
      ${chuaNoi.length ? `<br><strong>⚠ ${fmt(luongLan.tongChuaNoi)} lương bên Nhân sự chưa nối vào dòng nào</strong> (${chuaNoi.length} cơ sở: ${esc(chuaNoi.slice(0, 6).map((x) => x.cua_hang).join(', '))}${chuaNoi.length > 6 ? '…' : ''}) — chỗ này KHÔNG vào báo cáo, bấm "⬇ Nạp lương từ Nhân sự" để ghép.` : ''}
      </span></div>`;
  }

  async function luongLayNgay(im) {
    if (!state.options.luongTuDong || !API.isEnabled() || sync.locked) return;
    if (!(state.salarySites || []).some((r) => (r.nsTen || '').trim())) return;
    try {
      const r = await API.call('nsLuong', { thang: state.period.month, nam: state.period.year });
      const d = r && r.data ? r.data : r;
      if (!d || d.ok === false || !d.ds) return;
      const kq = E.dongBoLuong(state, d.ds);
      luongLan = { luc: new Date().toLocaleTimeString('vi-VN'), soDoi: kq.soDoi, mat: kq.mat,
        chuaGia: kq.chuaGia, chuaNoi: kq.chuaNoi, tongChuaNoi: kq.tongChuaNoi, ve0: kq.veKhong };
      if (kq.soDoi || kq.veKhong.length) { commit(); if (!im) toast(`🔗 Cập nhật ${kq.soDoi} dòng lương từ Nhân sự.` + (kq.veKhong.length ? ` ${kq.veKhong.length} dòng về 0 vì kỳ này chưa có số.` : '')); }
      else { recompute(); renderTab(); }
    } catch (e) { /* mạng hỏng thì thôi, số cũ vẫn còn */ }
  }

  /** Bộ phận mặc định cho dòng lương: suy từ các dòng ĐANG NỐI trang Nhân sự. */
  function bpLuongMacDinh() {
    const dem = {};
    (state.salarySites || []).forEach((r) => {
      if ((r.nsTen || '').trim() && r.dept) { dem[r.dept] = (dem[r.dept] || 0) + 1; }
    });
    let bp = '', n = 0;
    Object.keys(dem).forEach((d) => { if (dem[d] > n) { n = dem[d]; bp = d; } });
    return bp;
  }

  /** Chọn sẵn "tạo dòng mới" cho mọi cơ sở CÓ TIỀN mà chưa ghép. */
  function layHetLuongVaoNhap() {
    if (!luong || !luong.ghep) return { n: 0, khong: 0 };
    const md = bpLuongMacDinh();
    let n = 0, khong = 0;
    luong.ghep.forEach((g) => {
      /* Cơ sở chưa ra tiền thì KHÔNG tạo dòng — tạo ra một dòng lương 0 đồng là đúng cái sai
         "ghi 0" chỉ khác chỗ nó nằm ở danh mục thay vì ở con số. */
      if (g.co_luong === false || g.rowIndex !== null || g.taoMoi) return;
      let d = E.doanBoPhan(g.cua_hang, state.departments);
      if (!d && (g.bo_phan || '').trim() && state.departments.some((x) => x.id === g.bo_phan)) { d = g.bo_phan; }
      if (!d && md) { d = md; }
      if (d) { g.taoMoi = d; g.cach = 'tao'; n++; } else { khong++; }
    });
    return { n, khong };
  }

  async function napTuNhanSu() {
    if (!API.isEnabled()) return toast('Chức năng này cần đăng nhập vào máy chủ (bản chạy trên WordPress).');
    luong = { dangTai: true };
    renderTab();
    try {
      const r = await API.call('nsLuong', { thang: state.period.month, nam: state.period.year });
      const d = r && r.data ? r.data : r;
      if (!d || d.ok === false) { luong = { loi: (d && d.error) || 'Không đọc được lương từ trang Nhân sự.' }; renderTab(); return; }
      if (!d.ds || !d.ds.length) { luong = { loi: `Kỳ ${R_periodLabel()} chưa có dữ liệu chấm công nào bên Nhân sự.` }; renderTab(); return; }
      luong = { thang: d.thang, ghep: E.ghepLuong(state, d.ds) };
      /* 🔴 NẠP XONG LÀ GHÉP HẾT. Cùng lối với doanh thu — anh Thắng 18/09/2026: *"Dữ liệu lấy
         realtime + nạp"*. Cơ sở nào CÓ tiền mà chưa có dòng lương thì chọn sẵn "tạo dòng mới";
         cơ sở chưa ra tiền thì vẫn đứng ngoài, không tạo dòng rỗng. Vẫn phải bấm Ghi. */
      layHetLuongVaoNhap();
      renderTab();
    } catch (e) {
      luong = { loi: 'Lỗi khi gọi máy chủ: ' + e.message };
      renderTab();
    }
  }

  // ------------------------------------------------------------------ Tab: Kiểm tra
  function renderCheck(root) {
    const R = report;
    const lv = { error: 'LỖI', warn: 'CẢNH BÁO', info: 'Ghi chú', ok: 'OK' };
    const order = { error: 0, warn: 1, info: 2, ok: 3 };
    const list = [...issues].sort((a, b) => order[a.level] - order[b.level]);
    // đối chiếu bảo toàn tổng
    const recon = state.costItems.map((it) => {
      const a = R.itemAlloc[it.id] || {};
      const s = Object.values(a).reduce((x, y) => x + y, 0);
      const shares = E.itemShares(state, it);
      const expected = state.groups.reduce((acc, g) => {
        // nhóm không có bộ phận đủ điều kiện → phần đó không phân bổ được
        const w = E.groupWeights(state, g.id, it.excludeDepts, it.method);
        return acc + (w.sum > 0 ? shares[g.id] || 0 : 0);
      }, 0);
      return { it, total: E.num(it.total), allocated: s, expected, ok: Math.abs(s - E.num(it.total)) < 0.5 };
    });
    const badRecon = recon.filter((r) => !r.ok);
    root.innerHTML = `
      <div class="grid">
        <div class="card">
          <div class="card-head"><h2>Kết quả kiểm tra dữ liệu</h2><span class="hint">${issues.filter((i) => i.level === 'error').length} lỗi · ${issues.filter((i) => i.level === 'warn').length} cảnh báo · ${issues.filter((i) => i.level === 'info').length} ghi chú</span></div>
          ${list.length ? list.map((i) => `<div class="issue ${i.level}"><span class="lv">${lv[i.level]}</span><span>${esc(i.msg)}</span></div>`).join('') : `<div class="issue ok"><span class="lv">OK</span><span>Không phát hiện vấn đề nào.</span></div>`}
        </div>
        <div class="card">
          <div class="card-head"><h2>Bảo toàn tổng tiền</h2><span class="hint">Tổng phân bổ về các bộ phận phải bằng tổng tiền từng khoản.</span></div>
          <p>${badRecon.length ? `<span class="bad-text">${badRecon.length} khoản không khớp</span>` : `<span class="ok-text">Tất cả ${recon.length} khoản đều khớp.</span>`}
          &nbsp; Tổng khoản: <strong>${fmt(recon.reduce((a, r) => a + r.total, 0))}</strong> · Đã phân bổ: <strong>${fmt(R.grandTotal)}</strong></p>
          <div class="table-wrap" style="max-height:50vh"><table class="grid-table dense">
            <thead><tr><th>Khoản</th><th class="num">Tổng tiền</th><th class="num">Đã phân bổ</th><th class="num">Lệch</th><th></th></tr></thead>
            <tbody>${recon.map((r) => `<tr><td class="wrap">${esc(r.it.name)}</td>${tdn(r.total)}${tdn(r.allocated)}${tdn(r.allocated - r.total)}<td class="center">${r.ok ? '<span class="ok-text">✓</span>' : '<span class="bad-text">✗</span>'}</td></tr>`).join('')}</tbody>
          </table></div>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h2>Đối chiếu doanh thu</h2></div>
        <div class="table-wrap"><table class="grid-table dense">
          <thead><tr><th>Bộ phận</th><th class="num">Tổng doanh thu các điểm</th><th class="num">Doanh thu áp dụng</th><th>Nguồn</th><th class="num">Số điểm có DT = 0</th></tr></thead>
          <tbody>${state.departments
            .map((d) => {
              const sites = state.sites.filter((s) => s.dept === d.id);
              const sum = sites.reduce((a, s) => a + E.num(s.revenue), 0);
              const zero = sites.filter((s) => E.num(s.revenue) === 0).length;
              return `<tr><td>${esc(d.name)}</td>${tdn(sum)}${tdn(R.revenue[d.id])}<td class="muted">${d.revenueOverride ? 'Nhập tay' : sites.length ? `Cộng ${sites.length} điểm` : 'Nhập tay (chưa có điểm)'}</td><td class="num ${zero ? 'muted' : ''}">${zero}</td></tr>`;
            })
            .join('')}</tbody>
        </table></div>
      </div>`;
  }

  // ------------------------------------------------------------------ Điều phối tab
  // ------------------------------------------------------------------ Tab: Nhật ký (bản WordPress)
  function renderLog(root) {
    root.innerHTML = `<div class="card"><div class="card-head"><h2>Nhật ký hoạt động</h2><span class="hint">Ai làm gì, lúc nào — 200 dòng gần nhất.</span><div class="spacer"></div><button class="btn small" data-act="reloadLog">↻ Tải lại</button></div><div id="logBox" class="table-wrap tall"><div class="empty">Đang tải…</div></div></div>`;
    ACTIONS.reloadLog();
  }
  const RENDERERS = { dashboard: renderDashboard, revenue: renderRevenue, costs: renderCosts, salary: renderSalary, report: renderReport, sites: renderSites, misa: renderMisa, check: renderCheck, log: renderLog };
  function renderTab(tab) {
    if (tab) ui.tab = tab;
    if (!RENDERERS[ui.tab]) ui.tab = 'dashboard';
    $$('#tabs button').forEach((b) => b.classList.toggle('active', b.dataset.tab === ui.tab));
    $$('.tab').forEach((s) => s.classList.toggle('active', s.id === `tab-${ui.tab}`));
    const root = $(`#tab-${ui.tab}`);
    // giữ vị trí cuộn & ô đang focus
    const focusPath = document.activeElement && document.activeElement.dataset ? document.activeElement.dataset.path : null;
    const scrolls = $$('.table-wrap', root).map((w) => [w.scrollLeft, w.scrollTop]);
    RENDERERS[ui.tab](root);
    $$('.table-wrap', root).forEach((w, i) => {
      if (scrolls[i]) {
        w.scrollLeft = scrolls[i][0];
        w.scrollTop = scrolls[i][1];
      }
    });
    if (focusPath) {
      const el = $(`[data-path="${focusPath}"]`, root);
      if (el && el.dataset.type !== 'toggle') el.focus({ preventScroll: true });
    }
    saveState();
  }

  // ------------------------------------------------------------------ Sự kiện nhập liệu
  function onFieldChange(el) {
    const path = el.dataset.path;
    const type = el.dataset.type;
    if (!path) return;
    let val;
    if (type === 'num') val = E.num(el.value);
    else if (type === 'pct') val = E.num(el.value) / 100;
    else if (type === 'bool') val = el.checked;
    else if (type === 'toggle') {
      const arr = getPath(state, path) || [];
      const v = el.dataset.value;
      val = el.checked ? [...new Set([...arr, v])] : arr.filter((x) => x !== v);
    } else val = el.value;
    if (type === 'text' && /\.(stt)$/.test(path)) val = /^\d+$/.test(String(val).trim()) ? +val : val;
    setPath(state, path, val);
    // đổi mã nhóm/bộ phận không cho phép qua UI; đổi kiểu chia → dựng shares mặc định cho custom
    if (/^costItems\.\d+\.split$/.test(path) && val === 'custom') {
      const idx = +path.split('.')[1];
      const it = state.costItems[idx];
      if (!it.shares || !Object.keys(it.shares).length) {
        const n = state.groups.length || 1;
        it.shares = {};
        state.groups.forEach((g) => (it.shares[g.id] = E.num(it.total) / n));
      }
    }
    setTimeout(() => commit(), 0);
  }

  function move(arr, i, delta) {
    const j = i + delta;
    if (j < 0 || j >= arr.length) return;
    const [x] = arr.splice(i, 1);
    arr.splice(j, 0, x);
  }

  const ACTIONS = {
    // nhóm / bộ phận
    addGroup() {
      const id = prompt('Mã nhóm (viết liền, không dấu, VD: KHAC):', '');
      if (!id) return;
      const gid = id.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '');
      if (!gid || state.groups.find((g) => g.id === gid)) return toast('Mã nhóm trống hoặc đã tồn tại.');
      state.groups.push({ id: gid, name: `Nhóm ${gid}`, method: 'revenue' });
      commit();
    },
    delGroup(i) {
      const g = state.groups[i];
      if (state.departments.some((d) => d.group === g.id)) return toast(`Nhóm "${g.name}" còn bộ phận, hãy chuyển bộ phận sang nhóm khác trước.`);
      if (!confirm(`Xoá nhóm "${g.name}"?`)) return;
      state.groups.splice(i, 1);
      commit();
    },
    addDept() {
      const name = prompt('Tên bộ phận mới:', '');
      if (!name) return;
      let id = IMP.norm(name).replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || `bp${Date.now()}`;
      while (state.departments.find((d) => d.id === id)) id += '_';
      state.departments.push({ id, name: name.trim(), group: (state.groups[state.groups.length - 1] || {}).id || '', ratio: 0, revenue: 0, revenueOverride: false, unitCode: '' });
      state.salaryDept.push({ dept: id, misaGeneral: '', misaDetail: '' });
      commit();
    },
    delDept(i) {
      const d = state.departments[i];
      const n = state.sites.filter((s) => s.dept === d.id).length + state.salarySites.filter((s) => s.dept === d.id).length;
      if (!confirm(`Xoá bộ phận "${d.name}"${n ? ` cùng ${n} dòng điểm/lương liên quan` : ''}?`)) return;
      state.departments.splice(i, 1);
      state.sites = state.sites.filter((s) => s.dept !== d.id);
      state.salarySites = state.salarySites.filter((s) => s.dept !== d.id);
      state.salaryDept = state.salaryDept.filter((s) => s.dept !== d.id);
      state.costItems.forEach((it) => (it.excludeDepts = (it.excludeDepts || []).filter((x) => x !== d.id)));
      commit();
    },
    moveDept(arg) {
      const [i, dlt] = arg.split(':').map(Number);
      move(state.departments, i, dlt);
      commit();
    },
    // điểm
    addSite() {
      const dept = ui.siteFilter !== 'all' ? ui.siteFilter : (state.departments[0] || {}).id;
      state.sites.push({ dept, code: '', name: '', revenue: 0 });
      commit();
      const last = $$(`[data-path^="sites.${state.sites.length - 1}."]`)[1];
      if (last) last.focus();
    },
    delSite(i) {
      state.sites.splice(i, 1);
      commit();
    },
    zeroSites() {
      const f = ui.siteFilter;
      if (!confirm('Đưa doanh thu các điểm đang hiển thị về 0?')) return;
      state.sites.forEach((s) => {
        if (f === 'all' || s.dept === f) s.revenue = 0;
      });
      commit();
    },
    togglePaste() {
      const box = $('#pasteBox');
      box.hidden = !box.hidden;
      if (!box.hidden) $('#pasteArea').focus();
    },
    applyPaste() {
      const text = $('#pasteArea').value;
      const defDept = $('#pasteDept').value;
      let added = 0, updated = 0;
      text.split(/\r?\n/).forEach((line) => {
        if (!line.trim()) return;
        const parts = line.split(/\t|;|\|/).map((x) => x.trim());
        if (parts.length < 2) return;
        let [code, name, rev, dep] = parts;
        if (parts.length === 2) { rev = name; name = code; }
        let dept = defDept;
        if (dep) {
          const d = state.departments.find((x) => IMP.norm(x.name) === IMP.norm(dep) || x.id === dep);
          if (d) dept = d.id;
        }
        const ex = code ? state.sites.find((s) => s.code === code) : null;
        if (ex) { ex.revenue = E.num(rev); if (name) ex.name = name; updated++; }
        else { state.sites.push({ dept, code, name, revenue: E.num(rev) }); added++; }
      });
      toast(`Đã thêm ${added} điểm, cập nhật ${updated} điểm.`);
      commit();
    },
    // khoản chi phí
    costFilter(v) {
      ui.costFilter = v;
      renderTab();
    },
    addItem() {
      state.costItems.push({ id: E.newId('ci'), kind: 'company', name: '', misaGeneral: '', misaDetail: '', account: '', total: 0, split: 'equal', shares: {}, objectCode: '', excludeDepts: [], groupKey: '', method: '', note: '', status: 'da_duyet', createdBy: sync.on ? API.getConfig().user : '', createdAt: new Date().toISOString() });
      ui.costFilter = 'all';
      commit();
      const el = $(`[data-path="costItems.${state.costItems.length - 1}.name"]`);
      if (el) { el.focus(); el.scrollIntoView({ block: 'center' }); }
    },
    dupItem(i) {
      const c = JSON.parse(JSON.stringify(state.costItems[i]));
      c.id = E.newId('ci');
      state.costItems.splice(i + 1, 0, c);
      commit();
    },
    delItem(i) {
      if (!confirm(`Xoá khoản "${state.costItems[i].name || '#' + (i + 1)}"?`)) return;
      state.costItems.splice(i, 1);
      commit();
    },
    moveItem(arg) {
      const [i, dlt] = arg.split(':').map(Number);
      move(state.costItems, i, dlt);
      commit();
    },
    addManual() {
      state.manualCols.push({ id: E.newId('mc'), name: 'Cột mới', account: '', values: {} });
      commit();
    },
    delManual(i) {
      if (!confirm(`Xoá cột "${state.manualCols[i].name}"?`)) return;
      state.manualCols.splice(i, 1);
      commit();
    },
    // lương
    addSalarySite() {
      const dept = (state.departments[0] || {}).id;
      const last = state.salarySites[state.salarySites.length - 1];
      state.salarySites.push({ id: E.newId('ss'), dept: last ? last.dept : dept, groupTitle: last ? last.groupTitle : '', stt: '', name: '', reported: 0, report: 0, dntt: 0, actual: 0, unitCode: '', misaGeneral: '', misaDetail: '' });
      commit();
    },
    delSalarySite(i) {
      state.salarySites.splice(i, 1);
      commit();
    },
    moveSalary(arg) {
      const [i, dlt] = arg.split(':').map(Number);
      move(state.salarySites, i, dlt);
      commit();
    },
    syncSalary() {
      state.salarySites.forEach((r) => { r.report = E.num(r.reported); r.dntt = E.num(r.reported); });
      commit();
    },
    approveAll() {
      const n = state.costItems.filter((it) => it.status === 'cho_duyet').length;
      if (!n || !confirm(`Duyệt ${n} khoản đang chờ?`)) return;
      state.costItems.forEach((it) => { if (it.status === 'cho_duyet') { it.status = 'da_duyet'; it.approvedBy = API.getConfig().user || 'Kế toán'; } });
      commit();
    },
    syncNow() { sync.pull({ force: true, notify: true }); },
    async lockPeriod() {
      const lock = !sync.locked;
      if (!confirm(lock ? `Chốt kỳ ${E.periodLabel(state.period)}? Sau khi chốt, nhân viên và kế toán không thêm/sửa khoản hay số liệu được nữa (Admin mở lại được).` : `Mở lại kỳ ${E.periodLabel(state.period)} để sửa?`)) return;
      try {
        await API.call('lockPeriod', { period: API.periodKey(state.period), locked: lock });
        sync.locked = lock;
        toast(lock ? 'Đã chốt kỳ.' : 'Đã mở lại kỳ.');
        renderTab();
      } catch (e) { toast('Lỗi: ' + e.message); }
    },
    async reloadLog() {
      const box = $('#logBox');
      if (!box) return;
      try {
        const r = await API.call('getLog', { limit: 200 });
        const rows = r.items || [];
        box.innerHTML = rows.length ? `<table class="grid-table dense log-table"><thead><tr><th>Lúc</th><th>Người</th><th>Vai</th><th>Hành động</th><th>Chi tiết</th></tr></thead><tbody>${rows.map((x) => `<tr><td class="muted">${esc(x.luc ? new Date(x.luc).toLocaleString('vi-VN') : '')}</td><td>${esc(x.nguoi)}</td><td class="muted">${esc(x.vai)}</td><td><code>${esc(x.hanhDong)}</code></td><td class="wrap">${esc(x.chiTiet)}</td></tr>`).join('')}</tbody></table>` : '<div class="empty">Chưa có gì.</div>';
      } catch (e) { box.innerHTML = `<div class="issue error"><span class="lv">LỖI</span><span>${esc(e.message)}</span></div>`; }
    },
    // khác
    sitesDept(v) {
      ui.sitesDept = v;
      renderTab();
    },
    /* Mở/gập một chứng từ ở tab Chi tiết MISA. Nhớ theo SỐ CHỨNG TỪ chứ không theo vị trí: thêm
       bớt một khoản chi phí là mọi vị trí xê dịch, mà cái đang mở thì nhảy sang chứng từ khác. */
    misaMo(v) {
      const ds = ui.misaMo || [];
      ui.misaMo = ds.includes(v) ? ds.filter((x) => x !== v) : ds.concat([v]);
      renderTab();
    },
    misaMoHet() {
      const kq = E.misaRows(state, report, ui.sitesDept);
      const tat = kq ? [...new Set(kq.rows.map((r) => r.soCT))] : [];
      ui.misaMo = (ui.misaMo || []).length >= tat.length ? [] : tat;
      renderTab();
    },
    misaThieu() {
      ui.misaThieu = !ui.misaThieu;
      renderTab();
    },
    misa50() {
      ui.misa50 = !ui.misa50;
      renderTab();
    },
    napFabi(nguon) { napTuFabi(nguon); },
    fabiTuDong() {
      state.options.fabiTuDong = !state.options.fabiTuDong;
      commit();
      if (state.options.fabiTuDong) { toast('Đã bật tự lấy doanh thu từ FABi.'); fabiLayNgay(); }
      else { toast('Đã tắt tự lấy — doanh thu giữ nguyên, gõ tay được.'); }
    },
    goLienKet(i) {
      const s = state.sites[Number(i)];
      if (!s || !confirm(`Gỡ liên kết của "${s.name || s.code}" với cửa hàng FABi "${s.fabiTen}"?\n\nDoanh thu giữ nguyên số hiện tại, từ nay gõ tay.`)) return;
      s.fabiTen = '';
      commit();
    },
    fabiDong() { fabi = null; renderTab(); },
    moDoanhThu() { renderTab('revenue'); },
    /* Gỡ những liên kết trỏ vào điểm sai bộ phận, VÀ xoá luôn con số chúng đã ghi vào đó. Giữ số
       lại mới là nguy: đó là tiền ghế nằm trong doanh thu Event, không nguồn nào nhận, không ai
       biết nó từ đâu ra, mà vẫn kéo lệch tỷ trọng phân bổ chi phí của cả hai bộ phận. */
    goSaiBp() {
      const sai = diemSaiBp();
      if (!sai.length) return;
      if (!confirm(`Gỡ ${sai.length} liên kết sai bộ phận?\n\n`
        + sai.map((x) => `· ${x.name || x.code} (${deptName(x.dept)}) ← ${x.fabiTen}`).join('\n')
        + `\n\nDoanh thu đã ghi nhầm vào mấy điểm này sẽ được ĐƯA VỀ 0 — đó là tiền ghế, không phải `
        + `doanh thu của bộ phận ấy. Sau đó bấm "Nạp từ Ghế" để tạo điểm đúng bên Posh / JP.`)) return;
      prevState = JSON.parse(JSON.stringify(state));
      sai.forEach((x) => { const s = state.sites[x.i]; if (!s) return; s[x.nguon] = ''; s.revenue = 0; });
      commit();
      toast(`Đã gỡ ${sai.length} liên kết sai bộ phận và xoá số ghi nhầm.`, { label: 'Hoàn tác', fn: undo });
    },
    /* "Cứ lấy hết cơ sở đó là được" — mở nguồn, chọn sẵn tạo mới cho mọi cửa hàng chưa ghép, rồi
       DỪNG Ở BẢN XEM TRƯỚC. Vẫn phải bấm Ghi: đây là thứ tạo ra điểm bán mới trong sổ, không phải
       thứ nên tự chạy sau lưng. */
    async layHetNguon(nguon) {
      renderTab('revenue');
      await napTuFabi(nguon);
    },
    /* Hai nút của dòng "SAI KỲ" — một đường dựng kỳ mới, một đường quay lại. */
    dungKyMoi() { xoaSoKyNay(); },
    veKyCu() {
      const cu = E.lechKy(state);
      if (!cu) return;
      const [y, m] = cu.split('-');
      state.period = { month: +m, year: +y };
      $('#selMonth').value = String(+m);
      $('#inpYear').value = String(+y);
      commit({ noPush: true });
      sync.onPeriodChange();
      toast(`Đã quay về kỳ ${E.periodLabel(state.period)}.`);
    },
    /* ---------------- Lương từ trang Nhân sự ---------------- */
    napLuong() { napTuNhanSu(); },
    /* 🔧 Chẩn đoán: in ra đúng cấu trúc dữ liệu bên Chấm công cho một cơ sở, để sửa cho trúng chỗ
       để tiền thay vì đoán. Chỉ in SỐ — họ tên và CCCD của nhân viên bị giấu ngay từ máy chủ. */
    /* 🔍 Khám: in ra lớp / hàm / bảng của plugin Chấm công. `bang_cong_va_luong()` với Khu vui
       chơi chỉ trả giờ vào/ra thô, không có tiền — nên phải tìm ĐÚNG hàm tính lương của họ mà gọi,
       chứ không tự cộng giờ rồi nhân đơn giá ở bên này. */
    async nsKham() {
      if (!API.isEnabled()) return toast('Chức năng này cần đăng nhập vào máy chủ.');
      try {
        const r = await API.call('nsKham', {});
        const d = r && r.data ? r.data : r;
        if (!d || d.ok === false) return toast('Không đọc được: ' + ((d && d.error) || '?'));
        const log = [{ level: 'info', msg: d.ghi_chu }];
        (d.lop || []).forEach((x) => {
          log.push({ level: 'warn', msg: `class ${x.lop} — ${x.ham.length} hàm` });
          x.ham.forEach((h) => log.push({ level: 'ok', msg: `  ${x.lop}${h}` }));
        });
        (d.bang || []).forEach((x) => log.push({ level: 'info', msg: `bảng ${x.bang} — ${x.so_dong} dòng` }));
        showLog('Lớp / hàm / bảng của plugin Chấm công', log);
      } catch (e) { toast('Lỗi: ' + e.message); }
    },
    async nsChuanDoan(coso) {
      if (!API.isEnabled()) return toast('Chức năng này cần đăng nhập vào máy chủ.');
      try {
        const r = await API.call('nsChuanDoan', { coso, thang: state.period.month, nam: state.period.year });
        const d = r && r.data ? r.data : r;
        if (!d || d.ok === false) return toast('Không đọc được: ' + ((d && d.error) || '?'));
        const log = [{ level: 'info', msg: `${d.coso} · ${d.thang} · ${d.ghi_chu}` }]
          .concat((d.so || []).map((x) => ({ level: 'ok', msg: `${x.duong} = ${x.gia_tri}` })))
          .concat((d.khoa || []).map((x) => ({ level: 'warn', msg: `${x.duong} = ${x.gia_tri}` })));
        showLog(`Cấu trúc dữ liệu Nhân sự — ${coso}`, log);
      } catch (e) { toast('Lỗi: ' + e.message); }
    },
    luongDong() { luong = null; renderTab(); },
    luongTuDong() {
      state.options.luongTuDong = !state.options.luongTuDong;
      commit();
      if (state.options.luongTuDong) { toast('Đã bật tự lấy lương từ trang Nhân sự.'); luongLayNgay(); }
      else { toast('Đã tắt tự lấy — lương giữ nguyên, gõ tay được.'); }
    },
    goLienKetLuong(i) {
      const r = state.salarySites[Number(i)];
      if (!r || !confirm(`Gỡ liên kết của "${r.name}" với cơ sở "${r.nsTen}" bên Nhân sự?\n\nLương giữ nguyên số hiện tại, từ nay gõ tay.`)) return;
      r.nsTen = '';
      commit();
    },
    luongTaoHet() {
      const r = layHetLuongVaoNhap();
      renderTab();
      toast(`Đã chọn tạo mới ${r.n} dòng lương.` + (r.khong ? ` ${r.khong} cơ sở không đoán được bộ phận — tự chọn giúp em.` : ''));
    },
    luongGhi() {
      if (!luong || !luong.ghep) return;
      const kq = E.napLuong(state, luong.ghep);
      luong = null;
      const bat = !state.options.luongTuDong && kq.xong > 0;
      if (bat) state.options.luongTuDong = true;
      commit();
      luongLayNgay(true);
      toast(`Đã ghi ${kq.xong} dòng lương từ Nhân sự`
        + (kq.tao ? ` · tạo mới ${kq.tao} dòng` : '')
        + (kq.boQua ? ` · bỏ qua ${kq.boQua} cơ sở chưa ghép` : '')
        + (kq.chuaGia ? ` · ${kq.chuaGia} cơ sở CHƯA KHAI GIÁ GIỜ nên không ghi (không ghi 0)` : '') + '.'
        + (bat ? ' Từ nay số tự lấy, khỏi bấm.' : '')
        + (kq.tao ? ' Nhớ điền Mã đơn vị cho dòng mới.' : ''));
    },
    fabiTaoHet() {
      const r = layHetVaoNhap();
      renderTab();
      toast(`Đã chọn tạo mới ${r.n} điểm` + (r.theoMd ? ` (${r.theoMd} theo bộ phận ${deptName(r.md)})` : '') + '.'
        + (r.khong ? ` ${r.khong} cửa hàng chưa có bộ phận — chọn "Bộ phận mặc định" rồi bấm lại.` : ''));
    },
    fabiGhi() {
      if (!fabi || !fabi.ghep) return;
      /* Tạo điểm mới TRƯỚC, vì napFabi ghi theo siteIndex — tạo sau thì mấy điểm mới không nhận
         được đồng doanh thu nào. */
      const nguonCu = fabi.nguon || 'fabi';
      const khoa = (NGUON[nguonCu] || NGUON.fabi).khoa;
      const t = E.taoDiemTuFabi(state, fabi.ghep, khoa);
      const kq = E.napFabi(state, fabi.ghep, khoa);
      /* Nhớ danh sách BỎ HẲN của nguồn này. Anh Thắng: báo cáo đang làm là MN, mà trang Ghế liệt
         kê cơ sở cả nước — không nhớ thì mỗi kỳ lại phải bỏ lại từng cái một, và dòng THIẾU LIÊN
         KẾT kêu suốt đời cho những cơ sở vốn không thuộc báo cáo này. */
      const bqCu = E.dsBoQua(state, khoa);
      const them = fabi.ghep.filter((g) => g.boHan).map((g) => String(g.cua_hang || '').trim());
      const boRa = fabi.ghep.filter((g) => !g.boHan).map((g) => String(g.cua_hang || '').trim());
      state.options.boQuaNguon = Object.assign({}, state.options.boQuaNguon);
      state.options.boQuaNguon[khoa] = bqCu.filter((x) => boRa.indexOf(x) < 0)
        .concat(them.filter((x) => x && bqCu.indexOf(x) < 0));
      const soBoHan = them.length;
      fabi = null;
      /* Ghi xong là đã có liên kết — bật luôn tự lấy, đó chính là thứ anh Thắng muốn ("tự link
         và lấy realtime"). Vẫn tắt được bằng nút trên thanh công cụ. */
      const bat = !state.options.fabiTuDong && kq.xong > 0;
      if (bat) state.options.fabiTuDong = true;
      commit();
      /* Chạy luôn một lượt lấy sống: vừa xác nhận liên kết chạy được, vừa đếm ngay phần tiền bên
         nguồn CHƯA nối vào điểm nào để Tổng quan báo. Đợi tới lần đổi kỳ sau mới đếm thì người ta
         đã đóng máy đi rồi, mà báo cáo thì đang thiếu tiền. */
      fabiLayNgay(true);
      toast(`Đã nối ${kq.xong} điểm với ${(NGUON[nguonCu] || NGUON.fabi).ten}`
        + (t.tao.length ? ` · tạo mới ${t.tao.length} điểm` : '')
        + (kq.boQua ? ` · bỏ qua ${kq.boQua} cửa hàng chưa ghép` : '')
        + (soBoHan ? ` · bỏ hẳn ${soBoHan} cơ sở (không hỏi lại)` : '') + '.'
        + (bat ? ' Từ nay số tự lấy, khỏi bấm.' : '')
        + (t.tao.length ? ' Nhớ điền Mã đơn vị cho điểm mới.' : ''));
    },
    /* 🔴 XUẤT RIÊNG TỜ NHẬP MISA. File tổng có 17 sheet và sheet ĐẦU là "File tổng báo cáo" —
       bố cục khác hẳn; đưa nguyên file ấy cho MISA là nó đọc trúng sheet đầu rồi báo sai cột, dù
       mấy sheet "<Bộ phận> chi tiết" bên trong hoàn toàn đúng. Hai nút này cho ra file CHỈ có tờ
       nhập, mở lên là đúng thứ cần nhập. */
    xuatMisa() { xuatFileMisa(ui.sitesDept); },
    xuatMisaHet() { xuatFileMisa(''); },
    export: exportExcel,
    print() { renderTab('report'); setTimeout(() => window.print(), 100); },
    copyReport: copyReport,
  };

  // ------------------------------------------------------------------ Nhập / xuất
  function exportExcel() {
    try {
      const wb = EXP.buildWorkbook(XLSX, state, report);
      XLSX.writeFile(wb, EXP.fileName(state));
      toast('Đã tạo file Excel: ' + EXP.fileName(state));
    } catch (e) {
      console.error(e);
      toast('Xuất Excel lỗi: ' + e.message);
    }
  }

  function xuatFileMisa(deptId) {
    try {
      const wb = EXP.buildMisaWorkbook(XLSX, state, report, deptId);
      if (!wb) return toast('Chưa có dòng nào để xuất — bộ phận này chưa có điểm bán nhận chi phí.');
      const d = deptId ? state.departments.find((x) => x.id === deptId) : null;
      const ten = EXP.misaFileName(state, d);
      XLSX.writeFile(wb, ten);
      toast('Đã tạo tờ nhập MISA: ' + ten);
    } catch (e) {
      console.error(e);
      toast('Xuất tờ MISA lỗi: ' + e.message);
    }
  }

  function copyReport() {
    const R = report;
    const lines = [];
    lines.push(['Bộ Phận', ...R.columns.map((c) => c.title), ...R.manualCols.map((m) => m.name), 'Tổng'].join('\t'));
    R.departments.forEach((d) => {
      lines.push([d.name, ...R.columns.map((c) => Math.round(R.matrix[d.id][c.key])), ...R.manualCols.map((m) => Math.round(m.vals[d.id] || 0)), Math.round(R.rowTotals[d.id] + R.manualCols.reduce((a, m) => a + (m.vals[d.id] || 0), 0))].join('\t'));
    });
    lines.push(['Tổng cộng', ...R.columns.map((c) => Math.round(R.colTotals[c.key])), ...R.manualCols.map((m) => Math.round(m.total)), Math.round(R.grandTotal + R.manualCols.reduce((a, m) => a + m.total, 0))].join('\t'));
    const text = lines.join('\n');
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject())
      .then(() => toast('Đã sao chép Mục I — dán thẳng vào Excel.'))
      .catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        toast('Đã sao chép Mục I.');
      });
  }

  function importExcelFile(file) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      try {
        const res = IMP.importWorkbook(XLSX, new Uint8Array(ev.target.result), state);
        prevState = JSON.parse(JSON.stringify(state));
        const keep = sync.on ? state.costItems.filter((it) => it.createdBy && it.createdBy !== API.getConfig().user) : [];
        state = res.state;
        state.costItems.forEach((it) => { it.status = 'da_duyet'; it.createdBy = sync.on ? API.getConfig().user : ''; it.createdAt = new Date().toISOString(); });
        if (keep.length) { state.costItems = state.costItems.concat(keep); res.log.push({ level: 'info', msg: `Giữ lại ${keep.length} khoản do người khác nhập trên máy chủ.` }); }
        commit({ tab: 'dashboard' });
        showLog(`Đã nhập "${file.name}"`, res.log, res.sheetNames);
      } catch (e) {
        console.error(e);
        showLog(`Không đọc được "${file.name}"`, [{ level: 'error', msg: e.message }], []);
      }
    };
    reader.readAsArrayBuffer(file);
  }

  function importJsonFile(file) {
    const reader = new FileReader();
    reader.onload = (ev) => {
      try {
        const data = JSON.parse(ev.target.result);
        if (!data || !data.departments) throw new Error('File không đúng định dạng cấu hình của ứng dụng.');
        prevState = JSON.parse(JSON.stringify(state));
        state = E.normalizeState(data);
        commit({ tab: 'dashboard' });
        toast(`Đã mở cấu hình "${file.name}".`, { label: 'Hoàn tác', fn: undo });
      } catch (e) {
        toast('Không đọc được file: ' + e.message);
      }
    };
    reader.readAsText(file);
  }

  function exportJson() {
    const blob = new Blob([JSON.stringify(state, null, 1)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `bao_cao_chi_phi_${E.periodShort(state.period)}.json`;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
  }

  function undo() {
    if (!prevState) return;
    state = prevState;
    prevState = null;
    commit();
    toast('Đã hoàn tác, giữ dữ liệu cũ.');
  }

  function loadSample() {
    if (sync.on && !confirm('Đang đồng bộ máy chủ: dữ liệu mẫu sẽ ghi lên máy chủ cho kỳ T8/2026 (khoản do người khác nhập vẫn giữ). Tiếp tục?')) return;
    prevState = JSON.parse(JSON.stringify(state));
    const keep = sync.on ? state.costItems.filter((it) => it.createdBy && it.createdBy !== API.getConfig().user) : [];
    state = E.normalizeState(JSON.parse(JSON.stringify(window.SAMPLE_DATA)));
    state.costItems.forEach((it) => { it.status = 'da_duyet'; it.createdBy = sync.on ? API.getConfig().user : ''; });
    state.costItems = state.costItems.concat(keep);
    commit({ tab: 'dashboard', noPush: true });
    sync.onPeriodChange();
    sync.schedulePush();
    toast('Đã nạp dữ liệu mẫu T8/2026.', { label: 'Hoàn tác', fn: undo });
  }

  function newPeriod() {
    if (!confirm('Tạo kỳ mới: giữ nhóm, bộ phận, danh sách điểm, tên cột nhập tay, mã đơn vị & nội dung MISA; xoá toàn bộ số tiền và khoản chi phí. Tiếp tục?')) return;
    prevState = JSON.parse(JSON.stringify(state));
    const p = state.period;
    const m = p.month === 12 ? 1 : p.month + 1;
    const y = p.month === 12 ? p.year + 1 : p.year;
    /* Xoá số bằng CHÍNH hàm mà đường "đổi sang kỳ chưa có" dùng — hai chỗ cùng một luật thì không
       có cửa nào lệch. Riêng nút này còn DỌN HẲN danh sách khoản chi phí (đúng như câu hỏi ở trên
       hứa), còn đường kia thì giữ danh mục để làm bản gốc cho kỳ mới. */
    const moi = E.batDauKyMoi(state);
    moi.period = { month: m, year: y };
    /* Đổi kỳ SAU khi xoá số nên phải đóng lại dấu, không thì kỳ mới toanh lại tự báo "SAI KỲ". */
    moi.soCuaKy = E.khoaKy(moi.period);
    moi.costItems = [];
    state = moi;
    commit({ tab: 'revenue', noPush: true });
    sync.onPeriodChange();
    toast(`Đã tạo kỳ ${E.periodLabel(state.period)}.`, { label: 'Hoàn tác', fn: undo });
  }

  /* 🔴 DỌN SỐ CỦA CHÍNH KỲ ĐANG MỞ, KHÔNG NHẢY SANG KỲ SAU.
     Anh Thắng 18/09/2026 mở T09 và thấy nguyên số của T08 — vì kỳ ấy đã bị dựng bằng lối chép cả
     số. Nút "Kỳ mới" thì lại NHẢY sang tháng kế, nên không dùng để chữa được. Đây là nút để chữa:
     đứng yên ở kỳ đang mở, giữ danh mục, đưa mọi con số về 0. */
  function xoaSoKyNay() {
    const nhan = E.periodLabel(state.period);
    if (!confirm(`Xoá MỌI SỐ LIỆU của kỳ ${nhan}?\n\n`
      + `Giữ nguyên danh mục: bộ phận, điểm bán, mã đơn vị, tài khoản, nội dung MISA, cách chia, `
      + `liên kết với Doanh thu FABi, tích "không nhận chi phí".\n\n`
      + `Đưa về 0: doanh thu từng điểm, lương, tiền từng khoản chi phí.`)) return;
    prevState = JSON.parse(JSON.stringify(state));
    state = E.batDauKyMoi(state);
    commit({ tab: 'revenue' });
    toast(`Đã xoá số liệu kỳ ${nhan}, giữ nguyên danh mục.`, { label: 'Hoàn tác', fn: undo });
  }

  function resetAll() {
    if (!confirm('Xoá toàn bộ dữ liệu đã lưu trên trình duyệt này? (Nên Lưu file cấu hình .json trước.)' + (sync.on ? '\n\nĐồng bộ máy chủ sẽ được TẮT để không xoá dữ liệu chung.' : ''))) return;
    if (sync.on) { API.setConfig({ enabled: false }); sync.stop(); }
    prevState = JSON.parse(JSON.stringify(state));
    state = E.normalizeState(E.emptyState());
    commit({ tab: 'revenue' });
    toast('Đã xoá dữ liệu.', { label: 'Hoàn tác', fn: undo });
  }

  // ------------------------------------------------------------------ Hộp nhật ký & toast
  function showLog(title, log, sheetNames) {
    $('#logTitle').textContent = title;
    const lv = { ok: 'OK', error: 'LỖI', warn: 'CẢNH BÁO', info: 'Ghi chú' };
    $('#logBody').innerHTML =
      (sheetNames && sheetNames.length ? `<p class="hint">Sheet trong file: ${sheetNames.map((n) => `<span class="chip">${esc(n)}</span>`).join(' ')}</p>` : '') +
      log.map((l) => `<div class="issue ${l.level}"><span class="lv">${lv[l.level] || l.level}</span><span>${esc(l.msg)}</span></div>`).join('') +
      `<p class="hint" style="margin-top:8px">Kiểm tra lại các tab đầu vào, đặc biệt <strong>Chi phí đầu vào</strong> (kiểu chia, loại trừ, gộp cột) và <strong>Lương</strong>.</p>`;
    $('#btnUndoImport').hidden = !prevState;
    $('#logModal').hidden = false;
  }
  let toastTimer = null;
  function toast(msg, action) {
    const t = $('#toast');
    t.innerHTML = `<span>${esc(msg)}</span>${action ? `<button>${esc(action.label)}</button>` : ''}`;
    if (action) $('button', t).onclick = () => { action.fn(); t.hidden = true; };
    t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (t.hidden = true), action ? 8000 : 3500);
  }

  // ------------------------------------------------------------------ Khởi động
  function init() {
    $('#appVersion').textContent = APP_VERSION;
    $('#selMonth').innerHTML = MONTHS.map((m) => `<option value="${m}">${String(m).padStart(2, '0')}</option>`).join('');

    const saved = loadJSON(STORAGE_KEY);
    state = E.normalizeState(saved || JSON.parse(JSON.stringify(window.SAMPLE_DATA)));
    recompute();
    if (!saved) toast('Chào anh! Đang hiển thị dữ liệu mẫu T8/2026 — bấm "Nhập Excel" để nạp file của kỳ mới.');

    // tabs
    $('#tabs').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-tab]');
      if (b) renderTab(b.dataset.tab);
    });
    // kỳ
    $('#selMonth').addEventListener('change', (e) => { state.period.month = +e.target.value; commit({ noPush: true }); sync.onPeriodChange(); });
    $('#inpYear').addEventListener('change', (e) => { state.period.year = +e.target.value || state.period.year; commit({ noPush: true }); sync.onPeriodChange(); });
    // nhập liệu (uỷ quyền)
    const main = $('#main');
    main.addEventListener('change', (e) => {
      const el = e.target;
      /* Đổi điểm ghép cho một cửa hàng FABi. Ghi vào BẢN NHÁP thôi — vẫn phải bấm Ghi. */
      if (el.matches('[data-fabi]')) {
        const k = Number(el.getAttribute('data-fabi'));
        const v = el.value === '' ? null : Number(el.value);
        if (fabi && fabi.ghep && fabi.ghep[k]) {
          const g = fabi.ghep[k];
          const raw = el.value;
          if (raw === 'bo_han') {
            /* Bỏ HẲN: nhớ vào state lúc bấm Ghi, để lần sau khỏi hiện lại. Vẫn chỉ là bản nháp
               tới lúc ấy. */
            g.siteIndex = null; g.taoMoi = ''; g.boHan = true; g.cach = 'bo_han';
          } else if (raw.indexOf('new:') === 0) {
            /* Chưa tạo ngay — chỉ ghi ý định. Tạo thật lúc bấm Ghi, để anh còn đổi ý hoặc Huỷ mà
               không để lại một điểm rác trong danh sách. */
            g.siteIndex = null; g.taoMoi = raw.slice(4); g.boHan = false; g.cach = 'tao';
          } else {
            g.boHan = false;
            g.taoMoi = '';
            /* Một điểm chỉ nhận MỘT cửa hàng: gán cho dòng này thì gỡ khỏi dòng kia, không thì
               hai cửa hàng cùng ghi vào một điểm và cái sau đè mất cái trước. */
            if (v !== null) { fabi.ghep.forEach((o, i) => { if (i !== k && o.siteIndex === v) { o.siteIndex = null; o.cach = 'khong'; } }); }
            g.siteIndex = v;
            g.cach = v === null ? 'khong' : 'tay';
          }
          renderTab();
        }
        return;
      }
      if (el.matches('[data-bpmd]')) {
        state.options.bpMacDinh = Object.assign({}, state.options.bpMacDinh);
        state.options.bpMacDinh[(fabi && fabi.nguon) || 'fabi'] = el.value;
        /* Đổi bộ phận thì CHIA LẠI những dòng đang chờ tạo mới — không thì ô chọn đổi mà bảng vẫn
           giữ bộ phận cũ, và người ta chỉ phát hiện sau khi đã Ghi. */
        if (fabi && fabi.ghep) {
          fabi.ghep.forEach((g) => { if (g.cach === 'tao') { g.taoMoi = ''; g.cach = 'khong'; } });
          layHetVaoNhap();
        }
        commit();
        return;
      }
      /* Đổi dòng lương cho một cơ sở bên Nhân sự — cũng chỉ ghi vào BẢN NHÁP. */
      if (el.matches('[data-luong]')) {
        const k = Number(el.getAttribute('data-luong'));
        const v = el.value === '' ? null : Number(el.value);
        if (luong && luong.ghep && luong.ghep[k]) {
          const g = luong.ghep[k];
          const raw = el.value;
          if (raw.indexOf('new:') === 0) {
            g.rowIndex = null; g.taoMoi = raw.slice(4); g.cach = 'tao';
          } else {
            g.taoMoi = '';
            /* Một dòng lương chỉ nhận MỘT cơ sở — không thì hai cơ sở ghi vào một dòng và cái sau
               đè mất cái trước, tổng lương thiếu hẳn một cơ sở. */
            if (v !== null) { luong.ghep.forEach((o, i) => { if (i !== k && o.rowIndex === v) { o.rowIndex = null; o.cach = 'khong'; } }); }
            g.rowIndex = v;
            g.cach = v === null ? 'khong' : 'tay';
          }
          renderTab();
        }
        return;
      }
      if (el.matches('[data-path]')) onFieldChange(el);
    });
    main.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.matches('input[data-path]')) {
        e.preventDefault();
        // Enter → xuống ô cùng cột dòng dưới
        const cell = e.target.closest('td');
        const row = e.target.closest('tr');
        if (cell && row) {
          const idx = Array.from(row.children).indexOf(cell);
          const next = row.nextElementSibling && row.nextElementSibling.children[idx];
          const inp = next && next.querySelector('input,select');
          if (inp) { inp.focus(); if (inp.select) inp.select(); return; }
        }
        e.target.blur();
      }
    });
    main.addEventListener('focusin', (e) => {
      if (e.target.matches('input.num[data-path]') && e.target.select) e.target.select();
    });
    main.addEventListener('click', (e) => {
      const b = e.target.closest('[data-act]');
      if (!b || !main.contains(b)) return;
      const fn = ACTIONS[b.dataset.act];
      if (fn) fn(b.dataset.arg);
    });
    // nút trên cùng
    $('#fileInput').addEventListener('change', (e) => { if (e.target.files[0]) importExcelFile(e.target.files[0]); e.target.value = ''; });
    $('#jsonInput').addEventListener('change', (e) => { if (e.target.files[0]) importJsonFile(e.target.files[0]); e.target.value = ''; $('#moreMenu').hidden = true; });
    $('#btnExport').addEventListener('click', exportExcel);
    $('#btnMore').addEventListener('click', (e) => { e.stopPropagation(); const m = $('#moreMenu'); m.hidden = !m.hidden; $('#btnMore').setAttribute('aria-expanded', String(!m.hidden)); });
    document.addEventListener('click', (e) => { if (!e.target.closest('.menu')) $('#moreMenu').hidden = true; });
    $('#moreMenu').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-act]');
      if (!b) return;
      $('#moreMenu').hidden = true;
      ({ sample: loadSample, newPeriod, xoaSoKyNay, exportJson, print: ACTIONS.print, copyReport, reset: resetAll, syncNow: ACTIONS.syncNow }[b.dataset.act] || (() => {}))();
    });
    // kết nối máy chủ
    $('#btnSettings').addEventListener('click', openSettings);
    $('#cfgModal').addEventListener('click', (e) => { if (e.target.matches('[data-close]') || e.target === e.currentTarget) $('#cfgModal').hidden = true; });
    $('#acctModal').addEventListener('click', (e) => { if (e.target.matches('[data-close]') || e.target === e.currentTarget) $('#acctModal').hidden = true; });
    $('#btnCfgTest').addEventListener('click', () => testSettings(false));
    $('#btnCfgSave').addEventListener('click', () => testSettings(true));
    $('#cfgUrl').addEventListener('input', updateEmpLink);
    // modal
    $('#logModal').addEventListener('click', (e) => { if (e.target.matches('[data-close]') || e.target === e.currentTarget) $('#logModal').hidden = true; });
    $('#btnUndoImport').addEventListener('click', () => { undo(); $('#logModal').hidden = true; });
    // kéo-thả file Excel vào trang
    document.addEventListener('dragover', (e) => { e.preventDefault(); });
    document.addEventListener('drop', (e) => {
      e.preventDefault();
      const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (!f) return;
      if (/\.json$/i.test(f.name)) importJsonFile(f);
      else importExcelFile(f);
    });
    // phím tắt
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); exportExcel(); }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'o') { e.preventDefault(); $('#fileInput').click(); }
    });

    renderTab(ui.tab);
    if (WP) wpBoot(); else sync.start();
  }

  // ------------------------------------------------------------------ Bản WordPress: cổng PIN, tài khoản
  function wpShowGate() {
    UI.gate.show();
    $('#syncText').textContent = 'Chưa đăng nhập';
  }
  async function wpBoot() {
    document.title = `${CFGWP.tenTrang || 'Báo cáo chi phí'} — ${report.periodLabel}`;
    $('.brand-title').firstChild.textContent = (CFGWP.tenTrang || 'Báo cáo chi phí') + ' ';
    $('#btnSettings').textContent = '👤';
    $('#btnSettings').title = 'Tài khoản: đổi PIN, đăng xuất, người dùng';
    $('#tabLog').hidden = false;
    $$('a[href="nhap.html"]').forEach((a) => (a.href = CFGWP.nhapUrl || 'nhap.html'));
    $$('a[href="README.md"]').forEach((a) => (a.parentNode && a.parentNode.removeChild(a)));
    $$('#moreMenu button[data-act="sample"]').forEach((b) => (b.textContent = 'Nạp dữ liệu mẫu T8/2026 (ghi lên máy chủ)'));
    UI.setToast((m) => toast(m));
    UI.gate.setup({ title: CFGWP.tenTrang || 'Báo cáo chi phí', foot: 'Nhân viên nhập chi phí dùng trang riêng — hỏi kế toán đường dẫn.', onLogin: wpAfterLogin });
    API.onAuth((u) => { if (!u) { sync.stop(); wpShowGate(); } });
    // chip phiên bản: cài đè xong mà không biết bản nào đang chạy thì mọi con số lệch đều thành câu đố
    const chip = document.createElement('span');
    chip.className = 'badge';
    chip.id = 'verChip';
    chip.textContent = 'v' + (CFGWP.ver || '?');
    chip.title = 'Phiên bản plugin đang phục vụ trang này';
    $('.brand-title').appendChild(chip);
    try {
      const u = await API.whoami();
      if (u) wpAfterLogin(u); else wpShowGate();
    } catch (e) {
      wpShowGate();
      $('#gateErr').textContent = e.message;
    }
    // 🔴 Trang và PHP phải cùng một bản. Hosting giữ opcache là PHP trả mã CŨ dù tệp đã mới
    // (plugin Ghế 15/09/2026). Hỏi ping: khác bản là báo ngay, kèm cách xử lý.
    try {
      const r = await API.call('ping');
      if (r.version && CFGWP.ver && r.version !== CFGWP.ver) {
        chip.textContent = `v${CFGWP.ver} ≠ PHP v${r.version}`;
        chip.style.background = 'var(--danger-soft)';
        chip.style.color = 'var(--danger)';
        toast(`Máy chủ đang chạy mã bản ${r.version} trong khi trang là bản ${CFGWP.ver} (bộ đệm PHP cũ). Vào wp-admin → Báo cáo chi phí → Tự cập nhật → "Xoá bộ đệm mã PHP", hoặc nhờ hosting khởi động lại PHP.`);
      }
    } catch (e) { /* ping lỗi thì cổng đã báo */ }
  }
  function wpAfterLogin(u) {
    if (u && u.vaiApp === 'nhap') {
      toast('Tài khoản nhân viên — chuyển sang trang nhập chi phí…');
      setTimeout(() => (location.href = CFGWP.nhapUrl || 'nhap.html'), 600);
      return;
    }
    UI.gate.hide();
    sync.start({ force: true });
  }

  // ------------------------------------------------------------------ Kết nối máy chủ (cài đặt)
  function openSettings() {
    if (WP) {
      UI.account.open({ onLogout: () => { sync.stop(); wpShowGate(); } });
      return;
    }
    const c = API.getConfig();
    $('#cfgUrl').value = c.url;
    $('#cfgToken').value = c.token;
    $('#cfgUser').value = c.user;
    $('#cfgEnabled').checked = !!c.enabled;
    $('#cfgMsg').textContent = sync.lastError ? 'Lỗi gần nhất: ' + sync.lastError : '';
    updateEmpLink();
    $('#cfgModal').hidden = false;
  }
  function updateEmpLink() {
    const url = $('#cfgUrl').value.trim();
    const base = location.href.replace(/[^/]*$/, '') + 'nhap.html';
    $('#cfgEmpLink').textContent = url ? `${base}?url=${encodeURIComponent(url)}` : '—';
  }
  async function testSettings(save) {
    const cfg = { url: $('#cfgUrl').value, token: $('#cfgToken').value, user: $('#cfgUser').value, enabled: $('#cfgEnabled').checked };
    const msg = $('#cfgMsg');
    if (save && !cfg.enabled) {
      API.setConfig(cfg);
      sync.stop();
      $('#cfgModal').hidden = true;
      toast('Đã tắt đồng bộ — làm việc cục bộ trên trình duyệt này.');
      return;
    }
    if (!cfg.url || !cfg.token) { msg.textContent = 'Nhập URL và mã truy cập.'; return; }
    if (!cfg.user) { msg.textContent = 'Nhập tên của anh/chị.'; return; }
    msg.textContent = 'Đang kiểm tra…';
    try {
      const r = await API.call('ping', {}, { config: cfg });
      if (!r.role) throw new Error('Mã truy cập không đúng');
      if (r.role !== 'ketoan') throw new Error('Mã này là mã NHÂN VIÊN — trang tổng hợp cần mã kế toán.');
      msg.textContent = `Kết nối OK — API v${r.version}, quyền kế toán.`;
      if (save) {
        API.setConfig(cfg);
        $('#cfgModal').hidden = true;
        await sync.start({ force: true });
      }
    } catch (e) {
      msg.textContent = 'Lỗi: ' + e.message;
    }
  }

  // ------------------------------------------------------------------ Đồng bộ với máy chủ
  const sync = {
    on: false,
    busy: false,
    locked: false,
    lastError: '',
    version: 0,
    periodKey: '',
    stateSnap: '',
    costSnap: {},
    pushTimer: null,
    pollTimer: null,
    /* Hẹn giờ lấy số từ NGUỒN (FABi / Ghế / Nhân sự) — khác pollTimer, cái kia chỉ hỏi máy chủ của
       chính plugin này. */
    nguonTimer: null,
    pendingPush: false,

    indicator(kind, text) {
      $('#syncDot').className = 'dot ' + (kind || '');
      $('#syncText').textContent = text;
      $('#syncBox').title = text;
    },
    stateForServer(st) {
      const c = JSON.parse(JSON.stringify(st));
      delete c.costItems;
      return c;
    },
    snapshot() {
      this.stateSnap = JSON.stringify(this.stateForServer(state));
      this.costSnap = {};
      state.costItems.forEach((it) => (this.costSnap[it.id] = JSON.stringify(it)));
    },
    async start(opts) {
      this.on = API.isEnabled();
      clearInterval(this.pollTimer);
      clearInterval(this.nguonTimer);
      if (!this.on) { this.indicator('', 'Cục bộ'); renderTab(); return; }
      this.periodKey = API.periodKey(state.period);
      await this.pull({ force: true, notify: true, initial: true });
      this.pollTimer = setInterval(() => this.pull({}), 60000);
      /* 🔴 LẤY NGAY KHI MỞ TRANG, VÀ LẤY LẠI ĐỀU ĐỀU.
         Anh Thắng 18/09/2026: *"các lần sau nó tự đẩy qua luôn hay phải bấm nạp"*. Bản trước chỉ
         lấy khi ĐỔI KỲ — mở lại trang ở đúng kỳ ấy thì không lấy gì, nên sáng ra mở lên vẫn là số
         hôm qua mà dòng trạng thái lại ghi "TỰ LẤY: BẬT". Tin là đang tự lấy trong khi không phải
         thì còn tệ hơn là biết mình phải bấm.
         10 phút một lần: đủ tươi cho việc xem trong ngày, mà không nện vào cơ sở dữ liệu của
         plugin Ghế / FABi mỗi phút. */
      fabiLayNgay(true);
      luongLayNgay(true);
      this.nguonTimer = setInterval(() => { fabiLayNgay(true); luongLayNgay(true); }, 600000);
    },
    stop() {
      this.on = false;
      clearInterval(this.pollTimer);
      clearInterval(this.nguonTimer);
      clearTimeout(this.pushTimer);
      this.indicator('', 'Cục bộ');
      renderTab();
    },
    onPeriodChange() {
      if (!this.on) return;
      const k = API.periodKey(state.period);
      if (k === this.periodKey) return;
      this.periodKey = k;
      /* Tải kỳ xong mới lấy FABi — lấy trước thì bản kéo từ máy chủ đè lên ngay sau đó. */
      this.pull({ force: true, notify: true, initial: true }).then(() => fabiLayNgay(true)).then(() => luongLayNgay(true));
    },
    schedulePush() {
      if (!this.on) return;
      clearTimeout(this.pushTimer);
      this.pushTimer = setTimeout(() => this.push(), 1200);
    },
    /** Đẩy thay đổi cục bộ lên máy chủ (chỉ những gì khác bản đã đồng bộ). */
    async push() {
      if (!this.on) return;
      if (this.busy) { this.pendingPush = true; return; }
      this.busy = true;
      this.indicator('busy', 'Đang lưu lên máy chủ…');
      try {
        const period = API.periodKey(state.period);
        if (period !== this.periodKey) { this.busy = false; this.onPeriodChange(); return; }
        // khoản chi phí
        const changed = [];
        const seen = new Set();
        state.costItems.forEach((it, i) => {
          seen.add(it.id);
          const j = JSON.stringify(it);
          if (this.costSnap[it.id] !== j) changed.push(Object.assign({}, it, { period, sortOrder: i + 1 }));
        });
        const removed = Object.keys(this.costSnap).filter((id) => !seen.has(id));
        for (let i = 0; i < changed.length; i += 40) {
          const r = await API.call('upsertCosts', { items: changed.slice(i, i + 40) });
          if (r.denied && r.denied.length) toast(`${r.denied.length} khoản không được phép sửa.`);
        }
        for (const id of removed) await API.call('deleteCost', { id });
        // cấu hình & số liệu còn lại
        const sj = JSON.stringify(this.stateForServer(state));
        if (sj !== this.stateSnap) {
          const r = await API.call('saveState', { period, state: JSON.parse(sj), version: this.version });
          this.version = r.version;
        }
        this.snapshot();
        this.lastError = '';
        this.indicator('ok', `Đã lưu máy chủ ${new Date().toLocaleTimeString('vi-VN')} · ${API.getConfig().user}`);
      } catch (e) {
        this.lastError = e.message;
        if (e.data && e.data.conflict) {
          this.indicator('bad', 'Xung đột: người khác vừa lưu kỳ này');
          toast('Kỳ này vừa được người khác lưu. Tải lại từ máy chủ rồi sửa tiếp.', { label: 'Tải lại', fn: () => this.pull({ force: true, notify: true, overwrite: true }) });
        } else {
          this.indicator('bad', 'Lỗi lưu: ' + e.message);
          toast('Không lưu được lên máy chủ: ' + e.message);
        }
      } finally {
        this.busy = false;
        if (this.pendingPush) { this.pendingPush = false; this.schedulePush(); }
      }
    },
    /** Tải từ máy chủ; hoà trộn với thay đổi cục bộ chưa đẩy. */
    async pull(opts) {
      if (!this.on || this.busy) return;
      opts = opts || {};
      // đang gõ trong ô nhập thì để lần sau (trừ khi bấm tải lại)
      if (!opts.force && document.activeElement && document.activeElement.matches('input, select, textarea')) return;
      this.busy = true;
      this.indicator('busy', 'Đang tải từ máy chủ…');
      try {
        const period = API.periodKey(state.period);
        const r = await API.call('getPeriod', { period });
        this.periodKey = period;
        this.locked = !!r.locked;
        const serverCosts = (r.costs || []).map((c) => { const o = Object.assign({}, c); delete o.period; return o; });
        let changedSomething = false;
        if (r.state) {
          const localStateJson = JSON.stringify(this.stateForServer(state));
          const localDirty = this.stateSnap && localStateJson !== this.stateSnap;
          if (opts.initial || opts.overwrite || !localDirty) {
            if (r.version !== this.version || opts.initial || opts.overwrite) {
              const merged = Object.assign({}, r.state, { costItems: [] });
              const newState = E.normalizeState(merged);
              if (JSON.stringify(this.stateForServer(newState)) !== localStateJson) changedSomething = true;
              const localCosts = state.costItems;
              state = newState;
              state.costItems = localCosts;
              this.version = r.version;
            }
          }
        } else if (opts.initial) {
          // kỳ chưa có trên máy chủ → hỏi đẩy cấu hình đang có lên
          const hasLocal = state.departments.length && (state.sites.length || state.costItems.length || state.salarySites.length);
          /* 🔴 CHÉP DANH MỤC, KHÔNG CHÉP SỐ — anh Thắng 18/09/2026: *"nếu chọn kỳ tháng 9 nó phải
             trống chứ"*. Bản trước chép nguyên cả doanh thu, lương và tiền từng khoản của kỳ
             trước, nên kỳ mới mở ra đã có sẵn một bộ số trông hoàn chỉnh mà là số THÁNG TRƯỚC —
             không dòng nào báo, không ô nào đỏ. Xem E.batDauKyMoi(). */
          if (hasLocal && confirm(`Kỳ ${E.periodLabel(state.period)} chưa có trên máy chủ.\n\n`
            + `Dựng kỳ mới từ danh mục đang có (${state.departments.length} bộ phận, ${state.sites.length} điểm bán, `
            + `${state.costItems.length} khoản chi phí — giữ tên, mã đơn vị, tài khoản, cách chia)?\n\n`
            + `MỌI SỐ LIỆU sẽ bắt đầu từ 0: doanh thu, lương, tiền từng khoản.`)) {
            state = E.batDauKyMoi(state);
            state.costItems.forEach((it) => { if (!it.createdBy) it.createdBy = API.getConfig().user; });
            this.stateSnap = '';
            this.costSnap = {};
            this.version = 0;
            this.busy = false;
            await this.push();
            recompute();
            renderTab();
            return;
          }
          /* 🔴 BẤM HUỶ THÌ QUAY VỀ KỲ CŨ. Bản trước cứ thế chạy tiếp: số của kỳ cũ ở lại nguyên
             trên màn hình nhưng đã đội nhãn kỳ mới, rồi liên kết sống nạp doanh thu kỳ MỚI đè lên
             — ra một báo cáo trộn hai kỳ (doanh thu kỳ này + lương, cột nhập tay, chi phí kỳ
             trước) mà nhìn thì hoàn chỉnh. Tệ hơn nữa: lần lưu kế tiếp đẩy mớ ấy lên máy chủ dưới
             tên kỳ mới. Nên: không dựng thì không đứng lại đây. */
          const veKy = hasLocal ? E.lechKy(state) : '';
          if (!veKy) { this.busy = false; recompute(); renderTab(); return; }
          const [yy, mm] = veKy.split('-');
          state.period = { month: +mm, year: +yy };
          $('#selMonth').value = String(+mm);
          $('#inpYear').value = String(+yy);
          this.periodKey = API.periodKey(state.period);
          recompute();
          saveState();
          renderTab();
          toast(`Chưa dựng kỳ ${E.periodLabel({ month: +period.slice(5), year: +period.slice(0, 4) })} — đã quay về kỳ ${E.periodLabel(state.period)} để số không bị lẫn hai kỳ.`);
          return;
        }
        // hoà trộn khoản chi phí: máy chủ thắng với khoản chưa sửa cục bộ; giữ khoản mới cục bộ chưa đẩy
        const localById = {};
        state.costItems.forEach((it) => (localById[it.id] = it));
        const serverIds = new Set(serverCosts.map((c) => c.id));
        const merged = [];
        serverCosts.forEach((sc) => {
          const loc = localById[sc.id];
          const locDirty = loc && this.costSnap[sc.id] && JSON.stringify(loc) !== this.costSnap[sc.id];
          const pick = locDirty ? loc : sc;
          if (!loc || JSON.stringify(loc) !== JSON.stringify(pick)) changedSomething = true;
          merged.push(pick);
        });
        state.costItems.forEach((it) => {
          if (serverIds.has(it.id)) return;
          const wasOnServer = !!this.costSnap[it.id];
          if (!wasOnServer) merged.push(it); // mới tạo cục bộ, chưa đẩy
          else changedSomething = true; // đã bị xoá trên máy chủ
        });
        state.costItems = merged;
        // cập nhật snapshot cho phần đã đồng bộ (giữ dấu "dirty" cho phần chưa đẩy)
        const dirtyState = this.stateSnap && JSON.stringify(this.stateForServer(state)) !== this.stateSnap;
        const prevCostSnap = this.costSnap;
        const stateSnapBefore = this.stateSnap;
        this.snapshot();
        if (dirtyState && !opts.overwrite) this.stateSnap = stateSnapBefore;
        state.costItems.forEach((it) => {
          const j = JSON.stringify(it);
          const sc = serverCosts.find((c) => c.id === it.id);
          if (!sc || JSON.stringify(sc) !== j) this.costSnap[it.id] = prevCostSnap[it.id] || ''; // vẫn khác máy chủ → còn phải đẩy
        });
        this.lastError = '';
        const pending = state.costItems.filter((it) => it.status === 'cho_duyet').length;
        this.indicator('ok', `${this.locked ? '🔒 ' : ''}Máy chủ ${new Date().toLocaleTimeString('vi-VN')}${r.updatedBy ? ' · ' + r.updatedBy : ''}${pending ? ` · ${pending} chờ duyệt` : ''}`);
        if (changedSomething || opts.initial) {
          recompute();
          saveState();
          renderTab();
          if (opts.notify && changedSomething && !opts.initial) toast('Đã cập nhật dữ liệu mới từ máy chủ.');
          if (opts.initial && r.state) toast(`Đã tải kỳ ${E.periodLabel(state.period)} từ máy chủ (bản v${r.version}${r.updatedBy ? ', ' + r.updatedBy : ''}).`);
        } else if (opts.notify) toast('Không có gì mới trên máy chủ.');
        this.busy = false;
        if (Object.keys(this.costSnap).some((id) => { const it = state.costItems.find((x) => x.id === id); return it && this.costSnap[id] !== JSON.stringify(it); })) this.schedulePush();
      } catch (e) {
        this.lastError = e.message;
        this.indicator('bad', 'Lỗi máy chủ: ' + e.message);
        if (opts.notify) toast('Không tải được từ máy chủ: ' + e.message);
      } finally {
        this.busy = false;
      }
    },
  };

  // Cho phép kiểm thử / tích hợp từ ngoài (VD: nhúng iframe rồi gọi window.BaoCaoApp.getState()).
  window.BaoCaoApp = {
    getState: () => JSON.parse(JSON.stringify(state)),
    setState: (s) => { prevState = JSON.parse(JSON.stringify(state)); state = E.normalizeState(s); commit(); },
    getReport: () => report,
    exportExcel,
    renderTab,
    version: APP_VERSION,
  };

  document.addEventListener('DOMContentLoaded', init);
})();
