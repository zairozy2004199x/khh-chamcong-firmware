# Duyệt mẫu khuôn mặt — màn `Khuôn mặt` trong wp-admin

Có ở **hai nơi**, cùng một dữ liệu:

| Ở đâu | Đường vào | Ai vào được |
|---|---|---|
| **Web quản trị** (3.50.0) | `/quan-tri-cham-cong/?man=mat` — mục **🙂 Khuôn mặt** ở cột dọc | **Quản lý trở lên**, đăng nhập bằng PIN |
| wp-admin (3.49.0) | `admin.php?page=vhcc-mat` — menu **Chấm công → Khuôn mặt** | phải có tài khoản WordPress |

Bản **3.49.0** là bản đầu tiên màn này **hiện ảnh ra**; **3.50.0** đưa nó ra web.

---

## 1. Vì sao phải có ảnh

Anh Thắng, 08/09/2026: *"trên web quản trị chưa có phần duyệt khuôn mặt này (cần hiện rõ ảnh đó
ra, đây chỉ hiện duyệt hay không thôi)"*.

Chú thích ngay trên bảng đã tự nói ra định nghĩa: duyệt nghĩa là **"tôi đã xem ảnh và đúng là
người này"**. Mà màn hình lại chỉ có chữ. Nút Duyệt khi ấy chỉ còn là nút dọn hàng chờ — và
**duyệt bừa còn hại hơn không duyệt**: nó dán nhãn *"đã có người xác nhận"* lên đúng tấm mẫu có
thể là mặt người chấm hộ, rồi từ đó hệ thống gắn cờ **ngược** — người thật bị coi là giả, suốt.

---

## 2. Nay màn hình có gì

### Cột **Ảnh — xem rồi mới duyệt**, hai tấm cạnh nhau

| Tấm | Viền | Là gì |
|---|---|---|
| **Tấm đã sinh ra mẫu** | xanh | lượt chấm công đã tạo ra dãy đặc trưng này (`nguon_ngay` + `nguon_coso`) |
| **⚠️ KHÔNG phải tấm gốc** | vàng | ảnh gốc không còn (đã dọn), hoặc mẫu lấy từ ảnh thẻ nên không có lượt chấm nào — đang hiện tạm tấm chấm công **gần nhất**. **Đừng duyệt theo tấm này.** |
| **Ảnh thẻ trong hồ sơ** | xanh dương | bản đối chứng, Cửa hàng trưởng chụp lúc lập hồ sơ |

**Hai tấm chứ không phải một.** Một mình tấm mẫu chỉ trả lời được *"có phải mặt người không"*;
câu cần trả lời là *"có phải mặt ĐÚNG NGƯỜI NÀY không"* — muốn vậy phải có cái để so.

Không còn ảnh nào thì ô ấy **nói thẳng** và bảo **Xoá mẫu** (lượt chấm sau tự lấy lại), chứ không
để trống — ô trống đọc ra là *"chưa tải xong"*.

### Bảng **30 lượt lệch nhất** cũng có ảnh

Bảng ấy sinh ra để trả lời đúng một câu — *mấy lượt lệch nhất là người thật hay không* — mà câu ấy
chỉ trả lời được bằng mắt. Bản cũ đưa một đường dẫn sang Bảng chấm công rồi bắt tự dò đúng ngày,
đúng người, **ba mươi lần**. Không ai làm thế, nên ngưỡng cứ để nguyên số mặc định.

Ở đây ảnh **chỉ hiện khi đúng lượt ấy** — không có thì để chữ *"không có ảnh"*. Đưa tấm gần nhất
thay vào là cho người ta xem nhầm ảnh rồi kết luận về một lượt khác: tệ hơn hẳn ô trống.

---

## 2b. Ai được duyệt — và vì sao (3.50.0)

Anh Thắng 08/09/2026, khi em hỏi: *"QUản lý và admin duyệt"*.

Việc duyệt trước đó nằm sau cửa **wp-admin**, mà Quản lý của chuỗi đăng nhập bằng **PIN** vào
`/quan-tri-cham-cong/` — không ai phát cho họ tài khoản WordPress. Nên hàng chờ cứ dài ra, mà
**mẫu chưa duyệt vẫn được dùng để so**.

Gác bằng `VHCC_Mat::QUYEN` = **`ngoai_coso`** (bậc **Quản lý**, 3). Khai **một hằng**, cả hai màn
cùng hỏi nó.

⚠️ **Thang là thang.** "Quản lý trở lên" gồm cả **Kế toán** (bậc 4) đứng giữa. Không có cách nào
khai *"Quản lý và Admin nhưng KHÔNG Kế toán"* mà không phá thang năm bậc — và Kế toán vốn là bậc
*"full quyền ngoài admin"*, nên nằm trong là đúng ý chứ không phải nới lỏng.

