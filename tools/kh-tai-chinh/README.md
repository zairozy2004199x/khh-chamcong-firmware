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

## Hướng dẫn sử dụng

`Tai-Chinh-KH-Huong-dan-su-dung.pdf` — 27 trang, kèm ảnh chụp cả 17 màn hình,
đi từ lúc cài đến lúc ra số: nhập gì ở đâu → đối chiếu thế nào → đọc kết quả ra
sao. Cuối bản có một bảng việc-làm-hằng-tháng 16 bước và một bảng những chỗ dễ
sai 12 dòng.

Bản dựng nằm trong `huong-dan/` (HTML + CSS + ảnh). Dựng lại:

```bash
cd tools/kh-tai-chinh/huong-dan && node in-pdf.mjs
```

Ảnh chụp từ dữ liệu mẫu chạy trên bộ giả lập, không phải số thật của công ty.

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
| Chi phí | Ghi khoản chi theo bộ phận × khoản mục; bảng cộng chéo; lọc; danh mục sửa tại chỗ |
| Đối soát chi phí | Ghép chứng từ chi phí với dòng chi trong sao kê |
| Hoá đơn đầu ra | Dán vào / xuất ra đúng 22 cột file VAT; gom theo thuế suất, khu vực, dịch vụ |
| Hoá đơn đầu vào | VAT được khấu trừ, tờ khai GTGT, ngưỡng tiền mặt |
| Công nợ | Phải thu / phải trả, tuổi nợ theo mốc, ghi trả từng đợt, tự ghép từ sao kê |
| Hồ sơ | Sổ lưu chứng từ; dò nối với bút toán; bút toán nào chưa có giấy |
| Pháp danh | Sổ hợp đồng thuê / NCC; cảnh báo sắp hết hạn; doanh thu chia sẻ |
| Báo cáo | Cả kỳ trên một trang; xu hướng 12 tháng; tải CSV |
| Nhật ký | Ai làm gì lúc nào; khoá sổ theo ngày; phục hồi bản ghi đã xoá |
| Sao lưu | Tải cả kho ra .json, nhập lại được |

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

## Chi phí

**Một bảng cho tất cả bộ phận, không phải mỗi bộ phận một trang.** wp-admin hiện
có năm menu chi phí rời (Chi Phí Tổng, KVC, VP, MTĐ, Nội bộ) — năm chỗ nhập, năm
chỗ sửa, muốn biết tổng cả công ty thì phải cộng tay. Ở đây bộ phận chỉ là một
**cột**: lọc ra từng bộ phận vẫn xem riêng được, mà cộng ngang dọc thì máy làm.

Bảng **cộng chéo khoản mục × bộ phận** là thứ kế toán thật sự cần: "tháng này
khu vui chơi tốn bao nhiêu tiền điện" là một ô, không phải lọc hai lần rồi cộng.
Cộng trong SQL chứ không kéo hết dòng về PHP — một tháng vài nghìn dòng thì kéo
về vẫn chạy, một năm thì không.

Bộ phận và khoản mục sửa được ngay trên trang, lưu trong `wp_options` theo từng
pháp nhân. Vài chục dòng chữ thì một bảng MySQL kèm màn hình quản lý là thừa.

Chi **tiền mặt** được đánh dấu riêng và đối soát chi phí bỏ qua — nếu không nó
bị báo "chưa thấy tiền ra" oan, vì tiền mặt không đi qua ngân hàng.

## Đối soát chi phí

Ghép chứng từ chi phí với tiền thật đã ra khỏi tài khoản, **dùng lại đúng**
`KHTC_DoiSoat::ghep()` của đối soát cổng thanh toán. Bài toán giống hệt — hai
danh sách ngày/số tiền/mã, ghép một-một, không dòng nào được nhận hai lần — nên
viết lại lần nữa chỉ là thêm một chỗ để sai. Số chứng từ đóng vai mã giao dịch;
chi phí không có phí cổng nên lượt trừ phí tự bỏ qua.

Ba nhóm: **Khớp** · **Có chứng từ, chưa thấy tiền ra** (đòi kế toán chi, hoặc
chứng từ ghi nhầm) · **Tiền ra, không có chứng từ** (thiếu chứng từ, hoặc tiền
ra ngoài sổ).

Chạy xong, màn hình Chi phí biết khoản nào đã trả thật — cột "Tiền ra".

## Hoá đơn đầu ra

Nối thẳng với công cụ **Đối soát VAT** đang dùng: ô dán vào nhận **đúng 22 cột**
mà file kia sinh ra, **đúng thứ tự**, kể cả cột `STT` và cột trống thứ 21. Copy
từ Excel là dán được, không phải sắp lại cột. Nút tải CSV xuất ra cũng đúng 22
cột đó — có một phép kiểm khứ hồi: xuất ra rồi dán lại phải ra y hệt, từng
trường một, kể cả mã điểm misa và địa chỉ ở cột cuối.

Bảng **gom theo thuế suất** chính là mấy dòng phải điền vào tờ khai GTGT — khỏi
lọc từng bậc rồi chép số bằng tay. Gom thêm theo khu vực và dịch vụ.

Số hoá đơn **duy nhất trong mỗi pháp nhân**, chặn bằng `UNIQUE (cty, so_hd)` ở
tầng bảng chứ không chỉ ở màn hình: xuất trùng số hoá đơn là sai luật, và chặn ở
tầng bảng thì không lệ thuộc đường nào ghi vào. Hai pháp nhân đánh số riêng nên
không đụng nhau.

### Có VAT thắng khi ba số không khớp

Chạy dữ liệu thật 2025 của công ty bắt được một lỗi nặng ở đây. Bản đầu lấy
`chưa VAT + VAT` làm gốc rồi ghi đè lên cột có VAT, vì tưởng hai cột kia là số
gốc. Thực tế ngược lại: **có VAT là tiền khách đã trả**, máy tính tiền ghi lại,
gần như luôn là số tròn; chưa VAT và VAT là hai số suy ra bằng phép chia rồi
làm tròn.

File của công ty tính `VAT = round(chưa VAT × 8%)` — làm tròn hai lần nên lệch
1 đồng, ở **234 / 2.781 hoá đơn**. Bản đầu vì thế đổi hoá đơn 66.265.000 thành
66.264.999: khách trả một đằng, hoá đơn ghi một nẻo.

