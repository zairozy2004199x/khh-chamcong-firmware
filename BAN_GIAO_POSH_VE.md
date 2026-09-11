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
| Plugin bán vé | `vhcp-ve/vhcp-ve.php` + `vhcp-ve/assets/ve.js` + `vhcp-ve/assets/soat.js` | **1.59.0** |
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
| Soát vé tại quầy (nhân viên) | `khmatrix.com/soat-ve` · mỗi quầy một link `?cs=MÃCS` | `[posh_soat]` |
| Quản trị WordPress (admin) | WP Admin → **Vé khu vui chơi** | — |

Cả 3 trang tự được tạo khi kích hoạt plugin. Trang quản trị hiện link ngay trong WP Admin.

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

### 🏪 Cơ sở bán chạy đo **HAI** con số khác nhau — đừng gộp làm một (từ 1.59.0)
Màn **🏪 Cơ sở bán chạy** (`/ql/coso-bc`) xếp hạng cửa hàng bằng hai thước đo, và chúng **không**
thay nhau được:

| Cột | Lấy ở đâu | Trả lời câu hỏi |
|---|---|---|
| **Bán tại chỗ** | `pve_ve.coso` — quầy gắn vào đơn lúc khách quét mã tại cửa hàng | cửa hàng **bán** được bao nhiêu |
| **Đã soát** | `pve_ve.coso_dung` — quầy bấm xác nhận lúc khách vào cửa | cửa hàng **đón** bao nhiêu lượt khách |

Vé mua từ xa rồi vào chơi ở cơ sở X: `coso` trống, `coso_dung` = X. Gộp hai cột lại là mất luôn
thông tin ấy, mà đó chính là cái cần để biết cơ sở nào đang "ăn" khách của kênh trực tuyến.
Vì thế **Mua từ xa** đứng thành một dòng riêng (khoá `@xa`), không bị nhét vào cửa hàng nào.

Hai điểm dễ làm hỏng số liệu:
- **Gộp tên bằng `squash_cs()`** (bỏ dấu + bỏ ký tự lạ + viết hoa) trước khi cộng. Tên cơ sở do
  người gõ tay vào đơn, nên "FunZone Hà Nội" và "Funzone Ha Noi" là **một** cửa hàng; không bóp
  tên thì bảng xếp hạng tách đôi doanh thu của họ.
- **Cơ sở bán 0 vé vẫn phải có trong bảng.** Danh sách dựng từ bảng cơ sở đã khai, rồi mới cộng số
  vào — chứ không phải `GROUP BY` rồi lấy những gì có. Cửa hàng doanh số 0 biến mất khỏi báo cáo
  là đúng cái cửa hàng cần nhìn nhất.

### 🔴 Link quét mã theo cửa hàng — tiện, KHÔNG phải bảo mật (từ 1.59.0)
Mỗi nhân viên mở một đường dẫn riêng: `<trang soát vé>/?cs=MÃCS`. Trang tự tạo khi kích hoạt
plugin (`bao_dam_trang_soat()`, mặc định `/soat-ve`, nội dung `[posh_soat]`); link lấy sẵn ở màn
**🏪 Cơ sở bán chạy**, mỗi thẻ cửa hàng có nút **Chép**.

Có `?cs=` thì `soat.js` **khoá** ô chọn quầy, đổi nhãn thành "Quầy (đã khoá theo link)", gỡ nút
"Đổi quầy", và **ghi đè ca đang lưu trong máy** nếu ca ấy là quầy khác.

⚠️ Khoá này là để nhân viên **khỏi phải chọn**, không phải để chặn ai: sửa `?cs=` trên thanh địa
chỉ là đổi được quầy. Cửa thật vẫn là **PIN**, và mọi luật vẫn do máy chủ chốt ở `r_soat()`. Giá
trị thật của nó: nhân viên quầy A không lỡ tay soát vé vào sổ quầy B — lỗi đó rất khó thấy, vì vé
vẫn "đã dùng", chỉ sai chỗ, và bảng ở trên sẽ ghi nhầm lượt khách suốt cả tháng.

