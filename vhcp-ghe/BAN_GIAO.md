# Bàn giao — plugin ghế `vhcp-ghe`

Cập nhật: 2026-09-24 · Phiên bản hiện tại: **2.137.0** · Nhánh phát triển: `claude/posh-qr-kh1urz`
(Chỉ commit/push lên nhánh này, không mở PR nếu chưa được yêu cầu.)

Đây là plugin WordPress phục vụ trang ngoài `/ghe` (SPA đăng nhập bằng PIN) cho hệ thống thanh
toán ghế massage POSH của K&H. Tài liệu này ghi lại việc đã làm gần đây, một vấn đề đã đóng (giữ
lại để giải thích), và **việc cần kiểm tiếp với anh Thắng** — để người tiếp nhận không phải dò lại
từ đầu.

---

## 1. Việc đã làm gần đây

### Sao Kê 0.46.0 — Quy cơ sở cho dòng cổng: tha số 0 đệm, tha đuôi tỉnh, đối chiếu MÃ CỬA HÀNG

Anh Thắng 24/09/2026, hai ảnh: (1) *"bóc sai địa điểm mã cửa hàng của anh rồi"* — dòng **GO AC 03**
bị quy về **GO TRƯỜNG CHINH** trong khi danh sách cửa hàng của cổng nói mã VCD7HWKFAM = "GO ÂU CƠ 03";
(2) *"QR trên ghế đang báo không có"* — GO BẾN TRE: bảng Sao Kê hiện "GO BT 08 · GO BẾN TRE — Bến
Tre", Báo cáo tổng bên Ghế hiện **VietQR –**.

**Ba lỗ, một chỗ vá (`cong_coso_dong()`):**
1. **Số 0 đệm**: cổng "GO BT 08", ghế "GO-BT-8" → `chuan_ch` không khớp. `ghe_coso_cua_may()` nay thử
   khoá `chuan_may()` (0.44.0) trước — máy khớp thẳng ghế, ra cơ sở của ghế.
2. **Đuôi tỉnh**: tên ánh xạ "GO BẾN TRE — Bến Tre" không khớp cơ sở "GO BẾN TRE". `ghe_coso_chuan()`
   lùi khoá lỏng `chuan_ch_long()` (bỏ " — …", bỏ ngoặc); khoá khít vẫn thắng.
3. **Mã cửa hàng chưa từng được hỏi lại**: suy cơ sở chỉ đi từ tên máy trong nội dung rồi ánh xạ tay.
   Nay hỏi cả hai nhân chứng; khác nhau thì **cờ `xungDot`**, kể tên bên thua. Bên thắng theo độ
   chắc: *tên máy khớp thẳng ghế* > *mã cửa hàng (sổ đăng ký của cổng, khớp tên cơ sở)* > *ánh xạ tay*
   (một dòng người gõ, có thể sai từ đầu — đúng ca GO AC 03). Gán máy tay không bị đè.
- Bảng Sao Kê cổng: chỉ hiện tên cơ sở khi **quy được cơ sở Ghế**; không quy được thì in "⚠ … (chưa
  quy được cơ sở Ghế)" — hết cảnh bảng hiện tên mà Ghế báo "–". Mâu thuẫn in kèm bên thua.

`kiem-saoke-quy-coso.php` (16 phép) chạy thật `ghe_coso_cua_may` / `ghe_coso_chuan` / `cong_coso_dong`
với bản đồ giả: GO BT 08 → GO-BT-8 → GO BẾN TRE; đuôi tỉnh; GO AC 03 mâu thuẫn → theo mã cửa hàng;
máy khớp ghế thắng mã cửa hàng; gán tay không bị đè.

### Sao Kê 0.47.0 — Vào bằng vé từ Ghế bị trắng màn "reading 'vietqr'" (hoãn tự vào tới khi tệp JS đọc xong)

Anh Thắng 25/09/2026, ảnh `/sao-ke/#cvietqr`: nội dung trống, toast đỏ *"Cannot read properties of
undefined (reading 'vietqr')"*; *"chạy từ này là nó lỗi, khả năng từ đăng nhập pin"* — đúng: chỉ đường
**vé từ Ghế** dính, đường gõ PIN không.

**Nguyên nhân (tái hiện bằng trình duyệt ẩn, `scratchpad/ui/sk-check.js`).** Khối tự đăng nhập trong
`app.html` nằm GIỮA tệp JS và chạy ngay lúc trình duyệt đọc tới. Có vé (`SAOKE_VE_OK`) thì gọi thẳng
`vaoApp('')` → `nav(#hash)` / `dungOKyTinh()`… đụng các biến `var` khai ở PHÍA DƯỚI (`CONG_TEN`,
`VIEW_CONG`, `CONG_DUNG`, `CG_*`) khi chúng còn `undefined` → ném lỗi (`reading 'map'`, rồi
`reading 'cvietqr'`), **script gãy giữa chừng**, các dòng `var X = {}` phía dưới không bao giờ chạy.
Bấm tab Việt QR sau đó là `CONG_DUNG[nguon]` trên `undefined` → toast `reading 'vietqr'`, view trống.
Đường PIN: `vaoApp(pin)` chạy khi người ta gõ PIN, tệp đã đọc xong, nên không dính.

**Sửa:** nhánh vé bọc `setTimeout(function(){ vaoApp(''); }, 0)` — chạy sau khi tệp đọc hết. Tái hiện
lại ba kịch bản (PIN + #cvietqr · vé không hash · vé + #cvietqr): không còn lỗi, tab dựng đúng, các
biến đều có. `kiem-saoke-ve-hoan-vao.js` (9 phép) canh nhánh vé phải hoãn và các bảng cổng vẫn khai sau
khối tự đăng nhập.

### Sao Kê 0.54.0 — Đợt toàn dòng MỚI đổ HTTP 500: thêm chỉ mục `ma_gd`, dò theo mã tham chiếu cũng theo lô

**Anh Thắng 25/09/2026** (ảnh sau 0.53.0): *Không nạp được (đã nạp xong 1600/24261 — phần ấy đã lưu): Máy chủ trả về trang
HTML thay vì dữ liệu (HTTP 500)*. Hai đợt đầu nhanh vì dòng đã có (webhook đã ghi); đợt 3 toàn dòng MỚI (kỳ cũ webhook
chưa phủ): mỗi dòng mới `cong_dong_trung()` hỏi `… AND ( ref=%s OR ma_gd=%s )` — cột `ma_gd` KHÔNG có chỉ mục → MySQL quét
cả bảng mỗi câu; 800 dòng × 2 câu → PHP hết giờ → fatal → 500.

**Làm:**
- `KEY ma_gd (ma_gd)` trong CREATE + thêm tay khi kích hoạt (`SHOW INDEX` rồi `ALTER TABLE ADD INDEX`), `VER_TBL` 5 → 6.
  Webhook (một dòng, vẫn gọi `cong_dong_trung`) cũng nhanh theo.
- `cong_cu_theo_ref_()`: dòng khoá chưa có → dò tiếp theo ref / mã GD **theo lô** (hai câu `IN (…)` cho cả đợt), cùng luật
  với `cong_dong_trung()` (cùng nguồn, cùng tiền, ref trước rồi maGD, khớp cột ref HOẶC ma_gd). Dòng không thấy ở cả hai lô →
  `luu_cong( …, 'moi' )` chèn thẳng, không hỏi gì nữa.
- Cùng khoá xuất hiện hai lần TRONG MỘT ĐỢT → dòng sau là trùng (trước đây SELECT từng dòng bắt được, nay dò lô tự bắt).
- `@set_time_limit( 120 )` đầu `rpc_napFileCongTx` (nơi hosting cho phép).

**Kiểm:** `kiem-saoke-nap-bu-theo-dot.php` 31 phép (2 dòng mới → 1 câu khoá + 2 câu ref/mã GD, 0 câu rời; ref+tiền trùng
bắt được bằng lô; cùng ref khác tiền → chèn; cùng khoá hai lần trong đợt → chèn 1). FakeWpdb hiểu `IN` theo khoa/ref/ma_gd.
Hai bài cũ ghim `VER_TBL = '5'` nới thành ≥ 5.

**Cài:** cài đè Sao Kê 0.54.0 (kích hoạt lại tự thêm chỉ mục — bảng lớn mất vài giây, một lần), Ctrl+F5, nạp lại file.

### Sao Kê 0.53.0 — Nạp bù nhẹ hẳn: không tính lại kho Ghế tại chỗ, vá mã theo lô, 800 dòng/đợt

**Anh Thắng 25/09/2026** *"chậm quá"* — sau 0.52.0 (chia đợt) vẫn lê thê ở đợt 2/61. Hai thứ nặng còn lại trong MỖI đợt:
(1) `day_ghe_ngay_don_()` tính lại trọn từng ngày đụng tới và ghi đè kho Ghế (hàng trăm câu ghi/ngày), lặp ở đợt sau cùng
ngày; (2) lượt nạp đầu của một file, dòng nào cũng được vá mã cửa hàng (webhook không gửi) bằng một câu UPDATE riêng —
400 câu/đợt.

**Làm:**
- `day_ghe_danh_dau_()`: nạp file / đổi bản đồ cửa hàng / đổi ánh xạ chỉ gọi `VHG_VietQR::quen_ngay()` (một câu xoá/ngày)
  — Ghế tự kéo lại khi có người bấm Xem. Ghế < 2.143.0 thì lùi về tính lại như cũ. Gán máy tay vẫn tính lại ngay (một ngày).
- `cong_va_lo_()`: mỗi ô (ma_ch / diem_ban) một câu `UPDATE … SET ô = CASE id WHEN … END WHERE id IN (…)` cho cả đợt
  (≤400 id/câu); `luu_cong()` nhận `&$va_lo` để gom thay vì UPDATE rời. Webhook (không truyền) không đổi.
- `CG_DOT` 400 → 800: 24.261 dòng ≈ 31 đợt.

**Kiểm:** `kiem-saoke-nap-bu-theo-dot.php` 26 phép (thêm: 1 câu CASE cho 3 dòng, 0 UPDATE rời; đánh dấu thay vì tính lại);
`lib/be-saoke.php` FakeWpdb hiểu `UPDATE … CASE id`, đếm `so_update_lo`. Cả bộ HỎNG 11 / 64.

**Cài:** cài đè Sao Kê 0.53.0 + Ghế 2.143.0, Ctrl+F5 trang Sao Kê rồi nạp lại file (nạp lại không đếm hai lần).

### Sao Kê 0.52.0 — Nạp bù theo ĐỢT: dò trùng theo lô, không còn "Unexpected token '<'" / "File quá lớn"

**Anh Thắng 25/09/2026:** *"nạp file bù rất lâu và hay lỗi"* — ảnh 1: 7.816 dòng → *Unexpected token '<', "<html><hea"… is not
valid JSON* (hosting trả trang HTML lỗi thay JSON); ảnh 2: 24.261 dòng → *File quá lớn, tách nhỏ giúp em* (trần 20.000/yêu cầu).

**Nguyên nhân:** một yêu cầu HTTP ôm cả file; mỗi dòng `luu_cong()` hỏi 2–3 câu SQL (dò theo khoá, rồi theo mã tham chiếu +
số tiền) → ~20.000 câu cho 7.816 dòng → quá giờ PHP/hosting → trang HTML.

**Làm:**
- **Màn hình chia đợt** (`cgNapTxDot`, `CG_DOT = 400`): gửi từng đợt, dòng tiến độ "đang nạp 800/7816 — đợt 2/20", kết quả
  cộng dồn (`cgGopKqTx`: số đếm cộng, danh sách hợp bỏ trùng, "chưa quy được" gộp theo nhãn). Đợt lỗi → nói rõ *đã nạp
  xong N/M dòng, phần ấy đã lưu — bấm Nạp bù lại* (máy chủ chống trùng theo khoá nên nạp lại không đếm hai lần). Cả hai
  luồng (nạp bù VietQR · giao dịch lẻ MoMo/VNPAY) cùng đi qua; luồng giao dịch lẻ trước đọc `r2.moi` (máy chủ trả
  `themMoi`) nên luôn hiện 0 — sửa luôn.
- **Máy chủ dò trùng theo LÔ** (`cong_cu_theo_khoa_`): một câu `SELECT … WHERE khoa IN (…)` cho cả đợt (≤500 khoá/câu);
  `luu_cong( $row, &$kq, $cu_biet )` nhận dòng đã dò sẵn (mảng = đã có → đi thẳng đường VÁ; `false` = chưa có theo khoá →
  vẫn qua `cong_dong_trung()` dò theo mã tham chiếu rồi chèn; `null` = tự hỏi như cũ — webhook không đổi). Trần mỗi đợt
  2.000 dòng (bản app cũ gửi cả file thì máy chủ chỉ đường tải lại trang). Trả thêm `dsMaCH`, `thieuBanDo` không cắt 30
  (để hợp các đợt không hụt).
- **Cầu gọi** (`shim google.script.run`): máy chủ trả trang HTML thì báo *"Máy chủ trả về trang HTML thay vì dữ liệu (HTTP
  504) — thường là quá giờ xử lý hoặc tường lửa hosting chặn"* thay vì lỗi JSON khó hiểu.
- Bệ đỡ thử `lib/be-saoke.php`: FakeWpdb hiểu thêm câu `khoa IN (…)`, đếm `so_select_lo` / `so_get_row_khoa`.

**Kiểm:** `kiem-saoke-nap-bu-theo-dot.php` (5 dòng: 3 đã có + 2 mới → 1 câu dò lô, 0 câu theo dòng, vá 3 mã cửa hàng, nạp
lại 0 thêm; khoá lạ nhưng ref+tiền trùng vẫn bị chặn; trần 2.000; 35 mã lạ trả đủ) + `kiem-saoke-nap-bu-gop.js` 11 phép
(chia 1.000 dòng = 400·400·200, tiến độ, gộp, đợt 2 lỗi → `loi(msg, 400, 1000)` và dừng, 0 dòng không gọi máy chủ).

**Cài:** cài đè 0.52.0 rồi **tải lại trang Sao Kê (Ctrl+F5)** để lấy app.html mới; nạp file 24.261 dòng ≈ 61 đợt, có tiến độ.

### Sao Kê 0.51.0 — Chữ hiệu "Posh" không phải tên máy · tên điểm bán là cấp cơ sở · hai nút Gán / Tạo cơ sở mới bên Ghế

**Anh Thắng 25/09/2026** gửi `store_export` + `transactions` của **tài khoản VietQR thứ hai** (AEON Hải Phòng / Huế / Long
Biên, Sân bay Cam Ranh… + chuỗi JP) và ảnh màn nạp bù: *"Cơ sở chưa gán mã (473)"* toàn tên máy. Hỏi *"Nếu chưa gán anh
tạo cửa hàng mới được không"* → *"Làm luôn 2 nút đó đi em"*.

**Đọc file thật:** 989/1666 cửa hàng đặt tên đuôi "Posh" ("AE Huế 04 Posh", "AEHP 01 Posh"); 3781/3817 giao dịch là
`PaymentForOrder` (không mang tên máy trong nội dung → chỉ quy được qua MÃ CỬA HÀNG); cột **Tên điểm bán** (182 giá trị,
"POSH Aeon Mall Huế") mới là cấp cơ sở. Hậu quả của đuôi "Posh": `cong_coso()` không cắt được số máy → mỗi máy thành một
"cơ sở" (473 dòng); `chuan_may()` ra `aehp01posh` ≠ `aehp1` của ghế "AEHP-1" → tiền rơi "không khớp".

**Làm:**
- `bo_duoi_hieu_()` / `bo_hieu_()`: bỏ chữ hiệu POSH ở đuôi / hai đầu trước khi so. `cong_coso()`, `chuan_may()`,
  `ghe_coso_cua_may()` dùng đuôi; `ghe_coso_chuan()` lùi thêm khoá bỏ chữ hiệu hai đầu ("POSH Aeon Mall Huế" ↔ "AEON MALL
  HUẾ"). Giữa tên thì giữ ("OCP POSH 01").
- Nhân chứng 2b trong `cong_coso_dong()`: **tên điểm bán** của mã cửa hàng (`vqr_diem_theo_ma_`, cache trong lượt, quên
  cùng `vqr_ch_quen_`) khi tên cửa hàng không ra ghế/cơ sở.
- `ax_cua_may_()`: MỘT chỗ tra ánh xạ cho bốn màn (tên máy · cơ sở suy từ tên máy · tên điểm bán) — trước là bốn bản chép
  cùng biểu thức; ánh xạ ghi theo nhãn điểm bán (nút Gán) nhờ vậy mới tra ra.
- Nạp bù: "chưa gán" nay = **không quy được về cơ sở Ghế bằng bất kỳ nhân chứng nào** (hỏi `cong_coso_dong()`), nhãn gom
  theo tên điểm bán; trả `chuaGan[{ten,soGd,tien,maCH,tenMay}]` (≤80, theo tiền giảm dần) + `soChuaGan`.
- Màn hình: bảng "Cửa hàng cổng CHƯA QUY ĐƯỢC về cơ sở Ghế" với mỗi dòng **[chọn cơ sở Ghế] Gán** (→ `luuAnhXaCuaHang`,
  đường cũ) và **＋ Tạo cơ sở mới bên Ghế** (hỏi tên, mặc định = nhãn → RPC mới `taoCoSoGhe` → `VHG_May::luu_coso(0, tên)`
  của Ghế — chặn gần trùng, báo móc Chi Phí — rồi ghi ánh xạ nhãn → tên với cờ `gheMoi=1` vì bộ đệm tên cơ sở trong lượt
  chưa biết cơ sở vừa tạo; ghi nhật ký Ghế). Xong dòng nào đánh dấu tại dòng. Ánh xạ ghi xong → kho Ghế 7 ngày gần tự
  tính lại (0.50.0); xa hơn bấm ↻ ở Báo cáo tổng. `can_pin` 36.

**Kiểm:** `kiem-saoke-duoi-posh-diem-ban.php` 47 phép (bốc hàm thật, VHG_May giả); cập nhật đếm ở `kiem-saoke-quy-coso`,
`kiem-bi-danh-coso` (3 chỗ gọi `cong_coso_dong`), `kiem-saoke-rpc-nhan-phien-ve`, `kiem-saoke-vqr-hai-tai-khoan`,
`kiem-saoke-vqr-cache-nhanh` (`can_pin` 36); `kiem-vietqr-tung-may` bốc thêm `ax_cua_may_`/`bo_duoi_hieu_`.

**Cài:** cài đè Sao Kê 0.51.0, nạp lại **Danh sách cửa hàng** (file `store_export` mới nhất — màn báo 5 mã chưa có), rồi
nạp lại file giao dịch: bảng "chưa quy được" chỉ còn những điểm thật sự chưa có bên Ghế, gán/tạo ngay tại chỗ.

### Sao Kê 0.50.0 — Bản đồ cửa hàng đọc một lần · lọc ngày dùng chỉ mục · ĐẨY số VietQR sang kho Ghế

Cùng lượt với Ghế 2.142.0 (xem mục ấy về nguyên nhân đo được). Làm:
- `vqr_ds_ch()` / `vqr_ma_tat_ca()` cache trong lượt, `vqr_may_theo_ma()` nhớ kết quả theo mã; `vqr_ch_quen_()`
  sau hai chỗ `update_option('saoke_vqr_ch')`. 40.000 lượt hỏi mã: `get_option` gọi 1 lần, 0,01s (bản cũ 2,2–9,4s
  cho 20.000). Màn Sao Kê cổng, đối soát, báo cáo VietQR cho Ghế đều hưởng.
- Lọc ngày so thẳng cột: `thoi_diem>=%s` / `thoi_diem<%s` (mốc ĐẾN = 00:00 ngày sau) thay `DATE(thoi_diem)>=…`
  ở 4 màn + `thoi_diem >= %s AND thoi_diem < %s` ở báo cáo theo máy — chỉ mục `thoi_diem` (có từ đầu) nay dùng được.
- Một luật quy dòng: `vietqr_quy_dong_()` (+ `vietqr_gom_()` ba rổ). `vietqr_theo_may_ngay()` lặp qua nó;
  `vietqr_theo_coso_ngay()` SUY từ bản theo máy (bỏ vòng lặp chép luật). `cong_may_dong()` còn 6 chỗ gọi,
  `cong_coso_dong()` còn 2 — ba bài đếm đã cập nhật kèm lý do.
- Đẩy sang Ghế (khi có lớp `VHG_VietQR`): `day_ghe_dong_()` sau `luu_cong()` = dòng mới trong webhook;
  `day_ghe_ngay_()` sau gán máy tay (đọc ngày giao dịch); nạp file kết xuất / nạp Google Sheet ghi dấu từng ngày
  đụng tới (kể cả dòng trùng được VÁ `ma_ch`) rồi `day_ghe_ngay_don_()` một lần cuối lượt; nạp / xoá bản đồ cửa
  hàng và ba chỗ ghi ánh xạ → `day_ghe_gan_day_(7)`. Mọi lỗi bên Ghế nuốt + `error_log` — webhook luôn được trả lời.

**Kiểm:** `kiem-saoke-vqr-cache-nhanh.php` 44 phép; `kiem-saoke-webhook-may.php` thêm `goi('vqr_ch_quen_')` sau
mỗi lần bài sửa option tay (bài đo luật đọc mã, không đo cache). `can_pin` vẫn 35.

### Sao Kê 0.49.0 — Nhiều tài khoản VietQR chính thức ("thêm tài khoản thứ 2 của VietQR")

Anh Thắng 25/09/2026, đang ở khối "Cài đặt & công cụ" tab Việt QR: *"anh muốn thêm tài khoản thứ 2 của
VietQR"*. Bản cũ chỉ có MỘT cặp username/password (`saoke_vqr_user/pass`) cho cổng gọi Token URL, và
callback không phân biệt tài khoản.

**Làm gì.**
- Option `saoke_vqr_tk` = danh sách tài khoản: nhãn · username · password · số TK nhận · ngân hàng.
  Cặp cũ tự thành tài khoản #1 khi danh sách trống (cài đè không phải khai lại).
- Token: payload `exp=…;tk=<id>`, ký bằng mật khẩu của CHÍNH tài khoản → đổi `tk=` là chữ ký sai. Token
  cấp trước 0.49.0 (không có `tk=`) vẫn hợp lệ, gán tài khoản #1.
- Callback: biết tài khoản từ token; payload thiếu số TK / ngân hàng thì lấy của tài khoản ấy — cho cả
  Sao kê ngân hàng (`saoke_gd.so_tk`) lẫn bảng cổng (`saoke_cong.so_tk`). Log ghi tên tài khoản.
- `getSaoKeCong(..., tk)`: tham số thứ 5 = số TK để lọc; cộng theo tài khoản TRƯỚC khi lọc (ô xổ kể đủ);
  bên bank lọc cùng tài khoản; trả `taiKhoan[]` + `locTk`.
- App tab Việt QR: ô **🏦 Tài khoản VietQR** (nhãn · số TK · ngân hàng · tổng trong kỳ); dòng phụ dưới thẻ
  "Từ cổng" kể tổng từng tài khoản khi có ≥2.
- WP Admin → Sao Kê: bảng tài khoản (mỗi dòng một tài khoản, dòng trống cuối = thêm mới, ô Xoá). Mật khẩu
  trống = giữ cũ. Dòng đầu chép sang cặp cũ cho chỗ nào còn đọc cặp cũ.

**Việc anh làm:** WP Admin → Sao Kê SePay → "VietQR chính thức" → điền dòng mới (nhãn, username,
password, số TK nhận, ngân hàng) → Lưu. Bên cổng VietQR (tài khoản 2): khai **cùng Token URL và
Callback URL** như tài khoản 1, chỉ khác username/password. Nạp *Danh sách cửa hàng* của tài khoản 2
vào cùng bản đồ (mã cửa hàng khác nhau thì không đè nhau).

`kiem-saoke-vqr-hai-tai-khoan.php` (30 phép): cặp cũ → tk1, token cũ còn dùng, hai tài khoản cấp token
riêng, lẫn user/pass → 401, đổi tk= trong token → chối, hết hạn / tài khoản lạ → chối, danh sách công
khai không kèm mật khẩu, callback/cong_nhan_webhook/getSaoKeCong/admin/app nối đúng.

### v2.138.0 — GỘP SỔ: "Nhập 2 điểm này lại thành 1" (⇄ từng hàng + 📒 gộp sổ theo bí danh)

Anh Thắng 24/09/2026, hai ảnh Báo cáo tổng: **POSH MN CGV VINCOM LANDMARK** — 1.040.000 tiền mặt
(600.000 + 440.000 theo báo cáo nhân viên), VietQR trống; **CGV LANDMARK 81 · KH00245 · 2 ghế** —
VietQR 870.000, tiền mặt trống. *"Nhập 2 điểm này lại thành 1."*

**Vì sao 2.137.0 chưa đủ.** (1) Bí danh chỉ kéo được **tiền VietQR** về đích; báo cáo tiền mặt vẫn
nằm dưới tên cũ vì `bc` lưu tên cơ sở dạng chữ (`coso`, `coso_key`) — Báo cáo tổng, MISA, công nợ
vẫn ra hai dòng. (2) Nút **⇄** thực ra chỉ có trong khối "CƠ SỞ GẦN TRÙNG TÊN" (cặp lệch một ký tự);
hai tên khác hẳn nhau như trên **không có nút nào để gộp** — BAN_GIAO 2.137.0 ghi "nút ⇄ trên từng
hàng, nút 🔁 khai bí danh" là **chưa đúng với mã** (trang không đổi ở bản ấy). (3) Payload cơ sở
gửi ra trang **chưa từng có `dong_cua`** → khối "cơ sở đã đóng cửa" (2.134.0) không bao giờ tách,
nút 🚪 luôn trông như đang mở.

**Làm gì.**
- `VHG_May::gop_so_coso($ten_dich, $ds_ten_cu)`: đổi **NHÃN** cơ sở trên mọi dòng sổ của tên cũ
  (theo `coso_key`) sang tên đích. **Không xoá dòng tiền, không sửa con số.** Trùng khoá:
  `bc (coso_key, ngay, lan)` → dòng cũ sang `lan` kế tiếp; `bc_khoa`/`bc_ma_misa` (không phải tiền)
  → bỏ dòng cũ, kể ra (hết "dính vào MISA"); `bc_thang_bs`/`bc_congno_dau` (tiền theo tháng) →
  **TREO** dưới tên cũ, kể ra — người quyết, máy không cộng bừa. `bc_pin.coso` (danh sách phụ trách)
  thay tên cũ bằng tên đích để nhân viên không mất điểm khỏi màn nhập. Trả `tom_tat` một câu.
- `gop_coso()` gọi gộp sổ **sau khi xoá nguồn xong** (xoá hụt → chưa dời dòng sổ nào).
- `gop_so_bi_danh($id)`: kéo sổ của mọi bí danh về — cho cơ sở đã gộp ở 2.137.0.
- Trang: nút **⇄** trên **từng hàng** (chọn đích trong ô xổ "tên · mã KH", hai lần xác nhận); nút
  **📒** khi hàng có bí danh; hàng hiện 🔁 tên cũ. Cả hai **chỉ Quản trị** (`coso_gop`, `coso_gopso`
  chặn ở cổng). Payload gửi kèm `dong_cua`, `bi_danh`.

**Việc anh làm:** Địa điểm → hàng "POSH MN CGV VINCOM LANDMARK" → **⇄** → chọn "CGV LANDMARK 81 ·
KH00245" → xác nhận hai lần. Hộp thoại sau đó kể: N báo cáo tiền mặt đã dời, Unit MISA cũ bỏ (nếu
đích đã có), tháng nào còn treo. Nếu đã ⇄ ở bản trước (hàng cũ đã mất) thì bấm **📒** trên hàng
"CGV LANDMARK 81". Rồi mở Báo cáo tổng: một dòng, tiền mặt 1.040.000 + VietQR 870.000.