Giờ giữ có VAT và chưa VAT như file, bù chênh vào VAT. Chạy lại dữ liệu thật:
số dòng, tổng chưa VAT và tổng có VAT khớp tuyệt đối với số chốt trong file;
chỉ tổng VAT lệch 7 đ (KH989) và 99 đ (KH705) — đúng phần làm tròn mà file để
sai. Màn hình vẫn đếm và nói ra số dòng đã sửa, không sửa lặng lẽ.

### Phép tính VAT

Quy tắc bất di bất dịch, **từng dòng một**:

```
chưa VAT + VAT = có VAT
```

`tinh()` chỉ làm tròn **một lần** rồi lấy hiệu ra số thứ ba, không bao giờ làm
tròn hai số rồi cộng. Làm tròn hai lần thì lệch 1 đồng — số vẫn hợp lệ, bảng vẫn
in ra, nhưng Misa từ chối cả tệp và không nói vì sao. Bộ kiểm chạy hàng chục số
lẻ (1 đ, 7 đ, 333.333 đ, 12.345.679 đ) qua cả ba bậc 5/8/10% và bắt buộc đẳng
thức đúng ở mọi trường hợp.

Một giới hạn có thật: trong bảng dán vào, **0 vừa nghĩa là "ô để trống" vừa nghĩa
là "thuế đúng bằng 0"** — không phân biệt được. Quy ước: `VAT = 0` luôn hiểu là
chưa biết và tính lại theo thuế suất. Hoá đơn 0% vẫn ra đúng vì thuế suất 0 cho
lại VAT = 0.

Dòng nào trong file gốc có `chưa VAT + VAT ≠ có VAT` thì lấy hai số đầu làm gốc,
tính lại có VAT, và **đếm số dòng như vậy để báo lên** — không sửa lặng lẽ.

## Hoá đơn đầu vào

**Đây là chuyện thuế, không phải sổ phải trả thứ hai.** Công nợ phải trả đã
dựng trên bảng chi phí; thêm hoá đơn đầu vào làm nguồn nợ nữa là quay lại đúng
cái lỗi hai nguồn sự thật vừa bỏ ở 0.6. Màn hình này trả lời một câu khác:
tháng này được khấu trừ bao nhiêu VAT, và phải nộp bao nhiêu.

Cũng vì thế nó **không gộp vào bảng chi phí**: không phải khoản chi nào cũng có
hoá đơn (lương, chi lặt vặt tiền mặt), và không phải hoá đơn đầu vào nào cũng
là chi phí (mua tài sản cố định, mua hàng nhập kho). Gộp lại thì một trong hai
bảng luôn phải mang những dòng không thuộc về nó.

Phép tính VAT **dùng lại** `KHTC_HoaDonRa::tinh()` — cùng phép toán, cùng quy
tắc "chỉ làm tròn một lần rồi lấy hiệu", đã có hàng chục phép kiểm đứng sau.
Chỗ này dùng lại là đúng; khác với việc mượn phép ghép của đối soát cổng cho
công nợ, vốn là hai bài toán khác nhau nên đã hỏng.

### Tờ khai GTGT

```
VAT phải nộp = VAT đầu ra − VAT đầu vào ĐƯỢC KHẤU TRỪ
```

Phần không được khấu trừ **không** trừ vào đây. Ra số âm thì gọi đúng tên là
**chuyển kỳ sau**, không phải nhà nước trả lại — hai con số này không bao giờ
cùng dương, và có một phép kiểm buộc như vậy.

Đây là chỗ hai màn hình hoá đơn gặp nhau: đổi khoảng ngày ở bộ lọc là cả bảng
tờ khai đổi theo, số đầu ra lấy thẳng từ mục Hoá đơn đầu ra cùng kỳ.

### Ngưỡng tiền mặt

Hoá đơn từ **20.000.000 đ** trở lên mà trả bằng tiền mặt được đặt sẵn là
**không khấu trừ**, kèm lý do hiện ngay trên dòng.

Đây là **nhắc, không phải phán quyết**: kế toán bật lại được bằng một nút. Quy
định thuế đổi theo thời kỳ và có ngoại lệ — phần mềm không nên quyết thay, chỉ
nên làm cho chỗ đáng ngờ đập vào mắt.

### Chặn trùng theo cặp số hoá đơn + MST

Khác hoá đơn đầu ra (số do mình đánh nên duy nhất trong pháp nhân), số hoá đơn
đầu vào do **bên bán** đánh. Hai nhà cung cấp cùng phát hành số `00000001` là
bình thường. Chặn theo mình số hoá đơn thì nhà cung cấp thứ hai không nhập được
hoá đơn hợp lệ của họ, nên `UNIQUE (cty, so_hd, mst)`.

## Hồ sơ

Sổ lưu chứng từ giấy. Trả lời hai câu, và câu thứ hai mới là câu khó:

1. Chứng từ này mình có giữ không, file ở đâu?
2. **Bút toán trong sổ kia đã có chứng từ lưu chưa?**

Bản gốc (`routes/ho-so.js`, 1.497 dòng) chỉ trả lời được câu 1: một danh sách
giấy tờ với ô đánh dấu "đã hạch toán" gõ tay, không nối với sổ sách nào. Ở đây
plugin đã có sẵn hoá đơn đầu vào, đầu ra, chi phí và hợp đồng nên nối được thật
— và câu 2 chính là câu kiểm toán sẽ hỏi.

### Hai cột "đã hạch toán" và "đã nối" để riêng

`đã hạch toán` là cờ kế toán tự bật. `đã nối` là máy tìm được đúng một bút toán
mang số chứng từ đó trong sổ.

Cố ý **không** suy cái này từ cái kia. Chúng có thể khác nhau một cách hợp lệ —
hạch toán trên Misa ngoài hệ thống này chẳng hạn — nên màn hình có hẳn một thẻ
số **"đánh dấu nhưng chưa nối"**. Với một sổ đối chiếu giấy với sổ, che mất chỗ
lệch là bỏ mất chính công việc.

### Dò nối

Ghép theo **số chứng từ**, và chỉ khi trong sổ có **đúng một** bút toán mang số
đó. Hai bút toán cùng số thì bỏ qua — nối nhầm còn tệ hơn không nối, vì bảng
"bút toán chưa có chứng từ" sẽ báo là đã có, cho một bút toán thật ra chưa có
giấy nào.

