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

#include <WiFi.h>          // chỉ để QUÉT/NỐI AP ghế lúc NẠP OTA — Internet đi qua 4G
#include <SD.h>
#include <SPI.h>
#include <TFT_eSPI.h>
#include <XPT2046_Touchscreen.h>
#include <ArduinoJson.h>
#include <Preferences.h>   // nhớ PIN đăng nhập qua mất điện (khỏi gõ lại mỗi lần)
#include "font_viet.h"     // font VLW tiếng Việt (vietLon 22 bold / vietVua 16) — chữ có dấu

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
void  cheDoKiemTra();   // xem chỉ số như chốt ca nhưng CHỈ XEM, không ghi
void  cheDoChotTien();  // chốt tiền: đọc chỉ số + MỐC từ ghế qua AP, tính kỳ OFFLINE, dời mốc, đẩy web sau
struct ChiSoGhe;
struct ChotKetQua;
bool  docChiSoGhe(const ApGhe& g, ChiSoGhe& cs);          // GET /chotso: chỉ số + mốc chốt trước
bool  doiMocGhe(const ApGhe& g, const String& maLan, ChotKetQua& kq);  // POST /chotmoc: dời mốc, lấy kỳ
void  themChotQueue(const String& line);                  // xếp 1 bản ghi chốt vào NVS chờ đẩy web
void  flushChotQueue();                                   // có mạng -> đẩy các bản ghi chốt đang chờ
int   demChotQueue();                                     // số bản ghi còn chờ đẩy
// 4G (bê từ esp32_ghe_massage.ino)
void   modemPowerOn();
bool   atProbe(int txPin, int rxPin, long baud);
String atSend(const char* cmd, unsigned long to);
String atWait(const char* token, unsigned long to);
bool   net4gDiag();
bool   net4gConnect();
int    net4gReadStart(int want);
int    net4gPostOpen(const String& url, const String& body, int* datalen);
bool   bat4G();

/* ═══════════════════════════════ CẤU HÌNH ═══════════════════════════════════
 * Máy trạm này = CYD + A7680C (4G) GIỐNG con ghế. Internet cho phần CHỐT CA đi
 * qua 4G, KHÔNG cần WiFi Internet (WiFi radio chỉ dùng để nối AP ghế lúc nạp OTA).
 * Chỉ cần sửa WEB_BASE cho đúng tên miền web rồi nạp lại.
 * ─────────────────────────────────────────────────────────────────────────── */
#define WEB_BASE      "https://khmatrix.com/ghe/" // trang /ghe của web (có dấu / cuối)

// Khớp secrets/otaPhucVu của con ghế (esp32_ghe_massage.ino: SEC_AP_PASS, OTA_AP_PREFIX)
#define GHE_AP_PREFIX "POSH_QR-"
#define GHE_AP_PASS   "12345678"
#define OTA_KEY       "12345678"                // == SEC_AP_PASS: header X-OTA-Key
IPAddress GHE_IP(192, 168, 4, 1);
const uint16_t GHE_PORT = 80;
const char*  FW_PATH   = "/firmware.bin";       // tên file .bin trên thẻ SD máy trạm
const int    NEAR_RSSI = -90;                   // gần như không lọc sóng (để chắc thấy ghế; siết lại sau khi ok)

// ── A7680C (4G) — KHỚP con ghế (SIM_TX_PIN/SIM_RX_PIN/SIM_PWRKEY/SIM_APN) ──────
const char* SIM_APN     = "v-internet";         // Viettel; đổi nếu SIM nhà mạng khác
#define SIM_TX_PIN   4
#define SIM_RX_PIN   16
#define SIM_PWRKEY   17
#define USE_PWRKEY   false                       // con ghế để false (cấp nguồn cứng); giữ giống

// ── Phần cứng CYD (khớp esp32_ghe_massage.ino) ────────────────────────────────
#define T_CS   33
#define T_IRQ  36
#define T_CLK  25
#define T_DIN  32
#define T_DO   39
int TS_MINX = 200, TS_MAXX = 3700, TS_MINY = 240, TS_MAXY = 3800;
#define SD_CS  5                                 // thẻ SD trên CYD: SPI SCK18/MISO19/MOSI23
#define BL_PIN 21                                // đèn nền
#define FW_VERSION "may-tram 2026-09-04a (CHOT TIEN offline: moc tren ghe, /chotmoc + hang cho day web)"
// ═════════════════════════════════════════════════════════════════════════════

TFT_eSPI tft = TFT_eSPI();
SPIClass tsSPI = SPIClass(HSPI);
XPT2046_Touchscreen ts(T_CS, T_IRQ);
bool   g_sdOk = false;
String g_token = "";                            // token web sau khi login (phần chốt ca) - chỉ RAM
Preferences prefsTram;                          // NVS: nhớ PIN đăng nhập
String g_pinLuu = "";                           // PIN đã lưu (nạp trong setup) - tự đăng nhập lại
int    g_okCount = 0, g_failCount = 0;
// 4G
int  g_simTx = SIM_TX_PIN, g_simRx = SIM_RX_PIN; long g_simBaud = 115200;
volatile bool g_4gReady = false;
volatile int  g_netFails = 0;

// Một AP ghế dò được
struct ApGhe { String ssid; String ma; String bssid; int rssi; int kenh; };
ApGhe g_ds[24]; int g_dsN = 0;
/* Chỉ số + mốc đọc từ ghế qua /chotso. kỳ này = tm-tmc, qr-qrc (tính ngay, khỏi web). */
struct ChiSoGhe { long tm = 0, qr = 0, tmc = 0, qrc = 0; String lan = ""; };
/* Kết quả /chotmoc (ghế trả về sau khi dời mốc) — là con số CHỐT CHÍNH THỨC. */
struct ChotKetQua { long tm = 0, qr = 0, tmc = 0, qrc = 0, tmKy = 0, qrKy = 0; };

/* ══════════════════════════════════════════════════════════════════════════════════════════
 * PREFETCH CẢ CƠ SỞ.
 *
 * Anh Thắng 26/08/2026: *"khi lấy máy đầu tiên thì mã trạm sẽ phải lấy sẵn thông tin của cơ sở
 * đó trước. Để máy sau chỉ bấm phát ăn ngay."*
 *
 * Ghế ĐẦU tiên chốt: sau khi login, gọi MỘT lượt `chot_coso` -> tải số liệu chốt của MỌI ghế
 * cùng cơ sở vào mảng này. Ghế SAU: dò AP lấy mã -> tra thẳng trong mảng, hiện ngay, không phải
 * chờ thêm 3–6 giây một lượt AT-HTTP nữa.
 *
 * ⚠️ CHỈ ĐỂ HIỆN, KHÔNG ĐỂ GHI. `chot_luu` vẫn tự đóng mốc chỉ số theo giờ máy chủ lúc chốt thật
 *    (xem VHG_Quy::chot) — nên tiền vào ngăn GIỮA lúc prefetch và lúc chốt không bị bỏ sót. Con
 *    số trong mảng này chỉ là cái nhân viên nhìn để đối chiếu.
 * ═════════════════════════════════════════════════════════════════════════════════════════ */