🔴 **Cửa hàng trưởng (bậc 2) đứng ngoài, và đó là cả điểm của lớp này.** Họ nhận ra mặt người cơ
sở mình nhanh hơn ai hết — nhưng thứ mẫu này canh là **chấm hộ**, mà người đứng gần chuyện chấm
hộ nhất chính là người ở cửa hàng. Một lớp gác do chính người bị gác dựng lên thì không còn là
lớp gác.

Gác ở **cả hai chỗ**: lúc vẽ màn và lúc nhận POST. Chỉ gác lúc vẽ thì ai đoán ra tên `viec` là
gửi thẳng POST được — mà `mat_duyet_het` duyệt sạch cả hàng chờ trong một lượt.

---

## 3. Bốn cái bẫy trong đợt này

**1. `esc_url()` NUỐT `data:`.** Ảnh thẻ lưu dạng data URI. WordPress chỉ cho qua một danh sách
giao thức, và `data` không có trong đó — `esc_url('data:image/...')` trả về **chuỗi rỗng**. Ảnh
biến mất, không một lời báo. Data URI phải đi qua `esc_attr`, kèm phép soát khuôn.

**Bản giả trong `wp-stub.php` trước đây trả nguyên chuỗi**, nên cái bẫy này sẽ XANH trong bài kiểm
mà ĐEN ngoài đời. Nay bản giả cũng nuốt y hệt WordPress. (Chạy lại cả chín bộ sau khi đổi: không
chỗ nào khác vấp — tức là chưa từng có chỗ nào dựa vào cái sai ấy.)

**2. Data URI không phải ảnh là một đường chạy mã.** Cột `anh_the` do người dùng nạp lên. Một
`data:text/html;base64,PHNjcmlwdD4=` nhét vào `src` là script chạy trong trang quản trị. Nên soát
khuôn `^data:image/(jpeg|png|webp|gif);base64,…$`, và **chối thì phải nói ra** ("Ảnh thẻ hỏng
khuôn"), không im lặng bỏ qua.

**3. HAI LỚP GÁC HAI BẬC KHÁC NHAU = HỎNG IM LẶNG.** Bản đầu của màn web để `ngoai_coso` trong
khi `VHCC_Mat::ds()/duyet()/xoa()` vẫn gác `ho_so` (bậc Kế toán). Quản lý **mở được màn** mà bảng
thì **rỗng**, bấm Duyệt thì **bị chối** — và câu chối lại nói về *"hồ sơ nhân sự"*, một việc chẳng
liên quan. Phép thử bắt được vì nó đo **cả hai đầu**: màn có hiện không, *và* trạng thái trong sổ
có đổi thật không. Nay là **một hằng** `VHCC_Mat::QUYEN`, màn web mượn thẳng.

**4. Ảnh thẻ là LONGTEXT ~60 KB mỗi người.** `VHCC_Mat::ds()` nhận thêm tham số `$kem_anh`; lượt
chỉ đếm mã (nút *Duyệt tất cả*) gọi với `false`, không kéo về vài megabyte cho một vòng lặp đếm.

---

## 4. Nằm ở đâu trong mã

| Việc | Tệp · hàm |
|---|---|
| Lần ngược từ mẫu về tấm ảnh đã sinh ra nó | `class-vhcc-mat.php` · `anh_cua_mau()` |
| Danh sách mẫu (kèm/không kèm ảnh) | `class-vhcc-mat.php` · `ds( $u, $trang_thai, $kem_anh )` |
| Ô ảnh hai tấm trên màn duyệt | `class-vhcc-man.php` · `o_anh_mau()`, `khoi_anh_()` |
| Đường dẫn tương đối -> URL | `class-vhcc-man.php` · `url_anh_cham()` |
| Màn Khuôn mặt trên WEB | `class-vhcc-web-mat.php` (`VHCC_WebMat`) |
| Bậc được duyệt — khai một chỗ | `class-vhcc-mat.php` · hằng `QUYEN` |
| Phép thử wp-admin (17 phép) | `tools/test/kiem-mat.php` mục **13** |
| Phép thử màn web (23 phép) | `tools/test/test-cham-cong.php`, cuối tệp |

⚠️ Hàm đổi đường dẫn ảnh -> URL có **ba bản** — `VHCC_Man`, `VHCC_Web`, `VHCC_WebMat` — vì ba lớp
không gọi chéo được vào hàm private của nhau. Đổi cách lưu ảnh chấm công thì phải sửa **cả ba**.

⚠️ Màn này **không có `<script>`**; mọi thứ là HTML + CSS nội tuyến.
