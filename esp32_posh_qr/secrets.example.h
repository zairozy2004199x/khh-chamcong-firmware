// ============================================================================
//  BÍ MẬT của hộp POSH QR — MẪU
//  ----------------------------------------------------------------------------
//  CÁCH DÙNG: copy file này thành  secrets.h  (CÙNG thư mục), điền giá trị thật.
//      secrets.h đã nằm trong .gitignore (mẫu `secrets.*`) nên KHÔNG bị commit.
//
//  ⚠️ ĐỪNG điền giá trị thật vào chính file này — file này ĐI LÊN REPO CÔNG KHAI.
//     Đã hớ một lần ở firmware chấm công đúng kiểu đó (điền link thật vào
//     secrets.example.h), nên chỗ này ghi lại cho khỏi lặp.
//
//  Từ bản đầu tiên: các giá trị dưới đây chỉ là DỰ PHÒNG. Firmware đọc bộ nhớ
//  trong (NVS) trước; NVS trống thì lấy ở đây rồi TỰ CHÉP VÀO NVS. Nạp firmware
//  mới (OTA) KHÔNG ghi đè NVS, nên khai một lần là xong.
// ============================================================================
#pragma once

// --- KHOÁ KÝ MÃ QR — bí mật nặng nhất của hộp này ---------------------------
// Máy chủ bán vé dùng CHÍNH chuỗi này để ký mã QR; hộp dùng nó để kiểm.
// Ai có khoá là tự in được mã mở ghế vô hạn -> đừng dán vào chat, đừng commit.
// Nên đặt chuỗi ngẫu nhiên >= 32 ký tự. Sinh nhanh:  openssl rand -hex 24
#define SEC_QR_KHOA       "KHOA_KY_NGAU_NHIEN_DAT_O_DAY"

// --- Mã ghế: phải KHỚP ô <máy> trong mã QR (mã QR ghi "*" thì mở được mọi ghế) ---
#define SEC_MAY_ID        "MA_GHE_VI_DU_GHE-01"

// --- WiFi (không bắt buộc) --------------------------------------------------
// Hộp chạy được KHÔNG CẦN WiFi — kiểm mã QR hoàn toàn trên chip. Khai WiFi chỉ để
// hộp tự lấy giờ NTP (cho ô "hết hạn" có nghĩa) và để vào portal qua mạng nhà.
#define SEC_WIFI_SSID     "TEN_WIFI"
#define SEC_WIFI_PASS     "MAT_KHAU_WIFI"

// --- AP cấu hình của chính hộp (WiFi "PoshQR-<mã ghế>" @ 192.168.4.1) --------
// Chưa khai thì AP MỞ, không mật khẩu — để còn vào khai được. Khai xong thì có khoá.
#define SEC_AP_PASS       "MAT_KHAU_AP"

// --- Trang nạp firmware /update --------------------------------------------
// Chưa khai mật khẩu thì trang /update bị CHẶN hẳn, không nạp được từ trình duyệt.
#define SEC_OTA_USER      "admin"
#define SEC_OTA_PASS      "MAT_KHAU_TRANG_NAP"
