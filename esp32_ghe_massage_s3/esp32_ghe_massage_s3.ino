/* ============================================================================
 *  GHẾ QR — Waveshare ESP32-S3-Touch-LCD-2.8B (480×640, ST7701 RGB)
 *  ⚠️ BƯỚC 1: BRING-UP MÀN — dùng DRIVER GỐC ĐÃ TEST của Waveshare.
 * ----------------------------------------------------------------------------
 *  Không cần thư viện ngoài (TFT_eSPI / ESP32_Display_Panel / Arduino_GFX) — màn
 *  ST7701 RGB chạy bằng esp_lcd có sẵn trong ESP32 core. Các file kèm theo lấy
 *  NGUYÊN từ demo Arduino chính hãng Waveshare (LVGL_Arduino), chỉ bỏ phần LVGL:
 *      Display_ST7701.cpp/.h  — init ST7701 (SPI2) + panel RGB + LCD_addWindow + đèn nền
 *      TCA9554PWR.cpp/.h       — mở rộng GPIO (CS/RST màn, còi) qua I2C
 *      I2C_Driver.cpp/.h       — Wire trên SDA=15, SCL=7
 *
 *  CÀI ĐẶT ARDUINO:
 *      Board = "ESP32S3 Dev Module" · PSRAM = "OPI PSRAM" · Flash = 16MB
 *      (KHÔNG cần cài/đặt file config gì thêm — mọi thứ nằm trong thư mục sketch.)
 *
 *  KẾT QUẢ: màn hiện 4 DẢI MÀU dọc theo chiều cao: ĐỎ / LỤC / LAM / TRẮNG.
 *      · Đúng màu -> panel + chân RGB OK -> báo em ráp full app ghế.
 *      · Đen -> báo Serial. Sai màu -> chỉnh nhỏ (ít khi, vì đây là code Waveshare).
 * ========================================================================== */
#include <Arduino.h>
#include "I2C_Driver.h"
#include "TCA9554PWR.h"
#include "Display_ST7701.h"

#define LCD_W  480
#define LCD_H  640
#define CHUNK  40                    // vẽ theo khối 40 hàng (480*40*2 = 38 KB/lần)

static uint16_t* g_buf = nullptr;    // buffer nguồn để đẩy vào panel (PSRAM)

static inline uint16_t RGB(uint8_t r, uint8_t g, uint8_t b){
  return ((r & 0xF8) << 8) | ((g & 0xFC) << 3) | (b >> 3);
}

/* Tô 1 hình chữ nhật màu đặc — đẩy theo khối cho nhẹ RAM. */
void toHcn(int x, int y, int w, int h, uint16_t mau){
  if(!g_buf) return;
  for(int yy = y; yy < y + h; yy += CHUNK){
    int rows = min(CHUNK, y + h - yy);
    for(int i = 0; i < w * rows; i++) g_buf[i] = mau;
    LCD_addWindow(x, yy, x + w - 1, yy + rows - 1, (uint8_t*)g_buf);   // Xend/Yend inclusive
  }
}

void setup(){
  Serial.begin(115200);
  delay(300);
  Serial.println("\n\n=== GHE QR S3 (bring-up man ST7701 RGB - driver Waveshare) ===");

  I2C_Init();                        // Wire SDA=15 SCL=7
  TCA9554PWR_Init(0x00);             // expander: tất cả OUTPUT
  Set_EXIO(EXIO_PIN8, Low);          // tắt còi
  Backlight_Init();                  // đèn nền GPIO6 (PWM)
  Serial.println("[S3] I2C + expander + den nen OK, dang init LCD...");
  LCD_Init();                        // ST7701 reset(EXIO1)+init(CS EXIO3) + panel RGB
  Serial.println("[S3] LCD_Init xong. Neu man den -> bao Serial.");

  g_buf = (uint16_t*)heap_caps_malloc((size_t)LCD_W * CHUNK * 2, MALLOC_CAP_SPIRAM);
  if(!g_buf) g_buf = (uint16_t*)malloc((size_t)LCD_W * CHUNK * 2);
  Serial.println(g_buf ? "[S3] buffer ve OK" : "[S3] THIEU RAM buffer ve");

  // 4 dải màu dọc theo chiều cao (mỗi dải 160px) — kiểm panel + màu RGB
  toHcn(0,   0, LCD_W, 160, RGB(255,0,0));    Serial.println("[VE] DO");
  toHcn(0, 160, LCD_W, 160, RGB(0,255,0));    Serial.println("[VE] LUC");
  toHcn(0, 320, LCD_W, 160, RGB(0,0,255));    Serial.println("[VE] LAM");
  toHcn(0, 480, LCD_W, 160, RGB(255,255,255)); Serial.println("[VE] TRANG");
  Serial.println("[S3] Da ve 4 dai mau. Xong bring-up neu man dung mau.");
}

void loop(){
  // Nhấp nháy đèn nền nhẹ để biết còn sống (không đổi màn)
  static uint32_t t = 0;
  if(millis() - t > 2000){ t = millis(); Serial.println("[S3] dang chay..."); }
  delay(50);
}
