# Giao thức bo ghế — những gì đã dò ra được

Cập nhật 24/08/2026. Ghi lại để lần sau khỏi dò lại từ đầu.

## Bối cảnh

Trên ghế có một bo đọc chỉ số màn và điều khiển ghế. Hai chân RX/TX của nó vốn nối
cục nhận tiền; sau khi chuyển sang ICT L70 giao tiếp xung với ESP32 thì hai chân đó
bỏ không. Muốn ESP32 điều khiển bo ghế thì phải đóng vai cục nhận tiền — tức nói
đúng thứ tiếng mà bo ghế chờ nghe.

**Vai trò đảo so với khối MDB sẵn có trong firmware ghế.** Ở đó ESP32 là CHỦ, hỏi
L70. Ở đây bo ghế là CHỦ, ESP32 phải làm TỚ và trả lời đúng nhịp.

## Đã chốt

Dò từ **bốn bản ghi độc lập**, hai tốc độ lấy mẫu khác nhau (1 MHz và 4 MHz), bằng
`tools/doc-sr.py`:

```
9600 baud · 11 bit mỗi khung · cực thuận · nghỉ ở mức CAO
khung = 1 start + 8 data + 1 bit thứ chín + 1 stop
```

Bằng chứng:

| Bản ghi | Kết quả | Lỗi khung |
|---|---|---|
| 1 (4 MHz) | `000 1E0` | 0 |
| 2 (4 MHz) | `000 1E0` | 1 |
| 3 (4 MHz) | `000 1E0` | 0 |
| 4 (1 MHz) | `000 1E0` | 0 |

Thử **đảo cực** thì hỏng ở cả bốn bản → không có tầng đảo trên đường truyền.

Bề rộng mọi xung đều là bội số nguyên của 104,17 µs (= 1 bit ở 9600):

```
1033 µs ÷ 104,17 = 9,92 ≈ 10 bit
 414 µs ÷ 104,17 = 3,97 ≈  4 bit
 620 µs ÷ 104,17 = 5,95 ≈  6 bit
```

Ba con số này lặp y hệt ở cả bốn bản, sai lệch dưới 1 µs.

## Chưa phân biệt được — và vì sao không sao

`9600 · 8 bit + chẵn (even)` và `9600 · 9 bit` cho **kết quả giống hệt nhau**, cùng
0 lỗi. Không phải trùng hợp: trên dây chúng là một, cùng 11 bit. Chỉ khác cách gọi
bit thứ chín — MDB gọi là *mode bit*, UART thường gọi là *bit chẵn lẻ*.

Với hai byte hiện có thì chưa tách được, vì parity chẵn của `0xE0` tình cờ đúng
bằng 1 — bằng chính giá trị mode bit. Cần một byte mà hai cách tính lệch nhau mới
phân biệt được.

**Không cản trở việc giả lập**: cứ đẩy/đọc đủ 11 bit là đúng cả hai đường.

## Chưa biết

- **Bo ghế mong nghe câu đáp nào.** Nó phát đúng hai khung lúc lên điện rồi im bặt —
  hỏi một câu, không ai đáp, nên bỏ cuộc. Con ESP32 trên ghế không trả lời vì nó
  giao tiếp bằng xung ở GPIO 27, không đụng đường UART này.
- **`0xE0` nghĩa là gì.** Bit thứ chín bật → theo MDB là byte địa chỉ, mở đầu một
  lệnh. Nhưng 0xE0 không nằm trong bảng địa chỉ MDB chuẩn (0x08 đổi xu, 0x10 nhận
  tiền giấy, 0x30 thanh toán không tiền mặt), nên nhiều khả năng là địa chỉ riêng
  của hãng ghế.

Đang mắc vòng: *muốn biết đáp gì thì phải nghe thêm, muốn nghe thêm thì phải đáp*.
Cách phá vòng là cứ đáp thử rồi xem bo ghế nói gì tiếp — đó là việc của
`esp32_ghe_gia_lap_mdb`.

## `F8` = KẸT TIỀN — xác nhận 24/08/2026

