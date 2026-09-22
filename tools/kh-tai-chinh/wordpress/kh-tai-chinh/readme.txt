=== Tài Chính K&H ===
Requires at least: 5.6
Requires PHP: 7.4
Stable tag: 1.7.2
License: GPL-2.0-or-later

Theo dõi ngân hàng, giao dịch và đối soát cho CÔNG TY TNHH DỊCH VỤ VÀ GIẢI TRÍ K&H.

== Description ==

Dựng lại bản KH Bank Tracker (Node.js) thành plugin WordPress để chạy thẳng trên
host hiện có, không cần Render/Railway, không cần SSH.

Dữ liệu nằm trong bảng MySQL của chính website, không phải file JSON — bản gốc
giữ toàn bộ giao dịch trong một tệp 95 MB, mỗi lần mở trang là nạp cả tệp vào
RAM; trên shared hosting cách đó không chạy nổi.

Đăng nhập dùng luôn tài khoản WordPress, không dựng bảng mật khẩu riêng. Có màn
hình Người dùng để quản trị viên cấp tài khoản cho kế toán ngay trong plugin,
khỏi đi vòng qua wp-admin và khỏi phải hiểu hệ thống vai trò của WordPress.

== Đang có ==

* Tổng quan — tổng số dư, thu/chi tháng này, số dư từng tài khoản, các đợt đối
  soát gần nhất còn lệch
* Ngân hàng — thêm/xoá tài khoản, số dư đầu tính từ một ngày mốc
* Giao dịch / Sao kê — thêm tay, dán sao kê hàng loạt, lọc theo tài khoản /
  khoảng ngày / thu-chi / nội dung, phân trang 100 dòng. Dán chồng kỳ không
  nhân đôi số dư: dòng nào đã có sẵn trong tài khoản đó (nhận ra qua mã giao
  dịch) sẽ bị bỏ qua và máy nói ra đã bỏ bao nhiêu. Dòng không có mã thì không
  chặn được — máy đếm riêng và báo, vì gộp nhầm hai lần thu thật giống hệt
  nhau là mất tiền, sai nặng hơn để lọt một dòng trùng.
* Đối soát — VietQR, Payoo, VNPay, Zalo Mini App, MoMo. Kết quả có một dòng
  tự kiểm: tổng cổng trừ phí lệch quá 5% so với tiền thực nhận, hoặc không dòng
  nào ghép được theo mã giao dịch, thì máy nói thẳng là kết quả chưa đáng tin
  trước khi người dùng kịp đọc bốn bảng. Dán bảng cổng gửi về,
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
* Dán thô — copy nguyên cả sheet từ file của cổng, máy tự nhận là file gì
  (Sao kê QR ngân hàng, Payoo, VNPay, MoMo) và tự lấy đúng cột. Nhận dạng bằng
  dòng tiêu đề, không phải tên tệp. Luôn xem trước 10 dòng rồi mới ghi. Nạp đi
  qua đúng đường dán thường ngày nên vẫn chặn trùng, chặn kỳ khoá.
* Danh mục điểm — nối mã cửa hàng trong sao kê với điểm xuất hoá đơn, mã Misa,
  khu vực, dịch vụ. Đặt cờ "bỏ qua" cho mã test, mã vãng lai, gian đã đóng.
  Nạp hàng loạt; mã đã có thì cập nhật đè, cờ bỏ qua giữ nguyên.
* Sinh hoá đơn từ sao kê — chọn kỳ và tick nguồn tiền (sao kê tài khoản nào,
  đợt cổng nào), máy gom tiền theo điểm rồi xuất mỗi điểm một hoá đơn. Xem
  trước trước khi ghi. Mặc định mỗi điểm mỗi ngày một hoá đơn — đúng luật công
  ty đang dùng; đổi sang gộp cả kỳ được nếu cần một tờ tổng. Tiền mang mã chưa có trong danh mục được liệt kê riêng
  chứ không bỏ lặng. Số hoá đơn cấp liên tiếp; trùng một số ở giữa thì dừng
  hẳn và không ghi gì.
* Hoá đơn đầu ra — nối thẳng với công cụ Đối soát VAT: dán vào và xuất ra đúng
  22 cột của file đó, đúng thứ tự, không phải sắp lại. Bảng gom theo thuế suất
  chính là mấy dòng điền vào tờ khai GTGT; gom thêm theo khu vực và dịch vụ.
  Số hoá đơn duy nhất trong mỗi pháp nhân, chặn ở tầng bảng.
* Hoá đơn đầu vào — VAT được khấu trừ, gom theo thuế suất và nhà cung cấp.
  Hoá đơn từ 20 triệu trả tiền mặt được đặt sẵn là không khấu trừ (nhắc, bật
  lại được). Bảng tờ khai GTGT: VAT đầu ra trừ VAT đầu vào được khấu trừ ra số
  phải nộp, hoặc phần chuyển sang kỳ sau.
