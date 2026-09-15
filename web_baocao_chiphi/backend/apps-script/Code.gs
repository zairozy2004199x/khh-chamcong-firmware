/**
 * API "Báo cáo chi phí K&H" — Google Apps Script + Google Sheets.
 *
 * Triển khai: xem web_baocao_chiphi/backend/README.md
 *
 * Bảng dữ liệu (tự tạo khi chạy lần đầu):
 *   ChiPhi  — mỗi dòng một khoản chi phí do nhân viên / kế toán nhập
 *   State   — cấu hình + số liệu còn lại của từng kỳ (JSON, chia nhỏ nhiều ô vì Sheets giới hạn 50.000 ký tự/ô)
 *   NhatKy  — ai làm gì, lúc nào
 *
 * Phân quyền bằng 2 mã truy cập trong Script Properties:
 *   TOKEN_KETOAN — toàn quyền (kế toán)
 *   TOKEN_NHAP   — nhân viên: chỉ thêm / sửa / xoá khoản CỦA MÌNH khi còn "chờ duyệt"
 */

const API_VERSION = '1.0.0';
const SHEET_COSTS = 'ChiPhi';
const SHEET_STATE = 'State';
const SHEET_LOG = 'NhatKy';
const CHUNK = 45000; // ký tự mỗi ô JSON

const COST_COLS = [
  'id', 'period', 'status', 'createdAt', 'createdBy', 'updatedAt', 'updatedBy',
  'kind', 'name', 'misaGeneral', 'misaDetail', 'account', 'objectCode', 'total',
  'split', 'shares', 'excludeDepts', 'groupKey', 'method', 'note',
  'approvedBy', 'approvedAt', 'deleted', 'sortOrder',
];
const STATUSES = ['cho_duyet', 'da_duyet', 'tu_choi'];

// ------------------------------------------------------------------ Điểm vào HTTP
function doGet(e) {
  const p = (e && e.parameter) || {};
  if (!p.action) {
    return HtmlService.createHtmlOutput(
      '<p style="font-family:sans-serif">API Báo cáo chi phí K&amp;H đang chạy (v' + API_VERSION + '). ' +
      'Dán URL này vào phần <b>Kết nối máy chủ</b> của web app.</p>'
    );
  }
  return respond(handle(p));
}
function doPost(e) {
  let body = {};
  try {
    body = JSON.parse((e && e.postData && e.postData.contents) || '{}');
  } catch (err) {
    return respond({ ok: false, error: 'Body không phải JSON' });
  }
  return respond(handle(body));
}
function respond(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

// ------------------------------------------------------------------ Điều phối
function handle(req) {
  try {
    const action = String(req.action || '');
    if (action === 'ping') return { ok: true, version: API_VERSION, role: roleOf(req.token), now: new Date().toISOString() };
    const role = roleOf(req.token);
    if (!role) return { ok: false, error: 'Mã truy cập không đúng' };
    const user = String(req.user || '').trim().slice(0, 80) || (role === 'ketoan' ? 'Kế toán' : 'Nhân viên');
    const ctx = { role, user };
    switch (action) {
      case 'getPeriod': return getPeriod(ctx, req);
      case 'listPeriods': return { ok: true, periods: listPeriods() };
      case 'saveState': return withLock(() => saveState(ctx, req));
      case 'upsertCost': return withLock(() => upsertCosts(ctx, [req.item]));
      case 'upsertCosts': return withLock(() => upsertCosts(ctx, req.items || []));
      case 'deleteCost': return withLock(() => deleteCost(ctx, req.id));
      case 'setCostStatus': return withLock(() => setCostStatus(ctx, req.id, req.status));
      case 'setAllStatus': return withLock(() => setAllStatus(ctx, req.period, req.status));
      default: return { ok: false, error: 'Không có action: ' + action };
    }
  } catch (err) {
    return { ok: false, error: String(err && err.message || err) };
  }
}

function roleOf(token) {
  const props = PropertiesService.getScriptProperties();
  const t = String(token || '');
  if (!t) return null;
  if (t === props.getProperty('TOKEN_KETOAN')) return 'ketoan';
  if (t === props.getProperty('TOKEN_NHAP')) return 'nhap';
  return null;
}

function withLock(fn) {
  const lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    return fn();
  } finally {
    lock.releaseLock();
  }
}

