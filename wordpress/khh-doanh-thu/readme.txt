=== K&H — Báo cáo doanh thu FABi ===
Contributors: khh
Tags: doanh-thu, bao-cao, fabi, ipos, pos
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.28.0
License: Proprietary

Nạp file "Báo cáo bán hàng" xuất từ máy POS FABi (iPOS) và dựng báo cáo doanh thu theo
ngày, cửa hàng, khung giờ, hình thức thanh toán, tại chỗ/mang về và món bán chạy.

== Description ==

Cài xong vào menu **Doanh thu FABi** trong trang quản trị, và mở được bằng **đường link
ngoài**: mặc định `tên-miền/doanh-thu-hcm` (đổi được ở ô dưới báo cáo, hoặc khai cứng
`define( 'KHH_DT_SLUG', 'doanh-thu-hcm' );` trong wp-config.php). Ngoài ra còn nhúng được
vào trang bất kỳ bằng shortcode `[khh_doanh_thu]`.
Đang bật plugin **Nền tảng K&H** thì báo cáo cũng hiện thành một ứng dụng trong thanh bên.

Số liệu lưu trong bảng riêng `wp_khh_dt_ngay`, gộp sẵn theo ngày × cửa hàng nên mở nhanh
và không phình: 15 cửa hàng × 365 ngày ≈ 5.500 dòng một năm. Nạp lại cùng một ngày thì
ghi đè ngày đó, không cộng dồn.

**Hai cái bẫy của file FABi đã xử lý sẵn:**

1. File có một trang cho mỗi cửa hàng VÀ một trang "Tất cả cửa hàng" gộp — plugin luôn
   đọc trang gộp.
2. Trong trang có chèn dòng "Tổng" cộng dồn (cột Thời gian để "-"). Cộng thẳng cột
   Thành tiền là doanh thu **gấp đôi** — plugin bỏ các dòng đó và báo lại đã bỏ bao nhiêu.

Doanh thu lấy cột **Tổng tiền** (thực thu); không có thì Thành tiền − (Chiết khấu + Giảm giá).
Số hoá đơn đếm theo **Mã hoá đơn** không trùng, không lấy số dòng.

Đã kiểm với bản xuất thật 14–20/06/2026 của 15 cửa hàng: 17.360 dòng dữ liệu, bỏ 1 dòng
"Tổng", doanh thu 1.748.615.000 ₫, 12.860 hoá đơn, cao điểm 19–20h.

== Installation ==

1. Trang quản trị → Plugin → Cài mới → Tải plugin lên → chọn file zip.
2. Kích hoạt.
3. Vào menu **Doanh thu FABi** → **Nạp báo cáo** → thả file xuất từ FABi.

Máy chủ cần `ZipArchive` và `XMLReader` để đọc .xlsx (hầu hết hosting đều có sẵn).
Thiếu thì xuất bản CSV từ FABi, plugin vẫn đọc được.

Ai được nạp và xoá số liệu: người có quyền `edit_posts` trở lên. Ai đăng nhập cũng xem được.

== Sao kê ngân hàng — tiền thực nộp ==

Đối soát báo cáo cơ sở với máy POS cho kết quả khớp **0 đồng suốt 14/14 ngày** — vì báo cáo ấy
chép ra từ chính máy POS. Hai con số cùng một nguồn thì so với nhau mãi mãi bằng không, kể cả khi
có thất thoát. Tiền thực nộp mà cũng để cơ sở tự gõ thì y hệt.

Nên cột **Thực nộp** lấy từ sao kê ngân hàng:

1. **Nạp báo cáo → Sao kê ngân hàng** → thả file .xlsx/.csv tải từ ngân hàng. Chỉ tiền vào được
   lấy; nạp lại cùng kỳ không cộng dồn (khoá theo mã giao dịch).
2. **Quản trị → Sao kê ngân hàng** → khai **mã nộp tiền** của từng cơ sở (mã người nộp gõ vào nội
   dung chuyển khoản) và **giờ cắt**. Khoản nào chưa nhận ra cơ sở thì màn gom sẵn theo mã đọc
   được, chọn cơ sở một cái là xong cả nhóm.
3. Bảng đối soát bày số ngân hàng nhận được, và bày chỗ lệch với số cơ sở khai.

== Cửa hàng trưởng vào bằng PIN chấm công ==

Cửa hàng trưởng KHÔNG cần tài khoản WordPress. Ở trang **Nhân sự** của plugin Chấm công có cột
**Quản trị báo cáo cơ sở** — bấm *Đẩy* là người ấy có mặt trong sổ người dùng của báo cáo, mang
theo tên · PIN · mã cơ sở. Họ mở link báo cáo, gõ chính PIN chấm công đang dùng hằng ngày, và chỉ
thấy cơ sở của mình.

**Đẩy sang chỉ là đẩy người — vai cấp ở đây.** Người vừa đẩy sang là *chưa cấp*: vào xem được,
chưa nhập được. Vào **Quản trị → Người đẩy từ trang Nhân sự — cấp vai**, chọn *Nhập báo cáo* (cửa
hàng trưởng) hay *Nhập và duyệt* (kế toán, quản lý: xem tổng mọi cơ sở, nạp được file POS) rồi Lưu.
Đẩy lại bên Nhân sự không xoá vai đã cấp.

Ai phụ trách **hai cơ sở** thì tích đủ hai cơ sở cho họ ở trang Nhân sự — bên này tự theo, và họ
nhập báo cáo được cho cả hai. Còn nếu một điểm bán bị máy POS tách thành hai quán (khu vui chơi
và quán cà phê cùng một chỗ) thì ở bảng Ghép cơ sở **tích cả hai quán cho cùng một mã**. Ai vai **duyệt** (kế toán, quản lý) thì xem tổng mọi cơ sở, không
cần ghép mã.

Một việc phải khai một lần: **Quản trị → Ghép cơ sở**. Sổ nhân sự gọi quán bằng mã (`FZ_ADV_TP`),
máy POS gọi bằng tên dài ("TuTu Train - Aeon Tân Phú …"), không có cách nào đoán hộ. Mã chưa ghép
thì người của cơ sở đó vào được nhưng thấy rỗng — và màn nói thẳng ra lý do, chứ không lặng lẽ
cho họ xem cả 15 quán.

Gỡ ở trang Nhân sự là hàng biến mất và phiên đang mở tắt ngay. Đổi PIN, đổi vai hay chuyển cơ sở
bên nhân sự thì bản sao bên này tự theo.

== Đường API FABi ==

Khi iPOS cấp Client ID / Secret Key / Token (gọi 19004766 hoặc support@ipos.vn), khai vào
`wp-config.php`:

    define( 'KHH_DT_API_BASE',  'https://<máy chủ iPOS cấp>' );
    define( 'KHH_DT_API_PATH',  '/api/v1/sale/accounting' );
    define( 'KHH_DT_CLIENT_ID', '...' );
    define( 'KHH_DT_TOKEN',     '...' );

Rồi gọi `khh_dt_bat_dong_bo()` một lần để đặt lịch đồng bộ mỗi giờ. Khoá không nằm trong
code, không lên GitHub. Khi có tài liệu iPOS, chỗ duy nhất phải sửa là tên tham số ngày và
chỗ lấy mảng dòng trong JSON trả về, trong hàm `khh_dt_dong_bo_api()`.

== Changelog ==

= 1.75.1 =
* Bớt nút "Hôm qua" cạnh ô ngày (tab Nhập báo cáo ngày) — anh Thắng 26/09/2026: *"lúc được, lúc
  không. chỉ hiện nút hôm nay thôi, với nút to lên tí"*. Ô ngày vốn đã mặc định hôm qua, đủ dùng;
  chỉ giữ nút "Hôm nay" cho ca hay cần (đang xem hôm qua mà có việc phải nhập luôn hôm nay), và
  làm nút to hơn bản 1.75.0 (bản đó lỡ tay thu nhỏ hơn cả cỡ mặc định của nút viền).

= 1.75.0 =
* Tab Nhập báo cáo ngày: thêm hai nút **"Hôm qua" / "Hôm nay"** cạnh ô chọn ngày — anh Thắng
  26/09/2026: *"nhân viên cứ chọn lộn ngày"*. Ô lịch gõ tay (spinner tháng/ngày/năm tách rời) dễ
  bấm nhầm; hai nút chọn thẳng theo nhãn, và nút khớp với ngày đang xem được tô đậm để nhìn một
  cái biết ngay mình đang xem ngày nào, không phải tự cộng trừ trong đầu rồi đoán.

= 1.74.0 =
* Bảng nhận mặt (Cấu hình → Sao kê): gõ mã nộp tiền cho một cơ sở, cơ sở khác đã gõ TRƯỚC vẫn giữ
  đúng, mà mã vừa gõ lại "không lưu được" — anh Thắng 26/09/2026. Gốc: một mã chỉ được thuộc về
  ĐÚNG MỘT cơ sở (luật đúng, tránh đoán bừa khi hai cơ sở lỡ khai chung mã) — nhưng khi hai cơ sở
  trong CÙNG một lượt gửi khai trùng mã, cơ sở gửi SAU bị bỏ mà máy chủ báo "đã gán lại" chung
  chung như không có chuyện gì, không nói RÕ mã nào trùng, trùng ở đâu. Nay máy chủ tự đếm số mã
  gửi lên so với số mã thực lưu được, phát hiện trùng thì báo ngay trên màn: mã gì, trùng ở những
  cơ sở nào. Cũng nhân dịp sửa một chỗ dò lỗi CSDL đọc nhầm lỗi CŨ còn sót lại từ một câu truy vấn
  không ăn nhập, khiến "gán lại" có thể báo lỗi giả dù thực ra đã lưu trót lọt.

= 1.73.0 =
* Sửa "Khong lưu đươc": gõ mã nộp tiền ở bảng nhận mặt (Cấu hình → Sao kê), bấm "Lưu và gán lại"
  xong tải lại thì ô lại trống — anh Thắng 26/09/2026. Gốc: cột `nhan` (thêm ở 1.72.0) chỉ có
  thật trên site sau khi máy chủ chạy xong lượt nâng cấp bảng; site chưa kịp chạy (hoặc chạy chưa
  xong) thì câu SQL ghi/đọc sao kê tham chiếu một cột chưa tồn tại — MySQL âm thầm làm hỏng CẢ
  CÂU (không phải chỉ mất mỗi ô nhan), khiến cả việc nạp sao kê lẫn "gán lại" coi như không ghi/
  đọc được dòng nào, dù màn hình vẫn báo "thành công" bình thường. Nay tự kiểm cột có thật trước
  khi dùng, thiếu thì tự nâng cấp bảng ngay lúc đó, và vẫn ghi/đọc được (giảm về hành vi cũ) ngay
  cả khi việc tự nâng cấp không kịp — không còn câu SQL nào âm thầm vỡ vì một cột chưa tồn tại.

= 1.72.0 =
* Sao kê ngân hàng: nhãn cơ sở NGƯỜI ĐÃ XÁC NHẬN bên nguồn (Sao Kê Ngân Hàng K&H) giờ được LƯU LẠI
  và dùng lại đúng thứ tự ưu tiên mỗi khi "gán lại" — anh Thắng 26/09/2026: *"chưa thấy sao kê"* /
  *"chưa thấy chỗ đã nộp tiền"*. Trước bản này, nhãn nguồn chỉ được dùng THOÁNG QUA lúc kéo dữ
  liệu về để suy cơ sở, không hề lưu; mỗi lần sửa "bảng nhận mặt" (mã ↔ cơ sở) ở Cấu hình, hệ
  thống GÁN LẠI cơ sở cho MỌI dòng bằng cách đoán riêng từ nội dung + sổ mã của chính plugin này —
  cơ sở nào chỉ khai mã bên Sao Kê Ngân Hàng (không khai lại ở Cấu hình của plugin này) là bị xoá
  sạch cơ sở đã đúng ngay lần gán lại đầu tiên, lùi về "chưa khai mã nộp tiền" dù nội dung không
  hề đổi. Nay nhãn nguồn được giữ lại và thử trước, chỉ khi không có mới đoán theo nội dung như cũ.

= 1.71.0 =
* Nút **"💰 Đã nộp tiền"** ở tab Nhập báo cáo ngày — anh Thắng 26/09/2026: *"Bổ sung nút đã nộp
  tiền (Khi đã nộp thì khóa ô nhập lại)"*. Bấm xong khoá TOÀN BỘ form của đúng ngày × cơ sở đó,
  y hệt lúc hết quyền sửa — khoá VĨNH VIỄN, không tự mở lại được (kể cả gọi thẳng máy chủ, không
  chỉ ô mờ trên màn). Chỉ tài khoản văn phòng (WordPress có quyền biên tập, quản trị, hoặc PIN
  vai "duyệt") mới gỡ được bằng nút "🔓 Gỡ khoá đã nộp" — lần gỡ nào cũng ghi vào lịch sử sửa
  (ai gỡ, lúc nào) để còn tra lại.

= 1.70.0 =
* Quản trị (tài khoản mang capability `list_users`, không có `edit_posts`) giờ được cả **nạp file**
  (POS/sao kê/MoMo) lẫn **nhập báo cáo ngày** — anh Thắng 26/09/2026: *"bổ sung mấy quyền sửa cái
  này cho tài khoản quản trị"*. Trước bản này, role WordPress "quản trị" trên site (khác
  Administrator mặc định, có `list_users` nhưng thiếu `edit_posts`) vào được cả tab Quản trị (Sale
  vé/Bán lẻ, Bóc tách vé, Phân quyền — toàn màn nhạy hơn hẳn nạp file) mà lại bị chối ở cửa nạp
  file/nhập báo cáo ngày. Quản trị nay là tầng quyền cao nhất, bao trùm luôn văn phòng.

