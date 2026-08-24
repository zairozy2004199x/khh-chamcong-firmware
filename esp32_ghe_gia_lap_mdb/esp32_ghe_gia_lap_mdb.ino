/*
 * esp32_ghe_gia_lap_mdb — đóng vai cục nhận tiền để nói chuyện với BO GHẾ
 * ---------------------------------------------------------------------
 * NẠP VÀO CON ESP32 PHỤ. Con đang gắn trên ghế KHÔNG đụng tới.
 *
 * Vì sao có bản này: đo bằng logic analyzer thấy bo ghế phát đúng HAI khung
 * lúc lên điện rồi im bặt — nó hỏi một câu, không ai đáp, nên bỏ cuộc.
 * Muốn biết nó nói gì tiếp thì phải trả lời nó. Bản này trả lời, rồi in ra
 * mọi thứ nghe được.
 *
 * Thông số đã dò được từ 4 bản ghi độc lập (tools/doc-sr.py):
 *      9600 baud · 11 bit mỗi khung · cực thuận · nghỉ ở mức CAO
 *      khung = 1 start + 8 data + 1 bit thứ chín + 1 stop
 *
 * Bit thứ chín: MDB gọi là "mode bit" (bật = byte địa chỉ, mở đầu một lệnh),
 * UART thường gọi là bit chẵn lẻ. Trên dây hai cách hiểu giống hệt nhau, nên
 * bản này cứ đẩy/đọc đủ 11 bit rồi để người đọc tự diễn giải — đúng cả hai đường.
 *
 * Vì sao tự nhấp chân thay vì dùng Serial của ESP32: HardwareSerial của
 * Arduino-ESP32 không có kiểu 9 bit dữ liệu. Ở 9600 baud một bit rộng 104 µs,
 * tự nhấp chân thừa sức chính xác.
 *
 * ĐẤU DÂY — đọc kỹ trước khi cắm:
 *   CHAN_NGHE (35)  ←  chân TX của bo ghế      (bo ghế nói, mình nghe)
 *   CHAN_NOI  (26)  →  chân RX của bo ghế      (mình nói, bo ghế nghe)
 *   GND             ←→ mát bo ghế              (bắt buộc, không có thì đọc ra rác)
 *
 *   ⚠ PHẢI RÚT dây RX/TX khỏi con ESP32 đang gắn trên ghế trước khi đấu con này.
 *     Hai con cùng đẩy vào một đường TX là hỏng chân, có khi hỏng cả hai bo.
 *
 *   ⚠ ĐO ÁP chân TX bo ghế trước. Trên 3.3V thì CHAN_NGHE phải qua bộ chia áp.
 *     Và nếu bo ghế chạy 5V thì mức 3.3V của ESP32 có thể không đủ cao để nó
 *     nhận — lúc đó cần một tầng nâng mức, xem docs/CACH-LY-BO-GHE.md.
 *
 * GÕ LỆNH trong Serial Monitor (115200):
 *   d       bật/tắt việc đáp lại  (mặc định TẮT — lần chạy đầu chỉ nghe)
 *   aXXX    đặt khung đáp, hex 3 chữ số, ví dụ  a000  hoặc  a1FF
 *   t       đổi kiểu đáp: chỉ đáp byte địa chỉ  ↔  đáp mọi khung
 *   r       xoá bộ đếm
 *   ?       nhắc lại bảng lệnh
 */

// ————— chân —————
// Chân nghe khớp với MDB_RX_PIN của firmware ghế chính và với bản dò esp32_ghe_nghe_bo —
// ba nơi cùng một chân thì lúc chuyển qua lại khỏi phải đấu lại dây.
#define CHAN_NGHE   35        // chỉ-vào-được: dù mã có sai cũng không thể đẩy ngược vào bo ghế
// Không lấy 27 (MDB_TX_PIN của firmware chính) vì trên ghế chân đó đang giữ xung tiền L70.
// 26 là chân dư, đẩy ra được, không đụng gì.
#define CHAN_NOI    26        // chân đẩy ra được

// ————— nhịp —————
// 9600 baud → 104,1667 µs mỗi bit. Giữ dạng phân số để không dồn sai số qua 11 bit.
static const uint32_t BIT_x100 = 10417;      // bề rộng một bit, đơn vị 1/100 µs

