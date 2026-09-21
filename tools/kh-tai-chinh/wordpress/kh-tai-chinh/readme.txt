=== Tài Chính K&H ===
Requires at least: 5.6
Requires PHP: 7.4
Stable tag: 0.3.0
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

* Tổng quan — tổng số dư, thu/chi tháng này, số dư từng tài khoản, các đợt đối
  soát gần nhất còn lệch
* Ngân hàng — thêm/xoá tài khoản, số dư đầu tính từ một ngày mốc
* Giao dịch / Sao kê — thêm tay, dán sao kê hàng loạt, lọc theo tài khoản /
  khoảng ngày / thu-chi / nội dung, phân trang 100 dòng
* Đối soát — VietQR, Payoo, VNPay, Zalo Mini App, MoMo. Dán bảng cổng gửi về,
  máy ghép với sao kê ngân hàng và chia ra Khớp / Lệch tiền / Thiếu / Thừa.
  Ghép bốn lượt theo độ chắc chắn giảm dần: trùng mã giao dịch, trùng ngày và
  số tiền, trùng số tiền lệch ngày trong T+3, và trùng số tiền sau khi trừ phí
  (cổng chuyển về số ròng). Tải kết quả ra CSV.
* Chi phí — ghi khoản chi theo bộ phận và khoản mục, một bảng cho cả công ty
  chứ không phải mỗi bộ phận một trang. Bảng cộng chéo khoản mục × bộ phận,
  lọc theo kỳ / bộ phận / khoản mục / đã-chưa thấy tiền ra. Danh mục sửa được
  ngay trên trang, tách riêng theo từng pháp nhân.
* Đối soát chi phí — ghép chứng từ chi phí (loại chuyển khoản) với các dòng chi
  trong sao kê, dùng lại đúng phép ghép của đối soát cổng. Ba nhóm: Khớp / Có
  chứng từ chưa thấy tiền ra / Tiền ra không có chứng từ.
* Sao lưu — tải toàn bộ kho dữ liệu ra một tệp .json và nhập lại được. Nhập là
  thêm vào, id cấp lại và mọi liên kết nối lại theo id mới.
* Bản web ngoài — cùng các màn hình đó ở /tai-chinh/ thay vì trong wp-admin.
  Vẫn phải đăng nhập.

== Bản web ngoài ==

Sau khi kích hoạt, mở https://tenmien.vn/tai-chinh/ . Nếu ra 404 thì vào
Cài đặt → Đường dẫn tĩnh và bấm Lưu một lần để WordPress ghi lại luật đường dẫn.
Host không bật đường dẫn tĩnh thì dùng https://tenmien.vn/?khtc_man=tong-quan .

Trang đặt noindex và bắt đăng nhập, người ngoài không xem được.

== Làm tiếp ==

Công nợ, hoá đơn đầu vào, hoá đơn đầu ra, pháp danh, hồ sơ, báo cáo.