### Tỉ lệ điểm thưởng khai trong web quản trị (từ 1.59.0)
Trước đây "1 điểm = 1.000đ" nằm cứng trong mã. Từ 1.59.0 nó là tuỳ chọn `pve_tien_moi_diem` (mặc
định 1000), sửa ở màn **🏅 Hạng thành viên**, và `cong_diem()` đọc qua `tien_moi_diem()`.

⚠️ Đổi tỉ lệ **chỉ áp cho đơn mua sau đó**. Điểm đã cộng cho khách nằm sẵn ở `pve_tv`, không được
tính lại — tính lại là tự ý sửa hạng của khách đã lên hạng, chuyện đó phải do anh Thắng quyết chứ
không phải tác dụng phụ của một lần sửa ô số. Màn hình có ghi rõ dòng cảnh báo này.

### Đơn nạp ví nằm ở bảng RIÊNG — màn Đơn hàng không thấy
`pve_nap` khác `pve_ve`, nên màn **Đơn hàng & soát vé** không bao giờ liệt kê lệnh nạp. Từ
**1.54.0** có màn riêng **💸 Đơn nạp ví**: lọc theo trạng thái, tìm theo mã/tên/SĐT, và **xác nhận
tay** khi luồng SePay chưa thông.

⚠️ Xác nhận tay vẫn **thử khớp sổ phụ trước**; khớp được thì sổ cái ghi "Nạp ví", không khớp mới
ghi "quản trị xác nhận tay" — sau này soi lại còn biết đồng nào máy tự nhận, đồng nào người gật.
Việc đổi trạng thái nằm trong `nap_xong()` bằng **một** câu `UPDATE … WHERE trang_thai='cho'`:
cron, trang khách và quản trị có thể chạy cùng lúc, tách ra hai nơi tự làm là có ngày cộng ví hai
lần.

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

### Khai khuyến mãi ở ĐÂU
Từ **1.50.1**, trong khu quản trị `[posh_ql]`:

| Khai gì | Ở màn |
|---|---|
| Tin ưu đãi hiện trên Zalo | 🎁 **Ưu đãi** |
| **Mã ưu đãi khi nạp ví** | 🎁 **Ưu đãi** (khối dưới) |
| Mệnh giá nạp ví | 💰 **Ví tiền** |

Mọi khuyến mãi nằm chung một màn — khai ở hai nơi là sớm muộn chạy một chương trình mà quên nửa
kia. Màn WP Admin cũ vẫn còn và **đọc/ghi cùng một cặp option** (`pve_vi_goi`, `pve_vi_code`) —
đừng tách thành hai kho dữ liệu, hai màn sẽ nói hai con số khác nhau và không biết tin màn nào.

⚠️ Nút Lưu và chỗ báo kết quả của khối mã nay có ở **cả hai màn**. `$()` trong khu quản trị chỉ
trả thẻ **đầu tiên**, nên phải gắn/ghi cho tất cả (`viBao()`): bấm Lưu ở màn này mà câu báo hiện ở
màn kia thì người ta tưởng bấm không ăn rồi bấm lại.

### Mã ưu đãi theo CỬA HÀNG
Mỗi mã có ô **Cơ sở áp dụng** (WP Admin → Vé khu vui chơi → Ví tiền). Để trống = dùng mọi nơi;
chọn một cơ sở thì mã **chỉ ăn khi khách đang đứng tại đó** — máy chủ tự kiểm bằng toạ độ
(`POSH_Ve::cs_dang_dung`), không tin theo tham số trang khách gửi lên, nếu không thì ai cũng gõ
được mã của quầy đông khách nhất mà chẳng cần tới.

