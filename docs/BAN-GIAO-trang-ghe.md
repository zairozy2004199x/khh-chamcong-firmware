# Bàn giao — Trang Ghế (plugin `vhcp-ghe`)

Hệ thống quản lý ghế massage & doanh thu POSH/K&H, chạy trên WordPress tại **khmatrix.com**.
Bản đang chạy: **2.20.2**. Repo: <https://github.com/zairozy2004199x/khh-chamcong-firmware>
(nhánh phát triển `claude/posh-qr-kh1urz`).

> ⚠️ Repo **CÔNG KHAI**. Tuyệt đối không đặt PIN / khoá / token / số tài khoản ngân hàng /
> khoá merchant trong mã nguồn. Tất cả cấu hình nhạy cảm nằm trong **Option của WordPress** (DB).

---

## 1. Cài đặt / cập nhật

1. WordPress → **Plugins → Add New → Upload Plugin** → chọn `dist/vhcp-ghe.zip` → *Install* →
   nếu hỏi thì **Replace current** → **Activate**.
2. Sau khi cài đè: bấm **Ctrl + F5** để trình duyệt bỏ bản JS cũ trong cache.
3. Kiểm tra số bản: mở trang, số phiên bản in thẳng ra (biến `VHG_BAN`) — phải là **2.20.2**.
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

### 3.1 Unit ID / Tên MISA ngay trên bảng Địa điểm (2.73.0)

Anh Thắng 14/09/2026: *"đẩy dồn 2 cột này qua bên địa điểm để kiểm tra và check nhập 1 lần"*.

Hai ô **Unit ID** và **Tên MISA** trước chỉ có ở tab *Xuất MISA*. Muốn biết cơ sở nào còn thiếu
Unit ID thì phải mở màn khác rồi dò tên qua lại giữa hai bảng 73 dòng. Nay điền thẳng ở bảng
**Địa điểm**, và cạnh ô tìm có dòng đếm **"⚠ N cơ sở chưa có Unit ID"**.

- **Lưu khi rời ô**, không phải mỗi hàng một nút — 73 cơ sở thì 73 cú bấm là đúng thứ cần bỏ.
  Viền ô: vàng = đang gửi · xanh = đã lưu · **đỏ = CHƯA lưu** (giữ nguyên chữ vừa gõ để gõ lại).
- **Một nguồn duy nhất**: cả hai màn cùng ghi vào bảng `bc_ma_misa`. Tab *Xuất MISA* vẫn giữ vì
  nó còn **Vùng · Thứ tự · Xoá**.
- Không đủ quyền đọc bảng MISA → **ẩn hẳn** hai cột, không để lại ô nhập chết.

> 🔴 Ô ở màn Địa điểm gọi `kt_ma_misa_dat` (chỉ chạm 2 cột), **KHÔNG** gọi `kt_ma_misa_luu` (ghi
> cả hàng). Dùng nhầm hàm kia thì mỗi lần sửa một Unit ID là **Vùng và Thứ tự của cơ sở đó bị
> xoá trắng** — im lặng, chỉ lộ ra lúc xuất MISA thấy thứ tự loạn.

> 🔴 Khoá ghép tên cơ sở ⇄ bảng MISA là `squash()`, ghép **ở máy chủ** (`ma_misa_map`). Đừng viết
> lại luật chuẩn hoá ấy bằng JavaScript — repo này đã có đúng một vụ hai bản sao lệch nhau
> (xem `CLAUDE.md` mục 5: Sao Kê ra chữ thường, Ghế ra chữ HOA).

Ô **Tên MISA** rộng theo màn hình (`width:100%`) nhưng có sàn `.misa-ten{min-width:240px}` — tên
MISA là thứ phải đối chiếu bằng mắt với sổ kế toán, nhìn không hết tên thì cột này mất gần hết
công dụng. Hover vào ô hiện tên cơ sở đầy đủ (`title`).

**Sắp xếp (2.74.0)** — ô chọn cạnh ô tìm, 5 kiểu: *A→Z địa điểm* (mặc định) · **Chưa có Unit ID
lên đầu** · *A→Z Unit ID* · *A→Z tên MISA* · *Nhiều ghế nhất*. Lựa chọn được nhớ trong
`localStorage` (`vhg_cs_sap`).

