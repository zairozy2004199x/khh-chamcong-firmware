/*
 * api.js — Lớp gọi máy chủ cho bản WordPress.
 *
 * Hai chế độ, tự chọn theo window.KHUNC_CFG:
 *   - 'local' (mặc định — trang tĩnh / GitHub Pages): không có máy chủ, mọi thứ nằm ở localStorage.
 *   - 'wp'    (plugin WordPress): gọi REST /wp-json/khunc/v1/call {fn, args, token};
 *             đăng nhập PIN → thẻ phiên; tự đổi đường (rest → admin-ajax → URL app) khi hosting chặn.
 *
 * Hợp đồng: call(fn, args) → Promise<{ok:true, …}>; lỗi thì throw Error kèm e.data.
 */
(function (root) {
  'use strict';

  const CFG = root.KHUNC_CFG || null;
  const MODE = CFG && CFG.mode === 'wp' ? 'wp' : 'local';
  const TOKEN_KEY = 'khunc_token';
  const DUONG_KEY = 'khunc_duong';

  let nguoiDung = null;
  const nghe = [];

  function getToken() { try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; } }
  function setToken(t) { try { if (t) localStorage.setItem(TOKEN_KEY, t); else localStorage.removeItem(TOKEN_KEY); } catch (e) { /* bỏ qua */ } }
  function onAuth(fn) { nghe.push(fn); }
  function phatAuth() { nghe.forEach((f) => { try { f(nguoiDung); } catch (e) { /* bỏ qua */ } }); }
  function currentUser() { return nguoiDung; }

  /** Vai app: 'ketoan' (nhập + đánh dấu) | 'xem' (chỉ đọc) | '' (chưa đăng nhập). */
  function vaiApp() { return nguoiDung ? nguoiDung.vaiApp || '' : ''; }
  function laAdmin() { return !!(nguoiDung && nguoiDung.vai === 'Admin'); }
  function duocSua() { return MODE !== 'wp' || vaiApp() === 'ketoan'; }

  /* ---------------------------------------------------------------- 3 đường gọi */

  const THU_TU = ['rest', 'ajax', 'trang'];
  let duong = 'rest';
  try { const d = localStorage.getItem(DUONG_KEY); if (THU_TU.indexOf(d) >= 0) duong = d; } catch (e) { /* bỏ qua */ }
  function nhoDuong(d) { duong = d; try { localStorage.setItem(DUONG_KEY, d); } catch (e) { /* bỏ qua */ } }
  function coDuong(d) { return d === 'rest' ? !!CFG.endpoint : d === 'ajax' ? !!CFG.ajax : !!CFG.trang; }

  function gui(d, fn, args, tok, signal) {
    if (d === 'rest') {
      return fetch(CFG.endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-KHUNC-Token': tok },
        body: JSON.stringify({ fn: fn, args: args, token: tok }), signal: signal,
      });
    }
    if (d === 'ajax') {
      const fd = new FormData();
      fd.append('action', 'khunc_call');
      fd.append('fn', fn);
      fd.append('args', JSON.stringify(args || {}));
      fd.append('token', tok);
      return fetch(CFG.ajax, { method: 'POST', credentials: 'same-origin', body: fd, signal: signal });
    }
    const b = 'fn=' + encodeURIComponent(fn) +
      '&args=' + encodeURIComponent(JSON.stringify(args || {})) +
      '&token=' + encodeURIComponent(tok);
    return fetch(CFG.trang, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: b, signal: signal,
    });
  }

  async function docKetQua(res) {
    const txt = await res.text();
    let j = null;
    try { j = JSON.parse(txt); } catch (e) { /* không phải JSON */ }
    return { status: res.status, json: j, raw: txt };
  }
  const biChan = (r) =>
    !(r.json && typeof r.json.ok !== 'undefined') &&
    (r.status === 403 || r.status === 404 || r.status === 405 || r.status === 0 || !r.json);

  async function callWp(fn, args, opts) {
    const tok = getToken();
    const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    // Nhập cả file Excel lên một lần nên để rộng hơn các lời gọi thường.
    const han = (opts && opts.timeout) || 90000;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), han) : null;
    const daThu = [];
    let r = null;
    try {
      let d = coDuong(duong) ? duong : 'rest';
      for (;;) {
        daThu.push(d);
        try {
          r = await docKetQua(await gui(d, fn, args, tok, ctrl ? ctrl.signal : undefined));
        } catch (e) {
          if (e.name === 'AbortError') throw new Error('Máy chủ không phản hồi (quá ' + Math.round(han / 1000) + ' giây)');
          r = { status: 0, json: null, raw: String(e.message || e) };
        }
        if (!biChan(r)) { if (d !== duong) nhoDuong(d); break; }
        const ke = THU_TU.filter((x) => daThu.indexOf(x) < 0 && coDuong(x))[0];
        if (!ke) { try { localStorage.removeItem(DUONG_KEY); } catch (e) { /* bỏ qua */ } break; }
        d = ke;
      }
    } finally {
      if (timer) clearTimeout(timer);
    }

    const j = r.json;
    if (r.status === 401 && j && j.code === 'no_session') {
      setToken('');
      nguoiDung = null;
      phatAuth();
      const err = new Error('Phiên đã hết — đăng nhập lại bằng PIN');
      err.data = j;
      throw err;
    }
    if (!j) {
      const dau = String(r.raw || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 120);
      const laCf = /checking your browser|just a moment|cloudflare|attention required/i.test(String(r.raw || ''));
      throw new Error(laCf
        ? 'Tường lửa (Cloudflare/hosting) đang chặn lời gọi máy chủ. Mở ' + CFG.trang +
          ' trên trình duyệt để kiểm tra; tắt Bot Fight Mode nếu dùng Cloudflare.'
        : 'Không gọi được máy chủ (' + r.status + ') qua ' + daThu.join(' → ') +
          (dau ? ' — trả về: "' + dau + '"' : ''));
    }
    if (j.ok !== true) {
      const err = new Error(j.error || 'Lỗi máy chủ (' + r.status + ')');
      err.data = j;
      throw err;
    }
    return Object.assign({ ok: true }, j.data || {});
  }

  function call(fn, args, opts) {
    if (MODE !== 'wp') return Promise.reject(new Error('Trang đang chạy cục bộ, không có máy chủ.'));
    return callWp(fn, args || {}, opts);
  }

  /* ---------------------------------------------------------------- đăng nhập */

  async function login(pin) {
    const r = await callWp('login', { pin: pin });
    if (r.user && r.user.token) {
      setToken(r.user.token);
      nguoiDung = r.user;
      phatAuth();
    }
    return r.user;
  }
  async function whoami() {
    if (MODE !== 'wp') return null;
    if (!getToken()) { nguoiDung = null; return null; }
    try {
      const r = await callWp('aiDangDangNhap', { token: getToken() });
      nguoiDung = r.user || null;
    } catch (e) {
      if (!(e.data && e.data.code === 'no_session')) throw e;
      nguoiDung = null;
    }
    phatAuth();
    return nguoiDung;
  }
  async function logout() {
    if (MODE !== 'wp') return;
    try { await callWp('logout', { token: getToken() }); } catch (e) { /* bỏ qua */ }
    setToken('');
    nguoiDung = null;
    phatAuth();
  }

  root.UNCApi = {
    MODE, CFG, call, login, logout, whoami, currentUser, onAuth, getToken,
    vaiApp, laAdmin, duocSua,
  };
})(typeof self !== 'undefined' ? self : this);