⚠️ Cơ sở phải đã khai **toạ độ + bán kính**, không thì mã không bao giờ ăn.
⚠️ `cs_dang_dung()` **không** gọi `giam_tai_cho()`: hàm ấy trả false cho cơ sở chưa khai % giảm,
mà "đang đứng ở đâu" với "cơ sở ấy có giảm giá vé không" là hai câu hỏi khác nhau.
Tên cơ sở so bằng `squash_cs()` (bỏ dấu, bỏ khoảng trắng, về hoa) — khai tay thì "Funzone Hà Nội"
và "FUNZONE HÀ NỘI" phải là một.

### ⚠️ Popup có BỐN bước dùng chung một khung — đừng đặt trùng tên lớp
Ví vé · nạp ví · giỏ · đặt lẻ · mã QR nằm cùng `.pve-mask`. `qs()` lấy **thẻ đầu tiên trong cả
popup**, nên thêm bước mới mà trùng tên lớp là nó **cướp** lượt tra của bước cũ — im lặng, không
lỗi. Đã dính ở 1.48.0: bước Giỏ có nút `.pve-go` đứng trước bước đặt lẻ, việc gắn cho nút "Tạo mã
thanh toán" đi lạc sang nút của Giỏ và **bấm Đặt vé không ra gì** (vá ở 1.48.1). Nay bước đặt lẻ
tra bằng `qf()`, bước nạp bằng `qf2()` — đều buộc phạm vi. Nút "Trả bằng ví" cố tình **không** mang
lớp `.pve-go` mà dùng `.pve-go2`.

Dính **lần thứ ba** ở 1.49.0: dòng "chưa khai gói nạp" dùng lại lớp `.pve-nap-tt` của dòng tổng
kết, mà khối gói nạp đứng trước — `napTong()` tưởng đó là dòng tổng kết và **xoá trắng** nó. Kết
quả: cài mới, mở Nạp ví ra trống trơn, không nói gì (vá ở 1.49.1, lớp riêng `.pve-nap-trong`).
Bản thử khi ấy chỉ chạy cảnh **đã khai gói** nên không thấy — nay có thêm cảnh cài mới.

### 🔴 BỐN LẦN DÍNH: `qs()` LẤY THẺ ĐẦU TIÊN TRONG CẢ POPUP
Popup có **năm** bước dùng chung một khung (ví vé · nạp ví · giỏ · đặt lẻ · mã QR). `qs()` tra
trong cả popup, nên bước nào đặt trùng tên lớp là **cướp** lượt tra của bước khác — im lặng.

| Lần | Lớp bị cướp | Hậu quả |
|---|---|---|
| 1.48.0 | `.pve-go` (bước Giỏ) | nút "Tạo mã thanh toán" của bước đặt lẻ không được gắn việc |
| 1.49.0 | `.pve-nap-tt` | dòng "chưa khai gói nạp" bị xoá trắng |
| 1.55.0 | `.pve-qr` + `[hidden]` | trả bằng ví rồi mã QR vẫn hiện |
| 1.55.1 | **`.pve-badge`** (ví vé) | **"Chờ thanh toán" không bao giờ đổi** |

Nay mỗi bước có hàm tra riêng buộc phạm vi: `qf()` bước đặt lẻ · `qf2()` bước nạp · `qq()/qqa()`
bước mã QR. **Thêm bước mới thì thêm hàm mới, đừng dùng `qs()`.**
Bản thử phải mở **ví vé trước rồi mới mua** — chỉ khi đó `.pve-badge` mới tồn tại và bẫy mới lộ.

### 🔴 KHÔNG LẤY BẢN CŨ TRONG ĐỆM KHI HỎI TRẠNG THÁI
Trang hỏi 5 giây một lượt bằng **cùng một địa chỉ**. Trình duyệt — và nhất là lớp nhớ đệm của site
(SpeedyCache) — hoàn toàn có thể trả lại câu trả lời cũ mà không hỏi máy chủ: tiền đã về, máy chủ
đã đổi trạng thái, mà màn hình đứng im, **phải F5 mới thấy**. Mọi lượt hỏi trạng thái (`layMoi()`
ở trang khách, `get()` ở khu quản trị, vòng làm tươi của trang soát vé) đều thêm `cache:'no-store'`
**và** một tham số đổi theo lượt để địa chỉ không lặp lại.

