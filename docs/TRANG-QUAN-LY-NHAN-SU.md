# Trang Quản Lý Nhân Sự — ai vào được trang nào

> Anh Thắng 26/08/2026: *"Giờ anh muốn tạo 1 trang Quản lý nhân sự riêng, để cấu hình nhân sự
> có thể xem những trang nào trong tất cả các trang anh làm"* — *"để điều phối nó dễ hơn"*.

Địa chỉ: **`https://khmatrix.com/nhan-su/`**
Nằm trong plugin **Chấm Công** (`vhcp-cham-cong` ≥ 2.48.0). Không phải cài thêm gì.

Vào được: **Kế toán trở lên**. Cùng phiên với trang quản trị chấm công — đăng nhập một lần,
bấm qua lại được, không gõ PIN hai lần. Trên thanh của trang quản trị chấm công có nút
**👥 Quản lý nhân sự** (chỉ hiện với người mở được nó).

---

## 1. Bảng làm việc thế nào

Mỗi hàng là một người trong hồ sơ nhân sự, mỗi cột là một trang. Mỗi ô có **ba** trạng thái:

| Ô đang chọn | Nghĩa là |
|---|---|
| **Theo vai (vào được)** / **Theo vai (không)** | Người này đi theo thang vai. Đổi vai trong hồ sơ là quyền đổi theo. Đây là mặc định của tất cả mọi người. |
| **Mở** (nền xanh) | Ghim cứng: người này vào được trang đó, dù vai chưa tới. |
| **Khoá** (nền đỏ) | Ghim cứng: người này **không** vào được, dù là Admin. |

**Vai vẫn là luật; bảng này chỉ ghi những chỗ khác luật.** Không ai phải đi tích 240 người ×
mấy trang — khai xong đúng mấy dòng lệch là xong, phần còn lại tự chạy theo vai.

Bảng có **lọc theo cơ sở / vai trò / ô tìm tên–mã** và **phân trang 50 người**. Bấm *Lưu bảng
này* chỉ ghi 50 người đang hiện; ngoại lệ của người ở trang khác không bị đụng tới.

Khối **"Đang có N ngoại lệ"** ở dưới gom tất cả những chỗ khác mặc định về một chỗ, kèm nút
**Gỡ** cho từng dòng — bảng chính có lọc và phân trang, nên đây là chỗ soát lại cho chắc.

Khối **"Mặc định theo vai"** (gập lại) in ra bảng trang × vai để trả lời "sao người này vào
được" mà không phải hỏi ai.

---

## 2. Những trang khai được ở đây

| Trang | Địa chỉ | Mặc định vào được từ bậc |
|---|---|---|
| Quản trị chấm công | `/quan-tri-cham-cong/` | Nhân viên (màn bên trong vẫn gác riêng) |
| Trạm chấm công | `/cham-cong/` | Nhân viên |
| Nội bộ | `/noi-bo/` | Nhân viên (còn qua thêm chốt theo vai của chính trang Nội bộ) |

Chốt có hiệu lực thật, ở đúng chỗ của từng trang:

* **Quản trị chấm công** — chối ngay ở cửa vào, sau khi đăng nhập. Màn chối vẫn có nút *Thoát*.
* **Trạm chấm công** — chối lúc **phát thẻ**, tức là sau khi gõ đúng PIN. Trạm là trang
  JavaScript: người vừa mở nó ra thì chưa có phiên nào, nên gác ở cửa trang là chối cả người
  chưa kịp gõ PIN. Gõ đúng PIN mà bị khoá thì màn hình nói rõ lý do, **không** báo "PIN không
  đúng", và **không** tính vào số lần gõ sai.
* **Nội bộ** — chối ở cửa vào, trước khi vẽ một dòng bảng tin nào.

---

## 3. Những trang **không** khai được ở đây — và vì sao

Bốn trang dưới đây cố ý vắng mặt. Đây không phải chuyện làm thiếu:

| Trang | Vì sao |
|---|---|
| **Ghế massage** (`/ghe/`) | Trang của **khách**. Khách quét QR rồi trả tiền, không đăng nhập và không có Mã NV. Khoá được nó là ghế đứng im, tiền không vào. |
| **Cổng K&H** | Cửa trước, công khai, chỉ liệt kê các hệ. Khoá cửa trước là khoá cả nhà. |
| **Thư viện hợp đồng** (`/hop-dong/`) | Giao diện lấy thẳng từ Apps Script và tự đăng nhập bên trong, không mang phiên chấm công sang. |
| **Vận hành chi phí** (`/chi-phi/`) | App đó có **sổ người dùng riêng** (vai "Kế toán cá nhân" / "Kế toán NCC"), không nhất thiết có Mã NV trong hồ sơ chấm công — nên ngoại lệ khai theo Mã NV ở đây không bám vào đâu được. Khai quyền cho app chi phí thì làm ở chính app ấy: **Cấu hình → Người dùng**. |

Chính trang này cũng không nằm trong bảng: nó gác bằng vai **Kế toán trở lên**, cố định. Nếu
khai được quyền vào chính nó thì một lần tích nhầm là không còn ai gỡ được nữa.

---

## 4. Người chưa có Mã NV

Ngoại lệ bám vào **Mã NV**. Hồ sơ nào chưa có mã thì ô quyền hiện chữ *"chưa có Mã NV"* thay
vì ô chọn — khai cho họ cũng không có tác dụng, vì thẻ phiên của họ mang mã rỗng. Sửa ở
**Quản trị chấm công → Hồ sơ & tài khoản**.

---

## 4b. Thêm nhân sự — chỉ MỘT cửa (08/09/2026)

> Anh Thắng: *"Việc thêm nhân sự rất rối. Không rõ ràng ở trang nào. Gộp lại chỉ cần 1 trang
> thêm được là được"*.

Cửa duy nhất tạo hồ sơ: **Quản trị chấm công → Hồ sơ & tài khoản → `+ Hồ sơ mới`**
(`/quan-tri-cham-cong/?man=ho_so&sua=+`). Mọi chỗ khác chỉ **chỉ đường** tới đó:

| Chỗ bấm | Trước | Nay |
|---|---|---|
| Trang này (`/nhan-su/`) — `➕ Thêm nhân sự` | link sang biểu mẫu ấy | y nguyên, nhưng nói rõ nó mở cửa duy nhất ở trang kia |
| Quản trị chấm công — ô `➕ Thêm nhân sự mới` | mở biểu mẫu | y nguyên, ghi rõ là **cửa duy nhất** |
| wp-admin → Chấm Công → Nhân sự | **biểu mẫu tạo hồ sơ thứ hai** | bỏ; chỉ còn nút chỉ đường + vẫn **sửa** hồ sơ đã có |
| Thẻ 🔑 Tài khoản đăng nhập | trông như một cửa thêm người | thêm một dòng: thẻ này **không tạo người mới** |

**Vì sao phải bỏ biểu mẫu ở wp-admin, không chỉ để đó cho tiện.** Hai biểu mẫu ấy cùng ghi qua
`VHCC_NhanSu::luu_ho_so()` nên nhìn không ra sai. Nhưng cửa ở trang web còn làm **ba việc nữa** mà
cửa wp-admin không có: nhận **ảnh thẻ**, **đẩy hồ sơ xuống máy chấm công**, và lấy **mẫu đối chiếu
khuôn mặt** cho chấm công online. Người được tạo ở wp-admin vì thế **không quẹt mặt được** — mà
không có gì báo, tới lúc họ đứng trước máy mới biết.

Chặn ở **cả hai đầu**, không chỉ ẩn nút: đường `POST` của màn wp-admin cũng từ chối mã **chưa có
hồ sơ** (một tab còn mở từ trước, hoặc một liên kết đã lưu, vẫn gửi lên được). Sửa hồ sơ **đã có**
thì vẫn cho — đó mới là việc của màn ấy.

### Thẻ "Tạo nhân sự mới" + biểu mẫu đủ ô (3.43.0)

> Anh Thắng, ngay sau khi cài 3.42.0: *"không có ô rõ ràng tạo nhân sự mới, nhập đủ trường thông
> tin nếu có"*.

Gộp về một cửa là đúng, nhưng cửa ấy vẫn là **cái nút nhỏ `+ Hồ sơ mới` nằm lẫn trong hàng lọc**,
dưới hai thẻ to (nạp `.csv`, tài khoản đăng nhập) — nên vẫn phải đi tìm. Nay:

* **Thẻ riêng `➕ Tạo nhân sự mới` đứng ĐẦU màn** *Hồ sơ & tài khoản*, kê thẳng những ô sẽ phải
  khai để đọc là biết mình sắp điền gì. Thẻ chỉ là **đường vào** `?man=ho_so&sua=+` — không dựng
  biểu mẫu thứ hai.
* **Đủ ô**: thêm **Người liên hệ khẩn** và **SĐT người liên hệ khẩn**. Hai cột này có trong bảng
  `nhan_vien` và đang giữ dữ liệu, nhưng trước đây **chỉ khai được ở màn wp-admin** — bỏ biểu mẫu
  bên đó mà không đưa sang thì thành ra không còn cửa nào nhập chúng. Biểu mẫu nay có đủ **20 ô**
  người dùng khai được, cộng ô ảnh thẻ và lưới tích cơ sở.
* **Mã NV và Họ tên bắt buộc** — hồ sơ không tên thì bảng công tra ra mã trần.
* **Giới tính đổi thành ô CHỌN** (Nam / Nữ → `male` / `female`). Lệnh xuống máy chấm công chỉ nhận
  đúng hai giá trị ấy, nên gõ tay "Nam" là máy nhận hồ sơ **không có giới tính** mà màn hình vẫn
  thấy có chữ. Sổ cũ ghi "Nam"/"nam" thì ô chọn **kê lại giá trị đang có** — không thì lượt lưu kế
  tiếp xoá trắng ô của họ.
* **Trạng thái làm việc** vẫn là ô **gõ có gợi ý** (Đang làm · Tạm nghỉ · Đã nghỉ việc), cố ý
  không ép thành ô chọn: luật "đã nghỉ" đọc theo chữ *nghỉ* trong câu (`VHCC_NhanSu::da_nghi`) và
  sổ cũ có những câu như "Đã nghỉ 12/2025".

### Chặn PIN trùng ở mọi đường ghi (3.47.0)

> Anh Thắng: *"chặn trường hợp tạo mã pin trùng nhé"*.

Có **bốn đường** ghi được vào hai ô PIN, mà trước bản này chỉ **một** đường soát trùng:

| Đường ghi | Trước | Nay |
|---|---|---|
| Màn Hồ sơ ngoài web (`sua_hs`) | soát PIN đăng nhập | soát cả **PIN máy** |
| Màn wp-admin (`luu_ho_so`) | **không soát gì** | soát cả hai |
| Nạp `.csv` | chỉ soát *khuôn* PIN | soát trùng: **bỏ đúng ô PIN** của dòng ấy, giữ các ô khác, báo tên ra |
| Kéo từ app gốc | không soát | như trên, và ghi lý do vào dòng báo cáo |

Luật chung nằm ở **`VHCC_NhanSu::pin_trung_loi()`** — một hàm cho cả bốn đường, vì mỗi đường tự
viết một phép soát là sớm muộn có đường quên, mà đường quên thì hỏng im lặng.

**Hai ô PIN, hai phạm vi khác nhau:**

* **PIN đăng nhập** — so **cả chuỗi**. Trùng là cổng nhận người *gặp trước*, nhật ký ghi tên người
  đó, còn người kia gõ đúng PIN của mình mà vào hồ sơ người khác — và họ tưởng mình bấm nhầm nên
  **không ai báo**.
* **PIN máy chấm công** — so **trong cùng cơ sở** (tính cả cơ sở phụ). Trùng là **giờ của người này
  ghi vào người kia**. Cố ý *không* so cả chuỗi: mỗi đầu đọc chỉ giữ người của cơ sở nó, chặn cả
  chuỗi thì tới cơ sở thứ mười là không còn số 4 chữ số nào cấp được, và người ta sẽ đi vòng qua
  bằng cách bỏ trống ô.

