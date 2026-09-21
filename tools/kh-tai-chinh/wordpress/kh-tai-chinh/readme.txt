=== Tài Chính K&H ===
Requires at least: 5.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later

Theo dõi ngân hàng, giao dịch và đối soát cho CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H.

== Description ==

Dựng lại bản KH Bank Tracker (Node.js) thành plugin WordPress để chạy thẳng trên
host hiện có, không cần Render/Railway, không cần SSH.

Dữ liệu nằm trong bảng MySQL của chính website, không phải file JSON — bản gốc
giữ toàn bộ giao dịch trong một tệp 95 MB, mỗi lần mở trang là nạp cả tệp vào
RAM; trên shared hosting cách đó không chạy nổi.

Đăng nhập dùng luôn tài khoản WordPress, không dựng bảng mật khẩu riêng.

== Đang có ==

* Tổng quan — tổng số dư, thu/chi tháng này, số dư từng tài khoản
* Ngân hàng — thêm/xoá tài khoản, số dư đầu tính từ một ngày mốc
* Giao dịch / Sao kê — thêm tay, dán sao kê hàng loạt, lọc theo tài khoản /
  khoảng ngày / thu-chi / nội dung, phân trang 100 dòng

== Làm tiếp ==

Đối soát MoMo · Zalo-VNPay-Payoo · VietQR, công nợ, hoá đơn đầu vào/đầu ra,
chi phí, báo cáo. Bản gốc có 23.600 dòng cho các mảng này.
