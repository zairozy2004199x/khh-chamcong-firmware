# Cơ chế trang `/mua-ma` — để dựng trang `/mua-ve`

> Tóm tắt toàn bộ cách trang bán mã giảm giá `/mua-ma` (trong `vhcp-ghe`,
> file `includes/class-vhg-shop.php`) hoạt động, để bên vé (`vhcp-ve`) dựng
> `/mua-ve` theo đúng khuôn. Đọc kèm mã nguồn `class-vhg-shop.php` +
> `class-vhg-ma.php`.

## 0. Đường dẫn

- **Link khách:** `https://<tên-miền>/mua-ma/` (VD `https://khmatrix.com/mua-ma/`).
- Slug đổi được ở **Option** `vhg_slug_ma` (mặc định `mua-ma`) — `VHG_Shop::slug()`.
- 3 dạng URL cùng vào một trang (xem chú thích đầu `class-vhg-shop.php`):
  - `/mua-ma/AMTP01` — dạng thư mục, **cho mã QR dán trên ghế ngắn nhất**.
  - `/mua-ma/?ghe=AMTP01` — dạng tham số.
  - `/mua-ma/` — không gắn ghế nào.

## 1. Đăng ký trang (WordPress rewrite, KHÔNG dùng shortcode/page)

`VHG_Shop::init()` (móc `add_action('init', ['VHG_Shop','init'], 4)` trong `vhcp-ghe.php`):

```php
add_rewrite_rule('^'.slug().'/([A-Za-z0-9]{1,20})/?$', 'index.php?vhg_shop=1&vhg_ghe=$matches[1]', 'top');
add_rewrite_rule('^'.slug().'/?$',                     'index.php?vhg_shop=1', 'top');
add_filter('query_vars', fn($v)=>array_merge($v,['vhg_shop','vhg_ghe']));
add_action('template_redirect', ['VHG_Shop','phuc_vu'], 0);   // 0 = chạy trước redirect_canonical
```

- Nhận diện trang: `la_shop()` xét `get_query_var('vhg_shop')===1` **hoặc** tự khớp path
  (`/slug/mã`) vì luật rewrite có thể chưa flush kịp.
- **Flush rewrite** 1 lần sau khi cài (`vhg_flush_rewrite` option + `add_action('init','vhg_flush_rewrite',99)`).
- `remove_action('template_redirect','redirect_canonical')` để `/MUA-MA/`, dấu `/` thừa… không bị WP đá về 404.

## 2. Một endpoint, hai chế độ — `phuc_vu()`

`template_redirect → VHG_Shop::phuc_vu()`:

- Có `?api=<viec>` (GET/POST) → **trả JSON** qua `api()` rồi `exit`.
- Không có `api` → **in nguyên trang HTML** (headless, tự `<html><head>…`, KHÔNG theo theme)
  rồi `exit`. Trang nhúng sẵn:
  - `window.VHG_SHOP` = URL API (chính URL trang, mọi lượt gọi POST về `?api=…`).
  - `window.VHG_GHE` = mã ghế lấy từ địa chỉ (`ghe_tu_dia_chi()`), để hiện đúng giá/gói theo ghế.
  - Google Fonts + CSS + một khối `<script>` IIFE lớn (giống trang `/ghe`).

## 3. API (POST JSON tới `?api=<viec>`) — `api()`

`goi(viec, data, cb)` bên JS `POST` JSON `{...data}` tới `<url>?api=<viec>`; server đọc
`json_decode(php://input)`, đối chiếu `$viec`, trả JSON qua `tra()`. Các việc chính:

| `api=` | Vào | Ý nghĩa |
|---|---|---|
| `goi` | `VHG_Ma::ds_menh_gia($ghe)` | Danh sách **gói/mệnh giá + giá** (đã áp KM theo mã máy→cơ sở→mặc định) + cấu hình màn chào (`km_trang`). |
| `dat` | `VHG_Ma::dat_don($sdt,$pin,$menh_gia,$so_luong,$cc,$ma_may)` | **Tạo đơn**, trả `ma_don` + `phai_tra` + thông tin chuyển khoản + **chuỗi VietQR**. |
| `soi` | tra đơn | **Poll trạng thái đơn** (chờ tiền về). |
| `tra` | `phat_ma`/lấy mã | Đơn đã thanh toán → **phát mã** cho khách. |
| `cua_toi` | theo SĐT/PIN | Xem mã/đơn của tôi. |

Còn `dat_nap`, `dat_ghe`, `vi`, `tieu`, `lay_lai_pin`, `dung`… là các nhánh ví/khác — bỏ qua khi làm vé.

