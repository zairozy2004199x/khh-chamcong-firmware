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
#include "cong_tien.h"   // CỔNG TIỀN serial 4800 8E1 (thay đường XUNG cũ) — đã prove máy thật

#define FW_VERSION "ghe-massage 2026-08-23c (QR mua ma tren o goi)"

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
/* Khai báo trước: `drawIdle()` và `startSession()` gọi hai hàm này, mà chúng định nghĩa ở tận
   khối cấu hình phía dưới. Arduino tự sinh nguyên mẫu, nhưng chỉ tự sinh được khi nó phân tích
   trót lọt — viết thẳng ra đây thì không phụ thuộc vào chuyện đó nữa. */
bool duNhanTien();
void veManChuaCoTk();
/* Ô quảng cáo vẽ mã QR trước chỗ định nghĩa bộ vẽ. Khai ra đây cho khỏi phụ thuộc vào việc
   Arduino tự sinh nguyên mẫu — nó chỉ tự sinh được khi phân tích trót lọt. */
static void qrDatVung(int x, int y, int w, int h, int pxToiDa);

String CHAIR_ID = "";        // máy chủ gán; rỗng = chưa hỏi được
bool   CHUA_GAN = false;     // máy chủ báo ghế này chưa ai gán mã

// --- Thông tin nhận tiền: LẤY TỪ MÁY CHỦ, không nạp cứng ---
/* Đổi số tài khoản trên web là mọi ghế theo trong ~1 phút, KHÔNG phải nạp lại firmware. */
String BANK_BIN     = "";
String ACCOUNT_NO   = "";
String ACCOUNT_NAME = "";
/* 🔴 TIỀN TỐ BẮT BUỘC TRONG NỘI DUNG CHUYỂN KHOẢN — máy chủ gửi xuống trong lượt nhịp.
   SePay: "VietinBank cá nhân/hộ kinh doanh BẮT BUỘC nội dung CK phải chứa `sevqr` để định
   tuyến giao dịch qua SePay". Thiếu chuỗi này thì tiền vẫn vào tài khoản, ngân hàng vẫn báo
   thành công, mà SePay KHÔNG BAO GIỜ THẤY — không webhook, ghế không chạy, và không có gì
   trên đời báo cho ai biết. Để rỗng khi ngân hàng không đòi. */
String ND_TIEN_TO   = "";

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
String PKG_MOTA[PKG_MAX] = { "KHOI DONG & THU GIAN NHE", "SAU & PHUC HOI",
                             "TRI LIEU & GIAM DAU", "DANG CAP & QUA TANG" };
int    PKG_VIP[PKG_MAX]  = { 0, 0, 0, 1 };
/* ============================================================================================
 * Ô QUẢNG CÁO MÃ GIẢM GIÁ, LUÂN PHIÊN.
 *
 * Một ô gói luân phiên hai vế: mấy chục giây hiện gói như thường, mấy chục giây hiện lời mời
 * mua mã giảm giá. Tem QR dán cứng cạnh thùng tiền, nên ô này chỉ MỜI chứ không vẽ mã.
 *
 * ⚠️ VẾ QUẢNG CÁO VẪN PHẢI BẤM ĐƯỢC, và bấm vào là mở gói đó như thường. Khách nhìn thấy ô mình
 *    định bấm bỗng đổi thành chữ khác rồi bấm vào không ra gì là hỏng nặng hơn hẳn cái nó chữa.
 * ⚠️ KHÔNG luân phiên khi đang chờ trả tiền hay đang chạy: hai màn đó không có ô gói nào.
 * -1 = tắt. Ghế chưa nhận được cấu hình thì cứ hiện gói, không tự bịa ra khuyến mãi.
 * ============================================================================================ */
int QC_O    = -1;
int QC_GIAY = 30;
int QC_GIAM = 0;
bool g_qcMat = false;          // đang hiện vế quảng cáo hay vế gói
unsigned long g_qcLuc = 0;
/* Địa chỉ trang bán mã, để ghế TỰ VẼ mã QR vào ô quảng cáo. Rỗng = chỉ vẽ lời mời bằng chữ.
   ⚠️ RỖNG THÌ KHÔNG VẼ MÃ. Một mã QR dẫn đi đâu không rõ còn tệ hơn không có mã: khách quét,
      không ra gì, và lần sau họ không quét nữa — kể cả cái tem thật dán cạnh thùng tiền. */
String QC_URL = "";

/* Số tiền kiểu Việt: 200000 -> "200.000d". Tấm bảng giá treo tường ghi đủ số chứ không viết
   tắt "200k", và khách đối chiếu bảng với màn ghế bằng mắt — hai chỗ ghi khác kiểu là một
   khoảnh khắc ngờ vực ngay lúc sắp trả tiền. */
String tienVN(long v){
  String s = String(v), r = "";
  int n = s.length();
  for(int i=0;i<n;i++){
    r += s[i];
    int con = n - 1 - i;
    if(con > 0 && con % 3 == 0) r += '.';
  }
  return r + "d";
}

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
/* Chu kỳ hỏi máy chủ khi đang chờ trả. 800 chứ không 2000: kênh HTTPS nay GIỮ MỞ (xem wpGoi)
   nên một lượt hỏi chỉ còn ~150ms thay vì ~1,5s bắt tay TLS — hỏi dày hơn không còn tốn gì.
   Đừng hạ xuống dưới ~500: mỗi lượt vẫn là một request PHP, mà host này đã có tiền sử bị
   Imunify360 chặn vì gõ cửa quá dày. */
const unsigned long PAY_POLL_MS  = 800;
const unsigned long PAY_GRACE_MS = 20000;  // sau khi HỦY vẫn theo dõi ~20s: tiền tới trễ vẫn chạy
const unsigned long NHIP_MS      = 30000;  // nhịp sống + lấy cấu hình

// --- Relay điều khiển ghế ---
#define RELAY_PIN          17
#define RELAY_ACTIVE_HIGH  true

// --- Nhận TIỀN MẶT ---
/* 🔴 ĐỔI 25/08/2026 — BỎ ĐƯỜNG XUNG, DÙNG CỔNG TIỀN SERIAL (cong_tien.h).
   Đo trên máy thật: cục ICT L70 nói với ghế bằng SERIAL 4800 8E1 (khung 81 4X),
   KHÔNG phải xung, cũng KHÔNG phải MDB 9600/9-bit như khối cũ giả định. ESP32 xen
   giữa: relay ICT->ghế (tiền mặt xuyên qua) + bơm 81 4X khi quét QR. Xem cong_tien.h.

   Vì thế TẮT cả hai đường cũ:
     CASH_ENABLE=false        -> không gắn ngắt đếm xung (giải phóng GPIO27 cho cổng tiền phát)
     CASH_INHIBIT_ENABLE=false-> không dùng chân khoá 22 (ghế thật tự khoá ICT qua dây B: 5E)
   Mã đếm xung/chẩn đoán cũ để lại (không gọi tới) để phần báo trạng thái khỏi vỡ; dọn sau. */
#define USE_MDB            false   // khối MDB cũ (9600/9-bit) SAI giả định -> để tắt
#define CASH_ENABLE        false   // TẮT đường xung (đã thay bằng cong_tien.h)
#define CASH_PULSE_PIN     27
#define CASH_VND_PER_PULSE 5000
#define CASH_DEBOUNCE_MS   50
const unsigned long CASH_BATCH_GAP_MS = 400;
#define CASH_MAX_VND       200000  // trần một lượt (updateAcceptor vẫn tính, dù không còn khoá tay)
#define CASH_INHIBIT_ENABLE false  // TẮT chân khoá 22 (ghế tự khoá ICT)
#define INHIBIT_PIN        22
#define INHIBIT_ACTIVE_HIGH true

/* --- Tự phát hiện cục nhận tiền ICT L70 hỏng -----------------------------------------------
   Chỉ có ĐÚNG MỘT dây tín hiệu về ESP32 (đường xung) và một dây khoá đi ra. Nên đừng hứa hẹn
   "chẩn đoán cục tiền": bốn thứ dưới đây là tất cả những gì suy ra được từ hai sợi dây đó, và
   cả bốn đều là chuyện tiền đếm sai hoặc tiền vào mà ghế không chạy.

   ⚠️ KHÔNG suy ra "cục tiền hỏng" từ việc LÂU KHÔNG CÓ TỜ NÀO. Cả ngày không ai trả tiền mặt là
      chuyện bình thường ở nhiều cửa hàng; báo hỏng vì thế là dạy người ta bỏ qua cảnh báo. Ghế
      vẫn khai "lần cuối nhận tờ là bao giờ" để trên web tự nhìn, nhưng đó là SỐ LIỆU, không
      phải BÁO LỖI. */
#define CASH_KET_MS       2500   // đường xung nằm ở mức thấp liên tục quá lâu = treo/chập dây
#define CASH_BOI_SO       10000  // mọi mệnh giá VN từ 10.000 lên đều chia hết cho 10.000
#define CASH_NHIEU_NGUONG 6      // số cạnh bị chống nảy loại trong một đợt -> coi là nhiễu

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
CongTien congTien;      // CỔNG TIỀN serial (relay tiền mặt ICT->ghế + bơm 81 4X khi QR)

volatile bool g_4gReady = false;
int  g_simTx = SIM_TX_PIN, g_simRx = SIM_RX_PIN; long g_simBaud = 115200;

enum State { ST_IDLE, ST_WAIT_PAY, ST_RUNNING };
State state = ST_IDLE;
bool  screenDrawn = false;
/* Web đã bấm "khởi động lại", đang chờ ghế rảnh. Xem checkRemoteCmd(). */
volatile bool g_rebootCho = false;

char    payCode[8] = "";
long    payAmount = 0;
int     payMinutes = 0;
long    g_runTotalVnd = 0;
String  qrPayload = "";
/* Nội dung chuyển khoản THẬT của lượt này — dựng MỘT LẦN trong startSession().
   ⚠️ MÀN PHẢI IN ĐÚNG BIẾN NÀY, tuyệt đối không ráp lại chuỗi lần thứ hai để hiển thị.
      Ráp hai lần là hai chỗ lệch nhau lúc nào không hay: bản trước màn in
      "GHEAMTP01 FFPL45" trong khi mã QR mang "SEVQR GHEAMTP01 FFPL45" — khách nào gõ
      tay theo dòng chữ trên màn là chuyển đúng số tiền vào đúng tài khoản mà SePay
      không thấy, ghế không chạy, và không ai hiểu vì sao. */
