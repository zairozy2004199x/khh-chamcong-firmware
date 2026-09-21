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

## 2c. Lấy ảnh chấm công làm ẢNH THẺ (3.52.0)

Anh Thắng 09/09/2026, đứng trước danh sách **29 người ở một cơ sở chưa có ảnh thẻ**: *"tài khoản
có tính năng chấm công online, và có hệ thống nhận diện khuôn mặt, vậy lấy ảnh nhận diện chuẩn
đặt để đẩy vào đây luôn được không"*.

Anh đúng: chính mấy người ấy đã tự chụp mặt mình cả chục lần rồi, mỗi lượt chấm công một tấm —
mà cột ảnh thẻ vẫn trắng và ai đó phải đi chụp lại từng người.

### Chọn tấm nào — theo SỐ ĐO, không theo ngày mới nhất

Ảnh chấm công là ảnh chụp tại chỗ: có tấm đeo khẩu trang, có tấm đội mũ bảo hiểm, có tấm ngược
nắng (mở bảng *30 lượt lệch nhất* mà xem — quá nửa là mấy tấm đó). Lấy tấm mới nhất là **gặp gì
lấy nấy**.

Nhưng hệ thống đã có sẵn một thước đo cho đúng việc này: **khoảng cách `d`** giữa tấm ấy và mẫu
của người đó. `d` **nhỏ** nghĩa là khuôn mặt hiện rõ và giống hệt mọi lượt khác — tức là **không
khẩu trang, không mũ, đủ sáng, nhìn thẳng**. Nên chọn tấm `d` nhỏ nhất chính là chọn tấm rõ mặt
nhất, **mà không cần biết gì về khẩu trang hay mũ**.

### Ba chốt

🔴 **Chỉ lấy lượt kết luận `khop` và KHÔNG bị gắn cờ.** Đây là chốt chống **chấm hộ**: nếu một
lượt là mặt người khác thì `d` lớn và kết luận là `lech` — lấy đúng tấm ấy làm ảnh thẻ là **dán
mặt người chấm hộ lên hồ sơ nạn nhân**, rồi từ đó máy chấm công nhận nhầm suốt. Phép thử dựng
hẳn một hàng `lech` mang `d` **nhỏ nhất** để chốt này có răng.

⚠️ **Không đè ảnh thẻ đang có.** Ảnh chụp tử tế tốt hơn hẳn ảnh chấm công tại chỗ; đè lên là thay
thứ tốt bằng thứ tạm, một cách im lặng.

⚠️ **Luôn xem trước rồi mới lưu.** Ảnh hiện ngay cạnh nút, không giấu sau một cú bấm. Máy chọn
giúp, nhưng thứ đi vào hồ sơ thì phải có người nhìn qua.

Không có lượt `khop` nào thì **trả rỗng**, không hạ chuẩn xuống `kho_noi`: người mới chỉ có đúng
một lượt (lượt ấy thành mẫu, chưa có gì để so) thì thà không đề xuất còn hơn đề xuất một tấm chưa
ai đối chiếu với cái gì.

### Và đẩy thẳng xuống máy chấm công (3.53.0)

Anh Thắng, ngay sau đó: *"và ảnh đó đẩy xuống máy chấm công luôn"*. Đúng — cả điểm của việc lấy
ảnh là để **máy** nhận được mặt; dừng ở chỗ ghi vào hồ sơ thì vẫn phải gọi từng người ra đứng
trước đầu đọc, tức là chưa giải quyết được gì.

Cùng lượt bấm: ghi vào `nhan_vien.anh_the` **và** đặt lệnh `add` xuống mọi máy của cơ sở, ảnh đi
kèm lệnh (`anh_b64`). Cùng đường với nút *Tải ảnh thẻ lên* vốn có — không dựng đường thứ hai.

⚠️ **Cơ sở chưa gắn máy nào thì VẪN lưu ảnh**, và nói thẳng là chưa có máy. Ảnh thẻ còn dùng cho
chấm công online và cho việc đối chiếu, không chỉ cho máy — chối cả việc lưu là mất luôn phần
dùng được.

⚠️ Phép thử đo **lệnh thật trong hàng đợi**, không đo câu chữ báo về: câu báo có thể nói *"đã đặt
lệnh"* trong khi hàng đợi trống, và đó đúng là loại hỏng im lặng không ai phát hiện.

### 🔴 Ảnh CHƯA TỪNG xuống tới đầu đọc — hai lỗi trên cùng một đường (3.54.0)

Anh Thắng hỏi: *"vậy firmware máy chấm công Hik và ESP32 hiện cho đẩy xuống được chưa"*.

