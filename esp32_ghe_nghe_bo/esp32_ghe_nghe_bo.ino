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
  ketLuan();
}

void loop(){ delay(1000); }
