# Đối soát thu hộ & lập danh sách xuất hoá đơn VAT

Gộp sao kê từ các cổng thanh toán, quy về **điểm xuất hoá đơn**, tách VAT, rồi sinh
file Excel để nhập Misa.

Có hai cách dùng, chung một bộ quy tắc:

| Cách dùng | Ở đâu | Hợp với |
|---|---|---|
| **Plugin WordPress** | `wordpress/` | Site đã chạy WordPress — cài như plugin bình thường |
| **Trang web tĩnh** | `web/` | Host thường, không có WordPress |
| **Dòng lệnh** | `vatrec/` — Python | Chạy tự động, xử lý hàng loạt, đưa vào pipeline |

Cả ba dùng chung một bộ quy tắc và chung luôn phần giao diện trong `web/`.

> **Dữ liệu không nằm trong repo này.** Repo firmware là repo công khai, còn sao kê
> chứa tên khách, số điện thoại và số tài khoản. `.gitignore` đã chặn mọi file
> `.xlsx` / `.xls` trong thư mục này — đừng gỡ.

---

## 1. Cài làm plugin WordPress

Cách này hợp nhất nếu site đã chạy WordPress: công cụ nằm luôn trong trang quản
trị, và chỉ tài khoản có quyền `manage_options` mới mở được.

### Đóng gói

```bash
cd tools/doi-soat-vat/wordpress
./dong-goi.sh              # sinh ra doi-soat-vat.zip
```

Script chép `web/` vào plugin lúc đóng gói, nên giao diện chỉ có một bản duy nhất
trong repo, không sợ hai bản lệch nhau.

### Cài

Plugin → Thêm Plugin → **Tải Plugin lên** → chọn `doi-soat-vat.zip` → Kích hoạt.
Sau đó mở mục **Đối soát VAT** ở menu bên trái.

> Zip của trang tĩnh (`web/`) **không** cài được bằng đường này — WordPress sẽ báo
> "Không tìm thấy gói mở rộng hợp lệ". Phải dùng zip do `dong-goi.sh` sinh ra.

### Địa chỉ web của công cụ

Plugin mở một địa chỉ gọn ngay trên tên miền:

```
https://tenmien.com/doi-soat-vat/
```

Địa chỉ này hiện ngay đầu trang quản trị kèm nút sao chép, gửi cho ai cũng được.

**Mặc định phải đăng nhập WordPress mới mở được.** Muốn gửi cho người ngoài thì
tick "Cho người chưa đăng nhập dùng địa chỉ này" rồi Lưu. Sao kê vẫn không rời
khỏi máy người dùng trong cả hai trường hợp — điều thay đổi chỉ là *ai được dùng
công cụ*, không phải dữ liệu đi đâu.

Nếu địa chỉ báo 404: vào Cài đặt → Đường dẫn tĩnh, bấm Lưu một lần để WordPress
dựng lại bảng đường dẫn. Trang quản trị cũng in kèm đường dẫn dự phòng trỏ thẳng
vào file, dùng được ngay không cần rewrite.

### Cập nhật plugin: nhớ đổi số phiên bản

Giao diện là file tĩnh nên trình duyệt cache rất chặt. Sửa `web/js/core.js` mà
không đổi số phiên bản thì người dùng cập nhật plugin xong **vẫn thấy bản cũ** —
đúng như chưa sửa gì.

Số phiên bản phải khớp ở **ba chỗ**:

| Chỗ | Ví dụ |
|---|---|
| `wordpress/doi-soat-vat/doi-soat-vat.php`, dòng `Version:` | `1.2.0` |
| cùng file, hằng `DSVAT_VERSION` | `'1.2.0'` |
| `web/index.html`, chuỗi `?v=` ở thẻ `<script src="js/app.js">` | `?v=1.2.0` |

`dong-goi.sh` kiểm tra ba chỗ này và **từ chối đóng gói** nếu lệch.

Chỉ cần đổi `?v=` trong `index.html`: `app.js` đọc lại số đó từ chính thẻ script
của nó rồi truyền cho worker, worker truyền tiếp cho `core.js`, `report.js` và
SheetJS. Một chỗ đổi, cả bộ file được tải mới.

Số phiên bản đang chạy hiện ở cuối trang công cụ và ở đầu trang quản trị — kiểm
tra ở đó khi thấy cập nhật "không có tác dụng".

### Plugin làm gì

