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

#define FW_VERSION "2026-08-01c (nut BOOT lam nut nap: nhan=chon, giu 2s=NAP, giu 5s=tu dong)"

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
/* =====================================================================================
 *  CẢM ỨNG XPT2046 — SPI MỀM, CỐ Ý KHÔNG DÙNG TOUCH_CS CỦA TFT_eSPI
 * -------------------------------------------------------------------------------------
 *  Vì sao SPI mềm: trên bo CYD, cảm ứng KHÔNG chung bus với màn hình.
 *      Màn:     SCLK 14, MOSI 13, MISO 12, CS 15   (User_Setup.h)
 *      Cảm ứng: SCLK 25, MOSI 32, MISO 39, CS 33   (bus RIÊNG)
 *      Thẻ SD:  SCLK 18, MISO 19, MOSI 23, CS 5    (bus thứ ba)
 *  TOUCH_CS của TFT_eSPI giả định cảm ứng chung bus với màn -> thêm vào KHÔNG chạy, mà
 *  còn phải sửa User_Setup.h — file đó DÙNG CHUNG cho cả máy chính và nằm trong 5 file
 *  job `doichieu` đối chiếu. Sửa là đụng cả máy chính. XPT2046 chỉ chạy ~2 MHz nên
 *  bit-bang thừa sức, và không tranh bus với ai.
 *
 *  ⚠️ HIỆU CHỈNH LÀ DỮ LIỆU, KHÔNG PHẢI MÃ. Không ai kiểm được cảm ứng bằng máy ảo, nên
 *     4 mốc + 3 cờ đảo/hoán nằm trong NVS, sửa ở portal hoặc màn CALIB — khỏi nạp lại.
 *     Bấm lệch thì vào màn CALIB đọc số thô rồi khai lại, không phải sửa firmware.
 * ===================================================================================== */
/* ⚠️ 01/08/2026 — CHÂN LÀ DỮ LIỆU, KHÔNG PHẢI MÃ.
   Bản đầu #define cứng 5 chân. Cảm ứng không phản hồi thì phải đoán từng chân, mỗi lần
   đoán là nạp lại USB — 5 chân là 5 lượt. Nay chân nằm trong NVS, sửa ở /tpcal hoặc đọc
   log chẩn đoán rồi khai lại. Mặc định theo bo CYD ESP32-2432S028R. */
int  g_tpClk = 25, g_tpDin = 32, g_tpDo = 39, g_tpCs = 33, g_tpIrq = 36;
/* Có dùng chân IRQ để biết "có ai chạm" không.
   ⚠️ ĐÂY LÀ CHỖ CHẾT IM LẶNG: bản đầu chặn cứng `if (IRQ == HIGH) return false;`. Chân đó
   không nối đúng thì nó luôn HIGH -> KHÔNG BAO GIỜ đọc cảm ứng, mà không in ra một chữ nào.
   Đặt tpDungIrq=0 thì bỏ qua IRQ, chỉ xét lực nhấn Z — chậm hơn chút nhưng chạy được
   trên bo lô khác chân. */
bool g_tpDungIrq = true;
bool g_tpDebug   = false;      // in số thô ra Serial mỗi 500ms để chẩn đoán

int  g_tpXMin = 300, g_tpXMax = 3800, g_tpYMin = 300, g_tpYMax = 3800;
bool g_tpSwap = true, g_tpInvX = false, g_tpInvY = true;   // mặc định cho CYD ở setRotation(1)
bool g_tpCo   = false;                                     // có đọc được cảm ứng lần nào chưa

