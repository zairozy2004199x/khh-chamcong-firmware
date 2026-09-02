/* ============================================================================
 *  THỢ NẠP dsPIC — nạp firmware cho dsPIC33F từ thẻ nhớ, KHÔNG CẦN MÁY TÍNH
 *  (BẢN CÓ MÀN HÌNH CYD — chọn/soi bằng nút BOOT + màn TFT, không cần cảm ứng)
 * ----------------------------------------------------------------------------
 *  VÌ SAO CÓ CÁI NÀY: PICkit bắt buộc phải cắm vào máy tính. Chế độ
 *  Programmer-To-Go thì hàng nhái không chạy, mà chạy được cũng chỉ giữ ĐÚNG MỘT
 *  ảnh — muốn đổi bản là phải mang về máy tính.
 *  Máy này để cả xấp file .hex trong thẻ nhớ, đứng tại chỗ CHỌN bản nào thì nạp
 *  bản đó, và tải bản mới về thẻ qua WiFi — giống hệt con "thợ nạp" đang dùng cho
 *  máy chấm công.
 *
 *  ⚠️⚠️ BỘ NẠP NÀY ĐANG Ở CHẾ ĐỘ CHỈ ĐỌC. CỐ Ý.
 *     Chuỗi lệnh ICSP trong icsp_dspic.h viết theo tài liệu Microchip DS70152
 *     nhưng CHƯA đối chiếu được từng dòng với bản gốc. Ghi bằng chuỗi lệnh sai là
 *     hỏng bo thật. Thứ tự bắt buộc: MACHIP (đọc mã chip) -> DOC (đọc thử flash) ->
 *     đúng rồi mới viết tiếp phần XOÁ/GHI.
 *
 *  ─── ĐẤU DÂY dsPIC (ICSP) ───────────────────────────────────────────────────
 *    dsPIC33F chạy 3,3V, vào chế độ nạp chỉ cần MCLR ở mức VDD (không cần Vpp) ->
 *    nối THẲNG. Đo VDD NGAY TẠI CHÂN CHIP trước khi cắm (tối đa 4,0V).
 *
 *      ESP32 <CHAN_MCLR> ──► MCLR   của dsPIC
 *      ESP32 <CHAN_PGEC> ──► PGEC
 *      ESP32 <CHAN_PGED> ◄─► PGED   (hai chiều)
 *      GND               ───  VSS
 *      Thẻ nhớ (khe SD trên CYD): SPI SCK18 / MISO19 / MOSI23, CS=5
 *
 *  ─── CHÂN TRÊN CYD — ĐỌC KỸ ─────────────────────────────────────────────────
 *    Bản này chạy trên CYD (ESP32-2432S028) nên MÀN + ĐÈN NỀN + THẺ SD đã CHIẾM
 *    sẵn nhiều chân. Màn: 12/13/14/15/2, đèn nền GPIO21 (TFT_eSPI tự giữ — ĐỪNG
 *    dùng 21 cho việc khác, đó là lỗi "màn đen" của bản cũ). Thẻ SD: 5/18/19/23.
 *    Cảm ứng (không dùng ở đây): 25/32/33/39.
 *
 *    🔴 3 chân ICSP dưới đây MẶC ĐỊNH để 32/33/25 (giống bản DevKit cũ). Nhưng trên
 *       CYD, 32/33/25 là chân của IC CẢM ỨNG, KHÔNG hở ra hàng chân — muốn cắm dsPIC
 *       ra ngoài thì phải đổi sang chân CYD có hở: GPIO22, GPIO27 (I/O), GPIO35
 *       (CHỈ ĐỌC — không dùng cho PGED/MCLR/PGEC được). Ví dụ nếu bo anh hở 22/27
 *       và một chân I/O nữa (16/17 ở bo 2-USB) thì sửa 3 dòng #define bên dưới cho
 *       khớp DÂY THỰC TẾ rồi nạp lại. Sai chân là đọc mã chip sẽ ra rác.
 * ========================================================================== */
