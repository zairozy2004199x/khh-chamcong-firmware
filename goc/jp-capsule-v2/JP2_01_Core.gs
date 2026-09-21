/*═══════════════════════════════════════════════════════════════════════════
 * JP CAPSULE v2 — WEB NHÂN VIÊN  ·  01_Core
 * ---------------------------------------------------------------------------
 * Lớp truy cập dữ liệu duy nhất. KHÔNG file nào khác được gọi thẳng
 * SpreadsheetApp — sai ở đâu chỉ cần sửa trong file này.
 *
 * Cung cấp: jpDb_ · jpSheet_ · jpRows_ · jpAppend_ · jpUpdate_ · jpFields_
 *           jpDelete_ · jpNextId_ · jpAudit_ · jpLock_ · tiện ích ngày/số
 *═══════════════════════════════════════════════════════════════════════════*/

/*──────────────────── ① KẾT NỐI & CACHE ────────────────────*/

var JP__SS = null;
var JP__VALS = {};   // cache giá trị theo tab
var JP__HDR  = {};   // cache header theo tab

function jpDb_() {
  if (!JP__SS) JP__SS = SpreadsheetApp.openById(jpSheetId_());
  return JP__SS;
}

/**
 * Đã soát cột cho tab nào trong LẦN CHẠY NÀY.
 *
 * `jpSheet_` được gọi ở MỌI lượt đọc và ghi, và `jpEnsureCols_` đọc lại dòng header
 * mỗi lượt — mỗi lần đọc đó là **một lượt gọi sang Sheets**. Đo được (`test/toc-do.js`):
 * một hàm chạm 8 tab thì mất 8 lượt đọc chỉ để soát cột, trong khi cột chỉ đổi khi
 * CHÍNH TA thêm vào. Nhớ lại trong lần chạy là bỏ hết số lượt đó.
 *
 * ⚠️ Đây là cờ theo LẦN CHẠY, không phải cache lâu dài: mỗi lần gọi web app là một
 * lần chạy mới nên vẫn soát lại. `jpResetKetNoi_` xoá cờ vì đổi sheet là đổi cả bộ
 * cột. Và `jpEnsureCols_` khi thêm cột thì xoá cờ của đúng tab đó.
 */
var JP__COLS_OK = {};

/** Lấy sheet theo tên, tự tạo kèm header nếu chưa có. */
/**
 * ĐỌC THÔ một tab của CHÍNH sheet dữ liệu JP — giá trị nguyên bản, không map theo tên cột.
 *
 * Dùng cho tab do CÔNG THỨC sinh ra (`JP_BankFeed` = `QUERY(IMPORTRANGE(...))`): tiêu đề
 * ở đó là của nguồn ngoài, không phải schema của mình, nên `jpRows_` không áp được.
 * Trả `null` khi chưa có tab — để hàm gọi biết mà rơi sang đường khác.
 */
function jpDocTabTho_(tenTab, soCot) {
  var sh = jpDb_().getSheetByName(tenTab);
  if (!sh) return null;
  var n = sh.getLastRow();
  if (n < 1) return [];
  return sh.getRange(1, 1, n, soCot || sh.getLastColumn()).getValues();
}

/**
 * ĐỌC MỘT SHEET NGOÀI (nguồn giao dịch ngân hàng) — **chỉ đọc, không bao giờ ghi**.
 *
 * Đây là chỗ DUY NHẤT ngoài `jpDb_` được đụng `SpreadsheetApp`, và nó nằm ở `01_Core`
 * đúng theo kiến trúc "một cửa truy cập dữ liệu".
 *
 * ⚠️ Trả về **giá trị thô** (mảng của mảng), không dựng object theo tên cột: sheet ngoài
 * không do mình quản, hàng tiêu đề đổi lúc nào không biết. Ai gọi thì tự map bằng
 * `JP_BANK_COT` — và map sai thì thấy ngay ở bảng xem trước, chứ không lặng lẽ ra
 * `undefined`.
 *
 * ⚠️ Hỏng quyền thì ném lỗi NÓI RÕ phải làm gì. Web app chạy bằng quyền chủ sở hữu
 * project (`executeAs: USER_DEPLOYING`) nên sheet phải được chia sẻ cho CHÍNH tài khoản
 * đó — không phải cho người đang đăng nhập web.
 */
