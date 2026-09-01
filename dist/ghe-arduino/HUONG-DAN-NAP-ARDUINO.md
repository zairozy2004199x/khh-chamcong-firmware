# Nạp firmware ghế massage bằng Arduino IDE

Bản: **ghe-massage 2026-09-01c** (màn chọn gói dựng lại theo tấm mẫu)

> 🔴 **Nạp vào con ESP32 RỜI, không phải con đang gắn trên ghế.** Bản này mới chỉ
> nhìn qua ảnh dựng, chưa ai thấy nó trên màn thật.

---

## 1. Cài Arduino IDE + hỗ trợ ESP32

1. Tải Arduino IDE 2.x: <https://www.arduino.cc/en/software>
2. Mở **File → Preferences → Additional boards manager URLs**, dán:
   ```
   https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
   ```
3. **Tools → Board → Boards Manager**, gõ `esp32`, cài **esp32 by Espressif Systems**
   phiên bản **3.3.10**.

   ⚠️ Ghim đúng 3.3.10. Máy dựng tự động của mình dùng bản này; đổi bản khác là
   có thể biên dịch được mà chạy khác — kiểu hỏng không ai kịp thấy.

## 2. Cài ba thư viện

**Tools → Manage Libraries**, cài đúng phiên bản:

| Thư viện | Phiên bản |
|---|---|
| ArduinoJson | 7.2.0 |
| TFT_eSPI | 2.5.43 |
| XPT2046_Touchscreen | 1.4.0 |

## 3. 🔴 Chép `User_Setup.h` vào thư viện TFT_eSPI — BƯỚC HAY BỊ QUÊN NHẤT

Chép tệp **`User_Setup.h`** trong gói này, **ĐÈ LÊN** tệp cùng tên trong thư mục
thư viện:

```
Windows:  C:\Users\<TÊN>\Documents\Arduino\libraries\TFT_eSPI\User_Setup.h
macOS:    ~/Documents/Arduino/libraries/TFT_eSPI/User_Setup.h
Linux:    ~/Arduino/libraries/TFT_eSPI/User_Setup.h
```

Bỏ qua bước này thì **nạp vẫn xong nhưng màn hình trắng hoặc đen** — sai chân và
sai driver, không có thông báo lỗi nào cả.

Màn vẫn trắng sau khi nạp: mở `User_Setup.h`, đổi `ILI9341_2_DRIVER` thành
`ILI9341_DRIVER`, hoặc `ST7789_DRIVER` (kèm `TFT_RGB_ORDER TFT_BGR`) — CYD có mấy
đời tấm nền khác nhau. Sửa ở tệp này, đừng sửa chỗ khác.

## 4. Điền `secrets.h`

Trong thư mục `esp32_ghe_massage/`, chép `secrets.example.h` thành **`secrets.h`**
rồi điền:

- `SEC_WIFI_SSID` / `SEC_WIFI_PASS` — WiFi để thử trên bàn
- `SEC_WP_URL` — địa chỉ website, **không có dấu `/` ở cuối**
- `SEC_WP_KEY` — phải **khớp hệt** `VHG_KHOA_MAY` trong `wp-config.php`

> Mã ghế **không** nằm ở đây. Ghế tự khai MAC, máy chủ nói nó là ghế số mấy — nên
> một bản firmware dùng cho mọi ghế. Ghế mới cắm điện sẽ hiện ở mục "chờ gán"
> trên web.

## 5. Chọn board và nạp

**Tools** đặt đúng như máy dựng tự động:

| Mục | Giá trị |
|---|---|
| Board | ESP32 Dev Module |
| CPU Frequency | 240MHz |
| Flash Frequency | 80MHz |
| Flash Mode | QIO |
| Flash Size | 4MB (32Mb) |
| Partition Scheme | Default 4MB with spiffs |
| PSRAM | Disabled |
| Core Debug Level | None |

Cắm cáp USB, chọn **Tools → Port**, bấm **Upload** (mũi tên →).

Không vào được chế độ nạp thì **giữ nút BOOT** lúc bấm Upload, thả ra khi thấy
dòng `Connecting...`.

---

## Nếu vướng

| Hiện tượng | Xem chỗ này |
|---|---|
| Màn trắng / đen sau khi nạp | Bước 3 — `User_Setup.h` chưa chép, hoặc sai driver |
| `A fatal error occurred: Failed to connect` | Giữ nút BOOT khi bấm Upload; hoặc cáp USB chỉ có 2 dây sạc |
| Không thấy cổng COM | Thiếu driver CH340 / CP2102 |
| Biên dịch lỗi thiếu thư viện | Bước 2 — thiếu một trong ba thư viện |
| Ghế hiện "GHE CHUA DUOC GAN MA" | Bình thường: vào web → Ghế Massage → Máy & cơ sở, gán mã cho MAC hiện trên màn |
