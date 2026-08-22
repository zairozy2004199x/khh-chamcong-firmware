// ============================================================================
//  BÍ MẬT của firmware chấm công — MẪU
//  ----------------------------------------------------------------------------
//  CÁCH DÙNG: copy file này thành  secrets.h  (CÙNG thư mục), điền giá trị thật.
//      secrets.h  đã nằm trong .gitignore (mẫu `secrets.*`) nên KHÔNG bị commit.
//      Không có secrets.h thì build BÁO LỖI ngay — cố ý, để không bao giờ nạp
//      firmware bằng mật khẩu mẫu.
//
//  ⚠️ GIỮ secrets.h CẨN THẬN: repo KHÔNG có bản sao.
//
//  ⚠️ NHỮNG GIÁ TRỊ CŨ ĐÃ TỪNG NẰM TRONG REPO NÊN COI NHƯ ĐÃ LỘ — git giữ lịch
//     sử vĩnh viễn. Tách file này KHÔNG tự làm chúng an toàn: PHẢI ĐỔI (rotate)
//     khoá máy + mật khẩu Hikvision, rồi mới coi là xong.
//
//  🔴 22/08/2026 — BỎ HẲN APPS SCRIPT VÀ FIREBASE.
//     Máy nay chỉ nói chuyện với MỘT nơi: website. Nên hai khoá nặng nhất của
//     bản cũ — Firebase database secret (quyền admin, ai có nó là ĐẨY ĐƯỢC
//     FIRMWARE TUỲ Ý vào mọi máy) và token web app — KHÔNG còn trong firmware.
//     Ai còn giữ hai giá trị đó thì nên vô hiệu chúng cho xong.
// ============================================================================
#pragma once

// --- WiFi cửa hàng mặc định (dùng khi flash lần đầu; đổi được ở portal 192.168.4.1) ---
#define SEC_WIFI_SSID     "TEN_WIFI"
#define SEC_WIFI_PASS     "MAT_KHAU_WIFI"

// --- Mật khẩu AP cấu hình của chính máy chấm công (AP "CHAM_CONG") ---
// ⚠️ Phải KHỚP AP_PASS trong esp32_ota_updater/secrets.h, không thì máy nạp không vào được.
#define SEC_AP_PASS       "MAT_KHAU_AP"

// --- Đầu đọc Hikvision (ISAPI digest) ---
#define SEC_HIK_USER      "admin"
#define SEC_HIK_PASS      "MAT_KHAU_HIKVISION"

// --- Trang cập nhật firmware /update của máy chấm công ---
// ⚠️ Phải KHỚP OTA_USER/OTA_PASS trong esp32_ota_updater/secrets.h.
#define SEC_OTA_USER      "admin"
#define SEC_OTA_PASS      "MAT_KHAU_TRANG_UPDATE"

// ============================================================================
//  CÁC GIÁ TRỊ TRÊN CHỈ LÀ DỰ PHÒNG
//  ----------------------------------------------------------------------------
//  Firmware đọc bí mật từ bộ nhớ trong (NVS/Preferences) trước; NVS chưa có thì
//  lấy giá trị ở đây và TỰ CHÉP VÀO NVS. Cập nhật firmware (OTA) KHÔNG ghi đè NVS,
//  nên chạy bản này một lần là máy giữ được bí mật mãi.
//
//  Nhờ vậy bản do GitHub Actions build (secrets.h toàn placeholder
//  "__CHUA_CAU_HINH__") vẫn chạy được trên máy đã di trú, và file .bin đó KHÔNG
//  chứa bí mật nào -> đặt ở chỗ tải công khai được để bấm cập nhật từ xa.
//
//  ⚠️ Chip TRẮNG nạp bản CI thì chưa có gì trong NVS: máy hiện "CHUA CAU HINH"
//     trên màn hình, vào 192.168.4.1 khai. Trang /update bị CHẶN cho tới khi
//     khai xong mật khẩu /update.
//  ⚠️ Nạp lần đầu bằng USB thì nên dùng bản build ở máy anh (có secrets.h thật)
//     để máy tự có cấu hình, khỏi khai tay.
//
//  NVS chỉ thắng khi giá trị trong NVS DÙNG ĐƯỢC (khác giá trị mẫu, và với link
//  thì phải đúng dạng). Rác trong NVS thì file này GHI ĐÈ lên và log rõ
//  "KHONG dung duoc -> thay bang secrets.h".
//  Vẫn đúng: giá trị THẬT anh đã khai ở portal 192.168.4.1 thì file này KHÔNG
//  đè lên — portal là nơi khai cuối cùng, muốn đổi thì đổi ở portal.
// ============================================================================

// ============================================================================
//  ĐƯỜNG DUY NHẤT: WEBSITE
//  ----------------------------------------------------------------------------
//  Máy đẩy mỗi lượt chấm công, nhịp sống, lấy lệnh, báo xong, sổ mặt và ảnh —
//  tất cả vào cùng địa chỉ này, phân biệt bằng trường `viec` trong thân JSON.
//
//  ⚠️ URL KHÔNG ĐƯỢC CÓ DẤU "/" Ở CUỐI. Firmware không đi theo chuyển hướng;
//     WordPress chuyển hướng để thêm dấu gạch là máy gọi lại bằng GET và MẤT
//     trọn lượt chấm công — mà log vẫn có thể trông như thành công.
//  ⚠️ ĐỪNG dán link /exec hay link Firebase cũ vào đây. Đã có static_assert
//     chặn lúc biên dịch, và `wpUrlHopLe()` chặn lúc chạy.
//  ⚠️ SEC_WP_KEY phải KHỚP HỆT `VHCC_KHOA_MAY` trong wp-config.php của website.
//     Đây nay là bí mật nặng nhất của firmware: ai có nó là ghi được chấm công
//     cho bất kỳ ai, bất kỳ ngày nào, ở mọi cơ sở.
// ============================================================================
#define SEC_WP_URL        "https://DIEN_TEN_MIEN_CUA_ANH/cham-cong-may"
#define SEC_WP_KEY        "DIEN_KHOA_GIONG_VHCC_KHOA_MAY"
