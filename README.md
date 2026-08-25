# Firmware máy chấm công K&H (ESP32)

Repo này **công khai** vì máy chấm công tải firmware **không kèm xác thực** — file `.bin` phải đặt ở
chỗ tải được ẩn danh thì nút "Cập nhật từ xa" trên web app mới hoạt động.

## Công khai mà vẫn an toàn — vì sao

Mã nguồn ở đây **không chứa bí mật nào**: không mật khẩu WiFi, không mật khẩu Hikvision, không token
web app, không Firebase secret, không cả link web app hay địa chỉ Firebase.

Firmware đọc mọi thứ đó từ **bộ nhớ trong của chip (NVS)**, theo thứ tự:

1. NVS đã có giá trị → dùng luôn.
2. NVS trống mà bản compile có giá trị thật (build ở máy, có `secrets.h`) → **chép vào NVS** rồi dùng.
3. Không có gì → máy hiện `CHUA CAU HINH`, mở AP không mật khẩu để khai tay.

**Cập nhật firmware (OTA) không ghi đè NVS.** Nên máy đã cấu hình một lần rồi thì nhận bản build từ
repo này vẫn chạy bình thường, không phải tới tận nơi khai lại.

CI có bước chặn cứng: quét file `.bin` tìm dấu vết bí mật (`AKfycb`, `default-rtdb`, `firebaseio`, …),
thấy là **dừng, không phát hành**.

## Năm firmware trong repo

| Thư mục | Việc |
|---|---|
| `esp32_hik_chamcong_full/` | **Máy chính** — đọc sự kiện quẹt thẻ/khuôn mặt từ đầu đọc Hikvision (ISAPI), đẩy lên web app kèm ảnh, đồng bộ nhân viên xuống máy, màn hình CYD, hỗ trợ cả WiFi lẫn 4G |
| `esp32_ota_updater/` | **Máy trạm ("thợ nạp")** — tự tải `.bin` mới về thẻ nhớ cắm sẵn qua WiFi; đứng gần máy chính rồi **bấm** mới nạp |
| `esp32_posh_qr/` | **Hộp POSH QR** — mở ghế massage bằng mã QR: quét mã → kiểm chữ ký ngay trên chip (không cần mạng) → gửi lệnh UART sang bo ICT của ghế |
| `esp32_nap_pic/` | **Thợ nạp dsPIC** — nạp firmware cho dsPIC33F từ thẻ nhớ, **không cần máy tính**, và **chọn được bản** nào trong thẻ |
| `esp32_cau_ict/` | **Cầu nghe lén** (mạch tạm) — ESP32 ngồi giữa `ICT L70 ⇄ ghế`, chuyển tiếp trong suốt hai chiều và chép lại từng byte. Dùng để **học giao thức**, không nằm trong máy chạy thật |

### Hộp POSH QR

Khách quét mã QR đã trả tiền, hộp tự kiểm rồi bảo bo ghế chạy N phút. **Không cần mạng** —
mã QR tự mang chữ ký HMAC-SHA256 bên trong, hộp kiểm bằng khoá nằm trong NVS của chính nó.

```
POSH1|<mã ghế hoặc *>|<phút>|<hết hạn>|<mã lượt>|<chữ ký 16 hex>
```

Chống xài lại: hộp nhớ 200 mã lượt gần nhất trong NVS (sống qua mất điện), cộng thêm ô hạn dùng.

**Nối với bo ghế qua UART.** Đây là chỗ hay vướng nhất, nên firmware có sẵn bộ đo tín hiệu:
cắm cáp USB, mở Serial Monitor 115200, gõ `TRO` để xem bảng lệnh. Thứ tự nên làm:

| Lệnh | Việc |
|---|---|
| `DAY` | đo mức nghỉ dây RX — loại lỗi phần cứng trước đã (UART nghỉ phải ở mức CAO) |
| `TUKIEM` | nối tạm TX↔RX rồi chạy: biết lỗi ở trong chip hay ngoài dây |
| `DO` | dò baud, thử lần lượt các tốc độ thông dụng rồi chấm điểm |
| `NGHE` | nghe lén bo nói gì khi bấm nút trên ghế |
| `HEX` / `CHU` | bắn thử một khung bất kỳ, xem bo trả về gì |
| `CAU` | nối thẳng cổng USB với bo ghế để soi bằng phần mềm trên máy tính |

