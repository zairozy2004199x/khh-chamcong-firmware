# Web app "Ủy nhiệm chi & Công nợ"

Trang web để kế toán **biết ngay khoản nào đã đi tiền, khoản nào chưa**, và **công nợ phải trả
từng nhà cung cấp còn bao nhiêu, quá hạn bao lâu** — thay cho việc dò tay trong file Excel
*"Đi ủy nhiệm chi KVC (MT, MN)"*.

Chạy **hoàn toàn trong trình duyệt**: không máy chủ, không tài khoản, **không gửi dữ liệu đi đâu**.
Mọi thứ nằm trên chính máy đang mở trang.

## Vì sao cần

File Excel hiện tại **không có cột nào đánh dấu tiền đã đi hay chưa**. Muốn biết thì phải nhìn cột
*Ngày tạo lệnh* có điền chưa — mà cột đó lại ghi tự do (`PAYMENT 31/8`, `payment 30/11`). Thêm nữa:

| Chỗ lệch chuẩn trong file | Trang web xử lý |
|---|---|
| 2 tài khoản công ty bị ghi thành **16 kiểu** (`K&H cũ`, `K VÀ H CŨ`, `KH CŨ`, `K va H cu`…) | Gom về đúng 2 nhóm: K&H cũ / K&H mới |
| Ngày ghi bằng chữ: `UNC 1/9`, `PAYMENT 31/8`, `trước 14/12`, `30-31/12` | Đọc ra ngày thật, tự suy năm theo sheet (sheet T1/2026 ghi `UNC 28/12` là 12/2025) |
| Một ô chứa 2 số tiền xuống dòng (`33,000,000⏎11,000,000`) | Cộng lại, không nối thành số khổng lồ |
| Ghi chú của kế toán lọt vào cột thụ hưởng (`Nộp thừa 25/08`, `cấn trừ với chị Vân`) | Xếp riêng loại *Ghi chú nộp / cấn trừ*, **không tính vào công nợ nhà cung cấp** |
| 419 mã bộ phận cho ~20 bộ phận thật (`FZ SC-gà rán`, `FZ SC-nước đá`) | Tách thành bộ phận + khoản mục |
| Dòng cộng, dòng trống, số tài khoản bị Excel đổi thành số thực (`22789677.0`) | Bỏ / chuẩn hoá |

## Bản WordPress (khuyên dùng khi cả bộ phận cùng tra)

Thư mục `khunc-uy-nhiem-chi/` là **plugin WordPress** dùng chung toàn bộ giao diện ở đây, nhưng
dữ liệu nằm trong MySQL nên **cả bộ phận thấy cùng một bộ số**: kế toán nhập Excel một lần, ai mở
trang cũng thấy; đánh dấu ở máy này thì máy kia tải lại là thấy. Đăng nhập PIN, 3 vai trò
**Admin** (mọi thứ + quản lý người dùng) · **Kế toán** (nhập Excel, đánh dấu) · **Xem** (chỉ tra
cứu và in — mọi nút ghi tự ẩn). Có nhật ký thao tác và tự cập nhật từ nhánh GitHub.

Sửa giao diện ở đây rồi chạy `python3 khunc-uy-nhiem-chi/tools/dong-bo-giao-dien.py` để chép sang
plugin (CI kiểm tra lệch). Chạy thử không cần WordPress:
`php -S 127.0.0.1:8088 khunc-uy-nhiem-chi/tools/dev/router.php` → `/uy-nhiem-chi/` (PIN 1111).

Cài bằng zip từ Releases (`khunc-uy-nhiem-chi-vX.Y.Z`). Xem `khunc-uy-nhiem-chi/readme.txt`.

Khác nhau giữa hai bản:

| | Bản tĩnh (thư mục này) | Bản WordPress |
|---|---|---|
| Dữ liệu ở đâu | Trình duyệt của từng người | MySQL, dùng chung |
| Ai nhập Excel | Ai cũng phải tự nhập | Kế toán nhập một lần |
| Đánh dấu "đã đi tiền" | Chỉ máy đó thấy | Mọi người thấy |
| Đăng nhập | Không | PIN, 3 vai trò |
| Cần gì | Chỉ trình duyệt | Hosting WordPress |

## Mở ứng dụng

Cách 1 — mở trực tiếp: tải thư mục `web_uynhiemchi/` về máy, mở `index.html` bằng Chrome / Edge.

Cách 2 — chạy qua máy chủ tĩnh (khi muốn dùng chung trong mạng nội bộ):

