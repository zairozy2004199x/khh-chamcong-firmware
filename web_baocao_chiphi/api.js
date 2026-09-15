/*
 * api.js — Kết nối tới API Google Apps Script (backend/apps-script/Code.gs).
 * Cấu hình (URL, mã truy cập, tên người dùng) lưu ở localStorage, dùng chung cho index.html và nhap.html.
 */
(function (root) {
  'use strict';
  const KEY = 'khh_baocao_chiphi_api';

  function getConfig() {
    try {
      return Object.assign({ url: '', token: '', user: '', enabled: false }, JSON.parse(localStorage.getItem(KEY) || '{}'));
    } catch (e) {
      return { url: '', token: '', user: '', enabled: false };
    }
  }
  function setConfig(cfg) {
    const c = Object.assign(getConfig(), cfg || {});
    c.url = String(c.url || '').trim();
    c.token = String(c.token || '').trim();
    c.user = String(c.user || '').trim();
    try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) { /* bỏ qua */ }
    return c;
  }
  function isEnabled() {
    const c = getConfig();
    return !!(c.enabled && c.url && c.token);
  }
  function periodKey(p) {
    return `${p.year}-${String(p.month).padStart(2, '0')}`;
  }

  /**
   * Gọi API. Dùng POST với Content-Type text/plain để không có preflight CORS
   * (Apps Script theo redirect và trả header cho phép mọi origin).
   */
  async function call(action, payload, opts) {
    const cfg = Object.assign(getConfig(), (opts && opts.config) || {});
    if (!cfg.url) throw new Error('Chưa cấu hình URL máy chủ');
    const body = JSON.stringify(Object.assign({ action, token: cfg.token, user: cfg.user }, payload || {}));
    const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), (opts && opts.timeout) || 30000) : null;
    let res;
    try {
      res = await fetch(cfg.url, {
        method: 'POST',
        headers: { 'Content-Type': 'text/plain;charset=utf-8' },
        body,
        redirect: 'follow',
        signal: ctrl ? ctrl.signal : undefined,
      });
    } catch (e) {
      throw new Error(e.name === 'AbortError' ? 'Máy chủ không phản hồi (quá 30 giây)' : 'Không kết nối được máy chủ: ' + e.message);
    } finally {
      if (timer) clearTimeout(timer);
    }
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      throw new Error('Máy chủ trả về không phải JSON. Kiểm tra lại URL (phải là URL "Web app" kết thúc bằng /exec) và quyền truy cập "Bất kỳ ai".');
    }
    if (!data.ok) {
      const err = new Error(data.error || 'Lỗi máy chủ');
      err.data = data;
      throw err;
    }
    return data;
  }

  root.BaoCaoApi = { KEY, getConfig, setConfig, isEnabled, periodKey, call };
})(typeof self !== 'undefined' ? self : this);