`kiem-gop-so-coso.php` (43 phép) chạy thật `gop_so_coso`/`gop_coso`/`gop_so_bi_danh` trên CSDL giả
có bảng: đổi nhãn, số không đổi, trùng ngày → lần 2, không DELETE bc/bc_dong, Unit đích thắng, tháng
bổ sung trùng → treo + cảnh báo, PIN đổi phạm vi, gộp sổ chạy sau xoá nguồn, xoá hụt không dời, cổng
chặn không phải admin, payload có dong_cua/bi_danh, câu "báo cáo cũ giữ nguyên" đã gỡ hết.

### v2.139.0 — Sổ mồ côi: "Địa điểm đã xoá, nhưng nó đang dính dữ liệu cũ, nên xuất MISA ra cả điểm xoá và điểm mới"

Anh Thắng 24/09/2026, ảnh Địa điểm lọc "LANDMARK": chỉ còn **một** hàng CGV LANDMARK 81 (KH00245,
Unit 50CGVLM, tên MISA "POSH MN CGV VINCOM LANDMARK") — *"nó đang hiện 1 điểm sao mà gộp"*. Tức
"POSH MN CGV VINCOM LANDMARK" **không còn là cơ sở**; nó chỉ còn trên các dòng `bc` cũ (1.040.000
tiền mặt). Nút ⇄ của 2.138.0 cần hai hàng, ở đây chỉ có một.

**Gốc.** Xoá / đổi tên cơ sở không đụng sổ (đúng luật giữ tiền) → tên cũ đứng riêng một dòng ở Báo
cáo tổng và MISA, mà danh mục không còn chỗ nào để bấm.

**Làm gì.**
- `VHG_May::ten_so_mo_coi()`: tên có trong `bc` (một câu GROUP BY, kèm số báo cáo, khoảng ngày, tổng
  tiền từ `bc_dong`) hoặc trong `bc_ma_misa` mà **không** là tên/bí danh của cơ sở nào. Gửi ra trang
  qua `cosoMoCoi`, **chỉ Quản trị**.
- Tab Địa điểm: khối xanh **"📒 TÊN CŨ CÒN TRONG SỔ, KHÔNG CÒN TRONG DANH MỤC"** trên bảng — mỗi tên
  một dòng: số báo cáo · từ → đến · tiền · Unit MISA, ô xổ chọn cơ sở đích, nút **Gộp sổ**.
- `gop_so_ten_cu($ten_cu, $dich)` (cổng `coso_gopso_ten`, Quản trị): chối nếu tên cũ là tên/bí danh
  của cơ sở đang có (khi ấy ⇄ mới đúng); gộp sổ (`gop_so_coso`) rồi ghi tên cũ làm **bí danh** của
  đích để tiền VietQR cổng còn ghi tên cũ vẫn về.
- **Đổi tên cơ sở (✎) nay kéo sổ theo** (`luu_coso`): tên khác khoá → `gop_so_coso(tên mới, [tên
  cũ])` + tên cũ thành bí danh; chỉ khác hoa-thường/dấu → đổi nhãn hiển thị trên dòng sổ
  (`dong_bo_nhan_so_`). Đổi sang tên/bí danh của cơ sở KHÁC → **chối**, chỉ sang ⇄ (trước đây cho
  qua → hai cơ sở một tên). Đây là chỗ đã sinh ra ca này; từ nay đổi tên không tách sổ nữa.

**Việc anh làm:** Địa điểm → khối 📒 trên cùng → dòng "POSH MN CGV VINCOM LANDMARK" (2 báo cáo …
1.040.000đ) → chọn "CGV LANDMARK 81 · KH00245" → **Gộp sổ** → xác nhận. Xuất MISA lại: một dòng.

`kiem-gop-so-coso.php` (65 phép) thêm: liệt kê sổ mồ côi (bỏ tên/bí danh đang có, kể Unit MISA
không còn báo cáo), gộp tên cũ vào đích + bí danh, chối khi tên cũ thuộc cơ sở khác, đổi tên kéo sổ /
chối trùng / cùng khoá chỉ đổi nhãn / không đổi tên không chạm sổ, cổng và khối trang.

### v2.140.0 — Gửi lại mà máy không chạy = SỬA lần trước, không phải thu lần nữa (hết cộng đôi Thực thu)

Anh Thắng 25/09/2026, ảnh màn Duyệt: **CGV-CT-01** ba dòng cùng ngày (215→234 · 234→234 · 234→234),
**CGV-CT-02** cũng ba (245→299 · 299→299 · 299→299). Hai dòng sau **Actual 0** nhưng "Thực thu ghi đè"
160.000 / 300.000 vẫn ghi → tổng ngày **920.000đ** trong khi tiền thật là 460.000đ. *"Check giúp anh lỗi
này."*

**Nguyên nhân.** Nhân viên gửi lại để sửa tiền (lần đầu gõ Thực thu 0). Luật 29/08 "mỗi lượt Gửi = một
lần thu mới" chèn lần mới; chỉ số trước tự nối bằng chỉ số sau lần trước nên Actual = 0 — nhưng **Thực thu
và QR là số gõ tay, không tự triệt tiêu**, nên mỗi lần gửi lại là cộng thêm một lần. Chốt 120 giây
(12/09) không bắt được vì số đã đổi và cách xa hơn. Màn nhân viên sau khi gửi vẫn giữ nguyên bộ số đã
gõ nên bấm Gửi lần nữa là đi lên y nguyên. Màn Duyệt không hiện lần mấy / giờ gửi nên ba dòng trông y
hệt nhau.

**Sửa ba lớp.**
- Máy chủ `VHG_BaoCao::gui_lai_de_()` (gọi đầu `luu()`): ghế gửi lại với **chỉ số sau đúng bằng chỉ số
  sau đã lưu gần nhất trong ngày** → máy không chạy thêm → **đè** tiền mặt / QR / ghi chú / ảnh lên dòng
  đã lưu (giữ chỉ số + Actual), ghi `bc_undo` như Sửa 24h, ghi chú có dấu "↩ Gửi lại HH:MM (đè lần n)".
  Ghế có chỉ số mới vẫn thành lần mới. Cả lượt là gửi lại → khai nộp tiền mới thay khai cũ
  (`nop_lai_header_`), trả `updated=true` với câu báo nói rõ. Dòng đã nộp tiền / đính bill → **lỗi chỉ
  đường**, không lặng lẽ tạo lần mới. QR > Actual mà không Thực thu → lỗi (tiền mặt âm).
- Màn Duyệt (`chi_tiet()` + `ktdRow`): ngày có ≥2 lần thì mỗi dòng ghi **lần N · gửi HH:MM · ai**; dòng
  chỉ số đứng mà còn tiền mặt / QR và ghế có ≥2 dòng → cờ đỏ **⚠ NGHI TRÙNG** để kế toán Xoá dòng thừa
  (dữ liệu cũ trước bản này).
- Màn nhân viên: gửi xong **nạp lại bảng** (`selectLoc`) — chỉ số trước = chỉ số sau vừa gửi, ô nhập
  trống; bấm Gửi nhầm thì không có gì để gửi.

**Dọn ngày trong ảnh:** vào Duyệt → cơ sở đó → hai dòng CGV-CT-01 và CGV-CT-02 mang cờ NGHI TRÙNG
(lần 2, Actual 0): **Xoá** dòng lần 2 (vào thùng rác, hoàn tác được), giữ lần 1 (chỉ số) và lần 3 (tiền
+ ghi chú "hoàn khách"). Tổng ngày về 460.000đ tiền mặt · 240.000đ QR.

`kiem-gui-lai-may-khong-chay.php` (30 phép): đè đúng dòng, giữ chỉ số/Actual, undo, ghi chú không phình,
chỉ số nhích → lần mới, hỗn hợp, bill/nộp → lỗi, QR > Actual → lỗi, nộp đủ theo số mới, ảnh nối, khai
nộp lại header, thứ tự gọi trong luu(), trường mới của chi_tiet, selectLoc sau gửi, cờ NGHI TRÙNG.

### v2.144.0 — Báo cáo tổng: ô "🔍 Lọc cơ sở" ngay trên thanh điều khiển ("Cho lọc theo cơ sở")

**Anh Thắng 25/09/2026** (sau khi kho VietQR chạy ổn): *"Cho lọc theo cơ sở"*.

**Làm:** ô nhập `bct-loc` cạnh nút Số liệu; gõ là lọc ngay ở màn hình (không gọi lại máy chủ): bỏ dấu, không phân biệt hoa
thường, nhiều tên cách nhau dấu phẩy, khớp cả Mã KH và (chế độ Từng ghế) tên/mã ghế. Hàm thuần `bctApDungLoc(r, loc)` trả
bản sao đã lọc với **TỔNG, tổng cột, VietQR thực, số ghế tính lại theo phần đang lọc** (số ghế đếm một lần mỗi cơ sở ở chế
độ Từng ghế); `bctLoad`, `bctVeLai` (gõ ô lọc) và `bctXuat` (.csv) đều đi qua nó. Bảng in dòng "🔍 Đang lọc «…»: N/M cơ sở —
TỔNG và .csv theo phần đang lọc". Ô lọc giữ giá trị khi đổi Gộp theo / Số liệu (vẽ lại thanh từ `BCT_LOC`).

**Kiểm:** `kiem-bct-loc-coso.js` 16 phép (không dấu, mã KH, nhiều tên, tổng theo phần lọc, biên rỗng/không khớp, từng ghế).

### v2.143.0 — `VHG_VietQR::quen_ngay()`: Sao Kê chỉ ĐÁNH DẤU ngày cũ, Ghế tự kéo khi Xem (nạp bù hết lê thê)

**Anh Thắng 25/09/2026** *"chậm quá"* (nạp bù 24.261 dòng, đợt 2/61). Một phần lớn thời gian mỗi đợt là Sao Kê tính lại
trọn từng ngày đụng tới rồi ghi đè kho Ghế (`nhan_ngay`: một ngày ~1.000 giao dịch → hàng trăm câu INSERT), và đợt sau
cùng ngày ấy lại tính lần nữa. Nạp file không cần số ngay — chỉ cần kho BIẾT ngày ấy đã cũ.

**Làm:** `quen_ngay( $ds_ngay )` xoá trọn dòng của các ngày (kể cả dấu) → ngày thành "thiếu" → lượt Xem kế tiếp tự kéo
(≤3 ngày ngay trong lượt, nhiều hơn màn hình kéo từng đợt có tiến độ — cơ chế 2.142.0). Webhook về ngày đã quên →
`cong_gd()` thấy chưa có dấu → kéo trọn ngày, vẫn đúng. Sao Kê 0.53.0 gọi hàm này sau nạp file / đổi bản đồ / đổi ánh xạ;
Ghế cũ chưa có thì Sao Kê lùi về tính lại như trước. `kiem-vietqr-kho-ghe.php` thêm phép cho `quen_ngay` (48 phép).

### v2.142.0 — Kho số VietQR của Ghế: Sao Kê ĐẨY sang, bấm Xem là tự nạp (hết "không nối được tới máy chủ" lần hai)

**Anh Thắng 25/09/2026**, sau khi cài 2.141.0 vẫn thấy Báo cáo tổng 01→25/09 báo *"Không nối được tới máy chủ"*:
*"khả năng đọc dữ liệu sao kê bị lỗi"* → rồi đổi hướng: *"Thay vậy chúng ta đẩy sao kê qua là trang ghế tự lưu
luôn"* · *"khi có dữ liệu thêm thì ghi vào máy, để cần đọc ngay, chứ sao kê nó đang quá tải mà cứ gọi qua là lúc
được lúc không"* · *"nên lúc bấm xem, là nó tự đẩy đọc và nạp vào trang ghế luôn"*.

**Nguyên nhân thật (đo được):** chỉ mục `bc_dong.ngay` (2.141.0) đúng nhưng không phải nút thắt. Mỗi lần bấm Xem,
`bao_cao_tong()` gọi sang `SAOKE_App::vietqr_theo_coso_ngay()` quy lại TỪNG dòng VietQR trong khoảng; mỗi dòng
gọi `vqr_may_theo_ma()` hai lần, mỗi lần `get_option('saoke_vqr_ch')` giải tuần tự cả bản đồ 74KB, trượt khoá thì
duyệt lại 400 cửa hàng bằng regex. Đo: 20.000 dòng = 2,2s (trúng) … 9,4s (trượt). 25 ngày × hai lần là quá 30s
PHP → PHP bị ngắt → status 0 → câu "không nối được". Sửa bên Sao Kê 0.50.0 (cache) đưa 40.000 lượt hỏi mã về
0,01s — nhưng anh Thắng muốn Ghế KHÔNG gọi sang nữa, và anh đúng: tính lại mỗi lần Xem là để một màn hình phụ
thuộc sức khoẻ của plugin khác.

**Làm:**
- Bảng mới `vhg_bc_vqr` (ngày × `coso_key` × `ma_may` → `so_tien`, `cap_luc`; UNIQUE ba cột + KEY ngày). Lớp
  `VHG_VietQR` (`includes/class-vhg-vietqr.php`). Ba rổ khớp y nguyên `vietqr_theo_may_ngay()`: máy · "chưa rõ
  máy" (`ma_may=''`) · "không khớp cơ sở" (`coso_key=''`, `ma_may=''`) — dòng cuối LUÔN có khi ngày đã đồng bộ,
  là DẤU "ngày này đã có trong kho" và mang `cap_luc`.
- **Sao Kê đẩy sang** (0.50.0): webhook về → `VHG_VietQR::cong_gd()` một câu UPSERT; gán máy tay / nạp file / đổi
  bản đồ cửa hàng / đổi ánh xạ → `nhan_ngay()` ghi đè trọn ngày bị đụng. Ghế cũ không có lớp thì Sao Kê im.
- **Bấm Xem là tự nạp**: `theo_coso_ngay()` / `theo_may_ngay()` kéo ngay trong lượt hôm nay + hôm qua nếu kho cũ
  hơn 10 phút (kẻo một webhook lỡ) và ngày thiếu nếu ≤ 3; thiếu nhiều hơn → trả `vqThieuNgay`, JS `bctNapVq()`
  kéo từng đợt qua `kt_vqr_dongbo` (~8s máy chủ/đợt, trả `tiep`), có dòng tiến độ, xong tự vẽ lại. Không có Sao
  Kê → nói thẳng, không quay vòng; kéo 2 lượt vẫn thiếu → dừng, kể tên ngày.
- Dòng "VietQR trong kho Ghế cập nhật lúc … · ↻ kéo lại từ Sao Kê cho khoảng này" (ép kéo cả ngày đã có — dùng
  sau khi đổi bản đồ / ánh xạ bên Sao Kê). Ghi nhật ký khi kéo lại xong.
- `vietqr_thuc_()` (Báo cáo tổng, MISA) và nhánh Từng ghế/QR đọc kho; bỏ hẳn hai đường gọi sang `SAOKE_App` lúc Xem.
- Gộp sổ / đổi tên cơ sở → `VHG_VietQR::quen_coso()` bỏ các ngày mang khoá cũ, lượt Xem kế tiếp kéo lại dưới tên
  đích (Sao Kê tra bí danh → tên đích). Kho là số SUY RA — xoá không mất tiền; luật "không xoá dòng tiền" không
  áp cho bảng này.

**Kiểm:** `kiem-vietqr-kho-ghe.php` 47 phép (bảng giả trong bộ nhớ, chạy lớp thật): ghi một ngày rồi đọc lại bằng
y ba rổ; theo cơ sở = mọi máy + chưa rõ máy; cộng lẻ webhook = kho cũ + tiền, không đẻ dòng trùng; webhook về ngày
chưa có → kéo trọn ngày thay vì cộng lẻ; chọn ngày kéo lúc Xem; kéo từng đợt chỉ ngày thiếu / ép cả khoảng; quên
cơ sở. `kiem-xuat-misa-tinh.php` chuyển stub sang `VHG_VietQR`. Cả bộ HỎNG 11 / 61 (11 lỗi cũ có sẵn).