| `GIU 0\|1` | giữ chân TX ở mức cố định để đo bằng đồng hồ vạn năng |
| `TUKIEM 200` | khép kín TX→RX chạy 200 lần — bắt lỗi "lúc được lúc không" mà chạy 1 lần không thấy |

⚠️ **Bo đưa tín hiệu qua chip đệm HT245, đo được VCC = 5V.** Đọc kỹ khối ghi chú đầu
`esp32_posh_qr/ict_ghe.h`. Hai việc:

- **Chiều bo → ESP32: bắt buộc chia áp** (1kΩ nối tiếp + 2kΩ xuống mass). Đầu ra HT245 đánh
  0–5V, chân ESP32 chỉ chịu ~3,6V — cắm thẳng là hỏng chân, mà hỏng âm thầm.
- **Chiều ESP32 → bo: phải đo, đừng đoán theo chữ in trên chip.** `74HC245` ở 5V có ngưỡng
  vào 3,5V → 3,3V của ESP32 **không đủ**; `74HCT245` ngưỡng 2,0V → **thừa sức**. Chip in
  "HT245" — chữ quyết định (`C` hay `CT`) đã bị lược đi, và mã đó được dùng cho cả hai loại,
  nên soi chữ hay tra mã đều không kết luận được. Gõ `GIU 1` rồi đo đầu ra HT245: ~5V là nối
  thẳng được, ~0V là phải nâng mức. Xong thì `TUKIEM 200` để chắc.

Kiểm tại chỗ, không cần chip, không cần arduino-cli:

```bash
bash esp32_posh_qr/ci/kiem-ma-qr.sh      # đọc/kiểm mã QR + đối chiếu chéo với tao-ma-qr.py
bash esp32_posh_qr/ci/kiem-ict.sh        # chốt từng byte của khung lệnh gửi sang bo ghế
bash esp32_posh_qr/ci/kiem-bien-dich.sh  # biên dịch kiểm cả sketch bằng thư viện ESP32 giả
```

Sinh mã QR để thử: `python3 esp32_posh_qr/ci/tao-ma-qr.py --khoa "$KHOA" --may GHE-01 --phut 15`

### Cầu nghe lén ICT L70 ⇄ ghế (mạch tạm)

```
Ghế  ⇄  ADuM1201  ⇄  [ ESP32 ]  ⇄  ADuM1201  ⇄  ICT L70
                        │
                     USB → Serial Monitor 115200
```

Đầu bán tiền ICT L70 và bo ghế đã nói chuyện đúng giao thức của chúng từ trước. Ngồi đoán khung
lệnh là tự nghĩ ra một thứ rồi mong nó trùng; ngồi giữa mà chép thì có **đúng** cái bo ghế chịu
nghe — cả checksum, cả nhịp hỏi đáp. Sau này thay hẳn L70 thì chỉ việc phát lại y như vậy.

**Nếu cáp là MDB** (harness `WEL-RBG01`): nguồn 12V, dữ liệu đỏ=RX/xanh dương=TX, mức TTL 5V cách
ly quang — **không cần MAX3232**, ADuM1201 đúng bài. Nhưng MDB **không phải "chép rồi phát lại"**:
9600 baud, **9 bit** (bit thứ 9 = mode), và **chủ–tớ** — bo ghế là chủ poll, L70 là tớ (bill
validator `0x30`). Để hộp QR mở ghế, ESP32 phải **đóng vai máy nhận tiền và trả lời poll**. Lệnh
`MDB` nghe đúng 9 bit; phần giải mã đã chốt bằng 9 bài test (`ci/kiem-mdb.sh`).

Cắm USB, gõ `QUYTRINH` — nó chạy tuần tự các bước nghiệm thu và dừng lại ngay chỗ hỏng. Lệnh
đáng chú ý: `DOBAUD` đo baud bằng **bề rộng xung** (đo thật, không phải thử từng tốc độ rồi
đoán), `BANG <hex>` bắn khung sang phía ghế để giả làm L70, `CAT`/`NOI` để cắt cầu.