void tpNapCauHinh(){
  g_tpXMin = prefs.getInt("tpXMin", g_tpXMin);
  g_tpXMax = prefs.getInt("tpXMax", g_tpXMax);
  g_tpYMin = prefs.getInt("tpYMin", g_tpYMin);
  g_tpYMax = prefs.getInt("tpYMax", g_tpYMax);
  g_tpSwap = prefs.getString("tpSwap", g_tpSwap ? "1" : "0") == "1";
  g_tpInvX = prefs.getString("tpInvX", g_tpInvX ? "1" : "0") == "1";
  g_tpInvY = prefs.getString("tpInvY", g_tpInvY ? "1" : "0") == "1";
  g_tpClk  = prefs.getInt("tpClk", g_tpClk);
  g_tpDin  = prefs.getInt("tpDin", g_tpDin);
  g_tpDo   = prefs.getInt("tpDo",  g_tpDo);
  g_tpCs   = prefs.getInt("tpCs",  g_tpCs);
  g_tpIrq  = prefs.getInt("tpIrq", g_tpIrq);
  g_tpDungIrq = prefs.getString("tpIrqOn", g_tpDungIrq ? "1" : "0") == "1";
  g_tpDebug   = prefs.getString("tpDebug", "0") == "1";
}
void tpKhoiDong(){
  tpNapCauHinh();                                   // đọc chân TRƯỚC khi pinMode
  pinMode(g_tpClk, OUTPUT); pinMode(g_tpDin, OUTPUT); pinMode(g_tpCs, OUTPUT);
  pinMode(g_tpDo, INPUT);   pinMode(g_tpIrq, INPUT);
  digitalWrite(g_tpCs, HIGH); digitalWrite(g_tpClk, LOW);
  Serial.printf("[TP] chan CLK=%d DIN=%d DO=%d CS=%d IRQ=%d  dungIrq=%d debug=%d\n",
                g_tpClk, g_tpDin, g_tpDo, g_tpCs, g_tpIrq, (int)g_tpDungIrq, (int)g_tpDebug);
  Serial.println("[TP] Cam ung khong phan hoi? Bat chan doan: POST /tpcal?debug=1  (hoac tpIrqOn=0)");
}
/* 1 lượt trao đổi: gửi 8 bit lệnh, 1 xung rỗng, rồi đọc 12 bit (MSB trước). */
static uint16_t tpHoi(uint8_t lenh){
  for (int i = 7; i >= 0; i--){
    digitalWrite(g_tpDin, (lenh >> i) & 1);
    digitalWrite(g_tpClk, HIGH); delayMicroseconds(1);
    digitalWrite(g_tpClk, LOW);  delayMicroseconds(1);
  }
  digitalWrite(g_tpClk, HIGH); delayMicroseconds(1); digitalWrite(g_tpClk, LOW);  // xung rỗng
  uint16_t v = 0;
  for (int i = 0; i < 12; i++){
    digitalWrite(g_tpClk, HIGH); delayMicroseconds(1);
    v = (uint16_t)((v << 1) | (digitalRead(g_tpDo) ? 1 : 0));
    digitalWrite(g_tpClk, LOW);  delayMicroseconds(1);
  }
  return v;
}
/* Đọc thô. Trả false khi không có ai chạm. Lấy TRUNG VỊ của 3 lần: nhiễu 1 lần không lọt. */
bool tpDocTho(uint16_t* rx, uint16_t* ry, uint16_t* rz){
  if (g_tpDungIrq && digitalRead(g_tpIrq) == HIGH) return false;   // chưa chạm (theo IRQ)
  uint16_t xs[3], ys[3], z = 0;
  digitalWrite(g_tpCs, LOW); delayMicroseconds(2);
  z = tpHoi(0xB1);                                        // Z1
  for (int k = 0; k < 3; k++){ ys[k] = tpHoi(0x91); xs[k] = tpHoi(0xD1); }
  tpHoi(0xD0);                                            // lượt cuối để chip nghỉ
  digitalWrite(g_tpCs, HIGH);
  // trung vị 3 phần tử, không cần sắp xếp
  #define _TPMID(a,b,c) ( (a)>(b) ? ((b)>(c)?(b):((a)>(c)?(c):(a))) : ((a)>(c)?(a):((b)>(c)?(c):(b))) )
  uint16_t x = _TPMID(xs[0], xs[1], xs[2]);
  uint16_t y = _TPMID(ys[0], ys[1], ys[2]);
  #undef _TPMID
  if (z < 200) return false;                              // chạm quá nhẹ -> bỏ, tránh bấm oan
  if (x < 50 || y < 50 || x > 4000 || y > 4000) return false;
  if (rx) *rx = x; if (ry) *ry = y; if (rz) *rz = z;
  g_tpCo = true;
  return true;
}
/* CHẨN ĐOÁN: đọc BỎ QUA mọi cổng chặn, in hết ra Serial.
   Có hàm này thì không phải đoán chân — nhìn số là biết SPI có nói chuyện được không:
     · Z luôn 0 và X/Y luôn 0 (hoặc luôn 4095) -> SPI/chân sai
     · Z nhảy lên khi chạm                     -> SPI TỐT, chỉ còn hiệu chỉnh trục
     · IRQ luôn =1 dù đang chạm                -> chân IRQ sai -> đặt tpIrqOn=0 là chạy
*/
void tpChanDoan(){
  static unsigned long lan = 0;
  if (!g_tpDebug || millis() - lan < 500) return;
  lan = millis();
  int irq = digitalRead(g_tpIrq);
  uint16_t z, ys[1], xs[1];
  digitalWrite(g_tpCs, LOW); delayMicroseconds(2);
  z     = tpHoi(0xB1);
  ys[0] = tpHoi(0x91);
  xs[0] = tpHoi(0xD1);
  tpHoi(0xD0);
  digitalWrite(g_tpCs, HIGH);
  Serial.printf("[TP] IRQ=%d  Z=%4u  X=%4u  Y=%4u\n", irq, z, xs[0], ys[0]);
}
/* Đổi số thô sang toạ độ màn 320x240. TÁCH RIÊNG khỏi phần đọc để test được bằng g++. */
void tpDoiToaDo(uint16_t rx, uint16_t ry, int* sx, int* sy,
                int xmin, int xmax, int ymin, int ymax, bool swap, bool invX, bool invY){
  long a = rx, b = ry;
  long ax = (xmax != xmin) ? ( (a - xmin) * 1000L / (xmax - xmin) ) : 0;   // 0..1000
  long ay = (ymax != ymin) ? ( (b - ymin) * 1000L / (ymax - ymin) ) : 0;
  if (ax < 0) ax = 0; if (ax > 1000) ax = 1000;
  if (ay < 0) ay = 0; if (ay > 1000) ay = 1000;
  long px = swap ? ay : ax;            // ở setRotation(1) trục cảm ứng thường hoán so với màn
  long py = swap ? ax : ay;
  if (invX) px = 1000 - px;
  if (invY) py = 1000 - py;
  if (sx) *sx = (int)(px * 319 / 1000);
  if (sy) *sy = (int)(py * 239 / 1000);
}
/* Một lần CHẠM đã nhả (nhấn-nhả), có chống dội. Trả false nếu không có gì. */
bool tpCham(int* sx, int* sy){
  static unsigned long lanCuoi = 0;
  if (millis() - lanCuoi < 250) return false;
  uint16_t rx, ry, rz;
  if (!tpDocTho(&rx, &ry, &rz)) return false;
  tpDoiToaDo(rx, ry, sx, sy, g_tpXMin, g_tpXMax, g_tpYMin, g_tpYMax, g_tpSwap, g_tpInvX, g_tpInvY);
  unsigned long t0 = millis();
  while (digitalRead(g_tpIrq) == LOW && millis() - t0 < 1500) delay(10);   // chờ nhả
  lanCuoi = millis();
  return true;
}
/* Điểm có nằm trong ô chữ nhật? Tách riêng để test được. */
bool tpTrong(int x, int y, int ox, int oy, int ow, int oh){
  return x >= ox && x < ox + ow && y >= oy && y < oy + oh;
}