= 1.69.3 =
* Bóc tách vé → khách vào: vé/combo **chưa khai** giờ tính **khách vào (POS)** THEO ĐÚNG GỢI Ý đã
  điền sẵn (combo "Trẻ em + Người lớn" → 2), không còn hạ về 1 khách/vé rồi đợi ai đó vào Quản trị
  bấm Lưu mới đúng số (anh Thắng 26/09/2026: *"Điền sẵn thì phải áp dụng luôn chứ, chứ đợi quản lý
  vào điền à, vào thì điền sẵn làm gì nữa"*). Trước bản này, số hiện ở ô "Khách mỗi vé" (Quản trị)
  và số THẬT SỰ dùng để tính Khách vào (POS) là hai con số khác nhau cho tới khi Lưu. Dòng chú
  thích "cách tính" ở tab Nhập báo cáo cũng đổi theo — không còn ghi cứng "tạm 1" khi thực ra đang
  tạm theo 2 hay 3.

= 1.69.2 =
* Đối soát: số "thực nộp" (khi đếm két để trống mà đã khai Nộp quỹ) nay hiện THẲNG ra bảng, ngay
  dưới ô Đếm két — anh Thắng 26/09/2026: *"chứ kế toán sao biết được"* khi số ấy trước đó chỉ nằm
  trong chú thích rê chuột (title). Kế toán soát nhiều dòng một lượt, không rê chuột từng ô.

= 1.69.1 =
* Đối soát: đếm két để trống mà đã khai **Tiền thực nộp về quỹ** thì lấy số nộp quỹ làm số đã xác
  nhận cho "Phải nộp"/"Lệch" (anh Thắng 26/09/2026, ca Aeon Bình Tân 25/09: đếm két=0, nộp quỹ
  4.540.000, ghi chú lệch 240.000 do chốt visa — trước bản này "Lệch" hiện −4.780.000, bằng nguyên
  tiền mặt POS, trong khi cơ sở đã giải trình một khoản lệch nhỏ hơn nhiều). Cột "Đếm két" trên
  bảng vẫn hiện đúng số cơ sở đã gõ (không bịa), kèm chú thích nhỏ khi đang lấy theo nộp quỹ để kế
  toán biết vì sao Lệch không phải phép trừ với 0. Đếm két đã khai một số thật thì số đó vẫn thắng,
  không bị nộp quỹ đè lên.

= 1.69.0 =
* **Chuyển khoản thực thu** — ô mới ở Nhập báo cáo ngày, bản sao của "Tiền mặt đếm trong két" nhưng
  cho phía chuyển khoản (anh Thắng 26/09/2026: *"nhiều khi lệch ngược giữa chuyển khoản và tiền
  mặt… kiểu nhân viên cho khách bấm vé chuyển khoản nhưng khách đưa tiền mặt, hoặc ngược lại, nên
  cần nhân viên nhập số thực"*). Máy POS ghi hình thức thanh toán theo nút nhân viên bấm lúc bán,
  không theo tiền thật cầm trên tay — bấm nhầm thì tiền mặt và chuyển khoản (POS) lệch NGƯỢC CHIỀU
  nhau. Tab Đối soát có thêm cột **Lệch CK** riêng (không gộp vào cột Lệch có sẵn, vì gộp là hai
  chiều lệch triệt tiêu nhau, đúng chỗ đang sai lại hiện ra "khớp"); cột này cũng được xét khi tô đỏ
  cảnh báo một dòng.

= 1.68.6 =
* Kéo sao kê: nhận đúng cơ sở khi bảng sao kê đã có sẵn "Nhãn phân loại" viết gọn (anh Thắng
  26/09/2026: *"fabi chưa đẩy sao kê vào"* — plugin Sao Kê đã gắn đúng nhãn "TÀU GÒ VẤP" cho khoản
  tiền, nhưng Đối soát vẫn không nhận). Hàm nối nhãn gọn với tên cơ sở đầy đủ bên POS trước đây chỉ
  so CÒN NGUYÊN CHUỖI — "TÀU" không phải một mẩu con của "TuTu Train" nên không bao giờ khớp, dù
  cùng một quán. Nay khớp lỏng theo từ chung khi so nguyên chuỗi hụt (cùng luật đã dùng ở màn gợi ý
  ghép tên cho admin), nên nhãn gọn nào cũng nhận đúng cơ sở, không phải khai lại tay.

= 1.68.5 =
* Tab Đối soát: sửa lỗi sâu hơn đứng sau **"nó mờ như này xong từ f5"** (anh Thắng 26/09/2026) — bản 1.68.4 mới sửa được
  phần nổi (thanh lọc bị khoá lúc đang tải); phần chìm là khi một lượt tải LỖI (mất mạng giữa chừng, hosting cắt kết
  nối), màn xoá trắng toàn bộ tab về một dòng lỗi — mất luôn cả thanh lọc lẫn các dải sao kê đối chiếu đang hiện, không
  còn gì để bấm ngoài F5 (đây cũng là lý do "sao kê đối chiếu tự nhiên biến mất"). Nay: (1) một lượt tải bị treo mãi
  (mạng rớt mà không báo lỗi) tự kết thúc sau 25 giây thay vì treo vô thời hạn; (2) lỗi khi đã có số cũ trên màn thì
  GIỮ NGUYÊN màn, chỉ chèn một dòng cảnh báo kèm nút **Thử lại** ngay trong tab; lần đầu mở tab mà lỗi (chưa có gì để
  giữ) vẫn còn nút Thử lại để bấm lại, không phải nạp lại cả trang.
* Dọn một lỗi HTML tiềm ẩn cùng chỗ: nút Lọc của tab Đối soát từng trùng `id` với chính thẻ bọc thanh lọc.

= 1.68.4 =
* Tab Đối soát: sửa **"Không chỉnh được ngày"** (anh Thắng 26/09/2026). Trong lúc màn đang tải lại (mạng chậm, hay một
  lượt tải bị treo), thanh lọc (ô Từ/đến, ô Cơ sở, nút Lọc) bị khoá `pointer-events:none` chung với bảng — không còn
  cách nào tự sửa ngày để thử lại. Giờ chỉ khoá phần bảng số sắp bị thay; thanh lọc luôn bấm/gõ được, kể cả khi đang
  tải hay lượt tải bị treo lâu.

= 1.68.3 =
* Danh mục hàng hoá FABi: **bỏ món đã đóng** (cột Trạng thái = 0) lúc nạp và cả lúc đọc bản đã nạp (anh Thắng 25/09/2026:
  *"anh lỡ nạp cả món đã đóng, có ảnh hưởng gì không"*) — nên bản đang có trên site tự sạch sau khi cập nhật, không phải
  nạp lại; lúc nạp báo "bỏ N món đã đóng".

= 1.68.2 =
* **Nạp danh mục hàng hoá theo từng quán** (anh Thắng 25/09/2026: *"Nạp danh mục hàng hoá có cần chọn cơ sở không"*):
  FABi xuất mỗi quán một file, trước đó mỗi lần nạp là thay cả bản nên nạp quán sau xoá mất quán trước. Nay quán nào có
  trong file (cột Cửa hàng) thì chỉ thay phần quán ấy, quán khác giữ nguyên; cùng mã ở nhiều quán gộp một dòng. File không
  có cột Cửa hàng thì chọn cơ sở ở ô bên (không chọn = món chung mọi quán, thay cả bản như cũ). Khối bày "Đã có: quán N
  món (lúc nạp)", xoá được riêng một quán.
* Hộp thư: nhật ký 5 lượt một trang có Trước/Sau (*"Hiện 5 lệnh 1 trang thôi"*); cùng lý do bỏ qua lặp cho nhiều thư gom
  thành một dòng "×N", cột Chi tiết xuống dòng thay vì kéo ngang.

= 1.68.1 =
* Sổ kho: dòng do tạo tay đã bị bỏ khỏi danh mục nhưng còn dòng sổ (anh Thắng 25/09/2026: *"tạo ra nên phía dưới nó
  không có"*) — giờ có nút **✕ xoá dòng** ngay tại dòng trong bảng kho (món không trong danh mục và FABi chưa bán), và
  danh mục phía dưới liệt kê cả những món ấy với chip đỏ "có dòng sổ · không trong danh mục" để tích lại hay xoá.

= 1.68.0 =
* **Danh mục hàng hoá FABi** (anh Thắng 25/09/2026: *"Tạo hàng mới trên FABi mà chưa bán, thành ra doanh thu nó không có
  hàng đó để nhập kho… các bạn muốn set trước, thấy không có loại vé đó nên tạo trước, dẫn tới dễ sai lệch"*). Quản trị có
  khối mới: nạp bản xuất "Danh sách hàng hoá" của FABi (file "update item in store": Mã món · Cửa hàng · Tên · Tên nhóm ·
  Tên loại · Đơn vị · Giá), tải lại thành CSV, xoá. Từ đó mọi ô chọn có đủ tên đúng FABi kèm mã, kể cả món **chưa bán**:
  tab Kho "Thêm mặt hàng mới" chọn trong danh mục của đúng quán (tự điền mã) thay vì gõ tay; ô "chọn combo đang bán" có
  cả combo chưa bán (ghi "FABi chưa bán"); Bóc tách vé bày vé chưa bán để khai khách/vé trước; Xuất MISA lấy mã hàng từ
  danh mục cho món chưa bán.
* **Xoá hàng sai trên sổ kho** (*"Cho phép xoá hàng sai trên kho hàng"*): người phụ trách quán xoá được món FABi chưa bán
  (khai trước, gõ sai) — xoá khỏi danh mục và xoá cả dòng sổ của quán ấy, sổ ghi động giữ nguyên vết cũ và thêm một dòng
  "xoá mặt hàng". Món FABi đã ghi bán vẫn chỉ văn phòng xoá được (không đếm nữa thì bỏ tích).

= 1.67.4 =
* Bảng Phân quyền PIN gọn lại (anh Thắng 25/09/2026: *"đang bị dãn"*): cột "Cơ sở riêng" gập thành một dòng tóm tắt
  ("Riêng 1 quán: Estella" / "theo mã (5 quán)"), bấm mới xổ danh sách tích; cột "Cơ sở (từ sổ nhân sự)" xuống dòng thay
  vì kéo bảng tràn ngang.

= 1.67.3 =
* Tab Xuất MISA: bảng chứng từ gọn lại (anh Thắng 25/09/2026: *"chỉnh tên cơ sở hiện 2 hàng cho nó gọn lại"*) — tên cơ sở
  gói trong 2 dòng (rê chuột thấy đủ tên); cột Lưu ý tóm theo loại ("chưa Mã đơn vị · 18 món chưa mã hàng · tổng lệch POS")
  thay vì liệt kê từng món, danh sách đủ nằm ở chú thích khi rê chuột và ở "Xem đủ danh sách" phía trên.

= 1.67.2 =
* **Báo cáo hàng (sổ kho) ngay trong tab Nhập báo cáo, chỉ xem** (anh Thắng 25/09/2026: *"Cho báo cáo hàng để nhân viên
  gửi báo cáo hằng ngày, qua bên này chỉ hiện không sửa, sửa bên kho hàng"*). Dưới khối Hàng bán theo máy có khối "Báo cáo
  hàng (sổ kho)": từng mặt hàng với Tồn đầu · Nhập · Máy bán (lẻ + combo) · Huỷ · Tồn tính · Đếm còn · Lệch · Ghi chú, cùng
  số với tab Kho, không có ô nhập; tiêu đề đếm mấy mặt hàng lệch kho, mấy mặt hàng chưa đếm. Nút "tab Kho hàng hoá →" mở
  đúng ngày và cơ sở đang nhập. **Ảnh báo cáo** (Tải ảnh / Chia sẻ Zalo) có thêm phần sổ kho này.

= 1.67.1 =
* **Xuất MISA thành tab riêng** (anh Thắng 25/09/2026: *"Tách TAB XUẤT MISA RA 1 TAB RIÊNG NHÉ"*) — tab "Xuất MISA" chỉ hiện
  cho người được nạp file. Bảng chứng từ và bảng từng dòng **phân trang 20 dòng** (*"giới hạn 20 dòng cho 1 trang"*), nút
  Đánh dấu vẫn tính cả kỳ. Cảnh báo **gom theo loại** (N món chưa có Mã hàng, N cơ sở chưa khai Mã đơn vị, N combo chưa tách…)
  kể vài tên đầu, bấm "Xem đủ danh sách" mới xổ hết — trước đó 40 dòng "Chưa có Mã hàng" choán màn. Dòng FABi "Toàn hệ
  thống" (file không có cột cửa hàng) không còn thành chứng từ.
* **Cơ sở gán riêng cho từng người** (*"tách riêng nhân viên, nó gộp dẫn đến nhân viên chung cơ sở"*): bảng Phân quyền PIN
  thêm cột "Cơ sở riêng" — tích quán cho đúng người ấy rồi Lưu là họ chỉ thấy/nhập những quán đã tích, đè bảng Ghép cơ sở
  theo mã nhân sự; bỏ tích hết là về theo mã. Vai duyệt vẫn xem tổng; đẩy lại từ Nhân sự không xoá phần đã gán. Bảng Ghép
  cơ sở ghi rõ ai đang gán riêng.
* **Tab Cảnh báo bày đủ ngày**, kể cả đã chốt (*"Ngày nào bấm nộp sẽ hiện xanh, chứ không phải ẩn"*): viên xanh = đã chốt,
  vàng = đã lưu chưa chốt, đỏ = chưa nộp quá hạn. Cột Chưa chốt và nhãn tab vẫn chỉ đếm việc treo.

= 1.67.0 =
* **Xuất MISA — chứng từ bán hàng** (anh Thắng 25/09/2026: *"Giờ bắt đầu bóc tách và xuất dữ liệu ra misa"*, kèm ảnh
  chứng từ kế toán gõ tay). Tab Quản trị có khối mới: chọn kỳ / cửa hàng / chưa-đã xuất, xem trước từng chứng từ
  (mỗi ngày × cơ sở một số chứng từ, mỗi mặt hàng một dòng theo **Mã hàng FABi**), tải Excel (hay CSV) đúng 17 cột
  lưới MISA: Ngày hạch toán, Ngày chứng từ, Số chứng từ, Mã khách hàng, Diễn giải, Mã hàng, Tên hàng, TK doanh thu,
  TK công nợ, TK giá vốn, TK kho, ĐVT, Số lượng, Đơn giá, Thành tiền, Đơn vị, Chi nhánh.
* **Combo bóc tách như kế toán đang làm**: dòng vé = (đơn giá − sale phụ) × số vé, không giá vốn; dòng hàng (thạch,
  bim bim, nước…) = sale phụ × số vé chia theo công thức combo của sổ kho (2 thạch/combo → 10.000 một cái), có TK giá
  vốn 6320 + TK kho 1567, đứng ngay sau dòng vé. Combo chưa khai sale phụ hay chưa có công thức thì giữ một dòng vé
  nguyên giá và cảnh báo — máy không tự bịa giá. Có thể khai "đơn giá trong combo" riêng cho một món.
* **Ba bảng khai** ngay dưới: tài khoản & chi nhánh (5110 / 131 / 6320 / 1567 / Khu vui chơi, ĐVT, tiền tố số chứng
  từ), cơ sở (Mã đơn vị như TTAMTA, tên MISA, mã khách), mặt hàng (mã hàng — bỏ trống thì lấy mã FABi, tên MISA, ĐVT,
  đơn giá trong combo). Thiếu mã đơn vị / mã hàng, ngày chưa chốt, tổng dòng lệch doanh thu POS đều được kể ra trước
  khi tải.
* Tải xong hỏi **đánh dấu đã xuất** để lần sau không xuất trùng; xem lại "Đã xuất" và bỏ dấu được. Chỉ người được
  nạp file (văn phòng) thấy khối này. Thư viện Excel chỉ nạp lúc bấm tải; máy chặn thì lùi về CSV.

= 1.66.0 =
* **Lớp áo mới cho cả trang** (anh Thắng 25/09/2026: *"design lại giao diện nhé"*), cùng tông với trang Chi phí:
  thanh đầu xanh đậm có nhãn phiên bản, tab dạng viên (tab đang mở nền xanh, nhãn số Cảnh báo), thẻ số bo tròn có
  vạch màu, thẻ tổng nền xanh; khung có vạch tiêu đề; bảng tiêu đề nền nhạt dính đầu, dòng so le, sáng lên khi rê;
  nút và ô nhập bo tròn, viền sáng khi bấm; cảnh báo / thông báo có vạch màu bên trái; nền tối theo hệ. Điện thoại:
  thanh đầu gọn, tab một hàng cuộn ngang, hàng "Từ … đến" xuống dòng được (hết tràn ngang). Mọi tên lớp, luật 16px /
  44px và thẻ dọc sổ kho giữ nguyên — toàn bộ bài thử màn vẫn xanh. Đã chụp thử máy tính, điện thoại và nền tối.

= 1.65.2 =
* **Form phiếu nhập hàng thành hộp nổi giữa trang** (anh Thắng 25/09/2026: *"hiện form nhập dạng nổi này, trắng tràn
  trang"* — như form Thêm hạng mục bên Chi phí). Bấm **＋ Lập phiếu nhập hàng** là nền mờ phủ trang, hộp trắng ở giữa
  với form và danh sách phiếu; ✕, Esc hay bấm ra nền là đóng; điện thoại tràn cả màn. Hộp treo ngoài tab nên sổ kho
  vẽ lại phía sau (sau khi lưu) mà hộp vẫn đứng, danh sách trong hộp tự cập nhật. `kiem-phieu-nhap-man.js` 16 phép.

= 1.65.1 =
* **Phiếu nhập hàng lên đầu tab Kho, dạng nút bấm mới hiện form.** Anh Thắng 25/09/2026: *"cho phiếu lên đầu, với dạng
  form bấm hiện ra"*. Ngay dưới ô chọn ngày / cơ sở là nút **＋ Lập phiếu nhập hàng** kèm dòng tóm tắt (mấy phiếu ngày
  này, mấy phiếu 90 ngày); bấm mới xổ form và danh sách phiếu, lưu xong giữ mở để thấy phiếu vừa lập. Khối ở cuối tab bỏ.

= 1.65.0 =
* **Phiếu nhập hàng.** Anh Thắng 25/09/2026: *"Tạo phiếu nhập hàng, khi có phiếu nhập hàng nhập vào hoặc đẩy lên nó sẽ
  đẩy vào dữ liệu kho hàng"*. Tab Kho có khối **Phiếu nhập hàng**: số phiếu (tự đánh NH<ngày>-NN, gõ tay được),
  ngày nhập, nhà cung cấp, từng mặt hàng (chọn từ danh mục / món FABi từng bán, hay gõ tên mới) + số lượng + đơn giá,
  ghi chú. Lưu xong **ô Nhập của sổ kho ngày ấy = tổng các phiếu** — ô khoá lại, ghi "phiếu"; số đếm, hàng huỷ, tồn
  đầu đặt lại, ghi chú của dòng kho không bị đụng; mặt hàng mới vào danh mục kho. Danh sách phiếu 90 ngày; xoá phiếu
  (chỉ văn phòng) là ô Nhập tính lại theo các phiếu còn lại. Ghi qua sổ ghi động nên có vết người / giờ.
* **Đẩy phiếu lên từ hệ khác**: cùng cổng `POST /khh-dt/v1/phieu-nhap`, thân JSON `{ ngay, co_so, ncc, ghi_chu,
  so_phieu, dong: [{ mh, sl, gia }] }` (nhận cả `mat_hang` / `so_luong`), cùng phép gác quyền như người lập tay: đúng
  quán mình mới lập được; tên quán / tên mặt hàng lệch dấu cách vẫn về đúng tên.
* Bảng mới `khh_dt_phieu_nhap`, tạo lúc cài đè. `kiem-phieu-nhap.php` 28 phép chạy thật (cộng dồn hai phiếu, xoá tính
  lại, không đụng số đếm, 403 quán khác, xoá chỉ văn phòng, JSON đẩy lên), `kiem-phieu-nhap-man.js` 12 phép.

= 1.64.6 =
* **Tab Nhập báo cáo: món là thành phần combo ghi rõ "lẻ 2 + 6 theo combo → rời kho 8".** Anh Thắng 24/09/2026:
  *"ghi nhận 8 là đúng, nhưng chỗ theo combo là 6, vé lẻ là 2, tổng là 8"*. FABi chỉ ghi phần bán lẻ (2), phần đi
  theo combo nằm ở sổ kho; nay dòng món ở tab Nhập lấy đúng phép tách của sổ kho ghi thêm, và dưới bảng kể mọi
  thành phần rời kho theo combo hôm ấy (kể cả thạch, bim bim không có dòng FABi). Hai màn không bao giờ nói hai số.
  `kiem-quy-trinh.php` +3 phép (so_pos mang kho_combo / kho_tong / theo_combo), `kiem-hang-ban-chot-man.js` +2.

= 1.64.5 =
* **Sửa lỗi 500 khi bấm Lưu ở khối Bóc tách vé** (bản 1.64.4, anh Thắng 24/09/2026: *"bấm lưu nó báo lỗi"*). Đường
  POST dựng lại yêu cầu REST theo kiểu của bộ thử (`new WP_REST_Request( array(...) )`); WordPress thật nhận
  (`$method, $route`) nên nổ. Nay gọi thẳng hàm gói trả về theo tên quán. `kiem-ve-khach.php` thêm phép rà mã plugin
  không được dựng yêu cầu kiểu ấy.
* **Vé bán theo lố "X2" gợi ý nhân đôi**: *"sai, combo này là 4"* — COMBO TRẺ EM + NGƯỜI LỚN + BIM BIM X2 gợi ý 4,
  VÉ TRẺ EM X2 gợi ý 2 (chữ "x" trong tên thường như "Vé Xe điện" không tính). Số đã khai không tự đổi — anh mở khối
  bóc tách, xem lại các vé X2 rồi Lưu.
* **Kho: tên combo và thành phần so lỏng.** *"Đã set combo đó bao gồm nước… đọc theo combo đó bán gì thì hiểu có sản
  nào chứ"* — combo "+ NƯỚC SUỐI" đã khai mà kho vẫn "Theo combo 0" và vẫn nhắc chưa khai, vì tên trong file FABi
  có dấu cách thừa; bảng thành phần còn hai dòng chỉ khác dấu cách. Nay khớp lỏng (gộp dấu cách, bỏ hoa thường) ở
  trừ kho, tách lẻ/combo, nhắc combo chưa khai và SL thực đã chốt; thành phần khai lệch hoa thường về đúng tên danh
  mục; hai lượt khai chỉ khác dấu cách gộp thành một. `kiem-kho.php` +5 phép.
* **Tab Nhập báo cáo bày "Cách tính khách vào (POS)"**: từng vé × khách/vé đang áp, vé chưa khai đánh dấu "tạm 1" — để
  thấy ngay vé nào đang tính mấy thay vì đoán (*"set xong lại sao nó không áp dụng"*: số 4 anh set là cho vé
  "TRẺ EM + NGƯỜI LỚN X2" của quán khác, còn Estella bán "TRẺ EM + NGƯỜI LỚN + BIM BIM X2" — tên khác).

= 1.64.4 =
* **Bóc tách vé → khách: khai theo TÊN VÉ, dùng cho mọi cửa hàng; quán nào khác thì tự set riêng.** Anh Thắng 24/09/2026:
  *"vé đã có sẵn lấy theo và anh đã set vé đó là tính 2 người mà"*, *"khai linh tinh rồi quán có quán không"*, rồi *"để
  nhỡ vé đó riêng thì cơ sở đó chủ động tự set"*. Bản 1.60–1.64.3 nút Lưu ghi vào đúng quán đang chọn, nên combo khai
  2 ở Gò Vấp mà Bình Tân vẫn tạm tính 1 (23 khách thay vì 43). Nay: nút chính **Lưu cho tất cả cửa hàng** ghi bảng
  chung; **Lưu riêng cho quán này** chỉ ghi những vé gõ **khác** số chung, số riêng đè số chung ở đúng quán ấy (màn ghi
  "quán này set riêng (chung: N)"); **Bỏ set riêng, dùng số chung**. Lúc nâng cấp hệ **gộp một lần** các khai theo quán
  cũ về bảng chung (vé chưa có ở bảng chung lấy từ quán), cho cả khách/vé lẫn sale phụ/vé.
* **Tên vé tra lỏng.** *"Hiện đủ vé. Nhập 2 mà vẫn cứ báo sai"*: tên trong file FABi có hai dấu cách, bảng khai một dấu
  cách -> tra không ra. Nay khớp đúng trước, không thì khớp lỏng (gộp dấu cách, bỏ hoa thường); ghi lại tên lệch dấu
  cách đè đúng dòng đang có. Áp cho khách/vé, sale phụ/vé và cả phép tách tiền ở tab Nhập báo cáo.
* **Sửa: cửa hàng trưởng Estella bấm Lưu báo cáo bị "Anh/chị không phụ trách cơ sở này".** Đường ghi gộp hai dấu cách
  rồi so chặt với hồ sơ; đường đọc thì so tên nguyên văn nên vẫn mở được. Nay mọi cổng nhận tên quán (lưu báo cáo, gán
  cơ sở cho tài khoản, bóc tách vé, nhóm món) đều tra về tên nguyên văn trong kho POS, và phép "được đụng quán này" so
  lỏng theo dấu cách.
* Hai bảng cấu hình (bóc tách vé, nhóm Sale vé) đổ thành **thẻ dọc trên điện thoại** — hết cắt cột "Khách mỗi vé",
  "Sale phụ" (ảnh anh Thắng 24/09).
* `kiem-ve-khach.php` viết lại theo luật mới: 59 phép (ca Gò Vấp 42, ca Bình Tân 43, set riêng 63 không lây quán khác,
  bỏ riêng về 43, gộp một lần, tên hai dấu cách); `kiem-quy-trinh.php` +3 (Estella lưu được, quán khác vẫn 403);
  `kiem-ve-khach-man.js` 29.

= 1.64.3 =
* **Tab "Cảnh báo" mới — việc còn treo chuyển sang đây.** Anh Thắng 24/09/2026: *"cho nó sang tab cảnh báo đi, đây
  tab báo cáo mà"* — 91 thẻ ngày chưa chốt chèn đầu tab Nhập là quá ồn cho văn phòng. Tab Cảnh báo gom **theo cơ sở**:
  mỗi quán một dòng (số ngày chưa chốt, số quá hạn, các thẻ ngày), quán quá hạn xếp trước; bấm thẻ ngày là sang tab
  Nhập đúng ngày ấy. Nhãn tab mang số ngày chưa chốt (đỏ khi có quá hạn). Tab Nhập chỉ còn **một dòng** tóm tắt + nút
  sang Cảnh báo, và thanh bốn bước của ngày đang mở.
* **Sale vé / Sale phụ: lưu một lần cho mọi cửa hàng.** Anh Thắng: *"có là đều hết chứ"*. Thêm nút **Lưu cho tất cả
  cửa hàng** (bảng chung, mọi quán chưa khai riêng đều theo), nút **Lưu riêng cho cửa hàng này** chỉ khi quán ấy khác,
  và nút **Bỏ khai riêng, dùng bảng chung** cho quán đang khai riêng (Gò Vấp). Cổng `nhom-ve` nhận `cua_hang=*` và
  `xoa_rieng`.
* **Sửa: tiêu đề khối ghi Tân An mà ô chọn nhảy về Tân Phú, báo "chưa có nhóm món nào".** Tên máy POS có hai dấu cách,
  máy chủ gộp thành một rồi tra không ra. Nay tra về tên **nguyên văn** trong kho POS (`khh_dt_bc_ten_cua`, dùng cho
  cả khối bóc tách vé); ô chọn không còn rơi về quán đầu danh sách.
* **Sửa "lúc thì tự lưu, lúc thì không lưu" ở khối Bóc tách vé** (Estella). Cùng gốc: đường ghi gộp dấu cách, đường
  đọc lấy tên nguyên văn, nên quán tên có hai dấu cách lưu xong đọc lại không thấy. Nay hai đường cùng tra về tên
  nguyên văn; bảng đã lưu dưới khoá lệch vẫn đọc được và được dồn về một khoá ở lượt lưu sau. Áp cho cả bảng nhóm
  Sale vé / Sale phụ. `kiem-ve-khach.php` +4 phép.
* **Sửa: Chrome tự điền chữ "admin" vào ô "Vé giấy đã soát".** Mọi ô nhập báo cáo / hàng bán / sổ kho tắt tự điền.
* `kiem-hang-ban-chot.php` +7 phép, `kiem-hang-ban-chot-man.js` +5, `kiem-quy-trinh-man.js` 32 phép theo bố cục mới.

= 1.64.2 =
* **Sửa "cứ bấm nào nó lại mất bảng" ở ô ngày (Đối soát, Doanh thu).** Anh Thắng 24/09/2026. Chọn ngày Từ xong,
  nửa giây sau tab tự tải lại và vẽ lại cả thanh lọc, nên bảng lịch đang mở của ô "đến" biến mất, chưa kịp chọn
  ngày thứ hai. Từ khi có nút Lọc (1.58.x) thì tự chạy chỉ gây hại: nay hai ô ngày ở tab có nút Lọc **không tự
  chạy** nữa — chọn đủ hai ngày rồi bấm **Lọc** hoặc Enter. Ô ngày lẻ ở sổ kho / thẻ kho vẫn tự chạy như cũ.
  `kiem-o-ngay.js` đổi 2 phép, thêm 2.

= 1.64.1 =
* **Sửa nốt "qua ngày 24 tồn đầu không nhảy" — bước sửa 1.63.1 bỏ sót dòng đã Lưu lại bằng bản mới.** Ảnh anh
  Thắng 24/09/2026 sau khi cài: sáu dòng Gò Vấp "đã sửa 5 lần" vẫn Hàng tồn còn = 0, lệch −148… Vì màn mới
  không còn gửi cột "SL Hàng Bán", mỗi lần Lưu lại cột ấy về trống mà ô Hàng tồn còn hiện sẵn "0" nên 0 giữ
  nguyên — điều kiện "cả hai ô đều 0" không còn khớp. Bản này tra **sổ nhật ký**: số 0 sinh ra từ một lượt
  ghi 0/0 của bản cũ và từ đó chưa bao giờ có lượt đếm ra số khác 0 → là 0 giả, gỡ về trống; từng đếm 3 rồi
  đếm 0 → 0 thật, giữ. Chạy một lần lúc nâng cấp (khoá mới, khoá của 1.63.1 không chặn). Sổ nhật ký giữ nguyên.
* Màn Kho: dòng nào ghi **Hàng tồn còn = 0 trong khi tồn tính còn hàng** thì cảnh báo đỏ ngay dưới bảng, nêu
  tên món và chỉ cách thoát: nếu chưa đếm, xoá trống ô rồi Lưu — ngày mai tồn đầu kéo đúng.
* `kiem-kho.php` +11 phép (vết B gỡ đúng dòng, không gỡ 0 thật, không sửa lịch sử, khoá cũ không chặn).
* **Sửa "Anh/chị không phụ trách cơ sở này" ở tab Kho, phải F5 mới hết** (chị Truyền 24/09/2026). Trang mở từ
  trước khi văn phòng ghép cơ sở / cấp vai nên còn nhớ danh sách quán cũ và sổ kho gọi nhầm quán. Giờ tab Kho
  lấy **quán mình phụ trách** làm mặc định, và gặp câu chối ấy thì tự hỏi lại cấu hình, đổi sang quán mình rồi
  tải lại (một lần); không đổi được thì nói rõ cách tải lại trang. `kiem-kho-man.js` +4 phép.

= 1.64.0 =
* **Quy trình báo cáo cơ sở hằng ngày — tự động theo dõi, nhắc, tổng hợp.** Anh Thắng 24/09/2026: *"làm quy
  trình báo cáo hằng ngày tự động"* — *"báo cáo cơ sở thôi"*. Mỗi ngày bán hàng đi qua bốn bước: **số máy
  POS về** (hộp thư 08:02) → **cơ sở khai** (soát hàng bán, đếm két, khách vào) → **sổ kho** → **Lưu và
  chốt**. Hạn chốt: giờ cấu hình (mặc định **10:00**) sáng hôm sau.
  - Tab Nhập báo cáo: trên cùng là **việc còn treo** của đúng cơ sở mình (ngày chưa chốt trong 7 ngày,
    ngày quá hạn đỏ) — bấm là mở ngày ấy; dưới ô chọn ngày là **thanh bốn bước** của ngày đang mở kèm hạn.
  - Tab Quản trị: khối **Quy trình báo cáo cơ sở hằng ngày** — bật/tắt, giờ hạn, nhìn lùi, địa chỉ nhận
    thư tổng hợp; bảng **hôm qua** từng cơ sở (trạng thái, két lệch, món lệch, quá hạn); nhật ký 30 lượt;
    nút "Tổng hợp và gửi ngay".
  - Lịch hằng ngày lúc giờ hạn (WP-Cron, cùng lưu ý phải bật Cron Jobs hosting như hộp thư): tổng hợp
    mọi cơ sở, ghi nhật ký, gửi **một thư** cho văn phòng (nhiều địa chỉ cách nhau dấu phẩy; trống = chỉ
    ghi nhật ký). Địa chỉ gõ sai báo lỗi, không lặng lẽ bỏ.
  - 🔴 **Hệ không tự điền số thay cơ sở.** Két đếm, khách đếm là số người đếm — lên "nháp" bằng số máy
    là Đối soát lệch 0 tăm tắp trong khi chẳng ai đếm. Tự động ở đây là theo dõi, nhắc, tổng hợp.
  - Cổng REST `quy-trinh` (GET ai cũng gọi được nhưng chỉ nhận việc của cơ sở mình; POST và
    `quy-trinh-chay` chỉ quản trị). `bao-cao-ngay` GET kèm `quy_trinh` (bốn bước + hạn).
  - `kiem-quy-trinh.php` 46 phép chạy thật (bốn trạng thái, hạn theo múi giờ site kể cả ca UTC, danh
    sách việc lọc theo cơ sở, thư đúng người đúng tiêu đề, không thêm dòng báo cáo nào);
    `kiem-quy-trinh-man.js` 26 phép (màn nối đúng cổng, chạy thật veViec/veBuoc). `wp-stub` thêm
    `wp_mail()` ghi lại thư.

= 1.63.1 =
* **Bảng kho tính lại ngay khi gõ.** Anh Thắng 24/09/2026: *"nhập tồn mà sao nó không tính realtime trước
  và sau của ngày đó"*. Gõ vào Tồn đầu, Nhập, Hàng huỷ hay Hàng tồn còn là **Tồn tính** và **Lệch kho**
  của dòng ấy đổi ngay, cùng công thức máy chủ (tồn đầu + nhập − máy bán − combo − huỷ; lệch = hàng tồn
  còn − tồn tính, chỉ khi có gõ). Bấm Lưu vẫn là máy chủ tính và ghi sổ. `kiem-kho-man.js` +9 phép chạy
  thật công thức (đặt lại 148 + huỷ 3 → 140; chưa nạp FABi thì không bịa; đếm 0 khác trống…).
* **Sửa: "qua ngày 24 tồn đầu không nhảy".** Dòng kho ghi trước 1.61.3 còn dính "Hàng tồn còn = 0" và
  "SL Hàng Bán = 0" do ô trống bị ép thành 0; 0 là một mốc đếm nên tồn cuối 23/09 = 0 kéo sang 24/09,
  và mở lại màn 23/09 bấm Lưu vẫn giữ 0. Lúc nâng cấp hệ gỡ **một lần** các dòng có CẢ HAI ô đều 0 về
  trống (dòng chỉ đếm 0 thật thì giữ). `kiem-kho.php` +8 phép: tái hiện lỗi, sửa, không sửa nhầm, chỉ
  chạy một lần.

= 1.63.0 =
* **Tải ảnh báo cáo và chia sẻ lên Zalo.** Anh Thắng 24/09/2026: *"Lưu và chốt xong nó sẽ có thêm tải ảnh
  và chia sẻ báo cáo này lên Zalo"*. Tab Nhập báo cáo, khi ngày đã có báo cáo lưu, hiện hai nút **Tải ảnh
  báo cáo** và **Chia sẻ lên Zalo** (chưa chốt thì nút ghi "Chia sẻ (chưa chốt)"). Ảnh PNG dựng bằng
  canvas từ **chính số đã lưu**: đầu ảnh ngày · cơ sở · ĐÃ CHỐT / CHƯA CHỐT · người · giờ; ô máy POS
  (doanh thu, Sale vé / bán lẻ / phụ, hoá đơn, khách máy, tiền mặt, CK); ô cơ sở khai (đếm két, nộp quỹ,
  bill huỷ, lượt chạy, khách đếm, vé giấy); các dòng lệch (xanh khi 0, đỏ khi lệch); bảng hàng bán với
  SL máy / SL thực (đỏ nếu khác); ghi chú. Không dùng thư viện ngoài.
* Chia sẻ trên **điện thoại** mở khung chia sẻ của máy (có Zalo) kèm ảnh và tóm tắt chữ; **máy tính**
  không có khung ấy thì tải ảnh về và chép tóm tắt vào bộ nhớ tạm để dán vào Zalo web. Tên tệp
  `bao-cao-<ngày>-<cơ sở>.png`.
* **Xoá món thêm tay nếu sai.** *"Cho admin xoá món nếu sai"* — món "mới · FABi chưa bán" có nút **✕ xoá**
  cho người văn phòng (quyền nạp file): hỏi xác nhận, xoá khỏi danh mục và bỏ mã đã gán; cửa hàng
  không xoá được (403). Số đã khai vẫn nằm trong sổ ghi động.
* `kiem-hang-ban-chot-man.js` +9 phép, trong đó tóm tắt chạy thật với số giả (lệch két −100.000, lệch
  khách +5, 1 món lệch máy, ĐÃ CHỐT · người · giờ); `kiem-kho.php` +2 (xoá cần văn phòng), `kiem-kho-man.js` +1.

= 1.62.0 =
* 🔴 **Thêm sản phẩm mới vào danh mục kho theo tên + mã hàng FABi.** Anh Thắng 24/09/2026: *"muốn bổ sung
  thêm sản phẩm mới (lấy tên sản phẩm mà mã theo FABi), để sau đồng bộ nó chạy cùng"*. Hàng mới về chưa
  bán nên FABi chưa có dòng, không tích được từ danh sách; nay khối "Mặt hàng có kho" có ô **Thêm mặt hàng
  mới** (tên đúng như FABi sẽ ghi + **mã hàng** FABi). Món thêm tay được đánh dấu *"mới · FABi chưa bán"*,
  có dòng trong bảng ngay để nhập hàng về. Trình đọc file FABi nay ghi thêm **mã hàng** vào từng dòng
  món; khi FABi bán món mang **đúng mã** (dù tên gõ khác chút) số bán tự rơi vào dòng kho ấy — ở bảng
  ngày, thẻ kho, chuỗi ngày và cả bảng tách lẻ/combo. Một mã không gán được cho hai tên.
* 🔴 **Khai thành phần combo bằng CHỌN, không gõ tên.** *"Hiện combo đang chạy và thành phần đang bán, mới
  hiểu được combo đó có hàng bán gì để trừ, chứ nhập hay ghi sai tên sản phẩm"*. Ô "Món combo" thành
  **danh sách chọn** gồm combo hệ nghi (đang bán mà chưa khai), món FABi có chữ "combo" và combo đã khai
  (kèm số bán 90 ngày); thành phần là **bảng các món trong danh mục kho, mỗi món một ô số lượng**. Chọn
  combo đã khai là công thức điền sẵn vào ô để sửa. Vẫn còn "Khác — gõ tên…" và ô thêm nhanh cho món
  chưa có trong danh mục. Bảng combo đã khai **tô đỏ thành phần không trùng tên món nào** trong kho hay
  FABi (ảnh anh gửi: "bimbim", "nước suối", "thạch" gõ tay — combo bán ra không trừ được dòng nào).
* Cổng `kho-mat-hang` nhận thêm `them_ten`, `them_ma`, `ma`; trả về `ma_hang`.
* Bài kiểm: `kiem-kho.php` +13 phép (thêm món kèm mã, chối trùng mã, mã khớp đổi tên về danh mục ở bốn
  chỗ, REST), `kiem-kho-man.js` +9 phép.

= 1.61.3 =
* 🔴 **Lưu sổ kho mà chưa đếm thì "Hàng tồn còn" phải là trống, không phải 0.** Anh Thắng 24/09/2026 mở
  kho thấy cột Hàng tồn còn toàn 0 và lệch kho = −tồn tính dù chưa ai đếm — *"khi nào nhập hàng tồn còn
  khác tồn tính mới báo lệch kho chứ"*. Lỗi của em: ô trống được đưa vào câu SQL qua `%s`, WordPress đổi
  thành chuỗi rỗng và MySQL ép chuỗi rỗng vào cột số thành **0** — "chưa đếm" thành "đếm được 0". Nay các
  ô có thể trống (Hàng tồn còn, Tồn đầu đặt lại, SL khai cũ) ghi **NULL** thẳng vào SQL. Bệ đỡ thử cũng
  sửa cho giống WordPress thật (trước đây nó tự trả NULL nên bài kiểm xanh oan).
* Những dòng đã lưu hôm nay với Hàng tồn còn = 0 mà anh không đếm: xoá số 0 trong ô rồi bấm Lưu sổ kho
  một lần là về trống.
* `kiem-kho.php` +7 phép (NULL thật trong bảng, lệch kho "—", 0 thật vẫn ra lệch).

= 1.61.2 =
* 🔴 **Đã chọn danh mục thì bảng kho bày đủ danh mục.** Anh Thắng 24/09/2026 tích 21 món ở "Mặt hàng có
  kho", Lưu, mà bảng chỉ có 8 dòng — *"nhiều hàng mà sao lại không hiện sl trong kho"*. Bảng chỉ gom món
  có bán hôm nay, có tồn đã biết hay đã khai hôm nay; món trong danh mục chưa rơi vào ba nguồn ấy bị ẩn,
  tức không có ô để nhập hàng mới về hay đặt mốc. Nay món nào trong danh mục cũng có dòng; số chưa biết
  bày "—", nhập hàng vào dòng ấy là đặt mốc như cũ. `kiem-kho.php` +4 phép.

= 1.61.1 =
* 🔴 **Ghép cơ sở: có quán tích rồi mà "không thêm được".** Anh Thắng 24/09/2026. Nguyên nhân: bảng ghép
  lưu tên quán qua bộ rửa chữ (cắt khoảng trắng đầu/cuối, gộp khoảng trắng đôi), trong khi tên quán FABi
  xuất ra hay có khoảng trắng thừa — tên đã lưu không còn bằng từng ký tự với tên trong số liệu, nên ô
  tích mở lại như chưa tích và người ở mã ấy mở màn thấy rỗng. Nay **lưu đúng nguyên văn tên POS** (so
  lỏng với danh sách quán đang có rồi lấy đúng chuỗi trong danh sách); bảng Ghép **báo tên đã lưu không
  khớp** kèm tên đúng, bấm Lưu bảng ghép một lần là tự sửa; ô tích bày theo so lỏng nên bảng cũ vẫn hiện
  đúng. `kiem-day-bao-cao.php` +5 phép, bài màn +3.

= 1.61.0 =
* 🔴 **Cột "SL hàng bán" trong Kho hàng hoá thành "Hàng huỷ".** Anh Thắng 24/09/2026: *"cột này ghi là hàng
  huỷ (nếu huỷ nhập vào nó trừ ra)"* — *"vì hàng bán lệch đã nhập sẵn bên này rồi"* (bảng Hàng bán theo
  máy POS ở tab Nhập báo cáo). Hàng hỏng, đổ, vỡ bỏ đi gõ vào đây là trừ thẳng khỏi tồn: **tồn tính =
  tồn đầu + nhập − máy bán − combo nhập tay − hàng huỷ**. Cột "Lệch khai" bỏ (không còn khai bán lần hai).
  Cột mới `huy` ở cả hai bảng kho; sổ ghi động và "xem các lượt khai" ghi huỷ.
* 🔴 **Sổ kho lấy SL thực cơ sở đã chốt.** Món nào cơ sở đã gõ *SL thực (nếu lệch)* ở tab Nhập báo cáo thì
  sổ kho dùng đúng số ấy thay số máy (ô Máy bán tổng đánh dấu `*`), kể cả combo (thành phần trừ theo số
  chốt). Không bắt khai lần hai, hai tab nói cùng một con số.
* **Tìm được chỗ cấu hình Sale phụ.** *"Chỗ set Sale Phụ anh không thấy"* — hai khối cấu hình nằm ở tab
  Quản trị (dưới Phân quyền). Hàng số máy ở tab Nhập báo cáo nay có link **"Quản trị → Sale vé / Bán lẻ /
  Sale phụ"** mở thẳng đúng cửa hàng đang nhập; hai khối ấy tải lỗi thì **hiện lỗi ra** thay vì biến mất
  im lặng (trước đây `catch` rỗng).
* Bài kiểm: `kiem-kho.php` +11 phép (huỷ trừ tồn; SL thực đè máy ở bảng ngày, thẻ kho, chuỗi ngày, combo;
  không có bảng báo cáo thì theo máy), `kiem-kho-man.js` viết lại 5 phép, `kiem-hang-ban-chot-man.js` +3.

= 1.60.0 =
* 🔴 **Đặt lại tồn đầu.** Anh Thắng 24/09/2026 mở Tân Phú thấy cả cột Tồn đầu âm (−2, −8, −34) vì hôm
  trước máy bán mà chưa ai đặt mốc — *"cho set lại tồn đầu"*. Ô **Tồn đầu** trong bảng Kho hàng hoá nay
  gõ được (với người có quyền ghi): số mờ trong ô là tồn cuối hôm trước kéo sang; gõ số thật là ngày ấy
  lấy đúng số đó làm tồn đầu (kể cả **0**), tồn tính = số đặt + nhập − máy bán, và các ngày sau nối tiếp
  từ mốc mới. Để trống là giữ số kéo. Đặt lại là một **bút toán mốc** ghi vào sổ ghi động (cột mới
  `dat_dau` ở cả hai bảng kho) — thẻ kho và "xem các lượt khai" thấy ai đặt, lúc nào, đặt bao nhiêu;
  không sửa lịch sử.
* Trên điện thoại ô Tồn đầu vẫn đứng cột 3 dòng Nhập. Chú giải dưới bảng nói cách dùng.
* Bài kiểm: `kiem-kho.php` +9 phép (kéo âm → đặt 50 → 57 → ngày sau 56; đặt 0 khác trống; xoá về số kéo;
  sổ ghi động giữ từng lượt), `kiem-kho-man.js` +5.

= 1.59.5 =
* **Thẻ kho trên điện thoại thành lưới 3 cột cố định.** Anh Thắng 23/09/2026 gửi ảnh: hai số "Máy bán lẻ /
  Theo combo" chen vào giữa làm dòng "Hàng tồn còn" lệch sang phải — *"chiều dài ô bằng chữ để sắp lại
  cho gọn"*. Cột 1 là ô gõ rộng đúng bằng chữ nhãn (~124px), cột 2 lệch (canh giữa), cột 3 số máy (canh
  phải); Nhập ↔ Tồn đầu, SL hàng bán ↔ Lệch khai ↔ Máy bán tổng, Hàng tồn còn ↔ Lệch kho ↔ Tồn tính;
  hai số lẻ/combo thành một dòng chữ nhỏ riêng. `kiem-kho-man.js` viết lại 6 phép bố cục.

= 1.59.4 =
* **Thẻ kho trên điện thoại xếp lại: mỗi ô gõ một dòng — ô gõ · lệch · số máy.** Anh Thắng 23/09/2026:
  *"cho ô nhỏ lại cho thành 1 hàng xem gọn hơn"* · *"cho số theo máy đếm phía sau ô nhập, nếu lệch ở
  giữa"*. Ô gõ còn ~nửa dòng (trước cả dòng), bên phải là con số máy tương ứng (Nhập ↔ Tồn đầu; SL hàng
  bán ↔ Máy bán tổng; Hàng tồn còn ↔ Tồn tính), lệch đứng giữa. Thẻ ngắn đi gần nửa. Bảng máy tính
  không đổi.
* **Đổi tên hai cột**: "NV khai bán" → **"SL hàng bán"**, "NV đếm còn" → **"Hàng tồn còn"**.
* **Sale phụ khai được tới từng LOẠI VÉ, riêng từng cửa hàng.** Anh Thắng 23/09/2026: *"cứ Sale … = loại vé
  (sl) × tiền tại mỗi cửa hàng, khác cách tính sale phụ khác"*. Bảng *Bóc tách vé → khách* của cửa hàng
  đang chọn có cột mới **"Sale phụ mỗi vé (đ)"** bên cạnh "Khách mỗi vé": combo này 20.000, combo kia
  15.000 đều được. Để trống là theo số của **nhóm món** (khối Sale vé / Bán lẻ / Sale phụ, ô ghi sẵn
  "nhóm: 20.000"); gõ **0** là loại vé ấy không có phụ dù nhóm có. Sale phụ = Σ số vé × tiền phụ của
  chính vé ấy.
* Bài kiểm: `kiem-hang-ban-chot.php` +5 phép (tên vé đè nhóm, 0 loại vé ra, xoá về theo nhóm, riêng
  quán), `kiem-ve-khach.php` +3, bài màn +2; `kiem-kho-man.js` +7 phép canh bố cục thẻ và tên cột.

= 1.59.3 =
* 🔴 **Sale phụ = số vé × tiền phụ mỗi vé.** Anh Thắng 23/09/2026 chỉnh lại: *"Cái này là chiết khấu 20k
  cho 1 đơn vé combo 80k"* — trong giá vé combo 80.000đ có 20.000đ là phần phụ (chiết khấu / quà kèm),
  sổ kế toán tách riêng. Cột "Sale phụ?" (ô tích) ở Quản trị đổi thành ô số **"Sale phụ mỗi vé (đ)"**
  theo nhóm món: gõ 20000 ở hàng *VÉ COMBO.* là 36 combo ra 720.000đ; nhóm để trống = không có phụ.
  Sale vé và Bán lẻ không đổi. Cấu hình ô tích của 1.59.0/1.59.1 bị bỏ (nghĩa cũ cộng cả tiền nhóm,
  giữ lại là ra số sai); chưa khai thì Sale phụ = 0, không đoán.
* `kiem-hang-ban-chot.php` viết lại phần sale phụ (36 × 20.000 = 720.000; ô tích cũ rửa ra rỗng; theo
  cửa hàng), bài màn +2 phép.

= 1.59.2 =
* **Ô gõ số trong bảng Kho hàng hoá thu hẹp** (64px, canh phải; ô ghi chú 120px). Anh Thắng 23/09/2026:
  *"cho các ô này nhỏ lại, để tránh lệch cột"* — ô text mặc định của trình duyệt rộng ~150px, tám cột là
  bảng tràn ngang, phải kéo thanh cuộn mới thấy cột Lệch. Trên điện thoại vẫn là thẻ dọc, ô 100% như cũ.
  `kiem-kho-man.js` +3 phép canh bề rộng.

= 1.59.1 =
* 🔴 **Vé chưa khai TẠM TÍNH 1 khách mỗi vé, không bỏ qua.** Anh Thắng 23/09/2026 nhìn Lotte Gò Vấp: hai
  combo tên khác Aeon Tân Phú ("… + THẠCH", "… + BIM BIM") chưa được khai, máy chỉ cộng 12 + 2 = 14 và
  bày như số thật — *"bên khách lại lấy khách vào sai… phải 28 chứ"*. Nay 10 + 12 + 4 + 2 = 28, ô ghi
  **"Khách vào (POS) · tạm tính"** và dòng dưới nói rõ vé nào đang tạm 1 khách/vé; khai 2 cho combo
  là số lên 42. Ngày không có vé nào mới bày "—".
* **Khối Bóc tách vé ở Quản trị có bảng "vé chưa khai" GOM MỌI CƠ SỞ** (90 ngày), kèm cơ sở bán, số đã
  bán, và **điền sẵn gợi ý** — sửa nếu cần rồi bấm Lưu một lần là xong cả chuỗi, không phải dò từng
  quán. Gợi ý nay **đếm chữ chỉ người** trong tên vé ("trẻ em", "người lớn", "bé", "phụ huynh"):
  "TRẺ EM + NGƯỜI LỚN + THẠCH" → 2 (thạch không phải người); không có chữ chỉ người thì dấu "+" → 2,
  còn lại → 1.
* 🔴 **Mỗi cửa hàng một cấu hình.** Anh Thắng 23/09/2026: *"Mỗi cửa hàng 1 cấu hình đi. Để cho dễ"* —
  *"trong tài khoản admin… cứ chọn cửa hàng để cấu hình tránh lẫn lộn"*. Hai khối ở Quản trị (Bóc tách
  vé → khách; Sale vé / Bán lẻ / Sale phụ) dùng **một ô chọn cửa hàng** chung: chọn quán rồi khai, Lưu
  là ghi riêng cho quán ấy; đổi ô chọn là cả hai khối tải lại. Bảng chung của bản 1.59.0 (nếu đã khai)
  chỉ còn là **mặc định cho quán chưa khai riêng**, và màn ghi rõ ô nào đang *"thừa bảng chung"*. Khối
  bóc tách nhắc **quán khác còn vé chưa khai** (bấm tên quán là chuyển sang), và vé chưa khai của quán
  đang chọn được **điền sẵn gợi ý** để một lần Lưu là xong quán ấy. Cổng REST `ve-khach` và `nhom-ve`
  nay đòi `cua_hang` khi ghi.
* Bài kiểm `kiem-ve-khach.php` viết lại phần tạm tính và theo cửa hàng (đúng ca Gò Vấp 28 → 42; khai ở
  Gò Vấp không lẫn sang Tân Phú; sổ phẳng cũ là bảng chung; riêng đè chung), 45 phép; `kiem-hang-ban-chot.php`
  +8 phép theo cửa hàng; hai bài màn +10 phép.

= 1.59.0 =
* 🔴 **Bóc tách vé → khách vào.** Anh Thắng 23/09/2026: *"mình sẽ bóc tách sẵn cho nhân viên, giờ áp
  dụng cho gian Tàu trước"* — *"nếu vé là combo VÉ TRẺ EM + NGƯỜI LỚN tính là 2 người, còn nếu nó là
  trẻ hoặc người lớn riêng thì là 1 người"*. Trước đây ô "Tổng khách vào" (đếm ở cửa) chỉ so được với
  "Số vé bán", mà một vé combo là hai người qua cửa nên số máy luôn thấp hơn số đếm, cột lệch đỏ oan.
* Tab **Quản trị → Bóc tách vé → khách vào** (chỉ văn phòng: vai duyệt / tài khoản biên tập): chọn cơ
  sở (mặc định gian Tàu đầu tiên), bảng món đã bán 90 ngày, vé xếp trước, ô **khách mỗi vé** có gợi ý
  (vé có dấu "+" → 2, vé lẻ → 1), nút *Điền gợi ý vào ô trống*, Lưu. Khai theo **tên món**, dùng chung
  mọi cơ sở bán món ấy. Ô trống = không tính; **0** = vé không ứng với người.
* Tab **Nhập báo cáo** có thêm ô máy **Khách vào (POS)** = Σ vé × khách mỗi vé; cơ sở chưa bóc tách thì
  bày "—" (không bày 0). Lệch nay so **khách đếm ở cửa với khách máy**; chưa bóc tách thì vẫn so với
  số vé như cũ. Vé có bán mà **chưa khai được kể tên** ngay dưới hàng số máy — cộng thiếu một loại là
  lệch đổ oan cho nhân viên.
* Tab **Đối soát** cột "Khách − vé" thành **"Khách − máy"**, dùng khách máy khi có, lùi về số vé khi chưa.
* Cổng REST mới `khh-dt/v1/ve-khach` GET/POST, gác bằng quyền nạp file.
* 🔴 **Hàng bán theo máy — nhân viên soát tại chỗ, lệch mới nhập.** Anh Thắng 23/09/2026: *"hiện số
  lượng hàng bán và thành tiền để nhân viên kiểm kho bán được và chốt bán thực tế đúng máy POS không,
  nếu lệch nhân viên mới nhập, đúng rồi thì để nguyên, chốt đúng xong thì bấm lưu và chốt, để kế toán
  xác nhận"*. Tab Nhập báo cáo có bảng **Hàng bán theo máy POS**: từng món với nhóm/loại, SL máy,
  thành tiền, ô **SL thực (nếu lệch)** và cột lệch tính ngay. Ô trống = đúng máy; chỉ dòng có gõ số
  khác máy mới được lưu (cột mới `mon_thuc` trong bảng báo cáo ngày, đổi cũng vào lịch sử sửa). Tab
  Đối soát thêm cột **Hàng bán**: *khớp máy* / *N món lệch* (rê chuột thấy máy bao nhiêu, thực bao
  nhiêu) / *chưa soát*.
* 🔴 **Ba ô tiền tách theo nhóm món: Sale vé · Sale bán lẻ · Sale phụ.** Anh Thắng 23/09/2026: *"tách
  giúp anh 2 ô là tiền sale vé và tiền sale bán lẻ"*, rồi ô thứ ba *"Tiền Sale Phụ"* (vé lẻ + đồ đóng
  sẵn, tức mọi thứ trừ vé combo chính), và *"thêm cấu hình tích trong cấu hình để tính loại nào sale
  vé, loại nào sale bán lẻ"*. Tab **Quản trị → Sale vé / Bán lẻ / Sale phụ** liệt kê mọi **nhóm món**
  FABi từng bán (90 ngày, mọi cơ sở) với loại món, số lượng, tiền, và hai cột tích **Sale vé?** /
  **Sale phụ?**. Sale vé = nhóm đã tích; **bán lẻ = doanh thu máy − vé** (luôn cộng lại đúng doanh thu
  máy); sale phụ = cộng các nhóm đã tích cột phụ. Chưa tích gì thì tạm theo cột *Loại món* của FABi
  (Vé / Đồ ăn / Đồ uống), dòng nạp trước bản này không có cột ấy thì đoán qua tên/nhóm. Ba ô hiện ở
  hàng số máy tab Nhập báo cáo và ba cột ở Đối soát. Trình đọc file FABi từ nay ghi thêm *Loại món*
  vào từng dòng món.
* Cổng REST mới `khh-dt/v1/nhom-ve` GET/POST (quyền nạp file).
* Bài kiểm: `kiem-ve-khach.php` mới (chạy thật: combo 2 + lẻ 1 = 32 khách, chưa khai → null, vé chưa
  khai được kể tên, khai 0, xoá bằng '', số âm bị chối, route gác quyền), `kiem-ve-khach-man.js` mới
  canh ba màn; `kiem-hang-ban-chot.php` mới (37 phép: tách tiền theo nhóm đã tích đúng ba con số của
  anh 3.420.000 / 170.000 / 710.000, rửa số thực, lệch so lại với máy, chưa soát ≠ soát rồi khớp hết,
  lưu/đọc mon_thuc), `kiem-hang-ban-chot-man.js` mới (20 phép canh ba màn).

= 1.58.3 =
* 🔴 **Vai cấp tự động theo lối cũ được đánh dấu để kiểm.** Anh Thắng 23/09/2026 gửi ảnh chị Thảo —
  cửa hàng trưởng vào bằng PIN — thấy doanh thu cả 15 quán và có nút Nạp báo cáo: *"nhân viên quản
  lý cửa hàng nào thì hiện doanh thu cửa hàng của mình thôi"*. Máy chủ vốn cắt số liệu theo cơ sở
  ngay trong câu truy vấn; chị thấy hết vì mang vai **duyệt** do bên Chấm công tự suy trước 1.58.0
  (vai chấm công quy về Quản lý), mà duyệt nghĩa là xem tổng. 1.58.0 cố ý không đụng hàng cũ nên
  vai suy sai vẫn nằm đó im lặng.
* Lần đầu chạy bản này, mọi người **đang có vai** (chỉ có thể là do lối cũ cấp) được ghi vào danh
  sách "cần kiểm". Bảng Phân quyền bày cảnh báo đầu bảng kể tên họ, ai đang duyệt thì tô đỏ *"đang
  xem mọi cơ sở"*, từng hàng ghi *"vai cấp tự động lối cũ — kiểm rồi Lưu"*. Bấm **Lưu** (kể cả giữ
  nguyên vai) là hết cờ; đẩy lại từ Nhân sự không xoá cờ. **Không tự hạ vai ai** — kế toán ở mã văn
  phòng cũng nằm trong danh sách, hạ nhầm là người cần xem tổng lại thấy rỗng.
