=== Nền tảng K&H ===
Contributors: khh
Tags: quan-ly-cong-viec, nhan-su, cham-cong, bang-luong, intranet
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.20.0
License: Proprietary

Nền tảng quản trị nội bộ 16 ứng dụng chạy ngay trong WordPress: dự án & công việc, báo cáo dự án,
đề xuất, quy trình, hồ sơ nhân sự, chấm công, bảng công, nghỉ phép, bảng lương, thông báo, tri thức,
họp, trò chuyện, bảng tin, đặt tài nguyên.

== Description ==

Cài xong vào menu **Nền tảng K&H** trong trang quản trị, hoặc nhúng vào trang bất kỳ bằng
shortcode `[khh_platform]`. Dữ liệu lưu trong bảng riêng của WordPress, mọi người đăng nhập
đều thấy chung và cập nhật cho nhau. Quyền lấy theo tài khoản WordPress.

== Installation ==

1. Trang quản trị → Plugin → Cài mới → Tải plugin lên → chọn file zip.
2. Kích hoạt.
3. Vào menu **Nền tảng K&H**.

== Changelog ==

= 1.20.0 =
* **Ứng dụng mới "Báo cáo Dự Án" — trang tổng.** Ứng dụng Công việc & Dự án trả lời "dự án NÀY
  đang thế nào"; trang này trả lời "TẤT CẢ dự án đang thế nào", thứ mà tới giờ phải mở từng dự
  án ra cộng tay. Bốn màn: Tổng quan, Theo bộ phận, Theo người, Cần xử lý.
* Số liệu **tách bạch hai loại và ghi rõ trên màn hình**: nhóm "hiện tại" (còn bao nhiêu việc,
  quá hạn bao nhiêu, sắp đến hạn trong 7 ngày) tính tại lúc mở trang và KHÔNG chạy theo kỳ báo
  cáo — việc quá hạn từ tháng trước hôm nay vẫn đang quá hạn; nhóm "trong kỳ" (mở mới, hoàn
  thành, tỷ lệ đúng hạn) mới đi theo kỳ 30 ngày / 90 ngày / năm nay / tất cả.
* Tỷ lệ đúng hạn **chỉ tính trên việc có đặt hạn** — gom việc quên đặt hạn vào mẫu số thì tỷ lệ
  tự đẹp lên theo số việc làm ẩu.
* Lọc theo **bộ phận** (ô Bộ phận của dự án), biểu đồ nhịp độ mở mới / hoàn thành theo tuần,
  bảng dự án xếp được theo tên, bộ phận, tiến độ hoặc hạn chót (dự án chưa đặt hạn xuống cuối).
* Màn **Cần xử lý**: dự án trễ hạn, dự án đứng im 14 ngày không động tĩnh, việc quá hạn lâu
  nhất, việc chưa giao ai. Bấm vào là sang thẳng dự án đó bên Công việc & Dự án.
* Xuất **CSV** toàn bộ bảng dự án (dấu `;` và BOM để Excel bản tiếng Việt mở ra đúng cột, đúng dấu).
* **Hộp "Tài khoản của bạn" nay xem được hồ sơ nhân sự của chính mình** — mã nhân sự, chức danh,
  bộ phận, mảng, cơ sở, quản lý trực tiếp, ngày vào làm, hợp đồng, phép còn lại, liên hệ. Không
  bày ngày sinh, số sổ BHXH, tài khoản ngân hàng: chủ hồ sơ đã biết, bày ra chỉ thêm rủi ro khi
  có người ngó màn hình.
* **Nhân viên tự đổi mật khẩu ngay trong nền tảng.** Phải nhập đúng mật khẩu hiện tại; sai 5 lần
  thì khoá 15 phút, đếm theo TÀI KHOẢN chứ không theo IP (cả cơ sở dùng chung một đường mạng,
  đếm theo IP là một người gõ sai khoá cả cửa hàng). Trước đây muốn đổi phải nhờ quản trị đặt
  lại rồi đọc mật khẩu mới qua điện thoại — mật khẩu đi qua tay người thứ ba là mất ý nghĩa.
* Cả hai việc trên bật/tắt được ở **Cấu hình → Tài khoản của nhân viên**, kèm ô độ dài mật khẩu
  tối thiểu (6–64, mặc định 8) và tuỳ chọn có hiện lương cơ bản / phụ cấp hay không.

= 1.19.0 =
* Không đổi tính năng — đi cùng lượt phát hành chung của kho.

