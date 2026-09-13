# Dò Vé Rẻ — so giá vé máy bay & điền sẵn hồ sơ đặt vé

Một trang web chạy hoàn toàn trong trình duyệt: gõ chặng bay một lần, máy xếp các lựa chọn từ rẻ
tới đắt, mở song song những nơi đang bán chặng đó, rồi bơm sẵn thông tin hành khách và hoá đơn VAT
vào form đặt vé của hãng. Số thẻ, CVV và OTP **cố ý** để người dùng tự nhập.

```
flight-booking-assistant/
├── index.html              trang web (mở bằng trình duyệt là chạy, không cần cài gì)
├── autofill-bookmarklet.js bản đọc được của hàm điền hộ, để sửa luật khớp ô
├── learn-bookmarklet.js    chế độ "Học form": ghi luật riêng cho đúng trang của hãng
├── server/proxy.mjs        proxy giá thật (Amadeus) + tra mã số thuế — không phụ thuộc thư viện nào
├── server/test-proxy.mjs   22 test chạy bằng Amadeus giả, không cần khoá
├── booking/server.mjs      máy chủ đơn hàng: khách trả tiền cho mình, mình đi mua vé
├── booking/public/         trang khách đặt (dat-ve.html) và trang mình xử lý (quan-tri.html)
└── booking/test-booking.mjs 31 test cả vòng đời đơn, không cần ngân hàng thật
    automation/dat-ve.mjs   script Playwright: mở trang, điền hồ sơ, dừng trước thanh toán
```

## Chạy

Chỉ xem bảng giá và điền hộ: mở thẳng `index.html` bằng Chrome/Edge/Safari. Không cài gì.
Hồ sơ hành khách nằm trong `localStorage` của chính trình duyệt đó.

Muốn giá thật hoặc bán vé qua mình thì cần Node 20 trở lên:

```
cp .env.example .env          # Windows: copy .env.example .env
```

Sửa `.env` (số tài khoản, khoá Amadeus, phí dịch vụ), rồi:

```
node server/proxy.mjs         # giá thật + tra mã số thuế   → cổng 8787
node booking/server.mjs       # đơn hàng + thu tiền          → cổng 8788
```

Hai máy chủ tự đọc `.env`, **không phải gõ biến môi trường**, nên lệnh giống nhau trên
Windows, macOS và Linux.

> **Gõ ở terminal, đừng gõ trong `node`.** Nếu dấu nhắc đang là `>` và có dòng
> *"Welcome to Node.js"* thì đó là REPL của Node — nó đọc lệnh shell thành JavaScript và báo
> `Unexpected identifier`. Gõ `.exit` (hoặc Ctrl+D) để ra, rồi chạy lệnh trên.

Trên Windows dùng PowerShell hoặc Command Prompt đều được; `cd` tới thư mục
`flight-booking-assistant` trước khi chạy.

## Tự động tới đâu

| Bước | Ai làm |
|---|---|
| Dò giá, xếp chuyến rẻ nhất, lịch giá 7 ngày | máy |
| Mở đúng chặng – ngày – số khách trên 5 trang so giá | máy |
| Điền ô tìm chuyến trên trang hãng (chặng, ngày đi/về, số khách) | máy điền, người soát |
| Điền họ tên, ngày sinh, CCCD/hộ chiếu, liên hệ, mã số thuế, địa chỉ hoá đơn | máy điền, người soát |
| Chọn thẻ, nhập số thẻ/CVV, xác thực OTP (3-D Secure) | người |
| Lưu mã đặt chỗ, đối chiếu email hoá đơn VAT | người |

Bước thanh toán không tự động được, và đó là chuyện của ngân hàng chứ không phải của công cụ:
3-D Secure yêu cầu chủ thẻ xác thực trực tiếp. Ngoài ra nhiều hãng và đại lý cấm robot đặt vé trong
điều khoản sử dụng, và chặn bằng captcha. Cách làm ở đây — điền hộ trên chính trình duyệt của người
dùng, người dùng vẫn là người bấm mua — nằm trong ranh giới đó.

## Nút "Điền hộ" (bookmarklet)