#include <SD.h>
#include <SPI.h>
#include <TFT_eSPI.h>
#include "doc_hex.h"
#include "icsp_dspic.h"

#define FW_VERSION "nap-pic 2026-09-02a (CYD man hinh + nut BOOT; dsPIC33F — moi chi DOC)"

/* ---- CHÂN ICSP (đổi cho khớp dây thực tế trên CYD — xem chú thích đầu file) ---- */
#define CHAN_MCLR   32     // ►MCLR  (mặc định = chân cảm ứng T_DIN; đổi nếu cắm ra ngoài)
#define CHAN_PGEC   33     // ►PGEC  (mặc định = chân cảm ứng T_CS)
#define CHAN_PGED   25     // ◄►PGED (mặc định = chân cảm ứng T_CLK)

/* ---- CHÂN ĐIỀU KHIỂN / BÁO ---- */
#define CHAN_NUT     0     // nút BOOT sẵn trên bo: bấm=chuyển mục, giữ 1.5s=chọn/chạy
#define CHAN_COI    22     // còi (tuỳ chọn; để -1 nếu không gắn). ĐỪNG để 21 (đèn nền)!
#define SD_CS        5     // thẻ SD trên CYD

/* ⚠️ KHÔNG định nghĩa chân đèn nền ở đây. GPIO21 do TFT_eSPI quản (TFT_BL trong
   User_Setup.h). Bản cũ để CHAN_DEN=21 và tắt nó -> MÀN ĐEN. Đã bỏ hẳn. */

#define MAX_FILE    16      // số file .hex nhớ được trong danh sách

/* ---- Màu giao diện (RGB565) ---- */
#define C_NEN    0x0000     // đen
#define C_TRANG  0xFFFF
#define C_XAM    0x8410
#define C_VANG   0xFEA0
#define C_LUC    0x2E8B
#define C_DO     0xF9A0
#define C_CYAN   0x07FF

TFT_eSPI  tft = TFT_eSPI();
IcspDsPic icsp;
String   g_ten[MAX_FILE];
int      g_soFile = 0;
int      g_chon   = -1;     // file đang CHỌN (dùng cho SO SÁNH / KIỂM)
int      g_cur    = 0;      // con trỏ MENU (file + các nút lệnh)
bool     g_coThe  = false;

/* Ô kết quả dưới màn — vòng 6 dòng gần nhất. */
String   g_kq[6];
int      g_kqN = 0;

/* Số mục lệnh cố định sau danh sách file: [ĐỌC MÃ CHIP] [SO SÁNH] [KIỂM FILE]. */
#define SO_LENH_MENU 3

void veManHinh();   // khai báo trước (ghiKetQua gọi tới)

/* ============================================================================
 *  TIỆN ÍCH MÀN HÌNH
 * ========================================================================== */
String tenNgan(const String& duong) {
  String t = duong;
  int sl = t.lastIndexOf('/');
  if (sl >= 0) t = t.substring(sl + 1);
  if (t.length() > 22) t = t.substring(0, 21) + "~";
  return t;
}

/* Ghi 1 dòng kết quả (ra cả Serial lẫn màn). */
void ghiKetQua(const String& s) {
  Serial.println("[KQ] " + s);
  if (g_kqN < 6) g_kq[g_kqN++] = s;
  else { for (int i = 0; i < 5; i++) g_kq[i] = g_kq[i + 1]; g_kq[5] = s; }
  veManHinh();
}

int soMuc() { return g_soFile + SO_LENH_MENU; }  // tổng mục trong menu

