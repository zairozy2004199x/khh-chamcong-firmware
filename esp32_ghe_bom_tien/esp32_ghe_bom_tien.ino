/*
 * esp32_ghe_bom_tien — nghe ICT, chuyển tiếp vào ghế, và bơm thêm tiền khi cần
 * ---------------------------------------------------------------------------
 * NẠP VÀO CON ESP32 PHỤ để thử. Con đang gắn trên ghế KHÔNG đụng tới.
 *
 * VÌ SAO BẢN NÀY ĐƠN GIẢN HẲN SO VỚI esp32_ghe_gia_lap_mdb
 * Bản kia cố ĐÓNG VAI cục nhận tiền: trả lời cuộc bắt tay 9600 baud của bo ghế.
 * Sai hướng. Cách hãng làm là để ICT nguyên trong mạch — nó lo phần bắt tay —
 * còn ESP32 chỉ chen vào giữa, nghe ICT rồi nói lại vào chân RX của ghế.
 *
 * ĐO ĐƯỢC GÌ (bản ghi 1 MHz, ba lần nạp tờ 10k cho ba cụm khớp nhau từng µs):
 *      thấp  414 µs  = 2 bit      →  start + d0(0)
 *      cao   207 µs  = 1 bit      →  d1(1)
 *      thấp 1242 µs  = 6 bit      →  d2..d7 (0)
 *      cao            nghỉ        →  stop
 *   207 µs là một bit ở 4800 baud. Ghép lại: 0000 0010 = 0x02.
 *
 *      ICT gửi MỘT byte 0x02 ở 4800 baud 8N1 khi nhận 10.000đ.
 *      Không khung, không checksum, không bắt tay.
 *
 * ĐẤU DÂY
 *   GPIO 35  ←  đường ICT nói ra      (nghe ICT báo tiền)
 *   GPIO 26  →  chân RX22 của ghế     (nói vào ghế)
 *   GND      ↔  mát chung
 *
 *   ⚠ Đường ICT chạy 5V. GPIO 35 phải qua bộ chia áp 1k/2k như đã làm với TX22.
 *
 * GÕ LỆNH (Serial Monitor 115200, New Line)
 *   b        bơm một tờ — gửi mã đang đặt
 *   mXX      đổi mã tờ tiền, hex 2 chữ số (mặc định 02 = 10k)
 *   c        bật/tắt chuyển tiếp ICT → ghế
 *   r        xoá bộ đếm
 */

#define CHAN_ICT   35        // chỉ-vào-được: không thể lỡ tay đẩy ngược vào ICT
#define CHAN_GHE   26        // chân đẩy ra được

/* 4800 baud 8N1 — đo được từ bản ghi, không phải đoán. */
static const uint32_t TOC_DO = 4800;

/* Mã kênh tiền của ICT. 0x02 = 10.000đ trên máy anh Thắng, đo ngày 24/08/2026.
   Mệnh giá khác gần như chắc là mã khác — nạp một tờ rồi xem dòng "ICT:" in ra. */
uint8_t maToTien = 0x02;

/* Chuyển tiếp ICT sang ghế. Bật thì ghế nhận tiền thật y như khi nối thẳng;
   tắt thì ESP32 nuốt hết, dùng để tách bạch lúc thử. */
bool chuyenTiep = true;

uint32_t soNgheIct = 0, soBom = 0;

HardwareSerial Bus(1);       // RX = chân nghe ICT, TX = chân nói vào ghế

void inTrangThai() {
  Serial.printf("[mã tờ tiền: %02X | chuyển tiếp: %s | nghe ICT %lu, đã bơm %lu]\n",
                maToTien, chuyenTiep ? "BẬT" : "tắt", soNgheIct, soBom);
}

void docLenh() {
  if (!Serial.available()) { return; }
  String d = Serial.readStringUntil('\n');
  d.trim();
  if (d.length() == 0) { return; }

  char c = d.charAt(0);
  if (c == 'b') {
    Bus.write(maToTien);
    soBom++;
    Serial.printf("%8lu ms  BƠM %02X vào ghế\n", millis(), maToTien);
  } else if (c == 'm') {
    maToTien = (uint8_t)strtol(d.substring(1).c_str(), nullptr, 16);
    Serial.printf(">> mã tờ tiền = %02X\n", maToTien);
  } else if (c == 'c') {
    chuyenTiep = !chuyenTiep;
    Serial.printf(">> chuyển tiếp ICT → ghế: %s\n", chuyenTiep ? "BẬT" : "TẮT");
  } else if (c == 'r') {
    soNgheIct = soBom = 0;
    Serial.println(">> đã xoá bộ đếm");
  } else {
    Serial.println(F("\n  b     bơm một tờ (gửi mã đang đặt)"));
    Serial.println(F("  mXX   đổi mã tờ tiền, hex 2 chữ số"));
    Serial.println(F("  c     bật/tắt chuyển tiếp ICT → ghế"));
    Serial.println(F("  r     xoá bộ đếm\n"));
  }
  inTrangThai();
}

void setup() {
  Serial.begin(115200);
  delay(300);
  Bus.begin(TOC_DO, SERIAL_8N1, CHAN_ICT, CHAN_GHE);

  Serial.println(F("\n=== bơm tiền vào ghế ==="));
  Serial.printf("nghe ICT GPIO %d · nói vào ghế GPIO %d · %lu baud 8N1\n",
                CHAN_ICT, CHAN_GHE, (unsigned long) TOC_DO);
  Serial.println(F("Nạp một tờ tiền thật để xem mã của mệnh giá đó, rồi gõ 'b' để bơm lại."));
  inTrangThai();
}

void loop() {
  docLenh();

  while (Bus.available()) {
    uint8_t b = Bus.read();
    soNgheIct++;
    Serial.printf("%8lu ms  ICT: %02X", millis(), b);
    if (chuyenTiep) {
      Bus.write(b);
      Serial.print("   → chuyển tiếp vào ghế");
    } else {
      Serial.print("   (nuốt, không chuyển tiếp)");
    }
    Serial.println();
  }
}
