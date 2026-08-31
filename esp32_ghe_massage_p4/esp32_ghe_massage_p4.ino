/* ============================================================================
 *  POSH QR — port sang GUITION JC-ESP32P4-M3-DEV (JC4880P443)
 *  ESP32-P4 + C6 · màn ST7701S 480×800 MIPI-DSI (DÙNG NGANG 800×480) · GT911 · WiFi C6
 *  ----------------------------------------------------------------------------
 *  GIAI ĐOẠN 2b-2 — NỐI SERVER THẬT + BÊ NGUYÊN CẤU TRÚC LOGIC BẢN CŨ.
 *
 *  Luồng (như esp32_ghe_massage, chạy mạng qua WiFi thay 4G AT):
 *    IDLE  : hỏi server (nhịp) lấy MÃ GHẾ + tài khoản + bảng gói → hiện gói.
 *    khách chạm gói → WAIT_PAY: dựng VietQR THẬT (số tiền + nội dung GHE<id> <mã>) →
 *            poll server "luot" xem tiền vào chưa.
 *    tiền vào → CẢM ƠN (~4s) → RUNNING: đếm ngược đúng số phút của gói.
 *    hết giờ → IDLE.
 *
 *  ⚠️ RELAY / CỔNG TIỀN / DÒ XUNG (I/O vật lý) để GĐ3 — cần chốt chân trên bo.
 *     Ở đây RUNNING chỉ đếm ngược trên màn + báo "running" cho server qua nhịp.
 *
 *  THƯ VIỆN: "QRCode" (Richard Moore) · "ArduinoJson" (Benoît Blanchon).
 *  Board: ESP32P4 Dev Module · PSRAM Enabled · Flash 16MB · USB CDC On Boot Enabled.
 *  Nguồn: cắm CẢ HAI cổng USB-C.
 * ========================================================================== */
#include <Arduino.h>
#include <Wire.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <qrcode.h>
#include "esp_lcd_mipi_dsi.h"
#include "esp_lcd_panel_io.h"
#include "esp_lcd_panel_ops.h"
#include "driver/gpio.h"
#include "esp_ldo_regulator.h"
#include "esp_cache.h"
#include "esp_mac.h"

#include "cau_hinh_p4.h"
#include "panel_jc4880p443.h"
#include "net_4g.h"       // 4G A7680C (dự phòng khi WiFi rớt)
#include "outbox.h"       // hàng đợi tiền mặt khi mất mạng → có mạng tự đẩy
#if (P4_TIEN_RX_ICT >= 0) && (P4_TIEN_TX_GHE >= 0)
  #define CO_CONG_TIEN 1
  #include "cong_tien.h"  // ICT cổng tiền mặt (Serial1 4800 8E1)
#else
  #define CO_CONG_TIEN 0
#endif

#if __has_include("secrets.h")
  #include "secrets.h"
#endif
#ifndef SEC_WIFI_SSID
  #define SEC_WIFI_SSID "__DIEN_SSID__"
  #define SEC_WIFI_PASS "__DIEN_PASS__"
#endif
#ifndef SEC_WP_URL
  #define SEC_WP_URL "https://khmatrix.com/ghe-may"   // đường /ghe-may của website
  #define SEC_WP_KEY "__DIEN_KHOA_MAY__"              // khớp VHG_KHOA_MAY ở wp-config.php
#endif
#ifndef SEC_BANK_BIN
  #define SEC_BANK_BIN   ""    // dự phòng khi server chưa trả tài khoản
  #define SEC_ACCOUNT_NO ""
#endif

/* ─── FRAMEBUFFER + phần cứng (như GĐ1, đã chạy) ─────────────────────────── */
static esp_lcd_panel_io_handle_t g_io = nullptr;
static esp_lcd_panel_handle_t    g_panel = nullptr;
static uint16_t*                 g_fb = nullptr;
static uint8_t                   g_gt = 0;

static inline uint16_t RGB565(uint8_t r, uint8_t g, uint8_t b) {
  return (uint16_t)(((r & 0xF8) << 8) | ((g & 0xFC) << 3) | (b >> 3));
}
static void fbFlush() {
  if (g_fb) esp_cache_msync(g_fb, (size_t)PANEL_W * PANEL_H * 2, ESP_CACHE_MSYNC_FLAG_DIR_C2M);
}

/* ─── LỚP NGANG 800×480 (xoay 90° panel dọc) ─────────────────────────────── */
#define LW 800
#define LH 480
static inline void lpx(int lx, int ly, uint16_t c) {
  if (lx < 0 || lx >= LW || ly < 0 || ly >= LH || !g_fb) return;
  g_fb[lx * PANEL_W + (LH - 1 - ly)] = c;
}
static void lRect(int lx, int ly, int w, int h, uint16_t c) {
  for (int j = 0; j < h; j++) for (int i = 0; i < w; i++) lpx(lx + i, ly + j, c);
}
static void lFill(uint16_t c) { lRect(0, 0, LW, LH, c); }