// ------------------------------------------------------------------ Sheets
function ss() {
  return SpreadsheetApp.getActiveSpreadsheet();
}
function sheet(name, header) {
  let sh = ss().getSheetByName(name);
  if (!sh) {
    sh = ss().insertSheet(name);
    sh.appendRow(header);
    sh.setFrozenRows(1);
    sh.getRange(1, 1, 1, header.length).setFontWeight('bold');
  }
  return sh;
}
function costsSheet() {
  return sheet(SHEET_COSTS, COST_COLS);
}
function stateSheet() {
  return sheet(SHEET_STATE, ['period', 'version', 'updatedAt', 'updatedBy', 'json1']);
}
function logSheet() {
  return sheet(SHEET_LOG, ['time', 'user', 'role', 'action', 'detail']);
}
function log(ctx, action, detail) {
  try {
    logSheet().appendRow([new Date(), ctx.user, ctx.role, action, String(detail || '').slice(0, 500)]);
  } catch (e) { /* bỏ qua */ }
}
function periodKey(p) {
  if (!p) return '';
  if (typeof p === 'string') return p;
  return String(p.year) + '-' + String(p.month).padStart(2, '0');
}

// ------------------------------------------------------------------ Khoản chi phí
function readCosts(period) {
  const sh = costsSheet();
  const last = sh.getLastRow();
  if (last < 2) return { rows: [], index: {} };
  const values = sh.getRange(2, 1, last - 1, COST_COLS.length).getValues();
  const rows = [];
  const index = {};
  values.forEach((v, i) => {
    const o = {};
    COST_COLS.forEach((c, j) => (o[c] = v[j]));
    if (!o.id) return;
    index[o.id] = i + 2; // số dòng trên sheet
    if (period && o.period !== period) return;
    if (o.deleted === true || o.deleted === 'TRUE') return;
    rows.push(rowToItem(o));
  });
  rows.sort((a, b) => (a.sortOrder || 0) - (b.sortOrder || 0) || String(a.createdAt).localeCompare(String(b.createdAt)));
  return { rows, index };
}
function rowToItem(o) {
  const parse = (s, d) => { try { return s ? JSON.parse(s) : d; } catch (e) { return d; } };
  return {
    id: o.id, period: o.period, status: o.status || 'cho_duyet',
    createdAt: iso(o.createdAt), createdBy: o.createdBy, updatedAt: iso(o.updatedAt), updatedBy: o.updatedBy,
    kind: o.kind || 'company', name: o.name || '', misaGeneral: o.misaGeneral || '', misaDetail: o.misaDetail || '',
    account: o.account || '', objectCode: o.objectCode || '', total: Number(o.total) || 0,
    split: o.split || 'equal', shares: parse(o.shares, {}), excludeDepts: parse(o.excludeDepts, []),
    groupKey: o.groupKey || '', method: o.method || '', note: o.note || '',
    approvedBy: o.approvedBy || '', approvedAt: iso(o.approvedAt), sortOrder: Number(o.sortOrder) || 0,
  };
}
function iso(d) {
  if (!d) return '';
  if (d instanceof Date) return d.toISOString();
  return String(d);
}
function itemToRow(it) {
  return COST_COLS.map((c) => {
    if (c === 'shares' || c === 'excludeDepts') return JSON.stringify(it[c] || (c === 'shares' ? {} : []));
    if (c === 'deleted') return it.deleted === true;
    if (c === 'total' || c === 'sortOrder') return Number(it[c]) || 0;
    return it[c] === undefined || it[c] === null ? '' : it[c];
  });
}

function upsertCosts(ctx, items) {
  if (!Array.isArray(items) || !items.length) return { ok: false, error: 'Không có khoản nào' };
  const sh = costsSheet();
  const { index } = readCosts(null);
  const now = new Date().toISOString();
  const saved = [];
  const denied = [];
  items.forEach((raw) => {
    if (!raw || !raw.id || !raw.period) return;
    const existingRow = index[raw.id];
    let current = null;
    if (existingRow) {
      const v = sh.getRange(existingRow, 1, 1, COST_COLS.length).getValues()[0];
      const o = {};
      COST_COLS.forEach((c, j) => (o[c] = v[j]));
      current = rowToItem(o);
      current.deleted = o.deleted === true || o.deleted === 'TRUE';
    }
    // quyền nhân viên: chỉ khoản của mình và còn chờ duyệt
    if (ctx.role === 'nhap' && current && (current.createdBy !== ctx.user || current.status !== 'cho_duyet')) {
      denied.push(raw.id);
      return;
    }
    const it = Object.assign({}, current || {}, sanitizeItem(raw));
    it.id = raw.id;
    it.period = String(raw.period);
    it.deleted = false;
    it.updatedAt = now;
    it.updatedBy = ctx.user;
    if (!current) {
      it.createdAt = now;
      it.createdBy = ctx.user;
      it.status = ctx.role === 'ketoan' ? (STATUSES.includes(raw.status) ? raw.status : 'da_duyet') : 'cho_duyet';
      if (!it.sortOrder) it.sortOrder = Date.now();
    } else if (ctx.role === 'ketoan' && STATUSES.includes(raw.status) && raw.status !== current.status) {
      it.status = raw.status;
      it.approvedBy = ctx.user;
      it.approvedAt = now;
    } else {
      it.status = current.status;
    }
    if (existingRow) sh.getRange(existingRow, 1, 1, COST_COLS.length).setValues([itemToRow(it)]);
    else sh.appendRow(itemToRow(it));
    saved.push(it.id);
  });
  log(ctx, 'upsertCosts', saved.length + ' khoản: ' + items.map((i) => i && i.name).join(', ').slice(0, 300));
  return { ok: true, saved, denied };
}
function sanitizeItem(raw) {
  const s = (v, n) => String(v === undefined || v === null ? '' : v).slice(0, n || 500);
  const out = {
    kind: raw.kind === 'personal' ? 'personal' : 'company',
    name: s(raw.name), misaGeneral: s(raw.misaGeneral), misaDetail: s(raw.misaDetail),
    account: s(raw.account, 60), objectCode: s(raw.objectCode, 60), total: Number(raw.total) || 0,
    split: s(raw.split, 40) || 'equal', shares: typeof raw.shares === 'object' && raw.shares ? raw.shares : {},
    excludeDepts: Array.isArray(raw.excludeDepts) ? raw.excludeDepts.map(String) : [],
    groupKey: s(raw.groupKey, 60), method: s(raw.method, 20), note: s(raw.note, 1000),
  };
  if (raw.sortOrder !== undefined) out.sortOrder = Number(raw.sortOrder) || 0;
  return out;
}