```bash
cd web_uynhiemchi
python3 -m http.server 8080
# mở http://localhost:8080
```

Cách 3 — nhúng vào trang khác bằng `<iframe src=".../web_uynhiemchi/index.html">`.
Trang cho đọc/ghi từ ngoài qua `window.UNCApp` (`getState()`, `setState()`, `getDong()`, `getCongNo()`).

Chưa có file Excel thì bấm **⋯ → Nạp dữ liệu mẫu để xem thử** (số liệu bịa, để xem giao diện).

## Dùng hằng ngày

1. **Nhập Excel** (kéo-thả file vào trang cũng được, `Ctrl+O`). Trang đọc **mọi sheet** trong file,
   nhận ra 3 kiểu bố cục lẫn lộn:

   | Kiểu sheet | Nhận ra bằng | Đọc thành |
   |---|---|---|
   | Sheet tháng 2025–2026 | có cột *Tên đơn vị thụ hưởng* | Ủy nhiệm chi (kể cả bản trước 11/2025 thiếu cột *Ngày cần đi tiền*) |
   | Sheet thuê mặt bằng (*Posh - JP*, *FZ - DIY - TÀU*) | có cột *Tiền cọc /Tiền thuê* | Mỗi cột tiền (thuê, điện, phí DV, phí BH) thành **một khoản riêng**, hạn lấy từ *Hạn thanh toán* |
   | `TT TIỀN MẶT` | có cột *Link hóa đơn* | Chi tiền mặt, giữ link hoá đơn |

   Sheet nháp không có tiêu đề (`21.06`, `8.7.2024`, `Trang tính1`) được **bỏ qua có báo** — xem tab
   **Nhật ký** để biết sheet nào đọc bao nhiêu dòng, sheet nào bỏ và vì sao.

2. **Lần đầu**: vào tab **Ủy nhiệm chi**, lọc từng kỳ cũ → **Chọn tất cả đang lọc** → **✓ Đã đi tiền**.
   Vài cái bấm là chốt xong cả năm cũ, từ đó về sau chỉ còn theo dõi kỳ đang chạy.

3. **Tab Ủy nhiệm chi** — cái anh cần nhất. Lọc theo kỳ, bộ phận, tài khoản chi, trạng thái,
   khoảng hạn, hoặc gõ tìm (gõ **không dấu vẫn ra**: `thai binh an` tìm được `THÁI BÌNH AN`).
   Tick **Chỉ quá hạn** để ra ngay danh sách phải xử lý. Bấm một dòng để xem đầy đủ và đánh dấu:

   | Trạng thái | Nghĩa |
   |---|---|
   | Chưa lập lệnh | Chưa làm gì |
   | Đã lập lệnh | Đã làm UNC, chờ ngân hàng |
   | Đã đi tiền | Tiền đã ra khỏi tài khoản (ghi kèm ngày đi + số chứng từ) |
   | Huỷ / không nộp | Bỏ, không tính vào công nợ |

4. **Tab Công nợ** — bảng kế toán hay phải làm: từng nhà cung cấp có tổng phát sinh, đã thanh toán,
   còn phải trả, chia cột **tuổi nợ** (chưa đến hạn / quá hạn 1–30 / 31–60 / 61–90 / trên 90 ngày).
   Đổi **Gom theo** sang Bộ phận, Tài khoản chi hoặc Kỳ. Đổi **Tính đến** để đối chiếu theo mốc ngày
   trong quá khứ. Bấm một dòng → xem từng khoản và in thẳng biên bản đối chiếu.

5. **Tab Mẫu biểu** — 7 mẫu, tự điền sẵn từ khoản / nhà cung cấp đang chọn, in A4 hoặc lưu PDF:

   | Mẫu | Ghi chú |
   |---|---|
   | Ủy nhiệm chi | In 2 liên, có số tiền bằng chữ |
   | Giấy đề nghị thanh toán | Mẫu 05-TT |
   | Phiếu chi | Mẫu 02-TT |
   | Biên bản đối chiếu công nợ | Chốt số với nhà cung cấp, 2 bản |
   | Bảng kê chi tiết công nợ phải trả | Kèm biên bản đối chiếu |
   | Sổ chi tiết thanh toán với người bán | Mẫu S31-DN, TK 331 |
   | Kế hoạch chi tiền trình ký | Danh sách đang lọc, để sếp duyệt |

   Điền **⋯ → Thông tin công ty & tài khoản** một lần thì mẫu nào cũng có sẵn tên công ty, MST,
   số tài khoản K&H cũ / mới và người đại diện.

