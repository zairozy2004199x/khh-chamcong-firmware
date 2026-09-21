# Chạy bộ thử & xem trước màn hình

Hai lệnh, chạy trước mỗi lần giao bản.

```bash
bash tools/test/chay-het.sh      # chạy HẾT bộ thử, một kết luận
bash tools/xem/xem-man.sh        # dựng màn /nhan-su/ ra ảnh mà xem
```

---

## 1. `chay-het.sh` — bài thử đỏ mà không ai chạy thì cũng như không có

Kho có hơn 50 bài thử nhưng trước 13/09/2026 không có lệnh nào chạy hết, nên thực tế mỗi lượt sửa
chỉ chạy mấy bài "có vẻ liên quan". Cái giá đã trả bằng tiền thật:

- **3.66.0 cắt mất GIÂY của chấm công bù.** Gom mọi phép đọc giờ về `VHCC_DB::gio_24()`, mà hàm ấy
  trả `HH:MM`. `08:30:15` lặng lẽ thành `08:30` — mỗi lượt chấm mất tới 59 giây, không dòng đỏ nào,
  sổ vẫn trông đúng vì ai nhìn cũng chỉ đọc tới phút. **`kiem-cham-bu.php` bắt được ngay từ bản
  ấy.** Không ai chạy, nên lỗi sống tới 3.67.0.
- **`bam-thu-trang-ghe.js` "đỏ sẵn" suốt.** Nó đòi một tham số đường dẫn chỉ ghi trong chú thích
  đầu tệp; gọi thiếu là nổ mười dòng `ERR_INVALID_ARG_TYPE`, trông y hệt bài kiểm đỏ. Nên nó bị coi
  là hỏng sẵn và không ai chạy — 42 phép bấm thật nằm đó không canh gì cả. Đã cho mặc định.

⚠️ **Bài nào cần tham số thì tự có mặc định.** `chay-het.sh` cố ý KHÔNG truyền gì, để chính đường
mặc định ấy cũng được chạy thật — chứ không phải một nhánh không ai đi tới rồi mục lúc nào không hay.

Trả mã thoát khác 0 nếu có bài đỏ, dùng được cho CI. Bỏ qua `wp-stub.php` (bệ đỡ, không phải bài
thử) và `bench-queries.php` (đo tốc độ, không có kết luận đúng/sai).

### ⚠️ Đóng gói bằng `build-plugin-zip.sh`, KHÔNG bằng `zip -qr`

13/09/2026: hai bản **3.69.0** và **3.69.1** đã giao đi với `goc/` **nằm trong bản cài** — Code.gs
+ Index.html, 1,3 MB. Trên hosting chúng nằm dưới `wp-content/plugins/…` và **đọc được từ web**
bằng một địa chỉ đoán ra được, tức công bố cấu trúc bảng và cách tính lương của cả chuỗi để đổi
lấy đúng con số không.

Nguyên nhân: đóng gói bằng `zip -qr` thô, rồi soát bằng `diff -rq zip nguồn`. **Phép soát ấy bảo
đảm ra sai** — nó đòi bản cài khớp y hệt cây nguồn, trong khi trình đóng gói *cố ý* loại bớt. Soát
càng chặt càng chắc chắn sai.

`test-cham-cong.php` có canh chuyện này, nhưng nó **tự đóng gói lại rồi mới soát** — nên nó canh
trình đóng gói, không canh cái tệp đang nằm trong `dist/`. Hai bản lọt vẫn xanh hết bài.

Nay `chay-het.sh` soi **đúng tệp sẽ được commit và gửi đi**, và chạy **trước** khi có gì kịp dựng
lại nó.

---

## 2. `xem-man.sh` — bộ thử đếm chuỗi không nhìn được màn hình

Dựng trang bằng **chính hàm của plugin** (`VHCC_TrangNS::phuc_vu()`) với bệ đỡ WordPress giả của bộ
thử, rồi mở bằng Chromium. Không chép lại giao diện — chép ra là dựng một bản sao, mà bản sao thì
đẹp kể cả khi trang thật đã hỏng.

Bản 3.69.0 **xanh cả 80 phép thử** của màn nhân sự. Mở ra xem thì lòi ra hai lỗi không phép thử nào
bắt nổi, vì cả hai đều không phải chuyện nội dung:

1. Dải đếm nói *"8 người đang trôi theo cơ sở — đó là trạng thái ĐÚNG cho nhân viên quầy"*, trong
   khi 2 trong 8 người ấy là người hệ **không** suy ra mảng. Con số không sai; **câu chữ** sai —
   màn hình đang trấn an về đúng những trường hợp cần đụng tay.
2. Ô xổ bị `max-width:170px` cắt cụt đuôi, che mất đúng phần nhãn «theo cơ sở → …» vốn là lý do cái
   nhãn ấy tồn tại.

Phép thử đếm chuỗi thì cả hai đều xanh: chuỗi **có** trong HTML. Nó chỉ không đọc được, hoặc đọc
được mà nói sai về ai.

Lệnh này in ra ba thứ:

- **Dải đếm đang nói gì** — đọc chữ nhanh hơn soi ảnh, và lỗi (1) là lỗi chữ.
- **Từng người, hệ đang hiểu mảng nào** — soát được cả mười cảnh trong một màn hình.
- **Ô nào đang bị cắt mất chữ** — dò bằng `scrollWidth > clientWidth`, đúng lỗi (2). Đừng bắt người
  soi ảnh tìm chữ cụt.

Dữ liệu mẫu trong `tools/xem/dung-man.php`: **mỗi dòng là một cảnh đã từng sai**, không phải dữ liệu
cho đẹp — người làm hai mảng, người chỉ QL (không được kéo mảng theo), cơ sở chưa khai mảng, người
chưa gắn cơ sở, người đã ghim tay. Thêm cảnh mới thì thêm vào đó.