function jpDocSheetNgoai_(sheetId, tenTab, soCot) {
  var id = jpStr_(sheetId);
  if (!id) throw new Error('Chưa đặt JP_BANK_SHEET_ID trong Script Properties');
  var ss;
  try {
    ss = SpreadsheetApp.openById(id);
  } catch (e) {
    throw new Error('Không mở được sheet nguồn ' + id + ' (' + e.message +
      '). Chia sẻ sheet đó cho tài khoản CHỦ SỞ HỮU project Apps Script, quyền Người xem là đủ.');
  }
  var sh = ss.getSheetByName(tenTab);
  if (!sh) throw new Error('Sheet nguồn không có tab "' + tenTab + '"');
  var n = sh.getLastRow();
  if (n < 2) return [];
  return sh.getRange(1, 1, n, soCot || sh.getLastColumn()).getValues();
}

function jpSheet_(tabDef) {
  var ss = jpDb_();
  var sh = ss.getSheetByName(tabDef.name);
  if (!sh) {
    sh = ss.insertSheet(tabDef.name);
    sh.getRange(1, 1, 1, tabDef.cols.length).setValues([tabDef.cols]);
    sh.setFrozenRows(1);
    JP__COLS_OK[tabDef.name] = 1;            // vừa tạo đúng bộ cột, khỏi soát lại
    return sh;
  }
  if (!JP__COLS_OK[tabDef.name]) {
    jpEnsureCols_(sh, tabDef);
    JP__COLS_OK[tabDef.name] = 1;
  }
  return sh;
}

/** Bổ sung cột thiếu vào cuối — cho phép nâng cấp schema không mất dữ liệu. */
function jpEnsureCols_(sh, tabDef) {
  var last = sh.getLastColumn();
  var cur = last ? sh.getRange(1, 1, 1, last).getValues()[0].map(String) : [];
  var missing = tabDef.cols.filter(function (c) { return cur.indexOf(c) < 0; });
  if (!missing.length) return;
  sh.getRange(1, cur.length + 1, 1, missing.length).setValues([missing]);
  jpClearCache_(tabDef.name);
  delete JP__COLS_OK[tabDef.name];           // vừa đổi header, lần sau soát lại
}

/**
 * Bỏ kết nối và cache đang giữ, để lần đọc sau mở lại sheet theo cấu hình mới.
 * Dùng khi vừa đổi `JP_SHEET_ID` giữa một lần chạy (xem `jpDatCauHinh_`).
 */
function jpResetKetNoi_() {
  JP__SS = null;
  JP__COLS_OK = {};                          // sheet khác thì bộ cột cũng khác
  jpClearCache_();
}

function jpClearCache_(tabName) {
  if (tabName) { delete JP__VALS[tabName]; delete JP__HDR[tabName]; }
  else { JP__VALS = {}; JP__HDR = {}; }
}

/*──────────────────── ② ĐỌC ────────────────────*/

function jpVals_(tabDef) {
  var n = tabDef.name;
  if (JP__VALS[n]) return JP__VALS[n];
  var sh = jpSheet_(tabDef);
  var v = sh.getLastRow() ? sh.getDataRange().getValues() : [tabDef.cols];
  JP__VALS[n] = v;
  JP__HDR[n] = v[0].map(String);
  return v;
}

function jpHdr_(tabDef) {
  if (!JP__HDR[tabDef.name]) jpVals_(tabDef);
  return JP__HDR[tabDef.name];
}

/**
 * Đọc toàn bộ tab thành mảng object, kèm _row (số dòng thật để ghi).
 * ĐỌC 1 LẦN — tuyệt đối không gọi trong vòng lặp.
 */
function jpRows_(tabDef) {
  var v = jpVals_(tabDef), h = v[0].map(String), out = [];
  for (var i = 1; i < v.length; i++) {
    var o = { _row: i + 1 }, empty = true;
    for (var j = 0; j < h.length; j++) {
      o[h[j]] = v[i][j];
      if (v[i][j] !== '' && v[i][j] !== null) empty = false;
    }
    if (!empty) out.push(o);
  }
  return out;
}

