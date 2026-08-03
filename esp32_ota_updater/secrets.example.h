// ============================================================================
//  BÍ MẬT của ESP32 "THỢ NẠP" — MẪU
//  ----------------------------------------------------------------------------
//  CÁCH DÙNG: copy thành  secrets.h  (cùng thư mục) rồi điền. `secrets.*` đã nằm
//  trong .gitignore. Không có secrets.h thì build BÁO LỖI ngay (cố ý).
//
//  ⚠️ 3 giá trị dưới PHẢI KHỚP secrets.h của firmware máy chấm công
//     (esp32_hik_chamcong_full/secrets.h) — lệch một chữ là thợ nạp không vào
//     được máy đích, và nó chỉ báo "FAIL" chung chung.
// ============================================================================
#pragma once

// Mật khẩu AP của MÁY CHẤM CÔNG (AP tên "ChamCong-<trạm>") — khớp SEC_AP_PASS bên kia.
#define SEC_AP_PASS    "MAT_KHAU_AP"

// Tài khoản trang /update của máy chấm công — khớp SEC_OTA_USER / SEC_OTA_PASS bên kia.
#define SEC_OTA_USER   "admin"
#define SEC_OTA_PASS   "MAT_KHAU_TRANG_UPDATE"

// ============================================================================
//  TỪ BẢN 2026-07-30a: 3 giá trị trên chỉ là DỰ PHÒNG
//  ----------------------------------------------------------------------------
//  Máy trạm đọc từ bộ nhớ trong (NVS) trước; NVS trống thì lấy ở đây và TỰ CHÉP
//  VÀO NVS. Nhờ vậy bản do CI build (toàn placeholder) vẫn chạy được.
//
//  Khai TRÊN PORTAL của máy trạm (nối WiFi "NapFW-xxxx" rồi vào 192.168.4.1):
//    · mật khẩu AP + tài khoản/mật khẩu /update của MÁY CHẤM CÔNG
//    · WiFi có Internet (hotspot điện thoại được) để máy tự tải .bin
//    · link latest.json (hoặc link .bin trực tiếp)
//    · mật khẩu AP của chính máy trạm — để trống thì AP MỞ (chip mới phải vậy,
//      không thì không vào được portal mà khai)
// ============================================================================
