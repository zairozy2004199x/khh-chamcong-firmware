# Bàn giao — plugin ghế `vhcp-ghe`

Cập nhật: 2026-08-29 · Phiên bản hiện tại: **1.62.0** · Nhánh phát triển: `claude/posh-qr-kh1urz`
(Chỉ commit/push lên nhánh này, không mở PR nếu chưa được yêu cầu.)

Đây là plugin WordPress phục vụ trang ngoài `/ghe` (SPA đăng nhập bằng PIN) cho hệ thống thanh
toán ghế massage POSH của K&H. Tài liệu này ghi lại việc đã làm gần đây, một vấn đề đã đóng (giữ
lại để giải thích), và **việc cần kiểm tiếp với anh Thắng** — để người tiếp nhận không phải dò lại
từ đầu.

---

## 1. Việc đã làm gần đây

### v1.62.0 — Ô ảnh ở mọi chế độ + nối dòng thời gian chỉ số
- **Ô nhập ảnh (📷 Chỉ số / 🧹 Vệ sinh) LUÔN hiện**, cả chế độ Gọn (điện thoại) lẫn Đầy đủ. Trước
  đây ảnh chỉ ở chế độ Đầy đủ → điện thoại để Gọn là mất đường đính ảnh; PC/điện thoại khác chế độ
  thấy khác nhau. (`js_baocao()` trong `class-vhg-trang.php`: header Gọn thêm 2 cột, `celAnh` chuyển
  ra ngoài khối `if(!GON)` xuống cuối hàng ở cả hai chế độ.)
