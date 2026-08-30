/* ============================================================================
 *  POSH QR — port sang GUITION JC-ESP32P4-M3-DEV (JC4880P443)
 *  ESP32-P4 + C6 · màn ST7701S 480×800 MIPI-DSI (DÙNG KIỂU NẰM NGANG 800×480)
 *  · cảm ứng GT911 · WiFi qua C6
 *  ----------------------------------------------------------------------------
 *  GIAI ĐOẠN 2a — KHUNG GIAO DIỆN NGANG + NÚT CHỌN GÓI + VẼ MÃ QR THẬT.
 *  Nạp lên phải thấy (màn NẰM NGANG):
 *     · Trái: 3 nút gói (1 / 2 / 3), chạm để chọn — nút chọn sáng lên.
 *     · Phải: mã QR to (nội dung theo gói đang chọn) — QUÉT THỬ BẰNG ĐIỆN THOẠI
 *       để xác nhận QR vẽ đúng (bước này QR chỉ là URL thử, chưa nối tiền thật).
 *  Xong bước này = màn ngang + cảm ứng + QR đều chuẩn → GĐ2b nối server/tiền.
 *
 *  THƯ VIỆN CẦN CÀI: "QRCode" (Richard Moore) trong Arduino Library Manager.
 *  Board: ESP32P4 Dev Module · PSRAM Enabled · Flash 16MB · USB CDC On Boot Enabled.
 *  Cấp nguồn: cắm CẢ HAI cổng USB-C (né brownout).
 * ========================================================================== */
#include <Arduino.h>
#include <Wire.h>
#include <WiFi.h>
#include <qrcode.h>
#include "esp_lcd_mipi_dsi.h"
#include "esp_lcd_panel_io.h"
#include "esp_lcd_panel_ops.h"
#include "driver/gpio.h"
#include "esp_ldo_regulator.h"
#include "esp_cache.h"

#include "cau_hinh_p4.h"
#include "panel_jc4880p443.h"

#if __has_include("secrets.h")
  #include "secrets.h"
#endif
#ifndef SEC_WIFI_SSID
  #define SEC_WIFI_SSID "__DIEN_SSID__"
  #define SEC_WIFI_PASS "__DIEN_PASS__"
#endif
/* Tài khoản nhận tiền để dựng VietQR THẬT (bước sau server sẽ tự cấp). Điền trong secrets.h:
     #define SEC_BANK_BIN   "970436"      // BIN ngân hàng (VD 970436 = Vietcombank)
     #define SEC_ACCOUNT_NO "0123456789"  // số tài khoản nhận
   Chưa điền thì QR hiện chữ nhắc, không phải mã hợp lệ. */
#ifndef SEC_BANK_BIN
  #define SEC_BANK_BIN   ""
  #define SEC_ACCOUNT_NO ""
#endif

/* ─── FRAMEBUFFER (panel dọc 480×800) ────────────────────────────────────── */
static esp_lcd_panel_io_handle_t g_io    = nullptr;
static esp_lcd_panel_handle_t    g_panel = nullptr;
static uint16_t*                 g_fb    = nullptr;
static uint8_t                   g_gt    = 0;   // địa chỉ GT911 (tự dò)

static inline uint16_t RGB565(uint8_t r, uint8_t g, uint8_t b) {
  return (uint16_t)(((r & 0xF8) << 8) | ((g & 0xFC) << 3) | (b >> 3));
}
static void fbFlush() {
  if (g_fb) esp_cache_msync(g_fb, (size_t)PANEL_W * PANEL_H * 2, ESP_CACHE_MSYNC_FLAG_DIR_C2M);
}

/* ─── LỚP NẰM NGANG 800×480 (xoay 90° so với panel dọc 480×800) ──────────────
 * Nếu ảnh bị lộn (trái↔phải / trên↔dưới), đảo công thức trong lpx()/touchLandscape()
 * — em sẽ chỉnh 1 dòng theo phản hồi của anh. */
#define LW 800
#define LH 480
static inline void lpx(int lx, int ly, uint16_t c) {
  if (lx < 0 || lx >= LW || ly < 0 || ly >= LH || !g_fb) return;
  int px = LH - 1 - ly;   // cột panel dọc (0..479)
  int py = lx;            // hàng panel dọc (0..799)
  g_fb[py * PANEL_W + px] = c;
}
static void lRect(int lx, int ly, int w, int h, uint16_t c) {
  for (int j = 0; j < h; j++) for (int i = 0; i < w; i++) lpx(lx + i, ly + j, c);
}
static void lFill(uint16_t c) { lRect(0, 0, LW, LH, c); }