- Sắp **trong DOM**, không vẽ lại bảng — cột Unit ID là ô đang gõ, vẽ lại là mất con trỏ và mất
  chữ chưa kịp lưu (ô tìm ở trên cũng theo luật này).
- Khoá sắp đọc **từ chính ô nhập**, không từ bản đồ nạp lúc đầu — vừa gõ xong chọn sắp lại phải
  thấy nó về đúng chỗ mới.
- Ô trống dồn **xuống cuối** (muốn xem hàng trống thì đã có kiểu *Chưa có Unit ID lên đầu*).
- Hàng **"(chưa gán)" luôn nằm cuối** mọi kiểu — nó không phải một cơ sở.
- Gõ xong **không tự sắp lại**: đang điền lần lượt mà mỗi lần rời ô hàng nhảy đi là không ai điền
  nổi. Thứ tự chỉ đổi khi chọn lại kiểu.

Phép kiểm: `tools/kiem-ghe/kiem-diadiem-misa.js` (Chromium, giả lập ở tầng `XMLHttpRequest` nên
chạy đúng `goi()` thật) · `tools/kiem-ghe/kiem-rong-cot-misa.js` (đo cắt chữ ở 3 bề rộng màn) · và
`tools/kiem-ghe/kiem-sap-xep-diadiem.js` (5 kiểu sắp xếp, chạy qua `noi()` — đường thật) · và
`tools/kiem-ghe/kiem-co-so-trung.js` (dò gần trùng, gộp đúng chiều, đóng/mở cửa).

### 3.2 Cơ sở trùng & cơ sở đã đóng cửa (2.75.0)

Anh Thắng 14/09/2026: *"cần xoá hẳn CÁC CƠ SỞ TRÙNG, còn cơ sở đóng cửa thì ẩn, chứ không hiện
2, 3 cơ sở như này"*. Ca thật: **CGV PEAR PLAZA** ⟷ **CGV PEARL PLAZA** — lệch đúng một chữ L.

**Dò gần trùng.** Khối cảnh báo cam ngay trên bảng Địa điểm, đo bằng **khoảng cách sửa
(Levenshtein) ≤ 2** trên tên đã bỏ dấu.

> `luu_coso()` vốn đã chặn tạo cơ sở gần trùng, nhưng nó dùng `squash()` — chỉ gộp khác dấu /
> hoa-thường / khoảng trắng. `PEAR` vs `PEARL` là hai chuỗi khác nhau thật nên lọt qua. Đó là lý do
> phải có phép đo thứ hai.

> 🔴 **Chỉ NGHI NGỜ, không tự gộp.** "CGV Vincom 1" và "CGV Vincom 2" cũng lệch 1 ký tự mà là hai
> nơi thật. Tên ngắn (< 6 ký tự sau khi bỏ dấu) bị bỏ qua — ở đó báo động giả liên tục rồi không
> ai đọc nữa.

**Gộp** (`coso_gop`) dời **hết ghế** sang cơ sở giữ lại rồi xoá cơ sở kia. Hỏi xác nhận **hai lần**,
câu hỏi nói rõ cơ sở nào biến mất.

> 🔴 Dời ghế **trước**, xoá **sau**, và xoá bằng `xoa_coso()` chứ không `DELETE` thẳng —
> `xoa_coso()` có chốt "còn ghế thì không cho xoá", nên nếu bước dời hụt ghế nào thì bước xoá
> **tự chặn**, cơ sở nguồn còn nguyên để làm lại. Xoá thẳng là ghế sót mất cơ sở, rơi khỏi mọi
> phạm vi PIN — đúng cái đã gây *"cả loạt VHM biến mất"*.

> ⚠️ **Không sửa báo cáo cũ.** Bảng `bc` lưu tên cơ sở dạng chữ; báo cáo đã nộp dưới tên cũ vẫn
> nằm dưới tên cũ. Sổ của tháng đã chốt không đổi lại vì hôm nay ai đó dọn danh mục.

**Đóng cửa** (`coso_dong`, cột `coso.dong_cua`) — nút 🚪 trên mỗi hàng. Cơ sở đóng rơi xuống khối
gập **"🚪 Cơ sở đã đóng cửa"** ở cuối, bấm 🚪 lần nữa là mở lại.