Không xét số tiền: hồ sơ hay ghi số tròn còn sổ ghi số lẻ sau chiết khấu, bắt
khớp cả tiền thì gần như không nối được gì. Số chứng từ đã đủ chặt khi nó duy
nhất.

### Hợp đồng nối được nhưng không vào bảng chiều ngược

Màn hình Pháp danh đã đếm "chưa có bản đủ dấu" ngay trên chính hợp đồng. Trả
lời cùng một câu ở hai nơi bằng hai cách đo thì sớm muộn hai chỗ nói khác nhau,
và không ai biết bên nào đúng.

### Những phần của bản gốc không mang sang

`ho-so.js` còn có đồng bộ hoá đơn từ Google Drive, phí cảng Phú Quốc kèm tải
ảnh, và một bảng doanh thu chia sẻ theo điểm theo tháng. Phần Drive bỏ vì cùng
lý do với phần Sheet ở Pháp danh. Phí cảng Phú Quốc là một khoản chi cụ thể,
ghi thẳng vào **Chi phí** với bộ phận riêng là đủ. Doanh thu chia sẻ đã có ở
**Pháp danh**, làm lại lần nữa là hai nguồn sự thật.

## Pháp danh

Sổ hợp đồng: **thuê gian hàng** và **nhà cung cấp**, đổi qua lại ở ô Sổ.

Dựng lại từ `routes/phap-danh.js` của bản gốc (2.287 dòng), đọc thẳng mã nguồn
chứ không đoán theo tên — "pháp danh" hoá ra là sổ hợp đồng, không phải gì
khác.

**Một bảng cho cả hai loại.** Thuê và NCC khác nhau vài cột (gian, tiền thuê
tháng, tỷ lệ chia sẻ so với nội dung, giá trị hợp đồng) nhưng giống nhau ở phần
cốt lõi: đối tác, MST, số hợp đồng, ngày hết hạn, bản scan. Hai bảng gần giống
hệt thì mọi việc dùng chung — cảnh báo sắp hết hạn, đếm hợp đồng thiếu dấu, tìm
theo đối tác — đều phải viết hai lần và sớm muộn lệch nhau.

### Khác bản gốc một chỗ quan trọng

Bản gốc lưu thời hạn hợp đồng thành **một ô chữ tự do** (`thoiHanHopDong`), nên
không máy nào biết hợp đồng nào sắp hết — phải mở từng dòng ra đọc. Ở đây ngày
bắt đầu và ngày hết hạn là **cột ngày thật**, và có hẳn bảng "sắp hết hạn trong
60 ngày", phân biệt "còn 12 ngày" với "quá hạn 18 ngày". Đó là việc mà sổ hợp
đồng sinh ra để làm.

Tổng tiền thuê tháng chỉ cộng hợp đồng **đang hoạt động** và **không được
miễn** — đã đóng hoặc tạm ngưng không tính.

### Doanh thu chia sẻ

Ghép hợp đồng với hoá đơn đầu ra qua **mã điểm nội bộ** — cột mà cả hai bảng
đều có sẵn. Không tự bịa đường nối nào khác: hợp đồng chưa điền mã điểm thì
hiện thẳng "chưa gắn mã điểm" và ra 0, kèm cảnh báo đỏ, chứ **không đoán theo
tên gian** — đoán sai ở đây là trả nhầm tiền cho người khác.

Hợp đồng đánh dấu **miễn** thì doanh thu vẫn hiện nhưng phần chia bằng 0, để
còn đối chiếu được.

Có tỷ lệ % mà chưa chọn hình thức chia sẻ là **trạng thái mâu thuẫn** — hợp
đồng mang 15% nhưng không bao giờ xuất hiện trong bảng, và không có gì cho thấy
vì sao. Cả ô nhập tay lẫn ô dán bảng đều tự đặt về "giữ tiền".

### Không dựng lại phần đồng bộ Google Sheet

Bản gốc có `cap-nhat-tu-sheet` kéo dữ liệu từ một bảng tính ngoài. Plugin không
với tới bảng tính đó, và một đường nạp dữ liệu im lặng từ nơi khác là thứ khó
dò nhất khi số sai. Thay bằng ô dán bảng như mọi màn hình khác.

## Báo cáo

Một trang gom cả kỳ: dòng tiền từng tài khoản, xu hướng 12 tháng, doanh thu
theo khu vực và dịch vụ, chi phí cộng chéo, thuế GTGT, công nợ cuối kỳ, đối
soát còn lệch. Nút kỳ nhanh (tháng trước / quý / năm) và tải CSV.

**Chỉ đọc, không có bảng riêng.** Mọi con số tính lại từ chứng từ gốc mỗi lần
mở. Lưu sẵn kết quả thì nhanh hơn, nhưng sửa một hoá đơn tháng trước là báo cáo
đã lưu thành sai mà không có gì báo — đúng cái bẫy mà sổ thanh toán ở 0.6 đã
phải dọn.

**Không hàm nào dùng "hôm nay" làm mốc.** Chạy báo cáo tháng 8 vào tháng 10
phải ra đúng số tháng 8 — kể cả số dư ngân hàng và công nợ. Có một phép kiểm
đổ thêm dữ liệu tháng 10 rồi buộc báo cáo tháng 8 không được nhúc nhích.

Sửa lỗi này khi làm báo cáo: `KHTC_CongNo::con_no()` **không lọc theo ngày
chứng từ**, chỉ dùng ngày chốt để tính tuổi nợ. Báo cáo tháng 8 vì thế gồm cả
hoá đơn tháng 10, và một hoá đơn tháng 8 trả hồi tháng 10 thì nhìn lại tháng 8
đã thấy hết nợ. Giờ ngày chốt cắt **cả chứng từ lẫn các lần trả**.

**Số dư cuối kỳ tính độc lập**, không lấy đầu kỳ cộng thu trừ chi. Hai đường
phải gặp nhau; lệch khác 0 thì trang hiện cảnh báo đỏ — nghĩa là có dòng nằm
ngoài mốc "tính từ ngày" của tài khoản mà vẫn được cộng.

**Kết quả tạm tính** = doanh thu chưa VAT − chi phí đã ghi. Ghi rõ trên cả màn
hình lẫn tệp CSV rằng đây *không* phải báo cáo kết quả kinh doanh: thiếu khấu
hao, giá vốn, phân bổ trước sau.