Chỉ ba việc: thêm một mục vào menu quản trị, mở địa chỉ web ở trên, và nhớ xem
địa chỉ đó có cho người ngoài vào hay không. Không có endpoint nào nhận dữ liệu,
không tạo bảng nào trong cơ sở dữ liệu.

Trang công khai được in ra từ chính `web/index.html`, chỉ chèn thêm một thẻ
`<base>` để mọi đường dẫn tương đối (CSS, JS, Web Worker) trỏ về thư mục plugin —
nhờ vậy giao diện chỉ có một bản duy nhất, không phải chép lại vào PHP.

Trong trang quản trị thì công cụ được nhúng bằng iframe, vì giao diện của nó có
bộ CSS riêng, in thẳng vào trang quản trị thì hai bên đè nhau.

---

## 2. Dùng trang web tĩnh

### Chạy thử tại máy

```bash
cd tools/doi-soat-vat/web
python3 -m http.server 8000
# mở http://localhost:8000
```

Phải chạy qua HTTP (`http://`), không mở thẳng file bằng `file://` — Web Worker
không hoạt động với giao thức `file://`.

### Đưa lên web host

Chép **nguyên thư mục `web/`** lên host. Đây là trang tĩnh thuần: không cần PHP,
Node hay cơ sở dữ liệu. Nginx, Apache, GitHub Pages, Netlify, hosting cPanel đều chạy.

**Toàn bộ xử lý diễn ra trong trình duyệt người dùng.** File sao kê không được tải
lên máy chủ; trang không gửi bất cứ yêu cầu mạng nào ra ngoài sau khi tải xong.
Điều đó có nghĩa là:

- Không cần lo cấu hình bảo mật cho dữ liệu nhạy cảm ở phía máy chủ.
- Máy chủ không lưu gì cả, không có gì để rò rỉ.
- Nhưng: **ai vào được URL cũng dùng được công cụ**. Nếu không muốn công khai,
  đặt sau xác thực HTTP cơ bản hoặc trong mạng nội bộ. Bản thân trang không có
  đăng nhập.

Trang đã đặt `<meta name="robots" content="noindex, nofollow">` nên máy tìm kiếm
không lập chỉ mục, nhưng đó không phải là biện pháp bảo mật.

### Các bước trên trang

1. **Kỳ báo cáo** — từ ngày, đến ngày, ngày ghi trên hoá đơn, thuế suất, và
   **kiểu xuất hoá đơn** (xem mục 4.5). Ô "Lọc pháp nhân" để trống thì lấy tất cả.
   Nút *Đặt kỳ = hôm qua* dành cho nhịp chạy hằng ngày.
2. **Thả file sao kê** — thả cả bộ vào một lượt. Trang tự đọc từng sheet và nhận
   ra sheet nào là sao kê của cổng nào, sheet nào là bảng danh mục.
3. **Kiểm lại bảng sheet** — bỏ chọn sheet của kỳ cũ. Cột *Tên luồng tiền* quyết
   định tên sheet pivot trong file kết quả.
4. **Danh mục bổ sung** (không bắt buộc) — khai mã điểm bán chưa có trong danh mục.
   Lưu trong trình duyệt, kỳ sau mở lại vẫn còn.
5. **Tổng hợp** rồi **tải file `.xlsx`**.

Ô cài đặt ở bước 1 cũng được nhớ lại cho lần sau.

---

## 3. Dùng dòng lệnh

```bash
cd tools/doi-soat-vat
pip install openpyxl python-calamine

# gộp cả kỳ — mỗi điểm một hoá đơn
python3 -m vatrec --config config/kh989.json --out KH989_thang8.xlsx

# theo từng ngày — mỗi điểm mỗi ngày một dòng
python3 -m vatrec --config config/kh989.json --out KH989_thang8.xlsx --theo-ngay
```

Chạy cho đúng một ngày thì đặt `ky_tu` bằng `ky_den`:

```bash
python3 -m vatrec --config config/kh989-hom-qua.json --out KH989_2026-08-25.xlsx
```

`python-calamine` chỉ cần khi phải đọc `.xls`. File `.xls` do NPOI sinh ra (sao kê
Zalo) không đọc được bằng `xlrd` hay LibreOffice, chỉ calamine đọc được.

### File cấu hình

Xem mẫu trong `config/` (`*.mau.json`). Chép ra thành `config/kh989.json` rồi sửa
đường dẫn — `.gitignore` chặn file cấu hình thật để đường dẫn nội bộ không lọt lên
repo công khai. Đường dẫn trong `file` tính tương đối so với chính file cấu hình.