/* ─── KHỬ RĂNG CƯA (anti-alias): trộn màu theo độ phủ 0..255 ───────────────── */
static inline uint16_t blend565(uint16_t fg, uint16_t bg, uint8_t a) {
  uint16_t fr = (fg >> 11) & 0x1F, fgc = (fg >> 5) & 0x3F, fb = fg & 0x1F;
  uint16_t br = (bg >> 11) & 0x1F, bgc = (bg >> 5) & 0x3F, bb = bg & 0x1F;
  uint16_t r = (fr * a + br * (255 - a) + 127) / 255;
  uint16_t g = (fgc * a + bgc * (255 - a) + 127) / 255;
  uint16_t b = (fb * a + bb * (255 - a) + 127) / 255;
  return (uint16_t)((r << 11) | (g << 5) | b);
}
// Chữ nhật BO GÓC mượt (góc khử răng cưa, trộn với nền `bg`).
static void lRoundRectA(int lx, int ly, int w, int h, int r, uint16_t c, uint16_t bg) {
  if (r * 2 > w) r = w / 2; if (r * 2 > h) r = h / 2;
  if (r < 1) { lRect(lx, ly, w, h, c); return; }
  lRect(lx + r, ly, w - 2 * r, h, c);
  lRect(lx, ly + r, r, h - 2 * r, c);
  lRect(lx + w - r, ly + r, r, h - 2 * r, c);
  for (int cn = 0; cn < 4; cn++) {
    int ox = (cn & 1) ? (lx + w - r) : lx;
    int oy = (cn & 2) ? (ly + h - r) : ly;
    float ccx = (cn & 1) ? (float)(lx + w - r) : (float)(lx + r);
    float ccy = (cn & 2) ? (float)(ly + h - r) : (float)(ly + r);
    for (int j = 0; j < r; j++) for (int i = 0; i < r; i++) {
      float dx = (ox + i + 0.5f) - ccx, dy = (oy + j + 0.5f) - ccy;
      float cov = (float)r - sqrtf(dx * dx + dy * dy) + 0.5f;
      if (cov <= 0) continue; if (cov > 1) cov = 1;
      uint8_t a = (uint8_t)(cov * 255);
      lpx(ox + i, oy + j, a >= 255 ? c : blend565(c, bg, a));
    }
  }
}
// Đĩa tròn ĐẶC mượt.
static void lCircleA(int cx, int cy, int r, uint16_t c, uint16_t bg) {
  for (int j = -r - 1; j <= r + 1; j++) for (int i = -r - 1; i <= r + 1; i++) {
    float cov = (float)r - sqrtf((float)(i * i + j * j)) + 0.5f;
    if (cov <= 0) continue; if (cov > 1) cov = 1;
    uint8_t a = (uint8_t)(cov * 255);
    lpx(cx + i, cy + j, a >= 255 ? c : blend565(c, bg, a));
  }
}
// Nét đậm bo đầu (đóng dấu đĩa tròn dọc đoạn) — dùng vẽ dấu tích.
static void lStroke(int x0, int y0, int x1, int y1, int rad, uint16_t c, uint16_t bg) {
  int steps = abs(x1 - x0) + abs(y1 - y0); if (steps < 1) steps = 1;
  for (int s = 0; s <= steps; s++)
    lCircleA(x0 + (x1 - x0) * s / steps, y0 + (y1 - y0) * s / steps, rad, c, bg);
}

/* ─── FONT SỐ khử răng cưa (nội suy 5×7 rồi tăng tương phản cho nét sắc) ───── */
static const uint8_t FONT57[10][7] = {
  {0x0E,0x11,0x13,0x15,0x19,0x11,0x0E},{0x04,0x0C,0x04,0x04,0x04,0x04,0x0E},
  {0x0E,0x11,0x01,0x02,0x04,0x08,0x1F},{0x1F,0x02,0x04,0x02,0x01,0x11,0x0E},
  {0x02,0x06,0x0A,0x12,0x1F,0x02,0x02},{0x1F,0x10,0x1E,0x01,0x01,0x11,0x0E},
  {0x06,0x08,0x10,0x1E,0x11,0x11,0x0E},{0x1F,0x01,0x02,0x04,0x08,0x08,0x08},
  {0x0E,0x11,0x11,0x0E,0x11,0x11,0x0E},{0x0E,0x11,0x11,0x0F,0x01,0x02,0x0C},
};
static const uint8_t GLYPH_D[7] = {0x01,0x0F,0x0F,0x11,0x11,0x11,0x0F};
static inline float _fbit(const uint8_t g[7], int cx, int cy) {
  if (cx < 0 || cx > 4 || cy < 0 || cy > 6) return 0.0f;
  return ((g[cy] >> (4 - cx)) & 1) ? 1.0f : 0.0f;
}
static void lBitmap(int lx, int ly, const uint8_t g[7], int sc, uint16_t c, uint16_t bg) {
  int W = 5 * sc, H = 7 * sc;
  for (int j = 0; j < H; j++) for (int i = 0; i < W; i++) {
    float u = (i + 0.5f) / sc - 0.5f, v = (j + 0.5f) / sc - 0.5f;
    int u0 = (int)floorf(u), v0 = (int)floorf(v);
    float fu = u - u0, fv = v - v0;
    float top = _fbit(g, u0, v0) * (1 - fu) + _fbit(g, u0 + 1, v0) * fu;
    float bot = _fbit(g, u0, v0 + 1) * (1 - fu) + _fbit(g, u0 + 1, v0 + 1) * fu;
    float cov = top * (1 - fv) + bot * fv;
    cov = (cov - 0.5f) * 1.7f + 0.5f;      // tăng tương phản: giữ nét đặc, chỉ mềm viền
    if (cov <= 0.02f) continue; if (cov > 1) cov = 1;
    uint8_t a = (uint8_t)(cov * 255);
    lpx(lx + i, ly + j, a >= 250 ? c : blend565(c, bg, a));
  }
}
static void lDigit(int lx, int ly, int d, int sc, uint16_t c, uint16_t bg) {
  if (d >= 0 && d <= 9) lBitmap(lx, ly, FONT57[d], sc, c, bg);
}
static int lNum(int lx, int ly, long v, int sc, uint16_t c, uint16_t bg) {
  char s[16]; int n = snprintf(s, sizeof s, "%ld", v); int x = lx;
  for (int i = 0; i < n; i++) { lDigit(x, ly, s[i] - '0', sc, c, bg); x += 6 * sc; }
  return x - lx;
}
static int lPrice(int lx, int ly, long v, int sc, uint16_t c, uint16_t bg) {  // giá: chấm nghìn + "đ"
  char s[16]; int n = snprintf(s, sizeof s, "%ld", v); int x = lx;
  for (int i = 0; i < n; i++) {
    lDigit(x, ly, s[i] - '0', sc, c, bg); x += 6 * sc;
    int rem = n - 1 - i;
    if (rem > 0 && rem % 3 == 0) { lCircleA(x + sc / 2, ly + 6 * sc + sc / 2, (sc + 1) / 2, c, bg); x += 2 * sc; }
  }
  x += sc; lBitmap(x, ly, GLYPH_D, sc, c, bg); x += 6 * sc; return x - lx;
}
static int lPriceW(long v, int sc) {   // bề rộng chuỗi giá (để căn giữa)
  char s[16]; int n = snprintf(s, sizeof s, "%ld", v); int w = 0;
  for (int i = 0; i < n; i++) { w += 6 * sc; int rem = n - 1 - i; if (rem > 0 && rem % 3 == 0) w += 2 * sc; }
  return w + sc + 6 * sc;
}
static void lColon(int lx, int ly, int sc, uint16_t c, uint16_t bg) {
  lCircleA(lx + sc / 2, ly + 2 * sc, (sc + 1) / 2, c, bg);
  lCircleA(lx + sc / 2, ly + 5 * sc, (sc + 1) / 2, c, bg);
}
static void lMMSS(int lx, int ly, int secs, int sc, uint16_t c, uint16_t bg) {
  if (secs < 0) secs = 0; int mm = secs / 60, ss = secs % 60; int x = lx;
  lDigit(x, ly, mm / 10, sc, c, bg); x += 6 * sc; lDigit(x, ly, mm % 10, sc, c, bg); x += 6 * sc;
  lColon(x, ly, sc, c, bg); x += 3 * sc;
  lDigit(x, ly, ss / 10, sc, c, bg); x += 6 * sc; lDigit(x, ly, ss % 10, sc, c, bg);
}
static int lMMSSW(int sc) { return (6 + 6 + 3 + 6 + 6) * sc; }   // bề rộng MM:SS