* Cột cơ sở của người vai duyệt nay ghi rõ *"xem tổng MỌI cơ sở"*, và nếu mã của họ là một quán thì
  nhắc *"nếu là cửa hàng trưởng thì chọn Nhập báo cáo"*.
* 🔴 **Nút "Lọc" cạnh hai ô ngày Từ/đến** ở tab Doanh thu và tab Đối soát. Anh Thắng 23/09/2026:
  *"chọn ngày nó ko tự ra, thêm nút tìm kiếm để nó chạy ngày lọc"*. Bấm Lọc (hoặc Enter trong ô
  ngày) là đọc cả hai ô một lượt rồi chạy, kể cả khi giá trị không đổi; thiếu một ô thì báo thay vì
  đi hỏi một khoảng dở. Ô ngày vẫn tự chạy khi chọn xong như 1.44.
* 🔴 **Tab Doanh thu không còn nuốt lượt gọi sau.** Nguyên nhân thật của chuyện "chọn ngày không
  ra": `tai()` gặp lượt đang tải là bỏ luôn lượt mới — đổi Từ rồi đổi đến ngay là lượt hai mất im
  lặng, thanh ngày ghi khoảng mới mà số vẫn của khoảng cũ. Nay đánh số lượt như tab Đối soát: lượt
  về trễ thì bỏ, lượt mới nhất luôn được vẽ.