String  payND = "";
/* Một lượt gọi máy chủ mất bao lâu (ms) — ĐO ĐỂ TRỪ, không phải để xem chơi.
   Ghế tính "còn bao nhiêu giây" TRƯỚC khi gọi, máy chủ đóng dấu giờ LÚC NHẬN. Cả quãng bắt tay
   TLS + đẩy gói nằm gọn giữa hai mốc đó, nên con số ghế gửi luôn LỚN HƠN sự thật đúng bằng
   quãng ấy — và phép trừ tuổi dữ liệu bên máy chủ không nhìn thấy nó. Đúng chỗ sinh ra khoảng
   lệch 4-5 giây giữa đồng hồ trên ghế và đồng hồ trên web. */
unsigned long g_rttMs = 0;
/* Đang cần hỏi dày (chờ khách trả) thì giữ kênh HTTPS mở giữa các lượt. */
volatile bool g_giuKenh = false;
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
/* Đếm riêng cho phần phát hiện hỏng. Tách khỏi `g_cashPulses` vì con số kia là TIỀN — trộn
   vào là có ngày một phép đếm để chẩn đoán lại cộng thành doanh thu. */
volatile uint32_t g_tmNhieu      = 0;   // cạnh bị chống nảy loại — nhiễu trên đường xung
volatile uint32_t g_tmXungKhiKhoa = 0;  // xung tới TRONG LÚC đã khoá máy nhận tiền
volatile bool     g_tmDangKhoa   = false;
char          g_tmLoi[10]     = "";     // lỗi ĐANG diễn ra ngay lúc này ("" = đang bình thường)
char          g_tmLoiCuoi[10] = "";     // lỗi gần nhất TỪNG thấy, kể cả đã hết
uint16_t      g_tmLan         = 0;      // đã thấy lỗi bao nhiêu lần kể từ lúc khởi động
unsigned long g_tmLucLoi      = 0;      // millis lúc thấy lỗi gần nhất (0 = chưa lần nào)
unsigned long g_tmLucTo       = 0;      // millis lúc nhận tờ tiền hợp lệ gần nhất

void IRAM_ATTR onCashPulse(){
  static unsigned long lastEdgeMs = 0;
  unsigned long now = millis();
  if(now - lastEdgeMs >= CASH_DEBOUNCE_MS){
    g_cashPulses = g_cashPulses + 1; lastEdgeMs = now;
    if(g_tmDangKhoa) g_tmXungKhiKhoa = g_tmXungKhiKhoa + 1;
  } else {
    /* Cạnh bị chống nảy loại. Đường xung sạch thì con số này đứng yên ở 0 suốt đời; nó nhích
       lên là dây đang bắt nhiễu, mà nhiễu trên đường xung nghĩa là TIỀN ĐẾM SAI. */
    g_tmNhieu = g_tmNhieu + 1;
  }
}

/**
 * Ghi một lỗi của cục nhận tiền.
 * @param dangDienRa  true = hỏng NGAY LÚC NÀY và còn nhìn thấy được (dây kẹt). false = một sự
 *                    việc vừa xảy ra xong (đếm lệch, nhận tiền khi đã khoá) — không có gì để
 *                    "còn đang" cả, nên chỉ vào sổ chứ không treo cờ.
 *
 * ⚠️ Hai loại này phải TÁCH. Treo cờ "đang hỏng" cho một sự việc đã qua thì cờ không bao giờ hạ,
 *    và nó che mất lỗi thật sự đang diễn ra ngay sau đó.
 */
void ghiLoiTien(const char* ma, bool dangDienRa){
  if(dangDienRa && strcmp(g_tmLoi, ma) == 0) return;   // vẫn đúng lỗi đang treo, đừng đếm lại
  strncpy(g_tmLoiCuoi, ma, sizeof(g_tmLoiCuoi)-1); g_tmLoiCuoi[sizeof(g_tmLoiCuoi)-1] = 0;
  if(g_tmLan < 65000) g_tmLan++;
  g_tmLucLoi = millis();
  if(dangDienRa){ strncpy(g_tmLoi, ma, sizeof(g_tmLoi)-1); g_tmLoi[sizeof(g_tmLoi)-1] = 0; }
  /* Đẩy nhịp NGAY. Chờ hết 30 giây mới báo là nửa phút cửa hàng không biết máy đang nuốt tiền. */
  g_statusDirty = true;
  Serial.printf("[TIEN] %s: %s\n", dangDienRa ? "DANG LOI" : "vua xay ra", ma);
}

/** Gọi mỗi vòng loop(): nhìn đường xung xem có đang kẹt không. */
void kiemCucTien(){
  if(!CASH_ENABLE || USE_MDB) return;
  static unsigned long thapTu = 0;
  /* Nghỉ thì đường xung ở mức CAO (INPUT_PULLUP). Một xung tiền dài chừng 50-100ms; nằm ở mức
     thấp mấy giây liền là cục tiền treo, transistor ra chập, hoặc dây đứt chạm mát. */
  if(digitalRead(CASH_PULSE_PIN) == LOW){
    if(thapTu == 0) thapTu = millis();
    else if(millis() - thapTu > CASH_KET_MS) ghiLoiTien("ket", true);
  } else {
    thapTu = 0;
    if(strcmp(g_tmLoi, "ket") == 0){ g_tmLoi[0] = 0; g_statusDirty = true;
      Serial.println("[TIEN] duong xung da nha, het ket"); }
  }
  uint32_t k;
  noInterrupts(); k = g_tmXungKhiKhoa; g_tmXungKhiKhoa = 0; interrupts();
  /* Đã bảo "đừng nhận nữa" mà vẫn có xung: dây INHIBIT tuột, sai cực, hoặc cục tiền lờ đi.
     Tiền vào két mà ghế không tính — đúng kiểu thất thoát không ai lần ra. */
  if(k > 0) ghiLoiTien("khoa", false);
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

  /* Bấm giờ TRƯỚC khi rẽ nhánh. Ghế chạy 4G thì cả khối keep-alive ở dưới không bao giờ được
     chạy tới — đo giờ chỉ ở nhánh WiFi là đúng con ghế cần đo nhất lại không đo được gì, và
     phép trừ lệch đồng hồ trên web im lặng trở thành vô tác dụng. */
  unsigned long t0 = millis();

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
    if(viec == "nhip") g_rttMs = millis() - t0;
    return ra;
  }
  /* ==========================================================================================
   * KÊNH HTTPS GIỮ MỞ GIỮA CÁC LƯỢT GỌI.
   *
   * 🔴 Bản trước dựng `WiFiClientSecure` NGAY TRONG HÀM. Biến cục bộ nên hết hàm là nó bị huỷ,
   *    kéo theo cả socket — nghĩa là MỖI lượt gọi phải bắt tay TLS lại từ đầu. Trên ESP32 một
   *    lượt bắt tay mất 1-2 giây. Hai chỗ trả giá:
   *      · Chờ khách trả: mỗi lượt hỏi "có tiền chưa" gánh thêm 1-2s -> quét xong 5-6s ghế mới chạy.
   *      · Nhịp sống: ghế tính `con_lai` rồi mới bắt tay, máy chủ đóng dấu giờ lúc nhận -> con số
   *        gửi lên đã già 1-2 giây ngay lúc sinh ra, và web hiển thị chậm hơn ghế đúng chừng đó.
   *
   * Để `static` + `setReuse(true)` là dùng lại đúng một socket: lượt sau chỉ còn ~150ms.
   *
   * ⚠️ KHÔNG gọi `begin()` lại mỗi lượt. `begin()` đặt lại tiêu đề VÀ ngắt kết nối đang có —
   *    tức là vẫn bắt tay lại, chỉ khác là mình tưởng đã sửa xong.
   * ⚠️ PHẢI ĐỌC HẾT THÂN TRẢ LỜI kể cả khi mã không phải 200. Bỏ dở là còn byte nằm lại trong
   *    socket, lượt sau đọc trúng phần thừa của lượt trước và JSON hỏng — kiểu lỗi chỉ hiện ra
   *    khi máy chủ trả lỗi, tức là đúng lúc đang rối nhất.
   * ⚠️ HỎNG THÌ QUAY VỀ CÁCH CŨ. Có host cắt keep-alive; ba lượt gãy liên tiếp là thôi giữ kênh
   *    hẳn, chậm còn hơn chết.
   * ========================================================================================== */
  static WiFiClientSecure c;
  static HTTPClient h;
  static bool daMo = false;
  static int  gayLienTiep = 0;
  static bool thoiGiuKenh = false;

  if(!daMo){
    c.setInsecure();
    h.begin(c, wp_url);
    h.setReuse(!thoiGiuKenh);
    h.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
    h.addHeader("Content-Type","application/json");
    h.addHeader("X-VHG-Key", wp_key);
    h.setTimeout(20000);
    daMo = true;
  }
  int code = h.POST(body);
  String than = h.getString();          // đọc hết, kể cả khi lỗi
  String ra   = (code==200) ? than : "";
  /* ⚠️ CHỈ ĐO LƯỢT `nhip`. `g_rttMs` dùng để trừ nửa quãng đi khỏi `con_lai`, mà `con_lai` được
     tính ngay trước lượt `nhip` — nên chỉ lượt ấy mới nói đúng quãng cần trừ.
     Lượt `luot` nay XIN MÁY CHỦ GIỮ LẠI tới 4 giây, nên nó mất 4 giây là chuyện bình thường và
     hoàn toàn không phải độ trễ đường truyền. Đo lẫn vào là đồng hồ trên web tự lùi 2 giây sau
     mỗi lượt khách trả tiền — một phép sửa lệch giờ tự tạo ra lệch giờ. */
  if(viec == "nhip") g_rttMs = millis() - t0;

  if(code <= 0){
    /* Kết nối gãy: dẹp hẳn để lượt sau bắt tay lại từ đầu. */
    h.end(); c.stop(); daMo = false;
    if(++gayLienTiep >= 3 && !thoiGiuKenh){
      thoiGiuKenh = true;
      Serial.println("[WP] keep-alive gay 3 lan -> thoi giu kenh, ve cach cu (cham hon).");
    }
  } else {
    gayLienTiep = 0;
    /* Hết đợt hỏi dày thì trả lại ~40KB bộ nhớ TLS. Nhịp 30 giây một lượt thì host đã đóng
       socket từ lâu, giữ cũng không nhanh hơn được. */
    if(!g_giuKenh || thoiGiuKenh){ h.end(); c.stop(); daMo = false; }
  }

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
/* ============================================================================================
 * BẢNG MÀU — dựng theo tấm bảng giá anh Thắng thiết kế (nâu rất tối + vàng đồng + thẻ kem).
 *
 * 🔴 BA GIỚI HẠN CỦA PHẦN CỨNG, ghi ở đây để lần sau khỏi hỏi lại:
 *    1. Màn 320×240, KHÔNG phải màn ngang 16:9 như tấm bảng treo tường. Bốn thẻ xếp một hàng
 *       ngang thì mỗi thẻ chỉ còn 76px — không đủ cho một con số tiền. Nên xếp 2×2.
 *    2. Font dựng sẵn của TFT_eSPI KHÔNG có dấu tiếng Việt. Chữ đã được máy chủ bỏ dấu trước
 *       khi gửi xuống (xem VHG_May::bo_dau_hoa) — ở đây chỉ vẽ, không xử lý chữ.
 *    3. Không có hình minh hoạ. Vẽ được bằng bitmap nhưng bốn tấm ảnh màu ăn hết vài trăm KB
 *       flash, mà chỗ đó đang để dành cho phần MDB và bộ mã QR. Thay bằng một dải màu ở đầu
 *       thẻ để mắt phân biệt được các gói.
 * ============================================================================================ */
