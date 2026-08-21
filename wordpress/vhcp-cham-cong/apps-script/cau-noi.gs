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

/** Hàm giao diện được phép gọi qua cầu nối.
 *
 * ⚠️ Cầu nối TỪ CHỐI mọi tên không có trong đây và nói rõ tên nào bị từ chối, nên thiếu tên thì
 *    báo lỗi rõ ràng — KHÔNG chết im.
 *
 * ⚠️ TUYỆT ĐỐI KHÔNG khai vào đây hàm xoá/ghi đè cả sheet, hay hàm đồng bộ hàng loạt.
 *
 * ⚠️ 21/08/2026 — KHAI 22 HÀM MÁY + OTA, và phải giải thích vì sao, vì lời ghi chú cũ ở đây viết
 *    "tuyệt đối không khai hàm nạp lại firmware".
 *
 *    Anh Thắng chốt: Firebase GIỮ NGUYÊN cho phần điều khiển máy, nhưng màn quản lý máy thì đưa
 *    lên web. Cách rẻ nhất và ít chỗ hỏng nhất là để WordPress gọi 22 hàm này QUA CẦU NỐI, chứ
 *    KHÔNG cho WordPress nói chuyện trực tiếp với Firebase:
 *      · chỉ MỘT nơi ghi Firebase (Apps Script) -> không có hai người ghi tranh nhau;
 *      · khoá Firebase KHÔNG phải sao thêm một bản sang wp-config;
 *      · sheet `MayChamCong` vẫn là NGUỒN THẬT của "máy nào thuộc cơ sở nào" — mà đó chính là
 *        thứ `doPost` đang dùng để ghi chấm công, nên không được có bản thứ hai đi lệch.
 *
 *    Ba hàm dưới đây NGUY, khai vào có chủ ý, và đã có hai lớp gác SẴN CÓ:
 *      setOtaTarget / clearOtaTarget  — đẩy firmware cho CẢ CHUỖI máy
 *      donKhoiTest                    — dọn khối "test" trong sheet cơ sở
 *    Hai lớp: (1) cầu nối đòi WEB_KEY, khoá này nằm ở máy chủ, không bao giờ xuống trình duyệt;
 *    (2) chính mấy hàm đó đã tự kiểm `u.isAdmin` bên trong Code.gs. Khai vào cầu nối KHÔNG nới
 *    lỏng lớp thứ hai một chút nào.
 *
 *    ⚠️ Nhưng có một chỗ mà CẢ HAI lớp đó không che: LINK .BIN SAI DẠNG. Module 4G A7680C chết ở
 *       khoảng 532 ký tự, mà link release của GitHub trả 302 rồi chuyển hướng dài 943 ký tự. Đẩy
 *       một link như vậy là mọi máy 4G KHÔNG BAO GIỜ tải được, tức mất luôn đường sửa từ xa và
 *       phải đi từng cửa hàng cắm USB. Nên phía WordPress kiểm dạng link TRƯỚC khi gọi
 *       `setOtaTarget` — xem VHCC_May::ota_url_hop_le().
 */
