/**
 * CẦU NỐI — cho phép trang web khmatrix.com gọi ĐÚNG các hàm của app hợp đồng này.
 * ============================================================================
 *
 * Ý TƯỞNG: KHÔNG dựng lại app. Giao diện và toàn bộ nghiệp vụ (bóc tách PDF bằng AI, dò
 * thư mục Drive, tách smart-chip link, tính tiền thuê theo tháng, học gán gian hàng…)
 * GIỮ NGUYÊN ở đây. Chỉ thêm một cửa để WordPress gọi vào.
 *
 *   Trình duyệt  →  khmatrix.com/hop-dong/  →  (khoá bí mật)  →  /exec của app này  →  Sheet
 *                   ↑ cổng PIN ở đây                            ↑ mọi logic vẫn ở đây
 *
 * Vì sao đi vòng qua WordPress chứ không gọi thẳng /exec từ trình duyệt:
 *   - KHOÁ BÍ MẬT không bao giờ xuống trình duyệt. Gọi thẳng là ai xem mã trang cũng thấy
 *     khoá, rồi ghi được vào sheet hợp đồng.
 *   - Cổng PIN nằm ở WordPress nên dùng chung tài khoản với app Vận hành chi phí.
 *   - Hiện tại `appsscript.json` đang để access = ANYONE_ANONYMOUS, tức là AI CÓ LINK /exec
 *     là dùng được app hợp đồng. Sau khi qua cầu nối này thì nên đổi lại thành hạn chế, chỉ
 *     WordPress biết link — em có ghi cách ở cuối file.
 *
 * CÁCH CÀI (làm một lần, ~3 phút)
 *   1. Mở CHÍNH project Apps Script của app hợp đồng (không phải project mới — cầu nối phải
 *      nằm cùng chỗ mới gọi được các hàm của app).
 *   2. File → New → Script file, đặt tên "CauNoi", dán TOÀN BỘ file này vào.
 *      ⚠️ Nếu project đã có hàm doPost() ở file khác thì BÁO EM, đừng dán — hai doPost là
 *         Apps Script chỉ nhận một cái, cái kia chết im lặng.
 *   3. Project Settings → Script Properties → thêm:
 *        WEB_KEY = <khoá dài lấy trong Cài đặt của plugin WordPress>
 *      (đặt ở Script Properties chứ không viết vào code, để khoá không nằm trong bản sao code)
 *   4. Deploy → Manage deployments → bản deploy đang chạy → ✏️ → Version: New version → Deploy.
 *      Giữ nguyên Execute as / Who has access đang có, URL /exec KHÔNG đổi.
 *   5. Dán URL /exec đó vào Cài đặt của plugin, bấm "Thử cầu nối".
 *
 * SAU NÀY SỬA APP: sửa Code.gs như trước rồi Deploy → New version. Trang web tự dùng bản mới,
 * không phải sửa gì bên WordPress.
 * ============================================================================
 */

/** Hàm giao diện được phép gọi qua cầu nối. Lấy đúng từ Index.html của app này. */
var CN_CHO_PHEP = [
  // dữ liệu & form
  'getData', 'getFormMeta', 'getContractRow', 'addContract', 'updateContract', 'apiChuyenTab',
  // thiếu thông tin
  'apiCauHinhCanhBaoThieu', 'apiLuuTruongKiem', 'apiBoiVangThieu', 'apiSoatCot',
  'apiXemCotUrl', 'apiDatTenCotUrl', 'apiDsChuaBocTach',
  // bóc tách PDF
  'getExtractConfig', 'getStagingData', 'extractFromDriveLink', 'extractOneForAdd',
  'apiDeXuatTuPdf', 'apiGhiBoSung', 'apiGhiDeLech', 'apiBocTachNhieuDong',
  'apiDoThuMucPdf', 'apiLayThuMucPdf', 'apiLuuThuMucPdf', 'apiThuMucCoDinh',
  'apiThuMucDaBoc', 'apiLuuThuMucDaBoc', 'apiHoanTacDonFile',
  'apiTachLinkStaging', 'apiLocTrungHop',
  // tiền thuê theo tháng
  'getTienThueThang', 'getCoSoMap', 'saveCoSoMap', 'docTienThueTuScan', 'ghiTienThueTuScan',
  // gán gian hàng
  'goiYGianHang', 'apGoiYGianHang', 'setVhcpSource',
  // cấu hình
  'apiNguonDuLieu', 'apiLuuCheDoDoc', 'apiTachLinkAnTatCa', 'apiLapBangTraLink',
  'apiCauHinhAI', 'apiDsModelAI', 'apiCauHinhToken', 'apiNhanDangHoc'
];

/** Tên file HTML của giao diện trong project này (doGet đang dùng file nào thì để tên đó). */
var CN_FILE_GIAO_DIEN = 'Index';