* Bài kiểm: `kiem-day-bao-cao.php` +8 phép (đánh dấu một lần, cờ theo người, Lưu là hết cờ, đẩy
  lại không xoá cờ, gỡ người mất cờ), `kiem-phan-quyen-pin-man.js` +4 phép, `kiem-o-ngay.js` +12 phép
  (nút Lọc hai tab, `tai()` không bỏ rơi lượt, `locTay` chạy thật ba tình huống).

= 1.58.2 =
* 🔴 **Thoát không còn nhảy sang trang WordPress.** Anh Thắng 23/09/2026: *"đăng xuất ra nó nhảy ra
  trang wordpress"*. Người vào bằng tài khoản trước đây thoát qua link `wp_logout_url()` → trình
  duyệt bị đưa sang wp-login.php và (tuỳ nonce, tuỳ plugin khác, tuỳ link đẹp) không quay lại. Nay
  nút **Thoát** dùng chung cho cả hai lối: máy chủ huỷ phiên WordPress (`wp_logout()`) lẫn phiên PIN
  ngay trong lượt REST `dang-xuat`, màn tải lại **đúng địa chỉ đang đứng** và hiện ô gõ PIN, kèm
  link *đăng nhập bằng tài khoản* cho người văn phòng. Link WordPress cũ chỉ còn là đường lùi khi
  REST bị plugin bảo mật chặn.
* `kiem-thoat-dang-nhap.py` viết lại theo lối mới (một nút, máy chủ tự thoát, tải lại trang sau khi
  huỷ phiên WordPress vì nonce cũ đã chết).

= 1.58.1 =
* **Bảng "Phân quyền nộp báo cáo" lên ĐẦU tab Quản trị.** Anh Thắng 23/09/2026 mở tab ra thấy Ghép cơ
  sở choán cả màn và hỏi *"Tab Phân Quyền bên Fabi chưa có"* — bảng cấp vai (1.58.0) nằm dưới, phải
  cuộn mới thấy. Việc làm thường (cấp vai) đứng trên việc làm một lần (ghép mã); tiêu đề đổi thành
  "Phân quyền nộp báo cáo — người đẩy từ trang Nhân sự". Bài kiểm tĩnh thêm 2 phép canh thứ tự.

= 1.58.0 =
* 🔴 **Đẩy người từ trang Nhân sự sang CHỈ LÀ ĐẨY NGƯỜI — vai (nhập / duyệt) cấp ở tab Quản trị bên
  này.** Anh Thắng 23/09/2026: *"đẩy dữ liệu nhân sự là cửa hàng trưởng từ danh sách nhân sự qua
  để anh phân quyền nộp báo cáo, vẫn như chi phí, chỉ đẩy nhân sự qua, chứ không phân quyền nhiệm
  vụ trong đó, mà do trang tự phân quyền"*. Trước đây bên chấm công tự suy vai (Admin/Quản lý/Kế
  toán → duyệt, còn lại → nhập) và mỗi lần đẩy lại là **ghi đè** vai bên này: ai đẩy sang là nhập
  được ngay chưa ai cấp, và cấp xong bên kia sửa hồ sơ một cái (đổi PIN, thêm cơ sở) là vai bay.
