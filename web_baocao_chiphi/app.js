/* app.js — Giao diện web "Báo cáo chi phí K&H". Toàn bộ logic tính nằm ở engine.js. */
(function () {
  'use strict';

  const APP_VERSION = '1.0.0';
  const STORAGE_KEY = 'khh_baocao_chiphi_v1';
  const UI_KEY = 'khh_baocao_chiphi_ui';
  const E = window.BaoCaoEngine;
  const IMP = window.BaoCaoImporter;
  const EXP = window.BaoCaoExporter;

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
  let ui = Object.assign({ tab: 'dashboard', sitesDept: '', siteFilter: 'all', costFilter: 'all' }, loadJSON(UI_KEY) || {});
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
    $('#periodBadge').textContent = report.periodLabel;
    $('#selMonth').value = state.period.month;
    $('#inpYear').value = state.period.year;
    document.title = `Báo cáo chi phí K&H ${report.periodLabel}`;
  }
  function commit(opts) {
    recompute();
    saveState();
    if (!opts || !opts.silent) renderTab(opts && opts.tab);
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
  function renderDashboard(root) {
    const R = report;
    const totalRev = Object.values(R.revenue).reduce((a, b) => a + b, 0);
    const manualTotal = R.manualCols.reduce((a, m) => a + m.total, 0);
    const totalCost = R.grandTotal + manualTotal + R.salaryDeptTotals.total + R.salarySitesTotal.actual;
    root.innerHTML = `
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
          <button class="btn small danger" data-act="zeroSites" title="Đưa doanh thu tất cả điểm đang lọc về 0">Xoá số doanh thu</button>
        </div>
        <div id="pasteBox" class="card" style="margin:0 0 10px;background:var(--panel-2)" hidden>
          <p class="hint">Mỗi dòng: <kbd>Mã đơn vị</kbd> <kbd>Tab</kbd> <kbd>Tên điểm</kbd> <kbd>Tab</kbd> <kbd>Doanh thu</kbd> (tuỳ chọn thêm <kbd>Tab</kbd> <kbd>Bộ phận</kbd>). Điểm trùng mã sẽ được cập nhật doanh thu.</p>
          <textarea id="pasteArea" placeholder="50AMBT	POSH MN AEON MALL BÌNH TÂN	197260000	Posh"></textarea>
          <div class="toolbar" style="margin-top:6px">
            <label class="hint">Bộ phận mặc định <select id="pasteDept">${deptOptions().map((o) => `<option value="${o.value}">${esc(o.label)}</option>`).join('')}</select></label>
            <button class="btn small primary" data-act="applyPaste">Thêm vào danh sách</button>
          </div>
        </div>
        <div class="table-wrap tall"><table class="grid-table dense">
          <thead><tr><th>#</th><th>Bộ phận</th><th>Mã đơn vị</th><th>Tên điểm</th><th class="num">Doanh thu</th><th class="num">Tỷ trọng trong BP</th><th></th></tr></thead>
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
                      <td>${inp(`sites.${i}.revenue`, s.revenue, 'num')}</td>
                      <td class="num muted">${rev > 0 ? pct(E.num(s.revenue) / rev) : '—'}</td>
                      ${actBtns([{ act: 'delSite', arg: i, icon: '🗑', title: 'Xoá điểm', cls: 'danger' }])}
                    </tr>`;
                  })
                  .join('')
              : `<tr><td colspan="7" class="empty">Chưa có điểm nào. Nhập Excel, dán nhanh, hoặc thêm từng điểm.</td></tr>`
          }
          ${sites.length ? `<tr class="total"><td colspan="4">Cộng (${sites.length} điểm)</td>${tdn(sites.reduce((a, x) => a + E.num(x.s.revenue), 0))}<td></td><td></td></tr>` : ''}
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
    const items = state.costItems.map((it, i) => ({ it, i })).filter((x) => filter === 'all' || x.it.kind === filter);
    const totalAll = state.costItems.reduce((a, it) => a + E.num(it.total), 0);

    root.innerHTML = `
      <div class="card">
        <div class="card-head">
          <h2>Khoản chi phí ${R.periodLabel}</h2>
          <span class="hint">Tương ứng vùng G..N sheet "total general". <em>Nội dung chi tiết MISA</em> là tiêu đề cột trên báo cáo; các khoản cùng <em>Gộp cột</em> hiển thị chung một cột.</span>
          <div class="spacer"></div>
          <div class="seg">
            ${[{ v: 'all', l: 'Tất cả' }, { v: 'personal', l: 'Cá nhân' }, { v: 'company', l: 'Công ty' }].map((o) => `<button data-act="costFilter" data-arg="${o.v}" class="${filter === o.v ? 'active' : ''}">${o.l}</button>`).join('')}
          </div>
          <button class="btn small primary" data-act="addItem">+ Thêm khoản</button>
        </div>
        <div class="table-wrap tall"><table class="grid-table dense">
          <thead><tr>
            <th class="sticky-col">#</th><th>Loại</th><th>Tên khoản (nội bộ)</th><th>Nội dung chung MISA</th><th>Nội dung chi tiết MISA (tiêu đề cột báo cáo)</th>
            <th>Tài khoản</th><th>Mã đối tượng</th><th class="num">Tổng tiền</th><th>Kiểu chia nhóm</th>
            ${groups.map((g) => `<th class="num">${esc(g.name)}</th>`).join('')}
            <th>Phương pháp</th><th>Loại trừ bộ phận</th><th>Gộp cột</th><th>Ghi chú</th><th></th>
          </tr></thead>
          <tbody>${
            items.length
              ? items
                  .map(({ it, i }) => {
                    const shares = E.itemShares(state, it);
                    const custom = it.split === 'custom';
                    return `<tr>
                      <td class="sticky-col muted">${i + 1}</td>
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
                      ${actBtns([
                        { act: 'moveItem', arg: `${i}:-1`, icon: '↑', title: 'Lên' },
                        { act: 'moveItem', arg: `${i}:1`, icon: '↓', title: 'Xuống' },
                        { act: 'dupItem', arg: i, icon: '⧉', title: 'Nhân đôi' },
                        { act: 'delItem', arg: i, icon: '🗑', title: 'Xoá khoản', cls: 'danger' },
                      ])}
                    </tr>`;
                  })
                  .join('')
              : `<tr><td colspan="${15 + groups.length}" class="empty">Chưa có khoản chi phí. Nhập Excel hoặc bấm "+ Thêm khoản".</td></tr>`
          }
          <tr class="total"><td class="sticky-col"></td><td colspan="6">Tổng cộng (${state.costItems.length} khoản)</td>${tdn(totalAll)}<td></td>
            ${groups.map((g) => tdn(state.costItems.reduce((a, it) => a + (E.itemShares(state, it)[g.id] || 0), 0))).join('')}<td colspan="5"></td></tr>
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
          <button class="btn small" data-act="syncSalary" title="Đặt Báo cáo và DNTT = Theo báo cáo cho tất cả dòng">Báo cáo = DNTT = Theo báo cáo</button>
          <button class="btn small primary" data-act="addSalarySite">+ Thêm dòng</button>
        </div>
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
                          <td>${inp(`salarySites.${i}.name`, r.name, 'text', 'wide')}</td>
                          <td>${sel(`salarySites.${i}.dept`, r.dept, deptOptions())}</td>
                          <td>${inp(`salarySites.${i}.reported`, E.num(r.reported), 'num')}</td>
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
              <tr class="subtotal"><td class="sticky-col"><strong>Số tiền phân bổ</strong></td><td class="muted">${alloc.cols.length} cột</td>${tdn(alloc.sumRevenue)}<td class="num">100%</td>${alloc.cols.map((c) => tdn(c.total)).join('')}${tdn(alloc.cols.reduce((a, c) => a + c.total, 0))}</tr>
            </thead>
            <tbody>
              ${alloc.rows.map((r) => `<tr><td class="sticky-col">${esc(r.name)}</td><td><code>${esc(r.code)}</code></td>${tdn(r.revenue)}<td class="num muted">${pct(r.weight)}</td>${alloc.cols.map((c) => tdn(r.vals[c.key])).join('')}${tdn(r.total)}</tr>`).join('')}
              <tr class="total"><td class="sticky-col">Tổng cộng</td><td></td>${tdn(alloc.sumRevenue)}<td class="num">100%</td>${alloc.cols.map((c) => tdn(alloc.totals[c.key])).join('')}${tdn(alloc.grandTotal)}</tr>
            </tbody>
          </table></div>`
            : `<div class="empty">Bộ phận này chưa có điểm bán nào (tab Doanh thu).</div>`
        }
      </div>`;
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
  const RENDERERS = { dashboard: renderDashboard, revenue: renderRevenue, costs: renderCosts, salary: renderSalary, report: renderReport, sites: renderSites, check: renderCheck };
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
      state.costItems.push({ id: E.newId('ci'), kind: 'company', name: '', misaGeneral: '', misaDetail: '', account: '', total: 0, split: 'equal', shares: {}, objectCode: '', excludeDepts: [], groupKey: '', method: '', note: '' });
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
    // khác
    sitesDept(v) {
      ui.sitesDept = v;
      renderTab();
    },
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
        state = res.state;
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
    prevState = JSON.parse(JSON.stringify(state));
    state = E.normalizeState(JSON.parse(JSON.stringify(window.SAMPLE_DATA)));
    commit({ tab: 'dashboard' });
    toast('Đã nạp dữ liệu mẫu T8/2026.', { label: 'Hoàn tác', fn: undo });
  }

  function newPeriod() {
    if (!confirm('Tạo kỳ mới: giữ nhóm, bộ phận, danh sách điểm, tên cột nhập tay, mã đơn vị & nội dung MISA; xoá toàn bộ số tiền và khoản chi phí. Tiếp tục?')) return;
    prevState = JSON.parse(JSON.stringify(state));
    const p = state.period;
    const m = p.month === 12 ? 1 : p.month + 1;
    const y = p.month === 12 ? p.year + 1 : p.year;
    state.period = { month: m, year: y };
    state.sites.forEach((s) => (s.revenue = 0));
    state.departments.forEach((d) => { if (d.revenueOverride) d.revenue = 0; });
    state.costItems = [];
    state.manualCols.forEach((mc) => (mc.values = {}));
    state.salaryDept.forEach((r) => E.SALARY_DEPT_FIELDS.forEach((f) => (r[f.key] = 0)));
    state.salarySites.forEach((r) => { r.reported = 0; r.report = 0; r.dntt = 0; r.actual = 0; });
    commit({ tab: 'revenue' });
    toast(`Đã tạo kỳ ${E.periodLabel(state.period)}.`, { label: 'Hoàn tác', fn: undo });
  }

  function resetAll() {
    if (!confirm('Xoá toàn bộ dữ liệu đã lưu trên trình duyệt này? (Nên Lưu file cấu hình .json trước.)')) return;
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
    $('#selMonth').addEventListener('change', (e) => { state.period.month = +e.target.value; commit(); });
    $('#inpYear').addEventListener('change', (e) => { state.period.year = +e.target.value || state.period.year; commit(); });
    // nhập liệu (uỷ quyền)
    const main = $('#main');
    main.addEventListener('change', (e) => {
      const el = e.target;
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
      ({ sample: loadSample, newPeriod, exportJson, print: ACTIONS.print, copyReport, reset: resetAll }[b.dataset.act] || (() => {}))();
    });
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
  }

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