// mốc bắt đầu bit thứ k, tính từ sườn xuống của bit start
static inline uint32_t mocDau(uint8_t k) { return (BIT_x100 * k) / 100; }
// mốc GIỮA bit thứ k — chỗ lấy mẫu cho chắc
static inline uint32_t mocGiua(uint8_t k) { return (BIT_x100 * (2 * k + 1)) / 200; }

static const uint8_t SO_BIT_DU_LIEU = 9;     // 8 data + bit thứ chín
static const uint8_t BIT_STOP       = 10;    // vị trí bit stop trong khung

// ————— trạng thái —————
bool     dapLai      = false;   // lần chạy đầu chỉ nghe cho an toàn
bool     chiDapDiaChi = true;   // chỉ trả lời khung có bit thứ chín bật
uint16_t khungDap    = 0x000;   // ACK của MDB là 0x00 với bit thứ chín tắt
uint32_t soNghe = 0, soDap = 0, soLoiStop = 0;

// ————— chờ tới một mốc tuyệt đối, không dùng delay để khỏi trôi —————
static inline void choToi(uint32_t moc) {
  while ((int32_t)(micros() - moc) < 0) { /* bận chờ, 104 µs nên không đáng ngại */ }
}

/*
 * Đọc một khung. Trả về:
 *    0…0x1FF  khung đọc được (bit 8 là bit thứ chín)
 *    -1       hết hạn chờ, không có gì
 *    -2       sườn xuống nhưng không phải bit start thật (nhiễu gai)
 *    -3       đọc được nhưng bit stop sai — khung hỏng
 */
int32_t docKhung(uint32_t hanChoUs) {
  uint32_t batDau = micros();

  /* Phải chờ đường VỀ MỨC NGHỈ trước, rồi mới rình lúc nó XUỐNG.
     Bỏ vòng đầu thì khi hàm được gọi lúc đường đang thấp, vòng dưới thoát ngay và mã tưởng
     vừa bắt được bit start, trong khi thật ra đang đứng giữa một quãng thấp — đọc ra rác,
     bit stop sai, lặp mỗi mili-giây. Đúng lỗi "KHUNG HỎNG" tràn màn hình 24/08/2026, cùng
     gốc với "401 byte toàn 00" của bản nghe. */
  while (digitalRead(CHAN_NGHE) == LOW) {
    if (micros() - batDau > hanChoUs) { return -4; }   // -4 = đường kẹt ở mức thấp
  }
  while (digitalRead(CHAN_NGHE) == HIGH) {
    if (micros() - batDau > hanChoUs) { return -1; }
  }
  uint32_t goc = micros();                       // sườn xuống = mép bit start

  choToi(goc + mocGiua(0));
  if (digitalRead(CHAN_NGHE) != LOW) { return -2; }   // gai nhiễu, không phải start

  uint16_t v = 0;
  for (uint8_t i = 0; i < SO_BIT_DU_LIEU; i++) {
    choToi(goc + mocGiua(i + 1));
    if (digitalRead(CHAN_NGHE)) { v |= (1 << i); }    // UART đẩy bit thấp ra trước
  }

  choToi(goc + mocGiua(BIT_STOP));
  if (digitalRead(CHAN_NGHE) != HIGH) {
    soLoiStop++;
    return -3;
  }
  return (int32_t)v;
}

// Đẩy một khung 11 bit ra CHAN_NOI.
void guiKhung(uint16_t v) {
  uint32_t goc = micros();
  digitalWrite(CHAN_NOI, LOW);                        // bit start
  for (uint8_t i = 0; i < SO_BIT_DU_LIEU; i++) {
    choToi(goc + mocDau(i + 1));
    digitalWrite(CHAN_NOI, (v >> i) & 1);
  }
  choToi(goc + mocDau(BIT_STOP));
  digitalWrite(CHAN_NOI, HIGH);                       // bit stop
  choToi(goc + mocDau(BIT_STOP + 1));                 // giữ đủ bề rộng bit stop
}

void inBangLenh() {
  Serial.println(F("\n--- lệnh ---"));
  Serial.println(F("  d     bật/tắt đáp lại"));
  Serial.println(F("  aXXX  đặt khung đáp (hex 3 chữ số, vd a000 / a1FF)"));
  Serial.println(F("  t     đổi: chỉ đáp byte địa chỉ <-> đáp mọi khung"));
  Serial.println(F("  r     xoá bộ đếm"));
  Serial.println(F("  ?     bảng lệnh này\n"));
}

