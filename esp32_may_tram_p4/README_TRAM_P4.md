# Máy thu tiền / kỹ thuật — bản P4 (GUITION JC4880P443C)

Bản port của `esp32_may_tram` (bo CYD) sang bo **ESP32-P4 + C6** (màn 4.3" 480×800
MIPI-DSI, cảm ứng GT911, 4G A7680C). Dùng lại nền tảng màn/cảm ứng/font của
`esp32_ghe_massage_p4`.

## Ba việc (menu chính)
1. **Kiểm tra chỉ số máy (CHỈ XEM):** dò AP `POSH_QR-<mã>` của ghế để lấy mã →
   4G đăng nhập web (PIN, `api=login`) → `api=chot_xem` → hiện **cơ sở, tiền mặt hệ
   thống ghi nhận, chỉ số lần trước, đơn giá**. Không ghi gì.
2. **Nạp firmware ghế:** nối AP `POSH_QR-<mã>` (mật khẩu mặc định 12345678) → POST
   `firmware.bin` THÔ lên `192.168.4.1/update` kèm header `X-OTA-Key` (đúng
   `otaPhucVu()` của firmware ghế).
3. **Nạp firmware máy chấm công:** nối AP `ChamCong-<cơ sở>` → POST **multipart**
   `firmware.bin` lên `192.168.4.1/update` kèm **Basic-Auth** (admin/mật khẩu).

## Mạng
- **WiFi (C6)** CHỈ dùng để nối AP máy đích lúc nạp firmware.
- **Internet đi 4G A7680C** (việc kiểm tra chỉ số). Hai việc tách theo thời gian —
  bật 4G thì tắt WiFi và ngược lại (không chạy song song).

## Chuẩn bị
- **Thẻ microSD** chứa `firmware.bin` (đặt tên đúng `FW_PATH` = `/firmware.bin`).
  Muốn nạp ghế thì chép .bin của ghế; nạp chấm công thì chép .bin chấm công (đổi thẻ
  hoặc đổi file giữa 2 lần nạp).
- **`secrets.h`** (chép từ `secrets.example.h`, KHÔNG commit): `SEC_WEB_BASE`,
  mật khẩu AP + tài khoản `/update` của máy chấm công. Khoá ghế mặc định 12345678.
- **Chân**: xem `cau_hinh_tram_p4.h`. 4G ở 32/33 + PWRKEY 34 (nguồn 4V riêng, GND
  chung). Thẻ SD SDMMC 43/44/39 — ⚠️ **đối chiếu pad bo** trước khi dùng (thứ tự
  CLK/CMD/D0 tuỳ layout).

## Thư viện / board
- Board **ESP32P4 Dev Module**, PSRAM **Enabled**, Flash 16MB, USB CDC On Boot **Enabled**.
- Thư viện: **ArduinoJson**. (Màn/cảm ứng/4G/SD dùng driver sẵn của core P4.)

## Lưu ý
- ⚠️ Chưa compile được ở máy build (không có toolchain P4) — nạp Arduino IDE, lỗi
  dòng nào báo để sửa.
- OTA an toàn: máy đích dùng `Update.begin/end` — nạp lỗi/dở thì GIỮ firmware cũ.
- Firmware **ghế bản P4 hiện chưa có** cổng OTA AP (mới có OTA-qua-4G trong kế
  hoạch). Chức năng "nạp firmware ghế" ở đây nạp cho **ghế có cổng AP `POSH_QR-*`**
  (bản CYD, hoặc bản P4 sau khi thêm `otaPhucVu`). Cần thì báo để thêm OTA-AP cho
  ghế P4.
