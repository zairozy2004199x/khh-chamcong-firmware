/**
 * CẦU NỐI CHẤM CÔNG — cho trang khmatrix.com/cham-cong/ gọi các hàm của app này.
 * ============================================================================
 *
 * 🔴 KHÁC HẲN app hợp đồng — ĐỌC HẾT KHỐI NÀY TRƯỚC KHI DÁN.
 *
 * Project chấm công **ĐÃ CÓ `doPost`**: đó là cửa mà CẢ CHUỖI MÁY CHẤM CÔNG đang đẩy dữ liệu
 * vào. Firmware ESP32 gọi `http.POST(body)` với JSON `{"employeeNo":…,"time":…,"image":…}`
 * tới đúng link `/exec` này (xem `esp32_hik_chamcong_full.ino`).
 *
 * Apps Script CHỈ NHẬN MỘT `doPost`. Dán thêm một hàm `doPost` thứ hai là cái kia **chết im
 * lặng**: máy quẹt thẻ vẫn kêu bíp, vẫn báo thành công, mà **không còn dòng chấm công nào được
 * ghi** — không ai phát hiện cho tới lúc tính lương. Vì vậy:
 *
 *   ❌ TUYỆT ĐỐI KHÔNG dán file này rồi để nó tự khai `function doPost`.
 *   ✅ File này KHÔNG khai doPost. Nó chỉ có `ccCauNoi(e)` — anh CHÈN 3 DÒNG vào ĐẦU hàm
 *      `doPost` đang có, xem "CÁCH CÀI" bên dưới.
 *
 * PHÂN BIỆT AI GỌI — máy chấm công hay trang web:
 *   Yêu cầu của trang web LUÔN có `fn` (tên hàm cần gọi) và `key` (khoá bí mật).
 *   Máy chấm công KHÔNG BAO GIỜ gửi hai thứ đó.
 *   Nên `ccCauNoi(e)` chỉ nhận việc khi có `fn`; không có `fn` thì trả null và `doPost` cũ chạy
 *   tiếp Y NGUYÊN như trước. Máy chấm công không bị ảnh hưởng một chút nào.
 *
 * ============================================================================
 * CÁCH CÀI (làm một lần, ~4 phút)
 *
 * 1. Mở CHÍNH project Apps Script của app chấm công → File → New → Script file,
 *    đặt tên `CauNoiChamCong`, dán TOÀN BỘ file này vào.
 *
 * 2. Tìm hàm `doPost` đang có trong Code.gs, CHÈN 3 dòng này vào NGAY DÒNG ĐẦU của nó:
 *
 *        function doPost(e) {
 *          var _cn = ccCauNoi(e);          // ⬅️ CHÈN
 *          if (_cn) return _cn;            // ⬅️ CHÈN — có fn thì cầu nối lo, không thì đi tiếp
 *                                          // ⬅️ (dòng trống cho dễ đọc)
 *          … toàn bộ mã cũ của anh, KHÔNG SỬA GÌ …
 *        }
 *
 *    ⚠️ Chèn ở ĐẦU, trước mọi dòng khác. Chèn ở giữa là những dòng phía trên vẫn chạy cho
 *       yêu cầu của trang web — có thể ghi một dòng chấm công rác vào sheet mỗi lần mở trang.
 *
 * 3. Project Settings → Script Properties → thêm:
 *        WEB_KEY = <khoá dài lấy trong Cài đặt của plugin WordPress>
 *
 * 4. Deploy → Manage deployments → bản đang chạy → ✏️ → New version → Deploy.
 *    ⚠️ GIỮ NGUYÊN "Execute as" và "Who has access" đang có. Đổi "Who has access" là **cả
 *       chuỗi máy chấm công mất đường gửi** — máy gọi ẩn danh, phải để Anyone.
 *    URL /exec KHÔNG đổi, nên firmware không phải nạp lại.
 *
 * 5. Dán URL /exec vào Cài đặt của plugin, bấm "Thử cầu nối".
 *
 * 6. ⚠️ KIỂM LẠI MÁY CHẤM CÔNG NGAY sau khi deploy: quẹt thử một lần rồi xem dòng đó có vào
 *    sheet không. Đây là bước KHÔNG ĐƯỢC BỎ — nếu bước 2 chèn sai chỗ thì đây là lúc duy nhất
 *    phát hiện được, trước khi cả ngày dữ liệu chấm công rơi vào hư không.
 * ============================================================================
 */

