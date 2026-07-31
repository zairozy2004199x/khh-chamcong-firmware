/*
 * ESP32-2432S028 (CYD) <-> Máy chấm công Hikvision SH-K1T9343MFWX
 * HAI CHIỀU:
 *  (1) ĐỌC lượt chấm công -> đẩy Google Sheets + HIỆN "Xin cảm ơn" lên TFT (như bản cũ).
 *  (2) GHI nhân viên: poll web app "QuanLyNhanVien" lấy lệnh thêm/sửa/xóa NV (kèm ảnh)
 *      rồi ghi vào máy Hikvision qua ISAPI (UserInfo + FDLib/FaceDataRecord).
 *
 * THƯ VIỆN CẦN CÀI (Library Manager):
 *   - ArduinoJson (Benoit Blanchon)
 *   - TFT_eSPI (Bodmer)  -> cấu hình User_Setup.h cho CYD (xem tft_display_addon.md)
 *   - TJpg_Decoder (Bodmer)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <MD5Builder.h>
#include "mbedtls/base64.h"
#include <Preferences.h>
#include <time.h>
#include <sys/time.h>    // settimeofday() — set đồng hồ từ giờ mạng 4G (AT+CCLK?)
#include <TFT_eSPI.h>
#include <TJpg_Decoder.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <Update.h>       // OTA qua WiFi AP nội bộ (nạp .bin qua trình duyệt, không cần cáp)
#include <PPP.h>          // Internet qua 4G A7680C (ESP32 Arduino core >= 3.0)
//#define LOAD_FONT7
#define LOAD_FONT6
// ======================= CẤU HÌNH =======================
#define FW_VERSION "2026-07-31b (nhu 31a, + bat o chua trong kieu \"...\" trong secrets.h la CHUA KHAI, khong ghi vao NVS)"  // đổi mỗi lần sửa -> nhìn boot log biết bản nào đang chạy

// ---- BÍ MẬT: nằm ở secrets.h (KHÔNG commit — .gitignore có mẫu `secrets.*`) ----
// Chưa có file thì copy secrets.example.h -> secrets.h rồi điền. Build BÁO LỖI nếu thiếu,
// cố ý: thà không build được còn hơn nạp firmware bằng mật khẩu mẫu.
// ⚠️ Các giá trị CŨ đã từng nằm trong repo nên coi như ĐÃ LỘ (git giữ lịch sử vĩnh viễn).
//    Tách file KHÔNG tự làm chúng an toàn — phải ĐỔI Firebase secret + EMP_TOKEN + mật khẩu Hikvision.
#if !__has_include("secrets.h")
  #error "Thieu secrets.h — copy secrets.example.h thanh secrets.h roi dien gia tri that."
#endif
#include "secrets.h"

/* ⚠️ KHAI BÁO TRƯỚC — PHẢI ĐẶT Ở ĐÂY, ĐỪNG DỜI XUỐNG GIỮA FILE.
   Arduino/arduino-cli tự sinh prototype cho hàm trong .ino, NHƯNG nếu file đã có sẵn một
   khai báo thì nó THÔI tự sinh. Đặt khai báo ở giữa file là mọi chỗ dùng phía TRÊN nó
   mất khai báo -> build đỏ. Đã bị đúng lỗi này: `bool netUp();` đặt ở dòng ~640 làm
   drawNetStatus() (dòng 238) báo "'netUp' was not declared in this scope". */
/* Giá trị "chưa khai" dùng chung cho mọi khoá cấu hình. PHẢI đứng trước SEC_EXEC_URL /
   SEC_FB_HOST ở dưới — macro chỉ nở ra lúc DÙNG, nên định nghĩa sau là lỗi compile. */
#define CFG_PLACEHOLDER "__CHUA_CAU_HINH__"

String empGet(const String& query);
String urlEncodeMin(const String& s);
bool   netUp();

// WiFi cửa hàng MẶC ĐỊNH (dùng khi flash lần đầu / chưa lưu cấu hình). Đổi tại portal 192.168.4.1.
const char* ssid     = SEC_WIFI_SSID;
const char* password = SEC_WIFI_PASS;
const char* AP_PASS  = SEC_AP_PASS;   // mật khẩu WiFi cấu hình (AP "CHAM_CONG" @ 192.168.4.1)

// --- CHẾ ĐỘ MẠNG ---
const bool  USE_4G   = true;         // true: cửa hàng KHÔNG có WiFi -> Hikvision nối AP của ESP32, Internet qua 4G A7680C. false: dùng WiFi cửa hàng như cũ.
// --- A7680C (4G) — chỉ dùng khi USE_4G=true ---
const char* SIM_APN  = "v-internet"; // Viettel
const long  PPP_BAUD = 9600;        // baud UART cho PPP. 9600 = ổn định qua dây 2 sợi không flow-control (chấm công đủ dùng). Nếu muốn nhanh + có dây RTS/CTS thì để 115200.
#define SIM_TX_PIN   4             // ESP32 TX -> A7680C RX
#define SIM_RX_PIN   16              // ESP32 RX <- A7680C TX
#define SIM_PWRKEY   17              // chân PEN/PWRKEY bật module (xem modemPowerOn nếu cần chỉnh)

// IP Hikvision: 4G -> đặt máy Hik nối AP ESP32 với IP TĨNH này (gateway 192.168.4.1); WiFi -> IP trên LAN cửa hàng
const char* hik_ip   = USE_4G ? "192.168.4.50" : "192.168.4.1";
const char* hik_user = SEC_HIK_USER;
const char* hik_pass = SEC_HIK_PASS;

String STATION_NAME = "CHUA_DAT_TEN";   // ĐỔI qua portal 192.168.4.1 (lưu Preferences 'station') -> 1 file .bin dùng cho MỌI cửa hàng
/* MÃ THIẾT BỊ để server tự biết máy này ở cửa hàng nào — KHỎI phải gõ tên ở portal từng máy.
   Khoá chính là SERIAL ĐẦU ĐỌC (đầu đọc gắn tường, ít thay -> thay bo ESP32 thì cắm điện là chạy);
   MAC bo là khoá dự phòng. Bảng ghép mã -> cửa hàng nằm ở SERVER (sheet MayChamCong), sửa được
   trong web app mà KHÔNG phải nạp lại firmware. */
String HIK_SERIAL = "";          // đọc từ đầu đọc (ISAPI deviceInfo), nhớ vào Preferences 'hikSn'
String HIK_MODEL  = "";          // chỉ để anh nhìn cho biết máy nào, không dùng để ghép
bool   STATION_TU_SERVER = false;   // tên cơ sở hiện tại do server trả về (đã gán) hay còn là bản nhớ cũ
unsigned long lastWhoAmIMs = 0;
#define WHOAMI_CHU_KY_MS (30UL*60UL*1000UL)   // hỏi lại mỗi 30' -> đổi gán trong web app là máy tự theo
const char* OTA_USER = SEC_OTA_USER;          // tài khoản đăng nhập trang cập nhật firmware /update
const char* OTA_PASS = SEC_OTA_PASS;    // ĐỔI mật khẩu này nếu muốn; trang /update yêu cầu đăng nhập

// ⚠️ 30/07/2026 — deployment ĐÃ ĐỔI một lần. Bài học: `clasp deploy -i <id cũ>` báo
// "Requested entity was not found" nghĩa là id đó gắn với script CŨ, không phải script đang chạy.
// (scriptId + deployment id thật ghi ở LINKS-VA-ID.md của repo nội bộ — cố ý KHÔNG để ở đây.)
// doGet + doPost nằm chung 1 web app, nên máy và dashboard dùng cùng một link /exec.
// ⚠️ PHẢI dùng dạng  /macros/s/<id>/exec  — KHÔNG dùng  /a/macros/<tên miền>/s/<id>/exec :
// dạng có tên miền là link cho người đã đăng nhập Workspace, thiết bị gọi ẩn danh sẽ bị chặn.
// ⚠️ Sửa file này KHÔNG tự lên máy — phải nạp lại qua Arduino IDE hoặc đẩy .bin qua tab "Cập nhật FW".
// ⚠️ 31/07/2026 — LINK THẬT ĐÃ GỠ KHỎI MÃ NGUỒN. Mã này nằm ở repo firmware CÔNG KHAI
//    (khh-chamcong-firmware) để CI tự build; để link thật ở đây là công khai luôn địa chỉ
//    web app + Firebase của công ty. Link thật giờ CHỈ nằm ở 2 chỗ:
//      1) secrets.h trên máy anh (đã .gitignore) — dùng khi nạp USB;
//      2) NVS của từng máy đã chạy bản 2026-07-30c trở lên — OTA không ghi đè.
//    Chip trắng nạp bản CI thì khai ở portal 192.168.4.1 (ô "Link web app /exec").
#ifndef SEC_EXEC_URL
  #define SEC_EXEC_URL CFG_PLACEHOLDER
#endif
const char* google_script_url = SEC_EXEC_URL;

// --- Đồng bộ nhân viên: DÙNG CHUNG web dashboard chấm công (cùng /exec) ---
// doGet của dashboard đã xử lý action=pending/photo/ack. Token phải khớp EMP_TOKEN trong Code.gs.
const char* emp_script_url = google_script_url;
const char* EMP_TOKEN      = SEC_EMP_TOKEN;

// --- Firebase Realtime DB: nguồn ĐỌC lệnh NV cho ESP (URL NGẮN, KHÔNG redirect -> đọc được qua 4G) ---
// Vì sao: đọc kết quả Apps Script qua 4G bị chặn (redirect googleusercontent ~468 ký tự > giới hạn dòng AT).
// Tạo tại console.firebase.google.com > Build > Realtime Database. ĐIỀN 2 dòng dưới sau khi tạo:
const bool  USE_FIREBASE  = true;   // true = ESP đọc lệnh NV từ Firebase (bắt buộc khi chạy 4G)
#ifndef SEC_FB_HOST        // như SEC_EXEC_URL ở trên: host thật đã gỡ, lấy từ secrets.h / NVS / portal
  #define SEC_FB_HOST CFG_PLACEHOLDER
#endif
const char* FB_HOST       = SEC_FB_HOST;   // URL database (KHÔNG có "/" ở cuối)
const char* FB_SECRET     = SEC_FB_SECRET;   // Database secret (quyền admin, bỏ qua rule). Để "REPLACE..." = KHÔNG gửi auth (rule mở)
const bool  FB_SYNC_PHOTO = true;   // Phase 2: đồng bộ ảnh khuôn mặt (đọc /photo từ Firebase, ghi mặt vào Hikvision)


#define BL_PIN 21          // Đèn nền CYD (không đặt tên TFT_BL để tránh trùng User_Setup)
#define WINDOW 30
#define DEBOUNCE_SEC 10
const unsigned long POLL_INTERVAL_MS = 5000;
const unsigned long STATUS_HOLD_MS   = 8000;   // giữ màn "cảm ơn" bao lâu rồi về màn chờ

// --- Đồng hồ màn chờ (NTP) ---
const long  GMT_OFFSET_SEC = 7 * 3600;         // Việt Nam GMT+7
const int   DST_OFFSET_SEC = 0;
const char* NTP_SERVER1    = "pool.ntp.org";
const char* NTP_SERVER2    = "time.google.com";

// --- Đồng bộ nhân viên ---
const unsigned long EMP_POLL_MS = 10000;       // chu kỳ poll hàng đợi nhân viên (10s: quét/thêm/xóa lên nhanh hơn ~3 lần; poll rỗng chỉ ~4 byte nên tốn 4G không đáng kể)
const int   MIN_HEAP_FOR_PHOTO  = 120000;      // ngưỡng RAM tối thiểu để xử lý ảnh (byte)
const int   MAX_FACE_BYTES      = 90000;       // buffer tối đa cho 1 ảnh JPEG khuôn mặt
const char* FDID_STR            = "1";         // face lib mặc định trên terminal
const char* FACE_LIB_TYPE       = "blackFD";   // thư viện mặt chuẩn (KHÔNG phải blacklist)
// --- Chống mất dữ liệu + heartbeat online (Đợt 1) ---
const char* FAR_FUTURE                  = "2037-12-31T23:59:59+07:00";   // endTime = "đến hiện tại" cho backfill
const unsigned long HB_INTERVAL_MS      = 60000;      // gửi heartbeat online lên Firebase mỗi 60s
const unsigned long SAFETY_BACKFILL_MS  = 1800000;    // định kỳ bù lượt sót mỗi 30 phút (lưới an toàn)
const unsigned long OTA_CHECK_MS        = 300000;     // kiểm tra firmware mới (Firebase /ota) mỗi 5 phút
// ========================================================

TFT_eSPI tft = TFT_eSPI();
long  lastSerialNo = -1;
unsigned long lastPoll = 0;
unsigned long empLastPoll = 0;      // chu kỳ poll hàng đợi nhân viên
unsigned long statusUntil = 0;      // thời điểm quay lại màn chờ
Preferences prefs;

/* ===========================================================================
 *  BÍ MẬT + 2 LINK: LẤY TỪ Preferences (NVS), giá trị compile chỉ là DỰ PHÒNG
 * ---------------------------------------------------------------------------
 *  Vì sao: file .bin compile từ secrets.h chứa mật khẩu Hikvision, EMP_TOKEN và
 *  Firebase database secret (quyền admin — ai có nó ĐẨY ĐƯỢC FIRMWARE TUỲ Ý vào
 *  mọi máy). Muốn CI tự build rồi để .bin ở chỗ tải công khai được (để bấm cập
 *  nhật từ xa) thì .bin BẮT BUỘC không được chứa mấy thứ đó.
 *
 *  Cách làm: mỗi giá trị đọc từ NVS trước; NVS chưa có mà bản compile có giá trị
 *  thật thì CHÉP VÀO NVS ngay. **OTA không ghi đè NVS**, nên chỉ cần chạy bản này
 *  một lần là máy giữ được bí mật, và các bản CI sau (secrets.h toàn placeholder)
 *  vẫn chạy bình thường — không phải tới từng máy khai lại.
 *
 *  ⚠️ Máy CHƯA từng chạy bản này mà nhận bản CI thì mất cấu hình -> phải nạp USB
 *     hoặc khai tay ở portal. Web app có chặn/cảnh báo theo phiên bản heartbeat.
 * =========================================================================== */
// CFG_PLACEHOLDER đã định nghĩa ở ĐẦU FILE (SEC_EXEC_URL/SEC_FB_HOST dùng tới nó từ dòng ~100).
// Giữ giá trị thật ở String toàn cục (sống suốt đời chương trình) rồi trỏ các con trỏ cũ vào .c_str()
String _cfgHikUser, _cfgHikPass, _cfgApPass, _cfgOtaUser, _cfgOtaPass,
       _cfgEmpTok, _cfgFbSec, _cfgExecUrl, _cfgFbHost;
bool   g_chuaCauHinh = false;      // thiếu giá trị bắt buộc -> hiện rõ trên màn hình, không chết im

/* ⚠️ PHẢI bắt cả GIÁ TRỊ MẪU của secrets.example.h, không chỉ "__CHUA_CAU_HINH".
   Đã trả giá thật 31/07/2026: secrets.h còn nguyên `SEC_EMP_TOKEN "TOKEN_WEB_APP"` và
   `SEC_FB_SECRET "FIREBASE_DATABASE_SECRET"`. Hai chuỗi đó KHÔNG bị coi là placeholder nên
   được CHÉP THẲNG VÀO NVS như giá trị thật -> máy gửi `&token=TOKEN_WEB_APP` (bad_token) và
   `?auth=FIREBASE_DATABASE_SECRET` (403), mà log thì báo `empTok=có fbSec=có` nên soi mãi
   không ra. Tệ hơn: một khi đã vào NVS thì sửa secrets.h cũng vô ích, NVS thắng. */
