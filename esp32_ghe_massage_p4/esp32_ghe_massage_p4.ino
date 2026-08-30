/* ============================================================================
 *  POSH QR — bản PORT sang GUITION JC-ESP32-P4-M3-Dev (JC4880P443)
 *  ESP32-P4 + ESP32-C6 · màn ST7701S 480×800 MIPI-DSI · cảm ứng GT911 · WiFi qua C6
 *  ----------------------------------------------------------------------------
 *  GIAI ĐOẠN 1b — BRING-UP PHẦN CỨNG (chưa có logic ghế).
 *  Mục tiêu, nạp lên phải thấy:
 *     · Màn SÁNG: nền xanh navy + 3 vạch màu (đỏ/lục/lam) + ô trắng ở giữa.
 *     · CHẠM vào màn → vẽ ô vàng tại chỗ chạm + in toạ độ ra Serial.
 *     · Serial in thông tin chip, WiFi (qua C6) nối được + IP.
 *  Thấy đủ 3 cái trên = nền màn/cảm ứng/WiFi OK → GĐ2 bê logic ghế + cổng tiền vào.
 *
 *  MÔI TRƯỜNG: Arduino-ESP32 ≥ 3.3.6, board "ESP32-P4 Dev Module", PSRAM bật.
 *     WiFi qua C6 cần firmware ESP-Hosted đã nạp cho C6 (theo tài liệu bo). Nếu
 *     WiFi không nối được thì lỗi ở C6/ESP-Hosted, KHÔNG phải code màn.
 *
 *  ⚠️ ĐÂY LÀ BẢN CHẠY THỬ ĐẦU (không build/test được ở máy soạn code). Tên hàm/
 *     struct của esp_lcd có thể lệch nhẹ theo phiên bản core — nếu báo lỗi biên
 *     dịch ở khối DSI, đối chiếu ví dụ esp_lcd MIPI-DSI của core đang cài. Init
 *     panel + timing đã đúng bo (panel_jc4880p443.h).
 * ========================================================================== */
#include <Arduino.h>
#include <Wire.h>
#include <WiFi.h>
#include "esp_lcd_mipi_dsi.h"
#include "esp_lcd_panel_io.h"
#include "esp_lcd_panel_ops.h"
#include "driver/gpio.h"
#include "esp_ldo_regulator.h"   // 🔴 D-PHY của MIPI-DSI phải được cấp nguồn qua LDO nội (kênh 3)
#include "esp_cache.h"           // 🔴 đẩy cache xuống PSRAM sau khi vẽ, kẻo DMA quét ra ảnh RÁC

#include "cau_hinh_p4.h"
#include "panel_jc4880p443.h"

#if __has_include("secrets.h")
  #include "secrets.h"
#endif
#ifndef SEC_WIFI_SSID
  #define SEC_WIFI_SSID "__DIEN_SSID__"
  #define SEC_WIFI_PASS "__DIEN_PASS__"
#endif

/* ─── MÀN DSI ────────────────────────────────────────────────────────────── */
static esp_lcd_panel_io_handle_t  g_io    = nullptr;
static esp_lcd_panel_handle_t     g_panel = nullptr;
static uint16_t*                  g_fb    = nullptr;   // framebuffer RGB565 (DPI tự quét)

static inline uint16_t RGB565(uint8_t r, uint8_t g, uint8_t b) {
  return (uint16_t)(((r & 0xF8) << 8) | ((g & 0xFC) << 3) | (b >> 3));
}
/* Sau khi ghi framebuffer PHẢI đẩy cache xuống PSRAM (C2M) — nếu không, khối quét màn (DMA) đọc
   ra dữ liệu cũ/rác → màn "lèo nhèo". Kích thước là bội của dòng cache (768000/64) nên không lệch. */
static void fbFlush() {
  if (g_fb) esp_cache_msync(g_fb, (size_t)PANEL_W * PANEL_H * 2, ESP_CACHE_MSYNC_FLAG_DIR_C2M);
}
static void fbFill(uint16_t c) {
  if (!g_fb) return;
  for (int i = 0; i < PANEL_W * PANEL_H; i++) g_fb[i] = c;
}
static void fbRect(int x, int y, int w, int h, uint16_t c) {
  if (!g_fb) return;
  for (int j = y; j < y + h; j++) {
    if (j < 0 || j >= PANEL_H) continue;
    for (int i = x; i < x + w; i++) {
      if (i < 0 || i >= PANEL_W) continue;
      g_fb[j * PANEL_W + i] = c;
    }
  }
}

