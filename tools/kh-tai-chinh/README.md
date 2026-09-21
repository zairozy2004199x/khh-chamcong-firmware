# Tài Chính K&H — plugin WordPress

Dựng lại **KH Bank Tracker** (app Node.js) thành plugin WordPress, để chạy thẳng
trên hosting hiện có: chỉ cần wp-admin, không cần Render/Railway, không cần SSH.

## Cài

Plugin → Thêm Plugin → **Tải Plugin lên** → chọn `kh-tai-chinh.zip` → Kích hoạt.
Menu **Tài Chính K&H** hiện ở thanh bên trái wp-admin.

Đóng gói lại sau khi sửa mã:

```bash
cd tools/kh-tai-chinh
./dong-goi.sh              # sinh ra kh-tai-chinh.zip
```

## Hai chỗ cố tình làm khác bản gốc

**Dữ liệu trong MySQL, không phải JSON.** Bản gốc giữ toàn bộ giao dịch trong
`transactions.json` — dữ liệu thật đã 95 MB / 219.000 dòng, mỗi lần mở trang là
nạp cả tệp vào RAM. Trên shared hosting cách đó không chạy nổi. Bảng MySQL có chỉ
mục `(cty, ngay)` nên lọc theo kỳ không phải quét toàn bảng.

**Đăng nhập dùng tài khoản WordPress.** Bản gốc tự dựng bảng người dùng + mật
khẩu riêng. Bớt một chỗ giữ mật khẩu là bớt một chỗ có thể rò. Quyền tối thiểu
để mở là `edit_pages` (Editor) — kế toán thường không phải Administrator.

## Đang có

| Màn hình | Làm được gì |
|---|---|
| Tổng quan | Tổng số dư, thu/chi tháng này, số dư từng tài khoản, các đợt đối soát còn lệch |
| Ngân hàng | Thêm/xoá tài khoản; số dư đầu tính từ một ngày mốc |
| Giao dịch / Sao kê | Thêm tay; dán sao kê hàng loạt; lọc theo tài khoản, khoảng ngày, thu/chi, nội dung; phân trang 100 dòng |
| Đối soát | Dán bảng cổng gửi về, ghép với sao kê, chia ra Khớp / Lệch tiền / Thiếu / Thừa; tải CSV |

Mọi màn hình đều tách **KH Cũ / KH Mới**, chọn ở góc phải. Lựa chọn lưu theo
từng người dùng, nên hai kế toán mở cùng lúc không đá nhau.

### Số dư tính thế nào

`số dư = số dư đầu + thu − chi`, chỉ tính giao dịch **từ ngày mốc trở đi**.
Giao dịch trước ngày đó coi như đã nằm sẵn trong số dư đầu; cộng lại là đếm hai
lần. Đây là chỗ dễ sai nhất của màn hình ngân hàng nên tính một lần trong
`KHTC_NganHang::so_du()`, không rải ra từng trang.

### Dán sao kê

Mỗi dòng: `Ngày (dd/mm/yyyy) · Diễn giải · Số tiền · Thu/Chi`, cách nhau bằng
Tab (copy thẳng từ Excel) hoặc dấu phẩy. Bỏ trống cột cuối thì số dương là Thu,
số âm là Chi.

Số tiền nhận cả `1.500.000`, `1,500,000`, `1500000` và `20.000 ₫`. Quy ước: nhóm
sau dấu cuối cùng dài đúng 3 chữ số thì đó là phân cách nghìn, ngược lại là dấu
thập phân. Không có quy ước này thì `20.000 ₫` đọc ra 20 đồng — sai 1000 lần mà
không có gì báo, vì 20 vẫn là số hợp lệ.

## Bản web ngoài

Cùng bốn màn hình đó, nhưng ở địa chỉ công khai của website thay vì trong
wp-admin:

```
https://tenmien.vn/tai-chinh/            Tổng quan
https://tenmien.vn/tai-chinh/ngan-hang/
https://tenmien.vn/tai-chinh/giao-dich/
https://tenmien.vn/tai-chinh/doi-soat/
```

**Vẫn phải đăng nhập.** "Ra web ngoài" ở đây là đổi địa chỉ và bỏ khung
wp-admin, không phải mở cho người lạ: chưa đăng nhập thì đá về trang đăng nhập,
đăng nhập rồi mà thiếu quyền thì báo thẳng. Trang cũng đặt `noindex`. Số dư ngân
hàng của công ty không phải thứ để công khai.

Ra 404 → vào **Cài đặt → Đường dẫn tĩnh**, bấm Lưu một lần. Host không bật đường
dẫn tĩnh thì dùng `https://tenmien.vn/?khtc_man=tong-quan`.