function doPost(e) {
  var yc;
  try {
    yc = JSON.parse((e && e.postData) ? e.postData.contents : '{}');
  } catch (err) {
    return cnTraVe({ ok: false, error: 'Thân request không phải JSON' });
  }

  var khoa = PropertiesService.getScriptProperties().getProperty('WEB_KEY') || '';
  if (!khoa) {
    return cnTraVe({ ok: false, error: 'Cầu nối chưa đặt WEB_KEY trong Script Properties' });
  }
  if (String(yc.key || '') !== khoa) {
    return cnTraVe({ ok: false, error: 'Sai khoá' });
  }

  var fn = String(yc.fn || '');

  // Lấy chính giao diện gốc của app để WordPress đem ra phục vụ — nhờ vậy KHÔNG phải chép
  // Index.html sang chỗ khác, và sửa giao diện ở đây là trang web tự có bản mới.
  if (fn === '__giaoDien') {
    try {
      return cnTraVe({ ok: true, data: HtmlService.createHtmlOutputFromFile(CN_FILE_GIAO_DIEN).getContent() });
    } catch (err) {
      return cnTraVe({ ok: false, error: 'Không đọc được file giao diện "' + CN_FILE_GIAO_DIEN + '": ' + err });
    }
  }

  if (fn === '__ping') {
    return cnTraVe({ ok: true, data: { song: true, soHam: CN_CHO_PHEP.length, giaoDien: CN_FILE_GIAO_DIEN } });
  }

  if (CN_CHO_PHEP.indexOf(fn) < 0) {
    // Nói rõ tên hàm chứ không im lặng: thiếu tên trong danh sách là một tính năng chết,
    // mà chết im thì rất lâu sau mới ai phát hiện.
    return cnTraVe({ ok: false, error: 'Hàm "' + fn + '" chưa có trong CN_CHO_PHEP của file CauNoi.gs' });
  }
  // Lấy hàm qua globalThis, KHÔNG qua `this`: trong runtime V8, `this` bên trong một hàm gọi
  // thường có thể là undefined, lúc đó mọi lệnh đều báo "không có hàm" dù hàm nằm ngay đó.
  var G = (typeof globalThis !== 'undefined') ? globalThis : this;
  if (typeof G[fn] !== 'function') {
    return cnTraVe({ ok: false, error: 'Project này không có hàm "' + fn + '" — dán CauNoi.gs vào ĐÚNG '
      + 'project của app hợp đồng chưa? (cầu nối phải nằm cùng project mới gọi được hàm của app)' });
  }

  var args = Array.isArray(yc.args) ? yc.args : [];
  try {
    return cnTraVe({ ok: true, data: G[fn].apply(null, args) });
  } catch (err) {
    // Trả nguyên văn lỗi để giao diện hiện đúng như khi chạy trong Apps Script
    return cnTraVe({ ok: false, error: String((err && err.message) ? err.message : err) });
  }
}

function cnTraVe(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

/**
 * ============================================================================
 * 🔴 LỖ BẢO MẬT ĐANG CÓ SẴN TRONG Code.gs — VÁ SAU KHI TRANG WEB CHẠY ĐƯỢC
 * ============================================================================
 *
 * `doGet(e)` của app hiện có nhánh này:
 *
 *     if (e && e.parameter && e.parameter.data === '1') {
 *       return ContentService.createTextOutput(JSON.stringify(getData(fresh)))…
 *     }
 *
 * Cộng với `appsscript.json` đang để `"access": "ANYONE_ANONYMOUS"`, nghĩa là:
 * BẤT KỲ AI có link /exec, chỉ cần thêm `?data=1`, là tải về TOÀN BỘ hợp đồng —
 * tên khách · MST · địa chỉ · SỐ TÀI KHOẢN NGÂN HÀNG · link file hợp đồng —
 * KHÔNG cần đăng nhập, KHÔNG cần khoá. Chính ghi chú trong Code.gs đã nêu điều này.
 *
 * Trang /hop-dong/ trên WordPress KHÔNG dùng nhánh đó (nó gọi doPost có khoá), nên
 * vá được mà không ảnh hưởng gì. VÁ SAU khi trang web chạy ổn — vá trước là tự khoá
 * mình ra ngoài lúc còn đang thử.
 *
 * CÁCH VÁ: trong Code.gs, sửa nhánh đó thành đòi khoá:
 *
 *     if (e && e.parameter && e.parameter.data === '1') {
 *       var khoaYc = String((e.parameter.key || ''));
 *       var khoaThat = PropertiesService.getScriptProperties().getProperty('WEB_KEY') || '';
 *       if (!khoaThat || khoaYc !== khoaThat) {
 *         return ContentService.createTextOutput(JSON.stringify({ ok: false, error: 'Thiếu khoá' }))
 *           .setMimeType(ContentService.MimeType.JSON);
 *       }
 *       var fresh = e.parameter.fresh === '1';
 *       return ContentService.createTextOutput(JSON.stringify(getData(fresh)))
 *         .setMimeType(ContentService.MimeType.JSON);
 *     }
 *
 * Rồi Deploy → New version. Ai đang dùng `?data=1` ở đâu khác thì thêm `&key=<WEB_KEY>`.
 * ============================================================================
 */

/**
 * SIẾT LẠI QUYỀN TRUY CẬP (nên làm sau khi trang web chạy được)
 *
 * Hiện `appsscript.json` để "access": "ANYONE_ANONYMOUS" — ai có link /exec là mở được app
 * hợp đồng, thấy hết giá và điều khoản. Link dài nên khó đoán, nhưng "khó đoán" không phải
 * là khoá: link lọt vào lịch sử trình duyệt, tin nhắn, hay log của bên thứ ba là xong.
 *
 * Sau khi /hop-dong/ trên khmatrix.com chạy được, KHÔNG cần ai mở /exec bằng trình duyệt nữa.
 * Lúc đó nên:
 *   - Vẫn giữ "Anyone" (WordPress gọi vào bằng máy chủ, không có tài khoản Google), NHƯNG
 *     doGet() nên chặn: xoá đường dựng giao diện ở doGet, chỉ để lại doPost có khoá.
 *   - Em sẽ đưa đoạn doGet chặn đó khi anh xác nhận trang web đã chạy ổn — làm sớm là tự
 *     khoá mình ra ngoài lúc còn đang thử.
 */
