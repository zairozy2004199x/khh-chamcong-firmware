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
#include "esp_mac.h"      // esp_read_mac: MAC bo doc tu efuse, khong phu thuoc WiFi da bat chua
//#define LOAD_FONT7
#define LOAD_FONT6
// ======================= CẤU HÌNH =======================
/* ⚠️ FW_VERSION bị nhồi NGUYÊN VĂN vào JSON của heartbeat (hbSend) và vào HTML portal.
   Bản 31b từng để dấu nháy kép trong đây -> thân JSON hỏng -> máy chủ trả 400, mất nhịp sống,
   web app báo máy offline dù máy đang chạy. ĐỪNG dùng " \ hay ký tự điều khiển trong chuỗi này.
   Chỗ ghi JSON nay cũng đã escape (jsonEscMin_), nhưng giữ chuỗi sạch vẫn là tuyến phòng thứ nhất. */
#define FW_VERSION "2026-08-22a (chay thang tren host - bo Apps Script va Firebase)"  // đổi mỗi lần sửa -> nhìn boot log biết bản nào đang chạy

// ---- BÍ MẬT: nằm ở secrets.h (KHÔNG commit — .gitignore có mẫu `secrets.*`) ----
// Chưa có file thì copy secrets.example.h -> secrets.h rồi điền. Build BÁO LỖI nếu thiếu,
// cố ý: thà không build được còn hơn nạp firmware bằng mật khẩu mẫu.
// ⚠️ Các giá trị CŨ đã từng nằm trong repo nên coi như ĐÃ LỘ (git giữ lịch sử vĩnh viễn).
//    Tách file KHÔNG tự làm chúng an toàn — phải ĐỔI khoá máy (VHCC_KHOA_MAY) + mật khẩu Hikvision.
#if !__has_include("secrets.h")
  #error "Thieu secrets.h — copy secrets.example.h thanh secrets.h roi dien gia tri that."
#endif
#include "secrets.h"

/* ⚠️ KHAI BÁO TRƯỚC — PHẢI ĐẶT Ở ĐÂY, ĐỪNG DỜI XUỐNG GIỮA FILE.
   Arduino/arduino-cli tự sinh prototype cho hàm trong .ino, NHƯNG nếu file đã có sẵn một
   khai báo thì nó THÔI tự sinh. Đặt khai báo ở giữa file là mọi chỗ dùng phía TRÊN nó
   mất khai báo -> build đỏ. Đã bị đúng lỗi này: `bool netUp();` đặt ở dòng ~640 làm
   drawNetStatus() (dòng 238) báo "'netUp' was not declared in this scope". */
/* Giá trị "chưa khai" dùng chung cho mọi khoá cấu hình. PHẢI đứng trước SEC_WP_URL /
   SEC_WP_URL ở dưới — macro chỉ nở ra lúc DÙNG, nên định nghĩa sau là lỗi compile. */
#define CFG_PLACEHOLDER "__CHUA_CAU_HINH__"

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

/* IP Hikvision. 4G -> máy Hik nối AP ESP32 với IP TĨNH này (gateway 192.168.4.1);
   WiFi -> IP của đầu đọc trên LAN cửa hàng.
   🔴 03/08/2026 — TRƯỚC ĐÂY đây là HẰNG SỐ, không khai được ở portal. Máy mới ở FZ_SC_VIVO_T4
      đọc không ra đầu đọc (serial trống, Model trống, quét roster 0 NV, tải lại 0 lượt) mà
      KHÔNG CÁCH NÀO sửa từ xa — phải mang laptop ra nạp lại. Mọi bí mật/link khác đều khai được
      ở portal, riêng ô quan trọng nhất khi lắp máy mới thì không. Nay khai được như các ô kia. */
const char* HIK_IP_MAC_DINH = USE_4G ? "192.168.4.50" : "192.168.4.1";
const char* hik_ip   = HIK_IP_MAC_DINH;
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

/* ===========================================================================================
 *  MỘT ĐƯỜNG DUY NHẤT: WORDPRESS  (22/08/2026)
 * -------------------------------------------------------------------------------------------
 *  Anh Thắng: *"hệ thống chạy hết trực tiếp trên host luôn, cả đường máy chấm công"*, và
 *  *"làm chuẩn 1 website, chứ không liên quan gì đến google sheet nữa"*.
 *
 *  Trước bản này máy nói chuyện với BA nơi:
 *      Apps Script /exec   -> đẩy lượt chấm công vào sheet
 *      Firebase RTDB       -> nhận lệnh NV, nhịp sống, OTA, sổ mặt, ảnh
 *      Apps Script ?whoami -> hỏi mình thuộc cửa hàng nào
 *  Nay CHỈ CÒN `wp_url` — một địa chỉ, một khoá, một cách hiểu chữ "xong".
 *
 *  Ba cái được:
 *    · không còn khoá Firebase trong firmware. Khoá đó có quyền admin: ai cầm được nó là ĐẨY
 *      ĐƯỢC FIRMWARE TUỲ Ý vào cả 26 máy. Nay firmware không mang nó nữa;
 *    · không còn chuyện ba nơi trả lời khác nhau câu "máy này thuộc cửa hàng nào";
 *    · mất mạng Google thì máy vẫn chạy.
 *
 *  Cái mất, nói thẳng: website thành ĐIỂM CHẾT DUY NHẤT. Host sập là máy không nhận lệnh và
 *  không OTA được. Nhưng lượt chấm công KHÔNG mất: nó nằm trong sổ của đầu đọc, lấy lại được
 *  bằng lệnh "Tải lại" sau khi host sống. Và trước đây Apps Script sập cũng đã mất rồi, nên
 *  đây không phải một điểm chết mới.
 *
 *  ⚠️ LIÊN KẾT + KHOÁ KHÔNG NẰM TRONG BẢN .BIN. Bản .bin do CI build được đặt ở chỗ tải công
 *     khai nên không được chứa bí mật nào — SEC_WP_* ở ci/secrets.ci.h là placeholder.
 *     Máy lấy hai giá trị này theo thứ tự: NVS -> secrets.h (nạp USB) -> portal 192.168.4.1.
 *
 *  🔴 THỨ TỰ DI TRÚ — LÀM ĐÚNG THỨ TỰ NÀY, KHÔNG ĐẢO:
 *       1. đặt /cfg/wp trên Firebase = link + khoá MỚI của host, chờ ~5 phút cho máy nhận;
 *       2. kiểm trên web: máy đã gửi lượt chấm công vào MySQL chưa;
 *       3. RỒI MỚI đẩy bản firmware này.
 *     Đảo thứ tự là máy lên bản mới mà trong NVS chưa có link WordPress -> nó không còn đường
 *     nào để hỏi, vì Firebase đã bị gỡ khỏi chính bản vừa nạp. Lúc đó phải tới tận nơi, mở
 *     portal 192.168.4.1 và gõ tay. Anh Thắng đã biết và chấp nhận rủi ro này
 *     (*"nếu máy mất liên kết thì đợi nạp lại ota thôi"*) — nhưng biết trước vẫn hơn.
 * =========================================================================================== */
constexpr bool _cxTim(const char* h, const char* n){
  for (int i = 0; h[i]; i++){
    int j = 0;
    while (n[j] && h[i+j] == n[j]) j++;
    if (!n[j]) return true;
  }
  return false;
}
#ifndef SEC_WP_URL
  #define SEC_WP_URL CFG_PLACEHOLDER
#endif
#ifndef SEC_WP_KEY
  #define SEC_WP_KEY CFG_PLACEHOLDER
#endif
/* Chặn LÚC BIÊN DỊCH cái lỗi dán nhầm link Apps Script vào ô WordPress. Trước đây có hai ô nên
   dán lẫn là chuyện thường; nay chỉ còn một ô, nhưng người quen tay vẫn dán link cũ vào. */
static_assert(!_cxTim(SEC_WP_URL, "/macros/"),
  "SEC_WP_URL dang chua /macros/ — day la link Apps Script, khong phai link WordPress. "
  "Phai la dang https://<ten mien>/cham-cong-may (khong co dau / o cuoi).");
static_assert(_cxTim(SEC_WP_URL, "__CHUA_CAU_HINH") || _cxTim(SEC_WP_URL, "https://"),
  "SEC_WP_URL phai bat dau bang https:// . Cong nhan cham cong tu choi HTTP thuong.");
const char* wp_url = SEC_WP_URL;
const char* wp_key = SEC_WP_KEY;

/* Đồng bộ ảnh khuôn mặt: máy lấy ảnh của lệnh thêm/sửa rồi ghi mặt vào đầu đọc. */
const bool  DONG_BO_ANH = true;


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
const unsigned long HB_INTERVAL_MS      = 60000;      // gửi nhịp sống lên website mỗi 60s
const unsigned long SAFETY_BACKFILL_MS  = 1800000;    // định kỳ bù lượt sót mỗi 30 phút (lưới an toàn)
const unsigned long OTA_CHECK_MS        = 300000;     // xét bản firmware mà nhịp sống chở về, mỗi 5 phút
// ========================================================

TFT_eSPI tft = TFT_eSPI();
long  lastSerialNo = -1;
unsigned long lastPoll = 0;
unsigned long empLastPoll = 0;      // chu kỳ poll hàng đợi nhân viên
unsigned long statusUntil = 0;      // thời điểm quay lại màn chờ
Preferences prefs;

/* ===========================================================================
 *  BÍ MẬT + LINK: LẤY TỪ Preferences (NVS), giá trị compile chỉ là DỰ PHÒNG
 * ---------------------------------------------------------------------------
 *  Vì sao: file .bin compile từ secrets.h chứa mật khẩu Hikvision và KHOÁ MÁY —
 *  ai có khoá máy là ghi được chấm công cho bất kỳ ai, bất kỳ ngày nào, ở mọi
 *  cơ sở. Muốn CI tự build rồi để .bin ở chỗ tải công khai được (để bấm cập
 *  nhật từ xa) thì .bin BẮT BUỘC không được chứa mấy thứ đó.
 *
 *  Cách làm: mỗi giá trị đọc từ NVS trước; NVS chưa có mà bản compile có giá trị
 *  thật thì CHÉP VÀO NVS ngay. **OTA không ghi đè NVS**, nên chỉ cần chạy bản này
 *  một lần là máy giữ được bí mật, và các bản CI sau (secrets.h toàn placeholder)
 *  vẫn chạy bình thường — không phải tới từng máy khai lại.
 *
 *  ⚠️ Máy CHƯA từng chạy bản này mà nhận bản CI thì mất cấu hình -> phải nạp USB
 *     hoặc khai tay ở portal. Màn máy trên web hiện bản firmware từng máy để soi.
 * =========================================================================== */
// CFG_PLACEHOLDER đã định nghĩa ở ĐẦU FILE (SEC_WP_URL/SEC_WP_KEY dùng tới nó từ dòng ~100).
// Giữ giá trị thật ở String toàn cục (sống suốt đời chương trình) rồi trỏ các con trỏ cũ vào .c_str()
String _cfgHikUser, _cfgHikPass, _cfgApPass, _cfgOtaUser, _cfgOtaPass, _cfgHikIp,
       _cfgWpUrl, _cfgWpKey;
bool   g_chuaCauHinh = false;      // thiếu giá trị bắt buộc -> hiện rõ trên màn hình, không chết im

/* ⚠️ PHẢI bắt cả GIÁ TRỊ MẪU của secrets.example.h, không chỉ "__CHUA_CAU_HINH".
   Đã trả giá thật 31/07/2026: secrets.h còn nguyên mấy chuỗi mẫu của secrets.example.h. Chúng
   KHÔNG bị coi là placeholder nên được CHÉP THẲNG VÀO NVS như giá trị thật -> máy gửi khoá mẫu
   và bị từ chối, mà log thì báo "có" nên soi mãi không ra. Tệ hơn: một khi đã vào NVS thì sửa
   secrets.h cũng vô ích, NVS thắng.
   ⚠️ Danh sách MAU dưới đây GIỮ NGUYÊN cả mấy chuỗi của Apps Script/Firebase dù hai thứ đó đã
      gỡ: máy nào từng bị chép chuỗi mẫu vào NVS thì nay đọc lên vẫn phải bị loại. */
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
/* ⛔ `execUrlHopLe()` và `fbHostHopLe()` — ĐÃ GỠ 22/08/2026 cùng với Apps Script và Firebase.

   `wpUrlHopLe()` dưới đây nay là hàm kiểm dạng DUY NHẤT, nên nó gánh nặng hơn hẳn: hồi chạy
   song song, dán sai ô này thì vẫn còn đường /exec đỡ cho; nay dán sai là máy KHÔNG đẩy được
   một lượt nào. Phép thử của nó ở tools/test/fw/kiem-link.cpp — chạy bằng g++, không cần máy. */
bool wpUrlHopLe(const String& u){
  if (u.length() == 0)             return false;
  if (!u.startsWith("https://"))   return false;   // cổng từ chối HTTP thường
  /* Dấu / ở cuối: WordPress chuyển hướng để bỏ/thêm nó, mà máy KHÔNG đi theo chuyển hướng —
     đi theo là gọi lại bằng GET và mất trọn thân POST, tức mất lượt chấm công. */
  if (u.endsWith("/"))             return false;
  if (u.indexOf("/macros/") >= 0)  return false;   // dán lẫn link Apps Script cũ
  if (u.indexOf(".firebasedatabase.app") > 0) return false;   // dán lẫn link Firebase cũ
  if (u.indexOf('.', 8) < 0)       return false;   // phải có tên miền thật
  return u.length() > 12;
}
/** Giá trị này DÙNG ĐƯỢC không. Hai khoá là link nên còn phải đúng dạng, không chỉ "khác mẫu". */
bool cfgDungDuoc(const char* khoa, const String& v){
  if (cfgLaPlaceholder(v)) return false;
  if (strcmp(khoa, "wpUrl")   == 0) return wpUrlHopLe(v);
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
    // Chỉ khoá LINK không phải bí mật nên mới in ra được — in khoá máy là hớ.
    bool khoe = (strcmp(khoa,"wpUrl") == 0);
    Serial.printf("[CFG] ⚠️ '%s': ca trong may va secrets.h deu khong dung duoc%s%s\n",
                  khoa, khoe ? " — dang luu: " : "", khoe ? v.c_str() : "");
  }
  return "";
}
/**
 * MAC của bo ESP32 — DANH TÍNH của máy trong bảng MayChamCong của web app.
 * ⚠️ 31/07/2026 — TRƯỚC ĐÂY dùng macBo(), trả MAC của giao diện STA.
 *    Ở chế độ 4G (USE_4G=true) máy chỉ bật softAP, STA không hề khởi tạo, nên hàm đó trả
 *    "00:00:00:00:00:00". Máy tự khai danh tính toàn số 0 -> web app không khớp được máy nào,
 *    tab "Máy chấm công" hiện 0 máy, không có gì để gán cửa hàng. IM LẶNG hoàn toàn.
 *    Nay đọc từ efuse: luôn có, và LÀ ĐÚNG giá trị mà máy chạy WiFi vẫn báo (ESP_MAC_WIFI_STA),
 *    nên máy cũ đã ghi nhận bằng macBo() vẫn khớp, không sinh dòng trùng.
 */