#define COL_BG    0x1060   // nền nâu rất tối
#define COL_KHUNG 0x20A1   // khung/ô chìm
#define COL_VANG  0xDD28   // vàng đồng — chữ nhấn
#define COL_VANG2 0x8B24   // vàng tối — viền, chữ phụ
#define COL_KEM   0xF75B   // nền thẻ
#define COL_CHU   0x28E1   // chữ trên nền kem
#define COL_MO    0x9C4E   // chữ phụ, mờ — dùng trên nền TỐI (COL_BG, COL_KHUNG)
/* ============================================================================================
 * 🔴 THẺ VVIP TRƯỚC ĐÂY GẦN NHƯ KHÔNG ĐỌC ĐƯỢC.
 *
 * Anh Thắng 23/08/2026, nhìn ghế thật: *"chữ nhạt màu mà nền nhạt màu nên không thấy được"*.
 * Đo ra thì đúng, và còn ngược đời — trên thẻ VVIP chữ GIÁ mờ hơn cả chữ phụ:
 *
 *      giá trên ba thẻ thường  COL_CHU / COL_KEM   14,16:1   đọc thoải mái
 *      giá trên thẻ VVIP       COL_VANG / 0x7AA3    3,05:1   ← chưa bằng một phần tư
 *      chữ phụ trên thẻ VVIP   0xE71C  / 0x7AA3     5,23:1   ← lại rõ hơn chữ chính
 *
 * Gốc là nền VVIP cũ (0x7AA3) là màu ĐỒNG TỐI, mà chữ đặt lên lại là VÀNG — hai màu cùng độ
 * sáng thì mắt không tách ra được, dù trên bảng màu nhìn chúng rất khác nhau.
 *
 * Nay thẻ VVIP đọc y như ba thẻ kia: nền VÀNG SÁNG, chữ TỐI. Vàng đủ đậm để mắt phân biệt
 * ngay với nền kem của ba thẻ thường (lệch hẳn ở kênh lam: 140 so với 222).
 *
 * ⚠️ Màu chọn theo tương phản đo được, và đo ở CẢ HAI CHIỀU — vì ảnh chụp màn ghế cho thấy
 *    tấm nền đang render ĐẢO MÀU so với thiết kế (nền lẽ ra nâu rất tối thì hiện ra trắng),
 *    mà cấu hình đó nằm trong User_Setup của TFT_eSPI, không nằm trong tệp này. Nên mọi màu
 *    dưới đây phải đọc được dù tấm nền có đảo hay không:
 *
 *                              bình thường   nếu đảo màu
 *      COL_CHU trên COL_VIP      12,24:1       10,72:1
 *      COL_VIP_MO trên COL_VIP    5,49:1        6,26:1
 *      COL_VANG2 trên COL_VIP     3,87:1        4,90:1   (viền + dải màu)
 *
 *    Viền vẫn để COL_VANG như cũ thì chỉ còn 1,60:1 — tức là biến mất trên nền vàng.
 * ============================================================================================ */
#define COL_VIP    0xF6D1  // nền thẻ VVIP — vàng SÁNG (cũ 0x7AA3 đồng tối, không đọc nổi)
#define COL_VIP_MO 0x6A83  // chữ phụ trên thẻ VVIP
#define COL_MO_KEM 0x7B6B  // chữ phụ trên nền KEM sáng — COL_MO ở đây chỉ được 2,84:1
#define COL_ACC   COL_VANG // tên cũ, còn dùng ở màn "chưa gán mã"

/* Thẻ 2×2. Chiều cao chừa 30px đầu cho tiêu đề và 34px cuối cho dải "QUET MA QR". */
Btn PKG_BTN[PKG_MAX] = { {8,34,150,84}, {162,34,150,84}, {8,122,150,84}, {162,122,150,84} };

/* Một thẻ gói. Tách hẳn ra vì drawIdle() vốn đã dài, mà đây là phần duy nhất người ta sẽ còn
   sửa đi sửa lại — mỗi lần anh Thắng đổi bảng giá là đụng đúng hàm này. */
/* Vế QUẢNG CÁO của ô luân phiên. Cùng khung, cùng vị trí, cùng vùng bấm — chỉ đổi nội dung.
   Nền vàng để nó bật hẳn khỏi ba ô kia: khách phải nhận ra có gì đó khác ở đây. */
void veTheQuangCao(int i){
  Btn b  = PKG_BTN[i];
  int cx = b.x + b.w / 2;

  tft.fillRoundRect(b.x, b.y, b.w, b.h, 7, COL_VIP);
  /* Viền và dải màu phải là COL_VANG2 (vàng tối), không phải COL_VANG: trên nền vàng sáng thì
     COL_VANG chỉ còn 1,60:1 — nhìn như không có viền. */
  tft.drawRoundRect(b.x, b.y, b.w, b.h, 7, COL_VANG2);
  tft.fillRect(b.x + 6, b.y + 4, b.w - 12, 3, COL_VANG2);

  tft.setTextDatum(TC_DATUM);

  /* Đã vẽ được mã QR chưa. Chưa thì rơi xuống vế chữ ở dưới — xem chỗ gán. */
  bool veXong = false;

  if(QC_URL.length()){
    /* ============================================================================================
     * MÃ QR CHIẾM GẦN TRỌN Ô, KHÔNG CÒN CHỮ NÀO.
     *
     * Anh Thắng 23/08/2026, nhìn ô thật trên màn: *"2 hàng chữ nhỏ ẩn đi"* — hai hàng font 1
     * kẹp trên dưới mã ("MUA MA GIAM GIA -x%" và "QUET DE MUA - hoac tem canh thung tien").
     *
     * 🔴 VÀ ĐÓ LÀ ĐÚNG, không chỉ vì gọn mắt. Hai hàng đó ăn 26px chiều cao, mà chiều cao
     *    là thứ QUYẾT ĐỊNH mã có quét được hay không — ô rộng 150 nhưng chỉ cao 84, nên mã
     *    luôn bị chiều cao bó.
     *
     *      vùng 58px (cũ, còn chữ)   vùng 70px (nay)
     *        version 2 (25 module)   2 px/module        2 px/module
     *        version 3 (29 module)   1 px/module   →    2 px/module   ← chỗ ăn tiền
     *
     *    Địa chỉ nay là 31 ký tự (`khmatrix.com/mua-ma/?ghe=AMTP01`, chế độ byte) — version 2,
     *    sát mép 32. Thêm một ký tự vào mã ghế hay tên miền là rơi sang version 3, và ở vùng
     *    58px thì version 3 xuống 1 px/module = gần như không điện thoại nào quét nổi, trong khi
     *    trên màn NHÌN VẪN THẤY "có mã QR". Bỏ hai hàng chữ là mua được cả khoảng đệm đó.
     *
     * ⚠️ NỀN TRẮNG PHỦ CẢ VÙNG LẶNG. Vẽ mã đen lên nền thẻ là không máy nào quét được: bộ dò
     *    cần tương phản trắng-đen, và cần vùng lặng trắng quanh mã.
     * ============================================================================================ */
    int vungH = 70, vungY = b.y + 10;
    tft.fillRect(cx - vungH/2 - 2, vungY - 2, vungH + 4, vungH + 4, TFT_WHITE);
    qrDatVung(cx - vungH/2, vungY, vungH, vungH, 3);
    esp_qrcode_config_t qc = ESP_QRCODE_CONFIG_DEFAULT();
    qc.display_func       = qrDrawCb;
    /* Trần version 4: quá đó là module nhỏ hơn 2px, tức là một mã nhìn có mà quét không ra. */
    qc.max_qrcode_version = 4;
    qc.qrcode_ecc_level   = ESP_QRCODE_ECC_LOW;
    veXong = (esp_qrcode_generate(&qc, QC_URL.c_str()) == ESP_OK);

    /* 🔴 Chuỗi dài quá trần thì `esp_qrcode_generate` báo lỗi và callback KHÔNG chạy. Trước
       đây ô còn lại mảng trắng trơn nhưng dòng chữ dưới vẫn mời quét tem, nên vẫn còn lối đi.
       Nay không còn dòng chữ nào — mảng trắng trơn là một ô CÂM, khách nhìn không hiểu gì.
       Nên xoá mảng trắng đi và rơi xuống vế chữ như khi không có địa chỉ. */
    if(!veXong) tft.fillRect(cx - vungH/2 - 2, vungY - 2, vungH + 4, vungH + 4, COL_VIP);
  }

  if(!veXong){
    tft.setTextColor(COL_CHU, COL_VIP);
    tft.drawString("MUA MA GIAM GIA -" + String(QC_GIAM) + "%", cx, b.y + 10, 1);
    /* Chữ TỐI trên nền vàng sáng. Để TFT_WHITE như cũ là chữ tan hẳn vào nền. */
    tft.setTextSize(2);
    tft.drawString("-" + String(QC_GIAM) + "%", cx, b.y + 26, 4);
    tft.setTextSize(1);
    tft.setTextColor(COL_VIP_MO, COL_VIP);
    tft.drawString("QUET TEM CANH THUNG TIEN", cx, b.y + 60, 1);
    /* Nói rõ vẫn bấm được vào đây để mua gói như thường — nếu không, ô này trông như một tấm
       biển quảng cáo chết và khách sẽ đợi nó đổi lại mới dám bấm.
       🔴 Câu cũ "(cham de mua goi nhu thuong)" dài 168px trong ô rộng 150 — tràn 9px mỗi bên,
          mà phần tràn nằm NGOÀI ô nên lượt luân phiên sau vẽ lại ô KHÔNG xoá được nó. */
    tft.drawString("(cham vao de mua goi)", cx, b.y + 71, 1);
  }
}

