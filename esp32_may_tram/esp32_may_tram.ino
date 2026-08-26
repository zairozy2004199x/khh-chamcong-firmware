/*
 * ESP32 "MÁY TRẠM" cho hệ QR ghế massage POSH  (board CYD ESP32-2432S028)
 * =============================================================================
 * MỘT thiết bị cầm tay — HAI việc, chọn ở màn chính:
 *
 *   [1] NẠP FIRMWARE (OTA nội bộ, không cần điện thoại/cáp)
 *       - Chép firmware.bin vào thẻ microSD của máy trạm.
 *       - Đến gần hộp QR (ESP32 ghế). Hộp ghế tự phát AP "POSH_QR-<mã ghế>".
 *       - Máy trạm dò AP đó -> hiện danh sách -> chạm chọn ghế.
 *       - Nối AP (mật khẩu 12345678) -> POST .bin THÔ lên http://192.168.4.1/update
 *         kèm header "X-OTA-Key: 12345678"  (đúng giao thức otaPhucVu() trong
 *         esp32_ghe_massage.ino) -> ghế tự nạp & khởi động lại.
 *
 *   [2] THU TIỀN / CHỐT CA
 *       - Dò AP "POSH_QR-*" để BIẾT MÃ GHẾ (khỏi gõ tay) -> chạm chọn ghế.
 *       - Chuyển sang WiFi Internet của máy trạm (khai ở phần CẤU HÌNH bên dưới).
 *       - Gõ PIN -> đăng nhập web (?api=login) lấy token.
 *       - Gọi ?api=chot_xem -> hiện: cơ sở, TIỀN MẶT hệ thống ghi nhận từ lần chốt
 *         trước, QR (nếu quyền cho phép gọi ?api=so_may). Nhân viên đếm tiền mặt thật.
 *       - Gõ CHỈ SỐ trên màn máy đếm + SỐ TIỀN đếm được -> bấm CHỐT
 *         -> ?api=chot_luu -> hệ thống tự đóng ca, hiện lệch/không.
 *
 * VÌ SAO WiFi AP (nối ghế) và WiFi STA (nối Internet) KHÔNG chạy cùng lúc ở đây:
 *   máy trạm chỉ có MỘT radio WiFi. OTA thì nối AP của ghế; chốt ca thì nối
 *   Internet. Hai việc TÁCH BẠCH theo thời gian nên không cần đồng thời — khác với
 *   con ghế (ghế có 4G riêng nên radio WiFi rảnh để phát AP).
 *
 * THƯ VIỆN: TFT_eSPI (Bodmer, dùng ĐÚNG User_Setup.h của CYD) · XPT2046_Touchscreen
 *           (P. Stoffregen) · ArduinoJson (Benoit Blanchon).
 *
 * ⚠️ KHÔNG biên dịch được trong môi trường này — phải build & test trên phần cứng
 *    CYD thật (Arduino IDE / arduino-cli, board "ESP32 Dev Module").
 *
 * AN TOÀN: ghế dùng Update.begin/end — nạp lỗi/dở thì ghế HỦY và GIỮ firmware cũ.
 */

#include <WiFi.h>
#include <SD.h>
#include <SPI.h>
#include <TFT_eSPI.h>
#include <XPT2046_Touchscreen.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

// ── Khai báo trước (arduino-cli thôi tự sinh prototype khi đã có sẵn) ──────────
struct ApGhe;
bool  getTouch(int& sx, int& sy);
bool  cho4Cham(int& x, int& y, unsigned long chuMs);
int   quetAp(bool phaiCoGhe);
int   chonGheTuDanhSach(const char* tieuDe);
bool  napFirmwareVaoGhe(const ApGhe& g);
long  banPhimSo(const char* nhan, long macDinh, long toiDa);
bool  webLogin(const String& pin);
bool  webGoi(const String& viec, const String& body, String& raOut);
void  cheDoOTA();
void  cheDoChotCa();

