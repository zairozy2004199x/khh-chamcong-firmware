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
 * ĐO ĐƯỢC GÌ — bằng cách nạp tờ thật rồi để chính ESP32 đọc cổng ICT:
 *
 *      81  (0x40 + kênh)  10        ICT báo NHẬN được tiền, 4800 baud 8N1
 *      kênh 1 = 10k · 2 = 20k · 3 = 50k · 4 = 100k
 *
 *   Ba byte, cách nhau 3 ms rồi 1,2 giây. Không checksum, không bắt tay.
 *
 *   ⚠ Các bản ghi logic analyzer đầu cho ra byte 0x02 cho MỌI mệnh giá — SAI, do đọc lệch
 *     khung ở một đường bên cạnh. Byte thật chưa bao giờ là 0x02. Ba lần đọc sai cùng một
 *     kiểu vẫn là sai; thứ lật lại được là đo từ phía khác. Xem docs/GIAO-THUC-BO-GHE.md.
 *
 * ĐẤU DÂY — ESP32 CHEN VÀO GIỮA, không cắm song song
 *   GPIO 35  ←  đường ICT nói ra      (nghe ICT báo tiền)
 *   GPIO 26  →  chân RX22 của ghế     (nói vào ghế)
 *   GND      ↔  mát chung của cả ghế lẫn ICT
 *
 *   🔴 PHẢI RÚT đường ICT nói ra KHỎI chân RX22 trước. Nguyên bản nó cắm thẳng vào đó;
 *      để nguyên rồi cắm thêm GPIO 26 vào cùng chân là hai con cùng lái một sợi dây —
 *      ICT giữ mức cao, ESP32 kéo xuống thấp, dòng chạy thẳng giữa hai cổng đẩy. Nhẹ thì
 *      tín hiệu ra rác, nặng thì chết cổng. Đường ICT phải đi VÒNG qua ESP32.
 *
 *      Ghế TX22 → ICT giữ nguyên, không đụng: cuộc bắt tay 9600 là chuyện riêng của hai đứa.
 *
 *   ⚠ Đường ICT chạy 5V. GPIO 35 phải qua bộ chia áp 1k/2k như đã làm với TX22.
 *
 *   Thử lần đầu thì gọn hơn nữa: chỉ cắm GPIO 26 + GND, để hở hẳn đường ICT. Tiền mặt tạm
 *   không ăn, nhưng trả lời được câu duy nhất đang cần — ghế có nghe mình nói không.
 *
 * GÕ LỆNH (Serial Monitor 115200, New Line)
 *   b10      bơm tờ 10.000đ   ·  b20 · b50 · b100
 *   b        bơm bằng mã đang đặt
 *   mXX      đổi mã tờ tiền, hex 2 chữ số
 *   p / i    đổi khung 8N1→8E1→8O1  ·  đảo cực cả hai chiều
 *   n        đảo RIÊNG chiều nói — bật khi chân nói đi qua transistor nâng mức 5V
 *   vNNNN    đổi tốc độ (mặc định v4800 — đúng như đo được)
 *   c        bật/tắt chuyển tiếp ICT → ghế
 *   r        xoá bộ đếm
 */

#include <driver/uart.h>   // uart_set_line_inverse — đảo riêng chiều nói

#define CHAN_ICT   35        // chỉ-vào-được: không thể lỡ tay đẩy ngược vào ICT
#define CHAN_GHE   26        // chân đẩy ra được

HardwareSerial Bus(1);       // RX = chân nghe ICT, TX = chân nói vào ghế

/* 4800 baud — đo được từ bản ghi (đơn vị 207 µs = 1 bit), không phải đoán.
   Nhưng KHUNG thì chưa chắc: 24/08/2026 chân nghe cho ra "81 41" cách nhau 3 ms trong khi
   logic analyzer đo cùng lúc ở đường bên cạnh lại ra một byte sạch. Một khung vỡ thành hai
   là dấu của sai khung hoặc sai cực, nên để đổi được bằng lệnh thay vì ghim cứng. */
static uint32_t tocDo = 4800;
static uint8_t  kieuKhung = 0;      // 0 = 8N1 · 1 = 8E1 · 2 = 8O1
static bool     daoCuc = false;     // đảo CẢ hai chiều (nghe + nói)
/* Đảo RIÊNG chiều nói. Cần khi chân nói đi qua tầng nâng mức bằng transistor NPN: transistor
   đảo tín hiệu, nên phải đảo trước một lần cho hai lần đảo thành đúng chiều. Không dùng daoCuc
   được, vì nó đảo luôn chiều nghe — mà chiều nghe vẫn cắm thẳng vào ICT, không qua transistor.
   (Đảo daoCuc còn kéo theo lỗi gpio_pulldown_en trên chân 35 vì chân đó chỉ-vào-được.) */