/* Font số 5×7 (0–9) để hiện số gói / giá — vẽ phóng to. */
static const uint8_t FONT57[10][7] = {
  {0x0E,0x11,0x13,0x15,0x19,0x11,0x0E},{0x04,0x0C,0x04,0x04,0x04,0x04,0x0E},
  {0x0E,0x11,0x01,0x02,0x04,0x08,0x1F},{0x1F,0x02,0x04,0x02,0x01,0x11,0x0E},
  {0x02,0x06,0x0A,0x12,0x1F,0x02,0x02},{0x1F,0x10,0x1E,0x01,0x01,0x11,0x0E},
  {0x06,0x08,0x10,0x1E,0x11,0x11,0x0E},{0x1F,0x01,0x02,0x04,0x08,0x08,0x08},
  {0x0E,0x11,0x11,0x0E,0x11,0x11,0x0E},{0x0E,0x11,0x11,0x0F,0x01,0x02,0x0C},
};
static void lDigit(int lx, int ly, int d, int sc, uint16_t c) {
  if (d < 0 || d > 9) return;
  for (int r = 0; r < 7; r++) for (int b = 0; b < 5; b++)
    if (FONT57[d][r] & (1 << (4 - b))) lRect(lx + b * sc, ly + r * sc, sc, sc, c);
}
// Vẽ số nguyên (căn trái), trả bề rộng đã vẽ (px).
static int lNum(int lx, int ly, long v, int sc, uint16_t c) {
  char s[12]; int n = snprintf(s, sizeof s, "%ld", v);
  int x = lx;
  for (int i = 0; i < n; i++) { lDigit(x, ly, s[i] - '0', sc, c); x += 6 * sc; }
  return x - lx;
}

/* ─── VietQR (chuẩn EMV/Napas) — bê nguyên từ bản cũ esp32_ghe_massage ────── */
static String _tlv(const char* id, const String& val) {
  char len[3]; snprintf(len, sizeof len, "%02d", (int)val.length()); return String(id) + len + val;
}
static String _crc16(const String& s) {
  uint16_t crc = 0xFFFF;
  for (size_t i = 0; i < s.length(); i++) { crc ^= ((uint8_t)s[i]) << 8;
    for (int b = 0; b < 8; b++) crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1); }
  char o[5]; snprintf(o, sizeof o, "%04X", crc); return String(o);
}
static String buildVietQR(const String& bin, const String& acct, long amount, const String& addInfo) {
  String s = _tlv("00","01") + _tlv("01", amount ? "12" : "11");
  String ben = _tlv("00", bin) + _tlv("01", acct);
  s += _tlv("38", _tlv("00","A000000727") + _tlv("01", ben) + _tlv("02","QRIBFTTA"));
  s += _tlv("53","704");
  if (amount) s += _tlv("54", String(amount));
  s += _tlv("58","VN");
  if (addInfo.length()) s += _tlv("62", _tlv("08", addInfo));
  s += "6304"; return s + _crc16(s);
}

/* ─── VẼ MÃ QR (thư viện QRCode) — version 11 đủ chứa chuỗi VietQR ─────────── */
static void lQR(int lx, int ly, int oPx, const char* text) {
  QRCode qr; uint8_t buf[qrcode_getBufferSize(11)];
  qrcode_initText(&qr, buf, 11, ECC_MEDIUM, text);   // version 11 (~177 byte): đủ cho VietQR
  int mod = (oPx) / qr.size; if (mod < 1) mod = 1;
  int side = mod * qr.size;
  lRect(lx, ly, side + 2 * mod, side + 2 * mod, RGB565(0xFF,0xFF,0xFF)); // nền trắng + viền
  for (int y = 0; y < qr.size; y++)
    for (int x = 0; x < qr.size; x++)
      if (qrcode_getModule(&qr, x, y))
        lRect(lx + mod + x * mod, ly + mod + y * mod, mod, mod, RGB565(0,0,0));
}

