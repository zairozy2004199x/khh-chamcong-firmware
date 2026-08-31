/* ============================================================================
 *  MÁY THU TIỀN / KỸ THUẬT — bo GUITION JC4880P443C (ESP32-P4 + C6)
 *  ----------------------------------------------------------------------------
 *  MỘT màn 4.3" cảm ứng, BA việc (menu chính):
 *    [1] KIỂM TRA CHỈ SỐ MÁY  (CHỈ XEM) — dò AP ghế lấy mã → 4G hỏi web
 *        (login bằng PIN, api=chot_xem) → hiện cơ sở / tiền mặt hệ thống / chỉ số
 *        lần trước / đơn giá. KHÔNG ghi gì.
 *    [2] NẠP FIRMWARE GHẾ      — nối AP "POSH_QR-<mã>" → POST .bin THÔ /update
 *        kèm X-OTA-Key (đúng otaPhucVu của firmware ghế).
 *    [3] NẠP FW MÁY CHẤM CÔNG  — nối AP "ChamCong-<cơ sở>" → POST multipart
 *        /update kèm Basic-Auth (đúng trang /update máy chấm công).
 *
 *  Nền tảng màn/cảm ứng/khử răng cưa/font BÊ NGUYÊN từ esp32_ghe_massage_p4 (đã
 *  chạy trên bo thật). WiFi (C6) chỉ để nối AP máy đích; Internet đi 4G A7680C.
 *  Thẻ microSD chứa 2 file: /ghe.bin + /chamcong.bin (1 thẻ nạp được cả hai).
 *  KHÔNG để khoá trong mã — xem secrets.h.
 *
 *  THƯ VIỆN: ArduinoJson (Benoît Blanchon). Board "ESP32P4 Dev Module", PSRAM
 *  Enabled, Flash 16MB, USB CDC On Boot Enabled. KHÔNG compile được ở máy Claude.
 * ========================================================================== */
#include <Arduino.h>
#include <Wire.h>
#include <WiFi.h>
#include <SD_MMC.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include "mbedtls/base64.h"
#include "esp_lcd_mipi_dsi.h"
#include "esp_lcd_panel_io.h"
#include "esp_lcd_panel_ops.h"
#include "driver/gpio.h"
#include "esp_ldo_regulator.h"
#include "esp_cache.h"
#include "esp_mac.h"

#include "cau_hinh_tram_p4.h"
#include "panel_jc4880p443.h"
#include "font_ascii.h"
#include "net_4g.h"

/* ─── FRAMEBUFFER + màn (bê từ bản ghế P4) ───────────────────────────────── */
static esp_lcd_panel_io_handle_t g_io = nullptr;
static esp_lcd_panel_handle_t    g_panel = nullptr;
static uint16_t*                 g_fb = nullptr;
static uint8_t                   g_gt = 0;

/* Kiểu dùng chung — KHAI TRƯỚC mọi hàm. Arduino tự sinh prototype ở đầu file; nếu struct
   định nghĩa sau hàm dùng nó thì prototype báo "does not name a type". */
struct Nut { int x, y, w, h; };
struct Ap  { String ssid, nhan, bssid; int rssi; };

