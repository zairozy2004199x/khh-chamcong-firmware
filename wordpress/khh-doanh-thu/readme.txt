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