**Cài:** cài đè 2.142.0 (bảng tự tạo khi kích hoạt) VÀ Sao Kê 0.50.0. Mở Báo cáo tổng 01→25/09 → lần đầu thấy
dòng "⏳ Đang nạp VietQR từ Sao Kê vào kho Ghế — còn N/25 ngày", vài đợt là xong và bảng tự vẽ lại; từ đó về sau
mở là có ngay.

### v2.141.0 — Báo cáo tổng "không nối được tới máy chủ" khi chọn khoảng ngày rộng (thêm chỉ mục `bc_dong.ngay`)

Anh Thắng 25/09/2026, ảnh khối lọc Báo cáo tổng: **12/09→24/09 chạy được, 01/09→24/09 thì lỗi**,
trắng màn với thông báo *"Không nối được tới máy chủ (mất mạng giữa chừng, hoặc bị chặn)"*.

**Không phải lỗi mạng.** Thông báo ấy hiện khi `readyState` lên 4 mà `status=0` — dấu hiệu chuẩn của
việc **tiến trình PHP bị host cắt ngang** giữa chừng (quá thời gian cho phép một lượt chạy), không
phải PHP trả lỗi có định dạng.

**Nguyên nhân.** `bc_dong` — bảng lớn nhất hệ thống, mỗi ghế mỗi lần thu ghi một dòng, cộng dồn suốt
vòng đời — chỉ có khoá ghép `may_ngay (ma_may,ngay)`. Báo cáo tổng (và Lịch sử, VietQR thực…) lọc
**thẳng theo khoảng ngày**, không kèm điều kiện `ma_may=`. Khoá ghép mà cột đứng đầu không nằm trong
điều kiện lọc thì vô dụng cho câu ấy — MySQL phải dò gần hết bảng thay vì nhảy thẳng tới khoảng ngày.
Bảng càng phình theo tháng, khoảng ngày càng rộng thì càng dễ chạm ngưỡng thời gian máy chủ cho phép.
`bc`, `bc_khoa`, `bc_yeucau`… đều có sẵn `KEY ngay (ngay)` — `bc_dong` là bảng **duy nhất thiếu**.

**Sửa:** thêm `KEY ngay (ngay)` vào `bc_dong` — cả trong định nghĩa bảng (cài mới) lẫn migration tay
(site đang chạy, dò `SHOW INDEX` rồi `ALTER TABLE … ADD INDEX`, idempotent). Không đụng khoá ghép cũ,
không đụng công thức tính tiền nào — thuần tốc độ đọc.

**Việc anh làm:** chỉ cần cài bản này (cài đè lên 2.140.0); chỉ mục tự thêm ngay khi kích hoạt plugin,
không cần thao tác gì thêm trên CSDL. Sau đó khoảng 01/09→24/09 (và các khoảng rộng khác, tới 92 ngày)
chạy bình thường.

`kiem-bao-cao-tong-ngay-rong.php` (9 phép): định nghĩa bảng có khoá mới, migration dò đúng bảng/tên
khoá, chạy thật trên CSDL giả — thêm khi thiếu, không đụng khi đã có — và đếm lại các câu lọc `bc_dong`
theo khoảng ngày thuần đang tồn tại (lý do khoá này cần).

### v2.137.0 (+ Sao Kê 0.45.0) — BÍ DANH cơ sở: gộp tên cũ vào điểm mới mà tiền VietQR không rơi

Anh Thắng 24/09/2026, ảnh CSV Báo cáo tổng: **"POSH MN CGV VINCOM LANDMARK"** (không Mã KH, 0 ghế)
nằm cạnh **"CGV LANDMARK 81 · KH00245"**; **"BỆNH VIỆN UNG BƯỚU HCM"** (0 ghế, 45,87 triệu theo cục)
cạnh **"BV UNG BƯỚU · KH00186"** — *"điểm này đang dính vào MISA, cần loại bỏ ngay, vì điểm mới đã
có"*; *"nguyên nhân bị sao bị trùng 2 điểm"*.

**Nguyên nhân trùng.** Hai TÊN cho một chỗ. Tên có ghế + Mã KH là tên bên Ghế; tên kia là **tên cửa
hàng bên cổng VietQR** (kiểu Excel "POSH MN …"), cũng tồn tại như một cơ sở **rỗng** bên Ghế. Khi
máy cổng không khớp được ghế nào (tên máy bên cổng khác tên khai ghế), Sao Kê lùi về **tên cửa hàng**
— và vì Ghế có cơ sở đúng tên ấy, tiền VietQR đổ vào đó thành một dòng riêng. Dấu vết đúng như ảnh:
dòng trùng **0 ghế, không Mã KH, tiền chỉ có theo cục vào vài ngày** = tiền ngân hàng, không phải
báo cáo ghế. **Xoá suông cơ sở rỗng là sai kiểu khác**: tên mất → Sao Kê trả "không khớp" → tiền
biến khỏi báo cáo, không sang điểm mới.

**Làm gì.**
- Ghế: cột **`coso.bi_danh`** (mỗi dòng một tên). `gop_coso()` **giữ tên cũ (và bí danh của nó) làm
  bí danh của đích** — đọc trước khi xoá nguồn, kẻo mất; xoá nguồn hụt thì không ghi. Nút **⇄** trên
  từng hàng: gộp vào cơ sở bất kỳ (chọn theo số), hai lần xác nhận. Nút **🔁**: khai bí danh tay. Một
  tên không thể là tên/bí danh của hai cơ sở — máy chủ chối, bảo gộp nếu là cùng một điểm.
- Sao Kê 0.45.0: `ghe_coso_chuan($ten)` tra **tên hoặc bí danh → tên đích**; ba chỗ lùi theo
  `tenChuan` dùng nó. Tên thật thắng bí danh. `ghe_ds_coso()` dùng `SELECT *`: Ghế cũ chưa có cột vẫn
  chạy.

**Việc anh làm (nạp cả hai plugin trước):** Địa điểm → hàng "POSH MN CGV VINCOM LANDMARK" → **⇄** →
chọn "CGV LANDMARK 81". Tương tự "BỆNH VIỆN UNG BƯỚU HCM" → **⇄** → "BV UNG BƯỚU". Dòng cũ hết dính,
tiền VietQR mang tên cũ chạy về đúng chỗ. **Gốc sâu hơn** để dọn dần: tên máy bên cổng phải khớp
`ten_khai` của ghế (xem `chuan_may` 0.44.0) — khớp được thì không cần lùi theo tên cửa hàng nữa.

`kiem-bi-danh-coso.php` (17 phép): gộp ghi bí danh vào đích trước khi xoá nguồn; xoá hụt không ghi;
bí danh không trùng hai nơi (so bỏ dấu); Sao Kê tra bí danh ra tên đích, tên thật thắng, không có
cột `bi_danh` vẫn chạy.

### v2.136.0 — SỬA LỖI 2.133.0: gõ Mã KH trong hàng cơ sở bị bắn thành lệnh "đổi cơ sở" ghế

Anh Thắng 24/09/2026, ảnh sửa Cali Thảo Điền → KH00270 rồi Lưu: *"Chưa chọn cơ sở đích — không đổi"*.

**Lỗi của 2.133.0.** Sắp xếp theo mã ghế gắn `data-csma` lên `<tr>` cơ sở — **trùng tên** với thuộc
tính của ô `<select>` "Đổi cơ sở" cho ghế, mà hai chỗ gán `onchange` cho **mọi** phần tử mang tên
ấy. Sự kiện `change` từ ô Mã KH nổi bọt lên `<tr>` → bắn `may_coso` với `ma` = mã ghế nhỏ nhất của
cơ sở và đích rỗng → máy chủ chối. **Không ghế nào bị dời** — nhưng chỉ vì đích rỗng; đây là loại
lỗi có thể dời ghế thật nếu cấu trúc khác đi một chút.

- Đổi tên thuộc tính sắp xếp thành `data-cssapma`.
- **Siết hai chỗ gán**: chỉ bắt `select[data-csma]`. Lệnh dời ghế phải đi từ đúng cái ô chọn; một
  thuộc tính lạc trên thẻ khác không bao giờ thành lệnh dời ghế nữa.
- Lưu Mã KH có đường riêng (`coso_luu`) nên nhiều khả năng đã lưu được dù có hộp báo — kiểm lại hàng
  Cali Thảo Điền; chưa đúng thì Lưu lại sau khi nạp bản này.

`kiem-cs-sap-theo-ma.js` thêm 2 phép: hàng cơ sở không mang `data-csma`; hai chỗ gán chỉ bắt `<select>`.

### v2.135.0 — Admin bổ sung DOANH THU TỔNG THEO THÁNG cho từng cơ sở (màn Doanh thu địa điểm)

Anh Thắng 23/09/2026: *"hiển thị doanh thu tháng/năm, ngày trước đang thiếu. Cho phép admin bổ sung
lại doanh thu tổng theo tháng/năm của từng cơ sở trước. Còn bổ sung máy theo ngày thì sau."* —
*"Chỉ áp dụng admin."*

- Bảng riêng **`bc_thang_bs`** (cơ sở × tháng, UNIQUE): tổng · tiền mặt · QR · ghi chú · ai · lúc.
  **Không đẻ dòng giả vào `bc_dong`**: số tổng tháng không có ngày, không có ghế — nhét vào sổ ngày
  là Báo cáo ngày MISA in một cột nhảy vọt, Báo cáo tổng có một ô bằng cả tháng. Nên **chỉ màn
  tháng/năm (`lich_su`) đọc bảng này; MISA và Báo cáo tổng không đụng** — nói rõ ngay trên form.
- **Không chồng lên tháng đã có dữ liệu ngày**: lưu thì chặn (kể ra N dòng đã có); dữ liệu ngày về
  sau (nhập cũ, nộp muộn) thì `lich_su()` **bỏ qua** số bổ sung và kêu đỏ *"BỎ QUA — tháng đã có
  dữ liệu ngày, xoá dòng bổ sung đi"*. Cộng cả hai là đếm hai lần; lặng lẽ bỏ là admin không biết.
- Tổng bắt buộc; TM/QR tuỳ — thiếu một vế suy từ hai vế kia; không có gì thì ghi toàn tiền mặt và
  **nói ra** trong thông báo. TM + QR ≠ Tổng thì chối. Không nhận tháng chưa tới, không nhận số âm.
- Form gập **"✎ Bổ sung doanh thu TỔNG THÁNG"** ở màn Doanh thu địa điểm, chỉ vẽ khi Quản trị; cổng
  `kt_thang_bs_luu/xoa` kiểm `la_quan_tri()` lần nữa. Lưu lại cùng tháng = ghi đè (hộp hỏi nói).
- Thẻ tháng bổ sung gắn nhãn cam **"✎ bổ sung tay"** thay chỗ "N ghế · N ngày"; mở ra thấy nguồn
  số, ai nhập, lúc nào, và nút Xoá (admin). Nhật ký ghi từng lượt.

`kiem-bo-sung-tong-thang.php` (16 phép): bốc `thang_bs_luu()` + `lich_su()` ra chạy với `$wpdb`
giả — kiểm luật suy TM/QR, chặn tháng đã có ngày, trộn vào năm, bỏ qua + kêu khi có dữ liệu ngày,
cổng chỉ admin.

### v2.134.0 — Bảng Unit ID MISA: thiếu lên đầu, đóng cửa xuống khối riêng; khẳng định MISA chỉ đi từ dòng tiền

Anh Thắng 23/09/2026: *"khi cửa hàng đóng cửa cần ẩn cơ sở và tạo bảng riêng… ở cuối trang. Xuất
MISA nếu tháng đó phát sinh doanh thu; không phát sinh thì không hiện. Cơ sở nào thiếu thông tin như
unit hoặc mã ghế thì hiện đầu để kế toán bổ sung — chứ nhiều quá không biết được."*

**Từng ý, cái gì đã có, cái gì làm mới:**
- *Đóng cửa → bảng riêng cuối trang*: tab Địa điểm đã có từ 2.75.0 (🚪) và 2.132.0 (🙈 chỉ còn ghế
  ẩn). **Mới**: bảng **Unit ID MISA** (tab Xuất MISA) cũng tách cơ sở đóng cửa xuống khối gập cuối.
- *MISA chỉ ra cơ sở có doanh thu tháng đó*: **đã đúng từ đầu** — chứng từ và Báo cáo ngày đi từ
  `bc_dong`, không đi từ danh mục. **Mới**: thêm phép kiểm khẳng định (cơ sở có trong danh mục nhưng
  không có dòng tiền → không hiện), để ai sửa sau này không vô tình đổi.
- *Thiếu thông tin lên đầu*: **mới** — `ma_misa_ds()` gắn `thieu` = [unit · kh · ghe] cho từng cơ
  sở đang mở và **xếp thiếu nhiều nhất lên đầu**; màn hình dán nhãn đỏ *"⚠ thiếu: Unit ID · Mã KH"*
  ngay dưới tên. Thêm cột **Ghế** (ghế sống); 0 ghế tô đỏ. Cơ sở đóng cửa **không** bị tính thiếu —
  không ai bổ sung Unit ID cho một chỗ đã đóng.

`kiem-ma-misa-thieu-len-dau.php` (10 phép) bốc `ma_misa_ds()` ra chạy với `$wpdb` giả: thứ tự, nhãn
thiếu, đóng cửa xuống cuối và không bị gắn thiếu, ghế ẩn không tính là "có ghế".

### v2.133.0 — Địa điểm: thêm sắp xếp "Theo mã ghế (nhỏ → lớn)"

Anh Thắng 23/09/2026: *"cho thêm sắp xếp theo mã ghế"*.

- Mỗi hàng cơ sở mang `data-csma` = **mã ghế nhỏ nhất** của nó, so theo số (`csMaNhoNhat_`, cùng bộ so
  `numeric` với `dsMaHtml_` — một bộ so, không lệch nhau). Mã cấp tuần tự theo ngày mở điểm nên xếp
  theo mã nhỏ nhất ≈ **thứ tự mở điểm**.
- Khoá sắp đệm mọi cụm số lên 10 chữ số rồi so chuỗi → `9999` đứng trước `80013`, `VC-GP-6` trước
  `VC-GP-12`. Cơ sở không có ghế dồn cuối, trong đó vẫn A→Z theo tên; "(chưa gán)" luôn cuối.
- Sửa luôn sót của 2.132.0: khối "🙈 chỉ còn ghế ẩn" nay cũng được sắp cùng luật với ba khối kia.

`tools/test/kiem-cs-sap-theo-ma.js` (9 phép) — bốc `csSapKhoa_` + `csSapMot_` ra chạy trên DOM giả.

### v2.132.0 — Địa điểm: khối riêng "🙈 Cơ sở chỉ còn ghế ẩn" để soi và xoá

Anh Thắng 23/09/2026: *"anh muốn soi lại cơ sở bị ẩn để xoá. Nó đang nằm ở đâu"*.

**Nó không mất — nó trông y như một thứ khác.** Bộ đếm ghế chỉ đếm ghế sống (đúng ý anh 12/09), nên
cơ sở còn 3 ghế ẩn hiện ra "0 ghế" và rơi vào khối gập **"📭 Cơ sở chưa có ghế"**, lẫn với cơ sở mới
tạo chưa gán gì. Đã bấm 🚪 thì nằm ở "🚪 Cơ sở đã đóng cửa".

- Đếm riêng `demAn` (ghế ẩn theo cơ sở). Ô Số ghế in `0 (+3 ẩn)` ở mọi khối — nhìn là biết.
- Chia bốn khối theo thứ tự ưu tiên: 🚪 đóng cửa → có ghế sống (bảng chính) → **🙈 chỉ còn ghế ẩn
  (mới)** → 📭 trống thật. Khối mới đặt ngay dưới bảng chính, tiêu đề màu cam, nói rõ 🗑 là xoá hẳn
  cả cơ sở lẫn mã ẩn và sổ tiền giữ nguyên.
- Không đụng máy chủ: dữ liệu `an` đã có sẵn trong danh sách ghế màn này.

`tools/test/kiem-coso-chi-con-ghe-an.js` (9 phép) — bốc đúng đoạn chia khối ra chạy với dữ liệu giả:
cơ sở toàn ghế ẩn phải vào `hAn`, không vào `hRong`; đóng cửa vẫn thắng; ô Số ghế có "(+N ẩn)".

### v2.131.0 — Xoá cơ sở CƯỠNG CHẾ (Quản trị): ghế đang chạy xoá theo, mã trống lại, sổ tiền nguyên

Anh Thắng 23/09/2026, sau khi 2.129.0 chối *"còn 2 ghế ĐANG CHẠY (80199, 80200)"*: *"cho phép admin
toàn quyền xoá. Miễn giữ doanh thu là được. Xoá cả mã ghế. Để anh lấy mã đó gán cho ghế đúng."*

- `xoa_han_coso( $id, $that, $cuong_che )` — cưỡng chế bỏ chốt "ghế đang chạy": xoá mọi dòng `may`
  của cơ sở (khoá theo đúng `coso_id`, không quét lạc ghế cùng mã nơi khác), rồi `bc_ma_misa`, rồi
  `coso`. **Điều KHÔNG bỏ: sổ tiền** — bc/bc_dong/thu/chot/nop nguyên vẹn, đúng điều kiện anh đặt
  và đúng luật của `xoa_han_may()` cưỡng chế từ 2.119.0.
- Cổng `coso_xoa` chỉ nhận `cuong_che` khi `la_quan_tri()` — cùng chốt với `may_xoa_han`. Không
  phải admin thì hạ về đường thường (vẫn chối khi còn ghế sống).
- Hộp hỏi kể đúng ghế đang chạy sẽ mất, và nói thẳng hệ quả: **tạo lại đúng mã ấy ở cơ sở khác là
  nó nhặt lại toàn bộ tiền cũ của mã** (mọi bảng nối bằng chuỗi `ma_may`). Với "gán cho ghế đúng" —
  cùng cái ghế, chỉ đổi chỗ — đó là điều anh muốn. Gán mã cho **một ghế khác** thì lịch sử dính nhầm.
- Nhật ký ghi rõ "CƯỠNG CHẾ N ghế đang chạy: mã…".

`kiem-xoa-han-coso.php` lên 25 phép: không cưỡng chế vẫn chối · cưỡng chế xem trước kể đúng ghế sống ·
xoá thật đúng `coso_id` · **không một câu DELETE nào chạm sổ** · cổng kiểm admin.

### v2.130.0 (+ Sao Kê 0.44.0) — Báo cáo tổng, Từng ghế × QR: VietQR thực đối chiếu tới TỪNG MÁY

Anh Thắng 23/09/2026: *"trong VietQR cũng có đánh từng máy 1,2,3,4… trên ghế cũng đánh 1,2,3,4…
hai bên đối chiếu lại máy nào lệch không"*.

**Hôm 22/09 em nói sai**: *"sao kê chỉ quy được tiền về cơ sở, không về từng ghế"*. Thực ra Sao Kê
gán **mỗi giao dịch ra TÊN MÁY** ("AMTP 12", "LM-NSG 01") bằng `cong_may_dong()` rồi mới gộp lên cơ
sở — số máy vẫn còn đó, chỉ là chưa ai nối "AMTP 12" với ghế "AMTP-12".

- **Sao Kê 0.44.0**: `SAOKE_App::vietqr_theo_may_ngay($tu,$den)` — cùng luật gán với
  `vietqr_theo_coso_ngay()`, trả **ba rổ, không đồng nào rơi**: `vq[cơ sở][mã ghế][ngày]` (ra máy),
  `chuaMay[cơ sở][ngày]` (ra cơ sở nhưng không ra máy: QR tĩnh *PaymentForOrder*, tên máy không có
  số, hoặc số trùng hai ghế), `khongKhop` (không ra cả cơ sở).
- Khoá nối `chuan_may()`: `chuan_ch()` rồi **bỏ số 0 dẫn đầu của cụm số cuối** — cổng đánh
  "LM-NSG 01", Ghế khai "LM-NSG-1"; `chuan_ch` ra `lmnsg01` với `lmnsg1`, không khớp dù ai nhìn cũng
  thấy là một máy. Chỉ nhận khi tên máy trỏ **đúng một ghế của đúng cơ sở ấy** — trỏ ghế cơ sở khác
  là dấu hiệu trùng mã liên cơ sở, bỏ vào "chưa rõ máy" chứ không gán chéo.
- **Ghế**: Báo cáo tổng, gộp **Từng ghế** + số liệu **QR** nay có lớp ◆ VietQR đỏ trên từng dòng
  ghế, y như mức cơ sở. `bct_gan_vq_may_()` gắn lớp ấy và chèn cuối mỗi cụm cơ sở hai loại dòng
  lẻ (in nghiêng): **(chưa rõ máy)** và **(không còn trong danh mục)** — để **tổng cột VietQR theo
  ghế bằng đúng tổng theo cơ sở**; lệch là hai bảng nói khác nhau về cùng một khoản tiền.
- CSV "Xuất .csv (VietQR thực)" của 2.127.0 tự ăn theo: chế độ Từng ghế × QR nay cũng ra hai khối.
- Chế độ TỔNG theo ghế **không đổi** (tiền mặt + QR nhân viên khai) — ghi chú nói rõ muốn đối chiếu
  máy thì bấm QR.

Bài kiểm `tools/test/kiem-vietqr-tung-may.php` (18 phép): chạy thật `chuan_may()`,
`vietqr_theo_may_ngay()` (bốc từ Sao Kê, bệ đỡ giả) và `bct_gan_vq_may_()` (bốc từ Ghế) — canh
bất biến *tổng ba rổ = tổng cổng* và *tổng theo ghế = tổng theo cơ sở*.

### v2.129.0 — Xoá hẳn cơ sở (ghế ẩn xoá theo) · cơ sở đóng cửa hết dòng 0đ ở Báo cáo tổng

Anh Thắng 23/09/2026: *"một số cơ sở đã ẩn, nhưng khi xuất misa vẫn nhảy vào, nên anh cần xoá hẳn
điểm đó luôn"* — kèm ảnh ghế **80107 · CGV-PLZ-01 · CGV PEAR PLAZA · "đã ẩn"**.