> 🔴 **Đóng cửa ≠ xoá.** Cơ sở đóng vẫn còn ghế, báo cáo, công nợ của những tháng nó từng chạy.
> Cờ này chỉ ẩn khỏi danh sách làm việc hằng ngày.
> 🔴 Trạng thái đóng xét **trước** "rỗng ghế": cơ sở đã đóng thường cũng hết ghế, xét ngược thì nó
> rơi vào khối "chưa có ghế" và lại hiện ra y như một cơ sở mới — đúng cái đang muốn dẹp.

### 3.3 Xoá hẳn mã ghế chưa gán (2.76.0)

Anh Thắng 14/09/2026: *"xoá mã ghế không có cơ sở"*. Nút 🗑 **Xoá mã chưa gán** nằm ngay trên hàng
**"(chưa gán)"** của bảng Địa điểm.

> 🔴 **`may_xoa` KHÔNG xoá — nó chỉ đặt `an=1`.** Đó là chủ ý và đúng cho ghế đang chạy thật (chỉ
> số, doanh thu, log giữ nguyên). Nhưng mã rác chưa gán — kiểu `AMBT01` còn sót từ đợt đổi cách đặt
> mã — thì ẩn đi vẫn nằm đó. `xoa_han_may()` là đường **duy nhất** trong plugin xoá hẳn, và nó bị
> bó bằng ba chốt:
>
> 1. chỉ ghế `coso_id = 0` — ghế đang thuộc một cơ sở thì không đụng tới;
> 2. chỉ ghế **không còn một dòng nào** ở bất kỳ bảng nào mang cột `ma_may`;
> 3. ghế bị từ chối phải **báo rõ** còn dấu vết ở bảng nào, bao nhiêu dòng.
>
> ⚠️ Danh sách bảng **dò từ `information_schema`**, không chép tay — thêm bảng mới có cột `ma_may`
> mà quên cập nhật là hàm tưởng "sạch" rồi xoá, dữ liệu bảng mới thành mồ côi.

Chạy **hai bước**: hỏi máy chủ xem cái nào xoá được (không xoá gì) → hiện danh sách **đúng những mã
sắp mất** cùng lý do giữ lại từng mã khác → xác nhận mới xoá. Danh sách mã lấy từ `D.may`, **không**
đọc chữ đang hiển thị — bảng có thể đang bị ô tìm lọc.

**Cưỡng chế (2.77.0)** — anh Thắng: *"admin có quyền xoá hẳn"*. Mã bị chặn **chỉ vì còn dữ liệu**
thì Quản trị xoá được, sau khi **gõ tay** chuỗi `XOA HAN`.

> 🔴 Cưỡng chế **chỉ bỏ chốt 2, KHÔNG bỏ chốt 1** — ghế đang thuộc một cơ sở vẫn không xoá được,
> kể cả admin. Anh xin quyền xoá *"mã không có cơ sở"*, không phải xoá ghế đang chạy.
> 🔴 Cưỡng chế **chỉ xoá dòng ở bảng `may`**. Các dòng ở `thu`/`lenh`/`nhip`… **vẫn nằm nguyên** —
> `thu` là **tiền đã thu**, xoá đi là tổng doanh thu của tháng đã chốt tự nhiên nhỏ lại mà không
> ai đi đối soát lại. Giữ lại thì **tổng tiền không đổi**, chỉ là mấy dòng ấy không còn tra ngược
> ra ghế nào.
> ⚠️ **Đừng tạo lại ghế trùng mã đã xoá** — mọi bảng nối bằng chuỗi `ma_may`, nên ghế mới sẽ
> **nhặt lại toàn bộ lịch sử cũ**.
> ⚠️ Mã còn nhiều dòng `thu` gần như luôn là **mã CŨ của một ghế đã đổi tên**. Việc đúng là ✎
> **đổi mã** (`gan_ma()` dời cả lịch sử sang mã mới), không phải xoá. Hộp thoại nói đúng câu này
> trước khi cho gõ xác nhận.
>
> Dùng `prompt()` bắt gõ chứ không `confirm()`: một cú Enter là qua được `confirm`, mà đây là xoá
> mã còn tới hàng trăm dòng tiền.