* **Người mới đẩy sang mang vai "chưa cấp"**: đăng nhập được bằng PIN, thấy cơ sở mình, nhưng mọi ô
  nhập khoá và dòng trạng thái nói thẳng *"chỉ xem — chưa được cấp quyền nhập, nhờ quản trị cấp ở
  tab Quản trị"*. Trường `vai` bên chấm công gửi kèm (bản cũ vẫn gửi) **bị bỏ qua**.
* **Tab Quản trị có bảng mới "Người đẩy từ trang Nhân sự — cấp vai"**: từng người với mã, cơ sở (mã
  nhân sự + tên POS đã ghép, hoặc nhắc chưa ghép), ô chọn *Chưa cấp (chỉ xem) · Nhập báo cáo · Nhập
  và duyệt*, nút Lưu. Cấp xong là phiên đang mở của họ nhập được ngay (vai đọc lại từ bảng mỗi
  lượt). Ai mất PIN vì trùng người khác được nêu đỏ ngay hàng đó.
* **Đẩy lại không xoá vai đã cấp** — đẩy lại chỉ cập nhật tên, PIN, cơ sở. Thu vai thì chọn "Chưa
  cấp" rồi Lưu; gỡ hẳn thì vẫn bấm Gỡ ở trang Nhân sự.
* Vai lạ gửi tới cổng cấp vai bị **chối** (trước đây cổng đẩy lặng lẽ quy về "nhập" — một chữ gõ sai
  mà thành được nhập). Cổng REST mới `POST khh-dt/v1/nguoi-vai` (`ma_nv`, `vai`), chỉ quản trị viên.
* Hàng đã có trên hosting (đã cấp theo lối cũ) **không bị đụng** — chỉ người đẩy MỚI là chưa cấp.
* Bài kiểm: `kiem-day-bao-cao.php` viết lại theo lối mới (52 phép: đẩy kèm vai vẫn là chưa cấp, đẩy
  lại không đổi vai, vai lạ bị chối, sổ PIN cho tab Quản trị không lộ PIN); `kiem-quyen-nap.php`
  thêm 3 phép (PIN chưa cấp → quyền rỗng, không được nạp); `kiem-phan-quyen-pin-man.js` mới 20 phép
  canh màn và cổng (cổng đẩy không đọc `vai`, route có gác quản trị, ô chọn có mục chưa cấp).

= 1.57.0 =
* 🔴 **Thư không đính kèm tệp thì hệ tìm LINK TẢI trong thân thư và tải về.** FABi gửi kiểu này:
  thư chỉ có chữ "Dữ liệu báo cáo hàng ngày… Báo cáo D05: Bán hàng" và một nút *Tải xuống file*.
  Lượt chạy thử của anh Thắng ra *"thư không có tệp đính kèm (4 thư)"* — đúng như ảnh hộp thư đã
  lộ từ trước (không có biểu tượng kẹp giấy).
* **Chỉ tải link `https`, ở tên miền của người gửi hoặc tên miền gõ ở ô mới "Tên miền link được
  tải".** Link trỏ tên miền lạ thì nhật ký **nêu tên miền ấy** để thêm; thư đã qua gác người gửi
  vẫn có thể bị chèn link lạ (chuyển tiếp, chữ ký), tải hết là đem máy chủ đi gõ cửa bất kỳ đâu.
* 🔴 **Nhận ra link đòi đăng nhập.** Link ấy trả mã 200 hẳn hoi nhưng thân là **trang web** đăng
  nhập, không phải bảng tính — hệ phân biệt bằng cả Content-Type lẫn mấy byte đầu (`.xlsx` là tệp
  zip, bắt đầu bằng `PK`) và nói thẳng *"link trả về TRANG WEB — nhiều khả năng đòi đăng nhập"*,
  thay vì đem trang HTML đi đọc như xlsx rồi hỏng ở tận bộ đọc.
* Tên tệp lấy từ `Content-Disposition` → đuôi đường dẫn → đặt theo kiểu nội dung. Tệp từ link và
  tệp đính kèm **đi chung một hàm nạp** (`khh_dt_thu_nap_noi`), không lệch nhau.
* Thư FABi chưa nạp được thì **không đánh dấu đã đọc** — sáng mai thử lại, không mất.
* Nhật ký hiện **link** ở dòng bỏ qua. `kiem-hop-thu.php` lên **99 phép** (có lượt chạy thật với
  hộp thư giả: link tên miền lạ, link đòi đăng nhập), `kiem-hop-thu-man.py` lên **20 phép**.

= 1.56.0 =
* 🔴 **"Xem 4 thư, nạp được 0 tệp" giờ nói VÌ SAO.** Câu báo sau *Lấy thư ngay* gom lý do bỏ qua
  theo nhóm (người gửi không trong danh sách / không có tệp / …) và **liệt kê địa chỉ gửi bị
  chối**. Anh Thắng gõ `Fabi` — tên hiển thị — vào ô địa chỉ, hệ chối cả 4 thư mà không nói gì.
* **Nút "＋ Thêm <địa chỉ>"** ngay dưới nhật ký cho từng địa chỉ bị chối ở lượt gần nhất: bấm là
  ghép vào ô *Chỉ nhận thư từ* (không trùng) và **Lưu luôn**. Hết phải cuộn tìm, chép, gõ lại.
* **Cảnh báo đỏ** khi ô *Chỉ nhận thư từ* có mục **không có `@`** — đó là tên hiển thị, không phải
  địa chỉ. Hệ **cố ý không khớp tên hiển thị**: tên ấy ai cũng đặt được, nới ra là kẻ lạ đặt tên
  "iPOS FABi" rồi gửi .csv thẳng vào kho doanh thu. Chỗ sửa là chỉ đường, không phải nới cửa.
* Thêm seam cho bài thử cắm hộp thư giả vào `khh_dt_thu_lay()` — lần đầu vòng lọc người gửi /
  đánh dấu đã đọc được chạy **thật** trong bài kiểm. `kiem-hop-thu.php` lên **78 phép**,
  `kiem-hop-thu-man.py` lên **17 phép**.

= 1.55.0 =
* **Hộp thư: chế độ "hằng ngày, một lượt lúc HH:MM"** — mặc định **08:02**. FABi gửi báo cáo đúng
  08:00 mỗi sáng, nên kéo mỗi 2 giờ là 11 lượt nối IMAP vô ích một ngày. Chế độ "mỗi N giờ" vẫn
  giữ cho nguồn gửi bất chợt.
* 🔴 **Mốc chạy tính theo múi giờ của site, không phải UTC của máy chủ.** Đặt 08:02 mà tính theo
  UTC là chạy 15:02 giờ Việt Nam, trễ 7 tiếng, trong khi màn vẫn ghi "lượt sau 08:02". Bài thử
  ép thẳng "bây giờ 01:00 VN" và chốt mốc ra 08:02 VN cùng ngày.
* Quá hạn theo chế độ: hằng ngày cho trượt 26 giờ rồi mới kêu; theo giờ vẫn hai nhịp.
* Giờ gõ sai (`25:99`) thì **giữ giá trị cũ**, không lưu rác rồi lịch lặng lẽ rơi về mặc định
  trong khi màn vẫn hiện thứ người ta gõ.
* **Bệ đỡ bài thử**: thêm WP-Cron (`wp_schedule_event`…) có seam `$GLOBALS['VHCP_LICH']` và
  `site_url()`. Có seam thì chốt được **lịch đặt ra đúng mốc, đúng nhịp, tắt là hết lịch** — trước
  đây không bài nào chạm tới `khh_dt_thu_dat_lich()`.
* `tools/test/kiem-hop-thu.php` lên **65 phép**, thêm `tools/test/kiem-hop-thu-man.py` (8 phép).

= 1.54.0 =
* **THẺ KHO** — bấm vào tên mặt hàng trong sổ kho là mở thẻ kho của nó: **từng ngày** tồn đầu ·
  nhập · máy bán · combo tay · tồn tính · đếm · lệch · **tồn cuối**. Anh Thắng: *"tồn kho ngày
  đó bao nhiêu, bán bao nhiêu, tồn bao nhiêu"* — màn ngày chỉ cho xem một ngày một lúc, muốn
  thấy hàng chạy thì phải có thẻ kho. Chọn được khoảng ngày; mặc định 30 ngày về trước.
* 🔴 **Thẻ kho và màn ngày dùng chung một lõi chạy chuỗi** (`khh_dt_kho_chay`). Viết bản thứ hai
  là sớm muộn hai màn ra hai số khác nhau cho cùng một ngày, không màn nào sai để lần ra. Bài
  thử đối chiếu thẳng: tồn cuối trên thẻ kho phải bằng tồn đầu ngày sau trên màn ngày.
* Ngày **chưa nạp báo cáo FABi** được đánh dấu ngay trên thẻ, cột máy bán hiện "—"; chuỗi vẫn
  chạy tiếp để kéo tồn sang ngày sau, nạp báo cáo xong tự tính lại.
* **Tab Kho mặc định hôm nay** (trước là hôm qua). Sổ kho là việc cuối ngày — mặc định hôm qua là
  mỗi tối phải tự đổi ngày, ai quên là số đếm hôm nay đè lên hôm qua.
* Sửa một lệch nhỏ giữa hai màn mà bài thử bắt được: ngày nhập đầu tiên, màn ngày báo tồn đầu
  "—" còn thẻ kho báo 0. Nay cả hai báo "—" (chưa biết), còn tính thì vẫn coi kho rỗng.
* `tools/test/kiem-kho.php` lên **120 phép**, `tools/test/kiem-kho-man.js` lên **84 phép**.

= 1.53.0 =
* 🔴 **Nạp file bị chối "Xin lỗi, bạn không được phép làm điều đó" khi vào bằng PIN — đã sửa.**
  `khh_dt_duoc_nap()` chỉ nhận quyền WordPress `edit_posts`, không nhận vai PIN `duyệt`, trong
  khi hàm ghi báo cáo ngay bên dưới đã nhận từ lâu. Nay vai `duyệt` (văn phòng) nạp được; `nhập`
  (nhân viên cửa hàng) vẫn không. Và khi chối thì **nói rõ lý do** — máy chủ đang thấy anh/chị
  là ai, cần gì để được nạp — thay vì câu chung chung của WordPress.
* 🔴 **Sổ kho chỉ hiện hàng đang bán hoặc còn trên kệ**, không kéo cả thực đơn 90 ngày sang mỗi
  ngày. Từ hôm qua chỉ kéo sang mặt hàng **còn tồn đã biết** (khác 0); tồn **âm** vẫn kéo — đó là
  dấu hiệu sai sổ, phải bày ra.
* 🔴 **Ngày chưa nạp báo cáo FABi thì cột "Máy bán" hiện "—" và có câu báo đỏ trên cùng**, không
  in 0 nữa. 0 ở đây nghĩa là *chưa có số*, không phải *bán 0* — người trực nhìn 0 sẽ đếm rồi thấy
  lệch kho bằng đúng số đã bán, rồi tưởng mất hàng. Số đếm vẫn lưu được; nạp báo cáo xong hệ tự
  tính lại cho ngày ấy.
* `tools/test/kiem-kho.php` lên **106 phép**, `tools/test/kiem-kho-man.js` lên **74 phép**, thêm
  `tools/test/kiem-quyen-nap.php`.

= 1.52.0 =
Hai thay đổi học từ ERPNext và Odoo — cả hai đều ghi một **sổ ghi động bất biến** rồi tồn hiện
tại chỉ là tổng của nó: *"đã ghi thì không sửa, sai thì ghi một bút toán bù"*.

* 🔴 **Sổ khai giờ là sổ ghi động — khai lại là GHI THÊM, không ghi đè.** Trước đây khai lại là
  `UPDATE`, chỉ còn người và giờ của lần cuối. Với một sổ sinh ra để bắt thất thoát thì đó là lỗ
  to nhất: người đang bị đối soát tự sửa con số mình đã khai, không để lại dấu vết — đếm thiếu,
  thấy cột lệch đỏ, sửa số đếm cho khớp, sổ xanh. Nay mỗi lượt Lưu ghi thêm một dòng, và dòng
  nào bị khai lại thì màn hiện nhãn đỏ **"đã sửa N lần"**, bấm ra xem đủ các lượt kèm **người và
  giờ**.
* Bảng cũ vẫn giữ nhưng chỉ còn là **bản cộng dồn cho nhanh** (đúng vai *Bin* của ERPNext) — sổ
  ghi động mới là gốc, và **dựng lại được** bản cộng dồn từ sổ bất cứ lúc nào.
* 🔴 **Công thức combo có ngày hiệu lực — sửa hôm nay không viết lại quá khứ.** Trước đây công
  thức được áp **lúc đọc** từ bảng hiện tại, nên sửa một combo hôm nay là số tồn của cả mấy
  tháng trước đổi theo, im lặng: hôm qua sổ cân, hôm nay mở lại đúng ngày ấy thì lệch, mà không
  có gì nói vì sao. Nay mỗi lượt sửa ghi thêm một dòng hiệu lực (theo **từng combo**, đúng lối
  BOM của ERPNext), và mỗi ngày dùng công thức có hiệu lực vào **đúng ngày ấy**.
* Ô **"Áp từ ngày"** mặc định là **hôm nay**. Áp lùi vẫn được — có lúc đúng, như khai muộn một
  combo đã bán từ đầu tháng — nhưng phải tự gõ ngày, vì áp lùi là cố ý sửa lại quá khứ.
* Thành phần để trống = **xoá combo từ ngày ấy trở đi**, không xoá cả quá khứ.
* `tools/test/kiem-kho.php` lên **90 phép**, `tools/test/kiem-kho-man.js` lên **70 phép**.

= 1.51.0 =
* 🔴 **Vá lỗ đọc chéo cơ sở ở sổ kho.** Đường ĐỌC của sổ kho không gác theo phạm vi cơ sở của
  người dùng — cửa hàng trưởng quán này đổi một chữ trên thanh địa chỉ là đọc được sổ kho, tồn
  hàng và phần khai của quán khác. Nay gác bằng `khh_dt_co_so_ds()`, đúng lối `bao-cao-ngay.php`
  đã làm (đường GHI vốn đã có gác).
* 🔴 **Rút lại một kết luận sai của 1.47.0.** Bản ấy khẳng định *"FABi đang tự tách sẵn thành
  phần combo"* dựa trên dấu hiệu "món có số lượng mà doanh thu 0đ". Dấu hiệu ấy **hỏng**, vì
  món được **cộng gộp theo tên** trong mỗi (ngày × cơ sở): mặt hàng vừa bán lẻ vừa nằm trong
  combo sẽ có tổng doanh thu > 0 nên **không bao giờ** lọt vào danh sách — đúng trường hợp cần
  dò thì dò không ra. Thứ lọt vào lại là món **lúc nào cũng 0đ**: hàng cho, khuyến mãi, vé
  online. Nay màn chỉ nêu *"N món máy ghi số lượng mà doanh thu 0đ"*, nói rõ **không phân biệt
  được** hàng cho với thành phần combo, và **vẫn nhắc** khai thành phần combo thay vì chặn.
* Cảnh báo trừ hai lần giữ lại nhưng **hạ đúng mức chắc chắn**: *"xem lại kẻo trừ hai lần"*.
* **Luôn hiện đang xem kho của cơ sở nào**, kể cả tài khoản chỉ phụ trách một cơ sở — trước đây
  ô chọn chỉ vẽ khi có từ hai cơ sở, nên trên điện thoại không còn chữ nào nhắc tới cơ sở.
* **Bệ đỡ bài thử**: `WP_Error` nay nhận tham số thứ ba và có `get_error_data()` — trước đây
  nuốt mất nên không bài nào kiểm được một lượt chối là 403 hay 400.
* `tools/test/kiem-kho.php` lên **69 phép**, `tools/test/kiem-kho-man.js` lên **57 phép**.

= 1.50.0 =
* 🔴 **Chọn mặt hàng CÓ KHO của từng cơ sở.** FABi bán cả BẠC XỈU, CACAO LATTE, COMBO TRÀ CHANH
  GIÃ TAY — đồ pha tại chỗ, không có kho để đếm. Đổ hết vào sổ thì nhân viên phải cuộn qua vài
  chục dòng vô nghĩa mới tới chai nước, và mấy dòng ấy **mãi mãi đỏ** vì chẳng ai đếm chúng bao
  giờ — sổ đỏ vì lý do vớ vẩn là sổ bị bỏ. Nay có khối **"Mặt hàng có kho của cơ sở này"**: tích
  những món có hàng trên kệ, kèm **số lượng bán 90 ngày qua** để biết món nào đáng theo dõi.
  Danh mục lưu **riêng theo từng cơ sở**.
* ⚠️ Mặt hàng ngoài danh mục **mà đã có người khai thì vẫn hiện** — giấu đi là số người ta đã gõ
  biến mất khỏi màn trong khi vẫn nằm trong sổ. Bỏ tích hết rồi Lưu là thôi lọc, bày lại tất cả.
* 🔴 **Hết cảnh cả màn toàn số âm.** Trước đây hệ khởi tồn bằng 0 rồi trừ số bán ra, trong khi
  chưa hề biết trên kệ có bao nhiêu — nên ngày đầu đã ra "BIMBIM LỚN −61", "−139", "COCA COLA
  −14". Số âm ấy không sai một cách thú vị, nó **vô nghĩa**, mà lại tô đỏ cả sổ. Nay chưa ai đặt
  mốc thì tồn đầu và tồn tính hiện **"—"**, và không có lệch để tô đỏ.
* **Đặt mốc bằng một trong hai cách**: đếm tay một lần, hoặc ghi lượt nhập kho đầu tiên. Nhãn
  trên mỗi dòng nay nói thẳng việc phải làm — **"đếm 1 lần để đặt mốc"** thay vì "chưa có mốc".
* `tools/test/kiem-kho.php` lên **61 phép**, `tools/test/kiem-kho-man.js` lên **52 phép**.

= 1.49.0 =
* **Sổ kho bày lại thành thẻ dọc trên điện thoại.** Bảng 12 cột với ba ô phải gõ, trên điện
  thoại là dải cuộn ngang với ô bé bằng đầu ngón tay — mà đây đúng là màn nhân viên dùng hằng
  ngày ngoài cửa hàng. Nay mỗi mặt hàng là một thẻ: tên ở trên, mấy số của máy thu lại thành
  một hàng chữ nhỏ, còn **ba ô phải gõ** (Nhập · SL hàng bán · Hàng tồn còn) nổi lên thành hàng ô
  to, cao 44px, chữ 16px.
* **Cùng một markup, đổi cách bày bằng CSS** — không dựng hai bản HTML. Hai bản thì sớm muộn
  sửa một bên quên bên kia, và bên bị quên sẽ là bên điện thoại, vì lúc lập trình ai cũng nhìn
  màn to.
* Mỗi ô tự mang nhãn của nó (`data-nhan`), nên khi hàng tiêu đề ẩn đi thì không còn con số trần
  nào không biết là số gì. Bài kiểm canh **mọi ô** đều có nhãn.
* 🔴 Vá một lỗ trong chính bài kiểm: `kiem-man-dien-thoai.py` trước đây chỉ quét **khối @media
  đầu tiên**, nên luật của sổ kho nằm ở khối thứ hai không bị canh — hạ ô nhập xuống 14px mà bài
  vẫn xanh. Nay gom hết mọi khối.
* `tools/test/kiem-kho-man.js` lên **43 phép**.