1. Khai hồ sơ trong mục **Hồ sơ điền sẵn**, bấm **Lưu hồ sơ vào máy**.
2. Kéo nút **Điền hộ** lên thanh dấu trang của trình duyệt (hoặc bấm *Sao chép mã "Điền hộ"* rồi
   tạo một dấu trang mới và dán vào ô địa chỉ của nó).
3. Mở trang đặt vé của hãng, tới form thông tin hành khách, bấm dấu trang đó.

Hàm điền chạy hai lớp:

1. **Luật riêng theo tên miền** — khớp bằng selector nên luôn đúng, kể cả trang không có nhãn tiếng
   Việt nào. Do chế độ *Học form* ghi lại (xem dưới).
2. **Luật chung** — đoán theo chữ quanh ô, hai vòng: vòng 1 chỉ đọc chữ của chính ô đó (`name`,
   `id`, `placeholder`, `aria-label`, `<label>` của nó); vòng 2 mới đọc thêm chữ nằm cùng khối và
   khối đứng trước — và chỉ lấy khối không chứa ô nhập nào khác, để không lây nhãn của ô liền trên.

Bảng luật phủ cả tiếng Việt lẫn tiếng Anh, gồm cả ô tìm chuyến (điểm đi, điểm đến, ngày đi, ngày về,
số người lớn/trẻ em/em bé) lẫn ô hành khách và hoá đơn. Ô thứ *n* của cùng một loại nhận thông tin
của khách thứ *n*, nên đoàn 3 người điền một lượt. Ô nào dính `so the`, `cvv`, `otp`, `captcha` thì
bỏ qua, cố ý.

Ô chọn sân bay thường là hộp gợi ý: hàm điền chữ vào rồi bắn `keyup` để danh sách bật lên, còn việc
bấm chọn đúng dòng vẫn là của người dùng. Bộ đếm khách kiểu nút +/− (không phải `input`/`select`)
thì máy không chạm tới được.

## Học form — khi trang của hãng không chịu khớp

Trang của Vietjet, Vietnam Airlines, Bamboo dựng bằng widget riêng, tên ô đổi theo từng bản phát
hành, nên đoán theo nhãn có lúc trật. Cách chắc chắn:

1. Kéo nút **Học form** (mục *Luật riêng theo trang*) lên thanh dấu trang.
2. Mở trang đặt vé, bấm dấu trang đó → bấm vào từng ô trên trang → chọn ô đó là gì.
3. Bấm **Chép luật**, quay lại Dò Vé Rẻ, dán khối JSON vào ô *Luật riêng theo trang*, bấm **Lưu**.

Khối JSON có dạng:

```json
{ "vietjetair.com": [ { "sel": "input[name=\"txtFrom\"]", "key": "from" } ] }
```

`key` nhận một trong: `full` `first` `last` `dob` `gender` `idNo` `nat` `phone` `email` `ctName`
`company` `tax` `invEmail` `addr` `buyer` `cardHolder` `from` `to` `depDate` `retDate` `adt` `chd` `inf`.

Luật riêng chạy trước luật chung, nên chỉ cần học vài ô khó; phần còn lại vẫn để máy đoán. Học một
lần dùng mãi, trừ khi hãng dựng lại giao diện.

## Nối giá thật

Không có proxy thì bảng giá là **giá mô phỏng**: máy dựng theo cự ly chặng (toạ độ sân bay thật),
hệ số hãng, khung giờ bay, thứ trong tuần và số ngày mua trước. Đủ để chạy thử luồng, không phải giá bán.

### Chạy thử trước, chưa cần khoá

```bash
node server/proxy.mjs --mock
```

Mở `index.html`, dán `http://localhost:8787` vào ô **Nguồn giá → Địa chỉ proxy**, bấm *Dùng nguồn này*.
Nhãn đổi sang **Giá thật** và bảng lấy dữ liệu từ proxy. Tra mã số thuế cũng chạy bằng dữ liệu mẫu.

### Giá thật từ Amadeus