**Gốc, hai lớp.**
1. Nút 🗑 cơ sở gọi `xoa_coso()`, mà hàm ấy đếm `COUNT(*) WHERE coso_id` — đếm **cả ghế đã ẩn**.
   CGV PEAR PLAZA còn đúng một ghế ẩn nên bị chối "còn 1 ghế". Ghế ấy thì từ 2.115.0 không ẩn/xoá
   mềm được nữa, xoá hẳn ghế lại nằm ở màn khác. Anh đi vòng ba màn không ra.
2. Cơ sở "đã ẩn" vẫn nằm trong danh mục → **Báo cáo tổng in nó thành một dòng 0đ** (luật "không
   thu được đồng nào vẫn nằm nguyên một dòng"). Đó là cái "vẫn nhảy vào".

**Làm gì.**
- `VHG_May::xoa_han_coso( $id, $that )` — **nới đúng một bậc, y như `xoa_han_may()` đã nới**: ghế
  **đã ẩn** không chặn nữa, xoá hẳn theo cùng cơ sở. Ghế **đang chạy** vẫn chặn tuyệt đối — xoá cơ
  sở còn ghế sống là cả loạt rơi khỏi màn nhập (vụ "cả loạt VHM biến mất" 12/09). Phải Đổi cơ sở
  trước.
- **Chỉ xoá danh mục, không xoá sổ**: dòng `coso` + dòng `bc_ma_misa` + ghế ẩn ở `may`. Báo cáo
  (`bc`/`bc_dong`), thu, chốt, nộp nối bằng tên/`ma_may` nên còn nguyên — tổng tiền các tháng đã
  chốt **không đổi**. Xuất MISA cho một tháng cơ sở này còn doanh thu **vẫn ra dòng của nó**: đó là
  tiền thật, phải ra. Xoá xong nó chỉ hết nằm trong danh mục.
- Nút 🗑 nay **xem trước rồi mới hỏi**: hộp hỏi kể đúng ghế ẩn sẽ mất, số báo cáo cũ giữ nguyên, và
  dặn **đừng tạo lại cơ sở trùng tên** (báo cáo cũ sẽ ghép lại vào nó qua `squash(tên)`). Câu cũ
  *"ghế thành chưa gán, KHÔNG bị xoá"* đã sai từ 12/09 — gỡ.
- **Báo cáo tổng**: cơ sở đã bấm 🚪 đóng cửa mà kỳ này **không có đồng nào** → không in dòng 0đ.
  Đóng cửa mà kỳ này **có tiền** (thu nốt trước khi dọn) → **vẫn hiện**. Chốt là "có tiền trong
  khoảng", không phải cờ đóng cửa — không bảng nào được giấu tiền thật.

`xoa_coso()` cũ giữ lại cho wp-admin và cho `gop_coso()` (gộp cơ sở), không đụng.

Bài kiểm `tools/test/kiem-xoa-han-coso.php` (17 phép) — bốc thẳng hàm ra chạy rồi soi **câu DELETE
nào được bắn và câu nào KHÔNG được bắn**; dò chuỗi không nói được "có chạm sổ không".

### v2.128.0 — "Ai đang cầm tiền": bấm vào tên là bung từng ngày, tích ngày nào chốt ngày ấy

Anh Thắng 22/09/2026: *"bấm vào nhân viên sẽ hiện… hiện ngày chưa nộp, nhân viên nộp ngày nào
mình tích vào"*, và nói rõ vì sao: *"nộp báo cáo mà chưa chốt, xong qua nộp tiền không ngày hôm
trước — kế toán không thể chốt một cục được"*.

**Gốc.** Ngày làm báo cáo và ngày tiền về tay là **hai việc khác nhau**. Nhân viên nộp báo cáo mỗi
ngày, còn tiền mặt về quầy theo nhịp riêng — hôm nay mang tiền của ba hôm trước, mai mang nốt.
Bảng này xưa nay chỉ có **một con số tổng của cả người**, nên nút duy nhất là chốt cả cục: kế toán
nhận tiền của ba ngày mà phải ghi hết nợ của mười ngày, hoặc không ghi gì. **Cả hai đều sai sổ.**

- Bấm vào **tên người** → bung ra bảng **từng ngày chưa nộp**: ngày · cơ sở của ngày ấy · ngăn ghế
  · quầy · báo cáo · tổng, mới nhất lên đầu. Có ô tích từng dòng và ô tích tất.
- Nút đổi theo lựa chọn: **"✓ Xác nhận đã nộp N ngày · X đ"**. Hộp hỏi lại **kể đúng từng ngày và
  số tiền** — đây là ghi hết nợ ngay, không có bước hoàn tác dễ dàng, nên người bấm phải đọc lại
  đúng thứ mình sắp ghi. Nút cũ đổi tên thành **"Xác nhận CẢ CỤC"** cho khỏi bấm nhầm.
- Tải khi mở, không tải sẵn: bảng có mấy chục người, tải sẵn hết là mở màn Quỹ phải chờ mấy chục
  lượt cho một thứ có thể không ai bấm tới.

**Ba nguồn tiền, ba cột ngày** — `VHG_Quy::dang_cam_theo_ngay()` quy về cùng một ngày dương lịch:
`bc.ngay` (ngày làm ăn, thứ kế toán nghĩ theo) · `DATE(chot.tao_luc)` · `DATE(thu.luc)`. Tiền không
có ngày (dữ liệu cũ nhập lại) vào nhóm **`(chưa rõ ngày)`** — không đồng nào được rơi mất, vì tổng
các ngày phải bằng đúng tổng đang cầm; lệch một đồng là người ta thôi tin cả cái bảng.

🔴 **Chỗ dễ hỏng nhất là câu lọc.** `nop()` nay nhận thêm `$ngay_ds`, lọc bằng **chính cột mà bảng
đọc đã gom theo** — lệch một cột là tích một ngày rồi gắn phải dòng của ngày khác, âm thầm. Và
**câu lọc không bao giờ được rỗng khi đã tích**: rỗng là lặng lẽ thành *nộp tất*, đúng thứ vừa cố
tránh, mà màn hình vẫn báo thành công. Tích toàn ngày không đọc được → chặn hết (`1=0`), lượt nộp
tự huỷ và người bấm nhận đúng câu "không có đồng nào ở ngày đã tích".

Ngày đã chốt đi thẳng vào ghi chú của lượt nộp — ba tháng sau nhìn lại sổ, "xác nhận thay" mà
không nói xác nhận cho ngày nào thì không tra ngược được.

Bài kiểm `tools/test/kiem-cam-tien-theo-ngay.php` (17 phép) — **bốc thẳng `nop()` và
`dang_cam_theo_ngay()` ra chạy** với `$wpdb` giả rồi soi **chính câu SQL nó bắn đi**. Dò chuỗi
không nói được "câu UPDATE có kèm vế ngày không".

### v2.127.0 — Nút "Xuất .csv" của Báo cáo tổng nay ra số VietQR thực

Anh Thắng 22/09/2026, sau 2.126.0: *"chưa được"* — kèm chính tệp vừa tải: cả bảng chỉ có số đen,
số đỏ mất sạch.

**Em sửa nhầm chỗ ở 2.126.0.** Câu *"chỗ xuất QR lấy theo số thực"* là nói nút **Xuất .csv** của
màn Báo cáo tổng — màn anh đang mở trong ảnh — chứ không phải chứng từ MISA. (Bản 2.126.0 vẫn
đúng và vẫn cần: chứng từ MISA cũng phải lấy số thực. Chỉ là nó không phải chỗ anh đang đứng.)

**Gốc.** Màn hình xếp **hai lớp chồng nhau** trong một ô: đen = số nhân viên đọc trên máy, đỏ =
tiền thật về ngân hàng (sao kê). Tệp CSV chỉ có một lớp, mà bản cũ lấy đúng lớp **đen**. Tức
người ta mở màn hình ra để nhìn số đỏ, bấm Xuất, rồi nhận về đúng thứ mình không định lấy — và
trong tệp không có gì nói nó là lớp nào.

⚠️ **Loại hỏng không kêu tiếng nào.** Tệp tải về đủ dòng, đủ cột, số nào cũng là số thật — chỉ là
thật của lớp kia. Không lỗi, không ô trống. Chỉ người ngồi đối chiếu với sao kê mới phát hiện, mà
lúc ấy đã dán vào sổ rồi.

- Chế độ **QR** + có sao kê: tệp ra **số VietQR thực**, cả ô từng ngày lẫn cột Tổng lẫn dòng TỔNG.
- **Lớp nhân viên nhập vẫn còn**, thành **khối thứ hai** bên dưới, dán nhãn *"chỉ để đối chiếu —
  KHÔNG phải tiền về ngân hàng"*. Hai lớp ấy tồn tại là để đối chiếu; bỏ hẳn một lớp thì tệp hết
  đường tra vì sao lệch. Hai khối chồng nhau là cách Báo cáo ngày (VND / SGD) vẫn làm.
- Mỗi khối có **một dòng nói rõ nó là tiền gì**. Hai bảng số giống hệt nhau nằm cạnh nhau mà không
  dán nhãn thì sớm muộn có người cộng nhầm khối.
- Tên tệp gắn đuôi `_vietqr-thuc`; nút đổi chữ thành **"Xuất .csv (VietQR thực)"** khi đang ở chế
  độ ấy — nói trước tệp sắp tải là tiền gì, khỏi bấm như canh bạc.
- Các chế độ khác (Tổng · Tiền mặt · gộp Từng ghế · chưa đọc được sao kê) **giữ nguyên một khối**
  như cũ. Mức Từng ghế vốn không có lớp VietQR: sao kê chỉ quy được tiền về cơ sở.

Bài kiểm `tools/test/kiem-bct-xuat-vietqr.js` (18 phép) — **chạy thật hàm dựng tệp** rồi đọc lại
từng ô của CSV. Dò chuỗi trong mã nguồn không nói được "ô ngày 11/09 của AEON có đúng bằng số đỏ
không".

### v2.126.0 — Chứng từ MISA: QR là tiền THỰC về ngân hàng

Anh Thắng 22/09/2026, ảnh màn Báo cáo tổng: *"chỗ xuất QR lấy theo số thực tức QR số màu đỏ"*.

Cùng luật anh đã chốt 18/09 cho cột TỔNG: *"QR là QR thực về ngân hàng, còn con số QR nhân viên
nhập chỉ là đối chiếu thôi"*. `bc_dong.qr` là số nhân viên **đọc trên máy** — nó lệch thật, và
ngay trong ảnh anh gửi có cơ sở lệch cả chục triệu (**AEON MALL TÂN PHÚ**: bảng 35.570.000 mà
VietQR thực 59.800.000). Đưa số đọc-trên-máy vào sổ kế toán là ghi doanh thu theo một con số
**không ai chuyển tiền theo**.

**Chỗ khó: tiền thực chỉ biết theo CƠ SỞ × NGÀY.** Sao kê ngân hàng không tách nổi ghế, mà chứng
từ thì mỗi ghế một dòng. Nên phải **chia** số thực xuống các ghế của cơ sở-ngày ấy, theo tỉ lệ
chính số QR nhân viên nhập — chỗ duy nhất biết ghế nào chạy nhiều hơn ghế nào.

- Hàm chia mới `chia_ty_le_()`: chia sàn rồi **rải từng đồng phần dư** cho ghế trọng số lớn
  trước. `round()` từng phần rồi cộng lại gần như luôn lệch vài đồng so với số tổng — mà đây là
  tiền đã về ngân hàng, **tổng phải khớp tuyệt đối**, nếu không sổ MISA lệch sao kê đúng bằng cái
  vài đồng ấy, mỗi ngày một ít. Ổn định: xuất lại cùng một ngày ra y nguyên một bảng.
- Cả cơ sở-ngày nhập 0 QR mà ngân hàng vẫn có tiền → chia theo doanh thu ghế; doanh thu cũng 0 →
  chia đều. Thà đều còn hơn dồn hết vào một ghế ngẫu nhiên.
- Chia ra 0đ cho một ghế thì **bỏ hẳn dòng** — ngân hàng không nhận đồng nào cho ghế ấy, viết một
  dòng 0đ vào sổ là thêm rác.

🔴 **Không có sao kê thì GIỮ số cũ, và kêu lên.** Cơ sở-ngày nào sao kê chưa về (hoặc chưa cài Sao
Kê) mà lấy 0 cho nó là **xoá trắng doanh thu QR của ngày ấy khỏi sổ** — im lặng, mà tệp vẫn tải về
bình thường. Nên: giữ số nhân viên nhập, và màn xuất in dòng đỏ kể đúng cơ sở-ngày nào. Xuất theo
từng ngày thì hỏi lại trước khi tải.

- Ô chọn **"QR = số THỰC về ngân hàng"** mặc định **bật**; vẫn tắt được cho ngày nào sao kê chưa
  về mà phải xuất gấp. Khối "Xuất theo NGÀY" dùng chung ô này.
- Sau khi tải, màn xuất in luôn số đối chiếu: ngân hàng bao nhiêu so với nhân viên nhập bao nhiêu,
  lệch bao nhiêu.

Bài kiểm `tools/test/kiem-xuat-misa-tinh.php` lên **32 phép** — chạy thật phép chia (tổng khớp
tuyệt đối, ổn định, trọng số 0, số lẻ) và chạy thật cả hàm xuất với sao kê giả.

### v2.125.0 — Mã đối tượng lấy thẳng `coso.ma_kh`, gỡ ô khai trùng của 2.124.0

Anh Thắng 22/09/2026, sau khi xuất thử 2.124.0: *"xuất misa chưa thấy mã đối tượng"*, rồi chỉ
thẳng thẻ cơ sở ở màn Địa điểm: *"chính là mã khách hàng"* — **AEON MALL BÌNH DƯƠNG · 🏷 KH00108**.

**Em làm sai ở 2.124.0.** Cột `coso.ma_kh` đã có từ **v1.99.8** (01/09/2026, cũng do anh Thắng
yêu cầu), khai ở màn Địa điểm, kế toán đang dùng để đối chiếu với sổ ngoài. 2.124.0 lại dựng thêm
ô "Mã đối tượng" ở bảng Unit ID — tức **hai chỗ gõ cùng một con số**. Hậu quả tức thì: xuất ra vẫn
trắng, vì ô mới chưa ai gõ. Hậu quả lâu dài còn tệ hơn: một ngày hai con số lệch nhau và **không ô
nào tự nhận mình sai**.

- `misa_chungtu()` đọc thẳng `coso.ma_kh`, ghép qua `squash()` — `bc.coso_key` là tên đã bóc
  dấu/hoa-thường lúc nộp, `coso.ten` là tên đang hiển thị; ghép thẳng hai chuỗi tên là hụt ngay
  khi ai đó sửa hoa-thường hay khoảng trắng.
- *Tên đối tượng nợ* = tên cơ sở. Đây là **nhãn cho người đọc**, không phải khoá ghép, nên không
  cần ô khai riêng.
- **Gỡ sạch** `bc_ma_misa.doi_tuong` / `doi_tuong_ten` của 2.124.0: cột, migration, cổng lưu, và
  hai ô nhập cùng nút "Chép Unit ID → Mã đối tượng".
- Bảng **Unit ID MISA** nay có cột **Mã KH chỉ để XEM** (đậm nếu có, đỏ "chưa khai" nếu không) —
  để kế toán đứng ngay chỗ sắp bấm Xuất là thấy cơ sở nào còn thiếu, chứ không phải để gõ lần
  thứ hai. Sửa thì sang màn Địa điểm, một nơi duy nhất. Dòng cảnh báo lúc xuất cũng chỉ về đó.

Bài kiểm `tools/test/kiem-xuat-misa-tinh.php` lên **19 phép**, thêm hai phép canh chính cái bẫy
này: nguồn phải là `coso.ma_kh`, và **không được có ô khai thứ hai** ở bất kỳ tệp nào. Dữ liệu giả
dùng tên có dấu ("Gò Cần Thơ") để chạm đúng chỗ ghép `squash()`.

### v2.124.0 — Xuất MISA: thứ tự Unit · tách theo tỉnh · mã đối tượng (khách hàng)

Anh Thắng 22/09/2026, ba câu kèm hai ảnh (bảng DAILY REPORT đang dựng tay, và tệp chứng từ đã
xuất): *"sắp xếp Unit theo thứ tự để xuất misa"*, *"xuất rõ phân theo tỉnh"*, *"xuất kèm mã đối
tượng (chính là mã khách hàng)"*.

> ⚠️ **Phần 1 dưới đây đã bị 2.125.0 sửa lại** — hai ô khai thêm ở bảng Unit ID là sai, nguồn
> đúng là `coso.ma_kh` của màn Địa điểm. Giữ lại để hiểu vì sao có bản 2.125.0.

#### 1. Mã đối tượng Nợ = mã khách hàng

**Đây là chỗ nặng nhất trong ba việc.** Bút toán đang ghi **Nợ TK 131 — phải thu KHÁCH HÀNG**, mà
cột *Mã đối tượng Nợ* thì trắng cả bảng. Một khoản phải thu không có đối tượng thì MISA **không
dựng được sổ công nợ**: tổng doanh thu vẫn đúng, nhưng "ai còn nợ bao nhiêu" thì không có. Tệp vẫn
tải về, vẫn trông như xong — đúng loại hỏng không kêu tiếng nào.

- `bc_ma_misa` thêm hai cột `doi_tuong` + `doi_tuong_ten`, khai ngay ở bảng **Unit ID MISA**.
- Chứng từ điền cột *Mã đối tượng Nợ* và *Tên đối tượng nợ* theo **`coso_key`** chứ không theo
  tên: tên cơ sở trong `bc` là tên đã đóng băng lúc nộp, đổi tên cơ sở một lần là chứng từ cũ hụt
  mã.
- ⚠️ **Hệ KHÔNG tự lấy Unit ID làm mã đối tượng.** Unit ID là *mã đơn vị* (đơn vị của mình), mã
  đối tượng là *khách hàng* — hai danh mục riêng bên MISA, có thể trùng mà cũng có thể không. Đoán
  một mã khách hàng là đẩy công nợ sang nhầm người, âm thầm. Thiếu thì **để trắng và kêu lên**
  (dòng đỏ kể tên cơ sở ngay dưới nút Tải) — MISA báo thiếu còn sửa được, sai đối tượng thì phải
  dò ngược cả tháng. Ai dùng chung một bộ mã thì có nút **⤵ Chép Unit ID → Mã đối tượng**, chỉ
  chép vào **dòng đang trắng**, không đè mã đã khai.
- *Mã đơn vị* / *Tên đơn vị* giữ nguyên là mã & tên **ghế** như cũ — không đụng.

#### 2. Thứ tự Unit + tách theo tỉnh (Báo cáo ngày)

**Số đầu Unit ID chính là tỉnh**: 58GOTV · 59SCCT · 60GODL · 61ZCKG · 62SCCM. Nên thứ tự anh dựng
tay không phải "xếp theo bảng chữ cái" mà xếp theo con số ấy — trong ảnh, **CAN THO (59) đứng
trước CA MAU (62)**, còn xếp theo tên thì "CA MAU" phải lên trước. Đó là lý do luật cũ (xếp theo
`vung` dạng chữ) ra sai thứ tự.

🔴 **Luật cũ còn một lỗi câm:** chốt cuối viết `strcmp($a['unit_id'], $a['unit_id'])` — so `$a` với
**chính nó**, nên luôn trả 0. Hai cơ sở ngang hàng xếp ngẫu nhiên theo cách MySQL trả hàng, tháng
này một kiểu tháng sau một kiểu. Bảng vẫn đủ số nên không ai thấy; chỉ người dán vào tệp tháng
trước mới thấy hàng không còn khớp.

- Thứ tự mới: **số đầu Unit ID → ô "TT" nếu có ghim tay → phần chữ A→Z → tên cơ sở**. Thiếu Unit
  ID dồn cuối bảng.
- Mỗi tỉnh có **một dòng mở** (tên tỉnh ở cột Unit ID, các cột số để trắng — khớp đúng bảng anh
  đang dựng, dán vào là nằm đúng chỗ) và **một dòng CỘNG** đóng nhóm.
- **Tên tỉnh lấy từ ô "Vùng" đầu tiên có chữ trong nhóm** — khai một cơ sở là cả tỉnh có tên, và
  không bao giờ có chuyện hai cơ sở cùng số in ra hai tên tỉnh khác nhau. Chưa khai thì in
  `Nhóm <số>` (đúng chỗ, đúng thứ tự, chỉ thiếu cái tên) và báo ra ở dòng kết quả.

Bài kiểm `tools/test/kiem-xuat-misa-tinh.php` (18 phép) — **bốc thẳng hai hàm xuất từ mã nguồn ra
chạy** với `$wpdb` giả, không chép lại logic, không dò chuỗi: dò chuỗi không nói được "dòng CỘNG có
đúng bằng tổng các dòng trên nó không".

### v2.123.0 — Rê chuột xem to ảnh: hết bị khung bảng cắt

Anh Thắng 21/09/2026, ảnh màn Duyệt báo cáo SENSE CITY PHẠM VĂN ĐỒNG: rê vào ảnh của ghế
SC-PVD-2 thì ảnh to ra rồi **đứt ngang ở mép dưới khung bảng**, chỉ còn nhìn được một dải trên
cùng. Số trên đồng hồ nằm giữa tấm ảnh, nên thấy mỗi dải ấy là soát không được gì — vẫn phải mở
tab mới cho từng tấm, đúng việc mà cái phóng to này sinh ra để bỏ.

**Gốc.** Bảng ghế nằm trong `.table-scroll`. Khai `overflow-x:auto` thì trình duyệt **tự** nâng
`overflow-y` từ `visible` lên `auto` — luật CSS, không phải chỗ ấy khai thiếu. Tức khung đó cắt
**cả hai chiều**. Cách phóng to cũ là `transform:scale(6)` trên chính thẻ `<img>` **nằm trong**
bảng, nên nó mãi là con của khung cắt: phóng bao nhiêu cũng chỉ thấy phần lọt trong khung, và
dòng cuối bảng thì gần như không thấy gì.

⚠️ **Không chữa được bằng `z-index`.** z-index xếp thứ tự chồng lớp, nó không gỡ được việc bị
cắt — `overflow` của tổ tiên luôn thắng. Đường duy nhất là đưa ảnh xem **ra ngoài** khung ấy.

- Thay bằng **một thẻ nổi `#kt-anh-xem` gắn thẳng vào `<body>`**, `position:fixed` — không tổ
  tiên nào cắt được nữa. Một thẻ dùng chung cả trang, không dựng lại mỗi lần rê.
- **Ép nằm trọn trong màn**: ưu tiên hiện bên phải thumbnail, sát mép phải thì lật sang trái;
  dọc căn giữa thumbnail rồi kẹp vào hai mép. `fixed` mà không kẹp mép thì chỉ đổi chỗ bị cắt.
- **Ảnh xem lấy bản to** (Drive `sz=w1200`) chứ không phóng lại chính thumbnail `w200` — kéo
  w200 lên nửa màn thì nhoè, mà nhoè thì vẫn phải mở tab mới.
- `pointer-events:none`: lớp nổi hiện ngay cạnh con trỏ, nếu nó ăn chuột thì chuột coi như rời
  thumbnail → lớp tắt → chuột lại về thumbnail → lớp hiện… thành nhấp nháy.
- Cuộn là tắt, và nghe ở **giai đoạn bắt** (`true`) — cuộn bên trong khung bảng không nổi bọt
  lên `document`, nghe kiểu thường sẽ bỏ sót đúng cái khung đang chứa ảnh.
- Thumbnail trong **ô sửa của kế toán** (2.122.0) nay cũng rê chuột xem to được. Đang sửa ảnh mà
  muốn biết tấm nào là tấm nào lại phải mở tab mới thì mất luôn ô sửa đang gõ dở.

Bài kiểm `tools/test/kiem-anh-xem-to.js` (19 phép) — **chạy thật hàm đặt toạ độ** trên một DOM
giả, không chỉ dò chữ: "không bị cắt" là phát biểu về con số toạ độ, nên bài canh thẻ nổi nằm
trọn trong màn ở năm vị trí thumbnail, kể cả sát đáy và góc dưới-phải.

### v2.122.0 — Ô sửa của kế toán nay thêm / bỏ được ảnh

Anh Thắng 20/09/2026: *"chỗ sửa này đang không thấy sửa, thêm ảnh, sửa ảnh."*

**Gốc.** Chính tab Duyệt báo cáo in dòng cảnh báo *"6 ghế thiếu ảnh"*, mà ô sửa mở ra lại chỉ có
mấy ô số. Hệ nói ra một việc phải làm rồi bịt luôn đường làm việc ấy: muốn bù ảnh phải quay về màn
Sửa 24h của nhân viên — nơi đã quá hạn từ lâu với đúng những báo cáo đang bị nhắc.

- Ô sửa (tab Duyệt báo cáo) nay có khối ảnh: **ảnh đang có** hiện thành thumbnail, mỗi ảnh một nút
  ✕ để đánh dấu bỏ — bấm lại là **hoàn tác ngay tại chỗ**, chưa Lưu thì chưa đụng gì tới dữ liệu.
- Ba nút thêm: **+ Chỉ số · + Vệ sinh · + QR**, đúng ba loại ảnh nhân viên chụp.
- Máy chủ `VHG_KeToan::sua()` nhận `patch.images` (ba dataUrl) và `patch.anhXoa` (danh sách URL bỏ
  đi), và **chỉ ghi lại cột `anh` khi thật sự có đụng tới** — sửa một ô số không được phép quét
  sạch chứng từ.

**Ba luật giữ cho nó không thành đường rẽ thứ hai:**

1. **Một đường lưu ảnh duy nhất.** Kế toán đi qua `VHG_BaoCao::luu_anh()` (mở `public` từ bản này)
   chứ không dựng bản sao — hai chỗ tự đặt tên tệp / chọn thư mục là hai màn rồi sẽ nằm hai nơi.
2. **Xoá ảnh phải hoàn tác được.** Cột `anh` cũ nay nằm trong ảnh chụp `bc_undo` cùng các ô số.
   Mất một ô số còn gõ lại được; mất chứng từ thì không.
3. **Một bộ luật nén ảnh duy nhất.** Khối JS kế toán dùng lại `window.VHG_NEN_ANH` của khối nhân
   viên, không chép cạnh dài / chất lượng sang bản thứ hai. Thiếu nó thì vẫn đính được ảnh (đọc
   thô bằng `FileReader`) chứ không câm lặng bỏ qua.

Bài kiểm `tools/test/kiem-kt-sua-anh.js` (12 phép).

### v2.121.0 — Tách quyền "Sửa báo cáo đã nộp" khỏi "Chốt doanh số"

Anh Thắng 20/09/2026, sau khi hỏi lại luật 24h: *"hoặc anh sẽ thiết lập thêm 1 tài khoản để thực
hiện quyền đó"*.

**Luật đang chạy (đã kiểm lại trong code):**

- Hằng `GIO_SUA = 24` chỉ áp cho **đường PIN của nhân viên** — danh sách 24h và hai đường lưu khi
  nhân viên sửa. Quá hạn: *"Báo cáo đã quá 24 giờ nên khoá. Nhờ kế toán."*
- `VHG_KeToan::sua()` (tab Duyệt báo cáo) **không kiểm hạn giờ**. Chốt duy nhất: ngày đó đang
  *Khoá ngày* thì phải Bỏ khoá trước. Mỗi lần sửa lưu giá trị cũ kèm **tên người sửa** (`$boi`).

**Tách quyền.** Nhận tiền và sửa sổ khác hẳn nhau về hậu quả: nhận tiền sai thì đếm lại ra ngay;
sửa sổ sai (hay cố ý) thì không còn gì để đối chiếu ngược — mà đường này đổi được cả tháng đã chốt.

- `VHG_Auth::vai_tro_sua_bc()` + `duoc_sua_bc()`, cờ `sua_bc` trong `quyen_cua()` (một nguồn cho cả
  cổng chặn lẫn giao diện).
- Router chặn `kt_sua` khi thiếu cờ, **trước** khi gọi `VHG_KeToan::sua`. Cổng chung cho mọi việc
  `kt_*` (phải Quản trị hoặc Chốt doanh số) giữ nguyên.
- Khai được ở **Cấu hình › Phân quyền**, ô thứ tư: *"Sửa báo cáo đã nộp (không giới hạn 24 giờ)"*.
- ⚠️ **Chưa khai bao giờ = lấy y danh sách "Chốt doanh số"**, tức hệ đang chạy không đổi gì. Một
  bản vá phân quyền không được tự thu hẹp quyền người đang dùng.

Bài kiểm `tools/test/kiem-quyen-sua-bc.js` (16 phép).

### v2.120.0 — Nút 🗑 ở Quản lý ghế nay là XOÁ HẲN

Anh Thắng 19/09/2026: *"cho phép xoá hẳn"* — bấm 🗑 chỉ nhận được lời từ chối *"Ẩn / điều chuyển
ghế đã khoá từ bản 2.115.0"*.

Đúng vậy: 2.115 khoá ẩn ghế, mà nút 🗑 xưa nay chính là **ẩn mềm** (`may_xoa` → `an=1`), nên nó rơi
vào cửa đã khoá. Hệ quả là màn Quản lý ghế **không còn đường nào dọn mã rác**, trong khi Vạn Hạnh
Mall đang có 18 mã rác.

- Nút 🗑 (cả bảng chính lẫn khối "Ghế đã ẩn") nay đi thẳng `may_xoa_han`: **xem trước** để biết mã
  ấy còn dữ liệu không, rồi mới hỏi, rồi mới xoá.
- Mã còn dữ liệu → nói thẳng lý do và nhắc: nếu đây là **mã cũ của một ghế đang chạy** thì phải
  **gộp** (đổi mã) vào ghế ấy trước; xoá thẳng là bỏ lại mấy dòng tiền không tra ra ghế nào.
- Sửa lại tooltip và lời hướng dẫn cuối tab — chúng vẫn đang nói "Xoá = ẩn mềm, không có đường nào
  làm mất hẳn một ghế", tức là mô tả một hệ đã không còn đúng.


### v2.119.0 — Chặn luật lịch sử sinh rác, và cho xoá hẳn mã rác

Anh Thắng 19/09/2026: *"lại tiếp tục sinh rác"*, *"dữ liệu tiền bạc mà cứ sinh rác"*, *"cho xoá
hẳn được không"*.

**Rác là gì.** Vạn Hạnh Mall có **18 mã trong khối "Ghế đã ẩn"**, phần lớn là **mã cũ của chính
những ghế đang chạy**: VHM-1 (801351 sống / 80135 ẩn), VHM-11 (80145 / 80192), VHM-12 (80146 /
80193). Ai đó tạo mã mới thay vì dùng "đổi mã", nên lịch sử kẹt ở mã cũ. Luật cứu-theo-lịch-sử của
2.115 lôi hết chúng ra → màn nhập hiện **từng cặp trùng tên**. Nhân viên không biết điền hàng nào;
điền cả hai là **doanh thu đếm đôi**.

Hai tầng chặn ở `ds_ghe()`:

1. **Không dựng dậy ghế đang nằm trong khối "đã ẩn".** Cờ ẩn là ý định tường minh của quản trị;
   luật lịch sử không được cãi lại nó. Ca GO-TDM vẫn được cứu vì mấy ghế ấy KHÔNG bị ẩn — chúng rơi
   khỏi cơ sở, chuyện khác hẳn. (Từ 2.115 không ai bật thêm cờ ẩn được nữa nên danh sách chỉ teo.)
2. **Không dựng dậy mã cũ khi cơ sở đã có ghế SỐNG cùng TÊN.** Lưới thứ hai cho mã trùng tên mà
   không nằm trong khối ẩn. So theo TÊN chứ không theo mã — mã chính là thứ đã đổi.

**Xoá hẳn:** `xoa_han_may()` trước chỉ chịu xoá mã **chưa gán**, mà mã rác thì vẫn dính cơ sở nên
không có đường dọn. Nay nới đúng một bậc: **ghế đang ẩn cũng xoá hẳn được**. Không nới cho ghế đang
chạy. Chốt dữ liệu giữ nguyên: **còn một dòng dữ liệu là không xoá** — mã rác có tiền thì phải GỘP
vào ghế đang chạy trước (đổi mã), xoá thẳng là bỏ lại mấy dòng tiền không tra ra ghế nào.


### v2.118.0 — Rà cả hệ: mọi chỗ đọc chỉ số đều phải chốt cơ sở

Anh Thắng 19/09/2026, sau khi 2.117 chữa xong màn nhập: *"đã đúng cho cơ sở đó, check lại xem tất
cả hệ thống có đang lấy nhầm không"*.

Rà hết các truy vấn tra theo `ma_may`. Ba chỗ nữa cùng bệnh, đều đụng tiền:

1. **`ap_moc_()`** — chỗ NGUY NHẤT. Nó **ghi đè `chi_so_truoc` của hàng đã chốt** mỗi khi có
   chèn/sửa/xoá/đổi ngày ở ngày trước. Lấy mốc theo mã ghế trên toàn hệ nghĩa là với mã trùng, nó
   tự tay sửa sai tiền của một báo cáo đang đúng ở cơ sở khác. Nay **tự suy cơ sở** từ báo cáo của
   chính hàng ấy — không bắt người gọi truyền, vì hàm này được gọi từ bảy chỗ (luu, sua_dong,
   duyệt, xoá, đổi ngày…), sót một chỗ là sót âm thầm.
2. **`chi_so_ke_ct_()`** — cái "trần" cho ngày đang nhập. Trần lấy nhầm thì hoặc chặn oan một số
   đúng, hoặc thả lọt một số sai. Nay cùng luật ưu tiên cơ sở; router `bc_lastmeters` truyền xuống.
3. **`noi_tiep()` / `noi_hang()`** — tìm hàng kế tiếp để nối lại mốc. Thêm `loc_coso_()`; `luu()` và
   `sua_dong()` truyền cơ sở của chính báo cáo.

**Còn lại — CHƯA sửa, và nói rõ vì sao.** Mấy bảng này **không có cột cơ sở** nên không chốt được
nếu không có bản đồ mã→cơ sở, mà chính bản đồ ấy cũng nhập nhằng khi mã trùng:

| Chỗ | Bảng | Ảnh hưởng |
|---|---|---|
| `kich_xa_tru()` | `lenh` | lượt kích ghế từ xa bị trừ vào actual — trừ nhầm của ghế trùng mã |
| `VHG_KeToan` (~1170) | `thu` | tiền QR theo ghế/ngày |
| `bao_tri()` | `bao_tri` | báo lỗi có thể cập nhật dòng của cơ sở khác |
| đếm bật/tắt máy | `bat_tat` | chỉ là thống kê |
| `vietqr_thuc_()` | bản đồ mã→cơ sở | mã trùng thì một cơ sở "ăn" hết tiền QR |

**Cách chữa dứt điểm cho nhóm này là bỏ mã trùng**, không phải vá thêm: đổi mã một bên bằng *Nhân
bản ghế* (chép sẵn mốc chỉ số nên không nhảy về 0).


### v2.117.0 — Chỉ số trước phải của ĐÚNG ghế ở ĐÚNG cơ sở

Anh Thắng 19/09/2026: *"dữ liệu ghế này lại đi đọc dữ liệu của ghế khác là sao"*.

GO Thủ Dầu Một, màn nhập: **GO-TDM-1 (80016)** ra chỉ số trước **7.868 ngày 17/09**, **GO-TDM-2
(80017)** ra **9.119** — trong khi báo cáo 09/09 của chính cơ sở ấy ghi 25.166 và 28.295 (khớp
bảng tay của nhân viên: 25.166→25.283, 28.295→28.481). TDM-3/TDM-4 thì đúng.

**Gốc:** `chi_so_truoc_ct_()` tra theo **mỗi `ma_may`**, không đếm xỉa cơ sở. Mã ghế đã từng dùng
trùng ở nơi khác (vụ 12/09 *"2 cơ sở chung 1 mã"*), nên chỉ số của ghế nơi khác nhảy vào làm mốc.
Lấy nhầm mốc thì `actual = (sau − trước)` ra một con số tiền hoàn toàn bịa — sai mốc là sai tiền,
không phải sai hiển thị.

- Thêm tham số `$coso`: **ưu tiên** lịch sử của chính cơ sở ấy (JOIN `bc`, lọc `coso_key`).
- **Ưu tiên chứ không lọc cứng**: ghế điều chuyển thật sang cơ sở mới chưa có lịch sử ở đó thì vẫn
  lùi về tra toàn hệ, để nối tiếp chỉ số cũ của nó.
- Bắt được mốc cùng cơ sở thì **không cho bảng `chot`** (chốt ca quét QR, không có cột cơ sở) đè
  lên — chính nó cũng có thể là của ghế trùng mã nơi khác.
- Chuyền `$coso` xuống: `bc_lastmeters` (màn nhập gửi kèm cơ sở đang chọn), `luu()` lúc gửi báo
  cáo, và hai chỗ mới của 2.116 (`ds_24h`, `sua_dong`).


### v2.116.0 — Sửa 24h bày CẢ ghế chưa nhập, và lưu được luôn

Anh Thắng 19/09/2026: *"trả lại để nhập lại, hoặc bấm sửa thì nó phải có cả ghế chưa nhập chứ"*.

`ds_24h()` lọc hàng `chi_so_sau IS NOT NULL OR tong<>0 OR actual<>0`, nên báo cáo nộp thiếu ghế thì
mở Sửa 24h ra **vẫn thiếu đúng chỗ ấy** — đúng lúc người ta mở để bổ sung thì màn hình giấu mất
phần cần bổ sung. GO Thủ Dầu Một 19/09 nộp 1 ghế / 4 ghế: mở Sửa vẫn 1 ghế, không có đường nhập nốt.

- `ds_24h()` ghép thêm ghế của cơ sở chưa có dòng, để trống, cờ `chuaNhap`, kèm `chi_so_truoc` tính
  sẵn. Danh sách lấy từ `ds_ghe()` nên đã gồm ghế cứu theo lịch sử (2.115) — ghế rơi khỏi danh mục
  vẫn nhập được.
- `sua_dong()` trước đây chối "Không thấy dòng cần sửa" nếu ghế chưa có dòng — nay **tạo dòng** rồi
  sửa, vẫn chốt phạm vi PIN và đúng cơ sở của báo cáo (không thành đường chèn dòng vào báo cáo cơ
  sở khác).
- Màn Sửa 24h gắn nhãn cam **CHƯA NHẬP** để không lẫn với hàng đã nhập rồi bị xoá số.


### v2.115.0 — Màn nhập dựng theo LỊCH SỬ THU TIỀN · khoá tính năng ẩn ghế

Anh Thắng 19/09/2026: *"nguyên tắc đi từ đầu đến cuối dữ liệu"*, *"hôm trước có, nay phải có chứ"*,
*"không có dữ liệu xoá nào, khoá lại ẩn ghế đi"*.

**Ca mới:** GO Thủ Dầu Một có 4 ghế, báo cáo ra **1 ghế** (GO-TDM-4). Khác ca 80111: lần này màn
nhập (2.114) **không hiện dải cảnh báo ghế ẩn nào** — tức GO-TDM-1/2/3 không dính cờ `an`, chúng
rơi khỏi cơ sở (mất gán `coso_id` / đổi cơ sở nhầm / tên cơ sở lệch).

Ba lần mất ghế, ba nguyên nhân khác nhau. Vá từng nguyên nhân thì nguyên nhân thứ tư lại tới, mà
mỗi lần tới là một cơ sở nộp thiếu ghế nhiều ngày không ai thấy. Nên **đảo nguồn sự thật**:

1. **`ds_ghe()` bổ sung ghế theo lịch sử.** Ghế nào đã từng thu tiền ở cơ sở này trong
   `GHE_LS_NGAY` (45) ngày gần đây thì vẫn hiện, bất kể danh mục đang nói gì. Khớp theo `bc.coso`
   (tên cơ sở đóng băng trong báo cáo) chứ **không** theo `may.coso_id` — chính `coso_id` là thứ
   đang sai. Vẫn chốt theo phạm vi PIN, không thêm trùng, và ghế kéo về từ lịch sử thì bỏ khỏi dải
   cảnh báo "đang bị giấu".
   Cửa sổ 45 ngày là đường rụng cho ghế tháo thật: hết 45 ngày không thu đồng nào thì tự rụng.
2. **Khoá tính năng ẩn ghế.** `dat_an` / `dat_an_lo` / `xoa_may` (xoá mềm = `an=1`) đều chặn chiều
   BẬT qua `chan_an_()`; **vẫn cho gỡ** để dọn cờ cũ. Bốn nút bấm bên Quản lý ghế nay nói lý do
   thay vì gọi máy chủ rồi nhận lỗi. Việc mà nút ẩn từng làm, nay dữ liệu tự làm và làm đúng hơn:
   không ai phải nhớ bấm, và không ai bấm nhầm được.

Bài kiểm `tools/test/kiem-ghe-theo-lich-su.js` (14 phép).


### v2.114.0 — Bỏ cảnh báo "tiền VietQR chưa quy được cơ sở"

Anh Thắng 19/09/2026: *"Dữ liệu QR từ nhiều nguồn mà, nếu không biết thì bỏ qua"*.

Bản 2.113.0 thêm dải cam báo phần tiền VietQR về bank mà không quy được về cơ sở nào, phòng ca
"máy chưa gắn cơ sở → tiền thật rơi ra ngoài bảng". Đo trên host thật thì con số ấy ra
**725.709.481.314đ** trong khi cả bảng ghế cùng kỳ chỉ **1,24 tỷ** — tức tài khoản nhận VietQR
phục vụ nhiều mảng, gần như toàn bộ phần không khớp là tiền của mảng khác chứ không phải ghế lạc
mất tiền. Một cảnh báo lệch 500 lần thì không ai đọc, và nó che mất cảnh báo thật.

Bỏ ở **hai chỗ**: dải cam dưới cột TỔNG, và dòng "Chưa quy được cơ sở" trong lớp đối chiếu ở nút
QR. Không khớp = không phải của ghế ⇒ bỏ qua, không cộng vào TỔNG và cũng không kêu.
`kiem-bct-tong-thuc.js` nay canh chiều ngược: không được có dải cảnh báo ấy.


### v2.113.0 — Báo cáo tổng: cột TỔNG là TIỀN THẬT, không còn là sản lượng máy

Anh Thắng 18/09/2026: *"Tổng phải là thực thu để xác định tiền, còn chỉ số trên máy nó đâu phải
doanh thu, vì chỉ số trên máy nó còn sai số"* + *"QR là QR thực về ngân hàng, còn con số QR nhân
viên nhập chỉ là đối chiếu thôi"*.

**Cũ:** `tong = tien_mat + qr(NV khai)`, mà `tien_mat = actual − qr` khi nhân viên không gõ ô
"Thực thu" — tức **cả hai vế đều suy từ chỉ số máy**. Chỉ số máy đếm lượt chạy chứ không đếm tiền.
Số QR nhân viên khai thì lệch thấy rõ với sao kê: kỳ 01→18/09, AEON Tân Phú khai 85,29tr trong khi
bank về **91,92tr**; AEON Bình Dương **không khai đồng nào** mà bank về **23,31tr**.

**Nay:** `TỔNG = thực thu tiền mặt (người đếm) + VietQR THỰC về ngân hàng (sao kê)`.

- Vế tiền mặt lấy `bc_dong.tien_mat`, **không** lấy `bc_dong.tong` — `tong` đã gồm QR nhân viên
  khai, cộng thêm QR bank nữa là đếm tiền hai lần.
- Tiền bank cộng vào ô **trước** khi dựng danh sách cơ sở → cơ sở có tiền về mà chưa ai nộp báo cáo
  vẫn ra một dòng, không biến mất khỏi bảng.
- `vietqr_thuc_()` nay gọi **một lần**, dùng chung cho cả cột TỔNG lẫn lớp đối chiếu ở nút QR.
- **Chỉ mức Cơ sở.** Sao kê quy tiền về cơ sở, không về từng ghế — gộp "Từng ghế" giữ công thức cũ
  và màn hình nói thẳng đó là số nhân viên khai.
- Màn hình in công thức đang dùng, cảnh báo cam cho phần **tiền bank chưa quy được cơ sở**
  (`vqKhongKhop` — không nằm trong bảng, phải gắn máy vào đúng cơ sở), và báo khi chưa đọc được sao
  kê thì TỔNG đang tạm tính theo số nhân viên khai.

⚠️ **Còn một nửa chưa xong:** vế tiền mặt chỉ thật sự là "thực thu" khi nhân viên CÓ gõ ô Thực thu
tiền mặt; bỏ trống thì vẫn rơi về `actual − qr` (chỉ số máy). Muốn dứt điểm thì phải bắt buộc nhập
ô đó cho mọi ghế — chưa làm, chờ anh Thắng chốt vì nó đổi thói quen nhập hằng ngày.

Bài kiểm `tools/test/kiem-bct-tong-thuc.js` (12 phép).


### v2.111.0 — Ghế ẩn: nói ra ở màn nhập · để lại dấu vết · chuyển cơ sở là gỡ cờ ẩn

**Ca thật (18/09/2026).** Báo cáo 17/09 của **CGV Vincom Xuân Khánh** do Phan Như Hạnh nộp chỉ có
**1 ghế (CGV-CT-02)** trong khi cơ sở có 2. Ghế **80111 / CGV-CT-01** mang cờ `an` từ 13/09 ("chỉ
số trước 215, ngày 13/09" = lần cuối nó có mặt trong một báo cáo). Ghế KHÔNG hề bị chuyển cơ sở —
tra mã ra đúng cơ sở, `đang dùng`. Nó hiện/mất theo luật ghế ẩn đổi qua từng bản: ≤2.106 lọc sạch
→ 2.107 hiện lại kèm nhãn "đang ẩn" → **2.108 ẩn hẳn** (đúng yêu cầu, nhưng không còn dấu vết nào).

Ba việc, đều nhắm vào cùng một thứ: **giấu một dòng nhập mà không nói tại sao giấu**.

1. **Màn nhập NÓI RA.** `ds_ghe()` nhận thêm tham chiếu `$an_bo` gom ghế vừa bị giấu; `boot()` gửi
   xuống `gheAn`; chọn cơ sở xong hiện dải cam: *"Cơ sở này còn N ghế KHÔNG hiện ở bảng vì đang
   đánh dấu đã dọn/điều chuyển: …"*. Không dựng lại dòng nhập cho ghế ẩn (ghế ẩn vẫn ẩn), chỉ đếm
   và gọi tên để người nộp hỏi lại trước khi ký.
2. **Dấu vết trên chính dòng ghế.** Thêm `may.an_luc` + `may.an_ai`; `dat_an`/`dat_an_lo`/`xoa_may`
   /`dat_coso`/`dat_coso_lo` đều ghi "ai bấm, lúc nào", router truyền `$ai['name']`. Màn **Tìm ghế**
   in ngay dưới trạng thái. ⚠️ KHÔNG dựa vào bảng `nhat_ky`: nó chỉ giữ 500 dòng và bị log tiền vào
   đẩy trôi trong ngày — đúng lúc cần thì không còn.
3. **Chuyển cơ sở là gỡ cờ ẩn.** `dat_coso`/`dat_coso_lo` đặt `an=0` và báo rõ "đã hiện lại". Trước
   đây đổi Địa điểm không đụng cờ `an`, nên ghế ẩn ở cơ sở A chuyển sang B vẫn vô hình với nhân
   viên B, trong khi màn quản trị hiện nó nằm đúng cơ sở B.

Bài kiểm `tools/test/kiem-ghe-an-dau-vet.js` canh cả ba luật (20 phép).


### v2.110.0 — Tắt hẳn tự vẽ lại cả trang · Bảng chéo có khung cuộn riêng, hàng ngày dính đầu

Hai việc anh Thắng nêu 18/09/2026.

**1. *"Web cứ mấy giây lại nhảy trang một lần, tắt tính năng đó luôn"*.** `henLai()` hẹn giờ gọi
`tai(true)` → `ve()` dựng lại cả trang; đang đọc Báo cáo tổng thì bảng biến về "Đang tải…", cuộn
văng về đầu.

Danh sách tab được miễn trừ ở `henLai()` đã dài dần theo từng lần bị dính: `quan-ly` (xoá ô đang
gõ), `kt-duyet` (*"chỉ f5 chỗ đó thôi"*), `hl-hotro`, `ma` (đóng khối đang soạn), `bc-doanhthu`
(xoá ẢNH vừa chọn). Sáu lần cùng một lỗi thì cái sai nằm ở chỗ **tự vẽ lại được bật mặc định** —
tab mới nào cũng dính, và phải có người kêu mới biết. Nay lật mặc định: **không tab nào tự vẽ lại
cả trang**, muốn số mới thì bấm ↻ (mỗi thao tác thêm/sửa/xoá vẫn tự tải lại như cũ).

Hai thứ vẫn sống vì cập nhật TẠI CHỖ, không gây nhảy: đồng hồ đầu trang + đếm ngược ghế đang chạy
(`dhTop`/`chayDongHo`), và lưới ghế tab **Điều khiển** khi bật "Tự làm mới" (`capNhatDieuKhien()`
— thay mỗi lưới, giữ nguyên ô Số phút/Tiền mặt đang gõ). Chỗ đếm ngược hết giờ cũng đổi từ
`tai(true)` sang `capNhatDieuKhien()`, cùng lý do.

⚠️ Lối thứ ba dễ sót: handler `visibilitychange` (mở lại màn / quay về từ tab khác) trước đây gọi
thẳng `tai(true)` — liếc sang tab khác rồi quay lại là bảng dựng lại từ đầu, y hệt cái vừa tắt,
chỉ khác cái cớ. Nay chỉ còn tác dụng ở tab Điều khiển và cũng đi qua `capNhatDieuKhien()`.

**2. *"Có cách nào kéo sang mà nhìn được ngày, giờ muốn kéo được phải kéo xuống cuối trang"*.**
`.table-scroll` chỉ có `overflow-x`, không có trần cao → khung cuộn cao bằng cả bảng (sáu chục cơ
sở = mấy màn hình), nên thanh cuộn ngang nằm tận đáy bảng: phải cuộn dọc hết trang mới với tới,
kéo xong lại cuộn ngược lên mới đọc được hàng cần.

- Lớp `.bct-box` mới cho hai bảng chéo (Báo cáo tổng · Ngày×Ghế): `max-height:calc(100vh - 210px)`
  + `overflow:auto` → khung nằm gọn trong màn, thanh cuộn ngang luôn ở mép dưới.
- `.bct th` nay `position:sticky;top:0` → **hàng ngày dính đầu khung**, kéo ngang tới cột nào cũng
  biết là ngày nào. Ô góc (vừa dính trái vừa dính đầu) z-index cao nhất.
- Hàng **TỔNG dính đáy** (đã khai từ lâu) nay mới thật sự chạy — `sticky bottom` cần khung cuộn có
  trần, trước không có trần nên nó chẳng dính vào đâu.


### v2.109.0 — Báo cáo tổng: cột Tổng đứng ngay sau Số ghế, không còn ở cuối bảng

Anh Thắng 18/09/2026: *"cột tổng doanh thu đẩy ra trước, cạnh cột số ghế"*. Khoảng xem thường là
nửa tháng trở lên (ảnh 01/09–18/09 = 18 cột ngày), nên muốn biết một cơ sở thu được bao nhiêu
phải cuộn ngang hết bảng — cuộn xong thì chỉ còn cột tên cơ sở dính lại, số ghế và mã KH đã trôi
mất. Con số đáng đọc nhất lại là con số phải đi xa nhất mới thấy.

- `bctBang()` dời ô Tổng (cả tiêu đề, thân bảng, hàng TỔNG ở chân) lên ngay sau **Số ghế**.
- Lớp VietQR thực (nhãn đỏ dưới số, khi xem cột QR) đi theo cột Tổng sang chỗ mới, không tách rời.
- Dòng chú thích VietQR ở chân: khối trái nay **5 cột** (4 khi gộp theo cơ sở), phần còn lại đúng
  bằng số cột ngày — trước cộng thêm 1 cho cột Tổng ở cuối, nay không còn.
- `bctXuat()` (CSV) xếp cột **y hệt bảng đang nhìn**: tệp tải về mà khác thứ tự màn hình là kế
  toán dán sang Excel rồi dò nhầm cột.

Kiểm bằng bệ thử đếm ô: cả hai kiểu gộp (Cơ sở · Từng ghế), mọi hàng — tiêu đề, thân, hàng TỔNG,
dòng chú thích VietQR — đều bằng số ô nhau (7 và 8), thứ tự tiêu đề đúng Số ghế → Tổng → ngày.


### v2.108.0 — Ghế ẩn biến mất khỏi màn nhập của nhân viên (thu hẹp ngoại lệ của 2.107)

Anh Thắng 17/09/2026: *"những mã ghế ẩn, cho ẩn khỏi màn nhập nhân viên"*. Bản 15/09 chữa lỗi "0
ghế" bằng cách cho MỌI ghế `an`=1 hiện lại khi PIN gán tường minh ghế/cơ sở ấy — chữa đúng bệnh
nhưng quá tay: cơ sở còn ghế sống vẫn phải nhìn cả loạt hàng gắn nhãn "đang ẩn" (VD Vạn Hạnh Mall:
`80143`, `80144`, `VC-TDUC-1`, `VC-TDUC-2`).

- `VHG_BaoCao::ds_ghe()` tách làm **2 lượt**: lượt 1 gom ghế trong phạm vi + đếm **ghế sống của
  từng cơ sở**; lượt 2 mới dựng danh sách trả về.
- Luật mới cho ghế ẩn: hiện **chỉ khi** PIN gán đích danh ghế/cơ sở ấy **VÀ** cơ sở đó không còn
  ghế sống nào trong phạm vi PIN. Cơ sở còn dù một ghế sống → ghế ẩn biến mất.
- **Lưới chống khoá cửa giữ nguyên**: cơ sở bị ẩn sạch (điều chuyển/dọn tạm) thì ghế ẩn vẫn hiện để
  nhân viên nộp được — không tái phát lỗi "0 ghế" của 2.85/2.86.
- PIN toàn quyền (màn admin / quản nhiều nơi): không đổi, ghế ẩn vẫn giấu như cũ.
- Tab **Quản lý ghế**, đối chiếu, kế toán đọc thẳng `VHG_May::ds_may()` nên **vẫn thấy đủ** ghế ẩn;
  dữ liệu không mất, gỡ cờ `an` là ghế về lại màn nhập ngay.

Đã chạy bệ thử gọi thẳng `ds_ghe()` với 5 tình huống (cơ sở còn ghế sống / ẩn sạch / PIN gán đích
danh ghế ẩn / PIN toàn quyền) — kết quả đúng như luật trên.


### v1.73.0 — "Xác nhận đã nộp" thay cho dữ liệu cũ/đã nhập ở "Ai đang cầm tiền"

Sau khi v1.72.0 nối "Báo cáo doanh thu" vào Quỹ tiền mặt, dữ liệu CŨ/ĐÃ NHẬP (`kt_nhap`) hiện ra
hàng loạt "đang cầm" hàng trăm triệu — anh Thắng: *"một số lệnh nộp tiền cũ, thực ra mọi người đã
nộp rồi. Làm sao để duyệt nộp (dữ liệu import nên bên nhân viên không thấy)"*. Dữ liệu nhập không
gắn với phiên đăng nhập nào nên không có ai để tự bấm "Nộp về quầy".

- Nút **"Xác nhận đã nộp"** mới ở cuối mỗi dòng bảng "Ai đang cầm tiền" (chỉ kế toán/quản lý thấy,
  cùng quyền với nút "Đã nhận") — bấm là ghi HẾT NỢ NGAY cho đúng người đó, không qua bước "chờ
  xác nhận" như nộp thật (`VHG_Quy::nop_va_nhan_thay()` = gộp `nop()`+`nhan()` làm một, dùng lại
  nguyên luật gộp 3 nguồn/chốt UNIQUE có sẵn, không viết lại).
- Có hộp xác nhận nói rõ đây là ghi nợ hết NGAY LẬP TỨC, không hoàn tác dễ dàng — tránh bấm nhầm
  cho một khoản còn thật sự treo (chỉ nên dùng cho dữ liệu CŨ đã chắc chắn nộp rồi ngoài đời).
- Quyền mới `quy_nop_thay` xếp cùng nhóm "chốt doanh số" với `nop_nhan`/`nop_huy`.

### v1.72.0 — Nối "Báo cáo doanh thu" vào Quỹ tiền mặt + thoát 1 lần + đóng được Đối chiếu máy

Bốn việc, cùng đợt 29/08:

**1. Doanh thu báo cáo nay tính vào "đang cầm" ở tab Quỹ & nộp tiền.** Anh Thắng: *"Sau khi nhân
viên chốt báo cáo doanh thu, thì nó sẽ hiển ở đây là doanh thu nhân viên đang cầm. Trừ khi nhân
viên tích vào đã nộp thì nó chuyển sang vàng, và kế toán tích vào thì đã hết nợ"*. Trước bản này,
tab "Quỹ & nộp tiền" ("Tôi đang cầm"/"Ai đang cầm tiền") chỉ gom tiền từ 2 nguồn (`chot` — chốt ca
quét QR ghế, `thu` — thu tại quầy); tiền khai qua "Báo cáo doanh thu" (chỉ số/QR nhập tay, việc cả
phiên làm việc này xây) hoàn toàn KHÔNG nằm trong đó, dù nhân viên vẫn đang cầm tiền mặt thật cho
tới khi nộp.

- Thêm cột `bc.nop_id` (giống hệt cơ chế `chot.nop_id`/`thu.nop_id` có sẵn): 0 = báo cáo này tiền
  còn trên tay nhân viên; khác 0 = đã gộp vào một lượt "Nộp về quầy", chờ xác nhận.
- `VHG_Quy::dang_cam()`, `ai_dang_cam()`, `bao_cao_ca()`, `nop()`, `huy_nop()` đều nối thêm nguồn
  thứ ba này — dùng lại NGUYÊN VẸN luồng trạng thái đã có: **đang cầm** (nop_id=0) → nhân viên bấm
  **"Nộp về quầy"** → **chờ xác nhận** (tô nền vàng, cùng tông với các khung cảnh báo khác) → quản
  lý/kế toán bấm **"Đã nhận"** → **hết nợ**. Không dựng cơ chế mới, chỉ nối thêm một đường ống vào
  cơ chế cũ.
- ⚠️ **Khớp theo TÊN** (`bc.nhan_vien` so với `chot.nguoi`/tên đăng nhập token) — cùng namespace
  tên người mà `chot`/`thu` vẫn dùng. **Cần kiểm với anh Thắng**: PIN Báo cáo doanh thu của một
  nhân viên phải có `ten` GIỐNG HỆT tên tài khoản `/ghe` (token) của chính người đó thì số mới gộp
  đúng vào một dòng "đang cầm" — hai tên khác nhau (VD PIN ghi "Lan" nhưng tài khoản ghi "Lý Thị
  Ngọc Lan") sẽ tách thành HAI người khác nhau trên bảng "Ai đang cầm tiền".
- Tiện thể vá luôn 1 lỗi phát hiện khi soát code: cờ hiện nút "Đã nhận" (`quyen_nhan`) ở nhánh
  quản trị đang nhét cứng "chỉ Admin/Quản lý", trong khi quyền THẬT (`nop_nhan`/`nop_huy`) là quyền
  "chốt doanh số" — CẤU HÌNH ĐƯỢC, kế toán hoặc vai trò khác vẫn làm được mà không cần lên Quản lý.
  Vai trò được cấp quan_tri kiểu khác Admin/Quản lý (VD Cửa hàng trưởng) trước đây bị giấu mất nút
  dù đủ quyền — nay dùng chung đúng một hàm `duoc_chot_doanh_so()` như nhánh còn lại vẫn đang làm.

**2. "Thoát" trong Báo cáo doanh thu nay thoát LUÔN cả trang chính.** Anh Thắng: *"2 trang này là
1, tại sao thoát 2 lần"*. `/ghe` có HAI phiên đăng nhập tách biệt cố ý (PIN báo cáo riêng, token
trang chính riêng) cùng hiện trên một trang — trước đây bấm Thoát trong màn Báo cáo doanh thu chỉ
đóng lớp phủ, để lộ trang chính vẫn còn đăng nhập token, bắt bấm Thoát thêm lần nữa. Nay bấm Thoát
ở Báo cáo doanh thu gọi luôn `window.VHG_Trang.thoat()` — một cú bấm thoát cả hai, không đụng gì
tới việc ĐĂNG NHẬP (vẫn cần đúng PIN/token riêng như cũ).

**3. Nút "Đối chiếu máy" đóng lại được.** Anh Thắng: *"Lỡ xổ cái đối chiếu máy, giờ đóng lại không
được"*. Trước đây bấm là chạy, không có đường đóng — bảng đứng yên mãi tới khi tải lại cả trang.
Nay bấm lần 1 chạy đối chiếu (đổi chữ nút "Đóng đối chiếu"), bấm lần 2 xoá bảng + trả chữ nút về.

**4. Bảng tự co giãn theo màn hình máy tính/điện thoại** (nội dung gốc, giữ nguyên bên dưới).

### v1.71.0 — Bảng tự co giãn theo màn hình máy tính/điện thoại

Anh Thắng 29/08: *"Điều chỉnh trang tự co giãn theo màn hình máy tính và điện thoại"*.

- **Nguyên nhân**: mọi bảng trong `/ghe` (bảng "Số liệu từng ghế", Đối chiếu, Lịch sử tháng, Lịch
  sử chốt ca) dùng chung lớp `.bc-t`, và lớp này ép cứng `min-width:820px` — kể cả 3 bảng phụ chỉ
  5-7 cột ngắn và cả bảng ghế ở CHẾ ĐỘ GỌN (điện thoại, đã bớt cột "Tiền mặt/Thực thu/Ghi chú").
  Ép rộng 820px bắt điện thoại nào cũng phải cuộn ngang mới xem hết, dù nội dung thật sự đã đủ hẹp
  để vừa màn hình.
- **Vá**: bỏ `min-width` khỏi luật `.bc-t` chung; chỉ gắn lại (`.full`) cho đúng bảng ghế ở CHẾ ĐỘ
  ĐẦY ĐỦ (máy tính, 10 cột, có 2 ô nhập chữ "Thực thu"/"Ghi chú" thật sự cần bề ngang). Bảng ghế ở
  Gọn (`.gon`) và 3 bảng phụ nay tự co theo đúng nội dung, không còn bị ép rộng giả.
  Đồng thời bớt đệm ô + thu nhỏ chiều rộng tối thiểu của ô nhập trong chế độ Gọn (`.bc-t.gon`) cho
  vừa khít điện thoại phổ thông hơn.
- **Chưa hết cuộn ngang 100%** trên điện thoại màn rất nhỏ (dưới ~390px) — bảng vẫn là bảng số
  liệu dạng cột, không phải thẻ xếp dọc như màn "Sửa 24h". Anh Thắng test lại trên máy thật, còn
  thấy cuộn khó chịu ở đâu thì báo tiếp, em vá thêm.

Nhân tiện vá thêm 1 lỗi phát hiện cùng lúc: nút **"Đối chiếu máy"** trước đây bấm là CHẠY, không có
đường đóng lại — anh Thắng: *"Lỡ xổ cái đối chiếu máy, giờ đóng lại không được"*. Bảng đối chiếu đổ
ra rồi đứng yên mãi, muốn thu gọn lại phải tải lại cả trang. Nay bấm lần 1 chạy đối chiếu (như cũ,
đổi chữ nút thành "Đóng đối chiếu"), bấm lần 2 xoá bảng + trả chữ nút về — cùng kiểu bật/tắt với nút
"Sửa/Đóng" ở khung "Báo cáo trong 24h".

### v1.70.0 — Vá "Nộp" đứng số cũ sau khi ghi đè Thực thu + tô đỏ cho kế toán

Anh Thắng 29/08 bắt được ở ghế VP-PQ-16 (màn kế toán, tab Duyệt báo cáo): Tiền mặt đã ghi đè xuống
830.000đ nhưng cột "Nộp" vẫn đứng ở 990.000đ (số cũ trước khi ghi đè) — *"Chỗ này bị sai"*, kèm yêu
cầu *"Chỉ số nào thực thu (báo đỏ lên cho kế toán biết)"*.

- **Nguyên nhân**: `nop_so_tien` (cột "Nộp") được rải một lần lúc gửi báo cáo theo đúng `tien_mat`
  tại thời điểm đó (`chia_nop_()`). Sửa `tien_mat` sau đó — qua "Sửa 24h" của nhân viên
  (`VHG_BaoCao::sua_dong()`) hoặc qua màn kế toán (`VHG_KeToan::sua()`) — không tự kéo `nop_so_tien`
  theo, vì đây là cột lưu riêng, không tính lại mỗi lần đọc.
- **Vá**: cả hai hàm sửa trên nay TỰ CẬP NHẬT `nop_so_tien` theo số tiền mặt mới, nhưng CHỈ khi
  dòng đó trước đó đã nộp **duy nhất vừa đủ** đúng số `tien_mat` cũ (case phổ biến nhất — "nộp đủ"
  là mặc định). Nộp dở dang thì để nguyên — không đủ dữ kiện chia lại đúng giữa nhiều ghế cùng báo
  cáo, sửa sai còn nguy hơn để kế toán tự đối chiếu.
- **Hoàn tác** (`VHG_KeToan::undo()`, việc `'sua'`) cũng cập nhật theo: nhật ký sửa nay lưu thêm
  `nop_so_tien` cũ để hoàn tác trả lại đúng, không chỉ trả lại chỉ số/tiền mặt mà bỏ quên cột Nộp.
- **Tô đỏ**: màn kế toán (`ktdRow`) nay tô đỏ + đậm số "Tiền mặt" VÀ dòng ghi chú khi ghế đang có
  "Thực thu ghi đè" (trước chỉ tô đỏ dòng ghi chú bắt đầu bằng ⚠, bỏ sót câu ghi đè đứng một mình).

### v1.69.0 — Bắt buộc ảnh khi Sửa 24h (tối thiểu 1 ảnh/ghế)

Anh Thắng 29/08: *"bổ sung thêm ảnh trong báo cáo 24h nhé (tối thiểu 1 ảnh nhé)"*. Card sửa từng
ghế ở "Báo cáo trong 24h — sửa được" (`theGheSua`) nay có thêm 2 ô chọn ảnh (📷 Ảnh chỉ số / 🧹 Ảnh
vệ sinh, cùng kiểu nút với bảng nhập chính):

- Ghế **đã có sẵn ảnh** từ lúc gửi ban đầu → chỉ hiện số ảnh đã có, không bắt đính lại.
- Ghế **chưa có ảnh nào** → bấm "Lưu ghế này" bị chặn (kèm cảnh báo đỏ) cho tới khi chọn ít nhất 1
  trong 2 ảnh. Chọn xong thì ảnh được nén (giống ảnh ở bảng nhập chính) rồi gộp thêm vào đúng ghế.
- Chốt lại lần nữa ở server (`VHG_BaoCao::sua_dong()`): dù client bị bỏ qua/lỗi thời, server vẫn
  tự đếm ảnh cũ (cột `bc_dong.anh`) + ảnh mới trong `patch.images`, tổng bằng 0 thì từ chối lưu.
- `ds_24h()` (bc_recent) nay trả thêm `anh` (mảng URL ảnh đã có) cho mỗi ghế để client biết trước.

### v1.68.0 — Báo cáo TỔNG (không chi tiết), hiện lại số tiền ở Sửa 24h, gọn 1 hàng

Ba việc nhỏ, cùng theo yêu cầu anh Thắng 29/08:

- **Báo cáo tổng**: ô "Ảnh chứng từ nộp tiền" sẵn có nay kiêm luôn đường nộp THAY THẾ khi nhân
  viên không điền bảng chi tiết từng ghế — *"Ô này là nộp báo cáo tổng nếu không làm báo cáo
  kia"*. Đính ảnh + gõ số vào ô "Số tiền nộp" (dùng lại, đóng vai "Tổng doanh thu") rồi bấm Gửi:
  hệ thống ghi MỘT dòng `bc_dong` duy nhất (`ma_may=''`, không chỉ số/QR riêng), đánh dấu rõ trong
  ghi chú là báo cáo tổng để kế toán biết đối chiếu qua ảnh chứng từ, không qua chỉ số máy. Ảnh
  chứng từ là BẮT BUỘC trong luồng này (không có ảnh thì không tạo được báo cáo). Server:
  `VHG_BaoCao::luu_tong()` (mới) + dispatch `bc_submit_tong`. Client: nhánh mới trong
  `guiBaoCao()` khi bảng ghế trống nhưng có ảnh + có số tiền → gọi `guiBaoCaoTong()` (mới).
  Còn bảng chi tiết từng ghế thì luồng cũ (`bc_submit`) không đổi gì.
- **Sửa 24h hiện lại số tiền**: card sửa từng ghế trong "Báo cáo trong 24h — sửa được" nay có thêm
  dòng tính SỐNG "Actual: …đ · Tiền mặt (đủ): …đ" (kèm "đang ghi đè bằng Thực thu" khi có), cập
  nhật ngay khi gõ lại Chỉ số sau/QR/Thực thu — *"vẫn sẽ hiện số tiền thực thu và chỉ số tiền mặt
  đủ như lúc nhập gửi báo cáo"*, không phải bấm Lưu mới biết đúng/sai.
  Đồng thời 4 ô Chỉ số sau/QR/Thực thu/Ghi chú trong card này dồn từ 2 hàng x 2 cột thành **1 hàng
  4 cột** cho gọn — *"điều chỉnh thành 1 hàng luôn cho nó gọn"*.

### v1.67.0 — Cột "Tăng/Giảm" đổi thành "Thực thu": GHI ĐÈ, không còn cộng dồn

Anh Thắng 29/08: *"cột này là cột thực thu"* rồi *"khi nhập thực thu ở cột này, tiền cộng sẽ lấy
theo cột này"*. Trước đây cột "Tăng/Giảm" CỘNG vào công thức tiền mặt
(`actual − QR + điều_chỉnh`). Nay đổi hẳn:

- Đổi tên cột thành **"Thực thu"** ở màn nhập báo cáo chính, màn "Báo cáo trong 24h — sửa được",
  và ô "±" ở màn kế toán duyệt lẻ từng ghế (`theGheSua`).
- **Có gõ** ở cột này → tiền mặt phải nộp LẤY ĐÚNG số đó, ghi đè hẳn, không cộng vào công thức.
  **Bỏ trống** → vẫn tính theo công thức `actual − QR` như cũ, y như chưa từng có cột này.
- Áp dụng cho **mọi hàng**, không chỉ hàng bất thường (chỉ số ngược / công thức ra âm) như cơ chế
  "Thực thu ghi đè" ban đầu — hàng bất thường vẫn bắt buộc phải có (kèm lý do), hàng thường thì
  đây là lựa chọn. Gộp làm một với ô "Thực thu" trong khung cảnh báo cũ — không còn hai ô riêng.
- Server (`tinh_()`, `luu()`, `sua_dong()`) đều đã cập nhật cùng luật; `bc_recent()` (dùng cho màn
  Sửa 24h) chỉ trả số ra ô khi báo cáo ĐÃ thật sự ghi đè (đọc dấu "Thực thu ghi đè" trong ghi chú)
  — báo cáo cũ trước bản này (có số ở cột `dieu_chinh` theo nghĩa cộng dồn) hiện ra Ô TRỐNG, không
  bị hiểu nhầm thành một lượt ghi đè.
- ⚠️ **Chưa động tới** màn kế toán duyệt HÀNG LOẠT (dán bảng CSV `KTN_CANON`, cột `adjust`) — đó
  là màn NHẬP LẠI số liệu lịch sử đã chốt sẵn (actual/cash/total đã tính từ hệ cũ), không chạy qua
  công thức nào ở đây nên không bị ảnh hưởng, nhưng cũng chưa đổi nhãn cột cho khớp.

### v1.66.3 — "Không đọc được trả lời của máy chủ" khi bấm "Đối chiếu máy"

Anh Thắng 29/08, cơ sở POSH nhiều ghế bấm "Đối chiếu máy" ở màn báo cáo PIN thì báo *"Không đọc
được trả lời của máy chủ (mạng hoặc tường lửa)"* — cùng câu lỗi với v1.66.2 nhưng ở một nút khác
hẳn, nên là một nguyên nhân khác.

- **Gốc thật:** `VHG_BaoCao::doi_chieu()` hỏi CSDL **hai lượt riêng cho MỖI ghế** (QR · tiền mặt),
  cơ sở vài chục ghế thành vài chục lượt hỏi trong một lần bấm. Mỗi lượt lại lọc bằng
  `DATE(luc)=%s` — bọc cột `luc` trong hàm nên MySQL không dùng được phần `luc` của khoá
  `may (ma_may,luc)`, phải dò hết mọi hàng của riêng máy đó chứ không chỉ hàng trong ngày. Cộng
  dồn là vượt hẳn thời gian chờ mặc định (25s), trình duyệt báo đúng như lỗi mạng dù đây là chậm ở
  máy chủ.
- **Sửa:** đổi `DATE(luc)=%s` sang dạng khoảng `luc>=... AND luc<...` (dùng được trọn khoá
  `may`), và gom lại đúng **hai câu `GROUP BY ma_may` cho cả cơ sở trong một lượt** thay vì hỏi lại
  từng ghế — tra kết quả bằng mã máy trong bộ nhớ. Không đổi số liệu trả về, chỉ nhanh hơn.
- Thời gian chờ riêng cho lượt này cũng nâng 25s→45s làm lưới an toàn thứ hai.

### v1.66.2 — "Lỗi khi gửi báo cáo" ở cơ sở đính nhiều ảnh

Anh Thắng 29/08, cơ sở đính 13 ảnh chứng từ báo *"Không đọc được trả lời của máy chủ (mạng hoặc
tường lửa)"* khi bấm Gửi — xác nhận LẶP LẠI NHIỀU LẦN, riêng cơ sở nhiều ảnh mới bị. Đây là dấu
hiệu gói tin gửi lên quá nặng bị cắt/chặn giữa chừng (giới hạn dung lượng một lượt gửi của hosting
hoặc tường lửa), không phải mạng chập chờn ngẫu nhiên.

