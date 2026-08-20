# Chi Phí Cơ Sở / Vận Hành Chi Phí — bản WordPress

App Apps Script `VanHanhChiPhi` (Code.gs 1.619 dòng + Index.html 3.129 dòng) đã được dựng lại
thành **1 plugin WordPress** chạy trực tiếp trên hosting, dữ liệu nằm trong **bảng MySQL riêng**
(`wp_vhcp_*`), không còn phụ thuộc Google Sheet / Google Drive.

- Mã plugin: `wordpress/vhcp-chi-phi/`
- File cài đặt: `dist/vhcp-chi-phi.zip` (tạo lại bằng `bash tools/build-plugin-zip.sh`)

## 1. Cài trong 4 bước

1. Vào **wp-admin → Plugin → Cài mới → Tải plugin lên**, chọn `dist/vhcp-chi-phi.zip`.
2. Bấm **Cài đặt** → **Kích hoạt**.
3. Menu bên trái xuất hiện **Vận Hành Chi Phí** → bấm đường dẫn app (mặc định
   `https://<tên-miền>/chi-phi/`).
4. Đăng nhập **PIN 1111** (vai trò Admin) → vào tab **⚙️ Cấu hình** đổi PIN và khai người dùng.

> Yêu cầu hosting: WordPress ≥ 5.6, PHP ≥ 7.2 (thử trên 8.4), MySQL/MariaDB có quyền `CREATE TABLE`.
> Kích hoạt plugin sẽ tự tạo 13 bảng và nạp cấu hình mặc định (14 cơ sở, 13 nhóm mặt hàng,
> ma trận phân quyền 12 hành động × 4 vai trò).

Nếu mở `/chi-phi/` bị **404**: vào **Vận Hành Chi Phí → Bảo trì → "Kiểm tra lại bảng dữ liệu +
làm mới đường dẫn"** (hoặc **Cài đặt → Đường dẫn tĩnh → Lưu**). Hosting để permalink dạng `?p=`
thì dùng `https://<tên-miền>/?vhcp=app`.

## 2. Đường dẫn & cách nhúng

| Việc | Cách làm |
|---|---|
| Mở app trực tiếp | `https://<tên-miền>/chi-phi/` (đổi được ở **Cài đặt**) |
| Nhúng vào 1 trang WordPress | dán shortcode `[vhcp_app height="900"]` |
| Nhúng vào trang tổng K&H | đặt `CHIPHI_URL = 'https://<tên-miền>/chi-phi/'` trong `AttendanceScript/Index.html` |
| Đăng nhập một lần từ trang tổng | `https://<tên-miền>/chi-phi/?sso=<token>` |

**SSO** dùng đúng thuật toán của app cũ — `base64url(payload).base64url(HMAC-SHA256)` — nên trang
tổng **không phải sửa gì**: chỉ cần điền cùng chuỗi bí mật vào **Vận Hành Chi Phí → Cài đặt →
SSO_SECRET** (giá trị `SSO_SECRET` đang đặt trong Script Properties của app cũ). Bảng phân vai trò
theo email (`CH_SSO`) vẫn hoạt động y như trước.

## 3. Mang dữ liệu cũ từ Google Sheet sang

Vào **Vận Hành Chi Phí → Nhập dữ liệu**. Với từng tab của bảng tính:
**Tệp → Tải xuống → CSV** rồi tải file lên, chọn đúng "Tab đang nạp".

Nạp theo thứ tự này để không bị lệch khóa:

1. `CH_CoSo`, `CH_Nhom`, `CH_PhanLoai`, `CH_DoiTuong`, `CH_TKNo`, `CH_QR`, `CH_NguoiDung`, `CH_SSO`, `CH_Quyen`
2. `DonHang` → `TamUng` → `ChiPhi`
3. `DA_Index` → rồi **từng tab dự án** (chọn dự án ở ô "Dự án / Đợt nhận dòng")
4. `MK_Don` → `MK_Line`
5. `BP_Index` → rồi **từng tab đợt** Công tác/Setup
6. `NhatKy` (tùy, chỉ để tra lịch sử)

