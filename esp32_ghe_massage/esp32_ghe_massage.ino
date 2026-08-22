/* ============================================================================
 *  GHẾ MASSAGE — Thanh toán VietQR + tiền mặt
 *  Board: ESP32-2432S028 (CYD) + A7680C (4G) + relay
 *
 *  Luồng: khách chọn gói -> CYD VẼ mã VietQR lên màn -> khách quét app bank CK
 *         -> ngân hàng bắn webhook về website -> ghế hỏi website thấy đã trả
 *         -> đóng relay chạy ghế + đếm ngược -> hết giờ tự ngắt.
 *         Hoặc: khách nhét tiền mặt -> máy đếm tiền xác thực -> ghế chạy ngay.
 *
 * =============================================================================================
 *  🔴 ĐỔI LỚN 22/08/2026 — BỎ FIREBASE, NÓI THẲNG VỚI WEBSITE
 * =============================================================================================
 *  Anh Thắng chốt cho cả hệ thống chạy trên host. Trước bản này ghế nói chuyện với Firebase:
 *      /ghe/pay/<ghế>/<mã>   xem khách đã trả chưa
 *      /ghe/status/<ghế>     báo còn sống
 *      /ghe/cmd/<ghế>        nhận lệnh bật/tắt từ web
 *      /ghe/config/<ghế>     lấy giá / số phút / số tài khoản
 *      /ghe/revenue/…        ghi doanh thu tiền mặt
 *  Nay tất cả là MỘT địa chỉ: POST `<website>/ghe-may`, phân biệt bằng trường `viec`.
 *
 *  Ba cái được:
 *    · KHÔNG CÒN DATABASE SECRET TRONG FIRMWARE. Khoá cũ có quyền admin trên cả project
 *      Firebase — ai cầm được là đọc/ghi/xoá toàn bộ, kể cả nhánh của hệ thống khác dùng chung.
 *      Nó đã nằm nguyên văn trong mã nguồn, nên phải coi là ĐÃ LỘ và vô hiệu ở Console.
 *    · MỘT BẢN .BIN CHO MỌI GHẾ. Mã ghế không nạp cứng nữa: ghế khai MAC, máy chủ nói nó là ghế
 *      số mấy. Không có chuyện đó thì mỗi ghế một bản .bin và cập nhật từ xa mất hết ý nghĩa.
 *    · Mất mạng Google thì ghế vẫn chạy.
 *
 *  Cái mất, nói thẳng: website thành điểm chết duy nhất cho đường QR. Nhưng TIỀN MẶT vẫn chạy
 *  khi mất mạng — máy đếm tiền đã xác thực tờ tiền, ghế chạy ngay và ghi sổ sau. Đó là lý do
 *  đường tiền mặt KHÔNG được chờ máy chủ trả lời.
 *
 *  THƯ VIỆN CẦN CÀI: TFT_eSPI (Bodmer) · XPT2046_Touchscreen (P. Stoffregen) · ArduinoJson.
 *  QR dùng bộ mã CÓ SẴN trong ESP32 core ("qrcode.h"/esp_qrcode) — KHÔNG cần thư viện ngoài.
 * ========================================================================== */
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <TFT_eSPI.h>
#include <XPT2046_Touchscreen.h>
#include <SPI.h>
#include <Preferences.h>
#include "qrcode.h"
#include <time.h>
#include <sys/time.h>
#include <esp_mac.h>

#define FW_VERSION "ghe-massage 2026-08-22c (goi co ten, lay tu web)"

#if !__has_include("secrets.h")
  #error "Thieu secrets.h — copy secrets.example.h thanh secrets.h roi dien gia tri that."
#endif
#include "secrets.h"

/* 🔴 `Btn` PHẢI KHAI Ở ĐÂY — TRƯỚC HÀM ĐẦU TIÊN CỦA TỆP. ĐỪNG DỜI XUỐNG DƯỚI.
   Arduino tự sinh prototype cho MỌI hàm tự do rồi chèn hết vào ngay trước hàm ĐẦU TIÊN nó thấy.
   Struct khai sau điểm đó thì prototype `bool inBtn(Btn, int, int)` nằm TRƯỚC định nghĩa struct
   -> build đỏ với câu "'Btn' was not declared in this scope", chỉ vào dòng chẳng liên quan gì.
   Bản gốc đã ghi đúng cảnh báo này; em vẫn vướng lại vì chèn `_cxTim()` lên trên nó — tức là
   ĐẨY hàm đầu tiên lên sớm hơn cả struct. Nay struct nằm trên cùng thì không đẩy gì lên trên
   được nữa. (Cách khác: cho `inBtn` thành hàm thành viên của struct — hàm thành viên không bị
   sinh prototype. Xem `AnhGiaiMa` bên máy chấm công.) */
struct Btn { int x, y, w, h; };

// ============================ CẤU HÌNH ============================
/* ⚠️ KHÔNG CÒN `CHAIR_ID` NẠP CỨNG. Ghế khai MAC, máy chủ trả về mã ghế trong lượt nhịp đầu
   tiên. Ghế chưa được gán thì máy chủ trả cờ `chuaGan` và ghế hiện chữ lên màn — người đi lắp
   biết ngay còn thiếu một bước, thay vì đứng nhìn màn trống rồi đoán. */
String CHAIR_ID = "";        // máy chủ gán; rỗng = chưa hỏi được
bool   CHUA_GAN = false;     // máy chủ báo ghế này chưa ai gán mã

// --- Thông tin nhận tiền: LẤY TỪ MÁY CHỦ, không nạp cứng ---
/* Đổi số tài khoản trên web là mọi ghế theo trong ~1 phút, KHÔNG phải nạp lại firmware. */
String BANK_BIN     = "";
String ACCOUNT_NO   = "";
String ACCOUNT_NAME = "";

// --- Giá mặc định (máy chủ đè lên trong lượt nhịp) ---
long PRICE_VND  = 10000;
int  MINUTES    = 6;
/* MỆNH GIÁ — máy chủ đè lên trong lượt nhịp. Bốn con số dưới đây chỉ là bản dự phòng dùng khi
   ghế chưa hỏi được máy chủ lần nào (vừa cắm điện, mạng chưa lên).

   🔴 KHÔNG khai cứng nữa. Ghế này KHÔNG CÓ OTA — khai cứng nghĩa là đổi mệnh giá phải mang USB
      đi 26 cửa hàng. Khai ở web thì ghế lấy về trong ~30 giây.

   ⚠️ `PKG_N` là số nút ĐANG dùng, thay đổi được; `PKG_MAX` là số ô vẽ được trên màn, cố định 4.
      Trộn hai cái này là vẽ ra ngoài mảng. */
const int PKG_MAX = 4;
int    PKG_N = 4;
long   PKG_AMT[PKG_MAX]  = { 50000, 100000, 150000, 200000 };
String PKG_TEN[PKG_MAX]  = { "GOI CO BAN", "GOI PHO BIEN", "GOI CHUYEN SAU", "GOI THUONG HANG" };
int    PKG_PHUT[PKG_MAX] = { 0, 0, 0, 0 };   // 0 = tính theo tỉ lệ quy đổi

/* Số phút của một gói: khai cứng nếu máy chủ có gửi, không thì tính theo tỉ lệ quy đổi.
   MỘT chỗ tính duy nhất — trước đây phép này chép ở bốn nơi (vẽ nút, mở phiên, nhận tiền mặt,
   nhận tiền trễ) và chỉ cần một nơi quên là ghế chạy sai số phút so với cái nó vừa hiện ra. */
int phutGoi(int i){
  if(i < 0 || i >= PKG_N) return 0;
  if(PKG_PHUT[i] > 0)     return PKG_PHUT[i];
  if(PRICE_VND <= 0)      return 0;
  return (int)(PKG_AMT[i] * (long)MINUTES / PRICE_VND);
}

const int PAY_WINDOW_S   = 150;    // chờ khách trả (giây) rồi hủy QR
const unsigned long PAY_POLL_MS  = 2000;   // chu kỳ hỏi máy chủ khi đang chờ trả
const unsigned long PAY_GRACE_MS = 20000;  // sau khi HỦY vẫn theo dõi ~20s: tiền tới trễ vẫn chạy
const unsigned long NHIP_MS      = 30000;  // nhịp sống + lấy cấu hình

// --- Relay điều khiển ghế ---
#define RELAY_PIN          17
#define RELAY_ACTIVE_HIGH  true

