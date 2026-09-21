# Tài Chính K&H — plugin WordPress

Dựng lại **KH Bank Tracker** (app Node.js) thành plugin WordPress, để chạy thẳng
trên hosting hiện có: chỉ cần wp-admin, không cần Render/Railway, không cần SSH.

## Cài

Plugin → Thêm Plugin → **Tải Plugin lên** → chọn `kh-tai-chinh.zip` → Kích hoạt.
Menu **Tài Chính K&H** hiện ở thanh bên trái wp-admin.

Đóng gói lại sau khi sửa mã:

```bash
cd tools/kh-tai-chinh
./dong-goi.sh              # sinh ra kh-tai-chinh.zip
```

## Hai chỗ cố tình làm khác bản gốc

**Dữ liệu trong MySQL, không phải JSON.** Bản gốc giữ toàn bộ giao dịch trong
`transactions.json` — dữ liệu thật đã 95 MB / 219.000 dòng, mỗi lần mở trang là
nạp cả tệp vào RAM. Trên shared hosting cách đó không chạy nổi. Bảng MySQL có chỉ
mục `(cty, ngay)` nên lọc theo kỳ không phải quét toàn bảng.

**Đăng nhập dùng tài khoản WordPress.** Bản gốc tự dựng bảng người dùng + mật
khẩu riêng. Bớt một chỗ giữ mật khẩu là bớt một chỗ có thể rò. Quyền tối thiểu
để mở là `edit_pages` (Editor) — kế toán thường không phải Administrator.

## Đang có

| Màn hình | Làm được gì |
|---|---|
| Tổng quan | Tổng số dư, thu/chi tháng này, số dư từng tài khoản |
| Ngân hàng | Thêm/xoá tài khoản; số dư đầu tính từ một ngày mốc |
| Giao dịch / Sao kê | Thêm tay; dán sao kê hàng loạt; lọc theo tài khoản, khoảng ngày, thu/chi, nội dung; phân trang 100 dòng |

Mọi màn hình đều tách **KH Cũ / KH Mới**, chọn ở góc phải. Lựa chọn lưu theo
từng người dùng, nên hai kế toán mở cùng lúc không đá nhau.

### Số dư tính thế nào

`số dư = số dư đầu + thu − chi`, chỉ tính giao dịch **từ ngày mốc trở đi**.
Giao dịch trước ngày đó coi như đã nằm sẵn trong số dư đầu; cộng lại là đếm hai
lần. Đây là chỗ dễ sai nhất của màn hình ngân hàng nên tính một lần trong
`KHTC_NganHang::so_du()`, không rải ra từng trang.

### Dán sao kê

Mỗi dòng: `Ngày (dd/mm/yyyy) · Diễn giải · Số tiền · Thu/Chi`, cách nhau bằng
Tab (copy thẳng từ Excel) hoặc dấu phẩy. Bỏ trống cột cuối thì số dương là Thu,
số âm là Chi.

Số tiền nhận cả `1.500.000`, `1,500,000`, `1500000` và `20.000 ₫`. Quy ước: nhóm
sau dấu cuối cùng dài đúng 3 chữ số thì đó là phân cách nghìn, ngược lại là dấu
thập phân. Không có quy ước này thì `20.000 ₫` đọc ra 20 đồng — sai 1000 lần mà
không có gì báo, vì 20 vẫn là số hợp lệ.

## Chạy test

```bash
php tools/kh-tai-chinh/tests/kiem-so-ngay.php
```

19 phép thử cho hai hàm đọc số và đọc ngày — phần quyết định mọi con số vào sổ,
và là phần sai âm thầm nếu hỏng.

## Còn phải làm

Bản gốc có 23.600 dòng route cho các mảng chưa dựng lại: đối soát VietQR
(3.722 dòng), pháp danh (2.287), Zalo-VNPay-Payoo (2.180), chi phí (1.924), đối
soát chi phí (1.725), hồ sơ (1.497), hoá đơn đầu vào (1.416), báo cáo (1.260),
công nợ, đầu ra, hoá đơn đầu ra, sao lưu.
