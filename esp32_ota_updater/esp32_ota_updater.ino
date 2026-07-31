/*
 * ESP32 "THỢ NẠP" OTA nội bộ (dành cho board CYD ESP32-2432S028 có khe thẻ SD)
 * ------------------------------------------------------------------------------
 * MỤC ĐÍCH: cập nhật firmware cho các MÁY CHẤM CÔNG mà KHÔNG cần điện thoại/cáp.
 *   - Chép file firmware .bin vào thẻ microSD, đổi tên thành:  firmware.bin
 *   - Cắm thẻ vào con "thợ nạp" này, bật nguồn.
 *   - Mang lại GẦN máy chấm công cần cập nhật.
 *   - Nó tự: quét AP "ChamCong-*" ở GẦN (sóng mạnh) -> nối (mật khẩu chung
 *     12345678) -> POST firmware.bin lên http://192.168.4.1/update
 *     (đăng nhập admin/admin) -> máy đích tự nạp & khởi động lại.
 *   - Hiện ✓/✗ trên màn TFT rồi tự tìm máy kế (bỏ qua máy đã nạp trong phiên).
 *
 * VÌ SAO CHẠY ĐƯỢC: tất cả máy dùng CHUNG mật khẩu AP (12345678) + CHUNG tài
 * khoản trang /update (admin/admin), tên AP theo mẫu "ChamCong-<cơ sở>".
 *
 * THƯ VIỆN: TFT_eSPI (Bodmer) — dùng ĐÚNG User_Setup.h của board CYD như firmware chấm công.
 * AN TOÀN: máy đích dùng Update.h -> nếu upload lỗi/dở, nó HỦY và GIỮ firmware cũ.
 */

#include <WiFi.h>
#include <SD.h>
#include <SPI.h>
#include "mbedtls/base64.h"
#include <TFT_eSPI.h>
#include <WebServer.h>          // portal điện thoại: 2 nút Tải / Nạp
#include <DNSServer.h>          // captive portal
#include <HTTPClient.h>
#include <WiFiClientSecure.h>   // tải .bin qua HTTPS
#include <Preferences.h>        // cấu hình + bí mật giữ trong NVS (không nằm trong .bin)
#include <ArduinoJson.h>        // đọc latest.json
#include <Update.h>             // TỰ nâng cấp chính máy trạm (ghi sang phân vùng app còn lại)

/* ⚠️ KHAI BÁO TRƯỚC — PHẢI Ở ĐÂY, ĐỪNG DỜI XUỐNG GIỮA FILE.
   arduino-cli tự sinh prototype cho hàm trong .ino, NHƯNG có sẵn khai báo thì nó THÔI tự sinh.
   Đặt giữa file là mọi chỗ dùng phía TRÊN nó mất khai báo -> build đỏ. Firmware máy chấm công
   đã vỡ đúng vì lỗi này ('netUp' was not declared in this scope). */
bool   pushFirmware(const String& ssid);
bool   updateOne(const String& ssid, const String& bssid, int kenh);
String cfgChe(const String& v);
void   scr(const String& l1, uint16_t c1, const String& l2, uint16_t c2, const String& l3, uint16_t c3);

// ===== CẤU HÌNH (khớp firmware máy chấm công) =====
// Bí mật ở secrets.h (KHÔNG commit). Thiếu file thì build báo lỗi — cố ý.
// ⚠️ SEC_AP_PASS / SEC_OTA_USER / SEC_OTA_PASS phải KHỚP secrets.h của máy chấm công,
//    lệch một chữ là thợ nạp không vào được máy đích và chỉ báo "FAIL" chung chung.
#if !__has_include("secrets.h")
  #error "Thieu secrets.h — copy secrets.example.h thanh secrets.h roi dien gia tri that."
#endif
#include "secrets.h"

#define FW_VERSION "2026-07-30a (tu tai .bin ve the qua WiFi; BAM moi nap; bi mat trong NVS)"

/* Máy chính từ 31/07/2026 dùng AP tên CỐ ĐỊNH "CHAM_CONG" (trước là ChamCong-<cơ sở>, mà tên
   cơ sở đổi là Hikvision mất WiFi). Vẫn NHẬN cả tên cũ, không thì không nạp được cho những máy
   chưa lên bản mới — đúng lúc cần máy trạm nhất.
   ⚠️ Tên giống nhau hết nên KHÔNG được phân biệt máy theo SSID nữa: g_done[] và mọi chỗ chọn máy
      chuyển sang dùng BSSID (địa chỉ MAC của AP, mỗi chip một cái, không bao giờ đổi). */
const char*   AP_TEN      = "CHAM_CONG";   // tên AP MỚI, cố định cho mọi máy
const char*   AP_PREFIX   = "ChamCong-";   // tên AP CŨ — vẫn nhận để còn nạp được
// 3 giá trị dưới chỉ là DỰ PHÒNG: máy đọc từ NVS trước, NVS trống thì lấy ở đây rồi CHÉP VÀO NVS.
// Nhờ vậy bản do CI build (secrets.h toàn placeholder) vẫn chạy, và file .bin không chứa bí mật.
const char*   AP_PASS     = SEC_AP_PASS;   // mật khẩu WiFi AP của máy chấm công
const char*   OTA_USER    = SEC_OTA_USER;  // tài khoản trang /update
const char*   OTA_PASS    = SEC_OTA_PASS;
const char*   FW_PATH     = "/firmware.bin"; // tên file .bin trên thẻ SD

/* Link mặc định -> cắm điện là dùng được, khỏi gõ tay ở portal.
   ⚠️ Tên repo PHẢI KHỚP `FW_REPO_MACDINH` trong ChamCongLive/Mã.js và trong firmware.yml.
   ⚠️ HAI LINK KHÁC NHAU và không được lẫn:
        FW_URL_MC   -> firmware MÁY CHÍNH, tải về THẺ để đi nạp cho máy khác
        FW_URL_TRAM -> firmware CHÍNH MÁY TRẠM NÀY, nạp thẳng vào nó
      Lẫn hai cái là máy trạm tự nạp firmware máy chấm công vào mình. Nên ngoài việc
      tách link, mỗi file .json còn mang trường "loai" và code TỪ CHỐI nếu sai loại. */
#define FW_REPO_MD "zairozy2004199x/khh-chamcong-firmware"
const char* FW_URL_MC   = "https://github.com/" FW_REPO_MD "/releases/download/latest/latest.json";
const char* FW_URL_TRAM = "https://github.com/" FW_REPO_MD "/releases/download/latest-tram/latest-tram.json";
const char* LOAI_MC     = "may-chinh";
const char* LOAI_TRAM   = "may-tram";
IPAddress     TARGET_IP(192, 168, 4, 1);   // IP máy đích tại AP của nó
const uint16_t TARGET_PORT = 80;
const int     NEAR_RSSI   = -68;           // chỉ nạp máy Ở GẦN (sóng >= mức này) -> tránh nạp nhầm máy xa
const int     SD_CS       = 5;             // chân CS thẻ SD trên CYD (SPI: SCK18/MISO19/MOSI23)
#define BL_PIN 21                          // đèn nền CYD
// ==================================================

