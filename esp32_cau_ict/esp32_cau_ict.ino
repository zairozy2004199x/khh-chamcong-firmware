/* ============================================================================
 *  CẦU NGHE LÉN  ICT L70  ⇄  GHẾ MASSAGE      (firmware MẠCH TẠM, để học giao thức)
 * ----------------------------------------------------------------------------
 *  ESP32 ngồi GIỮA đường dây có sẵn, cho hai bên nói chuyện qua mình y như cũ, và
 *  chép lại từng byte của cả hai chiều:
 *
 *      Ghế  ⇄  TXS0108E  ⇄  [ ESP32 ]  ⇄  TXS0108E  ⇄  ICT L70
 *                             │
 *                          cáp USB → máy tính (Serial Monitor 115200)
 *
 *  VÌ SAO LÀM KIỂU NÀY THAY VÌ ĐOÁN KHUNG LỆNH
 *    Đầu bán tiền ICT L70 và bo ghế đã nói chuyện với nhau đúng giao thức của
 *    chúng suốt bao lâu nay. Ngồi đoán khung lệnh là tự nghĩ ra một thứ rồi mong
 *    nó trùng. Còn ngồi giữa mà chép thì trong 5 phút có ĐÚNG cái mà bo ghế chịu
 *    nghe — kể cả checksum, kể cả nhịp hỏi đáp, kể cả những byte không ai ngờ tới.
 *    Sau này thay hẳn L70 thì chỉ việc phát lại đúng cái nó vẫn phát.
 *
 *  ⚠️ FIRMWARE NÀY KHÔNG TỰ Ý CHEN GÌ VÀO ĐƯỜNG DÂY. Mặc định nó chỉ chuyển tiếp
 *     và ghi lại. Muốn bắn thử thì phải gõ lệnh BANI/BANG. Cố ý như vậy: chen byte
 *     lạ vào lúc L70 và bo ghế đang giữa chừng một lượt là làm hỏng đúng cái mình
 *     đang muốn quan sát.
 *
 *  ─── ĐẤU DÂY ───────────────────────────────────────────────────────────────
 *    Phía ICT L70            Phía GHẾ
 *    ESP32 GPIO26 ← TX L70   ESP32 GPIO16 ← TX ghế     (qua TXS0108E, phía LV)
 *    ESP32 GPIO27 → RX L70   ESP32 GPIO17 → RX ghế
 *    GND chung HẾT: ESP32, cả hai mạch TXS0108E, L70, bo ghế.
 *
 *  ⚠️ TXS0108E — CHÂN OE. Đây là chỗ vấp kinh điển, và nó IM LẶNG hoàn toàn.
 *     Chân OE của TXS0108E có điện trở kéo XUỐNG bên trong. Để hở = OE mức thấp =
 *     CẢ MẠCH TẮT, mọi đầu ra thả nổi. Nhìn thì thấy dây cắm đủ, đèn nguồn sáng,
 *     mà không byte nào qua được, và chẳng có dấu hiệu gì báo tại sao.
 *        -> PHẢI nối OE lên VCCA (3,3V). Nối thẳng bằng dây cũng được, hoặc khai
 *           CHAN_OE_* bên dưới để ESP32 tự kéo lên lúc khởi động.
 *     Ngoài ra TXS0108E cần ĐỦ CẢ HAI nguồn: VCCA = 3,3V, VCCB = 5V, và VCCA phải
 *     nhỏ hơn hoặc bằng VCCB.
 *
 *  ⚠️ TXS0108E vốn sinh ra cho I2C (kiểu hở cực máng). Ghép với UART đẩy đối xứng
 *     thì phần lớn trường hợp chạy, nhưng nó có mạch "một phát" bên trong nên đôi
 *     khi dở chứng: bit dính, rác lác đác, hoặc tự dao động — nhất là baud cao hay
 *     dây dài. Gặp mấy triệu chứng đó thì ĐỪNG đi sửa phần mềm: hạ baud xuống trước,
 *     vẫn không đỡ thì đổi sang TXB0104 (đẩy đối xứng) hoặc SN74LVC2T45 (cố định
 *     chiều, chuẩn nhất cho UART).
 *
 *  ─── DÙNG ───────────────────────────────────────────────────────────────────
 *    Cắm USB, mở Serial Monitor 115200, gõ  TRO  để xem bảng lệnh.
 *    Chưa biết baud thì gõ  DOBAUD  rồi bỏ tờ tiền vào L70 cho có tín hiệu chạy.
 * ========================================================================== */

