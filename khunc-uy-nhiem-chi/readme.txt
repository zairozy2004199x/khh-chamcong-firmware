=== Ủy Nhiệm Chi & Công Nợ (K&H) ===
Contributors: kvah
Tags: ke-toan, uy-nhiem-chi, cong-no, excel
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.2.0
License: GPL-2.0-or-later

Theo dõi ủy nhiệm chi đã đi tiền hay chưa và công nợ phải trả từng nhà cung cấp, đọc thẳng từ
file Excel "Đi ủy nhiệm chi". Cả bộ phận dùng chung một bộ số.

== Description ==

File Excel "Đi ủy nhiệm chi" không có cột nào đánh dấu tiền đã đi hay chưa — chỉ suy được từ
cột *Ngày tạo lệnh* có điền hay không, mà cột đó ghi tự do (`PAYMENT 31/8`, `payment 30/11`).
Plugin này đọc file đó, chuẩn hoá lại rồi cho kế toán đánh dấu và tra cứu.

**Đọc được 3 kiểu bố cục lẫn trong cùng một workbook**

* Sheet tháng 2025–2026 — ủy nhiệm chi (kể cả bản trước 11/2025 thiếu cột *Ngày cần đi tiền*)
* Sheet thuê mặt bằng — mỗi cột tiền (thuê, điện, phí DV, phí BH) thành một khoản riêng
* Sheet `TT TIỀN MẶT` — chi tiền mặt, giữ link hoá đơn

**Chuẩn hoá những chỗ dữ liệu thật hay lệch**

* 2 tài khoản công ty bị ghi thành 16 kiểu (`K&H cũ`, `K VÀ H CŨ`, `KH CŨ`…) → gom về 2 nhóm
* Ngày ghi bằng chữ: `UNC 1/9`, `trước 14/12`, `30-31/12` → ra ngày thật, tự suy năm theo sheet
* Một ô chứa 2 số tiền xuống dòng → cộng lại, không nối thành số khổng lồ
* Ghi chú kế toán lọt vào cột thụ hưởng (`Nộp thừa 25/08`, `cấn trừ…`) → xếp riêng, không tính
  vào công nợ nhà cung cấp
* Dòng `CÔNG TY …` không bị nhận nhầm là dòng `Cộng`

**Công nợ phải trả** — từng nhà cung cấp: tổng phát sinh, đã thanh toán, còn phải trả, chia cột
tuổi nợ (chưa đến hạn / quá hạn 1–30 / 31–60 / 61–90 / trên 90 ngày). Gom được theo Nhà cung cấp,
Bộ phận, Tài khoản chi hoặc Kỳ. Đổi mốc "Tính đến" để đối chiếu theo ngày trong quá khứ.

**7 mẫu biểu in A4**, tự điền từ khoản / nhà cung cấp đang chọn: Ủy nhiệm chi (2 liên, có số tiền
bằng chữ), Giấy đề nghị thanh toán (05-TT), Phiếu chi (02-TT), Biên bản đối chiếu công nợ, Bảng kê
chi tiết công nợ phải trả, Sổ chi tiết thanh toán với người bán (S31-DN, TK 331), Kế hoạch chi
tiền trình ký.

**Cảnh báo tự động**: quá hạn, đến hạn trong 3 ngày tới, nghi trùng chi (cùng số tài khoản + cùng
số tiền + cùng kỳ), thiếu thông tin chuyển khoản.

== Dùng thế nào ==

1. Cài plugin, vào menu **Ủy nhiệm chi & Công nợ** trong wp-admin để lấy đường dẫn trang.
2. Mở trang, đăng nhập lần đầu bằng tài khoản **Admin**, PIN **1111** — đổi ngay ở nút 👤.
3. Thêm người dùng: **Admin** (mọi thứ + quản lý người dùng) · **Kế toán** (nhập Excel, đánh dấu)
   · **Xem** (chỉ tra cứu và in, mọi nút ghi tự ẩn).
