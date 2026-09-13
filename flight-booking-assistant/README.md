# Dò Vé Rẻ — so giá vé máy bay & điền sẵn hồ sơ đặt vé

Một trang web chạy hoàn toàn trong trình duyệt: gõ chặng bay một lần, máy xếp các lựa chọn từ rẻ
tới đắt, mở song song những nơi đang bán chặng đó, rồi bơm sẵn thông tin hành khách và hoá đơn VAT
vào form đặt vé của hãng. Số thẻ, CVV và OTP **cố ý** để người dùng tự nhập.

```
flight-booking-assistant/
├── index.html              trang web (mở bằng trình duyệt là chạy, không cần cài gì)
├── autofill-bookmarklet.js bản đọc được của hàm điền hộ, để sửa luật khớp ô
├── learn-bookmarklet.js    chế độ "Học form": ghi luật riêng cho đúng trang của hãng
└── automation/dat-ve.mjs   script Playwright: mở trang, điền hồ sơ, dừng trước thanh toán
```

## Chạy

Mở thẳng `index.html` bằng Chrome/Edge/Safari. Không máy chủ, không cài đặt, không tài khoản.
Hồ sơ hành khách nằm trong `localStorage` của chính trình duyệt đó.

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

Bảng giá trong trang là **giá mô phỏng**: máy dựng theo cự ly chặng (toạ độ sân bay thật), hệ số
hãng, khung giờ bay, thứ trong tuần và số ngày mua trước. Nó đủ để chạy thử luồng, không phải giá bán.

Muốn giá thật thì nối một trong hai API sau; cả hai đều cần khoá và **không gọi thẳng từ trình duyệt
được** (CORS + lộ khoá), nên phải có một proxy nhỏ:

- **Amadeus Self-Service** — `GET /v2/shopping/flight-offers`, có gói miễn phí để thử.
- **Duffel** — `POST /air/offer_requests`, dữ liệu sát vé bán thật hơn, tính phí theo giao dịch.

Proxy tối giản (Node 18+), đặt khoá trong biến môi trường, không đẩy khoá lên repo:

```js
// proxy.mjs — node proxy.mjs, rồi trong index.html gọi fetch('http://localhost:8787/offers?...')
import { createServer } from 'node:http';
const TOKEN = process.env.AMADEUS_TOKEN;           // lấy qua OAuth client_credentials
createServer(async (req, res) => {
  const u = new URL(req.url, 'http://x');
  const api = 'https://test.api.amadeus.com/v2/shopping/flight-offers'
    + `?originLocationCode=${u.searchParams.get('from')}`
    + `&destinationLocationCode=${u.searchParams.get('to')}`
    + `&departureDate=${u.searchParams.get('dep')}`
    + `&adults=${u.searchParams.get('adt') || 1}&currencyCode=VND&max=30`;
  const r = await fetch(api, { headers: { Authorization: `Bearer ${TOKEN}` } });
  res.writeHead(r.status, { 'content-type': 'application/json', 'access-control-allow-origin': '*' });
  res.end(await r.text());
}).listen(8787);
```

Trong `index.html`, thay `buildOffers(q)` bằng lời gọi proxy và ánh xạ về cùng hình dạng
`{al, code, dep, arr, mins, stops, bag, seller, price}` là bảng giá chạy bằng dữ liệu thật, phần còn
lại của trang giữ nguyên. Nhớ đổi nhãn **Giá mô phỏng** ở đầu trang.

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