/* ─── VietQR (bê nguyên bản cũ) ──────────────────────────────────────────── */
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
  s += _tlv("53","704"); if (amount) s += _tlv("54", String(amount));
  s += _tlv("58","VN"); if (addInfo.length()) s += _tlv("62", _tlv("08", addInfo));
  s += "6304"; return s + _crc16(s);
}
static void lQR(int lx, int ly, int oPx, const char* text) {
  QRCode qr; uint8_t buf[qrcode_getBufferSize(11)];
  qrcode_initText(&qr, buf, 11, ECC_MEDIUM, text);
  int mod = oPx / qr.size; if (mod < 1) mod = 1;
  int side = mod * qr.size;
  lRect(lx, ly, side + 2 * mod, side + 2 * mod, RGB565(0xFF,0xFF,0xFF));
  for (int y = 0; y < qr.size; y++) for (int x = 0; x < qr.size; x++)
    if (qrcode_getModule(&qr, x, y)) lRect(lx + mod + x * mod, ly + mod + y * mod, mod, mod, 0x0000);
}

/* ─── PHẦN CỨNG: init màn + GT911 (như GĐ1) ─────────────────────────────── */
static bool manKhoiTao() {
  gpio_config_t bl = { .pin_bit_mask = 1ULL << PANEL_BL_GPIO, .mode = GPIO_MODE_OUTPUT };
  gpio_config(&bl); gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 0);
  static esp_ldo_channel_handle_t ldo = nullptr;
  esp_ldo_channel_config_t lc = {}; lc.chan_id = 3; lc.voltage_mv = 2500;
  if (esp_ldo_acquire_channel(&lc, &ldo) != ESP_OK) return false;
  esp_lcd_dsi_bus_handle_t dsi = nullptr;
  esp_lcd_dsi_bus_config_t bus = {}; bus.bus_id = 0; bus.num_data_lanes = PANEL_DSI_LANES;
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
static bool touchLandscape(int* lx, int* ly) {
  uint8_t st = 0;
  if (!gtRead(0x814E, &st, 1) || !(st & 0x80)) return false;
  int n = st & 0x0F; bool has = false;
  if (n > 0) { uint8_t p[8];
    if (gtRead(0x8150, p, 8)) {
      int px = p[0] | (p[1] << 8), py = p[2] | (p[3] << 8);
      *lx = py; *ly = LH - 1 - px;
      if (*lx < 0) *lx = 0; if (*lx >= LW) *lx = LW - 1;
      if (*ly < 0) *ly = 0; if (*ly >= LH) *ly = LH - 1;
      has = true;
    }
  }
  gtWrite(0x814E, 0); return has;
}

/* ─── TRẠNG THÁI + CẤU HÌNH TỪ SERVER ───────────────────────────────────── */
enum State { ST_IDLE, ST_WAIT_PAY, ST_CAMON, ST_RUNNING, ST_NOACC };
static State state = ST_IDLE;

static const int PKG_MAX = 4;
static long PKG_AMT[PKG_MAX]  = {50000, 100000, 150000, 200000};
static int  PKG_PHUT[PKG_MAX] = {0, 0, 0, 0};
static int  PKG_N = 3;
static long PRICE_VND = 50000; static int MINUTES = 6;   // tỉ lệ quy đổi phút
static String CHAIR_ID = "", BANK_BIN = SEC_BANK_BIN, ACCOUNT_NO = SEC_ACCOUNT_NO, ND_TIEN_TO = "";
static bool CHUA_GAN = true;

