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
