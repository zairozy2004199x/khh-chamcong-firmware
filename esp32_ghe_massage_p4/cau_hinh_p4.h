/* ============================================================================
 *  CẤU HÌNH PHẦN CỨNG — bản port sang bo GUITION JC4880P443C (ESP32-P4 + C6)
 *  ----------------------------------------------------------------------------
 *  MỤC ĐÍCH: gom TẤT CẢ chân ngoại vi + hằng phần cứng vào MỘT chỗ, y như bản
 *  cũ (esp32_ghe_massage) gom ở đầu .ino. Bo mới chân thoải mái → anh cắm chân
 *  nào thì sửa số ở đây, KHÔNG phải lục trong code.
 *
 *  ⚠️ TRƯỚC KHI ĐỔI: những chân DƯỚI ĐÂY ĐÃ BỊ BO CHIẾM, ĐỪNG lấy làm ngoại vi
 *     (sẽ đụng màn / WiFi / thẻ nhớ / âm thanh). Nguồn: field-notes + schematic
 *     JC4880P443C (github.com/ultramcu/guition-jc4880p443c-i-w).
 *
 *       Màn ST7701S (MIPI-DSI):  RESET=GPIO5, ĐÈN NỀN=GPIO23
 *       Cảm ứng GT911 + audio I²C: SDA=GPIO7, SCL=GPIO8, GT911 RESET=GPIO3 (addr 0x5D)
 *       Audio ES8311 (I²S):      GPIO9,10,12,13,48 ; amp enable GPIO11
 *       Thẻ nhớ SDMMC 4-bit:     GPIO39,40,41,42,43,44
 *       WiFi C6 qua SDIO:        GPIO14,15,16,17,18,19 ; C6 reset GPIO54
 *       Console UART0:           GPIO37 (TX), GPIO38 (RX)
 *       Nút BOOT: GPIO35   ·   LED: GPIO26   ·   RS485 sẵn: TX=GPIO26, RX=GPIO27
 *
 *  → Chọn chân ngoại vi TRÁNH nhóm trên. Nếu bo chỉ đưa ra pad hạn chế, cân nhắc
 *    IC mở rộng I²C (PCF8574/MCP23017 trên bus 7/8) cho relay + đọc xung — xem
 *    README_PORT.md.
 * ========================================================================== */
#pragma once

/* ─── BO / MÀN (hằng phần cứng JC4880P443C) ──────────────────────────────── */
#define P4_LCD_W          480
#define P4_LCD_H          800
#define P4_LCD_RESET      5
#define P4_LCD_BL         23
#define P4_TOUCH_SDA      7
#define P4_TOUCH_SCL      8
#define P4_TOUCH_RST      3
#define P4_TOUCH_ADDR     0x5D    // GT911
#define P4_C6_RESET       54      // ESP32-C6 (WiFi6 qua SDIO / ESP-Hosted)

/* ─── NGOẠI VI CỦA MÌNH — SỬA THEO CHÂN ANH CẮM ("thích nào dùng nấy") ─────
 * Mặc định lấy tạm chân, PHẢI đối chiếu pad thật của bo trước khi đấu.
 * Các đường này bê nguyên ý nghĩa từ bản cũ (esp32_ghe_massage). */

/* Cổng tiền — SERIAL 4800 8E1 (Serial1), thay đường xung cũ. Xem cong_tien.h.
 * Bản cũ: RX_ICT=IO35, TX_GHE=IO27. Trên P4 chọn 2 chân trống (RS485 27 dùng được). */
#define P4_TIEN_RX_ICT    27      // đọc TX của bo ICT (81/4X/10)
#define P4_TIEN_TX_GHE    -1      // phát sang chân nhận tiền của ghế (đặt chân trống)

/* 4G A7680C — UART riêng (Serial2) + PWRKEY. Anh giữ 4G. */
#define P4_SIM_TX_PIN     -1      // ESP TX -> SIM RX
#define P4_SIM_RX_PIN     -1      // ESP RX <- SIM TX
#define P4_SIM_PWRKEY     -1
#define P4_USE_PWRKEY     false
#define P4_SIM_APN        "v-internet"

/* Relay chạy ghế + rơ-le bypass fail-safe đường tiền (giữ tiền mặt khi mất điện) */
#define P4_USE_RELAY          false   // như bản cũ: mặc định tắt (relay đi qua đường tiền)
#define P4_RELAY_PIN          -1
#define P4_RELAY_ACTIVE_HIGH  true
#define P4_BYPASS_PIN         -1
#define P4_BYPASS_ACTIVE_HIGH false

/* Dò xung "ghế đang chạy" — bản cũ GPIO26 qua chia áp (chạy=LOW / tắt=HIGH). */
#define P4_DO_GHECHAY         1
#define P4_GATE_BY_PIN        1
#define P4_GHECHAY_PIN        -1      // chọn 1 chân input trống (26 đã là LED/RS485 — tránh)
#define P4_GHECHAY_DUTY_NGUONG 50
#define P4_GHECHAY_CHET_MS    10000

/* ─── GHI CHÚ ────────────────────────────────────────────────────────────
 * -1 = CHƯA gán, phải điền chân thật trước khi build & đấu dây.
 * Sau khi chốt chân, cong_tien.h sẽ đọc P4_TIEN_RX_ICT / P4_TIEN_TX_GHE thay cho
 * hằng cũ (xem đầu cong_tien.h — sẽ chỉnh để ưu tiên macro này nếu định nghĩa). */