// --- Nhận TIỀN MẶT: chọn MỘT trong hai đường ---
/* Hai đường KHÔNG chạy cùng lúc được — chúng dùng chung GPIO27. Xem khối MDB ở dưới và
   static_assert chặn ngay lúc biên dịch.
     PULSE (12V) : máy gạt DIP sang chế độ xung, mỗi tờ ra N xung. Đang dùng.
     MDB         : bus MDB 9-bit. Máy phải ở chế độ MDB và hết lỗi cảm biến. */
#define USE_MDB            false
#define CASH_ENABLE        true
#define CASH_PULSE_PIN     27
#define CASH_VND_PER_PULSE 5000    // 1 xung = ? đồng — theo DIP xung của máy đếm tiền
#define CASH_DEBOUNCE_MS   50      // chống nảy: bỏ cạnh cách nhau < ngưỡng này
const unsigned long CASH_BATCH_GAP_MS = 400;   // im lặng >400ms = một tờ/đợt đã nạp xong
#define CASH_MAX_VND       200000  // trần một lượt; tới ngưỡng -> KHOÁ máy nhận tiền
#define CASH_INHIBIT_ENABLE true
#define INHIBIT_PIN        22
#define INHIBIT_ACTIVE_HIGH true

// --- Mạng ---
const bool  USE_4G   = true;
const char* WIFI_SSID = SEC_WIFI_SSID;
const char* WIFI_PASS = SEC_WIFI_PASS;

// --- A7680C (4G) ---
#define SIM_TX_PIN   4
#define SIM_RX_PIN   16
#define SIM_PWRKEY   17
#define USE_PWRKEY   false
const char* SIM_APN = "v-internet";

/* ⚠️ Chặn LÚC BIÊN DỊCH cái lỗi dán nhầm link cũ vào ô website. */
constexpr bool _cxTim(const char* h, const char* n){
  for (int i = 0; h[i]; i++){ int j = 0; while (n[j] && h[i+j] == n[j]) j++; if (!n[j]) return true; }
  return false;
}
static_assert(!_cxTim(SEC_WP_URL, "/macros/"),
  "SEC_WP_URL chua /macros/ — day la link Apps Script cu, khong phai link website.");
static_assert(!_cxTim(SEC_WP_URL, "firebasedatabase"),
  "SEC_WP_URL la link Firebase cu. He thong da bo Firebase; dung dang https://<ten mien>/ghe-may.");
static_assert(_cxTim(SEC_WP_URL, "__CHUA_CAU_HINH") || _cxTim(SEC_WP_URL, "https://"),
  "SEC_WP_URL phai bat dau bang https:// . Cong nhan tu choi HTTP thuong.");
const char* wp_url = SEC_WP_URL;
const char* wp_key = SEC_WP_KEY;

// --- Đèn nền + Touch (CYD chuẩn) ---
#define BL_PIN 21
#define T_CS   33
#define T_IRQ  36
#define T_CLK  25
#define T_DIN  32
#define T_DO   39
int TS_MINX = 200, TS_MAXX = 3700, TS_MINY = 240, TS_MAXY = 3800;

#define QR_PX      3

// ================================================================
TFT_eSPI tft = TFT_eSPI();
SPIClass tsSPI = SPIClass(HSPI);
XPT2046_Touchscreen ts(T_CS, T_IRQ);
Preferences prefs;

volatile bool g_4gReady = false;
int  g_simTx = SIM_TX_PIN, g_simRx = SIM_RX_PIN; long g_simBaud = 115200;

enum State { ST_IDLE, ST_WAIT_PAY, ST_RUNNING };
State state = ST_IDLE;
bool  screenDrawn = false;

char    payCode[8] = "";
long    payAmount = 0;
int     payMinutes = 0;
long    g_runTotalVnd = 0;
String  qrPayload = "";
unsigned long waitUntil = 0;
unsigned long lastPayPoll = 0;
unsigned long runUntil = 0;
volatile char g_srcCode = 0;          // 0=none 'q'=QR 'c'=tiền mặt 'r'=lệnh từ web
volatile bool g_statusDirty = true;
unsigned long lastNhipMs = 0;
int     lastShownSec = -1;
unsigned long last4gTry = 0;
volatile int  g_netFails = 0;
unsigned long lastRegCheck = 0;

volatile bool g_payWaiting = false;
volatile unsigned long g_watchPayUntil = 0;
volatile long g_paidAmount = 0;
portMUX_TYPE  g_mux = portMUX_INITIALIZER_UNLOCKED;
volatile int  g_remoteStartMin = 0;
volatile bool g_remoteStop = false;
volatile bool g_coLenh = false;       // máy chủ báo có lệnh đang chờ


volatile uint32_t g_cashPulses = 0;
volatile unsigned long g_lastPulseMs = 0;
void IRAM_ATTR onCashPulse(){
  static unsigned long lastEdgeMs = 0;
  unsigned long now = millis();
  if(now - lastEdgeMs >= CASH_DEBOUNCE_MS){ g_cashPulses = g_cashPulses + 1; lastEdgeMs = now; }
}
/* Tiền mặt CHỜ ghi sổ. Nhân UI cộng vào, netTask đẩy lên máy chủ.
   ⚠️ `g_cashRef` là mã ỔN ĐỊNH của đợt đang chờ: netTask có thể phải gửi lại vài lần khi mạng
      chập chờn, mà máy chủ ghi doanh thu theo mã đó nên gửi lại KHÔNG cộng đôi. Sinh ngẫu nhiên
      mỗi lần gửi là mỗi lần thử lại thành một dòng doanh thu mới. */
volatile long g_pendingCashLog = 0;
char g_cashRef[40] = "";

