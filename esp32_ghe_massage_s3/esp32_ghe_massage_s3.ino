/* ============================================================================
 *  GHẾ QR — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB + GT911)
 *  ⚠️ BƯỚC 1: BRING-UP — CHỈ THỬ MÀN + CẢM ỨNG (chưa có app ghế/QR/tiền).
 * ----------------------------------------------------------------------------
 *  VÌ SAO THỬ RIÊNG TRƯỚC: board này màn RGB ST7701, sai 1 chân trong ~20 chân
 *  RGB là màn đen/sọc. Nạp cái này để CHẮC CHẮN màn lên + cảm ứng ăn ĐÃ, rồi mới
 *  ráp full app ghế (bê logic từ bản _p4). Giống cách đã làm với CYD (test trước).
 *
 *  CHUẨN BỊ (xem README_S3.md):
 *    1. Arduino: Board = "ESP32S3 Dev Module", PSRAM = "OPI PSRAM", Flash = 16MB.
 *    2. Library Manager: cài  ESP32_Display_Panel  (>=1.2.0) của esp-arduino-libs.
 *    3. Chân RGB ĐÃ điền sẵn trong  esp_panel_board_custom_conf.h  (bóc từ schematic
 *       Waveshare). Chỉ cần xác nhận: nếu SAI MÀU thì đảo cụm Blue/Red (xem file conf).
 *    4. Nạp. Mở Serial 115200.
 *
 *  KẾT QUẢ MONG ĐỢI:
 *    · Màn đổi màu ĐỎ→LỤC→LAM→TRẮNG mỗi giây, rồi hiện 4 ô lưới + thanh dưới.
 *    · Chạm màn -> Serial in toạ độ + vẽ 1 chấm trắng tại chỗ chạm.
 *    · Màn ĐEN / SỌC -> sai chân RGB hoặc timing: soát lại conf. Màu SAI/ÂM BẢN ->
 *      cần dán init ST7701 của Waveshare (xem cuối file conf).
 * ========================================================================== */
#include <Arduino.h>
#include "esp_panel_board.h"

using namespace esp_panel::board;

Board*      board = nullptr;
static uint16_t* g_buf = nullptr;      // buffer vẽ hình chữ nhật (PSRAM)
static const int BUF_MAX = 480 * 300;  // đủ 1 ô lưới

/* RGB565 */
static inline uint16_t RGB(uint8_t r, uint8_t g, uint8_t b){
  return ((r & 0xF8) << 8) | ((g & 0xFC) << 3) | (b >> 3);
}

/* Vẽ 1 hình chữ nhật đặc màu (dùng buffer PSRAM, drawBitmap). */
void veHcn(int x, int y, int w, int h, uint16_t mau){
  if(!g_buf) return;
  int n = w * h;
  if(n > BUF_MAX) return;               // ô quá to -> bỏ (bring-up giữ đơn giản)
  for(int i = 0; i < n; i++) g_buf[i] = mau;
  board->getLCD()->drawBitmap(x, y, w, h, (uint8_t*)g_buf);
}

void veGiaoDienThu(){
  auto lcd = board->getLCD();
  lcd->fillColor(RGB(8, 16, 32));       // nền xanh đen
  // Thanh tiêu đề
  veHcn(0, 0, 480, 70, RGB(20, 40, 80));
  // Lưới 2×2 (giả bố cục chọn gói)
  const uint16_t mau[4] = { RGB(200,60,60), RGB(60,170,90), RGB(60,120,220), RGB(210,170,40) };
  int gx[2] = { 20, 250 }, gy[2] = { 90, 380 };
  int k = 0;
  for(int r = 0; r < 2; r++)
    for(int c = 0; c < 2; c++)
      veHcn(gx[c], gy[r], 210, 270, mau[k++]);
  // Thanh dưới
  veHcn(0, 660, 480, 40, RGB(20, 40, 80));
  Serial.println("[UI] da ve luoi 2x2 + thanh. Cham man de thu cam ung.");
}

void setup(){
  Serial.begin(115200);
  delay(300);
  Serial.println("\n\n=== GHE QR S3 (bring-up man + cam ung) ===");

  g_buf = (uint16_t*)heap_caps_malloc(BUF_MAX * sizeof(uint16_t), MALLOC_CAP_SPIRAM);
  if(!g_buf) g_buf = (uint16_t*)malloc(BUF_MAX * sizeof(uint16_t));
  Serial.printf("[MEM] buffer ve: %s\n", g_buf ? "OK (PSRAM)" : "THIEU RAM");

  board = new Board();
  if(!board->init()){ Serial.println("[LCD] init() LOI - soat lai conf/chan RGB"); }
  if(!board->begin()){ Serial.println("[LCD] begin() LOI - soat lai conf/chan RGB"); }
  Serial.println("[LCD] init xong. Neu man den -> sai chan RGB hoac timing.");

  // Thử màu toàn màn 4 nhịp cho chắc panel sáng
  auto lcd = board->getLCD();
  const uint16_t mauTest[4] = { RGB(255,0,0), RGB(0,255,0), RGB(0,0,255), RGB(255,255,255) };
  const char*    tenTest[4] = { "DO", "LUC", "LAM", "TRANG" };
  for(int i = 0; i < 4; i++){
    lcd->fillColor(mauTest[i]);
    Serial.printf("[TEST] full man: %s\n", tenTest[i]);
    delay(1000);
  }
  veGiaoDienThu();
}

void loop(){
  auto touch = board->getTouch();
  if(touch){
    uint8_t n = touch->readPoints();
    if(n > 0){
      auto pts = touch->getPoints();
      int tx = pts[0].x, ty = pts[0].y;
      Serial.printf("[CHAM] x=%d y=%d (so diem=%d)\n", tx, ty, n);
      veHcn(tx - 8, ty - 8, 16, 16, RGB(255, 255, 255));   // chấm trắng tại chỗ chạm
    }
  }
  delay(15);
}