static int  payIdx = 0; static long payAmount = 0; static int payMinutes = 0; static char payCode[8] = "";
static uint32_t camonUntil = 0, waitUntil = 0, lastPoll = 0, lastNhip = 0, lastDrawSec = 0;
/* Đếm ngược CHỐT theo xung ghế: chỉ trừ giờ khi ghế THỰC SỰ chạy (dò xung). */
static uint32_t runRemainMs = 0;   // ms còn lại của phiên
static uint32_t lastTickMs  = 0;   // mốc trừ giờ gần nhất
static uint32_t gheChetTu   = 0;   // lúc BẮT ĐẦU thấy ghế không chạy (0 = đang chạy)
static bool     gheKhongChay = false;

/* ─── I/O VẬT LÝ (GĐ3): relay chạy ghế + bypass + dò xung ─────────────────── */
static void relayGhe(bool on) {
#if (P4_RELAY_PIN >= 0)
  digitalWrite(P4_RELAY_PIN, (on == P4_RELAY_ACTIVE_HIGH) ? HIGH : LOW);
#endif
}
static void bypassSet(bool on) {
#if (P4_BYPASS_PIN >= 0)
  digitalWrite(P4_BYPASS_PIN, (on == P4_BYPASS_ACTIVE_HIGH) ? HIGH : LOW);
#endif
}
// true = ghế đang chạy. Không gán chân / tắt cổng → coi như CHẠY (không chốt theo xung).
static bool gheDangChay() {
#if (P4_GHECHAY_PIN >= 0) && P4_GATE_BY_PIN
  return digitalRead(P4_GHECHAY_PIN) == LOW;   // chạy=LOW / tắt=HIGH (như bản cũ)
#else
  return true;
#endif
}
static void ioInit() {
#if (P4_RELAY_PIN >= 0)
  pinMode(P4_RELAY_PIN, OUTPUT);
#endif
#if (P4_BYPASS_PIN >= 0)
  pinMode(P4_BYPASS_PIN, OUTPUT);
#endif
  relayGhe(false);      // AN TOÀN: ghế KHÔNG tự chạy lúc cấp nguồn
  bypassSet(false);     // tiền đi qua tiếp điểm NC (fail-safe) khi chưa điều khiển
#if (P4_GHECHAY_PIN >= 0) && P4_GATE_BY_PIN
  pinMode(P4_GHECHAY_PIN, INPUT_PULLUP);
#endif
}

static int phutGoi(int i) {
  if (i < 0 || i >= PKG_N) return 0;
  if (PKG_PHUT[i] > 0) return PKG_PHUT[i];
  if (PRICE_VND <= 0) return 0;
  return (int)(PKG_AMT[i] * (long)MINUTES / PRICE_VND);
}
static bool duNhanTien() { return ACCOUNT_NO.length() > 0 && BANK_BIN.length() > 0; }
static void genCode(char* out) {
  const char* A = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  for (int i = 0; i < 5; i++) out[i] = A[random(0, 32)]; out[5] = 0;
}
static String macBo() {
  uint8_t m[6]; esp_read_mac(m, ESP_MAC_WIFI_STA);
  char b[18]; snprintf(b, sizeof b, "%02X:%02X:%02X:%02X:%02X:%02X", m[0],m[1],m[2],m[3],m[4],m[5]);
  return String(b);
}
static String jsonEsc(String s) { s.replace("\\","\\\\"); s.replace("\"","\\\""); return s; }

/* ─── SERVER: POST /ghe-may — WiFi (C6) TRƯỚC, rớt thì 4G A7680C ──────────── */
static bool netUp() { return WiFi.status() == WL_CONNECTED || net4gReady(); }

static String wpPost(const String& viec, const String& them) {
  String body = "{\"key\":\"" + String(SEC_WP_KEY) + "\",\"viec\":\"" + viec + "\",\"mac\":\"" + macBo() + "\"";
  if (CHAIR_ID.length()) body += ",\"ma_may\":\"" + jsonEsc(CHAIR_ID) + "\"";
  if (them.length()) body += "," + them;
  body += "}";
  // 1) WiFi nếu đang nối (nhanh, ổn khi mall có WiFi tốt).
  if (WiFi.status() == WL_CONNECTED) {
    WiFiClientSecure cli; cli.setInsecure();
    HTTPClient http; http.setTimeout(12000);
    if (http.begin(cli, SEC_WP_URL)) {
      http.addHeader("Content-Type", "application/json");
      int code = http.POST(body);
      String r = (code == 200) ? http.getString() : "";
      http.end();
      if (code == 200) return r;   // WiFi lỗi → thử 4G bên dưới
    }
  }
  // 2) 4G (dự phòng): AT-HTTP giữ phiên đọc thân trả về.
  if (net4gReady()) { String r; if (net4gPost(SEC_WP_URL, body, r) == 200) return r; }
  return "";
}

/* Vẽ trước — định nghĩa ở dưới */
static void veIdle(); static void veWaitPay(); static void veCamon(); static void veRunning(); static void veNoAcc();
static void sangTrangThai(State s);

/* ─── TIỀN MẶT + HÀNG ĐỢI OFFLINE ────────────────────────────────────────── */
static Outbox outbox;
#if CO_CONG_TIEN
CongTien congTien;   // định nghĩa cho extern trong cong_tien.h
#endif

