=== Đối soát VAT ===
Contributors: khh
Tags: ke-toan, hoa-don, doi-soat, excel, vat
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.1.0
License: MIT

Gộp sao kê thu hộ từ nhiều cổng thanh toán, quy về điểm xuất hoá đơn, tách VAT
rồi xuất file Excel để nhập Misa.

== Description ==

Công cụ đọc sao kê của QR VietQR, Payoo, VNPay và Zalo Mini App, tự nhận diện
từng sheet, quy mã điểm bán của mỗi cổng về điểm xuất hoá đơn theo danh mục, rồi
sinh file Excel gồm danh sách hoá đơn, bản kê chi tiết, pivot theo ngày và bảng
đối soát.

Xuất hoá đơn theo hai kiểu: gộp cả kỳ (mỗi điểm một hoá đơn) hoặc theo từng ngày
(mỗi điểm mỗi ngày một dòng).

= Địa chỉ web =

Plugin mở một địa chỉ gọn ngay trên tên miền, ví dụ https://tenmien.com/doi-soat-vat/
Địa chỉ hiện ở đầu trang quản trị kèm nút sao chép.

Mặc định phải đăng nhập WordPress mới mở được. Muốn gửi cho người ngoài thì tick
"Cho người chưa đăng nhập dùng địa chỉ này" rồi Lưu.

= Về dữ liệu =

Toàn bộ xử lý chạy bằng JavaScript ngay trong trình duyệt. Plugin không nhận,
không lưu và không gửi đi bất kỳ file sao kê nào; cơ sở dữ liệu WordPress không
bị ghi thêm gì. Máy chủ chỉ phục vụ file tĩnh của giao diện.

= Quyền truy cập =

Chỉ tài khoản có quyền `manage_options` (thường là Quản trị viên) mới thấy và mở
được công cụ.

== Installation ==

1. Vào Plugin → Thêm Plugin → Tải Plugin lên, chọn file zip này.
2. Bấm Kích hoạt.
3. Mở mục "Đối soát VAT" ở menu bên trái.

== Frequently Asked Questions ==

= Sao kê có bị tải lên máy chủ không? =

Không. File được đọc bằng JavaScript trong trình duyệt, không rời khỏi máy bạn.

= Địa chỉ /doi-soat-vat/ báo 404 =

WordPress chưa dựng lại bảng đường dẫn. Vào Cài đặt → Đường dẫn tĩnh và bấm Lưu
một lần. Trong lúc đó dùng đường dẫn dự phòng in ở trang quản trị.

= Vì sao khung công cụ trong trang quản trị bị trống? =

Một số cấu hình bảo mật chặn truy cập file .html trong thư mục plugin. Bấm
"Mở ở tab mới" để kiểm tra; nếu báo 403 thì cần cho phép đọc file tĩnh trong
wp-content/plugins/doi-soat-vat/web/.

== Changelog ==

= 1.1.0 =
* Thêm địa chỉ web gọn cho công cụ, kèm tuỳ chọn cho người chưa đăng nhập dùng.
* Trang quản trị hiện địa chỉ kèm nút sao chép và đường dẫn dự phòng.

= 1.0.0 =
* Bản đầu tiên.
