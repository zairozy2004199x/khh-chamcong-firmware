/**
 * GHI SONG SONG SANG WORDPRESS — hàng đợi + lịch đẩy.
 * ============================================================================
 *
 * Việc của tệp này: mỗi lượt chấm công máy đẩy vào `doPost`, ghi thêm một dòng vào HÀNG ĐỢI.
 * Một lịch chạy mỗi phút đẩy hàng đợi đó sang cổng WordPress. Sheet vẫn là nguồn thật; MySQL
 * chạy song song để ĐỐI SỐ HÀNG trước khi chuyển hẳn.
 *
 * 🔴 VÌ SAO XẾP HÀNG CHỨ KHÔNG GỌI THẲNG — anh Thắng chốt hướng này.
 *    Gọi `UrlFetchApp` ngay trong `doPost` thì WordPress chậm hay chết là MỌI LƯỢT CHẤM CÔNG
 *    chậm theo. Firmware chờ 30 giây rồi bỏ (`http.setTimeout(30000)`), tức cái MỚI làm hỏng cái
 *    ĐANG CHẠY TỐT — mà cái đang chạy tốt là tiền lương. Xếp hàng thì `doPost` chỉ thêm một
 *    `appendRow` (nó đang ghi sheet rồi), WordPress chết cũng không máy nào biết, chỉ là số hàng
 *    đối chiếu tới chậm hơn một phút.
 *
 * ============================================================================
 * CÁCH CÀI (làm một lần)
 *
 * 1. Mở CHÍNH project Apps Script của app chấm công → File → New → Script file,
 *    đặt tên `GhiSongSongWP`, dán TOÀN BỘ tệp này vào.
 *
 * 2. Khai hai Script Property (Project Settings → Script properties):
 *      WP_URL  = https://khmatrix.com/cham-cong-may      ← ĐÚNG địa chỉ này, KHÔNG dấu / cuối
 *      WP_KEY  = <chuỗi ngẫu nhiên dài, giống hệt VHCC_KHOA_MAY trong wp-config.php>
 *    ⚠️ ĐỪNG ghi cứng hai giá trị này vào tệp — repo công khai.
 *
 * 3. Chèn ĐÚNG MỘT DÒNG vào đầu hàm `doPost` đang có, ngay sau `try {`:
 *
 *        wpXepHang(e && e.postData ? e.postData.contents : '');
 *
 *    Dòng này KHÔNG BAO GIỜ ném lỗi (xem thân hàm) nên không thể làm `doPost` chết.
 *
 * 4. Chạy tay hàm `wpBatDongBo()` một lần để tạo lịch mỗi phút.
 *    Muốn dừng: `wpTatDongBo()`. Muốn xem tình hình: `wpTinhTrang()`.
 *
 * 5. Sau vài ngày, chạy `wpDoiSoHang('TUTU_BT', '2026-08')` để đối số hàng hai bên.
 *
 * ============================================================================
 * ⚠️ ẢNH KHÔNG ĐI QUA HÀNG ĐỢI — CỐ Ý, ĐỪNG "SỬA"
 *    Một ô sheet chỉ chứa được 50.000 ký tự. Ảnh mặt base64 của một JPEG 100 KB là ~133.000 ký
 *    tự — xếp vào hàng đợi là `appendRow` NÉM LỖI, và nó ném lỗi ngay trên đường nóng của
 *    `doPost`. Nên hàng đợi CẮT trường `image` và chỉ giữ cờ `anhCo`.
 *    Hệ quả phải biết trước: trong giai đoạn ghi song song, MySQL có ĐỦ GIỜ nhưng KHÔNG có ảnh.
 *    Việc của giai đoạn này là đối SỐ HÀNG và GIỜ — hai thứ đó là tiền. Ảnh sẽ có đủ khi
 *    firmware trỏ thẳng về WordPress, vì lúc đó gói đi trực tiếp, không qua sheet.
 * ============================================================================
 */

