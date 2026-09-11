# BÀN GIAO — Hệ thống bán vé POSH (Web + Zalo Mini App)

**Công ty:** K&H COM., LTD · **Website:** khmatrix.com · **Ngày bàn giao:** cập nhật theo lần push cuối
**Kho mã nguồn (GitHub):** `zairozy2004199x/khh-chamcong-firmware` · **Nhánh:** `claude/posh-qr-kh1urz`

Hệ thống gồm **2 phần dùng chung một backend** (dữ liệu vé/đơn/khách đồng bộ 2 chiều):
1. **Plugin WordPress `vhcp-ve`** (cài trên khmatrix.com) — backend REST API + trang bán vé + trang quản trị.
2. **Zalo Mini App** (`zalo-mini-app/`) — app khách hàng trên Zalo.

---

## 1. Thành phần & phiên bản

| Thành phần | Vị trí | Phiên bản cuối |
|---|---|---|
| Plugin bán vé | `vhcp-ve/vhcp-ve.php` + `vhcp-ve/assets/ve.js` | **1.45.0** |
| Zalo Mini App | `zalo-mini-app/` | deploy qua `zmp` |
| Mini App ID (Zalo) | — | **1014095630057742680** |

---

## 2. Cài / cập nhật PLUGIN WEB

1. WP Admin → **Plugins → Add New → Upload Plugin** → chọn `vhcp-ve.zip` → **Install** → nếu hỏi bấm **Replace current with uploaded** → **Activate**.
2. Vào **SpeedyCache → Clear Cache** (bắt buộc, nếu không sẽ thấy bản cũ).
3. Mở trang bằng **tab ẩn danh** để kiểm tra.

> Nếu upload báo lỗi kích hoạt: kiểm tra **Plugins** có 2 bản plugin vé trùng không → xoá bản cũ, giữ 1 bản. (Bản mới đã có "khiên" chống lỗi class trùng.)

## 3. Deploy ZALO MINI APP (PowerShell, Windows)
```powershell
cd "$HOME\Downloads\posh-moi\zalo-mini-app"
git pull                # hoặc giải nén zip mới đè vào thư mục này
npm.cmd install
npx.cmd zmp login       # nếu báo "Permission denied. Please login again."
npx.cmd zmp deploy
```
- Deploy hỏi: **Version status** = Development; **Description** = tuỳ; **Mini App ID** = `1014095630057742680`.
- Deploy xong **quét mã QR mới** hiện ra (bản publish cũ KHÔNG tự cập nhật).

---

## 4. Các trang & đường dẫn

| Trang | Đường dẫn | Shortcode |
|---|---|---|
| Bán vé (khách) | `khmatrix.com/mua-ve` | `[posh_ve]` |
| Quản trị vé (marketing, đăng nhập PIN) | `khmatrix.com/quan-tri-ve` | `[posh_ql]` |
| Quản trị WordPress (admin) | WP Admin → **Vé khu vui chơi** | — |

Cả 2 trang tự được tạo khi kích hoạt plugin. Trang quản trị hiện link ngay trong WP Admin.

---

## 5. Tính năng đã hoàn thành

### Trang bán vé `[posh_ve]` (giao diện tối/vàng gold, full màn)
- Hero, banner, màn chọn khu vực, form đặt vé nhanh.
- Danh sách vé theo nhóm, tích điểm/hạng thành viên, gợi ý vé theo định vị GPS.
- **Đăng nhập Zalo (OAuth)** — đồng bộ tài khoản với app; admin thấy nút **🔧 Quản trị vé**.
- **Chọn thanh toán:** QR ngân hàng (VietQR) / Momo / VNPay.
- Chân trang công ty + phiên bản; ẩn header/footer theme + admin bar.

### Trang quản trị `[posh_ql]` (kiểu HRM: sidebar + tab con, đăng nhập PIN)
- **📊 Tổng quan:** Lợi nhuận / Tổng đơn / Giá vốn / Vé đã bán, doanh thu hôm nay–tháng, biểu đồ 7 ngày, bán theo kênh (Zalo/Web), vé bán chạy (có ảnh). Lọc **Tất cả/Tuần/Tháng** + **theo loại vé**.
- **🎟️ Vé:** tạo/sửa/xoá vé + upload ảnh → tự lên web + Zalo.
- **🧾 Đơn hàng & soát vé:** lọc trạng thái/kênh, tìm, xác nhận Đã TT / Đã dùng / Huỷ.
- **👥 Khách hàng:** danh sách khách đã mua (tên, SĐT, điểm, hạng, số đơn, tổng chi) + tìm + sắp xếp.
- **🎁 Ưu đãi:** tạo/sửa/xoá ưu đãi + ảnh + hạng tối thiểu + hạn dùng.
- **🏅 Hạng thành viên:** sửa tên hạng + điểm mốc.

### Zalo Mini App
- 5 tab: Trang chủ / Danh mục / Giỏ hàng / Tin nhắn / Cá nhân.
- Đăng nhập Zalo lấy tên+SĐT, tự điền khi thanh toán.
- Chọn thanh toán QR/Momo/VNPay; giỏ hàng nhiều vé.
- Khu **Quản lý** (PIN): báo cáo, đơn, soát vé + nút mở trang quản trị web.