static inline uint16_t RGB565(uint8_t r, uint8_t g, uint8_t b) {
  return (uint16_t)(((r & 0xF8) << 8) | ((g & 0xFC) << 3) | (b >> 3));
}
static void fbFlush() {
  if (g_fb) esp_cache_msync(g_fb, (size_t)PANEL_W * PANEL_H * 2, ESP_CACHE_MSYNC_FLAG_DIR_C2M);
}
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
static inline uint16_t blend565(uint16_t fg, uint16_t bg, uint8_t a) {
  uint16_t fr = (fg >> 11) & 0x1F, fgc = (fg >> 5) & 0x3F, fb = fg & 0x1F;
  uint16_t br = (bg >> 11) & 0x1F, bgc = (bg >> 5) & 0x3F, bb = bg & 0x1F;
  uint16_t r = (fr * a + br * (255 - a) + 127) / 255;
  uint16_t g = (fgc * a + bgc * (255 - a) + 127) / 255;
  uint16_t b = (fb * a + bb * (255 - a) + 127) / 255;
  return (uint16_t)((r << 11) | (g << 5) | b);
}
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
static void lCircleA(int cx, int cy, int r, uint16_t c, uint16_t bg) {
  for (int j = -r - 1; j <= r + 1; j++) for (int i = -r - 1; i <= r + 1; i++) {
    float cov = (float)r - sqrtf((float)(i * i + j * j)) + 0.5f;
    if (cov <= 0) continue; if (cov > 1) cov = 1;
    uint8_t a = (uint8_t)(cov * 255);
    lpx(cx + i, cy + j, a >= 255 ? c : blend565(c, bg, a));
  }
}
/* Font (bê từ bản ghế P4) */
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
    cov = (cov - 0.5f) * 1.7f + 0.5f;
    if (cov <= 0.02f) continue; if (cov > 1) cov = 1;
    uint8_t a = (uint8_t)(cov * 255);
    lpx(lx + i, ly + j, a >= 250 ? c : blend565(c, bg, a));
  }
}
#define CHAR_W(sc) (6 * (sc))
static int lTextW(const char* s, int sc) { return (int)strlen(s) * CHAR_W(sc); }
static void lText(int lx, int ly, const char* s, int sc, uint16_t c, uint16_t bg) {
  int x = lx; for (const char* p = s; *p; p++) { lBitmap(x, ly, glyph7(*p), sc, c, bg); x += CHAR_W(sc); }
}
static void lTextC(int cx, int ly, const char* s, int sc, uint16_t c, uint16_t bg) { lText(cx - lTextW(s, sc) / 2, ly, s, sc, c, bg); }
static void lTextR(int rx, int ly, const char* s, int sc, uint16_t c, uint16_t bg) { lText(rx - lTextW(s, sc), ly, s, sc, c, bg); }

/* ─── PHẦN CỨNG: init màn + GT911 (bê nguyên) ────────────────────────────── */
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
/* Chờ MỘT lần chạm-nhả, trả toạ độ điểm nhả. toMs=0 → chờ vô hạn. */
static bool doiCham(int* x, int* y, uint32_t toMs) {
  uint32_t t0 = millis(); int tx, ty;
  for (;;) {
    if (touchLandscape(&tx, &ty)) {
      int rx = tx, ry = ty; uint32_t s = millis();
      while (touchLandscape(&tx, &ty)) { rx = tx; ry = ty; delay(10); if (millis() - s > 2000) break; }
      *x = rx; *y = ry; return true;
    }
    if (toMs && millis() - t0 > toMs) return false;
    delay(10);
  }
}

/* ─── BẢNG MÀU (tông teal như bản ghế) ───────────────────────────────────── */
#define C_BG    RGB565(0x0C,0x22,0x3A)
#define C_BAR   RGB565(0x15,0x42,0x57)
#define C_BTN   RGB565(0x1E,0x6E,0x86)
#define C_BTN2  RGB565(0x2C,0x7E,0x8E)
#define C_SHD   RGB565(0x05,0x12,0x20)
#define C_GLOW  RGB565(0x6C,0xE6,0xF2)
#define C_GLOW2 RGB565(0x27,0x6E,0x80)
#define C_WHITE 0xFFFF
#define C_PHU   RGB565(0xAE,0xD8,0xE8)
#define C_YEL   RGB565(0xF4,0xC8,0x54)
#define C_OK    RGB565(0x2E,0xB0,0x66)
#define C_DO    RGB565(0xE0,0x4B,0x4B)
#define C_INK   RGB565(0x0A,0x20,0x30)

static Preferences prefsTram;
static bool   g_sdOk = false;
static String g_token = "";      // token web (chỉ RAM)
static String g_pinLuu = "";     // PIN đã lưu (NVS) — tự đăng nhập lại
static bool   g_4gUp = false;

/* Nút bấm hình chữ nhật bo góc + chữ căn giữa. (struct Nut khai ở đầu file) */
static void veNut(const Nut& b, const char* nhan, uint16_t nen, uint16_t chu, int sc) {
  lRoundRectA(b.x + 3, b.y + 5, b.w, b.h, 14, C_SHD, C_BG);
  lRoundRectA(b.x, b.y, b.w, b.h, 14, C_GLOW2, C_BG);
  lRoundRectA(b.x + 2, b.y + 2, b.w - 4, b.h - 4, 12, nen, C_GLOW2);
  lTextC(b.x + b.w / 2, b.y + (b.h - 7 * sc) / 2, nhan, sc, chu, nen);
}
static bool trong(const Nut& b, int x, int y) { return x >= b.x && x < b.x + b.w && y >= b.y && y < b.y + b.h; }