struct GheChot {
  String ma, coso;
  long   hethong;    // tiền mặt hệ thống ghi nhận từ lần chốt trước (theo_he_thong)
  long   csTruoc;    // chỉ số máy đếm lần chốt trước
  int    donVi;      // đồng / một đơn vị chỉ số
  int    lanDau;     // 1 = ghế chưa chốt bao giờ
};
GheChot g_chot[40]; int g_chotN = 0;   // bộ nhớ prefetch (đủ cho một cơ sở lớn)

/* Tra một ghế trong bộ nhớ prefetch. -1 nếu chưa có. */
int chotTimTrongCache(const String& ma){
  for(int i = 0; i < g_chotN; i++) if(g_chot[i].ma == ma) return i;
  return -1;
}

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
/* Vẽ một dòng chữ TIẾNG VIỆT CÓ DẤU bằng font VLW. Tải/gỡ font ngay trong hàm cho tiện gọi.
   veLon = bold 22 (tiêu đề/nút), veVua = 16 (dòng phụ). datum mặc định canh giữa. */
void veLon(const String& s, int x, int y, uint16_t fg, uint16_t bg, uint8_t datum = MC_DATUM){
  tft.loadFont(vietLon); tft.setTextDatum(datum); tft.setTextColor(fg, bg);
  tft.drawString(s, x, y); tft.unloadFont();
}
void veVua(const String& s, int x, int y, uint16_t fg, uint16_t bg, uint8_t datum = MC_DATUM){
  tft.loadFont(vietVua); tft.setTextDatum(datum); tft.setTextColor(fg, bg);
  tft.drawString(s, x, y); tft.unloadFont();
}

void manChao(){
  tft.fillScreen(COL_BG); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(0xCE40, COL_BG); tft.setTextSize(2);
  tft.drawString("K&H", 160, 60, 4); tft.setTextSize(1);
  veLon("MÁY TRẠM POSH", 160, 118, COL_ACC, COL_BG);
  veVua("Nạp firmware & Thu tiền", 160, 152, COL_XAM, COL_BG);
}

