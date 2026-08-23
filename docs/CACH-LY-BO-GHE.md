# Cách ly & chống nhiễu cho đường nối ESP32 ↔ bo ghế

*Ghi 24/08/2026 — cho việc ESP32 đóng vai cục nhận tiền trên hai chân RX/TX của bo ghế.*

---

## 1. Hai chuyện khác nhau, đừng gộp

| | **Cách ly** (galvanic isolation) | **Chống nhiễu** (noise immunity) |
|---|---|---|
| Chống cái gì | Hai mát khác điện thế; xung dội từ mô-tơ; sự cố 220V | Sườn xung bẩn làm sai bit |
| Hỏng thì sao | **Chết ESP32, có khi giật người** | Đọc sai byte, ghế cộng nhầm tiền |
| Giải bằng | Opto tốc độ cao / IC cách ly số **+ nguồn cách ly** | Cáp xoắn, tụ lọc, RC nhỏ, đi dây tách khỏi mô-tơ |

Làm cái thứ hai mà bỏ cái thứ nhất thì vẫn chết chip. Làm cái thứ nhất mà nguồn hai bên
vẫn chung thì **chưa cách ly gì cả** — xem mục 3.

## 2. Vì sao ca NÀY thật sự cần

- **Ghế có mô-tơ.** Con lăn, bơm hơi — chổi than và cuộn cảm dội ngược. Dòng đóng/cắt lớn làm
  mát nảy lên vài volt. UART 3.3V không chịu nổi mức nảy đó.
- **Đang có relay là đang có cách ly, và chuyển sang nối dây là MẤT nó.** Tiếp điểm khô vốn
  cách ly hoàn toàn. Đây là bước lùi về an toàn, phải bù lại.
- **ESP32 này còn giữ sổ tiền.** Nó chết giữa phiên không chỉ là ghế dừng — là mất luôn đợt
  tiền mặt đang chờ ghi sổ.
- **Ghế cắm 220V.** Bo ghế có biến áp riêng. Mát của nó và mát ESP32 **không có gì bảo đảm cùng
  điện thế**, và chênh lệch đó đi qua sợi dây tín hiệu.

## 3. 🔴 CÁCH LY MÀ CHUNG NGUỒN LÀ KHÔNG CÁCH LY

Đây là chỗ hay hỏng nhất, và nhìn bên ngoài vẫn thấy "đã lắp mạch cách ly".

IC cách ly có hai bên, mỗi bên cần nguồn riêng: `VCC1/GND1` và `VCC2/GND2`. Lấy cả hai bên
cùng một cục 5V là **hai mát lại chập vào nhau qua đường nguồn** — con IC vẫn chạy, tín hiệu
vẫn qua, mà rào cách ly thì không tồn tại.

Hai cách làm cho đúng:

- **Cách A — hai nguồn rời.** ESP32 dùng adapter riêng của nó; bên kia của IC lấy 5V **từ bo
  ghế**. Không nối mát hai bên với nhau ở bất cứ đâu. Đơn giản nhất, không tốn thêm gì.
- **Cách B — DC-DC cách ly.** Vẫn một adapter, nhưng bên bo ghế lấy điện qua **B0505S-1W**
  (5V→5V cách ly, ~15–25k). Dùng khi không kéo được nguồn từ bo ghế.

## 4. Mua gì — theo từng đường tín hiệu

### Đường UART với bo ghế (2 chiều, 9600 baud)

**Nên nhất: module ADUM1201** — IC cách ly số 2 kênh, ~40–70k.
- Hai kênh ngược chiều nhau, vừa đúng TX + RX, một bo là xong.
- Nhanh hơn UART hàng chục lần, không phải tính điện trở, không lo hệ số truyền đạt tụt theo
  tuổi như opto.
- Chịu được mức chênh vài trăm volt giữa hai mát.

**Rẻ hơn: module opto 6N137, 2 kênh** — ~20–30k.
- 6N137 là opto **logic tốc độ cao** (tới 10 Mbit), thừa sức 9600 baud.
- Phải có điện trở kéo lên ở đầu ra; module bán sẵn đã có.

