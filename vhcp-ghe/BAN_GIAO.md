# Bàn giao — plugin ghế `vhcp-ghe`

Cập nhật: 2026-08-28 · Phiên bản hiện tại: **1.52.0** · Nhánh phát triển: `claude/posh-qr-kh1urz`
(Chỉ commit/push lên nhánh này, không mở PR nếu chưa được yêu cầu.)

Đây là plugin WordPress phục vụ trang ngoài `/ghe` (SPA đăng nhập bằng PIN) cho hệ thống thanh
toán ghế massage POSH của K&H. Tài liệu này ghi lại việc đã làm gần đây và **một vấn đề còn mở**
để người tiếp nhận không phải dò lại từ đầu.

---

## 1. Việc đã làm gần đây

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

---

## 2. VẤN ĐỀ CÒN MỞ — "Cơ sở phụ" không đẩy sang bên ghế

**Hiện tượng (anh Thắng 28/08/2026):** *"Nhân viên bên cơ sở phụ lại không đẩy thông tin sang, nó
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

### Nguồn hệ nhân sự KHÔNG nằm trong git (tại thời điểm bàn giao)
- Bản deploy **K&H Chấm công 2.94.0** ở `khmatrix.com/quan-tri-cham-cong` **không có** trong repo
  `khh-chamcong-firmware` lẫn repo `zairozy2004199x/Claude`.
- Repo `Claude` chỉ có bản HR **cũ hơn** (Apps Script `NhanSuKH-KHM`): **không có** trường "Cơ sở
  phụ" và **không có** phần đẩy sang ghế. Các chuỗi UI của bản 2.94.0 (`Hồ sơ & tài khoản`,
  `Máy & Firmware`, `Sửa đủ`, `Áp cho N dòng`…) không xuất hiện ở đâu trong hai repo.
- **Việc cần làm tiếp:** tìm đúng nguồn bản 2.94.0 (nhánh/repo khác, hoặc mã sửa thẳng trên server)
  rồi sửa chỗ tạo bản ghi đẩy sang theo mục "Cách sửa" ở trên. Bên ghế giữ nguyên.

---

## 3. Ghi chú kiến trúc nhanh (cho người tiếp nhận)

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

## 4. Cách kiểm tra & đóng gói (đã dùng suốt session)

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

## 5. Ràng buộc bắt buộc
- ⛔ **Repo CÔNG KHAI** — KHÔNG hardcode PIN / khoá / secret trong mã. PIN nằm ở nhân sự / `bc_pin`.
- Chỉ push nhánh `claude/posh-qr-kh1urz`; không mở PR nếu chưa được yêu cầu.
- Mỗi lần đổi tính năng: **tăng `VHG_VERSION`** ở `vhcp-ghe.php` (header `Version:` + `define`), vì
  `vhg_maybe_upgrade()` chạy `dbDelta` khi đổi phiên bản.