= 1.48.0 =
* 🔴 **Sửa lỗi Safari phóng to trang mỗi lần chạm ô nhập, trên iPhone.** Ô nhập để 14px, mà
  Safari trên iOS **tự phóng to cả trang** khi chạm vào ô có cỡ chữ dưới 16px — không tắt được
  bằng CSS. Nhân viên nhập báo cáo ngoài cửa hàng gõ bằng điện thoại, nên cứ mỗi ô là màn nhảy
  một cái rồi phải vuốt về. Nay ở bề ngang điện thoại mọi ô nhập để đúng **16px**.
* **Ô chạm cao tối thiểu 44px** trên điện thoại (trước là ~34px — ngón tay bấm trượt), và hai
  nút *Lưu* / *Lưu và chốt ngày* chiếm hết bề ngang, khỏi bấm nhầm sang nút kia.
* Bài kiểm mới `tools/test/kiem-man-dien-thoai.py` — **16 phép**, canh cả cỡ chữ ô nhập, chiều
  cao ô chạm, thẻ `viewport` (và **không** được chặn phóng to — chặn là chặn luôn người mắt
  kém), lưới tự xuống cột, và ô số phải gợi bàn phím số chứ không dùng `type="number"`.

= 1.47.0 =
* 🔴 **Hệ tự trả lời "FABi có tách sẵn thành phần combo không"**, bằng chính số liệu đã nạp chứ
  không đoán. Dấu hiệu: bản xuất có tách sẵn thì dòng thành phần mang **số lượng > 0 mà doanh
  thu 0đ** — tiền nằm hết ở dòng combo. Món bán lẻ bình thường không bao giờ như vậy.
* 🔴 **Cảnh báo ĐANG TRỪ KHO HAI LẦN.** Nếu FABi đã tách sẵn mà bảng Thành phần combo lại khai
  thêm, mỗi chai nước bị trừ hai lượt: sổ báo mất hàng mỗi ngày trong khi kho vẫn đủ, và người
  trực bị nghi oan — mà không có dòng nào sai để lần ra. Màn kho nay kêu đỏ, kèm tên đúng mấy
  mặt hàng đang bị trừ đôi và cách gỡ.
* Khi FABi đã tách sẵn, màn hình **thôi nhắc đi khai thành phần combo** — nhắc lúc ấy là xui
  người ta tạo ra chính lỗi trừ hai lần.
* `tools/test/kiem-kho.php` lên **42 phép**, `tools/test/kiem-kho-man.js` lên **31 phép**.

= 1.46.0 =
* **SỔ KHO HÀNG HOÁ** — tab mới "Kho hàng hoá". Cuối ngày nhân viên khai **bán bao nhiêu** và
  **đếm còn bao nhiêu**; hệ đối chiếu với số máy POS ghi (lấy thẳng từ báo cáo FABi đã nạp) rồi
  chỉ ra **hai chỗ lệch độc lập**:
  *Lệch khai* = nhân viên khai bán − máy ghi bán · *Lệch kho* = đếm còn − tồn tính.
* **Danh sách mặt hàng tự sinh từ FABi** — món mới xuất hiện là tự có ô nhập, khỏi khai báo
  trước. Món hôm nay không bán cái nào **vẫn ở lại sổ** chừng nào còn tồn, để còn đếm được.
* 🔴 **Món trong combo tự trừ kho theo thành phần.** FABi ghi doanh thu vào tên combo chứ không
  vào tên chai nước, nên không tách thì chai nước ấy mãi mãi "chưa bán" và tồn tính thừa dần.
  Có khối **Thành phần combo** để khai một lần (`Nước suối x2, Kẹo cầu vồng x1`), và hệ **nhắc**
  khi thấy món trông như combo mà chưa khai. Hệ **không tự đoán** công thức — đoán sai là trừ
  nhầm kho hàng loạt mà không dòng nào sai.
* 🔴 **Tồn tính lấy số MÁY, không lấy số nhân viên khai.** Lấy số khai thì người khai thiếu bao
  nhiêu, tồn tính cũng thừa bấy nhiêu — hai vế triệt tiêu, cột lệch luôn bằng 0 dù hàng đã mất.
* 🔴 **Mỗi lần đếm tay là một mốc mới.** Tồn đầu ngày mai lấy **số đã đếm**, không lấy số tính,
  nên một ngày lệch không kéo theo mọi ngày sau đỏ vì một lỗi đã xử lý xong.
* 🔴 **Ô chưa khai hiện "—", khác hẳn ô đếm được 0.** Trộn hai thứ là cả sổ trông như đã soát
  xong và khớp, đúng điều ngược lại với sự thật.
* **Nới trần món từ 40 lên 300 mỗi ngày mỗi cơ sở.** 40 là đủ cho màn "món bán chạy" nhưng sai
  cho sổ kho: món rơi khỏi top 40 thành "bán 0 cái", rồi tồn tính thừa đúng bằng số đã bán —
  trông y như nhân viên ăn bớt. Mà thất thoát thật hay nằm đúng ở mấy món bán lẻ tẻ ấy.
* Bài kiểm mới `tools/test/kiem-kho.php` (**32 phép**) và `tools/test/kiem-kho-man.js`
  (**28 phép**).

= 1.45.0 =
* **NHẬN BÁO CÁO QUA HỘP THƯ.** FABi gửi báo cáo kèm tệp đính kèm về một hộp thư riêng, web tự
  vào lấy theo giờ (mặc định 2 tiếng, chỉnh được 1–24) rồi nạp vào kho. Màn cấu hình nằm trong
  tab **Quản trị**, kèm nút **"Lấy thư ngay"** và **nhật ký 50 lượt gần nhất**.
* Tệp lấy về đi qua **đúng bộ đọc mà đường nạp tay vẫn dùng** — `khh_dt_nap_tep()` được tách ra
  để cả hai đường dùng chung. Chép luật đọc file ra bản thứ hai là sớm muộn hai bên lệch nhau,
  mà lệch kiểu ấy im thin thít: file nạp vào, không báo lỗi, chỉ là đọc sai kiểu.
* Nhận đủ 5 loại: báo cáo bán hàng FABi, sao kê ngân hàng, MoMo trên POS, sao kê MoMo, Bes.
* 🔴 **Bỏ trống ô "Chỉ nhận thư từ" là chối hết** — cố ý. Hộp thư nào cũng nhận thư rác, mà một
  tệp .csv của người lạ đi thẳng vào kho doanh thu thì không ai nhìn ra ngay. Khớp được cả một
  tên miền (`@fabi.vn`), và `@fabi.vn` **không** khớp `fabi.vn.ke-gian.com`.
* Đuôi tệp vẫn gác y như đường nạp tay (.xlsx/.csv/.tsv/.txt), thêm ô **mẫu tên tệp**.
* **Không nạp trùng**: nhớ Message-ID của 300 thư gần nhất. Thư của người lạ **không** bị đánh
  dấu đã đọc — hệ không được lặng lẽ giấu thư trong hộp thư của người khác.
* 🔴 **Màn hình cảnh báo nếu quá hạn mà chưa chạy.** Lịch của WordPress chỉ chạy khi có người mở
  trang; ban đêm không ai vào là cả đêm không lấy thư mà màn hình vẫn trông bình thường. Màn
  cấu hình in sẵn đường dẫn `wp-cron.php` để dán vào Cron Jobs bên hosting.
* **Không dùng phần mở rộng `imap` của PHP** — từ PHP 8.4 nó đã bị tách khỏi bản gốc nên có thể
  biến mất sau một lượt nâng cấp PHP của hosting. Nói IMAP thẳng bằng socket + TLS.
* **Mật khẩu hộp thư không bao giờ đi ngược ra trình duyệt**, và nên đặt bằng
  `define( 'KHH_DT_MAIL_PASS', '…' )` trong `wp-config.php` để nó không nằm trong cơ sở dữ liệu.
  Lời báo đăng nhập hỏng cũng không in lại phản hồi máy chủ — có máy chủ nhắc lại cả dòng lệnh,
  mà dòng ấy có mật khẩu.
* 🔴 **Sửa luôn một lỗi cũ**: đường nạp file một lượt (`POST /nap`) tính ra `loai` rồi bỏ đấy,
  luôn đọc như báo cáo FABi — tải file MoMo hay sao kê qua đường ấy là đọc sai kiểu mà không
  báo. Nay cả hai đường cùng đi qua `khh_dt_nap_tep()`.
* Bài kiểm mới `tools/test/kiem-hop-thu.php` — **46 phép**, phần IMAP diễn lại nguyên lượt đối
  đáp trên một **cặp socket thật**, gồm cả phản hồi có literal (chỗ dễ sai nhất).

= 1.44.0 =
* 🔴 **Sửa ngày không còn làm màn chớp trắng rồi nhảy về đầu trang.** `<input type="date">` bắn
  sự kiện *ngay giữa lúc gõ*: xoá một ô để sửa là giá trị thành rỗng và hệ đi hỏi máy chủ một
  khoảng rỗng; gõ tiếp, vừa đủ một ngày hợp lệ là nó hỏi lần nữa, dù còn đang gõ dở. Nay ô ngày
  **bỏ qua giá trị rỗng hoặc gõ dở**, **chờ một nhịp** để gõ xong mới chạy một lượt, và **rời ô
  thì chạy ngay** (chọn xong trên lịch không phải đợi).
* **Đang tải lại thì giữ nguyên số cũ trên màn**, chỉ mờ đi một chút — thay vì xoá trắng cả
  bảng mỗi lượt. Lần đầu mở tab thì vẫn hiện "Đang tải…" như cũ.
* **Lượt trả về trễ không đè lượt mới.** Đổi ngày rồi đổi tiếp là hai lượt hỏi chạy song song;
  lượt đầu về sau thì màn hiện số của khoảng cũ trong khi thanh ngày ghi khoảng mới — sai mà
  trông như thật.
* Bài kiểm mới `tools/test/kiem-o-ngay.js` — **22 phép**, chạy thật hàm nối ô ngày trên ô giả
  và bộ hẹn giờ giả, chứ không chỉ dò chuỗi trong mã.
* **Bệ đỡ bài thử**: thư mục tạm nay riêng cho từng lượt chạy và tự dọn lúc thoát. Trước đây nó
  đặt tên theo số hiệu tiến trình mà không ai dọn — máy chạy bài thử quay vòng số hiệu ở 32768
  và đã có 2.053 thư mục cũ nằm lại, nên một lượt chạy mới có thể thừa hưởng tệp của lượt cũ và
  đỏ một lần rồi xanh lại, không lần ra nguyên do.

= 1.43.0 =
* 🔴 **Ghép cơ sở vào tài khoản MoMo ngay trên màn Đối soát.** K&H có hai pháp nhân và **phí
  hai tài khoản khác nhau**, nên phép "chia tạm" của 1.41.0 (gộp mọi cơ sở chưa có chủ vào một
  rổ) lấy phí của KH785 rắc sang cả cơ sở của KH989. Nay có bảng **"Ghép cơ sở vào tài khoản
  MoMo"**: mỗi cơ sở một ô gõ mã tài khoản, bấm Lưu ghép là xong. Ghép rồi thì phí của tài
  khoản nào chỉ chia cho cơ sở của tài khoản ấy.
* Trước bản này, bảng ghép **chỉ học được từ lượt nạp sao kê có gõ mã tài khoản** — tức muốn
  sửa một cơ sở thì phải đi nạp lại sao kê cả tháng.
* Bảng **mở sẵn khi còn cơ sở chưa ghép**, và cơ sở chưa ghép **xếp lên đầu** — đó là việc còn
  dở, không phải mục nâng cao. Mỗi dòng bày kèm doanh thu MoMo trong kỳ để biết cơ sở nào đáng
  ghép trước.
* **Ô để trống là bỏ ghép** (kể cả khi chỉ còn một dấu cách) — ghép nhầm thì gỡ được.
* Câu "Đang chia TẠM" nay **chỉ thẳng xuống bảng ghép**, và nói rõ phí hai tài khoản khác nhau.
* `tools/test/kiem-momo-phi.php` lên **74 phép**, `tools/test/kiem-sua-phi-momo.py` lên **46
  phép**.

= 1.42.0 =
* 🔴 **Nút Xoá lượt phí giờ xoá thật.** Trước đây bấm Xoá là không có gì xảy ra và cũng không
  câu báo nào. Màn hình gửi mã lượt phí trong *thân* một yêu cầu DELETE dạng multipart — mà PHP
  chỉ tự bóc thân multipart cho POST, còn WordPress chỉ bóc JSON và form-urlencoded. Mã tới máy
  chủ là rỗng nên nó đi xoá "lượt phí số 0", không có, rồi trả về mã 200 như thường. Nay mã đi
  trên đường dẫn — phương thức nào cũng đọc được.
* **Xoá hụt thì kêu lên.** Màn hình trước đây bỏ qua hẳn kết quả máy chủ trả về, nên một lượt
  xoá hụt trông y hệt một lượt xoá được. Nay không xoá được là hiện lời báo.
* **Máy chủ chối lượt xoá thiếu mã** thay vì lặng lẽ trả "không xoá được" — thiếu tham số là
  lỗi, không phải kết quả.
* `tools/test/kiem-momo-phi.php` lên **60 phép**, `tools/test/kiem-sua-phi-momo.py` lên **32
  phép** — khoá cả vế máy chủ lẫn vế màn hình.

= 1.41.0 =
* **Nút Sửa trên từng lượt phí đã nhập.** Bấm là đổ nguyên lượt ấy (tài khoản, khoảng ngày, số
  tiền) lên ô nhập ở trên, bôi sẵn con số cũ, cuộn ô vào tầm mắt — gõ số mới rồi bấm Lưu là ghi
  đè. Trước đây muốn chữa một con số gõ sai thì phải Xoá dòng rồi nhập lại từ đầu.
* 🔴 **Đã nhập phí là chia cho cửa hàng, không để trống.** Bảng ghép `mã cửa hàng → tài khoản`
  chỉ học được từ lượt nạp sao kê CÓ gõ mã tài khoản, mà sao kê thì đã nạp từ trước khi có ô ấy
  — nên phí nhập vào nằm im, cột Phí trống trơn. Nay tài khoản nào chưa ghép được cơ sở nào thì
  phí của nó **chia tạm cho mọi cơ sở chưa thuộc tài khoản nào khác**, vẫn theo % doanh thu và
  vẫn cộng lại đúng số đã nhập.
* **Chia tạm thì màn hình nói rõ là tạm**, kèm tài khoản, khoảng ngày, số tiền và chia cho mấy
  cơ sở — cùng đường đi để chia cho đúng pháp nhân (nạp lại sao kê MoMo có gõ mã tài khoản).
* 🔴 **Hai tài khoản cùng chưa ghép thì KHÔNG chia tạm.** Cùng đổ vào một rổ cơ sở là mỗi cơ sở
  gánh phí của cả hai pháp nhân — tổng toàn hệ vẫn đúng nên không gì báo, mà từng cơ sở thì sai.
  Trường hợp ấy hệ giữ nguyên "chưa chia được" và chỉ đường nạp lại sao kê kèm mã.
* Cơ sở **đã có chủ thì phép chia tạm không đụng tới**.
* `tools/test/kiem-momo-phi.php` lên **52 phép**; thêm `tools/test/kiem-sua-phi-momo.py` — **22
  phép** cho phần màn hình.

= 1.40.0 =
* 🔴 **Nhập phí xong mà cột Phí vẫn trống — đã sửa.** Màn hình báo "Tài khoản đã biết: KH785"
  trong khi bảng ghép `mã cửa hàng -> tài khoản` còn rỗng, vì bản trước gộp hai thứ khác hẳn
  nhau làm một: tài khoản ĐÃ GHÉP được cơ sở, và tài khoản mới chỉ từng thấy trong lượt nhập
  phí. Nay `khh_dt_momo_tk_da_ghep()` lo phần gác, `khh_dt_momo_tk_ds()` chỉ còn gợi ý cho ô gõ.
* **Phí không chia được thì nói ra**, kèm tài khoản, khoảng ngày và số tiền — trước đây hệ
  `continue` lặng lẽ, tiền đã gõ vào mà màn hình coi như chưa có, không gì lần ra nguyên nhân.
* **Bấm Lưu là thấy ngay, không phải F5.** Nút Lưu và nút Xoá gọi lại đúng phần Đối soát chứ
  không phải phần Doanh thu theo ngày — phí nằm ở phần Đối soát nên màn hình cứ đứng im.
* 🔴 **"68.866" không còn vào sổ thành 69đ.** Ô nhập là `type="number"` nên trình duyệt đọc dấu
  chấm thành dấu thập phân; chép y con số trên màn MoMo là mất 68.797đ, không câu báo nào vì 69
  vẫn là số hợp lệ. Nay ô là `type="text"`, còn máy chủ đọc bằng `khh_dt_so()` — hiểu cả
  "68.866", "68,866" lẫn "68866".
* **Gõ lại đúng khoảng cũ là SỬA, không phải chồng ngày.** Trước đây muốn chữa một con số gõ sai
  thì phải Xoá rồi nhập lại, vì hệ coi chính lượt ấy là lượt chồng. Chối chồng ngày vẫn giữ
  nguyên cho mọi khoảng KHÁC.
* `tools/test/kiem-momo-phi.php` lên **43 phép**.

= 1.39.0 =
* **Ô nhập phí MoMo giờ lúc nào cũng vào được.** Bản 1.38.0 chỉ hiện ô nhập khi hệ đã biết ngày
  nào còn thiếu phí — mà muốn biết thì phải nạp lại sao kê kèm mã tài khoản trước. Thành ra tính
  năng có mà không có cửa vào. Nay khối "Phí MoMo" luôn nằm dưới bảng Đối soát MoMo, tự điền sẵn
  khoảng ngày đang xem, gợi ý mấy tài khoản đã biết, và liệt kê các lượt đã nhập kèm nút Xoá.
* **Phân trang 20 dòng một trang** cho bốn bảng dài: Đối soát cơ sở với máy POS, ba bảng Lệch
  giao dịch MoMo, và bảng mã nộp tiền lạ ở màn Sao kê ngân hàng.
* Mỗi bảng **nhớ số trang riêng** — ba bảng lệch nằm cùng một màn, dùng chung một ô nhớ thì bấm
  sang trang 3 ở bảng dài là hai bảng ngắn trống trơn.
* Số trang được **kẹp lại trong khoảng hợp lệ**: đang xem trang 9 rồi đổi kỳ sang khoảng chỉ có
  2 trang thì về trang cuối, chứ không trả màn trắng trông như mất dữ liệu.
* Đổi trang **không gọi lại máy chủ** — số liệu đã có sẵn, chỉ vẽ lại.
* Bài kiểm mới `tools/test/kiem-phan-trang.js` — 18 phép.

= 1.38.0 =
* Bảng Đối soát MoMo có thêm **cột Phí** và **cột Doanh thu MoMo đã trừ phí**, đặt ngay sau cột
  MoMo theo sao kê.
* Phí **nhập tay theo khoảng ngày × tài khoản** — đã kiểm cả ba nguồn của MoMo (Transaction
  report, daily report, màn Đối soát) và chỉ màn Đối soát có phí, dưới dạng MỘT SỐ TỔNG. Nhập
  gộp 2-3 ngày một lượt cũng được.
* Hệ **tự chia phí về từng cơ sở theo % doanh thu**, và cộng các phần lại **đúng bằng số đã
  nhập** (phép phần dư lớn nhất — chia tỷ lệ rồi làm tròn thường lệch vài đồng).
* 🔴 **Chối lượt nhập chồng ngày.** Nhập ngày 17 rồi nhập gộp "17→19" là phí ngày 17 vào sổ hai
  lần, không dòng nào sai và không gì báo.
* Ngày nào có doanh thu MoMo mà chưa nhập phí thì màn hình **nhắc, kèm ô nhập ngay tại chỗ**.
* Phí của tài khoản nào chỉ chia cho cơ sở của tài khoản ấy. Bảng `mã cửa hàng → tài khoản` học
  từ lượt nạp sao kê — thẻ "Sao kê MoMo" có thêm ô gõ mã tài khoản (ví dụ KH785).
