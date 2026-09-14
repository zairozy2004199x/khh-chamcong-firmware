# Phép kiểm trang Ghế

> 🔴 **Thư mục này nằm NGOÀI `vhcp-ghe/` là có chủ ý.** Lệnh build là
> `zip -qr dist/vhcp-ghe.zip vhcp-ghe`, tức là **mọi thứ** trong thư mục plugin đều vào zip. Để
> file kiểm ở trong đó thì nó theo lên máy chủ thật — vừa thừa, vừa là mã Node (`require`) nằm
> giữa một plugin PHP. Đặt ở `tools/` thì không phải nhớ thêm cờ `-x` nào, và không ai quên được.

## `kiem-diadiem-misa.js` — hai cột Unit ID / Tên MISA trên bảng Địa điểm

Mở khối JS thật của `class-vhg-trang.php` trong Chromium, giả lập `XMLHttpRequest` nên
**chạy đúng hàm `goi()` thật**, kể cả nhánh lỗi — không phải bản chép lại.

```bash
# 1. tách khối JS ra (khối thứ 5 = <<<'JS' ở class-vhg-trang.php)
# 2. thêm cửa sổ __T ra ngoài IIFE  (chỉ trong bản copy để kiểm, KHÔNG sửa vào nguồn)
NODE_PATH=/opt/node22/lib/node_modules node kiem-diadiem-misa.js
```

Kiểm 5 điều, mỗi điều tương ứng một cách hỏng đã thấy thật trong repo này:

| Kiểm | Hỏng nếu không kiểm |
|---|---|
| Số `<th>` = số `<td>` ở **mọi** hàng | Thêm cột mà quên hàng "(chưa gán)" hoặc bảng gập "cơ sở chưa có ghế" → mọi ô lệch sang trái một cột |
| Giá trị nạp đúng theo tên cơ sở | Ghép tên sai → Unit ID của cơ sở này hiện ở cơ sở khác |
| Lưu gửi đúng **tên cơ sở** đang sửa | Gửi nhầm tên → ghi Unit ID vào cơ sở khác, im lặng |
| Máy chủ từ chối → **giữ chữ vừa gõ** + viền đỏ | Tự trả về giá trị cũ = đúng lỗi 2.37.1 "gõ số mới tự về cũ" |
| Không đủ quyền → **ẩn hẳn** hai cột | Để lại ô nhập gõ được mà lưu không được — tệ hơn không có ô |

⚠️ Harness phải giả lập ở tầng `XMLHttpRequest`, **không** thay hàm `goi()`. `goi()` chỉ có
**ba** tham số và trả lỗi qua **chính** hàm gọi lại (`{ok:false, error:…}`), không có hàm xử lý
lỗi riêng. Thay `goi()` bằng bản giả là bỏ qua đúng cái luật ấy — và code gọi sai luật vẫn xanh.


## `kiem-rong-cot-misa.js` — cột Tên MISA có đọc hết tên không

Đo ở ba bề rộng màn (1500 / 1280 / 1024) và hỏi đúng một câu: **ô có bị cắt chữ không**
(`scrollWidth > clientWidth`), kèm kiểm trang không tràn ngang.

```bash
NODE_PATH=/opt/node22/lib/node_modules node kiem-rong-cot-misa.js
```

⚠️ Harness phải nạp **CSS thật** của plugin (`spa.css` tách từ khối `<<<'CSS'`), không dùng CSS
rút gọn tự viết. Chiều rộng cột do `.misa-ten{min-width:240px}` quyết định — kiểm bằng CSS khác
là đo một cái bảng khác, và nó sẽ xanh kể cả khi bản thật vẫn cắt chữ.


## `kiem-sap-xep-diadiem.js` — 5 kiểu sắp xếp bảng Địa điểm

```bash
NODE_PATH=/opt/node22/lib/node_modules node kiem-sap-xep-diadiem.js
```

