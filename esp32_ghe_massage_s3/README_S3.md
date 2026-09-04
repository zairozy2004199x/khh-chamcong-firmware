# Ghế QR trên Waveshare ESP32-S3-Touch-LCD-2.8B

Board mới: **ESP32-S3R8** (8MB PSRAM, 16MB flash), màn **ST7701 480×640 giao tiếp RGB**,
cảm ứng **GT911 (I2C)**, mở rộng GPIO **TCA9554**. Khác hẳn CYD (TFT_eSPI) và bản P4 (MIPI-DSI).

## Làm theo 2 bước (đừng gộp — tránh màn đen như CYD)

### BƯỚC 1 — Bring-up: chắc chắn MÀN + CẢM ỨNG chạy (file này)
`esp32_ghe_massage_s3.ino` chỉ thử màn (đổi màu + lưới 2×2) và cảm ứng (chạm → Serial + chấm).
Chưa có app ghế/QR/tiền. **Xong bước này (màn lên, chạm ăn) mới sang bước 2.**

### BƯỚC 2 — Ráp full app ghế (làm sau, khi báo màn đã lên)
Bê logic từ bản `esp32_ghe_massage_p4/` (đã port sẵn, board-độc-lập): 4G, QR, cổng tiền ICT,
outbox tiền mặt offline, NVS, OTA AP, chốt tiền offline (`/chotso` + `/chotmoc`). Chỉ thay lớp
vẽ màn sang ESP32_Display_Panel.

## Cài đặt Arduino
1. **Board**: `ESP32S3 Dev Module`
2. **PSRAM**: `OPI PSRAM`  ·  **Flash Size**: `16MB`  ·  **Partition**: `16M Flash (3MB APP/9.9MB FATFS)` hoặc tương đương
3. **Library Manager** → cài **`ESP32_Display_Panel`** (esp-arduino-libs, **>= 1.2.0**).
   Nó tự kéo theo `ESP32_Display_Panel` core + `esp_lcd` drivers.

## CHÂN — `esp_panel_board_custom_conf.h` (ĐÃ ĐIỀN từ schematic Waveshare)
Thư viện không có preset 2.8B nên khai custom. **Chân đã điền sẵn** từ bảng phân bổ GPIO trong
schematic chính thức:

| Nhóm | Giá trị đã điền |
|---|---|
| RGB điều khiển | HSYNC=**38**, VSYNC=**39**, DE=**40**, PCLK=**41** |
| RGB green (6 bit) | GPIO **9,10,11,12,13,14** (chắc chắn) |
| RGB blue+red (10 bit) | GPIO 3,5,8,17,18 (blue) · 21,45,46,47,48 (red) — 🟡 **thứ tự nhóm cần xác nhận qua màu** |
| Init SPI ST7701 | SDA=**GPIO1**, SCK=**GPIO2**, CS=**EXIO3** (qua TCA9554) |
| Đèn nền | **GPIO6** (PWM) |
| Cảm ứng GT911 | SCL=**7**, SDA=**15**, INT=**16**, RST=EXIO2 |
| SD | MOSI=1, SCLK=2, MISO=42, CS=EXIO4 |
| Expander/Còi | TCA9554 @0x20 · Còi=EXIO8 · LCD_RST=EXIO1 · TP_RST=EXIO2 |

> 🟡 **Chỉ còn 1 điều cần kiểm khi test:** thứ tự bit blue/red (đọc từ ảnh schematic hơi mờ).
> Nếu lên hình mà **đỏ↔xanh dương đảo** → đổi chéo cụm `DATA0..4` với `DATA11..15` trong file conf.
> Nếu 1 màu **loang/sai sắc** → đảo thứ tự trong cụm đó. **Không gây màn đen.**

## Đọc kết quả bring-up (Serial 115200)
| Hiện tượng | Nghĩa |
|---|---|
| Màn đổi ĐỎ→LỤC→LAM→TRẮNG + lưới 2×2, chạm ra chấm | ✅ màn + cảm ứng OK → báo em ráp app |
| Màn **đen** hẳn | sai chân RGB DATA/điều khiển, hoặc init ST7701 chưa chạy |
| Màn **sọc/trôi/lệch** | sai timing (HPW/HBP/HFP/VPW/VBP/VFP) hoặc PCLK |
| Lên hình **sai màu / âm bản** | đảo thứ tự DATA hoặc cần init ST7701 riêng của Waveshare |
| Chạm không ra | GT911: sai địa chỉ/INT, hoặc RST qua expander chưa nhả |

Nếu compile báo **thiếu macro**: chép nguyên bản gốc
`libraries/ESP32_Display_Panel/esp_panel_board_custom_conf.h` vào đây rồi chỉ sửa giá trị theo
bảng trên (bản gốc đủ mọi macro mặc định).

## Trạng thái
- [x] Bring-up màn + cảm ứng (file này) — **cần anh điền chân + nạp thử**
- [ ] Full app ghế QR (port từ _p4) — làm sau khi màn xác nhận lên