/* =====================================================================================
 *  NÚT BOOT (GPIO0) LÀM NÚT NẠP — đường ĐIỀU KHIỂN CHÍNH
 * -------------------------------------------------------------------------------------
 *  Vì sao: cảm ứng không phản hồi trên bo thật (thử 2 bản, cả khi bỏ qua chân IRQ), mà
 *  điện thoại anh Thắng cũng không nối được AP nên portal vô dụng ngoài hiện trường.
 *  Nút BOOT thì CHẮC CHẮN có trên mọi bo ESP32, là nút cơ thật, không cần hiệu chỉnh,
 *  không dùng SPI, không có chân nào phải đoán. Một nút là đủ nếu phân theo THỜI GIAN GIỮ.
 *
 *      Nhấn nhả nhanh  (< 800ms)   -> chọn máy kế tiếp (danh sách rỗng thì QUÉT LẠI)
 *      Giữ 2 giây                  -> NẠP cho máy đang chọn (màn đếm ngược, nhả tay là HUỶ)
 *      Giữ 5 giây                  -> bật/tắt chế độ tự động
 *
 *  ⚠️ GPIO0 là chân quyết định chế độ khởi động. ĐANG CHẠY thì đọc thoải mái, nhưng
 *     ĐỪNG GIỮ NÚT LÚC CẤP ĐIỆN / RESET — giữ lúc đó là bo vào chế độ nạp qua USB và
 *     không chạy chương trình. Nhấn sau khi máy đã lên màn thì không sao.
 * ===================================================================================== */
#define NUT_BOOT 0
const unsigned long NUT_NGAN_MS = 800;    // dưới mức này = nhấn nhả nhanh
const unsigned long NUT_NAP_MS  = 2000;   // giữ tới đây = nạp
const unsigned long NUT_AUTO_MS = 5000;   // giữ tới đây = bật/tắt tự động

/* Phân loại một lần nhấn theo thời gian giữ. TÁCH RIÊNG để test được bằng g++ —
   phần đọc chân thì không test được, nhưng phần QUYẾT ĐỊNH thì phải chắc. */
