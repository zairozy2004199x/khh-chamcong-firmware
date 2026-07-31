// ============================================================================
//  BÍ MẬT của firmware chấm công — MẪU
//  ----------------------------------------------------------------------------
//  CÁCH DÙNG: copy file này thành  secrets.h  (CÙNG thư mục), điền giá trị thật.
//      secrets.h  đã nằm trong .gitignore (mẫu `secrets.*`) nên KHÔNG bị commit.
//      Không có secrets.h thì build BÁO LỖI ngay — cố ý, để không bao giờ nạp
//      firmware bằng mật khẩu mẫu.
//
//  ⚠️ GIỮ secrets.h CẨN THẬN: repo KHÔNG có bản sao. Mất file này thì phải lấy
//     lại từng giá trị: Firebase secret ở Console → Project settings → Service
//     accounts → Database secrets; EMP_TOKEN xem Script Property của web app.
//
//  ⚠️ NHỮNG GIÁ TRỊ CŨ ĐÃ TỪNG NẰM TRONG REPO NÊN COI NHƯ ĐÃ LỘ — git giữ lịch
//     sử vĩnh viễn. Tách file này KHÔNG tự làm chúng an toàn: PHẢI ĐỔI
//     (rotate) Firebase secret + EMP_TOKEN + mật khẩu Hikvision, rồi mới coi là xong.
// ============================================================================
#pragma once

// --- WiFi cửa hàng mặc định (dùng khi flash lần đầu; đổi được ở portal 192.168.4.1) ---
#define SEC_WIFI_SSID     "TEN_WIFI"
#define SEC_WIFI_PASS     "MAT_KHAU_WIFI"

// --- Mật khẩu AP cấu hình của chính máy chấm công (AP "ChamCong-<trạm>") ---
// ⚠️ Phải KHỚP AP_PASS trong esp32_ota_updater/secrets.h, không thì máy nạp không vào được.
#define SEC_AP_PASS       "MAT_KHAU_AP"

// --- Đầu đọc Hikvision (ISAPI digest) ---
#define SEC_HIK_USER      "admin"
#define SEC_HIK_PASS      "MAT_KHAU_HIKVISION"

// --- Trang cập nhật firmware /update của máy chấm công ---
// ⚠️ Phải KHỚP OTA_USER/OTA_PASS trong esp32_ota_updater/secrets.h.
#define SEC_OTA_USER      "admin"
#define SEC_OTA_PASS      "MAT_KHAU_TRANG_UPDATE"

// --- Token gọi web app Apps Script (?token=...) ---
// ⚠️ Phải KHỚP Script Property `EMP_TOKEN` của web app ChamCongLive.
#define SEC_EMP_TOKEN     "TOKEN_WEB_APP"

// --- Firebase Realtime Database secret (QUYỀN ADMIN, bỏ qua mọi rule) ---
// ⚠️ Đây là bí mật nặng nhất: ai có nó thì ghi được /ota, tức ĐẨY FIRMWARE TUỲ Ý
//    vào mọi máy chấm công. Đừng dán vào chat, đừng commit.
// ⚠️ Phải KHỚP Script Property `FB_SECRET` của web app.
//    Để trống ("") = gọi Firebase KHÔNG kèm auth (chỉ chạy được nếu rule đang mở).
#define SEC_FB_SECRET     "FIREBASE_DATABASE_SECRET"

// ============================================================================
//  TỪ BẢN 2026-07-30c: các giá trị trên chỉ là DỰ PHÒNG
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
//     trên màn hình, AP mở không mật khẩu, vào 192.168.4.1 khai. Trang /update
//     bị CHẶN cho tới khi khai xong mật khẩu /update.
//  ⚠️ Nạp lần đầu bằng USB thì nên dùng bản build ở máy anh (có secrets.h thật)
//     để máy tự có cấu hình, khỏi khai tay.
// ============================================================================

// --- 2 link (từ bản 2026-07-30c) ---
// Không khai 2 dòng này thì .ino dùng giá trị mặc định ghi trong code (vẫn build được).
// Khai ở đây thì file .bin không còn phụ thuộc giá trị trong code.
// ⚠️ Link web app PHẢI dạng /macros/s/<id>/exec — dạng /a/macros/<tên miền>/s/… là link cho
//    người đã đăng nhập Workspace, thiết bị gọi ẩn danh sẽ bị chặn.
#define SEC_EXEC_URL      "https://script.google.com/macros/s/DIEN_ID/exec"
#define SEC_FB_HOST       "https://DIEN_TEN-default-rtdb.asia-southeast1.firebasedatabase.app"
