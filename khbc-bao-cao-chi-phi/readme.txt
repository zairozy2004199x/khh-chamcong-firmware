=== Báo Cáo Chi Phí (K&H) ===
Contributors: khh
Tags: chi phí, phân bổ, báo cáo, MISA, kế toán
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.4.0
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

= 1.4.0 =
* **Tab "Chi tiết MISA"** — xem trên màn hình đúng những dòng sẽ nằm trong sheet "<Bộ phận> chi
  tiết" khi bấm Xuất Excel, trước khi xuất. Cùng một phép dựng với file, không phải đường riêng.
* Gập sẵn theo chứng từ (Posh: 21 chứng từ × 66 điểm = 1.386 dòng — đổ hết ra là bức tường số
  không ai soát nổi). Mỗi chứng từ một dòng tóm tắt: tài khoản, số dòng, tổng tiền. Bấm để mở.
* Nút lọc nhanh **chứng từ thiếu TK Nợ/Có** — MISA từ chối cả chứng từ nếu thiếu, nên thấy sớm.
* Nêu rõ các cơ sở không nhận chi phí đã bị bỏ khỏi danh sách, đúng như khi xuất file.

= 1.3.0 =
* **Ô tích "Không nhận chi phí" cho từng cơ sở** (tab Doanh thu). Cơ sở nghỉ / đóng cửa **vẫn ghi
  doanh thu** như cũ, nhưng không nhận chi phí phân bổ; phần chi phí đó **chia lại** cho các cơ sở
  còn lại nên **tổng chi phí bộ phận không đổi một đồng**. Tab "<Bộ phận> chi tiết" bỏ hẳn dòng
  của cơ sở ấy — đẩy lên MISA một bút toán 0đ vẫn là ghi nhận chi phí cho nó.
* Báo **LỖI** nếu mọi cơ sở của một bộ phận đều được tích: khi ấy chi phí của bộ phận sẽ không
  phân bổ về đâu cả, trong khi File tổng báo cáo vẫn cộng đủ.

= 1.2.0 =
* **Tab "<Bộ phận> chi tiết" — tờ nhập MISA.** Sheet "<Bộ phận> T8.2026" phân bổ xong tiền về từng
  điểm, nhưng MISA không đọc được bảng hai chiều ấy: nó cần MỘT DÒNG cho mỗi (khoản × điểm). Tab
  mới bẻ bảng ra thành dòng, đúng mẫu 50 cột của MISA, đặt ngay sau sheet phân bổ của cùng bộ phận.
* Số chứng từ `NVK<mã><ngày cuối tháng><tháng><stt>` (vd `NVKPOSH310801`) — mã theo bộ phận, gieo
  sẵn POSH · JP · FZ · EV · FA · TU · PBMN, sửa được.
* Hai cột lương nay có ô **tài khoản** riêng (trước không có chỗ nào khai, nên mọi dòng lương lên
  MISA đều trống TK Nợ/Có — MISA từ chối cả chứng từ).
* Cảnh báo sớm ở màn Kiểm tra khi một khoản chưa đủ cặp tài khoản Nợ/Có.

= 1.1.0 =
* Hiện ở **Trang IT** (khmatrix.com/it) cạnh Chấm Công · Ghế Massage · Sao Kê — cập nhật ngay tại
  đó, không phải vào wp-admin. Plugin tự khai tên qua bộ lọc `vhcp_tu_cap_nhat_ds`; trang IT không
  giữ danh sách nào cả nên không phải sửa plugin Ghế.
* Thêm `KHBC_TuCapNhat::ban_moi_nho()` — chỉ đọc ô nhớ, KHÔNG gọi mạng. Trang IT dùng hàm này ở
  mỗi lượt mở trang; gọi thẳng GitHub ở đó là treo trang khi mạng chậm.

= 1.0.0 =
* Bản đầu: chuyển web app "Báo cáo chi phí" (GitHub Pages + Google Sheets) sang WordPress —
  cùng engine tính toán, thêm đăng nhập PIN, phân quyền, người dùng, ảnh chứng từ, chốt kỳ, nhật ký, tự cập nhật.