Phép kiểm: `tools/kiem-ghe/kiem-xoa-ma-chua-gan.php` (24 phép, hàm PHP, giả lập `$wpdb`) ·
`tools/kiem-ghe/kiem-xoa-ma-chua-gan.js` (giao diện) · `tools/kiem-ghe/kiem-cuong-che-xoa.js`
(gõ sai / bấm Huỷ / gõ đúng).

### 3.4 Sửa thẳng ô tiền trên bảng Duyệt (2.78.0)

Anh Thắng 14/09/2026: *"cho sửa trực tiếp trong ô luôn"*. Trước đây phải bấm **Sửa** để bung một
hàng ô nhập ở cột cuối, gõ xong bấm **Lưu** — kế toán nắn QR của hai chục ghế là hai chục lần
bung–gõ–đóng.

Nay **Tiền mặt** và **QR** là ô nhập ngay trên bảng, Enter hoặc bấm ra ngoài là lưu — y như cột
chỉ số **Trước/Sau** đã làm từ 2.37.0. Nút **Sửa** vẫn còn cho những trường ít dùng (± điều chỉnh,
ghi chú).

| Cột | Sửa thẳng? | Vì sao |
|---|---|---|
| Trước · Sau | ✅ | người nhập |
| **Tiền mặt · QR** | ✅ **mới** | người nhập |
| Actual · Nộp | ❌ | **số TÍNH RA** — cho gõ vào là đẻ ra con số không khớp công thức nào, rồi không ai biết số nào đúng. Đổi Actual thì sửa chỉ số; đổi Nộp thì sửa Tiền mặt/QR |

**Ô Tiền mặt mang hai ý, gửi hai khoá khác nhau:**

- **gõ số** → `actualOverride` = ghi đè (Thực thu), không tính theo chỉ số;
- **xoá trắng** → `bo_ghi_de` = gỡ ghi đè, tính lại `Actual − QR` (hỏi xác nhận trước, và dọn luôn
  dấu *"Thực thu ghi đè"* trong ghi chú).

> 🔴 **Phải là cờ riêng, không suy từ `actualOverride` rỗng.** Rỗng đã mang nghĩa khác và quan
> trọng: *"tôi chỉ sửa QR/chỉ số, ĐỪNG đụng số ghi đè"*. Trộn hai ý vào một giá trị là mỗi lần kế
> toán nắn QR của dòng ghi đè thì tiền mặt tự rơi về công thức — **âm thầm, mà tổng vẫn khớp nên
> đối chiếu không bắt**. Đúng lỗi R1 đã phải đi vá 12/09/2026.

Báo cáo đã **khoá ngày** thì mọi ô về lại chữ tĩnh.

Phép kiểm: `tools/kiem-ghe/kiem-sua-o-tien.php` (11 phép, hàm `sua()` thật, giả lập `$wpdb`) và
`tools/kiem-ghe/kiem-sua-o-tien.js` (cột nào cho gõ, ba payload, khoá ngày).

### 3.5 Tìm nhân viên ở tab PIN báo cáo (2.79.0)

Anh Thắng 14/09/2026: *"gõ tìm kiếm tên nhân viên"*. Ô tìm ngay trên bảng **📋 PIN nhân viên báo
cáo**, lọc theo **tên · PIN · cơ sở · ghế riêng**, kèm bộ đếm *"3/48 người"* / *"không thấy ai khớp"*.

> 🔴 Khoá tìm dựng bằng **`kdJS` (bỏ dấu)**, không phải `toLowerCase()` như ô *lọc cơ sở* ngay bên
> dưới. Đây là tìm **tên người**: gõ `thang` phải ra **Thắng**, gõ `chau` phải ra **châu**. Không ai
> gõ đủ dấu để đi tìm một cái tên.

> ⚠️ Lọc **ngay trong DOM** (ẩn/hiện hàng), không vẽ lại bảng — vẽ lại là mất con trỏ sau mỗi phím
> gõ. Cùng luật với ô tìm ở bảng Địa điểm.

Phép kiểm: `tools/kiem-ghe/kiem-tim-nhan-vien.js` — gõ có dấu / không dấu / HOA-thường, tìm theo
PIN, theo cơ sở, và ca không khớp ai.

### 3.6 Tên thường gọi của ghế (2.80.0)