void bao(const String& l1, uint16_t c1, const String& l2, const String& l3){
  tft.fillScreen(COL_BG);
  veLon(l1, 160, 70, c1, COL_BG);
  if(l2.length()) veVua(l2, 160, 118, TFT_WHITE, COL_BG);
  if(l3.length()) veVua(l3, 160, 148, COL_XAM, COL_BG);
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
  bao("Đang dò ghế...", COL_ACC, "Quét WiFi POSH_QR", "");
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
    bao("Không thấy ghế", COL_DO, "Lại gần hộp QR hơn", "Chạm để dò lại");
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
  if(!g_sdOk){ bao("Chưa có thẻ SD", COL_DO, "Cắm thẻ có firmware.bin", ""); delay(1600); return false; }
  File f = SD.open(FW_PATH, FILE_READ);
  if(!f){ bao("Không thấy file", COL_DO, String(FW_PATH), "Chép .bin vào thẻ trước"); delay(1800); return false; }
  long fsize = f.size();
  if(fsize <= 0){ f.close(); bao("File rỗng", COL_DO, "", ""); delay(1600); return false; }

  bao("Kết nối ghế...", COL_ACC, g.ma, g.bssid);
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(150);
  WiFi.begin(g.ssid.c_str(), GHE_AP_PASS);
  unsigned long t0 = millis();
  while(WiFi.status() != WL_CONNECTED && millis() - t0 < 15000){ delay(250); }
  if(WiFi.status() != WL_CONNECTED){
    f.close(); bao("Nối thất bại", COL_DO, g.ma, "Lại gần hơn rồi thử lại"); delay(1800); return false;
  }

  WiFiClient cl; cl.setTimeout(20000);
  if(!cl.connect(GHE_IP, GHE_PORT)){
    f.close(); bao("Không nối 192.168.4.1", COL_DO, "", ""); WiFi.disconnect(true); delay(1600); return false;
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
  if(ok){ g_okCount++; bao("NẠP XONG", COL_OK, "Ghế " + g.ma + " đang khởi động lại", "OK:" + String(g_okCount) + " Lỗi:" + String(g_failCount)); }
  else  { g_failCount++; bao("NẠP LỖI", COL_DO, "Ghế " + g.ma, "Giữ firmware cũ - thử lại"); }
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

// ══════════════════════════ 4G (bê từ esp32_ghe_massage.ino) ═════════════════
void modemPowerOn(){
  if(USE_PWRKEY){
    pinMode(SIM_PWRKEY, OUTPUT);
    digitalWrite(SIM_PWRKEY, HIGH); delay(200);
    digitalWrite(SIM_PWRKEY, LOW);  delay(1200);
    digitalWrite(SIM_PWRKEY, HIGH);
  }
  Serial.println("[4G] Bat nguon A7680C, cho boot ~12s...");
  delay(12000);
}
bool atProbe(int txPin, int rxPin, long baud){
  Serial2.begin(baud, SERIAL_8N1, rxPin, txPin); delay(300);
  bool ok = false;
  for(int i = 0; i < 3 && !ok; i++){
    while(Serial2.available()) Serial2.read();
    Serial2.print("AT\r\n");
    unsigned long t0 = millis(); String r = "";
    while(millis() - t0 < 800){ while(Serial2.available()) r += (char)Serial2.read(); if(r.indexOf("OK") >= 0){ ok = true; break; } delay(5); }
  }
  Serial2.end(); return ok;
}
String atSend(const char* cmd, unsigned long to){
  while(Serial2.available()) Serial2.read();
  Serial2.print(cmd); Serial2.print("\r\n");
  unsigned long t0 = millis(); String r = "";
  while(millis() - t0 < to){ while(Serial2.available()) r += (char)Serial2.read(); if(r.indexOf("OK") >= 0 || r.indexOf("ERROR") >= 0) break; delay(5); }
  r.replace("\r", " "); r.replace("\n", " "); r.trim(); return r;
}
String atWait(const char* token, unsigned long to){
  unsigned long t0 = millis(); String r = "";
  while(millis() - t0 < to){ while(Serial2.available()) r += (char)Serial2.read(); if(r.indexOf(token) >= 0) break; delay(3); }
  return r;
}
bool net4gDiag(){
  atSend("ATE0", 800);
  Serial.println("[4G] CPIN? -> " + atSend("AT+CPIN?", 2000));
  atSend("AT+CFUN=1", 3000);
  atSend("AT+CTZU=1", 1000);
  atSend("AT+COPS=0", 12000);
  atSend((String("AT+CGDCONT=1,\"IP\",\"") + SIM_APN + "\"").c_str(), 1500);
  bool reg = false;
  for(int i = 0; i < 30 && !reg; i++){
    String e = atSend("AT+CEREG?", 1200);
    String g = atSend("AT+CGREG?", 1200);
    if(e.indexOf(",1") >= 0 || e.indexOf(",5") >= 0 || g.indexOf(",1") >= 0 || g.indexOf(",5") >= 0){ reg = true; break; }
    Serial.printf("[4G] cho dang ky (%ds)\n", i * 2);
    delay(1500);
  }
  if(!reg) return false;
  atSend("AT+CGACT=1,1", 10000);
  atSend("AT+CSSLCFG=\"sslversion\",0,4", 1500);
  atSend("AT+CSSLCFG=\"authmode\",0,0", 1500);
  atSend("AT+CSSLCFG=\"enableSNI\",0,1", 1500);
  atSend("AT+CNTPCID=1", 1000);
  atSend("AT+CNTP=\"pool.ntp.org\",28", 2000);
  Serial.println("[4G] NTP: " + atSend("AT+CNTP", 9000));
  Serial.println("[4G-DIAG] IP=" + atSend("AT+CGPADDR=1", 3000) + " CSQ=" + atSend("AT+CSQ", 1000));
  return true;
}
bool net4gConnect(){
  g_4gReady = false; modemPowerOn();
  long bauds[] = {115200, 9600}; bool found = false;
  for(int bi = 0; bi < 2 && !found; bi++){
    if(atProbe(SIM_TX_PIN, SIM_RX_PIN, bauds[bi])){ g_simTx = SIM_TX_PIN; g_simRx = SIM_RX_PIN; g_simBaud = bauds[bi]; found = true; }
    else if(atProbe(SIM_RX_PIN, SIM_TX_PIN, bauds[bi])){ g_simTx = SIM_RX_PIN; g_simRx = SIM_TX_PIN; g_simBaud = bauds[bi]; found = true; }
  }
  if(!found){ Serial.println("[4G] Module KHONG tra AT -> kiem nguon/PWRKEY/day/SIM"); return false; }
  Serial.printf("[4G] AT OK: tx=%d rx=%d @%ld\n", g_simTx, g_simRx, g_simBaud);
  Serial2.begin(g_simBaud, SERIAL_8N1, g_simRx, g_simTx); delay(300);
  if(net4gDiag()){ g_4gReady = true; Serial.println("[4G] SAN SANG (LTE)"); return true; }
  Serial.println("[4G] Chua dang ky mang"); return false;
}
int net4gReadStart(int want){
  Serial2.print("AT+HTTPREAD=0,"); Serial2.print(want); Serial2.print("\r\n");
  String hdr = ""; unsigned long t0 = millis(); bool sawTag = false, done = false;
  while(!done && millis() - t0 < 8000){
    while(Serial2.available()){
      char c = (char)Serial2.read(); t0 = millis(); hdr += c;
      if(!sawTag){ if(hdr.endsWith("+HTTPREAD:")){ sawTag = true; hdr = ""; } }
      else if(c == '\n'){ done = true; break; }
    }
    if(!done) delay(2);
  }
  if(!sawTag) return -1;
  int lc = hdr.lastIndexOf(','); int st = (lc >= 0) ? lc + 1 : 0;
  return hdr.substring(st).toInt();
}
/* POST JSON qua AT-HTTP, giữ phiên để đọc thân trả về. Trả HTTP status; datalen = số byte thân. */
int net4gPostOpen(const String& url, const String& body, int* datalen){
  if(datalen) *datalen = 0;
  if(!g_4gReady) return 0;
  Serial2.print("AT+HTTPTERM\r\n"); delay(120); while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); atWait("OK", 6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK", 2000);
  Serial2.print("AT+HTTPPARA=\"SSLCFG\",0\r\n"); atWait("OK", 1500);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); atWait("OK", 3000);
  Serial2.print("AT+HTTPPARA=\"CONTENT\",\"application/json\"\r\n"); atWait("OK", 2000);
  Serial2.print("AT+HTTPDATA="); Serial2.print(body.length()); Serial2.print(",20000\r\n");
  if(atWait("DOWNLOAD", 6000).indexOf("DOWNLOAD") < 0){ Serial2.print("AT+HTTPTERM\r\n"); return 0; }
  Serial2.print(body); atWait("OK", 20000);
  Serial2.print("AT+HTTPACTION=1\r\n");
  String r = atWait("+HTTPACTION:", 40000);
  int status = 0, dl = 0, p = r.indexOf("+HTTPACTION:");
  if(p >= 0){ int c1 = r.indexOf(',', p), c2 = (c1 >= 0) ? r.indexOf(',', c1 + 1) : -1;
              if(c1 >= 0 && c2 >= 0){ status = r.substring(c1 + 1, c2).toInt(); dl = r.substring(c2 + 1).toInt(); } }
  if(status != 200){ r.replace("\r", " "); r.replace("\n", " "); Serial.println("[HTTP-RAW] st=" + String(status) + " | " + r); }
  if(status != 200) return status;
  if(datalen) *datalen = dl;
  return 200;
}

// ══════════════════════════ WEB (phần CHỐT CA, đi qua 4G) ════════════════════
/* Bật 4G (lười: bật 1 lần, giữ g_4gReady). Trước khi dùng 4G thì TẮT WiFi cho radio
   khỏi vướng — phần dò AP ghế đã xong trước đó rồi. */
bool bat4G(){
  if(g_4gReady) return true;
  WiFi.disconnect(true); WiFi.mode(WIFI_OFF); delay(100);
  bao("Đang bật 4G...", COL_ACC, "Chờ ~15-30s", "Lần đầu hơi lâu");
  return net4gConnect();
}
bool noiInternet(){
  if(!bat4G()){ bao("4G chưa sẵn sàng", COL_DO, "Kiểm SIM / sóng", "Xem log Serial [4G]"); delay(2000); return false; }
  return true;
}

/* POST JSON tới WEB_BASE?api=<viec> qua 4G. Trả true nếu HTTP 200; thân trả về ở raOut. */
bool webGoi(const String& viec, const String& body, String& raOut){
  raOut = "";
  String url = String(WEB_BASE);
  url += (url.indexOf('?') < 0 ? "?" : "&");
  url += "api=" + viec;
  int dl = 0, st = net4gPostOpen(url, body, &dl);
  if(st != 200){
    Serial2.print("AT+HTTPTERM\r\n"); atWait("OK", 1500);
    Serial.printf("[WEB] %s -> HTTP %d (loi)\n", viec.c_str(), st);
    return false;
  }
  int n = net4gReadStart(dl);
  if(n > 0){ raOut.reserve(n + 4); int got = 0; unsigned long t0 = millis();
    while(got < n && millis() - t0 < 12000){ while(Serial2.available() && got < n){ raOut += (char)Serial2.read(); got++; t0 = millis(); } delay(1); } }
  atWait("OK", 2000); Serial2.print("AT+HTTPTERM\r\n"); atWait("OK", 1500);
  Serial.printf("[WEB] %s -> HTTP 200, %d byte\n", viec.c_str(), raOut.length());
  return true;
}

bool webLogin(const String& pin){
  String r; StaticJsonDocument<128> b; b["pin"] = pin;
  String body; serializeJson(b, body);
  if(!webGoi("login", body, r)){ bao("Lỗi mạng", COL_DO, "Không gọi được web", ""); delay(1600); return false; }
  StaticJsonDocument<512> d;
  if(deserializeJson(d, r)){ bao("Web trả rác", COL_DO, "", ""); delay(1600); return false; }
  if(!(d["ok"] | false)){
    String e = String((const char*)(d["error"] | "Sai PIN"));
    bao("Đăng nhập lỗi", COL_DO, e.substring(0, 34), ""); delay(2000); return false;
  }
  g_token = String((const char*)(d["token"] | ""));
  return g_token.length() > 0;
}

/* PREFETCH: một lượt gọi `chot_coso` -> tải số liệu chốt của MỌI ghế cùng cơ sở vào g_chot[].
   Cơ sở lấy theo PHIÊN ở máy chủ (không gửi lên) — người gắn cơ sở chỉ tải được cơ sở mình. */
bool prefetchCoSo(){
  String r; StaticJsonDocument<128> b; b["token"] = g_token;
  String body; serializeJson(b, body);
  if(!webGoi("chot_coso", body, r)) return false;
  /* Cả cơ sở có thể vài chục ghế -> để trên HEAP (DynamicJsonDocument), đừng ngốn stack. */
  DynamicJsonDocument d(12288);
  if(deserializeJson(d, r)){ Serial.println("[CHOT] prefetch: web tra rac"); return false; }
  if(!(d["ok"] | false)) return false;
  JsonArray arr = d["ghe"].as<JsonArray>();
  g_chotN = 0;
  for(JsonObject g : arr){
    if(g_chotN >= 40) break;
    g_chot[g_chotN].ma      = String((const char*)(g["ma_may"] | ""));
    g_chot[g_chotN].coso    = String((const char*)(g["coso"] | ""));
    g_chot[g_chotN].hethong = (long)(g["theo_he_thong"] | 0);
    g_chot[g_chotN].csTruoc = (long)(g["chi_so_truoc"] | 0);
    g_chot[g_chotN].donVi   = (int)(g["don_vi"] | 5000);
    g_chot[g_chotN].lanDau  = (int)(g["lan_dau"] | 0);
    g_chotN++;
  }
  Serial.printf("[CHOT] prefetch co so: %d ghe\n", g_chotN);
  return g_chotN > 0;
}

/* ĐẢM BẢO ĐÃ ĐĂNG NHẬP — tự dùng PIN đã lưu, chỉ hỏi khi chưa lưu hoặc PIN sai.
 *
 * Anh Thắng 27/08/2026: *"Máy trạm cho tính năng tự lưu lại mật khẩu đăng nhập"*.
 *
 * PIN lưu trong NVS (qua mất điện vẫn còn). Token thì KHÔNG lưu — token có hạn, hết hạn là hỏng;
 * giữ PIN rồi tự login lại mới chắc. Login bằng PIN lưu mà trượt (admin đổi PIN) -> xoá PIN cũ,
 * hỏi lại rồi lưu PIN mới. Trả false nếu người dùng bấm huỷ ở bàn phím. */
bool damBaoDangNhap(){
  if(g_token.length()) return true;
  if(g_pinLuu.length()){
    bao("Đang nhập tự động...", COL_ACC, "PIN đã lưu", "");
    if(webLogin(g_pinLuu)) return true;
    g_pinLuu = ""; prefsTram.remove("pin");     // PIN lưu không còn đúng -> bỏ, hỏi lại
  }
  long pin = banPhimSo("Nhap PIN", 0, 0);
  if(pin < 0) return false;
  if(!webLogin(String(pin))) return false;
  g_pinLuu = String(pin); prefsTram.putString("pin", g_pinLuu);   // lưu cho lần sau
  return true;
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
  if(!damBaoDangNhap()) return;

  // 3) Số liệu chốt: LẤY TỪ BỘ NHỚ PREFETCH nếu có; chưa có thì tải CẢ CƠ SỞ một lượt.
  //    Ghế đầu tiên chốt sẽ tải cả cơ sở; ghế sau tra thẳng bộ nhớ, hiện ngay.
  { String coso; long hethong = 0, csTruoc = 0; int donVi = 5000, lanDau = 0;
    int ci = chotTimTrongCache(ma);
    if(ci < 0){
      bao("Đang tải cả cơ sở...", COL_ACC, "Lần đầu - chờ chút", "");
      prefetchCoSo();
      ci = chotTimTrongCache(ma);
    }
    if(ci >= 0){
      // Trúng bộ nhớ -> hiện NGAY, không gọi thêm mạng.
      coso    = g_chot[ci].coso;
      hethong = g_chot[ci].hethong;
      csTruoc = g_chot[ci].csTruoc;
      donVi   = g_chot[ci].donVi;
      lanDau  = g_chot[ci].lanDau;
    } else {
      // Không nằm trong cơ sở của mình (hoặc prefetch trượt) -> hỏi riêng ghế này như cũ.
      bao("Đang lấy số liệu...", COL_ACC, "Ghế " + ma, "");
      String r; StaticJsonDocument<192> b; b["token"] = g_token; b["ma_may"] = ma;
      String body; serializeJson(b, body);
      if(!webGoi("chot_xem", body, r)){ bao("Lỗi mạng", COL_DO, "chot_xem", ""); delay(1600); return; }
      StaticJsonDocument<1024> d;
      if(deserializeJson(d, r)){ bao("Web trả rác", COL_DO, "", ""); delay(1600); return; }
      if(!(d["ok"] | false)){
        String e = String((const char*)(d["error"] | "Loi"));
        bao("Không chốt được", COL_DO, e.substring(0, 34), ""); delay(2400); return;
      }
      coso    = String((const char*)(d["coso"] | ""));
      hethong = (long)(d["theo_he_thong"] | 0);   // tiền mặt hệ thống ghi nhận từ lần chốt trước
      csTruoc = (long)(d["chi_so_truoc"] | 0);
      donVi   = (int)(d["don_vi"] | 5000);
      lanDau  = (int)(d["lan_dau"] | 0);
    }

    /* QR hôm nay: gọi so_may cho có "QR bao nhiêu / tiền mặt bao nhiêu" như yêu cầu.
       BEST-EFFORT — so_may là việc chỉ quản trị làm được; nhân viên thu thường chỉ
       chốt được ca, nên thất bại thì bỏ qua, không chặn luồng chốt.
       🔴 CHỈ GỌI KHI KHÔNG TRÚNG BỘ NHỚ (ci<0). Trúng bộ nhớ nghĩa là ghế thứ 2 trở đi — mục
          đích prefetch là "bấm phát ăn ngay", nên đừng thêm một lượt AT-HTTP 3–6 giây cho một
          con số phụ mà nhân viên phần lớn không có quyền lấy. */
    long qrHomNay = -1;
    if(ci < 0){
      String rq; StaticJsonDocument<192> bq; bq["token"] = g_token; bq["ma_may"] = ma;
      String bodyq; serializeJson(bq, bodyq);
      if(webGoi("so_may", bodyq, rq)){
        StaticJsonDocument<1024> dq;
        if(!deserializeJson(dq, rq) && (dq["ok"] | false))
          qrHomNay = (long)(dq["hom_nay"]["qr"] | 0);
      } }

    // Màn tóm tắt trước khi đếm
    tft.fillScreen(COL_BG);
    veLon("Ghế " + ma, 14, 20, COL_ACC, COL_BG, TL_DATUM);
    veVua(coso, 14, 48, COL_XAM, COL_BG, TL_DATUM);
    veVua("Tiền mặt (hệ thống): " + String(hethong) + " đ", 14, 74, TFT_WHITE, COL_BG, TL_DATUM);
    if(qrHomNay >= 0) veVua("QR hôm nay: " + String(qrHomNay) + " đ", 14, 98, TFT_WHITE, COL_BG, TL_DATUM);
    veVua("Chỉ số trước: " + String(csTruoc) + "  |  đơn vị " + String(donVi) + "đ", 14, 122, TFT_WHITE, COL_BG, TL_DATUM);
    veVua(lanDau ? "Lần chốt đầu tiên - chạm để đếm" : "Chạm để đếm tiền >>", 14, 150, COL_OK, COL_BG, TL_DATUM);
    { int x, y; cho4Cham(x, y, 0); }

    // 4) Nhân viên đếm tiền -> nhập chỉ số + tiền đếm
    long chiSo = banPhimSo("Chi so tren may dem", csTruoc, 0);
    if(chiSo < 0) return;
    long tienDem = banPhimSo("Tien mat dem duoc (d)", 0, 0);
    if(tienDem < 0) return;

    // 5) chot_luu
    bao("Đang chốt ca...", COL_ACC, "Ghế " + ma, "");
    String r2; StaticJsonDocument<256> b2;
    b2["token"] = g_token; b2["ma_may"] = ma; b2["chi_so"] = chiSo; b2["tien_dem"] = tienDem;
    String body2; serializeJson(b2, body2);
    if(!webGoi("chot_luu", body2, r2)){ bao("Loi mang", COL_DO, "chot_luu", ""); delay(1600); return; }
    StaticJsonDocument<1024> d2;
    if(deserializeJson(d2, r2)){ bao("Web trả rác", COL_DO, "", ""); delay(1600); return; }
    if(!(d2["ok"] | false)){
      String e = String((const char*)(d2["error"] | "Loi"));
      bao("Chốt thất bại", COL_DO, e.substring(0, 34), ""); delay(2600); return;
    }
    /* Chốt xong -> số liệu prefetch của ghế này đã cũ (mốc chỉ số vừa đổi). Bỏ khỏi bộ nhớ để
       nếu có chốt lại ghế này trong buổi thì tải lại số mới, không hiện con số đã lỗi thời. */
    if(ci >= 0){ g_chot[ci].ma = ""; }
    long theoMay = (long)(d2["theo_may"] | 0);
    long lechDem = (long)(d2["lech_dem"] | 0);
    String cb = String((const char*)(d2["canh_bao"] | ""));
    tft.fillScreen(COL_BG);
    veLon("ĐÃ CHỐT CA", 160, 40, COL_OK, COL_BG);
    veVua("Ghế " + ma + "  |  theo máy " + String(theoMay) + " đ", 160, 88, TFT_WHITE, COL_BG);
    veVua("Đếm: " + String(tienDem) + " đ   lệch: " + String(lechDem) + " đ", 160, 114, TFT_WHITE, COL_BG);
    if(cb.length()) veVua(cb.substring(0, 34), 160, 146, COL_DO, COL_BG);
    veVua("Chạm để tiếp tục", 160, 188, COL_XAM, COL_BG);
    int x, y; cho4Cham(x, y, 0);
  }
}

// ═══════════════════════════════ KIỂM TRA CHỈ SỐ (CHỈ XEM) ═══════════════════
/* Anh Thắng 27/08/2026: *"Kiểm tra chỉ số máy (như thu tiền nhưng chỉ xem không chốt) để quản
 * lý đi kiểm tra chỉ số"*.
 *
 * 🔴 KHÁC CHỐT CA Ở ĐÚNG MỘT ĐIỂM: KHÔNG gọi `chot_luu`. Quản lý đi rà chỉ số các máy mà không
 *    được vô tình đóng mốc — đóng mốc nhầm là cắt quãng của người thu thật, và số tiền của họ
 *    hôm sau tự hụt đi. Nên đường này KHÔNG có nút lưu, KHÔNG đụng vào cơ sở dữ liệu.
 *
 * Cho nhập chỉ số hiện tại để XEM chênh ước tính (theo máy đếm) so với lần chốt trước — con số
 * để đối chiếu tại chỗ, không ghi đi đâu cả. */
void cheDoKiemTra(){
  quetAp(true);
  if(g_dsN == 0) return;
  int sel = chonGheTuDanhSach("Chon ghe KIEM TRA");
  if(sel < 0) return;
  String ma = g_ds[sel].ma;

  if(!noiInternet()) return;
  if(!damBaoDangNhap()) return;

  /* Lấy số liệu: dùng chung bộ nhớ prefetch với chốt ca -> đi rà nhiều máy vẫn nhanh. */
  String coso; long hethong = 0, csTruoc = 0; int donVi = 5000, lanDau = 0;
  int ci = chotTimTrongCache(ma);
  if(ci < 0){
    bao("Đang tải cả cơ sở...", COL_ACC, "Lần đầu - chờ chút", "");
    prefetchCoSo();
    ci = chotTimTrongCache(ma);
  }
  if(ci >= 0){
    coso = g_chot[ci].coso; hethong = g_chot[ci].hethong; csTruoc = g_chot[ci].csTruoc;
    donVi = g_chot[ci].donVi; lanDau = g_chot[ci].lanDau;
  } else {
    bao("Đang lấy số liệu...", COL_ACC, "Ghế " + ma, "");
    String r; StaticJsonDocument<192> b; b["token"] = g_token; b["ma_may"] = ma;
    String body; serializeJson(b, body);
    if(!webGoi("chot_xem", body, r)){ bao("Lỗi mạng", COL_DO, "chot_xem", ""); delay(1600); return; }
    StaticJsonDocument<1024> d;
    if(deserializeJson(d, r)){ bao("Web trả rác", COL_DO, "", ""); delay(1600); return; }
    if(!(d["ok"] | false)){
      String e = String((const char*)(d["error"] | "Loi"));
      bao("Không xem được", COL_DO, e.substring(0, 34), ""); delay(2400); return;
    }
    coso = String((const char*)(d["coso"] | "")); hethong = (long)(d["theo_he_thong"] | 0);
    csTruoc = (long)(d["chi_so_truoc"] | 0); donVi = (int)(d["don_vi"] | 5000);
    lanDau = (int)(d["lan_dau"] | 0);
  }

  // Màn tóm tắt (CHỈ XEM)
  tft.fillScreen(COL_BG);
  veLon("KIỂM TRA - Ghế " + ma, 14, 20, COL_ACC, COL_BG, TL_DATUM);
  veVua(coso, 14, 48, COL_XAM, COL_BG, TL_DATUM);
  veVua("Chỉ số lần chốt trước: " + String(csTruoc), 14, 74, TFT_WHITE, COL_BG, TL_DATUM);
  veVua("Tiền mặt hệ thống ghi: " + String(hethong) + " đ", 14, 98, TFT_WHITE, COL_BG, TL_DATUM);
  veVua("Đơn vị: " + String(donVi) + " đ / 1 chỉ số", 14, 122, TFT_WHITE, COL_BG, TL_DATUM);
  if(lanDau) veVua("Ghế chưa chốt lần nào", 14, 146, COL_OK, COL_BG, TL_DATUM);
  veVua("Chạm để nhập chỉ số hiện tại (xem chênh)", 14, 172, COL_OK, COL_BG, TL_DATUM);
  veVua("Huỷ ở bàn phím = thoát, KHÔNG chốt", 14, 196, COL_XAM, COL_BG, TL_DATUM);
  { int cx, cy; cho4Cham(cx, cy, 0); }

  // Nhập chỉ số hiện tại để XEM chênh ước tính (KHÔNG lưu)
  long chiSo = banPhimSo("Chi so hien tai (chi xem)", csTruoc, 0);
  if(chiSo < 0) return;   // huỷ -> thoát, không ghi gì
  long chenh   = lanDau ? 0 : (chiSo - csTruoc);
  long tienUoc = chenh > 0 ? chenh * donVi : 0;

  tft.fillScreen(COL_BG);
  veLon("KIỂM TRA CHỈ SỐ", 160, 30, COL_ACC, COL_BG);
  veVua("Ghế " + ma + (coso.length() ? "  |  " + coso : ""), 160, 68, TFT_WHITE, COL_BG);
  veVua("Trước: " + String(csTruoc) + "    Nay: " + String(chiSo), 160, 96, TFT_WHITE, COL_BG);
  if(lanDau){
    veVua("Lần đầu - chưa có mốc để tính chênh", 160, 126, COL_XAM, COL_BG);
  } else {
    veVua("Chênh: " + String(chenh) + " chỉ số = " + String(tienUoc) + " đ", 160, 126, COL_OK, COL_BG);
    veVua("(ước theo máy đếm - chưa đối soát tiền mặt)", 160, 150, COL_XAM, COL_BG);
  }
  veVua("CHỈ XEM - KHÔNG CHỐT CA", 160, 180, COL_DO, COL_BG);
  veVua("Chạm để tiếp tục", 160, 208, COL_XAM, COL_BG);
  int x, y; cho4Cham(x, y, 0);
}

// ═══════════════════════════════ MÀN CHÍNH ═══════════════════════════════════
// ═══════════════════════════ ĐỌC CHỈ SỐ TIỀN TỪ GHẾ (AP) ═════════════════════
/* Nối AP ghế rồi trao đổi 1 request thô (GET/POST). Trả thân JSON (đã bỏ header), ""=lỗi.
   Dùng chung cho /chotso (đọc) và /chotmoc (dời mốc). extraHdr: header phụ (vd X-Chot-Lan). */
static String gheYeuCau(const ApGhe& g, const char* dong1, const String& extraHdr){
  WiFi.mode(WIFI_STA); WiFi.disconnect(true); delay(150);
  WiFi.begin(g.ssid.c_str(), GHE_AP_PASS);
  unsigned long t0 = millis();
  while(WiFi.status() != WL_CONNECTED && millis() - t0 < 15000) delay(250);
  if(WiFi.status() != WL_CONNECTED){ WiFi.disconnect(true); return ""; }
  WiFiClient cl; cl.setTimeout(8000);
  if(!cl.connect(GHE_IP, GHE_PORT)){ WiFi.disconnect(true); return ""; }
  cl.print(String(dong1) + " HTTP/1.1\r\n");
  cl.print("Host: 192.168.4.1\r\n");
  cl.print(String("X-OTA-Key: ") + OTA_KEY + "\r\n");
  if(extraHdr.length()) cl.print(extraHdr);         // vd "X-Chot-Lan: ...\r\nContent-Length: 0\r\n"
  cl.print("Connection: close\r\n\r\n");
  String resp = ""; unsigned long tr = millis();
  while((cl.connected() || cl.available()) && millis() - tr < 8000){
    while(cl.available()){ resp += (char)cl.read(); tr = millis(); if(resp.length() > 2000) break; }
    delay(2);
  }
  cl.stop(); WiFi.disconnect(true);
  int b = resp.indexOf("\r\n\r\n");
  return (b >= 0) ? resp.substring(b + 4) : resp;
}

/* GET /chotso -> chỉ số tiền mặt + QR cộng dồn + MỐC chốt trước của ghế. Trả false nếu lỗi.
   Ghế cũ (không có tmc/qrc/lan) -> mốc = 0 -> coi như lần đầu, kỳ = toàn bộ. */
bool docChiSoGhe(const ApGhe& g, ChiSoGhe& cs){
  bao("Kết nối ghế...", COL_ACC, g.ma, "đọc chỉ số + mốc");
  String body = gheYeuCau(g, "GET /chotso", "");
  if(body.length() == 0){
    bao("Nối ghế thất bại", COL_DO, g.ma, "Lại gần hơn rồi thử lại"); delay(1600); return false;
  }
  StaticJsonDocument<384> d;
  if(deserializeJson(d, body) || !(d["ok"] | 0)){
    bao("Ghế không trả chỉ số", COL_DO, "Ghế cần firmware có /chotso", ""); delay(2200); return false;
  }
  cs.tm  = (long)(d["tm"]  | 0);
  cs.qr  = (long)(d["qr"]  | 0);
  cs.tmc = (long)(d["tmc"] | 0);
  cs.qrc = (long)(d["qrc"] | 0);
  cs.lan = String((const char*)(d["lan"] | ""));
  Serial.printf("[CHOTSO] %s: tm=%ld qr=%ld | moc tm=%ld qr=%ld lan='%s'\n",
                g.ma.c_str(), cs.tm, cs.qr, cs.tmc, cs.qrc, cs.lan.c_str());
  return true;
}

/* POST /chotmoc (mã lần) -> ghế DỜI MỐC = chỉ số hiện tại, trả KỲ vừa chốt (con số chính thức).
   Gửi lại đúng mã lần -> ghế trả y kết quả cũ (không dời hai lần). Chạy hoàn toàn qua AP, OFFLINE. */
bool doiMocGhe(const ApGhe& g, const String& maLan, ChotKetQua& kq){
  String extra = String("X-Chot-Lan: ") + maLan + "\r\nContent-Length: 0\r\n";
  String body = gheYeuCau(g, "POST /chotmoc", extra);
  if(body.length() == 0) return false;
  StaticJsonDocument<384> d;
  if(deserializeJson(d, body) || !(d["ok"] | 0)) return false;
  kq.tm   = (long)(d["tm"]    | 0);  kq.qr   = (long)(d["qr"]    | 0);
  kq.tmc  = (long)(d["tmc"]   | 0);  kq.qrc  = (long)(d["qrc"]   | 0);
  kq.tmKy = (long)(d["tm_ky"] | 0);  kq.qrKy = (long)(d["qr_ky"] | 0);
  Serial.printf("[CHOTMOC] %s lan='%s' trung=%d ky tm=%ld qr=%ld\n",
                g.ma.c_str(), maLan.c_str(), (int)(d["trung"] | 0), kq.tmKy, kq.qrKy);
  return true;
}

/* ── HÀNG CHỜ ĐẨY WEB (NVS "chotQ") — mỗi dòng 1 bản ghi JSON, chốt offline nằm đây tới khi có mạng ── */
void themChotQueue(const String& line){
  String q = prefsTram.getString("chotQ", "");
  q += line; q += "\n";
  while(q.length() > 3000){ int nl = q.indexOf('\n'); if(nl < 0) break; q = q.substring(nl + 1); }  // bỏ cũ nhất
  prefsTram.putString("chotQ", q);
}
int demChotQueue(){
  String q = prefsTram.getString("chotQ", "");
  int n = 0, i = 0;
  while(i < (int)q.length()){ int nl = q.indexOf('\n', i); if(nl < 0) nl = q.length();
    if(nl > i) n++; i = nl + 1; }
  return n;
}
/* Có internet + đăng nhập -> đẩy từng bản ghi lên web (chot_tien_luu). Web idempotent theo ma_lan.
   Đẩy được thì bỏ khỏi hàng; thất bại thì giữ lại. Im lặng nếu không có mạng. */
void flushChotQueue(){
  String q = prefsTram.getString("chotQ", "");
  if(q.length() == 0) return;
  if(!noiInternet()) return;
  if(!damBaoDangNhap()) return;
  String con = "";
  int i = 0;
  while(i < (int)q.length()){
    int nl = q.indexOf('\n', i); if(nl < 0) nl = q.length();
    String line = q.substring(i, nl); i = nl + 1;
    line.trim(); if(line.length() == 0) continue;
    StaticJsonDocument<384> d;
    if(deserializeJson(d, line)) continue;          // dòng rác -> bỏ luôn
    StaticJsonDocument<384> b;
    b["token"]  = g_token;
    b["ma_may"] = (const char*)(d["ma_may"] | "");
    b["ma_lan"] = (const char*)(d["ma_lan"] | "");
    b["tm"] = (long)(d["tm"] | 0);   b["qr"] = (long)(d["qr"] | 0);
    b["tmc"] = (long)(d["tmc"] | 0); b["qrc"] = (long)(d["qrc"] | 0);
    b["tm_ky"] = (long)(d["tm_ky"] | 0); b["qr_ky"] = (long)(d["qr_ky"] | 0);
    String body; serializeJson(b, body);
    String r; bool ok = false;
    if(webGoi("chot_tien_luu", body, r)){
      StaticJsonDocument<256> dr;
      if(!deserializeJson(dr, r) && (dr["ok"] | false)) ok = true;
    }
    if(!ok){ con += line; con += "\n"; }            // giữ lại để đẩy sau
  }
  prefsTram.putString("chotQ", con);
}

// ═══════════════════════════════ CHẾ ĐỘ CHỐT TIỀN (OFFLINE) ══════════════════
/* MỐC nằm TRÊN GHẾ -> chốt chạy được KHÔNG cần internet:
   1) Dò AP -> chọn ghế. 2) GET /chotso: chỉ số + mốc -> tính KỲ NÀY ngay tại chỗ.
   3) Bấm CHỐT -> POST /chotmoc (ghế dời mốc, trả kỳ chính thức) — vẫn qua AP, offline.
   4) Lưu bản ghi vào NVS + thử đẩy web luôn; không có mạng thì để hàng chờ, đẩy sau. */
void cheDoChotTien(){
  quetAp(true);
  if(g_dsN == 0) return;
  int sel = chonGheTuDanhSach("Chon ghe CHOT TIEN");
  if(sel < 0) return;
  ApGhe g = g_ds[sel];
  String ma = g.ma;

  // 1) Đọc chỉ số + MỐC thẳng từ ghế (qua AP) — không cần internet
  ChiSoGhe cs;
  if(!docChiSoGhe(g, cs)) return;
  bool dau  = (cs.lan.length() == 0);               // ghế chưa chốt lần nào -> kỳ = toàn bộ từ đầu
  long tmKy = cs.tm - cs.tmc;
  long qrKy = cs.qr - cs.qrc;

  // 2) Màn xác nhận (OFFLINE): chỉ số ghế + kỳ này, hai nút HUỶ / CHỐT
  tft.fillScreen(COL_BG);
  veLon("CHỐT TIỀN - Ghế " + ma, 14, 16, COL_ACC, COL_BG, TL_DATUM);
  veVua("Tiền mặt (ghế): " + String(cs.tm) + " đ", 14, 54, TFT_WHITE, COL_BG, TL_DATUM);
  veVua("QR (ghế): " + String(cs.qr) + " đ", 14, 78, TFT_WHITE, COL_BG, TL_DATUM);
  if(dau) veVua("Lần đầu - kỳ này = toàn bộ từ đầu", 14, 108, COL_XAM, COL_BG, TL_DATUM);
  veVua("Kỳ này: TM " + String(tmKy) + "  |  QR " + String(qrKy), 14, 132, COL_OK, COL_BG, TL_DATUM);
  tft.fillRoundRect(20, 190, 130, 40, 9, COL_DO);
  veLon("HUỶ", 85, 210, TFT_WHITE, COL_DO);
  tft.fillRoundRect(170, 190, 130, 40, 9, 0x0400);
  veLon("CHỐT", 235, 210, TFT_WHITE, 0x0400);
  { int cx, cy; cho4Cham(cx, cy, 0); if(cx < 160) return; }   // chạm nửa trái = HUỶ

  // 3) CHỐT: dời mốc TRÊN GHẾ (qua AP, offline). ma_lan chống chốt trùng khi gửi lại.
  String ml = "ct-" + ma + "-" + String((uint32_t)millis());
  bao("Đang chốt (ghế)...", COL_ACC, "Ghế " + ma, "dời mốc trên ghế");
  ChotKetQua kq;
  if(!doiMocGhe(g, ml, kq)){
    bao("Chốt ghế lỗi", COL_DO, "Không dời được mốc", "Lại gần ghế rồi thử lại"); delay(2200); return;
  }

  // 4) Lưu bản ghi vào NVS (nguồn để đẩy web) rồi thử đẩy ngay
  { StaticJsonDocument<384> rec;
    rec["ma_may"] = ma; rec["ma_lan"] = ml;
    rec["tm"] = kq.tm; rec["qr"] = kq.qr; rec["tmc"] = kq.tmc; rec["qrc"] = kq.qrc;
    rec["tm_ky"] = kq.tmKy; rec["qr_ky"] = kq.qrKy;
    String line; serializeJson(rec, line);
    themChotQueue(line); }
  bao("Đang đẩy web...", COL_ACC, "Ghế " + ma, "(offline thì để sau)");
  flushChotQueue();                                  // có mạng thì đẩy; không thì im lặng để hàng chờ

  // 5) Kết quả (con số từ ghế — luôn có, kể cả offline)
  int conCho = demChotQueue();
  tft.fillScreen(COL_BG);
  veLon("ĐÃ CHỐT TIỀN", 160, 36, COL_OK, COL_BG);
  veVua("Ghế " + ma, 160, 76, TFT_WHITE, COL_BG);
  veVua("Kỳ này: TM " + String(kq.tmKy) + "  |  QR " + String(kq.qrKy), 160, 108, TFT_WHITE, COL_BG);
  if(conCho > 0) veVua(String(conCho) + " bản chờ đẩy web (tự đẩy khi có mạng)", 160, 140, COL_XAM, COL_BG);
  else           veVua("Đã đồng bộ lên web", 160, 140, COL_OK, COL_BG);
  veVua("Chạm để tiếp tục", 160, 182, COL_XAM, COL_BG);
  int x, y; cho4Cham(x, y, 0);
}

void manChinh(){
  tft.fillScreen(COL_BG);
  veLon("MÁY TRẠM POSH", 160, 14, COL_ACC, COL_BG);
  // 4 nút (mỗi nút cao 40, cách 6) — vùng chạm khớp trong loop()
  tft.fillRoundRect(24, 32, 272, 40, 9, 0x2145);
  veLon("NẠP FIRMWARE", 160, 52, TFT_WHITE, 0x2145);
  tft.fillRoundRect(24, 78, 272, 40, 9, 0x0341);
  veLon("THU TIỀN / CHỐT CA", 160, 98, TFT_WHITE, 0x0341);
  tft.fillRoundRect(24, 124, 272, 40, 9, 0x0400);
  veLon("CHỐT TIỀN (ĐỌC GHẾ)", 160, 144, TFT_WHITE, 0x0400);
  tft.fillRoundRect(24, 170, 272, 40, 9, 0x03A0);
  veLon("KIỂM TRA CHỈ SỐ", 160, 190, TFT_WHITE, 0x03A0);
}

void setup(){
  Serial.begin(115200); delay(200);
  Serial.println("\n\n=== " FW_VERSION " ===");
  pinMode(BL_PIN, OUTPUT); digitalWrite(BL_PIN, HIGH);

  prefsTram.begin("tram", false);
  g_pinLuu = prefsTram.getString("pin", "");   // PIN đã lưu -> tự đăng nhập, khỏi gõ lại
  Serial.printf("[TRAM] PIN da luu: %s\n", g_pinLuu.length() ? "co" : "chua");

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

  if(x >= 24 && x <= 296 && y >= 32 && y <= 72){          // NẠP FIRMWARE
    cheDoOTA();
  } else if(x >= 24 && x <= 296 && y >= 78 && y <= 118){  // THU TIỀN / CHỐT CA
    cheDoChotCa();
  } else if(x >= 24 && x <= 296 && y >= 124 && y <= 164){ // CHỐT TIỀN (ĐỌC GHẾ)
    cheDoChotTien();
  } else if(x >= 24 && x <= 296 && y >= 170 && y <= 210){ // KIỂM TRA CHỈ SỐ (chỉ xem)
    cheDoKiemTra();
  }
  manChinh();
}
