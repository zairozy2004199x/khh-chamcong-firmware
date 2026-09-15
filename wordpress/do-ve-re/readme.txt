=== Dò Vé Rẻ ===
Contributors: khh
Tags: flight, booking, vietnam, ve may bay
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later

So giá vé máy bay theo chặng và ngày, mở song song các trang đang bán vé, điền sẵn hồ sơ
hành khách và hoá đơn VAT, nhận đơn của khách rồi theo dõi tới lúc xuất vé.

== Description ==

Ba màn hình trong một trang:

1. **Bảng giá** — gõ chặng, ngày, số khách; xem lịch giá 7 ngày, lọc theo hãng, điểm dừng,
   giờ bay, hành lý; mở song song Google Flights, Skyscanner, Traveloka, Trip.com, Momondo
   với chặng và ngày đã điền sẵn trên đường dẫn.
2. **Khách đặt vé** — khách chọn chuyến, điền hành khách và thông tin hoá đơn, nhận mã đơn
   cùng nội dung chuyển khoản. Không có ô nhập số thẻ ở bất kỳ đâu.
3. **Đơn hàng** — chỉ quản trị viên thấy: đánh dấu đã nhận tiền, nhập mã đặt chỗ, hoàn tiền.

Kèm **Điền hộ**: một dấu trang kéo lên thanh bookmark, bấm khi đang ở form đặt vé của hãng
để máy đổ sẵn họ tên, ngày sinh, CCCD, mã số thuế, địa chỉ… Số thẻ, CVV và OTP cố ý không
đụng tới — người thật tự nhập.

**Bảng giá hiện là giá mô phỏng**: máy tự tính theo cự ly, số ngày còn lại tới ngày bay,
thứ trong tuần và giờ cất cánh. Không phải giá thật của hãng, nên không khớp Trip.com hay
Traveloka. Muốn giá thật phải nối API bán vé — phần đó chưa có trong bản này.

== Installation ==

1. Trang quản trị → Plugin → Cài mới → Tải plugin lên → chọn file zip.
2. Kích hoạt.
3. Vào **Dò Vé Rẻ → Cài đặt** khai tài khoản nhận tiền, phí dịch vụ và logo.
4. Mở màn khách ở `https://site.com/?dvr=1`, hoặc chèn shortcode `[do_ve_re]` vào một trang.

== Changelog ==

= 1.0.0 =
* Bản đầu tiên: bảng giá, màn khách đặt vé, màn đơn hàng.
* Đơn lưu trong bảng riêng của WordPress; máy chủ tự kiểm email, số điện thoại và tính lại
  phí dịch vụ, không tin số do trình duyệt gửi lên.
* Khách chỉ tra được đơn của chính mình bằng mã đơn, không thấy giá mua vào và nhật ký nội bộ.
* Nhận diện K&H: logo, tên, màu thương hiệu khai trong Cài đặt.