void veTheGoi(int i){
  /* Tới lượt vế quảng cáo thì vẽ vế đó. Vùng bấm KHÔNG đổi, nên chạm vào vẫn mở đúng gói này. */
  if(i == QC_O && QC_O >= 0 && QC_GIAM > 0 && g_qcMat){ veTheQuangCao(i); return; }
  Btn  b    = PKG_BTN[i];
  bool vip  = (PKG_VIP[i] != 0);
  int  cx   = b.x + b.w / 2;
  uint16_t nen  = vip ? COL_VIP : COL_KEM;
  /* Chữ chính TỐI trên cả hai kiểu thẻ. Trước đây thẻ VVIP dùng COL_VANG trên nền đồng tối —
     đó đúng là chỗ anh Thắng bảo "không thấy được". */
  uint16_t chu  = COL_CHU;
  uint16_t phu  = vip ? COL_VIP_MO : COL_MO_KEM;

  tft.fillRoundRect(b.x, b.y, b.w, b.h, 7, nen);
  tft.drawRoundRect(b.x, b.y, b.w, b.h, 7, COL_VANG2);
  /* Dải màu ở đầu thẻ thay cho hình minh hoạ: đủ để mắt phân biệt bốn gói mà không tốn flash. */
  tft.fillRect(b.x + 6, b.y + 4, b.w - 12, 3, COL_VANG2);

  tft.setTextDatum(TC_DATUM);
  /* Tên gói. Máy chủ cắt còn VHG_May::CHU_VUA_O ký tự cho vừa 150px ở font 1 (6px/ký tự).
     🔴 Chú thích cũ ghi "16 ký tự" — con số đó không có thật ở đâu cả: máy chủ hồi đó cắt ở 30,
        tức là cho qua một chuỗi 180px trong ô 150px. Đừng chép số vào đây nữa. */
  tft.setTextColor(chu, nen);
  tft.drawString(PKG_TEN[i].length() ? PKG_TEN[i] : String("GOI ") + String(i + 1), cx, b.y + 12, 1);

  /* Số phút ngay dưới tên — đúng thứ tự trên tấm bảng giá. */
  tft.setTextColor(phu, nen);
  tft.drawString(String(phutGoi(i)) + " PHUT", cx, b.y + 24, 1);

  /* SỐ TIỀN to nhất: đó là thứ khách quyết định. Font 4 cao 26px, "200.000d" rộng ~112px nên
     vừa trong 150px. Dùng dấu chấm nghìn kiểu Việt, không viết tắt "200k" — tấm bảng giá ghi
     đủ số, và khách đối chiếu bảng treo tường với màn ghế bằng mắt. */
  tft.setTextColor(chu, nen);
  tft.drawString(tienVN(PKG_AMT[i]), cx, b.y + 38, 4);

  /* Mô tả một dòng, dưới cùng. Rỗng thì bỏ trống chứ đừng bịa chữ. */
  if(PKG_MOTA[i].length()){
    tft.setTextColor(phu, nen);
    tft.drawString(PKG_MOTA[i], cx, b.y + b.h - 14, 1);
  }
  if(vip){
    /* Nhãn VVIP ở góc phải trên, như tấm bảng giá. */
    /* Nhãn cũng đổi sang COL_VANG2: nhãn COL_VANG nằm trên thẻ vàng sáng thì chỉ 1,60:1,
       tức là cái nhãn biến mất đúng ở thẻ duy nhất cần nó. */
    tft.fillRoundRect(b.x + b.w - 42, b.y - 5, 38, 13, 5, COL_VANG2);
    tft.setTextDatum(MC_DATUM);
    tft.setTextColor(COL_KEM, COL_VANG2);
    tft.drawString("VVIP", b.x + b.w - 23, b.y + 1, 1);
    tft.setTextDatum(TC_DATUM);
  }
}

/**
 * Màn "chưa lấy được tài khoản nhận".
 *
 * Nói cho NHÂN VIÊN, không phải cho khách: khách không làm gì được với thông tin này. Nên câu
 * đầu là câu khách cần ("tạm thời trả tiền mặt"), phần dưới là thứ nhân viên cần để sửa.
 */
void veManChuaCoTk(){
  tft.fillScreen(COL_BG);
  tft.setTextDatum(TC_DATUM);
  tft.setTextColor(TFT_YELLOW, COL_BG);
  tft.drawString("TAM NGUNG NHAN QR", 160, 40, 4);
  tft.setTextColor(TFT_WHITE, COL_BG);
  tft.drawString("Quy khach vui long tra TIEN MAT", 160, 82, 2);
  tft.drawString("hoac bao nhan vien. Xin loi quy khach.", 160, 104, 2);
  tft.setTextColor(COL_MO, COL_BG);
  tft.drawString("Ghe chua lay duoc so tai khoan nhan tien", 160, 140, 1);
  tft.drawString("-> can noi mang de hoi may chu MOT lan", 160, 154, 1);
  tft.setTextColor(COL_ACC, COL_BG);
  tft.drawString(CHAIR_ID.length() ? CHAIR_ID : macBo(), 160, 176, 2);
  tft.setTextColor(netUp() ? 0x0660 : 0xF800, COL_BG);
  tft.drawString(netUp() ? "Dang co mang - cho lay cau hinh..." : "MAT MANG", 160, 202, 2);
}

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
  /* 🔴 CHƯA BIẾT TÀI KHOẢN NHẬN thì đừng mời khách chọn gói. Bốn cái thẻ gói nhìn y như bình
     thường mà bấm vào lại không ra mã được — thà nói thẳng ngay từ màn chờ, và nói cho NHÂN VIÊN
     chứ không phải cho khách, vì đây là việc của nhân viên.
     Đặt SAU khối "chưa gán mã" ở trên: ghế chưa gán mã thì lỗi đó là lỗi gốc, nói một chuyện
     một lúc thôi. */
  if(!duNhanTien()){
    veManChuaCoTk();
    return;
  }
  /* ============================================================================================
   * DẢI TIÊU ĐỀ. Mã ghế nằm ở góc phải, nhỏ: khách không cần nó, nhưng người đi sửa thì cần và
   * không phải mò vào web mới biết mình đang đứng trước con ghế nào.
   *
   * 🔴 TRƯỚC ĐÂY HAI CHỮ ĐÈ LÊN NHAU. Tiêu đề căn giữa x=160 dài 258px nên chạy tới x=289, còn
   *    mã ghế căn phải x=314 bắt đầu từ x=278 — chồng 11px. Anh Thắng chụp được: dải trên cùng
   *    hiện ra "…GHE CAO CHMTP01".
   *
   *    Và lúc MẤT MẠNG thì tệ hơn nhiều: chuỗi thành "AMTP01 - MAT MANG" (102px, bắt đầu từ
   *    x=212) nên chồng 77px — tức là đúng cái chữ "MAT MANG", thứ nhân viên cần đọc nhất, bị
   *    tiêu đề đè nát.
   *
   * ⚠️ Nên KHÔNG đoán chiều rộng nữa. Vẽ phần bên phải TRƯỚC, ĐO nó bằng `textWidth()`, rồi mới
   *    căn tiêu đề vào phần còn lại. Mã ghế dài bao nhiêu cũng không đè được nữa.
   * ============================================================================================ */
  tft.fillRect(0, 0, 320, 28, COL_KHUNG);
  tft.drawFastHLine(0, 28, 320, COL_VANG2);

  String chuPhai = netUp() ? CHAIR_ID : (CHAIR_ID + " - MAT MANG");
  tft.setTextDatum(TR_DATUM);
  tft.setTextColor(netUp() ? 0x0660 : 0xF800, COL_KHUNG);
  tft.drawString(chuPhai, 314, 9, 1);

  /* Chỗ còn lại cho tiêu đề, chừa 8px khe để hai bên không dính nhau. */
  int mepPhai = 314 - tft.textWidth(chuPhai, 1) - 8;
  /* Bốn mức, dài xuống ngắn: lấy mức ĐẦU TIÊN vừa chỗ. Cắt cụt giữa chừng chữ thì đọc còn khó
     hiểu hơn là mất hẳn vế đầu, nên thà rụng cả cụm. Mức cuối luôn vừa ở mọi mã ghế hợp lệ. */
  static const char* TIEU_DE[] = {
    "CHAO MUNG QUY KHACH  -  MASSAGE GHE CAO CAP",
    "CHAO MUNG  -  MASSAGE GHE CAO CAP",
    "MASSAGE GHE CAO CAP",
    "MASSAGE"
  };
  String tieu = "";
  for(unsigned k = 0; k < sizeof(TIEU_DE)/sizeof(TIEU_DE[0]); k++){
    if(tft.textWidth(TIEU_DE[k], 1) <= mepPhai - 6){ tieu = TIEU_DE[k]; break; }
  }
  if(tieu.length()){
    tft.setTextDatum(MC_DATUM);
    tft.setTextColor(COL_VANG, COL_KHUNG);
    tft.drawString(tieu, (6 + mepPhai) / 2, 13, 1);
  }

  for(int i=0;i<PKG_N;i++) veTheGoi(i);

  /* Dải chân: câu mời quét mã. Đây là câu duy nhất nói cho khách biết PHẢI LÀM GÌ, nên nó nằm
     trên dải riêng chứ không lẫn vào chữ nhỏ. */
  tft.fillRect(0, 210, 320, 30, COL_KHUNG);
  tft.drawFastHLine(0, 210, 320, COL_VANG2);
  tft.setTextColor(COL_VANG, COL_KHUNG);
  tft.drawString("CHON GOI  >  QUET MA QR DE THANH TOAN & BAT DAU", 160, 222, 1);

  /* Mã ghế + trạng thái mạng đã vẽ ở ĐẦU hàm, trước tiêu đề — vì tiêu đề phải đo nó mới biết
     căn vào đâu. Đừng chuyển xuống lại đây. */
}

