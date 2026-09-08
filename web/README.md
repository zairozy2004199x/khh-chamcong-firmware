# web/

Trang web rời, **không liên quan tới firmware máy chấm công**. Để ở đây cho khỏi mất.

| File | Việc |
|---|---|
| `nha-ma-so-13.html` | Nhà ma "Số 13 Hàng Lược": **trang bán vé** cho khách + **trang quản lý** cho nhân viên, chung một file |

## Hai trang trong một file

| Đường dẫn | Ai dùng | Có gì |
|---|---|---|
| (mặc định) | Khách | Chọn đêm → suất → hạng vé → giữ chỗ, ra vé kèm mã, tải vé `.svg` |
| `#quanly` | Nhân viên | Tổng quan (4 ô số + tình trạng suất theo đêm + đơn mới nhất), Đơn giữ chỗ (lọc, cho vào, huỷ), Soát vé tại cửa (gõ mã → cho vào), Đối soát theo đêm (+ xuất `.csv`) |

Hai trang **dùng chung một sổ đặt vé**, nên phải nằm chung một file — đây là lý do không tách đôi.
Huỷ đơn ở trang quản lý thì chỗ được trả lại cho trang bán vé ngay.

⚠️ Trang quản lý **không có lớp đăng nhập riêng**: ai mở được link là vào được `#quanly` và thấy
tên, số điện thoại khách. Chỉ đưa link cho người trong nhà.

Sổ đang có sẵn 9 đơn thử (mã bắt đầu bằng `DEMO-`) để xem trang chạy. Xoá bằng nút trong
**Đối soát theo đêm → Dữ liệu thử**.

## Chạy

File này viết cho **Artifact của Claude**: nó nhận `<!doctype>`, `<head>`, `<body>` từ bên ngoài,
nên trong file chỉ có `<title>`, `<style>`, phần thân và `<script>`.

- Trên Artifact: số chỗ còn lại lưu ở kho dùng chung (`db`) — mọi người xem cùng lúc thấy cùng một
  con số; nút "Tải vé về máy" dùng `downloads` để xuất vé ra file `.svg`.
- Mở bằng trình duyệt thường: vẫn chạy, nhưng **vé chỉ lưu trên máy đó** (`localStorage`) và số chỗ
  luôn hiện sức chứa tối đa. Muốn mở thẳng thì bọc thêm:

```html
<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"></head><body>
<!-- dán nội dung nha-ma-so-13.html vào đây -->
</body></html>
```