TFT_eSPI tft = TFT_eSPI();
long   g_fwSize = 0;
bool   g_sdOk = false;
String g_done[40]; int g_doneN = 0;        // BSSID đã nạp trong phiên (khỏi nạp lại)
bool laMayChamCong(const String& ssid){ return ssid == String(AP_TEN) || ssid.startsWith(AP_PREFIX); }
int    g_okCount = 0, g_failCount = 0;

/* ===========================================================================
 *  CẤU HÌNH TRONG NVS + ĐƯỜNG RA INTERNET
 * ---------------------------------------------------------------------------
 *  Trước bản này máy trạm CHỈ biết nối AP "ChamCong-*" — không có WiFi Internet,
 *  không có code tải file, nên bắt buộc phải rút thẻ SD ra chép .bin bằng máy tính.
 *  Nay: nối WiFi/hotspot -> tải .bin -> ghi thẳng vào thẻ CẮM SẴN.
 *
 *  ⚠️ Tải DỞ DANG thì GIỮ NGUYÊN file cũ trên thẻ: ghi ra firmware.new, đủ byte
 *     mới đổi tên thành firmware.bin. Không bao giờ để thẻ có file cụt.
 * =========================================================================== */
Preferences prefs;
WebServer   server(80);
DNSServer   dnsServer;

String _cfgApPass, _cfgOtaUser, _cfgOtaPass, _cfgStaSsid, _cfgStaPass, _cfgFwUrl, _cfgSelfAp, _cfgFwVer, _cfgFwTramUrl;
// Ban CI da nap vao CHINH may tram nay. KHONG so voi FW_VERSION duoc: FW_VERSION la chu viet
// tay trong code, con ver cua CI la "tram-<ngay>-<sha>" — hai kieu khac han, so la khong bao gio
// khop, thanh ra lan nao bam cung tai lai va nap lai du dang dung ban do.
String _cfgTramVer;
bool   g_tuDongNap  = false;    // mặc định TẮT: bấm mới nạp (trước đây tự nạp máy nào ở gần)
bool   g_chuaCauHinh = false;
String g_tinhTrang  = "";       // dòng trạng thái mới nhất cho portal đọc
volatile bool g_dangLamViec = false;   // đang tải / đang nạp -> chặn bấm chồng lệnh

bool cfgLaPlaceholder(const String& v){
  return v.length() == 0 || v.startsWith("__CHUA_CAU_HINH") || v.startsWith("REPLACE");
}
String cfgLay(const char* khoa, const char* biencompile){
  String v = prefs.getString(khoa, "");
  if (v.length()) return v;
  String c = String(biencompile ? biencompile : "");
  if (!cfgLaPlaceholder(c)) { prefs.putString(khoa, c); Serial.printf("[CFG] di tru '%s' vao NVS\n", khoa); return c; }
  return "";
}
void napCauHinh(){
  _cfgApPass  = cfgLay("apPass",  SEC_AP_PASS);
  _cfgOtaUser = cfgLay("otaUser", SEC_OTA_USER);
  _cfgOtaPass = cfgLay("otaPass", SEC_OTA_PASS);
  _cfgStaSsid = prefs.getString("staSsid", "");
  _cfgStaPass = prefs.getString("staPass", "");
  _cfgFwUrl     = prefs.getString("fwUrl",     FW_URL_MC);     // có mặc định -> khỏi gõ tay
  _cfgFwTramUrl = prefs.getString("fwTramUrl", FW_URL_TRAM);
  _cfgTramVer   = prefs.getString("tramVer",   "");
  _cfgSelfAp  = prefs.getString("selfAp",  "");
  _cfgFwVer   = prefs.getString("fwVer",   "");
  g_tuDongNap = prefs.getString("autoNap", "") == "1";
  AP_PASS  = _cfgApPass.c_str();
  OTA_USER = _cfgOtaUser.c_str();
  OTA_PASS = _cfgOtaPass.c_str();
  // Thiếu 3 cái này thì KHÔNG nạp được cho máy đích. staSsid/fwUrl chỉ cần khi muốn tự tải.
  g_chuaCauHinh = (_cfgApPass.length() == 0) || (_cfgOtaPass.length() == 0);
  Serial.printf("[CFG] apPass=%s otaPass=%s staSsid=%s fwUrl=%s tuDongNap=%d\n",
    _cfgApPass.length()?"có":"THIẾU", _cfgOtaPass.length()?"có":"THIẾU",
    _cfgStaSsid.length()?_cfgStaSsid.c_str():"(chưa khai)", _cfgFwUrl.length()?"có":"(chưa khai)", (int)g_tuDongNap);
}
String cfgChe(const String& v){
  if (!v.length()) return "(trống)";
  return v.substring(0, 4) + "…(" + String(v.length()) + " ký tự)";
}
void ghiTinhTrang(const String& s){ g_tinhTrang = s; Serial.println("[TT] " + s); }

bool isDone(const String& s){ for(int i=0;i<g_doneN;i++) if(g_done[i]==s) return true; return false; }
void markDone(const String& s){ if(g_doneN < 40) g_done[g_doneN++] = s; }

// ---------- Màn hình ----------
/* Khung + bảng màu lấy ĐÚNG của màn chờ máy chính (showIdle) -> hai máy nhìn như một bộ,
   không còn cảnh "một màn được chăm, bảy màn trơ chữ giữa nền đen". */
#define COL_PANEL 0x08CA                                  // navy đậm, nền panel
uint16_t colKhung(){ return tft.color565(255, 140, 0); }  // cam
uint16_t colVien(){  return tft.color565(40, 90, 150); }
uint16_t colChay(){  return tft.color565(0, 190, 120); }  // xanh lá thanh tiến trình
uint16_t colDo(){    return tft.color565(190, 30, 45); }
void veKhung(){
  tft.drawRoundRect(3, 3, 314, 234, 10, colKhung());
  tft.drawRoundRect(4, 4, 312, 232, 10, colKhung());
}
void veDemCuoi(){
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
  tft.drawString("OK: " + String(g_okCount) + "   Loi: " + String(g_failCount), 160, 218, 2);
}

void scr(const String& l1, uint16_t c1, const String& l2, uint16_t c2, const String& l3, uint16_t c3){
  tft.fillScreen(TFT_BLACK); veKhung(); tft.setTextDatum(MC_DATUM);
  if(l1.length()){ tft.setTextColor(c1, TFT_BLACK); tft.drawString(l1, 160, 60, 4); }
  if(l2.length()){ tft.setTextColor(c2, TFT_BLACK); tft.drawString(l2, 160, 118, 4); }
  if(l3.length()){ tft.setTextColor(c3, TFT_BLACK); tft.drawString(l3, 160, 170, 2); }
  veDemCuoi();
}

/* Màn LỖI: dải đỏ nguyên bề ngang trên cùng. Chữ đỏ trên nền đen dễ trôi qua mắt —
   đứng giữa cửa hàng ồn ào, liếc một cái phải biết ngay là hỏng. */
