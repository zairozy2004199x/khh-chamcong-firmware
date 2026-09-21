/* =============================================================================================
 *  NGHE BO GHẾ NÓI GÌ — bản firmware CHỈ NGHE, KHÔNG BAO GIỜ NÓI.
 *  K&H · dùng một lần để nhận dạng giao thức, xong thì bỏ.
 *
 *  BỐI CẢNH
 *  Hai chân RX/TX trên bo ghế vốn để nói chuyện với CỤC NHẬN TIỀN. Anh Thắng đã chuyển L70 sang
 *  đường xung nối thẳng ESP32, nên hai chân đó giờ nối tới ESP32 — tức ESP32 phải ĐÓNG VAI cục
 *  nhận tiền. Muốn đóng vai thì phải biết bo ghế nói tiếng gì trước đã.
 *
 *  Bo ghế là CHỦ: ngay lúc này, chưa cắm gì vào, nó vẫn đang liên tục GỌI cục nhận tiền. Nên chỉ
 *  cần ngồi nghe là giao thức tự lộ ra.
 *
 *  🔴 VÌ SAO CHỈ NGHE:
 *     Bắn bừa một byte vào bus mà chưa biết giao thức thì tệ nhất không phải là "không chạy" —
 *     mà là bo ghế hiểu nhầm thành "có tiền" rồi cộng phút, hoặc vào trạng thái lỗi phải ngắt
 *     điện mới ra. Nhận dạng xong rồi mới nói, và nói thì viết ở firmware chính.
 *
 *  🔴 CHÂN NGHE LÀ GPIO 35 — CỐ Ý CHỌN CHÂN CHỈ-VÀO-ĐƯỢC.
 *     GPIO 34/35 trên ESP32 không có tầng đẩy ra. Nghĩa là dù mã có sai thế nào, dù `pinMode`
 *     gõ nhầm thành OUTPUT, chân đó VẪN KHÔNG THỂ đẩy tín hiệu ngược vào bo ghế. Với một bản
 *     firmware cắm vào máy đang chạy tiền của khách, đó không phải là chi tiết nhỏ.
 *
 *  ĐẤU DÂY
 *     Bo ghế  TX ───┬──────────────►  ESP32 GPIO 35
 *                   │
 *                  (chia áp nếu bo 5V — xem dưới)
 *     Bo ghế GND ───────────────────  ESP32 GND      ← BẮT BUỘC, không có thì đọc ra rác
 *     Bo ghế  RX ────  KHÔNG NỐI  ─   (bản này không nói)
 *
 *  ⚠️ ĐO ÁP TRƯỚC KHI CẮM. Chân ESP32 chịu 3.3V, KHÔNG chịu 5V.
 *       - Đo giữa TX của bo ghế và GND lúc ghế đứng yên.
 *       - Ra ~3.3V  -> cắm thẳng được.
 *       - Ra ~5V    -> chia áp: TX ──[10k]──┬── GPIO35
 *                                           └──[20k]── GND
 *       - Ra ~12V   -> KHÔNG chia áp cho đủ; dùng opto PC817 hoặc module MDB. Cắm thẳng là
 *                      chết chân ESP32 ngay, và có khi chết cả con.
 *
 *  CÁCH DÙNG
 *     1. Nạp bản này, mở Serial Monitor ở 115200.
 *     2. Bật ghế lên. Để yên 30 giây.
 *     3. Chép TOÀN BỘ những gì in ra gửi lại — trong đó có phần đoán giao thức.
 * ============================================================================================= */

#define CHAN_NGHE     35        // chỉ-vào-được: không thể lỡ tay đẩy ngược vào bo ghế
#define NGUONG_KHUNG  8000UL    // im quá ngần này (micro giây) = hết một khung, xuống dòng

/* Các tốc độ đáng thử, xếp theo mức hay gặp ở cục nhận tiền. 9600 là của ccTalk và MDB. */
const uint32_t TOC_DO[] = { 9600, 19200, 38400, 4800, 2400, 57600, 115200 };
const int SO_TOC_DO = sizeof(TOC_DO) / sizeof(TOC_DO[0]);

const uint32_t NGHE_MOI_TOC_DO_MS = 4000;   // nghe mỗi tốc độ 4 giây rồi đổi

HardwareSerial BusGhe(1);

struct KetQua {
  uint32_t toc_do;
  uint32_t so_byte;
  uint32_t so_khung;
  uint32_t byte_la;     // byte >= 0x80: nhiều bất thường = sai tốc độ, hoặc là bus 9-bit
};
KetQua bang[SO_TOC_DO];