String macBo(){
  static String cache = "";
  if (cache.length()) return cache;
  uint8_t m[6] = {0};
  if (esp_read_mac(m, ESP_MAC_WIFI_STA) == ESP_OK) {
    char b[18];
    snprintf(b, sizeof(b), "%02X:%02X:%02X:%02X:%02X:%02X", m[0], m[1], m[2], m[3], m[4], m[5]);
    cache = String(b);
  } else {
    cache = WiFi.macAddress();        // đường lùi; ít nhất không tệ hơn cách cũ
    Serial.println("[MAC] esp_read_mac thất bại -> tạm dùng macBo()");
  }
  return cache;
}
void napCauHinh(){
  _cfgHikIp   = cfgLay("hikIp",    HIK_IP_MAC_DINH);
  _cfgHikUser = cfgLay("hikUser",  SEC_HIK_USER);
  _cfgHikPass = cfgLay("hikPass",  SEC_HIK_PASS);
  _cfgApPass  = cfgLay("apPass",   SEC_AP_PASS);
  _cfgOtaUser = cfgLay("otaUser",  SEC_OTA_USER);
  _cfgOtaPass = cfgLay("otaPass",  SEC_OTA_PASS);
  _cfgWpUrl   = cfgLay("wpUrl",    wp_url);
  _cfgWpKey   = cfgLay("wpKey",    wp_key);
  if (_cfgHikIp.length() == 0) _cfgHikIp = HIK_IP_MAC_DINH;   // trống là mọi lệnh ISAPI đi vào "http:///…"
  hik_ip = _cfgHikIp.c_str();
  hik_user = _cfgHikUser.c_str();  hik_pass = _cfgHikPass.c_str();
  AP_PASS  = _cfgApPass.c_str();
  OTA_USER = _cfgOtaUser.c_str();  OTA_PASS = _cfgOtaPass.c_str();
  wp_url = _cfgWpUrl.c_str();  wp_key = _cfgWpKey.c_str();
  /* 🔴 ĐƯỜNG WORDPRESS NAY LÀ ĐƯỜNG DUY NHẤT — thiếu nó là máy KHÔNG đẩy được chấm công.
     Trước bản này nó là "đường thứ hai" nên thiếu cũng không sao, và dòng log cũ nói đúng như
     vậy. Giữ nguyên câu đó sau khi gỡ Apps Script là nói dối người đang đi lắp máy. */
  bool _urlXau = !wpUrlHopLe(_cfgWpUrl);
  g_chuaCauHinh = _urlXau || (_cfgWpKey.length() == 0) ||
                  (_cfgHikPass.length() == 0) || (_cfgOtaPass.length() == 0);
  Serial.printf("[CFG] IP dau doc = %s%s\n", hik_ip,
                (_cfgHikIp == String(HIK_IP_MAC_DINH)) ? " (mac dinh)" : " (khai o portal)");
  Serial.printf("[CFG] wpUrl=%s wpKey=%s hikPass=%s otaPass=%s apPass=%s\n",
    _cfgWpUrl.length()?"có":"THIẾU", _cfgWpKey.length()?"có":"THIẾU",
    _cfgHikPass.length()?"có":"THIẾU", _cfgOtaPass.length()?"có":"THIẾU", _cfgApPass.length()?"có":"THIẾU");
  if (_urlXau) {
    Serial.println("[CFG] 🔴 LINK WEBSITE SAI DẠNG — máy sẽ KHÔNG đẩy được chấm công.");
    Serial.println("        Đang có: " + (_cfgWpUrl.length() ? _cfgWpUrl : String("(trống)")));
    Serial.println("        Phải là: https://<tên miền>/cham-cong-may  (không có dấu / ở cuối)");
    if (_cfgWpUrl.indexOf("/macros/") >= 0)
      Serial.println("        ⚠️ Đây là link Apps Script cũ. Hệ thống đã chuyển hẳn sang website, link đó không dùng nữa.");
  }
  if (_cfgWpKey.length() == 0) Serial.println("[CFG] 🔴 THIẾU khoá máy — cổng nhận trả 401 và mọi lượt chấm công rơi.");
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

  /* Bản firmware ở GÓC TRÊN TRÁI — anh Thắng 07/08/2026: *"bổ sung tên firmware ra màn hình
     chấm công, bổ sung vào 1 góc"*.
     Vì sao đáng có: đứng tại cửa hàng muốn biết máy này đã lên bản mới chưa thì trước đây phải
     mở web tra heartbeat, mà máy CHƯA GÁN thì web lại không hiện gì. Nhìn màn là xong.
     ⚠️ Chỉ lấy phần MÃ NGÀY (tới dấu cách đầu tiên): FW_VERSION có kèm câu mô tả dài, in cả
        chuỗi thì tràn ngang màn và đè lên tiêu đề.
     ⚠️ Font 1 (cao 8px) đặt ở y=6 -> chiếm y 6..14, còn tiêu đề font 4 canh giữa y=28 chiếm
        y 15..41. Không đụng nhau. Đổi font/toạ độ thì phải tính lại chỗ này. */
  {
    String _fw = String(FW_VERSION);
    int _sp = _fw.indexOf(' ');
    if (_sp > 0) _fw = _fw.substring(0, _sp);
    tft.setTextDatum(TL_DATUM);
    tft.setTextColor(tft.color565(110, 130, 150), COL_BG);
    tft.drawString("fw " + _fw, 10, 6, 1);
  }

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

/* ---------- searchID: MỖI LẦN TÌM MỘT MÃ RIÊNG ----------
   🔴 Đây là NGUYÊN NHÂN của "check-in chạy 1-2 lần rồi thôi, lượt bù thì vẫn chạy" (01/08/2026).

   Với ISAPI của Hikvision, `searchID` là KHOÁ MỘT PHIÊN TÌM KIẾM: đầu đọc giữ lại tập kết quả
   ứng với mã đó để mình lật trang. Hàm đọc lượt trực tiếp trước đây dùng cứng `searchID:"1"`:
     · hỏi lần 1 với maxResults=1 trên tổng N lượt -> phiên tìm BỎ DỞ (còn "MORE")
     · hỏi lần 2 vẫn `searchID:"1"` nhưng ĐỔI vị trí và số lượng -> đầu đọc kẹt phiên đó
   Kẹt rồi thì mọi lần hỏi sau bằng mã "1" đều chết, tới khi khởi động lại đầu đọc mới hết.
   Còn hàm bù dùng `searchID:"backfill"` (mã khác, lại lật hết trang mới thôi) nên vẫn chạy —
   đúng như hiện trường báo. `FDSearch`/`UserInfo/Search` cũng dùng cứng "1" nhưng chỉ chạy khi
   người dùng bấm, rất thưa, nên chưa lộ.

   ⚠️ Vì sao nó lừa được lâu: hỏng KHÔNG ở dạng "sai mật khẩu" hay "sai IP" mà ra `-1` (đầu đọc
   ngắt kết nối), nên nhìn log cứ tưởng lỗi mạng. Đã đi sai hướng 2 lần vì con số đó.

   Quy tắc từ nay: MỘT lần tìm = MỘT mã. Vòng lật trang thì lấy mã MỘT LẦN trước vòng và giữ
   nguyên cho cả vòng đó — đó mới là ý nghĩa thật của searchID. */
String acsSearchId(){
  static uint32_t dem = 0;
  return "cc" + String((uint32_t)millis(), HEX) + "_" + String(++dem);
}

// Nguyên mẫu: mấy hàm chẩn đoán dưới đây gọi hikRequest/shortResp định nghĩa ở xa phía dưới.
// Khai trước cho tường minh, không dựa vào việc Arduino tự sinh nguyên mẫu.
String hikRequest(const String& method, const String& uri, const String& payload, int* outCode);
String shortResp(const String& r, int code);
String acsMocBatDau();          // acsMocTuLuotCuoi() gọi tới, mà nó định nghĩa ở dưới

/* Giờ hiện tại của ESP32, dạng đọc được — chỉ để in ra chẩn đoán. */
String gioEspISO(){
  struct tm t;
  if (!getLocalTime(&t, 10)) return "KHONG DOC DUOC";
  char b[64];
  snprintf(b, sizeof b, "%04d-%02d-%02d %02d:%02d:%02d",
           t.tm_year + 1900, t.tm_mon + 1, t.tm_mday, t.tm_hour, t.tm_min, t.tm_sec);
  return String(b);
}

/* Giờ hiện tại của ĐẦU ĐỌC (ISAPI /System/time) — chỉ để in ra chẩn đoán.
   Lượt chấm công mang dấu thời gian của ĐẦU ĐỌC, không phải của ESP32. Hai bên lệch ngày là
   khoảng startTime/endTime mình đặt sẽ loại sạch lượt thật, mà đầu đọc vẫn trả 200 tử tế —
   hỏng IM LẶNG, đúng kiểu khó tìm nhất. */
String hikGioMay(){
  int code = 0;
  String r = hikRequest("GET", "/ISAPI/System/time?format=json", "", &code);
  if (code != 200 || r.length() == 0) return "khong doc duoc (http" + String(code) + ")";
  StaticJsonDocument<192> filter;
  filter["Time"]["localTime"] = true;
  filter["Time"]["timeZone"]  = true;
  DynamicJsonDocument d(384);
  if (deserializeJson(d, r, DeserializationOption::Filter(filter))) {
    String x = r; x.replace("\n"," "); x.replace("\r"," ");
    return "parse loi -> " + x.substring(0, 120);
  }
  String lt = String((const char*)(d["Time"]["localTime"] | ""));
  String tz = String((const char*)(d["Time"]["timeZone"]  | ""));
  if (!lt.length()) { String x = r; x.replace("\n"," "); return "khong thay localTime -> " + x.substring(0, 120); }
  return lt + (tz.length() ? ("  (tz " + tz + ")") : "");
}

/* Mốc bắt đầu cho lượt TRỰC TIẾP: từ lượt cuối đã đẩy thành công.
   Khoảng HẸP là điều kiện để không cần biết đầu đọc xếp kết quả kiểu gì — chỉ cần WINDOW lượt
   đầu tiên là đã bao trọn phần mới. Chưa đẩy được lượt nào (máy mới) thì lùi rộng ra.
   ⚠️ Lùi lại 60 giây so với lượt cuối: đầu đọc có thể ghi hai lượt trong cùng một giây, lấy
      đúng mốc bằng nhau thì lượt thứ hai có nguy cơ rơi ra ngoài khoảng. Đẩy trùng thì vô hại
      (serialNo lọc, và doPost bỏ qua bản trùng), còn SÓT là mất công của nhân viên. */
String acsMocTuLuotCuoi(){
  if (lastSyncTime.length() >= 19) {
    struct tm t; memset(&t, 0, sizeof t);
    if (strptime(lastSyncTime.c_str(), "%Y-%m-%d %H:%M:%S", &t)) {
      t.tm_isdst = -1;
      time_t e = mktime(&t) - 60;
      struct tm l;
      if (localtime_r(&e, &l)) {
        char b[64];
        snprintf(b, sizeof b, "%04d-%02d-%02dT%02d:%02d:%02d+07:00",
                 l.tm_year + 1900, l.tm_mon + 1, l.tm_mday, l.tm_hour, l.tm_min, l.tm_sec);
        return String(b);
      }
    }
  }
  return acsMocBatDau();
}

/* Mốc BẮT ĐẦU cho lệnh đọc sổ chấm công trực tiếp.
   ⚠️ Đồng hồ ESP32 có thể CHƯA đồng bộ (NTP không chạy qua AT-HTTP; giờ mạng 4G AT+CCLK? phải
      đợi NITZ). Lúc đó getLocalTime() trả năm 1970. Nếu lấy nguyên năm 1970 làm mốc thì vẫn
      chạy được, nhưng nếu lấy "now - 24h" của một đồng hồ sai thì có thể LỌT MẤT lượt chấm
      công thật — mất công của nhân viên. Nên:
        · đồng hồ tin được (năm >= 2020) -> lùi 2 ngày, đủ rộng để không bỏ sót lượt nào
        · đồng hồ CHƯA tin được          -> lấy mốc cố định 2020-01-01, thà quét rộng còn hơn sót
      Cả hai nhánh đều LUÔN có mốc — đó mới là thứ đầu đọc đòi. */
String acsMocBatDau(){
  struct tm t;
  if (getLocalTime(&t, 10) && (t.tm_year + 1900) >= 2020) {
    time_t nay = mktime(&t) - (time_t)(2 * 24 * 3600);      // lùi 2 ngày
    struct tm lui;
    if (localtime_r(&nay, &lui)) {
      char b[64];   // 64 chứ không 40: g++ -Wformat-truncation cảnh báo %04d in được tới 11 ký tự
      snprintf(b, sizeof b, "%04d-%02d-%02dT00:00:00+07:00",
               lui.tm_year + 1900, lui.tm_mon + 1, lui.tm_mday);
      return String(b);
    }
  }
  return "2020-01-01T00:00:00+07:00";
}

// ---------- POST đọc SỔ CHẤM CÔNG của máy (AcsEvent, Digest) ----------
/* ⚠️ Đây là đường LẤY LƯỢT CHẤM CÔNG — hỏng là không lượt nào lên sheet, mà web app
   KHÔNG hề biết (máy vẫn báo online bằng heartbeat). Trước bản này hàm tự dựng lại Digest
   một lần nữa (bản chép tay thứ hai) và khi lỗi chỉ in "[MÁY] Lỗi HTTP: -1" — con số đó
   không cho biết gì: -1 là CHƯA MỞ NỔI KẾT NỐI TCP tới đầu đọc (đầu đọc hết khe kết nối,
   vừa bị dội sau lượt tải ảnh, hoặc mất mạng nội bộ), khác hẳn 401/403 (sai mật khẩu) hay
   500 (đầu đọc từ chối câu lệnh).
   Nay: dùng lại hikRequest() — đúng đường đã chạy được cho UserInfo/Record — và
     · code <= 0 (chưa nối được) -> NGHỈ rồi THỬ LẠI, tối đa 3 lần
     · code > 0  (đầu đọc đã trả lời) -> lỗi thật, thử lại vô ích, in nguyên văn câu trả lời
   Đầu đọc trả body cũng in ra để lần sau đọc log là biết ngay, không phải đoán. */
String hikPost(String payload) {
  const String uri = "/ISAPI/AccessControl/AcsEvent?format=json";
  int code = 0;
  String r;
  for (int lan = 1; lan <= 3; lan++) {
    r = hikRequest("POST", uri, payload, &code);
    if (code == 200) return r;
    String vt = (code > 0) ? (" " + shortResp(r, code)) : String(" (chua mo noi ket noi TCP toi dau doc)");
    // ⚠️ In kèm TÀI NGUYÊN. Hiện trường báo "check-in được 1-2 lần rồi thôi" — dáng đó là CẠN
    // tài nguyên, không phải sai mật khẩu/sai IP. Ba con số dưới đây phân biệt được ngay:
    //   · heap tụt dần         -> rò bộ nhớ
    //   · nhomax nhỏ mà heap to -> heap phân mảnh (xin khối liền mạch không nổi)
    //   · apKhach = 0          -> đầu đọc RỜI khỏi AP, thử lại bao nhiêu cũng vô ích
    Serial.printf("[MÁY] Đọc sổ chấm công lần %d LỖI: http%d%s | heap=%u nhomax=%u apKhach=%u\n",
                  lan, code, vt.c_str(), (unsigned)ESP.getFreeHeap(),
                  (unsigned)ESP.getMaxAllocHeap(), (unsigned)WiFi.softAPgetStationNum());
    if (code > 0) break;                 // đầu đọc đã trả lời -> lỗi thật, thử lại không đổi gì
    if (lan < 3) delay(500L * lan);       // nghỉ tăng dần rồi thử lại
  }
  return "";
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

// ---------- Đẩy lượt chấm công lên WordPress ----------
String wpGoi(const String& body, bool docThan);              // forward decl (định nghĩa ngay dưới)
/* Mấy hàm 4G + tiện ích định nghĩa ở CUỐI file mà `wpGoi` gọi tới. Khai tay chứ không trông vào
   prototype Arduino tự sinh: nó sinh theo heuristic và đã có tiền lệ hỏng khi hàm nằm sau macro. */
int    net4gPostOpen(const String& url, const String& body, int* datalen);
int    net4gReadStart(int want);
String atWait(const char* token, unsigned long to);
String jsonEscMin_(const String& s);

/**
 * ĐẨY MỘT LƯỢT CHẤM CÔNG.
 *
 * Trước 22/08/2026 hàm này đẩy vào Apps Script rồi đẩy thêm một bản sang WordPress. Nay chỉ còn
 * MỘT nơi, nên cũng chỉ còn MỘT bản chuỗi — trước có hai bản cho cùng một lượt, mà hai bản là
 * sớm muộn lệch nhau và lệch ở đây nghĩa là hai nơi ghi hai lượt khác nhau cho cùng một lần bấm.
 *
 * ⚠️ KHÔNG KÈM ẢNH. Anh Thắng chốt *"ảnh driver không cần lấy qua đâu, có giờ chấm công là được
 *    rồi"*, mà ảnh base64 là gần hết gói. Vẫn NHẬN con trỏ ảnh vì nơi gọi đã đọc ảnh về rồi —
 *    và giải phóng nó NGAY, TRƯỚC khi mở TLS: bắt tay TLS cần ~40KB RAM liền mạch, còn giữ
 *    buffer base64 là thiếu RAM rồi lỗi HTTP -1, trông y như lỗi mạng.
 *
 * LƯU Ý: hàm này NHẬN QUYỀN SỞ HỮU imageB64 và sẽ free nó. Caller KHÔNG được free lại.
 */
bool pushEvent(String empNo, String name, String eventTime, char* imageB64, int imageB64Len) {
  if (imageB64) { free(imageB64); imageB64 = NULL; }
  (void) imageB64Len;

  String body = String("{\"macAddress\":\"") + macBo()
              + "\",\"hikSerial\":\"" + HIK_SERIAL + "\",\"hikModel\":\"" + HIK_MODEL
              + "\",\"stationName\":\"" + String(STATION_NAME)
              + "\",\"employeeNo\":\"" + empNo + "\",\"name\":\"" + name
              + "\",\"time\":\"" + eventTime + "\",\"image\":\"\"}";

  for (int lan = 1; lan <= 3; lan++) {
    Serial.printf("   -> [WP] gui luot (lan %d, %d byte, heap %d)...\n", lan, body.length(), ESP.getFreeHeap());
    String ra = wpGoi(body, true);
    /* CÙNG luật với cổng nhận: 200 + thân có chữ "SUCCESS". `wpGoi` trả "" khi không 200, nên
       ở đây chỉ còn phải soi chữ. Khác đi là máy tưởng xong trong khi lượt chưa vào bảng. */
    if (ra.indexOf("SUCCESS") >= 0) { Serial.println("   ✔️ DA GHI MySQL"); return true; }
    delay(1200);
  }
  Serial.println("   ❌ Gui WordPress that bai sau 3 lan -> giu lai trong so dau doc, lay lai bang lenh Tai lai.");
  return false;
}

/**
 * MỘT CỬA DUY NHẤT ĐỂ NÓI CHUYỆN VỚI WORDPRESS.
 *
 * Mọi thứ máy cần — đẩy chấm công, nhịp sống, lấy lệnh, báo xong, sổ mặt, ảnh — đều là một lượt
 * POST JSON vào `wp_url` kèm khoá. Một hàm chứ không rải mỗi nơi một kiểu: rải ra là sớm muộn
 * có chỗ quên chống chuyển hướng, và chỗ đó im lặng mất dữ liệu.
 *
 * 🔴 KHOÁ ĐI TRONG THÂN JSON, KHÔNG PHẢI HEADER. Đường 4G gửi bằng lệnh AT (`AT+HTTPDATA`), mà
 *    đặt header tuỳ ý qua AT thì tuỳ đời module — có module không có `USERDATA`, có module cắt
 *    header dài. Cổng nhận đọc được khoá ở CẢ HAI chỗ (`X-VHCC-Key` hoặc trường `key`), nên
 *    dùng thân là một đường chạy được trên cả WiFi lẫn 4G. Vẫn gửi kèm header ở đường WiFi.
 *
 * 🔴 KHÔNG ĐI THEO CHUYỂN HƯỚNG. Gặp 30x là coi như THẤT BẠI và nói rõ link sai — chứ không gọi
 *    lại bằng GET rồi mất trọn thân POST mà vẫn thấy chữ "SUCCESS" trong trang trả về và tưởng
 *    đã ghi. Đây là cái bẫy riêng của WordPress mà Apps Script không có.
 *
 * `docThan=false` khi chỉ cần biết thành/bại (đỡ một lượt HTTPREAD trên 4G, mỗi lượt ~2 giây).
 * Trả "" nếu hỏng; trả thân trả về nếu 200 (và `docThan`), hoặc "SUCCESS" nếu 200 mà không đọc.
 */
String wpGoi(const String& body, bool docThan){
  if (!wpUrlHopLe(String(wp_url)) || strlen(wp_key) == 0) return "";
  if (!netUp()) return "";

  /* Nhét khoá vào thân. Chèn ngay sau dấu { đầu tiên để khỏi phải phân tích JSON — thân nào ở
     đây cũng do chính firmware dựng nên chắc chắn mở bằng '{'. */
  String than = body;
  if (than.length() > 1 && than.charAt(0) == '{') {
    than = "{\"key\":\"" + String(wp_key) + "\"," + than.substring(1);
  }

  if (USE_4G) {
    int dl = 0, st = net4gPostOpen(String(wp_url), than, &dl);
    if (st != 200) {
      Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      if (st == 401) Serial.println("   ⚠️ [WP] 401 — sai khoa may (phai khop VHCC_KHOA_MAY o wp-config.php).");
      else if (st == 301 || st == 302 || st == 307 || st == 308)
        Serial.printf("   ⚠️ [WP] %d CHUYEN HUONG — link sai (dau / o cuoi? sai ten mien?).\n", st);
      else if (st) Serial.printf("   ⚠️ [WP] 4G status=%d\n", st);
      return "";
    }
    if (!docThan || dl <= 0) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); return "SUCCESS"; }
    int n = net4gReadStart(dl); String ra = "";
    if (n > 0) { ra.reserve(n + 4); int got = 0; unsigned long t0 = millis();
      while (got < n && millis()-t0 < 12000) { while (Serial2.available() && got < n){ ra += (char)Serial2.read(); got++; t0=millis(); } delay(1); } }
    atWait("OK",2000); Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    return ra;
  }

  WiFiClientSecure client; client.setInsecure();
  HTTPClient http;
  http.begin(client, wp_url);
  http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-VHCC-Key", wp_key);
  http.setTimeout(20000);
  int code = http.POST(than);
  String ra = (code == 200) ? http.getString() : "";
  http.end();
  if (code != 200) {
    if (code == 401) Serial.println("   ⚠️ [WP] 401 — sai khoa may (phai khop VHCC_KHOA_MAY o wp-config.php).");
    else if (code == 301 || code == 302 || code == 307 || code == 308)
      Serial.printf("   ⚠️ [WP] %d CHUYEN HUONG — link sai (dau / o cuoi? sai ten mien?). "
                    "May KHONG di theo chuyen huong nen luot nay khong vao MySQL.\n", code);
    else Serial.printf("   ⚠️ [WP] loi HTTP %d\n", code);
    return "";
  }
  return docThan ? ra : String("SUCCESS");
}

