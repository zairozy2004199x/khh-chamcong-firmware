=== K&H — Báo cáo doanh thu FABi ===
Contributors: khh
Tags: doanh-thu, bao-cao, fabi, ipos, pos
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.2
Stable tag: 1.20.1
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
theo tên · PIN · mã cơ sở · vai. Họ mở link báo cáo, gõ chính PIN chấm công đang dùng hằng ngày,
và chỉ thấy cơ sở của mình.

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