var CC_CHO_PHEP = [
  /* --- Máy chấm công: ĐỌC --- */
  'getDanhSachMay', 'getMachineStatus', 'getMachineRoster', 'chanDoanMay',
  'getQueueMay', 'getHangDoiTaiLai', 'xemKhoiTest',
  'getLuongMayTuDong', 'getGiaMayTuDong',
  /* --- Máy chấm công: GHI trong phạm vi một máy --- */
  'ganMayVaoCuaHang', 'boGanMay', 'luuSimMay', 'requestMachineScan',
  /* Bảo máy ĐỌC LẠI sổ chấm công của đầu đọc trong một khoảng — lệnh của MÁY, không phải dữ liệu
     của web, nên không có gì để viết lại trên MySQL. */
  'requestBackfill',
  'xoaLenhQueue', 'xoaLenhTaiLai', 'dungTaiLai',
  'setGiaMayTuDong',
  /* --- Dọn khối "test" do gói thử đường truyền tạo ra (đụng sheet cơ sở -> Admin) --- */
  'donKhoiTest',
  /* --- Cập nhật firmware. NGUY: đẩy cho cả chuỗi. Xem khối ⚠️ ở trên. --- */
  'getFwMoiNhat', 'getOtaTarget', 'setOtaTarget', 'clearOtaTarget',
  /* --- Nạp hồ sơ nhân sự một chiều: sheet NhanVien -> MySQL. CHỈ ĐỌC. ---
     Thêm 22/08/2026. Trước đó nhân sự phải dán tay từ Sheet sang, mà 21+ người trải nhiều cơ sở
     thì dán tay là chép sai vài ô rồi bảng lương lệch mà không biết lệch ở đâu.
     ⚠️ MỘT CHIỀU. Không có hàm GHI nhân sự nào ở đây: sheet `NhanVien` vẫn là nguồn thật, WordPress
        chỉ sao lại. Mở đường ghi hai chiều là sớm muộn hai bên đè nhau và không ai biết bên nào đúng.
     ⚠️ Hàm này TỰ LỌC theo quyền của PIN gọi nó (`getEmployees` xét `_canStation`, và ẩn lương/ngân
        hàng với cửa hàng trưởng). Nên PIN dùng để gọi quyết định kéo được bao nhiêu người — cầu nối
        gọi bằng PIN admin, tức kéo đủ. */
  'getEmployees',
  /* --- Nạp chấm công CŨ một chiều: sheet CS_<cơ sở> -> MySQL. CHỈ ĐỌC. ---
     Hai hàm này định nghĩa ở CUỐI file này (không nằm trong Code.gs), và chúng gọi lại đúng
     `_bangCongTho` mà bảng lương của app đang dùng — khỏi sinh ra cách đọc sheet thứ hai. */
  'ccDsCoSoXuat', 'ccXuatChamCong',
  /* Sổ phân quyền — PIN đăng nhập thật của mọi người, để khỏi cấp PIN lần thứ hai. */
  'ccXuatPhanQuyen'
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

/* ===========================================================================================
 *  XUẤT DỮ LIỆU CŨ SANG WORDPRESS — MỘT CHIỀU, CHỈ ĐỌC        (22/08/2026)
 * -------------------------------------------------------------------------------------------
 *  Anh Thắng: *"kéo cả dữ liệu chấm công cũ qua luôn nhé"*.
 *
 *  🔴 VÌ SAO HAI HÀM NÀY NẰM Ở ĐÂY, KHÔNG NẰM TRONG Code.gs
 *     `Code.gs` là file của anh Thắng, đang chạy thật cho cả chuỗi máy. Thêm hàm vào đó là thêm
 *     một chỗ có thể hỏng trong file đó. Còn file này thì plugin phát hành, dán lại là xong.
 *
 *  🔴 VÌ SAO GỌI LẠI `_bangCongTho` CHỨ KHÔNG TỰ ĐỌC SHEET
 *     Cách đọc sheet `CS_<cơ sở>` không đơn giản: một cơ sở có thể GỘP NHIỀU SHEET (ca đêm), cột
 *     giờ vào/ra nằm ở vị trí cố định, và có hàng dán ngược. Tự viết vòng đọc thứ hai ở đây là
 *     sớm muộn hai bên đọc ra hai kết quả khác nhau cho cùng một tháng — mà đó là bảng lương.
 *     Nên dùng ĐÚNG hàm mà bảng lương của app đang dùng.
 *
 *  ⚠️ MỘT CHIỀU. Không có hàm nào ở đây ghi vào sheet. Sheet vẫn là nguồn thật.
 *  ⚠️ Chỉ Admin/Quản lý — dữ liệu chấm công cả cơ sở là căn cứ tính lương.
 * =========================================================================================== */

/** Danh sách cơ sở đã quét, để WordPress biết phải kéo những cơ sở nào. */
function ccDsCoSoXuat(pin) {
  var u = _requireAuth(pin);
  if (!u.isAdmin && u.role !== ROLE.QUAN_LY) {
    return { ok: false, error: 'Chỉ Admin / Quản lý xuất được dữ liệu cả chuỗi.' };
  }
  var r = luongDsCoSo(pin);
  if (!r || !r.ok) return { ok: false, error: (r && r.error) ? r.error : 'Không đọc được danh sách cơ sở.' };
  return { ok: true, daQuet: !!r.daQuet, ds: r.ds || [] };
}

/**
 * Chấm công THÔ của MỘT cơ sở trong MỘT tháng: [{ma, ten, ngay:[{date, vao, ra}]}]
 *
 * ⚠️ MỘT CƠ SỞ MỘT THÁNG MỖI LƯỢT — cố ý. Apps Script chỉ có 6 phút mỗi lượt chạy, và một lượt
 *    trả về cả chuỗi cả năm thì vừa quá 6 phút vừa quá cỡ phản hồi. WordPress gọi nhiều lượt,
 *    mỗi lượt một cơ sở một tháng, và tự biết đã kéo tới đâu.
 *
 * @param {string} monthLabel dạng `MM-yyyy`, ví dụ `08-2026`.
 */
function ccXuatChamCong(pin, station, monthLabel) {
  var u = _requireAuth(pin);
  if (!u.isAdmin && u.role !== ROLE.QUAN_LY) {
    return { ok: false, error: 'Chỉ Admin / Quản lý xuất được dữ liệu chấm công.' };
  }
  station = String(station || '').replace(/^CS_/, '').trim();
  if (!station) return { ok: false, error: 'Thiếu cơ sở.' };
  var prefix = _vpThangPrefix(monthLabel);
  if (!prefix) return { ok: false, error: 'Tháng phải dạng MM-yyyy, ví dụ 08-2026.' };

  var tho = _bangCongTho(station, prefix);
  /* Cơ sở không có sheet thì KHÔNG phải lỗi của lượt kéo — nói rõ là "không có sheet" để
     WordPress ghi nhận rồi đi tiếp, chứ đừng dừng cả mẻ kéo vì một cơ sở. */
  if (tho.error) return { ok: true, khongCoSheet: true, station: station, thang: prefix, rows: [] };

  return { ok: true, station: station, thang: prefix, sheets: tho.sheets || [], rows: tho.rows || [] };
}

/**
 * Sổ PHÂN QUYỀN của app gốc — PIN đăng nhập thật của mọi người.
 *
 * 🔴 Vì sao cần: anh Thắng kéo nhân sự về rồi vẫn không đăng nhập được trang web, vì cổng PIN
 *    của plugin đọc một danh sách khác. Mà PIN thật thì mọi người ĐÃ CÓ — chúng nằm ở sheet
 *    `PhanQuyen`, đúng chỗ `loginByPin` của app gốc đọc. Kéo sổ đó về là ai đang đăng nhập
 *    được app gốc thì đăng nhập được trang web bằng CHÍNH PIN đó, không phải cấp lại lần hai.
 *
 * ⚠️ CHỈ ĐỌC, và chỉ Admin/Quản lý. Đây là danh sách PIN của cả chuỗi.
 * ⚠️ Trả về PIN — bắt buộc, vì đầu kia phải so PIN lúc đăng nhập. Chỗ nhận (WordPress) không
 *    bao giờ in nó ra màn hình, và cầu nối thì đã có WEB_KEY chặn.
 */
function ccXuatPhanQuyen(pin) {
  var u = _requireAuth(pin);
  if (!u.isAdmin && u.role !== ROLE.QUAN_LY) {
    return { ok: false, error: 'Chỉ Admin / Quản lý xuất được sổ phân quyền.' };
  }
  var sh = _ensureSheet(SH_ROLE);
  if (!sh || sh.getLastRow() < 2) return { ok: true, rows: [] };
  var v = sh.getRange(2, 1, sh.getLastRow() - 1, PQ_H.length).getValues();
  var out = [];
  for (var i = 0; i < v.length; i++) {
    var p = String(v[i][0] == null ? '' : v[i][0]).trim();
    if (!p) continue;
    out.push({
      pin:      p,
      hoTen:    String(v[i][1] || '').trim(),
      vaiTro:   String(v[i][2] || '').trim().toUpperCase(),
      cuaHang:  String(v[i][3] || '').trim(),
      maCcOnline:   String(v[i][4] || '').trim(),
      coSoCcOnline: String(v[i][5] || '').trim()
    });
  }
  return { ok: true, rows: out };
}