/** Một việc của máy: dựng thân `{"viec":…, "hikSerial":…, "macAddress":…, …}` rồi gọi. */
String wpViec(const String& viec, const String& them, bool docThan){
  String b = "{\"viec\":\"" + viec + "\",\"hikSerial\":\"" + jsonEscMin_(HIK_SERIAL)
           + "\",\"macAddress\":\"" + jsonEscMin_(macBo())
           + "\",\"stationName\":\"" + jsonEscMin_(STATION_NAME)
           + "\",\"hikModel\":\"" + jsonEscMin_(HIK_MODEL) + "\"";
  if (them.length()) b += "," + them;
  b += "}";
  return wpGoi(b, docThan);
}

/* ⛔ `wpNhanCauHinh()` — ĐÃ GỠ 22/08/2026.
   Hàm này đọc `/cfg/wp` trên Firebase để tự nhận link + khoá WordPress, nhờ đó "nạp OTA là
   xong" mà khỏi gõ tay ở portal từng máy. Nay không còn Firebase nên nó cũng không còn.

   Máy lấy link + khoá theo thứ tự: NVS -> secrets.h (nạp USB) -> portal 192.168.4.1.
   Máy đang chạy thì trong NVS ĐÃ có sẵn hai giá trị đó (chính hàm này ghi vào, trước khi bị
   gỡ) — nên bản này lên là chạy tiếp. Chip trắng thì gõ ở portal.
   🔴 Vì vậy thứ tự di trú ở đầu tệp KHÔNG được đảo: đặt /cfg/wp trước, đẩy firmware sau. */

/* ===========================================================================/* ===========================================================================
 *  MÃ HTTP CỦA LƯỢT ISAPI GẦN NHẤT — để web nói ĐÚNG nguyên nhân
 * ---------------------------------------------------------------------------
 *  🔴 03/08 (bản j) — LỖI CỦA BẢN i: em lấy `HIK_SERIAL.length()` làm dấu hiệu "đọc được đầu
 *  đọc". Sai: serial đọc từ `/ISAPI/System/deviceInfo`, mà có đời đầu đọc KHÔNG trả lời đường
 *  đó trong khi vẫn trả lời `AcsEvent` (tức là chấm công vẫn chạy tốt). Máy như vậy bị web tô
 *  đỏ "chưa đọc được đầu đọc" — BÁO ĐỘNG GIẢ, đúng loại lỗi tệ nhất vì nó đẩy người ta đi sửa
 *  một thứ không hỏng. Đây là điều em vẫn nhắc mình: đừng kết luận từ một dấu hiệu gián tiếp.
 *
 *  Nay ghi lại mã HTTP + mốc giờ của lượt ISAPI GẦN NHẤT, ngay trong `hikRequest` — mọi lượt
 *  ISAPI đều đi qua đây nên không sót đường nào. Web đọc mã đó là biết chắc:
 *      2xx  -> tốt (kể cả khi serial trống vì đời máy không trả deviceInfo)
 *      401  -> tới được đầu đọc, SAI MẬT KHẨU ISAPI
 *      ≤ 0  -> không với tới (sai IP / chưa nối AP / đầu đọc tắt)
 * =========================================================================== */
/* ---- Vòng nhớ các lượt ĐÃ XỬ LÝ: chặn đẩy lại vô hạn khi chốt theo THỜI GIAN ----
   Chốt bằng `>=` để không mất người quẹt cùng giây, nên cùng một lượt sẽ xuất hiện lại ở vòng poll
   sau. Nhớ DA_DAY_TOI_DA khoá "mã|giờ" gần nhất là đủ: mỗi vòng chỉ có vài lượt trong khoảng hẹp.
   "Đã xử lý" gồm cả lượt đã đẩy, lượt rác và lượt quẹt trùng — mọi thứ đã có kết luận thì đừng
   xét lại. Chỉ lượt ĐẨY LỖI là KHÔNG ghi, để vòng sau thử lại (không mất công nhân viên).
   ⚠️ Số lượng khai ở `#define` ngay dưới — ĐỪNG viết con số vào chú thích, sửa define mà quên
      chú thích là bẫy cho người đọc sau (đã hụt một lần: chú thích ghi 12, thực tế 34).
   ⚠️ Nhớ trong RAM, mất khi khởi động lại — không sao, lúc đó máy chủ vẫn bỏ giờ đã ghi. */
/* Mã NV của một bản ghi AcsEvent — thẻ thay mã khi máy chỉ quẹt thẻ. Tách ra hàm vì cần dùng
   ở bộ lọc (trước khi bóc các trường khác) và ở phần đẩy. */
String emp0(JsonObject e){
  String x = e["employeeNoString"] | "";
  if (x.length() == 0) x = String((const char*)(e["cardNo"] | ""));
  return x;
}
/* ⚠️ PHẢI rộng hơn WINDOW (30): trang baseline có thể mang tới 30 lượt, vòng nhớ nhỏ hơn thì
   mấy lượt đầu bị đẩy ra khỏi vòng và vòng poll sau đẩy lại chúng lên sheet. */
#define DA_DAY_TOI_DA 34
String g_daDay[DA_DAY_TOI_DA]; int g_daDayIdx = 0;
static inline String khoaLuot(const String& emp, const String& t){ return emp + "|" + t; }
bool daDayRoi(const String& emp, const String& t){
  String k = khoaLuot(emp, t);
  for (int i = 0; i < DA_DAY_TOI_DA; i++) if (g_daDay[i] == k) return true;
  return false;
}
void ghiDaDay(const String& emp, const String& t){
  g_daDay[g_daDayIdx] = khoaLuot(emp, t);
  g_daDayIdx = (g_daDayIdx + 1) % DA_DAY_TOI_DA;
}

/* Kết quả lượt ĐỌC SỔ CHẤM CÔNG gần nhất — gửi lên web để chẩn đoán từ xa.
   🔴 04/08/2026, anh Thắng: *"chấm lúc máy đang mở thì không lên, nhưng rút điện gắn lại thì
   đồng bộ lên"*. Rút điện gắn lại = chạy `backfillRange` lúc khởi động, nên lên. Tức là LƯỢT
   TRỰC TIẾP (`checkNewAcsEvents`) im, còn LƯỢT BÙ chạy. Hai đường cùng gọi một endpoint, khác
   nhau ở khoảng thời gian và ở chỗ lượt bù LẬT TRANG còn lượt trực tiếp chỉ đọc trang đầu.
   Có mấy giả thuyết đều hợp lý, mà log chỉ in ra Serial — phải cắm USB tại cửa hàng mới thấy.
   Nên đem ba số này lên heartbeat: đọc TỪ MỐC NÀO · đầu đọc báo TỔNG bao nhiêu · trả về BAO NHIÊU
   dòng. Ba số đó phân biệt được các giả thuyết mà không cần đoán, cũng không cần ra cửa hàng. */
String g_soTu = "";               // startTime của lượt đọc sổ gần nhất
long   g_soTong = -1;             // totalMatches đầu đọc báo (-1 = chưa đọc lần nào)
long   g_soSo = -1;               // số dòng đầu đọc trả về
unsigned long g_soLuc = 0;        // millis() lượt đọc sổ gần nhất
const char* g_soChot = "?";       // đang chốt lượt mới theo gì: "serial" | "thoi-gian" | "?"

int g_hikHttp = 0;                 // mã HTTP lượt ISAPI gần nhất (0 = chưa gọi lần nào)
unsigned long g_hikOkLuc = 0;      // millis() lượt ISAPI 2xx gần nhất

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
  g_hikHttp = code;
  if (code >= 200 && code < 300) g_hikOkLuc = millis();
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

/* Bóc nội dung giữa <the>…</the> trong một chuỗi XML. Trả "" nếu không có.
   Cố ý KHÔNG dùng thư viện XML: chỉ cần đúng hai trường (serialNumber, model), mà thêm thư viện
   là phình .bin của con ESP32 vốn đã sát phân vùng. */
String xmlGiua(const String& xml, const char* the){
  String mo = String("<") + the + ">", dong = String("</") + the + ">";
  int a = xml.indexOf(mo); if (a < 0) return "";
  a += mo.length();
  int b = xml.indexOf(dong, a); if (b < 0) return "";
  String v = xml.substring(a, b); v.trim();
  return v;
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
  /* 🔴 03/08/2026 — ĐỜI ĐẦU ĐỌC là thứ phải xem ĐẦU TIÊN khi hỏng (ghi chú 01/08: K1T320 và
     K1T343 hành xử KHÁC nhau trên cùng firmware). Nhưng cột `Model` trong sheet MayChamCong lại
     hay TRỐNG, nên suốt cả tối 03/08 không phân biệt được đời máy từ web — phải hỏi anh Thắng.
     Lý do: hàm này CHỈ đọc được JSON. Có đời máy chấm công Hikvision **bỏ qua `?format=json`**
     và trả về XML với HTTP 200. Parse JSON thất bại -> `return` -> serial + model rỗng, dù ISAPI
     hoàn toàn sống.
     Nay: parse JSON không được thì bóc thẳng từ XML. Không thư viện XML nào cần — chỉ cần cắt
     giữa hai thẻ. */
  StaticJsonDocument<256> filter;
  filter["DeviceInfo"]["serialNumber"] = true;
  filter["DeviceInfo"]["model"]        = true;
  DynamicJsonDocument d(512);
  String sn, md;
  if (deserializeJson(d, r, DeserializationOption::Filter(filter))) {
    Serial.println("[MÃ MÁY] deviceInfo không phải JSON -> thử đọc XML");
    sn = xmlGiua(r, "serialNumber");
    md = xmlGiua(r, "model");
    if (!sn.length() && !md.length()) {
      String x = r; x.replace("\n", " "); x.replace("\r", " ");
      Serial.println("[MÃ MÁY] cũng không đọc được XML. 160 byte đầu: " + x.substring(0, 160));
      return;
    }
  } else {
    sn = String((const char*)(d["DeviceInfo"]["serialNumber"] | ""));
    md = String((const char*)(d["DeviceInfo"]["model"] | ""));
  }
  sn.trim(); md.trim();
  if (sn.length()) { if (sn != HIK_SERIAL) prefs.putString("hikSn", sn);    HIK_SERIAL = sn; }
  if (md.length()) { if (md != HIK_MODEL)  prefs.putString("hikModel", md); HIK_MODEL  = md; }
  Serial.println("[MÃ MÁY] serial đầu đọc: " + HIK_SERIAL + (HIK_MODEL.length() ? ("  (" + HIK_MODEL + ")") : ""));
}

/* ---- Hỏi server "tôi ở cửa hàng nào?" ----
   Vẫn cần TÊN cửa hàng: nó hiện trên màn hình máy và đi kèm mọi lượt chấm công để người xem log
   biết máy nào. Nhưng nay tên KHÔNG còn là khoá của bất cứ thứ gì — hàng đợi lệnh, nhịp sống,
   OTA đều khoá theo SERIAL đầu đọc. Tên hai máy trùng nhau không còn làm lệnh chạy sang cửa
   hàng khác nữa (trước kia Firebase khoá theo tên nên có).

   Mất mạng / server chưa gán -> GIỮ NGUYÊN tên đang nhớ, KHÔNG xoá trắng: xoá là màn hình máy
   hiện "CHUA_DAT_TEN" trong khi nó vẫn đang chấm công đúng cơ sở. */
bool hoiCuaHang() {
  if (!netUp()) return false;
  String r = wpViec("toi_la_ai", "", true);
  if (r.length() == 0) { Serial.println("[CƠ SỞ] Không hỏi được server -> giữ '" + STATION_NAME + "'"); return false; }
  StaticJsonDocument<384> d;
  if (deserializeJson(d, r)) { Serial.println("[CƠ SỞ] parse trả lời lỗi -> giữ '" + STATION_NAME + "'"); return false; }
  if ((int)(d["choGan"] | 0) == 1) {
    STATION_TU_SERVER = false;
    Serial.println("[CƠ SỞ] ⚠️ MÁY CHƯA ĐƯỢC GÁN CƠ SỞ. Vào wp-admin > Chấm Công > Máy chấm công rồi gán cơ sở cho máy này.");
    Serial.println("        serial=" + HIK_SERIAL + "  mac=" + macBo());
    Serial.println("        Chấm công VẪN được giữ ở máy chủ (bảng chờ gán), gán xong là tự vào bảng chấm công.");
    return false;
  }
  String st = String((const char*)(d["coSo"] | "")); st.trim();
  if (st.length() == 0) { Serial.println("[CƠ SỞ] server không trả tên -> giữ '" + STATION_NAME + "'"); return false; }
  /* Chỉ nhận tên hợp lệ — một ô cơ sở bị gõ ký tự lạ không được biến thành tên trạm. */
  for (unsigned i = 0; i < st.length(); i++){
    char c = st.charAt(i);
    bool okc = (c>='0'&&c<='9')||(c>='a'&&c<='z')||(c>='A'&&c<='Z')||c=='_'||c=='-';
    if (!okc){ Serial.println("[CƠ SỞ] ⚠️ Tên cơ sở có ký tự lạ -> bỏ qua: '" + st + "'"); return false; }
  }
  STATION_TU_SERVER = true;
  if (st != STATION_NAME) {
    Serial.println("[CƠ SỞ] Server gán máy này vào '" + st + "' (trước là '" + STATION_NAME + "') -> ghi nhớ");
    STATION_NAME = st;
    prefs.putString("station", st);
  }
  return true;
}

