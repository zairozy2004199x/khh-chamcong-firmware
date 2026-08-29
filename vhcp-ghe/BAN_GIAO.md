# Bàn giao — plugin ghế `vhcp-ghe`

Cập nhật: 2026-08-29 · Phiên bản hiện tại: **1.72.0** · Nhánh phát triển: `claude/posh-qr-kh1urz`
(Chỉ commit/push lên nhánh này, không mở PR nếu chưa được yêu cầu.)

Đây là plugin WordPress phục vụ trang ngoài `/ghe` (SPA đăng nhập bằng PIN) cho hệ thống thanh
toán ghế massage POSH của K&H. Tài liệu này ghi lại việc đã làm gần đây, một vấn đề đã đóng (giữ
lại để giải thích), và **việc cần kiểm tiếp với anh Thắng** — để người tiếp nhận không phải dò lại
từ đầu.

---

## 1. Việc đã làm gần đây

### v1.72.0 — Nối "Báo cáo doanh thu" vào Quỹ tiền mặt + thoát 1 lần + đóng được Đối chiếu máy

Bốn việc, cùng đợt 29/08:

**1. Doanh thu báo cáo nay tính vào "đang cầm" ở tab Quỹ & nộp tiền.** Anh Thắng: *"Sau khi nhân
viên chốt báo cáo doanh thu, thì nó sẽ hiển ở đây là doanh thu nhân viên đang cầm. Trừ khi nhân
viên tích vào đã nộp thì nó chuyển sang vàng, và kế toán tích vào thì đã hết nợ"*. Trước bản này,
tab "Quỹ & nộp tiền" ("Tôi đang cầm"/"Ai đang cầm tiền") chỉ gom tiền từ 2 nguồn (`chot` — chốt ca
quét QR ghế, `thu` — thu tại quầy); tiền khai qua "Báo cáo doanh thu" (chỉ số/QR nhập tay, việc cả
phiên làm việc này xây) hoàn toàn KHÔNG nằm trong đó, dù nhân viên vẫn đang cầm tiền mặt thật cho
tới khi nộp.

- Thêm cột `bc.nop_id` (giống hệt cơ chế `chot.nop_id`/`thu.nop_id` có sẵn): 0 = báo cáo này tiền
  còn trên tay nhân viên; khác 0 = đã gộp vào một lượt "Nộp về quầy", chờ xác nhận.
- `VHG_Quy::dang_cam()`, `ai_dang_cam()`, `bao_cao_ca()`, `nop()`, `huy_nop()` đều nối thêm nguồn
  thứ ba này — dùng lại NGUYÊN VẸN luồng trạng thái đã có: **đang cầm** (nop_id=0) → nhân viên bấm
  **"Nộp về quầy"** → **chờ xác nhận** (tô nền vàng, cùng tông với các khung cảnh báo khác) → quản
  lý/kế toán bấm **"Đã nhận"** → **hết nợ**. Không dựng cơ chế mới, chỉ nối thêm một đường ống vào
  cơ chế cũ.
- ⚠️ **Khớp theo TÊN** (`bc.nhan_vien` so với `chot.nguoi`/tên đăng nhập token) — cùng namespace
  tên người mà `chot`/`thu` vẫn dùng. **Cần kiểm với anh Thắng**: PIN Báo cáo doanh thu của một
  nhân viên phải có `ten` GIỐNG HỆT tên tài khoản `/ghe` (token) của chính người đó thì số mới gộp
  đúng vào một dòng "đang cầm" — hai tên khác nhau (VD PIN ghi "Lan" nhưng tài khoản ghi "Lý Thị
  Ngọc Lan") sẽ tách thành HAI người khác nhau trên bảng "Ai đang cầm tiền".
- Tiện thể vá luôn 1 lỗi phát hiện khi soát code: cờ hiện nút "Đã nhận" (`quyen_nhan`) ở nhánh
  quản trị đang nhét cứng "chỉ Admin/Quản lý", trong khi quyền THẬT (`nop_nhan`/`nop_huy`) là quyền
  "chốt doanh số" — CẤU HÌNH ĐƯỢC, kế toán hoặc vai trò khác vẫn làm được mà không cần lên Quản lý.
  Vai trò được cấp quan_tri kiểu khác Admin/Quản lý (VD Cửa hàng trưởng) trước đây bị giấu mất nút
  dù đủ quyền — nay dùng chung đúng một hàm `duoc_chot_doanh_so()` như nhánh còn lại vẫn đang làm.

**2. "Thoát" trong Báo cáo doanh thu nay thoát LUÔN cả trang chính.** Anh Thắng: *"2 trang này là
1, tại sao thoát 2 lần"*. `/ghe` có HAI phiên đăng nhập tách biệt cố ý (PIN báo cáo riêng, token
trang chính riêng) cùng hiện trên một trang — trước đây bấm Thoát trong màn Báo cáo doanh thu chỉ
đóng lớp phủ, để lộ trang chính vẫn còn đăng nhập token, bắt bấm Thoát thêm lần nữa. Nay bấm Thoát
ở Báo cáo doanh thu gọi luôn `window.VHG_Trang.thoat()` — một cú bấm thoát cả hai, không đụng gì
tới việc ĐĂNG NHẬP (vẫn cần đúng PIN/token riêng như cũ).

**3. Nút "Đối chiếu máy" đóng lại được.** Anh Thắng: *"Lỡ xổ cái đối chiếu máy, giờ đóng lại không
được"*. Trước đây bấm là chạy, không có đường đóng — bảng đứng yên mãi tới khi tải lại cả trang.
Nay bấm lần 1 chạy đối chiếu (đổi chữ nút "Đóng đối chiếu"), bấm lần 2 xoá bảng + trả chữ nút về.

**4. Bảng tự co giãn theo màn hình máy tính/điện thoại** (nội dung gốc, giữ nguyên bên dưới).

### v1.71.0 — Bảng tự co giãn theo màn hình máy tính/điện thoại

Anh Thắng 29/08: *"Điều chỉnh trang tự co giãn theo màn hình máy tính và điện thoại"*.

- **Nguyên nhân**: mọi bảng trong `/ghe` (bảng "Số liệu từng ghế", Đối chiếu, Lịch sử tháng, Lịch
  sử chốt ca) dùng chung lớp `.bc-t`, và lớp này ép cứng `min-width:820px` — kể cả 3 bảng phụ chỉ
  5-7 cột ngắn và cả bảng ghế ở CHẾ ĐỘ GỌN (điện thoại, đã bớt cột "Tiền mặt/Thực thu/Ghi chú").
  Ép rộng 820px bắt điện thoại nào cũng phải cuộn ngang mới xem hết, dù nội dung thật sự đã đủ hẹp
  để vừa màn hình.
- **Vá**: bỏ `min-width` khỏi luật `.bc-t` chung; chỉ gắn lại (`.full`) cho đúng bảng ghế ở CHẾ ĐỘ
  ĐẦY ĐỦ (máy tính, 10 cột, có 2 ô nhập chữ "Thực thu"/"Ghi chú" thật sự cần bề ngang). Bảng ghế ở
  Gọn (`.gon`) và 3 bảng phụ nay tự co theo đúng nội dung, không còn bị ép rộng giả.
  Đồng thời bớt đệm ô + thu nhỏ chiều rộng tối thiểu của ô nhập trong chế độ Gọn (`.bc-t.gon`) cho
  vừa khít điện thoại phổ thông hơn.
- **Chưa hết cuộn ngang 100%** trên điện thoại màn rất nhỏ (dưới ~390px) — bảng vẫn là bảng số
  liệu dạng cột, không phải thẻ xếp dọc như màn "Sửa 24h". Anh Thắng test lại trên máy thật, còn
  thấy cuộn khó chịu ở đâu thì báo tiếp, em vá thêm.

Nhân tiện vá thêm 1 lỗi phát hiện cùng lúc: nút **"Đối chiếu máy"** trước đây bấm là CHẠY, không có
đường đóng lại — anh Thắng: *"Lỡ xổ cái đối chiếu máy, giờ đóng lại không được"*. Bảng đối chiếu đổ
ra rồi đứng yên mãi, muốn thu gọn lại phải tải lại cả trang. Nay bấm lần 1 chạy đối chiếu (như cũ,
đổi chữ nút thành "Đóng đối chiếu"), bấm lần 2 xoá bảng + trả chữ nút về — cùng kiểu bật/tắt với nút
"Sửa/Đóng" ở khung "Báo cáo trong 24h".

### v1.70.0 — Vá "Nộp" đứng số cũ sau khi ghi đè Thực thu + tô đỏ cho kế toán

Anh Thắng 29/08 bắt được ở ghế VP-PQ-16 (màn kế toán, tab Duyệt báo cáo): Tiền mặt đã ghi đè xuống
830.000đ nhưng cột "Nộp" vẫn đứng ở 990.000đ (số cũ trước khi ghi đè) — *"Chỗ này bị sai"*, kèm yêu
cầu *"Chỉ số nào thực thu (báo đỏ lên cho kế toán biết)"*.

- **Nguyên nhân**: `nop_so_tien` (cột "Nộp") được rải một lần lúc gửi báo cáo theo đúng `tien_mat`
  tại thời điểm đó (`chia_nop_()`). Sửa `tien_mat` sau đó — qua "Sửa 24h" của nhân viên
  (`VHG_BaoCao::sua_dong()`) hoặc qua màn kế toán (`VHG_KeToan::sua()`) — không tự kéo `nop_so_tien`
  theo, vì đây là cột lưu riêng, không tính lại mỗi lần đọc.
