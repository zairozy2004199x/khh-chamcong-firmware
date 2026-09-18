=== Báo Cáo Chi Phí (K&H) ===
Contributors: khh
Tags: chi phí, phân bổ, báo cáo, MISA, kế toán
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.16.0
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

= 1.16.0 =
* 🔴 **Hiện phần doanh thu bên nguồn chưa nối vào điểm nào.** Trang Ghế 01→17/09 tổng
  1.216.383.000 mà báo cáo chỉ thấy 546.005.000 — chênh nằm ở những cơ sở bên Ghế chưa ai ghép
  vào điểm bán. Bản trước lặng lẽ bỏ qua, nên nhìn Tổng quan thì tưởng tháng này bán kém chứ
  không ai nghĩ là **thiếu liên kết**.
* Tổng quan có dòng **THIẾU LIÊN KẾT**: bao nhiêu tiền, bao nhiêu cơ sở, tên vài cơ sở đầu, và
  nút sang thẳng tab Doanh thu để ghép.
* Hộp xem trước khi nạp ghi rõ **tổng của chính nguồn** và **phần sẽ bỏ lại** — đối chiếu thẳng
  được với con số trên trang nguồn, khỏi phải cộng tay.
* Tab Lương cũng vậy: lương bên Nhân sự chưa nối vào dòng nào thì nói ra bằng số.

= 1.15.0 =
* 🔴 **Sửa lỗi Tổng quan trộn hai kỳ.** Đổi ô chọn tháng chỉ đổi cái *nhãn* — doanh thu, lương,
  cột nhập tay và tiền từng khoản vẫn là số của kỳ cũ. Liên kết sống thì nạp doanh thu kỳ MỚI đè
  lên, ra một báo cáo trộn hai kỳ mà nhìn thì hoàn chỉnh (%CP/DT ra 254%, tổng vẫn cộng đẹp,
  không ô nào đỏ). Tệ hơn: lần lưu kế tiếp đẩy mớ ấy lên máy chủ dưới tên kỳ mới.
* Mỗi bộ số giờ mang **dấu kỳ** (`soCuaKy`). Lệch với kỳ đang chọn là Tổng quan **báo đỏ ngay
  trên đầu**, kèm hai lối thoát: *Dựng kỳ này từ danh mục (đưa mọi số về 0)* hoặc *Quay về kỳ cũ*.
  Tab Kiểm tra cũng ghi thành LỖI.
* Hộp "Kỳ chưa có trên máy chủ — dựng kỳ mới?" mà bấm **Huỷ** thì nay **quay về kỳ cũ**, thay vì
  đứng lại ở kỳ mới với nguyên số của kỳ cũ.
* Bắt được cả những kỳ đã lỡ lưu số của kỳ khác từ trước.

= 1.14.0 =
* **Lương Mục III lấy thẳng từ trang Nhân sự (Chấm công), mỗi cơ sở một dòng.** Nút
  **⬇ Nạp lương từ Nhân sự** trên Mục III: xem trước từng cơ sở → ghép vào dòng lương (hoặc tạo
  dòng mới) → bấm Ghi. Ghi xong là **nhớ liên kết**, kỳ sau số tự về như doanh thu.
* Số lấy bằng cách **gọi đúng hàm tính lương của bên Chấm công**, không chép lại luật tính sang
  đây — một bản sao luật lương sẽ lệch dần mỗi lần bên kia sửa, mà lệch về tiền lương thì không
  ai phát hiện bằng mắt.
* 🔴 **Cơ sở chưa khai giá giờ thì KHÔNG ghi số 0.** Khu vui chơi (Funzone, Tutu, Event, Farm,
  Pinball) bên Chấm công mới có giờ công, chưa ra tiền. Ghi 0 vào báo cáo thì nhìn y hệt "tháng
  này không có lương" — lương của cả một nhóm biến mất mà tổng vẫn cộng đẹp. Những cơ sở ấy bị
  **bỏ qua**, giữ nguyên số cũ, và giao diện bày rõ **lý do** thay vì bày số 0.
* Cột "Theo báo cáo" của dòng đã liên kết chuyển sang chỉ đọc khi bật **🔗 Tự lấy** — gỡ liên kết
  cạnh tên cơ sở hoặc tắt Tự lấy là gõ tay lại được.
* Mất liên kết (cơ sở không còn bên Nhân sự) cũng **giữ số cũ** và báo ra, không ghi đè bằng 0.