### Bảng xu hướng 12 tháng

Thanh so sánh nằm ngay trong ô, ba điều cố ý:

1. **Thanh chỉ có một màu.** Xanh lá / đỏ mà plugin dùng cho thu / chi trượt
   kiểm tra mù màu nặng — validator của bộ dataviz cho ΔE 4.2 ở deutan, dưới cả
   ngưỡng sàn 6, tức là với người mù màu đỏ-lục hai thanh y hệt nhau. Ở đây
   không cần màu để phân biệt: thu và chi nằm ở **hai cột riêng**, mỗi cột có
   tiêu đề và có sẵn con số. Một hue xanh dương qua sạch cả sáu phép kiểm.
   Con số vẫn giữ xanh/đỏ vì đó là chữ, tương phản cao, và đã quen mắt.
2. **Thu và chi chung một thước đo.** Mỗi cột một thước thì tháng thu 1 tỷ và
   tháng chi 10 triệu vẽ ra thanh dài bằng nhau. Cũng vì thế không cột nào có
   trục thứ hai.
3. **Cột chênh lệch vẽ từ giữa** sang trái hoặc phải. Dấu do **hướng** mang,
   không do màu — in đen trắng vẫn đọc được.

Lớp CSS của ô biểu đồ tên `.khtc-vach`, **không** phải `.khtc-thanh` — tên đó
đã là thanh điều hướng trên cùng. Dùng trùng thì nav sụp thành cột dọc và ô
biểu đồ ăn nguyên màu nền đen của nav; đã xảy ra thật, thấy được nhờ dựng trang
ra ảnh, và giờ có phép kiểm chặn.

## Công nợ

Phải thu dựng từ hoá đơn đầu ra, phải trả dựng từ chi phí chuyển khoản. Chia
theo mốc tuổi nợ **1–30 / 31–60 / 61–90 / trên 90 ngày**, gom theo khách hàng
và nhà cung cấp — mở bảng ra là thấy ngay phải gọi ai trước.

Chi **tiền mặt không vào sổ công nợ**: trả ngay tại chỗ thì không nợ ai.

Tuổi nợ tính từ **hạn thanh toán** nếu chứng từ có ghi, không có thì từ ngày
chứng từ. Hai cách cho ra bảng rất khác nhau: nhà cung cấp cho nợ 30 ngày mà
tính từ ngày hoá đơn thì hôm nộp hàng đã thành "quá hạn 1 ngày".

### Một sổ thanh toán duy nhất

Mọi đồng tiền trả cho một chứng từ — gõ tay hay do đối soát tự ghép — đều là
một dòng trong bảng `thanh_toan`. Còn nợ **luôn** bằng:

```
tổng chứng từ − tổng các dòng thanh toán của nó
```

Không lưu sẵn "đã trả" ở đâu cả. Bản 0.5 còn dùng cờ `chi_phi.giao_dich_id`
cho việc này; 0.6 bỏ, vì hai nguồn sự thật về việc đã trả hay chưa thì sớm muộn
lệch nhau và lúc đó không ai biết bên nào đúng. Màn hình Chi phí và màn hình
Công nợ giờ đọc cùng một chỗ — có một phép kiểm đứng đúng ở chỗ này.

Không ghi trả **quá số còn nợ**. Trả thừa không phải chuyện không xảy ra, nhưng
nó là một khoản khác (đặt cọc, ghi nhầm); cho vào đây thì bảng công nợ ra số
dương giả và không dò ra từ đâu.

### Phép đoán ghép — khác hẳn đối soát cổng

Thử dùng lại `KHTC_DoiSoat::ghep()` cho công nợ là **sai**, và đã sai thật khi
làm: phép kia ghép theo ngày gần nhau, dung sai 3 ngày — đúng cho cổng chốt
T+2, T+3. Công nợ thì ngược hẳn: khách nhận hoá đơn đầu tháng, trả cuối tháng
sau, cách nhau 40 ngày là bình thường. Ép dùng phép kia thì gần như không ghép
được gì.

`KHTC_CongNo::doan_ghep()` có luật riêng, và luật nào cũng chỉ ghép khi **không
còn cách hiểu nào khác**:

1. Số chứng từ xuất hiện trong mã giao dịch hoặc diễn giải sao kê, và số tiền
   đúng bằng phần còn nợ.
2. Số tiền đúng bằng phần còn nợ, và trong cả danh sách chỉ có **đúng một**
   chứng từ mang số tiền đó.

Luật 2 bắt buộc phải duy nhất. Hai hoá đơn cùng còn nợ 5.400.000 mà đoán bừa
thì đóng nhầm cái này, để hở cái kia — kế toán đi đòi nhầm người, và không có
gì trên màn hình cho thấy đã đoán sai.

Tiền chỉ trả cho chứng từ phát sinh **trước hoặc cùng ngày**; không có giới hạn
"cách bao nhiêu ngày". Trả gộp nhiều chứng từ hay trả làm nhiều đợt thì phải
ghi tay — đoán sai một khoản trả gộp còn tệ hơn không đoán.

Chạy lại thì các dòng **tự ghép** trong kỳ bị dọn và làm lại; dòng ghi tay giữ
nguyên.

## Khoá sổ và nhật ký

Hai mô hình lấy từ cách các phần mềm kế toán thật làm — QuickBooks, Zoho Books,
NetSuite đều có khoá kỳ; LedgerSMB, NetSuite và Zoho đều có audit trail.

### Khoá sổ

Một **ngày khoá** cho mỗi pháp nhân. Mọi bản ghi mang ngày từ đó trở về trước
thì không thêm, không xoá được.

Vì sao cần: tờ khai GTGT nộp rồi mà ai đó thêm một hoá đơn lùi ngày vào tháng
cũ thì bảng "theo thuế suất" đổi số ngay, trong khi tờ khai đã nộp thì không
đổi. Lần sau mở lại, số trên màn hình khác số đã nộp và không ai biết vì sao.

Khoá chỉ có giá trị nếu **không có đường vòng**, nên nó chặn cả lối gián tiếp:
xoá một tài khoản ngân hàng còn giao dịch trong kỳ khoá bị từ chối — bằng không
chỉ cần xoá tài khoản là mọi dòng đã khoá biến mất. Bộ kiểm đi thử từng lối ghi
một, kể cả lối này.