#define NUT_KHONG 0
#define NUT_NGAN  1
#define NUT_NAP   2
#define NUT_AUTO  3
int nutPhanLoai(unsigned long giuMs){
  if (giuMs < 40)            return NUT_KHONG;   // nhiễu / dội tiếp xúc
  if (giuMs < NUT_NGAN_MS)   return NUT_NGAN;
  if (giuMs < NUT_NAP_MS)    return NUT_KHONG;   // vùng chết: giữ lỡ cỡ thì KHÔNG làm gì
  if (giuMs < NUT_AUTO_MS)   return NUT_NAP;
  return NUT_AUTO;
}
void nutKhoiDong(){ pinMode(NUT_BOOT, INPUT_PULLUP); }
bool nutDangNhan(){ return digitalRead(NUT_BOOT) == LOW; }   // BOOT kéo xuống GND khi nhấn

/* ============ MÀN CẢM ỨNG: danh sách máy + nút ============ */
#define MAN_DS      0
#define MAN_XACNHAN 1
#define MAN_CALIB   2
int g_man = MAN_DS;

#define TP_MAX_MAY 4
struct MayGan { String ssid, bssid; int ch, rssi; bool daNap; };
MayGan g_dsMay[TP_MAX_MAY]; int g_soMay = 0;
int    g_chon = -1;

// Toạ độ các ô bấm — MỘT định nghĩa duy nhất, cả phần vẽ và phần bắt chạm đều dùng.
const int O_QUET_X = 246, O_QUET_Y = 6,   O_QUET_W = 68, O_QUET_H = 30;
const int O_HANG_X = 8,   O_HANG_Y = 44,  O_HANG_W = 304, O_HANG_H = 36;
const int O_HANG_CACH = 40;
const int O_AUTO_X = 8,   O_AUTO_Y = 202, O_AUTO_W = 150, O_AUTO_H = 32;
const int O_CAL_X  = 246, O_CAL_Y  = 202, O_CAL_W  = 68,  O_CAL_H  = 32;
const int O_HUY_X  = 20,  O_HUY_Y  = 170, O_HUY_W  = 130, O_HUY_H  = 44;
const int O_NAP_X  = 170, O_NAP_Y  = 170, O_NAP_W  = 130, O_NAP_H  = 44;

