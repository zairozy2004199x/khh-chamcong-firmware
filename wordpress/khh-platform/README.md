# Nền tảng K&H — plugin WordPress

Nền tảng quản trị nội bộ 16 ứng dụng chạy ngay trong WordPress.
Không cần build, không phụ thuộc thư viện ngoài: PHP + HTML + CSS + JavaScript thuần.

## Cài đặt

Trang quản trị → **Plugin → Cài mới → Tải plugin lên** → chọn `khh-platform.zip` → **Kích hoạt**.

Khi kích hoạt, plugin tạo bảng `{prefix}khh_docs` và nạp 117 bản ghi ví dụ (8 nhân sự, 3 dự án,
21 công việc, bảng công tháng 9, 2 kỳ lương, đề xuất, quy trình, thông báo, chat…).
Xoá sạch dữ liệu ví dụ bằng nút **Xoá hết** ở ô vàng trong ứng dụng Công việc.

## Dùng ở đâu

| Cách | Đường dẫn |
|---|---|
| Trang quản trị | Menu **Nền tảng K&H** |
| Toàn màn hình | `https://site.com/?khh_app=1` |
| Nhúng vào trang | shortcode `[khh_platform]` hoặc `[khh_platform height="900px"]` |

Trang toàn màn hình và shortcode đều bắt buộc đăng nhập.

## Tạo tài khoản đăng nhập

### Cách 1 — Tên đăng nhập + mật khẩu (dùng chính thức)

**Nền tảng K&H → Cấp tài khoản**. Đây là cách dùng khi chạy thật, hợp với nhân viên cơ sở
không có email công ty và với hosting không gửi được thư.

- **Hàng loạt**: tích chọn nhiều hồ sơ → *Cấp tài khoản hàng loạt*. Tên đăng nhập lấy từ
  **mã nhân sự**; mật khẩu 10 ký tự do máy sinh, đã bỏ các chữ dễ đọc nhầm (0/O, 1/l/I) để
  đọc qua điện thoại không sai. Mỗi lượt tối đa 300 người.
- **Từng người**: tự đặt tên đăng nhập và mật khẩu (tối thiểu 8 ký tự).
- Mật khẩu **chỉ hiện một lần**, kèm nút in danh sách để phát. WordPress chỉ lưu bản băm,
  không ai xem lại được mật khẩu cũ — quên thì bấm *Đặt lại mật khẩu*.
- Trùng tên thì tự thêm số: `nguyenthibaomai`, `nguyenthibaomai2`.

Nhân viên đăng nhập ở `wp-login.php` rồi mở `?khh_app=1`. Nhắc họ đổi mật khẩu sau lần đầu.

### Cách 2 — Mã PIN, mỗi người một mã (để chạy thử)

**Nền tảng K&H → Mã PIN đăng nhập**. Hai cách tạo:

- **Hàng loạt**: tích chọn nhiều tài khoản → *Tạo mã cho những người đã chọn* → hệ thống sinh
  mã 6 số cho từng người và **hiện một lần** trong bảng để gửi đi.
- **Từng người**: chọn tài khoản, tự đặt mã 4–8 chữ số.

Người dùng mở `https://site.com/?khh_pin=1`, nhập mã của mình — hệ thống tự biết là ai.
Mỗi tài khoản giữ một mã; hai người không được trùng mã.

Đây là **cửa vào phụ, yếu hơn mật khẩu** nên luôn có chốt chặn: chỉ Quản trị viên tạo được,
mã lưu dạng băm (không xem lại được), bắt buộc có hạn, sai 5 lần khoá 15 phút theo IP,
phiên đăng nhập là phiên tạm (đóng trình duyệt là hết). Thử xong bấm **Xoá hết mã**.

### Đăng nhập thử với tư cách người khác

**Nền tảng K&H → Đăng nhập thử**: Quản trị viên bấm một nút là vào thẳng nền tảng với tư cách
người đó, để xem họ thấy gì và bị chặn ở đâu. Trong lúc thử luôn có thanh cam ở đáy màn hình
kèm nút quay lại. Chức năng này không hỏi mật khẩu của người kia, chỉ Quản trị viên dùng được,
và cookie ghi "người thật" được ký bằng khoá bí mật của WordPress nên không giả mạo được.