= 1.18.0 =
* **Tự cập nhật từ GitHub Releases**: hiện nút "Cập nhật" ngay ở màn Plugin như mọi plugin
  khác, không phải dựng zip rồi tải lên tay nữa. Lọc tag theo tiền tố `khh-platform-v…` nên
  bản của các bộ khác trong cùng kho không hiện nhầm sang đây. Khoá GitHub dùng chung ô
  `vhcp_gh_token` với các bộ cùng site — khai một lần là cả nhà cùng thấy bản mới; chưa khai
  thì vẫn chạy bình thường, chỉ là trần số lượt gọi API thấp hơn.
* Khai tên vào bảng của trang IT (`khmatrix.com/it`) qua bộ lọc `vhcp_tu_cap_nhat_ds`.

= 1.17.0 =
* **Tự nối với plugin Chấm Công (K&H) đang chạy cùng site — không cần khai gì.** Thấy bảng
  `vhcc_cham_cong` là nền tảng đọc thẳng: Dữ liệu chấm công và Bảng công cơ sở có số ngay,
  trang Nguồn chấm công chỉ còn báo "Đã tự nối". Khai một bảng khác bằng tay thì bảng đó
  được ưu tiên.
* Giá trị công lấy **đúng luật của bên Chấm Công**: số phút = giờ ra − giờ vào, không trừ
  nghỉ trưa, không áp ca chuẩn của nền tảng. Ra sớm hơn vào là dấu hiệu ghi sai → hiện giờ
  nhưng không tính công, để người soát nhìn thấy. Hàng chính và hàng ca đêm `-CD` cùng ngày
  gộp thành một ngày công.
* **Cơ sở của từng người tự lấy theo sổ nhân viên bên Chấm Công** (cột cửa hàng) lúc trả về
  giao diện — Bảng công cơ sở chia đúng mà không phải gán tay hàng trăm người. Ai đã gán tay
  thì giữ nguyên; không ghi gì vào hồ sơ.
* Mã chạy song song (`vhcc_ma_song_song`) được tôn trọng: dòng ghi bằng mã cũ vẫn về đúng
  người.
* Cột Thiết bị hiện cơ sở của máy đã ghi, cột Phương thức dịch nguồn ghi (máy chấm công,
  chấm bù, chấm công online).
* Toàn bộ kiểm thử bằng WordPress 6.4.3 + MariaDB thật, plugin Chấm Công (K&H) 3.87 thật.

= 1.16.1 =
* Sửa lỗi **cả nền tảng trắng trơn** trên site để Đường dẫn tĩnh ở mức "Mặc định". Khi đó
  `rest_url()` trả về dạng `?rest_route=/khh/v1/` — đã có sẵn dấu `?`. Giao diện nối thêm
  `state?since=0` vào nên thành hai dấu `?`, WordPress không nhận ra tuyến nào và trả 404:
  không một dòng dữ liệu nào tải được. Nay tham số nối bằng `&` khi cần.
  Lỗi này tìm ra khi chạy thử trên WordPress thật, bộ kiểm thử giả không lộ ra.

= 1.16.0 =
* **Bỏ hẳn việc chép dữ liệu chấm công sang.** Nền tảng nay **đọc thẳng** bảng của phần mềm cũ
  mỗi lần trả dữ liệu về giao diện. Không còn bản thứ hai để lệch nhau, không phải chạy lại
  hàng tháng, sửa bên phần mềm cũ thì bên này đổi theo.
* Trang quản trị đổi tên thành **Nguồn chấm công**. Khai một lần — bảng nào, cột nào — rồi thôi;
  đó là thứ duy nhất máy không tự đoán được.
* Ngày công người dùng **tự sửa trong nền tảng luôn thắng** số của phần mềm cũ, nhưng vẫn giữ
  lại giờ vào/ra đọc được. Sửa xong là thấy ngay, không phải chờ.
* Giao diện hỏi máy chủ mỗi 8 giây, nhưng dữ liệu chấm công chỉ gửi lại **khi nội dung thật sự
  đổi** — không tải lại cả tháng công mỗi lần hỏi.
* Đệm 2 phút giữa hai lần đọc cơ sở dữ liệu cũ, và lọc thẳng bằng SQL theo cột ngày, nên bảng
  vài trăm nghìn dòng vẫn nhẹ.