Lưu ý:

- **Ngày tháng đọc kiểu Việt Nam** (ngày trước: `20/08/2026`). Nếu bảng tính đang xuất kiểu Mỹ,
  đổi **Cài đặt bảng tính → Ngôn ngữ/Vùng → Việt Nam** trước khi tải CSV.
- Số có dấu phân cách nghìn (`1.500.000` hoặc `1,500,000`) đều đọc được.
- **Ô trống khác 0**: cột *Thực mua* / *Thuế suất* / *Tạm ứng duyệt* để trống sẽ vào DB là `NULL`
  (nghĩa là "chưa nhập"), đúng như app cũ phân biệt.
- Tick **"Xóa dữ liệu cũ của bảng này trước khi nạp"** nếu nạp lại lần 2 để tránh nhân đôi;
  nạp lại cùng mã đơn / cùng ID dòng thì hệ thống tự ghi đè.
- Tab dự án / tab đợt có 4 dòng đầu là tiêu đề + dải tổng hợp — trình nhập tự bỏ 4 dòng đó.

## 4. Khác gì so với bản Apps Script

| Việc | App cũ (Apps Script) | Bản WordPress |
|---|---|---|
| Nơi lưu dữ liệu | Google Sheet | Bảng MySQL `wp_vhcp_*` |
| Ảnh chứng từ / hồ sơ | Google Drive | `wp-content/uploads/vhcp/<Cơ sở>/<Người lập>/` |
| Nút "Mở sheet" ở tab Kỹ thuật / Công tác | mở tab Google Sheet | **đã ẩn** (không còn sheet để mở) |
| Dọn ảnh cũ (`migrateOldImages`) | dời ảnh trong Drive | không cần — lưu đúng cây ngay khi tải lên |
| Giới hạn 6 phút/lệnh của Apps Script | có | không còn |
| Giao diện | Index.html | **giữ nguyên 100%** nhờ lớp tương thích `google.script.run` |
| Đăng nhập | PIN, API mở cho mọi người có link | PIN + **token phiên** (API chặn nếu không có token) và **hãm 10 lần thử PIN sai / 10 phút** |

Giao diện không bị viết lại: `assets/js/gas-shim.js` dựng lại `google.script.run` và chuyển mỗi
lệnh gọi thành `POST /wp-json/vhcp/v1/call {fn, args}`. Nhờ vậy toàn bộ 102 chỗ gọi backend
trong Index.html chạy y như cũ, và về sau sửa giao diện vẫn theo cách quen.

Toàn bộ nghiệp vụ được dịch nguyên văn, gồm những chỗ dễ sai nhất:

- Tạm ứng "1 cục" cho cả đơn = *Tạm ứng duyệt* (nếu có) hoặc *tổng hạng mục xin + dự phòng + bù trừ kỳ trước*.
- Thực chi mỗi dòng = *Thực mua* nếu đã nhập, ngược lại = *Thành tiền*.
- Dòng NCC hiệu lực = phân loại "Nhà cung cấp" **hoặc** dòng cá nhân bị bỏ tích "CN xử lý".
- Hạng mục xin **khóa sau khi cấp tạm ứng** (chỉ sửa Thực chi); mua thêm ghi vào mục **PHÁT SINH**.
- Kế toán sửa số tiền lúc "Chờ quyết toán" → tự gắn cờ đỏ `[KT sửa]`; đơn bị trả lại → cờ `[Trả lại]` mở khóa cho NV sửa.
- Dự án kỹ thuật: hạng mục lớn mang **dự toán**, thực tế chỉ tính ở **mục con** (không cộng trùng);
  xóa hạng mục lớn thì mục con chuyển thành `(Phát sinh)` chứ không mất tiền; mục con thừa hưởng
  *hình thức chi* của cha.
- Xuất MISA: TK Nợ lấy từ **ma trận nhóm × phân loại lớn** (tên nhóm **gốc**, chưa bỏ đuôi `- NCC`/`- Mua lẻ`),
  TK Có ưu tiên **TK Có của người duyệt tạm ứng** rồi mới đến TK Có của phân loại (141/331);
  các dòng cùng nhóm mặt hàng xếp liền nhau; chốt "đã xuất" theo từng bộ phận CN/NCC.