/**
 * Lọc nhanh theo 1 cặp key/value.
 *
 * LỌC TRÊN GIÁ TRỊ THÔ RỒI MỚI DỰNG OBJECT (sửa 04/08/2026). Bản trước là
 * `jpRows_(tabDef).filter(...)` — dựng object cho **mọi** dòng rồi bỏ gần hết.
 * `jpFind_(JP_TABS.ROWS, 'reportId', id)` trên bảng 4.000 dòng × 45 cột là **180.000
 * phép gán** để giữ lại 20 dòng, và hàm này được gọi ở khắp nơi (`jpGetReport`,
 * `jpSubmitReport`, `jpXuatKhoBaoCao_`, `jpHoanKhoBaoCao_`…).
 *
 * Số lượt gọi Sheets KHÔNG đổi (`jpVals_` vẫn cache dữ liệu thô) — đây là tiết kiệm
 * CPU, và nó lớn dần theo tháng vì `JP_Rows` chỉ có thêm chứ không bớt.
 *
 * ⚠️ Vẫn trả về **object MỚI mỗi lượt gọi**, y như `jpRows_`. Nhiều hàm gán trường
 * tạm vào chính object đó (`__kho`, `__con`, `__daTru` của FIFO); dùng lại object là
 * dữ liệu lần trước còn dính.
 *
 * ⚠️ Cột không có trong header thì trả **mảng rỗng**, đúng như trước (`r[key]` là
 * `undefined`, `String(undefined) !== s` với mọi `s` là chuỗi thật).
 */
function jpFind_(tabDef, key, val) {
  var v = jpVals_(tabDef), h = v[0].map(String);
  var c = h.indexOf(key);
  if (c < 0) return [];
  var s = String(val), out = [];
  for (var i = 1; i < v.length; i++) {
    if (String(v[i][c]) !== s) continue;
    var o = { _row: i + 1 }, empty = true;
    for (var j = 0; j < h.length; j++) {
      o[h[j]] = v[i][j];
      if (v[i][j] !== '' && v[i][j] !== null) empty = false;
    }
    if (!empty) out.push(o);
  }
  return out;
}

function jpFindOne_(tabDef, key, val) {
  var r = jpFind_(tabDef, key, val);
  return r.length ? r[0] : null;
}

/*──────────────────── ③ GHI ────────────────────*/

/** Thêm 1 dòng từ object. Trả về object đã ghi (kèm _row). */
function jpAppend_(tabDef, obj) {
  var sh = jpSheet_(tabDef), h = jpHdr_(tabDef);
  var row = h.map(function (c) {
    return (obj[c] === undefined || obj[c] === null) ? '' : obj[c];
  });
  sh.appendRow(row);
  jpClearCache_(tabDef.name);
  obj._row = sh.getLastRow();
  return obj;
}

/**
 * Thêm NHIỀU dòng bằng MỘT lần ghi. Dùng thay cho vòng lặp gọi jpAppend_ —
 * mỗi appendRow là một lượt gọi sang Sheets, 50 dòng là 50 lượt.
 * Rỗng thì không đụng sheet. Trả về số dòng đã ghi.
 */
function jpAppendMany_(tabDef, rows) {
  if (!rows || !rows.length) return 0;
  var sh = jpSheet_(tabDef), h = jpHdr_(tabDef);
  var block = rows.map(function (o) {
    return h.map(function (c) {
      return (o[c] === undefined || o[c] === null) ? '' : o[c];
    });
  });
  sh.getRange(sh.getLastRow() + 1, 1, block.length, h.length).setValues(block);
  jpClearCache_(tabDef.name);
  return block.length;
}

/** Ghi đè cả dòng theo object (chỉ các cột có mặt trong obj). */
function jpUpdate_(tabDef, rowIndex, obj) {
  var sh = jpSheet_(tabDef), h = jpHdr_(tabDef);
  var rng = sh.getRange(rowIndex, 1, 1, h.length);
  var cur = rng.getValues()[0], changed = false;
  for (var j = 0; j < h.length; j++) {
    if (obj[h[j]] !== undefined) { cur[j] = obj[h[j]]; changed = true; }
  }
  if (changed) rng.setValues([cur]);
  jpClearCache_(tabDef.name);
}

