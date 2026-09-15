/*
 * wp-ui.js — Giao diện dùng chung cho bản WordPress: cổng đăng nhập PIN, hộp Tài khoản
 * (đổi PIN, đăng xuất) và quản lý người dùng cho Admin. Dùng ở cả trang kế toán và trang nhân viên.
 */
(function (root) {
  'use strict';
  const API = root.BaoCaoApi;
  const $ = (s, r) => (r || document).querySelector(s);
  const esc = (s) => String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const ROLE_TAG = (vai) => `<span class="role-tag ${vai === 'Admin' ? 'Admin' : vai === 'Nhân viên' ? 'nv' : ''}">${esc(vai)}</span>`;
  let toastFn = (m) => alert(m);

  // ------------------------------------------------------------ cổng PIN
  const gate = {
    onLogin: null,
    busy: false,
    setup(opts) {
      opts = opts || {};
      this.onLogin = opts.onLogin || null;
      if (opts.title) $('#gateTitle').textContent = opts.title;
      if (opts.foot) $('#gateFoot').textContent = opts.foot;
      const pin = $('#gatePin');
      const kp = $('#keypad');
      if (kp && !kp.children.length) {
        ['1', '2', '3', '4', '5', '6', '7', '8', '9', '⌫', '0', 'OK'].forEach((k) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.textContent = k;
          if (k === '⌫' || k === 'OK') b.className = 'aux';
          b.addEventListener('click', () => {
            if (k === 'OK') return gate.submit();
            if (k === '⌫') pin.value = pin.value.slice(0, -1);
            else if (pin.value.length < 8) pin.value += k;
            pin.focus();
          });
          kp.appendChild(b);
        });
      }
      pin.addEventListener('keydown', (e) => { if (e.key === 'Enter') gate.submit(); });
      $('#gateBtn').addEventListener('click', () => gate.submit());
    },
    show(msg) {
      $('#gate').hidden = false;
      $('#gateErr').textContent = msg || '';
      $('#gatePin').value = '';
      setTimeout(() => $('#gatePin').focus(), 80);
    },
    hide() { $('#gate').hidden = true; },
    async submit() {
      if (this.busy) return;
      const pin = ($('#gatePin').value || '').trim();
      if (!/^\d{4,8}$/.test(pin)) { $('#gateErr').textContent = 'PIN phải 4–8 số'; return; }
      this.busy = true;
      $('#gateBtn').disabled = true;
      $('#gateErr').textContent = '';
      try {
        const u = await API.login(pin);
        this.hide();
        if (this.onLogin) this.onLogin(u);
      } catch (e) {
        $('#gateErr').textContent = e.message || 'PIN không đúng';
        $('#gatePin').value = '';
        $('#gatePin').focus();
      } finally {
        this.busy = false;
        $('#gateBtn').disabled = false;
      }
    },
  };

  // ------------------------------------------------------------ hộp tài khoản
  const account = {
    users: [],
    async open(opts) {
      opts = opts || {};
      const u = API.currentUser() || {};
      const modal = $('#acctModal');
      const body = $('#acctBody');
      const admin = u.vai === 'Admin';
      body.innerHTML = `
        <div class="acct-head">
          <div class="avatar">${esc((u.ten || '?').trim().charAt(0).toUpperCase())}</div>
          <div><div style="font-weight:800;font-size:16px">${esc(u.ten || '')}</div><div>${ROLE_TAG(u.vai || '')}${u.boPhan ? ` <span class="hint">· ${esc(u.boPhan)}</span>` : ''}</div></div>
          <div class="spacer" style="flex:1"></div>
          <button class="btn" id="acctLogout">Đăng xuất</button>
        </div>
        <div class="grid">
          <div class="card" style="margin:0">
            <div class="card-head"><h2>Đổi PIN</h2></div>
            <div class="form-grid">
              <label>PIN hiện tại<input id="pinOld" type="password" inputmode="numeric" maxlength="8" autocomplete="off"></label>
              <label>PIN mới (4–8 số)<input id="pinNew" type="password" inputmode="numeric" maxlength="8" autocomplete="off"></label>
              <label>Nhập lại PIN mới<input id="pinNew2" type="password" inputmode="numeric" maxlength="8" autocomplete="off"></label>
            </div>
            <div class="toolbar" style="margin-top:8px"><button class="btn primary" id="btnPin">Đổi PIN</button><span class="hint" id="pinMsg"></span></div>
          </div>
          ${opts.extra || ''}
        </div>
        ${admin ? `<div class="card" style="margin-top:14px">
          <div class="card-head"><h2>Người dùng & phân quyền</h2><span class="hint">Admin/Kế toán vào trang kế toán; Nhân viên chỉ vào trang nhập chi phí. PIN là duy nhất cho mỗi người.</span><div class="spacer"></div><button class="btn small primary" id="btnAddUser">+ Thêm người</button></div>
          <div id="userAdd" class="form-grid" hidden style="margin-bottom:10px;padding:10px;background:var(--panel-2);border-radius:8px">
            <label>Tên<input id="nuTen" placeholder="VD: Lan"></label>
            <label>Vai<select id="nuVai"><option>Nhân viên</option><option>Kế toán</option><option>Admin</option></select></label>
            <label>Bộ phận (tuỳ chọn)<input id="nuBp" placeholder="VD: Funzone"></label>
            <label>Email (để SSO, tuỳ chọn)<input id="nuEmail" placeholder="ten@poshvn.com"></label>
            <label>PIN (4–8 số)<input id="nuPin" inputmode="numeric" maxlength="8" autocomplete="off"></label>
            <div class="toolbar" style="align-items:flex-end"><button class="btn primary" id="btnSaveNew">Tạo</button><button class="btn" id="btnCancelNew">Huỷ</button><span class="hint" id="nuMsg"></span></div>
          </div>
          <div class="table-wrap"><table class="grid-table dense" id="userTable"><thead><tr><th>Tên</th><th>Vai</th><th>Bộ phận</th><th>Email</th><th>PIN</th><th>Hoạt động</th><th></th></tr></thead><tbody><tr><td colspan="7" class="empty">Đang tải…</td></tr></tbody></table></div>
        </div>` : ''}`;
      modal.hidden = false;
      $('#acctLogout').onclick = async () => { await API.logout(); modal.hidden = true; if (opts.onLogout) opts.onLogout(); };
      $('#btnPin').onclick = async () => {
        const o = $('#pinOld').value.trim(), n = $('#pinNew').value.trim(), n2 = $('#pinNew2').value.trim();
        const msg = $('#pinMsg');
        if (n !== n2) { msg.textContent = 'PIN mới nhập lại không khớp.'; return; }
        try { await API.call('changePin', { old: o, new: n }); msg.textContent = 'Đã đổi PIN.'; $('#pinOld').value = $('#pinNew').value = $('#pinNew2').value = ''; toastFn('Đã đổi PIN.'); }
        catch (e) { msg.textContent = e.message; }
      };
      if (admin) {
        $('#btnAddUser').onclick = () => { $('#userAdd').hidden = false; $('#nuTen').focus(); };
        $('#btnCancelNew').onclick = () => { $('#userAdd').hidden = true; };
        $('#btnSaveNew').onclick = async () => {
          const msg = $('#nuMsg');
          try {
            await API.call('saveUser', { user: { ten: $('#nuTen').value, vai: $('#nuVai').value, boPhan: $('#nuBp').value, email: $('#nuEmail').value, pin: $('#nuPin').value, hoatDong: true } });
            $('#userAdd').hidden = true;
            ['nuTen', 'nuBp', 'nuEmail', 'nuPin'].forEach((id) => ($('#' + id).value = ''));
            toastFn('Đã tạo người dùng.');
            account.loadUsers();
          } catch (e) { msg.textContent = e.message; }
        };
        account.loadUsers();
      }
    },
    async loadUsers() {
      const tb = $('#userTable tbody');
      try {
        const r = await API.call('listUsers');
        this.users = r.users || [];
        const me = API.currentUser() || {};
        tb.innerHTML = this.users.map((u) => `<tr data-id="${u.id}">
          <td><input data-f="ten" value="${esc(u.ten)}" class="wide"></td>
          <td><select data-f="vai">${['Nhân viên', 'Kế toán', 'Admin'].map((v) => `<option ${u.vai === v ? 'selected' : ''}>${v}</option>`).join('')}</select></td>
          <td><input data-f="boPhan" value="${esc(u.boPhan)}"></td>
          <td><input data-f="email" value="${esc(u.email)}" class="wide"></td>
          <td><input data-f="pin" placeholder="${u.coPin ? '•••• (đặt lại)' : 'chưa có PIN'}" inputmode="numeric" maxlength="8" autocomplete="off" class="short" style="width:110px"></td>
          <td class="center"><input type="checkbox" data-f="hoatDong" ${u.hoatDong ? 'checked' : ''} ${u.id === me.id ? 'disabled' : ''}></td>
          <td class="act"><button class="icon-btn" data-save="${u.id}" title="Lưu">💾</button>${u.id !== me.id ? `<button class="icon-btn danger" data-del="${u.id}" title="Xoá">🗑</button>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="7" class="empty">Chưa có người dùng.</td></tr>';
        tb.onclick = async (e) => {
          const sv = e.target.closest('[data-save]');
          const del = e.target.closest('[data-del]');
          if (sv) {
            const tr = sv.closest('tr');
            const get = (f) => tr.querySelector(`[data-f="${f}"]`);
            const user = { id: +sv.dataset.save, ten: get('ten').value, vai: get('vai').value, boPhan: get('boPhan').value, email: get('email').value, hoatDong: get('hoatDong').checked };
            if (get('pin').value.trim()) user.pin = get('pin').value.trim();
            try { await API.call('saveUser', { user }); toastFn(`Đã lưu ${user.ten}.`); account.loadUsers(); } catch (err) { toastFn('Lỗi: ' + err.message); }
          }
          if (del) {
            const u = this.users.find((x) => x.id === +del.dataset.del);
            if (!u || !confirm(`Xoá người dùng "${u.ten}"?`)) return;
            try { await API.call('deleteUser', { id: u.id }); toastFn('Đã xoá.'); account.loadUsers(); } catch (err) { toastFn('Lỗi: ' + err.message); }
          }
        };
      } catch (e) {
        tb.innerHTML = `<tr><td colspan="7" class="empty">${esc(e.message)}</td></tr>`;
      }
    },
  };

  // ------------------------------------------------------------ tệp đính kèm (đọc file → base64 → uploadFile)
  async function uploadFiles(files, period, onProgress) {
    const out = [];
    for (const f of Array.from(files || [])) {
      if (f.size > 8 * 1024 * 1024) throw new Error(`"${f.name}" quá 8MB`);
      const b64 = await new Promise((res, rej) => { const r = new FileReader(); r.onload = () => res(String(r.result)); r.onerror = () => rej(new Error('Không đọc được tệp')); r.readAsDataURL(f); });
      if (onProgress) onProgress(f.name);
      const r = await API.call('uploadFile', { period, file: { name: f.name, type: f.type, base64: b64 } }, { timeout: 120000 });
      out.push(r.url);
    }
    return out;
  }
  function attChips(urls, removable) {
    return (urls || []).map((u, i) => {
      const name = decodeURIComponent(String(u).split('/').pop() || 'tệp');
      return `<span class="att-chip" title="${esc(name)}"><a href="${esc(u)}" target="_blank" rel="noopener">📎 ${esc(name.replace(/_\d{10}_[a-z0-9]{6}(\.\w+)$/i, '$1'))}</a>${removable ? `<button type="button" data-att-del="${i}" title="Bỏ tệp">✕</button>` : ''}</span>`;
    }).join(' ');
  }

  root.KHBC_UI = { gate, account, uploadFiles, attChips, setToast: (fn) => (toastFn = fn), ROLE_TAG };
})(typeof self !== 'undefined' ? self : this);