/* ═══════════════════════════════ CẤU HÌNH ═══════════════════════════════════
 * Sửa 3 dòng dưới cho đúng hiện trường rồi nạp lại. (Bản sau có thể chuyển sang
 * đọc từ NVS/portal như máy trạm chấm công; giờ để #define cho gọn & dễ đọc.)
 * ─────────────────────────────────────────────────────────────────────────── */
#define WIFI_SSID     "TenWifiCuaBan"          // WiFi Internet cho phần CHỐT CA
#define WIFI_PASS     "MatKhauWifi"
#define WEB_BASE      "https://khmatrix.com/ghe/" // trang /ghe của web (có dấu / cuối)

// Khớp secrets/otaPhucVu của con ghế (esp32_ghe_massage.ino: SEC_AP_PASS, OTA_AP_PREFIX)
#define GHE_AP_PREFIX "POSH_QR-"
#define GHE_AP_PASS   "12345678"
#define OTA_KEY       "12345678"                // == SEC_AP_PASS: header X-OTA-Key
IPAddress GHE_IP(192, 168, 4, 1);
const uint16_t GHE_PORT = 80;
const char*  FW_PATH   = "/firmware.bin";       // tên file .bin trên thẻ SD máy trạm
const int    NEAR_RSSI = -90;                   // gần như không lọc sóng (để chắc thấy ghế; siết lại sau khi ok)

// ── Phần cứng CYD (khớp esp32_ghe_massage.ino) ────────────────────────────────
#define T_CS   33
#define T_IRQ  36
#define T_CLK  25
#define T_DIN  32
#define T_DO   39
int TS_MINX = 200, TS_MAXX = 3700, TS_MINY = 240, TS_MAXY = 3800;
#define SD_CS  5                                 // thẻ SD trên CYD: SPI SCK18/MISO19/MOSI23
#define BL_PIN 21                                // đèn nền
#define FW_VERSION "may-tram 2026-08-26a (OTA POSH_QR + chot ca)"
// ═════════════════════════════════════════════════════════════════════════════

TFT_eSPI tft = TFT_eSPI();
SPIClass tsSPI = SPIClass(HSPI);
XPT2046_Touchscreen ts(T_CS, T_IRQ);
bool   g_sdOk = false;
String g_token = "";                            // token web sau khi login (phần chốt ca)
int    g_okCount = 0, g_failCount = 0;

// Một AP ghế dò được
struct ApGhe { String ssid; String ma; String bssid; int rssi; int kenh; };
ApGhe g_ds[24]; int g_dsN = 0;

// ── Màu (giống bảng màu con ghế cho đồng bộ) ──────────────────────────────────
#define COL_BG   TFT_BLACK
#define COL_ACC  0xFD20        // cam
#define COL_OK   0x07E0        // xanh lá
#define COL_DO   0xF800        // đỏ
#define COL_XAM  0x8410

// ═══════════════════════════════ CẢM ỨNG ════════════════════════════════════
bool getTouch(int& sx, int& sy){
  if(!ts.touched()) return false;
  TS_Point p = ts.getPoint();
  sx = map(p.x, TS_MINX, TS_MAXX, 0, 320);
  sy = map(p.y, TS_MINY, TS_MAXY, 0, 240);
  sx = constrain(sx, 0, 319); sy = constrain(sy, 0, 239);
  return true;
}
/* Chờ MỘT lần chạm (nhả tay mới trả) trong tối đa chuMs; chuMs=0 = chờ mãi.
   Trả false nếu hết giờ. Chống dội: đợi nhả tay ~40ms. */
bool cho4Cham(int& x, int& y, unsigned long chuMs){
  unsigned long t0 = millis();
  while(chuMs == 0 || millis() - t0 < chuMs){
    if(getTouch(x, y)){
      int lx, ly; unsigned long tn = millis();
      while(getTouch(lx, ly) && millis() - tn < 1200) delay(10);  // đợi nhả
      delay(40);
      return true;
    }
    delay(15);
  }
  return false;
}

