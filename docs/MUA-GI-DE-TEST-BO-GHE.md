# Mua một lần — đủ để dò xong và lắp thật một ghế

*Chốt 24/08/2026. Danh sách này CỐ Ý phủ cả ba khả năng điện áp (3.3V / 5V / 12V) và cả ba
họ giao thức (ccTalk / MDB / riêng của hãng), vì lúc mua thì chưa đo được — mà thiếu một món
là dừng cả tuần chờ hàng.*

---

## A. BẮT BUỘC

| # | Món | SL | Giá ước | Để làm gì |
|---|---|---|---|---|
| 1 | **ESP32 DevKit V1** (WROOM-32, 30 chân) | 2 | 100k/con | Con test. Mua 2: cháy một con lúc dò là còn con kia, khỏi dừng. **Không cần CYD.** |
| 2 | **Bộ phân tích logic 8 kênh USB** (bản sao Saleae) | 1 | ~100k | Xem mục B — món đáng tiền nhất trong cả danh sách. |
| 3 | **Module cách ly ADUM1201** | 3 | 50k/cái | UART hai chiều với bo ghế. Mua 3: 1 test, 1 lắp thật, 1 dự phòng. |
| 4 | **B0505S-1W** (DC-DC cách ly 5V→5V) | 3 | 20k/cái | Nguồn cho phía bo ghế. **Thiếu món này là cách ly giả** — xem `CACH-LY-BO-GHE.md` mục 3. |
| 5 | **PC817** rời | 10 | 3k/con | Đường xung tiền L70. Rẻ, hay cháy, mua dư. |
| 6 | **Điện trở 1/4W**: 100Ω, 1kΩ, 3.3kΩ, 10kΩ, 20kΩ | 10 mỗi loại | ~10k cả bộ | Chia áp cho 5V và 12V, lọc RC. |
| 7 | **Tụ**: 1nF (mã 102), 100nF (104), 10µF | 10 mỗi loại | ~15k cả bộ | Lọc nhiễu, kê nguồn. |
| 8 | **Cáp 4 lõi có lưới chống nhiễu** | 3 m | ~15k/m | Đường tín hiệu. Đừng dùng dây bù nhìn không lưới. |
| 9 | **Bo cắm test (breadboard) + dây cắm** | 1 bộ | ~60k | Ráp thử trước khi hàn. |

**Cộng khoảng 500k.** Trong đó ~300k là đồ dùng lại được mãi (ESP32, bộ phân tích logic,
breadboard); phần thật sự tiêu hao chỉ khoảng 200k.

## B. 🔴 VÌ SAO BỘ PHÂN TÍCH LOGIC LÀ MÓN ĐÁNG TIỀN NHẤT

Em đã viết bản `esp32_ghe_nghe_bo` để dò, và nó chạy được. Nhưng nói thật: **bộ phân tích
logic tốt hơn hẳn**, và với ~100k thì không có lý do gì không mua.

| | Bản ESP32 của em | Bộ phân tích logic |
|---|---|---|
| Đo tốc độ baud | Đoán bằng cách thử 7 mức | **Đo thẳng bề rộng bit → ra số chính xác** |
| Nghe hai chiều cùng lúc | Không (1 chân) | **Có, 8 kênh** |
| MDB 9-bit | Đọc bằng tay, có thể lệch | **Thấy từng bit, không đoán** |
| Thấy được sườn xung bẩn | Không | **Có — biết luôn có cần lọc nhiễu không** |
| Sai thì biết | Khó | Nhìn dạng sóng là biết |

Phần mềm dùng **PulseView (sigrok)** — miễn phí, có sẵn bộ giải mã UART. Cắm vào, chọn kênh,
nó tự in ra byte. Với việc "đoán sai một byte là ghế cộng tiền sai" thì đây là món bảo hiểm
rẻ nhất.

**⚠️ Bộ phân tích logic chịu tối đa 5V. Đường 12V phải chia áp trước, xem mục D.**

Bản ESP32 vẫn giữ — dùng để kiểm chéo, và để dò tại cửa hàng khi không mang laptop.

## C. KHÔNG MUA

- **Module PC817 cho UART** — sườn quá chậm so với bit 9600 baud, và tệ dần theo tuổi.
  Lý do đầy đủ ở `CACH-LY-BO-GHE.md` mục 4.
- **Module MAX3232 / RS232** — bo ghế gần như chắc chắn dùng mức logic (3.3V/5V), không phải
  RS232 thật ±12V. Mua về không dùng tới. Nếu dò ra đúng là RS232 thật thì lúc đó mua, 25k.
- **Bộ chuyển USB-TTL** — ESP32 DevKit đã có sẵn cổng USB nối tiếp.

## D. CHIA ÁP — tra bảng, khỏi tính

Đo áp TX của bo ghế so với mát bo ghế, lúc ghế đứng yên, rồi tra:

| Đo được | Làm gì | Ra bao nhiêu |
|---|---|---|
| **~3.3V** | Cắm thẳng | 3.3V |
| **~5V** | `TX ──[10k]──┬── vào ESP32`, `└──[20k]── mát` | 3.3V |
| **~12V** | `TX ──[10k]──┬── vào ESP32`, `└──[3.3k]── mát` | ~3.0V |
| **0V suốt** | Bo không gọi, hoặc sai chân — thử chân kia | — |

Lọc nhiễu, lắp ngay sau bộ chia: nối tiếp **100Ω** rồi **1nF** xuống mát.

## E. THỨ TỰ LÀM — mỗi bước có điều kiện dừng

1. **Đo áp** TX bo ghế ↔ mát bo ghế. Ghi lại con số.
2. **Đo chênh mát**: que đen ở mát ESP32, que đỏ ở mát bo ghế, **thang AC**.
   Trên 1–2 V AC → **đừng nối chung mát**, phải cách ly ngay cả khi dò.
3. **Dò**: cắm bộ phân tích logic (qua bộ chia nếu cần) vào TX bo ghế. Bật ghế. Ghi 30 giây.
   Chưa ra byte nào thì chạy thêm bản `esp32_ghe_nghe_bo` để kiểm chéo.
4. **Gửi kết quả** — dạng sóng hoặc phần in ra. Em viết phần ESP32 đóng vai cục nhận tiền.
5. **Lắp cố định** với ADUM1201 + B0505S-1W, sau khi đã chạy được trên bàn.

🔴 **Bước 1–3 KHÔNG đụng gì tới ghế đang chạy tiền của khách.** Chỉ nghe, không nối vào chân
RX của bo ghế. Con ESP32 trên ghế cũng không nạp lại gì.

## F. Sau khi lắp thật vẫn giữ

- **Relay GPIO17** làm đường cắt cứng — xem `CACH-LY-BO-GHE.md` mục 5.
- **Đường xung L70 vào GPIO27** — chưa có lý do gì bỏ; nó đang chạy tốt.