= 1.13.0 =
* **Nguồn thứ hai: doanh thu Posh / JP lấy thẳng từ plugin Ghế Massage** (khmatrix.com/ghe →
  Báo cáo tổng). Cùng lối liên kết sống như Doanh thu FABi: nối một lần, từ đó tự về mỗi kỳ.
* Tab Doanh thu có hai nút **⬇ Nạp từ FABi** và **⬇ Nạp từ Ghế**; cột Nguồn ghi rõ điểm đang lấy
  từ đâu (🔗 FABi / 🔗 Ghế); dòng trạng thái tách riêng số điểm của từng nguồn.
* **Một điểm chỉ nối MỘT nguồn** — nối nguồn này thì tự gỡ nguồn kia. Hai nguồn cùng ghi vào một
  điểm là chúng đè nhau mỗi lần mở kỳ, và số cuối cùng phụ thuộc cái nào chạy sau.
* Điều kiện lọc dữ liệu giống hệt màn Báo cáo tổng của chính plugin Ghế, nên hai màn không bao
  giờ nói hai số khác nhau cho cùng một cơ sở.

= 1.12.0 =
* **FABi mở cơ sở mới → tự tách điểm mới bên này**, không còn kẹt ở "chưa ghép". Ô chọn có thêm
  nhóm **"➕ Tạo điểm mới trong <bộ phận>"**, và một nút tạo hàng loạt cho mọi cửa hàng còn lại
  (đoán bộ phận từ tên quán: Tutu · VR FUN/FUNZONE → Funzone · ECO FARM → Farm · GHOST/SNOW/Ngôi
  Nhà Ma → Event). Vẫn sửa được từng dòng trước khi Ghi.
* Điểm mới nhận luôn doanh thu kỳ này và **nối sẵn** với cửa hàng FABi, nên từ kỳ sau tự cập nhật.
* **Không bịa Mã đơn vị** — đó là mã trong sổ MISA; bịa ra thì bút toán hạch toán vào một đơn vị
  không tồn tại mà nhìn tờ nhập vẫn thấy có mã. Để trống, và màn Kiểm tra có cảnh báo riêng cho
  điểm có doanh thu mà thiếu mã.

= 1.11.0 =
* **Liên kết sống với Doanh thu FABi** — anh Thắng: *"thay vì đẩy thì nó tự link và lấy realtime"*.
  Nối một lần, từ đó doanh thu tự về mỗi khi mở kỳ; ô doanh thu của điểm đã nối thành chỉ-đọc,
  cột **Nguồn** cho biết số đến từ đâu (🔗 FABi hay gõ tay), gỡ liên kết được bằng một nút.
* Công tắc **🔗 Tự lấy** trên tab Doanh thu (tự bật sau lần nối đầu tiên), kèm dòng trạng thái:
  đang nối bao nhiêu điểm, lấy lúc nào, có điểm nào đứt liên kết.
* **Đường tự động chỉ đi theo liên kết đã chốt, tuyệt đối không đoán tên.** Muốn nối thêm thì qua
  nút "⬇ Nạp / nối thêm điểm" — ở đó có màn xem trước để người nhìn rồi mới quyết.
* **Kỳ đã chốt thì dừng lấy**, số đứng yên. Cửa hàng biến mất bên FABi thì **giữ số cũ** và báo
  ra — đưa về 0 thì trông y hệt một tháng ế, mà thật ra là mất liên kết.

= 1.10.0 =
* **Sửa: kỳ mới mở ra mang nguyên số liệu kỳ trước.** Đổi sang kỳ chưa có, app hỏi "đẩy dữ liệu
  đang có lên làm bản gốc?" — bấm OK là nó chép cả doanh thu, lương và tiền từng khoản. Kỳ mới
  mở ra đã có một bộ số trông hoàn chỉnh mà là số THÁNG TRƯỚC, không dòng nào báo.
  Nay chép **danh mục** (bộ phận, điểm bán, mã đơn vị, tài khoản, nội dung MISA, cách chia, liên
  kết FABi, tích "không nhận chi phí") và đưa **mọi số về 0**.
* Thêm **"Xoá số liệu kỳ này (giữ danh mục)"** ở menu ⋯ — dọn kỳ đang mở mà không nhảy sang tháng
  sau, để chữa những kỳ đã lỡ mang số cũ.