1. Đăng ký ở [developers.amadeus.com](https://developers.amadeus.com) → tạo app → lấy **API Key** và **API Secret**.
   Gói Self-Service có bậc miễn phí; môi trường `test` trả dữ liệu sandbox (chặng và giá không đầy đủ),
   muốn số liệu bán thật phải chuyển sang `production` (có tính phí theo lượt gọi).
2. Chạy proxy:

Khai trong `.env`:

```ini
AMADEUS_ID=xxx
AMADEUS_SECRET=yyy
AMADEUS_ENV=test        # đổi thành production khi bán thật
```

rồi `node server/proxy.mjs`.

3. Dán địa chỉ proxy vào trang như trên.

Proxy lo giúp ba việc mà trình duyệt không làm được: **giữ khoá ở máy chủ** (gọi thẳng từ trang web là
ai xem mã nguồn cũng thấy khoá), **qua CORS**, và **đệm 5 phút** để một lần dò lại không đốt thêm lượt gọi.

Biến môi trường: `PORT` (8787), `ALLOW_ORIGIN` (`*`), `VND_RATE` (quy đổi khi Amadeus trả tiền tệ khác VND),
`AMADEUS_ENV` (`test`/`production`), `TAX_BASE` (dịch vụ tra mã số thuế).

Endpoint:

| Đường dẫn | Việc |
|---|---|
| `GET /api/offers?from=SGN&to=HAN&dep=2026-10-04&adt=1&cabin=ECONOMY&direct=1` | danh sách chuyến, đã xếp từ rẻ tới đắt |
| `GET /api/tax?mst=0312345678` | tên và địa chỉ doanh nghiệp theo mã số thuế |
| `GET /api/health` | proxy đang dùng nguồn nào |

Kiểm thử (dựng Amadeus giả đúng schema, không chạm mạng ngoài, không cần khoá):

```bash
node server/test-proxy.mjs     # 22 test: token, tham số, ánh xạ dữ liệu, cache, tra MST, lỗi
```

### Điều phải biết trước khi tin bảng giá

- **Vietjet và Vietravel phần lớn không bán qua GDS.** Amadeus là hệ thống của các hãng truyền thống;
  vé rẻ nhất chặng nội địa nhiều khi chỉ có trên trang của chính hãng. Nên bảng giá thật vẫn phải
  đối chiếu với mục *Mở song song nơi đang bán chặng này*.
- **Giá GDS là giá chào, chưa chắc là giá thanh toán.** Phí xuất vé, phí hành lý, phí thanh toán của
  từng kênh cộng vào sau.
- Môi trường `test` của Amadeus trả dữ liệu sandbox — đừng lấy con số ở đó làm giá thật.

### Gõ mã số thuế, tự điền thông tin công ty

Gõ đủ 10 số vào ô **Mã số thuế**, proxy hỏi dịch vụ tra cứu doanh nghiệp và điền **tên công ty** +
**địa chỉ** vào hồ sơ hoá đơn. Mặc định dùng `https://api.vietqr.io/v2/business/{mst}` (miễn phí);
đổi sang dịch vụ khác bằng `TAX_BASE`. Kết quả nhớ trong 24 giờ. Máy chỉ điền vào ô đang trống —
anh đã tự gõ thì nó không đè lên.

## Bán qua mình — khách trả tiền cho mình, mình đi mua vé

```bash
BANK_ID=970436 BANK_ACCOUNT=0071000123456 BANK_NAME="CONG TY TNHH K&H" BANK_LABEL=Vietcombank \
ADMIN_TOKEN=$(openssl rand -base64 12) WEBHOOK_SECRET=$(openssl rand -base64 12) \
FEE_PCT=3 FEE_MIN=50000 HOLD_MINUTES=30 node booking/server.mjs
```

- Khách đặt: `http://localhost:8788/dat-ve.html` — mở từ nút **Đặt qua mình** trên bảng giá, chuyến và
  giá đi kèm sẵn trên đường dẫn. Khai địa chỉ máy chủ này vào ô *Máy chủ đơn hàng* ở đầu trang chính
  thì nút đó mới hiện.
- Mình xử lý: `http://localhost:8788/quan-tri.html` — dán `ADMIN_TOKEN` để vào.

Vòng đời một đơn:

| Trạng thái | Ai làm gì |
|---|---|
| `cho_thanh_toan` | khách nhận mã QR VietQR, số tiền và nội dung (mã đơn) nằm sẵn trong mã |
| `da_nhan_tien` | webhook ngân hàng khớp mã đơn trong nội dung chuyển khoản, hoặc mình bấm tay |
| `da_xuat_ve` | mình mua vé xong, nhập mã đặt chỗ (và giá mua vào để tính chênh lệch) |
| `hoan_tien` / `huy` | mua hụt hoặc khách đổi ý |
| `het_han` | quá `HOLD_MINUTES` mà chưa chuyển khoản — giá thôi được giữ |

Từ trang quản trị, mỗi đơn có nút **Điền hộ** mang đúng thông tin khách của đơn đó: kéo lên thanh dấu
trang, mở trang hãng, bấm một cái là form đầy. Mua xong bấm **Mã đặt chỗ**, đơn đóng lại và khách thấy
mã ngay trên trang của họ.

### Tiền về thì máy tự biết

Nội dung chuyển khoản chính là mã đơn (`DVR26090001`). Đăng ký một dịch vụ báo biến động số dư
(SePay, Casso…) trỏ webhook về `POST /api/webhook/bank`, đặt `WEBHOOK_SECRET` trùng với khoá của họ.
Máy dò mã đơn trong nội dung kể cả khi ngân hàng chèn thêm chữ hay dấu cách, đối chiếu số tiền, thiếu
bao nhiêu ghi rõ bấy nhiêu, và tiền về lần hai không ghi đè đơn đã xử lý. Chưa dùng dịch vụ nào thì
bấm tay nút **Đã nhận tiền**.

### Những chỗ dễ mất tiền

- **Giá đổi giữa lúc khách trả và lúc mình mua.** `HOLD_MINUTES` giữ giá, hết giờ đơn tự thành
  `het_han`; tiền về sau hạn vẫn nhận nhưng đơn gắn cờ *trả sau hạn giữ giá* để mình quyết mua tiếp
  hay hoàn. Đặt `FEE_PCT` đủ bù dao động là hợp lý.
- **Mua hụt phải hoàn nhanh.** Có nút riêng và ghi vào nhật ký đơn.
- **Không bao giờ nhận số thẻ.** Khách chuyển khoản; không có chỗ nào trong hệ thống lưu thẻ, nên
  không phải lo PCI-DSS.

### Sau này: quẹt thẻ ngay trên web

Có pháp nhân rồi thì mở cổng VNPay/MoMo/ZaloPay được — khách trả bằng thẻ hoặc ví ngay trên trang,
đơn tự sang `da_nhan_tien` khi cổng báo về, phần còn lại của luồng giữ nguyên. Muốn tự xuất vé luôn
(không phải vào trang hãng bấm tay) thì cần [Duffel](https://duffel.com) hoặc Amadeus Enterprise /
Sabre qua đại lý — lúc đó mình gánh cả hoàn/đổi vé và tranh chấp thẻ.

### Kiểm thử

```bash
node booking/test-booking.mjs   # 31 test: tạo đơn, chặn đơn sai, webhook khớp mã,
                                # thiếu tiền, quản trị, hoàn tiền, hết hạn giữ giá
```

## Script tự đặt (Playwright)

`automation/dat-ve.mjs` mở trình duyệt thật, vào đúng trang chặng đã chọn, dừng cho người chọn
chuyến, điền hồ sơ, rồi dừng hẳn ở bước thanh toán.

```bash
npm i playwright && npx playwright install chromium
cp profile.example.json profile.json   # hoặc bấm "Sao chép hồ sơ JSON" trên trang và dán vào
node automation/dat-ve.mjs "https://www.traveloka.com/vi-VN/flight/fullsearch?..."
```

## Riêng tư

Không có máy chủ nào trong đường đi. Hồ sơ nằm ở `localStorage`; bookmarklet mang dữ liệu đi theo
chính nó; script Playwright đọc file `profile.json` trên máy. Số thẻ và CVV không có chỗ nhập ở bất
kỳ đâu trong bộ này — để trình duyệt hoặc trình quản lý mật khẩu giữ.