bool cfgLaPlaceholder(const String& v){
  if (v.length() == 0) return true;
  if (v.startsWith("__CHUA_CAU_HINH") || v.startsWith("REPLACE")) return true;
  static const char* MAU[] = { "TOKEN_WEB_APP", "FIREBASE_DATABASE_SECRET",
                               "MAT_KHAU", "TEN_WIFI", "DIEN_ID", "DIEN_TEN",
                               "CUA_HANG", "DIEN_VAO", "CHUA_KHAI" };
  for (unsigned i = 0; i < sizeof(MAU) / sizeof(MAU[0]); i++)
    if (v.indexOf(MAU[i]) >= 0) return true;
  /* ⚠️ 31/07/2026 — Ô CHỪA TRỐNG kiểu "..." / "---" / "***".
     Mẫu secrets.h phát cho người dùng để "..." ở chỗ phải tự điền. Chuỗi đó dài 3 ký tự,
     không trùng mẫu nào ở trên, nên TRƯỚC ĐÂY bị coi là giá trị THẬT và ghi thẳng vào NVS
     -> máy báo "có cấu hình" mà thật ra chưa có gì, đúng kiểu lỗi im lặng đã mất công 2 lần.
     Quy tắc: không có LẤY MỘT chữ hoặc số nào thì không thể là giá trị thật. */
  bool coChuSo = false;
  for (unsigned i = 0; i < v.length(); i++) {
    char c = v.charAt(i);
    if ((c >= '0' && c <= '9') || (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z')) { coChuSo = true; break; }
  }
  if (!coChuSo) return true;
  return false;
}
/* Link /exec phải dạng  /macros/s/<id>/exec.
   Dạng /a/macros/<tên miền>/s/… là link cho người ĐÃ ĐĂNG NHẬP Workspace — thiết bị gọi ẩn danh
   bị chặn. Còn id sai (VD DIEN_ID) thì Google trả 404. Cả hai trước đây im lặng: máy cứ đẩy, cứ
   thất bại, log chỉ có mã số. Nay sai dạng = coi như CHƯA CẤU HÌNH và nói rõ sai chỗ nào. */
bool execUrlHopLe(const String& u){
  if (u.length() == 0)              return false;
  if (u.indexOf("/a/macros/") >= 0) return false;
  if (u.indexOf("/macros/s/") < 0)  return false;
  if (!u.endsWith("/exec"))         return false;
  return true;
}
/**
 * Link Firebase RTDB có đúng dạng không — cùng ý với _fbHost() bên web app.
 * ⚠️ CỐ Ý chỉ nhận ".firebasedatabase.app", KHÔNG nhận dạng cũ "<ten>.firebaseio.com":
 *    CI quét file .bin tìm mẫu 'firebaseio' để chặn lộ link ra bản tải công khai, nên
 *    viết chuỗi đó vào firmware là CI đỏ — và đó là CI làm đúng việc của nó, đừng nới ra.
 *    Database của mình là dạng .firebasedatabase.app nên không mất gì. Web app thì vẫn
 *    nhận cả 2 dạng (Apps Script không bị quét, giữ đường lùi ở đó là được).
 */
bool fbHostHopLe(const String& u){
  if (!u.startsWith("https://")) return false;
  if (u.endsWith("/"))           return false;
  return u.indexOf(".firebasedatabase.app") > 0;
}
/** Giá trị này DÙNG ĐƯỢC không. Hai khoá là link nên còn phải đúng dạng, không chỉ "khác mẫu". */
bool cfgDungDuoc(const char* khoa, const String& v){
  if (cfgLaPlaceholder(v)) return false;
  if (strcmp(khoa, "execUrl") == 0) return execUrlHopLe(v);
  if (strcmp(khoa, "fbHost")  == 0) return fbHostHopLe(v);
  return true;
}
/**
 * Đọc cấu hình: NVS trước, giá trị trong secrets.h là dự phòng.
 * ⚠️ 31/07/2026 — TRƯỚC ĐÂY chỉ cần NVS có ký tự nào là NVS thắng, nên:
 *    máy nạp trước bản 2026-07-30c đã bị ghi thẳng giá trị MẪU ("FIREBASE_DATABASE_SECRET",
 *    "TOKEN_WEB_APP") và link /a/macros/<tên miền>/… vào NVS. Sửa secrets.h rồi nạp lại
 *    KHÔNG cứu được — chỉ "Erase All Flash" mới xoá, mà điều đó không ai đoán ra.
 *    Nay: NVS chỉ thắng khi giá trị trong NVS DÙNG ĐƯỢC. Rác trong NVS thì secrets.h ghi đè.
 *    Nhờ vậy "sửa secrets.h rồi nạp lại" là đủ, khỏi phải xoá sạch flash.
 */
String cfgLay(const char* khoa, const char* biencompile){
  String v = prefs.getString(khoa, "");
  String c = String(biencompile ? biencompile : "");
  if (cfgDungDuoc(khoa, v)) return v;                        // NVS tốt -> NVS thắng, như cũ
  if (cfgDungDuoc(khoa, c)) {                                // NVS rác/trống mà secrets.h tốt -> lấy
    prefs.putString(khoa, c);
    if (v.length()) Serial.printf("[CFG] '%s' trong may KHONG dung duoc -> thay bang secrets.h\n", khoa);
    else            Serial.printf("[CFG] di tru '%s' vao NVS\n", khoa);
    return c;
  }
  if (v.length()){
    // Chỉ 2 khoá này KHÔNG phải bí mật nên mới in ra được — in bí mật là hớ.
    bool khoe = (strcmp(khoa,"execUrl") == 0 || strcmp(khoa,"fbHost") == 0);
    Serial.printf("[CFG] ⚠️ '%s': ca trong may va secrets.h deu khong dung duoc%s%s\n",
                  khoa, khoe ? " — dang luu: " : "", khoe ? v.c_str() : "");
  }
  return "";
}
void napCauHinh(){
  _cfgHikUser = cfgLay("hikUser",  SEC_HIK_USER);
  _cfgHikPass = cfgLay("hikPass",  SEC_HIK_PASS);
  _cfgApPass  = cfgLay("apPass",   SEC_AP_PASS);
  _cfgOtaUser = cfgLay("otaUser",  SEC_OTA_USER);
  _cfgOtaPass = cfgLay("otaPass",  SEC_OTA_PASS);
  _cfgEmpTok  = cfgLay("empTok",   SEC_EMP_TOKEN);
  _cfgFbSec   = cfgLay("fbSec",    SEC_FB_SECRET);
  _cfgExecUrl = cfgLay("execUrl",  google_script_url);
  _cfgFbHost  = cfgLay("fbHost",   FB_HOST);
  hik_user = _cfgHikUser.c_str();  hik_pass = _cfgHikPass.c_str();
  AP_PASS  = _cfgApPass.c_str();
  OTA_USER = _cfgOtaUser.c_str();  OTA_PASS = _cfgOtaPass.c_str();
  EMP_TOKEN = _cfgEmpTok.c_str();  FB_SECRET = _cfgFbSec.c_str();
  google_script_url = _cfgExecUrl.c_str();  emp_script_url = _cfgExecUrl.c_str();
  FB_HOST = _cfgFbHost.c_str();
  // FB_SECRET được phép trống (rule Firebase mở thì gọi không kèm auth) -> KHÔNG tính là thiếu.
  bool _urlXau = !execUrlHopLe(_cfgExecUrl);
  g_chuaCauHinh = _urlXau || (_cfgFbHost.length() == 0) ||
                  (_cfgEmpTok.length()  == 0) || (_cfgHikPass.length() == 0) ||
                  (_cfgOtaPass.length() == 0);
  Serial.printf("[CFG] exec=%s fbHost=%s empTok=%s hikPass=%s otaPass=%s apPass=%s fbSec=%s\n",
    _cfgExecUrl.length()?"có":"THIẾU", _cfgFbHost.length()?"có":"THIẾU", _cfgEmpTok.length()?"có":"THIẾU",
    _cfgHikPass.length()?"có":"THIẾU", _cfgOtaPass.length()?"có":"THIẾU", _cfgApPass.length()?"có":"THIẾU",
    _cfgFbSec.length()?"có":"(trống - gọi Firebase không auth)");
  // Nói RÕ sai chỗ nào. Trước đây chỉ có một câu chung nên vẫn phải đi mò từng thứ.
  if (_urlXau) {
    Serial.println("[CFG] 🔴 LINK WEB APP SAI DẠNG — máy sẽ KHÔNG đẩy được chấm công.");
    Serial.println("        Đang có: " + (_cfgExecUrl.length() ? _cfgExecUrl : String("(trống)")));
    Serial.println("        Phải là: https://script.google.com/macros/s/<id>/exec");
    if (_cfgExecUrl.indexOf("/a/macros/") >= 0)
      Serial.println("        ⚠️ Dạng /a/macros/<tên miền>/… chỉ dùng được khi đã đăng nhập Workspace — bỏ phần đó đi.");
  }
  if (_cfgEmpTok.length() == 0) Serial.println("[CFG] 🔴 THIẾU token web app (EMP_TOKEN) — whoami và đồng bộ nhân viên sẽ bị chặn.");
  if (_cfgFbSec.length() == 0)  Serial.println("[CFG] 🟠 Chưa có Firebase secret — mất OTA từ xa / heartbeat. Chấm công VẪN chạy.");
  if (g_chuaCauHinh) Serial.println("[CFG] ⚠️ CHƯA CẤU HÌNH ĐỦ — vào AP \"CHAM_CONG\" @192.168.4.1 để khai, hoặc nạp USB bản có secrets.h thật.");
}
/** Che bí mật khi hiện lên portal: 4 ký tự đầu + độ dài. KHÔNG in giá trị thật. */
String cfgChe(const String& v){
  if (!v.length()) return "(trống)";
  return v.substring(0, 4) + "…(" + String(v.length()) + " ký tự)";
}
String lastEmp = "";
long   lastEmpSec = -100000;
String lastSyncTime = "";           // giờ lượt cuối đã đẩy OK ("YYYY-MM-DD HH:MM:SS") -> bù khi khởi động / định kỳ
unsigned long lastHbMs = 0;         // mốc gửi heartbeat online gần nhất
unsigned long lastSafetyBackfill = 0;   // mốc bù định kỳ gần nhất
unsigned long lastOtaCheck = 0;     // mốc kiểm tra OTA gần nhất
String g_otaTriedVer = "";          // phiên bản OTA đã thử (lỗi) trong phiên này -> chờ reboot/bản mới

// Trạng thái đồng hồ màn chờ
bool idleActive   = false;
int  lastClockSec = -1;
int  lastClockMin = -1;

// --- WiFi provisioning + web portal quản lý nhân viên ---
WebServer server(80);
DNSServer dnsServer;
String cfgSsid, cfgPass;            // WiFi cửa hàng đang dùng (đọc flash, fallback mặc định ssid/password)
unsigned long wifiDownSince = 0;    // thời điểm bắt đầu mất WiFi cửa hàng (0 = đang có)
int netStatusLast = -1;             // trạng thái mạng đã vẽ trên màn (-1 chưa vẽ)
unsigned long lastWebMs = 0;        // thời điểm request web gần nhất -> đang dùng portal thì tạm ngưng poll cho web mượt

// ---------- Callback vẽ JPEG lên TFT ----------
bool tftJpgOutput(int16_t x, int16_t y, uint16_t w, uint16_t h, uint16_t* bitmap) {
  if (y >= tft.height()) return 0;
  tft.pushImage(x, y, w, h, bitmap);
  return 1;
}

// ---------- Màn hình ----------
// Màu dùng chung cho màn chờ (RGB565)
#define COL_BG     TFT_BLACK
#define COL_PANEL  0x08CA              // navy đậm ~ rgb(8,24,80) - nền panel đồng hồ

// Chỉ báo mạng góc phải trên màn chờ (xanh = online, đỏ = mất). Chỉ vẽ khi trạng thái đổi.
void drawNetStatus(){
  int cur = netUp() ? 1 : 0;
  if (cur == netStatusLast) return;
  netStatusLast = cur;
  tft.setTextDatum(TR_DATUM);
  tft.setTextColor(cur ? TFT_GREEN : TFT_RED, COL_BG);
  tft.setTextPadding(70);
  tft.drawString(String(USE_4G ? "4G" : "WiFi") + (cur ? " ON" : " --"), 308, 12, 2);
  tft.setTextPadding(0);
}

// Cập nhật phần động của đồng hồ (giờ:phút:giây + ngày). Chỉ vẽ khi giây/phút đổi -> không nháy.
void updateClock() {
  drawNetStatus();
  struct tm t;
  if (!getLocalTime(&t, 10)) {
    // Chưa đồng bộ được NTP -> hiện gạch chờ
    tft.setTextDatum(MC_DATUM);
    tft.setTextColor(TFT_CYAN, COL_PANEL);
    tft.setTextPadding(tft.textWidth("88:88:88", 4));
    tft.drawString("--:--:--", 160, 100, 4);   // font 4 (nét, luôn hiện được trên máy này)
    tft.setTextPadding(0);
    return;
  }
  if (t.tm_sec == lastClockSec) return;   // chưa sang giây mới thì thôi
  lastClockSec = t.tm_sec;

  // Giờ:phút:giây bằng font 4 (nét, luôn hiện được — máy này chỉ nạp font 4, font 6/7 không có)
  char hms[12];
  sprintf(hms, "%02d:%02d:%02d", t.tm_hour, t.tm_min, t.tm_sec);
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_CYAN, COL_PANEL);
  tft.setTextPadding(tft.textWidth("88:88:88", 4));
  tft.drawString(hms, 160, 100, 4);
  tft.setTextPadding(0);

  // Ngày + thứ: chỉ vẽ lại khi sang phút mới
  if (t.tm_min != lastClockMin) {
    lastClockMin = t.tm_min;
    const char* wd[] = {"CHU NHAT","THU HAI","THU BA","THU TU","THU NAM","THU SAU","THU BAY"};
    char dstr[40];
    sprintf(dstr, "%s  -  %02d/%02d/%04d",
            wd[t.tm_wday], t.tm_mday, t.tm_mon + 1, t.tm_year + 1900);
    tft.setTextColor(TFT_WHITE, COL_BG);
    tft.setTextPadding(300);
    tft.drawString(dstr, 160, 168, 4);
    tft.setTextPadding(0);
  }
}

// Màn chờ = đồng hồ số. Vẽ phần khung/nhãn TĨNH 1 lần rồi để updateClock() lo phần động.
/* ---------- Khung + thanh tiến trình dùng chung cho các màn PHỤ ----------
 * Trước đây chỉ showIdle có khung cam + panel navy; bảy màn còn lại trơ chữ giữa nền đen.
 * Dùng lại ĐÚNG bảng màu đó cho các màn phụ -> nhìn thành một bộ.
 */
void veKhung(){
  uint16_t k = tft.color565(255, 140, 0);
  tft.drawRoundRect(3, 3, 314, 234, 10, k);
  tft.drawRoundRect(4, 4, 312, 232, 10, k);
}
const int TT_X = 30, TT_Y = 140, TT_W = 260, TT_H = 26;
int _ttPctCu = -1;
void ttKhung(){
  tft.fillRoundRect(TT_X, TT_Y, TT_W, TT_H, 6, COL_PANEL);
  tft.drawRoundRect(TT_X, TT_Y, TT_W, TT_H, 6, tft.color565(40, 90, 150));
  _ttPctCu = -1;
}
/* Chỉ tô lại phần đổi. Update.onProgress bắn RẤT dày (mỗi khối ghi) — vẽ mỗi lần bắn là
   màn nháy và làm chậm hẳn việc nạp, nên bỏ qua khi phần trăm chưa nhích. */
void ttPct(int pct){
  if (pct < 0) pct = 0; if (pct > 100) pct = 100;
  if (pct == _ttPctCu) return;
  _ttPctCu = pct;
  int rong = (TT_W - 4) * pct / 100;
  uint16_t xanh = tft.color565(0, 190, 120);
  // fillRoundRect với bề rộng nhỏ hơn 2*bán kính vẽ ra hình méo -> hẹp thì dùng fillRect
  if (rong >= 12)    tft.fillRoundRect(TT_X + 2, TT_Y + 2, rong, TT_H - 4, 4, xanh);
  else if (rong > 0) tft.fillRect(TT_X + 2, TT_Y + 2, rong, TT_H - 4, xanh);
  if (rong < TT_W - 4)
    tft.fillRect(TT_X + 2 + rong, TT_Y + 2, (TT_W - 4) - rong, TT_H - 4, COL_PANEL);
}

void showIdle() {
  idleActive = true;
  statusUntil = 0;
  lastClockSec = -1; lastClockMin = -1;      // ép vẽ lại giờ + ngày
  netStatusLast = -1;                        // ép vẽ lại chỉ báo mạng

  tft.fillScreen(COL_BG);

  // Khung viền ngoài (cam) như mẫu
  uint16_t frame = tft.color565(255, 140, 0);
  tft.drawRoundRect(3, 3, 314, 234, 10, frame);
  tft.drawRoundRect(4, 4, 312, 232, 10, frame);

  // Tiêu đề
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(frame, COL_BG);
  tft.drawString("MAY CHAM CONG", 160, 28, 4);

  // Panel đồng hồ (nền navy, viền xanh)
  tft.fillRoundRect(24, 60, 272, 84, 8, COL_PANEL);
  tft.drawRoundRect(24, 60, 272, 84, 8, tft.color565(40, 90, 150));

  // Tên cơ sở — hoặc BÁO ĐỎ nếu máy chưa khai đủ cấu hình (đừng để chết im, phải thấy ngay tại quầy)
  if (g_chuaCauHinh) {
    tft.setTextColor(tft.color565(230, 70, 90), COL_BG);
    tft.drawString("CHUA CAU HINH - AP CHAM_CONG", 160, 196, 2);
  } else {
    tft.setTextColor(tft.color565(150, 170, 190), COL_BG);
    tft.drawString(String(STATION_NAME), 160, 196, 4);
  }

  // 3 ô màu trang trí (xanh lá / vàng / đỏ) như mẫu
  tft.fillRoundRect(30,  216, 82, 14, 4, tft.color565(0, 190, 120));
  tft.fillRoundRect(119, 216, 82, 14, 4, tft.color565(245, 190, 0));
  tft.fillRoundRect(208, 216, 82, 14, 4, tft.color565(230, 70, 90));

  updateClock();   // vẽ giờ + ngày lần đầu
}

void showThankYou(String name, String eventTime, uint8_t* jpeg, int jpegLen) {
  (void)jpeg; (void)jpegLen;          // không hiện ảnh trên màn (ảnh vẫn được gửi lên Google Sheet)
  idleActive = false;                 // dừng cập nhật đồng hồ khi hiện màn cảm ơn
  tft.fillScreen(TFT_BLACK); veKhung();
  tft.setTextDatum(MC_DATUM);

  // Lời cảm ơn (chữ lớn, canh giữa trên)
  tft.setTextColor(TFT_GREEN, TFT_BLACK);
  tft.drawString("XIN CAM ON!", 160, 70, 4);

  // Tên nhân viên (chữ lớn nhất, giữa màn)
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.drawString(name, 160, 130, 4);

  // Giờ chấm công
  tft.setTextColor(TFT_YELLOW, TFT_BLACK);
  String t = (eventTime.length() >= 19) ? eventTime.substring(11, 19) : eventTime;
  tft.drawString(t, 160, 185, 4);

  statusUntil = millis() + STATUS_HOLD_MS;
}

// ---------- Tiện ích ----------
String getMD5(String payload) {
  MD5Builder md5; md5.begin(); md5.add(payload); md5.calculate();
  return md5.toString();
}

String extractParam(String header, String param) {
  int start = header.indexOf(param + "=");
  if (start == -1) return "";
  start += param.length() + 1;
  bool hasQuote = (header.charAt(start) == '\"');
  if (hasQuote) start++;
  int end = header.indexOf(hasQuote ? "\"" : ",", start);
  if (end == -1) end = header.length();
  return header.substring(start, end);
}

int timeToSec(const String& t) {
  int sp = t.indexOf(' ');
  if (sp < 0 || (int)t.length() < sp + 9) return -1;
  int h = t.substring(sp + 1, sp + 3).toInt();
  int m = t.substring(sp + 4, sp + 6).toInt();
  int s = t.substring(sp + 7, sp + 9).toInt();
  return h * 3600 + m * 60 + s;
}

// ---------- POST tới máy chấm công (Digest) ----------
String hikPost(String payload) {
  HTTPClient http;
  String url = "http://" + String(hik_ip) + "/ISAPI/AccessControl/AcsEvent?format=json";
  String uri = "/ISAPI/AccessControl/AcsEvent?format=json";

  http.begin(url);
  http.setTimeout(8000);
  const char* headerKeys[] = {"WWW-Authenticate"};
  http.collectHeaders(headerKeys, 1);
  http.addHeader("Content-Type", "application/json");
  int code = http.POST(payload);

  if (code == 401) {
    String authReq = http.header("WWW-Authenticate");
    http.end();
    String realm  = extractParam(authReq, "realm");
    String nonce  = extractParam(authReq, "nonce");
    String qop    = extractParam(authReq, "qop");
    String opaque = extractParam(authReq, "opaque");
    String cnonce = "0a4f113b";
    String nc     = "00000001";
    String HA1 = getMD5(String(hik_user) + ":" + realm + ":" + String(hik_pass));
    String HA2 = getMD5("POST:" + uri);
    String response = getMD5(HA1 + ":" + nonce + ":" + nc + ":" + cnonce + ":" + qop + ":" + HA2);
    String authHeader = "Digest username=\"" + String(hik_user) + "\", realm=\"" + realm +
                        "\", nonce=\"" + nonce + "\", uri=\"" + uri + "\", qop=" + qop +
                        ", nc=" + nc + ", cnonce=\"" + cnonce + "\", response=\"" + response +
                        "\", opaque=\"" + opaque + "\"";
    http.begin(url);
    http.setTimeout(8000);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", authHeader);
    code = http.POST(payload);
  }

  String body = "";
  if (code == 200) body = http.getString();
  else { Serial.print("[MÁY] Lỗi HTTP: "); Serial.println(code); }
  http.end();
  return body;
}

// Encode base64 -> trả về ĐỘ DÀI, *out = buffer malloc (caller phải free). Lỗi -> trả 0, *out=NULL.
// Dùng buffer thay vì trả String để TRÁNH nhân đôi bộ nhớ: String((char*)out) cần thêm một vùng
// liền mạch cỡ ~ảnh; trên heap phân mảnh nó lặng lẽ thất bại -> ảnh 0 byte.
int base64Encode(const uint8_t* data, size_t len, char** out) {
  *out = NULL;
  size_t outCap = 4 * ((len + 2) / 3) + 1;   // TỰ TÍNH kích thước base64 (+1 null)
  char* buf = (char*)malloc(outCap);
  if (!buf) { Serial.println("   [ANH] Het RAM khi encode."); return 0; }
  size_t written = 0;
  int rc = mbedtls_base64_encode((uint8_t*)buf, outCap, &written, data, len);
  if (rc != 0) { Serial.printf("   [ANH] base64 loi rc=%d\n", rc); free(buf); return 0; }
  buf[written] = 0;
  *out = buf;
  return (int)written;
}

String getPathFromUrl(const String& url) {
  int p = url.indexOf("://");
  if (p < 0) return url;
  int s = url.indexOf('/', p + 3);
  return (s < 0) ? "/" : url.substring(s);
}

// ---------- Tải ảnh JPEG THÔ từ máy -> trả độ dài, *out = buffer (caller free) ----------
int fetchImageRaw(String picUrl, uint8_t** out) {
  *out = NULL;
  String uri = getPathFromUrl(picUrl);
  HTTPClient http;
  http.begin(picUrl);
  http.setTimeout(10000);
  const char* hk[] = {"WWW-Authenticate"};
  http.collectHeaders(hk, 1);
  int code = http.GET();

  if (code == 401) {
    String authReq = http.header("WWW-Authenticate");
    http.end();
    String realm  = extractParam(authReq, "realm");
    String nonce  = extractParam(authReq, "nonce");
    String qop    = extractParam(authReq, "qop");
    String opaque = extractParam(authReq, "opaque");
    String cnonce = "0a4f113b";
    String nc     = "00000001";
    String HA1 = getMD5(String(hik_user) + ":" + realm + ":" + String(hik_pass));
    String HA2 = getMD5("GET:" + uri);
    String response = getMD5(HA1 + ":" + nonce + ":" + nc + ":" + cnonce + ":" + qop + ":" + HA2);
    String authHeader = "Digest username=\"" + String(hik_user) + "\", realm=\"" + realm +
                        "\", nonce=\"" + nonce + "\", uri=\"" + uri + "\", qop=" + qop +
                        ", nc=" + nc + ", cnonce=\"" + cnonce + "\", response=\"" + response +
                        "\", opaque=\"" + opaque + "\"";
    http.begin(picUrl);
    http.setTimeout(10000);
    http.addHeader("Authorization", authHeader);
    code = http.GET();
  }

  if (code != 200) {
    Serial.printf("   [ẢNH] Không tải được ảnh, HTTP %d\n", code);
    http.end();
    return 0;
  }

  int len = http.getSize();
  const int MAXIMG = 55000;
  uint8_t* buf = (uint8_t*)malloc(MAXIMG);
  if (!buf) { Serial.println("   [ẢNH] Hết RAM cấp buffer ảnh."); http.end(); return 0; }

  WiFiClient* stream = http.getStreamPtr();
  int idx = 0;
  unsigned long t0 = millis();
  while (http.connected() && idx < MAXIMG) {
    size_t avail = stream->available();
    if (avail) {
      int toRead = MAXIMG - idx;
      if ((int)avail < toRead) toRead = avail;
      idx += stream->readBytes(buf + idx, toRead);
      t0 = millis();
      if (len > 0 && idx >= len) break;
    } else {
      if (millis() - t0 > 3000) break;
      delay(1);
    }
  }
  http.end();
  Serial.printf("   [ẢNH] Đã tải %d byte (free heap=%d)\n", idx, ESP.getFreeHeap());

  if (idx <= 0) { free(buf); return 0; }
  *out = buf;
  return idx;
}

// ---------- Đẩy Google Sheets ----------
bool net4gHttpPost(const String& url, const String& body);   // forward decl (định nghĩa ở khối 4G bên dưới)
// LƯU Ý: hàm này NHẬN QUYỀN SỞ HỮU imageB64 và sẽ free nó (sau khi copy vào body).
// Caller KHÔNG được free lại.
bool pushEventToGoogle(String empNo, String name, String eventTime, char* imageB64, int imageB64Len) {
  String body;
  body.reserve(imageB64Len + 400);
  body  = "{\"macAddress\":\""  + WiFi.macAddress() + "\",";
  body += "\"hikSerial\":\""    + HIK_SERIAL + "\",";     // server ghép mã này -> cửa hàng (khoá chính)
  body += "\"hikModel\":\""     + HIK_MODEL  + "\",";
  body += "\"stationName\":\""  + String(STATION_NAME) + "\",";
  body += "\"employeeNo\":\""   + empNo + "\",";
  body += "\"name\":\""         + name + "\",";
  body += "\"time\":\""         + eventTime + "\",";
  body += "\"image\":\"";
  if (imageB64Len > 0) body += imageB64;   // nối trực tiếp C-string, không tạo thêm String base64
  body += "\"}";
  // Đã copy base64 vào body -> GIẢI PHÓNG buffer base64 NGAY, TRƯỚC khi mở TLS.
  // TLS handshake cần ~40KB RAM liền mạch; còn giữ buffer base64 sẽ thiếu -> lỗi HTTP -1.
  if (imageB64) { free(imageB64); imageB64 = NULL; }

  // 4G: đẩy qua LỆNH AT HTTP của module (không PPP). GỬI BẢN GỌN (KHÔNG kèm ảnh) để né giới hạn AT+HTTPDATA.
  // Chấm công chỉ cần Mã NV + tên + giờ; ảnh mặt đã enroll sẵn trong máy nên không cần đẩy qua 4G.
  if (USE_4G) {
    String slim = String("{\"macAddress\":\"") + WiFi.macAddress()
                + "\",\"hikSerial\":\"" + HIK_SERIAL + "\",\"hikModel\":\"" + HIK_MODEL
                + "\",\"stationName\":\"" + String(STATION_NAME)
                + "\",\"employeeNo\":\"" + empNo + "\",\"name\":\"" + name + "\",\"time\":\"" + eventTime + "\",\"image\":\"\"}";
    for (int a = 1; a <= 3; a++) {
      Serial.printf("   -> [4G HTTP] gửi Google (lần %d, %d byte, heap %d)...\n", a, slim.length(), ESP.getFreeHeap());
      if (net4gHttpPost(google_script_url, slim)) { Serial.println("   ✔️ ĐỒNG BỘ (4G HTTP, không kèm ảnh)!"); return true; }
      delay(1500);
    }
    Serial.println("   ⚠️ 4G HTTP thất bại sau 3 lần"); return false;
  }

  for (int attempt = 1; attempt <= 3; attempt++) {
    WiFiClientSecure client;
    client.setInsecure();
    HTTPClient http;
    http.begin(client, google_script_url);
    http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
    const char* loc[] = {"Location"};
    http.collectHeaders(loc, 1);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(30000);

    Serial.printf("   -> Gửi Google (lần %d, ảnh %d byte, heap %d)...\n",
                  attempt, imageB64Len, ESP.getFreeHeap());
    int code = http.POST(body);

    if (code == 301 || code == 302 || code == 307) {
      String location = http.header("Location");
      http.end();
      if (location.length() > 0) {
        http.begin(client, location);
        http.setTimeout(30000);
        code = http.GET();
      }
    }

    String resp = http.getString();
    Serial.print("   -> Mã HTTP: "); Serial.print(code);
    Serial.print(" | Phản hồi: "); Serial.println(resp);
    http.end();

    if (code == 200 && resp.indexOf("SUCCESS") >= 0) {
      Serial.println("   ✔️ ĐỒNG BỘ THÀNH CÔNG!");
      return true;
    }
    Serial.printf("   ⚠️ Lỗi (mã %d), thử lại %d/3...\n", code, attempt);
    delay(800);
  }
  Serial.println("   ❌ Gửi Google thất bại sau 3 lần.");
  return false;
}

// =======================================================================
// ============  ĐỒNG BỘ NHÂN VIÊN: WEB APP -> ESP32 -> HIKVISION  ========
// =======================================================================

// ---- Dựng header Digest từ các tham số đã tách ----
String digestHeaderFrom(const String& realm, const String& nonce, const String& qop,
                        const String& opaque, const String& method, const String& uri) {
  String cnonce = "0a4f113b";
  String nc     = "00000001";
  String HA1 = getMD5(String(hik_user) + ":" + realm + ":" + String(hik_pass));
  String HA2 = getMD5(method + ":" + uri);
  String response = getMD5(HA1 + ":" + nonce + ":" + nc + ":" + cnonce + ":" + qop + ":" + HA2);
  String h = "Digest username=\"" + String(hik_user) + "\", realm=\"" + realm +
             "\", nonce=\"" + nonce + "\", uri=\"" + uri + "\", qop=" + qop +
             ", nc=" + nc + ", cnonce=\"" + cnonce + "\", response=\"" + response + "\"";
  if (opaque.length()) h += ", opaque=\"" + opaque + "\"";
  return h;
}

String buildDigestAuth(const String& method, const String& uri, const String& authReq) {
  return digestHeaderFrom(extractParam(authReq, "realm"), extractParam(authReq, "nonce"),
                          extractParam(authReq, "qop"),   extractParam(authReq, "opaque"),
                          method, uri);
}

// Lấy 1 "challenge" Digest (nonce...) từ 1 endpoint nhẹ — dùng cho POST multipart bằng WiFiClient thô.
bool getDigestChallenge(String& realm, String& nonce, String& qop, String& opaque) {
  HTTPClient http;
  String url = "http://" + String(hik_ip) + "/ISAPI/System/deviceInfo?format=json";
  http.begin(url); http.setTimeout(6000);
  const char* hk[] = {"WWW-Authenticate"}; http.collectHeaders(hk, 1);
  int code = http.GET();
  bool ok = false;
  if (code == 401) {
    String a = http.header("WWW-Authenticate");
    realm  = extractParam(a, "realm");  nonce  = extractParam(a, "nonce");
    qop    = extractParam(a, "qop");    opaque = extractParam(a, "opaque");
    ok = nonce.length() > 0;
  }
  http.end();
  return ok;
}

int hikSend_(HTTPClient& http, const String& method, const String& payload) {
  if (method == "GET") return http.GET();
  return http.sendRequest(method.c_str(), (uint8_t*)payload.c_str(), payload.length());
}

// GET/POST/PUT JSON tới máy (Digest). Trả body; *outCode = mã HTTP.
String hikRequest(const String& method, const String& uri, const String& payload, int* outCode) {
  HTTPClient http;
  String url = "http://" + String(hik_ip) + uri;
  http.begin(url); http.setTimeout(10000);
  const char* hk[] = {"WWW-Authenticate"}; http.collectHeaders(hk, 1);
  if (method != "GET") http.addHeader("Content-Type", "application/json");
  int code = hikSend_(http, method, payload);
  if (code == 401) {
    String authReq = http.header("WWW-Authenticate");
    http.end();
    String auth = buildDigestAuth(method, uri, authReq);
    http.begin(url); http.setTimeout(10000);
    if (method != "GET") http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", auth);
    code = hikSend_(http, method, payload);
  }
  String body = (code > 0) ? http.getString() : "";
  http.end();
  if (outCode) *outCode = code;
  return body;
}

bool hikOk(const String& r) {
  String s = r;
  s.replace(" ",""); s.replace("\t",""); s.replace("\r",""); s.replace("\n","");   // bỏ MỌI ký tự trắng
  return s.indexOf("\"statusString\":\"OK\"") >= 0 ||
         s.indexOf("\"subStatusCode\":\"ok\"") >= 0 ||
         s.indexOf("\"statusCode\":1,")    >= 0 ||     // dự phòng
         s.indexOf("\"statusCode\":1}")    >= 0;
}

String shortResp(const String& r, int code) {
  String s = r; s.replace("\n", ""); s.replace("\r", "");
  if (s.length() > 90) s = s.substring(0, 90);
  return "http" + String(code) + " " + s;
}

/* ---- MÃ THIẾT BỊ: đọc serial + model của đầu đọc Hikvision ----
   Nhớ vào Preferences: đầu đọc mất điện / chưa kịp lên mạng lúc khởi động thì vẫn còn mã để hỏi
   server. Không có mã cũng không sao — server còn khoá dự phòng là MAC bo. */
void docThongTinDauDoc() {
  HIK_SERIAL = prefs.getString("hikSn", "");
  HIK_MODEL  = prefs.getString("hikModel", "");
  int code = 0;
  String r = hikRequest("GET", "/ISAPI/System/deviceInfo?format=json", "", &code);
  if (code != 200 || r.length() == 0) {
    Serial.printf("[MÃ MÁY] Chưa đọc được đầu đọc (http%d) -> dùng mã đã nhớ: '%s'\n", code, HIK_SERIAL.c_str());
    return;
  }
  StaticJsonDocument<256> filter;
  filter["DeviceInfo"]["serialNumber"] = true;
  filter["DeviceInfo"]["model"]        = true;
  DynamicJsonDocument d(512);
  if (deserializeJson(d, r, DeserializationOption::Filter(filter))) { Serial.println("[MÃ MÁY] parse deviceInfo lỗi"); return; }
  String sn = String((const char*)(d["DeviceInfo"]["serialNumber"] | ""));
  String md = String((const char*)(d["DeviceInfo"]["model"] | ""));
  sn.trim(); md.trim();
  if (sn.length()) { if (sn != HIK_SERIAL) prefs.putString("hikSn", sn);    HIK_SERIAL = sn; }
  if (md.length()) { if (md != HIK_MODEL)  prefs.putString("hikModel", md); HIK_MODEL  = md; }
  Serial.println("[MÃ MÁY] serial đầu đọc: " + HIK_SERIAL + (HIK_MODEL.length() ? ("  (" + HIK_MODEL + ")") : ""));
}

/* ---- Hỏi server "tôi ở cửa hàng nào?" ----
   Cần TÊN cửa hàng vì mọi đường Firebase đều mang tên đó (/queue/<trạm>, /hb/<trạm>,
   /roster/<trạm>, /photo/<trạm>) — không thể để server tự suy hết.
   Mất mạng / server chưa gán -> GIỮ NGUYÊN tên đang nhớ, KHÔNG xoá trắng. */
bool hoiCuaHang() {
  if (!netUp()) return false;
  String q = "action=whoami&serial=" + urlEncodeMin(HIK_SERIAL)
           + "&mac="     + urlEncodeMin(WiFi.macAddress())
           + "&station=" + urlEncodeMin(STATION_NAME)
           + "&model="   + urlEncodeMin(HIK_MODEL);
  String r = empGet(q);
  if (r.length() == 0) { Serial.println("[CƠ SỞ] Không hỏi được server -> giữ '" + STATION_NAME + "'"); return false; }
  StaticJsonDocument<384> d;
  if (deserializeJson(d, r)) { Serial.println("[CƠ SỞ] parse whoami lỗi -> giữ '" + STATION_NAME + "'"); return false; }
  if (d["choGan"] | false) {
    STATION_TU_SERVER = false;
    Serial.println("[CƠ SỞ] ⚠️ MÁY CHƯA ĐƯỢC GÁN CỬA HÀNG. Vào web app > tab 'Máy chấm công' rồi gán cửa hàng cho máy này.");
    Serial.println("        serial=" + HIK_SERIAL + "  mac=" + WiFi.macAddress());
    Serial.println("        Chấm công vẫn được giữ ở server (sheet ChamCongChoGan), gán xong sẽ tự chuyển về.");
    return false;
  }
  String st = String((const char*)(d["station"] | "")); st.trim();
  if (!(d["ok"] | false) || st.length() == 0) { Serial.println("[CƠ SỞ] server không trả tên -> giữ '" + STATION_NAME + "'"); return false; }
  STATION_TU_SERVER = true;
  if (st != STATION_NAME) {
    Serial.println("[CƠ SỞ] Server gán máy này vào '" + st + "' (trước là '" + STATION_NAME + "') -> ghi nhớ");
    STATION_NAME = st;
    prefs.putString("station", st);
  }
  return true;
}

// ---- Gọi web app nhân viên (HTTPS GET, có follow redirect + token) ----
String empGet(const String& query) {
  if (USE_4G) {                                             // 4G: GET qua AT-HTTP + follow 302
    String url = String(emp_script_url) + "?" + query + "&token=" + EMP_TOKEN;
    int dl = 0, st = net4gGetOpen(url, &dl);
    if (st != 200) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      Serial.printf("[NV] empGet '%s' 4G status=%d\n", query.c_str(), st); return ""; }
    int n = net4gReadStart(dl); String body = "";
    if (n > 0) { body.reserve(n + 4); int got = 0; unsigned long t0 = millis();
      while (got < n && millis()-t0 < 10000) { while (Serial2.available() && got < n){ body += (char)Serial2.read(); got++; t0=millis(); } delay(1); } }
    atWait("OK",2000); Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    Serial.printf("[NV] empGet '%s' 4G len=%d\n", query.c_str(), (int)body.length());
    return body;
  }
  for (int attempt = 1; attempt <= 2; attempt++) {
    WiFiClientSecure client; client.setInsecure();
    HTTPClient http;
    String url = String(emp_script_url) + "?" + query + "&token=" + EMP_TOKEN;
    http.begin(client, url);
    http.setFollowRedirects(HTTPC_FORCE_FOLLOW_REDIRECTS);
    http.setTimeout(20000);
    int code = http.GET();
    String body = (code == 200) ? http.getString() : "";
    http.end();
    if (code == 200) return body;
    Serial.printf("[NV] empGet '%s' HTTP %d (lần %d)\n", query.c_str(), code, attempt);
    delay(500);
  }
  return "";
}

