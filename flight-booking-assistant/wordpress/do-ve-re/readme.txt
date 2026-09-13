=== Dò Vé Rẻ ===
Contributors: khh
Tags: flights, booking, vietqr, travel
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later

So giá vé máy bay, nhận đơn của khách qua chuyển khoản VietQR, gửi email mã đặt chỗ.

== Description ==

Ba phần dùng được ngay sau khi kích hoạt:

* **Bảng giá** — shortcode `[do_ve_re]`: khách gõ chặng, xem giá theo 7 ngày, lọc theo hãng / điểm dừng / giờ bay, và mở song song các trang đang bán chặng đó.
* **Trang đặt vé** — shortcode `[do_ve_re_dat_ve]`: khách điền thông tin, gõ mã số thuế là tự ra tên công ty, rồi chuyển khoản theo mã QR có sẵn số tiền và mã đơn.
* **Trang Đơn hàng** trong khu quản trị: theo dõi đã thu — đã trả cho hãng — chênh lệch, điền hộ thông tin khách sang form của hãng, nhập mã đặt chỗ để đóng đơn.

Không lưu số thẻ của khách ở bất kỳ đâu: khách chuyển khoản ngân hàng.

== Installation ==

1. Quản trị → Gói mở rộng → Cài mới → Tải gói mở rộng lên → chọn file zip này → Kích hoạt.
2. Tạo hai trang: một trang dán `[do_ve_re]`, một trang dán `[do_ve_re_dat_ve]`.
3. Vào **Dò Vé Rẻ → Cài đặt**: khai số tài khoản nhận tiền, phí dịch vụ, chọn trang đặt vé.
4. Muốn giá thật thì khai thêm khoá Amadeus (developers.amadeus.com). Bỏ trống thì bảng chạy bằng giá mô phỏng.
5. Muốn tiền về tự khớp đơn thì khai khoá webhook, trỏ dịch vụ báo biến động số dư (SePay, Casso…) về:
   `/wp-json/dovere/v1/webhook/bank` với header `Authorization: Apikey <khoá>`.

== Frequently Asked Questions ==

= Email gửi bằng gì? =
Bằng `wp_mail` của WordPress, nên dùng luôn cấu hình SMTP sẵn có trên site.

= Giá có chính xác không? =
Chưa khai khoá Amadeus thì bảng giá là **mô phỏng** và có nhãn nói rõ. Khai rồi thì giá lấy từ Amadeus,
nhưng Vietjet và Vietravel phần lớn không bán qua GDS nên vẫn nên đối chiếu với trang hãng.

== Changelog ==

= 1.4.1 =
* Sửa lỗi gọi sai đường dẫn REST khiến bảng giá báo "không tìm thấy đường dẫn".
* Thẻ "Nguồn giá" chỉ hiện với người quản trị; khách không thấy thông báo kỹ thuật nào.
* Ẩn cột lọc thì phần còn lại trải hết bề ngang, không chừa chỗ trống.

= 1.4.0 =
* Quy đổi ngoại tệ sang VND theo tỉ giá khai trong Cài đặt, ghi rõ "quy đổi từ … USD".
* Lọc bỏ chuyến của hãng giả Duffel Airways (mã ZZ) trong chế độ thử; nút thử nguồn cảnh báo khi đang dùng khoá thử.

= 1.3.0 =
* Bỏ hẳn chuyến bay mô phỏng: chưa nối nguồn thật thì trang không dựng giá, chỉ mở đúng nơi đang bán.
* Nút "Thử nguồn giá" gọi đúng nguồn đang chọn và cho xem vài chuyến thật, chọn được chặng để thử.

= 1.2.1 =
* Cài mới chạy ngay ở chế độ "so giá rồi dẫn sang nơi bán" — không cần khai gì.
* Công cụ nội bộ (hồ sơ điền sẵn, học form) chỉ hiện với người quản trị đang đăng nhập.

= 1.2.0 =
* Thêm chế độ "so giá rồi dẫn sang nơi bán": không cần hợp đồng với hãng nào, khách bấm là sang thẳng nơi bán vé.
* Khai được mã giới thiệu (affiliate) cho Traveloka, Trip.com, Kiwi.

= 1.1.1 =
* Sửa trang đặt vé: các ô dính sát viền khối.

= 1.1.0 =
* Luồng chốt giá trước: khách đặt theo giá tham khảo, mình kiểm chỗ rồi mới báo giá chính thức và số tài khoản.
* Nguồn giá "đại lý cấp 1": khai đường dẫn API và bảng ánh xạ trong Cài đặt, không phải sửa code; có nút gọi thử.

= 1.0.8 =
* Thêm nguồn giá Duffel (cổng tự phục vụ của Amadeus đã đóng 17/7/2025); chọn nguồn ngay trong Cài đặt.

= 1.0.7 =
* Logo và banner đầu trang; khai đường dẫn ảnh là dùng logo riêng của công ty.
* Màu thương hiệu khai được; chữ trên nút tự chọn đen hay trắng theo tương phản WCAG.

= 1.0.5 =
* Giao diện sáng: nền trời, khối bo tròn nổi khối, nút bo viên; bỏ chế độ tối để khách luôn thấy một giao diện.
* Nạp bộ chữ Baloo 2 + Be Vietnam Pro (trước đây thiếu nên trang chạy bằng font hệ thống).
* Thêm khối thông tin công ty ở chân trang, sửa được trong Cài đặt.

= 1.0.3 =
* Hai trang bán vé có khung riêng: bỏ header/footer của theme, giãn hết bề ngang màn hình, ẩn thanh quản trị.

= 1.0.2 =
* Tự dựng sẵn trang bảng giá và trang đặt vé cho khách, hiện link ngay trong khu quản trị.

= 1.0.0 =
* Bảng giá, trang đặt vé với QR VietQR, trang quản trị đơn, email bốn mốc, webhook ngân hàng, tra mã số thuế.