Hai việc **cố ý không bị khoá chặn**: chạy lại đối soát cổng và đối soát chi
phí. Chúng chỉ ghi cờ "dòng này ứng với dòng kia", không đụng ngày, số tiền hay
phân loại của bản ghi nào — chốt sổ xong vẫn phải đối chiếu lại được với cổng.

**Cố ý không có mật khẩu riêng** như QuickBooks. Thêm một mật khẩu là thêm một
thứ để quên và để dán lên màn hình. Ai mở được plugin thì mở được khoá — nhưng
mọi lần đặt và mở khoá đều vào nhật ký. Biết ai mở, lúc nào, đáng giá hơn là
chặn được ai.

### Nhật ký

Hai kế toán dùng chung một sổ và tiền là tiền thật, nên "ai xoá dòng này" phải
trả lời được.

**Ghi theo lô, không theo dòng.** Dán một bảng sao kê 2.000 dòng mà ghi 2.000
dòng nhật ký thì nhật ký to hơn cả sổ và không ai đọc nổi — che mất đúng thứ cần
thấy. Hàm dán hàng loạt bật một cờ, các hàm ghi lẻ im lặng, cuối cùng chỉ một
dòng "nạp 2.000 giao dịch".

**Xoá thì giữ lại nguyên văn bản ghi**, nên phục hồi được — và phục hồi đúng id
cũ, vì các bảng khác trỏ vào nhau bằng id; đặt lại bằng id mới thì bản ghi sống
lại nhưng mồ côi.

Cách này gọn hơn xoá mềm: xoá mềm bắt **mọi** câu truy vấn trong plugin phải nhớ
lọc "chưa xoá", quên một chỗ là số sai lặng lẽ. Giữ xác trong nhật ký thì không
câu nào phải đổi.

## Sao lưu

Dữ liệu giờ nằm trong MySQL của website. Website đổi host, ai đó gỡ nhầm plugin,
một bản nâng cấp hỏng — mất hết. Bản gốc chạy trên file JSON nên "sao lưu" chỉ
là copy một tệp; đổi sang MySQL mà không làm đường ra là **lấy đi mất một thứ
người dùng đang có**.

Xuất cả năm bảng và danh mục ra một tệp `.json`. Nhập lại thì **thêm vào, không
xoá** cái đang có, và **id được cấp lại**: giữ nguyên id cũ thì nhập vào một
website đã có dữ liệu sẽ khiến giao dịch trỏ nhầm tài khoản — số dư sai mà không
có gì báo. Các liên kết (giao dịch → tài khoản, dòng cổng → đợt, chi phí → giao
dịch) được nối lại theo id mới; có một phép kiểm đứng sau đúng chỗ này.

## Dữ liệu mẫu

Một nút ở màn hình Sao lưu nạp **89 dòng giả** — 2 tài khoản, 13 tháng sao kê,
5 hoá đơn ra, 4 hoá đơn vào, 6 chi phí, 5 hợp đồng, 3 hồ sơ, một đợt đối soát
— rồi chạy luôn đối soát chi phí, tự ghép công nợ và dò nối hồ sơ, để mọi màn
hình có số ngay lần mở đầu tiên. Kế toán xem thử được cách bày số trước khi
nhập số thật, và bản hướng dẫn chụp được ảnh màn hình có dữ liệu.

Ba chỗ phải cẩn thận, vì một nút "nạp dữ liệu" đặt sai là nút phá sổ:

* **Chỉ nạp khi sổ còn trống.** `du_lieu_that()` đếm những dòng *không* mang id
  mẫu; còn một dòng thật thì từ chối. Khoá sổ cũng chặn.
* **Xoá đúng những dòng chính nó tạo.** Id được ghi vào `wp_options` lúc nạp,
  lúc xoá đọc lại đúng danh sách đó — không xoá theo ngày, không xoá theo
  "trông giống dữ liệu mẫu".
* **Quét luôn dòng mồ côi.** Xoá một chứng từ mà bỏ lại dòng `thanh_toan` của
  nó thì khoản đã trả vẫn được cộng vào dù chứng từ không còn — vô hình trên
  màn hình nhưng sai số. Xoá theo tầng ở cả hoá đơn ra và chi phí, cộng một
  lượt quét mồ côi khi dọn dữ liệu mẫu.

Bản dựng dữ liệu mẫu dùng **ngày tương đối** (lùi n tháng so với hôm nay), nên
cài lúc nào cũng có dữ liệu rơi vào tháng hiện tại và mấy màn hình mặc định lọc
"tháng này" không ra bảng trắng. Một hợp đồng cố tình còn 12 ngày là hết hạn và
một hợp đồng quá hạn 18 ngày, để phần cảnh báo có gì mà cảnh báo; một hoá đơn
vào trả tiền mặt trên 20 triệu, để nhắc khấu trừ hiện ra.

## Sinh hoá đơn từ sao kê

Đến bản 1.4.0 plugin mới chỉ phủ **nửa sau** của quy trình: nhận danh sách hoá
đơn 22 cột đã làm xong rồi tính tiếp. Việc biến hàng chục nghìn dòng QR thành
vài trăm hoá đơn vẫn nằm trong Excel — và đó là chỗ mất nhiều giờ nhất mỗi
tháng.

Luồng thật, đọc ra từ chính tệp của công ty:

```
tiền vào tài khoản (mỗi dòng mang MÃ CỬA HÀNG)
   ↓  danh mục điểm: mã cửa hàng → điểm xuất hoá đơn + mã Misa + khu vực
   ↓  cộng theo điểm trong kỳ
   ↓  mỗi điểm một hoá đơn → sổ hoá đơn đầu ra
```

### Ba thứ máy KHÔNG tự quyết

Đoán sai mấy thứ này thì ra một danh sách **trông rất hợp lý mà sai** — loại
sai đắt nhất, vì không ai soát lại cái trông hợp lý. Nên chúng là lựa chọn
hiện trên màn hình, không phải giả định giấu trong mã:

1. **Kỳ và ngày hoá đơn** — người dùng chọn.
2. **Nguồn tiền nào vào hoá đơn** — tick từng tài khoản, từng đợt cổng.
3. **Độ mịn** — **mặc định mỗi điểm mỗi ngày một tờ**, đúng luật công ty đang
   dùng. Đổi sang gộp cả kỳ được nếu cần một tờ tổng.
   Chạy luật đó trên dữ liệu thật tháng 8/2026 của KH705 ra **1.983 tờ**, trong
   khi file hoá đơn thật có **1.986 cặp (điểm × ngày)** — lệch 3, đúng bằng
   phần mã Payoo chưa ánh xạ. Đây là phép đối chiếu chặt nhất từ trước tới nay
   giữa cái máy sinh ra và cái công ty làm tay.
4. **Điểm nào không xuất** — cờ "bỏ qua" trong danh mục, dùng cho mã test, mã
   vãng lai, gian đã đóng. Cố ý là một CỜ chứ không phải xoá dòng: xoá rồi thì
   lần nạp danh mục sau nó lại về, và không ai nhớ vì sao trước đó nó bị loại.

Máy chỉ làm phần cộng và phần tách VAT — phần nó làm không sai.

### Không đồng nào được biến mất

Tiền mang mã cửa hàng **không có trong danh mục** không được im lặng bỏ đi:
doanh thu hụt mà không ai thấy là kiểu sai tệ nhất ở đây. Nó được gom riêng,
đếm, và liệt kê từng mã ra màn hình. Bộ kiểm có một phép chốt:

```
tiền vào hoá đơn + tiền điểm bỏ qua + tiền mã lạ  =  đúng tổng thu trong kỳ
```

Chạy trên dữ liệu thật tháng 8/2026 của KH989: 270 mã trong danh mục, 1.636
dòng sao kê, **0 mã lạ, 0 đồng thất lạc**, ra 4 hoá đơn tổng 138.450.000 đ —
đúng bằng tiền vào tài khoản.

### Số hoá đơn

Cấp liên tiếp từ số bắt đầu. Trùng một số nào đó ở giữa dải thì **dừng hẳn và
không ghi dòng nào**, chứ không bỏ qua rồi chạy tiếp: số hoá đơn nhảy cóc là
thứ cơ quan thuế hỏi đầu tiên, và sửa sau tốn hơn nhiều so với chạy lại.

## Chạy liên tục: dán sao kê chồng kỳ

Sổ này chạy tháng này qua tháng khác. Kế toán tải sao kê rồi dán, lần sau tải
lại thường **lấy dư mấy ngày cho chắc**, hoặc dán hai lần vì không rõ lần đầu
đã ăn chưa. Bảng `giao_dich` không có khoá duy nhất, nên trước bản 1.4.0 mỗi
lần như thế là **số dư phình lên, im lặng** — và phải dò tay hàng chục nghìn
dòng mới biết sai ở đâu.

Giờ chặn theo cặp **(tài khoản, mã giao dịch)**. Mã tham chiếu ngân hàng là
duy nhất nên đây là phép so chính xác, không phải phỏng đoán: kiểm trên 54.061
dòng sao kê thật của công ty, **100% dòng có mã** và **không mã nào lặp** trong
cùng một tài khoản.

Dòng **không có mã thì không chặn**. Hai khách cùng trả 50.000 một ngày ở cùng
một điểm là chuyện thường; gộp chúng lại là mất tiền thật — sai nặng hơn hẳn
việc để lọt một dòng trùng mà người ta còn thấy được. Số dòng loại này được
đếm và nói ra để người dán tự quyết.

Nhập tệp sao lưu cũng theo đúng luật đó, nên nhập lại một tệp không nhân đôi
sao kê; dòng cổng đã ghép với một giao dịch vẫn trỏ đúng vào dòng đã có thay
vì hoá mồ côi.

## Tài khoản đăng nhập riêng

Vẫn **không dựng bảng mật khẩu riêng** — người dùng ở đây là tài khoản
WordPress thật, để WordPress lo băm mật khẩu, khoá sau nhiều lần sai, gửi thư
đặt lại và mọi bản vá về sau. Màn hình Người dùng chỉ là lối tắt.

Chỗ thật sự đổi là **quyền**. Bản trước lấy `edit_pages` làm điều kiện vào,
nghĩa là muốn cho kế toán xem sổ thì phải cho họ làm Editor — kèm quyền sửa và
xoá mọi trang của website. Giờ có quyền riêng `khtc_xem` và vai trò
**Kế toán K&H** chỉ mang đúng quyền đó.

Bốn chỗ phải cẩn thận, vì đây là cửa vào sổ tiền:

* **Nâng cấp không chạy `register_activation_hook`.** Chỉ gán quyền lúc kích
  hoạt thì người đang dùng bản cũ bị khoá ngoài ngay sau khi bấm Cập nhật. Nên
  có bộ lọc `user_has_cap` bù `khtc_xem` cho ai đang có `edit_pages`, và vai
  trò được dựng lại khi số bản đổi.
* **Gỡ quyền phải gỡ được thật.** Người mang vai trò Editor vẫn có
  `edit_pages`, nên bộ lọc bù quyền sẽ cho họ vào lại — bấm Gỡ xong mà vẫn vào
  được thì tệ hơn là không có nút. Gỡ đặt thêm một quyền phủ định để chặn hẳn.
  Có phép kiểm đúng cho trường hợp này.
* **Mật khẩu hiện đúng một lần** và không bao giờ vào nhật ký. Nhật ký ghi
  *việc* đã làm, không ghi mật khẩu. Quên thì đặt lại cái mới.
* **Không tự gỡ quyền của chính mình**, và không gỡ quản trị viên website từ
  đây — gỡ xong là không vào lại được để sửa.

Màn hình chỉ hiện với ai có `create_users` và `list_users`. Kế toán không thấy
mục này trong menu, và bấm thẳng đường dẫn cũng bị từ chối.

## Gói dữ liệu kèm bản cài

Nhập tệp sao lưu qua ô tải lên vướng `upload_max_filesize` — nhiều host mặc
định chỉ 2 MB, mà một kỳ dữ liệu thật đã 5 MB. Đặt tệp `.json` vào thư mục
`du-lieu/` trong plugin thì nó đi cùng file zip (đã nén sẵn, JSON co lại còn
khoảng một phần tư) và màn hình Sao lưu hiện nút nhập thẳng.

```bash
cp kho.json wordpress/kh-tai-chinh/du-lieu/     # thư mục này bị .gitignore
./dong-goi.sh /duong/dan/khac                   # gói ra NGOÀI kho mã
```