function deleteCost(ctx, id) {
  if (!id) return { ok: false, error: 'Thiếu id' };
  const sh = costsSheet();
  const { index } = readCosts(null);
  const row = index[id];
  if (!row) return { ok: true, deleted: false };
  const v = sh.getRange(row, 1, 1, COST_COLS.length).getValues()[0];
  const o = {};
  COST_COLS.forEach((c, j) => (o[c] = v[j]));
  if (ctx.role === 'nhap' && (o.createdBy !== ctx.user || (o.status || 'cho_duyet') !== 'cho_duyet')) {
    return { ok: false, error: 'Chỉ xoá được khoản của mình khi còn chờ duyệt' };
  }
  sh.getRange(row, COST_COLS.indexOf('deleted') + 1).setValue(true);
  sh.getRange(row, COST_COLS.indexOf('updatedAt') + 1).setValue(new Date().toISOString());
  sh.getRange(row, COST_COLS.indexOf('updatedBy') + 1).setValue(ctx.user);
  log(ctx, 'deleteCost', id + ' ' + o.name);
  return { ok: true, deleted: true };
}

function setCostStatus(ctx, id, status) {
  if (ctx.role !== 'ketoan') return { ok: false, error: 'Chỉ kế toán được duyệt' };
  if (!STATUSES.includes(status)) return { ok: false, error: 'Trạng thái không hợp lệ' };
  const sh = costsSheet();
  const { index } = readCosts(null);
  const row = index[id];
  if (!row) return { ok: false, error: 'Không tìm thấy khoản' };
  const now = new Date().toISOString();
  sh.getRange(row, COST_COLS.indexOf('status') + 1).setValue(status);
  sh.getRange(row, COST_COLS.indexOf('approvedBy') + 1).setValue(ctx.user);
  sh.getRange(row, COST_COLS.indexOf('approvedAt') + 1).setValue(now);
  sh.getRange(row, COST_COLS.indexOf('updatedAt') + 1).setValue(now);
  log(ctx, 'setCostStatus', id + ' → ' + status);
  return { ok: true };
}
function setAllStatus(ctx, period, status) {
  if (ctx.role !== 'ketoan') return { ok: false, error: 'Chỉ kế toán được duyệt' };
  if (!STATUSES.includes(status)) return { ok: false, error: 'Trạng thái không hợp lệ' };
  const sh = costsSheet();
  const { rows, index } = readCosts(String(period));
  const now = new Date().toISOString();
  let n = 0;
  rows.forEach((r) => {
    if (r.status === 'cho_duyet') {
      const row = index[r.id];
      sh.getRange(row, COST_COLS.indexOf('status') + 1).setValue(status);
      sh.getRange(row, COST_COLS.indexOf('approvedBy') + 1).setValue(ctx.user);
      sh.getRange(row, COST_COLS.indexOf('approvedAt') + 1).setValue(now);
      n++;
    }
  });
  log(ctx, 'setAllStatus', period + ' → ' + status + ' (' + n + ')');
  return { ok: true, count: n };
}