* Khối trạng thái đầu trang cho biết đang đọc bảng nào, bao nhiêu dòng, bao nhiêu người, và
  những mã nhân sự chưa khớp — kèm nút Đọc lại ngay, Tắt, Đổi bảng.
* Gỡ bỏ: nút ghi bản sao vào nền tảng, lịch chạy mỗi giờ, và các tuỳ chọn giữ/ghi đè — không
  còn ý nghĩa khi đã đọc thẳng.

= 1.15.0 =
* **Tự lấy dữ liệu chấm công, khai một lần rồi thôi.** Khai nguồn xong là nền tảng tự đọc lại
  bảng của phần mềm cũ — mỗi giờ theo lịch WordPress, và mỗi khi có người mở nền tảng
  (giãn cách 5 phút cho khỏi nặng máy chủ). Không phải vào trang nhập nữa.
* Mỗi lượt chỉ lấy vài tháng gần nhất (mặc định 2, chỉnh được 1–12), và lọc **thẳng bằng SQL**
  theo cột ngày nên không kéo cả bảng hàng trăm nghìn dòng về mỗi lần.
* Cột ngày lưu dạng chữ (15/09/2026) thì không lọc bằng SQL — so sánh chuỗi sẽ ra sai — mà
  đọc rồi lọc trong PHP.
* Bước 1 **tự đoán sẵn bảng chấm công**: bảng nào tên và cột giống bảng chấm công thì lên đầu
  danh sách và được chọn sẵn, chỉ việc bấm xem thử.
* Đầu trang có khối trạng thái: đang bật hay tắt, lấy từ bảng nào, lần chạy gần nhất thêm và
  cập nhật bao nhiêu ngày công của bao nhiêu người, bao nhiêu dòng chưa khớp người. Kèm nút
  **Lấy dữ liệu ngay** và **Tắt tự lấy**.
* Nhiều người mở nền tảng cùng lúc thì chỉ một lượt chạy, không dẫm lên nhau.
* Tắt plugin thì gỡ luôn lịch chạy.

= 1.14.0 =
* Trang quản trị mới **Nhập chấm công**: đọc thẳng bảng chấm công của phần mềm cũ nằm chung
  máy chủ MySQL, không phải xuất Excel rồi nhập lại mỗi tháng. Chỉ đọc, không sửa và không
  xoá gì bên cơ sở dữ liệu đó. Chỉ Quản trị website mở được.
* Ba bước không cần JavaScript: chọn cơ sở dữ liệu và bảng → ghép cột (tự đoán sẵn, xem kèm
  vài dòng đầu) → đối chiếu rồi mới ghi.
* Nhận ba dạng bảng hay gặp: mỗi dòng một ngày có giờ vào / giờ ra; mỗi dòng một ngày có sẵn
  cột giờ công; và nhật ký quẹt thẻ thô — gom theo người và ngày rồi lấy mốc sớm nhất làm
  giờ vào, muộn nhất làm giờ ra.
* Đọc được nhiều kiểu ghi: ngày 2026-09-15 hay 15/09/2026, giờ 08:10:00 hay 6:05 PM, giờ công
  8 / 7.5 / 7:30 / 450 phút. Tên cột viết dính kiểu EmployeeCode cũng đoán ra.
* Khớp người theo Mã nhân sự, không có mã thì theo họ tên (bỏ dấu, không phân biệt hoa
  thường). Hồ sơ trùng tên nhau thì **không đoán bừa** — báo số dòng bỏ qua.
* Bước đối chiếu nói rõ bao nhiêu dòng dùng được, bao nhiêu chưa khớp người, chưa đọc được
  ngày, và nêu vài mã chưa khớp để sửa hồ sơ rồi chạy lại.
* Chọn được khoảng ngày, và chọn giữ nguyên hay ghi đè ngày đã có. Ghi đè vẫn giữ những gì
  phần mềm cũ không có, VD ảnh chấm công đã chụp.

= 1.13.1 =
* **Bảng công tự lấy số từ Dữ liệu chấm công.** Ngày nào có giờ vào và giờ ra mà chưa nhập tay
  thì bảng công tự tính ra số giờ, hiện màu xám gạch chân để phân biệt với số nhập tay.
* Cách tính lấy phần giao với ca chuẩn nên **nghỉ trưa không bị tính thành công**: vào 08:10
  ra 18:00 ra đúng 8h chứ không phải 9h50m. Chỉ làm ca sáng thì ra 3.5h và mã ca C1.