**Cách ly bằng ADuM1201** (bộ cách ly số 2 kênh, một kênh mỗi chiều) là cách nên dùng: chiều cố
định nên không có kiểu lỗi "tự đoán chiều" của TXS0108E, không có chân OE để mà quên, chạy tới
hàng Mbps, và đầu ra phía 5V đúng mức nên **khỏi cần biết con HT245 trên bo ghế là HC hay HCT**.

L70 ăn **nguồn 12V DC**. Nó có **hai đầu ra mức khác nhau** — đấu vào đầu nào quyết định phần cứng:
đầu **DB9** (chân 2=TXD, 3=RXD, 5=GND) là **RS-232 thật → cần MAX3232**; đầu **molex TMT 8 chân**
(đen=RX1, tím=TX1, xanh dương=GND, trắng=Download VCC) nhiều khả năng là **cổng nạp TTL → dùng
ADuM1201/mạch chuyển mức, khỏi MAX3232**. Nhìn cáp thật xem ra đầu nào.

⚠️ **Trước hết đo xem L70 là RS-232 thật hay TTL.** Bản `L70T-P5 / L77T-P5` là RS-232 thật —
mức lưỡng cực, idle ở điện áp **âm** (−5…−12V), logic đảo. Cắm thẳng vào ESP32 hay ADuM1201 là
**cháy chân**. Đo chân TX của L70 so với mass lúc nghỉ: **âm** → cần **MAX3232** (nó hạ mức và đảo
logic, sau nó coi như UART thường); **dương ~5V** → TTL, dùng ADuM1201/mạch chuyển mức như dưới.

⚠️ **Phải là ADuM1201, không phải ADuM1200** — con 1200 hai kênh cùng chiều, UART không chạy được,
mà cắm vào cũng chẳng cháy gì nên rất khó ngờ. Cần 4 đường nên phải hai con; hoặc một con
**ADuM1402** (4 kênh, 2 mỗi chiều) gọn cả hai bên trong một chip.

⚠️ **Hai nét mass RIÊNG, không chạm nhau** — ngược hẳn với cách đấu thường. Nối chúng lại thì mạch
**vẫn chạy** nên không ai phát hiện, chỉ là đã vứt sạch phần cách ly. Kéo theo: ESP32 ăn nguồn
riêng (cắm USB laptop là được), không lấy 5V của máy. Và tụ 0,1µF sát chân VDD1/VDD2 từng con.

Nếu vẫn dùng TXS0108E: ⚠️ **chân OE có trở kéo xuống bên trong — để hở là cả mạch TẮT**, không byte
nào qua và không có dấu hiệu báo lỗi nào. Phải nối OE lên 3,3V, và mass thì chung hết.

Chân lấy nguyên khối `D32 D33 D25 D26 D27` — năm chân liền nhau ở hàng trái DevKit 30 chân, ngay
trên GND: đếm không nhầm, kẹp que đo không chạm nhau. Cả năm đều không dính boot, không dính
flash, không bị PSRAM chiếm. Hộp POSH QR **cố ý dùng chung cặp 32/33** cho phía bo ghế, nên nạp
qua nạp lại giữa hai firmware không phải đấu lại dây.

## Phát hành

Mỗi lần đẩy code lên nhánh `main`, GitHub Actions tự biên dịch và tạo bản phát hành.
Không cần Personal Access Token, không cần đặt secret hay variable nào — workflow dùng
`GITHUB_TOKEN` có sẵn để phát hành vào chính repo này.

Web app đọc một link **cố định**, không đổi qua mỗi lần phát hành:

```
https://github.com/<chủ-repo>/khh-chamcong-firmware/releases/download/latest/latest.json
```

Nội dung `latest.json`:

```json
{ "ver": "2026-07-31-abc1234", "url": "…/chamcong-2026-07-31-abc1234.bin",
  "size": 1250000, "commit": "…", "repo": "…" }
```

## ⚠️ Điều kiện trước khi nhận bản từ repo này

Máy **phải chạy bản `2026-07-30c` trở lên ít nhất một lần**. Bản đó là bản đầu tiên biết chép bí mật
từ firmware vào NVS. Máy chưa qua bước đó mà nhận bản CI (toàn placeholder) thì **mất cấu hình**, phải
nạp lại bằng USB. Web app có kiểm và cảnh báo theo phiên bản báo về từ heartbeat.

## Build ở máy mình