### Ba màn tự làm tươi, đừng bắt ai bấm F5
| Màn | Nhịp | Vì sao |
|---|---|---|
| Trang khách (mã QR) | 5 giây | chờ tiền về |
| Trang soát vé ở quầy | 10 giây | vé mới trả tiền phải đẩy về quầy |
| Khu quản trị: **Đơn hàng**, **Đơn nạp ví** | 15 giây | trạng thái đổi do việc xảy ra **ở nơi khác** — khách chuyển khoản, nhân viên soát vé, cron dò sổ phụ |

Khu quản trị chỉ chạy khi **đúng tab đang mở và cửa sổ đang hiện** — máy để đó cả ngày mà cứ 15
giây gọi một lượt là nhọc máy chủ vô ích. Quay lại tab thì làm tươi **ngay**, không đợi hết nhịp.

### Xong hẳn thì phải BÁO XONG
Hai việc kết thúc hẳn — trả vé bằng ví, và nạp tiền vào ví — dùng chung một màn hoàn thành
(`manXong()`): phủ kín màn hình, đóng luôn popup bên dưới, tự về đầu trang mua vé sau 5 giây.

Trước đó lượt nạp chỉ đổi cái nhãn nhỏ thành "Đã vào ví" rồi để khách ngồi nhìn mã QR chuyển
khoản — không ai biết còn phải làm gì nữa.

### Lịch sử một tấm vé & một lệnh nạp
Thẻ đơn trong khu quản trị hiện ba mốc: **🛒 đặt** (lúc nào, tại cơ sở nào hay mua từ xa) · **💰
trả tiền** · **🎟️ đã soát** (lúc nào, ở cơ sở nào). Thiếu ba mốc này thì lúc khách khiếu nại *"tôi
chưa dùng mà báo đã dùng"* không có gì để đối chiếu ngoài một chữ "Đã dùng". Chỉ in mốc **đã có** —
in "chưa dùng" cho mọi vé chưa soát là làm loãng chỗ người ta đang tìm.

⚠️ Giờ hiển thị cắt bằng chuỗi, **không dựng `Date`**: chuỗi máy chủ trả về là giờ địa phương đã
quy đổi sẵn, đưa vào `Date` là trình duyệt hiểu thành UTC rồi lệch thêm 7 tiếng.

### 🔴 ĐƠN CHỈ ĐƯỢC TẠO SAU KHI KHÁCH CHỌN CÁCH TRẢ (từ 1.58.0)
Trước đó nút **MUA VÉ NGAY** ở khung đặt nhanh vừa chọn vé, vừa tạo đơn, vừa nhảy thẳng vào màn
mã QR chuyển khoản — khách chưa kịp nói muốn trả bằng gì thì đơn đã nằm trong sổ và **tồn kho đã
bị trừ**. Anh Thắng: *"Mua ngay là chọn nhanh, chứ thanh toán cũng phải rõ ràng, chọn ví hoặc
chuyển khoản"*.

Ba lối mua (đặt nhanh · thẻ vé · giỏ) đều dừng ở bước **Chọn cách thanh toán** (`moTraTien()`).
Bấm một trong hai nút mới gọi máy chủ. Nút ví bị khoá thì **nói rõ vì sao** (chưa đăng nhập Zalo,
hay thiếu bao nhiêu tiền) — "không bấm được" mà không giải thích là khách tưởng hỏng.

⚠️ Hai nút "Trả bằng ví" rời rạc ở bước đặt lẻ và bước giỏ **đã bỏ**: một chỗ quyết, một chỗ sửa.