// ======================= 4G (tái dùng từ máy chấm công) =======================
void modemPowerOn(){
  if(USE_PWRKEY){
    pinMode(SIM_PWRKEY, OUTPUT);
    digitalWrite(SIM_PWRKEY, HIGH); delay(200);
    digitalWrite(SIM_PWRKEY, LOW);  delay(1200);
    digitalWrite(SIM_PWRKEY, HIGH);
  }
  Serial.println("[4G] Bật nguồn A7680C, chờ boot ~12s...");
  delay(12000);
}
bool atProbe(int txPin, int rxPin, long baud){
  Serial2.begin(baud, SERIAL_8N1, rxPin, txPin); delay(300);
  bool ok=false;
  for(int i=0;i<3 && !ok;i++){
    while(Serial2.available()) Serial2.read();
    Serial2.print("AT\r\n");
    unsigned long t0=millis(); String r="";
    while(millis()-t0<800){ while(Serial2.available()) r+=(char)Serial2.read(); if(r.indexOf("OK")>=0){ok=true;break;} delay(5); }
  }
  Serial2.end(); return ok;
}
String atSend(const char* cmd, unsigned long to){
  while(Serial2.available()) Serial2.read();
  Serial2.print(cmd); Serial2.print("\r\n");
  unsigned long t0=millis(); String r="";
  while(millis()-t0<to){ while(Serial2.available()) r+=(char)Serial2.read(); if(r.indexOf("OK")>=0||r.indexOf("ERROR")>=0) break; delay(5); }
  r.replace("\r"," "); r.replace("\n"," "); r.trim(); return r;
}
String atWait(const char* token, unsigned long to){
  unsigned long t0=millis(); String r="";
  while(millis()-t0<to){ while(Serial2.available()) r+=(char)Serial2.read(); if(r.indexOf(token)>=0) break; delay(3); }
  return r;
}
bool net4gDiag(){
  atSend("ATE0",800);
  Serial.println("[4G] CPIN? -> " + atSend("AT+CPIN?",2000));
  atSend("AT+CFUN=1",3000);
  atSend("AT+CTZU=1",1000);
  atSend("AT+COPS=0",12000);
  atSend((String("AT+CGDCONT=1,\"IP\",\"")+SIM_APN+"\"").c_str(),1500);
  bool reg = false;
  for(int i=0;i<30 && !reg;i++){
    String e = atSend("AT+CEREG?",1200);
    String g = atSend("AT+CGREG?",1200);
    if(e.indexOf(",1")>=0||e.indexOf(",5")>=0||g.indexOf(",1")>=0||g.indexOf(",5")>=0){ reg=true; break; }
    Serial.printf("[4G] cho dang ky (%ds) CEREG=%s CGREG=%s CSQ=%s\n", i*2, e.c_str(), g.c_str(), atSend("AT+CSQ",1000).c_str());
    delay(1500);
  }
  if(!reg) return false;
  atSend("AT+CGACT=1,1",10000);
  atSend("AT+CSSLCFG=\"sslversion\",0,4", 1500);
  Serial.println("[4G] SSL authmode: " + atSend("AT+CSSLCFG=\"authmode\",0,0", 1500));
  Serial.println("[4G] SSL SNI: " + atSend("AT+CSSLCFG=\"enableSNI\",0,1", 1500));
  /* HTTPS cần giờ đúng để bắt tay TLS — giờ sai là mọi lượt gọi lỗi mà không nói vì sao. */
  atSend("AT+CNTPCID=1", 1000);
  atSend("AT+CNTP=\"pool.ntp.org\",28", 2000);
  Serial.println("[4G] NTP: " + atSend("AT+CNTP", 9000));
  Serial.println("[4G-DIAG] IP  = " + atSend("AT+CGPADDR=1", 3000));
  Serial.println("[4G-DIAG] CSQ = " + atSend("AT+CSQ", 1000));
  Serial.println("[4G-DIAG] giờ= " + atSend("AT+CCLK?", 1000) + "  (1970/1980 = giờ sai -> SSL chan)");
  return true;
}
bool net4gConnect(){
  g_4gReady=false; modemPowerOn();
  long bauds[]={115200,9600}; bool found=false;
  for(int bi=0;bi<2 && !found;bi++){
    if(atProbe(SIM_TX_PIN,SIM_RX_PIN,bauds[bi])){ g_simTx=SIM_TX_PIN; g_simRx=SIM_RX_PIN; g_simBaud=bauds[bi]; found=true; }
    else if(atProbe(SIM_RX_PIN,SIM_TX_PIN,bauds[bi])){ g_simTx=SIM_RX_PIN; g_simRx=SIM_TX_PIN; g_simBaud=bauds[bi]; found=true; }
  }
  if(!found){ Serial.println("[4G] Module KHÔNG trả AT -> kiểm nguồn/PWRKEY/dây/SIM"); return false; }
  Serial.printf("[4G] AT OK: tx=%d rx=%d @%ld\n", g_simTx, g_simRx, g_simBaud);
  Serial2.begin(g_simBaud, SERIAL_8N1, g_simRx, g_simTx); delay(300);
  if(net4gDiag()){ g_4gReady=true; Serial.println("[4G] SẴN SÀNG (LTE) — AT-HTTP"); return true; }
  Serial.println("[4G] Chưa đăng ký mạng — thử lại sau"); return false;
}
void net4gMarkOk(){ g_netFails = 0; }
void net4gMarkFail(){
  g_netFails = g_netFails + 1;      // ++ trên biến volatile: C++20 bỏ, viết rõ ra cho khỏi cảnh báo
  if(g_netFails >= 3){
    Serial.println("[4G] 3 lan HTTP that bai -> danh dau ROT MANG, se ket noi lai");
    g_4gReady = false; g_netFails = 0;
  }
}
int net4gReadStart(int want){
  Serial2.print("AT+HTTPREAD=0,"); Serial2.print(want); Serial2.print("\r\n");
  String hdr=""; unsigned long t0=millis(); bool sawTag=false, done=false;
  while(!done && millis()-t0<8000){
    while(Serial2.available()){
      char c=(char)Serial2.read(); t0=millis(); hdr+=c;
      if(!sawTag){ if(hdr.endsWith("+HTTPREAD:")){ sawTag=true; hdr=""; } }
      else if(c=='\n'){ done=true; break; }
    }
    if(!done) delay(2);
  }
  if(!sawTag) return -1;
  int lc=hdr.lastIndexOf(','); int st=(lc>=0)?lc+1:0;
  return hdr.substring(st).toInt();
}
/* POST qua AT-HTTP và GIỮ phiên để đọc thân trả về.
   Ghế phải ĐỌC câu trả lời (đã trả tiền chưa, giá bao nhiêu, có lệnh không) nên không dùng được
   kiểu POST-rồi-đóng-phiên. KHÔNG tự đi theo 30x: cổng nhận không bao giờ được chuyển hướng, và
   đi theo bằng GET là mất trọn thân POST. */
int net4gPostOpen(const String& url, const String& body, int* datalen){
  if(datalen) *datalen=0;
  if(!g_4gReady) return 0;
  Serial2.print("AT+HTTPTERM\r\n"); delay(120); while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPPARA=\"SSLCFG\",0\r\n"); atWait("OK",1500);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); atWait("OK",3000);
  Serial2.print("AT+HTTPPARA=\"CONTENT\",\"application/json\"\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPDATA="); Serial2.print(body.length()); Serial2.print(",20000\r\n");
  if(atWait("DOWNLOAD",6000).indexOf("DOWNLOAD")<0){ Serial2.print("AT+HTTPTERM\r\n"); return 0; }
  Serial2.print(body); atWait("OK",20000);
  Serial2.print("AT+HTTPACTION=1\r\n");
  String r=atWait("+HTTPACTION:",40000);
  int status=0, dl=0, p=r.indexOf("+HTTPACTION:");
  if(p>=0){ int c1=r.indexOf(',',p), c2=(c1>=0)?r.indexOf(',',c1+1):-1;
            if(c1>=0&&c2>=0){ status=r.substring(c1+1,c2).toInt(); dl=r.substring(c2+1).toInt(); } }
  if(status!=200){ r.replace("\r"," "); r.replace("\n"," "); Serial.println("[HTTP-RAW] st=" + String(status) + " | " + r); }
  if(status==0) net4gMarkFail(); else net4gMarkOk();
  if(status!=200) return status;
  if(datalen) *datalen=dl;
  return 200;
}
bool netUp(){ return USE_4G ? g_4gReady : (WiFi.status()==WL_CONNECTED); }

// ======================= MỘT CỬA DUY NHẤT NÓI CHUYỆN VỚI WEBSITE =======================
/* MAC bo ESP32 — DANH TÍNH của ghế. Máy chủ tra bảng `may` để biết đây là ghế số mấy.
   Nhờ vậy MỘT bản .bin dùng cho MỌI ghế. */
String macBo(){
  static String cache = "";
  if(cache.length()) return cache;
  uint8_t m[6];
  if(esp_read_mac(m, ESP_MAC_WIFI_STA) == ESP_OK){
    char b[18]; snprintf(b,sizeof(b),"%02X:%02X:%02X:%02X:%02X:%02X",m[0],m[1],m[2],m[3],m[4],m[5]);
    cache = String(b);
  } else cache = WiFi.macAddress();
  return cache;
}
String jsonEsc(const String& s){ String o=s; o.replace("\\","\\\\"); o.replace("\"","\\\""); return o; }

/**
 * Gọi website. `them` là các trường JSON thêm (không có ngoặc bọc). Trả thân trả về, "" nếu hỏng.
 *
 * 🔴 KHOÁ ĐI TRONG THÂN JSON, không chỉ header. Đường 4G gửi bằng lệnh AT nên đặt header tuỳ ý
 *    là tuỳ đời module. Cổng nhận đọc được khoá ở cả hai chỗ.
 * 🔴 KHÔNG ĐI THEO CHUYỂN HƯỚNG. Gặp 30x là coi như thất bại và nói rõ link sai — chứ không gọi
 *    lại bằng GET rồi mất trọn thân POST mà vẫn tưởng xong.
 */
String wpGoi(const String& viec, const String& them){
  if(!netUp()) return "";
  String body = "{\"key\":\"" + String(wp_key) + "\",\"viec\":\"" + viec
              + "\",\"mac\":\"" + macBo() + "\"";
  if(CHAIR_ID.length()) body += ",\"ma_may\":\"" + jsonEsc(CHAIR_ID) + "\"";
  if(them.length()) body += "," + them;
  body += "}";

  if(USE_4G){
    int dl=0, st=net4gPostOpen(String(wp_url), body, &dl);
    if(st!=200){
      Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      if(st==401) Serial.println("[WP] 401 — sai khoa may (phai khop VHG_KHOA_MAY o wp-config.php).");
      else if(st==301||st==302||st==307||st==308) Serial.printf("[WP] %d CHUYEN HUONG — link sai (dau / o cuoi?).\n", st);
      return "";
    }
    int n=net4gReadStart(dl); String ra="";
    if(n>0){ ra.reserve(n+4); int got=0; unsigned long t0=millis();
      while(got<n && millis()-t0<12000){ while(Serial2.available()&&got<n){ ra+=(char)Serial2.read(); got++; t0=millis(); } delay(1);} }
    atWait("OK",2000); Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    return ra;
  }
  WiFiClientSecure c; c.setInsecure();
  HTTPClient h; h.begin(c, wp_url);
  h.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
  h.addHeader("Content-Type","application/json");
  h.addHeader("X-VHG-Key", wp_key);
  h.setTimeout(20000);
  int code=h.POST(body); String ra=(code==200)?h.getString():""; h.end();
  if(code==401) Serial.println("[WP] 401 — sai khoa may.");
  else if(code==301||code==302||code==307||code==308) Serial.printf("[WP] %d CHUYEN HUONG — link sai.\n", code);
  return ra;
}

