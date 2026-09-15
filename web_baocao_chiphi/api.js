/*
 * api.js — Lớp gọi máy chủ, dùng chung cho trang kế toán (app.js) và trang nhân viên (nhap.js).
 *
 * Hai chế độ, tự chọn theo window.KHBC_CFG:
 *   - 'gas' (mặc định, trang tĩnh trên GitHub Pages): gọi Google Apps Script bằng URL + mã truy cập
 *     do người dùng nhập; cấu hình lưu localStorage.
 *   - 'wp'  (plugin WordPress): gọi REST /wp-json/khbc/v1/call {fn, args, token}; đăng nhập PIN → thẻ
 *     phiên trong localStorage; tự đổi đường (rest → admin-ajax → URL app) khi hosting chặn.
 *
 * Hợp đồng với app.js không đổi: call(action, payload) → Promise<{ok:true, …}>; lỗi → throw Error
 * kèm e.data (conflict, code…). getConfig().user là tên người đang dùng.
 */
(function (root) {
  'use strict';
  const CFG = root.KHBC_CFG || null;
  const MODE = CFG && CFG.mode === 'wp' ? 'wp' : 'gas';
  const KEY = 'khh_baocao_chiphi_api';
  const TOKEN_KEY = 'khbc_token';
  const DUONG_KEY = 'khbc_duong';

  // ------------------------------------------------------------------ cấu hình (chế độ gas)
  function getConfig() {
    if (MODE === 'wp') {
      const u = wpUser || {};
      return { mode: 'wp', url: CFG.endpoint, token: getToken(), user: u.ten || '', role: u.vaiApp || '', vai: u.vai || '', enabled: !!getToken(), userInfo: u };
    }
    try {
      return Object.assign({ mode: 'gas', url: '', token: '', user: '', enabled: false }, JSON.parse(localStorage.getItem(KEY) || '{}'));
    } catch (e) {
      return { mode: 'gas', url: '', token: '', user: '', enabled: false };
    }
  }
  function setConfig(cfg) {
    if (MODE === 'wp') return getConfig();
    const c = Object.assign(getConfig(), cfg || {});
    c.url = String(c.url || '').trim();
    c.token = String(c.token || '').trim();
    c.user = String(c.user || '').trim();
    try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) { /* bỏ qua */ }
    return c;
  }
  function isEnabled() {
    if (MODE === 'wp') return !!getToken() && !!wpUser;
    const c = getConfig();
    return !!(c.enabled && c.url && c.token);
  }
  function periodKey(p) {
    return `${p.year}-${String(p.month).padStart(2, '0')}`;
  }

  // ------------------------------------------------------------------ phiên (chế độ wp)
  let wpUser = null;
  function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
  function setToken(t) { try { if (t) localStorage.setItem(TOKEN_KEY, t); else localStorage.removeItem(TOKEN_KEY); } catch (e) { /* bỏ qua */ } }
  const listeners = { auth: [] };
  function onAuth(fn) { listeners.auth.push(fn); }
  function emitAuth() { listeners.auth.forEach((f) => { try { f(wpUser); } catch (e) { /* bỏ qua */ } }); }

  // ------------------------------------------------------------------ gửi (chế độ wp): 3 đường
  const THU_TU = ['rest', 'ajax', 'trang'];
  let duong = 'rest';
  try { const d = localStorage.getItem(DUONG_KEY); if (THU_TU.includes(d)) duong = d; } catch (e) { /* bỏ qua */ }
  function nhoDuong(d) { duong = d; try { localStorage.setItem(DUONG_KEY, d); } catch (e) { /* bỏ qua */ } }
  function coDuong(d) { return d === 'rest' ? !!CFG.endpoint : d === 'ajax' ? !!CFG.ajax : !!CFG.trang; }
  function gui(d, fn, args, tok, signal) {
    if (d === 'rest') {
      return fetch(CFG.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-KHBC-Token': tok }, body: JSON.stringify({ fn, args, token: tok }), signal });
    }
    if (d === 'ajax') {
      const fd = new FormData();
      fd.append('action', 'khbc_call'); fd.append('fn', fn); fd.append('args', JSON.stringify(args || {})); fd.append('token', tok);
      return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: fd, signal });
    }
    const b = 'fn=' + encodeURIComponent(fn) + '&args=' + encodeURIComponent(JSON.stringify(args || {})) + '&token=' + encodeURIComponent(tok);
    return fetch(CFG.trang, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: b, signal });
  }
  async function docKetQua(res) {
    const txt = await res.text();
    let j = null;
    try { j = JSON.parse(txt); } catch (e) { /* không phải JSON */ }
    return { status: res.status, json: j, raw: txt };
  }
  const biChan = (r) => !(r.json && typeof r.json.ok !== 'undefined') && (r.status === 403 || r.status === 404 || r.status === 405 || r.status === 0 || !r.json);

  async function callWp(fn, args, opts) {
    const tok = getToken();
    const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), (opts && opts.timeout) || 45000) : null;
    const daThu = [];
    let r = null;
    try {
      let d = coDuong(duong) ? duong : 'rest';
      for (;;) {
        daThu.push(d);
        try {
          r = await docKetQua(await gui(d, fn, args, tok, ctrl ? ctrl.signal : undefined));
        } catch (e) {
          if (e.name === 'AbortError') throw new Error('Máy chủ không phản hồi (quá 45 giây)');
          r = { status: 0, json: null, raw: String(e.message || e) };
        }
        if (!biChan(r)) { if (d !== duong) nhoDuong(d); break; }
        const ke = THU_TU.find((x) => !daThu.includes(x) && coDuong(x));
        if (!ke) { try { localStorage.removeItem(DUONG_KEY); } catch (e) { /* bỏ qua */ } break; }
        d = ke;
      }
    } finally {
      if (timer) clearTimeout(timer);
    }
    const j = r.json;
    if (r.status === 401 && j && j.code === 'no_session') {
      setToken('');
      wpUser = null;
      emitAuth();
      const err = new Error('Phiên đã hết — đăng nhập lại bằng PIN');
      err.data = j;
      throw err;
    }
    if (!j) {
      const dau = String(r.raw || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120);
      const laCf = /checking your browser|just a moment|cloudflare|attention required/i.test(String(r.raw || ''));
      throw new Error(laCf
        ? 'Tường lửa (Cloudflare/hosting) đang chặn các đường gọi máy chủ. Mở ' + CFG.trang + ' trên trình duyệt để kiểm tra; tắt Bot Fight Mode nếu dùng Cloudflare.'
        : 'Không gọi được máy chủ (' + r.status + ') qua ' + daThu.join(' → ') + (dau ? ' — trả về: "' + dau + '"' : ''));
    }
    if (j.ok !== true) {
      const err = new Error(j.error || 'Lỗi máy chủ (' + r.status + ')');
      err.data = j;
      throw err;
    }
    return Object.assign({ ok: true }, j.data || {});
  }

  // ------------------------------------------------------------------ gửi (chế độ gas)
  async function callGas(action, payload, opts) {
    const cfg = Object.assign(getConfig(), (opts && opts.config) || {});
    if (!cfg.url) throw new Error('Chưa cấu hình URL máy chủ');
    const body = JSON.stringify(Object.assign({ action, token: cfg.token, user: cfg.user }, payload || {}));
    const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), (opts && opts.timeout) || 30000) : null;
    let res;
    try {
      res = await fetch(cfg.url, { method: 'POST', headers: { 'Content-Type': 'text/plain;charset=utf-8' }, body, redirect: 'follow', signal: ctrl ? ctrl.signal : undefined });
    } catch (e) {
      throw new Error(e.name === 'AbortError' ? 'Máy chủ không phản hồi (quá 30 giây)' : 'Không kết nối được máy chủ: ' + e.message);
    } finally {
      if (timer) clearTimeout(timer);
    }
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch (e) {
      throw new Error('Máy chủ trả về không phải JSON. Kiểm tra lại URL (phải là URL "Web app" kết thúc bằng /exec) và quyền truy cập "Bất kỳ ai".');
    }
    if (!data.ok) { const err = new Error(data.error || 'Lỗi máy chủ'); err.data = data; throw err; }
    return data;
  }

  function call(action, payload, opts) {
    return MODE === 'wp' ? callWp(action, payload || {}, opts) : callGas(action, payload, opts);
  }

  // ------------------------------------------------------------------ đăng nhập (wp)
  async function login(pin) {
    const r = await callWp('login', { pin });
    if (r.user && r.user.token) { setToken(r.user.token); wpUser = r.user; emitAuth(); }
    return r.user;
  }
  async function whoami() {
    if (MODE !== 'wp') return null;
    if (!getToken()) { wpUser = null; return null; }
    try {
      const r = await callWp('aiDangDangNhap', { token: getToken() });
      wpUser = r.user || null;
    } catch (e) {
      if (!(e.data && e.data.code === 'no_session')) throw e;
      wpUser = null;
    }
    emitAuth();
    return wpUser;
  }
  async function logout() {
    if (MODE !== 'wp') return;
    try { await callWp('logout', { token: getToken() }); } catch (e) { /* bỏ qua */ }
    setToken('');
    wpUser = null;
    emitAuth();
  }
  function currentUser() { return wpUser; }

  root.BaoCaoApi = { MODE, CFG, KEY, getConfig, setConfig, isEnabled, periodKey, call, login, logout, whoami, currentUser, onAuth, getToken };
})(typeof self !== 'undefined' ? self : this);
