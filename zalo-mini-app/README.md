# POSH · Zalo Mini App — Bán vé khu vui chơi trả trước

Mini app để khách **mua vé khu vui chơi trước** qua Zalo: chọn vé → nhập tên + SĐT → hiện **mã QR
VietQR** để chuyển khoản → nhận **mã vé**. Backend dùng lại **WordPress khmatrix.com** (plugin
`vhcp-ghe`), REST công khai `vhg/v1/ve/*`.

> ⚠️ Đây là **Stage 1**: chọn vé → tạo vé → hiện QR + mã vé + theo dõi trạng thái. Phần *xác nhận
> đã nhận tiền tự động* và *dùng mã vé tại cổng* làm ở bước sau (xem cuối file). Đã có sẵn nút
> **"Đã thanh toán"** trong trang admin để duyệt tay khi test.

## 1. Backend (đã kèm trong vhcp-ghe ≥ 2.12.0)
- Danh mục vé: **Cài đặt → Vé khu vui chơi** (mỗi dòng `Tên | Giá | Mô tả`); xem/duyệt vé đã đặt ở đây luôn.
- File `includes/class-vhg-vemini.php` mở 3 API REST (không cần đăng nhập, có CORS):
  - `GET  /wp-json/vhg/v1/ve/goi` → danh sách vé + thông tin TK.
  - `POST /wp-json/vhg/v1/ve/dat` `{id, ten, sdt}` → tạo vé `cho` + trả chuỗi VietQR + mã vé.
  - `GET  /wp-json/vhg/v1/ve/trangthai?ma_ve=...` → trạng thái vé.
- Tự tạo bảng `..._bc_ve`. Số TK nhận tiền lấy từ **cấu hình chung** đã khai trong web (không
  hardcode). Nếu web chưa khai TK → API báo lỗi "chưa cấu hình tài khoản".
- Cài/ghi đè plugin `vhcp-ghe` như thường; vào **Cài đặt → Đường dẫn tĩnh (Permalinks) → Lưu**
  một lần cho chắc REST hoạt động.

## 2. Chạy mini app
```bash
npm install -g zmp-cli      # nếu chưa có
cd zalo-mini-app
npm install
# Sửa BASE trong src/api.ts cho đúng tên miền web của anh (mặc định https://khmatrix.com)
npm start                   # chạy thử trên trình duyệt (Zalo Studio)
```
- Đăng ký Mini App tại **Zalo for Developers** (https://mini.zalo.me) để lấy **App ID**, rồi
  `zmp login` và `npm run deploy` để đưa lên Zalo.
- Web (khmatrix.com) phải chạy **HTTPS** — Zalo Mini App chỉ gọi API qua HTTPS.

## 3. Màn hình
| File | Việc |
|---|---|
| `src/pages/home.tsx` | Lưới gói (giá + phút), chạm để mua |
| `src/pages/buy.tsx` | Nhập tên + SĐT → tạo vé |
| `src/pages/ticket.tsx` | QR VietQR + mã vé + số TK/nội dung (chạm để chép) + tự cập nhật trạng thái |
| `src/api.ts` | Gọi REST `vhg/v1/ve/*` — **đổi `BASE` ở đây** |

## 4. Bước sau (Stage 2+)
- **Xác nhận đã nhận tiền TỰ ĐỘNG**: nối vé với đối soát ngân hàng (khớp nội dung CK = mã vé) →
  vé tự `cho` → `da_tt` (màn vé tự đổi sang "Đã thanh toán"). Hiện tại đã có nút duyệt tay trong
  admin (Cài đặt → Vé khu vui chơi).
- **Dùng vé tại cổng khu vui chơi**: nhân viên soát vé quét/nhập mã vé → đánh dấu đã dùng (chống
  dùng lại), hoặc in vé.
- **Đăng nhập Zalo** (zmp-sdk `getUserInfo`) để tự điền tên/SĐT + lưu lịch sử vé của khách.
