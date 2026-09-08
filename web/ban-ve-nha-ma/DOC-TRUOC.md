# Ghost Bride VIP — bán vé theo khung giờ

Một file duy nhất: `index.html`. Không cần cài gì, không cần cơ sở dữ liệu, không cần server.

| Địa chỉ | Ai dùng | Có gì |
|---|---|---|
| `khmatrix.com/ban-ve-nha-ma` | Khách | Chọn ngày → khung giờ → ghi danh → nhận mã giữ chỗ (0₫), tra cứu bằng SĐT, tải thiệp `.svg` |
| `khmatrix.com/ban-ve-nha-ma/#quanly` | Nhân viên | Tổng quan · Xét duyệt tiền · Soát vé tại cửa · Dữ liệu đối soát · Cài đặt hệ thống |

Hai trang **dùng chung một sổ**, nên phải nằm chung một file: khách giữ một khung là ô ấy hụt đi
ngay trên màn quản trị, kế toán huỷ một đơn là chỗ mở lại cho khách khác — tức thì.

## Đưa lên host

1. cPanel → **File Manager** → vào `public_html`.
2. Tạo thư mục **`ban-ve-nha-ma`**.
3. Tải `index.html` vào trong thư mục đó.

Xong. Địa chỉ ra đúng `khmatrix.com/ban-ve-nha-ma`.

WordPress **không đụng gì tới nó**: máy chủ thấy có thư mục thật thì phục vụ thẳng, không đẩy vào
WordPress nữa. Không phải tạo trang, không phải cài plugin.

## 🔴 Đọc trước khi giao cho khách thật

Bản này để sổ **trong trình duyệt đang mở** — mỗi máy một sổ riêng. Khách đặt trên điện thoại của
họ thì **máy quản trị không thấy đơn ấy**, và ngược lại.

Nghĩa là hôm nay nó dùng được cho:
- xem thử, duyệt giao diện, tập cho nhân viên quen tay;
- bán tại quầy bằng **đúng một máy** (nhân viên nhập hộ khách).

**Chưa dùng được** cho việc khách tự đặt từ điện thoại của họ. Muốn vậy phải có sổ chung trên máy
chủ — mở `index.html`, tìm khối `CAUHINH` ở đầu phần `<script>` và điền link vào:

```js
var CAUHINH = {
  so:   "",     /* dán link máy chủ vào đây */
  khoa: ""      /* mật khẩu khai ở máy chủ */
};
```

Phần gọi máy chủ đã viết sẵn (hàm `khoMang`), kèm hợp đồng dữ liệu máy chủ phải trả về. Việc còn
lại là dựng đầu bên kia — đó là giai đoạn 2, nối vào plugin `vhcp-ve` đang chạy trên khmatrix.com
(nó đã có sẵn VietQR, Momo, VNPay, tồn kho vé, tích điểm).

## Mã PIN vào trang quản trị

Mặc định **`246810`**, đổi trong *Cài đặt hệ thống → Mã PIN*.

⚠️ PIN này nằm ở phía trình duyệt — ai xem mã nguồn trang là đọc được. Nó chỉ ngăn người đi ngang
gõ nhầm `#quanly`, **không phải bảo mật thật**. Chặn thật thì cửa phải nằm ở máy chủ (giai đoạn 2).

## Cấu hình mặc định

Sửa hết trong *Cài đặt hệ thống*, không phải sửa mã.

| Mục | Mặc định |
|---|---|
| Giá vé | 100.000₫ / người |
| Sức chứa | 10 người / khung giờ |
| Khung giờ | 10:10 → 11:45, cách nhau 5 phút |
| Mở bán trước | 14 ngày |
| Có mặt trước | 10 phút |
| Tự nạp đơn mới | 6 giây |

## Vòng đời một đơn

```
Khách ghi danh          -> Đang giữ chỗ          (đã trừ chỗ ngay, chưa thu tiền)
Khách bấm "đã chuyển"   -> Chờ duyệt thanh toán  (hiện ở màn Xét duyệt, có chuông)
Kế toán bấm DUYỆT       -> Chờ Check-in
Nhân viên soát vé       -> Đã vào
Kế toán bấm TỪ CHỐI     -> Bị huỷ                (chỗ trả lại cho khách khác ngay)
```

**Đơn đang giữ chỗ vẫn chiếm chỗ** dù chưa trả đồng nào — giữ mà không trừ thì hai đoàn cùng một
khung, và người thứ hai chỉ biết mình hụt lúc đã tới cửa.

**Thực thu chỉ đếm đơn đã duyệt tiền.** Cộng cả đơn đang giữ chỗ vào là báo cáo kế toán nói dối
đúng con số người ta mang đi đối chiếu ngân hàng.

## Soát vé

Trang cửa quét QR bằng bộ đọc **có sẵn trong trình duyệt**, không tải thư viện nào từ mạng ngoài —
cửa hay sóng yếu, mà một trang soát vé chết vì tải không nổi thư viện là cả hàng khách đứng đợi.
Máy nào không có bộ đọc thì vẫn còn ô **gõ tay mã** ngay bên dưới; đó là đường luôn chạy được.

Vé sai ngày thì **cảnh báo chứ không chặn** — khách tới sớm hoặc trễ một khung là chuyện thường,
chặn cứng thì nhân viên phải gọi quản lý giữa lúc đông nhất. Người ở cửa quyết định, họ đang nhìn
thấy khách; trang thì không.

Vé đã vào rồi thì **chặn hẳn** lần hai.

## Đã bấm thử những gì

Chạy thật trong trình duyệt (Chromium), không phải chỉ đọc mã:

- vẽ đủ 20 khung giờ, cột tình trạng tiền sảnh khớp số chỗ;
- chặn thiếu tên · thiếu SĐT · chưa tích cam kết;
- giữ chỗ 3 người → khung hụt đúng 3 chỗ → ra thiệp có mã, tiền 300.000₫;
- báo đã chuyển khoản → đơn sang *Chờ duyệt*, chuông kêu, badge sidebar lên 1;
- kế toán duyệt → soát vé lần 1 cho vào, **lần 2 bị chặn**, mã lạ báo không có trong sổ;
- lọc + xuất CSV ở màn đối soát; nạp 12 đơn mẫu; 4 ô số kế toán tính đúng.

Trang gọi ra ngoài đúng một chỗ: Google Fonts. Chặn mạng đó thì trang vẫn chạy, chỉ đổi sang chữ
hệ thống (đã thử).
