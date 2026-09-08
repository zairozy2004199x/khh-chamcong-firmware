# web/

Trang web rời, **không liên quan tới firmware máy chấm công**. Để ở đây cho khỏi mất.

| File | Việc |
|---|---|
| `nha-ma-so-13.html` | **Bản gốc** — viết cho Artifact của Claude (không có `<!doctype>`, `<head>`, `<body>`) |
| `dist/nha-ma-so-13.html` | **Bản up lên web** — chạy độc lập, thả lên host nào cũng được |
| `dung.py` | Gói bản gốc thành bản up lên web: `python3 web/dung.py` |
| `so-dat-ve.gs` | Google Apps Script làm sổ chung (tuỳ chọn — xem phần Sổ đặt vé) |

Sửa thì sửa `nha-ma-so-13.html` rồi chạy lại `dung.py`. **Đừng sửa thẳng trong `dist/`**, chạy lại
script là mất.

## Hai trang trong một file

| Đường dẫn | Ai dùng | Có gì |
|---|---|---|
| (mặc định) | Khách | Chọn đêm → suất → hạng vé → giữ chỗ, ra vé kèm mã, tải vé `.svg` |
| `#quanly` | Nhân viên | Tổng quan (4 ô số + tình trạng suất theo đêm + đơn mới nhất), Đơn giữ chỗ (lọc, cho vào, huỷ), Soát vé tại cửa (gõ mã → cho vào), Đối soát theo đêm (+ xuất `.csv`) |

Hai trang **dùng chung một sổ đặt vé**, nên phải nằm chung một file — đây là lý do không tách đôi.
Huỷ đơn ở trang quản lý thì chỗ được trả lại cho trang bán vé ngay.

⚠️ Trang quản lý **không có lớp đăng nhập riêng**: ai mở được trang là gõ thêm `#quanly` vào được và
thấy tên, số điện thoại khách. Cần chặn thật thì phải làm thêm mật khẩu — chưa có.

## Up lên web

```bash
python3 web/dung.py          # ghi ra web/dist/nha-ma-so-13.html
```

Rồi kéo đúng **một file** `dist/nha-ma-so-13.html` lên host. Không cần cài gì, không cần server,
không cần cơ sở dữ liệu. Muốn nó là trang chủ thì đổi tên thành `index.html`.

Trang gọi ra ngoài đúng **một** chỗ: Google Fonts (`fonts.googleapis.com`, `fonts.gstatic.com`) để
lấy ba bộ chữ. Chặn mạng đó thì trang vẫn chạy, chỉ đổi sang chữ hệ thống.

## Sổ đặt vé — ba kiểu, trang tự chọn kiểu chạy được

| Kiểu | Khi nào | Được gì |
|---|---|---|
| Kho chung của Artifact | Mở trên claude.ai | Mọi người thấy cùng số chỗ, tự cập nhật |
| Google Apps Script | Có điền `CAUHINH.so` | Một sổ chung cho mọi máy, đọc/ghi vào Google Sheet |
| Trên máy đang mở | Không điền gì | **Mỗi máy một sổ riêng** — máy khác không thấy đơn |

Kiểu thứ ba là mặc định của bản `dist/`: mở là chạy ngay, nhưng chỉ hợp để xem thử hoặc bán vé từ
đúng một máy. Bán vé thật cho khách vào từ điện thoại của họ thì **phải** dùng kiểu hai.

Cách bật kiểu hai: làm theo hướng dẫn ở đầu `so-dat-ve.gs`, xong mở
`nha-ma-so-13.html`, tìm khối này ở đầu phần `<script>` và điền vào:

```js
var CAUHINH = {
  so:   "",     /* dán link /exec của Google Apps Script vào đây */
  khoa: ""      /* mật khẩu khai trong Apps Script; để trống nếu không đặt */
};
```

Rồi chạy lại `python3 web/dung.py` và up lại file.

> Phần Apps Script **chưa chạy thử được** ở đây vì cần tài khoản Google của anh. Code viết theo đúng
> cách Apps Script vẫn dùng, nhưng anh cài xong hãy thử giữ một chỗ rồi mở Sheet xem có dòng mới
> chưa — có dòng là chạy.

## Sửa nội dung hay gặp

Mở `nha-ma-so-13.html`, đầu phần `<script>`:

- `DEMS` — ba đêm diễn (tên, mô tả)
- `SUATS` — giờ, canh giờ, sức chứa (`suc`), phụ thu (`phuThu`)
- `HANGS` — tên hạng vé, giá, số khách mỗi vé, mức sợ

Địa chỉ, số điện thoại, luật nhà ma nằm thẳng trong phần thân trang, tìm theo chữ là thấy.