/**
 * CHỈ ghi đúng những ô được liệt kê — dùng cho đối soát nộp tiền.
 * Bài học POSH v3 #1: không bao giờ ghi đè ô doanh thu khi áp đối soát.
 *
 * GOM Ô LIỀN NHAU THÀNH MỘT LƯỢT GHI (sửa 04/08/2026). Bản trước gọi `setValue`
 * **một lượt mỗi ô**: `jpSaveReport` ghi 13 ô header là **13 lượt gọi sang Sheets**,
 * và `jpHoanKhoBaoCao_` gọi hàm này cho từng lớp tồn. Đo được ở `test/toc-do.js`:
 * `jpKtReopen` 21 lượt ghi, `jpSaveReport` 15–17 lượt — gần hết là chỗ này.
 *
 * ⚠️ **CHỈ gom ô LIỀN NHAU, không đọc-rồi-ghi-lại cả dòng.** Đọc cả dòng rồi
 * `setValues` một phát thì nhanh hơn nữa, nhưng nó **ghi lên cả những ô không được
 * liệt kê** — phá đúng cái bất biến hàm này tồn tại để giữ (POSH v3 #1: đối soát
 * không bao giờ được đụng ô doanh thu). Ô nào không có trong `fields` thì không có
 * lệnh ghi nào chạm tới, y như trước.
 *
 * Cột không có trong header thì bỏ qua **im lặng**, giữ đúng hành vi cũ: gọi hàm
 * này với một cột chưa kịp thêm là chuyện bình thường lúc nâng schema.
 */
function jpFields_(tabDef, rowIndex, fields) {
  var sh = jpSheet_(tabDef), h = jpHdr_(tabDef);

  /* Đổi tên cột → chỉ số cột, rồi sắp tăng dần để gom được khối liền nhau */
  var ds = [];
  Object.keys(fields).forEach(function (k) {
    var c = h.indexOf(k);
    if (c >= 0) ds.push({ c: c + 1, v: fields[k] });
  });
  if (!ds.length) return;
  ds.sort(function (a, b) { return a.c - b.c; });

  var i = 0;
  while (i < ds.length) {
    var j = i;
    while (j + 1 < ds.length && ds[j + 1].c === ds[j].c + 1) j++;
    var khoi = ds.slice(i, j + 1).map(function (x) { return x.v; });
    if (khoi.length === 1) sh.getRange(rowIndex, ds[i].c).setValue(khoi[0]);
    else sh.getRange(rowIndex, ds[i].c, 1, khoi.length).setValues([khoi]);
    i = j + 1;
  }

  jpClearCache_(tabDef.name);
}

/**
 * Ghi MỘT cột cho NHIỀU dòng — gom dòng liền nhau thành một lượt gọi.
 *
 * `capNhat` = { <số dòng>: <giá trị> }. Dùng cho ba chỗ trước đây ghi
 * `qtyRemaining` **một lượt mỗi lớp tồn** trong vòng lặp (`jpGhiLopDaTru_` ·
 * `jpHoanKhoBaoCao_` · kiểm kê): duyệt một báo cáo 50 mã ăn vào 2 lớp mỗi mã là
 * **100 lượt gọi sang Sheets**, khoảng 1–5 giây chỉ để trừ tồn.
 *
 * Lớp sinh cùng lúc thì nằm liền dòng (`jpAppendMany_` ghi một khối), nên FIFO trừ
 * các lớp kế nhau gom được thành ít khối.
 *
 * ⚠️ Chỉ ghi ĐÚNG một cột. Giữ nguyên tinh thần `jpFields_`: ô nào không được nêu
 * thì không có lệnh ghi nào chạm tới.
 */
