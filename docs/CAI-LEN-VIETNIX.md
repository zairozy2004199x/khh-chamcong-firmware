# Cài lên Vietnix

> Tệp này chỉ nói **thao tác trong bảng điều khiển của Vietnix**. Các bước của WordPress và của
> plugin (khoá, múi giờ, Apps Script, kéo dữ liệu) nằm ở [CAI-LEN-HOSTING.md](CAI-LEN-HOSTING.md)
> — nhà cung cấp nào cũng làm giống nhau.

> Gói của anh Thắng dùng **cPanel** (xem trang quản lý: có *File Manager*, *MySQL Databases*,
> *phpMyAdmin*, *Cron Jobs*). Hướng dẫn dưới đây theo cPanel.

---

## 0. Việc đầu tiên: nhờ Vietnix chuyển hộ

Vietnix có **chuyển dữ liệu miễn phí**. Trước khi tự làm gì, mở ticket nói đúng ba câu:

> Tôi vừa mua hosting. Site WordPress cũ ở Hostinger, tên miền `khmatrix.com`.
> Nhờ bên bạn chuyển giúp sang Vietnix.
> Nếu Hostinger đã ngưng và không lấy được dữ liệu thì báo tôi, tôi cài mới.

**Hostinger còn vào được thì lấy bản sao lưu trước khi làm gì khác** — vào phpMyAdmin bên đó,
chọn cơ sở dữ liệu, **Export → Go**, giữ tệp `.sql`. Có nó thì khỏi kéo lại dữ liệu.
Không lấy được cũng **không mất gì**: hồ sơ, chấm công cũ, sổ phân quyền đều kéo lại được từ
Google Sheet, và kéo lại bao nhiêu lần cũng không sinh dòng rác.

---

## 1. Trỏ tên miền

Trong email chào mừng của Vietnix có **hai địa chỉ nameserver** (dạng `ns1.…`, `ns2.…`) và
**địa chỉ IP** của hosting. Lấy từ email đó, đừng lấy từ đâu khác.

Vào chỗ đang quản lý tên miền `khmatrix.com`, đổi nameserver sang hai địa chỉ ấy.

⚠️ **Đổi nameserver mất 2–24 tiếng mới lan hết.** Trong lúc chờ, có máy vào được site mới, có
máy vẫn vào site cũ — nên **đừng vừa đổi vừa cài**, dễ cài lên nhầm nơi rồi tưởng mất dữ liệu.
Chờ trang hiện đúng nội dung mới rồi hãy làm bước 2.

---

## 2. Bật HTTPS