void scrLoi(const String& l1, const String& l2, const String& l3){
  tft.fillScreen(TFT_BLACK);
  tft.fillRoundRect(6, 6, 308, 38, 8, colDo());
  veKhung(); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_WHITE, colDo());      tft.drawString(l1, 160, 25, 4);
  if(l2.length()){ tft.setTextColor(TFT_YELLOW, TFT_BLACK);   tft.drawString(l2, 160, 110, 4); }
  if(l3.length()){ tft.setTextColor(TFT_DARKGREY, TFT_BLACK); tft.drawString(l3, 160, 165, 2); }
  veDemCuoi();
}

/* ---------- MÀN TIẾN TRÌNH: vẽ khung MỘT LẦN, sau đó chỉ tô lại phần đổi ----------
 * ⚠️ Bản cũ gọi fillScreen() ở MỖI 2% -> 50 lần xoá trắng cả màn trong một lần nạp,
 *    nhìn nháy liên tục. Thêm thanh tiến trình mà vẫn xoá cả màn thì còn tệ hơn.
 *    Nay ttMo() vẽ nền + khung 1 lần, ttPct() chỉ tô thanh và con số.
 */
const int TT_X = 30, TT_Y = 122, TT_W = 260, TT_H = 26;
int _ttPctCu = -1;
void ttMo(const String& tieuDe, uint16_t mauTd, const String& phu,
          const String& canhBao, uint16_t mauCb){
  tft.fillScreen(TFT_BLACK); veKhung(); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(mauTd, TFT_BLACK); tft.drawString(tieuDe, 160, 48, 4);
  if(phu.length()){ tft.setTextColor(TFT_WHITE, TFT_BLACK); tft.drawString(phu, 160, 88, 2); }
  tft.fillRoundRect(TT_X, TT_Y, TT_W, TT_H, 6, COL_PANEL);
  tft.drawRoundRect(TT_X, TT_Y, TT_W, TT_H, 6, colVien());
  if(canhBao.length()){ tft.setTextColor(mauCb, TFT_BLACK); tft.drawString(canhBao, 160, 196, 2); }
  veDemCuoi();
  _ttPctCu = -1;
}
void ttPct(int pct){
  if(pct < 0) pct = 0; if(pct > 100) pct = 100;
  if(pct == _ttPctCu) return;
  _ttPctCu = pct;
  int rong = (TT_W - 4) * pct / 100;
  // fillRoundRect với bề rộng nhỏ hơn 2*bán kính vẽ ra hình méo -> hẹp thì dùng fillRect
  if(rong >= 12)     tft.fillRoundRect(TT_X + 2, TT_Y + 2, rong, TT_H - 4, 4, colChay());
  else if(rong > 0)  tft.fillRect(TT_X + 2, TT_Y + 2, rong, TT_H - 4, colChay());
  if(rong < TT_W - 4)                                    // phần chưa chạy: trả về nền panel
    tft.fillRect(TT_X + 2 + rong, TT_Y + 2, (TT_W - 4) - rong, TT_H - 4, COL_PANEL);
  // Xoá vệt số cũ rồi mới vẽ: "100 %" ngắn lại thành "9 %" là còn sót đuôi nếu không xoá.
  tft.fillRect(96, 156, 128, 30, TFT_BLACK);
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_CYAN, TFT_BLACK);
  tft.drawString(String(pct) + " %", 160, 170, 4);
}

// base64("admin:admin") cho header Authorization Basic
String basicAuth(){
  String cred = String(OTA_USER) + ":" + String(OTA_PASS);
  unsigned char out[64]; size_t olen = 0;
  mbedtls_base64_encode(out, sizeof(out), &olen, (const unsigned char*)cred.c_str(), cred.length());
  return String((char*)out).substring(0, olen);
}

// Đẩy firmware.bin (multipart) lên máy đích /update. Trả true nếu máy đích báo thành công.
bool pushFirmware(const String& ssid){
  File f = SD.open(FW_PATH, FILE_READ);
  if(!f){ Serial.println("[SD] Khong mo duoc firmware.bin"); return false; }
  long fsize = f.size();
  if(fsize <= 0){ f.close(); Serial.println("[SD] File rong"); return false; }

  String boundary = "----otaGun" + String((uint32_t)millis(), HEX);
  String pre = "--" + boundary + "\r\n"
               "Content-Disposition: form-data; name=\"fw\"; filename=\"firmware.bin\"\r\n"
               "Content-Type: application/octet-stream\r\n\r\n";
  String post = "\r\n--" + boundary + "--\r\n";
  long clen = (long)pre.length() + fsize + (long)post.length();

  WiFiClient client; client.setTimeout(20000);
  if(!client.connect(TARGET_IP, TARGET_PORT)){ f.close(); Serial.println("[NET] Khong ket noi 192.168.4.1"); return false; }

  client.print("POST /update HTTP/1.1\r\n");
  client.print("Host: 192.168.4.1\r\n");
  client.print("Authorization: Basic " + basicAuth() + "\r\n");
  client.print("Content-Type: multipart/form-data; boundary=" + boundary + "\r\n");
  client.print("Content-Length: " + String(clen) + "\r\n");
  client.print("Connection: close\r\n\r\n");
  client.print(pre);

  uint8_t buf[1024]; long sent = 0; bool sendOk = true; int lastPct = -1;
  ttMo("DANG NAP", TFT_ORANGE, ssid, "KHONG TAT NGUON MAY DICH", TFT_RED);   // khung vẽ MỘT lần
  ttPct(0);
  while(sent < fsize){
    int want = (fsize - sent > 1024) ? 1024 : (int)(fsize - sent);
    int n = f.read(buf, want);
    if(n <= 0){ sendOk = false; break; }
    int w = 0;
    while(w < n){
      int x = client.write(buf + w, n - w);
      if(x <= 0){ if(!client.connected()){ sendOk = false; break; } delay(1); }
      else w += x;
    }
    if(!sendOk) break;
    sent += n;
    int pct = (int)(sent * 100 / fsize);
    if(pct != lastPct && pct % 2 == 0){ lastPct = pct; ttPct(pct); }
  }
  f.close();
  if(sendOk) client.print(post);

  // Đọc phản hồi máy đích
  String resp = ""; unsigned long t0 = millis();
  while((client.connected() || client.available()) && millis() - t0 < 20000){
    while(client.available()){ resp += (char)client.read(); t0 = millis(); if(resp.length() > 3000) break; }
    delay(2);
  }
  client.stop();

  int sp = resp.indexOf(' '); int status = (sp >= 0) ? resp.substring(sp + 1, sp + 4).toInt() : 0;
  bool ok = sendOk && (status == 200) &&
            (resp.indexOf("TH\xC3\x80NH C\xC3\x94NG") >= 0 || resp.indexOf("THANH CONG") >= 0 ||
             resp.indexOf("thanh cong") >= 0 || resp.indexOf("\xE2\x9C\x85") >= 0 || resp.indexOf("khoi dong") >= 0 ||
             resp.indexOf("success") >= 0 || (sent == fsize));   // gửi đủ byte + 200 coi như OK (máy đích tự restart)
  Serial.printf("[NAP] %s: gui %ld/%ld byte, http%d -> %s\n", ssid.c_str(), sent, fsize, status, ok ? "OK" : "FAIL");
  return ok;
}

