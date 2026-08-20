=== Vận Hành Chi Phí (K&H) ===
Contributors: khh
Tags: chi phí, tạm ứng, quyết toán, MISA, kế toán
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later

App "Chi Phí Cơ Sở / Vận Hành Chi Phí" của K&H, dựng lại từ Google Apps Script sang WordPress.

== Description ==

Toàn bộ nghiệp vụ của app cũ, dữ liệu nằm trong bảng MySQL riêng của WordPress
(tiền tố `wp_vhcp_*`), không còn phụ thuộc Google Sheet:

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

= 1.0.0 =
* Bản đầu tiên: chuyển toàn bộ app Apps Script `VanHanhChiPhi` sang WordPress
  (backend PHP + REST API, giao diện giữ nguyên nhờ lớp tương thích `google.script.run`).
