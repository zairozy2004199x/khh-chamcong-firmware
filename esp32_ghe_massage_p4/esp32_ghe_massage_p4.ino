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
#include <Update.h>       // nạp firmware qua AP nội bộ (máy trạm) — Update tự huỷ nếu ảnh lỗi
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
#include "font_ascii.h"  // font 5×7 chữ HOA + số + dấu (giao diện kiểu bản CYD)
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
/* OTA-AP: mật khẩu AP "POSH_QR-<mã>" + khoá X-OTA-Key (máy trạm gửi). Mặc định 12345678
   (khớp SEC_GHE_AP_PASS/SEC_GHE_OTA_KEY của máy trạm). Đổi ở secrets.h nếu muốn. */
#ifndef SEC_AP_PASS
  #define SEC_AP_PASS   "12345678"
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

/* ─── FONT khử răng cưa (nội suy 5×7 rồi tăng tương phản cho nét sắc) ──────── */
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
/* CHỮ (chuỗi ASCII) — mỗi ký tự rộng 6*sc (5 glyph + 1 cách). */
#define CHAR_W(sc) (6 * (sc))
static int lTextW(const char* s, int sc) { return (int)strlen(s) * CHAR_W(sc); }
static void lText(int lx, int ly, const char* s, int sc, uint16_t c, uint16_t bg) {
  int x = lx;
  for (const char* p = s; *p; p++) { lBitmap(x, ly, glyph7(*p), sc, c, bg); x += CHAR_W(sc); }
}
static void lTextC(int cx, int ly, const char* s, int sc, uint16_t c, uint16_t bg) {  // căn giữa ngang
  lText(cx - lTextW(s, sc) / 2, ly, s, sc, c, bg);
}
static void lTextR(int rx, int ly, const char* s, int sc, uint16_t c, uint16_t bg) {  // căn phải
  lText(rx - lTextW(s, sc), ly, s, sc, c, bg);
}
static void lDigit(int lx, int ly, int d, int sc, uint16_t c, uint16_t bg) {
  if (d >= 0 && d <= 9) lBitmap(lx, ly, FONT_DIGIT[d], sc, c, bg);
}
static int lNum(int lx, int ly, long v, int sc, uint16_t c, uint16_t bg) {
  char s[16]; snprintf(s, sizeof s, "%ld", v); lText(lx, ly, s, sc, c, bg); return lTextW(s, sc);
}
/* Chuỗi tiền "50.000" (chấm ngăn nghìn), KHÔNG kèm "đ". */
static void _tienStr(long v, char* out, size_t cap) {
  char s[16]; int n = snprintf(s, sizeof s, "%ld", v); int o = 0;
  for (int i = 0; i < n && o < (int)cap - 2; i++) { out[o++] = s[i]; int rem = n - 1 - i; if (rem > 0 && rem % 3 == 0) out[o++] = '.'; }
  out[o] = 0;
}
static int lMoneyW(long v, int sc) { char t[24]; _tienStr(v, t, sizeof t); return lTextW(t, sc) + sc + CHAR_W(sc); }
static int lMoney(int lx, int ly, long v, int sc, uint16_t c, uint16_t bg) {  // "50.000đ"
  char t[24]; _tienStr(v, t, sizeof t);
  lText(lx, ly, t, sc, c, bg);
  int x = lx + lTextW(t, sc) + sc;
  lBitmap(x, ly, GLYPH_DD, sc, c, bg);
  return x + CHAR_W(sc) - lx;
}
static void lMoneyC(int cx, int ly, long v, int sc, uint16_t c, uint16_t bg) { lMoney(cx - lMoneyW(v, sc) / 2, ly, v, sc, c, bg); }
static void lMMSS(int lx, int ly, int secs, int sc, uint16_t c, uint16_t bg) {
  if (secs < 0) secs = 0; char b[8]; snprintf(b, sizeof b, "%02d:%02d", secs / 60, secs % 60);
  lText(lx, ly, b, sc, c, bg);
}
static int lMMSSW(int sc) { return 5 * CHAR_W(sc); }   // "MM:SS"

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
static long   PKG_AMT[PKG_MAX]  = {50000, 100000, 150000, 200000};
static int    PKG_PHUT[PKG_MAX] = {0, 0, 0, 0};
static String PKG_TEN[PKG_MAX]  = {"GOI CO BAN", "GOI PHO BIEN", "GOI CHUYEN SAU", "GOI THUONG HANG"};
static String PKG_MOTA[PKG_MAX] = {"", "", "", ""};
static int    PKG_VIP[PKG_MAX]  = {0, 0, 0, 1};
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
      char ka[8], kp[8], kn[8], km[8], kv[8];
      snprintf(ka, sizeof ka, "a%d", i); snprintf(kp, sizeof kp, "p%d", i);
      snprintf(kn, sizeof kn, "n%d", i); snprintf(km, sizeof km, "m%d", i); snprintf(kv, sizeof kv, "v%d", i);
      PKG_AMT[i]  = cfgNvs.getLong(ka, PKG_AMT[i]);
      PKG_PHUT[i] = cfgNvs.getInt (kp, PKG_PHUT[i]);
      PKG_TEN[i]  = cfgNvs.getString(kn, PKG_TEN[i]);
      PKG_MOTA[i] = cfgNvs.getString(km, PKG_MOTA[i]);
      PKG_VIP[i]  = cfgNvs.getInt (kv, PKG_VIP[i]);
    }
  }
  cfgNvs.end();
}
static void luuCauHinh() {   // gọi sau mỗi nhịp; TỰ bỏ qua nếu không đổi (khỏi mòn flash)
  static String sig = "\x01";
  String cur = CHAIR_ID + "|" + ACCOUNT_NO + "|" + BANK_BIN + "|" + ND_TIEN_TO + "|"
             + String(PRICE_VND) + "|" + String(MINUTES) + "|" + String(PKG_N);
  for (int i = 0; i < PKG_N; i++)
    cur += "|" + String(PKG_AMT[i]) + "," + String(PKG_PHUT[i]) + "," + PKG_TEN[i] + "," + PKG_MOTA[i] + "," + String(PKG_VIP[i]);
  if (cur == sig) return;
  sig = cur;
  cfgNvs.begin("cfg", false);
  cfgNvs.putString("ma", CHAIR_ID); cfgNvs.putString("tk", ACCOUNT_NO);
  cfgNvs.putString("bin", BANK_BIN); cfgNvs.putString("tienTo", ND_TIEN_TO);
  cfgNvs.putLong("gia", PRICE_VND); cfgNvs.putInt("phut", MINUTES); cfgNvs.putInt("pkgN", PKG_N);
  for (int i = 0; i < PKG_N; i++) {
    char ka[8], kp[8], kn[8], km[8], kv[8];
    snprintf(ka, sizeof ka, "a%d", i); snprintf(kp, sizeof kp, "p%d", i);
    snprintf(kn, sizeof kn, "n%d", i); snprintf(km, sizeof km, "m%d", i); snprintf(kv, sizeof kv, "v%d", i);
    cfgNvs.putLong(ka, PKG_AMT[i]); cfgNvs.putInt(kp, PKG_PHUT[i]);
    cfgNvs.putString(kn, PKG_TEN[i]); cfgNvs.putString(km, PKG_MOTA[i]); cfgNvs.putInt(kv, PKG_VIP[i]);
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
      if (n >= PKG_MAX) break; long a; int ph = 0, vp = 0; String nm = "", mt = "";
      if (v.is<JsonObjectConst>()) {
        a = (long)(v["t"] | 0); ph = (int)(v["p"] | 0);
        nm = String((const char*)(v["n"] | "")); mt = String((const char*)(v["m"] | "")); vp = (int)(v["v"] | 0);
      } else a = v.as<long>();
      if (a >= 1000) { PKG_AMT[n] = a; PKG_PHUT[n] = ph; PKG_TEN[n] = nm; PKG_MOTA[n] = mt; PKG_VIP[n] = vp; n++; }
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

/* ─── GIAO DIỆN kiểu bản CYD (tông teal/xanh, thẻ 2×2, có chữ) ────────────── */
#define C_BG    RGB565(0x0C,0x22,0x3A)  // nền xanh biển tối
#define C_BAR   RGB565(0x15,0x42,0x57)  // dải tiêu đề / chân
#define C_TOP   RGB565(0x2C,0x7E,0x8E)  // đỉnh thẻ (teal sáng)
#define C_BOT   RGB565(0x16,0x4A,0x5E)  // đáy thẻ (teal tối)
#define C_SHD   RGB565(0x05,0x12,0x20)  // bóng đổ
#define C_GLOW  RGB565(0x6C,0xE6,0xF2)  // viền cyan sáng
#define C_GLOW2 RGB565(0x27,0x6E,0x80)  // viền ngoài mờ
#define C_WHITE 0xFFFF
#define C_PHU   RGB565(0xAE,0xD8,0xE8)  // chữ phụ (phút / mô tả)
#define C_ID    RGB565(0x5A,0xD8,0x88)  // mã ghế (xanh lá)
#define C_YEL   RGB565(0xF4,0xC8,0x54)  // vàng nhấn
#define C_VIP   RGB565(0xF6,0xD6,0x40)  // nền huy hiệu VVIP
#define C_INK   RGB565(0x0A,0x20,0x30)  // chữ tối trên nền sáng
#define C_RED   RGB565(0xE0,0x4B,0x4B)
#define C_GREEN RGB565(0x2E,0xB0,0x66)

static int g_chon = 0;
struct Btn { int x, y, w, h; };
static Btn g_btn[PKG_MAX];

/* Thân thẻ CHUYỂN MÀU đỉnh→đáy (bo góc). */
static void lGradFill(int x, int y, int w, int h, int r, uint16_t top, uint16_t bot) {
  for (int j = 0; j < h; j++) {
    int ins = 0;
    if (j < r)          { int d = r - 1 - j; ins = r - (int)(sqrtf((float)(r * r - d * d)) + 0.5f); }
    else if (j >= h - r){ int d = j - (h - r); ins = r - (int)(sqrtf((float)(r * r - d * d)) + 0.5f); }
    uint8_t t = (uint8_t)((long)j * 255 / (h - 1));
    lRect(x + ins, y + j, w - 2 * ins, 1, blend565(top, bot, 255 - t));
  }
}
/* Dải tiêu đề trên + chân dưới, kèm đường viền glow. */
static void veBar(int y, int h) { lRect(0, y, LW, h, C_BAR); }

/* Màn báo (chưa gán mã / tạm ngưng…) — nền tối, chữ căn giữa. */
static void veThongBao(uint16_t nhan, const char* d1, const char* d2, const char* d3, const char* d4) {
  lFill(C_BG);
  lTextC(LW / 2, 70, d1, 4, nhan, C_BG);
  if (d2[0]) lTextC(LW / 2, 150, d2, 2, C_WHITE, C_BG);
  if (d3[0]) lTextC(LW / 2, 190, d3, 2, C_WHITE, C_BG);
  if (d4[0]) lTextC(LW / 2, 250, d4, 2, C_ID, C_BG);
  bool up = netUp();
  lTextC(LW / 2, 320, up ? "DANG CO MANG - CHO CAU HINH" : "MAT MANG", 2, up ? C_GREEN : C_RED, C_BG);
}

static void veTheGoi(int i, int x, int y, int w, int h) {
  g_btn[i] = { x, y, w, h };
  bool vip = (PKG_VIP[i] != 0);
  int cx = x + w / 2;
  lRoundRectA(x + 3, y + 6, w, h, 12, C_SHD, C_BG);       // bóng đổ
  lRoundRectA(x, y, w, h, 12, C_GLOW2, C_BG);             // viền ngoài mờ
  lRoundRectA(x + 1, y + 1, w - 2, h - 2, 11, C_GLOW, C_BG); // viền glow
  lGradFill(x + 4, y + 4, w - 8, h - 8, 9, C_TOP, C_BOT); // thân chuyển màu
  // Tên gói (trên)
  String ten = PKG_TEN[i].length() ? PKG_TEN[i] : (String("GOI ") + String(i + 1));
  lTextC(cx, y + 16, ten.c_str(), 2, C_WHITE, C_BOT);
  // "N PHUT"
  String ph = String(phutGoi(i)) + " PHUT";
  lTextC(cx, y + 44, ph.c_str(), 2, C_PHU, C_BOT);
  // GIÁ to (có "đ"), căn giữa
  lMoneyC(cx, y + 78, PKG_AMT[i], 5, C_WHITE, C_BOT);
  // Mô tả (dưới)
  if (PKG_MOTA[i].length()) lTextC(cx, y + h - 26, PKG_MOTA[i].c_str(), 1, C_PHU, C_BOT);
  // Huy hiệu VVIP góc phải trên
  if (vip) {
    lRoundRectA(x + w - 66, y - 6, 60, 26, 7, C_VIP, C_BG);
    lTextC(x + w - 36, y + 1, "VVIP", 2, C_INK, C_VIP);
  }
}

static void veIdle() {
  // Chưa gán mã / chưa có tài khoản nhận → nói thẳng cho nhân viên.
  if (CHUA_GAN || CHAIR_ID.length() == 0) {
    String mac = macBo();
    veThongBao(C_YEL, "GHE CHUA DUOC GAN MA", "VAO WEB: GHE MASSAGE > MAY & CO SO",
               "GAN MA CHO MAC NAY:", mac.c_str());
    return;
  }
  lFill(C_BG);
  // Dải tiêu đề: mã ghế (phải) + tên hệ thống (giữa phần còn lại)
  veBar(0, 48); lRect(0, 48, LW, 2, C_GLOW2);
  String idr = netUp() ? CHAIR_ID : (CHAIR_ID + " - MAT MANG");
  lTextR(LW - 12, 15, idr.c_str(), 2, netUp() ? C_ID : C_YEL, C_BAR);
  int mep = LW - 12 - lTextW(idr.c_str(), 2) - 16;
  const char* td = "MASSAGE GHE CAO CAP";
  if (lTextW(td, 2) <= mep - 12) lText((12 + mep) / 2 - lTextW(td, 2) / 2, 15, td, 2, C_WHITE, C_BAR);
  // Lưới thẻ 2×2
  int n = PKG_N < 1 ? 1 : (PKG_N > PKG_MAX ? PKG_MAX : PKG_N);
  int mx = 20, gapx = 20, gapy = 20, gy = 62, gh = 168;
  int cw = (LW - 2 * mx - gapx) / 2;
  for (int i = 0; i < n; i++) {
    int col = i % 2, row = i / 2;
    int x = mx + col * (cw + gapx), y = gy + row * (gh + gapy);
    veTheGoi(i, x, y, cw, gh);
  }
  // Dải chân: câu mời quét
  veBar(LH - 42, 42); lRect(0, LH - 42, LW, 2, C_GLOW2);
  lTextC(LW / 2, LH - 30, "CHON GOI  >  QUET QR DE THANH TOAN", 2, C_GLOW, C_BAR);
}

static void veWaitPay() {
  lFill(C_BG);
  // Dải tiêu đề 2 dòng: hướng dẫn + giá & phút
  veBar(0, 66); lRect(0, 66, LW, 2, C_GLOW2);
  lTextC(LW / 2, 10, "QUET MA QR DE THANH TOAN & BAT DAU", 2, C_WHITE, C_BAR);
  { String g = String(); char t[24]; _tienStr(payAmount, t, sizeof t);
    g = String(t) + "D   -   " + String(payMinutes) + " PHUT";
    lTextC(LW / 2, 40, g.c_str(), 2, C_YEL, C_BAR); }
  // Ô QR to, canh giữa
  int box = 336, bx = (LW - box) / 2, by = 84;
  lRoundRectA(bx + 3, by + 5, box, box, 12, C_SHD, C_BG);
  lRoundRectA(bx, by, box, box, 12, C_WHITE, C_BG);
  String memo = (ND_TIEN_TO.length() ? ND_TIEN_TO + " " : "") + "GHE" + (CHAIR_ID.length() ? CHAIR_ID : "01") + " " + String(payCode);
  String payload = buildVietQR(BANK_BIN, ACCOUNT_NO, payAmount, memo);
  int oPx = box - 24, mod = oPx / 61; if (mod < 1) mod = 1;   // v11 = 61 module
  int qside = mod * 61 + 2 * mod;
  lQR(bx + (box - qside) / 2, by + (box - qside) / 2, oPx, payload.c_str());
  // Nội dung chuyển khoản (để app không tự điền thì khách gõ tay)
  String nd = String("NOI DUNG: ") + memo; nd.toUpperCase();
  lTextC(LW / 2, by + box + 14, nd.c_str(), 2, C_YEL, C_BG);
  // Cửa sổ chờ + nút huỷ
  int left = (waitUntil > millis()) ? (int)((waitUntil - millis()) / 1000) : 0;
  String cho = String("CHO TRA: ") + String(left) + "S";
  lText(20, LH - 26, cho.c_str(), 2, C_RED, C_BG);
  lRoundRectA(LW - 150, LH - 34, 132, 30, 7, C_BAR, C_BG);
  lTextC(LW - 84, LH - 27, "CHAM DE HUY", 2, C_PHU, C_BAR);
}

static void veCamon() {   // đã nhận tiền — nền xanh + dấu tích + chữ
  lFill(C_GREEN);
  int cx = LW / 2, cy = LH / 2 - 40;
  lCircleA(cx, cy, 84, C_WHITE, C_GREEN);
  lStroke(cx - 36, cy + 4, cx - 10, cy + 34, 8, C_GREEN, C_WHITE);
  lStroke(cx - 10, cy + 34, cx + 44, cy - 34, 8, C_GREEN, C_WHITE);
  lTextC(cx, cy + 120, "DA NHAN TIEN - GHE SAP CHAY", 3, C_WHITE, C_GREEN);
}

static void veRunning() {
  uint16_t bg = C_BG;
  lFill(bg);
  veBar(0, 56); lRect(0, 56, LW, 2, C_GLOW2);
  lTextC(LW / 2, 8, "HE THONG GHE MASSAGE CAO CAP", 2, C_PHU, C_BAR);
  lTextC(LW / 2, 32, "PHIEN TRI LIEU DANG DIEN RA", 2, C_YEL, C_BAR);
  // Ô đếm ngược
  int boxX = 60, boxY = 78, boxW = LW - 120, boxH = 250;
  lRoundRectA(boxX, boxY, boxW, boxH, 16, C_BAR, bg);
  lTextC(LW / 2, boxY + 16, "SO PHUT CON LAI", 2, C_PHU, C_BAR);
  int left = (int)(runRemainMs / 1000);
  uint16_t c = gheKhongChay ? C_YEL : C_WHITE;
  int sc = 20, w = lMMSSW(sc);
  lMMSS((LW - w) / 2, boxY + 56, left, sc, c, C_BAR);
  // Tổng + tên gói
  String duoi = String("TONG: ") + String(payMinutes) + " PHUT";
  if (payIdx >= 0 && payIdx < PKG_N && PKG_TEN[payIdx].length()) duoi += "  -  " + PKG_TEN[payIdx];
  lTextC(LW / 2, boxY + boxH - 30, duoi.c_str(), 2, C_YEL, C_BAR);
  // Dải trạng thái
  int sX = 60, sY = LH - 82, sW = LW - 120, sH = 52;
  if (gheKhongChay) {
    lRoundRectA(sX, sY, sW, sH, 12, C_RED, bg);
    lTextC(LW / 2, sY + 8, "GHE DUNG DOT NGOT", 2, C_WHITE, C_RED);
    lTextC(LW / 2, sY + 30, "DONG HO TAM DUNG - KIEM TRA GHE", 2, C_WHITE, C_RED);
  } else {
    lRoundRectA(sX, sY, sW, sH, 12, C_BAR, bg);
    lCircleA(sX + 30, sY + sH / 2, 10, C_GREEN, C_BAR);
    lText(sX + 52, sY + 8, "PHIEN MASSAGE DANG CHAY", 2, C_YEL, C_BAR);
    lText(sX + 52, sY + 30, "XIN THU GIAN VA TAN HUONG DICH VU", 1, C_PHU, C_BAR);
  }
}

static void veNoAcc() {   // chưa có tài khoản nhận
  veThongBao(C_YEL, "TAM NGUNG NHAN QR",
             "QUY KHACH VUI LONG TRA TIEN MAT",
             "HOAC BAO NHAN VIEN. XIN LOI QUY KHACH.",
             CHAIR_ID.length() ? CHAIR_ID.c_str() : "");
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

/* ─── OTA-AP: máy trạm nạp firmware qua WiFi (giao thức POSH_QR-* + /update) ──
 * Ghế bật AP "POSH_QR-<mã>" (mật khẩu SEC_AP_PASS). Máy trạm nối vào, POST .bin THÔ
 * lên 192.168.4.1/update kèm header X-OTA-Key = SEC_AP_PASS. Update.h tự huỷ nếu ảnh
 * lỗi → nạp dở thì GIỮ firmware cũ. Dùng WIFI_AP_STA: vừa nối server (STA) vừa mở AP.
 * Bê nguyên giao thức bản CYD (esp32_ghe_massage) để cùng một máy trạm nạp được cả hai. */
static WiFiServer otaTcp(80);
static bool g_otaMoAP = false;

static void otaManNap(const char* msg) {   // báo trên màn P4 lúc đang nạp
  lFill(RGB565(0x14,0x1E,0x30));
  lTextC(LW / 2, LH / 2 - 20, msg, 4, C_YEL, RGB565(0x14,0x1E,0x30));
  lTextC(LW / 2, LH / 2 + 40, "KHONG TAT NGUON", 2, C_DO, RGB565(0x14,0x1E,0x30));
  fbFlush();
}

/* Gọi mỗi vòng loop(): nhận 1 client. GET / → mô tả; POST /update (X-OTA-Key khớp)
   → đọc Content-Length byte .bin THÔ → Update. Máy trạm ghế gửi raw body (không multipart). */
static void otaPhucVu() {
  WiFiClient cl = otaTcp.available();
  if (!cl) return;
  cl.setTimeout(8000);
  String req = cl.readStringUntil('\n');
  bool isPost = req.startsWith("POST");
  long len = 0; bool keyOk = false;
  for (;;) {
    String h = cl.readStringUntil('\n');
    h.trim(); if (h.length() == 0) break;
    int c = h.indexOf(':'); if (c <= 0) continue;
    String k = h.substring(0, c); k.toLowerCase();
    String v = h.substring(c + 1); v.trim();
    if (k == "content-length") len = v.toInt();
    else if (k == "x-ota-key" && v == String(SEC_AP_PASS)) keyOk = true;
  }
  if (!isPost) {
    cl.print(F("HTTP/1.1 200 OK\r\nContent-Type:text/plain\r\nConnection:close\r\n\r\n"
               "POSH QR OTA P4. POST .bin toi /update, kem X-OTA-Key = mat khau AP."));
    cl.stop(); return;
  }
  if (!keyOk || len <= 0) {
    cl.print(F("HTTP/1.1 401 Unauthorized\r\nConnection:close\r\n\r\nSai khoa hoac thieu Content-Length"));
    cl.stop(); Serial.println("[OTA] tu choi: sai khoa / thieu len"); return;
  }
  Serial.printf("[OTA] bat dau nap %ld byte\n", len);
  otaManNap("DANG NAP FIRMWARE...");
  if (!Update.begin(len)) {
    Update.printError(Serial);
    cl.print(F("HTTP/1.1 500\r\nConnection:close\r\n\r\nUpdate.begin loi")); cl.stop(); return;
  }
  long got = 0; uint8_t buf[1024]; uint32_t t0 = millis();
  while (got < len && cl.connected() && millis() - t0 < 60000) {
    int n = cl.read(buf, sizeof buf);
    if (n > 0) { if (Update.write(buf, n) != (size_t)n) { Update.printError(Serial); break; } got += n; t0 = millis(); }
    else delay(1);
  }
  bool ok = (got == len) && Update.end(true);
  cl.print(ok ? F("HTTP/1.1 200 OK\r\nConnection:close\r\n\r\nOK - dang khoi dong lai")
              : F("HTTP/1.1 500\r\nConnection:close\r\n\r\nFAIL - giu firmware cu"));
  cl.stop();
  Serial.printf("[OTA] nhan %ld/%ld byte -> %s\n", got, len, ok ? "OK" : "FAIL");
  delay(600);
  if (ok) ESP.restart();
}

static void startOtaAP() {
  String mac = macBo(); mac.replace(":", "");
  String ap = String("POSH_QR-") + (CHAIR_ID.length() ? CHAIR_ID : mac);
  WiFi.softAP(ap.c_str(), SEC_AP_PASS);
  if (!g_otaMoAP) { otaTcp.begin(); g_otaMoAP = true; }
  Serial.printf("[OTA] AP \"%s\" @192.168.4.1 (may tram nap .bin)\n", ap.c_str());
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
  WiFi.mode(WIFI_AP_STA); WiFi.setTxPower(WIFI_POWER_8_5dBm);   // STA nối server + AP cho máy trạm nạp
  WiFi.begin(SEC_WIFI_SSID, SEC_WIFI_PASS);
  startOtaAP();           // mở AP "POSH_QR-<mã>" để máy trạm P4 nạp firmware
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
        g_chon = i; startSession(i); break;   // chạm gói → mở màn QR ngay
      }
    }
    delay(150);
  }

  if (state == ST_WAIT_PAY) {
    if (tch && lx >= LW - 150 && ly >= LH - 40) { sangTrangThai(ST_IDLE); delay(150); }  // nút HUỶ
    if (state == ST_WAIT_PAY && now - lastPoll > 800) { lastPoll = now; if (checkPaid() > 0) sangTrangThai(ST_CAMON); }
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
  otaPhucVu();                              // máy trạm nạp firmware qua AP POSH_QR-*

  uint32_t nhipEvery = (state == ST_WAIT_PAY) ? 3000 : 6000;
  if (now - lastNhip > nhipEvery) nhip();

  delay(15);
}