String urlEncodeMin(const String& s) {
  String o = "";
  for (size_t i = 0; i < s.length(); i++) {
    char c = s[i];
    if ((c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') ||
        c == '-' || c == '_' || c == '.') o += c;
    else { char b[4]; sprintf(b, "%%%02X", (uint8_t)c); o += b; }
  }
  return o;
}

int b64val(int c) {
  if (c >= 'A' && c <= 'Z') return c - 'A';
  if (c >= 'a' && c <= 'z') return c - 'a' + 26;
  if (c >= '0' && c <= '9') return c - '0' + 52;
  if (c == '+' || c == '-') return 62;   // '-' = base64url (Firebase lưu base64url)
  if (c == '/' || c == '_') return 63;   // '_' = base64url
  return -1;
}

// Tải ảnh base64 từ web app rồi GIẢI MÃ TRỰC TIẾP (streaming) ra JPEG trong buffer malloc.
// Không giữ chuỗi base64 to -> tiết kiệm RAM. Trả độ dài JPEG, *out = buffer (caller free).
// Lỗi: -1 hết RAM, -2 HTTP != 200, -3 dữ liệu không phải base64 (vd body "ERR:...").
int fetchPhotoDecoded(const String& opId, uint8_t** out) {
  *out = NULL;
  if (USE_4G) {                                             // 4G: GET ảnh từ Firebase /photo, đọc THEO CHUNK 1KB (module giới hạn mỗi lần đọc) + giải mã dồn
    String url = String(FB_HOST) + "/photo/" + STATION_NAME + "/" + opId + ".json" + fbAuthParam();
    int dl = 0, st = net4gGetOpen(url, &dl);
    if (st != 200) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      Serial.printf("[NV] photo 4G status=%d\n", st); return -2; }
    if (dl <= 10) {                                          // "null"/rỗng = không có ảnh -> báo 0 (không lỗi), thêm NV khỏi mặt
      Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      Serial.printf("[NV] photo 4G: không có ảnh (dl=%d)\n", dl); return 0;
    }
    uint8_t* buf = (uint8_t*)malloc(MAX_FACE_BYTES);
    if (!buf) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); Serial.println("[NV] Hết RAM cấp buffer ảnh."); return -1; }
    uint8_t chunk[1024];
    int outIdx = 0, quad[4], qn = 0, start = 0; bool err = false, done = false;
    while (start < dl && !err && !done) {
      int want = (dl - start > 1024) ? 1024 : (dl - start);
      int got = net4gReadChunk(start, want, chunk);
      if (got <= 0) break;                                   // hết/lỗi
      start += got;
      for (int i = 0; i < got && !err && !done; i++) {
        char c = (char)chunk[i];
        if (c=='\r'||c=='\n'||c==' '||c=='\t'||c=='"') continue;   // bỏ khoảng trắng + dấu " bọc chuỗi JSON
        if (c=='=') { done = true; break; }
        int v = b64val(c); if (v < 0) { err = true; break; }
        quad[qn++] = v;
        if (qn == 4) {
          if (outIdx + 3 <= MAX_FACE_BYTES) {
            buf[outIdx++] = (quad[0]<<2)|(quad[1]>>4);
            buf[outIdx++] = ((quad[1]&0xF)<<4)|(quad[2]>>2);
            buf[outIdx++] = ((quad[2]&0x3)<<6)|quad[3];
          } else err = true;
          qn = 0;
        }
      }
    }
    if (!err) {
      if (qn == 2)      { buf[outIdx++] = (quad[0]<<2)|(quad[1]>>4); }
      else if (qn == 3) { buf[outIdx++] = (quad[0]<<2)|(quad[1]>>4);
                          buf[outIdx++] = ((quad[1]&0xF)<<4)|(quad[2]>>2); }
    }
    Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    Serial.printf("[NV] Ảnh giải mã %d byte (4G chunk, dl=%d, heap=%d)\n", outIdx, dl, ESP.getFreeHeap());
    if (err || outIdx <= 0) { free(buf); return -3; }
    *out = buf; return outIdx;
  }
  uint8_t* buf = (uint8_t*)malloc(MAX_FACE_BYTES);
  if (!buf) { Serial.println("[NV] Hết RAM cấp buffer ảnh."); return -1; }
  int outIdx = 0;

  WiFiClientSecure client; client.setInsecure();
  HTTPClient http;
  String url = String(emp_script_url) + "?action=photo&opId=" + opId + "&token=" + EMP_TOKEN;
  http.begin(client, url);
  http.setFollowRedirects(HTTPC_FORCE_FOLLOW_REDIRECTS);
  http.setTimeout(20000);
  int code = http.GET();
  if (code != 200) { Serial.printf("[NV] photo HTTP %d\n", code); http.end(); free(buf); return -2; }

  WiFiClient* stream = http.getStreamPtr();
  int quad[4], qn = 0; bool err = false, done = false;
  uint8_t tmp[256];
  unsigned long t0 = millis();
  while (true) {
    int avail = stream->available();
    if (avail <= 0) {
      if (!http.connected()) break;
      if (millis() - t0 > 4000) break;
      delay(1); continue;
    }
    int r = stream->readBytes(tmp, avail > (int)sizeof(tmp) ? (int)sizeof(tmp) : avail);
    t0 = millis();
    for (int i = 0; i < r && !err && !done; i++) {
      char c = tmp[i];
      if (c == '\r' || c == '\n' || c == ' ' || c == '\t') continue;
      if (c == '=') { done = true; break; }
      int v = b64val(c);
      if (v < 0) { err = true; break; }     // ký tự lạ -> đây là body lỗi, không phải base64
      quad[qn++] = v;
      if (qn == 4) {
        if (outIdx + 3 <= MAX_FACE_BYTES) {
          buf[outIdx++] = (quad[0] << 2) | (quad[1] >> 4);
          buf[outIdx++] = ((quad[1] & 0xF) << 4) | (quad[2] >> 2);
          buf[outIdx++] = ((quad[2] & 0x3) << 6) | quad[3];
        } else err = true;
        qn = 0;
      }
    }
    if (err || done) break;
  }
  http.end();

  if (!err) {
    if (qn == 2)      { buf[outIdx++] = (quad[0] << 2) | (quad[1] >> 4); }
    else if (qn == 3) { buf[outIdx++] = (quad[0] << 2) | (quad[1] >> 4);
                        buf[outIdx++] = ((quad[1] & 0xF) << 4) | (quad[2] >> 2); }
  }
  Serial.printf("[NV] Ảnh giải mã %d byte (heap=%d)\n", outIdx, ESP.getFreeHeap());
  if (err || outIdx <= 0) { free(buf); return -3; }
  *out = buf; return outIdx;
}

