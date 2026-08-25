/* ============================================================================
 *  CẦU NGHE LÉN  ICT L70  ⇄  GHẾ MASSAGE      (firmware MẠCH TẠM, để học giao thức)
 * ----------------------------------------------------------------------------
 *  ESP32 ngồi GIỮA đường dây có sẵn, cho hai bên nói chuyện qua mình y như cũ, và
 *  chép lại từng byte của cả hai chiều:
 *
 *      Ghế  ⇄  ADuM1201  ⇄  [ ESP32 ]  ⇄  ADuM1201  ⇄  ICT L70
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
 *  ─── ĐẤU DÂY QUA ADuM1201 (cách nên dùng) ─────────────────────────────────
 *    ADuM1201 là bộ CÁCH LY SỐ hai kênh, một kênh mỗi chiều — vừa khít một cặp
 *    UART. Hai bên có nguồn riêng: VDD1 = 3,3V (phía ESP32), VDD2 = 5V (phía máy).
 *    Tín hiệu ra bên nào thì đánh đúng mức của bên đó.
 *
 *    ⚠️ PHẢI LÀ ADuM1201, ĐỪNG LẤY NHẦM ADuM1200. Nhìn giống hệt nhau, cùng vỏ
 *       8 chân, tên lệch một số. ADuM1200 hai kênh CÙNG chiều — UART không chạy
 *       được, mà cắm vào thì cũng chẳng cháy gì, chỉ là một chiều im lặng mãi.
 *       Cần 4 đường (2 mỗi bên) nên phải HAI con 1201. Hoặc một con ADuM1402
 *       (4 kênh, 2 mỗi chiều) là gọn hết cả hai bên trong một chip.
 *
 *    Mỗi con 1201, theo TÊN CHÂN (đối chiếu datasheet cho chắc số thứ tự):
 *
 *        phía 1 (3,3V, nối ESP32)          phía 2 (5V, nối thiết bị)
 *        VDD1 ← 3V3 của ESP32              VDD2 ← 5V của máy
 *        GND1 ← GND của ESP32              GND2 ← GND của máy
 *        VIA  ← ESP32 gửi ra   ───kênh A──► VOA  → RX của thiết bị
 *        VOB  → ESP32 nhận vào ◄──kênh B─── VIB  ← TX của thiết bị
 *
 *    Con ① (phía L70):  VIA ← GPIO26 → VOA → RX của L70
 *                       VOB → GPIO25 ← VIB ← TX của L70
 *    Con ② (phía ghế):  VIA ← GPIO33 → VOA → RX của ghế
 *                       VOB → GPIO32 ← VIB ← TX của ghế
 *
 *  ⚠️⚠️ HAI NÉT MASS, VÀ CHÚNG KHÔNG ĐƯỢC CHẠM NHAU.
 *     Đây là chỗ NGƯỢC HẲN với cách đấu qua mạch chuyển mức thường, nên đọc kỹ:
 *
 *        nét mass 1:  GND1 của cả hai con  +  GND của ESP32
 *        nét mass 2:  GND2 của cả hai con  +  GND của L70  +  GND của bo ghế
 *
 *     Nối hai nét đó lại với nhau thì mạch VẪN CHẠY — nên không ai phát hiện ra —
 *     chỉ là đã vứt sạch phần cách ly vừa bỏ tiền mua. Ghế massage có mô-tơ, mà
 *     mô-tơ thì đá nhiễu ngược về đường nguồn; cách ly là thứ chặn đường đó.
 *
 *     Kéo theo: ESP32 phải ăn nguồn RIÊNG, không lấy 5V của máy. Lúc test thì cắm
 *     USB vào laptop là tự nhiên đã tách rồi. Làm bo thật thì cần nguồn cách ly.
 *
 *  ─── VÌ SAO ADuM1201 HƠN TXS0108E Ở ĐÂY ───────────────────────────────────
 *    • CHIỀU CỐ ĐỊNH cho từng kênh. TXS0108E tự đoán chiều — nó sinh ra cho I2C,
 *      ghép với UART đẩy đối xứng thì hay dở chứng: bit dính, rác lác đác, có khi
 *      tự dao động. ADuM không đoán gì cả nên không có cái class lỗi đó.
 *    • KHÔNG CÓ CHÂN OE để mà quên. TXS0108E có trở kéo OE xuống bên trong, để hở
 *      là cả mạch tắt mà không báo gì — cái bẫy tốn buổi.
 *    • ĐẨY ĐỐI XỨNG, chạy tới hàng Mbps. Bỏ được trần baud 38400 của loại dùng trở
 *      kéo 10k. 115200 thoải mái.
 *    • Ra 5V ĐÚNG MỨC ở phía 2, nên câu "con HT245 trên bo ghế là HC hay HCT" thành
 *      không cần trả lời nữa: 5V vượt xa ngưỡng 3,5V của cả loại khó tính nhất.
 *
 *  ⚠️ TỤ LỌC. Datasheet đòi 0,1µF sát chân VDD1 và VDD2 của TỪNG con, thêm 10µF
 *     làm tụ đệm. Thiếu tụ thì chip chạy chập chờn và bắn nhiễu ra ngoài — mà lỗi
 *     đó trông y hệt lỗi sai baud, ngồi mò rất lâu mới ra.
 *
 *  ─── NẾU VẪN DÙNG TXS0108E ────────────────────────────────────────────────
 *    Nối A ← phía ESP32, B ← phía thiết bị, VCCA = 3,3V, VCCB = 5V, kênh khớp số
 *    (A1 chỉ thông với B1), và MASS CHUNG HẾT (loại này không cách ly).
 *    ⚠️ Chân OE có trở kéo xuống bên trong: để hở = cả mạch TẮT, không byte nào
 *       qua, không dấu hiệu gì. Phải nối OE lên VCCA (3,3V) ở cả hai mạch.
 *    ⚠️ Giữ baud ≤ 38400. Ra rác hay bit dính thì ĐỪNG sửa phần mềm — hạ baud
 *       trước, vẫn vậy thì đổi sang ADuM1201 (hoặc TXB0104 / SN74LVC2T45).
 *
*  ─── TÀI LIỆU L-SERIES CHO BIẾT (đọc từ Installation Guide) ──────────────────
 *  L70 có nhiều kiểu đầu ra, hai kiểu hay gặp:
 *    • Pulse: đầu ra tiền là CẶP TIẾP ĐIỂM KHÔ cách ly quang (Credit_Relay_NO/COM),
 *      khách kéo lên qua 4K7. Mỗi tờ = vài nhịp đóng tiếp điểm. KHÔNG có byte.
 *    • RS232/ccNet: nhãn ghi "RS232" nhưng thực chất là TTL 5V (không có ±12V thật).
 *      Có TXD/RXD. ⚠️ chú thích "Hi->TR ON, Lo->TR OFF" nghĩa là đường khách->L70
 *      đi qua transistor hở cực góp ĐẢO tín hiệu — dễ làm tưởng gửi mà bo không nhận.
 *  TTL 5V này khớp với con HT245 5V đo được trên bo.
 *  Vì chưa biết bo đang chạy kiểu nào, lệnh XUNG (đo bề rộng cạnh) phân biệt trước:
 *  hẹp cỡ micro giây = UART; rộng cỡ chục mili giây = xung tiền, khỏi cần khung lệnh.
 *
 *  ─── DÙNG ───────────────────────────────────────────────────────────────────
 *    Cắm USB, mở Serial Monitor 115200, gõ  TRO  để xem bảng lệnh.
 *    Chưa biết baud thì gõ  DOBAUD  rồi bỏ tờ tiền vào L70 cho có tín hiệu chạy.
 * ========================================================================== */