- **Nối dòng thời gian chỉ số** (anh Thắng 29/08: *"nhập vào ngày nằm giữa 2 ngày thì chỉ số tự chèn
  vào giữa… chỉ số cũ ngày hôm sau tự nhảy chỉnh lại"*). Sau khi lưu/sửa/bỏ/đổi-ngày một ghế ở ngày
  D, **lần đọc kế tiếp** (ngày > D, có chỉ số sau) tự lấy chỉ số sau vừa chốt của D làm chỉ số trước,
  tính lại actual/tiền/tổng. Chỉ đụng đúng một hàng kế tiếp (mốc = chỉ số sau gần nhất trước ngày đó).
  - Helper mới `VHG_BaoCao::noi_tiep($ma,$ngay)` (hàng kế tiếp), `noi_hang($ma,$ngay)` (chính hàng
    tại ngày — dùng khi đổi ngày), và private `ap_moc_()` (chính sách áp mốc). Gọi từ: `VHG_BaoCao::luu()`
    (ghế vừa gửi + ghế vừa bỏ), `VHG_BaoCao::sua_dong()` (nhân viên sửa 24h), `VHG_KeToan::sua()` và
    `VHG_KeToan::doi_ngay()` (đổi ngày → nối cả ngày mới, ngày cũ, và chính báo cáo được chuyển).
  - 🔴 AN TOÀN TIỀN: hàng kế tiếp là "Thực thu ghi đè" (đã chốt tay) hoặc nối xong hoá bất thường
    (sau < trước mới / tiền mặt ra âm) → CHỈ đổi chỉ số trước, GIỮ tiền cũ, ghim ghi chú
    "↺ Chỉ số trước tự nối lại…" để kế toán kiểm; không tự ghi tiền rác.
  - Chưa nối ở `xoa()`/`undo()` (xoá/hoàn tác báo cáo) — xem mục "Cần kiểm/làm tiếp".

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

### v1.52.1 – v1.53.3 — Vá theo phản hồi thực tế trên bảng Duyệt báo cáo
- Đơn chưa duyệt hết lên đầu danh sách; bung sẵn chi tiết; ảnh chỉ số cũ (link Google Drive dạng
  "view") hiện được thumbnail (`ktAnhSrc()`).
- `goi()` (hàm gọi API dùng chung) thêm **timeout 25s** — trước đó "Đang lưu…" có thể treo vô hạn
  trên mạng yếu.
- Tab mới **"📋 Báo cáo doanh thu"** ở sidebar chính, mở thẳng app thu-tiền PIN mà **khỏi đăng nhập
  lại** (token `/ghe` suy ra PIN nhân sự — `VHG_BaoCao::boot_tu_ai()`).
- **Lọc ghế lỗi theo cơ sở** cho vai trò bị giới hạn phạm vi (Quản lý/CHT); sau đó phải **loại trừ
  Kế toán** khỏi luật này (`VAI_XEM_HET`) vì tài khoản kế toán cần xem hết mọi cơ sở.
- Bảng Duyệt báo cáo + Nhật ký hoàn tác: **phân trang 10 dòng/trang** (trước tải hết một lần gây lag
  trên điện thoại).
- Ô lọc cơ sở trống với Kế toán: `so_lieu_khong_quan_tri()` trước đó luôn gửi `coso=[]` cho MỌI vai
  trò không quản trị — sửa để gửi danh sách thật khi là Kế toán.

### v1.54.0 – v1.55.2 — Nhớ nháp, tách doanh thu chưa/đã duyệt, dọn giao diện
- **Lưu nháp localStorage** khi nhân viên đang nhập báo cáo (khoá theo cơ sở+ngày) — F5/thoát app
  giữa chừng không mất số đã gõ; tự xoá khi gửi thành công. (`bcLuuNhap/bcDocNhap/bcXoaNhap`.)
- Tách rõ **doanh thu chưa duyệt / đã duyệt**; nút "Duyệt báo cáo" **chìm màu** khi đơn đã duyệt hết
  (tránh bấm lại nhầm); **duyệt riêng từng máy lẻ** thay vì phải huỷ cả lô khi chỉ một máy lỗi.
- **Phóng ảnh khi rê chuột** (`.kt-anh-zoom`) cho kế toán soát ảnh chứng từ dễ hơn.
- Cuộn lên đầu trang khi đổi trang (trước bấm "trang sau" bị nhảy xuống cuối); **tắt tự F5 cả trang**
  ở tab Duyệt báo cáo (`henLai()` chặn `TAB==='kt-duyet'`) — tránh mất thao tác dở khi tab tự làm mới.
- Chân trang (`VHG_Chan`) bị sidebar 216px che ở màn vừa (901–1400px) → thêm `body .vhg-chan{margin-left:216px}`
  ngay trong CSS riêng của `class-vhg-trang.php` (không sửa CSS dùng chung `VHG_Chan::css()`, vì lớp
  đó còn phục vụ trang khách `/mua-ma` không có sidebar).

### v1.56.0 – v1.57.0 — Bảng chéo Ngày×Ghế + ảnh theo từng ghế
- Tab **Doanh thu địa điểm** thêm **bảng chéo Ngày × Ghế cả năm** (không ngắt theo tháng) — mỗi ghế
  một cặp cột (chỉ số máy · Actual). API `kt_bangcheo` (`VHG_KeToan::bang_cheo()`).
- Nhân viên thu tiền chụp **2 ảnh/ghế** (chỉ số + vệ sinh) thay vì một xấp ảnh chung chia đều theo
  thứ tự (dễ gán nhầm ghế với 20+ máy). Ảnh gắn thẳng theo mã ghế (`images.chiso/vesinh`), lưu có
  nhãn ở server (`chiso.jpg`/`vesinh.jpg` theo mã).

### v1.58.0 – v1.61.0 — "Chỉ số bất thường": 4 việc anh Thắng chốt làm lần lượt

Bốn việc này đi cùng nhau, làm tuần tự theo yêu cầu *"Làm lần lượt từng cái một, báo kết quả xong
mới qua cái kế"* — cả 4 đã xong tính tới bản 1.61.0, nhưng **việc 3 và việc 4 chưa qua tay anh Thắng
kiểm** (xem mục "Cần kiểm tiếp" bên dưới).

1. **v1.58.0 (việc 1/4) — Lý do thay vì chặn cứng.** Trước đây `chi_so_sau < chi_so_truoc` bị CHẶN
   CỨNG, không gửi được. Nay cho nhập **lý do**, kèm gửi. `v1.59.1` mở rộng điều kiện "bất thường"
   sang cả **tiền mặt tính ra ÂM** (QR nhập > Actual — chỉ số vẫn đúng chiều nhưng công thức
   `tien_mat = actual − QR + điều_chỉnh` vẫn cho số âm).
2. **v1.59.0 (việc 2/4) — "Thực thu" ghi đè.** Khi bất thường, thêm ô **Thực thu** (bắt buộc) thay
   thẳng số tiền mặt phải nộp — không dùng công thức (rác) nữa. `v1.59.2`–`v1.59.3` mở rộng sang
   form **"Sửa"** riêng của kế toán (`VHG_KeToan::sua()`), vốn có một chốt chặn cứng khác, tách biệt
   với chốt ở `VHG_BaoCao::luu()` — dễ tưởng đã sửa hết nhưng thật ra là hai chỗ chặn khác nhau.
   `v1.59.4`: sắp danh sách máy theo **thứ tự tự nhiên** (`strnatcmp`, 1,2,…,10,11,12) thay vì thứ tự
   chuỗi (10 nhảy lên trước 2) sau mỗi lần Lưu.
3. **v1.60.0 (việc 3/4) — Đối chiếu lượt kích ghế từ xa.** Khi nhập chỉ số sau, hệ tự đếm số lượt
   Hotline/Admin **bấm Bật tay** (bảng `lenh`) trong đúng quãng chỉ số báo cáo bao phủ, quy đổi ra
   tiền theo **giá/phút RIÊNG của từng ghế** (`VHG_May::ty_le_cua()`) rồi **trừ thẳng khỏi `actual`**
   trước khi tính tiền mặt. Có kích thì "báo" bằng cách ghép vào ghi chú (`🔧 Đã trừ N lượt…`), không
   im lặng sửa số. File mới/đụng: `VHG_May::dem_luot_kich()`, `VHG_BaoCao::chi_so_truoc_ct_()` (tách
   từ `chi_so_truoc()`, trả thêm NGÀY mốc) + `kich_xa_tru()`; API `bc_kichxa` cho client xem trước;
   client `calc()`/`guiBaoCao()` trong `js_baocao()` dùng cùng số để khớp đúng server.
4. **v1.61.0 (việc 4/4) — Tab Hotline "📞 Hỗ trợ khách".** Sổ tay ngày cho Hotline (và Quản
   lý/Admin) ghi **số lượt kích thêm** + **số tiền hoàn khách** — bảng mới `hotline_bc` (1 dòng/cơ
   sở/ngày, gửi lại là ghi đè), lớp mới `VHG_Hotline` (`luu()/ds()`), API `hl_luu`/`hl_ds`/`hl_ke`
   (gate theo quyền **GIÚP KHÁCH**, không phải quyền quản trị — cùng khuôn `kt_*`). Đây là **sổ tay
   thủ công**, khác hẳn nhật ký `lenh` tự động: hệ không có luồng nào tự bắt được số tiền hoàn
   khách, nên ô đó luôn phải gõ tay; số "tự đếm được" từ `lenh` chỉ hiện để đối chiếu tham khảo.

---

## 2. VẤN ĐỀ ĐÃ ĐÓNG — "Cơ sở phụ" không đẩy sang bên ghế

**✅ ĐÃ SỬA** — plugin **K&H Chấm công (`vhcp-cham-cong`)**, bản **3.1.1**, commit *"Chấm công
3.1.1: đẩy sang ghế gộp luôn Cơ sở phụ"*, trên nhánh **`claude/rebuild-chi-phi-wordpress-hl2yze`**
(lịch sử git **không liên quan** tới nhánh `claude/posh-qr-kh1urz` của plugin ghế — hai plugin, hai
nhánh riêng trong cùng một repo GitHub). Sửa đúng theo mục "Cách sửa" đã ghi bên dưới: hàm
`VHCC_Day_Ghe::ho_so_day()` giờ ghép `Cửa hàng` + `Cơ sở phụ` vào một ô "Cơ sở" nối bởi `; ` trước
khi đẩy sang bảng dùng chung `vhcp_cfg`. **Bên ghế không cần sửa gì** — mục dưới đây giữ lại nguyên
văn để giải thích vì sao (bên ghế đã hỗ trợ nhiều cơ sở qua tách chuỗi `;`/`,`).

**Hiện tượng gốc (anh Thắng 28/08/2026):** *"Nhân viên bên cơ sở phụ lại không đẩy thông tin sang, nó
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

### Nguồn hệ nhân sự — đã tìm ra, KHÁC repo/nhánh với plugin ghế
Bản deploy **K&H Chấm công** hoá ra nằm CÙNG repo GitHub `khh-chamcong-firmware`, ở thư mục
`wordpress/vhcp-cham-cong/`, nhưng trên nhánh **`claude/rebuild-chi-phi-wordpress-hl2yze`** — một
lịch sử git **hoàn toàn không liên quan** tới nhánh `claude/posh-qr-kh1urz` của plugin ghế (đã xác
nhận bằng `git merge-base` trả về không tìm thấy tổ tiên chung). Hai plugin, hai nhánh, một repo.
Đã sửa đúng theo mục "Cách sửa" ở trên trong hàm `VHCC_Day_Ghe::ho_so_day()` (bản 3.1.1) — xem khối
✅ đầu mục 2.

---

## 3. CẦN KIỂM TIẾP — việc 3/4 và 4/4 chưa qua tay anh Thắng

Toàn bộ 4 việc "chỉ số bất thường" (mục 1 ở trên) đã code/lint xong và đã gửi bản cài (1.60.0,
1.61.0), nhưng tính tới lúc cập nhật tài liệu này **anh Thắng mới xác nhận test việc 1 và 2** (từ
1.58.0 tới 1.59.4). Người tiếp nhận nên hỏi/xem phản hồi trước khi coi đây là xong hẳn:

- **Việc 3 (trừ lượt kích khỏi doanh thu, v1.60.0):** cần một ca thật có Hotline bấm Bật rồi nhân
  viên nộp báo cáo đúng ngày đó, xem ghi chú `🔧 Đã trừ N lượt…` có ra đúng số và đúng tiền không
  (so với `VHG_May::ty_le_cua()` của đúng ghế đó).
- **Việc 4 (tab Hotline, v1.61.0):** cần Hotline thật vào tab **"📞 Hỗ trợ khách"**, thử Lưu, đổi
  ngày/cơ sở, gửi lại trong cùng ngày xem có ghi đè đúng không, và xem số "tự đếm được" có khớp với
  số lượt họ vừa bấm Bật ở tab Điều khiển hay không.

Nếu anh Thắng báo lỗi ở hai việc này, đọc kỹ khối 🔴 trong `kich_xa_tru()` (class-vhg-baocao.php)
và đầu `class-vhg-hotline.php` trước khi sửa — cả hai đều có đúc kết lý do thiết kế, đừng đổi hướng
mà không đọc.

---

## 4. Ghi chú kiến trúc nhanh (cho người tiếp nhận)

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
- **Sổ tay Hotline (`hotline_bc`) khác nhật ký `lenh`:** đừng nhầm hai bảng khi đọc code mới —
  `lenh` tự động (mọi lượt bấm Bật), `hotline_bc` thủ công (Hotline tự tổng kết, có số tiền hoàn
  khách mà `lenh` không có). Xem khối 🔴 đầu `class-vhg-hotline.php`.

## 5. Cách kiểm tra & đóng gói (đã dùng suốt session)

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

## 6. Ràng buộc bắt buộc
- ⛔ **Repo CÔNG KHAI** — KHÔNG hardcode PIN / khoá / secret trong mã. PIN nằm ở nhân sự / `bc_pin`.
- Chỉ push nhánh `claude/posh-qr-kh1urz`; không mở PR nếu chưa được yêu cầu.
- Mỗi lần đổi tính năng: **tăng `VHG_VERSION`** ở `vhcp-ghe.php` (header `Version:` + `define`), vì
  `vhg_maybe_upgrade()` chạy `dbDelta` khi đổi phiên bản.