* **Lệch vẫn không trừ phí**, cố ý: phí là khoản MoMo thu, không phải chỗ hai bên ghi khác nhau.
* Bài kiểm mới `tools/test/kiem-momo-phi.php` — 29 phép.

= 1.37.0 =
* **Trạm nghe IPN MoMo** — mở đường `…/wp-json/khh-dt/v1/momo-ipn` để dán vào trang quản trị
  MoMo. Bước MỘT: chỉ nghe và ghi nguyên văn payload vào nhật ký.
* 🔴 **Chưa ghi một dòng nào vào sổ MoMo**, và có phép thử khoá điều đó. Cổng này công khai
  (MoMo không đăng nhập được), nên chừng nào chưa kiểm được chữ ký thì một dòng vào sổ cũng là
  một dòng quá nhiều — ai biết đường dẫn cũng bơm được doanh thu giả vào đối soát.
* Chặn theo IP `118.69.210.244` (cột Outcoming trong tài liệu MoMo), sửa được bằng lọc
  `khh_dt_momo_ip_cho_phep` mà không phải sửa mã. Chối thì trả 204 chứ không 403 — nói "IP sai"
  là chỉ đường cho người đang dò.
* Lượt bị chối **vẫn được ghi** kèm lý do: "MoMo bảo đã gọi mà hệ không thấy gì" là ca tốn thời
  gian nhất, có nhật ký thì thành câu trả lời trong ba mươi giây.
* Mở đường dẫn bằng trình duyệt (GET) thì nó nói đang sống và in ra IP của người mở.
* Bài kiểm mới `tools/test/kiem-momo-ipn.php` — 18 phép.

= 1.36.0 =
* **Thoát đăng nhập có cho CẢ HAI lối vào.** Trước đây nút "Thoát" chỉ hiện với người vào bằng
  PIN; người văn phòng vào bằng tài khoản WordPress không có đường nào ra khỏi trang. Đây là
  trang mở trên máy dùng chung ở cửa hàng và điện thoại chuyền tay — không thoát được nghĩa là
  người sau ngồi vào vẫn đang là người trước, xem đúng những gì người trước xem.
* Hai lối thoát tách riêng, không dùng chung một đường: vào bằng PIN thì đóng phiên PIN qua REST
  `dang-xuat` (không đụng đăng nhập WordPress); vào bằng tài khoản thì đi `wp_logout_url()`.
* Lối thứ hai là **link người bấm**, không gọi bằng fetch: `wp_logout_url()` mang nonce nên
  WordPress sẽ chối — mà chối im lặng, người dùng chỉ thấy nút bấm không phản hồi.
* Bài kiểm mới `tools/test/kiem-thoat-dang-nhap.py` — 16 phép.

= 1.35.0 =
* **Nạp được báo cáo của cửa hàng chạy hệ Bes** (không phải FABi): thẻ mới "Bán hàng (Bes)" đọc
  báo cáo *TỔNG HỢP MÓN ĂN BÁN* xuất ra `.csv`. Không phải khai cơ sở ở đâu — danh sách cơ sở
  suy từ chính sổ, nạp được số là cơ sở tự hiện.
* Có ô **gõ tên cơ sở**: tên trong file thường trơ ("FUNZONE") và sẽ đụng mấy cơ sở đã có.
* 🔴 Báo cáo Bes xen **hàng nhóm** (tổng con) lẫn hàng món vào cùng một bảng. Cộng hết là **gấp
  đôi** doanh thu — đo trên file thật: 5.970.000 thành 11.940.000. Bộ đọc bỏ hàng nhóm, và
  **chốt lại bằng chính dòng "Tổng"** Bes tự in: lệch quá 1đ là CHỐI, không nạp.
* ⚠️ Báo cáo này **không có hình thức thanh toán**, nên `pttt` để rỗng — cơ sở ấy có doanh thu
  nhưng chưa đối soát được tiền mặt / CK / MoMo. Điền bừa "coi hết là tiền mặt" thì màn đối
  soát sẽ tố cửa hàng giữ tiền.
* Bài kiểm mới `tools/test/kiem-bes.php` — 18 phép, tự dựng file mẫu.

= 1.34.0 =
* **"Chưa có sổ" tách hẳn khỏi "lệch tiền".** Bảng Đối soát MoMo từng báo lệch 98.160.000đ cho
  kỳ 01→16/09 trong khi không mất một đồng: K&H có hai pháp nhân MoMo, sổ pháp nhân kia chưa
  nạp. Kế toán đọc con số ấy là đi tìm gần trăm triệu không hề thất lạc.
* Chỗ hỏng là `co[x.ngay]` — nó hỏi "sổ có NGÀY này không", một cờ chung cho cả hệ, không theo
  cơ sở. Sổ pháp nhân A phủ đủ ngày nên mọi ngày đều tính là "có sổ", kể cả với cơ sở của pháp
  nhân B mà sổ ấy không nhắc tới. Vì thế cột "Ngày thiếu file" đứng 0 trong khi cơ sở đó không
  có lấy một dòng sổ.
* Nay cơ sở nào sổ không nhắc tới thì xuống khối riêng "N cơ sở chưa có sổ MoMo", có nút nạp,
  **không** cộng vào ô Lệch. Dòng tổng nói rõ "N cơ sở so được".
* Phân định bằng **"sổ có nhắc tới cơ sở này không"**, không phải "tiền sổ có bằng 0 không" —
  cơ sở CÓ trong sổ mà tiền bằng 0 là lệch THẬT (máy ghi có thu, MoMo không trả), phải kêu.
* Bài kiểm mới `tools/test/kiem-momo-chua-so.js` — 13 phép, chạy thật hàm bốc từ doanh-thu.js.

= 1.33.0 =
* **Máy POS dời cơ sở thì bảng ghép đi theo FABi.** `khh_dt_hoc_ma_ch_momo()` tự nhận là "lời
  giải cho bài máy dời cơ sở" và chốt "một mã trỏ về hai quán trong cùng kỳ thì không học" —
  nhưng câu SQL của nó không lọc ngày, nó quét sạch lịch sử. Nên chỉ cần dời máy MỘT lần là mã
  ấy vĩnh viễn trỏ về hai quán, vĩnh viễn không học lại, và bảng ghép đứng yên ở tên cơ sở CŨ.
  Máy sang quán mới cả tháng mà doanh thu vẫn kể cho quán cũ, không một câu báo.
* Nay quán nào giữ máy tới **ngày muộn nhất** thì quán ấy đang giữ máy — đúng luật "nạp dữ liệu
  FABi vào là xác định máy đang nằm cơ sở nào". Dời bao nhiêu lần cũng theo kịp.
* Chỉ dừng lại khi thật sự không phân định được: hai quán cùng chia nhau ngày mới nhất. Lúc ấy
  không đoán, và mã vào danh sách `lan_can` để màn hình nói ra.
* Bài kiểm mới `tools/test/kiem-momo-hoc-coso.php` — 9 phép trên bảng thật (SQLite).

= 1.32.0 =
* Thả file **MoMo payments của FABi** vào thẻ "Sao kê MoMo" thì câu lỗi nay nói đúng: *"file
  đúng, nhưng nhầm thẻ — nạp ở thẻ Giao dịch MoMo (FABi) ngay bên cạnh"*. Trước đây nó bảo
  *"Anh tải đúng bản Transaction report của MoMo giúp em"* — tức đẩy người ta đi tải lại một
  file họ đang cầm trong tay, và lần sau họ sẽ tin là chỗ nạp bị hỏng.
* Thả một .xlsx khác thì nói rõ thẻ này chỉ đọc `.csv` (ô thả ghi "nhận .xlsx, .csv" nên không
  ai tự biết được).
* `.csv` thiếu cột thì vẫn báo thiếu cột như cũ — đó mới là ca câu cũ nói đúng.
* `kiem-momo.php` 62 → 72 phép, có chốt ngược: sổ MoMo đúng dạng vẫn phải đọc được.

= 1.31.0 =
* Bảng Đối soát MoMo: câu nhắc "Sổ MoMo chưa có N ngày" nay kèm **nút Nạp sao kê MoMo** mở
  thẳng vào đúng thẻ. Trước đây câu nhắc là chữ trơn kiểu "bấm Nạp báo cáo → thẻ Sao kê MoMo",
  tức bắt người đang soát sổ tự tìm nút ở góc trên màn rồi đếm sang thẻ thứ tư — nên thẻ ấy có
  từ bản 1.28.0 mà vẫn bị hiểu là "chưa có chỗ nạp MoMo".
* Hai câu nhắc còn lại (chưa nạp Giao dịch MoMo FABi · sổ MoMo là sổ gộp theo ngày) cũng có nút.
* Phép thử mới `tools/test/kiem-nut-nap-momo.py` — 13 phép, canh cả chỗ gắn sự kiện, vì lỗi
  "nút mở hộp mà không chọn thẻ nào" thì cú pháp vẫn sạch và không có câu báo nào.

= 1.30.0 =
* **Sửa lỗi che mất khoản lệch tiền trong đối soát MoMo.** Lõi ghép hai sổ ghi là "ghép hai
  lượt" ngay từ đầu, nhưng thực tế chạy MỘT lượt: với từng giao dịch máy POS, thử ghép theo mã —
  không thấy thì ghép mờ (ngày + số tiền + cơ sở) ngay tại đó. Nên một giao dịch KHÔNG có mã đi
  trước chiếm được đúng dòng sổ mà một giao dịch CÓ MÃ đứng sau cần.

  Đo bằng cách cho bản cũ và bản mới chạy cùng dữ kiện: máy ghi 70.000 cho mã M2, sổ MoMo trả
  71.000 cho đúng mã ấy, kèm một giao dịch không mã tình cờ đúng 71.000 cùng ngày cùng quán.
  Bản cũ kể `khớp=1 · lệch=0` — **khoản chênh 1.000đ trên đúng mã M2 biến mất khỏi báo cáo**,
  bị thay bằng một cặp "khớp + máy có, MoMo thiếu" không liên quan. Bản mới kể `lệch=1`, nêu
  đúng mã và đúng số.
  Nay LƯỢT 1 ghép mã cho **tất cả** giao dịch POS trước; chỉ phần còn lại mới vào LƯỢT 2 ghép
  mờ, nên ghép mờ không bao giờ tranh được dòng mà mã đã nhận. Cùng ngày · cùng số tiền · cùng
  quán là chuyện thường ngày trong quán ăn, nên ca này không hiếm.

* **Lõi ghép nay thử được bằng con số.** Tách `khh_dt_doi_soat_momo_lam( $pos, $sk )` ra khỏi
  `khh_dt_doi_soat_momo_gd()`: một hàm đọc database, một hàm thuần tính toán. Trước bản này hàm
  KẾT LUẬN VỀ TIỀN — giao dịch nào thiếu, giao dịch nào lệch — **không có một phép thử nào**;
  `kiem-momo.php` chỉ canh hai hàm đọc file, vì muốn gọi hàm đối soát thì phải dựng cả MySQL.
  `kiem-momo.php` thêm 22 phép cho lõi ghép (40 → 62): khớp · lệch tiền · chỉ một bên · giao
  dịch lỗi ở máy đếm riêng · mỗi dòng sổ chỉ ghép một lần · hai mốc cắt khác nhau không kết
  luận oan · ghép mờ không bắc cầu sang quán khác · và ca che-lệch ở trên.


= 1.28.0 =
* **Cột MoMo nói rõ số ấy lấy từ sổ nào.** Ngày lấy từ sổ gộp được đánh dấu ◷ kèm dòng giải nghĩa
  ngay dưới bảng: tổng ngày thì đúng, nhưng không tra xuống từng giao dịch được. Trước đây hai
  loại số nằm chung một cột, nhìn giống hệt nhau, mà một loại tra được còn loại kia thì không.

= 1.27.0 =
* **Dùng CẢ HAI sổ MoMo cùng lúc, đè theo từng ô (ngày × cơ sở).** Sổ gộp bên plugin Sao Kê có cả
  trăm ngày lịch sử, file thô nạp thẳng vào đây chỉ có kỳ vừa tải. Bỏ sổ gộp đi là mất lịch sử;
  cộng hai sổ là nhân đôi những ngày cả hai cùng có. Nay ô nào có file thô thì lấy file thô (tra
  được tới từng giao dịch, theo được máy khi dời cơ sở), ô nào không có thì giữ số của sổ gộp —
  mỗi ô một nguồn, không bao giờ hai.
* **"Không phải nạp lại lần hai."** Thêm nút *Tìm file MoMo trên máy chủ*: dò thư mục tải lên của
  WordPress, thấy `Transaction_report_….csv` nào thì hút thẳng về kho, chọn file nào tuỳ anh.
  Nạp lại đúng file cũ cũng không sao — khoá theo mã giao dịch nên ghi đè, không cộng dồn.
  Không thấy file nào thì nói thẳng là plugin kia đã xoá file sau khi gộp, và file thô phải qua
  đây **một lần** — không phải nạp lại hàng ngày.
* **Khai sổ bằng một nút.** Bảng dò MoMo có thêm nút *Khai ngay*: lấy cột hệ đoán, kèm bộ lọc vừa
  tìm được, khai luôn. Không đoán chắc được thì mới mở màn khai tay ra.

= 1.26.0 =
* **Sửa lỗi "Phải chỉ cột Tên cửa hàng" trong khi cột ấy nằm ngay đó.** Sổ `wpt9_saoke_congfile`
  đặt tên cột là `ch_chuan` / `ch_file`, mà hai tên ấy chưa có trong danh sách hệ nhận mặt — nên
  màn khai sổ MoMo chặn lại không cho lưu. Nay nhận cả hai, ưu tiên bản đã chuẩn hoá.
* **Nhận ra sổ ĐÃ GỘP THEO NGÀY và nói rõ nó làm được gì.** Sổ ấy mỗi dòng là một ngày của một
  quán, có cột đếm giao dịch — tổng ngày × cơ sở thì đúng và dùng được ngay, nhưng đối soát *từng
  giao dịch* thì không: 188 dòng gộp đem so với mấy nghìn giao dịch của máy POS sẽ kể lệch toàn
  phần, người đọc tưởng mất tiền thật. Nay màn MoMo nói thẳng điều đó thay vì bày một bảng sai,
  kèm đường đi đúng: nạp thẳng `Transaction_report_….csv` — cùng file ấy, chỉ là chưa gộp.

= 1.25.0 =
* **Nút "Tự tìm giao dịch MoMo trong site".** Mở danh sách bảng ra thì không cái nào tên *momo* —
  vì chẳng có bảng nào tên thế: MoMo nếu có thì nằm **lẫn** trong sổ cổng, nhận ra bằng một giá
  trị trong cột nguồn. Nay hệ dò hộ từng sổ từng cột, chỉ thẳng ra *sổ nào · cột nào · giá trị
  nào · bao nhiêu dòng*, bấm một nút là sang màn khai với **bộ lọc điền sẵn**.
* **Dò xong mà không có thì nói thẳng là không có**, kèm đường đi thật: nạp file
  `Transaction_report_….csv` ở thẻ *Sao kê MoMo*. Trước đây màn im lặng, người ta còn đi tìm tiếp
  một thứ không tồn tại.

= 1.24.0 =
* **Nạp thẳng sao kê MoMo** (Nạp báo cáo → thẻ *Sao kê MoMo*): file `Transaction_report_….csv`
  tải từ trang MoMo. MoMo không cho nối API nên đường vào là tải file — nay không phải khai bảng
  ngoài nữa, cứ tải lên là có sổ.
* **Ghép hai sổ bằng mã giao dịch.** Cột *Mã giao dịch* của MoMo chính là *Mã đối tác* bên FABi;
  đo trên hai file thật ngày 16/09/2026 thì 995 trong 1.008 mã trùng nhau. Không còn phải đoán
  theo tên quán.
* **Máy dời cơ sở: hệ tự học mã cửa hàng MoMo.** `KHTUTU2` ↔ quán nào là học ra từ chính những
  cặp khớp mã, mỗi lần nạp lại là dạy lại; một mã trỏ về hai quán trong cùng kỳ thì hệ không đoán.
* **Thêm nhóm "Ngoài phạm vi sổ MoMo".** Sổ MoMo chỉ phủ mấy quán dùng mã MoMo riêng (file thật:
  4 quán) trong khi máy POS ghi cả 12 — trước đây so thẳng là đẻ ra hàng nghìn dòng *"MoMo thiếu
  tiền"* của những quán vốn không nằm trong sổ. Nay chúng nằm riêng một nhóm, ghi rõ **không phải
  lệch**, và màn nói luôn sổ đang phủ cơ sở nào, từ ngày nào.
* **Thêm nhóm "Ngoài kỳ kho POS".** Hai file hiếm khi cắt cùng một mốc — đo trên hai file thật thì
  sổ MoMo chạy tới 16/09 còn bản xuất FABi dừng ở 15/09, và 8 trong 13 dòng *"MoMo nhận tiền, máy
  không ghi đơn"* chỉ là cái mốc ấy. Nay chúng nằm riêng, kèm lời nhắc nạp bản xuất FABi mới hơn.
* **Sửa lỗi: tải file lên thì thẻ nào cũng bị đọc như báo cáo bán hàng FABi.** Đường tải theo mẩu
  quên đọc tham số loại file, nên chọn *Sao kê ngân hàng* hay *MoMo* đều vô ích — mà lỗi ấy im
  thin thít, chỉ lộ ra khi màn đối soát trống trơn.

= 1.23.0 =
* **Tích những ngày đã được lần nộp sau xoá sạch.** Cơ sở gom mấy ngày nộp một cục: ngày 1–9 dồn
  tiền, ngày 10 nộp 26.890.000 là xong cả chín ngày — nay chín ngày ấy hiện **✓ đã nộp đủ** thay
  vì nằm im với chữ "đang dồn". Thêm ô đếm **Ngày đã nộp đủ**.
* **Lọc đúng nguồn khi khai sổ MoMo**: sổ cổng gộp cả VietQR / MoMo / VNPAY trong một bảng, nay
  chọn được *chỉ lấy dòng có `nguon` = `momo`*. Màn bày sẵn các giá trị có thật trong cột kèm số
  dòng, khỏi gõ tay rồi sai một chữ.

= 1.22.0 =
* **Nạp file "MoMo payments" của FABi** (Nạp báo cáo → thẻ *Giao dịch MoMo*): đọc cả 12 trang, cả
  khối QR Tĩnh lẫn QR Động, và ghép tên trang bị Excel cắt ở 31 ký tự về đúng tên cơ sở.
* **Bảng lệch TỪNG GIAO DỊCH** giữa máy POS và sổ MoMo: máy ghi mà MoMo không có · MoMo có mà máy
  không ghi · khớp mã nhưng lệch tiền · máy ghi lỗi/huỷ. So tổng một ngày thì hai lỗi ngược chiều
  triệt tiêu nhau; xuống từng mã thì cả hai hiện ra.
* **Máy FABi dời cơ sở thì cứ nạp lại file** — không phải khai tay bảng nào. Chính file nói giao
  dịch nào thuộc quán nào, theo từng giao dịch từng ngày; nạp lại là ghi đè cả cột cửa hàng.
* Nạp xong hệ còn **học** bảng "tên cửa hàng bên MoMo ↔ cơ sở FABi" từ các cặp khớp mã. Tên nào
  trỏ về hai quán trong cùng kỳ (đúng cảnh vừa dời máy) thì **không học** — học cái nào cũng sai
  một nửa.

= 1.21.0 =
* **Khối Đối soát MoMo**: so ĐÚNG phần MoMo máy POS ghi (đọc từ cột hình thức thanh toán) với sổ
  sao kê MoMo, theo từng cơ sở — không so với cả cục CK/QR, vì cục ấy còn có chuyển khoản, VNPAY
  và Việt QR.
