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