// ═══════════════════════════════ MÀN HÌNH ═══════════════════════════════════
void manChao(){
  tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(0xCE40, COL_BG); tft.setTextSize(2);
  tft.drawString("K&H", 160, 66, 4); tft.setTextSize(1);
  tft.setTextColor(COL_ACC, COL_BG); tft.drawString("MAY TRAM POSH", 160, 118, 4);
  tft.setTextColor(COL_XAM, COL_BG); tft.drawString("Nap firmware & Thu tien", 160, 150, 2);
}

void bao(const String& l1, uint16_t c1, const String& l2, const String& l3){
  tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(c1, COL_BG);   tft.drawString(l1, 160, 70, 4);
  if(l2.length()){ tft.setTextColor(TFT_WHITE, COL_BG); tft.drawString(l2, 160, 120, 2); }
  if(l3.length()){ tft.setTextColor(COL_XAM, COL_BG);  tft.drawString(l3, 160, 150, 2); }
}

// Thanh tiến trình
const int TT_X = 24, TT_Y = 150, TT_W = 272, TT_H = 24;
int _ttCu = -1;
void ttMo(const String& tieuDe, const String& phu){
  tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(COL_ACC, COL_BG); tft.drawString(tieuDe, 160, 60, 4);
  if(phu.length()){ tft.setTextColor(TFT_WHITE, COL_BG); tft.drawString(phu, 160, 100, 2); }
  tft.drawRoundRect(TT_X, TT_Y, TT_W, TT_H, 5, COL_XAM);
  _ttCu = -1;
}
void ttPct(int pct){
  pct = constrain(pct, 0, 100);
  if(pct == _ttCu) return; _ttCu = pct;
  int rong = (TT_W - 4) * pct / 100;
  tft.fillRect(TT_X + 2, TT_Y + 2, TT_W - 4, TT_H - 4, COL_BG);
  if(rong > 0) tft.fillRect(TT_X + 2, TT_Y + 2, rong, TT_H - 4, COL_OK);
  tft.fillRect(120, 118, 80, 26, COL_BG);
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_CYAN, COL_BG);
  tft.drawString(String(pct) + " %", 160, 128, 4);
}

// ═══════════════════════════ BÀN PHÍM SỐ trên màn ════════════════════════════
/* Nhập một số nguyên. Trả -1 nếu người dùng bấm HỦY.
   Layout: 3x4 phím số + [Xoa][0][OK]; nút HỦY ở góc trên. */
long banPhimSo(const char* nhan, long macDinh, long toiDa){
  String s = macDinh > 0 ? String(macDinh) : "";
  const int C = 3, R = 4;
  const int bx = 20, by = 84, bw = 90, bh = 36, gx = 6, gy = 4;
  const char* ky[12] = {"1","2","3","4","5","6","7","8","9","Xoa","0","OK"};
  auto ve = [&](){
    tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
    tft.setTextColor(COL_ACC, COL_BG); tft.drawString(String(nhan), 160, 22, 4);
    // ô hiển thị số
    tft.drawRoundRect(20, 42, 244, 32, 5, COL_XAM);
    tft.fillRect(22, 44, 240, 28, COL_BG);
    tft.setTextColor(TFT_WHITE, COL_BG);
    tft.drawString(s.length() ? s : "_", 142, 58, 4);
    // nút HỦY
    tft.fillRoundRect(272, 42, 44, 32, 5, COL_DO);
    tft.setTextColor(TFT_WHITE, COL_DO); tft.drawString("X", 294, 58, 4);
    // phím
    for(int i = 0; i < 12; i++){
      int c = i % C, r = i / C;
      int x = bx + c * (bw + gx), y = by + r * (bh + gy);
      uint16_t bg = (i == 9) ? 0x39C7 : (i == 11 ? COL_OK : 0x18E3);
      tft.fillRoundRect(x, y, bw, bh, 5, bg);
      tft.setTextColor(TFT_WHITE, bg);
      tft.drawString(ky[i], x + bw / 2, y + bh / 2, 4);
    }
  };
  ve();
  for(;;){
    int x, y; if(!cho4Cham(x, y, 0)) continue;
    if(x >= 272 && y < 74) return -1;                 // HỦY
    for(int i = 0; i < 12; i++){
      int c = i % C, r = i / C;
      int px = bx + c * (bw + gx), py = by + r * (bh + gy);
      if(x >= px && x <= px + bw && y >= py && y <= py + bh){
        if(i == 9){ if(s.length()) s.remove(s.length() - 1); }   // Xoa
        else if(i == 11){                                        // OK
          long v = s.length() ? s.toInt() : 0;
          if(toiDa > 0 && v > toiDa){ bao("So qua lon", COL_DO, "Toi da " + String(toiDa), ""); delay(1200); ve(); break; }
          return v;
        }
        else { if(s.length() < 9) s += ky[i]; }
        // vẽ lại ô số
        tft.fillRect(22, 44, 240, 28, COL_BG);
        tft.setTextColor(TFT_WHITE, COL_BG); tft.setTextDatum(MC_DATUM);
        tft.drawString(s.length() ? s : "_", 142, 58, 4);
        break;
      }
    }
  }
}