### 🔴 TỒN KHO TRỪ LÚC TẠO ĐƠN → PHẢI DỌN ĐƠN QUÁ HẠN
Trừ tồn ngay lúc tạo đơn là bắt buộc (hai người không cùng mua tấm vé cuối), nhưng khách bấm Mua
rồi bỏ đi thì số vé ấy **mất luôn**: sổ ghi "còn 0 vé" trong khi chẳng ai mua. Bán một buổi là hết
sạch vé ảo.

`don_het_han()` chạy trong cron 5 phút: đơn `cho` quá **2 giờ** (option `pve_gio_het_han`) thì huỷ
và **trả lại tồn**. Trước khi huỷ vẫn **dò sổ phụ một lần nữa** — khách chuyển tiền phút chót,
webhook về chậm, mà ta huỷ mất thì họ trả tiền xong không có vé.

⚠️ Mỗi dòng vé lưu `chi_tiet` chứa **id loại vé**, nếu không thì huỷ xong không biết trả tồn cho
vé nào. Đơn tạo trước 1.57.1 không có nên không trả được — không đoán bừa, trả nhầm loại còn tệ
hơn không trả.

### Phân trang 10 dòng
Mỗi vé giờ là một dòng riêng nên danh sách dài ra rất nhanh. Đơn hàng và Đơn nạp ví phân trang
**10 dòng**, và `TRANG_DON`/`TRANG_NAP` giữ trang đang xem để vòng tự-làm-tươi 15 giây **không kéo
về trang 1** — đang xem trang 3 mà nhảy về đầu là không đọc nổi.

### 🔴 MỖI VÉ MỘT MÃ RIÊNG (từ 1.55.0)
Trước đó cả giỏ ghi thành **một** dòng, **một** mã: đơn *"1x Vé vào cửa, 1x Vé 1 giờ, 1x Vé cả
ngày, 1x Vé Vào Cửa"* chỉ có đúng một mã QR. Nhân viên quét một lần là **cả bốn vé** thành đã dùng
— bốn người đi cùng thì chỉ một người vào được; mà đưa cùng một mã cho bốn người thì không có cách
nào biết vé nào đã dùng.

Nay **mỗi đơn vị vé là một dòng, một mã, soát riêng một lượt**. Cả nhóm dùng chung `ma_don` và
chung `noi_dung` chuyển khoản — khách vẫn chỉ chuyển **một** lần, tiền về là cả nhóm cùng xanh.

⚠️ `so_tien` mỗi dòng là **đơn giá**, không phải tổng đơn — ghi tổng vào từng dòng là doanh thu
nhân lên gấp số vé.
⚠️ Mọi chỗ đối soát phải so với **tổng của cả đơn** (`don_theo_ma()`), không phải đơn giá một vé:
so với đơn giá là thấy "đủ tiền" ngay cả khi khách mới trả một phần. Áp dụng ở `r_trangthai`,
`do_lai_saoke` (gom theo `noi_dung`) và `r_soat`.
⚠️ Trạng thái chung của đơn lấy theo **mẫu số thấp nhất**: còn một vé chưa trả tiền thì cả đơn vẫn
là "chờ".
⚠️ Ghi hỏng giữa chừng thì **dọn sạch các dòng đã ghi của đơn** — để lại nửa đơn là khách trả đủ
tiền mà chỉ nhận được vài vé.

### Trả bằng ví: báo thành công tràn màn hình
Trả bằng ví là xong, không còn gì để chờ — nên **không** đưa khách vào màn mã QR chuyển khoản nữa
(`xongVi()`). Màn báo thành công phủ kín, đóng luôn popup bên dưới, rồi tự về đầu trang mua vé sau
5 giây.

"Về trang chủ" ở đây là **về đầu trang mua vé**, không nhảy sang trang chủ website: khách vừa mua
xong thường mua tiếp hoặc mở ví xem vé.