/* Vùng vẽ mã QR — callback của esp_qrcode không nhận tham số riêng nên phải để ở đây.
   Tính theo KÍCH THƯỚC THẬT của mã: chuỗi VietQR dài ngắn khác nhau tuỳ tên tài khoản và mã
   lượt, nên số ô đổi theo. Cố định 3 pixel/ô là có ngày mã tràn khỏi màn và khách quét mãi
   không ra — mà nhìn thì vẫn thấy "có mã QR". */
static int g_qrX0 = 0, g_qrY0 = 0, g_qrO = 0, g_qrPx = 3;
/* Vùng đích của lượt vẽ mã QR sắp tới. Callback của esp_qrcode không nhận tham số riêng, nên
   đặt vùng vào đây TRƯỚC khi gọi `esp_qrcode_generate`.
   Mặc định là ô trắng giữa màn thanh toán; ô quảng cáo đặt lại trước khi gọi. */
static int g_qrVungX = 160 - 75, g_qrVungY = 40, g_qrVungW = 150, g_qrVungH = 150;
static int g_qrPxToiDa = 4;

static void qrDatVung(int x, int y, int w, int h, int pxToiDa){
  g_qrVungX = x; g_qrVungY = y; g_qrVungW = w; g_qrVungH = h; g_qrPxToiDa = pxToiDa;
}

static void qrDrawCb(esp_qrcode_handle_t qr){
  int size = esp_qrcode_get_size(qr);
  if(size <= 0) return;
  /* Cỡ ô tính theo CHIỀU NGẮN HƠN của vùng. Lấy chiều rộng thôi là mã tràn xuống dưới ở ô
     quảng cáo (150 rộng × 84 cao) — mà tràn thì phần bị cắt không quét được, và nhìn vẫn
     "có mã QR". */
  int canhVung = (g_qrVungW < g_qrVungH) ? g_qrVungW : g_qrVungH;
  int px = canhVung / (size + 4);       // +4 = vùng lặng 2 module mỗi bên, bắt buộc để quét được
  if(px < 1) px = 1;
  if(px > g_qrPxToiDa) px = g_qrPxToiDa;
  g_qrPx = px; g_qrO = size;
  int canh = size * px;
  g_qrX0 = g_qrVungX + (g_qrVungW - canh) / 2;
  g_qrY0 = g_qrVungY + (g_qrVungH - canh) / 2;
  Serial.printf("[QR] %d module, %d px/module trong vung %dx%d\n", size, px, g_qrVungW, g_qrVungH);
  /* Nền trắng phải phủ CẢ vùng lặng, không chỉ vùng có ô đen. Thiếu vùng lặng là nhiều điện
     thoại không nhận ra mã, và đó là kiểu hỏng chỉ lộ ra ở một số máy. */
  tft.fillRect(g_qrX0 - 2*px, g_qrY0 - 2*px, canh + 4*px, canh + 4*px, TFT_WHITE);
  for(int y=0;y<size;y++) for(int x=0;x<size;x++)
    if(esp_qrcode_get_module(qr, x, y))
      tft.fillRect(g_qrX0 + x*px, g_qrY0 + y*px, px, px, TFT_BLACK);
}

void drawQRScreen(){
  tft.fillScreen(COL_BG);

  /* Dải tiêu đề */
  tft.fillRect(0, 0, 320, 30, COL_KHUNG);
  tft.drawFastHLine(0, 30, 320, COL_VANG2);
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(COL_VANG, COL_KHUNG);
  tft.drawString("MA QR DE THANH TOAN & BAT DAU PHIEN MASSAGE", 160, 10, 1);
  tft.setTextColor(COL_KEM, COL_KHUNG);
  tft.drawString(tienVN(payAmount) + "   -   " + String(payMinutes) + " PHUT", 160, 22, 1);

  /* Ô trắng cho mã QR. Vẽ TRƯỚC khi sinh mã: callback vẽ đè lên đúng chỗ này. */
  tft.fillRoundRect(78, 36, 164, 158, 8, TFT_WHITE);
  tft.drawRoundRect(78, 36, 164, 158, 8, COL_VANG);

  /* Vùng của màn thanh toán: ô trắng 164x158 ở giữa, trừ viền còn 150x150. */
  qrDatVung(160 - 75, 40, 150, 150, 4);
  esp_qrcode_config_t qcfg = ESP_QRCODE_CONFIG_DEFAULT();
  qcfg.display_func       = qrDrawCb;
  qcfg.max_qrcode_version = 11;
  qcfg.qrcode_ecc_level   = ESP_QRCODE_ECC_LOW;
  esp_qrcode_generate(&qcfg, qrPayload.c_str());

  /* Câu hướng dẫn + mã lượt. Mã lượt phải HIỆN RA: app ngân hàng nào không tự điền nội dung
     thì khách gõ tay đúng chuỗi này, và không có nó thì tiền vào mà ghế không chạy. */
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(COL_MO, COL_BG);
  tft.drawString("Quet bang ung dung Ngan hang hoac Vi dien tu", 160, 199, 1);
  tft.setTextColor(COL_VANG, COL_BG);
  tft.drawString("Noi dung: " + payND, 160, 210, 1);

  /* Nút huỷ nhỏ, góc trái dưới: nó KHÔNG phải việc chính của màn này. Để to ở giữa như bản cũ
     là mời khách bấm nhầm ngay lúc vừa quét xong. */
  tft.fillRoundRect(6, 222, 84, 16, 5, COL_KHUNG);
  tft.drawRoundRect(6, 222, 84, 16, 5, COL_VANG2);
  tft.setTextColor(COL_MO, COL_KHUNG);
  tft.drawString("CHAM DE HUY", 48, 230, 1);
}
/* Đồng hồ chờ trả — HÀNG DƯỚI CÙNG, bên phải nút huỷ, nền tối như nền màn.
   ⚠️ HAI CHỖ DỄ HỎNG, đã hỏng cả hai cùng lúc ở bản trước:
      1. Đặt trong khoảng y 170–195 là chồng lên dòng "Quet bang ung dung…" và dòng nội dung —
         chữ đè chữ, đọc thành một đám mực. Vùng này CHỈ có mã QR và hai dòng chữ đó.
      2. Lấy nền TFT_WHITE cho vừa ô mã QR: ô trắng chỉ rộng tới x=242, mà setTextPadding kéo
         vệt nền tới tận mép phải — thành một mảng trắng lòi ra giữa nền tối.
   Nút huỷ chiếm x 6–90, nên hàng này bắt đầu từ x=100. */
void drawWaitCountdown(int secLeft){
  tft.setTextDatum(TL_DATUM);
  tft.setTextColor(TFT_RED, COL_BG);
  tft.setTextPadding(96);
  tft.drawString("Cho tra: " + String(secLeft) + "s", 100, 222, 2);
  tft.setTextColor(netUp() ? 0x0660 : TFT_RED, COL_BG);
  tft.setTextPadding(116);
  tft.drawString(netUp() ? "Dang kiem tra..." : "MAT MANG - cho lai", 198, 226, 1);
  tft.setTextPadding(0);
}
/* Gói đang chạy — để in tên lên màn đếm ngược. `-1` = không rõ (tiền mặt, hoặc lệnh từ web). */
int g_goiDangChay = -1;

void drawRunning(int secLeft){
  /* CHỈ VẼ NỀN MỘT LẦN. Vẽ lại cả màn mỗi giây thì màn nháy, và trên CYD một lượt fillScreen
     mất ~90ms — mỗi giây mất chừng đó là chạm màn hình trễ thấy rõ. */
  if(!screenDrawn){
    screenDrawn = true;
    tft.fillScreen(COL_BG);

    tft.fillRect(0, 0, 320, 30, COL_KHUNG);
    tft.drawFastHLine(0, 30, 320, COL_VANG2);
    tft.setTextDatum(MC_DATUM);
    tft.setTextColor(COL_MO, COL_KHUNG);
    tft.drawString("HE THONG GHE MASSAGE CAO CAP", 160, 9, 1);
    tft.setTextColor(COL_VANG, COL_KHUNG);
    tft.drawString("PHIEN TRI LIEU DANG DIEN RA", 160, 21, 1);

    /* Ô đếm ngược */
    tft.fillRoundRect(20, 40, 280, 108, 8, COL_KHUNG);
    tft.drawRoundRect(20, 40, 280, 108, 8, COL_VANG2);
    tft.setTextColor(COL_MO, COL_KHUNG);
    tft.drawString("SO PHUT CON LAI", 160, 52, 1);

    /* Tổng thời gian + tên gói: khách nhìn 15:30 mà không biết mình mua gói mấy phút thì con
       số đó không nói lên điều gì. */
    String duoi = "TONG: " + String(payMinutes) + " PHUT";
    if(g_goiDangChay >= 0 && g_goiDangChay < PKG_N && PKG_TEN[g_goiDangChay].length()){
      duoi += "  -  " + PKG_TEN[g_goiDangChay];
    }
    tft.setTextColor(COL_KEM, COL_KHUNG);
    tft.drawString(duoi, 160, 136, 1);

    /* Dải trạng thái dưới */
    tft.fillRoundRect(20, 156, 280, 44, 8, COL_KHUNG);
    tft.drawRoundRect(20, 156, 280, 44, 8, COL_VANG2);
    tft.fillCircle(42, 178, 8, 0x0660);
    tft.drawCircle(42, 178, 11, COL_VANG2);
    tft.setTextDatum(ML_DATUM);
    tft.setTextColor(COL_VANG, COL_KHUNG);
    tft.drawString("PHIEN MASSAGE DANG CHAY", 60, 170, 1);
    tft.setTextColor(COL_MO, COL_KHUNG);
    tft.drawString("Xin hay thu gian va tan huong dich vu.", 60, 186, 1);

    tft.setTextDatum(MC_DATUM);
    tft.setTextColor(COL_MO, COL_BG);
    tft.drawString(String("Ghe ") + CHAIR_ID + "   -   K&H", 160, 220, 1);
  }

  int mm = secLeft/60, ss = secLeft%60;
  char b[8]; snprintf(b, sizeof(b), "%02d:%02d", mm, ss);
  tft.setTextDatum(MC_DATUM);
  /* setTextPadding: xoá đúng vệt chữ cũ. Không có nó thì "10:00" -> "9:59" để lại một chữ số
     mồ côi trên màn, và người ta đọc ra một con số không tồn tại. */
  tft.setTextColor(COL_VANG, COL_KHUNG);
  /* Font 6 (48px) chứ không font 7: font 6 là font bản gốc đã dùng và chắc chắn được nạp
     trong User_Setup của bo CYD này. Đổi sang một font chưa bật là màn trống trơn — mà lúc đó
     ghế đang chạy và khách đang nhìn. */
  tft.setTextPadding(tft.textWidth("88:88", 6));
  tft.drawString(b, 160, 96, 6);
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
  /* CASH_INHIBIT_ENABLE tắt = KHÔNG có dây khoá nào cả, nên không bao giờ được coi là "đang
     khoá" — nếu không, mọi tờ tiền hợp lệ đều bị báo thành "nhận tiền khi đã khoá". */
  g_tmDangKhoa = CASH_INHIBIT_ENABLE && !en;
  if(!CASH_INHIBIT_ENABLE) return;
  digitalWrite(INHIBIT_PIN, ((en ? 0 : 1) == (INHIBIT_ACTIVE_HIGH ? 1 : 0)) ? HIGH : LOW);
}
void updateAcceptor(){
  bool allow = (g_runTotalVnd < CASH_MAX_VND);
  setAcceptorEnabled(allow);
  if(!allow) Serial.printf("[LIMIT] da nhan %ld d >= tran %d -> KHOA may nhan tien\n", g_runTotalVnd, CASH_MAX_VND);
}