static void _nut(int x, int y, int w, int h, const String& chu, uint16_t nen, uint16_t chuMau, int font){
  tft.fillRoundRect(x, y, w, h, 6, nen);
  tft.drawRoundRect(x, y, w, h, 6, TFT_DARKGREY);
  tft.setTextDatum(MC_DATUM); tft.setTextColor(chuMau, nen);
  tft.drawString(chu, x + w/2, y + h/2, font);
}
void tpQuetVaoDs(){
  int n = WiFi.scanNetworks();
  g_soMay = 0;
  for (int i = 0; i < n && g_soMay < TP_MAX_MAY; i++){
    String sd = WiFi.SSID(i);
    if (!laMayChamCong(sd)) continue;
    g_dsMay[g_soMay].ssid  = sd;
    g_dsMay[g_soMay].bssid = WiFi.BSSIDstr(i);
    g_dsMay[g_soMay].ch    = WiFi.channel(i);
    g_dsMay[g_soMay].rssi  = WiFi.RSSI(i);
    g_dsMay[g_soMay].daNap = isDone(g_dsMay[g_soMay].bssid);
    g_soMay++;
  }
  WiFi.scanDelete();
}
void veManDs(){
  tft.fillScreen(TFT_BLACK); veKhung();
  tft.setTextDatum(TL_DATUM); tft.setTextColor(colChay(), TFT_BLACK);
  tft.drawString("NAP FIRMWARE", 12, 12, 4);
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.drawString(g_fwSize > 0 ? (String(g_fwSize/1024) + " KB") : "CHUA CO FILE", 12, 40, 2);
  _nut(O_QUET_X, O_QUET_Y, O_QUET_W, O_QUET_H, "QUET", colVien(), TFT_WHITE, 2);
  if (g_soMay == 0){
    tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_YELLOW, TFT_BLACK);
    tft.drawString("Khong thay may nao", 160, 120, 4);
    tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
    tft.drawString("Lai gan may cham cong roi bam QUET", 160, 150, 2);
  } else {
    for (int i = 0; i < g_soMay; i++){
      int y = O_HANG_Y + i * O_HANG_CACH;
      bool gan = g_dsMay[i].rssi >= NEAR_RSSI;
      // xanh lá = ở gần, nạp được ngay · xanh dương = xa · xám = đã nạp phiên này
      uint16_t nen = g_dsMay[i].daNap ? TFT_DARKGREY : (gan ? colChay() : colVien());
      _nut(O_HANG_X, y, O_HANG_W, O_HANG_H, "", nen, TFT_WHITE, 2);
      // Viền trắng dày = máy ĐANG CHỌN (nút BOOT nhấn nhả nhanh để đổi)
      if (i == g_chon){
        tft.drawRoundRect(O_HANG_X-2, y-2, O_HANG_W+4, O_HANG_H+4, 7, TFT_WHITE);
        tft.drawRoundRect(O_HANG_X-3, y-3, O_HANG_W+6, O_HANG_H+6, 8, TFT_WHITE);
      }
      tft.setTextDatum(TL_DATUM); tft.setTextColor(TFT_WHITE, nen);
      // 5 ký tự cuối BSSID = danh tính máy, vì MỌI máy giờ cùng tên AP "CHAM_CONG"
      String bs = g_dsMay[i].bssid;
      tft.drawString(g_dsMay[i].ssid + "  " + bs.substring(bs.length() >= 5 ? bs.length()-5 : 0),
                     O_HANG_X + 10, y + 10, 2);
      tft.setTextDatum(TR_DATUM);
      tft.drawString(String(g_dsMay[i].rssi) + "dBm" + (g_dsMay[i].daNap ? " da nap" : ""),
                     O_HANG_X + O_HANG_W - 10, y + 10, 2);
    }
  }
  _nut(O_AUTO_X, O_AUTO_Y, O_AUTO_W, O_AUTO_H,
       g_tuDongNap ? "TU DONG: BAT" : "TU DONG: TAT",
       g_tuDongNap ? colDo() : colVien(), TFT_WHITE, 2);
  _nut(O_CAL_X, O_CAL_Y, O_CAL_W, O_CAL_H, "CALIB", colVien(), TFT_WHITE, 2);
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
  tft.drawString("OK:" + String(g_okCount) + "  Loi:" + String(g_failCount), 200, 218, 2);
  // Hướng dẫn nút BOOT ngay trên màn — không ai phải nhớ, không cần tra tài liệu
  tft.setTextColor(colChay(), TFT_BLACK);
  tft.drawString("BOOT: nhan = chon may  |  giu 2s = NAP", 160, 190, 2);
}
void veManXacNhan(){
  tft.fillScreen(TFT_BLACK); veKhung();
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_ORANGE, TFT_BLACK);
  tft.drawString("NAP CHO MAY NAY?", 160, 40, 4);
  if (g_chon >= 0 && g_chon < g_soMay){
    String bs = g_dsMay[g_chon].bssid;
    tft.setTextColor(TFT_WHITE, TFT_BLACK);  tft.drawString(bs, 160, 85, 2);
    tft.setTextColor(colChay(), TFT_BLACK);
    tft.drawString(bs.substring(bs.length() >= 5 ? bs.length()-5 : 0), 160, 120, 4);
    tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
    tft.drawString(String(g_dsMay[g_chon].rssi) + " dBm", 160, 150, 2);
  }
  _nut(O_HUY_X, O_HUY_Y, O_HUY_W, O_HUY_H, "HUY", colVien(), TFT_WHITE, 4);
  _nut(O_NAP_X, O_NAP_Y, O_NAP_W, O_NAP_H, "NAP", colChay(), TFT_WHITE, 4);
}
/* Màn CALIB: in SỐ THÔ để hiệu chỉnh được mà không phải nạp lại firmware.
   Không có màn này thì bấm lệch là bó tay, vì cảm ứng không test được bằng máy ảo. */
void veManCalib(){
  tft.fillScreen(TFT_BLACK); veKhung();
  tft.setTextDatum(TL_DATUM); tft.setTextColor(TFT_CYAN, TFT_BLACK);
  tft.drawString("CALIB cam ung", 12, 12, 4);
  tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
  tft.drawString("Cham 4 goc, doc so tho ben duoi.", 12, 44, 2);
  tft.drawString("Khai lai o portal: /tpcal", 12, 62, 2);
  _nut(O_CAL_X, O_CAL_Y, O_CAL_W, O_CAL_H, "XONG", colVien(), TFT_WHITE, 2);
}
void veCalibSo(uint16_t rx, uint16_t ry, uint16_t rz, int sx, int sy){
  tft.setTextDatum(TL_DATUM); tft.setTextColor(TFT_WHITE, TFT_BLACK); tft.setTextPadding(300);
  tft.drawString("tho  X=" + String(rx) + "  Y=" + String(ry) + "  Z=" + String(rz), 12, 100, 4);
  tft.drawString("man  x=" + String(sx) + "  y=" + String(sy), 12, 140, 4);
  tft.setTextPadding(0);
  tft.fillCircle(sx, sy, 4, TFT_RED);
}
/* Màn ĐẾM NGƯỢC khi đang giữ nút — để nhả tay kịp nếu bấm nhầm.
   Một nút thì phải có đường huỷ, không thì giữ lỡ tay là nạp sai máy. */