**⚠️ ĐỪNG dùng PC817 cho UART.** PC817 là opto transistor thường, sườn lên/xuống cỡ 3–18 µs.
Một bit ở 9600 baud dài 104 µs — tức sườn chiếm tới ~17% bề rộng bit, và con số đó **tệ dần
theo tuổi** khi hệ số truyền đạt tụt. Chạy được lúc mới lắp rồi vài tháng sau đọc sai byte lác
đác là kiểu hỏng khó lần nhất. PC817 để dành cho việc dưới đây.

### Đường xung tiền từ L70 (1 chiều, chậm)

**PC817 là đủ và đúng chỗ** — ~3–5k một con, hoặc module 4 kênh ~15k.
- Xung tiền dài 50–100 ms, sườn vài chục µs không ảnh hưởng gì.
- L70 chạy 12V, đầu ra hở cực thu. Cho qua opto là bỏ hẳn đường 12V chạm vào chân ESP32.

### Đường relay điều khiển ghế

**Giữ nguyên.** Relay vốn đã là tiếp điểm khô, cách ly sẵn.

## 5. 🔴 GIỮ LẠI RELAY, KỂ CẢ KHI ĐÃ NÓI CHUYỆN ĐƯỢC VỚI BO GHẾ

Nói chuyện được với bo ghế rồi thì relay trông như thừa. Không thừa:

- Nó là **đường cắt cứng** khi bo ghế treo hoặc hiểu nhầm lệnh.
- Nó **không phụ thuộc phần mềm**: ESP32 mất điện là tiếp điểm nhả, ghế dừng. Còn lệnh
  "cộng 15 phút" đã gửi xuống thì bo ghế cứ chạy cho hết, dù ESP32 có chết ngay sau đó.
- Nó là thứ duy nhất còn đúng khi giao thức đoán sai.

## 6. Mấy thứ gần như không tốn tiền mà ăn nhiều nhiễu nhất

1. **Cáp xoắn đôi hoặc có lưới.** Tín hiệu xoắn với mát của chính nó. Lưới **chỉ nối một
   đầu**, ở phía ESP32 — nối hai đầu là tự tạo vòng lặp mát, đúng thứ đang muốn tránh.
2. **Đừng đi chung bó với dây mô-tơ.** Tách ra ít nhất một gang tay; bắt buộc phải cắt ngang
   thì cắt vuông góc, đừng chạy song song.
3. **RC nhỏ ở đầu nhận:** nối tiếp **100 Ω**, rồi **1 nF** xuống mát. Hằng số thời gian 100 ns
   — so với bit 104 µs thì bằng không, mà gai nhọn từ mô-tơ bị cắt sạch.
4. **Tụ tại chân nguồn ESP32:** 100 nF gốm **song song** 10 µF, hàn sát chân.
5. **Dây càng ngắn càng tốt.** Mỗi centimet là một cọng ăng-ten.

## 7. Thứ tự lắp — đo trước, hàn sau

1. **Đo áp** giữa TX của bo ghế và mát bo ghế, lúc ghế đứng yên.
   3.3V → cắm thẳng dò được · 5V → chia áp để dò · **12V → bắt buộc qua cách ly ngay từ bước dò**.
2. **Đo chênh lệch mát:** que đen ở mát ESP32, que đỏ ở mát bo ghế, để thang **AC**. Trên
   1–2 V AC là đã có vấn đề mát — càng chắc phải cách ly, và **đừng nối chung mát**.
3. Dò giao thức bằng bản `esp32_ghe_nghe_bo` (chỉ nghe, chân GPIO 35 không đẩy ra được).
4. Biết giao thức rồi mới lắp cách ly cố định và viết phần ESP32 đóng vai cục nhận tiền.

## 8. Tổng tiền

| Món | Giá ước | Bắt buộc? |
|---|---|---|
| Module ADUM1201 (UART 2 chiều) | 40–70k | **Có** |
| B0505S-1W (nếu không kéo được nguồn từ bo ghế) | 15–25k | Tuỳ cách 3 |
| PC817 hoặc module 4 kênh (đường xung L70) | 5–15k | Nên |
| Cáp xoắn có lưới, 1 m | ~10k | Nên |
| Điện trở 100 Ω + tụ 1 nF, 100 nF, 10 µF | ~5k | Nên |

**Khoảng 70–130k cho một ghế.** Giá là ước lượng thị trường, anh xem lại lúc mua.

So với một con ESP32 chết giữa ca cùng đợt tiền mặt chưa kịp ghi sổ, thì đây là món rẻ nhất
trong cả hệ thống.