- **Nén ảnh chặt hơn:** cạnh dài 1280→1000px, chất lượng JPEG 0.6→0.5 — cắt đáng kể dung lượng mỗi
  ảnh, chứng từ (số tiền, mã QR) và ảnh chỉ số máy vẫn đọc được ở cỡ này.
- **Thời gian chờ riêng cho lượt Gửi:** 25s mặc định → 90s — lượt gửi kèm hàng chục ảnh trên 4G
  yếu có thể tải lâu hơn 25s dù ảnh đã nén, trước đây bị cắt ngang coi như lỗi dù vẫn đang tải.

⚠️ **Nếu vẫn còn lỗi sau bản này:** rất có thể là giới hạn CỨNG phía hosting (`post_max_size` của
PHP hoặc `client_max_body_size` của Nginx/tường lửa) — thứ KHÔNG sửa được từ trong mã plugin, phải
nhờ bên hosting nâng lên. Lúc đó cần tách lượt gửi ảnh ra khỏi lượt gửi số liệu chính (đổi kiến
trúc, việc lớn hơn) — báo lại nếu vẫn gặp để làm tiếp bước đó.

### v1.66.1 — Nút chọn ảnh: chữ Việt cố định, không lệ thuộc ngôn ngữ trình duyệt

Anh Thắng 29/08, ảnh PC hiện "Choose File" còn điện thoại hiện "Chọn tệp": *"tại sao trên web lại
khác trên điện thoại"*. KHÔNG phải trang gửi hai bản khác nhau — nút của `<input type="file">` là
chữ do CHÍNH TRÌNH DUYỆT vẽ theo ngôn ngữ hiển thị của trình duyệt đó (PC để tiếng Anh, điện thoại
để tiếng Việt), trang không có cách nào ép chữ đó qua HTML/CSS thường.

