/* ============================================================================
 *  POSH QR — HỘP MỞ GHẾ MASSAGE BẰNG MÃ QR
 *  ----------------------------------------------------------------------------
 *  Khách quét mã QR đã trả tiền  ->  ESP32 kiểm chữ ký ngay trên chip  ->  gửi
 *  lệnh qua UART bảo bo ICT của ghế cho chạy N phút.
 *
 *  BA KHỐI, ba file:
 *      ma_qr.h    đọc + kiểm chữ ký mã QR (offline, không cần mạng)
 *      quet_qr.h  gom byte từ module quét mã nối UART1
 *      ict_ghe.h  nói chuyện với bo ghế qua UART2  ◄── phần hay phải chỉnh nhất
 *
 *  ⚠️ CHƯA BIẾT BO GHẾ NÓI KIỂU GÌ thì đừng nạp rồi ngồi đoán. Cắm cáp USB, mở
 *     Serial Monitor 115200, gõ  TRO  để xem bảng lệnh dò tín hiệu (dò baud, nghe
 *     lén, bắn thử khung, cầu nối sang máy tính). Xem thêm đầu file ict_ghe.h.
 *
 *  ⚠️ BÍ MẬT (khoá ký mã QR, mật khẩu WiFi/AP/trang nạp) KHÔNG nằm trong repo.
 *     Firmware đọc từ bộ nhớ trong (NVS) trước; NVS trống mà bản biên dịch có giá
 *     trị thật (build ở máy, có secrets.h) thì chép vào NVS rồi dùng. Giống hệt
 *     cách firmware chấm công làm, và vì cùng lý do: file .bin do CI build được
 *     đặt ở chỗ tải công khai nên KHÔNG được chứa bí mật nào.
 * ========================================================================== */
#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <Update.h>
#include <Preferences.h>
#include <time.h>
#include <sys/time.h>
#include "esp_mac.h"

#include "secrets.h"
#include "ma_qr.h"
#include "quet_qr.h"
#include "ict_ghe.h"

#define FW_VERSION "posh-qr 2026-08-25a (UART bo ghe + phong thi nghiem tin hieu)"

/* ============================================================================
 *  CHÂN CẮM — sửa cho khớp cách đấu dây thực tế
 *  ⚠️ Đọc phần "CHỌN CHÂN" ở đầu ict_ghe.h trước khi đổi. Có mấy chân đổi vào là
 *     ESP32 không boot được nữa, mà triệu chứng chỉ là "cắm điện không lên".
 * ========================================================================== */
#define CHAN_ICT_RX    16      // ESP32 nhận  <- TX bo ghế
#define CHAN_ICT_TX    17      // ESP32 gửi   -> RX bo ghế
#define CHAN_QR_RX     26      // ESP32 nhận  <- TX module quét mã
#define CHAN_QR_TX     27      // ESP32 gửi   -> RX module quét mã
#define CHAN_QR_KICH   -1      // chân TRIG của module quét, -1 = module tự quét liên tục
#define CHAN_COI       32      // còi báo (loại còi có mạch dao động sẵn: cấp điện là kêu)
#define CHAN_DEN_XANH  33      // đèn báo mở ghế thành công
#define CHAN_DEN_DO    22      // đèn báo mã hỏng
#define CHAN_NUT       0       // nút BOOT sẵn trên bo: giữ lúc cắm điện -> vào cầu nối UART

/* Chân điều khiển con đệm HT245 nằm trên đường UART của bo ICT. Để -1 nếu không đấu
   dây tới nó. Xem khối "BO CÓ CHIP ĐỆM HT245" ở đầu ict_ghe.h — nhất là cái bẫy
   ngưỡng vào 3,5V của họ 74HC khi chạy 5V. */
#define CHAN_HT245_OE  -1      // -> chân 19 (OE) của HT245; kéo CAO = thả nổi đầu ra cho ESP32 tự đẩy
#define CHAN_HT245_DIR -1      // -> chân 1  (DIR) của HT245

#define QR_BAUD_MD     9600    // baud module quét mã (đa số module xuất xưởng là 9600)

Preferences prefs;
WebServer   server(80);
DNSServer   dns;
QuetQR      quet;
IctGhe      ict;

/* ============================================================================
 *  CẤU HÌNH: NVS trước, secrets.h là dự phòng (và tự chép vào NVS)
 *  Y hệt cơ chế của firmware chấm công — kể cả cách bắt giá trị MẪU, vì đã trả giá
 *  một lần: giá trị mẫu lọt vào NVS thì sửa secrets.h nạp lại KHÔNG cứu được nữa.
 * ========================================================================== */
String cfgKhoaKy, cfgMayId, cfgApPass, cfgOtaUser, cfgOtaPass, cfgSsid, cfgWifiPass;
uint16_t cfgPhutMacDinh = 15;
uint8_t  cfgIctChe      = ICT_CHE_NHI_PHAN;
long     cfgIctBaud     = 9600;
bool     cfgCongDon     = true;    // quét mã mới lúc ghế đang chạy -> cộng thêm phút
bool     cfgBatBuocGio  = false;   // true = chip chưa biết giờ thì TỪ CHỐI mã có hạn
bool     g_chuaCauHinh  = false;