// ═══════════════════════════ DÒ & CHỌN GHẾ (AP) ══════════════════════════════
/* Quét WiFi, lọc AP "POSH_QR-*" ở gần, xếp theo sóng. Trả số ghế tìm được. */
int quetAp(bool phaiCoGhe){
  bao("Dang do ghe...", COL_ACC, "Quet WiFi POSH_QR", "");
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(300);
  int n = WiFi.scanNetworks();          // đồng bộ; mặc định quét mọi kênh
  Serial.printf("[QUET] thay %d AP:\n", n);
  g_dsN = 0;
  for(int i = 0; i < n; i++){
    String ss = WiFi.SSID(i);
    // In HẾT để soi: thấy POSH_QR không? sóng bao nhiêu? tên có đúng prefix không?
    Serial.printf("   %2d) \"%s\"  %ddBm  ch%d %s\n", i, ss.c_str(), WiFi.RSSI(i), WiFi.channel(i),
                  ss.startsWith(GHE_AP_PREFIX) ? "<= GHE" : "");
    if(g_dsN >= 24) continue;
    if(!ss.startsWith(GHE_AP_PREFIX)) continue;
    if(WiFi.RSSI(i) < NEAR_RSSI) continue;
    ApGhe g; g.ssid = ss; g.ma = ss.substring(strlen(GHE_AP_PREFIX));
    g.bssid = WiFi.BSSIDstr(i); g.rssi = WiFi.RSSI(i); g.kenh = WiFi.channel(i);
    g_ds[g_dsN++] = g;
  }
  WiFi.scanDelete();
  Serial.printf("[QUET] loc ra %d ghe POSH_QR\n", g_dsN);
  // sắp xếp sóng mạnh trước
  for(int i = 0; i < g_dsN; i++) for(int j = i + 1; j < g_dsN; j++)
    if(g_ds[j].rssi > g_ds[i].rssi){ ApGhe t = g_ds[i]; g_ds[i] = g_ds[j]; g_ds[j] = t; }
  if(g_dsN == 0 && phaiCoGhe){
    bao("Khong thay ghe", COL_DO, "Lai gan hop QR hon", "Cham de do lai");
    int x, y; cho4Cham(x, y, 0);
  }
  return g_dsN;
}