| Kiểm | Hỏng nếu không kiểm |
|---|---|
| A→Z địa điểm · chưa có Unit ID lên đầu · A→Z Unit ID · A→Z tên MISA · nhiều ghế nhất | Kiểu nào đó lặng lẽ không đổi thứ tự |
| Ô trống dồn **xuống cuối** ở kiểu Unit ID / tên MISA | 19 hàng rỗng chiếm hết màn |
| Hàng **"(chưa gán)" luôn cuối** ở mọi kiểu | Người đọc tưởng có một địa điểm tên "(chưa gán)" |
| Bảng gập "cơ sở chưa có ghế" cũng được sắp | Hai bảng hai thứ tự khác nhau |
| Gõ Unit ID xong **KHÔNG tự nhảy chỗ**, chỉ đổi khi chọn lại kiểu sắp | Đang điền từ trên xuống mà hàng nhảy đi là không ai điền nổi |
| Nhớ lựa chọn qua `localStorage` | Mỗi lần tải lại phải chọn lại |

### 🔴 Harness PHẢI gọi `noi()`, không gọi thẳng `misaNap()`

Mọi sự kiện của trang gắn trong `noi()` — đó là đường thật (`if (TAB === 'quan-ly') { … noi(); }`).
Bản đầu của phép kiểm này gọi thẳng `misaNap()` nên ô chọn sắp xếp **không có handler nào**, và
phép kiểm **xanh oan cho một cái nút chết**.

Muốn gọi `noi()` thì `trang.html` phải dựng cả **khung đầu trang** (`#lam-moi`, `#thoat`) —
`noi()` gán `onclick` vào hai nút đó **không có bảo vệ null**, thiếu là nó văng giữa chừng và mọi
thứ gắn SAU đó không bao giờ được gắn.

### Dữ liệu thử phải khác nhau thật

Hai lần bản nháp báo hỏng oan vì dữ liệu thử, không phải vì code:
- mọi cơ sở cùng **3 ghế** → kiểu "nhiều ghế nhất" không thể đổi thứ tự;
- **mã ghế trùng nhau** → app bật khối cảnh báo đỏ (đúng chức năng), đẩy bảng ra khỏi ảnh chụp.


## `kiem-co-so-trung.js` — cơ sở trùng & cơ sở đã đóng cửa

| Kiểm | Hỏng nếu không kiểm |
|---|---|
| Dò đúng cặp gần trùng (PEAR/PEARL = 1, VINCOM 1/2 = 1, khác hẳn ≥ 3) | Bỏ sót cặp trùng, hoặc cảnh báo bừa |
| Gộp gửi **đúng chiều** (`nguon` = cơ sở bỏ, `dich` = cơ sở giữ) | Ghế dồn về đúng cái lẽ ra phải bỏ — mà tên cũ thì đã xoá |
| Hỏi xác nhận **đúng hai lần** trước khi xoá một cơ sở | Lỡ tay một cú bấm là mất cơ sở |
| Cơ sở đóng cửa **không nằm ở bảng chính**, kể cả khi 0 ghế | Vẫn hiện ra — đúng cái đang muốn dẹp |
| Khối "đã đóng cửa" **gập sẵn** | "Ẩn" mà vẫn mở toang thì không phải ẩn |
| Bấm 🚪 trên cơ sở đang đóng gửi `dong:0` (mở lại), không phải `dong:1` | Không mở lại được |

### Hai bẫy của harness đã ghi lại ở đây

- **Phải `setTAB('quan-ly')`.** Sau mỗi tác vụ `lam()` gọi `tai()` → `ve()` vẽ lại **cả trang** theo
  `TAB` hiện tại; để mặc định `doi-soat` thì bảng Địa điểm biến mất và nút tiếp theo không còn.
- **Vẽ lại màn sạch trước mỗi thao tác.** Bấm tiếp trên cái xác do lần vẽ lại bằng dữ liệu giả để
  lại là đang kiểm một thứ khác hẳn. Và stub `so_lieu` phải đủ `ai` / `cho` / `choGan` — thiếu là
  `ve()` ném lỗi, trông y như lỗi sản phẩm.


## Xoá hẳn mã ghế chưa gán — kiểm ở **hai tầng**