void veDemGiu(unsigned long giuMs){
  static int cuoi = -1;
  int con = (int)((NUT_NAP_MS - (giuMs > NUT_NAP_MS ? NUT_NAP_MS : giuMs) + 999) / 1000);
  bool quaNap = giuMs >= NUT_NAP_MS;
  int hienThi = quaNap ? -(int)((giuMs - NUT_NAP_MS) / 1000) : con;
  if (hienThi == cuoi) return;
  cuoi = hienThi;
  tft.fillRect(4, 96, 312, 60, TFT_BLACK);
  tft.setTextDatum(MC_DATUM);
  if (!quaNap){
    tft.setTextColor(TFT_ORANGE, TFT_BLACK);
    tft.drawString("GIU DE NAP... " + String(con), 160, 112, 4);
    tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
    tft.drawString("Nha tay de HUY", 160, 142, 2);
  } else {
    tft.setTextColor(colChay(), TFT_BLACK);
    tft.drawString("NHA TAY DE NAP", 160, 112, 4);
    tft.setTextColor(TFT_YELLOW, TFT_BLACK);
    tft.drawString("Giu tiep 5s = doi che do TU DONG", 160, 142, 2);
  }
}
/* Đọc nút BOOT, xử lý theo thời gian giữ. Trả true nếu cần vẽ lại màn. */
bool nutXuLy(){
  static bool dangGiu = false;
  static unsigned long batDau = 0;
  bool nhan = nutDangNhan();

  if (nhan && !dangGiu){ dangGiu = true; batDau = millis(); return false; }
  if (nhan && dangGiu){
    unsigned long giu = millis() - batDau;
    if (giu >= NUT_NGAN_MS && g_man == MAN_DS) veDemGiu(giu);   // đang giữ -> đếm ngược
    return false;
  }
  if (!nhan && !dangGiu) return false;

  // vừa NHẢ tay
  dangGiu = false;
  unsigned long giu = millis() - batDau;
  int loai = nutPhanLoai(giu);
  Serial.printf("[NUT] giu %lums -> %s\n", giu,
    loai==NUT_NGAN?"chon may ke tiep":loai==NUT_NAP?"NAP":loai==NUT_AUTO?"doi che do tu dong":"bo qua");

  if (loai == NUT_KHONG) return true;                 // vẽ lại để xoá phần đếm ngược
  if (loai == NUT_AUTO){
    g_tuDongNap = !g_tuDongNap;
    prefs.putString("autoNap", g_tuDongNap ? "1" : "0");
    ghiTinhTrang(g_tuDongNap ? "Bat TU DONG nap (nut BOOT)" : "Tat TU DONG nap (nut BOOT)");
    return true;
  }
  if (loai == NUT_NGAN){
    if (g_soMay == 0){ tpQuetVaoDs(); g_chon = (g_soMay > 0) ? 0 : -1; return true; }
    g_chon = (g_chon + 1) % g_soMay;                  // xoay vòng qua các máy
    return true;
  }
  // NUT_NAP
  if (g_soMay == 0 || g_chon < 0 || g_chon >= g_soMay){
    scrLoi("CHUA CHON MAY", "Nhan BOOT de chon", "Roi giu 2s de nap"); delay(1600); return true;
  }
  if (g_fwSize <= 0){ scrLoi("CHUA CO FILE", "Cam the co firmware.bin", ""); delay(1800); return true; }
  g_dangLamViec = true;
  updateOne(g_dsMay[g_chon].ssid, g_dsMay[g_chon].bssid, g_dsMay[g_chon].ch);
  g_dangLamViec = false;
  tpQuetVaoDs();
  if (g_chon >= g_soMay) g_chon = g_soMay - 1;
  return true;
}