/* Hiện danh sách ghế, chạm chọn. Trả chỉ số trong g_ds, -1 nếu hủy/không có. */
int chonGheTuDanhSach(const char* tieuDe){
  if(g_dsN == 0) return -1;
  const int y0 = 52, rh = 34;
  tft.fillScreen(COL_BG); tft.setTextDatum(TL_DATUM);
  tft.setTextColor(COL_ACC, COL_BG); tft.drawString(String(tieuDe), 12, 14, 4);
  tft.setTextColor(COL_XAM, COL_BG); tft.setTextDatum(TR_DATUM);
  tft.drawString("Do lai", 308, 22, 2);
  int hien = g_dsN > 5 ? 5 : g_dsN;                 // 5 dòng vừa màn 240px
  for(int i = 0; i < hien; i++){
    int y = y0 + i * rh;
    tft.fillRoundRect(12, y, 296, rh - 4, 5, 0x18E3);
    tft.setTextDatum(TL_DATUM); tft.setTextColor(TFT_WHITE, 0x18E3);
    tft.drawString(g_ds[i].ma, 22, y + 8, 4);
    tft.setTextDatum(TR_DATUM); tft.setTextColor(COL_XAM, 0x18E3);
    tft.drawString(String(g_ds[i].rssi) + "dBm", 300, y + 12, 2);
  }
  for(;;){
    int x, y; if(!cho4Cham(x, y, 0)) continue;
    if(y < 44 && x > 240) return -2;                // "Do lai"
    for(int i = 0; i < hien; i++){
      int ry = y0 + i * rh;
      if(y >= ry && y <= ry + rh - 4) return i;
    }
  }
}

// ═══════════════════════════ NẠP FIRMWARE VÀO GHẾ ════════════════════════════
/* Nối AP ghế đã chọn, POST body THÔ .bin lên /update kèm X-OTA-Key. */
bool napFirmwareVaoGhe(const ApGhe& g){
  if(!g_sdOk){ bao("Chua co the SD", COL_DO, "Cam the co firmware.bin", ""); delay(1600); return false; }
  File f = SD.open(FW_PATH, FILE_READ);
  if(!f){ bao("Khong thay file", COL_DO, String(FW_PATH), "Chep .bin vao the truoc"); delay(1800); return false; }
  long fsize = f.size();
  if(fsize <= 0){ f.close(); bao("File rong", COL_DO, "", ""); delay(1600); return false; }

  bao("Ket noi ghe...", COL_ACC, g.ma, g.bssid);
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(150);
  WiFi.begin(g.ssid.c_str(), GHE_AP_PASS);
  unsigned long t0 = millis();
  while(WiFi.status() != WL_CONNECTED && millis() - t0 < 15000){ delay(250); }
  if(WiFi.status() != WL_CONNECTED){
    f.close(); bao("Noi that bai", COL_DO, g.ma, "Lai gan hon roi thu lai"); delay(1800); return false;
  }

  WiFiClient cl; cl.setTimeout(20000);
  if(!cl.connect(GHE_IP, GHE_PORT)){
    f.close(); bao("Khong noi 192.168.4.1", COL_DO, "", ""); WiFi.disconnect(true); delay(1600); return false;
  }
  cl.print("POST /update HTTP/1.1\r\n");
  cl.print("Host: 192.168.4.1\r\n");
  cl.print(String("X-OTA-Key: ") + OTA_KEY + "\r\n");
  cl.print("Content-Type: application/octet-stream\r\n");
  cl.print("Content-Length: " + String(fsize) + "\r\n");
  cl.print("Connection: close\r\n\r\n");

  ttMo("DANG NAP", "Ghe " + g.ma + "  -  KHONG tat nguon");
  ttPct(0);
  uint8_t buf[1024]; long sent = 0; bool sendOk = true; int lastPct = -1;
  while(sent < fsize){
    int want = (fsize - sent > 1024) ? 1024 : (int)(fsize - sent);
    int n = f.read(buf, want);
    if(n <= 0){ sendOk = false; break; }
    int w = 0;
    while(w < n){
      int x = cl.write(buf + w, n - w);
      if(x <= 0){ if(!cl.connected()){ sendOk = false; break; } delay(1); }
      else w += x;
    }
    if(!sendOk) break;
    sent += n;
    int pct = (int)(sent * 100 / fsize);
    if(pct != lastPct){ lastPct = pct; ttPct(pct); }
  }
  f.close();

  // Đọc phản hồi ghế
  String resp = ""; unsigned long tr = millis();
  while((cl.connected() || cl.available()) && millis() - tr < 20000){
    while(cl.available()){ resp += (char)cl.read(); tr = millis(); if(resp.length() > 2000) break; }
    delay(2);
  }
  cl.stop(); WiFi.disconnect(true);

  int sp = resp.indexOf(' ');
  int status = (sp >= 0) ? resp.substring(sp + 1, sp + 4).toInt() : 0;
  bool ok = sendOk && (sent == fsize) && (status == 200)
            && (resp.indexOf("OK") >= 0 || resp.indexOf("khoi dong") >= 0);
  Serial.printf("[NAP] %s: %ld/%ld byte http%d -> %s\n", g.ma.c_str(), sent, fsize, status, ok ? "OK" : "FAIL");
  if(ok){ g_okCount++; bao("NAP XONG", COL_OK, "Ghe " + g.ma + " dang khoi dong lai", "OK:" + String(g_okCount) + " Loi:" + String(g_failCount)); }
  else  { g_failCount++; bao("NAP LOI", COL_DO, "Ghe " + g.ma, "Giu firmware cu - thu lai"); }
  delay(2000);
  return ok;
}

