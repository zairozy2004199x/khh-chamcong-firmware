=== Vận Hành Chi Phí (K&H) ===
Contributors: khh
Tags: chi phí, tạm ứng, quyết toán, MISA, kế toán
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.2.0
License: GPLv2 or later

App "Chi Phí Cơ Sở / Vận Hành Chi Phí" của K&H, dựng lại từ Google Apps Script sang WordPress.

== Description ==

Toàn bộ nghiệp vụ của app cũ, dữ liệu nằm trong bảng MySQL riêng của WordPress
(tiền tố `wp_vhcp_*`), không còn phụ thuộc Google Sheet:

* **Sổ chi phí** (nhập phẳng): chọn **loại chi phí** rồi nhập — mỗi loại gắn sẵn **mã tài khoản**,
  dòng chi lưu luôn mã nên dò lại chỉ cần đọc cột mã, không phải chạy lại hàm dò.
* **Nhập đơn** theo tuần, nhiều cơ sở: hạng mục xin tạm ứng → phát sinh → thực chi từng dòng.
* **Duyệt tạm ứng** (quản lý) → **cấp tạm ứng** (kế toán, tiền mặt/chuyển khoản kèm ảnh).
* **Quyết toán** tách 2 luồng cá nhân (141) và nhà cung cấp (331); thừa/thiếu tự tính.
* **Chi phí kỹ thuật**: dự án Tháo dỡ / Setup lắp đặt + sheet "Chi phí cơ sở" chung xuyên suốt.
* **Marketing**, **Công tác**, **Setup**: dự toán vs thực chi, VAT, hồ sơ đính kèm.
* **Tổng quan dòng tiền**, **Vận hành theo tuần** (gom cả 5 mảng), báo cáo 1 gian/cơ sở.
* **Xuất MISA** 10 cột đúng mẫu "Chứng từ nghiệp vụ khác" cho cả 4 mảng, chốt "đã xuất".
* **Cấu hình**: cơ sở → mã đơn vị, ma trận TK Nợ (nhóm × phân loại lớn), TK Có, đối tượng,
  VietQR, người dùng + PIN, ma trận phân quyền theo vai trò, phân vai trò SSO theo email.
* **Nhật ký hoạt động**, **nhập dữ liệu cũ từ CSV** xuất ra từ Google Sheet.

Đăng nhập bằng PIN 4 số như app cũ; hoặc đăng nhập một lần (SSO) từ trang tổng K&H
qua `?sso=<token>` dùng chung `SSO_SECRET`.

== Installation ==

1. Bảng điều khiển WordPress → **Plugin → Cài mới → Tải plugin lên** → chọn file
   `vhcp-chi-phi.zip` → **Cài đặt** → **Kích hoạt**.
2. Mở **Vận Hành Chi Phí** ở menu bên trái, bấm đường dẫn app (mặc định `/chi-phi/`).
3. Đăng nhập PIN mặc định **1111** (vai trò Admin) rồi vào tab ⚙️ Cấu hình đổi PIN ngay.
4. Muốn mang dữ liệu cũ sang: **Vận Hành Chi Phí → Nhập dữ liệu**.

== Changelog ==

= 1.2.0 =
* **Kỹ thuật · Marketing · Công tác · Setup** cũng có ô Loại chi phí trên từng dòng; mã tài khoản
  gắn lúc nhập, hiện dưới nội dung dòng. Bỏ mã gán cứng 141/331/64125 trong code — dòng chưa gắn
  loại vẫn xuất y như cũ để chuyển dần từng mảng.
* Thêm tab **🔎 Tra theo mã tài khoản**: 1 mã ra mọi khoản chi của mọi mảng, tổng theo mã/mảng/kỳ/
  cơ sở, lọc + tải Excel, tự cảnh báo số dòng chưa gắn mã và số dòng còn dùng mã cũ.
* Nút **🔗 Gán mã cho dòng cũ** làm một lượt cho cả 5 mảng; riêng Kỹ thuật tự suy loại chi phí theo
  loại dự án (Tháo dỡ / Setup lắp đặt / Chi phí cơ sở).

= 1.2.1 =
* Nạp dữ liệu cũ từ CSV nay **tự gán mã tài khoản ngay khi nạp** cho cả 5 đường: dòng chi của đơn
  (theo cột Nhóm mặt hàng), tab dự án kỹ thuật (cột Loại chi phí, trống thì suy theo loại dự án),
  marketing & công tác/setup (cột Loại chi phí tùy chọn), sổ chi phí. Nạp xong báo lại số dòng
  chưa có TK Nợ.

= 1.1.0 =
* Thêm tab **💵 Sổ chi phí**: chọn loại chi phí rồi nhập, không cần lập đơn/tạm ứng/quyết toán.
* Thêm danh mục **Loại chi phí gắn mã tài khoản** (TK Nợ/TK Có/Mã đối tượng) — nơi duy nhất
  quyết định "chi phí này là chi phí gì". Dòng chi (cả sổ chi phí lẫn dòng của đơn) lưu sẵn mã
  tại thời điểm nhập; có nút gán mã cho dòng cũ.
* Xuất MISA ưu tiên mã tài khoản trên dòng; ma trận nhóm × phân loại lớn chỉ còn là dự phòng.
* **Vận hành tuần bỏ gom** Kỹ thuật / Công tác / Setup — chỉ còn Đơn vận hành · Sổ chi phí · Marketing.
* Chặn theo vai trò ở phía máy chủ; cắt hết các chỗ đọc lặp database (không màn hình nào tăng
  số lệnh theo số bản ghi).

= 1.0.0 =
* Bản đầu tiên: chuyển toàn bộ app Apps Script `VanHanhChiPhi` sang WordPress
  (backend PHP + REST API, giao diện giữ nguyên nhờ lớp tương thích `google.script.run`).
