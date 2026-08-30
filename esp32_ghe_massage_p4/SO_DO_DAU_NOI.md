# Sơ đồ đấu nối — bo GUITION JC4880P443C (ESP32-P4 + C6) cho ghế QR

Bản port từ `esp32_ghe_massage` (bo CYD). Chọn chân từ **header JP1** (chân trống theo
ảnh pinout anh gửi 30/08/2026). Chân đã chốt nằm trong `cau_hinh_p4.h`.

---

## 1. Chân JP1 còn trống & cách chia

```
Trống theo ảnh:  52  51  50  49  35*  34  32  28  33  31  30  29
Header RS485:    26 (TX)   27 (RX)
Nguồn JP1:       3V3    5V    GND
(* 35 = nút BOOT / strapping — chỉ để dự phòng làm INPUT, KHÔNG dùng làm output)
```

Cách gom cụm để dây gọn:

| Cụm            | Chân dùng     | Chân dự phòng |
|----------------|---------------|---------------|
| 4G A7680C      | 32, 33, 34    | —             |
| Cổng tiền ICT  | 49, 50        | 26/27 (RS485) |
| Điều khiển     | 51 (relay), 52 (bypass) | —   |
| Dò xung ghế    | 29            | 30, 31, 28, 35 |

Chân bo ĐÃ CHIẾM (đừng đụng): màn DSI reset **5** / đèn nền **23**; cảm ứng+audio I²C
**7/8**, GT911 reset **3**; audio ES8311 **9,10,12,13,48**, amp **11**; thẻ nhớ
**39–44**; WiFi C6 SDIO **14–19,54**; console UART0 **37/38**.

---

## 2. Sơ đồ khối

```
                         ┌──────────────────────────────────┐
                         │   BO GUITION JC4880P443C (P4+C6)  │
                         │                                   │
   ┌── Màn 480×800 DSI ──┤ (5,23  — bo lo)                   │
   ┌── GT911 cảm ứng  ───┤ (7,8,3 — bo lo)                   │
   ┌── WiFi/4G ── C6 ────┤ (SDIO  — bo lo)                   │
                         │                                   │
   A7680C 4G ──── RXD ◄──┤ GPIO32  (SIM_TX)                  │
              ──── TXD ──►│ GPIO33  (SIM_RX)                  │
              ── PWRKEY ◄─┤ GPIO34  (SIM_PWRKEY)              │
              (nguồn 4V/≥2A riêng · GND chung)               │
                         │                                   │
   Bo ICT ─────── TX ───►│ GPIO49  (TIEN_RX_ICT) 4800 8E1    │
   (cổng tiền)           │ GPIO50  (TIEN_TX_GHE) ──► RX ghế  │
                         │                                   │
   Relay CHẠY GHẾ ── IN ◄┤ GPIO51  (RELAY_PIN, kích LOW)     │
   Relay BYPASS  ── IN ◄─┤ GPIO52  (BYPASS_PIN, kích LOW)    │
                         │                                   │
   Dò "ghế chạy" ─chia áp►│ GPIO29  (GHECHAY_PIN, chạy=LOW)  │
                         │                                   │
   3V3 / 5V / GND ───────┤ nguồn logic                       │
                         └──────────────────────────────────┘
```

---

## 3. Đấu từng ngoại vi

### 3.1 Module 4G A7680C (giữ 4G — mall không có WiFi ổn)
| A7680C | ↔ | Bo P4 |
|--------|---|-------|
| RXD    | ← | GPIO32 (ESP TX) |
| TXD    | → | GPIO33 (ESP RX) |
| PWRKEY | ← | GPIO34 (xung LOW ~1s bật/tắt) |
| GND    | — | GND chung với bo |
| VBAT   | — | **Nguồn 4.0V/≥2A RIÊNG** (buck 5V→4V, đỉnh dòng ~2A lúc phát) |

- UART A7680C mức 3V3 → nối thẳng, **không cần dịch mức**.
- ⚠️ ĐỪNG cấp VBAT từ 3V3 của bo — sụt áp, brownout. Dùng nguồn riêng, chỉ chung GND.
- Code: `Serial2.begin(115200, SERIAL_8N1, P4_SIM_RX_PIN, P4_SIM_TX_PIN)`.