// Nối AP máy đích rồi đẩy firmware
bool macTuChuoi(const String& s, uint8_t out[6]){
  int n = 0;
  for(int i = 0; i + 1 < (int)s.length() && n < 6; i++){
    if(s[i] == ':' || s[i] == '-') continue;
    out[n++] = (uint8_t)strtol(s.substring(i, i + 2).c_str(), nullptr, 16);
    i++;
  }
  return n == 6;
}
/* ⚠️ PHẢI nhắm đúng BSSID. Mọi máy giờ cùng tên AP "CHAM_CONG", mà WiFi.begin(ssid,pass) chỉ chọn
   AP nào SÓNG MẠNH NHẤT — đứng giữa hai máy là nạp nhầm sang máy bàn bên mà không hề biết. */
bool updateOne(const String& ssid, const String& bssid, int kenh){
  scr("Ket noi...", TFT_CYAN, ssid, TFT_WHITE, bssid, TFT_DARKGREY);
  WiFi.disconnect(true); delay(200);
  uint8_t bs[6];
  if(bssid.length() && macTuChuoi(bssid, bs)) WiFi.begin(ssid.c_str(), AP_PASS, kenh > 0 ? kenh : 0, bs);
  else                                        WiFi.begin(ssid.c_str(), AP_PASS);
  unsigned long t0 = millis();
  while(WiFi.status() != WL_CONNECTED && millis() - t0 < 15000){ delay(300); Serial.print("."); }
  if(WiFi.status() != WL_CONNECTED){ Serial.println("\n[NET] Nối AP thất bại: " + ssid); return false; }
  Serial.println("\n[NET] Đã nối " + ssid + " " + WiFi.BSSIDstr() + " (" + WiFi.localIP().toString() + ")");
  delay(500);
  bool ok = pushFirmware(ssid);
  WiFi.disconnect(true);
  return ok;
}

// ---------- Đọc lại cỡ file firmware trên thẻ ----------
void doLaiCoFile(){
  g_fwSize = 0;
  if(!g_sdOk) return;
  File f = SD.open(FW_PATH, FILE_READ);
  if(f){ g_fwSize = f.size(); f.close(); }
}

// ---------- Nối WiFi có Internet (giữ AP portal chạy song song) ----------
bool noiInternet(unsigned long chuMs = 15000){
  if(_cfgStaSsid.length() == 0) { ghiTinhTrang("Chua khai WiFi Internet"); return false; }
  if(WiFi.status() == WL_CONNECTED && WiFi.SSID() == _cfgStaSsid) return true;
  ghiTinhTrang("Dang noi WiFi " + _cfgStaSsid + "...");
  WiFi.begin(_cfgStaSsid.c_str(), _cfgStaPass.c_str());
  unsigned long t0 = millis();
  while(WiFi.status() != WL_CONNECTED && millis() - t0 < chuMs){ delay(250); }
  bool ok = WiFi.status() == WL_CONNECTED;
  ghiTinhTrang(ok ? ("Da noi WiFi, IP " + WiFi.localIP().toString()) : ("KHONG noi duoc WiFi " + _cfgStaSsid));
  return ok;
}

/* ---------- TẢI .bin VỀ THẺ ----------
 * Trả chuỗi rỗng nếu OK, ngược lại là lý do lỗi (đưa thẳng lên portal cho anh đọc).
 * fwUrl có thể là latest.json (lấy ver + url trong đó) hoặc link .bin trực tiếp.
 *
 * ⚠️ Ghi ra firmware.new, ĐỦ BYTE mới đổi tên -> tải dở dang KHÔNG làm mất file cũ.
 *    Đây là điều kiện sống còn: thẻ có file cụt là mang đi nạp hỏng máy chấm công.
 */
String taiFirmware(bool batBuoc){
  if(!g_sdOk) return "The SD chua san sang";
  if(_cfgFwUrl.length() == 0) return "Chua khai link firmware (fwUrl) o phan cau hinh";
  if(!noiInternet()) return "Khong noi duoc WiFi Internet — kiem ten/mat khau WiFi";

  String url = _cfgFwUrl, ver = "";
  // (1) latest.json -> lấy ver + url thật
  if(url.endsWith(".json")){
    WiFiClientSecure c1; c1.setInsecure();
    HTTPClient h1; h1.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS); h1.setTimeout(15000);
    if(!h1.begin(c1, url)) return "Khong mo duoc latest.json";
    int code = h1.GET();
    if(code != 200){ h1.end(); return "latest.json HTTP " + String(code); }
    String body = h1.getString(); h1.end();
    StaticJsonDocument<512> d;
    if(deserializeJson(d, body)) return "latest.json khong phai JSON hop le";
    ver = String((const char*)(d["ver"] | ""));
    url = String((const char*)(d["url"] | ""));
    // ⚠️ CHAN NHAM LOAI: the SD nay de di nap cho MAY CHINH. Dan nham link firmware may tram
    //    vao day la mang di nap sai firmware cho ca chuoi cua hang.
    String loai = String((const char*)(d["loai"] | ""));
    if(loai.length() && loai != String(LOAI_MC))
      return "File nay la firmware '" + loai + "', KHONG phai cua may cham cong — tu choi tai";
    if(url.length() == 0) return "latest.json thieu 'url'";
    if(!batBuoc && ver.length() && ver == _cfgFwVer && g_fwSize > 0)
      return "Da co ban " + ver + " tren the roi (bam Tai lai neu muon tai de)";
  }

  // (2) tải .bin
  ghiTinhTrang("Dang tai " + (ver.length() ? ver : String("firmware")) + "...");
  ttMo("DANG TAI VE THE", TFT_ORANGE, (ver.length() ? ver : String("firmware")),
       "Dang tai — dung rut the", TFT_YELLOW);
  ttPct(0);
  WiFiClientSecure c2; c2.setInsecure();
  HTTPClient h2; h2.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS); h2.setTimeout(20000);
  if(!h2.begin(c2, url)) return "Khong mo duoc link .bin";
  int code = h2.GET();
  if(code != 200){ h2.end(); return "Tai .bin HTTP " + String(code); }
  int tong = h2.getSize();
  if(tong <= 0){ h2.end(); return "May chu khong cho biet do dai file (Content-Length) — khong dam ghi vao the"; }

  SD.remove("/firmware.new");
  File f = SD.open("/firmware.new", FILE_WRITE);
  if(!f){ h2.end(); return "Khong ghi duoc vao the SD"; }

  WiFiClient* st = h2.getStreamPtr();
  uint8_t buf[1024]; int daGhi = 0, lanCuoi = -1;
  unsigned long tCuoi = millis();
  while(daGhi < tong){
    size_t co = st->available();
    if(co){
      int n = st->readBytes(buf, co > sizeof(buf) ? sizeof(buf) : co);
      if(n <= 0) break;
      if((int)f.write(buf, n) != n){ f.close(); SD.remove("/firmware.new"); h2.end(); return "The SD ghi loi (het cho?)"; }
      daGhi += n; tCuoi = millis();
      int pct = (int)((long)daGhi * 100 / tong);
      if(pct != lanCuoi && pct % 2 == 0){ lanCuoi = pct;
        ttPct(pct); }
    } else {
      if(!st->connected() && !st->available()) break;
      if(millis() - tCuoi > 20000) break;          // đứt mạng giữa đường
      delay(5);
    }
  }
  f.close(); h2.end();

  if(daGhi != tong){                               // THIẾU BYTE -> bỏ file mới, GIỮ file cũ
    SD.remove("/firmware.new");
    ghiTinhTrang("Tai DO DANG " + String(daGhi) + "/" + String(tong) + " byte — GIU nguyen file cu tren the");
    return "Tai do dang (" + String(daGhi) + "/" + String(tong) + " byte). File cu tren the KHONG bi mat.";
  }

  SD.remove(FW_PATH);
  if(!SD.rename("/firmware.new", FW_PATH)){
    ghiTinhTrang("Doi ten file that bai");
    return "Tai xong nhung doi ten that bai — rut the kiem lai";
  }
  if(ver.length()){ prefs.putString("fwVer", ver); _cfgFwVer = ver; }
  doLaiCoFile();
  g_doneN = 0;                                     // bản mới -> cho nạp lại các máy đã nạp phiên trước
  ghiTinhTrang("Da tai xong " + (ver.length()?ver:String("")) + " " + String(g_fwSize/1024) + " KB vao the");
  return "";
}