// ======================= VietQR =======================
static String _tlv(const char* id, const String& val){
  char len[3]; snprintf(len,sizeof(len),"%02d",(int)val.length());
  return String(id)+len+val;
}
static String _crc16(const String& s){
  uint16_t crc=0xFFFF;
  for(size_t i=0;i<s.length();i++){ crc ^= ((uint8_t)s[i])<<8;
    for(int b=0;b<8;b++) crc = (crc&0x8000) ? ((crc<<1)^0x1021) : (crc<<1); }
  char out[5]; snprintf(out,sizeof(out),"%04X",crc); return String(out);
}
String buildVietQR(const String& bin, const String& acct, long amount, const String& addInfo){
  String s = _tlv("00","01") + _tlv("01", amount ? "12" : "11");
  String ben = _tlv("00",bin) + _tlv("01",acct);
  s += _tlv("38", _tlv("00","A000000727") + _tlv("01",ben) + _tlv("02","QRIBFTTA"));
  s += _tlv("53","704");
  if(amount) s += _tlv("54", String(amount));
  s += _tlv("58","VN");
  if(addInfo.length()) s += _tlv("62", _tlv("08", addInfo));
  s += "6304"; return s + _crc16(s);
}

// ======================= Màn hình =======================
#define COL_BG    TFT_BLACK
#define COL_ACC   0x05BF

Btn PKG_BTN[PKG_MAX] = { {14,56,142,74}, {164,56,142,74}, {14,136,142,74}, {164,136,142,74} };

void drawIdle(){
  tft.fillScreen(COL_BG);
  tft.setTextDatum(TC_DATUM);
  /* Ghế chưa được gán mã thì NÓI RA trên màn. Người đi lắp thấy ngay còn thiếu một bước, thay vì
     bấm chọn gói rồi đứng chờ một mã QR không bao giờ hiện. */
  if(CHUA_GAN || CHAIR_ID.length()==0){
    tft.setTextColor(TFT_YELLOW, COL_BG);
    tft.drawString("GHE CHUA DUOC GAN MA", 160, 60, 4);
    tft.setTextColor(TFT_WHITE, COL_BG);
    tft.drawString("Vao web: Ghe Massage > May & co so", 160, 100, 2);
    tft.drawString("Gan ma cho MAC nay:", 160, 128, 2);
    tft.setTextColor(COL_ACC, COL_BG);
    tft.drawString(macBo(), 160, 152, 2);
    tft.setTextColor(netUp()?TFT_GREEN:TFT_RED, COL_BG);
    tft.drawString(netUp()?"4G ON":"4G -- (chua hoi duoc may chu)", 160, 200, 2);
    return;
  }
  tft.setTextColor(COL_ACC, COL_BG); tft.drawString("GHE MASSAGE " + CHAIR_ID, 160, 8, 4);
  tft.setTextColor(TFT_WHITE, COL_BG); tft.drawString("Chon so tien:", 160, 38, 2);
  for(int i=0;i<PKG_N;i++){
    long amt = PKG_AMT[i];
    int mins = phutGoi(i);
    Btn b = PKG_BTN[i]; int cx = b.x + b.w/2, cy = b.y + b.h/2;
    tft.fillRoundRect(b.x, b.y, b.w, b.h, 8, 0x02B5);
    tft.drawRoundRect(b.x, b.y, b.w, b.h, 8, COL_ACC);
    tft.setTextDatum(MC_DATUM);
    /* Tên gói ở TRÊN, nhỏ; số tiền to ở giữa; số phút ở dưới. Số tiền là thứ khách quyết định
       nên nó to nhất — tên gói chỉ để phân biệt, và ô rộng 142px nên font 1 mới đủ chỗ. */
    if(PKG_TEN[i].length()){
      tft.setTextColor(0xCE40, 0x02B5);
      tft.drawString(PKG_TEN[i], cx, b.y + 11, 1);
      tft.setTextColor(COL_ACC, 0x02B5);   tft.drawString(String(amt/1000) + "k",  cx, cy + 2, 4);
      tft.setTextColor(TFT_WHITE, 0x02B5); tft.drawString(String(mins) + " phut", cx, b.y + b.h - 12, 2);
    } else {
      tft.setTextColor(COL_ACC, 0x02B5);   tft.drawString(String(amt/1000) + "k",  cx, cy - 14, 4);
      tft.setTextColor(TFT_WHITE, 0x02B5); tft.drawString(String(mins) + " phut", cx, cy + 16, 2);
    }
  }
  tft.setTextDatum(BC_DATUM);
  tft.setTextColor(0xCE40, COL_BG); tft.drawString("K&H  -  POSH massage", 160, 224, 2);
  tft.setTextColor(netUp()?TFT_GREEN:TFT_RED, COL_BG);
  tft.drawString(netUp()?"4G ON":"4G --", 160, 238, 1);
}

static void qrDrawCb(esp_qrcode_handle_t qr){
  int size = esp_qrcode_get_size(qr), px = QR_PX, x0 = 16, y0 = 40;
  for(int y=0;y<size;y++) for(int x=0;x<size;x++)
    if(esp_qrcode_get_module(qr, x, y)) tft.fillRect(x0+x*px, y0+y*px, px, px, TFT_BLACK);
}
void drawQRScreen(){
  tft.fillScreen(TFT_WHITE);
  esp_qrcode_config_t qcfg = ESP_QRCODE_CONFIG_DEFAULT();
  qcfg.display_func       = qrDrawCb;
  qcfg.max_qrcode_version = 11;
  qcfg.qrcode_ecc_level   = ESP_QRCODE_ECC_LOW;
  esp_qrcode_generate(&qcfg, qrPayload.c_str());
  int rx = 190;
  tft.setTextDatum(TL_DATUM);
  tft.setTextColor(TFT_BLACK, TFT_WHITE);
  tft.drawString("QUET DE TRA", rx, 20, 4);
  tft.drawString(String(payAmount/1000) + "k = " + String(payMinutes) + "'", rx, 60, 4);
  tft.drawString(ACCOUNT_NO, rx, 100, 2);
  tft.setTextColor(0x8410, TFT_WHITE);
  tft.drawString("Ma: GHE" + CHAIR_ID + " " + payCode, rx, 140, 1);
  tft.fillRoundRect(100, 205, 120, 30, 6, 0xF9A6);
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_WHITE, 0xF9A6);
  tft.drawString("CHAM DE HUY", 160, 220, 2);
}
void drawWaitCountdown(int secLeft){
  tft.setTextDatum(TL_DATUM); tft.setTextPadding(150);
  tft.setTextColor(TFT_RED, TFT_WHITE);
  tft.drawString("Cho tra: " + String(secLeft) + "s", 190, 172, 2);
  tft.setTextColor(netUp() ? 0x0320 : TFT_RED, TFT_WHITE);
  tft.drawString(netUp() ? "Dang kiem tra..." : "MAT MANG - cho lai", 190, 192, 1);
  tft.setTextPadding(0);
}
void drawRunning(int secLeft){
  if(!screenDrawn){ tft.fillScreen(0x0400); screenDrawn=true;
    tft.setTextDatum(TC_DATUM); tft.setTextColor(TFT_WHITE, 0x0400);
    tft.drawString("GHE DANG CHAY", 160, 30, 4); }
  int mm=secLeft/60, ss=secLeft%60;
  char b[8]; snprintf(b,sizeof(b),"%02d:%02d",mm,ss);
  tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_WHITE, 0x0400);
  tft.setTextPadding(tft.textWidth("88:88",6));
  tft.drawString(b, 160, 130, 6);
  tft.setTextPadding(0);
}

// ======================= Touch =======================
bool getTouch(int& sx, int& sy){
  if(!ts.touched()) return false;
  TS_Point p = ts.getPoint();
  sx = map(p.x, TS_MINX, TS_MAXX, 0, 320);
  sy = map(p.y, TS_MINY, TS_MAXY, 0, 240);
  sx = constrain(sx,0,319); sy = constrain(sy,0,239);
  return true;
}
bool inBtn(Btn b, int x, int y){ return x>=b.x && x<=b.x+b.w && y>=b.y && y<=b.y+b.h; }