void veManHinh() {
  tft.fillScreen(C_NEN);
  tft.setTextDatum(TL_DATUM);

  // ----- Đầu trang -----
  tft.setTextColor(C_TRANG, C_NEN);
  tft.drawString("THO NAP dsPIC", 8, 6, 4);
  tft.setTextColor(g_coThe ? C_LUC : C_DO, C_NEN);
  tft.drawString(g_coThe ? ("The SD: " + String(g_soFile) + " file .hex")
                         : "The SD: KHONG doc duoc", 8, 36, 2);
  tft.setTextColor(icsp.choPhepGhi ? C_DO : C_XAM, C_NEN);
  tft.setTextDatum(TR_DATUM);
  tft.drawString(icsp.choPhepGhi ? "GHI DUOC" : "CHI DOC", 312, 36, 2);
  tft.setTextDatum(TL_DATUM);

  // ----- Danh sách menu (file + nút lệnh) -----
  int y = 56;
  int tong = soMuc();
  for (int i = 0; i < tong && y < 150; i++) {
    bool cur   = (i == g_cur);
    bool laFile = (i < g_soFile);
    String nhan;
    if (laFile) nhan = String(i + 1) + ") " + tenNgan(g_ten[i]) + (i == g_chon ? "  [dang chon]" : "");
    else if (i == g_soFile)     nhan = "> DOC MA CHIP (an toan)";
    else if (i == g_soFile + 1) nhan = "> SO SANH ban dang chon";
    else                        nhan = "> KIEM FILE dang chon";

    if (cur) { tft.fillRect(4, y - 1, 312, 19, C_VANG); tft.setTextColor(C_NEN, C_VANG); }
    else       tft.setTextColor(laFile ? C_TRANG : C_CYAN, C_NEN);
    tft.drawString(nhan, 10, y, 2);
    y += 19;
  }
  if (g_soFile == 0) {
    tft.setTextColor(C_DO, C_NEN);
    tft.drawString("(the chua co file .hex nao)", 10, y, 2);
  }

  // ----- Ô kết quả -----
  tft.drawFastHLine(0, 151, 320, C_XAM);
  tft.setTextColor(C_XAM, C_NEN);
  tft.drawString("Ket qua:", 8, 155, 2);
  int yy = 172;
  for (int i = 0; i < g_kqN && yy < 226; i++) {
    tft.setTextColor(C_TRANG, C_NEN);
    tft.drawString(g_kq[i], 10, yy, 2);
    yy += 17;
  }

  // ----- Gợi ý thao tác -----
  tft.setTextColor(C_XAM, C_NEN);
  tft.setTextDatum(BL_DATUM);
  tft.drawString("BOOT: bam=chuyen muc | giu 1.5s=chon/chay", 8, 238, 1);
  tft.setTextDatum(TL_DATUM);
}

/* ============================================================================
 *  THẺ NHỚ — liệt kê các file .hex
 * ========================================================================== */
void quetThe() {
  g_soFile = 0; g_chon = -1; g_cur = 0;
  if (!g_coThe) { Serial.println("[SD] chua co the nho"); return; }
  File goc = SD.open("/");
  if (!goc) { Serial.println("[SD] khong mo duoc thu muc goc"); return; }
  for (File f = goc.openNextFile(); f; f = goc.openNextFile()) {
    if (f.isDirectory()) { f.close(); continue; }
    String t = String(f.name());
    if (!t.startsWith("/")) t = "/" + t;
    String th = t; th.toLowerCase();
    if (th.endsWith(".hex") && g_soFile < MAX_FILE) {
      g_ten[g_soFile++] = t;
      Serial.printf("[SD] %d) %s  (%lu byte)\n", g_soFile, t.c_str(), (unsigned long)f.size());
    }
    f.close();
  }
  goc.close();
  if (g_soFile == 0) Serial.println("[SD] ⚠️ khong tim thay file .hex nao trong the");
  else g_chon = 0;
}

/* ============================================================================
 *  ĐỌC THỬ CẢ FILE .hex — kiểm file trước, KHÔNG đụng tới chip
 * ========================================================================== */