// ═══════════════════════════════ CHẾ ĐỘ OTA ══════════════════════════════════
void cheDoOTA(){
  for(;;){
    if(quetAp(true) == 0){ if(!g_dsN) continue; }   // quetAp tự chờ chạm khi rỗng
    int sel = chonGheTuDanhSach("Chon ghe de NAP");
    if(sel == -2) continue;                          // "Do lai"
    if(sel < 0) return;
    napFirmwareVaoGhe(g_ds[sel]);
    // xong 1 ghế -> quay lại dò tiếp
  }
}

// ══════════════════════════ WEB (phần CHỐT CA) ═══════════════════════════════
bool noiInternet(){
  bao("Noi WiFi Internet...", COL_ACC, WIFI_SSID, "");
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(150);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  unsigned long t0 = millis();
  while(WiFi.status() != WL_CONNECTED && millis() - t0 < 20000){ delay(250); }
  bool ok = WiFi.status() == WL_CONNECTED;
  if(!ok){ bao("Khong noi Internet", COL_DO, WIFI_SSID, "Kiem ten/mat khau WiFi"); delay(1800); }
  return ok;
}

/* POST JSON tới WEB_BASE?api=<viec>. Trả true nếu HTTP 200; thân trả về ở raOut. */
bool webGoi(const String& viec, const String& body, String& raOut){
  String url = String(WEB_BASE);
  url += (url.indexOf('?') < 0 ? "?" : "&");
  url += "api=" + viec;
  WiFiClientSecure c; c.setInsecure();             // WordPress https; site có thể tự-ký
  HTTPClient h; h.setTimeout(15000);
  h.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
  bool begun = url.startsWith("https") ? h.begin(c, url) : h.begin(url);
  if(!begun){ raOut = ""; return false; }
  h.addHeader("Content-Type", "application/json");
  int code = h.POST((uint8_t*)body.c_str(), body.length());
  raOut = (code > 0) ? h.getString() : String("");
  h.end();
  Serial.printf("[WEB] %s -> HTTP %d\n", viec.c_str(), code);
  return code == 200;
}

bool webLogin(const String& pin){
  String r; StaticJsonDocument<128> b; b["pin"] = pin;
  String body; serializeJson(b, body);
  if(!webGoi("login", body, r)){ bao("Loi mang", COL_DO, "Khong goi duoc web", ""); delay(1600); return false; }
  StaticJsonDocument<512> d;
  if(deserializeJson(d, r)){ bao("Web tra rac", COL_DO, "", ""); delay(1600); return false; }
  if(!(d["ok"] | false)){
    String e = String((const char*)(d["error"] | "Sai PIN"));
    bao("Dang nhap loi", COL_DO, e.substring(0, 34), ""); delay(2000); return false;
  }
  g_token = String((const char*)(d["token"] | ""));
  return g_token.length() > 0;
}

