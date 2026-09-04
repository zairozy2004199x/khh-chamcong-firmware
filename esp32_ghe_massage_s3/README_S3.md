# Ghế QR trên Waveshare ESP32-S3-Touch-LCD-2.8B

Màn **ST7701 RGB 480×640** (không phải TFT_eSPI). Bản này dùng **driver gốc đã test của
Waveshare** (esp_lcd, có sẵn trong ESP32 core) — **KHÔNG cần thư viện ngoài, KHÔNG cần file
config rời**. Mọi thứ nằm trong thư mục sketch.

## Cài đặt Arduino
- Board: **ESP32S3 Dev Module**
- **PSRAM: OPI PSRAM**  ·  **Flash Size: 16MB**  ·  Partition: mặc định (hoặc 16M)
- Không cài thêm thư viện nào (chỉ cần ESP32 core esp32 ≥ 3.x).

## Các file trong sketch (bê từ demo Arduino Waveshare, chỉ bỏ LVGL)
| File | Việc |
|---|---|
| `esp32_ghe_massage_s3.ino` | App ghế: lớp vẽ + chọn gói bằng cảm ứng |
| `Display_ST7701.cpp/.h` | Init ST7701 (SPI2) + panel RGB (esp_lcd) + `LCD_addWindow` + đèn nền |
| `TCA9554PWR.cpp/.h` | Mở rộng GPIO (CS/RST màn = EXIO3/EXIO1, còi = EXIO8) qua I2C |
| `I2C_Driver.cpp/.h` | Wire trên SDA=15, SCL=7 |
| `touch_cst328.h` | Cảm ứng CST328 (I2C 0x1A, thanh ghi 16-bit) — tự viết theo tài liệu, poll |
| `font_ascii.h` | Font 5×7 khử răng cưa |

## Chỉnh chiều cảm ứng (nếu chạm lệch)
Nếu chạm mà trúng sai ô, mở `touch_cst328.h` sửa 3 cờ đầu file:
`TOUCH_SWAP_XY` (đổi X↔Y), `TOUCH_MIRROR_X`, `TOUCH_MIRROR_Y` (lật trục) = 0/1.
Serial in `[TP] cham x=.. y=..` để so với chỗ ngón tay mà chỉnh.

## Nạp & kết quả
Mở `esp32_ghe_massage_s3.ino` → Upload → Serial 115200.
- Màn hiện **4 dải màu dọc**: ĐỎ / LỤC / LAM / TRẮNG → ✅ panel OK → báo để ráp full app ghế.
- Serial in `[S3] LCD_Init xong`. Nếu **đen** → chụp Serial. Nếu **sai màu** → chỉnh nhỏ.

## Bước sau (khi màn lên)
Ráp full app ghế QR trên `LCD_addWindow` + thêm cảm ứng (**CST328**, có trong demo Waveshare):
QR thanh toán, cổng tiền ICT, 4G, chốt offline — bê logic từ bản `_p4`.

## Chân (đã khớp schematic + demo Waveshare)
RGB: HSYNC38 VSYNC39 DE40 PCLK41 · data 5,45,48,47,21,14,13,12,11,10,9,46,3,8,18,17 ·
init SPI SDA1/SCK2 · CS=EXIO3 RST=EXIO1 (TCA9554) · đèn nền GPIO6 · I2C 15/7 · timing pclk30M
HPW10 HBP70 HFP60 VPW10 VBP20 VFP20.
