# Quy ước làm việc trong repo này

> Đọc file này trước khi sửa bất cứ thứ gì. Bàn giao chi tiết từng mảng:
> [`docs/BAN-GIAO-trang-ghe.md`](docs/BAN-GIAO-trang-ghe.md) (trang Ghế),
> [`BAN_GIAO_POSH_VE.md`](BAN_GIAO_POSH_VE.md) (bán vé + Zalo Mini App),
> [`README.md`](README.md) (firmware ESP32).

## 1. GIAO VIỆC BẰNG FILE ZIP, KHÔNG BẰNG ĐOẠN CODE

Anh Thắng cài plugin qua **WordPress → Plugins → Add New → Upload Plugin**, không sửa file trên
host. Nên mỗi lần sửa xong plugin thì **luôn build lại `.zip` và gửi thẳng file đó**, kể cả khi
chỗ sửa chỉ một dòng. Dán đoạn code ra chat là bắt anh ấy sửa tay trên máy chủ — dễ sai, và dễ
quên tăng số bản nên không biết bản nào đang chạy.

Dán code chỉ khi anh ấy hỏi *"sửa chỗ nào"* / *"cho xem code"* — và vẫn kèm zip.

```bash
# build (chạy ở gốc repo, tên thư mục = tên plugin)
bash tools/build-ghe.sh   # GHẾ: tạo bản sao lớp báo cáo mang số bản, zip, tự diff — xem §1 bên dưới
rm -f dist/vhcp-saoke.zip && zip -qr dist/vhcp-saoke.zip vhcp-saoke -x '*.DS_Store'   # các plugin khác
```

- `vhcp-ghe/` → `dist/vhcp-ghe.zip` (đã có sẵn trong repo, commit kèm mỗi lần sửa). **Build bằng
  `tools/build-ghe.sh`, không zip tay**: script chép `includes/class-vhg-baocao.php` thành
  `includes/class-vhg-baocao-v<VER>.php` (xoá bản sao cũ trước) rồi mới zip. `vhcp-ghe.php` nạp
  **bản sao** này trước, tệp gốc chỉ là đường lui — vì trên host thật tệp gốc **kẹt quyền trên đĩa**,
  sáu lần cài zip (2.86→2.92) không ghi đè được mà vẫn "cài thành công" (15/09/2026). Tên mới thì
  luôn ghi được, opcache luôn biên dịch tươi. **Chỉ sửa tệp gốc**, không sửa tay bản sao; bài
  `kiem-ghe-ban-baocao.php` canh bản sao đúng MỘT, đúng tên, byte-y-nguyên. Commit cả bản sao.
- `vhcp-saoke/` → `dist/vhcp-saoke.zip`
- `vhcp-ve/` → `dist/vhcp-ve.zip`
- `dist/vhcp-du-an.zip` → chỉ có zip, **không có mã nguồn trong repo**, không sửa được từ đây

Sau khi build: giải nén ra chỗ tạm rồi `diff -rq` với thư mục nguồn để chắc zip đúng bản.

## 2. Bắt buộc trước khi giao

1. `php -l` từng file `.php` đã đụng vào.
2. **`bash tools/soat-js-heredoc.sh`** — soát cú pháp **mọi** khối JS nằm trong `<<<'JS' … JS;`.
   PHP không kiểm cú pháp trong heredoc: **một dấu `}` thừa là trắng cả trang** (đã dính ở
   2.20.0, vá ở 2.20.1). ⚠️ **Đừng gõ tay `sed` hai khối như trước**: 15/09/2026
   `class-vhg-trang.php` có khối **thứ ba** (trang `/it`) và lệnh gõ tay cũ vẫn "xanh" trong khi
   khối mới chưa hề được kiểm. Script tự tìm nên thêm khối bao nhiêu cũng không sót.
3. Tăng số bản ở **ba** chỗ, phải bằng nhau: header `Version:`, hằng `VHG_VERSION` (cùng trong
   `vhcp-ghe.php`) **và** `VHG_BaoCao::BAN` (trong `includes/class-vhg-baocao.php`). Chỗ thứ ba là
   *vân tay* của tệp lớp: `boot()` trả nó ra (`banBc`), `vhg_soat_tep_lop()` so với `VHG_VERSION` để
   bắt ca *tệp lớp cũ còn sống* (opcache giữ bytecode cũ / upload không ghi đè được — 15/09/2026,
   bốn bản vá đúng liên tiếp "không ăn" vì thế). Quên tăng `BAN` là bộ soát báo đỏ trên host dù
   mã đúng. Bài `tools/test/kiem-ghe-ban-baocao.php` canh ba chỗ; `chay-het.sh` tự gom.
   Trang in số bản ra góc phải; số không đổi thì không ai biết bản mới đã lên chưa.