* Số nhập tay luôn thắng số của máy. Bấm vào ô đang tự tính thì hộp sửa điền sẵn con số đó —
  lưu lại là **chốt**, sau này máy chấm công đổi giờ cũng không ghi đè.
* Ngày nghỉ phép và ngày có giờ ra trước giờ vào thì không tính bừa thành giờ công.
* Thẻ thống kê thứ tư đổi thành **Lấy từ máy chấm công**, cho biết bao nhiêu giờ là tự tính.
* Mã ca chuẩn đổi từ S1/S2 sang **C1/C2** cho khớp cách gọi trên bảng công.
* Vẽ bảng nhanh hơn: mỗi người chỉ đọc bản ghi tháng một lần thay vì dò lại từng ô.

= 1.13.0 =
* **Bảng công cơ sở** thay cho tab Máy chấm công trong ứng dụng Chấm công: mỗi cơ sở một bảng
  công riêng, lưới cả tháng, một dòng một người, cột TỔNG bên phải, chân bảng đếm số người và
  tổng giờ. Thứ bảy và chủ nhật tô đỏ.
* Bảng công **chỉ giữ giá trị công** — số giờ và mã ca (VD 4.9 · C1-C2). Giờ vào/ra, thiết bị,
  phương thức vẫn nằm nguyên ở **Dữ liệu chấm công** (màn thời gian thực theo ngày) và không bị
  ghi đè khi nhập bảng công.
* Giờ công lưu bằng **phút** nên cộng dồn không sai số; nhập được "4.9", "4,9", "4:56" hay
  "7h30"; hiện ra dạng "4h 56m".
* Nút **Nhập công hàng loạt**: điền cùng một số giờ cho cả cơ sở hoặc một người, từ ngày đến
  ngày, có tuỳ chọn bỏ qua cuối tuần.
* Trường **Văn phòng** trong hồ sơ nhân sự đổi tên thành **Cơ sở** và nhớ lại các cơ sở đã
  dùng để chọn lại; đây cũng là trường quyết định người đó nằm ở bảng công nào.
* Máy chấm công vẫn khai và sửa được như cũ — bấm vào máy ở nhóm Thiết bị bên trái.

= 1.12.2 =
* Ô **Bộ phận** trong hộp Sửa dự án nhớ lại những bộ phận đã dùng: gõ một lần, lần sau bấm
  vào ô là chọn lại. Sổ gợi ý gộp cả bộ phận trong module Nhân sự, bộ phận ghi trên hồ sơ
  nhân viên, và bộ phận đã gõ ở các dự án khác.
* Gõ đúng bộ phận cũ nhưng khác hoa thường thì lấy lại đúng cách viết cũ, không đẻ thêm bản
  trùng làm lệch mọi bộ lọc và báo cáo.
* Nhãn ô đổi từ "Department" sang **Bộ phận** cho khớp với các màn còn lại.
* Hộp **Công việc mới**: chọn nhóm công việc thì **người phụ trách hiện sẵn**. Nhóm cố định
  chỉ một người quen làm thì điền luôn; nhiều người thì bày tên ra ngay dưới ô, bấm là chọn,
  ai nhiều việc hơn đứng trước. Hộp Chi tiết công việc không đụng tới, tránh đổi người phụ
  trách của việc đang chạy.

= 1.12.1 =
* Sửa lỗi **không tải được tệp lên**. Ba vai trò nền tảng (Nhân viên / Quản lý / Quản trị K&H)
  cần quyền `upload_files`, nhưng WordPress bỏ qua `add_role()` khi vai trò đã tồn tại — nên
  site cài từ bản cũ giữ mãi vai trò thiếu quyền, và nhân viên bấm đính kèm thì bị từ chối.
  Nay mỗi lần lên bản mới plugin tự soát và cấp bù quyền còn thiếu.
* Câu báo lỗi nói đúng bệnh: mã lỗi máy chủ trả về, hết quyền, quá dung lượng, hay mất mạng —
  thay cho câu chung chung "Bản này có thể chưa bật kho tệp".
* Mức dung lượng tối đa lấy theo đúng cấu hình PHP của hosting (thường 2–8MB) thay vì tự đặt
  20MB rồi để người dùng chọn tệp xong mới báo quá nặng.