**Nhiều người đăng nhập cùng lúc trên một máy:** mỗi trình duyệt chỉ giữ một phiên — mở thêm
cửa sổ ẩn danh hoặc trình duyệt khác rồi nhập mã PIN của người kia.

### Cách 3 — Tài khoản WordPress thường

**Nền tảng K&H → Tài khoản**: bảng liệt kê từng hồ sơ nhân sự và tài khoản tương ứng.
Hồ sơ nào chưa có tài khoản thì điền email rồi bấm **Tạo tài khoản** — WordPress gửi thư
đặt mật khẩu tới email đó. Cùng trang còn có nút **Tạo hồ sơ nhân sự** cho tài khoản
WordPress chưa gắn hồ sơ, và **Gửi lại thư đặt mật khẩu**.

Điều kiện để mọi thứ khớp: **tên hiển thị của tài khoản WordPress phải trùng tên trong hồ sơ nhân sự** —
app dùng tên này để lọc “việc của tôi”, phiếu lương và người duyệt. Nút *Tạo tài khoản* tự đặt đúng tên;
chỉ khi tạo tay trong *Người dùng → Thêm mới* mới phải tự điền Display name cho khớp.

### Cách 4 — Đăng nhập bằng Google (tuỳ chọn, mặc định TẮT)

1. **Google Cloud Console** → tạo project → **APIs & Services → Credentials → Create credentials →
   OAuth client ID → Web application**.
2. Điền hai ô (lấy đúng địa chỉ hiện trong **Nền tảng K&H → Cấu hình**):
   - *Authorized JavaScript origins*: `https://site-cua-ban.com`
   - *Authorized redirect URIs*: `https://site-cua-ban.com/?khh_google=1`
3. Copy **Client ID** dán vào **Nền tảng K&H → Cấu hình**, điền tên miền email được phép
   (vd `khh.vn`), bật *Chỉ cho đăng nhập khi email đã có trong hồ sơ nhân sự*, rồi Lưu.
4. Trong ứng dụng **Hồ sơ nhân sự**, mỗi người phải có **đúng email Google** của họ và vai trò.
5. Người dùng mở `https://site-cua-ban.com/?khh_app=1` → bị đưa về trang đăng nhập →
   bấm **Sign in with Google**. Tài khoản WordPress tự tạo, tự gắn vào hồ sơ nhân sự,
   tự nhận vai trò ghi trong hồ sơ.

Google chỉ chấp nhận địa chỉ **https** (trừ `http://localhost`). Bỏ trống Client ID là tắt hẳn cách này.

### Vai trò WordPress riêng

Plugin tạo ba vai trò chỉ có quyền đọc trang và tải tệp, không đụng gì tới website:
`Nhân viên K&H`, `Quản lý K&H`, `Quản trị K&H`. Administrator vẫn là Chủ sở hữu.

## Mang dữ liệu từ hệ thống nhân sự cũ sang

**Nền tảng K&H → Nhập / Xuất dữ liệu.**

### Hệ thống cũ nằm ngay trên WordPress này (không cần xuất file)

Chọn **Nhập thẳng từ cơ sở dữ liệu của site này** → chọn nơi hệ thống kia đang lưu dữ liệu:

- **Người dùng WordPress** — kèm mọi trường tuỳ biến trong `usermeta`.
- **Bảng trông giống dữ liệu nhân sự** — plugin tự lọc các bảng có tên chứa
  `hr`, `hrm`, `erp`, `employee`, `staff`, `nhansu`, `payroll`, `chamcong`… và đưa lên đầu.
- **Kiểu nội dung (custom post type)** — nếu hệ thống kia lưu nhân sự dạng bài viết,
  đọc cả `postmeta`.
- **Mọi bảng trong cơ sở dữ liệu** — không chắc thì chọn thử, bước sau chỉ xem trước chứ chưa ghi.

