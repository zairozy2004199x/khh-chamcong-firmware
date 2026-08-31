/* secrets.h — CHÉP file này thành "secrets.h" (cùng thư mục) rồi điền.
 * KHÔNG commit secrets.h (repo công khai). Chỉ khai cái nào muốn ĐỔI so với mặc định. */
#pragma once

#define SEC_WEB_BASE     "https://khmatrix.com/ghe/"   // trang /ghe của web (có / cuối)

/* Máy chấm công: mật khẩu AP + tài khoản trang /update (Basic-Auth). NÊN đổi cho đúng máy thật. */
#define SEC_CC_AP_PASS   "..."     // mật khẩu AP "ChamCong-<cơ sở>"
#define SEC_CC_OTA_USER  "admin"
#define SEC_CC_OTA_PASS  "..."     // mật khẩu trang /update máy chấm công

/* Ghế: mặc định 12345678 (đã công khai trong firmware ghế). Đổi ở đây nếu ghế dùng khoá khác.
#define SEC_GHE_AP_PASS  "12345678"
#define SEC_GHE_OTA_KEY  "12345678"
*/
