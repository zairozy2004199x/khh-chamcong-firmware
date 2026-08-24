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
 *   f        bật/tắt bộ lọc byte — tắt khi dò máy lạ, in nguyên xi tất cả
 *   s        CHỈ NGHE — không khai chân đẩy ra nào; dùng khi kẹp vào hệ đang chạy tốt
 *   t        TỰ KIỂM: bơm rồi đọc lại chính mình, kết luận mạch đúng hay sai
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
/* CHỈ NGHE: mở cổng với TX = -1, tức KHÔNG khai chân đẩy ra nào cả. Dùng khi kẹp con dò vào
   một hệ ĐANG CHẠY TỐT — bo hãng khác, ghế đang bán hàng — nơi đẩy nhầm một byte là phá hỏng
   thứ đang hoạt động. Tháo dây ở chân 26 vẫn là lớp bảo vệ chính, cái này là lớp thứ hai cho
   ca quên tháo. Bật/tắt bằng lệnh s. */
static bool     chiNghe = false;

/* BỘ LỌC BYTE. Bật thì chỉ byte có trong bảng ICT mới đi tiếp — sinh ra để chặn nhiễu đổ sang
   ghế. Nhưng khi DÒ một máy lạ thì chính nó là thứ cản: bảng của mình dò từ ICT của ghế mình,
   máy hãng khác nói khác là bị vứt sạch, và người dò chỉ thấy vài byte rời rạc rồi tưởng đường
   hỏng. Tắt bằng lệnh f — in nguyên xi mọi byte, gọn theo hàng. */
static bool     locByte = true;

static bool     daoNoi = true;      /* MẶC ĐỊNH BẬT: đấu dây đã chốt là qua transistor NPN, mà
   transistor thì đảo tín hiệu — nên chiều đúng luôn là chiều đảo. Để mặc định tắt thì mỗi lần
   nạp lại là đường nói nằm thấp suốt, và người ngồi thử phải nhớ gõ n. Đã vấp đúng vậy hai lần
   trong một buổi. Nối thẳng không qua transistor thì gõ n một lần để tắt. */

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
  Bus.begin(tocDo, khungCua(kieuKhung), CHAN_ICT, chiNghe ? -1 : CHAN_GHE, daoCuc);
  /* begin() chỉ nhận một cờ đảo cho cả hai chiều. Muốn đảo riêng chiều nói thì gọi thẳng
     xuống tầng dưới, sau khi cổng đã mở. Gọi với 0 để xoá khi tắt. */
  /* UART_NUM_1 chứ không phải số 1: tham số này kiểu uart_port_t, truyền số trần là
     trình dịch từ chối thẳng (invalid conversion from 'int' to 'uart_port_t'). Phải khớp
     đúng cổng đã mở ở HardwareSerial Bus(1). */
  if (daoNoi) uart_set_line_inverse(UART_NUM_1, UART_SIGNAL_TXD_INV);
  else if (!daoCuc) uart_set_line_inverse(UART_NUM_1, UART_SIGNAL_INV_DISABLE);
  if (chiNghe) {
    Serial.printf(">> cổng: %lu baud %s · 🔒 CHỈ NGHE — không khai chân đẩy ra nào\n",
                  (unsigned long) tocDo, tenKhung(kieuKhung));
  } else {
    Serial.printf(">> cổng: %lu baud %s · cực %s · chiều nói %s\n",
                  (unsigned long) tocDo, tenKhung(kieuKhung),
                  daoCuc ? "ĐẢO" : "thuận", daoNoi ? "ĐẢO (qua transistor)" : "thuận");
  }
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
/* Bảng byte thật của ICT, dò 24/08/2026 bằng tờ tiền thật:
      81            mở đầu khung báo tiền
      41..44        mệnh giá — kênh 1=10k · 2=20k · 3=50k · 4=100k
      10            kết thúc: ĐÃ NUỐT, có tiền
      29            nhả tờ ra / khách giựt lại: KHÔNG có tiền
      25            kẹt tờ
      2F            sẵn sàng lại
   Byte lạ vẫn được IN ra để còn dò tiếp, nhưng không bao giờ được chuyển tiếp sang ghế. */