static bool     daoNoi = false;

static uint32_t khungCua(uint8_t k){
  return (k == 1) ? SERIAL_8E1 : (k == 2) ? SERIAL_8O1 : SERIAL_8N1;
}
static const char* tenKhung(uint8_t k){
  return (k == 1) ? "8E1" : (k == 2) ? "8O1" : "8N1";
}

/* Mở lại cổng theo thiết lập hiện tại. ESP32 cho đảo cực ngay ở tầng UART (uart_set_line_inverse)
   — khỏi phải tự nhấp chân, và đảo cả chiều nghe lẫn chiều nói cùng lúc. */
static void moCong(){
  Bus.end();
  Bus.begin(tocDo, khungCua(kieuKhung), CHAN_ICT, CHAN_GHE, daoCuc);
  /* begin() chỉ nhận một cờ đảo cho cả hai chiều. Muốn đảo riêng chiều nói thì gọi thẳng
     xuống tầng dưới, sau khi cổng đã mở. Gọi với 0 để xoá khi tắt. */
  /* UART_NUM_1 chứ không phải số 1: tham số này kiểu uart_port_t, truyền số trần là
     trình dịch từ chối thẳng (invalid conversion from 'int' to 'uart_port_t'). Phải khớp
     đúng cổng đã mở ở HardwareSerial Bus(1). */
  if (daoNoi) uart_set_line_inverse(UART_NUM_1, UART_SIGNAL_TXD_INV);
  else if (!daoCuc) uart_set_line_inverse(UART_NUM_1, UART_SIGNAL_INV_DISABLE);
  Serial.printf(">> cổng: %lu baud %s · cực %s · chiều nói %s\n",
                (unsigned long) tocDo, tenKhung(kieuKhung),
                daoCuc ? "ĐẢO" : "thuận", daoNoi ? "ĐẢO (qua transistor)" : "thuận");
}

/* ——— KHUNG BÁO TIỀN CỦA ICT ———
   Dò xong 24/08/2026 bằng cách nạp lần lượt ba mệnh giá và đọc thẳng byte ICT gửi ra:

        10.000đ  →  81  41  10
        20.000đ  →  81  42  10
        50.000đ  →  81  43  10
       100.000đ  →  81  44  10

   Các byte khác của ICT, đo cùng ngày:
        81 (mã kênh) 29   khách đút vướng, NHẢ tờ ra — KHÔNG có tiền
        29  2F            khách giựt tờ lại, hoặc nhét giấy — KHÔNG có byte 81 mở đầu
        25                kẹt tờ
        2F                sẵn sàng lại

   🔴 Byte CUỐI mới quyết định có tiền hay không: 0x10 là đã nuốt, 0x29 là nhả ra. Hai byte đầu
      giống hệt nhau ở cả hai ca. Bơm thì phải kết bằng 0x10.

   Byte đầu và byte cuối giống hệt nhau ở cả ba; chỉ byte GIỮA đổi, theo đúng cách ICT đánh số
   kênh mệnh giá: 0x40 + số kênh. Kênh 1 = 10k, 2 = 20k, 3 = 50k, 4 = 100k — cả bốn đều đo
   bằng tờ thật, không có con nào suy ra.

   Nhịp cũng cố định: byte giữa cách byte đầu 3 ms, byte cuối cách byte giữa 1,2 giây — đó là
   lúc ICT kéo tờ tiền vào và xác nhận. Bơm mà bỏ nhịp này thì bo ghế có thể không nhận.

   ⚠️ Bản trước gửi MỘT byte 0x02 — sai. 0x02 là do đọc lệch khung ở một đường bên cạnh khi
      đo bằng logic analyzer; byte thật chưa bao giờ là 0x02. */
static const uint8_t ICT_MO_DAU  = 0x81;
static const uint8_t ICT_KET_THUC = 0x10;
static const uint16_t ICT_TRE_MA_MS  = 3;      // mở đầu → mã kênh
static const uint16_t ICT_TRE_HET_MS = 1200;   // mã kênh → kết thúc