#define FW_VERSION "cau-ict 2026-08-25a (nghe len 2 chieu ICT L70 <-> ghe)"

/* ============================================================================
 *  CHÂN CẮM — CHỌN CHO DỄ CẮM DỄ ĐO, VÌ ĐÂY LÀ MẠCH TEST
 * ----------------------------------------------------------------------------
 *  Trên DevKit ESP32 30 chân, hàng bên TRÁI đếm từ giữa xuống có đúng năm chân
 *  nằm liền nhau:   D32  D33  D25  D26  D27   — rồi tới D14 D12 D13 GND.
 *  Lấy nguyên khối đó: đếm không nhầm, kẹp que đo không chạm nhau, và GND thì
 *  nằm ngay dưới cùng hàng nên khỏi vòng dây sang bên kia bo.
 *
 *  Cả năm chân đều AN TOÀN: không quyết định kiểu boot, không dính flash, không
 *  bị PSRAM chiếm, và đều xuất được (không phải loại chỉ vào được).
 *
 *  ⚠️ NHỮNG CHÂN ĐỪNG BAO GIỜ LẤY CHO UART, kể cả khi làm mạch thật sau này:
 *      GPIO 6..11    dính flash — dùng là chip không boot.
 *      GPIO 34..39   CHỈ VÀO được, không làm chân TX được.
 *      GPIO 1, 3     là cổng USB (Serial0). Lấy là mất luôn màn hình log.
 *      GPIO 0,2,5,12,15  quyết định kiểu boot. Thiết bị bên kia kéo mấy chân đó
 *                    lúc cắm điện là ESP32 không khởi động. Riêng GPIO12 bị kéo
 *                    lên còn làm chip đổi flash sang 1,8V — coi như hỏng bo.
 *      GPIO 16, 17   an toàn trên bo WROOM, nhưng bo WROVER thì PSRAM chiếm mất.
 *                    Mạch test hay mượn bo bất kỳ nên tránh luôn cho chắc.
 * ========================================================================== */