* Tài khoản không có quyền tải tệp thì nút **+ Thêm tệp** khoá sẵn và nói rõ lý do.
* Trang quản trị **Nền tảng K&H** có mục **tự chẩn đoán**: quyền tài khoản, quyền của ba vai
  trò, thư mục kho tệp ghi được hay không, và mức `upload_max_filesize` / `post_max_size` thật.
  Thiếu quyền vai trò thì có sẵn nút **Cấp quyền tải tệp cho các vai trò** ngay tại đó.

= 1.12.0 =
* Hộp **Chi tiết công việc** đính kèm được **ảnh và PDF**: ảnh hiện hình thu nhỏ, PDF hiện
  nhãn đỏ, bấm vào mở ở tab mới. Mỗi tệp tối đa 20MB, mỗi công việc tối đa 20 tệp.
* Chọn nhiều tệp một lượt; tệp không phải ảnh hay PDF bị từ chối ngay, không tải lên.
* Gỡ tệp có hỏi xác nhận; đính kèm và gỡ đều ghi vào Lịch sử hoạt động của công việc.
* Tệp đi qua Thư viện của WordPress, nên vẫn nằm trong site và phân quyền như mọi tệp khác.

= 1.11.1 =
* Sửa lỗi bấm **Thoát** báo "Liên kết bạn theo dõi đã hết hạn". Đường dẫn thoát dựng bằng
  `wp_nonce_url()` — hàm đó bọc dấu `&` thành `&amp;` cho HTML, mà chuỗi này đi thẳng vào
  JavaScript, nên tham số biến thành `amp;_wpnonce` và WordPress không đọc được mã.
* Mã cũ hoặc thiếu thì đưa về lại nền tảng, không bày trang "liên kết hết hạn" của WordPress.

= 1.11.0 =
* Hộp **Tài khoản của bạn** có nút **Thoát** — trước đây vào rồi không có chỗ đăng xuất,
  phải mò vào trang quản trị WordPress. Có hỏi xác nhận trước khi thoát.
* Hộp này hiện thêm **tên đăng nhập**, để nhân viên biết mình đang dùng tài khoản nào.
* Đường dẫn thoát có mã chống giả mạo: không ai ép người khác đăng xuất bằng một
  đường dẫn gửi qua chat.

= 1.10.0 =
* **Đăng nhập ngay trên nền tảng**: mở `?khh_app=1` chưa đăng nhập thì thấy màn đăng nhập
  mang giao diện K&H, gõ tên đăng nhập và mật khẩu tại chỗ — không còn đá sang wp-login.php.
* Chống dò mật khẩu: sai 8 lần thì khoá 15 phút theo địa chỉ IP; câu báo lỗi cố tình nói
  chung chung, không hé lộ tên đăng nhập nào có thật.
* Khai được **logo** cho màn đăng nhập ở trang Cấu hình.
* Sửa lỗi: bấm Lưu ở trang **Cấu hình** đang ghi đè cả ô cài đặt, **xoá sạch mã PIN** đã tạo.
  Giờ chỉ gộp phần vừa sửa vào bản cũ.

= 1.9.0 =
* Trang **Cấp tài khoản**: tạo tài khoản bằng TÊN ĐĂNG NHẬP + MẬT KHẨU giao tận tay,
  không cần email và không phụ thuộc việc hosting có gửi được thư hay không.
* Cấp hàng loạt: tích chọn nhiều hồ sơ, máy lấy mã nhân sự làm tên đăng nhập và sinh
  mật khẩu 10 ký tự (bỏ các chữ dễ đọc nhầm 0/O, 1/l/I). Mỗi lượt tối đa 300 người.
* Mật khẩu hiện đúng một lần, có nút in danh sách để phát cho nhân viên.
* Đặt lại mật khẩu cho người đã có tài khoản, cũng hiện một lần.

= 1.8.0 =
* Nhập hồ sơ nhận thẳng file **Excel .xlsx** — không phải đổi sang CSV nữa. Đọc bằng PHP thuần
  (ZipArchive + SimpleXML), lấy bảng tính đầu tiên, ô ngày tháng tự đổi về ngày/tháng/năm.
* Thêm cách khớp **"Mã nhân sự, hồ sơ chưa có mã thì dò theo họ tên"** và đặt làm mặc định:
  những hồ sơ tạo tay lúc đầu (Giám đốc, Admin…) không còn bị nhân đôi khi nhập lại.
* Sửa lỗi: nhập lại làm **mất Vai trò và Tình trạng** đã chỉnh trong nền tảng. Giờ hai ô này
  chỉ bị ghi đè khi file thật sự có cột đó và ô có giá trị.