// ======================= Relay + khoá máy nhận tiền =======================
void relaySet(bool on){ digitalWrite(RELAY_PIN, (on == RELAY_ACTIVE_HIGH) ? HIGH : LOW); }
void setAcceptorEnabled(bool en){
  if(!CASH_INHIBIT_ENABLE) return;
  digitalWrite(INHIBIT_PIN, ((en ? 0 : 1) == (INHIBIT_ACTIVE_HIGH ? 1 : 0)) ? HIGH : LOW);
}
void updateAcceptor(){
  bool allow = (g_runTotalVnd < CASH_MAX_VND);
  setAcceptorEnabled(allow);
  if(!allow) Serial.printf("[LIMIT] da nhan %ld d >= tran %d -> KHOA may nhan tien\n", g_runTotalVnd, CASH_MAX_VND);
}

// ======================= Nhịp sống: gộp bốn câu hỏi vào MỘT lượt =======================
/**
 * Một lượt nhịp trả lời luôn: mã ghế của mình, giá/phút/số tài khoản, có tiền chờ không, có lệnh
 * bật/tắt không. Trước đây là bốn lượt gọi Firebase riêng (config, status, pay, cmd). Trên 4G mỗi
 * lượt AT-HTTP mất 3-6 giây — gộp lại là khác biệt giữa "ghế phản ứng trong 2 giây" và "10 giây".
 */
void guiNhip(){
  const char* st = (state==ST_RUNNING) ? "running" : (state==ST_WAIT_PAY ? "wait_pay" : "idle");
  const char* src = (g_srcCode=='q') ? "qr" : (g_srcCode=='c' ? "cash" : (g_srcCode=='r' ? "remote" : ""));
  long conLai = (state==ST_RUNNING) ? ((long)(runUntil - millis())/1000) : 0; if(conLai<0) conLai=0;
  String r = wpGoi("nhip", String("\"trang_thai\":\"") + st + "\",\"nguon\":\"" + src
    + "\",\"con_lai\":" + String(conLai) + ",\"fw\":\"" FW_VERSION "\"");
  lastNhipMs = millis(); g_statusDirty = false;
  if(r.length()==0) return;
  /* 1536 chứ không 512: gói nhịp mang thêm bốn gói {t,n,p} — mỗi gói một cái tên tới 18 ký tự.
     Tràn bộ đệm thì `deserializeJson` trả lỗi và HÀM THOÁT NGAY: ghế mất luôn cả giá, tài khoản
     lẫn lệnh, mà màn hình không có gì báo. Một con số chật ở đây làm chết cả lượt nhịp.
     netTask có 10240 byte ngăn xếp nên 1536 nằm gọn. */
  StaticJsonDocument<1536> d;
  if(deserializeJson(d, r)) return;
  String ma = String((const char*)(d["maMay"] | ""));
  if(ma.length()){ CHAIR_ID = ma; }
  CHUA_GAN = ((int)(d["chuaGan"] | 0) == 1);
  long gia = (long)(d["gia"] | 0);  int phut = (int)(d["phut"] | 0);
  /* Giá 0 nghĩa là máy chủ CHƯA khai máy này — giữ giá đang có chứ đừng nhận 0, không thì mọi
     gói tính ra 0 phút và ghế nhận tiền xong không chạy. */
  if(gia > 0)  PRICE_VND = gia;
  if(phut > 0) MINUTES   = phut;
  String tk = String((const char*)(d["soTk"] | "")); if(tk.length()) ACCOUNT_NO = tk;
  String bin= String((const char*)(d["bin"]  | "")); if(bin.length()) BANK_BIN = bin;
  String tn = String((const char*)(d["tenTk"]| "")); if(tn.length()) ACCOUNT_NAME = tn;
  /* Mệnh giá do web khai. Nhận vào CHỈ KHI đọc được ít nhất một giá trị hợp lệ — mảng rỗng hay
     gói lỗi mà nhận là màn ghế không còn nút nào bấm được, tức đường QR chết hẳn ở cửa hàng đó
     mà máy chủ vẫn thấy ghế gửi nhịp bình thường. Giữ bộ đang dùng còn hơn. */
  JsonArrayConst goi = d["goi"];
  if(!goi.isNull()){
    long   tt[PKG_MAX]; String tn[PKG_MAX]; int tp[PKG_MAX]; int n = 0;
    for(JsonVariantConst v : goi){
      if(n >= PKG_MAX) break;
      /* Nhận CẢ HAI dạng: số trơn (bản máy chủ 1.3.0) và {t,n,p} (từ 1.4.0). Ghế nạp bằng USB
         nên trong nhà sẽ có lẫn hai đời firmware và hai đời plugin trong nhiều tuần — bên nào
         cũng phải chịu được bên kia, không thì một cửa hàng nào đó im lặng mất hết nút bấm. */
      long a; String nm = ""; int ph = 0;
      if(v.is<JsonObjectConst>()){
        a  = (long)(v["t"] | 0);
        nm = String((const char*)(v["n"] | ""));
        ph = (int)(v["p"] | 0);
      } else {
        a = v.as<long>();
      }
      if(a >= 1000){ tt[n] = a; tn[n] = nm; tp[n] = ph; n++; }
    }
    if(n > 0){
      for(int i=0;i<n;i++){ PKG_AMT[i] = tt[i]; PKG_TEN[i] = tn[i]; PKG_PHUT[i] = tp[i]; }
      PKG_N = n; g_statusDirty = true;
    }
  }
  g_coLenh = ((int)(d["coLenh"] | 0) == 1);
  if(((int)(d["coTien"] | 0) == 1) && g_paidAmount == 0){
    /* Máy chủ báo có tiền chờ mà ghế đang rảnh (khách trả sau khi màn đã tắt QR) — vẫn lấy về
       và chạy. Khách đã trả tiền thì phải được massage, đừng bắt họ trả lần nữa. */
    g_watchPayUntil = millis() + PAY_GRACE_MS;
  }
}

/** Hỏi máy chủ có lượt nào đã trả tiền chưa. Trả số tiền (0 = chưa). */
long checkPaid(){
  String r = wpGoi("luot", "");
  if(r.length()==0) return 0;
  StaticJsonDocument<384> d;
  if(deserializeJson(d, r)) return 0;
  if((int)(d["co"] | 0) != 1) return 0;
  return (long)(d["so_tien"] | 0);
}

/** Lấy một lệnh bật/tắt do người bấm trên web. */
void checkRemoteCmd(){
  String r = wpGoi("lenh", "");
  if(r.length()==0) return;
  StaticJsonDocument<256> d;
  if(deserializeJson(d, r)) return;
  if((int)(d["co"] | 0) != 1) return;
  String viec = String((const char*)(d["viec"] | ""));
  int phut = (int)(d["phut"] | 0);
  if(viec == "on"){ g_remoteStartMin = (phut>0 ? phut : MINUTES); Serial.printf("[CMD] web MO may %d phut\n", g_remoteStartMin); }
  else if(viec == "off"){ g_remoteStop = true; Serial.println("[CMD] web TAT may"); }
}

/** Báo sổ tiền mặt. Gửi lại được: máy chủ ghi theo `ref`, xem ghi chú ở g_cashRef. */
void baoTienMat(long amount, const char* ref){
  wpGoi("tien_mat", String("\"so_tien\":") + String(amount) + ",\"ref\":\"" + String(ref) + "\"");
}

// ======================= Phiên thanh toán =======================
void genCode(char* out){
  const char* AB = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  uint32_t r = esp_random();
  for(int i=0;i<6;i++){ out[i] = AB[r % 31]; r /= 31; if(r==0) r=esp_random(); }
  out[6] = 0;
}
void startSession(int idx){
  payAmount  = PKG_AMT[idx];
  payMinutes = phutGoi(idx);
  genCode(payCode);
  String addInfo = "GHE" + CHAIR_ID + " " + payCode;
  qrPayload  = buildVietQR(BANK_BIN, ACCOUNT_NO, payAmount, addInfo);
  waitUntil  = millis() + (unsigned long)PAY_WINDOW_S*1000UL;
  lastPayPoll = 0; lastShownSec = -1;
  Serial.println("[PAY] Phiên " + addInfo + " = " + String(payAmount) + "d, " + String(payMinutes) + "'");
  g_paidAmount = 0;
  state = ST_WAIT_PAY; screenDrawn=false; g_statusDirty=true;
  g_payWaiting = true;
  g_watchPayUntil = waitUntil + PAY_GRACE_MS;
}
void startRunning(int minutes){
  g_payWaiting = false; g_watchPayUntil = 0;
  runUntil = millis() + (unsigned long)minutes*60000UL;
  relaySet(true);
  Serial.printf("[RUN] Ghế chạy %d phút\n", minutes);
  state = ST_RUNNING; screenDrawn=false; lastShownSec=-1; g_statusDirty=true;
}