/* Đếm cạnh trên đường — để phân biệt "bo ghế im hẳn" với "có tín hiệu mà giải sai tốc độ".
   Hai thứ đó trông giống nhau ở cổng Serial (đều ra ít byte), mà cách xử lý khác hẳn: một bên
   là đấu dây sai, một bên là đoán sai tốc độ. */
volatile uint32_t g_canh = 0;
void IRAM_ATTR demCanh(){ g_canh++; }

void inHex(uint8_t b){
  const char* h = "0123456789ABCDEF";
  Serial.print(h[b >> 4]); Serial.print(h[b & 0x0F]); Serial.print(' ');
}

/* =============================================================================================
 *  NHẬN DẠNG GIAO THỨC — chạy ngay trên byte vừa nghe được, không bắt anh Thắng đọc hex.
 *
 *  Mỗi lượt qua lại giữa hai bên tốn cả buổi, nên một lần nạp phải trả lời được hết. Ba họ giao
 *  thức dưới đây có DẤU VÂN TAY khác hẳn nhau, kiểm bằng máy chắc hơn nhìn mắt nhiều.
 * ============================================================================================= */

#define BO_DEM 512
uint8_t  g_bo[BO_DEM];
uint16_t g_soBo = 0;

void gomByte(uint8_t b){ if(g_soBo < BO_DEM) g_bo[g_soBo++] = b; }

/* --- ccTalk ---------------------------------------------------------------------------------
   Khung: [đích][số byte data][nguồn][mã lệnh][data...][checksum]
   Dấu vân tay: TỔNG TẤT CẢ byte trong khung chia hết cho 256.

   🔴 BẪY đã sập một lần, 24/08/2026: chuỗi toàn 00 có tổng bằng 0, mà 0 thì chia hết 256.
      Nên mọi đoạn rỗng đều "khớp checksum", và bản dò hớn hở báo "ccTalk — kết luận CHẮC"
      trong khi thật ra chân đọc đang kẹt ở mức thấp, không có byte nào. Câu "ba khung liên
      tiếp không thể trùng ngẫu nhiên" chỉ đúng khi các byte KHÁC NHAU.
      Từ đây: khung mà mọi byte giống hệt nhau thì không tính. */
int doCcTalk(){
  int khop = 0;
  for(uint16_t i = 0; i + 4 < g_soBo; i++){
    uint8_t len = g_bo[i + 1];
    uint16_t tong_dai = (uint16_t)len + 5;
    if(len > 32 || i + tong_dai > g_soBo) continue;
    uint8_t tong = 0;
    bool co_khac = false;
    for(uint16_t j = 0; j < tong_dai; j++){
      tong += g_bo[i + j];
      if(g_bo[i + j] != g_bo[i]) co_khac = true;
    }
    if(tong == 0 && co_khac){ khop++; i += tong_dai - 1; }
  }
  return khop;
}

/* Mọi byte gom được có giống hệt nhau không? Nếu có thì đấy là chân kẹt mức, không phải
   dữ liệu — và mọi kết luận phía sau đều vô nghĩa, phải nói thẳng ra. */
bool moiByteGiongNhau(){
  if(g_soBo < 8) return false;
  for(uint16_t i = 1; i < g_soBo; i++){ if(g_bo[i] != g_bo[0]) return false; }
  return true;
}

/* --- ICT RS232 ------------------------------------------------------------------------------
   Cục ICT ở chế độ nối tiếp bắn MỘT byte cho mỗi sự việc, không khung không checksum, và bo
   ghế thường im lặng hoàn toàn (chỉ nghe). Dấu hiệu: byte lẻ tẻ, cách nhau xa, không thành cụm.

   ⚠️ KHÔNG khẳng định "đúng là ICT" từ dấu hiệu này — nó chỉ nói "KHÔNG phải ccTalk và KHÔNG
      phải bus có khung". Bảng byte cụ thể vẫn phải soi từ hex thật. Nói quá lên là dẫn tới viết
      firmware theo một giả định chưa ai kiểm. */
int doLeTe(uint32_t so_byte, uint32_t so_khung){
  if(so_khung == 0) return 0;
  return (so_byte / so_khung) <= 2 ? 1 : 0;   // mỗi "khung" chỉ 1-2 byte = bắn lẻ
}

