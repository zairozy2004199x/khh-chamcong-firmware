/* ============================================================================
 *  CẤU HÌNH — MÁY THU TIỀN / KỸ THUẬT (bo GUITION JC4880P443C, ESP32-P4 + C6)
 *  ----------------------------------------------------------------------------
 *  Thiết bị cầm tay của KỸ THUẬT, MỘT màn 4.3" cảm ứng, BA việc (chọn ở menu):
 *    1) KIỂM TRA CHỈ SỐ MÁY  — xem chỉ số/doanh thu tiền mặt hệ thống ghi nhận
 *       (CHỈ XEM, không ghi). Internet qua 4G A7680C.
 *    2) NẠP FIRMWARE GHẾ      — nối AP "POSH_QR-<mã>" của ghế → POST .bin THÔ lên
 *       /update kèm X-OTA-Key (đúng otaPhucVu() của firmware ghế).
 *    3) NẠP FIRMWARE MÁY CHẤM CÔNG — nối AP "ChamCong-<cơ sở>" → POST multipart
 *       .bin lên /update kèm Basic-Auth (đúng trang /update máy chấm công).
 *
 *  Mạng: WiFi (C6) CHỈ để nối AP máy đích lúc nạp; Internet đi 4G (tách bạch thời
 *  gian, không chạy song song). File .bin để trên thẻ microSD của máy này.
 *
 *  ⚠️ Chân bo ĐÃ CHIẾM (đừng lấy làm ngoại vi): màn DSI reset=5 đèn nền=23;
 *     cảm ứng GT911 I²C SDA=7 SCL=8 reset=3; thẻ SDMMC 39–44; C6 SDIO 14–19,54;
 *     console 37/38; audio 9,10,11,12,13,48.
 * ========================================================================== */
#pragma once

/* ─── MÀN + CẢM ỨNG + C6 (hằng phần cứng JC4880P443C) ─────────────────────── */
#define P4_LCD_RESET    5
#define P4_LCD_BL       23
#define P4_TOUCH_SDA    7
#define P4_TOUCH_SCL    8
#define P4_TOUCH_RST    3
#define P4_C6_RESET     54

/* ─── THẺ NHỚ microSD (SDMMC 1-bit; chứa firmware.bin) ─────────────────────
 * ⚠️ ĐỐI CHIẾU pad bo trước khi dùng — thứ tự CLK/CMD/D0 tuỳ layout. Dùng 1-bit
 *    (chỉ CLK/CMD/D0) cho chắc; muốn nhanh thì bật 4-bit thêm D1..D3. */
#define SD_CLK_PIN      43
#define SD_CMD_PIN      44
#define SD_D0_PIN       39
#define FW_PATH         "/firmware.bin"   // tên file .bin trên thẻ

/* ─── 4G A7680C (dùng cho việc KIỂM TRA CHỈ SỐ) — chân như bên ghế ─────────── */
#define P4_SIM_TX_PIN   32      // ESP TX -> SIM RX
#define P4_SIM_RX_PIN   33      // ESP RX <- SIM TX
#define P4_SIM_PWRKEY   34
#define P4_USE_PWRKEY   1
#define P4_SIM_APN      "v-internet"

/* ─── ĐƯỜNG DẪN & KHOÁ (bí mật → secrets.h, KHÔNG commit; repo công khai) ──── */
#if __has_include("secrets.h")
  #include "secrets.h"
#endif
#ifndef SEC_WEB_BASE
  #define SEC_WEB_BASE   "https://khmatrix.com/ghe/"   // trang /ghe (có / cuối), api=login/chot_xem...
#endif
/* AP + khoá của GHẾ (khớp firmware ghế — vốn công khai) */
#ifndef SEC_GHE_AP_PASS
  #define SEC_GHE_AP_PASS  "12345678"
#endif
#ifndef SEC_GHE_OTA_KEY
  #define SEC_GHE_OTA_KEY  "12345678"     // header X-OTA-Key
#endif
/* MÁY CHẤM CÔNG: mật khẩu AP + tài khoản trang /update (Basic-Auth) — NÊN để trong secrets.h */
#ifndef SEC_CC_AP_PASS
  #define SEC_CC_AP_PASS   "12345678"
#endif
#ifndef SEC_CC_OTA_USER
  #define SEC_CC_OTA_USER  "admin"
#endif
#ifndef SEC_CC_OTA_PASS
  #define SEC_CC_OTA_PASS  "admin"
#endif

#define GHE_AP_PREFIX   "POSH_QR-"
#define CC_AP_PREFIX    "ChamCong-"