// ═══════════════════════════════ CHẾ ĐỘ CHỐT CA ══════════════════════════════
void cheDoChotCa(){
  // 1) Dò AP để biết mã ghế (khỏi gõ tay)
  quetAp(true);
  if(g_dsN == 0) return;
  int sel = chonGheTuDanhSach("Chon ghe de CHOT");
  if(sel < 0) return;
  String ma = g_ds[sel].ma;

  // 2) Nối Internet + đăng nhập
  if(!noiInternet()) return;
  if(g_token.length() == 0){
    long pin = banPhimSo("Nhap PIN", 0, 0);
    if(pin < 0) return;
    if(!webLogin(String(pin))) return;
  }

  // 3) chot_xem: hiện tiền hệ thống ghi nhận
  bao("Dang lay so lieu...", COL_ACC, "Ghe " + ma, "");
  { String r; StaticJsonDocument<192> b; b["token"] = g_token; b["ma_may"] = ma;
    String body; serializeJson(b, body);
    if(!webGoi("chot_xem", body, r)){ bao("Loi mang", COL_DO, "chot_xem", ""); delay(1600); return; }
    StaticJsonDocument<1024> d;
    if(deserializeJson(d, r)){ bao("Web tra rac", COL_DO, "", ""); delay(1600); return; }
    if(!(d["ok"] | false)){
      String e = String((const char*)(d["error"] | "Loi"));
      bao("Khong chot duoc", COL_DO, e.substring(0, 34), ""); delay(2400); return;
    }
    String coso = String((const char*)(d["coso"] | ""));
    long hethong = (long)(d["theo_he_thong"] | 0);   // tiền mặt hệ thống ghi nhận từ lần chốt trước
    long csTruoc = (long)(d["chi_so_truoc"] | 0);
    int  donVi   = (int)(d["don_vi"] | 5000);
    int  lanDau  = (int)(d["lan_dau"] | 0);

    /* QR hôm nay: gọi so_may cho có "QR bao nhiêu / tiền mặt bao nhiêu" như yêu cầu.
       BEST-EFFORT — so_may là việc chỉ quản trị làm được; nhân viên thu thường chỉ
       chốt được ca, nên thất bại thì bỏ qua, không chặn luồng chốt. */
    long qrHomNay = -1;
    { String rq; StaticJsonDocument<192> bq; bq["token"] = g_token; bq["ma_may"] = ma;
      String bodyq; serializeJson(bq, bodyq);
      if(webGoi("so_may", bodyq, rq)){
        StaticJsonDocument<1024> dq;
        if(!deserializeJson(dq, rq) && (dq["ok"] | false))
          qrHomNay = (long)(dq["hom_nay"]["qr"] | 0);
      } }

    // Màn tóm tắt trước khi đếm
    tft.fillScreen(COL_BG); tft.setTextDatum(TL_DATUM);
    tft.setTextColor(COL_ACC, COL_BG); tft.drawString("Ghe " + ma, 14, 12, 4);
    tft.setTextColor(COL_XAM, COL_BG); tft.drawString(coso, 14, 46, 2);
    tft.setTextColor(TFT_WHITE, COL_BG);
    tft.drawString("Tien mat (he thong): " + String(hethong) + " d", 14, 72, 2);
    if(qrHomNay >= 0) tft.drawString("QR hom nay: " + String(qrHomNay) + " d", 14, 94, 2);
    tft.drawString("Chi so truoc: " + String(csTruoc) + "  |  don vi " + String(donVi) + "d", 14, 116, 2);
    tft.setTextColor(COL_OK, COL_BG);
    tft.drawString(lanDau ? "Lan chot dau tien - cham de dem" : "Cham de dem tien >>", 14, 148, 2);
    { int x, y; cho4Cham(x, y, 0); }

    // 4) Nhân viên đếm tiền -> nhập chỉ số + tiền đếm
    long chiSo = banPhimSo("Chi so tren may dem", csTruoc, 0);
    if(chiSo < 0) return;
    long tienDem = banPhimSo("Tien mat dem duoc (d)", 0, 0);
    if(tienDem < 0) return;

    // 5) chot_luu
    bao("Dang chot ca...", COL_ACC, "Ghe " + ma, "");
    String r2; StaticJsonDocument<256> b2;
    b2["token"] = g_token; b2["ma_may"] = ma; b2["chi_so"] = chiSo; b2["tien_dem"] = tienDem;
    String body2; serializeJson(b2, body2);
    if(!webGoi("chot_luu", body2, r2)){ bao("Loi mang", COL_DO, "chot_luu", ""); delay(1600); return; }
    StaticJsonDocument<1024> d2;
    if(deserializeJson(d2, r2)){ bao("Web tra rac", COL_DO, "", ""); delay(1600); return; }
    if(!(d2["ok"] | false)){
      String e = String((const char*)(d2["error"] | "Loi"));
      bao("Chot that bai", COL_DO, e.substring(0, 34), ""); delay(2600); return;
    }
    long theoMay = (long)(d2["theo_may"] | 0);
    long lechDem = (long)(d2["lech_dem"] | 0);
    String cb = String((const char*)(d2["canh_bao"] | ""));
    tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
    tft.setTextColor(COL_OK, COL_BG); tft.drawString("DA CHOT CA", 160, 40, 4);
    tft.setTextColor(TFT_WHITE, COL_BG);
    tft.drawString("Ghe " + ma + "  |  theo may " + String(theoMay) + " d", 160, 90, 2);
    tft.drawString("Dem: " + String(tienDem) + " d   lech: " + String(lechDem) + " d", 160, 118, 2);
    if(cb.length()){ tft.setTextColor(COL_DO, COL_BG); tft.drawString(cb.substring(0, 40), 160, 150, 2); }
    tft.setTextColor(COL_XAM, COL_BG); tft.drawString("Cham de tiep tuc", 160, 190, 2);
    int x, y; cho4Cham(x, y, 0);
  }
}