#define CHAN_ICT_RX   25      // ESP32 nhận  <- TX của ICT L70
#define CHAN_ICT_TX   26      // ESP32 gửi   -> RX của ICT L70
#define CHAN_GHE_RX   32      // ESP32 nhận  <- TX của bo ghế
#define CHAN_GHE_TX   33      // ESP32 gửi   -> RX của bo ghế
#define CHAN_OE_ICT   -1      // CHỈ dùng khi đấu bằng TXS0108E: -> chân OE phía L70. ADuM1201 không có chân này.
#define CHAN_OE_GHE   -1      // CHỈ dùng khi đấu bằng TXS0108E: -> chân OE phía ghế. -1 = không đấu tới.
// GPIO 27 để trống làm chân dự phòng — dùng cho OE nếu muốn ESP32 tự bật/tắt mạch chuyển mức.

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
 *  ĐO CẠNH XUNG — TRẢ LỜI CÂU "UART HAY XUNG TIỀN?"
 * ----------------------------------------------------------------------------
 *  Tài liệu L-Series cho thấy L70 có NHIỀU kiểu giao tiếp, và hai kiểu hay gặp
 *  nhất KHÁC NHAU VỀ BẢN CHẤT:
 *
 *    Pulse Interface — đầu ra tiền là một CẶP TIẾP ĐIỂM KHÔ cách ly quang
 *      (Credit_Relay_NO / Credit_Relay_COM), phía khách kéo lên qua trở 4K7.
 *      Mỗi tờ tiền = một hoặc vài nhịp đóng tiếp điểm. KHÔNG CÓ BYTE NÀO.
 *
 *    RS232 / ccNet — TTL 5V thật (không phải RS232 ±12V), có TXD và RXD.
 *
 *  Chưa biết bo đang chạy kiểu nào thì DÒ BAUD là vô nghĩa: nếu là xung tiền thì
 *  chẳng có baud nào để dò, mà hàm dò vẫn cứ trả về một con số trông như thật.
 *  Nên phải phân biệt TRƯỚC, và cách phân biệt thì đơn giản: ĐO BỀ RỘNG XUNG.
 *
 *      bề rộng hẹp nhất ~50-500 micro giây, đi thành chùm  -> UART (baud = 1/bề rộng)
 *      bề rộng hàng chục MILI giây, lác đác                -> xung tiền
 *
 *  Hai thứ đó lệch nhau khoảng trăm lần nên không thể lẫn.
 * ========================================================================== */
#define XUNG_TOI_DA 900

struct CanhXung { uint32_t us; uint8_t day; uint8_t muc; };
CanhXung g_canh[XUNG_TOI_DA];