bool cfgLaPlaceholder(const String& v) {
  if (v.length() == 0) return true;
  if (v.startsWith("__CHUA_CAU_HINH") || v.startsWith("REPLACE")) return true;
  static const char* MAU[] = { "MAT_KHAU", "TEN_WIFI", "KHOA_KY", "DIEN_VAO",
                               "CHUA_KHAI", "MA_GHE", "TOKEN" };
  for (unsigned i = 0; i < sizeof(MAU) / sizeof(MAU[0]); i++)
    if (v.indexOf(MAU[i]) >= 0) return true;
  /* Ô chừa trống kiểu "..." / "---": không có lấy một chữ hoặc số nào thì không thể
     là giá trị thật. Bẫy này đã bắt hụt một lần ở firmware chấm công. */
  for (unsigned i = 0; i < v.length(); i++) {
    char c = v.charAt(i);
    if ((c >= '0' && c <= '9') || (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z')) return false;
  }
  return true;
}
String cfgLay(const char* khoa, const char* biencompile) {
  String v = prefs.getString(khoa, "");
  String c = String(biencompile ? biencompile : "");
  if (!cfgLaPlaceholder(v)) return v;
  if (!cfgLaPlaceholder(c)) {
    prefs.putString(khoa, c);
    Serial.printf("[CFG] di tru '%s' vao NVS\n", khoa);
    return c;
  }
  return "";
}
String macBo() {
  uint8_t m[6] = {0};
  char b[18] = "00:00:00:00:00:00";
  if (esp_read_mac(m, ESP_MAC_WIFI_STA) == ESP_OK)
    snprintf(b, sizeof(b), "%02X:%02X:%02X:%02X:%02X:%02X", m[0], m[1], m[2], m[3], m[4], m[5]);
  return String(b);
}
/** Che bí mật khi hiện lên portal: 4 ký tự đầu + độ dài, KHÔNG in giá trị thật. */
String cfgChe(const String& v) {
  if (!v.length()) return "(trống)";
  return v.substring(0, 4) + "…(" + String(v.length()) + " ký tự)";
}

void napCauHinh() {
  cfgKhoaKy   = cfgLay("khoaKy",  SEC_QR_KHOA);
  cfgMayId    = cfgLay("mayId",   SEC_MAY_ID);
  cfgApPass   = cfgLay("apPass",  SEC_AP_PASS);
  cfgOtaUser  = cfgLay("otaUser", SEC_OTA_USER);
  cfgOtaPass  = cfgLay("otaPass", SEC_OTA_PASS);
  cfgSsid     = cfgLay("ssid",    SEC_WIFI_SSID);
  cfgWifiPass = cfgLay("wifiPass",SEC_WIFI_PASS);
  if (cfgMayId.length() == 0) cfgMayId = "GHE-CHUA-DAT-TEN";
  cfgPhutMacDinh = prefs.getUShort("phutMD", 15);
  cfgIctChe      = (uint8_t)prefs.getUChar("ictChe", ICT_CHE_NHI_PHAN);
  cfgIctBaud     = prefs.getLong("ictBaud", 9600);
  cfgCongDon     = prefs.getBool("congDon", true);
  cfgBatBuocGio  = prefs.getBool("batGio",  false);
  if (cfgPhutMacDinh == 0 || cfgPhutMacDinh > QR_PHUT_TOI_DA) cfgPhutMacDinh = 15;
  if (cfgIctChe != ICT_CHE_NHI_PHAN && cfgIctChe != ICT_CHE_DONG_CHU) cfgIctChe = ICT_CHE_NHI_PHAN;

  g_chuaCauHinh = (cfgKhoaKy.length() == 0);
  Serial.printf("[CFG] may=%s khoaKy=%s otaPass=%s wifi=%s\n", cfgMayId.c_str(),
                cfgKhoaKy.length()   ? "có" : "THIẾU",
                cfgOtaPass.length()  ? "có" : "THIẾU",
                cfgSsid.length()     ? cfgSsid.c_str() : "(không khai - chỉ chạy AP)");
  if (g_chuaCauHinh)
    Serial.println("[CFG] 🔴 CHƯA CÓ KHOÁ KÝ — hộp sẽ TỪ CHỐI MỌI mã QR.\n"
                   "        Nối WiFi \"PoshQR-…\" rồi vào 192.168.4.1 để khai.");
}

/* ============================================================================
 *  ĐỒNG HỒ — hộp KHÔNG có pin đồng hồ
 *  ----------------------------------------------------------------------------
 *  Ô "hết hạn" trong mã QR chỉ có nghĩa khi hộp biết bây giờ là mấy giờ. Ba nguồn,
 *  theo thứ tự tin cậy:  NTP (khi có WiFi)  >  mã POSHT thợ quét  >  giờ nhớ lần cuối.
 *
 *  ⚠️ Giờ nhớ trong NVS luôn TRỄ hơn thực tế (mất điện bao lâu thì trễ bấy nhiêu),
 *     nên nó KHÔNG chặn được mã hết hạn. Điều đó chấp nhận được vì lớp chống xài
 *     lại (danh sách mã đã dùng) mới là lớp giữ tiền, và danh sách đó sống qua mất
 *     điện. Hạn dùng chỉ là lớp thứ hai.
 * ========================================================================== */
uint32_t g_gocEpoch = 0;      // epoch tại thời điểm g_gocMs
uint32_t g_gocMs    = 0;
bool     g_gioTinCay = false; // true = giờ lấy từ NTP hoặc mã POSHT trong phiên này
uint32_t g_luuGioMs = 0;

uint32_t gioHienTai() {
  if (g_gocEpoch == 0) return 0;
  return g_gocEpoch + (millis() - g_gocMs) / 1000;
}
void datGio(uint32_t epoch, bool tinCay) {
  if (epoch == 0) return;
  /* Không cho đồng hồ chạy lùi trừ khi nguồn đáng tin. Chạy lùi làm mã đã hết hạn
     sống lại — đúng cái mà ô hết hạn sinh ra để chặn. */
  uint32_t nay = gioHienTai();
  if (!tinCay && nay && epoch < nay) return;
  g_gocEpoch = epoch; g_gocMs = millis(); g_gioTinCay = g_gioTinCay || tinCay;
  struct timeval tv = { (time_t)epoch, 0 };
  settimeofday(&tv, nullptr);
  prefs.putULong("epoch", epoch);
  Serial.printf("[GIO] dat = %lu (%s)\n", (unsigned long)epoch, tinCay ? "tin cay" : "nho lai");
}
String gioDep(uint32_t e) {
  if (e == 0) return "(chua biet gio)";
  time_t t = (time_t)e + 7 * 3600;              // Việt Nam GMT+7
  struct tm tm; gmtime_r(&t, &tm);
  char b[24]; snprintf(b, sizeof(b), "%04d-%02d-%02d %02d:%02d:%02d",
                       tm.tm_year + 1900, tm.tm_mon + 1, tm.tm_mday, tm.tm_hour, tm.tm_min, tm.tm_sec);
  return String(b);
}

/* ============================================================================
 *  DANH SÁCH MÃ ĐÃ DÙNG — chống quét lại một ảnh chụp màn hình
 *  Vòng tròn 200 lượt gần nhất, mỗi lượt 8 byte (băm của "mã lượt"). Cả khối ghi
 *  xuống NVS thành MỘT blob, nên mỗi lượt bán chỉ tốn một lần ghi 1600 byte —
 *  ghế chạy cả chục phút mới có lượt sau, không lo mòn flash.
 * ========================================================================== */
#define DS_SO_LUOT 200
uint64_t g_dsMa[DS_SO_LUOT];
uint16_t g_dsViTri = 0;

void dsNap() {
  memset(g_dsMa, 0, sizeof(g_dsMa));
  size_t n = prefs.getBytes("dsMa", g_dsMa, sizeof(g_dsMa));
  if (n != sizeof(g_dsMa)) memset(g_dsMa, 0, sizeof(g_dsMa));
  g_dsViTri = prefs.getUShort("dsVt", 0);
  if (g_dsViTri >= DS_SO_LUOT) g_dsViTri = 0;
}
bool dsDaDung(uint64_t bam) {
  for (int i = 0; i < DS_SO_LUOT; i++) if (g_dsMa[i] == bam) return true;
  return false;
}
void dsGhiNho(uint64_t bam) {
  g_dsMa[g_dsViTri] = bam;
  g_dsViTri = (uint16_t)((g_dsViTri + 1) % DS_SO_LUOT);
  prefs.putBytes("dsMa", g_dsMa, sizeof(g_dsMa));
  prefs.putUShort("dsVt", g_dsViTri);
}
void dsXoa() {
  memset(g_dsMa, 0, sizeof(g_dsMa)); g_dsViTri = 0;
  prefs.putBytes("dsMa", g_dsMa, sizeof(g_dsMa)); prefs.putUShort("dsVt", 0);
  Serial.println("[DS] da xoa danh sach ma da dung");
}

/* ============================================================================
 *  TRẠNG THÁI GHẾ + BÁO HIỆU
 * ========================================================================== */
bool     g_dangChay   = false;
uint32_t g_hetLucMs   = 0;
uint32_t g_luotTong   = 0;
String   g_nhatKy[10];        // 10 dòng gần nhất, hiện lên portal cho thợ xem
uint8_t  g_nhatKyN    = 0;

void ghiNhatKy(const String& s) {
  String d = "[" + gioDep(gioHienTai()) + "] " + s;
  for (int i = 9; i > 0; i--) g_nhatKy[i] = g_nhatKy[i - 1];
  g_nhatKy[0] = d;
  if (g_nhatKyN < 10) g_nhatKyN++;
  Serial.println("[LOG] " + s);
}
void keu(int lan, int ms) {
  for (int i = 0; i < lan; i++) {
    digitalWrite(CHAN_COI, HIGH); delay(ms);
    digitalWrite(CHAN_COI, LOW);  if (i < lan - 1) delay(ms);
  }
}
void bao(bool ok) {
  digitalWrite(ok ? CHAN_DEN_XANH : CHAN_DEN_DO, HIGH);
  keu(ok ? 1 : 3, ok ? 180 : 90);
  delay(ok ? 300 : 120);
  digitalWrite(ok ? CHAN_DEN_XANH : CHAN_DEN_DO, LOW);
}

/* ============================================================================
 *  XỬ MỘT MÃ QUÉT ĐƯỢC
 * ========================================================================== */
void xuLyMa(const String& tho) {
  MaQR m = qrDoc(tho, cfgKhoaKy);

  if (m.loai == MA_HONG) { ghiNhatKy("❌ TỪ CHỐI — " + m.loi); bao(false); return; }

  if (m.loai == MA_DAT_GIO) {
    datGio(m.gioDat, true);
    ghiNhatKy("🕒 Thợ đặt giờ = " + gioDep(m.gioDat));
    bao(true); return;
  }

  /* --- mã mở ghế --- */
  if (m.may != "*" && m.may != cfgMayId) {
    ghiNhatKy("❌ TỪ CHỐI — mã dành cho ghế \"" + m.may + "\", hộp này là \"" + cfgMayId + "\"");
    bao(false); return;
  }
  uint32_t nay = gioHienTai();
  if (m.hetHan != 0) {
    if (nay == 0) {
      if (cfgBatBuocGio) {
        ghiNhatKy("❌ TỪ CHỐI — mã có hạn mà hộp chưa biết giờ (quét mã POSHT của thợ)");
        bao(false); return;
      }
      /* Không chặn, nhưng phải NÓI RA. Im lặng bỏ qua hạn dùng là kiểu lỗi tệ nhất:
         mọi thứ vẫn chạy, tới lúc phát hiện thì đã bán hớ cả tháng. */
      ghiNhatKy("⚠️ hộp chưa biết giờ -> BỎ QUA hạn dùng của mã");
    } else if (nay > m.hetHan) {
      ghiNhatKy("❌ TỪ CHỐI — mã hết hạn lúc " + gioDep(m.hetHan));
      bao(false); return;
    }
  }
  uint64_t bam = qrBamMaLuot(m.maLuot);
  if (dsDaDung(bam)) {
    ghiNhatKy("❌ TỪ CHỐI — mã lượt \"" + m.maLuot + "\" ĐÃ DÙNG rồi");
    bao(false); return;
  }
  if (g_dangChay && !cfgCongDon) {
    ghiNhatKy("❌ TỪ CHỐI — ghế đang chạy, hộp đặt KHÔNG cộng dồn");
    bao(false); return;
  }

  /* ⚠️ GHI NHỚ MÃ TRƯỚC KHI GỬI LỆNH, không phải sau.
     Nếu gửi trước mà mất điện đúng lúc đó thì mã chưa vào danh sách -> khách quét
     lại được lần nữa. Ghi trước thì tệ nhất là mất một lượt của khách đúng lúc mất
     điện — nhân viên xử tay được. Ngược lại thì mất tiền âm thầm, không ai biết. */
  dsGhiNho(bam);
  g_luotTong++; prefs.putULong("luot", g_luotTong);

  bool nhan = ict.moGhe(m.phut);
  g_dangChay = true;
  uint32_t themMs = (uint32_t)m.phut * 60000UL;
  g_hetLucMs = (cfgCongDon && g_hetLucMs > millis()) ? g_hetLucMs + themMs : millis() + themMs;

  ghiNhatKy(String(nhan ? "✅ MỞ GHẾ " : "⚠️ ĐÃ GỬI LỆNH MỞ GHẾ ") + String(m.phut) +
            " phút (mã " + m.maLuot + ")" +
            (nhan ? "" : " — BO KHÔNG TRẢ LỜI, xem ict.lanCuoi() / gõ TT"));
  bao(true);
  if (!nhan) {
    /* Không huỷ lượt: nhiều bo nhận lệnh xong im luôn, không hề trả ACK. Huỷ lượt ở
       đây là khách trả tiền mà ghế vẫn chạy còn hộp lại báo hỏng. Kêu thêm 2 tiếng
       để nhân viên để ý, và ghi rõ vào nhật ký. */
    keu(2, 60);
  }
}

/* ============================================================================
 *  BO GHẾ TỰ GỬI TIN — không phải trả lời lệnh nào của mình
 * ----------------------------------------------------------------------------
 *  Đây là chỗ để móc thêm khi đã biết bo nói gì. Ví dụ bo báo "hết giờ" thì đặt
 *  g_dangChay = false ở đây, thay vì để hộp tự đếm bằng đồng hồ của nó.
 *  Chưa biết giao thức thì CHỈ GHI LẠI, đừng đoán ý nghĩa — đoán sai thì ghế dừng
 *  giữa chừng mà nhân viên không hiểu vì sao.
 * ========================================================================== */
void xuLyKhungTuBo(const KhungIct& k) {
  ghiNhatKy("📥 bo ghế tự gửi: " + IctGhe::moTaKhung(k));
}

/* ============================================================================
 *  BẢNG LỆNH GÕ QUA CỔNG USB — chỗ dò tín hiệu bo ghế
 * ========================================================================== */
void inTro() {
  Serial.println(
    "\n===== LỆNH GÕ QUA CỔNG USB (115200) =====\n"
    "  TT              trạng thái hộp: giờ, cấu hình, lần trao đổi cuối với bo\n"
    "  --- dò tín hiệu bo ghế (làm theo ĐÚNG thứ tự này) ---\n"
    "  DAY             1. đo mức nghỉ của dây RX — loại ngay lỗi phần cứng, làm TRƯỚC\n"
    "  TUKIEM [số lần] 2. khép kín TX->RX rồi chạy (mặc định 200 lần). Chỉ 100% mới là đạt —\n"
    "                     mức điện áp thiếu ngưỡng chỉ lộ ra khi chạy nhiều lần\n"
    "  DO              3. dò baud của bo ghế (thử lần lượt các tốc độ thông dụng)\n"
    "  NGHE [giây]     nghe lén bo nói gì (mặc định 10 giây) — bấm nút trên ghế lúc này\n"
    "  BAUD <số>       đặt baud cho bo ghế rồi nhớ vào máy\n"
    "  KHUNG 1|2       1 = khung nhị phân 02..03, 2 = dòng chữ \"RUN 15\"\n"
    "  HEX <hex>       bắn thẳng chuỗi hex, ví dụ: HEX 02 03 31 00 0F 3D 03\n"
    "  CHU <chữ>       bắn một dòng chữ, tự thêm CR+LF\n"
    "  CAU             nối thẳng cổng USB với bo ghế (thoát: bấm RESET)\n"
    "  GIU 0|1         giữ chân TX ở mức thấp/cao để ĐO BẰNG ĐỒNG HỒ; gõ GIU trống để thả\n"
    "  --- con đệm HT245 trên đường UART (chỉ dùng nếu đã đấu dây tới OE/DIR) ---\n"
    "  OE 0|1          1 = cho HT245 dẫn, 0 = thả nổi đầu ra để ESP32 tự đẩy dây\n"
    "  CHIEU 0|1       đặt chân DIR của HT245 (1 = A->B)\n"
    "  --- lệnh thật ---\n"
    "  MO <phút>       gửi lệnh mở ghế\n"
    "  DUNG            gửi lệnh dừng ghế\n"
    "  PING            hỏi bo còn sống không\n"
    "  --- khác ---\n"
    "  QR <chuỗi>      giả lập một mã QR (khỏi phải in mã ra giấy để thử)\n"
    "  GIO <epoch>     đặt giờ cho hộp\n"
    "  XOAMA           xoá danh sách mã đã dùng (chỉ dùng khi thử nghiệm)\n"
    "  TRO             in lại bảng này\n"
    "=========================================\n");
}
void inTrangThai() {
  Serial.println("\n----- TRẠNG THÁI -----");
  Serial.println("  bản firmware : " FW_VERSION);
  Serial.println("  mã ghế       : " + cfgMayId + "   (MAC " + macBo() + ")");
  Serial.println("  khoá ký      : " + cfgChe(cfgKhoaKy));
  Serial.printf ("  bo ghế       : %ld baud, khung %s, RX=%d TX=%d\n",
                 ict.baud(), ict.che() == ICT_CHE_DONG_CHU ? "dòng chữ" : "nhị phân",
                 CHAN_ICT_RX, CHAN_ICT_TX);
  Serial.println("  lần cuối     : " + ict.lanCuoi());
  Serial.println("  giờ          : " + gioDep(gioHienTai()) + (g_gioTinCay ? " (tin cậy)" : " (nhớ lại / chưa đặt)"));
  Serial.printf ("  ghế          : %s%s\n", g_dangChay ? "ĐANG CHẠY" : "nghỉ",
                 g_dangChay ? (" — còn ~" + String((g_hetLucMs - millis()) / 60000) + " phút").c_str() : "");
  Serial.printf ("  đã bán       : %lu lượt\n", (unsigned long)g_luotTong);
  Serial.printf ("  WiFi         : %s\n", WiFi.status() == WL_CONNECTED
                 ? WiFi.localIP().toString().c_str() : "không nối (chỉ có AP 192.168.4.1)");
  Serial.println("----------------------\n");
}
void ngheLenhUsb() {
  static String d = "";
  while (Serial.available()) {
    char c = (char)Serial.read();
    if (c == '\r') continue;
    if (c != '\n') { if (d.length() < 220) d += c; continue; }

    String dong = d; d = ""; dong.trim();
    if (dong.length() == 0) continue;
    int sp = dong.indexOf(' ');
    String lenh = (sp < 0 ? dong : dong.substring(0, sp)); lenh.toUpperCase();
    String tham = (sp < 0 ? String("") : dong.substring(sp + 1)); tham.trim();

    if      (lenh == "TRO")  inTro();
    else if (lenh == "TT")   inTrangThai();
    else if (lenh == "DAY")  ict.doMucNghi();
    else if (lenh == "TUKIEM") { int n = tham.toInt(); ict.tuKiem(n > 0 ? n : 200); }
    else if (lenh == "GIU")  { if (tham.length() == 0) ict.giuMuc(-1);
                               else ict.giuMuc(tham.toInt() != 0 ? 1 : 0); }
    else if (lenh == "OE")    ict.chodan(tham.toInt() != 0);
    else if (lenh == "CHIEU") ict.datChieu(tham.toInt() != 0);
    else if (lenh == "DO")   { long b = ict.doBaud(); if (b) Serial.printf("   -> go 'BAUD %ld' de dat\n", b); }
    else if (lenh == "NGHE") { uint32_t g = tham.toInt(); ict.nghe((g ? g : 10) * 1000UL); }
    else if (lenh == "BAUD") { long b = tham.toInt();
                               if (b < 300 || b > 921600) Serial.println("baud khong hop ly");
                               else { ict.doiBaud(b); prefs.putLong("ictBaud", b);
                                 /* Mạch chuyển mức loại BSS138 kéo mức cao bằng trở 10k nên sườn
                                    xung ì. Baud cao + dây dài = bit bo tròn, lỗi lác đác chứ không
                                    chết hẳn — nhắc ngay lúc đặt cho khỏi mất buổi đi mò. */
                                 if (b > 38400) Serial.println(
                                   "[!] Baud > 38400: neu dang qua mach chuyen muc loai BSS138 (tro keo 10k)\n"
                                   "    thi suon xung se i -> loi lac dac. Ha ve 9600-38400, hoac doi sang\n"
                                   "    loai day doi xung (TXB0104 / SN74LVC2T45)."); } }
    else if (lenh == "KHUNG"){ int k = tham.toInt();
                               if (k != 1 && k != 2) Serial.println("chi co KHUNG 1 hoac KHUNG 2");
                               else { ict.doiChe((uint8_t)k); prefs.putUChar("ictChe", (uint8_t)k); } }
    else if (lenh == "HEX")  ict.banHex(tham);
    else if (lenh == "CHU")  ict.banChu(tham);
    else if (lenh == "CAU")  ict.cauNoi();
    else if (lenh == "MO")   { int p = tham.toInt(); if (p <= 0) p = cfgPhutMacDinh;
                               Serial.printf("[USB] gui lenh mo ghe %d phut\n", p);
                               bool ok = ict.moGhe((uint16_t)p);
                               g_dangChay = true; g_hetLucMs = millis() + (uint32_t)p * 60000UL;
                               Serial.println(ok ? "  -> bo NHAN" : "  -> bo KHONG tra loi"); }
    else if (lenh == "DUNG") { bool ok = ict.dungGhe(); g_dangChay = false;
                               Serial.println(ok ? "  -> bo NHAN" : "  -> bo KHONG tra loi"); }
    else if (lenh == "PING") Serial.println(ict.pingBo() ? "  -> bo con song" : "  -> bo KHONG tra loi");
    else if (lenh == "QR")   { Serial.println("[USB] gia lap ma: " + tham); xuLyMa(tham); }
    else if (lenh == "GIO")  { uint32_t e = (uint32_t)strtoul(tham.c_str(), nullptr, 10); datGio(e, true); }
    else if (lenh == "XOAMA") dsXoa();
    else Serial.println("khong hieu \"" + lenh + "\" — go TRO de xem bang lenh");
  }
}

/* ============================================================================
 *  PORTAL 192.168.4.1 — khai cấu hình + nạp firmware
 * ========================================================================== */
String oNhap(const char* ten, const char* nhan, const String& giaTri, const char* kieu) {
  return "<label style='display:block;margin:10px 0 4px;color:#94a3b8;font-size:13px'>" + String(nhan) + "</label>"
         "<input name='" + String(ten) + "' type='" + String(kieu) + "' value='" + giaTri + "' "
         "style='width:100%;padding:9px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:8px'>";
}
void trangChinh() {
  String h = "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
             "<body style='font-family:system-ui,Arial;background:#0f172a;color:#e2e8f0;padding:16px;max-width:560px;margin:auto'>";
  h += "<h2 style='color:#38bdf8'>POSH QR — " + cfgMayId + "</h2>";
  if (g_chuaCauHinh)
    h += "<p style='background:#7f1d1d;padding:10px;border-radius:8px'>🔴 <b>Chưa có khoá ký</b> — hộp đang từ chối mọi mã QR.</p>";
  h += "<p style='color:#94a3b8;font-size:13px'>Bản " FW_VERSION "<br>MAC " + macBo() +
       "<br>Giờ: " + gioDep(gioHienTai()) + (g_gioTinCay ? " (tin cậy)" : " (nhớ lại)") +
       "<br>Ghế: " + (g_dangChay ? "ĐANG CHẠY" : "nghỉ") +
       " · đã bán " + String(g_luotTong) + " lượt</p>";
  h += "<p style='color:#94a3b8;font-size:13px'>Bo ghế: " + String(ict.baud()) + " baud, khung " +
       (ict.che() == ICT_CHE_DONG_CHU ? "dòng chữ" : "nhị phân") +
       "<br>Lần trao đổi cuối: <code style='color:#fbbf24'>" + ict.lanCuoi() + "</code></p>";

  h += "<form method='POST' action='/luu'>";
  h += oNhap("mayId",   "Mã ghế (phải khớp ô &lt;máy&gt; trong mã QR)", cfgMayId, "text");
  h += oNhap("khoaKy",  "Khoá ký mã QR — để trống nếu không đổi", "", "password");
  h += oNhap("ssid",    "WiFi (để trống nếu ghế không có WiFi)", cfgSsid, "text");
  h += oNhap("wifiPass","Mật khẩu WiFi — để trống nếu không đổi", "", "password");
  h += oNhap("apPass",  "Mật khẩu AP của hộp — để trống nếu không đổi", "", "password");
  h += oNhap("otaUser", "Tài khoản trang nạp firmware", cfgOtaUser, "text");
  h += oNhap("otaPass", "Mật khẩu trang nạp — để trống nếu không đổi", "", "password");
  h += oNhap("phutMD",  "Số phút mặc định", String(cfgPhutMacDinh), "number");
  h += oNhap("ictBaud", "Baud bo ghế", String(ict.baud()), "number");
  h += "<label style='display:block;margin:10px 0 4px;color:#94a3b8;font-size:13px'>Khung lệnh bo ghế</label>"
       "<select name='ictChe' style='width:100%;padding:9px;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:8px'>"
       "<option value='1'" + String(ict.che() == 1 ? " selected" : "") + ">1 — nhị phân 02…03</option>"
       "<option value='2'" + String(ict.che() == 2 ? " selected" : "") + ">2 — dòng chữ \"RUN 15\"</option></select>";
  h += "<label style='display:block;margin:12px 0 4px'><input type='checkbox' name='congDon' value='1'" +
       String(cfgCongDon ? " checked" : "") + "> Quét mã lúc ghế đang chạy thì <b>cộng thêm phút</b></label>";
  h += "<label style='display:block;margin:4px 0 12px'><input type='checkbox' name='batGio' value='1'" +
       String(cfgBatBuocGio ? " checked" : "") + "> <b>Từ chối</b> mã có hạn khi hộp chưa biết giờ</label>";
  h += "<button style='width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:700;font-size:16px'>Lưu &amp; khởi động lại</button></form>";

  h += "<h3 style='color:#38bdf8;margin-top:22px'>Nhật ký gần đây</h3><pre style='background:#1e293b;padding:10px;border-radius:8px;font-size:12px;white-space:pre-wrap'>";
  if (g_nhatKyN == 0) h += "(chưa có)";
  for (int i = 0; i < g_nhatKyN; i++) h += g_nhatKy[i] + "\n";
  h += "</pre>";
  h += "<p><a href='/update' style='color:#38bdf8'>⬆️ Nạp firmware</a></p>";
  h += "<p style='color:#64748b;font-size:12px'>Dò tín hiệu bo ghế thì cắm cáp USB, mở Serial Monitor 115200, gõ <b>TRO</b>.</p></body>";
  server.send(200, "text/html; charset=utf-8", h);
}
void trangLuu() {
  auto lay = [&](const char* t) { return server.hasArg(t) ? server.arg(t) : String(""); };
  String v;
  v = lay("mayId");   if (v.length()) { v.trim(); prefs.putString("mayId", v); }
  v = lay("khoaKy");  if (v.length()) prefs.putString("khoaKy", v);
  v = lay("ssid");    prefs.putString("ssid", v);              // cho phép xoá trắng
  v = lay("wifiPass");if (v.length()) prefs.putString("wifiPass", v);
  v = lay("apPass");  if (v.length()) prefs.putString("apPass", v);
  v = lay("otaUser"); if (v.length()) prefs.putString("otaUser", v);
  v = lay("otaPass"); if (v.length()) prefs.putString("otaPass", v);
  int p = lay("phutMD").toInt();  if (p > 0 && p <= QR_PHUT_TOI_DA) prefs.putUShort("phutMD", (uint16_t)p);
  long b = lay("ictBaud").toInt(); if (b >= 300 && b <= 921600)     prefs.putLong("ictBaud", b);
  int k = lay("ictChe").toInt();   if (k == 1 || k == 2)            prefs.putUChar("ictChe", (uint8_t)k);
  prefs.putBool("congDon", lay("congDon") == "1");
  prefs.putBool("batGio",  lay("batGio")  == "1");
  server.send(200, "text/plain; charset=utf-8", "Đã lưu. Hộp khởi động lại…");
  delay(800); ESP.restart();
}
void trangUpdate() {
  if (cfgOtaPass.length() == 0) { server.send(503, "text/plain; charset=utf-8",
    "Chua khai mat khau trang nap. Vao 192.168.4.1 khai truoc."); return; }
  if (!server.authenticate(cfgOtaUser.c_str(), cfgOtaPass.c_str())) return server.requestAuthentication();
  String h = "<!doctype html><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
             "<body style='font-family:system-ui;background:#0f172a;color:#e2e8f0;padding:16px'>"
             "<h2 style='color:#38bdf8'>⬆️ Nạp firmware</h2>"
             "<p style='color:#94a3b8;font-size:14px'>Chọn file <b>.bin</b> rồi bấm Nạp. <b>KHÔNG tắt nguồn</b> khi đang nạp.</p>"
             "<form method='POST' action='/update' enctype='multipart/form-data'>"
             "<input type='file' name='fw' accept='.bin' style='width:100%;padding:10px;margin:8px 0;background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:8px'>"
             "<button style='width:100%;padding:12px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-weight:700'>🚀 Nạp</button></form>"
             "<p style='color:#94a3b8;font-size:12px'>Bản hiện tại: " FW_VERSION "</p>"
             "<p><a href='/' style='color:#38bdf8'>← Về trang chính</a></p></body>";
  server.send(200, "text/html; charset=utf-8", h);
}
void trangUpdateXong() {
  if (!server.authenticate(cfgOtaUser.c_str(), cfgOtaPass.c_str())) return server.requestAuthentication();
  bool ok = !Update.hasError();
  server.send(200, "text/html; charset=utf-8",
    String("<!doctype html><meta charset='utf-8'><body style='font-family:system-ui;padding:20px'>") +
    (ok ? "✅ <b>Nạp xong.</b> Hộp đang khởi động lại…" : "❌ <b>Nạp LỖI.</b> Kiểm tra lại file .bin.") + "</body>");
  delay(800); if (ok) ESP.restart();
}
void trangUpdateTai() {
  HTTPUpload& up = server.upload();
  if (up.status == UPLOAD_FILE_START) {
    if (cfgOtaPass.length() == 0) { Serial.println("[OTA] tu choi: chua khai mat khau"); return; }
    if (!server.authenticate(cfgOtaUser.c_str(), cfgOtaPass.c_str())) { Serial.println("[OTA] tu choi: chua dang nhap"); return; }
    Serial.printf("[OTA] bat dau nap: %s\n", up.filename.c_str());
    if (!Update.begin(UPDATE_SIZE_UNKNOWN)) Update.printError(Serial);
  } else if (up.status == UPLOAD_FILE_WRITE) {
    if (Update.write(up.buf, up.currentSize) != up.currentSize) Update.printError(Serial);
  } else if (up.status == UPLOAD_FILE_END) {
    if (Update.end(true)) Serial.printf("[OTA] xong %u byte\n", up.totalSize); else Update.printError(Serial);
  }
}

/* ========================================================================== */
void setup() {
  Serial.begin(115200);
  delay(400);
  Serial.println("\n\n============ " FW_VERSION " ============");

  pinMode(CHAN_COI, OUTPUT);       digitalWrite(CHAN_COI, LOW);
  pinMode(CHAN_DEN_XANH, OUTPUT);  digitalWrite(CHAN_DEN_XANH, LOW);
  pinMode(CHAN_DEN_DO, OUTPUT);    digitalWrite(CHAN_DEN_DO, LOW);
  pinMode(CHAN_NUT, INPUT_PULLUP);

  prefs.begin("poshqr", false);
  napCauHinh();
  dsNap();
  g_luotTong = prefs.getULong("luot", 0);
  datGio(prefs.getULong("epoch", 0), false);

  ict.batDau(&Serial2, CHAN_ICT_RX, CHAN_ICT_TX, cfgIctBaud, cfgIctChe);
  ict.datChanHT245(CHAN_HT245_OE, CHAN_HT245_DIR);
  quet.batDau(&Serial1, CHAN_QR_RX, CHAN_QR_TX, QR_BAUD_MD, CHAN_QR_KICH);

  /* Giữ nút BOOT lúc cắm điện -> vào thẳng cầu nối USB<->bo ghế. Không cần nạp lại
     firmware chỉ để soi bo. */
  if (digitalRead(CHAN_NUT) == LOW) {
    Serial.println("[BOOT] giu nut -> vao CAU NOI UART (thoat: bam RESET)");
    keu(2, 80);
    ict.cauNoi();                   // không bao giờ trả về
  }

  WiFi.mode(WIFI_AP_STA);
  String apTen  = "PoshQR-" + cfgMayId;
  bool   apKhoa = (cfgApPass.length() >= 8);
  WiFi.softAP(apTen.c_str(), apKhoa ? cfgApPass.c_str() : nullptr);
  Serial.printf("[AP] \"%s\" %s @ 192.168.4.1\n", apTen.c_str(), apKhoa ? "(co mat khau)" : "(MO - chua khai mat khau)");
  if (cfgSsid.length()) {
    WiFi.begin(cfgSsid.c_str(), cfgWifiPass.c_str());
    Serial.printf("[WIFI] dang noi \"%s\"", cfgSsid.c_str());
    for (int i = 0; i < 20 && WiFi.status() != WL_CONNECTED; i++) { delay(400); Serial.print("."); }
    Serial.println(WiFi.status() == WL_CONNECTED ? " OK " + WiFi.localIP().toString() : " KHONG NOI DUOC");
    if (WiFi.status() == WL_CONNECTED) configTime(0, 0, "pool.ntp.org", "time.google.com");
  }
  dns.start(53, "*", IPAddress(192, 168, 4, 1));

  server.on("/", trangChinh);
  server.on("/luu", HTTP_POST, trangLuu);
  server.on("/update", HTTP_GET, trangUpdate);
  server.on("/update", HTTP_POST, trangUpdateXong, trangUpdateTai);
  server.onNotFound([]() { server.sendHeader("Location", "http://192.168.4.1/"); server.send(302, "text/plain", ""); });
  server.begin();

  ghiNhatKy("Hộp khởi động — " + cfgMayId);
  inTro();
  keu(1, 120);
}

void loop() {
  dns.processNextRequest();
  server.handleClient();
  ngheLenhUsb();

  // Bộ thu của bo ghế chạy nền: tin bo TỰ GỬI cũng không rơi mất.
  if (ict.chay()) { while (ict.coKhungMoi()) xuLyKhungTuBo(ict.layKhung()); }

  String ma = quet.doc();
  if (ma.length()) xuLyMa(ma);

  if (g_dangChay && (int32_t)(g_hetLucMs - millis()) <= 0) {
    g_dangChay = false;
    ghiNhatKy("⏹ Hết giờ (theo đồng hồ của hộp)");
  }

  // NTP về thì lấy làm giờ chuẩn — chỉ cần một lần là đủ tin cậy cho tới lúc mất điện.
  static uint32_t ntpMs = 0;
  if (!g_gioTinCay && WiFi.status() == WL_CONNECTED && millis() - ntpMs > 5000) {
    ntpMs = millis();
    time_t t = time(nullptr);
    if (t > 1700000000) datGio((uint32_t)t, true);
  }
  // Nhớ giờ xuống NVS mỗi 10 phút, để mất điện xong còn có cái mà lần.
  if (g_gocEpoch && millis() - g_luuGioMs > 600000UL) {
    g_luuGioMs = millis();
    prefs.putULong("epoch", gioHienTai());
  }
  delay(2);
}
