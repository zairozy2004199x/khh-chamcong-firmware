/**
 * SỔ ĐẶT VÉ — NHÀ MA SỐ 13
 * Google Apps Script làm sổ chung cho trang bán vé khi tự up lên web host.
 * Không có file này thì trang vẫn chạy, nhưng mỗi máy một sổ riêng.
 *
 * ---- CÀI ----
 * 1. Tạo một Google Sheet mới (tên gì cũng được).
 * 2. Trong Sheet: Tiện ích mở rộng → Apps Script. Xoá hết code mẫu, dán file này vào.
 * 3. Sửa dòng KHOA bên dưới thành một chuỗi bí mật của riêng anh.
 * 4. Triển khai → Tùy chọn triển khai mới → loại "Ứng dụng web":
 *      - Thực thi với tư cách: Tôi
 *      - Ai có quyền truy cập: Bất kỳ ai
 *    Bấm Triển khai, chấp nhận quyền, chép link kết thúc bằng /exec.
 * 5. Mở nha-ma-so-13.html, tìm khối CAUHINH ở đầu phần <script>, điền:
 *      so:   "…/exec"        (link vừa chép)
 *      khoa: "…"             (đúng chuỗi KHOA ở dưới)
 * 6. Up file HTML lên host. Xong — mọi máy dùng chung một sổ.
 *
 * ---- LƯU Ý ----
 * • KHOA chỉ để chặn người lạ gọi bừa vào link /exec. Nó nằm trong file HTML nên
 *   ai xem mã nguồn trang cũng đọc được — coi là chốt cửa, không phải két sắt.
 * • Script này KHÔNG tự chặn đặt quá sức chứa; trang web kiểm trước khi ghi. Hai
 *   người bấm giữ chỗ đúng cùng một giây thì vẫn có thể vượt một hai chỗ.
 * • Mỗi lần sửa code phải Triển khai lại (Quản lý triển khai → sửa → phiên bản mới),
 *   nếu không link /exec vẫn chạy code cũ.
 */

var KHOA = 'doi-chuoi-nay-di';        // ⚠️ đổi thành chuỗi của riêng anh
var TEN_SHEET = 'DonDatVe';

var COT = ['ma', 'dem', 'suat', 'khoa', 'hang', 'sl', 'khach', 'tong',
           'ten', 'dt', 'luc', 'soat', 'soatLuc', 'huy', 'huyLuc'];

/* ---------------- đường vào ---------------- */

function doGet(e) {
  var t = (e && e.parameter) || {};
  if (!hopLe_(t.khoa)) return traLoi_({ ok: false, loi: 'Sai khoá' });
  if (t.viec === 'danhsach') return traLoi_({ ok: true, don: docHet_() });
  return traLoi_({ ok: false, loi: 'Không hiểu việc: ' + (t.viec || '(trống)') });
}

function doPost(e) {
  var t;
  try {
    t = JSON.parse((e && e.postData && e.postData.contents) || '{}');
  } catch (loi) {
    return traLoi_({ ok: false, loi: 'Gói tin hỏng' });
  }
  if (!hopLe_(t.khoa)) return traLoi_({ ok: false, loi: 'Sai khoá' });

  var khoaGhi = LockService.getScriptLock();
  try {
    khoaGhi.waitLock(20000);
    if (t.viec === 'them') return traLoi_(them_(t.don || {}));
    if (t.viec === 'doi')  return traLoi_(doi_(t.don || {}));
    if (t.viec === 'xoa')  return traLoi_(xoa_((t.don || {}).ma));
    return traLoi_({ ok: false, loi: 'Không hiểu việc: ' + (t.viec || '(trống)') });
  } catch (loi) {
    return traLoi_({ ok: false, loi: String(loi) });
  } finally {
    try { khoaGhi.releaseLock(); } catch (bo) {}
  }
}

/* ---------------- việc ---------------- */

function them_(d) {
  d.ma = String(d.ma || '').trim();
  if (!d.ma) return { ok: false, loi: 'Thiếu mã vé' };
  if (timDong_(d.ma) > 0) return { ok: false, loi: 'Mã vé đã có trong sổ' };
  bang_().appendRow(COT.map(function (c) { return d[c] === undefined ? '' : d[c]; }));
  return { ok: true, ma: d.ma };
}

function doi_(d) {
  var ma = String(d.ma || '').trim();
  var dong = timDong_(ma);
  if (dong <= 0) return { ok: false, loi: 'Không có mã vé ' + ma };
  var sh = bang_();
  COT.forEach(function (c, i) {
    if (c === 'ma' || d[c] === undefined) return;
    sh.getRange(dong, i + 1).setValue(d[c]);
  });
  return { ok: true, ma: ma };
}

function xoa_(ma) {
  ma = String(ma || '').trim();
  var dong = timDong_(ma);
  if (dong <= 0) return { ok: false, loi: 'Không có mã vé ' + ma };
  bang_().deleteRow(dong);
  return { ok: true, ma: ma };
}

/* ---------------- bảng tính ---------------- */

function bang_() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(TEN_SHEET);
  if (!sh) {
    sh = ss.insertSheet(TEN_SHEET);
    /* để dạng chữ, không thì Sheets tự đổi mã vé và mốc thời gian thành ngày tháng */
    sh.getRange(1, 1, sh.getMaxRows(), COT.length).setNumberFormat('@');
    sh.appendRow(COT);
    sh.setFrozenRows(1);
  }
  if (sh.getLastRow() === 0) sh.appendRow(COT);
  return sh;
}

function docHet_() {
  var sh = bang_();
  var n = sh.getLastRow() - 1;
  if (n <= 0) return [];
  return sh.getRange(2, 1, n, COT.length).getValues().map(function (h) {
    var d = {};
    COT.forEach(function (c, i) { d[c] = h[i]; });
    d.ma = String(d.ma || '').trim();
    ['sl', 'khach', 'tong'].forEach(function (c) { d[c] = Number(d[c]) || 0; });
    ['soat', 'huy'].forEach(function (c) {
      d[c] = d[c] === true || String(d[c]).toLowerCase() === 'true';
    });
    ['luc', 'soatLuc', 'huyLuc'].forEach(function (c) {
      d[c] = (d[c] instanceof Date) ? d[c].toISOString() : String(d[c] || '');
    });
    return d;
  }).filter(function (d) { return d.ma; });
}

function timDong_(ma) {
  ma = String(ma || '').trim();
  if (!ma) return 0;
  var sh = bang_();
  var n = sh.getLastRow() - 1;
  if (n <= 0) return 0;
  var cot = sh.getRange(2, 1, n, 1).getValues();
  for (var i = 0; i < cot.length; i++) {
    if (String(cot[i][0]).trim() === ma) return i + 2;
  }
  return 0;
}

/* ---------------- vụn ---------------- */

function hopLe_(khoa) {
  return !KHOA || String(khoa || '') === KHOA;
}

function traLoi_(o) {
  return ContentService.createTextOutput(JSON.stringify(o))
    .setMimeType(ContentService.MimeType.JSON);
}
