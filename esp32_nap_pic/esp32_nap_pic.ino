/* ============================================================================
 *  THỢ NẠP dsPIC — nạp firmware cho dsPIC33F từ thẻ nhớ, KHÔNG CẦN MÁY TÍNH
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
 *     nhưng CHƯA đối chiếu được từng dòng với bản gốc (máy viết không tải được
 *     tài liệu đó về). Ghi bằng chuỗi lệnh sai là hỏng bo thật, mà hỏng kiểu mất
 *     luôn đường ICSP thì PICkit cũng không cứu được.
 *     Nên thứ tự bắt buộc là:
 *         1. MACHIP   — đọc mã chip. Chỉ đọc, không hỏng được gì.
 *         2. DOC      — đọc thử flash, xem có ra số có nghĩa không.
 *         3. Hai bước trên chạy đúng rồi mới viết tiếp phần XOÁ và GHI.
 *     Đọc được mã chip là đã chứng minh CÙNG LÚC: chìa khoá vào chế độ nạp, thứ
 *     tự bit, nhịp xung, và đường dây — gần hết ẩn số nằm ở đó.
 *
 *  ─── ĐẤU DÂY ───────────────────────────────────────────────────────────────
 *    dsPIC33F chạy 3,3V y như ESP32 và vào chế độ nạp chỉ cần MCLR ở mức VDD
 *    (không cần Vpp 9-13V như PIC16/18) -> nối THẲNG, không mạch phụ nào.
 *
 *      ESP32 GPIO32 ──► MCLR   của dsPIC
 *      ESP32 GPIO33 ──► PGEC
 *      ESP32 GPIO25 ◄─► PGED   (hai chiều)
 *      GND          ───  VSS
 *      Thẻ nhớ: SPI (SCK18 / MISO19 / MOSI23), chân chọn CS = 5
 *
 *    ⚠️ ĐO VDD NGAY TẠI CHÂN CHIP trước khi cắm. dsPIC33F chịu tối đa 4,0V ở chân
 *       VDD. Bo ăn 5V thường có ổn áp 3,3V riêng cho con dsPIC — đo ở đường nguồn
 *       vào của bo thì ra 5V nhưng đó KHÔNG phải VDD của chip.
 *       Đo ngay chân VDD mà vẫn ra 5V thì con đó không phải dsPIC33F, dừng lại.
 *    ⚠️ dsPIC33F có NHIỀU cặp PGEC/PGED (1, 2, 3). Phải dùng đúng cặp bo đi dây ra.
 * ========================================================================== */
#include <SD.h>
#include <SPI.h>
#include "doc_hex.h"
#include "icsp_dspic.h"

#define FW_VERSION "nap-pic 2026-08-25a (dsPIC33F — moi chi DOC, chua GHI)"

/* ---- CHÂN CẮM ----
   Dùng lại khối chân dễ đếm D32 D33 D25 D26 D27 ở hàng trái DevKit, và chân thẻ
   nhớ y như con thợ nạp máy chấm công (SCK18/MISO19/MOSI23, CS=5). */
#define CHAN_MCLR   32
#define CHAN_PGEC   33
#define CHAN_PGED   25
#define CHAN_NUT     0      // nút BOOT sẵn trên bo
#define CHAN_COI    22
#define CHAN_DEN    21
#define SD_CS        5

#define MAX_FILE    16      // số file .hex nhớ được trong danh sách

IcspDsPic icsp;
String   g_ten[MAX_FILE];
int      g_soFile = 0;
int      g_chon   = -1;
bool     g_coThe  = false;

/* ============================================================================
 *  THẺ NHỚ — liệt kê các file .hex
 * ========================================================================== */
void quetThe() {
  g_soFile = 0; g_chon = -1;
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
 *  Bước này đáng làm trước mọi thứ: file hỏng mà phát hiện lúc đang ghi dở chip
 *  thì để lại một con chip nạp nửa vời, tệ hơn nhiều so với biết ngay từ đầu.
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
    Serial.println("[HEX] ⚠️ file CO ghi vung cau hinh (0xF80000+). Ghi sai FICD la MAT LUON duong ICSP,\n"
                   "      sau do PICkit cung khong vao duoc. Vung nay se ghi SAU CUNG, va chi khi da\n"
                   "      doc nguoc kiem tra xong phan chuong trinh.");
  return true;
}

/* ============================================================================
 *  SO BẢN TRONG CHIP VỚI FILE — chỉ đọc, dùng được ngay, không rủi ro gì
 * ========================================================================== */