- **Vá**: cả hai hàm sửa trên nay TỰ CẬP NHẬT `nop_so_tien` theo số tiền mặt mới, nhưng CHỈ khi
  dòng đó trước đó đã nộp **duy nhất vừa đủ** đúng số `tien_mat` cũ (case phổ biến nhất — "nộp đủ"
  là mặc định). Nộp dở dang thì để nguyên — không đủ dữ kiện chia lại đúng giữa nhiều ghế cùng báo
  cáo, sửa sai còn nguy hơn để kế toán tự đối chiếu.
- **Hoàn tác** (`VHG_KeToan::undo()`, việc `'sua'`) cũng cập nhật theo: nhật ký sửa nay lưu thêm
  `nop_so_tien` cũ để hoàn tác trả lại đúng, không chỉ trả lại chỉ số/tiền mặt mà bỏ quên cột Nộp.
- **Tô đỏ**: màn kế toán (`ktdRow`) nay tô đỏ + đậm số "Tiền mặt" VÀ dòng ghi chú khi ghế đang có
  "Thực thu ghi đè" (trước chỉ tô đỏ dòng ghi chú bắt đầu bằng ⚠, bỏ sót câu ghi đè đứng một mình).

### v1.69.0 — Bắt buộc ảnh khi Sửa 24h (tối thiểu 1 ảnh/ghế)

Anh Thắng 29/08: *"bổ sung thêm ảnh trong báo cáo 24h nhé (tối thiểu 1 ảnh nhé)"*. Card sửa từng
ghế ở "Báo cáo trong 24h — sửa được" (`theGheSua`) nay có thêm 2 ô chọn ảnh (📷 Ảnh chỉ số / 🧹 Ảnh
vệ sinh, cùng kiểu nút với bảng nhập chính):

- Ghế **đã có sẵn ảnh** từ lúc gửi ban đầu → chỉ hiện số ảnh đã có, không bắt đính lại.
- Ghế **chưa có ảnh nào** → bấm "Lưu ghế này" bị chặn (kèm cảnh báo đỏ) cho tới khi chọn ít nhất 1
  trong 2 ảnh. Chọn xong thì ảnh được nén (giống ảnh ở bảng nhập chính) rồi gộp thêm vào đúng ghế.
- Chốt lại lần nữa ở server (`VHG_BaoCao::sua_dong()`): dù client bị bỏ qua/lỗi thời, server vẫn
  tự đếm ảnh cũ (cột `bc_dong.anh`) + ảnh mới trong `patch.images`, tổng bằng 0 thì từ chối lưu.
- `ds_24h()` (bc_recent) nay trả thêm `anh` (mảng URL ảnh đã có) cho mỗi ghế để client biết trước.

### v1.68.0 — Báo cáo TỔNG (không chi tiết), hiện lại số tiền ở Sửa 24h, gọn 1 hàng

Ba việc nhỏ, cùng theo yêu cầu anh Thắng 29/08:

- **Báo cáo tổng**: ô "Ảnh chứng từ nộp tiền" sẵn có nay kiêm luôn đường nộp THAY THẾ khi nhân
  viên không điền bảng chi tiết từng ghế — *"Ô này là nộp báo cáo tổng nếu không làm báo cáo
  kia"*. Đính ảnh + gõ số vào ô "Số tiền nộp" (dùng lại, đóng vai "Tổng doanh thu") rồi bấm Gửi:
  hệ thống ghi MỘT dòng `bc_dong` duy nhất (`ma_may=''`, không chỉ số/QR riêng), đánh dấu rõ trong
  ghi chú là báo cáo tổng để kế toán biết đối chiếu qua ảnh chứng từ, không qua chỉ số máy. Ảnh
  chứng từ là BẮT BUỘC trong luồng này (không có ảnh thì không tạo được báo cáo). Server:
  `VHG_BaoCao::luu_tong()` (mới) + dispatch `bc_submit_tong`. Client: nhánh mới trong
  `guiBaoCao()` khi bảng ghế trống nhưng có ảnh + có số tiền → gọi `guiBaoCaoTong()` (mới).
  Còn bảng chi tiết từng ghế thì luồng cũ (`bc_submit`) không đổi gì.
- **Sửa 24h hiện lại số tiền**: card sửa từng ghế trong "Báo cáo trong 24h — sửa được" nay có thêm
  dòng tính SỐNG "Actual: …đ · Tiền mặt (đủ): …đ" (kèm "đang ghi đè bằng Thực thu" khi có), cập
  nhật ngay khi gõ lại Chỉ số sau/QR/Thực thu — *"vẫn sẽ hiện số tiền thực thu và chỉ số tiền mặt
  đủ như lúc nhập gửi báo cáo"*, không phải bấm Lưu mới biết đúng/sai.
  Đồng thời 4 ô Chỉ số sau/QR/Thực thu/Ghi chú trong card này dồn từ 2 hàng x 2 cột thành **1 hàng
  4 cột** cho gọn — *"điều chỉnh thành 1 hàng luôn cho nó gọn"*.

### v1.67.0 — Cột "Tăng/Giảm" đổi thành "Thực thu": GHI ĐÈ, không còn cộng dồn

Anh Thắng 29/08: *"cột này là cột thực thu"* rồi *"khi nhập thực thu ở cột này, tiền cộng sẽ lấy
theo cột này"*. Trước đây cột "Tăng/Giảm" CỘNG vào công thức tiền mặt
(`actual − QR + điều_chỉnh`). Nay đổi hẳn:

- Đổi tên cột thành **"Thực thu"** ở màn nhập báo cáo chính, màn "Báo cáo trong 24h — sửa được",
  và ô "±" ở màn kế toán duyệt lẻ từng ghế (`theGheSua`).
- **Có gõ** ở cột này → tiền mặt phải nộp LẤY ĐÚNG số đó, ghi đè hẳn, không cộng vào công thức.
  **Bỏ trống** → vẫn tính theo công thức `actual − QR` như cũ, y như chưa từng có cột này.
- Áp dụng cho **mọi hàng**, không chỉ hàng bất thường (chỉ số ngược / công thức ra âm) như cơ chế
  "Thực thu ghi đè" ban đầu — hàng bất thường vẫn bắt buộc phải có (kèm lý do), hàng thường thì
  đây là lựa chọn. Gộp làm một với ô "Thực thu" trong khung cảnh báo cũ — không còn hai ô riêng.
- Server (`tinh_()`, `luu()`, `sua_dong()`) đều đã cập nhật cùng luật; `bc_recent()` (dùng cho màn
  Sửa 24h) chỉ trả số ra ô khi báo cáo ĐÃ thật sự ghi đè (đọc dấu "Thực thu ghi đè" trong ghi chú)
  — báo cáo cũ trước bản này (có số ở cột `dieu_chinh` theo nghĩa cộng dồn) hiện ra Ô TRỐNG, không
  bị hiểu nhầm thành một lượt ghi đè.
- ⚠️ **Chưa động tới** màn kế toán duyệt HÀNG LOẠT (dán bảng CSV `KTN_CANON`, cột `adjust`) — đó
  là màn NHẬP LẠI số liệu lịch sử đã chốt sẵn (actual/cash/total đã tính từ hệ cũ), không chạy qua
  công thức nào ở đây nên không bị ảnh hưởng, nhưng cũng chưa đổi nhãn cột cho khớp.

### v1.66.3 — "Không đọc được trả lời của máy chủ" khi bấm "Đối chiếu máy"

Anh Thắng 29/08, cơ sở POSH nhiều ghế bấm "Đối chiếu máy" ở màn báo cáo PIN thì báo *"Không đọc
được trả lời của máy chủ (mạng hoặc tường lửa)"* — cùng câu lỗi với v1.66.2 nhưng ở một nút khác
hẳn, nên là một nguyên nhân khác.

- **Gốc thật:** `VHG_BaoCao::doi_chieu()` hỏi CSDL **hai lượt riêng cho MỖI ghế** (QR · tiền mặt),
  cơ sở vài chục ghế thành vài chục lượt hỏi trong một lần bấm. Mỗi lượt lại lọc bằng
  `DATE(luc)=%s` — bọc cột `luc` trong hàm nên MySQL không dùng được phần `luc` của khoá
  `may (ma_may,luc)`, phải dò hết mọi hàng của riêng máy đó chứ không chỉ hàng trong ngày. Cộng
  dồn là vượt hẳn thời gian chờ mặc định (25s), trình duyệt báo đúng như lỗi mạng dù đây là chậm ở
  máy chủ.
- **Sửa:** đổi `DATE(luc)=%s` sang dạng khoảng `luc>=... AND luc<...` (dùng được trọn khoá
  `may`), và gom lại đúng **hai câu `GROUP BY ma_may` cho cả cơ sở trong một lượt** thay vì hỏi lại
  từng ghế — tra kết quả bằng mã máy trong bộ nhớ. Không đổi số liệu trả về, chỉ nhanh hơn.
- Thời gian chờ riêng cho lượt này cũng nâng 25s→45s làm lưới an toàn thứ hai.

### v1.66.2 — "Lỗi khi gửi báo cáo" ở cơ sở đính nhiều ảnh

Anh Thắng 29/08, cơ sở đính 13 ảnh chứng từ báo *"Không đọc được trả lời của máy chủ (mạng hoặc
tường lửa)"* khi bấm Gửi — xác nhận LẶP LẠI NHIỀU LẦN, riêng cơ sở nhiều ảnh mới bị. Đây là dấu
hiệu gói tin gửi lên quá nặng bị cắt/chặn giữa chừng (giới hạn dung lượng một lượt gửi của hosting
hoặc tường lửa), không phải mạng chập chờn ngẫu nhiên.

- **Nén ảnh chặt hơn:** cạnh dài 1280→1000px, chất lượng JPEG 0.6→0.5 — cắt đáng kể dung lượng mỗi
  ảnh, chứng từ (số tiền, mã QR) và ảnh chỉ số máy vẫn đọc được ở cỡ này.
