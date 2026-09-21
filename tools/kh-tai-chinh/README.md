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
| Chi phí | Ghi khoản chi theo bộ phận × khoản mục; bảng cộng chéo; lọc; danh mục sửa tại chỗ |
| Đối soát chi phí | Ghép chứng từ chi phí với dòng chi trong sao kê |
| Hoá đơn đầu ra | Dán vào / xuất ra đúng 22 cột file VAT; gom theo thuế suất, khu vực, dịch vụ |
| Hoá đơn đầu vào | VAT được khấu trừ, tờ khai GTGT, ngưỡng tiền mặt |
| Công nợ | Phải thu / phải trả, tuổi nợ theo mốc, ghi trả từng đợt, tự ghép từ sao kê |
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
php tools/kh-tai-chinh/tests/kiem-chi-phi.php    # 68 phép thử
php tools/kh-tai-chinh/tests/kiem-hoa-don.php    # 128 phép thử
php tools/kh-tai-chinh/tests/kiem-khoa-nhat-ky.php   # 59 phép thử
php tools/kh-tai-chinh/tests/kiem-cong-no.php    # 80 phép thử
php tools/kh-tai-chinh/tests/kiem-hoa-don-vao.php   # 65 phép thử
php tools/kh-tai-chinh/tests/kiem-bao-cao.php    # 64 phép thử
```

`dong-goi.sh` chạy cả ba trước khi gói, hỏng một phép là không ra file zip.

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

Bộ giả lập **không thay được bản cài thật**: SQLite không phải MySQL, `dbDelta`
bị bỏ qua và bảng được tạo tay, nên lỗi riêng của MySQL vẫn lọt. Nó bắt được hàm
gọi sai tên, biến chưa khai báo, HTML vỡ và số sai — đã bắt được một lỗi chết
trang thật: câu lọc giao dịch dùng `cty` trần trong câu có JOIN, mà bảng ngân
hàng cũng có cột `cty`, nên MySQL báo "ambiguous column" và trang trắng.

## Còn phải làm

Các mảng chưa dựng lại: pháp danh, hồ sơ.

Đối soát ở bản này là **một engine dùng chung** cho VietQR / Payoo / VNPay /
Zalo / MoMo, thay vì mỗi cổng một trang riêng như bản gốc (3.722 + 2.180 dòng
cho hai nhóm cổng). Cùng phép ghép, chỉ khác nhãn kênh. Cổng nào có quy tắc
riêng mà bốn lượt hiện tại không phủ được thì thêm lượt vào `KHTC_DoiSoat::ghep()`
— chỗ duy nhất cần sửa, và đã có bộ kiểm đứng sau.