Trên đường 9600 baud (đường ghế ↔ ICT), khi cục nhận tiền bị kẹt tờ thì xuất hiện khung:

```
F8   bit thứ chín = 1
```

lặp lại khoảng **15–25 giây một lần**, xen giữa các cặp `00 E0` bình thường.

**Đã loại trừ nhiễu bằng phép thử tách bạch:** gỡ tờ kẹt ra, để máy chạy bình thường → `F8`
**hết hẳn**. Nên nó là tín hiệu thật, không phải gai.

Chỗ này ban đầu em nghi là nhiễu, vì `F8` = `1111 1000` nằm đúng họ với `FF`/`FE` — những giá
trị mà gai nhiễu hay sinh ra (xung xuống ngắn → mã tưởng có bit start → đọc tiếp toàn bit 1).
Phân biệt được chỉ nhờ phép thử gỡ kẹt: nhiễu thì không quan tâm máy có kẹt hay không.

### Vì sao đáng dùng

Firmware ghế hiện phát hiện ICT hỏng bằng cách suy từ ĐƯỜNG XUNG (GPIO 27) — chỉ có một sợi
dây tín hiệu nên suy được rất ít, và không cách nào biết "kẹt tiền" khác "không ai nạp tiền".
`F8` là bo ghế NÓI THẲNG, không phải suy đoán.

### Cách dùng cho chắc

Đòi **ít nhất 2 lần `F8` trong 60 giây** rồi mới báo. Một lần đơn lẻ vẫn có thể là gai — mà báo
động giả thì người ta học cách bỏ qua cảnh báo, còn tệ hơn không báo.

## LỜI GIẢI — ICT gửi BA byte, 4800 baud (24/08/2026)

Suốt buổi đi sai hướng vì tưởng ESP32 phải ĐÓNG VAI cục nhận tiền, tức phải trả lời cuộc bắt
tay 9600 baud của bo ghế. Cách hãng khác làm thì khác hẳn:

```
ghế TX22  ──────────►  ICT          (ghế hỏi, ICT trả lời — ESP32 không dính gì)
ICT       ──────────►  ghế          (ICT báo tiền)
ICT       ──────────►  ESP32        (ESP32 nghe ICT)
ESP32     ──────────►  ghế RX22     (ESP32 bơm thêm)
```

**ICT ở nguyên trong mạch.** Nó lo phần bắt tay. ESP32 chỉ chen vào giữa: nghe ICT rồi nói lại
vào chân RX của ghế. Không cần biết `00 E0` nghĩa là gì.

### 🔴 Chen vào giữa, KHÔNG cắm song song

Nguyên bản đường ICT nói ra cắm thẳng vào chân RX22 của ghế. Muốn ESP32 bơm được thì phải
**rút sợi đó khỏi RX22** rồi cho nó đi vòng qua ESP32:

```
trước:   ICT ──────────────────────────► RX22
sau:     ICT ──► ESP32 GPIO 35 ··· GPIO 26 ──► RX22
```

Để nguyên sợi cũ rồi cắm thêm GPIO 26 vào cùng chân là **hai cổng đẩy cùng lái một sợi dây**:
ICT giữ mức cao, ESP32 kéo xuống thấp, dòng chạy thẳng giữa hai bên. Nhẹ thì tín hiệu ra rác,
nặng thì chết cổng. Đây không phải chuyện lý thuyết — dễ vấp vì nhìn sơ đồ thì tưởng chỉ cần
"thêm một dây".

Đường **ghế TX22 → ICT giữ nguyên**, không đụng: cuộc bắt tay 9600 baud là chuyện riêng của
hai đứa, ESP32 không chen vào.

### Khung báo tiền — đo bằng chính ESP32, không qua logic analyzer

Nạp lần lượt ba mệnh giá rồi đọc thẳng byte ICT gửi ra:

```
 10.000đ  →  81  41  10
 20.000đ  →  81  42  10
 50.000đ  →  81  43  10
100.000đ  →  81  44  10
```

Byte đầu và byte cuối giống hệt ở cả ba; **chỉ byte GIỮA đổi**, theo đúng cách ICT đánh số kênh
mệnh giá: `0x40 + số kênh`.