Trang tự dựng HTML riêng, **không** gọi `get_header()` của theme: theme nào cũng
có CSS riêng cho bảng và nút, mượn khung theme thì mỗi lần đổi giao diện website
là bảng tài chính lại vỡ một kiểu.

## Đối soát ghép thế nào

Bốn lượt, độ chắc chắn giảm dần. Một dòng sao kê đã bị nhận thì lượt sau không
đụng tới nữa — nếu không, hai dòng cổng cùng nhận một dòng sao kê và tổng "khớp"
sẽ lớn hơn số tiền thật về tài khoản.

| Lượt | Ghép theo | Nhãn |
|---|---|---|
| 1 | Trùng mã giao dịch (kể cả lệch ngày) | Khớp mã giao dịch |
| 2 | Trùng ngày **và** trùng số tiền | Khớp ngày + số tiền |
| 3 | Trùng số tiền, ngày lệch trong T+3 | Khớp số tiền, lệch ngày |
| 4 | Trùng số tiền **sau khi trừ phí** | Khớp sau khi trừ phí |

Lượt 4 là lượt hay bị bỏ sót nhất. Payoo và VNPay chuyển về số ròng: cổng ghi
1.000.000, phí 11.000, ngân hàng về 989.000. Thiếu lượt này thì gần như mọi dòng
rơi vào "Thiếu" và bảng đối soát thành vô dụng.

Trùng mã nhưng số tiền không bằng → **Lệch tiền**, tách riêng chứ không gộp vào
"Khớp".

Dòng cổng trùng mã giao dịch với dòng đã nạp trong cùng đợt thì **bị bỏ qua**.
Dán hai lần cùng một bảng là chuyện thường; không chặn thì tổng cổng gấp đôi
trong khi sao kê giữ nguyên, ra một bảng lệch không có thật.

Bấm "Chạy lại đối soát" thì ghép **lại từ đầu**, không ghép thêm: sao kê có thể
vừa được nạp bổ sung, và một dòng "Thiếu" hôm qua hôm nay đã có tiền về. Ghép
thêm thì kết quả phụ thuộc thứ tự bấm nút.

Thẻ số ở đầu trang **cố ý không có** "tổng cổng trừ tổng ngân hàng": tài khoản
còn nhận tiền mặt và tiền kênh khác, nên hiệu đó gần như luôn khác 0 kể cả khi
đối soát sạch.

## Chạy test

```bash
php tools/kh-tai-chinh/tests/kiem-so-ngay.php    # 19 phép thử
php tools/kh-tai-chinh/tests/kiem-ghep.php       # 23 phép thử
php tools/kh-tai-chinh/tests/kiem-man-hinh.php   # 44 phép thử
```

`dong-goi.sh` chạy cả ba trước khi gói, hỏng một phép là không ra file zip.

* **kiem-so-ngay** — hai hàm đọc số và đọc ngày, phần quyết định mọi con số vào
  sổ và là phần sai âm thầm nếu hỏng.
* **kiem-ghep** — phép ghép của đối soát, tách riêng thành `KHTC_DoiSoat::ghep()`
  đúng để kiểm được mà không cần MySQL. Ghép sai không làm hỏng gì thấy được:
  bảng vẫn ra, tổng vẫn cộng, chỉ là kế toán đi đòi cổng một khoản đã về.
* **kiem-man-hinh** — dựng thật cả bốn màn hình trên `tests/gia-lap-wp.php`, một
  bộ giả lập WordPress tối thiểu cắm vào SQLite, rồi kiểm con số in ra.

Bộ giả lập **không thay được bản cài thật**: SQLite không phải MySQL, `dbDelta`
bị bỏ qua và bảng được tạo tay, nên lỗi riêng của MySQL vẫn lọt. Nó bắt được hàm
gọi sai tên, biến chưa khai báo, HTML vỡ và số sai — đã bắt được một lỗi chết
trang thật: câu lọc giao dịch dùng `cty` trần trong câu có JOIN, mà bảng ngân
hàng cũng có cột `cty`, nên MySQL báo "ambiguous column" và trang trắng.

## Còn phải làm

Các mảng chưa dựng lại: pháp danh, chi phí và đối soát chi phí, hồ sơ, hoá đơn
đầu vào, hoá đơn đầu ra, công nợ, báo cáo, sao lưu.

Đối soát ở bản này là **một engine dùng chung** cho VietQR / Payoo / VNPay /
Zalo / MoMo, thay vì mỗi cổng một trang riêng như bản gốc (3.722 + 2.180 dòng
cho hai nhóm cổng). Cùng phép ghép, chỉ khác nhãn kênh. Cổng nào có quy tắc
riêng mà bốn lượt hiện tại không phủ được thì thêm lượt vào `KHTC_DoiSoat::ghep()`
— chỗ duy nhất cần sửa, và đã có bộ kiểm đứng sau.