static void thanhTieuDe(const char* t) {
  lRect(0, 0, LW, 46, C_BAR); lRect(0, 46, LW, 2, C_GLOW2);
  lTextC(LW / 2, 13, t, 3, C_WHITE, C_BAR);
}
/* Màn thông báo 1–3 dòng + tự chờ n ms (0 = không chờ). */
static void bao(const char* d1, uint16_t c1, const char* d2, const char* d3, uint32_t giu = 0) {
  lFill(C_BG);
  lTextC(LW / 2, 150, d1, 4, c1, C_BG);
  if (d2 && d2[0]) lTextC(LW / 2, 230, d2, 2, C_WHITE, C_BG);
  if (d3 && d3[0]) lTextC(LW / 2, 270, d3, 2, C_PHU, C_BG);
  fbFlush();
  if (giu) delay(giu);
}

/* ─── THANH TIẾN ĐỘ khi nạp firmware ─────────────────────────────────────── */
static void ttMo(const char* tieu, const char* phu) {
  lFill(C_BG);
  thanhTieuDe(tieu);
  lTextC(LW / 2, 150, phu, 2, C_YEL, C_BG);
  lTextC(LW / 2, 200, "KHONG TAT NGUON", 2, C_DO, C_BG);
  lRoundRectA(90, 260, LW - 180, 44, 12, C_BAR, C_BG);
  fbFlush();
}
static int _ttPct = -1;
static void ttPct(int pct) {
  if (pct == _ttPct) return; _ttPct = pct;
  int x = 96, y = 266, w = LW - 192, h = 32;
  int fw = (int)((long)w * pct / 100); if (fw < 0) fw = 0; if (fw > w) fw = w;
  lRoundRectA(x, y, w, h, 9, C_BAR, C_BG);
  if (fw >= 2) lRoundRectA(x, y, fw, h, 9, C_OK, C_BG);
  char s[8]; snprintf(s, sizeof s, "%d%%", pct);
  lTextC(LW / 2, 330, s, 3, C_WHITE, C_BG);
  fbFlush();
}

/* ─── THẺ SD ─────────────────────────────────────────────────────────────── */
static bool sdBatDau() {
  SD_MMC.setPins(SD_CLK_PIN, SD_CMD_PIN, SD_D0_PIN);
  g_sdOk = SD_MMC.begin("/sdcard", true /*1-bit*/, false);
  Serial.printf("[SD] %s\n", g_sdOk ? "OK" : "khong thay the");
  return g_sdOk;
}
static long fwSize(const char* path) {
  if (!g_sdOk) return -1;
  File f = SD_MMC.open(path, FILE_READ); if (!f) return -1;
  long s = f.size(); f.close(); return s;
}

/* ─── DÒ AP máy đích (WiFi C6) ───────────────────────────────────────────── */
static Ap  g_ap[24]; static int g_apN = 0;   // struct Ap khai ở đầu file
static int quetAp(const char* prefix) {
  g_apN = 0;
  bao("DANG DO SONG...", C_YEL, prefix, "Lai gan may dich", 0);
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(300);
  int n = WiFi.scanNetworks();
  int plen = strlen(prefix);
  for (int i = 0; i < n && g_apN < 24; i++) {
    String ss = WiFi.SSID(i);
    if (!ss.startsWith(prefix)) continue;
    g_ap[g_apN].ssid  = ss;
    g_ap[g_apN].nhan  = ss.substring(plen);
    g_ap[g_apN].bssid = WiFi.BSSIDstr(i);
    g_ap[g_apN].rssi  = WiFi.RSSI(i);
    g_apN++;
  }
  WiFi.scanDelete();
  Serial.printf("[AP] %s -> %d may\n", prefix, g_apN);
  return g_apN;
}
/* Danh sách máy dò được → chạm chọn. Trả index hoặc -1 (huỷ). */
static int chonAp(const char* tieuDe) {
  for (;;) {
    lFill(C_BG); thanhTieuDe(tieuDe);
    if (g_apN == 0) { lTextC(LW / 2, 200, "KHONG THAY MAY NAO", 3, C_DO, C_BG);
      lTextC(LW / 2, 260, "LAI GAN ROI CHAM DE DO LAI", 2, C_PHU, C_BG); }
    int rows = g_apN < 6 ? g_apN : 6;
    for (int i = 0; i < rows; i++) {
      int y = 60 + i * 62;
      lRoundRectA(40, y, LW - 200, 54, 10, C_BTN, C_BG);
      lText(60, y + 16, g_ap[i].nhan.c_str(), 3, C_WHITE, C_BTN);
      char rs[12]; snprintf(rs, sizeof rs, "%ddBm", g_ap[i].rssi);
      lTextR(LW - 180, y + 20, rs, 2, C_PHU, C_BTN);
    }
    Nut lai = { LW - 150, 60, 120, 90 };  veNut(lai, "DO LAI", C_BAR, C_YEL, 2);
    Nut huy = { LW - 150, 170, 120, 90 }; veNut(huy, "THOAT", C_BAR, C_DO, 2);
    fbFlush();
    int x, y; if (!doiCham(&x, &y, 0)) continue;
    if (trong(lai, x, y)) return -2;      // -2 = dò lại
    if (trong(huy, x, y)) return -1;
    for (int i = 0; i < rows; i++) { int ry = 60 + i * 62; if (y >= ry && y < ry + 54 && x < LW - 200) return i; }
  }
}