function jpGhiCot_(tabDef, colName, capNhat) {
  var keys = Object.keys(capNhat || {});
  if (!keys.length) return 0;
  var sh = jpSheet_(tabDef), h = jpHdr_(tabDef);
  var c = h.indexOf(colName);
  if (c < 0) return 0;

  var rows = keys.map(Number).sort(function (a, b) { return a - b; });
  var i = 0, luot = 0;
  while (i < rows.length) {
    var j = i;
    while (j + 1 < rows.length && rows[j + 1] === rows[j] + 1) j++;
    var khoi = rows.slice(i, j + 1).map(function (r) { return [capNhat[r]]; });
    if (khoi.length === 1) sh.getRange(rows[i], c + 1).setValue(khoi[0][0]);
    else sh.getRange(rows[i], c + 1, khoi.length, 1).setValues(khoi);
    luot++;
    i = j + 1;
  }
  jpClearCache_(tabDef.name);
  return luot;
}

function jpDelete_(tabDef, rowIndex) {
  jpSheet_(tabDef).deleteRow(rowIndex);
  jpClearCache_(tabDef.name);
}

/** Xoá mọi dòng có key=val (dùng khi nhân viên xoá khu vực/dòng). */
function jpDeleteWhere_(tabDef, key, val) {
  var sh = jpSheet_(tabDef), h = jpHdr_(tabDef), c = h.indexOf(key);
  if (c < 0) return 0;
  var v = jpVals_(tabDef), s = String(val), rows = [];
  for (var i = 1; i < v.length; i++) {
    if (String(v[i][c]) === s) rows.push(i + 1);
  }
  if (!rows.length) return 0;

  /* Gộp dòng liền nhau thành khối rồi xoá từ dưới lên: mỗi khối 1 lượt gọi API
     thay vì deleteRow từng dòng. Lưu nháp báo cáo 50 dòng trước đây là 50 lượt,
     sát giới hạn 6 phút của Apps Script khi báo cáo to. */
  var blocks = [];
  rows.forEach(function (r) {
    var last = blocks[blocks.length - 1];
    if (last && r === last.start + last.count) last.count++;
    else blocks.push({ start: r, count: 1 });
  });
  for (var b = blocks.length - 1; b >= 0; b--) {
    sh.deleteRows(blocks[b].start, blocks[b].count);
  }

  jpClearCache_(tabDef.name);
  return rows.length;
}

/*──────────────────── ④ ID & KHOÁ ────────────────────*/

/** ID dạng RP20260731-0007 — có khoá script để không trùng. */
function jpNextId_(prefix) {
  var lock = LockService.getScriptLock();
  try { lock.waitLock(20000); } catch (e) {}
  try {
    var p = PropertiesService.getScriptProperties();
    var key = 'SEQ_' + prefix;
    var n = parseInt(p.getProperty(key) || '0', 10) + 1;
    p.setProperty(key, String(n));
    return prefix + jpToday_('yyyyMMdd') + '-' + ('0000' + n).slice(-4);
  } finally {
    try { lock.releaseLock(); } catch (e) {}
  }
}

/** Chạy fn trong khoá script — mọi thao tác ghi báo cáo đều phải qua đây. */
function jpLock_(fn) {
  var lock = LockService.getScriptLock();
  lock.waitLock(30000);
  try { return fn(); } finally {
    try { lock.releaseLock(); } catch (e) {}
  }
}

/**
 * KHOÁ CHO VIỆC CHẠY NỀN — thử lấy, **không được thì BỎ LƯỢT**, không xếp hàng.
 *
 * ⚠️⚠️ Việc nền TUYỆT ĐỐI không được dùng `jpLock_`. `jpLock_` chờ **30 giây**, và trong
 * 30 giây đó nó **giữ chỗ trước mặt nhân viên đang bấm Nộp**: người bấm Nộp cũng chờ
 * cùng cái khoá ấy, và họ nhận đúng câu *"Thời gian chờ khóa: một quá trình khác đã giữ
 * khóa quá lâu"*. Gặp thật 27/08/2026 — 38 lần va nhau trong một ngày, và nhân viên
 * VINWONDER không nộp được báo cáo.
 *
 * Việc nền chạy lại sau vài phút là xong; nhân viên đứng ở điểm bán thì không. ⇒ **Ai
 * nhường ai là rõ ràng**: nền bỏ lượt, người bấm được đi trước.
 *
 * Trả `{ lay: false }` khi không lấy được — người gọi PHẢI xét cờ đó. Cố ý **không** trả
 * thẳng kết quả của `fn`: trả thẳng thì "không lấy được khoá" lẫn với "chạy xong ra
 * `null`", mà hai thứ đó đòi hai cách xử lý khác nhau.
 */