```jsonc
{
  "co_so": "KH989",
  "ky_tu": "2026-08-01",
  "ky_den": "2026-08-31",
  "ngay_hoa_don": "2026-08-31",
  "vat_rate": 0.08,
  "theo_ngay": false,         // true = mỗi điểm mỗi ngày một dòng hoá đơn
  "phap_nhan": null,          // đặt tên pháp nhân để chỉ lấy điểm của pháp nhân đó

  "sources": [                // sao kê — mỗi sheet một mục
    { "kind": "qr",    "file": "…/KH989.xlsx", "sheet": "QR TK MB268", "stream": "QR Posh MB268" },
    { "kind": "payoo", "file": "…/Payoo.xlsx", "sheet": "Dữ liệu Payoo cơ sở KH989" },
    { "kind": "vnpay", "file": "…/VNPay.xlsx", "sheet": "dữ liệu VNpay cơ sở KH989" },
    { "kind": "zalo",  "file": "…/zalo.xls",   "sheet": "Đối soát zalo" }
  ],

  "catalogs": [               // danh mục điểm
    { "kind": "store_code", "file": "…/KH989.xlsx", "sheet": "chia theo mã cửa hàng KH989" },
    { "kind": "vnpay", "file": "…/VNPay.xlsx", "sheet": "Danh mục điểm",     "code_column": "Mã điểm thu" },
    { "kind": "payoo", "file": "…/Payoo.xlsx", "sheet": "Danh mục tên điểm", "code_column": "Chi nhánh" }
  ]
}
```

`stream` là tên luồng tiền hiện trên báo cáo; bỏ trống thì lấy tên sheet.

---

## 4. Quy tắc tính

Phần này là cái đáng đọc kỹ nhất. Mọi con số dưới đây đã được **tính lại từ sao kê
thô rồi so với chính file VAT mẫu KH705 / KH989**.

### 4.1 Đọc gì từ mỗi cổng

| Cổng | Lọc | Cột tiền | Mã điểm bán | Ngày |
|---|---|---|---|---|
| **QR VietQR** | `Trạng thái = Thành công` | `Số tiền đến (VND)` | `Mã cửa hàng` | `Thời gian TT` |
| **Payoo** | (lấy hết) | `Số tiền thanh toán (₫)` | `Cửa hàng` | `Ngày giao dịch` |
| **VNPay** | `Trạng thái = Thành công` | `Số tiền sau KM` | `Mã điểm thu` | `Thời gian GD` |
| **Zalo Mini App** | `Đã thanh toán`, bỏ đơn `Đã hủy` | `Tổng tiền phải trả` | cắt từ `Tên sản phẩm` | `Ngày đặt hàng` |
| **MoMo** | `Trạng thái = Thành công` | `Số tiền` | `Mã cửa hàng` | `Thời gian` |

Hai lựa chọn dễ nhầm, đã kiểm chứng bằng số:

- **Payoo lấy "Số tiền thanh toán", không lấy "Thành tiền".** "Thành tiền" là số
  sau khi trừ phí Payoo; hoá đơn phải xuất theo số khách trả.
  Kiểm chứng: tổng khớp tuyệt đối 311.286.008 với sheet `Danh mục tên điểm`.
- **VNPay lấy "Số tiền sau KM", không lấy "Số tiền hạch toán thu hộ".** Cột hạch
  toán lệch vì có giao dịch VNPay trả sang kỳ sau (294.509.000 so với 460.763.000).
  Kiểm chứng: "Số tiền sau KM" khớp tuyệt đối 460.763.000 với sheet `Danh mục điểm`.

**MoMo có hai khối cạnh nhau trên cùng một sheet.** Sheet dữ liệu MoMo đặt một
khối `MS.…` tổng hợp bên trái và khối xuất thẳng từ cổng bên phải. Vì công cụ dò
tiêu đề theo tên cột nên tự bắt đúng khối xuất thẳng — khối kia không có bộ cột
này. Đừng lấy `MS.Total Amount`: cột đó chỉ điền một phần.

Danh mục MoMo nằm ở sheet tổng hợp theo ngày (`Tổng Momo T8`), khoá là
`Mã cửa hàng` → `Tên điểm xuất hóa đơn`. Sheet đó **xếp nhiều bảng nối nhau**:
doanh thu, rồi phí MoMo, rồi dòng tổng. Chỉ dòng vừa có mã cửa hàng vừa có tên
điểm mới là dòng danh mục — bảng phí để trống tên điểm, dòng tổng để trống mã.
Bỏ sót chi tiết này là cộng cả tiền phí vào doanh thu.

