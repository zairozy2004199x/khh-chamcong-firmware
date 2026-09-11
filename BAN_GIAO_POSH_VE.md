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
| Plugin bán vé | `vhcp-ve/vhcp-ve.php` + `vhcp-ve/assets/ve.js` | **1.46.0** |
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

> Shortcode: `[posh_ve]` trang bán vé · `[posh_ql]` quản trị vé · **`[posh_soat]` màn hình soát vé tại quầy** (nhân viên chọn cơ sở + gõ PIN một lần, máy nhớ cho cả ca).


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

### 🔴 VÍ TIỀN — ba luật không được đổi (từ 1.49.0)
1. **Chủ ví là tài khoản Zalo**, không phải số điện thoại. Số điện thoại không phải bí mật: tra ví
   theo số là ai gõ số người khác cũng tiêu được tiền của họ.
2. **Trừ tiền bằng MỘT câu `UPDATE … WHERE so_du >= %d`** (`POSH_Ve::vi_tru`), không "đọc rồi mới
   ghi". Đọc-kiểm-ghi là hai lượt bấm gần nhau (hai tab, hoặc bấm lại vì mạng chậm) cùng thấy đủ
   tiền rồi cùng trừ — tiêu 100k hai lần từ một ví 100k.
3. **Cộng ví cũng chỉ một lần**: `tu_khop_nap()` chuyển trạng thái bằng
   `UPDATE … WHERE ma=%s AND trang_thai='cho'`; lượt thứ hai không đổi được dòng nào nên không
   cộng thêm. Trang hỏi 5 giây một lượt và mở được hai tab.

Mệnh giá nạp **phải có trong bảng gói** (WP Admin → Vé khu vui chơi → Ví tiền); trang không nhận
số khách tự gõ, vì "tặng thêm" đi theo gói. Số lượt của mã ưu đãi chỉ tăng **khi tiền thật sự về**
— tăng lúc tạo mã thì mã hết sạch vì những người không chuyển tiền. Lưu lại cấu hình mã **giữ
nguyên số lượt đã dùng** của mã cùng tên.

Sổ cái `pve_vi_gd` ghi từng lượt cộng/trừ kèm số dư sau. Đừng bỏ: khách kêu "mất tiền" mà chỉ có
mỗi một con số thì không đối chiếu được gì.

### ⚠️ Popup có BỐN bước dùng chung một khung — đừng đặt trùng tên lớp
Ví vé · nạp ví · giỏ · đặt lẻ · mã QR nằm cùng `.pve-mask`. `qs()` lấy **thẻ đầu tiên trong cả
popup**, nên thêm bước mới mà trùng tên lớp là nó **cướp** lượt tra của bước cũ — im lặng, không
lỗi. Đã dính ở 1.48.0: bước Giỏ có nút `.pve-go` đứng trước bước đặt lẻ, việc gắn cho nút "Tạo mã
thanh toán" đi lạc sang nút của Giỏ và **bấm Đặt vé không ra gì** (vá ở 1.48.1). Nay bước đặt lẻ
tra bằng `qf()`, bước nạp bằng `qf2()` — đều buộc phạm vi. Nút "Trả bằng ví" cố tình **không** mang
lớp `.pve-go` mà dùng `.pve-go2`.

### Vòng đời một tấm vé (từ 1.48.0)
```
khách bấm ＋ -> giỏ -> đặt -> QR chuyển khoản
                                  |
              Sao Kê nhận tiền vào -> POSH_Ve::tu_khop() -> trạng thái da_tt
                                  |
        ví vé của khách (mã + QR)  |  màn hình [posh_soat] ở quầy kêu chuông
                                  \-> nhân viên quét -> r_soat() -> da_dung + coso_dung
```

### ⚠️ Chuyển khoản VietQR trước đây KHÔNG BAO GIỜ tự xác nhận
Vé chỉ chuyển sang "đã thanh toán" qua hai lối: IPN của Momo/VNPay, hoặc quản trị bấm tay. Khách
quét mã VietQR chuyển tiền xong thì vé nằm mãi ở *"Chờ thanh toán"* — tiền đã vào tài khoản mà hệ
thống không biết, nhân viên soát vé cũng không dám cho vào.

`POSH_Ve::tu_khop()` bắc cầu sang plugin Sao Kê: tìm giao dịch **tiền vào** có nội dung chứa đúng
chuỗi in trên mã QR (`VE` + mã vé) và số tiền không thiếu. Nội dung ấy là duy nhất cho từng vé nên
một lượt chuyển khoản không thể khớp cho hai vé. Gọi ngay trong lúc trang khách hỏi trạng thái
(mỗi 5 giây) → tiền vào là vé xanh gần như tức thì. **Cần cài plugin Sao Kê trên cùng site.**