Anh Thắng 14/09/2026: *"thêm tên thường gọi cho ghế để nhân viên dễ biết, nhiều khi lấy mã cố định
thành tra tên không ra ràng"*.

Cột mới **`may.ten_goi`** + ô nhập **"Tên thường gọi"** trong bảng Quản lý ghế. Màn **nhập chỉ số
của nhân viên** hiện nó ở **dòng dưới**, chữ nhỏ màu xanh, ngay dưới `Tên ghế (mã)`.

> Cách lưu đổi ở **2.82.0** — xem 3.7.

> 🔴 **KHÔNG dùng lại `ten_khai`.** Cột đó là **"Tên trên sao kê"** — phải khớp nội dung chuyển
> khoản để đối soát ngân hàng ghép tiền về đúng ghế. Sửa nó thành *"ghế cạnh thang máy"* là mọi
> giao dịch của ghế đó thôi ghép được — **âm thầm**, chỉ lộ ra cuối tháng khi thấy một đống tiền
> không biết của ghế nào.
> 🔴 `ten_goi` **không được dùng làm khoá ghép** ở bất kỳ đâu. Nó chỉ để người đọc.
> 🔴 Hai ô đi **hai đường lưu khác nhau** (`may_ten` / `may_ten_goi`). Gộp làm một là một ngày nào
> đó sửa tên thường gọi lại ghi đè tên sao kê.

> ⚠️ Trên màn nhân viên, tên thường gọi **đứng dưới**, không thay chỗ `Tên ghế (mã)`. Nhân viên đối
> chiếu với tem dán trên ghế bằng **mã**; bỏ mã đi để lấy chỗ cho một câu dễ đọc là lúc cần tra
> ngược lại không còn gì để tra.

**Thứ tự sắp xếp giữ nguyên theo TÊN GHẾ**, không đổi sang tên thường gọi: tên ghế có quy luật
(`VHM-1`…`VHM-12`) nên xếp ra thứ tự dùng được; tên thường gọi là câu chữ tự do, xếp theo nó thì
danh sách nhảy lung tung mỗi lần ai đó sửa một cái tên.

Phép kiểm: `tools/kiem-ghe/kiem-luu-ten.js` + `kiem-luu-ten.php` (thay cho
`kiem-ten-thuong-goi.js`, đã xoá ở 2.82.0 cùng lối tự lưu mà nó kiểm).

> Phần hiển thị trên màn nhân viên là 6 dòng thêm vào, có `if (g.ten_goi)` bao ngoài, và dùng đúng
> `el()` sẵn có của khối đó — `node --check` sạch, nhưng **chưa dựng harness riêng cho màn nhân
> viên** (khối đó là IIFE kín, chưa có lối mở ra để kiểm).

### 3.7 Bấm Lưu mới lưu + vá "không lưu được tên thường gọi" + sắp đúng thứ tự (2.82.0)

Anh Thắng 14/09/2026, ba việc trong một buổi.

#### a) Vá: *"Không lưu được tên thường gọi"*

**CSDL lưu đúng từ đầu.** `may_ten_goi` ghi xuống `may.ten_goi` chuẩn — ảnh màn *nhập chỉ số* của
anh Thắng còn hiện đúng tên thường gọi màu xanh. Hỏng nằm ở **payload `so_lieu`**: chỗ dựng
`$may[]` để gửi cho màn hình chưa bao giờ kèm khoá `ten_goi`, nên `D.may[i].ten_goi` luôn
`undefined` và ô nhập vẽ lại **rỗng** sau mỗi lượt tải. Nhìn y hệt như không lưu được.

> 🔴 **Loại lỗi này không để lại dấu vết ở đâu cả** — CSDL đúng, log đúng, lệnh lưu trả `ok:true`.
> Cùng một vết với vụ `ma_kh` ở tab Địa điểm (12/09). Nên nay có người gác đúng chỗ nó xảy ra:
> `kiem-luu-ten.php` quét `class-vhg-trang.php`, **mọi** chỗ dựng danh sách ghế có `ten_khai` mà
> thiếu `ten_goi` trong vòng 8 dòng là **đỏ**.

Gửi ở **cả hai** payload — quản trị lẫn người thu/hotline. Cái tên này sinh ra đúng là để **nhân
viên** nhận ra ghế nào; giấu khỏi họ thì nó vô nghĩa.