= 1.9.0 =
* **Nạp doanh thu thẳng từ trang "Doanh thu FABi"** (khmatrix.com/doanh-thu-hcm). Nút ở tab
  Doanh thu: đọc doanh thu kỳ đang chọn, cộng theo cửa hàng, ghép với điểm bán rồi **bày ra xem
  trước** — đổi được từng dòng, bấm Ghi mới vào báo cáo.
* Ghép tên tự động (bỏ dấu, cắt đuôi pháp nhân "( Dịch Vụ và Giải Trí K&H )", so từ đặc trưng).
  **Không đoán bừa**: hai điểm cùng giống như nhau thì để trống cho người chọn. Lựa chọn được
  **nhớ lại** nên kỳ sau tự ghép.
* Đọc thẳng bảng của plugin kia (cùng một WordPress), chỉ đọc, không bao giờ ghi sang.
  Site chưa cài Doanh thu FABi thì báo bằng tiếng Việt chứ không lỗi trắng màn hình.

= 1.8.0 =
* **Nút "Xuất tờ MISA" — file RIÊNG chỉ có tờ nhập.** Nút cũ xuất workbook 17 sheet mà sheet đầu
  là "File tổng báo cáo" (bố cục khác hẳn), nên MISA đọc trúng sheet đầu rồi báo sai cột — dù
  các sheet "<Bộ phận> chi tiết" bên trong hoàn toàn đúng. Nay có 2 nút: một bộ phận, hoặc cả 7
  (mỗi bộ phận một sheet, không lẫn sheet báo cáo nào). Tên file: MISA_<bộ phận>_MN_T08_2026.xlsx
* Nút cũ đổi tên thành **"Xuất file tổng"** cho khỏi nhầm.
* Thêm hai ô gộp ở dòng nhãn ("Chi tiết hạch toán" I1:AE1, "Hóa đơn" AF1:AX1) cho khớp mẫu gốc.

= 1.7.0 =
* **Sửa: tài khoản lương phải theo TỪNG bộ phận.** Bản trước gieo 64131 (của Posh) cho cả bảy —
  Event thật ra là 64191. Đếm lại trên cả 7 tab của file gốc: Tutu 64101 · JP 64111 ·
  Funzone 64121 · Posh 64131 · Farm 64161 · Pinball 64171 · Event 64191 (TK Có 3341 chung).
  Sai số này thì chứng từ vẫn nhập được vào MISA, chỉ là chi phí lương bộ phận này chạy vào tài
  khoản bộ phận khác — sổ vẫn cân, chỉ sai chỗ, nên soát sổ không bắt được.
* Bộ phận tự thêm (không có trong bảng) thì để trống tài khoản, không bịa một số trông hợp lý;
  màn Kiểm tra đã có cảnh báo lo phần nhắc.

= 1.6.0 =
* **Bổ sung cột "Mã đối tượng Có"** (cột 15 của mẫu MISA) — mã nhà cung cấp. Đếm lại trên file
  gốc T8/2026: trong 50 cột chỉ 11 cột có dữ liệu, bản trước điền 10, bỏ sót đúng cột này
  (1.497 ô). Lấy từ ô "Mã đối tượng" đã có sẵn ở tab Chi phí đầu vào.
* Chứng từ lương (TK Có 3341) và phân bổ 1543 vẫn để trống cột này — đúng như file gốc, vì
  không có nhà cung cấp nào để trỏ tới.
* Thêm người gác trong bài kiểm: 50 cột của mẫu và 11 cột phải điền được chốt lại bằng con số
  đếm từ file gốc, ai thêm cột mà quên nối dữ liệu là báo đỏ ngay.

= 1.5.0 =
* **Sửa: tab "Chi tiết MISA" bày cột khác file và thiếu cột.** Thiếu Ngày chứng từ, Ngày hạch
  toán, Số tiền quy đổi, và xếp sai thứ tự. Gốc: cột được kê ở HAI NƠI — một bảng cho file, một
  danh sách viết tay cho màn hình. Nay chỉ còn MỘT bảng (`MISA_COLS` + `MISA_MAP`), cả hai bên
  cùng đọc, nên không còn chỗ để lệch.
* Thêm nút **"Đủ 50 cột như file"** — bày trọn mẫu MISA kể cả 40 cột để trống, để đối chiếu mẫu
  cho chắc trước khi xuất.

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