Kiểm chứng: khớp tuyệt đối 1.042.790.000 và **không lệch một ô nào** trên 17 điểm
× 31 ngày.

**Zalo là trường hợp đặc biệt.** File Zalo không có mã điểm bán. Mỗi dòng là một
*dòng sản phẩm* của đơn, và tên gian hàng nằm ở đầu tên sản phẩm
(`VINCOM TIMES CITY - Vé nhà ma`). Nên công cụ gom về đơn (một mã đơn tính một lần)
rồi cắt lấy phần trước dấu `-`. Tên gian hàng đó **chưa** tương ứng với điểm xuất
hoá đơn — phải tự khai ánh xạ ở bước 4 của trang web (hoặc phần danh mục bổ sung),
vì trong bộ file gốc việc gán này làm bằng tay.

### 4.2 Quy về điểm xuất hoá đơn

Mỗi cổng có bảng quy đổi riêng:

| Kênh | Sheet danh mục | Khoá tra cứu |
|---|---|---|
| QR | `chia theo mã cửa hàng` | `Mã cửa hàng` → `mã điểm xuất hóa đơn` |
| VNPay | `Danh mục điểm` | `Mã điểm thu` |
| Payoo | `Danh mục tên điểm` | `Chi nhánh` |
| MoMo | `Tổng Momo T8` | `Mã cửa hàng` → `Tên điểm xuất hóa đơn` |
| Zalo | (không có — phải tự khai) | tên gian hàng |

Ngoài ra, sheet nào chỉ có thông tin điểm mà không kèm mã của cổng nào (ví dụ
`MOMO KH cũ (KH989)`) được nhận là **danh mục điểm chung**: nó không tạo khoá tra
cứu mới, chỉ bù `Mã điểm trên misa thuế`, `Khu vực`, `Dịch vụ` và `Pháp nhân` cho
những điểm mà danh mục của cổng bỏ trống. Bản ghi đầy đủ hơn thắng.

Việc bù này cũng quyết định **lọc pháp nhân**: danh mục MoMo ghi pháp nhân tắt
(`KH Mới TK CTy`), còn bảng thông tin điểm mới ghi chuẩn (`KH mới`). Công cụ lọc
theo bản đã bù, tức đúng bản hiển thị trên báo cáo.

Doanh thu được cộng theo **(điểm xuất hoá đơn, luồng tiền, ngày)**.

Kiểm chứng trên dữ liệu thật: tính lại từ sao kê rồi so từng ô với các khối
"Tổng QR …" của sheet `chia theo mã cửa hàng`:

| Sheet sao kê | Khối trong file mẫu | Kết quả |
|---|---|---|
| `QR TK MB268` (KH989) | Tổng QR POSH 268MB | **8.370 ô, khớp 100%** |
| `QR Posh 30.03.26 HN 989` | Tổng QR Posh HN | tổng khớp, lệch 21 ô |
| `QR Posh 30.03.26 HN 705` | Tổng QR Posh HN | lệch 70 ô / 84.000+ ô |
| `QR JP 30.03.26 HN 705` | Tổng QR JP HN | lệch 15 ô |
| `QR + JP MB MỚI` (705) | TỔNG VỀ TK MB | lệch 3 ô |

Các ô lệch là lỗi dán tay trong file gốc, không phải lệch quy tắc: tổng cột vẫn
đúng, chỉ là tiền bị gán nhầm sang mã cửa hàng bên cạnh trong cùng một cột ngày.

### 4.3 Tách VAT

```
Có VAT   = tổng doanh thu của điểm trong kỳ
Chưa VAT = làm tròn(Có VAT / 1,08)
VAT      = Có VAT − Chưa VAT
```

VAT lấy **phần dư** chứ không tính riêng, để `Chưa VAT + VAT` luôn đúng bằng
`Có VAT` — tính riêng rồi làm tròn hai lần sẽ lệch 1 đồng ở một số giá trị.

Kiểm chứng với dòng đầu file mẫu: 37.750.000 → 34.953.704 + 2.796.296. Khớp.

### 4.4 Xuất theo kỳ hay theo ngày

Sao kê về theo ngày, nên công cụ có hai kiểu xuất. Cả hai dùng đúng một dữ liệu
đã tổng hợp, chỉ khác cách gộp dòng:

| Kiểu | Một dòng hoá đơn là | Ngày hoá đơn | Dùng khi |
|---|---|---|---|
| **Gộp cả kỳ** (mặc định) | một điểm, cả kỳ | ngày đã chọn ở ô *Ngày hoá đơn* | Chốt cuối tháng |
| **Theo từng ngày** | một điểm, một ngày | ngày phát sinh doanh thu | Xuất hoá đơn hằng ngày, hoặc soi lại doanh thu từng ngày |

Ví dụ thật (KH989, tháng 8): gộp cả kỳ ra **9 dòng**, theo ngày ra **160 dòng**,
tổng có VAT của cả hai đều đúng **910.199.008 đ**.

Lưu ý một chi tiết: tổng *Chưa VAT* giữa hai kiểu có thể lệch vài đồng
(842.776.860 so với 842.776.856 ở ví dụ trên). Đó là do làm tròn trên từng dòng —
9 dòng thì làm tròn 9 lần, 160 dòng thì 160 lần. Tổng **Có VAT** thì luôn khớp
tuyệt đối, vì VAT lấy phần dư (mục 3.3).

Ngoài ra file kết quả luôn có sheet **`Tổng theo ngày`** dù chọn kiểu nào: mỗi ngày
trong kỳ một dòng, kèm thứ trong tuần, số điểm phát sinh và tách theo từng luồng
tiền. Ngày nào bằng 0 giữa kỳ thường là dấu hiệu thiếu file sao kê của ngày đó.

### 4.5 Những gì công cụ **không** tự cộng vào

Đây là phần quan trọng nhất khi đọc kết quả. Bốn nhóm dưới đây bị tách ra và báo
riêng ở sheet `Đối soát`, **không** cộng vào hoá đơn nào:

| Nhóm | Nghĩa là gì | Phải làm gì |
|---|---|---|
| **Mã điểm bán chưa có trong danh mục** | Có tiền thật nhưng không biết xuất hoá đơn cho điểm nào | Khai vào danh mục rồi chạy lại |
| **Giao dịch vãng lai** | Tiền chuyển vào tài khoản nhưng ô mã cửa hàng trống hoặc là `-` | Tra tay xem của điểm nào |
| **Giao dịch ngoài kỳ** | Ngày nằm ngoài kỳ báo cáo | Bình thường — kiểm lại kỳ nếu số lớn bất thường |
| **Giao dịch trùng mã** | Cùng mã giao dịch xuất hiện ở hai sheet | Bỏ chọn sheet của kỳ cũ |

Nguyên tắc: **thà báo thiếu còn hơn cộng bừa.** Tiền không tra được điểm thì không
bao giờ bị nhét vào một hoá đơn nào đó cho tròn số.

Chống trùng chạy theo cặp `(cổng, mã giao dịch)`. Hai cổng khác nhau tình cờ trùng
mã vẫn được coi là hai giao dịch thật.

---

## 5. File kết quả

| Sheet | Nội dung |
|---|---|
| `DS xuất HĐ MTT` | Danh sách hoá đơn để nhập Misa — đúng thứ tự cột của file mẫu. Cột *Số HĐ* để trống vì số do Misa cấp |
| `kê ds xuất HĐ MTT` | Bản kê chi tiết, thêm cột tách theo từng luồng tiền để tra ngược |
| Một sheet cho mỗi luồng tiền | Pivot điểm xuất hoá đơn × ngày (luồng không phát sinh thì không tạo sheet) |
| `Lọc Payoo` | **Bảng lọc dữ liệu Payoo** — mã cửa hàng × hình thức thanh toán × ngày, ba khối cột: số xuất hoá đơn, phí Payoo thu, tiền Payoo phải trả về TK |
| `Đối soát theo file` | Mỗi file đầu vào một dòng: luồng đọc được, số GD, tiền vào hoá đơn, và phần bị tách riêng của chính file đó |
| `F1 …`, `F2 …` | **Một tab cho mỗi file** — điểm xuất hoá đơn × ngày, chỉ tính riêng file đó |
| `Tổng theo ngày` | Mỗi ngày trong kỳ một dòng: tổng, chưa VAT, VAT, số điểm phát sinh, tách theo luồng |
| `Đối soát` | Tổng theo luồng, số hoá đơn, và **toàn bộ cảnh báo ở mục 3.5** |

### 4.x. Đề xuất điểm cho cửa hàng mới