void doXung(uint32_t giay) {
  Serial.printf("[XUNG] do canh trong %lu giay tren CA HAI day.\n", (unsigned long)giay);
  Serial.println("       LAM CHO NO CHAY NGAY BAY GIO: bo to tien vao L70, hoac bam nut tren ghe.");
  congIct.end(); congGhe.end();
  pinMode(CHAN_ICT_RX, INPUT);
  pinMode(CHAN_GHE_RX, INPUT);

  int n = 0;
  uint8_t truocI = digitalRead(CHAN_ICT_RX) ? 1 : 0;
  uint8_t truocG = digitalRead(CHAN_GHE_RX) ? 1 : 0;
  uint32_t het = millis() + giay * 1000UL;
  uint32_t goc = micros();
  while ((int32_t)(het - millis()) > 0 && n < XUNG_TOI_DA) {
    uint8_t a = digitalRead(CHAN_ICT_RX) ? 1 : 0;
    if (a != truocI) { g_canh[n].us = micros() - goc; g_canh[n].day = 0; g_canh[n].muc = a; n++; truocI = a; if (n >= XUNG_TOI_DA) break; }
    uint8_t b = digitalRead(CHAN_GHE_RX) ? 1 : 0;
    if (b != truocG) { g_canh[n].us = micros() - goc; g_canh[n].day = 1; g_canh[n].muc = b; n++; truocG = b; }
  }
  moCong();

  if (n == 0) {
    Serial.println("[XUNG] khong bat duoc canh nao — hai day nam im suot.");
    Serial.println("       Kiem: da lam cho no chay that chua; GND chung chua; dung chan chua;");
    Serial.println("       va neu dung TXS0108E thi chan OE da keo len 3,3V chua.");
    return;
  }
  Serial.printf("[XUNG] bat duoc %d canh%s\n", n, n >= XUNG_TOI_DA ? " (day bo nho, dung som)" : "");

  // Thống kê bề rộng cho từng dây
  for (int d = 0; d < 2; d++) {
    uint32_t hepNhat = 0xFFFFFFFF, rongNhat = 0, tong = 0; int dem = 0;
    uint32_t truoc = 0; bool coTruoc = false;
    for (int i = 0; i < n; i++) {
      if (g_canh[i].day != d) continue;
      if (coTruoc) {
        uint32_t w = g_canh[i].us - truoc;
        if (w < hepNhat) hepNhat = w;
        if (w > rongNhat) rongNhat = w;
        tong += w; dem++;
      }
      truoc = g_canh[i].us; coTruoc = true;
    }
    const char* ten = d ? "GHE" : "L70";
    if (dem == 0) { Serial.printf("  %s: khong du canh de do\n", ten); continue; }
    Serial.printf("  %s: %d khoang, hep nhat %lu us, rong nhat %lu us, trung binh %lu us\n",
                  ten, dem, (unsigned long)hepNhat, (unsigned long)rongNhat,
                  (unsigned long)(tong / (uint32_t)dem));
    /* Đây là chỗ ra kết luận. Ngưỡng 2000us (2ms) nằm giữa hai thế giới: bit của
       UART chậm nhất (1200 baud) rộng 833us, còn xung tiền hẹp nhất cũng cỡ 20ms. */
    if (hepNhat < 2000) {
      long baud = (long)(1000000UL / hepNhat);
      Serial.printf("     => giong UART. Baud xap xi %ld -> go 'DOBAUD' de do ky, roi 'BAUD <so>'.\n", baud);
    } else {
      Serial.printf("     => giong XUNG TIEN (tiep diem kho), khong phai UART.\n");
      Serial.println("        Neu dung vay thi KHONG CAN khung lenh gi ca: gia lam L70 chi la keo");
      Serial.println("        chan Credit_Relay_NO ve COM dung so nhip. Dem so nhip moi to tien");
      Serial.println("        bang cach bo lan luot tung menh gia va xem 'XUNG' bao bao nhieu canh.");
    }
  }

  Serial.println("\n  --- 40 canh dau (micro giay ke tu luc bat dau do) ---");
  for (int i = 0; i < n && i < 40; i++)
    Serial.printf("  %8lu  %s  -> %s\n", (unsigned long)g_canh[i].us,
                  g_canh[i].day ? "GHE" : "L70", g_canh[i].muc ? "CAO" : "THAP");
}

/* ============================================================================
 *  BẢNG LỆNH
 * ========================================================================== */