/* ⛔ `urlEncodeMin()` — ĐÃ GỠ 22/08/2026. Nó sinh ra để ghép tham số vào URL của Apps Script
   (`?action=…&token=…`). Nay mọi thứ đi trong THÂN JSON của một lượt POST, không còn tham số
   URL nào để mã hoá. */

int b64val(int c) {
  if (c >= 'A' && c <= 'Z') return c - 'A';
  if (c >= 'a' && c <= 'z') return c - 'a' + 26;
  if (c >= '0' && c <= '9') return c - '0' + 52;
  if (c == '+' || c == '-') return 62;   // nhận cả base64url ('-' và '_') cho chắc
  if (c == '/' || c == '_') return 63;   // '_' = base64url
  return -1;
}

/* ⚠️ `nap()`/`chot()` là HÀM THÀNH VIÊN chứ không phải hàm tự do, và đó là cố ý: Arduino tự
   sinh prototype cho MỌI hàm tự do rồi chèn lên đầu tệp — chèn trước cả định nghĩa struct này,
   nên một hàm tự do nhận `AnhGiaiMa&` là build đỏ với câu lỗi chẳng liên quan gì. Hàm thành
   viên không bị sinh prototype. */
struct AnhGiaiMa {
  uint8_t* buf; int outIdx; int quad[4]; int qn;
  bool err, done, batDau; int mocI;
  static const char* moc(){ return "anh\":\""; }

  void nap(const uint8_t* p, int n){
    const char* M = moc();
    for (int i = 0; i < n && !err && !done; i++) {
      char c = (char)p[i];
      if (!batDau) {                                  // còn đang dò mốc `anh":"`
        if (c == M[mocI]) { mocI++; if (M[mocI] == 0) batDau = true; }
        else mocI = (c == M[0]) ? 1 : 0;              // lệch thì thử lại từ đầu VỚI chính ký tự này
        continue;
      }
      if (c == '"') { done = true; break; }           // hết chuỗi ảnh
      if (c=='\r'||c=='\n'||c==' '||c=='\t') continue;
      if (c == '=') { done = true; break; }           // đệm cuối base64
      int v = b64val(c);
      if (v < 0) { err = true; break; }
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
  void chot(){
    if (err) return;
    if (qn == 2)      { buf[outIdx++] = (quad[0]<<2)|(quad[1]>>4); }
    else if (qn == 3) { buf[outIdx++] = (quad[0]<<2)|(quad[1]>>4);
                        buf[outIdx++] = ((quad[1]&0xF)<<4)|(quad[2]>>2); }
  }
};

/**
 * TẢI ẢNH KHUÔN MẶT CỦA MỘT LỆNH rồi GIẢI MÃ THẲNG ra JPEG trong buffer malloc.
 *
 * Streaming, không giữ chuỗi base64 to: ảnh mặt cỡ 40-70KB base64, mà bắt tay TLS đã cần ~40KB
 * RAM liền mạch — giữ cả hai là hết RAM rồi lỗi HTTP -1, trông y như lỗi mạng.
 *
 * 🔴 MỘT BỘ GIẢI MÃ DUY NHẤT cho cả hai đường. Bản trước có HAI bản chép của cùng một vòng
 *    base64 (một cho 4G đọc theo chunk, một cho WiFi đọc theo stream). Hai bản của cùng một
 *    thuật toán là sớm muộn sửa một bên quên bên kia, và lỗi đó chỉ hiện ra ở một loại máy.
 *
 * ⚠️ Máy chủ trả JSON `{"status":"SUCCESS","anh":"<base64>"}`, không phải base64 trơn. Nên bộ
 *    giải mã phải BỎ QUA cho tới khi gặp mốc `anh":"` rồi mới bắt đầu — nếu không nó lấy luôn
 *    mấy chữ `status`, `SUCCESS` làm dữ liệu base64 (chúng đều là ký tự base64 hợp lệ!) và ảnh
 *    ra rác mà không có lỗi nào. Kết thúc ở dấu `"` đóng chuỗi.
 *
 * Trả độ dài JPEG (0 = không có ảnh, không phải lỗi), *out = buffer (caller free).
 * Lỗi: -1 hết RAM, -2 gọi hỏng, -3 dữ liệu không phải base64.
 */
int fetchPhotoDecoded(const String& opId, uint8_t** out) {
  *out = NULL;
  if (!wpUrlHopLe(String(wp_url)) || strlen(wp_key) == 0) return -2;
  String than = "{\"key\":\"" + String(wp_key) + "\",\"viec\":\"anh_lenh\",\"opId\":\""
              + jsonEscMin_(opId) + "\"}";

  uint8_t* buf = (uint8_t*)malloc(MAX_FACE_BYTES);
  if (!buf) { Serial.println("[NV] Hết RAM cấp buffer ảnh."); return -1; }
  AnhGiaiMa g = { buf, 0, {0,0,0,0}, 0, false, false, false, 0 };

  if (USE_4G) {                    // 4G: đọc THEO CHUNK 1KB (module giới hạn mỗi lần đọc)
    int dl = 0, st = net4gPostOpen(String(wp_url), than, &dl);
    if (st != 200) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      Serial.printf("[NV] anh 4G status=%d\n", st); free(buf); return -2; }
    /* Thân ngắn = `{"status":"SUCCESS","anh":""}` -> KHÔNG có ảnh. Trả 0 (không phải lỗi):
       thêm nhân viên KHÔNG kèm mặt vẫn là việc làm được, đừng biến nó thành thất bại. */
    if (dl <= 40) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
      Serial.printf("[NV] khong co anh cho lenh nay (dl=%d)\n", dl); free(buf); return 0; }
    uint8_t chunk[1024];
    int start = 0;
    while (start < dl && !g.err && !g.done) {
      int want = (dl - start > 1024) ? 1024 : (dl - start);
      int got = net4gReadChunk(start, want, chunk);
      if (got <= 0) break;
      start += got;
      g.nap(chunk, got);
    }
    g.chot();
    Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500);
    Serial.printf("[NV] Ảnh giải mã %d byte (4G chunk, dl=%d, heap=%d)\n", g.outIdx, dl, ESP.getFreeHeap());
  } else {
    WiFiClientSecure client; client.setInsecure();
    HTTPClient http;
    http.begin(client, wp_url);
    http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-VHCC-Key", wp_key);
    http.setTimeout(25000);
    int code = http.POST(than);
    if (code != 200) { Serial.printf("[NV] anh HTTP %d\n", code); http.end(); free(buf); return -2; }
    WiFiClient* stream = http.getStreamPtr();
    uint8_t tmp[256];
    unsigned long t0 = millis();
    while (!g.err && !g.done) {
      int avail = stream->available();
      if (avail <= 0) {
        if (!http.connected()) break;
        if (millis() - t0 > 4000) break;
        delay(1); continue;
      }
      int r = stream->readBytes(tmp, avail > (int)sizeof(tmp) ? (int)sizeof(tmp) : avail);
      t0 = millis();
      g.nap(tmp, r);
    }
    http.end();
    g.chot();
    Serial.printf("[NV] Ảnh giải mã %d byte (heap=%d)\n", g.outIdx, ESP.getFreeHeap());
  }

  /* Không gặp mốc `anh":"` = máy chủ trả một thân khác hẳn (lỗi, chuyển hướng, trang chặn của
     tường lửa). Nói riêng ra chứ đừng gộp vào "dữ liệu không phải base64" — hai ca này sửa
     khác nhau hoàn toàn. */
  if (!g.batDau) { free(buf); Serial.println("[NV] Tra ve khong co truong anh -> khong co anh cho lenh nay."); return 0; }
  if (g.err || g.outIdx <= 0) { free(buf); return -3; }
  *out = buf; return g.outIdx;
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
// -> đẩy theo trang lên website (việc `roster`)
void hikScanRoster(){
  Serial.println("[SCAN] Đọc danh sách + ảnh từ máy Hikvision...");
  // 1) FDSearch -> tập FPID (mã NV) đã có khuôn mặt. Quét THẲNG chuỗi tìm "FPID" (không phụ thuộc cấu trúc JSON).
  String faceSet = ",";
  {
    int pos = 0, total = -1; const int P = 20;
    const String sidFd = acsSearchId();          // một mã cho cả vòng lật trang, mới ở mỗi lần quét
    for (int g = 0; g < 200; g++) {
      int code;
      String body = "{\"searchID\":\"" + sidFd + "\",\"searchResultPosition\":" + String(pos) + ",\"maxResults\":" + String(P) +
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
  /* 2) UserInfo/Search -> đẩy LÊN THEO TRANG.
     Trước đây gom cả sổ thành một chuỗi rồi PUT một lần lên Firebase. Máy 200 người là chuỗi
     ~10KB — vượt xa cái module 4G nuốt nổi trong một lượt AT, và hỏng thì mất trọn sổ. Nay mỗi
     trang 20 người là một lượt: hỏng một trang thì chỉ thiếu trang đó, và lần quét sau bù lại.
     ⚠️ `dau=1` chỉ ở trang ĐẦU — nó bảo máy chủ xoá sổ cũ. Đặt ở trang cuối thì 4G rớt giữa
        chừng là sổ có lẫn cả người đã xoá lẫn người mới: sai mà nhìn như đúng. */
  String trang = ""; bool first = true; int soTrang = 0, dayOk = 0;
  int pos = 0, total = -1, count = 0; const int PAGE = 20;
  StaticJsonDocument<256> filter;
  filter["UserInfoSearch"]["numOfMatches"] = true;
  filter["UserInfoSearch"]["totalMatches"] = true;
  filter["UserInfoSearch"]["UserInfo"][0]["employeeNo"] = true;
  filter["UserInfoSearch"]["UserInfo"][0]["name"] = true;
  const String sidNv = acsSearchId();            // một mã cho cả vòng lật trang, mới ở mỗi lần quét
  for (int guard = 0; guard < 200; guard++) {
    int code;
    String body = "{\"UserInfoSearchCond\":{\"searchID\":\"" + sidNv + "\",\"searchResultPosition\":" + String(pos) +
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
      if (!first) trang += ",";
      trang += "{\"ma\":\"" + jsonEscMin_(emp) + "\",\"ten\":\"" + jsonEscMin_(nm)
             + "\",\"anh\":" + String(f ? "true" : "false") + "}";
      first = false; count++;
    }
    if (trang.length()) {
      String r = wpViec("roster", String("\"dau\":") + (soTrang == 0 ? "1" : "0") + ",\"ds\":[" + trang + "]", false);
      if (r.length()) dayOk++;
      else Serial.printf("[SCAN] ⚠️ Day trang %d HONG -> so tren web se thieu %d nguoi\n", soTrang, (int)0);
      soTrang++; trang = ""; first = true;
    }
    pos += numMatches;
    if (numMatches < PAGE || numMatches == 0 || (total >= 0 && pos >= total)) break;
  }
  Serial.printf("[SCAN] %d NV trên máy (total=%d), đẩy %d/%d trang lên website.\n",
                count, total, dayOk, soTrang);
}

// ---- Hàng đợi lệnh: hỏi thẳng WordPress ----
String g_doneOps[8]; int g_doneIdx = 0;                        // nhớ opId đã xử lý (tránh làm hai lần)
bool opDone(const String& op){ for(int i=0;i<8;i++) if(g_doneOps[i]==op) return true; return false; }
void opMarkDone(const String& op){ g_doneOps[g_doneIdx]=op; g_doneIdx=(g_doneIdx+1)&7; }

bool wpBaoXong(const String& opId, const String& ketQua);      // khai trước: wpLayLenh gọi tới

/**
 * Lấy MỘT lệnh đang chờ. Trả JSON phẳng {opId,action,employeeNo,…} — ĐÚNG tên trường mà
 * `checkEmployeeQueue` bên dưới đang đọc. Trả "" nghĩa là không có gì.
 *
 * 🔴 LỆNH ĐÃ XỬ LÝ MÀ MÁY CHỦ VẪN PHÁT LẠI = lượt "báo xong" trước đó rớt giữa đường (chuyện
 *    thường trên 4G). Báo xong LẦN NỮA rồi thôi vòng này. Bản Firebase cũ mắc đúng chỗ này:
 *    nó chỉ `continue`, mà URL đọc có `limitToFirst=1` nên vòng lặp chỉ có một lượt -> hàm trả
 *    "rỗng" -> lệnh nằm ở ĐẦU hàng và chặn sạch mọi lệnh phía sau. Máy vẫn báo online, vẫn chấm
 *    công (chấm công đi đường khác) nên nhìn hệt như "máy hỏng". Mất cả tối 03/08/2026.
 */
String wpLayLenh(){
  String r = wpViec("lenh", "", true);
  r.trim();
  if (r.length() == 0) return "";
  StaticJsonDocument<1024> d;
  if (deserializeJson(d, r)) { Serial.println("[NV] parse lenh loi"); return ""; }
  if (d["empty"] | false) return "";
  String op = String((const char*)(d["opId"] | ""));
  if (op.length() == 0) return "";
  if (opDone(op)) {
    Serial.println("[NV] Lenh da xu ly ma may chu con phat lai -> bao xong lan nua: " + op);
    wpBaoXong(op, "da xu ly tu truoc");
    return "";
  }
  return r;
}

/** Báo máy chủ đã xử lý xong một lệnh — thay lượt DELETE lên Firebase. */
bool wpBaoXong(const String& opId, const String& ketQua){
  String r = wpViec("xong", "\"opId\":\"" + jsonEscMin_(opId) + "\",\"ketQua\":\"" + jsonEscMin_(ketQua) + "\"", false);
  bool ok = (r.length() > 0);
  if (!ok) Serial.println("[NV] BAO XONG HONG: " + opId + " (may chu se phat lai lenh nay)");
  return ok;
}

void handleGetPhoto(const String& opId, const String& emp, const String& date,
                    const String& tstr, const String& which){
  /* Gửi kết quả về bằng chính lượt "báo xong": lỗi thì nằm ở cột kết quả của lệnh, ảnh thì đi
     việc `anh_tra`. Trước đây ghi vào /photoresp trên Firebase và web phải tự dọn. */
  String taLa = "\"opId\":\"" + jsonEscMin_(opId) + "\",\"employeeNo\":\"" + jsonEscMin_(emp)
              + "\",\"date\":\"" + jsonEscMin_(date) + "\",\"time\":\"" + jsonEscMin_(tstr)
              + "\",\"which\":\"" + jsonEscMin_(which) + "\"";
  if (date.length() < 10 || tstr.length() < 8) { wpBaoXong(opId, "thieu ngay/gio"); return; }

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
  String payload = "{\"AcsEventCond\":{\"searchID\":\"" + acsSearchId() + "\",\"searchResultPosition\":0,\"maxResults\":10,"
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
    wpBaoXong(opId, "khong tim thay anh (may co the da xoa anh cu)");
    return;
  }

  // 3) Tải ảnh -> base64 -> đẩy lên website
  uint8_t* jpeg = NULL;
  int jlen = fetchImageRaw(pic, &jpeg);
  if (jlen <= 0) { if (jpeg) free(jpeg); wpBaoXong(opId, "tai anh tu dau doc that bai"); return; }
  char* b64 = NULL;
  int b64len = base64Encode(jpeg, jlen, &b64);
  free(jpeg);
  if (b64len <= 0 || !b64) { if (b64) free(b64); wpBaoXong(opId, "het RAM khi encode anh"); return; }
  String body; body.reserve(b64len + 400);
  body = "{\"viec\":\"anh_tra\",\"hikSerial\":\"" + jsonEscMin_(HIK_SERIAL)
       + "\",\"macAddress\":\"" + jsonEscMin_(macBo()) + "\"," + taLa + ",\"anh\":\"";
  body += b64; body += "\"}";
  free(b64);
  bool ok = (wpGoi(body, false).length() > 0);
  Serial.printf("[TRICH] Đẩy ảnh %d byte b64 -> %s\n", b64len, ok ? "OK" : "FAIL");
  wpBaoXong(opId, ok ? "da tra anh" : "day anh len website that bai (anh co the qua lon cho 4G)");
}