// Xóa ảnh mặt cũ của 1 FPID (best-effort, để ghi đè sạch)
void faceDelete(const String& emp) {
  String uri = "/ISAPI/Intelligent/FDLib/FDSearch/Delete?format=json&FDID=" + String(FDID_STR) +
               "&faceLibType=" + String(FACE_LIB_TYPE);
  String body = "{\"FPID\":[{\"value\":\"" + emp + "\"}]}";
  int code; hikRequest("PUT", uri, body, &code);
  Serial.printf("[NV] faceDelete %s -> http%d\n", emp.c_str(), code);
}

// POST multipart ảnh khuôn mặt bằng WiFiClient thô (ghi JPEG theo chunk -> chỉ giữ 1 buffer).
// Gửi 1 LẦN multipart với auth cho sẵn. Trả status HTTP (-1 nếu không kết nối được), ghi resp.
int sendFaceMultipart_(const String& uri, const String& auth, const String& fpid,
                       uint8_t* jpeg, int jpegLen, const char* partName, String& resp) {
  resp = "";
  String boundary = "----cydhik" + String((uint32_t)millis(), HEX);
  String jsonPart = "{\"faceLibType\":\"" + String(FACE_LIB_TYPE) + "\",\"FDID\":\"" +
                    String(FDID_STR) + "\",\"FPID\":\"" + fpid + "\"}";
  String pre = "--" + boundary + "\r\n"
    "Content-Disposition: form-data; name=\"FaceDataRecord\";\r\n"
    "Content-Type: application/json\r\n"
    "Content-Length: " + String(jsonPart.length()) + "\r\n\r\n" + jsonPart + "\r\n"
    "--" + boundary + "\r\n"
    "Content-Disposition: form-data; name=\"" + String(partName) + "\";\r\n"
    "Content-Type: image/jpeg\r\n"
    "Content-Length: " + String(jpegLen) + "\r\n\r\n";
  String post = "\r\n--" + boundary + "--\r\n";
  long contentLen = (long)pre.length() + jpegLen + (long)post.length();

  WiFiClient client;
  client.setTimeout(12000);
  if (!client.connect(hik_ip, 80)) return -1;
  client.print("POST " + uri + " HTTP/1.1\r\n");
  client.print("Host: " + String(hik_ip) + "\r\n");
  if (auth.length()) client.print("Authorization: " + auth + "\r\n");
  client.print("Content-Type: multipart/form-data; boundary=" + boundary + "\r\n");
  client.print("Content-Length: " + String(contentLen) + "\r\n");
  client.print("Connection: close\r\n\r\n");
  client.print(pre);
  int sent = 0;
  while (sent < jpegLen) {
    int chunk = (jpegLen - sent) > 1024 ? 1024 : (jpegLen - sent);
    int w = client.write(jpeg + sent, chunk);
    if (w <= 0) { if (!client.connected()) break; delay(1); continue; }
    sent += w;
  }
  client.print(post);

  int status = 0; unsigned long t0 = millis();
  while (client.connected() || client.available()) {
    if (client.available()) { resp += (char)client.read(); t0 = millis(); if (resp.length() > 2000) break; }
    else { if (millis() - t0 > 5000) break; delay(1); }
  }
  client.stop();
  int sp = resp.indexOf(' ');
  if (sp >= 0) status = resp.substring(sp + 1, sp + 4).toInt();
  Serial.printf("[NV] face POST(%s) http%d, gửi %d/%d byte\n", partName, status, sent, jpegLen);
  return status;
}

// POST ảnh khuôn mặt (multipart). Lấy nonce Digest từ endpoint nhẹ; nếu vẫn 401 thì
// lấy challenge mới từ chính phản hồi rồi gửi lại 1 lần.
bool hikFacePost(const String& fpid, uint8_t* jpeg, int jpegLen, const char* partName, String& msg) {
  String uri = "/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json";
  String realm, nonce, qop, opaque, auth = "";
  if (getDigestChallenge(realm, nonce, qop, opaque))
    auth = digestHeaderFrom(realm, nonce, qop, opaque, "POST", uri);

  String resp;
  int status = sendFaceMultipart_(uri, auth, fpid, jpeg, jpegLen, partName, resp);

  if (status == 401) {                       // nonce cũ/không hợp lệ -> lấy lại từ phản hồi 401
    int p = resp.indexOf("WWW-Authenticate:");
    if (p >= 0) {
      int e = resp.indexOf("\r\n", p);
      String a = resp.substring(p + 17, e < 0 ? resp.length() : e);
      auth = buildDigestAuth("POST", uri, a);
      status = sendFaceMultipart_(uri, auth, fpid, jpeg, jpegLen, partName, resp);
    }
  }

  bool ok = (status == 200) && hikOk(resp);   // hikOk đã chịu được dấu cách JSON
  if (!ok) {
    int b = resp.indexOf("\r\n\r\n");
    String body = (b >= 0) ? resp.substring(b + 4) : resp;
    body.replace("\r", ""); body.replace("\n", "");
    if (body.length() > 100) body = body.substring(0, 100);
    msg = "face http" + String(status) + " " + body;
  } else msg = "face ok";
  return ok;
}

// Đẩy ảnh: dọn ảnh cũ rồi thử part name "FaceImage", nếu firmware khác thì thử "img".
bool faceUpload(const String& emp, uint8_t* jpeg, int jpegLen, String& msg) {
  faceDelete(emp);
  if (hikFacePost(emp, jpeg, jpegLen, "FaceImage", msg)) return true;
  Serial.println("[NV] Thử lại part name 'img'...");
  String msg2;
  if (hikFacePost(emp, jpeg, jpegLen, "img", msg2)) { msg = "face ok (img)"; return true; }
  msg = msg + " | " + msg2;
  return false;
}

// Tạo/sửa user. add: Record (trùng -> Modify). edit: Modify (chưa có -> Record).
bool upsertUser(const String& emp, const String& name, const String& pin,
                const String& action, const String& gender, String& msg) {
  String valid = "\"Valid\":{\"enable\":true,\"beginTime\":\"2020-01-01T00:00:00\",\"endTime\":\"2037-12-31T23:59:59\"}";
  String pw  = pin.length() ? (",\"password\":\"" + pin + "\"") : "";
  String gen = (gender == "male" || gender == "female") ? (",\"gender\":\"" + gender + "\"") : "";  // máy chỉ nhận male/female
  String body = "{\"UserInfo\":{\"employeeNo\":\"" + emp + "\",\"name\":\"" + name +
                "\",\"userType\":\"normal\",\"doorRight\":\"1\"," + valid + pw + gen +   // doorRight: quyền qua cửa 1 (thiếu -> máy báo "không cho phép" dù đã nhận mặt)
                ",\"RightPlan\":[{\"doorNo\":1,\"planTemplateNo\":\"1\"}]}}";
  int code; String r;
  if (action == "edit") {
    r = hikRequest("PUT", "/ISAPI/AccessControl/UserInfo/Modify?format=json", body, &code);
    if (hikOk(r)) { msg = "modified"; return true; }
    // chưa tồn tại -> rơi xuống add
  }
  r = hikRequest("POST", "/ISAPI/AccessControl/UserInfo/Record?format=json", body, &code);
  Serial.printf("[NV][DBG] Record http%d hikOk=%d resp=%s\n", code, hikOk(r)?1:0, shortResp(r, code).c_str());  // debug: xem hikOk có nhận OK không
  if (hikOk(r)) { msg = "created"; return true; }
  if (r.indexOf("AlreadyExist") >= 0 || r.indexOf("already") >= 0) {
    r = hikRequest("PUT", "/ISAPI/AccessControl/UserInfo/Modify?format=json", body, &code);
    if (hikOk(r)) { msg = "modified"; return true; }
  }
  msg = "user " + shortResp(r, code);
  return false;
}

void pollDeleteProcess() {
  for (int i = 0; i < 10; i++) {
    int code; String r = hikRequest("GET", "/ISAPI/AccessControl/UserInfoDetail/DeleteProcess?format=json", "", &code);
    if (code != 200) break;
    if (r.indexOf("success") >= 0 || r.indexOf("100") >= 0) break;
    delay(300);
  }
}

// Màn báo đang đồng bộ NV
void showSync(const String& action, const String& name) {
  idleActive = false;                 // dừng cập nhật đồng hồ khi hiện màn đồng bộ
  tft.fillScreen(TFT_BLACK); veKhung();
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_ORANGE, TFT_BLACK);
  tft.drawString("DONG BO NHAN VIEN", 160, 90, 4);
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  String a = action; a.toUpperCase();
  tft.drawString(a + ": " + name, 160, 135, 2);
}

// Thực thi 1 lệnh trên máy chấm công theo action
bool processOp(const String& action, const String& emp, const String& name,
               const String& pin, const String& gender, bool hasPhoto, const String& opId, String& msg) {
  int code;
  if (action == "delete") {
    String body = "{\"UserInfoDetail\":{\"mode\":\"byEmployeeNo\",\"EmployeeNoList\":[{\"employeeNo\":\"" + emp + "\"}]}}";
    String r = hikRequest("PUT", "/ISAPI/AccessControl/UserInfoDetail/Delete?format=json", body, &code);
    if (hikOk(r) || r.indexOf("NotExist") >= 0) { pollDeleteProcess(); msg = "deleted"; return true; }
    msg = "del " + shortResp(r, code); return false;
  }
  // add / edit
  if (!upsertUser(emp, name, pin, action, gender, msg)) return false;
  if (!hasPhoto) return true;
  uint8_t* jpeg = NULL;
  int jlen = fetchPhotoDecoded(opId, &jpeg);
  if (jlen == 0) { msg = "user ok (khong co anh)"; return true; }        // không có ảnh -> vẫn xong, khỏi kẹt
  if (jlen < 0)  { msg = "photo fetch fail(" + String(jlen) + ")"; return false; }
  bool faceOk = faceUpload(emp, jpeg, jlen, msg);
  free(jpeg);
  return faceOk;
}

// escape tối thiểu cho JSON (tên NV): \ và "
String jsonEscMin_(const String& s){ String o=s; o.replace("\\","\\\\"); o.replace("\"","\\\""); return o; }

// lấy số nguyên của "key":<số> trong chuỗi JSON thô (-1 nếu không thấy)
int jsonInt_(const String& s, const String& key){
  int p = s.indexOf("\"" + key + "\""); if (p < 0) return -1;
  int c = s.indexOf(':', p); if (c < 0) return -1;
  int i = c + 1; while (i < (int)s.length() && (s[i]==' '||s[i]=='\t')) i++;
  int j = i; while (j < (int)s.length() && ((s[j]>='0'&&s[j]<='9')||s[j]=='-')) j++;
  return (j > i) ? s.substring(i, j).toInt() : -1;
}

// QUÉT: đọc danh sách NV (UserInfo/Search) + tập FPID có ảnh (FDSearch) trên máy
// -> ghi {mã:{n:tên, f:0/1}} lên Firebase /roster/<STATION>
void hikScanRoster(){
  Serial.println("[SCAN] Đọc danh sách + ảnh từ máy Hikvision...");
  // 1) FDSearch -> tập FPID (mã NV) đã có khuôn mặt. Quét THẲNG chuỗi tìm "FPID" (không phụ thuộc cấu trúc JSON).
  String faceSet = ",";
  {
    int pos = 0, total = -1; const int P = 20;
    for (int g = 0; g < 200; g++) {
      int code;
      String body = "{\"searchID\":\"1\",\"searchResultPosition\":" + String(pos) + ",\"maxResults\":" + String(P) +
                    ",\"faceLibType\":\"" + String(FACE_LIB_TYPE) + "\",\"FDID\":\"" + String(FDID_STR) + "\"}";
      String r = hikRequest("POST", "/ISAPI/Intelligent/FDLib/FDSearch?format=json", body, &code);
      if (g == 0) { String dbg=r; dbg.replace("\r"," "); dbg.replace("\n"," ");
                    Serial.println("[SCAN][DBG] FDSearch http" + String(code) + ": " + dbg.substring(0, 220)); }
      if (code != 200 || r.length() == 0) break;
      int found = 0, p = 0;
      while ((p = r.indexOf("\"FPID\"", p)) >= 0) {         // bắt mọi "FPID":"..."
        int c = r.indexOf(':', p); int q1 = (c>=0)?r.indexOf('"', c+1):-1; int q2 = (q1>=0)?r.indexOf('"', q1+1):-1;
        if (q1>=0 && q2>q1) { String fp = r.substring(q1+1, q2); if (fp.length()) { faceSet += fp + ","; found++; } }
        p = (q2>0)?q2+1:p+6;
      }
      int num = jsonInt_(r, "numOfMatches"); total = jsonInt_(r, "totalMatches");
      if (num < 0) num = found;                             // fallback nếu không đọc được numOfMatches
      pos += num;
      if (num < P || num == 0 || (total >= 0 && pos >= total)) break;
    }
    Serial.println("[SCAN][DBG] faceSet=" + faceSet);
  }
  // 2) UserInfo/Search -> roster {mã:{n:tên, f:0/1}}
  String roster = "{"; bool first = true;
  int pos = 0, total = -1, count = 0; const int PAGE = 20;
  StaticJsonDocument<256> filter;
  filter["UserInfoSearch"]["numOfMatches"] = true;
  filter["UserInfoSearch"]["totalMatches"] = true;
  filter["UserInfoSearch"]["UserInfo"][0]["employeeNo"] = true;
  filter["UserInfoSearch"]["UserInfo"][0]["name"] = true;
  for (int guard = 0; guard < 200; guard++) {
    int code;
    String body = "{\"UserInfoSearchCond\":{\"searchID\":\"1\",\"searchResultPosition\":" + String(pos) +
                  ",\"maxResults\":" + String(PAGE) + "}}";
    String r = hikRequest("POST", "/ISAPI/AccessControl/UserInfo/Search?format=json", body, &code);
    if (code != 200 || r.length() == 0) { Serial.printf("[SCAN] Search http%d -> dừng\n", code); break; }
    DynamicJsonDocument d(8192);
    if (deserializeJson(d, r, DeserializationOption::Filter(filter))) { Serial.println("[SCAN] parse lỗi -> dừng"); break; }
    JsonObject s = d["UserInfoSearch"];
    int numMatches = s["numOfMatches"] | 0;
    total = s["totalMatches"] | (int)0;
    for (JsonObject u : s["UserInfo"].as<JsonArray>()) {
      String emp = String((const char*)(u["employeeNo"] | ""));
      if (emp.length() == 0) continue;
      String nm = String((const char*)(u["name"] | ""));
      int f = (faceSet.indexOf("," + emp + ",") >= 0) ? 1 : 0;
      if (!first) roster += ",";
      roster += "\"" + jsonEscMin_(emp) + "\":{\"n\":\"" + jsonEscMin_(nm) + "\",\"f\":" + String(f) + "}";
      first = false; count++;
    }
    pos += numMatches;
    if (numMatches < PAGE || numMatches == 0 || (total >= 0 && pos >= total)) break;
  }
  roster += "}";
  Serial.printf("[SCAN] %d NV trên máy (total=%d), đang ghi Firebase...\n", count, total);
  String url = String(FB_HOST) + "/roster/" + STATION_NAME + ".json" + fbAuthParam();
  bool ok = net4gHttpPut(url, roster);
  Serial.printf("[SCAN] Ghi /roster: %s\n", ok ? "OK" : "FAIL");
}

// ---- Firebase: đọc lệnh NV (URL NGẮN, KHÔNG redirect -> chạy được qua 4G) ----
String g_doneOps[8]; int g_doneIdx = 0;                        // nhớ opId đã xử lý (tránh chạy lại trước khi Apps Script kịp xóa)
bool opDone(const String& op){ for(int i=0;i<8;i++) if(g_doneOps[i]==op) return true; return false; }
void opMarkDone(const String& op){ g_doneOps[g_doneIdx]=op; g_doneIdx=(g_doneIdx+1)&7; }

// GET body 1 URL (4G AT-HTTP; hoặc WiFi khi !USE_4G). Trả "" nếu không 200.
String httpGetBody(const String& url){
  if (USE_4G){
    int dl=0, st=net4gGetOpen(url,&dl);
    if(st!=200){ Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); if(st) Serial.printf("[FB] GET status=%d\n",st); return ""; }
    int n=net4gReadStart(dl); String body="";
    if(n>0){ body.reserve(n+4); int got=0; unsigned long t0=millis();
      while(got<n && millis()-t0<12000){ while(Serial2.available()&&got<n){ body+=(char)Serial2.read(); got++; t0=millis(); } delay(1);} }
    atWait("OK",2000); Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    return body;
  }
  WiFiClientSecure c; c.setInsecure(); HTTPClient h; h.begin(c,url);
  h.setFollowRedirects(HTTPC_FORCE_FOLLOW_REDIRECTS); h.setTimeout(15000);
  int code=h.GET(); String b=(code==200)?h.getString():""; h.end(); return b;
}

// ?auth=<secret> nếu đã điền secret; rỗng nếu còn "REPLACE..." (rule đang mở). Auth SAI sẽ bị Firebase từ chối kể cả rule mở.
String fbAuthParam(){ String s = FB_SECRET; if(s.length()==0 || s.startsWith("REPLACE")) return ""; return "?auth=" + s; }

// Firebase: lấy 1 lệnh pending (bỏ opId vừa xử lý). Trả JSON {opId,action,...} chuẩn cho checkEmployeeQueue, "" nếu rỗng.
String fbGetPending(){
  // CHỈ lấy 1 lệnh cũ nhất (orderBy $key + limitToFirst=1) -> response ~125 byte, tránh giới hạn đọc ~1KB của module 4G
  String url = String(FB_HOST) + "/queue/" + STATION_NAME + ".json?orderBy=%22%24key%22&limitToFirst=1";
  String sec = FB_SECRET; if (!(sec.length()==0 || sec.startsWith("REPLACE"))) url += "&auth=" + sec;
  String body = httpGetBody(url); body.trim();
  if(body.length()==0 || body=="null") return "";             // hàng đợi rỗng
  DynamicJsonDocument d(2048);
  if(deserializeJson(d, body)){ Serial.println("[FB] parse queue loi"); return ""; }
  JsonObject root = d.as<JsonObject>();
  if(root.containsKey("error")){ Serial.println("[FB] Firebase error: " + body.substring(0, 90)); return ""; }  // vd Permission denied / auth sai
  for(JsonPair kv : root){
    String opId = kv.key().c_str();
    if(opDone(opId)) continue;                                // đã xử lý -> chờ Apps Script xóa
    JsonObject c = kv.value().as<JsonObject>();
    DynamicJsonDocument o(1024);
    o["opId"]       = opId;
    o["action"]     = (const char*)(c["action"]     | "");
    o["employeeNo"] = (const char*)(c["employeeNo"] | "");
    o["name"]       = (const char*)(c["name"]       | "");
    o["pin"]        = (const char*)(c["pin"]        | "");
    o["gender"]     = (const char*)(c["gender"]     | "");
    o["hasPhoto"]   = FB_SYNC_PHOTO && (bool)(c["hasPhoto"] | false);
    o["date"]       = (const char*)(c["date"]  | "");   // cho lệnh getphoto (trích ảnh)
    o["time"]       = (const char*)(c["time"]  | "");
    o["which"]      = (const char*)(c["which"] | "");
    o["startTime"]  = (const char*)(c["startTime"] | "");   // lệnh backfill (Tải lại) cho máy 4G
    o["endTime"]    = (const char*)(c["endTime"]   | "");
    o["bfImage"]    = (bool)(c["image"] | false);
    String out; serializeJson(o, out); return out;
  }
  return "";
}

// DELETE 1 URL qua AT-HTTP (HTTPACTION=3). true nếu 2xx.
bool net4gHttpDelete(const String& url){
  Serial2.print("AT+HTTPTERM\r\n"); delay(120); while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); atWait("OK",3000);
  Serial2.print("AT+HTTPACTION=3\r\n");                          // 3 = DELETE
  String r=atWait("+HTTPACTION:",25000);
  int p=r.indexOf("+HTTPACTION:"), code=0;
  if(p>=0){ int c1=r.indexOf(',',p), c2=(c1>=0)?r.indexOf(',',c1+1):-1; if(c1>=0&&c2>=0) code=r.substring(c1+1,c2).toInt(); }
  Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
  Serial.printf("   [FB DEL] status=%d\n", code);
  return (code>=200 && code<300);
}