4. Build zip, commit cả zip lẫn source, push.

## 3. Nhánh — VÀ MỘT KHO NÀY CÓ HAI CÂY PLUGIN, ĐỌC KỸ

Nhánh của trang Ghế / Sao Kê / bán vé: **`claude/posh-qr-kh1urz`**.
Không mở PR nếu chưa được yêu cầu. `main` đang tụt lại rất xa (chỉ còn phần firmware).

🔴 **Nhưng plugin KHÔNG chỉ sống ở nhánh này.** Nhánh
**`claude/rebuild-chi-phi-wordpress-hl2yze`** giữ 14 plugin trong thư mục `wordpress/`:
Chi Phí (+4 biến thể hn · mtd · vp · tổng), Chấm Công, Dự Án, Hợp Đồng, Nội Bộ, Trang Chủ,
Cổng, Doanh Thu FABi, Nền tảng K&H, và **một `vhcp-ghe` KHÁC HẲN** cái ở nhánh này.
Nhánh ấy có sẵn workflow `phat-hanh.yml` tự dựng zip và treo lên Releases theo tag
`<tên-plugin>-v<số bản>`, nên plugin ở đó tự cập nhật qua Releases chứ không qua `dist/`.

⚠️ **Tìm mã một plugin thì phải dò CẢ HAI NƠI.** 15/09/2026 em tìm mỗi nhánh này rồi kết luận
"Chi phí không có trên GitHub" — sai, nó nằm ở `wordpress/vhcp-chi-phi` suốt.

🔴 **ĐỪNG ĐÁNH SỐ BẢN MÀ KHÔNG HỎI CÁC NHÁNH KIA.** Cùng một plugin đang được vá ở 3–4 nhánh
song song; hai nhánh cùng đánh `3.83.0` cho hai việc khác nhau thì WordPress chỉ so được CON SỐ
và báo *"đang tải lên một phiên bản cũ"* — một câu đúng về số mà sai về việc, và cài đè bên nào
cũng mất việc của bên kia. 16/09/2026 em dính đúng bẫy này: dựng một `vhcp-cham-cong` 3.87.0 ở
nhánh này trong khi nhánh kia đã phát hành 3.87.0 với nội dung khác. Trước khi chốt số, chạy
**`php tools/test/kiem-so-ban-doc-nhat.php`** (bài ấy nằm ở nhánh rebuild, hỏi sang MỌI nhánh
trên `origin` — bài kiểm duy nhất trong kho làm việc đó).

## 4. Repo này CÔNG KHAI

Không đặt PIN, khoá, token, số tài khoản ngân hàng, khoá merchant vào mã nguồn — kể cả trong
comment hay file mẫu. Cấu hình nhạy cảm nằm trong **Option của WordPress** (DB) hoặc **NVS của
chip**. Bí mật nạp máy đi qua thẻ SD `token.txt` (đã gitignore). Thư mục `esp32_*/build/` cũng bị
chặn: file `.bin`/`.elf`/`.map` chứa bí mật ở dạng chữ đọc được.

## 5. Ghế ⇄ Sao Kê dùng chung một DB

`vhcp-ghe` và `vhcp-saoke` đọc chéo bảng của nhau nên phải cùng cài trên một site.

⚠️ **Hai plugin chuẩn hoá tên cơ sở theo hai kiểu khác nhau** — nguồn của lỗi âm thầm:

| | Hàm | "SÂN BAY CẦN THƠ" ra |
|---|---|---|
| Sao Kê | `chuan_ch()` = `kd()` + bỏ ký tự lạ | `sanbaycantho` (**thường**) |
| Ghế | `VHG_BaoCao::squash()` = `remove_accents` + hoa | `SANBAYCANTHO` (**HOA**) |

