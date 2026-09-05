# Ghế QR trên Waveshare ESP32-S3-Touch-LCD-2.8B

Màn **ST7701 RGB 480×640** (không phải TFT_eSPI). Dùng **driver gốc đã test của Waveshare**
(esp_lcd, có sẵn trong ESP32 core). Cảm ứng thực tế trên board là **GT911** (địa chỉ I2C
**0x14**), KHÔNG phải CST328 như tài liệu ghi — driver tự nhận diện cả hai.

## Cài đặt Arduino
- Board: **ESP32S3 Dev Module**
- **PSRAM: OPI PSRAM**  ·  **Flash Size: 16MB**  ·  **USB CDC On Boot: Enabled** (để có Serial)
- Thư viện cần cài (Library Manager): **QRCode** (Richard Moore) — dùng vẽ mã VietQR.
  Ngoài ra chỉ cần ESP32 core ≥ 3.x (driver màn/cảm ứng nằm sẵn trong sketch).

## Các file trong sketch
| File | Việc |
|---|---|
| `esp32_ghe_massage_s3.ino` | App ghế: lớp vẽ + chọn gói (cảm ứng) + hiện QR VietQR |
| `Display_ST7701.cpp/.h` | Init ST7701 (SPI2) + panel RGB (esp_lcd) + `LCD_addWindow` + đèn nền |
| `TCA9554PWR.cpp/.h` | Mở rộng GPIO (CS/RST màn = EXIO3/EXIO1, còi = EXIO8) qua I2C |
| `I2C_Driver.cpp/.h` | Wire trên SDA=15, SCL=7 |
| `touch_cst328.h` | Cảm ứng: tự nhận diện **GT911**(0x14/0x5D) hoặc CST328(0x1A), thanh ghi 16-bit |
| `font_ascii.h` | Font 5×7 khử răng cưa |

## Lưu ý cảm ứng GT911 (board này)
- TCA9554 **dùng hết 8 chân**; `Init(0x00)` kéo tất cả LOW → **giữ TP_RST của GT911** ⇒ touch
  chết. `setup()` kéo EXIO 2/4/5/6/7 lên HIGH để **nhả reset** rồi mới đọc.
- Toạ độ GT911 chip này bắt đầu ngay tại `0x8150` (không có byte track-id đầu):
  `x = p[0]|p[1]<<8`, `y = p[2]|p[3]<<8` (little-endian). Đo thực: trên-trái (23,17),
  dưới-phải (461,618) — khớp 480×640, đúng chiều. Chỉnh chiều (nếu cần) bằng
  `TOUCH_SWAP_XY/MIRROR_X/MIRROR_Y` đầu `touch_cst328.h`.

## Thanh toán (VietQR) — PLACEHOLDER
`BANK_BIN / ACCOUNT_NO / ND_TIEN_TO / CHAIR_ID` ở đầu `.ino` đang là **placeholder** (repo công
khai, không đặt số TK thật). Sau sẽ nạp từ web/NVS như bản `_p4`.

## Nạp & kết quả (Stage C)
Mở `.ino` → Upload → chạm 1 gói ở lưới 2×2 → màn hiện **QR VietQR thật** (số tiền + nội dung
CK "POSH GHE01 <mã>") + nút **HUỶ**. Mở app ngân hàng quét thử để kiểm mã dựng đúng.

## Bước sau
Nhận tiền (cổng ICT / 4G / WiFi) → chạy phiên đếm ngược → NVS + chốt tiền offline — bê logic
từ bản `_p4` (`cong_tien.h`, `net_4g.h`, `outbox.h`).

## Chân (khớp schematic + demo Waveshare)
RGB: HSYNC38 VSYNC39 DE40 PCLK41 · data 5,45,48,47,21,14,13,12,11,10,9,46,3,8,18,17 ·
init SPI SDA1/SCK2 · CS=EXIO3 RST=EXIO1 (TCA9554) · đèn nền GPIO6 · I2C 15/7 · timing pclk30M
HPW10 HBP70 HFP60 VPW10 VBP20 VFP20.