cPanel → **SSL/TLS Status** → chọn cả `khmatrix.com` và `www.khmatrix.com` → **Run AutoSSL**
(Let's Encrypt, miễn phí). Xong thì vào **Domains** bật **Force HTTPS Redirect**.

⚠️ **Bắt buộc, không phải cho đẹp.** Firmware máy chấm công **từ chối địa chỉ `http://`** —
có `static_assert` chặn ngay lúc biên dịch. Chưa có HTTPS thì máy không gửi được lượt nào.

---

## 3. Chỉnh PHP

cPanel → **Select PHP Version** (đổi phiên bản + bật phần mở rộng), rồi tab **Options** để sửa
mấy giá trị dưới. Không thấy tab Options thì dùng **MultiPHP INI Editor**.

| Đặt | Giá trị | Vì sao |
|---|---|---|
| Phiên bản PHP | **8.1** trở lên | Plugin dùng cú pháp PHP 8 |
| `post_max_size` | **≥ 8M** | Máy gửi kèm ảnh mặt base64. Nhỏ hơn là gói bị **cắt giữa chừng** và mất lượt bấm đó |
| `upload_max_filesize` | **≥ 8M** | Đi kèm cái trên |
| `max_execution_time` | **≥ 120** | Lệnh kéo dữ liệu gọi mạng sang Apps Script nhiều lượt |
| `memory_limit` | **≥ 256M** | Bảng lương cả tháng của một cơ sở |

Phần mở rộng cần bật: `mysqli`, `curl`, `mbstring`, `json`. Gói WordPress nào cũng có sẵn.

---

## 4. 🔴 TẮT CHẶN BOT CHO ĐƯỜNG MÁY GỬI VỀ

**Đây là chỗ dễ hỏng nhất, và hỏng thì im lặng.**

Máy chấm công ESP32 gọi bằng thư viện HTTP trần: **không phải trình duyệt, không chạy
JavaScript, không giữ cookie**. Mọi lớp "chống bot" đều xếp nó vào loại bot và chặn — mà firmware
thì **không báo cho ai biết**: máy vẫn kêu bíp, vẫn báo thành công, chấm công thì không vào.

Phải kiểm ba thứ:

1. **ModSecurity** — cPanel → **ModSecurity**. Bật thì mở ticket nhờ Vietnix **loại trừ đường
   `/cham-cong-may`** (họ phải làm ở tầng máy chủ, anh tự tắt trong cPanel không phải lúc nào
   cũng đủ).
2. **Cloudflare** — nếu anh cho `khmatrix.com` chạy qua Cloudflare thì thêm một *WAF exception*
   cho `/cham-cong-may`, hoặc để tên miền ở chế độ **DNS only** (mây xám). Anh đã gặp đúng chuyện
   Cloudflare chặn ở app chi phí rồi.
3. **Plugin bảo mật WordPress** (Wordfence, iThemes…) — đừng cài, hoặc cài thì phải cho
   `/cham-cong-may` đi qua.

**Cách biết chắc:** sau khi cài xong, quẹt thử một thẻ ở máy thật rồi mở
**Chấm Công → Cổng nhận từ máy** xem nhật ký có ghi lượt đó không. Đó là chỗ **duy nhất** nhìn
ra được — cổng nhận luôn trả `SUCCESS` cho firmware kể cả khi nó bỏ gói, buộc phải vậy, không
thì máy đẩy lại vô hạn.

---

## 5. Cài WordPress

cPanel → **Softaculous Apps Installer** (hoặc **WordPress Toolkit**) → cài vào **thư mục gốc**
của `khmatrix.com`, **để trống ô thư mục con** — cài vào thư mục con là địa chỉ thành
`khmatrix.com/wp/`, và firmware thì đã ghi cứng `/cham-cong-may` ở gốc.

Có tệp `.sql` từ Hostinger thì làm ngược lại: **MySQL Databases** tạo cơ sở dữ liệu rỗng + một
người dùng, gán toàn quyền; **phpMyAdmin → Import** nạp tệp đó; rồi **File Manager** chép thư
mục `public_html` cũ sang.

---

## 6. Sửa `wp-config.php`

cPanel → **File Manager** → `public_html` → chuột phải vào `wp-config.php` → **Edit**.

Sửa `wp-config.php`, thêm **trên** dòng `/* That's all, stop editing! */`:

```php
define( 'VHCC_KHOA_MAY',  '…chuỗi ngẫu nhiên dài, ít nhất 32 ký tự…' );
define( 'VHCC_PIN_ADMIN', '…PIN 6 số vai trò ADMIN trong sheet PhanQuyen…' );
```

Đặt khoá mới, **đừng dùng lại khoá của Hostinger** — nó đã nằm trong ảnh chụp màn hình gửi qua
chat.

---

## 7. Còn lại

Từ đây theo [CAI-LEN-HOSTING.md](CAI-LEN-HOSTING.md) từ mục **1.2 Múi giờ** trở xuống:
múi giờ, kích hoạt plugin, Firebase `cfg/wp`, dán hai tệp Apps Script, kéo dữ liệu.

**Đổi khoá thì phải sửa cả hai nơi**, không thì máy gửi lên bị chối sạch:

| Chỗ | Sửa thành |
|---|---|
| Firebase `cfg/wp/key` | đúng `VHCC_KHOA_MAY` mới |
| Firebase `cfg/wp/url` | `https://khmatrix.com/cham-cong-may` (giữ nguyên nếu tên miền không đổi) |
| Apps Script → `WP_KEY` | đúng `VHCC_KHOA_MAY` mới |
| Apps Script → `WEB_KEY` | chuỗi plugin **mới** sinh ra (bấm *Sinh khoá mới*), rồi Deploy lại |

**Tên miền giữ nguyên thì không phải nạp lại firmware máy nào.** Máy lấy địa chỉ từ Firebase
`cfg/wp`, không phải từ bộ nhớ trong — đây đúng là lý do làm nhánh đó thay vì ghi cứng vào
firmware.

---

## 8. Kiểm sau khi chuyển

Làm đủ, theo thứ tự — mỗi dòng hỏng thì dừng ở đó:

| Kiểm | Đạt là thế nào |
|---|---|
| `https://khmatrix.com` | Lên trang, ổ khoá xanh |
| Chấm Công → Cài đặt | Hiện đúng số bản plugin |
| Bấm **Thử cầu nối** | "Cầu nối sống" |
| Cổng nhận từ máy | Dòng xanh ✔️ đã cấu hình khoá |
| **Quẹt thẻ thật một lần** | Nhật ký cổng nhận ghi lượt đó |
| `khmatrix.com/cham-cong` | Đăng nhập được bằng PIN |
| Bảng công & Lương | Số khớp với app gốc cùng cơ sở cùng tháng |

Dòng **quẹt thẻ thật** là dòng không được bỏ. Sáu dòng kia đều là anh nhìn màn hình; chỉ dòng đó
mới chứng minh đường từ máy về web thông thật.