// Xóa 1 lệnh khỏi Firebase (đã xử lý xong) để ESP khỏi đọc lại — xóa cả /queue lẫn /photo cho gọn
void fbDelete(const String& opId){
  String qurl = String(FB_HOST) + "/queue/" + STATION_NAME + "/" + opId + ".json" + fbAuthParam();
  String purl = String(FB_HOST) + "/photo/" + STATION_NAME + "/" + opId + ".json" + fbAuthParam();
  if (USE_4G) { net4gHttpDelete(qurl); net4gHttpDelete(purl); }
  else {
    WiFiClientSecure c; c.setInsecure(); HTTPClient h;
    h.begin(c, qurl); h.sendRequest("DELETE"); h.end();
    h.begin(c, purl); h.sendRequest("DELETE"); h.end();
  }
}

// PUT 1 body JSON lên Firebase — dùng được CẢ 4G (AT-HTTP) LẪN WiFi (HTTPS trực tiếp).
bool fbHttpPut(const String& url, const String& body){
  if (USE_4G) return net4gHttpPut(url, body);
  WiFiClientSecure c; c.setInsecure();
  HTTPClient h; h.begin(c, url);
  h.setTimeout(20000);
  h.addHeader("Content-Type", "application/json");
  int code = h.sendRequest("PUT", (uint8_t*)body.c_str(), body.length());
  h.end();
  return (code >= 200 && code < 300);
}

// ===== TRÍCH ẢNH CHẤM CÔNG THEO YÊU CẦU (chống gian lận) =====
// Tìm sự kiện chấm công trên máy theo (mã NV + khung giờ ±120s), tải ảnh khuôn mặt,
// rồi ĐẨY base64 lên Firebase /photoresp/<station>/<opId> để dashboard đọc & hiện thumbnail.
// which ('in'/'out') chỉ để log; đã lọc theo giờ nên không cần dùng để chọn.
void handleGetPhoto(const String& opId, const String& emp, const String& date,
                    const String& tstr, const String& which){
  String respUrl = String(FB_HOST) + "/photoresp/" + STATION_NAME + "/" + opId + ".json" + fbAuthParam();
  if (date.length() < 10 || tstr.length() < 8) { fbHttpPut(respUrl, "{\"err\":\"thieu ngay/gio\"}"); return; }

  // 1) Khung giờ ±120s quanh giờ chấm công (clamp trong cùng ngày)
  long tgt = tstr.substring(0,2).toInt()*3600L + tstr.substring(3,5).toInt()*60L + tstr.substring(6,8).toInt();
  long lo = tgt - 120; if (lo < 0) lo = 0;
  long hi = tgt + 120; if (hi > 86399) hi = 86399;
  char b0[10], b1[10];
  sprintf(b0, "%02ld:%02ld:%02ld", lo/3600, (lo%3600)/60, lo%60);
  sprintf(b1, "%02ld:%02ld:%02ld", hi/3600, (hi%3600)/60, hi%60);
  String startT = date + "T" + b0 + "+07:00";
  String endT   = date + "T" + b1 + "+07:00";
  Serial.printf("[TRICH] opId=%s emp=%s %s..%s (%s)\n", opId.c_str(), emp.c_str(), startT.c_str(), endT.c_str(), which.c_str());

  // 2) Tìm sự kiện của đúng NV có ảnh, gần giờ mục tiêu nhất
  String payload = "{\"AcsEventCond\":{\"searchID\":\"1\",\"searchResultPosition\":0,\"maxResults\":10,"
                   "\"major\":0,\"minor\":0,\"startTime\":\"" + startT + "\",\"endTime\":\"" + endT +
                   "\",\"employeeNoString\":\"" + emp + "\"}}";
  String r = hikPost(payload);
  String pic = "";
  if (r.length()) {
    StaticJsonDocument<256> filter;
    JsonObject fi = filter["AcsEvent"]["InfoList"].createNestedObject();
    fi["time"] = true; fi["pictureURL"] = true; fi["employeeNoString"] = true;
    DynamicJsonDocument doc(16384);
    if (!deserializeJson(doc, r, DeserializationOption::Filter(filter))) {
      JsonArray list = doc["AcsEvent"]["InfoList"].as<JsonArray>();
      long best = 999999;
      for (JsonObject e : list) {
        String eno = e["employeeNoString"] | "";
        if (eno.length() && eno != emp) continue;              // khác NV -> bỏ (cùng khung giờ có thể nhiều người)
        String pu = e["pictureURL"] | "";
        if (pu.length() < 5) continue;
        String et = e["time"].as<String>();                    // yyyy-MM-ddTHH:mm:ss+07:00
        int tp = et.indexOf('T');
        long es = -1;
        if (tp >= 0 && (int)et.length() >= tp + 9)
          es = et.substring(tp+1,tp+3).toInt()*3600L + et.substring(tp+4,tp+6).toInt()*60L + et.substring(tp+7,tp+9).toInt();
        long diff = (es < 0) ? 0 : (es >= tgt ? es - tgt : tgt - es);
        if (diff < best) { best = diff; pic = pu; }
      }
    } else Serial.println("[TRICH] parse AcsEvent lỗi");
  }
  if (pic.length() < 5) {
    Serial.println("[TRICH] Không tìm thấy sự kiện có ảnh (máy có thể đã xóa ảnh cũ)");
    fbHttpPut(respUrl, "{\"err\":\"khong tim thay anh (may co the da xoa anh cu)\"}");
    return;
  }

  // 3) Tải ảnh -> base64 -> đẩy Firebase
  uint8_t* jpeg = NULL;
  int jlen = fetchImageRaw(pic, &jpeg);
  if (jlen <= 0) { if (jpeg) free(jpeg); fbHttpPut(respUrl, "{\"err\":\"tai anh tu may that bai\"}"); return; }
  char* b64 = NULL;
  int b64len = base64Encode(jpeg, jlen, &b64);
  free(jpeg);
  if (b64len <= 0 || !b64) { if (b64) free(b64); fbHttpPut(respUrl, "{\"err\":\"het RAM khi encode anh\"}"); return; }
  String body; body.reserve(b64len + 20);
  body = "{\"img\":\""; body += b64; body += "\"}";
  free(b64);
  bool ok = fbHttpPut(respUrl, body);
  Serial.printf("[TRICH] Đẩy ảnh %d byte b64 -> %s\n", b64len, ok ? "OK" : "FAIL");
  if (!ok) fbHttpPut(respUrl, "{\"err\":\"day anh len firebase that bai (anh co the qua lon cho 4G)\"}");
}

// Poll hàng đợi nhân viên, xử lý 1 lệnh rồi xóa khỏi Firebase (ack)
void checkEmployeeQueue() {
  String resp = USE_FIREBASE ? fbGetPending()
                             : empGet("action=pending&station=" + urlEncodeMin(String(STATION_NAME)));
  if (resp.length() == 0) return;
  StaticJsonDocument<1024> d;
  if (deserializeJson(d, resp)) { Serial.println("[NV] parse pending lỗi"); return; }
  if (d["empty"] | false) return;

  String opId   = d["opId"]       | "";
  String action = d["action"]     | "";
  String emp    = d["employeeNo"] | "";
  String name   = d["name"]       | "";
  String pin    = d["pin"]        | "";
  String gender = d["gender"]     | "";
  bool hasPhoto = d["hasPhoto"]   | false;
  String date   = d["date"]       | "";                              // cho lệnh getphoto
  String tstr   = d["time"]        | "";
  String which  = d["which"]       | "";
  String bfStart = d["startTime"]  | "";                             // lệnh backfill (Tải lại)
  String bfEnd   = d["endTime"]    | "";
  bool   bfImage = d["bfImage"]    | false;
  if (opId.length() == 0 || action.length() == 0) return;

  if (action == "scan") {                                            // QUÉT: đọc danh sách NV từ máy -> Firebase /roster
    Serial.printf("[NV] Lệnh quét máy (%s)\n", opId.c_str());
    showSync("scan", "may cham cong");
    hikScanRoster();
    opMarkDone(opId); if (USE_FIREBASE) fbDelete(opId);
    statusUntil = millis() + 4000;
    return;
  }

  if (action == "getphoto") {                                        // TRÍCH ẢNH chấm công theo yêu cầu (chống gian lận)
    Serial.printf("[TRICH] Lệnh trích ảnh %s: NV=%s ngày=%s giờ=%s (%s)\n",
                  opId.c_str(), emp.c_str(), date.c_str(), tstr.c_str(), which.c_str());
    showSync("trich anh", emp);
    handleGetPhoto(opId, emp, date, tstr, which);
    opMarkDone(opId); if (USE_FIREBASE) fbDelete(opId);              // xóa /queue (không đụng /photoresp - server tự xóa sau khi đọc)
    statusUntil = millis() + 3000;
    return;
  }

  if (action == "backfill") {                                        // Dashboard "Tải lại từ máy" -> máy 4G nhận qua Firebase
    Serial.printf("[TẢI LẠI] %s: %s..%s emp=%s\n", opId.c_str(), bfStart.c_str(), bfEnd.c_str(), emp.c_str());
    opMarkDone(opId); if (USE_FIREBASE) fbDelete(opId);              // xóa lệnh TRƯỚC (backfill lâu -> tránh chạy lại)
    if (bfStart.length() >= 10 && bfEnd.length() >= 10) {
      showBackfillProgress(0);
      int p = backfillRange(bfStart, bfEnd, bfImage && !USE_4G, emp);   // 4G: chỉ giờ
      Serial.printf("[TẢI LẠI] Xong, đẩy %d lượt.\n", p);
    }
    statusUntil = millis() + 3000;
    return;
  }

  if (action != "add" && action != "edit" && action != "delete") {   // lệnh lạ (vd op-test 'ping') -> dọn, khỏi tạo user rác
    Serial.printf("[NV] Bỏ qua lệnh lạ '%s' (%s)\n", action.c_str(), opId.c_str());
    opMarkDone(opId); if (USE_FIREBASE) fbDelete(opId);
    return;
  }

  if (hasPhoto && ESP.getFreeHeap() < MIN_HEAP_FOR_PHOTO) {
    Serial.printf("[NV] Hoãn %s: heap %d < %d\n", opId.c_str(), ESP.getFreeHeap(), MIN_HEAP_FOR_PHOTO);
    return;   // không ack -> xử lý lại vòng sau khi RAM rảnh
  }

  Serial.printf("\n[NV] Xử lý %s: %s NV=%s '%s' pin=%s photo=%d\n",
                opId.c_str(), action.c_str(), emp.c_str(), name.c_str(), pin.c_str(), hasPhoto);
  showSync(action, name);

  String msg;
  bool ok = processOp(action, emp, name, pin, gender, hasPhoto, opId, msg);
  Serial.printf("[NV] Kết quả %s: %s | %s\n", opId.c_str(), ok ? "DONE" : "FAIL", msg.c_str());
  opMarkDone(opId);
  // XÓA lệnh khỏi Firebase DÙ done hay fail: limitToFirst chỉ lấy lệnh đầu, để lệnh fail lại sẽ KẸT queue.
  // (Fail đã in ở dòng trên; muốn thử lại thì thêm/sửa NV lại trên web.)
  if (USE_FIREBASE) fbDelete(opId);
  else empGet("action=ack&opId=" + opId + "&status=" + String(ok ? "done" : "fail") + "&msg=" + urlEncodeMin(msg));
  statusUntil = millis() + 4000;   // giữ màn báo ngắn rồi về màn chờ
}

// Log thông tin face lib lúc boot (chẩn đoán)
void logFdLib() {
  int code; String r = hikRequest("GET", "/ISAPI/Intelligent/FDLib?format=json", "", &code);
  int n = r.length() > 200 ? 200 : r.length();
  Serial.printf("[NV] FDLib http%d: %s\n", code, r.substring(0, n).c_str());
}

// ---------- CHỐNG MẤT DỮ LIỆU: ghi mốc đồng bộ + bù (backfill) ----------
// Ghi nhớ giờ lượt cuối đã gửi OK -> để bù khi khởi động / định kỳ. Chuỗi cùng độ dài so sánh trực tiếp (chỉ tiến).
void rememberSync(const String& eventTime) {
  if (eventTime.length() >= 19 && eventTime > lastSyncTime) {
    lastSyncTime = eventTime;
    prefs.putString("lastSyncT", lastSyncTime);
  }
}

void showBackfillProgress(int done) {
  idleActive = false;
  tft.fillScreen(TFT_BLACK); veKhung(); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_ORANGE, TFT_BLACK); tft.drawString("DANG TAI LAI DU LIEU", 160, 95, 4);
  tft.setTextColor(TFT_WHITE, TFT_BLACK);  tft.drawString("Da dong bo: " + String(done), 160, 145, 4);
  tft.setTextColor(TFT_DARKGREY, TFT_BLACK); tft.drawString("Vui long doi...", 160, 185, 2);
}

// Bù lịch sử theo khoảng thời gian (phân trang toàn bộ log Hikvision, đẩy từng lượt).
// Google TỰ chống trùng (bỏ giờ đã ghi) nên chạy lại an toàn. 4G: pushEventToGoogle tự gửi slim (không ảnh).
int backfillRange(String startISO, String endISO, bool withImage, String empFilter) {
  int pos = 0, pushed = 0, seen = 0; const int PAGE = 20;
  StaticJsonDocument<512> filter;
  JsonObject fi = filter["AcsEvent"]["InfoList"].createNestedObject();
  fi["serialNo"] = true; fi["time"] = true; fi["employeeNoString"] = true;
  fi["name"] = true;     fi["cardNo"] = true; fi["pictureURL"] = true;
  filter["AcsEvent"]["responseStatusStrg"] = true;
  filter["AcsEvent"]["numOfMatches"] = true;
  filter["AcsEvent"]["totalMatches"] = true;
  for (int guard = 0; guard < 1000; guard++) {
    if (!netUp()) { Serial.println("[BÙ] Mất mạng, dừng."); break; }
    String payload = "{\"AcsEventCond\":{\"searchID\":\"backfill\",\"searchResultPosition\":" + String(pos) +
                     ",\"maxResults\":" + String(PAGE) + ",\"major\":0,\"minor\":0" +
                     ",\"startTime\":\"" + startISO + "\",\"endTime\":\"" + endISO + "\"}}";
    String r = hikPost(payload);
    if (r.length() == 0) { Serial.println("[BÙ] Phản hồi rỗng, dừng."); break; }
    DynamicJsonDocument doc(16384);
    if (deserializeJson(doc, r, DeserializationOption::Filter(filter))) { Serial.println("[BÙ] Parse lỗi, dừng."); break; }
    JsonArray list = doc["AcsEvent"]["InfoList"].as<JsonArray>();
    int pageCount = 0;
    for (JsonObject e : list) {
      pageCount++; seen++;
      String emp = e["employeeNoString"] | ""; String name = e["name"] | "";
      String card = e["cardNo"] | ""; String pic = e["pictureURL"] | "";
      if (emp.length() == 0) emp = card;
      if (emp.length() == 0 && name.length() == 0) continue;
      if (empFilter.length() > 0 && emp != empFilter) continue;
      String eventTime = e["time"].as<String>();
      eventTime.replace("T", " "); eventTime.replace("+07:00", "");
      char* imgB64 = NULL; int imgB64Len = 0;
      if (!USE_4G && withImage && pic.length() > 5) {   // 4G: bỏ ảnh (pushEventToGoogle cũng tự bỏ)
        uint8_t* jpeg = NULL; int jl = fetchImageRaw(pic, &jpeg);
        if (jl > 0) imgB64Len = base64Encode(jpeg, jl, &imgB64);
        if (jpeg) free(jpeg);
      }
      bool ok = pushEventToGoogle(emp, name, eventTime, imgB64, imgB64Len); imgB64 = NULL;
      if (ok) { pushed++; rememberSync(eventTime); }
      if (seen % 3 == 0) showBackfillProgress(pushed);
      delay(50);
    }
    String status = doc["AcsEvent"]["responseStatusStrg"] | "OK";
    int numMatches = doc["AcsEvent"]["numOfMatches"] | 0;
    Serial.printf("[BÙ] pos=%d: %d lượt, status=%s\n", pos, pageCount, status.c_str());
    if (status != "MORE") break;
    if (pageCount == 0) break;
    pos += (numMatches > 0 ? numMatches : pageCount);
  }
  Serial.printf("[BÙ] Hoàn tất: quét %d, đẩy %d lượt.\n", seen, pushed);
  return pushed;
}

// ② Heartbeat online: ghi mốc thời gian (Firebase server timestamp) lên /hb/<trạm> mỗi 60s -> dashboard biết máy online.
void hbSend() {
  if (!netUp()) return;
  String url = String(FB_HOST) + "/hb/" + STATION_NAME + ".json" + fbAuthParam();
  fbHttpPut(url, "{\"t\":{\".sv\":\"timestamp\"},\"fw\":\"" FW_VERSION "\"}");
}

// ===== OTA TỪ XA: tải firmware .bin từ URL (GitHub) rồi ghi flash =====
// Update.h tự kiểm tra ảnh hợp lệ trước khi chuyển partition -> tải lỗi/dở sẽ HỦY, GIỮ firmware cũ (an toàn).
bool otaDownloadAndFlash(const String& url){
  Serial.println("[OTA] Tải firmware: " + url);
  idleActive = false;
  tft.fillScreen(TFT_BLACK); veKhung(); tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_ORANGE, TFT_BLACK); tft.drawString("DANG CAP NHAT FW", 160, 70, 4);
  tft.setTextColor(TFT_RED, TFT_BLACK);    tft.drawString("KHONG TAT NGUON", 160, 110, 2);
  ttKhung(); ttPct(0);
  /* ⚠️ Nhánh WiFi dùng Update.writeStream() — MỘT lệnh chạy suốt, không có mốc nào để
     cập nhật màn. Trước đây nạp qua WiFi là màn ĐỨNG IM cả phút, đúng lúc cần biết máy
     còn sống. onProgress() cho thanh chạy được ở cả nhánh WiFi lẫn 4G. */
  Update.onProgress([](size_t da, size_t tong){ if (tong) ttPct((int)((uint64_t)da * 100 / tong)); });

  if (USE_4G) {
    int dl = 0, st = net4gGetOpen(url, &dl);
    if (st != 200 || dl <= 0) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); Serial.printf("[OTA] 4G GET status=%d len=%d\n", st, dl); return false; }
    if (!Update.begin(dl)) { Update.printError(Serial); Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); return false; }
    uint8_t buf[1024]; int start = 0; bool ok = true; unsigned long lastShow = 0;
    while (start < dl) {
      int want = (dl - start > 1024) ? 1024 : (dl - start);
      int got = net4gReadChunk(start, want, buf);
      if (got <= 0) { Serial.printf("[OTA] đọc chunk lỗi tại %d/%d\n", start, dl); ok = false; break; }
      if (Update.write(buf, got) != (size_t)got) { Update.printError(Serial); ok = false; break; }
      start += got;
      ttPct((int)((long)start * 100 / dl));
      if (millis() - lastShow > 1500) { lastShow = millis();
        tft.setTextColor(TFT_WHITE, TFT_BLACK); tft.setTextPadding(260);
        tft.drawString(String((long)start * 100 / dl) + "%  (" + String(start/1024) + "/" + String(dl/1024) + " KB)", 160, 195, 4);
        tft.setTextPadding(0); }
    }
    Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    if (!ok) { Update.abort(); return false; }
    if (!Update.end(true)) { Update.printError(Serial); return false; }
    Serial.println("[OTA] Flash OK (4G)."); return true;
  } else {
    WiFiClientSecure client; client.setInsecure();
    HTTPClient http; http.begin(client, url);
    http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS); http.setTimeout(30000);
    int code = http.GET();
    if (code != 200) { Serial.printf("[OTA] WiFi GET %d\n", code); http.end(); return false; }
    int len = http.getSize();
    if (len <= 0) { Serial.println("[OTA] không rõ kích thước .bin"); http.end(); return false; }
    if (!Update.begin(len)) { Update.printError(Serial); http.end(); return false; }
    size_t written = Update.writeStream(*http.getStreamPtr());
    http.end();
    if (written != (size_t)len) { Serial.printf("[OTA] ghi %u/%d byte\n", (unsigned)written, len); Update.abort(); return false; }
    if (!Update.end(true)) { Update.printError(Serial); return false; }
    Serial.println("[OTA] Flash OK (WiFi)."); return true;
  }
}