Ô PIN **để trống vẫn là "không đổi"** — không soát ô trống, kẻo mọi lượt sửa số điện thoại cũng bị
chối oan.

**Chỗ đang trùng từ TRƯỚC thì chặn không dọn được** (sổ kéo về từ Sheets, nơi PIN gõ tay). Nên thẻ
*Hồ sơ nhân sự* nay tự chỉ ra: một dải đỏ **"N mã PIN đang bị M người dùng chung"** kèm đường bấm,
và một mục lọc **⚠ PIN đang TRÙNG nhau** để xem đúng nhóm ấy. Dọn hết thì dải báo tắt — báo mãi
thành tiếng ồn rồi không ai đọc nữa.

Phép thử (30 phép): chối PIN đăng nhập trùng ở `luu_ho_so` và nêu **trùng với ai** · PIN máy trùng
**cùng cơ sở** thì chối, **khác cơ sở** thì cho · lưu lại chính hồ sơ đang giữ PIN đó không bị tự
chối · không gửi ô PIN thì lưu bình thường · màn web chối PIN máy trùng · `.csv` bỏ đúng ô PIN mà
vẫn nạp các ô khác, bắt cả **trùng giữa hai dòng trong cùng file** · đường kéo bỏ PIN của dòng sau
và **nói ra trong dòng báo cáo** · dải đỏ + mục lọc chỉ ra đúng người đang trùng, và **tắt** sau khi
dọn.

---

### Dọn hai chỗ gây nhầm (3.46.0)

> Anh Thắng: *"loại bỏ chỗ này tránh nhầm"* (nút ở `/nhan-su/`) và *"loại bỏ chỗ này"* (thẻ 🔑).

**1. Bỏ nút `➕ Thêm nhân sự` khỏi trang `/nhan-su/`.** Nó chỉ là một đường dẫn sang biểu mẫu ở màn
*Hồ sơ & tài khoản*, nhưng đặt ở đây thì trang này trông như một cửa thêm người thứ hai — đúng cái
rối đợt 3.42.0 đang gỡ. Trang `/nhan-su/` làm **một việc**: khai ai vào được trang nào.

**2. Thẻ 🔑 `Tài khoản đăng nhập` chỉ còn hiện khi cổng đang đọc SAI chỗ.** Ở trạng thái đúng —
cổng đọc thẳng Hồ sơ Nhân sự — thẻ ấy không còn việc gì, mà **hai nút của nó đều hỏng im lặng**:

* *Nạp tài khoản* chép hồ sơ sang **danh sách riêng**, mà cổng không đọc danh sách ấy nữa — bấm
  xong báo "đã nạp N người" mà chẳng đổi gì;
* *Khai Admin* cũng ghi vào chính danh sách đó (`VHCC_NguoiDung::khai_admin`), nên tài khoản vừa
  khai **không đăng nhập được**.