= 1.7.0 =
* **Mảng kinh doanh** (Posh, HVC…): khai ngay trên hồ sơ từng người, bộ phận vẫn dùng chung
  giữa các mảng — Phòng kế toán chỉ khai một lần, không phải nhân đôi.
* Người làm cho cả hai mảng để **Dùng chung**: mảng nào cũng thấy và quản lý được.
* Chọn một mảng ở thanh bên thì cả phần nhân sự — danh sách, hợp đồng, cơ cấu tổ chức,
  báo cáo — chỉ hiện người của mảng đó cộng người dùng chung.
* Bảng lương tách theo mảng; cộng các mảng lại đúng bằng tổng toàn công ty.
* Báo cáo nhân sự thêm phần nhân sự và quỹ lương theo mảng.
* **Gán mảng hàng loạt**: chọn một bộ phận, gán cả bộ phận vào một mảng trong một thao tác.
* Tách quyền theo mảng: Quản lý chỉ thao tác với người cùng mảng, cộng người dùng chung.
  Bật sẵn, tắt được, chạy độc lập với giới hạn theo bộ phận.
* Nhập hồ sơ nhận thêm cột Mảng kinh doanh (mảng, khối, công ty, thương hiệu, business unit…).

= 1.6.0 =
* Màn hình **Bộ phận** trong Hồ sơ nhân sự: đổi tên, gộp, xoá, sắp thứ tự, đặt trưởng bộ phận.
  Đổi tên một bộ phận cập nhật luôn hồ sơ của mọi nhân sự thuộc bộ phận đó.
* Ô "Bộ phận" trong hồ sơ nhân sự chuyển thành danh sách gợi ý và tự nắn về đúng tên đã có,
  hết cảnh "Phòng Vận Hành" và "Phòng vận hành" thành hai bộ phận khác nhau.
* Giới hạn phạm vi của vai trò Quản lý theo bộ phận: chỉ duyệt nghỉ phép, sửa chấm công và xem
  lương của người trong bộ phận mình phụ trách. Bật sẵn, tắt được ở màn hình Bộ phận.
* REST mới `POST khh/v1/bulk`: ghi tối đa 200 bản ghi một lượt, xét quyền như `khh/v1/doc`.
* Nhóm dữ liệu `depts` chỉ Quản trị và Chủ sở hữu mới ghi được.

= 1.5.0 =
* Nhập thẳng từ cơ sở dữ liệu cùng site: bảng bất kỳ, custom post type, hoặc người dùng WordPress —
  không cần xuất file. Chỉ đọc, có kiểm tên bảng trước khi truy vấn.
* Tự đoán cả tên cột kỹ thuật tiếng Anh của các phần mềm nhân sự phổ biến.

= 1.4.0 =
* Nhập hồ sơ nhân sự từ hệ thống cũ bằng CSV: tự đoán cột tiếng Việt, xem trước, chống trùng.
* Xuất hồ sơ nhân sự ra CSV.
* Hướng dẫn để phần mềm cũ tự đẩy dữ liệu sang qua REST + application password.

= 1.3.0 =
* Mã PIN cho nhiều người: mỗi tài khoản một mã, tạo hàng loạt và hiện một lần để gửi đi.
* Đăng nhập thử với tư cách người khác dành cho Quản trị viên, có thanh nhắc và nút quay lại.

= 1.2.0 =
* Đăng nhập nhanh bằng mã PIN để chạy thử: mặc định tắt, mã lưu dạng băm, bắt buộc có hạn,
  sai 5 lần khoá 15 phút theo IP, chỉ tạo phiên tạm, có nút Tắt ngay.

= 1.1.0 =
* Đăng nhập bằng Google (Google Identity Services), giới hạn theo tên miền email.
* Tự tạo và gắn tài khoản với hồ sơ nhân sự khi đăng nhập lần đầu.
* Trang Tài khoản: tạo tài khoản cho từng hồ sơ, gửi lại thư đặt mật khẩu, tạo hồ sơ cho tài khoản sẵn có.
* Trang Cấu hình: Client ID, tên miền được phép, chính sách tạo tài khoản.
* Ba vai trò WordPress riêng: Nhân viên / Quản lý / Quản trị K&H.

= 1.0.0 =
* Bản đầu tiên: 15 ứng dụng, REST API riêng, phân quyền theo tài khoản WordPress.
