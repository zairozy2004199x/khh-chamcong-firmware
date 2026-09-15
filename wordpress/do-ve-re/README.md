# Dò Vé Rẻ — plugin WordPress

So giá vé máy bay, nhận đơn của khách, theo dõi tới lúc xuất vé.
Không cần build, không phụ thuộc thư viện ngoài: PHP + HTML + CSS + JavaScript thuần.

## Cài đặt

Trang quản trị → **Plugin → Cài mới → Tải plugin lên** → chọn `do-ve-re.zip` → **Kích hoạt**.

Khi kích hoạt, plugin tạo bảng `{prefix}dvr_orders`.

## Dùng ở đâu

| Cách | Đường dẫn |
|---|---|
| Màn khách, toàn màn hình | `https://site.com/?dvr=1` |
| Nhúng vào một trang | shortcode `[do_ve_re]` |
| Quản trị | Menu **Dò Vé Rẻ** |

## Ba màn hình

**Bảng giá** — chặng, ngày, số khách, hạng vé; lịch giá 7 ngày; lọc theo hãng, số điểm dừng,
giờ cất cánh, hành lý; một nút mở song song 5 trang so giá với chặng và ngày điền sẵn.

**Khách đặt vé** — khách chọn chuyến rồi điền hành khách, liên hệ, mã số thuế. Máy chủ tính
phí dịch vụ, sinh mã đơn và nội dung chuyển khoản. Đơn giữ giá theo số phút khai trong Cài đặt.

**Đơn hàng** — chỉ hiện với tài khoản có quyền `manage_options`: đánh dấu đã nhận tiền, nhập
mã đặt chỗ và giá mua vào (để tính chênh lệch), hoàn tiền.

## Điền hộ

Nút *Điền hộ* là một bookmarklet: kéo lên thanh dấu trang, mở form đặt vé của hãng rồi bấm nó.
Máy dò các ô "họ tên / ngày sinh / CCCD / mã số thuế / địa chỉ…" kể cả trang tiếng Anh và điền vào.

Trang nào đổi giao diện làm điền hụt thì dùng *Học form*: bấm vào từng ô, chọn ô đó là gì, rồi
dán khối luật trở lại trang. Từ lần sau máy khớp bằng selector đã học.

Ô **số thẻ, CVV, OTP** nằm trong danh sách bỏ qua — không bao giờ bị điền.

## Bảo mật & quyền

- Hồ sơ điền sẵn nằm trong `localStorage` của trình duyệt người dùng, không gửi lên máy chủ.
- Đơn đặt vé thì lưu trên máy chủ. Máy chủ **tự kiểm lại** email, số điện thoại, số khách, và
  **tự tính lại** phí dịch vụ — không tin con số trình duyệt gửi lên.
- `GET /wp-json/dvr/v1/orders` (danh sách) yêu cầu `manage_options`.
- `GET /wp-json/dvr/v1/orders/<mã>` công khai để khách tra đơn của mình, nhưng đã cắt bỏ giá
  mua vào và nhật ký nội bộ.
- `POST /wp-json/dvr/v1/orders/<mã>` (đổi trạng thái) yêu cầu `manage_options`.
- Không có chỗ nào nhập hay lưu số thẻ.

## Nguồn giá

Bảng giá hiện là **giá mô phỏng**, tính bằng công thức trong trình duyệt:

```
giá gốc = 380.000 + 900 × (cự ly km ^ 0,95)
× hệ số theo số ngày còn lại (sát ngày thì đắt)
× hệ số theo thứ trong tuần (cuối tuần đắt)
× hệ số theo giờ bay (đêm và sáng sớm rẻ)
× hệ số theo hãng và hạng vé
```

Số hiệu chuyến cũng là số sinh ra, không có thật. Muốn giá thật phải nối API bán vé
(Amadeus hoặc một đại lý có API) — phần đó **chưa có** trong bản này.

## Cấu trúc

```
do-ve-re/
  do-ve-re.php     bảng đơn, REST, shortcode, trang quản trị, cài đặt
  page.php         toàn bộ giao diện khách
  assets/dvr.css   giao diện
  assets/dvr.js    bảng giá, điền hộ, học form, kho đơn
  uninstall.php    gỡ plugin thì xoá bảng và cài đặt
```
