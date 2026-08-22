# Cài hệ thống Ghế massage QR lên host

*Plugin `vhcp-ghe` 1.0.0 — thay toàn bộ Apps Script + Firebase của app ghế massage*

## 1. Luồng chạy: trước và sau

| | trước | nay |
|---|---|---|
| khách quét QR trả tiền | ngân hàng → webhook Apps Script | ngân hàng → `khmatrix.com/ghe-tien` |
| ghi doanh thu | Firebase `/ghe/revenue/<ref>` | bảng MySQL `vhg_thu`, khoá `ref` |
| báo ghế chạy | Firebase `/ghe/pay/<ghế>/<mã>` | bảng `vhg_cho`, ghế hỏi `/ghe-may` |
| ghế báo còn sống | Firebase `/ghe/status` | việc `nhip` trên `/ghe-may` |
| bật/tắt ghế từ xa | Firebase `/ghe/cmd` | bảng `vhg_lenh` |
| xem doanh thu | trang `/exec` gác bằng PIN 6 số | wp-admin → **Ghế Massage** |

## 2. 🔴 Hai khoá phải khai trong `wp-config.php`

```php
define( 'VHG_KHOA_WEBHOOK', '…chuỗi ngẫu nhiên dài…' );        // dán vào ô webhook của bên gửi
define( 'VHG_KHOA_MAY',     '…chuỗi ngẫu nhiên KHÁC…' );       // nạp vào ESP32 của ghế
```

**Không khai thì cổng ĐÓNG, không phải mở.** Mọi lượt bắn tới đều bị từ chối (và vẫn ghi vào
nhật ký, để anh thấy là "bị chặn" chứ không phải "chưa ai bắn").

**Hai khoá phải KHÁC NHAU.** Khoá webhook đi trên đường dẫn (`?token=…`) vì bên gửi không cho đặt
header tuỳ ý — nghĩa là nó nằm trong nhật ký máy chủ và trong ô cấu hình của bên gửi, coi như đã
lộ một phần. Dùng chung một chuỗi là lộ cái này kéo theo cái kia.

> **Bản Apps Script cũ có `DASHBOARD_PIN = '246810'` ghi thẳng trong mã.** PIN đó gác cả việc
> bật/tắt ghế lẫn xoá bản ghi doanh thu. Bản này bỏ hẳn PIN riêng: màn nằm trong wp-admin, ai xem
> phải đăng nhập WordPress. Bớt một bí mật là bớt một chỗ lộ.

## 3. Thứ tự làm

1. Cài `vhcp-ghe.zip` → Kích hoạt (tạo 8 bảng).
2. Thêm hai hằng ở trên vào `wp-config.php`.
3. Vào **Ghế Massage → Máy & cơ sở**: khai cơ sở, rồi khai từng máy.
   - **Mã máy chỉ được gồm chữ và số, không dấu, không khoảng trắng.** Mã này đi vào nội dung
     chuyển khoản mà khách gõ tay (`GHE3 T1ABC`) — có dấu là khách gõ sai và ghế không chạy.
   - **Tên trên sao kê**: tên máy như Tingo/VietQR ghi trong file Excel (VD `AMTP 03`). Khai đúng
     thì doanh thu nhập từ Excel tự gộp vào đúng máy.
   - **Mã ngân hàng (BIN)**: 970418 = BIDV. Sai BIN là QR quét ra ngân hàng khác.
4. Vào **Nhận tiền & nhật ký**, copy link webhook, dán vào ô Webhook bên VietQR/SePay.
5. Chuyển thử **1.000đ** với nội dung `GHE<mã máy> TEST1`. Nhật ký phải hiện ngay một dòng.

**Nếu nhật ký trống hoàn toàn** sau khi chuyển thử: bên gửi chưa bắn tới, hoặc tường lửa của
hosting chặn. Mở link webhook bằng trình duyệt — ra câu xác nhận cổng còn sống là đường thông,
lúc đó vấn đề nằm ở cấu hình bên gửi. Vietnix thì xin loại trừ `/ghe-tien` khỏi Imunify360.