| Tờ | Byte giữa | Kênh |
|---|---|---|
| 10.000đ | `41` | 1 |
| 20.000đ | `42` | 2 |
| 50.000đ | `43` | 3 |
| 100.000đ | `44` | 4 |

Nhịp cũng cố định:

```
81              mở đầu
0x40 + kênh     mệnh giá        ← 3 ms sau
10              kết thúc        ← 1,2 giây sau
```

1,2 giây là lúc ICT kéo tờ tiền vào và xác nhận. Bơm mà bỏ nhịp này thì bo ghế có thể không nhận.

### Bốn ca KHÔNG có tiền — đo 24/08/2026

Ngoài ca nhận được tiền, ICT còn phát ra bốn kiểu dấu vết nữa. Ghi lại đủ vì chúng
quyết định lúc nào ĐƯỢC bơm và lúc nào KHÔNG.

```
81  (0x40+kênh)  10        NHẬN được — CÓ tiền, bơm sang ghế
81  (0x40+kênh)  29        đút vướng, ICT nhả tờ ra — KHÔNG tiền
29  2F                     khách giựt lại / nhét giấy — KHÔNG tiền, KHÔNG có byte mở đầu
25                         kẹt tờ
2F                         sẵn sàng lại (gỡ xong kẹt, hoặc xong một lượt)
```

🔴 **Byte CUỐI mới quyết định có tiền hay không.** Hai dòng đầu bảng mở đầu giống hệt
nhau — cùng `81`, cùng mã mệnh giá. Ai bơm ngay khi thấy `81 41` là cộng tiền cho cả
tờ bị nhả ra. Phải chờ tới byte thứ ba: `10` = có, `29` = không.

Ca thứ ba khác hẳn: **không có `81`, không có mã mệnh giá**. Hai tình huống đo riêng
biệt đều cho đúng dấu vết này:

| Việc khách làm | Log đọc được |
|---|---|
| nhét tờ vào rồi giựt lại | `29` → `2F` |
| nhét giấy (không phải tiền) | `29` → `2F` |

Giống nhau là đúng chứ không phải trùng hợp: cả hai lần ICT đều chưa kịp nhận diện
mệnh giá nên không có gì để báo, chỉ nói "đã đẩy ra" rồi "sẵn sàng". Không cần tách
hai ca — với ghế thì cả hai đều là *không có tiền*, xử lý y như nhau.

Nhờ vậy quy tắc đọc gọn lại còn một câu: **chỉ bơm khi bắt được đủ bộ ba kết thúc
bằng `10`.** Mọi thứ khác là không tiền.

### ⚠️ `0x02` ở các bản ghi trước là SAI

Mấy bản ghi logic analyzer đầu cho ra một byte `0x02` cho mọi mệnh giá, và em đã suýt viết mã
theo đó. Sai: `0x02` là do đọc lệch khung ở một đường bên cạnh. Byte thật chưa bao giờ là `0x02`.

Chỗ này đáng nhớ vì nó trông rất thuyết phục: ba bản ghi độc lập đều cho `0x02`, 0 lỗi khung,
bề rộng xung khớp bội số 207 µs. Nhưng ba lần đọc sai cùng một kiểu vẫn là sai. Thứ lật lại
được là **đo từ phía khác** — để chính ESP32 đọc cổng, thay vì suy từ dạng sóng.

### Bài học về cách dò

**Hỏi xem đã có bo nào chạy được chưa, TRƯỚC khi ngồi dò.** Anh Thắng có sẵn bo hãng khác đang
chạy tốt; kẹp con dò vào đó là ra lời giải trong mười phút. Cả buổi mò tám giá trị đáp, năm mức
trễ, hai cực tín hiệu — không cần thiết.

**Cuộc bắt tay `00 E0` ở 9600 baud là chuyện giữa ghế và ICT**, không liên quan tới việc bơm
tiền. Nó có thật, đã dò đúng, nhưng giải nó không đưa tới đâu cả.

## Bo ghế CÓ nghe thấy câu trả lời — chốt 24/08/2026