// ------------------------------------------------------------------ State theo kỳ
function findStateRow(period) {
  const sh = stateSheet();
  const last = sh.getLastRow();
  if (last < 2) return 0;
  const keys = sh.getRange(2, 1, last - 1, 1).getValues();
  for (let i = 0; i < keys.length; i++) if (String(keys[i][0]) === period) return i + 2;
  return 0;
}
function readState(period) {
  const sh = stateSheet();
  const row = findStateRow(period);
  if (!row) return null;
  const width = sh.getLastColumn();
  const v = sh.getRange(row, 1, 1, width).getValues()[0];
  const json = v.slice(4).join('');
  let state = null;
  try { state = json ? JSON.parse(json) : null; } catch (e) { state = null; }
  return { version: Number(v[1]) || 0, updatedAt: iso(v[2]), updatedBy: v[3], state };
}
function saveState(ctx, req) {
  if (ctx.role !== 'ketoan') return { ok: false, error: 'Chỉ kế toán được lưu cấu hình' };
  const period = periodKey(req.period);
  if (!period) return { ok: false, error: 'Thiếu kỳ' };
  const st = req.state || {};
  delete st.costItems; // khoản chi phí sống ở sheet ChiPhi
  const json = JSON.stringify(st);
  const sh = stateSheet();
  const cur = readState(period);
  if (cur && req.version !== undefined && Number(req.version) !== cur.version) {
    return { ok: false, conflict: true, error: 'Kỳ này vừa được ' + cur.updatedBy + ' lưu lúc ' + cur.updatedAt + '. Tải lại rồi lưu.', current: cur };
  }
  const version = (cur ? cur.version : 0) + 1;
  const chunks = [];
  for (let i = 0; i < json.length; i += CHUNK) chunks.push(json.slice(i, i + CHUNK));
  if (!chunks.length) chunks.push('');
  const rowVals = [period, version, new Date().toISOString(), ctx.user].concat(chunks);
  let row = findStateRow(period);
  if (!row) {
    sh.appendRow([period]);
    row = sh.getLastRow();
  }
  // xoá phần JSON cũ (có thể dài hơn) rồi ghi mới
  const width = Math.max(sh.getLastColumn(), rowVals.length);
  sh.getRange(row, 1, 1, width).clearContent();
  sh.getRange(row, 1, 1, rowVals.length).setValues([rowVals]);
  log(ctx, 'saveState', period + ' v' + version + ' (' + json.length + ' ký tự)');
  return { ok: true, version, updatedAt: rowVals[2] };
}
function listPeriods() {
  const set = {};
  const sh = stateSheet();
  if (sh.getLastRow() >= 2) sh.getRange(2, 1, sh.getLastRow() - 1, 1).getValues().forEach((r) => { if (r[0]) set[r[0]] = true; });
  const cs = costsSheet();
  if (cs.getLastRow() >= 2) cs.getRange(2, 2, cs.getLastRow() - 1, 1).getValues().forEach((r) => { if (r[0]) set[r[0]] = true; });
  return Object.keys(set).sort();
}
function getPeriod(ctx, req) {
  const period = periodKey(req.period);
  if (!period) return { ok: false, error: 'Thiếu kỳ' };
  const st = readState(period);
  const costs = readCosts(period).rows;
  if (ctx.role === 'nhap') {
    // nhân viên chỉ cần danh mục nhóm / bộ phận để chọn kiểu chia
    const s = (st && st.state) || {};
    return { ok: true, period, role: ctx.role, costs, groups: s.groups || [], departments: (s.departments || []).map((d) => ({ id: d.id, name: d.name, group: d.group })) };
  }
  return { ok: true, period, role: ctx.role, state: st ? st.state : null, version: st ? st.version : 0, updatedAt: st ? st.updatedAt : '', updatedBy: st ? st.updatedBy : '', costs, periods: listPeriods() };
}

// ------------------------------------------------------------------ Tiện ích chạy tay trong trình soạn thảo
/** Chạy 1 lần sau khi tạo script: đặt mã truy cập. Đổi 2 chuỗi dưới rồi bấm Run. */
function CAI_DAT_MA_TRUY_CAP() {
  PropertiesService.getScriptProperties().setProperties({
    TOKEN_KETOAN: 'DOI-MA-KE-TOAN-' + Utilities.getUuid().slice(0, 8),
    TOKEN_NHAP: 'DOI-MA-NHAN-VIEN-' + Utilities.getUuid().slice(0, 8),
  });
  costsSheet(); stateSheet(); logSheet();
  Logger.log(JSON.stringify(PropertiesService.getScriptProperties().getProperties(), null, 2));
}
/** Xem mã truy cập hiện tại (View → Logs). */
function XEM_MA_TRUY_CAP() {
  Logger.log(JSON.stringify(PropertiesService.getScriptProperties().getProperties(), null, 2));
}