Plugin **chỉ đọc**, không sửa gì của hệ thống kia. Tên bảng luôn được đối chiếu với danh sách
bảng có thật trước khi truy vấn, nên không chèn được câu lệnh vào.

Cột kỹ thuật tiếng Anh cũng tự đoán: `display_name → Họ tên`, `employee_id → Mã nhân sự`,
`designation → Chức danh`, `department → Bộ phận`, `hiring_date → Ngày vào làm`,
`pay_rate → Lương cơ bản`. Riêng cột tên `id` cố tình **không** nhận làm mã nhân sự,
vì ở hầu hết phần mềm đó chỉ là số thứ tự nội bộ.

### Hệ thống cũ ở nơi khác — đi qua file

Nhận thẳng **`.xlsx`** xuất từ phần mềm nhân sự, hoặc `.csv`. File Excel được đọc bằng PHP thuần
(ZipArchive + SimpleXML, không cần thư viện ngoài): lấy bảng tính đầu tiên, giữ đúng cột kể cả khi
file bỏ trống ô ở giữa, và đổi ô định dạng ngày từ số thứ tự của Excel về dạng ngày/tháng/năm.
Máy chủ không bật ZipArchive thì trình nhập báo rõ và bảo lưu sang CSV.

**Nhập lại nhiều lần được.** Cách khớp mặc định là *Mã nhân sự, hồ sơ chưa có mã thì dò theo họ tên* —
hợp với lần nhập thứ hai trở đi, khi vài hồ sơ đã tạo tay trong nền tảng và chưa có mã.

Những ô **Vai trò** và **Tình trạng** chỉ bị ghi đè khi file có cột đó và ô có giá trị, nên phân quyền
đã chỉnh trong nền tảng không bị lần nhập sau xoá mất. Các cột khác thì có trong file là ghi đè —
muốn giữ cách sắp xếp Bộ phận / Mảng đã làm trong nền tảng thì ở bước 2 chọn *— Bỏ qua —* cho cột đó.


1. Phần mềm cũ xuất danh sách nhân sự ra **CSV** (Excel: *Save As → CSV UTF-8*). Dán thẳng
   từ Excel cũng được vì công cụ đọc cả dấu Tab.
2. Tải file lên hoặc dán vào ô → **Đọc dữ liệu**.
3. Màn hình bước 2 hiện bảng xem trước và tự đoán từng cột theo tên tiếng Việt
   (*Họ và tên, Mã NV, Phòng ban, SĐT, Ngày vào làm, Lương cơ bản…*). Soát lại, cột nào
   không cần thì chọn *— bỏ qua —*.
4. Chọn cách nhận ra người đã có (**Mã nhân sự / Email / Họ và tên**) và có cập nhật đè hay không → Nhập.

Tự xử lý sẵn: bỏ BOM đầu file, dấu phân cách `,` `;` hoặc Tab, ngày kiểu Việt `31/12/2026`,
tiền `18.000.000 ₫` → số, *Nam/Nữ/Male*, *Thử việc → probation*, *Đã nghỉ → left*,
*Trưởng phòng → Quản lý*. Nhập lại lần hai **không tạo bản trùng** — khớp theo mã rồi cập nhật.

Nhập xong sang **Tài khoản** bấm tạo tài khoản đăng nhập cho từng người, hoặc **Mã PIN đăng nhập**
tạo mã hàng loạt.

**Xuất ngược:** nút *Tải CSV hồ sơ nhân sự* xuất toàn bộ để đối chiếu hoặc sao lưu.

### Để hệ thống cũ tự đẩy sang, không cần file

```
POST /wp-json/khh/v1/doc
Authorization: Basic <tên đăng nhập:application password>
Content-Type: application/json

{ "coll": "staff", "id": "MNV-01",
  "data": { "name": "Quang Thắng", "code": "MNV-01", "title": "Quản lý dự án",
            "dept": "Phòng kỹ thuật", "email": "thang@khh.vn",
            "start": "2021-03-01", "salary": 18000000, "role": "admin" } }
```