/** Hàm giao diện được phép gọi qua cầu nối — CHỜ đối chiếu Index.html của app chấm công.
 *
 * ⚠️ Danh sách này đang RỖNG có chủ ý. Điền tên hàm mà giao diện thật gọi (lấy từ
 *    `google.script.run.<tên hàm>(…)` trong Index.html). Cầu nối TỪ CHỐI mọi tên không có
 *    trong đây và nói rõ tên nào bị từ chối, nên thiếu tên thì báo lỗi rõ ràng — KHÔNG chết im.
 *
 * ⚠️ TUYỆT ĐỐI KHÔNG khai vào đây những hàm xoá/ghi đè cả sheet, hàm đồng bộ hàng loạt, hay
 *    hàm nạp lại firmware. Cho gọi qua web là mở đường phá dữ liệu chấm công bằng một request.
 */
var CC_CHO_PHEP = [
];

/** Tên file HTML của giao diện trong project này (doGet đang dùng file nào thì để tên đó). */
var CC_FILE_GIAO_DIEN = 'Index';


/**
 * Cửa của cầu nối. Trả về:
 *   · null            -> KHÔNG phải yêu cầu của trang web (máy chấm công) -> doPost cũ chạy tiếp
 *   · TextOutput JSON -> đã xử lý xong, doPost cũ KHÔNG được chạy nữa
 */
function ccCauNoi(e) {
  var yc = null;
  try {
    var raw = (e && e.postData) ? e.postData.contents : '';
    if (!raw) return null;                       // không có thân request -> không phải cầu nối
    yc = JSON.parse(raw);
  } catch (err) {
    return null;                                 // thân không phải JSON -> để doPost cũ tự xử
  }
  if (!yc || typeof yc !== 'object') return null;

  // KHÔNG có `fn` -> đây là máy chấm công (hoặc thứ khác). Trả null, tuyệt đối không chạm vào.
  var fn = String(yc.fn || '');
  if (!fn) return null;

  var khoa = PropertiesService.getScriptProperties().getProperty('WEB_KEY') || '';
  if (!khoa) {
    return ccTraVe({ ok: false, error: 'Cầu nối chưa đặt WEB_KEY trong Script Properties' });
  }
  // Có `fn` mà sai khoá thì DỪNG ở đây, KHÔNG cho rơi xuống doPost cũ: một yêu cầu có `fn` thì
  // chắc chắn không phải máy chấm công, mà để nó rơi xuống là thành một dòng chấm công rác.
  if (String(yc.key || '') !== khoa) {
    return ccTraVe({ ok: false, error: 'Sai khoá — kiểm lại WEB_KEY và khoá trong Cài đặt của plugin' });
  }

  if (fn === '__ping') {
    return ccTraVe({ ok: true, data: { song: true, soHam: CC_CHO_PHEP.length, giaoDien: CC_FILE_GIAO_DIEN } });
  }

  // Lấy giao diện gốc để WordPress đem ra phục vụ — khỏi phải chép Index.html sang chỗ khác.
  if (fn === '__giaoDien') {
    try {
      return ccTraVe({ ok: true, data: HtmlService.createHtmlOutputFromFile(CC_FILE_GIAO_DIEN).getContent() });
    } catch (err2) {
      return ccTraVe({ ok: false, error: 'Không đọc được file giao diện "' + CC_FILE_GIAO_DIEN + '": ' + err2 });
    }
  }

  if (!CC_CHO_PHEP.length) {
    return ccTraVe({ ok: false, error: 'CC_CHO_PHEP trong CauNoiChamCong.gs còn RỖNG — chưa khai hàm nào '
      + 'được gọi qua web. Gửi Index.html cho bên làm plugin để khai đúng danh sách.' });
  }
  if (CC_CHO_PHEP.indexOf(fn) < 0) {
    return ccTraVe({ ok: false, error: 'Hàm "' + fn + '" chưa có trong CC_CHO_PHEP của CauNoiChamCong.gs' });
  }

  // Lấy hàm qua globalThis, KHÔNG qua `this`: trong runtime V8 `this` bên trong hàm gọi thường
  // có thể là undefined, lúc đó mọi lệnh đều báo "không có hàm" dù hàm nằm ngay đó.
  var G = (typeof globalThis !== 'undefined') ? globalThis : this;
  if (typeof G[fn] !== 'function') {
    return ccTraVe({ ok: false, error: 'Project này không có hàm "' + fn + '" — đã dán '
      + 'CauNoiChamCong.gs vào ĐÚNG project của app chấm công chưa?' });
  }

  var args = Array.isArray(yc.args) ? yc.args : [];
  try {
    return ccTraVe({ ok: true, data: G[fn].apply(null, args) });
  } catch (err3) {
    return ccTraVe({ ok: false, error: String((err3 && err3.message) ? err3.message : err3) });
  }
}

function ccTraVe(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