/* --- MDB 9-bit ------------------------------------------------------------------------------
   MDB là 9600 baud nhưng 9 BIT — UART thường của ESP32 không đọc nổi. Nên đọc bằng tay: canh
   giữa mỗi bit, lấy đủ 9 bit + bit chẵn lẻ.

   🔴 Đọc ở đây CHỈ ĐỌC. Không có digitalWrite nào trong khối này, và chân 35 vốn không đẩy ra
      được — hai lớp, vì đây là bus của máy đang chạy tiền khách. */
/* 1/9600 = 104,1667 µs — KHÔNG tròn. Giữ dạng phân số và tính mốc TUYỆT ĐỐI từ mép sườn;
   cộng dồn 104 µs mười một lần thì tới bit stop đã lệch 1,8 µs, lấy mẫu trượt sang bit bên. */
static const uint32_t MDB_BIT_x100 = 10417;
static inline uint32_t mdbGiua(uint8_t k){ return (MDB_BIT_x100 * (2 * k + 1)) / 200; }

static inline void mdbChoToi(uint32_t moc){
  while((int32_t)(micros() - moc) < 0){ /* bận chờ; ở 104 µs thì không đáng ngại */ }
}

int ngheMdb(uint32_t ms){
  pinMode(CHAN_NGHE, INPUT);
  uint32_t het = millis() + ms;
  int so_byte = 0, so_dia_chi = 0, so_hong = 0;

  Serial.println();
  Serial.printf("=== NGHE KIỂU MDB 9-BIT (%lu giây) ===\n", (unsigned long)(ms / 1000));

  while(millis() < het){
    /* 🔴 Phải bắt SƯỜN XUỐNG, không phải "đang ở mức thấp".
       Bản cũ chỉ hỏi digitalRead()==0 rồi lao vào đọc. Khi đường nằm thấp kéo dài — mà bo ghế
       có hẳn một quãng thấp 1 ms lúc bắt tay — nó tưởng mọi điểm trong quãng đó đều là bit
       start, đọc ra chín bit thấp, tức 00, lặp tới khi đầy bộ đệm. Đó là nguồn gốc của
       "401 byte toàn 00" hôm 24/08/2026.
       Nên: chờ đường LÊN CAO trước (về mức nghỉ), rồi mới rình lúc nó XUỐNG. */
    while(digitalRead(CHAN_NGHE) == LOW){ if(millis() >= het) goto xong; }
    while(digitalRead(CHAN_NGHE) == HIGH){ if(millis() >= het) goto xong; }
    uint32_t goc = micros();                    // mép sườn xuống = mép bit start

    mdbChoToi(goc + mdbGiua(0));
    if(digitalRead(CHAN_NGHE) != LOW){ continue; }   // gai nhiễu, không phải start thật

    uint16_t v = 0;
    for(int i = 0; i < 9; i++){
      mdbChoToi(goc + mdbGiua(i + 1));
      if(digitalRead(CHAN_NGHE)) v |= (1 << i);
    }

    /* Kiểm bit stop. Không kiểm thì khung hỏng cũng được nhận, và một chuỗi rác vẫn ra
       được "byte" trông như thật. */
    mdbChoToi(goc + mdbGiua(10));
    if(digitalRead(CHAN_NGHE) != HIGH){ so_hong++; continue; }

    /* Bit thứ 9 bật = byte ĐỊA CHỈ (mở đầu một khung MDB). */
    bool la_dia_chi = (v & 0x100) != 0;
    if(la_dia_chi){ Serial.println(); Serial.print("  [ĐC] "); so_dia_chi++; }
    inHex((uint8_t)(v & 0xFF));
    gomByte((uint8_t)(v & 0xFF));
    so_byte++;
    if(so_byte > 400) break;
  }
xong:
  Serial.println();
  Serial.printf("  -> %d byte, %d byte địa chỉ (bit 9 bật), %d khung hỏng bit stop\n",
                so_byte, so_dia_chi, so_hong);
  if(so_byte >= 8 && so_dia_chi == 0 && so_hong == 0){
    Serial.println("  ⚠ Không byte nào có bit 9 — nếu tất cả lại giống hệt nhau thì đây KHÔNG");
    Serial.println("    phải dữ liệu MDB, mà là chân đọc đang kẹt mức. Xem cảnh báo ở KẾT LUẬN.");
  }
  return so_dia_chi;
}