#define FW_VERSION "cau-ict 2026-08-25a (nghe len 2 chieu ICT L70 <-> ghe)"

/* ---- CHÂN CẮM ---- */
#define CHAN_ICT_RX   26      // ESP32 nhận  <- TX của ICT L70
#define CHAN_ICT_TX   27      // ESP32 gửi   -> RX của ICT L70
#define CHAN_GHE_RX   16      // ESP32 nhận  <- TX của bo ghế
#define CHAN_GHE_TX   17      // ESP32 gửi   -> RX của bo ghế
#define CHAN_OE_ICT   -1      // -> OE của TXS0108E phía L70; -1 = đã nối cứng lên 3,3V
#define CHAN_OE_GHE   -1      // -> OE của TXS0108E phía ghế; -1 = đã nối cứng lên 3,3V

#define BAUD_MAC_DINH 9600
#define NGHI_MS       15      // dây im ngần này = hết một khung (ở 9600 một byte ~1ms)
#define KHUNG_TOI_DA  96
#define HANG_DOI      16      // nhớ tối đa ngần này khung chờ in ra

HardwareSerial& congIct = Serial1;
HardwareSerial& congGhe = Serial2;

/* ============================================================================
 *  GHI NHẬT KÝ — KHÔNG ĐƯỢC LÀM CHẬM VIỆC CHUYỂN TIẾP
 *  In ra cổng USB tốn thời gian. In ngay giữa lúc đang chuyển byte là làm méo nhịp
 *  của chính cái mình đang đo — đo cái gì cũng ra sai. Nên: chuyển byte trước, gom
 *  khung vào RAM, lúc nào cả hai dây cùng im mới in.
 * ========================================================================== */
struct Khung {
  uint8_t  d[KHUNG_TOI_DA];
  uint8_t  n    = 0;
  bool     tuIct = true;      // true = L70 nói, false = ghế nói
  uint32_t luc  = 0;          // millis lúc chốt khung
  bool     tran = false;
};
Khung   hang[HANG_DOI];
uint8_t hGhi = 0, hDoc = 0, hSo = 0;

struct GomKhung {
  uint8_t  d[KHUNG_TOI_DA];
  uint8_t  n = 0;
  bool     tran = false;
  uint32_t cuoiMs = 0;
};
GomKhung gomIct, gomGhe;

long     g_baud      = BAUD_MAC_DINH;
bool     g_noiCau    = true;    // false = cắt cầu, không chuyển tiếp nữa
bool     g_inChu     = true;    // in kèm cột chữ
uint32_t g_soIct = 0, g_soGhe = 0, g_khungIct = 0, g_khungGhe = 0;
uint32_t g_batDauMs = 0;

/* Khai báo trước: Arduino tự sinh mấy dòng này, nhưng g++ ở bước biên dịch kiểm
   (ci/kiem-bien-dich.sh) thì không — mà thiếu nó là hàm nào gọi ngược lên trên đều đỏ. */
void moCong();
void chayCau();
void inHangDoi();

void xepKhung(GomKhung& g, bool tuIct) {
  if (g.n == 0) return;
  Khung& k = hang[hGhi];
  k.n = g.n; k.tuIct = tuIct; k.luc = millis(); k.tran = g.tran;
  memcpy(k.d, g.d, g.n);
  hGhi = (uint8_t)((hGhi + 1) % HANG_DOI);
  if (hSo < HANG_DOI) hSo++;
  else hDoc = hGhi;                       // hàng đầy: khung cũ nhất rơi ra
  if (tuIct) g_khungIct++; else g_khungGhe++;
  g.n = 0; g.tran = false;
}