static bool laByteIct(uint8_t b){
  if (b == ICT_MO_DAU || b == ICT_KET_THUC) return true;
  if (b >= 0x41 && b <= 0x44) return true;
  if (b == 0x25 || b == 0x29 || b == 0x2F) return true;
  return false;
}

static uint32_t soRac = 0;            // byte 00 do chân nghe thả nổi — đã vứt
static unsigned long g_racBaoLuc = 0;
static uint32_t g_racLuoc = 0;   // số rác ở lần báo trước — để tính được NHỊP, không chỉ tổng // chặn nhịp báo, 3 giây một lần cho khỏi tràn màn hình


/* ——— Bơm một tờ: ba byte, đúng nhịp ———
   Dùng MÁY TRẠNG THÁI chứ không delay(1200). Bản thử thì delay cũng chạy, nhưng mã này sẽ
   chép sang firmware ghế chính — ở đó chặn vòng lặp 1,2 giây là mất nhịp mạng, trễ quét QR,
   và cảm ứng không phản hồi. Viết đúng ngay từ đây thì lúc chép sang khỏi phải sửa. */
static uint8_t       g_bomBuoc = 0;    // 0 = rảnh · 1 = đã gửi mở đầu · 2 = đã gửi mã kênh
static unsigned long g_bomMoc  = 0;
static uint8_t       g_bomMa   = 0;

/* ——— TỰ KIỂM (lệnh t) ———
   Nối chân nghe vào ĐÚNG điểm ra của tầng nâng mức, rồi bơm một tờ và đọc lại chính mình.
   Trả lời được câu "cái gì THẬT SỰ ra tới dây" mà không cần ghế hợp tác chút nào.

   Trong lúc tự kiểm phải TẮT bộ lọc byte: bộ lọc sinh ra để chặn nhiễu đổ sang ghế, nhưng
   nó cũng giấu luôn tiếng vọng sai — mà tiếng vọng sai chính là thứ cần nhìn. Lần thử
   18:07 vấp đúng chỗ này: đọc về vài byte lọt bảng, cạnh 9877 byte bị vứt, nên không phân
   biệt được tiếng vọng thật với nhiễu trúng số. */
static bool          g_tuKiem   = false;
static unsigned long g_tuKiemMoc = 0;
static uint8_t       g_vong[48];
static uint8_t       g_soVong  = 0;
static uint8_t       g_tuKiemMa = 0;
static uint8_t       g_tuKiemLan = 0;   // 0 = chiều đang đặt · 1 = đã tự lật thử chiều kia