⚠️ `[hidden]` **phải thắng** `display:flex` cho `.pve-qr/.pve-cong/.pve-bank` — đã dính **ba lần**:
`el.hidden = true` không giấu được phần tử có `display` đặt sẵn. Trả bằng ví rồi mà mã QR chuyển
khoản vẫn nằm đó là mời khách trả lần thứ hai.

### Vòng đời một tấm vé (từ 1.48.0)
```
khách bấm ＋ -> giỏ -> đặt -> QR chuyển khoản
                                  |
              Sao Kê nhận tiền vào -> POSH_Ve::tu_khop() -> trạng thái da_tt
                                  |
        ví vé của khách (mã + QR)  |  màn hình [posh_soat] ở quầy kêu chuông
                                  \-> nhân viên quét -> r_soat() -> da_dung + coso_dung
```

### 🔴 SITE NÀY CÓ **HAI** CỔNG NHẬN TIỀN — vé chỉ đọc một
| Cổng | Của plugin | Ghi vào |
|---|---|---|
| `/ghe-tien` | Ghế | sổ thu của Ghế, phân loại theo `GHE<ghế>` / `MUA<đơn>` |
| `/wp-json/saoke/v1/webhook` | Sao Kê | **sổ sao kê ngân hàng** |

**Vé đối soát bằng sổ sao kê.** Nếu bên SePay chỉ khai cổng của Ghế thì tiền vé về vẫn đúng tài
khoản, Ghế vẫn nhận gói, nhưng nội dung `SEVQR VE<mã>` không khớp luật nào của Ghế nên nằm lại đó
— còn sổ sao kê **trống**, vé chờ mãi. Không ai thấy lỗi vì **chẳng bên nào sai cả**.

**Từ Ghế 2.38.0 + Sao Kê 0.16.0 có CẦU NỐI**: cổng `/ghe-tien` nhận xong đẩy luôn mọi giao dịch
sang sổ sao kê (`SAOKE_App::nhan_gd`). Khai một webhook là đủ, cả hai plugin cùng thấy.

🔴 **Chống trùng là bắt buộc, không phải tuỳ chọn.** Khai cả hai webhook bên SePay là cùng một
giao dịch tới hai đường. Hai đường phải quy về **cùng một `sepay_id`**: cổng Sao Kê chống trùng
theo `id` của gói, còn `VHG_Doc::tach()` lại ưu tiên `referenceCode` khi dựng `ref` — gói SePay có
**cả hai** trường, nên nếu không chỉnh thì một giao dịch vào sổ hai lần với hai khoá khác nhau.
Cầu nối lấy `id` của gói khi gói chỉ có một giao dịch; gói nhiều dòng (Tingo/VietQR dạng bảng)
không có `id` chung nên mới dùng `ref` từng dòng. **Đếm gấp đôi khó thấy hơn hẳn đếm thiếu**: sổ
vẫn khớp với chính nó, chỉ lệch với ngân hàng.

Màn Kiểm tra hệ thống (từ vé 1.52.0) có mục *"Cổng Sao Kê đã nhận gói từ SePay"*: nếu đỏ, nó in
sẵn đường dẫn cần dán vào SePay.

### 🔴 TIỀN TỐ `SEVQR` — mắt xích im lặng nhất
Với **VietinBank tài khoản cá nhân / hộ kinh doanh**, SePay **bắt buộc** nội dung chuyển khoản
phải chứa `SEVQR` mới định tuyến được giao dịch. Thiếu nó thì **tiền vẫn vào tài khoản, ngân hàng
vẫn báo thành công, nhưng SePay không bao giờ thấy** — không webhook, vé không tự xác nhận, và sổ
sao kê **không có lấy một dòng nào** để đi tìm.