Application password tạo ở *Người dùng → Hồ sơ → Application Passwords* của một tài khoản Quản trị.
Cùng cách đó đẩy được mọi nhóm dữ liệu khác, kể cả `attendance` (chấm công) —
xem mục **Dữ liệu** bên dưới để biết tên nhóm.

## Phân quyền

Vai trò lấy từ tài khoản WordPress, có thể ghi đè từng người ở **Người dùng → Hồ sơ → Nền tảng K&H**:

| Vai trò nền tảng | Mặc định ánh xạ từ WordPress |
|---|---|
| Chủ sở hữu | Administrator |
| Quản trị | Editor |
| Quản lý | Author |
| Nhân viên | các vai trò còn lại |

**Máy chủ chặn thật, không chỉ ẩn nút:** hồ sơ nhân sự, bảng lương, cấu hình lương và máy chấm công
chỉ Quản trị trở lên mới ghi được — REST trả 403 cho người khác. Các nhóm còn lại (công việc, đề xuất,
chat, đặt phòng, chấm công…) thì mọi tài khoản đã đăng nhập đều dùng được.

Đây là quyền **trên toàn hệ thống**, không phải chức vụ trong phòng ban — chức vụ nằm ở ô *Chức danh*
của hồ sơ nhân sự và không ảnh hưởng gì tới quyền.

**Máy chủ chặn thật, không chỉ ẩn nút:** hồ sơ nhân sự, danh sách bộ phận, bảng lương, cấu hình lương
và máy chấm công chỉ Quản trị trở lên mới ghi được — REST trả 403 cho người khác. Các nhóm còn lại
(công việc, đề xuất, chat, đặt phòng, chấm công…) thì mọi tài khoản đã đăng nhập đều dùng được.

### Giới hạn Quản lý theo bộ phận

Mặc định **bật**. Một Quản lý chỉ duyệt nghỉ phép, sửa chấm công và xem lương của người trong phạm vi
của mình; ngoài phạm vi thì nút vẫn bấm được nhưng bị từ chối kèm lời nhắc.

Phạm vi của một Quản lý = bộ phận ghi trong hồ sơ của chính họ, **cộng** mọi bộ phận mà họ được đặt
làm *Trưởng bộ phận* ở màn hình **Hồ sơ nhân sự → Bộ phận**. Nếu bật thêm tách quyền theo mảng thì
phải thoả cả hai điều kiện. Chủ sở hữu và Quản trị luôn quản lý
toàn công ty. Tắt giới hạn ở ngay màn hình đó để quay về kiểu cũ.

Lưu ý: giới hạn này áp cho **thao tác**, không giấu danh sách — Quản lý vẫn nhìn thấy toàn bộ danh
sách nhân sự như trước.

## Mảng kinh doanh

Công ty có nhiều mảng (Posh, HVC…) nhưng dùng chung phòng ban — Phòng kế toán làm sổ cho cả hai.
Nên mảng **không** đặt ở cấp bộ phận mà ghi trên hồ sơ từng người: **Hồ sơ nhân sự → Mảng kinh doanh**.

- Bộ phận vẫn khai **một lần**. Không có "Kế toán Posh" và "Kế toán HVC".
- Ai làm cho cả hai mảng thì để **Dùng chung** — mảng nào cũng thấy và quản lý được người đó.
- Chọn một mảng ở thanh bên là cả phần nhân sự đi theo mảng đó: danh sách, hợp đồng lao động,
  cơ cấu tổ chức, báo cáo. Người *Dùng chung* luôn hiện kèm.
- **Gán mảng hàng loạt**: chọn một bộ phận rồi gán cả bộ phận vào một mảng, không phải sửa tay
  từng hồ sơ.
- Đổi tên một mảng cập nhật luôn hồ sơ của mọi người thuộc mảng đó.
- Bảng lương có thanh chọn mảng; cộng các mảng lại đúng bằng tổng toàn công ty (người *Dùng chung*
  đứng riêng một nhóm nên không bị đếm hai lần).