## 4. Hai luật giữ tiền — và vì sao chúng quan trọng

**Không đếm hai lần.** Doanh thu ghi theo **mã tham chiếu** của ngân hàng, và mã đó là UNIQUE.
Nên: webhook bắn lại, nhập lại đúng file Excel, hay nhập file chồng lấn ngày — phần trùng tự hoà,
phần mới thì thêm. Nhờ vậy **"nhập lại cho chắc" là việc an toàn**.

**Không mất tiền.** Gói webhook không đọc được thì vẫn được **giữ nguyên văn** trong nhật ký, và
cổng vẫn trả 200. Trả khác 2xx là bên gửi đẩy lại vài lần rồi **tắt hẳn webhook** — lúc đó mới là
mất thật. Gói nội dung mơ hồ (`PaymentForOrder`) cũng **vào sổ**, chỉ là chưa gắn được máy.

## 5. Giao dịch "PaymentForOrder" — gắn máy sau

Phần lớn giao dịch Tingo mang nội dung vô nghĩa, không nói máy nào. Chỉ **Mã điểm bán** (VVB…)
mới nói. Cách làm:

1. Xuất file giao dịch từ Tingo → copy (**bôi đen cả dòng tiêu đề**) → dán vào ô **Nhập bảng
   giao dịch**.
2. Hệ thống **tự học**: dòng nào có tên máy trong nội dung thì nó ghi nhớ (Mã điểm bán → tên máy),
   rồi áp cho những dòng cùng mã mà không có tên.
3. Máy nào chưa học được thì xuất **Danh sách Voice Box** từ Tingo và dán vào ô thứ hai — đó là
   **khai tay**, và máy tự học sẽ không ghi đè lên.
4. Bấm **Áp lại bản đồ** để gắn tên cho những giao dịch cũ chưa rõ máy — khỏi nhập lại cả file.

## 6. Bật ghế bằng tay

Màn đối soát có nút **Bật / Tắt** từng ghế. Đây là **cho không một lượt massage**, nên hệ thống
bắt buộc ghi ai bấm và lúc nào — cuối tháng còn giải thích được vì sao một ghế chạy nhiều hơn số
tiền thu. Tối đa **60 phút** một lệnh: gõ nhầm số 0 là ghế chạy suốt đêm mà không ai ở đó để tắt.

## 7. Còn phải làm: firmware của ghế

Plugin đã sẵn sàng, **firmware ESP32 của ghế thì chưa** — mã nguồn của nó không nằm trong repo
này. Ghế cần đổi ba chỗ:

| việc | gọi gì |
|---|---|
| báo còn sống, hỏi giá/phút | POST `/ghe-may` `{"key":…,"ma_may":"3","viec":"nhip","trang_thai":"idle"}` |
| lấy lượt đã trả tiền | POST `{"viec":"luot"}` → `{"co":1,"ma_lenh":"T1ABC","phut":6}` |
| lấy lệnh bật/tắt tay | POST `{"viec":"lenh"}` → `{"co":1,"viec":"on","phut":6}` |

Nhịp trả về `coTien` và `coLenh` để ghế chỉ phải gọi thêm khi **thật sự có việc** — ghế đặt ở cửa
hàng, phần lớn thời gian là rảnh.

⚠️ **Lượt được đánh dấu "đã nhận" ngay lúc phát, không chờ ghế báo chạy xong.** Ghế mất điện giữa
chừng thì khách mất lượt — nhưng nếu không đánh dấu, ghế khởi động lại là chạy lại lượt cũ, và cứ
thế mãi. Giữa "mất một lượt hiếm khi" và "một lượt chạy vô hạn", chọn cái thứ nhất; cái thứ hai
còn làm hỏng cả bảng đối soát. Bù tay bằng nút **Bật**.