* **Ngày chưa tải file MoMo bị loại khỏi phép so** và đếm riêng. MoMo không bắn webhook nên sổ ấy
  do người ta tải file hằng ngày; coi ngày thiếu file là ngày bằng 0 thì mỗi lần quên tải lại hoá
  thành một lời tố "MoMo giữ tiền".
* Bảng Tổng hợp tách cột **MoMo (POS)** riêng với **MoMo (sao kê)**.

= 1.20.1 =
* **Sửa lỗi tab Đối soát trắng màn** ("theTreo is not defined"): dọn một hàm chết ở 1.20.0 đã cắt
  lẹm sang hàm đang dùng nằm kẹp bên trong. Kèm một bài kiểm mới soi mọi lời gọi JavaScript tới
  hàm không tồn tại — `node --check` và bộ thử PHP đều mù với lớp lỗi này.

= 1.20.0 =
* **Số đầy đủ ở chỗ để đối chiếu**: chip nộp tiền ghi `về 39.870.000` thay vì `về 40 tr` — con số
  ấy là để tra trên sao kê, không phải để liếc.
* **Sổ MoMo**: khai một lần ở Quản trị → *Khai sổ MoMo*, bảng Tổng hợp có thêm cột **MoMo (sao kê)**
  và **Lệch MoMo** so với phần CK/QR máy POS ghi. Sổ MoMo đọc thẳng, không nhập vào kho — nó là sổ
  đối chiếu, không phải sổ tiền nộp.
* **Cảnh báo hai sổ lệch kỳ**: sao kê có khoản từ trước ngày kho POS bắt đầu thì cột Đã nộp gánh cả
  tiền mặt của những ngày chưa có số POS — vì thế Không khớp ra số âm. Nay nói thẳng lý do và cách
  chữa (nạp file POS kỳ trước).

= 1.19.0 =
* **Tổng hợp cả kỳ theo cơ sở**: doanh thu POS · CK/QR · tiền mặt · đã nộp · treo đầu kỳ · treo
  cuối kỳ · không khớp. Trả lời thẳng câu "tổng nộp có lệch so với doanh thu không".
* Nói rõ phép so đúng: **tiền nộp so với TIỀN MẶT, không so với tổng doanh thu** — phần khách trả
  bằng chuyển khoản và quét QR tự về tài khoản, không ai mang đi nộp. Đem nộp so với tổng doanh
  thu thì quán nào cũng "thiếu" đúng bằng phần QR, tháng nào cũng thiếu.

= 1.18.0 =
* **Tự nhận đúng sổ sao kê ngân hàng** (`saoke_gd`) và sổ cổng QR (`saoke_cong`): thêm tên cột
  thật — `tien`, `thoi_diem`, `nhan`, `diem_ban` — nên không phải chọn tay nữa.
* **`loai` là CHIỀU tiền (in/out), không phải nguồn.** Xếp nhầm là cột chiều bỏ trống và mọi khoản
  tiền ĐI cũng được cộng vào phần "đã nộp".
* Chỉ lấy giao dịch **hướng Đến** và **trạng thái Thành công**.
* Kéo về 0 khoản thì nói rõ vì sao — nhất là khi cả sổ là tiền cổng QR (đó là khách quét mã trả
  tiền, không ai phải mang đi nộp).

= 1.17.0 =
* **Chọn tay sổ sao kê khi máy dò không ra**: chọn bảng bất kỳ trong site → xem 3 dòng đầu và
  toàn bộ cột → tự chỉ cột nào là Ngày, Số tiền, Nội dung, Mã GD, Nhãn → kéo. Máy vẫn đoán sẵn,
  người khai chỉ sửa chỗ sai.
* **Quét mọi bảng**, không riêng bảng mang tiền tố WordPress — plugin có thể tạo bảng riêng.
* Ô chọn nguồn **hiện hai dòng gần nhất** của sổ đang chọn: nhìn nội dung chuyển khoản là biết
  ngay có phải sao kê ngân hàng hay không.

= 1.16.0 =
* **Đổi sang SỐ DƯ TREO cộng dồn.** Cơ sở gom mấy ngày nộp một cục (sao kê TÀU GÒ VẤP: 4 lần
  trong 2 tháng, mỗi lần 17–40 triệu), nên so từng ngày với từng ngày là 26/30 ngày đều đỏ kể cả
  khi người ta đã nộp đủ. Nay cộng dồn tiền mặt phải nộp trừ tiền đã về; một cú chuyển lớn xoá
  sạch phần treo của mấy ngày trước.
* Cột **Đang treo** thay cột Nộp tiền; ô đếm đổi thành **Tiền mặt đang treo ở cơ sở** và
  **Cơ sở treo quá N ngày** (N khai được, mặc định 10).
* Lịch đọc lại theo lối ấy: **✓ là ngày tiền về tài khoản**, chấm là ngày tiền dồn lên (bình
  thường), **✕ chỉ khi treo quá lâu và quá nhiều**. Cột cuối là số dư treo cuối kỳ.
* Số dư mở đầu tính từ toàn bộ lịch sử trước kỳ đang xem — không thì một cơ sở ôm 40 triệu từ
  tháng trước vẫn trông sạch sẽ.

= 1.15.0 =
* **Bảng đối soát nói rõ nó đang đọc sổ nào** — tên bảng, số khoản, khoảng ngày. Chọn nhầm sổ thì
  mọi kết luận "chưa nộp" đều sai, mà trước đó màn không hề cho biết mình đang đọc gì.
* **Lấy sổ mã nộp tiền có sẵn trong site**, khỏi gõ lại: chọn sổ → xem em ghép thử tên gọn
  ("TÀU GÒ VẤP") với tên dài của máy POS → sửa chỗ lệch → điền vào bảng. Em chỉ đề nghị, không tự
  ghi; tên nào không đoán ra thì để trống chứ không đoán bừa.
* Hai sổ mã (khu vui chơi / ghế massage) nhận riêng từng sổ một — nhà mình để riêng vì nhiều cơ sở
  trùng tên mà là hai dòng tiền khác nhau.

= 1.14.0 =
* **Không kết tội cơ sở chưa khai mã nộp tiền.** Kéo sao kê về mà chưa khai mã thì không khoản nào
  ghép được, và bản trước bày ra "chưa nộp 5,2 tr" cho từng ngày — nêu tên người có thể đã nộp đủ.
  Nay ghi rõ *chưa khai mã nộp tiền*, không tính vào ô "tiền mặt chưa về", và liệt kê những cơ sở
  còn thiếu khai.
* **Dò sổ sao kê theo CỘT, không theo tên bảng.** Lọc theo tên đã trượt đúng cái bảng cần tìm.
  Tên bảng là thứ người khác đặt; còn sổ tiền nào cũng phải có ngày, số tiền và nội dung.
* **Giữ nguyên chữ hoa của mã đã gõ.** Gõ `KH705KVCMN0002` mở lại thấy `kh705kvcmn0002` thì khớp
  vẫn đúng, nhưng người khai tưởng hệ làm hỏng mã của mình.

= 1.13.0 =
* **Lịch nộp tiền**: lưới cơ sở × ngày cho cả tháng, mỗi ô một dấu — ✓ đã nộp đủ · ▲ về thiếu ·
  ✕ chưa về · · chưa tới hạn. Nhìn một màn là thấy quán nào nộp đều, quán nào cứ cuối tuần là
  đứt. Cột cuối cộng tổng còn thiếu của từng cơ sở.
* **Tự dò sổ sao kê có sẵn trong site**: quét bảng trong chính MySQL (plugin Sao Kê Ngân Hàng,
  cổng SePay trong plugin Ghế…), bày ra kèm số dòng và khoảng ngày để chọn — không đoán hộ.
  Bảng nào có sẵn cột "Nhãn phân loại" thì tin nhãn ấy trước, nhãn rỗng mới tự đoán theo mã.
* **Bỏ tiền cổng QR** (VNPAY / MoMo / Việt QR) khỏi phần "đã nộp" — đúng cảnh báo trên màn sao kê
  nhà mình: tiền ấy khách trả thẳng vào tài khoản, không ai phải mang đi nộp; tính vào là quán
  nào nhiều khách quét QR cũng tự khắc "nộp đủ".

= 1.12.0 =
* **Tab Đối soát có hàng lọc riêng**: Ngày mới nhất / 7 ngày / 30 ngày / Tháng này / Tất cả, ô
  Từ–đến, và ô chọn cơ sở. Trước đó nó ăn theo kỳ chọn ở tab Doanh thu — mà ô chọn nằm ở tab kia,
  người đang đứng ở Đối soát không thấy gì để bấm.
* Thêm ô **"chỉ dòng cần xem"**: giấu những ngày đã nộp đủ, chỉ để lại dòng chưa nộp / vượt ngưỡng
  / chưa nhập báo cáo. Mấy ô đếm phía trên vẫn tính trên cả kỳ — "còn bao nhiêu tiền chưa về" mà
  đổi theo bộ lọc thì không còn là con số để nhìn mỗi sáng.

= 1.11.0 =
* **Nhận mặt cơ sở theo MÃ NỘP TIỀN** in trong nội dung chuyển khoản (`… ND IBFT VC Bien Hoa
  KH705MTDMN0023`), đúng lối nhà mình đang dùng. Màn khai bày theo cơ sở, mỗi cơ sở một ô mã —
  khai được nhiều mã, cách nhau dấu phẩy, nên đổi mã giữa chừng không làm sao kê cũ hoá "chưa gán".
* **Mã khớp trọn, có ranh giới hai đầu.** `KH705MTDMN0002` nằm gọn trong `KH705MTDMN0020`; tìm
  kiểu "có chứa" là tiền quán này chạy vào sổ quán kia, sai âm thầm theo một chiều cố định.
* Khoản chưa nhận ra cơ sở nay **gom theo mã đọc được** — mười khoản cùng một quán là một dòng
  phải khai, chọn cơ sở một cái là cả nhóm về sổ.
* Vẫn khai được mẩu chữ thường (tên quán, số tài khoản) cho những khoản người nộp quên gõ mã; mã
  luôn được xét trước chữ.

= 1.10.0 =
* **Tab Đối soát trả lời thẳng câu "nhân viên nộp tiền chưa"**: cột *Ngân hàng nhận* và cột
  *Nộp tiền* (đã nộp đủ / thiếu bao nhiêu / chưa tới hạn / muộn mấy ngày), cùng ô đếm
  **Tiền mặt chưa về tài khoản**.
* Hai cột ấy **không chờ cơ sở nhập báo cáo** — máy POS biết hôm ấy thu bao nhiêu tiền mặt, ngân
  hàng biết nhận được bao nhiêu, thế là đủ trả lời. (Trước đó cả hàng nấp sau ô "chưa nhập báo
  cáo", nên đúng chỗ cần nhìn nhất lại là chỗ trống.)
* Ngày chỉ bị tính là thiếu **sau giờ cắt của hôm sau** — tiền bán tối nay thì sáng mai mới mang
  ra ngân hàng, bôi đỏ ngay là bảng lúc nào cũng đỏ và không ai nhìn nữa.
* **Kéo thẳng từ cổng SePay** đã có sẵn trong plugin Ghế Massage, khỏi tải file: nút *Kéo giao
  dịch về* + tự kéo mỗi giờ. **Bỏ tiền khách trả ghế** (giao dịch đã nhận ra mã ghế) — đó là
  doanh thu, không phải nhân viên nộp tiền; cộng nhầm vào là phép đối soát thành vô nghĩa.

= 1.9.0 =
* **Cột "Thực nộp" lấy từ SAO KÊ NGÂN HÀNG**, không còn là số cơ sở tự khai. Nạp sao kê ở
  **Nạp báo cáo → Sao kê ngân hàng** (.xlsx hoặc .csv). Chỉ lấy tiền vào; nạp lại cùng kỳ không
  cộng dồn.
* Số cơ sở khai vẫn giữ để ĐỐI CHIẾU: khai đã nộp 10 triệu mà ngân hàng nhận 8 triệu thì bảng
  đối soát bày ngay chỗ lệch ấy, và có ô đếm "Khai nộp nhiều hơn ngân hàng nhận".
* **Quản trị → Sao kê ngân hàng**: khai mẩu chữ trong nội dung chuyển khoản (hoặc số tài khoản)
  → cơ sở. Khoá dài xét trước khoá ngắn. Khai thêm khoá là gán lại được cả những kỳ đã nạp, khỏi
  nạp lại file.
* **Giờ cắt** (mặc định 12h): tiền nộp trước giờ này tính cho doanh thu hôm trước — quán đóng cửa
  đêm, sáng hôm sau mới mang tiền ra ngân hàng.
* Khoản chưa nhận ra cơ sở được đếm và bày ngay trên bảng đối soát: mỗi khoản bỏ sót là một lời
  "chưa nộp" oan cho người đã nộp thật.
* Sửa: `fgetcsv()` thiếu tham số `$escape` — trên PHP 8.4 in Deprecated vào giữa JSON, giao diện
  nhận được "not valid JSON". Và bỏ dấu tiếng Việt nay không phụ thuộc `remove_accents()` của
  WordPress.

= 1.8.0 =
* **Một mã cơ sở ghép được nhiều quán trên máy POS.** Cùng một điểm Gò An Lạc mà máy POS tách
  thành "FUNZONE ADVENTURE GO AN LẠC" và "COFFE GO AN LẠC", trong khi sổ nhân sự chỉ có một mã và
  một cửa hàng trưởng coi cả hai. Ô chọn một-đổi-một nay thành danh sách tích, tích bao nhiêu quán
  cũng được. Bảng ghép khai kiểu cũ (một chuỗi) vẫn đọc nguyên, không phải khai lại.

= 1.7.0 =
* **Một người phụ trách được hai (hay nhiều) cơ sở** — tích thêm cơ sở cho họ ở trang Nhân sự là
  bên này tự theo. Mọi phép lọc nay nghĩ bằng danh sách cơ sở, không phải một cơ sở.
* **Vai "duyệt" (kế toán, quản lý, admin) xem tổng mọi cơ sở**, không bị bó vào cơ sở của mình.
  Kế toán đứng ở mã văn phòng (VP_KH-HCM) — một mã không phải quán nào cả, không bao giờ ghép
  được; trước đó chính người cần nhìn cả 15 quán lại là người thấy rỗng.
* Bảng Ghép cơ sở thôi đòi ghép những mã mà mọi người ở đó đều vai duyệt, và nói rõ ai đang phụ
  trách mấy cơ sở.
* Ô chọn cửa hàng cũng cắt theo phần của người xem — trước vẫn liệt kê đủ 15 quán, chọn quán
  người ta thì nhận màn trống, trông y như hệ hỏng.

= 1.6.0 =
* **Đăng nhập bằng PIN chấm công** cho người được đẩy từ trang Nhân sự sang — không tạo tài khoản
  WordPress cho họ nữa. Thẻ phiên đi ở header (không phải cookie), sống 30 ngày; vai và cơ sở đọc
  lại từ sổ mỗi lượt gọi nên gỡ quyền là ăn ngay.
* Cổng nhận người: `khh_dt_day_vao()` / `khh_dt_day_ra()` / `khh_dt_da_day()`. PIN trùng thì người
  cũ mất PIN và được kể tên ra.
* Tab **Quản trị → Ghép cơ sở**: khai mã cơ sở bên nhân sự ↔ tên cơ sở trên máy POS. Chưa ghép thì
  người của mã ấy KHÔNG thấy cơ sở nào (trước đây ô cơ sở trống nghĩa là thấy hết).
* Cắt dữ liệu theo cơ sở ngay ở câu truy vấn, và chặn cả đường ĐỌC báo cáo ngày của cơ sở khác —
  trước chỉ chặn đường ghi.
* Ô "người nhập" của báo cáo ghi kèm Mã NV với người vào bằng PIN, để còn truy lại được.

= 1.5.0 =
* Tab **Quản trị**: danh sách ai được nhập báo cáo, cơ sở phụ trách và quyền, sửa ngay tại chỗ.
* Cổng **day-nhan-vien** để trang nhân sự đẩy cửa hàng trưởng sang: xác thực bằng token, chưa có
  tài khoản thì tự tạo và trả về mật khẩu, gỡ quyền bằng tham số bo=1.
* Tách quyền: nạp file POS và xoá kho vẫn cần edit_posts; nhập báo cáo ngày chỉ cần được cấp
  quyền (meta khh_dt_quyen), nên cửa hàng trưởng là tài khoản thường vẫn nhập được.

= 1.4.0 =
* Thêm tab **Nhập báo cáo ngày** cho cơ sở và tab **Đối soát**.
  Máy POS tự điền phần của nó (doanh thu, hoá đơn, số vé, tiền mặt, chuyển khoản);
  cơ sở chỉ nhập thứ máy POS không biết: tiền mặt đếm trong két, tiền thực nộp, bill huỷ,
  tổng lượt chạy, tổng khách vào, vé giấy đã soát. Hệ thống tính lệch ngay khi gõ.
* Gán cơ sở cho người dùng trong trang Hồ sơ: người phụ trách một cơ sở chỉ nhập và xem
  cơ sở đó. Sửa sau khi chốt vẫn giữ bản cũ trong lịch sử.
* Đếm số vé từ cột "Loại món" của FABi để đối chiếu với số khách cơ sở đếm tại cửa.

= 1.3.1 =
* Sửa lỗi nạp file báo "Call to undefined function wp_tempnam()": hàm đó chỉ có trong
  trang quản trị, không có khi chạy REST — nay tự tạo file tạm.
* Đọc được bản xuất FABi kiểu mới (không có dòng tiêu đề, không có cột Pos ID, ô số viết
  dạng <c><v>…</v></c> không kèm thuộc tính). Trước đây kiểu này đọc ra doanh thu bằng 0
  vì bộ tách ô bỏ sót các ô không thuộc tính làm lệch hết cột; nay tách bằng SimpleXML.
* Nhận thêm tên cột "Hoá đơn" (bản mới không còn cột "Mã hoá đơn").
* File không có dòng tiêu đề thì tự ghi kỳ báo cáo theo khoảng ngày đọc được.

= 1.3.0 =
* Lỗi PHP trong lúc nạp giờ trả về câu tiếng Việt kèm tên file và số dòng, thay vì trang
  HTML làm giao diện báo "not valid JSON" hay "mã 500" chung chung.
* Thêm nút **Kiểm tra máy chủ** trong hộp nạp: xem PHP, ZipArchive, XMLReader, thư mục
  tạm, bộ nhớ, thời gian chạy, giới hạn tải lên và bảng dữ liệu đã tạo chưa.
* Bảng dữ liệu tự tạo lại nếu lúc kích hoạt plugin chưa tạo được.

= 1.2.0 =
* Nạp file theo từng mẩu 1MB: file mấy chục MB vẫn nạp được dù hosting chặn tải lên ở
  mức thấp. Trước đây gửi nguyên file thì máy chủ cắt ngang và trả trang HTML, giao diện
  báo lỗi khó hiểu "Unexpected token '<'".
* Báo lỗi bằng tiếng Việt khi máy chủ trả HTML thay vì dữ liệu (413 / 403 / lỗi 5xx).

= 1.1.0 =
* Đường link ngoài cho báo cáo (mặc định /doanh-thu-hcm), đổi được trong trang quản trị.
  Bảng đường dẫn tự nạp lại khi đổi slug, không phải vào Cài đặt → Đường dẫn tĩnh bấm Lưu.

= 1.0.0 =
* Bản đầu: nạp file .xlsx/.csv của FABi, báo cáo theo ngày/cửa hàng/khung giờ/PTTT/nguồn/món,
  lọc theo kỳ và theo cửa hàng, in ra PDF, và sẵn đường nối API FABi.