- Báo cáo nhân sự có thêm phần nhân sự và quỹ lương theo mảng.

Danh mục mảng lưu trong nhóm `settings` (bản ghi `units`), nên chỉ Quản trị trở lên sửa được.

### Tách quyền theo mảng

Mặc định **bật**. Một Quản lý chỉ thao tác với người **cùng mảng** với mình, cộng những người
*Dùng chung*. Quản lý chưa khai mảng thì vẫn làm việc với mọi mảng. Tắt được ngay ở màn hình
Mảng kinh doanh.

Chạy **độc lập** với giới hạn theo bộ phận: bật cả hai thì một thao tác phải thoả **cả hai** —
cùng mảng *và* trong bộ phận mình phụ trách.

## Bộ phận

**Hồ sơ nhân sự → Bộ phận** (cần quyền Quản trị trở lên):

- **Đổi tên** — cập nhật luôn ô *Bộ phận* trong hồ sơ của mọi nhân sự thuộc bộ phận đó, không phải
  sửa từng người. 199 nhân sự cũng chỉ một thao tác.
- **Gộp** hai bộ phận bị gõ lệch tên vào làm một; **Xoá** thì bắt chọn nơi chuyển người sang trước,
  không ai bị mất hồ sơ.
- **Trưởng bộ phận**, **thứ tự hiển thị** trên thanh bên, **ghi chú**.
- Người chưa khai bộ phận gom vào dòng *Chưa phân bộ phận* ở cuối bảng, gán hàng loạt được.

Bộ phận **dùng chung giữa các mảng kinh doanh** — xem phần Mảng kinh doanh ở trên.

Tên bộ phận vẫn nằm ngay trong hồ sơ nhân sự như trước, nên dữ liệu cũ và phần nhập từ hệ thống cũ
chạy y nguyên. Khi nhập tên chỉ khác nhau ở hoa thường hay dấu cách thừa, hệ thống tự nắn về đúng
tên đã có thay vì sinh bộ phận mới.

## Dữ liệu

Một bảng duy nhất, mỗi bản ghi một dòng JSON:

```sql
{prefix}khh_docs ( coll, doc_id, payload, deleted, updated_at, updated_by )
```

21 nhóm dữ liệu: `projects tasks requests reqtypes flows jobs staff depts timeoffs attendance
payrolls posts meetings rooms devices channels messages feed resources bookings settings`.

REST API (bắt buộc đăng nhập, dùng nonce `X-WP-Nonce`):

```
GET  /wp-json/khh/v1/state?since=<ms>   → { now, docs:{coll:[…]}, deleted:[{coll,id}] }
POST /wp-json/khh/v1/doc                  { coll, id, data }
POST /wp-json/khh/v1/bulk                 { coll, docs:[{id,data}] }  → { saved, skipped }
POST /wp-json/khh/v1/delete               { coll, id }
```

`bulk` ghi tối đa 200 bản ghi một lượt (đổi tên bộ phận cho 150 người tốn 2 lượt gọi thay vì 150),
xét quyền y hệt `doc`.

Trình duyệt đồng bộ lại mỗi 8 giây và mỗi khi quay lại tab. Ảnh chấm công và tệp đính kèm trong chat
đi qua thư viện media của WordPress (`/wp-json/wp/v2/media`).

## Cấu trúc

```
khh-platform.php   plugin: bảng dữ liệu, REST API, phân quyền, trang quản trị, shortcode
uninstall.php      gỡ plugin (mặc định GIỮ dữ liệu)
seed-data.json     dữ liệu ví dụ nạp lúc kích hoạt
assets/
  app.css          giao diện, nền sáng/tối, responsive
  core.js          lõi: dữ liệu, điều hướng, vai trò, hộp thoại dùng chung
  charts.js        biểu đồ SVG
  home.js wework.js baocao.js request.js workflow.js hrm.js attendance.js
  leave.js payroll.js info.js message.js square.js booking.js
```

