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

Phép kiểm: `tools/kiem-ghe/kiem-xoa-ma-chua-gan.php` (hàm PHP, giả lập `$wpdb`) và
`tools/kiem-ghe/kiem-xoa-ma-chua-gan.js` (giao diện).

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
