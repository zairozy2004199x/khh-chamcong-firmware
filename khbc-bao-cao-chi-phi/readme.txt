=== Báo Cáo Chi Phí (K&H) ===
Contributors: khh
Tags: chi phí, phân bổ, báo cáo, MISA, kế toán
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later

Phân bổ chi phí Máy tự động / Khu vui chơi ra "File tổng báo cáo" và phân bổ theo điểm để hạch toán MISA.
Nhân viên nhập khoản chi phí, kế toán duyệt, web tự tính.

== Description ==

* **Nhân viên** vào `/bao-cao-chi-phi/nhap/`, đăng nhập PIN, nhập khoản chi phí (tên, số tiền, chia
  MTĐ/KVC, nội dung MISA, tài khoản, mã đối tượng, ghi chú, ảnh chứng từ). Khoản ở trạng thái Chờ duyệt.
* **Kế toán** vào `/bao-cao-chi-phi/`: nhập Excel doanh thu / bảng lương (hoặc gõ tay), duyệt khoản,
  chỉnh kiểu chia, loại trừ, gộp cột; web tính ra **File tổng báo cáo** (3 mục) và **phân bổ theo điểm**;
  xuất Excel 10 sheet, in PDF, sao chép bảng; **chốt kỳ**; tuỳ chọn tính cả khoản chờ duyệt để xem trước.
* Kiểm tra dữ liệu: tỷ lệ, bảo toàn tổng, mã trùng, doanh thu 0. Kỳ mới giữ cấu hình.
* **Admin** quản lý người dùng & PIN ngay trong app; nhật ký hoạt động; SSO từ trang tổng K&H
  (`?sso=<token>`, dùng chung bí mật với plugin Vận Hành Chi Phí).
* Dữ liệu trong bảng MySQL riêng `wp_khbc_*`; cấu hình mỗi kỳ có số phiên bản để phát hiện hai
  người cùng lưu. REST API một cửa, tự chuyển đường khi hosting chặn `/wp-json/`.
* **Tự cập nhật**: menu Báo cáo chi phí → Tự cập nhật — bản đang chạy / bản mới trên GitHub / Kiểm tra ngay.
  Nguồn là nhánh `main` của repo công khai (không cần token; đổi nhánh ở Cài đặt), dự phòng GitHub Releases
  tag `khbc-bao-cao-chi-phi-vX.Y.Z`. Chỉ nâng lên bản cao hơn. Sau cập nhật tự xoá opcache; trang app tự báo
  nếu PHP đang trả bản khác với trang (bộ đệm cũ).

== Installation ==

1. Plugin → Cài mới → Tải plugin lên → chọn `khbc-bao-cao-chi-phi.zip` → Kích hoạt.
2. Menu **Báo cáo chi phí** → mở Trang kế toán. Đăng nhập PIN **1111** (Admin), đổi PIN ngay ở 👤 Tài khoản.
3. 👤 Tài khoản → Người dùng: thêm kế toán, nhân viên và cấp PIN. Gửi nhân viên đường dẫn `…/bao-cao-chi-phi/nhap/`.
4. Nhập Excel kỳ đầu (hoặc ⋯ → Nạp dữ liệu mẫu) rồi bắt đầu.

== Changelog ==

= 1.0.0 =
* Bản đầu: chuyển web app "Báo cáo chi phí" (GitHub Pages + Google Sheets) sang WordPress —
  cùng engine tính toán, thêm đăng nhập PIN, phân quyền, người dùng, ảnh chứng từ, chốt kỳ, nhật ký, tự cập nhật.
