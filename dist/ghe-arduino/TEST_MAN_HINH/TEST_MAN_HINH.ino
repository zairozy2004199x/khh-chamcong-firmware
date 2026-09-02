// ============================================================================
//  TEST MÀN HÌNH CYD — tách khỏi firmware ghế để soi lỗi "màn đen".
//  ----------------------------------------------------------------------------
//  MỤC ĐÍCH: nạp sketch NGẮN này để biết PANEL + DRIVER có đúng không, khỏi
//  dính mấy thứ phức tạp (4G, tiền, touch) của firmware ghế.
//
//  CÁCH DÙNG:
//    1) Bảo đảm User_Setup.h của thư viện TFT_eSPI đã đúng (ST7789 cho bo 2 USB,
//       hoặc ILI9341_2_DRIVER cho bo 1 USB) — xem User_Setup_ST7789_2USB.h.
//    2) Mở sketch này, chọn board "ESP32 Dev Module", Upload.
//    3) Mở Serial Monitor 115200. Nó in mỗi giây "[TEST] dang chay ..." + đổi màu
//       nền: ĐỎ -> XANH LÁ -> XANH DƯƠNG -> TRẮNG -> ĐEN, có chữ to ở giữa.
//
//  ĐỌC KẾT QUẢ:
//    · Màn ĐỔI MÀU + chữ hiện  -> panel/driver ĐÚNG. Lỗi màn đen là do firmware
//      ghế (hoặc màu/invert). Báo em để chỉnh firmware.
//    · Màn VẪN ĐEN nhưng Serial IN "[TEST] dang chay" đều  -> MCU chạy, panel/driver
//      SAI: thử đổi ST7789 <-> ILI9341_2, và bật/tắt invert ở dưới (DAO_MAU).
//    · Serial KHÔNG in gì / in lặp liên tục từ đầu  -> nạp chưa vào / boot loop /
//      thiếu nguồn: thử cổng USB kia, giữ BOOT lúc nạp, đổi cáp USB.
//
//  NẾU LÊN HÌNH NHƯNG MÀU SAI:
//    · Âm bản (đỏ ra xanh ngọc...)  -> đổi DAO_MAU sang !DAO_MAU (true<->false).
//    · Đỏ/xanh dương đảo            -> sửa TFT_RGB_ORDER trong User_Setup.h.
// ============================================================================
#include <TFT_eSPI.h>

// Panel ghế thật cần đảo màu. Bo test thường KHÔNG. Đổi true/false nếu màu sai.
#define DAO_MAU false

#define BL_PIN 21           // đèn nền CYD

TFT_eSPI tft = TFT_eSPI();

const uint16_t MAU[]  = { TFT_RED, TFT_GREEN, TFT_BLUE, TFT_WHITE, TFT_BLACK };
const char*    TEN[]  = { "DO", "XANH LA", "XANH DUONG", "TRANG", "DEN" };
int i = 0;

void setup(){
  Serial.begin(115200);
  delay(200);
  Serial.println("\n\n=== TEST MAN HINH CYD ===");
  Serial.printf("Driver TFT_eSPI: xem User_Setup.h. DAO_MAU=%d\n", DAO_MAU);

  pinMode(BL_PIN, OUTPUT);
  digitalWrite(BL_PIN, HIGH);            // BẬT đèn nền
  Serial.println("[TEST] da bat den nen (GPIO21 HIGH). Neu man van toi thui -> sai chan den/nguon.");

  tft.init();
  tft.setRotation(1);                    // ngang 320x240
  tft.invertDisplay(DAO_MAU);
  tft.fillScreen(TFT_RED);
  tft.setTextDatum(MC_DATUM);
  tft.setTextColor(TFT_WHITE, TFT_RED);
  tft.drawString("MAN HINH OK", 160, 120, 4);
  Serial.println("[TEST] da goi tft.init() + fill DO + chu 'MAN HINH OK'.");
}

void loop(){
  uint16_t mau = MAU[i];
  tft.fillScreen(mau);
  tft.setTextDatum(MC_DATUM);
  // chữ tương phản: nền tối -> chữ trắng, nền sáng -> chữ đen
  uint16_t chu = (mau == TFT_WHITE) ? TFT_BLACK : TFT_WHITE;
  tft.setTextColor(chu, mau);
  tft.drawString(TEN[i], 160, 110, 4);
  tft.drawString("TEST MAN HINH", 160, 150, 2);

  Serial.printf("[TEST] dang chay - nen: %s\n", TEN[i]);
  i = (i + 1) % 5;
  delay(1000);
}
