# web/

Trang web rời, **không liên quan tới firmware máy chấm công**. Để ở đây cho khỏi mất.

| File | Việc |
|---|---|
| `nha-ma-so-13.html` | Trang bán vé nhà ma "Số 13 Hàng Lược" — chọn đêm / chọn suất / chọn hạng vé, giữ chỗ và xuất vé kèm mã |

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