Tách một kỳ sao kê dài thành nhiều tệp cho khỏi hết giờ thì **đặt cùng số tài
khoản** ở mỗi tệp: lúc nhập, tệp thứ hai nhận ra tài khoản tệp thứ nhất đã tạo
và dùng lại, không xẻ số dư ra nhiều dòng trùng tên. Đã thử thật với 45.959
dòng chia ba phần — ra đúng một tài khoản, đủ dòng, không dòng nào mồ côi.

Hai chỗ phải giữ đúng:

* **Thư mục `du-lieu/` nằm trong `.gitignore`.** Dữ liệu thật của một công ty
  không bao giờ được nằm trong kho mã. Bản phát hành bình thường không có thư
  mục này, nên cũng không có nút.
* **Chọn tệp bằng tên, không bằng đường dẫn.** Tên do trình duyệt gửi lên chỉ
  được nhận nếu nó nằm trong danh sách `tep_kem()` đã quét sẵn — ghép thẳng
  tên vào đường dẫn là mở cửa cho `../../wp-config.php`. Có phép kiểm đứng
  đúng chỗ này.

## Giao diện

Menu **dọc bên trái**, gom theo nhóm — mười bốn mục xếp phẳng thì tìm mục nào
cũng phải đọc hết cả hàng, và ở bản cũ nó đã tràn xuống hai dòng.

Cách bày menu và danh sách đường dẫn hợp lệ là **hai thứ riêng**:
`KHTC_Web::nhom()` quyết định cách bày, `KHTC_Web::man_hinh()` vẫn là chỗ duy
nhất quyết định đường dẫn nào chạy được. Đổi cách bày không vô tình mở hay
khoá mất một trang.

**Khung nhập liệu gấp lại được** bằng `<details>` — không một dòng JavaScript,
và bấm được cả khi trình duyệt chặn script. Chỉ khung *nhập* mới gấp; bảng số
liệu không bao giờ gấp, vì mở trang ra là phải thấy số ngay.

### Ba nguyên tắc của bảng màu

1. Chiều sâu do **bóng đổ nhiều lớp** và nền chuyển sắc rất nhẹ, không do viền
   dày hay bóng đậm. Bóng nhiều lớp mảnh trông như giấy xếp chồng; một lớp
   bóng dày trông như nút nhựa.
2. Màu nằm ở **khung** — thanh bên, thẻ số, nút bấm. Trong bảng số liệu màu
   chỉ còn xanh/đỏ cho thu/chi, vì đó là quy ước kế toán đã quen mắt.
3. **Con số luôn đọc được trước.** Nền chuyển sắc không bao giờ chạy dưới một
   cột số. Một bảng tiền đẹp mà phải nheo mắt đọc là một bảng hỏng.

Một tệp CSS phục vụ cả wp-admin lẫn bản web ngoài. Phần nào chỉ dành cho web
ngoài thì nằm dưới `body.khtc-web` — wp-admin đã có thanh bên và nền riêng,
chồng thêm là vỡ. Ngược lại `body.wp-admin` được bù 32px cho tiêu đề bảng dính,
vì thanh quản trị của WordPress đã chiếm sẵn đỉnh màn hình.

### Màn hẹp

Dưới 1100px thanh bên nằm ngang và menu chiếm **trọn một hàng riêng**. Thiếu
`flex-basis: 100%` thì nó bị tên công ty và phần đăng nhập ép thành một cột hẹp
và mỗi mục vỡ thành ba dòng — đã xảy ra thật khi thử ở 420px. Dưới 640px menu
thành một hàng vuốt ngang được, thay vì mười bốn viên xếp chồng đẩy hết nội
dung xuống dưới màn hình.

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

## Đối soát tự kiểm trước khi người đọc kịp tin

Chạy thử một tệp Payoo (308 triệu) với sao kê của **một tài khoản khác** (138
triệu) — hai dòng tiền không liên quan gì nhau — máy vẫn báo **"Khớp 291 dòng"**.
Không dòng nào khớp theo mã; cả 291 là trùng ngẫu nhiên ngày và số tiền, vì sao
kê QR có 517 dòng đúng 100.000 đ, 390 dòng 20.000 đ, 377 dòng 50.000 đ. Mệnh
giá tròn và lượng lớn thì đụng nhau là chắc chắn.

Phép ghép **không sửa**: khi hai tệp đúng là của nhau, ghép nhiều dòng cùng
mệnh giá trong một ngày vẫn ra tổng đúng — đó mới là việc của đối soát. Từ chối
ghép chỉ vì trùng mệnh giá sẽ phá đúng trường hợp bình thường. Cái phải sửa là
sự im lặng: con số "Khớp 291" tự nó trông rất yên tâm.

`KHTC_DoiSoat::canh_bao()` nói thẳng, ngay trên bốn bảng, khi:

* tổng cổng trừ phí lệch quá **5%** so với tiền thực nhận — nới 5% cho dòng về
  muộn qua kỳ, còn lệch đúng bằng phí là bình thường nên im;
* đợt trên 20 dòng khớp mà **không dòng nào** ghép được theo mã giao dịch.