struct MenhGia { uint32_t tien; uint8_t kenh; };
static const MenhGia DS_MENH_GIA[] = {
  { 10000,  1 },
  { 20000,  2 },
  { 50000,  3 },
  { 100000, 4 },
};
static const uint8_t SO_MENH_GIA = sizeof(DS_MENH_GIA) / sizeof(DS_MENH_GIA[0]);

uint8_t maToTien = 0x41;   // mặc định kênh 1 = 10.000đ

/* Chuyển tiếp ICT sang ghế. Bật thì ghế nhận tiền thật y như khi nối thẳng;
   tắt thì ESP32 nuốt hết, dùng để tách bạch lúc thử. */
bool chuyenTiep = true;

uint32_t soNgheIct = 0, soBom = 0;
static uint32_t soRac = 0;            // byte 00 do chân nghe thả nổi — đã vứt
static unsigned long g_racBaoLuc = 0; // chặn nhịp báo, 3 giây một lần cho khỏi tràn màn hình


/* ——— Bơm một tờ: ba byte, đúng nhịp ———
   Dùng MÁY TRẠNG THÁI chứ không delay(1200). Bản thử thì delay cũng chạy, nhưng mã này sẽ
   chép sang firmware ghế chính — ở đó chặn vòng lặp 1,2 giây là mất nhịp mạng, trễ quét QR,
   và cảm ứng không phản hồi. Viết đúng ngay từ đây thì lúc chép sang khỏi phải sửa. */
static uint8_t       g_bomBuoc = 0;    // 0 = rảnh · 1 = đã gửi mở đầu · 2 = đã gửi mã kênh
static unsigned long g_bomMoc  = 0;
static uint8_t       g_bomMa   = 0;

void bomMotTo(uint8_t ma) {
  if (g_bomBuoc) { Serial.println(F(">> đang bơm dở một tờ, chờ xong đã")); return; }
  g_bomMa = ma;
  Bus.write(ICT_MO_DAU);
  g_bomBuoc = 1;
  g_bomMoc  = millis();
  Serial.printf("%8lu ms  BƠM: %02X …\n", millis(), ICT_MO_DAU);
}

/** Gọi mỗi vòng loop() — đẩy nốt các byte còn lại khi tới nhịp. */
void bomTiep() {
  if (!g_bomBuoc) { return; }
  unsigned long nay = millis();
  if (g_bomBuoc == 1 && nay - g_bomMoc >= ICT_TRE_MA_MS) {
    Bus.write(g_bomMa);
    g_bomBuoc = 2; g_bomMoc = nay;
    Serial.printf("%8lu ms  BƠM: %02X (kênh %d)\n", nay, g_bomMa, g_bomMa - 0x40);
  } else if (g_bomBuoc == 2 && nay - g_bomMoc >= ICT_TRE_HET_MS) {
    Bus.write(ICT_KET_THUC);
    g_bomBuoc = 0;
    soBom++;
    Serial.printf("%8lu ms  BƠM: %02X — xong một tờ\n", nay, ICT_KET_THUC);
  }
}

void inTrangThai() {
  Serial.printf("[mã tờ tiền: %02X | %lu baud %s %s | chuyển tiếp: %s | nghe ICT %lu, đã bơm %lu]\n",
                maToTien, (unsigned long) tocDo, tenKhung(kieuKhung), daoCuc ? "ĐẢO" : "thuận",
                chuyenTiep ? "BẬT" : "tắt", soNgheIct, soBom);
}

