# Chuyển máy chấm công về chạy thẳng trên host

*Bản 22/08/2026 — plugin Chấm Công 2.0.0, firmware 2026-08-22a*

Anh Thắng chốt: **cả hệ thống chạy trực tiếp trên host, kể cả đường máy chấm công**, và
**không liên quan gì tới Google Sheet nữa**. Tài liệu này ghi lại việc đó đã đổi cái gì, và
làm theo thứ tự nào để không mất máy nào.

---

## 1. Trước và sau

Trước đây máy chấm công nói chuyện với **ba** nơi:

| việc | trước | nay |
|---|---|---|
| đẩy lượt chấm công | Apps Script `/exec` → sheet | website `/cham-cong-may` → MySQL |
| nhận lệnh (thêm/xoá NV, quét, tải lại) | Firebase `/queue/<tên máy>` | website, việc `lenh` |
| báo đã xử lý xong | xoá node trên Firebase | website, việc `xong` |
| nhịp sống (máy còn online không) | Firebase `/hb/<tên máy>` | website, việc `nhip` |
| cập nhật firmware | Firebase `/ota` | nhịp sống chở về `otaVer`/`otaUrl` |
| sổ mặt trong đầu đọc | Firebase `/roster/<tên máy>` | website, việc `roster` |
| ảnh khuôn mặt của lệnh thêm NV | Firebase `/photo/…` | website, việc `anh_lenh` |
| ảnh trích theo yêu cầu | Firebase `/photoresp/…` | website, việc `anh_tra` |
| cờ dừng tải lại | Firebase `/stop/<tên máy>` | website, việc `dung` |
| "máy này thuộc cơ sở nào" | Firebase `/may/<mac>` hoặc `?whoami` | website, việc `toi_la_ai` |

Nay **một** địa chỉ, **một** khoá, **một** cách hiểu chữ "xong".

### Được gì

- **Không còn khoá Firebase trong firmware.** Khoá đó có quyền admin: ai cầm được nó là đẩy
  được firmware tuỳ ý vào cả 26 máy. Đây là bí mật nặng nhất của hệ thống cũ, và nay nó không
  còn nằm trong bản .bin, không còn trong NVS, không còn trong `secrets.h`.
- **Hết cảnh ba nơi trả lời khác nhau** câu "máy này thuộc cơ sở nào".
- **Hàng đợi khoá theo SERIAL đầu đọc, không theo tên máy tự khai.** Firebase khoá theo
  `STATION_NAME` — tên gõ tay ở portal 192.168.4.1 — nên hai máy đặt trùng tên là ăn chung hàng
  đợi: lệnh thêm nhân viên của cửa hàng này chạy sang máy cửa hàng kia, không có gì báo.
- **Lệnh đã gửi mà chưa báo xong thì được gửi lại.** Firebase không có khái niệm "đã gửi": máy
  đọc xong phải tự xoá, mà xoá hỏng trên 4G là chuyện thường — lệnh nằm lại ở đầu hàng và chặn
  sạch phía sau. Đúng lỗi đã làm mất cả tối 03/08/2026.
- **Mất mạng Google thì hệ thống vẫn chạy.**

### Mất gì — nói thẳng

Website thành **điểm chết duy nhất**. Host sập là máy không nhận lệnh, không OTA được.

Nhưng **lượt chấm công không mất**: nó nằm trong sổ của đầu đọc Hikvision, lấy lại được bằng
lệnh **Tải lại** trên màn Máy sau khi host sống. Và trước đây Apps Script sập cũng đã mất rồi,
nên đây không phải một điểm chết mới.

---

## 2. 🔴 THỨ TỰ DI TRÚ — LÀM ĐÚNG THỨ TỰ, KHÔNG ĐẢO

Firmware mới **không đọc Firebase nữa**, kể cả `/cfg/wp` — chỗ trước đây máy tự nhận link + khoá
WordPress. Nên nếu đẩy firmware trước khi máy kịp có link trong NVS thì máy lên bản mới mà
**không còn đường nào để hỏi**: phải tới tận cửa hàng, mở portal 192.168.4.1 và gõ tay.