**Sửa:** ẩn hẳn nút xấu-xí đó (thu về 1×1px, vẫn bấm được qua `<label for="...">` phủ lên trên) và
tự vẽ MỘT nút chữ Việt cố định "Chọn ảnh" — giống hệt nhau trên mọi máy, mọi trình duyệt, bất kể
ngôn ngữ hệ thống người dùng đang để gì.

### v1.66.0 — Gọn/Đầy đủ đồng bộ theo PIN, không còn kẹt riêng từng máy

Anh Thắng 29/08, ảnh chụp màn PC vẫn hiện đúng 7 cột (thiếu Tiền mặt/Tăng-giảm/Ghi chú):
*"Trên PC sao lại không đồng bộ với web điện thoại, thiếu cột"*.

**Gốc:** lựa chọn Gọn/Đầy đủ (nút 🖥/📱 góc phải) chỉ lưu trong `localStorage` — kho riêng của
TỪNG TRÌNH DUYỆT. Đổi bên điện thoại (hoặc bấm nhầm một lần trên PC từ trước) không hề kéo theo
máy khác, vì không có gì nối `localStorage` của máy này với máy kia. Máy nào chưa từng đổi thì
tự đoán theo bề ngang cửa sổ đang mở (`max-width:860px`) — cửa sổ PC không tối đa hoá, hoặc từng
bị bấm "Gọn" một lần, là kẹt vĩnh viễn ở chế độ ít cột, đúng ca trong ảnh.