/* ─── BÀN PHÍM SỐ (nhập PIN) ─────────────────────────────────────────────── */
static long banPhimSo(const char* nhan) {
  String s = "";
  for (;;) {
    lFill(C_BG); thanhTieuDe(nhan);
    lRoundRectA(200, 60, 400, 60, 12, C_WHITE, C_BG);
    String hienthi = ""; for (unsigned i = 0; i < s.length(); i++) hienthi += "*";
    lTextC(400, 78, hienthi.length() ? hienthi.c_str() : "____", 4, C_INK, C_WHITE);
    const char* lab[12] = {"1","2","3","4","5","6","7","8","9","XOA","0","OK"};
    Nut bt[12];
    for (int i = 0; i < 12; i++) {
      int c = i % 3, r = i / 3;
      bt[i] = { 220 + c * 130, 140 + r * 78, 116, 66 };
      uint16_t nen = (i == 9) ? C_DO : (i == 11 ? C_OK : C_BTN);
      veNut(bt[i], lab[i], nen, C_WHITE, 3);
    }
    fbFlush();
    int x, y; if (!doiCham(&x, &y, 0)) continue;
    for (int i = 0; i < 12; i++) if (trong(bt[i], x, y)) {
      if (i == 9) { if (s.length()) s.remove(s.length() - 1); }
      else if (i == 11) { return s.length() ? s.toInt() : -1; }
      else { const char* d[12] = {"1","2","3","4","5","6","7","8","9","","0",""}; if (s.length() < 8) s += d[i]; }
      break;
    }
  }
}

/* ─── NẠP: nối AP máy đích ───────────────────────────────────────────────── */
static bool noiAp(const Ap& a, const char* pass) {
  bao("KET NOI MAY DICH...", C_YEL, a.nhan.c_str(), a.bssid.c_str(), 0);
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(150);
  WiFi.begin(a.ssid.c_str(), pass);
  uint32_t t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < 15000) delay(250);
  if (WiFi.status() != WL_CONNECTED) { bao("NOI THAT BAI", C_DO, a.nhan.c_str(), "Lai gan roi thu lai", 1800); return false; }
  return true;
}
static String b64(const String& s) {
  size_t olen = 0; unsigned char out[160];
  mbedtls_base64_encode(out, sizeof out, &olen, (const unsigned char*)s.c_str(), s.length());
  return String((char*)out).substring(0, olen);
}
/* Gửi thân file .bin qua client đang mở (đã in header). Cập nhật thanh %. */
static bool guiFile(WiFiClient& cl, File& f, long fsize) {
  uint8_t buf[1024]; long sent = 0; int last = -1;
  while (sent < fsize) {
    int want = (fsize - sent > 1024) ? 1024 : (int)(fsize - sent);
    int n = f.read(buf, want); if (n <= 0) return false;
    int w = 0; while (w < n) { int x = cl.write(buf + w, n - w); if (x <= 0) { if (!cl.connected()) return false; delay(1); } else w += x; }
    sent += n; int pct = (int)(sent * 100 / fsize); if (pct != last) { last = pct; ttPct(pct); }
  }
  return sent == fsize;
}
static bool docPhanHoi(WiFiClient& cl, String& resp) {
  resp = ""; uint32_t t0 = millis();
  while ((cl.connected() || cl.available()) && millis() - t0 < 20000) {
    while (cl.available()) { resp += (char)cl.read(); t0 = millis(); if (resp.length() > 3000) break; }
    delay(2);
  }
  cl.stop(); return true;
}
static int httpStatus(const String& resp) { int sp = resp.indexOf(' '); return sp >= 0 ? resp.substring(sp + 1, sp + 4).toInt() : 0; }