// ======================= Nhịp sống: gộp bốn câu hỏi vào MỘT lượt =======================
/* ============================================================================================
 * NHỚ CẤU HÌNH VÀO FLASH.
 *
 * 🔴 LỖI 22/08/2026, 23:31 — anh Thắng quét mã trên ghế, tiền KHÔNG tới SePay, không tới đâu cả.
 *
 *    Số tài khoản nhận, mã ngân hàng, tiền tố nội dung, giá và các gói CHỈ được nạp từ lượt nhịp.
 *    Không ghi vào flash. Nên ghế khởi động lại (nạp USB xong, mất điện, hay chỉ là reset) mà
 *    lúc đó chưa hỏi được máy chủ thì `ACCOUNT_NO` và `BANK_BIN` là CHUỖI RỖNG — và `buildVietQR`
 *    vẫn dựng ra một mã VietQR đúng chuẩn với ngân hàng rỗng, tài khoản rỗng.
 *
 *    Nhìn màn ghế thì KHÔNG THẤY GÌ KHÁC THƯỜNG: vẫn bốn gói, vẫn mã QR đen trắng, vẫn dòng nội
 *    dung. Khách quét, chuyển tiền, và tiền không tới tài khoản nào. Đây là kiểu hỏng đắt nhất
 *    trong cả hệ thống — nó không kêu, và nó nhằm đúng vào tiền của khách.
 *
 *    `CHAIR_ID` thì đã nhớ vào flash từ lâu, ngay dòng comment cạnh nó còn ghi "để mất mạng vẫn
 *    hiện đúng". Thiếu đúng phần còn lại, mà phần còn lại mới là phần nhận tiền.
 *
 * Nên: nhớ TẤT CẢ những gì cần để đứng một mình mà thu tiền cho đúng. Ghế mất mạng ba ngày vẫn
 * phải thu đúng vào đúng tài khoản, đúng giá, đúng tiền tố.
 * ============================================================================================ */
/** Chữ ký gọn của cấu hình — chỉ để so "có đổi không", không để đọc ngược ra. */
String kyCauHinh(){
  String k = ACCOUNT_NO + "|" + BANK_BIN + "|" + ACCOUNT_NAME + "|" + ND_TIEN_TO + "|"
           + String(PRICE_VND) + "|" + String(MINUTES) + "|" + String(PKG_N);
  for(int i=0;i<PKG_N && i<PKG_MAX;i++){
    k += "|" + String(PKG_AMT[i]) + "," + PKG_TEN[i] + "," + String(PKG_PHUT[i]) + ","
       + PKG_MOTA[i] + "," + String(PKG_VIP[i]);
  }
  return k;
}

void luuCauHinh(){
  prefs.putString("tk",    ACCOUNT_NO);
  prefs.putString("bin",   BANK_BIN);
  prefs.putString("tenTk", ACCOUNT_NAME);
  prefs.putString("tienTo", ND_TIEN_TO);
  prefs.putLong  ("gia",   PRICE_VND);
  prefs.putInt   ("phut",  MINUTES);
  prefs.putInt   ("pkgN",  PKG_N);
  for(int i=0;i<PKG_N && i<PKG_MAX;i++){
    char k[12];
    snprintf(k,sizeof(k),"p%dt",i); prefs.putLong  (k, PKG_AMT[i]);
    snprintf(k,sizeof(k),"p%dn",i); prefs.putString(k, PKG_TEN[i]);
    snprintf(k,sizeof(k),"p%dp",i); prefs.putInt   (k, PKG_PHUT[i]);
    snprintf(k,sizeof(k),"p%dm",i); prefs.putString(k, PKG_MOTA[i]);
    snprintf(k,sizeof(k),"p%dv",i); prefs.putInt   (k, PKG_VIP[i]);
  }
}

void docCauHinh(){
  /* ⚠️ Chỉ nhận giá trị KHÁC RỖNG. Ghế chưa từng nói chuyện với máy chủ thì flash rỗng, và lúc
     đó phải để `ACCOUNT_NO` rỗng THẬT — chính chuỗi rỗng ấy là thứ chặn không cho hiện mã QR
     (xem `duNhanTien`). Nhận bừa một giá trị mặc định vào đây là gỡ mất cái chốt đó. */
  String v;
  v = prefs.getString("tk",     ""); if(v.length()) ACCOUNT_NO   = v;
  v = prefs.getString("bin",    ""); if(v.length()) BANK_BIN     = v;
  v = prefs.getString("tenTk",  ""); if(v.length()) ACCOUNT_NAME = v;
  /* Tiền tố thì nhận cả chuỗi rỗng — rỗng là một lựa chọn hợp lệ trên web, y như lúc đọc nhịp. */
  if(prefs.isKey("tienTo")) ND_TIEN_TO = prefs.getString("tienTo", "");
  long g = prefs.getLong("gia", 0);  if(g > 0) PRICE_VND = g;
  int  p = prefs.getInt ("phut", 0); if(p > 0) MINUTES   = p;
  int  n = prefs.getInt ("pkgN", 0);
  if(n > 0 && n <= PKG_MAX){
    PKG_N = n;
    for(int i=0;i<n;i++){
      char k[12];
      snprintf(k,sizeof(k),"p%dt",i); PKG_AMT[i]  = prefs.getLong  (k, PKG_AMT[i]);
      snprintf(k,sizeof(k),"p%dn",i); PKG_TEN[i]  = prefs.getString(k, PKG_TEN[i]);
      snprintf(k,sizeof(k),"p%dp",i); PKG_PHUT[i] = prefs.getInt   (k, PKG_PHUT[i]);
      snprintf(k,sizeof(k),"p%dm",i); PKG_MOTA[i] = prefs.getString(k, PKG_MOTA[i]);
      snprintf(k,sizeof(k),"p%dv",i); PKG_VIP[i]  = prefs.getInt   (k, PKG_VIP[i]);
    }
  }
  Serial.printf("[CFG] tu flash: tk=%s bin=%s tienTo=%s gia=%ld/%dp goi=%d\n",
    ACCOUNT_NO.c_str(), BANK_BIN.c_str(), ND_TIEN_TO.c_str(), PRICE_VND, MINUTES, PKG_N);
}

/**
 * ĐỦ ĐIỀU KIỆN NHẬN TIỀN CHƯA — chốt chặn cuối trước khi vẽ bất kỳ mã QR nào.
 *
 * 🔴 KHÔNG CÓ TÀI KHOẢN THÌ KHÔNG CÓ MÃ QR. Không có ngoại lệ, không có "cứ hiện đi rồi tính".
 *    Một mã QR không nhận được tiền mà vẫn hiện lên màn là đang mời khách trả tiền vào hư không,
 *    và cả hai bên đều không biết cho tới khi quá muộn.
 */
bool duNhanTien(){ return ACCOUNT_NO.length() > 0 && BANK_BIN.length() > 0; }

/**
 * Một lượt nhịp trả lời luôn: mã ghế của mình, giá/phút/số tài khoản, có tiền chờ không, có lệnh
 * bật/tắt không. Trước đây là bốn lượt gọi Firebase riêng (config, status, pay, cmd). Trên 4G mỗi
 * lượt AT-HTTP mất 3-6 giây — gộp lại là khác biệt giữa "ghế phản ứng trong 2 giây" và "10 giây".
 */