Phép thử: chạy hai lượt giống hệt nhau, khác đúng một biến — ESP32 có đáp hay không.

| | Có đáp `000` | Không đáp |
|---|---|---|
| lần hỏi 2 cách lần 1 | **6.323 ms** | **273 ms** |
| lần hỏi 3 | không có | **122 ms** sau lần 2 |
| sau đó | im 240 giây | bỏ cuộc |

Không được trả lời thì bo ghế hỏi dồn ba lần trong 400 ms rồi thôi. Được trả lời thì nó giãn
nhịp ra 6,3 giây, hỏi thêm một lần, rồi im hẳn.

**Kết luận: chiều nói thông.** Mức 3,3V của ESP32 đủ cho đầu vào 5V của bo ghế — không cần
tầng nâng mức, không cần transistor.

Cách phát hiện: đừng chỉ nhìn xem bo ghế có nói thêm BYTE mới không (suốt buổi nó chỉ nói
`00 E0`). Nhìn **nhịp** giữa các lần hỏi — bo ghế đổi nhịp là bằng chứng nó đã nhận được gì đó,
kể cả khi nội dung nó nói ra không đổi.

Việc còn lại: bo ghế im 240 giây sau khi bắt tay — ngược hẳn với dáng hỏi dồn khi bị bỏ mặc.
Nhiều khả năng nó đã coi cục nhận tiền là có mặt và đang nằm chờ báo có tiền. Chưa biết khung
báo tiền trông thế nào.

## Mạch nối — chốt ngày 24/08/2026

Chân TX22 của bo ghế chạy **mức 5V**, chân ESP32 chỉ chịu 3,3V. Ba điện trở:

```
        +5V (nguồn bo ghế)
         |
       [4,7 kΩ]        <- kéo lên, giữ đường sống lúc rảnh
         |
TX22 ----+--[1 kΩ]--+---------> GPIO 35
                    |
                 [2 kΩ]
                    |
RX22 <--------------+---------- GPIO 26   (đấu thẳng, KHÔNG chia áp)
                    |
GND  ---------------+---------- GND
```

| Trị số | Nối | Vì sao |
|---|---|---|
| 4,7 kΩ | +5V → TX22 | TX22 là kiểu **hở cực** — tự nó chỉ kéo xuống được. Thiếu con này thì đường nằm chết ở mức thấp; firmware báo `ĐƯỜNG KẸT Ở MỨC THẤP` 5–6 lần/giây (đúng bằng 1000 ms ÷ hạn chờ 200 ms, tức đường thấp *suốt*). Cục nhận tiền thật khi cắm vào sẽ cấp cái kéo lên này; tháo nó ra là đường mất chỗ dựa. |
| 1 kΩ | TX22 → điểm A | nhánh trên bộ chia áp |
| 2 kΩ | điểm A → GND | nhánh dưới; 5V × 2/(1+2) = 3,33V |

**Đo trước khi cắm vào ESP32** (chưa nối dây sang GPIO 35): TX22 phải ~5V đứng yên,
điểm A phải ~3,3V. Ra 5V ở điểm A = chưa nối 2 kΩ xuống mát. Ra 0V = chưa nối 1 kΩ lên TX22.

**Chiều nói thì đấu thẳng.** ESP32 đẩy 3,3V, mạch logic 5V nhận mức cao từ ~2,0–2,5V nên đủ.
Đừng chia áp chiều này — chia áp chỉ để hạ áp ở chiều vào.

**Cặp 1k/2k chứ không phải 10k/20k.** Cùng ra 3,33V, nhưng trở kháng thấp hơn mười lần nên
ăn nhiễu ít hơn hẳn. Trước khi đổi sang 1k/2k, firmware đếm `114737` khung hỏng; sau khi đổi
còn `7`.

### Cách ly quang — đã thử và bỏ

Module **PC817** không dùng được ở đây, hai lý do:

1. **Điện trở hạn dòng của module tính cho 12V.** Ở 5V, dòng qua LED dưới 1 mA, không đủ ngưỡng
   sáng — đo được đầu ra phẳng lì ở mức cao suốt 10 giây trong khi đầu vào có tín hiệu sạch.