void inKhung(const Khung& k) {
  char dau[40];
  snprintf(dau, sizeof(dau), "%8lu %s %2u ",
           (unsigned long)(k.luc - g_batDauMs),
           k.tuIct ? "L70 ->GHE" : "GHE ->L70", k.n);
  Serial.print(dau);
  String hex, chu;
  for (uint8_t i = 0; i < k.n; i++) {
    char b[4]; snprintf(b, sizeof(b), "%02X ", k.d[i]); hex += b;
    chu += (k.d[i] >= 0x20 && k.d[i] <= 0x7E) ? (char)k.d[i] : '.';
  }
  Serial.print(hex);
  if (g_inChu) Serial.print(" |" + chu + "|");
  if (k.tran)  Serial.print("  (DAI QUA CO, da cat)");
  Serial.println();
}

/* ============================================================================
 *  DÒ BAUD BẰNG CÁCH ĐO BỀ RỘNG XUNG
 * ----------------------------------------------------------------------------
 *  Cách này ĐO chứ không đoán: xung hẹp nhất trên đường UART chính là MỘT bit, nên
 *  baud = 1 / (bề rộng xung hẹp nhất). Đo vài trăm xung rồi lấy cái hẹp nhất.
 *
 *  Hơn hẳn kiểu "thử từng baud rồi chấm điểm cái nhận được": kiểu đó sai baud vẫn
 *  ra byte, chỉ là byte rác, và rác thì đôi khi trông giống dữ liệu thật — nhất là
 *  khi lệch gấp đôi (9600 với 19200). Còn đo xung thì ra thẳng con số.
 *
 *  ⚠️ CHỈ ĐO ĐƯỢC KHI DÂY ĐANG CÓ TÍN HIỆU CHẠY. Bỏ tờ tiền vào L70, hoặc bấm nút
 *     trên ghế, trong lúc đang đo. Dây im thì hàm này không có gì để đo.
 *  ⚠️ Đo trên chân phía LV của TXS0108E (tức chân ESP32), không phải phía 5V.
 * ========================================================================== */
long doBaudTheoXung(int chan, const char* ten, uint32_t giay) {
  Serial.printf("[DOBAUD] nghe chan %s (GPIO %d) trong %lu giay — LAM CHO DAY CO TIN HIEU\n"
                "         (bo to tien vao L70, hoac bam nut tren ghe) ...\n",
                ten, chan, (unsigned long)giay);
  congIct.end(); congGhe.end();          // trả chân về GPIO thường để đo
  pinMode(chan, INPUT);

  uint32_t hepNhat = 0xFFFFFFFF;
  uint32_t soXung = 0;
  uint32_t het = millis() + giay * 1000UL;
  while ((int32_t)(het - millis()) > 0) {
    uint32_t w = pulseIn(chan, LOW, 20000);        // bit 0 kéo dây xuống thấp
    if (w >= 3 && w < 20000) { soXung++; if (w < hepNhat) hepNhat = w; }
  }
  moCong();                                        // trả chân lại cho UART

  if (soXung < 20) {
    Serial.printf("[DOBAUD] chi bat duoc %lu xung — khong du de ket luan.\n", (unsigned long)soXung);
    Serial.println("         Day co dang chay khong? Kiem: OE cua TXS0108E da keo len 3,3V chua,\n"
                   "         GND da chung het chua, va co that su lam cho hai ben noi chuyen chua.");
    return 0;
  }
  long baudDo = (long)(1000000UL / hepNhat);
  static const long CHUAN[] = { 1200, 2400, 4800, 9600, 19200, 38400, 57600, 115200 };
  long gan = CHUAN[0]; long lech = labs(baudDo - CHUAN[0]);
  for (unsigned i = 1; i < sizeof(CHUAN) / sizeof(CHUAN[0]); i++) {
    long l = labs(baudDo - CHUAN[i]);
    if (l < lech) { lech = l; gan = CHUAN[i]; }
  }
  Serial.printf("[DOBAUD] %lu xung, hep nhat %lu us -> ~%ld baud -> gan nhat la %ld\n",
                (unsigned long)soXung, (unsigned long)hepNhat, baudDo, gan);
  if (lech * 100 / gan > 12)
    Serial.println("         ⚠️ lech xa moc chuan qua. Co the day bi nhieu, hoac TXS0108E dang bo tron\n"
                   "            suon xung. Do lai vai lan; van lech thi ha baud hoac doi mach chuyen muc.");
  else
    Serial.printf("         -> go 'BAUD %ld' de dat cho ca hai ben.\n", gan);
  return gan;
}

