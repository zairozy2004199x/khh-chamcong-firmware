# BÀN GIAO — Báo Cáo Chi Phí (K&H)

Tài liệu cho người tiếp nhận phát triển tiếp. Bản 1.1.0, ngày 16/09/2026.

> Tài liệu này nay nằm **trong repo** (`khbc-bao-cao-chi-phi/BAN-GIAO.md`) để nó đi cùng mã và
> cùng được sửa trong mỗi lần thay đổi — bản gửi rời qua chat sẽ cũ đi mà không ai biết.

## 1. Địa chỉ

| Thứ | Địa chỉ |
|---|---|
| Repo (công khai) | https://github.com/zairozy2004199x/khh-chamcong-firmware |
| Thư mục plugin WordPress | https://github.com/zairozy2004199x/khh-chamcong-firmware/tree/main/khbc-bao-cao-chi-phi |
| Thư mục bản web tĩnh (nguồn giao diện) | https://github.com/zairozy2004199x/khh-chamcong-firmware/tree/main/web_baocao_chiphi |
| Pull request gốc (mô tả đầy đủ) | https://github.com/zairozy2004199x/khh-chamcong-firmware/pull/1 |
| Bản phát hành + file zip cài đặt | https://github.com/zairozy2004199x/khh-chamcong-firmware/releases/tag/khbc-bao-cao-chi-phi-v1.0.0 |
| Hướng dẫn plugin | `khbc-bao-cao-chi-phi/readme.txt` |
| Hướng dẫn bản web tĩnh | `web_baocao_chiphi/README.md` |
| Hướng dẫn backend Google Sheets | `web_baocao_chiphi/backend/README.md` |

Tải mã nguồn: `git clone https://github.com/zairozy2004199x/khh-chamcong-firmware.git`

> Repo này còn chứa firmware máy chấm công ESP32 (`esp32_*`). Chỉ hai thư mục
> `khbc-bao-cao-chi-phi/` và `web_baocao_chiphi/` thuộc phần việc này.

## 2. Ứng dụng làm gì

Thay cho việc kéo công thức tay trong file Excel "File chi phí MN Txx/yyyy":

- **Nhân viên** vào `/bao-cao-chi-phi/nhap/` nhập khoản chi phí kèm ảnh chứng từ → trạng thái *Chờ duyệt*.
- **Kế toán** vào `/bao-cao-chi-phi/` nhập Excel doanh thu / bảng lương, duyệt khoản, xem **File tổng báo cáo**
  (3 mục đúng bố cục file gốc) và **phân bổ theo điểm**, xuất Excel 10 sheet cho MISA.
- **Admin** quản lý người dùng & PIN, chốt kỳ, xem nhật ký.

Cách tính: mỗi khoản có tổng tiền, chia cho hai nhóm **MTĐ** (Posh, JP) và **KVC** (Funzone, Event,
Farm, Tutu, Pinball). MTĐ chia theo tỷ lệ cố định (Posh 60 / JP 40), KVC chia theo tỷ trọng doanh thu.
Chi tiết trong `web_baocao_chiphi/README.md`, mục "Cách tính".

## 3. Cấu trúc mã

### Plugin WordPress — `khbc-bao-cao-chi-phi/`

| Tệp | Vai trò |
|---|---|
| `khbc-bao-cao-chi-phi.php` | Nạp plugin, bước nâng cấp, xoá opcache sau cập nhật |
| `includes/class-khbc-db.php` | 5 bảng MySQL `wp_khbc_*`: `nguoi_dung`, `phien`, `khoan`, `ky`, `nhat_ky` |
| `includes/class-khbc-auth.php` | Đăng nhập PIN (băm), thẻ phiên 30 ngày, SSO `?sso=` |
| `includes/class-khbc-store.php` | Đọc/ghi khoản chi phí, cấu hình kỳ, người dùng, tệp đính kèm, nhật ký |
| `includes/class-khbc-api.php` | REST một cửa `POST /wp-json/khbc/v1/call {fn, args, token}` + 2 đường dự phòng |
| `includes/class-khbc-app.php` | Trang app `/bao-cao-chi-phi/` và `/bao-cao-chi-phi/nhap/`, shortcode `[khbc_app]` |
| `includes/class-khbc-admin.php` | Trang wp-admin: Cài đặt, Tự cập nhật, đặt lại PIN Admin |
| `includes/class-khbc-tu-cap-nhat.php` | Tự cập nhật từ nhánh GitHub / Releases |
| `assets/js/*`, `assets/css/app.css`, `templates/*.html` | Giao diện — **chép tự động**, xem mục 5 |
| `tools/dev/` | Bộ giả lập WordPress trên SQLite để chạy thử không cần WordPress |
| `tools/test/kiem-trang-it.php` | Kiểm giao kèo với **Trang IT** — xem mục 5.6 |