```bash
php  kiem-xoa-ma-chua-gan.php     # hàm PHP, giả lập $wpdb — chốt an toàn thật nằm ở đây
NODE_PATH=/opt/node22/lib/node_modules node kiem-xoa-ma-chua-gan.js   # giao diện
```

Đây là thao tác **không hoàn tác được**, nên kiểm cả hai tầng chứ không chỉ giao diện.

**Tầng PHP** (14 phép kiểm): xem trước **không chạy một câu DELETE nào** · mã còn dữ liệu bị giữ và
lý do nói rõ bảng nào bao nhiêu dòng · mã đang thuộc cơ sở bị giữ · câu `DELETE` vẫn kèm
`coso_id=0` (chốt thứ hai, phòng khi vòng lọc phía trên sai) · danh sách rỗng / mã không tồn tại /
mã trùng.

> Test nạp **đúng hàm thật** từ `class-vhg-may.php` bằng `eval`, không chép lại thân hàm. Chép lại
> là kiểm một bản sao — bản thật sửa gì cũng không ai biết.

**Tầng giao diện**: đúng hai lượt gọi (xem trước `that:0` rồi mới `that:1`) · mã **đã ẩn** và mã
**đang thuộc cơ sở** không lọt vào lệnh · mã còn dữ liệu không được gửi đi xoá · hộp xác nhận
**liệt kê đúng những mã sắp mất**.


## `kiem-cuong-che-xoa.js` — đường cưỡng chế của Quản trị

Ba ca, và hai trong ba ca là **không được xoá gì**:

| Ca | Phải xảy ra |
|---|---|
| Gõ **sai** chuỗi xác nhận | chỉ có lượt xem trước, **không** lượt nào `that:1` |
| Bấm **Huỷ** ở ô gõ | như trên |
| Gõ **đúng** `XOA HAN` | lượt 2 có `that:1` **và** `buoc_qua:1`, đúng danh sách mã |

Và hộp thoại phải nói đủ ba điều trước khi cho gõ: gợi ý **✎ ĐỔI MÃ** là việc đúng hơn · **tổng
tiền KHÔNG đổi** · **đừng tạo lại mã đã xoá**.

> ⚠️ Kiểm nội dung hộp thoại thì giữ **nguyên văn**. Bản đầu cắt chuỗi ở 400 ký tự rồi đi tìm một
> câu nằm sau chỗ cắt — báo hỏng oan.


## Sửa thẳng ô tiền — kiểm ở **hai tầng**

```bash
php  kiem-sua-o-tien.php    # hàm VHG_KeToan::sua() thật, giả lập $wpdb
NODE_PATH=/opt/node22/lib/node_modules node kiem-sua-o-tien.js
```

**Tầng PHP** — mệnh đề quan trọng nhất: **gõ QR trên dòng đang ghi đè thì số ghi đè phải GIỮ
NGUYÊN**, không rơi về công thức. Hỏng chỗ này là tiền sai mà **tổng vẫn khớp**, nên đối chiếu
không bắt được. Kiểm thêm: gõ Tiền mặt → ghi đè + gắn dấu vào ghi chú; xoá trắng (`bo_ghi_de`) →
về `Actual − QR`, `dieu_chinh` về 0, **dọn dấu** khỏi ghi chú; dòng thường không tự sinh dấu ghi đè.

**Tầng giao diện**: đúng **4 cột** cho gõ (Trước · Sau · Tiền mặt · QR), **Actual và Nộp không**;
ba payload đúng khoá; khoá ngày → **0** ô nhập.

> ⚠️ Bảng dựng bằng `innerHTML` (tiêu đề) + `appendChild` (dòng) nằm chung **một** `tbody`, nên
> hàng 0 là **tiêu đề**. Bản đầu đọc hàng 0 → mọi ô "không có input" → báo hỏng oan.


## `kiem-tim-nhan-vien.js` — ô tìm ở tab PIN báo cáo

Dùng đúng những cái tên có thật trong ảnh anh Thắng gửi (có dấu, có ngoặc, HOA lẫn thường).