// Gửi một lượt tiền mặt lên server (idempotent theo ref). true = server đã nhận.
static bool guiTienMat(long vnd, const char* ref) {
  String r = wpPost("tien_mat", String("\"so_tien\":") + String(vnd) + ",\"ref\":\"" + ref + "\"");
  return r.indexOf("\"ok\":true") >= 0;
}

// Quy đổi tiền → phút theo tỉ lệ đang có (server nạp qua nhịp).
static int phutTuTien(long vnd) {
  if (PRICE_VND <= 0 || MINUTES <= 0) return 0;
  return (int)(vnd * (long)MINUTES / PRICE_VND);
}

// ICT vừa NUỐT một tờ: ghế CHẠY NGAY (không chờ mạng) + ghi sổ vào hàng đợi.
static void onCashIn(long vnd) {
  outbox.themTienMat(vnd);                 // nhớ để đẩy server (kể cả đang offline)
  int themPhut = phutTuTien(vnd);
  if (themPhut <= 0) return;
  if (state == ST_RUNNING) {
    runRemainMs += (uint32_t)themPhut * 60000UL;   // CỘNG dồn, không ghi đè
    payMinutes  += themPhut;
  } else {
    payAmount = vnd; payMinutes = themPhut; payIdx = 0;
    sangTrangThai(ST_RUNNING);              // vào phiên chạy ngay
  }
}

/* ─── NHỚ CẤU HÌNH VÀO NVS (mất mạng lúc bật máy vẫn có gói + QR đúng) ─────── */
static Preferences cfgNvs;
static void docCauHinh() {   // đọc lúc setup(), TRƯỚC khi vẽ màn đầu tiên
  cfgNvs.begin("cfg", true);
  String v;
  v = cfgNvs.getString("ma",  ""); if (v.length()) CHAIR_ID   = v;
  v = cfgNvs.getString("tk",  ""); if (v.length()) ACCOUNT_NO = v;
  v = cfgNvs.getString("bin", ""); if (v.length()) BANK_BIN   = v;
  if (cfgNvs.isKey("tienTo")) ND_TIEN_TO = cfgNvs.getString("tienTo", "");
  long g = cfgNvs.getLong("gia", 0); if (g > 0) PRICE_VND = g;
  int  p = cfgNvs.getInt ("phut", 0); if (p > 0) MINUTES   = p;
  int  n = cfgNvs.getInt ("pkgN", 0);
  if (n > 0 && n <= PKG_MAX) {
    PKG_N = n;
    for (int i = 0; i < n; i++) {
      char ka[8], kp[8]; snprintf(ka, sizeof ka, "a%d", i); snprintf(kp, sizeof kp, "p%d", i);
      PKG_AMT[i]  = cfgNvs.getLong(ka, PKG_AMT[i]);
      PKG_PHUT[i] = cfgNvs.getInt (kp, PKG_PHUT[i]);
    }
  }
  cfgNvs.end();
}
static void luuCauHinh() {   // gọi sau mỗi nhịp; TỰ bỏ qua nếu không đổi (khỏi mòn flash)
  static String sig = "\x01";
  String cur = CHAIR_ID + "|" + ACCOUNT_NO + "|" + BANK_BIN + "|" + ND_TIEN_TO + "|"
             + String(PRICE_VND) + "|" + String(MINUTES) + "|" + String(PKG_N);
  for (int i = 0; i < PKG_N; i++) cur += "|" + String(PKG_AMT[i]) + "," + String(PKG_PHUT[i]);
  if (cur == sig) return;
  sig = cur;
  cfgNvs.begin("cfg", false);
  cfgNvs.putString("ma", CHAIR_ID); cfgNvs.putString("tk", ACCOUNT_NO);
  cfgNvs.putString("bin", BANK_BIN); cfgNvs.putString("tienTo", ND_TIEN_TO);
  cfgNvs.putLong("gia", PRICE_VND); cfgNvs.putInt("phut", MINUTES); cfgNvs.putInt("pkgN", PKG_N);
  for (int i = 0; i < PKG_N; i++) {
    char ka[8], kp[8]; snprintf(ka, sizeof ka, "a%d", i); snprintf(kp, sizeof kp, "p%d", i);
    cfgNvs.putLong(ka, PKG_AMT[i]); cfgNvs.putInt(kp, PKG_PHUT[i]);
  }
  cfgNvs.end();
  Serial.println("[CFG] da luu cau hinh vao NVS");
}