Anh Thắng đã biết và chấp nhận rủi ro này (*"nếu máy mất liên kết thì đợi nạp lại ota thôi"*),
nhưng làm đúng thứ tự thì không phải dùng tới.

**Bước 1 — website sẵn sàng.**
- Cài `vhcp-cham-cong.zip` (2.4.1 trở lên), Kích hoạt (tạo/nới 23 bảng).
- **Đăng nhập lần đầu — PIN nằm ở DỮ LIỆU CŨ, không phải cấp lại.** Vào **wp-admin → Chấm
  Công → Cài đặt**. Lúc cài, nếu chưa ai đăng nhập được, plugin đi tìm sổ PIN cũ trước:
  1. **Nạp sổ Phân quyền của app gốc** (nếu đã kéo về) sang *danh sách riêng*, **giữ nguyên
     PIN mọi người đang dùng**. Màn Cài đặt kể lại đã nạp bao nhiêu người.
  2. **Chỉ khi không tìm được sổ nào** mới khai tạm một tài khoản **Admin** với PIN 6 số ngẫu
     nhiên, in ở đúng trang đó, **đúng một lần**. Ghi lại → bấm *"Tôi đã ghi lại — ẩn đi"* →
     vào được rồi thì nạp sổ PIN cũ ở mục 📥 bên dưới.
  - PIN **không bao giờ in ra trang `/cham-cong`** — chỉ wp-admin thấy được.
  - Plugin cũng chuyển **Nguồn người dùng** sang *danh sách riêng*. Trang Cài đặt nói rõ việc
    này; danh sách cũ **không mất gì**, chọn lại ô đó là quay về.
  - **Không** tự kéo người từ plugin Vận Hành Chi Phí — hai hệ thống tách nhau. Muốn nạp thì
    bấm nút ở mục 📥.
  - Chạy **đúng một lần**. Xoá tài khoản đó đi là chết hẳn, nâng cấp sau không mọc lại; và nếu
    đổi nguồn ngược lại thì lần nâng cấp sau không lật lại lựa chọn đó.

- **🌐 Trang quản trị NGOÀI WEB — `https://<tên miền>/quan-tri-cham-cong`.** Anh Thắng:
  *"mọi việc anh thao tác trên web giao diện bên ngoài hết, không làm bên trong wp-admin"*.
  Đăng nhập bằng **PIN chấm công**, không cần tài khoản WordPress.
  - **Lần đầu chưa ai có PIN?** Mở trang này trong trình duyệt **đang đăng nhập wp-admin** →
    bấm **"Vào bằng tài khoản WordPress"**. Quyền `manage_options` sửa được cả website và đọc
    thẳng được bảng người dùng, nên nó đã cao hơn một PIN Admin — đi vòng qua PIN không thêm
    lớp an toàn nào, chỉ tạo một vòng tròn không lối ra. Vào rồi thì bấm **Khai Admin** để có
    PIN dùng lâu dài. Chỉ **Admin** và **Quản lý**
  vào được — hồ sơ có CCCD, số tài khoản, lương. Làm được:
  - **Nạp .csv hồ sơ nhân viên**, kèm **Xem trước** liệt kê **từng ô: *đang là* → *sẽ thành***.
    Đọc sai cột là thấy ngay ở bảng đó, **trước khi** ghi đè. Luôn bấm Xem trước trước.
  - **↩ Hoàn tác** lượt nạp gần nhất — trả từng ô về giá trị cũ, xoá người mới thêm. Chỉ lùi
    được **một** bước, và chỉ trả lại những ô lượt nạp đó động vào.
  - **Sửa hồ sơ ngay trên bảng.** Mã NV cố ý không sửa được — đổi mã là sửa mọi hàng chấm công
    đã có của người đó.
  - **Khai tài khoản Admin toàn quyền** (chỉ Admin). PIN 6 số ngẫu nhiên, hiện **đúng một lần**,
    không lưu chỗ nào để in lại.
  - **🗑 Xoá sạch hồ sơ** để nạp lại từ đầu (chỉ Admin, phải gõ `XOA HET`). **Không hoàn tác
    được.** Lượt chấm công, bảng lương, lịch làm **không** bị xoá — chúng gắn theo Mã NV, nạp
    lại hồ sơ đúng mã là khớp lại như cũ.
  - Trang **không in PIN** của ai ra, chỉ cho biết có mấy chữ số; và chặn công cụ tìm kiếm.