void moCong() {
  congIct.end(); congGhe.end();
  delay(20);
  congIct.begin(g_baud, SERIAL_8N1, CHAN_ICT_RX, CHAN_ICT_TX);
  congGhe.begin(g_baud, SERIAL_8N1, CHAN_GHE_RX, CHAN_GHE_TX);
  gomIct.n = 0; gomGhe.n = 0;
}

/* ============================================================================
 *  BẢNG LỆNH
 * ========================================================================== */
void inTro() {
  Serial.println(
    "\n===== CAU NGHE LEN ICT L70 <-> GHE — LENH GO QUA USB (115200) =====\n"
    "  TT              trang thai: baud, dem byte/khung tung chieu\n"
    "  DOBAUD [giay]   DO baud bang be rong xung (mac dinh 5 giay).\n"
    "                  Phai lam cho day co tin hieu trong luc do!\n"
    "  BAUD <so>       dat baud cho CA HAI ben (hai ben cua mot duong day, phai bang nhau)\n"
    "  CAT             CAT cau — ngung chuyen tiep, hai ben khong nghe nhau nua\n"
    "  NOI             NOI lai cau\n"
    "  BANI <hex>      ban chuoi hex sang phia ICT L70\n"
    "  BANG <hex>      ban chuoi hex sang phia GHE  <-- day la cai de gia lam L70\n"
    "  CHU 0|1         tat/bat cot chu ben canh hex\n"
    "  XOA             xoa bo dem\n"
    "  QUYTRINH        chay tuan tu cac buoc nghiem thu, bao hong o dau\n"
    "  TRO             in lai bang nay\n"
    "===================================================================\n");
}
void inTrangThai() {
  Serial.println("\n----- TRANG THAI -----");
  Serial.println("  ban firmware : " FW_VERSION);
  Serial.printf ("  baud         : %ld (ca hai ben)\n", g_baud);
  Serial.printf ("  cau          : %s\n", g_noiCau ? "DANG NOI (hai ben nghe nhau)" : "DA CAT");
  Serial.printf ("  L70  -> ghe  : %lu byte, %lu khung\n", (unsigned long)g_soIct, (unsigned long)g_khungIct);
  Serial.printf ("  ghe  -> L70  : %lu byte, %lu khung\n", (unsigned long)g_soGhe, (unsigned long)g_khungGhe);
  if (g_soIct == 0 && g_soGhe == 0)
    Serial.println("  ⚠️ CHUA NHAN DUOC BYTE NAO tu ca hai ben. Xem buoc 1-3 cua QUYTRINH.");
  else if (g_soIct == 0)
    Serial.println("  ⚠️ Phia L70 im. Kiem TX cua L70 co vao GPIO26 khong, va OE cua mach ben do.");
  else if (g_soGhe == 0)
    Serial.println("  ⚠️ Phia ghe im. Binh thuong neu bo ghe chi NGHE ma khong noi lai —\n"
                   "     dung the thi thay hen dut day: xem ghi chu ve chan DIR cua HT245.");
  Serial.println("----------------------\n");
}

int docHex(const String& s, uint8_t* ra, int toiDa) {
  int n = 0, i = 0, dai = (int)s.length();
  while (i < dai && n < toiDa) {
    if (!isHexadecimalDigit(s[i]))                     { i++; continue; }
    if (i + 1 >= dai || !isHexadecimalDigit(s[i + 1])) { i++; continue; }  // mẩu lẻ: bỏ
    auto nib = [](char c) -> uint8_t {
      if (c >= '0' && c <= '9') return (uint8_t)(c - '0');
      if (c >= 'a' && c <= 'f') return (uint8_t)(c - 'a' + 10);
      if (c >= 'A' && c <= 'F') return (uint8_t)(c - 'A' + 10);
      return 0;
    };
    ra[n++] = (uint8_t)((nib(s[i]) << 4) | nib(s[i + 1]));
    i += 2;
  }
  return n;
}
void ban(HardwareSerial& cong, const String& hex, const char* phia) {
  uint8_t d[KHUNG_TOI_DA];
  int n = docHex(hex, d, KHUNG_TOI_DA);
  if (n == 0) { Serial.println("[BAN] khong doc duoc byte hex nao"); return; }
  cong.write(d, n); cong.flush();
  Serial.printf("[BAN] -> %s: ", phia);
  for (int i = 0; i < n; i++) Serial.printf("%02X ", d[i]);
  Serial.println();
}

