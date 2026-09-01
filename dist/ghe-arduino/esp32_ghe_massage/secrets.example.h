// ============================================================================
//  BÍ MẬT của firmware GHẾ MASSAGE — MẪU
//  ----------------------------------------------------------------------------
//  CÁCH DÙNG: copy file này thành  secrets.h  (CÙNG thư mục), điền giá trị thật.
//      secrets.h đã nằm trong .gitignore nên KHÔNG bị commit.
//
//  🔴 22/08/2026 — BẢN CŨ CÓ BÍ MẬT GHI THẲNG TRONG MÃ, VÀ CHÚNG ĐÃ LỘ.
//     Bản trước để nguyên trong .ino:
//        · FB_SECRET  — Firebase database secret, QUYỀN ADMIN trên cả project;
//        · FB_HOST    — địa chỉ database;
//        · mật khẩu WiFi cửa hàng.
//     Ai cầm được database secret là ĐỌC/GHI/XOÁ được toàn bộ dữ liệu của project
//     đó — kể cả nhánh của hệ thống khác dùng chung project. PHẢI VÔ HIỆU nó ở
//     Firebase Console → Project settings → Service accounts → Database secrets.
//     Bản này KHÔNG còn dùng Firebase, nên vô hiệu xong là hết chuyện.
//
//  ⚠️ MÃ GHẾ KHÔNG CÒN NẰM Ở ĐÂY. Ghế tự khai MAC, máy chủ nói nó là ghế số mấy
//     (bảng `may`, cột `mac`). Nhờ vậy MỘT bản .bin dùng cho MỌI ghế và cập nhật
//     từ xa mới có nghĩa. Ghế mới cắm điện sẽ hiện trong "chờ gán" trên web.
// ============================================================================
#pragma once

// --- WiFi cửa hàng (chỉ dùng khi USE_4G = false, để thử trên bàn) ---
#define SEC_WIFI_SSID  "TEN_WIFI"
#define SEC_WIFI_PASS  "MAT_KHAU_WIFI"

// ============================================================================
//  ĐƯỜNG DUY NHẤT: WEBSITE
//  ----------------------------------------------------------------------------
//  ⚠️ URL KHÔNG ĐƯỢC CÓ DẤU "/" Ở CUỐI. Ghế không đi theo chuyển hướng; WordPress
//     chuyển hướng để bỏ dấu gạch là ghế gọi lại bằng GET và mất trọn thân POST.
//  ⚠️ SEC_WP_KEY phải KHỚP HỆT `VHG_KHOA_MAY` trong wp-config.php.
//     KHÁC `VHG_KHOA_WEBHOOK` — khoá kia đi trên đường dẫn nên coi như lộ một phần.
// ============================================================================
#define SEC_WP_URL     "https://DIEN_TEN_MIEN_CUA_ANH/ghe-may"
#define SEC_WP_KEY     "DIEN_KHOA_GIONG_VHG_KHOA_MAY"