Kèm một dòng đếm ghép nhờ lượt nào, để người đọc biết kết quả chắc tới đâu.

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
php tools/kh-tai-chinh/tests/kiem-chi-phi.php    # 70 phép thử
php tools/kh-tai-chinh/tests/kiem-hoa-don.php    # 128 phép thử
php tools/kh-tai-chinh/tests/kiem-khoa-nhat-ky.php   # 59 phép thử
php tools/kh-tai-chinh/tests/kiem-cong-no.php    # 86 phép thử
php tools/kh-tai-chinh/tests/kiem-hoa-don-vao.php   # 65 phép thử
php tools/kh-tai-chinh/tests/kiem-bao-cao.php    # 64 phép thử
php tools/kh-tai-chinh/tests/kiem-phap-danh.php  # 79 phép thử
php tools/kh-tai-chinh/tests/kiem-ho-so.php      # 64 phép thử
php tools/kh-tai-chinh/tests/kiem-mau.php       # 42 phép thử
```

Tổng **743 phép thử**. `dong-goi.sh` chạy hết trước khi gói, hỏng một phép là
không ra file zip.

* **kiem-so-ngay** — hai hàm đọc số và đọc ngày, phần quyết định mọi con số vào
  sổ và là phần sai âm thầm nếu hỏng.
* **kiem-ghep** — phép ghép của đối soát, tách riêng thành `KHTC_DoiSoat::ghep()`
  đúng để kiểm được mà không cần MySQL. Ghép sai không làm hỏng gì thấy được:
  bảng vẫn ra, tổng vẫn cộng, chỉ là kế toán đi đòi cổng một khoản đã về.
* **kiem-man-hinh** — dựng thật các màn hình trên `tests/gia-lap-wp.php`, một
  bộ giả lập WordPress tối thiểu cắm vào SQLite, rồi kiểm con số in ra.
* **kiem-chi-phi** — chi phí, bảng cộng chéo, đối soát chi phí và sao lưu, cũng
  chạy thật trên bộ giả lập. Kiểm cả những chỗ dễ sai lặng lẽ: tổng cột và tổng
  hàng của bảng cộng chéo phải cộng lại bằng tổng chung; chi tiền mặt không bị
  báo thiếu; chạy đối soát hai lần phải ra y hệt; nhập sao lưu xong không giao
  dịch nào trỏ vào tài khoản không tồn tại.
* **kiem-hoa-don** — nặng nhất ở phép tính VAT: mọi số lẻ qua mọi bậc thuế phải
  giữ `chưa VAT + VAT = có VAT`. Cộng phép kiểm khứ hồi xuất-rồi-dán-lại, và một
  phép chặn hồi quy cho ô thuế suất mặc định (PHP đổi khoá mảng `'8'` thành số
  nguyên `8`, so nghiêm ngặt với chuỗi thì trượt và mọi hoá đơn ghi tay mất
  thuế — lỗi này đã xảy ra thật, thấy được nhờ dựng trang ra ảnh).
* **kiem-khoa-nhat-ky** — đi thử **từng lối ghi một** vào kỳ đã khoá, kể cả lối
  vòng (xoá tài khoản kéo theo xoá giao dịch, phục hồi từ nhật ký). Một lối
  quên kiểm tra là cả tính năng thành trang trí.
* **kiem-cong-no** — trả làm nhiều đợt, chặn trả thừa, tuổi nợ theo mốc, và
  `doan_ghep()` kiểm riêng như một hàm thuần: hai chứng từ cùng số tiền thì
  phải im lặng, không trả cho chứng từ chưa phát sinh, một dòng sao kê chỉ đóng
  được một chứng từ. Cộng một phép kiểm buộc màn hình Chi phí và màn hình Công
  nợ nói cùng một con số.
* **kiem-hoa-don-vao** — ngưỡng tiền mặt ở cả ba mốc (dưới / đúng / trên), và
  tờ khai: phần không khấu trừ phải **không** bị trừ vào số phải nộp, và
  "phải nộp" với "chuyển kỳ sau" không bao giờ cùng dương.
* **kiem-bao-cao** — đổ thêm dữ liệu tháng 10 rồi buộc **mọi** con số của báo
  cáo tháng 8 không đổi; hai đường tính số dư phải gặp nhau; thanh biểu đồ dài
  nhất phải đúng 100% và không thanh nào vượt.
* **kiem-phap-danh** — "sắp hết hạn" ở cả bốn trường hợp (còn hạn, quá hạn,
  đúng mốc 60 ngày, 61 ngày, chưa điền hạn), và doanh thu chia sẻ: hợp đồng
  chưa gắn mã điểm phải ra 0 chứ không đoán; hợp đồng miễn vẫn hiện doanh thu
  nhưng phần chia bằng 0.
* **kiem-mau** — nạp dữ liệu mẫu rồi **xoá**, và buộc mọi bảng trở lại đúng
  như trước khi nạp; nạp đè lên sổ đã có dữ liệu thật phải bị từ chối; xoá
  không được đụng vào một dòng người dùng tự nhập. Cộng phép kiểm chặn dòng
  `thanh_toan` mồ côi và phép kiểm nhật ký: một lần nạp chỉ ghi **một** dòng.
* **kiem-ho-so** — phép dò nối: hai bút toán cùng số phải bỏ qua; gỡ nối rồi dò
  lại phải nối lại đúng cái vừa gỡ; bút toán bị xoá thì nhãn nói thẳng chứ
  không im. Và cờ hạch toán phải độc lập với việc nối được — bật cờ cho một hồ
  sơ chưa nối phải làm số "lệch" tăng lên.

Bộ giả lập **không thay được bản cài thật**: SQLite không phải MySQL, `dbDelta`
bị bỏ qua và bảng được tạo tay, nên lỗi riêng của MySQL vẫn lọt. Nó bắt được hàm
gọi sai tên, biến chưa khai báo, HTML vỡ và số sai — đã bắt được một lỗi chết
trang thật: câu lọc giao dịch dùng `cty` trần trong câu có JOIN, mà bảng ngân
hàng cũng có cột `cty`, nên MySQL báo "ambiguous column" và trang trắng.

## Còn phải làm

**Mọi mảng của bản gốc đã dựng lại xong** (bản 1.0.0). Bản 1.1.0 dựng lại giao
diện, bản 1.2.0 thêm dữ liệu mẫu và bản hướng dẫn PDF.

Hai phần cố ý không mang sang: **đồng bộ Google Sheet** (Pháp danh) và **đồng
bộ Google Drive** (Hồ sơ). Plugin không với tới hai nơi đó, và một đường nạp dữ
liệu im lặng từ bên ngoài là thứ khó dò nhất khi số sai. Mọi màn hình đều có ô
dán bảng thay thế.

Vẫn đúng như từ đầu: bộ kiểm chạy trên **SQLite, không phải MySQL**. Lỗi riêng
của MySQL vẫn lọt qua. Lần cài đầu tiên trên host thật mới là lần thử thật.

Đối soát ở bản này là **một engine dùng chung** cho VietQR / Payoo / VNPay /
Zalo / MoMo, thay vì mỗi cổng một trang riêng như bản gốc (3.722 + 2.180 dòng
cho hai nhóm cổng). Cùng phép ghép, chỉ khác nhãn kênh. Cổng nào có quy tắc
riêng mà bốn lượt hiện tại không phủ được thì thêm lượt vào `KHTC_DoiSoat::ghep()`
— chỗ duy nhất cần sửa, và đã có bộ kiểm đứng sau.
