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

/* ─── NGOẠI VI CỦA MÌNH — ĐÃ CHỐT CHÂN TỪ HEADER JP1 (30/08/2026) ──────────
 * Chân trống JP1 (theo ảnh pinout anh gửi): 52 51 50 49 35(nút BOOT) 34 32 28
 *   33 31 30 29  +  header RS485: 26(TX) 27(RX)  +  nguồn 3V3 / 5V / GND.
 * Quy tắc chọn: NÉ 35 (nút BOOT/strapping — chỉ để dự phòng input), gom 4G một
 * cụm (32/33/34), cổng tiền một cụm (49/50), điều khiển một cụm (51/52), dò xung
 * (29). Các đường bê NGUYÊN ý nghĩa từ bản cũ (esp32_ghe_massage). Xem sơ đồ đấu
 * nối cuối file này. */

/* Cổng tiền — SERIAL 4800 8E1 (Serial1). Bo ICT là TTL 3V3 → đấu THẲNG GPIO,
 * KHÔNG qua transceiver RS485 (26/27) trừ khi bo ghế thật sự chạy RS485. */
#define P4_TIEN_RX_ICT    49      // ESP RX  <- TX của bo ICT (81/4X/10)
#define P4_TIEN_TX_GHE    50      // ESP TX  -> chân nhận tiền của ghế

/* 4G A7680C — UART riêng (Serial2) + PWRKEY. Anh giữ 4G.
 * ⚠️ NGUỒN A7680C: cấp 4V/≥2A RIÊNG (đỉnh dòng ~2A lúc phát) — ĐỪNG lấy 3V3 bo.
 *    GND chung với bo. UART mức 3V3 (A7680C IO 3V3 → nối thẳng, không cần dịch mức). */
#define P4_SIM_TX_PIN     32      // ESP TX -> SIM RX
#define P4_SIM_RX_PIN     33      // ESP RX <- SIM TX
#define P4_SIM_PWRKEY     34      // xung LOW ~1s để bật/tắt nguồn module
#define P4_USE_PWRKEY     true
#define P4_SIM_APN        "v-internet"

/* Relay chạy ghế + rơ-le bypass fail-safe đường tiền (giữ tiền mặt khi mất điện).
 * Module relay opto thường KÍCH MỨC THẤP → để ACTIVE_HIGH=false nếu dùng loại đó.
 * Chọn chân boot ở mức an toàn (mặc định input hi-Z lúc reset) → setup() kéo về
 * trạng thái TẮT trước khi enable, tránh ghế tự chạy lúc mới cấp nguồn. */
#define P4_USE_RELAY          false   // bật khi dùng relay rời (mặc định: tiền đi thẳng)
#define P4_RELAY_PIN          51      // đóng nguồn/kích ghế chạy
#define P4_RELAY_ACTIVE_HIGH  false   // đa số module relay opto kích LOW
#define P4_BYPASS_PIN         52      // rơ-le bypass đường tiền (fail-safe khi mất điện)
#define P4_BYPASS_ACTIVE_HIGH false

/* Dò xung "ghế đang chạy" — INPUT qua chia áp (chạy=LOW / tắt=HIGH), như bản cũ. */
#define P4_DO_GHECHAY         1
#define P4_GATE_BY_PIN        1
#define P4_GHECHAY_PIN        29      // INPUT (chia áp về ≤3V3); dự phòng: 30/31/28/35
#define P4_GHECHAY_DUTY_NGUONG 50
#define P4_GHECHAY_CHET_MS    10000

/* ─── SƠ ĐỒ ĐẤU NỐI (tóm tắt — bản đầy đủ ở SO_DO_DAU_NOI.md) ─────────────
 *
 *   JP1 / bo P4                 Ngoại vi
 *   ─────────────────────────   ─────────────────────────────────────────────
 *   GPIO49  ── TIEN_RX_ICT ───  TX  của bo ICT (cổng tiền)     [4800 8E1 TTL]
 *   GPIO50  ── TIEN_TX_GHE ───  RX nhận tiền của GHẾ
 *   GPIO32  ── SIM_TX ────────  RXD của A7680C (4G)
 *   GPIO33  ── SIM_RX ────────  TXD của A7680C (4G)
 *   GPIO34  ── SIM_PWRKEY ────  PWRKEY của A7680C
 *   GPIO51  ── RELAY_PIN ─────  IN của module relay CHẠY GHẾ   [kích LOW]
 *   GPIO52  ── BYPASS_PIN ────  IN của relay BYPASS đường tiền [kích LOW]
 *   GPIO29  ── GHECHAY_PIN ───  điểm dò "ghế chạy" QUA CHIA ÁP (chạy=LOW)
 *   3V3 / 5V / GND ──────────   nguồn logic; A7680C cấp 4V/≥2A RIÊNG, GND chung
 *
 *   TRỐNG còn lại: 28 30 31 (+35 nút BOOT — chỉ dự phòng input). RS485 26/27 để
 *   dành nếu bo ghế chạy RS485 (khi đó dời cổng tiền sang 26/27).
 *
 * ─── GHI CHÚ ────────────────────────────────────────────────────────────
 * -1 = chưa gán. Giờ đã chốt hết → build & đấu dây được. Đối chiếu pad JP1 thật
 * trước khi cắm. cong_tien.h ĐÃ ưu tiên P4_TIEN_RX_ICT / P4_TIEN_TX_GHE. */