Cửa hàng mới phát sinh liên tục, và mỗi cổng đặt mã một kiểu: Payoo ghi
`DVGIAITRIKH_FZ_IPH`, Zalo ghi thẳng tên gian hàng kèm emoji (`🌸 THE LOOP (IPH)`).
Mã nào chưa có trong danh mục thì bảng *Mã điểm bán chưa có trong danh mục* hiện
kèm một ô chọn **đề xuất điểm xuất hoá đơn**, xếp theo mức khớp.

Cách chấm điểm:

1. **Mã đã khai ở cổng khác** → chắc chắn, không phải phỏng đoán.
2. Còn lại thì so chữ: bỏ dấu và emoji, cắt thành từ, rồi
   - cân mỗi từ theo **độ hiếm trong danh mục** — từ có ở hầu hết các điểm
     (`an`, `go`) gần như không tính, từ chỉ có ở một điểm (`vivocity`) gần như
     là chữ ký;
   - bỏ hẳn từ không mang danh tính điểm: mô tả khuyến mãi (`combo`, `sale`,
     `vé`) và **loại hình mặt bằng** (`mall`, `mart`, `plaza`) — nếu để lại,
     `AEON MALL HUẾ` khớp nhầm sang `FUNFEST AEON MALL BÌNH TÂN` chỉ vì chung
     chữ "mall", trong khi "Huế" mới là chỗ phân biệt;
   - bỏ tiền tố chung của cả lô mã (`DVGIAITRIKH` của Payoo);
   - nhân thêm **hệ số phủ**: khớp được càng ít từ trong mã thì càng bớt chắc.

Mức chắc hiện thành chữ (*gần chắc chắn* / *nhiều khả năng* / *có thể*) chứ không
phải con số lẻ. Dưới ngưỡng thì **không đề xuất gì** — `COMBO MUA 4 TẶNG 2` không
phải địa danh nên để trống, thà thế còn hơn đoán bừa.

**Đề xuất không bao giờ tự áp vào.** Bấm *Khai vào danh mục* mới đưa xuống bảng ở
bước 4, điền sẵn cả mã misa / khu vực / dịch vụ / pháp nhân của điểm được chọn —
sửa hoặc xoá thoải mái trước khi chạy lại.

**Hai mã cho một cửa hàng:** Payoo ghi cả `Cửa hàng` (`DVGIAITRIKH_FZ_IPH`) lẫn
`Mã cửa hàng` (`00003182`). Danh mục khai theo mã nào cũng tra ra được — mã chính
không thấy thì tra tiếp mã phụ.

### 5.0. Bảng lọc Payoo

Sao kê Payoo tải theo ngày hay theo tháng đều là một danh sách giao dịch thô. Tab
`Lọc Payoo` (và bảng cùng tên trên trang web) gom lại đúng dạng đang dùng để xuất
hoá đơn:

```
STT · Tên điểm xuất hóa đơn · Mã điểm trên misa thuế · Chi nhánh · Hình thức thanh toán
   || <mỗi ngày một cột> · Tổng xuất hóa đơn
   || <mỗi ngày một cột> · Tổng tiền phí
   || <mỗi ngày một cột> · Tổng tiền Payoo phải trả
```

Mỗi cửa hàng tách hai dòng `Quét mã QR` và `Thẻ`; STT gộp ô cho cả hai dòng. Thứ
tự cửa hàng giữ đúng thứ tự gặp trong file gốc, không xếp lại theo bảng chữ cái.
Khối thứ ba = khối thứ nhất trừ khối phí, tức số Payoo thực chuyển về tài khoản.

Bảng dựng thẳng từ giao dịch thô, **không đi qua bước tra danh mục**, nên chạy
được cả khi file Payoo chỉ có mỗi sheet dữ liệu gốc — lúc đó cột tên điểm và mã
misa để trống, còn số tiền vẫn đủ.

Trên trang web, bảng mở ra ở **ngày mới nhất có dữ liệu**; chọn lại ô *Ngày* để
xem ngày khác, hoặc `— Cả kỳ —` để cộng gộp. Ô *Khối số* đổi giữa số xuất hoá
đơn, phí, và tiền Payoo phải trả.

**Chống trùng:** cột `Mã giao dịch Payoo` trong sao kê thực tế **bỏ trống toàn
bộ**, nên trước đây dán nhầm sheet hai lần là Payoo cộng đôi mà không cảnh báo
gì. Nay mã tham chiếu ghép theo thứ tự `Mã giao dịch Payoo` → `Mã QR` →
`Mã chuẩn chi` → `Số tham chiếu`: `Mã QR` phủ giao dịch quét mã, `Mã chuẩn chi`
phủ giao dịch thẻ, ghép lại phủ đủ 100% số dòng và không mã nào trùng nhau.

