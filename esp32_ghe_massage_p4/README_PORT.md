# Port bo QR massage sang GUITION JC4880P443C (ESP32-P4 + C6)

Bản gốc: `esp32_ghe_massage/` (bo **ESP32-2432S028 / CYD** + A7680C 4G + relay).
Đích: **JC4880P443C** — ESP32-P4 + ESP32-C6 (WiFi6), màn **4.3″ 480×800 ST7701S MIPI-DSI**,
cảm ứng **GT911 (I²C)**, 32M PSRAM / 16M Flash, có mic + loa (ES8311).

Nguyên tắc anh Thắng 30/08/2026: **"tái sử dụng kiểu bản cũ hết"** — giữ NGUYÊN toàn bộ logic,
chỉ thay lớp phần cứng. Mạng: **giữ 4G A7680C** (mall không có WiFi ổn).

---

## 1. Tái dùng NGUYÊN (không đụng logic)
- `cong_tien.h` — cổng tiền serial 4800 8E1 (đã bê vào thư mục này). Chỉ đổi chân qua `cau_hinh_p4.h`.
- Toàn bộ **logic nghiệp vụ** trong `.ino` cũ: chọn gói → dựng nội dung VietQR → chờ tiền vào
  (webhook/nhịp) → đóng relay + đếm ngược → dò xung ghế → hết giờ ngắt; giao thức server
  (nhịp, chốt ca, ghekhongchay), 4G A7680C qua UART, OTA, secrets/NVS.
- `qrcode.h` (thư viện QR) — tạo ma trận QR như cũ; chỉ khác cách **vẽ ra màn**.

## 2. VIẾT LẠI (lớp phần cứng — khác hẳn CYD)
| Khối | Bản cũ (CYD) | Bản P4 |
|---|---|---|
| Màn | `TFT_eSPI` ILI9341 SPI + font VLW `font_viet.h` | `esp_lcd` MIPI-DSI ST7701S + **LVGL** (font Việt của LVGL) |
| Cảm ứng | `XPT2046` (điện trở, SPI) | **GT911** (điện dung, I²C 7/8, addr 0x5D) |
| WiFi | WiFi tích hợp (AP OTA) | **C6 qua ESP-Hosted/SDIO** (arduino-esp32 ≥ 3.3.6) |
| Vẽ QR | `tft.drawPixel` từng ô | LVGL canvas / `lv_canvas` từ ma trận QR |

~225 chỗ gọi `tft.`/touch trong .ino cũ → gom về một lớp `man_hinh` (API mỏng: `veQR()`,
`veChu()`, `xoaMan()`, `chamO()`) để phần logic gọi mà không biết nền màn — nhờ vậy logic bê nguyên.

## 3. Chân cắm
Tất cả chân ngoại vi nằm trong **`cau_hinh_p4.h`** (mọi thứ là `#define`, "thích nào dùng nấy").
Trong đó liệt kê rõ **chân BO ĐÃ CHIẾM** (DSI/C6-SDIO/I²C/SD/audio/console) — đừng lấy làm ngoại vi.
Các `-1` là **chưa gán**, phải điền chân thật (đối chiếu pad bo) trước khi build.

## 4. Thư viện / môi trường
- **arduino-esp32 ≥ 3.3.6** (target ESP32-P4) hoặc ESP-IDF 5.5.
- **WiFi qua C6:** thành phần ESP-Hosted (nạp firmware C6 kèm — xem field notes bo).
- **LVGL** + **esp_lcd (MIPI-DSI)** cho ST7701S (khởi tạo DSI thủ công — stock `esp_lcd_st7701`
  chưa chuẩn cho panel này, theo field notes).
- **GT911** (I²C).

## 5. Bring-up theo GIAI ĐOẠN (nạp thử trên bo thật từng bước)
> Firmware này KHÔNG test được trên máy build của Claude (không có bo/toolchain P4). Làm từng
> giai đoạn, mỗi bước anh nạp + báo kết quả, rồi mới qua bước sau.

1. **GĐ1 — Bring-up phần cứng:** màn hiện chữ + bắt chạm GT911 + C6 nối WiFi + mở Serial1/Serial2.
   Mục tiêu: xác nhận nền màn/cảm ứng/WiFi/UART chạy trước khi bê logic vào.
2. **GĐ2 — Lớp `man_hinh` (LVGL):** vẽ QR + màn chọn gói + cảnh báo, thay cho các `tft.*`.
3. **GĐ3 — Bê logic:** chép nguyên máy trạng thái + server comms + cổng tiền + relay + dò xung từ
   .ino cũ, nối vào `man_hinh` và `cau_hinh_p4.h`.
4. **GĐ4 — 4G + OTA + secrets/NVS:** dựng lại y bản cũ trên UART của P4.
5. **GĐ5 — Chạy thử máy thật:** quét QR → tiền vào → ghế chạy → hết giờ ngắt; đối chiếu doanh thu.

## 6. Đang chờ để làm tiếp
- Điền **chân thật** vào `cau_hinh_p4.h` (4G UART/PWRKEY, cổng tiền RX/TX, relay/bypass, dò xung) —
  đối chiếu pad/header bo. Nếu pad ra ngoài ít, dùng **IC mở rộng I²C** cho relay + đọc xung.
- Xác nhận cổng tiền & bo ghế là **UART TTL hay RS485** (bo có sẵn RS485 26/27).