Vai trò: `Admin` · `Kế toán` · `Nhân viên`. Nhân viên chỉ thêm/sửa/xoá khoản **của mình** khi còn
*Chờ duyệt*; mở trang kế toán thì bị chuyển về trang nhập. Chặn ở phía máy chủ
(`class-khbc-api.php::required_roles` và các chốt trong `class-khbc-store.php`), không tin giao diện.

### Bản web tĩnh — `web_baocao_chiphi/`

| Tệp | Vai trò |
|---|---|
| `engine.js` | **Lõi tính toán**, không phụ thuộc giao diện — sửa công thức thì sửa ở đây |
| `importer.js` | Đọc file Excel "File chi phí MN" |
| `exporter.js` | Dựng workbook Excel đầu ra |
| `app.js` | Trang kế toán: các tab, tự lưu, đồng bộ máy chủ |
| `nhap.js`, `nhap.html` | Trang nhân viên |
| `api.js` | Gọi máy chủ — 2 chế độ: Apps Script (bản tĩnh) / REST WordPress (plugin) |
| `wp-ui.js` | Cổng PIN, hộp Tài khoản, quản lý người dùng (chỉ dùng ở bản WordPress) |
| `test/engine.test.js` | Kiểm thử, đối chiếu 185 con số với file Excel gốc T8/2026 |
| `backend/apps-script/Code.gs` | Backend Google Sheets cho bản tĩnh (không bắt buộc) |

## 4. Chạy thử trên máy

```bash
git clone https://github.com/zairozy2004199x/khh-chamcong-firmware.git
cd khh-chamcong-firmware

# 1) Kiểm thử lõi tính toán (cần Node)
node web_baocao_chiphi/test/engine.test.js

# 2) Chạy plugin KHÔNG cần WordPress (cần PHP 7.2+ có pdo_sqlite)
php -S 127.0.0.1:8088 khbc-bao-cao-chi-phi/tools/dev/router.php
#    → http://127.0.0.1:8088/bao-cao-chi-phi/        PIN 1111 (Admin)
#    → http://127.0.0.1:8088/bao-cao-chi-phi/nhap/
#    → http://127.0.0.1:8088/__dev/reset   tạo lại dữ liệu mẫu:
#         Admin PIN 1111 · Kế toán PIN 2222 · Nhân viên PIN 3333

# 3) Chạy bản web tĩnh
cd web_baocao_chiphi && python3 -m http.server 8080
```

`tools/dev/` chỉ là bộ giả lập để phát triển, **không** đi kèm file zip cài đặt và không dùng cho
môi trường thật.

## 5. Quy tắc bắt buộc khi sửa

1. **Giao diện chỉ sửa ở `web_baocao_chiphi/`**, rồi chạy:
   ```bash
   python3 khbc-bao-cao-chi-phi/tools/dong-bo-giao-dien.py
   ```
   Lệnh này chép JS/CSS/HTML sang plugin. CI chạy `--check` và **báo đỏ nếu quên chép**.
   Sửa thẳng trong `khbc-bao-cao-chi-phi/assets/` sẽ bị ghi đè ở lần đồng bộ sau.

2. **Tăng số phiên bản ở 3 chỗ, phải bằng nhau** (CI kiểm):
   - `khbc-bao-cao-chi-phi/khbc-bao-cao-chi-phi.php` — dòng `* Version:`
   - cùng tệp — `define( 'KHBC_VERSION', ... )`
   - `khbc-bao-cao-chi-phi/readme.txt` — `Stable tag:`
   ```bash
   bash khbc-bao-cao-chi-phi/tools/kiem-phien-ban.sh   # in ra số nếu khớp, báo lỗi nếu lệch
   ```

3. **Đổi bảng dữ liệu** thì tăng `KHBC_DB::SCHEMA_VERSION`, nếu không `dbDelta()` sẽ không chạy.
   Không viết chú thích bên trong chuỗi `CREATE TABLE` — `dbDelta()` hiểu nhầm thành cột.

4. **Phân quyền chốt ở máy chủ**, không chỉ ẩn nút trên giao diện.

5. **Đừng gỡ dòng khai vào Trang IT** (`add_filter( 'vhcp_tu_cap_nhat_ds', … )` trong
   `class-khbc-tu-cap-nhat.php`). Xem mục 5.6.

5. Đẩy lên nhánh `main` là CI tự chạy test, kiểm đồng bộ, lint PHP, dựng zip và tạo Release
   `khbc-bao-cao-chi-phi-v<phiên bản>`. WordPress trên hosting tự thấy bản mới.

### 5.6 Giao kèo với Trang IT (khmatrix.com/it) — 1.1.0

Trang IT là một trang **của plugin Ghế** (`vhcp-ghe`), bày bảng "plugin nào đang chạy bản nào, có
bản mới không" và cho bấm cập nhật ngay — tiện cho điện thoại, nơi wp-admin gần như không dùng được.

**Trang ấy KHÔNG giữ danh sách plugin nào cả.** Nó dựng bảng lúc chạy từ bộ lọc
`vhcp_tu_cap_nhat_ds`. Plugin nào khai một dòng là tự hiện thêm; không ai phải đi sửa plugin Ghế,
và hai plugin không cần biết nhau.