void docLenh() {
  if (!Serial.available()) { return; }
  String d = Serial.readStringUntil('\n');
  d.trim();
  if (d.length() == 0) { return; }

  char c = d.charAt(0);
  if (c == 'b') {
    /* bNNN: bơm theo MỆNH GIÁ (b10, b50, b100 — nghìn đồng). Chỉ "b" thì dùng mã đang đặt. */
    String so = d.substring(1); so.trim();
    if (so.length()) {
      uint32_t ngan = (uint32_t) so.toInt();
      uint8_t  kenh = 0;
      for (uint8_t i = 0; i < SO_MENH_GIA; i++) {
        if (DS_MENH_GIA[i].tien == ngan * 1000UL) { kenh = DS_MENH_GIA[i].kenh; break; }
      }
      if (!kenh) {
        Serial.print(F(">> không có mệnh giá đó. Có:"));
        for (uint8_t i = 0; i < SO_MENH_GIA; i++) { Serial.printf(" b%lu", (unsigned long) (DS_MENH_GIA[i].tien / 1000) ); }
        Serial.println();
        inTrangThai();
        return;
      }
      maToTien = (uint8_t) (0x40 + kenh);
    }
    bomMotTo(maToTien);
  } else if (c == 'm') {
    maToTien = (uint8_t)strtol(d.substring(1).c_str(), nullptr, 16);
    Serial.printf(">> mã tờ tiền = %02X\n", maToTien);
  } else if (c == 'n') {
    daoNoi = !daoNoi;
    if (daoNoi) daoCuc = false;   // hai thứ chồng nhau thì rối; chọn một
    moCong();
  } else if (c == 'p') {
    kieuKhung = (uint8_t) ((kieuKhung + 1) % 3);
    moCong();
  } else if (c == 'i') {
    daoCuc = !daoCuc;
    moCong();
  } else if (c == 'v') {
    /* Đổi tốc độ để dò. 207 µs/bit ra đúng 4800, nhưng nếu khung vẫn vỡ thì đáng thử các
       tốc độ lân cận — bộ tạo nhịp của ICT có thể lệch. */
    uint32_t v = (uint32_t) d.substring(1).toInt();
    if (v >= 1200 && v <= 115200) { tocDo = v; moCong(); }
    else { Serial.println(">> tốc độ phải trong khoảng 1200..115200"); }
  } else if (c == 'c') {
    chuyenTiep = !chuyenTiep;
    Serial.printf(">> chuyển tiếp ICT → ghế: %s\n", chuyenTiep ? "BẬT" : "TẮT");
  } else if (c == 'r') {
    soNgheIct = soBom = 0;
    Serial.println(">> đã xoá bộ đếm");
  } else {
    Serial.println(F("\n  b10   bơm tờ 10.000đ  ·  b20 · b50 · b100"));
    Serial.println(F("  b     bơm bằng mã đang đặt"));
    Serial.println(F("  mXX   đổi mã tờ tiền, hex 2 chữ số"));
    Serial.println(F("  p     đổi khung: 8N1 → 8E1 → 8O1"));
    Serial.println(F("  i     đảo cực CẢ hai chiều"));
    Serial.println(F("  n     đảo RIÊNG chiều nói — bật khi đi qua transistor nâng mức 5V"));
    Serial.println(F("  vNNNN đổi tốc độ (vd v4800, v2400, v9600)"));
    Serial.println(F("  c     bật/tắt chuyển tiếp ICT → ghế"));
    Serial.println(F("  r     xoá bộ đếm\n"));
  }
  inTrangThai();
}

void setup() {
  Serial.begin(115200);
  delay(300);
  moCong();

  Serial.println(F("\n=== bơm tiền vào ghế ==="));
  Serial.printf("nghe ICT GPIO %d · nói vào ghế GPIO %d\n", CHAN_ICT, CHAN_GHE);
  Serial.println(F("Nạp một tờ tiền thật để xem mã của mệnh giá đó, rồi gõ 'b' để bơm lại."));
  inTrangThai();
}

void loop() {
  docLenh();
  bomTiep();

  while (Bus.available()) {
    uint8_t b = Bus.read();

    /* 0x00 KHÔNG BAO GIỜ là byte thật của ICT — bảng đã dò đủ: 81 · 41..44 · 10 · 25 · 29 · 2F.
       Nó là dấu của chân nghe THẢ NỔI: GPIO 34-39 không có điện trở kéo bên trong, không cắm gì
       vào là hứng nhiễu điện lưới, mỗi chu kỳ 50 Hz vượt ngưỡng một lần thì UART tưởng có bit
       start rồi đọc ra 00. Cứ 20 ms một byte, đều tăm tắp.

       Vứt thẳng, không đếm, không chuyển tiếp. Trước đây mỗi byte rác này được bơm nguyên vào
       ghế — 50 byte vô nghĩa mỗi giây. */
    if (b == 0x00) {
      soRac++;
      if (millis() - g_racBaoLuc > 3000) {
        g_racBaoLuc = millis();
        Serial.printf("%8lu ms  ⚠ %lu byte 00 — chân nghe GPIO %d đang THẢ NỔI (nhiễu 50 Hz).\n"
                      "            Cắm nó vào ICT qua chia áp, hoặc kéo lên 3V3 bằng trở 10k.\n"
                      "            Đã vứt hết, không byte nào lọt sang ghế.\n",
                      millis(), soRac, CHAN_ICT);
      }
      continue;
    }

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