## 5. Kiểm nghiệm

`tools/test/` có bộ tự kiểm chạy bằng PHP CLI, **không cần WordPress hay MySQL**
(dựng $wpdb tối giản trên SQLite):

```bash
php tools/test/test-flows.php     # 147 phép thử: đơn, duyệt, quyết toán, dự án, MKT,
                                  # công tác, báo cáo tuần, 4 luồng xuất MISA, nhập CSV, tải ảnh
```

## 6. Cập nhật plugin về sau

```bash
bash tools/build-plugin-zip.sh    # tạo lại dist/vhcp-chi-phi.zip
```

rồi tải lên wp-admin như lần đầu (WordPress hỏi "thay thế bản cũ" → chọn thay thế).
Cập nhật **không xóa dữ liệu**; xóa plugin cũng **giữ nguyên bảng dữ liệu** (muốn dọn sạch thì
thêm `define( 'VHCP_DELETE_DATA_ON_UNINSTALL', true );` vào `wp-config.php` trước khi xóa).

Nếu máy anh vào được hosting bằng SSH hoặc FTP thì đẩy thẳng bằng script:

```bash
cp tools/deploy-hosting.env.mau tools/deploy-hosting.env   # điền thông tin hosting
bash tools/deploy-hosting.sh                               # rsync/lftp lên wp-content/plugins
```

File `tools/deploy-hosting.env` đã nằm trong `.gitignore` — **không bao giờ commit mật khẩu hosting**.

## 7. Bảo mật cần biết

- **PIN vẫn lưu nguyên văn** trong bảng cấu hình, vì tab ⚙️ Cấu hình hiện & sửa PIN từng người
  đúng như cách vận hành cũ. Ai vào được wp-admin hoặc database là thấy PIN. Muốn siết thì đổi
  sang đăng nhập SSO từ trang tổng và không phát PIN nữa.
- **Bắt buộc HTTPS** — PIN và token đi qua đường truyền.
- Ảnh chứng từ nằm trong `wp-content/uploads` nên **ai có đúng link là xem được** (giống chế độ
  "bất kỳ ai có liên kết" của Drive trước đây). Cần kín hơn thì chặn `uploads/vhcp/` bằng
  `.htaccess` và cho đọc qua PHP có kiểm phiên — hiện chưa làm.
- Token phiên sống 30 ngày, thu hồi khi bấm **Đăng xuất**.
- API chỉ mở đúng 1 endpoint và chỉ chạy các hàm trong bảng cho phép (`class-vhcp-api.php`);
  file hồ sơ chặn mọi đuôi chạy được trên server (`.php`, …), ảnh giới hạn 15MB.

## 8. Khắc phục sự cố

| Hiện tượng | Xử lý |
|---|---|
| `/chi-phi/` báo 404 | **Bảo trì → làm mới đường dẫn**, hoặc vào **Cài đặt → Đường dẫn tĩnh → Lưu** |
| Vào app hiện lại cổng PIN liên tục | Token hết hạn/đã thu hồi — đăng nhập lại; kiểm tra trình duyệt không chặn `localStorage` |
| Đính ảnh báo "Không ghi được file" | Sửa quyền ghi cho `wp-content/uploads` (thường 755) |
| Tải MISA ra CSV thay vì Excel | Máy/hosting chặn CDN `cdnjs.cloudflare.com` — CSV là bản dự phòng sẵn có, dùng bình thường |
| Xuất MISA cảnh báo "Thiếu TK Nợ (nhóm × phân loại)" | Vào ⚙️ Cấu hình → ma trận TK Nợ, điền theo **tên nhóm gốc** (giữ đuôi `- NCC`/`- Mua lẻ`) |
| Số liệu lệch sau khi nhập CSV | Nạp lại tab đó với tick "Xóa dữ liệu cũ trước khi nạp"; kiểm tra định dạng ngày kiểu Việt Nam |