### 3.2 Cổng tiền (bo ICT) — SERIAL 4800 8E1
| Bo ICT | ↔ | Bo P4 |
|--------|---|-------|
| TX (81/4X/10) | → | GPIO49 (TIEN_RX_ICT) |
| RX (nhận tiền của **ghế**) | ← | GPIO50 (TIEN_TX_GHE) |
| GND | — | GND chung |

- Bo ICT TTL 3V3 → đấu **thẳng GPIO**, KHÔNG qua transceiver RS485 (26/27).
- Nếu bo ghế thật sự chạy **RS485**: dời cổng tiền sang header 26/27 và sửa
  `P4_TIEN_RX_ICT=27`, `P4_TIEN_TX_GHE=26` trong `cau_hinh_p4.h`.
- Xử lý byte giữ nguyên `cong_tien.h` (đã ưu tiên đọc 2 macro trên).

### 3.3 Relay chạy ghế + relay bypass fail-safe
| Module relay | ↔ | Bo P4 |
|--------------|---|-------|
| IN relay chạy ghế | ← | GPIO51 (RELAY_PIN) |
| IN relay bypass   | ← | GPIO52 (BYPASS_PIN) |
| VCC | — | 5V bo (hoặc nguồn relay riêng) |
| GND | — | GND chung |

- Module relay opto phổ biến **kích mức THẤP** → giữ `*_ACTIVE_HIGH=false`. Nếu module
  của anh kích mức CAO thì đổi thành `true`.
- **Fail-safe:** đấu tiền đi qua tiếp điểm **NC** (thường-đóng) của relay bypass → mất
  điện / lúc bo đang boot thì tiền vẫn đi thẳng vào ghế, không kẹt tiền khách.
- `setup()` phải kéo 2 chân về mức TẮT **trước khi** enable, tránh ghế tự chạy lúc cấp nguồn.

### 3.4 Dò xung "ghế đang chạy"
| Điểm ghế | ↔ | Bo P4 |
|----------|---|-------|
| Điểm báo ghế chạy | →(chia áp)→ | GPIO29 (GHECHAY_PIN) |
| GND | — | GND chung |

- Điện áp điểm dò thường > 3V3 → **BẮT BUỘC chia áp** về ≤3.3V trước khi vào GPIO
  (ví dụ 10k trên / 10k dưới nếu điểm dò ~6V; tính lại theo áp thật). Có thể thêm
  diode kẹp + tụ nhỏ chống nhiễu.
- Logic như bản cũ: **chạy = LOW, tắt = HIGH**. Dùng để chốt (gate) đồng hồ đếm ngược:
  server chỉ tính giờ khi ghế thực sự chạy.

---

## 4. Nguồn

- **Logic bo + relay + ICT + dò xung:** 5V/GND từ JP1 (hoặc nguồn 5V chung).
- **A7680C:** nguồn 4V/≥2A **riêng**, chỉ chung GND.
- **Bo P4:** cấp đủ qua **cả hai cổng USB-C** (hoặc nguồn 5V ≥2A) — thiếu dòng gây
  brownout (đã gặp lúc bring-up).
- **Một điểm GND chung** cho tất cả (bo, 4G, ICT, relay, ghế) — thiếu GND chung là
  lỗi UART/đọc xung chập chờn khó tìm.

---

## 5. Trạng thái an toàn lúc bật nguồn

| Chân | Lúc reset | Yêu cầu |
|------|-----------|---------|
| GPIO51 relay chạy | hi-Z → kéo TẮT trong `setup()` | ghế KHÔNG tự chạy |
| GPIO52 bypass | hi-Z; tiền đi qua tiếp điểm NC | tiền vẫn thẳng khi mất điện |
| GPIO34 PWRKEY | để mức nghỉ, chỉ xung khi bật 4G | không tự tắt/bật SIM |
| GPIO35 (BOOT) | strapping | KHÔNG dùng làm output |