- **📥 Nạp người dùng từ dữ liệu cũ** (wp-admin → Chấm Công → Cài đặt, khi đang dùng *danh
  sách riêng*). Hai đường, đều có **Xem trước** trước khi ghi:
  - **Tải file `.csv` của sổ NHÂN VIÊN — lấy ĐỦ mọi cột.** Google Sheets → **File → Tải xuống
    → Giá trị được phân tách bằng dấu phẩy (.csv)** → tải lên. Nhận đủ *Mã NV · Họ tên · Cửa
    hàng · Trạng thái đồng bộ · Cập nhật · CCCD · Chức vụ · Nhiệm vụ · Cơ sở phụ · PIN đăng
    nhập* và mọi cột hồ sơ khác; ghi thẳng vào hồ sơ **Nhân sự**. Thứ tự cột nào cũng được;
    cột không nhận ra được **kể tên ra**, không im lặng bỏ. Nạp xong bấm nạp từ **Hồ sơ Nhân
    sự** để cột *PIN đăng nhập* thành tài khoản đăng nhập.
    - 🔴 **Ô trống KHÔNG ghi đè.** Sheet đang thu gọn nhiều nhóm cột — xuất thiếu cột rồi nạp
      đè thì ô trống *không* xoá mất số tài khoản, lương, CCCD đang có. Khớp theo **Mã NV** nên
      nạp lại là cập nhật, không nhân đôi.
    - Ô có dấu phẩy bên trong (`"FARM_PT, FZ_LTVT"`) đọc đúng, không làm lệch cột.
  - **Dán thẳng từ Google Sheets** — chỉ tài khoản đăng nhập: bôi đen cột **Họ tên** và **PIN**
    (kèm **Vai trò**, **Cơ sở** nếu có) của **một cơ sở** → Ctrl+C → dán vào ô. Thứ tự cột nào
    cũng được, có hay không có dòng tiêu đề đều được.
  - **Nạp từ kho đã có trên host** — hồ sơ **Nhân sự** (chỗ file `.csv` đổ vào), sổ Phân quyền
    đã kéo về, hoặc bảng của plugin chi phí; chọn được **riêng từng cơ sở**, mỗi cơ sở hiện sẵn
    số người và số PIN dùng được.
  - ⚠️ Sổ nhân viên ghi cột **Chức vụ** là *"Máy tự động"* — đó là chức vụ, **không phải vai
    trò đăng nhập**. Nên có ô **Vai trò nếu sổ không ghi**: để mặc thì cả sổ thành *Nhân viên*
    và **không ai đăng nhập được**. Màn hình đếm và kêu lên số dòng rơi vào trường hợp này.
  - Chỉ **thêm**, không sửa và không xoá ai — bấm hai lần không nhân đôi danh sách.
  - **Dòng hỏng được kêu đích danh** (PIN sai khuôn, chưa có PIN, trùng PIN với người khác).
    Nạp 26 cửa hàng mà im lặng bỏ 4 người thì cuối tháng 4 người đó không có công.
  - PIN dễ đoán / đã lộ thì **vẫn nạp** (chặn là khoá đúng người đang dùng nó ra ngoài) nhưng
    được **kêu tên ra để đổi sớm**.
  - ⚠️ Google Sheets coi PIN là **số**: `0123` bị cắt thành `123`, `246813` có thể ra
    `246813.0`. Đuôi `.0` hệ thống tự cắt; số 0 ở đầu bị mất thì phải định dạng cột đó thành
    **Văn bản** trong Sheet rồi chép lại.