void quyTrinh() {
  Serial.println("\n========== QUY TRINH NGHIEM THU CAU NGHE LEN ==========");
  Serial.println("BUOC 1 — KIEM TAY, firmware khong tu lam duoc:");
  Serial.println("   [ ] OE cua CA HAI mach TXS0108E da keo len 3,3V (de ho = ca mach TAT, im lang hoan toan)");
  Serial.println("   [ ] Ca hai mach du CA HAI nguon: VCCA = 3,3V, VCCB = 5V");
  Serial.println("   [ ] GND chung het: ESP32 + 2 mach + L70 + bo ghe");
  Serial.println("   [ ] TX cua ben kia vao RX cua minh (CHEO), khong phai TX-TX");
  Serial.println("   [ ] Dung so kenh: A1 thong voi B1, A2 voi B2");
  Serial.println("   Xong het 5 o thi go tiep QUYTRINH.\n");

  Serial.println("BUOC 2 — do baud bang be rong xung. LAM CHO DAY CO TIN HIEU NGAY BAY GIO:");
  long b = doBaudTheoXung(CHAN_ICT_RX, "phia L70", 5);
  if (b == 0) {
    Serial.println("\n❌ DUNG O BUOC 2. Khong co tin hieu tren day phia L70.");
    Serial.println("   Gan nhu chac chan la phan cung — quay lai lam ky BUOC 1,");
    Serial.println("   dac biet la chan OE. Do xong lam lai QUYTRINH.");
    return;
  }
  if (b != g_baud) { g_baud = b; moCong(); Serial.printf("   -> da dat baud = %ld\n", g_baud); }

  Serial.println("\nBUOC 3 — nghe that 8 giay. Lam cho hai ben noi chuyen (bo tien vao L70):");
  uint32_t i0 = g_soIct, g0 = g_soGhe;
  uint32_t het = millis() + 8000;
  while ((int32_t)(het - millis()) > 0) { chayCau(); inHangDoi(); delay(1); }
  uint32_t dIct = g_soIct - i0, dGhe = g_soGhe - g0;

  Serial.printf("\n----- KET QUA: L70 gui %lu byte, ghe gui %lu byte -----\n",
                (unsigned long)dIct, (unsigned long)dGhe);
  if (dIct == 0 && dGhe == 0) {
    Serial.println("❌ Khong ben nao noi gi. Baud do duoc o buoc 2 nhung khong doc ra byte:");
    Serial.println("   TXS0108E dang bo tron suon xung? Ha baud xuong thu (BAUD 4800),");
    Serial.println("   van vay thi doi sang TXB0104 hoac SN74LVC2T45.");
  } else if (dGhe == 0) {
    Serial.println("⚠️ Chi co L70 noi, ghe im. Hai kha nang:");
    Serial.println("   - bo ghe von chi NGHE, khong tra loi (rat co the — con HT245 chi co MOT chan");
    Serial.println("     DIR cho ca 8 kenh nen hai duong qua no bi ep cung mot chieu);");
    Serial.println("   - hoac duong ve chua dau dung. Kiem TX cua ghe -> GPIO16.");
    Serial.println("   Neu la kha nang dau thi VAN LAM DUOC: chi can phat lai dung cai L70 phat.");
  } else if (dIct == 0) {
    Serial.println("⚠️ Chi co ghe noi, L70 im. Kiem TX cua L70 -> GPIO26, va OE mach phia do.");
  } else {
    Serial.println("✅ CA HAI CHIEU DEU CHAY. Day la cai can nhat: gio cu bo tien vao L70 vai lan,");
    Serial.println("   chep lai khung hai ben trao doi, roi phat lai dung nhu vay la mo duoc ghe.");
    Serial.println("   Meo: bo tien MENH GIA KHAC NHAU de xem byte nao la so tien.");
  }
  Serial.println("=======================================================\n");
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
    else if (lenh == "DOBAUD") { uint32_t g = tham.toInt(); doBaudTheoXung(CHAN_ICT_RX, "phia L70", g ? g : 5); }
    else if (lenh == "BAUD") { long b = tham.toInt();
                               if (b < 300 || b > 921600) Serial.println("baud khong hop ly");
                               else { g_baud = b; moCong(); Serial.printf("[BAUD] ca hai ben = %ld\n", g_baud); } }
    else if (lenh == "CAT")  { g_noiCau = false; Serial.println("[CAU] DA CAT — hai ben khong nghe nhau nua"); }
    else if (lenh == "NOI")  { g_noiCau = true;  Serial.println("[CAU] da noi lai"); }
    else if (lenh == "BANI") ban(congIct, tham, "ICT L70");
    else if (lenh == "BANG") ban(congGhe, tham, "GHE");
    else if (lenh == "CHU")  { g_inChu = (tham.toInt() != 0); Serial.printf("[CHU] cot chu %s\n", g_inChu ? "BAT" : "TAT"); }
    else if (lenh == "XOA")  { g_soIct = g_soGhe = g_khungIct = g_khungGhe = 0; hSo = 0; hDoc = hGhi;
                               g_batDauMs = millis(); Serial.println("[XOA] da xoa bo dem"); }
    else if (lenh == "QUYTRINH") quyTrinh();
    else Serial.println("khong hieu \"" + lenh + "\" — go TRO de xem bang lenh");
  }
}