2. **PC817 tắt mất ~18 µs**, tức 17% bề rộng một bit ở 9600 baud. Ngay cả khi cấp đủ dòng thì
   sườn cũng méo tới mức bit stop rơi sai chỗ.

Khi chạy thật thì dùng **6N137** (opto cho truyền dữ liệu, trễ dưới 1 µs) và **cấp nguồn riêng
cho hai phía** — cách ly mà chung nguồn thì không cách ly được gì.

## Bẫy đã vấp, ghi lại để khỏi vấp lại

**Decoder UART của PulseView mặc định 8 bit.** Với dữ liệu 9 bit thì nó KHÔNG THỂ
sạch ở bất kỳ baud nào. Cả buổi dò từ 115200 xuống 9600 đều bẩn chính vì chỗ này —
dò đúng chỗ nhưng sai thước đo.

**Hai decoder có thể đang đặt hai baud khác nhau.** Đổi baud trên màn chỉ đổi được
cái đang chọn. Đã có lúc RX chạy 9600 còn TX chạy 115200, nên hàng trên và hàng dưới
trong cùng một tấm ảnh giải mã theo hai chuẩn — không thể nào sạch cùng lúc.

**Đo bề rộng xung bằng mắt trên ảnh chụp thì sai.** Một điểm ảnh ở thang 10 giây là
2,47 µs; đo "xung hẹp nhất" dễ trúng vào một CỤM bit chứ không phải một bit đơn. Đã
suýt chốt nhầm 115200 vì chuyện này. Cách chắc: xuất `.sr` rồi để máy dò.

**Ở 1 MHz mà baud 115200 thì mỗi bit chỉ có 8,7 mẫu** — đủ nhưng không dư, decoder
dễ trượt. Muốn chắc thì lấy mẫu ≥ 20 lần baud.

**Kẹp đo tuột giữa các lần bắt.** Có bản mất tín hiệu ở ba kênh cùng lúc, kể cả nhịp
lên điện — mà nhịp đó thì lần nào cũng phải có. Cố định dây, và luôn kiểm nhịp lên
điện làm mốc: không thấy nó là dây có vấn đề, không phải bo im.

## Công cụ

```
python3 tools/doc-sr.py bản-ghi.sr --kenh 0 1 2 3
```

Giải nén `.sr`, đếm sườn, đo bề rộng bit, thử 12 tốc độ × 5 kiểu khung (kể cả kiểu
9 bit của MDB), xếp hạng theo số lỗi khung, in byte kèm mốc thời gian. Khung 9 bit
được đánh dấu `*` ở byte có bit thứ chín bật.

```
bash tools/test/fw/kiem-nhip-mdb.sh
```

Canh phép tính mốc bit của bản giả lập — chỗ dễ sai nhất, vì 104,1667 µs không tròn
và cộng dồn thì tới bit stop đã lệch.

## Chân dùng chung — cả ba nơi phải khớp

| | nghe (bo ghế nói) | nói (mình nói) |
|---|---|---|
| `esp32_ghe_nghe_bo` (chỉ nghe) | **GPIO 35** | không có |
| `esp32_ghe_gia_lap_mdb` | **GPIO 35** | GPIO 26 |
| `esp32_ghe_massage` (firmware ghế) | **GPIO 35** (`MDB_RX_PIN`) | GPIO 27 (`MDB_TX_PIN`, đang tắt) |

GPIO 35 là chân **chỉ-vào-được** — không có tầng đẩy ra, nên dù mã có sai thế nào cũng
không thể đẩy ngược tín hiệu vào bo ghế. Đó là lớp an toàn vật lý, giữ nguyên ở cả ba bản.

Bản giả lập nói ở **GPIO 26** chứ không phải 27, vì trên ghế chân 27 đang giữ xung tiền
của ICT L70. Trên con ESP32 phụ thì không đụng, nhưng để cùng một con số cho khỏi nhầm
lúc mang qua lại.

`tools/test/fw/kiem-nhip-mdb.sh` canh cho ba nơi này luôn cùng một chân — sửa lệch là nó báo.