**Sửa — PIN là thứ chung duy nhất giữa các máy của một người:**
- Bảng mới `bc_gon` (pin, gon) — trống nghĩa là CHƯA TỪNG đổi (giữ nguyên cách đoán theo bề
  ngang màn hình như cũ, không ép ai).
- `VHG_BaoCao::boot()` trả thêm `gon` (0/1/null) — client nhận được thì DÙNG NGAY, ghi đè cách
  đoán cục bộ, trước khi vẽ màn chính.
- `datGon()` (khi bấm nút 🖥/📱) nay vừa ghi `localStorage` như cũ, vừa gọi `bc_gon_luu` lưu lên
  server theo PIN — đổi ở máy nào, máy khác mở `bc_boot` lần sau tự thấy đúng lựa chọn đó.

**Cần test:** trên điện thoại bấm "🖥 Đầy đủ" một lần, sau đó mở lại trang trên máy tính (đăng
nhập lại bằng cùng PIN, hoặc dùng "Mở màn Báo cáo doanh thu" từ `/ghe`) — máy tính phải TỰ hiện
Đầy đủ (đủ 10 cột: …Tiền mặt, Tăng/Giảm, Ghi chú…), không cần bấm lại nút.

### v1.65.1 — Lịch sử chốt ca của nhân viên

Anh Thắng 29/08: *"Bổ sung lịch sử chốt ca nhân viên"*. Màn "Báo cáo doanh thu" (chế độ Đầy đủ) đã
có "Lịch sử báo cáo trong tháng" (doanh thu từng ghế/ngày) — nhưng KHÔNG có chỗ nào cho nhân viên
tự xem lại mình đã CHỐT CA ra sao từng ngày (đủ hết cơ sở hay chốt sớm, lý do gì, bỏ qua cơ sở
nào). Thêm khối mới **"Lịch sử chốt ca"** ngay dưới, đọc thẳng từ `bc_phien` (bảng đã có sẵn từ
trước — một dòng/ngày/nhân viên, ghi mỗi lần gửi báo cáo hoặc chốt sớm) qua API mới
`bc_lichsu_ca` → `VHG_BaoCao::lich_su_ca()`. Lọc theo đúng PIN đang đăng nhập — mỗi người chỉ thấy
lịch sử của chính mình. Bảng hiện: ngày, trạng thái (Đủ báo cáo/CHỐT SỚM/Đang thu), số cơ sở đã
xong/tổng, tổng tiền, giờ chốt, và chi tiết lý do + cơ sở bỏ qua nếu là chốt sớm.

### v1.65.0 — Sổ doanh thu ghế + ẩn ghế đã dọn · Cơ sở chưa nộp báo cáo + lịch tuần · lọc theo nhân viên

Ba việc riêng, gộp chung một bản vì cùng lúc anh Thắng yêu cầu:

**a) Trang quản trị "Máy & cơ sở" — sổ doanh thu theo ghế + ẩn ghế đã dọn.** Anh Thắng: *"Sổ ra
từng ghế theo điểm gồm các cột (Doanh thu ghế trong tháng, Doanh thu QR, Doanh Thu Tiền mặt), (Tích
chọn ghế đã dọn/điều chuyển nơi khác: Ghế sẽ bị ẩn khỏi trang thu tiền của nhân viên, nhưng vẫn lưu
trong dữ liệu)"*.
- Bảng "Máy (ghế)" thêm 3 cột doanh thu (chọn tháng qua ô "Doanh thu tháng" phía trên, mặc định
  tháng hiện tại), gộp thẳng từ `bc_dong` theo `ma_may` (`VHG_BaoCao::doanh_thu_thang_theo_may()`)
  — không giữ bản số riêng, khỏi lệch với số kế toán đang duyệt.
- Cột mới **"Đã dọn/điều chuyển"**: tích là ẩn NGAY khỏi danh sách ghế cho nhân viên nhập chỉ số
  (`may.an`, cột mới) — ĐÁNH DẤU, KHÔNG XOÁ, giống hệt tinh thần cột `huy` ở bảng `thu`. Bỏ tích là
  dùng lại bình thường. Chỉ lọc ở **đúng một chỗ**: `VHG_BaoCao::ds_ghe()` — trang quản trị/kế toán
  vẫn thấy đủ ghế kể cả đã dọn, chỉ MÀN NHÂN VIÊN mất ghế đó.

**b) Tab "Duyệt báo cáo" — cảnh báo cơ sở chưa nộp hôm nay + lịch nộp theo tuần.** Anh Thắng:
*"Bổ sung Cơ sở chưa nộp báo cáo trong ngày. Với mỗi cơ sở sẽ set lịch nộp báo cáo theo tuần, từ đó
theo lịch cơ sở nào chưa nộp báo cáo."*
- Cột mới `coso.lich_bc` — danh sách số thứ (1=Thứ Hai…7=Chủ Nhật) cơ sở đó PHẢI nộp; mặc định
  `1,2,3,4,5,6,7` (mọi ngày, giữ đúng hành vi ngầm định cũ). Cấu hình qua khối gấp **"⚙ Lịch nộp
  báo cáo theo cơ sở"** ngay trên tab Duyệt báo cáo (có ô lọc tên, vì có tới ~540 cơ sở) — tích/bỏ
  tích ngày nào tự lưu ngay, không cần nút Lưu riêng.
- Khối **"⚠ Cơ sở chưa nộp báo cáo hôm nay"** đặt TRÊN CÙNG tab (việc phải làm ngay hôm nay, khác
  hẳn duyệt/đối chiếu cả tháng bên dưới) — đối chiếu đúng lịch riêng từng cơ sở (`lich_bc`) trước
  khi báo thiếu, và bỏ qua cơ sở đã hết ghế đang dùng (toàn bộ ghế đã "đã dọn") vì không có gì để
  thu. `VHG_KeToan::thieu_bao_cao()`.

**c) Tab "Duyệt báo cáo" — lọc theo nhân viên.** Anh Thắng: *"lọc báo cáo theo nhân viên"*. Thêm ô
chọn cạnh ô lọc cơ sở có sẵn; danh sách nhân viên KHÔNG có sẵn như cơ sở nên dựng lại từ chính
`r.rows` mỗi lần tải tháng (chỉ biết ai đã nộp SAU KHI tải xong). Lọc kết hợp ĐƯỢC với cơ sở (chọn
cả hai cùng lúc thu hẹp đúng giao của hai điều kiện).

### v1.64.0 — Tổng tiền mặt/QR THEO TỪNG CƠ SỞ ở khối Tiến độ

Anh Thắng 29/08, nhìn khối "Tiến độ: 6/9 cơ sở … · Tổng 5.880.000đ": *"Hiện tổng doanh thu tiền
mặt và QR theo cơ sở trên này"*. Trước đây khối này chỉ có MỘT số Tổng gộp cả ngày — muốn biết cơ
sở nào thu tiền mặt bao nhiêu/QR bao nhiêu phải mở từng báo cáo ra xem.

- **Backend (`VHG_BaoCao::phien_tinh()`):** ngoài tổng gộp cả ngày (`tong_tien_mat`/`tong_qr`/
  `tong`) như cũ, nay CỘNG DỒN thêm theo từng `coso_key` (một cơ sở có thể có NHIỀU report_id
  trong ngày do thu nhiều lần — xem v1.63.0/1.63.4 — phải gộp hết các lần của cùng cơ sở mới ra
  đúng tổng cơ sở đó), trả thêm mảng `theo_coso`: `[{ten, tien_mat, qr, tong}, …]`. Đi xuyên suốt
  cả 3 đường trả về phiên (`bc_phien`, `bc_submit`, `bc_chot_som`) vì cả ba đều gọi qua
  `phien_tinh()`, không cần sửa riêng từng chỗ.
- **Client (`veProg()`):** mỗi cơ sở ĐÃ GỬI (chip xanh) nay có thêm `title` (rê chuột xem nhanh
  trên máy tính) VÀ một dòng chi tiết luôn hiện ngay dưới hàng chip (máy chạm không rê được) —
  "Tên cơ sở: Tiền mặt X đ · QR Y đ · Tổng Z đ", mỗi cơ sở một dòng.

### v1.63.7 — "Lệch hàng" ở nút Gửi/Chốt VẪN còn sau 1.63.5 — gốc thật là `align-items:flex-end`

Bản 1.63.5 cho hai nút `flex:1 1 160px` để chia đều bề ngang, tưởng xong, nhưng anh Thắng chụp
màn hình lại báo **vẫn lệch hàng**. Dựng lại y hệt bằng Playwright mới thấy: hai nút RỘNG BẰNG
NHAU thật (đúng như 1.63.5 sửa), nhưng ở màn hẹp (điện thoại), chữ "Gửi báo cáo cơ sở này" dài hơn
nên tự XUỐNG DÒNG bên trong nút (nút cao 2 dòng), còn "Xin chốt ca sớm" vẫn vừa 1 dòng (nút thấp
hơn). `.bc-row` dùng chung `align-items:flex-end` — đúng cho các hàng có Ô NHẬP với NHÃN phía
trên (dán mép dưới cho input ngang hàng với nhãn), nhưng ở hàng CHỈ TOÀN NÚT này, flex-end lại dán
MÉP DƯỚI hai nút bằng nhau → nút thấp (1 dòng) bị đẩy tụt xuống so với nút cao (2 dòng), nhìn lệch
hẳn dù chiều rộng đã bằng nhau — đúng cái anh Thắng thấy, chỉ là do CHIỀU CAO chứ không phải chiều
rộng như 1.63.5 tưởng.

**Sửa:** đặt riêng `align-items:stretch` cho ĐÚNG hàng nút này (không đụng `.bc-row` dùng chung ở
những hàng khác) — cả hai nút cùng cao bằng nút cao nhất, mép trên/dưới thẳng hàng bất kể nút nào
xuống dòng.

⚠️ Bài học: đổi số lượng phần tử trong một hàng flex có `align-items` khác `stretch` (mặc định) mà
không tự kiểm ở MÀN HẸP dễ vỡ layout theo cách không thấy được nếu chỉ test màn rộng (desktop) —
1.63.5 test trên màn rộng nên KHÔNG thấy chữ xuống dòng, tưởng đã hết lệch.