/* ─── KHỞI TẠO MÀN (như GĐ1, đã chạy) ────────────────────────────────────── */
static bool manKhoiTao() {
  gpio_config_t bl = { .pin_bit_mask = 1ULL << PANEL_BL_GPIO, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&bl); gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 0);

  static esp_ldo_channel_handle_t ldo = nullptr;
  esp_ldo_channel_config_t lc = {}; lc.chan_id = 3; lc.voltage_mv = 2500;
  if (esp_ldo_acquire_channel(&lc, &ldo) != ESP_OK) return false;

  esp_lcd_dsi_bus_handle_t dsi = nullptr;
  esp_lcd_dsi_bus_config_t bus = {};
  bus.bus_id = 0; bus.num_data_lanes = PANEL_DSI_LANES;
  bus.phy_clk_src = MIPI_DSI_PHY_CLK_SRC_DEFAULT; bus.lane_bit_rate_mbps = PANEL_DSI_LANE_MBPS;
  if (esp_lcd_new_dsi_bus(&bus, &dsi) != ESP_OK) return false;

  esp_lcd_dbi_io_config_t dbi = {}; dbi.virtual_channel = 0; dbi.lcd_cmd_bits = 8; dbi.lcd_param_bits = 8;
  if (esp_lcd_new_panel_io_dbi(dsi, &dbi, &g_io) != ESP_OK) return false;

  gpio_config_t rs = { .pin_bit_mask = 1ULL << PANEL_RESET_GPIO, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&rs);
  gpio_set_level((gpio_num_t)PANEL_RESET_GPIO, 0); delay(20);
  gpio_set_level((gpio_num_t)PANEL_RESET_GPIO, 1); delay(130);

  for (size_t i = 0; i < ST7701_JC4880P443_INIT_N; i++) {
    const st7701_cmd_t* c = &ST7701_JC4880P443_INIT[i];
    esp_lcd_panel_io_tx_param(g_io, c->cmd, c->len ? c->data : nullptr, c->len);
  }
  esp_lcd_panel_io_tx_param(g_io, 0x11, nullptr, 0); delay(120);
  esp_lcd_panel_io_tx_param(g_io, 0x29, nullptr, 0);

  esp_lcd_dpi_panel_config_t dpi = {};
  dpi.virtual_channel = 0; dpi.dpi_clk_src = MIPI_DSI_DPI_CLK_SRC_DEFAULT;
  dpi.dpi_clock_freq_mhz = PANEL_DPI_HZ / 1000000;
  dpi.pixel_format = LCD_COLOR_PIXEL_FORMAT_RGB565; dpi.num_fbs = 1;
  dpi.video_timing.h_size = PANEL_W; dpi.video_timing.v_size = PANEL_H;
  dpi.video_timing.hsync_back_porch = PANEL_HSYNC_BACK; dpi.video_timing.hsync_pulse_width = PANEL_HSYNC_PULSE;
  dpi.video_timing.hsync_front_porch = PANEL_HSYNC_FRONT; dpi.video_timing.vsync_back_porch = PANEL_VSYNC_BACK;
  dpi.video_timing.vsync_pulse_width = PANEL_VSYNC_PULSE; dpi.video_timing.vsync_front_porch = PANEL_VSYNC_FRONT;
  dpi.flags.use_dma2d = true;
  if (esp_lcd_new_panel_dpi(dsi, &dpi, &g_panel) != ESP_OK) return false;
  esp_lcd_panel_init(g_panel);
  if (esp_lcd_dpi_panel_get_frame_buffer(g_panel, 1, (void**)&g_fb) != ESP_OK || !g_fb) return false;
  return true;
}

/* ─── GT911 (như GĐ1, đọc toạ độ kiểu X_low @0x8150) ─────────────────────── */
static bool gtRead(uint16_t reg, uint8_t* b, size_t n) {
  if (!g_gt) return false;
  Wire.beginTransmission(g_gt); Wire.write(reg >> 8); Wire.write(reg & 0xFF);
  if (Wire.endTransmission(false) != 0) return false;
  size_t got = Wire.requestFrom((int)g_gt, (int)n);
  for (size_t i = 0; i < n && Wire.available(); i++) b[i] = Wire.read();
  return got == n;
}
static void gtWrite(uint16_t reg, uint8_t v) {
  if (!g_gt) return;
  Wire.beginTransmission(g_gt); Wire.write(reg >> 8); Wire.write(reg & 0xFF); Wire.write(v); Wire.endTransmission();
}
static bool gtProbe(uint8_t a) {
  Wire.beginTransmission(a); Wire.write(0x81); Wire.write(0x40);
  if (Wire.endTransmission(false) != 0) return false;
  uint8_t id[4] = {0}; Wire.requestFrom((int)a, 4);
  for (int i = 0; i < 4 && Wire.available(); i++) id[i] = Wire.read();
  return id[0] == '9' && id[1] == '1' && id[2] == '1';
}
static void gtInit() {
  gpio_config_t rc = { .pin_bit_mask = 1ULL << P4_TOUCH_RST, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&rc);
  gpio_set_level((gpio_num_t)P4_TOUCH_RST, 0); delay(20);
  gpio_set_level((gpio_num_t)P4_TOUCH_RST, 1); delay(120);
  Wire.begin(P4_TOUCH_SDA, P4_TOUCH_SCL, 400000);
  if (gtProbe(0x5D)) g_gt = 0x5D; else if (gtProbe(0x14)) g_gt = 0x14;
}
// Lấy 1 điểm chạm ở toạ độ NGANG (0..799, 0..479). Trả true nếu đang chạm.
static bool touchLandscape(int* lx, int* ly) {
  uint8_t st = 0;
  if (!gtRead(0x814E, &st, 1) || !(st & 0x80)) return false;
  int n = st & 0x0F; bool has = false;
  if (n > 0) {
    uint8_t p[8];
    if (gtRead(0x8150, p, 8)) {
      int px = p[0] | (p[1] << 8);   // toạ độ theo panel DỌC (X: 0..479)
      int py = p[2] | (p[3] << 8);   //                       (Y: 0..799)
      // Nghịch đảo lpx: px = LH-1-ly, py = lx  →  lx = py, ly = LH-1-px
      *lx = py; *ly = LH - 1 - px;
      if (*lx < 0) *lx = 0; if (*lx >= LW) *lx = LW - 1;
      if (*ly < 0) *ly = 0; if (*ly >= LH) *ly = LH - 1;
      has = true;
    }
  }
  gtWrite(0x814E, 0);
  return has;
}