Từ **1.51.0** nội dung là `<tiền tố> VE<mã vé>` / `<tiền tố> NAP<mã nạp>`, dựng bằng
`POSH_Ve::nd_ck()`. Tiền tố đọc từ option **dùng chung với plugin Ghế** (`vhg_tien_to_nd`) — hai
plugin chạy qua một tài khoản SePay, khai hai nơi là sớm muộn một bên quên và hỏng đúng kiểu im
lặng này. Chưa cài Ghế thì lùi về `pve_tien_to_nd`.

⚠️ VietQR chỉ cho **25 ký tự** ở ô nội dung; dài hơn là ngân hàng tự cắt, cắt ở đâu tuỳ ngân hàng —
cắt mất mã là đối soát mù. `SEVQR VEK7M2PQAB` mới 16 ký tự nên còn dư.

⚠️ **Vé tạo TRƯỚC 1.51.0 không có tiền tố** → SePay chưa từng thấy chúng, không có gì để dò lại.
Những vé ấy phải xác nhận tay ở Đơn hàng & soát vé.

### 🔴 NGÂN HÀNG KHÔNG TRẢ LẠI NGUYÊN VĂN NỘI DUNG CHUYỂN KHOẢN
Nội dung về tay ta thường là `CT DEN:0123 VE HTTMEXEQ` hoặc `VE-HTTMEXEQ` — ngân hàng chèn thêm
khoảng trắng, dấu gạch, chữ của chính nó. So chuỗi cứng là **trượt, mà trượt im lặng**: tiền vào
tài khoản rồi mà vé vẫn "chờ thanh toán".

`co_tien_ve()` so hai lượt: lượt 1 so thẳng (rẻ), lượt 2 **bóp chuỗi** — bỏ hết ký tự không phải
chữ-số, viết hoa, rồi mới tìm. Cùng cách plugin Ghế đã chạy được với đơn `MUA<mã>`
(`VHG_Doc::don_mua`). Mã 8 ký tự ngẫu nhiên nên bóp xong vẫn không đụng nhầm đơn khác. Lượt 2 chỉ
quét giao dịch **30 ngày gần đây, đủ tiền, tối đa 500 dòng** — nó chạy trong lượt trang khách hỏi
trạng thái, không được phép quét cả sổ.

⚠️ Màn Kiểm tra hệ thống gọi **đúng hàm ấy**, không viết câu SQL riêng: viết riêng là màn kiểm nói
một đằng, máy chủ làm một nẻo, rồi ta tin nhầm màn kiểm.

### 🔴 SePay gửi khoá webhook ở BA kiểu
Bảng cấu hình webhook của SePay cho chọn: không xác thực (khoá trong URL `?key=`), **API Key**
(header `Authorization: Apikey <khoá>`), hoặc Basic Auth. `vhcp-saoke` trước 0.15.1 **chỉ** đọc
`?key=` — chọn kiểu API Key là mọi lượt bắn về bị chặn 401, Nhật ký ghi "SAI KEY", trong khi bên
SePay nhìn vẫn thấy "đã gửi". Nay nhận cả ba, và câu log nói rõ khoá tới bằng đường nào.

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

### Ví vé: vừa mã chữ vừa mã QR, chạm để phóng to
Mỗi vé hiện **cả mã chữ lẫn mã QR**, ở **mọi trạng thái** — nhân viên quét là ra ngay vé còn dùng
được hay đã soát rồi ở đâu, đó mới là thứ gỡ được tranh cãi tại quầy.

⚠️ Vé **đã dùng / đã huỷ** thì QR phải **làm mờ + dán nhãn đè lên**: đưa ra một mã trông y như vé
thật mà không vào được cửa còn dễ cãi nhau hơn là không có mã.

Chạm vào ô QR → phóng to hết màn hình (`phongTo()`). Quầy đông, màn hình nghiêng, đèn kém thì tấm
QR 190px trong danh sách quét mãi không ăn. Nền tối cho mã nổi, nhưng **khung mã phải trắng** —
máy quét đọc theo tương phản, đảo màu là nhiều máy chịu.

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