- **Thời gian chờ riêng cho lượt Gửi:** 25s mặc định → 90s — lượt gửi kèm hàng chục ảnh trên 4G
  yếu có thể tải lâu hơn 25s dù ảnh đã nén, trước đây bị cắt ngang coi như lỗi dù vẫn đang tải.

⚠️ **Nếu vẫn còn lỗi sau bản này:** rất có thể là giới hạn CỨNG phía hosting (`post_max_size` của
PHP hoặc `client_max_body_size` của Nginx/tường lửa) — thứ KHÔNG sửa được từ trong mã plugin, phải
nhờ bên hosting nâng lên. Lúc đó cần tách lượt gửi ảnh ra khỏi lượt gửi số liệu chính (đổi kiến
trúc, việc lớn hơn) — báo lại nếu vẫn gặp để làm tiếp bước đó.

### v1.66.1 — Nút chọn ảnh: chữ Việt cố định, không lệ thuộc ngôn ngữ trình duyệt

Anh Thắng 29/08, ảnh PC hiện "Choose File" còn điện thoại hiện "Chọn tệp": *"tại sao trên web lại
khác trên điện thoại"*. KHÔNG phải trang gửi hai bản khác nhau — nút của `<input type="file">` là
chữ do CHÍNH TRÌNH DUYỆT vẽ theo ngôn ngữ hiển thị của trình duyệt đó (PC để tiếng Anh, điện thoại
để tiếng Việt), trang không có cách nào ép chữ đó qua HTML/CSS thường.

**Sửa:** ẩn hẳn nút xấu-xí đó (thu về 1×1px, vẫn bấm được qua `<label for="...">` phủ lên trên) và
tự vẽ MỘT nút chữ Việt cố định "Chọn ảnh" — giống hệt nhau trên mọi máy, mọi trình duyệt, bất kể
ngôn ngữ hệ thống người dùng đang để gì.

### v1.66.0 — Gọn/Đầy đủ đồng bộ theo PIN, không còn kẹt riêng từng máy

Anh Thắng 29/08, ảnh chụp màn PC vẫn hiện đúng 7 cột (thiếu Tiền mặt/Tăng-giảm/Ghi chú):
*"Trên PC sao lại không đồng bộ với web điện thoại, thiếu cột"*.

**Gốc:** lựa chọn Gọn/Đầy đủ (nút 🖥/📱 góc phải) chỉ lưu trong `localStorage` — kho riêng của
TỪNG TRÌNH DUYỆT. Đổi bên điện thoại (hoặc bấm nhầm một lần trên PC từ trước) không hề kéo theo
máy khác, vì không có gì nối `localStorage` của máy này với máy kia. Máy nào chưa từng đổi thì
tự đoán theo bề ngang cửa sổ đang mở (`max-width:860px`) — cửa sổ PC không tối đa hoá, hoặc từng
bị bấm "Gọn" một lần, là kẹt vĩnh viễn ở chế độ ít cột, đúng ca trong ảnh.

**Sửa — PIN là thứ chung duy nhất giữa các máy của một người:**
- Bảng mới `bc_gon` (pin, gon) — trống nghĩa là CHƯA TỪNG đổi (giữ nguyên cách đoán theo bề
  ngang màn hình như cũ, không ép ai).
- `VHG_BaoCao::boot()` trả thêm `gon` (0/1/null) — client nhận được thì DÙNG NGAY, ghi đè cách
  đoán cục bộ, trước khi vẽ màn chính.
- `datGon()` (khi bấm nút 🖥/📱) nay vừa ghi `localStorage` như cũ, vừa gọi `bc_gon_luu` lưu lên
  server theo PIN — đổi ở máy nào, máy khác mở `bc_boot` lần sau tự thấy đúng lựa chọn đó.

**Cần test:** trên điện thoại bấm "🖥 Đầy đủ" một lần, sau đó mở lại trang trên máy tính (đăng
nhập lại bằng cùng PIN, hoặc dùng "Mở màn Báo cáo doanh thu" từ `/ghe`) — máy tính phải TỰ hiện
Đầy đủ (đủ 10 cột: …Tiền mặt, Tăng/Giảm, Ghi chú…), không cần bấm lại nút.

### v1.65.1 — Lịch sử chốt ca của nhân viên

Anh Thắng 29/08: *"Bổ sung lịch sử chốt ca nhân viên"*. Màn "Báo cáo doanh thu" (chế độ Đầy đủ) đã
có "Lịch sử báo cáo trong tháng" (doanh thu từng ghế/ngày) — nhưng KHÔNG có chỗ nào cho nhân viên
tự xem lại mình đã CHỐT CA ra sao từng ngày (đủ hết cơ sở hay chốt sớm, lý do gì, bỏ qua cơ sở
nào). Thêm khối mới **"Lịch sử chốt ca"** ngay dưới, đọc thẳng từ `bc_phien` (bảng đã có sẵn từ
trước — một dòng/ngày/nhân viên, ghi mỗi lần gửi báo cáo hoặc chốt sớm) qua API mới
`bc_lichsu_ca` → `VHG_BaoCao::lich_su_ca()`. Lọc theo đúng PIN đang đăng nhập — mỗi người chỉ thấy
lịch sử của chính mình. Bảng hiện: ngày, trạng thái (Đủ báo cáo/CHỐT SỚM/Đang thu), số cơ sở đã
xong/tổng, tổng tiền, giờ chốt, và chi tiết lý do + cơ sở bỏ qua nếu là chốt sớm.

### v1.65.0 — Sổ doanh thu ghế + ẩn ghế đã dọn · Cơ sở chưa nộp báo cáo + lịch tuần · lọc theo nhân viên

Ba việc riêng, gộp chung một bản vì cùng lúc anh Thắng yêu cầu:

**a) Trang quản trị "Máy & cơ sở" — sổ doanh thu theo ghế + ẩn ghế đã dọn.** Anh Thắng: *"Sổ ra
từng ghế theo điểm gồm các cột (Doanh thu ghế trong tháng, Doanh thu QR, Doanh Thu Tiền mặt), (Tích
chọn ghế đã dọn/điều chuyển nơi khác: Ghế sẽ bị ẩn khỏi trang thu tiền của nhân viên, nhưng vẫn lưu
trong dữ liệu)"*.
- Bảng "Máy (ghế)" thêm 3 cột doanh thu (chọn tháng qua ô "Doanh thu tháng" phía trên, mặc định
  tháng hiện tại), gộp thẳng từ `bc_dong` theo `ma_may` (`VHG_BaoCao::doanh_thu_thang_theo_may()`)
  — không giữ bản số riêng, khỏi lệch với số kế toán đang duyệt.
- Cột mới **"Đã dọn/điều chuyển"**: tích là ẩn NGAY khỏi danh sách ghế cho nhân viên nhập chỉ số
  (`may.an`, cột mới) — ĐÁNH DẤU, KHÔNG XOÁ, giống hệt tinh thần cột `huy` ở bảng `thu`. Bỏ tích là
  dùng lại bình thường. Chỉ lọc ở **đúng một chỗ**: `VHG_BaoCao::ds_ghe()` — trang quản trị/kế toán
  vẫn thấy đủ ghế kể cả đã dọn, chỉ MÀN NHÂN VIÊN mất ghế đó.

**b) Tab "Duyệt báo cáo" — cảnh báo cơ sở chưa nộp hôm nay + lịch nộp theo tuần.** Anh Thắng:
*"Bổ sung Cơ sở chưa nộp báo cáo trong ngày. Với mỗi cơ sở sẽ set lịch nộp báo cáo theo tuần, từ đó
theo lịch cơ sở nào chưa nộp báo cáo."*
- Cột mới `coso.lich_bc` — danh sách số thứ (1=Thứ Hai…7=Chủ Nhật) cơ sở đó PHẢI nộp; mặc định
  `1,2,3,4,5,6,7` (mọi ngày, giữ đúng hành vi ngầm định cũ). Cấu hình qua khối gấp **"⚙ Lịch nộp
  báo cáo theo cơ sở"** ngay trên tab Duyệt báo cáo (có ô lọc tên, vì có tới ~540 cơ sở) — tích/bỏ
  tích ngày nào tự lưu ngay, không cần nút Lưu riêng.
- Khối **"⚠ Cơ sở chưa nộp báo cáo hôm nay"** đặt TRÊN CÙNG tab (việc phải làm ngay hôm nay, khác
  hẳn duyệt/đối chiếu cả tháng bên dưới) — đối chiếu đúng lịch riêng từng cơ sở (`lich_bc`) trước
  khi báo thiếu, và bỏ qua cơ sở đã hết ghế đang dùng (toàn bộ ghế đã "đã dọn") vì không có gì để
  thu. `VHG_KeToan::thieu_bao_cao()`.

**c) Tab "Duyệt báo cáo" — lọc theo nhân viên.** Anh Thắng: *"lọc báo cáo theo nhân viên"*. Thêm ô
chọn cạnh ô lọc cơ sở có sẵn; danh sách nhân viên KHÔNG có sẵn như cơ sở nên dựng lại từ chính
`r.rows` mỗi lần tải tháng (chỉ biết ai đã nộp SAU KHI tải xong). Lọc kết hợp ĐƯỢC với cơ sở (chọn
cả hai cùng lúc thu hẹp đúng giao của hai điều kiện).

### v1.64.0 — Tổng tiền mặt/QR THEO TỪNG CƠ SỞ ở khối Tiến độ

Anh Thắng 29/08, nhìn khối "Tiến độ: 6/9 cơ sở … · Tổng 5.880.000đ": *"Hiện tổng doanh thu tiền
mặt và QR theo cơ sở trên này"*. Trước đây khối này chỉ có MỘT số Tổng gộp cả ngày — muốn biết cơ
sở nào thu tiền mặt bao nhiêu/QR bao nhiêu phải mở từng báo cáo ra xem.