// Đọc Firebase /ota = {"ver":"...","url":"..."}; nếu ver khác bản đã áp -> tải + flash + khởi động lại.
void checkOtaUpdate(){
  if (!netUp()) return;
  String body = httpGetBody(String(FB_HOST) + "/ota.json" + fbAuthParam());
  body.trim();
  if (body.length() == 0 || body == "null") return;
  DynamicJsonDocument d(768);
  if (deserializeJson(d, body)) return;
  String ver = d["ver"] | "";
  String furl = d["url"] | "";
  if (ver.length() == 0 || furl.length() == 0) return;
  if (ver == prefs.getString("otaVer", "")) return;   // đã áp bản này rồi
  if (ver == g_otaTriedVer) return;                    // đã thử (lỗi) trong phiên này -> chờ reboot / bản mới hơn
  g_otaTriedVer = ver;
  Serial.println("[OTA] Có bản mới: " + ver + " (đang chạy: " FW_VERSION ")");
  if (otaDownloadAndFlash(furl)) { prefs.putString("otaVer", ver); Serial.println("[OTA] Khởi động lại bản mới..."); delay(800); ESP.restart(); }
  else { Serial.println("[OTA] Cập nhật thất bại -> giữ firmware cũ."); showIdle(); }
}

// ---------- Kiểm tra lượt chấm công mới ----------
void checkNewAcsEvents() {
  String r1 = hikPost("{\"AcsEventCond\":{\"searchID\":\"1\",\"searchResultPosition\":0,\"maxResults\":1,\"major\":0,\"minor\":0}}");
  if (r1.length() == 0) return;
  DynamicJsonDocument d1(1024);
  if (deserializeJson(d1, r1)) return;
  long total = d1["AcsEvent"]["totalMatches"] | 0;
  if (total <= 0) return;

  long pos = total > WINDOW ? total - WINDOW : 0;
  String payload2 = "{\"AcsEventCond\":{\"searchID\":\"1\",\"searchResultPosition\":" + String(pos) +
                    ",\"maxResults\":" + String(WINDOW) + ",\"major\":0,\"minor\":0}}";
  String r2 = hikPost(payload2);
  if (r2.length() == 0) return;

  StaticJsonDocument<400> filter;
  JsonObject fi = filter["AcsEvent"]["InfoList"].createNestedObject();
  fi["serialNo"] = true;
  fi["time"] = true;
  fi["employeeNoString"] = true;
  fi["name"] = true;
  fi["cardNo"] = true;
  fi["pictureURL"] = true;

  DynamicJsonDocument doc(16384);
  if (deserializeJson(doc, r2, DeserializationOption::Filter(filter))) {
    Serial.println("[MÁY] Parse đuôi lỗi.");
    return;
  }

  JsonArray list = doc["AcsEvent"]["InfoList"].as<JsonArray>();
  long adv = lastSerialNo, maxSerial = lastSerialNo; bool stop = false;

  for (JsonObject e : list) {
    long sn = e["serialNo"] | 0;
    if (sn > maxSerial) maxSerial = sn;

    if (lastSerialNo == -1) continue;              // baseline (mới nạp) -> chỉ lấy maxSerial, không đẩy lịch sử
    if (sn <= lastSerialNo || stop) continue;      // đã gửi / đã gặp 1 lỗi -> để dành vòng sau (giữ thứ tự, không bỏ sót)

    String emp  = e["employeeNoString"] | "";
    String name = e["name"] | "";
    String card = e["cardNo"] | "";
    String pic  = e["pictureURL"] | "";
    if (emp.length() == 0) emp = card;
    if (emp.length() == 0 && name.length() == 0) { if (sn > adv) adv = sn; continue; }   // rác -> bỏ, vẫn nhảy con trỏ

    String eventTime = e["time"].as<String>();
    eventTime.replace("T", " ");
    eventTime.replace("+07:00", "");

    int evSec = timeToSec(eventTime);
    if (emp == lastEmp && evSec >= 0 && (evSec - lastEmpSec) >= 0 && (evSec - lastEmpSec) < DEBOUNCE_SEC) {
      Serial.printf("[BỎ QUA] %s quẹt trùng trong %ds\n", emp.c_str(), DEBOUNCE_SEC);
      if (sn > adv) adv = sn; continue;            // quẹt trùng (đã ghi) -> coi như xử lý, nhảy con trỏ
    }

    Serial.println("\n[LƯỢT MỚI]");
    Serial.print("   NV: "); Serial.print(emp); Serial.print("  Tên: "); Serial.println(name);
    Serial.print("   Thời gian: "); Serial.println(eventTime);

    uint8_t* jpeg = NULL; int jpegLen = 0; char* imgB64 = NULL; int imgB64Len = 0;
    // 4G: KHÔNG gửi ảnh lúc chấm công (chỉ trích khi bấm nút) -> bỏ tải+encode ảnh cho nhẹ + tránh "Hết RAM".
    if (!USE_4G && pic.length() > 5) {
      Serial.println("   [ẢNH] Đang tải ảnh khuôn mặt từ máy...");
      jpegLen = fetchImageRaw(pic, &jpeg);
      if (jpegLen > 0) imgB64Len = base64Encode(jpeg, jpegLen, &imgB64);
      if (jpeg) { free(jpeg); jpeg = NULL; }
    }

    showThankYou(name, eventTime, NULL, 0);

    // CHỈ nhảy con trỏ (adv) + ghi mốc đồng bộ khi gửi THÀNH CÔNG -> mất mạng/đẩy lỗi KHÔNG mất lượt (thử lại vòng sau).
    bool ok = pushEventToGoogle(emp, name, eventTime, imgB64, imgB64Len);
    imgB64 = NULL;   // quyền sở hữu đã chuyển cho pushEventToGoogle
    if (ok) { adv = sn; lastEmp = emp; lastEmpSec = evSec; rememberSync(eventTime); }
    else { stop = true; Serial.printf("[GIỮ] serial %ld gửi lỗi -> thử lại vòng sau (không mất lượt)\n", sn); }
  }

  if (lastSerialNo == -1) {                        // lần đầu (mới nạp): nhận baseline, KHÔNG đẩy lịch sử
    if (maxSerial >= 0) { lastSerialNo = maxSerial; prefs.putLong("lastSN", lastSerialNo); }
    Serial.print("[KHỞI TẠO] Theo dõi từ serialNo = "); Serial.println(lastSerialNo);
    return;
  }
  if (adv != lastSerialNo) { lastSerialNo = adv; prefs.putLong("lastSN", lastSerialNo); }
}

// ======================= WEB PORTAL: cấu hình WiFi + quản lý nhân viên =======================
String jsonEsc(String s){ s.replace("\\","\\\\"); s.replace("\"","\\\""); return s; }

// Kết nối WiFi cửa hàng (STA). Đọc creds từ flash (fallback mặc định). true nếu nối được trong timeout.
bool connectSTA(uint32_t timeoutMs){
  cfgSsid = prefs.getString("wifi_ssid", ssid);
  cfgPass = prefs.getString("wifi_pass", password);
  Serial.printf("[WiFi] STA -> %s\n", cfgSsid.c_str());
  WiFi.begin(cfgSsid.c_str(), cfgPass.c_str());
  unsigned long t0 = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - t0 < timeoutMs) { delay(300); Serial.print("."); }
  return WiFi.status() == WL_CONNECTED;
}

// Bật AP cấu hình (song song STA): SSID "CHAM_CONG" @ 192.168.4.1 (luôn bật để vào quản lý/cấu hình)
/* ⚠️ 31/07/2026 — TÊN AP CỐ ĐỊNH "CHAM_CONG", KHÔNG ghép tên cơ sở nữa. ĐỪNG ĐỔI LẠI.
   Lý do (đã trả giá thật): đầu đọc Hikvision ở máy 4G nối vào AP này theo SSID. Tên AP ghép
   STATION_NAME nên hễ tên cơ sở đổi — nạp lại firmware làm NVS trống, hay web app gán cơ sở
   qua whoami — là AP đổi tên, Hikvision tìm không thấy SSID cũ, mất mạng luôn. Triệu chứng:
   `[MÁY] Lỗi HTTP: -1` liên tục, không đọc được lượt quẹt nào, web app thì trắng trơn.
   Tên cơ sở giờ chỉ dùng để hiển thị + đặt tên sheet, KHÔNG dính vào tên AP. */
void startAP(){
  String apName = "CHAM_CONG";
  // Chip mới nạp bản CI (secrets.h toàn placeholder) thì chưa có mật khẩu AP -> phải MỞ AP,
  // không thì không vào được portal mà khai cấu hình = máy thành cục chặn giấy.
  bool mo = (_cfgApPass.length() < 8);      // WPA2 đòi tối thiểu 8 ký tự
  if (mo) WiFi.softAP(apName.c_str());
  else    WiFi.softAP(apName.c_str(), AP_PASS);
  dnsServer.start(53, "*", IPAddress(192,168,4,1));   // captive portal: mọi tên miền -> 192.168.4.1
  Serial.printf("[AP] %s @ %s (%s)\n", apName.c_str(), WiFi.softAPIP().toString().c_str(),
                mo ? "MỞ - chưa có mật khẩu AP, vào khai cấu hình ngay" : "có mật khẩu");
}

bool g_4gReady = false;                                       // 4G (AT-HTTP) đã sẵn sàng (đăng ký LTE) chưa
bool g_timeSynced = false;                                    // đã lấy được giờ mạng 4G (AT+CCLK?) cho đồng hồ chưa
int  g_simTx = SIM_TX_PIN, g_simRx = SIM_RX_PIN; long g_simBaud = 115200;  // chiều + baud UART module đã dò được
bool netUp(){ return USE_4G ? g_4gReady : (WiFi.status()==WL_CONNECTED); }   // có Internet chưa (4G: đã đăng ký LTE, đẩy qua AT-HTTP)
bool hikUp(){ return USE_4G ? true : (WiFi.status()==WL_CONNECTED); }              // tới được Hikvision (4G: qua AP; WiFi: qua LAN)

// Bật nguồn A7680C: xung PWRKEY/PEN (GPIO17). A7680C thường cần kéo thấp ~1s để bật.
void modemPowerOn(){
  pinMode(SIM_PWRKEY, OUTPUT);
  digitalWrite(SIM_PWRKEY, HIGH); delay(200);
  digitalWrite(SIM_PWRKEY, LOW);  delay(1200);   // xung bật (nếu board dùng PEN mức cao thì đổi: bỏ 2 dòng LOW/HIGH, chỉ giữ HIGH)
  digitalWrite(SIM_PWRKEY, HIGH);
  Serial.println("[4G] Bật nguồn A7680C, chờ boot ~12s...");
  delay(12000);
}

// Thử gửi "AT" trên 1 cặp chân + baud, true nếu module trả "OK" (kiểm nguồn + đúng chiều TX/RX + đúng baud)
bool atProbe(int txPin, int rxPin, long baud){
  Serial2.begin(baud, SERIAL_8N1, rxPin, txPin);
  delay(300);
  bool ok=false;
  for(int i=0;i<3 && !ok;i++){
    while(Serial2.available()) Serial2.read();
    Serial2.print("AT\r\n");
    unsigned long t0=millis(); String r="";
    while(millis()-t0<800){ while(Serial2.available()) r+=(char)Serial2.read(); if(r.indexOf("OK")>=0){ok=true;break;} delay(5); }
    r.replace("\r"," "); r.replace("\n"," ");
    Serial.printf("[4G] AT (esp_tx=%d esp_rx=%d @%ld) -> %s\n", txPin, rxPin, baud, r.length()?r.c_str():"(khong tra loi)");
  }
  Serial2.end();
  return ok;
}

// Gửi 1 lệnh AT, trả về chuỗi phản hồi (dùng cho chẩn đoán)
String atSend(const char* cmd, unsigned long to){
  while(Serial2.available()) Serial2.read();
  Serial2.print(cmd); Serial2.print("\r\n");
  unsigned long t0=millis(); String r="";
  while(millis()-t0<to){ while(Serial2.available()) r+=(char)Serial2.read(); if(r.indexOf("OK")>=0||r.indexOf("ERROR")>=0) break; delay(5); }
  r.replace("\r"," "); r.replace("\n"," "); r.trim(); return r;
}
// Đọc Serial2 tới khi thấy 'token' hoặc hết thời gian (cho AT-HTTP: DOWNLOAD / +HTTPACTION)
String atWait(const char* token, unsigned long to){
  unsigned long t0=millis(); String r="";
  while(millis()-t0<to){ while(Serial2.available()) r+=(char)Serial2.read(); if(r.indexOf(token)>=0) break; delay(3); }
  return r;
}
// Soi SIM/sóng/đăng ký + set APN (Serial2 ĐÃ mở sẵn). Trả true nếu đã đăng ký mạng.
bool net4gDiag(){
  Serial.println("[4G] === CHAN DOAN ===");
  Serial.println("  CPIN?  -> " + atSend("AT+CPIN?",1500));   // mong: +CPIN: READY
  Serial.println("  CSQ    -> " + atSend("AT+CSQ",1500));     // rssi; 99=khong song, >=10 tot
  String reg = atSend("AT+CEREG?",1500); Serial.println("  CEREG? -> " + reg);  // 0,1 / 0,5 = da dang ky LTE
  Serial.println("  COPS?  -> " + atSend("AT+COPS?",3000));   // nha mang
  atSend("ATE0",800);                                         // tat echo
  atSend("AT+CFUN=1",3000);                                   // full functionality
  atSend("AT+CTZU=1",1000);                                   // tự cập nhật giờ/múi giờ từ mạng (NITZ) -> cho AT+CCLK?
  atSend("AT+CGDCONT=1,\"IP\",\"v-internet\"",1500);          // APN Viettel (context 1 cho HTTP)
  Serial.println("  CGACT1 -> " + atSend("AT+CGACT=1,1",10000)); // KÍCH HOẠT context 1 (bearer cho HTTP)
  Serial.println("[4G] ==================");
  return (reg.indexOf(",1")>=0 || reg.indexOf(",5")>=0);      // đã đăng ký mạng?
}
// Đẩy 1 JSON lên URL bằng LỆNH AT HTTP của module (không cần PPP). Apps Script trả 302 = đã nhận.
bool net4gHttpPost(const String& url, const String& body){
  if(!g_4gReady) return false;
  while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPTERM\r\n"); delay(150); while(Serial2.available()) Serial2.read();  // dọn phiên cũ
  Serial2.print("AT+HTTPINIT\r\n"); String ri=atWait("OK",6000); ri.replace("\r"," ");ri.replace("\n"," ");ri.trim(); Serial.println("   [HTTPINIT] "+ri);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); atWait("OK",3000);
  Serial2.print("AT+HTTPPARA=\"CONTENT\",\"application/json\"\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPDATA="); Serial2.print(body.length()); Serial2.print(",30000\r\n");
  String rd=atWait("DOWNLOAD",6000); rd.replace("\r"," ");rd.replace("\n"," ");rd.trim(); Serial.println("   [HTTPDATA] "+rd);
  if (rd.indexOf("DOWNLOAD")<0){ Serial2.print("AT+HTTPTERM\r\n"); Serial.println("   [4G HTTP] khong vao DOWNLOAD"); return false; }
  Serial2.print(body); atWait("OK",30000);                    // nạp body rồi chờ OK
  Serial2.print("AT+HTTPACTION=1\r\n");                        // 1 = POST
  String r = atWait("+HTTPACTION:",40000);                    // vd: +HTTPACTION: 1,200,17
  int code=0, p=r.indexOf("+HTTPACTION:");
  if(p>=0){ int c1=r.indexOf(',',p); int c2=(c1>=0)?r.indexOf(',',c1+1):-1; if(c1>=0&&c2>=0) code=r.substring(c1+1,c2).toInt(); }
  Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
  Serial.printf("   [4G HTTP] status=%d\n", code);
  return (code==200||code==301||code==302||code==303||code==307);   // 2xx hoặc redirect Apps Script = đã nhận
}
// PUT 1 body JSON lên URL (Firebase set/overwrite). Trả true nếu 2xx.
bool net4gHttpPut(const String& url, const String& body){
  if(!g_4gReady) return false;
  while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPTERM\r\n"); delay(150); while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); atWait("OK",3000);
  Serial2.print("AT+HTTPPARA=\"CONTENT\",\"application/json\"\r\n"); atWait("OK",2000);
  // 9600 baud CHẬM: ảnh to (base64 ~40-70KB) cần ~60-90s để gửi hết qua UART.
  // Cửa sổ nhận data phải đủ dài theo kích thước, trần 120s (giới hạn AT+HTTPDATA của module).
  long dwin = (body.length() > 6000) ? 120000L : 30000L;
  Serial2.print("AT+HTTPDATA="); Serial2.print(body.length()); Serial2.print(","); Serial2.print(dwin); Serial2.print("\r\n");
  String rd=atWait("DOWNLOAD",6000);
  if (rd.indexOf("DOWNLOAD")<0){ Serial2.print("AT+HTTPTERM\r\n"); Serial.println("   [4G PUT] khong vao DOWNLOAD"); return false; }
  Serial2.print(body); atWait("OK",dwin);                      // print(body) chặn tới khi gửi hết ở baud; chờ OK trong cửa sổ dwin
  Serial2.print("AT+HTTPACTION=4\r\n");                        // 4 = PUT
  String r = atWait("+HTTPACTION:",40000);
  int code=0, p=r.indexOf("+HTTPACTION:");
  if(p>=0){ int c1=r.indexOf(',',p); int c2=(c1>=0)?r.indexOf(',',c1+1):-1; if(c1>=0&&c2>=0) code=r.substring(c1+1,c2).toInt(); }
  Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
  Serial.printf("   [4G PUT] status=%d\n", code);
  return (code>=200 && code<300);
}
// GET qua AT-HTTP: mở phiên + ACTION=0 + tự follow 302 (Apps Script). Trả status, gán *datalen (byte body 200).
// KHÔNG HTTPTERM khi thành công — để caller HTTPREAD đọc body ngay.
int net4gGetOpen(String url, int* datalen){
  if(datalen) *datalen=0;
  for (int hop=0; hop<3; hop++){
    Serial2.print("AT+HTTPTERM\r\n"); delay(120); while(Serial2.available()) Serial2.read();  // phiên SẠCH mỗi hop (host redirect khác)
    Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
    Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
    Serial2.print("AT+HTTPPARA=\"REDIR\",1\r\n"); atWait("OK",2000);   // module TỰ follow 302 (nếu hỗ trợ) -> hop0 ra 200 luôn
    Serial.printf("   [4G GET] hop%d URL len=%d\n", hop, url.length());
    Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n"); atWait("OK",3000);
    int status=0, dl=0;
    for(int tryn=0; tryn<2; tryn++){                            // thử 2 lần: đủ để biết 706 có phải chập chờn, mà không khựng lâu
      Serial2.print("AT+HTTPACTION=0\r\n");                     // 0 = GET
      String r=atWait("+HTTPACTION:",25000);                   // 25s: response thật <5s; bound để loop khỏi đứng lâu
      int p=r.indexOf("+HTTPACTION:"); status=0; dl=0;
      if(p>=0){ int c1=r.indexOf(',',p), c2=(c1>=0)?r.indexOf(',',c1+1):-1; if(c1>=0&&c2>=0){ status=r.substring(c1+1,c2).toInt(); dl=r.substring(c2+1).toInt(); } }
      Serial.printf("   [4G GET] hop%d try%d status=%d len=%d\n", hop, tryn, status, dl);
      if(status<600) break;                                     // đã có mã HTTP thật -> thôi retry
      delay(300);
    }
    if(status==301||status==302||status==303||status==307){     // REDIR không ăn -> tự follow (hop sau init lại)
      Serial2.print("AT+HTTPHEAD\r\n"); String h=atWait("OK",6000);
      int lp=h.indexOf("Location:"); if(lp<0) lp=h.indexOf("location:");
      if(lp<0){ Serial.println("   [4G GET] khong thay Location trong HTTPHEAD"); return status; }
      int le=h.indexOf('\n',lp); if(le<0) le=h.length();
      url=h.substring(lp+9,le); url.trim();
      Serial.printf("   [4G GET] redir len=%d -> ", url.length()); Serial.println(url);   // in FULL để soi độ dài
      continue;
    }
    if(datalen) *datalen=dl; return status;                     // 200 (hoặc lỗi khác) — dừng, GIỮ phiên cho HTTPREAD
  }
  return 0;
}
// Gửi HTTPREAD, ĐỌC TRỰC TIẾP Serial2 (không qua atWait vì atWait hút cả data),
// tiêu thụ đúng dòng header "+HTTPREAD: ...<len>\n" -> Serial2 dừng NGAY ở đầu data. Trả len. -1 lỗi.
int net4gReadStart(int want){
  Serial2.print("AT+HTTPREAD=0,"); Serial2.print(want); Serial2.print("\r\n");
  String hdr=""; unsigned long t0=millis(); bool sawTag=false, done=false;
  while(!done && millis()-t0<8000){
    while(Serial2.available()){
      char c=(char)Serial2.read(); t0=millis(); hdr+=c;
      if(!sawTag){ if(hdr.endsWith("+HTTPREAD:")){ sawTag=true; hdr=""; } }   // reset: hdr giờ chỉ phần sau tag
      else if(c=='\n'){ done=true; break; }
    }
    if(!done) delay(2);
  }
  if(!sawTag) return -1;
  int lc=hdr.lastIndexOf(','); int st=(lc>=0)?lc+1:0;
  return hdr.substring(st).toInt();                             // Serial2 giờ ở ngay đầu data
}
// Đọc 1 KHÚC body tại offset 'start' (module giới hạn ~1KB/lần), tối đa 'want' byte vào dst.
// Trả số byte thực đọc; 0 = hết; -1 lỗi. Tự nuốt marker "+HTTPREAD: 0/OK" ở cuối khúc.
int net4gReadChunk(int start, int want, uint8_t* dst){
  Serial2.print("AT+HTTPREAD="); Serial2.print(start); Serial2.print(","); Serial2.print(want); Serial2.print("\r\n");
  String hdr=""; unsigned long t0=millis(); bool sawTag=false, hd=false;
  while(!hd && millis()-t0<8000){
    while(Serial2.available()){
      char c=(char)Serial2.read(); t0=millis(); hdr+=c;
      if(!sawTag){ if(hdr.endsWith("+HTTPREAD:")){ sawTag=true; hdr=""; } }
      else if(c=='\n'){ hd=true; break; }
    }
    if(!hd) delay(2);
  }
  if(!sawTag) return -1;
  int lc=hdr.lastIndexOf(','); int st=(lc>=0)?lc+1:0; int len=hdr.substring(st).toInt();
  if(len<=0){ atWait("OK",1500); return 0; }
  int got=0; t0=millis();
  while(got<len && millis()-t0<8000){ while(Serial2.available() && got<len){ dst[got++]=(uint8_t)Serial2.read(); t0=millis(); } delay(1); }
  atWait("OK",2000);                                            // nuốt "+HTTPREAD: 0\r\nOK" cuối khúc
  return got;
}