bool kiemFile(const String& duong, uint32_t* soTuRa, uint32_t* dauRa, uint32_t* cuoiRa) {
  File f = SD.open(duong.c_str(), FILE_READ);
  if (!f) { Serial.println("[HEX] khong mo duoc " + duong); return false; }
  DocHex d; d.batDau();
  TuChuongTrinh ra[8];
  String dong; dong.reserve(600);
  uint32_t dau = 0xFFFFFFFF, cuoi = 0;
  bool ok = true;
  while (f.available()) {
    char c = (char)f.read();
    if (c != '\n') { if (dong.length() < 560) dong += c; continue; }
    int n = d.nap(dong.c_str(), ra, 8);
    dong = "";
    if (n < 0) { Serial.printf("[HEX] 🔴 %s\n", d.loi()); ok = false; break; }
    for (int i = 0; i < n; i++) {
      if (ra[i].diaChi < dau) dau = ra[i].diaChi;
      if (ra[i].diaChi > cuoi) cuoi = ra[i].diaChi;
    }
  }
  if (ok && dong.length()) {
    int n = d.nap(dong.c_str(), ra, 8);
    if (n < 0) { Serial.printf("[HEX] 🔴 %s\n", d.loi()); ok = false; }
  }
  f.close();
  if (ok && !d.xong()) { Serial.println("[HEX] 🔴 file thieu ban ghi ket thuc — co the bi cut"); ok = false; }
  if (!ok) return false;
  if (soTuRa) *soTuRa = d.soTu();
  if (dauRa)  *dauRa  = dau;
  if (cuoiRa) *cuoiRa = cuoi;
  Serial.printf("[HEX] ✅ %s: %lu lenh, dia chi %06lX..%06lX, %lu dong\n",
                duong.c_str(), (unsigned long)d.soTu(),
                (unsigned long)dau, (unsigned long)cuoi, (unsigned long)d.soDong());
  if (cuoi >= ICSP_CONFIG_DAU)
    Serial.println("[HEX] ⚠️ file CO ghi vung cau hinh (0xF80000+). Ghi sai FICD la MAT LUON duong ICSP.");
  return true;
}

/* ============================================================================
 *  SO BẢN TRONG CHIP VỚI FILE — chỉ đọc, dùng được ngay, không rủi ro gì
 * ========================================================================== */
void soSanh(const String& duong) {
  File f = SD.open(duong.c_str(), FILE_READ);
  if (!f) { ghiKetQua("SS: khong mo duoc file"); return; }
  if (!icsp.moPhien()) { f.close(); ghiKetQua("SS: khong vao duoc che do nap"); return; }

  DocHex d; d.batDau();
  TuChuongTrinh ra[8];
  String dong; dong.reserve(600);
  uint32_t soKhop = 0, soLech = 0;
  Serial.println("[SS] dang so... (256 KB mat khoang 2 phut)");
  while (f.available() && soLech < 20) {
    char c = (char)f.read();
    if (c != '\n') { if (dong.length() < 560) dong += c; continue; }
    int n = d.nap(dong.c_str(), ra, 8);
    dong = "";
    if (n < 0) { Serial.printf("[SS] 🔴 %s\n", d.loi()); break; }
    for (int i = 0; i < n; i++) {
      uint32_t trongChip = icsp.docTu(ra[i].diaChi);
      if (trongChip == ra[i].giaTri) soKhop++;
      else {
        soLech++;
        Serial.printf("[SS] lech tai %06lX: file %06lX  chip %06lX\n",
                      (unsigned long)ra[i].diaChi, (unsigned long)ra[i].giaTri,
                      (unsigned long)trongChip);
      }
    }
  }
  f.close();
  icsp.dongPhien();
  Serial.printf("[SS] khop %lu, lech %lu\n", (unsigned long)soKhop, (unsigned long)soLech);
  ghiKetQua("SS: khop " + String(soKhop) + ", lech " + String(soLech));
  if (soLech == 0 && soKhop > 0)      ghiKetQua("SS: chip DANG chay dung ban nay.");
  else if (soKhop == 0)               ghiKetQua("SS: khong khop — chay DOC MA CHIP truoc.");
}

/* ============================================================================
 *  CHỌN / CHẠY MỘT MỤC MENU
 * ========================================================================== */