/* NẠP GHẾ: POST .bin THÔ /update + header X-OTA-Key. */
static bool napGhe(const Ap& a) {
  long fsize = fwSize(FW_GHE);
  if (fsize <= 0) { bao("KHONG CO FILE", C_DO, FW_GHE, "Chep .bin ghe vao the SD", 2000); return false; }
  if (!noiAp(a, SEC_GHE_AP_PASS)) return false;
  WiFiClient cl; cl.setTimeout(20000);
  if (!cl.connect(IPAddress(192, 168, 4, 1), 80)) { bao("KHONG NOI 192.168.4.1", C_DO, "", "", 1600); WiFi.disconnect(true); return false; }
  cl.print("POST /update HTTP/1.1\r\n");
  cl.print("Host: 192.168.4.1\r\n");
  cl.print(String("X-OTA-Key: ") + SEC_GHE_OTA_KEY + "\r\n");
  cl.print("Content-Type: application/octet-stream\r\n");
  cl.print("Content-Length: " + String(fsize) + "\r\n");
  cl.print("Connection: close\r\n\r\n");
  ttMo("DANG NAP GHE", a.nhan.c_str()); ttPct(0);
  File f = SD_MMC.open(FW_GHE, FILE_READ);
  bool sent = f && guiFile(cl, f, fsize); if (f) f.close();
  String resp; docPhanHoi(cl, resp); WiFi.disconnect(true);
  int st = httpStatus(resp);
  bool ok = sent && st == 200 && (resp.indexOf("OK") >= 0 || resp.indexOf("khoi dong") >= 0);
  if (ok) bao("NAP XONG", C_OK, a.nhan.c_str(), "Ghe dang khoi dong lai", 2200);
  else    bao("NAP LOI", C_DO, a.nhan.c_str(), "Giu firmware cu - thu lai", 2200);
  return ok;
}
/* NẠP CHẤM CÔNG: POST multipart /update + Basic-Auth. */
static bool napChamCong(const Ap& a) {
  long fsize = fwSize(FW_CC);
  if (fsize <= 0) { bao("KHONG CO FILE", C_DO, FW_CC, "Chep .bin cham cong vao the SD", 2000); return false; }
  if (!noiAp(a, SEC_CC_AP_PASS)) return false;
  WiFiClient cl; cl.setTimeout(20000);
  if (!cl.connect(IPAddress(192, 168, 4, 1), 80)) { bao("KHONG NOI 192.168.4.1", C_DO, "", "", 1600); WiFi.disconnect(true); return false; }
  String bnd = "----traP4" + String((uint32_t)millis(), HEX);
  String pre = "--" + bnd + "\r\nContent-Disposition: form-data; name=\"fw\"; filename=\"firmware.bin\"\r\nContent-Type: application/octet-stream\r\n\r\n";
  String post = "\r\n--" + bnd + "--\r\n";
  long clen = (long)pre.length() + fsize + (long)post.length();
  cl.print("POST /update HTTP/1.1\r\n");
  cl.print("Host: 192.168.4.1\r\n");
  cl.print("Authorization: Basic " + b64(String(SEC_CC_OTA_USER) + ":" + SEC_CC_OTA_PASS) + "\r\n");
  cl.print("Content-Type: multipart/form-data; boundary=" + bnd + "\r\n");
  cl.print("Content-Length: " + String(clen) + "\r\n");
  cl.print("Connection: close\r\n\r\n");
  cl.print(pre);
  ttMo("DANG NAP CHAM CONG", a.nhan.c_str()); ttPct(0);
  File f = SD_MMC.open(FW_CC, FILE_READ);
  bool sent = f && guiFile(cl, f, fsize); if (f) f.close();
  if (sent) cl.print(post);
  String resp; docPhanHoi(cl, resp); WiFi.disconnect(true);
  int st = httpStatus(resp);
  bool ok = sent && st == 200 && (resp.indexOf("THANH CONG") >= 0 || resp.indexOf("thanh cong") >= 0 ||
            resp.indexOf("success") >= 0 || resp.indexOf("khoi dong") >= 0 || true);   // gửi đủ + 200 coi như OK
  if (ok) bao("NAP XONG", C_OK, a.nhan.c_str(), "May cham cong khoi dong lai", 2200);
  else    bao("NAP LOI", C_DO, a.nhan.c_str(), "Thu lai", 2200);
  return ok;
}