/**
 * Một đợt tiền mặt nạp xong -> chạy/cộng giờ ghế.
 *
 * 🔴 GHẾ CHẠY NGAY, KHÔNG CHỜ MÁY CHỦ. Máy đếm tiền đã xác thực tờ tiền rồi — bắt khách chờ
 *    website trả lời là mất mạng thì khách mất tiền. Ghi sổ đi sau, và có thể phải gửi lại.
 */
void checkCash(){
  if(!CASH_ENABLE || g_cashPulses==0) return;
  if(millis()-g_lastPulseMs < CASH_BATCH_GAP_MS) return;
  noInterrupts(); uint32_t p=g_cashPulses; g_cashPulses=0; interrupts();
  long amount = (long)p * CASH_VND_PER_PULSE;
  int minutes = (PRICE_VND>0) ? (int)((amount * (long)MINUTES) / PRICE_VND) : 0;
  Serial.printf("[CASH] %u xung = %ld d -> %d phut\n", (unsigned)p, amount, minutes);
  if(minutes<=0) return;
  if(state==ST_RUNNING){ runUntil += (unsigned long)minutes*60000UL; g_statusDirty=true; Serial.println("[CASH] cong them gio (dang chay)"); }
  else { g_srcCode='c'; startRunning(minutes); }
  portENTER_CRITICAL(&g_mux);
  g_pendingCashLog += amount;
  if(g_cashRef[0] == 0){
    snprintf(g_cashRef, sizeof(g_cashRef), "cash-%s-%lu",
      CHAIR_ID.length()?CHAIR_ID.c_str():"?", (unsigned long)millis());
  }
  portEXIT_CRITICAL(&g_mux);
  g_runTotalVnd += amount; updateAcceptor();
}


/* ===========================================================================================
 *  MDB — nhận tiền mặt qua bus MDB (soft-UART 9 bit, bit-bang)
 * -------------------------------------------------------------------------------------------
 *  Bo QR_TOUCH_EXT (congnghechi.vn), chân đã xác nhận theo guide bo:
 *      bo "MDB TX" -> GPIO35 = ESP NHẬN (RX). GPIO35 chỉ-input nên đúng là RX.
 *      bo "MDB RX" -> GPIO27 = ESP GỬI (TX).
 *      GND P11 nối chung GND ESP.
 *  MDB: 9600 baud, 9 bit (bit thứ 9 = MODE: 1=địa chỉ/lệnh, 0=data), 1 stop.
 *  Firmware TỰ DÒ cực tính RX (đo mức đường lúc rảnh) và TỰ ĐẢO thử TX khi máy im — khỏi chỉnh
 *  tay, vì đúng/sai cực tính nhìn từ ngoài giống hệt nhau: cả hai đều là "máy không trả lời".
 *
 *  ⚠️ ĐIỀU KIỆN để chạy: máy phải (1) ở chế độ MDB (DIP SW2+SW3 ON), (2) HẾT lỗi cảm biến
 *     (đèn XANH, không nháy đỏ), (3) có nguồn bus MDB. Thiếu một trong ba thì máy im, và im vì
 *     lý do nào cũng giống nhau — nên log in cả cực tính đang thử để còn lần ra.
 *
 *  🔴 MDB VÀ ĐẾM XUNG KHÔNG CHẠY CÙNG LÚC ĐƯỢC: cả hai đều dùng GPIO27. Bật cả hai là chân đó
 *     vừa là OUTPUT (MDB gửi) vừa gắn ngắt FALLING (đếm xung) — ghế sẽ tự đếm chính tín hiệu
 *     mình phát ra thành tiền khách nạp. Chặn ngay lúc biên dịch, xem static_assert dưới.
 *
 *  ⚠️ `mdbTask()` chạy trên NHÂN UI và có lúc chặn tới ~150ms (chờ máy trả lời) kèm
 *     `noInterrupts()` từng byte. Với `USE_MDB=false` (mặc định) thì không tốn gì. Bật lên mà
 *     thấy đồng hồ đếm ngược giật thì chuyển khối này sang netTask — nhưng lúc đó phải khoá
 *     `state`/`runUntil` vì hai nhân cùng chạm.
 * =========================================================================================== */
#define MDB_TX_PIN   27
#define MDB_RX_PIN   35
#define MDB_BIT_US   104      // 1/9600 = 104.17us (chỉnh nếu lệch)
#define MDB_DEBUG    true     // in RAW từng byte 9-bit nhận được — để soi máy có trả lời không
#define MDB_INVERT_TX true    // cực tính GỬI ban đầu; firmware tự đảo thử nếu máy không trả lời

static_assert(!(USE_MDB && CASH_ENABLE),
  "USE_MDB va CASH_ENABLE cung dung GPIO27 — bat ca hai la ghe tu dem tin hieu minh phat ra "
  "thanh tien khach nap. Chon MOT: may MDB thi CASH_ENABLE=false, may xung thi USE_MDB=false.");

static bool g_rxInv = false;
static bool g_txInv = MDB_INVERT_TX;
static int  mdbFails = 0;
#define MDB_A_RESET  0x30     // địa chỉ bill validator (0x30) | lệnh
#define MDB_A_SETUP  0x31
#define MDB_A_POLL   0x33
#define MDB_A_BILL   0x34     // BILL TYPE (enable)

static uint8_t mdbBuf[40]; static int mdbN = 0;
static long   mdbScale = 1;               // scaling factor (từ SETUP)
static uint8_t mdbCredit[16];             // hệ số giá trị mỗi loại bill
static int    mdbState = 0;               // 0=reset 1=setup 2=enable 3=poll
static unsigned long mdbLastPoll = 0, mdbLastStep = 0;

// mức vật lý cho 1 bit LOGIC khi GỬI: logic1 = idle/mark, logic0 = start
static inline int mdbPhysTx(int logicBit){ return g_txInv ? !logicBit : logicBit; }

// gửi 1 byte 9-bit: start(0) + 9 data (LSB trước, bit8 = mode) + stop(1)
void IRAM_ATTR mdbTx9(uint16_t v){
  noInterrupts();
  digitalWrite(MDB_TX_PIN, mdbPhysTx(0)); delayMicroseconds(MDB_BIT_US);
  for(int i=0;i<9;i++){ digitalWrite(MDB_TX_PIN, mdbPhysTx((v>>i)&1)); delayMicroseconds(MDB_BIT_US); }
  digitalWrite(MDB_TX_PIN, mdbPhysTx(1)); delayMicroseconds(MDB_BIT_US);
  interrupts();
}
// đọc 1 byte 9-bit; trả 0..511 (bit8 = mode), -1 nếu quá hạn
int IRAM_ATTR mdbRx9(unsigned long toMs){
  unsigned long t0 = millis();
  int idlePhys = g_rxInv ? LOW : HIGH;
  while(digitalRead(MDB_RX_PIN) == idlePhys){ if(millis()-t0 > toMs) return -1; }
  noInterrupts();
  delayMicroseconds(MDB_BIT_US + MDB_BIT_US/2);      // tới GIỮA bit data đầu
  uint16_t v = 0;
  for(int i=0;i<9;i++){ int r = digitalRead(MDB_RX_PIN); int logic = g_rxInv ? !r : r; if(logic) v |= (1<<i); delayMicroseconds(MDB_BIT_US); }
  interrupts();
  return v;
}
/* TỰ DÒ cực tính RX: đo mức đường lúc RẢNH. Idle phải là MARK (logic 1). Đường rảnh ở mức THẤP
   nghĩa là tín hiệu đảo. Nếu đo ra CẢ HAI mức lẫn lộn thì đường đang thả nổi hoặc thiếu GND
   chung — nói thẳng ra, vì ca đó dò kiểu gì cũng sai. */