// ═══════════════════════════════ MÀN CHÍNH ═══════════════════════════════════
void manChinh(){
  tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(COL_ACC, COL_BG); tft.drawString("MAY TRAM POSH", 160, 24, 4);
  // 2 nút lớn
  tft.fillRoundRect(24, 60, 272, 70, 10, 0x2145);
  tft.setTextColor(TFT_WHITE, 0x2145); tft.drawString("NAP FIRMWARE", 160, 95, 4);
  tft.fillRoundRect(24, 148, 272, 70, 10, 0x0341);
  tft.setTextColor(TFT_WHITE, 0x0341); tft.drawString("THU TIEN / CHOT CA", 160, 183, 4);
}

void setup(){
  Serial.begin(115200); delay(200);
  Serial.println("\n\n=== " FW_VERSION " ===");
  pinMode(BL_PIN, OUTPUT); digitalWrite(BL_PIN, HIGH);

  tft.init(); tft.setRotation(1);
  tsSPI.begin(T_CLK, T_DO, T_DIN, T_CS);
  ts.begin(tsSPI); ts.setRotation(1);
  manChao(); delay(1500);

  // Thẻ SD dùng VSPI mặc định (SCK18/MISO19/MOSI23) — tách khỏi HSPI của cảm ứng.
  SPI.begin(18, 19, 23, SD_CS);
  g_sdOk = SD.begin(SD_CS);
  Serial.printf("[SD] %s\n", g_sdOk ? "OK" : "KHONG doc duoc the");

  manChinh();
}

void loop(){
  int x, y;
  if(!getTouch(x, y)){ delay(20); return; }
  // đợi nhả
  { int lx, ly; unsigned long t = millis(); while(getTouch(lx, ly) && millis() - t < 1200) delay(10); }
  delay(40);

  if(x >= 24 && x <= 296 && y >= 60 && y <= 130){        // NẠP FIRMWARE
    cheDoOTA();
  } else if(x >= 24 && x <= 296 && y >= 148 && y <= 218){ // THU TIỀN / CHỐT CA
    cheDoChotCa();
  }
  manChinh();
}
