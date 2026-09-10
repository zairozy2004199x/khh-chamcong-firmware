# Bàn giao — Trang Ghế (plugin `vhcp-ghe`)

Hệ thống quản lý ghế massage & doanh thu POSH/K&H, chạy trên WordPress tại **khmatrix.com**.
Bản đang chạy: **2.20.1**. Repo: <https://github.com/zairozy2004199x/khh-chamcong-firmware>
(nhánh phát triển `claude/posh-qr-kh1urz`).

> ⚠️ Repo **CÔNG KHAI**. Tuyệt đối không đặt PIN / khoá / token / số tài khoản ngân hàng /
> khoá merchant trong mã nguồn. Tất cả cấu hình nhạy cảm nằm trong **Option của WordPress** (DB).

---

## 1. Cài đặt / cập nhật

1. WordPress → **Plugins → Add New → Upload Plugin** → chọn `dist/vhcp-ghe.zip` → *Install* →
   nếu hỏi thì **Replace current** → **Activate**.
2. Sau khi cài đè: bấm **Ctrl + F5** để trình duyệt bỏ bản JS cũ trong cache.
3. Kiểm tra số bản: mở trang, số phiên bản in thẳng ra (biến `VHG_BAN`) — phải là **2.20.1**.
   Nếu vẫn thấy số cũ = máy chủ còn giữ bản cũ hoặc chưa kích hoạt lại.

`vhcp-ghe` và `vhcp-saoke` **dùng chung một database** và đọc chéo bảng của nhau, nên cả hai
plugin phải cùng cài trên một site.

## 2. Truy cập trang

- URL: **`/ghe`** (slug đổi được qua option `vhg_slug`). Trang là **SPA một trang** — máy chủ
  render `<div id="app">` rỗng rồi JS dựng toàn bộ giao diện.
- Đăng nhập bằng tài khoản WordPress; phân quyền theo **vai trò** (xem mục 4).
- Người đứng quầy có thể "Thêm vào màn hình chính" điện thoại để dùng như app.

## 3. Các tab chính

**Kế toán / Quản trị**
- 📊 Đối soát · 📋 Báo cáo doanh thu · 🎁 Mã giảm giá · 💵 Thu tiền
- 🧾 Quỹ & nộp tiền · **💰 Doanh thu đã nộp** (mới) · 📈 Duyệt báo cáo · ⚖️ Đề nghị & yêu cầu
- 💰 Đối soát & công nợ · 📤 Xuất MISA · 📊 Báo cáo tổng · 🏢 Doanh thu địa điểm · 📥 Nhập doanh thu cũ

**Vận hành ghế**
- ⚡ Kích hoạt ghế · 🪑 Quản lý ghế · 🔖 Gắn mã máy · 🔌 Lịch sử tắt mở máy · 📋 PIN báo cáo
- 🎛 Điều khiển ghế · 🚨 Ghế lỗi · 📞 Hỗ trợ khách · ⬆️ Nạp firmware · ⚙️ Cấu hình

## 4. Phân quyền (`VHG_Auth::quyen_cua`)

- **quan_tri** — Admin/Quản lý: toàn quyền (thêm/xoá cơ sở & ghế, gán mã, cấp PIN báo cáo,
  xem doanh thu cả chuỗi).
- **chot_doanh_so** — được chốt/duyệt doanh số, xem báo cáo tổng.
- **giup_khach** — vận hành hỗ trợ khách (điều khiển ghế, báo lỗi).
- Chưa khai vai trò nào = mặc định Admin + Quản lý. **Admin luôn có quyền** dù khai kiểu gì.

## 5. Tab "Doanh thu đã nộp" (mới, 2.20.x)

Đối chiếu **tiền mặt phải nộp** với **số đã nộp thật vào ngân hàng**:

- Với mỗi cơ sở lấy **MÃ NỘP** (đặt bên plugin Sao Kê → tab *Cấu hình mã*) rồi dò trong
  **nội dung giao dịch sao kê ngân hàng** (`saoke_gd`, loại `in`, lọc theo ngày) → cộng ra
  cột **Đã nộp**. **Còn lại = Tiền mặt phải nộp − Đã nộp** (đỏ nếu còn thiếu).
- Tiền **QR** về thẳng bank nên không tính vào phần nộp tay.
- **Quản trị / chốt doanh số**: thấy tất cả cơ sở + ô lọc **Nhân viên** (theo `bc_pin`); chọn
  một người thì bảng và 3 thẻ tổng lọc lại đúng cơ sở người đó phụ trách.
- **Nhân viên thường**: chỉ thấy cơ sở trong **phạm vi PIN** của mình.
- Cơ sở **chưa đặt mã** hiện nhãn "chưa đặt mã" → vào Sao Kê đặt mã cho nó.

## 6. Liên thông với plugin Sao Kê (`vhcp-saoke`)

- **Sao Kê đọc của Ghế**: bảng `wp_vhg_coso`, `wp_vhg_may` (để lấy "Cửa hàng chuẩn" và tự gán
  cơ sở cho giao dịch VietQR = doanh thu POSH).
- **Ghế đọc của Sao Kê**: bảng `wp_saoke_gd`, `wp_saoke_cong`, và option `saoke_coso_ma`
  (mã nộp tiền mặt theo cơ sở) — dùng cho tab *Doanh thu đã nộp* và lớp *VietQR thực* trong
  *Báo cáo tổng* (nhãn **VIETQR** màu đỏ = tiền về thật).
- Mã nộp có dạng `KH705MTDMB0089` = `KH{705|989}{MTD|KVC}{MB|MN}{4 số}`.

## 7. Firmware ghế (tham chiếu)

Firmware ESP32 và máy trạm nằm cùng repo (`esp32_*`), nạp qua tab **Nạp firmware**. Bí mật nạp
máy (token/mật khẩu) đi qua thẻ SD `token.txt` — **đã gitignore**, không lọt vào repo công khai.

## 8. Quy trình phát triển

- Sửa mã → `php -l` từng file PHP; JS trong `class-vhg-trang.php` là heredoc `js()`/`js_baocao()`,
  **luôn `node --check` trọn khối** trước khi giao (một dấu `}` thừa là trắng cả trang — đúng lỗi
  đã gặp ở 2.20.0, vá ở 2.20.1).
- Tăng số bản ở `vhcp-ghe.php` (header `Version:` + hằng `VHG_VERSION`).
- Build lại `dist/vhcp-ghe.zip`, commit, push nhánh phát triển.