static void nhip() {
  lastNhip = millis();
  const char* stt = (state == ST_RUNNING || state == ST_CAMON) ? "running"
                    : (state == ST_WAIT_PAY ? "wait_pay" : "idle");
  long conLai = (state == ST_RUNNING) ? (long)(runRemainMs / 1000) : 0;
  String nd = String("\"trang_thai\":\"") + stt + "\",\"con_lai\":" + String(conLai)
            + ",\"ghe_chay\":" + String((state == ST_RUNNING && !gheKhongChay) ? 1 : 0)
            + ",\"fw\":\"posh-qr-p4\"";
  String r = wpPost("nhip", nd);
  if (!r.length()) return;
  DynamicJsonDocument d(2048);
  if (deserializeJson(d, r)) return;
  String ma = String((const char*)(d["maMay"] | "")); if (ma.length()) CHAIR_ID = ma;
  CHUA_GAN = ((int)(d["chuaGan"] | 0) == 1);
  long gia = (long)(d["gia"] | 0); int phut = (int)(d["phut"] | 0);
  if (gia > 0) PRICE_VND = gia; if (phut > 0) MINUTES = phut;
  String tk = String((const char*)(d["soTk"] | "")); if (tk.length()) ACCOUNT_NO = tk;
  String bin = String((const char*)(d["bin"] | "")); if (bin.length()) BANK_BIN = bin;
  if (d.containsKey("tienTo")) ND_TIEN_TO = String((const char*)(d["tienTo"] | ""));
  JsonArrayConst goi = d["goi"];
  if (!goi.isNull()) {
    int n = 0;
    for (JsonVariantConst v : goi) {
      if (n >= PKG_MAX) break; long a; int ph = 0;
      if (v.is<JsonObjectConst>()) { a = (long)(v["t"] | 0); ph = (int)(v["p"] | 0); }
      else a = v.as<long>();
      if (a >= 1000) { PKG_AMT[n] = a; PKG_PHUT[n] = ph; n++; }
    }
    if (n > 0) PKG_N = n;
  }
  luuCauHinh();   // nhớ cấu hình mới nhất vào NVS (tự bỏ qua nếu không đổi)
  // Tiền vào trong lúc chờ (server báo qua coTien) → chạy.
  if (((int)(d["coTien"] | 0) == 1) && state == ST_WAIT_PAY) sangTrangThai(ST_CAMON);
  if (state == ST_IDLE || state == ST_NOACC) sangTrangThai(duNhanTien() ? ST_IDLE : ST_NOACC);
}
static long checkPaid() {
  String r = wpPost("luot", "\"cho\":4");
  if (!r.length()) return 0;
  DynamicJsonDocument d(512);
  if (deserializeJson(d, r)) return 0;
  if ((int)(d["co"] | 0) != 1) return 0;
  return (long)(d["so_tien"] | 0);
}

/* ─── GIAO DIỆN (bảng màu sang) ─────────────────────────────────────────── */
#define C_BG     RGB565(0xEC,0xF1,0xF7)
#define C_INK    RGB565(0x22,0x30,0x45)
#define C_BLUE   RGB565(0x2F,0x6F,0xB0)
#define C_AMBER  RGB565(0xE8,0x91,0x2A)
#define C_GREEN  RGB565(0x2E,0x9B,0x57)
#define C_RED    RGB565(0xD6,0x45,0x45)
#define C_BORDER RGB565(0xD3,0xDD,0xEA)
#define C_SHADOW RGB565(0xD7,0xDE,0xE8)
#define C_TINT   RGB565(0xFF,0xF3,0xDF)
#define C_WHITE  0xFFFF

static int g_chon = 0;
struct Btn { int x, y, w, h; };
static Btn g_btn[PKG_MAX];

/* Đồng hồ nhỏ (vành + 2 kim) — báo "số phút" không cần chữ. */
static void lClock(int cx, int cy, int r, uint16_t c, uint16_t bg) {
  lCircleA(cx, cy, r, c, bg);
  lCircleA(cx, cy, r - (r > 8 ? 3 : 2), bg, c);
  lStroke(cx, cy, cx, cy - (r - 5), (r > 12 ? 2 : 1), c, bg);          // kim dài
  lStroke(cx, cy, cx + (r - 8 > 0 ? r - 8 : 2), cy, (r > 12 ? 2 : 1), c, bg); // kim ngắn
}
/* Gợi ý "mã QR" ở màn chờ chọn gói: 3 ô định vị như góc mã QR thật. */
static void lQrHint(int cx, int cy, int s, uint16_t c, uint16_t bg) {
  int q = s / 3, off = s / 2 - q / 2;
  int px[3] = { cx - off, cx + off, cx - off };
  int py[3] = { cy - off, cy - off, cy + off };
  for (int k = 0; k < 3; k++) {
    lRoundRectA(px[k] - q / 2, py[k] - q / 2, q, q, q / 4, c, bg);
    lRoundRectA(px[k] - q / 2 + q / 6, py[k] - q / 2 + q / 6, q - q / 3, q - q / 3, q / 6, bg, c);
    lRoundRectA(px[k] - q / 6, py[k] - q / 6, q / 3, q / 3, q / 12, c, bg);
  }
}

/* Kích thước & vị trí THẺ QR bên phải — dùng chung IDLE + WAIT_PAY cho cân đối. */
#define QR_X   412
#define QR_Y    40
#define QR_W   364
#define QR_H   400