void mdbAutoDetectRx(){
  int hi=0, lo=0;
  for(int i=0;i<300;i++){ if(digitalRead(MDB_RX_PIN)) hi++; else lo++; delayMicroseconds(40); }
  g_rxInv = (lo > hi);
  Serial.printf("[MDB] auto RX: hi=%d lo=%d -> invertRX=%s%s\n", hi, lo, g_rxInv?"true":"false",
                (hi>30 && lo>30) ? "  (!! duong dao dong ~ nhieu/tha noi -> kiem day/GND)" : "");
}
// gửi block lệnh: addr/cmd (mode=1) + data (mode=0) + checksum (mode=0)
static void mdbSend(uint8_t cmd, const uint8_t* d, int n){
  uint16_t chk = cmd; mdbTx9(0x100 | cmd);
  for(int i=0;i<n;i++){ mdbTx9(d[i]); chk += d[i]; }
  mdbTx9(chk & 0xFF);
}
// đọc phản hồi. -2 quá hạn, -1 sai checksum, 0=ACK, 255=NAK, >0 = số byte data
static int mdbResp(unsigned long toMs){
  mdbN = 0; unsigned long t0 = millis();
  while(millis()-t0 < toMs){
    int b = mdbRx9(60); if(b < 0) continue;
    if(MDB_DEBUG) Serial.printf(" rx=%03X", b);
    uint8_t v = b & 0xFF;
    if(b & 0x100){                                   // byte có MODE=1 = byte cuối
      if(mdbN==0){ if(v==0x00) return 0; if(v==0xFF) return 255; }
      uint16_t s=0; for(int i=0;i<mdbN;i++) s += mdbBuf[i];
      if((s & 0xFF) != v) return -1;
      mdbTx9(0x000);                                 // ACK
      return mdbN;
    } else if(mdbN < (int)sizeof(mdbBuf)) mdbBuf[mdbN++] = v;
  }
  return -2;
}

void mdbInit(){
  if(!USE_MDB) return;
  pinMode(MDB_TX_PIN, OUTPUT); digitalWrite(MDB_TX_PIN, mdbPhysTx(1));
  pinMode(MDB_RX_PIN, INPUT);
  mdbState = 0; mdbLastStep = 0;
  Serial.printf("[MDB] init TX=%d RX=%d @9600 9-bit\n", MDB_TX_PIN, MDB_RX_PIN);
  mdbAutoDetectRx();
}

/* Máy MDB báo đã nuốt một tờ -> chạy/cộng giờ ghế, rồi xếp vào hàng chờ ghi sổ.
   Dùng CHUNG `g_pendingCashLog` + `g_cashRef` với đường đếm xung: một chỗ ghi sổ duy nhất, nên
   không thể có chuyện hai đường ghi ra hai kiểu. */
static void mdbCreditVnd(long vnd){
  if(vnd <= 0) return;
  int minutes = (PRICE_VND>0) ? (int)((vnd*(long)MINUTES)/PRICE_VND) : 0;
  Serial.printf("[MDB] +%ld d -> %d phut\n", vnd, minutes);
  if(minutes <= 0) return;
  if(state==ST_RUNNING){ runUntil += (unsigned long)minutes*60000UL; g_statusDirty=true; Serial.println("[MDB] +gio (dang chay)"); }
  else { g_srcCode='c'; startRunning(minutes); }
  portENTER_CRITICAL(&g_mux);
  g_pendingCashLog += vnd;
  /* ⚠️ PHẢI có mã ổn định cho đợt này. Để rỗng thì máy chủ tự sinh mã từ (giờ + tiền + nội
     dung) — mà giờ thì đổi mỗi giây, nên netTask gửi lại vì mạng chập chờn là đẻ ra một dòng
     doanh thu MỚI. Cùng lý do với đường đếm xung, xem checkCash(). */
  if(g_cashRef[0] == 0){
    snprintf(g_cashRef, sizeof(g_cashRef), "mdb-%s-%lu",
      CHAIR_ID.length()?CHAIR_ID.c_str():"?", (unsigned long)millis());
  }
  portEXIT_CRITICAL(&g_mux);
  g_runTotalVnd += vnd; updateAcceptor();
}

// Gọi mỗi vòng loop(). Chu trình: RESET -> SETUP -> BILL TYPE(enable) -> POLL lặp.
void mdbTask(){
  if(!USE_MDB) return;
  if(millis()-mdbLastStep < 60) return;
  mdbLastStep = millis();

  if(mdbState==0){                          // RESET
    mdbAutoDetectRx();                       // dò lại (phòng máy lên nguồn sau ESP)
    Serial.println("[MDB] RESET");
    mdbSend(MDB_A_RESET, NULL, 0);
    int r = mdbResp(250);
    Serial.printf("[MDB] reset resp=%d\n", r);
    mdbState = 1; return;
  }
  if(mdbState==1){                          // SETUP (đọc cấu hình + bảng giá bill)
    mdbSend(MDB_A_SETUP, NULL, 0);
    int r = mdbResp(350);
    if(r > 10){
      mdbScale = ((long)mdbBuf[3]<<8) | mdbBuf[4];
      for(int i=0;i<16 && (11+i)<mdbN;i++) mdbCredit[i] = mdbBuf[11+i];
      Serial.printf("[MDB] SETUP ok feature=%d scale=%ld\n", mdbBuf[0], mdbScale);
      Serial.print("[MDB] gia bill:"); for(int i=0;i<7;i++) Serial.printf(" t%d=%ld", i, (long)mdbCredit[i]*mdbScale); Serial.println();
      mdbFails = 0; mdbState = 2;
    } else {
      Serial.printf("[MDB] SETUP fail=%d -> reset\n", r); mdbState = 0;
      if(++mdbFails % 4 == 0){               // máy im lâu -> tự đảo thử cực tính TX
        g_txInv = !g_txInv; digitalWrite(MDB_TX_PIN, mdbPhysTx(1));
        Serial.printf("[MDB] >> tu dao cuc tinh TX -> invertTX=%s\n", g_txInv ? "true" : "false");
      }
      delay(400);
    }
    return;
  }
  if(mdbState==2){                          // BILL TYPE: nhận mọi mệnh giá, không escrow
    uint8_t d[4] = {0xFF,0xFF, 0x00,0x00};
    mdbSend(MDB_A_BILL, d, 4);
    int r = mdbResp(200);
    Serial.printf("[MDB] ENABLE resp=%d\n", r);
    mdbState = 3; return;
  }
  if(mdbState==3){                          // POLL định kỳ
    if(millis()-mdbLastPoll < 150) return;
    mdbLastPoll = millis();
    mdbSend(MDB_A_POLL, NULL, 0);
    int r = mdbResp(150);
    if(r > 0){
      for(int i=0;i<mdbN;i++){
        uint8_t z = mdbBuf[i];
        if(z & 0x80){                        // sự kiện bill: 1 rrr tttt
          uint8_t routing = (z>>4)&0x07, type = z & 0x0F;
          long val = (long)mdbCredit[type]*mdbScale;
          Serial.printf("[MDB] BILL routing=%d type=%d val=%ld\n", routing, type, val);
          if(routing==0 || routing==1) mdbCreditVnd(val);   // 0=đã vào khay, 1=escrow
        } else {
          Serial.printf("[MDB] status 0x%02X\n", z);
          if(z==0x06){ Serial.println("[MDB] (Just Reset) -> setup lai"); mdbState=1; }
        }
      }
    } else if(r == -1) Serial.println("[MDB] poll checksum sai");
  }
}

// ======================= SETUP / LOOP =======================
void netTask(void*);

void setup(){
  Serial.begin(115200); delay(200);
  Serial.println("\n\n=== " FW_VERSION " ===");
  pinMode(RELAY_PIN, OUTPUT); relaySet(false);
  pinMode(BL_PIN, OUTPUT); digitalWrite(BL_PIN, HIGH);
  if(CASH_ENABLE){ pinMode(CASH_PULSE_PIN, INPUT_PULLUP);
    attachInterrupt(digitalPinToInterrupt(CASH_PULSE_PIN), onCashPulse, FALLING); }
  if(CASH_INHIBIT_ENABLE){ pinMode(INHIBIT_PIN, OUTPUT); }
  setAcceptorEnabled(true);
  mdbInit();

  prefs.begin("ghe", false);
  CHAIR_ID = prefs.getString("chair", "");   // nhớ mã ghế máy chủ đã gán, để mất mạng vẫn hiện đúng

  tft.init(); tft.setRotation(1); tft.fillScreen(COL_BG);
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(0xCE40, COL_BG); tft.setTextSize(2); tft.drawString("K&H", 160, 76, 4); tft.setTextSize(1);
  tft.setTextColor(COL_ACC, COL_BG); tft.drawString("POSH massage", 160, 128, 4);
  tft.setTextColor(0x9CD3, COL_BG); tft.drawString("Relax in style", 160, 158, 2);
  delay(1800);
  tft.fillScreen(COL_BG); tft.setTextColor(COL_ACC, COL_BG);
  tft.drawString("Dang khoi dong...", 160, 120, 4);

  tsSPI.begin(T_CLK, T_DO, T_DIN, T_CS);
  ts.begin(tsSPI); ts.setRotation(1);

  if(USE_4G){ net4gConnect(); }
  else { WiFi.begin(WIFI_SSID, WIFI_PASS); unsigned long t0=millis();
    while(WiFi.status()!=WL_CONNECTED && millis()-t0<15000) delay(300);
    Serial.println(WiFi.status()==WL_CONNECTED ? "[WiFi] OK" : "[WiFi] fail"); }

  if(netUp()) guiNhip();     // lấy mã ghế + giá + số tài khoản ngay lượt đầu
  if(CHAIR_ID.length()) prefs.putString("chair", CHAIR_ID);
  state = ST_IDLE; screenDrawn=false;
  xTaskCreatePinnedToCore(netTask, "netTask", 10240, NULL, 1, NULL, 0);
}

