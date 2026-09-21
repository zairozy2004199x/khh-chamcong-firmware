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

## 7. Firmware của ghế

Đã chuyển xong: `esp32_ghe_massage/`. Ghế nay chỉ nói chuyện với website.

### 🔴 Việc phải làm NGAY, không chờ nạp firmware

Bản firmware cũ có **Firebase database secret ghi thẳng trong mã nguồn**:

```
FB_SECRET = "C1hH…"   (đã gỡ khỏi repo, nhưng nó từng nằm trong file anh gửi)
```

Khoá đó có **quyền admin trên cả project Firebase** — đọc/ghi/xoá được mọi thứ, kể cả nhánh của
hệ thống khác dùng chung project đó. Vào **Firebase Console → Project settings → Service accounts
→ Database secrets** và **vô hiệu nó**. Bản mới không dùng Firebase nữa nên vô hiệu xong là hết
chuyện. Đổi luôn mật khẩu WiFi `KHHCM` — nó cũng nằm trong file đó.

### Một bản .bin cho mọi ghế

Bản cũ nạp cứng `CHAIR_ID` lúc biên dịch → mỗi ghế một file .bin, và cập nhật từ xa mất hết ý
nghĩa. Nay **ghế khai MAC, máy chủ nói nó là ghế số mấy**.

Ghế mới cắm điện sẽ:
1. hiện **"GHE CHUA DUOC GAN MA"** kèm MAC ngay trên màn;
2. tự hiện ra trong **Máy & cơ sở** với mã tạm bắt đầu bằng `?`;
3. anh gán mã thật (kèm MAC) → ghế nhận trong ~30 giây và nhớ vào bộ nhớ trong.

### Ghế hỏi gì

| việc | gọi | trả về |
|---|---|---|
| nhịp sống + lấy cấu hình | `{"viec":"nhip","mac":…,"trang_thai":…}` | `maMay, chuaGan, gia, phut, soTk, bin, coTien, coLenh` |
| lấy lượt đã trả tiền | `{"viec":"luot"}` | `{"co":1,"so_tien":20000,"phut":6}` |
| lấy lệnh bật/tắt | `{"viec":"lenh"}` | `{"co":1,"viec":"on","phut":6}` |
| báo sổ tiền mặt | `{"viec":"tien_mat","so_tien":…,"ref":…}` | `{"ok":true}` |

Nhịp gộp **bốn câu hỏi vào một lượt** (trước là bốn lượt Firebase riêng). Trên 4G mỗi lượt
AT-HTTP mất 3-6 giây — đó là khác biệt giữa "ghế phản ứng trong 2 giây" và "10 giây".

### Ba chỗ giữ nguyên vì chúng đúng

- **Tiền mặt KHÔNG chờ máy chủ.** Máy đếm tiền đã xác thực tờ tiền → ghế chạy ngay, ghi sổ sau.
  Mất mạng thì ghế vẫn phục vụ được. Lượt ghi sổ mang mã ổn định nên gửi lại không cộng đôi.
- **Ân hạn 20 giây sau khi huỷ.** Khách bấm huỷ rồi tiền mới về — ghế vẫn chạy. Khách đã trả tiền
  thì phải được massage.
- **Hai nhân tách bạch.** Mạng chạy nhân 0, màn hình + cảm ứng + đếm ngược chạy nhân 1. Lệnh 4G
  chặn 5 giây không làm đơ màn.

### Đổi số tài khoản không phải nạp lại firmware

`soTk` / `bin` / `gia` / `phut` đều lấy từ máy chủ trong lượt nhịp. Sửa trên web là mọi ghế theo
trong khoảng một phút.

⚠️ Ghế **chưa có đường cập nhật từ xa** (không đọc `/ota` như máy chấm công) — lần này phải nạp
USB. CI vẫn biên dịch mỗi lần đẩy và để sẵn file .bin trong artifact.