## 4. Luồng khách (client)

1. Vào `/mua-ma/` → **đập mặt MÀN CHÀO toàn màn hình** (splash, cấu hình `km_trang`, 3 khổ
   9:16 / 1:1 / 16:9 tự chọn theo thiết bị). Bấm **CTA** hoặc **✕** mới lộ trang gói.
   Tắt splash được bằng `cfg.bat=false`.
2. `goi('goi')` → vẽ danh sách **gói + giá** (đã áp khuyến mãi).
3. Khách chọn gói + nhập **SĐT** (+ PIN nếu có, + mã giới thiệu `cc`) → `goi('dat', {...})`.
4. Server tạo đơn (`don_ma`), trả **mã đơn + số tiền + số TK + chuỗi VietQR**.
   Client vẽ **QR VietQR** (nội dung CK = `MUA<mã đơn>`), nút tải QR.
5. Client **`goi('soi', {ma_don})` lặp** để chờ tiền về.
6. Tiền về (đối soát qua `vhcp-saoke`/webhook khớp nội dung `MUA<đơn>`) → đơn `da_thanh_toan`
   → `phat_ma()` sinh mã → client `goi('tra')` hiện **mã cho khách**.

## 5. Dữ liệu (bảng, trong `vhcp-ghe`)

- `don_ma` — **đơn mua mã**: `ma_don`, `sdt`, `pin_bam`, `menh_gia`, `so_luong`, `phai_tra`,
  `trang_thai`, `ref_ban`, `cc`… (`VHG_Ma::dat_don()` / `don()` / `phat_ma()`).
- Mã phát ra + mệnh giá + phạm vi giá (mã→cơ sở→mặc định): `VHG_Ma::giam_cua()` / `gia_ban()`.
- Cấu hình màn chào (canvas): Option `vhg_km_trang` (model `{bat,cta,trang:{9x16,1x1,16x9}}`).

## 6. Thanh toán VietQR

- Chuỗi EMVCo dựng **server** (mức sửa lỗi L, ~125 ký tự), `addInfo = "MUA<mã đơn>"`,
  tài khoản lấy từ cấu hình (`so_tk`/`bank_bin`).
- Đối soát **tự động**: `vhcp-saoke` nhận biến động số dư (webhook/CSV), khớp nội dung
  `MUA<đơn>` → cập nhật `don_ma.trang_thai` → `soi` thấy `đã trả` → phát mã.
- **KHÔNG để số TK / khoá merchant trong mã nguồn** (repo công khai) — tất cả ở Option/DB.

## 7. Dựng `/mua-ve` theo khuôn này (khác gì)

| Mảng | /mua-ma (mã giảm giá) | /mua-ve (vé) — đổi gì |
|---|---|---|
| Slug/route | `vhg_slug_ma` → `/mua-ma` | Option riêng → `/mua-ve`, cùng kiểu `add_rewrite_rule` |
| Đăng ký | `VHG_Shop::init` trên `add_action('init',…,4)` | Lớp riêng trong `vhcp-ve`, cùng cơ chế rewrite + `template_redirect` |
| `goi` | mệnh giá mã | **danh mục vé / suất / hạng** + giá |
| `dat` | tạo `don_ma` | tạo **đơn vé** (bảng đơn của vhcp-ve), trả VietQR y hệt |
| `soi` | chờ tiền | y hệt — poll trạng thái đơn |
| `tra`/`phat_ma` | phát **mã** | phát **vé** (mã vé/QR check-in) |
| Đối soát | `MUA<đơn>` qua saoke | đặt tiền tố riêng (VD `VE<đơn>`) qua saoke |
| Màn chào | canvas `vhg_km_trang` | dùng lại y hệt hoặc option `vhg_ve_trang` |

**Nguyên tắc giữ nguyên:** headless render (không theme) + một endpoint `?api=` trả JSON +
VietQR `addInfo` có tiền tố nhận diện + poll `soi` + đối soát tự động qua `vhcp-saoke`.

## Tham chiếu mã nguồn

- `vhcp-ghe/includes/class-vhg-shop.php` — đăng ký route, `phuc_vu()`, `api()`, render trang.
- `vhcp-ghe/includes/class-vhg-ma.php` — `ds_menh_gia()`, `dat_don()`, `don()`, `phat_ma()`, giá theo phạm vi.
- `vhcp-ghe/vhcp-ghe.php` — móc `add_action('init', ['VHG_Shop','init'], 4)` + flush rewrite.