static bool manKhoiTao() {
  // Đèn nền tắt trong lúc init cho khỏi thấy nhiễu.
  gpio_config_t bl = { .pin_bit_mask = 1ULL << PANEL_BL_GPIO, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&bl);
  gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 0);

  // 🔴 CẤP NGUỒN D-PHY (LDO kênh 3, 2.5V) — thiếu bước này DSI không chạy, màn ĐEN.
  static esp_ldo_channel_handle_t ldo = nullptr;
  esp_ldo_channel_config_t ldo_cfg = {};
  ldo_cfg.chan_id    = 3;
  ldo_cfg.voltage_mv = 2500;
  if (esp_ldo_acquire_channel(&ldo_cfg, &ldo) != ESP_OK) { Serial.println("[LCD] LOI: LDO D-PHY (kenh 3)"); return false; }
  Serial.println("[LCD] LDO D-PHY 2.5V OK.");

  // Bus DSI (2 lane, 500 Mbps).
  esp_lcd_dsi_bus_handle_t dsi = nullptr;
  esp_lcd_dsi_bus_config_t bus = {};
  bus.bus_id             = 0;
  bus.num_data_lanes     = PANEL_DSI_LANES;
  bus.phy_clk_src        = MIPI_DSI_PHY_CLK_SRC_DEFAULT;
  bus.lane_bit_rate_mbps = PANEL_DSI_LANE_MBPS;
  if (esp_lcd_new_dsi_bus(&bus, &dsi) != ESP_OK) { Serial.println("[LCD] LOI: new_dsi_bus"); return false; }

  // Kênh lệnh DBI (gửi init).
  esp_lcd_dbi_io_config_t dbi = {};
  dbi.virtual_channel = 0;
  dbi.lcd_cmd_bits    = 8;
  dbi.lcd_param_bits  = 8;
  if (esp_lcd_new_panel_io_dbi(dsi, &dbi, &g_io) != ESP_OK) { Serial.println("[LCD] LOI: io_dbi"); return false; }

  // Reset cứng panel (GPIO5).
  gpio_config_t rs = { .pin_bit_mask = 1ULL << PANEL_RESET_GPIO, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&rs);
  gpio_set_level((gpio_num_t)PANEL_RESET_GPIO, 0); delay(20);
  gpio_set_level((gpio_num_t)PANEL_RESET_GPIO, 1); delay(130);

  // Gửi bảng init ST7701S của đúng bo.
  for (size_t i = 0; i < ST7701_JC4880P443_INIT_N; i++) {
    const st7701_cmd_t* c = &ST7701_JC4880P443_INIT[i];
    esp_lcd_panel_io_tx_param(g_io, c->cmd, c->len ? c->data : nullptr, c->len);
  }
  esp_lcd_panel_io_tx_param(g_io, 0x11, nullptr, 0);   // SLPOUT
  delay(120);
  esp_lcd_panel_io_tx_param(g_io, 0x29, nullptr, 0);   // DISPON

  // Panel DPI (quét ảnh liên tục từ framebuffer).
  esp_lcd_dpi_panel_config_t dpi = {};
  dpi.virtual_channel     = 0;
  dpi.dpi_clk_src         = MIPI_DSI_DPI_CLK_SRC_DEFAULT;
  dpi.dpi_clock_freq_mhz  = PANEL_DPI_HZ / 1000000;
  dpi.pixel_format        = LCD_COLOR_PIXEL_FORMAT_RGB565;
  dpi.num_fbs             = 1;
  dpi.video_timing.h_size            = PANEL_W;
  dpi.video_timing.v_size            = PANEL_H;
  dpi.video_timing.hsync_back_porch  = PANEL_HSYNC_BACK;
  dpi.video_timing.hsync_pulse_width = PANEL_HSYNC_PULSE;
  dpi.video_timing.hsync_front_porch = PANEL_HSYNC_FRONT;
  dpi.video_timing.vsync_back_porch  = PANEL_VSYNC_BACK;
  dpi.video_timing.vsync_pulse_width = PANEL_VSYNC_PULSE;
  dpi.video_timing.vsync_front_porch = PANEL_VSYNC_FRONT;
  dpi.flags.use_dma2d = true;
  if (esp_lcd_new_panel_dpi(dsi, &dpi, &g_panel) != ESP_OK) { Serial.println("[LCD] LOI: new_panel_dpi"); return false; }
  /* KHÔNG gọi esp_lcd_panel_reset / disp_on_off cho panel DPI — panel này KHÔNG hỗ trợ (nó quét
     ảnh liên tục từ framebuffer). Reset cứng đã làm bằng GPIO5 ở trên. Chỉ cần init. */
  esp_lcd_panel_init(g_panel);

  // Lấy con trỏ framebuffer để vẽ thẳng.
  if (esp_lcd_dpi_panel_get_frame_buffer(g_panel, 1, (void**)&g_fb) != ESP_OK || !g_fb) {
    Serial.println("[LCD] LOI: get_frame_buffer"); return false;
  }
  Serial.println("[LCD] DSI + ST7701S init OK.");
  return true;
}