- **Backend (`VHG_BaoCao::phien_tinh()`):** ngoài tổng gộp cả ngày (`tong_tien_mat`/`tong_qr`/
  `tong`) như cũ, nay CỘNG DỒN thêm theo từng `coso_key` (một cơ sở có thể có NHIỀU report_id
  trong ngày do thu nhiều lần — xem v1.63.0/1.63.4 — phải gộp hết các lần của cùng cơ sở mới ra
  đúng tổng cơ sở đó), trả thêm mảng `theo_coso`: `[{ten, tien_mat, qr, tong}, …]`. Đi xuyên suốt
  cả 3 đường trả về phiên (`bc_phien`, `bc_submit`, `bc_chot_som`) vì cả ba đều gọi qua
  `phien_tinh()`, không cần sửa riêng từng chỗ.
- **Client (`veProg()`):** mỗi cơ sở ĐÃ GỬI (chip xanh) nay có thêm `title` (rê chuột xem nhanh
  trên máy tính) VÀ một dòng chi tiết luôn hiện ngay dưới hàng chip (máy chạm không rê được) —
  "Tên cơ sở: Tiền mặt X đ · QR Y đ · Tổng Z đ", mỗi cơ sở một dòng.

### v1.63.7 — "Lệch hàng" ở nút Gửi/Chốt VẪN còn sau 1.63.5 — gốc thật là `align-items:flex-end`

Bản 1.63.5 cho hai nút `flex:1 1 160px` để chia đều bề ngang, tưởng xong, nhưng anh Thắng chụp
màn hình lại báo **vẫn lệch hàng**. Dựng lại y hệt bằng Playwright mới thấy: hai nút RỘNG BẰNG
NHAU thật (đúng như 1.63.5 sửa), nhưng ở màn hẹp (điện thoại), chữ "Gửi báo cáo cơ sở này" dài hơn
nên tự XUỐNG DÒNG bên trong nút (nút cao 2 dòng), còn "Xin chốt ca sớm" vẫn vừa 1 dòng (nút thấp
hơn). `.bc-row` dùng chung `align-items:flex-end` — đúng cho các hàng có Ô NHẬP với NHÃN phía
trên (dán mép dưới cho input ngang hàng với nhãn), nhưng ở hàng CHỈ TOÀN NÚT này, flex-end lại dán
MÉP DƯỚI hai nút bằng nhau → nút thấp (1 dòng) bị đẩy tụt xuống so với nút cao (2 dòng), nhìn lệch
hẳn dù chiều rộng đã bằng nhau — đúng cái anh Thắng thấy, chỉ là do CHIỀU CAO chứ không phải chiều
rộng như 1.63.5 tưởng.

**Sửa:** đặt riêng `align-items:stretch` cho ĐÚNG hàng nút này (không đụng `.bc-row` dùng chung ở
những hàng khác) — cả hai nút cùng cao bằng nút cao nhất, mép trên/dưới thẳng hàng bất kể nút nào
xuống dòng.

⚠️ Bài học: đổi số lượng phần tử trong một hàng flex có `align-items` khác `stretch` (mặc định) mà
không tự kiểm ở MÀN HẸP dễ vỡ layout theo cách không thấy được nếu chỉ test màn rộng (desktop) —
1.63.5 test trên màn rộng nên KHÔNG thấy chữ xuống dòng, tưởng đã hết lệch.

### v1.63.6 — Chế độ Gọn (điện thoại) không thấy Actual khi gõ chỉ số sau

Anh Thắng test chế độ Gọn (BỆNH VIỆN 175), chụp màn hình bảng ghế (Ghế/Chỉ số trước/Chỉ số
sau/QR/📷/🧹 — không có cột Actual) kèm ảnh một cột "ACTUAL" riêng: *"Khi nhập số sau, sẽ hiện
luôn ra số trừ nhé"*. Đúng — `calc()` vốn đã tính lại `actual = (sau−trước)×đơn vị` mỗi lần gõ
(uỷ quyền sự kiện `input`), nhưng ô hiển thị (`cellRo('actual')`) chỉ được thêm vào bảng ở chế độ
Đầy đủ (`if(!GON){...}`), nên chế độ Gọn tính đúng nhưng KHÔNG có ô nào để hiện ra — nhân viên gõ
chỉ số sau xong không thấy gì ngay, phải cuộn xuống xem tổng "Thực thu" cuối bảng. Đã cho cột
Actual hiện ở CẢ HAI chế độ (chỉ ẩn cột "Tiền mặt" riêng ở Gọn, giữ bảng gọn nhẹ) — gõ chỉ số sau
là thấy Actual cập nhật ngay tại đúng hàng ghế đó.

### v1.63.5 — Hàng nút Gửi/Chốt lệch sau khi bỏ nút "➕ Thu lần nữa"

Anh Thắng test bản 1.63.4, chụp màn hình: hai nút "Gửi báo cáo cơ sở này" / "Xin chốt ca sớm" dồn
về bên trái, để lại một khoảng trắng lớn bên phải trong cùng khối — *"lệch hàng"*. Đúng: bỏ nút thứ
ba (➕ Thu lần nữa) ở 1.63.4 mà quên chỉnh lại độ rộng hai nút còn lại — hàng flex vẫn để chúng ở độ
rộng tự nhiên (theo chữ), không co giãn theo hàng. Đã cho cả hai nút (và nút "Đối chiếu máy" ở chế
độ Đầy đủ) `flex:1 1 160px` để chia đều hết bề ngang hàng, không còn khoảng trắng thừa.

### v1.63.4 — TÌM RA GỐC THẬT của "vẫn bắt gõ lại PIN": lỗi ĐỊNH TUYẾN, không phải PIN/phiên

Sau ba bản vá liên tiếp (1.63.1 lưu PIN vào phiên, 1.63.2 thêm migration tay + `viSao`) mà anh
Thắng vẫn báo **vẫn bắt đăng nhập**, bản 1.63.2's `viSao` cuối cùng lộ ra câu lỗi thật trên màn:

> *"Tự động vào thất bại: Việc báo cáo không rõ: bc_boot_tu_token"*

**Gốc thật — bug định tuyến (dispatch) trong `class-vhg-trang.php::api()`, không liên quan gì tới
PIN hay phiên:** ngay đầu hàm `api()` có một cổng chặn SỚM cho mọi việc PIN-riêng:

```php
if ( 0 === strpos( $viec, 'bc_' ) && 0 !== strpos( $viec, 'bc_pin_' ) ) { … trả lỗi "Việc báo
cáo không rõ" cho mọi $viec lạ rồi return NGAY … }
```

`bc_boot_tu_token` (thêm từ v1.53.0, dùng token `/ghe` để suy PIN — KHÔNG dùng PIN-riêng) có tên
bắt đầu bằng `bc_` và không bắt đầu bằng `bc_pin_`, nên **luôn luôn** rơi vào cổng này, luôn luôn
rớt xuống "Việc báo cáo không rõ: bc_boot_tu_token", và **không bao giờ chạm được** tới cài đặt
thật của nó nằm SAU cổng token (dòng ~267, dùng `$ai` từ `user_by_token()` + `pin_phien_tu_token()`
— chính là chỗ 1.63.1/1.63.2 sửa). Nói cách khác: **tính năng "Mở màn Báo cáo doanh thu" đã là mã
CHẾT — không thể chạy được — kể từ ngày ra đời ở v1.53.0.** Hai bản vá 1.63.1 và 1.63.2 đều ĐÚNG về
mặt logic (PIN giờ đã nằm sẵn trong phiên, migration đã chắc chắn chạy, `viSao` đã sẵn sàng chẩn
đoán) nhưng không có tác dụng gì vì đường gọi bị chặn từ bước định tuyến, trước khi tới được đoạn
code dùng PIN đó.

**Sửa:** khai `bc_boot_tu_token` là ngoại lệ thứ hai của cổng PIN-riêng, y hệt `bc_pin_*`:

```php
if ( 0 === strpos( $viec, 'bc_' ) && 0 !== strpos( $viec, 'bc_pin_' ) && 'bc_boot_tu_token' !== $viec ) {
```

⚠️ Đây là bài học chung cho MỌI việc `bc_*` mới thêm sau này mà không đi qua PIN-riêng: phải khai
thêm vào đúng dòng `if` này ở đầu `api()`, không thì mọi sửa ở đoạn xử lý thật phía sau đều vô tác
dụng — lặp lại y hệt lỗi này. Xem khối 🔴 chú thích ngay tại dòng `if` đó.

**Cần test:** bấm "Mở màn Báo cáo doanh thu" khi đã đăng nhập `/ghe` — phải vào THẲNG bảng số liệu,
không hỏi PIN, không còn banner vàng "Tự động vào thất bại".

### v1.63.4 — Bỏ nút "➕ Thu lần nữa": LUÔN tự hiểu là thu thêm một lần mới

Anh Thắng 29/08: *"không nên bấm + thu lần nữa, mà sẽ tự hiểu và chèn vào giữa, nghĩa là chọn ngày
đó thì doanh thu ngày đó thôi"* → xác nhận qua câu hỏi làm rõ: **LUÔN tự hiểu là thêm một lần thu
mới (nối tiếp), không bao giờ đè lên lần cũ** — bỏ hẳn khái niệm bật/tắt tay.