// Poll hàng đợi nhân viên, xử lý 1 lệnh rồi báo xong cho website (ack)
void checkEmployeeQueue() {
  String resp = wpLayLenh();
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
  bool hasPhoto = DONG_BO_ANH && (bool)(d["hasPhoto"] | false);
  String date   = d["date"]       | "";                              // cho lệnh getphoto
  String tstr   = d["time"]        | "";
  String which  = d["which"]       | "";
  String bfStart = d["startTime"]  | "";                             // lệnh backfill (Tải lại)
  String bfEnd   = d["endTime"]    | "";
  bool   bfImage = d["bfImage"]    | false;
  /* 🔴 Lệnh KHÔNG có `action` là rác (web ghi dở, hay ai sửa tay trong bảng).
     Bản cũ chỉ `return` -> nó nằm mãi ở ĐẦU hàng và chặn sạch phía sau, y hệt ca 03/08. Có opId
     thì xoá được, nên xoá; không có opId thì mới đành thôi. */
  if (opId.length() == 0) { Serial.println("[NV] Lenh khong co opId -> bo qua"); return; }
  if (action.length() == 0) {
    Serial.println("[NV] Lenh rac (thieu action) -> xoa: " + opId);
    opMarkDone(opId); wpBaoXong(opId, "");
    return;
  }

  if (action == "scan") {                                            // QUÉT: đọc danh sách NV từ đầu đọc -> đẩy lên website
    Serial.printf("[NV] Lệnh quét máy (%s)\n", opId.c_str());
    showSync("scan", "may cham cong");
    hikScanRoster();
    opMarkDone(opId); wpBaoXong(opId, "");
    statusUntil = millis() + 4000;
    return;
  }

  if (action == "getphoto") {                                        // TRÍCH ẢNH chấm công theo yêu cầu (chống gian lận)
    Serial.printf("[TRICH] Lệnh trích ảnh %s: NV=%s ngày=%s giờ=%s (%s)\n",
                  opId.c_str(), emp.c_str(), date.c_str(), tstr.c_str(), which.c_str());
    showSync("trich anh", emp);
    handleGetPhoto(opId, emp, date, tstr, which);
    opMarkDone(opId); wpBaoXong(opId, "");              // xóa /queue (không đụng /photoresp - server tự xóa sau khi đọc)
    statusUntil = millis() + 3000;
    return;
  }

  if (action == "backfill") {                                        // Màn máy "Tải lại" -> máy nhận qua hàng đợi
    Serial.printf("[TẢI LẠI] %s: %s..%s emp=%s\n", opId.c_str(), bfStart.c_str(), bfEnd.c_str(), emp.c_str());
    opMarkDone(opId); wpBaoXong(opId, "");              // xóa lệnh TRƯỚC (backfill lâu -> tránh chạy lại)
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
    opMarkDone(opId); wpBaoXong(opId, "");
    return;
  }

  /* ⚠️ VAN AN TOÀN — 03/08/2026.
     Nhánh dưới CỐ Ý không ack để chờ RAM rảnh. Nhưng nếu RAM **không bao giờ** đủ (ảnh quá lớn,
     bộ nhớ phân mảnh) thì lệnh này nằm ở đầu hàng VĨNH VIỄN và chặn sạch phía sau — kể cả lệnh
     tải lại, kể cả lệnh xoá nhân viên. Đúng loại lỗi đã làm mất cả tối 03/08 với FZ_SC_VIVO_T4.
     Nay đếm số lần hoãn CÙNG một lệnh: quá `HOAN_TOI_DA` thì BỎ lệnh đó (xoá) để thông hàng.
     Bỏ một lệnh thêm/sửa NV thì lưu lại hồ sơ trên web là nó xếp lại — mất cả hàng đợi thì không
     cứu được bằng gì. */
  static String opHoan = ""; static int soHoan = 0;
  const int HOAN_TOI_DA = 30;                 // 30 vòng × 10 giây ≈ 5 phút
  if (hasPhoto && ESP.getFreeHeap() < MIN_HEAP_FOR_PHOTO) {
    if (opHoan == opId) soHoan++; else { opHoan = opId; soHoan = 1; }
    Serial.printf("[NV] Hoãn %s (lần %d/%d): heap %d < %d\n", opId.c_str(), soHoan, HOAN_TOI_DA,
                  ESP.getFreeHeap(), MIN_HEAP_FOR_PHOTO);
    if (soHoan < HOAN_TOI_DA) return;         // không ack -> xử lý lại vòng sau khi RAM rảnh
    Serial.println("[NV] Hoãn quá lâu -> BỎ lệnh này để thông hàng đợi: " + opId);
    opMarkDone(opId); wpBaoXong(opId, "");
    opHoan = ""; soHoan = 0;
    return;
  }
  if (opHoan == opId) { opHoan = ""; soHoan = 0; }   // qua được rồi thì xoá bộ đếm

  Serial.printf("\n[NV] Xử lý %s: %s NV=%s '%s' pin=%s photo=%d\n",
                opId.c_str(), action.c_str(), emp.c_str(), name.c_str(), pin.c_str(), hasPhoto);
  showSync(action, name);

  String msg;
  bool ok = processOp(action, emp, name, pin, gender, hasPhoto, opId, msg);
  Serial.printf("[NV] Kết quả %s: %s | %s\n", opId.c_str(), ok ? "DONE" : "FAIL", msg.c_str());
  opMarkDone(opId);
  // BÁO XONG DÙ done hay fail: máy chủ chỉ phát lệnh cũ nhất, để lệnh fail nằm lại là KẸT cả hàng.
  // (Fail đã in ở dòng trên; muốn thử lại thì thêm/sửa NV lại trên web.)
  wpBaoXong(opId, (ok ? String("ok ") : String("HONG ")) + msg);
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

/* ===========================================================================
 *  DẤU THỜI GIAN CỦA ĐẦU ĐỌC — CẮT MÚI GIỜ, KHÔNG ĐOÁN MÚI GIỜ NÀO
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026 — máy MỚI của anh Thắng in ra log khởi động:
 *        [KHỞI ĐỘNG] Bù dữ liệu bỏ lỡ từ 2026-08-07 17:06:09+08:00 ...
 *  Cái đuôi `+08:00` KHÔNG được phép có mặt trong `lastSyncTime`. Nó lọt vào vì hai chỗ bóc giờ
 *  đều viết CỨNG `eventTime.replace("+07:00", "")` — đầu đọc Hikvision MỚI XUẤT XƯỞNG mặc định
 *  múi giờ GMT+08 (Trung Quốc), nên chuỗi không khớp và cái đuôi ở lại. Hậu quả:
 *    · `lastSyncTime` = "2026-08-07 17:06:09+08:00", rồi lượt bù nối thêm "+07:00" nữa thành
 *      "2026-08-07T17:06:09+08:00+07:00" — mốc HỎNG gửi xuống đầu đọc;
 *    · lượt chấm công đẩy lên máy chủ mang nguyên đuôi đó -> tách ngày/giờ sai.
 *  Cắt cứng 19 ký tự đầu thì múi giờ nào cũng sạch, khỏi phải liệt kê.
 *
 *  ⚠️ Cắt đuôi chỉ làm chuỗi ĐÚNG ĐỊNH DẠNG — nó KHÔNG sửa được giờ sai. Đầu đọc để GMT+08 thì
 *     giờ nó ghi vẫn SỚM HƠN giờ Việt Nam 1 tiếng, và đó là tiền lương. Nên nhớ lại múi giờ đọc
 *     được và đẩy lên heartbeat để web kêu lên, thay vì lặng lẽ ghi sai cả tháng.
 * =========================================================================== */
String g_tzDoc = "";                 // múi giờ thấy trong lượt gần nhất ("+07:00" / "+08:00" / "")
String chuanGioDauDoc(String t){
  t.replace("T", " ");
  if (t.length() > 19){ g_tzDoc = t.substring(19); t = t.substring(0, 19); }
  else if (t.length() == 19) g_tzDoc = "";        // đầu đọc không kèm múi giờ
  return t;
}

/* ===========================================================================
 *  DỪNG ĐƯỢC LƯỢT BÙ ĐANG CHẠY  —  /stop/<trạm>
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026, anh Thắng: *"Đồng bộ anh thấy vẫn chạy, dù ngắt nó vẫn chạy ngầm, không ngăn
 *  cản được, dẫn đến tình trạng nghẽn"*.
 *
 *  Anh nói đúng, và trước bản này thì KHÔNG CÓ CÁCH NÀO ngắt. Ba lý do cộng lại:
 *    · lệnh tải lại bị XOÁ KHỎI HÀNG ĐỢI TRƯỚC khi chạy (dòng ~1825) — cố ý, để máy khởi động
 *      lại giữa chừng không chạy lại từ đầu. Nhưng hệ quả là xoá lệnh trên web sau đó **không
 *      còn tác dụng gì**: máy đã cầm lệnh trong tay rồi;
 *    · `backfillRange` là vòng CHẶN, chạy tới khi hết trang. Trong lúc đó `loop()` KHÔNG quay →
 *      máy không hỏi hàng đợi, không gửi heartbeat. Mọi lệnh gửi xuống lúc đó nằm chết ở đầu
 *      hàng, web đọc ra là "ĐƠ" — chính cái NGHẼN anh thấy;
 *    · lượt bù lúc khởi động và lượt bù ĐỊNH KỲ 30 phút KHÔNG có lệnh nào trong hàng đợi cả —
 *      web không có gì để mà xoá.
 *
 *  Nay: mỗi TRANG (20 lượt) máy liếc `/stop/<trạm>`; có cờ thì dừng ngay và tự xoá cờ. Web đặt
 *  cờ = ngắt được cả ba đường trên, kể cả lượt chạy ngầm.
 *  ⚠️ Cờ do CẢ HAI bên xoá (máy xoá khi dừng, web xoá mỗi lần ra lệnh tải lại mới). Chỉ một bên
 *     xoá là lỡ sót một lần thì mọi lượt bù sau đều chết câm — đúng kiểu hỏng im lặng tệ nhất.
 *  ⚠️ Liếc theo TRANG chứ không theo từng lượt: mỗi trang vốn đã là 20 lượt HTTP POST, thêm 1
 *     lượt GET nhỏ là ~5% — còn hỏi từng lượt thì gấp đôi lưu lượng 4G.
 * =========================================================================== */
/* Khai trước: `hbSend` định nghĩa ở DƯỚI `backfillRange` mà vòng bù phải gọi nó. Arduino tự sinh
   prototype được, nhưng dựa vào thứ đó là mỗi lần đổi chỗ hàm lại hồi hộp — khai thẳng cho chắc. */
void hbSend();
/* Máy chủ trả {"dung":1} MỘT LẦN rồi tự tiêu cờ — nên `bfXoaCoDung()` không còn việc gì và đã
   gỡ. Trước kia cờ nằm trên Firebase và CẢ HAI bên phải xoá; một bên sót là mọi lượt bù sau đều
   chết câm. Cờ tiêu ngay lúc đọc thì không còn ai phải nhớ xoá. */
bool bfXinDung(){
  String r = wpViec("dung", "", true);
  // "" = không đọc được (mất mạng/lỗi) -> KHÔNG coi là xin dừng. Đọc hỏng mà dừng bù là mất dữ liệu.
  if (r.length() == 0) return false;
  StaticJsonDocument<192> d;
  if (deserializeJson(d, r)) return false;
  return ((int)(d["dung"] | 0) == 1);
}
/* Máy CÓ ĐANG chạy lượt bù hay không — heartbeat mang lên để web phân biệt "đang bận" với "chết".
   Không có cái này thì web nhìn hàng đợi đứng im là kết luận ĐƠ, trong khi máy đang làm việc tử tế. */
bool g_dangBu = false; int g_buDaDay = 0; String g_buNgay = "";

/* ===========================================================================
 *  LƯỢT BÙ CHỈ ĐẨY GIỜ VÀO + GIỜ RA  —  bỏ hết lượt ở giữa
 * ---------------------------------------------------------------------------
 *  🔴 07/08/2026, anh Thắng: *"chỉ đọc giờ vào và giờ ra, tránh đọc lục tung, vì máy chấm công
 *  nhận diện liên tục nên sinh ra rất nhiều giờ… tránh nghẽn cổ chai"*.
 *
 *  Đúng, và quan trọng hơn: đẩy lượt ở giữa là PHÍ HOÀN TOÀN, không mất gì khi bỏ. Máy chủ lưu
 *  mỗi người mỗi ngày đúng MỘT CẶP [sớm nhất, muộn nhất] và quy tắc chỉ NỚI RỘNG, không bao giờ
 *  thu hẹp — lượt nằm giữa bị trả về `'giua'` và KHÔNG ghi gì cả (Mã.js `_ghiGioVaoRa`). Nên
 *  gom ở đây rồi chỉ đẩy 2 lượt/người/ngày là RA ĐÚNG KẾT QUẢ CŨ, chỉ khác là nhanh hơn nhiều lần.
 *
 *  Đây cũng là chỗ chữa gốc của cái nghẽn: mỗi lượt đẩy là một HTTP POST + delay(50), mà đầu đọc
 *  nhận diện khuôn mặt liên tục nên một người có thể sinh vài chục lượt/ngày. Bỏ lượt giữa là
 *  giảm số POST xuống đúng bằng 2 × số người.
 *
 *  ⚠️ Mảng để STATIC, KHÔNG để trên ngăn xếp: 80 phần tử × 83 byte ≈ 6,6 KB, đủ sức thổi bay
 *     ngăn xếp của tác vụ loop().
 *  ⚠️ Quá sức chứa thì KHÔNG được lặng lẽ bỏ: đánh dấu tràn rồi đẩy thẳng lượt đó như cách cũ.
 *     Thà chậm còn hơn mất một lượt chấm công.
 *  ⚠️ Dùng char cố định chứ không String: 320 đối tượng String là băm nhỏ đống nhớ, mà máy này
 *     vốn đã phải canh RAM cho ảnh khuôn mặt.
 * =========================================================================== */
#define BF_GOM_MAX 80
struct BfMoc { char emp[20]; char ten[34]; char ngay[11]; char som[9]; char muon[9]; };
static BfMoc g_bfGom[BF_GOM_MAX];
static int   g_bfGomN = 0;
static bool  g_bfGomTran = false;
static void bfGomXoa(){ g_bfGomN = 0; g_bfGomTran = false; }
/* Trả true nếu đã gom được (khỏi đẩy ngay); false = hết chỗ, người gọi phải tự đẩy. */
static bool bfGomThem(const String& emp, const String& ten, const String& ngay, const String& gio){
  for (int i = 0; i < g_bfGomN; i++){
    if (strcmp(g_bfGom[i].emp, emp.c_str()) == 0 && strcmp(g_bfGom[i].ngay, ngay.c_str()) == 0){
      if (gio < String(g_bfGom[i].som))  strlcpy(g_bfGom[i].som,  gio.c_str(), sizeof(g_bfGom[i].som));
      if (gio > String(g_bfGom[i].muon)) strlcpy(g_bfGom[i].muon, gio.c_str(), sizeof(g_bfGom[i].muon));
      return true;
    }
  }
  if (g_bfGomN >= BF_GOM_MAX){ g_bfGomTran = true; return false; }
  BfMoc& m = g_bfGom[g_bfGomN++];
  strlcpy(m.emp,  emp.c_str(),  sizeof(m.emp));
  strlcpy(m.ten,  ten.c_str(),  sizeof(m.ten));
  strlcpy(m.ngay, ngay.c_str(), sizeof(m.ngay));
  strlcpy(m.som,  gio.c_str(),  sizeof(m.som));
  strlcpy(m.muon, gio.c_str(),  sizeof(m.muon));
  return true;
}

// Bù lịch sử theo khoảng thời gian (phân trang toàn bộ log Hikvision).
// Google TỰ chống trùng (bỏ giờ đã ghi) nên chạy lại an toàn. 4G và WiFi đều gửi bản gọn (không ảnh).
int backfillRange(String startISO, String endISO, bool withImage, String empFilter) {
  int pos = 0, pushed = 0, seen = 0; const int PAGE = 20;
  bfGomXoa();
  g_dangBu = true; g_buDaDay = 0; g_buNgay = startISO.substring(0, 10);
  StaticJsonDocument<512> filter;
  JsonObject fi = filter["AcsEvent"]["InfoList"].createNestedObject();
  fi["serialNo"] = true; fi["time"] = true; fi["employeeNoString"] = true;
  fi["name"] = true;     fi["cardNo"] = true; fi["pictureURL"] = true;
  filter["AcsEvent"]["responseStatusStrg"] = true;
  filter["AcsEvent"]["numOfMatches"] = true;
  filter["AcsEvent"]["totalMatches"] = true;
  // MỘT mã cho cả vòng lật trang (đúng ý nghĩa searchID), nhưng phải MỚI ở mỗi lần chạy bù —
  // dùng lại "backfill" cho lần bù sau là đúng cái bẫy đã làm lượt trực tiếp chết.
  const String sidBu = acsSearchId();
  for (int guard = 0; guard < 1000; guard++) {
    if (!netUp()) { Serial.println("[BÙ] Mất mạng, dừng."); break; }
    /* Web bấm DỪNG -> thoát ngay tại đây. Đặt TRƯỚC lệnh hỏi đầu đọc: dừng rồi thì đừng tốn thêm
       một vòng ISAPI + 20 lượt POST nữa. */
    if (bfXinDung()) {
      Serial.println("[BÙ] Web xin DỪNG -> thoát (đã đẩy " + String(pushed) + " lượt).");
      /* cờ dừng tự tiêu ở máy chủ ngay lúc đọc — không phải xoá */ break;
    }
    /* Heartbeat NGAY TRONG vòng bù. Trước đây `loop()` đứng im suốt lượt bù nên máy đang chạy
       tử tế mà web đọc ra "mất tín hiệu / hàng đợi ĐƠ" — anh Thắng thấy nghẽn là ở đây. */
    if (millis() - lastHbMs >= HB_INTERVAL_MS) { lastHbMs = millis(); hbSend(); }
    String payload = "{\"AcsEventCond\":{\"searchID\":\"" + sidBu + "\",\"searchResultPosition\":" + String(pos) +
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
      String eventTime = chuanGioDauDoc(e["time"].as<String>());
      if (eventTime.length() < 19) continue;                 // giờ không đọc được -> bỏ, đừng gom rác
      /* GOM, KHÔNG ĐẨY. Chỉ đẩy ở vòng dưới, mỗi người mỗi ngày đúng 2 lượt.
         Hết chỗ gom thì đẩy thẳng như cách cũ — thà chậm còn hơn mất một lượt. */
      if (bfGomThem(emp, name, eventTime.substring(0, 10), eventTime.substring(11, 19))) {
        if (seen % 20 == 0) showBackfillProgress(pushed);
        continue;
      }
      char* imgB64 = NULL; int imgB64Len = 0;
      if (!USE_4G && withImage && pic.length() > 5) {   // 4G: bỏ ảnh (pushEvent cũng tự bỏ)
        uint8_t* jpeg = NULL; int jl = fetchImageRaw(pic, &jpeg);
        if (jl > 0) imgB64Len = base64Encode(jpeg, jl, &imgB64);
        if (jpeg) free(jpeg);
      }
      bool ok = pushEvent(emp, name, eventTime, imgB64, imgB64Len); imgB64 = NULL;
      if (ok) { pushed++; rememberSync(eventTime); g_buDaDay = pushed; }
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

  /* ---- Đẩy phần đã gom: mỗi người mỗi ngày ĐÚNG giờ VÀO + giờ RA ----
     Vào == ra (cả ngày chỉ quẹt 1 lần) thì chỉ đẩy MỘT lượt, đừng đẩy hai lần cùng một giờ. */
  Serial.printf("[BÙ] Gom xong: %d người-ngày từ %d lượt quét -> chỉ đẩy giờ VÀO + giờ RA.\n",
                g_bfGomN, seen);
  if (g_bfGomTran)
    Serial.println("[BÙ] ⚠️ Vượt sức chứa bảng gom — phần dư đã đẩy thẳng từng lượt (chậm nhưng không sót).");
  for (int i = 0; i < g_bfGomN; i++){
    if (!netUp()) { Serial.println("[BÙ] Mất mạng khi đang đẩy phần gom."); break; }
    if (bfXinDung()) { Serial.println("[BÙ] Web xin DỪNG giữa lúc đẩy."); /* cờ dừng tự tiêu ở máy chủ ngay lúc đọc — không phải xoá */ break; }
    if (millis() - lastHbMs >= HB_INTERVAL_MS) { lastHbMs = millis(); hbSend(); }
    String ngay = String(g_bfGom[i].ngay), emp = String(g_bfGom[i].emp), ten = String(g_bfGom[i].ten);
    String gio[2] = { String(g_bfGom[i].som), String(g_bfGom[i].muon) };
    int soGio = (gio[0] == gio[1]) ? 1 : 2;
    for (int k = 0; k < soGio; k++){
      String t = ngay + " " + gio[k];
      if (pushEvent(emp, ten, t, NULL, 0)) { pushed++; rememberSync(t); g_buDaDay = pushed; }
      delay(50);
    }
    showBackfillProgress(pushed);
  }
  Serial.printf("[BÙ] Hoàn tất: quét %d, đẩy %d lượt.\n", seen, pushed);
  g_dangBu = false;
  /* Gửi heartbeat NGAY khi xong: web thấy `dangBu` tắt là biết máy rảnh, khỏi chờ hết chu kỳ 60s
     mới dám kết luận. */
  lastHbMs = millis(); hbSend();
  return pushed;
}

// ② Nhịp sống: đẩy lên website mỗi 60s -> màn máy biết máy nào còn sống, và nhịp chở luôn lệnh OTA về.
/* ===========================================================================
 *  AI ĐANG NỐI VÀO AP CỦA ESP32 — và ở IP nào
 * ---------------------------------------------------------------------------
 *  🔴 03/08/2026, anh Thắng: *"có cách nào xác định được đầu đọc nối vào ESP đúng IP"*.
 *  Trước đây KHÔNG có: heartbeat chỉ gửi mốc giờ + số bản firmware, nên từ web chỉ suy được
 *  "đọc được / không đọc được đầu đọc" (cột Serial trống), chứ không biết đầu đọc đang ở IP nào
 *  — mà đó đúng là câu cần trả lời khi lắp máy mới. Kết quả: phải ra tận cửa hàng mới biết.
 *
 *  ESP32 CHÍNH LÀ router của đầu đọc (softAP 192.168.4.1) nên nó tự dò được. Đem lên heartbeat
 *  là chẩn đoán được TỪ XA:
 *    · apSo = 0            -> đầu đọc chưa nối AP (sai mật khẩu WiFi / chưa khai / ngoài vùng)
 *    · apSo ≥ 1, hikOk=0   -> đã nối nhưng KHÔNG trả lời ISAPI: so apIp với hikIp là ra ngay
 *                             "sai IP" hay "đúng IP nhưng sai mật khẩu ISAPI"
 *    · hikOk=1             -> tốt
 *  Chỉ ĐỌC trạng thái, không đổi hành vi gì.
 * =========================================================================== */
/* 🔴 LẦN ĐẦU EM LÀM SAI, CI BẮT ĐƯỢC — ghi lại để không lặp:
   bản đầu lấy IP máy con bằng `esp_netif_get_sta_list()` + `esp_netif_sta_list_t`, bọc
   `#if __has_include(<esp_netif.h>)`. Guard đó VÔ DỤNG: header CÓ, nhưng IDF 5 (core 3.3.10)
   đã bỏ chính hai cái tên đó -> compile đỏ 4 dòng. Bài học: `__has_include` chỉ chứng minh có
   FILE, không chứng minh có HÀM. Máy soạn bản này không biên dịch được nên phải dùng thứ chắc
   chắn có, hoặc chờ CI xác nhận — đừng suy từ "header tồn tại".

   Nay dò thẳng: ESP32 là router của đầu đọc, mà dải DHCP của softAP bắt từ 192.168.4.2, nên
   thử mở cổng 80 từ .2 tới .12 là biết đầu đọc THẬT đang ở IP nào. Trả lời đúng câu anh Thắng
   hỏi ("nối đúng IP chưa") mà không cần API tầng IDF nào.
   ⚠️ CHỈ dò khi CHƯA đọc được đầu đọc, và tối đa 5 phút một lần: lúc đó máy vốn không ghi được
      lượt nào nên 3 giây dò không mất gì; máy đang chạy tốt thì hàm này thoát ngay ở dòng đầu. */
String g_apDoIp = "";
unsigned long g_apDoLuc = 0;
String apDoIpMoCong80(){
  if (HIK_SERIAL.length()) return "";                                  // đang tốt -> khỏi dò
  if (g_apDoLuc && millis() - g_apDoLuc < 300000UL) return g_apDoIp;   // nhớ kết quả 5 phút
  g_apDoLuc = millis();
  String out;
  /* 🔴 03/08 (bản j) — LỖI CỦA BẢN i: dò .2 → .12 mà **BỎ QUÊN chính `hik_ip` (.50)**.
     Hậu quả thật ở FZ_LTVT: đầu đọc nằm đúng .50 và trả lời cổng 80, nhưng dải dò không có .50
     nên `apIp` rỗng -> web kết luận "chưa lấy được danh sách máy con để so", tức là NÓI KHÔNG
     BIẾT trong khi đã có đủ dữ liệu để nói "đúng IP, chỉ sai mật khẩu ISAPI".
     Nay dò `hik_ip` TRƯỚC — nó là IP quan trọng nhất, và cũng là cái phân biệt "sai IP" với
     "đúng IP mà ISAPI không trả lời". */
  {
    WiFiClient c0;
    if (c0.connect(hik_ip, 80, 400)) { out = String(hik_ip); c0.stop(); }
  }
  for (int i = 2; i <= 12; i++) {
    String ip = "192.168.4." + String(i);
    if (ip == String(hik_ip)) continue;                 // đã dò ở trên
    WiFiClient c;
    if (c.connect(ip.c_str(), 80, 300)) {                              // 300ms/IP -> cả vòng ~3s
      if (out.length()) out += ",";
      out += ip;
      c.stop();
    }
  }
  g_apDoIp = out;
  Serial.println("[AP] Do cong 80 tren dai AP -> " + (out.length() ? out : String("khong thay gi")));
  return out;
}

/* Bản OTA máy chủ đang đặt cho máy này — nhịp sống chở về, `checkOtaUpdate` tiêu thụ. */
String g_otaVer = "", g_otaUrl = "";

void hbSend() {
  if (!netUp()) return;
  /* Kèm CHẨN ĐOÁN ĐẦU ĐỌC. Thêm ~70 byte mỗi 60 giây — không đáng gì so với chính cái bắt tay
     TLS của lượt này, mà đổi lại là khỏi ra cửa hàng để biết máy có với tới đầu đọc. */
  /* `hikOk` = ISAPI CÓ trả lời trong 10 phút gần đây, KHÔNG phải "có serial" (xem ghi chú ở
     `hikRequest`). Kèm `hikHttp` để web nói đúng nguyên nhân thay vì chỉ "không đọc được". */
  bool ispiSong = (g_hikOkLuc && (millis() - g_hikOkLuc) < 600000UL);
  // ⚠️ PHẢI escape: FW_VERSION là chuỗi người viết tay, có 1 dấu nháy là JSON hỏng.
  String cd = "\"fw\":\"" + jsonEscMin_(String(FW_VERSION)) + "\""
            + ",\"duong\":\"" + String(USE_4G ? "4g" : "wifi") + "\""
            + ",\"ip\":\""    + (USE_4G ? String("") : WiFi.localIP().toString()) + "\""
            + ",\"heap\":"    + String((long)ESP.getFreeHeap())
            + ",\"hik\":\""   + String(g_hikHttp) + "\""
            + ",\"hikIp\":\"" + String(hik_ip) + "\""
            + ",\"hikOk\":"   + String((ispiSong || HIK_SERIAL.length()) ? 1 : 0)
            + ",\"soTu\":\""  + jsonEscMin_(g_soTu) + "\""
            + ",\"soTong\":"  + String(g_soTong)
            + ",\"soSo\":"    + String(g_soSo)
            + ",\"soChot\":\"" + String(g_soChot) + "\""
            + ",\"soPhut\":"  + String(g_soLuc ? (long)((millis() - g_soLuc) / 60000UL) : -1)
            + ",\"apSo\":"    + String(WiFi.softAPgetStationNum())
            + ",\"apIp\":\""  + apDoIpMoCong80() + "\""
            /* Đang chạy lượt bù hay không. Web CẦN số này để đừng báo "hàng đợi ĐƠ" trong khi
               máy đang làm việc — lúc bù thì `loop()` không quay nên hàng đợi đứng im là ĐÚNG. */
            + ",\"dangBu\":"  + String(g_dangBu ? 1 : 0)
            + ",\"buDaDay\":" + String(g_buDaDay)
            + ",\"buNgay\":\"" + jsonEscMin_(g_buNgay) + "\""
            /* MAC AP: máy trạm dò WiFi chỉ thấy BSSID = MAC của AP = MAC gốc **+1** ở nhóm cuối,
               nên số anh Thắng đọc trên máy trạm luôn lệch 1 với MAC trong bảng. Khai cả hai. */
            + ",\"macAp\":\"" + jsonEscMin_(WiFi.softAPmacAddress()) + "\""
            /* Múi giờ ĐẦU ĐỌC đang đóng vào lượt chấm công. Đầu đọc mới xuất xưởng để GMT+08 thì
               mọi lượt ghi SỚM HƠN 1 tiếng — sai lương mà không ai thấy. Đẩy lên để web kêu. */
            + ",\"tzDoc\":\"" + jsonEscMin_(g_tzDoc) + "\"";

  String r = wpViec("nhip", cd, true);
  if (r.length() == 0) return;
  StaticJsonDocument<512> d;
  if (deserializeJson(d, r)) return;

  /* MỘT lượt nhịp trả lời luôn bốn câu — trước đây là bốn lượt gọi riêng (nhịp, whoami, OTA,
     cờ dừng). Trên 4G mỗi lượt AT-HTTP mất 3-6 giây; bốn lượt mỗi phút × 26 máy là nghẽn. */
  String cs = String((const char*)(d["coSo"] | "")); cs.trim();
  if (cs.length() && cs != STATION_NAME) {
    bool sach = true;
    for (unsigned i = 0; i < cs.length(); i++){
      char c = cs.charAt(i);
      if (!((c>='0'&&c<='9')||(c>='a'&&c<='z')||(c>='A'&&c<='Z')||c=='_'||c=='-')) { sach = false; break; }
    }
    if (sach) {
      Serial.println("[CƠ SỞ] nhip: may nay thuoc '" + cs + "' (truoc la '" + STATION_NAME + "')");
      STATION_NAME = cs; STATION_TU_SERVER = true; prefs.putString("station", cs);
    }
  }
  g_otaVer = String((const char*)(d["otaVer"] | ""));
  g_otaUrl = String((const char*)(d["otaUrl"] | ""));
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

// Bản đích do nhịp sống chở về; khác bản đang chạy -> tải + flash + khởi động lại.
/**
 * NẠP FIRMWARE MỚI.
 *
 * Không còn hỏi riêng một lượt: bản đích do NHỊP SỐNG chở về (`g_otaVer`/`g_otaUrl`), và máy chủ
 * đã tự so với bản máy đang chạy nên trả rỗng khi không có gì mới. Bớt một lượt gọi mỗi 5 phút
 * trên 4G, và bớt một chỗ để hai bên hiểu khác nhau về "bản mới".
 */
void checkOtaUpdate(){
  if (!netUp()) return;
  String ver = g_otaVer, furl = g_otaUrl;
  if (ver.length() == 0 || furl.length() == 0) return;
  if (ver == String(FW_VERSION)) return;               // đang chạy đúng bản đó rồi
  if (ver == prefs.getString("otaVer", "")) return;    // đã áp bản này rồi
  if (ver == g_otaTriedVer) return;                    // đã thử (lỗi) trong phiên này -> chờ reboot / bản mới hơn
  g_otaTriedVer = ver;
  Serial.println("[OTA] Có bản mới: " + ver + " (đang chạy: " FW_VERSION ")");
  if (otaDownloadAndFlash(furl)) { prefs.putString("otaVer", ver); Serial.println("[OTA] Khởi động lại bản mới..."); delay(800); ESP.restart(); }
  else { Serial.println("[OTA] Cập nhật thất bại -> giữ firmware cũ."); showIdle(); }
}

// ---------- Kiểm tra lượt chấm công mới ----------
void checkNewAcsEvents() {
  // ⚠️ PHẢI có startTime/endTime. Đây là điểm KHÁC DUY NHẤT giữa lệnh chạy được và lệnh chết:
  //      lượt bù  (backfillRange) -> CÓ startTime/endTime -> chạy
  //      trích ảnh (trichAnhTheoOp) -> CÓ startTime/endTime -> chạy
  //      lượt trực tiếp (hàm này)   -> KHÔNG có           -> luôn -1
  //    Cả ba đều gọi CHUNG hikPost, cùng một endpoint, cùng đầu đọc, cách nhau vài giây.
  //    ISAPI của Hikvision đòi khoảng thời gian trong AcsEventCond; thiếu thì đầu đọc phải quét
  //    toàn bộ sổ và nó NGẮT KẾT NỐI -> HTTPClient trả -1, trông y như lỗi mạng.
  //    Khớp luôn "chạy 1-2 lần rồi thôi": sổ còn ít thì máy chịu được, sổ dài ra là chết hẳn.
  /* 🔴 01/08/2026 — BỎ HẲN kiểu "đếm tổng rồi nhảy tới total-WINDOW".
     Bằng chứng ngoài hiện trường (hai dòng [SỔ] cách nhau 1 phút, lúc đang quẹt thật):
         [SỔ] tổng=186  đã đẩy tới serial=3089
         [SỔ] tổng=197  đã đẩy tới serial=3089
     Tổng TĂNG 11 lượt mà con trỏ serial KHÔNG nhích. Lượt mới có thật, đầu đọc đếm được, nhưng
     cửa sổ đọc không chứa lượt mới nào. Vì `pos = total - WINDOW` GIẢ ĐỊNH đầu đọc trả kết quả
     theo thứ tự CŨ -> MỚI. Máy này trả MỚI -> CŨ, nên total-WINDOW nhảy đúng vào chỗ CŨ NHẤT:
     serial nào cũng <= lastSerialNo -> "không có gì mới" -> IM LẶNG mãi mãi.
     Lượt bù chạy được chính vì nó LUÔN bắt đầu từ pos=0.

     Nay: pos=0 + khoảng thời gian HẸP (từ lượt cuối đã đẩy). Cách này KHÔNG còn phụ thuộc thứ
     tự máy trả về — khoảng hẹp thì WINDOW lượt đầu đã bao trọn phần mới, xếp kiểu nào cũng đúng.
     Bớt luôn 1 lệnh mỗi vòng: totalMatches lấy trong CÙNG phản hồi, khỏi gọi riêng để đếm. */
  String tuNgay = acsMocTuLuotCuoi();
  g_soTu = tuNgay;                       // để heartbeat nói được "đọc từ mốc nào"

  // ⚠️ TỪ ĐÂY KHÔNG CÒN `return` IM LẶNG NÀO TRONG HÀM NÀY.
  //    Bản trước thoát ra mà không in một chữ, nên ngoài hiện trường thấy: không có dòng LỖI,
  //    cũng không có [LƯỢT MỚI] — không biết hàm có chạy hay không, cũng không biết đầu đọc trả
  //    gì. Mất bốn lượt chẩn đoán vì đúng chỗ này.
  //    Đây là đường TIỀN LƯƠNG: thà log dài còn hơn im lặng. In dồn 60s/lần cho khỏi rác.
  static unsigned long inLanCuoi = 0;
  bool inDuoc = (inLanCuoi == 0) || (millis() - inLanCuoi >= 60000);

  String payload2 = "{\"AcsEventCond\":{\"searchID\":\"" + acsSearchId() +
                    "\",\"searchResultPosition\":0,\"maxResults\":" + String(WINDOW) +
                    ",\"major\":0,\"minor\":0,\"startTime\":\"" + tuNgay +
                    "\",\"endTime\":\"" + String(FAR_FUTURE) + "\"}}";
  String r2 = hikPost(payload2);
  if (r2.length() == 0) {
    if (inDuoc) { inLanCuoi = millis();
      Serial.printf("[SỔ] Đọc %d lượt từ %s: KHÔNG có phản hồi (hikPost đã in lý do ở trên).\n",
                    WINDOW, tuNgay.c_str()); }
    return;
  }

  StaticJsonDocument<400> filter;
  JsonObject fi = filter["AcsEvent"]["InfoList"].createNestedObject();
  fi["serialNo"] = true;
  fi["time"] = true;
  fi["employeeNoString"] = true;
  fi["name"] = true;
  fi["cardNo"] = true;
  fi["pictureURL"] = true;
  filter["AcsEvent"]["totalMatches"] = true;    // lấy luôn trong phản hồi này, khỏi gọi lệnh đếm riêng
  filter["AcsEvent"]["numOfMatches"] = true;

  DynamicJsonDocument doc(16384);
  if (deserializeJson(doc, r2, DeserializationOption::Filter(filter))) {
    String x = r2; x.replace("\n"," "); x.replace("\r"," ");
    Serial.println("[SỔ] Đầu đọc trả về thứ KHÔNG PHẢI JSON hợp lệ -> " + x.substring(0, 200));
    return;
  }
  long total = doc["AcsEvent"]["totalMatches"] | 0;
  g_soTong = total;
  g_soLuc  = millis();

  JsonArray list = doc["AcsEvent"]["InfoList"].as<JsonArray>();
  g_soSo = (list.isNull() ? 0 : (long)list.size());
  if (list.isNull() || list.size() == 0) {
    // Đầu đọc trả lời tử tế nhưng danh sách rỗng. Ca hay gặp nhất: chưa có lượt nào MỚI kể từ
    // mốc `tuNgay` — bình thường. Nhưng nếu total > 0 mà rỗng thì có chuyện, phải nói ra.
    if (inDuoc) { inLanCuoi = millis();
      if (total > 0) {
        String x = r2; x.replace("\n"," "); x.replace("\r"," ");
        Serial.printf("[SỔ] tổng=%ld nhưng danh sách RỖNG (từ %s) -> %s\n",
                      total, tuNgay.c_str(), x.substring(0, 200).c_str());
        Serial.println("        giờ ESP32 : " + gioEspISO());
        Serial.println("        giờ đầu đọc: " + hikGioMay());
      } else {
        Serial.printf("[SỔ] Chưa có lượt nào mới kể từ %s (đã đẩy tới serial=%ld)\n",
                      tuNgay.c_str(), lastSerialNo);
      } }
    return;
  }
  if (inDuoc) { inLanCuoi = millis();
    Serial.printf("[SỔ] đọc %u lượt (tổng %ld trong khoảng, từ %s)  đã đẩy tới serial=%ld\n",
                  (unsigned)list.size(), total, tuNgay.c_str(), lastSerialNo); }
  /* ===========================================================================
   *  🔴🔴 04/08/2026 — VÌ SAO K1T320 CHỈ LÊN SAU KHI RÚT ĐIỆN GẮN LẠI
   * ---------------------------------------------------------------------------
   *  Anh Thắng: *"chấm giữa giờ thì không đẩy lên và TRÊN ESP CŨNG KHÔNG NHẬN, rút điện gắn
   *  lại nó mới đọc"*.
   *
   *  Đã so TỪNG TRƯỜNG hai lệnh đọc sổ: lượt trực tiếp và lượt bù dùng **cùng** endpoint,
   *  **cùng** major/minor, **cùng** khoảng thời gian (bù lấy `lastSyncTime`+"+07:00", trực tiếp
   *  lấy đúng mốc đó lùi 60 giây). Khác nhau ĐÚNG MỘT CHỖ:
   *
   *      lượt trực tiếp CHỐT THEO `serialNo`   ·   lượt bù KHÔNG dùng `serialNo` chút nào
   *
   *  ⚠️⚠️ BẢN 04b ĐOÁN SAI Ở ĐÂY — GIỮ LẠI LÀM BÀI HỌC, ĐỪNG TIN LẠI:
   *  04b đoán "K1T320 không trả `serialNo` nên `sn = 0` -> bỏ qua mãi mãi", rồi chỉ vá riêng
   *  nhánh không-serialNo. Số đo hiện trường bác bỏ: máy báo **`chốt theo=serial`**, tức đầu đọc
   *  CÓ trả `serialNo`, nên nhánh vá đó chưa từng chạy — nạp xong vẫn không lên.
   *
   *  SỰ THẬT (04c): `tổng=5 · trả về=5 · chốt theo=serial` -> ISAPI trả 2xx, về đủ 5 dòng, rồi
   *  5 dòng đó bị chính bộ lọc `sn <= lastSerialNo` ném đi. Vậy `serialNo` của đời máy này KHÔNG
   *  phải mã lượt toàn cục tăng dần — gần như chắc là **thứ tự trong kết quả tìm** (1..N, mỗi lần
   *  tìm lại đánh số lại từ 1). Sau lần đồng bộ đầu, `lastSerialNo` luôn ≥ số của mọi dòng mới.
   *  Lượt bù không xét `serialNo` nên vẫn lấy đủ -> rút điện gắn lại là lên, khớp đúng anh thấy.
   *
   *  Chữa (04c): **bỏ hẳn `serialNo` làm khoá chốt, chốt theo THỜI GIAN cho MỌI đời máy** — đúng
   *  cơ chế lượt bù vẫn dùng, và lượt bù chạy được trên cả hai đời máy. Máy chủ vốn tự bỏ giờ đã
   *  ghi nên đẩy trùng vô hại.
   *  ⚠️ ĐỪNG dựng lại hai nhánh riêng cho hai đời máy: chính việc đó sinh ra lỗi này. Một trường
   *     JSON mang nghĩa khác nhau giữa hai đời máy thì không có cách "sửa cho đúng" — phải ngừng
   *     dùng nó. `coSerial` từ đây chỉ để BÁO lên web, không quyết định gì.
   * =========================================================================== */
  long maxSerial = lastSerialNo; bool stop = false;   // maxSerial chỉ để ghi lại, KHÔNG dùng để lọc
  bool coSerial = false;         // đầu đọc này có cho serialNo dùng được không
  String tMoiNhat = "";          // mốc mới nhất thấy trong trang (dùng khi KHÔNG có serialNo)
  /* 🔴 CHỐT MỐC ĐẦU VÒNG, không so với `lastSyncTime` đang chạy.
     Mô phỏng bằng Node bắt được: đầu đọc trả trang theo thứ tự MỚI→CŨ (đúng kiểu K1T320) thì lượt
     đầu tiên đẩy xong `rememberSync` nâng `lastSyncTime` lên NGAY, nên các lượt CŨ HƠN trong CÙNG
     trang liền trượt điều kiện `<= lastSyncTime` và bị loại OAN — trang 3 lượt mới chỉ lên 1.
     So với bản chụp đầu vòng thì thứ tự máy trả về không còn ảnh hưởng gì. */
  const String mocDau = lastSyncTime;

  for (JsonObject e : list) {
    long sn = e["serialNo"] | 0;
    if (sn > 0) coSerial = true;
    if (sn > maxSerial) maxSerial = sn;

    /* Lấy giờ TRƯỚC khi lọc: bộ lọc chốt theo THỜI GIAN cần chính con số này. */
    String eventTime = chuanGioDauDoc(e["time"].as<String>());
    if (eventTime.length() >= 19 && eventTime > tMoiNhat) tMoiNhat = eventTime;

    if (stop) continue;                            // đã gặp 1 lỗi -> để dành vòng sau (không bỏ sót)
    /* ===========================================================================
     *  🔴🔴 04/08/2026 — BỎ HẲN `serialNo` LÀM KHOÁ CHỐT (bản 04c)
     * ---------------------------------------------------------------------------
     *  SỐ THẬT từ hiện trường (FZ_LTVT, K1T320, bản 04b):
     *      tổng=5 · trả về=5 · chốt theo=**serial** · mốc=2026-08-04T02:23:03+07:00 · 0 phút trước
     *  Ba điều đọc ra:
     *    · đầu đọc CÓ trả `serialNo` -> giả thuyết "không trả serialNo" của bản 04b **SAI**,
     *      nhánh vá đó không hề chạy;
     *    · đọc sổ ra ĐỦ 5 dòng, ISAPI trả 2xx -> không phải mạng, không phải IP, không phải mật khẩu;
     *    · vậy 5 dòng đó bị chính bộ lọc `sn <= lastSerialNo` loại sạch.
     *  ⇒ `serialNo` của đời máy này KHÔNG phải mã lượt toàn cục tăng dần (rất có thể là **thứ tự
     *    trong kết quả tìm**, 1..N mỗi lần tìm). Dùng nó làm khoá chốt là sai từ gốc, và không có
     *    cách nào "sửa cho đúng" khi chính nghĩa của trường đó khác nhau giữa hai đời máy.
     *
     *  Chốt theo THỜI GIAN cho MỌI đầu đọc — đúng cơ chế `backfillRange` vẫn dùng, và lượt bù
     *  chạy được trên CẢ HAI đời máy (đó là lý do rút điện gắn lại thì lên). Máy chủ vốn tự bỏ giờ
     *  đã ghi nên đẩy trùng vô hại.
     *
     *  ⚠️ Dùng `>=` chứ không `>`: hai người quẹt trong CÙNG một giây thì `>` làm mất người thứ
     *     hai — mất công của nhân viên, không được. Bù lại phải chống đẩy lại vô hạn, nên có
     *     `daDay` (vòng nhớ khoá "mã|giờ" gần nhất, cỡ = DA_DAY_TOI_DA) chặn tại chỗ, khỏi
     *     phụ thuộc máy chủ.
     *  ⚠️ K1T343 cũng đổi sang nhánh này. Cố ý: giữ hai đường khác nhau cho hai đời máy chính là
     *     cách sinh ra lỗi tối nay. Đường mới là đường mà lượt bù của CHÍNH K1T343 vẫn đi.
     * =========================================================================== */
    /* Chưa có mốc (máy mới / vừa xoá flash) -> vòng này CHỈ nhận mốc, KHÔNG dội lịch sử lên sheet.
       ⚠️ Phải GHI NHỚ luôn mấy lượt đang có: mô phỏng bắt được nếu không ghi thì vòng poll ngay
          sau đó thấy chúng "mới hơn hoặc bằng mốc" và đẩy lên — tức là vẫn dội lịch sử, chỉ chậm
          một nhịp. */
    if (mocDau.length() < 19) { ghiDaDay(emp0(e), eventTime); continue; }
    if (eventTime.length() < 19 || eventTime < mocDau) continue;
    if (daDayRoi(emp0(e), eventTime)) continue;    // đã đẩy trong vòng nhớ -> bỏ

    String emp  = e["employeeNoString"] | "";
    String name = e["name"] | "";
    String card = e["cardNo"] | "";
    String pic  = e["pictureURL"] | "";
    if (emp.length() == 0) emp = card;
    /* Rác (không mã, không tên) -> bỏ. GHI vào vòng nhớ để vòng poll sau khỏi xét lại. */
    if (emp.length() == 0 && name.length() == 0) { ghiDaDay(emp0(e), eventTime); continue; }

    int evSec = timeToSec(eventTime);
    if (emp == lastEmp && evSec >= 0 && (evSec - lastEmpSec) >= 0 && (evSec - lastEmpSec) < DEBOUNCE_SEC) {
      Serial.printf("[BỎ QUA] %s quẹt trùng trong %ds\n", emp.c_str(), DEBOUNCE_SEC);
      /* ⚠️ PHẢI ghi vào vòng nhớ, đừng dựa vào `lastEmp`: người khác quẹt xen vào là `lastEmp` đổi,
         vòng poll sau lượt trùng này không còn ai chặn -> đẩy lên. Ghi ở đây thì chặn dứt điểm. */
      ghiDaDay(emp, eventTime); continue;
    }

    Serial.println("\n[LƯỢT MỚI]");
    Serial.print("   NV: "); Serial.print(emp); Serial.print("  Tên: "); Serial.println(name);
    Serial.print("   Thời gian: "); Serial.println(eventTime);
    // In tài nguyên ở MỖI lượt: so mấy dòng này với nhau là thấy ngay có tụt dần hay không.
    Serial.printf("   [TÀI NGUYÊN] heap=%u nhomax=%u apKhach=%u\n", (unsigned)ESP.getFreeHeap(),
                  (unsigned)ESP.getMaxAllocHeap(), (unsigned)WiFi.softAPgetStationNum());

    uint8_t* jpeg = NULL; int jpegLen = 0; char* imgB64 = NULL; int imgB64Len = 0;
    // 4G: KHÔNG gửi ảnh lúc chấm công (chỉ trích khi bấm nút) -> bỏ tải+encode ảnh cho nhẹ + tránh "Hết RAM".
    if (!USE_4G && pic.length() > 5) {
      Serial.println("   [ẢNH] Đang tải ảnh khuôn mặt từ máy...");
      jpegLen = fetchImageRaw(pic, &jpeg);
      if (jpegLen > 0) imgB64Len = base64Encode(jpeg, jpegLen, &imgB64);
      if (jpeg) { free(jpeg); jpeg = NULL; }
    }

    showThankYou(name, eventTime, NULL, 0);

    /* CHỈ ghi vòng nhớ + đẩy mốc đồng bộ khi gửi THÀNH CÔNG -> mất mạng/đẩy lỗi KHÔNG mất lượt
       (vòng sau `eventTime >= mocDau` mà chưa có trong vòng nhớ nên được thử lại). */
    bool ok = pushEvent(emp, name, eventTime, imgB64, imgB64Len);
    imgB64 = NULL;   // quyền sở hữu đã chuyển cho pushEvent
    if (ok) { lastEmp = emp; lastEmpSec = evSec;
              ghiDaDay(emp, eventTime); rememberSync(eventTime); }
    else { stop = true; Serial.printf("[GIỮ] serial %ld gửi lỗi -> thử lại vòng sau (không mất lượt)\n", sn); }
  }

  /* Đầu đọc KHÔNG cho serialNo -> KHÔNG được chạm `lastSerialNo` (để 0 vào đó là khoá chết nhánh
     serialNo mãi mãi, đúng cái bẫy vừa sửa). Mốc thời gian do `rememberSync` lo. */
  /* Chốt theo THỜI GIAN cho mọi đời máy. `coSerial` giờ chỉ để BÁO cho web biết đầu đọc có trả
     serialNo hay không — không còn dùng để quyết định gì. */
  g_soChot = coSerial ? "thoi-gian (dau doc co serialNo)" : "thoi-gian";
  if (mocDau.length() < 19 && tMoiNhat.length() >= 19) {
    rememberSync(tMoiNhat);
    Serial.println("[KHỞI TẠO] Theo dõi theo THỜI GIAN từ " + tMoiNhat);
  }
  /* Vẫn ghi `lastSerialNo` để bản cũ lùi về được mà không thấy con trỏ lạ. KHÔNG dùng để lọc nữa. */
  if (maxSerial > lastSerialNo) { lastSerialNo = maxSerial; prefs.putLong("lastSN", lastSerialNo); }
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
/**
 * URL để IN RA LOG: che phần bí mật nếu có, giữ nguyên phần còn lại.
 * Link KHÔNG phải bí mật, mà che đi thì hết đường chẩn đoán — đã mất công vì portal che link.
 * (Khoá máy nay đi trong THÂN JSON chứ không trong URL, nên URL không còn gì phải che; hai
 * nhánh dưới giữ lại cho ca gọi tay bằng URL có tham số.)
 */
String urlDeIn(const String& u){
  String o = u;
  int i = o.indexOf("token=");
  if (i >= 0){ int e = o.indexOf('&', i); o = o.substring(0, i+6) + "…che…" + (e>=0 ? o.substring(e) : ""); }
  i = o.indexOf("auth=");
  if (i >= 0){ int e = o.indexOf('&', i); o = o.substring(0, i+5) + "…che…" + (e>=0 ? o.substring(e) : ""); }
  return o;
}
/**
 * Đặt AT+HTTPPARA="URL" và KIỂM kết quả. Trước đây kết quả bị bỏ đi, nên URL bị module
 * từ chối (quá dài / ký tự lạ) mà vẫn chạy tiếp tới HTTPACTION rồi báo một con số 7xx
 * không hiểu từ đâu ra. Nay hỏng ở đâu biết ngay ở đó.
 */
bool atDatUrl(const String& url, const char* nhan){
  Serial.printf("   [%s] URL len=%d: %s\n", nhan, url.length(), urlDeIn(url).c_str());
  /* ⚠️ 31/07/2026 — CHƯA CÓ LINK thì NÓI RA, đừng chọc module.
     Bản 31b bắt đầu từ chối link sai dạng (execUrlHopLe) nên cfgLay trả "" — rồi code vẫn
     gọi AT+HTTPPARA="URL","" và module đáp 7xx. Nhìn log chỉ thấy "status=713", không ai
     đoán được là do CHƯA KHAI LINK. Một câu tiếng người ở đây tiết kiệm được cả buổi. */
  if (!url.startsWith("http")) {
    Serial.printf("   [%s] 🔴 CHUA CO LINK — may chua duoc khai link website.\n", nhan);
    Serial.println("        Vao http://192.168.4.1 (WiFi CHAM_CONG) khai o muc cau hinh,");
    Serial.println("        hoac sua SEC_WP_URL / SEC_WP_KEY trong secrets.h roi nap lai.");
    Serial.println("        Link phai dang: https://<ten mien>/cham-cong-may (khong co / o cuoi)");
    return false;
  }
  Serial2.print("AT+HTTPPARA=\"URL\",\""); Serial2.print(url); Serial2.print("\"\r\n");
  String r = atWait("OK", 3000);
  if (r.indexOf("OK") < 0){
    r.replace("\r"," "); r.replace("\n"," "); r.trim();
    Serial.printf("   [%s] ⚠️ MODULE TU CHOI URL -> '%s'\n", nhan, r.c_str());
    return false;
  }
  return true;
}
/** In nguyên văn dòng +HTTPACTION (và mọi thứ module trả về) — 3 trường, không chỉ mã. */
void inHttpAction(const String& r, const char* nhan){
  String t = r; t.replace("\r"," "); t.replace("\n"," "); t.trim();
  Serial.printf("   [%s] module tra: '%s'\n", nhan, t.c_str());
}
/* ⛔ `net4gHttpPost()` và `net4gHttpPut()` — ĐÃ GỠ 22/08/2026.
   Cả hai HTTPTERM ngay sau ACTION nên vứt luôn thân trả về: đủ dùng hồi chỉ cần biết "Apps
   Script đã nhận" và "Firebase đã ghi", nhưng nay máy phải ĐỌC câu trả lời của website (lệnh
   đang chờ, bản OTA, ảnh khuôn mặt). `net4gPostOpen()` ở dưới làm việc đó và GIỮ phiên. */

// GET qua AT-HTTP: mở phiên + ACTION=0 + tự follow 302 (Apps Script). Trả status, gán *datalen (byte body 200).
// KHÔNG HTTPTERM khi thành công — để caller HTTPREAD đọc body ngay.
int net4gGetOpen(String url, int* datalen){
  if(datalen) *datalen=0;
  for (int hop=0; hop<3; hop++){
    Serial2.print("AT+HTTPTERM\r\n"); delay(120); while(Serial2.available()) Serial2.read();  // phiên SẠCH mỗi hop (host redirect khác)
    Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
    Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
    Serial2.print("AT+HTTPPARA=\"REDIR\",1\r\n"); atWait("OK",2000);   // module TỰ follow 302 (nếu hỗ trợ) -> hop0 ra 200 luôn
    if (!atDatUrl(url, "4G GET")) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); return 0; }
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
/* POST qua AT-HTTP và GIỮ PHIÊN để đọc thân trả về — anh em sinh đôi của `net4gGetOpen`.
   Vì sao phải có: `net4gHttpPost` cũ HTTPTERM ngay sau ACTION nên vứt luôn thân trả về. Hồi còn
   Apps Script thì không sao (chỉ cần biết 200), nhưng nay máy phải ĐỌC câu trả lời — lệnh đang
   chờ, bản OTA, ảnh khuôn mặt đều nằm trong thân đó.
   KHÔNG tự đi theo 302: cổng nhận chấm công không bao giờ được chuyển hướng, và đi theo bằng
   GET là mất trọn thân POST. Gặp 30x thì trả thẳng mã đó lên cho nơi gọi kêu. */
int net4gPostOpen(const String& url, const String& body, int* datalen){
  if(datalen) *datalen=0;
  if(!g_4gReady) return 0;
  while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPTERM\r\n"); delay(150); while(Serial2.available()) Serial2.read();
  Serial2.print("AT+HTTPINIT\r\n"); atWait("OK",6000);
  Serial2.print("AT+HTTPPARA=\"CID\",1\r\n"); atWait("OK",2000);
  if (!atDatUrl(url, "4G POST")) { Serial2.print("AT+HTTPTERM\r\n"); atWait("OK",1500); return 0; }
  Serial2.print("AT+HTTPPARA=\"CONTENT\",\"application/json\"\r\n"); atWait("OK",2000);
  long dwin = (body.length() > 6000) ? 120000L : 30000L;
  Serial2.print("AT+HTTPDATA="); Serial2.print(body.length()); Serial2.print(","); Serial2.print(dwin); Serial2.print("\r\n");
  String rd=atWait("DOWNLOAD",6000);
  if (rd.indexOf("DOWNLOAD")<0){ Serial2.print("AT+HTTPTERM\r\n"); Serial.println("   [4G POST] khong vao DOWNLOAD"); return 0; }
  Serial2.print(body); atWait("OK",dwin);
  Serial2.print("AT+HTTPACTION=1\r\n");                        // 1 = POST
  String r = atWait("+HTTPACTION:",40000);
  int status=0, dl=0, p=r.indexOf("+HTTPACTION:");
  if(p>=0){ int c1=r.indexOf(',',p), c2=(c1>=0)?r.indexOf(',',c1+1):-1;
            if(c1>=0&&c2>=0){ status=r.substring(c1+1,c2).toInt(); dl=r.substring(c2+1).toInt(); } }
  Serial.printf("   [4G POST] status=%d len=%d\n", status, dl);
  if (status >= 600) { inHttpAction(r, "4G POST"); Serial.println("   ⚠️ 7xx la loi CUA MODULE, khong phai server tra ve."); }
  if (status != 200) { return status; }                        // nơi gọi tự HTTPTERM
  if(datalen) *datalen=dl;
  return 200;                                                  // GIỮ phiên cho HTTPREAD
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
      /* 🔴 04/08/2026 — GÓI THỬ NÀY TỪNG GHI RÁC VÀO SHEET TIỀN LƯƠNG.
         Anh Thắng: *"khi rút điện ra gắn lại, nó tạo ra lệnh test"*. Vì gói thử đi vào ĐÚNG đường
         ghi chấm công với `time:"test"`, mà máy chủ `"test".split(" ")` ra dateStr="test" rồi
         `findOrCreateDateBlock` TẠO THẬT một khối tháng tên "test" trong sheet cơ sở.
         Nay gắn cờ `selftest:true` để máy chủ trả lời mà KHÔNG ghi gì. Máy chủ cũng đã chặn thêm
         theo khuôn ngày giờ (bảo vệ mọi máy còn chạy bản cũ) — hai lớp, vì một lớp là còn lọt. */
      String tb = String("{\"selftest\":true,\"macAddress\":\"") + macBo()
                + "\",\"hikSerial\":\"" + HIK_SERIAL
                + "\",\"stationName\":\"" + String(STATION_NAME)
                + "\",\"employeeNo\":\"TEST4G\",\"name\":\"AT-HTTP test\",\"time\":\"test\",\"image\":\"\"}";
      Serial.println("[4G] === TEST: đẩy thử 1 gói lên website ===");
      bool tok = (wpGoi(tb, false).length() > 0);
      Serial.println(tok ? "[4G] ✔️ TEST OK — đường 4G → website CHẠY!" : "[4G] ✖ TEST chưa được — xem 'status=' phía trên");
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
  /* 🔴 Dòng này là chỗ ĐẦU TIÊN người lắp máy nhìn. Phải trả lời được câu "máy có với tới đầu
     đọc không", vì đó là gốc của mọi triệu chứng "chấm công không lên". Bản cũ chỉ in IP nên
     nhìn vào vẫn không biết IP đó có ai trả lời hay không. */
  h += "<div class='muted'>Mạng: " + staTxt + " · AP: " + apName + " · Hik: <b>" + String(hik_ip) + "</b> — "
       + (HIK_SERIAL.length() ? String("<b style='color:#4ade80'>ĐỌC ĐƯỢC đầu đọc</b>")
                              : String("<b style='color:#f87171'>KHÔNG đọc được đầu đọc</b> "
                                       "(sai IP, sai mật khẩu ISAPI, hoặc đầu đọc không cùng mạng)"))
       + "</div>";
  h += "<div class='card'><h2>🏪 Cơ sở của máy này</h2>";
  h += "<div class='muted'>Cơ sở hiện tại: <b>" + String(STATION_NAME) + "</b> — "
       + String(STATION_TU_SERVER ? "do <b>server gán theo mã máy</b> (đúng cách dùng)."
                                  : "<b>bản nhớ trong máy</b>; server chưa gán hoặc chưa hỏi được.") + "</div>";
  h += "<div class='muted'>Mã máy để server nhận ra cơ sở — <b>serial đầu đọc:</b> "
       + String(HIK_SERIAL.length() ? HIK_SERIAL : String("(chưa đọc được)"))
       + (HIK_MODEL.length() ? (" · " + HIK_MODEL) : "") + " · <b>MAC bo:</b> " + macBo() + "</div>";
  h += "<div class='muted'>👉 <b>Không cần gõ tên ở đây nữa.</b> Vào web app &gt; tab <b>Máy chấm công</b>, "
       "tìm máy theo serial/MAC ở trên rồi chọn cơ sở. Máy tự nhận trong 30 phút (hoặc khởi động lại cho nhanh). "
       "Đổi bo ESP32 mà giữ đầu đọc thì <b>không phải khai lại gì</b>.</div>";
  // ---- Khai bí mật + 2 link (lưu vào NVS; OTA không ghi đè NVS) ----
  h += "<div class='card' " + String(g_chuaCauHinh ? "style='border:2px solid #ef4444'" : "") + "><h2>"
       + String(g_chuaCauHinh ? "⚠️ MÁY CHƯA CẤU HÌNH ĐỦ" : "🔐 Cấu hình máy") + "</h2>";
  h += "<div class='muted'>Bản firmware do CI build KHÔNG chứa bí mật (cố ý, để file .bin đặt chỗ tải "
       "công khai được mà không lộ khoá nào). Máy lấy bí mật từ bộ nhớ trong — <b>cập nhật firmware "
       "KHÔNG làm mất</b>. Bỏ trống ô nào là <b>giữ nguyên</b> giá trị đang có.</div>";
  /* ⚠️ LINK HIỆN NGUYÊN VĂN, cố ý. Trước đây che bằng cfgChe() nên chỉ thấy 4 ký tự đầu — mà 4
     ký tự đầu của mọi link đều là "http", tức là che xong thì KHÔNG CÒN CÁCH NÀO biết máy đang
     giữ link đúng hay link cũ. Đã trả giá: máy đẩy chấm công ra 404 mà soi mãi không ra.
     Link không phải bí mật (cổng còn đòi khoá riêng), còn portal thì nằm sau mật khẩu AP.
     KHOÁ MÁY vẫn che như cũ — ai đọc được nó là ghi được chấm công cho bất kỳ ai. */
  h += "<div class='muted'>Đang có:<br>link website <b style='word-break:break-all'>"
       + (_cfgWpUrl.length() ? _cfgWpUrl : String("(trống)"))
       + "</b><br>khoá máy <b>" + cfgChe(_cfgWpKey)
       + "</b> · mật khẩu Hikvision <b>" + cfgChe(_cfgHikPass) + "</b> · mật khẩu /update <b>" + cfgChe(_cfgOtaPass)
       + "</b> · mật khẩu AP <b>" + cfgChe(_cfgApPass)
       + "</b><br>IP đầu đọc <b>" + _cfgHikIp + "</b>"
       + ((_cfgHikIp == String(HIK_IP_MAC_DINH)) ? " (mặc định)" : " (đã khai)") + "</div>";
  h += "<input id='cWp'    placeholder='Link website (https://…/cham-cong-may, KHÔNG có / ở cuối)'>";
  h += "<input id='cWpKey' placeholder='Khoá máy (VHCC_KHOA_MAY trong wp-config.php)'>";
  h += "<input id='cHikIp' placeholder='IP đầu đọc Hikvision (mặc định " + String(HIK_IP_MAC_DINH) + ")'>";
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
  h += "function saveCfg(){let f=['cWp','cWpKey','cHikIp','cHikU','cHikP','cOtaU','cOtaP','cApP'];"
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
  String body = "{\"UserInfoSearchCond\":{\"searchID\":\"" + acsSearchId() + "\",\"searchResultPosition\":0,\"maxResults\":50}}";
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
  String body = "{\"UserInfoSearchCond\":{\"searchID\":\"" + acsSearchId() + "\",\"searchResultPosition\":0,\"maxResults\":1,\"EmployeeNoList\":[{\"employeeNo\":\"" + no + "\"}]}}";
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

/* IP dạng a.b.c.d, mỗi số 0..255. Không nhận tên miền: hikRequest ghép thẳng "http://"+ip nên
   tên miền cũng chạy, nhưng đầu đọc trong LAN không có tên miền, cho gõ chữ chỉ mở đường gõ sai. */
bool ipHopLe(const String& v){
  int so = 0, phan = 0, chuSo = 0;
  for (unsigned i = 0; i <= v.length(); i++) {
    char c = (i < v.length()) ? v[i] : '.';
    if (c >= '0' && c <= '9') { so = so * 10 + (c - '0'); chuSo++; if (chuSo > 3 || so > 255) return false; }
    else if (c == '.') { if (chuSo == 0) return false; phan++; so = 0; chuSo = 0; }
    else return false;
  }
  return phan == 4;
}

/* Khai bí mật + 2 link vào NVS. Ô nào KHÔNG gửi lên thì GIỮ NGUYÊN — cố ý, để sửa 1 mật
   khẩu không phải gõ lại cả 8 ô (gõ lại là dịp làm sai). NVS sống qua OTA. */
void handleSaveCfg(){
  struct { const char* arg; const char* khoa; } m[] = {
    {"cWp","wpUrl"}, {"cWpKey","wpKey"},
    {"cHikIp","hikIp"},
    {"cHikU","hikUser"}, {"cHikP","hikPass"}, {"cOtaU","otaUser"}, {"cOtaP","otaPass"}, {"cApP","apPass"}
  };
  int n = 0; String loi = "";
  for (unsigned i = 0; i < sizeof(m)/sizeof(m[0]); i++) {
    if (!server.hasArg(m[i].arg)) continue;
    String v = server.arg(m[i].arg); v.trim();
    if (!v.length()) continue;
    if (String(m[i].khoa) == "apPass" && v.length() < 8) { loi += "Mat khau AP phai >= 8 ky tu. "; continue; }
    /* IP sai dạng thì MỌI lệnh ISAPI im lặng thất bại — chặn ngay lúc lưu, đừng để máy chạy
       cả tuần rồi mới thấy "chấm công không lên". Chỉ nhận 4 số 0..255. */
    if (String(m[i].khoa) == "hikIp" && !ipHopLe(v)) { loi += "IP dau doc phai dang 192.168.4.50. "; continue; }
    if (String(m[i].khoa) == "wpUrl") {
      while (v.endsWith("/")) v.remove(v.length()-1);          // dan tu trinh duyet hay dinh "/"
      if (!wpUrlHopLe(v)) {
        loi += "Link website phai dang https://<ten mien>/cham-cong-may (khong co / o cuoi). ";
        continue;
      }
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
  // MÃ THIẾT BỊ -> hỏi server tên cơ sở. Làm TRƯỚC khi in "Cơ sở" và trước phần bù dữ liệu:
  // tên cơ sở đi kèm mọi lượt chấm công, và hiện trên màn hình máy.
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

  // ③ OTA từ xa: xét bản mà nhịp sống chở về, mỗi 5 phút (tải + flash khi có bản mới)
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