### Backend dùng chung
- REST namespace `posh/v1`. Bảng `pve_ve` (đơn/vé), `pve_tv` (khách/điểm).
- 1 điểm = 1.000đ. Trạng thái vé: cho / da_tt / da_dung / huy. Kênh: web / zalo.

---

## 6. Cấu hình cần khai trong WP Admin → "Vé khu vui chơi"

| Mục | Ghi chú |
|---|---|
| **Tài khoản nhận tiền** | Số TK + BIN ngân hàng + chủ TK (để tạo VietQR). |
| **Cổng thanh toán Momo/VNPay** | Khoá merchant + hiện sẵn IPN URL để khai bên Momo/VNPay. |
| **PIN khu quản lý** | Mã PIN cho trang `/quan-tri-ve` và khu Quản lý trên Zalo. **Bắt buộc đặt.** |
| **Zalo** | App ID / Secret / Callback / Mã xác thực domain / **Zalo ID quản trị**. |
| **Cơ sở & toạ độ, Ưu đãi, Hạng thành viên, Chân trang** | — |

> ⚠️ **BẢO MẬT:** Kho mã nguồn **CÔNG KHAI**. Mọi khoá/PIN/số TK/khoá cổng đều **khai trong admin (DB)**, KHÔNG nằm trong mã nguồn. Giữ nguyên nguyên tắc này khi phát triển tiếp.

---

## 7. Việc CHƯA làm (bàn giao cho đợt sau)

1. **ZNS — tự nhắn Zalo cho khách** (đã chốt hướng, chưa code): gửi tin "thanh toán thành công" + nhắc chưa TT tối đa 2 lần rồi dừng. Cần: OA access/refresh token + 2 mẫu ZNS được Zalo duyệt (trả phí). Chạy nền bằng WP-Cron, đếm số lần nhắc trong DB.
2. **Khuyến mãi / mã giảm giá** — tính năng mới (bảng mã, giới hạn lượt, áp mã lúc thanh toán).
3. **Thương hiệu / Danh mục ưu đãi** — gom nhóm (hiện dùng trường "nhóm").
4. **Thu thập giới tính / độ tuổi khách** lúc mua → để lọc & thống kê theo tuổi/giới tính.
5. Momo/VNPay: cần nhập khoá merchant thật + tạo mẫu để chạy thanh toán điện tử (QR ngân hàng đã chạy).

---

## 8. Quy ước phát triển tiếp
- Plugin có **hai** file phải để mắt: `vhcp-ve/vhcp-ve.php` (PHP) và `vhcp-ve/assets/ve.js`
  (toàn bộ việc chạy máy của trang khách). Luôn `php -l` file PHP **và** `node --check` file JS
  trước khi đóng gói.

### ⚠️ Việc chạy máy của trang khách nằm ở TỆP NGOÀI — đừng nhét lại vào trang
Từ bản **1.45.0**, gần 450 dòng JS của `[posh_ve]` chuyển từ khối `<script>` nhúng trong đầu ra
shortcode sang `vhcp-ve/assets/ve.js`, nạp bằng `wp_enqueue_script()`, dữ liệu máy chủ đi qua
`wp_localize_script()` → `window.PVE_DATA` (`rest`, `ban`, `cs`).

Vì sao: ngày 11/09/2026 khách "bấm không mua được" mấy lượt liền. Màn Kiểm tra hệ thống xanh hết
(bảng đủ cột, tài khoản nhận tiền đủ, thử ghi vé thành công) → máy chủ sạch. Nút 🩺 do máy chủ
dựng thì HIỆN → PHP bản mới đã sống. Nhưng bấm nút không ra bảng, mà bảng ấy do chính khối script
nhúng gắn → **khối script nhúng không hề chạy**: bị plugin gộp/nén JS, tường lửa lọc thẻ script,
hoặc `wp_kses` của trình dựng trang nuốt mất. Hỏng kiểu ấy im lặng — không lỗi, không báo.

Ba dấu hiệu đọc được ngay, không cần mở console:
| Nhìn ở đâu | "chưa chạy" nghĩa là | "JS 1.45.0 ✓" nghĩa là |
|---|---|---|
| Chân trang, cạnh dòng bản quyền | `ve.js` không nạp được (404 / bị chặn) | tệp ngoài đã chạy |
| Chữ nhỏ trên nút 🩺 (chỉ admin, hoặc thêm `?soi=1`) | như trên | như trên |
| Bảng 🩺 dòng "Script trong trang" | thẻ `<script>` nhúng bị nuốt | script nhúng vẫn sống |

`ve.js` có đường dự phòng: mất `PVE_DATA` thì tự đoán địa chỉ REST từ trang đang mở và bỏ phần
giảm giá tại quầy — **nút mua vé vẫn sống**. Đừng bỏ đường dự phòng ấy đi.

- Tăng số **Version** trong header plugin mỗi lần sửa (để biết bản nào đang chạy).
- App Zalo: sửa trong `zalo-mini-app/src/`, `npx tsc --noEmit` để kiểm lỗi, rồi `zmp deploy`.
- Commit + push lên nhánh `claude/posh-qr-kh1urz`.
