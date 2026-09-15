/* nhap.js — trang nhập chi phí cho nhân viên (dùng chung api.js với trang kế toán). */
(function () {
  'use strict';
  const E = window.BaoCaoEngine;
  const API = window.BaoCaoApi;
  const WP = API.MODE === 'wp';
  const UI = window.KHBC_UI;
  const CFGWP = API.CFG || {};
  const $ = (s) => document.querySelector(s);
  const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const UI_KEY = 'khh_baocao_chiphi_nhap_ui';

  const now = new Date();
  let ui = { month: now.getMonth() + 1, year: now.getFullYear(), onlyMine: true };
  try { ui = Object.assign(ui, JSON.parse(localStorage.getItem(UI_KEY) || '{}')); } catch (e) { /* bỏ qua */ }
  let groups = [];
  let costs = [];
  let editing = null;
  let locked = false;
  let attachments = []; // URL tệp đính kèm của khoản đang soạn

  const period = () => ({ month: ui.month, year: ui.year });
  const saveUi = () => { try { localStorage.setItem(UI_KEY, JSON.stringify(ui)); } catch (e) { /* bỏ qua */ } };

  let toastTimer;
  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (t.hidden = true), 3500);
  }
  function setConn(ok, text) {
    $('#connDot').className = 'dot ' + (ok === null ? '' : ok ? 'ok' : 'bad');
    $('#connText').textContent = text;
  }

  // ------------------------------------------------------------ cấu hình
  function renderCfg() {
    const c = API.getConfig();
    $('#cfgUrl').value = c.url;
    $('#cfgToken').value = c.token;
    $('#cfgUser').value = c.user;
  }
  async function saveCfg() {
    const cfg = API.setConfig({ url: $('#cfgUrl').value, token: $('#cfgToken').value, user: $('#cfgUser').value, enabled: true });
    if (!cfg.user) { $('#cfgMsg').textContent = 'Vui lòng nhập tên.'; return; }
    $('#cfgMsg').textContent = 'Đang kiểm tra…';
    try {
      const r = await API.call('ping');
      if (!r.role) throw new Error('Mã truy cập không đúng');
      $('#cfgMsg').textContent = `Kết nối OK (quyền: ${r.role === 'ketoan' ? 'kế toán' : 'nhân viên'}).`;
      $('#settingsCard').hidden = true;
      await load();
    } catch (e) {
      $('#cfgMsg').textContent = 'Lỗi: ' + e.message;
      setConn(false, 'Lỗi kết nối');
    }
  }

  // ------------------------------------------------------------ dữ liệu
  async function load() {
    if (!API.isEnabled()) {
      setConn(null, WP ? 'Chưa đăng nhập' : 'Chưa kết nối');
      if (WP) UI.gate.show(); else $('#settingsCard').hidden = false;
      return;
    }
    setConn(null, 'Đang tải…');
    try {
      const r = await API.call('getPeriod', { period: API.periodKey(period()) });
      groups = r.groups && r.groups.length ? r.groups : (r.state && r.state.groups) || [];
      if (!groups.length) groups = [{ id: 'MTD', name: 'Máy tự động (MTĐ)' }, { id: 'KVC', name: 'Khu vui chơi (KVC)' }];
      costs = r.costs || [];
      locked = !!r.locked;
      $('#lockedBanner').hidden = !locked;
      $('#btnSubmit').disabled = locked;
      setConn(true, `${API.getConfig().user} · đã tải ${new Date().toLocaleTimeString('vi-VN')}${locked ? ' · 🔒 kỳ đã chốt' : ''}`);
      renderSplitOptions();
      renderList();
    } catch (e) {
      setConn(false, 'Lỗi: ' + e.message);
      $('#list').innerHTML = `<div class="issue error"><span class="lv">LỖI</span><span>${esc(e.message)}</span></div>`;
    }
  }

  function renderSplitOptions() {
    const sel = $('#fSplit');
    const cur = sel.value;
    sel.innerHTML = [`<option value="equal">Chia đều các nhóm (${groups.map((g) => g.name).join(' / ')})</option>`]
      .concat(groups.map((g) => `<option value="${esc(g.id)}">100% ${esc(g.name)}</option>`))
      .concat(['<option value="custom">Tuỳ chỉnh số tiền từng nhóm</option>'])
      .join('');
    if ([...sel.options].some((o) => o.value === cur)) sel.value = cur;
    renderShares();
  }
  function renderShares() {
    const box = $('#sharesBox');
    const custom = $('#fSplit').value === 'custom';
    box.hidden = !custom;
    if (!custom) return;
    if (!box.children.length || box.dataset.groups !== groups.map((g) => g.id).join(',')) {
      box.dataset.groups = groups.map((g) => g.id).join(',');
      box.innerHTML = groups.map((g) => `<label>${esc(g.name)}<input class="num share" data-g="${esc(g.id)}" inputmode="numeric" placeholder="0"></label>`).join('');
    }
  }
  function readForm() {
    const total = E.num($('#fTotal').value);
    const split = $('#fSplit').value;
    const shares = {};
    if (split === 'custom') {
      let s = 0;
      box_each((inp) => { shares[inp.dataset.g] = E.num(inp.value); s += shares[inp.dataset.g]; });
      if (Math.abs(s - total) > 0.5) throw new Error(`Tổng chia nhóm (${E.fmt(s)}) phải bằng số tiền (${E.fmt(total)}).`);
    }
    const name = $('#fName').value.trim();
    if (!name) throw new Error('Nhập tên khoản.');
    if (total <= 0) throw new Error('Số tiền phải lớn hơn 0.');
    return {
      id: $('#fId').value || E.newId('ci'),
      period: API.periodKey(period()),
      kind: $('#fKind').value,
      name,
      total,
      split,
      shares,
      misaGeneral: $('#fMisaGeneral').value.trim(),
      misaDetail: $('#fMisaDetail').value.trim(),
      account: $('#fAccount').value.trim(),
      objectCode: $('#fObject').value.trim(),
      note: $('#fNote').value.trim(),
      attachments: attachments.slice(),
      excludeDepts: editing ? editing.excludeDepts || [] : [],
      groupKey: editing ? editing.groupKey || '' : '',
      method: editing ? editing.method || '' : '',
    };
  }
  function box_each(fn) { document.querySelectorAll('#sharesBox input.share').forEach(fn); }

  function fillForm(it) {
    editing = it || null;
    $('#fId').value = it ? it.id : '';
    $('#fName').value = it ? it.name : '';
    $('#fTotal').value = it ? E.fmt(it.total) : '';
    $('#fKind').value = it ? it.kind : 'company';
    $('#fSplit').value = it ? it.split : 'equal';
    renderShares();
    if (it && it.split === 'custom') box_each((inp) => (inp.value = E.fmt(E.num(it.shares && it.shares[inp.dataset.g]))));
    $('#fMisaGeneral').value = it ? it.misaGeneral : '';
    $('#fMisaDetail').value = it ? it.misaDetail : '';
    $('#fAccount').value = it ? it.account : '';
    $('#fObject').value = it ? it.objectCode : '';
    $('#fNote').value = it ? it.note : '';
    attachments = it && Array.isArray(it.attachments) ? it.attachments.slice() : [];
    renderAtt();
    $('#formTitle').textContent = it ? `Sửa khoản: ${it.name}` : 'Thêm khoản chi phí';
    $('#btnSubmit').textContent = it ? 'Lưu thay đổi' : 'Gửi khoản chi phí';
    $('#btnCancelEdit').hidden = !it;
    $('#formMsg').textContent = '';
    if (it) window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function renderAtt() {
    const box = $('#fileList');
    if (!box) return;
    box.innerHTML = UI ? UI.attChips(attachments, true) : '';
    box.querySelectorAll('[data-att-del]').forEach((b) => b.addEventListener('click', () => { attachments.splice(+b.dataset.attDel, 1); renderAtt(); }));
  }
  async function pickFiles(ev) {
    const files = ev.target.files;
    if (!files || !files.length) return;
    $('#formMsg').textContent = 'Đang tải tệp lên…';
    try {
      const urls = await UI.uploadFiles(files, API.periodKey(period()), (n) => ($('#formMsg').textContent = `Đang tải ${n}…`));
      attachments = attachments.concat(urls);
      renderAtt();
      $('#formMsg').textContent = `Đã tải ${urls.length} tệp.`;
    } catch (e) { $('#formMsg').textContent = 'Lỗi tải tệp: ' + e.message; }
    ev.target.value = '';
  }

  async function submit(ev) {
    ev.preventDefault();
    if (locked) { $('#formMsg').textContent = 'Kỳ đã chốt.'; return; }
    let item;
    try { item = readForm(); } catch (e) { $('#formMsg').textContent = e.message; return; }
    if (!API.isEnabled()) { if (WP) UI.gate.show(); else $('#settingsCard').hidden = false; $('#formMsg').textContent = WP ? 'Hãy đăng nhập trước.' : 'Hãy kết nối máy chủ trước.'; return; }
    $('#btnSubmit').disabled = true;
    $('#formMsg').textContent = 'Đang gửi…';
    try {
      const r = await API.call('upsertCost', { item });
      if (r.denied && r.denied.length) throw new Error('Khoản này đã được duyệt, không sửa được nữa.');
      toast(editing ? 'Đã lưu thay đổi.' : 'Đã gửi, chờ kế toán duyệt.');
      fillForm(null);
      await load();
    } catch (e) {
      $('#formMsg').textContent = 'Lỗi: ' + e.message;
    } finally {
      $('#btnSubmit').disabled = false;
    }
  }

  async function removeItem(id) {
    const it = costs.find((c) => c.id === id);
    if (!it || !confirm(`Xoá khoản "${it.name}"?`)) return;
    try {
      await API.call('deleteCost', { id });
      toast('Đã xoá.');
      await load();
    } catch (e) { toast('Lỗi: ' + e.message); }
  }

  function renderList() {
    const me = API.getConfig().user;
    const rows = costs.filter((c) => !ui.onlyMine || c.createdBy === me);
    const statusLabel = (s) => (E.COST_STATUSES.find((x) => x.value === s) || { label: s }).label;
    const total = rows.reduce((a, c) => a + E.num(c.total), 0);
    const approved = rows.filter((c) => c.status === 'da_duyet').reduce((a, c) => a + E.num(c.total), 0);
    $('#sumText').innerHTML = rows.length ? `${rows.length} khoản · tổng <b>${E.fmt(total)}</b> · đã duyệt <b>${E.fmt(approved)}</b>` : '';
    if (!rows.length) { $('#list').innerHTML = '<div class="empty">Chưa có khoản nào trong kỳ này.</div>'; return; }
    const splitText = (c) => {
      if (c.split === 'custom') return groups.map((g) => `${g.name}: ${E.fmt(E.num(c.shares && c.shares[g.id]))}`).join(' · ');
      if (c.split === 'equal' || !c.split) return 'Chia đều các nhóm';
      const g = groups.find((x) => x.id === c.split);
      return `100% ${g ? g.name : c.split}`;
    };
    $('#list').innerHTML = rows
      .slice()
      .sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)))
      .map((c) => {
        const mine = c.createdBy === me;
        const canEdit = mine && c.status === 'cho_duyet' && !locked;
        const when = c.createdAt ? new Date(c.createdAt).toLocaleString('vi-VN') : '';
        return `<div class="entry">
          <div class="body">
            <div class="t">${esc(c.name)} <span class="status ${esc(c.status)}">${esc(statusLabel(c.status))}</span></div>
            <div class="meta">${esc(c.kind === 'personal' ? 'Cá nhân' : 'Công ty')} · ${esc(splitText(c))}${c.account ? ' · TK ' + esc(c.account) : ''}${c.objectCode ? ' · ' + esc(c.objectCode) : ''}</div>
            ${c.misaDetail ? `<div class="meta">MISA: ${esc(c.misaDetail)}</div>` : ''}
            ${c.note ? `<div class="meta">Ghi chú: ${esc(c.note)}</div>` : ''}
            ${(c.attachments || []).length ? `<div class="chips" style="margin-top:4px">${UI ? UI.attChips(c.attachments, false) : ''}</div>` : ''}
            <div class="meta">${esc(c.createdBy)} · ${esc(when)}${c.status !== 'cho_duyet' && c.approvedBy ? ` · ${esc(statusLabel(c.status))} bởi ${esc(c.approvedBy)}` : ''}</div>
            ${canEdit ? `<div class="toolbar" style="margin:6px 0 0"><button class="btn small" data-edit="${esc(c.id)}">Sửa</button><button class="btn small danger" data-del="${esc(c.id)}">Xoá</button></div>` : ''}
          </div>
          <div class="amt">${E.fmt(c.total)}</div>
        </div>`;
      })
      .join('');
  }

  // ------------------------------------------------------------ khởi động
  function init() {
    $('#selMonth').innerHTML = Array.from({ length: 12 }, (_, i) => `<option value="${i + 1}">${String(i + 1).padStart(2, '0')}</option>`).join('');
    $('#selMonth').value = ui.month;
    $('#inpYear').value = ui.year;
    $('#onlyMine').checked = ui.onlyMine;
    $('#periodBadge').textContent = E.periodLabel(period());
    renderCfg();
    renderSplitOptions();

    $('#selMonth').addEventListener('change', (e) => { ui.month = +e.target.value; saveUi(); $('#periodBadge').textContent = E.periodLabel(period()); fillForm(null); load(); });
    $('#inpYear').addEventListener('change', (e) => { ui.year = +e.target.value || ui.year; saveUi(); $('#periodBadge').textContent = E.periodLabel(period()); fillForm(null); load(); });
    $('#btnSettings').addEventListener('click', () => { const c = $('#settingsCard'); c.hidden = !c.hidden; renderCfg(); });
    $('#btnSaveCfg').addEventListener('click', saveCfg);
    $('#btnRefresh').addEventListener('click', load);
    $('#fSplit').addEventListener('change', renderShares);
    $('#form').addEventListener('submit', submit);
    $('#btnCancelEdit').addEventListener('click', () => fillForm(null));
    $('#onlyMine').addEventListener('change', (e) => { ui.onlyMine = e.target.checked; saveUi(); renderList(); });
    $('#fTotal').addEventListener('blur', (e) => { const v = E.num(e.target.value); e.target.value = v ? E.fmt(v) : ''; });
    $('#list').addEventListener('click', (e) => {
      const ed = e.target.closest('[data-edit]');
      const del = e.target.closest('[data-del]');
      if (ed) fillForm(costs.find((c) => c.id === ed.dataset.edit));
      if (del) removeItem(del.dataset.del);
    });
    // URL dạng nhap.html?url=…&token=…  để kế toán gửi link cấu hình sẵn
    const qs = new URLSearchParams(location.search);
    if (qs.get('url')) {
      API.setConfig({ url: qs.get('url'), token: qs.get('token') || API.getConfig().token, enabled: true });
      history.replaceState(null, '', location.pathname);
      renderCfg();
    }
    if (WP) {
      $('#settingsCard').hidden = true;
      $('#btnSettings').hidden = true;
      $('#btnAcct').hidden = false;
      $('#fileBox').hidden = false;
      $('#fFiles').addEventListener('change', pickFiles);
      $('#btnAcct').addEventListener('click', () => UI.account.open({ onLogout: () => { costs = []; renderList(); UI.gate.show(); } }));
      $('#acctModal').addEventListener('click', (e) => { if (e.target.matches('[data-close]') || e.target === e.currentTarget) $('#acctModal').hidden = true; });
      document.querySelectorAll('a[href="index.html"]').forEach((a) => (a.href = CFGWP.appUrl || 'index.html'));
      document.title = `${CFGWP.tenTrang || 'Nhập chi phí'} — Nhập chi phí`;
      UI.setToast(toast);
      UI.gate.setup({ title: 'Nhập chi phí', foot: 'Kế toán cấp PIN cho anh/chị. Quên PIN thì nhờ kế toán đặt lại.', onLogin: () => { UI.gate.hide(); load(); } });
      API.onAuth((u) => { if (!u) UI.gate.show(); });
      API.whoami().then((u) => { if (u) load(); else UI.gate.show(); }).catch((e) => { UI.gate.show(e.message); });
      setInterval(() => { if (API.isEnabled() && !editing) load(); }, 90000);
      return;
    }
    load();
    setInterval(() => { if (API.isEnabled() && !editing) load(); }, 90000);
  }
  document.addEventListener('DOMContentLoaded', init);
})();