/* ---------- TỰ NÂNG CẤP CHÍNH MÁY TRẠM NÀY ----------
 * Trả chuỗi rỗng nếu OK (rồi khởi động lại), ngược lại là lý do lỗi.
 *
 * ⚠️ KHÁC HẲN taiFirmware(): hàm kia tải firmware MÁY CHÍNH về THẺ để đi nạp cho máy khác.
 *    Hàm này ghi thẳng vào phân vùng app của CHÍNH MÁY TRẠM. Nạp nhầm firmware máy chấm
 *    công vào đây là máy trạm thành vô dụng, phải cắm USB nạp lại.
 *
 * Ba lớp chặn nhầm:
 *    1. Link riêng (fwTramUrl), không dùng chung fwUrl.
 *    2. BẮT BUỘC latest-tram.json có "loai":"may-tram" — thiếu trường này cũng TỪ CHỐI.
 *       (taiFirmware chỉ từ chối khi loai SAI, vì latest.json bản cũ chưa có trường đó.
 *        Ở đây chặt hơn: hậu quả nặng hơn hẳn.)
 *    3. Update.begin() kiểm vừa phân vùng; ghi thiếu byte thì Update.end() thất bại và
 *       máy GIỮ NGUYÊN firmware cũ — ESP32 chỉ chuyển sang phân vùng mới khi ghi trọn vẹn.
 */
String tuNangCap(bool batBuoc){
  if(_cfgFwTramUrl.length() == 0) return "Chua khai link firmware may tram (fwTramUrl)";
  if(!_cfgFwTramUrl.endsWith(".json")) return "Link may tram phai la latest-tram.json, khong nhan link .bin truc tiep";
  if(!noiInternet()) return "Khong noi duoc WiFi Internet — kiem ten/mat khau WiFi";

  WiFiClientSecure c1; c1.setInsecure();
  HTTPClient h1; h1.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS); h1.setTimeout(15000);
  if(!h1.begin(c1, _cfgFwTramUrl)) return "Khong mo duoc latest-tram.json";
  int code = h1.GET();
  if(code != 200){ h1.end(); return "latest-tram.json HTTP " + String(code)
     + (code==404 ? " — chua co ban phat hanh nao cho may tram" : ""); }
  String body = h1.getString(); h1.end();

  StaticJsonDocument<512> d;
  if(deserializeJson(d, body)) return "latest-tram.json khong phai JSON hop le";
  String loai = String((const char*)(d["loai"] | ""));
  if(loai != String(LOAI_TRAM))
    return "File nay la firmware '" + (loai.length()?loai:String("(khong ro loai)"))
         + "' — KHONG phai cua may tram. TU CHOI nap de khoi hong may.";
  String ver = String((const char*)(d["ver"] | ""));
  String url = String((const char*)(d["url"] | ""));
  if(url.length() == 0) return "latest-tram.json thieu 'url'";
  if(!batBuoc && ver.length() && ver == _cfgTramVer)
    return "May tram dang chay ban " + ver + " roi (bam Ep nang cap neu muon nap de)";

  ghiTinhTrang("Dang tai ban may tram " + (ver.length()?ver:String("")) + "...");
  WiFiClientSecure c2; c2.setInsecure();
  HTTPClient h2; h2.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS); h2.setTimeout(20000);
  if(!h2.begin(c2, url)) return "Khong mo duoc link .bin may tram";
  code = h2.GET();
  if(code != 200){ h2.end(); return "Tai .bin may tram HTTP " + String(code); }
  int tong = h2.getSize();
  if(tong <= 0){ h2.end(); return "May chu khong bao Content-Length — khong dam nap"; }
  if(!Update.begin(tong)){ h2.end(); return "Khong du cho phan vung app (" + String(tong) + " byte)"; }

  WiFiClient* st = h2.getStreamPtr();
  uint8_t buf[1024]; int daGhi = 0; unsigned long t0 = millis();
  while(h2.connected() && daGhi < tong){
    size_t co = st->available();
    if(co){
      int n = st->readBytes(buf, co > sizeof(buf) ? sizeof(buf) : co);
      if(Update.write(buf, n) != (size_t)n){ Update.abort(); h2.end();
        return "Ghi phan vung that bai o byte " + String(daGhi); }
      daGhi += n; t0 = millis();
      if(daGhi % 65536 < 1024) ghiTinhTrang("Nang cap " + String(daGhi*100/tong) + "%");
    } else if(millis() - t0 > 20000){ Update.abort(); h2.end();
      return "Mat mang giua chung (" + String(daGhi) + "/" + String(tong) + ") — GIU firmware cu"; }
    delay(1);
  }
  h2.end();
  if(daGhi != tong){ Update.abort();
    return "Tai do dang " + String(daGhi) + "/" + String(tong) + " — GIU firmware cu"; }
  if(!Update.end(true)){ return "Update.end that bai (ma " + String(Update.getError()) + ") — GIU firmware cu"; }

  // Ghi ver TRUOC khi restart, va chi khi Update.end da thanh cong -> lan sau biet dang chay ban nao.
  if(ver.length()) prefs.putString("tramVer", ver);
  ghiTinhTrang("Da nang cap may tram len " + ver + " — dang khoi dong lai");
  delay(800);
  ESP.restart();
  return "";
}