* Công nợ — phải thu (từ hoá đơn đầu ra) và phải trả (từ chi phí chuyển khoản),
  chia theo mốc tuổi nợ 1–30 / 31–60 / 61–90 / trên 90 ngày, gom theo khách
  hàng và nhà cung cấp. Ghi trả từng đợt, hoặc tự ghép từ sao kê. Một sổ thanh
  toán duy nhất: còn nợ luôn bằng tổng chứng từ trừ tổng đã trả.
* Hồ sơ — sổ lưu chứng từ giấy: có gì, file ở đâu, đã hạch toán chưa. Máy dò
  nối chứng từ với bút toán trong sổ theo số chứng từ, và trả lời được câu
  ngược lại: bút toán nào trong kỳ chưa có chứng từ lưu.
* Pháp danh — sổ hợp đồng thuê gian hàng và hợp đồng nhà cung cấp. Cảnh báo
  hợp đồng sắp hết hạn hoặc đã quá hạn, đếm hợp đồng chưa có bản đủ dấu, và
  bảng doanh thu chia sẻ ghép với hoá đơn đầu ra qua mã điểm nội bộ.
* Báo cáo — một trang gom cả kỳ: dòng tiền từng tài khoản (số dư đầu / thu /
  chi / số dư cuối), bảng xu hướng 12 tháng, doanh thu theo khu vực và dịch vụ,
  chi phí cộng chéo, thuế GTGT, công nợ cuối kỳ, đối soát còn lệch. Nút kỳ
  nhanh (tháng trước, quý, năm) và tải CSV. Chạy lại kỳ cũ luôn ra đúng số cũ.
* Khoá sổ — đặt một ngày khoá cho mỗi pháp nhân; mọi bản ghi mang ngày từ đó
  trở về trước không thêm, không xoá được. Chặn cả lối vòng: xoá tài khoản
  ngân hàng còn giao dịch trong kỳ khoá cũng bị từ chối.
* Nhật ký — ai làm gì lúc nào. Xoá thì giữ lại nguyên văn bản ghi nên phục hồi
  được, đúng id cũ. Dán hàng loạt chỉ ghi một dòng tổng kết.
* Sao lưu — tải toàn bộ kho dữ liệu ra một tệp .json và nhập lại được. Nhập là
  thêm vào, id cấp lại và mọi liên kết nối lại theo id mới.
* Dữ liệu mẫu — một nút nạp sẵn 89 dòng giả (13 tháng sao kê, hoá đơn, chi
  phí, hợp đồng, một đợt đối soát đã chạy) để xem thử mọi màn hình trước khi
  nhập số thật, và xoá sạch được bằng một nút khác. Chỉ nạp được khi sổ còn
  trống, và chỉ xoá đúng những dòng chính nó đã tạo.
* Người dùng — cấp tài khoản đăng nhập cho kế toán. Tài khoản mới mang vai trò
  riêng "Kế toán K&H": mở được sổ, KHÔNG sửa được bài viết, trang hay cài đặt
  của website. Trước đây phải cho họ làm Editor mới vào được sổ, tức là kèm
  quyền sửa xoá mọi trang. Mật khẩu hiện đúng một lần lúc tạo và không ghi vào
  nhật ký. Gỡ quyền không xoá tài khoản. Chỉ quản trị viên thấy màn hình này.
* Dữ liệu kèm trong bản cài — nếu bản cài có thư mục du-lieu chứa tệp .json,
  màn hình Sao lưu hiện nút nhập thẳng tệp đó, khỏi phải tải lên lần nữa.
  Dùng khi kho dữ liệu lớn hơn giới hạn tải tệp của host. Mã nguồn không kèm
  thư mục này; nó chỉ có khi ai đó cố ý gói dữ liệu vào một bản cài riêng.
* Bản web ngoài — cùng các màn hình đó ở /tai-chinh/ thay vì trong wp-admin.
  Vẫn phải đăng nhập.

== Giao diện ==

Menu dọc bên trái, gom theo nhóm (Tổng quan · Dòng tiền · Đối soát · Hoá đơn ·
Sổ · Hệ thống). Khung nhập liệu gấp lại được — mở trang ra là thấy bảng số
ngay, ô nhập nằm sau một cú bấm. Bảng có dòng kẻ so le và tiêu đề dính khi
cuộn. Bản in bỏ hết nút bấm và ô lọc, mỗi khung không bị cắt ngang trang.

Chạy được trên điện thoại: menu thành một hàng vuốt ngang, thẻ số xếp dọc.

== Bản web ngoài ==

Sau khi kích hoạt, mở https://tenmien.vn/tai-chinh/ . Nếu ra 404 thì vào
Cài đặt → Đường dẫn tĩnh và bấm Lưu một lần để WordPress ghi lại luật đường dẫn.
Host không bật đường dẫn tĩnh thì dùng https://tenmien.vn/?khtc_man=tong-quan .

Trang đặt noindex và bắt đăng nhập, người ngoài không xem được.

== Làm tiếp ==

Mọi mảng của bản gốc đã dựng lại xong. Hai phần cố ý không mang sang: đồng bộ
Google Sheet và đồng bộ Google Drive — plugin không với tới hai nơi đó, và một
đường nạp dữ liệu im lặng từ bên ngoài là thứ khó dò nhất khi số sai. Mọi màn
hình đều có ô dán bảng thay thế.