function jpLockThu_(fn, msCho) {
  var lock = LockService.getScriptLock();
  if (!lock.tryLock(msCho == null ? 3000 : msCho)) return { lay: false };
  try { return { lay: true, kq: fn() }; } finally {
    try { lock.releaseLock(); } catch (e) {}
  }
}

/*──────────────────── ⑤ AUDIT ────────────────────*/

function jpAudit_(user, action, reportId, target, detail) {
  try {
    jpAppend_(JP_TABS.AUDIT, {
      at: new Date(),
      who: (user && (user.hoTen || user.username)) || 'system',
      role: (user && user.role) || '',
      action: action,
      reportId: reportId || '',
      target: target || '',
      detail: (typeof detail === 'string') ? detail : JSON.stringify(detail || '')
    });
  } catch (e) { /* audit không được phép làm hỏng nghiệp vụ */ }
}

/*──────────────────── ⑥ TIỆN ÍCH ────────────────────*/

function jpNum_(v) {
  if (v === '' || v === null || v === undefined) return 0;
  var n = Number(String(v).replace(/[^\d.\-]/g, ''));
  return isNaN(n) ? 0 : n;
}

function jpStr_(v) { return (v === null || v === undefined) ? '' : String(v).trim(); }

/**
 * Số ảnh yêu cầu, đọc từ cấu hình ô máy / cụm. Ô TRỐNG là "chưa đặt" ⇒ lấy
 * `macDinh` (không truyền thì 1); số 0 GÕ VÀO là cố ý ⇒ giữ 0, nghĩa là chỗ đó
 * không cần chụp.
 *
 * Trước đây ba chỗ hiểu ba kiểu về cùng một ô: lúc lưu thì trống = 1, lúc kiểm
 * ảnh thì trống = 0, còn `jpPubMachine_` viết `|| 1` nên số 0 gõ vào thành 1 —
 * kế toán đặt 0 rồi mở lại thấy 1, tưởng không lưu được. Nay chỉ một hàm.
 */
function jpSoAnh_(v, macDinh) {
  return jpBlank_(v) ? (macDinh === undefined ? 1 : macDinh) : jpNum_(v);
}

/**
 * Phân biệt "chưa nhập" với "nhập số 0" — `jpNum_` biến cả hai thành 0, mà hai
 * thứ đó khác nhau hoàn toàn khi tính chỉ số đồng hồ.
 */
function jpBlank_(v) {
  return v === '' || v === null || v === undefined;
}

/**
 * Số, nhưng GIỮ NGUYÊN ô trống. Dùng cho chỉ số đồng hồ và hàng tồn thực tế —
 * những chỗ mà "chưa nhập" phải phân biệt được với "nhập số 0".
 *
 * Bọc `jpNum_` quanh chỉ số đồng hồ ở đường lưu là cách làm mất phân biệt đó:
 * ô trống thành 0, rồi hàm tính không còn biết là nhân viên chưa nhập.
 */
function jpNumOrBlank_(v) {
  return jpBlank_(v) ? '' : jpNum_(v);
}

function jpToday_(fmt) {
  return Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', fmt || 'yyyy-MM-dd');
}

/** Chuẩn hoá về 'yyyy-MM-dd'. Nhận Date, 'yyyy-MM-dd', 'dd/MM/yyyy'. */
/**
 * Có phải Date không — hỏi bằng `Object.prototype.toString`, KHÔNG dùng
 * `instanceof`. `instanceof` so theo prototype của **realm hiện tại**, nên một
 * Date sinh ra ở realm khác trả về FALSE dù nó là Date thật. Apps Script chỉ có
 * một realm nên `instanceof` chạy đúng, nhưng bộ test nạp code bằng `vm` (realm
 * riêng) thì sai — tức là **test không tái hiện được đúng thứ đang chạy thật**,
 * đúng kiểu lỗ hổng vừa để lọt sự cố 07/08/2026.
 */