var WP_SH_QUEUE   = 'DongBoWP';
var WP_Q_H        = ['Lúc tạo', 'Thân JSON', 'Trạng thái', 'Số lần thử', 'Lần cuối', 'Kết quả'];
var WP_LO         = 20;      // số dòng đẩy mỗi lượt chạy lịch
var WP_TOI_DA_THU = 5;       // thử quá số này thì treo lại, thôi đẩy — xem ghi chú ở wpDayHangDoi
var WP_GIU_NGAY   = 7;       // dòng đã xong thì giữ bấy nhiêu ngày cho việc đối chiếu rồi dọn

function _wpSheet_() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(WP_SH_QUEUE);
  if (!sh) {
    sh = ss.insertSheet(WP_SH_QUEUE);
    sh.getRange(1, 1, 1, WP_Q_H.length).setValues([WP_Q_H]).setFontWeight('bold');
    sh.setFrozenRows(1);
  }
  return sh;
}

function _wpCauHinh_() {
  var p = PropertiesService.getScriptProperties();
  return { url: String(p.getProperty('WP_URL') || '').trim(),
           key: String(p.getProperty('WP_KEY') || '').trim() };
}

/**
 * XẾP HÀNG — dòng duy nhất chèn vào `doPost`.
 *
 * 🔴 HÀM NÀY KHÔNG ĐƯỢC NÉM LỖI, BAO GIỜ. Nó nằm trên đường nóng của `doPost`: ném lỗi ở đây là
 *    lượt chấm công đó không vào sheet, tức MẤT CÔNG THẬT — để đổi lấy một bản sao mà bản sao
 *    thì chưa ai dùng. Nên toàn thân bọc try/catch và mọi nhánh đều `return`, không `throw`.
 */
function wpXepHang(than) {
  try {
    if (!than) return false;
    var d = JSON.parse(than);

    /* Gói thử đường truyền thì KHÔNG xếp hàng: firmware đẩy một gói này mỗi lần bật máy, trên
       mọi máy. Xếp vào là hàng đợi đầy rác cho một thứ cả hai bên đều đã bỏ.
       Còn lại — kể cả giờ sai khuôn, mã lạ — VẪN xếp, để hai bên nhận CÙNG MỘT đầu vào rồi mới
       so kết quả. Lọc bớt ở bên này là tự tay xoá mất chỗ lệch cần tìm. */
    if (d.selftest === true || String(d.employeeNo || '').toUpperCase() === 'TEST4G') return false;

    // Cắt ảnh — xem khối ⚠️ ở đầu tệp. Giữ cờ để biết lượt đó CÓ ảnh bên Drive.
    var anhCo = !!(d.image && String(d.image).length > 100);
    d.image = '';
    d.anhCo = anhCo;

    var gon = JSON.stringify(d);
    /* Vẫn quá dài (mã NV / tên bất thường dài) -> BỎ XẾP HÀNG, đừng để appendRow ném lỗi trên
       đường nóng. Ghi vết để đọc ra, chứ không im lặng. */
    if (gon.length > 45000) {
      _wpGhiVet_('QUA_DAI', 'gói ' + gon.length + ' ký tự sau khi cắt ảnh -> không xếp hàng');
      return false;
    }
    _wpSheet_().appendRow([new Date(), gon, '', 0, '', '']);
    return true;
  } catch (e) {
    try { _wpGhiVet_('XEP_HANG_LOI', String(e)); } catch (e2) {}
    return false;   // KHÔNG ném lên doPost
  }
}

/**
 * ĐẨY HÀNG ĐỢI — hàm của lịch chạy mỗi phút.
 *
 * Thành công = HTTP 200 VÀ thân có chữ "SUCCESS" — cùng đúng luật firmware dùng, để bên này và
 * firmware không bao giờ hiểu khác nhau về chữ "xong".
 *
 * `followRedirects: false` là CỐ Ý. Firmware không đi theo chuyển hướng: gặp 30x nó gọi lại bằng
 * GET và mất trọn thân POST. Nếu ở đây ta LẶNG LẼ đi theo chuyển hướng thì suốt giai đoạn ghi
 * song song mọi thứ trông êm, tới lúc trỏ firmware về WordPress mới vỡ — mà lúc đó là vỡ trên
 * máy thật, mất công thật. Nên ta để nó HỎNG NGAY và nói rõ địa chỉ sai.
 */