/* Bắt chạm cho màn đang hiện. Trả true nếu cần vẽ lại. */
bool tpXuLy(){
  int x = 0, y = 0;
  if (g_man == MAN_CALIB){
    uint16_t rx, ry, rz;
    if (!tpDocTho(&rx, &ry, &rz)) return false;
    tpDoiToaDo(rx, ry, &x, &y, g_tpXMin, g_tpXMax, g_tpYMin, g_tpYMax, g_tpSwap, g_tpInvX, g_tpInvY);
    veCalibSo(rx, ry, rz, x, y);
    if (tpTrong(x, y, O_CAL_X, O_CAL_Y, O_CAL_W, O_CAL_H)){ g_man = MAN_DS; delay(300); return true; }
    return false;
  }
  if (!tpCham(&x, &y)) return false;
  if (g_man == MAN_DS){
    if (tpTrong(x, y, O_QUET_X, O_QUET_Y, O_QUET_W, O_QUET_H)){ tpQuetVaoDs(); return true; }
    if (tpTrong(x, y, O_AUTO_X, O_AUTO_Y, O_AUTO_W, O_AUTO_H)){
      g_tuDongNap = !g_tuDongNap;
      prefs.putString("autoNap", g_tuDongNap ? "1" : "0");
      ghiTinhTrang(g_tuDongNap ? "Bat TU DONG nap (bam tren man)" : "Tat TU DONG nap (bam tren man)");
      return true;
    }
    if (tpTrong(x, y, O_CAL_X, O_CAL_Y, O_CAL_W, O_CAL_H)){ g_man = MAN_CALIB; return true; }
    for (int i = 0; i < g_soMay; i++){
      int oy = O_HANG_Y + i * O_HANG_CACH;
      if (tpTrong(x, y, O_HANG_X, oy, O_HANG_W, O_HANG_H)){ g_chon = i; g_man = MAN_XACNHAN; return true; }
    }
    return false;
  }
  if (g_man == MAN_XACNHAN){
    if (tpTrong(x, y, O_HUY_X, O_HUY_Y, O_HUY_W, O_HUY_H)){ g_man = MAN_DS; g_chon = -1; return true; }
    if (tpTrong(x, y, O_NAP_X, O_NAP_Y, O_NAP_W, O_NAP_H)){
      if (g_chon >= 0 && g_chon < g_soMay){
        if (g_fwSize <= 0){ scrLoi("CHUA CO FILE", "Cam the co firmware.bin", ""); delay(1800); }
        else {
          g_dangLamViec = true;
          updateOne(g_dsMay[g_chon].ssid, g_dsMay[g_chon].bssid, g_dsMay[g_chon].ch);
          g_dangLamViec = false;
          tpQuetVaoDs();                       // quét lại để cập nhật dấu "da nap"
        }
      }
      g_man = MAN_DS; g_chon = -1; return true;
    }
    return false;
  }
  return false;
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
/* Hiệu chỉnh cảm ứng qua portal — để sửa được mà KHÔNG phải nạp lại firmware.
   Không có đường này thì bấm lệch là bó tay, vì cảm ứng không kiểm được bằng máy ảo.
   GET  /tpcal        -> xem giá trị đang dùng (JSON)
   POST /tpcal?xmin=..&xmax=..&ymin=..&ymax=..&swap=0|1&invx=0|1&invy=0|1  (gửi ô nào đổi ô đó) */
void hTpCal(){
  if (server.method() == HTTP_POST){
    struct { const char* arg; const char* khoa; } m[] = {
      {"xmin","tpXMin"}, {"xmax","tpXMax"}, {"ymin","tpYMin"}, {"ymax","tpYMax"} };
    int n = 0;
    for (unsigned i = 0; i < sizeof(m)/sizeof(m[0]); i++){
      if (!server.hasArg(m[i].arg)) continue;
      long v = server.arg(m[i].arg).toInt();
      if (v < 0 || v > 4095){ server.send(400, "text/plain; charset=utf-8", "Moc phai trong 0..4095"); return; }
      prefs.putInt(m[i].khoa, (int)v); n++;
    }
    // chân: 0..39 trên ESP32; 34-39 chỉ-vào-được nên chỉ hợp cho DO/IRQ
    struct { const char* arg; const char* khoa; } ch[] = {
      {"clk","tpClk"}, {"din","tpDin"}, {"do","tpDo"}, {"cs","tpCs"}, {"irq","tpIrq"} };
    for (unsigned i = 0; i < sizeof(ch)/sizeof(ch[0]); i++){
      if (!server.hasArg(ch[i].arg)) continue;
      long v = server.arg(ch[i].arg).toInt();
      if (v < 0 || v > 39){ server.send(400, "text/plain; charset=utf-8", "Chan phai trong 0..39"); return; }
      prefs.putInt(ch[i].khoa, (int)v); n++;
    }
    const char* co[5]   = {"swap","invx","invy","irqon","debug"};
    const char* coKhoa[5]= {"tpSwap","tpInvX","tpInvY","tpIrqOn","tpDebug"};
    for (int i = 0; i < 5; i++)
      if (server.hasArg(co[i])){ prefs.putString(coKhoa[i], server.arg(co[i]) == "1" ? "1" : "0"); n++; }
    tpNapCauHinh();
    veManDs();
    server.send(200, "text/plain; charset=utf-8", "Da luu " + String(n) + " gia tri. Cham thu lai tren man.");
    return;
  }
  String j = String("{\"clk\":") + String(g_tpClk) + ",\"din\":" + String(g_tpDin)
           + ",\"do\":" + String(g_tpDo) + ",\"cs\":" + String(g_tpCs)
           + ",\"irq\":" + String(g_tpIrq) + ",\"irqon\":" + String(g_tpDungIrq ? 1 : 0)
           + ",\"debug\":" + String(g_tpDebug ? 1 : 0) + ","
           + "\"xmin\":" + String(g_tpXMin) + ",\"xmax\":" + String(g_tpXMax)
           + ",\"ymin\":" + String(g_tpYMin) + ",\"ymax\":" + String(g_tpYMax)
           + ",\"swap\":" + String(g_tpSwap ? 1 : 0) + ",\"invx\":" + String(g_tpInvX ? 1 : 0)
           + ",\"invy\":" + String(g_tpInvY ? 1 : 0)
           + ",\"docDuoc\":" + String(g_tpCo ? 1 : 0) + "}";
  server.send(200, "application/json", j);
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
  server.on("/tpcal",   HTTP_GET,  hTpCal);
  server.on("/tpcal",   HTTP_POST, hTpCal);
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
  tpKhoiDong();                 // cảm ứng: SPI mềm, bus riêng, không tranh với màn/SD
  nutKhoiDong();                // nút BOOT (GPIO0) = đường điều khiển CHÍNH
  Serial.println("[NUT] BOOT: nhan nha = chon may | giu 2s = NAP | giu 5s = doi che do tu dong");
  Serial.println("[NUT] ⚠️ DUNG giu nut luc cap dien/reset — bo se vao che do nap USB.");
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

/* Gõ trên Serial Monitor (đường DUY NHẤT chắc chắn tới được máy khi cảm ứng chết và
   portal cũng không vào được — hoàn cảnh thật của anh Thắng):
     d = bật/tắt in số thô cảm ứng      i = bật/tắt dùng chân IRQ
     p = in cấu hình chân đang dùng     ? = nhắc lại các lệnh  */
void tpLenhSerial(){
  if (!Serial.available()) return;
  char c = (char)Serial.read();
  if (c == 'd'){ g_tpDebug = !g_tpDebug; prefs.putString("tpDebug", g_tpDebug ? "1" : "0");
                 Serial.printf("[TP] debug = %d\n", (int)g_tpDebug); }
  else if (c == 'i'){ g_tpDungIrq = !g_tpDungIrq; prefs.putString("tpIrqOn", g_tpDungIrq ? "1" : "0");
                 Serial.printf("[TP] dungIrq = %d (0 = bo qua IRQ, chi xet luc nhan Z)\n", (int)g_tpDungIrq); }
  else if (c == 'p'){ Serial.printf("[TP] CLK=%d DIN=%d DO=%d CS=%d IRQ=%d dungIrq=%d debug=%d\n",
                 g_tpClk, g_tpDin, g_tpDo, g_tpCs, g_tpIrq, (int)g_tpDungIrq, (int)g_tpDebug); }
  else if (c == '?'){ Serial.println("[TP] d=debug  i=dung IRQ  p=in chan  ?=tro giup"); }
}
void loop(){
  tpLenhSerial();
  server.handleClient();
  dnsServer.processNextRequest();

  /* CẢM ỨNG: đặt TRƯỚC phần tự động, và bỏ qua khi đang nạp.
     Anh Thắng không nối được điện thoại vào AP nên portal không dùng được ngoài hiện trường
     -> bấm trực tiếp trên màn là đường CHÍNH, portal thành đường lùi. */
  tpChanDoan();                 // in số thô ra Serial khi bật debug — đường chẩn đoán DUY NHẤT
                                //   khi cảm ứng không phản hồi và portal cũng không vào được
  if (!g_dangLamViec){
    static bool daVeLanDau = false;
    if (!daVeLanDau){ tpQuetVaoDs(); if (g_soMay > 0) g_chon = 0; veManDs(); daVeLanDau = true; }
    // NÚT BOOT là đường chính; cảm ứng để đó, chạy được thì tốt, không thì vẫn dùng nút.
    if (nutXuLy()){
      if      (g_man == MAN_DS)      veManDs();
      else if (g_man == MAN_XACNHAN) veManXacNhan();
      else if (g_man == MAN_CALIB)   veManCalib();
    }
    if (tpXuLy()){
      if      (g_man == MAN_DS)      veManDs();
      else if (g_man == MAN_XACNHAN) veManXacNhan();
      else if (g_man == MAN_CALIB)   veManCalib();
    }
  }

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