// Lấy GIỜ MẠNG từ module 4G (AT+CCLK?) rồi set đồng hồ hệ thống — vì NTP không chạy qua AT-HTTP.
// CCLK trả giờ ĐỊA PHƯƠNG VN (do CTZU=1 + mạng Viettel +7); mktime hiểu tm là local(+7 từ configTime) -> epoch UTC.
bool syncTime4G(){
  String r = atSend("AT+CCLK?", 2000);                        // vd: +CCLK: "26/07/16,14:23:45+28"
  int q = r.indexOf('"'); if(q<0 || (int)r.length() < q+18){ return false; }
  int yy=r.substring(q+1 ,q+3 ).toInt();
  int MM=r.substring(q+4 ,q+6 ).toInt();
  int dd=r.substring(q+7 ,q+9 ).toInt();
  int hh=r.substring(q+10,q+12).toInt();
  int mm=r.substring(q+13,q+15).toInt();
  int ss=r.substring(q+16,q+18).toInt();
  int year=2000+yy;
  if(year<2024 || MM<1 || MM>12 || dd<1 || dd>31 || hh>23 || mm>59 || ss>59){
    Serial.println("[4G] CCLK chưa có giờ mạng: "+r); return false;           // NITZ chưa về -> thử lại sau
  }
  struct tm tmv={0};
  tmv.tm_year=year-1900; tmv.tm_mon=MM-1; tmv.tm_mday=dd;
  tmv.tm_hour=hh; tmv.tm_min=mm; tmv.tm_sec=ss; tmv.tm_isdst=0;
  time_t epoch=mktime(&tmv);
  struct timeval tv; tv.tv_sec=epoch; tv.tv_usec=0; settimeofday(&tv,NULL);
  Serial.printf("[4G] Đồng bộ giờ mạng (CCLK): %04d-%02d-%02d %02d:%02d:%02d\n", year,MM,dd,hh,mm,ss);
  return true;
}

// Đổi ngày-giờ UTC -> epoch (giây từ 1970), tự tính, KHÔNG phụ thuộc múi giờ (thuật toán days-from-civil).
static time_t utcToEpoch(int Y,int M,int D,int h,int m,int s){
  int yy = Y - (M <= 2 ? 1 : 0);
  long era = (yy >= 0 ? yy : yy-399) / 400;
  long yoe = yy - era*400;
  long doy = (153*(M + (M>2 ? -3 : 9)) + 2)/5 + D-1;
  long doe = yoe*365 + yoe/4 - yoe/100 + doy;
  long days = era*146097 + doe - 719468;
  return (time_t)days*86400L + h*3600L + m*60L + s;
}
// FALLBACK khi NITZ không có: lấy giờ từ header "Date:" của response Google (UTC) -> set đồng hồ.
// Mọi response HTTP (kể cả 302) đều có Date -> chỉ cần HTTPHEAD, khỏi đọc body/khỏi follow redirect.
bool syncTimeHttpDate(){
  Serial2.print("AT+HTTPTERM\r\n"); delay(120); while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(google_script_url); Serial2.print("\"\r\n"); atWait("OK",3000);
  Serial2.print("AT+HTTPACTION=0\r\n"); atWait("+HTTPACTION:",25000);
  Serial2.print("AT+HTTPHEAD\r\n"); String h=atWait("OK",6000);
  Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
  int dp=h.indexOf("Date:"); if(dp<0) dp=h.indexOf("date:");
  if(dp<0){ Serial.println("[4G] khong thay Date header"); return false; }
  int cm=h.indexOf(',',dp); if(cm<0) return false;
  String rest=h.substring(cm+1); rest.trim();                       // "16 Jul 2026 14:33:02 GMT"
  int s1=rest.indexOf(' '); if(s1<0) return false;
  int s2=rest.indexOf(' ',s1+1); if(s2<0) return false;
  int s3=rest.indexOf(' ',s2+1); if(s3<0) return false;
  int s4=rest.indexOf(' ',s3+1); if(s4<0) s4=rest.length();
  int D=rest.substring(0,s1).toInt();
  String mon=rest.substring(s1+1,s2);
  int Y=rest.substring(s2+1,s3).toInt();
  String tmv=rest.substring(s3+1,s4);                               // "14:33:02"
  const char* MN[]={"Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"};
  int MM=0; for(int i=0;i<12;i++) if(mon.startsWith(MN[i])){ MM=i+1; break; }
  int hh=tmv.substring(0,2).toInt(), mm=tmv.substring(3,5).toInt(), ss=tmv.substring(6,8).toInt();
  if(Y<2024 || MM<1 || D<1 || D>31){ Serial.println("[4G] Date parse loi: "+rest.substring(0,40)); return false; }
  struct timeval tv; tv.tv_sec=utcToEpoch(Y,MM,D,hh,mm,ss); tv.tv_usec=0; settimeofday(&tv,NULL);
  Serial.printf("[4G] Đồng bộ giờ qua HTTP Date (UTC): %04d-%02d-%02d %02d:%02d:%02d\n", Y,MM,D,hh,mm,ss);
  return true;
}

// Bật module + tự dò chiều TX/RX + baud → sẵn sàng đẩy dữ liệu qua AT-HTTP (KHÔNG dùng PPP).
bool net4gConnect(){
  g_4gReady = false;
  modemPowerOn();
  long bauds[]={115200, 9600}; bool found=false;                  // dò cả 2 baud (module có thể còn 9600 từ lần trước)
  for(int bi=0; bi<2 && !found; bi++){
    if (atProbe(SIM_TX_PIN, SIM_RX_PIN, bauds[bi])) { g_simTx=SIM_TX_PIN; g_simRx=SIM_RX_PIN; g_simBaud=bauds[bi]; found=true; }
    else if (atProbe(SIM_RX_PIN, SIM_TX_PIN, bauds[bi])) { g_simTx=SIM_RX_PIN; g_simRx=SIM_TX_PIN; g_simBaud=bauds[bi]; found=true; }
  }
  if(!found){ Serial.println("[4G] Module KHÔNG trả AT (115200/9600) -> kiểm NGUỒN / PWRKEY(GPIO17) / dây / SIM"); return false; }
  Serial.printf("[4G] AT OK: esp_tx=%d esp_rx=%d @%ld\n", g_simTx, g_simRx, g_simBaud);
  Serial2.begin(g_simBaud, SERIAL_8N1, g_simRx, g_simTx); delay(300);   // MỞ Serial2 và GIỮ MỞ cho AT-HTTP
  bool reg = net4gDiag();
  if (reg) {
    g_4gReady = true; Serial.println("[4G] SẴN SÀNG (đã đăng ký LTE) — đẩy dữ liệu qua AT-HTTP");
    static bool tested = false;
    if (!tested) { tested = true;                              // đẩy thử 1 gói để XÁC NHẬN đường 4G→Google (không cần Hikvision)
      String tb = String("{\"macAddress\":\"") + WiFi.macAddress() + "\",\"stationName\":\"" + String(STATION_NAME) + "\",\"employeeNo\":\"TEST4G\",\"name\":\"AT-HTTP test\",\"time\":\"test\",\"image\":\"\"}";
      Serial.println("[4G] === TEST: đẩy thử 1 gói lên Google ===");
      bool tok = net4gHttpPost(google_script_url, tb);
      Serial.println(tok ? "[4G] ✔️ TEST OK — đường 4G → Google CHẠY!" : "[4G] ✖ TEST chưa được — xem 'status=' phía trên");
    }
    return true;
  }
  Serial.println("[4G] Chưa đăng ký mạng (CEREG) — chờ sóng / thử lại sau");
  return false;
}

void handleRoot(){
  lastWebMs = millis();
  String staTxt = USE_4G
      ? (g_4gReady ? ("4G sẵn sàng · " + String(STATION_NAME) + " (đẩy qua AT-HTTP)") : "4G CHƯA kết nối (soi Serial)")
      : ((WiFi.status()==WL_CONNECTED) ? ("Đã kết nối · IP LAN: " + WiFi.localIP().toString()) : "CHƯA kết nối WiFi cửa hàng");
  String apName = "CHAM_CONG";        // CỐ ĐỊNH — xem ghi chú ở startAP()
  String h; h.reserve(8500);
  h += "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  h += "<title>Chấm công " + String(STATION_NAME) + "</title><style>";
  h += "body{font-family:system-ui,Arial;margin:0;background:#0f172a;color:#e2e8f0;padding:14px}";
  h += "h2{color:#38bdf8;margin:8px 0;font-size:17px}.card{background:#1e293b;border-radius:12px;padding:14px;margin-bottom:14px}";
  h += "input,button{font-size:16px;padding:10px;border-radius:8px;border:1px solid #334155;margin:5px 0;width:100%;box-sizing:border-box;background:#0f172a;color:#e2e8f0}";
  h += "button{background:#2563eb;border:0;font-weight:700;cursor:pointer}.b2{background:#dc2626;width:auto;padding:5px 10px;margin:0}";
  h += "table{width:100%;border-collapse:collapse;font-size:14px}td,th{padding:6px;border-bottom:1px solid #334155;text-align:left}.muted{color:#94a3b8;font-size:13px}</style></head><body>";
  h += "<h2>🕒 Chấm công — " + String(STATION_NAME) + "</h2>";
  h += "<div class='muted'>Mạng: " + staTxt + " · AP: " + apName + " · Hik: " + String(hik_ip) + "</div>";
  h += "<div class='card'><h2>🏪 Cơ sở của máy này</h2>";
  h += "<div class='muted'>Cơ sở hiện tại: <b>" + String(STATION_NAME) + "</b> — "
       + String(STATION_TU_SERVER ? "do <b>server gán theo mã máy</b> (đúng cách dùng)."
                                  : "<b>bản nhớ trong máy</b>; server chưa gán hoặc chưa hỏi được.") + "</div>";
  h += "<div class='muted'>Mã máy để server nhận ra cơ sở — <b>serial đầu đọc:</b> "
       + String(HIK_SERIAL.length() ? HIK_SERIAL : String("(chưa đọc được)"))
       + (HIK_MODEL.length() ? (" · " + HIK_MODEL) : "") + " · <b>MAC bo:</b> " + WiFi.macAddress() + "</div>";
  h += "<div class='muted'>👉 <b>Không cần gõ tên ở đây nữa.</b> Vào web app &gt; tab <b>Máy chấm công</b>, "
       "tìm máy theo serial/MAC ở trên rồi chọn cơ sở. Máy tự nhận trong 30 phút (hoặc khởi động lại cho nhanh). "
       "Đổi bo ESP32 mà giữ đầu đọc thì <b>không phải khai lại gì</b>.</div>";
  // ---- Khai bí mật + 2 link (lưu vào NVS; OTA không ghi đè NVS) ----
  h += "<div class='card' " + String(g_chuaCauHinh ? "style='border:2px solid #ef4444'" : "") + "><h2>"
       + String(g_chuaCauHinh ? "⚠️ MÁY CHƯA CẤU HÌNH ĐỦ" : "🔐 Cấu hình máy") + "</h2>";
  h += "<div class='muted'>Bản firmware do CI build KHÔNG chứa bí mật (cố ý, để file .bin đặt chỗ tải "
       "công khai được mà không lộ Firebase secret). Máy lấy bí mật từ bộ nhớ trong — <b>cập nhật firmware "
       "KHÔNG làm mất</b>. Bỏ trống ô nào là <b>giữ nguyên</b> giá trị đang có.</div>";
  /* ⚠️ HAI LINK HIỆN NGUYÊN VĂN, cố ý. Trước đây che bằng cfgChe() nên chỉ thấy 4 ký tự đầu —
     mà 4 ký tự đầu của mọi link đều là "http", tức là che xong thì KHÔNG CÒN CÁCH NÀO biết máy
     đang giữ link đúng hay link cũ. Đã trả giá: máy đẩy chấm công ra 404 mà soi mãi không ra.
     Link không phải bí mật (đã có token gác đường /exec, và Firebase có secret riêng), còn portal
     thì nằm sau mật khẩu AP. Token/secret/mật khẩu vẫn che như cũ. */
  h += "<div class='muted'>Đang có:<br>link web app <b style='word-break:break-all'>"
       + (_cfgExecUrl.length() ? _cfgExecUrl : String("(trống)")) + "</b><br>link Firebase <b style='word-break:break-all'>"
       + (_cfgFbHost.length() ? _cfgFbHost : String("(trống)"))
       + "</b><br>token web app <b>" + cfgChe(_cfgEmpTok) + "</b> · Firebase secret <b>" + cfgChe(_cfgFbSec)
       + "</b> · mật khẩu Hikvision <b>" + cfgChe(_cfgHikPass) + "</b> · mật khẩu /update <b>" + cfgChe(_cfgOtaPass)
       + "</b> · mật khẩu AP <b>" + cfgChe(_cfgApPass) + "</b></div>";
  h += "<input id='cExec'  placeholder='Link web app /exec (dạng /macros/s/…/exec)'>";
  h += "<input id='cFb'    placeholder='Link Firebase RTDB (không có / ở cuối)'>";
  h += "<input id='cTok'   placeholder='Token web app (EMP_TOKEN)'>";
  h += "<input id='cFbSec' placeholder='Firebase database secret (để trống = gọi không auth)'>";
  h += "<input id='cHikU'  placeholder='Tài khoản Hikvision (mặc định admin)'>";
  h += "<input id='cHikP'  placeholder='Mật khẩu Hikvision'>";
  h += "<input id='cOtaU'  placeholder='Tài khoản trang /update (mặc định admin)'>";
  h += "<input id='cOtaP'  placeholder='Mật khẩu trang /update'>";
  h += "<input id='cApP'   placeholder='Mật khẩu AP máy này (tối thiểu 8 ký tự)'>";
  h += "<button onclick='saveCfg()'>🔐 Lưu cấu hình & khởi động lại</button>";
  h += "<div class='muted'>⚠️ Mật khẩu AP và tài khoản /update phải KHỚP máy thợ nạp, lệch là thợ nạp không vào được.</div></div>";

  h += "<details><summary class='muted'>Đặt tay tên cơ sở (chỉ dùng khi server chưa gán được)</summary>";
  h += "<input id='stn' value='" + String(STATION_NAME) + "' placeholder='VD: FZ_LTVT'>";
  h += "<button onclick='saveStation()'>💾 Lưu tên cơ sở & khởi động lại</button>";
  h += "<div class='muted'>Đặt tay chỉ có tác dụng tới lần hỏi server kế tiếp: máy đã được gán thì <b>bảng ở server thắng</b>.</div>";
  h += "</details></div>";
  h += "<div class='card'><h2>➕ Thêm / cập nhật nhân viên</h2>";
  h += "<input id='no' placeholder='Mã nhân viên (ID)'><input id='nm' placeholder='Họ tên'><input id='pin' placeholder='PIN (tùy chọn)'>";
  h += "<button onclick='addEmp()'>💾 Lưu vào máy chấm công</button><div id='amsg' class='muted'></div>";
  h += "<div class='muted'>Portal thêm ID + tên. Khuôn mặt đăng ký trực tiếp tại máy (hoặc qua đồng bộ trung tâm).</div></div>";
  h += "<div class='card'><h2>👥 Nhân viên trên máy <button style='width:auto;padding:5px 10px' onclick='loadEmp()'>↻ Tải</button></h2><div id='emp'>Bấm ↻ Tải…</div></div>";
  h += "<div class='card'><h2>📶 Cấu hình WiFi cửa hàng</h2>";
  h += "<input id='ss' placeholder='Tên WiFi (SSID)'><input id='pw' placeholder='Mật khẩu WiFi'>";
  h += "<button onclick='saveWifi()'>Lưu & khởi động lại</button></div>";
  h += "<div class='card'><h2>⬆️ Cập nhật firmware (OTA qua WiFi)</h2>";
  h += "<div class='muted'>Nạp file .bin mới qua WiFi này — không cần máy tính/cáp.</div>";
  h += "<a href='/update'><button type='button'>Mở trang cập nhật →</button></a></div>";
  h += "<script>function g(i){return document.getElementById(i);}";
  h += "function loadEmp(){g('emp').innerHTML='Đang tải…';fetch('/emp').then(r=>r.json()).then(a=>{";
  h += "if(!a.length){g('emp').innerHTML='<span class=muted>Không có / chưa tới được máy chấm công.</span>';return;}";
  h += "let t='<table><tr><th>ID</th><th>Tên</th><th></th></tr>';";
  h += "a.forEach(u=>{t+=`<tr><td>${u.no}</td><td>${u.name}</td><td><button class=b2 data-no='${u.no}'>Xóa</button></td></tr>`;});";
  h += "g('emp').innerHTML=t+'</table>';g('emp').querySelectorAll('.b2').forEach(b=>b.onclick=()=>delEmp(b.getAttribute('data-no')));";
  h += "}).catch(_=>{g('emp').innerHTML='Lỗi tải danh sách';});}";
  h += "function addEmp(){let no=g('no').value.trim(),nm=g('nm').value.trim(),pin=g('pin').value.trim();";
  h += "if(!no||!nm){alert('Nhập ID và tên');return;}g('amsg').textContent='Đang lưu…';";
  h += "fetch('/addemp',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'no='+encodeURIComponent(no)+'&name='+encodeURIComponent(nm)+'&pin='+encodeURIComponent(pin)}).then(r=>r.text()).then(t=>{g('amsg').textContent=t;g('no').value='';g('nm').value='';g('pin').value='';loadEmp();});}";
  h += "function delEmp(no){if(!confirm('Xóa nhân viên '+no+'?'))return;fetch('/delemp',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'no='+encodeURIComponent(no)}).then(r=>r.text()).then(t=>{alert(t);loadEmp();});}";
  h += "function saveWifi(){let s=g('ss').value.trim();if(!s){alert('Nhập SSID');return;}fetch('/savewifi',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'ssid='+encodeURIComponent(s)+'&pass='+encodeURIComponent(g('pw').value)}).then(r=>r.text()).then(t=>alert(t));}";
  h += "function saveCfg(){let f=['cExec','cFb','cTok','cFbSec','cHikU','cHikP','cOtaU','cOtaP','cApP'];"
       "let b=[],co=0;f.forEach(function(k){let v=g(k).value.trim();if(v!==''){co++;b.push(k+'='+encodeURIComponent(v));}});"
       "if(!co){alert('Chua nhap o nao. Bo trong = giu nguyen.');return;}"
       "if(!confirm('Luu cau hinh vao bo nho trong va khoi dong lai?'))return;"
       "fetch('/savecfg',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.join('&')}).then(r=>r.text()).then(t=>alert(t));}";
  h += "function saveStation(){let s=g('stn').value.trim();if(!s){alert('Nhập tên cơ sở');return;}if(!confirm('Đổi tên cơ sở thành \"'+s+'\" và khởi động lại?'))return;fetch('/savestation',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'station='+encodeURIComponent(s)}).then(r=>r.text()).then(t=>alert(t));}";
  h += "loadEmp();</script></body></html>";
  server.send(200, "text/html; charset=utf-8", h);
}

