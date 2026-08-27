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

## 5. Nằm ở đâu trong mã

| Việc | Tệp |
|---|---|
| Sổ trang + luật "ai vào được trang nào" | `wordpress/vhcp-cham-cong/includes/class-vhcc-cong.php` |
| Trang `/nhan-su/` | `wordpress/vhcp-cham-cong/includes/class-vhcc-trang-ns.php` |
| Phép thử | `tools/test/test-cham-cong.php` (mục 60), `tools/test/kiem-noi-bo.php` |

Danh sách trang **tự dò** bằng `class_exists` + `method_exists('url')` — gỡ một plugin thì cột
của nó tự biến mất, không để lại dòng trỏ vào hư không. Số phiên bản ở chân trang đọc thẳng từ
hằng trong mã, dùng để đối chiếu sau khi cài đè bản mới.