- **`class-vhg-baocao.php::luu()`:** bỏ hẳn nhánh `$lan_moi` true/false — giờ MỌI lượt Gửi đều tạo
  `report_id` mới + `lan = MAX(lan ngày đó)+1`, chỉ số trước LUÔN tính nối tiếp lần gần nhất
  (`chi_so_truoc(..., true)`, không còn `false`). Đã bỏ hẳn khối "sửa đè lần cũ" (`header_()` tìm
  lần mới nhất để UPDATE) — gửi lại cho cùng cơ sở/ngày giờ không còn cách nào đè lên báo cáo cũ từ
  màn nhập chính này nữa. Muốn SỬA một lần đã gửi (gõ nhầm số) thì đi qua màn Sửa/Lịch sử 24h
  (`bc_edit`) — khác việc, không lẫn vào cùng một nút Gửi.
- **Client `js_baocao()`:** bỏ hẳn nút `bc-lan` ("➕ Thu lần nữa"), banner xanh "Đang thu LẦN NỮA…",
  và biến `LAN_MOI`. `selectLoc()` giờ luôn gọi `bc_lastmeters` với `toi:1` (nối tiếp) và luôn đọc
  lại nháp — không còn nhánh theo `LAN_MOI`. `bc_submit` không còn gửi cờ `lan_moi` (server không
  đọc cờ này nữa).
- **Cần test:** chọn một cơ sở/ngày ĐÃ có báo cáo, nhập số liệu mới rồi Gửi — không cần bấm nút gì
  thêm, hệ thống phải tự tạo LẦN 2 nối tiếp lần 1 (chỉ số trước = chỉ số sau của lần 1), thông báo
  "Đã gửi báo cáo … (lần 2)".

### v1.63.4 — Ô ảnh báo cáo: cho chọn từ thư viện, không chỉ chụp camera

Anh Thắng 29/08: *"Bổ sung thêm upload ảnh báo cáo, được chọn thêm ảnh từ thư viện thay vì việc chỉ
cho nhân viên chụp bằng camera thôi"*. `celAnh()` (ô ảnh 📷 Chỉ số / 🧹 Vệ sinh mỗi ghế) trước có
`capture='environment'` — thuộc tính này ép trình duyệt điện thoại mở THẲNG app camera, bỏ qua màn
chọn "Chụp ảnh / Chọn từ thư viện / Duyệt tệp" mặc định của hệ điều hành. Bỏ thuộc tính này là đủ —
`accept='image/*'` vẫn giữ, chỉ giới hạn loại tệp là ảnh.

### v1.63.3 — "➕ Thu lần nữa" bấm xong không thấy gì đổi (đúng ca cần test của 1.63.0)

