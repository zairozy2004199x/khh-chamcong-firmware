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

## 5. Nằm ở đâu trong mã

| Việc | Tệp |
|---|---|
| Sổ trang + luật "ai vào được trang nào" | `wordpress/vhcp-cham-cong/includes/class-vhcc-cong.php` |
| Trang `/nhan-su/` | `wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php` |
| Phép thử | `tools/test/test-cham-cong.php` (mục 60 · mục 39b cho cửa thêm người), `tools/test/kiem-noi-bo.php` |

Danh sách trang **tự dò** bằng `class_exists` + `method_exists('url')` — gỡ một plugin thì cột
của nó tự biến mất, không để lại dòng trỏ vào hư không. Số phiên bản ở chân trang đọc thẳng từ
hằng trong mã, dùng để đối chiếu sau khi cài đè bản mới.