6. **Xuất Excel** (`Ctrl+S`) ra file 7 sheet: *Ủy nhiệm chi* (có lọc sẵn), *Công nợ theo NCC*,
   *Tuổi nợ*, *Theo kỳ*, *Theo bộ phận*, *Theo tài khoản*, *Cảnh báo*.

## Trạng thái không mất khi nhập lại file

Mỗi khoản có một **khoá riêng** sinh từ kỳ + bộ phận + nội dung + số tiền + số tài khoản.
Trạng thái kế toán đánh dấu lưu theo khoá đó, **tách khỏi dữ liệu đọc từ Excel**. Nên tháng sau
xuất file mới từ Google Sheets rồi nhập lại, những khoản cũ **vẫn giữ nguyên đánh dấu** —
trang báo rõ "Giữ lại N khoản đã đánh dấu trước đó". Hai dòng trùng hệt nhau vẫn được đánh số
để không đè lên nhau.

## Cách hiểu "đã đi tiền"

File gốc không nói thẳng, nên trang cho chọn trong **⋯ → Thông tin công ty**:

- **Dòng có *Ngày tạo lệnh* = đã đi tiền** (mặc định) — hợp với cách bộ phận đang ghi: điền
  `PAYMENT …` khi tiền đã chuyển.
- **Dòng có *Ngày tạo lệnh* = mới lập lệnh** — nếu muốn kế toán xác nhận thêm một bước nữa.

Khoản nào kế toán đã tự đánh dấu thì **luôn theo kế toán**, lựa chọn này không đè lên.

## Đối chiếu sao kê ngân hàng

Tab **Đối chiếu sao kê** thay cho việc mở hai thứ cạnh nhau rồi dò từng dòng. Tải sao kê từ ngân
hàng (`.xlsx`/`.csv`) thả vào, trang **chỉ đọc dòng tiền RA** rồi bày ra sáu nhóm:

| Nhóm | Nghĩa | Làm gì |
|---|---|---|
| **Khớp chắc — chờ bật** | Số tiền khớp **đúng đến đồng** + **số tài khoản người thụ hưởng** khớp + ngày trong cửa sổ + chỉ có **một** ứng viên | Bấm một nút là bật *"đã đi tiền"* cho cả nhóm, ngày lấy đúng ngày ngân hàng trừ |
| **Đã khớp, sổ đã ghi** | Sổ đã ghi *"đã đi"* và ngân hàng có lượt trừ đúng khoản ấy | Xong, để đếm lại |
| **Cần người nhìn** | Khớp tiền + tên, hoặc nhiều khoản cùng số tiền nên máy không chọn hộ | Bấm một dòng để mở khoản ấy rồi tự đánh dấu |
| **Lệch với sổ** | Kế toán đã đánh tay trạng thái khác, mà ngân hàng vẫn trừ đúng số tiền ấy | Xem lại — chuyển nhầm, trả đường khác, hay chính lượt đánh tay kia sai |
| **NH trừ, sổ không có** | Tiền ra khỏi tài khoản mà sổ không có khoản nào khớp | Thường là phí/lãi/thuế — nhưng cũng là chỗ **duy nhất** lộ ra một lượt chuyển không ai đề nghị |
| **Sổ có, NH chưa trừ** | Khoản còn phải trả mà sao kê chưa thấy lượt trừ | Quá hạn thì xử trước |

Mấy chốt cố ý, để đối chiếu tự động không thành đường sai tiền:

- **Chỉ nhóm khớp chắc mới bật được**, và cũng phải **bấm nút** — trang không tự đổi gì cả.
  Tự bật một khoản là xoá nó khỏi công nợ; đoán sai theo chiều ấy thì bảng vẫn sạch, chỉ là
  thiếu một khoản thật.
- **Lệch một đồng là hai khoản khác nhau.** Không có chuyện "lệch dưới 1.000đ cũng coi là khớp".
- **Một lượt trừ chỉ khớp một dòng sổ**, và ngược lại. Cùng nhà cung cấp, cùng số tiền, hai tháng
  liền — không ràng buộc thì cả hai dòng cùng bám vào một lượt chuyển.
