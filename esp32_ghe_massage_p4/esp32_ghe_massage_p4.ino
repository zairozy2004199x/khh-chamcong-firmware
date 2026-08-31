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
static void lRoundRect(int lx, int ly, int w, int h, int r, uint16_t c) {
  if (r * 2 > w) r = w / 2; if (r * 2 > h) r = h / 2;
  for (int j = 0; j < h; j++) for (int i = 0; i < w; i++) {
    int dx = -1, dy = -1;
    if (i < r && j < r)              { dx = r - 1 - i; dy = r - 1 - j; }
    else if (i >= w - r && j < r)    { dx = i - (w - r); dy = r - 1 - j; }
    else if (i < r && j >= h - r)    { dx = r - 1 - i; dy = j - (h - r); }
    else if (i >= w - r && j >= h - r){ dx = i - (w - r); dy = j - (h - r); }
    if (dx >= 0 && dx * dx + dy * dy > r * r) continue;
    lpx(lx + i, ly + j, c);
  }
}

/* Font số 5×7 + glyph "đ" + ":" */
static const uint8_t FONT57[10][7] = {
  {0x0E,0x11,0x13,0x15,0x19,0x11,0x0E},{0x04,0x0C,0x04,0x04,0x04,0x04,0x0E},
  {0x0E,0x11,0x01,0x02,0x04,0x08,0x1F},{0x1F,0x02,0x04,0x02,0x01,0x11,0x0E},
  {0x02,0x06,0x0A,0x12,0x1F,0x02,0x02},{0x1F,0x10,0x1E,0x01,0x01,0x11,0x0E},
  {0x06,0x08,0x10,0x1E,0x11,0x11,0x0E},{0x1F,0x01,0x02,0x04,0x08,0x08,0x08},
  {0x0E,0x11,0x11,0x0E,0x11,0x11,0x0E},{0x0E,0x11,0x11,0x0F,0x01,0x02,0x0C},
};
static const uint8_t GLYPH_D[7] = {0x01,0x0F,0x0F,0x11,0x11,0x11,0x0F};
static void lBitmap(int lx, int ly, const uint8_t g[7], int sc, uint16_t c) {
  for (int r = 0; r < 7; r++) for (int b = 0; b < 5; b++)
    if (g[r] & (1 << (4 - b))) lRect(lx + b * sc, ly + r * sc, sc, sc, c);
}
static void lDigit(int lx, int ly, int d, int sc, uint16_t c) {
  if (d >= 0 && d <= 9) lBitmap(lx, ly, FONT57[d], sc, c);
}
static int lNum(int lx, int ly, long v, int sc, uint16_t c) {   // số trơn, căn trái
  char s[16]; int n = snprintf(s, sizeof s, "%ld", v); int x = lx;
  for (int i = 0; i < n; i++) { lDigit(x, ly, s[i] - '0', sc, c); x += 6 * sc; }
  return x - lx;
}
static int lPrice(int lx, int ly, long v, int sc, uint16_t c) { // giá: chấm nghìn + "đ"
  char s[16]; int n = snprintf(s, sizeof s, "%ld", v); int x = lx;
  for (int i = 0; i < n; i++) {
    lDigit(x, ly, s[i] - '0', sc, c); x += 6 * sc;
    int rem = n - 1 - i; if (rem > 0 && rem % 3 == 0) { lRect(x, ly + 6 * sc, sc, sc, c); x += 2 * sc; }
  }
  x += sc; lBitmap(x, ly, GLYPH_D, sc, c); x += 6 * sc; return x - lx;
}
static void lColon(int lx, int ly, int sc, uint16_t c) {   // dấu ":" cho MM:SS
  lRect(lx, ly + 2 * sc, sc, sc, c); lRect(lx, ly + 5 * sc, sc, sc, c);
}
static void lMMSS(int lx, int ly, int secs, int sc, uint16_t c) {
  if (secs < 0) secs = 0; int mm = secs / 60, ss = secs % 60; int x = lx;
  lDigit(x, ly, mm / 10, sc, c); x += 6 * sc; lDigit(x, ly, mm % 10, sc, c); x += 6 * sc;
  lColon(x, ly, sc, c); x += 3 * sc;
  lDigit(x, ly, ss / 10, sc, c); x += 6 * sc; lDigit(x, ly, ss % 10, sc, c);
}

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