void guiNhip(){
  const char* st = (state==ST_RUNNING) ? "running" : (state==ST_WAIT_PAY ? "wait_pay" : "idle");
  const char* src = (g_srcCode=='q') ? "qr" : (g_srcCode=='c' ? "cash" : (g_srcCode=='r' ? "remote" : ""));
  long conLai = (state==ST_RUNNING) ? ((long)(runUntil - millis())/1000) : 0; if(conLai<0) conLai=0;
  /* `tre` = lượt gọi trước mất bao nhiêu ms. Máy chủ trừ nửa quãng đó khỏi `con_lai`: con số
     trên được tính Ở ĐÂY, còn dấu giờ thì đóng lúc gói tin TỚI NƠI — nửa quãng đi là phần
     máy chủ không tự thấy được. Giữ kênh HTTPS mở làm quãng này còn ~150ms, nên phần trừ chỉ
     là cái lưới đỡ cho đường 4G (mỗi lượt AT-HTTP 3-6 giây, không keep-alive được). */
  /* Báo ngược TIỀN TỐ ghế đang thật sự dùng. Không có nó thì từ web không cách nào biết ghế đã
     nạp firmware mới chưa — người ta sửa ô trên web rồi tưởng xong, mà ghế vẫn dựng nội dung
     thiếu tiền tố, và tiền vẫn biến mất y như cũ. */
  String r = wpGoi("nhip", String("\"trang_thai\":\"") + st + "\",\"nguon\":\"" + src
    + "\",\"con_lai\":" + String(conLai) + ",\"tre\":" + String(g_rttMs)
    + ",\"tm_loi\":\"" + String(g_tmLoi) + "\",\"tm_cuoi\":\"" + String(g_tmLoiCuoi)
    + "\",\"tm_lan\":" + String(g_tmLan)
    + ",\"tm_giay\":" + String(g_tmLucLoi ? (long)((millis()-g_tmLucLoi)/1000) : -1L)
    + ",\"tm_to\":"   + String(g_tmLucTo  ? (long)((millis()-g_tmLucTo )/1000) : -1L)
    + ",\"nd\":\"" + jsonEsc(ND_TIEN_TO)
    + "\",\"fw\":\"" FW_VERSION "\"");
  lastNhipMs = millis(); g_statusDirty = false;
  if(r.length()==0) return;
  /* 1536 chứ không 512: gói nhịp mang thêm bốn gói {t,n,p} — mỗi gói một cái tên tới 18 ký tự.
     Tràn bộ đệm thì `deserializeJson` trả lỗi và HÀM THOÁT NGAY: ghế mất luôn cả giá, tài khoản
     lẫn lệnh, mà màn hình không có gì báo. Một con số chật ở đây làm chết cả lượt nhịp.
     netTask có 10240 byte ngăn xếp nên 1536 nằm gọn. */
  StaticJsonDocument<1536> d;
  if(deserializeJson(d, r)) return;
  /* ==========================================================================================
   * NHỚ LẠI NHỮNG GÌ ĐANG HIỆN TRÊN MÀN, ĐỂ BIẾT CÓ PHẢI VẼ LẠI KHÔNG.
   *
   * 🔴 LỖI THẬT, anh Thắng 22/08/2026: *"đã gán, nhưng trên máy chưa hiện hệ thống bấm chọn"*.
   *    Ghế chưa gán mã thì vòng lặp vẽ lại màn mỗi 5 giây (để dòng MAC và trạng thái 4G cập
   *    nhật cho người đang đứng lắp). Nhưng điều kiện của vòng đó LÀ `CHUA_GAN` — nên đúng
   *    khoảnh khắc máy chủ báo "đã gán rồi", vòng vẽ lại tắt, mà lần vẽ cuối cùng vẫn là trang
   *    "GHE CHUA DUOC GAN MA". Màn đứng nguyên ở đó CHO TỚI KHI TẮT NGUỒN.
   *
   *    Người đi lắp thì thấy web báo gán xong, ghế thì không nhúc nhích — và không có gì trên
   *    màn nói vì sao. Đây đúng kiểu lỗi làm người ta tháo ghế ra kiểm tra dây.
   *
   * Nên: so cái vừa nhận với cái đang hiện, khác thì bắt vẽ lại. Không chỉ mã ghế — giá, số
   * phút và cả bốn gói đều nằm trên màn, đổi ở web mà màn không đổi là hai nơi nói hai giá
   * khác nhau, và khách đọc màn ghế chứ không đọc web.
   * ========================================================================================== */
  String   cu_id   = CHAIR_ID;
  bool     cu_gan  = CHUA_GAN;
  long     cu_gia  = PRICE_VND;
  int      cu_phut = MINUTES;
  int      cu_n    = PKG_N;
  long     cu_amt0 = PKG_AMT[0];
  String   cu_ten0 = PKG_TEN[0];
  /* Chữ ký của TOÀN BỘ phần phải nhớ vào flash. Rộng hơn hẳn mấy biến `cu_*` ở trên: chúng để
     biết có cần VẼ LẠI MÀN không, còn cái này để biết có cần GHI FLASH không. Ghi mỗi lượt nhịp
     là 30 giây một lần ghi NVS, ngày hai nghìn tám trăm lượt — hao chip mà chẳng để làm gì. */
  String   cu_ky   = kyCauHinh();

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
  /* Nhận CẢ CHUỖI RỖNG: bỏ tiền tố trên web thì ghế phải bỏ theo. Dùng `containsKey` chứ đừng
     xét độ dài như mấy ô trên — ô kia rỗng nghĩa là "máy chủ chưa khai, giữ cái đang có", còn
     ô này rỗng là một lựa chọn hợp lệ. */
  if(d.containsKey("tienTo")) ND_TIEN_TO = String((const char*)(d["tienTo"] | ""));
  if(d.containsKey("qcO")){
    QC_O    = (int)(d["qcO"]    | -1);
    QC_GIAY = (int)(d["qcGiay"] | 30);
    QC_GIAM = (int)(d["qcGiam"] | 0);
    QC_URL  = String((const char*)(d["qcUrl"] | ""));
    if(QC_GIAY < 5) QC_GIAY = 5;
    /* Tắt quảng cáo thì đưa ô về vế gói NGAY, đừng để nó kẹt ở vế quảng cáo tới lượt luân phiên
       sau — người vừa tắt trên web sẽ tưởng lệnh không ăn. */
    if(QC_O < 0){ g_qcMat = false; }
  }
  /* Mệnh giá do web khai. Nhận vào CHỈ KHI đọc được ít nhất một giá trị hợp lệ — mảng rỗng hay
     gói lỗi mà nhận là màn ghế không còn nút nào bấm được, tức đường QR chết hẳn ở cửa hàng đó
     mà máy chủ vẫn thấy ghế gửi nhịp bình thường. Giữ bộ đang dùng còn hơn. */
  JsonArrayConst goi = d["goi"];
  if(!goi.isNull()){
    long   tt[PKG_MAX]; String tn[PKG_MAX]; int tp[PKG_MAX];
    String tm[PKG_MAX]; int tv[PKG_MAX]; int n = 0;
    for(JsonVariantConst v : goi){
      if(n >= PKG_MAX) break;
      /* Nhận CẢ HAI dạng: số trơn (bản máy chủ 1.3.0) và {t,n,p} (từ 1.4.0). Ghế nạp bằng USB
         nên trong nhà sẽ có lẫn hai đời firmware và hai đời plugin trong nhiều tuần — bên nào
         cũng phải chịu được bên kia, không thì một cửa hàng nào đó im lặng mất hết nút bấm. */
      long a; String nm = "", mt = ""; int ph = 0, vp = 0;
      if(v.is<JsonObjectConst>()){
        a  = (long)(v["t"] | 0);
        nm = String((const char*)(v["n"] | ""));
        ph = (int)(v["p"] | 0);
        mt = String((const char*)(v["m"] | ""));
        vp = (int)(v["v"] | 0);
      } else {
        a = v.as<long>();
      }
      if(a >= 1000){ tt[n] = a; tn[n] = nm; tp[n] = ph; tm[n] = mt; tv[n] = vp; n++; }
    }
    if(n > 0){
      for(int i=0;i<n;i++){
        PKG_AMT[i] = tt[i]; PKG_TEN[i] = tn[i]; PKG_PHUT[i] = tp[i];
        PKG_MOTA[i] = tm[i]; PKG_VIP[i] = tv[i];
      }
      PKG_N = n; g_statusDirty = true;
    }
  }
  /* Có gì trên màn đổi không? So SAU khi đã nhận hết, trước khi xử lệnh. */
  if(cu_id != CHAIR_ID || cu_gan != CHUA_GAN || cu_gia != PRICE_VND || cu_phut != MINUTES
     || cu_n != PKG_N || cu_amt0 != PKG_AMT[0] || cu_ten0 != PKG_TEN[0]){
    /* CHỈ khi đang rảnh. Đang chờ khách trả tiền mà xoá màn là mã QR biến mất ngay dưới tay
       người đang quét; đang chạy mà xoá là mất luôn số đếm ngược. Hai màn đó tự vẽ lại khi
       quay về rảnh. */
    if(state == ST_IDLE) screenDrawn = false;
    g_statusDirty = true;
    Serial.println("[UI] cau hinh doi -> ve lai man");
  }

  /* Cấu hình đổi -> ghi flash NGAY, đừng đợi lượt sau. Giữa hai lượt nhịp là 30 giây, và ghế
     có thể mất điện đúng trong 30 giây đó — mất điện rồi lên lại mà chưa kịp ghi là quay về
     đúng cảnh tài khoản rỗng. */
  if(cu_ky != kyCauHinh()){ luuCauHinh(); Serial.println("[CFG] cau hinh doi -> da ghi vao flash"); }

  g_coLenh = ((int)(d["coLenh"] | 0) == 1);
  if(((int)(d["coTien"] | 0) == 1) && g_paidAmount == 0){
    /* Máy chủ báo có tiền chờ mà ghế đang rảnh (khách trả sau khi màn đã tắt QR) — vẫn lấy về
       và chạy. Khách đã trả tiền thì phải được massage, đừng bắt họ trả lần nữa. */
    g_watchPayUntil = millis() + PAY_GRACE_MS;
  }
}