void docMaChipManHinh() {
  ghiKetQua("Dang doc ma chip...");
  uint16_t ma = 0, ban = 0;
  const ChipBiet* cb = icsp.docMaChip(&ma, &ban);
  if (cb) ghiKetQua("Chip: " + String(cb->ten));
  else {
    char t[24]; snprintf(t, sizeof(t), "Ma %04X ban %04X", ma, ban);
    ghiKetQua("Chip la: " + String(t) + " (chua co bang)");
  }
}

void keu(int lan, int ms);   // khai báo trước

void kichHoat(int i) {
  if (i < g_soFile) {                     // chọn 1 file
    g_chon = i;
    ghiKetQua("Da chon: " + tenNgan(g_ten[i]));
    keu(1, 80);
  } else if (i == g_soFile) {             // ĐỌC MÃ CHIP
    docMaChipManHinh();
    keu(1, 150);
  } else if (i == g_soFile + 1) {         // SO SÁNH
    if (g_chon < 0) { ghiKetQua("Chua chon file nao"); keu(4, 60); }
    else            soSanh(g_ten[g_chon]);
  } else {                                // KIỂM FILE
    if (g_chon < 0) { ghiKetQua("Chua chon file nao"); keu(4, 60); }
    else {
      uint32_t st, d1, d2;
      bool ok = kiemFile(g_ten[g_chon], &st, &d1, &d2);
      ghiKetQua(ok ? ("File OK: " + String((unsigned long)st) + " lenh") : "File .hex LOI (xem Serial)");
      keu(ok ? 1 : 5, 150);
    }
  }
}

/* ============================================================================
 *  CÒI + NÚT BOOT
 * ========================================================================== */
void keu(int lan, int ms) {
  if (CHAN_COI < 0) return;
  for (int i = 0; i < lan; i++) {
    digitalWrite(CHAN_COI, HIGH); delay(ms);
    digitalWrite(CHAN_COI, LOW);
    if (i < lan - 1) delay(ms);
  }
}

void ngheNut() {
  static bool dangBam = false;
  static uint32_t tuLuc = 0;
  bool bam = (digitalRead(CHAN_NUT) == LOW);
  if (bam && !dangBam) { dangBam = true; tuLuc = millis(); return; }
  if (!bam && dangBam) {
    dangBam = false;
    uint32_t lau = millis() - tuLuc;
    if (lau < 60) return;                               // nảy phím
    int tong = soMuc();
    if (tong == 0) { keu(4, 60); return; }
    if (lau < 1500) {                                   // bấm nhanh: sang mục kế
      g_cur = (g_cur + 1) % tong;
      veManHinh();
      keu(1, 40);
    } else {                                            // giữ: chọn/chạy mục
      kichHoat(g_cur);
    }
  }
}

/* ============================================================================
 *  BẢNG LỆNH GÕ QUA CỔNG USB (vẫn giữ — điều khiển từ máy tính khi cần)
 * ========================================================================== */