Ngoài `khh-platform.php` còn mấy tệp PHP rời, mỗi tệp một việc:
`pin-login.php` · `switch-user.php` · `cap-tai-khoan.php` · `dang-nhap.php` ·
`import.php` + `xlsx.php` · `nhap-cham-cong.php` · `noi-vhcc.php` ·
`tai-khoan-cua-toi.php` (nhân viên tự xem hồ sơ và tự đổi mật khẩu).

## Thêm một ứng dụng mới

Tạo `assets/myapp.js`, rồi thêm tên `'myapp'` vào mảng trong hàm `khh_scripts()` của file PHP:

```js
(function(){
  var A = window.APP;
  function view(){
    return '<aside class="side">' + A.sideUser() + '…</aside>' +
      '<section class="stage"><header class="topbar">' + A.navBtn() + '<h1>Tên</h1></header>' +
      '<nav class="tabs"></nav><div class="content" id="content">…</div></section>';
  }
  function after(){ A.$('#appRoot').addEventListener('click', function(e){ /* … */ }); }
  A.register({ id:'myapp', name:'Tên ứng dụng', desc:'Mô tả', cat:'work',
    color:'#1177D8', icon:'work', side:true, info:false, view:view, after:after });
})();
```

Muốn thêm nhóm dữ liệu mới thì khai tên nhóm ở hai chỗ: mảng `COLLS` trong `core.js`
và hàm `khh_collections()` trong PHP.

## Báo cáo Dự Án — trang tổng

Ứng dụng **Công việc & Dự án** trả lời *"dự án NÀY đang thế nào"*. Ứng dụng **Báo cáo Dự Án**
trả lời câu khác hẳn: *"TẤT CẢ dự án đang thế nào"* — thứ mà trước đây phải mở từng dự án ra
cộng tay.

Số liệu chia **hai loại, ghi rõ ngay trên màn hình**, vì trộn vào nhau là báo cáo sai:

| Nhóm | Gồm gì | Kỳ báo cáo có ăn không |
|---|---|---|
| **Hiện tại** | việc chưa xong, quá hạn, sắp đến hạn trong 7 ngày | **Không.** Việc quá hạn từ tháng trước hôm nay vẫn đang quá hạn; lọc nó ra khỏi kỳ là giấu mất chỗ đang cháy |
| **Trong kỳ** | mở mới, hoàn thành, tỷ lệ đúng hạn | **Có** — 30 ngày / 90 ngày / năm nay / tất cả |

Bốn màn:

- **Tổng quan** — 8 thẻ số, vòng tròn trạng thái công việc, đường nhịp độ *mở mới vs hoàn thành*
  theo tuần, và bảng từng dự án (xếp được theo tên, bộ phận, tiến độ hoặc hạn chót).
- **Theo bộ phận** — việc quá hạn và việc xong trong kỳ của từng bộ phận, kèm bảng đối chiếu.
- **Theo người** — được giao / đang làm / xong / quá hạn / đúng hạn của từng người phụ trách.
- **Cần xử lý** — dự án trễ hạn, dự án **đứng im** (14 ngày không có việc nào mở mới, hoàn thành
  hay bình luận), việc quá hạn lâu nhất, việc chưa giao ai. Bấm vào là sang thẳng dự án đó.

Vài quy ước cố ý, để con số đọc lên không đánh lừa người xem:

- **Tỷ lệ đúng hạn chỉ tính trên việc CÓ đặt hạn.** Việc quên đặt hạn thì không có gì để đúng hay
  muộn; gom nó vào mẫu số là tỷ lệ tự đẹp lên theo số việc làm ẩu.
- **Việc mồ côi** (dự án đã xoá) không được cộng vào đâu cả — nó không thuộc bộ phận nào, cộng
  vào là tổng các bộ phận không bằng tổng chung.
- **Dự án đã xong hết việc thì không bị kêu trễ hạn**, dù đã quá ngày kết thúc.
- Bộ lọc **bộ phận** đọc ô *Bộ phận* của dự án, cùng danh mục với Hồ sơ nhân sự.

