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

## Bốn firmware trong repo

| Thư mục | Việc |
|---|---|
| `esp32_hik_chamcong_full/` | **Máy chính** — đọc sự kiện quẹt thẻ/khuôn mặt từ đầu đọc Hikvision (ISAPI), đẩy lên web app kèm ảnh, đồng bộ nhân viên xuống máy, màn hình CYD, hỗ trợ cả WiFi lẫn 4G |
| `esp32_ota_updater/` | **Máy trạm ("thợ nạp")** — tự tải `.bin` mới về thẻ nhớ cắm sẵn qua WiFi; đứng gần máy chính rồi **bấm** mới nạp |
| `esp32_posh_qr/` | **Hộp POSH QR** — mở ghế massage bằng mã QR: quét mã → kiểm chữ ký ngay trên chip (không cần mạng) → gửi lệnh UART sang bo ICT của ghế |
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
Ghế  ⇄  TXS0108E  ⇄  [ ESP32 ]  ⇄  TXS0108E  ⇄  ICT L70
                        │
                     USB → Serial Monitor 115200
```

Đầu bán tiền ICT L70 và bo ghế đã nói chuyện đúng giao thức của chúng từ trước. Ngồi đoán khung
lệnh là tự nghĩ ra một thứ rồi mong nó trùng; ngồi giữa mà chép thì có **đúng** cái bo ghế chịu
nghe — cả checksum, cả nhịp hỏi đáp. Sau này thay hẳn L70 thì chỉ việc phát lại y như vậy.

Cắm USB, gõ `QUYTRINH` — nó chạy tuần tự các bước nghiệm thu và dừng lại ngay chỗ hỏng. Lệnh
đáng chú ý: `DOBAUD` đo baud bằng **bề rộng xung** (đo thật, không phải thử từng tốc độ rồi
đoán), `BANG <hex>` bắn khung sang phía ghế để giả làm L70, `CAT`/`NOI` để cắt cầu.

⚠️ **Chân OE của TXS0108E có trở kéo xuống bên trong — để hở là cả mạch TẮT**, không byte nào
qua được và không có dấu hiệu báo lỗi nào. Phải nối OE lên 3,3V. Đây là chỗ vấp kinh điển.

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