void nghe(int idx){
  uint32_t toc = TOC_DO[idx];
  bang[idx].toc_do = toc; bang[idx].so_byte = 0; bang[idx].so_khung = 0; bang[idx].byte_la = 0;

  Serial.println();
  Serial.printf("=== NGHE Ở %lu baud (%lu giây) ===\n", (unsigned long)toc, NGHE_MOI_TOC_DO_MS / 1000);

  BusGhe.begin(toc, SERIAL_8N1, CHAN_NGHE, -1);   // -1 = KHÔNG khai chân TX, không thể nói
  uint32_t het   = millis() + NGHE_MOI_TOC_DO_MS;
  uint32_t cuoi  = 0;
  bool     dang  = false;
  uint8_t  dem_dong = 0;

  while(millis() < het){
    while(BusGhe.available()){
      uint8_t b = BusGhe.read();
      uint32_t bay_gio = micros();
      if(dang && (bay_gio - cuoi) > NGUONG_KHUNG){
        Serial.println();  dem_dong = 0;  bang[idx].so_khung++;
      }
      if(dem_dong == 0){ Serial.print("  "); }
      inHex(b);
      if(toc == 9600) gomByte(b);   // chỉ gom ở 9600: ccTalk và MDB đều ở tốc độ này
      bang[idx].so_byte++;
      if(b >= 0x80) bang[idx].byte_la++;
      cuoi = bay_gio; dang = true;
      if(++dem_dong >= 16){ Serial.println(); dem_dong = 0; }
    }
    delay(1);
  }
  if(dang){ Serial.println(); bang[idx].so_khung++; }
  BusGhe.end();

  Serial.printf("  -> %lu byte, %lu khung, %lu byte >= 0x80\n",
    (unsigned long)bang[idx].so_byte, (unsigned long)bang[idx].so_khung,
    (unsigned long)bang[idx].byte_la);
}