void soSanh(const String& duong) {
  File f = SD.open(duong.c_str(), FILE_READ);
  if (!f) { Serial.println("[SS] khong mo duoc " + duong); return; }
  if (!icsp.moPhien()) { f.close(); Serial.println("[SS] khong vao duoc che do nap"); return; }

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
  Serial.printf("[SS] khop %lu, lech %lu%s\n", (unsigned long)soKhop, (unsigned long)soLech,
                soLech >= 20 ? " (dung sau 20 cho lech dau tien)" : "");
  if (soLech == 0 && soKhop > 0)
    Serial.println("[SS] ✅ chip dang chay DUNG ban nay.");
  else if (soKhop == 0)
    Serial.println("[SS] ⚠️ khong khop cho nao ca. Rat co the tang ICSP chua chay dung —\n"
                   "     chay MACHIP truoc, doc ra dung ten chip roi hay tin ket qua o day.");
}

/* ============================================================================
 *  CHỌN FIRMWARE BẰNG NÚT — không cần máy tính
 *    bấm nhanh   = sang file kế tiếp, còi kêu N tiếng = đang chọn file thứ N
 *    giữ 2 giây  = xác nhận, đọc thử file đó
 * ========================================================================== */
void keu(int lan, int ms) {
  for (int i = 0; i < lan; i++) {
    digitalWrite(CHAN_COI, HIGH); digitalWrite(CHAN_DEN, HIGH); delay(ms);
    digitalWrite(CHAN_COI, LOW);  digitalWrite(CHAN_DEN, LOW);
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
    if (g_soFile == 0) { Serial.println("[NUT] the khong co file .hex nao"); keu(4, 60); return; }
    if (lau < 1500) {
      g_chon = (g_chon + 1) % g_soFile;
      Serial.printf("[NUT] chon %d) %s\n", g_chon + 1, g_ten[g_chon].c_str());
      keu(g_chon + 1, 120);
    } else {
      Serial.println("[NUT] kiem file " + g_ten[g_chon]);
      uint32_t st, dc1, dc2;
      keu(kiemFile(g_ten[g_chon], &st, &dc1, &dc2) ? 1 : 5, 200);
    }
  }
}

/* ============================================================================
 *  BẢNG LỆNH GÕ QUA CỔNG USB
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
    "  Chon file khong can may tinh: bam nut BOOT — bam nhanh sang file ke tiep\n"
    "  (coi keu N tieng = file thu N), giu 2 giay de kiem file do.\n"
    "====================================================\n");
}
void inTrangThai() {
  Serial.println("\n----- TRANG THAI -----");
  Serial.println("  ban firmware : " FW_VERSION);
  Serial.printf ("  the nho      : %s\n", g_coThe ? "co" : "KHONG doc duoc");
  Serial.printf ("  file .hex    : %d\n", g_soFile);
  for (int i = 0; i < g_soFile; i++)
    Serial.printf("      %s %d) %s\n", i == g_chon ? "->" : "  ", i + 1, g_ten[i].c_str());
  Serial.printf ("  chan         : MCLR=%d PGEC=%d PGED=%d\n", CHAN_MCLR, CHAN_PGEC, CHAN_PGED);
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
    else if (lenh == "DS")   quetThe();
    else if (lenh == "CHON") { int k = tham.toInt();
                               if (k < 1 || k > g_soFile) Serial.println("so khong hop le — go DS de xem danh sach");
                               else { g_chon = k - 1; Serial.println("da chon " + g_ten[g_chon]); } }
    else if (lenh == "KIEM") { if (g_chon < 0) Serial.println("chua chon file nao");
                               else { uint32_t a,b,c2; kiemFile(g_ten[g_chon], &a, &b, &c2); } }
    else if (lenh == "MACHIP") icsp.docMaChip();
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
  pinMode(CHAN_COI, OUTPUT); digitalWrite(CHAN_COI, LOW);
  pinMode(CHAN_DEN, OUTPUT); digitalWrite(CHAN_DEN, LOW);

  icsp.batDau(CHAN_MCLR, CHAN_PGEC, CHAN_PGED);

  SPI.begin(18, 19, 23, SD_CS);
  g_coThe = SD.begin(SD_CS, SPI, 20000000);
  Serial.println(g_coThe ? "[SD] doc duoc the" : "[SD] KHONG doc duoc the — kiem the va chan CS");
  if (g_coThe) quetThe();

  inTro();
  Serial.println("⚠️ BUOC MOT LA GO  MACHIP  — doc ma chip, chi doc, khong hong duoc gi.");
  Serial.println("   Doc ra dung ten chip = tang ICSP da chay dung. Chua doc duoc thi\n"
                 "   dung nghi toi chuyen ghi.\n");
  keu(1, 120);
}

void loop() {
  ngheLenhUsb();
  ngheNut();
  delay(2);
}