### 5.1. Cột của hai sheet chính

Tên và thứ tự cột chép **đúng nguyên văn** file VAT mẫu, để dán thẳng vào quy
trình cũ mà không phải sắp lại cột.

`DS xuất HĐ MTT` — 22 cột:

```
STT · Ngày HĐ · Số HĐ · Tên khách hàng · Mã số thuế khách hàng · Địa chỉ khách hàng ·
Email nhận hóa đơn · Nội dung xuất hóa đơn · Số lượng · ĐVT · Thành tiền ·
Chưa VAT · VAT · Có VAT · Khu vực · Dịch vụ · Số hợp đồng (nếu có) ·
Mã điểm nội bộ · Mã điểm misa · Ghi chú · (trống) · Địa chỉ
```

`kê ds xuất HĐ MTT` — 21 cột gốc, rồi `Pháp nhân`, rồi mỗi luồng tiền một cột:

```
STT · Tháng · Ngày HĐ · lọc trùng · Số HĐ · Tên khách hàng · Mã số thuế khách hàng ·
Địa chỉ khách hàng · Email nhận hóa đơn · Nội dung hóa đơn · Tổng TT HĐ htoan Misa ·
đã xuất hóa đơn · Khu vực · Dịch vụ · Số hợp đồng · Mã đối tượng nội bộ ·
Mã điểm ghi chú HT Misa · Mã NCC HT Misa · ghi chú · Dịch vụ thu hộ ·
Những lưu ý khác (thời hạn hợp đồng, …)   ||   Pháp nhân · <từng luồng tiền>
```

**Hai chỗ tên cột không khớp nội dung — giữ nguyên theo file gốc, đừng sửa:**

| Cột | Thật ra chứa |
|---|---|
| `Số hợp đồng (nếu có)` / `Số hợp đồng` | Hình thức hợp tác (`CSE`, …) |
| `Mã điểm nội bộ` / `Mã đối tượng nội bộ` | Tên điểm xuất hoá đơn |

Cột `ghi chú` của bản kê tự điền kỳ dạng `t08/26`; cột `Dịch vụ thu hộ` liệt kê
các luồng tiền đã cộng vào dòng đó. Cột `Số HĐ` luôn để trống vì số do Misa cấp.

Bộ cột này được khoá cứng trong cả hai bộ test (`tests/test_vatrec.py` và
`tests/core.test.js`), và test còn so hai bản lõi Python / JavaScript với nhau —
đổi tên cột ở một bên mà quên bên kia là test đỏ ngay.

Dòng `TỔNG` cuối mỗi bảng dùng công thức `SUM()`, nên sửa số trong file thì tổng
tự cập nhật.

**Luôn mở sheet `Đối soát` trước.** Dòng "Lệch so với tổng luồng tiền" phải bằng 0.

Sau đó xem **`Đối soát theo file`**: tổng cột *Vào hoá đơn* của các file phải đúng
bằng tổng chung. File nào có số ở cột *Chưa có danh mục*, *Vãng lai* hay *Trùng mã*
thì mở tab `F…` của file đó ra soi — tab đó chỉ tính riêng file, so thẳng được với
file gốc mà không phải bóc tách gì thêm.

Số tổng chỉ đáng tin khi từng file đã đúng, nên thứ tự kiểm là: từng tab `F…` →
`Đối soát theo file` → mới tới `DS xuất HĐ MTT`.

---

## 6. Chạy test

```bash
cd tools/doi-soat-vat
python3 tests/test_vatrec.py    # lõi Python
node tests/core.test.js         # lõi JavaScript
```

Hai bộ test kiểm cùng một danh sách quy tắc và cùng những mốc số. Sửa quy tắc ở một
bên thì phải sửa cả bên kia, nếu không test sẽ lệch nhau.

---

## 7. Cấu trúc thư mục