Nguồn khác thì thẻ **vẫn hiện**, vì lúc đó nó là *chỗ sửa* (nút "Cho cổng đọc thẳng Hồ sơ Nhân
sự") — đường duy nhất ở trang web. Không mất đường nào: đổi nguồn còn làm được ở **wp-admin →
Cài đặt**, còn cấp quyền đăng nhập thì mở hồ sơ người đó, đặt **Vai trò + PIN** — có hiệu lực ngay.

Ẩn thẻ thì phải giữ lại cái *biết*: thẻ **Hồ sơ nhân sự** nay có một dòng "Cho ai đăng nhập được:
mở hồ sơ người đó, đặt Vai trò + PIN". Và **ba câu nhắc cũ** trỏ vào thẻ 🔑 ("nhớ bấm Nạp tài
khoản ở ô 🔑 bên trên") nay đổi theo nguồn — không thì chúng chỉ người đọc đi tìm một cái thẻ
không còn ở đó.

Phép thử (11 phép): trang `/nhan-su/` không còn nút lẫn liên kết `sua=moi` nhưng **bảng quyền vẫn
nguyên** · nguồn `ho_so` thì thẻ 🔑 biến hẳn, không còn cả chữ "Nạp tài khoản"/"Khai Admin" ở đâu
trên màn, mà thẻ tạo nhân sự + bảng hồ sơ + dòng chỉ cách cấp đăng nhập vẫn còn · nguồn khác thì
thẻ hiện kèm nút trỏ cổng về đọc thẳng hồ sơ. Kèm một chốt **chống xanh giả**: phép thử tự kiểm
"đã đăng nhập được chưa" trước, vì đổi nguồn là đổi luôn chỗ cổng tra PIN — đăng nhập trượt thì
trang chỉ có ô PIN và mọi phép "không thấy thẻ" đều xanh mà chẳng chứng minh gì.

---

### Nhân viên đang có của cơ sở, hiện ngay trong biểu mẫu tạo (3.45.0)

> Anh Thắng: *"trước khi tạo làm sao biết nhân viên đó có chưa, thì bổ sung danh sách cửa hàng đó
> có, khi chọn cửa hàng để thêm nhân viên sẽ hiện danh sách nhân viên đang có của cửa hàng đó"*.

Đáng làm vì **Mã NV là khoá**: tạo hồ sơ thứ hai cho một người đang có là **công của họ bị chẻ
đôi**, mỗi nửa một bảng lương — hỏng im lặng, cuối tháng mới lộ, và gỡ thì phải đi qua luồng đổi mã
kéo theo mọi hàng chấm công.

Trong biểu mẫu tạo, **ngay dưới lưới tích cơ sở**:

* Tích cơ sở nào thì hiện **danh sách nhân viên đang có** của cơ sở đó (mã · họ tên · cờ *đã
  nghỉ*), kèm số người. Tích nhiều cơ sở thì hiện nhiều danh sách.
* Người **làm hai cơ sở** (tích thêm cơ sở phụ) hiện ở **cả hai** danh sách — họ chính là người dễ
  bị tạo trùng nhất, vì cửa hàng bên kia không thấy họ trong danh sách của mình thì tưởng chưa có.
* **Người đã nghỉ vẫn hiện**, làm mờ và ghi *đã nghỉ*: nghỉ rồi mà lập hồ sơ mới chính là cái trùng
  cần chặn.
* Đang gõ ô **Họ tên** mà trùng tên người đã có thì hiện ngay dải cảnh báo, nêu **mã + cơ sở** của
  người đó. Khoá so là `khoa_so()` (bỏ dấu, bỏ ký tự lạ) — "Nguyễn Thị A" và "NGUYỄN THỊ  A" ra
  một, vì đúng cặp ấy mới nguy: CSDL coi là hai dòng nên không chặn, còn người đọc thấy y hệt.

**Dữ liệu nhúng sẵn trong trang, không mở cửa mạng mới.** Cả chuỗi hơn hai trăm hồ sơ gói lại chỉ
vài chục KB: tích cơ sở là thấy ngay, không chờ mạng, và không phải dựng một đường ajax mới — đường
mới là một cửa mới phải gác, mà thứ nó trả về đúng là danh sách người của cả chuỗi.

Hai chốt kèm theo: gói đi qua **`ds_nhan_vien( $toi, … )`** nên Cửa hàng trưởng chỉ thấy người của
cơ sở mình, và gói **chỉ có mã · tên · cờ nghỉ** — không PIN, không lương, không CCCD (mỗi thứ nhúng
thêm là một thứ ai mở mã trang cũng đọc được).

Phép thử (16 phép): khối chỉ có ở nhánh tạo mới · xếp đúng theo từng cơ sở · người hai cơ sở hiện ở
cả hai · người đã nghỉ có cờ · **gói chỉ chứa đúng ba khoá `m`/`t`/`n`** và PIN không hề nằm trong
trang · khoá so tên đã bỏ dấu · **Cửa hàng trưởng không thấy người cơ sở khác** (gọi thẳng hàm dựng
khối, không đo qua trang — trang của họ vốn rỗng nên đo qua trang là xanh giả) · không có người đăng
nhập thì khối rỗng hẳn.

---

### Bấm nút mà trang vẽ lại y nguyên — dấu `+` trong địa chỉ (3.44.0)

> Anh Thắng, sau khi cài 3.43.0: *"đã hiện, nhưng bấm cũng không chạy"*.

Mã lệnh "hồ sơ mới" trước đây là **dấu `+`** (`?man=ho_so&sua=+`). Liên kết sinh ra đúng
(`sua=%2B`), nhưng chỉ cần **một chặng nào đó trả `%2B` về `+` nguyên hình** — plugin tăng
tốc/bộ đệm viết lại liên kết, một lượt chuyển hướng chuẩn hoá, hay chép tay địa chỉ — thì PHP đọc
`+` trong chuỗi truy vấn là **dấu cách**. `sanitize_text_field` cắt dấu cách thành chuỗi rỗng, chốt
`'' !== $sua` thành sai, và **màn danh sách hiện lại như chưa bấm gì**: không một dòng báo lỗi, nên
nhìn vào chỉ thấy "nút không chạy".

Nay mã lệnh là chữ **`moi`** (chỉ chữ cái, không chặng nào dập được). Máy chủ vẫn nhận hai dạng cũ:
`sua=+` (dấu trang đã lưu) và `sua=` rỗng (chính là cái `+` vừa bị dập) — sửa mỗi liên kết mới thì
người đang giữ liên kết cũ vẫn gặp lại đúng cái hỏng im lặng ấy.

Cùng lượt sửa còn một lỗi thứ hai: nút `+ Hồ sơ mới` ở thẻ *Hồ sơ nhân sự* sinh liên kết
**thiếu `man=ho_so`**, nên với ai có màn mặc định khác thì nó rơi về màn nhà chứ không mở biểu mẫu.

Phép thử (6 phép): `sua=moi` ra biểu mẫu · `sua=+` cũ vẫn ra · **`+` bị dập thành dấu cách cũng
ra** · `sua=` rỗng cũng ra · **không có khoá `sua` thì vẫn là màn danh sách** (nới quá tay là không
ai xem được danh sách nữa) · và **không còn chỗ nào trong mã sinh liên kết bằng dấu `+`**.

---

Phép thử: 18 phép nữa trong `test-cham-cong.php` — thẻ đứng đầu màn (trên `.csv` và trên thẻ tài
khoản), nút trỏ đúng `man=ho_so&sua=+`, thẻ **không** chứa ô `ma_nv` nào (không phải biểu mẫu thứ
hai), hai ô khẩn lưu xuống bảng thật, giới tính là `<select>` có `male`/`female`, và **giá trị cũ
trong sổ không bị xoá trắng**.

---

Phép thử: `tools/test/test-cham-cong.php` mục **39b** (13 phép) — biểu mẫu biến khỏi màn `sua=+`,
`POST` tạo mới bị chặn và **không** ghi hàng nào vào bảng, sửa hồ sơ đã có vẫn lưu, và **cửa duy
nhất kia vẫn còn** (soi thẳng mã nguồn trang web, kẻo bỏ bên này mà bên kia cũng mất thì hết đường
tạo người).

---

## 4c. Người mới thêm xong không chấm công được — 3.48.0 (08/09/2026)

Anh Thắng chụp màn `/cham-cong/` mở trong Zalo: *"thêm nhân viên bị lỗi ở chấm công"*, rồi hỏi
*"khả năng nào không chọn nhiệm vụ lỗi không"* và chốt lại *"tại báo cáo lỗi không rõ ràng"*.

Dựng lại đúng cảnh ấy trong bộ giả lập (`scratchpad/sim-moi.php`, và nay là phép thử thật) ra **ba
câu trả lời khác nhau** — không cái nào là cái ban đầu tưởng.

### a) Không chọn nhiệm vụ **không** phải nguyên nhân

Gác nhiệm vụ trong `VHCC_Online::cham_cong()` chỉ chạy khi ô ấy **khác rỗng**. Bỏ trống là đi
thẳng: bộ giả lập cho người vừa được thêm chấm với `nhiemVu:''` → `ok:true, loai:"vao"`. Trang
trạm cũng không dựng ô nhiệm vụ khi hồ sơ chưa khai việc nào. Đã khoá lại bằng phép thử trong
`kiem-tram.php` để lần sau không phải hỏi lại.

### b) Lỗ thật: **PIN đăng nhập trùng với sổ PhanQuyen cũ**

Chốt chặn PIN trùng ở 3.47.0 chỉ soát **bảng hồ sơ**. Nhưng cửa trạm `VHCC_Tram::tim_pin()` tra
**sổ PhanQuyen TRƯỚC**, hồ sơ sau. Nên cấp cho người mới đúng con số mà sổ cũ đã cấp cho người
khác thì **lọt** — rồi người mới gõ PIN của mình lại **vào nhầm tài khoản người kia**: tên hiện
trên trạm là tên người kia, lượt chấm ghi sang mã người kia. Không một dòng báo lỗi, đúng loại
hỏng im lặng mà người dùng tưởng mình bấm nhầm.

Nay `pin_dang_dung()` soát **cả hai kho, theo đúng thứ tự cửa trạm đọc**. Hai ngoại lệ cố ý:

* hàng sổ cũ mang **đúng mã đang sửa** → cho, vì đó là hai bản ghi của **cùng một người**;
* hàng sổ cũ **chưa khai mã chấm công** → cho, vì `tim_pin` bỏ qua hàng ấy nên nó không cướp được
  phiên của ai — mà chuyện thường gặp nhất lại là *chính người ấy* đã có tên trong sổ cũ và nay
  mới được lập hồ sơ với đúng PIN quen dùng. Chặn nhóm đó là chối oan hàng loạt.

Câu chối cũng nói thẳng số ấy **nằm ở sổ PhanQuyen cũ**, kẻo người sửa đi tìm cái mã đó trong
danh sách hồ sơ và không thấy đâu.

### c) Báo lỗi rõ ràng ở trạm

| Trước | Nay |
|---|---|
| `Chưa khai "Cơ sở chấm công online" cho tài khoản này.` — ô ấy **chỉ có ở màn PhanQuyen cũ**, không có trên biểu mẫu một cửa họ vừa dùng | gọi tên người phải sửa, và chỉ đúng **lưới "Cơ sở"** trong hồ sơ |
| chưa có cơ sở mà nút **CHẤM CÔNG** vẫn sáng: bấm → chờ camera → chụp → bấm lưu → mới ăn câu chối | khoá nút **ngay lúc mở trang**, kèm câu nói rõ ai phải sửa gì |
| `Máy chủ không trả lời sau 10 giây` rồi hết — người đứng đó không biết bấm lại hay không | **hỏi lại máy chủ** rồi trả lời dứt khoát (xem dưới) |
| `Failed to fetch` / `Load failed` — chữ của trình duyệt, ba trình ba câu | dịch ra việc phải làm, **giữ chữ gốc trong ngoặc** để còn đối chiếu |
| không có gì để đọc trên ảnh chụp màn | mã ngắn cuối câu: `[QUA-HAN: cham]`, `[MAT-MANG: …]`, `[HTTP-500: cham]` |

**Quá hạn KHÔNG đồng nghĩa chưa ghi.** Lượt `cham` là lượt duy nhất mang ảnh; quá hạn thường là
ảnh đã đi rồi, chỉ câu trả lời chưa về. Bảo "chưa lưu được" là mời người ta bấm lượt thứ hai — mà
lượt thứ hai ngay sau giờ vào là **giờ ra**, mất cả ca công. Nên `soatLaiDaGhi()` chụp bảng *hôm
nay* của cơ sở ấy **trước khi gửi**, và khi lượt gọi hỏng thì hỏi lại bằng một lượt `toi` nhẹ
(không ảnh) rồi so:

* bảng đổi → **đã ghi thật**: đóng màn chọn, bỏ ảnh, và dặn *đừng bấm lại*;
* bảng y nguyên → **chưa ghi**: bảo bấm lưu lần nữa;
* hỏi lại cũng hỏng → nói thẳng là **chưa biết**, và chỉ cách tự kiểm bằng bảng *Hôm nay*.

So **cả bảng** (kèm giờ ra) chứ không chỉ *"đã có giờ vào chưa"* — buổi chiều thì giờ vào buổi
sáng vẫn nằm đó, đếm kiểu ấy là lượt tan làm nào cũng ra "đã ghi rồi".

Phép thử: 8 phép trong `test-cham-cong.php` (đo **cả hai đầu**: cửa ghi có chối không, *và* cửa
trạm có thật sự nhận nhầm người không — đo mỗi cửa ghi thì chốt đặt sai chỗ vẫn xanh) và 20 phép
trong `kiem-tram.php`.

## 4d. Chuyển đổi cơ sở chính ↔ cơ sở phụ — 3.62.0 (09/09/2026)

Anh Thắng, trước khối **"Cơ sở được chấm công"** trên trạm (`JP_HCM · cơ sở chính` /
`VP_KH-HCM · cơ sở phụ`): *"làm sao để chuyển đổi cơ sở chính cà cơ sở phụ"*.

### Trả lời ngắn

Ở **ô Cơ sở** (cột *Cơ sở* của trang `/nhan-su/`, hoặc ô *Cơ sở làm việc* trong hồ sơ) nay mỗi cơ
sở có thêm một **nút tròn `chính`**. Bấm nút tròn của cơ sở muốn đặt làm chính rồi bấm **Lưu** —
xong. Không cần bỏ tích cơ sở nào, và **không ai bị gỡ khỏi cửa hàng nào**.

Nút tròn chỉ hiện khi người ấy có **từ hai cơ sở trở lên** — một cơ sở thì "chính" không có nghĩa.

### "Cơ sở chính" nghĩa là gì (và không nghĩa là gì)

| Có nghĩa | Không có nghĩa |
|---|---|
| Cơ sở được **chọn sẵn** trong ô "đang có mặt ở cơ sở nào" lúc lưu lượt chấm | Không phải "cơ sở duy nhất được tính công" |
| Cơ sở đứng ở cột `cua_hang` — vài phép gom cần **một** tên thì lấy tên này | Không phải thứ bậc: cơ sở phụ **được tính công đủ như nhau** |
| Cơ sở đi vào thẻ phiên (`coso`), nên nó là nhãn `cơ sở chính` anh thấy trên trạm | Không quyết định quyền: quyền đi theo **vai**, phạm vi theo **ô tích** |

Trạm nay in thẳng câu ấy dưới bảng cơ sở, vì hai cái nhãn kia đọc lên như thứ bậc và người làm
hai nơi tưởng công ở cơ sở "phụ" là hạng hai.

### Trước 3.62.0 thì **không có đường nào** — ba lớp cùng chặn

1. Cả hai lưới ô tích gửi tên cơ sở lên theo **thứ tự vẽ**, mà thứ tự vẽ đã `sort()`/`ksort()`
   theo bảng chữ cái.
2. Nơi ghi lấy **phần tử đầu** làm `cua_hang`. Nên `JP_HCM` + `VP_KH-HCM` thì cơ sở chính vĩnh
   viễn là `JP_HCM`.
3. `VHCC_NhanSu::dat_ds_coso()` so hai danh sách theo **tập hợp** rồi trả về sớm — có kéo lại thứ
   tự cũng không ghi.

Đường duy nhất còn lại là gõ tay lại cả ô *Cơ sở làm việc* ở màn **wp-admin** (nó giữ nguyên thứ
tự gõ) — không ai biết, và màn ấy sắp bỏ.

### Ba chốt đi kèm

* **Đổi cơ sở chính KHÔNG reset quyền riêng.** Chuyển cơ sở thật (thêm/bỏ) thì ngoại lệ quyền của
  người ấy bị xoá về mặc định — đúng luật cũ. Nhưng đổi cái đứng đầu của **cùng một danh sách**
  thì không: phạt một ô quyền cho một cú bấm đổi mặc định là phạt oan.
* **Phải phụ trách cả hai đầu.** Đổi cơ sở chính giữa `A` và `B` thì người bấm phải phụ trách cả
  `A` lẫn `B` — cơ sở chính là cơ sở mặc định của người ta, đổi hộ ở một nơi mình không phụ trách
  là đổi sau lưng.
* **Không truyền chỉ định thì thứ tự danh sách không tự quyết định gì.** Luật cũ còn nguyên cho
  mọi đường ghi khác (lượt nạp .csv, bản kéo sheet): *"kéo lại thứ tự tích không phải là chuyển cơ
  sở của ai"*.

Và chốt *"đổi cửa hàng cần Quản lý"* trong `VHCC_NhanSu::luu_ho_so()` nay có vế
`$chi_doi_thu_tu`: đổi thứ tự chính/phụ **trong cùng danh sách cơ sở người ấy đang có** không
phải là chuyển cửa hàng, nên Kế toán bấm được — không thì cái nút vừa thêm bị khoá với đúng những
người hay dùng nó, kèm một câu chối nhắc "ô Cơ sở phụ" đã bỏ từ 3.13.0.

Đo bằng 37 phép trong `tools/test/test-cham-cong.php` — kể cả đường thật anh Thắng nhìn thấy:
`cua_hang` → `VHCC_Tram::tim_pin()` → `VHCC_Online::thong_tin()['coSoMacDinh']`.

---

## 4e. Cơ sở "chỉ quản lý — không chấm công" — 3.63.0 (09/09/2026)

Anh Thắng, trước khối *"Cơ sở được chấm công"* của chính mình đang liệt kê **sáu** cơ sở:
*"đối với cửa hàng chỉ quản lý nhân viên không chấm công thì làm sao để loại ra khỏi bảng chấm
công, nhưng vẫn quản lý được nhân viên cơ sở đó"*.

### Trả lời ngắn

Trong **ô Cơ sở**, mỗi cơ sở nay có thêm ô tích **`chỉ QL`** cạnh nút tròn *chính*. Tích nó thì
cơ sở ấy:

* **biến khỏi ô chọn cơ sở lúc chấm công** trên trạm (và lượt POST gửi thẳng cũng bị chối),
* **không mọc hàng trống** trong lưới bảng công của cơ sở ấy,

nhưng **ô tích cơ sở vẫn nguyên**, nên:

* thẻ phiên vẫn mang cơ sở ấy → `co_quyen_coso()` vẫn đúng,
* **danh sách nhân sự của cơ sở ấy vẫn quản được như thường**,
* và **mấy lượt đã chấm trước đó không mất** — cờ chặn lượt MỚI, không xoá cái đã ghi.

### Vì sao phải là "cờ phụ thêm", không phải một cột riêng

Từ 31/08/2026 một ô tích mang **ba nghĩa** cùng lúc: nơi người ta **làm**, nơi người ta **quản**,
nơi người ta **chấm**. Ba nghĩa ấy trùng nhau với gần hết mọi người — nhưng không trùng với
người quản nhiều cơ sở mà chỉ đứng làm ở một hai nơi.

Cách rẻ nhất là bỏ tích cơ sở đi — nhưng đó là **làm sai đúng nửa sau câu hỏi**: bỏ tích là mất
luôn quyền quản lý ở đó. Cách thứ hai là dựng một cột "cơ sở quản lý" riêng ngoài ô tích — nhưng
khi ấy **mọi** câu hỏi "người này ở đâu" trong cả plugin phải sửa lại, và mỗi câu bỏ sót là một
chỗ mất quyền câm.

Nên: `nhan_vien.coso_ql` là **tập con của những cơ sở đã tích**. Mọi thứ cấp phạm vi chạy y như
cũ, không đổi một dòng; chỉ vế **chấm công** trừ ra.

### Ba chốt đi kèm

* **Cơ sở chính không được đặt `chỉ QL`.** Cơ sở chính là cơ sở trạm **chọn sẵn** (nó đi vào thẻ
  phiên rồi thành `coSoMacDinh`), và lượt chấm không kèm ô chọn ghi thẳng vào đó. Cho phép thì ô
  xổ chọn sẵn một cơ sở không có trong danh sách, còn lượt chấm im lặng ghi vào đúng cơ sở vừa
  bị loại. Cả hai cửa ghi (`dat_ds_coso()` và biểu mẫu hồ sơ) chối, và chỉ luôn cách làm: bấm
  nút tròn **chính** cho một cơ sở người ấy CÓ chấm công.
* **Không được biến mất lặng lẽ.** Người bị loại là người mở trang trạm ra — sáu cơ sở còn hai
  thì họ tưởng hồ sơ bị sửa mất. Trạm **kể tên** mấy cơ sở "chỉ quản lý" ra, nói rõ *vẫn quản lý
  nhân viên ở đó*, và chỉ chỗ sửa nếu đặt nhầm.
* **Bảng "Hôm nay" và "Công của tôi" vẫn phủ ĐỦ cơ sở.** Chỉ `dsCoSo` (ô xổ) bị trừ.
  `VHCC_Online::ds_coso_cham_cua_nv()` là **hàm riêng**, không sửa `ds_coso_cua_nv()` — hàm kia
  còn ba đường gọi nữa và chúng ĐỌC LỊCH SỬ ("Công của tôi", `lichsu`, `thang` của trạm). Trừ ở
  đó là người vừa được đặt cờ mất mấy tháng công cũ khỏi màn hình của chính họ, im lặng.

### Câu chối phải nói đúng việc

Người bị loại vì cờ này thì hồ sơ **CÓ** tích cơ sở ấy — họ quản ở đó, họ đang đứng ở đó. Câu cũ
*"Bạn không có ở cơ sở này"* nghe như hệ thống hỏng, nên cửa ghi tách hẳn một câu riêng: cơ sở ấy
**đang đặt là CHỈ QUẢN LÝ**, và nếu nay có làm ở đây thật thì nhờ quản lý bỏ ô `chỉ QL`.

Đo bằng 44 phép trong `tools/test/test-cham-cong.php`. Mỗi cảnh đo **song song hai vế**: cái gì
biến mất (ô xổ, hàng lưới) và cái gì còn nguyên (thẻ phiên, `co_quyen_coso`, danh sách nhân sự,
lịch sử công). Lưới có **đối chứng cùng hình dạng nhưng không đặt cờ** trên cùng một lưới —
thiếu nó thì một bản vá lỡ tay bỏ sạch hàng trống vẫn xanh.

---

## 4f. "Bên app gốc có, bên bảng công không thấy" — 3.64.0 (11/09/2026)

Anh Thắng gửi hai ảnh cạnh nhau: **Dashboard của app gốc** trên `script.google.com` báo
*"THÁNG 09/2026 — ĐÃ CHẤM 5/30 NGÀY"*, còn **lưới bảng công** của web thì hàng người ấy toàn dấu
chấm — *"Bên trang chấm công lại có, bên bảng anh không thấy"*.

### Vì sao chuyện này xảy ra được: **hai cuốn sổ**

| Nơi | Ghi vào đâu |
|---|---|
| App chấm công cũ (`script.google.com`) | **Google Sheet** |
| Lưới bảng công của web | **MySQL của WordPress** |

Lượt chấm chỉ sang được bằng **đúng ba đường**:

1. **`GhiSongSongWP`** — hàng đợi + lịch mỗi phút bên app gốc. ⚠️ Nó **chỉ chép lượt đi qua
   `doPost`**, tức lượt **MÁY chấm công** đẩy lên. Người chấm bằng **trang web của app gốc** thì
   không có gì để chép.
2. **Kéo tay theo tháng** — `VHCC_Keo::keo_thang()`; trước 3.64.0 chỉ có ở wp-admin.
3. **Chấm thẳng trên trạm mới** `/cham-cong/` — ghi luôn vào MySQL, không qua sheet.

Thiếu cả ba thì bên kia có mà bên này không, **im lặng** — không màn nào nói ra.

### Nay: khối **Đối chiếu với app gốc** ngay trong màn Bảng công

Cuối màn Bảng công (đúng chỗ nhìn ra vấn đề), theo **cơ sở + tháng đang xem**, bậc **Quản lý trở
lên**:

* **Đối chiếu** — chỉ đọc, không ghi gì. Hỏi app gốc rồi đặt cạnh bảng công.
* **Nạp về những ngày còn thiếu** — ghi thật, nhưng đi qua `VHCC_Nhan::ghi_gio()` nên **giờ đã có
  ở đây KHÔNG bị đè**, và bấm lại lượt nữa cũng không sinh thêm hàng nào. Nạp xong **tự đối chiếu
  lại ngay** — bắt người ta bấm thêm một nút để biết kết quả của nút vừa bấm là để họ đoán.

### Ba loại chênh lệch, ba cách sửa — nên không gộp thành một con số

| Loại | Nghĩa là gì | Làm gì |
|---|---|---|
| **App gốc có – ở đây KHÔNG** | lượt chấm chưa sang | bấm **Nạp về** |
| **Chỉ có ở đây** | chấm trên **trạm mới** (ghi thẳng MySQL, không qua sheet) | **bình thường**, đừng "sửa" |
| **Lệch giờ** | hai bên cùng có, giờ khác nhau | xem lại từng ngày |
| **Mã bên app không có hồ sơ ở đây** | nạp về xong **vẫn không hiện trong lưới** (lưới dựng hàng theo sổ nhân sự) | lập hồ sơ đúng Mã NV đó **rồi** hãy nạp |

Vế cuối là vế dễ mất nhất: không kể riêng ra thì màn hình báo *"đã nạp N lượt"* mà lưới không đổi
gì, và người đọc tưởng phần mềm hỏng.

Một chốt nhỏ nhưng đáng nhớ: **thiếu hẳn một giờ** (bên app có giờ ra, bên này chỉ có giờ vào) xếp
vào **"thiếu"**, không vào "lệch" — nó là nửa ngày công chưa sang và sửa bằng đúng nút *Nạp về*;
xếp nhầm là người ta đi tìm ai gõ sai giờ.

25 phép thử trong `tools/test/test-cham-cong.php`, chạy trên bộ giả lập gọi mạng — kể cả chốt
*"Đối chiếu KHÔNG ghi một hàng nào"* và *"Cửa hàng trưởng gửi thẳng POST cũng không ghi được"*.

---

## 4g. Ô gõ giờ: 24 giờ, bỏ `type="time"` — 3.65.0 (11/09/2026)

Anh Thắng, ảnh hàng **Chấm công bù** với hai ô `01:37 CH` / `09:01 CH`: *"chuyển này sang 24h cho
dễ gõ"*.

### Vì sao không sửa được bằng một thuộc tính

`<input type="time">` hiện **12 giờ hay 24 giờ là do ngôn ngữ của TRÌNH DUYỆT**, không phải do
trang: Chrome không đọc thuộc tính `lang` cho ô giờ, Firefox và Safari theo hệ điều hành. Đứng từ
máy chủ **không ép được**. Muốn chắc thì phải tự cầm lấy ô.

### Nay: ô gõ thường, 24 giờ, và **gõ liền cũng được**

| Gõ | Ra |
|---|---|
| `13:37` · `13.37` · `13h37` · `13 37` | 13:37 |
| **`1337`** · **`937`** · `0830` | 13:37 · 09:37 · 08:30 |
| `8:30` | 08:30 |
| `24:00` · `13:60` · `tám rưỡi` · **`01:37 CH`** | **chối** |

Dạng 12 giờ **cố ý không nhận**: đoán `01:37` là 1 giờ sáng hay 1 giờ chiều là đoán một ca làm
việc — sai một lần là lệch tám tiếng công, và không ai nhìn ra.

Áp cho **cả sáu ô giờ** của màn: Giờ vào / Giờ ra (bù + sửa) và bốn ô khai ca. Để lẫn hai kiểu ô
trên một màn là mỗi lần gõ phải nhớ ô nào kiểu nào.

### Bỏ `type="time"` là bỏ luôn phần trình duyệt chặn gõ bậy

Nên phải thay bằng **đủ hai lớp**:

* `pattern` + `title` ngay trên ô — chặn tại chỗ, khỏi mất công gửi đi rồi mới biết sai;
* chốt ở **cửa ghi** (`VHCC_Bu::ghi()` / `sua()` / `VHCC_Ca::luu()`) — POST gửi tay cũng phải qua.

Lớp thứ hai mới là lớp quan trọng, vì `giay()` trả `null` cho **cả ô trống lẫn gõ bậy**. Trước bản
này `sua()` đã tách hai chuyện ấy (đã từng vá), còn `ghi()` thì **chưa**: gõ nhầm `8h3o` ở ô giờ
vào là nó bù **mỗi giờ ra**, màn hình báo *"Đã bù giờ ra 17:00"*, và người bù tưởng xong cả hai.
Phép thử mới dựng đúng cảnh ấy và đếm lại bảng — gỡ miếng vá ra là nó đỏ thật.

Cùng loại: `VHCC_Ca::lam_sach()` lặng lẽ bỏ mọi dòng ca đọc không được giờ. Nay `luu()` **kể tên
ca bị bỏ**, và màn hình in ra — thiếu một ca thì giờ công của cả ca ấy rơi ra ngoài mọi ca, và
không ai biết cho tới kỳ lương.

### Dấu `:` tự hiện lúc gõ — 3.66.0, và là **ngoại lệ script duy nhất**

Anh Thắng, ảnh ô đang gõ dở `130522`: *"gõ có hiện ra : luôn được không"*.

Được, nhưng phải có JavaScript — mà màn quản trị xưa nay **không một dòng script**, luật ấy từng
được giữ kể cả khi phải bỏ một tính năng khác (tính dãy đặc trưng khuôn mặt ngay lúc chọn ảnh).
Anh Thắng chốt mở ngoại lệ đúng cho việc này, 11/09/2026.

Ba điều kiện làm cho ngoại lệ này không trở thành cái khe cho khối thứ hai:

1. **Không chạy cũng không sao.** Trình duyệt chặn script, máy cũ, mạng cắt giữa chừng — ô vẫn gõ
   được và vẫn lưu được, vì luật đọc giờ nằm ở **máy chủ** (`VHCC_DB::gio_24()`). Khối này chỉ
   chèn dấu `:` cho đỡ mỏi tay.
2. **In đúng một lần, chỉ khi màn thật sự có ô giờ.** Màn dựng tám cơ sở cũng chỉ một khối; màn
   không có ô giờ thì sạch trơn như cũ.
3. **Phép thử không nới thành "được có script"** — nó đổi thành *"mọi khối script phải mang dấu
   `/*vhcc-gio24*​/`"* (`vhcc_script_la()`). Nhét một khối lạ vào là **7 phép đỏ**.

Khối chỉ nghe sự kiện `input`, chỉ đụng ô mang `data-gio24`, và **chỉ sửa khi con trỏ đang ở cuối
chuỗi** — đặt lại `value` là con trỏ nhảy về cuối, nên sửa giữa chuỗi mà bị nhảy thì mỗi lần sửa
một số phải rê chuột lại một lần.

⚠️ Khối cắt `HH:MM` ở **hai số đầu**, cố ý **không** đoán kiểu ba số như máy chủ: lúc đang gõ thì
`93` mới là hai phím đầu của `0937` hay của `9337` — không biết được. Kiểu ba số vẫn còn nguyên ở
máy chủ, cho lượt dán vào và cho máy không chạy script.

Và `gio_24()` nay nhận **cả sáu số**: `130522` → 13:05 (giây bị bỏ, vì ô này chỉ dùng tới phút).
Sáu số là dạng ô "Giờ vào" của sổ cũ nên tay quen gõ vậy — chối cả chuỗi là người ta gõ lại ba
lần rồi tưởng ô hỏng.

39 phép thử trong `tools/test/test-cham-cong.php`.

---

---

## 5. Nằm ở đâu trong mã

| Việc | Tệp |
|---|---|
| Sổ trang + luật "ai vào được trang nào" | `wordpress/vhcp-cham-cong/includes/class-vhcc-cong.php` |
| Trang `/nhan-su/` | `wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php` |
| Phép thử | `tools/test/test-cham-cong.php` (mục 60 · mục 39b cho cửa thêm người), `tools/test/kiem-noi-bo.php` |

Danh sách trang **tự dò** bằng `class_exists` + `method_exists('url')` — gỡ một plugin thì cột
của nó tự biến mất, không để lại dòng trỏ vào hư không. Số phiên bản ở chân trang đọc thẳng từ
hằng trong mã, dùng để đối chiếu sau khi cài đè bản mới.