Firmware **có đủ đường**: `fetchPhotoDecoded()` → giải mã base64 → `faceUpload()` → ISAPI
`FDLib/FaceDataRecord`. Nhưng dựng lại **đúng chuỗi byte máy chủ trả về** thì lộ ra **hai chỗ
gãy, độc lập nhau**, và cả hai đều nằm ở **máy chủ**:

| # | Lỗi | Vì sao chết |
|---|---|---|
| 1 | `queue.anh_b64` giữ **data URI** `data:image/jpeg;base64,…` | bộ giải mã bám mốc `anh":"` rồi coi **mọi** ký tự sau đó là base64. Ký tự thứ năm là dấu **`:`** — không có trong bảng base64 |
| 2 | `json_encode` đổi `/` thành `\/` | base64 của một tấm JPEG gần như luôn mở đầu bằng **`/9j/`** → gặp `\` là ký tự lạ |

Cả hai đều cho cùng một kết cục: `fetchPhotoDecoded` trả **-3** → `processOp` trả false → **người
VẪN được ghi vào đầu đọc nhưng KHÔNG có khuôn mặt**, nên họ vẫn phải ra máy đứng chụp lại — đúng
cái việc tính năng này sinh ra để bỏ. Máy báo về `HONG photo fetch fail(-3)`, mà dòng ấy chỉ nằm
trong nhật ký của lệnh chứ không nổi lên đâu cả.

**Sửa ở máy chủ, không sửa firmware:** `VHCC_MayCong::b64_tron()` cắt tiền tố lúc **ĐỌC** (nên
mấy lệnh đang nằm sẵn trong hàng đợi cũng được cứu), và `VHCC_Nhan::tra()` đáp bằng
`JSON_UNESCAPED_SLASHES`. **Mọi máy ngoài cửa hàng khỏi phải nạp lại firmware.**

⚠️ **Phép thử dựng lại NGUYÊN bộ giải mã của firmware** (bám mốc, bảng base64, dừng ở `"`/`=`) và
chạy nó trên thân JSON thật, chứ không đo từng mảnh — sửa một lỗi mà quên lỗi kia thì ảnh vẫn
không xuống được, mà đo từng mảnh thì cả hai đều "xanh". Kèm **hai phép đối chứng** chứng minh
từng lỗi thật sự làm firmware chết.

⚠️ Bẫy kèm theo: bản giả `wp_json_encode()` trong `wp-stub.php` **nuốt mất tham số `$options`**,
nên cờ `JSON_UNESCAPED_SLASHES` không có tác dụng trong bài kiểm — phép thử xanh mà máy vẫn không
nhận được ảnh. Cùng loại bẫy với `esc_url` nuốt `data:`. Nay bản giả nhận đủ tham số.

### Nằm ở đâu

| Việc | Tệp · hàm |
|---|---|
| Chọn tấm chuẩn nhất | `class-vhcc-mat.php` · `anh_chuan_cho()`, `co_tep_anh()`, `tep_anh()` |
| Đẩy xuống máy (ảnh đi kèm lệnh) | `class-vhcc-nhan-su.php` · `day_ho_so_moi_len_may()` → `lenh_may_()` |
| Cắt tiền tố data URI trước khi gửi máy | `class-vhcc-may-cong.php` · `b64_tron()`, `anh_cua_lenh()` |
| Cổng máy đáp không escape dấu `/` | `class-vhcc-nhan.php` · `tra()` |
| Bộ giải mã phía firmware | `esp32_hik_chamcong_full.ino` · `AnhGiaiMa`, `fetchPhotoDecoded()` |
| Ghi vào hồ sơ (cửa hẹp, chỉ cột `anh_the`) | `class-vhcc-nhan-su.php` · `anh_the_tu_cham()` |
| Lõi rửa ảnh dùng chung | `class-vhcc-nhan-su.php` · `rua_anh_tep()` |
| Khối đề xuất + nút | `class-vhcc-web.php` · `khoi_thieu_anh()` |
| Việc POST (một người · cả cơ sở) | `class-vhcc-web.php` · `anh_the_tu_cham`, `anh_the_tu_cham_het` |

---

## 3. Năm cái bẫy trong đợt này

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

**4. `ve_bao()` NUỐT MẤT DANH SÁCH BỎ QUA.** Nhánh `xong` `return` ngay sau dòng xanh, nên một
việc chạy hàng loạt trả kèm `boQua` là mất sạch phần ấy — người đọc thấy *"Đã lấy ảnh thẻ cho 12
người"* và tưởng xong hết, trong khi 17 người kia vẫn trắng ảnh mà không ai nói vì sao. Nay nhánh
chung cũng in nó. **Số bỏ qua phải luôn kèm lý do từng người.**

**5. Ảnh thẻ là LONGTEXT ~60 KB mỗi người.** `VHCC_Mat::ds()` nhận thêm tham số `$kem_anh`; lượt
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