void ketLuan(){
  Serial.println();
  Serial.println("================= KẾT LUẬN =================");

  uint32_t canh = g_canh;
  Serial.printf("Số cạnh tín hiệu đếm được trên chân: %lu\n", (unsigned long)canh);
  if(canh < 50){
    /* Không có cạnh nào = không có tín hiệu tới chân. Đây là lỗi ĐẤU DÂY, không phải sai tốc
       độ — nói thẳng ra, chứ đưa một bảng số 0 thì người đọc đi chỉnh tốc độ mãi không xong. */
    Serial.println("KHÔNG CÓ TÍN HIỆU NÀO tới chân. Đây là chuyện ĐẤU DÂY, không phải tốc độ:");
    Serial.println("  1. Đã nối GND của bo ghế với GND của ESP32 chưa? Thiếu là đọc ra rác/không gì.");
    Serial.println("  2. Có chắc đang cắm vào chân TX của bo ghế không? Thử cắm sang chân kia.");
    Serial.println("  3. Bo ghế đã có điện chưa? Có bo chỉ gọi cục tiền khi màn hình sáng.");
    Serial.println("  4. Đo áp TX-GND: 0V suốt = bo không gọi; 12V = phải qua opto, đừng cắm thẳng.");
    return;
  }

  int tot = -1;
  for(int i = 0; i < SO_TOC_DO; i++){
    if(bang[i].so_byte < 4) continue;
    /* Tốc độ ĐÚNG cho ra khung gọn và ít byte lạ. Sai tốc độ thì byte vỡ vụn, rất nhiều byte
       rơi vào vùng >= 0x80 vì bit bị cắt lệch. */
    if(tot < 0) { tot = i; continue; }
    float la_i   = (float)bang[i].byte_la   / (float)bang[i].so_byte;
    float la_tot = (float)bang[tot].byte_la / (float)bang[tot].so_byte;
    if(la_i < la_tot) tot = i;
  }

  if(tot < 0){
    Serial.println("CÓ tín hiệu trên chân nhưng KHÔNG giải ra byte nào ở mọi tốc độ đã thử.");
    Serial.println("Nhiều khả năng là bus 9-BIT (MDB) — UART thường không đọc được.");
    Serial.println("Gửi kết quả này về, sẽ làm bản nghe kiểu MDB 9-bit.");
    return;
  }

  Serial.printf("TỐC ĐỘ NHIỀU KHẢ NĂNG NHẤT: %lu baud\n", (unsigned long)bang[tot].toc_do);

  /* --- Nhận dạng họ giao thức --- */
  if(moiByteGiongNhau()){
    Serial.println();
    Serial.println("🔴 MỌI BYTE GOM ĐƯỢC ĐỀU GIỐNG HỆT NHAU — ĐÂY KHÔNG PHẢI DỮ LIỆU.");
    Serial.printf("   Cả %u byte đều là %02X. Không giao thức nào phát như vậy.\n",
                  (unsigned)g_soBo, g_bo[0]);
    Serial.println("   Nghĩa là chân đọc đang bị giữ ở một mức, không nhận được tín hiệu thật:");
    Serial.println("     - kẹp GND chưa chắc, hoặc chưa chung mát với bo ghế");
    Serial.println("     - kẹp vào nhầm chân (chân nguồn, chân mát, chân điều khiển)");
    Serial.println("     - dây tuột");
    Serial.println("   MỌI KẾT LUẬN GIAO THỨC DƯỚI ĐÂY ĐỀU VÔ NGHĨA. Sửa dây rồi đo lại.");
    Serial.println();
  }

  int cc = doCcTalk();
  Serial.println();
  if(cc >= 3){
    Serial.printf(">>> ccTalk — %d khung có checksum ĐÚNG (tổng byte chia hết 256).\n", cc);
    Serial.println(">>> Khung ccTalk: [đích][số data][nguồn][mã lệnh][data...][checksum].");
    Serial.println(">>> Các byte trong khung KHÁC nhau, nên khớp checksum ở đây là dấu hiệu thật,");
    Serial.println(">>> không phải kiểu chuỗi rỗng nào cũng khớp. Vẫn nên đối chiếu bằng logic analyzer.");
  } else if(doLeTe(bang[tot].so_byte, bang[tot].so_khung)){
    Serial.println(">>> KHÔNG phải ccTalk. Byte bắn LẺ TẺ, không thành khung —");
    Serial.println(">>> hợp với kiểu 'mỗi sự việc một byte' (ICT RS232 và vài hãng khác).");
    Serial.println(">>> Chưa khẳng định được là hãng nào: bảng byte phải soi từ hex ở trên.");
  } else {
    Serial.println(">>> Có khung nhưng KHÔNG khớp checksum ccTalk — giao thức riêng của hãng.");
    Serial.println(">>> Phần hex ở trên là thứ duy nhất lần ra được. Gửi nguyên về.");
  }
  Serial.println();
  Serial.println("Bảng đầy đủ:");
  for(int i = 0; i < SO_TOC_DO; i++){
    Serial.printf("  %6lu baud : %4lu byte, %3lu khung, %3lu byte lạ\n",
      (unsigned long)bang[i].toc_do, (unsigned long)bang[i].so_byte,
      (unsigned long)bang[i].so_khung, (unsigned long)bang[i].byte_la);
  }
  Serial.println();
  Serial.println("Chép TOÀN BỘ phần in ra ở trên gửi lại — phần hex mới là thứ nhận ra giao thức.");
  Serial.println("============================================");
}

void setup(){
  Serial.begin(115200);
  delay(600);
  Serial.println();
  Serial.println("### NGHE BO GHẾ — bản CHỈ NGHE, không gửi gì ra bus ###");
  Serial.printf("Chân nghe: GPIO %d (chỉ-vào-được, không thể đẩy ngược vào bo ghế)\n", CHAN_NGHE);
  Serial.println("Nhớ: GND bo ghế PHẢI nối GND ESP32. Bo 5V thì chia áp. Bo 12V thì qua opto.");
  Serial.println();

  pinMode(CHAN_NGHE, INPUT);
  attachInterrupt(digitalPinToInterrupt(CHAN_NGHE), demCanh, CHANGE);

  for(int i = 0; i < SO_TOC_DO; i++) nghe(i);

  detachInterrupt(digitalPinToInterrupt(CHAN_NGHE));

  /* MDB là 9 BIT — UART thường ở trên đọc không ra. Nghe thêm kiểu MDB để một lần nạp là kết
     luận được, khỏi phải nạp lại lần hai. */
  int mdb = ngheMdb(5000);

  ketLuan();
  if(mdb >= 2){
    Serial.println();
    Serial.printf(">>> CÓ %d BYTE ĐỊA CHỈ MDB (bit thứ 9 bật).\n", mdb);
    Serial.println(">>> Bo ghế nói MDB. UART thường đọc không ra là ĐÚNG, không phải sai tốc độ.");
    Serial.println(">>> Firmware ghế đã có sẵn khối MDB — nhưng ở vai CHỦ; sẽ viết thêm vai TỚ.");
  }
}

void loop(){ delay(1000); }