// Danh sách nhân viên trên máy (ISAPI UserInfo/Search) -> JSON [{no,name}]
void handleEmpList(){
  lastWebMs = millis();
  if (!hikUp()) { server.send(200,"application/json","[]"); return; }
  String body = "{\"UserInfoSearchCond\":{\"searchID\":\"cyd\",\"searchResultPosition\":0,\"maxResults\":50}}";
  int code; String r = hikRequest("POST","/ISAPI/AccessControl/UserInfo/Search?format=json", body, &code);
  String out = "[";
  StaticJsonDocument<192> filter;                          // chỉ giữ employeeNo + name -> doc nhỏ, không tràn RAM khi nhiều NV
  filter["UserInfoSearch"]["UserInfo"][0]["employeeNo"] = true;
  filter["UserInfoSearch"]["UserInfo"][0]["name"] = true;
  DynamicJsonDocument d(8192);
  if (!deserializeJson(d, r, DeserializationOption::Filter(filter))) {
    JsonArray arr = d["UserInfoSearch"]["UserInfo"].as<JsonArray>();
    bool first = true;
    for (JsonObject u : arr) {
      String no = u["employeeNo"] | ""; String nm = u["name"] | "";
      if (no.length()==0) continue;
      if (!first) out += ","; first = false;
      out += "{\"no\":\"" + jsonEsc(no) + "\",\"name\":\"" + jsonEsc(nm) + "\"}";
    }
  }
  out += "]";
  server.send(200, "application/json; charset=utf-8", out);
}

void handleAddEmp(){
  String emp=server.arg("no"), name=server.arg("name"), pin=server.arg("pin");
  emp.trim(); name.trim();
  if (emp.length()==0 || name.length()==0) { server.send(400,"text/plain; charset=utf-8","Thiếu ID hoặc tên"); return; }
  if (!hikUp())                             { server.send(503,"text/plain; charset=utf-8","Chưa tới được máy chấm công (kiểm Hikvision đã nối AP/LAN chưa)"); return; }
  showSync("add", name);
  String msg; bool ok = upsertUser(emp, name, pin, "add", "", msg);   // portal ESP: không có giới tính
  statusUntil = millis() + 3000;
  server.send(ok?200:500, "text/plain; charset=utf-8", ok ? ("✔ Đã lưu NV " + emp + " (" + msg + ")") : ("✖ Lỗi: " + msg));
}

void handleDelEmp(){
  String emp=server.arg("no"); emp.trim();
  if (emp.length()==0) { server.send(400,"text/plain; charset=utf-8","Thiếu ID"); return; }
  if (!hikUp()) { server.send(503,"text/plain; charset=utf-8","Chưa tới được máy chấm công"); return; }
  int code;
  String delBody = "{\"UserInfoDetail\":{\"mode\":\"byEmployeeNo\",\"EmployeeNoList\":[{\"employeeNo\":\"" + emp + "\"}]}}";
  String r = hikRequest("PUT","/ISAPI/AccessControl/UserInfoDetail/Delete?format=json", delBody, &code);
  bool ok = hikOk(r) || r.indexOf("NotExist")>=0;
  if (ok) pollDeleteProcess();
  faceDelete(emp);
  server.send(ok?200:500, "text/plain; charset=utf-8", ok ? ("✔ Đã xóa NV " + emp) : ("✖ Lỗi xóa: " + shortResp(r,code)));
}

// CHẨN ĐOÁN: dump TOÀN BỘ cấu hình 1 user trên máy (doorRight/RightPlan/Valid...) -> so user máy-tạo vs ESP-tạo.
// Dùng: mở 192.168.4.1/userraw?no=<mã NV>
void handleUserRaw(){
  lastWebMs = millis();
  String no = server.arg("no"); no.trim();
  if (no.length()==0) { server.send(400,"text/plain; charset=utf-8","Thêm ?no=<mã NV>, vd /userraw?no=1"); return; }
  if (!hikUp()) { server.send(503,"text/plain; charset=utf-8","Chưa tới được máy chấm công"); return; }
  String body = "{\"UserInfoSearchCond\":{\"searchID\":\"raw\",\"searchResultPosition\":0,\"maxResults\":1,\"EmployeeNoList\":[{\"employeeNo\":\"" + no + "\"}]}}";
  int code; String r = hikRequest("POST","/ISAPI/AccessControl/UserInfo/Search?format=json", body, &code);
  server.send(200,"text/plain; charset=utf-8", "HTTP " + String(code) + "\n" + r);
}

void handleSaveWifi(){
  String s=server.arg("ssid"), p=server.arg("pass"); s.trim();
  if (s.length()==0) { server.send(400,"text/plain; charset=utf-8","Thiếu SSID"); return; }
  prefs.putString("wifi_ssid", s); prefs.putString("wifi_pass", p);
  server.send(200,"text/plain; charset=utf-8","Đã lưu WiFi \"" + s + "\". Thiết bị khởi động lại...");
  delay(900); ESP.restart();
}

/* Khai bí mật + 2 link vào NVS. Ô nào KHÔNG gửi lên thì GIỮ NGUYÊN — cố ý, để sửa 1 mật
   khẩu không phải gõ lại cả 9 ô (gõ lại là dịp làm sai). NVS sống qua OTA. */
void handleSaveCfg(){
  struct { const char* arg; const char* khoa; } m[] = {
    {"cExec","execUrl"}, {"cFb","fbHost"}, {"cTok","empTok"}, {"cFbSec","fbSec"},
    {"cHikU","hikUser"}, {"cHikP","hikPass"}, {"cOtaU","otaUser"}, {"cOtaP","otaPass"}, {"cApP","apPass"}
  };
  int n = 0; String loi = "";
  for (unsigned i = 0; i < sizeof(m)/sizeof(m[0]); i++) {
    if (!server.hasArg(m[i].arg)) continue;
    String v = server.arg(m[i].arg); v.trim();
    if (!v.length()) continue;
    if (String(m[i].khoa) == "apPass" && v.length() < 8) { loi += "Mat khau AP phai >= 8 ky tu. "; continue; }
    if (String(m[i].khoa) == "execUrl" && v.indexOf("/macros/s/") < 0)
      { loi += "Link web app phai dang /macros/s/<id>/exec. "; continue; }
    if (String(m[i].khoa) == "fbHost") {
      while (v.endsWith("/")) v.remove(v.length()-1);          // dan tu Console hay dinh "/"
      // Câu này KHÔNG được chứa mẫu CI đang quét ('default-rtdb'), nên tả bằng lời.
      if (!fbHostHopLe(v)) { loi += "Link Firebase phai bat dau https:// va ket thuc .firebasedatabase.app "; continue; }
    }
    prefs.putString(m[i].khoa, v); n++;
  }
  if (loi.length()) { server.send(400, "text/plain; charset=utf-8", "KHONG luu: " + loi); return; }
  server.send(200, "text/plain; charset=utf-8", "Da luu " + String(n) + " gia tri. May khoi dong lai...");
  Serial.printf("[CFG] portal luu %d gia tri -> khoi dong lai\n", n);
  delay(600); ESP.restart();
}

// Đặt TÊN CƠ SỞ cho máy này (lưu Preferences 'station') -> dùng chung 1 file .bin
void handleSaveStation(){
  String s=server.arg("station"); s.trim();
  if (s.length()==0) { server.send(400,"text/plain; charset=utf-8","Thiếu tên cơ sở"); return; }
  prefs.putString("station", s);
  server.send(200,"text/plain; charset=utf-8","Đã đặt cơ sở \"" + s + "\". Thiết bị khởi động lại...");
  delay(900); ESP.restart();
}

// ===== OTA qua WiFi AP nội bộ: nạp .bin qua trình duyệt (Update.h) =====
void handleUpdatePage(){
  lastWebMs = millis();
  if (_cfgOtaPass.length() == 0) { server.send(503, "text/plain; charset=utf-8",
    "Chua khai mat khau /update (may chua cau hinh). Vao 192.168.4.1 khai cau hinh truoc."); return; }
  if (!server.authenticate(OTA_USER, OTA_PASS)) return server.requestAuthentication();
  String h="<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  h+="<body style='font-family:system-ui,Arial;background:#0f172a;color:#e2e8f0;padding:16px'>";
  h+="<h2 style='color:#38bdf8'>⬆️ Cập nhật firmware — " + String(STATION_NAME) + "</h2>";
  h+="<p style='color:#94a3b8;font-size:14px'>Chọn file <b>.bin</b> rồi bấm Nạp. <b>KHÔNG tắt nguồn</b> khi đang nạp. Nạp xong máy tự khởi động lại.</p>";
  h+="<form method='POST' action='/update' enctype='multipart/form-data'>";
  h+="<input type='file' name='fw' accept='.bin' style='width:100%;padding:10px;margin:8px 0;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:8px'>";
  h+="<button type='submit' style='width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:700;font-size:16px'>🚀 Nạp firmware</button></form>";
  h+="<p style='color:#94a3b8;font-size:12px'>Bản hiện tại: " FW_VERSION "</p><p><a href='/' style='color:#38bdf8'>← Về trang chính</a></p></body>";
  server.send(200,"text/html; charset=utf-8", h);
}
void handleUpdateDone(){
  if (_cfgOtaPass.length() == 0) { server.send(503, "text/plain; charset=utf-8",
    "Chua khai mat khau /update (may chua cau hinh). Vao 192.168.4.1 khai cau hinh truoc."); return; }
  if (!server.authenticate(OTA_USER, OTA_PASS)) return server.requestAuthentication();
  bool ok=!Update.hasError();
  server.send(200,"text/html; charset=utf-8", String("<!doctype html><meta charset='utf-8'><body style='font-family:system-ui;padding:20px;font-size:16px'>")+
    (ok?"✅ <b>Nạp firmware THÀNH CÔNG.</b> Thiết bị đang khởi động lại bản mới…":"❌ <b>Nạp LỖI.</b> Kiểm tra lại file .bin rồi thử lại.")+"</body>");
  delay(900);
  if (ok) ESP.restart();
}
void handleUpdateUpload(){
  lastWebMs = millis();
  HTTPUpload& up = server.upload();
  if (up.status==UPLOAD_FILE_START){
    if (_cfgOtaPass.length() == 0) { Serial.println("[OTA] Từ chối upload: máy chưa khai mật khẩu /update"); return; }
    if (!server.authenticate(OTA_USER, OTA_PASS)) { Serial.println("[OTA] Từ chối upload: chưa đăng nhập"); return; }   // chặn ghi firmware nếu chưa auth
    Serial.printf("[OTA] Bắt đầu nạp: %s\n", up.filename.c_str());
    idleActive=false; tft.fillScreen(TFT_BLACK); veKhung(); tft.setTextDatum(MC_DATUM); tft.setTextColor(TFT_ORANGE,TFT_BLACK); tft.drawString("DANG NAP FIRMWARE...", 160, 120, 4);
    if (!Update.begin(UPDATE_SIZE_UNKNOWN)) Update.printError(Serial);
  } else if (up.status==UPLOAD_FILE_WRITE){
    if (Update.write(up.buf, up.currentSize)!=up.currentSize) Update.printError(Serial);
  } else if (up.status==UPLOAD_FILE_END){
    if (Update.end(true)) Serial.printf("[OTA] Xong %u byte\n", up.totalSize); else Update.printError(Serial);
  }
}

void startPortal(){
  server.on("/", handleRoot);
  server.on("/emp", HTTP_GET, handleEmpList);
  server.on("/addemp", HTTP_POST, handleAddEmp);
  server.on("/delemp", HTTP_POST, handleDelEmp);
  server.on("/savewifi", HTTP_POST, handleSaveWifi);
  server.on("/savestation", HTTP_POST, handleSaveStation);
  server.on("/savecfg", HTTP_POST, handleSaveCfg);
  server.on("/userraw", HTTP_GET, handleUserRaw);   // chẩn đoán: dump cấu hình 1 user
  server.on("/update", HTTP_GET, handleUpdatePage);
  server.on("/update", HTTP_POST, handleUpdateDone, handleUpdateUpload);   // POST: completion + upload handler (Update.h)
  server.onNotFound([](){ server.sendHeader("Location","http://192.168.4.1/"); server.send(302,"text/plain",""); });
  server.begin();
  Serial.println("[WEB] portal @ / (AP 192.168.4.1 + IP LAN)");
}

void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n\n================ FIRMWARE " FW_VERSION " ================");   // nhìn dòng này để chắc đã nạp bản mới

  // Màn hình
  pinMode(BL_PIN, OUTPUT);
  digitalWrite(BL_PIN, HIGH);
  tft.init();
  tft.setRotation(1);                 // ngang 320x240
  tft.fillScreen(TFT_BLACK);
  TJpgDec.setSwapBytes(true);
  TJpgDec.setCallback(tftJpgOutput);

  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.drawString("Dang ket noi WiFi...", 160, 120, 2);

  prefs.begin("chamcong", false);
  napCauHinh();   // bí mật + 2 link: NVS trước, giá trị compile là dự phòng (và tự chép vào NVS)
  { String _st = prefs.getString("station", ""); if (_st.length()) STATION_NAME = _st; }   // tên cơ sở đặt qua portal (1 file .bin cho mọi máy)
  lastSerialNo = prefs.getLong("lastSN", -1);
  lastSyncTime = prefs.getString("lastSyncT", "");   // mốc đồng bộ cuối -> bù khi khởi động
  Serial.print("-> lastSerialNo nạp từ flash: "); Serial.println(lastSerialNo);
  Serial.print("-> lastSyncTime nạp từ flash: "); Serial.println(lastSyncTime);

  if (USE_4G) {
    WiFi.mode(WIFI_AP);              // chỉ phát AP cho Hikvision + điện thoại; Internet đi qua 4G
    startAP();                       // AP "CHAM_CONG" @ 192.168.4.1 (Hik nối vào với IP tĩnh 192.168.4.50)
    tft.drawString("Bat 4G...", 160, 145, 2);
    bool ok4g = net4gConnect();      // bật A7680C + PPP (Viettel v-internet)
    Serial.println(ok4g ? "✔️ 4G online" : "✖ 4G chưa online (kiểm SIM/sóng/nguồn)");
  } else {
    WiFi.mode(WIFI_AP_STA);          // nối WiFi cửa hàng (STA) + AP cấu hình song song
    startAP();
    bool wok = connectSTA(20000);
    Serial.println(wok ? ("\n✔️ WiFi cửa hàng: " + WiFi.localIP().toString()) : "\n✖ Chưa nối WiFi cửa hàng — vào 192.168.4.1 để cấu hình");
  }
  // MÃ THIẾT BỊ -> hỏi server tên cơ sở. Làm TRƯỚC khi in "Cơ sở" và trước phần bù dữ liệu,
  // vì mọi đường Firebase (/queue, /hb, /roster, /photo) đều mang tên cơ sở.
  docThongTinDauDoc();
  hoiCuaHang();
  lastWhoAmIMs = millis();
  Serial.print("-> Cơ sở: "); Serial.print(STATION_NAME);
  Serial.println(STATION_TU_SERVER ? "  (server gán theo mã máy)" : "  (bản nhớ trong máy — server chưa gán hoặc chưa hỏi được)");
  startPortal();                     // web quản lý nhân viên + cấu hình

  // Đồng bộ giờ cho đồng hồ màn chờ: set múi giờ GMT+7 rồi lấy giờ.
  // - WiFi: NTP (pool.ntp.org).  - 4G (AT-HTTP): NTP KHÔNG chạy -> lấy giờ mạng từ module (AT+CCLK?).
  configTime(GMT_OFFSET_SEC, DST_OFFSET_SEC, NTP_SERVER1, NTP_SERVER2);
  if (USE_4G && g_4gReady && (syncTime4G() || syncTimeHttpDate())) g_timeSynced = true;   // thử ngay; chưa được thì loop() thử lại

  logFdLib();   // in thông tin thư viện khuôn mặt (xác nhận FDID/blackFD)

  // ① Bù dữ liệu bỏ lỡ khi máy tắt/mất mạng: kéo từ mốc đồng bộ cuối đến hiện tại (Google tự chống trùng).
  if (netUp() && lastSyncTime.length() >= 19) {
    String startISO = lastSyncTime; startISO.replace(" ", "T"); startISO += "+07:00";
    Serial.println("[KHỞI ĐỘNG] Bù dữ liệu bỏ lỡ từ " + lastSyncTime + " ...");
    showBackfillProgress(0);
    int p = backfillRange(startISO, FAR_FUTURE, !USE_4G, "");   // WiFi: kèm ảnh; 4G: chỉ giờ
    Serial.printf("[KHỞI ĐỘNG] Đã bù %d lượt.\n", p);
  }
  lastHbMs = 0; lastSafetyBackfill = millis();   // heartbeat ngay chu kỳ đầu; hoãn bù định kỳ 1 nhịp

  showIdle();   // màn chờ = đồng hồ số
}

void loop() {
  server.handleClient();             // phục vụ web portal (192.168.4.1 + IP LAN)
  dnsServer.processNextRequest();    // captive portal cho AP

  // Hết thời gian giữ "cảm ơn" -> quay về màn chờ (đồng hồ)
  if (statusUntil && millis() > statusUntil) showIdle();

  // 4G: NTP không chạy qua AT-HTTP -> lấy giờ từ module (CCLK/NITZ); nếu mạng không gửi NITZ thì fallback HTTP Date
  if (USE_4G && g_4gReady && !g_timeSynced) {
    static unsigned long lastTimeTry = 0;
    if (millis() - lastTimeTry >= 15000) { lastTimeTry = millis(); if (syncTime4G() || syncTimeHttpDate()) g_timeSynced = true; }
  }

  // Cập nhật đồng hồ màn chờ (mỗi ~250ms, chỉ vẽ khi giây đổi)
  static unsigned long lastClockTick = 0;
  if (idleActive && millis() - lastClockTick >= 250) {
    lastClockTick = millis();
    updateClock();
  }

  // Đang dùng portal/OTA (có request web trong 15s gần đây) -> tạm ngưng poll máy/4G để web trả trang MƯỢT
  bool webBusy = (lastWebMs && millis() - lastWebMs < 15000);

  if (!webBusy && millis() - lastPoll >= POLL_INTERVAL_MS) {
    lastPoll = millis();
    if (netUp()) { wifiDownSince = 0; checkNewAcsEvents(); }
    else {
      if (wifiDownSince == 0) wifiDownSince = millis();
      if (USE_4G) { if (millis() - wifiDownSince > 60000) { wifiDownSince = millis(); net4gConnect(); } }   // 4G rớt >60s -> thử quay PPP lại
      else WiFi.begin(cfgSsid.c_str(), cfgPass.c_str());                                                    // WiFi: nối lại (AP vẫn bật để cấu hình @192.168.4.1)
    }
  }

  // Poll hàng đợi nhân viên (chu kỳ dài hơn để không tranh với poll chấm công)
  if (!webBusy && millis() - empLastPoll >= EMP_POLL_MS) {
    empLastPoll = millis();
    if (netUp()) checkEmployeeQueue();
  }

  // ② Heartbeat online mỗi 60s -> dashboard hiện 🟢/🔴 theo cơ sở
  if (!webBusy && netUp() && millis() - lastHbMs >= HB_INTERVAL_MS) {
    lastHbMs = millis();
    hbSend();
  }

  // ②b Hỏi lại cơ sở mỗi 30': anh đổi/gán máy trong web app thì máy tự theo, KHÔNG cần khởi động lại.
  //     Chưa đọc được serial đầu đọc lúc khởi động thì đây cũng là cơ hội đọc lại.
  if (!webBusy && netUp() && millis() - lastWhoAmIMs >= WHOAMI_CHU_KY_MS) {
    lastWhoAmIMs = millis();
    if (HIK_SERIAL.length() == 0) docThongTinDauDoc();
    hoiCuaHang();
  }

  // ③ OTA từ xa: kiểm tra firmware mới trên Firebase /ota mỗi 5 phút (tải + flash khi có bản mới)
  if (!webBusy && netUp() && millis() - lastOtaCheck >= OTA_CHECK_MS) {
    lastOtaCheck = millis();
    checkOtaUpdate();
  }

  // ① Lưới an toàn: định kỳ bù lượt sót (mỗi 30 phút) từ mốc đồng bộ cuối -> không lo mất khi 4G chập chờn
  if (!webBusy && netUp() && lastSyncTime.length() >= 19 && millis() - lastSafetyBackfill >= SAFETY_BACKFILL_MS) {
    lastSafetyBackfill = millis();
    String startISO = lastSyncTime; startISO.replace(" ", "T"); startISO += "+07:00";
    Serial.println("[ĐỊNH KỲ] Bù lượt sót từ " + lastSyncTime);
    backfillRange(startISO, FAR_FUTURE, false, "");
    showIdle();
  }
}