function jpLaDate_(v) {
  return Object.prototype.toString.call(v) === '[object Date]';
}

function jpDate_(v) {
  if (!v) return '';
  if (jpLaDate_(v)) {
    return Utilities.formatDate(v, 'Asia/Ho_Chi_Minh', 'yyyy-MM-dd');
  }
  var s = String(v).trim();
  var m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
  if (m) return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
  m = s.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
  if (m) return m[1] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[3]).slice(-2);
  return s;
}

/**
 * Ngày + GIỜ thành CHUỖI. Dùng cho mọi cột thời gian đi ra client.
 *
 * ⚠️ Vì sao cần, và vì sao KHÔNG được trả thẳng giá trị từ sheet:
 * `jpKtApprove` ghi `new Date()` vào `apprRevAt`/`apprStockAt`, nên đọc lại từ
 * sheet là một **đối tượng Date**, không phải chuỗi. `jpChuKy_` trước đây trả
 * thẳng nó về client — chỗ DUY NHẤT trong cả repo không đi qua bộ lọc.
 * Hậu quả (Andy gặp 07/08/2026): `google.script.run` đóng gói payload không
 * được ⇒ `withSuccessHandler` nhận **null** ⇒ client chết ở `d.head` với câu
 * `Cannot read properties of null`. **Máy chủ báo "Đã hoàn thành"**, nhật ký
 * thực thi sạch trơn — nên nhìn log không thấy gì, rất khó lần ra.
 * Chỉ báo cáo ĐÃ KÝ mới có `apprRevAt`, nên chỉ báo cáo đã duyệt mới hỏng.
 *
 * Giữ đúng khuôn `jpDate_`: chỉ gọi `Utilities.formatDate` khi thật sự là Date,
 * còn lại trả chuỗi — nhờ vậy dữ liệu đời cũ (đã là chuỗi) không bị đụng.
 */
/**
 * GIÁ TRỊ THÔ CỦA SHEET → toàn CHUỖI, trước khi đi ra client.
 *
 * ⚠️⚠️ **`google.script.run` CẤM `Date` trong giá trị trả về.** Gặp một cái là **cả lời
 * gọi hỏng**: `withSuccessHandler` nhận `null`, `withFailureHandler` **không** chạy, và
 * nhật ký thực thi máy chủ **sạch trơn** — nên nhìn log không thấy gì. Client chết ở
 * dòng đọc trường đầu tiên với câu `Cannot read properties of null`.
 *
 * Đây là **lần thứ hai** repo mắc đúng lỗi này: lần đầu 07/08/2026 ở `jpChuKy_`
 * (`apprRevAt` đọc thẳng từ sheet), lần này 15/08/2026 ở bảng *"ba dòng thô"* của màn
 * giao dịch ngân hàng — cột `Ngày GD` do API đẩy về là Date thật.
 *
 * ⇒ **Mọi mảng giá trị thô đọc từ sheet đều phải qua hàm này trước khi trả về client.**
 * Đừng trả `getValues()` thẳng, dù chỉ để "cho kế toán nhìn bằng mắt" — chính chỗ nhìn
 * bằng mắt là chỗ hay lấy nguyên xi nhất.
 */
function jpThoChuoi_(vals) {
  return (vals || []).map(function (r) {
    return (r || []).map(function (o) {
      if (o === null || o === undefined) return '';
      return jpLaDate_(o) ? jpNgayGio_(o) : String(o);
    });
  });
}

function jpNgayGio_(v) {
  if (!v) return '';
  if (jpLaDate_(v)) {
    return Utilities.formatDate(v, 'Asia/Ho_Chi_Minh', 'yyyy-MM-dd HH:mm');
  }
  return String(v).trim();
}

function jpDMY_(v) {
  var d = jpDate_(v);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
  var p = d.split('-');
  return p[2] + '/' + p[1] + '/' + p[0];
}

/** So sánh chuỗi bỏ dấu, thường hoá — dùng khi dò tên cơ sở. */
function jpNorm_(s) {
  return jpStr_(s).toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/đ/g, 'd').replace(/[^a-z0-9]+/g, '');
}

function jpMoney_(n) {
  return jpNum_(n).toLocaleString('vi-VN');
}