/* ============================================================================
 *  VIỆC CHÍNH: CHUYỂN TIẾP + GOM KHUNG
 *  ⚠️ Chuyển byte sang bên kia NGAY, trước khi làm bất cứ việc gì khác. Mọi thứ
 *     xen vào giữa (in ra, đếm, so sánh) đều cộng thêm độ trễ cho đường dây thật.
 * ========================================================================== */
void chuyen(HardwareSerial& tu, HardwareSerial& sang, GomKhung& gom, bool tuIct, uint32_t& dem) {
  while (tu.available()) {
    uint8_t b = (uint8_t)tu.read();
    if (g_noiCau) sang.write(b);                 // CHUYỂN TRƯỚC — mọi thứ khác tính sau
    dem++;
    if (gom.n < KHUNG_TOI_DA) gom.d[gom.n++] = b;
    else                      gom.tran = true;
    gom.cuoiMs = millis();
  }
  if (gom.n > 0 && (uint32_t)(millis() - gom.cuoiMs) >= NGHI_MS) xepKhung(gom, tuIct);
}
void chayCau() {
  chuyen(congIct, congGhe, gomIct, true,  g_soIct);
  chuyen(congGhe, congIct, gomGhe, false, g_soGhe);
}
void inHangDoi() {
  // Chỉ in khi CẢ HAI dây đang im — in lúc đang có byte chạy là làm méo nhịp.
  if (hSo == 0) return;
  if (congIct.available() || congGhe.available()) return;
  inKhung(hang[hDoc]);
  hDoc = (uint8_t)((hDoc + 1) % HANG_DOI);
  hSo--;
}

void setup() {
  Serial.begin(115200);
  delay(400);
  Serial.println("\n\n============ " FW_VERSION " ============");

  if (CHAN_OE_ICT >= 0) { pinMode(CHAN_OE_ICT, OUTPUT); digitalWrite(CHAN_OE_ICT, HIGH); }
  if (CHAN_OE_GHE >= 0) { pinMode(CHAN_OE_GHE, OUTPUT); digitalWrite(CHAN_OE_GHE, HIGH); }
  if (CHAN_OE_ICT < 0 && CHAN_OE_GHE < 0)
    Serial.println("[OE] Khong khai chan OE -> hai mach TXS0108E phai duoc NOI CUNG OE len 3,3V.\n"
                   "     De ho la ca mach TAT, khong byte nao qua duoc, va khong bao loi gi ca.");

  moCong();
  g_batDauMs = millis();
  Serial.printf("[CAU] L70 @ GPIO%d/%d — GHE @ GPIO%d/%d — %ld baud\n",
                CHAN_ICT_RX, CHAN_ICT_TX, CHAN_GHE_RX, CHAN_GHE_TX, g_baud);
  Serial.println("[CAU] dang chuyen tiep hai chieu. Cot dau la mili giay ke tu luc bat may.");
  inTro();
}

void loop() {
  chayCau();          // việc chính, luôn chạy trước
  inHangDoi();
  ngheLenhUsb();
}