- **Không đè lên thứ kế toán đã đánh tay.** Máy nghĩ khác người thì bày ra nhóm *Lệch*, để người quyết.
- **Không đoán chiều tiền.** Sao kê có hai cột *Ghi nợ / Ghi có*, hoặc một cột *Số tiền* mang dấu
  âm thì đọc được. Một cột số tiền **không dấu** thì trang báo lại chứ không đoán — đoán sai chiều
  là một khoản tiền **về** cũng thành "đã trả".

Cửa sổ ngày (mặc định: lệnh trước 3 ngày, tiền trừ sau 14 ngày) chỉnh được ngay trên màn.
Sao kê **không được lưu lại** giữa hai lần mở trang — nó là bản chụp một lúc của ngân hàng;
thứ cần giữ là trạng thái *"đã đi tiền"* sau khi bấm áp dụng, và thứ ấy lưu như mọi lượt đánh dấu khác.

## Cảnh báo tự động

Tab **Tổng quan** nêu thẳng: khoản quá hạn (kèm số ngày trễ), khoản đến hạn trong 3 ngày tới,
**nghi trùng chi** (cùng số tài khoản + cùng số tiền + cùng kỳ), và khoản **thiếu thông tin chuyển khoản**
(chưa có tên thụ hưởng / số tài khoản / ngân hàng / tài khoản chi). Bấm "xem →" là nhảy thẳng tới khoản đó.

## Lưu trữ & chuyển máy

Dữ liệu tự lưu trong trình duyệt (localStorage). File thật khoảng 3.400 khoản chiếm ~2,5 MB —
vẫn trong hạn mức, nhưng muốn chuyển máy hoặc lưu trữ thì dùng **⋯ → Lưu toàn bộ ra file (.json)**
rồi mở lại bằng **Mở file đã lưu**. File `.json` đó gồm cả dữ liệu lẫn trạng thái đã đánh dấu.

Phím tắt: `Ctrl+O` nhập Excel, `Ctrl+S` xuất Excel, `Esc` đóng ngăn đang mở.

## Kiểm thử

```bash
node web_uynhiemchi/test/engine.test.js
node web_uynhiemchi/test/saoke.test.js
```

41 + 31 bài, không cần cài gì thêm. Tập trung vào những chỗ dữ liệu thật hay làm sai: ngày ghi bằng chữ,
số tiền nhiều khoản trong một ô, 16 cách viết tên ngân hàng, dòng `CÔNG TY …` **không được** nhận
nhầm là dòng `Cộng`, và khoá dòng phải ổn định giữa 2 lần nhập.

Bộ thử sao kê canh đúng mấy chỗ đối chiếu tự động hay sai **một cách im lặng**: đọc nhầm tiền về
thành tiền ra, một lượt chuyển bị hai dòng sổ cùng nhận, tự bật cho khoản kế toán đã đánh khác đi,
lệch một đồng mà vẫn coi là khớp, và giao dịch ngân hàng không có dòng sổ nào.

## Cấu trúc mã

| File | Việc |
|---|---|
| `engine.js` | Lõi: chuẩn hoá (ngày, tiền, ngân hàng, tên NCC), trạng thái, tuổi nợ, công nợ, cảnh báo, đọc số thành chữ. Chạy được ở Node nên kiểm thử được |
| `importer.js` | Đọc workbook Excel → danh sách khoản đã chuẩn hoá; nhận diện 3 kiểu bố cục |
| `saoke.js` | Đọc sao kê ngân hàng (chỉ tiền ra) và đối chiếu với sổ theo 3 mức chắc chắn. Chạy được ở Node nên kiểm thử được |
| `exporter.js` | Xuất Excel 7 sheet |
| `mau.js` | Dựng 7 mẫu biểu in A4 |
| `app.js` | Giao diện: tab, lọc, bảng, đánh dấu hàng loạt, ngăn chi tiết |
| `api.js` | Lớp gọi máy chủ — chế độ `local` (bản tĩnh) hoặc `wp` (plugin), tự đổi đường khi tường lửa chặn |
| `wp-ui.js` | Phần chỉ có ở bản WordPress: cổng PIN, hộp tài khoản, quản lý người dùng |
| `sample-data.js` | Dữ liệu mẫu để xem thử — **toàn bộ là số liệu bịa** |
| `vendor/xlsx.full.min.js` | SheetJS kèm sẵn, không gọi mạng |

> Dữ liệu thật (tên nhà cung cấp, số tài khoản, số tiền) **không nằm trong repo này** — repo công khai.
> Trang chỉ đọc file Excel anh tự chọn, ngay trên máy anh.