/* ─── GIAO DIỆN GĐ2a: 3 nút gói + QR ────────────────────────────────────── */
struct Goi { int so; long gia; const char* url; };
static const Goi GOI[3] = {
  {1,  50000, "https://khmatrix.com/ghe?g=1"},
  {2, 100000, "https://khmatrix.com/ghe?g=2"},
  {3, 150000, "https://khmatrix.com/ghe?g=3"},
};
static int g_chon = 0;   // gói đang chọn (0..2)

// Vùng nút (ngang): 3 nút xếp dọc bên trái.
static const int BTN_X = 24, BTN_W = 300, BTN_H = 120, BTN_Y0 = 40, BTN_GAP = 20;
static void veManHinh() {
  lFill(RGB565(0x21, 0x3A, 0x5E));   // nền navy
  // Nút gói
  for (int i = 0; i < 3; i++) {
    int y = BTN_Y0 + i * (BTN_H + BTN_GAP);
    uint16_t nen = (i == g_chon) ? RGB565(0xE8,0x91,0x2A) : RGB565(0x2F,0x6F,0xB0);
    lRect(BTN_X, y, BTN_W, BTN_H, nen);
    lRect(BTN_X + 6, y + 6, BTN_W - 12, BTN_H - 12, (i == g_chon) ? RGB565(0xF6,0xB4,0x63) : RGB565(0x27,0x5C,0x95));
    lDigit(BTN_X + 26, y + 28, GOI[i].so, 8, RGB565(0xFF,0xFF,0xFF));       // số gói to
    lNum(BTN_X + 110, y + 44, GOI[i].gia, 5, RGB565(0xFF,0xFF,0xFF));       // giá
  }
  // QR bên phải: MÃ VietQR THẬT theo gói đang chọn (số tiền = giá gói).
  // Nội dung CK gồm mã ghế + gói để server đối chiếu (tạm GHE01; GĐ2b-2 server cấp mã thật).
  String memo = "GHE01 G" + String(GOI[g_chon].so);
  String payload;
  if (strlen(SEC_BANK_BIN) && strlen(SEC_ACCOUNT_NO))
    payload = buildVietQR(SEC_BANK_BIN, SEC_ACCOUNT_NO, GOI[g_chon].gia, memo);
  else
    payload = "CHUA DIEN SEC_BANK_BIN / SEC_ACCOUNT_NO trong secrets.h";
  lQR(430, 70, 320, payload.c_str());
}

/* ─── WiFi (giữ, hạ TX né brownout) ─────────────────────────────────────── */
static void noiWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.setTxPower(WIFI_POWER_8_5dBm);
  WiFi.begin(SEC_WIFI_SSID, SEC_WIFI_PASS);
}

void setup() {
  Serial.begin(115200);
  gtInit();
  if (manKhoiTao()) {
    veManHinh();
    fbFlush();
    gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 1);   // bật đèn nền
  }
  noiWiFi();
}

void loop() {
  int lx, ly;
  if (touchLandscape(&lx, &ly)) {
    // Chạm vào nút nào?
    for (int i = 0; i < 3; i++) {
      int y = BTN_Y0 + i * (BTN_H + BTN_GAP);
      if (lx >= BTN_X && lx < BTN_X + BTN_W && ly >= y && ly < y + BTN_H) {
        if (g_chon != i) { g_chon = i; veManHinh(); fbFlush(); }
        break;
      }
    }
    delay(120);   // chống dội chạm
  }
  delay(15);
}