/* ─── 4G + WEB (KIỂM TRA CHỈ SỐ — CHỈ XEM) ───────────────────────────────── */
static bool bat4G() {
  if (g_4gUp && net4gReady()) return true;
  WiFi.disconnect(true); WiFi.mode(WIFI_OFF); delay(100);   // nhường sóng, dùng 4G
  bao("DANG BAT 4G...", C_YEL, "Cho ~20s", "Kiem SIM / song neu lau", 0);
  g_4gUp = net4gBatDau();
  return g_4gUp;
}
static bool webGoi(const char* viec, const String& body, String& raOut) {
  String url = String(SEC_WEB_BASE); url += (url.indexOf('?') < 0 ? "?" : "&"); url += "api="; url += viec;
  return net4gPost(url, body, raOut) == 200;
}
static bool webLogin(const String& pin) {
  StaticJsonDocument<128> b; b["pin"] = pin; String body; serializeJson(b, body);
  String r; if (!webGoi("login", body, r)) return false;
  StaticJsonDocument<512> d; if (deserializeJson(d, r)) return false;
  if (!(d["ok"] | false)) return false;
  g_token = String((const char*)(d["token"] | "")); return g_token.length() > 0;
}
static bool damBaoDangNhap() {
  if (g_token.length()) return true;
  if (g_pinLuu.length()) { bao("DANG NHAP TU DONG", C_YEL, "PIN da luu", "", 0); if (webLogin(g_pinLuu)) return true;
    g_pinLuu = ""; prefsTram.remove("pin"); }
  long pin = banPhimSo("NHAP PIN"); if (pin < 0) return false;
  if (!webLogin(String(pin))) { bao("SAI PIN", C_DO, "", "", 1600); return false; }
  g_pinLuu = String(pin); prefsTram.putString("pin", g_pinLuu); return true;
}
/* Hiện số liệu CHỈ XEM cho một ghế. */
static void veChiSo(const String& ma, const String& coso, long hethong, long csTruoc, int donVi, int lanDau) {
  lFill(C_BG); thanhTieuDe("KIEM TRA CHI SO (CHI XEM)");
  int y = 70, gap = 62;
  auto dong = [&](const char* nhan, const String& gt, uint16_t cc) {
    lText(50, y + 12, nhan, 2, C_PHU, C_BG); lTextR(LW - 50, y + 8, gt.c_str(), 3, cc, C_BG);
    lRect(50, y + 50, LW - 100, 1, C_GLOW2); y += gap;
  };
  dong("MA MAY",            ma, C_WHITE);
  dong("CO SO",             coso.length() ? coso : String("-"), C_WHITE);
  dong("TIEN MAT HE THONG", String(hethong), C_YEL);
  dong("CHI SO LAN TRUOC",  String(csTruoc), C_WHITE);
  dong("DON GIA / CHI SO",  String(donVi), C_WHITE);
  if (lanDau) lTextC(LW / 2, y + 6, "GHE CHUA CHOT LAN NAO", 2, C_YEL, C_BG);
  Nut ve = { LW / 2 - 90, LH - 66, 180, 52 }; veNut(ve, "XONG", C_BAR, C_WHITE, 3);
  fbFlush();
  int x, yy; doiCham(&x, &yy, 0);
}
static void cheDoKiemTra() {
  quetAp(GHE_AP_PREFIX);
  int sel = chonAp("CHON GHE DE KIEM TRA");
  while (sel == -2) { quetAp(GHE_AP_PREFIX); sel = chonAp("CHON GHE DE KIEM TRA"); }
  if (sel < 0) return;
  String ma = g_ap[sel].nhan;
  if (!bat4G()) { bao("4G CHUA SAN SANG", C_DO, "Kiem SIM / song", "", 2000); return; }
  if (!damBaoDangNhap()) return;
  bao("DANG LAY SO LIEU...", C_YEL, ma.c_str(), "", 0);
  StaticJsonDocument<192> b; b["token"] = g_token; b["ma_may"] = ma; String body; serializeJson(b, body);
  String r; if (!webGoi("chot_xem", body, r)) { bao("LOI MANG", C_DO, "chot_xem", "", 1800); return; }
  StaticJsonDocument<1024> d; if (deserializeJson(d, r)) { bao("WEB TRA RAC", C_DO, "", "", 1800); return; }
  if (!(d["ok"] | false)) { String e = String((const char*)(d["error"] | "LOI")); bao("KHONG XEM DUOC", C_DO, e.c_str(), "", 2200); return; }
  veChiSo(ma, String((const char*)(d["coso"] | "")), (long)(d["theo_he_thong"] | 0),
          (long)(d["chi_so_truoc"] | 0), (int)(d["don_vi"] | 5000), (int)(d["lan_dau"] | 0));
}