Bên Ghế đọc dữ liệu khoá-theo-tên của Sao Kê (ví dụ option `saoke_coso_ma`) thì **phải quy khoá
qua `squash()` trước khi tra**, đừng tra thẳng — tra thẳng không bao giờ khớp và hỏng *im lặng*:
bảng vẫn hiện, chỉ là mọi cơ sở đều "chưa đặt mã" và số tiền bằng 0 (lỗi 2.20.1, vá ở 2.20.2).
Đừng sửa `squash()` để cho khớp: nó đang dùng chung cho phạm vi PIN nhân viên và đối chiếu VietQR.

## 6. Sao Kê: "chưa rõ máy" và mã cửa hàng Việt QR (0.17.0)

Giao dịch khách quét **QR tĩnh** có nội dung `PaymentForOrder` — trong đó **không có tên máy**, và
webhook của cổng cũng không gửi kèm trường nào chỉ ra máy. Không có cách "đọc khéo" nào cứu được;
dữ liệu thật nằm ở một trường mà webhook không gửi.

Bản kết xuất của cổng thì có. Hai bảng, một khoá nối:

| Bảng cổng xuất ra | Cột dùng tới |
|---|---|
| **Giao dịch thanh toán** | `Mã đơn hàng` (= `ma_gd` bên mình) · **`Mã cửa hàng`** · `Mã điểm bán` |
| **Danh sách cửa hàng** | **`Mã cửa hàng`** → `Tên cửa hàng` (**chính là tên máy**) · `Tên điểm bán` (cơ sở) |

⚠️ Khoá nối là **Mã cửa hàng**, KHÔNG phải Mã điểm bán: hai bảng ghi mã điểm bán theo hai kiểu
khác nhau (`VVB635365` ở bảng giao dịch, `MC1754018340421` ở bảng cửa hàng) — nối theo nó là nối
trượt mà không báo gì.

Đường đi: nạp **Danh sách cửa hàng** một lần (lưu ở option `saoke_vqr_ch`) → mỗi lần nạp bù
**Giao dịch thanh toán** nhớ chọn cột *Mã cửa hàng* → `luu_cong()` **vá** `ma_ch` vào đúng dòng
webhook đã ghi (không thêm dòng, không đếm tiền hai lần) → `cong_may_dong()` đọc ra tên máy.

🔴 Ba cái bẫy, cả ba chỉ lộ ra khi chạy trên **file thật** (0.18.0 — anh Thắng gửi file
12/09/2026):
* **Đoán cột phải khớp NGUYÊN TỪ.** Tiêu đề tiền là `Số tiền đến (VND)`; từ khoá của cột Nội
  dung có `nd`, mà `(vnd)` chứa `nd` → bộ đoán cũ trỏ Nội dung vào **cột tiền**, và
  `may_hop_le('20000')` đẻ ra một cái **máy tên "20000"** nuốt hết tiền của kỳ.
* **`Mã đơn hàng`, không phải `Mã tham chiếu`** — khoá chống trùng bên mình là mã đơn hàng; chọn
  nhầm là mọi dòng thành "mới", **tiền đếm hai lần**.
* **Ô rỗng của file là dấu `-`**, không phải ô trắng (dòng "Vãng lai"). Không chặn thì `-` thành
  một "mã cửa hàng" nằm trong danh sách thiếu bản đồ đời đời.

Và **cột Trạng thái**: cửa nạp nay bỏ dòng có nghĩa xấu (thất bại/huỷ/hoàn/chờ). Bắt **nghĩa
xấu** chứ không bắt nghĩa tốt — cổng đổi "Thành công" thành "Success" là bản dịch, còn đòi khớp
đúng chữ tốt thì hôm nào họ đổi chữ là cả file bị bỏ sạch.

**Chỗ nạp file nằm trong thẻ gập ⚙️** — 0.17.1 thêm một dòng trong khối *Cần biết* tự chỉ đường
xuống đó khi còn giao dịch "chưa rõ máy" (gập kín một việc chưa làm là cách chắc chắn nhất để nó
không bao giờ được làm), và **in số bản ra cạnh tên công ty** ở cột trái — để câu *"bản mới lên
chưa"* trả lời được bằng mắt. ⚠️ Số bản khai **hai chỗ**: header `Version:` và hằng `VER`; bộ thử
canh chúng bằng nhau.