```bash
cd esp32_hik_chamcong_full
cp secrets.example.h secrets.h     # rồi điền giá trị thật
```

`secrets.h` nằm trong `.gitignore`, không bao giờ được commit. Không có nó thì build **báo lỗi ngay** —
cố ý, để không bao giờ nạp nhầm firmware dùng mật khẩu mẫu.

Cấu hình biên dịch phải khớp CI (`.github/workflows/firmware.yml`), quan trọng nhất là
`PartitionScheme=default`: lệch phân vùng thì `.bin` có thể không vừa, và **OTA không đổi được bảng
phân vùng** — muốn đổi phải nạp USB lại toàn bộ máy.

## Cấu hình máy tại chỗ

Nối WiFi `ChamCong-<tên cơ sở>` → mở `192.168.4.1`. Chip chưa cấu hình thì AP **mở, không mật khẩu**
để còn vào khai được; khai xong mật khẩu AP thì lần sau AP có khoá.

### Thợ nạp dsPIC

PICkit bắt buộc phải cắm vào máy tính. Chế độ Programmer-To-Go thì hàng nhái không chạy, mà chạy
được cũng chỉ giữ **đúng một ảnh** — muốn đổi bản là phải mang về máy tính. Máy này để cả xấp
`.hex` trong thẻ nhớ, đứng tại chỗ **chọn bản nào nạp bản đó** (bấm nút BOOT, còi kêu N tiếng =
file thứ N, giữ 2 giây để xác nhận).

Nối thẳng, không mạch phụ nào: dsPIC33F chạy 3,3V y như ESP32, và vào chế độ nạp chỉ cần `MCLR` ở
mức VDD — **không cần Vpp 9–13V** như PIC16/18.

```
ESP32 GPIO32 ──► MCLR      GPIO33 ──► PGEC      GPIO25 ◄─► PGED      GND ─── VSS
Thẻ nhớ: SPI (SCK18 / MISO19 / MOSI23), CS = 5
```

⚠️ **Đang ở chế độ CHỈ ĐỌC, cố ý.** Chuỗi lệnh ICSP viết theo tài liệu Microchip DS70152 nhưng chưa
đối chiếu được từng dòng với bản gốc. Ghi bằng chuỗi lệnh sai là hỏng bo thật — mà hỏng kiểu mất
luôn đường ICSP thì PICkit cũng không cứu được. Thứ tự bắt buộc:

1. `MACHIP` — đọc mã chip. **Chỉ đọc, không hỏng được gì.** Đọc ra đúng tên chip là đã chứng minh
   cùng lúc: chìa khoá vào chế độ nạp, thứ tự bit, nhịp xung, và đường dây.
2. `DOC` / `SOSANH` — đọc thử flash, so bản trong chip với file `.hex`. Vẫn chỉ đọc.
3. Hai bước trên chạy đúng rồi mới viết tiếp phần xoá và ghi.

**Chip nằm ở đâu** (soi hai file firmware thật, 25/08/2026): con `dsPIC33FJ256` nằm trong **chính đầu
bán tiền ICT L70** — 87.552 lệnh chương trình đúng bằng 256 KB của nó, 12 từ cấu hình ở
`0xF80000`, byte ma đúng khuôn không một ngoại lệ. Còn **bo ghế thì không phải PIC**: firmware của
nó nằm ở `0x08000000` với từ đầu là con trỏ ngăn xếp trỏ vào SRAM `0x20000000` — đó là bảng vector
ARM Cortex-M, tức **STM32**. Nạp bo ghế là việc khác: SWD, hoặc dễ hơn là bộ nạp UART có sẵn trong
chip (ST AN3155).

Kiểm một file `.hex` trên máy trước khi mang thẻ ra hiện trường, bằng đúng bộ đọc mà firmware dùng:

```bash
bash esp32_nap_pic/ci/xem-hex.sh duong/dan/toi/file.hex
```

Hai bẫy của file `.hex` dsPIC (đã chốt bằng 24 bài test, `ci/kiem-hex.sh`): mỗi lệnh 24 bit chiếm
**4 byte trong file, byte thứ tư là byte ma phải vứt**; và **địa chỉ trong file = địa chỉ chương
trình × 2**. Sai một trong hai thì nạp ra bãi rác mà máy vẫn báo thành công.