| Gõ | Phải ra |
|---|---|
| `thang` (không dấu) | Hà Quang **Thắng** — và **không** ra "Thanh" |
| `Thắng` (có dấu) | y hệt |
| `DUONG` (HOA, không dấu) | DƯƠNG TRUNG TÍN · Huỳnh Ngọc Thanh (cơ sở có "BÌNH DƯƠNG") |
| `chau` | Đặng thị ngọc **châu** |
| PIN `594619` | đúng người đó |
| `ben tre` | khớp cả tên lẫn cơ sở |
| chuỗi vô nghĩa | 0 hàng, bộ đếm nói *"không thấy ai khớp"* |

> ⚠️ Bản đầu em đoán `ben tre` ra **2** người rồi báo hỏng oan — thực tế chỉ 1. Phép kiểm phải
> **đếm từ dữ liệu thử**, không đoán bằng cảm tính.

## `dung.sh` — dựng sân kiểm

```bash
bash tools/kiem-ghe/dung.sh        # -> /tmp/ghetest/{trang.html,k5t.js,spa.css}
```

Cắt khối JS **thật** ra khỏi `class-vhg-trang.php` theo mốc `<<<'JS'` / `JS;`, rồi chèn cửa sổ
`window.__T` **trước** dòng đóng `})();`.

> ⚠️ Phải chèn **trước** dòng đóng IIFE, không phải nối vào cuối file. Nối vào cuối thì `TOK`,
> `D`, `QL_SUA` trong cửa sổ đó là **biến toàn cục khác**, gán vào không chạm được gì bên trong —
> phép kiểm sẽ xanh trong khi không hề điều khiển được trang.

`k5t.js` / `spa.css` là bản dựng, `.gitignore` ở thư mục này lo phần không cho chúng vào repo.

## `kiem-luu-ten.js` + `kiem-luu-ten.php` — Lưu tên khi bấm nút (2.82.0)

Anh Thắng 14/09/2026: *"khi nào bấm lưu mới nhé, chứ cứ gõ vào phát bấm chuột ra nhảy đi đâu
mất"* và *"Không lưu được tên thường gọi"*.

```bash
bash tools/kiem-ghe/dung.sh
NODE_PATH=/opt/node22/lib/node_modules node tools/kiem-ghe/kiem-luu-ten.js   # 24 phép
php tools/kiem-ghe/kiem-luu-ten.php                                         # 15 phép
```

| Kiểm | Hỏng nếu không kiểm |
|---|---|
| Gõ xong rời ô **không** gọi máy chủ | Quay lại lối tự lưu → mỗi ô là một lần vẽ lại cả trang, đúng cái "nhảy đi đâu mất" |
| Vẽ lại giữa chừng vẫn giữ chữ đang gõ dở | Một lượt tự làm mới ập vào là mất trắng phần đang nhập |
| Gói gửi đi **chỉ** mang khoá đã sửa | Sửa tên thường gọi mà ghi đè `ten_khai` → tên trên sao kê hỏng, tiền cũ thôi ghép được vào ghế |
| Lưu hỏng thì **giữ** giỏ sửa | Mạng rớt là nuốt mất công gõ, mà màn hình lại trông như chưa ai gõ gì |
| Màn lấy tên **đã chuẩn hoá** của máy chủ | Giữ chữ thô → lần vẽ sau lại báo "chưa lưu" dù đã lưu |
| `ten_goi=''` vẫn ghi xuống CSDL | `empty('')` là true → xoá tên xong báo "đã lưu" mà tên còn nguyên |
| **Mọi payload danh sách ghế đều kèm `ten_goi`** | 🔴 Đúng gốc lỗi *"Không lưu được tên thường gọi"*: CSDL lưu đúng, chỉ payload không gửi ra, nên ô luôn vẽ lại rỗng — soi bảng dữ liệu không thấy gì sai |
| Sắp xếp: tên thiếu gạch nối vẫn đúng chỗ | `localeCompare` xếp `-` trước chữ số → `ESTELLA4` rơi xuống sau `ESTELLA-6` |

> Thay cho `kiem-ten-thuong-goi.js` (đã xoá ở 2.82.0): bài đó kiểm lối **tự lưu khi rời ô**, mà
> chính lối ấy là thứ bị bỏ.