Bộ thử: `tools/test/kiem-saoke-ma-cua-hang.php` (67 phép, chạy lớp thật với `$wpdb` giả) và
`tools/test/kiem-saoke-doan-cot.js` (15 phép, chạy chính hàm đoán cột trong `app.html` trên
**tiêu đề thật** của hai file kết xuất). Đây là hai bộ thử **đầu tiên** của `vhcp-saoke`;
`chay-het.sh` nay cũng soát cú pháp thư mục ấy.

Đo trên hai file thật (478 giao dịch · 532 cửa hàng): **180 dòng "chưa rõ máy" → 0**, nạp lại
lần hai thì **0 dòng mới, 0đ thêm**.

🔴 **BÀI HỌC ĐẮT NHẤT (0.18.1): luật suy ra máy có BA bản sao, không phải hai.** `r_saoke_cong()`
(đường REST) và `rpc_getSaoKeCong()` (đường app thật sự gọi) là hai hàm viết riêng cho **cùng một
màn**. 0.17.0 gom luật vào `cong_may_dong()` và sửa hai nơi, sót đúng cái hàm màn hình gọi — nên
bộ thử xanh, file nạp đúng, cột `ma_ch` có dữ liệu, mà màn hình **vẫn "chưa rõ máy"**.
Phép thử cũ đếm **một chuỗi ký tự** để khẳng định "luật chỉ còn một chỗ" — bản sao thứ ba viết
bằng chuỗi khác nên không bị đếm. Nay đếm theo **lời gọi**: `cong_ten_may()` đúng **1**,
`may_hop_le()` đúng **2**, `cong_may_dong()` đúng **4**. Chép lại luật ở nơi thứ năm là bài đỏ.

Và mỗi lượt nạp file ghi lại mốc **"phủ tới thời điểm nào"** (`saoke_cong_nap_<nguồn>`, chỉ tiến
không lùi), để dòng "chưa rõ máy" **mới hơn lần nạp** tự nói ra điều đó thay vì trông như bản vá
hỏng.

## 7. Sao Kê: lọc theo kỳ lịch — tuần / tháng (0.19.0)

Thanh lọc có ô **Kỳ**: Tuần này · Tuần trước · Tháng này · Tháng trước · 7 ngày · 30 ngày.
Mọi phép tính đi qua **một** hàm `khoangLich(ky, moc)` trong `app.html`; hai nút 7/30 ngày cũ
giờ cũng gọi vào đó, không còn đường tính ngày riêng.

Khác nhau phải giữ: **kỳ lịch** (tuần/tháng) neo vào mốc lịch, **7/30 ngày** là cửa sổ trượt
lùi từ hôm nay. "Tuần này" vào Thứ Tư ≠ "7 ngày".

Ba chỗ dễ sai, đã chôn assert trong `tools/test/kiem-saoke-ky-lich.js`:

1. **Tuần bắt đầu Thứ Hai.** `getDay()` trả 0 cho Chủ nhật; lấy thẳng thì Chủ nhật bị đẩy
   thành đầu tuần, mất sáu ngày. Phải `(getDay()+6)%7`.
2. **Cuối tháng và giao năm.** Dùng `new Date(y, m, 0)` (ngày cuối tháng trước) và để JS tự lùi
   tháng — đừng cộng trừ số ngày, tháng 2 và mốc 01/01 sai ngay.
3. **Kỳ đang chạy cắt ở hôm nay.** "Tháng này" ngày 12 mà điền Đến ngày 30 thì ô ngày hiện một
   ngày chưa xảy ra. Kỳ **đã qua** thì không cắt — cắt cả "Tuần trước" là mất số liệu âm thầm.

Gõ tay ô Từ/Đến phải nhả nhãn kỳ về "Tự chọn ngày" (`cgBoKy` / `skBoKy`). Để nguyên nhãn là app
nói dối, và lần **Làm mới** sau đó nhảy về kỳ cũ, mất khoảng ngày vừa gõ.

Bài thử đếm **chỗ gọi**, không chỉ kiểm hàm — đúng bài học 0.18.0 ở §6: luật đúng mà một bản sao
không được vá thì màn hình vẫn sai.

## 8. Sao Kê: giao dịch MỚI tự biết máy (0.20.0)

0.18.1 chỉ chữa được dòng CŨ, và chỉ khi tải file kết xuất về nạp. Bản này đóng nốt đường cho
tiền mới. Bốn lỗ hổng, cả bốn đều câm:

1. **`cong_doc_obj()` không hề đọc mã cửa hàng.** Không có lấy một khoá nào trong danh sách.
   Cổng gửi mã về cũng bị vứt ngay tại cửa.
2. **Ô mã toàn chữ bị vứt im lặng.** Mã cửa hàng thật của Việt QR là `RJFSHCSXE9` — toàn chữ
   cái. `cong_doc_hang()` phân loại ô theo hình dạng: cần *cả chữ lẫn số* mới vào mã giao dịch,
   cần *khoảng trắng* mới vào nội dung/điểm bán. Ô toàn chữ rơi khỏi **mọi** nhánh. Đây là chỗ
   anh Thắng gọi là *"có vấn đề gì đó làm mất dữ liệu cửa hàng"* — dữ liệu có trong gói webhook
   mà không bao giờ tới được bảng.
3. **`cong_nhan_webhook()` là mã chết.** Hàm duy nhất đổ webhook vào bảng cổng, định nghĩa một
   lần, **không nơi nào gọi**. `r_webhook` và `r_vqr_callback` chỉ ghi vào sao kê ngân hàng. Tức
   là sau lần nạp file gần nhất, giao dịch mới không phải "chưa rõ máy" mà **không có** trên màn
   hình cổng. ⚠️ Mã chết không bao giờ đỏ — chỉ phép **đếm chỗ gọi** mới thấy.
4. **Bật (3) lên thì lòi ra nguy cơ đếm đôi:** webhook và file dựng khoá từ hai giá trị có thể
   khác nhau. Đếm thiếu ai cũng thấy; đếm gấp đôi không ai thấy.

### Cửa cuối: dò mã mà KHÔNG cần biết cổng đặt tên trường là gì

`vqr_ma_tu_payload()` lấy **mọi** giá trị vô hướng trong payload rồi hỏi bản đồ "giá trị này có
phải một mã tôi đã biết không". Không đoán tên trường, nên cổng đổi tên trường cũng không gãy —
và không còn phải đợi ai gửi cho một gói payload thật mới vá được.

Bốn chốt, mỗi chốt một assert trong `tools/test/kiem-saoke-webhook-may.php`:

- **Dài ≥ 4 ký tự** — mã hai ba ký tự thì một con số vu vơ cũng khớp.
- **Phải có chữ cái** — nếu không, một *số tiền* toàn số trùng một mã toàn số là gán nhầm tiền
  sang máy khác. Cùng loại lỗi câm mà `may_hop_le()` từng dính qua cửa đoán cột (§6).
- **Dò theo TỪNG DÒNG** với dạng `{"values":[[…]]}` — một gói có thể chứa nhiều cửa hàng; dò
  trên cả gói là tiền máy này chui sang máy kia mà bảng nhìn vẫn đầy đủ.
- **Ưu tiên mã cửa hàng hơn mã điểm bán** — mã điểm có thể dùng chung giữa vài cửa hàng.

`vqr_may_theo_ma()` tra khoá chính trước, rồi tới mã điểm bán: cổng gửi cái nào thì tuỳ giao
dịch, bản đồ `store_export` có cả hai cột nên không phải đoán.

### Chống đếm đôi khi một giao dịch về bằng hai đường

`cong_dong_trung()`: cùng nguồn **và** cùng số tiền **và** trùng `ref` hoặc `ma_gd`. Ba điều
kiện cùng lúc, không được nới — chỉ so mã thôi thì hai giao dịch khác nhau vô tình trùng mã sẽ
bị gộp làm một, **mất hẳn** một khoản, còn tệ hơn đếm đúp. `VER_TBL` lên `'4'` cho `KEY ref`.

### Bệ đỡ bài thử

`tools/test/lib/be-saoke.php` dùng chung cho mọi bài thử sao kê. Nằm trong `lib/` là cố ý:
`chay-het.sh` quét `tools/test/*.php` không đệ quy. ⚠️ `prepare()` giả phải ăn chỗ giữ **theo
đúng thứ tự xuất hiện** — bản đầu thay "%s đầu tiên" rồi "%d đầu tiên" cho mỗi tham số, gặp câu
có cả hai là tham số số nhảy vào ăn ô `%s` phía sau, bài đỏ ở chỗ mã nguồn không hề sai. Lỗi ở
bệ đỡ là loại tốn thời gian nhất: nó đổ tội cho đúng thứ mình đang thử.
