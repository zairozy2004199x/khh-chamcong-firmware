/* ============================================================================
 *  GHẾ QR — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB + GT911)
 *  ⚠️ BƯỚC 1: BRING-UP — CHỈ THỬ MÀN + CẢM ỨNG (chưa có app ghế/QR/tiền).
 * ----------------------------------------------------------------------------
 *  Dùng API ESP32_Display_Panel v1.x (esp-arduino-libs). Header + cách gọi lấy
 *  từ ví dụ chính thức board_static_config của thư viện.
 *
 *  CHUẨN BỊ (xem README_S3.md):
 *    1. Arduino: Board = "ESP32S3 Dev Module", PSRAM = "OPI PSRAM", Flash = 16MB.
 *    2. Library Manager: cài  ESP32_Display_Panel  (>=1.0) của esp-arduino-libs.
 *    3. Chân RGB đã điền sẵn (bóc từ schematic) trong esp_panel_board_custom_conf.h.
 *    4. Nạp. Mở Serial 115200.
 *
 *  KẾT QUẢ MONG ĐỢI:
 *    · Màn hiện CÁC DẢI MÀU (colorBarTest) — đỏ/lục/lam... đúng màu = chân RGB OK.
 *    · Chạm màn -> Serial in toạ độ x,y.
 *    · Màn ĐEN -> begin() lỗi / sai chân RGB (xem Serial). Màu SAI -> init ST7701.
 * ========================================================================== */
#include <Arduino.h>
#include <vector>
#include <esp_display_panel.hpp>

using namespace esp_panel::board;
using namespace esp_panel::drivers;

Board* board = nullptr;

void setup(){
  Serial.begin(115200);
  delay(300);
  Serial.println("\n\n=== GHE QR S3 (bring-up man + cam ung) ===");

  board = new Board();
  bool ok = board->begin();               // begin() tự init bus + LCD + touch + expander
  Serial.println(ok ? "[LCD] begin() OK" : "[LCD] begin() LOI - soat lai conf/chan RGB");

  auto lcd = board->getLCD();
  if(lcd){
    Serial.println("[LCD] colorBarTest -> man phai hien cac DAI MAU");
    lcd->colorBarTest();                  // vẽ dải màu: chứng minh panel + chân RGB
  } else {
    Serial.println("[LCD] getLCD() = null");
  }

  auto touch = board->getTouch();
  Serial.println(touch ? "[TP] co GT911 - cham man de thu (toa do ra Serial)"
                       : "[TP] KHONG thay touch");
}

void loop(){
  auto touch = board->getTouch();
  if(touch){
    touch->readRawData(-1, -1, 20);
    std::vector<TouchPoint> points;
    touch->getPoints(points);
    for(auto& p : points){
      Serial.printf("[CHAM] x=%d y=%d\n", p.x, p.y);
    }
  }
  delay(20);
}
