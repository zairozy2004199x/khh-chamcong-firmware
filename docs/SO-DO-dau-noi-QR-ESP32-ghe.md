# Sơ đồ đấu nối: mạch QR ↔ ESP32 ↔ bo ghế (bản mới nhất)

> Lấy đúng theo firmware `esp32_posh_qr/esp32_posh_qr.ino` + `ict_ghe.h` (bản đang dùng).
> ESP32 đóng vai "máy nhận tiền": khách quét QR hợp lệ → ESP32 bảo bo ICT của ghế "cho chạy N phút".

## 0. Khối tổng quát

```
   [Module QR]            [ ESP32 DevKit 30 chân ]            [ Bo ICT của ghế ]
   GM65/GM805     UART1        (3,3V)              UART2   (qua CÁCH LY ADuM1201)
   TTL 9600  ───────────────►  RX GPIO25                     TX bo ghế
             ◄───────────────  TX GPIO26                     RX bo ghế
                                                    32/33 ──► ADuM1201 ──► HT245(5V) ──► MCU ghế
   Báo hiệu:  Còi GPIO22 · Đèn xanh GPIO21 · Đèn đỏ GPIO19 · Nút BOOT GPIO0
   Nguồn:     ESP32 ăn nguồn RIÊNG (5V/USB→3V3), KHÔNG lấy 5V của ghế.
```

## 1. Bảng chân ESP32 (đúng như `.ino`)

| Chức năng | Chân ESP32 | Nối tới | Ghi chú |
|---|---|---|---|
| **ICT RX** (nhận) | **GPIO32** | ◄── TX bo ghế (qua ADuM1201) | UART2, 9600 baud |
| **ICT TX** (gửi) | **GPIO33** | ──► RX bo ghế (qua ADuM1201) | chéo nhau, không TX-TX |
| **QR RX** (nhận) | **GPIO25** | ◄── TX module quét mã | UART1, 9600 baud |
| **QR TX** (gửi) | **GPIO26** | ──► RX module quét mã | |
| QR TRIG | −1 | (không đấu) | −1 = module tự quét liên tục |
| Còi | **GPIO22** | ──► còi (loại có dao động sẵn) | cấp điện là kêu |
| Đèn XANH | **GPIO21** | ──► LED báo mở ghế OK | qua trở ~330Ω |
| Đèn ĐỎ | **GPIO19** | ──► LED báo mã hỏng | qua trở ~330Ω |
| Nút | **GPIO0** | nút BOOT sẵn trên bo | giữ lúc cắm điện → vào chế độ cầu nối UART |
| HT245 OE / DIR | −1 / −1 | (không đấu) | dùng ADuM1201 nên bỏ, xem §3 |

> ⚠️ 32/33 **cố ý trùng** với firmware cầu nghe-lén (`esp32_cau_ict`) để nạp qua lại **không phải đấu lại dây**.

## 2. Module QR (GM65 / GM805 / barcode TTL) — mức điện áp

- Baud mặc định **9600** (khớp `QR_BAUD_MD`).
- Module thường cấp **5V**, chân TX ra mức 5V:
  - **Module TX (5V) → ESP32 RX (GPIO25):** phải **chia áp** (1kΩ nối tiếp + 2kΩ xuống mass) hoặc level shifter — chân ESP32 KHÔNG chịu 5V.
  - **ESP32 TX (GPIO26, 3,3V) → Module RX:** thường chạy thẳng (3,3V đủ mức HIGH).
- GND module ─── GND ESP32 (chung mass phía này).

## 3. Bo ICT của ghế — BẮT BUỘC cách ly bằng ADuM1201

Bo ICT đẩy UART qua chip đệm **HT245 (74HC245) chạy VCC 5V**. ESP32 3,3V **không nói thẳng** được, và mô-tơ ghế đá nhiễu ngược về nguồn → dùng **cách ly số ADuM1201** (2 kênh, mỗi chiều 1 kênh):

```
   ESP32 (3,3V)              ADuM1201                 Bo ghế (5V)
   ─────────                 ────────                 ───────────
   3V3   ───────────────────  VDD1        VDD2  ─────  5V  (chân 20 HT245)
   GND   ───────────────────  GND1        GND2  ─────  GND (chân 10 HT245)
   GPIO33 (TX) ────────────►  VIA ─kênh A─► VOA ─────► đầu VÀO HT245
   GPIO32 (RX) ◄────────────  VOB ◄─kênh B─ VIB ◄───── đầu RA  HT245

   Tụ 0,1µF sát VDD1 và sát VDD2 + thêm 10µF đệm (BẮT BUỘC, không phải tuỳ chọn).
```

**Bốn điều sống-còn:**
1. ⚠️⚠️ **GND1 và GND2 KHÔNG nối với nhau.** Nối lại thì mạch VẪN chạy nên không ai phát hiện — nhưng vứt sạch phần cách ly. Kéo theo: **ESP32 ăn nguồn RIÊNG**, không lấy 5V của ghế.
2. ⚠️ Phải là **ADuM1201** (2 kênh ngược chiều), **KHÔNG phải ADuM1200** (2 kênh cùng chiều → UART không chạy).
3. ⚠️ **Tụ lọc bắt buộc** — thiếu tụ chạy chập chờn, triệu chứng giống hệt sai baud.
4. Baud bo ghế mặc định **9600**, khung nhị phân (`ICT_CHE_NHI_PHAN`) — chỉnh được qua lệnh USB (`BAUD`, `KIEU`).

> Nếu bo ghế là **RS232 (cổng DB9, ±12V)**: BẮT BUỘC qua **MAX3232**, cắm thẳng vào ESP32 là **cháy chân ngay**. Không chắc loại gì thì đo TX bo lúc nghỉ so với mass: ~3,3/5V = TTL; âm (−5..−12V) = RS232.

## 4. Chân ESP32 CẤM dùng cho UART (để khỏi hỏng khi làm mạch thật)

- GPIO 6–11: dính flash → dùng là chip không boot.
- GPIO 34–39: chỉ VÀO được, không làm TX.
- GPIO 1, 3: cổng USB (Serial0) — mất log.
- GPIO 0, 2, 5, 12, 15: quyết định kiểu boot; GPIO12 kéo lên còn làm hỏng flash 1,8V.
- GPIO 16, 17: an toàn ở WROOM nhưng WROVER bị PSRAM chiếm → tránh.

## 5. Dò lỗi (gõ qua cổng USB, 115200 baud)

`DAY` (đo mức nghỉ RX) · `TUKIEM` (khép TX→RX tự kiểm) · `DO`/`BAUD` (dò/đặt baud) ·
`CAU` (nối thẳng USB ↔ bo ghế) · `MO <phút>` / `DUNG` (thử mở/dừng ghế) · `TT` (xem trạng thái).

## Tham chiếu mã nguồn
- `esp32_posh_qr/esp32_posh_qr.ino` — định nghĩa chân (`CHAN_ICT_*`, `CHAN_QR_*`, còi/đèn/nút).
- `esp32_posh_qr/ict_ghe.h` — đấu dây + cách ly ADuM1201 + HT245 + mức điện áp.
- `esp32_posh_qr/quet_qr.h` — đọc module QR qua UART.
