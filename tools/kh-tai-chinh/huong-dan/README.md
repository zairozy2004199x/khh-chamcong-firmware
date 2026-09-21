# Bản dựng hướng dẫn sử dụng

`huong-dan.html` + `huong-dan.css` + `anh/` dựng ra
`../Tai-Chinh-KH-Huong-dan-su-dung.pdf`.

```bash
node in-pdf.mjs      # cần playwright; chromium ở /opt/pw-browsers/chromium
```

Ảnh trong `anh/` chụp bằng Chromium ở bề ngang 1480px, `deviceScaleFactor` 1.5,
**cắt cao tối đa 1250px CSS** — cao hơn thì ảnh phải thu nhỏ để vừa một trang
A4 và chữ trong bảng không đọc được nữa. Ảnh giảm còn 192 màu: bảng số là chữ
đen trên nền trắng với vài mảng xanh/đỏ, giảm màu không nhìn ra khác biệt mà
tệp nhẹ đi ba lần.

Số trong ảnh lấy từ **dữ liệu mẫu** (`KHTC_Mau`) chạy trên bộ giả lập trong
`../tests/`, không phải số thật.

Sửa xong nhớ đổi số bản ở chân trang trong `in-pdf.mjs` cho khớp
`KHTC_VERSION`.