/* ─── CẢM ỨNG GT911 (I²C) ───────────────────────────────────────────────── */
static uint8_t g_gt = 0;   // địa chỉ GT911 thật (tự dò: 0x5D hoặc 0x14), 0 = chưa thấy

static bool gt911ReadReg(uint16_t reg, uint8_t* buf, size_t n) {
  if (!g_gt) return false;
  Wire.beginTransmission(g_gt);
  Wire.write((uint8_t)(reg >> 8)); Wire.write((uint8_t)(reg & 0xFF));
  if (Wire.endTransmission(false) != 0) return false;
  size_t got = Wire.requestFrom((int)g_gt, (int)n);
  for (size_t i = 0; i < n && Wire.available(); i++) buf[i] = Wire.read();
  return got == n;
}
static void gt911WriteReg(uint16_t reg, uint8_t v) {
  if (!g_gt) return;
  Wire.beginTransmission(g_gt);
  Wire.write((uint8_t)(reg >> 8)); Wire.write((uint8_t)(reg & 0xFF)); Wire.write(v);
  Wire.endTransmission();
}
// Thử đọc mã sản phẩm (reg 0x8140, 4 byte "911x") tại một địa chỉ để xác nhận GT911.
static bool gt911Thu(uint8_t addr) {
  Wire.beginTransmission(addr);
  Wire.write(0x81); Wire.write(0x40);
  if (Wire.endTransmission(false) != 0) return false;
  uint8_t id[4] = {0};
  Wire.requestFrom((int)addr, 4);
  for (int i = 0; i < 4 && Wire.available(); i++) id[i] = Wire.read();
  Serial.printf("[GT911] thu @0x%02X -> ID '%c%c%c%c' (%02X %02X %02X %02X)\n",
    addr, id[0]?id[0]:'.', id[1]?id[1]:'.', id[2]?id[2]:'.', id[3]?id[3]:'.', id[0],id[1],id[2],id[3]);
  return id[0] == '9' && id[1] == '1' && id[2] == '1';   // "911"
}
static void gt911Init() {
  // Reset cứng GT911 (RST GPIO3): thả lên rồi chờ chip khởi động.
  gpio_config_t rc = { .pin_bit_mask = 1ULL << P4_TOUCH_RST, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&rc);
  gpio_set_level((gpio_num_t)P4_TOUCH_RST, 0); delay(20);
  gpio_set_level((gpio_num_t)P4_TOUCH_RST, 1); delay(120);
  Wire.begin(P4_TOUCH_SDA, P4_TOUCH_SCL, 400000);

  // Quét bus để nhìn thấy mọi thiết bị.
  Serial.println("[I2C] quet bus SDA=7 SCL=8:");
  for (uint8_t a = 1; a < 127; a++) {
    Wire.beginTransmission(a);
    if (Wire.endTransmission() == 0) Serial.printf("[I2C]   @0x%02X\n", a);
  }
  // Tự dò địa chỉ GT911: thử 0x5D rồi 0x14.
  if      (gt911Thu(0x5D)) g_gt = 0x5D;
  else if (gt911Thu(0x14)) g_gt = 0x14;
  if (g_gt) Serial.printf("[GT911] DUNG dia chi 0x%02X\n", g_gt);
  else      Serial.println("[GT911] KHONG thay GT911 tren bus — kiem tra chan RST/INT hoac day I2C.");
}
// Trả số điểm chạm; nếu >0 điền x,y điểm đầu.
static int gt911Doc(int* x, int* y) {
  uint8_t st = 0;
  if (!gt911ReadReg(0x814E, &st, 1)) return 0;
  if (!(st & 0x80)) return 0;                 // chưa sẵn sàng
  int n = st & 0x0F;
  if (n > 0) {
    uint8_t p[8];
    if (gt911ReadReg(0x8150, p, 8)) {
      *x = p[1] | (p[2] << 8);
      *y = p[3] | (p[4] << 8);
    }
  }
  gt911WriteReg(0x814E, 0);                    // xoá cờ để lần đọc sau
  return n;
}