### 🔴 Quyết định cho vào cửa nằm ở MÁY CHỦ
Màn hình `[posh_soat]` chỉ gửi mã lên và in lại câu trả lời. Mọi luật — đã trả tiền chưa, đã dùng
rồi chưa, dùng ở đâu — kiểm trong `POSH_Ve::r_soat()`. Để trang tự kết luận thì sửa vài dòng trong
trình duyệt là vé nào cũng "hợp lệ". Vé mua từ xa (`coso` rỗng) hiện ở **mọi** cơ sở — khách mua
trước ở nhà rồi tới cơ sở nào cũng vào được; đổi luật ấy là vé mua trước không dùng được ở đâu cả.

### 🔴 Ví vé nằm Ở MÁY KHÁCH, máy chủ chỉ làm tươi
Trang gửi lên danh sách mã vé mà **chính máy ấy** đã mua (localStorage), máy chủ trả trạng thái mới
nhất. Đừng đổi thành "tra vé theo số điện thoại": số điện thoại không phải bí mật, ai gõ số người
khác cũng xem được vé của họ — mà mã vé chính là thứ đưa ra cổng để vào cửa. Đăng nhập Zalo thì
máy chủ **gộp thêm** vé mua bằng tài khoản ấy trên máy khác (danh tính do cookie đã ký xác nhận).

### ⚠️ Mã QR chuyển khoản dựng Ở MÁY CHỦ — đừng quay lại kiểu tải thư viện từ CDN
Trước 1.47.0 trang khách tải `qrcodejs` từ `cdnjs.cloudflare.com` rồi mới vẽ. Trên site thật nó
ra đúng câu dự phòng *"(Không tải được mã QR — dùng nội dung CK bên dưới)"*: khách đang cầm điện
thoại định quét thì phải tự gõ số tài khoản và nội dung — gõ sai một ký tự trong nội dung là tiền
vào mà đơn không tự khớp.

Nay `POSH_Ve::qr_svg()` dựng sẵn SVG bằng `VHG_QRVe` của plugin Ghế và trả kèm trong `qr_svg`.
Đã đối chiếu **khớp từng ô** với thư viện chuẩn (`python-qrcode`, version 6 / ECC M / chế độ
alphanumeric) cho đúng loại chuỗi VietQR này. Thiếu plugin Ghế thì trả rỗng và trang tự lùi về
cách cũ — nên **Ghế và Vé phải cùng cài trên một site**.

### ⚠️ Đăng nhập Zalo trên web KHÔNG cho số điện thoại
OAuth v4 chỉ trả `id`, `name`, `picture`. Số điện thoại chỉ lấy được trong **Zalo Mini App**
(`getPhoneNumber` → `POSH_Ve::r_zalo_sdt()`). Nên từ **1.46.0** mỗi vé ghi thêm cột `zalo_id`, và
`GET /wp-json/posh/v1/zalo/toi` trả về tên + ảnh + **SĐT của vé gần nhất cùng Zalo ID** để trang
điền sẵn hai ô Họ tên / Số điện thoại. Lần đầu khách vẫn phải gõ, từ lần hai là có sẵn — không
phải dựng thêm kho dữ liệu nào để nhớ.

Trang chỉ điền vào ô **đang trống**: khách mua hộ người khác mà bị ghi đè tên là vé xuất sai tên.

Từ **1.47.0** trang còn nhớ tên + SĐT ngay trên máy khách (`localStorage` khoá `posh_ve_kh`) sau
mỗi lượt đặt được vé: máy chủ chỉ tra được số từ vé **đã** đặt, mà thông tin Zalo lấy về từ lúc
mở trang — không nhớ tại máy thì đặt xong vé thứ nhất, mở vé thứ hai trong cùng phiên vẫn phải gõ
lại. Cách này chạy cho cả khách không đăng nhập Zalo. Nhãn "Mua bằng tài khoản Zalo" **chỉ** hiện
khi đúng là đăng nhập Zalo — dán nhãn ấy lên thông tin gõ tay là nói sai nguồn gốc của nó.

### ⚠️ Popup bị chuyển ra ngoài `.pve-page` — biến màu phải khai lại
`.pve-mask` và `.pve-wel` được JS `appendChild` thẳng vào `<body>` (để nền mờ phủ kín, khỏi vướng
theme bọc `transform`). Ra khỏi `.pve-page` là mất bộ biến `--tx/--sf/--g…` khai ở đó, mà
`color:var(--tx)` khi `--tx` không tồn tại **không phải** là bỏ qua — cả dòng thành "không hợp lệ
lúc tính giá trị", `color` tụt về kế thừa, tức màu mặc định của theme: **chữ đen trên nền đen**
(lỗi 11/09/2026, vá ở 1.46.0). Bộ biến được khai lại cho `.pve-mask, .pve-wel`; đổi màu ở
`.pve-page` thì phải đổi cả ở đó.

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