void inTro() {
  Serial.println(
    "\n===== THO NAP dsPIC — LENH GO QUA USB (115200) =====\n"
    "  TT              trang thai: the nho, file dang chon\n"
    "  DS              quet lai the, liet ke cac file .hex\n"
    "  CHON <so>       chon file thu <so> trong danh sach\n"
    "  KIEM            doc thu ca file .hex dang chon — KHONG dung toi chip\n"
    "  --- lam viec voi chip (moi chi DOC) ---\n"
    "  MACHIP          BUOC MOT: doc ma chip. Chi doc, khong hong duoc gi.\n"
    "  DOC <dc> <n>    do <n> lenh tu dia chi <dc> (hex) ra man hinh\n"
    "  SOSANH          so ban trong chip voi file .hex dang chon\n"
    "  TRO             in lai bang nay\n"
    "\n"
    "  Khong can may tinh: bam nut BOOT tren bo — bam nhanh chuyen muc tren MAN HINH,\n"
    "  giu 1.5 giay de chon file / chay lenh dang tro toi.\n"
    "====================================================\n");
}
void inTrangThai() {
  Serial.println("\n----- TRANG THAI -----");
  Serial.println("  ban firmware : " FW_VERSION);
  Serial.printf ("  the nho      : %s\n", g_coThe ? "co" : "KHONG doc duoc");
  Serial.printf ("  file .hex    : %d\n", g_soFile);
  for (int i = 0; i < g_soFile; i++)
    Serial.printf("      %s %d) %s\n", i == g_chon ? "->" : "  ", i + 1, g_ten[i].c_str());
  Serial.printf ("  chan ICSP    : MCLR=%d PGEC=%d PGED=%d\n", CHAN_MCLR, CHAN_PGEC, CHAN_PGED);
  Serial.printf ("  cho phep ghi : %s\n", icsp.choPhepGhi ? "CO" : "KHONG (co y — xem dau file .ino)");
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
    else if (lenh == "DS")   { quetThe(); veManHinh(); }
    else if (lenh == "CHON") { int k = tham.toInt();
                               if (k < 1 || k > g_soFile) Serial.println("so khong hop le — go DS de xem danh sach");
                               else { g_chon = k - 1; g_cur = k - 1; ghiKetQua("da chon " + tenNgan(g_ten[g_chon])); } }
    else if (lenh == "KIEM") { if (g_chon < 0) Serial.println("chua chon file nao");
                               else { uint32_t a,b,c2; bool ok = kiemFile(g_ten[g_chon], &a, &b, &c2);
                                      ghiKetQua(ok ? "File OK" : "File .hex LOI"); } }
    else if (lenh == "MACHIP") docMaChipManHinh();
    else if (lenh == "DOC")  { int s2 = tham.indexOf(' ');
                               uint32_t dc = (uint32_t)strtoul(tham.c_str(), nullptr, 16);
                               uint32_t n  = (s2 > 0) ? (uint32_t)tham.substring(s2 + 1).toInt() : 8;
                               if (n == 0 || n > 256) n = 8;
                               icsp.doVung(dc, n); }
    else if (lenh == "SOSANH") { if (g_chon < 0) Serial.println("chua chon file nao");
                                 else soSanh(g_ten[g_chon]); }
    else Serial.println("khong hieu \"" + lenh + "\" — go TRO de xem bang lenh");
  }
}

void setup() {
  Serial.begin(115200);
  delay(400);
  Serial.println("\n\n============ " FW_VERSION " ============");

  pinMode(CHAN_NUT, INPUT_PULLUP);
  if (CHAN_COI >= 0) { pinMode(CHAN_COI, OUTPUT); digitalWrite(CHAN_COI, LOW); }

  // ----- Màn hình: bật NGAY để khỏi cảnh "màn đen" -----
  tft.init();
  tft.setRotation(1);                    // ngang 320x240
  tft.fillScreen(C_NEN);
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(C_VANG, C_NEN);
  tft.drawString("THO NAP dsPIC", 160, 100, 4);
  tft.setTextColor(C_XAM, C_NEN);
  tft.drawString("dang khoi dong...", 160, 140, 2);
  tft.setTextDatum(TL_DATUM);

  icsp.batDau(CHAN_MCLR, CHAN_PGEC, CHAN_PGED);

  SPI.begin(18, 19, 23, SD_CS);
  g_coThe = SD.begin(SD_CS, SPI, 20000000);
  Serial.println(g_coThe ? "[SD] doc duoc the" : "[SD] KHONG doc duoc the — kiem the va chan CS");
  if (g_coThe) quetThe();

  inTro();
  Serial.println("⚠️ BUOC MOT LA  MACHIP  (nut BOOT: tro toi 'DOC MA CHIP' roi giu 1.5s).");

  ghiKetQua(g_coThe ? ("San sang — " + String(g_soFile) + " file .hex") : "Chua doc duoc the SD");
  keu(1, 120);
  veManHinh();
}

void loop() {
  ngheLenhUsb();
  ngheNut();
  delay(2);
}