/* ─── WiFi qua C6 ───────────────────────────────────────────────────────── */
static void noiWiFi() {
  Serial.printf("[WiFi] noi qua C6 toi '%s' ...\n", SEC_WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.setTxPower(WIFI_POWER_8_5dBm);   // hạ công suất phát để bớt spike dòng (né brownout nguồn yếu)
  WiFi.begin(SEC_WIFI_SSID, SEC_WIFI_PASS);
  uint32_t t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 20000) { delay(400); Serial.print('.'); }
  Serial.println();
  if (WiFi.status() == WL_CONNECTED) Serial.printf("[WiFi] OK — IP %s\n", WiFi.localIP().toString().c_str());
  else Serial.println("[WiFi] CHUA noi — kiem tra ESP-Hosted tren C6 + SSID/mat khau.");
}

/* ─── SETUP / LOOP ──────────────────────────────────────────────────────── */
void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("\n\n=== POSH QR P4 — BRING-UP GD1b (man + cham + wifi) ===");
  Serial.printf("[chip] %s  heap %u  PSRAM %u\n", ESP.getChipModel(), ESP.getFreeHeap(), ESP.getPsramSize());

  gt911Init();

  if (manKhoiTao()) {
    fbFill(RGB565(0x21, 0x3A, 0x5E));                 // nền navy
    fbRect(0,   40, PANEL_W, 20, RGB565(0xD6,0x45,0x45)); // đỏ
    fbRect(0,   70, PANEL_W, 20, RGB565(0x2E,0x9B,0x57)); // lục
    fbRect(0,  100, PANEL_W, 20, RGB565(0x2F,0x6F,0xB0)); // lam
    fbRect(PANEL_W/2 - 60, PANEL_H/2 - 60, 120, 120, RGB565(0xFF,0xFF,0xFF)); // ô trắng giữa
    /* BÁO TRẠNG THÁI GT911 NGAY TRÊN MÀN (khỏi cần Serial): ô góc trên-trái
       XANH LÁ = dò thấy GT911 (địa chỉ 0x5D hoặc 0x14) · ĐỎ = không thấy. */
    fbRect(12, 12, 60, 60, g_gt ? RGB565(0x2E,0x9B,0x57) : RGB565(0xD6,0x45,0x45));
    fbFlush();                                          // đẩy ảnh xuống PSRAM trước khi bật đèn
    gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 1);      // bật đèn nền
    Serial.println("[LCD] Da ve man test — cham vao de kiem GT911.");
  } else {
    Serial.println("[LCD] KHOI TAO MAN THAT BAI — xem log LOI o tren.");
  }

  noiWiFi();
  Serial.println("=== bring-up xong ===");
}

void loop() {
  // Đọc cờ trạng thái GT911 (0x814E): bit7 = có dữ liệu mới, 4 bit thấp = số điểm.
  uint8_t st = 0;
  bool ok = gt911ReadReg(0x814E, &st, 1);
  bool ready = ok && (st & 0x80);
  int n = st & 0x0F;

  // Ô góc trên-PHẢI: XANH DƯƠNG khi GT911 báo CÓ CHẠM, xám khi không.
  fbRect(PANEL_W - 72, 12, 60, 60, ready ? RGB565(0x2F,0x6F,0xB0) : RGB565(0x30,0x40,0x55));

  if (ready && n > 0) {
    uint8_t p[8];
    if (gt911ReadReg(0x8150, p, 8)) {
      // GT911 (bo này): 0x8150 = X_low → x=p[0..1], y=p[2..3]. (Đã dò khớp ngón tay.)
      int x = p[0] | (p[1] << 8);
      int y = p[2] | (p[3] << 8);
      if (x < 0) x = 0; if (x >= PANEL_W) x = PANEL_W - 1;
      if (y < 0) y = 0; if (y >= PANEL_H) y = PANEL_H - 1;
      fbRect(x - 12, y - 12, 24, 24, RGB565(0xE8,0x91,0x2A));  // chấm vàng tại chỗ chạm
    }
  }
  if (ready) gt911WriteReg(0x814E, 0);   // xoá cờ để lần sau GT911 cập nhật điểm mới
  fbFlush();
  delay(15);
}
