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

## ĐIỀN CHÂN (bắt buộc) — `esp_panel_board_custom_conf.h`
Thư viện **không có preset cho 2.8B**, nên phải khai tay. Mở file `esp_panel_board_custom_conf.h`
(cạnh .ino) và điền các chỗ ghi `ĐIỀN`. Lấy số từ **Wiki Waveshare ESP32-S3-Touch-LCD-2.8B →
Hardware/Schematic**, hoặc file demo Arduino của họ (thường `pins_config.h` / `Display_ST7701.*`).

| Nhóm | Macro | Lấy ở đâu |
|---|---|---|
| RGB điều khiển | `..._IO_HSYNC/_VSYNC/_DE/_PCLK` | bảng chân LCD trong wiki |
| RGB dữ liệu | `..._IO_DATA0..DATA15` (B0..B4,G0..G5,R0..R4) | **chép đúng thứ tự**, đảo là sai màu/trắng |
| ST7701 init SPI | `..._SPI_IO_CS/_SCK/_SDA` (là chân **TCA9554**, 0..7) | wiki/demo |
| Đèn nền | `..._BACKLIGHT_IO` (chân TCA9554) | wiki/demo |
| Cảm ứng GT911 | đã điền sẵn: SDA15 / SCL7 / INT16 | silk trên board (anh chụp) |
| Expander | `..._EXPANDER_I2C_ADDRESS` 0x20 (hoặc 0x38) | wiki |

> Các phần đã điền sẵn (I2C 15/7, INT 16, timing gần đúng, độ phân giải 480×640) là suy từ silk
> board + chuẩn ST7701 — nếu wiki khác thì sửa lại.

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