/** Hỏi máy chủ có lượt nào đã trả tiền chưa. Trả số tiền (0 = chưa). */
long checkPaid(){
  /* `cho` = xin máy chủ GIỮ câu hỏi này lại tối đa mấy giây thay vì trả "chưa có" ngay. Tiền về
     lúc nào máy chủ trả lời lúc đó, nên khoảng đợi không còn phụ thuộc vào nhịp hỏi của ghế.
     Máy chủ có trần cứng riêng, con số này chỉ là mong muốn. Bản plugin cũ không hiểu ô này thì
     bỏ qua và trả lời ngay — vẫn chạy y như trước. */
  String r = wpGoi("luot", "\"cho\":4");
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
  /* ⚠️ CỘNG DỒN, không gán đè. Mỗi lượt gọi chỉ lấy về MỘT lệnh; anh Thắng bấm ba cái thì có
     ba lệnh nằm trong hàng chờ. Gán đè là lệnh thứ hai về trước khi vòng lặp chính kịp tiêu
     lệnh thứ nhất thì lệnh thứ nhất mất hẳn — tiền đã trừ, phút thì không bao giờ tới. */
  if(viec == "on"){ g_remoteStartMin += (phut>0 ? phut : MINUTES);
    Serial.printf("[CMD] web MO may +%d phut (cho: %d)\n", (phut>0?phut:MINUTES), g_remoteStartMin); }
  else if(viec == "off"){ g_remoteStop = true; Serial.println("[CMD] web TAT may"); }
  else if(viec == "reboot"){
    /* 🔴 KHỞI ĐỘNG LẠI TỪ XA — nhưng KHÔNG cắt ngang một lượt khách đang massage.
     *
     * Ghế ở 26 cửa hàng, không ai ở đó để rút điện; đây là cách duy nhất dựng lại một con ghế
     * treo mà không phải chạy tới nơi. Nhưng nếu khách đang nằm trên ghế và đã trả tiền thì
     * khởi động lại là cắt mất lượt của họ, và tiền thì đã vào sổ rồi — không dựng lại được.
     *
     * Nên: đánh dấu, rồi VÒNG LẶP CHÍNH khởi động lại lúc ghế rảnh. Chờ lâu nhất bằng đúng
     * một lượt massage. Người bấm ở web đã được nói trước điều này. */
    g_rebootCho = true;
    Serial.println("[CMD] web doi KHOI DONG LAI - se chay khi ghe ranh");
  }
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
  /* 🔴 CHỐT CUỐI. Chưa biết tài khoản nhận thì KHÔNG mở phiên, không dựng mã, không hiện gì cả.
     Chặn ở đây chứ không chỉ ở chỗ vẽ: mọi đường vào màn QR đều đi qua hàm này, nên một chốt ở
     đây là chốt cho tất cả — thêm một đường vào mới sau này cũng tự được chặn. */
  if(!duNhanTien()){
    Serial.println("[PAY] TU CHOI mo phien: chua biet tai khoan nhan (chua hoi duoc may chu lan nao)");
    veManChuaCoTk();
    return;
  }
  payAmount     = PKG_AMT[idx];
  payMinutes    = phutGoi(idx);
  g_goiDangChay = idx;   // để màn đếm ngược in đúng tên gói khách vừa chọn
  genCode(payCode);
  /* Tiền tố đứng TRƯỚC: ngân hàng nào cắt bớt nội dung thì cắt từ cuối, mà mất tiền tố là mất
     cả lượt (SePay không thấy), còn mất mã lượt thì trên web vẫn gán tay được. */
  payND      = (ND_TIEN_TO.length() ? ND_TIEN_TO + " " : "") + "GHE" + CHAIR_ID + " " + payCode;
  qrPayload  = buildVietQR(BANK_BIN, ACCOUNT_NO, payAmount, payND);
  waitUntil  = millis() + (unsigned long)PAY_WINDOW_S*1000UL;
  lastPayPoll = 0; lastShownSec = -1;
  Serial.println("[PAY] Phiên " + payND + " = " + String(payAmount) + "d, " + String(payMinutes) + "'");
  g_paidAmount = 0;
  state = ST_WAIT_PAY; screenDrawn=false; g_statusDirty=true;
  g_payWaiting = true;
  g_watchPayUntil = waitUntil + PAY_GRACE_MS;
}
/* ════════════════════════════════════════════════════════════════════════════════════════════
 * 🔴 LỖI 23/08/2026 — TRẢ TIỀN THÊM MÀ THỜI GIAN BỊ CẮT NGẮN LẠI
 *
 * Anh Thắng: *"Anh bấm nhiều lệnh, tiền vẫn trừ, nhưng số phút không được cộng"*.
 *
 * Bản cũ GHI ĐÈ `runUntil`, không cộng. Nên ghế đang chạy còn 30 phút mà nhận thêm một gói
 * 6 phút thì thành ĐÚNG 6 PHÚT — khách vừa trả thêm tiền và vừa MẤT 24 phút đã trả trước đó.
 * Không phải "không được cộng", mà là bị TRỪ.
 *
 * ⚠️ Đường TIỀN MẶT vốn đã cộng đúng (`runUntil += ...` trong checkCash), nên chỉ mình nó
 *    đúng còn MỌI đường khác đều sai: QR, tiêu ví, dùng mã, và cả bấm gói trên chính màn ghế.
 *    Một luật đúng nằm ở chỗ gọi thay vì nằm trong hàm được gọi thì nó chỉ đúng ở đúng chỗ đó.
 *    Nên nay luật nằm ở ĐÂY, và checkCash bỏ vế cộng tay đi.
 *
 * Cộng KHÔNG chặn trần: chặn trần là lấy mất thời gian khách đã trả tiền, mà đó đúng là thứ
 * hàm này vừa được sửa để không làm nữa.
 * ════════════════════════════════════════════════════════════════════════════════════════════ */
void startRunning(int minutes){
  if(minutes <= 0) return;
  g_payWaiting = false; g_watchPayUntil = 0;

  if(state == ST_RUNNING){
    runUntil += (unsigned long)minutes*60000UL;
    /* Không đụng `relaySet`, `state`, `screenDrawn`: ghế đang chạy, đang có người nằm trên đó.
       Chỉ báo màn vẽ lại con số đếm ngược cho khớp. */
    lastShownSec = -1; g_statusDirty = true;
    Serial.printf("[RUN] cong them %d phut (dang chay, con %ld giay)\n",
      minutes, (long)((runUntil - millis())/1000));
    return;
  }

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
  noInterrupts(); uint32_t p=g_cashPulses; g_cashPulses=0;
  uint32_t nhieu=g_tmNhieu; g_tmNhieu=0; interrupts();
  long amount = (long)p * CASH_VND_PER_PULSE;
  int minutes = (PRICE_VND>0) ? (int)((amount * (long)MINUTES) / PRICE_VND) : 0;
  Serial.printf("[CASH] %u xung = %ld d -> %d phut\n", (unsigned)p, amount, minutes);

  /* ⚠️ HAI PHÉP KIỂM NÀY ĐỨNG TRƯỚC MỌI ĐƯỜNG THOÁT. Đợt tiền nhỏ hơn một phút thì hàm này
     `return` ngay ở dòng dưới — mà đợt đó vẫn có thể là đợt đếm sai, và đó chính là đợt cần
     báo nhất. Đặt phép kiểm sau chỗ thoát là chỉ bắt được lỗi khi mọi thứ đang suôn sẻ. */
  if(nhieu >= CASH_NHIEU_NGUONG) ghiLoiTien("nhieu", false);
  /* Mọi mệnh giá VN từ 10.000 lên đều chia hết cho 10.000. Số tiền một đợt KHÔNG chia hết là
     đã mất hoặc thừa xung — tức là đếm sai tiền của khách, dù tổng nhìn vẫn "có vẻ hợp lý". */
  if(amount > 0 && (amount % CASH_BOI_SO) != 0) ghiLoiTien("lech", false);
  else if(amount > 0) g_tmLucTo = millis();

  if(minutes<=0) return;
  /* Vế "đang chạy thì cộng thêm" ĐÃ CHUYỂN VÀO `startRunning()` — xem chú thích ở đó. Giữ lại
     ở đây là hai nơi cùng một luật, và đó chính là lý do các đường khác sai suốt. */
  if(state != ST_RUNNING) { g_srcCode = 'c'; }
  startRunning(minutes);
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
  /* CỔNG TIỀN serial 4800 8E1 — thay đường xung. Khi ICT báo tờ tiền thật thì gọi
     mdbCreditVnd() (cộng giờ + ghi sổ như tiền mặt cũ); tiền mặt vẫn được relay
     thẳng sang ghế bên trong cong_tien.tick(). */
  congTien.khoiDong(mdbCreditVnd);

  prefs.begin("ghe", false);
  CHAIR_ID = prefs.getString("chair", "");   // nhớ mã ghế máy chủ đã gán, để mất mạng vẫn hiện đúng
  docCauHinh();                              // và nhớ luôn phần NHẬN TIỀN — xem khối trên luuCauHinh()

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
  kiemCucTien();
  checkCash();
  mdbTask();
  congTien.tick();     // relay tiền mặt ICT->ghế + phát hiện tờ tiền thật (gọi mdbCreditVnd)

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
    /* QR đã trả: GIẢ LÀM ICT bơm khung "có tiền" vào ghế -> ghế tự chạy đúng chương
       trình của nó (Hướng 1). startRunning() bên dưới chỉ để MÀN đếm ngược cho khớp. */
    congTien.bom(paid);
    g_srcCode='q'; g_runTotalVnd += paid; updateAcceptor(); startRunning(mins);
    return;
  }

  if(state==ST_IDLE){
    /* Khởi động lại theo lệnh web — chỉ khi RẢNH, xem checkRemoteCmd(). Nói ra trên màn trước
       khi tắt: người đứng cạnh ghế thấy nó tối đi mà không có lý do thì tưởng ghế hỏng. */
    if(g_rebootCho){
      tft.fillScreen(COL_BG);
      tft.setTextDatum(MC_DATUM);
      tft.setTextColor(COL_VANG, COL_BG);
      tft.drawString("DANG KHOI DONG LAI...", 160, 110, 4);
      tft.setTextColor(COL_MO, COL_BG);
      tft.drawString("Lenh tu he thong quan ly", 160, 140, 2);
      Serial.println("[CMD] khoi dong lai NGAY BAY GIO");
      delay(1200);
      ESP.restart();
    }
    if(!screenDrawn){ drawIdle(); screenDrawn=true; }
    int x,y;
    if(getTouch(x,y) && !CHUA_GAN && CHAIR_ID.length()){
      for(int i=0;i<PKG_N;i++) if(inBtn(PKG_BTN[i],x,y)){ startSession(i); delay(250); return; }
    }
    /* Ghế chưa gán mã HOẶC chưa có tài khoản thì vẽ lại màn mỗi 5s — để dòng trạng thái mạng
       cập nhật cho người đang đứng lắp máy nhìn, và để màn tự biến mất ngay khi lấy được cấu
       hình chứ không phải chờ ai chạm vào. */
    static unsigned long veLai=0;
    if((CHUA_GAN || CHAIR_ID.length()==0 || !duNhanTien()) && millis()-veLai > 5000){
      veLai=millis(); screenDrawn=false; }

    /* Luân phiên ô quảng cáo. CHỈ vẽ lại ĐÚNG MỘT Ô, không vẽ lại cả màn: một lượt fillScreen
       trên CYD mất ~90ms, và cứ 30 giây chớp cả màn hình một cái thì khách tưởng ghế lỗi. */
    if(QC_O >= 0 && QC_GIAM > 0 && CHAIR_ID.length() && !CHUA_GAN && duNhanTien()
       && screenDrawn && millis() - g_qcLuc > (unsigned long)QC_GIAY * 1000UL){
      g_qcLuc = millis();
      g_qcMat = !g_qcMat;
      if(QC_O < PKG_N) veTheGoi(QC_O);
    }
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
    /* MỘT NƠI DUY NHẤT quyết định có giữ kênh HTTPS hay không, suy thẳng từ "đang chờ trả".
       Bật/tắt bằng tay ở startSession/startRunning/nút huỷ là ba chỗ phải nhớ, và chỗ nào quên
       thì hoặc mất tốc độ, hoặc ôm 40KB bộ nhớ TLS suốt ngày mà không ai biết vì sao hết RAM. */
    g_giuKenh = dangCho;

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