- Mở thử `https://<tên miền>/cham-cong-may` bằng trình duyệt: phải ra
  `{"status":"ERROR","message":"Cong nay chi nhan POST."}` với mã 405. Ra trang 404 của
  WordPress là **luật đường dẫn chưa nạp** — vào Cài đặt → Đường dẫn tĩnh bấm Lưu một lần.
- Vietnix: xin loại trừ `/cham-cong-may` khỏi Imunify360/ModSecurity, và loại trừ khỏi
  LiteSpeed Cache. Cổng này nhận POST liên tục từ 26 máy — tường lửa chặn là mất chấm công cả
  chuỗi mà log web trông vẫn bình thường.

**Bước 2 — đẩy link + khoá xuống máy, BẰNG ĐƯỜNG CŨ.**
- Trên Firebase, đặt `/cfg/wp` = `{"url":"https://<tên miền>/cham-cong-may","key":"<VHCC_KHOA_MAY>"}`.
- Chờ ~5 phút (máy đọc mỗi 5 phút).

**Bước 3 — kiểm rằng máy đã tới được website.**
- Vào **Chấm Công → Máy chấm công**: máy phải hiện ra trong danh sách.
- Hoặc bấm mặt thử một cái ở một cửa hàng rồi xem bảng chấm công có lượt đó không.
- **Chưa thấy máy nào thì DỪNG.** Đẩy firmware lúc này là mất máy đó.

**Bước 4 — thử firmware mới trên MỘT máy.**
- Màn Máy → mục Cập nhật firmware → điền phiên bản + link `.bin` raw của nhánh `bin` →
  chọn một máy → **Đặt riêng cho máy này**.
- Chờ 2-3 phút. Máy đó phải: lên đúng bản mới ở cột "Bản firmware", vẫn gửi nhịp (🟢), và vẫn
  đẩy được lượt chấm công.
- **Chưa xanh thì DỪNG.** Bản hỏng đẩy cho cả chuỗi thì không còn đường gọi về, và cách sửa duy
  nhất là mang USB đi 26 cửa hàng.

**Bước 5 — đẩy cả chuỗi.**
- Cùng màn đó, gõ `DONG Y` vào ô xác nhận → **Đẩy cập nhật cho cả chuỗi**.
- Theo dõi dòng "Máy đang chạy" trong vài giờ. Máy nào còn lệch bản sau vài giờ là máy đó không
  tải được: xem lại SIM của nó.

**Bước 6 — dọn.**
- Sau khi cả chuỗi lên bản mới và chạy ổn vài ngày: xoá `/cfg/wp`, `/queue`, `/hb`, `/ota`,
  `/roster`, `/photo`, `/stop` trên Firebase, rồi **vô hiệu database secret**.
- Bốn hàm còn lại trong `CC_CHO_PHEP` (`getEmployees`, `ccDsCoSoXuat`, `ccXuatChamCong`,
  `ccXuatPhanQuyen`) chỉ để nạp dữ liệu cũ từ Sheet. Nạp xong thì xoá luôn, và cầu nối không
  còn lý do tồn tại.

---

## 3. Link `.bin` — chỗ sai một lần là đi 26 cửa hàng

Module 4G A7680C chết ở khoảng **532 ký tự URL**. Link *release* của GitHub trả HTTP 302 rồi
chuyển hướng dài **~943 ký tự** — đẩy link đó là **mọi máy 4G không bao giờ tải được bản mới**,
tức mất luôn đường sửa từ xa.