static void veIdle() {
  lFill(C_BG);
  int n = PKG_N < 1 ? 1 : (PKG_N > PKG_MAX ? PKG_MAX : PKG_N);
  int LX = 24, LWD = 372, top = QR_Y, botM = 40, gap = 18;
  int availH = LH - top - botM;
  int h = (availH - (n - 1) * gap) / n; if (h > 132) h = 132;
  for (int i = 0; i < n; i++) {
    int y = top + i * (h + gap);
    g_btn[i] = { LX, y, LWD, h };
    bool sel = (i == g_chon);
    uint16_t inner = sel ? C_TINT : C_WHITE, edge = sel ? C_AMBER : C_BORDER, badge = sel ? C_AMBER : C_BLUE;
    lRoundRectA(LX + 3, y + 5, LWD, h, 22, C_SHADOW, C_BG);
    lRoundRectA(LX, y, LWD, h, 22, edge, C_BG);
    lRoundRectA(LX + 3, y + 3, LWD - 6, h - 6, 19, inner, edge);
    // Huy hiệu số gói (đĩa tròn) + số ở giữa
    int br = h / 2 - 16; if (br > 50) br = 50;
    int bx = LX + 20 + br, by = y + h / 2;
    lCircleA(bx, by, br, badge, inner);
    int ds = br / 3; if (ds < 4) ds = 4;
    lDigit(bx - (5 * ds) / 2, by - (7 * ds) / 2, i + 1, ds, C_WHITE, badge);
    // Giá (to) + đồng hồ phút (nhỏ) xếp 2 dòng bên phải huy hiệu
    int rx = LX + 40 + 2 * br;
    lPrice(rx, y + 18, PKG_AMT[i], 5, C_INK, inner);
    int ph = phutGoi(i);
    lClock(rx + 12, y + h - 30, 12, C_BLUE, inner);
    lNum(rx + 32, y + h - 44, ph, 4, C_BLUE, inner);
  }
  // Thẻ QR bên phải — ở IDLE là gợi ý "quét mã" (mời khách chọn gói).
  lRoundRectA(QR_X + 3, QR_Y + 6, QR_W, QR_H, 24, C_SHADOW, C_BG);
  lRoundRectA(QR_X, QR_Y, QR_W, QR_H, 24, C_WHITE, C_BG);
  lRoundRectA(QR_X, QR_Y, QR_W, 10, 5, C_BLUE, C_WHITE);
  lQrHint(QR_X + QR_W / 2, QR_Y + QR_H / 2, 210, RGB565(0xC7,0xD6,0xE8), C_WHITE);
}
static void veWaitPay() {
  lFill(C_BG);
  int LX = 24, LWD = 372, y = QR_Y, h = QR_H;
  lRoundRectA(LX + 3, y + 6, LWD, h, 24, C_SHADOW, C_BG);
  lRoundRectA(LX, y, LWD, h, 24, C_AMBER, C_BG);
  lRoundRectA(LX + 4, y + 4, LWD - 8, h - 8, 20, C_TINT, C_AMBER);
  // Huy hiệu số gói (trên)
  int bx = LX + LWD / 2, by = y + 92;
  lCircleA(bx, by, 56, C_AMBER, C_TINT);
  lDigit(bx - (5 * 11) / 2, by - (7 * 11) / 2, payIdx + 1, 11, C_WHITE, C_AMBER);
  // Giá (giữa, căn giữa)
  int pw = lPriceW(payAmount, 7);
  lPrice(bx - pw / 2, y + 188, payAmount, 7, C_INK, C_TINT);
  // Cửa sổ chờ (đồng hồ + MM:SS)
  int left = (waitUntil > millis()) ? (int)((waitUntil - millis()) / 1000) : 0;
  int cw = 34 + lMMSSW(5);
  int cx0 = bx - cw / 2;
  lClock(cx0 + 12, y + 300 + 5 * 5 / 2, 13, C_INK, C_TINT);
  lMMSS(cx0 + 34, y + 300, left, 5, C_INK, C_TINT);
  // Thẻ QR VietQR THẬT
  lRoundRectA(QR_X + 3, QR_Y + 6, QR_W, QR_H, 24, C_SHADOW, C_BG);
  lRoundRectA(QR_X, QR_Y, QR_W, QR_H, 24, C_WHITE, C_BG);
  lRoundRectA(QR_X, QR_Y, QR_W, 10, 5, C_AMBER, C_WHITE);
  String memo = (ND_TIEN_TO.length() ? ND_TIEN_TO + " " : "") + "GHE" + (CHAIR_ID.length() ? CHAIR_ID : "01") + " " + String(payCode);
  String payload = buildVietQR(BANK_BIN, ACCOUNT_NO, payAmount, memo);
  int oPx = 305, mod = oPx / 61; if (mod < 1) mod = 1;   // v11 = 61 module
  int qside = mod * 61 + 2 * mod;
  lQR(QR_X + (QR_W - qside) / 2, QR_Y + (QR_H - qside) / 2, oPx, payload.c_str());
}
static void veCamon() {   // đã nhận tiền — nền xanh + dấu tích mượt
  lFill(C_GREEN);
  int cx = LW / 2, cy = LH / 2;
  lCircleA(cx, cy, 96, C_WHITE, C_GREEN);
  lStroke(cx - 42, cy + 4, cx - 12, cy + 40, 9, C_GREEN, C_WHITE);
  lStroke(cx - 12, cy + 40, cx + 50, cy - 40, 9, C_GREEN, C_WHITE);
}
static void veRunning() {
  uint16_t bg = RGB565(0x12,0x1B,0x2C);
  lFill(bg);
  int left = (int)(runRemainMs / 1000);
  uint16_t c = gheKhongChay ? C_AMBER : C_WHITE;
  int sc = 20, w = lMMSSW(sc);
  lMMSS((LW - w) / 2, 150, left, sc, c, bg);
  // Thanh tiến độ (bo góc, mượt)
  long total = (long)payMinutes * 60; if (total < 1) total = 1;
  int barX = 70, barW = LW - 140, barH = 26, barY = LH - 74;
  lRoundRectA(barX, barY, barW, barH, 13, RGB565(0x27,0x35,0x4E), bg);
  int fw = (int)((long)barW * left / total); if (fw < 0) fw = 0; if (fw > barW) fw = barW;
  if (fw >= 2) lRoundRectA(barX, barY, fw, barH, 13, gheKhongChay ? C_AMBER : C_GREEN, bg);
  if (gheKhongChay) {   // ghế đang tạm dừng → biểu tượng ‖
    lRoundRectA(LW / 2 - 26, 80, 16, 44, 5, C_AMBER, bg);
    lRoundRectA(LW / 2 + 10, 80, 16, 44, 5, C_AMBER, bg);
  }
}
static void veNoAcc() {   // chưa có tài khoản nhận — nền đỏ + dấu "!"
  lFill(C_RED);
  int cx = LW / 2, cy = LH / 2;
  lCircleA(cx, cy, 84, C_WHITE, C_RED);
  lRoundRectA(cx - 9, cy - 46, 18, 56, 9, C_RED, C_WHITE);
  lCircleA(cx, cy + 34, 11, C_RED, C_WHITE);
}
static void sangTrangThai(State s) {
  state = s;
  if (s != ST_RUNNING) relayGhe(false);   // ngoài phiên chạy: LUÔN ngắt relay
  switch (s) {
    case ST_IDLE:    veIdle();    break;
    case ST_WAIT_PAY:veWaitPay(); break;
    case ST_CAMON:   veCamon();   camonUntil = millis() + 4000; break;
    case ST_RUNNING:
      runRemainMs = (uint32_t)payMinutes * 60000UL;
      lastTickMs = millis(); gheChetTu = 0; gheKhongChay = false;
      relayGhe(true);                      // ĐÓNG relay → ghế chạy
      veRunning(); lastDrawSec = 0; break;
    case ST_NOACC:   veNoAcc();   break;
  }
  fbFlush();
}
static void startSession(int idx) {
  if (!duNhanTien()) { sangTrangThai(ST_NOACC); return; }
  payIdx = idx; payAmount = PKG_AMT[idx]; payMinutes = phutGoi(idx);
  genCode(payCode);
  waitUntil = millis() + 150000UL; lastPoll = 0;
  sangTrangThai(ST_WAIT_PAY);
}