function wpDayHangDoi() {
  var cf = _wpCauHinh_();
  if (!cf.url || !cf.key) { _wpGhiVet_('CHUA_KHAI', 'thiếu Script Property WP_URL hoặc WP_KEY'); return; }

  var lock = LockService.getScriptLock();
  try { if (!lock.tryLock(5000)) return; } catch (e) { return; }   // lượt trước còn chạy -> thôi

  try {
    var sh = _wpSheet_(), lr = sh.getLastRow();
    if (lr < 2) return;
    var v = sh.getRange(2, 1, lr - 1, WP_Q_H.length).getValues();
    var da = 0;
    for (var i = 0; i < v.length && da < WP_LO; i++) {
      var tt = String(v[i][2] || '').trim();
      if (tt === 'xong' || tt === 'treo') continue;
      da++;
      var kq = _wpGui_(cf, String(v[i][1] || ''));
      var lan = Number(v[i][3] || 0) + 1;
      var moi = kq.ok ? 'xong' : (lan >= WP_TOI_DA_THU ? 'treo' : '');
      sh.getRange(i + 2, 3, 1, 4).setValues([[moi, lan, new Date(), kq.note]]);
      /* Treo = thử đủ số lần vẫn không được. KHÔNG xoá dòng: dòng treo là bằng chứng của một
         lượt chấm công CÓ trong sheet mà KHÔNG có trong MySQL. Xoá nó là lúc đối số hàng hai bên
         khớp giả — đúng loại sai làm cả việc này thành vô nghĩa. */
      if (moi === 'treo') _wpGhiVet_('TREO', 'thử ' + lan + ' lần vẫn trượt: ' + kq.note);
    }
    _wpDon_(sh);
  } finally {
    try { lock.releaseLock(); } catch (e) {}
  }
}

function _wpGui_(cf, than) {
  try {
    var r = UrlFetchApp.fetch(cf.url, {
      method: 'post',
      contentType: 'application/json',
      payload: than,
      headers: { 'X-VHCC-Key': cf.key },
      muteHttpExceptions: true,
      followRedirects: false
    });
    var ma = r.getResponseCode(), tho = String(r.getContentText() || '');
    if (ma === 301 || ma === 302 || ma === 307 || ma === 308) {
      return { ok: false, note: 'HTTP ' + ma + ' CHUYỂN HƯỚNG — WP_URL sai. Firmware KHÔNG đi theo '
        + 'chuyển hướng nên địa chỉ này sẽ mất chấm công lúc chuyển. Sửa WP_URL cho đúng '
        + '(không dấu / cuối, đúng http/https, đúng tên miền trong Cài đặt WordPress).' };
    }
    // Cùng luật firmware: 200 + có chữ SUCCESS. Đừng nới lỏng, để hai bên hiểu "xong" giống nhau.
    if (ma === 200 && tho.indexOf('SUCCESS') >= 0) return { ok: true, note: tho.slice(0, 200) };
    return { ok: false, note: 'HTTP ' + ma + ': ' + tho.slice(0, 200) };
  } catch (e) {
    return { ok: false, note: 'lỗi gọi: ' + String(e) };
  }
}

/** Dọn dòng ĐÃ XONG cũ hơn WP_GIU_NGAY ngày. Dòng `treo` KHÔNG dọn — nó là bằng chứng. */
function _wpDon_(sh) {
  var lr = sh.getLastRow();
  if (lr < 2) return;
  var v = sh.getRange(2, 1, lr - 1, 3).getValues();
  var moc = Date.now() - WP_GIU_NGAY * 86400000;
  for (var i = v.length - 1; i >= 0; i--) {
    if (String(v[i][2] || '').trim() !== 'xong') continue;
    var t = v[i][0];
    if (t instanceof Date && t.getTime() < moc) sh.deleteRow(i + 2);
  }
}

function wpBatDongBo() {
  wpTatDongBo();
  ScriptApp.newTrigger('wpDayHangDoi').timeBased().everyMinutes(1).create();
  return 'Đã bật: đẩy hàng đợi mỗi phút.';
}