static void cheDoNapGhe() {
  quetAp(GHE_AP_PREFIX);
  int sel = chonAp("CHON GHE DE NAP FW");
  while (sel == -2) { quetAp(GHE_AP_PREFIX); sel = chonAp("CHON GHE DE NAP FW"); }
  if (sel < 0) return;
  napGhe(g_ap[sel]);
}
static void cheDoNapChamCong() {
  quetAp(CC_AP_PREFIX);
  int sel = chonAp("CHON MAY CHAM CONG");
  while (sel == -2) { quetAp(CC_AP_PREFIX); sel = chonAp("CHON MAY CHAM CONG"); }
  if (sel < 0) return;
  napChamCong(g_ap[sel]);
}

/* ─── MENU CHÍNH ─────────────────────────────────────────────────────────── */
static void veMenu() {
  lFill(C_BG); thanhTieuDe("MAY KY THUAT K&H");
  Nut b1 = { 60, 70,  LW - 120, 100 };
  Nut b2 = { 60, 190, LW - 120, 100 };
  Nut b3 = { 60, 310, LW - 120, 100 };
  veNut(b1, "1  KIEM TRA CHI SO MAY", C_BTN2, C_WHITE, 3);
  veNut(b2, "2  NAP FIRMWARE GHE",    C_BTN,  C_WHITE, 3);
  veNut(b3, "3  NAP FW MAY CHAM CONG", C_BTN, C_WHITE, 3);
  // Chân: trạng thái thẻ SD + 2 file .bin
  long fg = fwSize(FW_GHE), fc = fwSize(FW_CC);
  String sd;
  if (!g_sdOk) sd = "CHUA CO THE SD";
  else sd = String("SD OK  |  ghe.bin: ") + (fg > 0 ? String(fg / 1024) + "KB" : "THIEU")
          + "  |  chamcong.bin: " + (fc > 0 ? String(fc / 1024) + "KB" : "THIEU");
  lTextC(LW / 2, LH - 30, sd.c_str(), 2, g_sdOk ? ( (fg > 0 || fc > 0) ? C_OK : C_YEL ) : C_YEL, C_BG);
  fbFlush();
}

void setup() {
  Serial.begin(115200);
  prefsTram.begin("tram", false);
  g_pinLuu = prefsTram.getString("pin", "");
  gtInit();
  if (manKhoiTao()) { gpio_set_level((gpio_num_t)PANEL_BL_GPIO, 1); }
  sdBatDau();
}

void loop() {
  veMenu();
  Nut b1 = { 60, 70,  LW - 120, 100 };
  Nut b2 = { 60, 190, LW - 120, 100 };
  Nut b3 = { 60, 310, LW - 120, 100 };
  int x, y; if (!doiCham(&x, &y, 0)) return;
  if      (trong(b1, x, y)) cheDoKiemTra();
  else if (trong(b2, x, y)) cheDoNapGhe();
  else if (trong(b3, x, y)) cheDoNapChamCong();
  // quay lại menu ở vòng loop kế
}
