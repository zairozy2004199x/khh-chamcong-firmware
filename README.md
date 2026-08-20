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

## Hai firmware trong repo

| Thư mục | Việc |
|---|---|
| `esp32_hik_chamcong_full/` | **Máy chính** — đọc sự kiện quẹt thẻ/khuôn mặt từ đầu đọc Hikvision (ISAPI), đẩy lên web app kèm ảnh, đồng bộ nhân viên xuống máy, màn hình CYD, hỗ trợ cả WiFi lẫn 4G |
| `esp32_ota_updater/` | **Máy trạm ("thợ nạp")** — tự tải `.bin` mới về thẻ nhớ cắm sẵn qua WiFi; đứng gần máy chính rồi **bấm** mới nạp |

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

---

## Ngoài firmware: web Chi Phí Cơ Sở trên WordPress

Thư mục `wordpress/vhcp-chi-phi/` là **plugin WordPress** dựng lại app *Chi Phí Cơ Sở / Vận Hành
Chi Phí* (bản Google Apps Script cũ) để chạy trực tiếp trên hosting, dữ liệu nằm trong bảng MySQL
riêng thay vì Google Sheet. File cài đặt sẵn: `dist/vhcp-chi-phi.zip`.

Hướng dẫn cài + mang dữ liệu cũ sang: [`docs/HUONG-DAN-CAI-DAT-WORDPRESS.md`](docs/HUONG-DAN-CAI-DAT-WORDPRESS.md).

```bash
bash tools/build-plugin-zip.sh    # đóng gói lại plugin
php tools/test/test-flows.php     # 170 phép thử logic, không cần WordPress/MySQL
bash tools/deploy-hosting.sh      # đẩy lên hosting qua SSH/FTP (chạy ở máy có mạng vào hosting)
```