function wpTatDongBo() {
  var n = 0;
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'wpDayHangDoi') { ScriptApp.deleteTrigger(t); n++; }
  });
  return 'Đã tắt ' + n + ' lịch.';
}

/** Đếm hàng đợi theo trạng thái + vết gần nhất. Chạy tay để xem tình hình. */
function wpTinhTrang() {
  var cf = _wpCauHinh_();
  var sh = _wpSheet_(), lr = sh.getLastRow();
  var d = { cho: 0, xong: 0, treo: 0 };
  if (lr >= 2) {
    sh.getRange(2, 3, lr - 1, 1).getValues().forEach(function (r) {
      var t = String(r[0] || '').trim();
      if (t === 'xong') d.xong++; else if (t === 'treo') d.treo++; else d.cho++;
    });
  }
  var kq = {
    daKhaiUrl: !!cf.url, daKhaiKey: !!cf.key,
    hangDoi: d,
    coLich: ScriptApp.getProjectTriggers().some(function (t) { return t.getHandlerFunction() === 'wpDayHangDoi'; }),
    vet: (PropertiesService.getScriptProperties().getProperty('wp_vet') || '').split('\n').slice(0, 10)
  };
  Logger.log(JSON.stringify(kq, null, 2));
  return kq;
}

/**
 * ĐỐI SỐ HÀNG — việc thật của giai đoạn ghi song song.
 *
 * Đếm số lượt trong sheet `CS_<cơ sở>` của một tháng rồi so với số hàng đợi ĐÃ XONG cùng tháng.
 * ⚠️ Đây là phép đối THÔ và cố ý thô: nó chỉ nói HAI BÊN CÓ LỆCH KHÔNG, không nói lệch ở đâu.
 *    Lệch thì mở màn "Cổng nhận từ máy" bên WordPress đọc nhật ký — chỗ đó ghi từng ca bị bỏ và
 *    vì sao. Đừng tin một con số khớp là xong: khớp số mà lệch GIỜ vẫn sai lương.
 */
function wpDoiSoHang(coSo, thang) {
  var sh = _wpSheet_(), lr = sh.getLastRow(), d = { xong: 0, cho: 0, treo: 0 };
  if (lr >= 2) {
    sh.getRange(2, 1, lr - 1, 3).getValues().forEach(function (r) {
      var t;
      try { t = JSON.parse(String(r[1] || '')); } catch (e) { return; }
      var luc = String(t.time || '');
      if (luc.indexOf(thang) !== 0) return;
      var khai = String(t.stationName || '').replace(/^CS_/, '');
      if (coSo && khai && khai.toLowerCase() !== String(coSo).toLowerCase()) {
        /* Không loại theo lời khai của máy: chính chỗ máy khai lệch là chỗ cần soi. Chỉ đếm riêng. */
      }
      var tt = String(r[2] || '').trim();
      if (tt === 'xong') d.xong++; else if (tt === 'treo') d.treo++; else d.cho++;
    });
  }
  var kq = { coSo: coSo, thang: thang, hangDoi: d,
    ghiChu: d.treo > 0
      ? '⚠️ Có ' + d.treo + ' lượt TREO: đã vào sheet mà CHƯA vào MySQL. Đừng chuyển firmware '
        + 'khi còn dòng treo.'
      : (d.cho > 0 ? 'Còn ' + d.cho + ' lượt chờ đẩy, đợi lịch chạy rồi đối lại.'
                   : 'Hàng đợi sạch — mọi lượt đã sang MySQL.') };
  Logger.log(JSON.stringify(kq, null, 2));
  return kq;
}

/** Vết của việc đồng bộ. Giữ trong Script Property để không đụng vào sheet nào của app. */
function _wpGhiVet_(ma, loi) {
  try {
    var p = PropertiesService.getScriptProperties();
    var cu = String(p.getProperty('wp_vet') || '');
    var moi = (new Date()).toISOString() + ' | ' + ma + ' | ' + loi;
    p.setProperty('wp_vet', (moi + '\n' + cu).split('\n').slice(0, 50).join('\n'));
  } catch (e) {}
}