void bomMotTo(uint8_t ma) {
  if (chiNghe) { Serial.println(F(">> đang ở chế độ CHỈ NGHE — gõ s để mở lại chiều nói")); return; }
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

void inTrangThai();   /* khai trước: tuKiemTiep() gọi nó mà nó nằm bên dưới. Arduino IDE tự
                        sinh prototype nên vẫn dịch được, nhưng đó là MAY chứ không phải đúng —
                        g++ từ chối thẳng, và phép dịch thử bắt được ngay. */

/** Bắt đầu một lượt tự kiểm: dọn sạch bộ đệm vào, bơm một tờ, rồi thu mọi byte vọng về. */
void tuKiem(uint8_t ma) {
  if (g_bomBuoc) { Serial.println(F(">> đang bơm dở một tờ, chờ xong đã")); return; }
  while (Bus.available()) { Bus.read(); }     // vứt nhiễu đã đọng, khỏi lẫn vào kết quả
  g_soVong = 0;
  g_tuKiemMa = ma;
  g_tuKiemLan = 0;
  g_tuKiem = true;
  g_tuKiemMoc = millis();
  Serial.printf("\n=== TỰ KIỂM === sẽ bơm %02X %02X %02X rồi đọc lại chính mình.\n"
                "    Chân nghe phải nối vào ĐÚNG điểm ra của transistor (qua chia áp 1k/2k).\n",
                ICT_MO_DAU, ma, ICT_KET_THUC);
}

/** Gọi mỗi vòng loop() khi đang tự kiểm: thu byte, và kết luận khi hết giờ. */
void tuKiemTiep() {
  if (!g_tuKiem) { return; }
  while (Bus.available()) {
    uint8_t b = (uint8_t) Bus.read();
    if (g_soVong < sizeof(g_vong)) { g_vong[g_soVong++] = b; }
  }
  /* Bơm mất 1,2 giây; chờ thêm nửa giây cho byte cuối vọng về. */
  if (millis() - g_tuKiemMoc < ICT_TRE_HET_MS + 500) { return; }
  g_tuKiem = false;

  Serial.printf("    mong đọc lại:  %02X %02X %02X\n", ICT_MO_DAU, g_tuKiemMa, ICT_KET_THUC);
  Serial.print(F("    thật sự đọc:  "));
  if (!g_soVong) { Serial.print(F("(không byte nào)")); }
  for (uint8_t i = 0; i < g_soVong; i++) { Serial.printf(" %02X", g_vong[i]); }
  Serial.println();

  /* Khớp theo THỨ TỰ, cho phép nhiễu chen giữa — dây hở vẫn có thể lẫn gai vào. */
  const uint8_t mong[3] = { ICT_MO_DAU, g_tuKiemMa, ICT_KET_THUC };
  uint8_t k = 0;
  for (uint8_t i = 0; i < g_soVong && k < 3; i++) { if (g_vong[i] == mong[k]) { k++; } }

  if (k == 3) {
    Serial.println(F("    ✓ ĐỌC LẠI ĐÚNG CẢ BA BYTE — mạch nâng mức và chiều đảo đều đúng."));
    Serial.println(F("      Tín hiệu ra tới dây là thật. Ghế không ăn thì lỗi ở phía ghế,"));
    Serial.println(F("      không phải ở mình: sai chân, hoặc ghế cần điều kiện khác.\n"));
  } else if (g_tuKiemLan == 0) {
    /* TỰ LẬT CHIỀU RỒI THỬ LẠI. Bắt người ngồi thử phải nhớ gõ n sau mỗi lần nạp là thiết kế
       tồi — lần nạp nào cũng đặt lại về mặc định, và đúng chuyện đó vừa xảy ra lúc 18:12.
       Máy tự thử được cả hai chiều trong ba giây thì không có lý do gì bắt người làm. */
    daoNoi = !daoNoi;
    Serial.printf("    … chưa ra. Tự lật chiều nói sang %s rồi thử lại.\n\n",
                  daoNoi ? "ĐẢO (qua transistor)" : "thuận");
    moCong();
    while (Bus.available()) { Bus.read(); }
    g_soVong = 0;
    g_tuKiemLan = 1;
    g_tuKiem = true;
    g_tuKiemMoc = millis();
    return;                      // chưa kết luận, chờ lượt hai
  } else if (!g_soVong) {
    Serial.println(F("    ✗ KHÔNG đọc được gì, cả hai chiều. Chân nghe chưa nối vào điểm ra"));
    Serial.println(F("      của transistor, hoặc tầng nâng mức không dẫn."));
    Serial.println(F("      Kiểm chân B/C/E: 8050 là E–B–C, C1815 là E–C–B, hai con khác nhau.\n"));
  } else {
    Serial.printf("    ✗ ĐỌC RA RÁC ở CẢ HAI chiều (khớp %d/3 byte). Không phải chuyện chiều đảo.\n", k);
    Serial.println(F("      Toàn FF/FE/00 là chân nghe thả nổi — chưa nối vào điểm ra thật."));
    Serial.println(F("      Nối rồi mà vẫn rác thì soát chia áp 1k/2k và mát chung.\n"));
  }
  inTrangThai();
}

void inTrangThai() {
  /* chiều nói PHẢI có trong dòng gọn này. Nó chỉ hiện lúc mở cổng thì người thử không biết
     lệnh n đang bật hay tắt — gõ n chẵn số lần là về thuận mà nhìn dòng gọn không thấy. */
  Serial.printf("[mã tờ tiền: %02X | %lu baud %s %s | nói: %s | chuyển tiếp: %s | nghe ICT %lu, đã bơm %lu]\n",
                maToTien, (unsigned long) tocDo, tenKhung(kieuKhung), daoCuc ? "ĐẢO" : "thuận",
                chiNghe ? "🔒 KHOÁ (chỉ nghe)" : (daoNoi ? "ĐẢO (transistor)" : "thuận"),
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
  } else if (c == 'f') {
    locByte = !locByte;
    Serial.printf("\n>> bộ lọc byte: %s\n", locByte ? "BẬT (chỉ byte trong bảng ICT)"
                                                      : "TẮT — in nguyên xi mọi byte");
  } else if (c == 's') {
    chiNghe = !chiNghe;
    if (chiNghe) chuyenTiep = false;   // chỉ nghe thì không chuyển tiếp được, khỏi hiểu nhầm
    moCong();
  } else if (c == 't') {
    tuKiem(maToTien);
    return;                       // đừng in trạng thái đè lên phần đầu bản tự kiểm
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
    Serial.println(F("  f     bật/tắt bộ lọc byte — TẮT khi dò máy lạ, in nguyên xi tất cả"));
    Serial.println(F("  s     CHỈ NGHE / mở lại chiều nói — bật khi kẹp vào hệ ĐANG CHẠY TỐT"));
    Serial.println(F("  t     TỰ KIỂM: bơm một tờ rồi đọc lại chính mình (nối chân nghe vào"));
    Serial.println(F("        điểm ra của transistor qua chia áp) — biết ngay mạch đúng chưa"));
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

  /* Đang tự kiểm thì tuKiemTiep() nuốt hết byte vào — không lọc, không chuyển tiếp, không in
     từng dòng. Cả bản tự kiểm gói trong một khối, đọc một lần là hiểu. */
  if (g_tuKiem) { tuKiemTiep(); return; }

  while (Bus.available()) {
    uint8_t b = Bus.read();

    /* CHỈ CHO QUA BYTE CÓ TRONG BẢNG. Chặn từng con nhiễu một là đuổi mãi không hết: vứt 00
       xong thì FF lọt, vứt FF xong sẽ tới FE, F8… Gai từ chân thả nổi ra được đủ hình thù.
       Bảng ICT đã dò đủ nên lật ngược lại được: cái gì không có trong bảng thì không phải ICT.

       GPIO 34-39 không có điện trở kéo bên trong, không cắm gì vào là hứng nhiễu điện lưới —
       cứ 20 ms một byte rác, đều tăm tắp theo chu kỳ 50 Hz. */
    /* TẮT LỌC = in nguyên xi, gọn theo hàng. Đường im quá 150 ms thì xuống dòng và đóng mốc
       giờ mới — nhìn là thấy ngay đâu là một cụm, đâu là khoảng nghỉ. Đó là thứ quan trọng
       nhất khi dò một giao thức chưa biết: nhịp nói lên nhiều hơn giá trị từng byte. */
    if (!locByte) {
      static unsigned long lanCuoi = 0;
      static uint8_t demHang = 0;
      unsigned long nay = millis();
      if (nay - lanCuoi > 150 || demHang >= 16) { Serial.printf("\n%8lu ms  ", nay); demHang = 0; }
      lanCuoi = nay; demHang++; soNgheIct++;
      /* VẪN PHẢI CHUYỂN TIẾP. Tắt lọc là để NHÌN, không phải để ngắt đường. Thiếu dòng này thì
         bật f xong ghế không nhận được gì, và người thử tưởng đường bơm hỏng — trong khi lỗi
         nằm ở đây. Đúng lúc ESP32 ngồi giữa thì ngắt đường là ghế mất luôn tiền mặt. */
      if (chuyenTiep && !chiNghe) { Bus.write(b); }
      Serial.printf("%02X%s ", b, (chuyenTiep && !chiNghe) ? ">" : "");
      continue;
    }

    if (!laByteIct(b)) {
      soRac++;
      /* TỰ TẮT CHUYỂN TIẾP khi rác quá nhiều. Bộ lọc chặn được byte lạ, nhưng nhiễu bắn ra đủ
         256 giá trị nên lâu lâu vẫn trúng vào bảng — và ba lần trúng đúng thứ tự 81 → mã kênh
         → 10 là ghế cộng tiền khống. Đã thấy 81, 41, 42, 44, 10 lọt qua trong log 18:08.
         Rác tới mức này thì chân nghe chắc chắn chưa nối vào ICT, nên chẳng có gì đáng chuyển
         tiếp cả. Bơm tay bằng b10 vẫn chạy bình thường — chỉ chặn đường tự động. */
      /* Chỉ tự cắt khi CHƯA HỀ nghe được byte ICT thật nào. Đấu xong mà lâu lâu dính một
         gai nhiễu là chuyện thường; cắt đường tiền mặt vì mấy cái gai đó thì khách nhét tiền
         vào ghế không chạy — hỏng nặng hơn nhiều so với thứ đang phòng. Có byte thật rồi tức
         là dây đã nối đúng chỗ, không còn là ca "chân thả nổi" nữa. */
      if (chuyenTiep && soRac > 500 && soNgheIct == 0) {
        chuyenTiep = false;
        Serial.printf("%8lu ms  ⛔ TỰ TẮT chuyển tiếp: %lu byte rác, chân nghe chưa nối vào ICT.\n"
                      "            Nhiễu trúng bảng ba lần đúng thứ tự là ghế cộng tiền khống.\n"
                      "            Nối chân nghe xong thì gõ c để bật lại.\n", millis(), soRac);
      }
      if (millis() - g_racBaoLuc > 3000) {
        /* BÁO THEO NHỊP, ĐỪNG KHẲNG ĐỊNH "THẢ NỔI". Chân thả nổi bắn ra hơn trăm byte mỗi
           giây; vài chục byte một giây thì nhiều khả năng là DỮ LIỆU THẬT của một máy nói
           khác bảng của mình — bộ lọc vứt rồi đếm nhầm thành rác. Nhãn đặt sai hại hơn không
           đặt: tối nay đã có một nhãn dắt cả hai đi kiểm dây trong khi dây không sao. */
        unsigned long dt = millis() - g_racBaoLuc;
        unsigned long nhip = dt ? (soRac - g_racLuoc) * 1000UL / dt : 0;
        g_racBaoLuc = millis();
        g_racLuoc = soRac;
        if (nhip > 100) {
          Serial.printf("%8lu ms  ⚠ %lu byte/giây ngoài bảng — nhịp này là chân nghe THẢ NỔI.\n"
                        "            Kiểm dây, chia áp, mát chung. Không byte nào lọt sang ghế.\n",
                        millis(), nhip);
        } else {
          Serial.printf("%8lu ms  · %lu byte/giây ngoài bảng (tổng %lu) — nhịp thấp, nhiều khả\n"
                        "            năng là DỮ LIỆU THẬT của máy nói khác bảng. Gõ f xem nguyên xi.\n",
                        millis(), nhip, soRac);
        }
      }
      continue;
    }

    soNgheIct++;
    Serial.printf("%8lu ms  ICT: %02X", millis(), b);
    if (chuyenTiep && !chiNghe) {
      Bus.write(b);
      Serial.print("   → chuyển tiếp vào ghế");
    } else {
      Serial.print("   (nuốt, không chuyển tiếp)");
    }
    Serial.println();
  }
}