```
tools/doi-soat-vat/
├── README.md
├── config/                 cấu hình mẫu cho bản dòng lệnh
├── tests/
│   ├── test_vatrec.py      test lõi Python
│   └── core.test.js        test lõi JavaScript
├── vatrec/                 bản Python
│   ├── normalize.py        chuẩn hoá ngày, tiền, chữ
│   ├── excel.py            đọc .xlsx/.xls, dò dòng tiêu đề
│   ├── catalog.py          danh mục điểm
│   ├── sources/            reader từng cổng
│   ├── aggregate.py        gom điểm × ngày × luồng
│   ├── invoices.py         tách VAT
│   ├── report.py           ghi .xlsx
│   ├── config.py           đọc file cấu hình
│   ├── pipeline.py         ghép các bước
│   └── cli.py              dòng lệnh
├── wordpress/              bản plugin WordPress
│   ├── dong-goi.sh         script đóng gói ra file .zip cài được
│   └── doi-soat-vat/       phần riêng của plugin (php + readme)
└── web/                    trang web tĩnh — chép nguyên thư mục này lên host
    ├── index.html
    ├── style.css
    ├── js/
    │   ├── core.js         lõi (bản JS, cùng quy tắc với vatrec/)
    │   ├── report.js       dựng .xlsx
    │   ├── worker.js       Web Worker, chạy phần nặng
    │   └── app.js          giao diện
    └── vendor/             SheetJS 0.20.3 (Apache-2.0)
```

---

## 8. Vướng mắc thường gặp

**Trang không phản hồi khi thả file to.**
Phần nặng chạy trong Web Worker nên trang vẫn bấm được, nhưng file 23 MB mất
khoảng 20–30 giây. Dòng trạng thái cho biết đang đọc tới sheet nào.

**"Không có sheet nào nhận diện được".**
Tiêu đề cột trong file không khớp bộ cột mà công cụ tìm. Công cụ dò 30 dòng đầu để
tìm dòng tiêu đề và so tên cột không phân biệt hoa thường. Nếu cổng đổi tên cột thì
sửa `SHEET_KINDS` trong `web/js/core.js` và mảng `REQUIRED` tương ứng trong
`vatrec/sources/`.

**Doanh thu cao gấp đôi.**
Đã chọn cả sheet của kỳ cũ trong cùng một file. Xem dòng "Giao dịch trùng mã" ở
sheet `Đối soát` — phần trùng đã bị bỏ, nhưng phần chỉ có ở sheet cũ thì không.
Bỏ chọn sheet cũ rồi chạy lại.

**Điểm xuất hoá đơn thiếu Khu vực / Mã điểm misa.**
Danh mục QR (`chia theo mã cửa hàng`) chỉ có tên điểm. Muốn đủ thông tin thì chọn
kèm sheet danh mục của VNPay hoặc Payoo — công cụ tự gộp, bản ghi đầy đủ hơn thắng.

**MoMo: sheet nào cũng bị nhận là sao kê.**
File MoMo thường kèm sao kê của kỳ trước (`Sheet 1`). Bỏ chọn sheet đó ở bước 3.
Nếu để nguyên thì phần trùng mã giao dịch tự bị bỏ và phần ngoài kỳ bị loại, đều
được báo ở sheet `Đối soát`.

**Tổng Zalo bằng 0.**
Chưa khai ánh xạ gian hàng Zalo sang điểm xuất hoá đơn. Xem mục 4.1.

**Cập nhật plugin rồi mà công cụ vẫn như cũ.**
Trình duyệt còn giữ bản JavaScript cũ. Bấm Ctrl+Shift+R (Cmd+Shift+R trên Mac) một
lần. Xem số phiên bản ở cuối trang công cụ để biết bản nào đang chạy. Nếu lập trình
viên sửa code mà quên đổi `?v=` thì mọi người vẫn nhận bản cũ — xem mục 1.

**WordPress báo "Không tìm thấy gói mở rộng hợp lệ".**
Đang cài nhầm zip của trang tĩnh. Chạy `wordpress/dong-goi.sh` để lấy zip plugin.

**Địa chỉ `/doi-soat-vat/` báo 404.**
WordPress chưa dựng lại bảng đường dẫn. Vào Cài đặt → Đường dẫn tĩnh và bấm Lưu.
Dùng tạm đường dẫn dự phòng in ở trang quản trị trong lúc đó.

**Muốn địa chỉ khác `/doi-soat-vat/`.**
Sửa hằng `DSVAT_SLUG` trong `doi-soat-vat.php`, rồi vào Cài đặt → Đường dẫn tĩnh
bấm Lưu.

**Muốn nâng cấp SheetJS.**
Đọc `web/vendor/README.md` trước — gói `xlsx` trên npm đứng yên ở 0.18.5 và dính
hai lỗ hổng đã công bố.