/* ================= PORTAL TRÊN ĐIỆN THOẠI =================
 * Vì sao dùng portal chứ không dùng cảm ứng màn CYD: firmware này chưa hề dùng cảm ứng,
 * bật lên phải sửa User_Setup.h (thêm TOUCH_CS) — dễ vỡ mà không kiểm được ở đây.
 * Máy chấm công đã có portal sẵn nên làm giống là chắc tay nhất.
 */
String _esc(const String& s){
  String o; for(unsigned i=0;i<s.length();i++){ char c=s[i];
    if(c=='<') o+="&lt;"; else if(c=='>') o+="&gt;"; else if(c=='&') o+="&amp;"; else if(c=='"') o+="&quot;"; else o+=c; }
  return o;
}
// Quét máy chấm công ở gần -> JSON cho portal
String quetMayJson(){
  int n = WiFi.scanNetworks();
  String j = "[";  bool dau = true;
  for(int i=0;i<n;i++){
    String s = WiFi.SSID(i); int r = WiFi.RSSI(i);
    if(!laMayChamCong(s)) continue;
    String bs = WiFi.BSSIDstr(i);              // MAC của AP -> DANH TÍNH THẬT của máy
    if(!dau) j += ","; dau = false;
    j += "{\"ssid\":\"" + s + "\",\"bssid\":\"" + bs + "\",\"ch\":" + String(WiFi.channel(i))
       + ",\"rssi\":" + String(r) + ",\"gan\":" + String(r >= NEAR_RSSI ? 1 : 0)
       + ",\"daNap\":" + String(isDone(bs) ? 1 : 0) + "}";
  }
  WiFi.scanDelete();
  return j + "]";
}
void hTrangChinh(){
  String h = "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  h += "<title>Nap firmware</title><style>body{font-family:system-ui,Arial;background:#0f172a;color:#e2e8f0;padding:14px;margin:0}";
  h += ".c{background:#16233c;border:1px solid #24365c;border-radius:12px;padding:14px;margin-bottom:12px}";
  h += "h2{color:#38bdf8;font-size:17px;margin:0 0 8px}.m{color:#94a3b8;font-size:13px;line-height:1.5}";
  h += "button{width:100%;padding:13px;border:0;border-radius:10px;font-size:16px;font-weight:700;margin-top:8px}";
  h += ".p{background:#2563eb;color:#fff}.g{background:#16a34a;color:#fff}.w{background:#334155;color:#e2e8f0}";
  h += "input{width:100%;padding:10px;margin-top:6px;border-radius:8px;border:1px solid #334155;background:#0b1220;color:#e2e8f0;box-sizing:border-box}";
  h += ".r{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 0;border-bottom:1px solid #24365c}";
  h += ".b{background:#2563eb;color:#fff;border:0;border-radius:8px;padding:9px 13px;font-weight:700;width:auto;margin:0}</style>";

  h += "<div class='c'><h2>&#128190; The SD</h2><div class='m'>";
  if(!g_sdOk)           h += "<b style='color:#f87171'>KHONG doc duoc the SD</b> — cam lai the roi bat lai may.";
  else if(g_fwSize <= 0) h += "<b style='color:#fbbf24'>The chua co firmware.bin</b> — bam \"Tai ban moi\" ben duoi.";
  else                   h += "Co <b>firmware.bin</b> " + String(g_fwSize/1024) + " KB"
                            + (_cfgFwVer.length() ? (" &middot; ban <b>" + _esc(_cfgFwVer) + "</b>") : "");
  h += "</div></div>";

  h += "<div class='c'><h2>&#11015;&#65039; Tai ban moi ve the</h2>";
  h += "<div class='m'>May tu noi WiFi Internet roi ghi thang vao the CAM SAN — khoi rut the ra chep bang may tinh. "
       "Tai do dang thi <b>giu nguyen file cu</b>.</div>";
  h += "<button class='p' onclick=\"go('/tai','Tai ban moi ve the?')\">&#11015;&#65039; Tai ban moi</button>";
  h += "<button class='w' onclick=\"go('/tai?force=1','Tai DE len file dang co?')\">&#8635; Tai lai (ghi de)</button></div>";

  // Nang cap CHINH MAY NAY — de rieng mot khoi, mau khac, chu ro rang: dung de bam nham
  // voi khoi tren (khoi tren la tai firmware MAY CHAM CONG ve the).
  h += "<div class='c'><h2>&#128295; Nang cap CHINH MAY TRAM NAY</h2>";
  h += "<div class='m'>Khac han o tren: o tren la tai firmware <b>may cham cong</b> ve the de di nap cho may khac. "
       "Nut duoi day nap firmware <b>cua chinh cai may dang cam dien nay</b>, xong no tu khoi dong lai."
       "<br>Ban CI dang chay: <b>" + _esc(_cfgTramVer.length()?_cfgTramVer:String("(chua tung nang cap qua mang)"))
       + "</b><br>Ban trong code: <b>" + _esc(String(FW_VERSION)) + "</b></div>";
  // Nut xep DOC full-width giong moi khoi khac. Bo boc trong .r: class do la hang flex co gach
  // chan, dung cho danh sach may quet duoc — nhet nut vao la lech han bo cuc cac khoi con lai.
  h += "<button class='p' onclick=\"go('/tunangcap','Nang cap chinh may tram nay?')\">&#128295; Nang cap may tram</button>";
  h += "<button class='w' onclick=\"go('/tunangcap?force=1','EP nap de len ban dang chay?')\">&#8635; Ep nang cap</button></div>";

  h += "<div class='c'><h2>&#11014;&#65039; Nap cho may cham cong</h2>";
  h += "<div class='m'>Dung <b>gan may</b> can nap roi bam. Chi may co <b>song manh</b> moi nen nap — xa qua de dut giua duong.";
  if(g_tuDongNap) h += "<br><b style='color:#fbbf24'>Dang bat che do TU DONG nap may o gan.</b>";
  h += "</div><div id='ds' class='m'>Dang quet...</div>";
  h += "<button class='w' onclick='quet()'>&#8635; Quet lai</button></div>";

  h += "<div class='c'><h2>&#128272; Cau hinh</h2><div class='m'>Bo trong o nao la <b>giu nguyen</b> o do.<br>Dang co: "
       "mat khau AP may dich <b>" + _esc(cfgChe(_cfgApPass)) + "</b> &middot; mat khau /update <b>" + _esc(cfgChe(_cfgOtaPass)) + "</b>"
       "<br>WiFi Internet: <b>" + (_cfgStaSsid.length()?_esc(_cfgStaSsid):String("(chua khai)")) + "</b>"
       "<br>Link firmware: <b>" + (_cfgFwUrl.length()?_esc(_cfgFwUrl):String("(chua khai)")) + "</b></div>";
  h += "<input id='cSta'  placeholder='Ten WiFi co Internet (hotspot dien thoai duoc)'>";
  h += "<input id='cStaP' placeholder='Mat khau WiFi do'>";
  h += "<input id='cUrl'  placeholder='Link latest.json cho MAY CHAM CONG (de trong = dung mac dinh)'>";
  h += "<input id='cUrlT' placeholder='Link latest-tram.json cho CHINH MAY NAY (de trong = dung mac dinh)'>";
  h += "<input id='cAp'   placeholder='Mat khau AP cua may cham cong'>";
  h += "<input id='cOtaU' placeholder='Tai khoan trang /update (mac dinh admin)'>";
  h += "<input id='cOtaP' placeholder='Mat khau trang /update'>";
  h += "<input id='cSelf' placeholder='Mat khau AP cua CHINH may nay (>=8 ky tu, de trong = AP mo)'>";
  h += "<div class='m' style='margin-top:8px'><label><input type='checkbox' id='cAuto' style='width:auto'";
  if(g_tuDongNap) h += " checked";
  h += "> Tu dong nap may o gan (khong can bam)</label></div>";
  h += "<button class='g' onclick='luu()'>&#128190; Luu &amp; khoi dong lai</button></div>";

  h += "<div class='c'><div class='m'>Firmware may nay: <b>" FW_VERSION "</b><br>Trang thai: <span id='tt'>" + _esc(g_tinhTrang) + "</span></div></div>";

  h += "<script>function g(i){return document.getElementById(i);}";
  h += "function go(u,q){if(!confirm(q))return;g('tt').textContent='Dang lam...';"
       "fetch(u,{method:'POST'}).then(r=>r.text()).then(t=>{alert(t);location.reload();});}";
  // ⚠️ Gửi kèm BSSID + kênh. Mọi máy cùng tên AP nên chỉ BSSID mới chỉ đúng máy nào;
  //    thiếu nó là dễ nạp nhầm sang máy bàn bên.
  h += "function nap(s,b,c){if(!confirm('Nap firmware cho '+s+' '+b+'?\\n\\nDUNG TAT NGUON may dich.'))return;"
       "g('tt').textContent='Dang nap '+b+'...';"
       "fetch('/nap?ssid='+encodeURIComponent(s)+'&bssid='+encodeURIComponent(b)+'&ch='+c,{method:'POST'})"
       ".then(r=>r.text()).then(t=>{alert(t);location.reload();});}";
  h += "function quet(){g('ds').textContent='Dang quet...';fetch('/quet').then(r=>r.json()).then(a=>{"
       "if(!a.length){g('ds').textContent='Khong thay may cham cong nao o gan.';return;}"
       // Hiện 5 ký tự cuối BSSID -> anh phân biệt được các máy dù tên AP giống nhau hết
       "g('ds').innerHTML=a.map(m=>\"<div class='r'><span>\"+m.ssid+\" <b style='color:#38bdf8'>\"+m.bssid.slice(12)+\"</b> \"+m.rssi+\" dBm\"+"
       "(m.gan?\"\":\" <i style='color:#fbbf24'>(xa)</i>\")+(m.daNap?\" <i style='color:#4ade80'>(da nap)</i>\":\"\")+"
       "\"</span><button class='b' onclick=\\\"nap('\"+m.ssid+\"','\"+m.bssid+\"',\"+m.ch+\")\\\">Nap</button></div>\").join('');});}";
  h += "function luu(){var f={cSta:'staSsid',cStaP:'staPass',cUrl:'fwUrl',cUrlT:'fwTramUrl',cAp:'apPass',cOtaU:'otaUser',cOtaP:'otaPass',cSelf:'selfAp'};"
       "var b=['autoNap='+(g('cAuto').checked?'1':'0')];"
       "for(var k in f){var v=g(k).value.trim();if(v!=='')b.push(f[k]+'='+encodeURIComponent(v));}"
       "if(!confirm('Luu cau hinh va khoi dong lai?'))return;"
       "fetch('/savecfg',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.join('&')})"
       ".then(r=>r.text()).then(t=>alert(t));}";
  h += "quet();</script>";
  server.send(200, "text/html; charset=utf-8", h);
}
void hTuNangCap(){
  if(g_dangLamViec){ server.send(409, "text/plain; charset=utf-8", "Dang lam viec khac, cho xong da."); return; }
  g_dangLamViec = true;
  String loi = tuNangCap(server.hasArg("force"));
  g_dangLamViec = false;
  // Nang cap OK thi ESP.restart() da chay trong tuNangCap -> khong bao gio toi duoc dong duoi.
  if(loi.length()) server.send(200, "text/plain; charset=utf-8", "KHONG NANG CAP: " + loi);
  else             server.send(200, "text/plain; charset=utf-8", "Da nang cap, dang khoi dong lai...");
}
void hTai(){
  if(g_dangLamViec){ server.send(409, "text/plain; charset=utf-8", "Dang lam viec khac, cho xong da."); return; }
  g_dangLamViec = true;
  String loi = taiFirmware(server.hasArg("force"));
  g_dangLamViec = false;
  if(loi.length()) server.send(200, "text/plain; charset=utf-8", "KHONG XONG: " + loi);
  else             server.send(200, "text/plain; charset=utf-8", "Da tai xong vao the: " + String(g_fwSize/1024) + " KB");
}
void hQuet(){ server.send(200, "application/json", quetMayJson()); }
void hNap(){
  if(g_dangLamViec){ server.send(409, "text/plain; charset=utf-8", "Dang lam viec khac, cho xong da."); return; }
  String ssid  = server.arg("ssid");
  String bssid = server.arg("bssid");
  int    kenh  = server.arg("ch").toInt();
  if(!laMayChamCong(ssid)){ server.send(400, "text/plain; charset=utf-8", "Ten may khong hop le."); return; }
  // Tên giống nhau hết -> BSSID là thứ DUY NHẤT chỉ đúng máy nào. Thiếu là không dám nạp.
  if(bssid.length() < 17){ server.send(400, "text/plain; charset=utf-8",
    "Thieu BSSID — bam Quet lai roi chon may tu danh sach."); return; }
  if(g_fwSize <= 0){ server.send(400, "text/plain; charset=utf-8", "The chua co firmware.bin — bam Tai ban moi truoc."); return; }
  if(g_chuaCauHinh){ server.send(400, "text/plain; charset=utf-8", "Chua khai mat khau AP / mat khau /update — xem phan Cau hinh."); return; }
  g_dangLamViec = true;
  String ten = ssid + " " + bssid.substring(12);          // "CHAM_CONG 11:A4" cho dễ đọc
  ghiTinhTrang("Dang nap " + ten);
  bool ok = updateOne(ssid, bssid, kenh);
  markDone(bssid);                                         // theo BSSID, KHÔNG theo tên
  if(ok) g_okCount++; else g_failCount++;
  ghiTinhTrang(ok ? ("Nap XONG " + ten) : ("Nap LOI " + ten));
  // Nạp xong máy đích hạ AP -> nối lại WiFi Internet cho lần tải sau
  if(_cfgStaSsid.length()) noiInternet(8000);
  g_dangLamViec = false;
  server.send(200, "text/plain; charset=utf-8", ok ? ("XONG! " + ten + " dang khoi dong lai.")
                                                  : ("LOI khi nap " + ten + " — lai gan hon roi thu lai."));
}
void hLuuCfg(){
  struct { const char* arg; const char* khoa; } m[] = {
    {"staSsid","staSsid"}, {"staPass","staPass"}, {"fwUrl","fwUrl"}, {"fwTramUrl","fwTramUrl"},
    {"apPass","apPass"}, {"otaUser","otaUser"}, {"otaPass","otaPass"}, {"selfAp","selfAp"}
  };
  int n = 0; String loi = "";
  for(unsigned i=0;i<sizeof(m)/sizeof(m[0]);i++){
    if(!server.hasArg(m[i].arg)) continue;
    String v = server.arg(m[i].arg); v.trim();
    if(!v.length()) continue;
    if(String(m[i].khoa)=="selfAp" && v.length() < 8){ loi += "Mat khau AP may nay phai >=8 ky tu. "; continue; }
    prefs.putString(m[i].khoa, v); n++;
  }
  if(server.hasArg("autoNap")){ prefs.putString("autoNap", server.arg("autoNap")=="1" ? "1" : ""); n++; }
  if(loi.length()){ server.send(400, "text/plain; charset=utf-8", "KHONG luu: " + loi); return; }
  server.send(200, "text/plain; charset=utf-8", "Da luu " + String(n) + " gia tri. May khoi dong lai...");
  delay(600); ESP.restart();
}
void batPortal(){
  uint8_t mac[6]; WiFi.macAddress(mac);
  char ten[24]; snprintf(ten, sizeof(ten), "NapFW-%02X%02X", mac[4], mac[5]);
  // Chưa khai mật khẩu AP riêng -> mở AP, không thì không vào được portal mà khai cấu hình.
  if(_cfgSelfAp.length() >= 8) WiFi.softAP(ten, _cfgSelfAp.c_str());
  else                         WiFi.softAP(ten);
  dnsServer.start(53, "*", IPAddress(192,168,4,1));
  server.on("/",        HTTP_GET,  hTrangChinh);
  server.on("/quet",    HTTP_GET,  hQuet);
  server.on("/tai",     HTTP_POST, hTai);
  server.on("/tunangcap", HTTP_POST, hTuNangCap);
  server.on("/nap",     HTTP_POST, hNap);
  server.on("/savecfg", HTTP_POST, hLuuCfg);
  server.onNotFound(hTrangChinh);            // captive portal: gõ gì cũng về trang chính
  server.begin();
  Serial.printf("[AP] %s @ 192.168.4.1 (%s)\n", ten, _cfgSelfAp.length()>=8 ? "co mat khau" : "MO");
}

void setup(){
  Serial.begin(115200); delay(400);
  pinMode(BL_PIN, OUTPUT); digitalWrite(BL_PIN, HIGH);
  tft.init(); tft.setRotation(1); tft.fillScreen(TFT_BLACK);
  Serial.println("\n=== ESP32 THO NAP OTA ===");

  prefs.begin("napfw", false);
  napCauHinh();                       // bí mật từ NVS (giá trị compile là dự phòng, tự chép vào NVS)
  Serial.println("FW " FW_VERSION);

  // AP portal + STA Internet chạy SONG SONG: điện thoại vẫn giữ được trang khi máy đi tải.
  WiFi.mode(WIFI_AP_STA); WiFi.disconnect(true);
  batPortal();

  // Thẻ SD (SPI VSPI: SCK18 MISO19 MOSI23, CS=5)
  SPI.begin(18, 19, 23, SD_CS);
  g_sdOk = SD.begin(SD_CS);
  doLaiCoFile();
  Serial.printf("[SD] sdOk=%d firmware.bin=%ld byte\n", (int)g_sdOk, g_fwSize);

  if(_cfgStaSsid.length()) noiInternet(12000);   // có khai WiFi thì nối sẵn cho bấm Tải là chạy ngay

  if(!g_sdOk)
    scrLoi("LOI THE SD", "Cam lai the", "Roi bat lai may");
  else if(g_fwSize <= 0)
    scr("CHUA CO FILE", TFT_YELLOW, "Vao AP NapFW-*", TFT_CYAN, "192.168.4.1 -> bam Tai ban moi", TFT_DARKGREY);
  else
    scr("SAN SANG", TFT_GREEN, String(g_fwSize/1024) + " KB", TFT_WHITE,
        g_tuDongNap ? "Tu dong nap may o gan" : "Vao 192.168.4.1 de bam Nap", TFT_DARKGREY);
}

void loop(){
  server.handleClient();
  dnsServer.processNextRequest();

  // MẶC ĐỊNH: KHÔNG tự nạp. Trước bản này loop() quét thấy máy nào sóng >= NEAR_RSSI là
  // tự nối và nạp luôn — với thẻ cắm sẵn thường trực thì đi ngang máy nào là nạp máy đó.
  // Nay phải bấm trên portal. Ai muốn kiểu cũ thì bật "Tu dong nap" trong phần Cấu hình.
  if(!g_tuDongNap || g_dangLamViec || !g_sdOk || g_fwSize <= 0){ delay(20); return; }

  static unsigned long lanQuet = 0;
  if(millis() - lanQuet < 2500) { delay(20); return; }
  lanQuet = millis();

  int n = WiFi.scanNetworks();
  String best = "", bestBs = ""; int bestRssi = -999, bestCh = 0;
  for(int i = 0; i < n; i++){
    String s = WiFi.SSID(i); int r = WiFi.RSSI(i);
    if(!laMayChamCong(s)) continue;
    String bs = WiFi.BSSIDstr(i);
    if(isDone(bs)) continue;                // theo BSSID: tên giống nhau hết nên theo tên là bỏ sót máy
    if(r < NEAR_RSSI) continue;             // chỉ máy Ở GẦN (sóng mạnh) -> tránh nạp nhầm máy xa
    if(r > bestRssi){ bestRssi = r; best = s; bestBs = bs; bestCh = WiFi.channel(i); }
  }
  WiFi.scanDelete();

  if(best.length() == 0){
    scr("SAN SANG", TFT_GREEN, "Cho may...", TFT_WHITE, "Mang lai gan may can nap", TFT_DARKGREY);
    return;
  }

  Serial.printf("[QUET] Nap cho %s %s (rssi %d)\n", best.c_str(), bestBs.c_str(), bestRssi);
  g_dangLamViec = true;
  bool ok = updateOne(best, bestBs, bestCh);
  markDone(bestBs);                            // đánh dấu để khỏi nạp lại trong phiên (kể cả khi lỗi -> di chuyển máy khác; muốn thử lại thì reset)
  g_dangLamViec = false;
  if(ok){ g_okCount++; scr("XONG!", TFT_GREEN, best, TFT_WHITE, "May dich dang khoi dong lai...", TFT_DARKGREY); }
  else  { g_failCount++; scrLoi("LOI - THU LAI", best, "Lai gan hon / bat lai may dich"); }
  delay(4000);                               // cho xem kết quả + máy đích kịp restart/hạ AP
}