Phải dùng link **raw của nhánh `bin`** (`raw.githubusercontent.com/…/bin/….bin`) — nó trả 200
thẳng. Hệ thống chặn sẵn link sai dạng, nhưng đây là lớp gác **duy nhất** còn lại (trước đây còn
Apps Script kiểm quyền admin ở giữa), nên đừng nới nó.

---

## 4. Máy mới / chip trắng

Bản `.bin` do CI build **không chứa link và khoá nào** (cố ý — nó nằm ở chỗ tải công khai). Máy
mới lấy cấu hình theo thứ tự: **NVS → `secrets.h` (nạp USB) → portal 192.168.4.1**.

Ở portal chỉ còn hai ô của đường truyền:

- **Link website**: `https://<tên miền>/cham-cong-may` — **không có dấu `/` ở cuối**. Máy không
  đi theo chuyển hướng; WordPress chuyển hướng để bỏ dấu gạch là máy gọi lại bằng GET và mất
  trọn thân POST, tức mất lượt chấm công, mà log có thể vẫn trông như thành công.
- **Khoá máy**: đúng bằng `VHCC_KHOA_MAY` trong `wp-config.php`.

Bốn ô cũ (link web app, link Firebase, token web app, Firebase secret) đã gỡ khỏi portal và khỏi
thẻ `token.txt` của máy thợ nạp. Thẻ cũ mang mấy khoá đó thì máy thợ nạp **báo dòng bị loại** —
cố ý, chứ bỏ qua lặng lẽ thì nhân viên cầm thẻ cũ đi nạp cả ngày mà không có gì tới máy.

---

## 5. Màn Máy chấm công có gì mới

- **Máy mất nhịp để trên cùng.** Quá 5 phút không có nhịp là đứt — cửa hàng đó đang không chấm
  công lên được mà không ai biết. Lượt bấm vẫn nằm trong đầu đọc, lấy lại bằng **Tải lại**.
- **Gán cơ sở cho máy thì lượt bấm đang chờ tự vào bảng chấm công.** Trước phải soi lại tay.
- **Sổ mặt trong đầu đọc**: bấm *Quét sổ máy*, chờ một phút, rồi xem đối chiếu với hồ sơ.
  Đây là phép đáng giá nhất của màn này: **người nghỉ việc mà mặt còn trong máy thì vẫn chấm
  công được**, và bảng lương vẫn tính — không có gì tự báo.
- **Hàng đợi lệnh** hiện ngay trên màn: lệnh nào đang chờ, lệnh nào đã xuống máy lúc mấy giờ.
- **Tải lại** chặn khoảng rộng quá 31 ngày: máy phải đẩy từng lượt qua 4G nên khoảng rộng làm
  nghẽn cả đường truyền hàng giờ.
- **OTA đặt riêng cho một máy** — bước nên làm trước mỗi lần đẩy cả chuỗi.

## 6. Cái đã gỡ, và vì sao không tiếc

| gỡ | vì sao |
|---|---|
| Đối chiếu sheet `MayChamCong` ↔ bảng `may` | Sinh ra để canh ca "một lượt bấm rơi vào hai cơ sở" hồi ghi song song. Nay chỉ còn một nơi trả lời, không còn hai bên để lệch. Giữ một nút không còn gì để đối chiếu là mời người ta tin nhầm. |
| `xemKhoiTest` / `donKhoiTest` | Dọn khối tháng tên "test" **trong sheet** do gói thử đường truyền tạo ra. Không còn sheet, và cổng nhận đã chặn gói `TEST4G` từ trước khi ghi. |
| `getLuongMayTuDong` / `getGiaMayTuDong` / `setGiaMayTuDong` | Của giao diện gốc trên Apps Script; WordPress chưa bao giờ gọi. |
| `getFwMoiNhat` (hỏi GitHub có bản nào mới) | Bắt máy chủ gọi ra ngoài mỗi lần mở màn. Câu hữu ích hơn nằm ngay trong nhà: **máy đang chạy bản nào** — đó mới là thứ cho biết lượt OTA vừa rồi tới được bao nhiêu máy. |