Nút **Tải CSV** xuất toàn bộ bảng dự án (dấu `;` và BOM để Excel bản tiếng Việt mở ra đúng cột,
đúng dấu), nút **In trang** in đúng những gì đang hiện.

## Tài khoản của nhân viên

Bấm vào tên mình ở góc trên bên trái để mở hộp **Tài khoản của bạn**. Quản trị viên bật/tắt
hai việc dưới đây ở **Nền tảng K&H → Cấu hình → Tài khoản của nhân viên**.

### Xem hồ sơ của chính mình

Mỗi người chỉ thấy hồ sơ của mình: mã nhân sự, chức danh, bộ phận, mảng, cơ sở, quản lý trực
tiếp, ngày vào làm, hợp đồng, phép còn lại, liên hệ. Chỉ để xem — sai chỗ nào thì báo văn phòng
sửa trong ứng dụng Hồ sơ nhân sự, sửa ở đó thì bảng công, phép và lương mới khớp theo.

Cố ý **không** bày ngày sinh, số sổ BHXH, tài khoản ngân hàng: chủ hồ sơ đã biết rồi, bày ra chỉ
thêm rủi ro khi có người ngó màn hình.

Lương cơ bản và phụ cấp có một ô bật riêng, **mặc định tắt**. Nói cho đúng: đó là *ẩn trên màn
hình*, không phải khoá dữ liệu — nền tảng vẫn gửi toàn bộ hồ sơ nhân sự về máy của mọi tài khoản
đã đăng nhập, đúng như trước nay. Muốn lương thật sự kín thì phải chặn từ tầng dữ liệu, chưa làm
trong bản này.

Hồ sơ được tìm theo mã WordPress ghi sẵn (`khh_staff_id`), không có mã thì mới dò theo tên —
tên hiển thị hay bị gõ lệch dấu hoặc trùng nhau, dò theo tên là có lúc mở nhầm hồ sơ người khác.

### Tự đổi mật khẩu

Trước đây muốn đổi mật khẩu phải nhờ quản trị đặt lại rồi đọc mật khẩu mới qua điện thoại —
mật khẩu đi qua tay người thứ ba là mất ý nghĩa của mật khẩu.

Chốt chặn:

- Bắt nhập **đúng mật khẩu hiện tại**. Ai mượn được máy đang mở sẵn cũng không đổi được.
- Sai 5 lần thì khoá 15 phút, đếm theo **từng tài khoản** — không theo IP, vì cả cơ sở dùng chung
  một đường mạng, đếm theo IP là một người gõ sai khoá cả cửa hàng.
- Mật khẩu mới tối thiểu 8 ký tự (đổi được, 6–64) và phải khác mật khẩu cũ.
- Đổi xong, **các máy khác đang mở tài khoản này phải đăng nhập lại** — WordPress vô hiệu mọi
  phiên cũ; máy vừa đổi được cấp lại phiên và tự tải lại trang.
- Người vào bằng **mã PIN** không thấy nút này: đổi mật khẩu cần mật khẩu hiện tại, mà họ không
  có. Nhờ quản trị đặt lại ở màn *Cấp tài khoản*.

## Cách tính lương

Lương theo ngày công (lấy từ Bảng công) + phụ cấp − BHXH/BHYT/BHTN (có trần đóng)
− khấu trừ phút đi muộn − thuế TNCN luỹ tiến 7 bậc sau giảm trừ gia cảnh.
Sửa mọi mức và biểu thuế trong app: **Bảng lương → Cấu hình tính lương**.

## Những gì plugin không làm

- Không nhận diện khuôn mặt. Việc đó do máy chấm công Hikvision + ESP32 ngoài hiện trường làm;
  app chỉ lưu ảnh và cho người phụ trách đối chiếu bằng mắt.
- Không gọi video trong trang. Nút "Tạo phòng họp" mở phòng trên meet.jit.si ở tab mới.
- Chat nhắn riêng chỉ ẩn trên giao diện, dữ liệu vẫn nằm trong bảng chung —
  quản trị viên WordPress đọc được. Đừng gửi mật khẩu hay token qua đây.