void inTro() {
  Serial.println(
    "\n===== CAU NGHE LEN ICT L70 <-> GHE — LENH GO QUA USB (115200) =====\n"
    "  TT              trang thai: baud, dem byte/khung tung chieu\n"
    "  XUNG [giay]     ĐO TRUOC TIEN: canh xung tren ca hai day, de biet day la UART\n"
    "                  hay chi la XUNG TIEN (tiep diem kho). Do baud ma nham kieu la vo nghia\n"
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
    Serial.println("  ⚠️ Phia L70 im. Kiem TX cua L70 co vao GPIO25 khong, va OE cua mach ben do.");
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
  Serial.println("   Chung cho ca hai kieu dau:");
  Serial.println("   [ ] Du CA HAI nguon o moi con: phia ESP32 3,3V, phia may 5V");
  Serial.println("   [ ] TX cua ben kia vao RX cua minh (CHEO), khong phai TX-TX");
  Serial.println("   NEU DUNG ADuM1201 (nen dung):");
  Serial.println("   [ ] Dung con 1201, KHONG phai 1200 (1200 hai kenh cung chieu -> UART khong chay)");
  Serial.println("   [ ] HAI net mass RIENG, KHONG cham nhau:");
  Serial.println("       net 1 = GND1 hai con + GND cua ESP32");
  Serial.println("       net 2 = GND2 hai con + GND cua L70 + GND cua bo ghe");
  Serial.println("   [ ] ESP32 an nguon RIENG (cam USB laptop la duoc), khong lay 5V cua may");
  Serial.println("   [ ] Tu 0,1uF sat chan VDD1 va VDD2 cua TUNG con");
  Serial.println("   NEU DUNG TXS0108E:");
  Serial.println("   [ ] OE cua CA HAI mach da keo len 3,3V (de ho = ca mach TAT, im lang hoan toan)");
  Serial.println("   [ ] GND CHUNG HET (loai nay khong cach ly)");
  Serial.println("   [ ] Dung so kenh: A1 thong voi B1, A2 voi B2");
  Serial.println("   Xong het thi go tiep QUYTRINH.\n");

  Serial.println("BUOC 2 — UART hay XUNG TIEN? Do canh truoc, vi do baud ma nham kieu la vo nghia.");
  Serial.println("   LAM CHO NO CHAY NGAY BAY GIO (bo to tien vao L70):");
  doXung(8);
  Serial.println("\n   Ket luan o tren bao 'giong XUNG TIEN' thi DUNG LAI O DAY — khong can baud,");
  Serial.println("   khong can khung lenh. Bao 'giong UART' thi di tiep buoc 3.\n");

  Serial.println("BUOC 3 — do baud bang be rong xung. LAM CHO DAY CO TIN HIEU NGAY BAY GIO:");
  long b = doBaudTheoXung(CHAN_ICT_RX, "phia L70", 5);
  if (b == 0) {
    Serial.println("\n❌ DUNG O BUOC 3. Khong co tin hieu tren day phia L70.");
    Serial.println("   Gan nhu chac chan la phan cung — quay lai lam ky BUOC 1.");
    Serial.println("   ADuM1201: kiem du nguon ca hai phia, va co lay nham con 1200 khong.");
    Serial.println("   TXS0108E: kiem chan OE truoc tien. Do xong lam lai QUYTRINH.");
    return;
  }
  if (b != g_baud) { g_baud = b; moCong(); Serial.printf("   -> da dat baud = %ld\n", g_baud); }

  Serial.println("\nBUOC 4 — nghe that 8 giay. Lam cho hai ben noi chuyen (bo tien vao L70):");
  uint32_t i0 = g_soIct, g0 = g_soGhe;
  uint32_t het = millis() + 8000;
  while ((int32_t)(het - millis()) > 0) { chayCau(); inHangDoi(); delay(1); }
  uint32_t dIct = g_soIct - i0, dGhe = g_soGhe - g0;

  Serial.printf("\n----- KET QUA: L70 gui %lu byte, ghe gui %lu byte -----\n",
                (unsigned long)dIct, (unsigned long)dGhe);
  if (dIct == 0 && dGhe == 0) {
    Serial.println("❌ Khong ben nao noi gi. Baud do duoc o buoc 2 nhung khong doc ra byte:");
    Serial.println("   Dung ADuM1201 -> kiem lai chieu tung kenh (VIA/VOA di mot chieu, VIB/VOB chieu kia)");
    Serial.println("   va tu loc 0,1uF sat chan nguon.");
    Serial.println("   Dung TXS0108E -> no dang bo tron suon xung. Ha baud (BAUD 4800), van vay");
    Serial.println("   thi doi sang ADuM1201 / TXB0104 / SN74LVC2T45.");
  } else if (dGhe == 0) {
    Serial.println("⚠️ Chi co L70 noi, ghe im. Hai kha nang:");
    Serial.println("   - bo ghe von chi NGHE, khong tra loi (rat co the — con HT245 chi co MOT chan");
    Serial.println("     DIR cho ca 8 kenh nen hai duong qua no bi ep cung mot chieu);");
    Serial.println("   - hoac duong ve chua dau dung. Kiem TX cua ghe -> GPIO32.");
    Serial.println("   Neu la kha nang dau thi VAN LAM DUOC: chi can phat lai dung cai L70 phat.");
  } else if (dIct == 0) {
    Serial.println("⚠️ Chi co ghe noi, L70 im. Kiem TX cua L70 -> GPIO25, va OE mach phia do.");
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
    else if (lenh == "XUNG") { uint32_t g = tham.toInt(); doXung(g ? g : 10); }
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
    Serial.println("[OE] Khong khai chan OE. Dung ADuM1201 thi DUNG vay - no khong co chan OE.\n"
                   "     Con dung TXS0108E thi phai NOI CUNG OE len 3,3V o ca hai mach:\n"
                   "     de ho la ca mach TAT, khong byte nao qua, va khong bao loi gi ca.");

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