4. Kế toán bấm **Nhập Excel** — file lên máy chủ theo mẻ, cả bộ phận thấy cùng một bộ số.
5. Lần đầu: lọc từng kỳ cũ → **Chọn tất cả đang lọc** → **✓ Đã đi tiền**, vài cái bấm là chốt
   xong năm cũ.

Trạng thái đánh dấu lưu theo khoá sinh từ nội dung khoản, **tách khỏi dữ liệu đọc từ Excel** —
tháng sau nhập file mới thì các khoản cũ vẫn giữ nguyên đánh dấu.

== Nhúng vào bài viết ==

`[khunc_app]` hoặc `[khunc_app height="900px"]`

== Yêu cầu hosting ==

Nhập file lớn được chia thành mẻ 600 khoản nên `post_max_size` mặc định là đủ. Nếu hosting vẫn
cắt request, plugin báo thẳng con số và đề nghị nâng `post_max_size` lên 16M thay vì âm thầm ghi
đè bằng danh sách rỗng.

== Changelog ==

= 1.2.0 =
* Bộ áo làm lại cho người NGỒI CẢ BUỔI với nó, không phải cho ảnh chụp: hàng bảng ủy nhiệm chi
  từ 49px xuống 31px, nên một màn 1280px bày 18 hàng thay vì 12 — cùng một bảng, cuộn còn hai
  phần ba.
* Chỉ cột "Nội dung" được xuống dòng; kỳ, bộ phận, ngày và số tiền ghim một dòng nên không còn
  cảnh trình duyệt bẻ "2026-03" làm đôi để nhường chỗ cho cột chữ.
* Hàng chẵn có sọc rất nhạt: bảng rộng tới mép phải thì mắt hay tuột sang hàng khác giữa chừng,
  và đó là cách đọc nhầm số tiền của nhà cung cấp này thành của nhà cung cấp kia.
* Thẻ số liệu đứng thành hàng thật (thẻ tô nền nay cũng có viền, trước để trong suốt nên chúng
  trôi); trên điện thoại xếp 2 thẻ một hàng thay vì 1.
* Ô tích "Chỉ quá hạn" không còn mọc thành khung rỗng to bằng ô nhập ngày.
* Ô nhập trên điện thoại đủ 16px để iOS thôi tự phóng to trang mỗi lần bấm vào.
* Thang chữ còn 7 cỡ (trước 11 cỡ, có cả cỡ lệch nhau nửa điểm), thang giãn cách đi đúng bước
  4px. Thêm `test/giao-dien.test.js` giữ hai bản màu tối — bản "theo hệ điều hành" và bản "bấm
  nút chuyển" — luôn khai giống hệt nhau.

= 1.1.0 =
* Đối chiếu sao kê ngân hàng: nhập sao kê (.xlsx/.csv), trang chỉ đọc dòng tiền RA rồi khớp với
  sổ theo số tiền + số tài khoản người thụ hưởng + ngày. Nhóm "khớp chắc" bật được "đã đi tiền"
  một lượt; mọi nhóm khác đều chờ người quyết.
* Bày ra CẢ HAI CHIỀU LỆCH, kể cả chiều dò tay không bao giờ thấy: giao dịch ngân hàng trừ mà sổ
  không có dòng nào.
* Không tự bật cho khoản kế toán đã đánh tay khác đi — xếp riêng vào nhóm "lệch" để người xem.

= 1.0.0 =
* Bản đầu tiên: đọc file Excel "Đi ủy nhiệm chi" (3 kiểu bố cục), theo dõi trạng thái đi tiền,
  bảng công nợ phải trả kèm tuổi nợ, 7 mẫu biểu in A4, xuất Excel 7 sheet.
* Đăng nhập PIN, 3 vai trò (Admin / Kế toán / Xem), nhật ký thao tác.
* Dữ liệu dùng chung trong MySQL; trạng thái đánh dấu tách riêng khỏi dữ liệu Excel nên nhập lại
  file không mất.
* Tự cập nhật từ nhánh GitHub, hiện ở Trang IT.