/* ─── SETUP / LOOP ──────────────────────────────────────────────────────── */
void setup() {
  Serial.begin(115200);
  randomSeed(esp_random());
  ioInit();               // relay TẮT + bypass fail-safe + dò xung — TRƯỚC mọi thứ
  docCauHinh();           // nạp cấu hình lần chạy gần nhất từ NVS (chạy được cả khi offline)
  gtInit();
  if (manKhoiTao()) { sangTrangThai(ST_IDLE); gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 1); }
  outbox.batDau(guiTienMat);              // mở hàng đợi tiền mặt (đọc NVS)
#if CO_CONG_TIEN
  congTien.khoiDong(onCashIn, nullptr);   // ICT cổng tiền mặt (Serial1)
#endif
  WiFi.mode(WIFI_STA); WiFi.setTxPower(WIFI_POWER_8_5dBm);
  WiFi.begin(SEC_WIFI_SSID, SEC_WIFI_PASS);
}

/* Quản mạng: WiFi rồi 4G. Bật 4G khi WiFi rớt & ghế RẢNH (bring-up chặn ~20s).
   Đẩy hàng đợi tiền mặt mỗi khi có mạng. */
static void quanMang(uint32_t now) {
  static uint32_t lanThu4g = 0, lanDay = 0;
  bool wifi = (WiFi.status() == WL_CONNECTED);
  // Chỉ bật/bơm lại 4G lúc RẢNH để không đơ màn giữa phiên chạy.
  if (!wifi && !net4gReady() && (state == ST_IDLE || state == ST_NOACC)
      && (lanThu4g == 0 || now - lanThu4g > 60000)) {
    lanThu4g = now; net4gBatDau();
  }
  if (netUp() && now - lanDay > 5000) { lanDay = now; outbox.day(3); }
}

void loop() {
  uint32_t now = millis();
  int lx, ly;
  bool tch = touchLandscape(&lx, &ly);

  if (state == ST_IDLE && tch) {
    for (int i = 0; i < PKG_N; i++) {
      Btn& b = g_btn[i];
      if (lx >= b.x && lx < b.x + b.w && ly >= b.y && ly < b.y + b.h) {
        if (g_chon != i) { g_chon = i; sangTrangThai(ST_IDLE); }
        startSession(i); break;
      }
    }
    delay(150);
  }

  if (state == ST_WAIT_PAY) {
    if (now - lastPoll > 800) { lastPoll = now; if (checkPaid() > 0) sangTrangThai(ST_CAMON); }
    if (state == ST_WAIT_PAY && now > waitUntil) sangTrangThai(ST_IDLE);
    static uint32_t wd = 0; if (state == ST_WAIT_PAY && now - wd > 1000) { wd = now; veWaitPay(); fbFlush(); }
  }
  if (state == ST_CAMON && now > camonUntil) sangTrangThai(ST_RUNNING);
  if (state == ST_RUNNING) {
    // Trừ giờ CHỐT theo xung: chỉ trừ khi ghế thật sự chạy (hoặc không chốt theo pin).
    uint32_t dt = now - lastTickMs; lastTickMs = now;
    bool chay = gheDangChay();
    if (chay) {
      gheChetTu = 0; gheKhongChay = false;
      runRemainMs = (runRemainMs > dt) ? (runRemainMs - dt) : 0;
    } else {
      // Ghế không chạy: TẠM DỪNG đồng hồ; quá ngưỡng thì cờ báo (không trừ giờ oan cho khách).
      if (gheChetTu == 0) gheChetTu = now;
      if (!gheKhongChay && (now - gheChetTu > P4_GHECHAY_CHET_MS)) { gheKhongChay = true; nhip(); }
    }
    if (runRemainMs == 0) sangTrangThai(ST_IDLE);
    else if (now / 1000 != lastDrawSec) { lastDrawSec = now / 1000; veRunning(); fbFlush(); }
  }

#if CO_CONG_TIEN
  congTien.datChay(state == ST_RUNNING);   // ICT phân biệt kẹt-tiền vs đang-chạy
  congTien.tick();                          // relay tiền mặt + bắt tờ + dò kẹt
#endif
  quanMang(now);                            // WiFi/4G + đẩy hàng đợi tiền mặt

  uint32_t nhipEvery = (state == ST_WAIT_PAY) ? 3000 : 6000;
  if (now - lastNhip > nhipEvery) nhip();

  delay(15);
}