void inTrangThai() {
  Serial.printf("[đáp: %s | %s | khung đáp: %03X | nghe %lu, đáp %lu, stop hỏng %lu]\n",
                dapLai ? "BẬT" : "tắt",
                chiDapDiaChi ? "chỉ byte địa chỉ" : "mọi khung",
                khungDap, soNghe, soDap, soLoiStop);
}

void docLenh() {
  if (!Serial.available()) { return; }
  String d = Serial.readStringUntil('\n');
  d.trim();
  if (d.length() == 0) { return; }

  char c = d.charAt(0);
  if (c == 'd') {
    dapLai = !dapLai;
    Serial.printf(">> đáp lại: %s\n", dapLai ? "BẬT" : "TẮT");
  } else if (c == 't') {
    chiDapDiaChi = !chiDapDiaChi;
    Serial.printf(">> %s\n", chiDapDiaChi ? "chỉ đáp byte địa chỉ" : "đáp mọi khung");
  } else if (c == 'r') {
    soNghe = soDap = soLoiStop = 0;
    Serial.println(">> đã xoá bộ đếm");
  } else if (c == 'a') {
    khungDap = (uint16_t)strtol(d.substring(1).c_str(), nullptr, 16) & 0x1FF;
    Serial.printf(">> khung đáp = %03X\n", khungDap);
  } else {
    inBangLenh();
  }
  inTrangThai();
}

void setup() {
  Serial.begin(115200);
  delay(300);

  pinMode(CHAN_NGHE, INPUT);
  pinMode(CHAN_NOI, OUTPUT);
  digitalWrite(CHAN_NOI, HIGH);      // UART nghỉ ở mức cao

  Serial.println(F("\n=== giả lập cục nhận tiền cho BO GHẾ ==="));
  Serial.printf("nghe GPIO %d · nói GPIO %d · 9600 baud · 11 bit/khung\n",
                CHAN_NGHE, CHAN_NOI);
  Serial.println(F("Cột 'bit9' bật = byte địa chỉ (mở đầu một lệnh)."));
  Serial.println(F("Lần chạy đầu ĐANG TẮT đáp — chỉ nghe. Gõ 'd' để bật đáp."));
  inBangLenh();
  inTrangThai();
}

void loop() {
  docLenh();

  int32_t k = docKhung(200000);      // chờ tối đa 200 ms rồi quay lại xem lệnh
  if (k < 0) {
    /* Gộp lại, mỗi giây in một dòng. In từng khung hỏng thì màn hình tràn hàng nghìn dòng
       giống hệt nhau, che mất byte thật — mà byte thật mới là thứ cần đọc. */
    static uint32_t demHong = 0, demKet = 0, lanIn = 0;
    if (k == -3) { demHong++; }
    if (k == -4) { demKet++; }
    if ((demHong || demKet) && millis() - lanIn > 1000) {
      lanIn = millis();
      if (demKet) {
        Serial.printf("%8lu ms  ĐƯỜNG KẸT Ở MỨC THẤP (%lu lần/giây) — kiểm dây, cực, mát\n",
                      millis(), demKet);
      }
      if (demHong) {
        Serial.printf("%8lu ms  %lu khung hỏng/giây (bit stop sai)\n", millis(), demHong);
      }
      demHong = demKet = 0;
    }
    return;
  }

  soNghe++;
  uint8_t  dl   = k & 0xFF;
  bool     bit9 = (k >> 8) & 1;

  Serial.printf("%8lu ms  nghe: %02X  bit9=%d %s",
                millis(), dl, bit9 ? 1 : 0,
                bit9 ? "(byte địa chỉ)" : "");

  if (dapLai && (bit9 || !chiDapDiaChi)) {
    // MDB cho thiết bị tối đa 5 ms để trả lời. Nghỉ một nhịp cho bo ghế
    // kịp nhả đường rồi mới đẩy, không thì hai bên chồng lên nhau.
    delayMicroseconds(500);
    guiKhung(khungDap);
    soDap++;
    Serial.printf("   → đáp %03X", khungDap);
  }
  Serial.println();
}