### v1.63.6 — Chế độ Gọn (điện thoại) không thấy Actual khi gõ chỉ số sau

Anh Thắng test chế độ Gọn (BỆNH VIỆN 175), chụp màn hình bảng ghế (Ghế/Chỉ số trước/Chỉ số
sau/QR/📷/🧹 — không có cột Actual) kèm ảnh một cột "ACTUAL" riêng: *"Khi nhập số sau, sẽ hiện
luôn ra số trừ nhé"*. Đúng — `calc()` vốn đã tính lại `actual = (sau−trước)×đơn vị` mỗi lần gõ
(uỷ quyền sự kiện `input`), nhưng ô hiển thị (`cellRo('actual')`) chỉ được thêm vào bảng ở chế độ
Đầy đủ (`if(!GON){...}`), nên chế độ Gọn tính đúng nhưng KHÔNG có ô nào để hiện ra — nhân viên gõ
chỉ số sau xong không thấy gì ngay, phải cuộn xuống xem tổng "Thực thu" cuối bảng. Đã cho cột
Actual hiện ở CẢ HAI chế độ (chỉ ẩn cột "Tiền mặt" riêng ở Gọn, giữ bảng gọn nhẹ) — gõ chỉ số sau
là thấy Actual cập nhật ngay tại đúng hàng ghế đó.

### v1.63.5 — Hàng nút Gửi/Chốt lệch sau khi bỏ nút "➕ Thu lần nữa"

Anh Thắng test bản 1.63.4, chụp màn hình: hai nút "Gửi báo cáo cơ sở này" / "Xin chốt ca sớm" dồn
về bên trái, để lại một khoảng trắng lớn bên phải trong cùng khối — *"lệch hàng"*. Đúng: bỏ nút thứ
ba (➕ Thu lần nữa) ở 1.63.4 mà quên chỉnh lại độ rộng hai nút còn lại — hàng flex vẫn để chúng ở độ
rộng tự nhiên (theo chữ), không co giãn theo hàng. Đã cho cả hai nút (và nút "Đối chiếu máy" ở chế
độ Đầy đủ) `flex:1 1 160px` để chia đều hết bề ngang hàng, không còn khoảng trắng thừa.

### v1.63.4 — TÌM RA GỐC THẬT của "vẫn bắt gõ lại PIN": lỗi ĐỊNH TUYẾN, không phải PIN/phiên

Sau ba bản vá liên tiếp (1.63.1 lưu PIN vào phiên, 1.63.2 thêm migration tay + `viSao`) mà anh
Thắng vẫn báo **vẫn bắt đăng nhập**, bản 1.63.2's `viSao` cuối cùng lộ ra câu lỗi thật trên màn:

> *"Tự động vào thất bại: Việc báo cáo không rõ: bc_boot_tu_token"*

**Gốc thật — bug định tuyến (dispatch) trong `class-vhg-trang.php::api()`, không liên quan gì tới
PIN hay phiên:** ngay đầu hàm `api()` có một cổng chặn SỚM cho mọi việc PIN-riêng:

```php
if ( 0 === strpos( $viec, 'bc_' ) && 0 !== strpos( $viec, 'bc_pin_' ) ) { … trả lỗi "Việc báo
cáo không rõ" cho mọi $viec lạ rồi return NGAY … }
```

`bc_boot_tu_token` (thêm từ v1.53.0, dùng token `/ghe` để suy PIN — KHÔNG dùng PIN-riêng) có tên
bắt đầu bằng `bc_` và không bắt đầu bằng `bc_pin_`, nên **luôn luôn** rơi vào cổng này, luôn luôn
rớt xuống "Việc báo cáo không rõ: bc_boot_tu_token", và **không bao giờ chạm được** tới cài đặt
thật của nó nằm SAU cổng token (dòng ~267, dùng `$ai` từ `user_by_token()` + `pin_phien_tu_token()`
— chính là chỗ 1.63.1/1.63.2 sửa). Nói cách khác: **tính năng "Mở màn Báo cáo doanh thu" đã là mã
CHẾT — không thể chạy được — kể từ ngày ra đời ở v1.53.0.** Hai bản vá 1.63.1 và 1.63.2 đều ĐÚNG về
mặt logic (PIN giờ đã nằm sẵn trong phiên, migration đã chắc chắn chạy, `viSao` đã sẵn sàng chẩn
đoán) nhưng không có tác dụng gì vì đường gọi bị chặn từ bước định tuyến, trước khi tới được đoạn
code dùng PIN đó.

**Sửa:** khai `bc_boot_tu_token` là ngoại lệ thứ hai của cổng PIN-riêng, y hệt `bc_pin_*`:

```php
if ( 0 === strpos( $viec, 'bc_' ) && 0 !== strpos( $viec, 'bc_pin_' ) && 'bc_boot_tu_token' !== $viec ) {
```

⚠️ Đây là bài học chung cho MỌI việc `bc_*` mới thêm sau này mà không đi qua PIN-riêng: phải khai
thêm vào đúng dòng `if` này ở đầu `api()`, không thì mọi sửa ở đoạn xử lý thật phía sau đều vô tác
dụng — lặp lại y hệt lỗi này. Xem khối 🔴 chú thích ngay tại dòng `if` đó.

**Cần test:** bấm "Mở màn Báo cáo doanh thu" khi đã đăng nhập `/ghe` — phải vào THẲNG bảng số liệu,
không hỏi PIN, không còn banner vàng "Tự động vào thất bại".

### v1.63.4 — Bỏ nút "➕ Thu lần nữa": LUÔN tự hiểu là thu thêm một lần mới

Anh Thắng 29/08: *"không nên bấm + thu lần nữa, mà sẽ tự hiểu và chèn vào giữa, nghĩa là chọn ngày
đó thì doanh thu ngày đó thôi"* → xác nhận qua câu hỏi làm rõ: **LUÔN tự hiểu là thêm một lần thu
mới (nối tiếp), không bao giờ đè lên lần cũ** — bỏ hẳn khái niệm bật/tắt tay.

- **`class-vhg-baocao.php::luu()`:** bỏ hẳn nhánh `$lan_moi` true/false — giờ MỌI lượt Gửi đều tạo
  `report_id` mới + `lan = MAX(lan ngày đó)+1`, chỉ số trước LUÔN tính nối tiếp lần gần nhất
  (`chi_so_truoc(..., true)`, không còn `false`). Đã bỏ hẳn khối "sửa đè lần cũ" (`header_()` tìm
  lần mới nhất để UPDATE) — gửi lại cho cùng cơ sở/ngày giờ không còn cách nào đè lên báo cáo cũ từ
  màn nhập chính này nữa. Muốn SỬA một lần đã gửi (gõ nhầm số) thì đi qua màn Sửa/Lịch sử 24h
  (`bc_edit`) — khác việc, không lẫn vào cùng một nút Gửi.
- **Client `js_baocao()`:** bỏ hẳn nút `bc-lan` ("➕ Thu lần nữa"), banner xanh "Đang thu LẦN NỮA…",
  và biến `LAN_MOI`. `selectLoc()` giờ luôn gọi `bc_lastmeters` với `toi:1` (nối tiếp) và luôn đọc
  lại nháp — không còn nhánh theo `LAN_MOI`. `bc_submit` không còn gửi cờ `lan_moi` (server không
  đọc cờ này nữa).
- **Cần test:** chọn một cơ sở/ngày ĐÃ có báo cáo, nhập số liệu mới rồi Gửi — không cần bấm nút gì
  thêm, hệ thống phải tự tạo LẦN 2 nối tiếp lần 1 (chỉ số trước = chỉ số sau của lần 1), thông báo
  "Đã gửi báo cáo … (lần 2)".

### v1.63.4 — Ô ảnh báo cáo: cho chọn từ thư viện, không chỉ chụp camera

Anh Thắng 29/08: *"Bổ sung thêm upload ảnh báo cáo, được chọn thêm ảnh từ thư viện thay vì việc chỉ
cho nhân viên chụp bằng camera thôi"*. `celAnh()` (ô ảnh 📷 Chỉ số / 🧹 Vệ sinh mỗi ghế) trước có
`capture='environment'` — thuộc tính này ép trình duyệt điện thoại mở THẲNG app camera, bỏ qua màn
chọn "Chụp ảnh / Chọn từ thư viện / Duyệt tệp" mặc định của hệ điều hành. Bỏ thuộc tính này là đủ —
`accept='image/*'` vẫn giữ, chỉ giới hạn loại tệp là ảnh.

### v1.63.3 — "➕ Thu lần nữa" bấm xong không thấy gì đổi (đúng ca cần test của 1.63.0)

Anh Thắng test đúng kịch bản BAN_GIAO đã nêu ở 1.63.0 ("bấm ➕ Thu lần nữa, chỉ số trước phải nối
tiếp") và báo: *"vẫn ghi nhận chỉ số cũ"* — bấm nút xong, chỉ số trước vẫn hiện số GỐC (330) chứ
không nối tiếp lần 1 (340).

**Gốc — LỖI HIỂN THỊ, không phải lỗi số liệu:** nút "➕ Thu lần nữa" và dòng banner xanh "Đang thu
LẦN NỮA…" chỉ được DỰNG MỘT LẦN lúc vẽ trang (`veChinh()`), dựa theo `LAN_MOI` **tại đúng lúc đó**
— luôn là `false` vì `veChinh()` chạy khi mở màn. `onclick` của nút chỉ đổi biến `LAN_MOI` trong bộ
nhớ JS rồi gọi lại `selectLoc()` để tải chỉ số mới, nhưng KHÔNG tự vẽ lại chữ trên nút hay banner —
nút vẫn ghi "➕ Thu lần nữa" y hệt trước khi bấm, banner xanh không hiện. Nhân viên bấm xong không
thấy GÌ xác nhận là đã bật chế độ, nên **không biết bấm có ăn hay chưa** — đúng câu "vẫn ghi nhận
chỉ số cũ" (không hẳn nhầm số, mà không có gì báo là đã đổi chế độ).

⚠️ Bản 1.63.3 CHỈ sửa phần HIỂN THỊ (nút tự đổi chữ + banner tự hiện/ẩn ngay trong `onclick`, không
đợi vẽ lại cả trang) — chưa đụng gì tới `chi_so_truoc_ct_()` (đọc lại kỹ thấy logic SQL đúng: lọc
`ngay <= $ngay`, sắp `ngay DESC, lan DESC` thì phải ra đúng lần thu gần nhất trong ngày). Đợi anh
Thắng test lại bản này để biết chắc: nếu bấm nút thấy banner xanh hiện ra VÀ chỉ số trước đổi đúng
sang 340 → xong hẳn cả 1.63.0 lẫn 1.63.3. Nếu banner hiện ra mà chỉ số trước VẪN sai → lỗi số liệu
thật nằm ở `chi_so_truoc_ct_()`/`bc_lastmeters`, cần đọc lại từ đầu với dữ liệu thật (mã ghế, ngày,
report_id của cả hai lần) chứ không suy đoán tiếp từ xa được nữa.

### v1.63.1 — "Mở màn Báo cáo doanh thu" vẫn bắt gõ lại PIN (Võ Nguyễn Hồng Nhung, 29/08)

**Hiện tượng:** nhân viên đã đăng nhập token `/ghe` xong, bấm tab "📋 Báo cáo doanh thu" (nút mở
thẳng khỏi gõ PIN, thêm ở v1.53.0), vẫn rơi về cổng "Nhập mã PIN nhân viên thu tiền".

**Gốc:** `VHG_BaoCao::boot_tu_ai()` (đã có từ v1.53.0, sửa một lần hôm 28/08 cho ca Vũ Nguyễn Hồng
Nhung) suy PIN bằng cách khớp **(tên, cơ sở)** trong `VHG_Auth::users()` — sổ nhân sự SỐNG — với
`coso` ghi trong phiên đăng nhập lúc trước, tức một ẢNH CHỤP cũ. Hồ sơ đổi cơ sở (hoặc gộp thêm cơ
sở phụ) SAU lúc đăng nhập là lệch khớp ngay; khớp lùi về tên suông thì lại trượt nếu trùng tên với
ai khác trong 400+ nhân sự. Cả hai kiểu trượt đều IM LẶNG — màn hình trông như tính năng chưa hề
chạy.

**Sửa tận gốc, không vá thêm điều kiện khớp:** bảng phiên (`vhg_phien`) nay có thêm cột `pin` —
`VHG_Auth::login()` ghi luôn PIN vừa xác thực đúng vào phiên. `boot_tu_ai()` dùng PIN đó trực tiếp
qua hàm mới `VHG_Auth::pin_phien_tu_token()`, hết mọi kiểu khớp tên/cơ sở. Đường dò cũ (tên+cơ sở)
**vẫn giữ lại** làm cầu nối cho phiên phát TRƯỚC bản 1.63.1 (chưa có `phien.pin`) — mất dần khi
phiên đó hết hạn (30 ngày) hoặc người dùng đăng xuất/đăng nhập lại.

⚠️ **PIN KHÔNG được gộp vào `$ai`/`user_by_token()`** dù tiện hơn — `$ai` bị nhúng thẳng vào JSON
`so_lieu()` gửi cho MỌI người, MỌI lượt tải trang; gộp PIN vào đó là in PIN ra network tab của tất
cả mọi phiên. Lấy PIN bằng hàm riêng (`pin_phien_tu_token()`), gọi tay đúng MỘT chỗ (dispatch
`bc_boot_tu_token`) — xem khối 🔴 trong `class-vhg-auth.php` và `class-vhg-baocao.php` trước khi
đụng lại chỗ này.

**Cần test:** một tài khoản đã ĐĂNG NHẬP TRƯỚC khi cài 1.63.1 phải **đăng xuất/đăng nhập lại MỘT
LẦN** để phiên mới mang theo `pin` — nếu chưa, `boot_tu_ai()` vẫn chạy đường dò cũ (vẫn có thể
đúng, nhưng không phải đường chính đã sửa). Sau khi đăng nhập lại, bấm "Mở màn Báo cáo doanh thu"
phải vào thẳng, không hỏi PIN — thử với đúng tài khoản Võ Nguyễn Hồng Nhung trước.

### v1.63.2 — 1.63.1 CHƯA HẾT: thêm chốt migration tay + báo lỗi rõ nguyên nhân

Anh Thắng test lại sau khi cài 1.63.1 (đã xác nhận đúng bản, đã đăng xuất/đăng nhập lại đàng
hoàng): **vẫn bắt đăng nhập**. Vậy lỗi không phải "phiên cũ chưa mang PIN" như 1.63.1 giả định.

**Nghi vấn hàng đầu:** bảng `phien` là bảng ĐANG SỐNG (có người đang mở web ngay lúc cài), và
`boot_tu_ai()` không cách nào tự phân biệt "cột `pin` rỗng vì phiên phát trước 1.63.1" với "cột
`pin` KHÔNG TỒN TẠI vì dbDelta chưa kịp/không thêm được trên bảng đó" — cả hai đọc ra y hệt nhau
(chuỗi rỗng). Trong khi bảng `bc` (thêm cột `lan` ở 1.63.0) là bảng có ÍT thao tác ghi đồng thời
hơn `phien` (mỗi request có token đều SELECT/DELETE bảng này).

**Đã thêm hai lớp, KHÔNG PHẢI ĐOÁN THÊM:**
1. `VHG_DB::migrate_()`: thêm tay `ALTER TABLE ... ADD COLUMN pin ...` có chốt
   `SHOW COLUMNS ... LIKE 'pin'` (idempotent), TÁCH KHỎI vòng lặp `dbDelta` chung — không còn dựa
   vào dbDelta tự thêm cột trên một bảng đông người dùng thật nữa.
2. `boot_tu_ai()` trả thêm trường **`viSao`** khi thất bại (bước nào trượt: có/thiếu `pin_phien`,
   tên không khớp ai, trùng tên bao nhiêu người, cơ sở hồ sơ của những người trùng tên là gì…).
   Client (`moBaoCao()`) hiện thẳng `viSao` lên MÀN HÌNH (ô vàng, ngay dưới tiêu đề "Báo cáo doanh
   thu") thay vì im lặng rớt về cổng PIN như trước — **không cần mở DevTools**, chỉ cần chụp màn
   hình là đọc được lý do.

**Nếu 1.63.2 vẫn không vào thẳng được:** đọc đúng dòng `viSao` hiện trên màn (không phải đoán lại
từ đầu) rồi mới sửa tiếp — dòng đó nói chính xác bước nào trượt.

### v1.63.0 — Thu NHIỀU LẦN trong ngày (mỗi lần 1 bản ghi, chỉ số nối tiếp)
Anh Thắng 29/08: *"thay vì 1 ngày 1 lần, cho thu nhiều lần; lần sau chỉ số tự đẩy chỉ số cũ vào"*.
- **CSDL:** thêm cột `lan SMALLINT` vào `bc` và `bc_dong`; đổi UNIQUE của `bc` từ
  `(coso_key,ngay)` → `(coso_key,ngay,lan)`. dbDelta KHÔNG tự bỏ khoá cũ nên có `VHG_DB::migrate_()`
  (chạy trong `install()`, idempotent) `ALTER TABLE bc DROP INDEX coso_ngay` nếu còn.
- **Nối chỉ số theo `(ngày, lần)`:** `chi_so_truoc_ct_()` thêm tham số `$toi`; `$toi=true` lấy CẢ
  chỉ số sau của các lần thu TRONG chính ngày đó (sắp `ngày DESC, lan DESC`) để lần sau nối tiếp
  lần trước. `chi_so_truoc()/lay_chiso_truoc()` thêm `$toi` truyền xuống.
- **`luu()`:** đọc cờ `lan_moi`. Có cờ → tạo LẦN THU MỚI: `report_id` mới, `lan = MAX(lan ngày đó)+1`,
  chỉ số trước tính với `toi=true` (nối lần trước); không cờ → sửa lần mới nhất trong ngày như cũ
  (`header_()` nay `ORDER BY lan DESC`). Cột `lan` ghi vào cả `bc` lẫn `bc_dong`.
- **Client `js_baocao()`:** nút **➕ Thu lần nữa** (`bc-lan`): bật `LAN_MOI`, tải lại chỉ số trước
  với `toi=1` (nối tiếp), không nạp nháp cũ; khi Gửi kèm `lan_moi:1`; gửi xong tự tắt. `bc_lastmeters`
  nhận thêm `toi`.
- **Kế toán KHÔNG phải sửa gì:** `ds()` và `doanhthu_ky()` vốn GOM + CỘNG theo `coso_key|ngày`,
  "Duyệt cả báo cáo"/"Khoá ngày" theo `coso+ngày` (bao mọi lần), checkbox duyệt lẻ theo
  `(report_id,ma_may)` (mỗi lần một report_id) → nhiều lần/ngày tự cộng đúng.
- ⚠️ **Còn để ý:** ngày đã KHOÁ vẫn chặn thu thêm (đúng ý — nhờ kế toán mở). Nối chỉ số GIỮA các
  lần CÙNG NGÀY khi SỬA lần cũ chưa tự lan sang lần sau cùng ngày (hiếm; nối lúc tạo là đủ). Chi
  tiết một ngày hiện mỗi lần một dòng cho cùng ghế (đúng, mỗi lần thu một dòng).

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

### Việc mới của phiên 29/08 (v1.62.0–1.63.0) — cần anh Thắng test thực tế
- **v1.63.0 THU NHIỀU LẦN/NGÀY — quan trọng nhất, phải test kỹ vì ĐỔI CSDL.** Migration tự chạy khi
  nâng cấp (`VHG_DB::migrate_()` bỏ UNIQUE cũ `coso_ngay`). Kịch bản test: một cơ sở gửi lần 1 →
  bấm **➕ Thu lần nữa** (chỉ số trước phải nối tiếp chỉ số sau lần 1) → gửi lần 2 → mở **Duyệt báo
  cáo** xem có ĐỦ 2 lần và **tổng cộng đúng** không. Nếu bảng Duyệt gộp/đếm ghế sai khi một ghế thu
  2 lần, xem `VHG_KeToan::ds()` (gom theo `coso_key|ngày`) và `chi_tiet()`.
  ⚠️ **Test lần 1 (29/08) đã lộ lỗi HIỂN THỊ**: bấm "➕ Thu lần nữa" không thấy nút/banner đổi gì
  → sửa ở v1.63.3 (mục 1 ở trên). **Test lại từ đầu** với bản 1.63.3: bấm nút phải THẤY banner
  xanh hiện ra ngay, rồi mới xem chỉ số trước có đúng nối tiếp không — nếu banner hiện mà số vẫn
  sai thì mới là lỗi `chi_so_truoc_ct_()` thật, báo lại kèm mã ghế/ngày/report_id cả hai lần.
- **v1.62.0 nối dòng thời gian — chưa nối ở `xoa()` / `undo()`** (xoá/hoàn tác cả báo cáo). Nếu xoá
  một ngày GIỮA rồi thấy chỉ số trước ngày sau không tự lùi về mốc trước đó → thêm gọi
  `VHG_BaoCao::noi_tiep()` vào hai hàm đó (class-vhg-ketoan.php). Chưa làm vì ngữ nghĩa restore của
  undo phức tạp, và xoá ngày giữa hiếm.
- **v1.62.0 ô ảnh mọi chế độ** — đã anh xác nhận trực quan (điện thoại & PC đều thấy 📷/🧹). Coi như xong.
- **v1.63.1/1.63.2 PIN phiên** — xem mục 1 ở trên. 1.63.1 CHƯA hết lỗi dù đã đăng xuất/đăng nhập
  lại đúng cách; 1.63.2 thêm chốt migration tay + hiện `viSao` (lý do trượt) thẳng lên màn hình
  cổng PIN. Cần Võ Nguyễn Hồng Nhung thử lại "Mở màn Báo cáo doanh thu" — nếu vẫn hỏi PIN, đọc
  đúng dòng vàng hiện trên màn (viSao) rồi báo lại nguyên văn, đừng đoán tiếp từ đầu.

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