#### b) *"khi nào bấm lưu mới nhé, chứ cứ gõ vào phát bấm chuột ra nhảy đi đâu mất"*

Gốc phiền phức **không phải "tự lưu"**, mà là **lưu xong vẽ lại cả trang**: đường cũ đi qua
`lam()` → `tai()` → `ve()`. Rời một ô là dựng lại toàn bộ màn — về trang 1, mất chỗ đang cuộn, mất
ô đang gõ dở ở hàng khác. Bảng 12 ghế × 2 ô là 24 lần bị hất ra.

Nên đổi **hai** thứ cùng lúc:

1. Gõ chỉ ghi vào **giỏ `QL_SUA`** (ngoài hàm vẽ), không gọi máy chủ. Ô đang khác bản đã lưu
   được tô **viền vàng**; thanh **"✎ N ghế đang sửa, CHƯA LƯU"** hiện trên bảng.
2. Nút **💾 Lưu** gửi **một** lượt `may_ten_lo`, rồi **cập nhật tại chỗ** (`D.may` + vẽ lại đúng
   một bảng) — **không** gọi `lam()`. Trang đứng yên: vẫn trang đó, vẫn bộ lọc đó. Enter cũng lưu.

| Điều | Vì sao |
|---|---|
| Giỏ nằm **ngoài** hàm vẽ | Đổi trang / đổi bộ lọc / một lượt tự làm mới ập vào giữa chừng vẫn không nuốt phần đang gõ |
| Gói chỉ mang khoá **thật sự** đã sửa | Sửa tên thường gọi **không bao giờ** động tới `ten_khai` (tên trên sao kê) |
| `array_key_exists` chứ không `isset`/`empty` | Xoá trắng một cái tên là ý định hợp lệ; `empty('')` là true nên lối cũ sẽ báo "đã lưu" mà tên còn nguyên |
| Lưu hỏng thì **giữ** giỏ | Mạng rớt không được phép nuốt công gõ — và tệ hơn: bảng vẽ lại bằng dữ liệu cũ nên trông như chưa ai gõ gì |
| Màn lấy tên **đã chuẩn hoá** máy chủ trả về | `chuan_ten()` sửa chữ người gõ; giữ chữ thô thì lần vẽ sau lại thấy "khác" và báo chưa lưu |

#### c) *"Sắp sai thứ tự"*

Ảnh: `ESTELLA-1 … ESTELLA-6` rồi **`ESTELLA4` rơi tuốt xuống cuối**. Anh Thắng tự tìm ra ngay sau
đó: *"do gốc thiếu ký tự"* — cái tên đó gõ sót dấu gạch nối.

Dữ liệu sai thật, nhưng **cách sắp cũng không chịu nổi một lỗi gõ**: `localeCompare(…, {numeric:
true})` chỉ so số khi hai bên cùng tới chữ số một lượt, mà dấu `-` xếp **trước** mọi chữ số — nên
cả dãy có gạch nối đứng trước cái tên thiếu gạch. Sót một ký tự là đủ để một cái ghế rớt khỏi chỗ
của nó, và người gõ không có cách nào đoán ra vì sao.

Nay: **bỏ hẳn dấu ngăn** (`-`, khoảng trắng, `_`, `.`…) rồi so theo từng khúc **CHỮ / SỐ**, khúc
số so bằng **giá trị**. `ESTELLA-4` và `ESTELLA4` về cùng một khoá; `-2` vẫn đứng trước `-10`.

> ⚠️ Chỉ bỏ **dấu ngăn**, không bỏ dấu tiếng Việt — cắt dấu đi thì hai cái tên khác hẳn nhau lại
> đụng khoá.

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
- ⚠️ **Bẫy đã vá ở 2.20.2:** Sao Kê lưu mã với khoá **chữ thường** (`chuan_ch()` → `sanbaycantho`),
  Ghế tra bằng khoá **chữ HOA** (`squash()` → `SANBAYCANTHO`) — không bao giờ khớp, nên **mọi** cơ sở
  hiện "chưa đặt mã" và *Đã nộp* = 0đ dù kế toán đã đặt đủ mã. Ghế nay quy khoá của option về
  `squash()` trước khi tra. Thêm chỗ nào đọc `saoke_coso_ma` thì phải quy khoá y như vậy.

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