Anh Thắng test đúng kịch bản BAN_GIAO đã nêu ở 1.63.0 ("bấm ➕ Thu lần nữa, chỉ số trước phải nối
tiếp") và báo: *"vẫn ghi nhận chỉ số cũ"* — bấm nút xong, chỉ số trước vẫn hiện số GỐC (330) chứ
không nối tiếp lần 1 (340).

**Gốc — LỖI HIỂN THỊ, không phải lỗi số liệu:** nút "➕ Thu lần nữa" và dòng banner xanh "Đang thu
LẦN NỮA…" chỉ được DỰNG MỘT LẦN lúc vẽ trang (`veChinh()`), dựa theo `LAN_MOI` **tại đúng lúc đó**
— luôn là `false` vì `veChinh()` chạy khi mở màn. `onclick` của nút chỉ đổi biến `LAN_MOI` trong bộ
nhớ JS rồi gọi lại `selectLoc()` để tải chỉ số mới, nhưng KHÔNG tự vẽ lại chữ trên nút hay banner —
nút vẫn ghi "➕ Thu lần nữa" y hệt trước khi bấm, banner xanh không hiện. Nhân viên bấm xong không
thấy GÌ xác nhận là đã bật chế độ, nên **không biết bấm có ăn hay chưa** — đúng câu "vẫn ghi nhận
chỉ số cũ" (không hẳn nhầm số, mà không có gì báo là đã đổi chế độ).

⚠️ Bản 1.63.3 CHỈ sửa phần HIỂN THỊ (nút tự đổi chữ + banner tự hiện/ẩn ngay trong `onclick`, không
đợi vẽ lại cả trang) — chưa đụng gì tới `chi_so_truoc_ct_()` (đọc lại kỹ thấy logic SQL đúng: lọc
`ngay <= $ngay`, sắp `ngay DESC, lan DESC` thì phải ra đúng lần thu gần nhất trong ngày). Đợi anh
Thắng test lại bản này để biết chắc: nếu bấm nút thấy banner xanh hiện ra VÀ chỉ số trước đổi đúng
sang 340 → xong hẳn cả 1.63.0 lẫn 1.63.3. Nếu banner hiện ra mà chỉ số trước VẪN sai → lỗi số liệu
thật nằm ở `chi_so_truoc_ct_()`/`bc_lastmeters`, cần đọc lại từ đầu với dữ liệu thật (mã ghế, ngày,
report_id của cả hai lần) chứ không suy đoán tiếp từ xa được nữa.

### v1.63.1 — "Mở màn Báo cáo doanh thu" vẫn bắt gõ lại PIN (Võ Nguyễn Hồng Nhung, 29/08)

**Hiện tượng:** nhân viên đã đăng nhập token `/ghe` xong, bấm tab "📋 Báo cáo doanh thu" (nút mở
thẳng khỏi gõ PIN, thêm ở v1.53.0), vẫn rơi về cổng "Nhập mã PIN nhân viên thu tiền".

**Gốc:** `VHG_BaoCao::boot_tu_ai()` (đã có từ v1.53.0, sửa một lần hôm 28/08 cho ca Vũ Nguyễn Hồng
Nhung) suy PIN bằng cách khớp **(tên, cơ sở)** trong `VHG_Auth::users()` — sổ nhân sự SỐNG — với
`coso` ghi trong phiên đăng nhập lúc trước, tức một ẢNH CHỤP cũ. Hồ sơ đổi cơ sở (hoặc gộp thêm cơ
sở phụ) SAU lúc đăng nhập là lệch khớp ngay; khớp lùi về tên suông thì lại trượt nếu trùng tên với
ai khác trong 400+ nhân sự. Cả hai kiểu trượt đều IM LẶNG — màn hình trông như tính năng chưa hề
chạy.

**Sửa tận gốc, không vá thêm điều kiện khớp:** bảng phiên (`vhg_phien`) nay có thêm cột `pin` —
`VHG_Auth::login()` ghi luôn PIN vừa xác thực đúng vào phiên. `boot_tu_ai()` dùng PIN đó trực tiếp
qua hàm mới `VHG_Auth::pin_phien_tu_token()`, hết mọi kiểu khớp tên/cơ sở. Đường dò cũ (tên+cơ sở)
**vẫn giữ lại** làm cầu nối cho phiên phát TRƯỚC bản 1.63.1 (chưa có `phien.pin`) — mất dần khi
phiên đó hết hạn (30 ngày) hoặc người dùng đăng xuất/đăng nhập lại.

⚠️ **PIN KHÔNG được gộp vào `$ai`/`user_by_token()`** dù tiện hơn — `$ai` bị nhúng thẳng vào JSON
`so_lieu()` gửi cho MỌI người, MỌI lượt tải trang; gộp PIN vào đó là in PIN ra network tab của tất
cả mọi phiên. Lấy PIN bằng hàm riêng (`pin_phien_tu_token()`), gọi tay đúng MỘT chỗ (dispatch
`bc_boot_tu_token`) — xem khối 🔴 trong `class-vhg-auth.php` và `class-vhg-baocao.php` trước khi
đụng lại chỗ này.

**Cần test:** một tài khoản đã ĐĂNG NHẬP TRƯỚC khi cài 1.63.1 phải **đăng xuất/đăng nhập lại MỘT
LẦN** để phiên mới mang theo `pin` — nếu chưa, `boot_tu_ai()` vẫn chạy đường dò cũ (vẫn có thể
đúng, nhưng không phải đường chính đã sửa). Sau khi đăng nhập lại, bấm "Mở màn Báo cáo doanh thu"
phải vào thẳng, không hỏi PIN — thử với đúng tài khoản Võ Nguyễn Hồng Nhung trước.

### v1.63.2 — 1.63.1 CHƯA HẾT: thêm chốt migration tay + báo lỗi rõ nguyên nhân

Anh Thắng test lại sau khi cài 1.63.1 (đã xác nhận đúng bản, đã đăng xuất/đăng nhập lại đàng
hoàng): **vẫn bắt đăng nhập**. Vậy lỗi không phải "phiên cũ chưa mang PIN" như 1.63.1 giả định.

**Nghi vấn hàng đầu:** bảng `phien` là bảng ĐANG SỐNG (có người đang mở web ngay lúc cài), và
`boot_tu_ai()` không cách nào tự phân biệt "cột `pin` rỗng vì phiên phát trước 1.63.1" với "cột
`pin` KHÔNG TỒN TẠI vì dbDelta chưa kịp/không thêm được trên bảng đó" — cả hai đọc ra y hệt nhau
(chuỗi rỗng). Trong khi bảng `bc` (thêm cột `lan` ở 1.63.0) là bảng có ÍT thao tác ghi đồng thời
hơn `phien` (mỗi request có token đều SELECT/DELETE bảng này).

**Đã thêm hai lớp, KHÔNG PHẢI ĐOÁN THÊM:**
1. `VHG_DB::migrate_()`: thêm tay `ALTER TABLE ... ADD COLUMN pin ...` có chốt
   `SHOW COLUMNS ... LIKE 'pin'` (idempotent), TÁCH KHỎI vòng lặp `dbDelta` chung — không còn dựa
   vào dbDelta tự thêm cột trên một bảng đông người dùng thật nữa.
2. `boot_tu_ai()` trả thêm trường **`viSao`** khi thất bại (bước nào trượt: có/thiếu `pin_phien`,
   tên không khớp ai, trùng tên bao nhiêu người, cơ sở hồ sơ của những người trùng tên là gì…).
   Client (`moBaoCao()`) hiện thẳng `viSao` lên MÀN HÌNH (ô vàng, ngay dưới tiêu đề "Báo cáo doanh
   thu") thay vì im lặng rớt về cổng PIN như trước — **không cần mở DevTools**, chỉ cần chụp màn
   hình là đọc được lý do.

**Nếu 1.63.2 vẫn không vào thẳng được:** đọc đúng dòng `viSao` hiện trên màn (không phải đoán lại
từ đầu) rồi mới sửa tiếp — dòng đó nói chính xác bước nào trượt.

### v1.63.0 — Thu NHIỀU LẦN trong ngày (mỗi lần 1 bản ghi, chỉ số nối tiếp)
Anh Thắng 29/08: *"thay vì 1 ngày 1 lần, cho thu nhiều lần; lần sau chỉ số tự đẩy chỉ số cũ vào"*.
- **CSDL:** thêm cột `lan SMALLINT` vào `bc` và `bc_dong`; đổi UNIQUE của `bc` từ
  `(coso_key,ngay)` → `(coso_key,ngay,lan)`. dbDelta KHÔNG tự bỏ khoá cũ nên có `VHG_DB::migrate_()`
  (chạy trong `install()`, idempotent) `ALTER TABLE bc DROP INDEX coso_ngay` nếu còn.
- **Nối chỉ số theo `(ngày, lần)`:** `chi_so_truoc_ct_()` thêm tham số `$toi`; `$toi=true` lấy CẢ
  chỉ số sau của các lần thu TRONG chính ngày đó (sắp `ngày DESC, lan DESC`) để lần sau nối tiếp
  lần trước. `chi_so_truoc()/lay_chiso_truoc()` thêm `$toi` truyền xuống.
- **`luu()`:** đọc cờ `lan_moi`. Có cờ → tạo LẦN THU MỚI: `report_id` mới, `lan = MAX(lan ngày đó)+1`,
  chỉ số trước tính với `toi=true` (nối lần trước); không cờ → sửa lần mới nhất trong ngày như cũ
  (`header_()` nay `ORDER BY lan DESC`). Cột `lan` ghi vào cả `bc` lẫn `bc_dong`.
- **Client `js_baocao()`:** nút **➕ Thu lần nữa** (`bc-lan`): bật `LAN_MOI`, tải lại chỉ số trước
  với `toi=1` (nối tiếp), không nạp nháp cũ; khi Gửi kèm `lan_moi:1`; gửi xong tự tắt. `bc_lastmeters`
  nhận thêm `toi`.
- **Kế toán KHÔNG phải sửa gì:** `ds()` và `doanhthu_ky()` vốn GOM + CỘNG theo `coso_key|ngày`,
  "Duyệt cả báo cáo"/"Khoá ngày" theo `coso+ngày` (bao mọi lần), checkbox duyệt lẻ theo
  `(report_id,ma_may)` (mỗi lần một report_id) → nhiều lần/ngày tự cộng đúng.
- ⚠️ **Còn để ý:** ngày đã KHOÁ vẫn chặn thu thêm (đúng ý — nhờ kế toán mở). Nối chỉ số GIỮA các
  lần CÙNG NGÀY khi SỬA lần cũ chưa tự lan sang lần sau cùng ngày (hiếm; nối lúc tạo là đủ). Chi
  tiết một ngày hiện mỗi lần một dòng cho cùng ghế (đúng, mỗi lần thu một dòng).

### v1.62.0 — Ô ảnh ở mọi chế độ + nối dòng thời gian chỉ số
- **Ô nhập ảnh (📷 Chỉ số / 🧹 Vệ sinh) LUÔN hiện**, cả chế độ Gọn (điện thoại) lẫn Đầy đủ. Trước
  đây ảnh chỉ ở chế độ Đầy đủ → điện thoại để Gọn là mất đường đính ảnh; PC/điện thoại khác chế độ
  thấy khác nhau. (`js_baocao()` trong `class-vhg-trang.php`: header Gọn thêm 2 cột, `celAnh` chuyển
  ra ngoài khối `if(!GON)` xuống cuối hàng ở cả hai chế độ.)
- **Nối dòng thời gian chỉ số** (anh Thắng 29/08: *"nhập vào ngày nằm giữa 2 ngày thì chỉ số tự chèn
  vào giữa… chỉ số cũ ngày hôm sau tự nhảy chỉnh lại"*). Sau khi lưu/sửa/bỏ/đổi-ngày một ghế ở ngày
  D, **lần đọc kế tiếp** (ngày > D, có chỉ số sau) tự lấy chỉ số sau vừa chốt của D làm chỉ số trước,
  tính lại actual/tiền/tổng. Chỉ đụng đúng một hàng kế tiếp (mốc = chỉ số sau gần nhất trước ngày đó).
  - Helper mới `VHG_BaoCao::noi_tiep($ma,$ngay)` (hàng kế tiếp), `noi_hang($ma,$ngay)` (chính hàng
    tại ngày — dùng khi đổi ngày), và private `ap_moc_()` (chính sách áp mốc). Gọi từ: `VHG_BaoCao::luu()`
    (ghế vừa gửi + ghế vừa bỏ), `VHG_BaoCao::sua_dong()` (nhân viên sửa 24h), `VHG_KeToan::sua()` và
    `VHG_KeToan::doi_ngay()` (đổi ngày → nối cả ngày mới, ngày cũ, và chính báo cáo được chuyển).
  - 🔴 AN TOÀN TIỀN: hàng kế tiếp là "Thực thu ghi đè" (đã chốt tay) hoặc nối xong hoá bất thường
    (sau < trước mới / tiền mặt ra âm) → CHỈ đổi chỉ số trước, GIỮ tiền cũ, ghim ghi chú
    "↺ Chỉ số trước tự nối lại…" để kế toán kiểm; không tự ghi tiền rác.
  - Chưa nối ở `xoa()`/`undo()` (xoá/hoàn tác báo cáo) — xem mục "Cần kiểm/làm tiếp".

### v1.50.0 — Nhóm phân quyền "Quản trị" khai được
- Trước đây quyền quản trị nhét cứng `Admin + Quản lý` (hằng `VHG_Auth::QUAN_TRI`). Nay thêm
  nhóm thứ tư trong bảng **Cấu hình → Phân quyền** để tick vai trò nào được quyền vận hành.
- `VHG_Auth::vai_tro_quan_tri()` đọc option `vhg_vai_tro_quantri`; **chưa khai bao giờ = Admin +
  Quản lý** (giữ nguyên hành vi cũ). Admin luôn bị ép có mặt. `la_quan_tri()` đọc theo danh sách này.
- `bc_pin_*` (PIN báo cáo) nới từ **chỉ-Admin** sang **quyền Quản trị**.
- **Vẫn chỉ Admin:** khai/xoá nhân sự và sửa chính bảng phân quyền (gác ở lớp `ch_` trong
  `class-vhg-trang.php`) — người được cấp quyền quản trị không tự nâng quyền cho mình được.
- File đụng: `class-vhg-auth.php` (thêm `vai_tro_quan_tri`, sửa `la_quan_tri`),
  `class-vhg-trang.php` (payload `ch_xem` thêm `quantri`; `ch_vai_tro` lưu `vhg_vai_tro_quantri`;
  gác `bc_pin_*` theo `la_quan_tri`; tab "PIN báo cáo" + bảng phân quyền + nút Lưu thêm nhóm `quantri`).

### v1.51.0 — Biểu đồ dashboard doanh thu
- Vẽ **thuần SVG + CSS, không thư viện ngoài** (nhẹ, hợp theme, đổi màu theo biến `--blue/--green/--amber`).
- Dashboard **Đối soát** thêm khối 4 ô: donut cơ cấu Tiền mặt/QR, theo **khu vực (tỉnh)**, top **cơ sở**, top **ghế** — theo kỳ đang chọn.
- Tab **Doanh thu địa điểm** thêm biểu đồ **theo tháng**.
- Helper (trong `class-vhg-trang.php`, gần `kpi()/bang()`): `tienGon()`, `bdDonut()`, `veBieuDo()`.
  Số liệu lấy từ `t = D.tong` và bản đồ cơ sở→tỉnh trong `D.coso` — không gọi thêm cổng nào.

### v1.52.0 — Thanh xếp chồng TM/QR + biểu đồ theo ngày
- Thanh ngang giờ **xếp chồng Tiền mặt (xanh lá) + QR (xanh dương)** trong một thanh, có chú thích
  màu — áp cho khu vực, cơ sở, ghế, và biểu đồ theo tháng. Helper `bdCotStack()`.
- Bung một tháng ở **Doanh thu địa điểm** hiện thêm biểu đồ **doanh thu theo ngày** (giữ thứ tự ngày).
- Đã bỏ `bdCot()` một màu (không còn dùng).

### v1.52.1 – v1.53.3 — Vá theo phản hồi thực tế trên bảng Duyệt báo cáo
- Đơn chưa duyệt hết lên đầu danh sách; bung sẵn chi tiết; ảnh chỉ số cũ (link Google Drive dạng
  "view") hiện được thumbnail (`ktAnhSrc()`).
- `goi()` (hàm gọi API dùng chung) thêm **timeout 25s** — trước đó "Đang lưu…" có thể treo vô hạn
  trên mạng yếu.
- Tab mới **"📋 Báo cáo doanh thu"** ở sidebar chính, mở thẳng app thu-tiền PIN mà **khỏi đăng nhập
  lại** (token `/ghe` suy ra PIN nhân sự — `VHG_BaoCao::boot_tu_ai()`).
- **Lọc ghế lỗi theo cơ sở** cho vai trò bị giới hạn phạm vi (Quản lý/CHT); sau đó phải **loại trừ
  Kế toán** khỏi luật này (`VAI_XEM_HET`) vì tài khoản kế toán cần xem hết mọi cơ sở.
- Bảng Duyệt báo cáo + Nhật ký hoàn tác: **phân trang 10 dòng/trang** (trước tải hết một lần gây lag
  trên điện thoại).
- Ô lọc cơ sở trống với Kế toán: `so_lieu_khong_quan_tri()` trước đó luôn gửi `coso=[]` cho MỌI vai
  trò không quản trị — sửa để gửi danh sách thật khi là Kế toán.

### v1.54.0 – v1.55.2 — Nhớ nháp, tách doanh thu chưa/đã duyệt, dọn giao diện
- **Lưu nháp localStorage** khi nhân viên đang nhập báo cáo (khoá theo cơ sở+ngày) — F5/thoát app
  giữa chừng không mất số đã gõ; tự xoá khi gửi thành công. (`bcLuuNhap/bcDocNhap/bcXoaNhap`.)
- Tách rõ **doanh thu chưa duyệt / đã duyệt**; nút "Duyệt báo cáo" **chìm màu** khi đơn đã duyệt hết
  (tránh bấm lại nhầm); **duyệt riêng từng máy lẻ** thay vì phải huỷ cả lô khi chỉ một máy lỗi.
- **Phóng ảnh khi rê chuột** (`.kt-anh-zoom`) cho kế toán soát ảnh chứng từ dễ hơn.
- Cuộn lên đầu trang khi đổi trang (trước bấm "trang sau" bị nhảy xuống cuối); **tắt tự F5 cả trang**
  ở tab Duyệt báo cáo (`henLai()` chặn `TAB==='kt-duyet'`) — tránh mất thao tác dở khi tab tự làm mới.
- Chân trang (`VHG_Chan`) bị sidebar 216px che ở màn vừa (901–1400px) → thêm `body .vhg-chan{margin-left:216px}`
  ngay trong CSS riêng của `class-vhg-trang.php` (không sửa CSS dùng chung `VHG_Chan::css()`, vì lớp
  đó còn phục vụ trang khách `/mua-ma` không có sidebar).

### v1.56.0 – v1.57.0 — Bảng chéo Ngày×Ghế + ảnh theo từng ghế
- Tab **Doanh thu địa điểm** thêm **bảng chéo Ngày × Ghế cả năm** (không ngắt theo tháng) — mỗi ghế
  một cặp cột (chỉ số máy · Actual). API `kt_bangcheo` (`VHG_KeToan::bang_cheo()`).
- Nhân viên thu tiền chụp **2 ảnh/ghế** (chỉ số + vệ sinh) thay vì một xấp ảnh chung chia đều theo
  thứ tự (dễ gán nhầm ghế với 20+ máy). Ảnh gắn thẳng theo mã ghế (`images.chiso/vesinh`), lưu có
  nhãn ở server (`chiso.jpg`/`vesinh.jpg` theo mã).

### v1.58.0 – v1.61.0 — "Chỉ số bất thường": 4 việc anh Thắng chốt làm lần lượt

Bốn việc này đi cùng nhau, làm tuần tự theo yêu cầu *"Làm lần lượt từng cái một, báo kết quả xong
mới qua cái kế"* — cả 4 đã xong tính tới bản 1.61.0, nhưng **việc 3 và việc 4 chưa qua tay anh Thắng
kiểm** (xem mục "Cần kiểm tiếp" bên dưới).

1. **v1.58.0 (việc 1/4) — Lý do thay vì chặn cứng.** Trước đây `chi_so_sau < chi_so_truoc` bị CHẶN
   CỨNG, không gửi được. Nay cho nhập **lý do**, kèm gửi. `v1.59.1` mở rộng điều kiện "bất thường"
   sang cả **tiền mặt tính ra ÂM** (QR nhập > Actual — chỉ số vẫn đúng chiều nhưng công thức
   `tien_mat = actual − QR + điều_chỉnh` vẫn cho số âm).
2. **v1.59.0 (việc 2/4) — "Thực thu" ghi đè.** Khi bất thường, thêm ô **Thực thu** (bắt buộc) thay
   thẳng số tiền mặt phải nộp — không dùng công thức (rác) nữa. `v1.59.2`–`v1.59.3` mở rộng sang
   form **"Sửa"** riêng của kế toán (`VHG_KeToan::sua()`), vốn có một chốt chặn cứng khác, tách biệt
   với chốt ở `VHG_BaoCao::luu()` — dễ tưởng đã sửa hết nhưng thật ra là hai chỗ chặn khác nhau.
   `v1.59.4`: sắp danh sách máy theo **thứ tự tự nhiên** (`strnatcmp`, 1,2,…,10,11,12) thay vì thứ tự
   chuỗi (10 nhảy lên trước 2) sau mỗi lần Lưu.
3. **v1.60.0 (việc 3/4) — Đối chiếu lượt kích ghế từ xa.** Khi nhập chỉ số sau, hệ tự đếm số lượt
   Hotline/Admin **bấm Bật tay** (bảng `lenh`) trong đúng quãng chỉ số báo cáo bao phủ, quy đổi ra
   tiền theo **giá/phút RIÊNG của từng ghế** (`VHG_May::ty_le_cua()`) rồi **trừ thẳng khỏi `actual`**
   trước khi tính tiền mặt. Có kích thì "báo" bằng cách ghép vào ghi chú (`🔧 Đã trừ N lượt…`), không
   im lặng sửa số. File mới/đụng: `VHG_May::dem_luot_kich()`, `VHG_BaoCao::chi_so_truoc_ct_()` (tách
   từ `chi_so_truoc()`, trả thêm NGÀY mốc) + `kich_xa_tru()`; API `bc_kichxa` cho client xem trước;
   client `calc()`/`guiBaoCao()` trong `js_baocao()` dùng cùng số để khớp đúng server.
4. **v1.61.0 (việc 4/4) — Tab Hotline "📞 Hỗ trợ khách".** Sổ tay ngày cho Hotline (và Quản
   lý/Admin) ghi **số lượt kích thêm** + **số tiền hoàn khách** — bảng mới `hotline_bc` (1 dòng/cơ
   sở/ngày, gửi lại là ghi đè), lớp mới `VHG_Hotline` (`luu()/ds()`), API `hl_luu`/`hl_ds`/`hl_ke`
   (gate theo quyền **GIÚP KHÁCH**, không phải quyền quản trị — cùng khuôn `kt_*`). Đây là **sổ tay
   thủ công**, khác hẳn nhật ký `lenh` tự động: hệ không có luồng nào tự bắt được số tiền hoàn
   khách, nên ô đó luôn phải gõ tay; số "tự đếm được" từ `lenh` chỉ hiện để đối chiếu tham khảo.

---

## 2. VẤN ĐỀ ĐÃ ĐÓNG — "Cơ sở phụ" không đẩy sang bên ghế

**✅ ĐÃ SỬA** — plugin **K&H Chấm công (`vhcp-cham-cong`)**, bản **3.1.1**, commit *"Chấm công
3.1.1: đẩy sang ghế gộp luôn Cơ sở phụ"*, trên nhánh **`claude/rebuild-chi-phi-wordpress-hl2yze`**
(lịch sử git **không liên quan** tới nhánh `claude/posh-qr-kh1urz` của plugin ghế — hai plugin, hai
nhánh riêng trong cùng một repo GitHub). Sửa đúng theo mục "Cách sửa" đã ghi bên dưới: hàm
`VHCC_Day_Ghe::ho_so_day()` giờ ghép `Cửa hàng` + `Cơ sở phụ` vào một ô "Cơ sở" nối bởi `; ` trước
khi đẩy sang bảng dùng chung `vhcp_cfg`. **Bên ghế không cần sửa gì** — mục dưới đây giữ lại nguyên
văn để giải thích vì sao (bên ghế đã hỗ trợ nhiều cơ sở qua tách chuỗi `;`/`,`).

**Hiện tượng gốc (anh Thắng 28/08/2026):** *"Nhân viên bên cơ sở phụ lại không đẩy thông tin sang, nó
chỉ đang lấy thông tin chính."* Trong hệ nhân sự (K&H Chấm công) mỗi người có **Cửa hàng** (cơ sở
chính) và một cột **Cơ sở phụ**; bên ghế chỉ thấy cơ sở chính.

### Nguyên nhân gốc (đã truy xong)
- Plugin ghế đọc nhân sự từ bảng MySQL dùng chung `{prefix}vhcp_cfg`, hàng `CH_NguoiDung`
  (xem `VHG_Auth::users()` nhánh `chung`). Cấu trúc cột JSON:
  ```
  [ Tên, PIN, Vai trò, Cơ sở, TK Có, Mã đối tượng, Bộ phận ]
  ```
  → **chỉ có MỘT ô "Cơ sở"** (chỉ số `cols[3]`). Không có ô "Cơ sở phụ".
- Hệ nhân sự khi đẩy sang chỉ ghi **Cửa hàng (chính)** vào `cols[3]`, **bỏ qua Cơ sở phụ**.

### Bên ghế KHÔNG cần sửa — đã hỗ trợ nhiều cơ sở sẵn
Trường "Cơ sở" đã được tách theo dấu `;` / `,` ở cả hai chỗ:
- Phạm vi báo cáo: `VHG_BaoCao::pham_vi_()` → `tach_()` (một người có nhiều cơ sở là thấy hết).
- Ô chọn nhân viên ở tab PIN báo cáo: `bcpTickCoso()` trong `class-vhg-trang.php` tự tách và tích
  **tất cả** ô cơ sở khớp.

→ Nếu hệ nhân sự gửi `"Cửa hàng; Cơ sở phụ"` trong đúng một ô "Cơ sở", bên ghế **tự nhận cả hai**.
  (Bằng chứng: người `"GO BÀ RỊA; KUBO GO BÀ RỊA"` đã hiển thị đủ hai cơ sở.)

### Cách sửa (một dòng, ở PHÍA HỆ NHÂN SỰ)
Chỗ hệ nhân sự tạo bản ghi đẩy sang ghế, ghép hai cơ sở vào một ô:
```js
coSo = [cuaHang, coSoPhu].filter(Boolean).join('; ');
```

### ⚠️ Cảnh báo taxonomy (quan trọng)
Mã cơ sở bên nhân sự (`POSH_HCM`, `TUTU_TP`, `VP_KH-HCM`…) **khác** tên chi nhánh bên ghế
(`GO BÀ RỊA`, `AEON MALL BÌNH DƯƠNG`…). Ô tích bên ghế **chỉ tích khi tên khớp** một chi nhánh ghế
thật (so khớp qua `VHG_BaoCao::squash()` — bỏ dấu, bỏ ký tự lạ, in hoa). Vậy giá trị đẩy sang phải
là **tên chi nhánh ghế**, không phải mã tổ chức HR — nếu không, dù đẩy cơ sở phụ cũng không khớp ô nào.

### Nguồn hệ nhân sự — đã tìm ra, KHÁC repo/nhánh với plugin ghế
Bản deploy **K&H Chấm công** hoá ra nằm CÙNG repo GitHub `khh-chamcong-firmware`, ở thư mục
`wordpress/vhcp-cham-cong/`, nhưng trên nhánh **`claude/rebuild-chi-phi-wordpress-hl2yze`** — một
lịch sử git **hoàn toàn không liên quan** tới nhánh `claude/posh-qr-kh1urz` của plugin ghế (đã xác
nhận bằng `git merge-base` trả về không tìm thấy tổ tiên chung). Hai plugin, hai nhánh, một repo.
Đã sửa đúng theo mục "Cách sửa" ở trên trong hàm `VHCC_Day_Ghe::ho_so_day()` (bản 3.1.1) — xem khối
✅ đầu mục 2.

---

## 3. CẦN KIỂM TIẾP — việc 3/4 và 4/4 chưa qua tay anh Thắng

Toàn bộ 4 việc "chỉ số bất thường" (mục 1 ở trên) đã code/lint xong và đã gửi bản cài (1.60.0,
1.61.0), nhưng tính tới lúc cập nhật tài liệu này **anh Thắng mới xác nhận test việc 1 và 2** (từ
1.58.0 tới 1.59.4). Người tiếp nhận nên hỏi/xem phản hồi trước khi coi đây là xong hẳn:

- **Việc 3 (trừ lượt kích khỏi doanh thu, v1.60.0):** cần một ca thật có Hotline bấm Bật rồi nhân
  viên nộp báo cáo đúng ngày đó, xem ghi chú `🔧 Đã trừ N lượt…` có ra đúng số và đúng tiền không
  (so với `VHG_May::ty_le_cua()` của đúng ghế đó).
- **Việc 4 (tab Hotline, v1.61.0):** cần Hotline thật vào tab **"📞 Hỗ trợ khách"**, thử Lưu, đổi
  ngày/cơ sở, gửi lại trong cùng ngày xem có ghi đè đúng không, và xem số "tự đếm được" có khớp với
  số lượt họ vừa bấm Bật ở tab Điều khiển hay không.

Nếu anh Thắng báo lỗi ở hai việc này, đọc kỹ khối 🔴 trong `kich_xa_tru()` (class-vhg-baocao.php)
và đầu `class-vhg-hotline.php` trước khi sửa — cả hai đều có đúc kết lý do thiết kế, đừng đổi hướng
mà không đọc.

### Việc mới của phiên 29/08 (v1.62.0–1.63.0) — cần anh Thắng test thực tế
- **v1.63.0 THU NHIỀU LẦN/NGÀY — quan trọng nhất, phải test kỹ vì ĐỔI CSDL.** Migration tự chạy khi
  nâng cấp (`VHG_DB::migrate_()` bỏ UNIQUE cũ `coso_ngay`). Kịch bản test: một cơ sở gửi lần 1 →
  bấm **➕ Thu lần nữa** (chỉ số trước phải nối tiếp chỉ số sau lần 1) → gửi lần 2 → mở **Duyệt báo
  cáo** xem có ĐỦ 2 lần và **tổng cộng đúng** không. Nếu bảng Duyệt gộp/đếm ghế sai khi một ghế thu
  2 lần, xem `VHG_KeToan::ds()` (gom theo `coso_key|ngày`) và `chi_tiet()`.
  ⚠️ **Test lần 1 (29/08) đã lộ lỗi HIỂN THỊ**: bấm "➕ Thu lần nữa" không thấy nút/banner đổi gì
  → sửa ở v1.63.3 (mục 1 ở trên). **Test lại từ đầu** với bản 1.63.3: bấm nút phải THẤY banner
  xanh hiện ra ngay, rồi mới xem chỉ số trước có đúng nối tiếp không — nếu banner hiện mà số vẫn
  sai thì mới là lỗi `chi_so_truoc_ct_()` thật, báo lại kèm mã ghế/ngày/report_id cả hai lần.
- **v1.62.0 nối dòng thời gian — chưa nối ở `xoa()` / `undo()`** (xoá/hoàn tác cả báo cáo). Nếu xoá
  một ngày GIỮA rồi thấy chỉ số trước ngày sau không tự lùi về mốc trước đó → thêm gọi
  `VHG_BaoCao::noi_tiep()` vào hai hàm đó (class-vhg-ketoan.php). Chưa làm vì ngữ nghĩa restore của
  undo phức tạp, và xoá ngày giữa hiếm.
- **v1.62.0 ô ảnh mọi chế độ** — đã anh xác nhận trực quan (điện thoại & PC đều thấy 📷/🧹). Coi như xong.
- **v1.63.1/1.63.2 PIN phiên** — xem mục 1 ở trên. 1.63.1 CHƯA hết lỗi dù đã đăng xuất/đăng nhập
  lại đúng cách; 1.63.2 thêm chốt migration tay + hiện `viSao` (lý do trượt) thẳng lên màn hình
  cổng PIN. Cần Võ Nguyễn Hồng Nhung thử lại "Mở màn Báo cáo doanh thu" — nếu vẫn hỏi PIN, đọc
  đúng dòng vàng hiện trên màn (viSao) rồi báo lại nguyên văn, đừng đoán tiếp từ đầu.

---

## 4. Ghi chú kiến trúc nhanh (cho người tiếp nhận)

- **SPA + cổng PIN:** `class-vhg-trang.php` chứa toàn bộ SPA (JS trong nowdoc `<<<'JS' … JS;`, hai
  khối: `js()` chính và `js_baocao()`). Mọi việc đi qua cổng `api()`; chốt phân quyền đặt MỘT chỗ ở
  đầu cổng (`VHG_Auth::duoc_lam()` + `VHG_Auth::VIEC_QUAN_TRI`).
- **Hai hệ PIN:** `VHG_Auth::users()` (nguồn nhân sự, đọc `vhcp_cfg`/option) và bảng `bc_pin` (ngoại
  lệ/khoá cho trang báo cáo, `VHG_BaoCao::pin_info()`).
- **Phân quyền:** `vhg_vai_tro_vao` / `_giup` / `_chot` / `_quantri` (option). Admin luôn được ép có.
- **Doanh thu money-only:** các rollup nới điều kiện `(chi_so_sau IS NOT NULL OR tong<>0 OR
  actual<>0)`; nhưng tra dòng thời gian chỉ số (`chi_so_truoc`, mốc) vẫn GIỮ nghiêm `chi_so_sau IS
  NOT NULL`.
- **Lọc kỳ bất kỳ:** `VHG_Thu::dau_ky/cuoi_ky` và `VHG_KeToan::dau_ngay_/cuoi_ngay_` nhận `YYYY-MM`.
- **Sổ tay Hotline (`hotline_bc`) khác nhật ký `lenh`:** đừng nhầm hai bảng khi đọc code mới —
  `lenh` tự động (mọi lượt bấm Bật), `hotline_bc` thủ công (Hotline tự tổng kết, có số tiền hoàn
  khách mà `lenh` không có). Xem khối 🔴 đầu `class-vhg-hotline.php`.

## 5. Cách kiểm tra & đóng gói (đã dùng suốt session)

```bash
# Lint PHP
php -l vhcp-ghe/includes/class-vhg-trang.php

# Kiểm cú pháp JS trong nowdoc (tách khối <<<'JS' … JS; rồi node --check)
#   (script tách khối + node --check từng khối — xem lịch sử phiên làm việc)

# Self-test kế toán (13 phép) — có harness ở scratchpad
php <harness>/run_selftest.php     # kỳ vọng: SELF-TEST: 13/13 ĐẠT

# Đóng gói bản cài từ file đã theo dõi trong git
git ls-files vhcp-ghe | zip -q vhcp-ghe-<ver>.zip -@
```

## 6. Ràng buộc bắt buộc
- ⛔ **Repo CÔNG KHAI** — KHÔNG hardcode PIN / khoá / secret trong mã. PIN nằm ở nhân sự / `bc_pin`.
- Chỉ push nhánh `claude/posh-qr-kh1urz`; không mở PR nếu chưa được yêu cầu.
- Mỗi lần đổi tính năng: **tăng `VHG_VERSION`** ở `vhcp-ghe.php` (header `Version:` + `define`), vì
  `vhg_maybe_upgrade()` chạy `dbDelta` khi đổi phiên bản.