static void veIdle() {
  lFill(C_BG); lRect(0, 0, LW, 6, C_BLUE);
  int n = PKG_N < 1 ? 1 : (PKG_N > PKG_MAX ? PKG_MAX : PKG_N);
  int top = 40, gap = 16, availH = LH - top - 24;
  int h = (availH - (n - 1) * gap) / n; if (h > 130) h = 130;
  for (int i = 0; i < n; i++) {
    int y = top + i * (h + gap);
    g_btn[i] = { 28, y, 340, h };
    bool sel = (i == g_chon);
    lRoundRect(30, y + 5, 340, h, 18, C_SHADOW);
    lRoundRect(28, y, 340, h, 18, sel ? C_AMBER : C_BORDER);
    lRoundRect(32, y + 4, 332, h - 8, 15, sel ? C_TINT : C_WHITE);
    int bs = h - 48; if (bs > 76) bs = 76;
    lRoundRect(50, y + (h - bs) / 2, bs, bs, 14, sel ? C_AMBER : C_BLUE);
    lDigit(50 + (bs - 40) / 2, y + (h - 56) / 2, i + 1, 8, C_WHITE);
    lPrice(150, y + (h - 35) / 2, PKG_AMT[i], 5, C_INK);
  }
  // Thẻ QR bên phải: trống ở IDLE, hiện khung để mời chạm.
  lRoundRect(403, 46, 372, 392, 22, C_SHADOW);
  lRoundRect(400, 40, 372, 392, 22, C_WHITE);
  lRoundRect(400, 40, 372, 8, 0, C_BLUE);
  // dấu QR mờ (khung) — chưa có mã tới khi chọn gói
  lRoundRect(470, 130, 232, 232, 16, C_BG);
}
static void veWaitPay() {
  lFill(C_BG); lRect(0, 0, LW, 6, C_AMBER);
  // Bên trái: gói đã chọn + số tiền + phút
  lRoundRect(30, 45, 340, 190, 18, C_SHADOW); lRoundRect(28, 40, 340, 190, 18, C_AMBER);
  lRoundRect(32, 44, 332, 182, 15, C_TINT);
  lRoundRect(60, 70, 80, 80, 16, C_AMBER); lDigit(80, 82, payIdx + 1, 9, C_WHITE);
  lPrice(60, 170, payAmount, 6, C_INK);
  // phút còn (cửa sổ chờ) — đồng hồ nhỏ
  int left = (waitUntil > millis()) ? (int)((waitUntil - millis()) / 1000) : 0;
  lMMSS(160, 90, left, 5, C_INK);
  // Thẻ QR VietQR THẬT
  lRoundRect(403, 46, 372, 392, 22, C_SHADOW); lRoundRect(400, 40, 372, 392, 22, C_WHITE);
  lRoundRect(400, 40, 372, 8, 0, C_AMBER);
  String memo = (ND_TIEN_TO.length() ? ND_TIEN_TO + " " : "") + "GHE" + (CHAIR_ID.length() ? CHAIR_ID : "01") + " " + String(payCode);
  String payload = buildVietQR(BANK_BIN, ACCOUNT_NO, payAmount, memo);
  lQR(430, 78, 300, payload.c_str());
}
static void veCamon() {   // đã nhận tiền — nền xanh + dấu tích
  lFill(C_GREEN);
  int cx = LW / 2 - 70, cy = LH / 2 - 60;
  // dấu tích to bằng 2 thanh
  for (int i = 0; i < 40; i++) lRect(cx + i, cy + 60 + i, 14, 14, C_WHITE);
  for (int i = 0; i < 90; i++) lRect(cx + 40 + i, cy + 100 - i, 14, 14, C_WHITE);
}
static void veRunning() {
  lFill(RGB565(0x14,0x1E,0x30));
  int left = (int)(runRemainMs / 1000);
  lMMSS(LW / 2 - 170, LH / 2 - 60, left, 16, gheKhongChay ? C_AMBER : C_WHITE);
  // thanh tiến độ
  long total = (long)payMinutes * 60; if (total < 1) total = 1;
  int barW = (int)((long)(LW - 120) * left / total);
  lRoundRect(60, LH - 70, LW - 120, 22, 10, RGB565(0x2A,0x3A,0x55));
  lRoundRect(60, LH - 70, barW, 22, 10, gheKhongChay ? C_AMBER : C_GREEN);
}
static void veNoAcc() {   // chưa có tài khoản nhận (chưa hỏi được server) — nền đỏ
  lFill(C_RED);
  lRoundRect(LW / 2 - 40, LH / 2 - 60, 80, 80, 8, C_WHITE);   // hình chữ nhật báo
  lRect(LW / 2 - 8, LH / 2 - 45, 16, 45, C_RED);
  lRect(LW / 2 - 8, LH / 2 + 6, 16, 16, C_RED);
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