/* ===== NHÂN 1 (loop): CHỈ GIAO DIỆN — không có lệnh 4G nào, nên màn không bao giờ đơ ===== */
void loop(){
  { static uint32_t _pc=0; uint32_t c=g_cashPulses; if(c!=_pc){ _pc=c; g_lastPulseMs=millis(); } }
  checkCash();
  mdbTask();

  if(g_remoteStop){ g_remoteStop=false;
    if(state!=ST_IDLE){ relaySet(false); state=ST_IDLE; g_srcCode=0; g_payWaiting=false;
      g_runTotalVnd=0; setAcceptorEnabled(true); g_statusDirty=true; screenDrawn=false;
      Serial.println("[CMD] -> da TAT may"); }
  }
  if(g_remoteStartMin > 0){ int m=g_remoteStartMin; g_remoteStartMin=0;
    g_srcCode='r'; startRunning(m); Serial.printf("[CMD] -> da MO may %d phut\n", m);
  }

  /* Nhận tiền QR -> chạy ghế ở MỌI trạng thái, kể cả sau khi khách đã bấm huỷ hoặc màn đã tắt
     QR. Khách đã trả tiền thì phải được massage. */
  if(g_paidAmount > 0){
    long paid = g_paidAmount; g_paidAmount = 0;
    int mins = payMinutes;
    if(PRICE_VND > 0 && paid >= PRICE_VND){ int m2 = (int)((paid / PRICE_VND) * MINUTES); if(m2 > mins) mins = m2; }
    if(mins <= 0) mins = MINUTES;
    Serial.printf("[PAY] Đã nhận %ld đ -> chạy %d phút\n", paid, mins);
    g_srcCode='q'; g_runTotalVnd += paid; updateAcceptor(); startRunning(mins);
    return;
  }

  if(state==ST_IDLE){
    if(!screenDrawn){ drawIdle(); screenDrawn=true; }
    int x,y;
    if(getTouch(x,y) && !CHUA_GAN && CHAIR_ID.length()){
      for(int i=0;i<PKG_N;i++) if(inBtn(PKG_BTN[i],x,y)){ startSession(i); delay(250); return; }
    }
    /* Ghế chưa gán mã thì vẽ lại màn mỗi 5s — để dòng "4G ON" và mã MAC cập nhật cho người đang
       đứng lắp máy nhìn. */
    static unsigned long veLai=0;
    if((CHUA_GAN || CHAIR_ID.length()==0) && millis()-veLai > 5000){ veLai=millis(); screenDrawn=false; }
  }
  else if(state==ST_WAIT_PAY){
    if(!screenDrawn){ drawQRScreen(); screenDrawn=true; }
    int secLeft = (int)((waitUntil - millis())/1000);
    if((long)(waitUntil - millis()) <= 0){
      Serial.println("[PAY] hết hạn -> về màn chính (còn theo dõi tiền ~20s)");
      g_payWaiting=false; state=ST_IDLE; g_srcCode=0; g_statusDirty=true; screenDrawn=false; return; }
    if(secLeft != lastShownSec){ lastShownSec=secLeft; drawWaitCountdown(secLeft); }
    int x,y; if(getTouch(x,y)){ Serial.println("[PAY] khách hủy -> van theo doi tien ~20s");
      g_payWaiting=false; g_watchPayUntil = millis() + PAY_GRACE_MS;
      state=ST_IDLE; g_srcCode=0; g_statusDirty=true; screenDrawn=false; delay(300); return; }
  }
  else if(state==ST_RUNNING){
    long left = (long)(runUntil - millis());
    if(left <= 0){ relaySet(false); Serial.println("[RUN] hết giờ -> tắt ghế");
      state=ST_IDLE; g_srcCode=0; g_statusDirty=true; screenDrawn=false;
      g_runTotalVnd=0; setAcceptorEnabled(true); return; }
    int secLeft = (int)(left/1000);
    if(secLeft != lastShownSec){ lastShownSec=secLeft; drawRunning(secLeft); }
  }
  delay(20);
}

/* ===== NHÂN 0 (netTask): mọi lượt gọi mạng (chặn 2-5s) — tách khỏi UI ===== */
void netTask(void*){
  Serial.println("[NET] task mang chay tren core 0");
  for(;;){
    bool dangCho = (g_watchPayUntil > 0 && millis() < g_watchPayUntil);

    if(!dangCho && USE_4G && g_4gReady && millis()-lastRegCheck > 30000UL){
      lastRegCheck = millis();
      String e = atSend("AT+CEREG?",1200), g2 = atSend("AT+CGREG?",1200);
      if(!(e.indexOf(",1")>=0||e.indexOf(",5")>=0||g2.indexOf(",1")>=0||g2.indexOf(",5")>=0)){
        Serial.printf("[4G] MAT DANG KY (CEREG=%s CGREG=%s) -> ket noi lai\n", e.c_str(), g2.c_str());
        g_4gReady = false;
      }
    }
    if(USE_4G && !g_4gReady && millis()-last4gTry > 15000UL){
      last4gTry = millis();
      String r = atSend("AT+CEREG?", 1200), g = atSend("AT+CGREG?", 1200);
      if(r.indexOf(",1")>=0||r.indexOf(",5")>=0||g.indexOf(",1")>=0||g.indexOf(",5")>=0){
        atSend("AT+CGACT=1,1", 8000); g_4gReady = true;
        Serial.println("[4G] ĐÃ ĐĂNG KÝ MẠNG LẠI!"); g_statusDirty = true;
      } else { atSend("AT+CFUN=1",2000); atSend("AT+COPS=0", 6000);
        Serial.printf("[4G] chua co mang CEREG=%s CGREG=%s\n", r.c_str(), g.c_str()); }
    }

    /* Đang chờ khách trả -> ƯU TIÊN hỏi tiền, không chen việc khác, để bắt tiền cho lẹ. */
    if(dangCho && netUp() && millis()-lastPayPoll > PAY_POLL_MS){
      lastPayPoll = millis();
      long paid = checkPaid();
      if(paid > 0){ g_paidAmount = paid; Serial.printf("[NET] thay tra %ld d\n", paid); }
    }

    /* Tiền mặt còn nợ sổ: đẩy LUÔN, kể cả lúc đang chờ trả — nó là tiền đã vào két rồi. */
    if(g_pendingCashLog > 0 && netUp()){
      portENTER_CRITICAL(&g_mux); long a=g_pendingCashLog; char ref[40]; strncpy(ref,g_cashRef,sizeof(ref)); portEXIT_CRITICAL(&g_mux);
      if(a>0){
        baoTienMat(a, ref);
        /* Xoá phần ĐÃ gửi chứ không đặt về 0 thẳng: giữa lúc gửi có thể khách nhét thêm tờ nữa,
           đặt 0 là mất luôn tờ đó khỏi sổ. */
        portENTER_CRITICAL(&g_mux);
        g_pendingCashLog -= a;
        if(g_pendingCashLog <= 0){ g_pendingCashLog = 0; g_cashRef[0] = 0; }
        portEXIT_CRITICAL(&g_mux);
      }
    }

    if(!dangCho && netUp() && (g_statusDirty || millis()-lastNhipMs > NHIP_MS)){
      guiNhip();
      if(CHAIR_ID.length() && prefs.getString("chair","") != CHAIR_ID) prefs.putString("chair", CHAIR_ID);
      if(g_coLenh) checkRemoteCmd();
    }
    vTaskDelay(pdMS_TO_TICKS(dangCho ? 40 : 120));
  }
}