Dòng khai gồm 5 khoá: `ma`, `ten`, `duong` (`plugin_basename`), `hien` (số bản đang chạy), `lop`
(tên lớp). Lớp ấy phải có `ban_moi_nho()`, `ban_moi()`, `quen_nho()`.

> 🔴 **`ban_moi_nho()` TUYỆT ĐỐI KHÔNG ĐƯỢC GỌI MẠNG.** Trang IT gọi nó ở **mỗi lượt mở trang**.
> `ban_moi()` hỏi thẳng GitHub và chờ tới 15 giây — nhét vào đó là treo cả trang khi mạng chậm,
> đúng loại lỗi người ta đổ cho "web lag" chứ không ai ngờ tới bộ cập nhật. Chỉ nút **"Kiểm tra
> bản mới"** mới được gọi `ban_moi()`.

> ⚠️ **Không kiểm `class_exists('VHG_Trang')` trước khi khai.** `add_filter` chạy được kể cả khi
> chưa ai treo `apply_filters` tương ứng; site không cài plugin Ghế thì dòng khai đơn giản là
> không ai đọc. Kiểm sớm là tự loại mình khỏi bảng một cách ngẫu nhiên tuỳ **thứ tự nạp plugin**.

Giao kèo này nằm vắt qua **hai plugin khác nhau**, nên không trình biên dịch nào bắt được lúc nó
gãy — gỡ nhầm một dòng thì plugin lặng lẽ biến khỏi bảng, không lỗi, không cảnh báo. Người gác:

```bash
php khbc-bao-cao-chi-phi/tools/test/kiem-trang-it.php [đường-dẫn/vhcp-ghe]
```

Có đối số thì nó cắt **chính hàm dựng bảng** `VHG_Trang::cn_ds_()` ra khỏi plugin Ghế và chạy thật
(16 phép). Không có thì phần liên plugin **báo BỎ QUA**, không lặng lẽ tính là đạt. CI chạy bài này
ở bước *"Còn hiện được ở trang IT không"*.

## 6. Cài lên hosting

1. WordPress → Plugin → Cài mới → Tải plugin lên → chọn `khbc-bao-cao-chi-phi.zip` → Kích hoạt.
2. Menu **Báo cáo chi phí** → Trang kế toán → PIN `1111` → bấm 👤 đổi PIN ngay.
3. 👤 Tài khoản → Người dùng: thêm kế toán, nhân viên, cấp PIN. Gửi nhân viên `…/bao-cao-chi-phi/nhap/`.
4. Đổi đường dẫn trang, múi giờ, nhánh tự cập nhật ở **Báo cáo chi phí → Cài đặt**.

Cài đè giữ nguyên dữ liệu. Gỡ plugin mặc định **không** xoá dữ liệu (xem `uninstall.php`).

## 7. Đã kiểm thử tới đâu

- `engine.js`: 185 con số khớp file Excel gốc T8/2026 (một ô lệch 0,2 đồng do Excel gõ tay số đã làm tròn).
- Trình duyệt thật (Playwright) bản tĩnh: 7 tab, sửa số, tải lại trang, nhập file Excel thật, xuất Excel,
  điện thoại, nền tối.
- Trang IT: 16 phép, chạy chính hàm dựng bảng của plugin Ghế (`tools/test/kiem-trang-it.php`).
- Trình duyệt thật + backend PHP thật qua bộ giả lập: sai PIN, bàn phím số, đẩy dữ liệu kỳ mới, đổi PIN,
  nhân viên tải ảnh và gửi khoản, kế toán duyệt (báo cáo cộng đúng), Admin thêm người dùng, chốt kỳ,
  nhật ký, chuyển hướng nhân viên, đăng xuất.

**Chưa kiểm trên WordPress thật của công ty** — mới chạy qua bộ giả lập. Việc đầu tiên nên làm là cài lên
một site thử rồi chạy lại luồng ở mục 6.

## 8. Việc còn để mở

- Mục II của File tổng báo cáo: file Excel gốc đang lỗi `#REF!` nên chỉ lấy được **Lương văn phòng**.
  Các cột lương vận hành, QL AC, ALEX, KPI, Bonus phải nhập tay.
- File Excel xuất ra có định dạng số và độ rộng cột nhưng **chưa tô màu / in đậm** — bản SheetJS miễn phí
  không hỗ trợ. Muốn có thì cần đổi sang thư viện khác (ExcelJS) hoặc bản SheetJS Pro.
- Bản web tĩnh trên GitHub Pages **chưa bật**: vào Settings → Pages → Source chọn "GitHub Actions".
  Không bật thì workflow `pages.yml` sẽ đỏ, không ảnh hưởng plugin.
- Đăng nhập hiện bằng PIN dùng chung kiểu app cũ. Muốn chặt hơn (mỗi người một tài khoản Google) thì
  cần thêm bước xác thực.
